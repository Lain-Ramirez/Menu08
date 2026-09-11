# Plantilla de caso de prueba

Se copia el bloque de abajo por cada caso. Los siete campos son obligatorios, también cuando la
respuesta es corta: un caso sin precondición no se puede repetir, y uno sin dato de entrada no se
puede repetir **igual**.

La nomenclatura y el alcance de cada grupo están en
[`plan-de-pruebas.md`](plan-de-pruebas.md).

---

## Bloque para copiar

```markdown
### CP-XXXX-nn · Título corto en una línea

| Campo | Contenido |
|---|---|
| **Identificador** | CP-XXXX-nn |
| **Precondición** | Qué tiene que ser cierto antes de empezar |
| **Dato de entrada** | Los valores concretos que se teclean o se envían |
| **Pasos** | 1. …<br>2. …<br>3. … |
| **Resultado esperado** | Qué debe pasar, en términos observables |
| **Resultado obtenido** | Qué pasó de verdad. Se escribe al ejecutar |
| **Estado** | Pasa · Falla · Bloqueado · No aplica |
| **Evidencia** | `evidencias/<módulo>/CP-XXXX-nn-…​.png` |
| **Defecto** | DEF-nn, o `—` |
| **Ejecutado por / cuándo** | Nombre · AAAA-MM-DD |
```

## Cómo se llena cada campo

| Campo | La regla | El error que evita |
|---|---|---|
| **Identificador** | El del plan, y no se reutiliza | Dos personas numerando a la vez |
| **Precondición** | Estado exacto del que se parte: sesión, rol, banco restaurado, turno abierto o cerrado | «A mí no me pasa» |
| **Dato de entrada** | Valores literales, no descripciones. `50000`, no «una base normal» | Un caso que cada quien ejecuta con datos distintos |
| **Pasos** | Numerados, uno por acción, en imperativo. Si son más de siete, probablemente son dos casos | Pasos que esconden tres acciones en una frase |
| **Resultado esperado** | Observable desde fuera: lo que se ve, el código de respuesta, la fila que queda en la base | «Funciona bien» |
| **Resultado obtenido** | Se escribe **al ejecutar**, no antes. Si coincide, se escribe igual: «lo esperado» no es un resultado | Rellenar el caso de memoria |
| **Estado** | Uno de los cuatro de abajo | Casillas a medio marcar |

### Los cuatro estados

| Estado | Cuándo |
|---|---|
| **Pasa** | El resultado obtenido coincide con el esperado |
| **Falla** | No coincide. Obliga a abrir un defecto y a enlazarlo |
| **Bloqueado** | No se pudo ejecutar: faltaba un dato, el ambiente estaba caído, dependía de un caso que falló. Se escribe el motivo en «resultado obtenido» |
| **No aplica** | El caso dejó de tener sentido en esta versión. Se explica por qué; no se borra |

Un caso que falla y se corrige **no se edita para poner «Pasa»**: se vuelve a ejecutar y se anota
la reejecución con su fecha, debajo del resultado anterior. El historial es la evidencia de que el
defecto se cerró de verdad.

---

## Ejemplo ya ejecutado

### CP-CAJA-07 · El cierre de turno calcula el faltante

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-07 |
| **Precondición** | Banco restaurado. Sesión con `pruebas.cajero@menu08.local`. Turno abierto con base `200000` y sin ventas |
| **Dato de entrada** | Conteo físico: `195000` |
| **Pasos** | 1. Abrir `/caja/turno`.<br>2. Escribir `195000` en «Conteo físico de la caja».<br>3. Pulsar «Cerrar turno».<br>4. Aceptar en el diálogo de confirmación. |
| **Resultado esperado** | El turno pasa a `cerrado`; el detalle muestra **faltante** de `$ 5.000`; en `turnos_caja` quedan `total_declarado = 195000.00` y `diferencia = -5000.00` |
| **Resultado obtenido** | Lo esperado. El detalle del turno #1 muestra «Faltante −$ 5.000» y la fila guarda `diferencia = -5000.00` |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-07-cierre-con-faltante.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-11 |

### CP-SEG-04 · Un cajero no entra al panel de carta

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SEG-04 |
| **Precondición** | Sesión iniciada con `pruebas.cajero@menu08.local` |
| **Dato de entrada** | Ruta `/panel/productos` escrita a mano en la barra de direcciones |
| **Pasos** | 1. Entrar como cajero.<br>2. Escribir `/panel/productos` en la barra.<br>3. Enviar. |
| **Resultado esperado** | **403** con la vista de error propia. La navegación no ofrece el enlace a CARTA para ese rol |
| **Resultado obtenido** | Se escribe al ejecutar |
| **Estado** | — |
| **Evidencia** | — |
| **Defecto** | — |
| **Ejecutado por / cuándo** | — |

---

**Issue #21 · Fase 5 - Pruebas**
