# La base visual y el catálogo de componentes

Qué declara cada hoja de estilo, por qué la escala es la que es, y qué queda pendiente.
Cierra el issue #14 y es la base sobre la que se maquetan CARTA, CAJA y el SVP.

El muestrario vivo está en **`/componentes`**: es una página de la aplicación, no una
captura, así que no se puede desincronizar de las hojas — usa exactamente las mismas clases
que las demás vistas.

---

## Tres hojas, un reparto claro

| Hoja | Qué declara | Autor |
|---|---|---|
| `recursos/css/md3.css` | Los 31 tokens de color de la paleta **Brasa**, en claro (`:root`) y oscuro (`html.o`) | Jovanny Medina |
| `recursos/css/base.css` | Reinicialización, tipografía, espaciado, radios, elevación, foco, área de toque y retícula | #14 |
| `recursos/css/componentes.css` | Los seis componentes del catálogo, más el diálogo y el aviso temporal | #14 |

> **Sobre el criterio 1 del issue.** Pide que `base.css` declare «variables CSS de paleta,
> tipografía, espaciado y radios». Declara las tres últimas, pero **no la paleta**: cuando el
> issue se redactó no existía `md3.css`, que llegó después y ya trae los 31 tokens de color.
> Duplicarlos en `base.css` sería crear una segunda fuente de verdad para lo mismo. Queda así a
> propósito, y se anota aquí para que quien revise el criterio sepa por qué.

Se cargan en ese orden desde `plantillas/base.php`. **`base.css` no repite ni un color de paleta**: si
alguna vez hace falta cambiar la paleta, se cambia `md3.css` y nada más. Por la misma razón el
modo oscuro no tiene una sola regla propia — funciona porque todo sale de tokens.

Ningún archivo enlaza un marco CSS ni un recurso remoto. La pila tipográfica es la del sistema,
así que la carta abre en la fila de la ventanilla sin esperar la descarga de una fuente.

---

## Las decisiones

### Tipografía: nueve papeles, no quince

MD3 define quince roles. Tres módulos no los necesitan, y una escala que nadie recuerda se
acaba usando mal. Se conservaron los nueve que tienen un sitio concreto en el prototipo:

| Variable | Valor | Dónde |
|---|---|---|
| `--tipo-pantalla` | 57/64 · 400 | El número de turno del SVP, leído desde la calle |
| `--tipo-titulo-g` | 32/40 · 700 | `h1` |
| `--tipo-titulo-m` | 24/32 · 600 | `h2`, título de diálogo |
| `--tipo-titulo-p` | 20/28 · 600 | `h3`, título de tarjeta |
| `--tipo-cuerpo-g` | 16/24 · 400 | Base del documento |
| `--tipo-cuerpo-m` | 14/20 · 400 | Celdas de tabla, avisos |
| `--tipo-cuerpo-p` | 12/16 · 400 | Texto de apoyo y de error |
| `--tipo-etiqueta-g` | 14/20 · 600 | Rótulo de botón |
| `--tipo-etiqueta-m` | 12/16 · 600 | Encabezado de tabla, etiqueta de estado |

Los títulos pesan 600–700 y no los 400 de MD3: con la pila tipográfica del sistema, un `h1` de
32 px en peso normal no se distinguía del cuerpo más que por el tamaño, y la página se leía sin
jerarquía. El `h1` además encoge en el teléfono (`clamp(26px, …, 32px)`).

El dinero y las horas llevan `font-variant-numeric: tabular-nums` (`.cifra`, `.numerica`). Sin
cifras de ancho fijo, una columna de totales baila de fila en fila y se lee torcida.

### El margen no se anula en global

Un reset moderno suele empezar por `p, h1…h4 { margin: 0 }`, porque el hueco lo reparte el
`gap` del contenedor. Aquí eso **rompía las dieciocho vistas ya construidas**: todas maquetan en
flujo (`<p><label><br><input></p>`) y ninguna usa `.pila`. `/ingresar` salía con los tres campos
pegados.

La regla es al revés: el margen de flujo se conserva, y **lo anula quien lo sustituye**.

```css
.pila > *, .fila > *, .rejilla > * { margin-block: 0; }
```

Dentro de una utilidad de disposición manda el `gap`; fuera, sigue mandando el flujo. Así el
código nuevo reparte con `gap` y el viejo se sigue leyendo mientras le llega el turno.

