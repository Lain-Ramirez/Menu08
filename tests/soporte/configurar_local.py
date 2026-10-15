"""Prepara el mismo checkout y la configuración del ciclo. No inicia servicios."""
from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parent.parent
REPO=ROOT.parent
COMMIT=subprocess.check_output(['git','-C',str(REPO),'rev-parse','HEAD'],text=True).strip()
f=REPO/'menu08_app/configuracion/configuracion.ejemplo.php'
s=f.read_text().replace("'http://localhost/Menu08/publico'","'http://127.0.0.1:8088'").replace("'localhost'","'127.0.0.1'").replace('=> 3306,','=> 13306,').replace("'usuario'     => ''","'usuario'     => 'root'").replace("'contrasena'  => ''","'contrasena'  => 'Menu08LocalQA2026'").replace("'entorno' => 'desarrollo'","'entorno' => 'produccion'")
f.with_name('configuracion.php').write_text(s)
(ROOT/'soporte/sesiones').mkdir(exist_ok=True)
(ROOT/'evidencias').mkdir(exist_ok=True)
print('Checkout:',COMMIT)
print('Configuración local preparada: PHP 8088, MySQL 13306, banco aislado menu08.')
