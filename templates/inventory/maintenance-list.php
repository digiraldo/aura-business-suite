<?php
/**
 * Template: Listado de Mantenimientos
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_inventory_maintenance_view' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
}

// Equipos para el selector de filtro
global $wpdb;
$equipment_filter_list = $wpdb->get_results(
    "SELECT id, name, brand FROM {$wpdb->prefix}aura_inventory_equipment
     WHERE deleted_at IS NULL ORDER BY name ASC"
) ?: [];

// Filtro por equipo pre-seleccionado desde URL (ej: desde detalle de equipo)
$preselect_equipment_id = intval( $_GET['equipment_id'] ?? 0 );

$can_create = current_user_can( 'aura_inventory_maintenance_create' ) || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_inventory_maintenance_edit'   ) || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_inventory_maintenance_delete' ) || current_user_can( 'manage_options' );

// Stats rápidos del período actual
$t_maint   = $wpdb->prefix . 'aura_inventory_maintenance';
$cur_month = date( 'Y-m' );
$total_month      = (int)   $wpdb->get_var( "SELECT COUNT(*)       FROM {$t_maint} WHERE DATE_FORMAT(maintenance_date,'%Y-%m') = '{$cur_month}'" );
$total_year       = (int)   $wpdb->get_var( "SELECT COUNT(*)       FROM {$t_maint} WHERE YEAR(maintenance_date) = YEAR(CURDATE())" );
$cost_month       = (float) $wpdb->get_var( "SELECT SUM(total_cost) FROM {$t_maint} WHERE DATE_FORMAT(maintenance_date,'%Y-%m') = '{$cur_month}'" );
$cost_year        = (float) $wpdb->get_var( "SELECT SUM(total_cost) FROM {$t_maint} WHERE YEAR(maintenance_date) = YEAR(CURDATE())" );
$pending_followup = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$t_maint} WHERE post_status = 'needs_followup'" );
$currency = get_option( 'aura_currency_symbol', '$' );
?>

<div class="wrap aura-inv-maintenance-list">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-tools" style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php _e( 'Historial de Mantenimientos', 'aura-suite' ); ?>
    </h1>

    <?php if ( $can_create ) : ?>
    <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-new-maintenance' ); ?>" class="page-title-action">
        + <?php _e( 'Registrar Mantenimiento', 'aura-suite' ); ?>
    </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if ( $pending_followup > 0 ) : ?>
    <div class="notice notice-warning">
        <p><?php printf(
            _n( 'Hay <strong>%d mantenimiento</strong> con seguimiento pendiente.', 'Hay <strong>%d mantenimientos</strong> con seguimiento pendiente.', $pending_followup, 'aura-suite' ),
            $pending_followup
        ); ?> <a href="#" id="aura-maint-filter-followup"><?php _e( 'Ver →', 'aura-suite' ); ?></a></p>
    </div>
    <?php endif; ?>

    <!-- KPIs rápidos -->
    <div class="aura-inv-kpi-grid">
        <div class="aura-inv-kpi-card aura-inv-kpi-blue">
            <div class="aura-inv-kpi-icon dashicons dashicons-admin-tools"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo $total_month; ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Este mes', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-inv-kpi-card aura-inv-kpi-green">
            <div class="aura-inv-kpi-icon dashicons dashicons-calendar-alt"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo $total_year; ?></span>
                <span class="aura-inv-kpi-label"><?php printf( __( 'Total %d', 'aura-suite' ), date('Y') ); ?></span>
            </div>
        </div>
        <div class="aura-inv-kpi-card aura-inv-kpi-orange">
            <div class="aura-inv-kpi-icon dashicons dashicons-money-alt"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo $currency . number_format( $cost_month, 2 ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Costo este mes', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-inv-kpi-card aura-inv-kpi-purple">
            <div class="aura-inv-kpi-icon dashicons dashicons-chart-area"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo $currency . number_format( $cost_year, 2 ); ?></span>
                <span class="aura-inv-kpi-label"><?php printf( __( 'Costo %d', 'aura-suite' ), date('Y') ); ?></span>
            </div>
        </div>
        <?php if ( $pending_followup > 0 ) : ?>
        <div class="aura-inv-kpi-card aura-inv-kpi-red">
            <div class="aura-inv-kpi-icon dashicons dashicons-warning"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo $pending_followup; ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Con seguimiento pendiente', 'aura-suite' ); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="aura-inv-filters-bar">

        <input type="search" id="aura-maint-search"
               placeholder="<?php esc_attr_e( 'Buscar por equipo, taller…', 'aura-suite' ); ?>"
               class="regular-text">

        <select id="aura-maint-filter-equipment">
            <option value="0"><?php _e( 'Todos los equipos', 'aura-suite' ); ?></option>
            <?php foreach ( $equipment_filter_list as $eq ) : ?>
            <option value="<?php echo esc_attr( $eq->id ); ?>"
                    <?php selected( $preselect_equipment_id, $eq->id ); ?>>
                <?php echo esc_html( $eq->name . ( $eq->brand ? ' · '.$eq->brand : '' ) ); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select id="aura-maint-filter-type">
            <option value=""><?php _e( 'Todos los tipos', 'aura-suite' ); ?></option>
            <option value="preventive"><?php _e( 'Preventivo',       'aura-suite' ); ?></option>
            <option value="corrective"><?php _e( 'Correctivo',       'aura-suite' ); ?></option>
            <option value="oil_change"><?php _e( 'Cambio de aceite', 'aura-suite' ); ?></option>
            <option value="cleaning"><?php _e( 'Limpieza',           'aura-suite' ); ?></option>
            <option value="inspection"><?php _e( 'Inspección',       'aura-suite' ); ?></option>
            <option value="major_repair"><?php _e( 'Reparación mayor','aura-suite' ); ?></option>
        </select>

        <select id="aura-maint-filter-performed">
            <option value=""><?php _e( 'Interno y externo', 'aura-suite' ); ?></option>
            <option value="internal"><?php _e( 'Interno', 'aura-suite' ); ?></option>
            <option value="external"><?php _e( 'Externo',  'aura-suite' ); ?></option>
        </select>

        <select id="aura-maint-filter-post-status">
            <option value=""><?php _e( 'Cualquier estado', 'aura-suite' ); ?></option>
            <option value="operational"><?php _e( 'Operacional',          'aura-suite' ); ?></option>
            <option value="needs_followup"><?php _e( 'Con seguimiento', 'aura-suite' ); ?></option>
            <option value="out_of_service"><?php _e( 'Fuera de servicio','aura-suite' ); ?></option>
        </select>

        <input type="date" id="aura-maint-filter-date-from" title="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>">
        <input type="date" id="aura-maint-filter-date-to"   title="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>">

        <button id="aura-maint-filter-apply" class="button">
            <span class="dashicons dashicons-search"></span> <?php _e( 'Filtrar', 'aura-suite' ); ?>
        </button>
        <button id="aura-maint-filter-clear" class="button button-link">
            <?php _e( 'Limpiar', 'aura-suite' ); ?>
        </button>
    </div><!-- .aura-inv-filters-bar -->

    <!-- Tabla -->
    <div id="aura-maint-table-wrap">
        <table id="aura-maint-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="col-photo" style="width:58px;"><?php _e( 'Foto', 'aura-suite' ); ?></th>
                    <th class="col-date sortable" data-sort="maintenance_date"><?php _e( 'Fecha',      'aura-suite' ); ?></th>
                    <th class="col-equipment"><?php _e( 'Equipo',         'aura-suite' ); ?></th>
                    <th class="col-type sortable" data-sort="type"><?php _e( 'Tipo',          'aura-suite' ); ?></th>
                    <th class="col-executor"><?php _e( 'Ejecutor',        'aura-suite' ); ?></th>
                    <th class="col-cost sortable" data-sort="total_cost"><?php _e( 'Costo total', 'aura-suite' ); ?></th>
                    <th class="col-post-status"><?php _e( 'Estado post-mant.','aura-suite' ); ?></th>
                    <th class="col-finance"><?php _e( 'Finanzas',         'aura-suite' ); ?></th>
                    <th class="col-actions"><?php _e( 'Acciones',         'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="aura-maint-tbody">
                <tr><td colspan="9" style="text-align:center;padding:30px;">
                    <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                    <?php _e( 'Cargando mantenimientos…', 'aura-suite' ); ?>
                </td></tr>
            </tbody>
        </table>

        <!-- Paginación -->
        <div id="aura-maint-pagination" class="tablenav bottom" style="display:none;">
            <div class="tablenav-pages">
                <span class="displaying-num" id="aura-maint-total-count"></span>
                <span class="pagination-links">
                    <button id="aura-maint-prev" class="button" disabled>&laquo;</button>
                    <span id="aura-maint-page-info"></span>
                    <button id="aura-maint-next" class="button">&raquo;</button>
                </span>
            </div>
        </div>
    </div><!-- #aura-maint-table-wrap -->

</div><!-- .aura-inv-maintenance-list -->

<!-- Modal de detalle -->
<div id="aura-maint-detail-modal" class="aura-inv-modal" style="display:none;">
    <div class="aura-inv-modal-overlay"></div>
    <div class="aura-inv-modal-content aura-inv-modal-large">
        <div class="aura-inv-modal-header">
            <h2 id="aura-maint-detail-title"><?php _e( 'Detalle del Mantenimiento', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-inv-modal-close dashicons dashicons-no-alt"></button>
        </div>
        <div class="aura-inv-modal-body" id="aura-maint-detail-body">
            <span class="spinner is-active"></span>
        </div>
    </div>
</div>

<?php
$_maint_list_js = wp_json_encode( [
    'ajaxurl'       => admin_url( 'admin-ajax.php' ),
    'nonce'         => wp_create_nonce( 'aura_inventory_nonce' ),
    'newUrl'        => admin_url( 'admin.php?page=aura-inventory-new-maintenance' ),
    'currency'      => $currency,
    'preselectEquip'=> $preselect_equipment_id,
    'can_edit'      => $can_edit,
    'can_delete'    => $can_delete,
    'txt' => [
        'loading'        => __( 'Cargando…', 'aura-suite' ),
        'no_results'     => __( 'No se encontraron mantenimientos.', 'aura-suite' ),
        'confirm_delete' => __( '¿Eliminar este registro de mantenimiento?', 'aura-suite' ),
        'error'          => __( 'Error al procesar la solicitud.', 'aura-suite' ),
        'deleted'        => __( 'Mantenimiento eliminado.', 'aura-suite' ),
        'page_of'        => __( 'Página %1$s de %2$s', 'aura-suite' ),
        'n_items'        => __( '%s registros', 'aura-suite' ),
        'has_finance'    => __( '✅ Con transacción', 'aura-suite' ),
        'no_finance'     => __( '—', 'aura-suite' ),
        'type_labels' => [
            'preventive'   => __( 'Preventivo',        'aura-suite' ),
            'corrective'   => __( 'Correctivo',        'aura-suite' ),
            'oil_change'   => __( 'Cambio de aceite',  'aura-suite' ),
            'cleaning'     => __( 'Limpieza',          'aura-suite' ),
            'inspection'   => __( 'Inspección',        'aura-suite' ),
            'major_repair' => __( 'Reparación mayor',  'aura-suite' ),
        ],
        'post_status_labels' => [
            'operational'    => __( 'Operacional',          'aura-suite' ),
            'needs_followup' => __( 'Seguimiento',          'aura-suite' ),
            'out_of_service' => __( 'Fuera de servicio',    'aura-suite' ),
        ],
        'performed_labels' => [
            'internal' => __( 'Interno', 'aura-suite' ),
            'external' => __( 'Externo',  'aura-suite' ),
        ],
    ],
] );
?>
<script>var auraMaintList = <?php echo $_maint_list_js; ?>;</script>
