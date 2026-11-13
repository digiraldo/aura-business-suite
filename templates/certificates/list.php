<?php
/**
 * Template: Listado de Certificados Emitidos
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Filtros
$search         = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$status         = sanitize_key( $_GET['status'] ?? '' );
$recipient_type = sanitize_key( $_GET['recipient_type'] ?? '' );
$counterparty   = sanitize_text_field( wp_unslash( $_GET['counterparty'] ?? '' ) );
$cp_label       = sanitize_text_field( wp_unslash( $_GET['counterparty_label'] ?? '' ) );
$date_from      = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
$date_to        = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) );
$paged          = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page       = 30;
$offset         = ( $paged - 1 ) * $per_page;

global $wpdb;
$table          = $wpdb->prefix . 'aura_certificates';
$students_table = $wpdb->prefix . 'aura_students';

$where  = '1=1';
$params = [];

if ( $search ) {
    $like    = '%' . $wpdb->esc_like( $search ) . '%';
    $where  .= ' AND (c.folio LIKE %s OR c.student_name LIKE %s OR c.recipient_email LIKE %s OR c.recipient_document LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s OR c.course_name LIKE %s)';
    $params  = array_merge( $params, [ $like, $like, $like, $like, $like, $like, $like ] );
}
if ( $status ) {
    $where   .= ' AND c.status = %s';
    $params[] = $status;
}
if ( $recipient_type && in_array( $recipient_type, [ 'student', 'wp_user', 'third_party', 'manual' ], true ) ) {
    $where   .= ' AND c.recipient_type = %s';
    $params[] = $recipient_type;
}
if ( $counterparty ) {
    if ( str_starts_with( $counterparty, 'wp:' ) ) {
        $where   .= ' AND (c.wp_user_id = %d OR s.wp_user_id = %d)';
        $uid      = absint( substr( $counterparty, 3 ) );
        $params[] = $uid;
        $params[] = $uid;
    } elseif ( str_starts_with( $counterparty, 'tp:' ) ) {
        $where   .= ' AND c.third_party_id = %d';
        $params[] = absint( substr( $counterparty, 3 ) );
    }
}
if ( $date_from ) {
    $where   .= ' AND c.issued_at >= %s';
    $params[] = $date_from . ' 00:00:00';
}
if ( $date_to ) {
    $where   .= ' AND c.issued_at <= %s';
    $params[] = $date_to . ' 23:59:59';
}

$base_sql  = "FROM {$table} c LEFT JOIN {$students_table} s ON c.student_id = s.id WHERE {$where}";
$count_sql = "SELECT COUNT(*) {$base_sql}";
$data_sql  = "SELECT c.id, c.folio, c.course_name, c.program_name, c.issued_at, c.status,
                     c.recipient_type, c.student_id, c.wp_user_id, c.third_party_id, c.recipient_email,
                     COALESCE(NULLIF(c.student_name,''), TRIM(CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,'')))) AS recipient_name
              {$base_sql} ORDER BY c.issued_at DESC LIMIT %d OFFSET %d";

if ( $params ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $certs = $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $params, [ $per_page, $offset ] ) ) );
} else {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $total = (int) $wpdb->get_var( $count_sql );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $certs = $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) );
}

$total_pages = (int) ceil( $total / $per_page );
$base_url    = admin_url( 'admin.php?page=aura-certificates-list' );
?>
<div class="wrap aura-certificates-wrap">
    <h1 class="wp-heading-inline">🏅 <?php esc_html_e( 'Certificados Emitidos', 'aura-suite' ); ?></h1>
    <a href="<?php echo esc_url( $base_url . '&action=issue' ); ?>" class="page-title-action">
        <?php esc_html_e( '+ Emitir Nuevo', 'aura-suite' ); ?>
    </a>
    <hr class="wp-header-end">

    <!-- Filtros -->
    <form method="GET" action="<?php echo esc_url( $base_url ); ?>" class="aura-search-form">
        <input type="hidden" name="page" value="aura-certificates-list">
        <div class="aura-filter-row" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>"
                   placeholder="<?php esc_attr_e( 'Buscar folio, destinatario, documento, curso…', 'aura-suite' ); ?>"
                   class="aura-input" style="width:240px;">
            <select name="status" class="aura-input">
                <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                <option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Activos', 'aura-suite' ); ?></option>
                <option value="revoked" <?php selected( $status, 'revoked' ); ?>><?php esc_html_e( 'Revocados', 'aura-suite' ); ?></option>
            </select>
            <select name="recipient_type" class="aura-input">
                <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
                <option value="student" <?php selected( $recipient_type, 'student' ); ?>><?php esc_html_e( 'Estudiantes', 'aura-suite' ); ?></option>
                <option value="wp_user" <?php selected( $recipient_type, 'wp_user' ); ?>><?php esc_html_e( 'Usuarios WP', 'aura-suite' ); ?></option>
                <option value="third_party" <?php selected( $recipient_type, 'third_party' ); ?>><?php esc_html_e( 'Terceros', 'aura-suite' ); ?></option>
                <option value="manual" <?php selected( $recipient_type, 'manual' ); ?>><?php esc_html_e( 'Manuales', 'aura-suite' ); ?></option>
            </select>
            <div style="display:inline-flex;align-items:center;gap:4px;">
                <input type="text" id="aura-cert-filter-counterparty" name="counterparty_label"
                       value="<?php echo esc_attr( $cp_label ); ?>"
                       placeholder="<?php esc_attr_e( 'Filtrar Usuario WP / Tercero…', 'aura-suite' ); ?>"
                       class="aura-input" style="width:200px;">
                <input type="hidden" id="aura-cert-filter-counterparty-ref" name="counterparty" value="<?php echo esc_attr( $counterparty ); ?>">
                <button type="button" id="aura-cert-filter-counterparty-explore" class="button" title="<?php esc_attr_e( 'Explorar Usuarios WP y Terceros', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-groups" style="vertical-align:text-bottom;"></span>
                </button>
            </div>
            <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>"
                   class="aura-input" placeholder="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>"
                   class="aura-input" placeholder="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>">
            <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'aura-suite' ); ?></button>
            <?php if ( $search || $status || $recipient_type || $counterparty || $date_from || $date_to ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'aura-suite' ); ?></a>
            <?php endif; ?>
        </div>
    </form>

    <p class="aura-count">
        <?php printf( esc_html__( '%d certificado(s) encontrado(s)', 'aura-suite' ), esc_html( number_format_i18n( $total ) ) ); ?>
    </p>

    <?php if ( empty( $certs ) ) : ?>
        <div class="notice notice-info inline"><p><?php esc_html_e( 'No se encontraron certificados con los filtros aplicados.', 'aura-suite' ); ?></p></div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:150px;"><?php esc_html_e( 'Folio', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Destinatario', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Programa', 'aura-suite' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                <th style="width:90px;"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th style="width:210px;"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $type_map = [
                'student'     => __( 'Estudiante', 'aura-suite' ),
                'wp_user'     => __( 'Usuario WP', 'aura-suite' ),
                'third_party' => __( 'Tercero', 'aura-suite' ),
                'manual'      => __( 'Manual', 'aura-suite' ),
            ];
            foreach ( $certs as $cert ) :
                $rtype  = $cert->recipient_type ?: ( ! empty( $cert->student_id ) ? 'student' : ( ! empty( $cert->wp_user_id ) ? 'wp_user' : ( ! empty( $cert->third_party_id ) ? 'third_party' : 'manual' ) ) );
                $rlabel = $type_map[ $rtype ] ?? __( 'Estudiante', 'aura-suite' );
            ?>
            <tr>
                <td><code><?php echo esc_html( $cert->folio ); ?></code></td>
                <td>
                    <strong><?php echo esc_html( $cert->recipient_name ?: '—' ); ?></strong>
                    <span class="aura-cert-badge aura-cert-badge--<?php echo esc_attr( $rtype ); ?>" style="margin-left:6px;"><?php echo esc_html( $rlabel ); ?></span>
                </td>
                <td><?php echo esc_html( $cert->course_name ); ?></td>
                <td><?php echo esc_html( $cert->program_name ); ?></td>
                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) ) ); ?></td>
                <td>
                    <span class="aura-cert-status aura-cert-status--<?php echo esc_attr( $cert->status ); ?>">
                        <?php echo $cert->status === 'active' ? esc_html__( 'Activo', 'aura-suite' ) : esc_html__( 'Revocado', 'aura-suite' ); ?>
                    </span>
                </td>
                <td class="aura-cert-actions-cell">
                    <a href="<?php echo esc_url( $base_url . '&action=view&id=' . absint( $cert->id ) ); ?>"
                       class="button button-small">
                        <?php esc_html_e( 'Ver', 'aura-suite' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=aura_cert_download&folio=' . rawurlencode( $cert->folio ) ) ); ?>"
                       class="button button-small" target="_blank">
                        <?php esc_html_e( 'PDF', 'aura-suite' ); ?>
                    </a>
                    <?php if ( $cert->status === 'active' && ( current_user_can( 'aura_cert_revoke' ) || current_user_can( 'manage_options' ) ) ) : ?>
                    <button type="button" class="button button-small aura-cert-revoke-btn"
                            data-id="<?php echo esc_attr( $cert->id ); ?>"
                            data-folio="<?php echo esc_attr( $cert->folio ); ?>">
                        <?php esc_html_e( 'Revocar', 'aura-suite' ); ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo wp_kses_post( paginate_links( [
                'base'    => add_query_arg( 'paged', '%#%' ),
                'format'  => '',
                'current' => $paged,
                'total'   => $total_pages,
                'type'    => 'list',
            ] ) );
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Modal de revocación -->
<div id="aura-revoke-modal" class="aura-modal" style="display:none;">
    <div class="aura-modal-overlay aura-modal-close"></div>
    <div class="aura-modal-content">
        <h2><?php esc_html_e( 'Revocar Certificado', 'aura-suite' ); ?> <span id="aura-revoke-folio-label"></span></h2>
        <input type="hidden" id="aura-revoke-id" value="">
        <p><?php esc_html_e( 'Indique el motivo de la revocación (obligatorio):', 'aura-suite' ); ?></p>
        <textarea id="aura-revoke-reason" class="aura-input" rows="3" style="width:100%;"
                  placeholder="<?php esc_attr_e( 'Motivo de revocación…', 'aura-suite' ); ?>"></textarea>
        <div class="aura-modal-actions" style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" id="aura-revoke-cancel" class="button aura-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-revoke-confirm" class="button button-primary" style="background:#dc2626;border-color:#dc2626;">
                <?php esc_html_e( 'Confirmar Revocación', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>
