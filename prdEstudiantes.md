<div align="center">

# PRD — Módulo de Estudiantes e Inscripciones
## AURA Business Suite — Plugin WordPress
### Documento de Requerimientos Completo para Implementación

> **Versión**: 1.0.0 · **Fecha**: Marzo 2026  
> **Dependencias previas requeridas**: Plugin AURA activo, Módulo Finanzas activo, Módulo Notificaciones globales configurado  
> **Ruta de implementación**: `modules/students/`  
> **Módulo posterior**: Módulo Certificados y Diplomas (requiere este módulo terminado)

</div>

---

## 1. Descripción General

### 1.1 Propósito

El Módulo de Estudiantes gestiona el ciclo de vida completo de un participante dentro del instituto CEM: desde la solicitud de inscripción (puede llegar por formulario público o creación manual), pasando por el flujo de aprobación, asignación de beca, registro de pagos, hasta el acceso a su portal personal en el frontend donde puede ver su información, historial de pagos y certificados.

### 1.2 Alcance

- **Backend (WordPress Admin)**: CRUD de estudiantes, cursos, inscripciones, becas, pagos; dashboard de estado de pagos; reportes.
- **Frontend (Sitio público)**: Portal del estudiante con login, dashboard personal, historial de pagos, descarga de comprobantes y certificados.
- **Formulario público de inscripción**: Página accesible sin login donde cualquier postulante puede enviar su solicitud (datos personales + campos de la solicitud).
- **Integración con Módulo Finanzas**: Cada pago de cuota registrado aquí genera automáticamente una transacción de ingreso en el módulo financiero.
- **Integración con Módulo Certificados**: Al aprobar la graduación de un estudiante, se habilita la emisión del diploma desde ese módulo.

### 1.3 Tipos de Perfil que Gestiona Este Módulo

| Código | Descripción | Ejemplo |
|--------|-------------|---------|
| `student` | Estudiante de un curso o programa académico | Participante de clases de música |
| `volunteer` | Voluntario registrado formalmente | Persona que apoya actividades del instituto |
| `teacher` | Instructor o facilitador de cursos | Maestro de un taller |
| `participant` | Participante de evento o actividad puntual | Asistente a un campamento de verano |
| `intern` | Practicante o pasante | Estudiante en práctica profesional |

> **Nota**: Todos estos perfiles comparten la misma infraestructura de base de datos y lógica; el campo `profile_type` los diferencia. Los capabilities de WordPress se adaptan según el tipo.

---

## 2. Arquitectura del Sistema

### 2.1 Estructura de Archivos

```
modules/students/
├── class-students-setup.php         # Tablas BD, CPTs, migración, capabilities
├── class-students-admin.php         # Menús WP Admin, enqueue de assets
├── class-students-crud.php          # AJAX handlers: crear/editar/eliminar estudiantes
├── class-students-courses.php       # AJAX handlers: CRUD de cursos y programas
├── class-students-enrollments.php   # AJAX handlers: inscripciones + flujo aprobación
├── class-students-payments.php      # AJAX handlers: registro y consulta de pagos
├── class-students-scholarships.php  # AJAX handlers: becas (internas y externas)
├── class-students-dashboard.php     # Dashboard admin (KPIs, tablas)
├── class-students-frontend.php      # Portal frontend: shortcodes, login, dashboard
├── class-students-notifications.php # Envío de emails/WhatsApp vía Aura_Notifications
└── class-students-reports.php       # Reportes exportables Excel/PDF

templates/students/
├── dashboard.php                    # Dashboard principal admin
├── list.php                         # Listado de estudiantes con filtros
├── student-form.php                 # Formulario crear/editar estudiante (admin)
├── course-list.php                  # Listado de cursos
├── course-form.php                  # Formulario crear/editar curso
├── enrollments.php                  # Gestión de inscripciones y aprobaciones
├── payments.php                     # Registro y listado de pagos
├── scholarships.php                 # Gestión de becas
├── settings.php                     # Configuración del módulo
└── frontend/
    ├── portal.php                   # Portal frontend del estudiante
    ├── login.php                    # Página de login frontend
    ├── dashboard-student.php        # Dashboard personal del estudiante
    ├── enrollment-form.php          # Formulario público de inscripción
    └── payment-history.php          # Historial de pagos del estudiante

assets/
├── css/
│   ├── students-admin.css
│   └── students-frontend.css
└── js/
    ├── students-admin.js
    └── students-frontend.js
```

### 2.2 Clases PHP — Resumen de Responsabilidades

| Clase | Descripción |
|-------|-------------|
| `Aura_Students_Setup` | `init()` estático: registra tablas con `dbDelta()`, CPTs, capabilities, crons |
| `Aura_Students_Admin` | `init()` estático: `add_menu_page()`, `add_submenu_page()`, `wp_enqueue_scripts` |
| `Aura_Students_CRUD` | Métodos `ajax_*` para crear/editar/eliminar estudiantes y obtener detalle |
| `Aura_Students_Courses` | Métodos `ajax_*` para cursos y programas |
| `Aura_Students_Enrollments` | Métodos `ajax_*` para inscripciones, aprobación, rechazo |
| `Aura_Students_Payments` | Métodos `ajax_*` para registrar cuotas, calcular deuda, listar pagos |
| `Aura_Students_Scholarships` | Métodos `ajax_*` para asignar y modificar becas |
| `Aura_Students_Dashboard` | Método `render_dashboard()` con KPIs y tablas resumen |
| `Aura_Students_Frontend` | Shortcodes: `[aura_student_portal]`, `[aura_enrollment_form]`, `[aura_student_login]` |
| `Aura_Students_Notifications` | Wrapper sobre `Aura_Notifications` (global) para notificaciones del módulo |
| `Aura_Students_Reports` | Generación de reports con PhpSpreadsheet y mPDF |

### 2.3 Registro en `aura-business-suite.php`

En el método `load_dependencies()` agregar:
```php
// Módulo Estudiantes
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-setup.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-admin.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-crud.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-courses.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-enrollments.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-payments.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-scholarships.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-dashboard.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-frontend.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-notifications.php';
require_once AURA_PLUGIN_DIR . 'modules/students/class-students-reports.php';
```

En el método `init_modules()` agregar:
```php
Aura_Students_Setup::init();
Aura_Students_Admin::init();
Aura_Students_CRUD::init();
Aura_Students_Courses::init();
Aura_Students_Enrollments::init();
Aura_Students_Payments::init();
Aura_Students_Frontend::init();
Aura_Students_Reports::init();
```

---

## 3. Base de Datos

### 3.1 Versión y Migración

- **Constante**: `DB_VERSION = '1.1.0'` (bump al agregar `preferred_areas`)
- **Opción WP**: `aura_students_db_version`
- **Migración**: Usar `dbDelta()` (mismo patrón que `Aura_Inventory_Setup`)

### 3.2 Tablas SQL

