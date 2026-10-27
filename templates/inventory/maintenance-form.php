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
                interval_months, interval_hours, last_maintenance_hours, next_maintenance_hours, oil_type, oil_capacity, photo
         FROM {$wpdb->prefix}aura_inventory_equipment WHERE id = %d AND deleted_at IS NULL",
        $preequip_id
    ) );
}

// Listas
$users = Aura_Roles_Manager::get_aura_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

// Consulta enriquecida de equipos para el selector inteligente y catálogo interactivo
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
    'urgent'  => [], // 🔴 Vencidos, urgentes o en reparación/mantenimiento
    'warning' => [], // 🟡 Próximos a vencer
    'in_use'  => [], // 🔵 En préstamo / uso
    'ok'      => [], // ✅ Al día / disponibles
];

$today_ts = strtotime( date( 'Y-m-d' ) );

foreach ( $equipment_list as $eq ) {
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

    // Clasificación de conveniencia para el usuario
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
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-equipment' ) ); ?>" class="page-title-action">
        <span class="dashicons dashicons-arrow-left-alt" style="vertical-align:middle;margin-top:-2px;font-size:16px;"></span>
        <?php _e( 'Volver al Inventario', 'aura-suite' ); ?>
    </a>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-maintenance' ) ); ?>" class="page-title-action">
        <span class="dashicons dashicons-list-view" style="vertical-align:middle;margin-top:-2px;font-size:16px;"></span>
        <?php _e( 'Ver Historial de Mantenimientos', 'aura-suite' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( $preequip && ! $is_edit ) : ?>
    <div class="notice notice-info is-dismissible" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-left-color:#2271b1;margin:16px 0 20px 0;">
        <span class="dashicons dashicons-admin-tools" style="font-size:24px;width:24px;height:24px;color:#2271b1;"></span>
        <div>
            <strong style="font-size:14px;color:#1d2327;">
                <?php printf( __( 'Registrando mantenimiento para: %s', 'aura-suite' ), esc_html( $preequip->name ) ); ?>
            </strong>
            <div style="font-size:12px;color:#646970;margin-top:2px;">
                <?php
                $meta_tags = [];
                if ( ! empty( $preequip->brand ) ) $meta_tags[] = __( 'Marca:', 'aura-suite' ) . ' ' . esc_html( $preequip->brand );
                if ( ! empty( $preequip->model ) ) $meta_tags[] = __( 'Modelo:', 'aura-suite' ) . ' ' . esc_html( $preequip->model );
                if ( ! empty( $preequip->last_maintenance_hours ) ) $meta_tags[] = __( 'Horas:', 'aura-suite' ) . ' ' . number_format( $preequip->last_maintenance_hours, 1 ) . ' h';
                echo implode( ' · ', $meta_tags );
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                                    <div class="aura-equip-select-row" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                                        <select id="maint_equipment_id" name="equipment_id" class="regular-text" style="min-width:320px;max-width:100%;height:38px;font-size:13px;" required>
                                            <option value=""><?php _e( '— Seleccionar equipo del listado —', 'aura-suite' ); ?></option>

                                            <?php if ( ! empty( $equip_groups['urgent'] ) ) : ?>
                                            <optgroup label="<?php echo esc_attr( sprintf( __( '🔴 Mantenimiento Vencido o Requiere Atención (%d)', 'aura-suite' ), count( $equip_groups['urgent'] ) ) ); ?>">
                                                <?php foreach ( $equip_groups['urgent'] as $eq ) : ?>
                                                    <option value="<?php echo esc_attr( $eq->id ); ?>"
                                                            data-hours="<?php echo esc_attr( $eq->last_maintenance_hours ?: '0' ); ?>"
                                                            data-photo="<?php echo esc_attr( $eq->photo_full ); ?>"
                                                            data-status="<?php echo esc_attr( $eq->status ); ?>"
                                                            data-maint="<?php echo esc_attr( $eq->calculated_status ); ?>"
                                                            data-code="<?php echo esc_attr( $eq->internal_code ); ?>"
                                                            data-brand="<?php echo esc_attr( $eq->brand ); ?>"
                                                            data-model="<?php echo esc_attr( $eq->model ); ?>"
                                                            data-oil="<?php echo esc_attr( $eq->oil_type ? $eq->oil_type . ( $eq->oil_capacity ? ' (' . $eq->oil_capacity . ' L)' : '' ) : 'No especificado' ); ?>"
                                                            <?php selected( intval( $v('equipment_id', $preequip_id) ), intval( $eq->id ) ); ?>>
                                                        <?php
                                                        $label = $eq->name;
                                                        if ( $eq->brand )         $label .= ' · ' . $eq->brand;
                                                        if ( $eq->internal_code ) $label .= ' [Cód: ' . $eq->internal_code . ']';
                                                        $label .= ' — ' . $eq->badge_text;
                                                        echo esc_html( $label );
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $equip_groups['warning'] ) ) : ?>
                                            <optgroup label="<?php echo esc_attr( sprintf( __( '🟡 Próximo Mantenimiento (%d)', 'aura-suite' ), count( $equip_groups['warning'] ) ) ); ?>">
                                                <?php foreach ( $equip_groups['warning'] as $eq ) : ?>
                                                    <option value="<?php echo esc_attr( $eq->id ); ?>"
                                                            data-hours="<?php echo esc_attr( $eq->last_maintenance_hours ?: '0' ); ?>"
                                                            data-photo="<?php echo esc_attr( $eq->photo_full ); ?>"
                                                            data-status="<?php echo esc_attr( $eq->status ); ?>"
                                                            data-maint="<?php echo esc_attr( $eq->calculated_status ); ?>"
                                                            data-code="<?php echo esc_attr( $eq->internal_code ); ?>"
                                                            data-brand="<?php echo esc_attr( $eq->brand ); ?>"
                                                            data-model="<?php echo esc_attr( $eq->model ); ?>"
                                                            data-oil="<?php echo esc_attr( $eq->oil_type ? $eq->oil_type . ( $eq->oil_capacity ? ' (' . $eq->oil_capacity . ' L)' : '' ) : 'No especificado' ); ?>"
                                                            <?php selected( intval( $v('equipment_id', $preequip_id) ), intval( $eq->id ) ); ?>>
                                                        <?php
                                                        $label = $eq->name;
                                                        if ( $eq->brand )         $label .= ' · ' . $eq->brand;
                                                        if ( $eq->internal_code ) $label .= ' [Cód: ' . $eq->internal_code . ']';
                                                        $label .= ' — ' . $eq->badge_text;
                                                        echo esc_html( $label );
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $equip_groups['in_use'] ) ) : ?>
                                            <optgroup label="<?php echo esc_attr( sprintf( __( '🔵 En Uso / Prestados — Retorno o Revisión (%d)', 'aura-suite' ), count( $equip_groups['in_use'] ) ) ); ?>">
                                                <?php foreach ( $equip_groups['in_use'] as $eq ) : ?>
                                                    <option value="<?php echo esc_attr( $eq->id ); ?>"
                                                            data-hours="<?php echo esc_attr( $eq->last_maintenance_hours ?: '0' ); ?>"
                                                            data-photo="<?php echo esc_attr( $eq->photo_full ); ?>"
                                                            data-status="<?php echo esc_attr( $eq->status ); ?>"
                                                            data-maint="<?php echo esc_attr( $eq->calculated_status ); ?>"
                                                            data-code="<?php echo esc_attr( $eq->internal_code ); ?>"
                                                            data-brand="<?php echo esc_attr( $eq->brand ); ?>"
                                                            data-model="<?php echo esc_attr( $eq->model ); ?>"
                                                            data-oil="<?php echo esc_attr( $eq->oil_type ? $eq->oil_type . ( $eq->oil_capacity ? ' (' . $eq->oil_capacity . ' L)' : '' ) : 'No especificado' ); ?>"
                                                            <?php selected( intval( $v('equipment_id', $preequip_id) ), intval( $eq->id ) ); ?>>
                                                        <?php
                                                        $label = $eq->name;
                                                        if ( $eq->brand )         $label .= ' · ' . $eq->brand;
                                                        if ( $eq->internal_code ) $label .= ' [Cód: ' . $eq->internal_code . ']';
                                                        $label .= ' — ' . $eq->badge_text;
                                                        echo esc_html( $label );
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $equip_groups['ok'] ) ) : ?>
                                            <optgroup label="<?php echo esc_attr( sprintf( __( '✅ Disponibles / Operativos al Día (%d)', 'aura-suite' ), count( $equip_groups['ok'] ) ) ); ?>">
                                                <?php foreach ( $equip_groups['ok'] as $eq ) : ?>
                                                    <option value="<?php echo esc_attr( $eq->id ); ?>"
                                                            data-hours="<?php echo esc_attr( $eq->last_maintenance_hours ?: '0' ); ?>"
                                                            data-photo="<?php echo esc_attr( $eq->photo_full ); ?>"
                                                            data-status="<?php echo esc_attr( $eq->status ); ?>"
                                                            data-maint="<?php echo esc_attr( $eq->calculated_status ); ?>"
                                                            data-code="<?php echo esc_attr( $eq->internal_code ); ?>"
                                                            data-brand="<?php echo esc_attr( $eq->brand ); ?>"
                                                            data-model="<?php echo esc_attr( $eq->model ); ?>"
                                                            data-oil="<?php echo esc_attr( $eq->oil_type ? $eq->oil_type . ( $eq->oil_capacity ? ' (' . $eq->oil_capacity . ' L)' : '' ) : 'No especificado' ); ?>"
                                                            <?php selected( intval( $v('equipment_id', $preequip_id) ), intval( $eq->id ) ); ?>>
                                                        <?php
                                                        $label = $eq->name;
                                                        if ( $eq->brand )         $label .= ' · ' . $eq->brand;
                                                        if ( $eq->internal_code ) $label .= ' [Cód: ' . $eq->internal_code . ']';
                                                        $label .= ' — ' . $eq->badge_text;
                                                        echo esc_html( $label );
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>
                                        </select>

                                        <button type="button" id="aura-open-equip-catalog-btn" class="button button-primary" style="height:38px;display:inline-flex;align-items:center;gap:6px;font-weight:600;padding:0 14px;border-radius:6px;background:#2271b1;">
                                            <span class="dashicons dashicons-search" style="font-size:18px;line-height:18px;width:18px;height:18px;"></span>
                                            <?php _e( 'Explorar Catálogo con Buscador', 'aura-suite' ); ?>
                                        </button>
                                    </div>

                                    <!-- Ficha Técnica Visual del Equipo Seleccionado -->
                                    <div id="maint-equip-card" class="aura-glass-card" style="display:<?php echo $preequip ? 'block' : 'none'; ?>;padding:16px 20px;border-radius:8px;margin-top:12px;border:1px solid #cbd5e1;background:#f8fafc;">
                                        <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
                                            <div id="maint-equip-photo-wrap" style="width:130px;height:95px;flex-shrink:0;border-radius:6px;overflow:hidden;border:1px solid #dcdcde;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
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
                                                     style="width:100%;height:100%;object-fit:cover;display:<?php echo $preequip_photo_url ? 'block' : 'none'; ?>;">
                                                <span id="maint-equip-photo-placeholder" class="dashicons dashicons-format-image" style="font-size:32px;width:32px;height:32px;color:#94a3b8;display:<?php echo $preequip_photo_url ? 'none' : 'block'; ?>;"></span>
                                            </div>

                                            <div style="flex:1;min-width:240px;">
                                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:6px;">
                                                    <h3 id="maint-card-title" style="margin:0;font-size:16px;color:#0f172a;font-weight:700;">
                                                        <?php echo $preequip ? esc_html( $preequip->name . ( $preequip->brand ? ' · ' . $preequip->brand : '' ) ) : '—'; ?>
                                                    </h3>
                                                    <div id="maint-card-badges" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                                        <span id="maint-card-code-badge" class="badge" style="background:#e2e8f0;color:#334155;font-weight:600;font-size:11px;padding:3px 8px;border-radius:4px;">
                                                            <?php echo $preequip && ! empty($preequip->internal_code) ? 'Cód: ' . esc_html( $preequip->internal_code ) : 'Activo'; ?>
                                                        </span>
                                                        <span id="maint-card-status-badge" class="badge" style="background:#dbeafe;color:#1e40af;font-weight:600;font-size:11px;padding:3px 8px;border-radius:4px;">
                                                            <?php echo $preequip && ! empty($preequip->status) ? esc_html( Aura_Inventory_Equipment::get_status_label($preequip->status) ) : 'Disponible'; ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div id="maint-card-meta-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:8px 12px;font-size:12px;color:#475569;margin-top:8px;">
                                                    <div>
                                                        <span style="display:block;font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600;"><?php _e( 'Horas de uso:', 'aura-suite' ); ?></span>
                                                        <strong id="maint-card-hours" style="color:#0f172a;font-size:13px;"><?php echo $preequip ? number_format( floatval($preequip->last_maintenance_hours), 1 ) . ' h' : '—'; ?></strong>
                                                    </div>
                                                    <div>
                                                        <span style="display:block;font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600;"><?php _e( 'Aceite recomendado:', 'aura-suite' ); ?></span>
                                                        <strong id="maint-card-oil" style="color:#0f172a;font-size:13px;"><?php echo $preequip && $preequip->oil_type ? esc_html( $preequip->oil_type . ( $preequip->oil_capacity ? ' (' . $preequip->oil_capacity . ' L)' : '' ) ) : 'No especificado'; ?></strong>
                                                    </div>
                                                    <div>
                                                        <span style="display:block;font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600;"><?php _e( 'Periodicidad:', 'aura-suite' ); ?></span>
                                                        <strong id="maint-card-interval" style="color:#0f172a;font-size:13px;">
                                                            <?php
                                                            if ( $preequip && $preequip->requires_maintenance ) {
                                                                $p = [];
                                                                if ( $preequip->interval_months ) $p[] = $preequip->interval_months . ' meses';
                                                                if ( $preequip->interval_hours )  $p[] = $preequip->interval_hours . ' h';
                                                                echo esc_html( implode( ' o ', $p ) ?: 'Programada' );
                                                            } else {
                                                                echo 'Bajo demanda';
                                                            }
                                                            ?>
                                                        </strong>
                                                    </div>
                                                </div>

                                                <!-- Botón para adelantar servicio o mantenimiento preventivo -->
                                                <div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                                    <button type="button" id="aura-fast-forward-maint-btn" class="button button-small" style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;color:#0369a1;border-color:#7dd3fc;background:#f0f9ff;">
                                                        <span class="dashicons dashicons-controls-forward" style="font-size:15px;width:15px;height:15px;line-height:15px;"></span>
                                                        <?php _e( 'Adelantar Mantenimiento por Retorno de Préstamo / Cambio de Pieza', 'aura-suite' ); ?>
                                                    </button>
                                                    <span style="font-size:11px;color:#64748b;"><?php _e( 'Auto-configura la fecha de hoy, tipo preventivo y actualiza las horas.', 'aura-suite' ); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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

