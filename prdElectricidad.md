# PRD — Módulo de Electricidad (Aura Business Suite)

> **Versión:** 1.0 | **Fecha:** Abril 2026
> **Contexto:** Este módulo forma parte de Aura Business Suite. El sistema de áreas (`wp_aura_areas`), el sistema de permisos granulares CBAC (`aura_*` capabilities) y el sistema de notificaciones **ya existen e implementados**. No se crean roles nuevos ni tablas nuevas de áreas o usuarios.

---

## 1. Resumen Ejecutivo

El **Módulo de Electricidad** permite a la organización registrar lecturas del medidor eléctrico, calcular el consumo en kWh por período, estimar costos de factura, configurar alertas de consumo alto y visualizar tendencias en dashboards. Las lecturas pueden registrarse manualmente por cualquier usuario autorizado o automáticamente vía API REST (sensor IoT / microcontrolador).

El módulo se integra con:
- `wp_aura_areas` — Áreas organizacionales (medidores asignados a áreas específicas)
- Sistema CBAC de Aura Suite — Capabilities `aura_electric_*` asignadas vía el gestor de permisos
- `Aura_Notifications` — Alertas de consumo alto por email y/o WhatsApp
- Módulo Finanzas (opcional) — Registrar costo del recibo como transacción
- **Sistema de Integraciones de Categorías Financieras** — Las lecturas de electricidad pueden vincularse automáticamente a categorías financieras específicas

---

## 2. Contexto de Integración con Aura Suite

### 2.0 Integración con Categorías Financieras (NUEVO)

A partir de la versión 1.2 del módulo Finanzas, las **categorías financieras** ahora soportan un sistema de **integraciones multi-módulo**. Esto permite que:

1. **Una categoría pueda ser reutilizada por múltiples módulos:**
   - Vehículos (rental, mantenimiento)
   - Inventario (adquisición de equipos)
   - Estudiantes (cuotas de inscripción)
   - Biblioteca (adquisición de libros)
   - **Electricidad** (consumo de kWh y facturas)

2. **Flujo de integración en Electricidad:**
   - Administrador configura en **Finanzas → Categorías** una categoría para "Consumo de Electricidad" con type `expense`
   - Al crear esa categoría, marca **"Electricidad"** en la sección de integraciones disponibles
   - En el módulo Electricidad, en **Configuración**, el admin puede seleccionar qué categoría usar para registrar automáticamente los costos de facturas
   - Cuando se cierra un período de lectura, se crea una transacción financiera en esa categoría con el costo total calculado

3. **Estructura de la integración:**
   ```
   wp_aura_finance_categories
   ├─ id: 45
   ├─ name: "Consumo de Electricidad"
   ├─ type: "expense"
   ├─ is_active: 1
   └─ integration_modules: ["electricity", "inventory"]  ← JSON array de módulos que usan esta categoría
   ```

4. **Ventajas:**
   - Una sola categoría puede ser usada por múltiples módulos sin duplicación
   - Todas las transacciones (vehículos, electricidad, inventario) quedan registradas en la misma categoría si se decide
   - Dashboard Financiero unifica automáticamente todos los gastos por categoría
   - Mejor rastreabilidad y auditoría de costos por área/módulo

5. **Configuración en Electricidad:**
   - Nueva opción en **Configuración** del módulo: "Categoría Financiera para Consumo"
   - Dropdown que muestra solo categorías con `integration_modules` que incluya `"electricity"`
   - Checkbox: "Crear automáticamente transacciones de consumo"
   - Si está habilitado, cada cierre de período genera una transacción con `status: pending` para revisar antes de aprob

ar

---

### 2.1 Lo que ya existe (NO reimplementar)

| Sistema | Tabla / Clase | Estado |
|---------|--------------|--------|
| Áreas organizacionales | `wp_aura_areas` | ✅ Implementado |
| Usuarios por área | `wp_aura_area_users` | ✅ Implementado |
| Gestor de roles/caps | `modules/common/class-roles-manager.php` | ✅ Implementado |
| Sistema de notificaciones | `modules/common/class-notifications.php` | ✅ Implementado |
| Panel admin Aura Suite | `aura-business-suite.php` | ✅ Implementado |
| CPT stub (legacy) | `modules/electricity/class-electricity-cpt.php` | ⚠️ Eliminar — se reemplaza por tablas custom |
| Dashboard stub | `modules/electricity/class-electricity-dashboard.php` | ⚠️ Stub — reescribir completo |
| API stub | `modules/electricity/class-electricity-api.php` | ⚠️ Stub — adaptar y ampliar |

### 2.2 Decisión de arquitectura: CPT → Tablas custom

El stub actual usa `aura_electric_reading` como Custom Post Type con post_meta. Esto genera:
- Consultas ineficientes (JOIN con `wp_postmeta`)
- Imposibilidad de filtros por área / sensor / fecha complejos
- Sin soporte para múltiples contadores/medidores

**Solución:** Migrar a tablas custom con `dbDelta()`, igual que Vehículos e Inventario:
- `wp_aura_electric_meters` — Medidores / contadores
- `wp_aura_electric_readings` — Lecturas
- `wp_aura_electric_audit` — Auditoría

### 2.3 Convenciones del proyecto a respetar

