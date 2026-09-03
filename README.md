# Menu08

Plataforma de carta digital, venta y producción para **food trucks**.

Un food truck no tiene local ni mesas: tiene una ventanilla, una fila y un punto que
cambia según el día. Menu08 está construido alrededor de eso.

El primer food truck de la plataforma es **Festín Rodante**.

| | Módulo | Quién lo usa | Qué resuelve |
|---|---|---|---|
| 🍽️ | **CARTA** | El cliente en la fila y el administrador del truck | Carta digital que se abre leyendo el QR pegado en la ventanilla, con la agenda de puntos donde para el truck. Y el panel donde se administran categorías, productos y paradas |
| 💵 | **CAJA** | El cajero | Apertura y cierre de turno, armado de la orden, total, medio de pago y **número de turno** para el cliente |
| 📺 | **SVP** | Producción y el cliente | **Sistema de Visualización de Producción**: tablero interno dentro del truck y **pantalla pública de turnos** en la ventanilla |

- Prototipo publicado en **https://adso.menu08.com**
- Proyecto formativo iniciado en **junio de 2025** · Tecnólogo en Análisis y Desarrollo de Software (ADSO), SENA · Ficha 3235887

---

## Cómo encajan los tres módulos

CARTA se construye primero porque es el catálogo del que dependen las otras dos:
sin productos no hay nada que vender en CAJA ni nada que mostrar en el SVP.

```mermaid
flowchart LR
    subgraph CARTA["🍽️ CARTA"]
        A1["Panel del truck<br/>categorías y productos"]
        A5["Agenda de paradas<br/>dónde estamos hoy"]
        A2["Carta pública<br/>por slug del truck"]
        A3["Código QR<br/>en la ventanilla"]
    end
    subgraph CAJA["💵 CAJA"]
        B1["Apertura de turno"]
        B2["Armado de la orden"]
        B3["Cobro y número de turno"]
    end
    subgraph SVP["📺 SVP"]
        C1["Tablero interno<br/>dentro del truck"]
        C2["Pantalla pública<br/>de turnos"]
    end

    A1 -->|"define el catálogo"| A2
    A5 -->|"dónde para hoy"| A2
    A1 -->|"provee los productos"| B2
    A2 --- A3
    A3 -.->|"el cliente escanea<br/>haciendo fila"| A2
    B1 --> B2 --> B3
    B3 -->|"registra la orden"| C1
    C1 -->|"avance de estado"| C2
    C2 -.->|"llaman su número"| B3

    style CARTA fill:#e8f5e9,stroke:#2e7d32
    style CAJA fill:#ede7f6,stroke:#4527a0
    style SVP fill:#fff8e1,stroke:#f9a825
```

## Recorrido de una venta

```mermaid
sequenceDiagram
    autonumber
    actor Cliente
    actor Cajero
    participant CARTA as CARTA
    participant CAJA as CAJA
    participant BD as MySQL
    participant SVP as SVP
    actor Produccion as Producción

    Cliente->>CARTA: Escanea el QR de la ventanilla mientras hace fila
    CARTA-->>Cliente: Carta del truck y el punto donde está hoy
    Cliente->>Cajero: Pide en la ventanilla
    Cajero->>CAJA: Arma la orden con los productos del catálogo
    CAJA->>BD: Guarda la orden, sus ítems y el total
    CAJA-->>Cliente: Entrega el número de turno
    Note over CAJA,BD: La orden nace en estado "pendiente"
    loop Sondeo cada pocos segundos
        SVP->>BD: Consulta las órdenes en curso
        BD-->>SVP: Devuelve el tablero en JSON
    end
    SVP-->>Produccion: Tablero interno con la orden
    Produccion->>SVP: Marca "en preparación" y luego "lista"
    SVP->>BD: Actualiza el estado de la orden
    SVP-->>Cliente: La pantalla pública muestra su número como listo
    Cliente->>Cajero: Recoge en la ventanilla
    Cajero->>CAJA: Marca la orden como entregada
```

## Ciclo de vida de la orden

