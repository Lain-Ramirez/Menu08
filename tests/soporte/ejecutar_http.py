"""Casos AA2 ejecutados contra una instancia LOCAL del commit de Menu08.
Cada comprobación conserva su esperado y observado. No corrige el producto.
Uso: python soporte/ejecutar_http.py (MySQL y PHP deben estar disponibles).
"""
from pathlib import Path
import os, re, json, subprocess, time, html, traceback
from datetime import datetime, timezone
import requests

ROOT=Path(__file__).resolve().parent.parent
REPO=ROOT.parent
EV=ROOT/'evidencias/actual'; EV.mkdir(parents=True,exist_ok=True)
BASE=os.environ.get('MENU08_QA_URL','http://127.0.0.1:8088')
from urllib.parse import urlparse
if urlparse(BASE).hostname not in ['127.0.0.1','localhost','::1']:
    raise RuntimeError('Las pruebas que restauran datos solo se ejecutan contra localhost.')
COMMIT=subprocess.check_output(['git','-C',str(REPO),'rev-parse','HEAD'],text=True).strip()
CASES={c['id']:c for c in json.loads((ROOT/'soporte/casos_aa2.json').read_text())}
RESULTS=[]; CURRENT=None

def bridge(accion,**kwargs):
    p=subprocess.run(['php',str(ROOT/'soporte/puente.php')],input=json.dumps(dict(accion=accion,**kwargs)),text=True,capture_output=True)
    if p.returncode: raise RuntimeError(p.stderr+p.stdout)
    return json.loads(p.stdout)
def sql(q,params=()):return bridge('sql',sql=q,params=list(params))
def scalar(q,params=()):return next(iter(sql(q,params)[0].values()))
def seed(full=False):
    names=['esquema.sql','datos_iniciales.sql','datos_festin_rodante.sql','datos_pruebas.sql'] if full else ['datos_pruebas.sql']
    for n in names:
        p=subprocess.run(['mysql','--protocol=TCP','-h127.0.0.1','-P13306','-uroot','--default-character-set=utf8mb4','--init-command=SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci']+([] if n=='esquema.sql' else ['menu08']),input=(REPO/'menu08_app/basedatos'/n).read_text(),text=True,capture_output=True,env=dict(os.environ,MYSQL_PWD='Menu08LocalQA2026'))
        if p.returncode:raise RuntimeError(p.stderr)
    ids()
def ids():
    global FT,PRODUCTS,CATS
    FT=int(scalar("SELECT id FROM food_trucks WHERE slug='truck-de-pruebas'"))
    PRODUCTS={r['nombre']:r for r in sql('SELECT * FROM productos WHERE food_truck_id=?',[FT])}
    CATS={r['nombre']:r['id'] for r in sql('SELECT * FROM categorias WHERE food_truck_id=?',[FT])}
def pid(n):return PRODUCTS[n]['id']
def req(s,method,path,**kw):
    t=time.perf_counter(); r=s.request(method,BASE+path,allow_redirects=False,timeout=20,**kw)
    if CURRENT is not None:
        # El cuerpo conserva la respuesta; los tokens y los ID de sesión se ocultan.
        body=re.sub(r'(?i)(name="_token" value="|name="csrf-token" content=")[a-f0-9]+',r'\1[oculto]',r.text) if not r.headers.get('Content-Type','').startswith('image/') else '[imagen binaria]'
        body=re.sub(r'"token_csrf"\s*:\s*"[a-f0-9]+"','"token_csrf":"[oculto]"',body)
        CURRENT['http'].append({'metodo':method,'ruta':path,'estado':r.status_code,'ms':round((time.perf_counter()-t)*1000,2),'content_type':r.headers.get('Content-Type'),'location':r.headers.get('Location'),'cuerpo':body})
    return r
def token(s,path):
    r=req(s,'GET',path)
    m=re.search(r'name="(?:_token|csrf-token)" (?:value|content)="([a-f0-9]+)"',r.text)
    if not m:raise RuntimeError('No se encontró token en '+path+': '+str(r.status_code))
    return m[1]