- **Prefijo de tablas:** `wp_aura_electric_` (ej. `wp_aura_electric_readings`)
- **Prefijo de capabilities:** `aura_electric_` (ej. `aura_electric_reading_create`)
- **Prefijo de opciones:** `aura_electric_` (ej. `aura_electric_cost_per_kwh`)
- **Namespace REST:** `/wp-json/aura/v1/electricity/`
- **Text domain:** `aura-suite`
- **Clases PHP:** prefijo `Aura_Electric_*`
- **Archivos:** `class-electric-*.php` dentro de `modules/electricity/`
- **Menú WP Admin:** menú propio (igual que Vehículos — fuera del menú de Aura Suite)
- **Tablas DataTables:** PRD §5.6 con Responsive 3.0.4 CDN, `dom`, dark mode CSS variables

---

## 3. Permisos CBAC

### 3.1 Capabilities del módulo

Estas capabilities **ya están declaradas y registradas** en `Aura_Roles_Manager::get_all_capabilities()` con clave `'electricity'`. El setup del módulo NO las registra de nuevo; se propagan automáticamente al rol `administrator` vía `ensure_admin_capabilities()` en cada `admin_init`.

Sin embargo, se amplían con dos capabilities nuevas (`settings` y `audit`) que se deben **agregar** en `class-roles-manager.php`:

| Capability | Ya existe | Descripción |
|-----------|-----------|-------------|
| `aura_electric_reading_create` | ✅ | Registrar lecturas del contador |
| `aura_electric_reading_edit_own` | ✅ | Editar lecturas propias |
| `aura_electric_reading_edit_all` | ✅ | Editar cualquier lectura |
| `aura_electric_reading_delete` | ✅ | Eliminar lecturas erróneas |
| `aura_electric_view_dashboard` | ✅ | Ver dashboard de consumo (admin) |
| `aura_electric_view_charts` | ✅ | Ver gráficos de tendencias |
| `aura_electric_alerts_receive` | ✅ | Recibir alertas de consumo alto |
| `aura_electric_thresholds_config` | ✅ | Configurar umbrales de alerta |
| `aura_electric_export` | ✅ | Exportar datos a CSV/Excel |
| `aura_electric_settings` | ➕ Agregar | ⭐ Configurar ajustes globales del módulo |
| `aura_electric_audit` | ➕ Agregar | ⭐ Ver log de auditoría del módulo |
| `aura_electric_integrate_finance` | ➕ Agregar | ⭐ Vincular transacciones con categorías financieras |

> ⭐ = Capabilities administrativas sensibles. Solo para administradores.

#### Cambio requerido en `class-roles-manager.php`

```php
// Dentro del array 'electricity' en get_all_capabilities():
'aura_electric_settings' => __('Configurar ajustes del módulo de electricidad', 'aura-suite'),
'aura_electric_audit'    => __('Ver auditoría del módulo de electricidad', 'aura-suite'),
'aura_electric_integrate_finance' => __('Integrar electricidad con categorías financieras', 'aura-suite'),
```

### 3.2 Acceso frontend para todos los tipos de usuario

Para que estudiantes, instructores y staff puedan ver datos de su área sin acceso al admin de WordPress, se usa un **shortcode público** `[aura_electric_panel area_id="X"]`. El permiso de acceso frontal se controla con `aura_electric_view_dashboard` asignado desde CBAC.

**Lógica de visibilidad por área:**
- `aura_electric_view_dashboard` sin área específica → ve solo el medidor/área asignada al usuario (vía `wp_aura_area_users`)
- `aura_electric_view_dashboard` + `aura_electric_view_charts` → ve el dashboard completo con gráficos históricos
- Admin / quien tiene acceso a todas las áreas → puede seleccionar cualquier medidor

### 3.3 Mapping CBAC — Perfiles típicos

| Perfil | Capabilities asignadas |
|-------|------------------------|
| **Administrador del sistema** | Todas las caps `aura_electric_*` |
| **Supervisor de instalaciones** | `reading_create`, `edit_own`, `edit_all`, `view_dashboard`, `view_charts`, `alerts_receive`, `thresholds_config`, `export` |
| **Técnico de mantenimiento** | `reading_create`, `edit_own`, `view_dashboard`, `alerts_receive` |
| **Instructor / Staff** | `view_dashboard`, `view_charts`, `alerts_receive` |
| **Estudiante** | `view_dashboard` (solo lectura del área asignada, vía shortcode frontend) |
| **Contador / Analista** | `view_dashboard`, `view_charts`, `export` |

---

## 4. Base de Datos

> Usar `dbDelta()` para toda la creación de tablas.
> NO crear tablas de áreas ni de usuarios — ya existen.

### 4.1 `wp_aura_electric_meters` (medidores)

