"""Venta completa por interfaz, tres sesiones separadas y cierre de turno."""
import ejecutar_http as q
from ejecutar_http import *
from ejecutar_e2e import login,shot
from playwright.sync_api import sync_playwright,expect
os.environ.setdefault('PLAYWRIGHT_BROWSERS_PATH','/tmp/menu08-qa-runtime/browsers')
q.CASES['CP-AUTO-E2E-01']={'nombre':'Flujo E2E completo: caja, producción, turnos y cierre por interfaz'}
def full(pw,engine):
    seed();b=getattr(pw,engine).launch();cash=b.new_context(viewport={'width':1280,'height':720});prod=b.new_context(viewport={'width':1280,'height':720});pub=b.new_context(viewport={'width':1280,'height':720});p=cash.new_page();k=prod.new_page();v=pub.new_page()
    q.CURRENT['navegador']=engine;q.CURRENT['herramienta']='Playwright / '+engine
    login(p,'cajero');p.locator('[name=base_inicial]').fill('50000');p.locator('button[type=submit]').filter(has_text='Abrir').click();p.wait_for_url('**/caja');p.locator('[data-venta-agregar="'+str(pid('Papas de prueba'))+'"]').click();p.locator('[data-venta-cobrar]').click();p.locator('[data-venta-recibido]').fill('10000');p.locator('[data-venta-confirmar]').click();p.wait_for_url('**/caja/comprobante/*');id=int(p.url.rsplit('/',1)[1]);check('Total vendido','6500.00',str(scalar('SELECT total FROM ordenes WHERE id=?',[id])));shot(p,'venta')
    login(k,'produccion');v.goto(BASE+'/turnos/truck-de-pruebas');check('Pendiente privada',1,k.locator('[data-svp-orden="'+str(id)+'"]').count());number=scalar('SELECT numero FROM ordenes WHERE id=?',[id]);check('Pendiente oculta pública',False,number in v.locator('body').inner_text())
    for state in ['en_preparacion','lista','entregada']:
        k.locator('[data-svp-orden="'+str(id)+'"] [data-svp-avance]').click()
        if state=='entregada':expect(k.locator('[data-svp-orden="'+str(id)+'"]')).to_have_count(0)
        else:expect(k.locator('[data-svp-orden="'+str(id)+'"]')).to_have_attribute('data-svp-estado',state)
        check('Estado SQL '+state,state,scalar('SELECT e.codigo FROM ordenes o JOIN estados_orden e ON e.id=o.estado_id WHERE o.id=?',[id]));v.wait_for_timeout(4500);check('Visibilidad pública '+state,state!='entregada',number in v.locator('body').inner_text());shot(k,state);shot(v,'publico-'+state)
    p=cash.new_page();p.goto(BASE+'/caja/turno');p.locator('[name=total_declarado]').fill('56500');p.locator('button[type=submit]').click();p.locator('.dialogo').get_by_role('button',name='Cerrar turno',exact=True).click();p.wait_for_url('**/caja/turnos/*');check('Diferencia','0.00',str(scalar('SELECT diferencia FROM turnos_caja WHERE food_truck_id=?',[q.FT])));check('Turno cerrado','cerrado',scalar('SELECT estado FROM turnos_caja WHERE food_truck_id=?',[q.FT]));shot(p,'cierre');note('Se interactuó con formularios y botones reales en tres contextos de navegador; SQL solo contrasta resultados y prepara el banco.')
    b.close()
if __name__=='__main__':
    with sync_playwright() as pw:
        for engine in ['chromium','firefox']:run('CP-AUTO-E2E-01',lambda e=engine:full(pw,e))
    (EV/'resultados_flujo_e2e.json').write_text(json.dumps(q.RESULTS,ensure_ascii=False,indent=2))

if __name__=='__main__':
    raise SystemExit(1 if any(r['estado']=='Fallida' or 'error_ejecutor' in r for r in q.RESULTS) else 0)
