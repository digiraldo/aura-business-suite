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

?>

<div class="wrap aura-transactions-list-page">
    <h1 class="wp-heading-inline">
        <?php _e('Transacciones Financieras', 'aura-suite'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=aura-financial-new-transaction'); ?>" class="page-title-action">
        <?php _e('Nueva Transacción', 'aura-suite'); ?>
    </a>

    <?php if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all') || current_user_can('manage_options')): ?>
    <button type="button" id="aura-export-btn" class="page-title-action">
        <span class="dashicons dashicons-download"></span>
        <?php _e('Exportar', 'aura-suite'); ?>
    </button>
    <?php endif; ?>

    <input type="hidden" id="aura-filtered-count" value="<?php echo intval($stats['count'] ?? 0); ?>">

    <hr class="wp-header-end">
    
    <!-- Estadísticas en cabecera -->
    <div class="aura-stats-header">
        <div class="aura-stat-card aura-stat-income">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Total Ingresos', 'aura-suite'); ?></div>
                <div class="stat-value">$<?php echo number_format($stats['total_income'], 2, '.', ','); ?></div>
            </div>
        </div>
        
        <div class="aura-stat-card aura-stat-expense">
            <div class="stat-icon">💸</div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Total Egresos', 'aura-suite'); ?></div>
                <div class="stat-value">$<?php echo number_format($stats['total_expense'], 2, '.', ','); ?></div>
            </div>
        </div>
        
        <div class="aura-stat-card aura-stat-balance <?php echo $stats['balance'] >= 0 ? 'positive' : 'negative'; ?>">
            <div class="stat-icon"><?php echo $stats['balance'] >= 0 ? '📈' : '📉'; ?></div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Balance', 'aura-suite'); ?></div>
                <div class="stat-value">$<?php echo number_format($stats['balance'], 2, '.', ','); ?></div>
            </div>
        </div>
        
        <div class="aura-stat-card aura-stat-count">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-label"><?php _e('Transacciones', 'aura-suite'); ?></div>
                <div class="stat-value"><?php echo number_format($stats['count']); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Contenedor principal con sidebar de filtros -->
    <div class="aura-transactions-page">
        
        <!-- Sidebar de filtros (colapsable) -->
        <aside id="aura-filters-sidebar">
            <div class="aura-filters-header">
                <h3><?php _e('Filtros', 'aura-suite'); ?></h3>
                <button type="button" class="button-link" id="toggle-filters">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
            </div>
            
            <div class="aura-filters-body">
                <form method="get" id="aura-filters-form">
                    <input type="hidden" name="page" value="aura-financial-transactions">
                    
                    <!-- Filtros Básicos -->
                    <div class="filter-section">
                        <h4><?php _e('Filtros Básicos', 'aura-suite'); ?></h4>
                    
                    <!-- Rango de fechas -->
                    <div class="filter-group">
                        <label><?php _e('Rango de Fechas', 'aura-suite'); ?></label>
                        <input type="text" 
                               name="filter_date_from" 
                               id="filter_date_from" 
                               class="aura-datepicker" 
                               placeholder="<?php _e('Desde', 'aura-suite'); ?>"
                               value="<?php echo esc_attr($_GET['filter_date_from'] ?? ''); ?>">
                        <input type="text" 
                               name="filter_date_to" 
                               id="filter_date_to" 
                               class="aura-datepicker" 
                               placeholder="<?php _e('Hasta', 'aura-suite'); ?>"
                               value="<?php echo esc_attr($_GET['filter_date_to'] ?? ''); ?>">
                    </div>
                    
                    <!-- Tipo de transacción -->
                    <div class="filter-group">
                        <label><?php _e('Tipo', 'aura-suite'); ?></label>
                        <label class="aura-checkbox">
                            <input type="checkbox" name="filter_type" value="income" 
                                   <?php checked(!empty($_GET['filter_type']) && $_GET['filter_type'] === 'income'); ?>>
                            <span><?php _e('Ingresos', 'aura-suite'); ?></span>
                        </label>
                        <label class="aura-checkbox">
                            <input type="checkbox" name="filter_type" value="expense" 
                                   <?php checked(!empty($_GET['filter_type']) && $_GET['filter_type'] === 'expense'); ?>>
                            <span><?php _e('Egresos', 'aura-suite'); ?></span>
                        </label>
                    </div>
                    
                    <!-- Estado -->
                    <div class="filter-group">
                        <label><?php _e('Estado', 'aura-suite'); ?></label>
                        <label class="aura-checkbox">
                            <input type="checkbox" name="filter_status[]" value="pending"
                                   <?php checked(in_array('pending', $_GET['filter_status'] ?? [])); ?>>
                            <span><?php _e('Pendiente', 'aura-suite'); ?></span>
                        </label>
                        <label class="aura-checkbox">
                            <input type="checkbox" name="filter_status[]" value="approved"
                                   <?php checked(in_array('approved', $_GET['filter_status'] ?? [])); ?>>
                            <span><?php _e('Aprobado', 'aura-suite'); ?></span>
                        </label>
                        <label class="aura-checkbox">
                            <input type="checkbox" name="filter_status[]" value="rejected"
                                   <?php checked(in_array('rejected', $_GET['filter_status'] ?? [])); ?>>
                            <span><?php _e('Rechazado', 'aura-suite'); ?></span>
                        </label>
                    </div>
                    
                    <!-- Categoría -->
                    <div class="filter-group">
                        <label><?php _e('Categoría', 'aura-suite'); ?></label>
                        <select name="filter_category" id="filter_category" class="aura-select2">
                            <option value=""><?php _e('Todas las categorías', 'aura-suite'); ?></option>
                            <?php
                            global $wpdb;
                            $categories = $wpdb->get_results("
                                SELECT id, name, parent_id 
                                FROM {$wpdb->prefix}aura_finance_categories 
                                WHERE is_active = 1 
                                ORDER BY display_order ASC, name ASC
                            ");
                            
                            foreach ($categories as $category) {
                                $selected = selected(!empty($_GET['filter_category']) && $_GET['filter_category'] == $category->id, true, false);
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
                
                <!-- Filtros Avanzados -->
                <div class="filter-section">
                    <h4><?php _e('Filtros Avanzados', 'aura-suite'); ?></h4>
                    
                    <!-- Rango de monto -->
                    <div class="filter-group">
                        <label><?php _e('Rango de Monto', 'aura-suite'); ?></label>
                        <input type="number" 
                               name="filter_amount_min" 
                               placeholder="<?php _e('Mínimo', 'aura-suite'); ?>"
                               step="0.01"
                               value="<?php echo esc_attr($_GET['filter_amount_min'] ?? ''); ?>">
                        <input type="number" 
                               name="filter_amount_max" 
                               placeholder="<?php _e('Máximo', 'aura-suite'); ?>"
                               step="0.01"
                               value="<?php echo esc_attr($_GET['filter_amount_max'] ?? ''); ?>">
                    </div>
                    
                    <!-- Método de pago -->
                    <div class="filter-group">
                        <label><?php _e('Método de Pago', 'aura-suite'); ?></label>
                        <select name="filter_payment_method" id="filter_payment_method">
                            <option value=""><?php _e('Todos', 'aura-suite'); ?></option>
                            <option value="Efectivo" <?php selected($_GET['filter_payment_method'] ?? '', 'Efectivo'); ?>><?php _e('Efectivo', 'aura-suite'); ?></option>
                            <option value="Transferencia" <?php selected($_GET['filter_payment_method'] ?? '', 'Transferencia'); ?>><?php _e('Transferencia', 'aura-suite'); ?></option>
                            <option value="Cheque" <?php selected($_GET['filter_payment_method'] ?? '', 'Cheque'); ?>><?php _e('Cheque', 'aura-suite'); ?></option>
                            <option value="Tarjeta" <?php selected($_GET['filter_payment_method'] ?? '', 'Tarjeta'); ?>><?php _e('Tarjeta', 'aura-suite'); ?></option>
                        </select>
                    </div>
                    
                    <!-- Creado por (solo si tiene permiso view_all) -->
                    <?php if (current_user_can('aura_finance_view_all')) : ?>
                    <div class="filter-group">
                        <label><?php _e('Creado por', 'aura-suite'); ?></label>
                        <select name="filter_user" id="filter_user" class="aura-select2">
                            <option value=""><?php _e('Todos los usuarios', 'aura-suite'); ?></option>
                            <?php
                            $users = get_users(array('orderby' => 'display_name'));
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
                    
                    <!-- Usuario Vinculado (Fase 6, Item 6.1) -->
                    <?php if (current_user_can('aura_finance_view_all') || current_user_can('aura_finance_user_ledger')) : ?>
                    <div class="filter-group">
                        <label><?php _e('Usuario Vinculado', 'aura-suite'); ?></label>
                        <div class="aura-user-autocomplete-wrap">
                            <input type="text"
                                   id="filter_related_user_search"
                                   placeholder="<?php _e('Buscar por nombre o email...', 'aura-suite'); ?>"
                                   autocomplete="off"
                                   class="widefat"
                                   value="<?php
                                       if (!empty($_GET['filter_related_user'])) {
                                           $fu = get_userdata(intval($_GET['filter_related_user']));
                                           echo $fu ? esc_attr($fu->display_name) : '';
                                       }
                                   ?>">
                            <input type="hidden"
                                   id="filter_related_user"
                                   name="filter_related_user"
                                   value="<?php echo esc_attr($_GET['filter_related_user'] ?? ''); ?>">
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Fase 8.2: Filtro por Área / Programa -->
                    <?php
                    $_tx_areas = $wpdb->get_results(
                        "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order, name"
                    );
                    $_tx_view_own = (
                        current_user_can( 'aura_areas_view_own' )
                        && ! current_user_can( 'aura_areas_view_all' )
                        && ! current_user_can( 'manage_options' )
                    );
                    if ( ! empty( $_tx_areas ) ) :
                    ?>
                    <div class="filter-group">
                        <label><?php _e('Área / Programa', 'aura-suite'); ?></label>
                        <select name="filter_area" id="filter_area" <?php echo $_tx_view_own ? 'disabled' : ''; ?>>
                            <option value=""><?php _e('Todas las áreas', 'aura-suite'); ?></option>
                            <?php foreach ( $_tx_areas as $_ta ) : ?>
                            <option value="<?php echo (int) $_ta->id; ?>"
                                style="padding-left:6px;"
                                <?php selected( (string)(int)($_GET['filter_area'] ?? 0), (string)(int)$_ta->id ); ?>>
                                <?php echo esc_html( $_ta->name ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( $_tx_view_own ) : ?>
                            <input type="hidden" name="filter_area" value="">
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="filter-section">
                    <h4><?php _e('Filtros Guardados', 'aura-suite'); ?></h4>
                    
                    <div class="filter-group">
                        <select id="load-filter-preset" class="widefat">
                            <option value=""><?php _e('Selecciona un filtro', 'aura-suite'); ?></option>
                            <option value="this_month"><?php _e('Este mes', 'aura-suite'); ?></option>
                            <option value="pending"><?php _e('Pendientes de aprobación', 'aura-suite'); ?></option>
                            <option value="my_transactions"><?php _e('Mis transacciones', 'aura-suite'); ?></option>
                            <option value="high_amount"><?php _e('Gastos mayores a $1000', 'aura-suite'); ?></option>
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
                            <span class="dashicons dashicons-star-filled" style="font-size: 14px; margin-right: 4px; vertical-align: text-top;"></span>
                            <?php _e('Guardar filtros', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="filter-actions">
                    <button type="submit" class="button button-primary widefat">
                        <span class="dashicons dashicons-filter" style="font-size: 14px; margin-right: 4px; vertical-align: text-top;"></span>
                        <?php _e('Aplicar', 'aura-suite'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="button button-secondary widefat">
                        <span class="dashicons dashicons-dismiss" style="font-size: 14px; margin-right: 4px; vertical-align: text-top;"></span>
                        <?php _e('Limpiar', 'aura-suite'); ?>
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
                
                <form method="get" class="search-form">
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
                <?php
                $transactions_list->display();
                ?>
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
                <h3><span class="dashicons dashicons-columns"></span><?php _e('Columnas a incluir', 'aura-suite'); ?></h3>
                <div class="export-col-actions">
                    <button type="button" id="export-select-all-cols" class="button button-small"><?php _e('Seleccionar todas', 'aura-suite'); ?></button>
                    <button type="button" id="export-deselect-cols" class="button button-small"><?php _e('Básicas', 'aura-suite'); ?></button>
                </div>
                <div class="export-columns-grid">
                    <label class="export-col-item"><input type="checkbox" value="id" checked> ID</label>
                    <label class="export-col-item"><input type="checkbox" value="transaction_date" checked> <?php _e('Fecha', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="transaction_type" checked> <?php _e('Tipo', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="category" checked> <?php _e('Categoría', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="amount" checked> <?php _e('Monto', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="description" checked> <?php _e('Descripción', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="status" checked> <?php _e('Estado', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="notes"> <?php _e('Notas', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="payment_method"> <?php _e('Método pago', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="reference"> <?php _e('Referencia', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="created_by"> <?php _e('Creado por', 'aura-suite'); ?></label>
                    <label class="export-col-item"><input type="checkbox" value="approved_by"> <?php _e('Aprobado por', 'aura-suite'); ?></label>
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

<?php
// Incluir modal de detalle de transacción
include(AURA_PLUGIN_DIR . 'templates/financial/transaction-modal.php');
?>
