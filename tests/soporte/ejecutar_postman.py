"""Ejecuta la colección original y prepara una copia reproducible para el video."""
import copy,sys,shutil
import ejecutar_http as q
from ejecutar_http import *
NODE=shutil.which('node') or '/tmp/menu08-qa-runtime/bin/node'
NEWMAN=str(ROOT/'node_modules/newman/bin/newman.js') if (ROOT/'node_modules/newman/bin/newman.js').exists() else '/tmp/menu08-qa-runtime/newman/node_modules/newman/bin/newman.js'
ORIGINAL=REPO/'postman/Menu08.postman_collection.json'
def entry(name,path):
    return {'name':name,'request':{'method':'GET','url':'{{baseUrl}}'+path},'event':[{'listen':'test','script':{'type':'text/javascript','exec':["pm.test('Cierre de sesión 302',()=>pm.response.to.have.status(302));"]}}]}
def command(collection,folder,label):
    raw=Path('/tmp/menu08-qa-runtime')/(label+'.json')
    args=[NODE,NEWMAN,'run',str(collection),'--ignore-redirects','--env-var','baseUrl='+BASE,'--reporters','cli,json','--reporter-json-export',str(raw),'--timeout-request','15000']
    if folder:args+=['--folder',folder]
    p=subprocess.run(args,capture_output=True,text=True)
    (EV/(label+'.txt')).write_text(p.stdout+p.stderr)
    data=json.loads(raw.read_text());r=data['run'];compact={'archivo':str(collection.relative_to(REPO)),'carpeta':folder,'fecha':datetime.now(timezone.utc).isoformat(),'exit_code':p.returncode,'stats':r['stats'],'fallos':r['failures'],'ejecuciones':[]}
    for e in r['executions']:
        compact['ejecuciones'].append({'nombre':e['item']['name'],'metodo':e['request']['method'],'url':e['request']['url'],'codigo':e.get('response',{}).get('code'),'ms':e.get('response',{}).get('responseTime'),'assertions':e.get('assertions',[])})
    (EV/(label+'.json')).write_text(json.dumps(compact,ensure_ascii=False,indent=2))
    check('Código Newman',0,p.returncode);check('Aserciones fallidas',0,r['stats']['assertions']['failed']);note(json.dumps({'requests':r['stats']['requests'],'assertions':r['stats']['assertions']},ensure_ascii=False));note('Reporte: evidencias/'+label+'.json');return compact
def all_tests():
    # Restaurar el banco completo; los IDs se descubren desde su catálogo.
    seed(full=True)
    def original():command(ORIGINAL,'1 - Recorrido completo (ejecutar en orden)','postman-original-recorrido')
    q.CASES['CP-AUTO-PM-01']={'nombre':'Recorrido del repositorio corregido'};run('CP-AUTO-PM-01',original)
    seed(full=True)
    def public():command(ORIGINAL,'2 - Publicas (sin sesion)','postman-original-publicas')
    q.CASES['CP-AUTO-PM-03']={'nombre':'Carpeta pública del repositorio corregida'};run('CP-AUTO-PM-03',public)
    seed(full=True)
    def mobile():command(ORIGINAL,'8 - Modulo movil (ejecutar en orden)','postman-original-movil')
    q.CASES['CP-AUTO-PM-04']={'nombre':'Carpeta móvil original de Postman'};run('CP-AUTO-PM-04',mobile)
    # La muestra del video es una carpeta de la colección principal corregida.
    seed()
    out=REPO/'postman/Menu08_GA9_VIDEO.postman_collection.json'
    q.CASES['CP-AUTO-PM-02']={'nombre':'Reejecución de Postman con manejo de sesión e IDs corregidos'}
    run('CP-AUTO-PM-02',lambda:command(out,None,'postman-video-validado'))
    (EV/'resultados_postman.json').write_text(json.dumps(q.RESULTS,ensure_ascii=False,indent=2,default=str))
if __name__=='__main__':all_tests()

if __name__=='__main__':
    raise SystemExit(1 if any(r['estado']=='Fallida' or 'error_ejecutor' in r for r in q.RESULTS) else 0)
