<?php
/**
 * Template: Listado de Reservas
 * Fase 4 — DataTables con 8 columnas + filtros + modal detalle + cancelación
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_library_view_loans_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-business-suite' ) );
}

$can_cancel = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
$expire_days = absint( get_option( 'aura_library_reservation_expire_days', 7 ) );

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
    <div class="wrap aura-app-context">

    <?php
    Aura_UI::render_page_header( [
        'title'    => __( 'Reservas de Libros', 'aura-business-suite' ),
        'subtitle' => __( 'Gestión de colas de espera y disponibilidad de ejemplares reservados', 'aura-business-suite' ),
        'icon'     => 'dashicons-calendar-alt',
        'badges'   => [
            [ 'label' => sprintf( __( '%d en espera', 'aura-business-suite' ), $res_stats['waiting'] ), 'variant' => 'blue' ],
        ],
        'actions'  => [
            [
                'label' => __( 'Ir al Catálogo', 'aura-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-library-books' ),
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-book-alt',
            ],
        ],
    ] );

    Aura_UI::render_stats_grid( [
        [
            'id'       => 'kpi-total-res',
            'icon'     => 'dashicons-calendar-alt',
            'value'    => number_format_i18n( $res_stats['total'] ),
            'label'    => __( 'Total Reservas', 'aura-business-suite' ),
            'variant'  => 'capital',
            'tooltip'  => __( 'Histórico total de reservas solicitadas', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-waiting-res',
            'icon'     => 'dashicons-clock',
            'value'    => number_format_i18n( $res_stats['waiting'] ),
            'label'    => __( 'En Espera', 'aura-business-suite' ),
            'variant'  => 'balance',
            'tooltip'  => __( 'Lectores esperando devolución de ejemplares', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-notified-res',
            'icon'     => 'dashicons-bell',
            'value'    => number_format_i18n( $res_stats['notified'] ),
            'label'    => __( 'Notificados (Disponibles)', 'aura-business-suite' ),
            'variant'  => 'income',
            'tooltip'  => __( 'Ejemplares listos para ser recogidos por el lector', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-expired-res',
            'icon'     => 'dashicons-dismiss',
            'value'    => number_format_i18n( $res_stats['expired'] ),
            'label'    => __( 'Expiradas', 'aura-business-suite' ),
            'variant'  => 'pending',
            'tooltip'  => __( 'Reservas no recogidas a tiempo por el lector', 'aura-business-suite' ),
        ],
    ] );
    ?>

    <!-- Filtros (Bootstrap 5.3 Input Groups) -->
    <div class="aura-glass-card aura-filters-card aura-lib-res-filters">
        <div class="aura-lib-filters-grid">
            <div class="aura-lib-filter-search">
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">🔍</span>
                    <input type="search" id="aura-lib-res-search"
                           placeholder="<?php esc_attr_e( 'Buscar por libro o lector…', 'aura-business-suite' ); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="aura-lib-filter-select">
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">🟢</span>
                    <select id="aura-lib-res-filter-status" class="form-control" aria-label="<?php esc_attr_e( 'Estado', 'aura-business-suite' ); ?>">
                        <option value=""><?php      esc_html_e( 'Todos los estados', 'aura-business-suite' ); ?></option>
                        <option value="waiting"><?php   esc_html_e( 'En espera',    'aura-business-suite' ); ?></option>
                        <option value="notified"><?php  esc_html_e( 'Notificado',   'aura-business-suite' ); ?></option>
                        <option value="expired"><?php   esc_html_e( 'Expirado',     'aura-business-suite' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="aura-lib-filter-actions">
                <button type="button" id="aura-lib-res-filter-apply" class="btn btn-shimmer btn-indigo btn-lift"
                        data-tooltip="<?php esc_attr_e( 'Aplicar filtros de búsqueda y estado a la cola de reservas', 'aura-business-suite' ); ?>">
                    <span class="dashicons dashicons-filter"></span>
                    <span><?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?></span>
                </button>
                <button type="button" id="aura-lib-res-filter-clear" class="btn btn-glass btn-lift"
                        data-tooltip="<?php esc_attr_e( 'Restablecer búsqueda y filtros activos', 'aura-business-suite' ); ?>">
                    <span><?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Aviso -->
    <div id="aura-lib-res-notice" class="notice" style="display:none;"></div>

    <!-- Tabla -->
    <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
        <table id="aura-lib-reservations-table" class="aura-table display responsive aura-lib-responsive-table" style="width:100%;">
            <thead>
                <tr>
                    <th># <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Identificador único de la reserva', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Título reservado y código Dewey', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Solicitante que realizó la reserva y correo de notificación', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Posición en cola', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Turno de prioridad para asignación al liberarse un ejemplar', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Reservado el', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha de registro de la reserva', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Expira', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha límite para retirar el libro tras ser notificado', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Estado', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Estado actual de la reserva (En espera, Notificado o Expirado)', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Acciones', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Consultar detalle o cancelar reserva', 'aura-business-suite' ); ?>">?</span></th>
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

    <div class="adp-footer">
        <div>
            Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<?php
$res_js_ver  = file_exists( AURA_PLUGIN_DIR . 'assets/js/library-reservations.js' ) ? AURA_VERSION . '.' . filemtime( AURA_PLUGIN_DIR . 'assets/js/library-reservations.js' ) : AURA_VERSION;
$res_css_ver = file_exists( AURA_PLUGIN_DIR . 'assets/css/library-reservations.css' ) ? AURA_VERSION . '.' . filemtime( AURA_PLUGIN_DIR . 'assets/css/library-reservations.css' ) : AURA_VERSION;

$res_js_deps = [ 'jquery', 'aura-ui-core' ];
if ( wp_script_is( 'datatables-responsive-js', 'registered' ) ) {
    $res_js_deps[] = 'datatables-responsive-js';
}

wp_enqueue_script( 'aura-lib-reservations',
    AURA_PLUGIN_URL . 'assets/js/library-reservations.js',
    $res_js_deps,
    $res_js_ver, true );
wp_enqueue_style( 'aura-lib-reservations-css',
    AURA_PLUGIN_URL . 'assets/css/library-reservations.css',
    [ 'aura-design-system' ], $res_css_ver );
?>

<!-- Modal: Detalle de Reserva -->
<div id="aura-lib-res-detail-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-res-detail-title" aria-hidden="true" data-async-modal="true">
    <div class="aura-modal-container aura-lib-modal-container aura-lib-modal-large">
        <div class="aura-modal-header">
            <h2 id="aura-lib-res-detail-title"><?php esc_html_e( 'Detalle de Reserva', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-modal-close aura-lib-modal-close"
                    data-modal-close="aura-lib-res-detail-modal"
                    aria-label="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body aura-lib-modal-body" id="aura-lib-res-detail-body">
            <p class="aura-lib-detail-empty"><?php esc_html_e( 'Cargando detalle…', 'aura-business-suite' ); ?></p>
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
