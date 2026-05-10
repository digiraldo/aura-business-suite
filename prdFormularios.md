<div align="center">

# PRD — Módulo de Formularios y Encuestas
## AURA Business Suite — Plugin WordPress
### Documento de Requerimientos Completo para Implementación

> **Versión**: 1.0.0 · **Fecha**: Marzo 2026
> **Dependencias previas requeridas**: Plugin AURA activo, Módulo Estudiantes activo (para integraciones de inscripción y encuestas)
> **Ruta de implementación**: `modules/forms/`
> **Orden de implementación**: Después de Módulo Certificados y Diplomas

</div>

---

## 1. Descripción General

### 1.1 Propósito

El Módulo de Formularios y Encuestas es un constructor de formularios visual tipo **Google Forms**, completamente independiente, que permite crear cualquier tipo de formulario con campos personalizados. Su poder está en dos modos de operación que se integran seamlessly con el resto de la suite:

1. **Modo independiente**: Formularios genéricos, encuestas de satisfacción, formularios de contacto — sin dependencia de ningún otro módulo.
2. **Modo integrado**: Al configurar un formulario como tipo "Inscripción a Curso", actúa como puerta de entrada al flujo de estudiantes; al tipo "Encuesta a Estudiantes", se asigna a participantes inscritos y los estudiantes la responden desde su portal con login.

### 1.2 Alcance

- **Backend (WordPress Admin)**: Builder de formularios (arrastrar y ordenar campos), configuración de tipos, gestión de respuestas, análisis gráfico, exportación.
- **Frontend (público, sin login)**: Formularios públicos de inscripción embebibles con shortcode `[aura_form id="X"]` o accesibles por URL amigable `/formulario/{slug}/`.
- **Frontend (con login, portal estudiante)**: Pestaña "📋 Mis Formularios" en `[aura_student_portal]` donde el estudiante ve y completa las encuestas asignadas.
- **Integración con Módulo Estudiantes**: Bridge de inscripción (form submit → enrollment pendiente) + asignación de encuestas + flujo de aprobación/rechazo en panel de inscripciones.
- **No reemplaza**: El módulo de estudiantes sigue gestionando a quién se aprueba/rechaza; solo recibe los datos del formulario.

### 1.3 Tipos de Formulario

| Código | Nombre | Descripción | Requiere Login |
|--------|--------|-------------|---------------|
| `generic` | Formulario Genérico | Contacto, registro de datos, formularios libres | No |
| `enrollment` | Inscripción a Curso | Postulación a un curso; genera enrollment pendiente en Estudiantes | No (público) |
| `survey` | Encuesta Asignada | Se asigna manualmente a estudiantes específicos; requieren login para llenarla | Sí (portal) |
| `feedback` | Encuesta Automática | Se envía automáticamente a todos los inscritos de un curso tras aprobación, X días después, etc. | Sí (portal) |

### 1.4 Límites del módulo (lo que NO hace)

- No aprueba ni rechaza postulantes (eso lo hace el Módulo Estudiantes en su vista de inscripciones)
- No genera certificados (eso es Módulo Certificados)
- No envía email/WhatsApp directamente (se delega a `Aura_Notifications`)
- No maneja autenticación de usuarios (delega a WordPress y al portal de Estudiantes)

---

## 2. Arquitectura del Sistema

### 2.1 Árbol de Archivos

```
modules/forms/
├── class-forms-setup.php            # Tablas BD, capabilities, rewrite rules, migración
├── class-forms-admin.php            # Menús WP Admin, enqueue de assets, nonces
├── class-forms-builder.php          # AJAX: CRUD de formularios y campos (builder)
├── class-forms-submissions.php      # AJAX: guardar submissions (público + autenticado)
├── class-forms-assignments.php      # AJAX: asignar encuestas a estudiantes, ver pendientes
├── class-forms-enrollment.php       # Bridge: procesar submissions tipo enrollment → Estudiantes
├── class-forms-analytics.php        # AJAX: estadísticas y gráficos por formulario
├── class-forms-export.php           # Exportar respuestas a CSV / Excel
├── class-forms-frontend.php         # Shortcodes: [aura_form], [aura_form_portal], rewrite
├── class-forms-notifications.php    # Wrapper sobre Aura_Notifications
├── class-forms-reports.php          # Reportes exportables
└── class-forms-settings.php         # Configuración del módulo

templates/forms/
├── dashboard.php                    # Dashboard con KPIs y formularios recientes
├── list.php                         # Listado de formularios con filtros
├── builder.php                      # Editor visual del formulario (drag & drop)
├── submissions-list.php             # Listado de respuestas de un formulario
├── submission-detail.php            # Vista detallada de una respuesta individual
├── enrollments-pending.php          # Panel de postulantes pendientes (tipo enrollment)
├── assignments.php                  # Gestión de asignaciones de encuestas
├── analytics.php                    # Análisis gráfico de respuestas
├── reports.php                      # Reportes exportables
├── settings.php                     # Configuración del módulo
└── frontend/
    ├── form-render.php              # Template de renderizado del formulario público
    ├── form-portal.php              # Lista de formularios pendientes del estudiante (portal)
    └── form-success.php             # Página de confirmación post-envío

assets/
├── css/
│   ├── forms-admin.css              # Estilos del backend (builder, listados)
│   └── forms-frontend.css           # Estilos del formulario público
└── js/
    ├── forms-admin.js               # Lógica del builder (Sortable.js), gestión de campos
    ├── forms-builder.js             # Interactividad específica del editor de campos
    └── forms-frontend.js            # Validación y envío del formulario en frontend
```

### 2.2 Clases PHP — Responsabilidades

| Clase | Responsabilidad |
|-------|-----------------|
| `Aura_Forms_Setup` | `dbDelta()`, capabilities, rewrite rules, cron events |
| `Aura_Forms_Admin` | `add_menu_page()`, `enqueue_scripts()`, nonces, callbacks render |
| `Aura_Forms_Builder` | AJAX CRUD: crear/editar/eliminar formularios y campos; reordenar; duplicar |
| `Aura_Forms_Submissions` | AJAX: guardar submission (validación server-side, anti-spam, rate limiting) |
| `Aura_Forms_Assignments` | AJAX: asignar/revocar encuestas; listar pendientes por estudiante |
| `Aura_Forms_Enrollment` | Hook listener: procesa submission tipo `enrollment` → crea `aura_student_enrollments` (pending) |
| `Aura_Forms_Analytics` | AJAX: agrega respuestas por campo (conteo de opciones, promedio de rangos, nube palabras) |
| `Aura_Forms_Export` | Exporta a CSV y Excel con PhpSpreadsheet |
| `Aura_Forms_Frontend` | Shortcodes `[aura_form]`, `[aura_form_portal]`; template endpoint público |
| `Aura_Forms_Notifications` | `notify_admin_new_submission()`, `notify_student_assignment()` via `Aura_Notifications` |
| `Aura_Forms_Reports` | Reportes consolidados con PhpSpreadsheet y mPDF |
| `Aura_Forms_Settings` | Opciones de configuración del módulo (páginas, anti-spam, etc.) |

### 2.3 Registro en `aura-business-suite.php`

En `load_dependencies()` añadir (después del bloque de Certificados):

```php
// Módulo Formularios
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-setup.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-admin.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-builder.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-submissions.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-assignments.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-enrollment.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-analytics.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-export.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-frontend.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-notifications.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-reports.php';
require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-settings.php';
```

En `init_modules()` añadir:

```php
Aura_Forms_Setup::init();
Aura_Forms_Admin::init();
Aura_Forms_Builder::init();
Aura_Forms_Submissions::init();
Aura_Forms_Assignments::init();
Aura_Forms_Enrollment::init();
Aura_Forms_Analytics::init();
Aura_Forms_Frontend::init();
Aura_Forms_Reports::init();
Aura_Forms_Settings::init();
```

---

## 3. Base de Datos

### 3.1 Versión y Migración

- **Constante**: `DB_VERSION = '1.2.0'` en `Aura_Forms_Setup`
- **Opción WP**: `aura_forms_db_version`
- **Migración**: `dbDelta()` igual que todos los módulos anteriores
- Se ejecuta en `register_activation_hook` y al detectar `DB_VERSION` desactualizada

### 3.2 Tablas SQL

