<?php
/**
 * Template: Listado e Historial de Mantenimientos
 *
 * Ruta: admin.php?page=aura-inventory-maintenance
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_inventory_maintenance_view' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permisos para ver los mantenimientos.', 'aura-suite' ) );
}

$can_create = current_user_can( 'aura_inventory_maintenance_create' ) || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_inventory_maintenance_edit'   ) || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_inventory_maintenance_delete' ) || current_user_can( 'manage_options' );

global $wpdb;
$t_maint = $wpdb->prefix . 'aura_inventory_maintenance';
$t_equip = $wpdb->prefix . 'aura_inventory_equipment';

$currency = function_exists( 'aura_get_currency_symbol' )
    ? aura_get_currency_symbol()
    : get_option( 'aura_currency_symbol', '$' );

$preselect_equipment_id = intval( $_GET['equipment_id'] ?? 0 );

$equipment_list = $wpdb->get_results(
    "SELECT id, name, brand, internal_code FROM {$t_equip} WHERE deleted_at IS NULL ORDER BY name ASC"
) ?: [];

$month_start = current_time( 'Y-m-01' );
$year_start  = current_time( 'Y-01-01' );

$kpi_total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_maint}" );
$kpi_month_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$t_maint} WHERE maintenance_date >= %s", $month_start
) );
$kpi_year_cost   = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(total_cost), 0) FROM {$t_maint} WHERE maintenance_date >= %s", $year_start
) );
$kpi_followup    = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$t_maint} WHERE post_status = 'needs_followup'"
);

// Encolado seguro sin duplicar el handle global de aura-business-suite.php
if ( ! wp_style_is( 'aura-inventory-maintenance', 'enqueued' ) && ! wp_style_is( 'aura-inv-maintenance', 'enqueued' ) ) {
    $css_path = AURA_PLUGIN_DIR . 'assets/css/inventory-maintenance.css';
    wp_enqueue_style(
        'aura-inv-maintenance',
        AURA_PLUGIN_URL . 'assets/css/inventory-maintenance.css',
        [ 'aura-design-system' ],
        file_exists( $css_path ) ? filemtime( $css_path ) : AURA_VERSION
    );
}
if ( ! wp_script_is( 'aura-inventory-maintenance', 'enqueued' ) ) {
    $js_path = AURA_PLUGIN_DIR . 'assets/js/inventory-maintenance.js';
    wp_enqueue_script(
        'aura-inventory-maintenance',
        AURA_PLUGIN_URL . 'assets/js/inventory-maintenance.js',
        [ 'jquery', 'aura-ui-core' ],
        file_exists( $js_path ) ? filemtime( $js_path ) : AURA_VERSION,
        true
    );
}

$header_actions = [];
if ( $can_create ) {
    $new_url = admin_url( 'admin.php?page=aura-inventory-new-maintenance' . ( $preselect_equipment_id ? '&equipment_id=' . $preselect_equipment_id : '' ) );
    $header_actions[] = [
        'label' => __( 'Registrar Mantenimiento', 'aura-suite' ),
        'url'   => $new_url,
        'icon'  => 'dashicons-plus-alt2',
        'class' => 'btn btn-shimmer btn-indigo btn-lift',
    ];
}
$header_actions[] = [
    'label' => __( 'Catálogo de Equipos', 'aura-suite' ),
    'url'   => admin_url( 'admin.php?page=aura-inventory-equipment' ),
    'icon'  => 'dashicons-hammer',
    'class' => 'btn btn-glass btn-lift',
];
$header_actions[] = [
    'label' => __( 'Préstamos', 'aura-suite' ),
    'url'   => admin_url( 'admin.php?page=aura-inventory-loans' ),
    'icon'  => 'dashicons-migrate',
    'class' => 'btn btn-glass btn-lift',
];
?>

<div class="aura-app-wrapper aura-inventory-maintenance-list">
    <div class="wrap aura-app-context">

        <?php
        if ( class_exists( 'Aura_UI' ) ) {
            Aura_UI::render_page_header( [
                'title'    => __( 'Historial de Mantenimientos', 'aura-suite' ),
                'subtitle' => __( 'Control técnico de servicios preventivos, correctivos, costos de taller y vínculo contable con Finanzas.', 'aura-suite' ),
                'icon'     => 'dashicons-admin-tools',
                'actions'  => $header_actions,
            ] );

            Aura_UI::render_stats_grid( [
                [
                    'label'   => __( 'Mantenimientos del Mes', 'aura-suite' ),
                    'value'   => number_format_i18n( $kpi_month_count ),
                    'variant' => 'income',
                    'icon'    => 'dashicons-calendar-alt',
                    'tooltip' => __( 'Servicios técnicos ejecutados en el mes calendario actual', 'aura-suite' ),
                ],
                [
                    'label'   => __( 'Inversión Anual en Mant.', 'aura-suite' ),
                    'value'   => esc_html( $currency ) . number_format( $kpi_year_cost, 2 ),
                    'variant' => 'expense',
                    'icon'    => 'dashicons-money-alt',
                    'tooltip' => __( 'Suma de repuestos y mano de obra acumulada en lo que va del año', 'aura-suite' ),
                ],
                [
                    'label'   => __( 'Requieren Seguimiento', 'aura-suite' ),
                    'value'   => number_format_i18n( $kpi_followup ),
                    'variant' => 'pending',
                    'icon'    => 'dashicons-warning',
                    'tooltip' => __( 'Equipos cuyo último mantenimiento quedó con observación de revisión pendiente', 'aura-suite' ),
                ],
                [
                    'label'   => __( 'Total Histórico', 'aura-suite' ),
                    'value'   => number_format_i18n( $kpi_total ),
                    'variant' => 'count',
                    'icon'    => 'dashicons-clipboard',
                    'tooltip' => __( 'Total de órdenes e intervenciones técnicas documentadas', 'aura-suite' ),
                ],
            ] );
        }
        ?>

        <?php if ( $kpi_followup > 0 ) : ?>
        <div class="alert-card alert-warning" style="margin-bottom:20px;">
            <span class="dashicons dashicons-warning"></span>
            <div style="flex:1;">
                <strong><?php printf( __( 'Hay %d mantenimiento(s) marcados para seguimiento técnico.', 'aura-suite' ), $kpi_followup ); ?></strong>
            </div>
            <a href="#" id="aura-maint-filter-followup" class="btn btn-glass btn-sm">
                <?php _e( 'Ver seguimientos →', 'aura-suite' ); ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Filtros Glassmorphic BS 5.3 -->
        <div class="aura-glass-card aura-filters-card">
            <div class="aura-maint-filters-grid">
                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Buscar por equipo, marca, taller o descripción', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-search"></span></span>
                    <input type="search" id="aura-maint-search" class="form-control"
                           placeholder="<?php esc_attr_e( 'Buscar equipo, taller, trabajo…', 'aura-suite' ); ?>">
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar por equipo específico', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-hammer"></span></span>
                    <select id="aura-maint-filter-equipment" class="form-select">
                        <option value=""><?php esc_html_e( 'Todos los equipos', 'aura-suite' ); ?></option>
                        <?php foreach ( $equipment_list as $eq ) : ?>
                        <option value="<?php echo esc_attr( $eq->id ); ?>" <?php selected( $preselect_equipment_id, (int) $eq->id ); ?>>
                            <?php echo esc_html( wp_unslash( $eq->name ) . ( $eq->brand ? ' · ' . wp_unslash( $eq->brand ) : '' ) ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar por tipo de mantenimiento', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-category"></span></span>
                    <select id="aura-maint-filter-type" class="form-select">
                        <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
                        <option value="preventive"><?php esc_html_e( 'Preventivo', 'aura-suite' ); ?></option>
                        <option value="corrective"><?php esc_html_e( 'Correctivo', 'aura-suite' ); ?></option>
                        <option value="oil_change"><?php esc_html_e( 'Cambio de aceite', 'aura-suite' ); ?></option>
                        <option value="cleaning"><?php esc_html_e( 'Limpieza', 'aura-suite' ); ?></option>
                        <option value="inspection"><?php esc_html_e( 'Inspección', 'aura-suite' ); ?></option>
                        <option value="major_repair"><?php esc_html_e( 'Reparación mayor', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Ejecutor interno o taller externo', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-businessman"></span></span>
                    <select id="aura-maint-filter-performed" class="form-select">
                        <option value=""><?php esc_html_e( 'Todos los ejecutores', 'aura-suite' ); ?></option>
                        <option value="internal"><?php esc_html_e( 'Personal interno', 'aura-suite' ); ?></option>
                        <option value="external"><?php esc_html_e( 'Taller externo', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Estado operativo resultante', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-flag"></span></span>
                    <select id="aura-maint-filter-post-status" class="form-select">
                        <option value=""><?php esc_html_e( 'Todo estado post-mant.', 'aura-suite' ); ?></option>
                        <option value="operational"><?php esc_html_e( 'Operacional', 'aura-suite' ); ?></option>
                        <option value="needs_followup"><?php esc_html_e( 'Requiere seguimiento', 'aura-suite' ); ?></option>
                        <option value="out_of_service"><?php esc_html_e( 'Fuera de servicio', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Fecha desde', 'aura-suite' ); ?>">
                    <span class="input-group-text"><?php esc_html_e( 'Desde', 'aura-suite' ); ?></span>
                    <input type="date" id="aura-maint-filter-date-from" class="form-control">
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Fecha hasta', 'aura-suite' ); ?>">
                    <span class="input-group-text"><?php esc_html_e( 'Hasta', 'aura-suite' ); ?></span>
                    <input type="date" id="aura-maint-filter-date-to" class="form-control">
                </div>

                <div class="aura-inv-filters-actions">
                    <button type="button" id="aura-maint-filter-apply" class="btn btn-indigo btn-lift" data-tooltip="<?php esc_attr_e( 'Aplicar filtros', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="aura-maint-filter-clear" class="btn btn-glass" data-tooltip="<?php esc_attr_e( 'Limpiar filtros', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla DataTables con Filas Expandibles (+) -->
        <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
            <table id="aura-maint-table" class="aura-table display aura-inv-responsive-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <span class="aura-th-flex">+
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Haz clic en + para desplegar el reporte completo del trabajo y repuestos', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th style="width:68px;">
                            <span class="aura-th-flex"><?php esc_html_e( 'Foto', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Pasa el cursor encima para ver la fotografía del equipo sin recortes', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th>
                            <span class="aura-th-flex"><?php esc_html_e( 'Equipo', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Activo intervenido, marca y código interno', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha de ejecución del mantenimiento', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th>
                            <span class="aura-th-flex"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Preventivo, correctivo, cambio de aceite, limpieza o inspección', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php esc_html_e( 'Ejecutor', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Técnico interno o taller/empresa externa responsable', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php esc_html_e( 'Costo total', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Suma total de repuestos más mano de obra', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php esc_html_e( 'Estado post-mant.', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Operacional, requiere seguimiento o fuera de servicio', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php esc_html_e( 'Finanzas', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Indica si el gasto fue sincronizado con el módulo de Finanzas', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th style="width:120px;text-align:center;">
                            <span class="aura-th-flex"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Ver ficha detallada, editar o eliminar registro', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <?php if ( class_exists( 'Aura_UI' ) && method_exists( 'Aura_UI', 'render_footer' ) ) : ?>
            <?php Aura_UI::render_footer(); ?>
        <?php else : ?>
            <div class="adp-footer">
                <span>Aura Business Suite · <?php _e( 'Módulo de Mantenimiento', 'aura-suite' ); ?></span>
                <span>Aura Design System</span>
            </div>
        <?php endif; ?>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<!-- Modal Detalle del Mantenimiento (Item 26 + Async Skeleton) -->
<div id="aura-maint-detail-modal" class="aura-modal-overlay" data-async-modal="true" role="dialog" aria-modal="true" aria-labelledby="aura-maint-detail-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:860px;">
        <div class="aura-modal-header">
            <h3 id="aura-maint-detail-title" class="aura-modal-title"><?php _e( 'Detalle del Mantenimiento', 'aura-suite' ); ?></h3>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div class="aura-modal-body" id="aura-maint-detail-body"></div>
        <div class="aura-modal-footer">
            <button type="button" class="btn btn-glass aura-maint-modal-dismiss"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
            <?php if ( $can_edit ) : ?>
            <a href="#" id="aura-maint-detail-edit-btn" class="btn btn-shimmer btn-indigo btn-lift">
                <span class="dashicons dashicons-edit"></span>
                <?php _e( 'Editar mantenimiento', 'aura-suite' ); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$_maint_list_js = wp_json_encode( [
    'ajaxurl'        => admin_url( 'admin-ajax.php' ),
    'nonce'          => wp_create_nonce( 'aura_inventory_nonce' ),
    'newUrl'         => admin_url( 'admin.php?page=aura-inventory-new-maintenance' ),
    'currency'       => $currency,
    'preselectEquip' => $preselect_equipment_id,
    'can_edit'       => $can_edit,
    'can_delete'     => $can_delete,
    'txt' => [
        'loading'        => __( 'Cargando mantenimientos…', 'aura-suite' ),
        'no_results'     => __( 'No se encontraron mantenimientos.', 'aura-suite' ),
        'confirm_delete' => __( '¿Eliminar este registro de mantenimiento?', 'aura-suite' ),
        'error'          => __( 'Error al procesar la solicitud.', 'aura-suite' ),
        'deleted'        => __( 'Mantenimiento eliminado.', 'aura-suite' ),
        'page_of'        => __( 'Página %1$s de %2$s', 'aura-suite' ),
        'n_items'        => __( '%s registros', 'aura-suite' ),
        'has_finance'    => __( 'Con transacción', 'aura-suite' ),
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
