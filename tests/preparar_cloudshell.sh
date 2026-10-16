#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
DOCKER=(env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH docker --host=unix:///var/run/docker.sock)
NODE="$ROOT/tests/.runtime/node/bin/node"

if [[ ! -x "$NODE" || ! -x "$ROOT/tests/.runtime/node/bin/npm" ]]; then
  command -v curl >/dev/null && command -v tar >/dev/null || { echo 'Se requieren curl y tar para instalar Node.js.' >&2; exit 1; }
  temp="$(mktemp -d /tmp/menu08-node.XXXXXX)"
  trap 'rm -rf "$temp"' EXIT
  curl -fL --retry 2 https://nodejs.org/dist/v24.21.0/node-v24.21.0-linux-x64.tar.xz -o "$temp/node.tar.xz"
  tar -xf "$temp/node.tar.xz" -C "$temp"
  source="$temp/node-v24.21.0-linux-x64"
  mkdir -p tests/.runtime/node/bin
  cp "$source/bin/node" tests/.runtime/node/bin/node
  if [[ ! -d /tmp/menu08-node-runtime-npm ]]; then cp -a "$source/lib/node_modules/npm" /tmp/menu08-node-runtime-npm; fi
  ln -sf /tmp/menu08-node-runtime-npm/bin/npm-cli.js tests/.runtime/node/bin/npm
  ln -sf /tmp/menu08-node-runtime-npm/bin/npx-cli.js tests/.runtime/node/bin/npx
fi
export PATH="$ROOT/tests/.runtime/node/bin:$PATH"
if [[ ! -f tests/node_modules/playwright-core/index.js || ! -f tests/node_modules/newman/bin/newman.js ]]; then npm ci --prefix tests; fi
export PLAYWRIGHT_BROWSERS_PATH="$ROOT/tests/.runtime/browsers"
if [[ ! -x "$PLAYWRIGHT_BROWSERS_PATH/chromium_headless_shell-1248/chrome-headless-shell-linux64/chrome-headless-shell" ]]; then
  "$NODE" tests/node_modules/playwright-core/cli.js install --only-shell chromium
fi
php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' || { echo 'PHP necesita la extensión pdo_mysql.' >&2; exit 1; }
command -v mysql >/dev/null || { echo 'Falta el cliente mysql.' >&2; exit 1; }
"${DOCKER[@]}" info --format '{{.ServerVersion}}' >/dev/null || { echo 'Docker local no está disponible.' >&2; exit 1; }

if ! "${DOCKER[@]}" inspect menu08-ga9-mysql >/dev/null 2>&1; then
  "${DOCKER[@]}" run --name menu08-ga9-mysql -e MYSQL_ROOT_PASSWORD=Menu08LocalQA2026 -p 127.0.0.1:13306:3306 -d mysql:8.4
else
  [[ "$("${DOCKER[@]}" inspect -f '{{.State.Running}}' menu08-ga9-mysql)" == true ]] || "${DOCKER[@]}" start menu08-ga9-mysql >/dev/null
fi

export MYSQL_PWD=Menu08LocalQA2026
ready=0
for _ in $(seq 1 45); do
  if "${DOCKER[@]}" exec -e MYSQL_PWD menu08-ga9-mysql mysqladmin ping -uroot --silent >/dev/null 2>&1; then ready=1; break; fi
  sleep 2
done
[[ "$ready" == 1 ]] || { echo 'MySQL no respondió; revisa docker logs menu08-ga9-mysql.' >&2; exit 1; }

php tests/soporte/configurar_local.php
for file in esquema.sql datos_iniciales.sql datos_festin_rodante.sql datos_pruebas.sql; do
  args=(--protocol=TCP -h127.0.0.1 -P13306 -uroot --default-character-set=utf8mb4 --init-command='SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci')
  [[ "$file" == esquema.sql ]] || args+=(menu08)
  mysql "${args[@]}" < "menu08_app/basedatos/$file"
done

mkdir -p tests/.runtime tests/soporte/sesiones
if ! php -r 'exit(@file_get_contents("http://127.0.0.1:8088/ingresar") === false ? 1 : 0);' >/dev/null 2>&1; then
  nohup php -d session.save_path="$PWD/tests/soporte/sesiones" -S 127.0.0.1:8088 -t ADSO.menu08.com ADSO.menu08.com/index.php >tests/.runtime/php-server.log 2>&1 </dev/null &
fi
for _ in $(seq 1 20); do
  if php -r 'exit(@file_get_contents("http://127.0.0.1:8088/ingresar") === false ? 1 : 0);' >/dev/null 2>&1; then break; fi
  sleep 1
done
php -r 'exit(@file_get_contents("http://127.0.0.1:8088/ingresar") === false ? 1 : 0);' || { echo 'PHP no respondió en 127.0.0.1:8088; revisa tests/.runtime/php-server.log.' >&2; exit 1; }

echo 'Listo: Node.js, Playwright, Newman, Chromium headless, MySQL local y PHP.'
echo 'Pruebas unitarias: php menu08_app/pruebas/ejecutar.php'
echo 'Postman/Newman: ./tests/video.sh postman'
echo 'E2E (Lain): ./tests/video.sh e2e'