```sql
-- ──────────────────────────────────────────────────────────────────────────────
-- 1. Formularios (definición del formulario)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_forms (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(300) NOT NULL,
    slug                VARCHAR(300) NOT NULL UNIQUE,  -- URL: /formulario/{slug}/
    description         TEXT NULL,                    -- Texto de introducción visible al rellenar
    type                ENUM('generic','enrollment','survey','feedback') NOT NULL DEFAULT 'generic',
    -- Integración con Estudiantes (solo para type = 'enrollment' o 'survey'/'feedback')
    course_id           BIGINT UNSIGNED NULL,          -- FK lógica → aura_student_courses.id
    area_id             BIGINT UNSIGNED NULL,          -- FK lógica → wp_aura_areas.id (término WP)
    -- Configuración del formulario
    submit_button_label VARCHAR(200) NOT NULL DEFAULT 'Enviar',
    success_message     TEXT NULL,                    -- Mensaje tras envío exitoso
    redirect_url        VARCHAR(500) NULL,            -- URL alternativa post-envío (opcional)
    -- Acceso y estado
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    requires_login      TINYINT(1) NOT NULL DEFAULT 0,  -- 1 para survey/feedback
    accept_multiple     TINYINT(1) NOT NULL DEFAULT 0,  -- Permitir múltiples envíos del mismo usuario
    -- Anti-spam y límites
    max_submissions     INT UNSIGNED NULL,             -- NULL = sin límite
    close_date          DATETIME NULL,                 -- Fecha de cierre automático
    -- Diseño visual
    primary_color       VARCHAR(7) NULL DEFAULT '#2563eb', -- Color del botón y acentos (hex)
    logo_url            VARCHAR(500) NULL,             -- Logo específico del formulario
    -- Notificaciones
    notify_admin_emails TEXT NULL,                     -- Emails separados por coma que reciben notif.
    notify_submitter    TINYINT(1) NOT NULL DEFAULT 0, -- 1 = enviar acuse al que envió
    -- Auto-asignación (para type = 'feedback')
    auto_assign_trigger ENUM('none','on_enrollment_approved','on_course_complete','scheduled') NOT NULL DEFAULT 'none',
    auto_assign_days    TINYINT UNSIGNED NOT NULL DEFAULT 0, -- Días tras el trigger para enviar
    -- Auditoría
    created_by          BIGINT UNSIGNED NOT NULL,      -- FK → wp_users.ID
    updated_by          BIGINT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,                 -- Soft delete
    INDEX idx_type      (type),
    INDEX idx_active    (is_active),
    INDEX idx_course    (course_id),
    INDEX idx_area      (area_id),
    INDEX idx_deleted   (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────────
-- 2. Campos del formulario
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_form_fields (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id         BIGINT UNSIGNED NOT NULL,          -- FK → aura_forms.id
    label           VARCHAR(500) NOT NULL,             -- Etiqueta visible al usuario
    field_type      ENUM(
                        'text',         -- Texto de una línea
                        'email',        -- Email (validación automática)
                        'tel',          -- Teléfono
                        'number',       -- Número (con min/max)
                        'date',         -- Fecha
                        'time',         -- Hora
                        'textarea',     -- Párrafo (texto largo)
                        'select',       -- Desplegable de opciones
                        'radio',        -- Opción única (botones)
                        'checkbox',     -- Opción múltiple (casillas)
                        'scale',        -- Escala del 1 al N (para encuestas NPS)
                        'file',         -- Subida de archivo
                        'hidden',       -- Campo oculto (valor prefijado, no visible)
                        'section_title',-- Separador visual con título y descripción
                        'paragraph'     -- Texto explicativo (solo lectura)
                    ) NOT NULL DEFAULT 'text',
    -- Configuración de opciones (para select, radio, checkbox)
    options_json    TEXT NULL,                         -- JSON: ["Opción A","Opción B","Opción C"]
    -- Configuración numérica / escala
    min_value       DECIMAL(10,2) NULL,
    max_value       DECIMAL(10,2) NULL,
    -- Configuración de archivo
    allowed_extensions VARCHAR(200) NULL DEFAULT 'jpg,jpeg,png,pdf', -- para field_type = 'file'
    max_file_size_kb INT UNSIGNED NULL DEFAULT 5120,  -- 5 MB por defecto
    -- Placeholder / valor predeterminado
    placeholder     VARCHAR(500) NULL,
    default_value   VARCHAR(500) NULL,
    -- Validación
    is_required     TINYINT(1) NOT NULL DEFAULT 0,
    multiple_select TINYINT(1) NOT NULL DEFAULT 0,   -- Solo para field_type = 'select'
    has_other       TINYINT(1) NOT NULL DEFAULT 0,   -- Incluir opción "Otro" (radio, select)
    -- Mapeo a campos estándar del Módulo Estudiantes (solo si el form es tipo 'enrollment')
    -- Valores posibles: first_name, last_name, email, phone, id_number, birthdate, address, area_id, course_id, notes, NULL
    mapping_key     VARCHAR(100) NULL,
    -- Ordenamiento
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    -- Auditoría
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_form      (form_id),
    INDEX idx_sort      (form_id, sort_order),
    INDEX idx_mapping   (mapping_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────────
-- 3. Respuestas / Submissions
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_form_submissions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id             BIGINT UNSIGNED NOT NULL,      -- FK → aura_forms.id
    -- Quién envió
    wp_user_id          BIGINT UNSIGNED NULL,          -- NULL para envíos anónimos
    submitted_name      VARCHAR(300) NULL,             -- Nombre si lo hubo en el form
    submitted_email     VARCHAR(300) NULL,             -- Email si lo hubo en el form
    -- Datos completos del formulario (registro permanente e inmutable)
    data_json           LONGTEXT NOT NULL,             -- JSON: { "field_id": "valor", ... }
    -- Contexto técnico
    source_url          VARCHAR(1000) NULL,            -- URL de la página donde se llenó
    ip_address          VARCHAR(45) NULL,              -- IPv4 o IPv6 (para anti-spam)
    user_agent          VARCHAR(500) NULL,
    -- Estado (relevante para type = 'enrollment')
    status              ENUM('received','reviewed','processed') NOT NULL DEFAULT 'received',
    -- Integración con Módulo Estudiantes
    -- Una vez procesado, el enrollment creado queda referenciado aquí
    enrollment_id       BIGINT UNSIGNED NULL,          -- FK lógica → aura_student_enrollments.id
    -- Auditoría
    submitted_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by         BIGINT UNSIGNED NULL,
    reviewed_at         DATETIME NULL,
    INDEX idx_form          (form_id),
    INDEX idx_user          (wp_user_id),
    INDEX idx_status        (status),
    INDEX idx_submitted_at  (submitted_at),
    INDEX idx_enrollment    (enrollment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────────
-- 4. Asignaciones de formulario/encuesta a estudiantes
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_form_assignments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id             BIGINT UNSIGNED NOT NULL,      -- FK → aura_forms.id
    student_id          BIGINT UNSIGNED NOT NULL,      -- FK lógica → aura_students.id
    enrollment_id       BIGINT UNSIGNED NULL,          -- FK lógica → aura_student_enrollments.id (opcional, contexto)
    -- Estado de completación
    status              ENUM('pending','completed','expired') NOT NULL DEFAULT 'pending',
    submission_id       BIGINT UNSIGNED NULL,          -- FK → aura_form_submissions.id cuando complete
    -- Fechas
    assigned_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at          DATETIME NULL,                 -- NULL = sin expiración
    completed_at        DATETIME NULL,
    -- Quién asignó
    assigned_by         BIGINT UNSIGNED NULL,          -- NULL si fue auto-asignado por trigger
    assignment_trigger  VARCHAR(100) NULL,             -- 'manual', 'on_enrollment_approved', etc.
    notes               TEXT NULL,
    INDEX idx_form      (form_id),
    INDEX idx_student   (student_id),
    INDEX idx_status    (status),
    INDEX idx_expires   (expires_at),
    UNIQUE KEY uk_assignment (form_id, student_id, enrollment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Relación de tablas

```
aura_forms (1) ──────────── (N) aura_form_fields
aura_forms (1) ──────────── (N) aura_form_submissions
aura_forms (1) ──────────── (N) aura_form_assignments
aura_form_submissions ───── (1) aura_form_assignments   (cuando: completed)
aura_form_submissions ───── (1) aura_student_enrollments (cuando: type=enrollment)
wp_aura_areas ──────────── (N) aura_forms              (course/area de inscripción)
```

---

## 4. Capabilities del Módulo

Registrar en `Aura_Roles_Manager::get_all_capabilities()` y `get_capabilities_for_ui()` bajo la sección `forms`:

```php
'forms' => array(
    'aura_forms_submit'             => __('Enviar / completar formularios', 'aura-suite'),
    'aura_forms_create'             => __('Crear nuevos formularios y encuestas', 'aura-suite'),
    'aura_forms_edit'               => __('Editar formularios existentes', 'aura-suite'),
    'aura_forms_delete'             => __('Eliminar formularios (solo admin)', 'aura-suite'),
    'aura_forms_view_responses_own' => __('Ver sus propias respuestas', 'aura-suite'),
    'aura_forms_view_responses_all' => __('Ver todas las respuestas de cualquier formulario', 'aura-suite'),
    'aura_forms_export'             => __('Exportar respuestas a CSV/Excel', 'aura-suite'),
    'aura_forms_analytics'          => __('Ver análisis y gráficos de respuestas', 'aura-suite'),
    'aura_forms_assign'             => __('Asignar encuestas a estudiantes', 'aura-suite'),
    'aura_forms_enrollment_review'  => __('Revisar postulantes de inscripción (panel)', 'aura-suite'),
    'aura_forms_settings'           => __('Configurar el módulo de formularios', 'aura-suite'),
    'aura_forms_reports'            => __('Ver reportes de formularios', 'aura-suite'),
),
```

**Asignación automática a Administradores** en `ensure_admin_capabilities()`: todas las capabilities del bloque `forms`.

### Perfiles de usuario y capabilities sugeridas

| Perfil | Capabilities |
|--------|-------------|
| Administrador | Todas |
| Coordinador Académico | `aura_forms_create`, `aura_forms_edit`, `aura_forms_view_responses_all`, `aura_forms_assign`, `aura_forms_enrollment_review`, `aura_forms_analytics`, `aura_forms_export` |
| Secretaría | `aura_forms_view_responses_all`, `aura_forms_enrollment_review`, `aura_forms_assign` |
| Analista de Datos | `aura_forms_view_responses_all`, `aura_forms_analytics`, `aura_forms_export`, `aura_forms_reports` |
| Estudiante (frontend, con login) | `aura_forms_submit`, `aura_forms_view_responses_own` |
| Visitante / Postulante (sin login) | Solo puede enviar si el form es público (`requires_login = 0`) |

---

## 5. Menús de WordPress Admin

```
📝 Formularios                         (capab: aura_forms_view_responses_all)  posición: 4.8
├── Dashboard                          (capab: aura_forms_view_responses_all)
├── Todos los Formularios              (capab: aura_forms_view_responses_all)  slug: aura-forms-list
├── Nuevo Formulario                   (capab: aura_forms_create)              slug: aura-forms-new
├── Postulantes (Inscripciones)        (capab: aura_forms_enrollment_review)   slug: aura-forms-enrollments
├── Encuestas Asignadas                (capab: aura_forms_assign)              slug: aura-forms-assignments
├── Análisis de Respuestas             (capab: aura_forms_analytics)           slug: aura-forms-analytics
├── Reportes                           (capab: aura_forms_reports)             slug: aura-forms-reports
└── Configuración                      (capab: aura_forms_settings)            slug: aura-forms-settings
```

**Posición**: `4.8` (después de Certificados en `4.7`)

**Slug del menú principal**: `aura-forms`

**Patrón de registro** (mismo que los demás módulos):

```php
add_menu_page(
    __('Formularios', 'aura-suite'),
    __('📝 Formularios', 'aura-suite'),
    'aura_forms_view_responses_all',
    'aura-forms',
    [ 'Aura_Forms_Admin', 'render_dashboard' ],
    'dashicons-feedback',
    4.8
);
```

---

## 6. Tipos de Campo — Referencia Completa

### 6.1 Propiedades comunes a todos los campos

Cada campo almacenado en la columna `fields_json` del formulario contiene siempre:

| Propiedad | Descripción |
|-----------|-------------|
| `id` | Identificador de instancia generado al crear (`field_{tipo}_{N}`) |
| `field_uid` | Identificador estable único (`fu_xxxxxxxxxx`) — **no cambia aunque se edite el label**; permite mantener historial de respuestas coherente entre versiones del formulario |
| `label` | Texto de la pregunta visible al usuario |
| `description` | Texto de ayuda (aparece como icono `?` junto al campo en el formulario público) |
| `type` | Tipo de campo (ver tabla 6.2) |
| `required` | `"1"` = obligatorio · `""` = opcional |

> **Por qué `field_uid`**: Si el admin renombra "Nombre completo" a "Nombre y apellidos", el `id` puede cambiar pero el `field_uid` permanece igual. Esto permite cruzar respuestas antiguas con nuevas en analytics sin perder historial.

---

### 6.2 Tabla de Tipos de Campo

| `field_type` | Etiqueta UI | Input HTML | Propiedades específicas | Genera respuesta |
|---|---|---|---|---|
| `text` | Texto Corto | `<input type="text">` | `placeholder`, `required` | Sí — texto plano |
| `textarea` | Párrafo | `<textarea>` | `placeholder`, `required`, filas configurables | Sí — texto largo |
| `email` | Correo Electrónico | `<input type="email">` | `placeholder`, `required` — valida formato email | Sí |
| `tel` | Número de Teléfono | `<input type="tel">` | `placeholder`, `required`, máscara opcional | Sí |
| `date` | Fecha | `<input type="date">` | `value` (fecha por defecto), `required`, rango min/max opcional | Sí — fecha ISO |
| `number` | Número | `<input type="number">` | `placeholder`, `min`, `max`, `step`, `required` | Sí — número |
| `time` | Hora | `<input type="time">` | `required` | Sí — HH:MM |
| `radio` | Opción Múltiple (única) | `<input type="radio">` | `options` (texto, una por línea), `required`, `has_other` (incluye opción libre "Otro") | Sí — texto de opción elegida (o texto libre si eligó "Otro") |
| `checkbox` | Casillas (múltiple) | `<input type="checkbox">` | `options` (texto, una por línea), `required` | Sí — JSON array de opciones marcadas |
| `select` | Desplegable | `<select>` | `options`, `multiple_select` (booleano), `required`, `has_other` (incluye opción libre "Otro") | Sí — valor(es) seleccionados (o texto libre si eligió "Otro") |
| `scale` | Escala (NPS / Likert) | Botones 1–N | `max_value` define rango (5, 10, etc.), `required` | Sí — número |
| `birthdate` | Fecha de Nacimiento | `<input type="date">` | `required` — calcula edad automáticamente al enviar | Sí — genera **dos entradas en `data_json`**: `{uid}` → edad calculada en años (número) y `{uid}_iso_date` → fecha en formato ISO `YYYY-MM-DD`. En la vista de detalle y en exportación se presentan como **dos filas/columnas separadas**: `{label} (fecha)` y `{label} (edad)`. En analytics se grafica la distribución de edades. |
| `image` | Imagen | `<img>` visual | `image_title`, `image_file_uploaded` (archivo subido via media), solo decorativa/informativa | **No** — puramente visual, no se almacena respuesta |
| `file` | Cargar Documento | `<input type="file">` | `allowed_extensions` (pdf, doc, docx, jpg, jpeg, png, zip, rar, txt), `max_file_size_kb`, `required` | Sí — nombre de archivo guardado con `uniqid()` en uploads |
| `downloadable` | Descargar Documento | Enlace de descarga | `file_uploaded` **ó** `file_url` (mutuamente excluyentes), `instructions` (texto orientativo) | **No** — solo muestra el enlace; no se almacena respuesta |
| `terms` | Aceptación de Términos | Radio agree / disagree | `terms_text` (HTML), `disagreement_message` (texto al estar en desacuerdo) | Sí — guarda `'agree'` o `'disagree'` internamente; se **muestra en español** (`De acuerdo` / `En desacuerdo`) en la vista de detalle y en el análisis de respuestas |
| `accept_only_terms` | Aceptación de Términos (Solo Aceptar) | Checkbox | `terms_text` (HTML), `required` | Sí — guarda `'accepted'` si está marcado; `null` si no (impide envío si `required`). Se muestra como `Aceptado` / `No aceptado` en detalle y análisis. |
| `hidden` | Campo oculto | `<input type="hidden">` | Valor fijo; útil para pasar `course_id` o UTM params | Sí — valor del campo oculto |
| `section_title` | Título de sección | `<h3>` visual | `label` como título | **No** — solo estructura visual |
| `paragraph` | Texto explicativo | `<p>` visual | `label` como contenido principal; `description` como texto de ayuda opcional debajo | **No** — solo lectura, sin respuesta |

---

### 6.3 Procesamiento especial en envío

Estos tipos requieren lógica adicional al procesar el POST en `Aura_Forms_Submissions::ajax_submit()`:

| Tipo | Nombre del campo POST | Lógica especial |
|------|----------------------|-----------------|
| `terms` | `field_{id}_agreement_response` | Acepta valores `agree` / `disagree`; ambos se guardan en BD. Si `disagree`, puede mostrar `disagreement_message` y aún permitir envío (registro del desacuerdo) |
| `accept_only_terms` | `field_{id}` (checkbox) | Si marcado → guarda `'accepted'`; si no marcado y `required` → bloquea envío |
| `file` | `$_FILES['field_{id}']` | Upload via `$_FILES`; se guarda con `uniqid()` prefix en directorio de uploads; se almacena solo el filename |
| `birthdate` | `field_{id}` (fecha ISO) | Genera **dos claves en `data_json`**: `{field_uid}` → edad en años (entero) y `{field_uid}_iso_date` → fecha en `YYYY-MM-DD`. En la vista de detalle de la respuesta se muestran como **dos filas separadas**: `{label} (fecha)` con la fecha ISO y `{label} (edad)` con `N años`. En la exportación CSV/Excel se generan **dos columnas**: `{label} (fecha)` y `{label} (edad)`. En analytics, se usa el valor numérico de edad para el histograma de distribución. |
| `radio` / `select` con `has_other` | `field_{id}` = `"__other__"` — texto libre en `field_{id}_other_text` | Se reemplaza `__other__` con el texto libre al procesar el POST |
| `image` | — | No genera entrada en `aura_form_responses`; se omite en el procesamiento de respuestas |
| `downloadable` | — | No genera entrada en `aura_form_responses`; solo renderiza el enlace de descarga en el frontend |

---

### 6.4 Propiedades del campo `select` — `multiple_select`

El campo `select` soporta selección múltiple mediante la propiedad `multiple_select`:

```json
{
  "id": "field_select_1",
  "field_uid": "fu_a1b2c3d4e5",
  "type": "select",
  "label": "Áreas de interés",
  "options": "Arte\nMúsica\nDeportes\nTecnología",
  "multiple_select": true,
  "required": "1"
}
```

- `multiple_select: false` → `<select>` estándar, guarda un string con el valor elegido
- `multiple_select: true` → `<select multiple>`, guarda JSON array con los valores seleccionados (igual que `checkbox`)

---

### Campos especiales para formularios de tipo `enrollment`

Cuando `form.type = 'enrollment'`, el builder muestra una sección adicional **"Campos de Inscripción"** con los siguientes campos preconstruidos (el admin los arrastra al formulario si los necesita):

| Campo especial | `mapping_key` | Descripción |
|---|---|---|
| Nombre | `first_name` | Se auto-mapea al crear el perfil del postulante |
| Apellido | `last_name` | |
| Correo | `email` | Obligatorio; se usa para deduplicar y para notificaciones |
| Teléfono | `phone` | |
| Número de identificación | `id_number` | Cédula, pasaporte, etc. |
| Fecha de nacimiento | `birthdate` | |
| Dirección | `address` | |
| Área / Programa elegido | `area_id` | Select dinámico con `wp_aura_areas` donde `type = 'program'` |
| Curso específico | `course_id` | Select dinámico con cursos del área seleccionada |
| Observaciones | `notes` | Texto libre |

> **Nota**: El `course_id` del formulario (`aura_forms.course_id`) es el curso al que está asociado el formulario en general. Los campos mapeados `area_id` y `course_id` permiten que el postulante seleccione entre varias opciones si el formulario es para un área con múltiples cursos activos.

---

## 7. Integración con Módulo Estudiantes

### 7.1 Bridge de Inscripción (tipo `enrollment`)

**Flujo completo cuando un visitante llena un formulario de tipo `enrollment`**:

```
1. Visitante llena formulario público (sin login)
         │
