"""Servicios SVP y seguridad. Se ejecuta después de ejecutar_http.py."""
import ejecutar_http as q
from ejecutar_http import *

def svp():
    seed();s=session('produccion');c=session('cajero')
    def sv01():
        r=req(s,'GET','/svp/ordenes');d=r.json();check('HTTP',200,r.status_code);check('Sin turno',None,d['turno']);check('Sin órdenes',[],d['ordenes']);check('Total',0,d['total'])
    run('CP-SVP-01',sv01)
    abrir(c)
    def sv02():
        d=req(s,'GET','/svp/ordenes').json();check('Turno entero',True,type(d['turno']) is int);check('Lista vacía',[],d['ordenes'])
    run('CP-SVP-02',sv02)
    orders=[]
    def sv03():
        id=oid(venta(c,{'Papas de prueba':1}));orders.append(id);d=req(s,'GET','/svp/ordenes').json();check('Total',1,d['total']);check('Pendiente','pendiente',d['ordenes'][0]['estado']);check('ID coincide',id,d['ordenes'][0]['id'])
    run('CP-SVP-03',sv03)
    def sv04():
        orders.append(oid(venta(c,{'Brownie de prueba':1},nota='Extra sal')));orders.append(oid(venta(c,{'Vaso de agua':1})));d=req(s,'GET','/svp/ordenes').json();check('Total',3,d['total']);check('Todos pendientes',['pendiente']*3,[o['estado'] for o in d['ordenes']]);check('Nota','Extra sal',next(o['nota'] for o in d['ordenes'] if o['id']==orders[1]))
    run('CP-SVP-04',sv04)
    def transition(i,target,previous,nextstate,field,cid):
        def t():
            r=avanzar(s,orders[i],target);check('HTTP',200,r.status_code);d=r.json()['orden'];check('Estado',target,d['estado']);check('Anterior',previous,d['estado_anterior']);check('Siguiente',nextstate,d['siguiente']);check('Marca '+field,True,scalar('SELECT '+field+' FROM ordenes WHERE id=?',[orders[i]]) is not None)
        run(cid,t)
    transition(0,'en_preparacion','pendiente','lista','en_preparacion_en','CP-SVP-05')
    transition(0,'lista','en_preparacion','entregada','lista_en','CP-SVP-06')
    def sv07():
        r=avanzar(s,orders[0],'entregada');check('HTTP',200,r.status_code);check('Sin siguiente',None,r.json()['orden']['siguiente']);d=req(s,'GET','/svp/ordenes').json();check('Dos órdenes',2,d['total']);check('Entregada excluida',False,orders[0] in [o['id'] for o in d['ordenes']])
    run('CP-SVP-07',sv07)
    avanzar(s,orders[1],'en_preparacion')
    def sv09():
        d=req(s,'GET','/svp/ordenes').json();check('Total',2,d['total']);check('Orden por estado',['pendiente','en_preparacion'],[o['estado'] for o in d['ordenes']]);check('Nota conservada','Extra sal',d['ordenes'][1]['nota'])
    run('CP-SVP-09',sv09)
    def sv10():
        avanzar(s,orders[1],'lista');avanzar(s,orders[1],'entregada');d=req(s,'GET','/svp/ordenes').json();check('Una pendiente',1,d['total']);check('Tercera conservada',orders[2],d['ordenes'][0]['id'])
    run('CP-SVP-10',sv10)
    def sv11():
        for n in [9,10]:
            sql('UPDATE ordenes SET creado_en=DATE_SUB(NOW(),INTERVAL '+str(n)+' MINUTE) WHERE id=?',[orders[2]]);d=req(s,'GET','/svp/ordenes').json();o=d['ordenes'][0];check('Minutos',n,o['minutos']);check('Demorada a '+str(n),n>=10,o['demorada'])
        note('Antigüedad controlada por UPDATE únicamente en la base local.')
    run('CP-SVP-11',sv11)
    def sv12():
        for id,target in [(orders[2],'entregada'),(orders[2],'volando'),(orders[0],'en_preparacion')]:
            before=sql('SELECT estado_id,en_preparacion_en,lista_en,entregada_en FROM ordenes WHERE id=?',[id]);r=avanzar(s,id,target);check('Rechazo '+target,422,r.status_code);check('Error','transicion_invalida',r.json()['error']);check('Sin cambios',before,sql('SELECT estado_id,en_preparacion_en,lista_en,entregada_en FROM ordenes WHERE id=?',[id]))
    run('CP-SVP-12',sv12)
    def sv13():
        r=sql('SELECT creado_en,en_preparacion_en,lista_en,entregada_en,estado_actualizado_en,TIMESTAMPDIFF(MINUTE,creado_en,lista_en) duracion FROM ordenes WHERE id=?',[orders[0]])[0];check('Cronología',True,r['creado_en']<=r['en_preparacion_en']<=r['lista_en']<=r['entregada_en']);check('Última marca',r['entregada_en'],r['estado_actualizado_en']);check('Duración no negativa',True,r['duracion']>=0);note(json.dumps(r))
    run('CP-SVP-13',sv13)
    def sv14():
        r=req(s,'GET','/svp/ordenes');d=r.json();check('JSON',True,'application/json' in r.headers['Content-Type']);check('Total coincide',len(d['ordenes']),d['total']);o=d['ordenes'][0]
        for k,t in [('minutos',int),('demorada',bool),('id',int)]:check('Tipo '+k,True,type(o[k]) is t)
        check('Ítems tipados',True,all(type(i['cantidad']) is int and isinstance(i['nombre'],str) for i in o['items']));check('Sin entregadas',True,all(o['estado']!='entregada' for o in d['ordenes']))
    run('CP-SVP-14',sv14)
    def sv15():
        avanzar(s,orders[2],'en_preparacion');r=req(session(login=False),'GET','/turnos/truck-de-pruebas/ordenes');d=r.json();check('Público 200',200,r.status_code);check('Solo numero y estado',True,all(set(o)=={'numero','estado'} for o in d['ordenes']));check('Visible',1,d['total']);avanzar(s,orders[2],'lista');check('Lista pública','lista',req(s,'GET','/turnos/truck-de-pruebas/ordenes').json()['ordenes'][0]['estado']);avanzar(s,orders[2],'entregada');check('Entregada excluida',0,req(s,'GET','/turnos/truck-de-pruebas/ordenes').json()['total'])
    run('CP-SVP-15',sv15)
    def sv16():
        d=req(session(login=False),'GET','/turnos/truck-de-pruebas/ordenes').json();check('Abierto vacío',True,type(d['turno']) is int and d['ordenes']==[]);post(c,'/caja/turno/cerrar',dict(total_declarado='50000'),'/caja/turno');d=req(session(login=False),'GET','/turnos/truck-de-pruebas/ordenes').json();check('Cerrado vacío',True,d['turno'] is None and d['ordenes']==[])
    run('CP-SVP-16',sv16)
    def sv17():
        for slug in ['no-existe','truck-de-pruebas']:
            if slug=='truck-de-pruebas':sql('UPDATE food_trucks SET activo=0 WHERE id=?',[q.FT])
            check('Pantalla '+slug,404,req(s,'GET','/turnos/'+slug).status_code);r=req(s,'GET','/turnos/'+slug+'/ordenes');check('Servicio '+slug,404,r.status_code);check('Error JSON','food_truck_no_encontrado',r.json()['error'])
        sql('UPDATE food_trucks SET activo=1 WHERE id=?',[q.FT]);check('Reactivado',200,req(s,'GET','/turnos/truck-de-pruebas').status_code)
    run('CP-SVP-17',sv17)
    def sv20():
        abrir(c);id=oid(venta(c,{'Papas de prueba':1}));check('Orden en curso',1,req(s,'GET','/svp/ordenes').json()['total']);post(c,'/caja/turno/cerrar',dict(total_declarado='56500'),'/caja/turno');d=req(s,'GET','/svp/ordenes').json();check('Después cierre',True,d['turno'] is None and d['ordenes']==[]);check('Orden persiste',1,int(scalar('SELECT COUNT(*) FROM ordenes WHERE id=?',[id])));pending('El repositorio deja pendiente la aceptación de negocio de cerrar con órdenes pendientes. Comportamiento técnico comprobado.')
    run('CP-SVP-20',sv20)
    seed();c=session('cajero');s=session('produccion');abrir(c)
    def sv21():
        for _ in range(20):venta(c,{'Papas de prueba':1})
        r=req(s,'GET','/svp/ordenes');check('20 órdenes',20,r.json()['total']);check('Menos de 1000 ms',True,r.elapsed.total_seconds()<1);m=bridge('perfil',ft=q.FT);check('Como máximo 2 consultas',True,len(m['consultas'])<=2);check('Modelo devuelve 20',20,m['ordenes']);note(json.dumps(m,ensure_ascii=False));note('Tiempo HTTP real: '+str(round(r.elapsed.total_seconds()*1000,2))+' ms. Se miden consultas Execute de esta conexión; no es ensayo de carga.')
    run('CP-SVP-21',sv21)

