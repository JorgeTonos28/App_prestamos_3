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

### Setup recomendado para capturas con Playwright/MCP Browser Tools (robusto y **sin bloquear startup**)
Usa este script como base para preparar el entorno de screenshots de forma rápida y estable.

> ⚠️ Importante: este script **NO** deja `php artisan serve` en foreground por defecto.
> Eso evita el error `Startup script timed out after 1200 seconds`.
> Si quieres arrancar servidor desde el mismo script, exporta `START_SERVER=1`.

```bash
#!/usr/bin/env bash
set -euo pipefail

# ---------- Config ----------
PORT="${APP_SCREENSHOT_PORT:-8001}"
APP_URL="${APP_URL:-http://127.0.0.1:${PORT}}"
DB_FILE="${DB_DATABASE:-database/database.sqlite}"
START_SERVER="${START_SERVER:-0}"   # 0 = solo setup (recomendado para startup); 1 = también levanta servidor
RUN_BUILD="${RUN_BUILD:-1}"         # 1 = npm run build; 0 = saltar build
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}" # 1 = migrate:fresh --seed; 0 = saltar migraciones

# ---------- Helpers ----------
pick_port() {
  local p="$1"
  while ss -ltn "( sport = :$p )" 2>/dev/null | rg -q ":$p"; do
    p=$((p + 1))
  done
  echo "$p"
}

upsert_env() {
  local key="$1"
  local value="$2"
  if rg -q "^${key}=" .env; then
    sed -i "s#^${key}=.*#${key}=${value}#" .env
  else
    echo "${key}=${value}" >> .env
  fi
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
upsert_env APP_ENV local
upsert_env APP_DEBUG true
upsert_env APP_URL "$APP_URL"
upsert_env DB_CONNECTION sqlite
upsert_env DB_DATABASE "$DB_FILE"
upsert_env CACHE_STORE database
upsert_env SESSION_DRIVER database
upsert_env QUEUE_CONNECTION database

mkdir -p "$(dirname "$DB_FILE")"
touch "$DB_FILE"

php artisan key:generate --force
php artisan config:clear
php artisan optimize:clear || true
php artisan route:clear || true
php artisan view:clear || true

# ---------- 3) Base de datos ----------
if [ "$RUN_MIGRATIONS" = "1" ]; then
  php artisan migrate:fresh --seed --force
fi

# ---------- 4) Frontend build ----------
if [ "$RUN_BUILD" = "1" ]; then
  npm run build
fi

# ---------- 5) Servidor ----------
PORT="$(pick_port "$PORT")"
echo "✅ Servidor para screenshots en puerto: $PORT"
echo "🔐 Credenciales seed: admin@prestamos.com / password"

if [ "$START_SERVER" = "1" ]; then
  php artisan serve --host=0.0.0.0 --port="$PORT"
else
  echo "ℹ️ Setup terminado sin iniciar servidor (START_SERVER=0)."
  echo "▶️ Inicia servidor manualmente cuando lo necesites: php artisan serve --host=0.0.0.0 --port=$PORT"
fi
```

Notas operativas:
- En MCP Browser Tools, usa `ports_to_forward` con el puerto impreso por el script.
- Si lo usarás como **startup script del entorno**, déjalo con `START_SERVER=0` para evitar timeout.
- Para levantar servidor en una segunda terminal/sesión: `START_SERVER=1 ./setup_codex.sh` o `php artisan serve --host=0.0.0.0 --port=8001`.
- Para login por Playwright, usa selectores por `id` (`#email`, `#password`) y no por `name`.
- Si falla Chromium con `SIGSEGV` en el runner, primero reintenta (puerto nuevo + ruta `/login`) antes de asumir problema de la app.
- Si hay timeout al localizar inputs, agrega espera explícita: `page.wait_for_selector('#email', timeout=60000)`.
