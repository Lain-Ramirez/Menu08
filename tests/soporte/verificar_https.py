"""Prueba HTTP/TLS en Apache y la redirección del front controller real."""
import ejecutar_http as q
from ejecutar_http import *

def guardar(r,metodo):
    q.CURRENT['http'].append({'metodo':metodo,'url':r.url,'estado':r.status_code,'location':r.headers.get('Location'),'content_type':r.headers.get('Content-Type'),'bytes':len(r.content)})
    return r

def validar_correccion():
    cert=ROOT/'.runtime/apache/tls/cert.pem'
    for raiz in ['publica','aplicacion']:
        for metodo in ['GET','HEAD']:
            ruta='/'+raiz+'/recursos/css/md3.css?nombre=a%20b&x=1'
            r=guardar(requests.request(metodo,'http://127.0.0.1:8090'+ruta,headers={'Host':'adso.menu08.com'},allow_redirects=False,timeout=15),metodo)
            check(raiz+' '+metodo+' HTTP',301,r.status_code)
            check(raiz+' destino conserva ruta y consulta','https://adso.menu08.com'+ruta,r.headers.get('Location'))
            if metodo=='HEAD':check('HEAD vacío',0,len(r.content))
        r=guardar(requests.get('https://localhost:8443/'+raiz+'/recursos/css/md3.css',headers={'Host':'adso.menu08.com'},verify=str(cert),allow_redirects=False,timeout=15),'GET')
        check(raiz+' HTTPS sin bucle',200,r.status_code)
        r=guardar(requests.get('http://127.0.0.1:8090/'+raiz+'/recursos/css/md3.css',allow_redirects=False,timeout=15),'GET')
        check(raiz+' HTTP local conservado',200,r.status_code)
    conf=REPO/'menu08_app/configuracion/configuracion.php';backup=conf.read_bytes()
    try:
        texto=re.sub(r"('url_base'\s*=>\s*)'[^']*'",r"\1'https://adso.menu08.com'",backup.decode())
        conf.write_text(texto)
        for ruta in ['/carta/truck-de-pruebas?busqueda=a%20b&x=1','/ingresar','/panel']:
            r=guardar(requests.get(q.BASE+ruta,allow_redirects=False,timeout=15),'GET');check('Front controller '+ruta,301,r.status_code);check('Destino canónico','https://adso.menu08.com'+ruta,r.headers.get('Location'));check('Sin sesión antes de redirigir',False,'Set-Cookie' in r.headers)
        r=guardar(requests.get(q.BASE+'/panel',headers={'Host':'ajeno.invalid','X-Forwarded-Proto':'https'},allow_redirects=False,timeout=15),'GET');check('No usa Host ni X-Forwarded-Proto','https://adso.menu08.com/panel',r.headers.get('Location'))
    finally:conf.write_bytes(backup)
    note('Las dos reglas .htaccess se ejecutaron con Apache real; TLS se verificó contra el certificado local de prueba. El front controller real redirige antes de abrir sesión. Configuración local restaurada en finally.')

if __name__=='__main__':
    run('CP-SEG-15',validar_correccion)
    (EV/'resultados_https.json').write_text(json.dumps(q.RESULTS,ensure_ascii=False,indent=2))
    raise SystemExit(1 if any(r['estado']=='Fallida' for r in q.RESULTS) else 0)
