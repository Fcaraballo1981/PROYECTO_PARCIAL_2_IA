# El Gabinete del Relojero

Sitio adaptable para compra y venta de relojes, con catálogo, solicitudes de compra, asistente Gemini y un módulo real para recibir relojes ofrecidos por clientes.

## Puesta en marcha local

Requiere PHP 8.1 o posterior con cURL, JSON, mbstring, session, fileinfo y PDO SQLite. En Vercel se utiliza además PDO PostgreSQL mediante el runtime configurado en `vercel.json`.

```powershell
.\start-local.ps1
```

Antes del primer inicio, abrí `.env` y pegá allí las claves que quieras utilizar. Luego abrí `http://127.0.0.1:8000`. El script carga automáticamente la configuración de `.env`, habilita SQLite sólo para este proceso y configura los límites de carga. Sin claves, las solicitudes y citas se guardan igualmente y el sitio informa qué análisis no pudo ejecutar.

Si PowerShell bloquea scripts locales:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-local.ps1
```

No abras los HTML con doble clic: los formularios necesitan el backend PHP.

## Variables de entorno

La configuración local se guarda en `.env`, en la raíz del proyecto. El archivo está excluido mediante `.gitignore` y bloqueado para acceso web. Usá `.env.example` como plantilla y no compartas el `.env` real.

- `GEMINI_API_KEY`: clave creada en [Google AI Studio](https://aistudio.google.com/app/apikey). Se usa únicamente en PHP.
- `GEMINI_MODEL`: opcional; por defecto `gemini-3.5-flash-lite`.
- `DATABASE_URL` o `POSTGRES_URL`: conexión PostgreSQL para producción. Si ambas faltan, el entorno local usa SQLite.
- `ADMIN_USERNAME` y `ADMIN_PASSWORD`: habilitan la API administrativa; la contraseña debe tener al menos 12 caracteres. El repositorio no incluye actualmente una interfaz web para administrarla.

No guardes secretos en JavaScript, archivos públicos ni el repositorio.

## Solicitudes de compra

- Cada reloj del catálogo permite iniciar una solicitud de compra.
- El servidor valida el producto y los datos de contacto, y devuelve un código `COMPRA-…`.
- Las solicitudes quedan guardadas en `purchase_inquiries`, dentro de SQLite local o PostgreSQL en producción.
- La solicitud no procesa pagos ni confirma por sí sola la disponibilidad definitiva.

## Créditos fotográficos del catálogo

Las fotografías se descargaron de Wikimedia Commons y se alojan en `assets/`. Se muestran con atribución, enlace a su página de licencia y encuadre adaptado mediante CSS:

- Rolex Submariner: EMore98, CC BY-SA 4.0.
- Omega Seamaster: Rama, CC BY-SA 2.0 FR.
- Cartier Santos: Pittigrilli, CC BY-SA 4.0.
- TAG Heuer Monaco: EMore98, CC BY-SA 4.0.
- Seiko SKX007: Ashley Pomeroy, CC BY-SA 4.0.
- Seiko 5 Sports GMT SSK003: Padgriffin, CC BY-SA 4.0.
- Citizen Aqualand Promaster JP1010: Pittigrilli, CC BY-SA 4.0.
- Citizen Eco-Drive CB0021: Ocrho, CC0.
- Orient Bambino ER24005W: MIKI Yoshihito, CC BY 2.0.
- Casio F-91W: Ashley Pomeroy, CC BY-SA 3.0.
- Casio G-Shock DW-5600E: Pittigrilli, dominio público.

## Módulo «Vendé tu reloj»

- Página pública en `vende-tu-reloj.html`, vinculada desde el menú principal.
- Flujo de cuatro pasos con regreso sin pérdida de datos.
- Validación cliente/servidor y consentimiento de tratamiento de datos.
- Entre 1 y 8 imágenes JPG o PNG de hasta 25 MB de origen; el navegador las redimensiona y comprime automáticamente para mantener el envío por debajo del límite de Vercel Functions.
- Análisis multimodal con Gemini cuando hay credenciales.
- La evaluación remota describe únicamente detalles visibles e inconsistencias posibles; no estima precios ni certifica autenticidad.
- Agenda en `America/Montevideo`, configurada de lunes a viernes de 09:00 a 17:00, con bloqueo transaccional y códigos de reserva.

No se encontró un servicio de correo en el proyecto original, por lo que no se simula el envío de confirmaciones. La reserva queda confirmada en pantalla sólo después de persistirse correctamente.

## Base de datos y migraciones

El proyecto original usaba `localStorage` y no incluía base de datos. El módulo actual admite dos motores:

- Desarrollo local: SQLite mediante `migrations/001_sell_module.sql`; genera `var/gabinete.sqlite`.
- Vercel: PostgreSQL mediante `migrations/001_sell_module_postgres.sql` y `DATABASE_URL` o `POSTGRES_URL`.
- La migración se ejecuta automáticamente y es idempotente al acceder por primera vez a una API del módulo.
- Las fotografías se almacenan como BLOB y se entregan sólo mediante `api/image.php`, con autorización o token privado de solicitud.

Para aplicar la migración manualmente desde esta instalación de PHP:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -r "require 'api/_bootstrap.php'; database(); echo 'Migración aplicada';"
```

