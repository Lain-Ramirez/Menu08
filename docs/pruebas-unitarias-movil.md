# Pruebas unitarias de la validación de los servicios móviles

Ejecutadas el 9 de septiembre de 2026 sobre PHP 8.5.4 en la máquina de desarrollo.
**49 comprobaciones, 0 fallos.**

Son de otra naturaleza que las demás de esta carpeta. Las de
[`pruebas-movil-servicios.md`](pruebas-movil-servicios.md) son **de integración**: hablan con
`https://adso.menu08.com` por HTTPS, necesitan el sitio desplegado, la base de datos y una sesión
abierta. Éstas son **unitarias**: prueban funciones puras leyendo el código fuente, sin red, sin
sesión y sin base de datos. Corren en un clon recién bajado, en cualquier máquina, en un segundo.

## Cómo se ejecutan

```bash
php menu08_app/pruebas/ejecutar.php
```

```
· UbicacionMedianochePrueba.php
· ValidadorCoordenadaPrueba.php
· ValidadorHoraDiaPrueba.php

49 comprobaciones, 0 fallos
```

Código de salida **0**. Sin marco de pruebas y sin gestor de dependencias: el ejecutor son tres
funciones —`afirmar()`, `afirmarIgual()` y `resumen()`— más la misma autocarga por convención del
front controller, recortada a lo imprescindible. Se lee entero en un minuto.

### Que corre en frío no es una promesa, está comprobado

La máquina donde salió esta ejecución **no puede conectarse a MySQL desde PHP** y **no tiene la
configuración local**:

```
$ php -m | grep -i -e pdo -e mysql
PDO                          <- solo el nucleo; sin controlador de MySQL ni mysqli

$ ls menu08_app/configuracion/configuracion.php
(no existe)
```

Y aun así las 49 pasan. Es la propiedad que hace útil esta batería: cualquiera la ejecuta al clonar,
antes de montar nada.

## Qué se prueba

| Archivo | Qué fija | Comprobaciones |
|---|---|---|
| `casos/ValidadorCoordenadaPrueba.php` | `Validador::coordenada()`, la puerta por la que entra el punto del GPS | 19 |
| `casos/ValidadorHoraDiaPrueba.php` | `Validador::hora()` y `Validador::diaSemana()` | 22 |
| `casos/UbicacionMedianochePrueba.php` | `Ubicacion::cruzaMedianoche()` | 8 |

Tres cosas que merecen explicación, porque no son casos de relleno:

**El campo vacío devuelve `null` y NO deja mensaje.** Es el detalle del que depende
`POST /movil/ubicacion`. La coordenada es opcional en el formulario del panel, así que el vacío se
guarda como nulo sin error; por eso el servicio móvil, donde las dos coordenadas **sí** son
obligatorias, tiene que marcar ese caso a mano con `Validador::error()`. Si alguien «arreglara» el
validador haciendo que el vacío deje mensaje, el servicio móvil seguiría funcionando y el panel
empezaría a rechazar paradas sin coordenadas. Esta prueba es lo que impide que ese detalle se
pierda.

**Los dos valores fuera de rango pasan el formato.** `90.0000001` y `180.0000001` tienen siete
decimales justos, así que superan la expresión regular y fallan en la comprobación siguiente. Es
exactamente la rama que se quiere ejercitar; con un valor de ocho decimales se habría probado la
anterior sin querer.

**`Ubicacion::cruzaMedianoche()` no tiene hoy ningún llamador.** Las vistas que lo usarán son del
issue de Frontend de la agenda, todavía abierto. Es un método puro que sostiene una regla —la misma
`hora_fin <= hora_inicio` que aplican en SQL las dos ramas nocturnas de `Ubicacion::vigente()` y la
copia bloqueante de `Ubicacion::asentarPunto()`—, y ahora mismo **esta prueba es lo único que la
sostiene**: si alguien invirtiera la comparación, nada más se quejaría. Se comprueban los tres casos
y los dos bordes exactos: `18:00:00 → 18:00:01` es jornada normal, `18:00:00 → 17:59:59` ya cierra al
día siguiente.

## El ejecutor delata los fallos

No basta con que salga verde: hay que ver que sabe ponerse rojo. Alterado a propósito un valor
esperado —`true` por `false` en el primer caso de la medianoche—, la misma orden responde:

```
· UbicacionMedianochePrueba.php
· ValidadorCoordenadaPrueba.php
· ValidadorHoraDiaPrueba.php

  FALLA  de 18:00 a 01:00 cruza la medianoche
           esperaba false
           obtuvo   true

49 comprobaciones, 1 fallos
```

```bash
$ echo $?
1
```

Los dos valores se imprimen con `var_export()`, y no es un adorno: sin él, `null`, la cadena vacía y
el entero `7` frente a la cadena `'7'` saldrían iguales por pantalla y el fallo no diría nada. Justo
esas distinciones son las que fija media batería.

El código de salida 1 es lo que permite usar el ejecutor como comprobación de un solo tirón antes de
empaquetar. La alteración se deshizo al terminar; la batería vuelve a dar 49 y 0.

## De paso: la sintaxis, por fin comprobada

`MovilControlador.php`, `Ubicacion.php` y `rutas.php` se escribieron sin PHP en la máquina, así que
nunca habían pasado por el analizador. Que el servicio respondiera en producción demostraba que
compilan, pero era evidencia indirecta:

```
$ php -l ... (los siete archivos)
No syntax errors detected in menu08_app/aplicacion/controladores/MovilControlador.php
No syntax errors detected in menu08_app/aplicacion/modelos/Ubicacion.php
No syntax errors detected in menu08_app/configuracion/rutas.php
No syntax errors detected in menu08_app/pruebas/ejecutar.php
No syntax errors detected in menu08_app/pruebas/casos/UbicacionMedianochePrueba.php
No syntax errors detected in menu08_app/pruebas/casos/ValidadorCoordenadaPrueba.php
No syntax errors detected in menu08_app/pruebas/casos/ValidadorHoraDiaPrueba.php
```

## Lo que estas pruebas no cubren

Lo que sigue **no es prueba unitaria y no debe intentar serlo**. Depende del estado de MySQL, de la
sesión o de una petición HTTP, así que su sitio son las pruebas de integración:

- **`Ubicacion::asentarPunto()`**, el corazón del reporte del GPS. Su transacción, el
  `SELECT … FOR UPDATE` sobre la fila del food truck y la elección entre actualizar la parada
  vigente o registrar una nueva dependen enteramente de lo que tenga la tabla en ese instante.
  Queda cubierto donde ya estaba: la comprobación contra el sitio publicado en
  [`pruebas-movil-servicios.md`](pruebas-movil-servicios.md), incluidos los dos reportes
  simultáneos, y las pruebas en dispositivo del módulo móvil.
- **`MovilControlador`** entero: el ingreso, la sesión, el token contra falsificación de peticiones
  y la forma de la respuesta HTTP. Nada de eso es una función pura.
- **Las tres ramas de la consulta de parada vigente**, que viven en SQL y solo se pueden ejercitar
  contra una base con datos y a la hora adecuada. Las dos nocturnas siguen sin probarse.
- **El resto del núcleo.** Esta batería cubre `coordenada()`, `hora()` y `diaSemana()` de
  `Validador`, que son las tres reglas de las que dependen los servicios móviles. Las demás
  —`texto()`, `precio()`, `entero()`— no se tocan aquí: son de otros módulos y les corresponde su
  propio issue.

Este issue **no añade ninguna ruta**, así que [`POSTMAN.md`](../POSTMAN.md) y la colección no
cambian.
