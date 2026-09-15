<?php
/**
 * Template: Página de Transacciones Pendientes de Aprobación
 * Muestra listado de transacciones con status=pending y permite aprobar/rechazar
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos (solo usuarios que pueden aprobar deberían ver esta página)
if (!current_user_can('aura_finance_approve')) {
    wp_die(__('No tienes permisos para acceder a esta página.', 'aura-suite'));
}

// Crear instancia de la tabla
$pending_list = new Aura_Financial_Pending_List();
$pending_list->prepare_items();

// Obtener categorías para filtros
global $wpdb;
$categories_table = $wpdb->prefix . 'aura_finance_categories';
$categories = $wpdb->get_results(
    "SELECT id, name, type FROM $categories_table WHERE deleted_at IS NULL ORDER BY name ASC",
    ARRAY_A
);

// Obtener usuarios creadores para filtro
$transactions_table = $wpdb->prefix . 'aura_finance_transactions';
$creators = $wpdb->get_results(
    "SELECT DISTINCT u.ID, u.display_name 
     FROM {$wpdb->users} u
     INNER JOIN $transactions_table t ON u.ID = t.created_by
     WHERE t.status = 'pending' AND t.deleted_at IS NULL
     ORDER BY u.display_name ASC",
    ARRAY_A
);

// Contar pendientes y métricas para KPIs
$current_user_id = get_current_user_id();
$stats_pending = $wpdb->get_row($wpdb->prepare(
    "SELECT 
        COUNT(*) as total_count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN created_by != %d THEN 1 ELSE 0 END) as approvable_count,
        SUM(CASE WHEN created_by = %d THEN 1 ELSE 0 END) as mine_count
     FROM $transactions_table 
     WHERE status = 'pending' AND deleted_at IS NULL",
    $current_user_id,
    $current_user_id
));

$total_pending_count  = (int) ($stats_pending->total_count ?? 0);
$total_pending_amount = (float) ($stats_pending->total_amount ?? 0.0);
$approvable_count     = (int) ($stats_pending->approvable_count ?? 0);
$mine_count           = (int) ($stats_pending->mine_count ?? 0);
$current_view         = isset($_GET['filter_view']) ? sanitize_text_field($_GET['filter_view']) : 'all';
$pagination_total     = $pending_list->get_pagination_arg('total_items');
?>

<div class="aura-app-wrapper">
<div class="wrap aura-app-context aura-transactions-list-page aura-pending-transactions-page">

    <!-- ====================================================
         Cabecera de Impresión Oficial (@media print)
    ===================================================== -->
    <div class="aura-print-header">
        <div class="aura-print-header__brand-box">
            <?php
            $logo_url = get_site_icon_url( 80 );
            if ( $logo_url ) {
                echo '<img src="' . esc_url( $logo_url ) . '" alt="" class="aura-print-logo" loading="eager">';
            }
            ?>
            <div class="aura-print-header__brand-text">
                <h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
                <span class="aura-print-header__tagline"><?php esc_html_e( 'MÓDULO FINANCIERO — GESTIÓN DE APROBACIONES', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-print-header__report-card">
            <h1><?php esc_html_e( 'Transacciones Pendientes de Aprobación', 'aura-suite' ); ?></h1>
            <div class="aura-print-period-pill">
                <span class="dashicons dashicons-clock"></span>
                <span><?php printf( esc_html__( '%d transacciones pendientes de validación', 'aura-suite' ), $total_pending_count ); ?></span>
            </div>
        </div>
        <div class="aura-print-header__meta-box">
            <div class="aura-print-meta-item">
                <span><?php esc_html_e( 'Generado por', 'aura-suite' ); ?>:</span>
                <strong class="aura-print-meta-value"><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e( 'Fecha de Emisión', 'aura-suite' ); ?>:</span>
                <span class="aura-print-meta-value"><?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?></span>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e( 'Carácter', 'aura-suite' ); ?>:</span>
                <span class="aura-print-badge-official"><?php esc_html_e( 'Documento de Trabajo', 'aura-suite' ); ?></span>
            </div>
        </div>
    </div>

    <!-- ====================================================
         Cabecera de Pantalla: Hero Glass Card Universal
    ===================================================== -->
    <div class="aura-layout">
    <main class="aura-content">
        <header class="aura-glass-card aura-page-header">
            <div class="aura-page-header__icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="aura-page-header__content">
                <h1><?php esc_html_e( 'Aprobaciones Pendientes', 'aura-suite' ); ?></h1>
                <p><?php esc_html_e( 'Revisa, audita y valida o rechaza las transacciones financieras que esperan confirmación contable.', 'aura-suite' ); ?></p>
            </div>
            <div class="aura-page-header__badges">
                <span class="aura-badge aura-badge-blue" data-tooltip="<?php esc_attr_e( 'Total de operaciones financieras que requieren validación contable', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-clipboard"></span> <?php echo esc_html( $total_pending_count ); ?> <?php esc_html_e( 'pendientes', 'aura-suite' ); ?>
                </span>
                <?php if ( $approvable_count > 0 ) : ?>
                <span class="aura-badge aura-badge-emerald" data-tooltip="<?php esc_attr_e( 'Transacciones registradas por otros usuarios habilitadas para tu aprobación inmediata', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html( $approvable_count ); ?> <?php esc_html_e( 'por aprobar', 'aura-suite' ); ?>
                </span>
                <?php endif; ?>
                <button type="button" id="aura-print-pending-btn" class="button aura-ud-btn aura-print-action-btn" data-tooltip="<?php esc_attr_e( 'Generar reporte formal para impresión o exportar a archivo PDF oficial', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-printer"></span>
                    <span><?php esc_html_e( 'Imprimir / PDF', 'aura-suite' ); ?></span>
                </button>
            </div>
        </header>

        <!-- ── Tarjetas KPI de Resumen de Aprobaciones ────────────────────── -->
        <div class="aura-kpi-grid">
            <div class="aura-stat-card">
                <div class="stat-icon" style="background: #3b82f6;">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-label-wrap">
                        <span class="stat-label"><?php esc_html_e( 'Total Pendientes', 'aura-suite' ); ?></span>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Suma total de movimientos contables que esperan aprobación en el sistema.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </div>
                    <div class="stat-value" style="color: #2563eb;">
                        <span><?php echo esc_html( number_format_i18n( $total_pending_count ) ); ?></span>
                    </div>
                    <div class="stat-sub"><?php esc_html_e( 'En espera de resolución', 'aura-suite' ); ?></div>
                </div>
            </div>

            <div class="aura-stat-card">
                <div class="stat-icon" style="background: #10b981;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-label-wrap">
                        <span class="stat-label"><?php esc_html_e( 'Para Tu Aprobación', 'aura-suite' ); ?></span>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Transacciones registradas por otros usuarios que puedes auditar y autorizar.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </div>
                    <div class="stat-value" style="color: #059669;">
                        <span><?php echo esc_html( number_format_i18n( $approvable_count ) ); ?></span>
                    </div>
                    <div class="stat-sub"><?php esc_html_e( 'Registradas por terceros', 'aura-suite' ); ?></div>
                </div>
            </div>

            <div class="aura-stat-card">
                <div class="stat-icon" style="background: #8b5cf6;">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-label-wrap">
                        <span class="stat-label"><?php esc_html_e( 'Mis Registros', 'aura-suite' ); ?></span>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Tus propias transacciones pendientes que deben ser validadas por otro supervisor.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </div>
                    <div class="stat-value" style="color: #7c3aed;">
                        <span><?php echo esc_html( number_format_i18n( $mine_count ) ); ?></span>
                    </div>
                    <div class="stat-sub"><?php esc_html_e( 'Esperando otro revisor', 'aura-suite' ); ?></div>
                </div>
            </div>

            <div class="aura-stat-card">
                <div class="stat-icon" style="background: #f59e0b;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-label-wrap">
                        <span class="stat-label"><?php esc_html_e( 'Monto en Espera', 'aura-suite' ); ?></span>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Importe monetario total acumulado pendiente de conciliación y aprobación.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </div>
                    <div class="stat-value" style="color: #d97706;">
                        <span>$<?php echo esc_html( number_format( $total_pending_amount, 2, ',', '.' ) ); ?></span>
                    </div>
                    <div class="stat-sub"><?php esc_html_e( 'Volumen monetario global', 'aura-suite' ); ?></div>
                </div>
            </div>
        </div>

        <!-- ── Barra de Navegación de Pestañas (Tabs Navbar) ──────────────── -->
        <nav class="aura-navbar aura-nav aura-glass-card" style="margin-bottom: 20px;">
            <a href="<?php echo esc_url( admin_url('admin.php?page=aura-financial-pending&filter_view=all') ); ?>" class="aura-navbar-item aura-tab-btn <?php echo $current_view === 'all' ? 'active is-active' : ''; ?>">
                <span class="dashicons dashicons-list-view"></span>
                <span><?php esc_html_e( 'Todas las Pendientes', 'aura-suite' ); ?></span>
                <span class="aura-tab-count-pill"><?php echo esc_html( $total_pending_count ); ?></span>
            </a>
            <?php if ( current_user_can('aura_finance_approve') ) : ?>
            <a href="<?php echo esc_url( admin_url('admin.php?page=aura-financial-pending&filter_view=others') ); ?>" class="aura-navbar-item aura-tab-btn <?php echo $current_view === 'others' ? 'active is-active' : ''; ?>">
                <span class="dashicons dashicons-yes-alt"></span>
                <span><?php esc_html_e( 'Para Aprobar', 'aura-suite' ); ?></span>
                <span class="aura-tab-count-pill"><?php echo esc_html( $approvable_count ); ?></span>
            </a>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url('admin.php?page=aura-financial-pending&filter_view=mine') ); ?>" class="aura-navbar-item aura-tab-btn <?php echo $current_view === 'mine' ? 'active is-active' : ''; ?>">
                <span class="dashicons dashicons-admin-users"></span>
                <span><?php esc_html_e( 'Mis Pendientes', 'aura-suite' ); ?></span>
                <span class="aura-tab-count-pill"><?php echo esc_html( $mine_count ); ?></span>
            </a>
        </nav>

        <?php if (current_user_can('aura_finance_approve') && $approvable_count > 0): ?>
        <div class="notice notice-info inline aura-pending-inline-notice">
            <div class="aura-pending-notice-inner">
                <span class="dashicons dashicons-info aura-pending-notice-icon"></span>
                <div class="aura-pending-notice-text">
                    <strong><?php _e('Tienes', 'aura-suite'); ?> <?php echo $approvable_count; ?> <?php _e('transacciones esperando tu aprobación.', 'aura-suite'); ?></strong>
                    <span class="aura-pending-notice-sub">
                        <?php _e('Nota: Por norma de control interno, no puedes autorizar tus propios registros.', 'aura-suite'); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        
        <!-- Filtros y Búsqueda con Input Groups Tipo Bootstrap -->
        <?php
        // Contar filtros activos para el badge
        $active_filters = 0;
        if (!empty($_GET['filter_type'])) $active_filters++;
        if (!empty($_GET['filter_category'])) $active_filters++;
        if (!empty($_GET['filter_creator'])) $active_filters++;
        if (!empty($_GET['filter_amount_min']) || !empty($_GET['filter_amount_max'])) $active_filters++;
        $filters_open = ($active_filters > 0) ? 'true' : 'false';
        ?>
        <div class="aura-filters-bar aura-glass-card">
            <div class="aura-filters-bar__header">
                <button type="button" class="aura-filters-toggle" aria-expanded="<?php echo $filters_open; ?>" aria-controls="pending-filters-body">
                    <span class="dashicons dashicons-filter"></span>
                    <?php _e('Filtros y Búsqueda', 'aura-suite'); ?>
                    <?php if ($active_filters > 0): ?>
                    <span class="active-filters-badge"><?php echo $active_filters; ?></span>
                    <?php endif; ?>
                    <span class="toggle-chevron dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <?php if ($active_filters > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-pending' . ($current_view !== 'all' ? '&filter_view=' . urlencode($current_view) : '')); ?>" class="clear-filters-link">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e('Limpiar filtros', 'aura-suite'); ?>
                </a>
                <?php endif; ?>
            </div>
            
            <div id="pending-filters-body" class="aura-filters-bar__body" <?php echo $filters_open === 'false' ? 'hidden' : ''; ?>>
                <form method="get" id="pending-filters-form">
                    <input type="hidden" name="page" value="aura-financial-pending" />
                    <?php if (!empty($_GET['filter_view'])): ?>
                    <input type="hidden" name="filter_view" value="<?php echo esc_attr($_GET['filter_view']); ?>" />
                    <?php endif; ?>

                    <div class="filters-inline">
                        <!-- Tipo de Flujo -->
                        <div class="fi-group">
                            <label for="filter-type">
                                <?php _e('Tipo de Flujo', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Filtrar por naturaleza contable: Entrada (+) o Salida (-) de fondos.', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text" id="addon-type">
                                    <span class="dashicons dashicons-randomize"></span>
                                </span>
                                <select name="filter_type" id="filter-type" aria-describedby="addon-type">
                                    <option value=""><?php _e('Todos los tipos', 'aura-suite'); ?></option>
                                    <option value="income" <?php selected(isset($_GET['filter_type']) && $_GET['filter_type'] === 'income'); ?>><?php _e('Ingresos (+)', 'aura-suite'); ?></option>
                                    <option value="expense" <?php selected(isset($_GET['filter_type']) && $_GET['filter_type'] === 'expense'); ?>><?php _e('Egresos (-)', 'aura-suite'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Categoría -->
                        <div class="fi-group">
                            <label for="filter-category">
                                <?php _e('Categoría', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Clasificación presupuestaria o rubro financiero asignado.', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text" id="addon-category">
                                    <span class="dashicons dashicons-tag"></span>
                                </span>
                                <select name="filter_category" id="filter-category" aria-describedby="addon-category">
                                    <option value=""><?php _e('Todas las categorías', 'aura-suite'); ?></option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category['id']); ?>" <?php selected(isset($_GET['filter_category']) && $_GET['filter_category'] == $category['id']); ?>>
                                        <?php echo esc_html($category['name']); ?> (<?php echo $category['type'] === 'income' ? __('Ingreso', 'aura-suite') : __('Egreso', 'aura-suite'); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Creador -->
                        <div class="fi-group">
                            <label for="filter-creator">
                                <?php _e('Registrado por', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Usuario que registró inicialmente la transacción en el sistema.', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text" id="addon-creator">
                                    <span class="dashicons dashicons-admin-users"></span>
                                </span>
                                <select name="filter_creator" id="filter-creator" aria-describedby="addon-creator">
                                    <option value=""><?php _e('Todos los usuarios', 'aura-suite'); ?></option>
                                    <?php foreach ($creators as $creator): ?>
                                    <option value="<?php echo esc_attr($creator['ID']); ?>" <?php selected(isset($_GET['filter_creator']) && $_GET['filter_creator'] == $creator['ID']); ?>>
                                        <?php echo esc_html($creator['display_name']); ?><?php echo ($creator['ID'] == $current_user_id) ? ' (Tú)' : ''; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Rango de Monto -->
                        <div class="fi-group fi-group--range">
                            <label>
                                <?php _e('Rango de Monto', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Acotar registros entre un monto mínimo y máximo.', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="fi-range">
                                <div class="aura-input-group aura-amount-group" style="flex: 1;">
                                    <span class="aura-input-group-text currency-symbol">$</span>
                                    <input type="number" name="filter_amount_min" placeholder="<?php _e('Mínimo', 'aura-suite'); ?>" step="0.01" min="0" value="<?php echo isset($_GET['filter_amount_min']) ? esc_attr($_GET['filter_amount_min']) : ''; ?>" />
                                </div>
                                <span class="fi-range-sep">—</span>
                                <div class="aura-input-group aura-amount-group" style="flex: 1;">
                                    <span class="aura-input-group-text currency-symbol">$</span>
                                    <input type="number" name="filter_amount_max" placeholder="<?php _e('Máximo', 'aura-suite'); ?>" step="0.01" min="0" value="<?php echo isset($_GET['filter_amount_max']) ? esc_attr($_GET['filter_amount_max']) : ''; ?>" />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Acciones -->
                        <div class="fi-actions">
                            <button type="submit" class="button button-primary aura-filter-submit-btn">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('Aplicar Filtros', 'aura-suite'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabla de transacciones o Estado Vacío -->
        <?php if ($pagination_total == 0): ?>
        <div class="aura-empty-state aura-glass-card">
            <span class="dashicons dashicons-yes-alt aura-empty-state__icon"></span>
            <h2 class="aura-empty-state__title"><?php _e('¡Todo al día!', 'aura-suite'); ?></h2>
            <p class="aura-empty-state__desc"><?php _e('No hay transacciones pendientes de aprobación para los filtros actuales.', 'aura-suite'); ?></p>
        </div>
        <?php else: ?>
        <section class="aura-pending-table-card aura-glass-card">
            <form method="post" id="transactions-filter">
                <?php wp_nonce_field('bulk_action_pending_transactions', 'aura_pending_nonce'); ?>
                
                <div class="aura-table-responsive-wrap">
                    <?php $pending_list->display(); ?>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- Pie de Impresión Oficial (@media print) -->
        <div class="aura-print-footer">
            <div class="aura-print-footer__left">
                <span class="dashicons dashicons-shield-alt"></span>
                <?php esc_html_e( 'Documento oficial de control interno emitido por Aura Business Suite.', 'aura-suite' ); ?>
            </div>
            <div class="aura-print-footer__right">
                <?php echo esc_html( get_bloginfo( 'name' ) ); ?> &bull; <?php esc_html_e( 'Página 1', 'aura-suite' ); ?>
            </div>
        </div>

    </main>
    </div>
</div>
</div>

<!-- Modal: Aprobar Transacción -->
<div id="approve-modal" class="aura-modal aura-modal-hidden" style="display:none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header aura-modal-header--primary">
            <h2>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php _e('Aprobar Transacción', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="aura-modal-info-box aura-modal-info-box--primary">
                <span class="dashicons dashicons-saved aura-modal-info-icon"></span>
                <div class="aura-modal-info-content">
                    <p class="aura-modal-info-title"><?php _e('¿Estás seguro de que deseas aprobar esta transacción?', 'aura-suite'); ?></p>
                    <div class="transaction-summary aura-modal-info-summary">
                        <strong><?php _e('Descripción:', 'aura-suite'); ?></strong>
                        <span id="approve-transaction-desc"></span>
                    </div>
                </div>
            </div>
            
            <div class="aura-modal-field">
                <label for="approve-note" class="aura-modal-label"><?php _e('Nota de aprobación (opcional):', 'aura-suite'); ?></label>
                <textarea id="approve-note" rows="3" class="aura-modal-textarea"
                          placeholder="<?php _e('Agrega un comentario u observación sobre esta aprobación...', 'aura-suite'); ?>"></textarea>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button aura-btn-modal-cancel" id="cancel-approve">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary aura-btn-modal-confirm aura-btn-modal--approve" id="confirm-approve">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Aprobar Transacción', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Rechazar Transacción -->
<div id="reject-modal" class="aura-modal aura-modal-hidden" style="display:none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header aura-modal-header--danger">
            <h2>
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Rechazar Transacción', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="aura-modal-info-box aura-modal-info-box--danger">
                <span class="dashicons dashicons-dismiss aura-modal-info-icon"></span>
                <div class="aura-modal-info-content">
                    <p class="aura-modal-info-title"><?php _e('Indica el motivo por el cual rechazas esta transacción:', 'aura-suite'); ?></p>
                    <div class="transaction-summary aura-modal-info-summary">
                        <strong><?php _e('Descripción:', 'aura-suite'); ?></strong>
                        <span id="reject-transaction-desc"></span>
                    </div>
                </div>
            </div>
            
            <div class="aura-modal-field">
                <label for="reject-reason" class="aura-modal-label">
                    <?php _e('Motivo de rechazo:', 'aura-suite'); ?>
                    <span class="required aura-modal-required">*</span>
                </label>
                <textarea id="reject-reason" rows="4" class="aura-modal-textarea"
                          placeholder="<?php _e('Explica detalladamente por qué rechazas esta transacción (mínimo 20 caracteres)...', 'aura-suite'); ?>" 
                          required></textarea>
                <p class="description aura-char-count-text">
                    <span id="reason-char-count">0</span> / 20 <?php _e('caracteres mínimos requeridos', 'aura-suite'); ?>
                </p>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button aura-btn-modal-cancel" id="cancel-reject">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary button-danger aura-btn-modal-confirm aura-btn-modal--danger" id="confirm-reject" disabled>
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Rechazar Transacción', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Aprobación Masiva -->
<div id="bulk-approve-modal" class="aura-modal aura-modal-hidden" style="display:none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header aura-modal-header--primary">
            <h2>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php _e('Aprobación Masiva de Transacciones', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="aura-modal-info-box aura-modal-info-box--primary">
                <span class="dashicons dashicons-saved aura-modal-info-icon"></span>
                <div class="aura-modal-info-content">
                    <p class="aura-modal-info-title">
                        <?php _e('¿Deseas aprobar las', 'aura-suite'); ?>
                        <strong><span id="bulk-approve-count">0</span></strong>
                        <?php _e('transacciones seleccionadas?', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            
            <div class="aura-modal-field">
                <label for="bulk-approve-note" class="aura-modal-label"><?php _e('Nota de aprobación (opcional):', 'aura-suite'); ?></label>
                <textarea id="bulk-approve-note" rows="3" class="aura-modal-textarea"
                          placeholder="<?php _e('Comentario aplicable a todas las transacciones autorizadas...', 'aura-suite'); ?>"></textarea>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button aura-btn-modal-cancel" id="cancel-bulk-approve">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary aura-btn-modal-confirm aura-btn-modal--approve" id="confirm-bulk-approve">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Aprobar Seleccionadas', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Rechazo Masivo -->
<div id="bulk-reject-modal" class="aura-modal aura-modal-hidden" style="display:none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header aura-modal-header--danger">
            <h2>
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Rechazo Masivo de Transacciones', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="aura-modal-info-box aura-modal-info-box--danger">
                <span class="dashicons dashicons-dismiss aura-modal-info-icon"></span>
                <div class="aura-modal-info-content">
                    <p class="aura-modal-info-title">
                        <?php _e('¿Deseas rechazar las', 'aura-suite'); ?>
                        <strong><span id="bulk-reject-count">0</span></strong>
                        <?php _e('transacciones seleccionadas?', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            
            <div class="aura-modal-field">
                <label for="bulk-reject-reason" class="aura-modal-label">
                    <?php _e('Motivo de rechazo:', 'aura-suite'); ?>
                    <span class="required aura-modal-required">*</span>
                </label>
                <textarea id="bulk-reject-reason" rows="4" class="aura-modal-textarea"
                          placeholder="<?php _e('Motivo común aplicable a todas las transacciones rechazadas (mínimo 20 caracteres)...', 'aura-suite'); ?>" 
                          required></textarea>
                <p class="description aura-char-count-text">
                    <span id="bulk-reason-char-count">0</span> / 20 <?php _e('caracteres mínimos requeridos', 'aura-suite'); ?>
                </p>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button aura-btn-modal-cancel" id="cancel-bulk-reject">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary button-danger aura-btn-modal-confirm aura-btn-modal--danger" id="confirm-bulk-reject" disabled>
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Rechazar Seleccionadas', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<?php include AURA_PLUGIN_DIR . 'templates/financial/transaction-modal.php'; ?>

<script>
jQuery(document).ready(function($) {
    'use strict';

    let currentTransactionId = null;
    let selectedTransactionIds = [];

    // === BOTÓN IMPRIMIR / PDF ===
    $('#aura-print-pending-btn').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    // === TOGGLE DE FILAS EXPANDIBLES (CHILD ROWS) ===
    $(document).on('click', '.aura-row-toggle', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $parentRow = $btn.closest('tr.aura-parent-row');
        const $childRow = $parentRow.next('tr.aura-child-row');
        const $wrapper = $childRow.find('.aura-child-card-wrapper');

        const isExpanded = $btn.attr('aria-expanded') === 'true';

        if (isExpanded) {
            $wrapper.slideUp(180, function() {
                $childRow.hide();
                $btn.attr('aria-expanded', 'false').removeClass('is-active');
            });
        } else {
            $childRow.show();
            $wrapper.hide().slideDown(220);
            $btn.attr('aria-expanded', 'true').addClass('is-active');
        }
    });

    // === TOGGLE FILTROS ===
    $('.aura-filters-toggle').on('click', function() {
        const $body = $('#pending-filters-body');
        const expanded = $(this).attr('aria-expanded') === 'true';
        if (expanded) {
            $body.slideUp(180, function() {
                $body.attr('hidden', '');
            });
            $(this).attr('aria-expanded', 'false');
        } else {
            $body.removeAttr('hidden').hide().slideDown(200);
            $(this).attr('aria-expanded', 'true');
        }
    });

    // === APROBAR ÚNICA ===
    $(document).on('click', '.approve-transaction', function(e) {
        e.preventDefault();
        currentTransactionId = $(this).data('id');
        const desc = $(this).closest('tr').find('strong').first().text() || ('Transacción #' + currentTransactionId);
        $('#approve-transaction-desc').text(desc);
        $('#approve-note').val('');
        $('#approve-modal').removeClass('aura-modal-hidden').fadeIn(200);
    });
    
    $('#confirm-approve').on('click', function() {
        const note = $('#approve-note').val().trim();
        approveTransaction(currentTransactionId, note);
    });
    
    $('#cancel-approve, #approve-modal .aura-modal-close, #approve-modal .aura-modal-overlay').on('click', function() {
        $('#approve-modal').fadeOut(200, function() { $(this).addClass('aura-modal-hidden'); });
    });
    
    // === RECHAZAR ÚNICA ===
    $(document).on('click', '.reject-transaction', function(e) {
        e.preventDefault();
        currentTransactionId = $(this).data('id');
        const desc = $(this).closest('tr').find('strong').first().text() || ('Transacción #' + currentTransactionId);
        $('#reject-transaction-desc').text(desc);
        $('#reject-reason').val('');
        $('#reason-char-count').text('0');
        $('#confirm-reject').prop('disabled', true);
        $('#reject-modal').removeClass('aura-modal-hidden').fadeIn(200);
    });
    
    $('#reject-reason').on('input', function() {
        const length = $(this).val().trim().length;
        $('#reason-char-count').text(length);
        $('#confirm-reject').prop('disabled', length < 20);
    });
    
    $('#confirm-reject').on('click', function() {
        const reason = $('#reject-reason').val().trim();
        if (reason.length >= 20) {
            rejectTransaction(currentTransactionId, reason);
        }
    });
    
    $('#cancel-reject, #reject-modal .aura-modal-close, #reject-modal .aura-modal-overlay').on('click', function() {
        $('#reject-modal').fadeOut(200, function() { $(this).addClass('aura-modal-hidden'); });
    });
    
    // === ACCIONES MASIVAS ===
    $('#doaction, #doaction2').on('click', function(e) {
        const action = $(this).siblings('select').val();
        selectedTransactionIds = [];
        
        $('input[name="transaction_ids[]"]:checked').each(function() {
            selectedTransactionIds.push($(this).val());
        });
        
        if (selectedTransactionIds.length === 0) {
            return;
        }
        
        e.preventDefault();
        
        if (action === 'bulk_approve') {
            $('#bulk-approve-count').text(selectedTransactionIds.length);
            $('#bulk-approve-note').val('');
            $('#bulk-approve-modal').removeClass('aura-modal-hidden').fadeIn(200);
        } else if (action === 'bulk_reject') {
            $('#bulk-reject-count').text(selectedTransactionIds.length);
            $('#bulk-reject-reason').val('');
            $('#bulk-reason-char-count').text('0');
            $('#confirm-bulk-reject').prop('disabled', true);
            $('#bulk-reject-modal').removeClass('aura-modal-hidden').fadeIn(200);
        }
    });
    
    $('#bulk-reject-reason').on('input', function() {
        const length = $(this).val().trim().length;
        $('#bulk-reason-char-count').text(length);
        $('#confirm-bulk-reject').prop('disabled', length < 20);
    });
    
    $('#confirm-bulk-approve').on('click', function() {
        const note = $('#bulk-approve-note').val().trim();
        bulkApproveTransactions(selectedTransactionIds, note);
    });
    
    $('#confirm-bulk-reject').on('click', function() {
        const reason = $('#bulk-reject-reason').val().trim();
        if (reason.length >= 20) {
            bulkRejectTransactions(selectedTransactionIds, reason);
        }
    });
    
    $('#cancel-bulk-approve, #bulk-approve-modal .aura-modal-close, #bulk-approve-modal .aura-modal-overlay').on('click', function() {
        $('#bulk-approve-modal').fadeOut(200, function() { $(this).addClass('aura-modal-hidden'); });
    });
    
    $('#cancel-bulk-reject, #bulk-reject-modal .aura-modal-close, #bulk-reject-modal .aura-modal-overlay').on('click', function() {
        $('#bulk-reject-modal').fadeOut(200, function() { $(this).addClass('aura-modal-hidden'); });
    });
    
    // === AJAX: APROBAR ===
    function approveTransaction(transactionId, note) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_approve_transaction',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_id: transactionId,
                approval_note: note
            },
            beforeSend: function() {
                $('#confirm-approve').prop('disabled', true).html('<span class="spinner is-active aura-inline-spinner"></span> Aprobando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#approve-modal').fadeOut(200);
                    showNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Transacción');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Transacción');
            }
        });
    }
    
    // === AJAX: RECHAZAR ===
    function rejectTransaction(transactionId, reason) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_reject_transaction',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_id: transactionId,
                rejection_reason: reason
            },
            beforeSend: function() {
                $('#confirm-reject').prop('disabled', true).html('<span class="spinner is-active aura-inline-spinner"></span> Rechazando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#reject-modal').fadeOut(200);
                    showNotice('warning', response.data.message);
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Transacción');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Transacción');
            }
        });
    }
    
    // === AJAX: APROBACIÓN MASIVA ===
    function bulkApproveTransactions(transactionIds, note) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_bulk_approve',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_ids: transactionIds,
                approval_note: note
            },
            beforeSend: function() {
                $('#confirm-bulk-approve').prop('disabled', true).html('<span class="spinner is-active aura-inline-spinner"></span> Aprobando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#bulk-approve-modal').fadeOut(200);
                    showNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-bulk-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Seleccionadas');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-bulk-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Seleccionadas');
            }
        });
    }
    
    // === AJAX: RECHAZO MASIVO ===
    function bulkRejectTransactions(transactionIds, reason) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_bulk_reject',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_ids: transactionIds,
                rejection_reason: reason
            },
            beforeSend: function() {
                $('#confirm-bulk-reject').prop('disabled', true).html('<span class="spinner is-active aura-inline-spinner"></span> Rechazando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#bulk-reject-modal').fadeOut(200);
                    showNotice('warning', response.data.message);
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-bulk-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Seleccionadas');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-bulk-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Seleccionadas');
            }
        });
    }
    
    // === NOTIFICACIÓN TOAST ===
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 
                           type === 'warning' ? 'notice-warning' : 'notice-error';
        
        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="animation:auraNoticeSlide 0.3s ease-out;"><p>' + message + '</p></div>');
        $('.aura-content').prepend($notice);
        
        setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }
});
</script>
