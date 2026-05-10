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
<div class="wrap aura-library-reservations-list">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-calendar-alt" style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#0073aa;"></span>
        <?php esc_html_e( 'Reservas', 'aura-business-suite' ); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Filtros -->
    <div class="aura-lib-filters-bar">
        <input type="search" id="aura-lib-res-search"
               placeholder="<?php esc_attr_e( 'Buscar por libro o lector…', 'aura-business-suite' ); ?>"
               class="regular-text">

        <select id="aura-lib-res-filter-status">
            <option value=""><?php      esc_html_e( 'Todos los estados', 'aura-business-suite' ); ?></option>
            <option value="waiting"><?php   esc_html_e( 'En espera',    'aura-business-suite' ); ?></option>
            <option value="notified"><?php  esc_html_e( 'Notificado',   'aura-business-suite' ); ?></option>
            <option value="expired"><?php   esc_html_e( 'Expirado',     'aura-business-suite' ); ?></option>
        </select>

        <button id="aura-lib-res-filter-apply" class="button">
            <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?>
        </button>
        <button id="aura-lib-res-filter-clear" class="button button-link">
            <?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?>
        </button>
    </div>

    <!-- Aviso -->
    <div id="aura-lib-res-notice" class="notice" style="display:none;"></div>

    <!-- Tabla -->
    <table id="aura-lib-reservations-table" class="wp-list-table widefat">
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
    <div id="aura-lib-res-pagination" class="aura-lib-pagination" style="margin-top:12px;"></div>

<?php
wp_enqueue_style( 'datatables-css',
    'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css', [], '2.2.2' );
wp_enqueue_style( 'datatables-responsive-css',
    'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
    [ 'datatables-css' ], '3.0.4' );
wp_enqueue_script( 'datatables-js',
    'https://cdn.datatables.net/2.2.2/js/dataTables.min.js', [ 'jquery' ], '2.2.2', true );
wp_enqueue_script( 'datatables-responsive-js',
    'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
    [ 'datatables-js' ], '3.0.4', true );
wp_enqueue_script( 'aura-lib-reservations',
    AURA_PLUGIN_URL . 'assets/js/library-reservations.js',
    [ 'jquery', 'datatables-responsive-js' ],
    AURA_VERSION, true );
wp_enqueue_style( 'aura-lib-reservations-css',
    AURA_PLUGIN_URL . 'assets/css/library-reservations.css',
    [ 'datatables-responsive-css' ], AURA_VERSION );
?>

</div><!-- .aura-library-reservations-list -->

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
