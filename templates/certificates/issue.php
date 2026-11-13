<?php
/**
 * Template: Emitir Certificado Individual
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para emitir certificados.', 'aura-suite' ) );
}

global $wpdb;

$templates   = Aura_Certificates_Templates::get_active_templates_for_select();
$signers     = Aura_Certificates_Signers::get_active_signers_for_js();
$list_url    = admin_url( 'admin.php?page=aura-certificates-list' );

// Preselección por URL (por ejemplo desde ficha de estudiante)
$pre_student_id    = absint( $_GET['student_id'] ?? 0 );
$pre_enrollment_id = absint( $_GET['enrollment_id'] ?? 0 );
$pre_mode          = 'student';
$pre_student       = null;

if ( $pre_student_id > 0 || $pre_enrollment_id > 0 ) {
    $students_table = $wpdb->prefix . 'aura_students';
    $enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
    $courses_table  = $wpdb->prefix . 'aura_student_courses';

    if ( $pre_enrollment_id > 0 ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pre_student = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.id AS enrollment_id, s.id AS student_id, s.first_name, s.last_name, s.email, s.wp_user_id,
                    COALESCE(c.name, '') AS course_name, COALESCE(s.graduated_at, e.updated_at) AS graduation_date
             FROM {$enroll_table} e
             INNER JOIN {$students_table} s ON s.id = e.student_id
             LEFT JOIN {$courses_table} c ON c.id = e.course_id
             WHERE e.id = %d",
            $pre_enrollment_id
        ) );
    } elseif ( $pre_student_id > 0 ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pre_student = $wpdb->get_row( $wpdb->prepare(
            "SELECT 0 AS enrollment_id, s.id AS student_id, s.first_name, s.last_name, s.email, s.wp_user_id,
                    '' AS course_name, s.graduated_at AS graduation_date
             FROM {$students_table} s
             WHERE s.id = %d",
            $pre_student_id
        ) );
    }
}

$default_city = Aura_Certificates_Settings::get( 'default_city', '' );
?>
<div class="wrap aura-certificates-wrap" id="aura-cert-issue-page">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <h1 class="wp-heading-inline" style="margin:0;">🏅 <?php esc_html_e( 'Emitir Certificado Individual', 'aura-suite' ); ?></h1>
            <p class="description" style="margin:4px 0 0;">
                <?php esc_html_e( 'Emite certificados para estudiantes inscritos, usuarios de WordPress, terceros del catálogo o destinatarios manuales.', 'aura-suite' ); ?>
            </p>
        </div>
        <a href="<?php echo esc_url( $list_url ); ?>" class="button">
            ← <?php esc_html_e( 'Volver al listado', 'aura-suite' ); ?>
        </a>
    </div>

    <div id="aura-issue-result" style="display:none;margin-bottom:18px;"></div>

    <form id="aura-cert-issue-form" novalidate>
        <div class="aura-issue-layout">
            <!-- Columna principal -->
            <div class="aura-issue-main">

                <!-- 1. Tipo y Selector de Destinatario -->
                <div class="aura-card" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;display:flex;align-items:center;gap:8px;">
                        <span class="dashicons dashicons-admin-users" style="color:var(--cert-primary);"></span>
                        <?php esc_html_e( '1. Destinatario del Certificado', 'aura-suite' ); ?>
                    </h2>

                    <div class="aura-recipient-tabs" role="tablist">
                        <button type="button" class="aura-recipient-tab <?php echo $pre_mode === 'student' ? 'is-active' : ''; ?>" data-mode="student">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <?php esc_html_e( 'Estudiante / Matrícula', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-recipient-tab" data-mode="counterparty">
                            <span class="dashicons dashicons-groups"></span>
                            <?php esc_html_e( 'Usuario WP o Tercero', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-recipient-tab" data-mode="manual">
                            <span class="dashicons dashicons-edit"></span>
                            <?php esc_html_e( 'Captura Manual', 'aura-suite' ); ?>
                        </button>
                    </div>
                    <input type="hidden" id="aura-issue-recipient-mode" name="recipient_mode" value="<?php echo esc_attr( $pre_mode ); ?>">

                    <!-- Panel A: Estudiante -->
                    <div class="aura-recipient-panel" data-panel="student">
                        <label for="aura-issue-student-search" style="display:block;font-weight:600;margin-bottom:6px;">
                            <?php esc_html_e( 'Buscar estudiante por nombre o correo:', 'aura-suite' ); ?>
                        </label>
                        <div style="display:flex;gap:8px;position:relative;">
                            <input type="text" id="aura-issue-student-search" class="aura-input" style="flex:1;"
                                   placeholder="<?php esc_attr_e( 'Escribe al menos 2 letras para buscar estudiantes o matrículas…', 'aura-suite' ); ?>"
                                   value="<?php echo $pre_student ? esc_attr( trim( $pre_student->first_name . ' ' . $pre_student->last_name ) ) : ''; ?>">
                            <button type="button" id="aura-issue-student-clear" class="button" style="<?php echo $pre_student ? '' : 'display:none;'; ?>">
                                <?php esc_html_e( 'Cambiar', 'aura-suite' ); ?>
                            </button>
                        </div>
                        <div id="aura-issue-student-results" class="aura-search-dropdown" style="display:none;"></div>

                        <input type="hidden" id="aura-issue-student-id" name="student_id" value="<?php echo esc_attr( $pre_student->student_id ?? 0 ); ?>">
                        <input type="hidden" id="aura-issue-enrollment-id" name="enrollment_id" value="<?php echo esc_attr( $pre_student->enrollment_id ?? 0 ); ?>">

                        <div id="aura-issue-student-selected" class="aura-selected-chip" style="<?php echo $pre_student ? '' : 'display:none;'; ?>margin-top:10px;">
                            <?php if ( $pre_student ) : ?>
                                <strong><?php echo esc_html( trim( $pre_student->first_name . ' ' . $pre_student->last_name ) ); ?></strong>
                                <span>(<?php echo esc_html( $pre_student->email ); ?>)</span>
                                <?php if ( ! empty( $pre_student->course_name ) ) : ?>
                                    <span class="aura-cert-badge aura-cert-badge--info"><?php echo esc_html( $pre_student->course_name ); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Panel B: Usuario WP / Tercero -->
                    <div class="aura-recipient-panel" data-panel="counterparty" style="display:none;">
                        <label for="aura-issue-counterparty-search" style="display:block;font-weight:600;margin-bottom:6px;">
                            <?php esc_html_e( 'Seleccionar Usuario de WordPress o Tercero:', 'aura-suite' ); ?>
                        </label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <input type="text" id="aura-issue-counterparty-search" class="aura-input" style="flex:1;min-width:240px;"
                                   placeholder="<?php esc_attr_e( 'Buscar usuario WP, persona natural o empresa…', 'aura-suite' ); ?>">
                            <button type="button" id="aura-issue-counterparty-explore" class="button button-secondary">
                                <span class="dashicons dashicons-search" style="vertical-align:text-bottom;"></span>
                                <?php esc_html_e( 'Explorar Catálogo', 'aura-suite' ); ?>
                            </button>
                            <button type="button" id="aura-issue-counterparty-clear" class="button" style="display:none;">
                                <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                            </button>
                        </div>
                        <input type="hidden" id="aura-issue-counterparty-ref" value="">
                        <input type="hidden" id="aura-issue-wp-user-id" name="wp_user_id" value="0">
                        <input type="hidden" id="aura-issue-third-party-id" name="third_party_id" value="0">
                        <div id="aura-issue-counterparty-preview" class="aura-selected-chip" style="display:none;margin-top:10px;"></div>
                    </div>

                    <!-- Campos editables del destinatario (aplican siempre o en modo usuario/tercero/manual) -->
                    <div class="aura-issue-grid-3" style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;">
                        <label>
                            <span><?php esc_html_e( 'Nombre en el certificado', 'aura-suite' ); ?> <strong style="color:#dc2626;">*</strong></span>
                            <input type="text" id="aura-issue-recipient-name" name="recipient_name" class="aura-input" style="width:100%;"
                                   value="<?php echo $pre_student ? esc_attr( trim( $pre_student->first_name . ' ' . $pre_student->last_name ) ) : ''; ?>"
                                   placeholder="<?php esc_attr_e( 'Nombre completo tal como aparecerá en el PDF', 'aura-suite' ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Correo electrónico', 'aura-suite' ); ?></span>
                            <input type="email" id="aura-issue-recipient-email" name="recipient_email" class="aura-input" style="width:100%;"
                                   value="<?php echo $pre_student ? esc_attr( $pre_student->email ) : ''; ?>"
                                   placeholder="<?php esc_attr_e( 'correo@ejemplo.com', 'aura-suite' ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Documento / Cédula / NIT', 'aura-suite' ); ?></span>
                            <input type="text" id="aura-issue-recipient-document" name="recipient_document" class="aura-input" style="width:100%;"
                                   placeholder="<?php esc_attr_e( 'Opcional para {documento}', 'aura-suite' ); ?>">
                        </label>
                    </div>
                </div>

                <!-- 2. Datos Académicos y Variables -->
                <div class="aura-card">
                    <h2 style="margin-top:0;display:flex;align-items:center;gap:8px;">
                        <span class="dashicons dashicons-awards" style="color:var(--cert-primary);"></span>
                        <?php esc_html_e( '2. Detalles del Certificado', 'aura-suite' ); ?>
                    </h2>

                    <div class="aura-issue-grid-2">
                        <label>
                            <span><?php esc_html_e( 'Curso / Evento / Reconocimiento', 'aura-suite' ); ?> <strong style="color:#dc2626;">*</strong></span>
                            <input type="text" id="aura-issue-course-name" name="course_name" class="aura-input" style="width:100%;" required
                                   value="<?php echo $pre_student ? esc_attr( $pre_student->course_name ) : ''; ?>"
                                   placeholder="<?php esc_attr_e( 'Ej: Diplomado en Gestión Estratégica', 'aura-suite' ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Programa / Facultad / Área', 'aura-suite' ); ?></span>
                            <input type="text" id="aura-issue-program-name" name="program_name" class="aura-input" style="width:100%;"
                                   placeholder="<?php esc_attr_e( 'Ej: Escuela de Liderazgo', 'aura-suite' ); ?>">
                        </label>
                    </div>

                    <div class="aura-issue-grid-3" style="margin-top:12px;">
                        <label>
                            <span><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></span>
                            <input type="text" id="aura-issue-category" name="category" class="aura-input" style="width:100%;"
                                   placeholder="<?php esc_attr_e( 'Ej: Académico, Taller, Honor', 'aura-suite' ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Duración / Horas', 'aura-suite' ); ?></span>
                            <input type="text" id="aura-issue-hours" name="hours" class="aura-input" style="width:100%;"
                                   placeholder="<?php esc_attr_e( 'Ej: 40 horas', 'aura-suite' ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Calificación / Mención', 'aura-suite' ); ?></span>
                            <input type="text" id="aura-issue-grade" name="grade" class="aura-input" style="width:100%;"
                                   placeholder="<?php esc_attr_e( 'Ej: Aprobado / 98%', 'aura-suite' ); ?>">
                        </label>
                    </div>

                    <div class="aura-issue-grid-3" style="margin-top:12px;">
                        <label>
                            <span><?php esc_html_e( 'Instructor / Ponente', 'aura-suite' ); ?></span>
                            <input type="text" id="aura-issue-instructor" name="instructor_name" class="aura-input" style="width:100%;"
                                   placeholder="<?php esc_attr_e( 'Ej: Mgtr. Laura Méndez', 'aura-suite' ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Fecha de finalización', 'aura-suite' ); ?></span>
                            <input type="date" id="aura-issue-graduation-date" name="graduation_date" class="aura-input" style="width:100%;"
                                   value="<?php echo ! empty( $pre_student->graduation_date ) ? esc_attr( gmdate( 'Y-m-d', strtotime( $pre_student->graduation_date ) ) ) : esc_attr( current_time( 'Y-m-d' ) ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Ciudad de expedición', 'aura-suite' ); ?></span>
                            <input type="text" id="aura-issue-city" name="city" class="aura-input" style="width:100%;"
                                   value="<?php echo esc_attr( $default_city ); ?>"
                                   placeholder="<?php esc_attr_e( 'Ej: Bogotá / Madrid', 'aura-suite' ); ?>">
                        </label>
                    </div>

                    <label style="display:block;margin-top:12px;">
                        <span><?php esc_html_e( 'Descripción / Texto adicional ({descripcion})', 'aura-suite' ); ?></span>
                        <textarea id="aura-issue-description" name="description" rows="3" class="aura-input" style="width:100%;"
                                  placeholder="<?php esc_attr_e( 'Texto descriptivo opcional que se imprimirá en la variable {descripcion}…', 'aura-suite' ); ?>"></textarea>
                    </label>
                </div>
            </div>

            <!-- Columna lateral -->
            <div class="aura-issue-side">
                <div class="aura-card" style="margin-bottom:16px;">
                    <h2 style="margin-top:0;"><?php esc_html_e( '3. Plantilla y Salida', 'aura-suite' ); ?></h2>

                    <label style="display:block;">
                        <span style="font-weight:600;display:block;margin-bottom:6px;"><?php esc_html_e( 'Plantilla de Diseño', 'aura-suite' ); ?></span>
                        <select id="aura-issue-template-id" name="template_id" class="aura-input" style="width:100%;">
                            <option value="0" data-thumb=""><?php esc_html_e( '— Usar plantilla predeterminada —', 'aura-suite' ); ?></option>
                            <?php foreach ( $templates as $tpl ) : ?>
                            <option value="<?php echo esc_attr( $tpl->id ); ?>"
                                    data-thumb="<?php echo esc_url( $tpl->thumbnail_url ?? '' ); ?>"
                                    <?php selected( ! empty( $tpl->is_default ) ); ?>>
                                <?php echo esc_html( $tpl->name ); ?>
                                <?php echo ! empty( $tpl->is_default ) ? ' ★' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div id="aura-issue-tpl-preview" class="aura-issue-tpl-preview" style="margin-top:10px;display:none;">
                        <img src="" alt="<?php esc_attr_e( 'Vista previa de plantilla', 'aura-suite' ); ?>" style="width:100%;border-radius:6px;border:1px solid #e2e8f0;">
                    </div>

                    <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;display:flex;flex-direction:column;gap:8px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="aura-issue-include-signatures" name="include_signatures" value="1" checked>
                            <span>
                                <?php
                                printf(
                                    /* translators: %d firmantes activos */
                                    esc_html__( 'Incluir firmas activas (%d)', 'aura-suite' ),
                                    count( $signers )
                                );
                                ?>
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="aura-issue-send-email" name="send_email" value="1" checked>
                            <span><?php esc_html_e( 'Enviar por correo electrónico', 'aura-suite' ); ?></span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="aura-issue-send-whatsapp" name="send_whatsapp" value="1">
                            <span><?php esc_html_e( 'Notificar por WhatsApp (si está activo)', 'aura-suite' ); ?></span>
                        </label>
                    </div>

                    <div style="margin-top:18px;">
                        <button type="submit" id="aura-issue-submit-btn" class="button button-primary button-large" style="width:100%;justify-content:center;">
                            🏅 <?php esc_html_e( 'Emitir Certificado y Generar PDF', 'aura-suite' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php
// End of issue.php

