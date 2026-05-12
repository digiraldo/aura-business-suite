# PRD — Módulo de Certificados y Diplomas
## Aura Business Suite · Plugin WordPress

> **Versión**: 1.0.0  
> **Módulo previo requerido**: Módulo Estudiantes (completado, todas las fases)  
> **Dependencia de BD ya existente**: `aura_students`, `aura_student_enrollments`, `aura_student_courses`  
> **Ruta del módulo**: `modules/certificates/`  
> **Ruta de plantillas**: `templates/certificates/`  
> **Ruta de assets**: `assets/js/certificates.js`, `assets/css/certificates.css`  
> **Color del módulo**: `#8b5cf6` (violeta, mismo que Estudiantes)  
> **Inspiración de referencia**: Tutor LMS Certificate Builder (canvas Fabric.js, drag & drop libre, variables dinámicas, QR de verificación)

---

## 1. Descripción General

El Módulo de Certificados y Diplomas permite:

1. **Crear plantillas de diseño** con un editor visual drag & drop (canvas libre Fabric.js)
2. **Emitir certificados personalizados en PDF** a estudiantes graduados/aprobados
3. **Verificar autenticidad** mediante folio único, UUID y código QR público
4. **Portal frontend del estudiante**: descarga de su propio certificado
5. **Emisión masiva** en background (WP Cron)
6. **Gestión de firmantes**: imagen de firma + nombre + cargo

### Límites del módulo (lo que NO hace)

- No gestiona cursos ni inscripciones (eso es Módulo Estudiantes)
- No configura email ni WhatsApp (se delega a `Aura_Notifications`)
- No genera reportes financieros (se delega a Módulo Finanzas)
- No reemplaza la sección "Mis Certificados" del portal de estudiantes (la renderiza, pero los datos los provee este módulo)

### Integración principal con Estudiantes

El hook `do_action('aura_student_graduated', $student_id, $enrollment_id)` ya existe y se dispara en `class-students-enrollments.php` cuando un admin gradúa a un estudiante (`ajax_graduate_student()`). Este módulo escucha ese hook para habilitar automáticamente el botón "Emitir Certificado" en el perfil del estudiante.

---

## 2. Arquitectura del Sistema

### 2.1 Árbol de archivos

```
modules/certificates/
├── class-certificates-setup.php          ← BD, capabilities, rwrules, cron
├── class-certificates-admin.php          ← Menús WP, enqueue assets, nonces
├── class-certificates-templates.php      ← CRUD plantillas (Fabric.js builder)
├── class-certificates-signers.php        ← CRUD firmantes y sus firmas PNG
├── class-certificates-issuer.php         ← Emisión individual y masiva, generación PDF
├── class-certificates-folio.php          ← Generador de folios y validación de unicidad
├── class-certificates-verify.php         ← Endpoint público de verificación (sin login)
├── class-certificates-frontend.php       ← Shortcodes del portal del estudiante
├── class-certificates-notifications.php  ← Wrapper sobre Aura_Notifications
├── class-certificates-reports.php        ← Reportes de emisiones (Excel + PDF)
└── class-certificates-settings.php       ← Configuración del módulo

templates/certificates/
├── dashboard.php                          ← KPIs + últimas emisiones
├── list.php                               ← Listado de certificados emitidos
├── templates-list.php                     ← Listado de plantillas de diseño
├── template-builder.php                   ← Editor Fabric.js (iframe o página entera)
├── signers.php                            ← Gestión de firmantes
├── issue-modal.php                        ← Modal de emisión individual (incluido en perfiles)
├── bulk-issue.php                         ← Interfaz de emisión masiva
├── verify-public.php                      ← Página pública de verificación
├── reports.php                            ← Reportes
├── settings.php                           ← Configuración
└── frontend/
    ├── my-certificates.php                ← Pestaña "Mis Certificados" en portal
    └── verify-widget.php                  ← Widget de QR para el portal

assets/js/
├── certificates.js                        ← Lógica admin general (emisión, modales)
├── certificate-builder.js                 ← Toda la lógica Fabric.js del editor
└── certificate-verify.js                  ← Lógica de la página de verificación pública

assets/css/
└── certificates.css                       ← Estilos del módulo
```

### 2.2 Mapa de clases

| Clase | Responsabilidad |
|-------|-----------------|
| `Aura_Certificates_Setup` | `dbDelta()`, registro de capabilities, rewrite rules, cron events |
| `Aura_Certificates_Admin` | `add_menu_page()`, `enqueue_scripts()`, callbacks de render |
| `Aura_Certificates_Templates` | CRUD de plantillas (crear, editar, eliminar, listar, set default) |
| `Aura_Certificates_Signers` | CRUD de firmantes (nombre, cargo, imagen de firma PNG) |
| `Aura_Certificates_Issuer` | Emisión individual, emisión masiva, generación PDF con mPDF |
| `Aura_Certificates_Folio` | `generate()`, `validate()`, `get_next_sequence()` |
| `Aura_Certificates_Verify` | Endpoint `?aura_verify={folio}`, shortcode `[aura_verificar_certificado]` |
| `Aura_Certificates_Frontend` | Shortcode `[aura_certificates_portal]`, datos para la pestaña del estudiante |
| `Aura_Certificates_Notifications` | `send_issued_email()`, `send_revoke_email()` usando `Aura_Notifications` |
| `Aura_Certificates_Reports` | Reportes de emisiones con PhpSpreadsheet y mPDF |
| `Aura_Certificates_Settings` | Opciones del módulo (prefijo de folio, páginas, requisito paz y salvo) |

### 2.3 Registro en `aura-business-suite.php`

En `load_dependencies()` añadir (después del bloque de Estudiantes):

```php
// Módulo Certificados
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-setup.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-admin.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-templates.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-signers.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-folio.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-issuer.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-verify.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-frontend.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-notifications.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-reports.php';
require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-settings.php';
```

En `init_modules()` añadir:

```php
Aura_Certificates_Setup::init();
Aura_Certificates_Admin::init();
Aura_Certificates_Templates::init();
Aura_Certificates_Signers::init();
Aura_Certificates_Issuer::init();
Aura_Certificates_Verify::init();
Aura_Certificates_Frontend::init();
Aura_Certificates_Reports::init();
Aura_Certificates_Settings::init();
```

---

## 3. Base de Datos

### 3.1 Versión y Migración

- **Constante**: `DB_VERSION = '1.0.0'` en `Aura_Certificates_Setup`
- **Opción WP**: `aura_certificates_db_version`
- **Migración**: `dbDelta()` igual que `Aura_Inventory_Setup`, `Aura_Students_Setup`
- Se ejecuta en `register_activation_hook` y al detectar `DB_VERSION` desactualizada

### 3.2 Tablas SQL

