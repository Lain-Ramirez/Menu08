# Menu08

Prototipo funcional de la plataforma **Menu08** para negocios de alimentos:
restaurantes, cafeterías, comidas rápidas y food trucks.

El negocio publica su carta, vende desde caja y la producción ve las órdenes en pantalla.
Tres módulos que comparten un mismo catálogo y una misma base de datos.

| | Módulo | Quién lo usa | Qué resuelve |
|---|---|---|---|
| 🍽️ | **CARTA** | El cliente y el administrador del negocio | Carta digital pública que se abre leyendo un código QR, y el panel donde se administran categorías y productos |
| 💵 | **CAJA** | El cajero | Apertura y cierre de turno, armado de la orden, total, medio de pago y comprobante |
| 📺 | **SVP** | El área de producción | **Sistema de Visualización de Producción**: tablero donde llegan las órdenes de CAJA y avanzan de estado hasta quedar listas |

- Prototipo publicado en **https://adso.menu08.com**
- Proyecto formativo iniciado en **junio de 2025** · Tecnólogo en Análisis y Desarrollo de Software (ADSO), SENA · Ficha 3235887

---

## Cómo encajan los tres módulos

CARTA es la pieza que se construye primero porque es el catálogo del que dependen las otras dos:
sin productos no hay nada que vender en CAJA ni nada que mostrar en el SVP.

```mermaid
flowchart LR
    subgraph CARTA["🍽️ CARTA"]
        A1["Panel del negocio<br/>categorías y productos"]
        A2["Carta pública<br/>ruta con el slug"]
        A3["Código QR<br/>descargable"]
    end
    subgraph CAJA["💵 CAJA"]
        B1["Apertura de turno"]
        B2["Armado de la orden"]
        B3["Cobro y comprobante"]
    end
    subgraph SVP["📺 SVP"]
        C1["Tablero de órdenes"]
        C2["Avance de estado"]
    end

    A1 -->|"define el catálogo"| A2
    A1 -->|"provee los productos"| B2
    A2 --- A3
    A3 -.->|"el cliente escanea"| A2
    B1 --> B2 --> B3
    B3 -->|"registra la orden"| C1
    C1 --> C2
    C2 -.->|"orden lista"| B3

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

    Cliente->>CARTA: Escanea el código QR de la mesa
    CARTA-->>Cliente: Muestra la carta del negocio por su slug
    Cliente->>Cajero: Pide en el mostrador
    Cajero->>CAJA: Arma la orden con los productos del catálogo
    CAJA->>BD: Guarda la orden, sus ítems y el total
    Note over CAJA,BD: La orden nace en estado "pendiente"
    loop Sondeo cada pocos segundos
        SVP->>BD: Consulta las órdenes en curso
        BD-->>SVP: Devuelve el tablero en JSON
    end
    SVP-->>Produccion: Muestra la orden en el tablero
    Produccion->>SVP: Marca "en preparación" y luego "lista"
    SVP->>BD: Actualiza el estado de la orden
    Cajero->>CAJA: Entrega el pedido y cierra el turno
```

## Ciclo de vida de la orden

```mermaid
stateDiagram-v2
    [*] --> pendiente : CAJA registra la orden
    pendiente --> en_preparacion : Producción la toma
    en_preparacion --> lista : Producción termina
    lista --> entregada : CAJA entrega al cliente
    entregada --> [*]

    note right of pendiente
        El SVP resalta la orden
        cuando lleva demasiado
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

```
publico/                    única carpeta expuesta por el servidor web
  index.php                 front controller
  .htaccess                 reescritura hacia el front controller
  recursos/                 css, js e imágenes estáticas
aplicacion/
  nucleo/                   Enrutador, ConexionBD, Controlador, Vista,
                            Sesion, Csrf, Validador, GestorImagenes, GeneradorQr
  controladores/            XxxControlador.php
  modelos/                  Xxx.php
  vistas/
    plantillas/             cabecera, pie y vista de error compartidas
    auth/  panel/  carta/  caja/  svp/
configuracion/
  configuracion.ejemplo.php plantilla versionada
  configuracion.php         credenciales reales — NUNCA se versiona
basedatos/
  esquema.sql               las ocho tablas
  datos_iniciales.sql       estados, negocio y catálogo de demostración
