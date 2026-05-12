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

- **Constante**: `DB_VERSION = '1.0.0'` en `Aura_Forms_Setup`
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

| `field_type` | Nombre visible | Input HTML | Notas |
|---|---|---|---|
| `text` | Texto corto | `<input type="text">` | Largo máximo configurable |
| `email` | Correo electrónico | `<input type="email">` | Validación formato email |
| `tel` | Teléfono | `<input type="tel">` | Máscara opcional |
| `number` | Número | `<input type="number">` | `min`, `max`, `step` configurables |
| `date` | Fecha | `<input type="date">` | Rango min/max opcional |
| `time` | Hora | `<input type="time">` | |
| `textarea` | Párrafo (texto largo) | `<textarea>` | Filas configurables |
| `select` | Lista desplegable | `<select>` | Opciones libres + opción "Otro" |
| `radio` | Opción única | `<input type="radio">` | Opciones libres + opción "Otro" |
| `checkbox` | Opción múltiple | `<input type="checkbox">` | Opciones libres, respuesta JSON array |
| `scale` | Escala (NPS / Likert) | Botones 1–N | `max_value` define el rango (5, 10, etc.) |
| `file` | Subida de archivo | `<input type="file">` | `allowed_extensions`, `max_file_size_kb` |
| `hidden` | Campo oculto | `<input type="hidden">` | Valor fijo; útil para pasar `course_id` |
| `section_title` | Título de sección | `<h3>` visual | Solo estructura; no genera respuesta |
| `paragraph` | Texto explicativo | `<p>` visual | Solo lectura; no genera respuesta |

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

- Tabla con: Título, Tipo (badge de color), Curso asociado, Nº respuestas, Estado (activo/inactivo), Acciones
- Filtros: por tipo, por estado, por curso
- Acciones por fila: Editar, Ver respuestas, Duplicar, Eliminar (con confirmación), Copiar URL del formulario

#### 2.2 Crear / Editar formulario — Metadatos (`templates/forms/builder.php` — sección superior)

Campos de configuración general:
- Título del formulario
- Tipo: `generic | enrollment | survey | feedback`
- Si `enrollment` o `survey`/`feedback`: select de Área y Curso
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
| `ajax_save_form()` | `aura_forms_save` | Crea o actualiza formulario (metadatos) |
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
| **FASE 1** | Setup: BD, capabilities, menús | `class-forms-setup.php`, `class-forms-admin.php` | ☐ Pendiente |
| **FASE 2** | Builder de formularios (admin CRUD) | `class-forms-builder.php`, `builder.php`, `list.php` | ☐ Pendiente |
| **FASE 3** | Frontend: renderizado y envío público | `class-forms-frontend.php`, `class-forms-submissions.php`, `form-render.php` | ☐ Pendiente |
| **FASE 4** | Inscripción: bridge con Módulo Estudiantes | `class-forms-enrollment.php`, `enrollments-pending.php` | ☐ Pendiente |
| **FASE 5** | Encuestas asignadas + portal frontend | `class-forms-assignments.php`, `assignments.php`, `form-portal.php` | ☐ Pendiente |
| **FASE 6** | Análisis de respuestas (gráficos) | `class-forms-analytics.php`, `analytics.php` | ☐ Pendiente |
| **FASE 7** | Exportación CSV / Excel | `class-forms-export.php` | ☐ Pendiente |
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
*Próximo módulo: Biblioteca (préstamo de libros) o módulo de integración global de notificaciones*