```sql
-- ─────────────────────────────────────────────────────────────
-- 1. Cursos / Programas académicos
-- ─────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_student_courses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(300) NOT NULL,
    slug            VARCHAR(300) NOT NULL UNIQUE,
    description     TEXT NULL,
    area_id         BIGINT UNSIGNED NULL,           -- FK → wp_aura_areas.id (opcional)
    instructor_id   BIGINT UNSIGNED NULL,           -- FK → wp_users.ID
    duration_weeks  SMALLINT UNSIGNED NULL,
    max_students    SMALLINT UNSIGNED NULL DEFAULT 0, -- 0 = sin límite
    base_cost       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency        VARCHAR(5) NOT NULL DEFAULT 'USD',
    status          ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    start_date      DATE NULL,
    end_date        DATE NULL,
    finance_cat_id  BIGINT UNSIGNED NULL,           -- FK → aura_finance_categories.id (para integración)
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status    (status),
    INDEX idx_area      (area_id),
    INDEX idx_instructor (instructor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. Estudiantes / Perfiles de participante
-- ─────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_students (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wp_user_id      BIGINT UNSIGNED NULL UNIQUE,    -- FK → wp_users.ID (NULL mientras es postulante)
    profile_type    ENUM('student','volunteer','teacher','participant','intern') NOT NULL DEFAULT 'student',
    first_name      VARCHAR(150) NOT NULL,
    last_name       VARCHAR(150) NOT NULL,
    email           VARCHAR(254) NOT NULL,
    phone           VARCHAR(30) NULL,
    phone_country   VARCHAR(5) NULL DEFAULT '+1',   -- Para WhatsApp
    id_number       VARCHAR(50) NULL,               -- Cédula / Pasaporte / DNI
    id_type         ENUM('cedula','pasaporte','dni','otro') NULL,
    birthdate       DATE NULL,
    gender          ENUM('M','F','otro','prefiero_no_decir') NULL,
    address         VARCHAR(500) NULL,
    city            VARCHAR(100) NULL,
    country         VARCHAR(100) NULL DEFAULT 'US',
    photo_url       VARCHAR(500) NULL,
    -- Áreas de interés (seleccionadas en el formulario de inscripción)
    preferred_areas TEXT NULL,                      -- JSON: [1,5,12] IDs de wp_aura_areas donde el postulante quiere participar
    -- Campos de inscripción (llenados en el formulario de solicitud)
    motivation      TEXT NULL,                      -- ¿Por qué quieres ingresar?
    supported_by    VARCHAR(300) NULL,              -- ¿Quién te apoya / recomienda?
    talent          TEXT NULL,                      -- ¿Qué talento o habilidad tienes?
    experience      TEXT NULL,                      -- Experiencia previa relevante
    extra_info      TEXT NULL,                      -- Campo libre
    -- Estado en el sistema
    status          ENUM('applicant','approved','active','graduated','withdrawn','rejected') NOT NULL DEFAULT 'applicant',
    rejection_reason VARCHAR(500) NULL,
    approved_by     BIGINT UNSIGNED NULL,           -- FK → wp_users.ID
    approved_at     DATETIME NULL,
    graduated_at    DATE NULL,
    -- Meta y auditoría
    notes           TEXT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,                  -- Soft delete
    INDEX idx_status       (status),
    INDEX idx_email        (email),
    INDEX idx_wp_user      (wp_user_id),
    INDEX idx_profile_type (profile_type),
    INDEX idx_deleted      (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 3. Inscripciones (relación estudiante ↔ curso)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_student_enrollments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id          BIGINT UNSIGNED NOT NULL,   -- FK → aura_students.id
    course_id           BIGINT UNSIGNED NOT NULL,   -- FK → aura_student_courses.id
    enrollment_date     DATE NOT NULL,
    status              ENUM('pending','active','completed','withdrawn','suspended') NOT NULL DEFAULT 'pending',
    -- Costos de esta inscripción (snapshot al momento de inscribir)
    base_cost           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    scholarship_type    ENUM('none','internal','external') NOT NULL DEFAULT 'none',
    scholarship_pct     TINYINT UNSIGNED NOT NULL DEFAULT 0, -- % de descuento (0-100)
    scholarship_sponsor VARCHAR(200) NULL,           -- Quien otorga la beca externa
    scholarship_notes   TEXT NULL,
    net_cost            DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- base_cost * (1 - scholarship_pct/100)
    -- Esquema de pago
    payment_scheme      ENUM('full','installments','scholarship_full') NOT NULL DEFAULT 'full',
    installment_count   TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- Número de cuotas
    installment_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- Monto por cuota
    first_payment_date  DATE NULL,
    -- Totales
    total_paid          DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- Calculado dinámicamente
    balance_due         DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- net_cost - total_paid
    payment_status      ENUM('unpaid','partial','paid','overdue') NOT NULL DEFAULT 'unpaid',
    -- Auditoría
    enrolled_by         BIGINT UNSIGNED NOT NULL,
    notes               TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student   (student_id),
    INDEX idx_course    (course_id),
    INDEX idx_status    (status),
    INDEX idx_pay_status (payment_status),
    UNIQUE KEY uk_student_course (student_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 4. Pagos individuales (cuotas)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_student_payments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id       BIGINT UNSIGNED NOT NULL,   -- FK → aura_student_enrollments.id
    student_id          BIGINT UNSIGNED NOT NULL,   -- FK → aura_students.id (denormalizado para queries)
    course_id           BIGINT UNSIGNED NOT NULL,   -- FK → aura_student_courses.id (denormalizado)
    payment_date        DATE NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    payment_method      ENUM('cash','transfer','card','check','other') NOT NULL DEFAULT 'cash',
    reference_number    VARCHAR(100) NULL,          -- Número de transferencia, etc.
    receipt_url         VARCHAR(500) NULL,          -- Comprobante subido
    installment_num     TINYINT UNSIGNED NULL,      -- Número de cuota (1, 2, 3...)
    finance_tx_id       BIGINT UNSIGNED NULL,       -- FK → aura_finance_transactions.id (integración)
    notes               TEXT NULL,
    registered_by       BIGINT UNSIGNED NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_student    (student_id),
    INDEX idx_date       (payment_date),
    INDEX idx_finance_tx (finance_tx_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 5. Cuotas programadas (calendario de pagos esperados)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_student_installment_schedule (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id   BIGINT UNSIGNED NOT NULL,
    installment_num TINYINT UNSIGNED NOT NULL,
    due_date        DATE NOT NULL,
    expected_amount DECIMAL(12,2) NOT NULL,
    paid_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status          ENUM('pending','paid','overdue','partial') NOT NULL DEFAULT 'pending',
    payment_id      BIGINT UNSIGNED NULL,           -- FK → aura_student_payments.id cuando se paga
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_due_date   (due_date),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Capabilities del Módulo

Registrar en `Aura_Roles_Manager::get_all_capabilities()` y `get_capabilities_for_ui()` bajo la sección `students`:

```php
'students' => array(
    'aura_students_create'              => __('Crear/registrar estudiantes manualmente', 'aura-suite'),
    'aura_students_edit'                => __('Editar información de estudiantes', 'aura-suite'),
    'aura_students_delete'              => __('Eliminar estudiantes (solo admin)', 'aura-suite'),
    'aura_students_view_all'            => __('Ver todos los estudiantes', 'aura-suite'),
    'aura_students_view_own'            => __('Ver solo información propia (estudiante)', 'aura-suite'),
    'aura_students_approve'             => __('Aprobar/rechazar solicitudes de inscripción', 'aura-suite'),
    'aura_students_enrollments_manage'  => __('Gestionar inscripciones a cursos', 'aura-suite'),
    'aura_students_scholarships_view'   => __('Ver becas asignadas', 'aura-suite'),
    'aura_students_scholarships_assign' => __('Asignar/modificar becas', 'aura-suite'),
    'aura_students_payments_register'   => __('Registrar pagos de estudiantes', 'aura-suite'),
    'aura_students_payments_view_all'   => __('Ver estado de pagos de todos', 'aura-suite'),
    'aura_students_payments_view_own'   => __('Ver solo pagos propios (estudiante)', 'aura-suite'),
    'aura_students_quotas_config'       => __('Configurar esquemas de cuotas', 'aura-suite'),
    'aura_students_status_view'         => __('Ver estado paz y salvo', 'aura-suite'),
    'aura_students_courses_manage'      => __('Crear y gestionar cursos/programas', 'aura-suite'),
    'aura_students_reports'             => __('Ver reportes de inscripciones y pagos', 'aura-suite'),
    'aura_students_settings'            => __('Configurar el módulo de estudiantes', 'aura-suite'),
),
```

**Asignación automática a Administradores** (en `ensure_admin_capabilities()`): todas las capabilities de la sección `students`.

**Capability asignada al WP user creado para el estudiante** al aprobarse:
- `aura_students_view_own`
- `aura_students_payments_view_own`

---

## 5. Menús de WordPress Admin

```
🎓 Estudiantes                          (capab: aura_students_view_all)
├── Dashboard                           (capab: aura_students_view_all)
├── Listado de Estudiantes              (capab: aura_students_view_all)
├── + Nuevo Estudiante                  (capab: aura_students_create)
├── Cursos y Programas                  (capab: aura_students_courses_manage)
├── Inscripciones                       (capab: aura_students_enrollments_manage)
├── Pagos y Cuotas                      (capab: aura_students_payments_view_all)
├── Becas                               (capab: aura_students_scholarships_view)
├── Paz y Salvo                         (capab: aura_students_status_view)
├── Reportes                            (capab: aura_students_reports)
└── Configuración                       (capab: aura_students_settings)
```

**Páginas admin** (slugs):
- `aura-students` → Dashboard
- `aura-students-list` → Listado
- `aura-students-new` → Formulario nuevo/editar
- `aura-students-courses` → Cursos
- `aura-students-enrollments` → Inscripciones
- `aura-students-payments` → Pagos
- `aura-students-scholarships` → Becas
- `aura-students-paz-salvo` → Paz y Salvo
- `aura-students-reports` → Reportes
- `aura-students-settings` → Configuración

---

## 6. Fases de Implementación

---

### FASE 1 — Infraestructura Base (Setup, BD, Menús)

**Archivos**: `class-students-setup.php`, `class-students-admin.php`

**Tareas**:
1. Crear `class-students-setup.php`:
   - Constantes `DB_VERSION = '1.0.0'` y `DB_VERSION_OPTION = 'aura_students_db_version'`
   - Método `init()`: llama a `register_post_types()`, y `add_action('admin_init', [__CLASS__, 'create_tables'])`
   - Método `create_tables()`: crea las 5 tablas con `dbDelta()` (ver sección 3.2)
   - Método `register_post_types()`: no hay CPTs propios (todo es tabla custom); se puede omitir o dejar vacío
   - Método `ensure_capabilities()`: llama a `Aura_Roles_Manager::ensure_admin_capabilities()` al activar

2. Crear `class-students-admin.php`:
   - Método `init()`: `add_action('admin_menu', [__CLASS__, 'register_menus'])`
   - Método `register_menus()`: registrar menú principal + submenús (ver sección 5)
   - Método `enqueue_assets($hook)`: cargar CSS/JS solo en páginas `aura-students*`
   - Incluir `wp_localize_script()` con nonce: `wp_create_nonce('aura_students_nonce')`

3. Registrar en `aura-business-suite.php` (ver sección 2.3)

4. Crear CSS base `assets/css/students-admin.css` con estilos del módulo

**Verificación**: El menú 🎓 Estudiantes aparece en el admin para usuarios con `aura_students_view_all`.

---

### FASE 2 — CRUD de Cursos y Programas

> **Relación con Módulo Áreas**: Un Curso es una *instancia* de un Área-Programa (`wp_aura_areas` con `type='program'`). El campo `area_id` vincula el curso al área que define el programa. Un Área puede tener múltiples cursos en distintas fechas/ediciones. Al filtrar cursos en el admin, se puede filtrar por área.

**Archivos**: `class-students-courses.php`, `templates/students/course-list.php`, `templates/students/course-form.php`

**Tareas**:
1. Método `ajax_save_course()`: crear o editar curso en `aura_student_courses`
   - Campos: name, slug (auto-generado), description, area_id, instructor_id, duration_weeks, max_students, base_cost, currency, status, start_date, end_date, finance_cat_id
   - Nonce: `aura_students_nonce`
   - Capability: `aura_students_courses_manage`

2. Método `ajax_delete_course()`: soft-delete (cambiar status a `archived`)

3. Método `ajax_get_course()`: retornar JSON de un curso por ID

4. Método `ajax_list_courses()`: listado paginado con filtros (status, área)

5. Template `course-list.php`:
   - Tabla con columnas: Nombre, Área, Instructor, Duración, Costo base, Estado, Inscritos, Acciones
   - Botón "➕ Nuevo Curso"
   - Modal "Ver detalle" con todos los campos más lista de inscripciones activas

6. Template `course-form.php`:
   - Formulario para crear/editar con validación JS
   - Select de áreas (cargado desde `wp_aura_areas`)
   - Select de instructores (users con `aura_students_*`)
   - Select de categoría financiera (cargado desde `aura_finance_categories` tipo `income`)
   - Calculadora visual: "Si base_cost = $500 y hay beca 50%, neto = $250"

**Hooks AJAX**:
```php
add_action('wp_ajax_aura_students_save_course',    [__CLASS__, 'ajax_save_course']);
add_action('wp_ajax_aura_students_delete_course',  [__CLASS__, 'ajax_delete_course']);
add_action('wp_ajax_aura_students_get_course',     [__CLASS__, 'ajax_get_course']);
add_action('wp_ajax_aura_students_list_courses',   [__CLASS__, 'ajax_list_courses']);
```

---

### FASE 3 — CRUD de Estudiantes

**Archivos**: `class-students-crud.php`, `templates/students/list.php`, `templates/students/student-form.php`

**Tareas**:
1. Método `ajax_save_student()`:
   - Crear o editar en `aura_students`
   - Al crear: generar código de estudiante `CEM-EST-{AÑO}-{SECUENCIAL-4}` y guardarlo en `user_meta` al aprobarse
   - Validar email único
   - Sanitizar todos los campos
   - Capability: `aura_students_create` (crear) / `aura_students_edit` (editar)

2. Método `ajax_delete_student()`:
   - Soft delete (`deleted_at = NOW()`)
   - Capability: `aura_students_delete`

3. Método `ajax_get_student()`: JSON completo del estudiante con sus inscripciones y pagos

4. Método `ajax_list_students()`: paginado, filtros por status, profile_type, curso, fecha

5. Template `list.php`:
   - Tabla con columnas: Foto, Nombre completo, Email, Tipo de perfil, Estado, Cursos inscritos, Saldo pendiente, Acciones
   - Filtros: estado (postulante/aprobado/activo/graduado/retirado), tipo, curso
   - Búsqueda en tiempo real por nombre/email
   - Botón "➕ Nuevo Estudiante" (requiere `aura_students_create`)
   - Botón "Ver detalle" abre modal con resumen completo
   - Colores de estado:
     - `applicant` → gris (postulante pendiente)
     - `approved` → azul (aprobado, pendiente de activar)
     - `active` → verde
     - `graduated` → dorado
     - `withdrawn` → naranja
     - `rejected` → rojo

6. Template `student-form.php`:
   - Sección 1: Información personal (nombre, apellido, email, teléfono, cédula, fecha nacimiento, foto)
   - Sección 2: Datos de la solicitud (motivación, quién apoya, talento, experiencia, info extra)
   - Sección 3: **Áreas de Interés** — checkboxes o multi-select cargado de `wp_aura_areas WHERE type='program' AND status='active'`; valor guardado en `preferred_areas` (JSON); visible en creación manual y edición; en el formulario público se muestra como selector en Fase 8
   - Sección 4: Estado y tipo de perfil (solo editable con `aura_students_edit`)
   - Sección 5: Notas internas (solo visible con `aura_students_view_all`)

**Hooks AJAX**:
```php
add_action('wp_ajax_aura_students_save',   [__CLASS__, 'ajax_save_student']);
add_action('wp_ajax_aura_students_delete', [__CLASS__, 'ajax_delete_student']);
add_action('wp_ajax_aura_students_get',    [__CLASS__, 'ajax_get_student']);
add_action('wp_ajax_aura_students_list',   [__CLASS__, 'ajax_list_students']);
```

---

### FASE 4 — Flujo de Aprobación e Inscripción

**Archivos**: `class-students-enrollments.php`, `templates/students/enrollments.php`

**Flujo completo**:

```
POSTULANTE LLENA FORMULARIO (o admin crea manualmente)
        │
        ▼
