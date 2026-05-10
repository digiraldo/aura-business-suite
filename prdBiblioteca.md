# PRD — Módulo de Biblioteca (Aura Business Suite)

> **Versión:** 1.0 | **Fecha:** Abril 2026
> **Contexto:** Este módulo forma parte de Aura Business Suite. El sistema de áreas (`wp_aura_areas`), el sistema de permisos granulares CBAC (`aura_*` capabilities), el sistema de notificaciones y el sistema de usuarios de WordPress **ya existen e implementados**. No se crean roles nuevos ni tablas nuevas de áreas o usuarios.

---

## 1. Resumen Ejecutivo

El **Módulo de Biblioteca** permite a organizaciones (institutos, fundaciones, ranchos educativos, empresas con acervo bibliográfico) gestionar su inventario de libros y materiales de lectura dentro de Aura Suite: catálogo usando el **Sistema de Clasificación Decimal Dewey (CDD)**, control de préstamos y devoluciones, sistema de reservas, multas por retraso, búsqueda avanzada, y notificaciones automáticas por email y WhatsApp.

El módulo se integra directamente con:
- `wp_aura_areas` — Áreas organizacionales ya existentes (el acervo puede pertenecer a un área o ser global)
- Sistema CBAC de Aura Suite — Capabilities `aura_library_*` asignadas vía el gestor de permisos existente
- Sistema de notificaciones global — Email y WhatsApp configurados en el panel de Aura Suite
- Módulo de Estudiantes — Un estudiante puede tener préstamos activos visibles en su perfil
- Módulo de Finanzas — Las multas por retraso pueden registrarse como ingresos en Finanzas

**Posición en el menú WP Admin:** `5.0` (entre Vehículos `4.9` y Electricidad `5.1`)

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
| Módulo Finanzas | `modules/financial/` | ✅ Implementado (integración de multas) |
| Módulo Estudiantes | `modules/students/` | ✅ Implementado (préstamos en perfil) |
| Configuración global | `wp_aura_settings` | ✅ Implementado |

### 2.2 Convenciones del proyecto a respetar

- **Prefijo de tablas:** `wp_aura_` (ej. `wp_aura_library_books`, `wp_aura_library_loans`)
- **Prefijo de capabilities:** `aura_library_` (ej. `aura_library_create`)
- **Prefijo de opciones:** `aura_library_` (ej. `aura_library_loan_days`)
- **Namespace REST:** `/wp-json/aura/v1/library/`
- **Text domain:** `aura-suite`
- **Clases PHP:** prefijo `Aura_Library_*`
- **Archivos:** `class-library-*.php` dentro de `modules/library/`
- **Templates:** `templates/library/`
- **Assets:** `assets/css/library-*.css`, `assets/js/library-*.js`
- **Menú WP Admin:** menú propio de nivel top con posición `5.0`
- **Tablas:** todas deben usar el estándar `5.6 DataTables Responsive` del PRD.md

---

## 3. Permisos CBAC

### 3.1 Capabilities del módulo

Estas capabilities deben declararse en `Aura_Roles_Manager::get_all_capabilities()` dentro de `modules/common/class-roles-manager.php`. Se propagan automáticamente al rol `administrator` vía `register_all_capabilities()` en la activación y `ensure_admin_capabilities()` en cada `admin_init`:

| Capability | Descripción | Acción protegida |
|-----------|-------------|-----------------|
| `aura_library_access` | ⭐ Ver el módulo (menú visible) | Acceso al panel de biblioteca |
| `aura_library_create` | Agregar libros al catálogo | Formulario de nuevo libro |
| `aura_library_edit` | Editar información de libros | Formulario de edición de libro |
| `aura_library_delete` | Eliminar libros del catálogo | Botón eliminar (soft delete) |
| `aura_library_view_catalog` | Ver catálogo completo de libros | Listado de libros |
| `aura_library_loan_create` | Registrar préstamo de libro | Formulario de nuevo préstamo |
| `aura_library_loan_return` | Registrar devolución de libro | Botón / formulario de devolución |
| `aura_library_loan_extend` | Extender plazo de préstamo | Botón de extensión de préstamo |
| `aura_library_view_loans_own` | Ver solo préstamos propios | Listado de préstamos (filtrado) |
| `aura_library_view_loans_all` | Ver todos los préstamos activos | Listado de préstamos (completo) |
| `aura_library_reports` | Ver reportes y estadísticas | Página de reportes |
| `aura_library_alerts` | Recibir alertas de devoluciones vencidas | Notificaciones del módulo |
| `aura_library_settings` | ⭐ Configurar ajustes del módulo | Página de configuración |
| `aura_library_audit` | ⭐ Ver log de auditoría | Página de auditoría |

> ⭐ = Capabilities administrativas críticas. Se conceden solo a bibliotecarios o administradores del sistema.

### 3.2 Mapping CBAC — Perfiles típicos

> El CBAC de Aura Suite asigna capabilities individualmente por usuario. La tabla siguiente es referencia orientativa para la configuración inicial:

| Perfil Sugerido | Capabilities asignadas |
|----------------|------------------------|
| **Administrador del sistema** | Todas las caps `aura_library_*` |
| **Bibliotecario** | `access`, `create`, `edit`, `view_catalog`, `loan_create`, `loan_return`, `loan_extend`, `view_loans_all`, `reports`, `alerts` |
| **Auxiliar de biblioteca** | `access`, `view_catalog`, `loan_create`, `loan_return`, `view_loans_all` |
| **Usuario / Lector** | `access`, `view_catalog`, `view_loans_own` |
| **Estudiante** | `access`, `view_catalog`, `view_loans_own` |

### 3.3 Visibilidad del menú