2. Aura_Forms_Submissions::ajax_submit()
   - Valida nonce, anti-spam, campos requeridos
   - Sanitiza todos los valores
   - Guarda en aura_form_submissions (data_json completo)
   - Dispara: do_action('aura_form_submission_saved', $submission_id, $form_id)
         │
3. Aura_Forms_Enrollment::on_submission_saved($submission_id, $form_id)
   - Verifica que form.type = 'enrollment'
   - Extrae campos mapeados (first_name, last_name, email, course_id, etc.)
   - Busca si ya existe aura_students.email → si no, crea perfil con status = 'applicant'
   - Crea registro en aura_student_enrollments:
       status = 'pending'
       source = 'form'
       form_submission_id = $submission_id
       course_id = (del mapeo o de form.course_id)
   - Actualiza aura_form_submissions.enrollment_id = ID creado
   - Dispara: do_action('aura_enrollment_created_from_form', $enrollment_id, $submission_id)
         │
4. Aura_Forms_Notifications::notify_admin_new_enrollment()
   - Envía email/WhatsApp al admin vía Aura_Notifications
         │
5. Panel "Postulantes" en Módulo Formularios / Módulo Estudiantes:
   Admin ve la postulación con todos los datos del formulario
   Botones: ✅ Aprobar | ❌ Rechazar | 🔙 Retirado
         │
6. Al aprobar:
   - aura_student_enrollments.status = 'active'
   - aura_students.status = 'active' (si era 'applicant')
   - Se asigna WP User o se activa la cuenta del estudiante
   - do_action('aura_student_enrollment_approved', $student_id, $enrollment_id)
```

### 7.2 HOOKs expuestos por este módulo

| Hook | Tipo | Cuándo se dispara | Parámetros |
|------|------|-------------------|-----------|
| `aura_form_submission_saved` | `do_action` | Al guardar cualquier submission | `$submission_id, $form_id` |
| `aura_form_enrollment_submitted` | `do_action` | Al guardar submission tipo enrollment | `$submission_id, $form_id, $enrollment_id` |
| `aura_form_assignment_completed` | `do_action` | Al completar una encuesta asignada | `$assignment_id, $submission_id, $student_id` |

### 7.3 HOOKs que escucha de otros módulos

| Hook | Viene de | Acción |
|------|----------|--------|
| `aura_student_enrollment_approved` | Módulo Estudiantes | Dispara auto-asignación de `feedback` forms configurados para `on_enrollment_approved` |
| `aura_student_course_completed` | Módulo Estudiantes | Dispara auto-asignación de `feedback` forms configurados para `on_course_complete` |

### 7.4 Panel de Postulantes Pendientes

La vista `templates/forms/enrollments-pending.php` muestra:

- **Filtros**: Por formulario, por curso, por estado (pending/approved/rejected/withdrawn), por fecha
- **Columnas**: Nombre, Email, Teléfono, Curso, Fecha postulación, Estado, Acciones
- **Al hacer clic en una postulación**: Modal lateral con todos los campos que llenó (renderizados desde `data_json`)
- **Botones de acción**:
  - ✅ **Aprobar** → llama `Aura_Students_Enrollments::ajax_approve_from_form($submission_id)`
  - ❌ **Rechazar** → modal con campo de motivo → `ajax_reject_from_form($submission_id, $reason)`
  - 🔙 **Retirado** → `ajax_mark_withdrawn($submission_id)` (la persona se arrepintió)
- **Estado del enrollment** se sincroniza en tiempo real en ambas vistas (Formularios y Estudiantes)

### 7.5 Asignación de Encuestas a Estudiantes

`Aura_Forms_Assignments` gestiona la tabla `aura_form_assignments`:

**Asignación manual** (desde panel "Encuestas Asignadas"):
```
Admin selecciona formulario tipo 'survey'
Admin filtra/selecciona estudiantes (por área, curso, estado, o individualmente)
Clic "Asignar" → inserta en aura_form_assignments (status = 'pending')
Aura_Forms_Notifications::notify_student_assignment() → email/WhatsApp
Estudiante ve la encuesta en portal frontend (pestaña "Mis Formularios")
```

**Asignación automática** (para formularios tipo `feedback`):
```
form.auto_assign_trigger = 'on_enrollment_approved'
form.auto_assign_days = 7  (enviar 7 días después de aprobación)

