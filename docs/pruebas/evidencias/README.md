# Evidencias

Una carpeta por módulo, con el mismo reparto que los grupos de casos del
[plan](../plan-de-pruebas.md):

```
carta/        CP-CARTA-xx
caja/         CP-CAJA-xx
svp/          CP-SVP-xx
seguridad/    CP-SEG-xx
```

## Cómo se nombran

```
CP-CAJA-07-cierre-con-faltante.png
CP-CAJA-07-comprobante.pdf
CP-SVP-03-demora-360px.png
CP-SVP-03-demora-oscuro.png
```

Identificador del caso, un resumen en minúsculas con guiones y, cuando el caso se comprueba en
varias condiciones, la condición al final: `-360px`, `-oscuro`, `-firefox`. Una evidencia que no
empieza por el identificador no se puede emparejar con su caso.

## Qué se captura

- **La pantalla entera, con la barra de direcciones a la vista.** Una captura recortada no
  demuestra contra qué servidor se ejecutó el caso.
- **Las respuestas de servicio no se capturan en imagen**: se pegan como texto dentro del caso,
  que es donde se pueden leer y buscar.
- **El comprobante de CAJA, también en PDF**, que es la salida que se entrega al cliente.

## Restauración del ambiente

El procedimiento —qué borra `datos_pruebas.sql`, qué no toca y cuándo hay que ejecutarlo— está en
la [sección 6 del plan](../plan-de-pruebas.md#6-restauración-del-ambiente-entre-ciclos).

Las evidencias **no se rehacen** al restaurar el banco: son la foto de un ciclo concreto y se
quedan como estaban. Lo que cambia entre ciclos es el resultado obtenido del caso, no su historial.
