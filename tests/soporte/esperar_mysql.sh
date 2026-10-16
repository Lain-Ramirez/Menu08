#!/usr/bin/env bash
set -euo pipefail

# El servidor temporal de inicialización de MySQL responde por socket antes de
# habilitar TCP. Hay que consultar por el mismo puerto que utiliza PHP.
for ((attempt = 1; attempt <= ${1:-60}; attempt++)); do
  if error=$(mysql --protocol=TCP -h127.0.0.1 -P13306 -uroot \
    --connect-timeout=2 --batch --skip-column-names --execute='SELECT 1' 2>&1); then
    [[ "$error" == 1 ]] && exit 0
  fi
  if ((attempt < ${1:-60})); then sleep 2; fi
done

echo 'MySQL no acepta consultas en 127.0.0.1:13306.' >&2
echo "$error" >&2
echo 'Ejecuta ./tests/preparar_cloudshell.sh; si persiste, revisa docker logs menu08-ga9-mysql.' >&2
exit 1
