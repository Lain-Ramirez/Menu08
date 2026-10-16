#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
NODE="$ROOT/tests/.runtime/node/bin/node"
case "${1:-}" in
  e2e|flujo) TEST=e2e ;;
  postman) TEST=postman ;;
  *) echo 'Uso: ./tests/video.sh {e2e|postman}' >&2; exit 2 ;;
esac
available_kb=$(df -Pk "$ROOT" | awk 'END {print $4}')
((available_kb >= 65536)) || { echo 'Espacio insuficiente: libera al menos 64 MiB para guardar sesiones y capturas. Revisa df -h .' >&2; exit 1; }

export MYSQL_PWD=Menu08LocalQA2026
needs_setup=0
[[ -x "$NODE" && -f tests/node_modules/playwright-core/index.js && -f tests/node_modules/newman/bin/newman.js ]] || needs_setup=1
[[ -f menu08_app/configuracion/configuracion.php ]] || needs_setup=1
if [[ "$TEST" == e2e ]]; then
  [[ -x tests/.runtime/browsers/chromium_headless_shell-1248/chrome-headless-shell-linux64/chrome-headless-shell ]] || needs_setup=1
fi
mysql --protocol=TCP -h127.0.0.1 -P13306 -uroot --connect-timeout=2 --execute='SELECT 1' >/dev/null 2>&1 || needs_setup=1
if [[ "$needs_setup" == 1 ]]; then
  echo 'Preparando las dependencias y los servicios locales de pruebas…'
  bash tests/preparar_cloudshell.sh
fi
bash tests/soporte/esperar_mysql.sh 15

if ! php -r 'exit(@file_get_contents("http://127.0.0.1:8088/ingresar") === false ? 1 : 0);' >/dev/null 2>&1; then
  mkdir -p tests/.runtime tests/soporte/sesiones
  nohup php -d session.save_path="$PWD/tests/soporte/sesiones" -S 127.0.0.1:8088 -t ADSO.menu08.com ADSO.menu08.com/index.php >tests/.runtime/php-server.log 2>&1 </dev/null &
fi
for _ in $(seq 1 15); do
  if php -r 'exit(@file_get_contents("http://127.0.0.1:8088/ingresar") === false ? 1 : 0);' >/dev/null 2>&1; then break; fi
  sleep 1
done
php -r 'exit(@file_get_contents("http://127.0.0.1:8088/ingresar") === false ? 1 : 0);' || { echo 'PHP local no respondió; revisa tests/.runtime/php-server.log.' >&2; exit 1; }

if [[ "$TEST" == e2e ]]; then
  export PLAYWRIGHT_BROWSERS_PATH="$ROOT/tests/.runtime/browsers"
  exec "$NODE" tests/e2e-video.cjs
fi
exec "$NODE" tests/node_modules/newman/bin/newman.js run postman/Menu08_GA9_VIDEO.postman_collection.json \
  --ignore-redirects --env-var baseUrl=http://127.0.0.1:8088 --reporters cli --timeout-request 15000