```mermaid
stateDiagram-v2
    [*] --> pendiente : CAJA registra la orden y asigna el número
    pendiente --> en_preparacion : Producción la toma
    en_preparacion --> lista : Producción termina
    lista --> entregada : El cliente la recoge en la ventanilla
    entregada --> [*]

    note right of en_preparacion
        La pantalla pública de turnos
        muestra los números en
        preparación y los listos
    end note

    note right of pendiente
        El tablero interno resalta
        la orden si lleva demasiado
        tiempo sin avanzar
    end note
```

---

## Arquitectura

PHP 8.2 con programación orientada a objetos, patrón **MVC construido a mano**,
sin gestor de dependencias ni marcos de trabajo externos. Una sola puerta de entrada:
`publico/index.php`.

```mermaid
flowchart TD
    N["🌐 Navegador"] -->|"petición HTTP"| H[".htaccess<br/>mod_rewrite"]
    H --> FC["publico/index.php<br/><i>front controller</i>"]
    FC --> E["Enrutador<br/>resuelve método y patrón"]
    E -->|"sin coincidencia"| E404["Vista de error 404"]
    E -->|"ruta privada"| S{"¿Sesión<br/>con el rol<br/>correcto?"}
    S -->|"no"| L["Redirige al ingreso<br/>o responde 403"]
    S -->|"sí"| C
    E -->|"ruta pública"| C["Controlador"]
    C --> M["Modelo"]
    M --> BD["ConexionBD<br/>PDO con sentencias preparadas"]
    BD -->|"sentencias preparadas"| DB[("MySQL 8<br/>menu08")]
    C --> V["Vista<br/>plantillas compartidas"]
    V --> N
    C -.->|"error no controlado"| B["Bitácora<br/>almacenamiento/bitacora"]

    style FC fill:#e3f2fd,stroke:#1565c0
    style DB fill:#fff3e0,stroke:#e65100
    style S fill:#fce4ec,stroke:#ad1457
```

### Estructura de carpetas

El repositorio es un **espejo del servidor**: las dos carpetas de primer nivel se llaman
igual que en el hosting y se copian tal cual, sin traducir nada.

```
menu08_app/                 🔒 privada · se copia a /home/sfacturs2/menu08_app/
  publico/
    index.php               front controller
    .htaccess
  aplicacion/
    nucleo/                 Enrutador, ConexionBD, Controlador, Vista, Sesion,
                            Csrf, Validador, GestorImagenes, GeneradorQr
    controladores/          XxxControlador.php
    modelos/                Xxx.php
    vistas/                 plantillas/ auth/ panel/ carta/ caja/ svp/
  configuracion/
    configuracion.ejemplo.php  plantilla versionada
    configuracion.php          credenciales reales — NUNCA se versiona
    rutas.php                  tabla de rutas
  basedatos/
    esquema.sql             las nueve tablas
    datos_iniciales.sql     estados, food truck y usuarios de demostración
  almacenamiento/
    bitacora/               registro de errores

ADSO.menu08.com/            🌐 pública · se copia a /home/sfacturs2/ADSO.menu08.com/
  index.php                 puente hacia menu08_app/publico/index.php
  .htaccess                 reescritura y cabeceras de seguridad
  recursos/                 css, js e imágenes
  subidas/                  logos, fotos de producto y códigos QR

docs/                       documentación del proyecto
```

**Nada sensible vive en la carpeta pública.** La configuración con las credenciales, los
scripts de la base de datos y el código de la aplicación quedan fuera del alcance del
servidor web por estructura, no por una regla de `.htaccess` que alguien pueda desactivar.
Ver [`docs/despliegue.md`](docs/despliegue.md).

---

## Modelo de datos

Nueve tablas en MySQL 8, motor InnoDB, cotejamiento `utf8mb4_unicode_ci`.
Todos los montos son `DECIMAL(10,2)`.