def post(s,path,data,page=None,**kw):
    return req(s,'POST',path,data=dict(data,_token=token(s,page or path)),**kw)
def session(role='foodtruck',login=True):
    s=requests.Session()
    if login:
        correo='plataforma@menu08.local' if role=='plataforma' else 'pruebas.'+role+'@menu08.local'
        r=post(s,'/ingresar',dict(correo=correo,contrasena='Menu08*Demo2026'))
        if r.status_code!=302:raise RuntimeError('No se pudo ingresar '+role)
    return s
def check(label,expected,actual):
    ok=expected==actual
    CURRENT['checks'].append(dict(comprobacion=label,esperado=expected,obtenido=actual,cumple=ok))
    return ok
def note(text):CURRENT['notas'].append(text)
def pending(text):CURRENT['pendientes'].append(text)
def run(cid,fn):
    global CURRENT
    CURRENT=dict(caso=cid,nombre=CASES[cid]['nombre'],fecha=datetime.now(timezone.utc).isoformat(),commit=COMMIT,checks=[],http=[],notas=[],pendientes=[],herramienta='Python requests + PHP PDO / MySQL 8.4')
    try:fn()
    except Exception as e:
        CURRENT['pendientes'].append('Error del ejecutor: '+str(e));CURRENT['error_ejecutor']=traceback.format_exc()
    CURRENT['estado']='Fallida' if 'error_ejecutor' in CURRENT or any(not x['cumple'] for x in CURRENT['checks']) else 'Pendiente de seguimiento' if CURRENT['pendientes'] or not CURRENT['checks'] else 'Aprobada'
    (EV/(cid+'.json')).write_text(json.dumps(CURRENT,ensure_ascii=False,indent=2,default=str))
    RESULTS.append(CURRENT)
    print(cid,CURRENT['estado'],[(x['comprobacion'],x['obtenido']) for x in CURRENT['checks'] if not x['cumple']],CURRENT['pendientes'],flush=True)
    CURRENT=None
def product_data(name='Producto QA',**kw):
    return dict(nombre=name,descripcion='Prueba reproducible',precio='9900',categoria_id=CATS['Postres'],orden='9',disponible='1',**kw)
def edit_data(n,**kw):
    p=PRODUCTS[n]
    d={k:p[k] if p[k] is not None else '' for k in ['id','nombre','descripcion','precio','categoria_id','orden']};d['disponible']='1';d.update(kw);return d
def ubic(**kw):
    d=dict(nombre='Parada nocturna del domingo',referencia='Banco local',latitud='4.65',longitud='-74.05',dia_semana='7',hora_inicio='20:00',hora_fin='02:00');d.update(kw);return d
def abrir(s,base='50000'):
    return post(s,'/caja/turno/abrir',dict(base_inicial=base),'/caja/turno')
def venta(s,items,pago='efectivo',nota='',**extra):
    return post(s,'/caja/vender',{**{'cantidad['+str(pid(n))+']':q for n,q in items.items()},'medio_pago':pago,'nota':nota,**extra},'/caja')
def oid(r):return int(r.headers['Location'].rsplit('/',1)[1])
def avanzar(s,id,estado):return post(s,f'/svp/orden/{id}/estado',dict(estado=estado),'/svp')
def count(t):return int(scalar('SELECT COUNT(*) FROM '+t+' WHERE food_truck_id=?',[FT]))
def summary():return {t:count(t) for t in ['productos','categorias','ubicaciones','turnos_caja','ordenes']}
def response(c,r):check(c,302,r.status_code)

