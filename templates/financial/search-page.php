<?php
/**
 * Página de Búsqueda Avanzada de Transacciones — Aura Business Suite
 * Optimizado visual y estructuralmente según directrices de prompt-maestro.md.
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$search_nonce  = wp_create_nonce( 'aura_search_nonce' );
$tags_nonce    = wp_create_nonce( 'aura_tags_nonce' );
$currency_sym  = get_option( 'aura_finance_currency', 'USD' );

// Usuarios para "creado por" — solo usuarios con caps financieras de Aura Suite
$users = Aura_Roles_Manager::get_aura_users( [ 'fields' => [ 'ID', 'display_name' ] ], 'aura_finance_' );

// Categorías financieras
global $wpdb;
$categories = $wpdb->get_results(
    "SELECT id, name, color, icon FROM {$wpdb->prefix}aura_finance_categories
     WHERE is_active = 1 ORDER BY name ASC"
);
?>
<div class="wrap aura-search-wrap">

    <!-- ── 1. Hero Header Banner ──────────────────────────────────── -->
    <div class="aura-hero-header">
        <div class="aura-hero-content">
            <div class="aura-hero-icon-box">
                <span class="dashicons dashicons-search"></span>
            </div>
            <div class="aura-hero-titles">
                <div class="aura-hero-subtitle-row">
                    <span class="aura-tag-badge">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e( 'Aura Finanzas', 'aura-suite' ); ?>
                    </span>
                    <span class="aura-tag-badge aura-tag-secondary">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Motor de Auditoría', 'aura-suite' ); ?>
                    </span>
                </div>
                <h1 class="aura-hero-title">
                    <?php esc_html_e( 'Búsqueda Avanzada de Transacciones', 'aura-suite' ); ?>
                </h1>
                <p class="aura-hero-desc">
                    <?php esc_html_e( 'Consulta y audita movimientos contables con filtros cruzados, sintaxis booleana inteligente, rangos temporales predeterminados y exportación instantánea.', 'aura-suite' ); ?>
                </p>
            </div>
        </div>
        <div class="aura-hero-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-transactions' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="<?php esc_attr_e( 'Ir al listado general de transacciones', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-list-view"></span>
                <span><?php esc_html_e( 'Transacciones', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-tags' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="<?php esc_attr_e( 'Administrar y consolidar etiquetas', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-tag"></span>
                <span><?php esc_html_e( 'Etiquetas', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-user-ledger' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="<?php esc_attr_e( 'Ver balances y estados de cuenta por usuario', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-book"></span>
                <span><?php esc_html_e( 'Libro Mayor', 'aura-suite' ); ?></span>
            </a>
        </div>
    </div>

    <!-- ── 2. KPI Summary Cards ────────────────────────────────────── -->
    <div class="aura-kpi-grid" style="margin-bottom: 24px;">
        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #3b82f6;">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Total Coincidencias', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Cantidad de transacciones que cumplen los criterios actuales', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #2563eb;">
                    <span id="kpi-search-count">0</span>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Movimientos encontrados', 'aura-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Volumen Total Filtrado', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Suma monetaria total de los registros filtrados', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #059669;">
                    <span id="kpi-search-amount">$0.00</span>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Suma absoluta acumulada', 'aura-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #f59e0b;">
                <span class="dashicons dashicons-filter"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Criterios Activos', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtros aplicados en la consulta actual', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #d97706;">
                    <span id="kpi-search-filters-count">0</span>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Filtros configurados', 'aura-suite' ); ?></div>
            </div>
        </div>
    </div>

    <!-- ── 3. Layout de Búsqueda con Sidebar Colapsable ───────────── -->
    <div class="aura-search-layout" id="aura-search-layout">

        <!-- Panel lateral de filtros (Sidebar) -->
        <aside class="aura-search-sidebar" id="aura-search-sidebar">

            <!-- Búsquedas guardadas -->
            <div class="aura-card" id="saved-searches-panel" style="margin-bottom: 20px;">
                <div class="aura-card-header" style="padding: 14px 18px; display:flex; justify-content:space-between; align-items:center;">
                    <div class="aura-card-title-group">
                        <h3 style="font-size: 14px; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-star-filled" style="color: #f59e0b;"></span>
                            <?php esc_html_e( 'Búsquedas Guardadas', 'aura-suite' ); ?>
                        </h3>
                    </div>
                </div>
                <div class="aura-card-body" style="padding: 12px 18px;">
                    <ul id="saved-searches-list" class="aura-saved-searches-list" style="margin: 0; padding: 0; list-style: none;">
                        <li><em style="color:#94a3b8; font-size:12.5px;"><?php esc_html_e( 'Cargando consultas...', 'aura-suite' ); ?></em></li>
                    </ul>
                </div>
            </div>

            <!-- Formulario de Filtros -->
            <div class="aura-card aura-filters-card">
                <div class="aura-card-header aura-filters-header" style="padding: 14px 18px; display:flex; justify-content:space-between; align-items:center;">
                    <div class="aura-card-title-group">
                        <h3 style="font-size: 14px; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-filter" style="color: #3b82f6;"></span>
                            <span><?php esc_html_e( 'Filtros de Búsqueda', 'aura-suite' ); ?></span>
                        </h3>
                    </div>
                    <button type="button" class="aura-btn-icon aura-toggle-sidebar-btn" id="toggle-filters" data-tooltip="<?php esc_attr_e( 'Ocultar panel lateral de filtros', 'aura-suite' ); ?>" style="width:28px;height:28px;">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                </div>
                <div class="aura-card-body" style="padding: 18px;">
                    <form id="aura-search-form">

                        <!-- Texto libre con operadores -->
                        <div class="aura-filter-group">
                            <label style="display:flex; justify-content:space-between; align-items:center;">
                                <span><?php esc_html_e( 'Búsqueda de texto', 'aura-suite' ); ?></span>
                                <button type="button" class="aura-syntax-toggle aura-help-icon" aria-expanded="false"
                                        aria-controls="aura-syntax-help" data-tooltip="<?php esc_attr_e( 'Ver operadores avanzados de búsqueda', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </button>
                            </label>
                            <input type="text" id="filter-text" name="text" class="aura-input"
                                   placeholder='<?php echo esc_attr( __( '"frase" -excluir tipo:egreso importe:>500', 'aura-suite' ) ); ?>'>
                            <p class="description" style="font-size:11px; color:#64748b; margin-top:4px;">
                                <?php esc_html_e( 'Soporta "frase exacta", AND, OR, -excluir y campo:valor', 'aura-suite' ); ?>
                            </p>

                            <!-- Panel de ayuda de sintaxis desplegable -->
                            <div id="aura-syntax-help" class="aura-syntax-help" hidden style="margin-top:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                                <h4 style="margin:0 0 8px 0; font-size:12px; color:#1e293b;"><?php esc_html_e( 'Operadores Disponibles', 'aura-suite' ); ?></h4>
                                <table class="aura-syntax-table" style="font-size:11px; width:100%; border-collapse:collapse;">
                                    <tbody>
                                        <tr><td><code>"frase exacta"</code></td><td><?php esc_html_e( 'Coincidencia literal', 'aura-suite' ); ?></td></tr>
                                        <tr><td><code>AND / OR</code></td><td><?php esc_html_e( 'Operadores lógicos', 'aura-suite' ); ?></td></tr>
                                        <tr><td><code>-termino</code></td><td><?php esc_html_e( 'Excluir palabra', 'aura-suite' ); ?></td></tr>
                                        <tr><td><code>tipo:egreso</code></td><td><?php esc_html_e( 'ingreso, egreso, capital', 'aura-suite' ); ?></td></tr>
                                        <tr><td><code>importe:>500</code></td><td><?php esc_html_e( 'Comparador (> < >= <= =)', 'aura-suite' ); ?></td></tr>
                                        <tr><td><code>metodo:cash</code></td><td><?php esc_html_e( 'efectivo, transferencia…', 'aura-suite' ); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ── Rango de Fechas con Presets Instantáneos ── -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Rango de Fechas', 'aura-suite' ); ?></label>

                            <!-- Barra de Atajos Predeterminados -->
                            <div class="aura-date-presets-wrapper" style="margin-bottom: 8px;">
                                <div class="aura-date-presets-pills">
                                    <button type="button" class="aura-preset-pill" data-preset="today"><?php esc_html_e( 'Hoy', 'aura-suite' ); ?></button>
                                    <button type="button" class="aura-preset-pill" data-preset="this-month"><?php esc_html_e( 'Este Mes', 'aura-suite' ); ?></button>
                                    <button type="button" class="aura-preset-pill" data-preset="last-month"><?php esc_html_e( 'Mes Anterior', 'aura-suite' ); ?></button>
                                    <button type="button" class="aura-preset-pill" data-preset="this-quarter"><?php esc_html_e( 'Trimestre', 'aura-suite' ); ?></button>
                                    <button type="button" class="aura-preset-pill" data-preset="last-semester"><?php esc_html_e( 'Semestre', 'aura-suite' ); ?></button>
                                    <button type="button" class="aura-preset-pill" data-preset="this-year"><?php esc_html_e( 'Todo el Año', 'aura-suite' ); ?></button>
                                    <button type="button" class="aura-preset-pill is-active" data-preset="all"><?php esc_html_e( 'Histórico', 'aura-suite' ); ?></button>
                                </div>
                            </div>

                            <div class="aura-date-range" style="display:flex; align-items:center; gap:6px;">
                                <input type="date" name="date_from" id="filter-date-from" class="aura-input" style="flex:1;">
                                <span style="color:#94a3b8; font-weight:700;">—</span>
                                <input type="date" name="date_to" id="filter-date-to" class="aura-input" style="flex:1;">
                            </div>
                        </div>

                        <!-- Tipo de Transacción -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Tipo de Transacción', 'aura-suite' ); ?></label>
                            <div class="aura-seg-btn-group" data-target="types">
                                <label class="aura-seg-btn is-active"><input type="checkbox" name="types[]" value="income" checked> 🟢 <?php esc_html_e( 'Ingresos', 'aura-suite' ); ?></label>
                                <label class="aura-seg-btn is-active"><input type="checkbox" name="types[]" value="expense" checked> 🔴 <?php esc_html_e( 'Gastos', 'aura-suite' ); ?></label>
                                <label class="aura-seg-btn is-active"><input type="checkbox" name="types[]" value="capital" checked> 🔵 <?php esc_html_e( 'Gastos de Capital', 'aura-suite' ); ?></label>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Estado de Aprobación', 'aura-suite' ); ?></label>
                            <div class="aura-seg-btn-group" data-target="statuses">
                                <label class="aura-seg-btn is-active"><input type="checkbox" name="statuses[]" value="approved" checked> <?php esc_html_e( 'Aprobados', 'aura-suite' ); ?></label>
                                <label class="aura-seg-btn is-active"><input type="checkbox" name="statuses[]" value="pending" checked> <?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></label>
                                <label class="aura-seg-btn"><input type="checkbox" name="statuses[]" value="rejected"> <?php esc_html_e( 'Rechazados', 'aura-suite' ); ?></label>
                            </div>
                        </div>

                        <!-- Categorías -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Categorías Financieras', 'aura-suite' ); ?></label>
                            <select name="categories[]" id="filter-categories" multiple size="4" class="aura-input" style="width:100%; height:auto;">
                                <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->id ); ?>">
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" style="font-size:11px; color:#64748b; margin-top:3px;">
                                <?php esc_html_e( 'Mantén presionado Ctrl para selección múltiple', 'aura-suite' ); ?>
                            </p>
                        </div>

                        <!-- Rango de Importe -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Rango de Importe ($)', 'aura-suite' ); ?></label>
                            <div class="aura-amount-range" style="display:flex; align-items:center; gap:6px;">
                                <input type="number" name="amount_min" class="aura-input" placeholder="<?php esc_attr_e( 'Mínimo', 'aura-suite' ); ?>" min="0" step="0.01" style="flex:1;">
                                <span style="color:#94a3b8; font-weight:700;">—</span>
                                <input type="number" name="amount_max" class="aura-input" placeholder="<?php esc_attr_e( 'Máximo', 'aura-suite' ); ?>" min="0" step="0.01" style="flex:1;">
                            </div>
                        </div>

                        <!-- Etiquetas con Autocomplete Chips -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Filtrar por Etiquetas (#tags)', 'aura-suite' ); ?></label>
                            <input type="text" id="filter-tags-input" class="aura-input" placeholder="<?php esc_attr_e( 'Escribe una etiqueta y presiona Enter...', 'aura-suite' ); ?>">
                            <div id="filter-tags-chips" class="aura-chips-container" style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px;"></div>
                            <input type="hidden" id="filter-tags-hidden" name="tags_json" value="[]">
                        </div>

                        <!-- Creado por -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Registrado por Usuario', 'aura-suite' ); ?></label>
                            <select name="created_by" id="filter-created-by" class="aura-input" style="width:100%;">
                                <option value=""><?php esc_html_e( '— Todos los usuarios —', 'aura-suite' ); ?></option>
                                <?php foreach ( $users as $u ) : ?>
                                <option value="<?php echo esc_attr( $u->ID ); ?>">
                                    <?php echo esc_html( $u->display_name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Comprobante -->
                        <div class="aura-filter-group">
                            <label><?php esc_html_e( 'Comprobante Adjunto', 'aura-suite' ); ?></label>
                            <select name="has_receipt" class="aura-input" style="width:100%;">
                                <option value=""><?php esc_html_e( 'Todos (con y sin comprobante)', 'aura-suite' ); ?></option>
                                <option value="yes"><?php esc_html_e( 'Solo con comprobante 📎', 'aura-suite' ); ?></option>
                                <option value="no"><?php esc_html_e( 'Sin comprobante adjunto', 'aura-suite' ); ?></option>
                            </select>
                        </div>

                        <!-- Botones de Acción de Filtro -->
                        <div class="aura-filter-buttons" style="display:flex; flex-direction:column; gap:8px; margin-top:16px;">
                            <button type="submit" id="btn-do-search" class="aura-btn aura-btn-primary" style="width:100%; justify-content:center;">
                                <span class="dashicons dashicons-search"></span>
                                <span><?php esc_html_e( 'Aplicar Búsqueda', 'aura-suite' ); ?></span>
                            </button>
                            <div style="display:flex; gap:8px;">
                                <button type="button" id="btn-clear-filters" class="aura-btn aura-btn-secondary" style="flex:1; justify-content:center;">
                                    <span><?php esc_html_e( 'Limpiar', 'aura-suite' ); ?></span>
                                </button>
                                <button type="button" id="btn-save-search" class="aura-btn" style="flex:1; justify-content:center; background:#fef3c7; border-color:#fde68a; color:#b45309;">
                                    <span class="dashicons dashicons-star-filled" style="font-size:14px;width:14px;height:14px;"></span>
                                    <span><?php esc_html_e( 'Guardar', 'aura-suite' ); ?></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Panel central de resultados -->
        <section class="aura-search-results-panel" id="search-results-panel">

            <!-- Card Contenedora de Resultados -->
            <div class="aura-card">
                <div class="aura-card-header" style="padding: 16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div class="aura-card-title-group" style="display:flex; align-items:center; gap:12px;">
                        <button type="button" id="btn-toggle-filters-top" class="aura-btn aura-btn-secondary" style="font-size:12px; padding:6px 12px;" data-tooltip="<?php esc_attr_e( 'Alternar visibilidad del panel de filtros', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-filter"></span>
                            <span id="btn-toggle-filters-text"><?php esc_html_e( 'Ocultar Filtros', 'aura-suite' ); ?></span>
                        </button>
                        <h3 style="font-size: 15px; display:flex; align-items:center; gap:8px; margin:0;">
                            <span class="dashicons dashicons-analytics" style="color:#2563eb;"></span>
                            <span><?php esc_html_e( 'Resultados de la Consulta', 'aura-suite' ); ?></span>
                        </h3>
                    </div>
                    <div class="aura-search-stats" id="search-stats" style="display:none; align-items:center; gap:12px; margin:0; padding:0; background:transparent; border:none;">
                        <button id="btn-export-search" class="aura-btn aura-btn-secondary" style="font-size:12px; padding:6px 12px;" data-tooltip="<?php esc_attr_e( 'Descargar reporte completo en formato CSV', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-download"></span>
                            <span><?php esc_html_e( 'Exportar a CSV', 'aura-suite' ); ?></span>
                        </button>
                    </div>
                </div>

                <div class="aura-card-body" style="padding: 0;">

                    <!-- Spinner de Carga -->
                    <div id="search-loading" style="display:none; text-align:center; padding:50px 20px;">
                        <span class="spinner is-active" style="float:none; margin:0 auto 12px auto; width:32px; height:32px;"></span>
                        <p style="color:#64748b; font-size:14px; font-weight:600;"><?php esc_html_e( 'Consultando base de datos contable...', 'aura-suite' ); ?></p>
                    </div>

                    <!-- Estado Vacío Limpio (Anti-Ruido según prompt-maestro.md) -->
                    <div id="search-empty" class="aura-empty-state" style="display:none; text-align:center; padding:60px 20px;">
                        <div style="margin-bottom:12px;">
                            <span class="dashicons dashicons-search" style="font-size:48px; width:48px; height:48px; color:#94a3b8;"></span>
                        </div>
                        <h4 style="margin:0 0 6px 0; color:#1e293b; font-size:16px; font-weight:700;"><?php esc_html_e( 'No se encontraron transacciones coincidentes', 'aura-suite' ); ?></h4>
                        <p style="color:#64748b; margin:0 auto; max-width:420px; font-size:13px; line-height:1.5;">
                            <?php esc_html_e( 'Intenta ampliar el rango de fechas o modificar los filtros aplicados en el panel lateral.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <div id="search-initial" style="text-align:center; padding:60px 20px;">
                        <span class="dashicons dashicons-search" style="font-size:48px; width:48px; height:48px; color:#94a3b8; margin-bottom:12px;"></span>
                        <h4 style="margin:0 0 6px 0; color:#1e293b; font-size:16px; font-weight:700;"><?php esc_html_e( 'Iniciando motor de auditoría...', 'aura-suite' ); ?></h4>
                        <p style="color:#64748b; margin:0; font-size:13px;"><?php esc_html_e( 'Cargando transacciones recientes.', 'aura-suite' ); ?></p>
                    </div>

                    <!-- Tabla de Resultados Moderna y Responsiva -->
                    <div class="aura-table-responsive-wrap">
                        <table id="search-results-table" class="aura-modern-table aura-search-table" style="display:none; width:100%;">
                            <thead>
                                <tr>
                                    <th class="column-id" style="width: 55px; text-align: center;">
                                        <span data-tooltip="<?php esc_attr_e( 'Identificador único y control de fila expandible', 'aura-suite' ); ?>">#</span>
                                    </th>
                                    <th class="column-transaction_date" style="width: 105px; text-align: center;">
                                        <span data-tooltip="<?php esc_attr_e( 'Fecha contable en que se ejecutó el movimiento', 'aura-suite' ); ?>"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></span>
                                    </th>
                                    <th class="column-description" style="width: 28%;">
                                        <span data-tooltip="<?php esc_attr_e( 'Concepto descriptivo de la operación contable', 'aura-suite' ); ?>"><?php esc_html_e( 'Concepto / Descripción', 'aura-suite' ); ?></span>
                                    </th>
                                    <th class="column-category" style="width: 15%;">
                                        <span data-tooltip="<?php esc_attr_e( 'Clasificación o categoría financiera asignada', 'aura-suite' ); ?>"><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></span>
                                    </th>
                                    <th class="column-type_status" style="width: 12%; text-align: center;">
                                        <span data-tooltip="<?php esc_attr_e( 'Tipo de flujo contable y estado de aprobación', 'aura-suite' ); ?>"><?php esc_html_e( 'Tipo / Estado', 'aura-suite' ); ?></span>
                                    </th>
                                    <th class="column-amount" style="width: 14%; text-align: right;">
                                        <span data-tooltip="<?php esc_attr_e( 'Importe monetario total del movimiento', 'aura-suite' ); ?>"><?php esc_html_e( 'Importe', 'aura-suite' ); ?></span>
                                    </th>
                                    <th class="column-tags" style="width: 14%;">
                                        <span data-tooltip="<?php esc_attr_e( 'Etiquetas (#tags) asociadas a esta transacción', 'aura-suite' ); ?>"><?php esc_html_e( 'Etiquetas', 'aura-suite' ); ?></span>
                                    </th>
                                    <th class="column-receipt" style="width: 60px; text-align: center;">
                                        <span data-tooltip="<?php esc_attr_e( 'Comprobante o factura adjunta a la transacción', 'aura-suite' ); ?>"><?php esc_html_e( 'Adj.', 'aura-suite' ); ?></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="search-results-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div id="search-pagination" class="aura-pagination-wrap" style="display:none; padding:16px 20px; border-top:1px solid #f1f5f9; display:flex; justify-content:center; gap:6px;"></div>
            </div>
        </section>
    </div>

    <!-- ── 4. Modal Universal para Guardar Búsqueda (prompt-maestro.md) ─ -->
    <div id="save-search-modal" class="aura-modal-overlay" style="display:none;">
        <div class="aura-modal-container" style="max-width: 460px;">
            <div class="aura-modal-header" style="background:#1e293b;">
                <h2>
                    <span class="dashicons dashicons-star-filled" style="color:#f59e0b;"></span>
                    <span><?php esc_html_e( 'Guardar Criterios de Búsqueda', 'aura-suite' ); ?></span>
                </h2>
                <button type="button" class="aura-modal-close" id="cancel-save-search-btn" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
            </div>
            <div class="aura-modal-body" style="padding: 20px;">
                <label style="display:block; font-weight:700; font-size:12.5px; margin-bottom:6px; color:#1e293b;">
                    <?php esc_html_e( 'Nombre identificador del preset:', 'aura-suite' ); ?>
                </label>
                <input type="text" id="save-search-name" class="aura-input" placeholder="<?php esc_attr_e( 'Ej: Egresos en Efectivo > $5,000', 'aura-suite' ); ?>" style="width:100%;">
                <p style="font-size:12px; color:#64748b; margin:6px 0 0 0;">
                    <?php esc_html_e( 'Podrás cargar esta consulta en cualquier momento desde el panel lateral.', 'aura-suite' ); ?>
                </p>
            </div>
            <div class="aura-modal-footer" style="padding: 14px 20px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" id="cancel-save-search" class="aura-btn aura-btn-secondary">
                    <span><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></span>
                </button>
                <button type="button" id="confirm-save-search" class="aura-btn aura-btn-primary">
                    <span><?php esc_html_e( 'Guardar Preset', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.auraSearchConfig = {
    ajaxUrl:     '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
    searchNonce: '<?php echo esc_js( $search_nonce ); ?>',
    tagsNonce:   '<?php echo esc_js( $tags_nonce ); ?>',
    currency:    '<?php echo esc_js( $currency_sym ); ?>',
    i18n: {
        income:       '<?php echo esc_js( __( 'Ingreso', 'aura-suite' ) ); ?>',
        expense:      '<?php echo esc_js( __( 'Gasto', 'aura-suite' ) ); ?>',
        capital:      '<?php echo esc_js( __( 'Gasto de Capital', 'aura-suite' ) ); ?>',
        pending:      '<?php echo esc_js( __( 'Pendiente', 'aura-suite' ) ); ?>',
        approved:     '<?php echo esc_js( __( 'Aprobado', 'aura-suite' ) ); ?>',
        rejected:     '<?php echo esc_js( __( 'Rechazado', 'aura-suite' ) ); ?>',
        noSaved:      '<?php echo esc_js( __( 'No hay búsquedas guardadas', 'aura-suite' ) ); ?>',
        deleteSearch: '<?php echo esc_js( __( '¿Eliminar esta búsqueda guardada?', 'aura-suite' ) ); ?>',
        saveSuccess:  '<?php echo esc_js( __( 'Búsqueda guardada con éxito', 'aura-suite' ) ); ?>',
        results:      '<?php echo esc_js( __( 'resultados', 'aura-suite' ) ); ?>',
        total:        '<?php echo esc_js( __( 'Total:', 'aura-suite' ) ); ?>',
        page:         '<?php echo esc_js( __( 'Página', 'aura-suite' ) ); ?>',
        of:           '<?php echo esc_js( __( 'de', 'aura-suite' ) ); ?>',
        hideFilters:  '<?php echo esc_js( __( 'Ocultar Filtros', 'aura-suite' ) ); ?>',
        showFilters:  '<?php echo esc_js( __( 'Mostrar Filtros', 'aura-suite' ) ); ?>'
    }
};
</script>

<?php 
if ( defined( 'AURA_PLUGIN_DIR' ) ) {
    include AURA_PLUGIN_DIR . 'templates/financial/transaction-modal.php';
}
?>