Un medidor representa un contador físico de electricidad. Una organización puede tener múltiples medidores (edificio principal, sede secundaria, etc.). Cada medidor puede estar asociado a un área específica.

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_electric_meters` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(150)    NOT NULL COMMENT 'Ej: Edificio Principal, Cancha, Bodega',
    `code`         VARCHAR(50)     NOT NULL COMMENT 'Código interno o número de contrato',
    `area_id`      BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK → wp_aura_areas.id (NULL = global)',
    `location`     VARCHAR(200)    DEFAULT NULL COMMENT 'Descripción física del medidor',
    `provider`     VARCHAR(150)    DEFAULT NULL COMMENT 'Empresa prestadora del servicio',
    `contract_no`  VARCHAR(100)    DEFAULT NULL,
    `api_key`      VARCHAR(64)     DEFAULT NULL COMMENT 'Clave para envío IoT/API',
    `cost_per_kwh` DECIMAL(10,4)   NOT NULL DEFAULT 0.1200 COMMENT 'Tarifa base por kWh',
    `alert_threshold_daily`   DECIMAL(10,2) DEFAULT NULL COMMENT 'kWh/día — dispara alerta',
    `alert_threshold_monthly` DECIMAL(10,2) DEFAULT NULL COMMENT 'kWh/mes — dispara alerta',
    `notes`        TEXT            DEFAULT NULL,
    `active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`   BIGINT UNSIGNED NOT NULL,
    `created_at`   DATETIME        NOT NULL,
    `updated_at`   DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`),
    KEY `idx_area`   (`area_id`),
    KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 `wp_aura_electric_readings` (lecturas)

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_electric_readings` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `meter_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK → wp_aura_electric_meters.id',
    `reading_date`    DATE            NOT NULL COMMENT 'Fecha de toma de lectura',
    `reading_kwh`     DECIMAL(12,3)   NOT NULL COMMENT 'Valor acumulado del contador en kWh',
    `consumption_kwh` DECIMAL(12,3)   DEFAULT NULL COMMENT 'Calculado: lectura_actual - lectura_anterior',
    `cost_per_kwh`    DECIMAL(10,4)   NOT NULL DEFAULT 0.1200,
    `cost_total`      DECIMAL(12,2)   DEFAULT NULL COMMENT 'consumption_kwh × cost_per_kwh',
    `source`          ENUM('manual','api','iot') NOT NULL DEFAULT 'manual',
    `photo_url`       VARCHAR(500)    DEFAULT NULL COMMENT 'URL foto del medidor (wp_media)',
    `notes`           TEXT            DEFAULT NULL,
    `recorded_by`     BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK → WP user ID (NULL si es IoT)',
    `created_at`      DATETIME        NOT NULL,
    `updated_at`      DATETIME        DEFAULT NULL,
    `deleted`         TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_meter_date` (`meter_id`, `reading_date`),
    KEY `idx_meter`   (`meter_id`),
    KEY `idx_date`    (`reading_date`),
    KEY `idx_source`  (`source`),
    KEY `idx_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3 `wp_aura_electric_audit` (auditoría)

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_electric_audit` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operation`   VARCHAR(60)     NOT NULL COMMENT 'Ej: reading_created, meter_deleted',
    `entity_type` VARCHAR(30)     DEFAULT NULL COMMENT 'meter | reading',
    `entity_id`   BIGINT UNSIGNED DEFAULT NULL,
    `user_id`     BIGINT UNSIGNED DEFAULT NULL,
    `ip_address`  VARCHAR(45)     DEFAULT NULL,
    `user_agent`  VARCHAR(300)    DEFAULT NULL,
    `details`     JSON            DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_operation` (`operation`),
    KEY `idx_entity`    (`entity_type`, `entity_id`),
    KEY `idx_user`      (`user_id`),
    KEY `idx_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Estructura de Archivos del Módulo

```
modules/electricity/
├── class-electric-module.php          ← Singleton principal, inicializa todo
├── class-electric-setup.php           ← Crea tablas (dbDelta). DB_VERSION + needs_update().
│                                         Elimina el CPT legacy al migrar.
├── class-electric-meter-manager.php   ← CRUD de medidores
├── class-electric-reading-manager.php ← CRUD de lecturas + cálculo de consumo
├── class-electric-alerts.php         ← Verificar umbrales y disparar notificaciones (adaptar stub)
├── class-electric-reports.php        ← Exportación CSV/Excel
├── class-electric-audit-manager.php  ← Log de auditoría del módulo
├── class-electric-frontend.php       ← Shortcodes y assets para vista pública
│
├── api/
│   ├── class-electric-rest-readings.php   ← Endpoints lecturas (GET/POST mantenido desde stub)
│   ├── class-electric-rest-meters.php     ← Endpoints medidores
│   └── class-electric-rest-stats.php      ← Endpoints dashboard/consumo/KPIs
│
├── admin/
│   ├── class-electric-admin.php           ← Registro de menús WP Admin
│   └── views/
│       ├── page-dashboard.php             ← Dashboard con KPIs y Chart.js
│       ├── page-readings.php              ← Tabla de lecturas (DataTables Responsive)
│       ├── page-meters.php                ← Gestión de medidores
│       ├── page-reports.php               ← Reportes y exportación
│       ├── page-audit.php                 ← Log de auditoría
│       └── page-settings.php             ← Configuración del módulo
│
└── assets/
    ├── css/
    │   └── electric-admin.css             ← Estilos con CSS variables + dark mode
    └── js/
        ├── electric-admin.js              ← Scripts generales
        ├── electric-dashboard.js          ← Chart.js: consumo diario/mensual, comparativa
        └── electric-readings.js           ← DataTable lecturas, modal nueva lectura
```

---

## 6. Menú WordPress Admin

El módulo tiene su propio menú principal (igual que Vehículos), **no colgado de Aura Suite**:

