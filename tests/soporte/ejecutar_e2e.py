"""Pruebas reales en Chromium y Firefox con Playwright, sin modificar Menu08."""
import os
os.environ.setdefault('PLAYWRIGHT_BROWSERS_PATH','/tmp/menu08-qa-runtime/browsers')
import ejecutar_http as q
from ejecutar_http import *
from playwright.sync_api import sync_playwright, expect

def login(page,role='foodtruck'):
    page.goto(BASE+'/salir');page.goto(BASE+'/ingresar');page.locator('[name=correo]').fill('pruebas.'+role+'@menu08.local');page.locator('[name=contrasena]').fill('Menu08*Demo2026');page.locator('button[type=submit]').click();page.wait_for_url('**/'+{'foodtruck':'panel','cajero':'caja','produccion':'svp'}[role]+'**')
def shot(page,label,full=False):
    name=q.CURRENT['caso']+'-'+q.CURRENT['navegador']+'-'+label+'.png'
    page.screenshot(path=str(EV/name),full_page=full)
    q.CURRENT.setdefault('capturas',[]).append('evidencias/actual/'+name)
def dims(page):return page.evaluate('({ancho:innerWidth,cliente:document.documentElement.clientWidth,documento:document.documentElement.scrollWidth})')
def ui_run(cid,engine,fn):
    only=os.environ.get('MENU08_QA_CASOS','').split(',')
    if only!=[''] and cid not in only:return
    def t():
        q.CURRENT['navegador']=engine;q.CURRENT['herramienta']='Playwright / '+engine
        note('Navegador real automatizado en Linux; no equivale a un dispositivo Android físico.')
        fn()
    run(cid,t)
