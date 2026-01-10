# Sistema de Gestión de Préstamos (Laravel + Inertia)

Este proyecto es una aplicación web para la gestión de préstamos informales, diseñada específicamente para un administrador único (prestamista). El sistema utiliza una arquitectura basada en un libro contable (ledger) por préstamo, lo que garantiza que cada evento financiero (desembolso, acumulación de interés, pago, ajuste) quede registrado de manera inmutable y auditable.

El núcleo del negocio es el cálculo diario de intereses y la capacidad de manejar pagos parciales, anticipados y refinanciamientos, manteniendo siempre el saldo actualizado al día.

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

### 3. Pagos y Cobranza
- **Aplicación Inteligente de Pagos**:
  1. Se actualiza el interés acumulado hasta la fecha del pago.
  2. El pago cubre primero moras/cargos (si existen).
  3. Luego cubre intereses acumulados.
  4. Finalmente, el remanente se aplica al capital (principal).
- **Recálculo Inmediato**: Los saldos se actualizan en tiempo real.
- **Cierre Automático**: El préstamo pasa a estado `closed` cuando el saldo llega a cero.

### 4. Refinanciamiento y Consolidación
- Capacidad para combinar múltiples préstamos activos de un cliente en un nuevo préstamo.
- Cierre contable de los préstamos anteriores y registro de apertura en el nuevo.

## Stack Tecnológico

- **Backend**: [Laravel 11](https://laravel.com) (PHP 8.2+)
- **Frontend**: [Inertia.js](https://inertiajs.com) + [Vue 3](https://vuejs.org)
- **Estilos**: [Tailwind CSS](https://tailwindcss.com)
- **Base de Datos**: MySQL
- **Autenticación**: Laravel Breeze / Sanctum

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

6. **Ejecutar migraciones**
   ```bash
   php artisan migrate
   ```

7. **Iniciar servidores de desarrollo**
   - Para el backend (Laravel):
     ```bash
     php artisan serve
     ```
   - Para el frontend (Vite):
     ```bash
     npm run dev
     ```

Ahora puede acceder a la aplicación en `http://localhost:8000`.

## Lógica de Negocio y Supuestos

### Cálculo de Intereses
- **Tasa Mensual**: La tasa se define mensualmente.
- **Tasa Diaria**: Se calcula dividiendo la tasa mensual por la convención de días del mes (por defecto 30, configurable).
- **Accrual (Devengo)**: Un proceso (manual al ver el préstamo o automático por job) calcula los intereses diarios desde la última fecha de actualización hasta "hoy".

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
- `resources/js/Pages`: Vistas del frontend (Vue components).