```
⚡ Electricidad                     [aura-electricity]
  ├── Dashboard                     [aura-electricity]           → aura_electric_view_dashboard
  ├── Lecturas                      [aura-electricity-readings]  → aura_electric_reading_create
  ├── Medidores                     [aura-electricity-meters]    → aura_electric_settings
  ├── Reportes                      [aura-electricity-reports]   → aura_electric_export
  ├── Auditoría                     [aura-electricity-audit]     → aura_electric_audit
  └── Configuración                 [aura-electricity-settings]  → aura_electric_settings
```

---

## 7. Fases de Implementación

### FASE 1 — Infraestructura Base

**Objetivo:** Crear clases de setup, tablas custom, menú admin y migración del stub CPT.

**Items:**

1. Crear `class-electric-module.php` como singleton con método `init()` que carga todas las demás clases.
2. Crear `class-electric-setup.php`:
   - Definir constante `AURA_ELECTRIC_DB_VERSION = '1.0.0'`
   - Método `run_migrations()` con `dbDelta()` para las 3 tablas
   - Método `needs_update()` comparando con opción `aura_electric_db_version`
   - Al instalar: detectar si existen lecturas en CPT legacy y migrarlas a `wp_aura_electric_readings` creando un medidor `default` automáticamente
   - Al migrar: **no eliminar** el CPT `aura_electric_reading` de inmediato — desregistrarlo silenciosamente (omitir `register_post_type`). Eliminar los datos CPT 90 días después mediante WP Cron.
3. Crear `class-electric-admin.php` con `register_menus()` usando `add_menu_page` + `add_submenu_page`.
4. Eliminar registros de `add_action('init', register_post_type...)` del CPT stub.
5. Actualizar `aura-business-suite.php`: reemplazar `require_once` del stub por el nuevo `class-electric-module.php`.

**Entregables:**
- Menú "⚡ Electricidad" visible para administradores
- Tablas creadas al activar el plugin
- Migración automática de datos CPT existentes

---

### FASE 2 — Gestión de Medidores

**Objetivo:** CRUD completo de medidores desde la vista admin.

**Items:**

1. Crear `class-electric-meter-manager.php`:
   - `get_all_meters($args)` — listado con filtros (area_id, active, search)
   - `get_meter($id)` — obtener medidor por ID
   - `create_meter($data)` — insertar con validación (code único, area_id válido)
   - `update_meter($id, $data)` — actualizar
   - `delete_meter($id)` — soft delete (`active = 0`). Impedir si tiene lecturas registradas
   - `generate_api_key($meter_id)` — generar o regenerar clave IoT de 32 chars (`wp_generate_password(32, false)`)

2. Crear `admin/views/page-meters.php`:
   - Tabla DataTables Responsive (PRD §5.6 estándar): columnas Nombre, Código, Área, Tarifa kWh, Umbral Diario, Umbral Mensual, Estado, Acciones
   - Modal para crear/editar medidor (campos: nombre, código, área, empresa, nro. contrato, tarifa, umbrales de alerta, notas)
   - Botón "Generar clave IoT" (copy to clipboard, oculta por seguridad)
   - Foto del medidor: botón selector desde biblioteca de medios WordPress
   - Dark mode con CSS variables `--em-*` y selectores `html[data-wp-dark-mode-scheme="dark"]` + `body.admin-color-midnight/coffee`

3. Registrar handlers AJAX:
   - `aura_electric_meter_save` (create/update)
   - `aura_electric_meter_delete`
   - `aura_electric_meter_generate_key`

**Validaciones:**
- `code` debe ser único en la tabla
- `area_id` debe existir en `wp_aura_areas` si se provee
- `cost_per_kwh` ≥ 0
- `alert_threshold_daily` y `alert_threshold_monthly` deben ser 0 o positivos

---

### FASE 3 — Registro de Lecturas

**Objetivo:** CRUD de lecturas, cálculo automático de consumo y tabla principal del módulo.

**Items:**

1. Crear `class-electric-reading-manager.php`:
   - `get_readings($args)` — listado con filtros (meter_id, date_from, date_to, source, deleted)
   - `get_reading($id)`
   - `create_reading($data)` — insertar y calcular automáticamente `consumption_kwh` y `cost_total`
     - `consumption_kwh` = `reading_kwh` − `reading_kwh` de la lectura anterior del mismo medidor
     - `cost_total` = `consumption_kwh × cost_per_kwh`
     - Validar: `reading_kwh` ≥ lectura anterior del mismo medidor (contador acumulativo)
     - Si es la primera lectura del medidor, `consumption_kwh = 0`
   - `update_reading($id, $data)` — recalcular `consumption_kwh` y `cost_total`; también recalcular la lectura siguiente si existe (cascade update)
   - `soft_delete_reading($id)` — `deleted = 1`; recalcular la lectura siguiente si existe
   - `get_last_reading($meter_id, $exclude_id = null)` — obtener la lectura más reciente válida

