# El Gabinete del Relojero

Sitio adaptable para compra y venta de relojes, con catálogo, solicitudes de compra, asistente Gemini y un módulo real para recibir relojes ofrecidos por clientes.

## Puesta en marcha local

Requiere PHP 8.1 o posterior con cURL, JSON, mbstring, session, fileinfo y las extensiones SQLite incluidas en la distribución de PHP.

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
No guardes secretos en JavaScript, archivos públicos ni el repositorio.

## Solicitudes de compra

- Cada reloj del catálogo permite iniciar una solicitud de compra.
- El servidor valida el producto y los datos de contacto, y devuelve un código `COMPRA-…`.
- Las solicitudes quedan guardadas en la tabla SQLite `purchase_inquiries`.
- La solicitud no procesa pagos ni confirma por sí sola la disponibilidad definitiva.

## Créditos fotográficos del catálogo

Las fotografías se descargaron de Wikimedia Commons y se alojan en `assets/`. Se muestran con atribución, enlace a su página de licencia y encuadre adaptado mediante CSS:

- Rolex Submariner: EMore98, CC BY-SA 4.0.
- Omega Seamaster: Rama, CC BY-SA 2.0 FR.
- Cartier Santos: Pittigrilli, CC BY-SA 4.0.
- TAG Heuer Monaco: EMore98, CC BY-SA 4.0.

## Módulo «Vendé tu reloj»

- Página pública en `vende-tu-reloj.html`, vinculada desde el menú principal.
- Flujo de cuatro pasos con regreso sin pérdida de datos.
- Validación cliente/servidor y consentimiento de tratamiento de datos.
- Entre 1 y 8 imágenes JPG o PNG; máximo 6 MB cada una y 24 MB en conjunto.
- Análisis multimodal con Gemini cuando hay credenciales.
- La evaluación remota describe únicamente detalles visibles e inconsistencias posibles; no estima precios ni certifica autenticidad.
- Agenda en `America/Montevideo`, configurada de lunes a viernes de 09:00 a 17:00, con bloqueo transaccional y códigos de reserva.

No se encontró un servicio de correo en el proyecto original, por lo que no se simula el envío de confirmaciones. La reserva queda confirmada en pantalla sólo después de persistirse correctamente.

## Base de datos y migraciones

El proyecto original usaba `localStorage` y no incluía base de datos. El nuevo módulo usa SQLite, disponible de forma embebida en PHP:

- Migración: `migrations/001_sell_module.sql`.
- Archivo generado: `var/gabinete.sqlite` (bloqueado por el router local y las reglas `.htaccess` para Apache).
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
- El acceso administrativo heredado queda deshabilitado si no se configuran `ADMIN_USERNAME` y una `ADMIN_PASSWORD` de al menos 12 caracteres; no existen credenciales predeterminadas.
- Transacción `BEGIN IMMEDIATE`, restricciones únicas y nueva comprobación de disponibilidad para evitar doble reserva.
- Historial de cambios y consentimiento fechado.

El catálogo público se define en `app.js`; las solicitudes de compra y de venta usan persistencia SQLite en el servidor.

## Comprobaciones

```powershell
Get-ChildItem api -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check app.js
node --check sell.js
```

Para producción, configurá HTTPS, copias de seguridad de `var/gabinete.sqlite`, política de conservación/eliminación de datos y permisos de escritura mínimos para el usuario de PHP.
