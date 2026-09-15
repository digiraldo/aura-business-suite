<?php
/**
 * Template: Listado de Reservas
 * Fase 4 — DataTables con 7 columnas + filtros + modal detalle + cancelación
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_library_view_loans_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-business-suite' ) );
}

$can_cancel = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
$expire_days = absint( get_option( 'aura_library_reservation_expire_days', 7 ) );
?>
global $wpdb;
$table_res = $wpdb->prefix . 'aura_library_reservations';
$res_stats = [
    'total'    => 0,
    'waiting'  => 0,
    'notified' => 0,
    'expired'  => 0,
];
if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_res'" ) === $table_res ) {
    $row_stats = $wpdb->get_row( "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status = 'notified' THEN 1 ELSE 0 END) as notified,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
        FROM {$table_res}" );
    if ( $row_stats ) {
        $res_stats['total']    = (int) $row_stats->total;
        $res_stats['waiting']  = (int) $row_stats->waiting;
        $res_stats['notified'] = (int) $row_stats->notified;
        $res_stats['expired']  = (int) $row_stats->expired;
    }
}
?>
<div class="aura-app-wrapper aura-library-reservations-list">

    <?php
    Aura_UI::render_page_header( [
        'title'       => __( 'Reservas de Libros', 'aura-business-suite' ),
        'subtitle'    => __( 'Gestión de colas de espera y disponibilidad de ejemplares reservados', 'aura-business-suite' ),
        'icon'        => 'dashicons-calendar-alt',
        'breadcrumbs' => [
            [ 'label' => __( 'Biblioteca', 'aura-business-suite' ), 'url' => admin_url( 'admin.php?page=aura-library' ) ],
            [ 'label' => __( 'Reservas', 'aura-business-suite' ) ],
        ],
    ] );

    Aura_UI::render_stats_grid( [
        [
            'id'       => 'kpi-total-res',
            'icon'     => 'dashicons-calendar-alt',
            'value'    => number_format_i18n( $res_stats['total'] ),
            'label'    => __( 'Total Reservas', 'aura-business-suite' ),
            'variant'  => 'primary',
            'tooltip'  => __( 'Histórico total de reservas solicitadas', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-waiting-res',
            'icon'     => 'dashicons-clock',
            'value'    => number_format_i18n( $res_stats['waiting'] ),
            'label'    => __( 'En Espera', 'aura-business-suite' ),
            'variant'  => 'info',
            'tooltip'  => __( 'Lectores esperando devolución de ejemplares', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-notified-res',
            'icon'     => 'dashicons-bell',
            'value'    => number_format_i18n( $res_stats['notified'] ),
            'label'    => __( 'Notificados (Disponibles)', 'aura-business-suite' ),
            'variant'  => 'success',
            'tooltip'  => __( 'Ejemplares listos para ser recogidos por el lector', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-expired-res',
            'icon'     => 'dashicons-dismiss',
            'value'    => number_format_i18n( $res_stats['expired'] ),
            'label'    => __( 'Expiradas', 'aura-business-suite' ),
            'variant'  => 'warning',
            'tooltip'  => __( 'Reservas no recogidas a tiempo por el lector', 'aura-business-suite' ),
        ],
    ] );
    ?>

    <!-- Filtros -->
    <div class="aura-glass-card aura-filters-card" style="margin-bottom: var(--aura-space-6, 24px);">
        <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
            <div style="flex:1; min-width:220px;">
                <input type="search" id="aura-lib-res-search"
                       placeholder="<?php esc_attr_e( 'Buscar por libro o lector…', 'aura-business-suite' ); ?>"
                       class="aura-input" style="width:100%;">
            </div>

            <div style="min-width:180px;">
                <select id="aura-lib-res-filter-status" class="aura-select" style="width:100%;">
                    <option value=""><?php      esc_html_e( 'Todos los estados', 'aura-business-suite' ); ?></option>
                    <option value="waiting"><?php   esc_html_e( 'En espera',    'aura-business-suite' ); ?></option>
                    <option value="notified"><?php  esc_html_e( 'Notificado',   'aura-business-suite' ); ?></option>
                    <option value="expired"><?php   esc_html_e( 'Expirado',     'aura-business-suite' ); ?></option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button id="aura-lib-res-filter-apply" class="aura-btn aura-btn-secondary">
                    <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?>
                </button>
                <button id="aura-lib-res-filter-clear" class="aura-btn aura-btn-ghost">
                    <?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Aviso -->
    <div id="aura-lib-res-notice" class="notice" style="display:none;"></div>

    <!-- Tabla -->
    <div class="aura-glass-card aura-dt-wrapper">
        <table id="aura-lib-reservations-table" class="aura-table display responsive nowrap" style="width:100%;">
            <thead>
                <tr>
                    <th><?php esc_html_e( '#',                'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Libro',            'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Lector',           'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Posición en cola', 'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Reservado el',     'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Expira',           'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Estado',           'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Acciones',         'aura-business-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="aura-lib-res-tbody">
                <tr>
                    <td colspan="8" style="text-align:center;padding:20px;">
                        <span class="spinner is-active" style="float:none;"></span>
                        <?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Paginación -->
        <div id="aura-lib-res-pagination" class="aura-lib-pagination" style="margin-top:16px;"></div>
    </div>

<?php
wp_enqueue_script( 'aura-lib-reservations',
    AURA_PLUGIN_URL . 'assets/js/library-reservations.js',
    [ 'jquery' ],
    AURA_VERSION, true );
wp_enqueue_style( 'aura-lib-reservations-css',
    AURA_PLUGIN_URL . 'assets/css/library-reservations.css',
    [], AURA_VERSION );
?>

</div><!-- .aura-app-wrapper -->

<!-- Modal: Detalle de Reserva -->
<div id="aura-lib-res-detail-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-res-detail-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content aura-lib-modal-large">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-res-detail-title"><?php esc_html_e( 'Detalle de Reserva', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body" id="aura-lib-res-detail-body">
            <span class="spinner is-active" style="float:none;"></span>
        </div>
    </div>
</div>

<?php
$js_data = wp_json_encode( [
    'ajaxurl'    => admin_url( 'admin-ajax.php' ),
    'nonce'      => wp_create_nonce( 'aura_library_nonce' ),
    'can_cancel' => $can_cancel,
    'expire_days'=> $expire_days,
    'txt'        => [
        'loading'         => __( 'Cargando…',                             'aura-business-suite' ),
        'no_results'      => __( 'No se encontraron reservas.',            'aura-business-suite' ),
        'error'           => __( 'Error al procesar la solicitud.',        'aura-business-suite' ),
        'cancelled'       => __( 'Reserva cancelada correctamente.',       'aura-business-suite' ),
        'confirm_cancel'  => __( '¿Cancelar esta reserva?',               'aura-business-suite' ),
        'page_of'         => __( 'Página %1$s de %2$s',                   'aura-business-suite' ),
        'n_items'         => __( '%s reservas',                           'aura-business-suite' ),
        'status_labels'   => [
            'waiting'  => __( 'En espera',  'aura-business-suite' ),
            'notified' => __( 'Notificado', 'aura-business-suite' ),
            'expired'  => __( 'Expirado',   'aura-business-suite' ),
            'cancelled'=> __( 'Cancelado',  'aura-business-suite' ),
        ],
    ],
] );
?>
<script>var auraLibraryReservations = <?php echo $js_data; // phpcs:ignore WordPress.Security.EscapeOutput ?>;</script>