2. Crear `admin/views/page-readings.php`:
   - Selector de medidor en la parte superior (si hay más de uno) para filtrar la tabla
   - Tabla DataTables Responsive — columnas: Fecha, Medidor, Lectura (kWh), Consumo (kWh), Costo, Fuente (Manual/API/IoT), Foto, Registrado por, Acciones
   - Botón "+ Nueva Lectura" abre modal lateral con:
     - Selector de medidor (dropdown)
     - Fecha (date picker, default: hoy)
     - Lectura actual (kWh) — campo numérico con mínimo = última lectura del medidor
     - Tarifa por kWh (pre-cargada del medidor, editable)
     - Notas opcionales
     - Foto del medidor: selector de biblioteca de medios
     - Vista previa en vivo de consumo calculado y costo estimado
   - Columna con ícono de fuente (🖱 Manual, 🔌 API, 📡 IoT)
   - Acciones por fila: Editar | Ver foto | Eliminar (solo si tiene cap `edit_own` o `edit_all`)

3. Registrar handlers AJAX:
   - `aura_electric_reading_save`
   - `aura_electric_reading_delete`
   - `aura_electric_reading_get_last` — devuelve la última lectura del medidor (para la vista previa del modal)

---

### FASE 4 — Dashboard con KPIs y Gráficos

**Objetivo:** Panel visual con consumo actual, tendencias y alertas de estado.

**Items:**

1. Crear `admin/views/page-dashboard.php`:
   - **KPI cards** (4 tarjetas principales):
     - ⚡ Consumo del mes (kWh)
     - 💰 Costo estimado del mes ($)
     - 📊 Consumo diario promedio (kWh)
     - 🔥 Pico diario del mes (kWh)
   - Si hay múltiples medidores: selector de medidor arriba + opción "Todos los medidores"
   - **Gráfico 1 — Consumo diario:** Chart.js, tipo `bar`, últimos 30 días. Línea de umbral si está configurada.
   - **Gráfico 2 — Comparativa mensual:** Chart.js, tipo `line`, últimos 12 meses, varios medidores superpuestos
   - **Tabla de últimas lecturas:** 5 últimas entradas del medidor/medidores seleccionados
   - **Panel de alertas activas:** badge rojo si algún medidor supera su umbral diario o mensual

2. Crear `electric-dashboard.js`:
   - Inicializar Chart.js al cargar la página
   - Llamada AJAX/REST a `/wp-json/aura/v1/electricity/stats?meter_id=X&period=30d`
   - Actualización automática cada 5 minutos con `setInterval` (configurable)
   - Destruir y re-renderizar charts al cambiar de medidor en el selector

3. Assets:
   - Chart.js se carga desde CDN solo en la página del dashboard: `https://cdn.jsdelivr.net/npm/chart.js`
   - Los estilos del dashboard usan las mismas CSS variables `--em-*`

---

### FASE 5 — Alertas y Notificaciones

**Objetivo:** Disparar notificaciones cuando el consumo supera los umbrales configurados.

**Items:**

1. Adaptar/reescribir `class-electric-alerts.php` (actualmente es stub):
   - `check_all_meters()` — recorrer todos los medidores activos y evaluar sus umbrales
   - `check_meter_threshold($meter_id)` — comparar consumo del día/mes vs umbral del medidor
   - `send_alert($meter_id, $type, $current, $threshold)` — componer notificación y enviar via `Aura_Notifications`
   - Hook: `add_action('aura_daily_electricity_alerts', [__CLASS__, 'check_all_meters'])` — cron diario
   - Al crear/actualizar una lectura: verificar umbral inmediatamente si supera el límite diario

2. Destinatarios de la alerta:
   - Todos los usuarios con capability `aura_electric_alerts_receive` asignada vía CBAC
   - El mensaje incluye: nombre del medidor, área, consumo actual, umbral configurado, enlace al dashboard

3. Template de notificación:
   - **Asunto:** `⚡ Alerta: Consumo alto en {nombre_medidor} — {fecha}`
   - **Cuerpo:** tabla con consumo diario/mensual vs umbral, enlace directo al dashboard del medidor

---

### FASE 6 — Reportes y Exportación

**Objetivo:** Exportar lecturas y resúmenes de consumo a CSV/Excel.

**Items:**

1. Crear `class-electric-reports.php`:
   - `export_readings_csv($args)` — exportar lecturas filtradas
   - `get_monthly_summary($meter_id, $year)` — resumen mes a mes
   - `get_annual_projection($meter_id)` — proyección de costo anual basado en promedio de kWh

2. Crear `admin/views/page-reports.php`:
   - Filtros: medidor, rango de fechas, fuente (manual/api/iot)
   - Vista previa de tabla con totales por período
   - Botón "Exportar CSV" y "Exportar Excel"
   - Gráfico de barras: consumo mensual del año seleccionado

---

### FASE 7 — Shortcode Frontend para Todos los Usuarios

**Objetivo:** Permitir que estudiantes, instructores y staff vean datos de consumo del área asignada desde cualquier página pública de WordPress, sin acceso al admin.

**Items:**

1. Crear `class-electric-frontend.php`:
   - Shortcode `[aura_electric_panel]` — atributos: `meter_id`, `area_id`, `period` (default: `30`)
   - Shortcode `[aura_electric_widget]` — versión compacta: solo el consumo del mes + variación %

2. Lógica de acceso en `shortcode_panel()`:
   ```
   - Si usuario no tiene aura_electric_view_dashboard → mensaje "Sin acceso"
   - Si tiene la cap y no se pasó meter_id/area_id → buscar medidores del área asignada al usuario
     vía wp_aura_area_users JOIN wp_aura_electric_meters WHERE area_id = área_usuario
   - Si se pasó area_id explícito → validar que el usuario pertenezca a esa área o tenga view_all
   ```

