# Pruebas de Menu08

Esta carpeta concentra la automatización y la evidencia de GA9 AA3 EV01. Las pruebas unitarias puras permanecen en `menu08_app/pruebas/`; la colección principal y la muestra del video están en `postman/`.

`evidencias/inicial/` conserva el ciclo previo a las correcciones. `evidencias/actual/` contiene las reejecuciones del código corregido. Cada registro guarda la fecha UTC, el commit, las comparaciones y las capturas. Los estados físicos o de despliegue pendientes se conservan como tales.

## Preparación local

Requisitos del entorno usado: PHP 8.3 con `pdo_mysql`, cliente `mysql`, Python 3.12, Git y Docker. Node/npm son opcionales si se utiliza el instalador auxiliar. Las dependencias están fijadas en `requirements.txt` y `package.json`.

Desde la raíz de Menu08:

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install -r tests/requirements.txt
export PLAYWRIGHT_BROWSERS_PATH=/tmp/menu08-qa-runtime/browsers
python -m playwright install chromium firefox
python tests/soporte/configurar_local.py
```

La configuración auxiliar apunta exclusivamente a PHP local `127.0.0.1:8088` y al MySQL aislado `127.0.0.1:13306`, base `menu08`. La contraseña del contenedor siguiente es de demostración local.

```bash
docker run --name menu08-ga9-mysql \
  -e MYSQL_ROOT_PASSWORD=Menu08LocalQA2026 \
  -p 127.0.0.1:13306:3306 -d mysql:8.4
```

Si ya existe, usar `docker start menu08-ga9-mysql`. Esperar a que MySQL acepte conexiones. En otra terminal, desde la raíz del repositorio:

```bash
php -d session.save_path="$PWD/tests/soporte/sesiones" \
  -S 127.0.0.1:8088 -t ADSO.menu08.com ADSO.menu08.com/index.php
```

Inicializar la base aislada y las herramientas:

```bash
python tests/soporte/ejecutar_http.py --init
npm install --prefix tests
python tests/infra/preparar_apache.py
```

Sin Node/npm instalados se puede usar `python tests/soporte/instalar_newman.py`, que utiliza el Node incluido con Playwright e instala las dependencias auxiliares en el directorio temporal. Apache usa los puertos 8090/8443 y un certificado de prueba generado localmente. No publica la aplicación: ejecuta las reglas `.htaccess` sobre copias de los recursos estáticos. La verificación TLS confía explícitamente en ese certificado; no desactiva su validación.

**Los ejecutores restauran el banco y `--init` recrea toda la base `menu08` del contenedor local.** No reutilizar una base personal ni ejecutar dos grupos simultáneamente. El ejecutor rechaza una URL de aplicación que no sea local. Las únicas consultas al hosting están separadas en el grupo `publicacion` y son de lectura.

## Ejecución

```bash
php menu08_app/pruebas/ejecutar.php
python tests/ejecutar.py
```

La batería PHP incorpora las 49 comprobaciones anteriores y 13 de redirección HTTPS: 62 en total. El ejecutor Python corre los grupos de forma secuencial y devuelve error si encuentra casos fallidos o errores de ejecución; los seguimientos físicos no se convierten en aprobaciones.

Para ejecutar una muestra:

```bash
python tests/ejecutar.py --grupo postman
python tests/ejecutar.py --grupo flujo
python tests/ejecutar.py --grupo https
```

| Grupo | Qué comprueba |
|---|---|
| `http` | CARTA y CAJA: altas, validaciones, imágenes, horarios, ventas, centavos, cierre y rollback. |
| `servicios` | SVP, transiciones, sondeo por servicio, permisos, CSRF, aislamiento, ubicación móvil y consultas SQL. |
| `e2e` | Interacción y presentación en Chromium/Firefox, impresión, sondeo, pérdida de red y recuperación. |
| `postman` | Carpetas 1, 2 y 8 de la colección principal, más la muestra del video. |
| `https` | Las dos reglas `.htaccess` en Apache y el front controller real de PHP, antes de abrir sesión. |
| `flujo` | Tres sesiones independientes: abrir caja, vender por interfaz, producir, entregar y cerrar. |
| `qr` | Decodificación del PNG descargado durante el grupo HTTP. |
| `publicacion` | Lectura del hosting; distingue la corrección local de su despliegue. |

Los grupos independientes requieren sus precondiciones. `qr` necesita primero el PNG del grupo `http`; la ejecución completa respeta ese orden.

## Evidencias y documentos

```bash
python tests/soporte/consolidar.py
python tests/soporte/renderizar_evidencias.py
```

Los informes HTML representan los registros de ejecución. Las capturas con `chromium` o `firefox` en el nombre son imágenes originales de la aplicación; las terminadas en `-informe.png` son capturas de los informes y se identifican así.

La carpeta documental de la actividad contiene únicamente los Excel y el guion. Para reconstruir el libro se usa `MENU08_ENTREGA_DIR` con la ubicación de la plantilla y `MENU08_EVIDENCE_REF` con el commit de las evidencias; el generador vincula los registros de GitHub y mantiene las capturas incrustadas.

## Correcciones de este ciclo

- Los agotados se excluyen de la carta; permanecen en el panel y en las órdenes históricas.
- La colección descubre IDs desde el catálogo y cierra sesión antes de cambiar de rol.
- La comprobación de recursos distingue su origen; permite URL absolutas del mismo servidor.
- HTTPS conserva ruta y consulta. El destino del front controller sale de la configuración, no de `Host` ni de cabeceras reenviadas. Las instalaciones locales HTTP continúan funcionando.

La redirección del hosting solo puede darse por comprobada después de desplegar el PR. La lectura a tres metros, el PDF de Firefox gráfico y la decisión sobre cerrar con órdenes pendientes también conservan su seguimiento.
