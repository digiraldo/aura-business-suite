# Implementación del Sistema de Presupuestos por Área y Usuarios

> **Fecha:** 21 de febrero de 2026  
> **Plugin:** Aura Business Suite — Módulo Financiero  
> **Versión lógica:** Fase 8.3+

---

## Tabla de Contenidos

1. [Visión general](#1-visión-general)
2. [Base de datos](#2-base-de-datos)
3. [Clase principal: Áreas (`class-areas-setup.php`)](#3-clase-principal-áreas)
4. [Clase principal: Presupuestos (`class-financial-budgets.php`)](#4-clase-principal-presupuestos)
5. [Integración con Transacciones](#5-integración-con-transacciones)
6. [Control de aprobación automática](#6-control-de-aprobación-automática)
7. [Notificaciones y alertas](#7-notificaciones-y-alertas)
8. [Reportes y Dashboard](#8-reportes-y-dashboard)
9. [Frontend: Templates y JS](#9-frontend-templates-y-js)
10. [Permisos y roles por usuario](#10-permisos-y-roles-por-usuario)
11. [Flujo completo de una transacción con presupuesto](#11-flujo-completo)
12. [Mapa de archivos](#12-mapa-de-archivos)

---

## 1. Visión general

El sistema de presupuestos funciona sobre el concepto de **Área/Programa**. 
Un área es una unidad organizativa (programa, departamento, equipo) que tiene un **usuario responsable**, un color y un ícono visual. Los presupuestos se asignan a nivel de **área** y las transacciones se etiquetan con el área a la que pertenecen.

```
Usuario responsable
        │
        ▼
   wp_aura_areas (Área/Programa)
        │
        ├──▶ wp_aura_finance_budgets (Presupuesto del período)
        │          columna category_id = referencia opcional
        │
        └──▶ wp_aura_finance_transactions (Transacciones)
                   columna area_id = FK al área
```

### Principio clave

> El presupuesto **no es por categoría**. El eje principal es el **área**. La categoría dentro del presupuesto es solo una referencia informativa (con soporte para desglose analítico).

---

## 2. Base de datos

### 2.1 Tabla `wp_aura_areas`

Creada en `Aura_Areas_Setup::create_areas_table()` con `dbDelta()`.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | ID del área |
| `name` | VARCHAR(100) | Nombre visible (ej. "Hadime Raíces") |
| `slug` | VARCHAR(100) UNIQUE | Identificador único (ej. `hadime-raices`) |
| `type` | ENUM | `program` / `department` / `team` |
| `description` | TEXT | Descripción larga opcional |
| `responsible_user_id` | BIGINT UNSIGNED | **FK → `wp_users.ID`** — el usuario que gestiona el área |
| `parent_area_id` | BIGINT UNSIGNED | FK propia (áreas anidadas, opcional) |
| `color` | VARCHAR(7) | Color hexadecimal para UI (ej. `#720427`) |
| `icon` | VARCHAR(50) | Clase Dashicon (ej. `dashicons-groups`) |
| `status` | ENUM | `active` / `archived` |
| `sort_order` | INT UNSIGNED | Orden de aparición en listas |
| `created_by` | BIGINT UNSIGNED | Usuario que creó el registro |
| `created_at` / `updated_at` | DATETIME | Timestamps automáticos |

**Índices:** `uq_slug`, `idx_status`, `idx_responsible`, `idx_parent`, `idx_type`

#### Programas predefinidos del instituto (insertados automáticamente)

| Nombre | Slug | Color |
|---|---|---|
| Hadime Junior | `hadime-junior` | `#ede522` (amarillo) |
| Hadime Más | `hadime-mas` | `#004526` (verde) |
| Hadime Raíces | `hadime-raices` | `#720427` (borgoña) |
| Hadime Líderes | `hadime-lideres` | `#5B2C6F` (morado) |
| Hadime Misioneros | `hadime-misioneros` | `#102e54` (azul) |
| Hadime Voluntarios | `hadime-voluntarios` | `#E67E22` (naranja) |
| Hadime Rentas | `hadime-rentas` | `#4D5656` (gris pizarra) |
| Hadime Nuevo Programa | `hadime-nuevo-programa` | `#008080` (turquesa) |

---

### 2.2 Tabla `wp_aura_finance_budgets`

Tabla central de presupuestos (creada en la instalación inicial del módulo financiero). Las columnas relevantes al sistema de áreas son:

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | INT PK | ID del presupuesto |
| **`area_id`** | BIGINT UNSIGNED NULL | **FK → `wp_aura_areas.id`** (columna agregada en Fase 7, migración `Aura_Areas_Setup::add_area_id_to_budgets()`) |
| `category_id` | INT NULL | Referencia opcional de categoría de gasto (NULL = presupuesto general del área) |
| `budget_amount` | DECIMAL | Monto asignado para el período |
| `period_type` | ENUM | `monthly` / `quarterly` / `semestral` / `yearly` |
| `start_date` / `end_date` | DATE | Ventana temporal del presupuesto |
| `alert_threshold` | INT | % de ejecución que dispara alerta (ej. 80) |
| `alert_on_exceed` | TINYINT | 1 = alertar al sobrepasar 100% |
| `notify_creator` / `notify_admins` / `notify_emails` | | Destinatarios de alertas por email |
| `alert_sent_threshold` / `alert_sent_exceed` | TINYINT | Flags para evitar alertas duplicadas |
| `is_active` | TINYINT | 0 = eliminado lógicamente |
| `created_by` | INT | Usuario que creó el presupuesto |

> **Nota de migración:** `category_id` se hizo **NULLable** en `Aura_Financial_Budgets::maybe_upgrade_table()` como parte de la refactorización Fase 8.3+ (era NOT NULL antes).

---

### 2.3 Columna `area_id` en `wp_aura_finance_transactions`

Agregada por dos rutas (ambas idempotentes):

- **`Aura_Areas_Setup::add_area_id_to_transactions()`** — durante la migración de Áreas (Fase 7)
- **`Aura_Financial_Transactions::maybe_migrate_area_id()`** — como fallback en `admin_init`

```sql
ALTER TABLE wp_aura_finance_transactions
  ADD COLUMN area_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER related_user_concept,
  ADD INDEX idx_area (area_id);
```

---

## 3. Clase principal: Áreas

**Archivo:** `modules/areas/class-areas-setup.php`

### Métodos principales

| Método | Descripción |
|---|---|
| `init()` | Registra hooks en `admin_init` para `maybe_migrate()` y `run_program_updates()` |
| `maybe_migrate()` | Ejecuta la migración completa UNA VEZ (guard con `wp_options` clave `aura_areas_db_v1`) |
| `create_areas_table()` | Crea `wp_aura_areas` con `dbDelta()` |
| `add_area_id_to_budgets()` | ALTER TABLE a presupuestos |
| `add_area_id_to_transactions()` | ALTER TABLE a transacciones |
| `insert_default_programs()` | Inserta los 8 programas institucionales |
| `maybe_update_programs()` | Migración incremental v2: actualiza colores/nombres y agrega programas nuevos sin borrar datos existentes |
| `default_programs()` | Fuente de verdad: array con slots name/slug/color/icon para cada programa |

### Flujo de activación

```
Plugin activado / admin_init
        │
        ▼
maybe_migrate() → guard wp_options('aura_areas_db_v1')
        │
        ├── create_areas_table()      → dbDelta SQL
        ├── add_area_id_to_budgets()  → ALTER TABLE si no existe
        ├── add_area_id_to_transactions() → ALTER TABLE si no existe
        └── insert_default_programs() → INSERT programas
```

---

## 4. Clase principal: Presupuestos

**Archivo:** `modules/financial/class-financial-budgets.php`  
**Clase:** `Aura_Financial_Budgets` (métodos todos estáticos)

### 4.1 Bootstrap (`init()`)

Registra todos los hooks AJAX en `wp_ajax_*`:

| Acción AJAX | Método | Descripción |
|---|---|---|
| `aura_get_budgets` | `ajax_get_budgets()` | Lista paginada con filtros |
| `aura_save_budget` | `ajax_save_budget()` | Crear o editar presupuesto |
| `aura_delete_budget` | `ajax_delete_budget()` | Soft-delete (`is_active = 0`) |
| `aura_get_budget_detail` | `ajax_get_budget_detail()` | Detalle completo + transacciones + desglose |
| `aura_get_budget_progress` | `ajax_get_budget_progress()` | Lista rápida de presupuestos activos (widget) |
| `aura_adjust_budget` | `ajax_adjust_budget()` | Ajuste por % o monto fijo |
| `aura_budget_widget_data` | `ajax_widget_data()` | Top 5 más críticos para el dashboard |
| `aura_get_area_budget_categories` | `ajax_get_area_budget_categories()` | **[Fase 8 — NUEVO]** Categorías con presupuesto activo para un área |
| `aura_budget_category_breakdown` | `ajax_budget_category_breakdown()` | **[Fase 8 — NUEVO]** Desglose por categoría para el tab de análisis |

También registra el **cron diario** `aura_finance_check_budgets_daily` → `check_budgets_and_alert()`.

---

### 4.2 Métodos de consulta estáticos (reutilizables por otras clases)

#### `get_active_budget_for_area(int $area_id): ?object`

Busca el presupuesto activo vigente HOY para un área específica.

```sql
SELECT * FROM wp_aura_finance_budgets
WHERE area_id   = $area_id
  AND start_date <= TODAY
  AND end_date   >= TODAY
  AND is_active  = 1
ORDER BY start_date DESC LIMIT 1
```

#### `get_executed(int $area_id, string $start, string $end, ?int $category_id): float`

Suma de egresos aprobados en el período, filtrado por área. `category_id` es opcional para desglose.

```sql
SELECT SUM(amount) FROM wp_aura_finance_transactions
WHERE area_id = $area_id
  AND transaction_date BETWEEN $start AND $end
  AND transaction_type = 'expense'
  AND status = 'approved'
  AND deleted_at IS NULL
  [AND category_id = $category_id]  -- solo si se pasa
```

#### `enrich($budget): object`

Agrega campos calculados al objeto de presupuesto:

| Campo agregado | Descripción |
|---|---|
| `executed` | Monto ejecutado (`get_executed()`) |
| `percentage` | % utilizado |
| `available` | `budget_amount - executed` (mín. 0) |
| `overrun` | Exceso sobre el límite (mín. 0) |
| `projection` | Proyección lineal al fin del período |
| `status` | `ok` / `warning` / `critical` / `overrun` |

---

### 4.3 Endpoint: `aura_get_area_budget_categories` (Fase 8 — nuevo)

**Uso:** formulario de transacciones — al seleccionar un área, recarga el `<select>` de categorías.

**Parámetros POST:** `area_id`, `type` (income/expense, opcional)

**Lógica:**
1. Busca categorías que tengan un presupuesto activo vigente para el área:
```sql
SELECT DISTINCT c.id, c.name, c.color, c.icon, c.type,
       b.id AS budget_id, b.budget_amount, b.start_date, b.end_date
FROM wp_aura_finance_budgets b
JOIN wp_aura_finance_categories c ON c.id = b.category_id
WHERE b.area_id = $area_id AND b.is_active = 1
  AND b.start_date <= TODAY AND b.end_date >= TODAY
```
2. Si hay resultados, enriquece cada categoría con `executed`, `available`, `overrun`, `percentage`.
3. Si no hay presupuestos para el área, devuelve **todas las categorías activas** + `has_budgets: false`.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "categories": [ { "id": 5, "name": "Papelería", "budget_amount": 5000, "executed": 1200, "available": 3800, "percentage": 24.0, ... } ],
    "has_budgets": true
  }
}
```

---

### 4.4 Endpoint: `aura_budget_category_breakdown` (Fase 8 — nuevo)

**Uso:** tab "Análisis por Categoría" en el modal de detalle de presupuesto.

**Parámetros POST:** `budget_id`

**Lógica:**
1. Obtiene el presupuesto (área + fechas).
2. Agrupa transacciones aprobadas del período por categoría:
```sql
SELECT COALESCE(c.id,0) AS id, COALESCE(c.name,'— Sin categoría —') AS name, c.color,
       COUNT(t.id) AS tx_count, SUM(t.amount) AS total_amount
FROM wp_aura_finance_transactions t
LEFT JOIN wp_aura_finance_categories c ON t.category_id = c.id
WHERE t.area_id = $area_id
  AND t.transaction_date BETWEEN $start AND $end
  AND t.transaction_type = 'expense'
  AND t.status = 'approved'
  AND t.deleted_at IS NULL
GROUP BY t.category_id
ORDER BY total_amount DESC
```
3. Calcula `pct` (% dentro del total ejecutado de ese período) para cada fila.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "categories": [ { "id": 5, "name": "Papelería", "color": "#2271b1", "tx_count": 4, "total_amount": 1200, "pct": 35.5 } ],
    "total_amount": 3380,
    "budget_amount": 5000,
    "pct_used": 67.6
  }
}
```

---

### 4.5 Sistema de alertas por cron

`check_budgets_and_alert()` se ejecuta **una vez al día** (`aura_finance_check_budgets_daily`):

1. Obtiene todos los presupuestos activos cuyo período incluye HOY.
2. Para cada uno: llama `get_executed()` y calcula el `%`.
3. Si `% >= alert_threshold` y `alert_sent_threshold = 0` → envía email + marca el flag.
4. Si `% >= 100` y `alert_sent_exceed = 0` y `alert_on_exceed = 1` → envía email + marca el flag.

**Destinatarios de email** (configurables por presupuesto):
- `notify_creator` = 1 → email al creador del presupuesto
- `notify_admins` = 1 → todos los administradores del sitio
- `notify_emails` → lista manual separada por comas

---

## 5. Integración con Transacciones

**Archivo:** `modules/financial/class-financial-transactions.php`

### Cómo se asigna el `area_id` al guardar una transacción

```php
// En ajax_save_transaction():

if (usuario_puede('aura_areas_view_own') && !puede('aura_areas_view_all')) {
    // Usuario con área propia → se fuerza automáticamente
    $area_id = SELECT id FROM wp_aura_areas
               WHERE responsible_user_id = $current_user_id
                 AND status = 'active'
               LIMIT 1;
} elseif (!empty($_POST['area_id'])) {
    // Admin/manager → usa el área seleccionada en el formulario
    $area_id = absint($_POST['area_id']);
}
```

> **En resumen:** un usuario con permiso `aura_areas_view_own` **no puede elegir el área** — el sistema la asigna automáticamente según su `responsible_user_id` en `wp_aura_areas`.

### Migración de la columna `area_id`

`maybe_migrate_area_id()` corre en `admin_init` con guard de `wp_options('aura_finance_area_id_migrated_v1')`. Solo ejecuta el `ALTER TABLE` una vez.

---

## 6. Control de Aprobación Automática

**Archivo:** `modules/financial/class-financial-settings.php`  
**Método clave:** `requires_manual_approval(array $transaction_data): bool`

La lógica evalúa tres condiciones en orden:

```
1. ¿La categoría tiene always_require_approval = 1?  → aprobación manual
2. ¿El módulo de origen tiene una excepción configurada? → aprobación manual
3. ¿El monto excedería el presupuesto activo del área?  → aprobación manual
```

La condición 3 usa:

```php
private static function is_budget_exceeded(int $area_id, float $amount): bool {
    $budget   = Aura_Financial_Budgets::get_active_budget_for_area($area_id);
    $executed = Aura_Financial_Budgets::get_executed($area_id, $budget->start_date, $budget->end_date);
    return ($executed + $amount) > $budget->budget_amount;
}
```

Si no hay presupuesto configurado para el área → **no** fuerza aprobación manual (retorna `false`).

---

## 7. Notificaciones y Alertas

**Archivo:** `modules/financial/class-financial-notifications.php`

### Hooks escuchados

| Hook | Método | Cuándo dispara |
|---|---|---|
| `aura_finance_budget_exceeded` | `on_budget_exceeded()` | Cuando la ejecución supera el 100% |
| `aura_finance_budget_saved` | `on_budget_saved()` | Al crear un nuevo presupuesto |

### Tipos de notificación in-app (base de datos)

| Tipo | Texto |
|---|---|
| `budget_warning` | "Presupuesto cercano al límite (80%)" |
| `budget_exceeded` | "Presupuesto sobrepasado" |
| `budget_assigned` | "Nuevo presupuesto asignado" |

### Hooks que dispara `aura_save_budget()`

```php
// Al crear o editar:
do_action('aura_finance_budget_saved', $id, 'created'|'updated', $data);

// Al eliminar:
do_action('aura_finance_budget_deleted', $id);
```

---

## 8. Reportes y Dashboard

### Dashboard financiero principal

**Archivo:** `modules/financial/class-financial-dashboard.php`

- Llama `Aura_Financial_Budgets::render_dashboard_widget()` si la clase existe.
- El widget muestra los **5 presupuestos más consumidos** del período actual.
- También ejecuta una query directa para detectar presupuestos que superaron el `alert_threshold` y mostrar alertas en el panel.
- El cache del dashboard se invalida con los hooks `aura_finance_budget_saved` y `aura_finance_budget_deleted`.

### Reportes

**Archivo:** `modules/financial/class-financial-reports.php`

- `get_budget_data()` — se incluye como sección `budget` en los reportes generales. Hace JOIN entre `aura_finance_budgets`, `aura_finance_categories` y `aura_finance_transactions` para calcular ejecutado y % por presupuesto.
- `budget_flat()` — versión "plana" para exportar a CSV/Excel.

### Exportación

**Archivo:** `modules/financial/class-financial-export.php`

Incluye `area_name` en la exportación de transacciones:

```sql
LEFT JOIN wp_aura_areas a ON t.area_id = a.id
```

---

## 9. Frontend: Templates y JS

### 9.1 Página de Presupuestos

**Template:** `templates/financial/budgets-page.php`  
**Script:** `assets/js/budgets.js`  
**Estilos:** `assets/css/budgets.css`  
**URL admin:** `admin.php?page=aura-financial-budgets`

#### Estructura de la página

```
┌─ Encabezado + botón "Nuevo Presupuesto"
├─ Barra de resumen (KPIs: total presupuestado, ejecutado, sobrepasados...)
├─ Filtros (período, estado, área)
└─ Tabla de presupuestos (con barras de progreso)

MODAL: Crear/Editar Presupuesto
  ├─ Campo Área (requerido)
  ├─ Campo Categoría de referencia (opcional)
  ├─ Monto, Período, Fechas
  └─ Configuración de alertas y notificaciones

MODAL: Detalle del Presupuesto
  ├─ Panel izquierdo: donut chart + stats + ajuste rápido
  └─ Panel derecho:
       ├─ Gráfico histórico (últimos 6 períodos)
       └─ Tabs:
            ├─ [Tab 1] Transacciones del período (tabla)
            └─ [Tab 2] Análisis por Categoría
                   ├─ Gráfico de barras CSS horizontal
                   ├─ Tabla de desglose (categoría / N° trans / monto / %)
                   └─ Alerta contextual si >90% se concentra en una categoría
```

#### Datos localizados (`auraBudgets`)

Inyectados desde `aura-business-suite.php` en `wp_localize_script('aura-budgets', 'auraBudgets', [...])`:

- `ajaxurl` — URL de admin-ajax.php
- `nonce` — nonce `aura_budgets_nonce`
- `budgetsUrl` — enlace a la página de presupuestos
- `txt` — objeto con todos los strings i18n (semestral incluido desde Fase 8)

---

### 9.2 Formulario de Transacciones (integración dinámica)

**Template:** `templates/financial/transaction-form.php`  
**Script:** `assets/js/transaction-form.js`  
**URL admin:** `admin.php?page=aura-financial-new-transaction`

#### Campo de Área

Si el usuario tiene `aura_areas_view_own` y no `aura_areas_view_all`:
- El campo de área se muestra como **texto informativo** (no editable).
- Se envía `area_id` como `<input type="hidden">`.

Si el usuario es manager/admin:
- Se muestra un `<select id="transaction_area_id">` con todas las áreas activas.

#### Carga dinámica de categorías (Fase 8)

Al **cambiar el área seleccionada** (`#transaction_area_id → change`):

```javascript
// 1. Llama aura_get_area_budget_categories
$.post(ajaxUrl, { action: 'aura_get_area_budget_categories', nonce: budgetsNonce, area_id })
  .done(function(res) {
    // 2. Reconstruye el <select id="category_id">
    // 3. Prefija con 💰 las categorías que tienen presupuesto activo
    // 4. Si has_budgets = false → muestra banner amarillo de advertencia
  });
```

#### Banner de estado del presupuesto

Elemento: `<div id="aura-budget-status-banner">`

Se actualiza al **cambiar área o categoría**:

```
💰 Presupuesto activo: Hadime Raíces → Papelería
   Asignado: $5,000.00  |  Ejecutado: $1,200.00 (24.0%)  |  Disponible: $3,800.00
```

Si la combinación no tiene presupuesto:
```
⚠️ No hay presupuesto activo para esta categoría en el área seleccionada.
```

#### Advertencia de sobregiro

Al **cambiar el monto** (`#amount → input`), si `monto ingresado > disponible`:
```
🔴 Este monto supera el disponible del presupuesto ($3,800.00)
```

#### Datos localizados (`auraTransactionData`)

Inyectados en `class-financial-transactions.php`:

```php
wp_localize_script('aura-transaction-form', 'auraTransactionData', [
    'ajaxUrl'      => admin_url('admin-ajax.php'),
    'nonce'        => wp_create_nonce('aura_transaction_nonce'),
    'budgetsNonce' => wp_create_nonce('aura_budgets_nonce'),   // para los endpoints de presupuesto
    'messages'     => [
        'noBudgetsForArea' => '...',
        'noBudgetForCat'   => '...',
        'overspend'        => '...',
        'loadingCats'      => '...',
    ],
]);
```

---

## 10. Permisos y roles por usuario

### Capabilities relevantes

| Capability | Descripción |
|---|---|
| `aura_areas_view_own` | Ve y gestiona únicamente su propia área (linked via `responsible_user_id`) |
| `aura_areas_view_all` | Ve todas las áreas |
| `aura_finance_create` | Puede crear transacciones y presupuestos |
| `aura_finance_edit_all` | Puede editar cualquier transacción/presupuesto |
| `aura_finance_view_all` | Puede ver todos los presupuestos |
| `aura_finance_delete_all` | Puede eliminar presupuestos |
| `aura_finance_approve` | Puede aprobar transacciones |
| `manage_options` | Administrador total (supera todos los filtros) |

### Cómo `responsible_user_id` conecta usuario → área

El usuario con `aura_areas_view_own` se mapea a su área así:

```sql
SELECT id FROM wp_aura_areas
WHERE responsible_user_id = $current_user_id
  AND status = 'active'
LIMIT 1
```

Esta query está presente en:
- `class-financial-transactions.php` — para asignar `area_id` automáticamente.
- `class-financial-budgets.php → ajax_get_budgets()` — para filtrar la lista al usuario.
- `templates/financial/budgets-page.php` — para pre-seleccionar y deshabilitar el filtro de área.
- `templates/financial/transaction-form.php` — para mostrar el área fija y enviarla como hidden.
- `class-financial-transactions-list.php` — para filtrar la lista de transacciones.

### Restricciones de visibilidad en presupuestos

```
Usuario aura_areas_view_own
  → Solo ve presupuestos de su área (WHERE b.area_id = $su_area_id)
  → No puede cambiar el área en el formulario
  → El area_id se fuerza en todos los guardados

Usuario aura_finance_view_all / manage_options
  → Ve todos los presupuestos de todas las áreas
  → Puede filtrar por área con el selector
```

---

## 11. Flujo completo

### Del formulario al presupuesto

```
[Usuario abre formulario de transacción]
        │
        ├── [Área fija] si aura_areas_view_own
        │         └── area_id = su área (hidden)
        │
        └── [Selecciona área] si es admin/manager
                  │
                  ▼
          JS: aura_get_area_budget_categories (AJAX)
                  │
                  ├── tiene presupuesto activo?
                  │     SÍ → recarga categorías (💰 marca las que tienen budget)
                  │     NO → carga todas + banner amarillo ⚠️
                  │
                  ▼
          [Selecciona categoría]
                  │
                  ▼
          JS: renderBudgetBanner() → muestra:
              💰 Asignado: $X | Ejecutado: $Y (Z%) | Disponible: $W
                  │
                  ▼
          [Ingresa monto]
                  │
                  ▼
          JS: checkOverspend() → si monto > disponible → 🔴 advertencia
                  │
                  ▼
          [Submit] → AJAX aura_save_transaction
                  │
                  ▼
          PHP: Aura_Financial_Settings::requires_manual_approval()
                  │
                  ├── is_budget_exceeded() ?
                  │     SÍ → status = 'pending' (requiere aprobación manual)
                  │     NO → status auto-aprobado (si aplica)
                  │
                  ▼
          INSERT en wp_aura_finance_transactions (con area_id)
```

### Del presupuesto al cron de alertas

```
[Cron diario: aura_finance_check_budgets_daily]
        │
        ▼
check_budgets_and_alert()
        │
        ├── Para cada presupuesto activo HOY:
        │     get_executed(area_id, start_date, end_date)
        │     pct = executed / budget_amount * 100
        │
        ├── pct >= alert_threshold && !alert_sent_threshold
        │     → send_alert(type='threshold')  → email  → marcar flag
        │
        └── pct >= 100 && !alert_sent_exceed && alert_on_exceed
              → send_alert(type='exceed')  → email  → marcar flag
```

---

## 12. Mapa de archivos

```
aura-business-suite/
│
├── aura-business-suite.php                    ← Enqueue scripts, wp_localize_script para 
│                                                auraBudgets (con nonce aura_budgets_nonce)
│
├── modules/
│   ├── areas/
│   │   ├── class-areas-setup.php             ← Crea wp_aura_areas, migraciones, programas
│   │   └── class-areas-admin.php             ← UI de administración de áreas
│   │
│   └── financial/
│       ├── class-financial-budgets.php        ← ★ CLASE CENTRAL de presupuestos
│       │     Métodos públicos clave:
│       │       init(), get_active_budget_for_area(), get_executed()
│       │       ajax_get_area_budget_categories() [FASE 8]
│       │       ajax_budget_category_breakdown() [FASE 8]
│       │       check_budgets_and_alert(), send_alert()
│       │       render_dashboard_widget(), render()
│       │
│       ├── class-financial-settings.php       ← is_budget_exceeded() → controla auto-aprobación
│       ├── class-financial-transactions.php   ← Guarda area_id, maybe_migrate_area_id()
│       ├── class-financial-transactions-list.php ← Lista con filtro por área
│       ├── class-financial-dashboard.php      ← Widget de presupuestos, alertas en panel
│       ├── class-financial-reports.php        ← Sección 'budget' en reportes
│       ├── class-financial-notifications.php  ← Hooks: on_budget_saved, on_budget_exceeded
│       └── class-financial-export.php         ← JOIN con areas para exportar area_name
│
├── templates/
│   ├── areas/
│   │   └── areas-page.php                    ← UI de gestión de áreas (273 dashicons)
│   │
│   └── financial/
│       ├── budgets-page.php                  ← Página completa de presupuestos
│       │     Modal crear/editar | Modal detalle (con tabs: Transacciones + Análisis)
│       └── transaction-form.php              ← Formulario con área + banner presupuesto
│
├── assets/
│   ├── js/
│   │   ├── budgets.js                        ← CRUD presupuestos, charts, tabs de análisis
│   │   │     loadCategoryBreakdown() [FASE 8]
│   │   │     renderCategoryBreakdown() [FASE 8]
│   │   │     resetDetailTabs() [FASE 8]
│   │   └── transaction-form.js               ← Carga dinámica de categorías por área
│   │         loadCategoriesForArea() [FASE 8]
│   │         renderBudgetBanner() [FASE 8]
│   │         checkOverspend() [FASE 8]
│   │
│   └── css/
│       └── budgets.css                       ← Estilos (incluye tabs y gráfico de barras CSS)
│
└── queries.sql                               ← Queries de referencia / diagnóstico
```

---

## Resumen de relaciones entre tablas

```sql
wp_users
    │ ID
    └──▶ wp_aura_areas.responsible_user_id
               │ id
               ├──▶ wp_aura_finance_budgets.area_id
               │           │ id
               │           └──▶ (budget_amount, start_date, end_date, alert_threshold...)
               │
               └──▶ wp_aura_finance_transactions.area_id
                               │ category_id
                               └──▶ wp_aura_finance_categories.id
```

---

*Documento generado el 21/02/2026. Refleja el estado del código en la Fase 8 de implementación.*
