# Sistema de Presupuestos por Categoría
**Fase 5, Item 5.1 — Aura Business Suite**

---

## Descripción General

El módulo de Presupuestos permite definir límites de gasto por categoría financiera con períodos configurables. El sistema monitorea en tiempo real el consumo contra el presupuesto asignado, proyecta el gasto al final del período, envía alertas automáticas por email y presenta un widget resumen en el dashboard financiero.

---

## Archivos del Módulo

| Archivo | Rol |
|---|---|
| `modules/financial/class-financial-budgets.php` | Clase principal: CRUD, cálculos, alertas, cron, AJAX |
| `templates/financial/budgets-page.php` | Interfaz HTML: tabla, modales crear/editar y detalle |
| `assets/js/budgets.js` | Lógica frontend: AJAX, gráficos ApexCharts, modales |
| `assets/css/budgets.css` | Estilos: tabla, barras de progreso, modales |

**Registro en el plugin principal (`aura-business-suite.php`):**
- `require_once` de la clase
- `Aura_Financial_Budgets::init()` en el hook `init`
- Submenú "📊 Presupuestos" en el panel de administración
- Enqueue de ApexCharts CDN + CSS + JS + `wp_localize_script` con nonce e i18n
- Método `render_budgets_page()` que invoca `Aura_Financial_Budgets::render()`

---

## Base de Datos

### Tabla `wp_aura_finance_budgets`

La tabla fue creada en una fase anterior. El módulo la extiende automáticamente mediante `maybe_upgrade_table()` en el hook `admin_init`.

**Columnas originales:**

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK autoincremental |
| `category_id` | BIGINT UNSIGNED | FK a `wp_aura_finance_categories` |
| `budget_amount` | DECIMAL(15,2) | Monto total presupuestado |
| `period_type` | ENUM | `monthly`, `quarterly`, `semestral`, `yearly` |
| `start_date` | DATE | Inicio del período |
| `end_date` | DATE | Fin del período |
| `alert_threshold` | INT | % para primera alerta (default 80) |
| `is_active` | BOOLEAN | Soft-delete (0 = eliminado) |
| `created_by` | BIGINT UNSIGNED | ID del usuario creador |
| `created_at` | DATETIME | Timestamp de creación |
| `updated_at` | DATETIME | Timestamp de última modificación |

**Columnas agregadas por `maybe_upgrade_table()`:**

| Columna | Tipo | Descripción |
|---|---|---|
| `alert_on_exceed` | TINYINT(1) | Enviar alerta al sobrepasar 100% |
| `notify_creator` | TINYINT(1) | Notificar al creador |
| `notify_admins` | TINYINT(1) | Notificar a administradores |
| `notify_emails` | TEXT | Emails adicionales separados por coma |
| `alert_sent_threshold` | TINYINT(1) | Flag: alerta de umbral ya enviada |
| `alert_sent_exceed` | TINYINT(1) | Flag: alerta de exceso ya enviada |

> El `ENUM period_type` también se amplía para incluir `semestral` si aún no lo contiene.

---

## Flujo de Datos

```
Usuario abre página Presupuestos
        │
        ▼
budgets.js → AJAX aura_get_budgets
        │
        ▼
class-financial-budgets.php::ajax_get_budgets()
  ├── SELECT budgets JOIN categories (name, color, icon)
  ├── array_map(enrich) → agrega executed, percentage, available, overrun, projection, status
  └── wp_send_json_success
        │
        ▼
budgets.js renderiza:
  ├── Barra de resumen (total presupuestado, ejecutado, conteos por estado)
  ├── Tabla con ícono+color de categoría, período, monto, barra de progreso, acciones
  └── Filtros por tipo de período y estado
```

---

## Cálculos Clave

### Método `enrich($budget)`

Enriquece cada fila de presupuesto con campos calculados:

```
executed    = SUM de transacciones tipo 'expense', no rechazadas, no eliminadas,
              en el rango start_date — end_date de esa categoría

percentage  = (executed / budget_amount) × 100

available   = MAX(0, budget_amount - executed)

overrun     = MAX(0, executed - budget_amount)

projection  = estimación lineal del gasto total al fin del período:
              días_transcurridos = hoy - start_date
              total_días         = end_date - start_date
              projection         = (executed / días_transcurridos) × total_días
              (si días_transcurridos = 0, projection = 0)

status      = 'ok'       → percentage < 70
              'warning'  → 70 ≤ percentage < 90
              'critical' → 90 ≤ percentage < 100
              'overrun'  → percentage ≥ 100
```

### Colores de estado

| Estado | Rango | Color |
|---|---|---|
| ok | 0 – 69% | Verde `#00a32a` |
| warning | 70 – 89% | Amarillo `#dba617` |
| critical | 90 – 99% | Naranja `#f97316` |
| overrun | ≥ 100% | Rojo `#d63638` |

---

## Períodos Disponibles

| Valor | Etiqueta | Duración |
|---|---|---|
| `monthly` | Mensual | 1 mes |
| `quarterly` | Trimestral | 3 meses |
| `semestral` | Semestral | 6 meses |
| `yearly` | Anual | 12 meses |

Al seleccionar el tipo de período en el formulario, la **Fecha fin se calcula automáticamente** a partir de la Fecha inicio en `budgets.js → autoSetEndDate()`.

---

## Acciones AJAX