status = 'applicant' → Admin recibe notificación
        │
  (Admin revisa)
        │
   ┌────┴────┐
RECHAZA     APRUEBA
   │           │
status=      status='approved'
'rejected'   + se crea WP user (subscriber)
             + se asigna capability aura_students_view_own
             + email con credenciales al estudiante
             + Admin inscribe al curso
             │
             ▼
    status='active' (inscripción activa)
    enrollment en aura_student_enrollments
             │
   (Al completar el curso)
             │
             ▼
    status='graduated'
    puede emitirse certificado (Módulo Certificados)
```

**Tareas**:
1. Método `ajax_approve_student()`:
   - Requiere `aura_students_approve`
   - Cambia `status = 'approved'`
   - Crea WP user: `wp_insert_user(['user_login' => email, 'user_email' => email, 'role' => 'subscriber', 'first_name' => ..., 'last_name' => ...])`
   - Asigna `aura_students_view_own` y `aura_students_payments_view_own` al nuevo user
   - Guarda `wp_user_id` en `aura_students`
   - Genera contraseña aleatoria si no se especifica
   - Envía email de bienvenida con credenciales vía `Aura_Students_Notifications::send_approval_email()`

2. Método `ajax_reject_student()`:
   - Requiere `aura_students_approve`
   - Cambia `status = 'rejected'`, guarda `rejection_reason`
   - Envía email de notificación al postulante

3. Método `ajax_enroll_student()`:
   - Requiere `aura_students_enrollments_manage`
   - Inserta en `aura_student_enrollments`
   - Calcula `net_cost = base_cost * (1 - scholarship_pct / 100)`
   - Genera schedule de cuotas en `aura_student_installment_schedule`
   - Cambia `status = 'active'` en `aura_students`
   - Envía notificación por email/WhatsApp

4. Método `ajax_update_enrollment()`: editar beca, esquema de pago, estado

5. Método `ajax_graduate_student()`:
   - Requiere `aura_students_enrollments_manage`
   - Cambia `status = 'completed'` en enrollment y `status = 'graduated'` en student
   - Guarda `graduated_at = date`
   - Activa el botón "Emitir Certificado" (hook: `do_action('aura_student_graduated', $student_id, $enrollment_id)`)

6. Template `enrollments.php`:
   - Lista de solicitudes pendientes (postulantes)
   - Para cada uno: foto, nombre, email, tipo de perfil, fecha de solicitud, motivación resumida
   - Botones: "✅ Aprobar" → abre modal con formulario de inscripción; "❌ Rechazar" → razón
   - Modal de aprobación/inscripción:
     - Datos del estudiante (readonly), incluyendo `preferred_areas` como referencia visual
     - **Selector de Área** (filtra la lista de Cursos disponibles): cargado de `wp_aura_areas WHERE type='program' AND status='active'`
     - Selector de Curso (filtrado por área si se seleccionó una)
     - Tipo de beca (ninguna / interna / externa)
     - Porcentaje de beca (0-100)
     - Esquema de pago (completo / cuotas)
     - Número de cuotas y fecha del primer pago (si son cuotas)
     - Costo calculado automáticamente en tiempo real
   - Lista de inscripciones activas con estado de pago, agrupables por área

**Hooks AJAX**:
```php
add_action('wp_ajax_aura_students_approve',  [__CLASS__, 'ajax_approve_student']);
add_action('wp_ajax_aura_students_reject',   [__CLASS__, 'ajax_reject_student']);
add_action('wp_ajax_aura_students_enroll',   [__CLASS__, 'ajax_enroll_student']);
add_action('wp_ajax_aura_students_update_enrollment', [__CLASS__, 'ajax_update_enrollment']);
add_action('wp_ajax_aura_students_graduate', [__CLASS__, 'ajax_graduate_student']);
```

---

### FASE 5 — Gestión de Pagos (Integración con Finanzas)

**Archivos**: `class-students-payments.php`, `templates/students/payments.php`

**Tareas**:
1. Método `ajax_register_payment()`:
   - Requiere `aura_students_payments_register`
   - Inserta en `aura_student_payments`
   - Actualiza `total_paid` y `balance_due` en `aura_student_enrollments`
   - Actualiza `payment_status`: si `balance_due <= 0` → `'paid'`, si `total_paid > 0` → `'partial'`
   - Actualiza la cuota correspondiente en `aura_student_installment_schedule`
   - **Integración con Finanzas**: Llama a método `create_finance_transaction()`:
     ```php
     global $wpdb;
     $wpdb->insert("{$wpdb->prefix}aura_finance_transactions", [
         'amount'         => $amount,
         'type'           => 'income',
         'description'    => "Pago cuota #{$installment_num} - {$student_name} / {$course_name}",
         'date'           => $payment_date,
         'category_id'    => $course->finance_cat_id,
         'status'         => 'approved',
         'related_module' => 'students',
         'related_id'     => $payment_id,
         'created_by'     => get_current_user_id(),
     ]);
     $finance_tx_id = $wpdb->insert_id;
     // Guardar finance_tx_id en aura_student_payments
     ```
   - Envía recibo por email si se especifica

2. Método `ajax_get_payment_summary()`: totales pagados, saldo, cuotas vencidas por estudiante

3. Método `ajax_list_payments()`: listado filtrable por curso, fecha, estado

4. Template `payments.php`:
   - Tabla de inscripciones con columnas: Estudiante, Curso, Beca, Costo neto, Pagado, Saldo, Estado pago
   - Filtros: curso, estado de pago, mes/año
   - Al hacer clic en una fila: expande detalle de cuotas con estado de cada una
   - Botón "➕ Registrar Pago" junto a cada inscripción con saldo pendiente
   - Modal de registro de pago:
     - Select de número de cuota (pre-cargado con cuotas pendientes)
     - Monto (pre-llenado con el monto de la cuota)
     - Fecha de pago
     - Método de pago
     - Número de referencia
     - Upload de comprobante
   - Indicadores visuales de urgencia:
     - 🔴 Rojo: cuota vencida (due_date < hoy)
     - 🟡 Amarillo: cuota vence en ≤ 7 días
     - 🟢 Verde: al día

**Hooks AJAX**:
```php
add_action('wp_ajax_aura_students_register_payment',   [__CLASS__, 'ajax_register_payment']);
add_action('wp_ajax_aura_students_payment_summary',    [__CLASS__, 'ajax_get_payment_summary']);
add_action('wp_ajax_aura_students_list_payments',      [__CLASS__, 'ajax_list_payments']);
```

---

### FASE 6 — Dashboard Admin y Paz y Salvo

**Archivos**: `class-students-dashboard.php`, `templates/students/dashboard.php`, `templates/students/paz-salvo.php`

**KPIs del Dashboard**:

| Tarjeta | Valor | Icono |
|---------|-------|-------|
| Total Estudiantes Activos | `COUNT(*) WHERE status = 'active'` | 🎓 |
| Postulantes Pendientes | `COUNT(*) WHERE status = 'applicant'` | ⏳ |
| Graduados Este Año | `COUNT(*) WHERE graduated_at YEAR = current_year` | 🏅 |
| Cuotas Vencidas | `COUNT(*) FROM installment_schedule WHERE status = 'overdue'` | 🔴 |
| Ingresos del Mes | `SUM(amount) FROM student_payments WHERE MONTH = current_month` | 💰 |
| Ingresos Proyectados | `SUM(net_cost) FROM enrollments WHERE status = 'active'` | 📈 |

**Gráficos** (usar ApexCharts, ya incluido):
- Barras mensuales: Pagos recibidos vs Proyectados (últimos 6 meses)
- Donuts: Distribución por tipo de perfil (estudiante/voluntario/teacher/etc.)
- Línea: Nuevas inscripciones por semana/mes

**Tabla "Últimas Actividades"** (10 más recientes):
- Nuevo postulante registrado
- Pago registrado
- Inscripción aprobada
- Cuota vencida sin pago

**Paz y Salvo** (`templates/students/paz-salvo.php`):
- Tabla de todos los estudiantes activos
- Columnas: Nombre, Curso, Saldo total, Cuotas vencidas, Estado (color)
- Filtro rápido: "Solo morosos" / "Solo al día"
- Exportar a Excel listado de morosos
- Botón individual "Enviar recordatorio" por WhatsApp o email

---

### FASE 7 — Becas

**Archivos**: `class-students-scholarships.php`, `templates/students/scholarships.php`

**Tipos de beca**:

| Tipo | Descripción | Responsable |
|------|-------------|-------------|
| `internal` | El instituto cubre el costo (donación interna) | Director Académico |
| `external` | Un patrocinador externo cubre el costo | Director + patrocinador |

**Porcentajes comunes** (sugeridos en la UI como botones de acceso rápido): 25%, 50%, 75%, 100%

**Tareas**:
1. Método `ajax_assign_scholarship()`:
   - Requiere `aura_students_scholarships_assign`
   - Actualiza `scholarship_type`, `scholarship_pct`, `scholarship_sponsor`, `scholarship_notes` en `aura_student_enrollments`
   - Recalcula `net_cost` y `balance_due`
   - Regenera el schedule de cuotas si el esquema de pago cambia
   - Registra en auditoría

2. Método `ajax_list_scholarships()`: listado con filtros por tipo, porcentaje, curso

3. Template `scholarships.php`:
   - Vista de todas las becas activas
   - Total de descuentos otorgados este mes/año
   - Desglose: cuánto cubre el instituto vs patrocinadores externos
   - Formulario de asignación (en modal)

**Hooks AJAX**:
```php
add_action('wp_ajax_aura_students_assign_scholarship', [__CLASS__, 'ajax_assign_scholarship']);
add_action('wp_ajax_aura_students_list_scholarships',  [__CLASS__, 'ajax_list_scholarships']);
```

---

### FASE 8 — Portal Frontend del Estudiante

**Archivos**: `class-students-frontend.php`, `templates/students/frontend/`

**Shortcodes disponibles**:

```php
// Página de login del estudiante (no usar el login de WP)
[aura_student_login redirect="/mi-portal"]