do_action('aura_student_enrollment_approved') es escuchado por:
Aura_Forms_Enrollment::on_enrollment_approved($student_id, $enrollment_id)
→ Busca formularios feedback asociados al course_id de ese enrollment
→ Si auto_assign_days > 0: schedules WP Cron para X días después
→ Si auto_assign_days = 0: crea assignment inmediatamente
```

---

## 8. Shortcodes y Frontend

### 8.1 `[aura_form id="X"]`

Renderiza un formulario específico. Se puede usar en cualquier página, entrada o widget.

**Parámetros**:
| Parámetro | Tipo | Defecto | Descripción |
|-----------|------|---------|-------------|
| `id` | int | — | ID del formulario (requerido) |
| `slug` | string | — | Alternativa a `id` |
| `redirect` | URL | — | URL de redirección post-envío (override del configurado) |

**Comportamiento**:
- Si `form.requires_login = 1` y el usuario no está autenticado: muestra mensaje con link de login
- Si `form.is_active = 0`: muestra mensaje de formulario cerrado
- Si `form.close_date` pasó: muestra mensaje de formulario expirado
- Genera nonce de seguridad dinámico por formulario
- Validación client-side + server-side (nunca confiar solo en JS)

### 8.2 `[aura_form_portal]`

Pestaña "📋 Mis Formularios" para el portal del estudiante (se integra con `[aura_student_portal]`).

- Lista encuestas pendientes del estudiante autenticado
- Muestra nombre del formulario, curso asociado, fecha de asignación, fecha de expiración
- Botón "Completar" → abre formulario en modal o redirige a página
- Lista de formularios completados con fecha de envío

### 8.3 URL amigable `/formulario/{slug}/`

Rewrite rule registrada en `Aura_Forms_Setup::register_rewrite_rules()`:

```php
add_rewrite_rule(
    '^formulario/([a-z0-9\-]+)/?$',
    'index.php?aura_form_slug=$matches[1]',
    'top'
);
add_rewrite_tag('%aura_form_slug%', '([a-z0-9\-]+)');
```

`Aura_Forms_Frontend` escucha `template_redirect` para interceptar esta query var y renderizar `templates/forms/frontend/form-render.php`.

---

## 9. Builder de Formularios (Admin)

El builder vive en `templates/forms/builder.php`. Utiliza **SortableJS** para reordenar campos (ya incluido en WordPress o cargable desde CDN). Es más sencillo que un canvas y más apropiado para formularios.

### 9.1 Interfaz del Builder

```
┌─────────────────────────────────────────────────────────────────────┐
│  [Título del formulario]                           [Vista previa] [Guardar] │
├────────────────┬────────────────────────────────────────────────────┤
│ AGREGAR CAMPO  │  CAMPOS DEL FORMULARIO (drag & drop para reordenar) │
│                │                                                    │
│ • Texto corto  │  ═══════════════════════════════════════════      │
│ • Párrafo      │  ☰ Nombre completo *         [Editar] [Borrar]    │
│ • Correo       │     Texto corto · Requerido · Mapeo: first_name   │
│ • Teléfono     │  ───────────────────────────────────────────────   │
│ • Número       │  ☰ Correo electrónico *      [Editar] [Borrar]    │
│ • Fecha        │     Email · Requerido · Mapeo: email              │
│ • Hora         │  ───────────────────────────────────────────────   │
│ • Opción única │  ☰ ¿Por qué desea inscribirse?  [Editar] [Borrar] │
│ • Opc. múltiple│     Párrafo · Opcional                            │
│ • Desplegable  │  ───────────────────────────────────────────────   │
│ • Escala       │  [+ Agregar nuevo campo]                          │
│ • Archivo      │                                                    │
│ • Separador    │                                                    │
│ • Texto fijo   │                                                    │
│ ── ── ── ──    │                                                    │
│ CAMPOS ESPECIALES│                                                  │
│ (solo enrollment)│                                                  │
│ • Área/Programa│                                                    │
│ • Curso        │                                                    │
└────────────────┴────────────────────────────────────────────────────┘
```

### 9.2 Herramienta de configuración de cada campo (panel lateral / modal)

Al hacer clic en "Editar" en un campo, se abre un panel con:
- **Etiqueta**: Label visible
- **Tipo**: Se puede cambiar (con advertencia si hay respuestas)
- **Requerido**: Toggle
- **Placeholder**: Texto de ayuda
- **Valor predeterminado**
- **Opciones** (para select/radio/checkbox): Editor de opciones inline (agregar/eliminar/reordenar)
- **Mapeo** (solo en formularios `enrollment`): Select de `mapping_key`
- **Para escala**: Min label, Max label, Max value (5 o 10)
- **Para archivo**: Extensiones permitidas, tamaño máximo

---

## 10. Análisis de Respuestas

`Aura_Forms_Analytics` genera estadísticas por campo usando los datos de `aura_form_submissions.data_json`.

### Por tipo de campo:

| Tipo | Visualización |
|------|--------------|
| `radio`, `select` | Gráfico de dona o barras con % por opción |
| `checkbox` | Barras horizontales con frecuencia por opción |
| `scale` | Gráfico de barras + promedio + NPS si max=10 |
| `text`, `textarea` | Nube de palabras (lista de respuestas más frecuentes) |
| `number` | Promedio, mínimo, máximo, distribución |
| `date` | Distribución por mes |

**Tecnología**: Chart.js (ya presente en el plugin por el módulo Finanzas).

---

## 11. Anti-Spam y Seguridad

Aplicar en `Aura_Forms_Submissions::ajax_submit()`:

1. **Nonce de WordPress**: `check_ajax_referer('aura_form_submit_{form_id}', 'nonce')` — generado dinámicamente por formulario
2. **Honeypot field**: Campo oculto con CSS `display:none` que los bots rellenan; si viene con valor → rechazar silenciosamente
3. **Rate limiting por IP**: Máximo 5 envíos por formulario por IP en 10 minutos (usando transients de WP)
4. **Sanitización estricta**: `sanitize_text_field()` para texto, `sanitize_email()` para email, `absint()` para numéricos, `wp_kses_post()` para textareas con formato
5. **Validación server-side**: Todos los campos `is_required = 1` deben tener valor; no confiar en JS
6. **Límite de tamaño de archivo**: Verificar `$_FILES[x]['size']` contra `max_file_size_kb` antes de `move_uploaded_file()`
7. **Extensiones permitidas**: Validar contra la whitelist del campo, nunca ejecutables (.php, .js, .exe)
8. **Almacenamiento de archivos**: Subir a `wp-content/uploads/aura-forms/{form_id}/{year}/{month}/` — fuera del webroot no es posible en WordPress, pero renombrar con hash + extensión segura

---

## 12. Fases de Implementación

---

### FASE 1 — Setup: Base de Datos, Capabilities y Menús

**Archivos**: `class-forms-setup.php`, `class-forms-admin.php`

**Tareas**:

#### 1.1 `Aura_Forms_Setup::init()`

```php
add_action('plugins_loaded', [__CLASS__, 'maybe_upgrade_db']);
add_action('init',           [__CLASS__, 'register_rewrite_rules']);
add_action('init',           [__CLASS__, 'register_cron_events']);
// Escuchar hooks de Estudiantes para auto-asignación
add_action('aura_student_enrollment_approved', [__CLASS__, 'on_enrollment_approved'], 10, 2);
add_action('aura_student_course_completed',    [__CLASS__, 'on_course_completed'],    10, 2);
```

#### 1.2 `create_tables()`

Ejecutar el SQL completo del apartado 3.2 con `dbDelta()`.

#### 1.3 `register_capabilities()`

Añadir las capabilities del apartado 4 al array de `Aura_Roles_Manager::get_all_capabilities()`.

#### 1.4 `register_rewrite_rules()`

Registrar la URL amigable `/formulario/{slug}/` (ver apartado 8.3).

#### 1.5 `Aura_Forms_Admin::init()`

Registrar menús del apartado 5. Encolar assets solo en páginas del módulo (`$screen->id` check).

**Resultado al completar FASE 1**: Plugin activa, tablas creadas, menús visibles, assets cargados.

---

### FASE 2 — Builder de Formularios (CRUD Admin)

**Archivos**: `class-forms-builder.php`, `templates/forms/builder.php`, `templates/forms/list.php`, `assets/js/forms-admin.js`, `assets/css/forms-admin.css`

**Tareas**:

#### 2.1 Listado de formularios (`templates/forms/list.php`)

- Tabla con: Título, Tipo (badge de color), Campos, Respuestas, Estado, **URL Pública**, Acciones
- Filtros: por tipo, por estado, búsqueda por título
- **Acciones por fila**: columna `Acciones` con cuatro botones redondos: Ver respuestas (verde), Editar (azul), Duplicar (ámbar), Eliminar (rojo). Los enlaces de exportación **CSV** y **Excel** aparecen como texto debajo del título del formulario (`.aura-forms-export-links`), no como botones.

**Columna URL Pública** — tres botones redondos (`.aura-url-btn`) con solo icono:

| Botón | Clase JS | Dashicon | Acción |
|-------|----------|----------|--------|
| Abrir en nueva pestaña | `.aura-url-open-btn` | `dashicons-external` | Enlace nativo `<a target="_blank" rel="noopener noreferrer">` |
| Copiar URL | `.aura-url-copy-btn` | `dashicons-clipboard` | Copia via Clipboard API; retroalimentación visual (icono cambia a `dashicons-yes` 2 s) |
| Código QR | `.aura-url-qr-btn` | `dashicons-share` | Abre modal `#aura-qr-modal` con QR generado por **QRCode.js** (CDN) |

**Modal QR** (`#aura-qr-modal`):
- Título del formulario + canvas/imagen QR 240×240 px
- URL en texto pequeño
- Botones: "Descargar QR (PNG)" (descarga canvas como PNG) y "Copiar URL"
- `qrcode.js` se carga desde CDN (`cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js`) solo en la página de listado
- Cierre con × o clic en overlay o tecla Escape

#### 2.2 Crear / Editar formulario — Información básica (`templates/forms/builder.php`)

La sección **Información básica** (siempre abierta por defecto) contiene los siguientes campos **en este orden**:

| # | Campo | Input | Campo DB | Notas |
|---|-------|-------|----------|-------|
| 1 | **Identidad Visual (Logo)** | Media picker (WP Media Library) | `logo_url VARCHAR(500)` | Botón "Seleccionar imagen" abre `wp.media`; preview con botón ×; recomendado PNG 300×90 px |
| 2 | **Tipo de formulario** | `<select>` | `type VARCHAR(20)` | Opciones: Genérico, Inscripción a Curso, Encuesta Asignada, Encuesta Automática |
| 3 | **Nombre de la Empresa / Organización** | `<input type="text">` | `company_name VARCHAR(300)` | Aparece en la cabecera del formulario público bajo el logo y sobre el título |
| 4 | **Título del formulario** | `<input type="text">` (requerido) | `title VARCHAR(300)` | Campo obligatorio |
| 5 | **Descripción (opcional)** | `<textarea>` | `description TEXT` | Texto introductorio visible al rellenar el formulario |
| 6 | **Área / Programa** | `<select>` — condicional | `area_id BIGINT` | Solo visible si tipo ≠ `generic` |
| 7 | **Curso específico** | `<select>` cargado vía AJAX | `course_id BIGINT` | Solo visible si tipo ≠ `generic`; se carga al seleccionar área |

**Logo picker — implementación JS** (inline en `builder.php`):
- Usa `wp.media` (ya encolado en la página del builder vía `wp_enqueue_media()`)
- Al seleccionar: usa `attachment.sizes.medium.url` si existe, si no `attachment.url`
- "Quitar logo": borra el hidden input y oculta el preview
- Descripción / texto de introducción
- Estado (activo/inactivo)
- Fecha de cierre (opcional)
- Límite de respuestas (opcional)
- URL de redirección post-envío (opcional)
- Mensaje de éxito
- Color principal
- Logo del formulario (Media Library picker)
- Emails de notificación al admin
- Toggle "Notificar al que envía"
- Si `feedback`: selector de trigger automático + días de espera

#### 2.3 Editor de campos (parte drag & drop del builder)

- Panel izquierdo: paleta de tipos de campo (ver sección 9.1)
- Panel derecho: zona de construcción con SortableJS
- Al agregar campo: aparece en la zona con handles de arrastre
- Al hacer clic en campo: abre panel de configuración lateral (sección 9.2)
- Reordenamiento: drag & drop con SortableJS; AJAX `ajax_reorder_fields()` guarda el nuevo `sort_order`

**AJAX Handlers en `Aura_Forms_Builder`**:

| Método | `action` WP | Descripción |
|--------|-------------|-------------|
| `ajax_save_form()` | `aura_forms_save` | Crea o actualiza formulario (metadatos). Si es nuevo, retorna `form.id` — el JS actualiza `formId` y la URL vía `history.replaceState` sin recargar. |
| `ajax_get_form()` | `aura_forms_get` | Devuelve datos completos del formulario + campos |
| `ajax_delete_form()` | `aura_forms_delete` | Soft delete (`deleted_at`) |
| `ajax_duplicate_form()` | `aura_forms_duplicate` | Copia form + campos con nuevo slug |
| `ajax_save_field()` | `aura_forms_field_save` | Crea o actualiza un campo individual |
| `ajax_delete_field()` | `aura_forms_field_delete` | Elimina un campo |
| `ajax_reorder_fields()` | `aura_forms_field_reorder` | Guarda array de IDs en nuevo orden |
| `ajax_get_courses()` | `aura_forms_get_courses` | Devuelve cursos por área_id (para selector dinámico) |

**Resultado al completar FASE 2**: Admin puede crear, editar y eliminar formularios con sus campos.

---

### FASE 3 — Frontend: Renderizado y Envío Público

**Archivos**: `class-forms-frontend.php`, `class-forms-submissions.php`, `templates/forms/frontend/form-render.php`, `templates/forms/frontend/form-success.php`, `assets/js/forms-frontend.js`, `assets/css/forms-frontend.css`

**Tareas**:

#### 3.1 Shortcode `[aura_form id="X"]`

- Cargar el form y sus campos desde la BD
- Verificar status, close_date, max_submissions
- Si requires_login y no autenticado: render mensaje con link al login
- Renderizar template `form-render.php` con todos los campos
- Inyectar honeypot + nonce

#### 3.2 URL amigable `/formulario/{slug}/`

Interceptar en `template_redirect`, cargar template `form-render.php`.

#### 3.3 Renderizado de campos (`form-render.php`)

Cada `field_type` tiene su fragmento HTML. Ejemplos:
```html
<!-- text -->
<div class="aura-field aura-field-text" data-field-id="<?php echo $field->id; ?>">
    <label><?php echo esc_html($field->label); ?><?php if ($field->is_required) echo ' <span>*</span>'; ?></label>
    <input type="text" name="field_<?php echo $field->id; ?>" placeholder="<?php echo esc_attr($field->placeholder); ?>">
</div>

<!-- scale (1-10) -->
<div class="aura-field aura-field-scale">
    <label><?php echo esc_html($field->label); ?></label>
    <div class="scale-buttons">
        <?php for ($i = 1; $i <= $field->max_value; $i++): ?>
            <button type="button" class="scale-btn" data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
        <?php endfor; ?>
        <input type="hidden" name="field_<?php echo $field->id; ?>" value="">
    </div>
</div>
```

#### 3.4 `ajax_submit()` en `Aura_Forms_Submissions`

1. `check_ajax_referer('aura_form_submit_' . $form_id, 'nonce')`
2. Verificar honeypot vacío
3. Rate limiting por IP
4. Cargar campos del formulario y validar cada valor
5. Sanitizar todos los valores
6. Si hay archivo: `wp_handle_upload()` con validación de extensión
7. Insertar en `aura_form_submissions`
8. `do_action('aura_form_submission_saved', $submission_id, $form_id)`
9. Retornar JSON `{success: true, message: '...', redirect_url: '...'}`

**Resultado al completar FASE 3**: Formulario público funcional, envíos guardados, anti-spam activo.

---

### FASE 4 — Tipo Inscripción: Bridge con Módulo Estudiantes

**Archivos**: `class-forms-enrollment.php`, `templates/forms/enrollments-pending.php`

**Tareas**:

#### 4.1 Listener del hook (`Aura_Forms_Enrollment`)

