<?php
/**
 * Template: Listado de Transacciones Financieras
 * 
 * Muestra tabla de transacciones con filtros avanzados,
 * búsqueda en tiempo real y estadísticas
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos (incluir administradores)
if (!current_user_can('aura_finance_view_own') && 
    !current_user_can('aura_finance_view_all') && 
    !current_user_can('manage_options')) {
    wp_die(__('No tienes permisos para ver esta página', 'aura-suite'));
}

// Instanciar tabla
$transactions_list = new Aura_Financial_Transactions_List();
$transactions_list->prepare_items();
$stats = $transactions_list->get_stats();

require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-tags.php';
$available_tags = Aura_Financial_Tags::get_all_tags();
?>

<?php
$active_filters_count = 0;
if (!empty($_GET['filter_type'])) $active_filters_count++;
if (!empty($_GET['filter_status'])) $active_filters_count++;
if (!empty($_GET['filter_category']) || !empty($_GET['category_id'])) $active_filters_count++;
if (!empty($_GET['filter_date_from']) || !empty($_GET['filter_date_to'])) $active_filters_count++;
if (!empty($_GET['filter_amount_min']) || !empty($_GET['filter_amount_max'])) $active_filters_count++;
if (!empty($_GET['filter_payment_method'])) $active_filters_count++;
if (!empty($_GET['filter_user'])) $active_filters_count++;
if (!empty($_GET['filter_related_user'])) $active_filters_count++;
if (!empty($_GET['filter_area'])) $active_filters_count++;
?>

<div class="wrap aura-transactions-list-page">
    <div class="aura-transactions-header-bar">
        <div>
            <h1 class="wp-heading-inline" style="margin-right: 15px;">
                <?php _e('Transacciones Financieras', 'aura-suite'); ?>
            </h1>
            <span class="aura-badge-sub"><?php printf(__('Total: %s registros', 'aura-suite'), number_format($stats['count'] ?? 0)); ?></span>
        </div>

        <div class="aura-header-actions-row">
            <a href="<?php echo admin_url('admin.php?page=aura-financial-new-transaction'); ?>" class="button button-primary aura-btn-hero">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php _e('Nueva Transacción', 'aura-suite'); ?>
            </a>

            <a href="<?php echo admin_url('admin.php?page=aura-financial-import'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-upload"></span>
                <?php _e('Importar Transacciones', 'aura-suite'); ?>
            </a>

            <?php if (current_user_can('aura_finance_bulk_edit') || current_user_can('manage_options')): ?>
            <button type="button" id="aura-bulk-edit-btn" class="button button-secondary" style="font-weight:600;color:#2563eb;border-color:#bfdbfe;background:#eff6ff;">
                <span class="dashicons dashicons-edit" style="color:#2563eb;"></span>
                <?php _e('Edición Masiva', 'aura-suite'); ?>
                <span id="aura-bulk-edit-badge" style="display:none;margin-left:4px;padding:1px 6px;border-radius:10px;background:#2563eb;color:#fff;font-size:11px;font-weight:700;">0</span>
            </button>
            <?php endif; ?>

            <?php if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all') || current_user_can('manage_options')): ?>
            <button type="button" id="aura-export-btn" class="button button-secondary">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Exportar CSV', 'aura-suite'); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" id="aura-filtered-count" value="<?php echo intval($stats['count'] ?? 0); ?>">

    <hr class="wp-header-end">
    
    <!-- Estadísticas en cabecera con tarjetas premium -->
    <div class="aura-stats-header">
        <div class="aura-stat-card aura-stat-income">
            <div class="stat-icon-wrap">
                <span class="dashicons dashicons-arrow-up-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Total Ingresos', 'aura-suite'); ?></div>
                <div class="stat-value">$<?php echo number_format($stats['total_income'], 2, '.', ','); ?></div>
            </div>
        </div>
        
        <div class="aura-stat-card aura-stat-expense">
            <div class="stat-icon-wrap">
                <span class="dashicons dashicons-arrow-down-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Total Egresos', 'aura-suite'); ?></div>
                <div class="stat-value">$<?php echo number_format($stats['total_expense'], 2, '.', ','); ?></div>
            </div>
        </div>

        <div class="aura-stat-card aura-stat-capital">
            <div class="stat-icon-wrap">
                <span class="dashicons dashicons-vault"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Gastos Capital (CapEx)', 'aura-suite'); ?></div>
                <div class="stat-value">$<?php echo number_format($stats['total_capital'] ?? 0, 2, '.', ','); ?></div>
            </div>
        </div>
        
        <div class="aura-stat-card aura-stat-balance <?php echo $stats['balance'] >= 0 ? 'positive' : 'negative'; ?>">
            <div class="stat-icon-wrap">
                <span class="dashicons <?php echo $stats['balance'] >= 0 ? 'dashicons-chart-line' : 'dashicons-chart-area'; ?>"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Balance Operativo', 'aura-suite'); ?></div>
                <div class="stat-value">$<?php echo number_format($stats['balance'], 2, '.', ','); ?></div>
            </div>
        </div>
        
        <div class="aura-stat-card aura-stat-count">
            <div class="stat-icon-wrap">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Transacciones', 'aura-suite'); ?></div>
                <div class="stat-value"><?php echo number_format($stats['count']); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Contenedor principal con sidebar de filtros moderno -->
    <div class="aura-transactions-page">
        
        <!-- Sidebar de filtros (colapsable y moderno) -->
        <aside id="aura-filters-sidebar" class="aura-filters-card">
            <div class="aura-filters-header">
                <div class="aura-filters-header-title">
                    <span class="dashicons dashicons-filter" style="color: #3b82f6;"></span>
                    <h3><?php _e('Filtros', 'aura-suite'); ?></h3>
                    <?php if ($active_filters_count > 0): ?>
                        <span class="aura-active-count-chip" title="<?php printf(__('%d filtros aplicados', 'aura-suite'), $active_filters_count); ?>">
                            <?php echo $active_filters_count; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <button type="button" class="button-link aura-toggle-sidebar-btn" id="toggle-filters" title="<?php esc_attr_e('Ocultar filtros', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
            </div>
            
            <div class="aura-filters-body">
                <form method="get" id="aura-filters-form">
                    <input type="hidden" name="page" value="aura-financial-transactions">
                    
                    <!-- Sección: Tipo de Transacción (Ingreso, Egreso, Capital) -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-tag" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#3b82f6;"></span>
                            <?php _e('Tipo de Transacción', 'aura-suite'); ?>
                        </h4>
                        <?php
                        $sel_types = (array) ($_GET['filter_type'] ?? array());
                        ?>
                        <div class="aura-filter-chips-grid aura-filter-chips-3col">
                            <label class="aura-chip-checkbox aura-chip-income <?php echo in_array('income', $sel_types, true) ? 'active' : ''; ?>">
                                <input type="checkbox" name="filter_type[]" value="income" <?php checked(in_array('income', $sel_types, true)); ?>>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php _e('Ingreso', 'aura-suite'); ?></span>
                            </label>

                            <label class="aura-chip-checkbox aura-chip-expense <?php echo in_array('expense', $sel_types, true) ? 'active' : ''; ?>">
                                <input type="checkbox" name="filter_type[]" value="expense" <?php checked(in_array('expense', $sel_types, true)); ?>>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php _e('Egreso', 'aura-suite'); ?></span>
                            </label>

                            <label class="aura-chip-checkbox aura-chip-capital <?php echo in_array('capital', $sel_types, true) ? 'active' : ''; ?>">
                                <input type="checkbox" name="filter_type[]" value="capital" <?php checked(in_array('capital', $sel_types, true)); ?>>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php _e('Capital (CapEx)', 'aura-suite'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Sección: Estado de Aprobación -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-yes-alt" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#10b981;"></span>
                            <?php _e('Estado de Aprobación', 'aura-suite'); ?>
                        </h4>
                        <?php
                        $sel_status = (array) ($_GET['filter_status'] ?? array());
                        ?>
                        <div class="aura-filter-chips-grid">
                            <label class="aura-chip-checkbox aura-chip-pending <?php echo in_array('pending', $sel_status, true) ? 'active' : ''; ?>">
                                <input type="checkbox" name="filter_status[]" value="pending" <?php checked(in_array('pending', $sel_status, true)); ?>>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php _e('Pendiente', 'aura-suite'); ?></span>
                            </label>

                            <label class="aura-chip-checkbox aura-chip-approved <?php echo in_array('approved', $sel_status, true) ? 'active' : ''; ?>">
                                <input type="checkbox" name="filter_status[]" value="approved" <?php checked(in_array('approved', $sel_status, true)); ?>>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php _e('Aprobado', 'aura-suite'); ?></span>
                            </label>

                            <label class="aura-chip-checkbox aura-chip-rejected <?php echo in_array('rejected', $sel_status, true) ? 'active' : ''; ?>">
                                <input type="checkbox" name="filter_status[]" value="rejected" <?php checked(in_array('rejected', $sel_status, true)); ?>>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php _e('Rechazado', 'aura-suite'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Sección: Fechas con Presets Rápidos -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-calendar-alt" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#f59e0b;"></span>
                            <?php _e('Rango de Fechas', 'aura-suite'); ?>
                        </h4>
                        <div class="aura-date-range-row">
                            <div class="aura-date-input-wrap">
                                <label for="filter_date_from"><?php _e('Desde', 'aura-suite'); ?></label>
                                <input type="text" 
                                       name="filter_date_from" 
                                       id="filter_date_from" 
                                       class="aura-datepicker" 
                                       placeholder="YYYY-MM-DD"
                                       value="<?php echo esc_attr($_GET['filter_date_from'] ?? ''); ?>">
                            </div>
                            <div class="aura-date-input-wrap">
                                <label for="filter_date_to"><?php _e('Hasta', 'aura-suite'); ?></label>
                                <input type="text" 
                                       name="filter_date_to" 
                                       id="filter_date_to" 
                                       class="aura-datepicker" 
                                       placeholder="YYYY-MM-DD"
                                       value="<?php echo esc_attr($_GET['filter_date_to'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="aura-quick-date-chips">
                            <button type="button" class="aura-date-preset-btn" data-range="today"><?php _e('Hoy', 'aura-suite'); ?></button>
                            <button type="button" class="aura-date-preset-btn" data-range="this_month"><?php _e('Este Mes', 'aura-suite'); ?></button>
                            <button type="button" class="aura-date-preset-btn" data-range="last_30"><?php _e('Últimos 30d', 'aura-suite'); ?></button>
                            <button type="button" class="aura-date-preset-btn" data-range="this_year"><?php _e('Este Año', 'aura-suite'); ?></button>
                        </div>
                    </div>

                    <!-- Sección: Categoría -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-category" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#8b5cf6;"></span>
                            <?php _e('Categoría Financiera', 'aura-suite'); ?>
                        </h4>

                        <div class="aura-select-wrap">
                            <select name="filter_category" id="filter_category" class="aura-select2 widefat">
                                <option value=""><?php _e('Todas las categorías', 'aura-suite'); ?></option>
                                <?php
                                global $wpdb;
                                $categories = $wpdb->get_results("
                                    SELECT id, name, parent_id 
                                    FROM {$wpdb->prefix}aura_finance_categories 
                                    WHERE is_active = 1 
                                    ORDER BY display_order ASC, name ASC
                                    LIMIT 500
                                ");
                                
                                foreach ((array) $categories as $category) {
                                    $curr_cat = !empty($_GET['filter_category']) ? intval($_GET['filter_category']) : (!empty($_GET['category_id']) ? intval($_GET['category_id']) : 0);
                                    $selected = selected($curr_cat === intval($category->id), true, false);
                                    $indent = $category->parent_id > 0 ? '&nbsp;&nbsp;&nbsp;↳ ' : '';
                                    printf(
                                        '<option value="%d" %s>%s%s</option>',
                                        $category->id,
                                        $selected,
                                        $indent,
                                        esc_html($category->name)
                                    );
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Sección: Método de Pago -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-money-alt" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#0ea5e9;"></span>
                            <?php _e('Método de Pago', 'aura-suite'); ?>
                        </h4>

                        <div class="aura-select-wrap">
                            <select name="filter_payment_method" id="filter_payment_method" class="widefat">
                                <option value=""><?php _e('Todos los métodos', 'aura-suite'); ?></option>
                                <option value="Efectivo" <?php selected($_GET['filter_payment_method'] ?? '', 'Efectivo'); ?>><?php _e('Efectivo', 'aura-suite'); ?></option>
                                <option value="Transferencia" <?php selected($_GET['filter_payment_method'] ?? '', 'Transferencia'); ?>><?php _e('Transferencia', 'aura-suite'); ?></option>
                                <option value="Cheque" <?php selected($_GET['filter_payment_method'] ?? '', 'Cheque'); ?>><?php _e('Cheque', 'aura-suite'); ?></option>
                                <option value="Tarjeta" <?php selected($_GET['filter_payment_method'] ?? '', 'Tarjeta'); ?>><?php _e('Tarjeta', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Sección: Rango de Monto -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-chart-bar" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#10b981;"></span>
                            <?php _e('Rango de Monto ($)', 'aura-suite'); ?>
                        </h4>
                        <div class="aura-amount-grid">
                            <div class="aura-amount-input-wrap">
                                <label for="filter_amount_min"><?php _e('Mínimo ($)', 'aura-suite'); ?></label>
                                <input type="number" 
                                       id="filter_amount_min"
                                       name="filter_amount_min" 
                                       placeholder="0.00"
                                       step="0.01"
                                       value="<?php echo esc_attr($_GET['filter_amount_min'] ?? ''); ?>">
                            </div>
                            <div class="aura-amount-input-wrap">
                                <label for="filter_amount_max"><?php _e('Máximo ($)', 'aura-suite'); ?></label>
                                <input type="number" 
                                       id="filter_amount_max"
                                       name="filter_amount_max" 
                                       placeholder="0.00"
                                       step="0.01"
                                       value="<?php echo esc_attr($_GET['filter_amount_max'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Responsables y Usuarios -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-admin-users" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#3b82f6;"></span>
                            <?php _e('Responsables y Usuarios', 'aura-suite'); ?>
                        </h4>

                        <!-- Creado por -->
                        <?php if (current_user_can('aura_finance_view_all')) : ?>
                        <div class="aura-field-wrap" style="margin-bottom:10px;">
                            <label for="filter_user"><?php _e('Creado por (Registrado por)', 'aura-suite'); ?></label>
                            <select name="filter_user" id="filter_user" class="aura-select2 widefat">
                                <option value=""><?php _e('Todos los usuarios', 'aura-suite'); ?></option>
                                <?php
                                $users = Aura_Roles_Manager::get_aura_users( [], 'aura_finance_' );
                                foreach ($users as $user) {
                                    $selected = selected(!empty($_GET['filter_user']) && $_GET['filter_user'] == $user->ID, true, false);
                                    printf(
                                        '<option value="%d" %s>%s</option>',
                                        $user->ID,
                                        $selected,
                                        esc_html($user->display_name)
                                    );
                                }
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Usuario Vinculado -->
                        <?php if (current_user_can('aura_finance_view_all') || current_user_can('aura_finance_user_ledger')) : ?>
                        <?php
                        $related_user_name   = '';
                        $related_user_avatar = '';
                        if (!empty($_GET['filter_related_user'])) {
                            $fu = get_userdata(intval($_GET['filter_related_user']));
                            if ($fu) {
                                $related_user_name   = $fu->display_name;
                                $related_user_avatar = get_avatar_url($fu->ID, array('size' => 24));
                            }
                        }
                        ?>
                        <div class="aura-field-wrap">
                            <label for="filter_related_user_search"><?php _e('Usuario Vinculado (Cliente/Proveedor)', 'aura-suite'); ?></label>
                            <div class="aura-user-autocomplete-wrap">
                                <input type="text"
                                       id="filter_related_user_search"
                                       placeholder="<?php _e('Buscar por nombre o correo...', 'aura-suite'); ?>"
                                       autocomplete="off"
                                       class="widefat"
                                       value="<?php echo esc_attr($related_user_name); ?>">
                                <input type="hidden"
                                       id="filter_related_user"
                                       name="filter_related_user"
                                       value="<?php echo esc_attr($_GET['filter_related_user'] ?? ''); ?>">
                                <div id="aura-filter-user-preview" class="aura-filter-user-preview" <?php echo $related_user_name ? '' : 'style="display:none;"'; ?>>
                                    <img id="aura-filter-user-avatar"
                                         src="<?php echo esc_url($related_user_avatar); ?>"
                                         alt=""
                                         width="24"
                                         height="24">
                                    <div class="aura-filter-user-meta">
                                        <span class="aura-filter-user-label"><?php _e('Seleccionado', 'aura-suite'); ?></span>
                                        <span id="aura-filter-user-name" class="aura-filter-user-name"><?php echo esc_html($related_user_name); ?></span>
                                    </div>
                                    <button type="button" id="aura-filter-user-clear" class="aura-filter-user-clear" title="<?php esc_attr_e('Quitar usuario', 'aura-suite'); ?>">×</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sección: Área / Programa -->
                    <?php
                    $_tx_view_own = (
                        current_user_can( 'aura_areas_view_own' )
                        && ! current_user_can( 'aura_areas_view_all' )
                        && ! current_user_can( 'manage_options' )
                    );
                    if ( $_tx_view_own ) {
                        $_tx_areas = Aura_Areas_Setup::get_user_areas( get_current_user_id() );
                    } else {
                        $_tx_areas = $wpdb->get_results(
                            "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order, name"
                        );
                    }
                    if ( ! empty( $_tx_areas ) ) :
                    ?>
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-grid-view" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#ec4899;"></span>
                            <?php _e('Área / Programa', 'aura-suite'); ?>
                        </h4>
                        <div class="aura-select-wrap">
                            <select name="filter_area" id="filter_area" class="widefat">
                                <option value=""><?php _e('Todas las áreas permitidas', 'aura-suite'); ?></option>
                                <?php foreach ( $_tx_areas as $_ta ) : ?>
                                <option value="<?php echo (int) $_ta->id; ?>"
                                    style="padding-left:6px;"
                                    <?php selected( (string)(int)($_GET['filter_area'] ?? 0), (string)(int)$_ta->id ); ?>>
                                    <?php echo esc_html( $_ta->name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Presets de filtros -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-star-filled" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#eab308;"></span>
                            <?php _e('Filtros Guardados', 'aura-suite'); ?>
                        </h4>
                        
                        <div class="aura-presets-wrap">
                            <select id="load-filter-preset" class="widefat">
                                <option value=""><?php _e('Selecciona un preset guardado', 'aura-suite'); ?></option>
                                <option value="this_month"><?php _e('Este mes', 'aura-suite'); ?></option>
                                <option value="pending"><?php _e('Pendientes de aprobación', 'aura-suite'); ?></option>
                                <option value="my_transactions"><?php _e('Mis transacciones', 'aura-suite'); ?></option>
                                <option value="high_amount"><?php _e('Gastos mayores a $1,000', 'aura-suite'); ?></option>
                                <?php
                                $user_presets = get_user_meta(get_current_user_id(), 'aura_finance_filter_presets', true);
                                if (is_array($user_presets)) {
                                    foreach ($user_presets as $preset_name => $preset_data) {
                                        printf(
                                            '<option value="%s">%s</option>',
                                            esc_attr($preset_name),
                                            esc_html($preset_name)
                                        );
                                    }
                                }
                                ?>
                            </select>
                            <button type="button" class="button button-secondary widefat" id="save-filter-preset" style="margin-top: 8px;">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php _e('Guardar filtro actual', 'aura-suite'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Botones de acción fijos -->
                    <div class="filter-actions">
                        <button type="submit" class="button button-primary widefat aura-btn-apply-filters">
                            <span class="dashicons dashicons-filter"></span>
                            <?php _e('Aplicar Filtros', 'aura-suite'); ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="button button-secondary widefat aura-btn-reset-filters">
                            <span class="dashicons dashicons-image-rotate"></span>
                            <?php _e('Limpiar Filtros', 'aura-suite'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </aside>
        
        <!-- Contenido principal -->
        <main id="aura-transactions-content">
            
            <!-- Barra de búsqueda -->
            <div class="aura-search-bar">
                <button type="button" class="button" id="show-filters" style="display: none;">
                    <span class="dashicons dashicons-filter"></span>
                    <?php _e('Mostrar Filtros', 'aura-suite'); ?>
                </button>
                
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="search-form">
                    <input type="hidden" name="page" value="aura-financial-transactions">
                    <input type="search" 
                           id="transaction-search-input" 
                           name="s" 
                           class="aura-search-input" 
                           placeholder="<?php _e('Buscar en descripción, notas, referencia...', 'aura-suite'); ?>"
                           value="<?php echo esc_attr($_GET['s'] ?? ''); ?>">
                    <button type="submit" class="button">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </form>
                
                <div id="search-results-dropdown" class="aura-search-results" style="display: none;">
                    <!-- Resultados de búsqueda en tiempo real se cargan aquí via AJAX -->
                </div>
            </div>
            
            <!-- Tabla de transacciones -->
            <form method="post" id="transactions-filter">
                <div class="aura-table-responsive-wrap">
                    <?php $transactions_list->display(); ?>
                </div>
            </form>


        </main>
    </div>
</div>

<!-- Modal para guardar preset de filtros -->
<div id="save-preset-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-content">
        <span class="aura-modal-close">&times;</span>
        <h2><?php _e('Guardar Filtros', 'aura-suite'); ?></h2>
        <p><?php _e('Ingresa un nombre para este conjunto de filtros:', 'aura-suite'); ?></p>
        <input type="text" id="preset-name-input" class="widefat" placeholder="<?php _e('Ej: Ingresos del mes', 'aura-suite'); ?>">
        <div class="modal-buttons">
            <button type="button" class="button button-primary" id="confirm-save-preset">
                <?php _e('Guardar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-secondary" id="cancel-save-preset">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL DE EXPORTACIÓN                                              -->
<!-- ================================================================ -->
<?php if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all') || current_user_can('manage_options')): ?>
<div id="aura-export-modal" style="display:none;">
    <div class="aura-export-overlay"></div>
    <div class="aura-export-modal-box">

        <div class="aura-export-modal-header">
            <h2>
                <span class="dashicons dashicons-download"></span>
                <?php _e('Exportar Transacciones', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-export-modal-close" aria-label="Cerrar">✕</button>
        </div>

        <form id="aura-export-form">
        <div class="aura-export-modal-body">

            <!-- FORMATO -->
            <div class="export-section">
                <h3><span class="dashicons dashicons-media-document"></span><?php _e('Formato de exportación', 'aura-suite'); ?></h3>
                <div class="export-format-grid">
                    <div class="export-format-card format-csv">
                        <input type="radio" name="export_format" id="fmt-csv" value="csv" checked>
                        <label for="fmt-csv"><span class="format-icon">📄</span>CSV</label>
                    </div>
                    <div class="export-format-card format-excel">
                        <input type="radio" name="export_format" id="fmt-excel" value="excel">
                        <label for="fmt-excel"><span class="format-icon">📊</span>Excel</label>
                    </div>
                    <div class="export-format-card format-pdf">
                        <input type="radio" name="export_format" id="fmt-pdf" value="pdf">
                        <label for="fmt-pdf"><span class="format-icon">📑</span>PDF</label>
                    </div>
                    <div class="export-format-card format-json">
                        <input type="radio" name="export_format" id="fmt-json" value="json">
                        <label for="fmt-json"><span class="format-icon">{ }</span>JSON</label>
                    </div>
                    <div class="export-format-card format-xml">
                        <input type="radio" name="export_format" id="fmt-xml" value="xml">
                        <label for="fmt-xml"><span class="format-icon">&lt;/&gt;</span>XML</label>
                    </div>
                </div>
            </div>

            <!-- ALCANCE -->
            <div class="export-section">
                <h3><span class="dashicons dashicons-filter"></span><?php _e('Datos a exportar', 'aura-suite'); ?></h3>
                <div class="export-scope-options">
                    <label class="export-scope-opt">
                        <input type="radio" name="export_scope" id="export-scope-filtered" value="filtered" checked>
                        <span>
                            <span class="scope-label" id="export-scope-filtered-label"><?php _e('Usar filtros actuales', 'aura-suite'); ?></span>
                            <span class="scope-desc"><?php _e('Solo las transacciones que coinciden con los filtros aplicados.', 'aura-suite'); ?></span>
                        </span>
                    </label>
                    <label class="export-scope-opt">
                        <input type="radio" name="export_scope" id="export-scope-all" value="all">
                        <span>
                            <span class="scope-label"><?php _e('Todas las transacciones', 'aura-suite'); ?></span>
                            <span class="scope-desc"><?php _e('Exportar toda la base de datos (respetando permisos).', 'aura-suite'); ?></span>
                        </span>
                    </label>
                    <label class="export-scope-opt">
                        <input type="radio" name="export_scope" id="export-scope-selected" value="selected">
                        <span>
                            <span class="scope-label"><?php _e('Selección actual', 'aura-suite'); ?></span>
                            <span class="scope-desc"><?php _e('Solo los registros marcados con checkbox en la tabla.', 'aura-suite'); ?></span>
                        </span>
                    </label>
                </div>
                <div id="export-scope-selected-info" style="display:none">
                    <span id="export-scope-selected-count"></span>
                </div>
            </div>

            <!-- COLUMNAS -->
            <div class="export-section" id="aura-export-columns">
                <h3><span class="dashicons dashicons-columns"></span><?php _e('Columnas a incluir (desmarcar para excluir)', 'aura-suite'); ?></h3>
                <div class="export-col-actions" style="margin-bottom: 10px; display: flex; gap: 8px;">
                    <button type="button" id="export-select-all-cols" class="button button-small"><?php _e('Seleccionar todas', 'aura-suite'); ?></button>
                    <button type="button" id="export-uncheck-all-cols" class="button button-small"><?php _e('Deseleccionar todas', 'aura-suite'); ?></button>
                    <button type="button" id="export-deselect-cols" class="button button-small"><?php _e('Básicas', 'aura-suite'); ?></button>
                </div>
                <div class="export-columns-grid">
                    <label class="export-col-item"><input type="checkbox" value="id" checked> ID</label>
                    <label class="export-col-item"><input type="checkbox" value="transaction_date" checked> <?php _e('Fecha', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="transaction_type" checked> <?php _e('Tipo', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="category_id" checked> <?php _e('Categoría', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="amount" checked> <?php _e('Monto', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="description" checked> <?php _e('Descripción', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="status" checked> <?php _e('Estado', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="payment_method" checked> <?php _e('Método de pago', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="reference_number"> <?php _e('N° Referencia', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="source_account_id"> <?php _e('Cuenta Origen', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="destination_account_id"> <?php _e('Cuenta Destino', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="recipient_payer"> <?php _e('Beneficiario/Pagador', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="receipt_file" checked> <?php _e('Comprobante / Adjunto', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="area_id"> <?php _e('Área/Programa', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="notes"> <?php _e('Notas', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="tags"> <?php _e('Etiquetas', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="related_user_id" checked> <?php _e('Usuario Vinculado', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="related_user_concept"> <?php _e('Concepto Usuario', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="created_by" checked> <?php _e('Creado por', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="approved_by" checked> <?php _e('Aprobado por', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="approved_at"> <?php _e('Fecha Aprobación', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="created_at"> <?php _e('Fecha Creación', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="updated_at"> <?php _e('Última Mod.', 'aura-suite'); ?></label>
                </div>
            </div>

            <!-- OPCIONES ADICIONALES -->
            <div class="export-section">
                <h3><span class="dashicons dashicons-admin-settings"></span><?php _e('Opciones adicionales', 'aura-suite'); ?></h3>
                <div class="export-extra-options">

                    <!-- Totales (Excel y PDF) -->
                    <div class="export-extra-opt export-opt-excel-pdf">
                        <label>
                            <input type="checkbox" id="export-opt-totals" value="1" checked>
                            <?php _e('Incluir fila de totales', 'aura-suite'); ?>
                        </label>
                    </div>

                    <!-- Incluir comprobantes físicos (ZIP) -->
                    <div class="export-extra-opt">
                        <label>
                            <input type="checkbox" id="export-opt-attachments" name="include_attachments" value="1">
                            <strong>📦 <?php _e('Incluir archivos adjuntos de comprobantes (Paquete ZIP)', 'aura-suite'); ?></strong>
                        </label>
                    </div>

                    <!-- Delimitador CSV -->
                    <div class="export-extra-opt export-opt-csv">
                        <label for="export-delimiter"><?php _e('Delimitador:', 'aura-suite'); ?></label>
                        <select name="export_delimiter" id="export-delimiter">
                            <option value=","><?php _e('Coma (,)', 'aura-suite'); ?></option>
                            <option value=";"><?php _e('Punto y coma (;)', 'aura-suite'); ?></option>
                            <option value="&#9;"><?php _e('Tab', 'aura-suite'); ?></option>
                        </select>
                    </div>

                </div>
            </div>

            <!-- PROGRESO Y ERROR -->
            <div id="aura-export-progress" style="display:none">
                <div class="export-progress-track">
                    <div id="aura-export-progress-bar"></div>
                </div>
                <div class="export-progress-label"><?php _e('Generando archivo…', 'aura-suite'); ?></div>
            </div>
            <div id="aura-export-error" style="display:none"></div>

        </div><!-- .body -->

        <div class="aura-export-modal-footer">
            <button type="button" class="button aura-export-modal-close"><?php _e('Cancelar', 'aura-suite'); ?></button>
            <button type="submit" id="aura-export-submit" class="button button-primary">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Exportar', 'aura-suite'); ?>
            </button>
        </div>
        </form>

    </div><!-- .modal-box -->
</div>
<?php endif; ?>

<!-- Modal Reasignación Masiva de Categoría -->
<div id="aura-bulk-category-modal" class="aura-modal-overlay" style="display:none;">
    <div class="aura-modal-content" style="max-width: 480px;background:#fff;border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,0.18);overflow:hidden;">
        <div class="aura-modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #e2e8f0;">
            <h2 class="aura-modal-title" style="margin:0;font-size:1.15rem;color:#0f172a;display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-tag" style="color:#4f46e5;"></span>
                <?php _e('Cambiar Categoría en Lote', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" data-close-modal style="background:none;border:none;cursor:pointer;font-size:1.4rem;">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body" style="padding:20px;">
            <p id="aura-bulk-cat-count" style="margin:0 0 14px 0;font-size:0.92rem;color:#475569;font-weight:600;">
                <?php _e('Selecciona la categoría a la cual se reasignarán las transacciones marcadas.', 'aura-suite'); ?>
            </p>

            <div class="aura-field" style="margin-bottom:16px;">
                <label for="aura-bulk-new-category" style="display:block;font-weight:600;margin-bottom:6px;font-size:0.88rem;">
                    <?php _e('Nueva Categoría / Subcategoría:', 'aura-suite'); ?> <span style="color:#ef4444;">*</span>
                </label>
                <select id="aura-bulk-new-category" class="widefat" style="height:38px;border-radius:8px;">
                    <option value=""><?php _e('-- Seleccionar Categoría --', 'aura-suite'); ?></option>
                    <?php
                    $all_cats = $wpdb->get_results(
                        "SELECT id, name, type, parent_id FROM {$wpdb->prefix}aura_finance_categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC"
                    );
                    if ($all_cats) {
                        $parent_cats = array_filter($all_cats, function($c) { return empty($c->parent_id); });
                        foreach ($parent_cats as $p) {
                            $type_lbl = $p->type === 'income' ? 'Ingreso' : ($p->type === 'expense' ? 'Egreso' : 'Ambos');
                            printf('<option value="%d">📁 %s (%s)</option>', (int)$p->id, esc_html($p->name), esc_html($type_lbl));
                            $sub_cats = array_filter($all_cats, function($c) use ($p) { return (int)$c->parent_id === (int)$p->id; });
                            foreach ($sub_cats as $sub) {
                                $sub_type_lbl = $sub->type === 'income' ? 'Ingreso' : ($sub->type === 'expense' ? 'Egreso' : 'Ambos');
                                printf('<option value="%d">&nbsp;&nbsp;└─ 📄 %s (%s)</option>', (int)$sub->id, esc_html($sub->name), esc_html($sub_type_lbl));
                            }
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:10px 12px;border-radius:8px;font-size:0.84rem;">
                ℹ️ <?php _e('Esta acción actualizará la categoría en todas las transacciones seleccionadas y registrará la modificación en la bitácora de auditoría.', 'aura-suite'); ?>
            </div>
        </div>

        <div class="aura-modal-footer" style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
            <button type="button" class="button button-secondary" data-close-modal><?php _e('Cancelar', 'aura-suite'); ?></button>
            <button type="button" class="button button-primary" id="aura-confirm-bulk-category-btn" style="background:#4f46e5;border-color:#4f46e5;font-weight:600;">
                <span class="dashicons dashicons-yes" style="vertical-align:middle;"></span>
                <?php _e('Aplicar a Seleccionadas', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal Edición Masiva Empresarial -->
<?php if (current_user_can('aura_finance_bulk_edit') || current_user_can('manage_options')): ?>
<div id="aura-bulk-edit-modal" class="aura-modal-overlay" style="display:none;z-index:999999;">
    <div class="aura-modal-content" style="max-width: 620px;background:#fff;border-radius:14px;box-shadow:0 25px 50px rgba(0,0,0,0.22);overflow:hidden;">
        <div class="aura-modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <div>
                <h2 class="aura-modal-title" style="margin:0;font-size:1.15rem;color:#0f172a;display:flex;align-items:center;gap:8px;font-weight:700;">
                    <span class="dashicons dashicons-edit" style="color:#2563eb;font-size:22px;line-height:1;"></span>
                    <?php _e('Edición Masiva de Transacciones', 'aura-suite'); ?>
                </h2>
                <p id="aura-bulk-edit-count-desc" style="margin:4px 0 0 0;font-size:0.83rem;color:#64748b;">
                    <?php _e('Aplica cambios a <strong id="aura-bulk-edit-count-num" style="color:#0f172a;">0</strong> transacciones seleccionadas.', 'aura-suite'); ?>
                </p>
            </div>
            <button type="button" class="aura-modal-close" data-close-bulk-edit style="background:none;border:none;cursor:pointer;font-size:1.4rem;color:#64748b;">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body" style="padding:20px 22px;max-height:70vh;overflow-y:auto;">
            <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:10px 14px;border-radius:8px;font-size:0.83rem;margin-bottom:18px;">
                ℹ️ <strong><?php _e('Regla de Seguridad:', 'aura-suite'); ?></strong> <?php _e('Solo se modificarán los campos cuya casilla esté activada. Los campos sin marcar conservarán sus valores intactos en cada transacción.', 'aura-suite'); ?>
            </div>

            <!-- Campo 1: Área / Programa -->
            <div class="aura-bulk-field-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.92rem;color:#1e293b;margin-bottom:8px;">
                    <input type="checkbox" id="aura-bulk-chk-area" value="1" style="margin:0;width:18px;height:18px;accent-color:#2563eb;">
                    <span>🏢 <?php _e('Cambiar Área / Programa', 'aura-suite'); ?></span>
                </label>
                <div id="aura-bulk-wrap-area" style="opacity:0.45;pointer-events:none;transition:all 0.2s ease;">
                    <select id="aura-bulk-val-area" class="widefat" style="height:38px;border-radius:8px;">
                        <option value="0">🏢 <?php _e('General (Sin área asignada)', 'aura-suite'); ?></option>
                        <?php
                        $all_areas = $wpdb->get_results("SELECT id, name, color, type FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
                        if (empty($all_areas) && class_exists('Aura_Areas_Setup')) {
                            $all_areas = Aura_Areas_Setup::get_active_areas();
                        }
                        if ($all_areas) {
                            foreach ($all_areas as $area) {
                                printf('<option value="%d">🏢 %s</option>', (int)$area->id, esc_html($area->name));
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Campo 2: Método de Pago -->
            <div class="aura-bulk-field-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.92rem;color:#1e293b;margin-bottom:8px;">
                    <input type="checkbox" id="aura-bulk-chk-payment" value="1" style="margin:0;width:18px;height:18px;accent-color:#2563eb;">
                    <span>💳 <?php _e('Cambiar Método de Pago', 'aura-suite'); ?></span>
                </label>
                <div id="aura-bulk-wrap-payment" style="opacity:0.45;pointer-events:none;transition:all 0.2s ease;">
                    <select id="aura-bulk-val-payment" class="widefat" style="height:38px;border-radius:8px;">
                        <option value="transfer">🏦 <?php _e('Transferencia Bancaria', 'aura-suite'); ?></option>
                        <option value="cash">💵 <?php _e('Efectivo', 'aura-suite'); ?></option>
                        <option value="card">💳 <?php _e('Tarjeta de Crédito / Débito', 'aura-suite'); ?></option>
                        <option value="check">📜 <?php _e('Cheque', 'aura-suite'); ?></option>
                        <option value="digital_wallet">📱 <?php _e('Billetera Digital (Nequi, DaviPlata, etc.)', 'aura-suite'); ?></option>
                        <option value="other">🔄 <?php _e('Otro Método de Pago', 'aura-suite'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Campo 3: Vinculado / Contraparte / Tercero -->
            <div class="aura-bulk-field-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.92rem;color:#1e293b;margin-bottom:8px;">
                    <input type="checkbox" id="aura-bulk-chk-related" value="1" style="margin:0;width:18px;height:18px;accent-color:#2563eb;">
                    <span>🤝 <?php _e('Cambiar Vinculado / Beneficiario / Tercero', 'aura-suite'); ?></span>
                </label>
                <div id="aura-bulk-wrap-related" style="opacity:0.45;pointer-events:none;transition:all 0.2s ease;">
                    <div style="display:flex;gap:12px;margin-bottom:10px;font-size:0.85rem;flex-wrap:wrap;">
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;">
                            <input type="radio" name="aura_bulk_related_mode" value="third_party" checked> <?php _e('Tercero / Proveedor / Persona Natural', 'aura-suite'); ?>
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;">
                            <input type="radio" name="aura_bulk_related_mode" value="user"> <?php _e('Usuario del Sistema', 'aura-suite'); ?>
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;">
                            <input type="radio" name="aura_bulk_related_mode" value="recipient_payer"> <?php _e('Texto Libre', 'aura-suite'); ?>
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;color:#ef4444;">
                            <input type="radio" name="aura_bulk_related_mode" value="none"> <?php _e('Desvincular (Vaciar)', 'aura-suite'); ?>
                        </label>
                    </div>

                    <!-- Subcontenedor Tercero -->
                    <div id="aura-bulk-related-sub-tp">
                        <select id="aura-bulk-val-third-party" class="widefat" style="height:38px;border-radius:8px;">
                            <option value="" data-party-type=""><?php _e('-- Seleccionar Tercero / Proveedor / Persona --', 'aura-suite'); ?></option>
                            <?php
                            $all_tps = $wpdb->get_results("SELECT id, full_name, commercial_name, document_id, party_type, accounting_role FROM {$wpdb->prefix}aura_finance_third_parties WHERE is_active = 1 ORDER BY party_type ASC, COALESCE(NULLIF(commercial_name, ''), full_name) ASC");
                            if ($all_tps) {
                                $tp_type_icons = array(
                                    'company'                 => '🏢',
                                    'store'                   => '🛒',
                                    'organization_foundation' => '🏛️',
                                    'person'                  => '👤',
                                    'religious'               => '⛪',
                                    'other'                   => '🔖',
                                );
                                $organizations = array();
                                $persons       = array();
                                foreach ($all_tps as $tp) {
                                    if ($tp->party_type === 'person') {
                                        $persons[] = $tp;
                                    } else {
                                        $organizations[] = $tp;
                                    }
                                }
                                if (!empty($organizations)) {
                                    echo '<optgroup label="🏢 ' . esc_attr__('Organizaciones / Entidades', 'aura-suite') . '">';
                                    foreach ($organizations as $tp) {
                                        $tp_name  = !empty($tp->commercial_name) ? $tp->commercial_name : $tp->full_name;
                                        $doc_str  = !empty($tp->document_id) ? " [{$tp->document_id}]" : '';
                                        $tp_icon  = $tp_type_icons[$tp->party_type] ?? '🏢';
                                        printf('<option value="%d" data-party-type="%s">%s %s%s</option>', (int)$tp->id, esc_attr($tp->party_type), $tp_icon, esc_html($tp_name), esc_html($doc_str));
                                    }
                                    echo '</optgroup>';
                                }
                                if (!empty($persons)) {
                                    echo '<optgroup label="👤 ' . esc_attr__('Personas Naturales', 'aura-suite') . '">';
                                    foreach ($persons as $tp) {
                                        $tp_name = !empty($tp->full_name) ? $tp->full_name : $tp->commercial_name;
                                        $doc_str = !empty($tp->document_id) ? " [{$tp->document_id}]" : '';
                                        printf('<option value="%d" data-party-type="person">👤 %s%s</option>', (int)$tp->id, esc_html($tp_name), esc_html($doc_str));
                                    }
                                    echo '</optgroup>';
                                }

                            }
                            ?>
                        </select>

                        <!-- Selector dinámico de concepto para Persona Natural -->
                        <div id="aura-bulk-tp-concept-wrapper" style="display:none;margin-top:10px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;padding:10px 12px;">
                            <label for="aura-bulk-val-third-party-concept" style="display:block;font-weight:600;font-size:0.84rem;color:#334155;margin-bottom:5px;">
                                📌 <?php _e('Concepto de Vinculación para Persona Natural:', 'aura-suite'); ?>
                            </label>
                            <select id="aura-bulk-val-third-party-concept" class="widefat" style="height:36px;border-radius:6px;">
                                <option value=""><?php _e('— Sin concepto específico —', 'aura-suite'); ?></option>
                                <option value="salary">💼 <?php _e('Pago de Salario / Honorarios / Nómina', 'aura-suite'); ?></option>
                                <option value="expense_reimbursement">🧾 <?php _e('Reembolso de Gastos', 'aura-suite'); ?></option>
                                <option value="loan_payment">💳 <?php _e('Pago / Abono de Préstamo', 'aura-suite'); ?></option>
                                <option value="scholarship">🎓 <?php _e('Beca / Apoyo Económico', 'aura-suite'); ?></option>
                                <option value="refund">↩️ <?php _e('Reembolso / Devolución', 'aura-suite'); ?></option>
                                <option value="payment_to_user">💸 <?php _e('Pago Realizado', 'aura-suite'); ?></option>
                                <option value="charge_to_user">💰 <?php _e('Cobro Realizado', 'aura-suite'); ?></option>
                                <option value="other">📌 <?php _e('Otro Concepto', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Subcontenedor Usuario -->
                    <div id="aura-bulk-related-sub-user" style="display:none;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <select id="aura-bulk-val-related-user" class="widefat" style="height:38px;border-radius:8px;">
                                <option value=""><?php _e('-- Seleccionar Usuario --', 'aura-suite'); ?></option>
                                <?php
                                $wp_users = get_users(array('fields' => array('ID', 'display_name', 'user_email'), 'orderby' => 'display_name'));
                                if ($wp_users) {
                                    foreach ($wp_users as $u) {
                                        printf('<option value="%d">%s (%s)</option>', (int)$u->ID, esc_html($u->display_name), esc_html($u->user_email));
                                    }
                                }
                                ?>
                            </select>
                            <select id="aura-bulk-val-related-concept" class="widefat" style="height:38px;border-radius:8px;">
                                <option value="none"><?php _e('Concepto: Sin especificar', 'aura-suite'); ?></option>
                                <option value="loan"><?php _e('Préstamo', 'aura-suite'); ?></option>
                                <option value="reimbursement"><?php _e('Reembolso de gastos', 'aura-suite'); ?></option>
                                <option value="advance"><?php _e('Anticipo', 'aura-suite'); ?></option>
                                <option value="payroll"><?php _e('Pago de Nómina / Honorarios', 'aura-suite'); ?></option>
                                <option value="other"><?php _e('Otro concepto', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Subcontenedor Texto Libre -->
                    <div id="aura-bulk-related-sub-text" style="display:none;">
                        <input type="text" id="aura-bulk-val-recipient-payer" class="widefat" placeholder="<?php esc_attr_e('Nombre de la persona o beneficiario...', 'aura-suite'); ?>" style="height:38px;border-radius:8px;">
                    </div>
                </div>
            </div>

            <!-- Campo 4: Creado por (Autor) -->
            <div class="aura-bulk-field-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.92rem;color:#1e293b;margin-bottom:8px;">
                    <input type="checkbox" id="aura-bulk-chk-creator" value="1" style="margin:0;width:18px;height:18px;accent-color:#2563eb;">
                    <span>👤 <?php _e('Cambiar Usuario Creador (Crea)', 'aura-suite'); ?></span>
                </label>
                <div id="aura-bulk-wrap-creator" style="opacity:0.45;pointer-events:none;transition:all 0.2s ease;">
                    <select id="aura-bulk-val-creator" class="widefat" style="height:38px;border-radius:8px;">
                        <option value=""><?php _e('-- Seleccionar Nuevo Creador --', 'aura-suite'); ?></option>
                        <?php
                        if (!empty($wp_users)) {
                            foreach ($wp_users as $u) {
                                printf('<option value="%d">%s (%s)</option>', (int)$u->ID, esc_html($u->display_name), esc_html($u->user_email));
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Campo 5: Categoría Financiera -->
            <div class="aura-bulk-field-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.92rem;color:#1e293b;margin-bottom:8px;">
                    <input type="checkbox" id="aura-bulk-chk-category" value="1" style="margin:0;width:18px;height:18px;accent-color:#2563eb;">
                    <span>📁 <?php _e('Cambiar Categoría / Subcategoría', 'aura-suite'); ?></span>
                </label>
                <div id="aura-bulk-wrap-category" style="opacity:0.45;pointer-events:none;transition:all 0.2s ease;">
                    <select id="aura-bulk-val-category" class="widefat" style="height:38px;border-radius:8px;">
                        <option value=""><?php _e('-- Seleccionar Categoría --', 'aura-suite'); ?></option>
                        <?php
                        if ($all_cats) {
                            $parent_cats = array_filter($all_cats, function($c) { return empty($c->parent_id); });
                            foreach ($parent_cats as $p) {
                                $type_lbl = $p->type === 'income' ? 'Ingreso' : ($p->type === 'expense' ? 'Egreso' : 'Ambos');
                                printf('<option value="%d">📁 %s (%s)</option>', (int)$p->id, esc_html($p->name), esc_html($type_lbl));
                                $sub_cats = array_filter($all_cats, function($c) use ($p) { return (int)$c->parent_id === (int)$p->id; });
                                foreach ($sub_cats as $sub) {
                                    $sub_type_lbl = $sub->type === 'income' ? 'Ingreso' : ($sub->type === 'expense' ? 'Egreso' : 'Ambos');
                                    printf('<option value="%d">&nbsp;&nbsp;└─ 📄 %s (%s)</option>', (int)$sub->id, esc_html($sub->name), esc_html($sub_type_lbl));
                                }
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Campo 6: Etiquetas (#tags) -->
            <div class="aura-bulk-field-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:0.92rem;color:#1e293b;margin-bottom:8px;">
                    <input type="checkbox" id="aura-bulk-chk-tags" value="1" style="margin:0;width:18px;height:18px;accent-color:#2563eb;">
                    <span>🏷️ <?php _e('Gestionar Etiquetas (#tags)', 'aura-suite'); ?></span>
                </label>
                <div id="aura-bulk-wrap-tags" style="opacity:0.45;pointer-events:none;transition:all 0.2s ease;">
                    <div style="display:flex;gap:12px;margin-bottom:10px;font-size:0.85rem;flex-wrap:wrap;">
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;">
                            <input type="radio" name="aura_bulk_tags_mode" value="replace" checked> <?php _e('Reemplazar etiquetas', 'aura-suite'); ?>
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;">
                            <input type="radio" name="aura_bulk_tags_mode" value="append"> <?php _e('Añadir a existentes (Conservar actuales)', 'aura-suite'); ?>
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;">
                            <input type="radio" name="aura_bulk_tags_mode" value="remove"> <?php _e('Quitar etiquetas específicas', 'aura-suite'); ?>
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;color:#ef4444;">
                            <input type="radio" name="aura_bulk_tags_mode" value="clear"> <?php _e('Vaciar todas las etiquetas', 'aura-suite'); ?>
                        </label>
                    </div>

                    <div id="aura-bulk-tags-selector-wrap">
                        <div class="aura-tags-manager-box" id="aura-bulk-tags-manager-box">
                            <div class="aura-tags-input-container" id="aura-bulk-tags-input-container">
                                <div id="aura-bulk-selected-tags-chips" class="aura-selected-tags-chips" style="display:none;"></div>
                                <div class="aura-tag-input-inline-wrap">
                                    <input 
                                        type="text" 
                                        id="aura-bulk-tag-new-input" 
                                        class="aura-tag-inline-field"
                                        placeholder="<?php esc_attr_e('Escribe una etiqueta y presiona Enter o coma...', 'aura-suite'); ?>"
                                        autocomplete="off">
                                    <button type="button" id="btn-bulk-add-tag-chip" class="aura-btn-add-tag" title="<?php esc_attr_e('Añadir etiqueta', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-plus"></span>
                                        <span><?php _e('Añadir', 'aura-suite'); ?></span>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="aura-bulk-val-tags" name="bulk_tags" value="">

                            <?php if (!empty($available_tags)) : ?>
                            <div class="aura-tags-suggestions-panel" style="margin-top:6px;">
                                <div class="aura-tags-suggestions-header">
                                    <span class="dashicons dashicons-randomize" style="font-size:13px;width:13px;height:13px;"></span>
                                    <span><?php _e('Catálogo rápido (clic para seleccionar/deseleccionar):', 'aura-suite'); ?></span>
                                </div>
                                <div class="aura-tags-cloud-pills" id="aura-bulk-available-tags-pills">
                                    <?php foreach ($available_tags as $tag_name => $tag_count) : ?>
                                    <button type="button" class="aura-tag-pill-btn" data-tag="<?php echo esc_attr($tag_name); ?>">
                                        <span class="tag-hash">#</span><span class="tag-txt"><?php echo esc_html($tag_name); ?></span>
                                        <span class="tag-cnt"><?php echo esc_html($tag_count); ?></span>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="aura-modal-footer" style="display:flex;justify-content:flex-end;gap:10px;padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;">
            <button type="button" class="button button-secondary" data-close-bulk-edit><?php _e('Cancelar', 'aura-suite'); ?></button>
            <button type="button" class="button button-primary" id="aura-confirm-bulk-edit-btn" style="background:#2563eb;border-color:#2563eb;font-weight:600;padding:4px 16px;height:36px;">
                <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:2px;"></span>
                <?php _e('Guardar Cambios Masivos', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Incluir modal de detalle de transacción
include(AURA_PLUGIN_DIR . 'templates/financial/transaction-modal.php');
?>
