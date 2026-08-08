# Sistema de Gestión de Préstamos (Laravel + Inertia)

Aplicación web para administrar microcréditos/préstamos informales, orientada a un flujo de operación de **prestamista único**. El diseño funcional gira alrededor de un **ledger por préstamo** para asegurar trazabilidad total de desembolsos, intereses, pagos, moras, gastos legales y ajustes.

> ⚠️ **Regla crítica del dominio:** los saldos de préstamos se derivan del ledger. No deben alterarse manualmente en la tabla `loans` sin registrar su impacto contable correspondiente.

## Características Principales

### 1. Gestión de Clientes
- Registro de clientes con identificador único (Cédula).
- Gestión de información de contacto y estado.
- Historial financiero completo y centralizado.

### 2. Gestión de Préstamos
- **Modalidades Flexibles**: Diario, Semanal, Quincenal, Mensual.
- **Cálculo de Interés**:
  - Motor de interés diario.
  - Soporte para interés simple (por defecto) y compuesto.
  - Tasa mensual convertible automáticamente a tasa diaria.
- **Cuota Fija Autocalculada**: El sistema calcula la cuota basada en el interés esperado del periodo más una amortización de capital opcional.
- **Ledger (Libro Mayor)**: Cada préstamo tiene su propio libro contable donde se registran todas las transacciones.
- **Mora y Legal**: Soporte para cálculo de mora configurable, transición automática a estatus legal y registro de cargos legales.
- **Cancelación y Castigo**: Funcionalidad para cancelar préstamos erróneos o declarar incobrables (castigo de cartera) aquellos con actividad previa.

### 3. Pagos y Cobranza
- **Aplicación Inteligente de Pagos**:
  1. Se actualiza el interés acumulado hasta la fecha del pago.
  2. El pago cubre primero intereses acumulados.
  3. Luego cubre moras/cargos (si existen).
  4. Finalmente, el remanente se aplica al capital (principal).
- **Recálculo Inmediato**: Los saldos se actualizan en tiempo real.
- **Cierre Automático**: El préstamo pasa a estado `closed` cuando el saldo llega a cero.
- **Notificaciones Automatizadas**:
  - Correo de cobranza a clientes en atraso.
  - SMS manuales y recordatorios automáticos mediante LabsMobile.
  - Resumen diario de cartera para el administrador.

### 4. Configuración Avanzada de Corte, Devengo y Mora
- **Devengo configurable**:
  - `realtime`: devenga al ritmo del pago/fecha de operación.
  - `cutoff_only`: devenga en fechas de corte programadas.
- **Modo de mora configurable**:
  - `dynamic_payment`: comportamiento dinámico por pagos.
  - `fixed_cutoff`: cálculo y publicación de mora en cortes fijos.
- **Fecha de corte base (`cutoff_anchor_date`)**: permite anclar el ciclo al desembolso u otra fecha de referencia.
- **Tipo de ciclo de cortes**:
  - `calendar`: en días calendario desde la fecha base.
  - `fixed_dates`: fechas fijas por modalidad (aplica especialmente a quincenal/mensual).
- **Cálculo de meses**:
  - `exact`: días reales del mes.
  - `thirty`: mes comercial de 30 días.
- **Regla actual de disparo de mora**: el sistema está cerrado a **cuotas vencidas** (installments). Se configura únicamente el valor de cuántas cuotas disparan la mora (`late_fee_trigger_value`).
- **Tipo de días para mora**: `business` (laborables) o `calendar` (calendario).
- **Configuración global + snapshot por préstamo**: los valores globales sirven como default para nuevos préstamos; cada préstamo guarda su propia configuración y no se altera retroactivamente cuando cambian los parámetros globales.

### 5. Consolidación de Préstamos
- Capacidad para combinar múltiples préstamos activos del mismo cliente en un nuevo préstamo desde el flujo de creación.
- El sistema valida consistencia de cliente/estado y la cronología de fechas antes de crear la consolidación.
- Se registra el cierre contable de los préstamos origen y la apertura del nuevo préstamo.

### 6. Solicitudes por WhatsApp + IA

- Flujo conversacional con consentimiento, recolección de datos y documentos configurables.
- Webhook firmado de WhatsApp Business Cloud API, archivos privados, deduplicación y ClamAV opcional.
- Score de riesgo determinista e informe asistido por OpenAI para decisión humana.
- Buzón administrativo, auditoría, notificación de decisión y creación opcional del cliente.
- Guía completa: [Agente de solicitudes de préstamo por WhatsApp](docs/whatsapp-agent.md).