```php
add_action('aura_form_submission_saved', [__CLASS__, 'maybe_create_enrollment'], 10, 2);

public static function maybe_create_enrollment($submission_id, $form_id) {
    $form = // cargar aura_forms con id = $form_id
    if ($form->type !== 'enrollment') return;

    $submission = // cargar aura_form_submissions
    $data = json_decode($submission->data_json, true);

    // Extraer campos mapeados
    $mapped = self::extract_mapped_fields($form_id, $data);
    // $mapped = ['first_name' => 'Juan', 'email' => 'juan@email.com', 'course_id' => 5, ...]

    // Buscar/crear aura_students
    $student_id = self::find_or_create_student($mapped);

    // Crear enrollment pendiente
    $enrollment_id = $wpdb->insert('aura_student_enrollments', [
        'student_id'         => $student_id,
        'course_id'          => $mapped['course_id'] ?? $form->course_id,
        'enrollment_date'    => current_time('Y-m-d'),
        'status'             => 'pending',
        'enrolled_by'        => 0, // 0 = inscripción automática por formulario
        'notes'              => 'Inscripción desde formulario #' . $form_id,
    ]);

    // Vincular submission con enrollment
    $wpdb->update('aura_form_submissions', ['enrollment_id' => $enrollment_id], ['id' => $submission_id]);

    do_action('aura_form_enrollment_submitted', $submission_id, $form_id, $enrollment_id);
}
```

#### 4.2 Panel "Postulantes Pendientes" (`enrollments-pending.php`)

- Tabla con todos los `aura_form_submissions` que tienen `enrollment_id` no nulo y `aura_student_enrollments.status = 'pending'`
- Columnas: Nombre, Email, Curso, Formulario, Fecha postulación, Estado
- Filtros: por formulario, por curso, por estado (todas/pendiente/aprobado/rechazado/retirado)
- **Modal de detalle** (`submission-detail.php`): renderiza todos los campos `data_json` mostrando `label: valor` para cada campo del formulario
- Botones de acción (verificar capabilities):
  - ✅ **Aprobar** → AJAX `aura_forms_approve_enrollment($submission_id)` → llama a `Aura_Students_Enrollments::approve($enrollment_id)`
  - ❌ **Rechazar** → Modal pide motivo → AJAX `aura_forms_reject_enrollment($submission_id, $reason)`
  - 🔙 **Retirado** → AJAX `aura_forms_mark_withdrawn($submission_id)`

#### 4.3 Notificaciones a postulante

En `Aura_Forms_Notifications`:
- `notify_enrollment_received($submission_id)` — al guardar submission
- `notify_enrollment_approved($enrollment_id)` — al aprobar
- `notify_enrollment_rejected($submission_id, $reason)` — al rechazar

**Resultado al completar FASE 4**: Flujo completo postulante → enrollment pendiente → revisión admin → aprobación/rechazo.

---

### FASE 5 — Encuestas Asignadas y Portal Frontend

**Archivos**: `class-forms-assignments.php`, `class-forms-frontend.php`, `templates/forms/assignments.php`, `templates/forms/frontend/form-portal.php`

**Tareas**:

#### 5.1 Panel de asignaciones (admin) — `assignments.php`

- **Pestaña "Asignar"**:
  - Select de formulario (solo tipo `survey`)
  - Filtro de estudiantes: por área, por curso, por estado, o búsqueda individual
  - Tabla de estudiantes con checkbox multi-selección
  - Botón "Asignar seleccionados"
  - Fecha de expiración opcional
  - Notas opcionales

- **Pestaña "Estado de Asignaciones"**:
  - Tabla: Formulario, Estudiante, Asignado el, Expira el, Estado (badge), Enviado el
  - Filtros: por formulario, por estado (pendiente/completado/expirado)

**AJAX Handlers en `Aura_Forms_Assignments`**:

| Método | `action` WP | Descripción |
|--------|-------------|-------------|
| `ajax_assign()` | `aura_forms_assign` | Crea N registros en `aura_form_assignments` |
| `ajax_revoke()` | `aura_forms_revoke` | Marca assignment como expirado (no elimina) |
| `ajax_list_assignments()` | `aura_forms_list_assignments` | Lista con filtros para el panel admin |
| `ajax_list_student_forms()` | `aura_forms_student_pending` | Lista de encuestas pendientes para el portal del estudiante |

#### 5.2 Auto-asignación por trigger

`Aura_Forms_Setup::on_enrollment_approved($student_id, $enrollment_id)`:
```php
// Buscar formularios de tipo 'feedback' que tengan:
// auto_assign_trigger = 'on_enrollment_approved' y course_id coincidente
$forms = $wpdb->get_results(...);
foreach ($forms as $form) {
    if ($form->auto_assign_days > 0) {
        wp_schedule_single_event(
            time() + ($form->auto_assign_days * DAY_IN_SECONDS),
            'aura_forms_auto_assign',
            [$form->id, $student_id, $enrollment_id]
        );
    } else {
        Aura_Forms_Assignments::create_assignment($form->id, $student_id, $enrollment_id, null, 'auto');
    }
}
```

#### 5.3 Portal frontend — `[aura_form_portal]`

Shortcode que muestra al estudiante autenticado:
- **Pendientes**: tarjetas con nombre del formulario, descripción, curso, fecha de expiración, botón "Completar ahora"
- **Completados**: lista con fecha de envío, botón "Ver mi respuesta"
- Si no hay nada pendiente: mensaje "¡Estás al día! No tienes formularios pendientes."

Al pulsar "Completar ahora" → abre el formulario en modal (AJAX carga el HTML) → al enviar, registra `submission_id` en `aura_form_assignments.submission_id` y `status = 'completed'`.

**Resultado al completar FASE 5**: Encuestas asignadas y auto-asignadas; los estudiantes las resuelven desde el portal.

---

### FASE 6 — Análisis de Respuestas

**Archivos**: `class-forms-analytics.php`, `templates/forms/analytics.php`

**Tareas**:

#### 6.1 Vista de análisis por formulario

Al entrar a "Análisis" de un formulario específico:
- **Resumen**: Total de respuestas, % completados (para assigned), fecha de primera y última, promedio de tiempo de completación (si se registra el timestamp de inicio)
- **Por campo**: gráfico + estadística (ver tabla del apartado 10)

#### 6.2 AJAX `ajax_get_analytics($form_id)`

- Carga todos los `aura_form_submissions` del formulario
- Itera `data_json` de cada submission
- Para cada `field_id` agrega conteos / valores
- Retorna JSON estructurado para Chart.js

**Resultado al completar FASE 6**: Dashboard de análisis visual por formulario.

---

### FASE 7 — Exportación de Respuestas

**Archivos**: `class-forms-export.php`

**Tareas**:

#### 7.1 Exportar a CSV

`ajax_export_csv($form_id, $filters)`:
- Cabecera: ID, Fecha, Nombre, Email, [label de cada campo...]
- Filas: una por submission, expandiendo `data_json`
- Genera archivo `formulario-{slug}-{fecha}.csv` y lo devuelve como descarga directa

#### 7.2 Exportar a Excel (.xlsx)

`ajax_export_excel($form_id, $filters)`:
- Mismo contenido que CSV pero usando **PhpSpreadsheet** (ya en vendor)
- Aplica formato de cabecera (fondo azul, texto blanco, negrita)
- Auto-ajuste de columnas (`getColumnDimension()->setAutoSize(true)`)
- Nombre: `formulario-{slug}-{fecha}.xlsx`

#### 7.3 Exportar inscripciones tipo `enrollment`

Columnas adicionales: Estado de enrollment, Revisado por, Fecha de revisión, Motivo de rechazo.

**Resultado al completar FASE 7**: Admin puede descargar todas las respuestas en CSV y Excel.

---

### FASE 8 — Reportes y Configuración

**Archivos**: `class-forms-reports.php`, `class-forms-settings.php`, `templates/forms/reports.php`, `templates/forms/settings.php`

**Tareas**:

#### 8.1 Reportes (`reports.php`)

- **Reporte de actividad**: Formularios activos, respuestas por mes (Chart.js línea de tiempo)
- **Reporte de inscripciones**: Postulaciones por curso, % aprobadas, % rechazadas, % retiradas
- **Reporte de encuestas**: Formularios asignados vs completados, tasa de respuesta por área/curso

#### 8.2 Configuración global del módulo (`settings.php`)

| Configuración | Tipo | Descripción |
|---|---|---|
| Página de formulario público | Select de páginas WP | Página donde está `[aura_form]` por defecto |
| Página del portal de formularios | Select de páginas WP | Donde está `[aura_form_portal]` (para el email de asignación) |
| Habilitar honeypot | Toggle | Anti-spam honeypot (default: ON) |
| Máx. envíos por IP | Número | Rate limiting (default: 5 en 10 min) |
| Subir archivos a | Select | `uploads/aura-forms/` (no configurable, informativo) |
| Notificación admin por defecto | Email | Email del admin para nuevas respuestas |
| Texto del correo de asignación | Textarea | Asunto y cuerpo configurable para el email que recibe el estudiante al asignársele una encuesta |

**Resultado al completar FASE 8**: Módulo completamente funcional con reportes y configuración persisted.

---

## 13. Resumen de AJAX Actions

| `action` | Clase | Requiere capability |
|----------|-------|---------------------|
| `aura_forms_save` | `Aura_Forms_Builder` | `aura_forms_create` / `aura_forms_edit` |
| `aura_forms_get` | `Aura_Forms_Builder` | `aura_forms_edit` |
| `aura_forms_delete` | `Aura_Forms_Builder` | `aura_forms_delete` |
| `aura_forms_duplicate` | `Aura_Forms_Builder` | `aura_forms_create` |
| `aura_forms_field_save` | `Aura_Forms_Builder` | `aura_forms_edit` |
| `aura_forms_field_delete` | `Aura_Forms_Builder` | `aura_forms_edit` |
| `aura_forms_field_reorder` | `Aura_Forms_Builder` | `aura_forms_edit` |
| `aura_forms_get_courses` | `Aura_Forms_Builder` | `aura_forms_edit` |
| `aura_form_submit` | `Aura_Forms_Submissions` | `aura_forms_submit` (o público con nonce) |
| `aura_forms_approve_enrollment` | `Aura_Forms_Enrollment` | `aura_forms_enrollment_review` |
| `aura_forms_reject_enrollment` | `Aura_Forms_Enrollment` | `aura_forms_enrollment_review` |
| `aura_forms_mark_withdrawn` | `Aura_Forms_Enrollment` | `aura_forms_enrollment_review` |
| `aura_forms_assign` | `Aura_Forms_Assignments` | `aura_forms_assign` |
| `aura_forms_revoke` | `Aura_Forms_Assignments` | `aura_forms_assign` |
| `aura_forms_list_assignments` | `Aura_Forms_Assignments` | `aura_forms_assign` |
| `aura_forms_student_pending` | `Aura_Forms_Assignments` | `aura_forms_submit` (propio) |
| `aura_forms_analytics` | `Aura_Forms_Analytics` | `aura_forms_analytics` |
| `aura_forms_export_csv` | `Aura_Forms_Export` | `aura_forms_export` |
| `aura_forms_export_excel` | `Aura_Forms_Export` | `aura_forms_export` |
| `aura_forms_save_settings` | `Aura_Forms_Settings` | `aura_forms_settings` |
| `aura_forms_insert_enrollment_defaults` | `Aura_Forms_Builder` | `aura_forms_edit` |

---

## 14. Convenciones de Código

Seguir exactamente los mismos patrones que los módulos ya implementados:

1. **Prefijo de tablas**: `{$wpdb->prefix}aura_forms`, `{$wpdb->prefix}aura_form_fields`, etc. — **nunca hardcodear** `wp_aura_*`.
2. **Nonces**: `'aura_forms_nonce'` para admin; `'aura_form_submit_{$form_id}'` para frontend (dinámico por formulario).
3. **Queries preparadas**: `$wpdb->prepare()` en **todas** las queries con parámetros de usuario — sin excepción.
4. **Sanitización**: siempre antes de guardar en BD; `esc_html()`/`esc_attr()` siempre al renderizar en HTML.
5. **Soft delete**: `deleted_at = NOW()` en lugar de `DELETE FROM` para formularios (preservar respuestas históricas).
6. **`data_json` inmutable**: Una vez guardado un submission, su `data_json` nunca se modifica — es el registro oficial de lo que la persona envió.
7. **`wp_json_encode()` / `json_decode()` siempre con manejo de error**: verificar `json_last_error() === JSON_ERROR_NONE`.
8. **Capabilities**: verificar con `current_user_can()` en **todos** los métodos `ajax_*`.
9. **Respuestas AJAX**: siempre `wp_send_json_success()` / `wp_send_json_error()`, nunca `echo` directo.
10. **Enqueue de assets**: usar `wp_localize_script()` para pasar nonces y URLs al JS; nunca hardcodear URLs en JS.

---

## 15. Plan de Implementación Completo

