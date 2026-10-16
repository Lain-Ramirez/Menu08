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
[[ -x "$NODE" ]] || { echo 'Falta Node.js local; ejecuta ./tests/preparar_cloudshell.sh.' >&2; exit 1; }
[[ -f tests/node_modules/playwright-core/index.js && -f tests/node_modules/newman/bin/newman.js ]] || { echo 'Faltan Playwright o Newman; ejecuta npm ci --prefix tests.' >&2; exit 1; }
[[ -f menu08_app/configuracion/configuracion.php ]] || { echo 'Falta la configuración local; ejecuta ./tests/preparar_cloudshell.sh.' >&2; exit 1; }

export MYSQL_PWD=Menu08LocalQA2026
mysqladmin --protocol=TCP -h127.0.0.1 -P13306 -uroot ping --silent >/dev/null || { echo 'MySQL no está listo; ejecuta ./tests/preparar_cloudshell.sh.' >&2; exit 1; }

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
