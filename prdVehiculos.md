# PRD — Módulo de Vehículos (Aura Business Suite)

> **Versión:** 1.1 (corregido) | **Fecha:** Mayo 2026  
> **Contexto:** Este módulo forma parte de Aura Business Suite. El sistema de áreas (`wp_aura_areas`) y el sistema de permisos granulares CBAC (`aura_*` capabilities) **ya existen e implementados**. No se crean roles nuevos ni tablas nuevas de áreas o usuarios.

---

## 1. Resumen Ejecutivo

El **Módulo de Vehículos** permite a organizaciones gestionar su flota vehicular dentro de Aura Suite: registro de vehículos, control de salidas (rental, encargos, mantenimiento u otros), catálogos configurables de destinos/propósitos/gastos, dashboard de indicadores, reportes exportables y auditoría de todas las operaciones.

El módulo se integra directamente con:
- `wp_aura_areas` — Áreas organizacionales ya existentes (se asignan vehículos a áreas)
- `wp_aura_area_users` — Usuarios por área (con rol contextual ya definido)
- Sistema CBAC de Aura Suite — Capabilities `aura_vehicles_*` asignadas vía el gestor de permisos existente

---

## 2. Contexto de Integración con Aura Suite

### 2.1 Lo que ya existe (NO reimplementar)

| Sistema | Tabla / Clase | Estado |
|---------|--------------|--------|
| Áreas organizacionales | `wp_aura_areas` | ✅ Implementado |
| Usuarios por área | `wp_aura_area_users` | ✅ Implementado |
| Gestor de roles/caps | `modules/common/class-roles-manager.php` | ✅ Implementado |
| Sistema de notificaciones | `modules/common/class-notifications.php` | ✅ Implementado |
| Panel admin Aura Suite | `aura-business-suite.php` | ✅ Implementado |
| CPT stub (legacy) | `modules/vehicles/class-vehicle-cpt.php` | ⚠️ Eliminar — se reemplaza por tablas custom |
| Alerts stub | `modules/vehicles/class-vehicle-alerts.php` | ⚠️ Stub — adaptar |
| Reports stub | `modules/vehicles/class-vehicle-reports.php` | ⚠️ Stub — adaptar |

### 2.2 Convenciones del proyecto a respetar

- **Prefijo de tablas:** `wp_aura_` (ej. `wp_aura_vehicles`, `wp_aura_vehicle_trips`)
- **Prefijo de capabilities:** `aura_vehicles_` (ej. `aura_vehicles_create`)
- **Prefijo de opciones:** `aura_vehicles_` (ej. `aura_vehicles_rate_per_km`)
- **Namespace REST:** `/wp-json/aura/v1/vehicles/`
- **Text domain:** `aura-suite`
- **Clases PHP:** prefijo `Aura_Vehicle_*`
- **Archivos:** `class-vehicle-*.php` dentro de `modules/vehicles/`
- **Menú WP Admin:** submódulo bajo el menú principal de Aura Suite

---

## 3. Permisos CBAC

### 3.1 Capabilities del módulo

Estas capabilities ya están declaradas en PRD.md y **ya están registradas** en `Aura_Roles_Manager::get_all_capabilities()`. El setup del módulo NO necesita registrarlas; se propagan automáticamente al rol `administrator` vía `register_all_capabilities()` en la activación del plugin principal y `ensure_admin_capabilities()` en cada `admin_init`:

| Capability | Descripción |
|-----------|-------------|
| `aura_vehicles_view_all` | Ver todos los vehículos (independiente del área) |
| `aura_vehicles_create` | Crear / registrar vehículos |
| `aura_vehicles_edit` | Editar datos de vehículos |
| `aura_vehicles_delete` | Eliminar vehículos (soft delete) |
| `aura_vehicles_exits_create` | Registrar salidas (rental, encargo, mantenimiento, otro) |
| `aura_vehicles_exits_edit_own` | Editar salidas propias en curso |
| `aura_vehicles_exits_edit_all` | Editar cualquier salida |
| `aura_vehicles_km_update` | Actualizar kilometraje del vehículo |
| `aura_vehicles_reports` | Ver reportes y estadísticas |
| `aura_vehicles_alerts` | Recibir alertas de mantenimiento pendiente |
| `aura_vehicles_audit` | ⭐ Ver log de auditoría del módulo |
| `aura_vehicles_settings` | ⭐ Configurar ajustes del módulo de vehículos |

> ⭐ = Capabilities administrativas críticas. Se conceden solo a administradores de flota o administradores del sistema.

### 3.2 Mapping CBAC — Perfiles típicos

> El CBAC de Aura Suite asigna capabilities individualmente por usuario/rol. La tabla siguiente es referencia orientativa para la configuración inicial:

| Perfil Sugerido | Capabilities asignadas |
|----------------|------------------------|
| **Administrador del sistema** | Todas las caps `aura_vehicles_*` (incluye `audit` + `settings`) |
| **Administrador de flota** | `view_all`, `create`, `edit`, `exits_create`, `exits_edit_all`, `km_update`, `reports`, `alerts` |
| **Coordinador de área** | `view_all`, `exits_create`, `exits_edit_own`, `exits_edit_all`, `km_update`, `reports` |
| **Operador / Editor** | `exits_create`, `exits_edit_own`, `km_update` |

### 3.3 Lógica de visibilidad por área

- Un usuario sin `aura_vehicles_view_all` solo ve vehículos asignados a sus áreas (vía `wp_aura_area_users`).
- Un usuario con `aura_vehicles_view_all` ve toda la flota sin importar área.
- Los vehículos sin área asignada son visibles solo para quienes tienen `aura_vehicles_view_all`.

---

## 4. Base de Datos

> Usar `dbDelta()` para toda la creación de tablas.  
> **NO** crear tablas de áreas ni de usuarios — ya existen.