// Portal completo del estudiante (requiere estar logueado con rol 'subscriber' + capability aura_students_view_own)
[aura_student_portal]

// Formulario público de inscripción (no requiere login)
[aura_enrollment_form type="student"]                 // Inscripción como estudiante
[aura_enrollment_form type="volunteer"]               // Solicitud como voluntario
[aura_enrollment_form type="teacher"]                 // Solicitud como instructor
[aura_enrollment_form type="participant"]             // Solicitud como participante

// Verificación de paz y salvo pública
[aura_student_paz_salvo_check]
```

**Configuración recomendada de páginas WordPress**:

| Título de página | Slug sugerido | Shortcode |
|-----------------|---------------|-----------|
| Mi Portal | `/mi-portal` | `[aura_student_portal]` |
| Acceso Estudiantes | `/acceso-estudiantes` | `[aura_student_login]` |
| Formulario de Inscripción | `/inscribete` | `[aura_enrollment_form type="student"]` |

**Dashboard del portal (`[aura_student_portal]`)**:

```
┌─────────────────────────────────────────────────────┐
│  👋 Hola, {nombre_completo}                          │
│  Perfil: Estudiante · Estado: Activo                 │
├──────────────┬───────────────┬──────────────────────┤
│ 📚 Mis Cursos│ 💰 Mis Pagos  │ 🏅 Mis Certificados   │
├──────────────┴───────────────┴──────────────────────┤
│ [Pestañas / Secciones]                               │
│                                                      │
│ Pestaña: Mis Cursos (agrupados por Área)             │
│   • Área: [Nombre del Área]                          │
│     - Nombre del curso, fechas, instructor           │
│     - Estado de inscripción                          │
│     - Progreso de pagos (barra visual)               │
│                                                      │
│ Pestaña: Mis Pagos                                   │
│   • Tabla de cuotas: fecha, monto, estado, comprobante │
│   • Saldo pendiente destacado                        │
│   • Botón "Descargar recibo" por cada pago           │
│                                                      │
│ Pestaña: Mis Certificados / Logros por Área          │
│   • Por cada Área en la que está inscrito:           │
│     - Nombre del Área / Programa                     │
│     - Cursos completados en esa área (con fecha)     │
│     - Certificados/diplomas emitidos (botón PDF)     │
│     - Botón "Ver QR de verificación"                 │
│     - Cursos en progreso (sin certificado aún)       │
│                                                      │
│ Pestaña: 📋 Mis Formularios (solo si Módulo Forms activo)
│   • Lista de encuestas/formularios enviados por admin│
│   • Pendientes: botón "Completar"                    │
│   • Completados: check verde + fecha                 │
└─────────────────────────────────────────────────────┘
```

**Formulario público `[aura_enrollment_form]`**:

Campos siempre presentes (datos de usuario que al aprobar crean el WP user):
- Nombre * (first_name)
- Apellido * (last_name)
- Email * (email — será el username/login)
- Teléfono (phone)
- Número de cédula/pasaporte (id_number)
- Contraseña * (o se genera automáticamente al aprobar — recomendado dejar al sistema)

Campos opcionales por tipo (configurables con checkboxes en `⚙️ Configuración → Módulo Estudiantes`):
- Fecha de nacimiento
- Género
- Dirección / Ciudad / País
- Foto de perfil

Campos de solicitud (siempre presentes para `type=student`):
- ¿Por qué quieres ingresar al programa? * (motivation)
- ¿Quién te apoya o recomienda? (supported_by)
- ¿Qué talento o habilidad tienes? (talent)
- Experiencia previa relevante (experience)
- Información adicional (extra_info)

**Selector de Área(s) de Interés** (presente en todos los tipos):
- "¿En qué área(s) o programa(s) te interesa participar?" (multi-select o checkboxes)
- Cargado desde `wp_aura_areas WHERE type='program' AND status='active'`
- Almacenado en `aura_students.preferred_areas` (JSON array de IDs)
- Si solo hay un área activa, puede preseleccionarse automáticamente

Selector de tipo de formulario (si se usa `[aura_enrollment_form]` sin parámetro `type`):
- Selecciona tu rol: ○ Estudiante ○ Voluntario ○ Instructor ○ Participante

**Seguridad del portal**:
- Verificar `is_user_logged_in()` y `current_user_can('aura_students_view_own')` en todos los shortcodes del portal
- Nonce en todos los formularios AJAX del frontend
- Rate limiting: máximo 3 envíos del formulario de inscripción por IP en 24h (usar `transients`)
- Sanitizar y validar todos los campos de entrada antes de insertar en BD

---

### FASE 9 — Notificaciones

**Archivos**: `class-students-notifications.php`

**Principio**: Este módulo NO gestiona configuración de email/WhatsApp. Todo se delega a `Aura_Notifications` (global, en `modules/common/class-notifications.php`).

**Notificaciones que se envían**:

| Evento | Destinatarios | Canal |
|--------|--------------|-------|
| Nueva solicitud recibida | Admins con `aura_students_approve` | Email |
| Solicitud aprobada + credenciales | Estudiante | Email |
| Solicitud rechazada | Estudiante | Email |
| Inscripción a curso confirmada | Estudiante | Email + WhatsApp |
| Cuota próxima a vencer (3 días antes) | Estudiante | Email + WhatsApp |
| Cuota vencida sin pago | Estudiante + Admin | Email |
| Pago registrado (confirmación) | Estudiante | Email |
| Graduación confirmada | Estudiante | Email + WhatsApp |

**Implementación**:
```php
class Aura_Students_Notifications {

