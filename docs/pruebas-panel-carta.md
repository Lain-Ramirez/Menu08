# Pruebas del panel de CARTA

Ejecutadas contra **https://adso.menu08.com**, no en local. **20 comprobaciones, 0 fallos**
tras corregir el defecto del formato de precio que se describe al final.

## Aislamiento entre food trucks

Se creó un segundo food truck en la base, con su propia categoría, y se pidió esa categoría
desde la sesión de Festín Rodante:

| Petición | Resultado |
|---|---|
| `GET /panel/categorias/{id de otro truck}` | **404** |

Devuelve 404 y no 403 a propósito: el identificador del food truck se toma **de la sesión**,
nunca de la dirección, así que para esa sesión el registro sencillamente no existe. Responder
403 confirmaría que existe y pertenece a otro.

## Validación del precio

| Entrada | Resultado |
|---|---|
| `abc` | 422 con el mensaje junto al campo |
| `-5` | 422 |
| `1.2345` | 422 |

## Carga de imágenes

| Caso | Resultado |
|---|---|
| Archivo con código PHP y extensión `.jpg` | **422** — «Solo se admiten imagenes JPG, PNG o WEBP» |
| PNG de 2 MB + 1 KB | 422 por tamaño |
| JPEG válido | Aceptado |

El tipo se determina leyendo las cabeceras del archivo con `finfo`, no la extensión ni el
encabezado que envía el navegador: los dos los controla quien sube.

La imagen aceptada se guardó como `2b4becda53edaaa4b0c2c58bf2262a67.jpg` —32 hexadecimales
aleatorios, no el nombre original— y se sirve desde `/subidas/` con `Content-Type: image/jpeg`.
Los primeros bytes descargados son `ff d8 ff e0`, la firma real de un JPEG.

Cuando la imagen se guarda pero otro campo falla la validación, el archivo **se borra**. Se
comprobó en la práctica: en el intento que falló por el precio, la foto llegó a guardarse y el
código la eliminó, sin dejar huérfanas en la carpeta pública.

## Nombre repetido

Crear un segundo producto llamado «Maduritos» en la misma categoría devuelve **422** con el
mensaje junto al campo.

## Baja lógica

Tras pulsar «Agotar» sobre el producto:

| Comprobación | Resultado |
|---|---|
| Registro en la tabla `productos` | **sigue existiendo**, con `disponible = 0` y su foto |
| Consulta del catálogo público | vacía: el producto desapareció de la carta |
| Pulsar «Reponer» | vuelve a aparecer |

Esto es lo que permite que las órdenes ya registradas conserven su referencia aunque el producto
deje de venderse.

## Token contra falsificación de peticiones

`POST /panel/productos` sin token devuelve **403** y no crea nada.

## El defecto que encontraron estas pruebas

El primer intento de crear un producto con precio `12.500` fue **rechazado**. El validador leía
el punto como separador decimal y veía tres decimales.

En Colombia `12.500` son doce mil quinientos. Cualquier dueño de food truck escribe así sus
precios, de modo que el panel habría sido inutilizable para su usuario real.

Corregido: el validador acepta las dos convenciones. Con los dos separadores presentes manda el
último, y el otro se toma como separador de miles. Con solo puntos formando grupos exactos de
tres cifras, son miles.

| Se escribe | Se guarda |
|---|---|
| `12.500` | 12500.00 |
| `12.500,50` | 12500.50 |
| `12,500.50` | 12500.50 |
| `$ 8.000` | 8000.00 |
| `12,50` | 12.50 |

Probado con 14 formatos distintos, incluidos los que deben rechazarse.

Queda una ambigüedad irreducible: `1.234` se interpreta como mil doscientos treinta y cuatro y
no como uno con veintitrés. Es la lectura correcta para el contexto: un precio de 1,23 pesos no
existe en un food truck.

El formulario además **muestra** el precio en la forma en que se escribe aquí, `12.500`, en vez
de `12500.00`.
