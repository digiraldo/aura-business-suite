<?php
/**
 * Template: Catálogo y Listado de Equipos
 *
 * Ruta: admin.php?page=aura-inventory-equipment
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permisos para ver el catálogo de equipos.', 'aura-suite' ) );
}

$can_create = current_user_can( 'aura_inventory_create' ) || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_inventory_edit'   ) || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_inventory_delete' ) || current_user_can( 'manage_options' );
$can_maint  = current_user_can( 'aura_inventory_maintenance_create' ) || current_user_can( 'manage_options' );

$categories = class_exists( 'Aura_Inventory_Setup' ) ? Aura_Inventory_Setup::get_categories_with_intervals() : [];

global $wpdb;
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: [];

$new_equip_url  = admin_url( 'admin.php?page=aura-inventory-new-equipment' );
$new_maint_url  = admin_url( 'admin.php?page=aura-inventory-maintenance&action=new' );
$maint_list_url = admin_url( 'admin.php?page=aura-inventory-maintenance' );
$loans_url      = admin_url( 'admin.php?page=aura-inventory-loans' );

// Encolado seguro sin duplicar el handle global de aura-business-suite.php
if ( ! wp_style_is( 'aura-inventory-equipment', 'enqueued' ) && ! wp_style_is( 'aura-inv-equipment', 'enqueued' ) ) {
    $css_path = AURA_PLUGIN_DIR . 'assets/css/inventory-equipment.css';
    wp_enqueue_style(
        'aura-inv-equipment',
        AURA_PLUGIN_URL . 'assets/css/inventory-equipment.css',
        [ 'aura-design-system' ],
        file_exists( $css_path ) ? filemtime( $css_path ) : AURA_VERSION
    );
}
if ( ! wp_script_is( 'aura-inventory-equipment', 'enqueued' ) ) {
    $js_path = AURA_PLUGIN_DIR . 'assets/js/inventory-equipment.js';
    wp_enqueue_script(
        'aura-inventory-equipment',
        AURA_PLUGIN_URL . 'assets/js/inventory-equipment.js',
        [ 'jquery', 'aura-ui-core' ],
        file_exists( $js_path ) ? filemtime( $js_path ) : AURA_VERSION,
        true
    );
}

$header_actions = [];
if ( $can_create ) {
    $header_actions[] = [
        'label' => __( 'Nuevo Equipo', 'aura-suite' ),
        'url'   => $new_equip_url,
        'icon'  => 'dashicons-plus-alt2',
        'class' => 'btn btn-shimmer btn-indigo btn-lift',
    ];
}
$header_actions[] = [
    'label' => __( 'Préstamos', 'aura-suite' ),
    'url'   => $loans_url,
    'icon'  => 'dashicons-migrate',
    'class' => 'btn btn-glass btn-lift',
];
$header_actions[] = [
    'label' => __( 'Mantenimientos', 'aura-suite' ),
    'url'   => $maint_list_url,
    'icon'  => 'dashicons-admin-tools',
    'class' => 'btn btn-glass btn-lift',
];
?>

<div class="aura-app-wrapper aura-inventory-equipment-list">
    <div class="wrap aura-app-context">

        <?php
        if ( class_exists( 'Aura_UI' ) ) {
            Aura_UI::render_page_header( [
                'title'    => __( 'Catálogo de Equipos y Activos', 'aura-suite' ),
                'subtitle' => __( 'Consulta el inventario operativo, estado de disponibilidad, programa de mantenimiento y ficha técnica 360°.', 'aura-suite' ),
                'icon'     => 'dashicons-hammer',
                'actions'  => $header_actions,
            ] );
        }
        ?>

        <!-- Barra de filtros Glassmorphic con Input Groups BS 5.3 -->
        <div class="aura-glass-card aura-filters-card">
            <div class="aura-inv-filters-grid">

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Buscar por nombre, marca, modelo, serie o código interno', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-search"></span></span>
                    <input type="search" id="aura-inv-search" class="form-control"
                           placeholder="<?php esc_attr_e( 'Buscar equipo, marca, código, serie…', 'aura-suite' ); ?>">
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar por categoría de activo', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-category"></span></span>
                    <select id="aura-inv-filter-category" class="form-select">
                        <option value=""><?php _e( 'Todas las categorías', 'aura-suite' ); ?></option>
                        <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar por estado operativo actual', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-flag"></span></span>
                    <select id="aura-inv-filter-status" class="form-select">
                        <option value=""><?php _e( 'Todos los estados', 'aura-suite' ); ?></option>
                        <option value="available"><?php _e( 'Disponible', 'aura-suite' ); ?></option>
                        <option value="in_use"><?php _e( 'En uso', 'aura-suite' ); ?></option>
                        <option value="maintenance"><?php _e( 'Mantenimiento', 'aura-suite' ); ?></option>
                        <option value="repair"><?php _e( 'Reparación', 'aura-suite' ); ?></option>
                        <option value="retired"><?php _e( 'Retirado', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar por área o programa asignado', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-building"></span></span>
                    <select id="aura-inv-filter-area" class="form-select">
                        <option value=""><?php _e( 'Todas las áreas', 'aura-suite' ); ?></option>
                        <?php foreach ( $areas as $area ) : ?>
                        <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar por vigencia de mantenimiento', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-admin-tools"></span></span>
                    <select id="aura-inv-filter-maintenance" class="form-select">
                        <option value="-1"><?php _e( 'Todo mantenimiento', 'aura-suite' ); ?></option>
                        <option value="overdue"><?php _e( 'Vencido', 'aura-suite' ); ?></option>
                        <option value="urgent"><?php _e( 'Urgente (≤ 3 días)', 'aura-suite' ); ?></option>
                        <option value="warning"><?php _e( 'Próximo a vencer', 'aura-suite' ); ?></option>
                        <option value="ok"><?php _e( 'Al día', 'aura-suite' ); ?></option>
                        <option value="none"><?php _e( 'Sin programación', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="aura-inv-filters-actions">
                    <button type="button" id="aura-inv-filter-apply" class="btn btn-indigo btn-lift" data-tooltip="<?php esc_attr_e( 'Aplicar filtros de búsqueda', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-filter"></span>
                        <?php _e( 'Filtrar', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="aura-inv-filter-clear" class="btn btn-glass" data-tooltip="<?php esc_attr_e( 'Limpiar todos los filtros', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e( 'Limpiar', 'aura-suite' ); ?>
                    </button>
                </div>

            </div>
        </div>

        <!-- Tabla DataTables con filas expandibles (+) y tooltips enriquecidos -->
        <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
            <table id="aura-inv-equipment-table" class="aura-table display aura-inv-responsive-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <span class="aura-th-flex">+
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Haz clic en + para desplegar especificaciones y acciones rápidas del equipo', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th style="width:68px;">
                            <span class="aura-th-flex"><?php _e( 'Foto', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Pasa el puntero sobre la miniatura para ver la foto ampliada sin recortes', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th>
                            <span class="aura-th-flex"><?php _e( 'Equipo', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Nombre, marca y código interno. Haz clic para abrir la Ficha Técnica 360°', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php _e( 'Mantenimiento', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Estado del ciclo preventivo y fecha próxima programada', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th>
                            <span class="aura-th-flex"><?php _e( 'Estado', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Disponibilidad operativa actual (editable en línea si tienes permisos)', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php _e( 'Categoría', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Clasificación principal del activo', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php _e( 'Ubicación', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Bodega, sede o vehículo donde reside habitualmente el equipo', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php _e( 'Responsable', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Usuario de WordPress o Tercero custodio del activo', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th style="width:140px;text-align:center;">
                            <span class="aura-th-flex"><?php _e( 'Acciones', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Ver Ficha 360°, registrar mantenimiento, editar o eliminar', 'aura-suite' ); ?>">?</span>
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
                <span>Aura Business Suite · <?php _e( 'Módulo de Inventario y Activos', 'aura-suite' ); ?></span>
                <span>Aura Design System</span>
            </div>
        <?php endif; ?>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<!-- Modal Detalle 360° de Equipo (Item 26 + Async Skeleton) -->
<div id="aura-inv-detail-modal" class="aura-modal-overlay" data-async-modal="true" role="dialog" aria-modal="true" aria-labelledby="aura-inv-detail-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:920px;">
        <div class="aura-modal-header">
            <h3 id="aura-inv-detail-title" class="aura-modal-title"><?php _e( 'Ficha Técnica del Equipo', 'aura-suite' ); ?></h3>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div class="aura-modal-body" id="aura-inv-detail-body"></div>
        <div class="aura-modal-footer">
            <button type="button" class="btn btn-glass aura-inv-modal-dismiss"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
            <?php if ( $can_edit ) : ?>
            <a href="#" id="aura-inv-detail-edit-btn" class="btn btn-shimmer btn-indigo btn-lift">
                <span class="dashicons dashicons-edit"></span>
                <?php _e( 'Editar equipo', 'aura-suite' ); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
var auraInventoryEquipment = <?php echo wp_json_encode( [
    'ajaxurl'      => admin_url( 'admin-ajax.php' ),
    'nonce'        => wp_create_nonce( 'aura_inventory_nonce' ),
    'newEquipUrl'  => $new_equip_url,
    'newMaintUrl'  => $new_maint_url,
    'maintListUrl' => $maint_list_url,
    'can_edit'     => $can_edit,
    'can_delete'   => $can_delete,
    'can_maint'    => $can_maint,
    'txt'          => [
        'loading'            => __( 'Cargando catálogo de equipos…', 'aura-suite' ),
        'no_results'         => __( 'No se encontraron equipos con los filtros aplicados.', 'aura-suite' ),
        'error'              => __( 'Ocurrió un error al procesar la solicitud.', 'aura-suite' ),
        'deleted'            => __( 'Equipo eliminado correctamente.', 'aura-suite' ),
        'confirm_delete'     => __( '¿Seguro que deseas eliminar el equipo "{name}"?', 'aura-suite' ),
        'register_maint'     => __( 'Registrar Mantenimiento', 'aura-suite' ),
        'view_maint_history' => __( 'Historial de Mantenimientos', 'aura-suite' ),
        'status_labels'      => [
            'available'   => __( 'Disponible', 'aura-suite' ),
            'in_use'      => __( 'En uso', 'aura-suite' ),
            'maintenance' => __( 'Mantenimiento', 'aura-suite' ),
            'repair'      => __( 'Reparación', 'aura-suite' ),
            'retired'     => __( 'Retirado', 'aura-suite' ),
        ],
        'maint_labels'       => [
            'overdue' => __( 'Vencido', 'aura-suite' ),
            'urgent'  => __( 'Urgente', 'aura-suite' ),
            'warning' => __( 'Próximo', 'aura-suite' ),
            'ok'      => __( 'Al día', 'aura-suite' ),
            'none'    => __( 'N/A', 'aura-suite' ),
        ],
    ],
] ); ?>;
</script>
