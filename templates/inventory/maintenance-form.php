<?php
/**
 * Template: Formulario de Registro / Edición de Mantenimiento
 *
 * Alta:      admin.php?page=aura-inventory-new-maintenance
 * Edición:   admin.php?page=aura-inventory-new-maintenance&id=N
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
        "SELECT id, name, brand, model, internal_code, status, requires_maintenance, interval_type,
                interval_months, interval_hours, last_maintenance_hours, next_maintenance_hours, oil_type, oil_capacity, photo
         FROM {$wpdb->prefix}aura_inventory_equipment WHERE id = %d AND deleted_at IS NULL",
        $preequip_id
    ) );
}

$users = Aura_Roles_Manager::get_aura_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

$equipment_list = $wpdb->get_results(
    "SELECT id, name, brand, model, internal_code, status, requires_maintenance,
            interval_type, interval_months, interval_hours, last_maintenance_hours, next_maintenance_hours,
            last_maintenance_date, next_maintenance_date, alert_days_before, photo,
            oil_type, oil_capacity, maintenance_instructions
     FROM {$wpdb->prefix}aura_inventory_equipment
     WHERE deleted_at IS NULL
     ORDER BY name ASC"
) ?: [];

$equip_groups = [
    'urgent'  => [],
    'warning' => [],
    'in_use'  => [],
    'ok'      => [],
];

$today_ts = strtotime( date( 'Y-m-d' ) );

foreach ( $equipment_list as $eq ) {
    $eq->name          = wp_unslash( $eq->name );
    $eq->brand         = wp_unslash( $eq->brand ?? '' );
    $eq->model         = wp_unslash( $eq->model ?? '' );
    $eq->internal_code = wp_unslash( $eq->internal_code ?? '' );

    $maint_status = 'ok';
    $badge_text   = '';
    $days_left    = null;

    if ( $eq->requires_maintenance && $eq->next_maintenance_date ) {
        $next_ts    = strtotime( $eq->next_maintenance_date );
        $days_left  = (int) round( ( $next_ts - $today_ts ) / DAY_IN_SECONDS );
        $alert_days = intval( $eq->alert_days_before ?: 7 );

        if ( $days_left < 0 ) {
            $maint_status = 'overdue';
            $badge_text   = sprintf( __( '🔴 Vencido hace %d d', 'aura-suite' ), abs( $days_left ) );
        } elseif ( $days_left <= 3 ) {
            $maint_status = 'urgent';
            $badge_text   = sprintf( __( '🟠 Urgente en %d d', 'aura-suite' ), $days_left );
        } elseif ( $days_left <= $alert_days ) {
            $maint_status = 'warning';
            $badge_text   = sprintf( __( '🟡 Próximo en %d d', 'aura-suite' ), $days_left );
        } else {
            $maint_status = 'ok';
            $badge_text   = sprintf( __( '✅ Próx: %s', 'aura-suite' ), $eq->next_maintenance_date );
        }
    } elseif ( ! $eq->requires_maintenance ) {
        $maint_status = 'none';
        $badge_text   = __( 'Sin mant. programado', 'aura-suite' );
    } else {
        $maint_status = 'warning';
        $badge_text   = __( '🟡 Sin fecha prog.', 'aura-suite' );
    }

    $eq->calculated_status = $maint_status;
    $eq->badge_text        = $badge_text;
    $eq->days_left         = $days_left;

    $photo_urls = aura_get_equipment_photo_urls( $eq->photo ?? '' );
    $eq->photo_thumb = $photo_urls['thumb'] ?? '';
    $eq->photo_full  = $photo_urls['full'] ?? '';

    if ( $maint_status === 'overdue' || $maint_status === 'urgent' || in_array( $eq->status, [ 'repair', 'maintenance' ] ) ) {
        $equip_groups['urgent'][] = $eq;
    } elseif ( $maint_status === 'warning' ) {
        $equip_groups['warning'][] = $eq;
    } elseif ( $eq->status === 'in_use' ) {
        $equip_groups['in_use'][] = $eq;
    } else {
        $equip_groups['ok'][] = $eq;
    }
}

$finance_cats = [];
if ( class_exists( 'Aura_Financial_Categories' ) ) {
    $finance_cats = $wpdb->get_results(
        "SELECT id, name, parent_id FROM {$wpdb->prefix}aura_finance_categories
         WHERE type = 'expense' AND is_active = 1 ORDER BY name ASC"
    ) ?: [];
}

$inv_settings        = Aura_Inventory_Categories::get_settings();
$default_finance_cat = intval( $inv_settings['finance_category_id'] ?? 0 );
$default_auto_check  = $default_finance_cat > 0;

$v = function( string $field, $default = '' ) use ( $maintenance ) {
    $val = $maintenance ? ( $maintenance->$field ?? $default ) : $default;
    return esc_attr( is_string( $val ) ? wp_unslash( $val ) : $val );
};

$is_internal = ! $maintenance || $maintenance->performed_by === 'internal';
$cur_type    = $maintenance ? $maintenance->type : 'preventive';
$cur_ps      = $maintenance ? $maintenance->post_status : 'operational';
$page_title  = $is_edit ? __( 'Editar Mantenimiento', 'aura-suite' ) : __( 'Registrar Mantenimiento', 'aura-suite' );
$currency_sym = function_exists( 'aura_get_currency_symbol' ) ? aura_get_currency_symbol() : get_option( 'aura_currency_symbol', '$' );

// Encolado seguro sin duplicar script/estilo global
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
?>

<div class="aura-app-wrapper aura-inv-maintenance-form">
    <div class="wrap aura-app-context">

        <?php
        if ( class_exists( 'Aura_UI' ) ) {
            Aura_UI::render_page_header( [
                'title'    => $page_title,
                'subtitle' => __( 'Documenta intervenciones técnicas, repuestos, mano de obra y genera el gasto automático en Finanzas.', 'aura-suite' ),
                'icon'     => $is_edit ? 'dashicons-edit' : 'dashicons-admin-tools',
                'actions'  => [
                    [
                        'label' => __( 'Volver al Inventario', 'aura-suite' ),
                        'url'   => admin_url( 'admin.php?page=aura-inventory-equipment' ),
                        'icon'  => 'dashicons-arrow-left-alt2',
                        'class' => 'btn btn-glass btn-lift',
                    ],
                    [
                        'label' => __( 'Historial de Mantenimientos', 'aura-suite' ),
                        'url'   => admin_url( 'admin.php?page=aura-inventory-maintenance' ),
                        'icon'  => 'dashicons-list-view',
                        'class' => 'btn btn-glass btn-lift',
                    ],
                ],
            ] );
        }
        ?>

        <?php if ( $preequip && ! $is_edit ) : ?>
        <div class="alert-card alert-info" style="margin-bottom:20px;">
            <span class="dashicons dashicons-admin-tools"></span>
            <div>
                <strong><?php printf( __( 'Registrando mantenimiento para: %s', 'aura-suite' ), esc_html( wp_unslash( $preequip->name ) ) ); ?></strong>
                <div class="aura-inv-banner-sub">
                    <?php
                    $meta_tags = [];
                    if ( ! empty( $preequip->brand ) ) $meta_tags[] = __( 'Marca:', 'aura-suite' ) . ' ' . esc_html( wp_unslash( $preequip->brand ) );
                    if ( ! empty( $preequip->model ) ) $meta_tags[] = __( 'Modelo:', 'aura-suite' ) . ' ' . esc_html( wp_unslash( $preequip->model ) );
                    if ( ! empty( $preequip->last_maintenance_hours ) ) $meta_tags[] = __( 'Horas:', 'aura-suite' ) . ' ' . number_format( $preequip->last_maintenance_hours, 1 ) . ' h';
                    echo implode( ' · ', $meta_tags );
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form id="aura-maint-form" method="post" novalidate>
            <?php wp_nonce_field( 'aura_inventory_nonce', '_inv_nonce' ); ?>
            <input type="hidden" name="maint_id" id="maint_id" value="<?php echo $maint_id; ?>">

            <div class="aura-inv-form-layout">

                <!-- ── Columna principal ─────────────────────────────── -->
                <div class="aura-inv-form-main">

                    <!-- Sección 1: Equipo y fecha -->
                    <div class="aura-glass-card aura-inv-form-card">
                        <div class="aura-inv-card-header">
                            <h2><span class="dashicons dashicons-hammer"></span> <?php _e( 'Equipo y fecha', 'aura-suite' ); ?></h2>
                        </div>
                        <div class="aura-inv-card-body">
                            <table class="form-table aura-inv-form-table">
                                <tr>
                                    <th><label for="maint_equipment_id"><?php _e( 'Equipo', 'aura-suite' ); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <div class="aura-equip-select-row">
                                            <select id="maint_equipment_id" name="equipment_id" class="form-select" style="flex:1;min-width:260px;" required>
                                                <option value=""><?php _e( '— Seleccionar equipo del listado —', 'aura-suite' ); ?></option>

                                                <?php
                                                $group_labels = [
                                                    'urgent'  => sprintf( __( '🔴 Mantenimiento Vencido o Requiere Atención (%d)', 'aura-suite' ), count( $equip_groups['urgent'] ) ),
                                                    'warning' => sprintf( __( '🟡 Próximo Mantenimiento (%d)', 'aura-suite' ), count( $equip_groups['warning'] ) ),
                                                    'in_use'  => sprintf( __( '🔵 En Uso / Prestados — Retorno o Revisión (%d)', 'aura-suite' ), count( $equip_groups['in_use'] ) ),
                                                    'ok'      => sprintf( __( '✅ Disponibles / Operativos al Día (%d)', 'aura-suite' ), count( $equip_groups['ok'] ) ),
                                                ];
                                                foreach ( $equip_groups as $gkey => $glist ) :
                                                    if ( empty( $glist ) ) continue;
                                                ?>
                                                <optgroup label="<?php echo esc_attr( $group_labels[ $gkey ] ); ?>">
                                                    <?php foreach ( $glist as $eq ) :
                                                        $interval_str = __( 'Bajo demanda', 'aura-suite' );
                                                        if ( $eq->requires_maintenance ) {
                                                            $p = [];
                                                            if ( $eq->interval_months ) $p[] = sprintf( _n( '%d mes', '%d meses', $eq->interval_months, 'aura-suite' ), $eq->interval_months );
                                                            if ( $eq->interval_hours )  $p[] = $eq->interval_hours . ' h';
                                                            $interval_str = implode( ' ' . __( 'o', 'aura-suite' ) . ' ', $p ) ?: __( 'Programada', 'aura-suite' );
                                                        }
                                                        $label = $eq->name;
                                                        if ( $eq->brand )         $label .= ' · ' . $eq->brand;
                                                        if ( $eq->internal_code ) $label .= ' ' . sprintf( __( '[Cód: %s]', 'aura-suite' ), $eq->internal_code );
                                                        $label .= ' — ' . $eq->badge_text;
                                                    ?>
                                                    <option value="<?php echo esc_attr( $eq->id ); ?>"
                                                            data-hours="<?php echo esc_attr( $eq->last_maintenance_hours ?: '0' ); ?>"
                                                            data-photo="<?php echo esc_attr( $eq->photo_full ); ?>"
                                                            data-status="<?php echo esc_attr( $eq->status ); ?>"
                                                            data-maint="<?php echo esc_attr( $eq->calculated_status ); ?>"
                                                            data-code="<?php echo esc_attr( $eq->internal_code ); ?>"
                                                            data-brand="<?php echo esc_attr( $eq->brand ); ?>"
                                                            data-model="<?php echo esc_attr( $eq->model ); ?>"
                                                            data-interval="<?php echo esc_attr( $interval_str ); ?>"
                                                            data-oil="<?php echo esc_attr( $eq->oil_type ? $eq->oil_type . ( $eq->oil_capacity ? ' (' . $eq->oil_capacity . ' L)' : '' ) : __( 'No especificado', 'aura-suite' ) ); ?>"
                                                            <?php selected( intval( $v('equipment_id', $preequip_id) ), intval( $eq->id ) ); ?>>
                                                        <?php echo esc_html( $label ); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="button" id="aura-open-equip-catalog-btn" class="btn btn-indigo btn-lift">
                                                <span class="dashicons dashicons-search"></span>
                                                <?php _e( 'Explorar Catálogo Visual', 'aura-suite' ); ?>
                                            </button>
                                        </div>

                                        <!-- Ficha Técnica Visual del Equipo Seleccionado -->
                                        <div id="maint-equip-card" class="aura-maint-equip-card" style="display:<?php echo $preequip ? 'block' : 'none'; ?>;">
                                            <div class="aura-maint-equip-card-flex">
                                                <div id="maint-equip-photo-wrap" class="aura-maint-equip-photo-wrap">
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
                                                         class="aura-table-thumb-preview"
                                                         style="display:<?php echo $preequip_photo_url ? 'block' : 'none'; ?>;">
                                                    <span id="maint-equip-photo-placeholder" class="dashicons dashicons-format-image" style="display:<?php echo $preequip_photo_url ? 'none' : 'block'; ?>;"></span>
                                                </div>

                                                <div style="flex:1;min-width:220px;">
                                                    <div class="aura-maint-card-hdr">
                                                        <h3 id="maint-card-title">
                                                            <?php echo $preequip ? esc_html( wp_unslash( $preequip->name ) . ( $preequip->brand ? ' · ' . wp_unslash( $preequip->brand ) : '' ) ) : '—'; ?>
                                                        </h3>
                                                        <div id="maint-card-badges" class="aura-maint-card-badges">
                                                            <span id="maint-card-code-badge" class="aura-inv-code-chip">
                                                                <?php echo $preequip && ! empty( $preequip->internal_code ) ? sprintf( esc_html__( 'Cód: %s', 'aura-suite' ), esc_html( wp_unslash( $preequip->internal_code ) ) ) : esc_html__( 'Activo', 'aura-suite' ); ?>
                                                            </span>
                                                            <span id="maint-card-status-badge" class="aura-inv-cat-chip">
                                                                <?php echo $preequip && ! empty( $preequip->status ) ? esc_html( Aura_Inventory_Equipment::get_status_label( $preequip->status ) ) : esc_html__( 'Disponible', 'aura-suite' ); ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div id="maint-card-meta-grid" class="aura-maint-card-meta-grid">
                                                        <div>
                                                            <span class="aura-maint-meta-lbl"><?php _e( 'Horas de uso', 'aura-suite' ); ?></span>
                                                            <strong id="maint-card-hours"><?php echo $preequip ? number_format( floatval( $preequip->last_maintenance_hours ), 1 ) . ' h' : '—'; ?></strong>
                                                        </div>
                                                        <div>
                                                            <span class="aura-maint-meta-lbl"><?php _e( 'Aceite recomendado', 'aura-suite' ); ?></span>
                                                            <strong id="maint-card-oil"><?php echo $preequip && $preequip->oil_type ? esc_html( $preequip->oil_type . ( $preequip->oil_capacity ? ' (' . $preequip->oil_capacity . ' L)' : '' ) ) : esc_html__( 'No especificado', 'aura-suite' ); ?></strong>
                                                        </div>
                                                        <div>
                                                            <span class="aura-maint-meta-lbl"><?php _e( 'Periodicidad', 'aura-suite' ); ?></span>
                                                            <strong id="maint-card-interval">
                                                                <?php
                                                                if ( $preequip && $preequip->requires_maintenance ) {
                                                                    $p = [];
                                                                    if ( $preequip->interval_months ) $p[] = sprintf( _n( '%d mes', '%d meses', $preequip->interval_months, 'aura-suite' ), $preequip->interval_months );
                                                                    if ( $preequip->interval_hours )  $p[] = $preequip->interval_hours . ' h';
                                                                    echo esc_html( implode( ' ' . __( 'o', 'aura-suite' ) . ' ', $p ) ?: __( 'Programada', 'aura-suite' ) );
                                                                } else {
                                                                    esc_html_e( 'Bajo demanda', 'aura-suite' );
                                                                }
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>

                                                    <div class="aura-maint-card-ff-row">
                                                        <button type="button" id="aura-fast-forward-maint-btn" class="btn btn-glass btn-sm">
                                                            <span class="dashicons dashicons-controls-forward"></span>
                                                            <?php _e( 'Adelantar Mantenimiento por Retorno de Préstamo / Cambio de Pieza', 'aura-suite' ); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="maint_date"><?php _e( 'Fecha de mantenimiento', 'aura-suite' ); ?> <span class="required">*</span></label></th>
                                    <td><input type="date" id="maint_date" name="maintenance_date" class="form-control" value="<?php echo $v( 'maintenance_date', date( 'Y-m-d' ) ); ?>" required style="max-width:240px;"></td>
                                </tr>
                                <tr>
                                    <th><label for="maint_hours"><?php _e( 'Horas del equipo al momento', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <div class="input-group aura-inv-compact-group">
                                            <input type="number" id="maint_hours" name="equipment_hours" value="<?php echo $v('equipment_hours'); ?>" step="0.1" min="0" class="form-control">
                                            <span class="input-group-text">h</span>
                                        </div>
                                        <p class="description"><?php _e( 'Actualiza las horas de uso acumuladas del equipo.', 'aura-suite' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="maint_type"><?php _e( 'Tipo de mantenimiento', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <select id="maint_type" name="type" class="form-select" style="max-width:300px;">
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
                                            <option value="<?php echo $val; ?>" <?php selected( $cur_type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            <!-- Panel informativo: instrucciones + último mantenimiento -->
                            <?php
                            $show_panel            = false;
                            $preequip_instructions = '';
                            $prev_maint_data       = null;
                            if ( $preequip_id > 0 ) {
                                $preequip_instructions = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT maintenance_instructions FROM {$wpdb->prefix}aura_inventory_equipment WHERE id = %d AND deleted_at IS NULL",
                                    $preequip_id
                                ) );
                                $prev_maint_data = $wpdb->get_row( $wpdb->prepare(
                                    "SELECT id, maintenance_date, type, description, performed_by, workshop_name, post_status
                                     FROM {$wpdb->prefix}aura_inventory_maintenance
                                     WHERE equipment_id = %d
                                     ORDER BY maintenance_date DESC, id DESC LIMIT 1",
                                    $preequip_id
                                ) );
                                $show_panel = ! empty( $preequip_instructions ) || ! empty( $prev_maint_data );
                            }
                            ?>
                            <div id="maint-instructions-panel" style="display:<?php echo $show_panel ? 'block' : 'none'; ?>;">
                                <div id="maint-instructions-block" class="aura-maint-callout aura-maint-callout-info" style="display:<?php echo ! empty( $preequip_instructions ) ? 'block' : 'none'; ?>;">
                                    <p class="aura-maint-callout-hdr">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        <?php _e( '¿Qué debe hacerse en este mantenimiento?', 'aura-suite' ); ?>
                                    </p>
                                    <pre id="maint-instructions-text"><?php echo esc_html( wp_unslash( $preequip_instructions ?? '' ) ); ?></pre>
                                </div>

                                <div id="maint-prev-block" class="aura-maint-callout aura-maint-callout-muted" style="display:<?php echo ! empty( $prev_maint_data ) ? 'block' : 'none'; ?>;">
                                    <p class="aura-maint-callout-hdr">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php _e( 'Último mantenimiento registrado', 'aura-suite' ); ?>
                                    </p>
                                    <div id="maint-prev-content">
                                        <?php if ( $prev_maint_data ) : ?>
                                        <table class="aura-maint-prev-tbl">
                                            <tr><td><?php _e( 'Fecha:', 'aura-suite' ); ?></td><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $prev_maint_data->maintenance_date ) ) ); ?></td></tr>
                                            <tr><td><?php _e( 'Tipo:', 'aura-suite' ); ?></td><td><?php echo esc_html( $types[ $prev_maint_data->type ] ?? $prev_maint_data->type ); ?></td></tr>
                                            <?php if ( $prev_maint_data->description ) : ?>
                                            <tr><td><?php _e( 'Trabajo:', 'aura-suite' ); ?></td><td><?php echo nl2br( esc_html( wp_unslash( $prev_maint_data->description ) ) ); ?></td></tr>
                                            <?php endif; ?>
                                            <?php if ( $prev_maint_data->performed_by === 'external' && $prev_maint_data->workshop_name ) : ?>
                                            <tr><td><?php _e( 'Taller:', 'aura-suite' ); ?></td><td><?php echo esc_html( wp_unslash( $prev_maint_data->workshop_name ) ); ?></td></tr>
                                            <?php endif; ?>
                                            <tr><td><?php _e( 'Estado:', 'aura-suite' ); ?></td><td><?php echo esc_html( $prev_maint_data->post_status ); ?></td></tr>
                                        </table>
                                        <?php else : ?>
                                        <em><?php _e( 'Sin mantenimientos previos registrados.', 'aura-suite' ); ?></em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Descripción del trabajo -->
                    <div class="aura-glass-card aura-inv-form-card">
                        <div class="aura-inv-card-header">
                            <h2><span class="dashicons dashicons-text-page"></span> <?php _e( 'Descripción del trabajo', 'aura-suite' ); ?></h2>
                        </div>
                        <div class="aura-inv-card-body">
                            <table class="form-table aura-inv-form-table">
                                <tr>
                                    <th><label for="maint_description"><?php _e( 'Descripción general', 'aura-suite' ); ?></label></th>
                                    <td><textarea id="maint_description" name="description" rows="4" class="form-control"><?php echo esc_textarea( wp_unslash( $maintenance->description ?? '' ) ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label for="maint_parts"><?php _e( 'Piezas / repuestos cambiados', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <textarea id="maint_parts" name="parts_replaced" rows="3" class="form-control"
                                                  placeholder="<?php esc_attr_e( 'Ej: Filtro de aceite, correa de distribución…', 'aura-suite' ); ?>"><?php echo esc_textarea( wp_unslash( $maintenance->parts_replaced ?? '' ) ); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="maint_observations"><?php _e( 'Observaciones adicionales', 'aura-suite' ); ?></label></th>
                                    <td><textarea id="maint_observations" name="observations" rows="3" class="form-control"><?php echo esc_textarea( wp_unslash( $maintenance->observations ?? '' ) ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Sección 3: Ejecutor -->
                    <div class="aura-glass-card aura-inv-form-card">
                        <div class="aura-inv-card-header">
                            <h2><span class="dashicons dashicons-businessman"></span> <?php _e( 'Ejecutor del mantenimiento', 'aura-suite' ); ?></h2>
                        </div>
                        <div class="aura-inv-card-body">
                            <table class="form-table aura-inv-form-table">
                                <tr>
                                    <th><?php _e( 'Realizado por', 'aura-suite' ); ?></th>
                                    <td class="aura-inv-radio-row">
                                        <label><input type="radio" name="performed_by" value="internal" <?php checked( $is_internal ); ?>> <?php _e( 'Personal interno', 'aura-suite' ); ?></label>
                                        <label><input type="radio" name="performed_by" value="external" <?php checked( ! $is_internal ); ?>> <?php _e( 'Taller / empresa externa', 'aura-suite' ); ?></label>
                                    </td>
                                </tr>
                            </table>

                            <div id="maint_internal_fields" <?php echo $is_internal ? '' : 'style="display:none;"'; ?>>
                                <table class="form-table aura-inv-form-table">
                                    <tr>
                                        <th><label for="maint_technician"><?php _e( 'Técnico responsable', 'aura-suite' ); ?></label></th>
                                        <td>
                                            <div class="input-group" style="max-width:460px;">
                                                <select id="maint_technician" name="internal_technician" class="form-select">
                                                    <option value=""><?php _e( '— Sin asignar —', 'aura-suite' ); ?></option>
                                                    <?php foreach ( $users as $u ) : ?>
                                                    <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( intval( $v('internal_technician') ), intval( $u->ID ) ); ?>>
                                                        <?php echo esc_html( $u->display_name ); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button"
                                                        id="aura-maint-btn-tech-explorer"
                                                        class="btn btn-glass"
                                                        data-tooltip="<?php esc_attr_e( 'Explorar todos los usuarios con filtro por rol', 'aura-suite' ); ?>">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                    <?php _e( 'Explorar Usuarios', 'aura-suite' ); ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div id="maint_external_fields" <?php echo $is_internal ? 'style="display:none;"' : ''; ?>>
                                <table class="form-table aura-inv-form-table">
                                    <tr>
                                        <th><label for="maint_workshop"><?php _e( 'Nombre del taller', 'aura-suite' ); ?></label></th>
                                        <td><input type="text" id="maint_workshop" name="workshop_name" value="<?php echo $v('workshop_name'); ?>" class="form-control"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="maint_invoice_number"><?php _e( 'N° de factura', 'aura-suite' ); ?></label></th>
                                        <td><input type="text" id="maint_invoice_number" name="invoice_number" value="<?php echo $v('invoice_number'); ?>" class="form-control" style="max-width:260px;"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="maint_workshop_invoice"><?php _e( 'URL / adjunto factura', 'aura-suite' ); ?></label></th>
                                        <td>
                                            <div class="input-group">
                                                <input type="url" id="maint_workshop_invoice" name="workshop_invoice"
                                                       value="<?php echo esc_url( $maintenance->workshop_invoice ?? '' ); ?>" class="form-control" placeholder="https://">
                                                <button type="button" id="maint_select_invoice" class="btn btn-glass" data-tooltip="<?php esc_attr_e( 'Seleccionar archivo o factura desde Media Library', 'aura-suite' ); ?>">
                                                    <span class="dashicons dashicons-upload"></span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 4: Estado post-mantenimiento -->
                    <div class="aura-glass-card aura-inv-form-card">
                        <div class="aura-inv-card-header">
                            <h2><span class="dashicons dashicons-flag"></span> <?php _e( 'Estado del equipo tras el mantenimiento', 'aura-suite' ); ?></h2>
                        </div>
                        <div class="aura-inv-card-body">
                            <table class="form-table aura-inv-form-table">
                                <tr>
                                    <th><?php _e( 'Estado resultante', 'aura-suite' ); ?></th>
                                    <td>
                                        <div class="aura-maint-ps-group">
                                            <?php
                                            $ps_opts = [
                                                'operational'    => [ 'icon' => '✅', 'label' => __( 'Operacional — listo para usar',         'aura-suite' ) ],
                                                'needs_followup' => [ 'icon' => '⚠️', 'label' => __( 'Requiere seguimiento — pendiente',      'aura-suite' ) ],
                                                'out_of_service' => [ 'icon' => '🔴', 'label' => __( 'Fuera de servicio — pasa a Reparación', 'aura-suite' ) ],
                                            ];
                                            foreach ( $ps_opts as $val => $info ) :
                                            ?>
                                            <label class="aura-maint-ps-label aura-maint-ps-<?php echo esc_attr( $val ); ?>">
                                                <input type="radio" name="post_status" value="<?php echo esc_attr( $val ); ?>" <?php checked( $cur_ps, $val ); ?>>
                                                <?php echo $info['icon'] . ' ' . esc_html( $info['label'] ); ?>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="maint_row_next_action">
                                    <th><label for="maint_next_action"><?php _e( 'Próxima acción programada', 'aura-suite' ); ?></label></th>
                                    <td>
                                        <input type="date" id="maint_next_action" name="next_action_date" class="form-control" value="<?php echo $v('next_action_date'); ?>" data-allow-future="1" style="max-width:240px;">
                                        <p class="description"><?php _e( 'Fecha de la acción de seguimiento necesaria.', 'aura-suite' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                </div><!-- .aura-inv-form-main -->

                <!-- ── Columna lateral ───────────────────────────────── -->
                <div class="aura-inv-form-sidebar">

                    <!-- Costos -->
                    <div class="aura-glass-card aura-inv-form-card">
                        <div class="aura-inv-card-header">
                            <h2><span class="dashicons dashicons-money-alt"></span> <?php _e( 'Costos', 'aura-suite' ); ?></h2>
                        </div>
                        <div class="aura-inv-card-body">
                            <div class="aura-maint-side-field">
                                <label for="maint_parts_cost"><?php _e( 'Repuestos / insumos', 'aura-suite' ); ?></label>
                                <div class="input-group" style="margin-top:6px;">
                                    <span class="input-group-text"><?php echo esc_html( $currency_sym ); ?></span>
                                    <input type="number" id="maint_parts_cost" name="parts_cost" value="<?php echo $v('parts_cost','0'); ?>" step="0.01" min="0" class="form-control">
                                </div>
                            </div>
                            <div class="aura-maint-side-field" style="margin-top:12px;">
                                <label for="maint_labor_cost"><?php _e( 'Mano de obra', 'aura-suite' ); ?></label>
                                <div class="input-group" style="margin-top:6px;">
                                    <span class="input-group-text"><?php echo esc_html( $currency_sym ); ?></span>
                                    <input type="number" id="maint_labor_cost" name="labor_cost" value="<?php echo $v('labor_cost','0'); ?>" step="0.01" min="0" class="form-control">
                                </div>
                            </div>
                            <div class="aura-maint-total-box">
                                <span><?php _e( 'Total', 'aura-suite' ); ?></span>
                                <strong id="maint_total_display">
                                    <?php echo esc_html( $currency_sym ); ?><span id="maint_total_value"><?php echo number_format( floatval( $v('total_cost','0') ), 2 ); ?></span>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- Integración Finanzas -->
                    <?php if ( ! $is_edit && ! empty( $finance_cats ) ) : ?>
                    <div class="aura-glass-card aura-inv-form-card" id="maint_finance_box">
                        <div class="aura-inv-card-header">
                            <h2><span class="dashicons dashicons-bank"></span> <?php _e( 'Registrar en Finanzas', 'aura-suite' ); ?></h2>
                        </div>
                        <div class="aura-inv-card-body">
                            <label class="aura-inv-check-label">
                                <input type="checkbox" id="maint_create_finance" name="create_finance_transaction" value="1" <?php checked( $default_auto_check ); ?>>
                                <?php _e( 'Crear transacción de egreso automáticamente', 'aura-suite' ); ?>
                            </label>
                            <div id="maint_finance_fields" style="display:<?php echo $default_auto_check ? 'block' : 'none'; ?>;margin-top:12px;">
                                <div class="aura-maint-side-field">
                                    <label for="maint_finance_category"><?php _e( 'Categoría financiera', 'aura-suite' ); ?></label>
                                    <select id="maint_finance_category" name="finance_category_id" class="form-select" style="margin-top:6px;">
                                        <option value=""><?php _e( '— Seleccionar —', 'aura-suite' ); ?></option>
                                        <?php foreach ( $finance_cats as $fc ) : ?>
                                        <option value="<?php echo esc_attr( $fc->id ); ?>" <?php selected( $default_finance_cat, $fc->id ); ?>>
                                            <?php echo esc_html( $fc->name ); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="aura-maint-side-field" style="margin-top:10px;">
                                    <label for="maint_finance_payment"><?php _e( 'Método de pago', 'aura-suite' ); ?></label>
                                    <select id="maint_finance_payment" name="finance_payment_method" class="form-select" style="margin-top:6px;">
                                        <option value="Efectivo"><?php _e( 'Efectivo', 'aura-suite' ); ?></option>
                                        <option value="Transferencia"><?php _e( 'Transferencia', 'aura-suite' ); ?></option>
                                        <option value="Cheque"><?php _e( 'Cheque', 'aura-suite' ); ?></option>
                                        <option value="Tarjeta"><?php _e( 'Tarjeta', 'aura-suite' ); ?></option>
                                    </select>
                                </div>
                            </div>
                            <p class="description" style="margin-top:8px;"><?php _e( 'Solo disponible al crear y si el costo total es mayor a 0.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <?php elseif ( $is_edit && $maintenance && $maintenance->finance_transaction_id ) : ?>
                    <div class="aura-glass-card aura-inv-form-card">
                        <div class="aura-inv-card-header">
                            <h2><span class="dashicons dashicons-bank"></span> <?php _e( 'Finanzas', 'aura-suite' ); ?></h2>
                        </div>
                        <div class="aura-inv-card-body">
                            <span class="aura-maint-finance-ok">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php printf( __( 'Transacción vinculada #%d', 'aura-suite' ), intval( $maintenance->finance_transaction_id ) ); ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Acciones -->
                    <div class="aura-glass-card aura-inv-form-card">
                        <div class="aura-inv-card-body" style="text-align:center;">
                            <button type="button" id="aura-maint-save-btn" class="btn btn-shimmer btn-indigo btn-lift" style="width:100%;margin-bottom:8px;justify-content:center;">
                                <span class="dashicons dashicons-yes"></span>
                                <?php echo $is_edit ? esc_html__( 'Actualizar mantenimiento', 'aura-suite' ) : esc_html__( 'Registrar mantenimiento', 'aura-suite' ); ?>
                            </button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-maintenance' ) ); ?>" class="btn btn-glass" style="width:100%;justify-content:center;">
                                <?php _e( 'Cancelar', 'aura-suite' ); ?>
                            </a>
                            <div id="aura-maint-form-notice" style="margin-top:12px;"></div>
                        </div>
                    </div>

                </div><!-- .aura-inv-form-sidebar -->

            </div><!-- .aura-inv-form-layout -->
        </form>

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

<!-- Modal Catálogo Rápido de Equipos (Item 26) -->
<div id="aura-equip-picker-modal" class="aura-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="aura-equip-picker-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:920px;">
        <div class="aura-modal-header">
            <h3 id="aura-equip-picker-title" class="aura-modal-title">
                <span class="dashicons dashicons-search"></span>
                <?php _e( 'Catálogo Rápido: Seleccionar Equipo para Mantenimiento', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal-close aura-equip-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <div class="aura-modal-body">
            <div style="margin-bottom:14px;">
                <div class="input-group" style="margin-bottom:10px;">
                    <span class="input-group-text"><span class="dashicons dashicons-search"></span></span>
                    <input type="search" id="aura-equip-picker-search" class="form-control"
                           placeholder="<?php esc_attr_e( 'Escribe para buscar por nombre, marca, serie o código interno en tiempo real…', 'aura-suite' ); ?>">
                </div>

                <div id="aura-equip-picker-chips" class="aura-equip-picker-chips">
                    <button type="button" class="aura-equip-chip is-active" data-filter="all">
                        <?php printf( __( 'Todos (%d)', 'aura-suite' ), count( $equipment_list ) ); ?>
                    </button>
                    <button type="button" class="aura-equip-chip aura-chip-urgent" data-filter="urgent">
                        <?php printf( __( '🔴 Requieren Atención (%d)', 'aura-suite' ), count( $equip_groups['urgent'] ) ); ?>
                    </button>
                    <button type="button" class="aura-equip-chip aura-chip-warning" data-filter="warning">
                        <?php printf( __( '🟡 Próximos (%d)', 'aura-suite' ), count( $equip_groups['warning'] ) ); ?>
                    </button>
                    <button type="button" class="aura-equip-chip aura-chip-in-use" data-filter="in_use">
                        <?php printf( __( '🔵 En Uso (%d)', 'aura-suite' ), count( $equip_groups['in_use'] ) ); ?>
                    </button>
                    <button type="button" class="aura-equip-chip aura-chip-ok" data-filter="ok">
                        <?php printf( __( '✅ Al Día (%d)', 'aura-suite' ), count( $equip_groups['ok'] ) ); ?>
                    </button>
                </div>
            </div>

            <div id="aura-equip-picker-list" class="aura-equip-picker-list-wrap">
                <table class="aura-table aura-equip-picker-table">
                    <thead>
                        <tr>
                            <th style="width:54px;text-align:center;"><?php _e( 'Foto', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Equipo / Identificación', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Horas', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Estado Operativo', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Mantenimiento', 'aura-suite' ); ?></th>
                            <th style="width:110px;text-align:right;"><?php _e( 'Acción', 'aura-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="aura-equip-picker-tbody">
                        <?php foreach ( $equipment_list as $eq ) :
                            $filter_cat = 'ok';
                            if ( $eq->calculated_status === 'overdue' || $eq->calculated_status === 'urgent' || in_array( $eq->status, [ 'repair', 'maintenance' ] ) ) {
                                $filter_cat = 'urgent';
                            } elseif ( $eq->calculated_status === 'warning' ) {
                                $filter_cat = 'warning';
                            } elseif ( $eq->status === 'in_use' ) {
                                $filter_cat = 'in_use';
                            }
                            $search_haystack = strtolower( $eq->name . ' ' . $eq->brand . ' ' . $eq->model . ' ' . $eq->internal_code );
                        ?>
                        <tr class="aura-equip-picker-row"
                            data-id="<?php echo esc_attr( $eq->id ); ?>"
                            data-group="<?php echo esc_attr( $filter_cat ); ?>"
                            data-search="<?php echo esc_attr( $search_haystack ); ?>">
                            <td style="text-align:center;">
                                <?php if ( ! empty( $eq->photo_thumb ) ) : ?>
                                    <img src="<?php echo esc_url( $eq->photo_thumb ); ?>" alt="<?php echo esc_attr( $eq->name ); ?>"
                                         class="aura-equip-picker-thumb aura-table-thumb-preview"
                                         data-img-url="<?php echo esc_url( $eq->photo_full ?: $eq->photo_thumb ); ?>"
                                         data-preview-title="<?php echo esc_attr( $eq->name ); ?>">
                                <?php else : ?>
                                    <span class="dashicons dashicons-format-image aura-inv-muted"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $eq->name ); ?></strong>
                                <div class="aura-inv-cell-sub">
                                    <?php
                                    $details = [];
                                    if ( $eq->brand )         $details[] = esc_html( $eq->brand );
                                    if ( $eq->model )         $details[] = esc_html( $eq->model );
                                    if ( $eq->internal_code ) $details[] = '<code>' . esc_html( $eq->internal_code ) . '</code>';
                                    echo implode( ' · ', $details );
                                    ?>
                                </div>
                            </td>
                            <td><strong><?php echo number_format( floatval( $eq->last_maintenance_hours ), 1 ); ?> h</strong></td>
                            <td><span class="aura-inv-cat-chip"><?php echo esc_html( Aura_Inventory_Equipment::get_status_label( $eq->status ) ); ?></span></td>
                            <td><span class="aura-inv-maint-badge aura-inv-maint-<?php echo esc_attr( $eq->calculated_status === 'none' ? 'ok' : $eq->calculated_status ); ?>"><?php echo esc_html( $eq->badge_text ); ?></span></td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-indigo btn-sm aura-equip-picker-select-btn" data-id="<?php echo esc_attr( $eq->id ); ?>">
                                    <?php _e( 'Seleccionar', 'aura-suite' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="aura-equip-picker-empty" class="aura-inv-empty-state" style="display:none;">
                    <span class="dashicons dashicons-info"></span>
                    <p><?php _e( 'No se encontraron equipos que coincidan con la búsqueda o el filtro.', 'aura-suite' ); ?></p>
                </div>
            </div>
        </div>

        <div class="aura-modal-footer">
            <button type="button" class="btn btn-glass aura-equip-modal-close"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>

<?php
$_maint_form_js = wp_json_encode( [
    'ajaxurl'              => admin_url( 'admin-ajax.php' ),
    'nonce'                => wp_create_nonce( 'aura_inventory_nonce' ),
    'maintId'              => $maint_id,
    'isEdit'               => $is_edit,
    'listUrl'              => admin_url( 'admin.php?page=aura-inventory-maintenance' ),
    'preequipPhoto'        => isset( $preequip_photo_url ) ? $preequip_photo_url : '',
    'preequipInstructions' => isset( $preequip_instructions ) ? (string) wp_unslash( $preequip_instructions ) : '',
    'preequipPrevMaint'    => isset( $prev_maint_data ) ? $prev_maint_data : null,
    'currency'             => $currency_sym,
    'txt' => [
        'saving'             => __( 'Guardando…', 'aura-suite' ),
        'saved'              => __( 'Mantenimiento guardado correctamente.', 'aura-suite' ),
        'finance_ok'         => __( ' (transacción financiera creada)', 'aura-suite' ),
        'error'              => __( 'Error al guardar el mantenimiento.', 'aura-suite' ),
        'required_equip'     => __( 'Selecciona un equipo.', 'aura-suite' ),
        'required_date'      => __( 'La fecha es obligatoria.', 'aura-suite' ),
        'instructions_title' => __( '¿Qué debe hacerse en este mantenimiento?', 'aura-suite' ),
        'prev_maint_title'   => __( 'Último mantenimiento registrado', 'aura-suite' ),
        'no_prev_maint'      => __( 'Sin mantenimientos previos registrados.', 'aura-suite' ),
        'type_labels'        => $types,
        'ps_labels' => [
            'operational'    => __( 'Operacional', 'aura-suite' ),
            'needs_followup' => __( 'Requiere seguimiento', 'aura-suite' ),
            'out_of_service' => __( 'Fuera de servicio', 'aura-suite' ),
        ],
        'ps_icons' => [
            'operational'    => '✅',
            'needs_followup' => '⚠️',
            'out_of_service' => '🔴',
        ],
        'date_label'     => __( 'Fecha:', 'aura-suite' ),
        'type_label'     => __( 'Tipo:', 'aura-suite' ),
        'work_label'     => __( 'Trabajo:', 'aura-suite' ),
        'workshop_label' => __( 'Taller:', 'aura-suite' ),
        'status_label'   => __( 'Estado:', 'aura-suite' ),
    ],
] );
?>
<script>window.auraMaintForm = <?php echo $_maint_form_js; ?>;</script>
