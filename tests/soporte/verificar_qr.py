import shutil
import ejecutar_http as q
from ejecutar_http import *
def validar():
    p=subprocess.run([shutil.which('node') or '/tmp/menu08-qa-runtime/bin/node',str(ROOT/'soporte/leer_qr.cjs'),str(EV/'qr-local.png')],capture_output=True,text=True)
    check('Lector QR',0,p.returncode);check('Contenido decodificado',BASE+'/carta/truck-de-pruebas',p.stdout.strip());note('QR real descargado por CP-CARTA-13; lectura óptica por ZXing. '+p.stdout.strip())
    (EV/'qr-decodificado.txt').write_text(p.stdout+p.stderr)
run('CP-CARTA-13',validar)
(EV/'resultados_qr.json').write_text(json.dumps(q.RESULTS,ensure_ascii=False,indent=2))