    public static function send_approval_email( int $student_id, string $password ): void {
        $student = /* obtener de BD */;
        $body = /* template HTML con credenciales */;
        Aura_Notifications::send_email(
            $student->email,
            __('[CEM] Tu solicitud ha sido aprobada', 'aura-suite'),
            $body,
            ['module' => 'students', 'context' => 'approval']
        );
    }

    public static function send_payment_reminder( int $enrollment_id, int $installment_num ): void {
        /* obtener student + enrollment + installment */
        Aura_Notifications::send_whatsapp(
            $student->phone_country . $student->phone,
            "Hola {$student->first_name}, recuerda que tu cuota #{$installment_num} de {$course->name} vence el {$due_date}."
        );
    }

    // ... otros métodos
}
```

**WP Cron** para recordatorios automáticos:
```php
// En class-students-setup.php::init()
if ( ! wp_next_scheduled('aura_students_daily_reminders') ) {
    wp_schedule_event( time(), 'daily', 'aura_students_daily_reminders' );
}
add_action( 'aura_students_daily_reminders', [__CLASS__, 'process_daily_reminders'] );

// process_daily_reminders():
// 1. Buscar cuotas con due_date = hoy + 3 dias y status = 'pending' → send reminder
// 2. Buscar cuotas con due_date < hoy y status = 'pending' → cambiar a 'overdue' + notificar
// 3. Actualizar payment_status en enrollments afectados
```

---

### FASE 10 — Reportes

**Archivos**: `class-students-reports.php`, `templates/students/reports.php`

**Reportes disponibles**:

| Reporte | Descripción | Formato |
|---------|-------------|---------|
| Lista Completa de Estudiantes | Con filtros: estado, tipo, curso, área | Excel / PDF |
| Estado de Pagos por Curso | Cuánto cobrado vs pendiente por curso | Excel / PDF |
| **Inscritos por Área/Programa** | Cantidad de estudiantes activos por cada `wp_aura_areas` | Excel / PDF |
| **Ingresos por Área/Programa** | Total cobrado, pendiente y proyectado agrupado por área | Excel |
| Morosos | Estudiantes con cuotas vencidas | Excel / PDF |
| Proyección de Ingresos | Cuotas futuras esperadas por mes | Excel |
| Becas Otorgadas | Resumen de becas por tipo y monto | Excel |
| Graduados por Período | Listado con fecha, curso y área | Excel / PDF |

**Integración con PhpSpreadsheet** (ya en `vendor/`):
```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
```

**Integración con mPDF** (ya en `vendor/`):
```php
use Mpdf\Mpdf;
$mpdf = new Mpdf(['format' => 'A4-L']);
$mpdf->WriteHTML($html);
$mpdf->Output('reporte.pdf', 'D'); // D = force download
```

---

### FASE 11 — Configuración del Módulo

**Template**: `templates/students/settings.php`

**Opciones** (guardadas con `update_option('aura_students_settings', $settings_array)`):

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `auto_generate_password` | bool | `true` | Generar contraseña automática al aprobar |
| `send_credentials_email` | bool | `true` | Enviar email con credenciales al aprobar |
| `reminder_days_before` | int | `3` | Días antes del vencimiento para recordatorio |
| `overdue_alert_enabled` | bool | `true` | Enviar alerta cuando cuota vence sin pago |
| `finance_integration_enabled` | bool | `true` | Crear transacción en Finanzas al registrar pago |
| `portal_page_id` | int | `0` | ID de la página WordPress con `[aura_student_portal]` |
| `enrollment_page_id` | int | `0` | ID de la página pública de inscripción (módulo Formularios) |
| `default_currency` | string | `'USD'` | Moneda por defecto para cursos |
| `student_code_prefix` | string | `'CEM-EST'` | Prefijo del código de estudiante |

**Pestañas de configuración** (3 tabs):
1. **General**: Prefijo de código, moneda, página del portal, página de inscripción
2. **Pagos y Finanzas**: Integración con finanzas, moneda
3. **Notificaciones**: Días de anticipación, qué eventos notifican (checkboxes)

> **Nota**: La pestaña "Formulario público" fue eliminada. Los formularios de inscripción se gestionan exclusivamente desde el módulo Formularios.

---

## 7. Integraciones con Otros Módulos

### 7.1 Con Módulo Finanzas — Sistema de Categorías Compartidas (v1.2+)

**NUEVO en v1.2 de Finanzas**: Categorías con soporte a múltiples módulos integradores.

Al registrar un pago estudiantil:

**Flujo de configuración:**
1. Admin en **Finanzas → Categorías** crea "Cuotas de Inscripción" con type `income`
2. Marca ☑ **Estudiantes** en "Integración con Módulos"
3. Guarda → `integration_modules: ["students"]`
4. En **Estudiantes → Configuración**, selecciona "Cuotas de Inscripción" como categoría por defecto
5. Activa "Crear transacciones automáticas"

**Transacción generada:**
```php
// Tabla: wp_aura_finance_transactions
[
    'amount'         => $pago->amount,
    'type'           => 'income',
    'category_id'    => $categoria->id,  // ← Vinculada a categoría de Estudiantes
    'description'    => "Pago {$cuota_num} - {$student->first_name} {$student->last_name} / {$course->name}",
    'date'           => $pago->payment_date,
    'status'         => 'completed',
    'source_module'  => 'students',
    'source_student_id' => $student->id,
    'category_id'    => $course->finance_cat_id,   // Categoría "Inscripciones" configurada por admin
    'status'         => 'approved',
    'related_module' => 'students',
    'related_id'     => $pago->id,
    'related_action' => 'enrollment',
    'created_by'     => get_current_user_id(),
]
```

### 7.2 Con Módulo Certificados y Diplomas

Al graduarse un estudiante, se lanza el action hook:
```php
do_action('aura_student_graduated', $student_id, $enrollment_id, $course_id);
```

El Módulo de Certificados escucha este hook para:
- Habilitar el botón "Emitir Certificado" en la ficha del estudiante
- (Opcionalmente) emitir el certificado automáticamente si hay plantilla por defecto

### 7.3 Con Módulo Áreas

#### Modelo conceptual

Las **Áreas** (`wp_aura_areas`) con `type = 'program'` son los **programas académicos o talleres** que ofrece la organización. Los **Cursos** (`aura_student_courses`) son *instancias* de esos programas (con fechas, costo y cupos específicos). Esta distinción es clave:

```
wp_aura_areas (type='program')  ← define QUÉ se enseña
        │
        │  area_id FK
        ▼
aura_student_courses             ← define CUÁNDO / cuánto cuesta / cuántos cupos
        │
        │  course_id FK
        ▼
aura_student_enrollments         ← relación estudiante ↔ curso (instancia)
        │
        │  enrollment_id FK
        ▼
