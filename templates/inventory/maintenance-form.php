<?php
/**
 * Template: Formulario de Registro / Edición de Mantenimiento
 *
 * Alta:   admin.php?page=aura-inventory-new-maintenance
 * Edición: admin.php?page=aura-inventory-new-maintenance&id=N
 * Pre-equipo: admin.php?page=aura-inventory-new-maintenance&equipment_id=N
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$can_create = current_user_can( 'aura_inventory_maintenance_create' ) || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_inventory_maintenance_edit'   ) || current_user_can( 'manage_options' );

if ( ! $can_create && ! $can_edit ) {
    wp_die( __( 'No tienes permisos para realizar esta acción.', 'aura-suite' ) );
}

global $wpdb;

$maint_id     = intval( $_GET['id']           ?? 0 );
$preequip_id  = intval( $_GET['equipment_id'] ?? 0 );
$is_edit      = $maint_id > 0;
$maintenance  = null;

if ( $is_edit ) {
    $maintenance = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aura_inventory_maintenance WHERE id = %d",
        $maint_id
    ) );
    if ( ! $maintenance ) wp_die( __( 'Registro no encontrado.', 'aura-suite' ) );
    $preequip_id = intval( $maintenance->equipment_id );
}

// Equipo pre-seleccionado
$preequip = null;
if ( $preequip_id > 0 ) {
    $preequip = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, name, brand, model, requires_maintenance, interval_type,
                interval_months, interval_hours, current_hours, oil_type, oil_capacity, photo
         FROM {$wpdb->prefix}aura_inventory_equipment WHERE id = %d AND deleted_at IS NULL",
        $preequip_id
    ) );
}

// Listas
$users   = Aura_Roles_Manager::get_aura_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
$equipment_list = $wpdb->get_results(
    "SELECT id, name, brand, internal_code FROM {$wpdb->prefix}aura_inventory_equipment WHERE deleted_at IS NULL ORDER BY name ASC"
) ?: [];

// Categorías financieras (para vincular gasto)
$finance_cats = [];
if ( class_exists( 'Aura_Financial_Categories' ) ) {
    $finance_cats = $wpdb->get_results(
        "SELECT id, name, parent_id FROM {$wpdb->prefix}aura_finance_categories
         WHERE type = 'expense' AND is_active = 1 ORDER BY name ASC"
    ) ?: [];
}

// Configuración del módulo de inventario (categoría y estado por defecto para finanzas)
$inv_settings          = Aura_Inventory_Categories::get_settings();
$default_finance_cat   = intval( $inv_settings['finance_category_id'] ?? 0 );
$default_auto_check    = $default_finance_cat > 0;

// Helper edición
$v = function( string $field, $default = '' ) use ( $maintenance ) {
    return esc_attr( $maintenance ? ( $maintenance->$field ?? $default ) : $default );
};
$checked = function( string $field ) use ( $maintenance ) {
    return ( $maintenance && $maintenance->$field ) ? 'checked' : '';
};
$is_internal = ! $maintenance || $maintenance->performed_by === 'internal';
$cur_type    = $maintenance ? $maintenance->type : 'preventive';
$cur_ps      = $maintenance ? $maintenance->post_status : 'operational';

$page_title = $is_edit ? __( 'Editar Mantenimiento', 'aura-suite' ) : __( 'Registrar Mantenimiento', 'aura-suite' );
?>

<div class="wrap aura-inv-maintenance-form">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-<?php echo $is_edit ? 'edit' : 'plus-alt'; ?>"
              style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php echo esc_html( $page_title ); ?>
    </h1>
    <hr class="wp-header-end">

    <form id="aura-maint-form" method="post" novalidate>
        <?php wp_nonce_field( 'aura_inventory_nonce', '_inv_nonce' ); ?>
        <input type="hidden" name="maint_id"     id="maint_id"     value="<?php echo $maint_id; ?>">

        <div class="aura-inv-form-layout">

            <!-- ── Columna principal ─────────────────────────────── -->
            <div class="aura-inv-form-main">

                <!-- Sección 1: Equipo y fecha -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Equipo y fecha', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="maint_equipment_id"><?php _e( 'Equipo', 'aura-suite' ); ?> <span class="required">*</span></label></th>
                                <td>
                                    <select id="maint_equipment_id" name="equipment_id" class="regular-text" required>
                                        <option value=""><?php _e( '— Seleccionar equipo —', 'aura-suite' ); ?></option>
                                        <?php foreach ( $equipment_list as $eq ) : ?>
                                        <option value="<?php echo esc_attr( $eq->id ); ?>"
                                                <?php selected( intval( $v('equipment_id', $preequip_id) ), intval( $eq->id ) ); ?>>
                                            <?php
                                            $eq_label = $eq->name;
                                            if ( $eq->brand )         $eq_label .= ' · ' . $eq->brand;
                                            if ( $eq->internal_code ) $eq_label .= ' · Cód: ' . $eq->internal_code;
                                            echo esc_html( $eq_label );
                                            ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="maint-equip-photo-wrap" style="margin-top:10px;display:<?php echo ( $preequip && ! empty( $preequip->photo ) ) ? 'block' : 'none'; ?>">
                                        <?php
                                        $preequip_photo_url = '';
                                        if ( $preequip && ! empty( $preequip->photo ) ) {
                                            $urls = aura_get_equipment_photo_urls( $preequip->photo );
                                            $preequip_photo_url = $urls['full'];
                                        }
                                        ?>
                                        <img id="maint-equip-photo-img"
                                             src="<?php echo esc_url( $preequip_photo_url ); ?>"
                                             alt="<?php esc_attr_e( 'Foto del equipo', 'aura-suite' ); ?>"
                                             style="max-width:260px;max-height:195px;border-radius:6px;border:1px solid #dcdcde;object-fit:cover;display:block;">
                                        <p class="description" style="margin-top:4px;font-size:11px;color:#8c8f94;"><?php esc_html_e( 'Foto del equipo seleccionado', 'aura-suite' ); ?></p>
                                    </div>
                                    <?php if ( $preequip ) : ?>
                                    <p class="description" id="maint_equip_hint">
                                        <?php if ( $preequip->oil_type ) : ?>
                                            <?php printf( __( 'Aceite: <strong>%s</strong>', 'aura-suite' ), esc_html( $preequip->oil_type ) ); ?>
                                            <?php if ( $preequip->oil_capacity ) : ?>
                                                (<?php echo esc_html( $preequip->oil_capacity ); ?> L)
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ( $preequip->current_hours ) : ?>
                                            — <?php printf( __( 'Horas actuales: <strong>%s h</strong>', 'aura-suite' ),
                                                number_format( $preequip->current_hours, 1 ) ); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="maint_date"><?php _e( 'Fecha de mantenimiento', 'aura-suite' ); ?> <span class="required">*</span></label></th>
                                <td>
                                    <input type="date" id="maint_date" name="maintenance_date"
                                           value="<?php echo $v( 'maintenance_date', date('Y-m-d') ); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="maint_hours"><?php _e( 'Horas del equipo al momento', 'aura-suite' ); ?></label></th>
                                <td>
                                    <input type="number" id="maint_hours" name="equipment_hours"
                                           value="<?php echo $v('equipment_hours'); ?>" step="0.1" min="0" class="small-text">
                                    <span>h</span>
                                    <p class="description"><?php _e( 'Actualiza las horas de uso acumuladas del equipo.', 'aura-suite' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="maint_type"><?php _e( 'Tipo de mantenimiento', 'aura-suite' ); ?></label></th>
                                <td>
                                    <select id="maint_type" name="type">
                                        <?php
                                        $types = [
                                            'preventive'   => __( 'Preventivo',        'aura-suite' ),
                                            'corrective'   => __( 'Correctivo',        'aura-suite' ),
                                            'oil_change'   => __( 'Cambio de aceite',  'aura-suite' ),
                                            'cleaning'     => __( 'Limpieza',          'aura-suite' ),
                                            'inspection'   => __( 'Inspección',        'aura-suite' ),
                                            'major_repair' => __( 'Reparación mayor',  'aura-suite' ),
                                        ];
                                        foreach ( $types as $val => $lbl ) :
                                        ?>
                                        <option value="<?php echo $val; ?>" <?php selected( $cur_type, $val ); ?>>
                                            <?php echo esc_html( $lbl ); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <!-- ── Panel informativo: instrucciones + último mantenimiento ─ -->
                        <div id="maint-instructions-panel" style="display:<?php
                            // Pre-seleccionado: mostrar si hay instrucciones o mantenimiento previo
                            $show_panel = false;
                            $preequip_instructions = '';
                            $prev_maint_data       = null;
                            if ( $preequip_id > 0 ) {
                                global $wpdb;
                                $preequip_instructions = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT maintenance_instructions FROM {$wpdb->prefix}aura_inventory_equipment WHERE id = %d AND deleted_at IS NULL",
                                    $preequip_id
                                ) );
                                $prev_maint_data = $wpdb->get_row( $wpdb->prepare(
                                    "SELECT id, maintenance_date, type, description, performed_by, workshop_name, post_status
                                     FROM {$wpdb->prefix}aura_inventory_maintenance
                                     WHERE equipment_id = %d
                                     ORDER BY maintenance_date DESC, id DESC
                                     LIMIT 1",
                                    $preequip_id
                                ) );
                                $show_panel = ! empty( $preequip_instructions ) || ! empty( $prev_maint_data );
                            }
                            echo $show_panel ? 'block' : 'none';
                        ?>">

                            <!-- Bloque A: Instrucciones qué hacer -->
                            <div id="maint-instructions-block"
                                 style="display:<?php echo ! empty( $preequip_instructions ) ? 'block' : 'none'; ?>;
                                        background:#f0f6fc;
                                        border-left:4px solid #2271b1;
                                        border-radius:0 6px 6px 0;
                                        padding:14px 16px;
                                        margin-top:14px;">
                                <p style="margin:0 0 8px;font-weight:600;font-size:13px;color:#1d2327;">
                                    <span class="dashicons dashicons-clipboard" style="color:#2271b1;vertical-align:middle;"></span>
                                    <?php _e( '¿Qué debe hacerse en este mantenimiento?', 'aura-suite' ); ?>
                                </p>
                                <pre id="maint-instructions-text"
                                     style="margin:0;font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-word;
                                            font-family:inherit;color:#1d2327;background:transparent;border:0;padding:0;"><?php
                                    echo esc_html( $preequip_instructions ?? '' );
                                ?></pre>
                            </div>

                            <!-- Bloque B: Último mantenimiento anterior -->
                            <div id="maint-prev-block"
                                 style="display:<?php echo ! empty( $prev_maint_data ) ? 'block' : 'none'; ?>;
                                        background:#f6f7f7;
                                        border-left:4px solid #8c8f94;
                                        border-radius:0 6px 6px 0;
                                        padding:14px 16px;
                                        margin-top:10px;">
                                <p style="margin:0 0 6px;font-weight:600;font-size:13px;color:#1d2327;">
                                    <span class="dashicons dashicons-clock" style="color:#8c8f94;vertical-align:middle;"></span>
                                    <?php _e( 'Último mantenimiento registrado', 'aura-suite' ); ?>
                                </p>
                                <div id="maint-prev-content" style="font-size:12px;color:#50575e;">
                                    <?php if ( $prev_maint_data ) :
                                        $type_labels_pm = [
                                            'preventive'   => __( 'Preventivo',       'aura-suite' ),
                                            'corrective'   => __( 'Correctivo',        'aura-suite' ),
                                            'oil_change'   => __( 'Cambio de aceite',  'aura-suite' ),
                                            'cleaning'     => __( 'Limpieza',          'aura-suite' ),
                                            'inspection'   => __( 'Inspección',        'aura-suite' ),
                                            'major_repair' => __( 'Reparación mayor',  'aura-suite' ),
                                        ];
                                        $ps_labels_pm = [
                                            'operational'    => '✅ ' . __( 'Operacional',         'aura-suite' ),
                                            'needs_followup' => '⚠️ ' . __( 'Requiere seguimiento','aura-suite' ),
                                            'out_of_service' => '🔴 ' . __( 'Fuera de servicio',  'aura-suite' ),
                                        ];
                                    ?>
                                    <table style="border-collapse:collapse;width:100%;">
                                        <tr><td style="padding:2px 8px 2px 0;font-weight:600;"><?php _e( 'Fecha:', 'aura-suite' ); ?></td>
                                            <td><?php echo esc_html( date_i18n( get_option('date_format'), strtotime( $prev_maint_data->maintenance_date ) ) ); ?></td></tr>
                                        <tr><td style="padding:2px 8px 2px 0;font-weight:600;"><?php _e( 'Tipo:', 'aura-suite' ); ?></td>
                                            <td><?php echo esc_html( $type_labels_pm[ $prev_maint_data->type ] ?? $prev_maint_data->type ); ?></td></tr>
                                        <?php if ( $prev_maint_data->description ) : ?>
                                        <tr><td style="padding:2px 8px 2px 0;font-weight:600;"><?php _e( 'Trabajo:', 'aura-suite' ); ?></td>
                                            <td><?php echo nl2br( esc_html( $prev_maint_data->description ) ); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ( $prev_maint_data->performed_by === 'external' && $prev_maint_data->workshop_name ) : ?>
                                        <tr><td style="padding:2px 8px 2px 0;font-weight:600;"><?php _e( 'Taller:', 'aura-suite' ); ?></td>
                                            <td><?php echo esc_html( $prev_maint_data->workshop_name ); ?></td></tr>
                                        <?php endif; ?>
                                        <tr><td style="padding:2px 8px 2px 0;font-weight:600;"><?php _e( 'Estado:', 'aura-suite' ); ?></td>
                                            <td><?php echo esc_html( $ps_labels_pm[ $prev_maint_data->post_status ] ?? $prev_maint_data->post_status ); ?></td></tr>
                                    </table>
                                    <?php else : ?>
                                    <em><?php _e( 'Sin mantenimientos previos registrados.', 'aura-suite' ); ?></em>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div><!-- #maint-instructions-panel -->

                    </div>
                </div><!-- postbox: equipo y fecha -->

                <!-- Sección 2: Descripción del trabajo -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Descripción del trabajo', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="maint_description"><?php _e( 'Descripción general', 'aura-suite' ); ?></label></th>
                                <td>
                                    <textarea id="maint_description" name="description"
                                              rows="4" class="large-text"><?php echo esc_textarea( $maintenance->description ?? '' ); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="maint_parts"><?php _e( 'Piezas / repuestos cambiados', 'aura-suite' ); ?></label></th>
                                <td>
                                    <textarea id="maint_parts" name="parts_replaced"
                                              rows="3" class="large-text"
                                              placeholder="<?php esc_attr_e( 'Ej: Filtro de aceite, correa de distribución…', 'aura-suite' ); ?>"><?php echo esc_textarea( $maintenance->parts_replaced ?? '' ); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="maint_observations"><?php _e( 'Observaciones adicionales', 'aura-suite' ); ?></label></th>
                                <td>
                                    <textarea id="maint_observations" name="observations"
                                              rows="3" class="large-text"><?php echo esc_textarea( $maintenance->observations ?? '' ); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div><!-- postbox: descripción -->

                <!-- Sección 3: Ejecutor -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Ejecutor del mantenimiento', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php _e( 'Realizado por', 'aura-suite' ); ?></th>
                                <td>
                                    <label style="margin-right:20px;">
                                        <input type="radio" name="performed_by" value="internal"
                                               <?php checked( $is_internal ); ?>>
                                        <?php _e( 'Personal interno', 'aura-suite' ); ?>
                                    </label>
                                    <label>
                                        <input type="radio" name="performed_by" value="external"
                                               <?php checked( ! $is_internal ); ?>>
                                        <?php _e( 'Taller / empresa externa', 'aura-suite' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <!-- Campos internos -->
                        <div id="maint_internal_fields" <?php echo $is_internal ? '' : 'style="display:none;"'; ?>>
                            <table class="form-table">
                                <tr>
                                    <th><label for="maint_technician"><?php _e( 'Técnico responsable', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <select id="maint_technician" name="internal_technician" class="regular-text">
                                            <option value=""><?php _e( '— Sin asignar —', 'aura-suite' ); ?></option>
                                            <?php foreach ( $users as $u ) : ?>
                                            <option value="<?php echo esc_attr( $u->ID ); ?>"
                                                    <?php selected( intval( $v('internal_technician') ), intval( $u->ID ) ); ?>>
                                                <?php echo esc_html( $u->display_name ); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Campos externos -->
                        <div id="maint_external_fields" <?php echo $is_internal ? 'style="display:none;"' : ''; ?>>
                            <table class="form-table">
                                <tr>
                                    <th><label for="maint_workshop"><?php _e( 'Nombre del taller', 'aura-suite' ); ?></label></th>
                                    <td><input type="text" id="maint_workshop" name="workshop_name"
                                               value="<?php echo $v('workshop_name'); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="maint_invoice_number"><?php _e( 'N° de factura', 'aura-suite' ); ?></label></th>
                                    <td><input type="text" id="maint_invoice_number" name="invoice_number"
                                               value="<?php echo $v('invoice_number'); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="maint_workshop_invoice"><?php _e( 'URL / adjunto factura', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <input type="url" id="maint_workshop_invoice" name="workshop_invoice"
                                               value="<?php echo esc_url( $maintenance->workshop_invoice ?? '' ); ?>" class="regular-text"
                                               placeholder="https://">
                                        <button type="button" id="maint_select_invoice" class="button">
                                            <span class="dashicons dashicons-upload"></span>
                                        </button>
                                    </td>
                                </tr>
                            </table>
                        </div>

                    </div>
                </div><!-- postbox: ejecutor -->

                <!-- Sección 4: Estado post-mantenimiento -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Estado del equipo tras el mantenimiento', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php _e( 'Estado resultante', 'aura-suite' ); ?></th>
                                <td>
                                    <?php
                                    $ps_opts = [
                                        'operational'    => [ 'icon' => '✅', 'label' => __( 'Operacional — listo para usar',           'aura-suite' ) ],
                                        'needs_followup' => [ 'icon' => '⚠️', 'label' => __( 'Requiere seguimiento — pendiente',        'aura-suite' ) ],
                                        'out_of_service' => [ 'icon' => '🔴', 'label' => __( 'Fuera de servicio — pasa a Reparación',   'aura-suite' ) ],
                                    ];
                                    foreach ( $ps_opts as $val => $info ) :
                                    ?>
                                    <label class="aura-maint-ps-label aura-maint-ps-<?php echo esc_attr( $val ); ?>"
                                           style="margin-bottom:8px;display:inline-flex;">
                                        <input type="radio" name="post_status" value="<?php echo esc_attr( $val ); ?>"
                                               <?php checked( $cur_ps, $val ); ?>>
                                        <?php echo $info['icon'] . ' ' . esc_html( $info['label'] ); ?>
                                    </label><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr id="maint_row_next_action">
                                <th><label for="maint_next_action"><?php _e( 'Próxima acción programada', 'aura-suite' ); ?></label></th>
                                <td>
                                    <input type="date" id="maint_next_action" name="next_action_date"
                                           value="<?php echo $v('next_action_date'); ?>"
                                           data-allow-future="1">
                                    <p class="description"><?php _e( 'Fecha de la acción de seguimiento necesaria.', 'aura-suite' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div><!-- postbox: estado -->

            </div><!-- .aura-inv-form-main -->

            <!-- ── Columna lateral ───────────────────────────────── -->
            <div class="aura-inv-form-sidebar">

                <!-- Costos -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Costos', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <table class="form-table aura-maint-costs-table">
                            <tr>
                                <th><label for="maint_parts_cost"><?php _e( 'Repuestos / insumos', 'aura-suite' ); ?></label></th>
                                <td>
                                    <input type="number" id="maint_parts_cost" name="parts_cost"
                                           value="<?php echo $v('parts_cost','0'); ?>"
                                           step="0.01" min="0" class="small-text"
                                           placeholder="0.00">
                                    <span><?php echo esc_html( get_option('aura_currency_symbol','$') ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="maint_labor_cost"><?php _e( 'Mano de obra', 'aura-suite' ); ?></label></th>
                                <td>
                                    <input type="number" id="maint_labor_cost" name="labor_cost"
                                           value="<?php echo $v('labor_cost','0'); ?>"
                                           step="0.01" min="0" class="small-text"
                                           placeholder="0.00">
                                    <span><?php echo esc_html( get_option('aura_currency_symbol','$') ); ?></span>
                                </td>
                            </tr>
                            <tr class="aura-maint-total-row">
                                <th><?php _e( 'Total', 'aura-suite' ); ?></th>
                                <td>
                                    <strong id="maint_total_display">
                                        <?php echo esc_html( get_option('aura_currency_symbol','$') ); ?>
                                        <span id="maint_total_value">
                                            <?php echo number_format( floatval( $v('total_cost','0') ), 2 ); ?>
                                        </span>
                                    </strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div><!-- postbox: costos -->

                <!-- Integración Finanzas -->
                <?php if ( ! $is_edit && ! empty( $finance_cats ) ) : ?>
                <div class="postbox" id="maint_finance_box">
                    <h2 class="hndle"><?php _e( 'Registrar en Finanzas', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <label>
                            <input type="checkbox" id="maint_create_finance" name="create_finance_transaction" value="1"
                                   <?php checked( $default_auto_check ); ?>>
                            <?php _e( 'Crear transacción de egreso automáticamente', 'aura-suite' ); ?>
                        </label>
                        <div id="maint_finance_fields" style="display:<?php echo $default_auto_check ? 'block' : 'none'; ?>;margin-top:12px;">
                            <p>
                                <label for="maint_finance_category"><?php _e( 'Categoría financiera', 'aura-suite' ); ?></label><br>
                                <select id="maint_finance_category" name="finance_category_id" style="width:100%;">
                                    <option value=""><?php _e( '— Seleccionar —', 'aura-suite' ); ?></option>
                                    <?php foreach ( $finance_cats as $fc ) : ?>
                                    <option value="<?php echo esc_attr( $fc->id ); ?>"
                                        <?php selected( $default_finance_cat, $fc->id ); ?>>
                                        <?php echo esc_html( $fc->name ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            <p>
                                <label for="maint_finance_payment"><?php _e( 'Método de pago', 'aura-suite' ); ?></label><br>
                                <select id="maint_finance_payment" name="finance_payment_method" style="width:100%;">
                                    <option value="Efectivo"><?php _e( 'Efectivo',        'aura-suite' ); ?></option>
                                    <option value="Transferencia"><?php _e( 'Transferencia','aura-suite' ); ?></option>
                                    <option value="Cheque"><?php _e( 'Cheque',             'aura-suite' ); ?></option>
                                    <option value="Tarjeta"><?php _e( 'Tarjeta',           'aura-suite' ); ?></option>
                                </select>
                            </p>
                        </div>
                        <p class="description"><?php _e( 'Solo disponible al crear. Solo si el costo total es mayor a 0.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <?php elseif ( $is_edit && $maintenance && $maintenance->finance_transaction_id ) : ?>
                <div class="postbox">
                    <h2 class="hndle"><?php _e( 'Finanzas', 'aura-suite' ); ?></h2>
                    <div class="inside">
                        <p>
                            <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
                            <?php printf(
                                __( 'Transacción financiera vinculada: <strong>#%d</strong>', 'aura-suite' ),
                                intval( $maintenance->finance_transaction_id )
                            ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Acciones -->
                <div class="postbox">
                    <div class="inside" style="text-align:center;">
                        <button type="button" id="aura-maint-save-btn"
                                class="button button-primary button-large" style="width:100%;margin-bottom:8px;">
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo $is_edit
                                ? esc_html__( 'Actualizar mantenimiento', 'aura-suite' )
                                : esc_html__( 'Registrar mantenimiento',  'aura-suite' ); ?>
                        </button>
                        <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-maintenance' ); ?>"
                           class="button" style="width:100%;">
                            <?php _e( 'Cancelar', 'aura-suite' ); ?>
                        </a>
                        <div id="aura-maint-form-notice" style="margin-top:12px;"></div>
                    </div>
                </div>

            </div><!-- .aura-inv-form-sidebar -->

        </div><!-- .aura-inv-form-layout -->
    </form>

</div><!-- .aura-inv-maintenance-form -->

<?php
$_maint_form_js = wp_json_encode( [
    'ajaxurl'     => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'aura_inventory_nonce' ),
    'maintId'       => $maint_id,
    'isEdit'        => $is_edit,
    'listUrl'       => admin_url( 'admin.php?page=aura-inventory-maintenance' ),
    'preequipPhoto' => isset( $preequip_photo_url ) ? $preequip_photo_url : '',
    'preequipInstructions' => isset( $preequip_instructions ) ? (string) $preequip_instructions : '',
    'preequipPrevMaint'    => isset( $prev_maint_data ) ? $prev_maint_data : null,
    'currency'    => get_option( 'aura_currency_symbol', '$' ),
    'txt' => [
        'saving'   => __( 'Guardando…',                             'aura-suite' ),
        'saved'    => __( 'Mantenimiento guardado correctamente.',  'aura-suite' ),
        'finance_ok' => __( ' (transacción financiera creada)', 'aura-suite' ),
        'error'    => __( 'Error al guardar el mantenimiento.',     'aura-suite' ),
        'required_equip' => __( 'Selecciona un equipo.',            'aura-suite' ),
        'required_date'  => __( 'La fecha es obligatoria.',         'aura-suite' ),
        'instructions_title' => __( '¿Qué debe hacerse en este mantenimiento?', 'aura-suite' ),
        'prev_maint_title'   => __( 'Último mantenimiento registrado', 'aura-suite' ),
        'no_prev_maint'      => __( 'Sin mantenimientos previos registrados.', 'aura-suite' ),
        'type_labels' => [
            'preventive'   => __( 'Preventivo',       'aura-suite' ),
            'corrective'   => __( 'Correctivo',        'aura-suite' ),
            'oil_change'   => __( 'Cambio de aceite',  'aura-suite' ),
            'cleaning'     => __( 'Limpieza',          'aura-suite' ),
            'inspection'   => __( 'Inspección',        'aura-suite' ),
            'major_repair' => __( 'Reparación mayor',  'aura-suite' ),
        ],
        'ps_labels' => [
            'operational'    => __( 'Operacional',           'aura-suite' ),
            'needs_followup' => __( 'Requiere seguimiento',  'aura-suite' ),
            'out_of_service' => __( 'Fuera de servicio',     'aura-suite' ),
        ],
        'ps_icons' => [
            'operational'    => '✅',
            'needs_followup' => '⚠️',
            'out_of_service' => '🔴',
        ],
        'date_label'        => __( 'Fecha:',   'aura-suite' ),
        'type_label'        => __( 'Tipo:',    'aura-suite' ),
        'work_label'        => __( 'Trabajo:', 'aura-suite' ),
        'workshop_label'    => __( 'Taller:',  'aura-suite' ),
        'status_label'      => __( 'Estado:',  'aura-suite' ),
    ],
] );
?>
<script>var auraMaintForm = <?php echo $_maint_form_js; ?>;</script>