- El menú principal "Biblioteca" solo aparece si el usuario tiene `aura_library_access`.
- Submenús sensibles (Configuración, Auditoría) requieren `aura_library_settings` y `aura_library_audit` respectivamente.
- Los lectores sin `aura_library_view_loans_all` solo ven sus propios préstamos.

---

## 4. Base de Datos

> Usar `dbDelta()` para toda la creación de tablas en el método `create_tables()`.
> **NO** crear tablas de áreas ni de usuarios — ya existen.

### 4.1 `wp_aura_library_books`

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_library_books` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dewey_number`     VARCHAR(30)     NOT NULL DEFAULT '',
    `title`            VARCHAR(255)    NOT NULL,
    `subtitle`         VARCHAR(255)    DEFAULT NULL,
    `author`           VARCHAR(255)    NOT NULL,
    `isbn`             VARCHAR(30)     DEFAULT NULL,
    `publisher`        VARCHAR(150)    DEFAULT NULL,
    `year_published`   YEAR            DEFAULT NULL,
    `edition`          VARCHAR(50)     DEFAULT NULL,
    `language`         VARCHAR(50)     NOT NULL DEFAULT 'Español',
    `pages`            SMALLINT UNSIGNED DEFAULT NULL,
    `category`         VARCHAR(100)    DEFAULT NULL,
    `subcategory`      VARCHAR(100)    DEFAULT NULL,
    `physical_location`VARCHAR(100)    DEFAULT NULL,
    `shelf_code`       VARCHAR(50)     DEFAULT NULL,
    `total_copies`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `available_copies` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `cover_image_id`   BIGINT UNSIGNED  DEFAULT NULL,
    `description`      TEXT            DEFAULT NULL,
    `keywords`         TEXT            DEFAULT NULL,
    `area_id`          BIGINT UNSIGNED  DEFAULT NULL,
    `status`           ENUM('available','unavailable','reference_only','lost','withdrawn') NOT NULL DEFAULT 'available',
    `created_by`       BIGINT UNSIGNED  NOT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `dewey_number` (`dewey_number`),
    KEY `isbn` (`isbn`),
    KEY `status` (`status`),
    KEY `area_id` (`area_id`),
    KEY `available_copies` (`available_copies`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 `wp_aura_library_loans`

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_library_loans` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `book_id`          BIGINT UNSIGNED NOT NULL,
    `borrower_user_id` BIGINT UNSIGNED NOT NULL,
    `loan_date`        DATE            NOT NULL,
    `due_date`         DATE            NOT NULL,
    `return_date`      DATE            DEFAULT NULL,
    `extended_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status`           ENUM('active','returned','overdue','lost','extended') NOT NULL DEFAULT 'active',
    `notes`            TEXT            DEFAULT NULL,
    `registered_by`    BIGINT UNSIGNED NOT NULL,
    `return_registered_by` BIGINT UNSIGNED DEFAULT NULL,
    `fine_amount`      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `fine_paid`        TINYINT(1)      NOT NULL DEFAULT 0,
    `fine_transaction_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `book_id` (`book_id`),
    KEY `borrower_user_id` (`borrower_user_id`),
    KEY `status` (`status`),
    KEY `due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3 `wp_aura_library_reservations`

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_library_reservations` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `book_id`          BIGINT UNSIGNED NOT NULL,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `reserved_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notified_at`      DATETIME        DEFAULT NULL,
    `expires_at`       DATETIME        DEFAULT NULL,
    `status`           ENUM('waiting','notified','fulfilled','cancelled','expired') NOT NULL DEFAULT 'waiting',
    `notes`            TEXT            DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `book_id` (`book_id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4 `wp_aura_library_audit`

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_library_audit` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `action`      VARCHAR(100)    NOT NULL,
    `entity_type` VARCHAR(50)     NOT NULL,
    `entity_id`   BIGINT UNSIGNED NOT NULL,
    `old_data`    LONGTEXT        DEFAULT NULL,
    `new_data`    LONGTEXT        DEFAULT NULL,
    `ip_address`  VARCHAR(45)     DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Sistema de Clasificación Decimal Dewey (CDD)

El módulo utiliza el CDD como sistema de clasificación principal. Este es el estándar internacional más usado en bibliotecas públicas y escolares.

### 5.1 Clases principales CDD (10 divisiones)

| Número | Clase |
|--------|-------|
| 000 | Ciencias de la Computación, Información y Obras Generales |
| 100 | Filosofía y Psicología |
| 200 | Religión |
| 300 | Ciencias Sociales |
| 400 | Lengua y Lingüística |
| 500 | Ciencias Puras (Matemáticas y Ciencias Naturales) |
| 600 | Tecnología y Ciencias Aplicadas |
| 700 | Artes y Recreación |
| 800 | Literatura y Retórica |
| 900 | Historia, Geografía y Biografía |

### 5.2 Uso del número Dewey en el módulo

- El campo `dewey_number` acepta el número completo con decimales (ej. `220.5`, `629.8`, `R 92 GAR` para referencia).
- La búsqueda en el catálogo filtra por rango Dewey (ej. todos los libros de 200-299).
- La vista del catálogo puede agruparse por clase principal Dewey.
- En la portada del libro se muestra el número Dewey como código de ubicación.

### 5.3 Ubicación física complementaria

Además del número Dewey, cada libro tiene:
- `physical_location`: sala/estante donde está físicamente (ej. "Sala Principal", "Sala Infantil", "Depósito")
- `shelf_code`: código del estante (ej. "A3-Fila2", "Ref-B1")

---

## 6. Estructura de Archivos del Módulo

```
modules/library/
├── class-library-module.php        # Singleton principal — bootstrap
├── class-library-setup.php         # Instalación de tablas, migración
├── class-library-capabilities.php  # Registro de capabilities en Roles Manager
├── admin/
│   ├── class-library-admin.php     # Registro de menús, enqueue de assets
│   ├── class-library-books.php     # CRUD de libros + búsqueda Dewey
│   ├── class-library-loans.php     # Gestión de préstamos y devoluciones
│   ├── class-library-reservations.php # Cola de reservas
│   ├── class-library-fines.php     # Cálculo y registro de multas
│   ├── class-library-reports.php   # Reportes y estadísticas
│   ├── class-library-audit.php     # Log de auditoría
│   └── class-library-settings.php  # Configuración del módulo
├── class-library-notifications.php # Triggers de email/WhatsApp
├── class-library-api.php           # Endpoints REST API
└── class-library-cron.php          # Tareas programadas (cron jobs)

templates/library/
├── dashboard.php                   # Dashboard principal con KPIs
├── books-list.php                  # Catálogo de libros (DataTables)
├── book-form.php                   # Formulario crear/editar libro
├── book-detail.php                 # Vista detalle de un libro
├── loans-list.php                  # Listado de préstamos (DataTables)
├── loan-form.php                   # Formulario de nuevo préstamo
├── reservations-list.php           # Listado de reservas (DataTables)
├── reports.php                     # Reportes y estadísticas
├── audit.php                       # Log de auditoría (DataTables)
└── settings.php                    # Configuración del módulo

assets/css/
├── library-dashboard.css
├── library-books.css
└── library-loans.css

assets/js/
├── library-books.js
├── library-loans.js
└── library-dashboard.js
```

---

## 7. Menú de Administración

### 7.1 Registro del menú

```php
// En class-library-admin.php — hook: admin_menu (prioridad 10)
add_menu_page(
    __( 'Biblioteca — AURA', 'aura-suite' ),
    __( 'Biblioteca', 'aura-suite' ),
    'aura_library_access',
    'aura-library',
    [ __CLASS__, 'render_dashboard' ],
    'dashicons-book',
    5.0
);

// Submenús
add_submenu_page( 'aura-library', __('Dashboard', 'aura-suite'), __('Dashboard', 'aura-suite'),
    'aura_library_access', 'aura-library', [ __CLASS__, 'render_dashboard' ] );

add_submenu_page( 'aura-library', __('Catálogo de Libros', 'aura-suite'), __('Catálogo', 'aura-suite'),
    'aura_library_view_catalog', 'aura-library-books', [ __CLASS__, 'render_books' ] );

add_submenu_page( 'aura-library', __('Préstamos', 'aura-suite'), __('Préstamos', 'aura-suite'),
    'aura_library_view_loans_all', 'aura-library-loans', [ __CLASS__, 'render_loans' ] );

add_submenu_page( 'aura-library', __('Reservas', 'aura-suite'), __('Reservas', 'aura-suite'),
    'aura_library_view_loans_all', 'aura-library-reservations', [ __CLASS__, 'render_reservations' ] );

add_submenu_page( 'aura-library', __('Reportes', 'aura-suite'), __('Reportes', 'aura-suite'),
    'aura_library_reports', 'aura-library-reports', [ __CLASS__, 'render_reports' ] );

add_submenu_page( 'aura-library', __('Auditoría', 'aura-suite'), __('Auditoría', 'aura-suite'),
    'aura_library_audit', 'aura-library-audit', [ __CLASS__, 'render_audit' ] );

add_submenu_page( 'aura-library', __('Configuración', 'aura-suite'), __('Configuración', 'aura-suite'),
    'aura_library_settings', 'aura-library-settings', [ __CLASS__, 'render_settings' ] );
```

### 7.2 Estructura del menú resultante

```
📚 Biblioteca
├── Dashboard
├── Catálogo
├── Préstamos
├── Reservas
├── Reportes
├── Auditoría
└── Configuración
```

---

## 8. Funcionalidades Detalladas

### 8.1 Dashboard (KPIs y widgets)

**Tarjetas de estadísticas rápidas (fila superior):**
- Total de libros en catálogo
- Libros disponibles para préstamo
- Préstamos activos (en mano)
- Préstamos vencidos (urgente)
- Reservas en espera
- Multas pendientes de cobro

**Gráficos (Chart.js — ya incluido en el plugin):**
- Préstamos por mes (últimos 12 meses) — gráfico de barras
- Libros más prestados (top 10) — gráfico horizontal de barras
- Distribución por clase Dewey — gráfico de donut

**Listas rápidas:**
- Préstamos que vencen en los próximos 3 días
- Préstamos ya vencidos (ordenados por días de retraso)
- Últimas devoluciones registradas

### 8.2 Catálogo de Libros

**Vista de tabla (DataTables Responsive — estándar `5.6` del PRD.md):** ✅ Implementado

| # | Columna | `responsivePriority` | Descripción |
|---|---------|---------------------|-------------|
| 0 | Portada (imagen) | 2 | Thumbnail de la portada; hover muestra imagen grande (CSS `.aura-img-preview`) |
| 1 | Título | 1 | Título + subtítulo del libro (siempre visible) |
| 2 | Autor | 2 | Autor(es) |
| 3 | Estado | 1 | Badge: Disponible / Sin stock / Solo consulta / Retirado / **En Préstamo** (calculado: cuando `status=available` y `available_copies=0`, se muestra badge morado "En Préstamo" en lugar del estado editorial) |
| 4 | Copias | 2 | `X / Y disponibles` — color dinámico (verde/amarillo/rojo). Si hay 0 copias disponibles, muestra debajo `N prestadas` como texto auxiliar. Tooltip con conteo exacto al pasar el cursor. |
| 5 | Dewey | 10000 | Número de clasificación Dewey (se oculta primero) |
| 6 | Categoría | 10000 | Categoría/subcategoría del libro (se oculta primero) |
| 7 | Ubicación | 10000 | Sala + código de estante (se oculta primero) |
| 8 | Acciones | 1 | Botones redondos: Ver detalle, Editar, Reservar, Eliminar |

> **Inicialización DataTables:** la tabla usa AJAX/jQuery personalizado para la paginación server-side.
> `DataTable()` se llama en `initDataTable()` (invocada al final de cada `renderTable()`) con
> `paging:false, searching:false, info:false, ordering:false` para no duplicar la paginación custom.

**Filtros sobre la tabla:**
- Búsqueda de texto libre (título, autor, ISBN, palabra clave)
- Filtro por clase Dewey (dropdown con las 10 clases principales)
- Filtro por categoría interna
- Filtro por ubicación física
- Filtro por estado
- Filtro por área organizacional

**Formulario de nuevo/editar libro:**
```
INFORMACIÓN PRINCIPAL
├── Número Dewey *                    (text, validación formato Dewey)
├── Título *                          (text, max 255)
├── Subtítulo                         (text, max 255)
├── Autor(es) *                       (text, max 255)
├── ISBN                              (text, validación ISBN-10/ISBN-13)
├── Editorial                         (text, max 150)
├── Año de publicación                (number, 1800–año actual)
├── Edición                           (text, max 50)
├── Idioma *                          (select: Español, Inglés, Francés, Otro)
└── Páginas                           (number)

CLASIFICACIÓN Y UBICACIÓN
├── Categoría                         (text/select)
├── Subcategoría                      (text)
├── Sala / Ubicación física           (text, ej. "Sala Principal")
├── Código de estante                 (text, ej. "A3-F2")
└── Área organizacional               (select del catálogo wp_aura_areas, opcional)

INVENTARIO
├── Total de ejemplares *             (number, min 1)
├── Estado *                          (select: Disponible, Sin stock, Solo consulta, Perdido, Retirado)
└── Solo consulta (no préstamo)       (checkbox)

DESCRIPCIÓN
├── Descripción / Resumen             (textarea)
└── Palabras clave                    (text, separadas por coma)

IMAGEN DE PORTADA
└── Seleccionar portada               (wp_media uploader — igual que módulo Inventario)
```

**Detalle de libro (book-detail.php):**
- Portada grande
- Todos los datos del libro
- Historial de préstamos del libro (DataTables paginado)
- Lista de reservas activas
- Botón "Registrar préstamo" si hay copias disponibles
- Botón "Agregar a reservas" si no hay copias disponibles

### 8.3 Préstamos

**Vista de tabla (DataTables Responsive):**

| Columna | `responsivePriority` | Descripción |
|---------|---------------------|-------------|
| ID | 10000 | Número de préstamo |
| Libro | 1 | Título + Dewey del libro |
| Lector | 1 | Nombre del usuario prestador |
| Fecha préstamo | 3 | Fecha de inicio del préstamo |
| Fecha devolución | 1 | Fecha límite — badge rojo si vencido |
| Estado | 1 | Badge: Activo / Vencido / Devuelto / Perdido / Cancelado |
| Extensiones | 5 | Número de veces extendido |
| Multa | 4 | Monto de multa acumulada si hay |
| Acciones | 1 | Registrar devolución, Extender (modal), Editar, Cancelar, Ver detalle |

**Filtros:**
- Buscar por nombre de lector o título de libro
- Filtro por estado (activo, vencido, devuelto)
- Filtro por rango de fechas
- Filtro por área del lector

**Formulario de nuevo préstamo (modal o página completa):**
```
├── Seleccionar libro *               (autocomplete por título, autor o Dewey)
├── Lector *                          (autocomplete por nombre o email de usuario WP)
├── Fecha de préstamo *               (date, default: hoy)
├── Fecha de devolución *             (date, default: hoy + días configurados)
└── Notas                             (textarea)
```

**Devolución:**
- Botón "Registrar devolución" desde la tabla o detalle.
- Indica fecha real de devolución.
- Calcula automáticamente si hay multa.
- Ofrece registrar la multa en Finanzas (integración con módulo Finanzas).
- Libera el ejemplar en la tabla de libros (incrementa `available_copies`).
- Verifica si hay reservas pendientes del libro → notifica al siguiente en la cola.

**Extensión de préstamo:**
- Botón disponible si el préstamo es activo y `extended_count` < límite configurado.
- Calcula nueva fecha límite: `nueva_fecha = due_date + días_de_extension`.
- Incrementa `extended_count`.
- Registra en auditoría.
- Envía notificación de confirmación al lector.

### 8.4 Reservas

Cuando un libro no tiene copias disponibles, el sistema permite agregar al usuario a una cola de espera:

- Muestra posición en la cola.
- Al registrar la devolución de un préstamo, el sistema notifica automáticamente al primero en la cola.
- La reserva tiene fecha de expiración configurable (ej. 48 horas para aceptar el préstamo).
- Si el usuario no responde antes de la expiración, se notifica al siguiente.

### 8.5 Multas

**Lógica de cálculo:**
```
días_de_retraso = fecha_devolución_real - due_date (si > 0)
multa = días_de_retraso * tarifa_diaria_configurada
```

**Opciones configurables:**
- Activar/desactivar multas (toggle)
- Tarifa diaria por retraso (monto en moneda del sistema)
- Período de gracia en días (ej. 1 día antes de empezar multa)
- Monto máximo de multa por préstamo (cap)

**Integración con Finanzas:**
- Al registrar multa como cobrada → crear ingreso en `wp_aura_transactions` con categoría "Multa Biblioteca".
- El campo `fine_transaction_id` en `wp_aura_library_loans` guarda el ID de la transacción en Finanzas.
- Checkbox en el formulario de devolución: "Registrar cobro de multa en Finanzas".

---

## 9. Notificaciones

### 9.1 Canales de notificación

El módulo Biblioteca hereda la configuración global de notificaciones de Aura Suite (email y WhatsApp) sin necesidad de configuración adicional fuera del módulo.

| Canal | Configuración global (ya existe) | Configuración del módulo |
|-------|----------------------------------|--------------------------|
| **Email** | Remitente, logo, nombre org. | `aura_library_email_alerts` (toggle), `aura_library_email_extra` (bibliotecario) |
| **WhatsApp** | Proveedor, token, número | Teléfono del lector en su perfil WP (`user_meta`) |

### 9.2 Triggers de notificación

| Evento | Canal | Destinatario | Contenido |
|--------|-------|-------------|-----------|
| Préstamo registrado | Email | Lector | Confirmación: libro, fecha devolución, ubicación |
| Préstamo registrado | Email | Bibliotecario (email extra) | Resumen del nuevo préstamo |
| 3 días antes del vencimiento | Email + WhatsApp | Lector | Recordatorio de devolución |
| Día del vencimiento | Email + WhatsApp | Lector | Alerta: devolución hoy |
| Préstamo vencido (post-vencimiento) | Email + WhatsApp | Lector + Bibliotecario | Alerta: préstamo vencido + multa acumulada |
| Devolución registrada | Email | Lector | Confirmación de devolución + multa si aplica |
| Reserva disponible | Email + WhatsApp | Usuario en cola | Notificación: el libro está disponible |
| Reserva expirada | Email | Usuario en cola | Aviso: la reserva expiró |

### 9.3 Implementación del cron

```php
// En class-library-cron.php
// Hook: wp_schedule_event — daily (ejecuta cada día a una hora configurable)
add_action( 'aura_library_daily_cron', [ __CLASS__, 'process_loan_alerts' ] );

// Lógica:
// 1. Obtener préstamos activos con due_date == hoy + 3 días → enviar recordatorio
// 2. Obtener préstamos activos con due_date == hoy → enviar alerta de vencimiento
// 3. Obtener préstamos activos con due_date < hoy  → marcar como 'overdue', enviar alerta vencido
// 4. Recalcular fine_amount en préstamos overdue
```

---

## 10. REST API

### 10.1 Endpoints del módulo

**Base URL:** `/wp-json/aura/v1/library/`

| Método | Endpoint | Capability requerida | Descripción |
|--------|----------|---------------------|-------------|
| `GET` | `/books` | `aura_library_view_catalog` | Listar libros con filtros |
| `GET` | `/books/{id}` | `aura_library_view_catalog` | Detalle de un libro |
| `POST` | `/books` | `aura_library_create` | Crear nuevo libro |
| `PUT` | `/books/{id}` | `aura_library_edit` | Actualizar libro |
| `DELETE` | `/books/{id}` | `aura_library_delete` | Eliminar libro (soft delete) |
| `GET` | `/loans` | `aura_library_view_loans_all` o `view_loans_own` | Listar préstamos |
| `GET` | `/loans/{id}` | `aura_library_view_loans_all` | Detalle de préstamo |
| `POST` | `/loans` | `aura_library_loan_create` | Crear nuevo préstamo |
| `PUT` | `/loans/{id}/return` | `aura_library_loan_return` | Registrar devolución |
| `PUT` | `/loans/{id}/extend` | `aura_library_loan_extend` | Extender plazo |
| `GET` | `/reservations` | `aura_library_view_loans_all` | Listar reservas |
| `POST` | `/reservations` | `aura_library_view_loans_own` | Crear reserva |
| `GET` | `/dashboard` | `aura_library_access` | Datos del dashboard (KPIs) |
| `GET` | `/reports/summary` | `aura_library_reports` | Reporte resumen |

**Seguridad:** Todos los endpoints requieren autenticación WordPress (cookie nonce para peticiones desde el panel, o Application Passwords para integraciones externas). Validar nonce con `wp_verify_nonce()`.

---

## 11. Integración con Módulo de Estudiantes

Cuando un usuario del módulo de Estudiantes tiene préstamos activos:

- En la ficha del estudiante (pestaña "Biblioteca" o sección adicional) se muestran sus préstamos activos.
- Si el estudiante tiene préstamos vencidos, el badge en su perfil indica "Préstamos bibliotecarios vencidos".
- El campo de teléfono del estudiante se usa automáticamente para las notificaciones WhatsApp de biblioteca.
- **Paz y salvo:** El sistema de paz y salvo del módulo de Estudiantes puede consultar si el estudiante tiene préstamos pendientes o multas sin pagar en biblioteca.

**Implementación:**
```php
// En class-library-loans.php
public static function get_active_loans_by_user( int $user_id ): array { ... }
public static function has_overdue_loans( int $user_id ): bool { ... }
public static function has_unpaid_fines( int $user_id ): bool { ... }
```

```php
// En class-students-student.php (módulo Estudiantes — ya existente)
// Agregar hook o llamada al módulo Biblioteca si está activo:
if ( class_exists( 'Aura_Library_Loans' ) ) {
    $active_loans  = Aura_Library_Loans::get_active_loans_by_user( $student_wp_user_id );
    $overdue_loans = Aura_Library_Loans::has_overdue_loans( $student_wp_user_id );
}
```

---

## 12. Integración con Módulo de Finanzas

Cuando se cobra una multa por retraso:

1. El bibliotecario registra la devolución y marca "Cobrar multa".
2. El sistema crea un ingreso en `wp_aura_transactions` con:
   - `amount`: monto de la multa
   - `type`: `income`
   - `category`: "Multas Biblioteca" (categoría auto-creada si no existe)
   - `description`: "Multa por retraso — préstamo #ID — libro: Título"
   - `status`: `approved` (automáticamente aprobado al ser cobrado en caja)
3. El ID de la transacción se guarda en `fine_transaction_id` del préstamo.

---

## 13. Configuración del Módulo (`aura_library_settings`)

Opciones almacenadas en `wp_options` con el prefijo `aura_library_`:

| Opción | Clave `wp_options` | Tipo | Default |
|--------|-------------------|------|---------|
| Días de préstamo por defecto | `aura_library_loan_days` | int | `14` |
| Días de extensión por defecto | `aura_library_extension_days` | int | `7` — se usa como valor pre-cargado en el modal de extensión; el usuario puede cambiarlo (1–180 días) |
| Extensiones máximas por préstamo | `aura_library_max_extensions` | int | `2` |
| Activar multas | `aura_library_fines_enabled` | bool | `false` |
| Tarifa diaria de multa | `aura_library_fine_per_day` | decimal | `0.00` |
| Período de gracia (días) | `aura_library_grace_days` | int | `1` |
| Multa máxima por préstamo | `aura_library_fine_max` | decimal | `0.00` |
| Días para expirar reserva | `aura_library_reservation_expire_days` | int | `2` |
| Activar alertas email | `aura_library_email_alerts` | bool | `true` |
| Email extra bibliotecario | `aura_library_email_extra` | string | `''` |
| Activar alertas WhatsApp | `aura_library_whatsapp_alerts` | bool | `false` |
| Hora del cron diario | `aura_library_cron_hour` | int (0-23) | `8` |
| Integrar multas con Finanzas | `aura_library_fines_to_finance` | bool | `false` |
| Integrar paz y salvo Estudiantes | `aura_library_paz_y_salvo` | bool | `false` |

**Secciones de la página de Configuración:**
1. Préstamos — días, extensiones máximas
2. Multas — activar, tarifa, período de gracia, cap
3. Reservas — días de expiración
4. Notificaciones — email, WhatsApp, hora del cron
5. Integraciones — Finanzas, Estudiantes
6. Acciones de Mantenimiento — reiniciar secuencias, limpiar auditoría antigua

---

## 14. Reportes

Página de reportes con pestañas:

### 14.1 Reporte de Actividad General
- Préstamos por período (semana/mes/año)
- Libros más prestados (top 10 con portada)
- Usuarios más activos (top 10 lectores)
- Tasa de devolución a tiempo vs. atrasadas

### 14.2 Reporte por Clasificación Dewey
- Distribución de préstamos por clase principal Dewey
- Libros más prestados por clase

### 14.3 Reporte de Morosidad
- Préstamos vencidos actuales (con días de retraso y multa acumulada)
- Historial de multas cobradas
- Total recaudado en multas (integración Finanzas si está activa)

### 14.4 Reporte de Inventario
- Total de libros por estado
- Libros sin préstamos en los últimos N meses (stock inactivo)
- Libros con mayor rotación

**Exportación:** Todos los reportes exportables a CSV y PDF (usando mPDF ya incluido en vendor).

---

## 15. Auditoría

Tabla de auditoría (DataTables Responsive) con todo el historial de acciones:

| Campo visible | Descripción |
|--------------|-------------|
| Fecha | Timestamp de la acción |
| Usuario | Nombre del usuario que realizó la acción |
| Acción | Tipo de acción (crear préstamo, registrar devolución, editar libro, etc.) |
| Entidad | Libro o préstamo afectado |
| Detalles | Datos antes / después del cambio |
| IP | IP del usuario |

Acciones que se registran en auditoría:
- Creación, edición y eliminación de libros
- Registro y devolución de préstamos
- Extensiones de préstamo
- Creación y cancelación de reservas
- Registro de multas
- Cambios en la configuración del módulo

---

## 16. Fases de Implementación

### FASE 1 — Infraestructura Base (Semana 1)

**Objetivo:** El módulo es visible en WordPress, crea sus tablas y registra sus permisos.

- [ ] F1.1 — Crear directorio `modules/library/` y la estructura de archivos
- [ ] F1.2 — `class-library-module.php` — singleton, `init()`, `load_dependencies()`
- [ ] F1.3 — `class-library-setup.php` — `create_tables()` con `dbDelta()` para las 4 tablas
- [ ] F1.4 — Registrar las 14 capabilities en `class-roles-manager.php` (array `get_all_capabilities()` — sección `aura_library_*`)
- [ ] F1.5 — `class-library-admin.php` — `register_menus()`, `enqueue_assets()`, posición `5.0`, 7 submenús
- [ ] F1.6 — Registrar hooks en `aura-business-suite.php`: `require_once` + `Aura_Library_Module::init()`
- [ ] F1.7 — Template vacío `dashboard.php` con mensaje "Próximamente"
- [ ] F1.8 — Verificar que la activación no rompe el plugin (activar/desactivar en WP admin)

**Criterio de éxito:** El menú "Biblioteca" aparece en posición 5.0 con sus 7 submenús, el administrador puede acceder a todos, las 4 tablas existen en la base de datos, las 14 capabilities están disponibles en el gestor de permisos CBAC.

---

### FASE 2 — Catálogo de Libros (Semanas 1-2)

**Objetivo:** CRUD completo de libros con el estándar visual del proyecto.

- [ ] F2.1 — `class-library-books.php` — métodos `get_all()`, `get_by_id()`, `create()`, `update()`, `delete()` (soft), `search()`
- [ ] F2.2 — Template `books-list.php` — DataTables Responsive con las 8 columnas definidas en sección 8.2
- [ ] F2.3 — Template `book-form.php` — formulario modal (misma UX del módulo Inventario): las secciones definidas en sección 8.2
- [ ] F2.4 — Template `book-detail.php` — vista completa del libro con historial de préstamos
- [ ] F2.5 — Selector de portada usando WordPress Media Uploader (igual a módulo Inventario — clase `aura-media-uploader`)
- [ ] F2.6 — Filtros: búsqueda libre, Dewey, categoría, estado
- [ ] F2.7 — Tooltip de portada al pasar el mouse (imagen grande) — CSS equal a estándar PRD.md 5.6
- [ ] F2.8 — Validación de ISBN (formato ISBN-10 e ISBN-13)
- [ ] F2.9 — Validación de número Dewey (formato libre, campo texto con hint de ejemplos)
- [ ] F2.10 — Auditoría: registrar en `wp_aura_library_audit` cada CRUD
- [ ] F2.11 — Assets `library-books.js` + `library-books.css`

**Criterio de éxito:** Crear, editar, eliminar y buscar libros funciona. La tabla es responsive. La imagen de portada se puede subir. Los datos se persisten correctamente en `wp_aura_library_books`.

---

### FASE 3 — Préstamos y Devoluciones (Semana 2)

**Objetivo:** El ciclo completo préstamo → devolución funciona.

- [ ] F3.1 — `class-library-loans.php` — métodos `create()`, `return_book()`, `extend()`, `get_active()`, `get_overdue()`, `get_by_user()`, `has_overdue_loans()`, `has_unpaid_fines()`
- [ ] F3.2 — Al crear préstamo: validar que el libro tiene `available_copies > 0`, decrementar `available_copies`, crear registro en `wp_aura_library_loans`
- [ ] F3.3 — Al registrar devolución: establecer `return_date`, incrementar `available_copies`, cambiar status a `returned`, calcular multa si aplica
- [ ] F3.4 — Template `loans-list.php` — DataTables Responsive con las 9 columnas definidas en sección 8.3, badges de estado
- [ ] F3.5 — Template `loan-form.php` — modal con autocomplete de libro (busca por título/autor/Dewey) y autocomplete de usuario WP
- [ ] F3.6 — Lógica de extensión: `extended_count < max_extensions`, calcular nueva `due_date`
- [ ] F3.7 — Filtros en la tabla: estado, texto libre, rango de fechas
- [ ] F3.8 — Auditoría: registrar préstamo, devolución, extensión
- [ ] F3.9 — `class-library-fines.php` — `calculate_fine()`, `register_fine_payment()`, integración con Finanzas si está activada en config

**Criterio de éxito:** Registrar un préstamo decremente el stock del libro. Devolver el libro incrementa el stock. Se calcula la multa correctamente. Los estados cambian correctamente en la tabla.

---

### FASE 4 — Reservas (Semana 3)

**Objetivo:** Los lectores pueden reservar un libro sin copias disponibles.

- [ ] F4.1 — `class-library-reservations.php` — `create()`, `cancel()`, `notify_next_in_queue()`, `expire_old()`
- [ ] F4.2 — Al registrar devolución: tras liberar copia, llamar a `notify_next_in_queue()` para el libro
- [ ] F4.3 — Template `reservations-list.php` — tabla de reservas activas con posición en cola, estado, botón cancelar
- [ ] F4.4 — Botón "Reservar" en `book-detail.php` cuando `available_copies == 0`
- [ ] F4.5 — Expiración automática de reservas (vía cron diario)

**Criterio de éxito:** Un libro sin stock puede ser reservado. Al devolverse el libro, el primer usuario en cola recibe notificación. Las reservas expiradas se limpian automáticamente.

---

### FASE 5 — Notificaciones y Cron (Semana 3)

**Objetivo:** Las alertas automáticas funcionan vía email y WhatsApp.

- [ ] F5.1 — `class-library-notifications.php` — métodos `send_loan_confirmation()`, `send_reminder()`, `send_overdue_alert()`, `send_return_confirmation()`, `send_reservation_available()` — usando el sistema de notificaciones global de Aura Suite
- [ ] F5.2 — `class-library-cron.php` — registrar `wp_schedule_event` con `aura_library_daily_cron`, lógica de los 3 triggers (3 días antes, día del vencimiento, post-vencimiento)
- [ ] F5.3 — Activar cron en activación del plugin: `Aura_Library_Cron::schedule()`
- [ ] F5.4 — Desactivar cron en desactivación del plugin: `wp_clear_scheduled_hook()`
- [ ] F5.5 — Botón manual "Ejecutar alertas ahora" en Configuración para pruebas

**Criterio de éxito:** Crear un préstamo envía email de confirmación. El cron diario detecta préstamos próximos a vencer y envía recordatorio. Los préstamos vencidos reciben alerta.

---

### FASE 6 — Dashboard y Reportes (Semana 4)

**Objetivo:** Dashboard con KPIs en tiempo real y reportes exportables.

- [ ] F6.1 — Template `dashboard.php` — tarjetas de KPIs (6 widgets), 3 gráficos Chart.js, 3 listas rápidas
- [ ] F6.2 — `assets/js/library-dashboard.js` — AJAX para cargar datos del dashboard sin reload
- [ ] F6.3 — `class-library-reports.php` — métodos para los 4 tipos de reporte definidos en sección 14
- [ ] F6.4 — Template `reports.php` — 4 pestañas con tablas y gráficos
- [ ] F6.5 — Exportación a CSV (PHP nativo) y PDF (mPDF ya en vendor)
- [ ] F6.6 — `assets/css/library-dashboard.css` con estilos de tarjetas y gráficos

**Criterio de éxito:** El dashboard carga correctamente mostrando todos los KPIs. Los reportes se generan y exportan correctamente.

---

### FASE 7 — Integración con Estudiantes y Finanzas (Semana 4)

**Objetivo:** Los módulos se comunican correctamente.

- [ ] F7.1 — `Aura_Library_Loans::get_active_loans_by_user( $user_id )` — método público
- [ ] F7.2 — `Aura_Library_Loans::has_overdue_loans( $user_id )` — método público
- [ ] F7.3 — `Aura_Library_Loans::has_unpaid_fines( $user_id )` — método público
- [ ] F7.4 — En módulo Estudiantes: agregar sección "Préstamos Biblioteca" en ficha del estudiante (si Biblioteca está activo)
- [ ] F7.5 — En sistema de paz y salvo de Estudiantes: incluir check de préstamos/multas de biblioteca
- [ ] F7.6 — Al registrar pago de multa: crear transacción en Finanzas (si `aura_library_fines_to_finance` está activo)

**Criterio de éxito:** La ficha del estudiante muestra sus préstamos activos. El paz y salvo detecta préstamos vencidos. Las multas cobradas aparecen en Finanzas.

---

### FASE 8 — Auditoría, Configuración y API REST (Semana 4-5)

**Objetivo:** El módulo está listo para producción.

- [ ] F8.1 — Template `audit.php` — DataTables paginado del log de auditoría con filtros
- [ ] F8.2 — Template `settings.php` — 6 secciones de configuración con formulario AJAX
- [ ] F8.3 — `class-library-api.php` — registrar todos los endpoints REST de la sección 10.1
- [ ] F8.4 — Validar nonces en todos los formularios AJAX (seguridad OWASP)
- [ ] F8.5 — Sanitizar y validar todos los inputs con `sanitize_text_field()`, `absint()`, `wp_kses_post()`
- [ ] F8.6 — Escapar todos los outputs con `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] F8.7 — Revisar que las capabilities se verifican en CADA endpoint y acción sensible
- [ ] F8.8 — Probar en dispositivos móviles — tablas DataTables responsivas
- [ ] F8.9 — Actualizar `Aura_Roles_Manager` con las 14 capabilities del módulo

**Criterio de éxito:** La auditoría registra todas las acciones. La configuración se guarda correctamente. La API REST responde con los datos correctos. No hay vulnerabilidades XSS ni CSRF.

---

## 17. Seguridad — Checklist OWASP

- [ ] **A01 Broken Access Control:** Todas las acciones verifican capability con `current_user_can()` antes de ejecutarse
- [ ] **A02 Cryptographic Failures:** No se almacenan datos sensibles en texto claro
- [ ] **A03 Injection:** Todas las queries usan `$wpdb->prepare()` con placeholders `%s`, `%d`
- [ ] **A04 Insecure Design:** Soft delete en libros (campo `deleted_at`), no eliminación física por defecto
- [ ] **A05 Security Misconfiguration:** No exponer datos internos en errores; usar `wp_die()` con mensajes genéricos
- [ ] **A07 Identification and Authentication Failures:** Verificar nonce en todos los formularios AJAX (`wp_verify_nonce()`)
- [ ] **A08 Software and Data Integrity Failures:** Validar tipo MIME de imágenes de portada antes de guardar
- [ ] **A10 SSRF:** No hacer peticiones HTTP a URLs proporcionadas por el usuario

---

## 18. Consideraciones para CEM (Centro de Estudios / Misiones)

La organización CEM es el cliente principal de Aura Suite. Para este contexto específico, el módulo de Biblioteca debe considerar:

- **Acervo típico:** Biblias, libros de teología, materiales didácticos de formación, manuales técnicos para la finca, libros de estudio por idiomas
- **Lectores:** Voluntarios, misioneros, estudiantes inscritos, staff permanente, instructores
- **Préstamos comunes:** Sin costo ni multa (se puede desactivar multas), principalmente por honor
- **Catalogación Dewey sugerida:**
  - Clase 200 (Religión/Teología) — Biblia, teología sistemática, devocionales
  - Clase 300 (Ciencias Sociales) — Administración, misiones, trabajo social
  - Clase 600 (Tecnología) — Manuales de mantenimiento, agricultura, fontanería, electricidad
  - Clase 400 (Lengua) — Libros de estudio de idiomas
- **Sin barcode/RFID:** Búsqueda manual por número Dewey y ubicación física
- **Frontend público:** El shortcode `[aura_library_catalog]` puede mostrar el catálogo público para que los lectores vean disponibilidad sin acceso admin

---

## 19. Shortcode Frontend (Opcional — Fase posterior)

Para organizaciones que deseen que los lectores consulten el catálogo desde el frontend de WordPress sin acceso al panel admin:

```php
// Shortcode: [aura_library_catalog]
// Muestra catálogo filtrable públicamente
// Shortcode: [aura_library_my_loans]
// Muestra al usuario logueado sus préstamos activos
```

Estos shortcodes son opcionales y se implementarán en una fase posterior, siguiendo el patrón del módulo de Estudiantes (`[aura_student_portal]`).

---

## 20. Resumen de Posición en el Sistema

| Ítem | Valor |
|------|-------|
| Módulo | `library` |
| Posición menú | `5.0` |
| Slug menú | `aura-library` |
| Ícono WP Admin | `dashicons-book` |
| Clase principal | `Aura_Library_Module` |
| Directorio | `modules/library/` |
| Templates | `templates/library/` |
| Capabilities registradas | 14 (`aura_library_*`) |
| Tablas creadas | 4 (`wp_aura_library_books`, `wp_aura_library_loans`, `wp_aura_library_reservations`, `wp_aura_library_audit`) |
| Fases | 8 fases |
| Integraciones | Estudiantes, Finanzas, Notificaciones globales |
| Estado actual | ❌ No iniciado — Fase 1 pendiente |