3. Output del shortcode principal `[aura_electric_panel]`:
   - 3 KPIs: Consumo del mes (kWh) · Costo estimado · Promedio diario
   - Mini-gráfico Chart.js de barras: últimos 30 días
   - Tabla con últimas 10 lecturas (fecha, consumo, costo, fuente)
   - Responsive: en móvil, gráfico se colapsa y solo se muestran KPIs

4. Enqueue de assets frontend:
   - Solo cargar en páginas con el shortcode (verificar con `has_shortcode`)
   - CSS: `assets/css/electric-frontend.css`
   - JS: `assets/js/electric-frontend.js` + Chart.js CDN

5. AJAX público (sin login) — solo para `[aura_electric_widget]` embebido en páginas de acceso libre:
   - `wp_ajax_nopriv_aura_electric_widget_data` — devuelve KPIs del medidor/área (solo datos públicos, sin costos)

---

### FASE 8 — API REST e Integración IoT

**Objetivo:** Permitir registrar lecturas desde dispositivos externos (microcontroladores, sensores IoT, scripts automatizados).

**Items:**

1. Crear `api/class-electric-rest-readings.php` (reemplaza stub):
   - `POST /aura/v1/electricity/readings` — crear lectura
     - Auth: `api_key` en header `X-Aura-API-Key` o body (vinculada al medidor)
     - Body: `{ meter_id, reading_kwh, reading_date (opcional, default: today), cost_per_kwh (opcional) }`
     - Respuesta: `{ success, reading_id, consumption_kwh, cost_total, alert_triggered }`
   - `GET /aura/v1/electricity/readings` — listar lecturas (requiere nonce WP)
   - `GET /aura/v1/electricity/readings/{id}` — detalle

2. Crear `api/class-electric-rest-meters.php`:
   - `GET /aura/v1/electricity/meters` — listar medidores (requiere `aura_electric_view_dashboard`)
   - `GET /aura/v1/electricity/meters/{id}` — detalle

3. Crear `api/class-electric-rest-stats.php`:
   - `GET /aura/v1/electricity/stats` — KPIs del dashboard
     - Params: `meter_id` (o 0 = todos), `period` (7d, 30d, 90d, 1y)
     - Respuesta: `{ monthly_kwh, monthly_cost, avg_daily_kwh, peak_daily_kwh, readings_count, labels[], values[] }`

4. Seguridad de la API IoT:
   - La `api_key` se vincula al medidor en la tabla `wp_aura_electric_meters.api_key`
   - Cada medidor tiene su propia clave — no es global
   - Rate limit: máximo 100 lecturas por clave por hora (via transient `aura_electric_rate_{hash(key)}`)
   - La clave se puede regenerar desde la interfaz del medidor

---

### FASE 9 — Integración con Módulo Finanzas (Sistema de Categorías Compartidas)

**Objetivo:** Vincular automáticamente el consumo eléctrico con categorías financieras para unificar gastos en el dashboard financiero.

**Contexto:** A partir de v1.2 de Finanzas, las categorías soportan un campo JSON `integration_modules` que especifica qué módulos pueden usar esa categoría. Esto elimina duplicación y crea un sistema unified de categorías.

**Items:**

1. **Configuración de Categoría Financiera (Lado Finanzas):**
   - Cuando se crea/edita una categoría en **Finanzas → Categorías**, una sección "Integración con Módulos" permite marcar:
     - ☐ Vehículos
     - ☐ Inventario
     - ☐ Estudiantes
     - ☐ Biblioteca
     - ☑ **Electricidad** ← Nuevo
   - Se guarda como JSON: `integration_modules: ["electricity"]`

2. **Nuevo campo en `wp_aura_electric_meters`:**
   ```sql
   `finance_category_id` BIGINT UNSIGNED DEFAULT NULL 
     COMMENT 'FK → wp_aura_finance_categories.id para auto-crear transacciones'
   ```

3. **Nueva opción en Configuración del módulo Electricidad:**
   - Crear `admin/views/page-settings.php` si no existe
   - Campos:
     - **"Categoría Financiera por Defecto"** → Selector de categorías `WHERE integration_modules JSON_CONTAINS("electricity")`
     - **"Crear transacciones automáticas"** → Toggle (default: OFF)
     - **"Usuario para transacciones automáticas"** → Selector (default: Admin del sitio)
     - **"Monto mínimo para crear transacción"** → Input número (ej: 10.00 para evitar ruido de lecturas pequeñas)
     - Guardar en opciones de WP: `aura_electric_finance_category_id`, `aura_electric_auto_transactions`, etc.

4. **Lógica de creación automática de transacciones:**
   - En `class-electric-reading-manager.php`, método `close_period()` (nuevo):
     ```php
     /**
      * Cerrar período mensual y crear transacción si está configurado
      */
     public function close_period($meter_id, $year, $month) {
         // Calcular consumo del mes
         $consumption = $this->get_monthly_consumption($meter_id, $year, $month);
         $cost = $consumption * $this->get_meter_cost_per_kwh($meter_id);
         
         // Si está activa la integración y el monto es suficiente
         if (get_option('aura_electric_auto_transactions') === 'yes' && $cost >= floatval(get_option('aura_electric_min_amount', 0))) {
             // Crear transacción en Finanzas
             do_action('aura_electric_create_finance_transaction', [
                 'meter_id' => $meter_id,
                 'month' => $month,
                 'year' => $year,
                 'consumption_kwh' => $consumption,
                 'cost' => $cost,
                 'category_id' => get_option('aura_electric_finance_category_id'),
             ]);
         }
     }
     ```

