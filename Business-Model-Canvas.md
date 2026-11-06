# Business Model Canvas — Menu08

Plataforma de carta digital, venta y producción para food trucks · [adso.menu08.com](https://adso.menu08.com)
Bogotá, Colombia · 6 de noviembre de 2026 · Beneficiario del Fondo Emprender SENA: Jovanny Medina Cifuentes, línea CREAR, modalidad individual

<table>
<tr>
<td rowspan="2" valign="top" width="20%">

### 🤝 Socios clave

- **SENA – Fondo Emprender**: capital semilla de hasta **42 SMMLV ($73.538.010)** por postulación individual con al menos un empleo, a nombre de Jovanny Medina Cifuentes como único socio, porque en esta modalidad solo se aceptan sociedades de un solo socio; acompañamiento de los Centros de Desarrollo Empresarial e interventoría durante la puesta en marcha<sup>1</sup>
- **Proveedor tecnológico habilitado por la DIAN** para el documento equivalente electrónico POS, obligatorio desde que el tiquete POS en papel perdió validez el 1 de julio de 2024<sup>2</sup>
- **Bancos y billeteras conectados a Bre-B** (Banco de la República): el pago por transferencia que registra CAJA se cobra con llave o QR<sup>3</sup>
- **Cámaras de Comercio y alcaldías** (en Bogotá, la SDDE y el DADEP), que formalizan al truck y autorizan las zonas de venta de comida en vehículos<sup>4</sup>
- **Proveedor de hosting LiteSpeed y registrador del dominio menu08.com**

</td>
<td valign="top" width="20%">

### ⚙️ Actividades clave

- Desarrollo y mantenimiento de los tres módulos: **CARTA** (carta por QR y agenda de paradas), **CAJA** (turno, orden, cobro y número de turno) y **SVP** (tablero de producción y pantalla pública de turnos)
- Mantenimiento de la app Android que reporta por GPS dónde está parado el truck
- Alta de cada truck: carga de la carta, la agenda de paradas, los usuarios por rol y el QR impreso para la ventanilla
- Seguridad y calidad: sentencias preparadas, tokens CSRF, roles, HTTPS obligatorio y pruebas automatizadas (unitarias, Postman y E2E) antes de cada despliegue
- Venta directa en los puntos donde paran los food trucks

</td>
<td rowspan="2" valign="top" width="20%">

### 🎁 Propuesta de valor

**Un food truck no tiene dirección fija ni mesas: tiene una ventanilla, una fila y un punto que cambia cada día. Menu08 resuelve la operación completa de esa ventanilla.**

- **¿Dónde están hoy?** La carta pública muestra la parada vigente según el día y la hora, incluidas las jornadas que cruzan la medianoche. Quien atiende la corrige desde la calle con un botón del celular
- **Carta en el celular del cliente:** se abre al leer el QR de la ventanilla, sin instalar nada. El 97,5 % de quienes usan internet en Colombia se conectan desde el celular<sup>5</sup>
- **Fila ordenada:** CAJA entrega un número de turno, producción lo avanza y la pantalla pública avisa cuando el pedido está listo
- **Cocina bajo control:** el tablero resalta las órdenes que llevan demasiado tiempo sin avanzar
- **Cierre de caja confiable:** turnos con base inicial y total de ventas por efectivo, tarjeta o transferencia. Cada orden guarda el precio del momento de la venta
- **Cero comisión por venta:** el truck paga una suscripción fija, no un porcentaje de lo que vende
- **Liviana:** funciona en cualquier navegador, y la app Android es compatible desde Android 7.0 sin depender de los servicios de Google

</td>
<td valign="top" width="20%">

### 💬 Relaciones con clientes

- **Puesta en marcha asistida y presencial** en el truck: carga de la carta, capacitación por rol (administrador, cajero y producción) y QR instalado en la ventanilla
- **Soporte directo por WhatsApp** en el horario en que operan los trucks, incluidas las jornadas nocturnas
- **Autoservicio** desde el panel: el dueño cambia productos, precios, disponibilidad y paradas sin esperar a nadie
- **Comunidad de trucks usuarios** con retroalimentación que se convierte en nuevas funciones del producto

</td>
<td rowspan="2" valign="top" width="20%">

### 👥 Segmentos de clientes

**Cliente que paga:**
- **Food trucks formalizados** (CIIU 5613 expendio de comidas preparadas en cafeterías y 5619 otros tipos de expendio de comidas preparadas n.c.p.) que rotan entre parques, eventos y zonas autorizadas, empezando por Bogotá
- **Ventas de comida de ventanilla y en ferias** con fila y producción a la vista

**Usuario final que no paga:**
- **Comensales en la fila**, que consultan la carta y siguen su turno desde la pantalla

**Tamaño de referencia:** el DANE estimó 5.297.252 micronegocios en 2024; el **8,3 % (≈ 439.672)** son de alojamiento y servicios de comida y generan el **9,4 % de $191,2 billones** en ventas (≈ $17,97 billones)<sup>6</sup>. En el IV trimestre de 2025 este sector sumó 1,9 p.p. al crecimiento del número de micronegocios<sup>7</sup>

</td>
</tr>
<tr>
<td valign="top">

### 🧱 Recursos clave

- **Software propio y funcionando** en producción: PHP 8.3 con MVC propio y sin dependencias de terceros, MySQL 8 con nueve tablas y una app Android en Kotlin sin bibliotecas de terceros
- **Batería de pruebas:** 62 comprobaciones unitarias, colección Postman de 22 solicitudes y 40 aserciones, y un recorrido E2E de la venta completa
- **Equipo:** Jovanny Medina Cifuentes, beneficiario del Fondo Emprender y único socio, y Lain Sthid Ramirez Rueda, en el empleo directo de desarrollo y soporte. Ambos son Tecnólogos en Análisis y Desarrollo de Software (ADSO) del SENA, ficha 3235887
- **Marca y dominio** menu08.com
- **Capital semilla** del Fondo Emprender

</td>
<td valign="top">

### 📣 Canales

- **La propia ventanilla:** cada QR y cada pantalla de turnos exhiben Menu08 ante todos los comensales de la fila
- **Venta directa** en parques gastronómicos, ferias y eventos donde se concentran los trucks
- **Demostración en vivo** con la carta pública y la pantalla de turnos en adso.menu08.com
- **Instagram y WhatsApp**, los mismos canales que los trucks ya enlazan desde su carta
- **Ecosistema SENA:** Centros de Desarrollo Empresarial y redes de egresados emprendedores

</td>
</tr>
<tr>
<td colspan="3" valign="top">

### 💸 Estructura de costos

**Rubros ejecutados con el Fondo Emprender**, todos financiables en la línea CREAR<sup>1</sup>:
- **Nómina del empleo directo** que exige la convocatoria, ocupado por Lain Sthid Ramirez Rueda, con remuneración mínima de 1 SMMLV ($1.750.905) más auxilio de transporte ($249.095), de acuerdo con el Decreto 1469 de 2025<sup>8</sup>
- **Equipos y software:** equipos de desarrollo, dispositivos de prueba Android y pantalla de demostración del SVP
- **Permisos, licencias y registros:** matrícula mercantil y registro de la marca Menu08 (propiedad intelectual, máximo 20 % de los recursos)

**Costos recurrentes de operación:**
- Hosting, dominio y certificados
- Transporte a los puntos de venta, soporte y material impreso (QR)
- Integración con el proveedor tecnológico de facturación electrónica

**Estructura liviana:** sin licencias de terceros que pagar por cada truck, porque el núcleo no usa marcos ni bibliotecas externas

</td>
<td colspan="2" valign="top">

### 💰 Fuentes de ingresos

- **Suscripción mensual por truck** con tarifa fija y sin comisión por venta:
  - **Plan CARTA:** carta por QR, agenda de paradas y app de ubicación
  - **Plan Operación:** CARTA + CAJA + SVP con usuarios por rol
- **Cargo único de puesta en marcha:** carga de la carta, capacitación y QR impreso
- **Pago anual con descuento** para mejorar el flujo de caja
- **Kit de ventanilla opcional:** QR resistente a la intemperie y montaje de la pantalla de turnos

**Recaudo:** transferencia inmediata Bre-B. En abril de 2026, más de 90.000 comercios ya tenían una llave registrada<sup>3</sup>

**Indicadores comprometidos para la condonación:** el empleo directo sostenido, ventas según la proyección del plan de negocio y ejecución completa de los rubros aprobados<sup>1</sup>

</td>
</tr>
</table>

<sub>
<b>Fuentes</b> ·
<sup>1</sup> SENA, <a href="https://historico.sena.edu.co/es-co/trabajo/FONDOEMPRENDER1/2026/MARZO/Convocatoria%20No%20153_Multisectorial.pdf">Convocatoria Nacional No. 153 «Multisectorial» del Fondo Emprender</a> (2026): modalidad individual de un solo socio (numeral 1.5.1, nota 3), topes del Acuerdo 007 de 2025, rubros financiables (Acuerdo 0010 de 2019, arts. 11 y 12), puntaje mínimo de viabilidad de 65 sobre 100 y causales de devolución ·
<sup>2</sup> DIAN, <a href="https://normograma.dian.gov.co/dian/compilacion/docs/oficio_dian_6259_2024.htm">Oficio 6259 de 2024</a> y Resolución 000165 de 2023 ·
<sup>3</sup> Banco de la República, <a href="https://www.banrep.gov.co/es/bre-b/indicadores">Indicadores Bre-B</a> ·
<sup>4</sup> Alcaldía de Bogotá, <a href="https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=175158&dt=S">Resolución 391 de 2025 de la SDDE</a> ·
<sup>5</sup> DANE, <a href="https://www.dane.gov.co/files/operaciones/ENTIC/bol-ENTICHogares-2024.pdf">ENTIC Hogares 2024</a> ·
<sup>6</sup> DANE, <a href="https://www.dane.gov.co/files/operaciones/EMICRON/bol-EMICRON-2024.pdf">EMICRON 2024</a> ·
<sup>7</sup> DANE, <a href="https://www.dane.gov.co/files/operaciones/EMICRON/bol-EMICRON-IVtrim2025.pdf">EMICRON IV trimestre de 2025</a> ·
<sup>8</sup> Decretos 1469 y 1470 de 2025, salario mínimo y auxilio de transporte de 2026
</sub>
