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

$users = get_users( [ 'role__not_in' => [], 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name' ] ] );

// Lista de equipos disponibles para vinculación como componente (excluye el actual)
$all_equipment_for_parent = $wpdb->get_results(
    "SELECT id, name, brand, internal_code FROM {$wpdb->prefix}aura_inventory_equipment WHERE deleted_at IS NULL"
    . ( $equipment_id ? $wpdb->prepare( ' AND id != %d', $equipment_id ) : '' )
    . ' ORDER BY name ASC'
) ?: [];

// Helper para autorellenar campos en edición
$v = function( string $field, $default = '' ) use ( $equipment ) {
    return esc_attr( $equipment ? ( $equipment->$field ?? $default ) : $default );
};
$checked = function( string $field ) use ( $equipment ) {
    return ( $equipment && $equipment->$field ) ? 'checked' : '';
};
$page_title = $is_edit ? __( 'Editar Equipo', 'aura-suite' ) : __( 'Registrar Nuevo Equipo', 'aura-suite' );
?>

<div class="wrap aura-inventory-equipment-form">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-<?php echo $is_edit ? 'edit' : 'plus-alt'; ?>"
              style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php echo esc_html( $page_title ); ?>
    </h1>
    <hr class="wp-header-end">

    <form id="aura-inv-equipment-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'aura_inventory_nonce', '_inv_nonce' ); ?>
        <input type="hidden" name="equipment_id" id="equipment_id" value="<?php echo $equipment_id; ?>">

        <div class="aura-inv-form-layout">

            <!-- ── Columna principal ─────────────────────────────── -->
            <div class="aura-inv-form-main">

                <!-- Sección 1: Información básica -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Información básica', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="inv_name"><?php _e( 'Nombre del equipo', 'aura-suite' ); ?> <span class="required">*</span></label></th>
                                <td><input type="text" id="inv_name" name="name" value="<?php echo $v('name'); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="inv_brand"><?php _e( 'Marca', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_brand" name="brand" value="<?php echo $v('brand'); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_model"><?php _e( 'Modelo', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_model" name="model" value="<?php echo $v('model'); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_serial"><?php _e( 'Número de serie', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_serial" name="serial_number" value="<?php echo $v('serial_number'); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_code"><?php _e( 'Código interno', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_code" name="internal_code" value="<?php echo $v('internal_code'); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_category"><?php _e( 'Categoría', 'aura-suite' ); ?></label></th>
                                <td>
                                    <select id="inv_category" name="category" class="regular-text">
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
                                    <select id="inv_status" name="status">
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
                                <td><input type="text" id="inv_location" name="location" value="<?php echo $v('location'); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Bodega, sala, vehículo…', 'aura-suite' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_description"><?php _e( 'Descripción', 'aura-suite' ); ?></label></th>
                                <td><textarea id="inv_description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $equipment->description ?? '' ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="inv_accessories"><?php _e( 'Accesorios incluidos', 'aura-suite' ); ?></label></th>
                                <td>
                                    <textarea id="inv_accessories" name="accessories" rows="4" class="large-text"
                                        placeholder="<?php esc_attr_e( 'Ej:\nCargador rápido 20V\n2 Baterías 4Ah\nEstuche de transporte', 'aura-suite' ); ?>"><?php echo esc_textarea( $equipment->accessories ?? '' ); ?></textarea>
                                    <p class="description"><?php _e( 'Un accesorio por línea. Lista los accesorios de dotación que vienen con el equipo (informativos).', 'aura-suite' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div><!-- postbox: información básica -->

                <!-- Sección 2: Adquisición y valor -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Adquisición y valor', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="inv_acquisition_date"><?php _e( 'Fecha de adquisición', 'aura-suite' ); ?></label></th>
                                <td><input type="date" id="inv_acquisition_date" name="acquisition_date" value="<?php echo $v('acquisition_date'); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_cost"><?php _e( 'Costo de adquisición', 'aura-suite' ); ?></label></th>
                                <td>
                                    <input type="number" id="inv_cost" name="cost" value="<?php echo $v('cost','0'); ?>" step="0.01" min="0" class="small-text">
                                    <span><?php echo esc_html( get_option( 'aura_currency_symbol', '$' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_estimated_value"><?php _e( 'Valor estimado actual', 'aura-suite' ); ?></label></th>
                                <td>
                                    <input type="number" id="inv_estimated_value" name="estimated_value" value="<?php echo $v('estimated_value','0'); ?>" step="0.01" min="0" class="small-text">
                                    <span><?php echo esc_html( get_option( 'aura_currency_symbol', '$' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_supplier"><?php _e( 'Proveedor', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_supplier" name="supplier" value="<?php echo $v('supplier'); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_warranty_date"><?php _e( 'Vencimiento de garantía', 'aura-suite' ); ?></label></th>
                                <td><input type="date" id="inv_warranty_date" name="warranty_date" value="<?php echo $v('warranty_date'); ?>"></td>
                            </tr>
                        </table>
                    </div>
                </div><!-- postbox: adquisición -->

                <!-- Sección 3: Mantenimiento periódico -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Mantenimiento periódico', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php _e( '¿Requiere mantenimiento?', 'aura-suite' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="inv_requires_maintenance" name="requires_maintenance" value="1" <?php echo $checked('requires_maintenance'); ?>>
                                        <?php _e( 'Este equipo tiene mantenimiento periódico programado', 'aura-suite' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <div id="aura-inv-maintenance-fields" <?php echo ( $equipment && $equipment->requires_maintenance ) ? '' : 'style="display:none;"'; ?>>
                            <table class="form-table">
                                <tr>
                                    <th><?php _e( 'Tipo de intervalo', 'aura-suite' ); ?></th>
                                    <td>
                                        <label><input type="radio" name="interval_type" value="time"  <?php checked( $v('interval_type','time'), 'time'  ); ?>> <?php _e( 'Por tiempo',        'aura-suite' ); ?></label>&nbsp;&nbsp;
                                        <label><input type="radio" name="interval_type" value="hours" <?php checked( $v('interval_type','time'), 'hours' ); ?>> <?php _e( 'Por horas de uso', 'aura-suite' ); ?></label>&nbsp;&nbsp;
                                        <label><input type="radio" name="interval_type" value="both"  <?php checked( $v('interval_type','time'), 'both'  ); ?>> <?php _e( 'Ambos',             'aura-suite' ); ?></label>
                                    </td>
                                </tr>
                                <tr id="inv_row_months">
                                    <th><label for="inv_interval_months"><?php _e( 'Cada (meses)', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <input type="number" id="inv_interval_months" name="interval_months" value="<?php echo $v('interval_months',''); ?>" min="1" max="120" class="small-text">
                                        <?php _e( 'meses', 'aura-suite' ); ?>
                                    </td>
                                </tr>
                                <tr id="inv_row_hours">
                                    <th><label for="inv_interval_hours"><?php _e( 'Cada (horas)', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <input type="number" id="inv_interval_hours" name="interval_hours" value="<?php echo $v('interval_hours',''); ?>" min="1" max="10000" class="small-text">
                                        <?php _e( 'horas de uso', 'aura-suite' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="inv_alert_days"><?php _e( 'Alerta con (días)', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <input type="number" id="inv_alert_days" name="alert_days_before" value="<?php echo $v('alert_days_before','7'); ?>" min="1" max="60" class="small-text">
                                        <?php _e( 'días de anticipación', 'aura-suite' ); ?>
                                    </td>
                                </tr>
                            </table>
                        </div><!-- #aura-inv-maintenance-fields -->
                    </div>
                </div><!-- postbox: mantenimiento -->

                <!-- Sección 4: Especificaciones técnicas -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Especificaciones técnicas', 'aura-suite' ); ?> <small>(<?php _e('opcional', 'aura-suite'); ?>)</small></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="inv_oil_type"><?php _e( 'Tipo de aceite', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_oil_type" name="oil_type" value="<?php echo $v('oil_type'); ?>" class="regular-text" placeholder="SAE 10W-40"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_oil_capacity"><?php _e( 'Capacidad de aceite (L)', 'aura-suite' ); ?></label></th>
                                <td><input type="number" id="inv_oil_capacity" name="oil_capacity" value="<?php echo $v('oil_capacity'); ?>" step="0.1" min="0" class="small-text"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_fuel_type"><?php _e( 'Tipo de combustible', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_fuel_type" name="fuel_type" value="<?php echo $v('fuel_type'); ?>" class="regular-text" placeholder="Gasolina, Diésel…"></td>
                            </tr>
                            <tr>
                                <th><label for="inv_voltage"><?php _e( 'Voltaje', 'aura-suite' ); ?></label></th>
                                <td>
                                    <input type="number" id="inv_voltage" name="voltage" value="<?php echo $v('voltage'); ?>" min="0" class="small-text">
                                    <span>V</span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="inv_hydraulic_pressure"><?php _e( 'Presión hidráulica', 'aura-suite' ); ?></label></th>
                                <td><input type="text" id="inv_hydraulic_pressure" name="hydraulic_pressure" value="<?php echo $v('hydraulic_pressure'); ?>" class="regular-text" placeholder="ej: 3000 PSI"></td>
                            </tr>
                        </table>
                    </div>
                </div><!-- postbox: especificaciones -->

            </div><!-- .aura-inv-form-main -->

            <!-- ── Columna lateral ───────────────────────────────── -->
            <div class="aura-inv-form-sidebar">

                <!-- Foto -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Foto del equipo', 'aura-suite' ); ?></h2>
                    <div class="inside aura-inv-photo-box">
                        <div id="aura-inv-photo-preview">
                            <?php
                            if ( $equipment && $equipment->photo ) :
                                // Soporta tanto attachment_id numérico como URL legacy
                                if ( is_numeric( $equipment->photo ) ) {
                                    $preview_url = wp_get_attachment_image_url( (int) $equipment->photo, 'aura-equipment-full' )
                                                ?: wp_get_attachment_url( (int) $equipment->photo );
                                } else {
                                    $preview_url = $equipment->photo;
                                }
                                if ( $preview_url ) : ?>
                            <img src="<?php echo esc_url( $preview_url ); ?>" style="max-width:100%;border-radius:4px;">
                                <?php endif;
                            else : ?>
                            <span class="dashicons dashicons-format-image" style="font-size:60px;color:#c3c4c7;display:block;text-align:center;padding:20px 0;"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" id="inv_photo" name="photo" value="<?php echo $v('photo'); ?>">
                        <button type="button" id="aura-inv-select-photo" class="button" style="width:100%;margin-top:8px;">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e( 'Seleccionar imagen', 'aura-suite' ); ?>
                        </button>
                        <p style="margin:6px 0 0;font-size:11px;color:#646970;">
                            <?php _e( 'Se recortará automáticamente a 4:3 (800×600 px).', 'aura-suite' ); ?>
                        </p>
                        <?php if ( $equipment && $equipment->photo ) : ?>
                        <button type="button" id="aura-inv-remove-photo" class="button button-link-delete" style="width:100%;margin-top:4px;">
                            <?php _e( 'Quitar imagen', 'aura-suite' ); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Asignación -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Asignación', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <p>
                            <label for="inv_responsible"><?php _e( 'Responsable', 'aura-suite' ); ?></label><br>
                            <select id="inv_responsible" name="responsible_user_id" style="width:100%;">
                                <option value=""><?php _e( '— Sin asignar —', 'aura-suite' ); ?></option>
                                <?php foreach ( $users as $user ) : ?>
                                <option value="<?php echo esc_attr( $user->ID ); ?>"
                                    <?php selected( intval( $v('responsible_user_id') ), $user->ID ); ?>>
                                    <?php echo esc_html( $user->display_name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <?php if ( ! empty( $areas ) ) : ?>
                        <p>
                            <label for="inv_area"><?php _e( 'Área / Programa', 'aura-suite' ); ?></label><br>
                            <select id="inv_area" name="area_id" style="width:100%;">
                                <option value=""><?php _e( '— Sin área —', 'aura-suite' ); ?></option>
                                <?php foreach ( $areas as $area ) : ?>
                                <option value="<?php echo esc_attr( $area->id ); ?>"
                                    <?php selected( intval( $v('area_id') ), intval( $area->id ) ); ?>>
                                    <?php echo esc_html( $area->name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <?php endif; ?>
                    </div>
                </div><!-- postbox: asignación -->

                <!-- Equipo padre (componente de) -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Componente de equipo', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <p>
                            <label for="inv_parent_equipment"><?php _e( 'Equipo padre', 'aura-suite' ); ?></label><br>
                            <select id="inv_parent_equipment" name="parent_equipment_id" style="width:100%;">
                                <option value=""><?php _e( '— Equipo independiente —', 'aura-suite' ); ?></option>
                                <?php foreach ( $all_equipment_for_parent as $peq ) :
                                    $plabel = esc_html( $peq->name );
                                    if ( $peq->brand )         $plabel .= ' · ' . esc_html( $peq->brand );
                                    if ( $peq->internal_code ) $plabel .= ' (' . esc_html( $peq->internal_code ) . ')';
                                ?>
                                <option value="<?php echo esc_attr( $peq->id ); ?>"
                                    <?php selected( intval( $v('parent_equipment_id') ), intval( $peq->id ) ); ?>>
                                    <?php echo $plabel; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="description" style="margin-top:4px;"><?php _e( 'Selecciona si este ítem es un componente reemplazable (batería, cargador, accesorio con vida útil propia) de otro equipo.', 'aura-suite' ); ?></p>
                    </div>
                </div><!-- postbox: equipo padre -->

                <!-- Acciones -->
                <div class="postbox">
                    <div class="inside" style="text-align:center;">
                        <button type="submit" id="aura-inv-save-btn" class="button button-primary button-large" style="width:100%;margin-bottom:8px;">
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo $is_edit ? esc_html__( 'Actualizar equipo', 'aura-suite' ) : esc_html__( 'Registrar equipo', 'aura-suite' ); ?>
                        </button>
                        <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-equipment' ); ?>" class="button" style="width:100%;">
                            <?php _e( 'Cancelar', 'aura-suite' ); ?>
                        </a>
                        <div id="aura-inv-form-notice" style="margin-top:12px;"></div>
                    </div>
                </div>

            </div><!-- .aura-inv-form-sidebar -->

        </div><!-- .aura-inv-form-layout -->
    </form>

</div><!-- .aura-inventory-equipment-form -->

<!-- ── Modal de recorte de imagen (Cropper.js) ─────────────────────────────── -->
<div id="aura-crop-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.72);align-items:center;justify-content:center;">
    <div style="background:#fff;padding:24px;border-radius:8px;max-width:680px;width:95vw;max-height:92vh;overflow:auto;box-shadow:0 8px 32px rgba(0,0,0,.35);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h3 style="margin:0;font-size:16px;">
                <span class="dashicons dashicons-crop" style="vertical-align:text-bottom;margin-right:4px;"></span>
                <?php _e( 'Recortar imagen del equipo &mdash; ratio 4:3', 'aura-suite' ); ?>
            </h3>
            <button type="button" id="aura-crop-close" class="button" style="min-width:0;padding:2px 8px;font-size:20px;line-height:1;">&times;</button>
        </div>
        <div id="aura-crop-container" style="max-height:380px;overflow:hidden;background:#f0f0f1;border-radius:4px;">
            <img id="aura-crop-img" style="display:block;max-width:100%;" src="" alt="">
        </div>
        <p style="margin:10px 0 4px;font-size:12px;color:#646970;">
            <?php _e( 'Arrastra el recuadro para elegir el área. Al aplicar se guardará una imagen 800×600 px (JPEG 80%) y un thumbnail 220×165 px.', 'aura-suite' ); ?>
        </p>
        <div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" id="aura-crop-cancel" class="button"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-crop-apply" class="button button-primary">
                <span class="dashicons dashicons-yes" style="vertical-align:text-bottom;"></span>
                <?php _e( 'Aplicar recorte y usar imagen', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<script>
var auraInvFormData = <?php echo wp_json_encode( [
    'ajaxurl'     => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'aura_inventory_nonce' ),
    'equipmentId' => $equipment_id,
    'isEdit'      => $is_edit,
    'listUrl'     => admin_url( 'admin.php?page=aura-inventory-equipment' ),
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
