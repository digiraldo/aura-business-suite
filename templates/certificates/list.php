<?php
/**
 * Template: Listado de Certificados Emitidos
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Filtros
$search    = sanitize_text_field( wp_unslash( $_GET['s']          ?? '' ) );
$status    = sanitize_key( $_GET['status']                         ?? '' );
$date_from = sanitize_text_field( wp_unslash( $_GET['date_from']  ?? '' ) );
$date_to   = sanitize_text_field( wp_unslash( $_GET['date_to']    ?? '' ) );
$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page  = 30;
$offset    = ( $paged - 1 ) * $per_page;

global $wpdb;
$table          = $wpdb->prefix . 'aura_certificates';
$students_table = $wpdb->prefix . 'aura_students';

$where  = '1=1';
$params = [];

if ( $search ) {
    $like    = '%' . $wpdb->esc_like( $search ) . '%';
    $where  .= ' AND (c.folio LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s OR c.course_name LIKE %s)';
    $params  = array_merge( $params, [ $like, $like, $like, $like ] );
}
if ( $status ) {
    $where   .= ' AND c.status = %s';
    $params[] = $status;
}
if ( $date_from ) {
    $where   .= ' AND c.issued_at >= %s';
    $params[] = $date_from . ' 00:00:00';
}
if ( $date_to ) {
    $where   .= ' AND c.issued_at <= %s';
    $params[] = $date_to . ' 23:59:59';
}

$base_sql = "FROM {$table} c LEFT JOIN {$students_table} s ON c.student_id = s.id WHERE {$where}";
$count_sql = "SELECT COUNT(*) {$base_sql}";
$data_sql  = "SELECT c.id, c.folio, c.course_name, c.program_name, c.issued_at, c.status, s.first_name, s.last_name {$base_sql} ORDER BY c.issued_at DESC LIMIT %d OFFSET %d";

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
        <div class="aura-filter-row">
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>"
                   placeholder="<?php esc_attr_e( 'Buscar folio, estudiante, curso…', 'aura-suite' ); ?>"
                   class="aura-input" style="width:260px;">
            <select name="status" class="aura-input">
                <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                <option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Activos', 'aura-suite' ); ?></option>
                <option value="revoked" <?php selected( $status, 'revoked' ); ?>><?php esc_html_e( 'Revocados', 'aura-suite' ); ?></option>
            </select>
            <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>"
                   class="aura-input" placeholder="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>"
                   class="aura-input" placeholder="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>">
            <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'aura-suite' ); ?></button>
            <?php if ( $search || $status || $date_from || $date_to ) : ?>
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
                <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Programa', 'aura-suite' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                <th style="width:90px;"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th style="width:160px;"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $certs as $cert ) : ?>
            <tr>
                <td><code><?php echo esc_html( $cert->folio ); ?></code></td>
                <td><?php echo esc_html( trim( $cert->first_name . ' ' . $cert->last_name ) ); ?></td>
                <td><?php echo esc_html( $cert->course_name ); ?></td>
                <td><?php echo esc_html( $cert->program_name ); ?></td>
                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) ) ); ?></td>
                <td>
                    <span class="aura-cert-status aura-cert-status--<?php echo esc_attr( $cert->status ); ?>">
                        <?php echo $cert->status === 'active' ? esc_html__( 'Activo', 'aura-suite' ) : esc_html__( 'Revocado', 'aura-suite' ); ?>
                    </span>
                </td>
                <td class="aura-cert-actions-cell">
                    <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=aura_cert_download&folio=' . urlencode( $cert->folio ) ) ); ?>"
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
                'base'    => add_query_arg( 'paged', '%#%', $base_url . ( $search ? '&s=' . urlencode( $search ) : '' ) ),
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
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <h2><?php esc_html_e( 'Revocar Certificado', 'aura-suite' ); ?></h2>
        <p><?php esc_html_e( 'Indique el motivo de la revocación (opcional):', 'aura-suite' ); ?></p>
        <textarea id="aura-revoke-reason" class="aura-input" rows="3" style="width:100%;"
                  placeholder="<?php esc_attr_e( 'Motivo de revocación…', 'aura-suite' ); ?>"></textarea>
        <div class="aura-modal-actions" style="margin-top:12px;">
            <button id="aura-revoke-confirm" class="button button-primary" style="background:#dc2626;border-color:#dc2626;">
                <?php esc_html_e( 'Confirmar Revocación', 'aura-suite' ); ?>
            </button>
            <button id="aura-revoke-cancel" class="button"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>