### Espaciado, radios y elevación

Retícula de 4 px (`--esp-1` a `--esp-8`: 4, 8, 12, 16, 24, 32, 48, 64). Escala de forma de MD3
(`--radio-xp` 4 → `--radio-completo`): campo 8, tarjeta y aviso 12, tarjeta destacada 16,
diálogo 28, botón y etiqueta completos.

Tres niveles de elevación (`--elev-1` a `--elev-3`) y un cuarto, `--elev-realce`, que es la sombra
de una pieza pulsable con el puntero encima. Las sombras son anchas y suaves, y su color sale de
`--on-surface` con `color-mix()` —un marrón casi negro—: la de MD3 lleva un 30 % de negro en el
primer trazo y sobre el crema de esta paleta se leía como un borde sucio.

### La respuesta al puntero y a la acción

Toda pieza pulsable responde igual en toda la aplicación, y la respuesta tiene tres tiempos. El
vocabulario sale de estudiar cómo reaccionan los elementos de [sena.edu.co](https://www.sena.edu.co/)
—la elevación de las tarjetas, el acercamiento de las fotos, la ficha de icono que se invierte, el
filete de la navegación, el hundimiento al pulsar— y está traducido a los tokens de la paleta Brasa.

| Momento | Qué pasa | Dónde |
|---|---|---|
| Puntero encima | Tinte de la marca (`--tinte`), borde `--anillo`, sombra `--elev-realce` y un despegue de 1 a 4 px | Botón, tarjeta-enlace, cifra y atajo del panel, ficha de CAJA, truck de la portada |
| Puntero encima | La ficha del icono se invierte (tinte → rojo lleno) y la flecha avanza | Navegación, atajos del panel, portada |
| Puntero encima | La foto se acerca dentro de su marco, en 500 ms | Ficha de CAJA, portada, carta, miniatura de la tabla |
| Pulsación | La pieza se hunde (`scale(.95–.98)`) en 60 ms | Botón, ficha de CAJA, tecla del turno, chip |
| Acción en curso | El botón que envió gira y no admite otro clic (`.boton-ocupado`); la tarjeta del SVP se atenúa (`data-svp-enviando`) | Todo formulario; tablero del SVP |
| Algo aparece | Entra deslizando (`entra`) o emerge (`emerge`) | Aviso, renglón de la orden, tarjeta del SVP, diálogo |

Tres tiempos, declarados en `base.css`: `--transicion` (150 ms, color y borde), `--mov-medio`
(300 ms, lo que se desplaza) y `--mov-lento` (500 ms, el acercamiento de una foto). Los cuatro
`@keyframes` compartidos —`entra`, `aparece`, `emerge`, `latido`— viven también ahí.

Cuatro reglas que conviene no deshacer:

- **El movimiento va tras dos guardas.** `@media (hover: hover)` deja fuera a las pantallas
  táctiles, donde `:hover` se queda pegado después del toque y la pieza aparecería levantada hasta
  tocar otra cosa. `prefers-reduced-motion: no-preference` deja fuera a quien pidió menos
  movimiento: esa persona conserva el color, el borde y la sombra, que no se mueven.
- **El hundimiento al pulsar no lleva la guarda de `hover`.** En una tableta no hay puntero que
  pasar, y el hundimiento es la única confirmación de que el toque entró.
- **El tablero del SVP no se mueve al pasar el puntero.** Se lee de lejos y con las manos
  ocupadas: ahí solo se mueve lo que acaba de pasar —una orden que entra, un envío en camino—.
- **Al papel va el estado final.** `@media print` anula animaciones y transiciones: el comprobante
  abre el diálogo de impresión al cargar, justo cuando una entrada con fundido estaría a medias.

### La línea base de los controles pelados pesa cero

La última sección de `componentes.css` viste a los controles sin maquetar. Se escribía como
`button:not(.boton):not(.boton-simbolo)`, y un `:not(.clase)` suma la especificidad de la clase que
niega: la regla pesaba (0,2,1), más que cualquier clase sola, y **le ganaba a las piezas que ya
tenían hoja propia** —`.venta-chip`, `.venta-ficha-boton`, `.turno-tecla`, `.turno-lectura-valor`—.
El chip elegido de CAJA no llegaba a pintarse de rojo y la base del turno salía con un recuadro
dentro de otro. Ahora va dentro de `:where()`, que deja el peso en cero: sigue vistiendo al control
pelado y cede ante cualquier clase.

### Lo que la hace comportarse como una aplicación

- **Salto al contenido.** Un enlace invisible hasta que recibe el foco, el primero del documento:
  quien navega con teclado no recorre cabecera y navegación en cada página.
- **Todo control nuevo sale oculto y lo enciende `interfaz.js`.** El botón de tema, los filtros, el
  cierre de avisos: sin JavaScript no aparecen y la página es la de siempre, completa.
- **Los filtros no consultan.** Esconden con `hidden` lo que el servidor ya pintó, sin tildes ni
  mayúsculas («bogota» encuentra «Bogotá»), y dicen cuándo no queda nada.

### El foco es dorado, no rojo

`--foco: 3px solid var(--secondary)`, a 2 px de separación. El dorado es la **marca**, no un
estado, así que el anillo no se confunde con el rojo de error ni con el naranja de aviso. Se
declara una sola vez, sobre `:focus-visible`, para toda la aplicación.

### El área de toque son 48 px

`--toque: 48px`, el mínimo de MD3, por encima de los 44 px de WCAG. Se teclea de pie, en la
ventanilla y con prisa. El trazo dibujado puede ser menor —un icono de 24 px— pero el blanco
pulsable llega a 48: eso es lo que hace `.boton-simbolo`.

### El color nunca decide solo

Es la advertencia que trae el propio `md3.css`:

> *La banda cálida queda densa (primary-container vs warning-container): distinguir estados
> también por icono/texto, no solo por color.*

Por eso **todo aviso y toda etiqueta de estado llevan icono y palabra** además del fondo. Quien
no separe naranja de rojo —o mire el tablero del SVP desde tres metros— sigue sabiendo qué está
leyendo. Los iconos son SVG de trazo, nunca emoji: un emoji no se recolorea con el tema y cada
sistema lo dibuja distinto.

**Dos excepciones: la marca y los módulos.** El logotipo de Menu08 es el emoji 🍴 y aparece como
tal en el sello de la cabecera, en la pantalla de acceso, en la carta pública y en el icono de la
pestaña (un SVG en línea con el emoji dentro, sin archivo que subir). Los tres módulos de la
navegación llevan también el suyo, de la misma familia: 📋 CARTA, 💵 CAJA y 👨‍🍳 SVP. Fuera de ahí
los iconos de la interfaz siguen siendo SVG de trazo. El sello va sobre `--primary-container` y no
sobre el rojo lleno, porque el emoji trae su propio color —gris plata en casi todos los sistemas—
y sobre el rojo se apagaba.

### La carta pública, el tablero y la orden de CAJA

Tres pantallas rehechas con el mismo criterio: **el orden de la pantalla es el orden de las
preguntas de quien la usa.**

- **Carta pública** (`carta/publica.php`, `carta.css`, `carta.js`). Portada con logotipo, nombre y
  el estado —abierto o cerrado, con la hora— a la vista sin desplazar; el contacto son botones que
  hacen algo (WhatsApp, llamar, Instagram); el punto del día lleva «Cómo llegar» cuando la parada
  trae coordenadas; la semana va plegada en un `<details>`; y cada producto es una ficha con la foto
  a la derecha y el precio siempre en el mismo sitio. A partir de 768 px, dos columnas.
  `carta.js` añade el buscador, el visor de fotos y el «leer más» de la descripción; los tres salen
  del servidor ocultos o sin recortar, así que sin JavaScript la carta se lee entera: las
  categorías son anclas y la foto es un enlace a la imagen.
- **La familia naranja.** El tercer color de la paleta (`--tertiary` en `md3.css`) dejó de ser
  verde y es un naranja mandarina, en claro y en oscuro, con los contrastes comprobados (6,5:1
  sobre blanco). Encima, `base.css` declara dos degradados: `--degradado`, del rojo al naranja,
  para lo que se pulsa o se elige —botones rellenos, chips elegidos, la barra flotante de la
  orden—, y `--degradado-suave`, del naranja claro al dorado claro, para lo que dice «listo»,
  «abierto» o «hecho». Las fichas de icono del panel y de la navegación se reparten los tonos de
  la familia: dorado, mandarina, salmón y ámbar.
- **Colores de la marca en los estados.** Pendiente va en el rojo tomate de la marca, en
  preparación en el dorado y lista en el naranja de la paleta Brasa, igual en el SVP, en la pantalla
  pública de turnos y en las etiquetas de estado. El naranja de aviso (`--warning`) queda solo para
  lo que de verdad avisa: un sobrante, una carta vacía, un aviso.
- **Tablero del SVP** (`svp/tablero.php`, `svp.css`). Cada tarjeta lleva una barra de tiempo en el
  borde de la cabeza, que se llena a medida que la orden se acerca al umbral de demora, y la barra
  del tablero muestra la hora de la cocina. La tarjeta tiene dos zonas: la cabeza, teñida
  del color del estado, con el número y el cronómetro, y el cuerpo, sobre fondo limpio, con los
  renglones a 18 px. El botón de avance lleva el color del estado **siguiente**. La demora invierte
  el cronómetro y pone la palabra; no se mueve nada. Botón de pantalla completa, que esconde la
  cabecera, la navegación y el pie.
- **Orden de CAJA** (`caja/venta.php`, `caja.css`, `caja.js`). El renglón va en dos pisos —nombre
  completo y subtotal arriba, unitario y contador abajo— porque en uno solo el nombre se cortaba.
  El botón dice «Cobrar $ 44.700», el título lleva la cuenta de unidades y la ficha del catálogo
  muestra cuántas lleva ya la orden. La columna crece con cada producto y, al desplazar, hasta
  ocupar la ventana (`--venta-tope` se mide al desplazar, no solo al cargar); el renglón que entra
  o cambia se trae a la vista y se resalta; vacía no se pega. Cuando el botón de cobro queda fuera
  de la vista —en el teléfono, siempre que se toca el catálogo— una barra flotante lleva la cuenta
  y el total y, al tocarla, lleva a la orden. El alto de la lista lo mide `caja.js` al desplazar:
  la columna crece con cada producto hasta que el botón de cobro toca el borde de la ventana, y
  solo entonces la lista se desplaza por dentro, nunca por debajo de tres renglones.

### El aviso solo es flex cuando trae icono

`.aviso` es un bloque normal. El `display: flex` que alinea icono y texto se activa con
`.aviso:has(> .aviso-icono)`, y no de entrada, porque siete vistas todavía meten el texto y sus
enlaces **directamente** dentro del `.aviso`, sin un `<p>` que los envuelva. Un contenedor flex
convierte cada tramo suelto de texto en una columna, y el aviso de
`panel/producto_formulario.php` saldría partido en tres. El muestrario incluye ese marcado
antiguo a propósito, para que la regresión no pueda volver sin que se vea.

### Dos puntos de quiebre, y solo dos

- **768 px** — la retícula pasa de una columna a varias (`.rejilla-2`, `-3`, `-4`).
- **480 px** — la tabla `.tabla-apilable` deja de ser tabla: cada fila pasa a ficha y el
  encabezado se reparte por celda desde `data-etiqueta`.

Todo lo demás lo resuelven `flex`, `grid` y `minmax(0, 1fr)`. Ese `minmax` no es cosmético: con
`1fr` a secas, una celda con contenido ancho estira la columna y saca la barra horizontal del
**documento**, que es justo lo que el issue prohíbe. Por lo mismo, una tabla ancha va siempre
dentro de `.tabla-envoltura`: lo que se desplaza es la tabla, nunca la página.

### Nomenclatura plana, con guion simple

`.aviso-exito`, no `.aviso--exito`. La razón es que el tipo lo emite PHP:
`Sesion::mensaje($texto, 'exito')` acaba en `class="aviso aviso-exito"`, y con guion simple la
correspondencia dato → clase es directa. Se aplicó a todo el catálogo por coherencia.

### El campo lleva el rótulo en la muesca

Campo con contorno de MD3: 56 px de alto, y el rótulo sube al borde cuando hay foco o valor. Al
enfocar el borde pasa a 2 px y el relleno baja a 15 px, de modo que la altura total no salta.

Dos cosas que hay que saber para usarlo:

1. **El rótulo va DESPUÉS del control en el marcado.** Sube con el combinador de hermanos `~`
   sobre `:placeholder-shown`, y ese combinador solo mira hacia adelante.
2. **Todo control necesita `placeholder=" "`.** Sin él, `:placeholder-shown` no deja de aplicar
   nunca y el rótulo no se mueve. Un `<select>` nunca casa con ese selector, así que se le pone
   `.campo-con-valor` a mano.

Se aparta del bloque provisional que tenía `base.php` (`<label>` + `<br>` + control). El
formulario del panel se remaqueta con esto en el #15 y el #16.

---

## `interfaz.js`

Un solo objeto global, sin bibliotecas y sin paso de compilación. Se carga con `defer`.

| Función | Qué hace |
|---|---|
| `Interfaz.aviso(texto, tipo, ms)` | Aviso temporal abajo, se retira solo a los 4 s. Se apilan tres como máximo |
| `Interfaz.confirmar(opciones)` | Diálogo modal. Devuelve una `Promise<boolean>` |
| `Interfaz.menu(boton, panel)` | Alterna `aria-expanded` y `hidden` |
| *(sin llamada)* botón ocupado | El botón que envió un formulario recibe `.boton-ocupado` y `aria-busy` hasta que llega la página siguiente |
| *(sin llamada)* `data-ver-clave` | Muestra u oculta la contraseña del campo cuyo `id` indica |
| *(sin llamada)* `data-tema` | Botón de tema claro u oscuro; recuerda la elección |
| *(sin llamada)* `data-filtro` | Busca y filtra una lista ya pintada (trucks de la portada, productos del panel), sin consulta |
| *(sin llamada)* `data-aviso-cerrar` | Cierra un mensaje de sesión; el de éxito se retira solo a los 8 s |
| *(sin llamada)* barra de progreso | Una barra fina arriba al pulsar un enlace interno o enviar un formulario; `data-sin-progreso` la evita en una descarga |

Y un enganche por atributos, para que una vista no tenga que escribir JavaScript:

```html
<button data-alterna="menu-panel">
<form data-confirmar="Se cierra el turno #3…" data-confirmar-peligro>
<button data-aviso="Copiado" data-aviso-tipo="exito">
```

**Todo es mejora progresiva.** Sin JavaScript, un formulario con `data-confirmar` se envía igual
que siempre y el menú se queda desplegado: nada de esto es requisito para operar la caja.

Tres detalles que no son evidentes:

- En una acción destructiva (`data-confirmar-peligro`) **el foco arranca en Cancelar**. Un Enter
  de más no debe cerrar un turno que no se puede reabrir.
- El diálogo **encierra el foco** mientras está abierto y lo **devuelve** al elemento que lo
  abrió al cerrarse. `Escape` cancela.
- Al confirmar se usa `requestSubmit()` y no `submit()`: `submit()` se salta la validación del
  navegador y el evento, así que un formulario inválido se enviaría igual.

El texto de un aviso se inserta con `textContent`, nunca como HTML.

Dos detalles más, de las respuestas que se enganchan solas:

- El botón se marca ocupado **después** del despacho del `submit` y solo si nadie lo detuvo: el
  diálogo de `data-confirmar` y `validacion.js` cancelan el primer envío, y marcar ahí dejaría el
  botón girando sobre un formulario que no salió. **No se deshabilita** —un botón deshabilitado no
  viaja con el formulario— y se limpia en `pageshow`, porque al volver con el botón de atrás el
  navegador restaura la página tal como quedó.
- El aviso temporal se retira con su animación de salida (`.aviso-temporal-sale`), y quien manda es
  un temporizador, no `animationend`: con `prefers-reduced-motion` ese evento puede no llegar.

---

## Lo que cambió en `plantillas/base.php`

- Se **vació el bloque `<style>`** provisional: la plantilla ya no lleva ni una regla propia.
  De los cinco atributos `style` en línea que quedaban entonces **solo sobrevive uno**, en
  `plantillas/error.php`: `panel/productos.php` y `panel/categorias.php` los perdieron con el #16,
  `panel/ubicaciones.php` con el #37 —que la reescribió entera con los componentes del catálogo—
  y `caja/inicio.php` ya no existe.
- Se enlazan las tres hojas y `interfaz.js`.
- **Un solo `<main>` por documento.** Antes se emitía uno por cada mensaje de sesión más otro
  para el contenido; eso no es HTML válido.
- Los mensajes de `Sesion::mensaje()` se pintan con icono y con `role="alert"` cuando son de
  error. Un tipo desconocido cae en `aviso` en vez de en una clase sin estilo.
- `color-scheme` pasa de `light dark` a `light`, y a `dark` bajo `html.o`. Antes, con el sistema
  en oscuro, el navegador pintaba los controles nativos en oscuro sobre una página que seguía en
  claro.

## La última sección de `componentes.css` es temporal

Al final de la hoja hay un bloque marcado **«piezas de módulo, provisionales»**. Venían del
`<style>` de `base.php` y se reescribieron contra los tokens, pero **no son del catálogo y no se
reutilizan**: cada issue se lleva las suyas al remaquetar su vista.

Ya no queda ninguna clase de módulo ahí. Las `.carta-*` se las llevó el #17 a
`recursos/css/carta.css`, y las `.comprobante-*` el #19 a `recursos/css/comprobante.css`, que es
donde viven también las reglas `@media print` del rollo de 80 mm. Lo que sigue en esa sección es
la línea base de los controles pelados —los que aún no van dentro de un `.campo`—, y se va con
el #16.

`.etiqueta-turno` hizo el camino contrario: la declaraba `caja.css`, pero desde el #19 la emiten
dos pantallas —`/caja` y `/caja/turno`— y cada una carga su propia hoja, así que subió al
catálogo junto a las demás variantes de `.etiqueta`.

---

## Comprobado

El muestrario y el marco del panel se revisaron en navegador sobre el sitio publicado,
`https://adso.menu08.com/componentes`, a los tres anchos de referencia —360, 768 y 1280 px—, y
los componentes se ven correctos. La evidencia por criterio está en el
[issue #14](https://github.com/Lain-Ramirez/Menu08/issues/14) y en el
[PR #51](https://github.com/Lain-Ramirez/Menu08/pull/51), y la del marco en el
[issue #15](https://github.com/Lain-Ramirez/Menu08/issues/15).

## El marco del panel

Cabecera, navegación y pie viven en `plantillas/cabecera.php`, `navegacion.php` y `pie.php`, y
`base.php` se limita a encadenarlos. Sus clases están en la sección 8 de `componentes.css`.

Dos cosas de ahí que conviene no deshacer:

- **La navegación no escribe los roles.** Los lee de las constantes `ROLES` de `PanelControlador`,
  `CajaControlador` y `SvpControlador`, que son las mismas que usa `exigirRol()`. Con una copia
  propia, cualquier cambio de permisos dejaría enlaces que llevan a un 403.
- **El colapso corta en `767.98px`, no en `767`.** El ancho de la ventana puede ser fraccionario
  —con el zoom del navegador— y entre 767 y 768 no aplicaría ninguna de las dos consultas: quedaba
  un botón visible que no alternaba nada. El valor está en dos sitios, `componentes.css` y el
  `data-alterna-desde` de `cabecera.php`; si se cambia uno, el otro también.

`Interfaz.menu()` acepta esa consulta de medios y solo gobierna dentro de ella. Fuera, quita el
`hidden` del panel: ese atributo saca el elemento del árbol de accesibilidad, y una navegación
visible en pantalla ancha no puede estar oculta para un lector de pantalla.

## Lo que estas hojas **no** cubren

Dicho aquí para que nadie lo dé por hecho:

- **El modo oscuro se elige, no se hereda.** `md3.css` lo define bajo `html.o` y el botón de la
  cabecera (y el de la carta pública) pone y quita esa clase; la elección se guarda en
  `localStorage` (`menu08-tema`). Por omisión sale el claro aunque el sistema esté en oscuro: es
  una decisión, no un descuido. La clase se aplica con un guion en línea en el `<head>`, antes de
  pintar, para que quien eligió el oscuro no vea un fogonazo claro en cada página.
- **Funciones modernas de CSS** de las que dependen las hojas: `color-mix()` en los estados
  deshabilitados, las sombras y los tintes (Chrome 111, Safari 16.2, Firefox 113), `:has()` en el
  aviso (Chrome 105, Safari 15.4, Firefox 121) y `:where()` en la línea base de los controles
  (Chrome 88, Safari 14, Firefox 78). Si `:has()` faltara, el icono del aviso caería a la línea de
  arriba en vez de alinearse al lado: se degrada, no se rompe. Las unidades de contenedor (`cqi`)
  de la pantalla pública de turnos van dentro de `@supports`: sin ellas el número conserva su
  tamaño anterior.
- **La revisión visual de esta ronda** se hizo pintando cada vista con datos de ejemplo y
  capturándola en un navegador sin interfaz a 360, 1280 y 1920 px —en claro, y el panel también en
  oscuro—. No
  sustituye la revisión sobre el sitio publicado con datos reales, que queda pendiente tras el
  despliegue.