aura_student_payments +          ← pagos del estudiante por ese curso
aura_certificate_issued          ← diplomas/certificados emitidos por ese curso/área
```

#### Reglas de negocio

1. **Al crear un Área** con `type='program'`, queda disponible para ser seleccionada en:
   - El selector de área de los formularios públicos (`[aura_enrollment_form]`)
   - El campo `area_id` al crear/editar un Curso
   - Los reportes y filtros del módulo estudiantes

2. **Al crear un Curso**, el campo `area_id` vincula ese curso a un área-programa. Un área puede tener múltiples cursos en distintas fechas o ediciones.

3. **Al registrar un estudiante** (formulario público o manual):
   - Se muestran las Áreas activas con `type='program'` para que el postulante seleccione su(s) área(s) de interés
   - La selección se guarda en `aura_students.preferred_areas` (JSON array de IDs)

4. **Al aprobar e inscribir un estudiante**:
   - El admin selecciona el Curso específico (que ya tiene un `area_id` asignado)
   - Se crea el registro en `aura_student_enrollments`
   - Un estudiante puede estar inscrito en **múltiples cursos** de **múltiples áreas** simultáneamente (no hay restricción `UNIQUE` en student_id dentro de enrollments)

5. **Agrupación por Área en el portal**:
   - En el portal frontend `[aura_student_portal]`, los logros, pagos y certificados se presentan **agrupados por Área**
   - Query: `JOIN aura_student_courses ON enrollments.course_id = courses.id JOIN wp_aura_areas ON courses.area_id = areas.id`

6. **Visibilidad restringida**:
   - Usuarios con `aura_areas_view_own` solo ven estudiantes inscritos en cursos asociados a sus propias áreas
   - Responsables de un Área pueden ver listados de inscritos sin acceder a datos financieros (requiere `aura_students_view_all`)

#### Consultas SQL de referencia

```sql
-- Estudiantes inscritos en un área específica (todos sus cursos)
SELECT DISTINCT s.id, s.first_name, s.last_name, s.email, s.status,
       e.status AS enrollment_status, c.name AS course_name,
       a.name AS area_name
FROM   {prefix}aura_students s
JOIN   {prefix}aura_student_enrollments e ON e.student_id = s.id
JOIN   {prefix}aura_student_courses c     ON c.id = e.course_id
JOIN   {prefix}aura_areas a               ON a.id = c.area_id
WHERE  a.id = %d
  AND  s.deleted_at IS NULL;

-- Resumen de inscritos y recaudación por Área
SELECT a.id, a.name AS area_name,
       COUNT(DISTINCT e.student_id) AS total_students,
       SUM(e.net_cost) AS total_expected,
       SUM(e.total_paid) AS total_collected,
       SUM(e.balance_due) AS total_pending
FROM   {prefix}aura_areas a
JOIN   {prefix}aura_student_courses c  ON c.area_id = a.id
JOIN   {prefix}aura_student_enrollments e ON e.course_id = c.id
WHERE  a.type = 'program'
  AND  a.status = 'active'
GROUP BY a.id, a.name;
```

#### Endpoint AJAX reutilizable

El endpoint `aura_areas_dropdown` del módulo Áreas ya existe y retorna la lista de áreas activas. En el módulo Estudiantes se consume como `aura_students_get_areas` (re-implementado en `class-students-courses.php` para desacoplar dependencias):

```php
// En class-students-courses.php
public static function ajax_get_areas(): void {
    check_ajax_referer('aura_students_nonce', '_nonce');
    global $wpdb;
    $areas = $wpdb->get_results(
        "SELECT id, name, type FROM {$wpdb->prefix}aura_areas
         WHERE status = 'active' AND type = 'program'
         ORDER BY name ASC"
    );
    wp_send_json_success($areas);
}
```

### 7.4 Con Módulo Notificaciones Globales

- **NO** configurar SMTP ni WhatsApp dentro de este módulo
- Usar siempre `Aura_Notifications::send_email()` y `Aura_Notifications::send_whatsapp()`
- La configuración SMTP/WhatsApp/Google Calendar vive en `⚙️ Configuración → Notificaciones`

---

### 7.5 Con Módulo Formularios — Formulario de Inscripción Personalizado

> ✅ **Integración implementada.** El Módulo Formularios ya está activo y la integración funciona en producción desde v1.7.7.

#### Descripción general

El Módulo de Formularios permite crear formularios de tipo **Inscripción** (`type = 'enrollment'`) que, al ser enviados públicamente, crean o actualizan automáticamente un registro en `aura_students` y un enrollment pendiente en `aura_student_enrollments`. Esta sincronización la gestiona `Aura_Forms_Enrollment` (clase puente en `modules/forms/class-forms-enrollment.php`).

#### Campos mapeados — Datos Personales

Los campos del formulario que tengan un `mapping_key` se sincronizan automáticamente con la tabla `aura_students`:

| `mapping_key` | Campo del estudiante | Tipo de campo recomendado |
|---|---|---|
| `first_name` | `aura_students.first_name` | `text` · Requerido |
| `last_name` | `aura_students.last_name` | `text` · Requerido |
| `email` | `aura_students.email` | `email` · Requerido (llave de búsqueda) |
| `phone` | `aura_students.phone` | `tel` |
| `birthdate` | `aura_students.birthdate` | `birthdate` |
| `gender` | `aura_students.gender` | `radio` (Masculino/Femenino/No binario/Prefiero no decirlo) |
| `city` | `aura_students.city` | `text` |
| `country` | `aura_students.country` | `text` |
| `id_number` | `aura_students.id_number` | `text` |
| `address` | `aura_students.address` | `text` |
| `motivation` | `aura_students.motivation` | `textarea` |

#### Campos mapeados — Postulación

| `mapping_key` | Destino | Nota |
|---|---|---|
| `course_id` | `aura_student_enrollments.course_id` | Requerido para que se cree el enrollment |
| `area_id` | Filtro para cargar `course_id` | No se guarda directamente; usa el area_id del curso |
| `notes` | `aura_student_enrollments.notes` | Texto libre |

> **Campos auto-gestionados** (no incluir en el formulario): `enrollment_date` (se pone en `current_time('Y-m-d')` automáticamente), `status` (siempre inicia como `pending`).

#### Flujo de sincronización al enviar el formulario

```
Usuario llena formulario de inscripción en frontend
    ↓
Aura_Forms_Submissions::ajax_submit_form() → guarda submission en aura_form_submissions
    ↓
Hook: do_action('aura_form_submission_saved', $submission_id, $form_id)
    ↓
Aura_Forms_Enrollment::maybe_create_enrollment():
    1. Verifica form.type === 'enrollment'
    2. Construye mapa field_uid → mapping_key desde aura_form_fields
    3. Extrae campos mapeados de data_json del submission
    4. Valida que exista email válido (OBLIGATORIO para continuar)
    5. find_or_create_student() → busca por email en aura_students;
       si no existe, crea nuevo con status='applicant', created_by=0 (origen: formulario)
       ⚠ id_type y gender se normalizan al ENUM de la tabla (vacío → NULL)
    6. create_pending_enrollment() → inserta en aura_student_enrollments con status='pending'
       (solo si course_id > 0, desde campo mapeado o desde form.course_id)
    7. Vincula submission.enrollment_id = enrollment.id
    ↓
Admin ve la inscripción en: Formularios → Postulantes (Inscripciones)
    URL: admin.php?page=aura-forms-enrollments
    (el estudiante también aparece en: Estudiantes → Listado de Estudiantes, filtro "⏳ Postulante")
    ↓
Admin aprueba → student.status='approved', enrollment.status='active', crea WP user si no existe
```

#### Dónde aparece el estudiante en el módulo de Estudiantes

| Estado | Dónde verlo | Filtro |
|--------|-------------|--------|
| Recién enviado el formulario | **Formularios → Postulantes** (`aura-forms-enrollments`) | Enrollment status = `pending` |
| Recién enviado el formulario | **Estudiantes → Listado** (`aura-students-list`) | Status = `⏳ Postulante` o `Todos los estados` (default) |
| Después de aprobar | **Formularios → Postulantes** | Enrollment status = `active` |
| Después de aprobar | **Estudiantes → Listado** | Status = `✅ Aprobado` |

> **Importante**: El campo `email` DEBE tener `mapping_key = 'email'` para que la integración funcione. Sin email válido, `maybe_create_enrollment()` retorna sin crear nada (falla silenciosa).

#### Valores ENUM aceptados por `id_type` y `gender` del mapping

| Mapping key | Valores aceptados en el form | Guardado en DB |
|-------------|------------------------------|----------------|
| `id_type` | `cedula` | `cedula` |
| `id_type` | `passport`, `pasaporte` | `pasaporte` |
| `id_type` | `dni` | `dni` |
| `id_type` | `otro`, `other` | `otro` |
| `id_type` | `''` o cualquier otro | `NULL` |
| `gender` | `m`, `masculino`, `male` | `M` |
| `gender` | `f`, `femenino`, `female` | `F` |
| `gender` | `otro`, `other`, `o` | `otro` |
| `gender` | `prefiero_no_decir`, `p` | `prefiero_no_decir` |
| `gender` | `''` o cualquier otro | `NULL` |

#### Formulario de inscripción predeterminado (botón "Insertar campos predeterminados")

Al crear un formulario de tipo **Inscripción** en el builder, aparece un banner informativo azul con el botón **"Insertar campos predeterminados"**. Al pulsarlo, el sistema inserta automáticamente el siguiente formulario base (omitiendo campos cuyo `mapping_key` ya exista):

| Orden | Campo | Tipo | Mapping Key | Requerido |
|---|---|---|---|---|
| — | Datos Personales | `section_title` | — | — |
| 1 | Nombre(s) | `text` | `first_name` | ✅ |
| 2 | Apellido(s) | `text` | `last_name` | ✅ |
| 3 | Correo electrónico | `email` | `email` | ✅ |
| 4 | Teléfono | `tel` | `phone` | — |
| 5 | Fecha de Nacimiento | `birthdate` | `birthdate` | — |
| 6 | Género | `radio` | `gender` | — |
| 7 | Ciudad | `text` | `city` | — |
| 8 | País | `text` | `country` | — |
| — | Información de Postulación | `section_title` | — | — |
| 9 | ¿Por qué deseas postularte? | `textarea` | `motivation` | — |

El admin puede **personalizar** el formulario agregando, editando u eliminando campos adicionales. Los campos extra (sin `mapping_key`) se guardan igualmente en `data_json` del submission y son visibles en el panel de "Inscripciones Pendientes".

#### Revisión de inscripciones desde el módulo de Formularios

- Menú: **Formularios → Inscripciones Pendientes** (`templates/forms/enrollments-pending.php`)
- Muestra los submissions de formularios `type = enrollment` con `enrollment_id` vinculado
- Acciones por fila: Aprobar, Rechazar (con motivo), Marcar retirado
- Al aprobar → estudiante se activa en el Módulo Estudiantes + se crea WP user (si no existe)

#### Hooks disponibles

```php
// Al guardar una inscripción (submission + enrollment creados)
do_action( 'aura_form_enrollment_submitted', $submission_id, $form_id, $enrollment_id );

