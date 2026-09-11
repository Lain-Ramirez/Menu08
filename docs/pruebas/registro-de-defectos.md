# Registro de defectos

Un defecto por fila. Se abre cuando un caso queda en **Falla**, y también cuando alguien encuentra
algo fuera de un caso: entonces se escribe primero el caso que lo reproduce y después el defecto,
porque un defecto que no se sabe reproducir no se puede dar por corregido.

La nomenclatura está en [`plan-de-pruebas.md`](plan-de-pruebas.md): `DEF-nn`, dos dígitos,
numeración corrida, sin reutilizar.

---

## La tabla

| Identificador | Módulo | Severidad | Pasos de reproducción | Estado | Responsable |
|---|---|---|---|---|---|
| DEF-01 | CAJA | Alta | 1. Entrar como `pruebas.cajero`. 2. Abrir turno con base `50000`. 3. Abrir `/caja/turno` en otra pestaña. 4. Pulsar «Abrir turno» otra vez. → Se crean dos turnos abiertos | Cerrado | Lain Ramírez |
| DEF-02 | SVP | Media | 1. Tablero abierto con órdenes. 2. Desconectar la red. 3. Esperar un ciclo de sondeo. → El tablero se queda en blanco en vez de avisar | Cerrado | Lain Ramírez |
| DEF-nn | | | | Abierto | |

> Las dos filas de arriba son ejemplos de formato con defectos ya corregidos durante la
> construcción. La primera se resolvió con la transacción y el `SELECT … FOR UPDATE` de
> `TurnoCaja::abrir()`; la segunda, con el aviso y el reintento del sondeo de `svp.js`. Se
> sustituyen por los defectos reales del primer ciclo.

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
