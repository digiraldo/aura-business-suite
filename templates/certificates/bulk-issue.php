<?php
/**
 * Template: Emisión Masiva
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pendientes de emitir
global $wpdb;
$enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
$students_table = $wpdb->prefix . 'aura_students';
$certs_table    = $wpdb->prefix . 'aura_certificates';
$courses_table  = $wpdb->prefix . 'aura_student_courses';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$pending = $wpdb->get_results(
    "SELECT e.id as enrollment_id, e.student_id, COALESCE(c.name, 'Programa General') as course_name, COALESCE(c.name, 'Programa General') as program_name, COALESCE(s.graduated_at, e.updated_at) as graduation_date,
            CONCAT(s.first_name,' ',s.last_name) as student_name, s.email
     FROM {$enroll_table} e
     INNER JOIN {$students_table} s ON e.student_id = s.id
     LEFT JOIN {$courses_table} c ON c.id = e.course_id
     WHERE (e.status = 'completed' OR s.status = 'graduated')
     AND NOT EXISTS (
         SELECT 1 FROM {$certs_table} cert WHERE cert.enrollment_id = e.id AND cert.status = 'active'
     )
     ORDER BY e.id DESC
     LIMIT 500"
);

$templates    = Aura_Certificates_Templates::get_active_templates_for_select();
$default_city = Aura_Certificates_Settings::get( 'default_city', '' );
?>
<div class="wrap aura-certificates-wrap" id="aura-bulk-issue-page">
    <h1 class="wp-heading-inline">📋 <?php esc_html_e( 'Emisión Masiva de Certificados', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <div class="aura-recipient-tabs" role="tablist" style="margin-bottom:16px;">
        <button type="button" class="aura-recipient-tab is-active" data-bulk-tab="students">
            <span class="dashicons dashicons-welcome-learn-more"></span>
            <?php esc_html_e( 'Estudiantes Graduados', 'aura-suite' ); ?>
            (<?php echo count( $pending ); ?>)
        </button>
        <button type="button" class="aura-recipient-tab" data-bulk-tab="counterparties">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'Usuarios WP y Terceros', 'aura-suite' ); ?>
        </button>
    </div>

    <!-- Configuración común de emisión masiva -->
    <div class="aura-card" style="max-width:960px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <label style="display:flex;align-items:center;gap:8px;">
                <strong><?php esc_html_e( 'Plantilla de Diseño:', 'aura-suite' ); ?></strong>
                <select id="aura-bulk-template-id" class="aura-input" style="min-width:240px;">
                    <option value="0"><?php esc_html_e( '— Usar predeterminada —', 'aura-suite' ); ?></option>
                    <?php foreach ( $templates as $tpl ) : ?>
                    <option value="<?php echo esc_attr( $tpl->id ); ?>" <?php echo $tpl->is_default ? 'selected' : ''; ?>>
                        <?php echo esc_html( $tpl->name ); ?>
                        <?php echo $tpl->is_default ? ' ★' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" id="aura-bulk-include-signatures" checked>
                    <span><?php esc_html_e( 'Incluir firmas', 'aura-suite' ); ?></span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" id="aura-bulk-send-email" checked>
                    <span><?php esc_html_e( 'Enviar por correo', 'aura-suite' ); ?></span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" id="aura-bulk-send-whatsapp">
                    <span><?php esc_html_e( 'Notificar WhatsApp', 'aura-suite' ); ?></span>
                </label>
            </div>
        </div>
    </div>

    <!-- TAB 1: Estudiantes graduados -->
    <div class="aura-bulk-tab-panel" data-bulk-panel="students">
        <?php if ( empty( $pending ) ) : ?>
            <div class="notice notice-success inline">
                <p><?php esc_html_e( '✅ Todos los estudiantes graduados ya tienen certificado emitido.', 'aura-suite' ); ?></p>
            </div>
        <?php else : ?>
        <div class="aura-card" style="max-width:960px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                <strong><?php printf(
                    esc_html__( '%d estudiante(s) pendiente(s) de certificado', 'aura-suite' ),
                    count( $pending )
                ); ?></strong>
                <div style="display:flex;gap:8px;">
                    <button type="button" id="aura-bulk-select-all" class="button button-small">
                        <?php esc_html_e( 'Seleccionar todos', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="aura-bulk-deselect-all" class="button button-small">
                        <?php esc_html_e( 'Deseleccionar', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="aura-bulk-check-all"></th>
                        <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Programa', 'aura-suite' ); ?></th>
                        <th style="width:120px;"><?php esc_html_e( 'Graduación', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $pending as $item ) : ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="aura-bulk-cert-check"
                                   data-student-id="<?php echo esc_attr( $item->student_id ); ?>"
                                   data-enrollment-id="<?php echo esc_attr( $item->enrollment_id ); ?>">
                        </td>
                        <td><?php echo esc_html( $item->student_name ); ?> <small style="color:#888;"><?php echo esc_html( $item->email ); ?></small></td>
                        <td><?php echo esc_html( $item->course_name ); ?></td>
                        <td><?php echo esc_html( $item->program_name ); ?></td>
                        <td><?php echo $item->graduation_date ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->graduation_date ) ) ) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
                <button type="button" id="aura-bulk-issue-btn" class="button button-primary" disabled>
                    <?php esc_html_e( 'Emitir Certificados Seleccionados', 'aura-suite' ); ?>
                </button>
                <span id="aura-bulk-selected-count" style="color:#666;"></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: Usuarios WP y Terceros -->
    <div class="aura-bulk-tab-panel" data-bulk-panel="counterparties" style="display:none;">
        <div class="aura-card" style="max-width:960px;margin-bottom:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e( 'Agregar Usuarios WP o Terceros al Lote', 'aura-suite' ); ?></h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
                <input type="text" id="aura-bulk-cp-search" class="aura-input" style="flex:1;min-width:260px;"
                       placeholder="<?php esc_attr_e( 'Buscar usuario WP o tercero para agregar a la lista…', 'aura-suite' ); ?>">
                <button type="button" id="aura-bulk-cp-explore" class="button button-secondary">
                    <span class="dashicons dashicons-groups" style="vertical-align:text-bottom;"></span>
                    <?php esc_html_e( 'Explorar y Agregar del Catálogo', 'aura-suite' ); ?>
                </button>
            </div>

            <div id="aura-bulk-cp-empty" class="notice notice-info inline">
                <p><?php esc_html_e( 'Aún no hay usuarios o terceros agregados al lote. Búscalos arriba para añadirlos.', 'aura-suite' ); ?></p>
            </div>

            <table id="aura-bulk-cp-table" class="wp-list-table widefat fixed striped" style="display:none;margin-bottom:16px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Correo', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Documento', 'aura-suite' ); ?></th>
                        <th style="width:70px;"></th>
                    </tr>
                </thead>
                <tbody id="aura-bulk-cp-tbody"></tbody>
            </table>

            <h3 style="margin-top:16px;padding-top:12px;border-top:1px solid #f1f5f9;">
                <?php esc_html_e( 'Datos Comunes del Certificado para este Lote', 'aura-suite' ); ?>
            </h3>
            <div class="aura-issue-grid-2">
                <label>
                    <span><?php esc_html_e( 'Curso / Evento / Reconocimiento', 'aura-suite' ); ?> *</span>
                    <input type="text" id="aura-bulk-cp-course" class="aura-input" style="width:100%;"
                           placeholder="<?php esc_attr_e( 'Ej: Jornada de Actualización 2026', 'aura-suite' ); ?>">
                </label>
                <label>
                    <span><?php esc_html_e( 'Programa / Área', 'aura-suite' ); ?></span>
                    <input type="text" id="aura-bulk-cp-program" class="aura-input" style="width:100%;">
                </label>
            </div>
            <div class="aura-issue-grid-3" style="margin-top:10px;">
                <label>
                    <span><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></span>
                    <input type="text" id="aura-bulk-cp-category" class="aura-input" style="width:100%;">
                </label>
                <label>
                    <span><?php esc_html_e( 'Duración / Horas', 'aura-suite' ); ?></span>
                    <input type="text" id="aura-bulk-cp-hours" class="aura-input" style="width:100%;">
                </label>
                <label>
                    <span><?php esc_html_e( 'Instructor / Ponente', 'aura-suite' ); ?></span>
                    <input type="text" id="aura-bulk-cp-instructor" class="aura-input" style="width:100%;">
                </label>
            </div>
            <div class="aura-issue-grid-2" style="margin-top:10px;">
                <label>
                    <span><?php esc_html_e( 'Ciudad de expedición', 'aura-suite' ); ?></span>
                    <input type="text" id="aura-bulk-cp-city" class="aura-input" style="width:100%;" value="<?php echo esc_attr( $default_city ); ?>">
                </label>
                <label>
                    <span><?php esc_html_e( 'Fecha de finalización', 'aura-suite' ); ?></span>
                    <input type="date" id="aura-bulk-cp-date" class="aura-input" style="width:100%;" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                </label>
            </div>
            <label style="display:block;margin-top:10px;">
                <span><?php esc_html_e( 'Descripción / Texto adicional', 'aura-suite' ); ?></span>
                <textarea id="aura-bulk-cp-description" rows="2" class="aura-input" style="width:100%;"></textarea>
            </label>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                <button type="button" id="aura-bulk-cp-issue-btn" class="button button-primary" disabled>
                    <?php esc_html_e( 'Emitir Lote a Usuarios / Terceros', 'aura-suite' ); ?>
                </button>
                <span id="aura-bulk-cp-count" style="color:#666;"></span>
            </div>
        </div>
    </div>

    <!-- Barra de progreso (oculta hasta que inicie el proceso) -->
    <div id="aura-bulk-progress-wrap" class="aura-card" style="max-width:650px;display:none;margin-top:16px;">
        <h3><?php esc_html_e( 'Procesando…', 'aura-suite' ); ?></h3>
        <div class="aura-progress-bar-wrap" style="height:12px;background:#e5e7eb;border-radius:6px;overflow:hidden;margin:8px 0;">
            <div id="aura-bulk-progress-bar" style="height:100%;width:0%;background:#8b5cf6;transition:width .4s;"></div>
        </div>
        <p id="aura-bulk-progress-text" style="color:#555;"></p>
    </div>
</div>
