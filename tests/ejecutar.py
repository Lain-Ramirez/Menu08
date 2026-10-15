"""Ejecuta los grupos secuencialmente para no interferir con la restauración SQL."""
from pathlib import Path
import argparse,subprocess,sys,json

TESTS=Path(__file__).resolve().parent;REPO=TESTS.parent
GROUPS={'http':'ejecutar_http.py','servicios':'ejecutar_servicios.py','e2e':'ejecutar_e2e.py','postman':'ejecutar_postman.py','https':'verificar_https.py','flujo':'ejecutar_flujo_e2e.py','qr':'verificar_qr.py','publicacion':'verificar_publicacion.py'}
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--grupo',choices=['todo',*GROUPS],default='todo')
args=parser.parse_args()
names=list(GROUPS) if args.grupo=='todo' else [args.grupo]
errors=[]
for name in names:
    result=subprocess.run([sys.executable,str(TESTS/'soporte'/GROUPS[name])],cwd=REPO)
    output=TESTS/'evidencias/actual'/('resultados_'+{'flujo':'flujo_e2e','publicacion':'publicacion','qr':'qr','https':'https'}.get(name,name)+'.json')
    failed=result.returncode!=0
    if output.exists():
        records=json.loads(output.read_text())
        failed=failed or any(x['estado']=='Fallida' or 'error_ejecutor' in x for x in records)
    else:failed=True
    if failed:errors.append(name)
if errors:raise SystemExit('Grupos con fallos: '+', '.join(errors))
print('Grupos ejecutados sin casos fallidos: '+', '.join(names))
print('Los seguimientos físicos y de despliegue se conservan en los registros.')