### 4.1 `wp_aura_vehicles`

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_vehicles` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plate`            VARCHAR(20)   NOT NULL,
    `brand`            VARCHAR(50)   NOT NULL,
    `model`            VARCHAR(50)   NOT NULL,
    `year`             SMALLINT UNSIGNED,
    `color`            VARCHAR(30),
    `type`             ENUM('sedan','suv','pickup','van','bus','motorcycle','truck','other') DEFAULT 'sedan',
    `vin`              VARCHAR(17),
    `status`           ENUM('available','rented','maintenance','unavailable') NOT NULL DEFAULT 'available',
    `mileage`          INT UNSIGNED  DEFAULT 0,
    `rate_per_km`      DECIMAL(10,2) DEFAULT 0.00,
    `fuel_type`        ENUM('gasoline','diesel','electric','hybrid','gas') DEFAULT 'gasoline',
    `transmission`     ENUM('manual','automatic') DEFAULT 'manual',
    `notes`            TEXT,
    `photos`           JSON,
    `unavailable_info` JSON COMMENT '{"reason":"","since":"","until":"","notes":""}',
    `transfer_history` JSON COMMENT '[{"from_area_id":0,"to_area_id":0,"date":"","by_user_id":0}]',
    `active`           TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`       DATETIME      NOT NULL,
    `updated_at`       DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plate` (`plate`),
    KEY `idx_status`   (`status`),
    KEY `idx_active`   (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 `wp_aura_vehicle_area` (pivot vehículo-área)

Reutiliza `wp_aura_areas` — solo se necesita la tabla pivot:

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_vehicle_area` (
    `vehicle_id`  BIGINT UNSIGNED NOT NULL,
    `area_id`     BIGINT UNSIGNED NOT NULL,
    `assigned_at` DATETIME    NOT NULL,
    `assigned_by` BIGINT UNSIGNED,
    PRIMARY KEY (`vehicle_id`, `area_id`),
    KEY `idx_area_id`    (`area_id`),
    KEY `idx_vehicle_id` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3 `wp_aura_vehicle_trips` (salidas unificadas)

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_vehicle_trips` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vehicle_id`            BIGINT UNSIGNED NOT NULL,
    `area_id`               BIGINT UNSIGNED DEFAULT NULL,
    `trip_type`             ENUM('rental','errand','maintenance','other') NOT NULL DEFAULT 'errand',
    `status`                ENUM('active','returned','cancelled')         NOT NULL DEFAULT 'active',
    -- Rental
    `client_name`           VARCHAR(150),
    `client_phone`          VARCHAR(20),
    `client_email`          VARCHAR(150),
    `client_document`       VARCHAR(50),
    `rate_per_km`           DECIMAL(10,2)  DEFAULT 0.00,
    -- Encargo / Otro
    `responsible_name`      VARCHAR(150),
    `destination`           VARCHAR(200),
    `purpose`               VARCHAR(200),
    `trip_description`      TEXT,
    -- Mantenimiento
    `maint_subtype`         ENUM('preventive','corrective','inspection'),
    `maint_priority`        ENUM('low','medium','high','urgent'),
    `maint_description`     TEXT,
    `maint_provider`        VARCHAR(150),
    `maint_contact`         VARCHAR(50),
    `maint_estimated_cost`  DECIMAL(10,2)  DEFAULT 0.00,
    `maint_actual_cost`     DECIMAL(10,2)  DEFAULT 0.00,
    `maint_completion_notes` TEXT,
    -- Salida
    `departure_datetime`    DATETIME       NOT NULL,
    `departure_odometer`    INT UNSIGNED   DEFAULT 0,
    `departure_fuel`        TINYINT UNSIGNED DEFAULT 100,
    -- Retorno
    `return_datetime`       DATETIME,
    `return_odometer`       INT UNSIGNED   DEFAULT 0,
    `return_fuel`           TINYINT UNSIGNED,
    `km_traveled`           INT UNSIGNED   DEFAULT 0,
    `total_amount`          DECIMAL(10,2)  DEFAULT 0.00,
    `additional_charges`    DECIMAL(10,2)  DEFAULT 0.00,
    `discounts`             DECIMAL(10,2)  DEFAULT 0.00,
    `total_expenses`        DECIMAL(10,2)  DEFAULT 0.00,
    `expenses_detail`       JSON COMMENT '[{"type":"expense_catalog_id","desc":"","amount":0}]',
    `cancellation_reason`   TEXT,
    -- Metadatos
    `assigned_to`           BIGINT UNSIGNED,
    `created_by`            BIGINT UNSIGNED NOT NULL,
    `created_at`            DATETIME        NOT NULL,
    `updated_at`            DATETIME,
    `deleted`               TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_vehicle`    (`vehicle_id`),
    KEY `idx_area`       (`area_id`),
    KEY `idx_status`     (`status`),
    KEY `idx_type`       (`trip_type`),
    KEY `idx_departure`  (`departure_datetime`),
    KEY `idx_deleted`    (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4 `wp_aura_vehicle_catalogs` (destinos, propósitos y gastos)

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_vehicle_catalogs` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `type`        ENUM('destination','purpose','expense') NOT NULL,
    `name`        VARCHAR(150)  NOT NULL,
    `description` VARCHAR(300),
    `icon`        VARCHAR(50),
    `active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
    `area_id`     BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL = global, ID = específico del área',
    `created_by`  BIGINT UNSIGNED NOT NULL,
    `created_at`  DATETIME      NOT NULL,
    `updated_at`  DATETIME,
    PRIMARY KEY (`id`),
    KEY `idx_type`   (`type`),
    KEY `idx_area`   (`area_id`),
    KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.5 `wp_aura_vehicle_audit`

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_vehicle_audit` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operation`   VARCHAR(60)     NOT NULL,
    `entity_type` VARCHAR(30),
    `entity_id`   BIGINT UNSIGNED,
    `user_id`     BIGINT UNSIGNED,
    `ip_address`  VARCHAR(45),
    `user_agent`  VARCHAR(300),
    `details`     JSON,
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
modules/vehicles/
├── class-vehicle-module.php          ← Singleton principal, inicializa todo
├── class-vehicle-setup.php           ← Crea tablas (dbDelta), seed catalogs. Patrón DB_VERSION + needs_update()
├── class-vehicle-manager.php         ← CRUD lógica de vehículos
├── class-vehicle-trip-manager.php    ← CRUD lógica de salidas/trips
├── class-vehicle-catalog-manager.php ← CRUD catálogos (destinos/propósitos/gastos)
├── class-vehicle-audit-manager.php   ← Log de auditoría del módulo
├── class-vehicle-reports.php         ← Reportes y exportaciones (adaptar stub)
├── class-vehicle-alerts.php          ← Alertas de mantenimiento (adaptar stub)
│
├── api/
│   ├── class-vehicle-rest-vehicles.php   ← Endpoints vehículos
│   ├── class-vehicle-rest-trips.php      ← Endpoints salidas
│   ├── class-vehicle-rest-catalogs.php   ← Endpoints catálogos
│   └── class-vehicle-rest-stats.php      ← Endpoints dashboard/stats
│
├── admin/
│   ├── class-vehicle-admin.php           ← Registro de menús WP Admin
│   └── views/
│       ├── page-dashboard.php
│       ├── page-vehicles.php
│       ├── page-trips.php
│       ├── page-catalogs.php
│       ├── page-reports.php
│       ├── page-audit.php
│       └── page-settings.php
│
└── assets/
    ├── css/
    │   └── vehicle-admin.css
    └── js/
        ├── vehicle-admin.js      ← Scripts generales del módulo
        ├── vehicle-dashboard.js  ← Charts y KPIs
        └── vehicle-trips.js      ← Formulario salidas y check-in
```

---

## 6. API REST

**Namespace:** `/wp-json/aura/v1/`  
**Autenticación:** WordPress nonce (`X-WP-Nonce`) + `is_user_logged_in()`  
**CSRF:** Todos los métodos POST/PUT/PATCH/DELETE requieren nonce válido

### 6.1 Endpoints de Vehículos

```
GET    /aura/v1/vehicles                       — Listar (filtros: status, area_id, search)
POST   /aura/v1/vehicles                       — Crear [aura_vehicles_create]
GET    /aura/v1/vehicles/{id}                  — Ver detalle
PUT    /aura/v1/vehicles/{id}                  — Editar [aura_vehicles_edit]
DELETE /aura/v1/vehicles/{id}                  — Soft delete [aura_vehicles_delete]
POST   /aura/v1/vehicles/{id}/photos           — Subir foto
DELETE /aura/v1/vehicles/{id}/photos           — Eliminar foto
POST   /aura/v1/vehicles/{id}/mark-unavailable — Dar de baja [aura_vehicles_edit]
POST   /aura/v1/vehicles/{id}/restore          — Restaurar [aura_vehicles_edit]
POST   /aura/v1/vehicles/{id}/transfer         — Transferir a otra área [aura_vehicles_edit]
```

### 6.2 Endpoints de Salidas (Trips)

```
GET    /aura/v1/vehicles/trips                 — Listar (filtros: type, status, vehicle_id, area_id, date_from, date_to)
POST   /aura/v1/vehicles/trips                 — Registrar salida [aura_vehicles_exits_create]
GET    /aura/v1/vehicles/trips/{id}            — Ver detalle
PUT    /aura/v1/vehicles/trips/{id}            — Editar salida activa [exits_edit_own o exits_edit_all]
POST   /aura/v1/vehicles/trips/{id}/checkin    — Registrar retorno [exits_edit_all o propietario]
POST   /aura/v1/vehicles/trips/{id}/cancel     — Cancelar [aura_vehicles_exits_edit_all]
DELETE /aura/v1/vehicles/trips/{id}            — Eliminar [aura_vehicles_delete]
```

### 6.3 Endpoints de Catálogos

```
GET    /aura/v1/vehicles/catalogs              — Listar ?type=destination|purpose|expense&area_id=
POST   /aura/v1/vehicles/catalogs              — Crear [aura_vehicles_edit]
PUT    /aura/v1/vehicles/catalogs/{id}         — Editar [aura_vehicles_edit]
DELETE /aura/v1/vehicles/catalogs/{id}         — Eliminar o desactivar [aura_vehicles_edit]
PATCH  /aura/v1/vehicles/catalogs/reorder      — Reordenar drag & drop { ids: [] }
```

### 6.4 Endpoints de Dashboard y Reportes

```
GET    /aura/v1/vehicles/stats                 — KPIs generales [aura_vehicles_view_all]
GET    /aura/v1/vehicles/stats/chart           — Datos gráfica ?type=km_by_vehicle|by_area|monthly&period=7d|30d|90d|year
GET    /aura/v1/vehicles/reports               — Vista previa HTML (DataTables) [aura_vehicles_reports]
POST   /aura/v1/vehicles/reports/export-csv    — Descargar CSV [aura_vehicles_reports]
POST   /aura/v1/vehicles/reports/export-pdf    — Descargar PDF [aura_vehicles_reports]
GET    /aura/v1/vehicles/audit                 — Log auditoría (filtros) [manage_options]
GET    /aura/v1/vehicles/audit/export-csv      — Exportar auditoría CSV [manage_options]
DELETE /aura/v1/vehicles/audit/cleanup         — Limpiar logs antiguos [manage_options]
GET    /aura/v1/vehicles/settings              — Obtener configuración [manage_options]
PUT    /aura/v1/vehicles/settings              — Guardar configuración [manage_options]
```

---

## 7. Módulos Funcionales

### 7.1 Dashboard

**KPIs principales:**
- Vehículos disponibles / en uso / en mantenimiento / no disponibles
- Salidas activas del día
- Mantenimientos en progreso
- Kilometraje total del período
- Ingresos / Costos totales del período

**Gráficas (Chart.js):**
- Doughnut: distribución de estados de flota
- Bar vertical: KM recorridos por vehículo (top 10)
- Bar horizontal: uso de vehículos por área
- Line: actividad mensual (salidas activas por día)
- Bar agrupado: ingresos vs costos por mes

### 7.2 Gestión de Vehículos

**Listado (DataTables):** Placa, Marca/Modelo, Año, Tipo, Estado (badge de color), KM, Área(s) asignada(s), Acciones  
**Acciones por fila:** Ver detalle | Editar | Registrar salida | Dar de baja | Eliminar  
**Filtros:** Estado, Tipo, Área, Búsqueda libre

**Formulario de vehículo:**
- Datos básicos: Placa*, Marca*, Modelo*, Año, Color, Tipo*, VIN
- Operativo: Estado*, Kilometraje*, Tarifa por KM, Combustible*, Transmisión*
- Fotos: upload múltiple (JPG/PNG/WebP, máx 2MB por imagen, máx 10 fotos)
- Notas adicionales
- Área(s) asignadas: selector múltiple de `wp_aura_areas`

**Estados y transiciones:**
```
available → rented         (al registrar salida tipo rental)
available → maintenance    (al registrar salida tipo maintenance)
available → unavailable    (al marcar como dado de baja)
rented    → available      (al registrar retorno)
maintenance → available    (al registrar retorno de mantenimiento)
unavailable → available    (al restaurar)
```

### 7.3 Sistema de Salidas (Trips)

**4 tipos de salida con campos específicos:**

**Rental (alquiler externo):**
- Cliente: nombre*, teléfono, email, documento de identidad
- Tarifa por km (hereda del vehículo, editable)
- Odómetro salida*, nivel de combustible salida
- Al retorno: odómetro retorno*, combustible retorno, cargos adicionales, descuentos → calcula total

**Encargo (errand):**
- Responsable: nombre*, referencia al usuario WP asignado
- Destino (selector del catálogo `destination`), Propósito (selector del catálogo `purpose`)
- Descripción del encargo
- Odómetro salida*, combustible salida
- Al retorno: odómetro retorno*, gastos (selector catálogo `expense` + monto), descripción

**Mantenimiento:**
- Subtipo: preventivo / correctivo / inspección
- Prioridad: baja / media / alta / urgente
- Descripción del trabajo a realizar
- Proveedor: nombre, contacto
- Costo estimado
- Al retorno: costo real, notas de finalización

**Otro:**
- Mismos campos que Encargo pero sin validación de catálogos obligatoria

**Flujo de salida:**
```
1. Seleccionar vehículo (disponible)
2. Seleccionar tipo de salida
3. Completar campos específicos del tipo
4. Ingresar odómetro de salida + nivel de combustible
5. Guardar → vehículo cambia a estado correspondiente
6. [Al volver] Registrar retorno → odómetro + combustible + gastos → cerrar salida
7. Vehículo regresa a "available"
```

### 7.4 Catálogos

Tres catálogos configurables con las mismas operaciones:

| Catálogo | Uso en formularios |
|----------|-------------------|
| **Destinos** | Selector en salidas de tipo encargo/otro |
| **Propósitos** | Selector de motivo en salidas de tipo encargo/otro |
| **Gastos** | Líneas de gastos en retorno de encargo/mantenimiento/otro |

Cada ítem tiene: Nombre*, Descripción, Ícono (Bootstrap Icons), Área (global o específica), Estado (activo/inactivo), Orden (drag & drop).

### 7.5 Reportes

| Tipo | Descripción | Capability |
|------|-------------|-----------|
| `trips` | Listado de salidas con KPIs | `aura_vehicles_reports` |
| `maintenances` | Salidas de mantenimiento con costos | `aura_vehicles_reports` |
| `costs` | Costos consolidados por vehículo/área | `aura_vehicles_reports` |
| `vehicles` | Estado de flota con historial resumen | `aura_vehicles_reports` |
| `mileage` | Kilometraje por vehículo y período | `aura_vehicles_reports` |

**Filtros:** Período (desde/hasta), Área, Vehículo, Tipo de salida  
**Exportación:** CSV (PHP nativo `fputcsv()`) y PDF (mPDF o Dompdf)  
**Vista previa:** tabla HTML interactiva con DataTables

### 7.6 Auditoría

Operaciones que se registran en `wp_aura_vehicle_audit`:

| Categoría | Operaciones |
|-----------|------------|
| Vehículos | `vehicle_create`, `vehicle_update`, `vehicle_delete`, `vehicle_transfer`, `vehicle_mark_unavailable`, `vehicle_restore`, `vehicle_photo_upload`, `vehicle_photo_delete` |
| Salidas | `trip_create`, `trip_update`, `trip_checkin`, `trip_cancel`, `trip_delete` |
| Catálogos | `catalog_create`, `catalog_update`, `catalog_delete`, `catalog_reorder` |
| Sistema | `report_export`, `settings_update`, `audit_cleanup` |

**Filtros de la pantalla:** Operación, Usuario, Rango de fechas, Dirección IP  
**Exportación:** CSV con filtros activos  
**Retención configurable:** días máximos de retención (default 365)

---

## 8. Configuración del Sistema

Opciones guardadas con `update_option()` / `get_option()`:

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `aura_vehicles_business_name` | string | `''` | Nombre que aparece en reportes/PDFs |
| `aura_vehicles_logo` | string (URL) | `''` | Logo para reportes |
| `aura_vehicles_default_color` | string | `'#667eea'` | Color por defecto de nuevas áreas |
| `aura_vehicles_rate_per_km` | decimal | `0.00` | Tarifa por KM global (heredable por vehículo) |
| `aura_vehicles_km_before_maintenance` | int | `10000` | KM acumulados antes de alertar mantenimiento |
| `aura_vehicles_block_with_pending_maint` | bool | `false` | Bloquear salida rental si tiene mantenimiento pendiente |
| `aura_vehicles_allow_editor_maintenance` | bool | `true` | Permitir al operador registrar salidas de mantenimiento |
| `aura_vehicles_allow_editor_reports` | bool | `false` | Permitir al operador ver reportes |
| `aura_vehicles_audit_retention_days` | int | `365` | Días de retención del log de auditoría |
| `aura_vehicles_notification_emails` | JSON array | `[]` | Emails adicionales para alertas |

---

## 9. Seguridad (OWASP)

| Riesgo | Mitigación |
|--------|-----------|
| **Inyección SQL** | Solo `$wpdb->prepare()`, `$wpdb->insert()`, `$wpdb->update()`. Nunca concatenar SQL. |
| **Autenticación rota** | Verificar `is_user_logged_in()` + nonce WordPress en cada petición REST. |
| **XSS** | Sanitizar inputs: `sanitize_text_field()`, `sanitize_email()`, `wp_kses_post()`. Escapar outputs: `esc_html()`, `esc_attr()`. |
| **Control de acceso** | `permission_callback` con `current_user_can()` en cada endpoint REST. Verificar membresía al área antes de operaciones. |
| **Deserialización insegura** | Solo `wp_json_encode()` / `json_decode()`. Nunca `unserialize()`. |
| **Uploads inseguros** | Validar MIME real con `finfo` (no extensión). Máx 2MB. Solo JPG/PNG/WebP. |
| **CSRF** | Nonce `wp_rest` verificado en todos los métodos mutantes. |
| **Logging insuficiente** | Sistema de auditoría propio en `wp_aura_vehicle_audit`. Registrar todas las operaciones sensibles. |

---

## 10. Fases de Implementación

---

### Fase 1 — Fundamentos del Módulo

**Objetivo:** Módulo cargable, tablas creadas, clase setup funcional.

**Archivos a crear/modificar:**

- `modules/vehicles/class-vehicle-setup.php`
  - Clase `Aura_Vehicle_Setup` con constantes `DB_VERSION = '1.1.0'` y `DB_VERSION_OPTION = 'aura_vehicles_db_version'`
  - Método estático `needs_update()`: compara `get_option(DB_VERSION_OPTION)` contra `DB_VERSION`
  - Método estático `create_tables()`: crear las 5 tablas con `dbDelta()` + `update_option(DB_VERSION_OPTION, DB_VERSION)`
  - Método estático `seed_default_catalogs()`: insertar catálogos globales si la tabla está vacía
  - En `init()`: si `needs_update()` es true, registrar hook `admin_init` → `create_tables()`; registrar tamaños de imagen `aura-equipment-full` (800×600) y `aura-equipment-thumb` (220×165) con guard `has_image_size()`
  - **NO registrar capabilities** (ya están en `Aura_Roles_Manager::get_all_capabilities()` y se sincronizan automáticamente)

- `modules/vehicles/class-vehicle-module.php`
  - Singleton con `get_instance()`
  - `init()`: enganchar `admin_menu` para registrar menús; enqueue de assets (`vehicle-admin.css`, `vehicle-admin.js`)
  - Cargar todos los archivos del módulo

- `aura-business-suite.php` (o el loader principal)
  - Incluir `class-vehicle-module.php` y llamar `Aura_Vehicle_Module::get_instance()`
  - Añadir `Aura_Vehicle_Setup::create_tables()` dentro del método `activate()` del plugin (mismo patrón que `Aura_Inventory_Setup::create_tables()`, `Aura_Students_Setup::create_tables()`, etc.)
  - **Eliminar** la llamada a `Aura_Vehicle_CPT::init()` del hook `init` (el CPT stub se reemplaza por tablas custom)

- `modules/vehicles/admin/class-vehicle-admin.php`
  - Registrar el módulo como **menú de nivel superior** en wp-admin (después de Formularios, posición 4.9), con ícono `dashicons-car`
  - Páginas: Dashboard Flota, Salidas, Vehículos, Reportes, Catálogos, Auditoría, Configuración
  - Registrar hook AJAX `aura_vehicle_crop_photo` → `Aura_Vehicle_Manager::ajax_crop_vehicle_photo()`

**Criterios de aceptación:**
- [ ] Plugin se activa sin errores PHP
- [ ] Las 5 tablas existen con las columnas correctas (IDs BIGINT AUTO_INCREMENT, area_id BIGINT UNSIGNED)
- [ ] `Aura_Vehicle_Setup::needs_update()` detecta correctamente la versión y ejecuta `create_tables()` solo cuando es necesario
- [ ] El menú "Vehículos" aparece como menú de nivel superior en wp-admin después de Formularios
- [ ] Las 12 capabilities `aura_vehicles_*` ya existen en `administrator` (vía `Aura_Roles_Manager`)
- [ ] Seed de catálogos globales insertado correctamente (solo si tabla vacía)
- [ ] El CPT `aura_vehicle` ya no se registra (stub eliminado del flujo init)

---

### Fase 2 — CRUD de Vehículos

**Objetivo:** Registro completo de la flota vehicular, incluyendo foto principal con editor de recorte.

**Archivos a crear/modificar:**

- `modules/vehicles/class-vehicle-manager.php`
  - `create(array $data)`: validar, sanitizar, insertar en `wp_aura_vehicles`, log auditoría
  - `update(int $id, array $data)`: idem + validar existencia
  - `delete(int $id)`: soft delete (`active = 0`), verificar que no tenga salidas activas
  - `get(int $id)`: recuperar con sus áreas asignadas, `photo_url` y `photo_thumb_url` via `aura_get_equipment_photo_urls()`
  - `get_list(array $filters)`: listar con filtros (status, area_id, search, paginación); cada registro incluye `photo_url` y `photo_thumb_url`
  - `assign_area(int $vehicle_id, int $area_id)`: insertar en `wp_aura_vehicle_area`
  - `unassign_area(int $vehicle_id, int $area_id)`: eliminar de pivot
  - `mark_unavailable(int $id, array $info)`: cambiar status + guardar `unavailable_info` JSON
  - `restore(int $id)`: revertir a `available`
  - `transfer(int $id, int $from_area, int $to_area)`: actualizar pivot + `transfer_history` JSON
  - `ajax_crop_vehicle_photo()`: AJAX handler (`aura_vehicle_crop_photo`) — recibe `attachment_id` + coordenadas Cropper.js, recorta a 800×600 JPEG q80, genera miniatura 220×165 (`aura-equipment-thumb`), devuelve `attachment_id`, `full_url`, `thumb_url`

- `modules/vehicles/api/class-vehicle-rest-vehicles.php`
  - Registrar todos los endpoints de vehículos
  - `permission_callback` correcto por capability para cada endpoint
  - Validar nonce en mutantes

- `modules/vehicles/admin/views/page-vehicles.php`
  - Tabla HTML (DataTables) — columna "Foto" muestra miniatura preferiendo `photo_thumb_url` (Cropper.js) y con fallback a galería legacy
  - Modal de crear/editar vehículo incluye widget de foto principal:
    - Botón "Seleccionar / Cambiar Foto" → abre Media Library (`wp.media`)
    - Modal de recorte (Cropper.js, proporción 4:3, `aspect_ratio: 4/3`)
    - Preview de miniatura después del recorte
    - Botón "Quitar foto"
  - Encola Cropper.js 1.6.2 CDN + `wp_enqueue_media()`

- `modules/vehicles/assets/js/vehicle-vehicles.js`
  - Inicializar DataTables
  - CRUD via REST API
  - `renderThumb(row)`: usa `row.photo_thumb_url` con fallback a galería legacy
  - wp.media picker + Cropper.js: `openVehCropModal()`, llamada AJAX `aura_vehicle_crop_photo`, actualiza `#veh-photo` + preview

**Esquema DB — `wp_aura_vehicles` (v1.1.0):**

| Columna | Tipo | Descripción |
|---|---|---|
| `photo` | `BIGINT(20) UNSIGNED DEFAULT NULL` | ID del adjunto WP (foto principal Cropper.js) |
| `photos` | `LONGTEXT DEFAULT NULL` | JSON array de nombres de archivo (galería legacy, hasta 10 fotos) |

> `photo` se muestra en el listado y en el detalle del vehículo. `photos` mantiene la galería multi-foto existente.

**Criterios de aceptación:**
- [ ] Crear vehículo con todos sus campos
- [ ] Editar vehículo — cambios se reflejan en la tabla
- [ ] Soft delete — vehículo desaparece del listado activo
- [ ] Seleccionar imagen desde Media Library → editor de recorte 4:3 → miniatura guardada como adjunto WP
- [ ] Foto principal se muestra en la columna de la tabla (miniatura 56×42 px)
- [ ] Campo `photo` se envía y persiste correctamente vía REST API
- [ ] Subir/eliminar fotos de galería legacy (máx 10, máx 2MB, solo JPG/PNG/WebP)
- [ ] Asignar/desasignar áreas al vehículo
- [ ] Dar de baja vehículo (status → `unavailable`) y restaurar
- [ ] Transferir vehículo a otra área — `transfer_history` JSON actualizado
- [ ] Todas las operaciones quedan registradas en `wp_aura_vehicle_audit`
- [ ] Usuario sin `aura_vehicles_view_all` solo ve vehículos de sus áreas

---

### Fase 3 — Sistema de Salidas (Trips)

**Objetivo:** Registro completo del ciclo de vida de salidas vehiculares.

**Archivos a crear/modificar:**

- `modules/vehicles/class-vehicle-trip-manager.php`
  - `create(array $data)`: validar tipo, insertar en `wp_aura_vehicle_trips`, bloquear si vehículo no está `available` (o `available` + `maintenance` según tipo), cambiar status del vehículo, log auditoría
  - `check_in(int $id, array $return_data)`: registrar retorno, calcular `km_traveled`, `total_amount`, restaurar status del vehículo
  - `cancel(int $id, string $reason)`: marcar como cancelado, restaurar status del vehículo
  - `update(int $id, array $data)`: solo salidas `active`, verificar permisos (`edit_own` vs `edit_all`)
  - `delete(int $id)`: soft delete (`deleted = 1`), solo si no está `active`
  - `get_list(array $filters)`: listar con filtros, paginación

- `modules/vehicles/api/class-vehicle-rest-trips.php`
  - Endpoints de salidas con `permission_callback` correcto

- `modules/vehicles/admin/views/page-trips.php`
  - Tabla DataTables de salidas con filtros (tipo, estado, fecha, vehículo, área)
  - Modal de nueva salida (formulario que cambia campos dinámicamente según tipo)
  - Modal de registrar retorno (check-in)
  - Modal de cancelar salida
  - Badges de estado con colores semánticos

- `assets/js/vehicle-trips.js`
  - Lógica del formulario dinámico (mostrar/ocultar campos por tipo)
  - Cálculo de km recorridos y monto total en el check-in (preview antes de guardar)
  - Integración con catálogos (carga destinos/propósitos/gastos via REST)

**Criterios de aceptación:**
- [ ] Registrar salida tipo rental con datos de cliente — vehículo pasa a `rented`
- [ ] Registrar salida tipo encargo — vehículo pasa a `rented`
- [ ] Registrar salida tipo mantenimiento — vehículo pasa a `maintenance`
- [ ] Registrar retorno (check-in) — vehículo vuelve a `available`, KM calculados automáticamente
- [ ] Cancelar salida — vehículo vuelve a `available`
- [ ] Usuario con `exits_edit_own` solo puede editar sus propias salidas activas
- [ ] Usuario con `exits_edit_all` puede editar cualquier salida activa
- [ ] No se puede registrar nueva salida si el vehículo no está `available` (o `maintenance` para tipo maintenance en modo "transferencia a taller")
- [ ] Todas las operaciones quedan en auditoría

---

### Fase 4 — Catálogos

**Objetivo:** Gestión de listas configurables de destinos, propósitos y gastos.

**Archivos a crear/modificar:**

- `modules/vehicles/class-vehicle-catalog-manager.php`
  - `create(array $data)`: sanitizar, verificar duplicados (mismo tipo + nombre + area_id), insertar
  - `update(int $id, array $data)`: idem
  - `delete(int $id)`: si tiene trips asociados, desactivar (`active = 0`); si no, eliminar
  - `reorder(array $ids)`: actualizar `sort_order` en lote
  - `get_list(array $filters)`: listar por tipo y área

- `modules/vehicles/api/class-vehicle-rest-catalogs.php`
  - Endpoints de catálogos

- `modules/vehicles/admin/views/page-catalogs.php`
  - Pestañas Bootstrap: Destinos / Propósitos / Gastos
  - Tabla con drag & drop (Sortable.js)
  - Modal crear/editar con selector de ícono (Bootstrap Icons)
  - Selector de Área: Global vs área específica (selector de `wp_aura_areas`)
  - Filtro por área

**Criterios de aceptación:**
- [ ] Crear / editar / eliminar / desactivar ítems en los 3 catálogos
- [ ] Reordenamiento por drag & drop persiste
- [ ] Ítems globales (`area_id = NULL`) son visibles en todos los formularios de salidas
- [ ] Ítems de área (`area_id = ID del área`) solo aparecen en salidas del área correspondiente
- [ ] No se puede eliminar un ítem con salidas ya registradas — se desactiva

---

### Fase 5 — Dashboard y KPIs

**Objetivo:** Vista de summary analítico con gráficas.

**Archivos a crear/modificar:**

- `modules/vehicles/api/class-vehicle-rest-stats.php`
  - Endpoint `/aura/v1/vehicles/stats`: KPIs (conteos por estado + totales kms/ingresos/costos del período)
  - Endpoint `/aura/v1/vehicles/stats/chart`: datasets para Chart.js según `?type=` y `?period=`
  - Filtrar por áreas del usuario si no tiene `aura_vehicles_view_all`

- `modules/vehicles/admin/views/page-dashboard.php`
  - Grid de tarjetas KPI
  - Contenedores `<canvas>` para cada gráfica
  - Selector de período (7 días, 30 días, 90 días, año actual)
  - Selector de área (si el usuario tiene `view_all`)

- `assets/js/vehicle-dashboard.js`
  - Cargar KPIs y gráficas al inicializar via REST API
  - Actualizar al cambiar período/área sin recargar la página

**Gráficas:**

| ID | Tipo | Datos |
|----|------|-------|
| `fleet-status` | Doughnut | Vehículos por estado |
| `km-by-vehicle` | Bar | KM por vehículo (top 10 del período) |
| `usage-by-area` | Bar horizontal | Salidas por área |
| `monthly-activity` | Line | Salidas activas por día del período |
| `cost-vs-income` | Bar agrupado | Costos de mantenimiento vs ingresos rental por mes |

**Criterios de aceptación:**
- [ ] Dashboard carga en menos de 2 segundos (carga asíncrona via REST)
- [ ] Las 5 gráficas se renderizan correctamente con Chart.js
- [ ] Cambiar período actualiza todos los KPIs y gráficas sin recargar página
- [ ] Usuario sin `view_all` solo ve datos de sus áreas

---

### Fase 6 — Reportes y Exportación

**Objetivo:** Generación de reportes con filtros y exportación a CSV y PDF.

**Archivos a crear/modificar:**

- `modules/vehicles/class-vehicle-reports.php` (adaptar stub existente)
  - Métodos para cada tipo de reporte: `get_trips_report`, `get_maintenances_report`, `get_costs_report`, `get_vehicles_report`, `get_mileage_report`
  - Aplicar filtros: período, área, vehículo, tipo de salida
  - Método `export_csv(array $rows, string $filename)`: headers + `fputcsv()`
  - Método `export_pdf(array $rows, string $title)`: renderizar template HTML con mPDF o Dompdf

- `modules/vehicles/admin/views/page-reports.php`
  - Selector de tipo de reporte
  - Panel de filtros (período, área, vehículo, tipo)
  - Botones: "Vista previa", "Descargar CSV", "Descargar PDF"
  - Área de vista previa (tabla DataTables cargada via REST)

**Criterios de aceptación:**
- [ ] Los 5 tipos de reporte generan datos correctos según filtros
- [ ] Export CSV descarga el archivo con todos los registros filtrados
- [ ] Export PDF descarga PDF con logo, título, filtros aplicados y tabla de datos
- [ ] Cada exportación queda registrada en auditoría (`report_export`)
- [ ] Usuario sin `aura_vehicles_reports` no accede a la página ni a los endpoints

---

### Fase 7 — Auditoría

**Objetivo:** Visualización y exportación del log de operaciones del módulo.

**Archivos a crear/modificar:**

- `modules/vehicles/admin/views/page-audit.php`
  - Panel de filtros: Operación (con `<optgroup>` por categoría), Usuario, Desde/Hasta, IP
  - Tabla DataTables mostrando: Fecha/Hora, Operación (badge), Usuario, Entidad, IP
  - Botón "Expandir detalles" → muestra JSON formateado en accorión/modal
  - Botones: "Exportar CSV", "Limpiar logs antiguos" (modal de confirmación con campo de días)

- `modules/vehicles/api/class-vehicle-rest-stats.php` (ampliar — o endpoint de auditoría en archivo dedicado)
  - Endpoint GET `/aura/v1/vehicles/audit` con filtros + paginación
  - Endpoint GET `/aura/v1/vehicles/audit/export-csv`
  - Endpoint DELETE `/aura/v1/vehicles/audit/cleanup?days=N` (requiere `manage_options`)

**Criterios de aceptación:**
- [ ] Solo usuarios con `manage_options` acceden a la pantalla de auditoría
- [ ] Todos los filtros funcionan correctamente
- [ ] Exportación CSV descarga los registros filtrados
- [ ] Limpieza de logs elimina registros anteriores a N días
- [ ] Cada operación del módulo (Fases 1-6) tiene al menos una entrada en la auditoría

---

### Fase 8 — Alertas de Mantenimiento

**Objetivo:** Notificar cuando un vehículo supera el umbral de kilometraje sin mantenimiento.

**Archivos a crear/modificar:**

- `modules/vehicles/class-vehicle-alerts.php` (adaptar stub existente)
  - `check_maintenance_due()`: comparar `mileage` de cada vehículo contra el último mantenimiento registrado + el umbral configurado (`aura_vehicles_km_before_maintenance`)
  - `send_alerts(array $vehicles)`: usar `class-notifications.php` existente para enviar email o alerta interna a usuarios con `aura_vehicles_alerts`
  - Registrar en `wp_aura_vehicle_audit` cada alerta enviada

- Hook en `class-vehicle-trip-manager.php`
  - Al hacer check-in, actualizar `mileage` del vehículo y revisar si aplica alerta

- Tarea `wp_schedule_event` diaria
  - Ejecutar `check_maintenance_due()` una vez al día al inicializar el módulo

**Criterios de aceptación:**
- [ ] Al hacer check-in de una salida, el kilometraje del vehículo se actualiza
- [ ] Si el vehículo supera el umbral configurado, se genera una alerta
- [ ] Solo usuarios con `aura_vehicles_alerts` reciben las notificaciones
- [ ] Si `aura_vehicles_block_with_pending_maint = true`, no se puede registrar salida tipo `rental` para vehículos con mantenimiento pendiente
- [ ] Alertas redundantes no se duplican (no enviar la misma alerta dos veces el mismo día)

---

### Fase 9 — Configuración del Módulo

**Objetivo:** Página de ajustes con WordPress Settings API.

**Archivos a crear/modificar:**

- `modules/vehicles/admin/views/page-settings.php`
  - Sección **General:** Nombre comercial, logo, color por defecto
  - Sección **Operación:** tarifa por km global, km antes de mantenimiento, bloquear rental con mantenimiento pendiente, permitir operador en mantenimientos, permitir operador en reportes
  - Sección **Auditoría:** retención de logs (días)
  - Sección **Notificaciones:** emails adicionales para alertas (textarea con emails separados por coma)
  - Botón "Guardar cambios" via WordPress Settings API o AJAX

**Criterios de aceptación:**
- [ ] Solo `manage_options` puede ver y guardar configuración
- [ ] Todos los valores se guardan/recuperan correctamente con `get_option()` / `update_option()`
- [ ] Las opciones afectan el comportamiento del módulo (umbral mantenimiento, bloqueo, etc.)
- [ ] Guarda cambios registra entrada en auditoría

---

### Fase 10 — Integración con Módulo Financiero (Opcional Futura)

**Objetivo:** Sincronizar ingresos y costos de vehículos con el módulo Financial de Aura Suite.

> Esta fase es opcional y se ejecuta si el Módulo Financial ya está instalado y activo.

**Elementos a integrar:**
- Al cerrar una salida tipo `rental` con `total_amount > 0`, crear automáticamente una transacción de **ingreso** en `wp_aura_financial_transactions` con categoría configurable
- Al cerrar una salida tipo `maintenance` con `maint_actual_cost > 0`, crear una transacción de **egreso**
- Los gastos del trip (`expenses_detail`) se pueden sincronizar como transacciones de egreso detalladas

**Prerequisito:** El módulo Financial debe estar activo y tener las categorías de vehículos configuradas.

---

## 11. Catálogos Semilla (Datos por Defecto)

Al activar el módulo, se insertan los siguientes ítems globales si la tabla está vacía:

```
Destinos (type='destination'):
  Sede Central, Aeropuerto, Taller, Puerto, Terminal, Hospital, Otro

Propósitos (type='purpose'):
  Reunión de trabajo, Entrega de documentos, Comisión oficial,
  Transporte de personal, Diligencias administrativas, Otro

Gastos (type='expense'):
  Combustible, Peaje, Parqueadero, Lavado, Aceite/Fluidos,
  Reparación imprevista, Otro
```

---

## 12. Notas de Implementación

1. **IDs de vehículos y trips:** Usan `BIGINT UNSIGNED AUTO_INCREMENT` — consistente con todos los demás módulos de Aura Suite (inventario, estudiantes, certificados, formularios). No usar UUIDs.
2. **DataTables:** Cargar los datos vía REST API en modo server-side para tablas grandes. Evitar cargar todos los registros en el HTML inicial.
3. **Fotos de vehículos:** Guardar en `wp-content/uploads/aura/vehicles/{vehicle_id}/`. Proteger el directorio con `.htaccess` si los vehículos no son públicos.
4. **Soft delete en trips:** El campo `deleted = 1` no elimina el registro; los trips eliminados se excluyen de todos los listados pero se conservan para auditoría.
5. **Stubs existentes:** `class-vehicle-cpt.php` registra CPTs (`aura_vehicle` + `aura_vehicle_exit`) con post_meta. Este enfoque **se reemplaza completamente** por tablas custom. **Eliminar** la llamada a `Aura_Vehicle_CPT::init()` del hook `init` en `aura-business-suite.php`. El archivo stub puede conservarse como referencia pero no debe ejecutarse.
6. **Nonce JS:** Inyectar `wp_localize_script('aura-vehicle-admin', 'auraVehiclesConfig', ['nonce' => wp_create_nonce('wp_rest'), 'apiBase' => rest_url('aura/v1/')])` al encolar los assets.
7. **Bootstrap 5:** Reusar los assets de Bootstrap ya cargados en el panel de Aura Suite — no cargar una segunda instancia.
8. **mPDF/Dompdf para PDF:** Verificar si ya está instalado via Composer en `vendor/` antes de requerir. Si no, añadir a `composer.json`.
9. **Consistencia de naming:** Todos los métodos de managers usan verbo en inglés (create, update, delete, get, get_list). Las vistas y callbacks REST usan español en los mensajes de error (`'message' => 'No tienes permiso...'`).
10. **`wp_aura_areas` ya creada:** No ejecutar `CREATE TABLE` para áreas. El setup solo crea las 5 tablas del módulo de vehículos. Los `area_id` son `BIGINT UNSIGNED` (FK lógico a `wp_aura_areas.id`).
11. **Capabilities ya registradas:** Las 12 caps `aura_vehicles_*` ya están en `Aura_Roles_Manager::get_all_capabilities()`. El setup del módulo **no debe** volver a registrarlas — se sincronizan automáticamente en `activate()` global y `ensure_admin_capabilities()` en cada `admin_init`. Las caps `aura_vehicles_audit` y `aura_vehicles_settings` son administrativas (⭐) y protegen el log de auditoría y la configuración del módulo.
12. **Patrón Setup:** Seguir el mismo patrón de `Aura_Inventory_Setup` / `Aura_Students_Setup`: constante `DB_VERSION`, método `needs_update()`, método `create_tables()` con `dbDelta()` + `update_option()`.

---

## 13. Correcciones Post-Implementación (Abril–Mayo 2026)

Las siguientes correcciones fueron aplicadas después de la implementación inicial:

### C1 — CSS/JS no cargaban en páginas del módulo
**Archivo:** `modules/vehicles/admin/class-vehicle-admin.php`  
**Causa:** WordPress genera el `$hook_suffix` como `sanitize_title(título_menú)_page_slug`. Para "Vehículos" → el hook real era `vehiculos_page_aura-vehicles-trips`, pero el código comparaba con `aura-vehicles_page_aura-vehicles-trips` → nunca coincidía → CSS/JS nunca se encolaban.  
**Fix:** Reemplazar todas las comparaciones de `$hook` con `$_GET['page']` (el slug registrado siempre es el valor exacto).

### C2 — Error al redimensionar imagen de vehículos
**Archivo:** `modules/vehicles/class-vehicle-manager.php`, función `ajax_crop_vehicle_photo()`  
**Causa:** `$editor->resize(800, 600, true)` con `crop=true` falla cuando la imagen es menor a 800×600 px (`image_resize_dimensions()` retorna `false`).  
**Fix:** Verificar el tamaño actual y llamar `resize(800, 600)` sin `crop=true` solo cuando la imagen supera las dimensiones objetivo.

### C3 — Mejoras UX modal "Nueva Salida"
**Archivos:** `page-trips.php`, `vehicle-trips.js`, `vehicle-admin.css`  
**Mejoras aplicadas:**
- Panel informativo del vehículo seleccionado (`#trip-vehicle-info`) con placa, marca/modelo, odómetro y tarifa.
- Título/ícono de sección dinámicos según tipo de salida (`trip-section-errand-icon`, `trip-section-errand-title`).
- Auto-fill de fecha/hora actual al abrir el modal (`getNow()`).
- Auto-fill de odómetro desde el vehículo seleccionado.
- Handler de tarjetas de tipo de salida (`.aura-trips-type-card`) que faltaba completamente.
- Validación con resaltado visual (`has-error`) en campos específicos.
- Texto dinámico del botón submit según tipo de salida.
- Cache de vehículos disponibles en `_vehiclesMap`.

### C4 — Permisos de subida de imágenes en módulos de Inventario y Vehículos
**Archivo:** `modules/common/class-roles-manager.php`  
**Causa:** WordPress requiere la capability nativa `upload_files` para cualquier subida vía `async-upload.php` y para el modal de Biblioteca de Medios. Usuarios con solo capabilities `aura_inventory_*` o `aura_vehicles_*` nunca la tienen por defecto.  
**Fix (dos filtros añadidos en `init()`):**
- `grant_upload_cap_for_media_modules` (filtro `user_has_cap`): otorga `upload_files` dinámicamente a usuarios con `aura_inventory_create`, `aura_inventory_edit`, `aura_vehicles_create` o `aura_vehicles_edit`. Sin escritura en base de datos.
- `allow_view_all_media_for_modules` (filtro `ajax_query_attachments_args`): elimina la restricción `author` para que esos usuarios vean todos los adjuntos, no solo los propios.

### C5 — Error al redimensionar imagen de equipos de inventario
**Archivo:** `modules/inventory/class-inventory-equipment.php`, función `ajax_crop_equipment_photo()`  
**Causa:** Mismo bug que C2 — `resize(800, 600, true)` falla para imágenes menores a 800×600 px.  
**Fix:** Mismo patrón: verificar dimensiones actuales y aplicar `resize(800, 600)` condicional.

### C6 — Tablas del módulo de Vehículos: DataTables Responsive
**Archivos:** `page-vehicles.php`, `page-trips.php`, `vehicle-vehicles.js`, `vehicle-trips.js`, `class-vehicle-admin.php`, `vehicle-admin.css`  
**Causa:** Las páginas del módulo cargaban DataTables 2.2.2 pero **no** el plugin Responsive 3.0.4. Aunque `vehicle-vehicles.js` tenía `responsive: true`, el plugin nunca se cargaba (opción ignorada).  
**Fixes aplicados:**
- Agregado CDN `datatables-responsive-css` (`responsive.dataTables.min.css`) y `datatables-responsive-js` (`dataTables.responsive.min.js`) a todas las páginas del módulo (`page-vehicles.php`, `page-trips.php`, `class-vehicle-admin.php` para reports).
- Agregado `responsive: true` en `vehicle-trips.js` (faltaba por completo).
- Agregado `dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>'` en todas las tablas del módulo, igual que el módulo de Inventario.
- Cambiado `searching: false` en la tabla de Salidas — los filtros propios reemplazan la búsqueda nativa de DataTables.
- Agregado input de búsqueda libre `#aura-trips-search` en la barra de filtros de Salidas, con handler `_table.search(val).draw()`.
- Agregados `responsivePriority` en columnas clave de la tabla de Salidas: ID (10000, primera en ocultarse), Vehículo (1, última en ocultarse).
- Actualizado `clear` handler para limpiar también `#aura-trips-search` y resetear el filtro de DataTables.
- Corregido CSS del botón expandir/colapsar en `vehicle-admin.css` para usar dashicons de WordPress (igual que `inventory-equipment.css`).
- Unificado idioma/formato de labels de paginación e información en todas las tablas del módulo.

---

*Documento generado: Mayo 2026 — Compatible con Aura Business Suite v1.x*