```mermaid
erDiagram
    FOOD_TRUCKS ||--o{ UBICACIONES  : "para en"
    FOOD_TRUCKS ||--o{ USUARIOS     : "da acceso a"
    FOOD_TRUCKS ||--o{ CATEGORIAS   : "organiza su carta en"
    FOOD_TRUCKS ||--o{ PRODUCTOS    : "ofrece"
    FOOD_TRUCKS ||--o{ TURNOS_CAJA  : "opera"
    FOOD_TRUCKS ||--o{ ORDENES      : "vende"
    CATEGORIAS   ||--o{ PRODUCTOS   : "agrupa"
    USUARIOS     ||--o{ TURNOS_CAJA : "abre"
    TURNOS_CAJA  ||--o{ ORDENES     : "acumula"
    ESTADOS_ORDEN ||--o{ ORDENES    : "clasifica"
    ORDENES      ||--o{ ORDEN_ITEMS : "detalla en"
    PRODUCTOS    |o--o{ ORDEN_ITEMS : "se vende como"

    FOOD_TRUCKS {
        int id PK
        varchar nombre "Festín Rodante"
        varchar slug UK "identifica la carta pública"
        varchar logo
        varchar whatsapp
        varchar instagram
        varchar ciudad "sin dirección fija"
        tinyint activo
    }
    UBICACIONES {
        int id PK
        int food_truck_id FK
        varchar nombre "Parque de la 93"
        varchar referencia "costado norte"
        decimal latitud
        decimal longitud
        tinyint dia_semana "1 lunes a 7 domingo"
        time hora_inicio
        time hora_fin "si es menor, cierra al día siguiente"
        tinyint activa
    }
    USUARIOS {
        int id PK
        int food_truck_id FK "NULL solo para el rol plataforma"
        varchar correo UK
        varchar contrasena "password_hash"
        enum rol "plataforma, food_truck, cajero, produccion"
    }
    CATEGORIAS {
        int id PK
        int food_truck_id FK
        varchar nombre
        smallint orden
        tinyint activo
    }
    PRODUCTOS {
        int id PK
        int food_truck_id FK
        int categoria_id FK
        varchar nombre
        decimal precio "DECIMAL(10,2)"
        varchar foto
        tinyint disponible
    }
    ESTADOS_ORDEN {
        tinyint id PK
        varchar codigo UK
        varchar nombre
        tinyint orden
    }
    TURNOS_CAJA {
        int id PK
        int food_truck_id FK
        int usuario_id FK
        decimal base_inicial
        decimal total_ventas
        enum estado "abierto, cerrado"
        datetime abierto_en
        datetime cerrado_en
    }
    ORDENES {
        int id PK
        int food_truck_id FK
        int turno_id FK
        tinyint estado_id FK
        varchar numero "número de turno, único por truck"
        decimal total
        enum medio_pago "efectivo, tarjeta, transferencia"
        datetime estado_actualizado_en
    }
    ORDEN_ITEMS {
        int id PK
        int orden_id FK
        int producto_id FK "puede quedar NULL"
        varchar nombre_producto "copia histórica"
        decimal precio_unitario "copia histórica"
        smallint cantidad
        decimal subtotal
    }
```

Dos decisiones de modelo que vienen de trabajar con food trucks y no con locales:

- **`UBICACIONES` en vez de una dirección.** El truck para en sitios distintos según el
  día. Cada fila es una parada programada, y la carta pública responde con ellas la
  pregunta *¿dónde están hoy?*. Una jornada nocturna cruza la medianoche: cuando
  `hora_fin` es menor o igual que `hora_inicio`, la jornada cierra al día siguiente.
- **`ORDEN_ITEMS` copia el nombre y el precio** del producto en el momento de la venta.
  Si el truck cambia el precio después, las órdenes ya registradas no se alteran.

El detalle completo está en [`docs/basedatos.md`](docs/basedatos.md).

## Roles y acceso

```mermaid
flowchart LR
    P["👑 plataforma"] --> T1["Administra<br/>todos los food trucks"]
    N["🏪 food_truck"] --> T2["Panel de CARTA<br/>catálogo y paradas"]
    K["💵 cajero"] --> T3["Módulo CAJA<br/>turnos y órdenes"]
    R["📺 producción"] --> T4["Tablero interno<br/>del SVP"]
    PUB["👥 público"] --> T5["Carta por QR y<br/>pantalla de turnos"]

    style P fill:#f3e5f5,stroke:#6a1b9a
    style N fill:#e8f5e9,stroke:#2e7d32
    style K fill:#ede7f6,stroke:#4527a0
    style R fill:#fff8e1,stroke:#f9a825
    style PUB fill:#eceff1,stroke:#455a64
```