## Stack Tecnológico

- **Backend**: [Laravel 11](https://laravel.com) (PHP 8.2+)
- **Frontend**: [Inertia.js](https://inertiajs.com) + [Vue 3](https://vuejs.org)
- **Estilos**: [Tailwind CSS](https://tailwindcss.com)
- **Base de Datos**: MySQL
- **Autenticación**: Laravel Breeze / Sanctum
- **Jobs / Scheduler**: Laravel Scheduler + Queue Worker (según volumen)

## Requisitos del Sistema

- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL

## Instalación y Configuración

Siga estos pasos para levantar el proyecto en un entorno local:

1. **Clonar el repositorio**
   ```bash
   git clone <url-del-repositorio>
   cd <nombre-del-directorio>
   ```

2. **Instalar dependencias de PHP**
   ```bash
   composer install
   ```

3. **Instalar dependencias de Frontend**
   ```bash
   npm install
   ```

4. **Configurar variables de entorno**
   - Copie el archivo de ejemplo:
     ```bash
     cp .env.example .env
     ```
   - Edite el archivo `.env` y configure los datos de conexión a su base de datos:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=nombre_de_tu_bd
     DB_USERNAME=tu_usuario
     DB_PASSWORD=tu_contraseña
     ```

5. **Generar clave de aplicación**
   ```bash
   php artisan key:generate
   ```

6. **Ejecutar migraciones y seeders**
   ```bash
   php artisan migrate --seed
   ```
   Esto crea la estructura base y datos iniciales (usuario administrador + parámetros operativos).

7. **Configuración de Archivos y Almacenamiento (Importante para Logo)**
   Para que el logo y otros archivos cargados sean visibles, debe crear el enlace simbólico del storage y asegurarse de que la URL de la aplicación sea correcta.
   ```bash
   php artisan storage:link
   ```
   Asegúrese de que la variable `APP_URL` en su archivo `.env` coincida con la URL que usa para acceder al sistema (ej. `http://localhost:8000` o `https://midominio.com`).

8. **Iniciar servidores de desarrollo**
   - Para el backend (Laravel):
     ```bash
     php artisan serve
     ```
   - Para el frontend (Vite):
     ```bash
     npm run dev
     ```

Ahora puede acceder a la aplicación en `http://localhost:8000`.

## Solución de errores comunes de conexión a BD

Si al ejecutar `php artisan migrate` o `php artisan db:seed` recibe:

- `SQLSTATE[HY000] [2002] ... conexión denegada`

el problema **no es la migración ni el seeder**; es que Laravel no puede conectarse a MySQL.

### Checklist rápido

1. Verifique que el servidor MySQL esté encendido y escuchando en el host/puerto configurado.
2. Revise `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=app_prestamos
   DB_USERNAME=root
   DB_PASSWORD=...
   ```
3. Limpie caché de configuración antes de reintentar:
   ```bash
   php artisan config:clear
   ```
4. Pruebe conexión y luego migre/siembre:
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```

### Nota sobre `optimize:clear`

Si usa `CACHE_STORE=database` y MySQL está caído, `php artisan optimize:clear` también puede fallar al intentar limpiar la tabla `cache`.
Primero restablezca conexión de base de datos o cambie temporalmente el cache store a `file` para tareas locales.

## Configuración de Producción / Servidor

Esta sección es **obligatoria** para despliegues en cPanel/VPS/Linux. Si estos pasos no se completan, varias automatizaciones del sistema no se ejecutarán.

### 1) Scheduler (Cron Job) – obligatorio

Debe existir **un cron por minuto** ejecutando Laravel Scheduler:

```bash
* * * * * /usr/local/bin/php /home/usuario/ruta_del_proyecto/artisan schedule:run >> /dev/null 2>&1
```

Sin esta configuración, **NO** funcionarán automáticamente:

- `SendOverdueEmails` (`loans:send-overdue-emails`) → envío de correos de cobranza a clientes.
- `SendOverdueSms` (`loans:send-overdue-sms`) → recordatorios SMS de mora mediante LabsMobile.
- `SendAdminLoanStatusSummary` (`loans:send-admin-status-summary`) → reporte diario al administrador.
- `UpdateLegalLoans` (`loans:update-legal-status`) → pase automático de préstamos al flujo legal.
- `RunDailyLoanAccruals` (`loans:daily-accrual`) → verificación/cálculo diario asociado a consistencia legal, intereses/mora y cargos automáticos.

Las tareas están programadas desde `routes/console.php`, por lo que el `schedule:run` por minuto es el gatillo central en producción.

### 2) Configuración de Colas (Queue)

Los correos se envían usando `Mail::queue(...)`. Para carteras pequeñas puede operar con `sync`, pero en volumen medio/alto se recomienda cola asíncrona.

En `.env`:

```env
QUEUE_CONNECTION=database
```

Prepare la tabla (si aplica) y ejecute worker:

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work --tries=3
```

> Recomendado en producción: mantener el worker supervisado (Supervisor/systemd o equivalente de su hosting).

### 3) Seeders de Configuración – obligatorio

Tras instalar, debe ejecutar seeders para parámetros por defecto de negocio:

```bash
php artisan db:seed
```

O de forma puntual:

```bash
php artisan db:seed --class=LateFeeSettingsSeeder
php artisan db:seed --class=LegalFeeSettingsSeeder
```

Estos seeders cargan valores base para mora y legal. Si faltan, los cálculos automáticos pueden fallar por ausencia de parámetros.

Además, están implementados con `firstOrCreate`, por lo que **no duplican** registros cuando la llave de configuración ya existe.

### 4) Configuración de correo (.env)

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.su-dominio.com
MAIL_PORT=465
MAIL_USERNAME=cobros@su-dominio.com
MAIL_PASSWORD=su_contraseña_secreta
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=cobros@su-dominio.com
MAIL_FROM_NAME="${APP_NAME}"
```

También valide en tabla `settings` (si aplica en su flujo):

- `email_sender_address`
- `email_sender_name`
- `admin_notification_email`

### 5) Zona horaria

Para coherencia en envíos y cierres diarios:

```env
APP_TIMEZONE=America/Santo_Domingo
```

### 6) LabsMobile SMS: producción, pruebas y depuración

#### Variables de entorno

El usuario API de LabsMobile es el correo de la cuenta y el token se genera en **Configuración API**. Las credenciales nunca deben versionarse.

```env
LABSMOBILE_ENABLED=true
LABSMOBILE_USERNAME=tu-correo@example.com
LABSMOBILE_TOKEN=tu-token-api
LABSMOBILE_ENDPOINT=https://api.labsmobile.com/json/send
LABSMOBILE_BALANCE_ENDPOINT=https://api.labsmobile.com/json/balance
LABSMOBILE_PRICES_ENDPOINT=https://api.labsmobile.com/json/prices
LABSMOBILE_TEST_MODE=true

# ACK de entrega. La URL debe ser pública y HTTPS.
# No agregue ?token= aquí; PRESTO lo concatena automáticamente.
LABSMOBILE_ACK_URL=https://prestamos.example.com/webhooks/labsmobile/delivery
LABSMOBILE_WEBHOOK_TOKEN=una-cadena-aleatoria-larga-y-secreta
```

La hora, periodicidad, cantidad por día y plantilla de cobranza se configuran únicamente desde **Configuración > SMS > Configurar recordatorios automáticos**; no existe una segunda hora de envío en `.env`.

Genere un token de webhook fuerte, por ejemplo:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Después de modificar `.env`:

```bash
php artisan config:clear
php artisan migrate --force
```

#### Prueba segura sin saldo

Mantenga `LABSMOBILE_TEST_MODE=true` y ejecute:

```bash
php artisan labsmobile:send-test 809-555-1234
```

También puede validar la cobranza completa sin contactar al proveedor:

```bash
php artisan loans:send-overdue-sms --force --dry-run
```

Y luego registrar envíos simulados en LabsMobile, todavía sin entrega real ni consumo de créditos:

```bash
php artisan loans:send-overdue-sms --force
```

En **Configuración > SMS** también puede enviar un mensaje individual. PRESTO muestra antes del envío cantidad de caracteres, GSM/Unicode, cantidad de segmentos SMS, créditos estimados y costo estimado en RD$.

#### ACK: aceptado no significa entregado

Cada envío real incluye el `ackurl` cuando `LABSMOBILE_ACK_URL` y `LABSMOBILE_WEBHOOK_TOKEN` están configurados. LabsMobile llama a:

```text
GET /webhooks/labsmobile/delivery
```

PRESTO protege esa ruta mediante el token y asocia el callback al `subid` del envío. Los diagnósticos principales son:

- `DELIVRD`: entregado y confirmado por el dispositivo.
- `UNDELIV`: no entregable; revisar número, disponibilidad y cobertura.
- `REJECTD`: rechazado por operador/red.
- `BLOCKED`: bloqueado por filtros de seguridad o antispam.
- `EXPIRED`: expiró antes de la entrega.
- `UNKNOWN`: error sin causa más específica.
- `READ`: marcado como leído cuando el canal lo soporta.

En **Configuración > SMS > Historial > Detalles** se muestran `subid`, código API, `acklevel`, descripción ACK, fechas, secuencia de eventos y payload técnico. Un estado **Aceptado por LabsMobile** solo confirma recepción/procesamiento por el proveedor; espere el ACK `DELIVRD` para considerar la entrega confirmada.

En producción confirme que Cloudflare, WAF, ModSecurity o el hosting no bloqueen la URL pública del webhook. El ACK solo puede recibirse para mensajes enviados después de haber configurado el `ackurl`.

Para probar ACK desde Windows en local, con Laravel activo en `127.0.0.1:8001`, ejecute en otra ventana de CMD:

```bat
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\start-labsmobile-ack-tunnel.ps1
```

El script administra la URL temporal de Cloudflare en `.env`. Mantenga esa ventana abierta durante la prueba. No es necesario registrar esta URL temporal en el panel de LabsMobile porque PRESTO incluye el `ackurl` en cada envío. Consulte `docs/labsmobile-local.md` para conocer el uso de cada campo y la configuración estable de producción.

#### Créditos y costo en pesos dominicanos

PRESTO consulta automáticamente `/json/prices` para conocer cuántos créditos consume un SMS estándar a República Dominicana. Los mensajes largos pueden dividirse en varios segmentos y Unicode reduce la capacidad por segmento.

LabsMobile devuelve la tarifa de envío en **créditos**, no el precio monetario pagado por cada crédito de su paquete. Para obtener el costo en RD$, configure en **Configuración > SMS > Configurar recordatorios automáticos** el campo:

**Costo efectivo de 1 crédito LabsMobile (RD$)**

PRESTO calcula:

```text
costo estimado RD$ = segmentos SMS × créditos por SMS RD × RD$ por crédito
```

El resumen filtrado y cada fila del historial muestran cantidad de SMS/segmentos, créditos y costo en pesos dominicanos. Los registros históricos también se recalculan para presentación utilizando la tarifa actual de créditos de República Dominicana.

La plantilla predeterminada de cobranza se mantiene corta y compatible con GSM para evitar que un acento aislado fuerce Unicode y multiplique innecesariamente los segmentos. Las plantillas personalizadas no se sobrescriben.

#### Saldo automático

El saldo se actualiza automáticamente al abrir **Configuración > SMS** y después de cada envío real realizado desde PRESTO. No es necesario pulsar un botón manual.

#### Paso a envíos reales

Antes de cambiar a producción:

1. Verifique `APP_URL` y HTTPS.
2. Ejecute migraciones y `php artisan config:clear`.
3. Confirme usuario/token LabsMobile.
4. Configure el ACK público y el token del webhook.
5. Ejecute pruebas simuladas.
6. Haga un primer envío real a un número propio/controlado.
7. Verifique en PRESTO la transición de **Aceptado** a **Entregado** o el diagnóstico de error.
8. Configure periodicidad, hora, cantidad por día y plantilla desde la UI.
9. Solo entonces active los recordatorios automáticos.

Para habilitar entrega real y consumo de saldo:

```env
LABSMOBILE_TEST_MODE=false
```

Más detalle operativo y troubleshooting: `docs/labsmobile-local.md`.

### 7) Comandos de verificación rápida post-despliegue

```bash
php artisan schedule:list
php artisan loans:send-overdue-emails
php artisan loans:send-overdue-sms --force --dry-run
php artisan loans:send-admin-status-summary
php artisan loans:update-legal-status
php artisan loans:daily-accrual
```

## Automatización de Correos (Recordatorios y Reportes)

La automatización de correos ya está cubierta en la sección **Configuración de Producción / Servidor** (Scheduler, colas, correo y zona horaria). Esa configuración aplica tanto para:

- Recordatorios de atraso a clientes.
- Resumen diario de cartera para el administrador.

Prueba manual recomendada:

```bash
php artisan loans:send-overdue-emails
php artisan loans:send-admin-status-summary
```

## Acceso por Defecto
Si ejecutó los seeders (`php artisan db:seed`), puede ingresar con:
- **Email**: `admin@prestamos.com`
- **Contraseña**: `password`

## Lógica de Negocio y Supuestos

### Cálculo de Intereses
- **Tasa Mensual**: La tasa se define mensualmente.
- **Tasa Diaria**: Se calcula dividiendo la tasa mensual por la convención de días del mes (por defecto 30, configurable).
- **Accrual (Devengo)**:
  - En `realtime`, se proyecta/acumula según la fecha efectiva de operación.
  - En `cutoff_only`, se acumula por cortes del ciclo configurado.
- **Interés simple en tabla de amortización**: para préstamos `simple`, la cuota de interés proyectada se mantiene sobre el **capital inicial** (no sobre saldo restante), incluso tras registrar/eliminar pagos retroactivos.
- **Regla quincenal**: el cálculo de cortes quincenales se ajusta a periodos de 15 días según configuración de ciclo y convención mensual.

### Regla de Inmutabilidad del Ledger
- El ledger es la fuente de verdad de saldos financieros.
- Tipos comunes de entrada: `disbursement`, `interest_accrual`, `payment`, `fee_accrual`, `legal_fee`, `adjustment`, `refinance_payoff`, `write_off`, `cancellation`.
- La prelación de pagos implementada actualmente es: **interés → mora/cargos (mora + legales) → capital**.

### Nuevos Campos Técnicos Relevantes (tabla `loans`)
Se incorporaron campos para soportar las reglas avanzadas de corte/devengo/mora:
- `late_fee_cutoff_mode`
- `payment_accrual_mode`
- `cutoff_anchor_date`
- `cutoff_cycle_mode`
- `month_day_count_mode`
- `late_fee_trigger_type` (actualmente operativo en `installments`)
- `late_fee_trigger_value`
- `late_fee_day_type`

Estos campos se aplican desde migraciones y defaults de configuración (seeders), y son consumidos por los servicios de negocio (`InterestEngine`, `PaymentService`, `ArrearsCalculator`, `LateFeeService`, `LegalStatusService`) para mantener consistencia de cálculo.

### Procesos automáticos relevantes
- `loans:send-overdue-emails`: notifica clientes en mora.
- `loans:send-overdue-sms`: envía recordatorios SMS configurables a clientes en mora.
- `loans:send-admin-status-summary`: envía consolidado de préstamos en atraso y legales al administrador.
- `loans:update-legal-status`: mueve préstamos elegibles a legal y registra cargo de entrada legal.
- `loans:daily-accrual`: corrida diaria de consistencia/acumulación relacionada con legal y cargos automáticos.

### Ledger (Libro Contable)
El sistema no modifica los saldos arbitrariamente. Todo cambio en `balance_total`, `principal_outstanding` o `interest_accrued` es el resultado de sumar las entradas en la tabla `loan_ledger_entries`.
- `interest_accrual`: Aumenta el saldo de intereses y el total.
- `payment`: Disminuye los saldos (se registra con valores negativos).
- `disbursement`: Aumenta el capital pendiente.

### Estructura de Directorios Clave
- `app/Models`: Modelos de datos (`Loan`, `Client`, `Payment`, `LoanLedgerEntry`).
- `app/Services`: Lógica compleja de negocio.
  - `InterestEngine.php`: Cálculo de tasas y devengo de intereses.
  - `PaymentService.php`: Lógica de aplicación de pagos y distribución de montos.
  - `InstallmentCalculator.php`: Cálculo de cuotas fijas.
  - `ArrearsCalculator.php`: Cálculo de moras y días de atraso.
  - `LegalStatusService.php`: Reglas para transición a estatus legal y aplicación de cargos legales.
  - `LabsMobileSmsService.php`: API, saldo, tarifa por país y ACK de LabsMobile.
  - `SmsDispatchService.php`: trazabilidad, créditos, costo y despacho de SMS.
- `resources/js/Pages`: Vistas del frontend (Vue components).

## Comandos Útiles

```bash
# Desarrollo
php artisan serve
npm run dev

# Calidad / utilidades
php artisan test
php artisan optimize:clear

# Operativos de cartera
php artisan loans:send-overdue-emails
php artisan loans:send-overdue-sms --force --dry-run
php artisan loans:send-admin-status-summary
php artisan loans:update-legal-status
php artisan loans:daily-accrual
```
