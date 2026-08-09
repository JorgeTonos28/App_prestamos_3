# Agente de solicitudes de préstamo por WhatsApp

Esta guía describe la integración implementada, sus límites de seguridad y todo lo necesario para ponerla en producción. La decisión de crédito siempre pertenece a un administrador autenticado: ni WhatsApp ni el modelo crean un préstamo o alteran el ledger.

## Qué hace el sistema

1. Recibe mensajes del número de WhatsApp Business mediante un webhook firmado de Meta.
2. Solicita consentimiento y recopila, paso a paso, identidad, contacto, datos económicos, empleo, referencia personal y las condiciones del préstamo deseado.
3. Solicita los documentos configurados por el administrador. El catálogo inicial incluye identidad, historial crediticio, ingresos, score, estados de cuenta de seis meses, carta de trabajo, referencia y domicilio. Desde la UI se pueden agregar categorías adicionales.
4. Descarga PDF, JPG o PNG desde Meta, comprueba tipo real y tamaño, calcula SHA-256, rechaza duplicados y guarda el archivo en almacenamiento privado.
5. Ejecuta el control antimalware si ClamAV está configurado y usa OpenAI para una revisión visual defensiva. La validación automática no certifica autenticidad legal; un administrador puede validar o rechazar manualmente.
6. Calcula un score con reglas locales y configurables. El LLM solo interpreta respuestas ambiguas, revisa visualmente documentos y redacta la narrativa del informe; nunca determina el score ni toma la decisión.
7. Publica el expediente en **Solicitudes IA**, donde el administrador revisa datos, documentos, chat, auditoría e informe y decide aprobar o rechazar.
8. Notifica la decisión por WhatsApp. Si la ventana de atención de 24 horas terminó, utiliza una plantilla aprobada por Meta.
9. Al aprobar, el administrador puede crear o vincular el cliente y transferirle los documentos válidos. No se crea un préstamo automáticamente.

## Arquitectura y responsabilidades

| Componente | Responsabilidad |
| --- | --- |
| `WhatsAppWebhookController` | Verificación inicial y autenticación HMAC del webhook. |
| `ProcessWhatsAppWebhook` | Procesamiento asíncrono, estados de entrega e idempotencia. |
| `WhatsAppInboundMessageService` | Normalización, bloqueo por teléfono, conversación y persistencia cifrada. |
| `LoanApplicationAgent` | Máquina de estados determinista para consentimiento, preguntas y documentos. |
| `ApplicantDocumentService` | Descarga segura, tipo real, tamaño, checksum, almacenamiento y validación. |
| `RiskScoringService` | Score determinista, métricas, factores, alertas y mitigantes. |
| `RiskAssessmentService` | Snapshot minimizado y narrativa estructurada para revisión humana. |
| `WhatsAppCloudService` | Mensajes, plantillas, descarga de media y estados de entrega. |
| UI `Solicitudes IA` | Buzón, expediente, documentos, decisión y auditoría. |

Los archivos no se guardan como BLOB en MySQL. Se almacenan en el disco privado de Laravel y la base de datos conserva metadatos, checksum, ruta privada, validación y relación con solicitud/cliente. Las descargas pasan por una ruta autenticada.

## Datos solicitados

El flujo incluido recopila:

- consentimiento;
- nombre, tipo y número de identificación, fecha de nacimiento, correo y dirección;
- tipo, monto, finalidad, número de cuotas, frecuencia y cuota máxima deseada;
- ingresos, gastos, pagos de otras deudas, situación y antigüedad laboral;
- empleador o actividad económica;
- nombre y teléfono de referencia personal;
- lista documental definida en administración.

Las validaciones de montos, fechas, frecuencias, teléfono e identificación se ejecutan localmente. El LLM se usa como fallback para interpretar una respuesta que no coincida con los formatos deterministas y su salida debe cumplir un esquema JSON estricto.

## Cálculo de riesgo

El score va de 0 a 100; un valor mayor representa mayor riesgo. Considera, entre otros:

- deuda/ingreso;
- cuota propuesta/ingreso;
- flujo disponible después de gastos, deudas y cuota;
- monto solicitado/ingreso mensual;
- ingreso mínimo;
- situación y antigüedad laboral;
- edad y consentimiento;
- documentos faltantes, inválidos o pendientes;
- score crediticio extraído, cuando esté disponible.