5. **Hook para creación de transacción:**
   - En `modules/electricity/class-electric-finance-integration.php` (nueva clase):
     ```php
     add_action('aura_electric_create_finance_transaction', function($data) {
         if (!current_user_can('aura_electric_integrate_finance')) {
             return;
         }
         
         $category = Aura_Financial_Categories::get_category($data['category_id']);
         if (!$category || !$category->is_active) {
             return; // Categoría no válida
         }
         
         $transaction_data = [
             'category_id' => $data['category_id'],
             'amount' => $data['cost'],
             'type' => 'expense',
             'date' => date('Y-m-t', mktime(0, 0, 0, $data['month'], 1, $data['year'])), // Último día del mes
             'description' => sprintf(
                 __('Electricidad — %s — %s/%s', 'aura-suite'),
                 $this->get_meter_name($data['meter_id']),
                 str_pad($data['month'], 2, '0', STR_PAD_LEFT),
                 $data['year']
             ),
             'status' => 'pending', // Para revisar antes de aprobar
             'created_by' => intval(get_option('aura_electric_finance_user_id', 1)),
             'source_module' => 'electricity',
             'source_meter_id' => $data['meter_id'],
         ];
         
         // Usar la clase Financial_Transaction de Finanzas
         Aura_Financial_Transaction::create($transaction_data);
     });
     ```

6. **Auditoría:**
   - Registrar en `wp_aura_electric_audit`: "transaction_created", meter_id, amount
   - Registrar en `wp_aura_finance_audit` que la transacción viene de "electricity" module

---

### FASE 10 — Auditoría y Configuración

**Objetivo:** Log completo de acciones y pantalla de configuración global.

**Items:**

1. Crear `class-electric-audit-manager.php`:
   - `log($operation, $entity_type, $entity_id, $details)` — insertar en `wp_aura_electric_audit`
   - Llamar desde `create_reading`, `update_reading`, `soft_delete_reading`, `create_meter`, `update_meter`, `delete_meter`

2. Crear `admin/views/page-audit.php`:
   - Tabla DataTables Responsive: Fecha, Operación, Entidad, Usuario, IP, Detalles
   - Filtros: operación, usuario, rango de fechas

3. Crear `admin/views/page-settings.php`:
   - **General:** tarifa por kWh por defecto, moneda, formato de fecha
   - **Alertas:** email de notificación adicional (CC), días entre alertas del mismo medidor
   - **Integración Finanzas:** toggle + categoría + usuario
   - **API / IoT:** ver todas las claves activas (nombre del medidor + primeros 8 chars de la clave)
   - **Mantenimiento:** botón "Migrar datos del CPT legacy ahora", botón "Vaciar log de auditoría (> 1 año)"

---

## 8. Estándar de Tablas (PRD §5.6)

Todas las tablas DataTables del módulo siguen el estándar global de Aura Suite:

```javascript
$('#tabla').DataTable({
    responsive: true,
    searching: false,
    dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
    pageLength: 20,
    language: { /* español */ },
    // Columna de imagen: al pasar el mouse → tooltip con imagen ampliada
});
```

**CSS variables del módulo** (prefijo `--em-*`):

```css
#aura-electric-app {
    --em-bg:        #f9fafb;
    --em-surface:   #ffffff;
    --em-border:    #e5e7eb;
    --em-text:      #111827;
    --em-muted:     #6b7280;
    --em-accent:    #f59e0b;  /* amarillo eléctrico */
    --em-input-bg:  #ffffff;
}
/* Dark mode */
html[data-wp-dark-mode-scheme="dark"] #aura-electric-app,
body.admin-color-midnight #aura-electric-app,
body.admin-color-coffee   #aura-electric-app {
    --em-bg:       #1e1e2e;
    --em-surface:  #2a2a3e;
    --em-border:   #3f3f5f;
    --em-text:     #e2e8f0;
    --em-muted:    #94a3b8;
    --em-input-bg: #2a2a3e;
}
```

---

## 9. API REST — Resumen de Endpoints

| Método | Ruta | Capability requerida | Descripción |
|--------|------|---------------------|-------------|
| `POST` | `/aura/v1/electricity/readings` | `api_key` del medidor | Registrar lectura (IoT/API) |
| `GET` | `/aura/v1/electricity/readings` | `view_dashboard` | Listar lecturas |
| `GET` | `/aura/v1/electricity/readings/{id}` | `view_dashboard` | Detalle de lectura |
| `GET` | `/aura/v1/electricity/meters` | `view_dashboard` | Listar medidores |
| `GET` | `/aura/v1/electricity/meters/{id}` | `view_dashboard` | Detalle de medidor |
| `GET` | `/aura/v1/electricity/stats` | `view_dashboard` | KPIs y series para gráficos |

**Headers de autenticación:**
- Admin WP: `X-WP-Nonce: {nonce_value}`
- IoT / API externa: `X-Aura-API-Key: {meter_api_key}`