<!-- Modal: Explorador y Catálogo Rápido de Equipos -->
<div id="aura-equip-picker-modal" class="aura-inv-modal">
    <div class="aura-inv-modal-overlay"></div>
    <div class="aura-inv-modal-content aura-inv-modal-large" style="max-width:920px;width:95%;">
        <div class="aura-inv-modal-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e8f0;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="dashicons dashicons-search" style="font-size:22px;color:#2271b1;line-height:22px;height:22px;"></span>
                <h2 style="margin:0;font-size:17px;font-weight:700;color:#0f172a;"><?php _e( 'Catálogo Rápido: Seleccionar Equipo para Mantenimiento', 'aura-suite' ); ?></h2>
            </div>
            <button type="button" class="aura-equip-modal-close dashicons dashicons-no-alt" style="background:none;border:none;cursor:pointer;font-size:20px;color:#64748b;"></button>
        </div>

        <div class="aura-inv-modal-body" style="padding:18px 20px;">
            <!-- Barra de búsqueda en vivo y pestañas de filtro -->
            <div style="margin-bottom:16px;">
                <div style="position:relative;margin-bottom:12px;">
                    <span class="dashicons dashicons-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:18px;"></span>
                    <input type="search" id="aura-equip-picker-search" placeholder="<?php esc_attr_e( 'Escribe para buscar por nombre, marca, serie o código interno en tiempo real…', 'aura-suite' ); ?>" style="width:100%;height:42px;padding-left:38px;padding-right:12px;border-radius:6px;border:1px solid #cbd5e1;font-size:14px;box-sizing:border-box;">
                </div>

                <!-- Chips de filtro de estado -->
                <div id="aura-equip-picker-chips" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
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
                        <?php printf( __( '🔵 En Uso / Prestados (%d)', 'aura-suite' ), count( $equip_groups['in_use'] ) ); ?>
                    </button>
                    <button type="button" class="aura-equip-chip aura-chip-ok" data-filter="ok">
                        <?php printf( __( '✅ Al Día (%d)', 'aura-suite' ), count( $equip_groups['ok'] ) ); ?>
                    </button>
                </div>
            </div>

            <!-- Listado con scroll -->
            <div id="aura-equip-picker-list" style="max-height:420px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;background:#ffffff;">
                <table class="wp-list-table widefat striped" style="margin:0;border:none;">
                    <thead>
                        <tr style="position:sticky;top:0;background:#f8fafc;z-index:2;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                            <th style="width:50px;text-align:center;"><?php _e( 'Foto', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Equipo / Identificación', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Horas de uso', 'aura-suite' ); ?></th>
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
                            data-search="<?php echo esc_attr( $search_haystack ); ?>"
                            data-name="<?php echo esc_attr( $eq->name . ( $eq->brand ? ' · ' . $eq->brand : '' ) ); ?>"
                            data-code="<?php echo esc_attr( $eq->internal_code ); ?>"
                            data-status="<?php echo esc_attr( $eq->status ); ?>"
                            data-status-label="<?php echo esc_attr( Aura_Inventory_Equipment::get_status_label( $eq->status ) ); ?>"
                            data-hours="<?php echo esc_attr( number_format( floatval( $eq->last_maintenance_hours ), 1 ) ); ?>"
                            data-oil="<?php echo esc_attr( $eq->oil_type ? $eq->oil_type . ( $eq->oil_capacity ? ' (' . $eq->oil_capacity . ' L)' : '' ) : 'No especificado' ); ?>"
                            data-interval="<?php
                                if ( $eq->requires_maintenance ) {
                                    $p = [];
                                    if ( $eq->interval_months ) $p[] = $eq->interval_months . ' meses';
                                    if ( $eq->interval_hours )  $p[] = $eq->interval_hours . ' h';
                                    echo esc_attr( implode( ' o ', $p ) ?: 'Programada' );
                                } else {
                                    echo 'Bajo demanda';
                                }
                            ?>"
                            data-photo="<?php echo esc_attr( $eq->photo_full ); ?>">
                            
                            <td style="text-align:center;padding:8px 4px;">
                                <?php if ( ! empty( $eq->photo_thumb ) ) : ?>
                                    <img src="<?php echo esc_url( $eq->photo_thumb ); ?>" alt="" style="width:38px;height:38px;border-radius:4px;object-fit:cover;display:block;margin:0 auto;border:1px solid #cbd5e1;">
                                <?php else : ?>
                                    <span class="dashicons dashicons-format-image" style="color:#94a3b8;font-size:24px;width:24px;height:24px;margin:0 auto;display:block;"></span>
                                <?php endif; ?>
                            </td>

                            <td style="vertical-align:middle;">
                                <strong style="font-size:13px;color:#0f172a;"><?php echo esc_html( $eq->name ); ?></strong>
                                <div style="font-size:11.5px;color:#64748b;margin-top:2px;">
                                    <?php
                                    $details = [];
                                    if ( $eq->brand )         $details[] = esc_html( $eq->brand );
                                    if ( $eq->model )         $details[] = esc_html( $eq->model );
                                    if ( $eq->internal_code ) $details[] = '<code style="background:#e2e8f0;padding:1px 4px;border-radius:3px;">' . esc_html( $eq->internal_code ) . '</code>';
                                    echo implode( ' · ', $details );
                                    ?>
                                </div>
                            </td>

                            <td style="vertical-align:middle;font-weight:600;color:#334155;">
                                <?php echo number_format( floatval( $eq->last_maintenance_hours ), 1 ); ?> h
                            </td>

                            <td style="vertical-align:middle;">
                                <span class="badge" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;font-size:11px;padding:2px 7px;border-radius:4px;">
                                    <?php echo esc_html( Aura_Inventory_Equipment::get_status_label( $eq->status ) ); ?>
                                </span>
                            </td>

                            <td style="vertical-align:middle;">
                                <?php
                                $badge_bg = '#f1f5f9';
                                $badge_fg = '#475569';
                                if ( $eq->calculated_status === 'overdue' ) {
                                    $badge_bg = '#fee2e2'; $badge_fg = '#991b1b';
                                } elseif ( $eq->calculated_status === 'urgent' ) {
                                    $badge_bg = '#ffedd5'; $badge_fg = '#9a3412';
                                } elseif ( $eq->calculated_status === 'warning' ) {
                                    $badge_bg = '#fef9c3'; $badge_fg = '#854d0e';
                                } elseif ( $eq->calculated_status === 'ok' ) {
                                    $badge_bg = '#ecfdf5'; $badge_fg = '#065f46';
                                }
                                ?>
                                <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $badge_bg; ?>;color:<?php echo $badge_fg; ?>;">
                                    <?php echo esc_html( $eq->badge_text ); ?>
                                </span>
                            </td>

                            <td style="vertical-align:middle;text-align:right;">
                                <button type="button" class="button button-primary button-small aura-equip-picker-select-btn" data-id="<?php echo esc_attr( $eq->id ); ?>">
                                    <?php _e( 'Seleccionar', 'aura-suite' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="aura-equip-picker-empty" style="display:none;padding:30px;text-align:center;color:#64748b;">
                    <span class="dashicons dashicons-info" style="font-size:32px;width:32px;height:32px;color:#94a3b8;margin-bottom:8px;display:block;margin:0 auto 8px auto;"></span>
                    <p style="margin:0;font-size:14px;"><?php _e( 'No se encontraron equipos que coincidan con la búsqueda o el filtro.', 'aura-suite' ); ?></p>
                </div>
            </div>
        </div>
        <div class="aura-inv-modal-footer" style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;">
            <button type="button" class="button aura-equip-modal-close"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>

<style>
.aura-equip-chip {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.aura-equip-chip:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.aura-equip-chip.is-active {
    background: #2271b1;
    color: #ffffff;
    border-color: #2271b1;
}
.aura-equip-chip.aura-chip-urgent.is-active {
    background: #dc2626;
    border-color: #dc2626;
}
.aura-equip-chip.aura-chip-warning.is-active {
    background: #d97706;
    border-color: #d97706;
}
.aura-equip-chip.aura-chip-in-use.is-active {
    background: #0284c7;
    border-color: #0284c7;
}
.aura-equip-chip.aura-chip-ok.is-active {
    background: #059669;
    border-color: #059669;
}
.aura-equip-picker-row:hover {
    background: #f0f7ff !important;
}
#aura-equip-picker-modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
}
#aura-equip-picker-modal.is-open {
    display: flex !important;
}
#aura-equip-picker-modal .aura-inv-modal-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(2px);
}
#aura-equip-picker-modal .aura-inv-modal-content {
    position: relative;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
    z-index: 2;
    overflow: hidden;
}
body.aura-dark-mode #aura-equip-picker-modal .aura-inv-modal-content {
    background: #1e293b;
    color: #f8fafc;
    border: 1px solid #334155;
}
body.aura-dark-mode #aura-equip-picker-list {
    background: #0f172a;
    border-color: #334155;
}
body.aura-dark-mode #aura-equip-picker-list table thead tr {
    background: #1e293b;
}
body.aura-dark-mode #aura-equip-picker-list table tbody tr:hover {
    background: #1e293b !important;
}
body.aura-dark-mode #maint-equip-card {
    background: #1e293b;
    border-color: #334155;
}
body.aura-dark-mode #maint-card-title {
    color: #f8fafc !important;
}
</style>

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
wp_enqueue_script(
    'aura-inventory-maintenance',
    AURA_PLUGIN_URL . 'assets/js/inventory-maintenance.js',
    ['jquery', 'jquery-ui-datepicker'],
    AURA_VERSION,
    true
);
?>
<script>
window.auraMaintForm = <?php echo $_maint_form_js; ?>;
if (window.MaintenanceForm && typeof window.MaintenanceForm.init === 'function') {
    window.MaintenanceForm.init();
}

