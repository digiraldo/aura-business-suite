<?php
/**
 * Template: Listado de Préstamos
 * Fase 3 — DataTables con 9 columnas + filtros + 3 modales
 *
 * @package Aura_Business_Suite
 */

if (!defined('ABSPATH'))
    exit;

if (!current_user_can('aura_library_view_loans_own') && !current_user_can('manage_options')) {
    wp_die(esc_html__('No tienes permisos para ver esta página.', 'aura-suite'));
}

$can_create = current_user_can('aura_library_loan_create') || current_user_can('manage_options');
$can_return = current_user_can('aura_library_loan_return') || current_user_can('manage_options');
$can_extend = current_user_can('aura_library_loan_extend') || current_user_can('manage_options');
$can_edit = current_user_can('aura_library_loan_edit') || current_user_can('manage_options');
$can_delete = current_user_can('aura_library_loan_delete') || current_user_can('manage_options');
$can_view_all = current_user_can('aura_library_view_loans_all') || current_user_can('manage_options');
$can_view_fines = current_user_can('aura_library_view_fines') || current_user_can('manage_options');

$fines_enabled = (bool) get_option('aura_library_fines_enabled', false);
$fines_to_finance = (bool) get_option('aura_library_fines_to_finance', false);
$max_extensions = (int) get_option('aura_library_max_extensions', 2);
$extension_days = (int) get_option('aura_library_extension_days', 7);

