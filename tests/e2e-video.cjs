#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('./node_modules/playwright-core');

const root = path.resolve(__dirname, '..');
const evidence = path.join(__dirname, 'evidencias', 'actual');
const base = process.env.MENU08_QA_URL || 'http://127.0.0.1:8088';
const env = { ...process.env, MYSQL_PWD: 'Menu08LocalQA2026' };
fs.mkdirSync(evidence, { recursive: true });

function mysql(sql, database = 'menu08') {
  const args = ['--protocol=TCP', '-h127.0.0.1', '-P13306', '-uroot', '--default-character-set=utf8mb4', '--init-command=SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci', '--batch', '--skip-column-names'];
  if (database) args.push(database);
  const result = spawnSync('mysql', args, { input: sql, encoding: 'utf8', env });
  if (result.status !== 0) throw new Error(result.stderr || 'Falló MySQL');
  return result.stdout.trim();
}

function seed() {
  for (const file of ['esquema.sql', 'datos_iniciales.sql', 'datos_festin_rodante.sql', 'datos_pruebas.sql']) {
    const sql = fs.readFileSync(path.join(root, 'menu08_app', 'basedatos', file), 'utf8');
    const args = ['--protocol=TCP', '-h127.0.0.1', '-P13306', '-uroot', '--default-character-set=utf8mb4', '--init-command=SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'];
    if (file !== 'esquema.sql') args.push('menu08');
    const result = spawnSync('mysql', args, { input: sql, encoding: 'utf8', env });
    if (result.status !== 0) throw new Error(`${file}: ${result.stderr}`);
  }
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
  console.log(`  ✓ ${message}`);
}

async function signIn(page, role) {
  await page.goto(`${base}/ingresar`);
  await page.locator('[name=correo]').fill(`pruebas.${role}@menu08.local`);
  await page.locator('[name=contrasena]').fill('Menu08*Demo2026');
  await page.locator('button[type=submit]').click();
  const destination = { cajero: '/caja', produccion: '/svp' }[role];
  await page.waitForURL(`**${destination}**`);
}

async function main() {
  console.log('E2E de caja → producción → pantalla pública → cierre de turno (Chromium)');
  seed();
  const productId = mysql("SELECT id FROM productos WHERE nombre='Papas de prueba' AND food_truck_id=(SELECT id FROM food_trucks WHERE slug='truck-de-pruebas')");
  assert(/^\d+$/.test(productId), 'el producto de prueba existe en la base aislada');
  const browser = await chromium.launch({ headless: true });
  const cashier = await browser.newContext({ viewport: { width: 1280, height: 720 }, locale: 'es-CO' });
  const production = await browser.newContext({ viewport: { width: 1280, height: 720 }, locale: 'es-CO' });
  const publicView = await browser.newContext({ viewport: { width: 1280, height: 720 }, locale: 'es-CO' });
  try {
    const cash = await cashier.newPage();
    await signIn(cash, 'cajero');
    await cash.locator('[name=base_inicial]').fill('50000');
    await cash.locator('button[type=submit]').filter({ hasText: 'Abrir' }).click();
    await cash.waitForURL('**/caja');
    await cash.locator(`[data-venta-agregar="${productId}"]`).click();
    await cash.locator('[data-venta-cobrar]').click();
    await cash.locator('[data-venta-recibido]').fill('10000');
    await cash.locator('[data-venta-confirmar]').click();
    await cash.waitForURL('**/caja/comprobante/*');
    const orderId = new URL(cash.url()).pathname.split('/').pop();
    assert(mysql(`SELECT CAST(total AS CHAR) FROM ordenes WHERE id=${Number(orderId)}`) === '6500.00', 'la venta de $6.500 quedó guardada');
    await cash.screenshot({ path: path.join(evidence, 'CP-AUTO-E2E-01-node-venta.png'), fullPage: true });

    const kitchen = await production.newPage();
    await signIn(kitchen, 'produccion');
    const publicPage = await publicView.newPage();
    await publicPage.goto(`${base}/turnos/truck-de-pruebas`);
    const orderNumber = mysql(`SELECT numero FROM ordenes WHERE id=${Number(orderId)}`);
    assert(await kitchen.locator(`[data-svp-orden="${orderId}"]`).count() === 1, 'producción ve la orden pendiente');
    assert(!(await publicPage.locator('body').innerText()).includes(orderNumber), 'la pantalla pública oculta la orden pendiente');

    for (const state of ['en_preparacion', 'lista', 'entregada']) {
      await kitchen.locator(`[data-svp-orden="${orderId}"] [data-svp-avance]`).click();
      if (state === 'entregada') {
        await kitchen.locator(`[data-svp-orden="${orderId}"]`).waitFor({ state: 'detached' });
      } else {
        await kitchen.locator(`[data-svp-orden="${orderId}"]`).waitFor({ state: 'visible' });
        await kitchen.waitForFunction(({ id, state }) => document.querySelector(`[data-svp-orden="${id}"]`)?.dataset.svpEstado === state, { id: orderId, state });
      }
      assert(mysql(`SELECT e.codigo FROM ordenes o JOIN estados_orden e ON e.id=o.estado_id WHERE o.id=${Number(orderId)}`) === state, `la base registra el estado ${state}`);
      await publicPage.waitForTimeout(4600);
      const visible = (await publicPage.locator('body').innerText()).includes(orderNumber);
      assert(visible === (state !== 'entregada'), `la pantalla pública ${state === 'entregada' ? 'retira' : 'muestra'} la orden`);
      await kitchen.screenshot({ path: path.join(evidence, `CP-AUTO-E2E-01-node-${state}.png`), fullPage: true });
    }

    const shift = await cashier.newPage();
    await shift.goto(`${base}/caja/turno`);
    await shift.locator('[name=total_declarado]').fill('56500');
    await shift.locator('button[type=submit]').click();
    await shift.locator('.dialogo').getByRole('button', { name: 'Cerrar turno', exact: true }).click();
    await shift.waitForURL('**/caja/turnos/*');
    const closed = mysql("SELECT estado FROM turnos_caja WHERE food_truck_id=(SELECT id FROM food_trucks WHERE slug='truck-de-pruebas') ORDER BY id DESC LIMIT 1");
    assert(closed === 'cerrado', 'el turno de caja terminó cerrado');
    await shift.screenshot({ path: path.join(evidence, 'CP-AUTO-E2E-01-node-cierre.png'), fullPage: true });
    console.log('E2E aprobado: las operaciones de caja, producción, pantalla pública y cierre pasaron.');
  } finally {
    await browser.close();
  }
}

main().catch(error => {
  console.error(`E2E fallido: ${error.stack || error.message}`);
  process.exitCode = 1;
});