// Al aprobar una inscripción
do_action( 'aura_form_enrollment_approved', $submission_id, $enrollment_id, $student_id );

// Al rechazar
do_action( 'aura_form_enrollment_rejected', $submission_id, $enrollment_id, $student_id, $reason );
```

#### Encuestas post-inscripción (asignación de formularios a estudiantes)

Para enviar encuestas a estudiantes **ya inscritos**, utilizar el tipo de formulario `survey` y la clase `Aura_Forms_Assignments`. Funciones:
- `aura_forms_assign` — Asignar a estudiantes seleccionados
- `aura_forms_revoke` — Revocar asignación
- `aura_student_portal_pending_forms` — Filtro para inyectar formularios pendientes en el portal frontend

```php
// Filtro para mostrar formularios pendientes en el portal del estudiante
$pending_forms = apply_filters('aura_student_portal_pending_forms', [], $student_id);
```

#### Bugs corregidos en la integración Formularios ↔ Estudiantes (Abril 2026)

| # | Bug | Archivo(s) | Causa raíz | Fix |
|---|-----|-----------|------------|-----|
| 1 | `deleted_at IS NULL` en `aura_form_fields` | `class-forms-enrollment.php` L59, L116 | La tabla no tiene columna `deleted_at` → query falla silenciosamente → mapping keys vacíos → email no extraído → estudiante nunca se crea | Eliminar `AND deleted_at IS NULL` de queries contra `aura_form_fields` |
| 2 | `$wpdb->prepare(null)` → `''` | `class-forms-enrollment.php` `find_or_create_student()` | WP 6.9.4 convierte `null` en `''` con `%s` → viola ENUM constraint → INSERT falla | Omitir columns ENUM/date del INSERT cuando su valor es null |
| 3 | Alias `hombre`/`mujer` faltantes | `class-forms-enrollment.php` gender_map | Formularios con opciones en español no mapeaban al ENUM | Agregar `'hombre' => 'M'`, `'mujer' => 'F'` |
| 4 | `deleted_at IS NULL` en `aura_form_fields` (builder) | `class-forms-builder.php` L648, L655 | Misma causa que #1 → campos de enrollment defaults fallan | Eliminar `AND deleted_at IS NULL` |
| 5 | `form_type` inexistente (debe ser `type`) | `assignments.php` L24, L81; `class-forms-assignments.php` L210, L219 | Columna se llama `type`, no `form_type` → queries retornan NULL | Reemplazar `form_type` por `type` |
| 6 | `deleted_at` en `aura_student_courses` | `assignments.php` L32 | Tabla no tiene `deleted_at`, usa `status` | Cambiar a `WHERE status = 'active'` |
| 7 | `ajax_list_applicants()` prepare() vacío | `class-students-enrollments.php` | `$wpdb->prepare()` con 0 args dinámicos + `%d` → null en WP 6.3+ | Separar queries con/sin params dinámicos |
| 8 | Falta `deleted_at IS NULL` en applicants | `class-students-enrollments.php` L577 | Podía mostrar estudiantes soft-deleted | Agregar `AND deleted_at IS NULL` |

---

## 8. Seguridad

### 8.1 Validación y Sanitización

- Todos los campos de entrada pasar por `sanitize_text_field()`, `sanitize_email()`, `absint()`, según corresponda
- Campos de texto largo: `wp_kses_post()` o `sanitize_textarea_field()`
- Verificar nonce en **todos** los endpoints AJAX: `check_ajax_referer('aura_students_nonce', '_nonce')`
- Verificar capability antes de ejecutar cualquier operación

### 8.2 Control de Acceso por Capability

```php
// Patrón estándar en todos los métodos AJAX:
public static function ajax_save_student(): void {
    check_ajax_referer('aura_students_nonce', '_nonce');
    
    $is_edit = isset($_POST['student_id']) && intval($_POST['student_id']) > 0;
    $cap     = $is_edit ? 'aura_students_edit' : 'aura_students_create';
    
    if ( ! current_user_can($cap) && ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => __('Sin permisos.', 'aura-suite')]);
    }
    // ...
}
```

### 8.3 Datos Personales (GDPR / Privacidad)

- Los campos `id_number`, `birthdate`, `phone` son datos sensibles
- Acceso solo para usuarios con `aura_students_view_all` o `aura_students_edit`
- Los propios datos del estudiante solo visibles con `aura_students_view_own`
- Las contraseñas NUNCA se almacenan en texto plano (usar siempre `wp_insert_user()` / `wp_set_password()`)

### 8.4 Formulario Público

- Rate limiting por IP: máximo 3 envíos en 24h usando `set_transient("aura_enroll_ip_{$ip}", $count, DAY_IN_SECONDS)`
- Verificar nonce también en formulario frontend: `wp_create_nonce('aura_enrollment_public')`
- Validar email no duplicado antes de insertar

---

## 9. UX/UI — Lineamientos de Diseño

### Referencia Visual

Seguir el mismo patrón visual del Módulo Inventario:
- Cards para KPIs con icono, número grande, tendencia
- Tablas con filas compactas (hover effect, selección)
- Modales para crear/editar (no páginas separadas para formularios simples)
- Color del módulo: `#8b5cf6` (violeta académico)

### Colores de Estado (consistentes en todo el módulo)

| Estado | Color de fondo | Color texto | Badge |
|--------|---------------|-------------|-------|
| `applicant` | `#f3f4f6` | `#6b7280` | ⏳ Postulante |
| `approved` | `#dbeafe` | `#1d4ed8` | ✅ Aprobado |
| `active` | `#dcfce7` | `#166534` | 🟢 Activo |
| `graduated` | `#fef3c7` | `#92400e` | 🏅 Graduado |
| `withdrawn` | `#fed7aa` | `#9a3412` | ↩ Retirado |
| `rejected` | `#fee2e2` | `#991b1b` | ❌ Rechazado |

| Estado de Pago | Color |
|---------------|-------|
| `unpaid` | 🔴 Rojo |
| `overdue` | 🔴 Rojo pulsante |
| `partial` | 🟡 Amarillo |
| `paid` | 🟢 Verde |

---

## 10. Variables de Contexto para Notificaciones

Las notificaciones de este módulo usan los siguientes placeholders reemplazados en el template:

| Campo `context` | Valor recomendado |
|-----------------|-------------------|
| `module` | `'students'` |
| `app_name` | `get_bloginfo('name')` + `' — Gestión Académica'` (ej: `'CEM — Gestión Académica'`) |
| `student_name` | `$student->first_name . ' ' . $student->last_name` |
| `course_name` | `$course->name` |
| `due_date` | `date_i18n('d/m/Y', strtotime($installment->due_date))` |
| `amount` | `number_format($installment->expected_amount, 2)` |
| `portal_url` | `get_permalink($settings['portal_page_id'])` |

---

## 11. Roadmap de Fases — Resumen

| Fase | Descripción | Prioridad |
|------|-------------|-----------|
| **FASE 1** | Setup: BD, menús, capabilities | ⚡ Crítica |
| **FASE 2** | CRUD de Cursos y Programas | ⚡ Crítica |
| **FASE 3** | CRUD de Estudiantes | ⚡ Crítica |
| **FASE 4** | Flujo de Aprobación e Inscripción | ⚡ Crítica |
| **FASE 5** | Gestión de Pagos + Integración Finanzas | ⚡ Crítica |
| **FASE 6** | Dashboard Admin + Paz y Salvo | 🔹 Alta |
| **FASE 7** | Becas (internas y externas) | 🔹 Alta |
| **FASE 8** | Portal Frontend del Estudiante | 🔹 Alta |
| **FASE 9** | Notificaciones y WP Cron | 🔹 Alta |
| **FASE 10** | Reportes (Excel / PDF) | 🔸 Media |
| **FASE 11** | Configuración del módulo | 🔸 Media |

---

## 12. Checklist de Implementación

