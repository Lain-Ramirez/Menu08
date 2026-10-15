"""Captura los informes derivados de los registros y las salidas reales CLI."""
import os,json,html
from pathlib import Path
os.environ.setdefault('PLAYWRIGHT_BROWSERS_PATH','/tmp/menu08-qa-runtime/browsers')
from playwright.sync_api import sync_playwright
from consolidar import CSS
ROOT=Path(__file__).resolve().parent.parent;EV=ROOT/'evidencias/actual'
data=json.loads((EV/'resumen.json').read_text())
with sync_playwright() as p:
    b=p.chromium.launch();page=b.new_page(viewport={'width':1200,'height':820},device_scale_factor=1)
    for r in data['resultados']:
        page.goto((EV/(r['caso']+'.html')).as_uri());page.screenshot(path=str(EV/(r['caso']+'-informe.png')))
    for name in ['unitarias','postman-original-recorrido','postman-original-publicas','postman-original-movil','postman-video-validado']:
        txt=(EV/(name+'.txt')).read_text()
        # ANSI de la consola se conserva en el TXT original, se limpia para leer.
        import re
        clean=re.sub(r'\x1b\[[0-9;]*m','',txt)
        f=EV/(name+'.html');f.write_text('<html lang="es"><meta charset="utf-8"><style>'+CSS+'</style><header><h1>Menu08 · '+html.escape(name)+'</h1><p>Salida real de herramienta · representación del archivo TXT</p></header><main><pre>'+html.escape(clean)+'</pre></main></html>')
        page.goto(f.as_uri());page.screenshot(path=str(EV/(name+'.png')),full_page=True)
        page.evaluate('window.scrollTo(0,document.body.scrollHeight)');page.screenshot(path=str(EV/(name+'-final.png')))
    b.close()
print('Capturas de informes y salidas CLI generadas.')