---

## 10. Esquema de Integración con el Plugin Principal

### Registro en `aura-business-suite.php`

```php
// Módulo Electricidad
require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electric-module.php';
Aura_Electric_Module::init();
```

### Registro en `class-roles-manager.php`

Agregar dentro de `get_all_capabilities()` bajo el key `'electricity'`:

```php
'aura_electric_settings' => __('Configurar ajustes del módulo de electricidad', 'aura-suite'),
'aura_electric_audit'    => __('Ver auditoría del módulo de electricidad', 'aura-suite'),
```

### Dashboard principal (`templates/main-dashboard.php`)

Al completar la implementación, actualizar:
- `$deployed_modules` → agregar `'electricity'`
- Tarjeta Electricidad → pasar de "Próximamente" a card activa con KPIs (`$ec_monthly_kwh`, `$ec_monthly_cost`, `$ec_meters`, `$ec_alert`)
- Roadmap → badge `✅ Listo`
- System info → `X / 7 (1 en planificación)` (Biblioteca pendiente)

---

## 11. Integración con Módulo CBAC — Gestión de Permisos

El módulo aparece automáticamente en la pantalla **"Gestión de Permisos Granulares (CBAC)"** (`/wp-admin/admin.php?page=aura-permissions`) porque todas sus capabilities están registradas en `Aura_Roles_Manager::get_all_capabilities()` bajo el key `'electricity'`.

El administrador puede asignar cualquier combinación de capabilities `aura_electric_*` a cualquier usuario activo del sistema, incluyendo:
- Administradores
- Supervisores
- Técnicos de mantenimiento
- Instructores
- Staff
- Estudiantes (con acceso limitado al dashboard frontend vía shortcode)

**Flujo típico para un estudiante:**
1. Admin va a CBAC → selecciona el usuario estudiante
2. En el bloque "⚡ ELECTRICIDAD" activa únicamente `aura_electric_view_dashboard`
3. El estudiante, al abrir la página con `[aura_electric_panel]`, ve automáticamente los datos del medidor de su área asignada
4. No puede registrar lecturas ni acceder al admin de WordPress

---

## 12. Notas de Implementación

- **Chart.js** se carga desde CDN solo en páginas del módulo: `https://cdn.jsdelivr.net/npm/chart.js`
- **Recalcúlo en cascada:** al editar o eliminar una lectura intermedia, se debe recalcular el `consumption_kwh` de la lectura inmediatamente posterior. Solo un nivel (no recalcular toda la cadena).
- **Contador acumulativo:** El campo `reading_kwh` es el valor acumulado del contador físico. El consumo se calcula como diferencia entre lecturas consecutivas del mismo medidor. Es el modelo estándar de medidores eléctricos.
- **Primera lectura del medidor:** El `consumption_kwh` es `0` y el `cost_total` es `0` — solo sirve como base de referencia.
- **Foto del medidor:** Usar `wp_ajax_aura_electric_reading_media` para abrir la biblioteca de medios de WordPress. ID del adjunto se guarda; la URL se obtiene con `wp_get_attachment_url()`.
- **IoT API key:** Generada con `wp_generate_password(32, false)` — alfanumérica sin símbolos. Mostrar solo los primeros 8 caracteres en la UI con opción de "Revelar" o "Copiar".
- **Migración del CPT:** Los post_meta `_aura_reading_date`, `_aura_reading_kwh`, `_aura_cost_per_kwh` se mapean directamente a los campos de `wp_aura_electric_readings`. `source = 'manual'`, `meter_id` = ID del medidor creado automáticamente como `default`.
- **Sin vendor nuevo:** No se requieren nuevas dependencias PHP. Chart.js se carga desde CDN en el frontend.

---

## 13. Estado Inicial / Seeding

Al activar el módulo por primera vez (sin datos previos), se crea automáticamente:

```php
// Un medidor por defecto si no existe ninguno
if ( 0 === (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_meters}") ) {
    $wpdb->insert( $table_meters, [
        'name'         => get_option('blogname') . ' — Medidor Principal',
        'code'         => 'MED-001',
        'cost_per_kwh' => 0.12,
        'active'       => 1,
        'created_by'   => get_current_user_id(),
        'created_at'   => current_time('mysql'),
    ] );
}
```

---

## 14. Resumen de Fases

| Fase | Nombre | Prioridad | Dependencias |
|------|--------|-----------|--------------|
| 1 | Infraestructura Base | 🔴 Crítica | — |
| 2 | Gestión de Medidores | 🔴 Crítica | Fase 1 |
| 3 | Registro de Lecturas | 🔴 Crítica | Fases 1, 2 |
| 4 | Dashboard con KPIs y Gráficos | 🟠 Alta | Fase 3 |
| 5 | Alertas y Notificaciones | 🟠 Alta | Fases 3, 4 |
| 6 | Reportes y Exportación | 🟡 Media | Fase 3 |
| 7 | Shortcode Frontend | 🟡 Media | Fases 3, 4 |
| 8 | API REST e Integración IoT | 🟡 Media | Fase 3 |
| 9 | Integración con Finanzas | � **Alta** (Nuevo v1.2) | Fases 3, 4, Finanzas v1.2 |
| 10 | Auditoría y Configuración | 🟠 Alta | Fases 1–3 |
