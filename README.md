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

### 6) Comandos de verificación rápida post-despliegue

```bash
php artisan schedule:list
php artisan loans:send-overdue-emails
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
php artisan loans:send-admin-status-summary
php artisan loans:update-legal-status
php artisan loans:daily-accrual
```
