# Pruebas del cambio de estado y de la integración CAJA → SVP

Ejecutadas contra **https://adso.menu08.com** y contra la base de datos del prototipo.
**27 comprobaciones, 0 fallos**, más un defecto de usabilidad encontrado, corregido y verificado
en el servidor.

## El recorrido completo de una orden

Una orden real, registrada en CAJA y llevada hasta entregada desde el servicio del SVP:

| Paso | Comprobación | Resultado |
|---|---|---|
| CAJA registra la venta | `POST /caja/vender` → 302 | `T3-021`, id 23 |
| Nace pendiente | `ordenes.estado_id` tras la venta | `pendiente` |
| **Aparece en el SVP** | `GET /svp/ordenes` antes y después | **20 → 21 órdenes**, con la nueva entre ellas |
| `pendiente → en_preparacion` | 200, `estado_anterior` correcto | `siguiente: lista` |
| `en_preparacion → lista` | 200 | `siguiente: entregada` |
| `lista → entregada` | 200 | `siguiente: null` |
| Sale del tablero | `GET /svp/ordenes` | vuelve a 20 órdenes |

La orden aparece en el tablero **en el siguiente sondeo**, sin intervención: es la misma petición
que el tablero repite cada pocos segundos. La pantalla que hace ese sondeo sola es el issue del
tablero; aquí se comprueba que el servicio que la alimenta ya responde lo que necesita.

## Las marcas de tiempo

| Comprobación | Resultado |
|---|---|
| Las tres marcas quedaron escritas | `en_preparacion_en`, `lista_en` y `entregada_en` con valor |
| Están en orden cronológico | `creado_en ≤ en_preparacion_en ≤ lista_en ≤ entregada_en` |
| `estado_actualizado_en` es la última transición | coincide con `entregada_en` |
| Los minutos se calculan **después** de entregar | la columna `lista_en` sigue ahí |

El recorrido de arriba se hizo de un tirón, así que dio 0 segundos: prueba el mecanismo, no la
medida. Para tener un valor real se avanzó hasta `lista` una orden registrada **110 minutos
antes**:

```
orden T3-001, registrada a las 10:39
  -> en_preparacion : 200 · minutos_hasta_lista = null
  -> lista          : 200 · minutos_hasta_lista = 110
```

Y la consulta que pide el criterio, sobre `ordenes` y sin unir con nada:

```sql
SELECT numero, TIMESTAMPDIFF(MINUTE, creado_en, lista_en) AS minutos
  FROM ordenes WHERE lista_en IS NOT NULL;
```

```
T3-001  ->  110 minutos
T3-021  ->    0 minutos
```

Con una sola columna que se fuera pisando, el 110 de `T3-001` se habría perdido en cuanto la
orden pasara a entregada. Con una columna por estado sigue ahí.

De paso quedó ejercitado el umbral de demora, que estaba sin cubrir: el tablero devuelve esa orden
con `minutos: 110` y **`demorada: true`**.

## Rechazos

Ninguno movió la orden: `ordenes.estado_id` se consultó en la base después de cada uno.

| Caso | Código | Cuerpo |
|---|---|---|
| Sin token | **403** | `token_invalido` · la orden no se movió |
| Sin sesión | **401** | `no_autenticado` |
| Rol `cajero` | **403** | `rol_no_autorizado` · la orden no se movió |
| Salto de estado (`pendiente → entregada`) | **422** | «La orden T2-001 esta "pendiente": solo puede pasar a "en_preparacion", no a "entregada".» |
| Estado inexistente (`volando`) | **422** | «El estado "volando" no existe o no se puede alcanzar. Estados de destino validos: en_preparacion, lista, entregada.» |
| Orden ya entregada | **422** | «La orden T3-021 ya esta "entregada" y no admite mas cambios.» |
| Orden inexistente | **404** | `orden_no_encontrada` |
| Ninguno devuelve HTML | — | los siete con `application/json` |

La transición se comprueba **dentro de la misma transacción** que la actualizaría, con la fila
bloqueada por `SELECT … FOR UPDATE`. No es una comprobación previa que otro proceso pueda adelantar
por el flanco: en un food truck es normal tener el tablero abierto en dos sitios, o que la misma
persona pulse dos veces porque la pantalla tardó.

## Defecto encontrado y corregido

**El rol `produccion` no tenía de dónde sacar un token contra falsificación de peticiones.**
Ninguna vista del SVP lo renderizaba —`Csrf::campo()` solo aparece en los formularios de CARTA,
CAJA y el ingreso—, y las tres rutas que sí lo generan le están vedadas a ese rol.

Funcionaba **por accidente**: el token que crea el formulario de ingreso sobrevive al
`session_regenerate_id()` del login, así que la sesión arrastraba ese. Pero vence a los 120
minutos, y a partir de ahí producción se quedaba sin forma de renovarlo y el servicio de cambio de
estado se volvía inalcanzable para siempre, sin cerrar sesión y volver a entrar.

**Corregido:** la plantilla común publica el token en `<meta name="csrf-token">` cuando hay sesión
iniciada. Es lo que va a leer el tablero desde JavaScript, y de paso deja el patrón puesto para
cualquier otro servicio que se llame por `fetch`.

Comprobado ya desplegado:

| Comprobación | Resultado |
|---|---|
| `GET /svp` con sesión de producción | trae `<meta name="csrf-token" content="…">` |
| `GET /carta/festin-rodante`, pública | **no** lo trae: se emite solo en zona privada |
| Ese token, usado contra la API | `T3-002` avanzó `pendiente → en_preparacion`, 200 |

## Lo que estas pruebas no cubren todavía

- **La orden de otro food truck.** Debe responder 404, igual que una inexistente, para no
  confirmar que existe. El filtro está en el `WHERE` de la consulta con bloqueo, pero no se pudo
  provocar: en la base de demostración solo hay un food truck.
- **Dos pantallas avanzando la misma orden a la vez.** El `SELECT … FOR UPDATE` está puesto para
  eso, y la lógica se comprobó de forma secuencial —el segundo envío recibe 422—, pero no se
  lanzaron dos peticiones simultáneas de verdad.
- **Qué debe ver producción cuando el cajero cierra el turno** con órdenes aún en curso: hoy
  desaparecen del tablero en el acto. Sigue siendo una decisión de producto pendiente.
