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
| `--tipo-titulo-g` | 32/40 · 400 | `h1` |
| `--tipo-titulo-m` | 24/32 · 400 | `h2`, título de diálogo |
| `--tipo-titulo-p` | 22/28 · 500 | `h3`, título de tarjeta |
| `--tipo-cuerpo-g` | 16/24 · 400 | Base del documento |
| `--tipo-cuerpo-m` | 14/20 · 400 | Celdas de tabla, avisos |
| `--tipo-cuerpo-p` | 12/16 · 400 | Texto de apoyo y de error |
| `--tipo-etiqueta-g` | 14/20 · 500 | Rótulo de botón |
| `--tipo-etiqueta-m` | 12/16 · 500 | Encabezado de tabla, etiqueta de estado |

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
(`--radio-xp` 4 → `--radio-completo`): campo 4, etiqueta 8, tarjeta y aviso 12, diálogo 28,
botón completo. Elevación solo de los niveles 1 a 3; del 4 en adelante no aparece nada.

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

---

## Lo que cambió en `plantillas/base.php`

- Se **vació el bloque `<style>`** provisional: la plantilla ya no lleva ni una regla propia.
  Quedan cinco atributos `style` en línea en vistas todavía sin remaquetar —`panel/productos.php`,
  `panel/categorias.php`, `panel/ubicaciones.php`, `caja/inicio.php` y `plantillas/error.php`—;
  desaparecen con el #16.
- Se enlazan las tres hojas y `interfaz.js`.
- **Un solo `<main>` por documento.** Antes se emitía uno por cada mensaje de sesión más otro
  para el contenido; eso no es HTML válido.
- Los mensajes de `Sesion::mensaje()` se pintan con icono y con `role="alert"` cuando son de
  error. Un tipo desconocido cae en `aviso` en vez de en una clase sin estilo.
- `color-scheme` pasa de `light dark` a `light`, y a `dark` bajo `html.o`. Antes, con el sistema
  en oscuro, el navegador pintaba los controles nativos en oscuro sobre una página que seguía en
  claro.

## La sección 9 de `componentes.css` es temporal

Al final de la hoja hay un bloque marcado **«piezas de módulo, provisionales»**: `.carta-*`,
`.comprobante-*` y `.barra`. Venían del `<style>` de `base.php` y se reescribieron contra los
tokens, pero **no son del catálogo y no se reutilizan**. Siguen ahí porque `carta/publica.php` y
`caja/comprobante.php` todavía emiten esas clases; se borran al remaquetar esas vistas en el #15
y el #16.

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

- **El modo oscuro no tiene interruptor.** `md3.css` lo define bajo `html.o`, pero nada añade esa
  clase. Las hojas están listas; falta decidir dónde vive el control.
- **Dos funciones modernas de CSS**, las únicas de las que dependen las hojas: `color-mix()` en
  los estados deshabilitados (Chrome 111, Safari 16.2, Firefox 113) y `:has()` en el aviso
  (Chrome 105, Safari 15.4, Firefox 121). Si `:has()` faltara, el icono del aviso caería a la
  línea de arriba en vez de alinearse al lado: se degrada, no se rompe.