| Fase | Descripción | Archivos principales | Estado |
|------|-------------|---------------------|--------|
| **FASE 1** | Setup: BD, capabilities, menús | `class-forms-setup.php`, `class-forms-admin.php` | ✅ Completado |
| **FASE 2** | Builder de formularios (admin CRUD) | `class-forms-builder.php`, `builder.php`, `list.php` | ✅ Completado |
| **FASE 3** | Frontend: renderizado y envío público | `class-forms-frontend.php`, `class-forms-submissions.php`, `form-render.php` | ✅ Completado |
| **FASE 4** | Inscripción: bridge con Módulo Estudiantes | `class-forms-enrollment.php`, `enrollments-pending.php` | ☐ Pendiente |
| **FASE 5** | Encuestas asignadas + portal frontend | `class-forms-assignments.php`, `assignments.php`, `form-portal.php` | ☐ Pendiente |
| **FASE 6** | Análisis de respuestas (gráficos) | `class-forms-analytics.php`, `analytics.php` | ✅ Completado |
| **FASE 7** | Exportación CSV / Excel | `class-forms-export.php` | ✅ Completado |
| **FASE 8** | Reportes y configuración global | `class-forms-reports.php`, `class-forms-settings.php` | ☐ Pendiente |

**Orden de prioridad de implementación sugerido**: FASE 1 → FASE 2 → FASE 3 → FASE 4 (las más críticas para el flujo de inscripción); luego FASE 5 → FASE 6 → FASE 7 → FASE 8.

---

## 16. Dependencias y Versiones

| Dependencia | Versión requerida | Ya disponible | Uso en este módulo |
|-------------|------------------|---------------|--------------------|
| WordPress | ≥ 6.0 | ✅ | Base del plugin |
| PHP | ≥ 8.0 | ✅ | Código del módulo |
| jQuery | ≥ 3.6 | ✅ (WP include) | Forms JS frontend |
| SortableJS | ≥ 1.15 | ⚠️ Cargar desde CDN o local | Builder drag & drop |
| Chart.js | ≥ 4.0 | ✅ (ya en assets/) | Análisis de respuestas |
| PhpSpreadsheet | ≥ 1.29 | ✅ (vendor/) | Exportación Excel |
| mPDF | ≥ 8.1 | ✅ (vendor/) | Reportes PDF |
| `Aura_Notifications` | — | ✅ (módulo common) | Emails / WhatsApp |
| Módulo Estudiantes | — | ✅ (requerido) | Bridge de inscripción |
| Módulo Certificados | — | ✅ (requerido previo) | Orden de implementación |

> **SortableJS**: Si no está ya en el plugin, descargarlo en `assets/js/sortable.min.js` (licencia MIT, ~50 KB). Alternativa: usar la API de drag & drop nativa del navegador.

---

*Documento creado: Marzo 2026 — AURA Business Suite v1.x*
*Última actualización: Abril 2026 — v1.7.7*
*Próximo módulo: Biblioteca (préstamo de libros) o módulo de integración global de notificaciones*

---

## 17. Historial de Implementaciones y Cambios (Registro de Desarrollo)

> Registro cronológico de mejoras y correcciones aplicadas tras la implementación inicial.

---

### 17.1 Columna "URL Pública" en listado de formularios

**Fecha**: Abril 2026 · **Archivo(s)**: `templates/forms/list.php`, `assets/css/forms-admin.css`, `assets/js/forms-admin.js`, `modules/forms/class-forms-admin.php`

Se añadió una columna **URL Pública** en la tabla de listado de formularios con tres botones redondos por fila:

| Botón | Clase | Comportamiento |
|-------|-------|---------------|
| Abrir en nueva pestaña | `.aura-url-open-btn` | Enlace nativo `<a target="_blank" rel="noopener noreferrer">` |
| Copiar URL | `.aura-url-copy-btn` | Clipboard API; icono cambia a `dashicons-yes` por 2 s |
| Código QR | `.aura-url-qr-btn` | Modal `#aura-qr-modal` con QRCode.js; descarga PNG; cierre con ×, overlay o Escape |

**QRCode.js** se carga vía CDN (`cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js`) únicamente en la página de listado (no en el builder).

---

### 17.2 Centralización de URL helpers (sincronización de configuración)

**Fecha**: Abril 2026 · **Archivo(s)**: `modules/forms/class-forms-frontend.php`, `templates/forms/builder.php`, `templates/forms/list.php`, `modules/forms/class-forms-notifications.php`, `templates/forms/settings.php`

Se detectó que `public_form_page` y `portal_page` (guardados en `wp_options` desde Configuración → Integración Frontend) **no se consumían**: las URLs estaban hardcodeadas en tres lugares distintos.

**Solución**: Se crearon dos helpers estáticos en `Aura_Forms_Frontend`:

```php
// URL pública de un formulario por slug
public static function get_form_url( string $slug ): string {
    return trailingslashit( site_url( 'formulario/' . sanitize_title( $slug ) ) );
}

// URL del portal del estudiante (lee portal_page de settings, fallback a home_url)
public static function get_portal_url(): string {
    $page_id = (int) Aura_Forms_Settings::get( 'portal_page' );
    if ( $page_id > 0 ) {
        $url = get_permalink( $page_id );
        if ( $url ) return $url;
    }
    return home_url( '/' );
}
```

Ambos helpers se usan ahora en `builder.php`, `list.php` y `class-forms-notifications.php`. Se añadió la variable `{portal}` a los reemplazos de email. Las descripciones de `settings.php` se actualizaron para clarificar el uso real de cada campo.

---

### 17.3 Corrección de URL 404 en `/formulario/{slug}/`

**Fecha**: Abril 2026 · **Archivo(s)**: `modules/forms/class-forms-setup.php`, `modules/forms/class-forms-settings.php`

**Problema**: La URL `/formulario/prueba/` devolvía 404 incluso tras regenerar las reglas de reescritura.

**Causa raíz**: Se usaba `add_rewrite_tag('%aura_form_slug%', '...')` que **no registra la query var como var pública**. WordPress ignoraba el parámetro y `get_query_var('aura_form_slug')` siempre retornaba vacío.

**Solución**:
1. Se eliminó `add_rewrite_tag()` del setup.
2. Se registró la query var vía filtro:
   ```php
   add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
   
   public static function register_query_vars( array $vars ): array {
       $vars[] = 'aura_form_slug';
       return $vars;
   }
   ```
3. `maybe_flush_rewrite()` ahora ejecuta `flush_rewrite_rules( true )` (flush duro — actualiza `.htaccess`).
4. El botón "Regenerar URLs" en configuración también hace flush duro.

---

### 17.4 Página pública de formulario — sin menú del tema

**Fecha**: Abril 2026 · **Archivo(s)**: `modules/forms/class-forms-frontend.php`, `assets/css/forms-frontend.css`

**Problema**: La URL `/formulario/{slug}/` mostraba el menú de navegación del tema activo.

**Solución**: `handle_form_url()` fue reescrito para generar una página HTML completamente autónoma en lugar de usar `get_header()`/`get_footer()`. Se mantienen `wp_head()` y `wp_footer()` para garantizar la carga correcta de scripts y estilos de WordPress sin el layout del tema.

Clase de body aplicada: `body.aura-form-standalone` — usada para ocultar con `display:none !important` todo elemento del tema (`.site-header`, `.main-navigation`, `footer.site-footer`, `#colophon`, `#wpadminbar`, etc.).

---

### 17.5 Rediseño WOW — standalone minimalista con modo claro/oscuro

**Fecha**: Abril 2026 · **Archivo(s)**: `modules/forms/class-forms-frontend.php`, `assets/css/forms-frontend.css`

**Objetivo**: Diseño moderno y minimalista para la página pública del formulario, sin header, con botón de cambio de tema claro/oscuro.

**Cambios en `handle_form_url()`**:
- Se eliminó el bloque `<header>` por completo.
- Se añadieron dos blobs decorativos `<span class="afs-blob">` como fondo ambiental (color del tema con blur).
- El footer contiene únicamente: nombre del sitio (enlace) + botón toggle dark/light (SVGs sol/luna inline).
- Script inline con detección de `prefers-color-scheme` y persistencia en `localStorage` bajo la clave `afs_theme`. El atributo `data-afs-theme` se aplica en `<html>` antes del primer paint (sin flash de modo incorrecto).

**Estructura de la página**:
```html
<html data-afs-theme="light|dark">
  <body class="aura-form-standalone">
    <!-- Blobs decorativos fijos -->
    <div class="afs-bg-blobs">...</div>
    <!-- Contenido principal: card centrado max-width 640px -->
    <main class="afs-main">
      <div class="afs-container"><!-- formulario --></div>
    </main>
    <!-- Footer mínimo con toggle -->
    <footer class="afs-footer">
      <a class="afs-footer-brand">Nombre del sitio</a>
      <button id="afs-theme-toggle" class="afs-theme-toggle"><!-- sol / luna --></button>
    </footer>
  </body>
</html>
```

**Tokens CSS (`assets/css/forms-frontend.css`)**:

| Token | Light | Dark |
|-------|-------|------|
| `--afs-bg` | `#f8fafc` | `#0b1120` |
| `--afs-surface` | `#ffffff` | `#141c2f` |
| `--afs-text` | `#0f172a` | `#f1f5f9` |
| `--afs-muted` | `#64748b` | `#94a3b8` |
| `--af-bg` | `#ffffff` | `#141c2f` |
| `--af-bg-soft` | `#f8fafc` | `#1c2742` |
| `--af-text` | `#111827` | `#f1f5f9` |
| `--af-text-muted` | `#6b7280` | `#94a3b8` |

El selector `[data-afs-theme="dark"]` en `<html>` sobreescribe tanto las variables `--afs-*` (layout standalone) como las `--af-*` (formulario), por lo que **todos los elementos** (labels, inputs, textareas, selects, escalas, checkboxes, términos, bloques de párrafo) se adaptan automáticamente sin reglas adicionales de clase.

**Overrides adicionales para colores hardcodeados en dark mode**:
- `.aura-form-notice--error` → fondo rojo semitransparente, texto `#fca5a5`
- `.aura-form-notice--login` → fondo azul semitransparente, texto `#93c5fd`
- `.aura-terms-disagree-msg` → igual que notice error
- `.aura-form-success` → fondo verde semitransparente, texto `#86efac`
- `select.aura-input` → flecha SVG actualizada a color muted neutral (`#94a3b8`)
- `.aura-download-btn:hover` → hover azul semitransparente en dark

---

### 17.6 Botón dark/light fijo en esquina superior derecha

**Fecha**: Abril 2026 · **Archivo(s)**: `modules/forms/class-forms-frontend.php`, `assets/css/forms-frontend.css`

El botón de cambio de tema fue movido del interior del `<footer>` a un elemento independiente con **`position: fixed; top: 16px; right: 16px; z-index: 9999`**, de modo que siempre permanece visible en la esquina superior derecha sin importar el scroll.

- Dimensiones aumentadas a 38×38 px con sombra sutil (`box-shadow: 0 2px 8px rgba(0,0,0,.15)`)
- Borde cambia a color brand en hover
- El `<footer>` queda simplificado solo al nombre del sitio como enlace

---

### 17.7 Campos "Identidad Visual" y "Nombre de la Empresa" · Reordenamiento del builder

**Fecha**: Abril 2026 · **Archivo(s)**: `templates/forms/builder.php`, `modules/forms/class-forms-setup.php`, `modules/forms/class-forms-builder.php`, `templates/forms/frontend/form-render.php`, `assets/css/forms-admin.css`, `assets/css/forms-frontend.css`

**Nuevo orden de la sección "Información Básica"** en el builder:

| # | Campo | Notas |
|---|-------|-------|
| 1 | Identidad Visual (Logo) | Media picker con preview y botón quitar |
| 2 | Tipo de formulario | Select, sin cambios |
| 3 | Nombre de la Empresa / Organización | Nuevo campo `company_name` |
| 4 | Título del Formulario * | Requerido, sin cambios |
| 5 | Descripción (opcional) | Textarea, sin cambios |
| 6 | Área/Programa | Condicional (solo tipos inscripción) |
| 7 | Curso específico | Condicional AJAX, solo tipos inscripción |

**Nuevo campo `company_name`**:
- Columna `company_name VARCHAR(300) DEFAULT NULL` en `wp_aura_forms` (migración automática, `DB_VERSION = '1.1.0'`)
- Sanitizado con `sanitize_text_field()` en `class-forms-builder.php`
- Guardado en INSERT y UPDATE del formulario