Cada ruta privada exige sesión iniciada y el rol adecuado. Todo formulario viaja con
token contra falsificación de peticiones, y todas las consultas usan sentencias
preparadas de PDO.

---

## Puesta en marcha

**Requisitos:** PHP 8.2 con las extensiones `pdo_mysql` y `gd`, MySQL 8 o MariaDB 10.6,
y Apache con `mod_rewrite` habilitado.

```bash
git clone git@github.com:Lain-Ramirez/Menu08.git
cd Menu08

# 1. Base de datos — en este orden
mysql -u root -p < basedatos/esquema.sql
mysql -u root -p < basedatos/datos_iniciales.sql

# 2. Configuración
cp configuracion/configuracion.ejemplo.php configuracion/configuracion.php
#    editar credenciales y url_base

# 3. Permisos de escritura
chmod -R 775 almacenamiento/subidas almacenamiento/bitacora
```

Apuntar el *DocumentRoot* del sitio a la carpeta `ADSO.menu08.com/`. Con el servidor
integrado de PHP, para pruebas rápidas — el último argumento es el front controller,
sin él las rutas no se resuelven:

```bash
php -S localhost:8000 -t ADSO.menu08.com ADSO.menu08.com/index.php
```

| Ruta | Acceso | Módulo |
|---|---|---|
| `/carta/festin-rodante` | pública | CARTA |
| `/turnos/festin-rodante` | pública, pantalla de la ventanilla | SVP |
| `/ingresar` | pública | — |
| `/panel` | rol `food_truck` o `plataforma` | CARTA |
| `/caja` | rol `cajero` | CAJA |
| `/svp` | rol `produccion` o `food_truck` | SVP |
| `/svp/ordenes` | rol `produccion` o `food_truck`, responde JSON | SVP |

Los usuarios de demostración y sus contraseñas están en
[`docs/basedatos.md`](docs/basedatos.md). **Cambiarlas antes de publicar el sitio.**

El contrato del servicio JSON que sondea el tablero está en
[`docs/api-svp.md`](docs/api-svp.md).

---

## Estado del desarrollo

El trabajo se sigue en los [issues](https://github.com/Lain-Ramirez/Menu08/issues)
del repositorio, agrupados por fase.

```mermaid
gantt
    title Fases del prototipo
    dateFormat YYYY-MM-DD
    axisFormat %d %b
    section Cerradas
    Análisis y diseño :done, dis, 2025-06-01, 2026-08-31
    section Codificación
    Backend y API :active, be, 2026-09-02, 2026-09-07
    Frontend :active, fe, 2026-09-02, 2026-09-07
    section Cierre
    Pruebas :pr, 2026-09-08, 2026-09-21
    Despliegue :de, 2026-09-22, 2026-10-05
    Documentación :do, 2026-10-06, 2026-10-19
```

| Fase | Milestone | Estado |
|---|---|---|
| Análisis | [Fase 1](https://github.com/Lain-Ramirez/Menu08/milestone/1) | Cerrada |
| Diseño (BD y UI) | [Fase 2](https://github.com/Lain-Ramirez/Menu08/milestone/2) | Cerrada |
| Backend y API | [Fase 3](https://github.com/Lain-Ramirez/Menu08/milestone/3) | En curso |
| Frontend | [Fase 4](https://github.com/Lain-Ramirez/Menu08/milestone/4) | En curso |
| Pruebas | [Fase 5](https://github.com/Lain-Ramirez/Menu08/milestone/5) | Pendiente |
| Despliegue | [Fase 6](https://github.com/Lain-Ramirez/Menu08/milestone/6) | Pendiente |
| Documentación | [Fase 7](https://github.com/Lain-Ramirez/Menu08/milestone/7) | Pendiente |

---

## Autoría

Proyecto formativo del Tecnólogo en Análisis y Desarrollo de Software del SENA, ficha 3235887.

- **Jovanny Medina Cifuentes** — [@JovannyCO](https://github.com/JovannyCO)
- **Lain Ramírez** — [@Lain-Ramirez](https://github.com/Lain-Ramirez)