def seguridad():
    seed();f=session();c=session('cajero');p=session('produccion');abrir(c)
    def se01():
        for correo,clave in [("prueba@menu08.local'",'x'),("' OR '1'='1",'x'),("plataforma@menu08.local'-- ",'x'),('x" OR ""="','x" OR ""="')]:
            s=session(login=False);r=post(s,'/ingresar',dict(correo=correo,contrasena=clave));check('Rechazo '+correo,401,r.status_code);check('No hay sesión',302,req(s,'GET','/panel').status_code);check('Sin traza',False,any(x in r.text for x in ['SQLSTATE','PDOException']))
    run('CP-SEG-01',se01)
    def se02():
        for slug in ["' OR '1'='1",'festin-rodante\" OR 1=1 --',"truck-de-pruebas' UNION SELECT 1--"]:
            r=req(session(login=False),'GET','/carta/'+requests.utils.quote(slug,safe=''));check('Slug malicioso',404,r.status_code);check('Sin SQL',False,'SQLSTATE' in r.text)
    run('CP-SEG-02',se02)
    def se03():
        for x in ['1 OR 1=1','0 UNION SELECT * FROM usuarios','abc']:
            r=req(f,'GET','/panel/productos',params={'categoria':x});check('Filtro '+x,200,r.status_code);check('Sin SQL',False,any(t in r.text for t in ['SQLSTATE','PDOException']))
    run('CP-SEG-03',se03)
    def se04():
        d={"cantidad[1' OR '1'='1]":2,'cantidad[999999) UNION SELECT id,nombre,precio FROM usuarios--]':1,'medio_pago':'efectivo'};n=count('ordenes');check('Solo claves maliciosas',422,post(c,'/caja/vender',d,'/caja').status_code);check('Sin inserción',n,count('ordenes'));r=venta(c,{'Papas de prueba':1},**{"cantidad[1' OR '1'='1]":5});check('Mezcla',302,r.status_code);check('Total válido','6500.00',str(scalar('SELECT total FROM ordenes WHERE id=?',[oid(r)])))
    run('CP-SEG-04',se04)
    def se05():
        name='<script>alert("QA")</script>';desc='<img src=x onerror=alert("QA")>';d=product_data(name);d['descripcion']=desc;check('Guardar texto',302,post(f,'/panel/productos',d,'/panel/productos/nuevo').status_code);id=scalar('SELECT id FROM productos WHERE food_truck_id=? AND nombre=?',[q.FT,name])
        for path in ['/panel/productos',f'/panel/productos/{id}','/carta/truck-de-pruebas']:
            r=req(f,'GET',path);check('Script escapado '+path,False,name in r.text);check('Nombre como texto '+path,True,'&lt;script&gt;' in r.text)
        post(f,'/panel/productos/disponibilidad',dict(id=id),'/panel/productos');r=req(f,'GET','/carta/truck-de-pruebas');check('Agotado escapado',False,name in r.text);note('La comprobación de ejecución y ausencia de alertas se complementa desde navegador.')
    run('CP-SEG-05',se05)
    def se06():
        for role in [None,'foodtruck','cajero','produccion','plataforma']:
            s=session(role,role is not None)
            for path,roles in [('/panel',['foodtruck','plataforma']),('/panel/productos',['foodtruck']),('/caja',['foodtruck','cajero']),('/svp',['foodtruck','produccion'])]:
                exp=302 if role is None else 200 if role in roles else 403
                check(str(role)+' '+path,exp,req(s,'GET',path).status_code)
    run('CP-SEG-06',se06)
    def se07():
        paths=['/ingresar','/panel/food-truck','/panel/categorias','/panel/categorias/estado','/panel/productos','/panel/productos/disponibilidad','/panel/ubicaciones','/panel/ubicaciones/estado','/caja/turno/abrir','/caja/turno/cerrar','/caja/vender']
        before=summary()
        for path in paths:
            s=session(login=False) if path=='/ingresar' else f
            page='/ingresar' if path=='/ingresar' else '/panel'
            valid={
                '/ingresar':dict(correo='pruebas.foodtruck@menu08.local',contrasena='Menu08*Demo2026'),
                '/panel/food-truck':dict(nombre='Truck de Pruebas',slug='truck-de-pruebas',ciudad='Bogota'),
                '/panel/categorias':dict(nombre='Categoria CSRF QA',orden='9'),
                '/panel/categorias/estado':dict(id=q.CATS['Entradas']),
                '/panel/productos':product_data('Producto CSRF QA'),
                '/panel/productos/disponibilidad':dict(id=pid('Papas de prueba')),
                '/panel/ubicaciones':ubic(nombre='Parada CSRF QA'),
                '/panel/ubicaciones/estado':dict(id=scalar('SELECT id FROM ubicaciones WHERE food_truck_id=? LIMIT 1',[q.FT])),
                '/caja/turno/abrir':dict(base_inicial='50000'),
                '/caja/turno/cerrar':dict(total_declarado='56500'),
                '/caja/vender':{'cantidad['+str(pid('Papas de prueba'))+']':1,'medio_pago':'efectivo'},
            }
            turn=scalar("SELECT id FROM turnos_caja WHERE food_truck_id=? AND estado='abierto' LIMIT 1",[q.FT])
            if path=='/caja/turno/abrir':sql("UPDATE turnos_caja SET estado='cerrado' WHERE id=?",[turn])
            for variant in ['ausente','alterado','vencido']:
                t=token(s,page);data=dict(valid[path])
                if variant=='alterado':data['_token']='0'*64
                if variant=='vencido':
                    sid=s.cookies.get('menu08_sesion');file=ROOT/'soporte/sesiones'/('sess_'+sid)
                    raw=file.read_text();raw,n=re.subn(r'(s:5:"vence";i:)\d+',lambda m:m[1]+str(int(time.time())-1),raw);file.write_text(raw)
                    if not n:raise RuntimeError('No se localizó fecha de vencimiento de la sesión de pruebas')
                    data['_token']=t
                check(path+' '+variant,403,req(s,'POST',path,data=data).status_code)
            if path=='/caja/turno/abrir':sql("UPDATE turnos_caja SET estado='abierto' WHERE id=?",[turn])
        check('Sin mutaciones',before,summary());note('Vencimiento reproducido conservando el token real y adelantando solo su fecha de expiración en el archivo de sesión local; no se esperaron 120 minutos.')
    run('CP-SEG-07',se07)
    def se08():
        for s,code,error in [(session(login=False),401,'no_autenticado'),(c,403,'rol_no_autorizado')]:
            r=req(s,'GET','/svp/ordenes');check('HTTP',code,r.status_code);check('Error',error,r.json()['error']);check('JSON',True,'application/json' in r.headers['Content-Type'])
        id=scalar('SELECT id FROM ordenes WHERE food_truck_id=? LIMIT 1',[q.FT])
        for t in [None,'0'*64]:
            r=req(p,'POST',f'/svp/orden/{id}/estado',data={'estado':'en_preparacion',**({} if t is None else {'_token':t})});check('Token inválido',403,r.status_code);check('Error','token_invalido',r.json()['error'])
        for s in [f,p]:check('Rol permitido',200,req(s,'GET','/svp/ordenes').status_code)
    run('CP-SEG-08',se08)
    def se09():
        for table,path in [('productos','/panel/productos/'),('categorias','/panel/categorias/'),('ubicaciones','/panel/ubicaciones/')]:
            other=scalar('SELECT id FROM '+table+' WHERE food_truck_id<>? LIMIT 1',[q.FT]);check('Ajeno '+table,404,req(f,'GET',path+str(other)).status_code);check('Ausente '+table,404,req(f,'GET',path+'999999').status_code)
            before=sql('SELECT * FROM '+table+' WHERE id=?',[other]);endpoint={'productos':'/panel/productos','categorias':'/panel/categorias','ubicaciones':'/panel/ubicaciones'}[table]
            data=product_data('Ajeno QA') if table=='productos' else dict(nombre='Ajeno QA',orden='1') if table=='categorias' else ubic(nombre='Ajeno QA');data['id']=other
            check('POST ajeno '+table,404,post(f,endpoint,data,endpoint).status_code);check('Fila ajena intacta '+table,before,sql('SELECT * FROM '+table+' WHERE id=?',[other]))
        # Los recursos de ventas ajenos se preparan por la interfaz del otro truck local.
        other=session(login=False);post(other,'/ingresar',dict(correo='foodtruck@menu08.local',contrasena='Menu08*Demo2026'));abrir(other);r=post(other,'/caja/vender',{'cantidad['+str(scalar('SELECT id FROM productos WHERE food_truck_id<>? AND disponible=1 LIMIT 1',[q.FT]))+']':1,'medio_pago':'efectivo'},'/caja');id=oid(r)
        check('Comprobante ajeno',404,req(c,'GET',f'/caja/comprobante/{id}').status_code)
        for x in [id,999999]:
            r=avanzar(p,x,'en_preparacion');check('Orden ajena/ausente',404,r.status_code);check('Error igual','orden_no_encontrada',r.json()['error'])
        check('Otra orden intacta','pendiente',scalar('SELECT e.codigo FROM ordenes o JOIN estados_orden e ON e.id=o.estado_id WHERE o.id=?',[id]))
    run('CP-SEG-09',se09)
    def se10():
        s=session(login=False);t=token(s,'/ingresar');old=s.cookies.get('menu08_sesion');r=req(s,'POST','/ingresar',data=dict(correo='pruebas.foodtruck@menu08.local',contrasena='Menu08*Demo2026',_token=t));new=s.cookies.get('menu08_sesion');check('ID regenerado',True,old!=new);hdr=r.headers.get('Set-Cookie','');check('HttpOnly',True,'HttpOnly' in hdr);check('SameSite Lax',True,'SameSite=Lax' in hdr);req(s,'GET','/salir');check('Privado bloqueado',302,req(s,'GET','/panel').status_code);pending('Secure solo se valida por HTTPS. Instancia local HTTP con solo_https=false; pendiente comprobar cookie del despliegue.')
    run('CP-SEG-10',se10)
    def se11():
        for path in ['/menu08_app/configuracion/configuracion.php','/menu08_app/basedatos/esquema.sql','/menu08_app/almacenamiento/bitacora','/configuracion/configuracion.php','/basedatos/esquema.sql']:
            r=req(f,'GET',path);check(path,404,r.status_code);check('Sin credencial local',False,'Menu08LocalQA2026' in r.text)
        check('CSS público',200,req(f,'GET','/recursos/css/md3.css').status_code)
    run('CP-SEG-11',se11)
    def se12():
        n=session(login=False);r=req(n,'POST','/movil/ubicacion',data={'latitud':'4.65','longitud':'-74.05'});check('Sin sesión',401,r.status_code)
        for role in ['cajero','produccion']:
            s=session(role);r=req(s,'POST','/movil/ubicacion',data={'latitud':'4.65','longitud':'-74.05'});check('Rol '+role,403,r.status_code);check('Error','rol_no_autorizado',r.json()['error'])
        s=session(login=False);r=req(s,'POST','/movil/ingresar',data=dict(correo='pruebas.foodtruck@menu08.local',contrasena='Menu08*Demo2026'));check('Ingreso móvil',200,r.status_code);d=r.json();check('Sin contraseña',False,'contrasena' in d.get('usuario',{}));t=d['token_csrf']
        check('Sin token',403,req(s,'POST','/movil/ubicacion',data={'latitud':'4.65','longitud':'-74.05'}).status_code);check('Coordenada inválida',422,req(s,'POST','/movil/ubicacion',data={'latitud':'200','longitud':'-74.05','_token':t}).status_code)
        before=count('ubicaciones');r=req(s,'POST','/movil/ubicacion',data={'latitud':'4.65','longitud':'-74.05','_token':t,'food_truck_id':'1'});check('Camino válido 200 o 201',True,r.status_code in [200,201]);note('HTTP obtenido en reporte válido: '+str(r.status_code));check('Sin filas ante error',before,count('ubicaciones')) if r.status_code==500 else None
        if r.status_code in [200,201]:
            id=r.json()['parada']['id'];check('Truck de la sesión',q.FT,int(scalar('SELECT food_truck_id FROM ubicaciones WHERE id=?',[id])));check('Latitud persistida','4.6500000',str(scalar('SELECT latitud FROM ubicaciones WHERE id=?',[id])));n=count('ubicaciones')
            r=req(s,'POST','/movil/ubicacion',data={'latitud':'4.66','longitud':'-74.06','_token':t});check('Reporte posterior actualiza',200,r.status_code);check('Misma parada',id,r.json()['parada']['id']);check('No duplicación',n,count('ubicaciones'));check('Latitud actualizada','4.6600000',str(scalar('SELECT latitud FROM ubicaciones WHERE id=?',[id])))
    run('CP-SEG-12',se12)
    def se13():
        s=session(login=False)
        for path in ['/','/carta/truck-de-pruebas','/carta/no-existe','/panel','/svp/ordenes','/caja/vender']:
            g=req(s,'GET',path);h=req(s,'HEAD',path);check('Estado '+path,g.status_code,h.status_code);check('Tipo '+path,g.headers.get('Content-Type'),h.headers.get('Content-Type'));check('Cuerpo '+path,0,len(h.content))
    run('CP-SEG-13',se13)
    def se14():
        rows=sql('SELECT contrasena FROM usuarios');check('Bcrypt todos',True,all(r['contrasena'].startswith('$2y$') for r in rows));user=(REPO/'menu08_app/aplicacion/modelos/Usuario.php').read_text();check('password_verify',True,'password_verify(' in user);bd=(REPO/'menu08_app/aplicacion/nucleo/ConexionBD.php').read_text();check('Preparadas reales',True,'PDO::ATTR_EMULATE_PREPARES   => false' in bd);note('Inspección de código: modelos usan prepare y execute; concatenaciones de consultas revisadas en modelos, se conservan referencias del commit en Fuentes. No es prueba de penetración.')
    run('CP-SEG-14',se14)

if __name__=='__main__':
    ids();svp();seguridad()
    (EV/'resultados_servicios.json').write_text(json.dumps(q.RESULTS,ensure_ascii=False,indent=2,default=str))

if __name__=='__main__':
    raise SystemExit(1 if any(r['estado']=='Fallida' or 'error_ejecutor' in r for r in q.RESULTS) else 0)