| Acción WordPress | Método PHP | Descripción |
|---|---|---|
| `aura_get_budgets` | `ajax_get_budgets()` | Lista todos los presupuestos activos enriquecidos |
| `aura_save_budget` | `ajax_save_budget()` | Crear o editar presupuesto; resetea flags de alerta en edición |
| `aura_delete_budget` | `ajax_delete_budget()` | Soft-delete (`is_active = 0`) |
| `aura_get_budget_detail` | `ajax_get_budget_detail()` | Presupuesto + últimas 50 transacciones + historial 6 períodos |
| `aura_get_budget_progress` | `ajax_get_budget_progress()` | Presupuestos vigentes hoy (para widget) |
| `aura_adjust_budget` | `ajax_adjust_budget()` | Aumentar/reducir monto por % o valor fijo; resetea flags |
| `aura_budget_widget_data` | `ajax_widget_data()` | Top 5 presupuestos más consumidos (para widget dashboard) |

**Nonce:** `aura_budgets_nonce`  
**Permisos mínimos:** `aura_finance_view_all` o `manage_options`

---

## Interfaz de Usuario

### Página principal

- **Barra de resumen:** 6 tarjetas (total presupuestado, total ejecutado, # presupuestos, # sobrepasados, # en alerta, # en buen estado)
- **Filtros:** por tipo de período y por estado; botón limpiar
- **Tabla:** columnas Categoría (íconodashicons coloreado + nombre), Período (badge), Presupuesto, Ejecutado, Disponible/Exceso, %, Barra de progreso, Acciones
- **Filas destacadas** con fondo según estado (rojo pálido para overrun, amarillo para warning)

### Modal Crear / Editar

Campos del formulario:

| Campo | Tipo | Notas |
|---|---|---|
| Categoría | Select agrupado | Agrupa por Ingresos / Egresos |
| Monto | Number con prefijo $ | Mínimo 0.01 |
| Tipo de período | Radio pills | Mensual / Trimestral / Semestral / Anual |
| Fecha inicio | Date | Permite fechas futuras (`data-allow-future`) |
| Fecha fin | Date | Se calcula automáticamente al elegir período |
| Umbral de alerta | Number (1–99) | % al que se envía la primera alerta (default 80) |
| Alerta al sobrepasar | Checkbox | Enviar email al superar 100% |
| Notificar creador | Checkbox | |
| Notificar admins | Checkbox | |
| Emails adicionales | Text (toggle) | Separados por coma |

### Modal Detalle

- **Gráfico donut** (ApexCharts): Ejecutado / Disponible / Exceso con colores del estado
- **Estadísticas:** Presupuesto total, Ejecutado (%), Disponible o Exceso, Proyección al fin del período
- **Gráfico histórico** (línea): Presupuesto vs Ejecutado en los últimos 6 períodos equivalentes
- **Tabla de transacciones:** últimas 50 del período (fecha, descripción, monto, estado)
- **Ajuste rápido:** aumentar/reducir por porcentaje o monto fijo

---

## Sistema de Alertas

### Cuándo se disparan

El cron diario `aura_finance_check_budgets_daily` (hook de WP-Cron, frecuencia `daily`) llama a `check_budgets_and_alert()`, que:

1. Obtiene todos los presupuestos activos donde `hoy` está dentro del rango `start_date — end_date`
2. Enriquece cada presupuesto con `enrich()`
3. Para cada presupuesto:
   - Si `percentage ≥ alert_threshold` y `alert_sent_threshold = 0` → envía alerta de umbral y marca el flag
   - Si `percentage ≥ 100` y `alert_on_exceed = 1` y `alert_sent_exceed = 0` → envía alerta de exceso y marca el flag

### A quiénes se envía

`send_alert($budget, $type, $pct)` reúne destinatarios según configuración del presupuesto:
- Creador del presupuesto (si `notify_creator = 1`)
- Todos los usuarios con rol `administrator` (si `notify_admins = 1`)
- Emails adicionales de `notify_emails`

El email es HTML con `wp_mail()`, indica categoría, período, monto presupuestado, ejecutado y porcentaje.

### Reset de flags

Cada vez que se edita o ajusta un presupuesto, `alert_sent_threshold` y `alert_sent_exceed` se resetean a `0` para que las alertas puedan dispararse nuevamente si aplica.

---

## Widget en Dashboard Financiero

`Aura_Financial_Budgets::render_dashboard_widget()` inserta un bloque HTML en la página del Dashboard Financiero. Al cargar, `budgets.js` detecta `#aura-budget-widget-body` y llama a `aura_budget_widget_data` para obtener los **5 presupuestos más consumidos** del período actual, mostrando:

- Ícono dashicons coloreado + nombre de categoría
- % de consumo (coloreado según estado)
- Barra de progreso
- Monto ejecutado / monto total
- Enlace "Ver todos →" a la página de presupuestos

---

## Seguridad

- Todos los endpoints AJAX verifican nonce con `check_ajax_referer('aura_budgets_nonce', 'nonce')`
- Permisos verificados individualmente por acción (vista, creación, edición, eliminación)
- Entradas sanitizadas con `sanitize_text_field`, `absint`, `floatval`, `sanitize_email`
- Salidas escapadas con `esc_html`, `esc_attr`, `esc_url` en el template PHP
- En JS: función `escHtml()` para contenido dinámico insertado en el DOM

---

## Dependencias

| Dependencia | Versión | Carga |
|---|---|---|
| jQuery | (bundled WP) | Automática |
| ApexCharts | 3.44.0 | CDN `jsdelivr.net` (solo en la página de presupuestos) |
| Dashicons | (bundled WP) | Automática en admin |
| `wp_aura_finance_categories` | — | Tabla existente del módulo de categorías |
| `wp_aura_finance_transactions` | — | Tabla existente del módulo de transacciones |
| `wp_aura_finance_budgets` | — | Tabla existente, extendida por este módulo |