def carta():
    seed()
    def c01():
        s=session(login=False);r=post(s,'/ingresar',dict(correo='pruebas.foodtruck@menu08.local',contrasena='Menu08*Demo2026'))
        check('Ingreso',302,r.status_code);check('Destino',BASE+'/panel',r.headers.get('Location'));check('Panel del truck',True,'Truck de Pruebas' in req(s,'GET','/panel').text)
    run('CP-CARTA-01',c01)
    def c02():
        s=session(login=False);r=post(s,'/ingresar',dict(correo='pruebas.foodtruck@menu08.local',contrasena='equivocada'));check('Rechazo',401,r.status_code);check('Mensaje genérico',True,'Correo o contraseña incorrectos' in r.text);check('Panel sin sesión',302,req(s,'GET','/panel').status_code)
    run('CP-CARTA-02',c02)
    def c03():
        s=session();check('Salir',302,req(s,'GET','/salir').status_code);check('Panel bloqueado',302,req(s,'GET','/panel').status_code)
    run('CP-CARTA-03',c03)
    def c04():
        s=session('cajero');check('Panel de productos',403,req(s,'GET','/panel/productos').status_code);check('Caja sin turno',302,req(s,'GET','/caja').status_code)
    run('CP-CARTA-04',c04)
    s=session()
    def c05():
        r=post(s,'/panel/categorias',dict(nombre='Postres de prueba',orden=9));check('Alta',302,r.status_code);check('Fila activa',1,scalar("SELECT activo FROM categorias WHERE food_truck_id=? AND nombre='Postres de prueba'",[FT]));check('Visible',True,'Postres de prueba' in req(s,'GET','/panel/categorias').text)
    run('CP-CARTA-05',c05)
    def c06():
        id=scalar("SELECT id FROM categorias WHERE food_truck_id=? AND nombre='Postres de prueba'",[FT]);n=count('categorias');r=post(s,'/panel/categorias',dict(id=id,nombre='Postres editados',orden=8));check('Edición',302,r.status_code);check('Mismo conteo',n,count('categorias'));check('Mismo ID y nuevo nombre','Postres editados',scalar('SELECT nombre FROM categorias WHERE id=?',[id]))
    run('CP-CARTA-06',c06)
    def c07():
        foto=next((REPO/'ADSO.menu08.com').rglob('*.jpg'));r=post(s,'/panel/productos',product_data('Producto QA con foto'),'/panel/productos/nuevo',files={'foto':('foto.jpg',foto.read_bytes(),'image/jpeg')});check('Alta foto',302,r.status_code)
        rows=sql("SELECT foto FROM productos WHERE food_truck_id=? AND nombre='Producto QA con foto'",[FT]);check('Fila creada',1,len(rows))
        if rows:check('Archivo escrito',True,(REPO/'ADSO.menu08.com/subidas'/str(rows[0]['foto'])).is_file());check('Foto referida en carta',True,str(rows[0]['foto']) in req(s,'GET','/carta/truck-de-pruebas').text)
    run('CP-CARTA-07',c07)
    def c08():
        r=post(s,'/panel/productos/disponibilidad',dict(id=pid('Papas de prueba')),'/panel/productos');check('Cambio',302,r.status_code);check('No disponible en BD',0,scalar('SELECT disponible FROM productos WHERE id=?',[pid('Papas de prueba')]));check('Se oculta de carta según criterio AA2',False,'Papas de prueba' in req(s,'GET','/carta/truck-de-pruebas').text);check('Sigue en panel',True,'Papas de prueba' in req(s,'GET','/panel/productos').text);note('Reejecución de DEF-01: el producto agotado debe quedar oculto en la carta y conservarse en el panel.')
    run('CP-CARTA-08',c08)
    def c09():
        r=post(s,'/panel/categorias/estado',dict(id=CATS['Entradas']),'/panel/categorias');check('Cambio',302,r.status_code);check('Categoría preservada inactiva',0,scalar('SELECT activo FROM categorias WHERE id=?',[CATS['Entradas']]));check('Producto fuera de carta',False,'Papas de prueba' in req(s,'GET','/carta/truck-de-pruebas').text)
    run('CP-CARTA-09',c09)
    seed();s=session()
    def c10():
        r=req(s,'GET','/carta/truck-de-pruebas');rows=sql('SELECT p.nombre,p.precio FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.food_truck_id=? AND c.activo=1 AND p.disponible=1 ORDER BY c.orden,c.id,p.disponible DESC,p.orden,p.id',[FT]);check('HTTP',200,r.status_code);check('Todos los nombres publicados',True,all(html.escape(x['nombre'],quote=True) in r.text for x in rows));note('Precios y orden del DOM se contrastan además en ejecutar_e2e.py; agotados se documentan en CP-CARTA-08.')
    run('CP-CARTA-10',c10)
    run('CP-CARTA-11',lambda:check('Slug inexistente',404,req(s,'GET','/carta/no-existe').status_code))
    def c12():
        sql('UPDATE food_trucks SET activo=0 WHERE id=?',[FT]);check('Inactivo',404,req(s,'GET','/carta/truck-de-pruebas').status_code);sql('UPDATE food_trucks SET activo=1 WHERE id=?',[FT]);check('Reactivado',200,req(s,'GET','/carta/truck-de-pruebas').status_code)
    run('CP-CARTA-12',c12)
    def c13():
        check('Pantalla QR',200,req(s,'GET','/panel/qr').status_code);r=req(s,'GET','/panel/qr/descargar');check('Descarga PNG',True,r.content.startswith(b'\x89PNG'));(EV/'qr-local.png').write_bytes(r.content);pending('Decodificación óptica del QR pendiente; se conserva PNG y destino mostrado por el panel.')
    run('CP-CARTA-13',c13)
    def c14():
        id=scalar('SELECT id FROM productos WHERE food_truck_id<>? LIMIT 1',[FT]);check('Producto ajeno',404,req(s,'GET',f'/panel/productos/{id}').status_code)
    run('CP-CARTA-14',c14)
    def c17():
        for value,expected in [('12.500','12500.00'),('12.500,50','12500.50'),('12,500.50','12500.50'),('$ 8.000','8000.00'),('12,50','12.50')]:
            r=post(s,'/panel/productos',edit_data('Brownie de prueba',precio=value),'/panel/productos');check('HTTP '+value,302,r.status_code);check('Precio '+value,expected,str(scalar('SELECT precio FROM productos WHERE id=?',[pid('Brownie de prueba')])) )
    run('CP-CARTA-17',c17)
    def c18():
        before=count('productos');other=scalar('SELECT id FROM categorias WHERE food_truck_id<>? LIMIT 1',[FT])
        data=[dict(precio=x) for x in ['abc','-5','1.2345']]+[dict(nombre='Papas de prueba',categoria_id=CATS['Entradas']),dict(nombre=''),dict(categoria_id=other),dict(categoria_id=999999)]
        for d in data:
            v=product_data();v.update(d);check('Rechazo '+str(d),422,post(s,'/panel/productos',v,'/panel/productos/nuevo').status_code)
        check('Sin inserciones',before,count('productos'))
    run('CP-CARTA-18',c18)
    def c19():
        before=count('productos');folder=REPO/'ADSO.menu08.com/subidas';files=set(folder.glob('*'));jpg=next((REPO/'ADSO.menu08.com').rglob('*.jpg')).read_bytes()
        for label,data,name,b,typ in [('PHP renombrado',product_data(),'malo.jpg',b'<?php echo "QA"; ?>','image/jpeg'),('Exceso 2MB',product_data(),'grande.png',b'\x89PNG\r\n\x1a\n'+b'0'*(2*1024*1024+1024),'image/png'),('Imagen válida precio inválido',{**product_data(),'precio':'abc'},'valido.jpg',jpg,'image/jpeg')]:
            check(label,422,post(s,'/panel/productos',data,'/panel/productos/nuevo',files={'foto':(name,b,typ)}).status_code)
        check('Sin filas',before,count('productos'));check('Sin archivos huérfanos',True,files==set(folder.glob('*')))
    run('CP-CARTA-19',c19)
    def c20():
        n=count('productos');r=post(s,'/panel/productos',edit_data('Brownie de prueba',precio='12500'),'/panel/productos');check('HTTP',302,r.status_code);check('Sin alta',n,count('productos'));check('Precio','12500.00',str(scalar('SELECT precio FROM productos WHERE id=?',[pid('Brownie de prueba')])))
    run('CP-CARTA-20',c20)
    def c21():
        d=dict(nombre='Truck de Pruebas',slug='truck-de-pruebas',ciudad='Bogota QA',descripcion='Prueba local');check('Guardar',302,post(s,'/panel/food-truck',d).status_code);check('Ciudad','Bogota QA',scalar('SELECT ciudad FROM food_trucks WHERE id=?',[FT]));check('Slug ajeno',422,post(s,'/panel/food-truck',{**d,'slug':'festin-rodante'}).status_code);check('Slug conservado','truck-de-pruebas',scalar('SELECT slug FROM food_trucks WHERE id=?',[FT]))
    run('CP-CARTA-21',c21)
    def c22():
        check('Alta nocturna',302,post(s,'/panel/ubicaciones',ubic()).status_code);r=sql("SELECT dia_semana,hora_inicio,hora_fin FROM ubicaciones WHERE food_truck_id=? AND nombre='Parada nocturna del domingo'",[FT])[0];check('Franja',{'dia_semana':7,'hora_inicio':'20:00:00','hora_fin':'02:00:00'},r)
    run('CP-CARTA-22',c22)
    def c23():
        id=scalar("SELECT id FROM ubicaciones WHERE food_truck_id=? AND nombre='Parada nocturna del domingo'",[FT]);check('Edición',302,post(s,'/panel/ubicaciones',ubic(id=id,referencia='Editada')).status_code)
        for val in [0,1]:
            check('Alternar',302,post(s,'/panel/ubicaciones/estado',dict(id=id),'/panel/ubicaciones').status_code);check('Activa',val,scalar('SELECT activa FROM ubicaciones WHERE id=?',[id]))
        check('ID conservado',1,int(scalar('SELECT COUNT(*) FROM ubicaciones WHERE id=?',[id])))
    run('CP-CARTA-23',c23)
    def vig(values):
        for moment,expected in values:
            x=bridge('vigente',ft=FT,momento=moment);check(moment,expected,None if x is None else x['nombre'])
    run('CP-CARTA-24',lambda:vig([('2026-10-14 '+t,n) for t,n in [('10:59:00',None),('11:00:00','Parque de Pruebas'),('14:59:00','Parque de Pruebas'),('15:00:00',None)]]+[('2026-10-15 00:30:00',None),('2026-10-16 13:00:00','Plaza de Pruebas')]))
    run('CP-CARTA-25',lambda:vig([('2026-10-17 '+t,n) for t,n in [('17:59:00',None),('18:00:00','Zona Rosa de Pruebas'),('23:30:00','Zona Rosa de Pruebas')]]+[('2026-10-18 '+t,n) for t,n in [('00:30:00','Zona Rosa de Pruebas'),('00:59:00','Zona Rosa de Pruebas'),('01:00:00',None),('02:00:00',None)]]))
    run('CP-CARTA-26',lambda:vig([('2026-10-18 '+t,n) for t,n in [('19:59:00',None),('20:00:00','Parada nocturna del domingo'),('23:30:00','Parada nocturna del domingo')]]+[('2026-10-19 '+t,n) for t,n in [('00:30:00','Parada nocturna del domingo'),('01:59:00','Parada nocturna del domingo'),('02:00:00',None)]]))
    def c27():
        n=count('ubicaciones')
        for d in [dict(dia_semana=x) for x in ['0','8','lunes']]+[dict(hora_inicio=x) for x in ['25:99','18']]+[dict(hora_fin=''),dict(latitud='200'),dict(nombre='')]:check(str(d),422,post(s,'/panel/ubicaciones',ubic(**d)).status_code)
        check('Sin inserciones',n,count('ubicaciones'))
    run('CP-CARTA-27',c27)
    seed();s=session()
    def c28():
        vig([('2026-10-19 10:00:00',None)]);r=req(s,'GET','/carta/truck-de-pruebas');check('Inactiva ausente',False,'Parada desactivada' in r.text);check('3 activas',3,int(scalar('SELECT COUNT(*) FROM ubicaciones WHERE food_truck_id=? AND activa=1',[FT])));check('Fila conservada',1,int(scalar("SELECT COUNT(*) FROM ubicaciones WHERE food_truck_id=? AND nombre='Parada desactivada'",[FT])))
    run('CP-CARTA-28',c28)
    def c29():
        for momento in ["' OR 1=1 --",'2026-99-99T25:99']:
            r=req(s,'GET','/panel/ubicaciones',params={'momento':momento});check(momento,200,r.status_code);check('Sin traza SQL',False,any(x in r.text for x in ['PDOException','SQLSTATE']))
        d=ubic(nombre="Punto '); DROP TABLE ubicaciones; --");check('Texto preparado',302,post(s,'/panel/ubicaciones',d).status_code);check('Nombre literal',1,int(scalar('SELECT COUNT(*) FROM ubicaciones WHERE food_truck_id=? AND nombre=?',[FT,d['nombre']])))
    run('CP-CARTA-29',c29)
    def c30():
        r=bridge('reloj');check('PHP','America/Bogota',r['zona']);check('MySQL','-05:00',r['mysql']['zona']);check('Diferencia menor 2 s',True,abs(datetime.fromisoformat(r['php']).replace(tzinfo=None).timestamp()-datetime.fromisoformat(r['mysql']['ahora']).timestamp())<2);note(json.dumps(r,ensure_ascii=False))
    run('CP-CARTA-30',c30)
    def c31():
        p=subprocess.run(['php',str(REPO/'menu08_app/pruebas/ejecutar.php')],capture_output=True,text=True);check('Código de retorno',0,p.returncode);check('62 sin fallos',True,'62 comprobaciones, 0 fallos' in p.stdout);note(p.stdout);(EV/'unitarias.txt').write_text(p.stdout)
    run('CP-CARTA-31',c31)

