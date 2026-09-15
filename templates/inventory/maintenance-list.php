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
<div class="aura-app-wrapper aura-inv-maintenance-list">

    <?php
    $actions_html = '';
    if ( $can_create ) {
        $actions_html .= '<a href="' . esc_url( admin_url( 'admin.php?page=aura-inventory-new-maintenance' ) ) . '" class="aura-btn aura-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__( 'Registrar Mantenimiento', 'aura-business-suite' ) . '</a>';
    }

    Aura_UI::render_page_header( [
        'title'       => __( 'Historial de Mantenimientos', 'aura-business-suite' ),
        'subtitle'    => __( 'Control de mantenimientos preventivos, correctivos e intervenciones de equipos', 'aura-business-suite' ),
        'icon'        => 'dashicons-admin-tools',
        'breadcrumbs' => [
            [ 'label' => __( 'Inventario', 'aura-business-suite' ), 'url' => admin_url( 'admin.php?page=aura-inventory' ) ],
            [ 'label' => __( 'Mantenimientos', 'aura-business-suite' ) ],
        ],
        'actions_html'=> $actions_html,
    ] );

    if ( $pending_followup > 0 ) : ?>
    <div class="notice notice-warning" style="margin-bottom: var(--aura-space-4, 16px);">
        <p><?php printf(
            _n( 'Hay <strong>%d mantenimiento</strong> con seguimiento pendiente.', 'Hay <strong>%d mantenimientos</strong> con seguimiento pendiente.', $pending_followup, 'aura-business-suite' ),
            $pending_followup
        ); ?> <a href="#" id="aura-maint-filter-followup"><?php esc_html_e( 'Ver →', 'aura-business-suite' ); ?></a></p>
    </div>
    <?php endif;

    Aura_UI::render_stats_grid( [
        [
            'id'       => 'kpi-maint-month',
            'icon'     => 'dashicons-admin-tools',
            'value'    => number_format_i18n( $total_month ),
            'label'    => __( 'Este Mes', 'aura-business-suite' ),
            'variant'  => 'primary',
            'tooltip'  => __( 'Mantenimientos realizados en el mes actual', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-maint-year',
            'icon'     => 'dashicons-calendar-alt',
            'value'    => number_format_i18n( $total_year ),
            'label'    => sprintf( __( 'Total Año %s', 'aura-business-suite' ), date( 'Y' ) ),
            'variant'  => 'info',
            'tooltip'  => __( 'Mantenimientos acumulados durante el año actual', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-maint-cost-month',
            'icon'     => 'dashicons-money-alt',
            'value'    => $currency . number_format( $cost_month, 2 ),
            'label'    => __( 'Costo Este Mes', 'aura-business-suite' ),
            'variant'  => 'warning',
            'tooltip'  => __( 'Gasto acumulado en mantenimientos del mes', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-maint-cost-year',
            'icon'     => 'dashicons-chart-area',
            'value'    => $currency . number_format( $cost_year, 2 ),
            'label'    => sprintf( __( 'Costo Año %s', 'aura-business-suite' ), date( 'Y' ) ),
            'variant'  => 'expense',
            'tooltip'  => __( 'Gasto acumulado en mantenimientos del año', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-maint-followup',
            'icon'     => 'dashicons-warning',
            'value'    => number_format_i18n( $pending_followup ),
            'label'    => __( 'Con Seguimiento', 'aura-business-suite' ),
            'variant'  => $pending_followup > 0 ? 'danger' : 'success',
            'tooltip'  => __( 'Mantenimientos que requieren inspección posterior', 'aura-business-suite' ),
        ],
    ], [ 'columns' => 5 ] );
    ?>

    <!-- Filtros -->
    <div class="aura-glass-card aura-filters-card" style="margin-bottom: var(--aura-space-6, 24px);">
        <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
            <div style="flex:1; min-width:200px;">
                <input type="search" id="aura-maint-search"
                       placeholder="<?php esc_attr_e( 'Buscar por equipo, taller…', 'aura-business-suite' ); ?>"
                       class="aura-input" style="width:100%;">
            </div>

            <div style="min-width:180px;">
                <select id="aura-maint-filter-equipment" class="aura-select" style="width:100%;">
                    <option value="0"><?php esc_html_e( 'Todos los equipos', 'aura-business-suite' ); ?></option>
                    <?php foreach ( $equipment_filter_list as $eq ) : ?>
                    <option value="<?php echo esc_attr( $eq->id ); ?>"
                            <?php selected( $preselect_equipment_id, $eq->id ); ?>>
                        <?php echo esc_html( $eq->name . ( $eq->brand ? ' · ' . $eq->brand : '' ) ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="min-width:140px;">
                <select id="aura-maint-filter-type" class="aura-select" style="width:100%;">
                    <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-business-suite' ); ?></option>
                    <option value="preventive"><?php   esc_html_e( 'Preventivo',        'aura-business-suite' ); ?></option>
                    <option value="corrective"><?php   esc_html_e( 'Correctivo',        'aura-business-suite' ); ?></option>
                    <option value="oil_change"><?php   esc_html_e( 'Cambio de aceite',  'aura-business-suite' ); ?></option>
                    <option value="cleaning"><?php     esc_html_e( 'Limpieza',          'aura-business-suite' ); ?></option>
                    <option value="inspection"><?php   esc_html_e( 'Inspección',        'aura-business-suite' ); ?></option>
                    <option value="major_repair"><?php esc_html_e( 'Reparación mayor',  'aura-business-suite' ); ?></option>
                </select>
            </div>

            <div style="min-width:130px;">
                <select id="aura-maint-filter-performed" class="aura-select" style="width:100%;">
                    <option value=""><?php         esc_html_e( 'Interno y externo', 'aura-business-suite' ); ?></option>
                    <option value="internal"><?php esc_html_e( 'Interno',           'aura-business-suite' ); ?></option>
                    <option value="external"><?php esc_html_e( 'Externo',           'aura-business-suite' ); ?></option>
                </select>
            </div>

            <div style="min-width:140px;">
                <select id="aura-maint-filter-post-status" class="aura-select" style="width:100%;">
                    <option value=""><?php               esc_html_e( 'Cualquier estado',  'aura-business-suite' ); ?></option>
                    <option value="operational"><?php    esc_html_e( 'Operacional',       'aura-business-suite' ); ?></option>
                    <option value="needs_followup"><?php esc_html_e( 'Con seguimiento',   'aura-business-suite' ); ?></option>
                    <option value="out_of_service"><?php esc_html_e( 'Fuera de servicio', 'aura-business-suite' ); ?></option>
                </select>
            </div>

            <div>
                <input type="date" id="aura-maint-filter-date-from" class="aura-input" title="<?php esc_attr_e( 'Desde', 'aura-business-suite' ); ?>">
            </div>
            <div>
                <input type="date" id="aura-maint-filter-date-to" class="aura-input" title="<?php esc_attr_e( 'Hasta', 'aura-business-suite' ); ?>">
            </div>

            <div style="display:flex; gap:8px;">
                <button id="aura-maint-filter-apply" class="aura-btn aura-btn-secondary">
                    <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?>
                </button>
                <button id="aura-maint-filter-clear" class="aura-btn aura-btn-ghost">
                    <?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla — DataTables la inicializa vía JS -->
    <div class="aura-glass-card aura-dt-wrapper">
        <table id="aura-maint-table" class="aura-table display responsive nowrap" style="width:100%;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Foto',             'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Equipo',           'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Fecha',            'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Tipo',             'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Ejecutor',         'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Costo total',      'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Estado post-mant.','aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Finanzas',         'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Acciones',         'aura-business-suite' ); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

<?php
wp_enqueue_script( 'aura-inv-maintenance-list', AURA_PLUGIN_URL . 'assets/js/inventory-maintenance.js', ['jquery'], AURA_VERSION, true );
wp_enqueue_style(  'aura-inv-maintenance',    AURA_PLUGIN_URL . 'assets/css/inventory-maintenance.css', [], AURA_VERSION );
?>

</div><!-- .aura-app-wrapper -->


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
