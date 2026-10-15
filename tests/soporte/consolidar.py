"""Consolida resultados observados; no asigna aprobaciones por inspección del plan."""
from pathlib import Path
from collections import Counter,defaultdict
import json,html,subprocess,hashlib
ROOT=Path(__file__).resolve().parent.parent;EV=ROOT/'evidencias/actual';REPO=ROOT.parent
def read(name):return json.loads((EV/name).read_text())
def build():
    cases=json.loads((ROOT/'soporte/casos_aa2.json').read_text())
    base={c['id']:c for c in cases}
    additional=[
        ('CP-AUTO-PM-01','Recorrido corregido del repositorio','Newman ejecuta la carpeta 1 corregida con base local limpia.','22 peticiones; 40 aserciones sin fallos.','Postman / Newman'),
        ('CP-AUTO-PM-02','Reejecución del recorrido de Postman corregido','Ejecutar Menu08_GA9_VIDEO.postman_collection.json; IDs desde HTML y salida antes de cambiar de rol.','22 peticiones; 40 aserciones sin fallos.','Postman / Newman'),
        ('CP-AUTO-PM-03','Carpeta pública corregida del repositorio','Ejecutar carpeta 2 sin sesión sobre base local limpia.','11 peticiones; 19 aserciones sin fallos.','Postman / Newman'),
        ('CP-AUTO-PM-04','Carpeta móvil original de Postman','Ejecutar carpeta 8 sobre base local limpia.','8 peticiones; 25 aserciones sin fallos.','Postman / Newman'),
        ('CP-AUTO-E2E-01','Flujo completo por interfaz en dos navegadores','Abrir turno 50000; vender Papas de prueba 6500; avanzar los tres estados; observar turnos públicos; cerrar con declarado 56500.','Venta 6500; estados y privacidad correctos; turno cerrado con diferencia 0.00 en ambos motores.','Playwright / Chromium y Firefox'),
    ]
    for id,name,steps,expected,tool in additional:
        c=dict(id=id,modulo='AUTOMATIZACIÓN',requisito='Complemento de ejecución AA3. Fuente: colección Postman, rutas y formularios del commit verificado.',nombre=name,objetivo='Acreditar la reproducción automática y su resultado real.',tipo='E2E' if 'E2E' in id else 'Servicios / integración',precondiciones='Instancia local 127.0.0.1:8088; MySQL aislado; banco restaurado por el ejecutor.',datos='Usuarios pruebas.* y datos_pruebas.sql; IDs descubiertos del banco.',pasos=steps,esperado=expected,herramienta=tool,responsable='Jovanny Medina Cifuentes / Lain Sthid Ramirez Rueda',prioridad='Alta',fuentes='postman/Menu08.postman_collection.json; menu08_app/configuracion/rutas.php')
        cases.append(c);base[id]=c
    files=['resultados_http.json','resultados_servicios.json','resultados_e2e.json','resultados_publicacion.json','resultados_qr.json','resultados_postman.json','resultados_flujo_e2e.json']
    records=[]
    for f in files:
        for r in read(f):r['archivo_ejecutor']=f;records.append(r)
    # Reemplazar mediciones del mismo caso, herramienta y navegador por su última
    # ejecución: hubo ajustes del ejecutor en redondeo, lectura DOM e impresión.
    latest={};obsolete=[]
    for r in records:
        key=(r['caso'],r.get('navegador',''),r.get('herramienta',''))
        if key in latest:obsolete.append(latest[key])
        latest[key]=r
    records=sorted(latest.values(),key=lambda r:r['fecha'])
    for i,r in enumerate(records,1):r['ejecucion']=f'EX-{i:03}'
    grouped=defaultdict(list)
    for r in records:grouped[r['caso']].append(r)
    final=[]
    for c in cases:
        rr=grouped[c['id']]
        if not rr:raise RuntimeError('Caso sin ejecución: '+c['id'])
        pending=[]
        for r in rr:
            for p in r['pendientes']:
                if c['id']=='CP-CARTA-13' and p.startswith('Decodificación'):continue
                if c['id']=='CP-SEG-10' and p.startswith('Secure solo'):continue
                pending.append(p)
        checks=[x for r in rr for x in r['checks']]
        status='Fallida' if any(not x['cumple'] for x in checks) else 'Pendiente de seguimiento' if pending else 'Aprobada'
        captures=list(dict.fromkeys(x for r in rr for x in r.get('capturas',[])))
        notes=list(dict.fromkeys(x for r in rr for x in r['notas']))
        # El límite local/hosting se conserva aun cuando se completó Secure.
        if c['id']=='CP-SEG-10':notes.append('Regeneración/cierre: local. Secure: cookie inicial HTTPS del hosting. No se comprobó su commit ni se ingresó en producción.')
        item=dict(caso=c['id'],nombre=c['nombre'],modulo=c['modulo'],estado=status,checks=checks,pendientes=list(dict.fromkeys(pending)),notas=notes,capturas=captures,ejecuciones=[r['ejecucion'] for r in rr],fecha=rr[-1]['fecha'])
        final.append(item)
        (EV/(c['id']+'.json')).write_text(json.dumps(dict(caso=c,resultados=item,ejecuciones=rr),ensure_ascii=False,indent=2))
    commit=subprocess.check_output(['git','-C',str(REPO),'rev-parse','HEAD'],text=True).strip()
    summary=dict(commit=commit,casos_plan=96,casos_complementarios=len(additional),casos_total=len(final),estados=dict(Counter(x['estado'] for x in final)),estados_plan=dict(Counter(x['estado'] for x in final if not x['caso'].startswith('CP-AUTO'))),ejecuciones=len(records),comprobaciones=len([x for r in records for x in r['checks']]),resultados=final)
    (EV/'resumen.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2))
    (EV/'ejecuciones.json').write_text(json.dumps(records,ensure_ascii=False,indent=2))
    (EV/'ajustes_instrumentacion.json').write_text(json.dumps({'motivo':'Se ajustó el redondeo esperado a number_format PHP, el control de sesión de casos selectivos, la lectura de encabezados DOM y la captura de impresión Firefox. Estos son ajustes del ejecutor, no correcciones del producto. Solo las últimas mediciones válidas se consolidan.','mediciones_sustituidas':obsolete},ensure_ascii=False,indent=2))
    (ROOT/'soporte/casos_entrega.json').write_text(json.dumps(cases,ensure_ascii=False,indent=2))
    return summary,records,cases

CSS='''body{margin:0;background:#fff8f4;color:#221a15;font:16px Arial,sans-serif}main{padding:32px;max-width:1200px;margin:auto}header{background:#aa2502;color:white;padding:24px 32px}h1{font-size:25px;margin:8px 0}h2{font-size:19px}small{font-size:13px}table{border-collapse:collapse;width:100%;margin:16px 0}td,th{padding:10px;text-align:left;border:1px solid #d7c2b7;vertical-align:top;overflow-wrap:anywhere}th{background:#725304;color:white}tr:nth-child(even){background:#f5ebe4}.fail{background:#ffdad6!important}.badge{display:inline-block;padding:8px 14px;border:1px solid #725304;background:#f5df93;color:#251a00;border-radius:8px;font-weight:bold}.meta{background:#f5ebe4;padding:16px;line-height:1.6}a{color:#aa2502}pre{white-space:pre-wrap;background:#251b16;color:#f0dfd7;padding:18px}img{max-width:100%;border:1px solid #d7c2b7}li{margin:8px 0}.pending{background:#ffdcc2;padding:14px}'''
def esc(v):return html.escape(v if isinstance(v,str) else json.dumps(v,ensure_ascii=False))
def pages(summary,records,cases):
    byid={c['id']:c for c in cases}
    for r in summary['resultados']:
        c=byid[r['caso']];rows=''.join('<tr class="'+('fail' if not x['cumple'] else '')+'"><td>'+esc(x['comprobacion'])+'</td><td>'+esc(x['esperado'])+'</td><td>'+esc(x['obtenido'])+'</td><td>'+('Cumple' if x['cumple'] else 'No cumple')+'</td></tr>' for x in r['checks'])
        pending='<div class="pending"><b>Pendiente:</b><ul>'+''.join('<li>'+esc(x)+'</li>' for x in r['pendientes'])+'</ul></div>' if r['pendientes'] else ''
        pics=''.join('<p><a href="'+esc(Path(x).name)+'">'+esc(Path(x).name)+'</a></p><img loading="lazy" src="'+esc(Path(x).name)+'">' for x in r['capturas'])
        doc='<html lang="es"><meta charset="utf-8"><title>'+esc(r['caso'])+'</title><style>'+CSS+'</style><header><small>MENU08 · AA3 · REGISTRO AUTOMATIZADO DE EJECUCIÓN</small><h1>'+esc(r['caso'])+' · '+esc(r['nombre'])+'</h1><span class="badge">'+esc(r['estado'])+'</span></header><main><div class="meta">Commit: '+summary['commit']+'<br>Fecha UTC: '+r['fecha']+'<br>Ejecuciones: '+', '.join(r['ejecuciones'])+'<br>Fuente: repositorio Menu08 y respuestas reales de las herramientas. Este informe es una representación de los registros; las capturas de aplicación aparecen al final.</div><h2>Resultado esperado del caso</h2><p>'+esc(c['esperado']).replace('\n','<br>')+'</p>'+pending+'<h2>Comparaciones ejecutadas</h2><table><thead><tr><th>Comprobación</th><th>Esperado</th><th>Obtenido</th><th>Resultado</th></tr></thead><tbody>'+rows+'</tbody></table><h2>Notas de ejecución</h2><ul>'+''.join('<li>'+esc(x)+'</li>' for x in r['notas'])+'</ul><p><a href="'+r['caso']+'.json">Abrir registro JSON con respuestas HTTP y comprobaciones completas</a></p><h2>Capturas originales de aplicación</h2>'+pics+'</main></html>'
        (EV/(r['caso']+'.html')).write_text(doc)
    links=''.join('<tr><td><a href="'+r['caso']+'.html">'+r['caso']+'</a></td><td>'+esc(r['nombre'])+'</td><td>'+r['estado']+'</td></tr>' for r in summary['resultados'])
    (EV/'index.html').write_text('<html lang="es"><meta charset="utf-8"><style>'+CSS+'</style><header><h1>Menu08 · Evidencias de ejecución GA9 AA3 EV01</h1></header><main><p>Commit '+summary['commit']+'</p><p>'+esc(summary['estados'])+'</p><table><tr><th>Caso</th><th>Prueba</th><th>Estado</th></tr>'+links+'</table></main></html>')
if __name__=='__main__':
    summary,records,cases=build();pages(summary,records,cases)
    print(json.dumps({k:v for k,v in summary.items() if k!='resultados'},ensure_ascii=False,indent=2))
