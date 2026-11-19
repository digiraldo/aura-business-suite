<?php
/**
 * Template: Formulario de Alta / Edición de Equipo
 *
 * Ruta:  admin.php?page=aura-inventory-new-equipment           → Alta
 *        admin.php?page=aura-inventory-new-equipment&id=N      → Edición
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$can_create = current_user_can( 'aura_inventory_create' ) || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_inventory_edit'   ) || current_user_can( 'manage_options' );

if ( ! $can_create && ! $can_edit ) {
    wp_die( __( 'No tienes permisos para realizar esta acción.', 'aura-suite' ) );
}

$equipment_id = intval( $_GET['id'] ?? 0 );
$is_edit      = $equipment_id > 0;
$equipment    = null;

if ( $is_edit ) {
    global $wpdb;
    $equipment = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aura_inventory_equipment WHERE id = %d AND deleted_at IS NULL",
        $equipment_id
    ) );
    if ( ! $equipment ) {
        wp_die( __( 'Equipo no encontrado.', 'aura-suite' ) );
    }
}

// Datos para los selectores
$categories = Aura_Inventory_Setup::get_categories_with_intervals();

global $wpdb;
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: [];

$users = Aura_Roles_Manager::get_aura_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

// Lista de equipos disponibles para vinculación como componente (excluye el actual)
$all_equipment_for_parent = $wpdb->get_results(
    "SELECT id, name, brand, internal_code FROM {$wpdb->prefix}aura_inventory_equipment WHERE deleted_at IS NULL"
    . ( $equipment_id ? $wpdb->prepare( ' AND id != %d', $equipment_id ) : '' )
    . ' ORDER BY name ASC'
) ?: [];

// Helper para autorellenar campos en edición
$v = function( string $field, $default = '' ) use ( $equipment ) {
    $val = $equipment ? ( $equipment->$field ?? $default ) : $default;
    return esc_attr( is_string( $val ) ? wp_unslash( $val ) : $val );
};
$checked = function( string $field ) use ( $equipment ) {
    return ( $equipment && $equipment->$field ) ? 'checked' : '';
};

$inv_settings = class_exists( 'Aura_Inventory_Categories' ) ? Aura_Inventory_Categories::get_settings() : [];
$code_info    = method_exists( 'Aura_Inventory_Equipment', 'get_code_suggestions' )
    ? Aura_Inventory_Equipment::get_code_suggestions( $equipment ? intval( $equipment->parent_equipment_id ?? 0 ) : 0 )
    : [
        'prefix'          => $inv_settings['code_prefix'] ?? 'CEM',
        'digits'          => $inv_settings['code_digits'] ?? 3,
        'last_main_code'  => '',
        'last_child_code' => '',
        'suggested_code'  => '',
        'is_child'        => false,
    ];
$current_internal_code = $v( 'internal_code' );
$initial_auto_code     = ( ! $is_edit && $current_internal_code === '' && ! empty( $code_info['suggested_code'] ) );
$page_title            = $is_edit ? __( 'Editar Equipo', 'aura-suite' ) : __( 'Registrar Nuevo Equipo', 'aura-suite' );
?>

<div class="aura-app-wrapper aura-inventory-equipment-form">
    <div class="wrap aura-app-context">

    <?php
    if ( class_exists( 'Aura_UI' ) ) {
        Aura_UI::render_page_header( [
            'title'    => $page_title,
            'subtitle' => $is_edit
                ? __( 'Actualiza especificaciones técnicas, mantenimiento programado, responsable y fotografía del equipo.', 'aura-suite' )
                : __( 'Registra un nuevo activo o equipo en el catálogo, su configuración de mantenimiento y asignación.', 'aura-suite' ),
            'icon'     => $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt',
            'actions'  => [
                [
                    'label' => __( 'Volver al Catálogo', 'aura-suite' ),
                    'url'   => admin_url( 'admin.php?page=aura-inventory-equipment' ),
                    'icon'  => 'dashicons-arrow-left-alt2',
                    'class' => 'btn btn-glass btn-lift',
                ],
            ],
        ] );
    }
    ?>

    <form id="aura-inv-equipment-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'aura_inventory_nonce', '_inv_nonce' ); ?>
        <input type="hidden" name="equipment_id" id="equipment_id" value="<?php echo $equipment_id; ?>">

        <div class="aura-inv-form-layout">

            <!-- ── Columna principal ─────────────────────────────── -->
            <div class="aura-inv-form-main">

                <!-- Sección 1: Información básica -->
                <div class="aura-glass-card aura-inv-form-card">
                    <div class="aura-inv-card-header">
                        <h2>
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php _e( 'Información básica', 'aura-suite' ); ?>
                        </h2>
                    </div>
                    <div class="aura-inv-card-body">
                        <table class="form-table aura-inv-form-table">
                            <tr>
                                <th><label for="inv_name"><?php _e( 'Nombre del equipo', 'aura-suite' ); ?> <span class="required">*</span></label></th>
                                <td><input type="text" id="inv_name" name="name" value="<?php echo $v('name'); ?>" class="form-control" required></td>
                            </tr>
                            <tr>
                                <th><label for="inv_brand"><?php _e( 'Marca', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_brand" name="brand" value="<?php echo $v('brand'); ?>" class="form-control"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_model"><?php _e( 'Modelo', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_model" name="model" value="<?php echo $v('model'); ?>" class="form-control"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_serial"><?php _e( 'Número de serie', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_serial" name="serial_number" value="<?php echo $v('serial_number'); ?>" class="form-control"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_code"><?php _e( 'Código interno', 'aura-suite' ); ?></label></th>
                                <td>
                                    <div class="input-group">
                                        <input type="text"
                                               id="inv_code"
                                               name="internal_code"
                                               value="<?php echo $initial_auto_code ? esc_attr( $code_info['suggested_code'] ) : $current_internal_code; ?>"
                                               data-auto-filled="<?php echo $initial_auto_code ? '1' : '0'; ?>"
                                               class="form-control"
                                               placeholder="<?php echo esc_attr( $code_info['suggested_code'] ?: 'CEM-001' ); ?>">
                                        <button type="button"
                                                id="aura-inv-apply-suggested-code"
                                                class="btn btn-glass"
                                                data-suggested="<?php echo esc_attr( $code_info['suggested_code'] ?? '' ); ?>"
                                                data-tooltip="<?php esc_attr_e( 'Aplicar siguiente código sugerido automáticamente', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-tag"></span>
                                            <span id="aura-inv-suggest-btn-label"><?php echo esc_html( $code_info['suggested_code'] ? sprintf( __( 'Usar %s', 'aura-suite' ), $code_info['suggested_code'] ) : __( 'Sugerir', 'aura-suite' ) ); ?></span>
                                        </button>
                                    </div>
                                    <div id="aura-inv-code-helper" class="aura-inv-code-helper">
                                        <span class="aura-inv-code-helper-item">
                                            <span class="dashicons dashicons-clock"></span>
                                            <span id="aura-inv-last-code-text">
                                                <?php
                                                if ( ! empty( $code_info['is_child'] ) ) {
                                                    echo ! empty( $code_info['last_child_code'] )
                                                        ? sprintf( esc_html__( 'Último componente del padre: %s', 'aura-suite' ), '<strong>' . esc_html( $code_info['last_child_code'] ) . '</strong>' )
                                                        : sprintf( esc_html__( 'Padre: %s (primer componente)', 'aura-suite' ), '<strong>' . esc_html( $code_info['parent_code'] ?: '—' ) . '</strong>' );
                                                } else {
                                                    echo ! empty( $code_info['last_main_code'] )
                                                        ? sprintf( esc_html__( 'Último código registrado: %s', 'aura-suite' ), '<strong>' . esc_html( $code_info['last_main_code'] ) . '</strong>' )
                                                        : esc_html__( 'Aún no hay códigos con este prefijo.', 'aura-suite' );
                                                }
                                                ?>
                                            </span>
                                        </span>
                                        <span class="aura-inv-code-helper-item aura-inv-code-next">
                                            <?php _e( 'Siguiente:', 'aura-suite' ); ?>
                                            <strong id="aura-inv-next-code-text"><?php echo esc_html( $code_info['suggested_code'] ?: '—' ); ?></strong>
                                        </span>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-settings&tab=general' ) ); ?>" class="aura-inv-code-cfg-link" data-tooltip="<?php esc_attr_e( 'Configurar prefijo e iniciales de la organización', 'aura-suite' ); ?>">
                                            <?php printf( esc_html__( 'Prefijo: %s', 'aura-suite' ), esc_html( $code_info['prefix'] ?? 'CEM' ) ); ?> ⚙
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_category"><?php _e( 'Categoría', 'aura-suite' ); ?></label></th>
                                <td>
                                    <select id="inv_category" name="category" class="form-select">
                                        <option value=""><?php _e( '— Seleccionar —', 'aura-suite' ); ?></option>
                                        <?php foreach ( $categories as $cat ) : ?>
                                        <option value="<?php echo esc_attr( $cat->slug ); ?>"
                                            data-interval-type="<?php echo esc_attr( $cat->interval_type ); ?>"
                                            data-interval-months="<?php echo esc_attr( $cat->interval_months ); ?>"
                                            data-interval-hours="<?php echo esc_attr( $cat->interval_hours ); ?>"
                                            <?php selected( $v('category'), $cat->slug ); ?>>
                                            <?php echo esc_html( $cat->name ); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description" id="inv_category_hint"></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_status"><?php _e( 'Estado', 'aura-suite' ); ?></label></th>
                                <td>
                                    <select id="inv_status" name="status" class="form-select">
                                        <option value="available"   <?php selected( $v('status','available'), 'available'   ); ?>><?php _e( 'Disponible',   'aura-suite' ); ?></option>
                                        <option value="in_use"      <?php selected( $v('status','available'), 'in_use'      ); ?>><?php _e( 'En uso',        'aura-suite' ); ?></option>
                                        <option value="maintenance" <?php selected( $v('status','available'), 'maintenance' ); ?>><?php _e( 'Mantenimiento', 'aura-suite' ); ?></option>
                                        <option value="repair"      <?php selected( $v('status','available'), 'repair'      ); ?>><?php _e( 'Reparación',    'aura-suite' ); ?></option>
                                        <option value="retired"     <?php selected( $v('status','available'), 'retired'     ); ?>><?php _e( 'Retirado',      'aura-suite' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_location"><?php _e( 'Ubicación', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_location" name="location" value="<?php echo $v('location'); ?>" class="form-control" placeholder="<?php esc_attr_e( 'Bodega, sala, vehículo…', 'aura-suite' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_description"><?php _e( 'Descripción', 'aura-suite' ); ?></label></th>
                                <td><textarea id="inv_description" name="description" rows="3" class="form-control"><?php echo esc_textarea( wp_unslash( $equipment->description ?? '' ) ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="inv_accessories"><?php _e( 'Accesorios incluidos', 'aura-suite' ); ?></label></th>
                                <td>
                                    <textarea id="inv_accessories" name="accessories" rows="4" class="form-control"
                                        placeholder="<?php esc_attr_e( 'Ej:\nCargador rápido 20V\n2 Baterías 4Ah\nEstuche de transporte', 'aura-suite' ); ?>"><?php echo esc_textarea( wp_unslash( $equipment->accessories ?? '' ) ); ?></textarea>
                                    <p class="description"><?php _e( 'Un accesorio por línea. Lista los accesorios de dotación que vienen con el equipo (informativos).', 'aura-suite' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div><!-- card: información básica -->

                <!-- Sección 2: Adquisición y valor -->
                <div class="aura-glass-card aura-inv-form-card">
                    <div class="aura-inv-card-header">
                        <h2>
                            <span class="dashicons dashicons-cart"></span>
                            <?php _e( 'Adquisición y valor', 'aura-suite' ); ?>
                        </h2>
                    </div>
                    <div class="aura-inv-card-body">
                        <table class="form-table aura-inv-form-table">
                            <tr>
                                <th><label for="inv_acquisition_date"><?php _e( 'Fecha de adquisición', 'aura-suite' ); ?></label></th>
                                <td><input type="date" id="inv_acquisition_date" name="acquisition_date" value="<?php echo $v('acquisition_date'); ?>" class="form-control"></td>
                            </tr>
                            <?php $inv_currency_sym = function_exists( 'aura_get_currency_symbol' ) ? aura_get_currency_symbol() : get_option( 'aura_currency_symbol', '$' ); ?>
                            <tr>
                                <th><label for="inv_cost"><?php _e( 'Costo de adquisición', 'aura-suite' ); ?></label></th>
                                <td>
                                    <div class="input-group aura-inv-compact-group">
                                        <span class="input-group-text"><?php echo esc_html( $inv_currency_sym ); ?></span>
                                        <input type="number" id="inv_cost" name="cost" value="<?php echo $v('cost','0'); ?>" step="0.01" min="0" class="form-control">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_estimated_value"><?php _e( 'Valor estimado actual', 'aura-suite' ); ?></label></th>
                                <td>
                                    <div class="input-group aura-inv-compact-group">
                                        <span class="input-group-text"><?php echo esc_html( $inv_currency_sym ); ?></span>
                                        <input type="number" id="inv_estimated_value" name="estimated_value" value="<?php echo $v('estimated_value','0'); ?>" step="0.01" min="0" class="form-control">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_supplier"><?php _e( 'Proveedor', 'aura-suite' ); ?></label></th>
                                <td>
                                    <?php $sup_tp_id = intval( $v( 'supplier_third_party_id', 0 ) ); ?>
                                    <input type="hidden" id="inv_supplier_third_party_id" name="supplier_third_party_id" value="<?php echo esc_attr( $sup_tp_id ?: '' ); ?>">
                                    <div class="input-group">
                                        <input type="text" id="inv_supplier" name="supplier" value="<?php echo esc_attr( wp_unslash( $equipment ? ( $equipment->supplier ?? '' ) : '' ) ); ?>"
                                               class="form-control" autocomplete="off"
                                               placeholder="<?php esc_attr_e( 'Buscar o escribir proveedor…', 'aura-suite' ); ?>">
                                        <button type="button" id="aura-inv-btn-supplier-explorer" class="btn btn-glass" data-tooltip="<?php esc_attr_e( 'Explorar Directorio de Terceros', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-groups"></span>
                                            <?php _e( 'Explorar Terceros', 'aura-suite' ); ?>
                                        </button>
                                        <button type="button" id="aura-inv-btn-supplier-clear" class="btn btn-glass"
                                                title="<?php esc_attr_e( 'Desvincular proveedor', 'aura-suite' ); ?>"
                                                style="<?php echo $sup_tp_id ? '' : 'display:none;'; ?>">&times;</button>
                                    </div>
                                    <div id="aura-inv-supplier-badge" style="margin-top:6px;<?php echo $sup_tp_id ? '' : 'display:none;'; ?>">
                                        <span class="aura-inv-link-pill">
                                            🔗 <?php _e( 'Vinculado a Tercero del Directorio', 'aura-suite' ); ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_warranty_date"><?php _e( 'Vencimiento de garantía', 'aura-suite' ); ?></label></th>
                                <td><input type="date" id="inv_warranty_date" name="warranty_date" value="<?php echo $v('warranty_date'); ?>" data-allow-future="1" class="form-control"></td>
                            </tr>
                        </table>
                    </div>
                </div><!-- card: adquisición -->

                <!-- Sección 3: Mantenimiento periódico -->
                <div class="aura-glass-card aura-inv-form-card">
                    <div class="aura-inv-card-header">
                        <h2>
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e( 'Mantenimiento periódico', 'aura-suite' ); ?>
                        </h2>
                    </div>
                    <div class="aura-inv-card-body">
                        <table class="form-table aura-inv-form-table">
                            <tr>
                                <th><?php _e( '¿Requiere mantenimiento?', 'aura-suite' ); ?></th>
                                <td>
                                    <label class="aura-inv-check-label">
                                        <input type="checkbox" id="inv_requires_maintenance" name="requires_maintenance" value="1" <?php echo $checked('requires_maintenance'); ?>>
                                        <?php _e( 'Este equipo tiene mantenimiento periódico programado', 'aura-suite' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <div id="aura-inv-maintenance-fields" <?php echo ( $equipment && $equipment->requires_maintenance ) ? '' : 'style="display:none;"'; ?>>
                            <table class="form-table aura-inv-form-table">
                                <tr>
                                    <th><?php _e( 'Tipo de intervalo', 'aura-suite' ); ?></th>
                                    <td class="aura-inv-radio-row">
                                        <label><input type="radio" name="interval_type" value="time"  <?php checked( $v('interval_type','time'), 'time'  ); ?>> <?php _e( 'Por tiempo',        'aura-suite' ); ?></label>
                                        <label><input type="radio" name="interval_type" value="hours" <?php checked( $v('interval_type','time'), 'hours' ); ?>> <?php _e( 'Por horas de uso', 'aura-suite' ); ?></label>
                                        <label><input type="radio" name="interval_type" value="both"  <?php checked( $v('interval_type','time'), 'both'  ); ?>> <?php _e( 'Ambos',             'aura-suite' ); ?></label>
                                    </td>
                                </tr>
                                <tr id="inv_row_months">
                                    <th><label for="inv_interval_months"><?php _e( 'Cada (meses)', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <div class="input-group aura-inv-compact-group">
                                            <input type="number" id="inv_interval_months" name="interval_months" value="<?php echo $v('interval_months',''); ?>" min="1" max="120" class="form-control">
                                            <span class="input-group-text"><?php _e( 'meses', 'aura-suite' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="inv_row_hours">
                                    <th><label for="inv_interval_hours"><?php _e( 'Cada (horas)', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <div class="input-group aura-inv-compact-group">
                                            <input type="number" id="inv_interval_hours" name="interval_hours" value="<?php echo $v('interval_hours',''); ?>" min="1" max="10000" class="form-control">
                                            <span class="input-group-text"><?php _e( 'horas', 'aura-suite' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="inv_alert_days"><?php _e( 'Alerta con (días)', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <div class="input-group aura-inv-compact-group">
                                            <input type="number" id="inv_alert_days" name="alert_days_before" value="<?php echo $v('alert_days_before','7'); ?>" min="1" max="60" class="form-control">
                                            <span class="input-group-text"><?php _e( 'días antes', 'aura-suite' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div><!-- #aura-inv-maintenance-fields -->

                        <!-- Instrucciones de mantenimiento (siempre visible) -->
                        <table class="form-table aura-inv-form-table aura-inv-maint-instr-sep">
                            <tr>
                                <th>
                                    <label for="inv_maintenance_instructions">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        <?php _e( 'Instrucciones de mantenimiento', 'aura-suite' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea id="inv_maintenance_instructions"
                                              name="maintenance_instructions"
                                              rows="5"
                                              class="form-control"
                                              placeholder="<?php esc_attr_e( "Describe qué debe hacerse en cada mantenimiento:\n- Cambio de aceite SAE 10W-30 (0.6 L)\n- Limpiar filtro de aire\n- Revisar y ajustar bujía\n- Inspeccionar correa de transmisión\n- Verificar nivel de combustible y fugas", 'aura-suite' ); ?>"><?php echo esc_textarea( wp_unslash( $equipment->maintenance_instructions ?? '' ) ); ?></textarea>
                                    <p class="description">
                                        <span class="dashicons dashicons-info"></span>
                                        <?php _e( 'Este texto será visible para el técnico al registrar un mantenimiento. Documenta el procedimiento específico, tipos de aceite, presiones, repuestos estándar, etc.', 'aura-suite' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div><!-- card: mantenimiento -->

                <!-- Sección 4: Especificaciones técnicas -->
                <div class="aura-glass-card aura-inv-form-card">
                    <div class="aura-inv-card-header">
                        <h2>
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php _e( 'Especificaciones técnicas', 'aura-suite' ); ?>
                            <small>(<?php _e('opcional', 'aura-suite'); ?>)</small>
                        </h2>
                    </div>
                    <div class="aura-inv-card-body">
                        <table class="form-table aura-inv-form-table">
                            <tr>
                                <th><label for="inv_oil_type"><?php _e( 'Tipo de aceite', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_oil_type" name="oil_type" value="<?php echo $v('oil_type'); ?>" class="form-control" placeholder="SAE 10W-40"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_oil_capacity"><?php _e( 'Capacidad de aceite (L)', 'aura-suite' ); ?></label></th>
                                <td>
                                    <div class="input-group aura-inv-compact-group">
                                        <input type="number" id="inv_oil_capacity" name="oil_capacity" value="<?php echo $v('oil_capacity'); ?>" step="0.1" min="0" class="form-control">
                                        <span class="input-group-text">L</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_fuel_type"><?php _e( 'Tipo de combustible', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_fuel_type" name="fuel_type" value="<?php echo $v('fuel_type'); ?>" class="form-control" placeholder="Gasolina, Diésel…"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_voltage"><?php _e( 'Voltaje', 'aura-suite' ); ?></label></th>
                                <td>
                                    <div class="input-group aura-inv-compact-group">
                                        <input type="number" id="inv_voltage" name="voltage" value="<?php echo $v('voltage'); ?>" min="0" class="form-control">
                                        <span class="input-group-text">V</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_hydraulic_pressure"><?php _e( 'Presión hidráulica', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_hydraulic_pressure" name="hydraulic_pressure" value="<?php echo $v('hydraulic_pressure'); ?>" class="form-control" placeholder="ej: 3000 PSI"></td>
                            </tr>
                        </table>
                    </div>
                </div><!-- card: especificaciones -->

            </div><!-- .aura-inv-form-main -->

            <!-- ── Columna lateral ───────────────────────────────── -->
            <div class="aura-inv-form-sidebar">

                <!-- Foto -->
                <div class="aura-glass-card aura-inv-form-card">
                    <div class="aura-inv-card-header">
                        <h2>
                            <span class="dashicons dashicons-format-image"></span>
                            <?php _e( 'Foto del equipo', 'aura-suite' ); ?>
                        </h2>
                    </div>
                    <div class="aura-inv-card-body aura-inv-photo-box">
                        <div id="aura-inv-photo-preview" class="aura-inv-photo-surface">
                            <?php
                            $has_photo = ( $equipment && $equipment->photo );
                            if ( $has_photo ) :
                                $preview_urls = function_exists( 'aura_get_equipment_photo_urls' )
                                    ? aura_get_equipment_photo_urls( $equipment->photo )
                                    : [ 'full' => ( is_numeric( $equipment->photo ) ? wp_get_attachment_url( (int) $equipment->photo ) : $equipment->photo ) ];
                                $preview_url = $preview_urls['full'] ?? '';
                                if ( $preview_url ) : ?>
                            <img src="<?php echo esc_url( $preview_url ); ?>" alt="" style="max-width:100%;max-height:240px;width:auto;height:auto;object-fit:contain;margin:0 auto;border-radius:8px;">
                                <?php endif;
                            else : ?>
                            <span class="dashicons dashicons-format-image aura-inv-photo-placeholder"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" id="inv_photo" name="photo" value="<?php echo $v('photo'); ?>">
                        <button type="button" id="aura-inv-select-photo" class="btn btn-glass btn-lift" style="width:100%;margin-top:10px;justify-content:center;">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e( 'Seleccionar imagen', 'aura-suite' ); ?>
                        </button>
                        <p class="aura-inv-photo-hint">
                            <?php _e( 'Los PNG con transparencia muestran cuadrícula de contraste adaptable a modo claro y oscuro.', 'aura-suite' ); ?>
                        </p>
                        <button type="button" id="aura-inv-remove-photo" class="btn btn-glass aura-btn-danger-outline" style="width:100%;margin-top:6px;justify-content:center;<?php echo $has_photo ? '' : 'display:none;'; ?>">
                            <?php _e( 'Quitar imagen', 'aura-suite' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Asignación -->
                <div class="aura-glass-card aura-inv-form-card">
                    <div class="aura-inv-card-header">
                        <h2>
                            <span class="dashicons dashicons-admin-users"></span>
                            <?php _e( 'Asignación', 'aura-suite' ); ?>
                        </h2>
                    </div>
                    <div class="aura-inv-card-body">
                        <?php
                        $resp_user_id = intval( $v( 'responsible_user_id', 0 ) );
                        $resp_tp_id   = intval( $v( 'responsible_third_party_id', 0 ) );
                        $resp_display = '';
                        $resp_type_label = '';
                        if ( $resp_user_id ) {
                            $u_obj = get_user_by( 'id', $resp_user_id );
                            if ( $u_obj ) {
                                $resp_display    = $u_obj->display_name;
                                $resp_type_label = __( 'Usuario WP', 'aura-suite' );
                            }
                        } elseif ( $resp_tp_id ) {
                            global $wpdb;
                            $tp_name = $wpdb->get_var( $wpdb->prepare(
                                "SELECT COALESCE(NULLIF(commercial_name, ''), name) FROM {$wpdb->prefix}aura_third_parties WHERE id = %d",
                                $resp_tp_id
                            ) );
                            if ( $tp_name ) {
                                $resp_display    = $tp_name;
                                $resp_type_label = __( 'Tercero', 'aura-suite' );
                            }
                        }
                        ?>
                        <div class="aura-inv-sidebar-field">
                            <label for="inv_responsible_display"><?php _e( 'Responsable', 'aura-suite' ); ?></label>
                            <input type="hidden" id="inv_responsible_user_id" name="responsible_user_id" value="<?php echo esc_attr( $resp_user_id ?: '' ); ?>">
                            <input type="hidden" id="inv_responsible_third_party_id" name="responsible_third_party_id" value="<?php echo esc_attr( $resp_tp_id ?: '' ); ?>">
                            <div class="input-group" style="margin-top:6px;">
                                <input type="text" id="inv_responsible_display" class="form-control" autocomplete="off"
                                       value="<?php echo esc_attr( $resp_display ); ?>"
                                       placeholder="<?php esc_attr_e( 'Buscar usuario WP o tercero…', 'aura-suite' ); ?>">
                                <button type="button" id="aura-inv-btn-responsible-clear" class="btn btn-glass"
                                        title="<?php esc_attr_e( 'Quitar responsable', 'aura-suite' ); ?>"
                                        style="<?php echo ( $resp_user_id || $resp_tp_id ) ? '' : 'display:none;'; ?>">&times;</button>
                            </div>
                            <div style="display:flex;gap:6px;margin-top:8px;">
                                <button type="button" id="aura-inv-btn-responsible-explorer" class="btn btn-glass" style="flex:1;justify-content:center;" data-tooltip="<?php esc_attr_e( 'Abrir selector de Usuarios del sistema', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php _e( 'Explorar Usuarios', 'aura-suite' ); ?>
                                </button>
                                <button type="button" id="aura-inv-btn-responsible-tp-explorer" class="btn btn-glass" style="justify-content:center;" data-tooltip="<?php esc_attr_e( 'Buscar tercero externo (contratista, técnico externo)', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-groups"></span>
                                    <?php _e( 'Terceros', 'aura-suite' ); ?>
                                </button>
                            </div>
                            <div id="aura-inv-responsible-badge" style="margin-top:6px;<?php echo $resp_type_label ? '' : 'display:none;'; ?>">
                                <span class="aura-inv-link-pill aura-inv-link-pill-purple">
                                    👤 <span id="aura-inv-responsible-badge-text"><?php echo esc_html( $resp_type_label ); ?></span>
                                </span>
                            </div>
                        </div>
                        <?php if ( ! empty( $areas ) ) : ?>
                        <div class="aura-inv-sidebar-field" style="margin-top:14px;">
                            <label for="inv_area"><?php _e( 'Área / Programa', 'aura-suite' ); ?></label>
                            <select id="inv_area" name="area_id" class="form-select" style="margin-top:6px;">
                                <option value=""><?php _e( '— Sin área —', 'aura-suite' ); ?></option>
                                <?php foreach ( $areas as $area ) : ?>
                                <option value="<?php echo esc_attr( $area->id ); ?>"
                                    <?php selected( intval( $v('area_id') ), intval( $area->id ) ); ?>>
                                    <?php echo esc_html( $area->name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div><!-- card: asignación -->

                <!-- Equipo padre (componente de) -->
                <div class="aura-glass-card aura-inv-form-card">
                    <div class="aura-inv-card-header">
                        <h2>
                            <span class="dashicons dashicons-networking"></span>
                            <?php _e( 'Componente de equipo', 'aura-suite' ); ?>
                        </h2>
                    </div>
                    <div class="aura-inv-card-body">
                        <div class="aura-inv-sidebar-field">
                            <label for="inv_parent_equipment"><?php _e( 'Equipo padre', 'aura-suite' ); ?></label>
                            <select id="inv_parent_equipment" name="parent_equipment_id" class="form-select" style="margin-top:6px;">
                                <option value=""><?php _e( '— Equipo independiente —', 'aura-suite' ); ?></option>
                                <?php foreach ( $all_equipment_for_parent as $peq ) :
                                    $plabel = esc_html( wp_unslash( $peq->name ) );
                                    if ( $peq->brand )         $plabel .= ' · ' . esc_html( wp_unslash( $peq->brand ) );
                                    if ( $peq->internal_code ) $plabel .= ' (' . esc_html( wp_unslash( $peq->internal_code ) ) . ')';
                                ?>
                                <option value="<?php echo esc_attr( $peq->id ); ?>"
                                    <?php selected( intval( $v('parent_equipment_id') ), intval( $peq->id ) ); ?>>
                                    <?php echo $plabel; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="description" style="margin-top:6px;"><?php _e( 'Selecciona si este ítem es un componente reemplazable (batería, cargador, accesorio con vida útil propia) de otro equipo.', 'aura-suite' ); ?></p>
                    </div>
                </div><!-- card: equipo padre -->

                <!-- Acciones -->
                <div class="aura-glass-card aura-inv-form-card aura-inv-form-actions-card">
                    <div class="aura-inv-card-body" style="text-align:center;">
                        <button type="submit" id="aura-inv-save-btn" class="btn btn-shimmer btn-indigo btn-lift" style="width:100%;margin-bottom:8px;justify-content:center;">
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo $is_edit ? esc_html__( 'Actualizar equipo', 'aura-suite' ) : esc_html__( 'Registrar equipo', 'aura-suite' ); ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-equipment' ) ); ?>" class="btn btn-glass" style="width:100%;justify-content:center;">
                            <?php _e( 'Cancelar', 'aura-suite' ); ?>
                        </a>
                        <div id="aura-inv-form-notice" style="margin-top:12px;"></div>
                    </div>
                </div>

            </div><!-- .aura-inv-form-sidebar -->

        </div><!-- .aura-inv-form-layout -->
    </form>

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

<!-- ── Modal de recorte de imagen (Cropper.js — Item 26) ───────────────────── -->
<div id="aura-crop-modal" class="aura-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="aura-crop-modal-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:700px;">
        <div class="aura-modal-header">
            <h3 id="aura-crop-modal-title" class="aura-modal-title">
                <span class="dashicons dashicons-crop"></span>
                <?php _e( 'Ajustar imagen del equipo', 'aura-suite' ); ?>
            </h3>
            <button type="button" id="aura-crop-close" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div class="aura-modal-body">
            <div class="aura-inv-crop-ratios" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                <button type="button" class="btn btn-glass aura-crop-ratio-btn is-active" data-ratio="NaN"><?php _e( 'Proporción original / libre', 'aura-suite' ); ?></button>
                <button type="button" class="btn btn-glass aura-crop-ratio-btn" data-ratio="1.333333"><?php _e( 'Horizontal 4:3', 'aura-suite' ); ?></button>
                <button type="button" class="btn btn-glass aura-crop-ratio-btn" data-ratio="1"><?php _e( 'Cuadrada 1:1', 'aura-suite' ); ?></button>
            </div>
            <div id="aura-crop-container" class="aura-inv-crop-box">
                <img id="aura-crop-img" style="display:block;max-width:100%;" src="" alt="">
            </div>
            <p class="aura-inv-photo-hint" style="margin-top:10px;">
                <?php _e( 'Arrastra o redimensiona el recuadro para encuadrar la fotografía sin perder su orientación, o usa la imagen original completa.', 'aura-suite' ); ?>
            </p>
        </div>
        <div class="aura-modal-footer">
            <button type="button" id="aura-crop-cancel" class="btn btn-glass"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-crop-use-original" class="btn btn-glass"><?php _e( 'Usar original sin recortar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-crop-apply" class="btn btn-shimmer btn-indigo btn-lift">
                <span class="dashicons dashicons-yes"></span>
                <?php _e( 'Aplicar y usar imagen', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<script>
var auraInvFormData = <?php echo wp_json_encode( [
    'ajaxurl'        => admin_url( 'admin-ajax.php' ),
    'nonce'          => wp_create_nonce( 'aura_inventory_nonce' ),
    'equipmentId'    => $equipment_id,
    'isEdit'         => $is_edit,
    'listUrl'        => admin_url( 'admin.php?page=aura-inventory-equipment' ),
    'codeSuggestion' => $code_info,
    'txt' => [
        'saving'    => __( 'Guardando…',                      'aura-suite' ),
        'saved'     => __( 'Equipo guardado correctamente.',  'aura-suite' ),
        'error'     => __( 'Error al guardar el equipo.',     'aura-suite' ),
        'required'  => __( 'El nombre del equipo es obligatorio.', 'aura-suite' ),
    ],
    'categoryHints' => array_reduce( $categories, function( $carry, $cat ) {
        $hints = [];
        if ( $cat->interval_type === 'time' || $cat->interval_type === 'both' ) {
            $hints[] = sprintf( __( 'Intervalo recomendado: cada %d meses', 'aura-suite' ), $cat->interval_months );
        }
        if ( $cat->interval_type === 'hours' || $cat->interval_type === 'both' ) {
            $hints[] = sprintf( __( 'o cada %d horas', 'aura-suite' ), $cat->interval_hours );
        }
        if ( $cat->interval_type === 'none' ) {
            $hints[] = __( 'Sin mantenimiento periódico recomendado.', 'aura-suite' );
        }
        $carry[ $cat->slug ] = [
            'hint'            => implode( ' ', $hints ),
            'interval_type'   => $cat->interval_type,
            'interval_months' => $cat->interval_months,
            'interval_hours'  => $cat->interval_hours,
        ];
        return $carry;
    }, [] ),
] ); ?>;
</script>