def caja():
    seed();s=session('cajero')
    run('CP-CAJA-01',lambda:(check('Apertura',302,abrir(s).status_code),check('Base y estado',{'base_inicial':'50000.00','estado':'abierto'},sql('SELECT base_inicial,estado FROM turnos_caja WHERE food_truck_id=?',[FT])[0])))
    def ca02():
        sql('UPDATE turnos_caja SET estado="cerrado" WHERE food_truck_id=?',[FT]);n=count('turnos_caja')
        for x in ['-1','abc','']:check('Base '+repr(x),422,abrir(s,x).status_code)
        check('Sin turnos nuevos',n,count('turnos_caja'));abrir(s)
    run('CP-CAJA-02',ca02)
    run('CP-CAJA-03',lambda:(check('Segundo turno',409,abrir(s).status_code),check('Uno abierto',1,int(scalar("SELECT COUNT(*) FROM turnos_caja WHERE food_truck_id=? AND estado='abierto'",[FT])))))
    orders=[]
    for num,items,pay,total,nota in [(6,{'Papas de prueba':2,'Empanada de prueba':1},'efectivo','16200.00',''),(7,{'Hamburguesa de prueba':1,'Salchipapa de prueba':1,'Gaseosa de prueba':2,'Brownie de prueba':1},'tarjeta','64800.00','Sin cebolla en la hamburguesa'),(8,{'Perro de prueba':2,'Vaso de agua':1,'Limonada de prueba':1},'transferencia','44501.00','')]:
        def test(items=items,pay=pay,total=total,nota=nota,num=num):
            r=venta(s,items,pay,nota);check('POST venta',302,r.status_code);id=oid(r);orders.append(id);row=sql('SELECT numero,total,medio_pago,nota FROM ordenes WHERE id=?',[id])[0];check('Total',total,row['total']);check('Medio',pay,row['medio_pago']);check('Suma ítems',total,str(scalar('SELECT SUM(subtotal) FROM orden_items WHERE orden_id=?',[id])));check('Consecutivo',True,row['numero'].endswith('-00'+str(num-5)));check('GET comprobante',200,req(s,'GET',f'/caja/comprobante/{id}').status_code);note(json.dumps(row,ensure_ascii=False));CURRENT['orden_id']=id
        run(f'CP-CAJA-{num:02}',test)
    run('CP-CAJA-09',lambda:(check('Orden vacía',422,venta(s,{}).status_code),check('Solo 3 órdenes',3,count('ordenes'))))
    def ca11():
        r=req(s,'GET',f'/caja/comprobante/{orders[1]}');check('Comprobante',200,r.status_code)
        for txt in ['64.800','Sin cebolla en la hamburguesa','Hamburguesa de prueba','Tarjeta']:check(txt,True,txt in r.text)
    run('CP-CAJA-11',ca11)
    def ca13():
        r=req(s,'GET','/caja/turno');check('Resumen',200,r.status_code);check('SQL total','125501.00',str(scalar('SELECT SUM(total) FROM ordenes WHERE food_truck_id=?',[FT])));check('Esperado 175501',True,'175.501' in r.text)
    run('CP-CAJA-13',ca13)
    def ca14():
        r=sql('SELECT medio_pago,COUNT(*) n,SUM(total) total FROM ordenes WHERE food_truck_id=? GROUP BY medio_pago ORDER BY medio_pago',[FT]);check('Desglose',[{'medio_pago':'efectivo','n':1,'total':'16200.00'},{'medio_pago':'tarjeta','n':1,'total':'64800.00'},{'medio_pago':'transferencia','n':1,'total':'44501.00'}],r)
    run('CP-CAJA-14',ca14)
    def ca15():
        r=post(s,'/caja/turno/cerrar',dict(total_declarado='170000'),'/caja/turno');check('Cierre',302,r.status_code);row=sql('SELECT total_ventas,total_declarado,diferencia,estado,cerrado_en FROM turnos_caja WHERE food_truck_id=? ORDER BY id DESC LIMIT 1',[FT])[0];check('Ventas','125501.00',row['total_ventas']);check('Diferencia','-5501.00',row['diferencia']);check('Estado','cerrado',row['estado']);check('Marca cierre',True,row['cerrado_en'] is not None)
    run('CP-CAJA-15',ca15)
    run('CP-CAJA-16',lambda:(check('Historial',200,req(s,'GET','/caja/turnos').status_code),check('Faltante visible',True,'5.501' in req(s,'GET','/caja/turnos').text)))
    run('CP-CAJA-10',lambda:(check('Venta turno cerrado',302,post(s,'/caja/vender',{'cantidad['+str(pid('Papas de prueba'))+']':1,'medio_pago':'efectivo'},'/caja/turno').status_code),check('No aumenta',3,count('ordenes'))))
    seed();s=session('cajero');abrir(s)
    def ca17():
        r=venta(s,{'Papas de prueba':99});check('99 unidades',302,r.status_code);check('Total','643500.00',str(scalar('SELECT total FROM ordenes WHERE id=?',[oid(r)])));n=count('ordenes');check('100 unidades',422,venta(s,{'Papas de prueba':100}).status_code);check('Sin alta extra',n,count('ordenes'))
    run('CP-CAJA-17',ca17)
    def ca18():
        before=count('ordenes');bad=[pid('Entrada agotada'),scalar('SELECT id FROM productos WHERE food_truck_id<>? LIMIT 1',[FT]),999999]
        for id in bad:check('Producto '+str(id),422,post(s,'/caja/vender',{'cantidad['+str(id)+']':1,'medio_pago':'efectivo'},'/caja').status_code)
        check('Sin ventas',before,count('ordenes'));check('Agotado ausente de caja',False,'Entrada agotada' in req(s,'GET','/caja').text)
    run('CP-CAJA-18',ca18)
    def ca19():
        r=venta(s,{'Papas de prueba':3},total='1',precio='1');check('Aceptada',302,r.status_code);check('Total real','19500.00',str(scalar('SELECT total FROM ordenes WHERE id=?',[oid(r)])));check('Precio real','6500.00',str(scalar('SELECT precio_unitario FROM orden_items WHERE orden_id=?',[oid(r)])))
    run('CP-CAJA-19',ca19)
    def ca20():
        n=count('ordenes')
        for pay in ['','bitcoin']:check('Medio '+repr(pay),422,venta(s,{'Papas de prueba':1},pay).status_code)
        check('Sin ventas',n,count('ordenes'))
    run('CP-CAJA-20',ca20)
    def ca21():
        n=count('ordenes');ni=scalar('SELECT COUNT(*) FROM orden_items')
        sql("CREATE TRIGGER qa_fallo_item BEFORE INSERT ON orden_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo deliberado QA local'")
        try:check('Fallo forzado',500,venta(s,{'Papas de prueba':1}).status_code)
        finally:sql('DROP TRIGGER IF EXISTS qa_fallo_item')
        check('Cabeceras sin cambio',n,count('ordenes'));check('Ítems sin cambio',ni,scalar('SELECT COUNT(*) FROM orden_items'));note('Fallo inducido solo en la base local; no es defecto de producto. Trigger retirado en finally.')
    run('CP-CAJA-21',ca21)
    def ca22():
        r=venta(s,{'Brownie de prueba':1});id=oid(r);f=session();check('Editar',302,post(f,'/panel/productos',edit_data('Brownie de prueba',precio='12500'),'/panel/productos').status_code);check('Precio histórico','9900.00',str(scalar('SELECT precio_unitario FROM orden_items WHERE orden_id=?',[id])));check('Nombre histórico','Brownie de prueba',scalar('SELECT nombre_producto FROM orden_items WHERE orden_id=?',[id]));check('Precio actual','12500.00',str(scalar('SELECT precio FROM productos WHERE id=?',[pid('Brownie de prueba')])))
    run('CP-CAJA-22',ca22)
    def ca23():
        t=token(s,'/caja');d={'cantidad['+str(pid('Papas de prueba'))+']':1,'medio_pago':'efectivo','_token':t};n=count('ordenes');check('Primero',302,req(s,'POST','/caja/vender',data=d).status_code);check('Reenvío',403,req(s,'POST','/caja/vender',data=d).status_code);check('Una venta',n+1,count('ordenes'))
    run('CP-CAJA-23',ca23)
    def ca24():
        for x in ['-1','abc','']:check('Declarado '+repr(x),422,post(s,'/caja/turno/cerrar',dict(total_declarado=x),'/caja/turno').status_code)
        check('Sigue abierto',1,int(scalar("SELECT COUNT(*) FROM turnos_caja WHERE food_truck_id=? AND estado='abierto'",[FT])))
    run('CP-CAJA-24',ca24)
    seed();s=session('cajero');abrir(s,'200000')
    def ca25():
        check('Cierre vacío',302,post(s,'/caja/turno/cerrar',dict(total_declarado='195000'),'/caja/turno').status_code);check('Diferencia','-5000.00',str(scalar('SELECT diferencia FROM turnos_caja WHERE food_truck_id=?',[FT])));check('Ventas','0.00',str(scalar('SELECT total_ventas FROM turnos_caja WHERE food_truck_id=?',[FT])));check('Segundo cierre',302,post(s,'/caja/turno/cerrar',dict(total_declarado='195000'),'/caja/turno').status_code)
    run('CP-CAJA-25',ca25)

if __name__=='__main__':
    import sys
    if '--init' in sys.argv: seed(full=True);print('Base local inicializada')
    else:
        ids();carta();caja()
        (EV/'resultados_http.json').write_text(json.dumps(RESULTS,ensure_ascii=False,indent=2,default=str))

if __name__=='__main__':
    raise SystemExit(1 if any(r['estado']=='Fallida' or 'error_ejecutor' in r for r in RESULTS) else 0)
