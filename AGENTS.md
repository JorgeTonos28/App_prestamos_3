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

### Setup recomendado para capturas con Playwright/MCP Browser Tools (robusto)
Usa este script como base para levantar entorno de screenshots de forma rápida y estable:

```bash
#!/usr/bin/env bash
set -euo pipefail

# ---------- Config ----------
PORT="${APP_SCREENSHOT_PORT:-8001}"
APP_URL="${APP_URL:-http://127.0.0.1:${PORT}}"
DB_FILE="${DB_DATABASE:-database/database.sqlite}"

# ---------- Helpers ----------
retry() {
  local n=0
  local max="${2:-3}"
  local wait_s="${3:-2}"
  until "$1"; do
    n=$((n+1))
    if [ "$n" -ge "$max" ]; then
      echo "❌ Falló comando tras ${max} intentos: $1"
      return 1
    fi
    echo "⚠️ Reintentando (${n}/${max})..."
    sleep "$wait_s"
  done
}

pick_port() {
  local p="$1"
  while lsof -iTCP:"$p" -sTCP:LISTEN -t >/dev/null 2>&1; do
    p=$((p + 1))
  done
  echo "$p"
}

# ---------- 1) Dependencias ----------
if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
  npm ci || npm install
fi

# ---------- 2) Entorno Laravel ----------
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Ajustes seguros para entorno de screenshots
grep -q '^APP_ENV=' .env && sed -i 's/^APP_ENV=.*/APP_ENV=local/' .env || echo 'APP_ENV=local' >> .env
grep -q '^APP_DEBUG=' .env && sed -i 's/^APP_DEBUG=.*/APP_DEBUG=true/' .env || echo 'APP_DEBUG=true' >> .env
grep -q '^APP_URL=' .env && sed -i "s#^APP_URL=.*#APP_URL=${APP_URL}#" .env || echo "APP_URL=${APP_URL}" >> .env

# Forzar sqlite local si no existe configuración usable
if ! grep -q '^DB_CONNECTION=' .env || grep -q '^DB_CONNECTION=mysql' .env; then
  grep -q '^DB_CONNECTION=' .env && sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env || echo 'DB_CONNECTION=sqlite' >> .env
  grep -q '^DB_DATABASE=' .env && sed -i "s#^DB_DATABASE=.*#DB_DATABASE=${DB_FILE}#" .env || echo "DB_DATABASE=${DB_FILE}" >> .env
fi

mkdir -p "$(dirname "$DB_FILE")"
touch "$DB_FILE"

php artisan key:generate --force
php artisan config:clear
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# ---------- 3) Base de datos ----------
php artisan migrate:fresh --seed --force

# ---------- 4) Frontend build ----------
npm run build

# ---------- 5) Servidor ----------
PORT="$(pick_port "$PORT")"
echo "✅ Servidor para screenshots en puerto: $PORT"
echo "🔐 Credenciales seed: admin@prestamos.com / password"
php artisan serve --host=0.0.0.0 --port="$PORT"
```

Notas operativas:
- En MCP Browser Tools, usa `ports_to_forward` con el puerto impreso por el script.
- Para login por Playwright, usa selectores por `id` (`#email`, `#password`) y no por `name`.
- Si falla Chromium con `SIGSEGV` en el runner, primero reintenta (puerto nuevo + ruta `/login`) antes de asumir problema de la app.
- Si hay timeout al localizar inputs, agrega espera explícita: `page.wait_for_selector('#email', timeout=60000)`.
