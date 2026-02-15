# 🤖 Guía para Agentes de IA (Codex/Copilot) - App Presto

Bienvenido al repositorio de **App Presto**. Este documento describe la arquitectura, reglas de negocio y comandos operativos para agentes autónomos.

## 📌 Contexto del Proyecto
Sistema de gestión de préstamos informales ("gota a gota" o microcréditos) desarrollado en **Laravel 11** (Backend) e **Inertia.js + Vue 3** (Frontend). 
El objetivo es mantener un **Ledger (Libro Contable)** inmutable para cada préstamo.

## 🏗 Arquitectura y Estructura Clave

### 1. Reglas de Oro (Business Logic)
* **INMUTABILIDAD DEL LEDGER:** Nunca actualices directamente los campos `balance` o `principal` en la tabla `loans`. Todo cambio de saldo debe ocurrir a través de una entrada en `LoanLedgerEntry`.
* **Motores de Cálculo:**
    * `app/Services/InterestEngine.php`: Lógica de cálculo de intereses y devengo diario.
    * `app/Services/PaymentService.php`: Lógica de distribución de pagos (Prelación: Mora > Interés > Capital).
    * `app/Services/AmortizationService.php`: Generación de tablas de amortización.

### 2. Ubicación de Archivos Importantes
| Dominio | Archivos Clave |
| :--- | :--- |
| **Modelos** | `app/Models/Loan.php`, `app/Models/Client.php`, `app/Models/LoanLedgerEntry.php` |
| **Controladores** | `app/Http/Controllers/LoanController.php`, `app/Http/Controllers/PaymentController.php` |
| **Vistas (Vue)** | `resources/js/Pages/Loans/`, `resources/js/Pages/Clients/` |
| **Rutas** | `routes/web.php` |

## 🛠 Comandos de Utilidad

### Configuración del Entorno (Sandbox)
Si estás en un entorno nuevo, ejecuta el script de preparación:
```bash
./setup_codex.sh
```

### Setup recomendado para capturas con Playwright/MCP Browser Tools
Usa este script como base en la configuración del entorno para minimizar errores al validar UI:

```bash
#!/usr/bin/env bash
set -euo pipefail

# 1) Dependencias de proyecto
if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
  npm ci
fi

# 2) Entorno Laravel
if [ ! -f .env ]; then
  cp .env.example .env
fi
php artisan key:generate --force

# 3) Base mínima para navegación de pantallas
php artisan migrate --seed --force

# 4) Build de frontend (evita fallos por assets faltantes)
npm run build

# 5) Arranque del servidor en puerto disponible
PORT="${APP_SCREENSHOT_PORT:-8001}"
while lsof -iTCP:"$PORT" -sTCP:LISTEN -t >/dev/null 2>&1; do
  PORT=$((PORT + 1))
done

echo "Servidor para screenshots en puerto: $PORT"
php artisan serve --host=0.0.0.0 --port="$PORT"
```

Notas operativas:
- En el runner de Codex, las capturas deben realizarse con la herramienta **MCP Browser Tools** (`run_playwright_script`) redirigiendo el puerto donde corre `php artisan serve`.
- Si un puerto está ocupado (ej. 8000), iniciar en 8001+ evita el error `Address already in use`.
- Si el navegador del runner falla por causas del entorno (crash del contenedor), reintentar en otro puerto y con una ruta simple como `/login`.