global $wpdb;
$table_loans = $wpdb->prefix . 'aura_library_loans';
$loan_stats = [
    'total' => 0,
    'active' => 0,
    'overdue' => 0,
    'returned' => 0,
];
if ($wpdb->get_var("SHOW TABLES LIKE '$table_loans'") === $table_loans) {
    $row_stats = $wpdb->get_row("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('active', 'extended') THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'overdue' OR (status IN ('active', 'extended') AND due_date < CURDATE()) THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned
        FROM {$table_loans}");
    if ($row_stats) {
        $loan_stats['total'] = (int) $row_stats->total;
        $loan_stats['active'] = (int) $row_stats->active;
        $loan_stats['overdue'] = (int) $row_stats->overdue;
        $loan_stats['returned'] = (int) $row_stats->returned;
    }
}
?>
<div class="aura-app-wrapper aura-library-loans-list">
    <div class="wrap aura-app-context">

        <?php
        $header_actions = [];
        if ($can_create) {
            $header_actions[] = [
                'label' => __('Nuevo Préstamo', 'aura-suite'),
                'url' => '#',
                'class' => 'btn btn-shimmer btn-indigo btn-lift',
                'icon' => 'dashicons-plus-alt2',
                'attr' => 'id="aura-lib-btn-new-loan"',
            ];
        }

        Aura_UI::render_page_header([
            'title' => __('Préstamos de Biblioteca', 'aura-suite'),
            'subtitle' => __('Control y seguimiento de préstamos, vencimientos y devoluciones', 'aura-suite'),
            'icon' => 'dashicons-book-alt',
            'badges' => [
                ['label' => sprintf(__('%d activos', 'aura-suite'), $loan_stats['active']), 'variant' => 'blue'],
            ],
            'actions' => $header_actions,
        ]);

        Aura_UI::render_stats_grid([
            [
                'id' => 'kpi-total-loans',
                'icon' => 'dashicons-book-alt',
                'value' => number_format_i18n($loan_stats['total']),
                'label' => __('Total Préstamos', 'aura-suite'),
                'variant' => 'capital',
                'tooltip' => __('Histórico total de préstamos registrados', 'aura-suite'),
            ],
            [
                'id' => 'kpi-active-loans',
                'icon' => 'dashicons-clock',
                'value' => number_format_i18n($loan_stats['active']),
                'label' => __('Préstamos Activos', 'aura-suite'),
                'variant' => 'balance',
                'tooltip' => __('Préstamos vigentes en posesión de lectores', 'aura-suite'),
            ],
            [
                'id' => 'kpi-overdue-loans',
                'icon' => 'dashicons-warning',
                'value' => number_format_i18n($loan_stats['overdue']),
                'label' => __('Vencidos / Mora', 'aura-suite'),
                'variant' => 'expense',
                'tooltip' => __('Préstamos que superaron su fecha límite de entrega', 'aura-suite'),
            ],
            [
                'id' => 'kpi-returned-loans',
                'icon' => 'dashicons-yes-alt',
                'value' => number_format_i18n($loan_stats['returned']),
                'label' => __('Devueltos', 'aura-suite'),
                'variant' => 'income',
                'tooltip' => __('Libros devueltos con éxito a la biblioteca', 'aura-suite'),
            ],
        ]);
        ?>

        <!-- Filtros (Bootstrap 5.3 Input Groups) -->
        <div class="aura-glass-card aura-filters-card aura-lib-loans-filters">
            <div class="aura-lib-filters-grid">
                <div class="aura-lib-filter-search">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">🔍</span>
                        <input type="search" id="aura-lib-loans-search"
                            placeholder="<?php esc_attr_e('Buscar por libro, lector…', 'aura-suite'); ?>"
                            class="form-control">
                    </div>
                </div>

                <div class="aura-lib-filter-select">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">🟢</span>
                        <select id="aura-lib-loans-filter-status" class="form-control"
                            aria-label="<?php esc_attr_e('Estado', 'aura-suite'); ?>">
                            <option value=""><?php esc_html_e('Todos los estados', 'aura-suite'); ?></option>
                            <option value="active"><?php esc_html_e('Activo', 'aura-suite'); ?></option>
                            <option value="overdue"><?php esc_html_e('Vencido', 'aura-suite'); ?></option>
                            <option value="extended"><?php esc_html_e('Extendido', 'aura-suite'); ?></option>
                            <option value="returned"><?php esc_html_e('Devuelto', 'aura-suite'); ?></option>
                            <option value="lost"><?php esc_html_e('Perdido', 'aura-suite'); ?></option>
                            <option value="cancelled"><?php esc_html_e('Cancelado', 'aura-suite'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="aura-lib-filter-date">
                    <div class="input-group"
                        data-tooltip="<?php esc_attr_e('Filtrar préstamos realizados desde esta fecha', 'aura-suite'); ?>">
                        <span class="input-group-text" aria-hidden="true">📅</span>
                        <input type="date" id="aura-lib-loans-filter-from" class="form-control">
                    </div>
                </div>
                <div class="aura-lib-filter-date">
                    <div class="input-group"
                        data-tooltip="<?php esc_attr_e('Filtrar préstamos realizados hasta esta fecha', 'aura-suite'); ?>">
                        <span class="input-group-text" aria-hidden="true">📅</span>
                        <input type="date" id="aura-lib-loans-filter-to" class="form-control">
                    </div>
                </div>

                <div class="aura-lib-filter-actions">
                    <button type="button" id="aura-lib-loans-filter-apply" class="btn btn-shimmer btn-indigo btn-lift"
                        data-tooltip="<?php esc_attr_e('Aplicar búsqueda y rango de fechas', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-filter"></span>
                        <span><?php esc_html_e('Filtrar', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" id="aura-lib-loans-filter-clear" class="btn btn-glass btn-lift"
                        data-tooltip="<?php esc_attr_e('Restablecer todos los filtros activos', 'aura-suite'); ?>">
                        <span><?php esc_html_e('Limpiar', 'aura-suite'); ?></span>
                    </button>
                </div>
            </div>
        </div><!-- .aura-filters-card -->

        <!-- Mensajes de estado -->
        <div id="aura-lib-loans-notice" class="notice" style="display:none;"></div>

        <!-- Tabla DataTables en Contenedor Glass (Desktop Fluido + Mobile Cards) -->
        <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
            <table id="aura-lib-loans-table" class="aura-table display responsive aura-lib-responsive-table"
                style="width:100%;">
                <thead>
                    <tr>
                        <th># <span class="aura-help-icon" tabindex="0"
                                data-tooltip="<?php esc_attr_e('Identificador único del préstamo', 'aura-suite'); ?>">?</span>
                        </th>
                        <th><?php esc_html_e('Libro', 'aura-suite'); ?> <span class="aura-help-icon" tabindex="0"
                                data-tooltip="<?php esc_attr_e('Portada ampliable al pasar el cursor, título y código Dewey', 'aura-suite'); ?>">?</span>
                        </th>
                        <?php if ($can_view_all): ?>
                            <th><?php esc_html_e('Lector', 'aura-suite'); ?> <span class="aura-help-icon" tabindex="0"
                                    data-tooltip="<?php esc_attr_e('Contacto o usuario del sistema responsable del préstamo', 'aura-suite'); ?>">?</span>
                            </th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Fecha préstamo', 'aura-suite'); ?> <span class="aura-help-icon"
                                tabindex="0"
                                data-tooltip="<?php esc_attr_e('Fecha en que se entregó el ejemplar al lector', 'aura-suite'); ?>">?</span>
                        </th>
                        <th><?php esc_html_e('Devol. prevista', 'aura-suite'); ?> <span class="aura-help-icon"
                                tabindex="0"
                                data-tooltip="<?php esc_attr_e('Fecha límite acordada para regresar el libro', 'aura-suite'); ?>">?</span>
                        </th>
                        <th><?php esc_html_e('Estado', 'aura-suite'); ?> <span class="aura-help-icon" tabindex="0"
                                data-tooltip="<?php esc_attr_e('Estado actual del préstamo (Activo, Vencido, Extendido o Devuelto)', 'aura-suite'); ?>">?</span>
                        </th>
                        <th><?php esc_html_e('Extensiones', 'aura-suite'); ?> <span class="aura-help-icon"
                                tabindex="0"
                                data-tooltip="<?php esc_attr_e('Renovaciones utilizadas frente al máximo permitido', 'aura-suite'); ?>">?</span>
                        </th>
                        <?php if ($fines_enabled && $can_view_fines): ?>
                            <th><?php esc_html_e('Multa', 'aura-suite'); ?> <span class="aura-help-icon" tabindex="0"
                                    data-tooltip="<?php esc_attr_e('Multa acumulada o cobrada por días de mora o daño', 'aura-suite'); ?>">?</span>
                            </th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Acciones', 'aura-suite'); ?> <span class="aura-help-icon" tabindex="0"
                                data-tooltip="<?php esc_attr_e('Ver detalle, devolver, extender, editar o cancelar el préstamo', 'aura-suite'); ?>">?</span>
                        </th>
                    </tr>
                </thead>
                <tbody id="aura-lib-loans-tbody">
                    <tr id="aura-lib-loans-loading">
                        <td colspan="<?php echo esc_attr(7 + ($can_view_all ? 1 : 0) + ($fines_enabled && $can_view_fines ? 1 : 0)); ?>"
                            style="text-align:center;padding:20px;">
                            <span class="spinner is-active" style="float:none;"></span>
                            <?php esc_html_e('Cargando…', 'aura-suite'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginación -->
            <div id="aura-lib-loans-pagination" class="aura-lib-pagination"
                style="margin-top:16px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;"></div>
        </div>

        <?php
        if (class_exists('Aura_Contact_Selector')) {
            Aura_Contact_Selector::enqueue_assets();
        }
        if (file_exists(AURA_PLUGIN_DIR . 'assets/js/third-party-selector.js')) {
            $tp_ver = @filemtime(AURA_PLUGIN_DIR . 'assets/js/third-party-selector.js') ?: AURA_VERSION;
            if (!wp_script_is('aura-third-party-selector', 'registered')) {
                wp_register_script(
                    'aura-third-party-selector',
                    AURA_PLUGIN_URL . 'assets/js/third-party-selector.js',
                    ['jquery', 'jquery-ui-autocomplete'],
                    (string) $tp_ver,
                    true
                );
            }
            wp_enqueue_script('aura-third-party-selector');
            if (!wp_scripts()->get_data('aura-third-party-selector', 'data')) {
                wp_localize_script('aura-third-party-selector', 'auraCounterpartiesData', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('aura_search_counterparties_nonce'),
                ]);
            }
        }
        $loans_js_ver = file_exists(AURA_PLUGIN_DIR . 'assets/js/library-loans.js') ? AURA_VERSION . '.' . filemtime(AURA_PLUGIN_DIR . 'assets/js/library-loans.js') : AURA_VERSION;
        $loans_css_ver = file_exists(AURA_PLUGIN_DIR . 'assets/css/library-loans.css') ? AURA_VERSION . '.' . filemtime(AURA_PLUGIN_DIR . 'assets/css/library-loans.css') : AURA_VERSION;

        $loans_js_deps = ['jquery', 'jquery-ui-autocomplete', 'aura-ui-core', 'wp-i18n'];
        if (wp_script_is('datatables-responsive-js', 'registered')) {
            $loans_js_deps[] = 'datatables-responsive-js';
        }
        if (wp_script_is('aura-third-party-selector', 'registered')) {
            $loans_js_deps[] = 'aura-third-party-selector';
        }

        wp_enqueue_script(
            'aura-lib-loans',
            AURA_PLUGIN_URL . 'assets/js/library-loans.js',
            $loans_js_deps,
            $loans_js_ver,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('aura-lib-loans', 'aura-suite', AURA_PLUGIN_DIR . 'languages');
        }
        wp_enqueue_style(
            'aura-lib-loans-css',
            AURA_PLUGIN_URL . 'assets/css/library-loans.css',
            ['aura-design-system'],
            $loans_css_ver
        );
        ?>

        <div class="adp-footer">
            <div>
                Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank"
                        rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14"
                            viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon">
                            <path
                                d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                        </svg> Diego Giraldo</a></strong> &bull; Versión
                <?php echo esc_html(defined('AURA_VERSION') ? AURA_VERSION : '1.0.0'); ?> &bull; Aura Business Suite
                &copy; <?php echo date('Y'); ?>
            </div>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<!-- ╔══════════════════════════════════════════╗
     ║  Modal: Nuevo Préstamo (UX v2)           ║
     ╚══════════════════════════════════════════╝ -->
<div id="aura-lib-loan-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog"
    aria-modal="true" aria-labelledby="aura-lib-loan-modal-title" aria-hidden="true">
    <div class="aura-modal-container aura-lib-modal-container aura-lib-loan-modal-content">

        <!-- Header con icono y degradado -->
        <div class="aura-modal-header aura-lib-loan-mhdr">
            <div class="aura-lib-loan-mhdr-icon">📚</div>
            <div class="aura-lib-loan-mhdr-text">
                <h2 id="aura-lib-loan-modal-title"><?php esc_html_e('Nuevo Préstamo', 'aura-suite'); ?></h2>
                <span><?php esc_html_e('Completa los campos para registrar el préstamo', 'aura-suite'); ?></span>
            </div>
            <button type="button" class="aura-modal-close aura-lib-modal-close" data-modal-close="aura-lib-loan-modal"
                aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>"
                title="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>

        <!-- Cuerpo del formulario -->
        <div class="aura-modal-body aura-lib-modal-body aura-lib-loan-mbody">
            <form id="aura-lib-loan-form" autocomplete="off" novalidate>
                <?php wp_nonce_field('aura_library_nonce', 'aura_lib_loan_nonce'); ?>

                <!-- ══ PASO 1: LIBRO ══════════════════════════════════ -->
                <div class="aura-lib-lstep">
                    <div class="aura-lib-lstep-hdr">
                        <span class="aura-lib-lstep-num">1</span>
                        <strong><?php esc_html_e('Libro', 'aura-suite'); ?></strong>
                        <span class="aura-lib-lstep-req"><?php esc_html_e('* requerido', 'aura-suite'); ?></span>
                    </div>

                    <!-- Buscador de libro + Modal Explorador -->
                    <div class="aura-lib-picker-input-row" id="loan-book-search-row">
                        <div class="aura-lib-lsearch-wrap">
                            <span class="dashicons dashicons-search aura-lib-lsearch-icon"></span>
                            <input type="text" id="loan_book_input" name="loan_book_input"
                                class="aura-lib-lsearch-input"
                                placeholder="<?php esc_attr_e('Buscar por título, autor o ISBN…', 'aura-suite'); ?>"
                                autocomplete="off">
                        </div>
                        <button type="button" id="aura-lib-btn-open-book-picker"
                            class="btn btn-glass btn-lift aura-lib-picker-trigger-btn"
                            data-tooltip="<?php esc_attr_e('Abrir explorador visual de todo el catálogo con filtros y portadas', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-book-alt"></span>
                            <span><?php esc_html_e('Explorar Catálogo', 'aura-suite'); ?></span>
                        </button>
                    </div>

                    <!-- Tarjeta del libro seleccionado -->
                    <div id="aura-lib-loan-book-card" class="aura-lib-loan-selected-card" style="display:none;">
                        <div class="aura-lib-loan-card-cover" id="loan-book-card-cover">
                            <span class="dashicons dashicons-book" style="font-size:22px;color:#9ca3af;"></span>
                        </div>
                        <div class="aura-lib-loan-card-info">
                            <strong id="loan-book-card-title" class="aura-lib-loan-card-title"></strong>
                            <span id="loan-book-card-sub" class="aura-lib-loan-card-sub"></span>
                        </div>
                        <div id="loan-book-card-avail" class="aura-lib-loan-card-avail"></div>
                        <button type="button" id="aura-lib-loan-clear-book" class="aura-lib-loan-clear-btn"
                            title="<?php esc_attr_e('Cambiar libro', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>

                    <span id="loan-book-error" class="aura-lib-loan-field-error" style="display:none;">
                        ⚠ <?php esc_html_e('Selecciona un libro de la lista de sugerencias.', 'aura-suite'); ?>
                    </span>
                    <input type="hidden" id="loan_book_id" name="book_id">
                </div>

                <!-- ══ PASO 2: LECTOR ════════════════════════════════ -->
                <?php if ($can_view_all): ?>
                    <div class="aura-lib-lstep">
                        <div class="aura-lib-lstep-hdr aura-lib-lstep-hdr-between">
                            <div class="aura-lib-lstep-title-wrap">
                                <span class="aura-lib-lstep-num">2</span>
                                <strong><?php esc_html_e('Lector o Contacto', 'aura-suite'); ?></strong>
                                <span class="aura-lib-lstep-req"><?php esc_html_e('* requerido', 'aura-suite'); ?></span>
                            </div>
                            <button type="button" id="aura-lib-btn-open-user-picker"
                                class="btn btn-glass btn-sm btn-lift aura-lib-picker-trigger-btn"
                                data-tooltip="<?php esc_attr_e('Abrir el directorio completo de todos los usuarios del sistema', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-groups"></span>
                                <span><?php esc_html_e('Explorar Todos los Usuarios', 'aura-suite'); ?></span>
                            </button>
                        </div>

                        <div id="loan-reader-selector-wrap">
                            <?php if (class_exists('Aura_Contact_Selector')): ?>
                                <?php
                                Aura_Contact_Selector::render([
                                    'id' => 'loan-contact-selector',
                                    'name' => 'contact_id',
                                    'placeholder' => __('Buscar contacto o usar "Explorar Todos los Usuarios"…', 'aura-suite'),
                                    'required' => true,
                                    'sync_fields' => [
                                        'user_id' => '#loan_user_id',
                                    ],
                                ]);
                                ?>
                            <?php else: ?>
                                <!-- Buscador de lector (fallback) -->
                                <div class="aura-lib-lsearch-wrap" id="loan-user-search-row">
                                    <span class="dashicons dashicons-admin-users aura-lib-lsearch-icon"></span>
                                    <input type="text" id="loan_user_input" name="loan_user_input"
                                        class="aura-lib-lsearch-input"
                                        placeholder="<?php esc_attr_e('Buscar por nombre o correo del lector…', 'aura-suite'); ?>"
                                        autocomplete="off">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tarjeta de usuario del sistema seleccionado desde el Explorador Global -->
                        <div id="aura-lib-loan-user-card" class="aura-lib-loan-selected-card" style="display:none;">
                            <div class="aura-lib-loan-user-avatar-box" id="loan-user-card-avatar"></div>
                            <div class="aura-lib-loan-card-info">
                                <strong id="loan-user-card-name" class="aura-lib-loan-card-title"></strong>
                                <span id="loan-user-card-email" class="aura-lib-loan-card-sub"></span>
                            </div>
                            <span class="badge badge-indigo"
                                id="loan-user-card-badge"><?php esc_html_e('Usuario WP', 'aura-suite'); ?></span>
                            <button type="button" id="aura-lib-loan-clear-user" class="aura-lib-loan-clear-btn"
                                data-tooltip="<?php esc_attr_e('Cambiar lector seleccionado', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>

                        <span id="loan-user-error" class="aura-lib-loan-field-error" style="display:none;">
                            ⚠ <?php esc_html_e('Selecciona un lector, contacto o usuario del sistema.', 'aura-suite'); ?>
                        </span>
                        <input type="hidden" id="loan_user_id" name="borrower_user_id">
                    </div>
                <?php endif; ?>

                <!-- ══ PASO 3: FECHAS ════════════════════════════════ -->
                <div class="aura-lib-lstep">
                    <div class="aura-lib-lstep-hdr">
                        <span class="aura-lib-lstep-num"><?php echo $can_view_all ? '3' : '2'; ?></span>
                        <strong><?php esc_html_e('Fechas del préstamo', 'aura-suite'); ?></strong>
                        <span id="loan-duration-chip" class="aura-lib-loan-duration-chip" style="display:none;"></span>
                    </div>
                    <div class="aura-lib-loan-dates-grid">
                        <div class="aura-lib-loan-date-field">
                            <label for="loan_loan_date">
                                <span class="dashicons dashicons-calendar-alt"
                                    style="font-size:13px;color:#6b7280;margin-right:3px;"></span>
                                <?php esc_html_e('Fecha de préstamo', 'aura-suite'); ?> <span
                                    class="required">*</span>
                            </label>
                            <input type="date" id="loan_loan_date" name="loan_date" required data-allow-future="1">
                        </div>
                        <div class="aura-lib-loan-date-field">
                            <label for="loan_due_date">
                                <span class="dashicons dashicons-clock"
                                    style="font-size:13px;color:#6b7280;margin-right:3px;"></span>
                                <?php esc_html_e('Fecha límite', 'aura-suite'); ?> <span class="required">*</span>
                            </label>
                            <input type="date" id="loan_due_date" name="due_date" required data-allow-future="1">
                        </div>
                    </div>
                </div>

                <!-- ══ NOTAS (colapsable) ════════════════════════════ -->
                <div class="aura-lib-lstep aura-lib-lstep-notes">
                    <button type="button" id="aura-lib-loan-notes-toggle" class="aura-lib-loan-notes-toggle">
                        <span class="dashicons dashicons-plus-alt2 aura-lib-notes-icon"></span>
                        <?php esc_html_e('Agregar nota (opcional)', 'aura-suite'); ?>
                    </button>
                    <div id="aura-lib-loan-notes-wrap" style="display:none;margin-top:8px;">
                        <textarea id="loan_notes" name="notes" rows="2" class="large-text"
                            placeholder="<?php esc_attr_e('Observaciones sobre este préstamo…', 'aura-suite'); ?>"></textarea>
                    </div>
                </div>

                <!-- Error general del modal -->
                <div id="aura-lib-loan-modal-error" class="aura-lib-loan-modal-error" style="display:none;"></div>

            </form>
        </div><!-- .aura-lib-modal-body -->

        <!-- Footer pegajoso -->
        <div class="aura-modal-footer aura-lib-loan-mfooter">
            <button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close"
                data-modal-close="aura-lib-loan-modal">
                <?php esc_html_e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" id="aura-lib-loan-save"
                class="btn btn-shimmer btn-indigo btn-lift aura-lib-loan-save-btn">
                <span class="aura-lib-btn-label">
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php esc_html_e('Registrar Préstamo', 'aura-suite'); ?>
                </span>
                <span class="aura-lib-btn-loading" style="display:none;">
                    <span class="spinner is-active" style="float:none;margin:0;vertical-align:middle;"></span>
                    <?php esc_html_e('Guardando…', 'aura-suite'); ?>
                </span>
            </button>
        </div>

    </div><!-- .aura-modal-container -->
</div>

<!-- ╔══════════════════════════════════╗
     ║  Modal: Registrar Devolución     ║
     ╚══════════════════════════════════╝ -->
<div id="aura-lib-return-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog"
    aria-modal="true" aria-labelledby="aura-lib-return-modal-title" aria-hidden="true" data-async-modal="true">
    <div class="aura-modal-container aura-lib-modal-container">
        <div class="aura-modal-header">
            <h2 id="aura-lib-return-modal-title"><?php esc_html_e('Registrar Devolución', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close aura-lib-modal-close" data-modal-close="aura-lib-return-modal"
                aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>"
                title="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>
        <form id="aura-lib-return-form" class="aura-modal-form-flex" autocomplete="off">
            <div class="aura-modal-body aura-lib-modal-body">
                <?php wp_nonce_field('aura_library_nonce', 'aura_lib_return_nonce'); ?>
                <input type="hidden" id="return_loan_id" name="loan_id">

                <!-- Info del préstamo -->
                <div id="aura-lib-return-info" class="aura-lib-return-info-box"></div>

                <div class="aura-lib-form-row">
                    <label for="return_date"><?php esc_html_e('Fecha de devolución *', 'aura-suite'); ?></label>
                    <input type="date" id="return_date" name="return_date" class="form-control regular-text" required>
                </div>

                <div class="aura-lib-form-row">
                    <label for="return_condition"><?php esc_html_e('Condición del libro', 'aura-suite'); ?></label>
                    <select id="return_condition" name="return_condition" class="form-control regular-text">
                        <option value="good"><?php esc_html_e('Buena', 'aura-suite'); ?></option>
                        <option value="damaged"><?php esc_html_e('Deteriorado', 'aura-suite'); ?></option>
                        <option value="lost"><?php esc_html_e('Perdido', 'aura-suite'); ?></option>
                    </select>
                </div>

                <div class="aura-lib-form-row">
                    <label for="return_notes"><?php esc_html_e('Notas de devolución', 'aura-suite'); ?></label>
                    <textarea id="return_notes" name="return_notes" rows="2" class="form-control large-text"></textarea>
                </div>

                <?php if ($fines_enabled): ?>
                    <!-- Panel de multa -->
                    <div id="aura-lib-fine-panel" class="aura-lib-fine-panel" style="display:none;">
                        <h3><?php esc_html_e('Multa por retraso', 'aura-suite'); ?></h3>
                        <p>
                            <?php esc_html_e('Días de retraso:', 'aura-suite'); ?>
                            <strong id="return-overdue-days">0</strong><br>
                            <?php esc_html_e('Importe de multa:', 'aura-suite'); ?>
                            <strong id="return-fine-amount">$0.00</strong>
                        </p>
                        <label>
                            <input type="checkbox" id="return_pay_fine" name="pay_fine" value="1">
                            <?php esc_html_e('Marcar multa como pagada', 'aura-suite'); ?>
                        </label>
                        <?php if ($fines_to_finance): ?>
                            <br>
                            <label>
                                <input type="checkbox" id="return_to_finance" name="to_finance" value="1">
                                <?php esc_html_e('Registrar cobro en Finanzas', 'aura-suite'); ?>
                            </label>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="aura-modal-footer aura-lib-modal-footer">
                <button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close"
                    data-modal-close="aura-lib-return-modal">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="submit" class="btn btn-shimmer btn-emerald btn-lift" id="aura-lib-return-save">
                    <?php esc_html_e('Confirmar Devolución', 'aura-suite'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ╔══════════════════════════════════╗
     ║  Modal: Detalle del Préstamo     ║
     ╚══════════════════════════════════╝ -->
<div id="aura-lib-loan-detail-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog"
    aria-modal="true" aria-labelledby="aura-lib-loan-detail-title" aria-hidden="true" data-async-modal="true">
    <div class="aura-modal-container aura-lib-modal-container aura-lib-modal-large">
        <div class="aura-modal-header">
            <h2 id="aura-lib-loan-detail-title"><?php esc_html_e('Detalle del Préstamo', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close aura-lib-modal-close"
                data-modal-close="aura-lib-loan-detail-modal"
                aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>"
                title="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>
        <div class="aura-modal-body aura-lib-modal-body" id="aura-lib-loan-detail-body">
            <p class="aura-lib-detail-empty"><?php esc_html_e('Cargando detalle…', 'aura-suite'); ?></p>
        </div>
    </div>
</div>

<!-- ╔══════════════════════════════════════════╗
     ║  Modal: Editar Préstamo                  ║
     ╚══════════════════════════════════════════╝ -->
<div id="aura-lib-edit-loan-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog"
    aria-modal="true" aria-labelledby="aura-lib-edit-loan-title" aria-hidden="true" data-async-modal="true">
    <div class="aura-modal-container aura-lib-modal-container">
        <div class="aura-modal-header">
            <h2 id="aura-lib-edit-loan-title"><?php esc_html_e('Editar Préstamo', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close aura-lib-modal-close"
                data-modal-close="aura-lib-edit-loan-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>"
                title="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>
        <form id="aura-lib-edit-loan-form" class="aura-modal-form-flex" autocomplete="off">
            <div class="aura-modal-body aura-lib-modal-body">
                <?php wp_nonce_field('aura_library_nonce', 'aura_lib_edit_loan_nonce'); ?>
                <input type="hidden" id="edit_loan_id" name="loan_id">

                <!-- Info resumida del préstamo -->
                <div id="aura-lib-edit-loan-info" class="aura-lib-return-info-box"></div>

                <div class="aura-lib-form-row">
                    <label for="edit_loan_date"><?php esc_html_e('Fecha de préstamo *', 'aura-suite'); ?></label>
                    <input type="date" id="edit_loan_date" name="loan_date" class="form-control regular-text" required
                        data-allow-future="1">
                </div>
                <div class="aura-lib-form-row">
                    <label
                        for="edit_due_date"><?php esc_html_e('Fecha límite de devolución *', 'aura-suite'); ?></label>
                    <input type="date" id="edit_due_date" name="due_date" class="form-control regular-text" required
                        data-allow-future="1">
                </div>
                <div class="aura-lib-form-row">
                    <label for="edit_status"><?php esc_html_e('Estado', 'aura-suite'); ?></label>
                    <select id="edit_status" name="status" class="form-control regular-text">
                        <option value="active"><?php esc_html_e('Activo', 'aura-suite'); ?></option>
                        <option value="overdue"><?php esc_html_e('Vencido', 'aura-suite'); ?></option>
                        <option value="extended"><?php esc_html_e('Extendido', 'aura-suite'); ?></option>
                        <option value="lost"><?php esc_html_e('Perdido', 'aura-suite'); ?></option>
                    </select>
                </div>
                <div class="aura-lib-form-row">
                    <label for="edit_notes"><?php esc_html_e('Notas', 'aura-suite'); ?></label>
                    <textarea id="edit_notes" name="notes" rows="3" class="form-control large-text"
                        placeholder="<?php esc_attr_e('Observaciones sobre este préstamo…', 'aura-suite'); ?>"></textarea>
                </div>

                <div id="aura-lib-edit-loan-error" class="aura-lib-loan-modal-error" style="display:none;"></div>
            </div>
            <div class="aura-modal-footer aura-lib-modal-footer">
                <button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close"
                    data-modal-close="aura-lib-edit-loan-modal">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="aura-lib-edit-loan-save">
                    <?php esc_html_e('Guardar Cambios', 'aura-suite'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ╔══════════════════════════════════════════╗
     ║  Modal: Extender Préstamo                ║
     ╚══════════════════════════════════════════╝ -->
<div id="aura-lib-extend-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog"
    aria-modal="true" aria-labelledby="aura-lib-extend-modal-title" aria-hidden="true" data-async-modal="true">
    <div class="aura-modal-container aura-lib-modal-container">
        <div class="aura-modal-header">
            <h2 id="aura-lib-extend-modal-title"><?php esc_html_e('Extender Préstamo', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close aura-lib-modal-close" data-modal-close="aura-lib-extend-modal"
                aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>"
                title="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>
        <form id="aura-lib-extend-form" class="aura-modal-form-flex" autocomplete="off">
            <div class="aura-modal-body aura-lib-modal-body">
                <?php wp_nonce_field('aura_library_nonce', 'aura_lib_extend_nonce'); ?>
                <input type="hidden" id="extend_loan_id" name="loan_id">

                <!-- Info del préstamo -->
                <div id="aura-lib-extend-info" class="aura-lib-return-info-box"></div>

                <div class="aura-lib-form-row">
                    <label><?php esc_html_e('Días a extender', 'aura-suite'); ?></label>
                    <div class="aura-lib-extend-presets">
                        <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-extend-preset"
                            data-days="7">+7 <?php esc_html_e('días', 'aura-suite'); ?></button>
                        <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-extend-preset"
                            data-days="14">+14 <?php esc_html_e('días', 'aura-suite'); ?></button>
                        <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-extend-preset"
                            data-days="30">+30 <?php esc_html_e('días', 'aura-suite'); ?></button>
                    </div>
                    <div class="input-group aura-lib-extend-days-group">
                        <span class="input-group-text" aria-hidden="true">⏱️</span>
                        <input type="number" id="extend_days" name="days" min="1" max="180"
                            value="<?php echo esc_attr($extension_days); ?>" class="form-control">
                        <span
                            class="input-group-text"><?php esc_html_e('días personalizados', 'aura-suite'); ?></span>
                    </div>
                </div>

                <div class="aura-lib-form-row">
                    <label><?php esc_html_e('Nueva fecha límite', 'aura-suite'); ?></label>
                    <strong id="extend-new-due" class="aura-lib-extend-new-due">—</strong>
                </div>

                <div id="aura-lib-extend-error" class="aura-lib-loan-modal-error" style="display:none;"></div>
            </div>
            <div class="aura-modal-footer aura-lib-modal-footer">
                <button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close"
                    data-modal-close="aura-lib-extend-modal">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="aura-lib-extend-save">
                    <?php esc_html_e('Confirmar Extensión', 'aura-suite'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ Modal Apilado: Explorador Visual del Catálogo de Libros ═══════════════ -->
<div id="aura-lib-book-picker-modal" class="aura-modal-overlay aura-lib-modal aura-lib-stacked-modal"
    style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-bp-modal-title" aria-hidden="true">
    <div class="aura-modal-container aura-lib-modal-container aura-lib-modal-wide aura-lib-bp-modal-container">
        <div class="aura-modal-header aura-lib-loan-mhdr">
            <div class="aura-lib-loan-mhdr-icon">📖</div>
            <div class="aura-lib-loan-mhdr-text">
                <h2 id="aura-lib-bp-modal-title">
                    <?php esc_html_e('Explorador del Catálogo de Libros', 'aura-suite'); ?></h2>
                <span><?php esc_html_e('Busca, filtra por clasificación Dewey y selecciona el ejemplar en 1 clic', 'aura-suite'); ?></span>
            </div>
            <button type="button" class="aura-modal-close aura-lib-bp-close"
                data-tooltip="<?php esc_attr_e('Cerrar explorador de libros', 'aura-suite'); ?>"
                aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>

        <div class="aura-modal-body aura-lib-modal-body">
            <div class="aura-lib-bp-toolbar">
                <div class="aura-lib-bp-search-box input-group">
                    <span class="input-group-text" aria-hidden="true">🔍</span>
                    <input type="search" id="aura-lib-bp-search" class="form-control"
                        placeholder="<?php esc_attr_e('Buscar por título, autor, ISBN o Dewey…', 'aura-suite'); ?>"
                        autocomplete="off">
                </div>
                <div class="aura-lib-bp-dewey-box">
                    <select id="aura-lib-bp-dewey" class="form-control"
                        aria-label="<?php esc_attr_e('Clase Dewey', 'aura-suite'); ?>">
                        <option value=""><?php esc_html_e('Todas las clases Dewey', 'aura-suite'); ?></option>
                        <option value="0"><?php esc_html_e('000 — Informática / Gral.', 'aura-suite'); ?></option>
                        <option value="1"><?php esc_html_e('100 — Filosofía / Psic.', 'aura-suite'); ?></option>
                        <option value="2"><?php esc_html_e('200 — Religión', 'aura-suite'); ?></option>
                        <option value="3"><?php esc_html_e('300 — Ciencias Sociales', 'aura-suite'); ?></option>
                        <option value="4"><?php esc_html_e('400 — Lenguas', 'aura-suite'); ?></option>
                        <option value="5"><?php esc_html_e('500 — Ciencias Puras', 'aura-suite'); ?></option>
                        <option value="6"><?php esc_html_e('600 — Tecnología', 'aura-suite'); ?></option>
                        <option value="7"><?php esc_html_e('700 — Artes', 'aura-suite'); ?></option>
                        <option value="8"><?php esc_html_e('800 — Literatura', 'aura-suite'); ?></option>
                        <option value="9"><?php esc_html_e('900 — Historia / Geo.', 'aura-suite'); ?></option>
                    </select>
                </div>
                <label class="aura-lib-bp-avail-toggle"
                    data-tooltip="<?php esc_attr_e('Mostrar únicamente libros con ejemplares disponibles para préstamo', 'aura-suite'); ?>">
                    <input type="checkbox" id="aura-lib-bp-avail-only" checked>
                    <span><?php esc_html_e('Solo disponibles', 'aura-suite'); ?></span>
                </label>
            </div>

            <div id="aura-lib-bp-grid" class="aura-lib-bp-grid"></div>
            <div id="aura-lib-bp-pagination" class="aura-lib-pagination"
                style="margin-top:16px; display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;">
            </div>
        </div>

        <div class="aura-modal-footer aura-lib-modal-footer">
            <span id="aura-lib-bp-count" class="aura-lib-sub-muted" style="margin-right:auto;"></span>
            <button type="button" class="btn btn-glass btn-lift aura-lib-bp-close">
                <?php esc_html_e('Cerrar', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<?php
$js_data = wp_json_encode([
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('aura_library_nonce'),
    'can_create' => $can_create,
    'can_return' => $can_return,
    'can_extend' => $can_extend,
    'can_edit' => $can_edit,
    'can_delete' => $can_delete,
    'can_view_all' => $can_view_all,
    'can_view_fines' => $can_view_fines,
    'fines_enabled' => $fines_enabled,
    'fines_to_finance' => $fines_to_finance,
    'max_extensions' => $max_extensions,
    'extension_days' => $extension_days,
    'loan_days' => (int) get_option('aura_library_loan_days', 14),
    'txt' => [
        'loading' => __('Cargando…', 'aura-suite'),
        'no_results' => __('No se encontraron préstamos.', 'aura-suite'),
        'error' => __('Error al procesar la solicitud.', 'aura-suite'),
        'saved' => __('Préstamo registrado correctamente.', 'aura-suite'),
        'returned' => __('Devolución registrada correctamente.', 'aura-suite'),
        'extended' => __('Préstamo extendido correctamente.', 'aura-suite'),
        'updated' => __('Préstamo actualizado correctamente.', 'aura-suite'),
        'cancelled' => __('Préstamo cancelado correctamente.', 'aura-suite'),
        'confirm_extend' => __('¿Extender este préstamo?', 'aura-suite'),
        'confirm_cancel' => __('¿Cancelar este préstamo? Esta acción no se puede deshacer.', 'aura-suite'),
        'page_of' => __('Página %1$s de %2$s', 'aura-suite'),
        'n_items' => __('%s préstamos', 'aura-suite'),
        'status_labels' => [
            'active' => __('Activo', 'aura-suite'),
            'overdue' => __('Vencido', 'aura-suite'),
            'extended' => __('Extendido', 'aura-suite'),
            'returned' => __('Devuelto', 'aura-suite'),
            'lost' => __('Perdido', 'aura-suite'),
            'cancelled' => __('Cancelado', 'aura-suite'),
        ],
        'condition_labels' => [
            'good' => __('Buena', 'aura-suite'),
            'damaged' => __('Deteriorado', 'aura-suite'),
            'lost' => __('Perdido', 'aura-suite'),
        ],
    ],
]);
?>
<script>var auraLibraryLoans = <?php echo $js_data; // phpcs:ignore WordPress.Security.EscapeOutput ?>;</script>