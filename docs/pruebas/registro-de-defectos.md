# Registro de defectos

Un defecto por fila. Se abre cuando un caso queda en **Falla**, y también cuando alguien encuentra
algo fuera de un caso: entonces se escribe primero el caso que lo reproduce y después el defecto,
porque un defecto que no se sabe reproducir no se puede dar por corregido.

La nomenclatura está en [`plan-de-pruebas.md`](plan-de-pruebas.md): `DEF-nn`, dos dígitos,
numeración corrida, sin reutilizar.

---

## Historial por ciclo de prueba

| Ciclo / Módulo | Issue | Casos ejecutados | Casos pasan | Casos fallan | Defectos registrados | Estado |
|---|---|---|---|---|---|---|
| CARTA | #22 | 16 | 14 | 2 | DEF-01, DEF-02 | Operativo con 2 defectos menores abiertos |
| CAJA | #23 | 16 | 16 | 0 | Ninguno | Aprobado sin defectos (16 pasan, 0 fallan) |
| SVP — integración con CAJA | #24 | 10 | 10 | 0 | Ninguno | Aprobado sin defectos (10 pasan, 0 fallan) |

## La tabla

| Identificador | Módulo | Severidad | Pasos de reproducción | Estado | Responsable |
|---|---|---|---|---|---|
| DEF-01 | CARTA | Media | 1. Entrar como `pruebas.foodtruck@menu08.local`. 2. En `/panel/productos`, marcar un producto como **no disponible**. 3. Abrir `/carta/{slug}`. → El producto **sigue apareciendo**, atenuado y con la etiqueta «No disponible». El criterio del #22 pide que no aparezca | Abierto | — |
| DEF-02 | CARTA | Baja | 1. Abrir `/carta/festin-rodante` con la ventana a **320 px** de ancho. 2. Comparar `document.documentElement.scrollWidth` con `clientWidth`. → 324 contra 320: el precio del primer producto termina cuatro píxeles fuera y el documento se desplaza de lado. A 360 y 768 px no ocurre | Abierto | — |

## Los defectos abiertos, con su historia

### Módulo CAJA (Issue #23)

En el ciclo de pruebas del módulo CAJA ([`casos/caja.md`](casos/caja.md)) se ejecutaron los dieciséis casos funcionales planificados (`CP-CAJA-01` a `CP-CAJA-16`). Se cubrieron apertura con base positiva/negativa y concurrencia de turnos, armado de órdenes y validación reactiva de importes en el carrito, venta con los tres medios de pago (efectivo, tarjeta, transferencia), rechazos de orden vacía y venta con turno cerrado, comprobante e impresión térmica a 80 mm en PDF, cuadre del turno contrastado contra cálculo a mano y consultas SQL, y cierre de turno con faltante.

**Resultado:** 16 casos pasaron exitosamente y 0 fallaron. No se identificaron defectos funcionales ni discrepancias en los totales almacenados contra la base de datos, por lo que **no se abrieron defectos** para este módulo.


### DEF-01 · El producto no disponible sigue saliendo en la carta

