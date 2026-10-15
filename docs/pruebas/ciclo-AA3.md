# Ciclo AA3: correcciones y reejecución

La automatización y sus registros se concentran en [`tests/`](../../tests/README.md). El ciclo inicial está conservado en [`tests/evidencias/inicial/`](../../tests/evidencias/inicial/README.md); la reejecución usa los archivos de [`tests/evidencias/actual/`](../../tests/evidencias/actual/).

| Casos inicialmente fallidos | Corrección | Cómo se vuelve a comprobar |
|---|---|---|
| CP-CARTA-08 | `catalogoCarta()` filtra `disponible=1`; la baja sigue siendo lógica. | HTTP, DOM en ambos navegadores y carpeta pública de Postman. |
| CP-AUTO-PM-01 | IDs descubiertos del catálogo; salida antes de cambiar de rol. | Newman sobre banco completo restaurado e IDs diferentes tras nuevos seeds. |
| CP-AUTO-PM-03 | Recursos comparados por origen; la carta oculta agotados. | Las 11 peticiones y 19 aserciones de la carpeta pública. |
| CP-SEG-15 | Redirección canónica en ambas reglas `.htaccess` y antes de abrir sesión en PHP. | Apache real HTTP/TLS y front controller real; 13 unitarias adicionales. |

La verificación local de HTTPS no certifica su despliegue. La respuesta HTTP del hosting, su commit y su bitácora se registran por separado. El PR no modifica el hosting.

Los informes de septiembre y del primer ciclo son históricos. Para el comportamiento vigente de esta rama, el producto no disponible queda oculto al público y continúa administrable desde el panel. Esta corrección reemplaza la decisión previa de mostrarlo atenuado.

Los seguimientos físicos/gráficos —lectura a tres metros y PDF de Firefox— y la decisión de cierre con órdenes en curso conservan su estado pendiente. Las fechas, los conteos y el commit efectivamente probado figuran en los registros de la reejecución.