Los umbrales se administran en **Configuración → WhatsApp + IA → Política de riesgo**. La evaluación conserva versión, parámetros, factores y snapshot para auditoría. Reanalizar genera una versión nueva; no sobrescribe la anterior.

## Controles de seguridad implementados

- El `GET` del webhook exige el verify token y el `POST` exige `X-Hub-Signature-256`, calculado sobre el cuerpo crudo con el App Secret.
- Se valida que `phone_number_id` sea exactamente el configurado.
- Payloads, PII, mensajes, resultados, notas y secretos se cifran con `APP_KEY`.
- Las credenciales nunca regresan al navegador; la UI solo muestra si están configuradas.
- Hash único por webhook y `provider_message_id` único evitan reprocesamiento; locks por teléfono y solicitud evitan carreras concurrentes.
- El endpoint tiene rate limiting y es la única excepción CSRF pública.
- La descarga de media acepta HTTPS y hosts permitidos de Meta, no sigue redirecciones ciegas y comprueba MIME mediante magic bytes.
- Solo se admiten PDF, JPEG y PNG; el máximo configurable nunca supera 25 MB.
- SHA-256 evita guardar dos veces el mismo archivo dentro de una solicitud.
- ClamAV puede bloquear y eliminar archivos infectados antes de pasar al banco documental.
- Los documentos se guardan en disco privado y solo usuarios autenticados pueden descargarlos.
- Los prompts tratan toda respuesta y todo documento como datos no confiables. Las llamadas no tienen herramientas, usan Structured Outputs y `store: false`.
- El snapshot de riesgo excluye nombre, identificación, dirección, teléfono y hechos con cuentas o identificadores completos.
- El LLM no puede modificar score, nivel o recomendación determinista.
- Toda aprobación/rechazo requiere acción humana y se registra en auditoría.
- Los errores internos de descarga/validación no se exponen al cliente por WhatsApp.
- Las solicitudes abiertas vencen automáticamente mediante el scheduler.

