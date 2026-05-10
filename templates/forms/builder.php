<?php
/**
 * Template — Builder / Editor de Formularios
 *
 * Panel de dos columnas: paleta de tipos de campo (izquierda) + zona de
 * construcción drag & drop (derecha) + panel lateral de config de cada campo.
 *
 * Este template es cargado tanto desde: admin.php?page=aura-forms-new
 * como desde: admin.php?page=aura-forms-list&action=edit&id=X
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_forms_create' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

$form_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

// Cargar datos del formulario si es edición
$form   = null;
$fields = [];
if ( $form_id ) {
    $form = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aura_forms WHERE id = %d AND deleted_at IS NULL",
        $form_id
    ) );
    if ( ! $form ) {
        wp_die( __( 'Formulario no encontrado.', 'aura-suite' ) );
    }
    $fields = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aura_form_fields WHERE form_id = %d ORDER BY sort_order ASC, id ASC",
        $form_id
    ) );
}

$page_title = $form_id
    ? sprintf( __( 'Editar: %s', 'aura-suite' ), esc_html( $form->title ) )
    : __( 'Nuevo Formulario', 'aura-suite' );

$back_url       = admin_url( 'admin.php?page=aura-forms-list' );
$form_public_url = $form ? Aura_Forms_Frontend::get_form_url( $form->slug ) : '';

// Áreas disponibles (para tipos enrollment / survey / feedback)
// Usa la tabla aura_areas (custom), no la taxonomía WP.
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
) ?? [];
?>
<div class="wrap aura-forms-wrap">

    <!-- Barra superior del builder -->
    <div class="aura-builder-header">
        <div class="aura-builder-header-left">
            <a href="<?php echo esc_url( $back_url ); ?>" class="aura-builder-back">
                ← <?php esc_html_e( 'Volver al listado', 'aura-suite' ); ?>
            </a>
            <h1 class="aura-builder-title"><?php echo esc_html( $page_title ); ?></h1>
        </div>
        <div class="aura-builder-header-right">
            <?php if ( $form_id && $form_public_url ) : ?>
            <a href="<?php echo esc_url( $form_public_url ); ?>" target="_blank" class="button">
                <?php esc_html_e( 'Vista previa', 'aura-suite' ); ?>
            </a>
            <?php endif; ?>
            <button type="button" id="aura-builder-save" class="button button-primary">
                <?php esc_html_e( 'Guardar formulario', 'aura-suite' ); ?>
            </button>
        </div>
    </div>

    <div id="aura-builder-notice" class="aura-builder-notice" style="display:none;"></div>

    <div class="aura-builder-layout">

        <!-- ═══════════════════════════════════════════════════════
             PANEL IZQUIERDO — Metadatos del formulario
        ════════════════════════════════════════════════════════ -->
        <div class="aura-builder-meta-panel" id="aura-meta-panel">

            <!-- ── Sección 1: Básico ── -->
            <details class="aura-meta-section" open>
                <summary class="aura-meta-section-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Información básica', 'aura-suite' ); ?>
                    <span class="aura-meta-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </summary>
                <div class="aura-meta-section-body">

                    <!-- Identidad Visual (Logo) -->
                    <div class="aura-field-row">
                        <label><?php esc_html_e( 'Identidad Visual (Logo)', 'aura-suite' ); ?></label>
                        <div class="aura-logo-picker">
                            <div class="aura-logo-preview" id="form-logo-preview"
                                 style="<?php echo $form && $form->logo_url ? '' : 'display:none;'; ?>">
                                <img id="form-logo-img"
                                     src="<?php echo esc_url( $form->logo_url ?? '' ); ?>"
                                     alt="<?php esc_attr_e( 'Logo del formulario', 'aura-suite' ); ?>"
                                     style="max-height:70px; max-width:200px; object-fit:contain; border-radius:4px;">
                                <button type="button" class="aura-logo-remove" id="form-logo-remove"
                                        title="<?php esc_attr_e( 'Quitar logo', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <input type="hidden" id="form-logo-url" name="logo_url"
                                   value="<?php echo esc_attr( $form->logo_url ?? '' ); ?>">
                            <button type="button" id="form-logo-btn" class="button button-secondary aura-logo-btn">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php esc_html_e( 'Seleccionar imagen', 'aura-suite' ); ?>
                            </button>
                        </div>
                        <small><?php esc_html_e( 'Recomendado: PNG transparente, 300×90 px o similar.', 'aura-suite' ); ?></small>
                    </div>

                    <div class="aura-field-row">
                        <label for="form-type"><?php esc_html_e( 'Tipo de formulario', 'aura-suite' ); ?></label>
                        <div class="aura-select-wrap">
                            <select id="form-type" name="type">
                                <option value="generic"    <?php selected( $form->type ?? '', 'generic' ); ?>><?php esc_html_e( 'Genérico', 'aura-suite' ); ?></option>
                                <option value="enrollment" <?php selected( $form->type ?? '', 'enrollment' ); ?>><?php esc_html_e( 'Inscripción a Curso', 'aura-suite' ); ?></option>
                                <option value="survey"     <?php selected( $form->type ?? '', 'survey' ); ?>><?php esc_html_e( 'Encuesta Asignada', 'aura-suite' ); ?></option>
                                <option value="feedback"   <?php selected( $form->type ?? '', 'feedback' ); ?>><?php esc_html_e( 'Encuesta Automática', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="aura-field-row">
                        <label for="form-company-name"><?php esc_html_e( 'Nombre de la Empresa / Organización', 'aura-suite' ); ?></label>
                        <input type="text" id="form-company-name" name="company_name"
                               value="<?php echo esc_attr( $form->company_name ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Ej. Centro de Educación Múltiple', 'aura-suite' ); ?>">
                        <small><?php esc_html_e( 'Aparece en la cabecera del formulario público.', 'aura-suite' ); ?></small>
                    </div>

                    <div class="aura-field-row">
                        <label for="form-title"><?php esc_html_e( 'Título del formulario', 'aura-suite' ); ?> <span class="aura-required-star">*</span></label>
                        <input type="text" id="form-title" name="title"
                               value="<?php echo esc_attr( $form->title ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Ej. Formulario de inscripción', 'aura-suite' ); ?>">
                    </div>

                    <div class="aura-field-row">
                        <label for="form-description"><?php esc_html_e( 'Descripción (opcional)', 'aura-suite' ); ?></label>
                        <textarea id="form-description" name="description" rows="3"
                                  placeholder="<?php esc_attr_e( 'Texto que verá el usuario antes de los campos…', 'aura-suite' ); ?>"><?php echo esc_textarea( $form->description ?? '' ); ?></textarea>
                    </div>

                    <!-- Área/Curso (visible solo para enrollment/survey/feedback) -->
                    <div class="aura-field-row aura-enrollment-fields" style="<?php echo ( ! $form || ! in_array( $form->type, [ 'enrollment', 'survey', 'feedback' ], true ) ) ? 'display:none' : ''; ?>">
                        <label for="form-area"><?php esc_html_e( 'Área / Programa', 'aura-suite' ); ?></label>
                        <div class="aura-select-wrap">
                            <select id="form-area" name="area_id">
                                <option value=""><?php esc_html_e( '— Todas las áreas —', 'aura-suite' ); ?></option>
                                <?php foreach ( $areas as $area ) : ?>
                                    <option value="<?php echo esc_attr( $area->id ); ?>"
                                            <?php selected( $form->area_id ?? '', $area->id ); ?>>
                                        <?php echo esc_html( $area->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="aura-field-row aura-enrollment-fields" style="<?php echo ( ! $form || ! in_array( $form->type, [ 'enrollment', 'survey', 'feedback' ], true ) ) ? 'display:none' : ''; ?>">
                        <label for="form-course"><?php esc_html_e( 'Curso específico', 'aura-suite' ); ?></label>
                        <div class="aura-select-wrap">
                            <select id="form-course" name="course_id">
                                <option value=""><?php esc_html_e( '— Sin curso fijo —', 'aura-suite' ); ?></option>
                                <?php
                                if ( $form && $form->course_id ) {
                                    $course = $wpdb->get_row( $wpdb->prepare(
                                        "SELECT id, name FROM {$wpdb->prefix}aura_student_courses WHERE id = %d",
                                        $form->course_id
                                    ) );
                                    if ( $course ) {
                                        echo '<option value="' . esc_attr( $course->id ) . '" selected>' . esc_html( $course->name ) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <small><?php esc_html_e( 'Se cargará al seleccionar área.', 'aura-suite' ); ?></small>
                    </div>

                </div>
            </details>

            <!-- ── Sección 2: Comportamiento ── -->
            <details class="aura-meta-section">
                <summary class="aura-meta-section-title">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php esc_html_e( 'Comportamiento', 'aura-suite' ); ?>
                    <span class="aura-meta-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </summary>
                <div class="aura-meta-section-body">

                    <div class="aura-toggles-grid">
                        <div class="aura-toggle-row">
                            <div class="aura-toggle-row-info">
                                <span class="aura-toggle-row-label"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></span>
                                <span class="aura-toggle-row-desc"><?php esc_html_e( 'Formulario activo y recibiendo respuestas', 'aura-suite' ); ?></span>
                            </div>
                            <label class="aura-toggle">
                                <input type="checkbox" name="is_active" value="1"
                                       <?php checked( $form->is_active ?? 1, 1 ); ?>>
                                <span class="aura-toggle-slider"></span>
                            </label>
                        </div>
                        <div class="aura-toggle-row">
                            <div class="aura-toggle-row-info">
                                <span class="aura-toggle-row-label"><?php esc_html_e( 'Requiere login', 'aura-suite' ); ?></span>
                                <span class="aura-toggle-row-desc"><?php esc_html_e( 'Solo usuarios autenticados', 'aura-suite' ); ?></span>
                            </div>
                            <label class="aura-toggle">
                                <input type="checkbox" name="requires_login" value="1"
                                       <?php checked( $form->requires_login ?? 0, 1 ); ?>>
                                <span class="aura-toggle-slider"></span>
                            </label>
                        </div>
                        <div class="aura-toggle-row">
                            <div class="aura-toggle-row-info">
                                <span class="aura-toggle-row-label"><?php esc_html_e( 'Múltiples envíos', 'aura-suite' ); ?></span>
                                <span class="aura-toggle-row-desc"><?php esc_html_e( 'Permite responder más de una vez', 'aura-suite' ); ?></span>
                            </div>
                            <label class="aura-toggle">
                                <input type="checkbox" name="accept_multiple" value="1"
                                       <?php checked( $form->accept_multiple ?? 0, 1 ); ?>>
                                <span class="aura-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="aura-field-row" style="margin-top:12px;">
                        <label for="form-close-date"><?php esc_html_e( 'Fecha de cierre', 'aura-suite' ); ?></label>
                        <input type="datetime-local" id="form-close-date" name="close_date"
                               value="<?php echo esc_attr( $form->close_date ?? '' ); ?>">
                        <small><?php esc_html_e( 'Dejar vacío para no tener límite de fecha.', 'aura-suite' ); ?></small>
                    </div>

                    <div class="aura-field-row">
                        <label for="form-max-subs"><?php esc_html_e( 'Máximo de respuestas', 'aura-suite' ); ?></label>
                        <input type="number" id="form-max-subs" name="max_submissions"
                               min="1"
                               value="<?php echo esc_attr( $form->max_submissions ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Sin límite', 'aura-suite' ); ?>">
                    </div>

                    <!-- Feedback: disparador automático -->
                    <div class="aura-feedback-fields" style="<?php echo ( ! $form || $form->type !== 'feedback' ) ? 'display:none' : ''; ?>">
                        <div class="aura-field-row">
                            <label for="form-auto-trigger"><?php esc_html_e( 'Disparador automático', 'aura-suite' ); ?></label>
                            <div class="aura-select-wrap">
                                <select id="form-auto-trigger" name="auto_assign_trigger">
                                    <option value="none"                   <?php selected( $form->auto_assign_trigger ?? 'none', 'none' ); ?>><?php esc_html_e( 'Ninguno', 'aura-suite' ); ?></option>
                                    <option value="on_enrollment_approved" <?php selected( $form->auto_assign_trigger ?? '', 'on_enrollment_approved' ); ?>><?php esc_html_e( 'Al aprobar inscripción', 'aura-suite' ); ?></option>
                                    <option value="on_course_complete"     <?php selected( $form->auto_assign_trigger ?? '', 'on_course_complete' ); ?>><?php esc_html_e( 'Al completar el curso', 'aura-suite' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aura-field-row">
                            <label for="form-auto-days"><?php esc_html_e( 'Días de espera tras disparador', 'aura-suite' ); ?></label>
                            <input type="number" id="form-auto-days" name="auto_assign_days"
                                   min="0" max="365"
                                   value="<?php echo esc_attr( $form->auto_assign_days ?? 0 ); ?>">
                            <small><?php esc_html_e( '0 = inmediatamente.', 'aura-suite' ); ?></small>
                        </div>
                    </div>

                </div>
            </details>

            <!-- ── Sección 3: Presentación ── -->
            <details class="aura-meta-section">
                <summary class="aura-meta-section-title">
                    <span class="dashicons dashicons-art"></span>
                    <?php esc_html_e( 'Presentación', 'aura-suite' ); ?>
                    <span class="aura-meta-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </summary>
                <div class="aura-meta-section-body">

                    <div class="aura-field-row">
                        <label for="form-submit-label"><?php esc_html_e( 'Texto del botón Enviar', 'aura-suite' ); ?></label>
                        <input type="text" id="form-submit-label" name="submit_button_label"
                               value="<?php echo esc_attr( $form->submit_button_label ?? __( 'Enviar', 'aura-suite' ) ); ?>">
                    </div>

                    <div class="aura-field-row">
                        <label for="form-success"><?php esc_html_e( 'Mensaje de éxito', 'aura-suite' ); ?></label>
                        <textarea id="form-success" name="success_message" rows="2"
                                  placeholder="<?php esc_attr_e( 'Gracias por tu respuesta…', 'aura-suite' ); ?>"><?php echo esc_textarea( $form->success_message ?? '' ); ?></textarea>
                    </div>

                    <div class="aura-field-row">
                        <label for="form-redirect"><?php esc_html_e( 'URL de redirección (opcional)', 'aura-suite' ); ?></label>
                        <input type="url" id="form-redirect" name="redirect_url"
                               value="<?php echo esc_attr( $form->redirect_url ?? '' ); ?>"
                               placeholder="https://…">
                    </div>

                    <div class="aura-field-row">
                        <label for="form-primary-color"><?php esc_html_e( 'Color principal', 'aura-suite' ); ?></label>
                        <div class="aura-color-row">
                            <input type="color" id="form-primary-color" name="primary_color"
                                   value="<?php echo esc_attr( $form->primary_color ?? '#2563eb' ); ?>">
                            <span class="aura-color-swatches">
                                <button type="button" class="aura-swatch" data-color="#2563eb" style="background:#2563eb;" title="Azul"></button>
                                <button type="button" class="aura-swatch" data-color="#7c3aed" style="background:#7c3aed;" title="Violeta"></button>
                                <button type="button" class="aura-swatch" data-color="#16a34a" style="background:#16a34a;" title="Verde"></button>
                                <button type="button" class="aura-swatch" data-color="#dc2626" style="background:#dc2626;" title="Rojo"></button>
                                <button type="button" class="aura-swatch" data-color="#d97706" style="background:#d97706;" title="Ámbar"></button>
                                <button type="button" class="aura-swatch" data-color="#0e7490" style="background:#0e7490;" title="Cian"></button>
                            </span>
                        </div>
                    </div>

                </div>
            </details>

            <!-- ── Sección 4: Notificaciones ── -->
            <details class="aura-meta-section">
                <summary class="aura-meta-section-title">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e( 'Notificaciones', 'aura-suite' ); ?>
                    <span class="aura-meta-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </summary>
                <div class="aura-meta-section-body">

                    <div class="aura-field-row">
                        <label for="form-notify-emails"><?php esc_html_e( 'Notificar al admin (emails)', 'aura-suite' ); ?></label>
                        <input type="text" id="form-notify-emails" name="notify_admin_emails"
                               value="<?php echo esc_attr( $form->notify_admin_emails ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'email1@..., email2@...', 'aura-suite' ); ?>">
                        <small><?php esc_html_e( 'Separar con comas si son varios.', 'aura-suite' ); ?></small>
                    </div>

                    <div class="aura-toggles-grid">
                        <div class="aura-toggle-row">
                            <div class="aura-toggle-row-info">
                                <span class="aura-toggle-row-label"><?php esc_html_e( 'Notificar al que envía', 'aura-suite' ); ?></span>
                                <span class="aura-toggle-row-desc"><?php esc_html_e( 'Envía confirmación por email', 'aura-suite' ); ?></span>
                            </div>
                            <label class="aura-toggle">
                                <input type="checkbox" name="notify_submitter" value="1"
                                       <?php checked( $form->notify_submitter ?? 0, 1 ); ?>>
                                <span class="aura-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                </div>
            </details>

        </div><!-- .aura-builder-meta-panel -->

        <!-- ═══════════════════════════════════════════════════════
             PANEL CENTRAL — Constructor de campos
        ════════════════════════════════════════════════════════ -->
        <div class="aura-builder-main">

            <!-- Paleta de tipos de campo -->
            <div class="aura-field-palette">
                <div class="aura-palette-header">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <h3><?php esc_html_e( 'Agregar campo', 'aura-suite' ); ?></h3>
                    <small><?php esc_html_e( 'Haz clic en el tipo deseado', 'aura-suite' ); ?></small>
                </div>
                <div class="aura-palette-groups">
                    <?php
                    $groups = [
                        'basic'    => __( 'Básicos',        'aura-suite' ),
                        'choice'   => __( 'Selección',      'aura-suite' ),
                        'media'    => __( 'Archivos',       'aura-suite' ),
                        'legal'    => __( 'Términos',       'aura-suite' ),
                        'advanced' => __( 'Avanzados',      'aura-suite' ),
                        'layout'   => __( 'Presentación',   'aura-suite' ),
                    ];
                    $field_types = [
                        [ 'type' => 'text',              'label' => __( 'Texto Corto',              'aura-suite' ), 'icon' => 'dashicons-editor-textcolor',  'group' => 'basic' ],
                        [ 'type' => 'textarea',          'label' => __( 'Párrafo',                  'aura-suite' ), 'icon' => 'dashicons-text-page',          'group' => 'basic' ],
                        [ 'type' => 'email',             'label' => __( 'Correo Electrónico',       'aura-suite' ), 'icon' => 'dashicons-email-alt',          'group' => 'basic' ],
                        [ 'type' => 'tel',               'label' => __( 'Teléfono',                 'aura-suite' ), 'icon' => 'dashicons-phone',              'group' => 'basic' ],
                        [ 'type' => 'number',            'label' => __( 'Número',                   'aura-suite' ), 'icon' => 'dashicons-calculator',         'group' => 'basic' ],
                        [ 'type' => 'date',              'label' => __( 'Fecha',                    'aura-suite' ), 'icon' => 'dashicons-calendar-alt',       'group' => 'basic' ],
                        [ 'type' => 'time',              'label' => __( 'Hora',                     'aura-suite' ), 'icon' => 'dashicons-clock',              'group' => 'basic' ],
                        [ 'type' => 'birthdate',         'label' => __( 'Fecha de Nacimiento',      'aura-suite' ), 'icon' => 'dashicons-universal-access',   'group' => 'basic' ],
                        [ 'type' => 'radio',             'label' => __( 'Opción única (radio)',     'aura-suite' ), 'icon' => 'dashicons-controls-play',      'group' => 'choice' ],
                        [ 'type' => 'checkbox',          'label' => __( 'Casillas (múltiple)',      'aura-suite' ), 'icon' => 'dashicons-yes-alt',            'group' => 'choice' ],
                        [ 'type' => 'select',            'label' => __( 'Desplegable',              'aura-suite' ), 'icon' => 'dashicons-arrow-down-alt2',    'group' => 'choice' ],
                        [ 'type' => 'scale',             'label' => __( 'Escala (NPS / Likert)',    'aura-suite' ), 'icon' => 'dashicons-star-filled',        'group' => 'choice' ],
                        [ 'type' => 'file',              'label' => __( 'Cargar Documento',         'aura-suite' ), 'icon' => 'dashicons-upload',             'group' => 'media' ],
                        [ 'type' => 'image',             'label' => __( 'Imagen decorativa',        'aura-suite' ), 'icon' => 'dashicons-format-image',       'group' => 'media' ],
                        [ 'type' => 'downloadable',      'label' => __( 'Descargar Documento',      'aura-suite' ), 'icon' => 'dashicons-download',           'group' => 'media' ],
                        [ 'type' => 'terms',             'label' => __( 'Términos (aceptar/negar)', 'aura-suite' ), 'icon' => 'dashicons-shield',             'group' => 'legal' ],
                        [ 'type' => 'accept_only_terms', 'label' => __( 'Términos (solo aceptar)',  'aura-suite' ), 'icon' => 'dashicons-shield-alt',         'group' => 'legal' ],
                        [ 'type' => 'hidden',            'label' => __( 'Campo Oculto',             'aura-suite' ), 'icon' => 'dashicons-hidden',             'group' => 'advanced' ],
                        [ 'type' => 'section_title',     'label' => __( 'Título de Sección',        'aura-suite' ), 'icon' => 'dashicons-editor-bold',        'group' => 'layout' ],
                        [ 'type' => 'paragraph',         'label' => __( 'Texto Explicativo',        'aura-suite' ), 'icon' => 'dashicons-editor-alignleft',   'group' => 'layout' ],
                    ];

                    foreach ( $groups as $group_key => $group_label ) :
                        $group_fields = array_filter( $field_types, fn( $f ) => $f['group'] === $group_key );
                        if ( empty( $group_fields ) ) continue;
                    ?>
                    <div class="aura-palette-group">
                        <h4><?php echo esc_html( $group_label ); ?></h4>
                        <div class="aura-palette-items">
                            <?php foreach ( $group_fields as $ft ) : ?>
                            <button type="button"
                                    class="aura-palette-item"
                                    data-type="<?php echo esc_attr( $ft['type'] ); ?>"
                                    title="<?php echo esc_attr( $ft['label'] ); ?>">
                                <span class="dashicons <?php echo esc_attr( $ft['icon'] ); ?>"></span>
                                <span><?php echo esc_html( $ft['label'] ); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div><!-- .aura-field-palette -->

            <!-- Canvas de campos (zona drag & drop) -->
            <div class="aura-canvas-wrapper">
                <div class="aura-canvas-header">
                    <div>
                        <h2><?php esc_html_e( 'Campos del formulario', 'aura-suite' ); ?></h2>
                        <p class="aura-canvas-hint">
                            <span class="dashicons dashicons-move"></span>
                            <?php esc_html_e( 'Arrastra para reordenar · haz clic en ✎ para editar', 'aura-suite' ); ?>
                        </p>
                    </div>
                    <span class="aura-fields-count" id="aura-fields-count">
                        <?php echo count( $fields ); ?> <?php esc_html_e( 'campos', 'aura-suite' ); ?>
                    </span>
                </div>

                <!-- Banner solo visible en formularios de tipo Inscripción -->
                <div id="aura-enrollment-defaults-banner"
                     class="aura-enrollment-defaults-banner"
                     style="<?php echo ( $form && $form->type === 'enrollment' ) ? '' : 'display:none;'; ?>">
                    <div class="aura-enrollment-defaults-info">
                        <span class="dashicons dashicons-groups"></span>
                        <div>
                            <strong><?php esc_html_e( 'Formulario de Inscripción', 'aura-suite' ); ?></strong>
                            <span><?php esc_html_e( 'Los campos mapeados se sincronizan automáticamente con el Módulo de Estudiantes al enviar.', 'aura-suite' ); ?></span>
                        </div>
                    </div>
                    <button type="button" id="aura-insert-enrollment-defaults" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Insertar campos predeterminados', 'aura-suite' ); ?>
                    </button>
                </div>
            <div id="aura-fields-canvas" class="aura-fields-canvas" data-form-id="<?php echo esc_attr( $form_id ); ?>">
                <?php if ( empty( $fields ) ) : ?>
                <div class="aura-canvas-empty" id="aura-canvas-empty">
                    <span class="dashicons dashicons-feedback"></span>
                    <p><?php esc_html_e( 'Aún no hay campos. Haz clic en un tipo para agregar el primero.', 'aura-suite' ); ?></p>
                </div>
                <?php else : ?>
                    <?php foreach ( $fields as $field ) :
                        $options = [];
                        if ( ! empty( $field->options_json ) ) {
                            $decoded = json_decode( $field->options_json, true );
                            if ( json_last_error() === JSON_ERROR_NONE ) $options = $decoded;
                        }
                    ?>
                    <div class="aura-field-item" data-field-id="<?php echo esc_attr( $field->id ); ?>" data-type="<?php echo esc_attr( $field->field_type ); ?>">
                        <div class="aura-field-item-handle" title="<?php esc_attr_e( 'Arrastrar para reordenar', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <span class="aura-field-type-badge aura-type-<?php echo esc_attr( $field->field_type ); ?>"><?php echo esc_html( $field->field_type ); ?></span>
                        <div class="aura-field-item-info">
                            <strong class="aura-field-item-label"><?php echo esc_html( $field->label ?: '(' . $field->field_type . ')' ); ?></strong>
                            <span class="aura-field-item-meta">
                                <?php if ( $field->is_required ) : ?>
                                    <span class="aura-field-required-dot" title="<?php esc_attr_e( 'Campo requerido', 'aura-suite' ); ?>">● <?php esc_html_e( 'Requerido', 'aura-suite' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $field->mapping_key ) : ?>
                                    <code><?php echo esc_html( $field->mapping_key ); ?></code>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="aura-field-item-actions">
                            <button type="button" class="aura-icon-btn aura-field-edit"
                                    data-field-id="<?php echo esc_attr( $field->id ); ?>"
                                    title="<?php esc_attr_e( 'Editar campo', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="aura-icon-btn aura-icon-btn--danger aura-field-delete"
                                    data-field-id="<?php echo esc_attr( $field->id ); ?>"
                                    title="<?php esc_attr_e( 'Eliminar campo', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div><!-- #aura-fields-canvas -->
            </div><!-- .aura-canvas-wrapper -->

        </div><!-- .aura-builder-main -->

    </div><!-- .aura-builder-layout -->

</div><!-- .aura-forms-wrap -->

<!-- ═══════════════════════════════════════════════════════════════
     MODAL / PANEL LATERAL — Configuración de cada campo
════════════════════════════════════════════════════════════════ -->
<div id="aura-field-config-overlay" class="aura-field-config-overlay" style="display:none;">
    <div class="aura-field-config-panel">
        <div class="aura-field-config-header">
            <h3 id="aura-config-panel-title"><?php esc_html_e( 'Configurar campo', 'aura-suite' ); ?></h3>
            <button type="button" id="aura-field-config-close" class="aura-panel-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <div class="aura-field-config-body">
            <input type="hidden" id="config-field-id"   name="field_id" value="">
            <input type="hidden" id="config-field-type" name="field_type" value="">
            <input type="hidden" id="config-form-id"    name="form_id"  value="<?php echo esc_attr( $form_id ); ?>">

            <!-- Etiqueta (todos los tipos excepto section_title/paragraph usando label como contenido) -->
            <div class="aura-field-row config-row-label">
                <label for="config-label"><?php esc_html_e( 'Etiqueta / Pregunta', 'aura-suite' ); ?></label>
                <input type="text" id="config-label" name="label" placeholder="<?php esc_attr_e( 'Texto visible al usuario', 'aura-suite' ); ?>">
            </div>

            <!-- Descripción de ayuda -->
            <div class="aura-field-row config-row-description">
                <label for="config-description"><?php esc_html_e( 'Texto de ayuda (opcional)', 'aura-suite' ); ?></label>
                <input type="text" id="config-description" name="description" placeholder="<?php esc_attr_e( 'Aparece como tooltip junto al campo', 'aura-suite' ); ?>">
            </div>

            <!-- Requerido (todos menos section_title, paragraph, image, downloadable) -->
            <div class="aura-field-row aura-field-row--inline config-row-required">
                <label><?php esc_html_e( 'Campo requerido', 'aura-suite' ); ?></label>
                <label class="aura-toggle">
                    <input type="checkbox" id="config-required" name="is_required" value="1">
                    <span class="aura-toggle-slider"></span>
                </label>
            </div>

            <!-- Placeholder (text, email, tel, number, textarea) -->
            <div class="aura-field-row config-row-placeholder" style="display:none;">
                <label for="config-placeholder"><?php esc_html_e( 'Placeholder', 'aura-suite' ); ?></label>
                <input type="text" id="config-placeholder" name="placeholder">
            </div>

            <!-- Valor predeterminado (text, email, tel, number, date, hidden) -->
            <div class="aura-field-row config-row-default" style="display:none;">
                <label for="config-default"><?php esc_html_e( 'Valor predeterminado', 'aura-suite' ); ?></label>
                <input type="text" id="config-default" name="default_value">
            </div>

            <!-- Opciones (radio, checkbox, select) — una por línea -->
            <div class="aura-field-row config-row-options" style="display:none;">
                <label for="config-options"><?php esc_html_e( 'Opciones (una por línea)', 'aura-suite' ); ?></label>
                <textarea id="config-options" name="options_raw" rows="5"
                          placeholder="<?php esc_attr_e( "Opción 1\nOpción 2\nOpción 3", 'aura-suite' ); ?>"></textarea>
            </div>

            <!-- Select múltiple (solo select) -->
            <div class="aura-field-row aura-field-row--inline config-row-multiple-select" style="display:none;">
                <label><?php esc_html_e( 'Selección múltiple', 'aura-suite' ); ?></label>
                <label class="aura-toggle">
                    <input type="checkbox" id="config-multiple-select" name="multiple_select" value="1">
                    <span class="aura-toggle-slider"></span>
                </label>
            </div>

            <!-- Incluir opción "Otro" (radio, select) -->
            <div class="aura-field-row aura-field-row--inline config-row-has-other" style="display:none;">
                <label><?php esc_html_e( 'Incluir opción "Otro"', 'aura-suite' ); ?></label>
                <label class="aura-toggle">
                    <input type="checkbox" id="config-has-other" name="has_other" value="1">
                    <span class="aura-toggle-slider"></span>
                </label>
            </div>

            <!-- Escala Max Value (scale) -->
            <div class="aura-field-row config-row-scale-max" style="display:none;">
                <label for="config-max-value"><?php esc_html_e( 'Valor máximo de la escala', 'aura-suite' ); ?></label>
                <select id="config-max-value" name="max_value">
                    <option value="5">5</option>
                    <option value="7">7</option>
                    <option value="10">10</option>
                </select>
            </div>

            <!-- Min / Max para campos número -->
            <div class="aura-field-row config-row-minmax" style="display:none;">
                <div class="aura-inline-fields">
                    <div>
                        <label for="config-min"><?php esc_html_e( 'Mínimo', 'aura-suite' ); ?></label>
                        <input type="number" id="config-min" name="min_value">
                    </div>
                    <div>
                        <label for="config-max"><?php esc_html_e( 'Máximo', 'aura-suite' ); ?></label>
                        <input type="number" id="config-max" name="max_value">
                    </div>
                </div>
            </div>

            <!-- Extensiones y tamaño para file -->
            <div class="aura-field-row config-row-file" style="display:none;">
                <label for="config-extensions"><?php esc_html_e( 'Extensiones permitidas', 'aura-suite' ); ?></label>
                <input type="text" id="config-extensions" name="allowed_extensions"
                       placeholder="pdf,doc,docx,jpg,jpeg,png">
                <label for="config-file-size" style="margin-top:8px;"><?php esc_html_e( 'Tamaño máx. (KB)', 'aura-suite' ); ?></label>
                <input type="number" id="config-file-size" name="max_file_size_kb" min="1" placeholder="5120">
            </div>

            <!-- Imagen URL (image) -->
            <div class="aura-field-row config-row-image" style="display:none;">
                <label for="config-image-url"><?php esc_html_e( 'URL de la imagen', 'aura-suite' ); ?></label>
                <input type="url" id="config-image-url" name="image_url"
                       placeholder="https://…">
                <button type="button" class="button aura-media-picker" data-target="config-image-url">
                    <?php esc_html_e( 'Seleccionar de biblioteca', 'aura-suite' ); ?>
                </button>
            </div>

            <!-- Archivo/URL descargable (downloadable) -->
            <div class="aura-field-row config-row-downloadable" style="display:none;">
                <label><?php esc_html_e( 'Archivo a descargar', 'aura-suite' ); ?></label>
                <div class="aura-downloadable-tabs">
                    <button type="button" class="aura-dl-tab active" data-tab="upload"><?php esc_html_e( 'Subir archivo', 'aura-suite' ); ?></button>
                    <button type="button" class="aura-dl-tab" data-tab="url"><?php esc_html_e( 'URL externa', 'aura-suite' ); ?></button>
                </div>
                <div class="aura-dl-panel" id="aura-dl-upload">
                    <input type="text" id="config-file-uploaded" name="file_uploaded"
                           placeholder="<?php esc_attr_e( 'Ruta del archivo en uploads', 'aura-suite' ); ?>">
                    <button type="button" class="button aura-media-picker" data-target="config-file-uploaded">
                        <?php esc_html_e( 'Seleccionar de biblioteca', 'aura-suite' ); ?>
                    </button>
                </div>
                <div class="aura-dl-panel" id="aura-dl-url" style="display:none;">
                    <input type="url" id="config-file-url" name="file_url"
                           placeholder="https://…">
                </div>
                <label for="config-instructions" style="margin-top:8px;"><?php esc_html_e( 'Instrucciones (opcional)', 'aura-suite' ); ?></label>
                <textarea id="config-instructions" name="instructions" rows="2"></textarea>
            </div>

            <!-- Texto de términos (terms, accept_only_terms) -->
            <div class="aura-field-row config-row-terms" style="display:none;">
                <label for="config-terms-text"><?php esc_html_e( 'Texto de términos y condiciones', 'aura-suite' ); ?></label>
                <textarea id="config-terms-text" name="terms_text" rows="4"
                          placeholder="<?php esc_attr_e( 'HTML permitido', 'aura-suite' ); ?>"></textarea>
            </div>

            <!-- Mensaje de desacuerdo (solo terms, no accept_only_terms) -->
            <div class="aura-field-row config-row-disagreement" style="display:none;">
                <label for="config-disagreement"><?php esc_html_e( 'Mensaje al mostrar desacuerdo', 'aura-suite' ); ?></label>
                <input type="text" id="config-disagreement" name="disagreement_message"
                       placeholder="<?php esc_attr_e( 'Ej. Necesitas aceptar para continuar', 'aura-suite' ); ?>">
            </div>

            <!-- Mapeo a campos de Estudiantes (solo en form type=enrollment) -->
            <div class="aura-field-row config-row-mapping aura-enrollment-mapping" style="display:none;">
                <label for="config-mapping"><?php esc_html_e( 'Mapeo a campo de inscripción', 'aura-suite' ); ?></label>
                <select id="config-mapping" name="mapping_key">
                    <option value=""><?php esc_html_e( '— Sin mapeo —', 'aura-suite' ); ?></option>
                    <optgroup label="<?php esc_attr_e( 'Datos Personales', 'aura-suite' ); ?>">
                        <option value="first_name"><?php esc_html_e( 'Nombre(s)', 'aura-suite' ); ?></option>
                        <option value="last_name"><?php esc_html_e( 'Apellido(s)', 'aura-suite' ); ?></option>
                        <option value="email"><?php esc_html_e( 'Correo electrónico', 'aura-suite' ); ?></option>
                        <option value="phone"><?php esc_html_e( 'Teléfono', 'aura-suite' ); ?></option>
                        <option value="birthdate"><?php esc_html_e( 'Fecha de nacimiento', 'aura-suite' ); ?></option>
                        <option value="gender"><?php esc_html_e( 'Género', 'aura-suite' ); ?></option>
                        <option value="city"><?php esc_html_e( 'Ciudad', 'aura-suite' ); ?></option>
                        <option value="country"><?php esc_html_e( 'País', 'aura-suite' ); ?></option>
                        <option value="id_number"><?php esc_html_e( 'Número de identificación', 'aura-suite' ); ?></option>
                        <option value="address"><?php esc_html_e( 'Dirección', 'aura-suite' ); ?></option>
                    </optgroup>
                    <optgroup label="<?php esc_attr_e( 'Postulación', 'aura-suite' ); ?>">
                        <option value="course_id"><?php esc_html_e( 'Curso al que se postula', 'aura-suite' ); ?></option>
                        <option value="area_id"><?php esc_html_e( 'Área / Programa', 'aura-suite' ); ?></option>
                        <option value="motivation"><?php esc_html_e( 'Motivación / Por qué se postula', 'aura-suite' ); ?></option>
                        <option value="notes"><?php esc_html_e( 'Observaciones adicionales', 'aura-suite' ); ?></option>
                    </optgroup>
                </select>
                <small><?php esc_html_e( 'Al enviar el formulario, este dato se copia automáticamente al perfil del estudiante.', 'aura-suite' ); ?></small>
            </div>

        </div><!-- .aura-field-config-body -->

        <div class="aura-field-config-footer">
            <button type="button" id="aura-field-config-cancel" class="button">
                <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-field-config-save" class="button button-primary">
                <?php esc_html_e( 'Guardar campo', 'aura-suite' ); ?>
            </button>
        </div>
    </div><!-- .aura-field-config-panel -->
</div><!-- #aura-field-config-overlay -->

<script>
// Palomitas de color del builder
(function(){
    document.querySelectorAll('.aura-swatch').forEach(function(btn){
        btn.addEventListener('click', function(){
            var color = this.dataset.color;
            var picker = document.getElementById('form-primary-color');
            if(picker){ picker.value = color; }
        });
    });
})();
</script>

<?php /* ── Logo picker — WP Media Library ─────────────────────────── */ ?>
<script>
(function($){
    var frame;
    var $btn     = $('#form-logo-btn');
    var $remove  = $('#form-logo-remove');
    var $input   = $('#form-logo-url');
    var $preview = $('#form-logo-preview');
    var $img     = $('#form-logo-img');

    $btn.on('click', function(e){
        e.preventDefault();
        if ( frame ) { frame.open(); return; }
        frame = wp.media({
            title:    '<?php esc_html_e( 'Seleccionar logo', 'aura-suite' ); ?>',
            button:   { text: '<?php esc_html_e( 'Usar como logo', 'aura-suite' ); ?>' },
            multiple: false,
            library:  { type: 'image' }
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;
            $input.val( url );
            $img.attr('src', url);
            $preview.show();
        });
        frame.open();
    });

    $remove.on('click', function(e){
        e.preventDefault();
        $input.val('');
        $img.attr('src','');
        $preview.hide();
    });
})(jQuery);
</script>