almacenamiento/
  subidas/                  logos, fotos de producto y códigos QR
  bitacora/                 registro de errores
docs/                       documentación del proyecto
```

---

## Modelo de datos

Ocho tablas en MySQL 8, motor InnoDB, cotejamiento `utf8mb4_unicode_ci`.
Todos los montos son `DECIMAL(10,2)`.

```mermaid
erDiagram
    NEGOCIOS     ||--o{ USUARIOS    : "da acceso a"
    NEGOCIOS     ||--o{ CATEGORIAS  : "organiza su carta en"
    NEGOCIOS     ||--o{ PRODUCTOS   : "ofrece"
    NEGOCIOS     ||--o{ TURNOS_CAJA : "opera"
    NEGOCIOS     ||--o{ ORDENES     : "vende"
    CATEGORIAS   ||--o{ PRODUCTOS   : "agrupa"
    USUARIOS     ||--o{ TURNOS_CAJA : "abre"
    TURNOS_CAJA  ||--o{ ORDENES     : "acumula"
    ESTADOS_ORDEN ||--o{ ORDENES    : "clasifica"
    ORDENES      ||--o{ ORDEN_ITEMS : "detalla en"
    PRODUCTOS    |o--o{ ORDEN_ITEMS : "se vende como"

    NEGOCIOS {
        int id PK
        varchar nombre
        varchar slug UK "identifica la carta pública"
        varchar logo
        tinyint activo
    }
    USUARIOS {
        int id PK
        int negocio_id FK "NULL solo para el rol plataforma"
        varchar correo UK
        varchar contrasena "password_hash"
        enum rol "plataforma, negocio, cajero, produccion"
    }
    CATEGORIAS {
        int id PK
        int negocio_id FK
        varchar nombre
        smallint orden
        tinyint activo
    }
    PRODUCTOS {
        int id PK
        int negocio_id FK
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
        int negocio_id FK
        int usuario_id FK
        decimal base_inicial
        decimal total_ventas
        enum estado "abierto, cerrado"
        datetime abierto_en
        datetime cerrado_en
    }
    ORDENES {
        int id PK
        int negocio_id FK
        int turno_id FK
        tinyint estado_id FK
        varchar numero "único por negocio"
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

`ORDEN_ITEMS` copia el nombre y el precio del producto en el momento de la venta:
si el negocio cambia el precio después, las órdenes ya registradas no se alteran.

El detalle completo está en [`docs/basedatos.md`](docs/basedatos.md).

## Roles y acceso

```mermaid
flowchart LR
    P["👑 plataforma"] --> T1["Administra<br/>todos los negocios"]
    N["🏪 negocio"] --> T2["Panel de CARTA<br/>categorías y productos"]
    K["💵 cajero"] --> T3["Módulo CAJA<br/>turnos y órdenes"]
    R["📺 producción"] --> T4["Tablero del SVP<br/>avance de estados"]

    style P fill:#f3e5f5,stroke:#6a1b9a
    style N fill:#e8f5e9,stroke:#2e7d32
    style K fill:#ede7f6,stroke:#4527a0
    style R fill:#fff8e1,stroke:#f9a825
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

Apuntar el *DocumentRoot* del sitio a la carpeta `publico/`. Con el servidor
integrado de PHP, para pruebas rápidas:

```bash
php -S localhost:8000 -t publico
```

| Ruta | Acceso | Módulo |
|---|---|---|
| `/carta/sabor-criollo` | pública | CARTA |
| `/ingresar` | pública | — |
| `/panel` | rol `negocio` o `plataforma` | CARTA |
| `/caja` | rol `cajero` | CAJA |
| `/svp` | rol `produccion` | SVP |

Los usuarios de demostración y sus contraseñas están en
[`docs/basedatos.md`](docs/basedatos.md). **Cambiarlas antes de publicar el sitio.**

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
    Análisis y diseño            :done, dis, 2025-06-01, 2026-08-31
    section Codificación
    Backend y API (CARTA→CAJA→SVP) :active, be, 2026-09-02, 2026-09-07
    Frontend                       :active, fe, 2026-09-02, 2026-09-07
    section Cierre
    Pruebas                      :pr, 2026-09-08, 2026-09-21
    Despliegue                   :de, 2026-09-22, 2026-10-05
    Documentación                :do, 2026-10-06, 2026-10-19
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
