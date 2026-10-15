"""Comprobaciones de lectura del despliegue enlazado por el repositorio."""
import ejecutar_http as q
from ejecutar_http import *
import ssl,socket

def publicar():
    old=q.BASE
    q.BASE='https://adso.menu08.com'
    def c15():
        s=session(login=False);live=req(s,'GET','/carta/festin-rodante');local=requests.get(old+'/carta/festin-rodante',timeout=20);check('HTTPS carta',200,live.status_code)
        for marker in ['data-carta','carta-portada','carta-agenda','data-carta-producto','/recursos/css/carta.css']:
            check('Estructura '+marker,marker in local.text,marker in live.text)
        note('Comparación de estructura y recursos con la instancia local; no se ha comprobado el commit desplegado ni se exige identidad de datos.')
    run('CP-CARTA-15',c15)
    def s15():
        from verificar_https import validar_correccion
        q.BASE=old
        try:validar_correccion()
        finally:q.BASE='https://adso.menu08.com'
        r=requests.get('http://adso.menu08.com/carta/festin-rodante',allow_redirects=False,timeout=20)
        q.CURRENT['http'].append({'metodo':'GET','url':r.url,'estado':r.status_code,'location':r.headers.get('Location'),'cuerpo':r.text})
        note('Observación del hosting, sin desplegar el PR: HTTP '+str(r.status_code)+'; Location='+str(r.headers.get('Location')))
        if r.status_code!=301 or r.headers.get('Location')!='https://adso.menu08.com/carta/festin-rodante':
            pending('Corrección HTTPS verificada en la rama y en Apache/PHP locales. Falta desplegar el PR y reejecutar el criterio de producción; el hosting sigue respondiendo HTTP '+str(r.status_code)+'.')
        pending('La bitácora del hosting y su commit desplegado no están acreditados por este ciclo.')
    run('CP-SEG-15',s15)
    def s10():
        r=req(session(login=False),'GET','/ingresar');cookie=r.headers.get('Set-Cookie','');check('Cookie HTTPS Secure',True,'secure' in cookie.lower());check('Cookie HttpOnly',True,'httponly' in cookie.lower());check('SameSite=Lax',True,'samesite=lax' in cookie.lower());note('Se comprueban los atributos de la cookie inicial del hosting. La regeneración al ingresar y salida se validaron en local; no se autentica en producción.')
    run('CP-SEG-10',s10)
    q.BASE=old
    (EV/'resultados_publicacion.json').write_text(json.dumps(q.RESULTS,ensure_ascii=False,indent=2))
if __name__=='__main__':publicar()

if __name__=='__main__':
    raise SystemExit(1 if any(r['estado']=='Fallida' or 'error_ejecutor' in r for r in q.RESULTS) else 0)
