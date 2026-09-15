<?php
/**
 * Template: Dashboard Principal de Aura Business Suite
 *
 * @package AuraBusinessSuite
 * @version 2.0.0 - Diseño Moderno con KPIs, Timeline y Módulos Escalables
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// ── Datos del usuario ────────────────────────────────────────────────────────
global $wpdb;

$current_user = wp_get_current_user();
$user_first   = !empty($current_user->first_name) ? $current_user->first_name : $current_user->display_name;
$user_role    = !empty($current_user->roles) ? $current_user->roles[0] : 'subscriber';

// Etiqueta legible del rol en español
$role_labels = [
    'administrator'      => __('Administrador', 'aura-suite'),
    'editor'             => __('Editor', 'aura-suite'),
    'author'             => __('Autor', 'aura-suite'),
    'contributor'        => __('Colaborador', 'aura-suite'),
    'subscriber'         => __('Suscriptor', 'aura-suite'),
    // Roles personalizados de Aura
    'aura_director'      => __('Director', 'aura-suite'),
    'aura_tesorero'      => __('Tesorero', 'aura-suite'),
    'aura_contador'      => __('Contador', 'aura-suite'),
    'aura_auditor'       => __('Auditor', 'aura-suite'),
    'aura_coordinador'   => __('Coordinador de Área', 'aura-suite'),
    'aura_responsable'   => __('Responsable de Área', 'aura-suite'),
    'aura_colaborador'   => __('Colaborador', 'aura-suite'),
    'aura_operador'      => __('Operador', 'aura-suite'),
    'aura_viewer'        => __('Observador', 'aura-suite'),
];
$user_role_label = isset($role_labels[$user_role])
    ? $role_labels[$user_role]
    : ucwords(str_replace(['aura_', '_'], ['', ' '], $user_role));

// Avatar del usuario (usa Simple Local Avatars si está activo, o Gravatar)
$user_avatar_url = get_avatar_url($current_user->ID, ['size' => 96, 'default' => 'mm']);

// Saludo según hora
$hour = (int) current_time('H');
if ($hour < 12) {
    $greeting = '🌅 ' . __('Buenos días', 'aura-suite');
} elseif ($hour < 18) {
    $greeting = '☀️ ' . __('Buenas tardes', 'aura-suite');
} else {
    $greeting = '🌙 ' . __('Buenas noches', 'aura-suite');
}

// ── Identidad de la organización ─────────────────────────────────────────────
$org_name     = get_option('aura_org_name', get_bloginfo('name'));
$org_logo_url = get_option('aura_org_logo_url', '');
$org_tagline  = get_option('aura_org_tagline', '');

// ── Módulos accesibles ────────────────────────────────────────────────────────
// Módulos lanzados (desplegados y en producción)
$deployed_modules = ['finance', 'inventory', 'students', 'certificates', 'forms', 'vehicles', 'library'];
// Total del roadmap completo del plugin (7 activos + 1 planificado)
$total_planned    = 8;
// Cuántos módulos desplegados puede ver este usuario
$active_count     = 0;
foreach ($deployed_modules as $mk) {
    if (Aura_Roles_Manager::user_can_view_module($mk)) {
        $active_count++;
    }
}

// ── Finanzas: estadísticas ────────────────────────────────────────────────────
$fin_pending      = 0;
$fin_income       = 0;
$fin_expense      = 0;
$fin_balance      = 0;
$fin_budget_exec  = 0;
$fin_total_budget = 0;
$fin_executed     = 0;
$fin_can_view_all = false;
$fin_can_view_own = false;

if (Aura_Roles_Manager::user_can_view_module('finance')) {
    global $wpdb;
    $t_fin = $wpdb->prefix . 'aura_finance_transactions';

    $fin_can_view_all = current_user_can('aura_finance_view_all') || current_user_can('manage_options');
    $fin_can_view_own = current_user_can('aura_finance_view_own');

    $month_start = date('Y-m-01');
    $month_end   = date('Y-m-t');

    // Ingresos y egresos del mes — según nivel de acceso
    if ($fin_can_view_all) {
        $monthly = $wpdb->get_results($wpdb->prepare(
            "SELECT transaction_type, SUM(amount) AS total
               FROM {$t_fin}
              WHERE status = 'approved'
                AND deleted_at IS NULL
                AND transaction_date BETWEEN %s AND %s
              GROUP BY transaction_type",
            $month_start, $month_end
        ));
    } elseif ($fin_can_view_own) {
        $monthly = $wpdb->get_results($wpdb->prepare(
            "SELECT transaction_type, SUM(amount) AS total
               FROM {$t_fin}
              WHERE status = 'approved'
                AND deleted_at IS NULL
                AND created_by = %d
                AND transaction_date BETWEEN %s AND %s
              GROUP BY transaction_type",
            get_current_user_id(), $month_start, $month_end
        ));
    } else {
        $monthly = [];
    }

    foreach ($monthly as $row) {
        if ($row->transaction_type === 'income') {
            $fin_income = (float) $row->total;
        } else {
            $fin_expense = (float) $row->total;
        }
    }
    $fin_balance = $fin_income - $fin_expense;

    // Pendientes de aprobación — solo para quienes pueden aprobar
    if (current_user_can('aura_finance_approve')) {
        $fin_pending = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_fin}
              WHERE status = 'pending' AND deleted_at IS NULL"
        );
    }

    // Presupuesto anual global
    $fin_total_budget = (float) get_option('aura_annual_budget', 0);
    $fin_executed     = $fin_expense;
    if ($fin_total_budget > 0) {
        $fin_budget_exec = min(100, round(($fin_executed / $fin_total_budget) * 100, 1));
    }
}

// ── Inventario: estadísticas ──────────────────────────────────────────────────
$inv_total        = 0;
$inv_available    = 0;
$inv_maint_alert  = 0;
$inv_loans_active = 0;
$inv_can_view_all = false;

if (Aura_Roles_Manager::user_can_view_module('inventory')) {
    $inv_can_view_all = current_user_can('aura_inventory_view_all') || current_user_can('manage_options');

    if ($inv_can_view_all) {
        $t_eq    = $wpdb->prefix . 'aura_inventory_equipment';
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';

        $inv_total     = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_eq} WHERE deleted_at IS NULL"
        );
        $inv_available = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_eq} WHERE deleted_at IS NULL AND status = 'available'"
        );

        // Mantenimientos próximos o vencidos (según días de alerta configurados)
        if (current_user_can('aura_inventory_maintenance_view') || $inv_can_view_all) {
            $inv_settings   = class_exists('Aura_Inventory_Categories') ? Aura_Inventory_Categories::get_settings() : [];
            $alert_days     = intval($inv_settings['alert_days_before'] ?? 7);
            $alert_horizon  = date('Y-m-d', strtotime("+{$alert_days} days"));

            $inv_maint_alert = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_eq}
                  WHERE deleted_at IS NULL
                    AND requires_maintenance = 1
                    AND next_maintenance_date IS NOT NULL
                    AND next_maintenance_date <= %s",
                $alert_horizon
            ));
        }

        // Préstamos activos (sin fecha de devolución real)
        if (current_user_can('aura_inventory_checkout') || current_user_can('aura_inventory_checkin') || $inv_can_view_all) {
            $inv_loans_active = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t_loans}
                  WHERE actual_return_date IS NULL"
            );
        }
    }
}

// ── Certificados: estadísticas ───────────────────────────────────────────────
$cert_total_issued   = 0;
$cert_issued_month   = 0;
$cert_total_templates = 0;
$cert_pending_bulk   = 0;
$cert_can_view_all   = false;

if ( Aura_Roles_Manager::user_can_view_module( 'certificates' ) ) {
    $cert_can_view_all = current_user_can( 'aura_certificates_view_all' ) || current_user_can( 'manage_options' );

    if ( $cert_can_view_all ) {
        $t_certs     = $wpdb->prefix . 'aura_certificates';
        $t_cert_tpls = $wpdb->prefix . 'aura_certificate_templates';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_certs}'" ) === $t_certs ) {
            $cert_total_issued = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t_certs} WHERE status = 'issued' AND deleted_at IS NULL"
            );
            $cert_issued_month = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_certs}
                  WHERE status = 'issued' AND deleted_at IS NULL
                    AND issued_at >= %s",
                date( 'Y-m-01' )
            ) );
            $cert_pending_bulk = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t_certs} WHERE status = 'pending'"
            );
        }
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_cert_tpls}'" ) === $t_cert_tpls ) {
            $cert_total_templates = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t_cert_tpls} WHERE status = 'active'"
            );
        }
    }
}

// ── Vehículos: estadísticas ──────────────────────────────────────────────────
$veh_total         = 0;
$veh_available     = 0;
$veh_trips_month   = 0;
$veh_maint_alert   = 0;
$veh_can_view_all  = false;

if ( Aura_Roles_Manager::user_can_view_module( 'vehicles' ) ) {
    $veh_can_view_all = current_user_can( 'aura_vehicles_view_all' ) || current_user_can( 'manage_options' );

    if ( $veh_can_view_all ) {
        $t_veh   = $wpdb->prefix . 'aura_vehicles';
        $t_trips = $wpdb->prefix . 'aura_vehicle_trips';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_veh}'" ) === $t_veh ) {
            $veh_total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_veh} WHERE deleted_at IS NULL" );
            $veh_available = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_veh} WHERE deleted_at IS NULL AND status = 'available'" );

            // Mantenimientos por km próximos o vencidos
            $veh_maint_alert = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t_veh}
                  WHERE deleted_at IS NULL
                    AND next_maintenance_km IS NOT NULL
                    AND current_km >= (next_maintenance_km - 500)"
            );
        }
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_trips}'" ) === $t_trips ) {
            $veh_trips_month = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_trips} WHERE departure_date >= %s",
                gmdate( 'Y-m-01' )
            ) );
        }
    }
}

// ── Notificaciones globales ─────────────────────────────────────────────────── ───────────────────────────────────────────────────
$total_notifications = 0;
$notif_table = $wpdb->prefix . 'aura_notifications';
if ($wpdb->get_var("SHOW TABLES LIKE '{$notif_table}'") === $notif_table) {
    $total_notifications = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$notif_table}
        WHERE user_id = %d AND is_read = 0
    ", get_current_user_id()));
}

// ── Biblioteca: estadísticas ─────────────────────────────────────────────────
$lib_total_books   = 0;
$lib_active_loans  = 0;
$lib_overdue       = 0;
$lib_reservations  = 0;
$lib_can_view      = false;

if ( Aura_Roles_Manager::user_can_view_module( 'library' ) ) {
    $lib_can_view = current_user_can( 'aura_library_view_all' ) || current_user_can( 'manage_options' );
    if ( $lib_can_view ) {
        $t_books = $wpdb->prefix . 'aura_library_books';
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_reserv = $wpdb->prefix . 'aura_library_reservations';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_books}'" ) === $t_books ) {
            $lib_total_books = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_books} WHERE deleted_at IS NULL" );
        }
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_loans}'" ) === $t_loans ) {
            $lib_active_loans = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t_loans} WHERE status = 'active'"
            );
            $lib_overdue = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t_loans} WHERE status = 'active' AND due_date < %s",
                    gmdate( 'Y-m-d' )
                )
            );
        }
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_reserv}'" ) === $t_reserv ) {
            $lib_reservations = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t_reserv} WHERE status = 'pending'"
            );
        }
    }
}

// ── Acceso rápido: nonce ──────────────────────────────────────────────────────
wp_nonce_field('aura_dashboard_nonce', 'aura_dashboard_nonce_field');
?>

<div class="aura-app-wrapper">
<div class="wrap aura-app-context adp-wrap">

    <div class="aura-layout">

    <!-- ════════════════════════════════════════════════════════════════
         HEADER
    ═══════════════════════════════════════════════════════════════════ -->
    <header class="wow-vip-card aura-dashboard-vip-header fade-up">
        <!-- Fila Superior: Identidad VIP + Conexión en vivo y Saludo de Usuario -->
        <div class="aura-vip-header__top-row flex items-center justify-between flex-wrap gap-4 mb-4">
            <!-- Lado izquierdo: Identidad de la organización -->
            <div class="flex items-center gap-3">
                <?php if ($org_logo_url): ?>
                <div class="avatar avatar-lg avatar-zoomable" data-preview-title="<?php echo esc_attr($org_name); ?>" style="background:linear-gradient(135deg,#eab308,#ca8a04);color:#1e1b4b;box-shadow:0 8px 24px rgba(234,179,8,0.35);padding:3px;cursor:zoom-in;">
                    <img src="<?php echo esc_url($org_logo_url); ?>" alt="<?php echo esc_attr($org_name); ?>" style="width:100%;height:100%;object-fit:contain;border-radius:50%;background:#0f172a;padding:4px;">
                </div>
                <?php else: ?>
                <div class="avatar avatar-lg" style="background:linear-gradient(135deg,#eab308,#ca8a04);color:#1e1b4b;box-shadow:0 8px 24px rgba(234,179,8,0.35);font-size:24px;">👑</div>
                <?php endif; ?>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 style="font-size:1.4rem;font-weight:800;color:var(--tx-primary);margin:0;line-height:1.2;">
                            <?php echo esc_html($org_name ?: get_bloginfo('name')); ?>
                        </h2>
                        <span class="badge aura-vip-badge aura-vip-badge--gold"><?php _e('Nivel 1 Empresarial', 'aura-suite'); ?></span>
                    </div>
                    <div class="text-sm text-muted" style="margin-top:2px;">
                        <?php echo esc_html($org_tagline ?: __('Aquí está el resumen de tu sistema AURA Business Suite', 'aura-suite')); ?>
                    </div>
                </div>
            </div>

            <!-- Lado derecho: Estado de conexión y Saludo de usuario -->
            <div class="flex items-center gap-3 flex-wrap">
                <span class="live-chip aura-vip-live-chip">
                    <span class="pulse-dot" style="background-color:var(--aura-emerald);"></span> <?php _e('Sistema en línea', 'aura-suite'); ?>
                </span>
                <div class="aura-vip-user-card">
                    <div class="avatar avatar-md avatar-ring avatar-ring-indigo avatar-zoomable" data-preview-title="<?php echo esc_attr($user_first . ' (' . $user_role_label . ')'); ?>" style="cursor:zoom-in;">
                        <img src="<?php echo esc_url($user_avatar_url); ?>" alt="<?php echo esc_attr($user_first); ?>">
                    </div>
                    <div>
                        <div class="aura-vip-user-card__greeting">
                            <?php echo esc_html($greeting) . ', ' . esc_html($user_first) . '!'; ?>
                        </div>
                        <span class="badge badge-indigo aura-vip-user-card__role">
                            <span class="dashicons dashicons-id-alt" style="font-size:11px;width:11px;height:11px;"></span>
                            <?php echo esc_html($user_role_label); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bloque Intermedio: Grid de Métricas Héroe WOW VIP -->
        <div class="aura-vip-stat-grid">
            <!-- Módulos activos -->
            <div class="aura-vip-stat-item" data-tooltip="<?php esc_attr_e('Módulos desplegados y accesibles para tu rol en el sistema AURA.', 'aura-suite'); ?>">
                <div class="text-xs text-muted font-bold" style="text-transform:uppercase;letter-spacing:0.8px;display:flex;align-items:center;gap:6px;">
                    <span>🧩</span> <?php _e('Módulos Activos', 'aura-suite'); ?>
                </div>
                <div class="flex items-baseline gap-1" style="margin-top:4px;">
                    <span style="font-size:2rem;font-weight:900;color:var(--tx-primary);line-height:1.2;"><?php echo $active_count; ?></span>
                    <span class="text-muted" style="font-size:1.1rem;font-weight:700;">/ <?php echo count($deployed_modules); ?></span>
                </div>
                <div class="text-xs text-muted" style="margin-top:4px;">
                    <?php printf(__('%d en planificación', 'aura-suite'), $total_planned - count($deployed_modules)); ?>
                </div>
            </div>

            <!-- Notificaciones -->
            <div class="aura-vip-stat-item" data-tooltip="<?php esc_attr_e('Notificaciones y avisos pendientes de lectura para tu usuario.', 'aura-suite'); ?>">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-muted font-bold" style="text-transform:uppercase;letter-spacing:0.8px;display:flex;align-items:center;gap:6px;">
                        <span>🔔</span> <?php _e('Notificaciones', 'aura-suite'); ?>
                    </div>
                    <?php if ($total_notifications > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-notifications'); ?>" class="btn btn-secondary btn-sm btn-lift" style="padding:2px 10px;font-size:11.5px;">
                        <span><?php _e('Ver →', 'aura-suite'); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
                <div style="font-size:2rem;font-weight:900;color:<?php echo $total_notifications > 0 ? 'var(--aura-amber)' : 'var(--tx-primary)'; ?>;line-height:1.2;margin-top:4px;">
                    <?php echo $total_notifications; ?>
                </div>
                <div class="text-xs text-muted" style="margin-top:4px;">
                    <?php echo $total_notifications > 0 ? __('Pendientes de atención', 'aura-suite') : __('Bandeja al día', 'aura-suite'); ?>
                </div>
            </div>

            <!-- Aprobaciones pendientes -->
            <?php if (current_user_can('aura_finance_approve')): ?>
            <div class="aura-vip-stat-item" data-tooltip="<?php esc_attr_e('Transacciones financieras pendientes que requieren tu revisión o aprobación.', 'aura-suite'); ?>">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-muted font-bold" style="text-transform:uppercase;letter-spacing:0.8px;display:flex;align-items:center;gap:6px;">
                        <span>📋</span> <?php _e('Aprobaciones', 'aura-suite'); ?>
                    </div>
                    <?php if ($fin_pending > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-pending'); ?>" class="btn btn-secondary btn-sm btn-lift" style="padding:2px 10px;font-size:11.5px;">
                        <span><?php _e('Aprobar →', 'aura-suite'); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
                <div style="font-size:2rem;font-weight:900;color:<?php echo $fin_pending > 0 ? 'var(--aura-amber)' : 'var(--aura-emerald)'; ?>;line-height:1.2;margin-top:4px;">
                    <?php echo $fin_pending; ?>
                </div>
                <div class="text-xs text-muted" style="margin-top:4px;">
                    <?php echo $fin_pending > 0 ? __('Requieren tu visto bueno', 'aura-suite') : __('Todo aprobado', 'aura-suite'); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Balance del mes -->
            <?php if (Aura_Roles_Manager::user_can_view_module('finance')): ?>
            <div class="aura-vip-stat-item" data-tooltip="<?php esc_attr_e('Diferencia neta entre ingresos y egresos aprobados en el mes en curso.', 'aura-suite'); ?>">
                <div class="text-xs text-muted font-bold" style="text-transform:uppercase;letter-spacing:0.8px;display:flex;align-items:center;gap:6px;">
                    <span><?php echo $fin_balance >= 0 ? '📈' : '📉'; ?></span> <?php _e('Balance del Mes', 'aura-suite'); ?>
                </div>
                <div class="<?php echo $fin_balance >= 0 ? 'text-gradient-gold' : ''; ?>" style="font-size:2rem;font-weight:900;line-height:1.2;margin-top:4px;color:<?php echo $fin_balance < 0 ? 'var(--aura-rose)' : 'inherit'; ?>;">
                    <?php echo ($fin_balance >= 0 ? '+' : '') . '$' . number_format($fin_balance, 0, ',', '.'); ?>
                </div>
                <div class="text-xs" style="margin-top:4px;color:<?php echo $fin_balance >= 0 ? 'var(--aura-emerald)' : 'var(--aura-rose)'; ?>;font-weight:600;">
                    <?php echo $fin_balance >= 0 ? '▲ ' . __('Superávit operativo activo', 'aura-suite') : '▼ ' . __('Déficit operativo temporal', 'aura-suite'); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Fila Inferior: Ecosistema y Fecha del Sistema -->
        <div class="flex items-center justify-between flex-wrap gap-3" style="border-top:1px solid var(--border);padding-top:14px;">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="badge aura-vip-badge aura-vip-badge--indigo"><?php _e('Núcleo AURA 2.0', 'aura-suite'); ?></span>
                <span class="badge aura-vip-badge aura-vip-badge--cyan"><?php _e('Sincronización Nube Activa', 'aura-suite'); ?></span>
                <span class="badge aura-vip-badge aura-vip-badge--rose"><?php _e('Seguridad AAA', 'aura-suite'); ?></span>
            </div>
            <div class="text-xs text-muted flex items-center gap-2">
                <span class="dashicons dashicons-calendar-alt" style="font-size:15px;width:15px;height:15px;"></span>
                <span><?php echo date_i18n('l, j \d\e F \d\e Y'); ?></span>
            </div>
        </div>
    </header>

    <main class="aura-content">


    <!-- ════════════════════════════════════════════════════════════════
         GRID DE MÓDULOS
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="aura-glass-card adp-section">
        <h2 class="aura-section-title adp-section__title">
            <span class="adp-section__icon">🌟</span>
            <?php _e('Tus Módulos', 'aura-suite'); ?>
        </h2>

        <div class="adp-modules-grid">

            <!-- ── FINANZAS ── (1 - Activo) -->
            <?php if (Aura_Roles_Manager::user_can_view_module('finance')): ?>
            <div class="aura-glass-card adp-module-card adp-module-card--finance card-lift mini-dash-card accent-emerald">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">💰</span>
                        <span class="badge badge-emerald adp-badge"><span class="pulse-dot dot-emerald"></span> <?php _e('Activo', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Finanzas', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Control de ingresos, egresos, presupuestos y aprobaciones', 'aura-suite'); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ($fin_can_view_all || $fin_can_view_own): ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-text--success">$<?php echo number_format($fin_income, 0, ',', '.'); ?></span>
                        <span class="adp-mini-stat__label">
                            <?php echo $fin_can_view_own && !$fin_can_view_all ? __('Mis ingresos', 'aura-suite') : __('Ingresos mes', 'aura-suite'); ?>
                        </span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-text--danger">$<?php echo number_format($fin_expense, 0, ',', '.'); ?></span>
                        <span class="adp-mini-stat__label">
                            <?php echo $fin_can_view_own && !$fin_can_view_all ? __('Mis egresos', 'aura-suite') : __('Egresos mes', 'aura-suite'); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (current_user_can('aura_finance_approve')): ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-text--warning"><?php echo $fin_pending; ?></span>
                        <span class="adp-mini-stat__label"><?php _e('Pendientes', 'aura-suite'); ?></span>
                    </div>
                    <?php elseif (!$fin_can_view_all && !$fin_can_view_own): ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-mini-stat__value--icon">💼</span>
                        <span class="adp-mini-stat__label"><?php _e('Módulo activo', 'aura-suite'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($fin_total_budget > 0): ?>
                <div class="adp-progress">
                    <div class="adp-progress__label" style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="font-size:12px; font-weight:600; color:var(--tx-muted,#64748b);"><?php _e('Ejecución Presupuestaria', 'aura-suite'); ?></span>
                        <strong style="font-size:12px; font-weight:800; color:var(--tx-primary,#0f172a);"><?php echo $fin_budget_exec; ?>%</strong>
                    </div>
                    <div class="micro-progress-bar" style="height: 7px; border-radius: 999px; background: rgba(148,163,184,0.2); overflow:hidden;">
                        <div class="micro-progress-fill" style="width:<?php echo $fin_budget_exec; ?>%; height:100%; border-radius:999px; background:<?php echo $fin_budget_exec > 90 ? '#ef4444' : ($fin_budget_exec > 70 ? '#f59e0b' : '#10b981'); ?>; transition: width 0.6s cubic-bezier(0.4,0,0.2,1);"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-dashboard'); ?>" class="btn btn-emerald btn-shimmer adp-btn">
                        <span class="dashicons dashicons-chart-area"></span>
                        <span><?php _e('Dashboard', 'aura-suite'); ?></span>
                    </a>
                    <?php if (current_user_can('aura_finance_create')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions&action=new'); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span><?php _e('Nueva Transacción', 'aura-suite'); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($fin_pending > 0 && current_user_can('aura_finance_approve')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-pending'); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span><?php printf(__('Aprobar (%d)', 'aura-suite'), $fin_pending); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── INVENTARIO ── (Activo) -->
            <?php if (Aura_Roles_Manager::user_can_view_module('inventory')): ?>
            <div class="aura-glass-card adp-module-card adp-module-card--inventory card-lift mini-dash-card accent-violet">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">📦</span>
                        <span class="badge badge-emerald adp-badge"><span class="pulse-dot dot-emerald"></span> <?php _e('Activo', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Inventario', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Gestión de herramientas, equipos, mantenimientos periódicos y préstamos', 'aura-suite'); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ($inv_can_view_all): ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $inv_available; ?> <small class="adp-mini-stat__subvalue">/ <?php echo $inv_total; ?></small></span>
                        <span class="adp-mini-stat__label"><?php _e('Disponibles', 'aura-suite'); ?></span>
                    </div>
                    <?php if (current_user_can('aura_inventory_maintenance_view') || $inv_can_view_all): ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value <?php echo $inv_maint_alert > 0 ? 'adp-text--warning' : ''; ?>"><?php echo $inv_maint_alert; ?></span>
                        <span class="adp-mini-stat__label"><?php _e('Mant. próximo', 'aura-suite'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (current_user_can('aura_inventory_checkout') || current_user_can('aura_inventory_checkin') || $inv_can_view_all): ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value <?php echo $inv_loans_active > 0 ? 'adp-text--success' : ''; ?>"><?php echo $inv_loans_active; ?></span>
                        <span class="adp-mini-stat__label"><?php _e('Préstamos activos', 'aura-suite'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-mini-stat__value--icon">🔧</span>
                        <span class="adp-mini-stat__label"><?php _e('Módulo activo', 'aura-suite'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory'); ?>" class="btn btn-violet btn-shimmer adp-btn">
                        <span class="dashicons dashicons-dashboard"></span>
                        <span><?php _e('Ver inventario', 'aura-suite'); ?></span>
                    </a>
                    <?php if (current_user_can('aura_inventory_create')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory-new'); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span><?php _e('Nuevo equipo', 'aura-suite'); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if (current_user_can('aura_inventory_maintenance_create')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory-new-maintenance'); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <span><?php _e('Nuevo mantenimiento', 'aura-suite'); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($inv_maint_alert > 0 && ($inv_can_view_all || current_user_can('aura_inventory_maintenance_view'))): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory-maintenance'); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-warning"></span>
                        <span><?php printf(__('%d con alerta', 'aura-suite'), $inv_maint_alert); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── ESTUDIANTES (Activo) ── -->
            <?php
            $st_total_active  = 0;
            $st_enrollments   = 0;
            $st_pending       = 0;
            $st_overdue       = 0;
            $st_can_view_all  = false;

            if ( Aura_Roles_Manager::user_can_view_module( 'students' ) ) :
                $st_can_view_all = current_user_can( 'aura_students_view_all' ) || current_user_can( 'manage_options' );

                if ( $st_can_view_all ) {
                    $t_st  = $wpdb->prefix . 'aura_students';
                    $t_en  = $wpdb->prefix . 'aura_student_enrollments';
                    $t_pay = $wpdb->prefix . 'aura_student_payments';

                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_st}'" ) === $t_st ) {
                        $st_total_active = (int) $wpdb->get_var(
                            "SELECT COUNT(*) FROM {$t_st} WHERE status = 'active' AND deleted_at IS NULL"
                        );
                    }
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_en}'" ) === $t_en ) {
                        $st_enrollments = (int) $wpdb->get_var(
                            "SELECT COUNT(*) FROM {$t_en} WHERE status = 'active'"
                        );
                        $st_pending = (int) $wpdb->get_var(
                            "SELECT COUNT(*) FROM {$t_en} WHERE status = 'pending'"
                        );
                    }
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_pay}'" ) === $t_pay ) {
                        $st_overdue = (int) $wpdb->get_var(
                            "SELECT COUNT(*) FROM {$t_pay} WHERE status = 'pending' AND due_date < CURDATE()"
                        );
                    }
                }
            ?>
            <div class="aura-glass-card adp-module-card adp-module-card--students card-lift mini-dash-card accent-cyan">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">🎓</span>
                        <span class="badge badge-emerald adp-badge"><span class="pulse-dot dot-emerald"></span> <?php _e( 'Activo', 'aura-suite' ); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e( 'Estudiantes', 'aura-suite' ); ?></h3>
                    <p class="adp-module-card__desc"><?php _e( 'Inscripciones, becas, pagos por cuotas y control académico integrado', 'aura-suite' ); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ( $st_can_view_all ) : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $st_total_active; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Estudiantes activos', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value <?php echo $st_pending > 0 ? 'adp-text--warning' : ''; ?>"><?php echo $st_pending; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Inscr. pendientes', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value <?php echo $st_overdue > 0 ? 'adp-text--danger' : ''; ?>"><?php echo $st_overdue; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Cuotas vencidas', 'aura-suite' ); ?></span>
                    </div>
                    <?php else : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-mini-stat__value--icon">🎓</span>
                        <span class="adp-mini-stat__label"><?php _e( 'Módulo activo', 'aura-suite' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url( 'admin.php?page=aura-students' ); ?>" class="btn btn-violet btn-shimmer adp-btn">
                        <span class="dashicons dashicons-dashboard"></span>
                        <span><?php _e( 'Ver estudiantes', 'aura-suite' ); ?></span>
                    </a>
                    <?php if ( current_user_can( 'aura_students_create' ) || current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-students-new' ); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span><?php _e( 'Nuevo estudiante', 'aura-suite' ); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ( $st_pending > 0 && $st_can_view_all ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-students-enrollments' ); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-clipboard"></span>
                        <span><?php printf( _n( '%d inscr. pendiente', '%d inscr. pendientes', $st_pending, 'aura-suite' ), $st_pending ); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ( $st_overdue > 0 && $st_can_view_all ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-students-payments' ); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-warning"></span>
                        <span><?php printf( _n( '%d cuota vencida', '%d cuotas vencidas', $st_overdue, 'aura-suite' ), $st_overdue ); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── CERTIFICADOS (Activo) ── -->
            <?php if ( Aura_Roles_Manager::user_can_view_module( 'certificates' ) ) : ?>
            <div class="aura-glass-card adp-module-card adp-module-card--certificates card-lift mini-dash-card accent-orange">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">🏅</span>
                        <span class="badge badge-emerald adp-badge"><span class="pulse-dot dot-emerald"></span> <?php _e( 'Activo', 'aura-suite' ); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e( 'Certificados', 'aura-suite' ); ?></h3>
                    <p class="adp-module-card__desc"><?php _e( 'Editor visual de diplomas, emisión con QR, folio único y verificación pública', 'aura-suite' ); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ( $cert_can_view_all ) : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $cert_total_issued; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Total emitidos', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-text--success"><?php echo $cert_issued_month; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Emitidos este mes', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $cert_total_templates; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Plantillas activas', 'aura-suite' ); ?></span>
                    </div>
                    <?php else : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-mini-stat__value--icon">🏅</span>
                        <span class="adp-mini-stat__label"><?php _e( 'Módulo activo', 'aura-suite' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url( 'admin.php?page=aura-certificates' ); ?>" class="btn btn-violet btn-shimmer adp-btn">
                        <span class="dashicons dashicons-dashboard"></span>
                        <span><?php _e( 'Ver certificados', 'aura-suite' ); ?></span>
                    </a>
                    <?php if ( current_user_can( 'aura_certificates_issue' ) || current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-certificates-list' ); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-awards"></span>
                        <span><?php _e( 'Emitir certificado', 'aura-suite' ); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ( current_user_can( 'aura_certificates_templates_manage' ) || current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-certificates-templates' ); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-art"></span>
                        <span><?php _e( 'Plantillas', 'aura-suite' ); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ( $cert_pending_bulk > 0 && $cert_can_view_all ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-certificates-bulk' ); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-warning"></span>
                        <span><?php printf( _n( '%d emisión pendiente', '%d emisiones pendientes', $cert_pending_bulk, 'aura-suite' ), $cert_pending_bulk ); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── FORMULARIOS (Activo) ── -->
            <?php
            $frm_active   = 0;
            $frm_subs_month = 0;
            $frm_pending  = 0;
            $frm_can_view_all = false;

            if ( Aura_Roles_Manager::user_can_view_module( 'forms' ) ) :
                $frm_can_view_all = current_user_can( 'aura_forms_view_responses_all' ) || current_user_can( 'manage_options' );

                if ( $frm_can_view_all ) {
                    $t_frms = $wpdb->prefix . 'aura_forms';
                    $t_fsub = $wpdb->prefix . 'aura_form_submissions';

                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_frms}'" ) === $t_frms ) {
                        $frm_active = (int) $wpdb->get_var(
                            "SELECT COUNT(*) FROM `{$t_frms}` WHERE is_active = 1 AND deleted_at IS NULL"
                        );
                    }
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_fsub}'" ) === $t_fsub ) {
                        $frm_subs_month = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(*) FROM `{$t_fsub}` WHERE submitted_at >= %s",
                                gmdate( 'Y-m-01 00:00:00' )
                            )
                        );
                    }
                    $t_enr = $wpdb->prefix . 'aura_student_enrollments';
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_enr}'" ) === $t_enr ) {
                        $frm_pending = (int) $wpdb->get_var(
                            "SELECT COUNT(*) FROM `{$t_enr}` WHERE status IN ('pending', 'pending_review')"
                        );
                    }
                }
            ?>
            <div class="aura-glass-card adp-module-card adp-module-card--forms card-lift mini-dash-card accent-amber">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">📝</span>
                        <span class="badge badge-emerald adp-badge"><span class="pulse-dot dot-emerald"></span> <?php _e( 'Activo', 'aura-suite' ); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e( 'Formularios', 'aura-suite' ); ?></h3>
                    <p class="adp-module-card__desc"><?php _e( 'Encuestas, solicitudes e inscripciones con recopilación de datos dinámica', 'aura-suite' ); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ( $frm_can_view_all ) : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $frm_active; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Forms activos', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-text--success"><?php echo $frm_subs_month; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Envíos este mes', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value <?php echo $frm_pending > 0 ? 'adp-text--warning' : ''; ?>"><?php echo $frm_pending; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Inscr. pendientes', 'aura-suite' ); ?></span>
                    </div>
                    <?php else : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-mini-stat__value--icon">📝</span>
                        <span class="adp-mini-stat__label"><?php _e( 'Módulo activo', 'aura-suite' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url( 'admin.php?page=aura-forms-dashboard' ); ?>" class="btn btn-amber btn-shimmer adp-btn">
                        <span class="dashicons dashicons-dashboard"></span>
                        <span><?php _e( 'Ver formularios', 'aura-suite' ); ?></span>
                    </a>
                    <?php if ( current_user_can( 'aura_forms_create' ) || current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-forms-new' ); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span><?php _e( 'Nuevo formulario', 'aura-suite' ); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ( $frm_pending > 0 && $frm_can_view_all ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-forms-enrollments' ); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-clipboard"></span>
                        <span><?php printf( _n( '%d inscr. pendiente', '%d inscr. pendientes', $frm_pending, 'aura-suite' ), $frm_pending ); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── VEHÍCULOS (Activo) ── -->
            <?php
            if ( Aura_Roles_Manager::user_can_view_module( 'vehicles' ) ) :
            ?>
            <div class="aura-glass-card adp-module-card adp-module-card--vehicles card-lift mini-dash-card accent-blue">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">🚗</span>
                        <span class="badge badge-emerald adp-badge"><span class="pulse-dot dot-emerald"></span> <?php _e( 'Activo', 'aura-suite' ); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e( 'Vehículos', 'aura-suite' ); ?></h3>
                    <p class="adp-module-card__desc"><?php _e( 'Control de flota, salidas, odómetro y mantenimientos por kilometraje', 'aura-suite' ); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ( $veh_can_view_all ) : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $veh_available; ?> <small class="adp-mini-stat__subvalue">/ <?php echo $veh_total; ?></small></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Disponibles', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-text--success"><?php echo $veh_trips_month; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Salidas este mes', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value <?php echo $veh_maint_alert > 0 ? 'adp-text--warning' : ''; ?>"><?php echo $veh_maint_alert; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Alertas mant.', 'aura-suite' ); ?></span>
                    </div>
                    <?php else : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-mini-stat__value--icon">🚗</span>
                        <span class="adp-mini-stat__label"><?php _e( 'Módulo activo', 'aura-suite' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url( 'admin.php?page=aura-vehicles' ); ?>" class="btn btn-blue btn-shimmer adp-btn">
                        <span class="dashicons dashicons-dashboard"></span>
                        <span><?php _e( 'Ver flota', 'aura-suite' ); ?></span>
                    </a>
                    <?php if ( current_user_can( 'aura_vehicles_exits_create' ) || current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-vehicles-trips' ); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span><?php _e( 'Nueva salida', 'aura-suite' ); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ( $veh_maint_alert > 0 && $veh_can_view_all ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-vehicles-list' ); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-warning"></span>
                        <span><?php printf( _n( '%d con alerta', '%d con alerta km', $veh_maint_alert, 'aura-suite' ), $veh_maint_alert ); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── BIBLIOTECA (Activo) ── -->
            <?php if ( Aura_Roles_Manager::user_can_view_module( 'library' ) ) : ?>
            <div class="aura-glass-card adp-module-card adp-module-card--library card-lift mini-dash-card accent-rose">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">📚</span>
                        <span class="badge badge-emerald adp-badge"><span class="pulse-dot dot-emerald"></span> <?php _e( 'Activo', 'aura-suite' ); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e( 'Biblioteca', 'aura-suite' ); ?></h3>
                    <p class="adp-module-card__desc"><?php _e( 'Catálogo digital, préstamos, devoluciones, reservas y alertas de vencimiento', 'aura-suite' ); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ( $lib_can_view ) : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $lib_total_books; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Libros', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-text--success"><?php echo $lib_active_loans; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Préstamos activos', 'aura-suite' ); ?></span>
                    </div>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value <?php echo $lib_overdue > 0 ? 'adp-text--danger' : ''; ?>"><?php echo $lib_overdue; ?></span>
                        <span class="adp-mini-stat__label"><?php _e( 'Vencidos', 'aura-suite' ); ?></span>
                    </div>
                    <?php else : ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value adp-mini-stat__value--icon">📚</span>
                        <span class="adp-mini-stat__label"><?php _e( 'Módulo activo', 'aura-suite' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url( 'admin.php?page=aura-library' ); ?>" class="btn btn-rose btn-shimmer adp-btn">
                        <span class="dashicons dashicons-dashboard"></span>
                        <span><?php _e( 'Ver biblioteca', 'aura-suite' ); ?></span>
                    </a>
                    <?php if ( current_user_can( 'aura_library_manage' ) || current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-library-loans' ); ?>" class="btn btn-secondary btn-sm btn-lift adp-btn">
                        <span class="dashicons dashicons-book"></span>
                        <span><?php _e( 'Préstamos', 'aura-suite' ); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ( $lib_overdue > 0 && $lib_can_view ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-library-loans' ); ?>" class="btn btn-rose btn-glow btn-sm adp-btn">
                        <span class="dashicons dashicons-warning"></span>
                        <span><?php printf( _n( '%d préstamo vencido', '%d préstamos vencidos', $lib_overdue, 'aura-suite' ), $lib_overdue ); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── ELECTRICIDAD (En Desarrollo) ── -->
            <div class="aura-glass-card adp-module-card adp-module-card--electricity adp-module-card--coming-soon adp-module-card--compact card-lift mini-dash-card accent-amber">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">⚡</span>
                        <span class="badge badge-amber adp-badge"><span class="pulse-dot dot-amber"></span> <?php _e('En desarrollo', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Electricidad', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Monitoreo de consumo eléctrico por área, alertas de umbral y tendencias', 'aura-suite'); ?></p>
                </div>
                <div class="adp-module-card__eta">📅 Q3 2026</div>
            </div>

        </div><!-- /.adp-modules-grid -->
    </div><!-- /.adp-section (módulos) -->


    <!-- ════════════════════════════════════════════════════════════════
         FILA INFERIOR: Accesos Rápidos + Info Sistema + Roadmap
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="adp-bottom-grid fade-up delay-3">

        <!-- Accesos Rápidos -->
        <div class="adp-panel glass-card">
            <h2 class="adp-panel__title">⚡ <?php _e('Accesos Rápidos', 'aura-suite'); ?></h2>
            <div class="adp-quick-actions">

                <?php if (current_user_can('aura_finance_create')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions&action=new'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">💸</span>
                    <div>
                        <strong><?php _e('Nueva Transacción', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar ingreso o egreso', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_finance_approve') && $fin_pending > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-pending'); ?>" class="adp-quick-btn adp-quick-btn--highlight card-lift">
                    <span class="adp-quick-btn__icon">✅</span>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <strong><?php _e('Aprobar Transacciones', 'aura-suite'); ?></strong>
                            <span class="live-chip live-warning" style="font-size:10px; padding:1px 6px;">
                                <span class="pulse-dot dot-amber"></span> <?php printf(__('%d pendientes', 'aura-suite'), $fin_pending); ?>
                            </span>
                        </div>
                        <span><?php _e('Revisar y autorizar movimientos en cola', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_finance_reports')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-reports'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">📊</span>
                    <div>
                        <strong><?php _e('Generar Reporte', 'aura-suite'); ?></strong>
                        <span><?php _e('Exportar datos financieros', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_vehicles_exits_create')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-vehicles-trips'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">🚗</span>
                    <div>
                        <strong><?php _e('Salida de Vehículo', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar uso de la flota', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_inventory_create')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-inventory-new'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">📦</span>
                    <div>
                        <strong><?php _e('Nuevo Equipo', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar equipo en inventario', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_inventory_maintenance_create')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-inventory-new-maintenance'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">🔧</span>
                    <div>
                        <strong><?php _e('Nuevo Mantenimiento', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar mantenimiento realizado', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_inventory_checkout')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-inventory-loans'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">🤝</span>
                    <div>
                        <strong><?php _e('Préstamo de Equipo', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar salida de un equipo', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if ( current_user_can( 'aura_students_create' ) || current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo admin_url( 'admin.php?page=aura-students-new' ); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">🎓</span>
                    <div>
                        <strong><?php _e( 'Nuevo Estudiante', 'aura-suite' ); ?></strong>
                        <span><?php _e( 'Registrar estudiante o participante', 'aura-suite' ); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if ( current_user_can( 'aura_certificates_issue' ) || current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo admin_url( 'admin.php?page=aura-certificates-list' ); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">🏅</span>
                    <div>
                        <strong><?php _e( 'Emitir Certificado', 'aura-suite' ); ?></strong>
                        <span><?php _e( 'Generar y enviar diploma/certificado', 'aura-suite' ); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_electric_reading_create')): ?>
                <a href="<?php echo admin_url('post-new.php?post_type=aura_electric_reading'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">⚡</span>
                    <div>
                        <strong><?php _e('Lectura Eléctrica', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar consumo eléctrico', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_admin_permissions_assign')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-permissions'); ?>" class="adp-quick-btn card-lift">
                    <span class="adp-quick-btn__icon">🔐</span>
                    <div>
                        <strong><?php _e('Gestionar Permisos', 'aura-suite'); ?></strong>
                        <span><?php _e('Roles y capabilities de usuarios', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

            </div><!-- /.adp-quick-actions -->
        </div><!-- /.adp-panel (accesos) -->

        <!-- Columna derecha: Info Sistema + Roadmap -->
        <div class="adp-right-col">

            <!-- Información del Sistema -->
            <div class="adp-panel glass-card">
                <h2 class="adp-panel__title">ℹ️ <?php _e('Sistema', 'aura-suite'); ?></h2>
                <div class="adp-user-mini">
                    <div class="avatar avatar-ring avatar-ring-indigo avatar-zoomable" data-preview-title="<?php echo esc_attr($current_user->display_name); ?>" style="flex-shrink:0; cursor:zoom-in;">
                        <?php echo get_avatar($current_user->ID, 48, '', '', ['class' => 'adp-avatar']); ?>
                    </div>
                    <div>
                        <strong><?php echo esc_html($current_user->display_name); ?></strong>
                        <span class="badge badge-indigo" style="font-size: 11px; padding: 2px 8px; width: fit-content; margin-top: 2px;">
                            <?php echo esc_html($user_role_label); ?>
                        </span>
                        <a href="<?php echo admin_url('profile.php'); ?>" class="adp-link-small" style="margin-top: 3px;">
                            <?php _e('Editar perfil →', 'aura-suite'); ?>
                        </a>
                    </div>
                </div>
                <table class="adp-info-table">
                    <tr>
                        <td><?php _e('Versión AURA:', 'aura-suite'); ?></td>
                        <td><strong class="text-gradient"><?php echo defined('AURA_VERSION') ? AURA_VERSION : '—'; ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('WordPress:', 'aura-suite'); ?></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('PHP:', 'aura-suite'); ?></td>
                        <td><?php echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Módulos activos:', 'aura-suite'); ?></td>
                        <td><strong><?php echo $active_count; ?> / <?php echo count($deployed_modules); ?></strong> <small class="adp-mini-stat__subvalue">(<?php echo $total_planned - count($deployed_modules); ?> en planificación)</small></td>
                    </tr>
                </table>
                <div class="adp-syslinks">
                    <?php if (current_user_can('aura_admin_settings')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-settings'); ?>" class="btn btn-secondary btn-sm btn-lift">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <span><?php _e('Configuración', 'aura-suite'); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if (current_user_can('aura_admin_permissions_assign')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-permissions'); ?>" class="btn btn-secondary btn-sm btn-lift">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span><?php _e('Permisos', 'aura-suite'); ?></span>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-audit-log'); ?>" class="btn btn-secondary btn-sm btn-lift">
                        <span class="dashicons dashicons-list-view"></span>
                        <span><?php _e('Auditoría', 'aura-suite'); ?></span>
                    </a>
                </div>
            </div><!-- /.adp-panel (sistema) -->

            <!-- Roadmap -->
            <div class="adp-panel glass-card">
                <h2 class="adp-panel__title">🚀 <?php _e('Estado del Roadmap', 'aura-suite'); ?></h2>
                <ul class="adp-roadmap">
                    <li data-tooltip="<?php esc_attr_e('Gestión integral de ingresos, egresos, caja chica, reembolsos y balances.', 'aura-suite'); ?>">
                        <span class="badge badge-emerald adp-roadmap__badge"><span class="pulse-dot dot-emerald"></span> Listo</span>
                        <span>💰 <?php _e('Módulo Finanzas', 'aura-suite'); ?></span>
                    </li>
                    <li data-tooltip="<?php esc_attr_e('Control de equipos, trazabilidad, mantenimientos y préstamos.', 'aura-suite'); ?>">
                        <span class="badge badge-emerald adp-roadmap__badge"><span class="pulse-dot dot-emerald"></span> Listo</span>
                        <span>📦 <?php _e('Módulo Inventario', 'aura-suite'); ?></span>
                    </li>
                    <li data-tooltip="<?php esc_attr_e('Gestión académica, inscripciones, becas y pagos de participantes.', 'aura-suite'); ?>">
                        <span class="badge badge-emerald adp-roadmap__badge"><span class="pulse-dot dot-emerald"></span> Listo</span>
                        <span>🎓 <?php _e('Módulo Estudiantes', 'aura-suite'); ?></span>
                    </li>
                    <li data-tooltip="<?php esc_attr_e('Editor visual de diplomas, folios únicos y verificación pública con QR.', 'aura-suite'); ?>">
                        <span class="badge badge-emerald adp-roadmap__badge"><span class="pulse-dot dot-emerald"></span> Listo</span>
                        <span>🏅 <?php _e('Módulo Certificados', 'aura-suite'); ?></span>
                    </li>
                    <li data-tooltip="<?php esc_attr_e('Formularios dinámicos, encuestas y recopilación de datos.', 'aura-suite'); ?>">
                        <span class="badge badge-emerald adp-roadmap__badge"><span class="pulse-dot dot-emerald"></span> Listo</span>
                        <span>📝 <?php _e('Módulo Formularios', 'aura-suite'); ?></span>
                    </li>
                    <li data-tooltip="<?php esc_attr_e('Control de flota automotriz, bitácora de salidas y alertas por odómetro.', 'aura-suite'); ?>">
                        <span class="badge badge-emerald adp-roadmap__badge"><span class="pulse-dot dot-emerald"></span> Listo</span>
                        <span>🚗 <?php _e('Módulo Vehículos', 'aura-suite'); ?></span>
                    </li>
                    <li data-tooltip="<?php esc_attr_e('Catálogo bibliográfico, préstamos físicos, reservas y alertas de entrega.', 'aura-suite'); ?>">
                        <span class="badge badge-emerald adp-roadmap__badge"><span class="pulse-dot dot-emerald"></span> Listo</span>
                        <span>📚 <?php _e('Módulo Biblioteca', 'aura-suite'); ?></span>
                    </li>
                    <li data-tooltip="<?php esc_attr_e('Monitoreo de consumo eléctrico, lecturas y cálculo de costos energéticos.', 'aura-suite'); ?>">
                        <span class="badge badge-amber adp-roadmap__badge"><span class="pulse-dot dot-amber"></span> En desarrollo</span>
                        <span>⚡ <?php _e('Módulo Electricidad', 'aura-suite'); ?> <small style="color:var(--adp-muted);">(Q3 2026)</small></span>
                    </li>
                </ul>
            </div><!-- /.adp-panel (roadmap) -->

        </div><!-- /.adp-right-col -->

    </div><!-- /.adp-bottom-grid -->


    <!-- ════════════════════════════════════════════════════════════════
         FOOTER
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="adp-footer">
        <p>
            <?php
            printf(
                __('Desarrollado con ❤️ por %s &nbsp;|&nbsp; © %s AURA Business Suite', 'aura-suite'),
                '<strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong>',
                date('Y')
            );
            ?>
        </p>
    </div>

    </main>

    </div><!-- /.aura-layout -->
</div><!-- /.wrap.aura-app-context -->
</div><!-- /.aura-app-wrapper -->