Lo encontró [`CP-CARTA-08`](casos/carta.md#cp-carta-08--producto-marcado-como-no-disponible).

**No es un descuido, es una contradicción entre dos decisiones escritas.** El criterio de
aceptación del #22 pide que un producto no disponible no aparezca en la carta. La vista hace lo
contrario a propósito: `carta/publica.php` lo pinta atenuado con su etiqueta, y `Producto::`
`catalogoCarta()` lo trae a propósito —lo dice su comentario: «la carta sí muestra lo agotado,
atenuado y con su etiqueta. El de CAJA lo deja fuera»—. Las dos decisiones son del mismo autor, en
issues distintos.

Por eso se registra en vez de corregirse sobre la marcha: **elegir entre esconderlo y atenuarlo es
una decisión de producto**, no un arreglo de código. Las dos posturas tienen argumento —esconderlo
evita que alguien pida lo que no hay; atenuarlo dice que el producto existe y que hoy se acabó—.
Quien decida cierra el defecto de una de estas dos maneras:

- **Esconderlo:** `CartaControlador` pasa a `Producto::catalogoPublico()`, que es el que ya usa
  CAJA, y la vista pierde la rama del agotado.
- **Dejarlo como está:** se corrige el criterio del issue #22 y este caso pasa a esperar lo que la
  aplicación hace.

Mientras tanto, quien quiera esconder un producto de la carta **sí tiene cómo**: desactivar su
categoría lo saca, y eso está comprobado en `CP-CARTA-09`.

### DEF-02 · La carta se desplaza de lado a 320 px

Lo encontró [`CP-CARTA-16`](casos/carta.md#cp-carta-16--la-carta-a-320-px).

Cuatro píxeles, y solo a 320. Los mide el precio del primer producto: en `.carta-fila` el nombre y
el precio comparten renglón, el precio lleva `white-space: nowrap` y a ese ancho, con la foto de
56 px y los huecos, no queda sitio para los dos. El documento entero acaba desplazándose en
horizontal, que es justo lo que el criterio del #37 prohíbe.

Severidad baja porque no impide leer ni pedir nada, pero es visible: en un teléfono pequeño la
carta «baila» al tocarla.

**Por dónde sale:** dejar que `.carta-fila` salte de línea —`flex-wrap: wrap` con el precio
alineado a la derecha con `margin-left: auto`— para que el precio baje a su propio renglón cuando
no cabe, en vez de empujar la página. Es una línea en `carta.css`, pero toca la maqueta del #17 y
se hace con su defecto delante, no de paso.

## Severidad

Se decide por **consecuencia**, no por lo difícil que sea corregirlo:

| Severidad | Qué significa | Ejemplos |
|---|---|---|
| **Alta** | Pierde o corrompe datos, deja pasar a quien no debe, o impide operar el módulo | Un turno duplicado, una orden que no queda registrada, un rol que entra donde no le toca, un truck viendo los datos de otro |
| **Media** | El módulo opera, pero un camino normal obliga a un rodeo o muestra un dato equivocado que no se guarda | El tablero no refresca solo y hay que recargar; un total mal formateado en pantalla y bien en la base |
| **Baja** | Presentación o texto, sin consecuencia sobre la operación | Un rótulo cortado a 360 px, una tilde que falta, un margen descuadrado |

**Un defecto alto cierra el ciclo en falso.** Es el único criterio de salida del plan que no admite
«se acepta y se sigue».

## Estado

```
Abierto ──▶ En corrección ──▶ Corregido ──▶ Cerrado
    │                             │
    └──────▶ Aplazado             └──▶ Reabierto  (la reejecución del caso volvió a fallar)
```

| Estado | Qué quiere decir |
|---|---|
| **Abierto** | Registrado y reproducido. Todavía nadie lo está arreglando |
| **En corrección** | Tiene responsable y está en curso |
| **Corregido** | El arreglo está hecho y desplegado, **falta reejecutar el caso** |
| **Cerrado** | El caso que lo encontró se reejecutó y pasa. Es lo único que cierra un defecto |
| **Reabierto** | La reejecución volvió a fallar. Conserva su identificador: no se abre uno nuevo |
| **Aplazado** | El equipo decide no corregirlo en este ciclo. Solo para severidad media o baja, y con el motivo escrito |

## Cómo se escriben los pasos de reproducción

Numerados, con el rol y el dato exacto, y terminando en la flecha `→` con **lo que se ve**. Quien
lea la fila tiene que poder reproducirlo sin preguntar nada:

```
1. Entrar como `pruebas.cajero@menu08.local`.
2. Abrir `/caja` con el turno cerrado.
→ 500 en vez de la redirección a `/caja/turno`.
```

Si el defecto solo aparece en un navegador, un ancho o un tema, **va en los pasos**: «en Firefox a
360 px» es parte de la reproducción, no una nota al margen.

---

**Issue #21 · Fase 5 - Pruebas**