**Logo picker** (`builder.php` — script inline `wp.media`):
- Botón "Seleccionar imagen" abre el selector de medios de WordPress
- Preview de miniatura con botón × para quitar la selección
- El `<input type="hidden" name="logo_url">` se actualiza en tiempo real

---

### 17.8 Opción "Otro" en campos radio y select

**Fecha**: Abril 2026 · **Archivo(s)**: `modules/forms/class-forms-setup.php`, `modules/forms/class-forms-builder.php`, `templates/forms/builder.php`, `templates/forms/frontend/form-render.php`, `assets/js/forms-frontend.js`, `assets/css/forms-frontend.css`, `templates/forms/submissions-list.php`, `templates/forms/submission-detail.php`

**Nueva columna en BD**: `has_other TINYINT(1) NOT NULL DEFAULT 0` en `wp_aura_form_fields` (migración `DB_VERSION = '1.2.0'`).

**Builder**: nuevo toggle "Incluir opción libre 'Otro'" visible solo para tipos `radio` y `select` (sección `config-row-has-other`).

**Renderizado en frontend** (`form-render.php`):
- **radio**: al final del grupo de opciones se añade una opción extra `<label class="aura-radio-other">` con `<input type="radio" value="__other__">` y un `<input type="text" name="field_X_other_text">` (activado/desactivado según si el radio está seleccionado).
- **select**: se añade `<option value="__other__">Otro...</option>` y, debajo del `<select>`, un `<input type="text" class="aura-other-text-input aura-input">` que aparece solo cuando se elige "Otro".

**JS** (`forms-frontend.js`): método `bindOtherOption()` habilita/deshabilita el input de texto libre según la selección del usuario.

**Procesamiento al enviar** (`class-forms-submissions.php`): si el valor recibido es `__other__`, se reemplaza por el contenido de `field_X_other_text` (texto libre del usuario).

**CSS** (`forms-frontend.css`):
- `.aura-radio-other` — `flex-wrap: wrap` para que el input de texto se ponga en la misma línea.
- `.aura-other-text-input` — `flex: 1; min-width: 150px; width: auto` (no hereda el `width: 16px` del radio nativo gracias a `:not(.aura-other-text-input)`).
- `.aura-field-select .aura-other-text-input` — `width: 100%; margin-top: 8px`.

---

### 17.9 Correcciones de visualización en vistas de detalle y análisis

**Fecha**: Abril 2026 · **Archivo(s)**: `templates/forms/submission-detail.php`, `modules/forms/class-forms-submissions.php`, `modules/forms/class-forms-export.php`

#### Fecha de Nacimiento — Vista de detalle y modal de respuesta

**Problema**: El campo `birthdate` mostraba solo la edad numérica (ej. `47`) en la vista de detalle y en el modal de respuestas rápidas, sin mostrar la fecha real de nacimiento.

**Cómo se guarda en BD** (`class-forms-submissions.php` → `process_submission()`):
Al enviar un formulario con un campo `birthdate`, se generan **dos entradas** en `data_json`:
- `{field_uid}` → edad calculada en años (entero, ej. `47`)
- `{field_uid}_iso_date` → fecha de nacimiento en formato `YYYY-MM-DD`

**Cómo se muestra** (dos filas/columnas separadas en todos los contextos):

| Contexto | Archivo | Comportamiento |
|----------|---------|---------------|
| Vista detalle (página completa) | `submission-detail.php` | Dos filas separadas: `{label} (fecha)` con ISO date y `{label} (edad)` con "N años" |
| Modal rápido ("Ver") | `ajax_get_submission()` en `class-forms-submissions.php` | Ídem — devuelve dos entradas en el array `fields` |
| Exportación CSV/Excel | `class-forms-export.php` | Dos columnas: `{label} (fecha)` y `{label} (edad)` |
| Analytics | `class-forms-analytics.php` | Usa solo el valor numérico de edad para el histograma de distribución |

**Nota sobre submissions antiguas**: Si la submission fue guardada antes de esta implementación, solo existirá el valor de edad numérica (`{field_uid}`). En ese caso `{label} (fecha)` mostrará `—`.

#### Términos en español

**Problema**: Los campos `terms` y `accept_only_terms` guardaban valores internos en inglés (`agree`, `disagree`, `accepted`) que se mostraban tal cual al revisor.

**Solución** (`submission-detail.php`):
| Valor almacenado | Se muestra como |
|------------------|-----------------|
| `agree` | `De acuerdo` |
| `disagree` | `En desacuerdo` |
| `accepted` | `Aceptado` |
| `null` / vacío | `No aceptado` |

**Análisis de Respuestas** (`class-forms-analytics.php`): el método `compute_field_stats()` ahora traduce los labels antes de construir el array `data` para Chart.js, de modo que los gráficos de dona muestran directamente las etiquetas en español. Los tipos `terms` y `accept_only_terms` se procesan en su propio `case` del switch separado de `radio`/`select`.

---

### 17.10 Exportación CSV/Excel — correcciones

**Fecha**: Abril 2026 · **Archivo(s)**: `modules/forms/class-forms-export.php`, `templates/forms/list.php`, `assets/css/forms-admin.css`

#### Bug "Formulario no encontrado" en el handler de exportación

**Problema**: Al hacer clic en el enlace CSV/Excel la URL navegaba a `admin-ajax.php?action=aura_forms_export_csv&form_id=6&nonce=...` y devolvía el mensaje de error.

**Causa**: La query `SELECT id, title, slug, form_type FROM ...` usaba el alias `form_type`, pero la columna real en la tabla se llama `type`.

**Solución**: Se corrigió a `SELECT id, title, slug, type AS form_type FROM ...` para que `$form->form_type` siga funcionando en el resto del handler sin más cambios.

#### Posición de los botones CSV/Excel en la lista

Los enlaces de exportación se movieron de la columna `Acciones` (donde desbordaban) a **texto debajo del título** del formulario. Implementación:

```php
// En list.php, dentro de la celda col-title:
<div class="aura-forms-export-links">
    <a href="<?= csv_url ?>" class="aura-export-link">CSV</a>
    <span class="aura-export-sep">|</span>
    <a href="<?= excel_url ?>" class="aura-export-link">Excel</a>
</div>
```

**CSS** (`forms-admin.css`): `.aura-forms-export-links { margin-top: 3px; font-size: .8rem; }`, `.aura-export-link { color: #2271b1; text-decoration: none; }`.

---

### 17.11 Fix CSS: escala visible en modo standalone y select con fondo correcto

**Fecha**: Abril 2026 · **Archivo(s)**: `assets/css/forms-frontend.css`

**Escala `is-selected` invisible** (solo en `body.aura-form-standalone`): Los botones de la escala NPS/Likert que el usuario seleccionaba no se veían como activos porque el tema del sitio sobreescribía el color. Se añadió override con `!important`:
```css
body.aura-form-standalone .aura-scale-btn.is-selected {
    background: var(--af-primary) !important;
    color: #fff !important;
    border-color: var(--af-primary) !important;
}
```

**Select con textura de fondo del navegador**: El selector `select.aura-input` no redeclaraba `background-color`, dejando que el navegador/tema aplicara su gradiente nativo. Se añadió `background-color: var(--af-bg)` y `background-size: 12px 8px` para normalizar el aspecto en todos los navegadores.

---

### 17.12 Auto-guardado de formulario nuevo antes de guardar campos

**Fecha**: Abril 2026 · **Archivo(s)**: `assets/js/forms-builder.js`, `aura-business-suite.php` (v1.7.6)

**Problema**: Al crear un "Nuevo Formulario" y agregar un campo inmediatamente, el sistema mostraba el error `ID de formulario requerido.` porque el formulario aún no tenía ID en la BD.

**Flujo correcto de creación** (después de la corrección):

1. Ir a **Nuevo Formulario** (`page=aura-forms-new`).
2. Llenar obligatoriamente el **Título del formulario** (campo requerido del paso "Información Básica").
3. Hacer clic en cualquier campo de la paleta (ej. "Fecha de Nacimiento") → el sistema **auto-guarda el formulario** en segundo plano (AJAX `aura_forms_save`) y actualiza la URL a `?page=aura-forms-list&action=edit&id=X` sin recargar la página.
4. Se abre el panel de configuración del campo **solo tras** confirmar que el formulario ya tiene ID.
5. Rellenar la configuración del campo y hacer clic en **Guardar Campo** → el campo se guarda correctamente con el `form_id` ya disponible.

**Correcciones implementadas en `forms-builder.js`**:

| Función | Mejora |
|---------|--------|
| `bindPalette()` → `autoSaveFormThenAddField()` | Guarda el formulario vía AJAX antes de abrir el panel del campo; el panel solo aparece si el guardado fue exitoso. |
| Handler `#aura-field-config-save` (botón "Guardar Campo") | Resolución de `form_id` desde **tres fuentes** en orden: `formId` (variable del módulo) → `#config-form-id` (campo oculto del panel) → parámetro `?id=` de la URL. Si ninguna lo tiene, auto-guarda el formulario primero. |
| `bindFormSave()` (botón "Guardar formulario") | Actualiza la variable `formId` antes del `location.href` redirect, evitando inconsistencias si el usuario agrega un campo antes de que termine la navegación. |

**Versión bumpeada a `1.7.6`** para forzar la recarga del JS en navegadores con caché del archivo anterior.

**Renderizado en formulario público** (`form-render.php`):
- Si `company_name` tiene valor, se renderiza `<p class="aura-form-company">` entre el logo y el título
- Estilo: fuente pequeña (0.8 rem), negrita, mayúsculas, color `--af-text-muted` — adaptable a dark mode automáticamente

---

### 17.13 Integración Forms ↔ Estudiantes — Campos mapeados y campos predeterminados

**Fecha**: Abril 2026 · **Archivo(s)**: `templates/forms/builder.php`, `modules/forms/class-forms-builder.php`, `assets/js/forms-builder.js`, `assets/css/forms-admin.css`, `aura-business-suite.php` (v1.7.7)

**Objetivo**: Que un formulario de tipo **Inscripción** pueda incluir campos que, al enviarse, se copien automáticamente a los datos del estudiante en el Módulo de Estudiantes (secciones *Datos Personales* y *Postulación*).

#### Mapping keys soportados (campo `mapping_key` en `aura_form_fields`)

**Datos Personales** (se mapean a columnas de `aura_students`):

| `mapping_key` | Campo del estudiante | Tipo recomendado en formulario |
|---|---|---|
| `first_name` | Nombre(s) | `text` · Requerido |
| `last_name` | Apellido(s) | `text` · Requerido |
| `email` | Correo electrónico | `email` · Requerido |
| `phone` | Teléfono | `tel` |
| `birthdate` | Fecha de Nacimiento | `birthdate` |
| `gender` | Género | `radio` (opciones: Masculino/Femenino/No binario/Prefiero no decirlo) |
| `city` | Ciudad | `text` |
| `country` | País | `text` |
| `id_number` | Número de identificación | `text` |
| `address` | Dirección | `text` |

**Postulación** (se mapean a columnas de `aura_student_enrollments` o `aura_students`):

| `mapping_key` | Destino | Tipo recomendado |
|---|---|---|
| `course_id` | Curso al que se postula (`aura_student_enrollments.course_id`) | `select`/`radio` cargado dinámicamente |
| `area_id` | Área / Programa (filtro previo al `course_id`) | `select` cargado dinámicamente |
| `motivation` | Motivación / Por qué se postula (`aura_students.motivation`) | `textarea` |
| `notes` | Observaciones adicionales (`aura_student_enrollments.notes`) | `textarea` |

> **Campos auto-gestionados** (NO incluir en el formulario): `enrollment_date` (se asigna automáticamente al momento del envío), `status` (inicia como `pending` y el admin lo aprueba/rechaza desde el panel de Inscripciones Pendientes).

#### Flujo de sincronización al enviar el formulario

```
Usuario llena formulario de tipo enrollment en frontend
    ↓
Aura_Forms_Submissions::ajax_submit_form() guarda el submission
    ↓
Hook do_action('aura_form_submission_saved', $submission_id, $form_id)
    ↓
Aura_Forms_Enrollment::maybe_create_enrollment() se ejecuta:
    1. Verifica que el formulario sea tipo 'enrollment'
    2. Carga submission y decodifica data_json
    3. Construye mapa field_uid → mapping_key desde aura_form_fields
    4. Extrae campos mapeados de data_json
    5. find_or_create_student() → busca por email, si no existe crea el registro en aura_students
       (importa: first_name, last_name, phone, id_number, id_type, birthdate, gender, address, city, country, motivation)
    6. create_pending_enrollment() → crea enrollment en aura_student_enrollments con status='pending'
    7. Vincula submission.enrollment_id → enrollment.id
    ↓
Admin recibe notificación → revisa en "Inscripciones Pendientes"
    ↓
Admin aprueba → ajax_approve_enrollment() → status='active', crea WP user si no existe,
                 dispara hook 'aura_form_enrollment_approved'
```