```sql
-- ──────────────────────────────────────────────────────────────────
-- 1. Plantillas de diseño
-- ──────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_certificate_templates (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    slug            VARCHAR(200) NOT NULL UNIQUE,
    description     TEXT NULL,
    orientation     ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape',
    width_mm        DECIMAL(6,2) NOT NULL DEFAULT 297.00,  -- A4 landscape ancho
    height_mm       DECIMAL(6,2) NOT NULL DEFAULT 210.00, -- A4 landscape alto
    design_json     LONGTEXT NOT NULL,                    -- canvas.toJSON() de Fabric.js
    thumbnail_url   VARCHAR(500) NULL,                    -- PNG guardado en WP Media Library
    is_default      TINYINT(1) NOT NULL DEFAULT 0,        -- Solo una puede ser default
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      BIGINT UNSIGNED NOT NULL,             -- FK → wp_users.ID
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active  (is_active),
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────
-- 2. Firmantes registrados
-- ──────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_certificate_signers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,               -- "María García"
    title           VARCHAR(200) NOT NULL,               -- "Directora Académica"
    signature_url   VARCHAR(500) NULL,                   -- PNG transparente en WP Media Library
    is_active       TINYINT(1) NOT NULL DEFAULT 1,       -- Solo activos aparecen al emitir
    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────
-- 3. Secuencia de folios (garantiza unicidad incluso con concurrencia)
-- ──────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_certificate_folio_seq (
    year            YEAR NOT NULL,
    last_seq        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────
-- 4. Certificados emitidos (registro principal)
-- ──────────────────────────────────────────────────────────────────
CREATE TABLE {prefix}aura_certificates (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Relaciones con Módulo Estudiantes (FKs lógicas, no constraints físicas)
    student_id          BIGINT UNSIGNED NOT NULL,        -- FK → aura_students.id
    enrollment_id       BIGINT UNSIGNED NULL,            -- FK → aura_student_enrollments.id
    -- Relaciones internas
    template_id         BIGINT UNSIGNED NOT NULL,        -- FK → aura_certificate_templates.id
    -- Identificación única
    folio               VARCHAR(50) NOT NULL UNIQUE,     -- CEM-2026-0042
    verification_code   VARCHAR(36) NOT NULL UNIQUE,     -- UUID v4
    -- Snapshots al momento de emitir (no cambian si se edita el estudiante)
    student_name        VARCHAR(300) NOT NULL,           -- Nombre completo snapshot
    course_name         VARCHAR(300) NOT NULL,           -- Nombre del curso snapshot
    program_name        VARCHAR(300) NULL,               -- Nombre del área/programa snapshot
    instructor_name     VARCHAR(300) NULL,               -- Instructor del curso snapshot
    organization_name   VARCHAR(300) NULL,               -- De aura_org_name option
    -- Fechas
    graduation_date     DATE NULL,                       -- De aura_students.graduated_at
    issued_at           DATETIME NOT NULL,
    -- Generación del PDF
    pdf_path            VARCHAR(500) NULL,               -- Ruta relativa en uploads/aura-certificates/
    -- Firmantes al momento de emisión
    include_signatures  TINYINT(1) NOT NULL DEFAULT 1,
    signers_json        TEXT NULL,                       -- JSON snapshot: [{id, name, title, signature_url}]
    -- Extra configurable al emitir
    description         TEXT NULL,                       -- Texto libre personalizable al emitir
    -- Auditoría
    issued_by           BIGINT UNSIGNED NOT NULL,        -- FK → wp_users.ID
    status              ENUM('active','revoked') NOT NULL DEFAULT 'active',
    revoked_at          DATETIME NULL,
    revoked_by          BIGINT UNSIGNED NULL,
    revoke_reason       VARCHAR(500) NULL,
    notes               TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student     (student_id),
    INDEX idx_enrollment  (enrollment_id),
    INDEX idx_template    (template_id),
    INDEX idx_folio       (folio),
    INDEX idx_verify_code (verification_code),
    INDEX idx_status      (status),
    INDEX idx_issued_at   (issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Notas sobre la tabla de secuencias

La tabla `aura_certificate_folio_seq` garantiza folios únicos con concurrencia. En `Aura_Certificates_Folio::generate()`:

```php
global $wpdb;
$year = (int) date('Y');
$table = $wpdb->prefix . 'aura_certificate_folio_seq';

// INSERT INTO ... ON DUPLICATE KEY UPDATE ejecuta en transacción
$wpdb->query( $wpdb->prepare(
    "INSERT INTO {$table} (year, last_seq) VALUES (%d, 1)
     ON DUPLICATE KEY UPDATE last_seq = last_seq + 1",
    $year
) );

$row = $wpdb->get_row( $wpdb->prepare(
    "SELECT last_seq FROM {$table} WHERE year = %d",
    $year
) );

