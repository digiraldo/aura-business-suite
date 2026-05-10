<?php
/**
 * Template: Emisión Masiva
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pendientes de emitir
global $wpdb;
$enroll_table   = $wpdb->prefix . 'aura_enrollments';
$students_table = $wpdb->prefix . 'aura_students';
$certs_table    = $wpdb->prefix . 'aura_certificates';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$pending = $wpdb->get_results(
    "SELECT e.id as enrollment_id, e.student_id, e.course_name, e.program_name, e.graduation_date,
            CONCAT(s.first_name,' ',s.last_name) as student_name, s.email
     FROM {$enroll_table} e
     INNER JOIN {$students_table} s ON e.student_id = s.id
     WHERE e.status = 'graduated'
     AND NOT EXISTS (
         SELECT 1 FROM {$certs_table} c WHERE c.enrollment_id = e.id AND c.status = 'active'
     )
     ORDER BY e.graduation_date ASC
     LIMIT 500"
);

$templates = Aura_Certificates_Templates::get_active_templates_for_select();
?>
<div class="wrap aura-certificates-wrap">
    <h1 class="wp-heading-inline">📋 <?php esc_html_e( 'Emisión Masiva de Certificados', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <div class="notice notice-info inline">
        <p>
            <?php printf(
                esc_html__( 'Seleccione los estudiantes graduados a quienes desea emitir certificados. Se procesarán en background.', 'aura-suite' )
            ); ?>
        </p>
    </div>

    <?php if ( empty( $pending ) ) : ?>
        <div class="notice notice-success inline">
            <p><?php esc_html_e( '✅ Todos los estudiantes graduados ya tienen certificado emitido.', 'aura-suite' ); ?></p>
        </div>
    <?php else : ?>

    <div class="aura-card" style="max-width:900px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div>
                <strong><?php printf(
                    esc_html__( '%d estudiante(s) pendiente(s) de certificado', 'aura-suite' ),
                    count( $pending )
                ); ?></strong>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <label>
                    <?php esc_html_e( 'Plantilla:', 'aura-suite' ); ?>
                    <select id="aura-bulk-template" class="aura-input" style="min-width:200px;margin-left:6px;">
                        <option value="0"><?php esc_html_e( '— Usar predeterminada —', 'aura-suite' ); ?></option>
                        <?php foreach ( $templates as $tpl ) : ?>
                        <option value="<?php echo esc_attr( $tpl->id ); ?>" <?php echo $tpl->is_default ? 'selected' : ''; ?>>
                            <?php echo esc_html( $tpl->name ); ?>
                            <?php echo $tpl->is_default ? ' ★' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
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
                        <input type="checkbox" class="aura-bulk-check"
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

    <!-- Barra de progreso (oculta hasta que inicie el proceso) -->
    <div id="aura-bulk-progress-wrap" class="aura-card" style="max-width:600px;display:none;margin-top:16px;">
        <h3><?php esc_html_e( 'Procesando…', 'aura-suite' ); ?></h3>
        <div class="aura-progress-bar-wrap" style="height:12px;background:#e5e7eb;border-radius:6px;overflow:hidden;margin:8px 0;">
            <div id="aura-bulk-progress-bar" style="height:100%;width:0%;background:#8b5cf6;transition:width .4s;"></div>
        </div>
        <p id="aura-bulk-progress-text" style="color:#555;"></p>
    </div>

    <?php endif; ?>
</div>
