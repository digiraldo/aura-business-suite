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

<div class="aura-app-wrapper aura-inventory-equipment-page">
    <div class="wrap aura-app-context">

        <?php
        $header_actions = [];
        if ( $can_create ) {
            $header_actions[] = [
                'label' => __( 'Nuevo Equipo', 'aura-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-inventory-new-equipment' ),
                'class' => 'aura-btn aura-btn-primary',
                'icon'  => 'dashicons-plus-alt',
            ];
        }

        Aura_UI::render_page_header([
            'title'    => __( 'Equipos y Herramientas', 'aura-suite' ),
            'subtitle' => __( 'Control integral de inventario, estado operativo, responsables y ciclo de vida de activos.', 'aura-suite' ),
            'icon'     => 'dashicons-archive',
            'badges'   => [
                ['label' => sprintf( __('%d categorías', 'aura-suite'), count( $categories ) ), 'variant' => 'blue', 'tooltip' => __( 'Categorías activas registradas', 'aura-suite' )],
            ],
            'actions'  => $header_actions,
        ]);
        ?>

        <!-- Barra de Filtros (Glass Card) -->
        <div class="aura-glass-card" style="padding: 18px 20px; margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                <input type="search" id="aura-inv-search" placeholder="<?php esc_attr_e( 'Buscar por nombre, marca, serie…', 'aura-suite' ); ?>" style="flex: 1; min-width: 200px; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px;">

                <select id="aura-inv-filter-category" style="height: 38px; min-width: 160px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value=""><?php _e( 'Todas las categorías', 'aura-suite' ); ?></option>
                    <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="aura-inv-filter-status" style="height: 38px; min-width: 140px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value=""><?php _e( 'Todos los estados', 'aura-suite' ); ?></option>
                    <option value="available"><?php _e( 'Disponible',   'aura-suite' ); ?></option>
                    <option value="in_use"><?php _e( 'En uso',          'aura-suite' ); ?></option>
                    <option value="maintenance"><?php _e( 'Mantenimiento', 'aura-suite' ); ?></option>
                    <option value="repair"><?php _e( 'Reparación',      'aura-suite' ); ?></option>
                    <option value="retired"><?php _e( 'Retirado',       'aura-suite' ); ?></option>
                </select>

                <select id="aura-inv-filter-maintenance" style="height: 38px; min-width: 160px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value="-1"><?php _e( 'Cualquier mantenimiento', 'aura-suite' ); ?></option>
                    <option value="overdue"><?php _e( '🔴 Vencido',        'aura-suite' ); ?></option>
                    <option value="urgent"><?php _e( '🟠 Urgente (≤3d)',   'aura-suite' ); ?></option>
                    <option value="warning"><?php _e( '🟡 Próximo',        'aura-suite' ); ?></option>
                </select>

                <?php if ( ! empty( $areas ) ) : ?>
                <select id="aura-inv-filter-area" style="height: 38px; min-width: 140px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value="0"><?php _e( 'Todas las áreas', 'aura-suite' ); ?></option>
                    <?php foreach ( $areas as $area ) : ?>
                    <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <button id="aura-inv-filter-apply" class="button aura-btn-primary" style="height: 38px;">
                    <span class="dashicons dashicons-search" style="vertical-align: middle;"></span> <?php _e( 'Filtrar', 'aura-suite' ); ?>
                </button>
                <button id="aura-inv-filter-clear" class="button aura-btn-secondary" style="height: 38px;">
                    <?php _e( 'Limpiar', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <!-- Tabla de equipos fluida -->
        <div class="aura-dt-wrapper">
            <table id="aura-inv-equipment-table" class="dataTable aura-table display responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th><?php _e( 'Foto', 'aura-suite' ); ?></th>
                        <th><?php _e( 'Equipo', 'aura-suite' ); ?></th>
                        <th><?php _e( 'Mantenimiento', 'aura-suite' ); ?></th>
                        <th><?php _e( 'Estado', 'aura-suite' ); ?></th>
                        <th><?php _e( 'Categoría', 'aura-suite' ); ?></th>
                        <th><?php _e( 'Ubicación', 'aura-suite' ); ?></th>
                        <th><?php _e( 'Responsable', 'aura-suite' ); ?></th>
                        <th><?php _e( 'Acciones', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<?php
// Scripts específicos del módulo que no están en el core
wp_enqueue_script(
    'aura-inv-equipment-list',
    AURA_PLUGIN_URL . 'assets/js/inventory-equipment.js',
    ['jquery', 'datatables-responsive-js', 'aura-ui-core'],
    filemtime( AURA_PLUGIN_DIR . 'assets/js/inventory-equipment.js' ), true
);
wp_enqueue_style(
    'aura-inv-equipment',
    AURA_PLUGIN_URL . 'assets/css/inventory-equipment.css',
    ['aura-design-system'],
    AURA_VERSION
);
?>



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