def suite(browser,engine):
    seed();ctx=browser.new_context(viewport={'width':1280,'height':720},locale='es-CO');p=ctx.new_page()
    errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    def ca16():
        for slug in ['truck-de-pruebas','festin-rodante']:
            p.goto(BASE+'/carta/'+slug)
            for w in [320,360,768]:
                p.set_viewport_size({'width':w,'height':720});d=dims(p);check(slug+' '+str(w)+' sin desborde',True,d['documento']<=d['cliente']);note(json.dumps(dict(slug=slug,**d)));shot(p,slug+'-'+str(w))
    ui_run('CP-CARTA-16',engine,ca16)
    def ca10():
        p.goto(BASE+'/carta/truck-de-pruebas');rows=sql('SELECT p.nombre,p.precio FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.food_truck_id=? AND c.activo=1 AND p.disponible=1 ORDER BY c.orden,c.id,p.disponible DESC,p.orden,p.id',[q.FT]);dom=p.locator('[data-carta-producto]').evaluate_all('(xs)=>xs.map(x=>({nombre:x.dataset.nombre,precio:x.dataset.precio}))');check('Orden catálogo', [r['nombre'] for r in rows],[r['nombre'] for r in dom]);check('Precios exactos DOM', [('$ '+format(int(float(r['precio'])+0.5),',d').replace(',','.')) for r in rows],[r['precio'] for r in dom]);note('El formato público redondea a pesos; el valor monetario exacto se comprueba por SQL y ventas en CP-CAJA-08.');shot(p,'catalogo')
    ui_run('CP-CARTA-10',engine,ca10)
    def ca32():
        p.set_viewport_size({'width':1280,'height':720});p.goto(BASE+'/ingresar');check('Campos vacíos inválidos',False,p.locator('form').evaluate('(f)=>f.checkValidity()'));p.locator('[name=correo]').fill('pruebas.foodtruck@menu08.local');p.locator('[name=contrasena]').fill('equivocada');p.locator('button[type=submit]').click();check('Correo conservado','pruebas.foodtruck@menu08.local',p.locator('[name=correo]').input_value());shot(p,'login-rechazado');p.locator('[name=contrasena]').fill('Menu08*Demo2026');p.locator('button[type=submit]').click();p.wait_for_url('**/panel');check('Negocio identificado',True,'Truck de Pruebas' in p.locator('body').inner_text());shot(p,'panel')
        for w in [360,768,1280]:
            p.set_viewport_size({'width':w,'height':720})
            for path in ['/panel','/panel/categorias','/panel/productos/nuevo','/panel/ubicaciones']:
                p.goto(BASE+path);d=dims(p);check(path+' '+str(w),True,d['documento']<=d['cliente']);check('CSRF '+path,True,p.locator('[name=_token]').count()>0 or path=='/panel')
        p.goto(BASE+'/panel/productos/nuevo');foto=next((REPO/'ADSO.menu08.com').rglob('*.jpg'));p.locator('[name=foto]').set_input_files(str(foto));expect(p.locator('#foto-previa')).to_be_visible();check('Foto previsualizada',True,p.locator('#foto-previa').evaluate('(x)=>x.src.startsWith("blob:") || x.src.startsWith("data:")'));shot(p,'formulario-foto')
    ui_run('CP-CARTA-32',engine,ca32)
    def ca33():
        sql('UPDATE categorias SET activo=0 WHERE food_truck_id=?',[q.FT]);r=p.goto(BASE+'/carta/truck-de-pruebas');check('Carta vacía 200',200,r.status);check('Mensaje',True,'La carta se está preparando' in p.locator('body').inner_text());shot(p,'catalogo-vacio');sql('UPDATE categorias SET activo=1 WHERE food_truck_id=? AND nombre<>?',[q.FT,'Categoria desactivada']);p.goto(BASE+'/carta/truck-de-pruebas');p.set_viewport_size({'width':360,'height':640});m=p.locator('.carta-barra a').evaluate_all('(xs)=>xs.map(x=>x.getBoundingClientRect().height)');check('Objetivos de al menos 44px',True,all(x>=44 for x in m));fonts=p.locator('.carta-item h3').evaluate_all('(xs)=>xs.map(x=>parseFloat(getComputedStyle(x).fontSize))');check('Texto mínimo 14px',True,bool(fonts) and min(fonts)>=14);note('Alturas enlaces: '+str(m)+'; tamaños nombres: '+str(fonts));shot(p,'movil')
    ui_run('CP-CARTA-33',engine,ca33)
    def ca34():
        p.goto(BASE+'/panel/ubicaciones');
        if '/ingresar' in p.url:login(p);p.goto(BASE+'/panel/ubicaciones')
        dias=p.locator('.panel-dia').all_text_contents();note('Días DOM: '+repr(dias));check('Días agrupados',True,any('Miércoles' in d for d in dias) and any('Sábado' in d for d in dias));shot(p,'agenda');p.goto(BASE+'/carta/truck-de-pruebas');check('Siguiente programada',True,p.locator('.carta-ahora-punto').count()==1);check('Agenda resumida',1,p.locator('details.carta-semana').count());check('Inactiva oculta',False,'Parada desactivada' in p.locator('body').inner_text());shot(p,'siguiente');
        # Crear una jornada hoy, vigente 24h, por la ruta de la aplicación.
        f=session();now=datetime.now().astimezone(timezone.utc);day=int(scalar('SELECT WEEKDAY(NOW())+1'))
        post(f,'/panel/ubicaciones',ubic(nombre='Punto vigente QA',dia_semana=str(day),hora_inicio='00:00',hora_fin='00:00'))
        p.goto(BASE+'/carta/truck-de-pruebas');check('Punto vigente','Punto vigente QA',p.locator('.carta-ahora-punto').inner_text());check('Abierto',True,'Abierto ahora' in p.locator('body').inner_text());p.set_viewport_size({'width':320,'height':640});check('Sin desborde bloque',True,dims(p)['documento']<=dims(p)['cliente']);shot(p,'vigente-320')
    ui_run('CP-CARTA-34',engine,ca34)
    # Capturas representativas de resultados HTTP y aislamiento desde navegador.
    def ca08():
        p.goto(BASE+'/carta/truck-de-pruebas');check('Agotado se oculta según AA2',0,p.locator('.carta-item-agotado').count());shot(p,'producto-agotado',True)
    ui_run('CP-CARTA-08',engine,ca08)
    # Caja por interfaz: agregar, quitar, filtrar y cobrar con efectivo.
    seed();c=session('cajero');abrir(c);ctx.close();ctx=browser.new_context(viewport={'width':1280,'height':720},locale='es-CO');p=ctx.new_page();login(p,'cajero')
    def ca04():
        b=p.locator('[data-venta-agregar="'+str(pid('Papas de prueba'))+'"]');b.click();b.click();p.locator('[data-venta-agregar="'+str(pid('Empanada de prueba'))+'"]').click();check('Total reactivo','$ 16.200',p.locator('[data-venta-total]').inner_text());check('Dos renglones',2,p.locator('.venta-linea').count());shot(p,'orden-armada');p.locator('[data-venta-vaciar]').click();check('Total vacío','$ 0',p.locator('[data-venta-total]').inner_text());check('Cobrar deshabilitado',True,p.locator('[data-venta-cobrar]').is_disabled());check('Sin errores JS',[],errors)
    ui_run('CP-CAJA-04',engine,ca04)
    def ca05():
        p.locator('[data-venta-categoria="'+str(q.CATS['Entradas'])+'"]').click();names=' '.join(p.locator('[data-venta-producto]:visible').all_inner_texts());check('Filtro entradas',True,'Papas de prueba' in names and 'Hamburguesa de prueba' not in names);p.locator('[data-venta-categoria=""]').click();p.locator('[data-venta-busqueda]').fill('limonada');names=' '.join(p.locator('[data-venta-producto]:visible').all_inner_texts());check('Búsqueda local',True,'Limonada de prueba' in names and 'Papas de prueba' not in names);shot(p,'filtro');p.locator('[data-venta-busqueda]').fill('')
    ui_run('CP-CAJA-05',engine,ca05)
    saleid=[]
    def ca26():
        for n,num in [('Papas de prueba',2),('Empanada de prueba',1)]:
            for _ in range(num):p.locator('[data-venta-agregar="'+str(pid(n))+'"]').click()
        p.locator('[data-venta-cobrar]').click();campo=p.locator('[data-venta-recibido]');campo.fill('16199');check('Insuficiente bloqueado',True,p.locator('[data-venta-confirmar]').is_disabled());shot(p,'efectivo-insuficiente');campo.fill('16200');check('Cambio exacto','$ 0',p.locator('[data-venta-cambio]').inner_text());campo.fill('50000');check('Cambio','$ 33.800',p.locator('[data-venta-cambio]').inner_text());shot(p,'cambio-correcto');p.locator('[data-venta-confirmar]').click();p.wait_for_url('**/caja/comprobante/*');id=int(p.url.rsplit('/',1)[1]);saleid.append(id);check('Persistencia','16200.00',str(scalar('SELECT total FROM ordenes WHERE id=?',[id])));shot(p,'comprobante')
    ui_run('CP-CAJA-26',engine,ca26)
    def ca12():
        # La impresión automática abre un diálogo nativo en Firefox headless.
        # Se captura el comprobante ya cargado, sin navegar de nuevo hacia él.
        p.emulate_media(media='print');shot(p,'impresion-80mm',True);css=(REPO/'ADSO.menu08.com/recursos/css/comprobante.css').read_text();check('CSS 80mm',True,'80mm' in css)
        if engine=='chromium':
            pdf=EV/'CP-CAJA-12-comprobante-80mm.pdf';p.pdf(path=str(pdf),width='80mm',height='220mm',print_background=True,prefer_css_page_size=True);check('PDF generado',True,pdf.read_bytes().startswith(b'%PDF'));note('PDF: evidencias/'+pdf.name)
        else:pending('Firefox headless permite comprobar estilos de impresión; la exportación PDF mediante diálogo gráfico requiere estación de grabación.')
        p.emulate_media(media='screen')
    ui_run('CP-CAJA-12',engine,ca12)
    # SVP: sondeo real, fallos de red y recuperación.
    id2=oid(venta(c,{'Brownie de prueba':1},nota='Extra sal'));prod=session('produccion');ctx.close();ctx=browser.new_context(viewport={'width':1280,'height':720});p=ctx.new_page();login(p,'produccion');public=ctx.new_page();public.goto(BASE+'/turnos/truck-de-pruebas')
    def sv08():
        p.goto(BASE+'/svp');before=p.evaluate('performance.timeOrigin');avanzar(prod,id2,'en_preparacion');p.wait_for_function('(id)=>document.querySelector(`[data-svp-orden="${id}"]`)?.dataset.svpEstado==="en_preparacion"',arg=id2,timeout=9000);check('Misma navegación',before,p.evaluate('performance.timeOrigin'));check('Movida por sondeo','en_preparacion',p.locator('[data-svp-orden="'+str(id2)+'"]').get_attribute('data-svp-estado'));shot(p,'sondeo');public.wait_for_timeout(4500);check('Turno visible público',True,scalar('SELECT numero FROM ordenes WHERE id=?',[id2]) in public.locator('body').inner_text());shot(public,'pantalla-publica')
    ui_run('CP-SVP-08',engine,sv08)
    def sv18():
        count0=p.locator('[data-svp-tarjeta]').count();ctx.set_offline(True);p.wait_for_timeout(6000);check('Tarjetas conservadas',count0,p.locator('[data-svp-tarjeta]').count());check('Aviso interno',True,p.locator('[data-svp-aviso]').is_visible());check('Aviso público',True,public.locator('[data-turnos-aviso]').is_visible());shot(p,'red-interrumpida');shot(public,'publico-sin-red');ctx.set_offline(False);p.wait_for_timeout(6000);check('Recupera interno',False,p.locator('[data-svp-aviso]').is_visible());check('Recupera público',False,public.locator('[data-turnos-aviso]').is_visible());shot(p,'red-recuperada');ctx.clear_cookies();p.wait_for_timeout(6000);check('Aviso sesión caducada',True,p.locator('[data-svp-aviso]').is_visible());shot(p,'sesion-caducada')
    ui_run('CP-SVP-18',engine,sv18)
    # Renovar sesión después de la negativa real 401.
    p.goto(BASE+'/ingresar');login(p,'produccion')
    def sv19():
        for w,h in [(360,640),(768,720),(1280,720)]:
            p.set_viewport_size({'width':w,'height':h})
            for dark in [False,True]:
                for page,path in [(p,'/svp'),(public,'/turnos/truck-de-pruebas')]:
                    page.set_viewport_size({'width':w,'height':h});page.goto(BASE+path);page.evaluate('(dark)=>document.documentElement.classList.toggle("o",dark)',dark);d=dims(page);check(path+' '+str(w)+' '+str(dark)+' ancho',True,d['documento']<=d['cliente']);shot(page,('svp' if path=='/svp' else 'turnos')+'-'+str(w)+('-oscuro' if dark else '-claro'))
        p.set_viewport_size({'width':1280,'height':720});p.goto(BASE+'/svp');font=p.locator('[data-svp-numero]').first.evaluate('(x)=>parseFloat(getComputedStyle(x).fontSize)');height=p.locator('[data-svp-avance]').first.bounding_box()['height'];check('Número mínimo 32px',True,font>=32);check('Botón mínimo 56px',True,height>=56);note('Número: '+str(font)+' px; botón: '+str(height)+' px.');pending('Lectura de números públicos a 3 metros en monitor físico de 24 pulgadas pendiente; emular ancho no la sustituye.')
    ui_run('CP-SVP-19',engine,sv19)
    ctx.close()

if __name__=='__main__':
    with sync_playwright() as pw:
        versions={}
        for engine in ['chromium','firefox']:
            b=getattr(pw,engine).launch();versions[engine]=b.version
            suite(b,engine);b.close()
        (EV/'navegadores.json').write_text(json.dumps(versions,indent=2))
    name='resultados_e2e_ajustes.json' if os.environ.get('MENU08_QA_CASOS') else 'resultados_e2e.json'
    (EV/name).write_text(json.dumps(q.RESULTS,ensure_ascii=False,indent=2,default=str))

if __name__=='__main__':
    raise SystemExit(1 if any(r['estado']=='Fallida' or 'error_ejecutor' in r for r in q.RESULTS) else 0)