`store: false` evita que la aplicación cree estado persistente de Responses, pero no sustituye la revisión contractual y de retención del proveedor. Consulta los [controles de datos de OpenAI](https://platform.openai.com/docs/models/default-usage-policies-by-endpoint) y, si aplica a tu organización, solicita Modified Abuse Monitoring o Zero Data Retention.

## Puesta en producción

### 1. Preparar servidor y base de datos

Antes de desplegar, crea un backup de la base de datos y de `storage/app/private`. Mantén el mismo `APP_KEY`: cambiarlo sin un proceso de rotación hará ilegibles secretos y datos cifrados.

En el servidor:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=WhatsAppAgentSettingsSeeder --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Requisitos operativos:

- `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL=https://tu-dominio.com`;
- certificado HTTPS válido y aplicación accesible públicamente;
- permisos de escritura para `storage` y `bootstrap/cache` por el usuario de PHP y del worker;
- base de datos y cache compartidos entre servidor web y workers;
- backup cifrado de base de datos, archivos privados y `APP_KEY`;
- logs centralizados y alertas para jobs fallidos, HTTP 4xx/5xx y espacio de disco.

No publiques `storage/app/private` ni crees enlaces simbólicos hacia esa carpeta. `php artisan storage:link` puede seguir usándose para logos/archivos públicos, pero no participa en documentos del solicitante.

### 2. Mantener las colas activas

Configura en `.env`:

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Mantén este proceso bajo Supervisor, systemd o el administrador de procesos de tu hosting:

```bash
php artisan queue:work --queue=whatsapp,risk-analysis,default --tries=5 --timeout=120 --sleep=2
```

El webhook responde rápido y delega el trabajo a la cola `whatsapp`; el informe usa `risk-analysis`. Sin worker, Meta recibirá el webhook pero el bot no contestará.

### 3. Activar el scheduler

Agrega un cron por minuto:

```cron
* * * * * cd /ruta/app-presto && php artisan schedule:run >> /dev/null 2>&1
```

El scheduler ejecuta `whatsapp:expire-applications` cada hora y cierra solicitudes abiertas cuya fecha haya vencido, además de las tareas existentes del sistema.

### 4. Configurar ClamAV

En Ubuntu/Debian, como referencia:

```bash
sudo apt-get update
sudo apt-get install -y clamav clamav-daemon
sudo freshclam
which clamscan
```

Coloca la ruta absoluta impresa en `.env`, por ejemplo:

```env
CLAMAV_BINARY=/usr/bin/clamscan
```

Luego ejecuta `php artisan config:clear` y reinicia los workers. En Windows usa la ruta absoluta de `clamscan.exe`. Verifica que el usuario del worker pueda ejecutar el binario y leer los archivos privados. Si no se configura, los expedientes muestran `not_configured` y deben recibir revisión manual; para producción se recomienda no omitirlo.

## Configuración de Meta WhatsApp Business

La interfaz oficial de Meta cambia con frecuencia; los nombres pueden variar ligeramente. La integración utiliza WhatsApp Cloud API.

1. En [Meta for Developers](https://developers.facebook.com/), crea o selecciona una app de tipo Business y agrega el producto WhatsApp.
2. Vincula el Business Portfolio/WABA y registra el número de negocio. Completa la verificación del negocio, nombre visible y método de pago que Meta solicite para pasar a producción.
3. Copia el **Phone Number ID** y el **WhatsApp Business Account ID**; no confundas el Phone Number ID con el número telefónico visible.
4. Para producción, crea un System User en Business Settings, asígnale la app y WABA y genera un token con los permisos que Meta requiera, normalmente `whatsapp_business_messaging` y `whatsapp_business_management`. No uses el token temporal de inicio rápido.
5. Copia el **App Secret** desde la configuración de la app.
6. Genera un verify token propio, largo y aleatorio. Ejemplo:

   ```bash
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

7. En App Presto entra a **Configuración → WhatsApp + IA**, deja el agente deshabilitado y guarda:

   - versión de Graph API admitida por tu app;
   - Phone Number ID;
   - Business Account ID;
   - Access Token;
   - App Secret;
   - Verify Token.

8. Copia desde esa pantalla la URL del webhook, que será:

   ```text
   https://tu-dominio.com/webhooks/whatsapp
   ```

9. En la configuración de Webhooks de WhatsApp pega esa URL y exactamente el mismo Verify Token. Suscribe el campo `messages` y la WABA correspondiente. Meta requiere un endpoint HTTPS público con certificado válido; consulta la [referencia oficial de webhooks de WhatsApp](https://www.postman.com/meta/whatsapp-business-platform/folder/tduohwq/webhook-payload-reference).
10. Crea y envía a aprobación dos plantillas sin variables/componentes dinámicos, por ejemplo:

    - `loan_application_approved`;
    - `loan_application_rejected`.

    Selecciona la categoría e idioma que Meta considere adecuados. En la UI coloca los nombres exactos y el código de idioma aprobado, por ejemplo `es_DO`. Las plantillas se usan al notificar fuera de la ventana de 24 horas.
11. Agrega los números de prueba si la app sigue en modo Development. Antes de recibir tráfico real, pasa la app a Live y confirma límites/calidad del número.

Meta entrega un `media_id` en el webhook; la aplicación obtiene la URL temporal y descarga el archivo con Bearer token. Las URL de descarga vencen rápidamente, por eso el worker debe estar activo. Consulta la [operación oficial de descarga de media](https://www.postman.com/meta/whatsapp-business-platform/request/zsq66eh/download-media).

## Configuración de OpenAI

1. En la [plataforma de OpenAI](https://platform.openai.com/) crea un proyecto dedicado para producción.
2. Configura facturación, límites presupuestarios y alertas de uso.
3. Crea una API key del proyecto con el menor acceso posible y guárdala una sola vez.
4. En **Configuración → WhatsApp + IA** pega la key, selecciona `gpt-5.6-terra` y comienza con razonamiento `medium`.
5. Guarda y pulsa **Probar conexiones**.

`gpt-5.6-terra` admite Responses API, Structured Outputs y entrada de imágenes, y está orientado al balance entre calidad y costo. Revisa su [ficha oficial](https://developers.openai.com/api/docs/models/gpt-5.6-terra) y la [guía de la familia GPT-5.6](https://developers.openai.com/api/docs/guides/latest-model) antes de cambiar modelo o esfuerzo.

No pegues secretos en “Instrucciones adicionales” ni “Notas de política”. Esos campos forman parte del prompt administrativo, no son almacenes de credenciales.

## Configuración final desde la UI

Con Meta, OpenAI y ClamAV preparados:

1. En **Configuración → WhatsApp + IA**, confirma que Meta y OpenAI aparezcan como configurados.
2. Personaliza bienvenida y aviso de privacidad con el texto revisado por tu asesor legal.
3. Marca los documentos obligatorios. Usa **Agregar otro requisito documental** para crear nuevas categorías; se seleccionan automáticamente.
4. Configura tamaño máximo y vencimiento de solicitudes.
5. Ajusta los umbrales de riesgo y agrega las reglas internas en “Notas de política”. Prueba los cambios con expedientes sintéticos antes de aplicarlos a clientes reales.
6. Decide si la aprobación debe proponer crear/vincular al cliente por defecto. El administrador todavía debe confirmarlo en cada decisión.
7. Escribe los nombres exactos de las plantillas aprobadas por Meta y su idioma.
8. Guarda, pulsa **Probar conexiones** y corrige cualquier error.
9. Habilita el agente y vuelve a guardar.
10. Envía `Hola` desde un teléfono externo al número del negocio. Completa un caso de bajo riesgo, uno medio, uno alto, un archivo duplicado, un archivo inválido y una decisión fuera de 24 horas.
11. Comprueba **Solicitudes IA**, el perfil del cliente, la descarga autenticada y los eventos de auditoría.

## Pruebas técnicas antes del lanzamiento

Ejecuta:

```bash
php artisan test
npm run build
php artisan schedule:list
php artisan queue:monitor whatsapp,risk-analysis,default --max=100
```

En producción revisa también:

```bash
php artisan queue:failed
php artisan about
```

Usa documentos y personas ficticias en pruebas. No pruebes prompt injection, malware o archivos corruptos con información real.

## Operación diaria

- Revisa primero documentos con `pending_manual_review`, `not_configured` o errores antimalware.
- Contrasta ingresos, empleo, cuentas y score con fuentes primarias; la IA solo ofrece señales.
- Lee factores, banderas rojas, mitigantes y preguntas de verificación antes de decidir.
- Escribe un fundamento claro, especialmente al rechazar.
- Si la notificación falla, la decisión permanece guardada y el evento queda en auditoría. Corrige credenciales/plantilla y contacta al cliente por un canal autorizado.
- Revisa jobs fallidos, consumo de OpenAI/Meta, calidad del número, disco y backups.
- Rota tokens de Meta/OpenAI periódicamente desde la UI y reinicia workers cuando cambies secretos por `.env`.

## Privacidad, cumplimiento y límites

- Adapta el aviso y consentimiento a República Dominicana y a cualquier jurisdicción donde operes. Define base legal, finalidad, acceso, conservación, eliminación, derechos del titular y transferencias a Meta/OpenAI.
- El vencimiento cierra el flujo, pero no elimina automáticamente el expediente. La retención legal de solicitudes rechazadas/canceladas y la del banco documental de clientes aprobados pueden ser distintas; acuerda una política con asesoría legal antes de automatizar borrados.
- Restringe cuentas administrativas, usa contraseñas fuertes, MFA/SSO en infraestructura y principio de mínimo privilegio en base de datos, Meta y OpenAI.
- Protege `APP_KEY` en un secret manager y realiza pruebas periódicas de restauración de backup.
- La validación visual no detecta todo fraude ni sustituye burós, bancos, empleadores, KYC/AML, listas restrictivas o firma de contrato.
- El informe es apoyo a la decisión, no una aprobación automática ni asesoría legal. Evalúa sesgo, explicabilidad, apelación y revisión humana conforme a tu política.
- Un cliente aprobado no recibe un préstamo automáticamente. La originación sigue el flujo normal de App Presto para preservar el ledger inmutable.

## Diagnóstico rápido

| Síntoma | Revisión |
| --- | --- |
| Meta no valida el webhook | HTTPS, URL pública, verify token idéntico y logs de Laravel. |
| Webhook 401 | App Secret incorrecto o proxy que modifica el cuerpo crudo. |
| El bot no responde | Worker activo, agente habilitado, Phone Number ID correcto y `queue:failed`. |
| Mensaje libre no sale | Ventana de 24 horas vencida; configura plantilla aprobada. |
| Media no descarga | Token/permisos, worker rápido, versión Graph y host de media permitido. |
| Documento queda manual | OpenAI o ClamAV no configurado, baja confianza o error del proveedor. |
| Informe no aparece | Cola `risk-analysis`, documentos obligatorios y logs del job. |
| Datos cifrados no abren | `APP_KEY` cambió; restaura la clave original desde backup seguro. |
| Cliente no se crea | Falta identificación/nombre válido, existe eliminado o se desmarcó la opción. |