jQuery(document).ready(function ($) {
    var $modal = $('#aura-equip-picker-modal');
    var $card  = $('#maint-equip-card');

    function applyCatalogFilter(groupFilter, searchQuery) {
        var q = (searchQuery || '').toLowerCase().trim();
        var visibleCount = 0;

        $('#aura-equip-picker-tbody .aura-equip-picker-row').each(function () {
            var $row = $(this);
            var rowGroup = $row.data('group') || '';
            var rowSearch = ($row.data('search') || '').toString().toLowerCase();

            var matchGroup = (groupFilter === 'all') || (rowGroup === groupFilter);
            var matchSearch = !q || (rowSearch.indexOf(q) !== -1);

            if (matchGroup && matchSearch) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });

        $('#aura-equip-picker-empty').toggle(visibleCount === 0);
    }

    // Abrir modal con display: flex
    $(document).on('click', '#aura-open-equip-catalog-btn', function (e) {
        e.preventDefault();
        $modal.css('display', 'flex').hide().fadeIn(150);
        $('#aura-equip-picker-search').val('').focus();
        $('#aura-equip-picker-chips .aura-equip-chip').removeClass('is-active');
        $('#aura-equip-picker-chips .aura-equip-chip[data-filter="all"]').addClass('is-active');
        applyCatalogFilter('all', '');
    });

    // Cerrar modal
    $(document).on('click', '.aura-equip-modal-close, .aura-inv-modal-overlay', function (e) {
        e.preventDefault();
        $modal.fadeOut(150);
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $modal.is(':visible')) {
            $modal.fadeOut(150);
        }
    });

    // Filtros por chips
    var currentChip = 'all';
    $(document).on('click', '#aura-equip-picker-chips .aura-equip-chip', function (e) {
        e.preventDefault();
        $('#aura-equip-picker-chips .aura-equip-chip').removeClass('is-active');
        $(this).addClass('is-active');
        currentChip = $(this).data('filter') || 'all';
        applyCatalogFilter(currentChip, $('#aura-equip-picker-search').val());
    });

    // Búsqueda en vivo
    $(document).on('input', '#aura-equip-picker-search', function () {
        applyCatalogFilter(currentChip, $(this).val());
    });

    // Seleccionar equipo desde el modal
    $(document).on('click', '.aura-equip-picker-select-btn', function (e) {
        e.preventDefault();
        var equipId = $(this).data('id');
        if (equipId) {
            $('#maint_equipment_id').val(equipId).trigger('change');
            $modal.fadeOut(150);
            $('html, body').animate({
                scrollTop: $('#maint_equipment_id').offset().top - 80
            }, 300);
        }
    });

    // Actualizar Ficha Técnica Dinámica
    function updateEquipmentCard(equipId) {
        if (!equipId) {
            $card.slideUp(150);
            return;
        }

        var $opt = $('#maint_equipment_id').find('option:selected');
        if (!$opt.length || !$opt.val()) {
            $card.slideUp(150);
            return;
        }

        var rawText = $opt.text().trim();
        var title   = rawText.replace(/^[🔴🟡🔵✅\s]+/, '').split(' — ')[0].trim();
        var code    = $opt.data('code') || '';
        var status  = $opt.data('status') || 'available';
        var hours   = $opt.data('hours') || 0;
        var oil     = $opt.data('oil') || 'No especificado';
        var interval = $opt.data('interval') || ($opt.data('cycle') ? ($opt.data('cycle') + ' días') : 'Bajo demanda');
        var photo   = $opt.data('photo') || '';

        $('#maint-card-title').text(title);
        if (code) {
            $('#maint-card-code-badge').text('Cód: ' + code).show();
        } else {
            $('#maint-card-code-badge').text('Activo').show();
        }

        var statusBadge = {
            'available': '<span style="background:#def7ec;color:#03543f;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">✅ Disponible</span>',
            'in_use': '<span style="background:#e1effe;color:#1e429f;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">🔵 En Uso / Prestado</span>',
            'maintenance': '<span style="background:#fef08a;color:#854d0e;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">🟡 En Mantenimiento</span>',
            'repair': '<span style="background:#fde8e8;color:#9b1c1c;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">🔴 En Reparación</span>',
            'retired': '<span style="background:#f3f4f6;color:#374151;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">⚪ De Baja</span>',
        }[status] || ('<span>' + status + '</span>');

        $('#maint-card-status-badge').html(statusBadge);
        $('#maint-card-hours').text(parseFloat(hours).toLocaleString('es', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' h');
        $('#maint-card-oil').text(oil);
        $('#maint-card-interval').text(interval);

        if (photo) {
            $('#maint-equip-photo-img').attr('src', photo).show();
            $('#maint-equip-photo-placeholder').hide();
        } else {
            $('#maint-equip-photo-img').hide();
            $('#maint-equip-photo-placeholder').show();
        }

        // Precargar horas actuales si el campo está vacío o en 0
        var $hoursInput = $('#maint_hours');
        if ($hoursInput.length && (!$hoursInput.val() || parseFloat($hoursInput.val()) === 0)) {
            if (parseFloat(hours) > 0) {
                $hoursInput.val(hours);
            }
        }

        $card.slideDown(200);
    }

    $(document).on('change', '#maint_equipment_id', function () {
        updateEquipmentCard($(this).val());
    });

    if ($('#maint_equipment_id').val()) {
        updateEquipmentCard($('#maint_equipment_id').val());
    }

    // Botón rápido de adelantar mantenimiento
    $(document).on('click', '#aura-fast-forward-maint-btn', function (e) {
        e.preventDefault();
        $('#maint_type').val('preventive');
        var today = new Date().toISOString().substring(0, 10);
        $('#maint_date').val(today);
        var currentDesc = $('#maint_description').val().trim();
        var note = '⚡ Mantenimiento preventivo adelantado por retorno de préstamo / inspección de piezas.';
        if (!currentDesc) {
            $('#maint_description').val(note);
        } else if (currentDesc.indexOf(note) === -1) {
            $('#maint_description').val(note + '\n\n' + currentDesc);
        }
        
        var $box = $('#maint_type, #maint_date, #maint_description');
        $box.css({ transition: 'background-color 0.4s', backgroundColor: '#e8f5e9' });
        setTimeout(function () {
            $box.css('backgroundColor', '');
        }, 1500);
    });
});
</script>