$prefix  = Aura_Certificates_Settings::get('folio_prefix') ?: 'CEM';
$padded  = str_pad( $row->last_seq, 4, '0', STR_PAD_LEFT );
return "{$prefix}-{$year}-{$padded}"; // e.g. CEM-2026-0042
```

---

## 4. Capabilities del Módulo

Registrar en `Aura_Roles_Manager::get_all_capabilities()` y `get_capabilities_for_ui()` bajo la sección `certificates`:

```php
'certificates' => array(
    'aura_cert_template_view'       => __('Ver plantillas de certificados', 'aura-suite'),
    'aura_cert_template_create'     => __('Crear nuevas plantillas de diseño', 'aura-suite'),
    'aura_cert_template_edit'       => __('Editar plantillas existentes', 'aura-suite'),
    'aura_cert_template_delete'     => __('Eliminar plantillas (solo admin)', 'aura-suite'),
    'aura_cert_issue'               => __('Emitir certificados a estudiantes', 'aura-suite'),
    'aura_cert_revoke'              => __('Revocar certificados emitidos (solo admin)', 'aura-suite'),
    'aura_cert_view_all'            => __('Ver listado completo de certificados emitidos', 'aura-suite'),
    'aura_cert_download_any'        => __('Descargar PDF de cualquier estudiante', 'aura-suite'),
    'aura_cert_download_own'        => __('Descargar solo el propio certificado (estudiante)', 'aura-suite'),
    'aura_cert_signatures_manage'   => __('Gestionar firmantes y sus firmas', 'aura-suite'),
    'aura_cert_verify_public'       => __('Verificar autenticidad vía página pública (sin login)', 'aura-suite'),
    'aura_cert_settings'            => __('Configurar el módulo de certificados', 'aura-suite'),
    'aura_cert_reports'             => __('Ver reportes de certificados emitidos', 'aura-suite'),
),
```

**Asignación automática a Administradores** en `ensure_admin_capabilities()`: todas las capabilities del bloque `certificates`.

**Capability asignada al WP user del estudiante** cuando se emite un certificado:
- `aura_cert_download_own` — para poder descargar desde el portal frontend

### Perfiles de usuario típicos y capabilities sugeridas

| Perfil | Capabilities |
|--------|-------------|
| Administrador | Todas |
| Director Académico | `aura_cert_template_view`, `aura_cert_issue`, `aura_cert_view_all`, `aura_cert_download_any`, `aura_cert_reports` |
| Secretaría Académica | `aura_cert_template_view`, `aura_cert_issue`, `aura_cert_view_all` |
| Auditor | `aura_cert_view_all`, `aura_cert_reports` |
| Estudiante (frontend) | `aura_cert_download_own`, `aura_cert_verify_public` |

---

## 5. Menús de WordPress Admin

```
🏅 Certificados                         (capab: aura_cert_view_all)  posición: 4.7
├── Dashboard                           (capab: aura_cert_view_all)
├── Certificados Emitidos               (capab: aura_cert_view_all)
├── Plantillas de Diseño                (capab: aura_cert_template_view)
├── Firmantes                           (capab: aura_cert_signatures_manage)
├── Emisión Masiva                      (capab: aura_cert_issue)
├── Reportes                            (capab: aura_cert_reports)
└── Configuración                       (capab: aura_cert_settings)
```

**Posición**: `4.7` (después de Estudiantes en `4.6`, antes de Finanzas)

**Slug del menú principal**: `aura-certificates`

**Patrón de registro** (mismo que `class-students-admin.php`):

```php
add_menu_page(
    __('Certificados', 'aura-suite'),
    __('🏅 Certificados', 'aura-suite'),
    'aura_cert_view_all',
    'aura-certificates',
    [ 'Aura_Certificates_Admin', 'render_dashboard' ],
    'dashicons-awards',
    4.7
);
```

---

## 6. Variables Dinámicas

Estas variables se sustituyen en tiempo real en el canvas del editor (preview) y definitivamente al generar el PDF.

| Variable | Se reemplaza con | Fuente |
|----------|-----------------|--------|
| `{nombre}` | Primer nombre del estudiante | `aura_students.first_name` |
| `{apellido}` | Apellido del estudiante | `aura_students.last_name` |
| `{nombre_completo}` | Nombre y apellido completos | `first_name . ' ' . last_name` |
| `{curso}` | Nombre del curso | `aura_student_courses.name` (snapshot en `aura_certificates.course_name`) |
| `{programa}` | Nombre del área o programa académico | `wp_aura_areas.name` (snapshot `program_name`) |
| `{fecha_emision}` | Fecha en que se emitió (dd/mm/yyyy) | `aura_certificates.issued_at` |
| `{fecha_graduacion}` | Fecha de graduación registrada | `aura_students.graduated_at` |
| `{instructor}` | Nombre del instructor del curso | `wp_users.display_name` del `instructor_id` (snapshot) |
| `{organizacion}` | Nombre de la organización | `get_option('aura_org_name')` |
| `{folio}` | Folio único del certificado | `aura_certificates.folio` e.g. `CEM-2026-0042` |
| `{año}` | Año de emisión | `date('Y', strtotime($issued_at))` |
| `{descripcion}` | Texto adicional provisto al emitir | `aura_certificates.description` |
| `{logo_url}` | URL del logo de la organización | `get_option('aura_org_logo_url')` |

**En el editor Fabric.js**: al insertar una variable dinámica, se crea un `fabric.Text` con el literal `{nombre_completo}` para ayuda visual; en la preview y al generar PDF, se pasan los datos reales.

---

## 7. Fases de Implementación

---

### FASE 1 — Setup: Base de Datos, Capabilities y Menús

**Archivos**: `class-certificates-setup.php`, `class-certificates-admin.php`

**Tareas**:

#### `Aura_Certificates_Setup`

1. Método estático `init()`:
   ```php
   add_action('plugins_loaded', [__CLASS__, 'maybe_upgrade_db']);
   add_action('init',           [__CLASS__, 'register_rewrite_rules']);
   add_action('init',           [__CLASS__, 'register_cron_events']);
   add_action('aura_student_graduated', [__CLASS__, 'on_student_graduated'], 10, 2);
   ```

2. Método `create_tables()`: ejecuta el SQL del apartado 3.2 con `dbDelta()`.

3. Método `register_capabilities()`: añade todas las capabilities del apartado 4 al array de `Aura_Roles_Manager`.

4. Método `register_rewrite_rules()`:
   ```php
   // URL pública: /verificar-certificado/{folio}
   add_rewrite_rule(
       '^verificar-certificado/([A-Z0-9\-]+)/?$',
       'index.php?aura_cert_verify=$matches[1]',
       'top'
   );
   add_rewrite_tag('%aura_cert_verify%', '([A-Z0-9\-]+)');
   ```

5. Método `on_student_graduated($student_id, $enrollment_id)`:
   - Guarda `_transient_` `aura_cert_ready_{$student_id}` = `$enrollment_id` (TTL 30 días)
   - Este transient lo consulta el perfil del estudiante para mostrar el botón "Emitir Certificado"

6. Método `register_cron_events()`:
   - Registra hook `aura_certs_bulk_process` para la cola de emisión masiva
   - Intervalo: cada 2 minutos (`wp_schedule_event`)

#### `Aura_Certificates_Admin`

1. Método `init()`:
   ```php
   add_action('admin_menu',         [__CLASS__, 'register_menus']);
   add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
   ```

2. Método `register_menus()`: registra los 7 submenús del apartado 5.

3. Método `enqueue_assets($hook)`:
   - En páginas del módulo (`aura-certificates*`): encola `certificates.css`, `certificates.js`
   - **Solo** en la página del editor de plantillas (`aura-certificates-template-builder`):
     - Encola Fabric.js (CDN recomendado: `https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js` o local en `assets/js/vendor/fabric.min.js`)
     - Encola `certificate-builder.js`
   - En todas las páginas admin excepto el builder: **NO** cargar Fabric.js
   - `wp_localize_script('certificates', 'auraCertificates', [...])`— pasar nonce, AJAX URL, site URL, org data

4. Método `localize_data()`: array de datos para JS:
   ```php
   return [
       'ajaxUrl'        => admin_url('admin-ajax.php'),
       'nonce'          => wp_create_nonce('aura_certificates_nonce'),
       'siteUrl'        => site_url(),
       'verifySlug'     => 'verificar-certificado',
       'orgName'        => get_option('aura_org_name', get_bloginfo('name')),
       'orgLogoUrl'     => get_option('aura_org_logo_url', ''),
       'dynamicVars'    => Aura_Certificates_Templates::get_dynamic_variables_list(),
       'i18n'           => [
           'confirmRevoke' => __('¿Revocar este certificado? Esta acción no se puede deshacer.', 'aura-suite'),
           'issuing'       => __('Emitiendo certificado...', 'aura-suite'),
           'success'       => __('Certificado emitido correctamente.', 'aura-suite'),
       ],
   ];
   ```

---

### FASE 2 — Editor de Plantillas (Template Builder)

**Archivos**: `class-certificates-templates.php`, `templates/certificates/template-builder.php`, `templates/certificates/templates-list.php`, `assets/js/certificate-builder.js`

**Stack técnico**:
- **Fabric.js v5.x** (canvas HTML5, drag & drop libre, no hay grillas ni columnas)
- **Almacenamiento**: `canvas.toJSON()` → `design_json` en la tabla `aura_certificate_templates`
- **Preview/thumbnail**: `canvas.toDataURL('image/png', 0.9)` → enviado al servidor → guardado en WP Media Library → URL en `thumbnail_url`

#### Canvas drag & drop: comportamiento requerido

- Cualquier elemento se puede mover libremente (X/Y libre, sin snap a grilla por defecto)
- Resize con handles en 4 esquinas
- Rotación con handle circular
- Z-index: botones "Subir capa" / "Bajar capa"
- Undo/Redo historial (mínimo 20 estados): `Ctrl+Z` / `Ctrl+Y`
- Guías de alineación snap-to-center (mostrar línea guía al acercar al centro)
- Selección múltiple con `Shift+Click` o arrastre
- Teclas `Delete`/`Backspace` eliminan el elemento seleccionado
- `Ctrl+D` duplica el elemento seleccionado

#### Elementos disponibles en el canvas

| Elemento | Tipo Fabric.js | Datos guardados |
|----------|---------------|-----------------|
| Texto estático | `fabric.Textbox` | text, fontFamily, fontSize, fontWeight, fontStyle, fill, textAlign, charSpacing, left, top, width, angle |
| Variable dinámica | `fabric.Textbox` | igual que texto pero con `data.isDynamic = true`, `data.varKey = '{nombre_completo}'` |
| Logo de la org | `fabric.Image` | `data.type = 'org_logo'`, srcPlaceholder, left, top, width, height |
| Imagen decorativa | `fabric.Image` | src (base64 o URL), left, top, width, height, angle |
| Firma digital | `fabric.Group` (imagen firma + texto nombre + texto cargo) | `data.type = 'signer'`, `data.signerId` |
| Código QR | `fabric.Image` | `data.type = 'qr_code'` — se renderiza al emitir, en el builder se muestra un QR de placeholder |
| Número de folio | `fabric.Textbox` | igual a variable dinámica con `data.varKey = '{folio}'` |
| Rectángulo | `fabric.Rect` | fill, stroke, strokeWidth, rx, ry, left, top, width, height, angle |
| Línea decorativa | `fabric.Line` | stroke, strokeWidth, x1, y1, x2, y2 |
| Fondo (Backdrop) | `fabric.Rect` | fill (color sólido o gradiente), width=canvas.width, height=canvas.height, `data.type = 'background'` |
| Fondo imagen | `fabric.Image` | `data.type = 'background_image'`, scaleToFit canvas |

#### Panel de herramientas izquierdo

```
┌─────────────────────────────────┐
│  📋 Elementos                   │
├─────────────────────────────────┤
│  [Aa] Texto                     │
│  [{}] Variable dinámica         │   ← dropdown con lista de variables
│  [🖼] Imagen                    │
│  [🏢] Logo de la organización   │
│  [✍] Firma de firmante          │   ← dropdown con firmantes activos
│  [QR] Código QR                 │
│  [#] Folio                      │
│  [▭] Rectángulo                 │
│  [─] Línea                      │
│  [🎨] Fondo/Color de fondo      │
├─────────────────────────────────┤
│  📐 Orientación                 │
│  ○ Paisaje (297×210mm)          │
│  ○ Retrato  (210×297mm)         │
│  ○ Personalizado: __×__ mm      │
├─────────────────────────────────┤
│  📚 Plantillas base             │
│  [Clásico dorado]               │
│  [Moderno minimalista]          │
│  [Profesional azul]             │
│  [Académico formal]             │
│  [Participación informal]       │
└─────────────────────────────────┘
```

#### Panel de propiedades derecho (contextual según elemento seleccionado)

Para texto / variable dinámica:
```
Fuente: [Google Font selector ▼]
Tamaño: [__] px
Negrita [□] Itálica [□]
Color: [████ #000000]
Alineación: [← ↔ →]
Espaciado: [__]
```

Para imagen / logo / firma:
```
Ancho: [__] mm  Alto: [__] mm
Bloquear proporción [✓]
Opacidad: [████████░░] 80%
```

Para formas/rectángulos:
```
Relleno: [████ color]
Borde: [████ color]    Grosor: [__] px
Radio de esquina: [__] px
Opacidad: [████████░░]
```

#### Tipografías disponibles (Google Fonts pre-cargadas)

Mínimo 10 fuentes variadas, incluyendo:
- `Playfair Display` (elegante/académico)
- `Montserrat` (moderno)
- `Lato` (neutral)
- `Great Vibes` (cursiva caligráfica para nombres)
- `Roboto`, `Open Sans`, `Raleway`, `Merriweather`, `Cinzel`, `Dancing Script`

Cargar con `@import url('https://fonts.googleapis.com/css2?family=...')` en el iframe del builder.

#### Plantillas prediseñadas incluidas (Fase 3)

Al hacer clic en una plantilla base, se llama `canvas.loadFromJSON(prebuiltDesigns['clasico_dorado'])` para poblar el canvas. Las 5 plantillas vendrán como JSON hardcodeado en `certificate-builder.js` o como archivos JSON en `assets/js/templates/`:

| ID | Nombre | Paleta principal |
|----|--------|-----------------|
| `clasico_dorado` | Clásico Dorado | Dorado `#B8860B` + crema |
| `moderno_minimalista` | Moderno Minimalista | Negro + blanco con acento violeta `#8b5cf6` |
| `profesional_azul` | Profesional Azul Corporativo | Azul `#1e3a5f` + blanco |
| `academico_formal` | Académico Formal | Burdeos `#7B1F2E` + gris oscuro |
| `participacion_informal` | Participación Informal | Verde `#16a34a` + blanco |

#### AJAX handlers en `Aura_Certificates_Templates`

```php
add_action('wp_ajax_aura_cert_save_template',     [__CLASS__, 'ajax_save_template']);
add_action('wp_ajax_aura_cert_load_template',     [__CLASS__, 'ajax_load_template']);
add_action('wp_ajax_aura_cert_delete_template',   [__CLASS__, 'ajax_delete_template']);
add_action('wp_ajax_aura_cert_list_templates',    [__CLASS__, 'ajax_list_templates']);
add_action('wp_ajax_aura_cert_set_default',       [__CLASS__, 'ajax_set_default']);
add_action('wp_ajax_aura_cert_save_thumbnail',    [__CLASS__, 'ajax_save_thumbnail']); // canvas.toDataURL → WP Media
```

#### Método `ajax_save_template()`

```php
public static function ajax_save_template(): void {
    check_ajax_referer('aura_certificates_nonce', 'nonce');
    if ( ! current_user_can('aura_cert_template_create') && ! current_user_can('aura_cert_template_edit') ) {
        wp_send_json_error(['message' => __('Sin permisos.', 'aura-suite')], 403);
    }
    $id          = absint($_POST['id'] ?? 0);
    $name        = sanitize_text_field($_POST['name'] ?? '');
    $design_json = wp_unslash($_POST['design_json'] ?? ''); // No sanitizar HTML, es JSON
    // Validar que design_json sea JSON válido
    $decoded = json_decode($design_json, true);
    if ( json_last_error() !== JSON_ERROR_NONE || empty($name) ) {
        wp_send_json_error(['message' => __('Datos inválidos.', 'aura-suite')], 400);
    }
    // Re-codificar limpio para evitar datos maliciosos embebidos en strings JSON
    $clean_json = wp_json_encode($decoded);
    // ... INSERT o UPDATE en aura_certificate_templates
    wp_send_json_success(['id' => $new_id, 'message' => __('Plantilla guardada.', 'aura-suite')]);
}
```

**Seguridad del design_json**: aunque es JSON del canvas (no ejecutable), se debe decodificar y re-encodificar para evitar inyecciones en strings. Las URLs de imágenes dentro del JSON deben validarse con `esc_url_raw()`.

---

### FASE 3 — Gestión de Firmantes

**Archivos**: `class-certificates-signers.php`, `templates/certificates/signers.php`

**Tareas**:

#### `Aura_Certificates_Signers`

1. Método `init()`:
   ```php
   add_action('wp_ajax_aura_cert_save_signer',   [__CLASS__, 'ajax_save_signer']);
   add_action('wp_ajax_aura_cert_delete_signer', [__CLASS__, 'ajax_delete_signer']);
   add_action('wp_ajax_aura_cert_list_signers',  [__CLASS__, 'ajax_list_signers']);
   add_action('wp_ajax_aura_cert_toggle_signer', [__CLASS__, 'ajax_toggle_active']);
   ```

2. `ajax_save_signer()`: Requiere `aura_cert_signatures_manage`. Campos: `name` (sanitize_text_field), `title` (sanitize_text_field), `signature_url` (esc_url_raw + validar que sea URL de la propia instalación WP o del Media Library). INSERT o UPDATE en `aura_certificate_signers`.

3. La imagen de firma se sube mediante el Media Uploader de WordPress estándar (`wp.media` API). Solo aceptar formatos: PNG con transparencia. El servidor valida `wp_check_filetype()` y que el attachment pertenezca a la instalación actual.

4. Template `signers.php`:
   - Tabla de firmantes: Foto de firma (miniatura), Nombre, Cargo, Estado (Activo/Inactivo), Orden, Acciones
   - Botón "Subir firma": abre el Media Library modal de WP
   - Toggle "Activo/Inactivo": firmantes inactivos no aparecen al emitir certificados
   - Drag para reordenar (actualiza `sort_order`)
   - Máximo 4 firmantes activos simultáneos (validar en backend)

---

### FASE 4 — Emisión Individual de Certificados

**Archivos**: `class-certificates-issuer.php`, `class-certificates-folio.php`, `templates/certificates/issue-modal.php`

#### Flujo completo de emisión individual

1. Admin abre el perfil del estudiante en el módulo Estudiantes
2. Sistema detecta transient `aura_cert_ready_{$student_id}` → muestra botón **"🏅 Emitir Certificado"**
3. Al hacer clic, abre `issue-modal.php` con:
   - Datos del estudiante (readonly): nombre, curso, programa, fecha de graduación
   - Selector de plantilla (radio cards con thumbnail y nombre, pre-selecciona la `is_default`)
   - Preview en tiempo real al cambiar plantilla (renderizado en `<canvas>` dentro del modal)
   - Selector de firmantes: checkboxes con los firmantes activos (pre-marcados todos)
   - Campo "Descripción adicional" (text area): texto libre que rellena `{descripcion}`
   - Opciones:
     - `[✓]` Enviar certificado por email al estudiante
     - `[✓]` Notificar por WhatsApp (si hay servicio configurado)
     - `[ ]` Requerir paz y salvo (si está activo en Configuración)
   - Botón "Emitir Certificado" → llama `wp_ajax_aura_cert_issue`

4. Backend `ajax_issue_certificate()`:
   - `check_ajax_referer('aura_certificates_nonce', 'nonce')`
   - Verificar `current_user_can('aura_cert_issue')`
   - Si paz y salvo activo: consultar saldo en `aura_student_enrollments` → rechazar si `balance_due > 0`
   - Obtener datos del estudiante, inscripción, curso, programa
   - Llamar `Aura_Certificates_Folio::generate()` → obtener folio único
   - Generar UUID v4: `wp_generate_uuid4()` (WP >= 5.3) para `verification_code`
   - Generar QR: `Aura_Certificates_Issuer::generate_qr($verification_url)` → PNG base64
   - Resolver variables dinámicas: `Aura_Certificates_Issuer::resolve_variables($template_id, $data)`
   - Exportar canvas PNG: **opción A** (recomendada): el JS del front envía `canvas.toDataURL('image/png', 1.0)` al servidor antes de confirmar—guardarlo temporalmente y usarlo para mPDF; **opción B** (sin JS): renderizar el JSON del canvas en el servidor con una librería PHP compatible (complejo, no recomendado para v1)
   - Generar PDF con mPDF: `new \Mpdf\Mpdf(['format' => 'A4-L', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0])`; embeber la imagen PNG full-page; guardar en `wp_upload_dir()['basedir'] . '/aura-certificates/' . date('Y/m') . '/cert-' . $folio . '.pdf'`
   - Crear directorio si no existe: `wp_mkdir_p()` + crear `.htaccess` con `Deny from all` para bloquear acceso directo
   - Insertar en `aura_certificates`
   - Asignar capability `aura_cert_download_own` al WP user del estudiante
   - Borrar transient `aura_cert_ready_{$student_id}`
   - Enviar email: `Aura_Certificates_Notifications::send_issued_email($certificate_id)`
   - Si WhatsApp: `Aura_Certificates_Notifications::send_issued_whatsapp($certificate_id)`
   - Retornar `wp_send_json_success(['folio' => $folio, 'download_url' => $download_url])`

#### Método `generate_qr($url)`

Usar `chillerlan/php-qrcode` vía Composer. Configurar:
```php
$options = new \chillerlan\QRCode\QROptions([
    'outputType' => \chillerlan\QRCode\Output\QRGdImage::class,
    'returnResource' => false,
    'imageBase64'    => true,
    'eccLevel'       => \chillerlan\QRCode\Data\QRMatrix::ECC_H, // Alta corrección
    'scale'          => 8,
    'imageTransparent' => false,
    'moduleValues'   => [
        \chillerlan\QRCode\Data\QRMatrix::M_DATA_DARK => [0, 0, 0],
        \chillerlan\QRCode\Data\QRMatrix::M_FINDER    => [0, 0, 0],
        \chillerlan\QRCode\Data\QRMatrix::M_BACKGROUND => [255, 255, 255],
    ],
]);
return (new \chillerlan\QRCode\QRCode($options))->render($url);
```

Agregar al `composer.json`:
```json
{
    "require": {
        "chillerlan/php-qrcode": "^4.3"
    }
}
```

#### Endpoint seguro de descarga de PDF

El PDF no se sirve con URL directa. Se crea un endpoint protegido:

```
GET /wp-admin/admin-ajax.php?action=aura_cert_download&folio=CEM-2026-0042&nonce=xxx
```

```php
add_action('wp_ajax_aura_cert_download',        [__CLASS__, 'ajax_download_pdf']); // Admin con aura_cert_download_any
add_action('wp_ajax_nopriv_aura_cert_download', [__CLASS__, 'ajax_download_pdf']); // Estudiante frontend con token

public static function ajax_download_pdf(): void {
    $folio = sanitize_text_field($_GET['folio'] ?? '');
    // Validar folio existe y está activo
    $cert = /* obtener de BD WHERE folio = %s AND status = 'active' */;
    if ( ! $cert ) { wp_die(__('Certificado no encontrado.', 'aura-suite'), 404); }
    
    // Verificar permiso: admin con aura_cert_download_any O estudiante propietario con aura_cert_download_own
    $is_owner = is_user_logged_in() && (int) get_current_user_id() === (int) /* wp_user_id del estudiante */;
    $can_download_any = current_user_can('aura_cert_download_any');
    if ( ! $can_download_any && ! ($is_owner && current_user_can('aura_cert_download_own')) ) {
        // Para URL pública (no logueado), verificar token con tiempo de expiración
        $token = sanitize_text_field($_GET['token'] ?? '');
        if ( ! wp_verify_nonce($token, 'aura_cert_download_' . $folio) ) {
            wp_die(__('No autorizado.', 'aura-suite'), 403);
        }
    }
    
    $file_path = wp_upload_dir()['basedir'] . '/aura-certificates/' . $cert->pdf_path;
    if ( ! file_exists($file_path) ) { wp_die(__('PDF no disponible.', 'aura-suite'), 404); }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="certificado-' . sanitize_file_name($folio) . '.pdf"');
    header('Content-Length: ' . filesize($file_path));
    header('X-Content-Type-Options: nosniff');
    readfile($file_path);
    exit;
}
```

> **Seguridad**: nunca exponer `$_SERVER['DOCUMENT_ROOT']` ni rutas físicas al usuario. Solo retornar el binario del PDF.

---

### FASE 5 — Emisión Masiva

**Archivos**: `class-certificates-issuer.php`, `templates/certificates/bulk-issue.php`

**Flujo**:

1. Admin va a **"Emisión Masiva"**
2. Filtros: Curso, Estado de inscripción (`completed`), Período (fecha de graduación)
3. Tabla de resultados mostrando estudiantes elegibles (graduados sin certificado previo para ese curso)
4. Checkbox "Seleccionar todos" + selección individual
5. Selector de plantilla (aplica a todos)
6. Opciones: `[✓]` Enviar email a cada estudiante
7. Botón **"Emitir Certificados Seleccionados"** → llama `wp_ajax_aura_cert_queue_bulk`
8. Backend encola los IDs en opción WP `aura_certs_bulk_queue` (JSON array)
9. Dispara `do_action('aura_certs_bulk_process')` inmediatamente (también en Cron cada 2 minutos)
10. El Cron procesa máximo 10 certificados por ejecución (evitar timeout)
11. Al finalizar la cola, envía email al admin que inició el proceso

```php
add_action('wp_ajax_aura_cert_queue_bulk',   [__CLASS__, 'ajax_queue_bulk']);
add_action('wp_ajax_aura_cert_bulk_status',  [__CLASS__, 'ajax_bulk_status']); // Polling del progreso
add_action('aura_certs_bulk_process',        [__CLASS__, 'process_bulk_queue']);
```

**Template `bulk-issue.php`**:
- Barra de progreso visible mientras se procesa (JS hace polling a `aura_cert_bulk_status` cada 3s)
- Log en tiempo real: "✅ Emitido: Juan Pérez — CEM-2026-0042", "❌ Error: María López — ya tenía certificado"
- Botón "Cancelar" disponible hasta que el Cron termine

---

### FASE 6 — Verificación Pública

**Archivos**: `class-certificates-verify.php`, `templates/certificates/verify-public.php`, `assets/js/certificate-verify.js`

**URL**: `{site_url}/verificar-certificado/{folio}` — sin autenticación requerida

**Implementación**:

```php
public static function init(): void {
    add_action('template_redirect',   [__CLASS__, 'intercept_verify_page']);
    add_shortcode('aura_verificar_certificado', [__CLASS__, 'shortcode_verify']);
}

public static function intercept_verify_page(): void {
    $folio = get_query_var('aura_cert_verify');
    if ( empty($folio) ) return;
    
    // Sanitizar: solo alfanumérico y guiones
    $folio = sanitize_text_field( preg_replace('/[^A-Z0-9\-]/i', '', $folio) );
    $cert  = self::get_certificate_public_data($folio); // Solo datos públicos, nunca paths
    
    include AURA_PLUGIN_DIR . 'templates/certificates/verify-public.php';
    exit;
}
```

**Método `get_certificate_public_data($folio)`** — solo retorna los campos seguros para mostrar públicamente:

```php
return [
    'valid'             => ($cert && $cert->status === 'active'),
    'status'            => $cert->status ?? 'not_found',
    'student_name'      => $cert->student_name ?? '',
    'course_name'       => $cert->course_name ?? '',
    'program_name'      => $cert->program_name ?? '',
    'organization_name' => $cert->organization_name ?? '',
    'issued_at'         => $cert->issued_at ?? '',
    'graduation_date'   => $cert->graduation_date ?? '',
    'folio'             => $folio,
    'signers'           => $cert ? json_decode($cert->signers_json, true) : [],
    // NUNCA incluir: pdf_path, verification_code completo, student_id, wp_user_id
];
```

**Template `verify-public.php`** muestra:
```
┌──────────────────────────────────────────────────────────────┐
│  [Logo de la organización]      [QR del certificado]         │
│                                                               │
│  ✅ CERTIFICADO VÁLIDO                                        │
│  (o: ❌ CERTIFICADO REVOCADO / ❓ NO ENCONTRADO)             │
│                                                               │
│  Nombre: Juan Carlos Pérez                                    │
│  Programa: Formación en Liderazgo                             │
│  Curso: Comunicación Efectiva                                 │
│  Fecha de emisión: 15 de enero de 2026                        │
│  Folio: CEM-2026-0042                                         │
│                                                               │
│  Firmado por:                                                 │
│  [imagen firma] María García — Directora Académica            │
│  [imagen firma] Dr. Luis Rodríguez — Rector                   │
│                                                               │
│  Emitido por: Instituto CEM                                   │
└──────────────────────────────────────────────────────────────┘
```

**Shortcode** `[aura_verificar_certificado]`: versión embebible en cualquier página de WordPress. Acepta parámetro opcional `folio` o lee de `$_GET['folio']`.

> **Seguridad**: nunca mostrar rutas de archivos, IDs internos, ni información de usuarios no relacionada con el certificado. Los datos de la página de verificación son intencionalmente públicos (nombre, curso, fecha) y no constituyen violación de privacidad ya que el beneficiario tiene el folio.

---

### FASE 7 — Portal Frontend del Estudiante

**Archivos**: `class-certificates-frontend.php`, `templates/certificates/frontend/my-certificates.php`

**Integración con el portal de Estudiantes**:

El módulo de Estudiantes en `class-students-frontend.php` llama a un hook en la pestaña "Mis Certificados":

```php
// En templates/students/frontend/ ... pestaña Mis Certificados:
do_action('aura_render_student_certificates_tab', $student_id, $wp_user_id);
```

Este módulo escucha ese hook:

```php
add_action('aura_render_student_certificates_tab', [__CLASS__, 'render_student_tab'], 10, 2);
```

**Método `render_student_tab($student_id, $wp_user_id)`**:
- Verifica `current_user_can('aura_cert_download_own')` o que `$wp_user_id === get_current_user_id()`
- Consulta todos los certificados del estudiante donde `student_id = $student_id AND status = 'active'`
- Agrupa por `program_name`
- Renderiza `templates/certificates/frontend/my-certificates.php`

**Template `my-certificates.php`**:

```
┌──────────────────────────────────────────────────────────────┐
│  🏅 Mis Certificados                                          │
├──────────────────────────────────────────────────────────────┤
│  📚 Programa: Formación en Liderazgo                         │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Comunicación Efectiva              15 ene 2026        │   │
│  │ Folio: CEM-2026-0042                                  │   │
│  │ [📄 Descargar PDF]  [🔗 Compartir]  [QR Ver código]  │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Trabajo en Equipo                  20 feb 2026        │   │
│  │ Folio: CEM-2026-0087                                  │   │
│  │ [📄 Descargar PDF]  [🔗 Compartir]  [QR Ver código]  │   │
│  └──────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

**Botón "Descargar PDF"**: apunta a `admin-ajax.php?action=aura_cert_download&folio=CEM-2026-0042&token={nonce}`

**Botón "Compartir"**: copia al portapapeles `site_url() . '/verificar-certificado/' . $folio`

**Botón "QR"**: abre modal con imagen del QR generado dinámicamente (base64 del QR del verification_url)

**Seguridad**: todos los endpoints del portal requieren `is_user_logged_in()` + verificar que el certificado pertenece al usuario logueado.

---

### FASE 8 — Listado Admin de Certificados Emitidos

**Archivos**: `class-certificates-admin.php` (callbacks), `templates/certificates/list.php`

**Template `list.php`** (tabla principal, estilo WP_List_Table):

| Columna | Valor |
|---------|-------|
| Folio | `CEM-2026-0042` (enlace a verificación pública) |
| Estudiante | Nombre completo (enlace al perfil en Módulo Estudiantes) |
| Curso / Programa | Nombre del curso |
| Plantilla usada | Nombre de la plantilla |
| Fecha de emisión | `d/m/Y H:i` |
| Emitido por | Nombre del usuario admin |
| Estado | 🟢 Activo / 🔴 Revocado |
| Acciones | 👁 Ver · 📄 PDF · ✉ Re-enviar email · ⊘ Revocar |

**Filtros**:
- Búsqueda: por nombre de estudiante, folio, curso
- Por estado: Todos / Activos / Revocados  
- Por período: mes/año
- Por plantilla

**Acción "Revocar"**:
- Requiere `aura_cert_revoke` + confirmación con razón obligatoria
- Actualiza `status = 'revoked'`, `revoked_at`, `revoked_by`, `revoke_reason`
- Envía email de notificación al estudiante
- El certificado sigue visible en el listado (nunca se borra físicamente)
- La página de verificación pública mostrará "❌ CERTIFICADO REVOCADO"

**Bulk actions**:
- "Descargar PDFs seleccionados" → ZIP descargable
- "Revocar seleccionados" (requiere `aura_cert_revoke`)
- "Re-enviar email seleccionados"

---

### FASE 9 — Notificaciones

**Archivos**: `class-certificates-notifications.php`

**Principio**: NO gestionar SMTP ni WhatsApp en este módulo. Todo se delega a `Aura_Notifications`.

| Evento | Destinatarios | Canal |
|--------|--------------|-------|
| Certificado emitido | Estudiante | Email (con PDF adjunto) + WhatsApp |
| Certificado revocado | Estudiante + Admin | Email |
| Cola masiva completada | Admin que inició | Email con resumen |

**Implementación**:

```php
class Aura_Certificates_Notifications {

    public static function send_issued_email( int $certificate_id ): void {
        $cert    = /* obtener de BD */;
        $student = /* obtener aura_students + wp_users email */;
        
        $download_url = add_query_arg([
            'action' => 'aura_cert_download',
            'folio'  => $cert->folio,
            'token'  => wp_create_nonce('aura_cert_download_' . $cert->folio),
        ], admin_url('admin-ajax.php'));
        
        $verify_url = site_url('/verificar-certificado/' . $cert->folio);
        $pdf_path   = wp_upload_dir()['basedir'] . '/aura-certificates/' . $cert->pdf_path;
        
        $body = /* HTML template con congratulaciones, folio, enlace de descarga, enlace de verificación */;
        
        Aura_Notifications::send_email(
            $student->email,
            sprintf(__('🏅 Tu certificado está listo — %s', 'aura-suite'), $cert->folio),
            $body,
            [
                'module'      => 'certificates',
                'context'     => 'issued',
                'attachments' => file_exists($pdf_path) ? [$pdf_path] : [],
            ]
        );
    }

    public static function send_issued_whatsapp( int $certificate_id ): void {
        $cert    = /* obtener de BD */;
        $student = /* obtener phone + phone_country de aura_students */;
        if ( empty($student->phone) ) return;
        
        $verify_url = site_url('/verificar-certificado/' . $cert->folio);
        $message = sprintf(
            __("🏅 ¡Felicitaciones %s! Tu certificado *%s* ha sido emitido.\n\nFolio: %s\nVerificar: %s", 'aura-suite'),
            $cert->student_name, $cert->course_name, $cert->folio, $verify_url
        );
        
        Aura_Notifications::send_whatsapp($student->phone_country . $student->phone, $message);
    }
}
```

---

### FASE 10 — Reportes

**Archivos**: `class-certificates-reports.php`, `templates/certificates/reports.php`

**Tipos de reportes** (misma arquitectura que `Aura_Students_Reports`):

| ID | Nombre | Columnas principales |
|----|--------|---------------------|
| `issued_by_period` | Certificados emitidos por período | Período, Cantidad, Plantilla más usada |
| `issued_by_course` | Certificados por curso/programa | Curso, Programa, Cantidad, Porcentaje graduados |
| `student_certificates` | Listado completo de certificados | Estudiante, Folio, Curso, Fecha, Estado, Firmantes |
| `revoked` | Certificados revocados | Folio, Estudiante, Fecha emisión, Fecha revocación, Razón |
| `pending_emit` | Estudiantes graduados sin certificado | Nombre, Curso, Fecha graduación, Días pendiente |

**Exportación**: Excel (PhpSpreadsheet) + PDF (mPDF)

**AJAX handlers**:
```php
add_action('wp_ajax_aura_cert_generate_report', [__CLASS__, 'ajax_generate_report']);
add_action('wp_ajax_aura_cert_export_excel',    [__CLASS__, 'ajax_export_excel']);
add_action('wp_ajax_aura_cert_export_pdf',      [__CLASS__, 'ajax_export_pdf']);
```

---

### FASE 11 — Configuración del Módulo

**Archivos**: `class-certificates-settings.php`, `templates/certificates/settings.php`

**Constante**: `const OPTION_KEY = 'aura_certificates_settings'`

**Opciones configurables**:

| Clave | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `folio_prefix` | string | `CEM` | Prefijo del folio (ej: `CEM`, `INST`, `DIP`) |
| `folio_padding` | int | `4` | Dígitos de relleno cero en el secuencial |
| `verify_slug` | string | `verificar-certificado` | Slug URL de verificación pública |
| `require_paz_salvo` | bool | `false` | Exigir paz y salvo para emitir |
| `default_send_email` | bool | `true` | Enviar email al emitir por defecto |
| `default_send_whatsapp` | bool | `false` | Enviar WhatsApp al emitir por defecto |
| `default_include_signatures` | bool | `true` | Incluir firmas por defecto |
| `cert_page_id` | int | `0` | ID de la página WP con `[aura_verificar_certificado]` |
| `pdf_dpi` | int | `150` | Resolución del PDF (150 para screen, 300 para impresión) |
| `max_active_signers` | int | `4` | Máximo firmantes activos simultáneos |

**Método helper estático** (igual que `Aura_Students_Settings`):
```php
public static function get( string $key, $fallback = null ) {
    $opts = get_option(self::OPTION_KEY, []);
    $all  = array_merge(self::defaults(), $opts);
    return $all[$key] ?? $fallback;
}
```

**Template `settings.php`** — 3 pestañas:
1. **General**: prefijo folio, padding, slug verificación, DPI PDF
2. **Emisión**: paz y salvo, defaults de email/WhatsApp/firmas
3. **Páginas y Shortcodes**: selector de página de verificación, instrucciones de uso de shortcodes

AJAX: `wp_ajax_aura_cert_save_settings` → `check_ajax_referer` + `aura_cert_settings` capability + `update_option`.

---

## 8. Hooks de Integración

### 8.1 Hooks que este módulo ESCUCHA

| Hook | Cuándo se dispara | Qué hace este módulo |
|------|------------------|---------------------|
| `aura_student_graduated($student_id, $enrollment_id)` | `Aura_Students_Enrollments::ajax_graduate_student()` | Guarda transient para habilitar botón "Emitir" en el perfil |
| `aura_student_deleted($student_id)` | Si se implementa en Estudiantes | Marca como revocados los certificados del estudiante |

### 8.2 Hooks que este módulo DISPARA

| Hook | Cuándo | Parámetros |
|------|--------|-----------|
| `aura_certificate_issued` | Al emitir exitosamente | `($certificate_id, $student_id, $folio)` |
| `aura_certificate_revoked` | Al revocar | `($certificate_id, $student_id, $folio, $reason)` |
| `aura_render_student_certificates_tab` | En el portal frontend (escuchado por este módulo mismo en `class-certificates-frontend.php`) | `($student_id, $wp_user_id)` |

### 8.3 Botón "Emitir Certificado" en el perfil del estudiante

En `templates/students/student-detail.php` (o donde esté la sección de inscripciones del estudiante), añadir en el tab de la inscripción `completed`:

```php
<?php
// Verificar si hay certificado pendiente de emisión para esta inscripción
$cert_ready     = absint(get_transient('aura_cert_ready_' . $student->id));
$already_issued = /* COUNT en aura_certificates WHERE student_id AND enrollment_id */;

if ( $enrollment->status === 'completed' && current_user_can('aura_cert_issue') ) :
    if ( $already_issued === 0 ) : ?>
        <button class="button button-primary aura-emit-cert-btn"
                data-student-id="<?php echo esc_attr($student->id); ?>"
                data-enrollment-id="<?php echo esc_attr($enrollment->id); ?>">
            🏅 <?php esc_html_e('Emitir Certificado', 'aura-suite'); ?>
        </button>
    <?php else : ?>
        <a href="<?php echo esc_url(/* link a certificado emitido */); ?>" class="button">
            📄 <?php esc_html_e('Ver Certificado Emitido', 'aura-suite'); ?>
        </a>
    <?php endif;
endif; ?>
```

---

## 9. Seguridad

### 9.1 Checklist de seguridad (OWASP Top 10)

| Riesgo | Mitigación implementada |
|--------|------------------------|
| **Broken Access Control** | `check_ajax_referer()` + `current_user_can()` en TODOS los handlers AJAX; nunca confiar en datos del cliente para determinar propietario |
| **SQL Injection** | Solo `$wpdb->prepare()` con placeholders `%s`, `%d`. Nunca concatenar strings en queries |
| **XSS** | `esc_html()`, `esc_attr()`, `esc_url()` en todos los outputs. `wp_kses_post()` para HTML permitido. `sanitize_text_field()` en inputs |
| **CSRF** | `wp_create_nonce()` + `check_ajax_referer()` en cada AJAX. Formularios admin con `wp_nonce_field()` |
| **Insecure File Upload** | Firmas: solo PNG, validar con `wp_check_filetype()` + verificar que el attachment pertenezca al sitio. PDFs generados por el servidor, nunca subidos por usuarios |
| **Path Traversal** | PDFs servidos por endpoint PHP que resuelve la ruta desde una constante base (`ABSPATH`), nunca desde input del usuario |
| **Information Disclosure** | Página de verificación pública solo expone datos que el beneficiario tiene derecho a que sean públicos. Nunca exponer IDs internos, paths, emails |
| **JSON Injection en design_json** | `json_decode()` → re-`json_encode()` al guardar plantillas, validar URLs embebidas |

### 9.2 Protección de PDFs generados

```
uploads/
└── aura-certificates/
    ├── .htaccess              ← "Deny from all" — bloquea acceso web directo
    ├── index.php              ← Archivo PHP vacío de silencio
    └── 2026/
        └── 01/
            └── cert-CEM-2026-0042.pdf
```

Los PDFs solo se sirven a través del endpoint `admin-ajax.php?action=aura_cert_download` que verifica permisos.

---

## 10. Integraciones con Otros Módulos

| Módulo | Integración |
|--------|-------------|
| **Estudiantes** | Fuente del beneficiario; hook `aura_student_graduated`; botón en perfil; datos para variables dinámicas |
| **Finanzas** | Requisito opcional de paz y salvo: verificar `balance_due = 0` en `aura_student_enrollments` antes de emitir |
| **Áreas** | `program_name` tomado de `wp_aura_areas.name` via `enrollment → course → area_id` |
| **Notificaciones globales** | `Aura_Notifications::send_email()` + `::send_whatsapp()` — NUNCA configurar SMTP en este módulo |
| **Configuración global** | `get_option('aura_org_name')` y `get_option('aura_org_logo_url')` para variables `{organizacion}` y `{logo_url}` |
| **Dashboard principal** | Tarjeta "Certificados" con KPIs: total emitidos, emitidos este mes, plantillas activas |

---

## 11. Assets y Dependencias Externas

### 11.1 JavaScript

| Archivo | Carga | Propósito |
|---------|-------|-----------|
| `fabric.min.js` v5.3.x | Solo en editor de plantillas | Canvas builder drag & drop |
| `certificate-builder.js` | Solo en editor de plantillas | Lógica del builder: toolbar, propiedades, guardado |
| `certificates.js` | Todas las páginas del módulo admin | Modales de emisión, revocación, listados |
| `certificate-verify.js` | Página pública de verificación | Animación y copia de enlace |

**Opciones para cargar Fabric.js**:
- **Opción A (recomendada para desarrollo)**: CDN `https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js`
- **Opción B (producción/privacidad)**: Descargar y colocar en `assets/js/vendor/fabric.min.js`

### 11.2 PHP / Composer

| Paquete | Versión | Uso | Ya en vendor |
|---------|---------|-----|-------------|
| `mpdf/mpdf` | `^8.x` | Generación de PDF | ✅ Sí |
| `phpoffice/phpspreadsheet` | `^1.x` | Exportación Excel | ✅ Sí |
| `chillerlan/php-qrcode` | `^4.3` | Generación de código QR | ❌ Añadir |

Para añadir `chillerlan/php-qrcode`, ejecutar en la raíz del proyecto:
```bash
composer require chillerlan/php-qrcode:^4.3
```

### 11.3 CSS

- `assets/css/certificates.css`: estilos del módulo admin
- Color primario: `#8b5cf6` (violeta — mismo que Estudiantes)
- Color oscuro: `#5b21b6`
- Fondo claro: `#f5f3ff`
- Seguir el mismo patrón de variables CSS del resto del plugin

---

## 12. Dashboard del Módulo

**Archivo**: `templates/certificates/dashboard.php`

**KPIs** (tarjetas de colores en la parte superior):

| Tarjeta | Query | Color |
|---------|-------|-------|
| Total Emitidos | `COUNT(*) FROM aura_certificates WHERE status='active'` | Violeta |
| Emitidos este mes | `COUNT(*) WHERE MONTH(issued_at)=current_month AND status='active'` | Azul |
| Plantillas activas | `COUNT(*) FROM aura_certificate_templates WHERE is_active=1` | Verde |
| Pendientes de emisión | `COUNT(students) WHERE status='graduated' AND no tiene certificado` | Naranja |
| Revocados | `COUNT(*) WHERE status='revoked'` | Rojo |

**Últimas emisiones** (tabla últimos 10):
- Folio, Estudiante, Curso, Fecha, Estado

**Accesos rápidos**:
- Botón "Emitir Certificado Individual"
- Botón "Emisión Masiva"
- Botón "Nueva Plantilla"

---

## 13. Reglas de Negocio

1. **Un estudiante puede tener múltiples certificados** (uno por cada inscripción completada, en cursos diferentes).
2. **No se pueden emitir duplicados**: si ya existe un certificado activo para `student_id + enrollment_id`, el sistema lo detecta y muestra el PDF existente en lugar de crear uno nuevo.
3. **El folio es permanente**: una vez generado, nunca se modifica aunque se revoque el certificado.
4. **Los certificados revocados no se eliminan**: se marcan con `status='revoked'` y siguen en BD para auditoría.
5. **Las plantillas con certificados emitidos no se pueden eliminar**: solo desactivar (`is_active=0`).
6. **La paz y salvo es opcional**: configurable en Fase 11. Si está activa, el sistema verifica `balance_due = 0` en la inscripción antes de permitir la emisión.
7. **El folio es único a nivel global**, no solo por año: el campo `UNIQUE` en la tabla lo garantiza.
8. **Máximo 4 firmantes activos simultáneos**: configurable en Fase 11 (default: 4).
9. **Las variables dinámicas se resuelven como snapshot**: los datos del certificado (nombre, curso, instructor, etc.) se copian al momento de emitir y no cambian si luego se edita el perfil del estudiante o el curso.
10. **Los PDFs se regeneran bajo demanda si se perdió el archivo**: si `pdf_path` apunta a un archivo inexistente, el endpoint de descarga regenera el PDF usando el snapshot de datos y la plantilla original.

---

## 14. Notas de Implementación

### mPDF — Generación de PDF

```php
$mpdf = new \Mpdf\Mpdf([
    'format'        => ($template->orientation === 'landscape') ? 'A4-L' : 'A4',
    'margin_top'    => 0,
    'margin_bottom' => 0,
    'margin_left'   => 0,
    'margin_right'  => 0,
    'dpi'           => Aura_Certificates_Settings::get('pdf_dpi', 150),
]);

// Insertar imagen PNG del canvas como página completa
$mpdf->AddPage();
$img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $canvas_png_base64));
$tmp      = tempnam(sys_get_temp_dir(), 'aura_cert_');
file_put_contents($tmp, $img_data);
$mpdf->Image($tmp, 0, 0, $template->width_mm, $template->height_mm, 'PNG');
unlink($tmp);

$pdf_content = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
```

### Fabric.js — Flujo canvas → servidor

1. JS: `const pngBase64 = canvas.toDataURL('image/png', 1.0);`
2. JS: POST al backend vía AJAX con el certificado: `{nonce, student_id, enrollment_id, template_id, png_data: pngBase64, signer_ids: [...], description: '...'}`
3. PHP: Validar nonce + permisos → decodificar PNG de base64 → guardar temporalmente → pasar a mPDF → guardar PDF permanentemente → registrar en BD → borrar temporal

### Verificación del `design_json` al guardar plantilla

```php
// Validar que sea JSON válido
$decoded = json_decode(wp_unslash($raw_json), true);
if ( json_last_error() !== JSON_ERROR_NONE ) {
    wp_send_json_error(__('El diseño contiene datos inválidos.', 'aura-suite'), 400);
}
// Sanitizar URLs dentro del JSON (no ejecutar como HTML)
array_walk_recursive($decoded, function(&$value) {
    if ( is_string($value) && filter_var($value, FILTER_VALIDATE_URL) ) {
        $value = esc_url_raw($value);
    }
});
$clean_json = wp_json_encode($decoded);
```

---

## 15. Orden de Implementación Recomendado

| # | Fase | Archivos principales | Resultado verificable |
|---|------|---------------------|-----------------------|
| 1 | Setup BD + capabilities + menús | `class-certificates-setup.php`, `class-certificates-admin.php` | Tablas creadas, menú visible en WP Admin |
| 2 | Editor de plantillas (Fabric.js) | `class-certificates-templates.php`, `certificate-builder.js`, `template-builder.php` | Crear y guardar una plantilla con variables |
| 3 | Firmantes | `class-certificates-signers.php`, `signers.php` | CRUD de firmantes, subir imagen de firma |
| 4 | Folio + emisión individual | `class-certificates-folio.php`, `class-certificates-issuer.php`, `issue-modal.php` | Emitir certificado a estudiante graduado, PDF generado |
| 5 | Verificación pública | `class-certificates-verify.php`, `verify-public.php` | URL pública muestra datos del certificado |
| 6 | Portal frontend (descarga) | `class-certificates-frontend.php`, `my-certificates.php` | Estudiante descarga su PDF desde el portal |
| 7 | Emisión masiva | `class-certificates-issuer.php` (métodos bulk), `bulk-issue.php` | Emitir 10+ certificados en background |
| 8 | Listado y revocación admin | `list.php` | Revocar certificado, ver historial completo |
| 9 | Notificaciones | `class-certificates-notifications.php` | Email con PDF adjunto al emitir |
| 10 | Reportes | `class-certificates-reports.php`, `reports.php` | Exportar Excel de certificados emitidos |
| 11 | Configuración | `class-certificates-settings.php`, `settings.php` | Cambiar prefijo folio, verificar que se aplica |

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