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
$deployed_modules = ['finance', 'inventory'];
// Total del roadmap completo del plugin
$total_planned    = 7;
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

// ── Notificaciones globales ───────────────────────────────────────────────────
$total_notifications = 0;
$notif_table = $wpdb->prefix . 'aura_notifications';
if ($wpdb->get_var("SHOW TABLES LIKE '{$notif_table}'") === $notif_table) {
    $total_notifications = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$notif_table}
        WHERE user_id = %d AND is_read = 0
    ", get_current_user_id()));
}

// ── Acceso rápido: nonce ──────────────────────────────────────────────────────
wp_nonce_field('aura_dashboard_nonce', 'aura_dashboard_nonce_field');
?>

<div class="wrap adp-wrap">

    <!-- ════════════════════════════════════════════════════════════════
         HEADER
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="adp-header">
        <div class="adp-header__welcome">
            <!-- Identidad de la organización -->
            <?php if ($org_name): ?>
            <div class="adp-org-identity">
                <?php if ($org_logo_url): ?>
                <img src="<?php echo esc_url($org_logo_url); ?>" alt="<?php echo esc_attr($org_name); ?>" class="adp-org-logo">
                <?php endif; ?>
                <div class="adp-org-info">
                    <span class="adp-org-name"><?php echo esc_html($org_name); ?></span>
                    <?php if ($org_tagline): ?>
                    <span class="adp-org-tagline"><?php echo esc_html($org_tagline); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="adp-user-greeting">
                <div class="adp-user-avatar-wrap">
                    <img src="<?php echo esc_url($user_avatar_url); ?>"
                         alt="<?php echo esc_attr($user_first); ?>"
                         class="adp-user-avatar"
                         width="64" height="64">
                </div>
                <div class="adp-user-greeting__info">
                    <h1 class="adp-header__title">
                        <?php echo esc_html($greeting) . ', ' . esc_html($user_first) . '!'; ?>
                    </h1>
                    <span class="adp-user-role-badge">
                        <span class="dashicons dashicons-id-alt" aria-hidden="true"></span>
                        <?php echo esc_html($user_role_label); ?>
                    </span>
                </div>
            </div>
            <p class="adp-header__subtitle">
                <?php _e('Aquí está el resumen de tu sistema AURA Business Suite', 'aura-suite'); ?>
            </p>
            <p class="adp-header__date">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo date_i18n('l, j \d\e F \d\e Y'); ?>
            </p>
        </div>

        <div class="adp-header__stats">
            <!-- Módulos activos -->
            <div class="adp-stat-card">
                <div class="adp-stat-card__icon">🧩</div>
                <div class="adp-stat-card__body">
                    <span class="adp-stat-card__value"><?php echo $active_count; ?></span><small style="font-size:.75em;color:#9ca3af;font-weight:400;">&nbsp;/&nbsp;<?php echo count($deployed_modules); ?></small>
                    <span class="adp-stat-card__label">
                        <?php _e('Módulos Activos', 'aura-suite'); ?>
                        <small style="display:block;font-size:10px;color:#9ca3af;font-weight:400;margin-top:1px;">
                            <?php printf(__('%d en desarrollo', 'aura-suite'), $total_planned - count($deployed_modules)); ?>
                        </small>
                    </span>
                </div>
            </div>

            <!-- Notificaciones -->
            <div class="adp-stat-card <?php echo $total_notifications > 0 ? 'adp-stat-card--alert' : ''; ?>">
                <div class="adp-stat-card__icon">🔔</div>
                <div class="adp-stat-card__body">
                    <span class="adp-stat-card__value"><?php echo $total_notifications; ?></span>
                    <span class="adp-stat-card__label"><?php _e('Notificaciones', 'aura-suite'); ?></span>
                </div>
                <?php if ($total_notifications > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-notifications'); ?>" class="adp-stat-card__link">
                    <?php _e('Ver →', 'aura-suite'); ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Aprobaciones pendientes -->
            <?php if (current_user_can('aura_finance_approve')): ?>
            <div class="adp-stat-card <?php echo $fin_pending > 0 ? 'adp-stat-card--warning' : ''; ?>">
                <div class="adp-stat-card__icon">📋</div>
                <div class="adp-stat-card__body">
                    <span class="adp-stat-card__value"><?php echo $fin_pending; ?></span>
                    <span class="adp-stat-card__label"><?php _e('Aprobaciones Pendientes', 'aura-suite'); ?></span>
                </div>
                <?php if ($fin_pending > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-pending'); ?>" class="adp-stat-card__link">
                    <?php _e('Aprobar →', 'aura-suite'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Balance del mes -->
            <?php if (Aura_Roles_Manager::user_can_view_module('finance')): ?>
            <div class="adp-stat-card <?php echo $fin_balance >= 0 ? 'adp-stat-card--success' : 'adp-stat-card--danger'; ?>">
                <div class="adp-stat-card__icon"><?php echo $fin_balance >= 0 ? '📈' : '📉'; ?></div>
                <div class="adp-stat-card__body">
                    <span class="adp-stat-card__value">
                        <?php echo ($fin_balance >= 0 ? '+' : '') . '$' . number_format($fin_balance, 0, ',', '.'); ?>
                    </span>
                    <span class="adp-stat-card__label"><?php _e('Balance del Mes', 'aura-suite'); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /.adp-header -->


    <!-- ════════════════════════════════════════════════════════════════
         GRID DE MÓDULOS
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="adp-section">
        <h2 class="adp-section__title">
            <span class="adp-section__icon">🌟</span>
            <?php _e('Tus Módulos', 'aura-suite'); ?>
        </h2>

        <div class="adp-modules-grid">

            <!-- ── FINANZAS ── (1 - Activo) -->
            <?php if (Aura_Roles_Manager::user_can_view_module('finance')): ?>
            <div class="adp-module-card adp-module-card--finance">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">💰</span>
                        <span class="adp-badge adp-badge--active"><?php _e('Activo', 'aura-suite'); ?></span>
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
                        <span class="adp-mini-stat__value" style="font-size:1.2em;">💼</span>
                        <span class="adp-mini-stat__label"><?php _e('Módulo activo', 'aura-suite'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($fin_total_budget > 0): ?>
                <div class="adp-progress">
                    <div class="adp-progress__label">
                        <span><?php _e('Ejecución Presupuestaria', 'aura-suite'); ?></span>
                        <strong><?php echo $fin_budget_exec; ?>%</strong>
                    </div>
                    <div class="adp-progress__bar">
                        <div class="adp-progress__fill" style="width:<?php echo $fin_budget_exec; ?>%;background:<?php echo $fin_budget_exec > 90 ? '#ef4444' : ($fin_budget_exec > 70 ? '#f59e0b' : '#10b981'); ?>;"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-dashboard'); ?>" class="adp-btn adp-btn--primary">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php _e('Dashboard', 'aura-suite'); ?>
                    </a>
                    <?php if (current_user_can('aura_finance_create')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions&action=new'); ?>" class="adp-btn adp-btn--secondary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Nueva Transacción', 'aura-suite'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($fin_pending > 0 && current_user_can('aura_finance_approve')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-pending'); ?>" class="adp-btn adp-btn--alert">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php printf(__('Aprobar (%d)', 'aura-suite'), $fin_pending); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── INVENTARIO ── (Activo) -->
            <?php if (Aura_Roles_Manager::user_can_view_module('inventory')): ?>
            <div class="adp-module-card adp-module-card--inventory">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">📦</span>
                        <span class="adp-badge adp-badge--active"><?php _e('Activo', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Inventario', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Gestión de herramientas, equipos, mantenimientos periódicos y préstamos', 'aura-suite'); ?></p>
                </div>

                <div class="adp-mini-stats">
                    <?php if ($inv_can_view_all): ?>
                    <div class="adp-mini-stat">
                        <span class="adp-mini-stat__value"><?php echo $inv_available; ?> <small style="font-size:.65em;color:#6b7280;">/ <?php echo $inv_total; ?></small></span>
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
                        <span class="adp-mini-stat__value" style="font-size:1.2em;">🔧</span>
                        <span class="adp-mini-stat__label"><?php _e('Módulo activo', 'aura-suite'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="adp-module-card__actions">
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory'); ?>" class="adp-btn adp-btn--primary">
                        <span class="dashicons dashicons-dashboard"></span>
                        <?php _e('Ver inventario', 'aura-suite'); ?>
                    </a>
                    <?php if (current_user_can('aura_inventory_create')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory-new'); ?>" class="adp-btn adp-btn--secondary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Nuevo equipo', 'aura-suite'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if (current_user_can('aura_inventory_maintenance_create')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory-new-maintenance'); ?>" class="adp-btn adp-btn--secondary">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Nuevo mantenimiento', 'aura-suite'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($inv_maint_alert > 0 && ($inv_can_view_all || current_user_can('aura_inventory_maintenance_view'))): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-inventory-maintenance'); ?>" class="adp-btn adp-btn--alert">
                        <span class="dashicons dashicons-warning"></span>
                        <?php printf(__('%d con alerta', 'aura-suite'), $inv_maint_alert); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── ESTUDIANTES (Próximamente - Semana 4) ── -->
            <div class="adp-module-card adp-module-card--students adp-module-card--coming-soon adp-module-card--compact">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">🎓</span>
                        <span class="adp-badge adp-badge--soon"><?php _e('Próximamente', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Estudiantes', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Inscripciones, becas, pagos por cuotas y control académico integrado', 'aura-suite'); ?></p>
                </div>
                <div class="adp-module-card__eta">📅 <?php _e('Semana 4', 'aura-suite'); ?> · Q2 2026</div>
            </div>

            <!-- ── VEHÍCULOS (Próximamente - Semana 5) ── -->
            <div class="adp-module-card adp-module-card--vehicles adp-module-card--coming-soon adp-module-card--compact">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">🚗</span>
                        <span class="adp-badge adp-badge--soon"><?php _e('Próximamente', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Vehículos', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Control de flota, salidas, odómetro y mantenimientos por kilometraje', 'aura-suite'); ?></p>
                </div>
                <div class="adp-module-card__eta">📅 <?php _e('Semana 5', 'aura-suite'); ?> · Q2 2026</div>
            </div>

            <!-- ── ELECTRICIDAD (Próximamente - Semana 6) ── -->
            <div class="adp-module-card adp-module-card--electricity adp-module-card--coming-soon adp-module-card--compact">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">⚡</span>
                        <span class="adp-badge adp-badge--soon"><?php _e('Próximamente', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Electricidad', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Monitoreo de consumo eléctrico por área, alertas de umbral y tendencias', 'aura-suite'); ?></p>
                </div>
                <div class="adp-module-card__eta">📅 <?php _e('Semana 6', 'aura-suite'); ?> · Q2 2026</div>
            </div>

            <!-- ── BIBLIOTECA (Próximamente - Semana 6+) ── -->
            <div class="adp-module-card adp-module-card--library adp-module-card--coming-soon adp-module-card--compact">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">📚</span>
                        <span class="adp-badge adp-badge--soon"><?php _e('Próximamente', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Biblioteca', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Catálogo digital, préstamos, devoluciones y alertas de vencimiento', 'aura-suite'); ?></p>
                </div>
                <div class="adp-module-card__eta">📅 <?php _e('Semana 6+', 'aura-suite'); ?> · Q3 2026</div>
            </div>

            <!-- ── FORMULARIOS (Próximamente - Semana 6+) ── -->
            <div class="adp-module-card adp-module-card--forms adp-module-card--coming-soon adp-module-card--compact">
                <div class="adp-module-card__header">
                    <div class="adp-module-card__icon-row">
                        <span class="adp-module-card__icon">📝</span>
                        <span class="adp-badge adp-badge--soon"><?php _e('Próximamente', 'aura-suite'); ?></span>
                    </div>
                    <h3 class="adp-module-card__title"><?php _e('Formularios', 'aura-suite'); ?></h3>
                    <p class="adp-module-card__desc"><?php _e('Encuestas, solicitudes e inscripciones con recopilación de datos dinámica', 'aura-suite'); ?></p>
                </div>
                <div class="adp-module-card__eta">📅 <?php _e('Semana 6+', 'aura-suite'); ?> · Q3 2026</div>
            </div>

        </div><!-- /.adp-modules-grid -->
    </div><!-- /.adp-section (módulos) -->


    <!-- ════════════════════════════════════════════════════════════════
         FILA INFERIOR: Accesos Rápidos + Info Sistema + Roadmap
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="adp-bottom-grid">

        <!-- Accesos Rápidos -->
        <div class="adp-panel">
            <h2 class="adp-panel__title">⚡ <?php _e('Accesos Rápidos', 'aura-suite'); ?></h2>
            <div class="adp-quick-actions">

                <?php if (current_user_can('aura_finance_create')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions&action=new'); ?>" class="adp-quick-btn">
                    <span class="adp-quick-btn__icon">💸</span>
                    <div>
                        <strong><?php _e('Nueva Transacción', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar ingreso o egreso', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_finance_approve') && $fin_pending > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-pending'); ?>" class="adp-quick-btn adp-quick-btn--highlight">
                    <span class="adp-quick-btn__icon">✅</span>
                    <div>
                        <strong><?php _e('Aprobar Transacciones', 'aura-suite'); ?></strong>
                        <span><?php printf(__('%d pendientes de aprobación', 'aura-suite'), $fin_pending); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_finance_reports')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-reports'); ?>" class="adp-quick-btn">
                    <span class="adp-quick-btn__icon">📊</span>
                    <div>
                        <strong><?php _e('Generar Reporte', 'aura-suite'); ?></strong>
                        <span><?php _e('Exportar datos financieros', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_vehicles_exits_create')): ?>
                <a href="<?php echo admin_url('post-new.php?post_type=aura_vehicle_exit'); ?>" class="adp-quick-btn">
                    <span class="adp-quick-btn__icon">🚗</span>
                    <div>
                        <strong><?php _e('Salida de Vehículo', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar uso de la flota', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_inventory_create')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-inventory-new'); ?>" class="adp-quick-btn">
                    <span class="adp-quick-btn__icon">📦</span>
                    <div>
                        <strong><?php _e('Nuevo Equipo', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar equipo en inventario', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_inventory_maintenance_create')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-inventory-new-maintenance'); ?>" class="adp-quick-btn">
                    <span class="adp-quick-btn__icon">🔧</span>
                    <div>
                        <strong><?php _e('Nuevo Mantenimiento', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar mantenimiento realizado', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_inventory_checkout')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-inventory-loans'); ?>" class="adp-quick-btn">
                    <span class="adp-quick-btn__icon">🤝</span>
                    <div>
                        <strong><?php _e('Préstamo de Equipo', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar salida de un equipo', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_electric_reading_create')): ?>
                <a href="<?php echo admin_url('post-new.php?post_type=aura_electric_reading'); ?>" class="adp-quick-btn">
                    <span class="adp-quick-btn__icon">⚡</span>
                    <div>
                        <strong><?php _e('Lectura Eléctrica', 'aura-suite'); ?></strong>
                        <span><?php _e('Registrar consumo eléctrico', 'aura-suite'); ?></span>
                    </div>
                    <span class="adp-quick-btn__arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('aura_admin_permissions_assign')): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-permissions'); ?>" class="adp-quick-btn">
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
            <div class="adp-panel">
                <h2 class="adp-panel__title">ℹ️ <?php _e('Sistema', 'aura-suite'); ?></h2>
                <div class="adp-user-mini">
                    <?php echo get_avatar($current_user->ID, 48, '', '', ['class' => 'adp-avatar']); ?>
                    <div>
                        <strong><?php echo esc_html($current_user->display_name); ?></strong>
                        <span class="adp-user-role"><?php echo esc_html($user_role); ?></span>
                        <a href="<?php echo admin_url('profile.php'); ?>" class="adp-link-small">
                            <?php _e('Editar perfil →', 'aura-suite'); ?>
                        </a>
                    </div>
                </div>
                <table class="adp-info-table">
                    <tr>
                        <td><?php _e('Versión AURA:', 'aura-suite'); ?></td>
                        <td><strong><?php echo defined('AURA_VERSION') ? AURA_VERSION : '—'; ?></strong></td>
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
                        <td><strong><?php echo $active_count; ?> / <?php echo $total_planned; ?></strong></td>
                    </tr>
                </table>
                <div class="adp-syslinks">
                    <?php if (current_user_can('aura_admin_settings')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-settings'); ?>">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Configuración', 'aura-suite'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if (current_user_can('aura_admin_permissions_assign')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-permissions'); ?>">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php _e('Permisos', 'aura-suite'); ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-audit-log'); ?>">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('Auditoría', 'aura-suite'); ?>
                    </a>
                </div>
            </div><!-- /.adp-panel (sistema) -->

            <!-- Roadmap -->
            <div class="adp-panel">
                <h2 class="adp-panel__title">🚀 <?php _e('Próximas Funcionalidades', 'aura-suite'); ?></h2>
                <ul class="adp-roadmap">
                    <li>
                        <span class="adp-roadmap__badge adp-roadmap__badge--q1">Q1 2026</span>
                        📦 <?php _e('Módulo Inventario', 'aura-suite'); ?>
                    </li>
                    <li>
                        <span class="adp-roadmap__badge adp-roadmap__badge--q1">Q1 2026</span>
                        📚 <?php _e('Módulo Biblioteca', 'aura-suite'); ?>
                    </li>
                    <li>
                        <span class="adp-roadmap__badge adp-roadmap__badge--q2">Q2 2026</span>
                        🎓 <?php _e('Módulo Estudiantes', 'aura-suite'); ?>
                    </li>
                    <li>
                        <span class="adp-roadmap__badge adp-roadmap__badge--q2">Q2 2026</span>
                        📝 <?php _e('Formularios Dinámicos', 'aura-suite'); ?>
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
                '<strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong>',
                date('Y')
            );
            ?>
        </p>
    </div>

</div><!-- /.wrap.adp-wrap -->