#### Nuevo botón "Insertar campos predeterminados" en el Builder

Cuando el tipo de formulario es **Inscripción**, aparece un banner azul en el área de construcción con el botón **"Insertar campos predeterminados"**.

Al pulsarlo (`AJAX aura_forms_insert_enrollment_defaults`), se insertan automáticamente los siguientes campos con sus mapping_keys ya configurados:

1. **Título de Sección**: "Datos Personales"
2. `text` → Nombre(s) · mapping: `first_name` · requerido
3. `text` → Apellido(s) · mapping: `last_name` · requerido
4. `email` → Correo electrónico · mapping: `email` · requerido
5. `tel` → Teléfono · mapping: `phone`
6. `birthdate` → Fecha de Nacimiento · mapping: `birthdate`
7. `radio` → Género · mapping: `gender` · opciones: Masculino / Femenino / No binario / Prefiero no decirlo
8. `text` → Ciudad · mapping: `city`
9. `text` → País · mapping: `country`
10. **Título de Sección**: "Información de Postulación"
11. `textarea` → ¿Por qué deseas postularte? · mapping: `motivation`

El sistema **omite** los campos cuyo `mapping_key` ya exista en el formulario, por lo que el botón se puede usar de forma segura para agregar solo los que faltan.

#### Cambios implementados

| Archivo | Cambio |
|---|---|
| `templates/forms/builder.php` | Dropdown de mapping_key reorganizado con `<optgroup>` "Datos Personales" / "Postulación"; se agregaron `gender`, `city`, `country`, `motivation`; banner de inscripción con botón "Insertar campos predeterminados" |
| `modules/forms/class-forms-builder.php` | Nuevo método `ajax_insert_enrollment_defaults()` + registro del AJAX `aura_forms_insert_enrollment_defaults` |
| `assets/js/forms-builder.js` | `bindEnrollmentDefaults()` — maneja clic en el botón, auto-guarda el formulario si no tiene ID, llama al AJAX y recarga la página con los campos insertados; `bindTypeVisibility()` actualizado para mostrar/ocultar el banner al cambiar el tipo |
| `assets/css/forms-admin.css` | Estilos para `.aura-enrollment-defaults-banner` e `#aura-insert-enrollment-defaults` |
| `aura-business-suite.php` | Versión bumpeada a `1.7.7` |

---

### 17.13 Bugs en el Panel de Postulantes Pendientes (`enrollments-pending.php`)

**Fecha**: Abril 2026 · **Archivo**: `templates/forms/enrollments-pending.php`

**Síntoma**: El panel "Postulantes — Inscripciones desde Formularios" (`admin.php?page=aura-forms-enrollments`) aparecía vacío aunque existían submissions y enrollments vinculados. El dropdown de filtro de formularios también aparecía vacío.

**Bugs encontrados y corregidos** (4):

| # | Línea | Descripción | Antes | Después |
|---|-------|-------------|-------|---------|
| 1 | 44 | Filtro de formularios usaba columna inexistente | `form_type = 'enrollment'` | `type = 'enrollment'` |
| 2 | 58 | WHERE de la query principal usaba columna inexistente | `f.form_type = 'enrollment'` | `f.type = 'enrollment'` |
| 3 | 89 | SELECT usaba columna inexistente en `aura_form_submissions` | `fs.created_at AS submitted_at` | `fs.submitted_at AS submitted_at` |
| 4 | 48 | Filtro de cursos usaba columna inexistente en `aura_student_courses` (no tiene `deleted_at`) | `deleted_at IS NULL OR deleted_at = '0000-00-00...'` | `status = 'active'` |

**Causa raíz**: La tabla `aura_forms` tiene columna `type` (no `form_type`); la tabla `aura_form_submissions` tiene columna `submitted_at` (no `created_at`); la tabla `aura_student_courses` usa `status = 'active'` como indicador de vigencia (sin `deleted_at`).

---

### 17.14 Bug crítico: Estudiante no se crea al enviar formulario de inscripción — ENUM vacío

**Fecha**: Abril 2026 · **Archivo**: `modules/forms/class-forms-enrollment.php`

**Síntoma**: Al enviar un formulario tipo `enrollment` (ej. formulario id=9 "Formulario de Inscripcion 2026"), no aparecía ningún estudiante ni en **Estudiantes → Listado de Estudiantes** (status `applicant`) ni en **Formularios → Postulantes** (`enrollment_id IS NOT NULL`). La submission se guardaba pero `enrollment_id` quedaba `NULL`.

**Causa raíz**: En `find_or_create_student()` (`class-forms-enrollment.php`), los campos ENUM `id_type` y `gender` se pasaban como cadena vacía `''` cuando el formulario no tenía esos campos mapeados. MySQL en modo estricto (`STRICT_TRANS_TABLES`, activo por defecto en MySQL 8.x / MariaDB 10.x) rechaza `''` como valor ENUM inválido → el `$wpdb->insert()` fallaba silenciosamente → el estudiante no se creaba → `find_or_create_student()` retornaba `null` → `maybe_create_enrollment()` hacía `return` sin crear nada.

**Flujo fallido** (antes del fix):
```
ajax_submit() → do_action('aura_form_submission_saved') 
  → maybe_create_enrollment()
    → extract_mapped_fields()       ← OK
    → is_email($email)              ← OK (email presente)
    → find_or_create_student()
        → $wpdb->insert(['id_type' => '', 'gender' => '']) 
        → MySQL ERROR: Data truncated for column 'id_type'
        → return null               ← falla silenciosamente
    → $student_id = null → return  ← sin crear nada
```

**Fix aplicado** en `find_or_create_student()`:

1. Normalizar `id_type` al ENUM de la tabla (`cedula`, `pasaporte`, `dni`, `otro`):

| Valor recibido del form | Valor guardado en DB |
|------------------------|---------------------|
| `cedula` | `cedula` |
| `passport` / `pasaporte` | `pasaporte` |
| `dni` | `dni` |
| `otro` / `other` | `otro` |
| `''` (vacío) o cualquier otro | `null` |

2. Normalizar `gender` al ENUM de la tabla (`M`, `F`, `otro`, `prefiero_no_decir`):

| Valor recibido del form | Valor guardado en DB |
|------------------------|---------------------|
| `m` / `masculino` / `male` | `M` |
| `f` / `femenino` / `female` | `F` |
| `otro` / `other` / `o` | `otro` |
| `prefiero_no_decir` / `p` | `prefiero_no_decir` |
| `''` (vacío) o cualquier otro | `null` |

3. Ambos valores pasan `null` a `$wpdb->insert()`, que lo traduce correctamente a SQL `NULL` (los campos tienen `DEFAULT NULL` en el schema).

**Después del fix**: el INSERT siempre tiene valores válidos → estudiante creado con `status = 'applicant'` → enrollment creado con `status = 'pending'`.

---

### 17.15 Bug crítico: `deleted_at IS NULL` en tabla `aura_form_fields` que no tiene esa columna

**Fecha**: Abril 2026 · **Archivos**: `modules/forms/class-forms-enrollment.php`, `modules/forms/class-forms-builder.php`

**Síntoma**: Al enviar un formulario tipo `enrollment`, no se creaba ningún estudiante ni enrollment. El `enrollment_id` en la submission quedaba `NULL`. Esto sucedía SIEMPRE, sin importar qué campos tuviera el formulario.

**Causa raíz**: La tabla `aura_form_fields` **no tiene columna `deleted_at`** (a diferencia de `aura_forms` que sí la tiene). Sin embargo, 4 queries SQL filtraban por `AND deleted_at IS NULL` en esta tabla:

| # | Archivo | Línea | Método | Efecto |
|---|---------|-------|--------|--------|
| 1 | `class-forms-enrollment.php` | 59 | `ajax_get_field_labels()` | Labels del modal de detalle vacíos |
| 2 | `class-forms-enrollment.php` | 116 | `maybe_create_enrollment()` | **Crítico**: mapping keys vacíos → email no se extrae → return sin crear nada |
| 3 | `class-forms-builder.php` | 648 | `ajax_insert_enrollment_defaults()` | Campos predeterminados duplicados |
| 4 | `class-forms-builder.php` | 655 | `ajax_insert_enrollment_defaults()` | Sort order incorrecto |

**Bug #2 era el bloqueante principal**: sin mapping keys, `extract_mapped_fields()` no podía mapear ningún campo del `data_json` → el email quedaba vacío → `is_email('')` retornaba false → early return antes de crear estudiante/enrollment.

**Fix aplicado**: Eliminar `AND deleted_at IS NULL` de las 4 queries contra `aura_form_fields`.

**Verificación**: Tras el fix, la submission #10 del formulario "Inscripciones 2026" (id=12) procesó correctamente: 9 mapping keys resueltos, email válido extraído, estudiante creado (status=applicant, gender=M), enrollment creado (status=pending, course_id=1).

---

### 17.16 Bug: `$wpdb->prepare(null)` produce `''` en vez de SQL `NULL` (WP 6.9.4)

**Fecha**: Abril 2026 · **Archivo**: `modules/forms/class-forms-enrollment.php`

**Síntoma**: Relacionado con 17.14. Aunque el fix original normalizaba id_type/gender a `null` de PHP, `$wpdb->insert()` con format `%s` convertía `null` en `''` (cadena vacía) al pasar por `$wpdb->prepare()` en WordPress 6.9.4. Esto violaba la restricción ENUM nuevamente.

**Fix aplicado**: En `find_or_create_student()`, se construyen los arrays `$insert_data` y `$insert_format` dinámicamente, **omitiendo** las columnas ENUM y date cuando su valor es `null`/vacío. Así MySQL usa el `DEFAULT NULL` definido en el schema.

---

### 17.17 Bug: `form_type` inexistente en módulo de asignaciones

**Fecha**: Abril 2026 · **Archivos**: `templates/forms/assignments.php`, `modules/forms/class-forms-assignments.php`

**Síntoma**: El panel de Asignaciones no mostraba formularios de tipo encuesta/feedback en el selector, y la creación de asignaciones fallaba silenciosamente.

**Causa raíz**: La columna de `aura_forms` se llama `type`, no `form_type`. 3 queries usaban `form_type`:

| # | Archivo | Efecto |
|---|---------|--------|
| 1 | `assignments.php` (L24-26) | SELECT con `form_type` → error SQL, selector vacío |
| 2 | `assignments.php` (L81) | `$sf->form_type` → null → tipo no mostrado |
| 3 | `class-forms-assignments.php` (L210-219) | SELECT + validación con `form_type` → siempre null → validación fallida |

**Fix aplicado**: Reemplazar `form_type` por `type` en las 3 ocurrencias.

---

### 17.18 Bug: `deleted_at IS NULL` en tabla `aura_student_courses` que no tiene esa columna

**Fecha**: Abril 2026 · **Archivo**: `templates/forms/assignments.php`

**Síntoma**: El selector de cursos en el panel de Asignaciones estaba vacío.

**Causa raíz**: La tabla `aura_student_courses` no tiene columna `deleted_at`. Usa `status` como indicador de vigencia.

**Fix aplicado**: Cambiar `WHERE ( deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' )` por `WHERE status = 'active'`.

---

## Estandar de Tablas — DataTables Responsive

Este modulo aplica el **Estandar de Tablas DataTables Responsive** definido en `PRD.md seccion 5.6`. Los puntos obligatorios son:

- **CDN requerido:** DataTables 2.2.2 (core) + Responsive 3.0.4 — ambos CSS y JS, encolados como dependencias encadenadas.
- **`responsive: true`** en la instancia de DataTable (requiere que el plugin Responsive este cargado).
- **`dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>'`** — layout estandar con contador arriba y paginacion abajo.
- **`searching: false`** — filtros propios del modulo reemplazan la busqueda nativa de DataTables.
- **`responsivePriority`** en columnas clave: ID → 10000, nombre/entidad principal → 1, acciones → 1.
- **CSS dashicons** para el boton expandir/colapsar en movil (f344 azul cerrado / f343 gris abierto).
- **Idioma unificado:** `info: '_TOTAL_ {entidades}'`, `lengthMenu: 'Mostrar _MENU_ por pagina'`, paginate: `"first":"«","last":"»","previous":"‹","next":"›"`.

Ver `PRD.md` seccion `5.6` para el codigo completo de referencia.