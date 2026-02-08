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