### Fase 1 — Setup
- [ ] Crear `modules/students/class-students-setup.php` con 5 tablas SQL
- [ ] Crear `modules/students/class-students-admin.php` con menús
- [ ] Registrar includes y `::init()` en `aura-business-suite.php`
- [ ] Agregar capabilities al `Aura_Roles_Manager`
- [ ] Crear `assets/css/students-admin.css` y `assets/js/students-admin.js` vacíos
- [ ] Verificar que las tablas se crean al guardar en admin

### Fase 2 — Cursos
- [ ] `class-students-courses.php` con 4 hooks AJAX
- [ ] Template `course-list.php` con tabla y modal de detalle
- [ ] Template `course-form.php` con validación JS
- [ ] Selector de categoría financiera funcional

### Fase 3 — Estudiantes
- [ ] `class-students-crud.php` con 4 hooks AJAX
- [ ] Template `list.php` con filtros (incluido filtro por área) y colores de estado
- [ ] Template `student-form.php` con 5 secciones (incluye Sección 3: Áreas de Interés)
- [ ] Multi-select de `preferred_areas` cargado de `wp_aura_areas WHERE type='program'`
- [ ] Búsqueda en tiempo real funcional

### Fase 4 — Inscripciones
- [ ] `class-students-enrollments.php` con 5 hooks AJAX
- [ ] Template `enrollments.php` con lista de postulantes pendientes
- [ ] Modal de aprobación con calculadora de costo en tiempo real
- [ ] WP user creado al aprobar + capabilities asignadas
- [ ] Email de bienvenida con credenciales enviado

### Fase 5 — Pagos
- [ ] `class-students-payments.php` con 3 hooks AJAX
- [ ] Template `payments.php` con tabla expandible
- [ ] Integración con `aura_finance_transactions` funcional
- [ ] `aura_student_installment_schedule` actualizada al pagar

### Fase 6 — Dashboard
- [ ] 6 KPIs calculados correctamente
- [ ] 3 gráficos ApexCharts funcionando
- [ ] Template `paz-salvo.php` con exportar Excel

### Fase 7 — Becas
- [ ] AJAX assign/list funcionando
- [ ] Recalculo de `net_cost` y `balance_due` correcto
- [ ] Regeneración de schedule correcto al cambiar beca

### Fase 8 — Frontend
- [ ] Shortcode `[aura_student_portal]` funcional y seguro
- [ ] Shortcode `[aura_enrollment_form]` con rate limiting
- [ ] Selector de `preferred_areas` en el formulario público (multi-select de áreas tipo 'program')
- [ ] Portal con pestañas (cursos agrupados por área, pagos, certificados por área)
- [ ] Pestaña "📋 Mis Formularios" condicional (solo si `class_exists('Aura_Forms')`)
- [ ] Hook `apply_filters('aura_student_portal_pending_forms', [], $student_id)` expuesto
- [ ] Login frontend que no redirige al `wp-login.php`

### Fase 9 — Notificaciones
- [ ] WP Cron `aura_students_daily_reminders` registrado
- [ ] Recordatorio 3 días antes del vencimiento funcional
- [ ] Cuotas marcadas como `overdue` automáticamente
- [ ] Todos los emails usan `Aura_Notifications::send_email()`

### Fase 10 — Reportes
- [ ] 8 tipos de reporte generan Excel correcto (incluye Inscritos por Área e Ingresos por Área)
- [ ] 4 tipos de reporte generan PDF correcto
- [ ] Filtros aplicados en los reportes (incluido filtro por área)

### Fase 11 — Configuración
- [ ] Settings guardados en `wp_options` como array
- [ ] 4 pestañas de configuración funcionales
- [ ] `portal_page_id` y `enrollment_page_id` configurables y usados en shortcodes




---

## Diagnóstico Real del Módulo Estudiantes

**PHP (backend):** ✅ 12/12 clases implementadas  
**Templates:** ✅ 15/15 archivos existen  
**CSS:** ✅ students-admin.css y students-frontend.css existen  
**Capabilities:** ✅ Registradas en class-roles-manager.php

**El problema crítico está en JavaScript:**

| Archivo | Estado | Contenido actual |
|---------|--------|-----------------|
| students-admin.js | ⚠️ Incompleto | Solo 115 líneas: framework base + refresh de KPIs del dashboard |
| `assets/js/students-frontend.js` | ❌ No existe | El archivo no existe en absoluto |

Sin estos JS, el módulo es una carcasa: los templates renderizan HTML pero ninguna acción funciona.

---

## Plan de Implementación — Capa JavaScript

### Bloque 1 — students-admin.js (Páginas del admin)

Cada sección de admin necesita su bloque de JS dentro del mismo archivo (siguiendo el patrón de `auraStudents.page` para saber en qué página se está):

**1.1 — Dashboard (ya existe parcialmente)**
- Refrescar KPIs ✅
- Cargar gráficos ApexCharts (barras mensuales, donut de perfiles, línea de inscripciones)
- Cargar tabla "Últimas Actividades" vía `aura_students_recent_activity`

**1.2 — Cursos (`aura-students-courses`)**
- Cargar tabla de cursos → `aura_students_list_courses`
- Modal crear/editar: poblar con `aura_students_get_course`, guardar con `aura_students_save_course`
- Cargar dropdowns dinámicos (áreas via `aura_students_get_areas`, instructores, categorías finanzas)
- Soft-delete → `aura_students_delete_course`

**1.3 — Listado de Estudiantes (`aura-students-list`)**
- Tabla paginada → `aura_students_list`
- Filtros: búsqueda, estado, tipo de perfil, curso
- Modal "Ver detalle" → `aura_students_get`
- Acciones: editar (redirige a `aura-students-new?id=X`), eliminar

**1.4 — Formulario Estudiante (`aura-students-new`)**
- Tabs (datos personales, postulación, preferencias, notas)
- Multi-select de áreas → `aura_students_get_programs`
- Upload foto (usando media uploader de WP o input file con preview)
- Submit → `aura_students_save` → redirect al listado con mensaje

**1.5 — Inscripciones (`aura-students-enrollments`)**
- Cargar postulantes → `aura_students_list_applicants`
- Cargar inscripciones activas → `aura_students_list_enrollments`
- Modal Aprobar → `aura_students_approve`
- Modal Rechazar → `aura_students_reject`
- Modal Inscribir a curso → `aura_students_enroll` (dropdown cursos por área → `aura_students_get_courses_by_area`)
- Modal Editar inscripción → `aura_students_update_enrollment`
- Botón Graduar → `aura_students_graduate` + confirmación

**1.6 — Pagos (`aura-students-payments`)**
- Tabla de inscripciones con estado de pago → `aura_students_list_payments`
- Expandir fila → cuotas del schedule → `aura_students_get_installments`
- Modal Registrar pago → `aura_students_register_payment`
- KPI minibar → `aura_students_payment_summary`
- Eliminar pago → `aura_students_delete_payment`

**1.7 — Becas (`aura-students-scholarships`)**
- Tabla de becas activas → `aura_students_list_scholarships`
- KPIs estadísticos → `aura_students_scholarship_stats`
- Modal Asignar/editar beca → `aura_students_assign_scholarship` (botones rápido 25/50/75/100%)
- Quitar beca → `aura_students_remove_scholarship`

**1.8 — Paz y Salvo (`aura-students-paz-salvo`)**
- Tabla → `aura_students_paz_salvo_list`
- Filtros: "Solo morosos" / "Solo al día"
- Botón Enviar recordatorio → `aura_students_send_reminder`
- Exportar morosos → `aura_students_export_debtors`

**1.9 — Reportes (`aura-students-reports`)**
- Cambio de tipo de reporte muestra/oculta filtros dinámicamente
- Vista previa en tabla → `aura_students_generate_report`
- Exportar Excel → `aura_students_export_excel`
- Exportar PDF → `aura_students_export_pdf`

**1.10 — Configuración (`aura-students-settings`)**
- Tabs (General, Formulario, Pagos, Notificaciones)
- Guardar por tab → `aura_students_save_settings`
- Selectores de página WP (portal, inscripción)

---

### Bloque 2 — `students-frontend.js` (Portal público) — **Crear desde cero**

**2.1 — Login (`[aura_student_login]`)**
- Submit del form → `aura_students_ajax_login` → redirect

**2.2 — Portal del estudiante (`[aura_student_portal]`)**
- Tabs (Mis Cursos / Mis Pagos / Mis Certificados / Mis Formularios)
- Carga lazy por pestaña:
  - Mis Cursos → `aura_student_portal_my_courses`
  - Mis Pagos → `aura_student_portal_my_payments` → tabla + barra de saldo
  - Mis Certificados → `aura_student_portal_my_certs` → listado + botón descarga PDF
- Botón "Descargar recibo" por pago
- Mostrar pestaña "Mis Formularios" solo si existen asignaciones (preparar para cuando exista el módulo Forms)

**2.3 — Formulario de inscripción (`[aura_enrollment_form]`)**
- Selector de tipo si no viene parámetro
- Selector multi de áreas de interés (con carga desde el JSON en el template)
- Validación de campos requeridos antes de enviar
- Rate limit visual (si `$rate_blocked === true`, deshabilitar sin exponer IP en cliente)
- Submit → `aura_students_submit_enrollment` → mostrar mensaje de éxito/error

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