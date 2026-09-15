# Arquitectura del Sistema de Gestión Vehicular — Implementación como Plugin WordPress

## Índice

1. [Visión General de la App Original](#1-visión-general)
2. [Entidades y Modelos de Datos](#2-entidades-y-modelos-de-datos)
3. [Sistema RBAC — Roles y Permisos](#3-sistema-rbac)
4. [Módulos Funcionales](#4-módulos-funcionales)
5. [Arquitectura del Plugin WordPress](#5-arquitectura-del-plugin-wordpress)
6. [Mapeo Usuarios WordPress → Roles del Sistema](#6-mapeo-de-usuarios-wordpress)
7. [Estructura de Base de Datos (Custom Tables)](#7-base-de-datos)
8. [API REST del Plugin](#8-api-rest)
9. [Páginas y Shortcodes](#9-páginas-y-shortcodes)
10. [CRUD: Destinos, Propósitos y Gastos](#10-crud-catálogos)
11. [Sistema de Auditoría](#11-auditoría)
12. [Reportes y Exportaciones](#12-reportes)
13. [Backups y Configuración](#13-backups-y-configuración)
14. [Seguridad](#14-seguridad)
15. [Hoja de Ruta de Implementación](#15-hoja-de-ruta)

---

## 1. Visión General

La aplicación original **CarTracker** es un sistema de gestión de flota vehicular organizado por **áreas** (unidades organizacionales), con control de acceso RBAC, registro de salidas/viajes (rentas, diligencias, mantenimientos), auditoría y reportes.

### Stack Original
| Componente | Tecnología |
|------------|-----------|
| Backend | PHP 8.0+ |
| Almacenamiento | Archivos JSON |
| Frontend | Bootstrap 5.3 + Chart.js + DataTables |
| Autenticación | Sesiones PHP propias |
| Iconos | Bootstrap Icons |

### Stack en WordPress
| Componente | WordPress Equivalente |
|------------|----------------------|
| Backend | Plugin PHP (Custom Plugin) |
| Almacenamiento | Custom Database Tables (MySQL/MariaDB) |
| Frontend | Bootstrap 5.3 encolado + Chart.js + DataTables |
| Autenticación | Sistema de usuarios WordPress (`wp_users`) |
| Roles/Permisos | Custom Capabilities + User Meta |
| Páginas | Custom Post Types o Page Templates via Shortcode |
| API | WordPress REST API (Custom Endpoints) |
| Ajustes | Options API (`wp_options`) |

---

## 2. Entidades y Modelos de Datos

### 2.1 Área (`car_areas`)

Unidad organizacional independiente. Agrupa vehículos y usuarios.

```sql
CREATE TABLE wp_car_areas (
    id            VARCHAR(36) PRIMARY KEY,  -- UUID
    name          VARCHAR(100) NOT NULL UNIQUE,
    description   TEXT,
    color         VARCHAR(7) DEFAULT '#667eea',  -- Hex color
    logo          VARCHAR(500),                  -- Ruta relativa wp-content/uploads/car/areas/
    website       VARCHAR(500),
    active        TINYINT(1) DEFAULT 1,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME,
    INDEX idx_active (active)
);

-- Tabla pivot área–usuario (polimórfica por rol)
CREATE TABLE wp_car_area_users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_id       VARCHAR(36) NOT NULL,
    user_id       BIGINT UNSIGNED NOT NULL,  -- wp_users.ID
    role          ENUM('primary_admin','admin','editor') NOT NULL,
    assigned_at   DATETIME NOT NULL,
    assigned_by   BIGINT UNSIGNED,
    UNIQUE KEY uq_area_user (area_id, user_id),
    INDEX idx_area (area_id),
    INDEX idx_user (user_id)
);

-- Tabla pivot área–vehículo
CREATE TABLE wp_car_area_vehicles (
    area_id       VARCHAR(36) NOT NULL,
    vehicle_id    VARCHAR(36) NOT NULL,
    assigned_at   DATETIME NOT NULL,
    PRIMARY KEY (area_id, vehicle_id),
    INDEX idx_vehicle (vehicle_id)
);
```

**Campos clave:**
| Campo | Descripción |
|-------|-------------|
| `id` | UUID generado al crear |
| `color` | Color hexadecimal para identificación visual en UI |
| `logo` | Ruta de imagen subida via WordPress Media o carpeta propia |
| `website` | URL externa de la organización |

---

### 2.2 Vehículo (`car_vehicles`)

```sql
CREATE TABLE wp_car_vehicles (
    id               VARCHAR(36) PRIMARY KEY,
    plate            VARCHAR(20) NOT NULL UNIQUE,
    brand            VARCHAR(50) NOT NULL,
    model            VARCHAR(50) NOT NULL,
    year             SMALLINT,
    color            VARCHAR(30),
    type             ENUM('sedan','suv','pickup','van','bus','motorcycle','truck','other') DEFAULT 'sedan',
    vin              VARCHAR(17),
    status           ENUM('available','rented','maintenance','unavailable') DEFAULT 'available',
    mileage          INT UNSIGNED DEFAULT 0,
    rate_per_km      DECIMAL(10,2) DEFAULT 0,
    fuel_type        ENUM('gasoline','diesel','electric','hybrid','gas') DEFAULT 'gasoline',
    transmission     ENUM('manual','automatic') DEFAULT 'manual',
    notes            TEXT,
    photos           JSON,                  -- Array de rutas de imágenes
    unavailable_info JSON,                  -- { reason, date, notes, marked_by, marked_at }
    transfer_history JSON,                  -- Array de transferencias entre áreas
    active           TINYINT(1) DEFAULT 1,
    created_at       DATETIME NOT NULL,
    updated_at       DATETIME,
    INDEX idx_status (status),
    INDEX idx_plate (plate)
);
```

**Estructura `unavailable_info`:**
```json
{
  "reason": "sold | accident | obsolete | donated | transferred_external | other",
  "reason_label": "Vendido",
  "date": "2025-12-01",
  "final_mileage": 125000,
  "notes": "Vendido por renovación de flota",
  "marked_by": 5,
  "marked_at": "2025-12-01T10:30:00"
}
```

**Estructura `transfer_history`:**
```json
[
  {
    "from_area_id": "uuid-origen",
    "from_area_name": "Centro Mateo",
    "to_area_id": "uuid-destino",
    "to_area_name": "Hadime Raíces",
    "transferred_by": 5,
    "transferred_at": "2025-12-01T15:00:00",
    "reason": "Reasignación"
  }
]
```

---

### 2.3 Salida / Viaje (`car_trips`)

Registro unificado para todos los tipos de salida: renta, diligencia, mantenimiento, otro.

```sql
CREATE TABLE wp_car_trips (
    id                  VARCHAR(36) PRIMARY KEY,
    vehicle_id          VARCHAR(36) NOT NULL,
    trip_type           ENUM('rental','errand','maintenance','other') NOT NULL DEFAULT 'rental',
    status              ENUM('active','returned','cancelled') DEFAULT 'active',

    -- Datos del cliente (para tipo 'rental')
    client_name         VARCHAR(150),
    client_phone        VARCHAR(20),
    client_email        VARCHAR(150),
    client_document     VARCHAR(50),
    rate_per_km         DECIMAL(10,2) DEFAULT 0,

    -- Datos internos (para tipos 'errand','other')
    responsible_name    VARCHAR(150),
    destination         VARCHAR(200),
    purpose             VARCHAR(200),
    trip_description    TEXT,

    -- Datos de mantenimiento (para tipo 'maintenance')
    maint_subtype       ENUM('preventive','corrective','inspection'),
    maint_priority      ENUM('low','medium','high','urgent'),
    maint_description   TEXT,
    maint_provider      VARCHAR(150),
    maint_contact       VARCHAR(50),
    maint_estimated_cost DECIMAL(10,2) DEFAULT 0,
    maint_actual_cost   DECIMAL(10,2) DEFAULT 0,
    maint_completion_notes TEXT,

    -- Datos de salida
    departure_datetime  DATETIME NOT NULL,
    departure_odometer  INT UNSIGNED DEFAULT 0,
    departure_fuel      TINYINT UNSIGNED DEFAULT 100,  -- % combustible

    -- Datos de retorno
    return_datetime     DATETIME,
    return_odometer     INT UNSIGNED DEFAULT 0,
    return_fuel         TINYINT UNSIGNED,
    km_traveled         INT UNSIGNED DEFAULT 0,
    total_amount        DECIMAL(10,2) DEFAULT 0,
    additional_charges  DECIMAL(10,2) DEFAULT 0,
    discounts           DECIMAL(10,2) DEFAULT 0,
    total_expenses      DECIMAL(10,2) DEFAULT 0,
    expenses_detail     JSON,                          -- Array de gastos individuales
    cancellation_reason TEXT,

    -- Metadatos
    assigned_to         BIGINT UNSIGNED,               -- wp_users.ID
    created_by          BIGINT UNSIGNED NOT NULL,      -- wp_users.ID
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME,
    deleted             TINYINT(1) DEFAULT 0,

    INDEX idx_vehicle (vehicle_id),
    INDEX idx_status (status),
    INDEX idx_type (trip_type),
    INDEX idx_departure (departure_datetime),
    INDEX idx_created (created_at)
);
```

**Estructura `expenses_detail`:**
```json
[
  { "name": "Combustible", "amount": 50000, "checked": true },
  { "name": "Peaje", "amount": 5000, "checked": true },
  { "name": "Parqueadero", "amount": 0, "checked": false }
]
```

---

### 2.4 Catálogos: Destinos, Propósitos, Gastos (`car_catalogs`)

```sql
CREATE TABLE wp_car_catalogs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type        ENUM('destination','purpose','expense') NOT NULL,
    name        VARCHAR(150) NOT NULL,
    description VARCHAR(300),
    icon        VARCHAR(50),           -- Bootstrap Icon class
    active      TINYINT(1) DEFAULT 1,
    sort_order  SMALLINT DEFAULT 0,
    area_id     VARCHAR(36),           -- NULL = global, UUID = solo para esa área
    created_by  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME,
    UNIQUE KEY uq_type_name_area (type, name, area_id),
    INDEX idx_type (type),
    INDEX idx_area (area_id)
);
```

**Tipos de catálogos:**
| Tipo | Descripción | Ejemplos |
|------|-------------|---------|
| `destination` | Destinos posibles para salidas | Sede Central, Aeropuerto, Taller, Cliente externo |
| `purpose` | Propósito o motivo del viaje | Reunión, Entrega, Comisión, Mantenimiento |
| `expense` | Tipos de gasto que se pueden registrar al retorno | Combustible, Peaje, Parqueadero, Alimentación |

**Características del CRUD:**
- Catálogos **globales** (visibles en todas las áreas) o **por área** (solo en el área asignada)
- El Owner gestiona catálogos globales
- El Primary Admin gestiona catálogos de su área
- Orden de presentación ajustable (`sort_order`)
- Activar/Desactivar sin eliminar

---

### 2.5 Auditoría (`car_audit`)

```sql
CREATE TABLE wp_car_audit (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation   VARCHAR(60) NOT NULL,   -- rental_create, vehicle_update, user_login, etc.
    entity_type VARCHAR(30),            -- trip, vehicle, area, user
    entity_id   VARCHAR(36),
    user_id     BIGINT UNSIGNED,
    user_role   VARCHAR(20),
    ip_address  VARCHAR(45),            -- IPv4 e IPv6
    user_agent  VARCHAR(300),
    details     JSON,                   -- Datos relevantes de la operación
    created_at  DATETIME NOT NULL,
    INDEX idx_operation (operation),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);
```

**Operaciones registradas:**
| Categoría | Operaciones |
|-----------|-------------|
| **Viajes/Salidas** | `trip_create`, `trip_update`, `trip_checkin`, `trip_cancel`, `trip_delete` |
| **Vehículos** | `vehicle_create`, `vehicle_update`, `vehicle_delete`, `vehicle_transfer`, `vehicle_mark_unavailable`, `vehicle_restore` |
| **Áreas** | `area_create`, `area_update`, `area_delete` |
| **Usuarios** | `user_create`, `user_update`, `user_assign_area`, `user_remove_area`, `user_deactivate` |
| **Sesión** | `user_login`, `user_logout`, `user_login_failed` |
| **Backups** | `backup_create`, `backup_restore`, `backup_delete` |
| **Configuración** | `settings_update`, `catalog_create`, `catalog_update`, `catalog_delete` |

---

### 2.6 Configuración del Sistema (`car_settings`)

Almacenada en `wp_options` con prefijo `car_`:

```php
// Estructura de opciones
[
    'car_business_name'             => 'CarTracker',
    'car_default_color'             => '#667eea',
    'car_rate_per_km'               => 0,
    'car_km_before_maintenance'     => 10000,
    'car_block_with_pending_maint'  => false,
    'car_allow_editor_maintenance'  => true,
    'car_allow_editor_reports'      => false,
    'car_session_timeout_minutes'   => 480,
    'car_min_password_length'       => 6,
    'car_audit_retention_days'      => 365,
    'car_notification_emails'       => [],
    'car_logo'                      => '',  // ruta en uploads
]
```

---

## 3. Sistema RBAC

### 3.1 Roles del Sistema

| Rol | Nivel | Descripción |
|-----|-------|-------------|
| `car_owner` | 5 | Super administrador. Acceso total al sistema, configuración, backups. Solo 1 por sistema. |
| `car_primary_admin` | 4 | Admin principal de área. Puede crear usuarios admin/editor para su área. |
| `car_admin` | 3 | Administrador de área. Gestión completa de vehículos/salidas en su área. |
| `car_editor` | 2 | Editor de área. Solo registra salidas y mantenimientos. |

> En WordPress, cada usuario de WP tiene su rol de WP normal (`subscriber`, `author`, etc.) MÁS las capacidades del plugin `car_*`. El rol global se almacena en `user_meta` como `car_global_role`.

### 3.2 Mapeo de Capacidades WordPress

```php
// Capacidades registradas en el plugin
$capabilities = [
    // Sistema general
    'car_access'                => 'Acceder al sistema de vehículos',
    'car_manage_system'         => 'Configurar el sistema (Owner)',
    'car_manage_areas'          => 'Crear/editar/eliminar áreas (Owner)',
    'car_manage_backups'        => 'Gestionar backups (Owner)',

    // Usuarios
    'car_view_users'            => 'Ver usuarios del área',
    'car_create_users'          => 'Crear usuarios (Primary Admin+)',
    'car_edit_users'            => 'Editar usuarios',
    'car_assign_users'          => 'Asignar usuarios a áreas',

    // Vehículos
    'car_view_vehicles'         => 'Ver vehículos',
    'car_manage_vehicles'       => 'Crear/editar vehículos (Admin+)',
    'car_delete_vehicles'       => 'Eliminar vehículos (Admin+)',
    'car_transfer_vehicles'     => 'Transferir vehículos (Owner)',
    'car_mark_unavailable'      => 'Dar de baja vehículos (Admin+)',

    // Salidas/Viajes
    'car_view_trips'            => 'Ver salidas',
    'car_create_trips'          => 'Registrar salidas',
    'car_checkin_trips'         => 'Registrar retorno',
    'car_cancel_trips'          => 'Cancelar salidas (Admin+)',
    'car_delete_trips'          => 'Eliminar salidas (Owner)',

    // Catálogos
    'car_manage_catalogs'       => 'Gestionar destinos/propósitos/gastos',

    // Reportes
    'car_view_reports'          => 'Ver y exportar reportes',

    // Auditoría
    'car_view_audit'            => 'Ver registros de auditoría (Owner)',
];
```

### 3.3 Matriz de Permisos

| Permiso | Owner | Primary Admin | Admin | Editor |
|---------|:-----:|:-------------:|:-----:|:------:|
| Configuración del sistema | ✅ | ❌ | ❌ | ❌ |
| Gestionar áreas | ✅ | ❌ | ❌ | ❌ |
| Backups | ✅ | ❌ | ❌ | ❌ |
| Auditoría | ✅ | ❌ | ❌ | ❌ |
| Crear/editar usuarios | ✅ | ✅ (su área) | ✅ (solo editors) | ❌ |
| Ver usuarios | ✅ | ✅ (su área) | ✅ (editors su área) | ❌ |
| Crear/editar vehículos | ✅ | ✅ | ✅ | ❌ |
| Dar de baja vehículo | ✅ | ✅ | ✅ | ❌ |
| Transferir vehículo | ✅ | ❌ | ❌ | ❌ |
| Registrar salida | ✅ | ✅ | ✅ | ✅ |
| Registrar retorno | ✅ | ✅ | ✅ | ✅ |
| Cancelar/eliminar salida | ✅ | ✅ | ✅ | ❌ |
| Gestionar catálogos globales | ✅ | ❌ | ❌ | ❌ |
| Gestionar catálogos de área | ✅ | ✅ | ❌ | ❌ |
| Reportes | ✅ | ✅ | ✅ | ❌ |

### 3.4 Rol Contextual por Área

Un usuario puede tener **roles diferentes en diferentes áreas**. La tabla `wp_car_area_users` almacena el rol contextual. El helper `CarMiddleware::getContextualRole($area_id, $user_id)` retorna el rol en un área específica.

```php
class CarMiddleware {
    /**
     * Retorna el rol del usuario en el área seleccionada actualmente
     */
    public static function getContextualRole(?string $areaId = null): string {
        $areaId = $areaId ?? get_user_meta(get_current_user_id(), 'car_selected_area', true);
        if (!$areaId) return 'none';

        global $wpdb;
        $role = $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}car_area_users WHERE area_id = %s AND user_id = %d",
            $areaId, get_current_user_id()
        ));
        return $role ?? 'none';
    }

    /**
     * Verifica si el usuario tiene una capacidad en el área actual
     */
    public static function canDo(string $capability): bool {
        $user = wp_get_current_user();
        if (!$user->ID) return false;
        return $user->has_cap($capability);
    }
}
```

---

## 4. Módulos Funcionales

### 4.1 Dashboard Analítico

**KPIs principales:**
- Total de vehículos por estado (disponible, en uso, mantenimiento, no disponible)
- Salidas activas del día
- Mantenimientos en progreso
- Kilómetros recorridos en el período
- Ingresos/costos del período

**Gráficas (Chart.js):**
| Gráfica | Tipo | Descripción |
|---------|------|-------------|
| Uso de vehículos | Doughnut | Distribución de estados de la flota |
| Km por vehículo | Bar | Kilómetros recorridos por cada vehículo en el período |
| Uso por área | Bar horizontal | Número de salidas por área |
| Actividad mensual | Line | Número de salidas/mantenimientos en el tiempo |
| Costos vs Ingresos | Bar agrupado | Comparativa financiera por período |

**Selector de período:** Hoy / Esta semana / 7 días / 30 días / Este mes / Este año / Personalizado

**Tabla de vehículos en uso:** Muestra en tiempo real los vehículos activos con quién los tiene y desde cuándo.

---

### 4.2 Gestión de Áreas

**CRUD completo:**
- Crear área con nombre, descripción, color, logo, website
- Asignar/desasignar vehículos al área
- Asignar/desasignar usuarios con sus roles (primary_admin, admin, editor)
- Ver estadísticas del área
- Activar/Desactivar áreas

**UI:**
- Tarjetas con logo/icono y color identificativo
- Vista previa en tiempo real al editar color y logo
- Paleta de colores predefinida

---

### 4.3 Gestión de Vehículos

**Datos del vehículo:**
- Placa, marca, modelo, año, color, tipo, VIN
- Estado (disponible, en uso, mantenimiento, no disponible)
- Kilometraje actual, tarifa por km, tipo de combustible, transmisión
- Fotos múltiples (subida con drag & drop)
- Notas
- Información de baja (`unavailable_info`) si aplica
- Historial de transferencias entre áreas

**Acciones:**
- Crear, Editar, Ver detalles, Eliminar (soft delete)
- Dar de baja (con motivo y nota)
- Restaurar (solo Owner)
- Transferir a otra área (solo Owner)
- Ver historial completo de salidas

---

### 4.4 Sistema Unificado de Salidas (Trips)

Todos los tipos de salida se gestionan desde un solo módulo con campos condicionales según el tipo:

#### Tipos de Salida

| Tipo | Código | Color | Descripción |
|------|--------|-------|-------------|
| Renta | `rental` | Verde | Vehículo rentado a un cliente externo con cobro por km |
| Diligencia | `errand` | Azul | Salida interna con responsable, destino y propósito |
| Mantenimiento | `maintenance` | Amarillo | Envío a taller; cambia estado del vehículo a "En mantenimiento" |
| Otro | `other` | Gris | Salida genérica interna |

#### Flujo Completo

```
[Registro de Salida]
        ↓
    Seleccionar tipo (rental/errand/maintenance/other)
        ↓
    Completar campos del tipo seleccionado:
    • rental     → datos del cliente, tarifa por km
    • errand     → responsable, destino (catálogo), propósito (catálogo)
    • maintenance → subtipo, prioridad, descripción, proveedor, costo estimado
    • other      → responsable, descripción
        ↓
    Datos comunes: vehículo, fecha/hora salida, odómetro inicial, combustible
        ↓
    [Vehículo cambia a status "rented" o "maintenance"]
        ↓
[Retorno / Check-in]
        ↓
    Fecha/hora retorno, odómetro final
    Para rental: cargos adicionales, descuentos, gastos (catálogo de gastos)
    Para maintenance: costo real, notas de finalización
        ↓
    [Vehículo regresa a status "available"]
```

#### Subtipos de Mantenimiento

| Subtipo | Código | Descripción |
|---------|--------|-------------|
| Preventivo | `preventive` | Mantenimiento programado (cambio de aceite, filtros, etc.) |
| Correctivo | `corrective` | Reparación por falla o avería |
| Inspección | `inspection` | Revisión técnica o diagnóstico |

#### Prioridades de Mantenimiento

| Prioridad | Color | Descripción |
|-----------|-------|-------------|
| Baja | Verde | No urgente, puede esperar |
| Media | Amarillo | Atender en los próximos días |
| Alta | Naranja | Atender lo antes posible |
| Urgente | Rojo | Atención inmediata requerida |

---

### 4.5 Gestión de Usuarios

**Flujo:**
1. Botón "Agregar Usuario" → modal de 2 pasos:
   - Paso 1: Buscar usuario existente en WordPress para asignarle rol en el área, O crear usuario nuevo
   - Paso 2: Seleccionar el rol a asignar (según permisos del solicitante)
2. Al crear usuario nuevo: se crea en `wp_users` + se asigna al área

**Tabla de usuarios:**
- Muestra rol contextual en el área activa
- Badge de estado (activo/inactivo)
- Áreas asignadas con sus roles
- Acciones: Ver perfil, Editar, Cambiar contraseña, Desactivar, Quitar del área

---

### 4.6 Catálogos (CRUD para Destinos, Propósitos y Gastos)

Módulo de administración para los datos maestros que se usan en los formularios de salidas.

**Gestión desde la UI de Configuración (menú Ajustes del plugin):**

#### Destinos (`destination`)
Lugares frecuentes adonde se envían los vehículos.
- Aeropuerto, Sede Central, Cliente X, Taller mecánico, Banco, etc.
- Pueden ser globales o específicos por área
- En el formulario de salida aparecen como selector + opción "Otro (especificar)"

#### Propósitos (`purpose`)
Motivo o finalidad del viaje.
- Reunión con cliente, Entrega de documentos, Comisión, Revisión técnica, etc.
- Pueden ser globales o específicos por área
- Mismo comportamiento que destinos en formulario

#### Gastos de Vehículo (`expense`)
Tipos de gasto registrables al momento del retorno.
- Combustible, Peaje, Parqueadero, Alimentación, Lavado, Reparación menor, etc.
- Aparecen como checkboxes con campo de monto en el modal de check-in
- Pueden ser globales o por área

**Pantalla de gestión (página de ajustes del plugin):**

```
┌─────────────────────────────────────────────────────────────┐
│  CATÁLOGOS DEL SISTEMA                                      │
├──────────────┬──────────────────┬──────────────────────────┤
│  [Destinos]  │  [Propósitos]    │  [Gastos de Vehículo]    │
├──────────────┴──────────────────┴──────────────────────────┤
│  + Agregar Destino    [Solo globales  ▼]                    │
├─────────────────────────────────────────────────────────────┤
│  ≡  Aeropuerto                   Global  [✏️] [🗑️]          │
│  ≡  Sede Central                 Global  [✏️] [🗑️]          │
│  ≡  Taller El Rápido            Área A  [✏️] [🗑️]          │
│  ≡  (drag para reordenar)                                   │
└─────────────────────────────────────────────────────────────┘
```

**API endpoints REST para catálogos:**
```
GET    /wp-json/car/v1/catalogs?type=destination
GET    /wp-json/car/v1/catalogs?type=purpose
GET    /wp-json/car/v1/catalogs?type=expense&area_id=uuid
POST   /wp-json/car/v1/catalogs
PUT    /wp-json/car/v1/catalogs/{id}
DELETE /wp-json/car/v1/catalogs/{id}
PATCH  /wp-json/car/v1/catalogs/reorder        -- { ids: [] }
```

---

## 5. Arquitectura del Plugin WordPress

### 5.1 Estructura de Carpetas

```
wp-content/plugins/
└── car-fleet-manager/
    ├── car-fleet-manager.php          ← Plugin bootstrap (register, hooks)
    ├── uninstall.php                  ← Limpieza al desinstalar
    ├── readme.txt
    │
    ├── includes/                      ← Lógica de negocio (sin HTML)
    │   ├── class-car-plugin.php       ← Clase principal (Singleton)
    │   ├── class-car-installer.php    ← Instalación/actualización de tablas
    │   ├── class-car-middleware.php   ← RBAC y autenticación
    │   ├── class-car-area-manager.php
    │   ├── class-car-vehicle-manager.php
    │   ├── class-car-trip-manager.php       ← Unifica trips (antes RentalManager)
    │   ├── class-car-catalog-manager.php    ← Destinos, propósitos, gastos
    │   ├── class-car-report-manager.php
    │   ├── class-car-backup-manager.php
    │   ├── class-car-audit-manager.php
    │   └── class-car-settings-manager.php
    │
    ├── api/                           ← Clases de endpoints REST
    │   ├── class-car-rest-areas.php
    │   ├── class-car-rest-vehicles.php
    │   ├── class-car-rest-trips.php
    │   ├── class-car-rest-users.php
    │   ├── class-car-rest-catalogs.php
    │   ├── class-car-rest-reports.php
    │   ├── class-car-rest-audit.php
    │   └── class-car-rest-session.php
    │
    ├── admin/                         ← Páginas del área admin de WP
    │   ├── class-car-admin.php        ← Registro de menús admin
    │   ├── views/
    │   │   ├── page-dashboard.php
    │   │   ├── page-areas.php
    │   │   ├── page-vehicles.php
    │   │   ├── page-trips.php
    │   │   ├── page-users.php
    │   │   ├── page-reports.php
    │   │   ├── page-audit.php
    │   │   ├── page-settings.php
    │   │   └── page-catalogs.php
    │   └── partials/
    │       ├── modal-trip.php
    │       ├── modal-vehicle.php
    │       ├── modal-area.php
    │       └── modal-user.php
    │
    ├── assets/
    │   ├── css/
    │   │   ├── car-admin.css          ← Estilos para el panel admin
    │   │   └── car-public.css         ← Estilos para shortcodes públicos
    │   └── js/
    │       ├── car-admin.js           ← JS principal del panel
    │       ├── car-dashboard.js       ← Chart.js + KPIs
    │       ├── car-trips.js           ← Lógica del modal de salidas
    │       └── car-catalogs.js        ← CRUD de catálogos
    │
    └── templates/                     ← Templates para shortcodes públicos (opcional)
        ├── select-area.php
        └── dashboard-embed.php
```

### 5.2 Bootstrap del Plugin (`car-fleet-manager.php`)

```php
<?php
/**
 * Plugin Name: CAR Fleet Manager
 * Plugin URI:  https://example.com/car-fleet-manager
 * Description: Sistema de gestión vehicular por áreas con RBAC.
 * Version:     1.0.0
 * Author:      Tu Nombre
 * Text Domain: car-fleet
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('CAR_PLUGIN_FILE',    __FILE__);
define('CAR_PLUGIN_DIR',     plugin_dir_path(__FILE__));
define('CAR_PLUGIN_URL',     plugin_dir_url(__FILE__));
define('CAR_PLUGIN_VERSION', '1.0.0');
define('CAR_DB_VERSION',     '1.0.0');

// Autoloader simple
spl_autoload_register(function (string $class): void {
    $prefix = 'Car_';
    if (strpos($class, $prefix) !== 0) return;
    $file = CAR_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace(['Car_', '_'], ['car-', '-'], $class)) . '.php';
    if (file_exists($file)) require_once $file;
});

register_activation_hook(__FILE__,   ['Car_Installer', 'activate']);
register_deactivation_hook(__FILE__,  ['Car_Installer', 'deactivate']);
register_uninstall_hook(__FILE__,     'car_fleet_uninstall');

add_action('plugins_loaded', function () {
    Car_Plugin::get_instance();
});
```

### 5.3 Instalador de Tablas (`class-car-installer.php`)

```php
class Car_Installer {
    public static function activate(): void {
        self::create_tables();
        self::register_capabilities();
        self::seed_default_catalogs();
        update_option('car_db_version', CAR_DB_VERSION);
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Se ejecutan todos los CREATE TABLE con dbDelta()
        // (ver SQL en sección 7)
        $sql = self::get_schema_sql($charset);
        dbDelta($sql);
    }

    private static function register_capabilities(): void {
        // Asigna todas las capabilities car_* al rol administrator de WP
        $admin = get_role('administrator');
        foreach (Car_Middleware::ALL_CAPABILITIES as $cap => $desc) {
            $admin->add_cap($cap);
        }
    }

    private static function seed_default_catalogs(): void {
        // Insertar destinos/propósitos/gastos por defecto si la tabla está vacía
        global $wpdb;
        $table = $wpdb->prefix . 'car_catalogs';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) return;

        $defaults = [
            ['type' => 'destination', 'name' => 'Sede Central'],
            ['type' => 'destination', 'name' => 'Aeropuerto'],
            ['type' => 'destination', 'name' => 'Taller'],
            ['type' => 'purpose',     'name' => 'Reunión'],
            ['type' => 'purpose',     'name' => 'Entrega de documentos'],
            ['type' => 'purpose',     'name' => 'Comisión'],
            ['type' => 'expense',     'name' => 'Combustible'],
            ['type' => 'expense',     'name' => 'Peaje'],
            ['type' => 'expense',     'name' => 'Parqueadero'],
        ];
        foreach ($defaults as $row) {
            $wpdb->insert($table, array_merge($row, [
                'area_id'    => null,
                'active'     => 1,
                'sort_order' => 0,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ]));
        }
    }
}
```

---

## 6. Mapeo de Usuarios WordPress

### 6.1 Integración con `wp_users`

El plugin **NO crea una tabla de usuarios propia**. Usa `wp_users` para aprovechar:
- Sistema de sesiones de WordPress (`is_user_logged_in()`, `wp_get_current_user()`)
- Gestión de contraseñas con `wp_set_password()` y `wp_check_password()`
- Foto de perfil (compatible con plugins como Simple Local Avatars)
- Recuperación de contraseña nativa de WordPress

### 6.2 User Meta adicional

```php
// user_meta adicional para el plugin
add_user_meta($userId, 'car_global_role', 'editor');        // owner|primary_admin|admin|editor
add_user_meta($userId, 'car_selected_area', $areaId);       // Área activa en la sesión
add_user_meta($userId, 'car_selected_vehicle', $vehicleId); // Vehículo activo en la sesión
add_user_meta($userId, 'car_created_by', $creatorId);       // Quién lo creó en el sistema
add_user_meta($userId, 'car_active', true);                 // Si está activo en el plugin
```

### 6.3 Restricción de Acceso

Los usuarios del plugin acceden exclusivamente al **área de administración de WordPress** (panel `/wp-admin`). No necesitan acceder al frontend público del sitio.

**Opción A — Solo panel admin:**
- Todos los usuarios tienen rol WP `subscriber` (mínimo)
- Las capabilities `car_*` se agregan según su rol en el plugin
- Los menús del plugin solo aparecen si tienen `car_access`

**Opción B — Sin acceso al admin de WP (Headless):**
- Plugin crea sus propias páginas protegidas en el frontend de WP
- Usa shortcodes: `[car_dashboard]`, `[car_trips]`, etc.
- La autenticación se hace con `wp_signon()` en una página de login personalizada

> **Recomendación**: Usar **Opción A** (panel admin de WP) para simplificar la implementación inicial y aprovechar la seguridad y UX de WordPress.

### 6.4 Protección de Páginas Admin

```php
// En cada vista del plugin, verificar acceso
add_action('admin_menu', function () {
    add_menu_page(
        'CAR Fleet Manager',
        '🚗 Vehículos',
        'car_access',           // ← Solo usuarios con esta capability acceden
        'car-dashboard',
        'car_render_dashboard',
        'dashicons-car',
        30
    );
    // ...submenús
});
```

---

## 7. Base de Datos

### 7.1 Esquema Completo (CREATE TABLE)

```sql
-- 1. Áreas
CREATE TABLE IF NOT EXISTS `wp_car_areas` (
    `id`          VARCHAR(36)    NOT NULL,
    `name`        VARCHAR(100)   NOT NULL,
    `description` TEXT,
    `color`       VARCHAR(7)     DEFAULT '#667eea',
    `logo`        VARCHAR(500),
    `website`     VARCHAR(500),
    `active`      TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`  DATETIME       NOT NULL,
    `updated_at`  DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name` (`name`),
    KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Usuarios por área (tabla pivot con rol)
CREATE TABLE IF NOT EXISTS `wp_car_area_users` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `area_id`     VARCHAR(36)    NOT NULL,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `role`        ENUM('primary_admin','admin','editor') NOT NULL,
    `assigned_at` DATETIME       NOT NULL,
    `assigned_by` BIGINT UNSIGNED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_area_user` (`area_id`, `user_id`),
    KEY `idx_area` (`area_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Vehículos por área (tabla pivot)
CREATE TABLE IF NOT EXISTS `wp_car_area_vehicles` (
    `area_id`     VARCHAR(36) NOT NULL,
    `vehicle_id`  VARCHAR(36) NOT NULL,
    `assigned_at` DATETIME    NOT NULL,
    PRIMARY KEY (`area_id`, `vehicle_id`),
    KEY `idx_vehicle` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Vehículos
CREATE TABLE IF NOT EXISTS `wp_car_vehicles` (
    `id`               VARCHAR(36)   NOT NULL,
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
    `fuel_type`        ENUM('gasoline','diesel','electric','hybrid','gas')    DEFAULT 'gasoline',
    `transmission`     ENUM('manual','automatic')                             DEFAULT 'manual',
    `notes`            TEXT,
    `photos`           JSON,
    `unavailable_info` JSON,
    `transfer_history` JSON,
    `active`           TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`       DATETIME      NOT NULL,
    `updated_at`       DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plate` (`plate`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Salidas/Viajes unificados
CREATE TABLE IF NOT EXISTS `wp_car_trips` (
    `id`                  VARCHAR(36)   NOT NULL,
    `vehicle_id`          VARCHAR(36)   NOT NULL,
    `trip_type`           ENUM('rental','errand','maintenance','other') NOT NULL DEFAULT 'rental',
    `status`              ENUM('active','returned','cancelled')         NOT NULL DEFAULT 'active',
    -- Cliente (rental)
    `client_name`         VARCHAR(150),
    `client_phone`        VARCHAR(20),
    `client_email`        VARCHAR(150),
    `client_document`     VARCHAR(50),
    `rate_per_km`         DECIMAL(10,2) DEFAULT 0.00,
    -- Interno (errand/other)
    `responsible_name`    VARCHAR(150),
    `destination`         VARCHAR(200),
    `purpose`             VARCHAR(200),
    `trip_description`    TEXT,
    -- Mantenimiento
    `maint_subtype`       ENUM('preventive','corrective','inspection'),
    `maint_priority`      ENUM('low','medium','high','urgent'),
    `maint_description`   TEXT,
    `maint_provider`      VARCHAR(150),
    `maint_contact`       VARCHAR(50),
    `maint_estimated_cost` DECIMAL(10,2) DEFAULT 0.00,
    `maint_actual_cost`   DECIMAL(10,2) DEFAULT 0.00,
    `maint_completion_notes` TEXT,
    -- Salida
    `departure_datetime`  DATETIME      NOT NULL,
    `departure_odometer`  INT UNSIGNED  DEFAULT 0,
    `departure_fuel`      TINYINT UNSIGNED DEFAULT 100,
    -- Retorno
    `return_datetime`     DATETIME,
    `return_odometer`     INT UNSIGNED  DEFAULT 0,
    `return_fuel`         TINYINT UNSIGNED,
    `km_traveled`         INT UNSIGNED  DEFAULT 0,
    `total_amount`        DECIMAL(10,2) DEFAULT 0.00,
    `additional_charges`  DECIMAL(10,2) DEFAULT 0.00,
    `discounts`           DECIMAL(10,2) DEFAULT 0.00,
    `total_expenses`      DECIMAL(10,2) DEFAULT 0.00,
    `expenses_detail`     JSON,
    `cancellation_reason` TEXT,
    -- Metadatos
    `assigned_to`         BIGINT UNSIGNED,
    `created_by`          BIGINT UNSIGNED NOT NULL,
    `created_at`          DATETIME      NOT NULL,
    `updated_at`          DATETIME,
    `deleted`             TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_vehicle`   (`vehicle_id`),
    KEY `idx_status`    (`status`),
    KEY `idx_type`      (`trip_type`),
    KEY `idx_departure` (`departure_datetime`),
    KEY `idx_created`   (`created_at`),
    KEY `idx_deleted`   (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Catálogos (Destinos, Propósitos, Gastos)
CREATE TABLE IF NOT EXISTS `wp_car_catalogs` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `type`        ENUM('destination','purpose','expense') NOT NULL,
    `name`        VARCHAR(150)  NOT NULL,
    `description` VARCHAR(300),
    `icon`        VARCHAR(50),
    `active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
    `area_id`     VARCHAR(36),
    `created_by`  BIGINT UNSIGNED NOT NULL,
    `created_at`  DATETIME      NOT NULL,
    `updated_at`  DATETIME,
    PRIMARY KEY (`id`),
    KEY `idx_type`    (`type`),
    KEY `idx_area`    (`area_id`),
    KEY `idx_active`  (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Auditoría
CREATE TABLE IF NOT EXISTS `wp_car_audit` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operation`   VARCHAR(60)   NOT NULL,
    `entity_type` VARCHAR(30),
    `entity_id`   VARCHAR(36),
    `user_id`     BIGINT UNSIGNED,
    `user_role`   VARCHAR(20),
    `ip_address`  VARCHAR(45),
    `user_agent`  VARCHAR(300),
    `details`     JSON,
    `created_at`  DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_operation` (`operation`),
    KEY `idx_entity`    (`entity_type`, `entity_id`),
    KEY `idx_user`      (`user_id`),
    KEY `idx_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. API REST

### 8.1 Convenciones

- **Namespace:** `/wp-json/car/v1/`
- **Autenticación:** WordPress nonce (`X-WP-Nonce`) para peticiones desde el panel admin. Cookie de sesión de WordPress para la autenticación general.
- **Formato:** JSON en `Content-Type: application/json`
- **CSRF:** Todos los métodos `POST/PUT/PATCH/DELETE` requieren nonce válido
- **Permisos:** Verificados en `permission_callback` de cada endpoint

### 8.2 Endpoints Completos

```
# SESIÓN
POST   /wp-json/car/v1/session/area          -- Seleccionar área activa
POST   /wp-json/car/v1/session/vehicle       -- Seleccionar vehículo activo
GET    /wp-json/car/v1/session               -- Obtener estado de sesión actual

# ÁREAS
GET    /wp-json/car/v1/areas                 -- Listar áreas accesibles
POST   /wp-json/car/v1/areas                 -- Crear área [Owner]
GET    /wp-json/car/v1/areas/{id}            -- Ver área
PUT    /wp-json/car/v1/areas/{id}            -- Editar área [Owner]
DELETE /wp-json/car/v1/areas/{id}            -- Eliminar área [Owner]
POST   /wp-json/car/v1/areas/{id}/vehicles   -- Asignar vehículo [Owner]
DELETE /wp-json/car/v1/areas/{id}/vehicles/{vid}  -- Desasignar vehículo [Owner]

# VEHÍCULOS
GET    /wp-json/car/v1/vehicles              -- Listar vehículos accesibles
POST   /wp-json/car/v1/vehicles              -- Crear vehículo [Admin+]
GET    /wp-json/car/v1/vehicles/{id}         -- Ver vehículo
PUT    /wp-json/car/v1/vehicles/{id}         -- Editar vehículo [Admin+]
DELETE /wp-json/car/v1/vehicles/{id}         -- Eliminar (soft) [Admin+]
POST   /wp-json/car/v1/vehicles/{id}/photos  -- Subir foto
DELETE /wp-json/car/v1/vehicles/{id}/photos  -- Eliminar foto
POST   /wp-json/car/v1/vehicles/{id}/mark-unavailable   -- Dar de baja [Admin+]
POST   /wp-json/car/v1/vehicles/{id}/restore            -- Restaurar [Owner]
POST   /wp-json/car/v1/vehicles/{id}/transfer           -- Transferir área [Owner]

# SALIDAS/VIAJES
GET    /wp-json/car/v1/trips                 -- Listar salidas (paginado, filtros)
POST   /wp-json/car/v1/trips                 -- Registrar salida
GET    /wp-json/car/v1/trips/{id}            -- Ver salida
PUT    /wp-json/car/v1/trips/{id}            -- Editar salida activa [Admin+]
POST   /wp-json/car/v1/trips/{id}/checkin    -- Registrar retorno
POST   /wp-json/car/v1/trips/{id}/cancel     -- Cancelar [Admin+]
DELETE /wp-json/car/v1/trips/{id}            -- Eliminar [Owner]

# USUARIOS
GET    /wp-json/car/v1/users                 -- Listar usuarios del área
GET    /wp-json/car/v1/users/available       -- Usuarios disponibles para asignar a área
POST   /wp-json/car/v1/users                 -- Crear usuario [PrimaryAdmin+]
GET    /wp-json/car/v1/users/{id}            -- Ver usuario (perfil + áreas)
PUT    /wp-json/car/v1/users/{id}            -- Editar usuario
POST   /wp-json/car/v1/users/{id}/assign     -- Asignar usuario a área con rol
DELETE /wp-json/car/v1/users/{id}/assign/{area_id}  -- Quitar de área

# CATÁLOGOS
GET    /wp-json/car/v1/catalogs              -- Listar ?type=destination&area_id=
POST   /wp-json/car/v1/catalogs              -- Crear [PrimaryAdmin+]
PUT    /wp-json/car/v1/catalogs/{id}         -- Editar [PrimaryAdmin+]
DELETE /wp-json/car/v1/catalogs/{id}         -- Eliminar [PrimaryAdmin+]
PATCH  /wp-json/car/v1/catalogs/reorder      -- Reordenar { ids: [] }

# REPORTES
POST   /wp-json/car/v1/reports/preview       -- Vista previa en tabla
POST   /wp-json/car/v1/reports/export-csv    -- Descargar CSV
POST   /wp-json/car/v1/reports/export-pdf    -- Descargar PDF

# AUDITORÍA
GET    /wp-json/car/v1/audit                 -- Listar logs (filtros) [Owner]
GET    /wp-json/car/v1/audit/export-csv      -- Exportar auditoría [Owner]
DELETE /wp-json/car/v1/audit/cleanup         -- Limpiar logs antiguos [Owner]

# CONFIGURACIÓN
GET    /wp-json/car/v1/settings              -- Obtener ajustes [Owner]
PUT    /wp-json/car/v1/settings              -- Guardar ajustes [Owner]

# BACKUPS
GET    /wp-json/car/v1/backups               -- Listar backups [Owner]
POST   /wp-json/car/v1/backups               -- Crear backup [Owner]
POST   /wp-json/car/v1/backups/{id}/restore  -- Restaurar backup [Owner]
DELETE /wp-json/car/v1/backups/{id}          -- Eliminar backup [Owner]
POST   /wp-json/car/v1/backups/upload        -- Subir archivo .zip [Owner]
POST   /wp-json/car/v1/backups/cleanup       -- Limpiar backups antiguos [Owner]
POST   /wp-json/car/v1/backups/reset         -- Resetear aplicación [Owner]

# DASHBOARD / STATS
GET    /wp-json/car/v1/stats                 -- KPIs generales
GET    /wp-json/car/v1/stats/chart           -- Datos para gráficas ?type=km_by_vehicle&period=30d
```

### 8.3 Ejemplo de Endpoint REST (PHP)

```php
class Car_Rest_Trips {
    public function register_routes(): void {
        register_rest_route('car/v1', '/trips', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_trips'],
                'permission_callback' => fn() => current_user_can('car_view_trips'),
                'args'                => [
                    'type'   => ['type' => 'string', 'enum' => ['rental','errand','maintenance','other']],
                    'status' => ['type' => 'string', 'enum' => ['active','returned','cancelled']],
                    'page'   => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_trip'],
                'permission_callback' => fn() => current_user_can('car_create_trips'),
                'args'                => $this->get_create_args(),
            ],
        ]);

        register_rest_route('car/v1', '/trips/(?P<id>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_trip'],
                'permission_callback' => fn() => current_user_can('car_view_trips'),
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_trip'],
                'permission_callback' => fn() => current_user_can('car_edit_trips'),
            ],
        ]);
    }

    public function create_trip(WP_REST_Request $request): WP_REST_Response {
        // Validar nonce WP
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_REST_Response(['success' => false, 'message' => 'Token inválido'], 403);
        }

        $params = $request->get_json_params();
        $manager = new Car_Trip_Manager();
        $result = $manager->create($params);

        $code = $result['success'] ? 201 : 400;
        return new WP_REST_Response($result, $code);
    }
}
```

---

## 9. Páginas y Shortcodes

### 9.1 Páginas del Panel Admin de WordPress

Registradas con `add_menu_page()` / `add_submenu_page()`:

| Slug | Página | Capacidad requerida |
|------|--------|---------------------|
| `car-dashboard` | Dashboard analítico | `car_access` |
| `car-trips` | Salidas y Viajes | `car_view_trips` |
| `car-vehicles` | Flota vehicular | `car_view_vehicles` |
| `car-areas` | Áreas organizacionales | `car_manage_areas` |
| `car-users` | Usuarios del sistema | `car_view_users` |
| `car-reports` | Reportes y exportaciones | `car_view_reports` |
| `car-catalogs` | Catálogos (Destinos/Propósitos/Gastos) | `car_manage_catalogs` |
| `car-audit` | Auditoría y logs | `car_view_audit` |
| `car-settings` | Configuración del sistema | `car_manage_system` |

### 9.2 Estructura de Menú en WP Admin

```
🚗 CAR Fleet
├── Dashboard
├── Salidas           (car_view_trips)
├── Vehículos         (car_view_vehicles)
├── ─────────────────
├── Áreas             (car_manage_areas)
├── Usuarios          (car_view_users)
├── ─────────────────
├── Reportes          (car_view_reports)
├── Catálogos         (car_manage_catalogs)
├── Auditoría         (car_view_audit)
└── Configuración     (car_manage_system)
```

### 9.3 Shortcodes (Opcional para Frontend Público)

Si se requiere embeber el sistema en páginas públicas de WordPress:

| Shortcode | Descripción |
|-----------|-------------|
| `[car_dashboard]` | Dashboard embebido |
| `[car_trips]` | Listado de salidas del área |
| `[car_select_area]` | Selector de área (como landing post-login) |

---

## 10. CRUD Catálogos

### 10.1 Página de Catálogos (`page-catalogs.php`)

La página de catálogos tiene 3 pestañas — Destinos, Propósitos y Gastos — con las mismas funcionalidades:

**Características:**
- Tabla con columnas: Nombre, Descripción, Área (Global o nombre del área), Estado (activo/inactivo), Acciones
- Botón "Crear" abre modal con campos: Nombre*, Descripción, Ícono (selector Bootstrap Icons), Área (Global o selector de área), Activo
- Edición en modal
- Eliminación con confirmación (solo si no tiene registros asociados, sino desactivar)
- Reordenamiento por drag & drop (usando Sortable.js)
- Filtro por área

### 10.2 Validaciones

```php
class Car_Catalog_Manager {
    public function create(array $data): array {
        // Sanitizar
        $name    = sanitize_text_field($data['name'] ?? '');
        $type    = in_array($data['type'], ['destination','purpose','expense']) ? $data['type'] : '';
        $area_id = !empty($data['area_id']) ? sanitize_text_field($data['area_id']) : null;

        // Validar
        if (empty($name) || empty($type)) {
            return ['success' => false, 'message' => 'Nombre y tipo son requeridos'];
        }

        // Permisos: catálogos globales solo para Owner; por área para PrimaryAdmin+
        if ($area_id === null && !current_user_can('car_manage_system')) {
            return ['success' => false, 'message' => 'No tienes permiso para crear catálogos globales'];
        }
        if ($area_id !== null && !current_user_can('car_manage_catalogs')) {
            return ['success' => false, 'message' => 'No tienes permiso para crear catálogos de área'];
        }

        // Verificar duplicados
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}car_catalogs WHERE type = %s AND name = %s AND area_id <=> %s",
            $type, $name, $area_id
        ));
        if ($exists) {
            return ['success' => false, 'message' => 'Ya existe un catálogo con ese nombre'];
        }

        // Insertar
        $wpdb->insert("{$wpdb->prefix}car_catalogs", [
            'type'       => $type,
            'name'       => $name,
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'icon'       => sanitize_text_field($data['icon'] ?? ''),
            'area_id'    => $area_id,
            'active'     => 1,
            'sort_order' => 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);

        Car_Audit_Manager::log('catalog_create', 'catalog', (string)$wpdb->insert_id, [
            'type' => $type, 'name' => $name
        ]);

        return ['success' => true, 'id' => $wpdb->insert_id];
    }
}
```

---

## 11. Auditoría

### 11.1 Clase Car_Audit_Manager

```php
class Car_Audit_Manager {
    /**
     * Registrar una operación en el log de auditoría
     */
    public static function log(
        string  $operation,
        string  $entityType,
        string  $entityId,
        array   $details = []
    ): void {
        global $wpdb;

        $user    = wp_get_current_user();
        $role    = get_user_meta($user->ID, 'car_global_role', true) ?: 'unknown';
        $ip      = self::get_client_ip();
        $agent   = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $wpdb->insert("{$wpdb->prefix}car_audit", [
            'operation'   => $operation,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'user_id'     => $user->ID,
            'user_role'   => $role,
            'ip_address'  => $ip,
            'user_agent'  => substr($agent, 0, 300),
            'details'     => wp_json_encode($details),
            'created_at'  => current_time('mysql'),
        ]);
    }

    private static function get_client_ip(): string {
        // No confiar en headers manipulables; usar REMOTE_ADDR como fuente primaria
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
```

### 11.2 Pantalla de Auditoría

**Filtros disponibles:**
- Tipo de operación (agrupados por categoría con `<optgroup>`)
- Usuario (select dinámico cargado desde API)
- Rango de fechas (desde / hasta)
- Dirección IP

**Columnas de la tabla:**
| Columna | Contenido |
|---------|-----------|
| Fecha/Hora | Timestamp formateado con hora |
| Operación | Badge con color semántico e icono |
| Usuario | Nombre + badge de rol |
| Entidad | Tipo de entidad + ID parcial |
| IP | Dirección IP con icono de red |
| Acciones | Botón para expandir detalles JSON |

**Exportación:** Botón "Exportar CSV" aplica los filtros activos y descarga el log.

**Limpieza de logs:** Modal de confirmación para eliminar logs con más de N días (configurable).

---

## 12. Reportes

### 12.1 Tipos de Reportes

| Tipo | Descripción | Disponible para |
|------|-------------|-----------------|
| `trips` | Listado de todas las salidas con KPIs | Owner, PrimaryAdmin, Admin |
| `maintenances` | Salidas de tipo mantenimiento con costos | Owner, PrimaryAdmin, Admin |
| `costs` | Costos consolidados por vehículo/área | Owner, PrimaryAdmin |
| `vehicles` | Estado de la flota con historial resumido | Owner, PrimaryAdmin, Admin |
| `mileage` | Kilometraje por vehículo y período | Owner, PrimaryAdmin, Admin |

### 12.2 Filtros Globales

- **Período:** Rango de fechas (desde / hasta)
- **Área:** Selector (Owner ve todas; Admin ve solo las suyas)
- **Vehículo:** Selector (filtrado por área seleccionada)
- **Tipo de salida:** rental / errand / maintenance / other

### 12.3 Exportaciones

| Formato | Librería | Implementación |
|---------|---------|----------------|
| **CSV** | PHP nativo | `fputcsv()` + headers `Content-Disposition: attachment` |
| **PDF** | [mPDF](https://github.com/mpdf/mpdf) o [Dompdf](https://github.com/dompdf/dompdf) | Renderizar template HTML → PDF |
| **Vista previa HTML** | DataTables | Tabla interactiva en pantalla |

### 12.4 Log de Exportaciones

Cada exportación se registra en `wp_car_audit` con operación `report_export`:
```json
{
  "report_type": "trips",
  "format": "csv",
  "filters": { "date_from": "2025-01-01", "area_id": "uuid-area", "trip_type": "rental" },
  "rows_exported": 247
}
```

---

## 13. Backups y Configuración

### 13.1 Sistema de Backups

El plugin hace backup de sus tablas personalizadas exportándolas a archivos SQL + CSV comprimidos en ZIP.

**Contenido de un backup:**
```
car_backup_YYYY-MM-DD_HHMMSS.zip
├── metadata.json          -- { version, created_at, table_prefix, wp_version }
├── car_areas.sql
├── car_area_users.sql
├── car_area_vehicles.sql
├── car_vehicles.sql
├── car_trips.sql
├── car_catalogs.sql
├── car_settings.json      -- Exportación de wp_options con prefijo car_
└── uploads/
    ├── areas/             -- Logos de áreas
    └── vehicles/          -- Fotos de vehículos
```

**Almacenamiento:** `wp-content/uploads/car/backups/` (directorio protegido con `.htaccess`)

### 13.2 Restauración

```
1. Seleccionar backup de la lista
2. Confirmar con modal de advertencia
3. Car_Backup_Manager::restore($backupId)
   → Descomprimir ZIP en carpeta temporal
   → Verificar metadata.json (compatibilidad de versión)
   → Truncar tablas del plugin (NO tablas de WP)
   → Ejecutar archivos .sql
   → Restaurar wp_options para ajustes del plugin
   → Restaurar uploads si el backup los incluye
   → Registrar en auditoría
4. Redirigir al dashboard con mensaje de éxito
```

### 13.3 Resetear Aplicación

**Preserva:**
- `wp_users` y toda la información de usuarios WordPress
- `wp_car_area_users` (asignaciones de roles)
- `wp_car_areas` (estructura de áreas)
- Imágenes de perfiles y logos de áreas en `uploads/car/`

**Limpia:**
- `wp_car_vehicles`
- `wp_car_area_vehicles`
- `wp_car_trips`
- `wp_car_audit`
- Fotos de vehículos en `uploads/car/vehicles/`
- Todos los backups en `uploads/car/backups/`

**Requiere:** Escribir "RESETEAR" en campo de confirmación + doble clic de confirmación.

### 13.4 Configuración del Sistema

La página de ajustes usa la WordPress Settings API con secciones:

| Sección | Ajustes |
|---------|---------|
| **General** | Nombre comercial, logo, colores por defecto |
| **Negocio** | Km antes de mantenimiento, bloquear renta con mtto pendiente, permitir editor en mantenimientos |
| **Seguridad** | Longitud mínima contraseña, forzar cambio periódico |
| **Auditoría** | Retención de logs en días |

---

## 14. Seguridad

### 14.1 Lista de Verificación OWASP

| Riesgo OWASP | Mitigación en el Plugin |
|-------------|-------------------------|
| **Inyección SQL** | Uso exclusivo de `$wpdb->prepare()`, `$wpdb->insert()`, `$wpdb->update()`. Nunca concatenar SQL. |
| **Autenticación rota** | WordPress maneja sesiones. Verificar `is_user_logged_in()` + nonce en cada petición. |
| **Exposición de datos** | Sanitizar toda salida con `esc_html()`, `esc_attr()`, `wp_json_encode()`. No exponer datos sensibles en respuestas. |
| **XXE** | Sin procesamiento de XML en el plugin. |
| **Control de acceso** | `permission_callback` en cada endpoint REST. Verificar rol contextual por área. |
| **Configuración incorrecta** | `.htaccess` en directorios de datos. No exponer rutas internas. |
| **XSS** | Sanitizar inputs con `sanitize_text_field()`, `sanitize_email()`, `wp_kses_post()`. Escapar salidas. |
| **Deserialización insegura** | Usar `wp_json_encode()`/`json_decode()`. No usar `unserialize()`. |
| **Componentes vulnerables** | Usar solo librerías mantenidas. Validar versiones de dependencias PDF. |
| **Logging insuficiente** | Sistema de auditoría propio (`wp_car_audit`). Registrar todas las operaciones sensibles. |

### 14.2 Validación de Uploads

```php
function car_validate_image_upload(array $file): array {
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $max_size      = 2 * 1024 * 1024; // 2MB

    // Verificar tipo MIME real con finfo (no extensión)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types, true)) {
        return ['valid' => false, 'message' => 'Tipo de archivo no permitido'];
    }
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'message' => 'La imagen no debe superar 2MB'];
    }
    return ['valid' => true];
}
```

### 14.3 Nonce Strategy

```php
// Frontend: incluir nonce en JS
wp_localize_script('car-admin-js', 'carConfig', [
    'nonce'   => wp_create_nonce('wp_rest'),
    'apiBase' => rest_url('car/v1/'),
    'userId'  => get_current_user_id(),
]);

// En endpoint REST: verificar nonce (WordPress lo hace automáticamente con WP_REST_Server)
// Para endpoints con cookie auth + nonce:
'permission_callback' => function() {
    return is_user_logged_in() && current_user_can('car_access');
},
```

---

## 15. Hoja de Ruta de Implementación

### Fase 1 — Fundamentos (Sprint 1-2)
- [ ] Crear estructura de carpetas del plugin
- [ ] `Car_Installer`: crear todas las tablas con `dbDelta()`
- [ ] `Car_Middleware`: capabilities, roles, helper de verificación
- [ ] Mapeo de usuarios WP + user_meta
- [ ] Registro de menús admin básicos
- [ ] CRUD de Áreas (sin frontend de asignación de usuarios)
- [ ] Seed de catálogos por defecto

### Fase 2 — Vehículos y Catálogos (Sprint 3-4)
- [ ] CRUD completo de Vehículos con fotos
- [ ] Asignación de vehículos a áreas
- [ ] CRUD de Catálogos (Destinos, Propósitos, Gastos) con drag & drop
- [ ] Dar de baja / restaurar / transferir vehículos
- [ ] Página de selección de área al ingresar

### Fase 3 — Salidas / Trips (Sprint 5-6)
- [ ] Formulario unificado de salidas (4 tipos)
- [ ] Modal de retorno / check-in
- [ ] Cancelación de salidas
- [ ] Tabla principal con filtros y DataTables
- [ ] Sistema de catálogos en formularios (destinos, propósitos, gastos)

### Fase 4 — Usuarios RBAC (Sprint 7)
- [ ] Flujo de asignación de usuarios existentes a área
- [ ] Creación de nuevos usuarios
- [ ] Roles contextuales por área
- [ ] Jerarquía de permisos (quién puede crear a quién)

### Fase 5 — Dashboard y Reportes (Sprint 8-9)
- [ ] KPIs en tiempo real
- [ ] Gráficas con Chart.js (km por vehículo, uso por área, actividad mensual)
- [ ] Módulo de reportes con filtros
- [ ] Exportación CSV y PDF

### Fase 6 — Auditoría, Backups y Configuración (Sprint 10)
- [ ] Sistema de auditoría completo
- [ ] Exportación y filtrado de logs
- [ ] Backups exportar/importar/restaurar
- [ ] Resetear aplicación
- [ ] Página de configuración con Settings API

### Fase 7 — Pulido y UX (Sprint 11-12)
- [ ] Dark/Light mode (Bootstrap 5.3 `data-bs-theme`)
- [ ] Sidebar colapsable con persistencia en localStorage
- [ ] Tooltips en todas las tablas
- [ ] Notificaciones de mantenimientos pendientes
- [ ] Responsive completo en panel admin

---

## Apéndice: Estructura de Archivos `wp-content/uploads`

```
wp-content/uploads/car/
├── .htaccess              ← deny from all (protege acceso directo)
├── areas/
│   ├── {area-uuid}.png    ← Logos de áreas
│   └── ...
├── vehicles/
│   ├── {vehicle-id}_1.jpg ← Fotos de vehículos
│   └── ...
├── profiles/
│   ├── {wp-user-id}.jpg   ← Fotos de perfil (o usar WordPress native)
│   └── ...
└── backups/
    ├── .htaccess           ← deny from all
    ├── car_backup_2025-12-01_103000.zip
    └── ...
```

> **Nota:** Para las fotos de perfil se recomienda usar plugins de WordPress existentes como [Simple Local Avatars](https://wordpress.org/plugins/simple-local-avatars/) para no reinventar esa funcionalidad.

---

*Documento generado el 5 de abril de 2026. Versión 1.0*