`router.php` bloquea durante el desarrollo el acceso directo a la base, migraciones, certificados y archivos internos. Las reglas `.htaccess` incluidas aplican la protección equivalente en Apache.

## Seguridad implementada

- Comprobación de mismo origen y límites por sesión.
- Consultas preparadas, validación de extensión real, MIME y contenido de imágenes.
- Clave de Gemini sólo en entorno de servidor.
- La API administrativa queda deshabilitada si no se configuran `ADMIN_USERNAME` y una `ADMIN_PASSWORD` de al menos 12 caracteres; no existen credenciales predeterminadas ni una interfaz administrativa incluida.
- Bloqueo transaccional adaptado a cada motor, restricciones únicas y nueva comprobación de disponibilidad para evitar doble reserva.
- Historial de cambios y consentimiento fechado.

El catálogo público se define en `app.js`; las solicitudes usan SQLite en desarrollo local y PostgreSQL cuando se configura `DATABASE_URL` o `POSTGRES_URL` (requerido en Vercel).

## Despliegue en Vercel

El repositorio incluye `vercel.json` para servir los archivos estáticos y ejecutar `api/*.php` con el runtime comunitario de PHP. En Vercel no configures `start-local.ps1` como **Build Command**: ese script es únicamente para Windows local y la configuración del repositorio omite el paso de build.

Para disponer de almacenamiento persistente:

1. En el proyecto de Vercel, abrí **Storage / Marketplace** y conectá una base PostgreSQL de Neon.
2. Confirmá que la integración haya creado `DATABASE_URL` o `POSTGRES_URL` para Production, Preview y Development según corresponda.
3. En **Settings → Environment Variables**, agregá `GEMINI_API_KEY` y, si usarás la API administrativa, `ADMIN_USERNAME` y `ADMIN_PASSWORD` (mínimo 12 caracteres).
4. Volvé a desplegar el último commit. Las tablas de PostgreSQL se crean automáticamente en el primer acceso a una API.

Sin una URL PostgreSQL, el proyecto usa SQLite localmente. En Vercel, el chat puede funcionar con una sesión temporal en `/tmp`, pero compras, valoraciones, imágenes y citas requieren PostgreSQL porque el sistema de archivos de las Functions no es persistente.

## Comprobaciones

```powershell
Get-ChildItem api -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check app.js
node --check sell.js
node --check chat-widget.js
```

Para producción, configurá HTTPS, copias de seguridad de PostgreSQL, una política de conservación/eliminación de datos y credenciales con privilegios mínimos. En desarrollo local, respaldá `var/gabinete.sqlite` si contiene información que necesites conservar.
