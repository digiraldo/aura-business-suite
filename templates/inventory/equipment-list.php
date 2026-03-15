<?php
/**
 * Template: Listado de Equipos e Inventario
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
}

// Categorías para filtros
$categories = Aura_Inventory_Setup::get_categories_with_intervals();

// Áreas disponibles
global $wpdb;
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: [];

$can_create = current_user_can( 'aura_inventory_create' ) || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_inventory_edit'   ) || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_inventory_delete' ) || current_user_can( 'manage_options' );
?>

<div class="wrap aura-inventory-equipment-list">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-archive" style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php _e( 'Equipos y Herramientas', 'aura-suite' ); ?>
    </h1>

    <?php if ( $can_create ) : ?>
    <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-new-equipment' ); ?>" class="page-title-action">
        + <?php _e( 'Nuevo Equipo', 'aura-suite' ); ?>
    </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Filtros -->
    <div class="aura-inv-filters-bar">
        <input type="search" id="aura-inv-search" placeholder="<?php esc_attr_e( 'Buscar por nombre, marca, serie…', 'aura-suite' ); ?>" class="regular-text">

        <select id="aura-inv-filter-category">
            <option value=""><?php _e( 'Todas las categorías', 'aura-suite' ); ?></option>
            <?php foreach ( $categories as $cat ) : ?>
            <option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
            <?php endforeach; ?>
        </select>

        <select id="aura-inv-filter-status">
            <option value=""><?php _e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="available"><?php _e( 'Disponible',   'aura-suite' ); ?></option>
            <option value="in_use"><?php _e( 'En uso',          'aura-suite' ); ?></option>
            <option value="maintenance"><?php _e( 'Mantenimiento', 'aura-suite' ); ?></option>
            <option value="repair"><?php _e( 'Reparación',      'aura-suite' ); ?></option>
            <option value="retired"><?php _e( 'Retirado',       'aura-suite' ); ?></option>
        </select>

        <select id="aura-inv-filter-maintenance">
            <option value="-1"><?php _e( 'Cualquier mantenimiento', 'aura-suite' ); ?></option>
            <option value="overdue"><?php _e( '🔴 Vencido',        'aura-suite' ); ?></option>
            <option value="urgent"><?php _e( '🟠 Urgente (≤3d)',   'aura-suite' ); ?></option>
            <option value="warning"><?php _e( '🟡 Próximo',        'aura-suite' ); ?></option>
        </select>

        <?php if ( ! empty( $areas ) ) : ?>
        <select id="aura-inv-filter-area">
            <option value="0"><?php _e( 'Todas las áreas', 'aura-suite' ); ?></option>
            <?php foreach ( $areas as $area ) : ?>
            <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button id="aura-inv-filter-apply" class="button">
            <span class="dashicons dashicons-search"></span> <?php _e( 'Filtrar', 'aura-suite' ); ?>
        </button>
        <button id="aura-inv-filter-clear" class="button button-link">
            <?php _e( 'Limpiar', 'aura-suite' ); ?>
        </button>
    </div><!-- .aura-inv-filters-bar -->

    <!-- Tabla de equipos -->
    <div id="aura-inv-table-wrap">
        <table id="aura-inv-equipment-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-photo" style="width:58px;"><?php _e( 'Foto', 'aura-suite' ); ?></th>
                    <th class="column-name sortable" data-sort="name"><?php _e( 'Equipo', 'aura-suite' ); ?></th>
                    <th class="column-category"><?php _e( 'Categoría', 'aura-suite' ); ?></th>
                    <th class="column-status"><?php _e( 'Estado', 'aura-suite' ); ?></th>
                    <th class="column-location"><?php _e( 'Ubicación', 'aura-suite' ); ?></th>
                    <th class="column-maintenance"><?php _e( 'Próximo mantenimiento', 'aura-suite' ); ?></th>
                    <th class="column-responsible"><?php _e( 'Responsable', 'aura-suite' ); ?></th>
                    <th class="column-actions"><?php _e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="aura-inv-tbody">
                <tr class="aura-inv-loading-row">
                    <td colspan="8" style="text-align:center;padding:30px;">
                        <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                        <?php _e( 'Cargando equipos…', 'aura-suite' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Paginación -->
        <div id="aura-inv-pagination" class="tablenav bottom" style="display:none;">
            <div class="tablenav-pages">
                <span class="displaying-num" id="aura-inv-total-count"></span>
                <span class="pagination-links">
                    <button id="aura-inv-prev" class="button" disabled>&laquo;</button>
                    <span id="aura-inv-page-info"></span>
                    <button id="aura-inv-next" class="button">&raquo;</button>
                </span>
            </div>
        </div>
    </div><!-- #aura-inv-table-wrap -->

</div><!-- .aura-inventory-equipment-list -->

<!-- Modal: detalle del equipo -->
<div id="aura-inv-detail-modal" class="aura-inv-modal" style="display:none;">
    <div class="aura-inv-modal-overlay"></div>
    <div class="aura-inv-modal-content aura-inv-modal-large">
        <div class="aura-inv-modal-header">
            <h2 id="aura-inv-detail-title"><?php _e( 'Detalle del Equipo', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-inv-modal-close dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>
        <div class="aura-inv-modal-body" id="aura-inv-detail-body">
            <span class="spinner is-active"></span>
        </div>
    </div>
</div>

<!-- Modal: editar equipo (reutiliza el form) -->
<div id="aura-inv-edit-modal" class="aura-inv-modal" style="display:none;">
    <div class="aura-inv-modal-overlay"></div>
    <div class="aura-inv-modal-content aura-inv-modal-large">
        <div class="aura-inv-modal-header">
            <h2><?php _e( 'Editar Equipo', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-inv-modal-close dashicons dashicons-no-alt"></button>
        </div>
        <div class="aura-inv-modal-body" id="aura-inv-edit-form-wrap">
            <span class="spinner is-active"></span>
        </div>
    </div>
</div>

<?php
$_inv_list_js = wp_json_encode( [
    'ajaxurl'     => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'aura_inventory_nonce' ),
    'newEquipUrl' => admin_url( 'admin.php?page=aura-inventory-new-equipment' ),
    'can_edit'    => $can_edit,
    'can_delete'  => $can_delete,
    'txt' => [
        'loading'        => __( 'Cargando…', 'aura-suite' ),
        'no_results'     => __( 'No se encontraron equipos.', 'aura-suite' ),
        'confirm_delete' => __( '¿Eliminar este equipo? Esta acción no se puede deshacer.', 'aura-suite' ),
        'error'          => __( 'Error al procesar la solicitud.', 'aura-suite' ),
        'deleted'        => __( 'Equipo eliminado correctamente.', 'aura-suite' ),
        'saved'          => __( 'Equipo guardado correctamente.', 'aura-suite' ),
        'page_of'        => __( 'Página %1$s de %2$s', 'aura-suite' ),
        'n_items'        => __( '%s equipos', 'aura-suite' ),
        'detail_title'   => __( 'Detalle: %s', 'aura-suite' ),
        'status_labels'  => [
            'available'   => __( 'Disponible',   'aura-suite' ),
            'in_use'      => __( 'En uso',        'aura-suite' ),
            'maintenance' => __( 'Mantenimiento', 'aura-suite' ),
            'repair'      => __( 'Reparación',    'aura-suite' ),
            'retired'     => __( 'Retirado',      'aura-suite' ),
        ],
        'maint_labels'   => [
            'overdue'  => __( '🔴 Vencido', 'aura-suite' ),
            'urgent'   => __( '🟠 Urgente', 'aura-suite' ),
            'warning'  => __( '🟡 Próximo', 'aura-suite' ),
            'ok'       => __( '✅ Ok',       'aura-suite' ),
            'none'     => __( '—',          'aura-suite' ),
        ],
    ],
] );
?>
<script>/* Datos inyectados PHP → JS */
var auraInventoryEquipment = <?php echo $_inv_list_js; ?>;
</script>
