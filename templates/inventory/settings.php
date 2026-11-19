<?php
/**
 * Template: Categorías y Configuración del Módulo de Inventario
 *
 * Ruta: admin.php?page=aura-inventory-settings
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
    wp_die( __( 'No tienes permisos para acceder a la configuración de inventario.', 'aura-suite' ) );
}

global $wpdb;

$nonce           = wp_create_nonce( 'aura_inventory_nonce' );
$categories      = class_exists( 'Aura_Inventory_Categories' ) ? Aura_Inventory_Categories::get_all() : [];
$settings        = class_exists( 'Aura_Inventory_Categories' ) ? Aura_Inventory_Categories::get_settings() : [];
$interval_labels = [
    'none'  => __( 'Sin programa de mantenimiento', 'aura-suite' ),
    'time'  => __( 'Por tiempo (meses)', 'aura-suite' ),
    'hours' => __( 'Por horas de uso', 'aura-suite' ),
    'both'  => __( 'Tiempo + horas', 'aura-suite' ),
];

$finance_cats_settings = [];
$finance_cats_map      = [];
if ( class_exists( 'Aura_Financial_Categories' ) ) {
    $finance_cats_settings = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}aura_finance_categories WHERE type = 'expense' AND is_active = 1 ORDER BY name ASC"
    ) ?: [];
    foreach ( $finance_cats_settings as $fc_row ) {
        $finance_cats_map[ (int) $fc_row->id ] = $fc_row->name;
    }
}

$allowed_tabs = [ 'categories', 'general', 'notifications', 'gcal', 'finanzas' ];
$req_tab      = sanitize_key( $_GET['tab'] ?? 'categories' );
$active_tab   = in_array( $req_tab, $allowed_tabs, true ) ? $req_tab : 'categories';

$code_prefix_cfg = strtoupper( (string) ( $settings['code_prefix'] ?? 'CEM' ) );
$code_digits_cfg = max( 2, min( 5, intval( $settings['code_digits'] ?? 3 ) ) );
$code_info       = method_exists( 'Aura_Inventory_Equipment', 'get_code_suggestions' )
    ? Aura_Inventory_Equipment::get_code_suggestions( 0 )
    : [ 'last_main_code' => '', 'next_main_code' => $code_prefix_cfg . '-' . str_pad( '1', $code_digits_cfg, '0', STR_PAD_LEFT ) ];

// Moneda base global ISO 4217 (Ajustes → Finanzas)
$global_currency_code = function_exists( 'aura_get_base_currency' )
    ? aura_get_base_currency()
    : get_option( 'aura_finance_base_currency', 'MXN' );
$global_currency_sym  = function_exists( 'aura_get_currency_symbol' )
    ? aura_get_currency_symbol()
    : get_option( 'aura_currency_symbol', '$' );
$global_currencies    = class_exists( 'Aura_Financial_Currency' ) ? Aura_Financial_Currency::get_currencies() : [];
$global_currency_meta = $global_currencies[ $global_currency_code ] ?? null;
$global_currency_lbl  = $global_currency_meta
    ? sprintf( '%s %s — %s (%s)', $global_currency_meta['flag'] ?? '', $global_currency_code, $global_currency_meta['name'] ?? '', $global_currency_sym )
    : sprintf( '%s (%s)', $global_currency_code, $global_currency_sym );
$global_currency_sample = function_exists( 'aura_format_money' )
    ? aura_format_money( 1234.50, $global_currency_code, false )
    : ( $global_currency_sym . '1,234.50' );

$gcal_enabled_nav = class_exists( 'Aura_Inventory_Google_Calendar' ) && Aura_Inventory_Google_Calendar::is_enabled();
$fin_cat_id       = intval( $settings['finance_category_id'] ?? 0 );
$fin_cat_label    = $fin_cat_id && isset( $finance_cats_map[ $fin_cat_id ] )
    ? $finance_cats_map[ $fin_cat_id ]
    : __( 'Sin vincular', 'aura-suite' );

// Encolado seguro
if ( ! wp_style_is( 'aura-inventory-settings', 'enqueued' ) && ! wp_style_is( 'aura-inv-settings', 'enqueued' ) ) {
    $css_path = AURA_PLUGIN_DIR . 'assets/css/inventory-settings.css';
    wp_enqueue_style(
        'aura-inventory-settings',
        AURA_PLUGIN_URL . 'assets/css/inventory-settings.css',
        [ 'aura-design-system' ],
        file_exists( $css_path ) ? filemtime( $css_path ) : AURA_VERSION
    );
}
if ( ! wp_script_is( 'aura-inventory-settings', 'enqueued' ) ) {
    $js_path = AURA_PLUGIN_DIR . 'assets/js/inventory-settings.js';
    wp_enqueue_script(
        'aura-inventory-settings',
        AURA_PLUGIN_URL . 'assets/js/inventory-settings.js',
        [ 'jquery', 'aura-ui-core' ],
        file_exists( $js_path ) ? filemtime( $js_path ) : AURA_VERSION,
        true
    );
}
?>

<div class="aura-app-wrapper aura-inventory-settings aura-inv-settings-wrap">
    <div class="wrap aura-app-context">

        <?php
        if ( class_exists( 'Aura_UI' ) ) {
            Aura_UI::render_page_header( [
                'title'    => __( 'Configuración de Inventario y Activos', 'aura-suite' ),
                'subtitle' => __( 'Administra categorías de equipos, intervalos preventivos, alertas por correo, Google Calendar y automatización contable.', 'aura-suite' ),
                'icon'     => 'dashicons-admin-settings',
                'actions'  => [
                    [
                        'label' => __( 'Catálogo de Equipos', 'aura-suite' ),
                        'url'   => admin_url( 'admin.php?page=aura-inventory-equipment' ),
                        'icon'  => 'dashicons-hammer',
                        'class' => 'btn btn-glass btn-lift',
                    ],
                    [
                        'label' => __( 'Dashboard', 'aura-suite' ),
                        'url'   => admin_url( 'admin.php?page=aura-inventory' ),
                        'icon'  => 'dashicons-chart-area',
                        'class' => 'btn btn-glass btn-lift',
                    ],
                ],
            ] );
        }
        ?>

        <!-- Barra de Navegación Aura Design System -->
        <nav class="aura-navbar aura-nav aura-glass-card tabs-bar aura-inv-settings-nav fade-up" id="aura-inv-settings-nav" role="tablist">
            <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab <?php echo $active_tab === 'categories' ? 'active' : ''; ?>" data-tab="categories" role="tab">
                <span class="dashicons dashicons-category"></span>
                <span><?php _e( 'Categorías', 'aura-suite' ); ?></span>
                <span class="aura-inv-nav-pill" id="js-nav-pill-categories"><?php echo count( $categories ); ?></span>
            </button>
            <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>" data-tab="general" role="tab">
                <span class="dashicons dashicons-admin-generic"></span>
                <span><?php _e( 'Configuración General', 'aura-suite' ); ?></span>
                <span class="aura-inv-nav-pill aura-inv-nav-pill-indigo" id="js-nav-pill-prefix"><?php echo esc_html( $code_prefix_cfg ); ?></span>
            </button>
            <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" data-tab="notifications" role="tab">
                <span class="dashicons dashicons-email-alt"></span>
                <span><?php _e( 'Notificaciones', 'aura-suite' ); ?></span>
                <span class="aura-inv-nav-pill <?php echo ! empty( $settings['email_alerts'] ) ? 'aura-inv-nav-pill-ok' : 'aura-inv-nav-pill-muted'; ?>" id="js-nav-pill-notif">
                    <?php echo ! empty( $settings['email_alerts'] ) ? esc_html__( 'Activas', 'aura-suite' ) : esc_html__( 'Pausadas', 'aura-suite' ); ?>
                </span>
            </button>
            <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab <?php echo $active_tab === 'gcal' ? 'active' : ''; ?>" data-tab="gcal" role="tab">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php _e( 'Google Calendar', 'aura-suite' ); ?></span>
                <span class="aura-inv-nav-pill <?php echo $gcal_enabled_nav ? 'aura-inv-nav-pill-ok' : 'aura-inv-nav-pill-muted'; ?>">
                    <?php echo $gcal_enabled_nav ? esc_html__( 'Activo', 'aura-suite' ) : esc_html__( 'Inactivo', 'aura-suite' ); ?>
                </span>
            </button>
            <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab <?php echo $active_tab === 'finanzas' ? 'active' : ''; ?>" data-tab="finanzas" role="tab">
                <span class="dashicons dashicons-money-alt"></span>
                <span><?php _e( 'Finanzas', 'aura-suite' ); ?></span>
                <span class="aura-inv-nav-pill <?php echo $fin_cat_id > 0 ? 'aura-inv-nav-pill-ok' : 'aura-inv-nav-pill-warn'; ?>" id="js-nav-pill-finance">
                    <?php echo esc_html( $fin_cat_label ); ?>
                </span>
            </button>
        </nav>

        <div id="js-inv-settings-notice" class="aura-inv-notice" style="display:none;margin-top:16px;"></div>

        <!-- ══════════════════════════════════════════════════════
             TAB 1: CATEGORÍAS
        ══════════════════════════════════════════════════════════ -->
        <div class="aura-inv-tab-panel" data-panel="categories" <?php echo $active_tab === 'categories' ? '' : 'style="display:none;"'; ?>>

            <div class="aura-inv-section-header">
                <div>
                    <h2><?php _e( 'Categorías de Equipos', 'aura-suite' ); ?></h2>
                    <p class="aura-inv-muted-desc"><?php _e( 'Define clasificaciones e intervalos predeterminados de mantenimiento para pre-llenar nuevos equipos.', 'aura-suite' ); ?></p>
                </div>
                <button type="button" id="js-install-defaults" class="btn btn-glass aura-inv-btn-defaults"
                        data-tooltip="<?php esc_attr_e( 'Crea categorías estándar como Cómputo, Herramientas, Audiovisual y Vehículos sin duplicar existentes', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e( 'Instalar categorías predeterminadas', 'aura-suite' ); ?>
                </button>
            </div>

            <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap" style="margin-bottom:24px;">
                <table class="aura-table aura-inv-cats-table aura-inv-responsive-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <span class="aura-th-flex">+
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Haz clic en + para desplegar la descripción y programa completo de la categoría', 'aura-suite' ); ?>">?</span>
                                </span>
                            </th>
                            <th class="col-name">
                                <span class="aura-th-flex"><?php _e( 'Nombre', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Nombre visible de la categoría de equipos', 'aura-suite' ); ?>">?</span>
                                </span>
                            </th>
                            <th class="col-slug aura-inv-col-secondary">
                                <span class="aura-th-flex"><?php _e( 'Slug', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Identificador interno único en la base de datos', 'aura-suite' ); ?>">?</span>
                                </span>
                            </th>
                            <th class="col-interval">
                                <span class="aura-th-flex"><?php _e( 'Programa de mantenimiento', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Frecuencia preventiva sugerida por meses u horas', 'aura-suite' ); ?>">?</span>
                                </span>
                            </th>
                            <th class="col-count aura-inv-col-secondary">
                                <span class="aura-th-flex"><?php _e( 'Equipos', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Cantidad de equipos asociados actualmente', 'aura-suite' ); ?>">?</span>
                                </span>
                            </th>
                            <th class="col-actions">
                                <span class="aura-th-flex"><?php _e( 'Acciones', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Editar intervalo o eliminar categoría sin equipos asignados', 'aura-suite' ); ?>">?</span>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="js-categories-tbody">
                        <?php if ( ! empty( $categories ) ) : ?>
                            <?php foreach ( $categories as $cat ) :
                                $itype = $cat->interval_type ?: 'none';
                                switch ( $itype ) {
                                    case 'time':
                                        $interval_str = sprintf( __( 'Cada %d meses', 'aura-suite' ), (int) $cat->interval_months );
                                        break;
                                    case 'hours':
                                        $interval_str = sprintf( __( 'Cada %d horas', 'aura-suite' ), (int) $cat->interval_hours );
                                        break;
                                    case 'both':
                                        $interval_str = sprintf( __( 'Cada %d meses / %d h', 'aura-suite' ), (int) $cat->interval_months, (int) $cat->interval_hours );
                                        break;
                                    default:
                                        $interval_str = __( 'Sin programa', 'aura-suite' );
                                        break;
                                }
                                $cat_desc = wp_unslash( $cat->description ?? '' );
                                $tip_html = '<div class="aura-tip-card">'
                                    . '<div class="aura-tip-card-header"><div class="aura-tip-info"><div class="aura-tip-title">' . esc_html( $cat->name ) . '</div></div></div>'
                                    . '<div class="aura-tip-card-body">'
                                    . '<div class="aura-tip-row"><span>Slug</span><strong>' . esc_html( $cat->slug ) . '</strong></div>'
                                    . '<div class="aura-tip-row"><span>Programa</span><strong>' . esc_html( $interval_str ) . '</strong></div>'
                                    . '<div class="aura-tip-row"><span>Equipos</span><strong>' . intval( $cat->count ) . '</strong></div>'
                                    . '</div></div>';
                            ?>
                            <tr data-term-id="<?php echo esc_attr( $cat->term_id ); ?>"
                                data-name="<?php echo esc_attr( $cat->name ); ?>"
                                data-slug="<?php echo esc_attr( $cat->slug ); ?>"
                                data-description="<?php echo esc_attr( $cat_desc ); ?>"
                                data-interval-str="<?php echo esc_attr( $interval_str ); ?>"
                                data-count="<?php echo esc_attr( $cat->count ); ?>">
                                <td>
                                    <button type="button" class="aura-inv-row-toggle aura-settings-cat-toggle"
                                            data-tooltip="<?php esc_attr_e( 'Expandir detalles de la categoría', 'aura-suite' ); ?>"
                                            aria-expanded="false" aria-label="<?php esc_attr_e( 'Expandir', 'aura-suite' ); ?>">+</button>
                                </td>
                                <td class="col-name">
                                    <strong class="aura-inv-cat-name-lbl" data-aura-tooltip="<?php echo esc_attr( $tip_html ); ?>">
                                        <?php echo esc_html( $cat->name ); ?>
                                    </strong>
                                    <?php if ( $cat_desc ) : ?>
                                    <div class="aura-inv-cell-sub"><?php echo esc_html( wp_trim_words( $cat_desc, 12, '…' ) ); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-slug aura-inv-col-secondary"><code><?php echo esc_html( $cat->slug ); ?></code></td>
                                <td class="col-interval">
                                    <span class="aura-inv-interval-badge aura-inv-interval-<?php echo esc_attr( $itype ); ?>">
                                        <?php echo esc_html( $interval_str ); ?>
                                    </span>
                                </td>
                                <td class="col-count aura-inv-col-secondary">
                                    <span class="aura-inv-count-badge"><?php echo intval( $cat->count ); ?></span>
                                </td>
                                <td class="col-actions">
                                    <div class="aura-inv-action-group">
                                        <button type="button" class="aura-btn-action-icon aura-btn-edit aura-inv-btn-edit-cat"
                                                data-term-id="<?php echo esc_attr( $cat->term_id ); ?>"
                                                data-name="<?php echo esc_attr( $cat->name ); ?>"
                                                data-slug="<?php echo esc_attr( $cat->slug ); ?>"
                                                data-description="<?php echo esc_attr( $cat_desc ); ?>"
                                                data-interval-type="<?php echo esc_attr( $itype ); ?>"
                                                data-interval-months="<?php echo esc_attr( $cat->interval_months ); ?>"
                                                data-interval-hours="<?php echo esc_attr( $cat->interval_hours ); ?>"
                                                data-tooltip="<?php esc_attr_e( 'Editar categoría', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button type="button" class="aura-btn-action-icon aura-btn-delete aura-inv-btn-delete-cat"
                                                data-term-id="<?php echo esc_attr( $cat->term_id ); ?>"
                                                data-name="<?php echo esc_attr( $cat->name ); ?>"
                                                data-tooltip="<?php esc_attr_e( 'Eliminar categoría', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr id="js-cats-empty">
                                <td colspan="6" style="padding:24px;text-align:center;" class="aura-inv-muted">
                                    <?php _e( 'No hay categorías registradas. Haz clic en "Instalar categorías predeterminadas" para comenzar.', 'aura-suite' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Formulario: Agregar nueva categoría -->
            <div class="aura-glass-card aura-inv-card">
                <h3 class="aura-inv-card-title">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e( 'Agregar nueva categoría', 'aura-suite' ); ?>
                </h3>

                <div id="js-add-cat-msg" class="aura-inv-notice" style="display:none;"></div>

                <form id="js-form-add-category" novalidate>
                    <div class="aura-inv-form-grid">
                        <div class="aura-inv-field">
                            <label for="cat-name" class="aura-inv-label">
                                <?php _e( 'Nombre', 'aura-suite' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" id="cat-name" name="name" class="form-control"
                                   placeholder="<?php esc_attr_e( 'Ej: Equipos de Construcción', 'aura-suite' ); ?>" required>
                        </div>

                        <div class="aura-inv-field">
                            <label for="cat-slug" class="aura-inv-label">
                                <?php _e( 'Slug (identificador)', 'aura-suite' ); ?>
                            </label>
                            <input type="text" id="cat-slug" name="slug" class="form-control"
                                   placeholder="<?php esc_attr_e( 'auto-generado si se deja vacío', 'aura-suite' ); ?>">
                        </div>

                        <div class="aura-inv-field aura-inv-field-full">
                            <label for="cat-description" class="aura-inv-label">
                                <?php _e( 'Descripción', 'aura-suite' ); ?>
                            </label>
                            <input type="text" id="cat-description" name="description" class="form-control"
                                   placeholder="<?php esc_attr_e( 'Descripción breve de esta categoría…', 'aura-suite' ); ?>">
                        </div>
                    </div>

                    <div class="aura-inv-interval-block">
                        <h4><?php _e( 'Intervalo de mantenimiento predeterminado', 'aura-suite' ); ?></h4>
                        <p class="description"><?php _e( 'Al registrar un equipo de esta categoría, se pre-llenará con este intervalo.', 'aura-suite' ); ?></p>

                        <div class="aura-inv-form-grid" style="margin-top:10px;">
                            <div class="aura-inv-field">
                                <label for="cat-interval-type" class="aura-inv-label"><?php _e( 'Tipo de intervalo', 'aura-suite' ); ?></label>
                                <select id="cat-interval-type" name="interval_type" class="form-select">
                                    <?php foreach ( $interval_labels as $val => $lbl ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="aura-inv-field" id="cat-months-wrap">
                                <label for="cat-interval-months" class="aura-inv-label"><?php _e( 'Cada N meses', 'aura-suite' ); ?></label>
                                <div class="input-group aura-inv-compact-group">
                                    <input type="number" id="cat-interval-months" name="interval_months" class="form-control" min="1" max="120" placeholder="12">
                                    <span class="input-group-text"><?php _e( 'meses', 'aura-suite' ); ?></span>
                                </div>
                            </div>

                            <div class="aura-inv-field" id="cat-hours-wrap">
                                <label for="cat-interval-hours" class="aura-inv-label"><?php _e( 'Cada N horas de uso', 'aura-suite' ); ?></label>
                                <div class="input-group aura-inv-compact-group">
                                    <input type="number" id="cat-interval-hours" name="interval_hours" class="form-control" min="1" max="9999" placeholder="250">
                                    <span class="input-group-text">h</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="aura-inv-form-actions">
                        <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-btn-add-cat">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e( 'Agregar categoría', 'aura-suite' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div><!-- /tab categories -->

        <!-- ══════════════════════════════════════════════════════
             TAB 2: CONFIGURACIÓN GENERAL
        ══════════════════════════════════════════════════════════ -->
        <div class="aura-inv-tab-panel" data-panel="general" <?php echo $active_tab === 'general' ? '' : 'style="display:none;"'; ?>>
            <div class="aura-glass-card aura-inv-card">
                <h3 class="aura-inv-card-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php _e( 'Configuración General y Códigos Internos', 'aura-suite' ); ?>
                </h3>
                <p class="description"><?php _e( 'Define el prefijo institucional para numeración secuencial de equipos y los parámetros operativos del inventario.', 'aura-suite' ); ?></p>

                <div id="js-general-msg" class="aura-inv-notice" style="display:none;"></div>

                <form id="js-form-settings" novalidate>
                    <table class="form-table aura-inv-settings-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="set-code-prefix"><?php _e( 'Iniciales / Prefijo de códigos internos', 'aura-suite' ); ?></label></th>
                            <td>
                                <div class="aura-inv-code-cfg-row">
                                    <div class="input-group aura-inv-compact-group">
                                        <span class="input-group-text"><span class="dashicons dashicons-tag"></span></span>
                                        <input type="text" id="set-code-prefix" name="code_prefix" class="form-control"
                                               maxlength="12" style="text-transform:uppercase;font-weight:700;"
                                               value="<?php echo esc_attr( $code_prefix_cfg ); ?>" placeholder="CEM">
                                    </div>
                                    <div class="input-group aura-inv-compact-group">
                                        <span class="input-group-text"><?php _e( 'Dígitos', 'aura-suite' ); ?></span>
                                        <select id="set-code-digits" name="code_digits" class="form-select">
                                            <?php for ( $d = 2; $d <= 5; $d++ ) : ?>
                                                <option value="<?php echo $d; ?>" <?php selected( $code_digits_cfg, $d ); ?>>
                                                    <?php printf( esc_html__( '%d dígitos (%s)', 'aura-suite' ), $d, str_pad( '1', $d, '0', STR_PAD_LEFT ) ); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div id="js-code-preview-box" class="aura-inv-code-preview-box">
                                    <span><?php _e( 'Ejemplo padre:', 'aura-suite' ); ?> <strong id="js-code-preview-main"><?php echo esc_html( $code_prefix_cfg . '-' . str_pad( '9', $code_digits_cfg, '0', STR_PAD_LEFT ) ); ?></strong></span>
                                    <span><?php _e( 'Ejemplo hijo:', 'aura-suite' ); ?> <strong id="js-code-preview-child"><?php echo esc_html( $code_prefix_cfg . '-' . str_pad( '9', $code_digits_cfg, '0', STR_PAD_LEFT ) . '-01' ); ?></strong></span>
                                    <?php if ( ! empty( $code_info['last_main_code'] ) ) : ?>
                                        <span><?php _e( 'Último en BD:', 'aura-suite' ); ?> <strong><?php echo esc_html( $code_info['last_main_code'] ); ?></strong></span>
                                        <span><?php _e( 'Siguiente:', 'aura-suite' ); ?> <strong><?php echo esc_html( $code_info['next_main_code'] ?? '' ); ?></strong></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php _e( 'Al crear un equipo nuevo, el sistema recordará el último código con estas iniciales y sugerirá el siguiente (y si es componente hijo, agregará -01, -02…).', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="set-items-per-page"><?php _e( 'Equipos por página', 'aura-suite' ); ?></label></th>
                            <td>
                                <input type="number" id="set-items-per-page" name="items_per_page" class="form-control" style="max-width:120px;"
                                       min="5" max="100" value="<?php echo esc_attr( $settings['items_per_page'] ); ?>">
                                <p class="description"><?php _e( 'Número de registros por página en los listados (5–100).', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="set-alert-days"><?php _e( 'Días de aviso para mantenimiento', 'aura-suite' ); ?></label></th>
                            <td>
                                <div class="input-group aura-inv-compact-group">
                                    <input type="number" id="set-alert-days" name="alert_days_before" class="form-control"
                                           min="1" max="60" value="<?php echo esc_attr( $settings['alert_days_before'] ); ?>">
                                    <span class="input-group-text"><?php _e( 'días', 'aura-suite' ); ?></span>
                                </div>
                                <p class="description"><?php _e( 'Con cuántos días de anticipación se activa la alerta de mantenimiento próximo.', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="set-loan-max"><?php _e( 'Días máximos de préstamo', 'aura-suite' ); ?></label></th>
                            <td>
                                <div class="input-group aura-inv-compact-group">
                                    <input type="number" id="set-loan-max" name="loan_max_days" class="form-control"
                                           min="1" max="365" value="<?php echo esc_attr( $settings['loan_max_days'] ); ?>">
                                    <span class="input-group-text"><?php _e( 'días', 'aura-suite' ); ?></span>
                                </div>
                                <p class="description"><?php _e( 'Número de días por defecto para la devolución esperada al registrar un préstamo.', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Mostrar equipos retirados', 'aura-suite' ); ?></th>
                            <td>
                                <label class="aura-inv-check-label">
                                    <input type="checkbox" name="show_retired" id="set-show-retired" value="1" <?php checked( $settings['show_retired'] ); ?>>
                                    <?php _e( 'Incluir equipos con estado "Retirado" en los listados', 'aura-suite' ); ?>
                                </label>
                                <p class="description"><?php _e( 'Si está desactivado, los equipos retirados se ocultan por defecto en los listados.', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Moneda y Formato (ISO 4217)', 'aura-suite' ); ?></th>
                            <td>
                                <div class="aura-inv-currency-link-card">
                                    <div>
                                        <div class="aura-inv-currency-main"><?php echo esc_html( $global_currency_lbl ); ?></div>
                                        <div class="aura-inv-currency-sub">
                                            <?php printf( esc_html__( 'Ejemplo de formato activo: %s (heredado de Ajustes Globales → Finanzas)', 'aura-suite' ), '<strong>' . esc_html( $global_currency_sample ) . '</strong>' ); ?>
                                        </div>
                                    </div>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings&tab=finance' ) ); ?>" class="btn btn-glass btn-sm">
                                        <span class="dashicons dashicons-external"></span>
                                        <?php _e( 'Configurar en Ajustes → Finanzas', 'aura-suite' ); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class="aura-inv-form-actions">
                        <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-btn-save-general">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e( 'Guardar configuración', 'aura-suite' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div><!-- /tab general -->

        <!-- ══════════════════════════════════════════════════════
             TAB 3: NOTIFICACIONES
        ══════════════════════════════════════════════════════════ -->
        <div class="aura-inv-tab-panel" data-panel="notifications" <?php echo $active_tab === 'notifications' ? '' : 'style="display:none;"'; ?>>
            <div class="aura-glass-card aura-inv-card">
                <h3 class="aura-inv-card-title">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php _e( 'Notificaciones por Correo', 'aura-suite' ); ?>
                </h3>
                <p class="description"><?php _e( 'Configure qué alertas se envían por correo y a qué destinatarios.', 'aura-suite' ); ?></p>

                <div id="js-notif-msg" class="aura-inv-notice" style="display:none;"></div>

                <form id="js-form-notifications" novalidate>
                    <table class="form-table aura-inv-settings-table" role="presentation">
                        <tr>
                            <th scope="row"><?php _e( 'Alertas de mantenimiento', 'aura-suite' ); ?></th>
                            <td>
                                <label class="aura-inv-check-label">
                                    <input type="checkbox" name="email_alerts" id="set-email-alerts" value="1" <?php checked( $settings['email_alerts'] ); ?>>
                                    <?php _e( 'Enviar correo cuando un equipo se acerque a su fecha de mantenimiento', 'aura-suite' ); ?>
                                </label>
                                <p class="description">
                                    <?php printf(
                                        __( 'Los correos se envían al responsable, administradores y correo del sitio (%s).', 'aura-suite' ),
                                        '<strong>' . esc_html( get_option( 'admin_email' ) ) . '</strong>'
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="set-email-extra"><?php _e( 'Correos adicionales', 'aura-suite' ); ?></label></th>
                            <td>
                                <textarea id="set-email-extra" name="email_extra" class="form-control" rows="3"
                                          placeholder="uno@ejemplo.com&#10;otro@ejemplo.com"><?php echo esc_textarea( $settings['email_extra'] ); ?></textarea>
                                <p class="description"><?php _e( 'Un correo por línea. Se agregarán a todos los envíos de alerta del módulo.', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Tipos de alerta', 'aura-suite' ); ?></th>
                            <td>
                                <div class="aura-inv-alert-types">
                                    <div class="aura-inv-alert-item aura-inv-alert-info">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <div>
                                            <strong><?php _e( 'Mantenimiento próximo', 'aura-suite' ); ?></strong>
                                            <p><?php printf( __( 'Aviso %d días antes de la fecha programada.', 'aura-suite' ), esc_html( $settings['alert_days_before'] ) ); ?></p>
                                        </div>
                                    </div>
                                    <div class="aura-inv-alert-item aura-inv-alert-warning">
                                        <span class="dashicons dashicons-warning"></span>
                                        <div>
                                            <strong><?php _e( 'Mantenimiento vencido', 'aura-suite' ); ?></strong>
                                            <p><?php _e( 'Aviso el día en que se venció la fecha de mantenimiento.', 'aura-suite' ); ?></p>
                                        </div>
                                    </div>
                                    <div class="aura-inv-alert-item aura-inv-alert-danger">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <div>
                                            <strong><?php _e( 'Préstamo vencido', 'aura-suite' ); ?></strong>
                                            <p><?php _e( 'Aviso cuando un préstamo no fue devuelto en la fecha esperada.', 'aura-suite' ); ?></p>
                                        </div>
                                    </div>
                                    <div class="aura-inv-alert-item aura-inv-alert-danger">
                                        <span class="dashicons dashicons-flag"></span>
                                        <div>
                                            <strong><?php _e( 'Equipo dañado en préstamo', 'aura-suite' ); ?></strong>
                                            <p><?php _e( 'Aviso cuando se registra un checkin con estado "dañado".', 'aura-suite' ); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class="aura-inv-form-actions" style="flex-wrap:wrap;">
                        <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-btn-save-notif">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e( 'Guardar notificaciones', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="btn btn-glass" id="js-btn-test-email">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e( 'Enviar correo de prueba', 'aura-suite' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="aura-glass-card aura-inv-card" style="margin-top:20px;">
                <h3 class="aura-inv-card-title">
                    <span class="dashicons dashicons-smartphone" style="color:#16a34a;"></span>
                    <?php _e( 'WhatsApp para Prestatarios Externos', 'aura-suite' ); ?>
                </h3>
                <div class="alert-card alert-warning" style="margin:0;">
                    <span class="dashicons dashicons-migrate"></span>
                    <div style="flex:1;">
                        <strong><?php _e( 'Configuración movida a Ajustes Globales', 'aura-suite' ); ?></strong>
                        <p style="margin:4px 0 0;font-size:12.5px;"><?php _e( 'La integración de WhatsApp ahora se administra de forma centralizada para todo Aura Suite.', 'aura-suite' ); ?></p>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings' ) ); ?>" class="btn btn-glass btn-sm">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e( 'Ir a Ajustes → WhatsApp', 'aura-suite' ); ?>
                    </a>
                </div>
            </div>
        </div><!-- /tab notifications -->

        <!-- ══════════════════════════════════════════════════════
             TAB 4: GOOGLE CALENDAR
        ══════════════════════════════════════════════════════════ -->
        <div class="aura-inv-tab-panel" data-panel="gcal" <?php echo $active_tab === 'gcal' ? '' : 'style="display:none;"'; ?>>
            <div class="aura-glass-card aura-inv-card">
                <h3 class="aura-inv-card-title">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e( 'Integración Google Calendar', 'aura-suite' ); ?>
                </h3>

                <div class="alert-card alert-info" style="margin:0 0 20px;">
                    <span class="dashicons dashicons-migrate"></span>
                    <div style="flex:1;">
                        <strong><?php _e( 'Configuración movida a Ajustes Globales', 'aura-suite' ); ?></strong>
                        <p style="margin:4px 0 0;font-size:12.5px;"><?php _e( 'Las credenciales Service Account JSON y recordatorios se configuran en Ajustes Globales → Google Calendar.', 'aura-suite' ); ?></p>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings' ) ); ?>" class="btn btn-glass btn-sm">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e( 'Ir a Ajustes → Google Calendar', 'aura-suite' ); ?>
                    </a>
                </div>

                <?php
                $gcal_resolved  = class_exists( 'Aura_Inventory_Google_Calendar' ) ? get_option( Aura_Inventory_Google_Calendar::CAL_ID_OPTION, '' ) : '';
                $gcal_share_url = $gcal_resolved ? 'https://calendar.google.com/calendar/render?cid=' . rawurlencode( $gcal_resolved ) : '';
                $gcal_enabled   = class_exists( 'Aura_Inventory_Google_Calendar' ) && Aura_Inventory_Google_Calendar::is_enabled();
                ?>

                <?php if ( $gcal_enabled && $gcal_resolved ) : ?>
                <div class="alert-card alert-info" style="margin-bottom:20px;">
                    <span class="dashicons dashicons-yes-alt" style="color:#16a34a;"></span>
                    <div style="flex:1;">
                        <strong><?php echo esc_html( Aura_Inventory_Google_Calendar::CALENDAR_NAME ); ?></strong>
                        <span style="font-size:12px;opacity:.8;"> · <?php _e( 'activo y configurado', 'aura-suite' ); ?></span>
                    </div>
                    <a href="<?php echo esc_url( $gcal_share_url ); ?>" target="_blank" rel="noopener" class="btn btn-indigo btn-sm">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php _e( 'Agregar a Google Calendar', 'aura-suite' ); ?>
                    </a>
                </div>
                <?php elseif ( ! $gcal_enabled ) : ?>
                <div class="alert-card alert-warning" style="margin-bottom:20px;">
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php _e( 'La integración no está activa. Configúrala en Ajustes globales.', 'aura-suite' ); ?></span>
                </div>
                <?php endif; ?>

                <?php
                $last_sync = get_option( 'aura_gcal_last_sync_status', '' );
                if ( $last_sync ) :
                    $is_ok     = ( strpos( $last_sync, 'ok:' ) === 0 );
                    $sync_info = $is_ok ? substr( $last_sync, 3 ) : substr( $last_sync, 6 );
                ?>
                <p style="font-size:13px;" class="<?php echo $is_ok ? 'aura-inv-text-ok' : 'aura-inv-text-danger'; ?>">
                    <span class="dashicons <?php echo $is_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                    <?php _e( 'Último sync:', 'aura-suite' ); ?>
                    <strong><?php echo esc_html( $sync_info ); ?></strong>
                </p>
                <?php endif; ?>

                <div id="js-gcal-msg" class="aura-inv-notice" style="display:none;margin-bottom:12px;"></div>
                <div class="aura-inv-form-actions">
                    <button type="button" class="btn btn-glass" id="js-btn-gcal-resync">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e( 'Resincronizar todos los equipos con Google Calendar', 'aura-suite' ); ?>
                    </button>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php _e( 'Crea o actualiza en Google Calendar los eventos de mantenimiento de todos los equipos con fecha programada.', 'aura-suite' ); ?>
                </p>
            </div>
        </div><!-- /tab gcal -->

        <!-- ══════════════════════════════════════════════════════
             TAB 5: FINANZAS
        ══════════════════════════════════════════════════════════ -->
        <div class="aura-inv-tab-panel" data-panel="finanzas" <?php echo $active_tab === 'finanzas' ? '' : 'style="display:none;"'; ?>>
            <div class="aura-glass-card aura-inv-card">
                <h3 class="aura-inv-card-title">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php _e( 'Integración Contable con Finanzas', 'aura-suite' ); ?>
                </h3>
                <p class="description"><?php _e( 'Conecta los gastos de mantenimiento preventivo/correctivo del inventario directamente con el libro de egresos de Finanzas.', 'aura-suite' ); ?></p>

                <div id="js-finanzas-msg" class="aura-inv-notice" style="display:none;"></div>

                <?php if ( empty( $finance_cats_settings ) ) : ?>
                <div class="alert-card alert-warning" style="margin:0 0 18px;">
                    <span class="dashicons dashicons-warning"></span>
                    <div style="flex:1;">
                        <strong><?php _e( 'No se encontraron categorías de gasto activas en Finanzas', 'aura-suite' ); ?></strong>
                        <p style="margin:4px 0 0;font-size:12.5px;"><?php _e( 'Crea al menos una categoría de tipo "Gasto/Egreso" en el módulo de Finanzas (por ejemplo: "Mantenimiento de Equipos") para poder vincular gastos automáticamente.', 'aura-suite' ); ?></p>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-categories' ) ); ?>" class="btn btn-glass btn-sm">
                        <?php _e( 'Ir a Categorías de Finanzas', 'aura-suite' ); ?>
                    </a>
                </div>
                <?php endif; ?>

                <form id="js-form-finanzas" novalidate>
                    <table class="form-table aura-inv-settings-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="set-finance-cat"><?php _e( 'Categoría de gasto por defecto', 'aura-suite' ); ?></label></th>
                            <td>
                                <div class="input-group" style="max-width:460px;">
                                    <select id="set-finance-cat" name="finance_category_id" class="form-select">
                                        <option value="0"><?php _e( '— Sin categoría por defecto —', 'aura-suite' ); ?></option>
                                        <?php foreach ( $finance_cats_settings as $fc ) : ?>
                                            <option value="<?php echo esc_attr( $fc->id ); ?>" <?php selected( $settings['finance_category_id'], $fc->id ); ?>>
                                                <?php echo esc_html( $fc->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-categories' ) ); ?>" class="btn btn-glass" data-tooltip="<?php esc_attr_e( 'Administrar categorías de egresos en Finanzas', 'aura-suite' ); ?>">
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                </div>
                                <p class="description">
                                    <?php _e( 'Cuando elijas una categoría aquí, al registrar un mantenimiento nuevo con costo > 0 esta categoría vendrá pre-seleccionada y se activará automáticamente el envío a Finanzas. Si lo dejas vacío, deberás elegir la categoría manualmente en cada mantenimiento.', 'aura-suite' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="set-auto-approve"><?php _e( 'Estado de la transacción creada', 'aura-suite' ); ?></label></th>
                            <td>
                                <select id="set-auto-approve" name="auto_approve_transactions" class="form-select" style="max-width:340px;">
                                    <option value="approved" <?php selected( $settings['auto_approve_transactions'], 'approved' ); ?>><?php _e( 'Aprobada automáticamente', 'aura-suite' ); ?></option>
                                    <option value="pending" <?php selected( $settings['auto_approve_transactions'], 'pending' ); ?>><?php _e( 'Pendiente de aprobación', 'aura-suite' ); ?></option>
                                </select>
                                <p class="description"><?php _e( 'Si eliges "Aprobada", impactará de inmediato el balance de egresos; si eliges "Pendiente", quedará en revisión en la cola de Finanzas.', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div class="aura-inv-form-actions">
                        <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-btn-save-finanzas">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e( 'Guardar configuración de finanzas', 'aura-suite' ); ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-transactions' ) ); ?>" class="btn btn-glass">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php _e( 'Ver Egresos en Finanzas', 'aura-suite' ); ?>
                        </a>
                    </div>
                </form>
            </div>

            <div class="aura-glass-card aura-inv-card" style="margin-top:20px;">
                <h3 class="aura-inv-card-title"><span class="dashicons dashicons-info"></span> <?php _e( 'Cómo funciona la integración', 'aura-suite' ); ?></h3>
                <div class="aura-inv-alert-types">
                    <div class="aura-inv-alert-item aura-inv-alert-info">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <div>
                            <strong><?php _e( 'Mantenimiento con costo', 'aura-suite' ); ?></strong>
                            <p><?php _e( 'Al registrar un mantenimiento con costo mayor a cero, se habilita la creación automática del gasto en Finanzas.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-inv-alert-item aura-inv-alert-info">
                        <span class="dashicons dashicons-admin-links"></span>
                        <div>
                            <strong><?php _e( 'Vínculo permanente', 'aura-suite' ); ?></strong>
                            <p><?php _e( 'El ID de la transacción financiera queda trazado directamente en el mantenimiento.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-inv-alert-item aura-inv-alert-warning">
                        <span class="dashicons dashicons-visibility"></span>
                        <div>
                            <strong><?php _e( 'Trazabilidad contable', 'aura-suite' ); ?></strong>
                            <p><?php _e( 'Los gastos creados desde Inventario aparecen identificados en el módulo financiero.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /tab finanzas -->

        <?php if ( class_exists( 'Aura_UI' ) && method_exists( 'Aura_UI', 'render_footer' ) ) : ?>
            <?php Aura_UI::render_footer(); ?>
        <?php else : ?>
            <div class="adp-footer">
                <span>Aura Business Suite · <?php _e( 'Configuración de Inventario', 'aura-suite' ); ?></span>
                <span>Aura Design System</span>
            </div>
        <?php endif; ?>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<!-- ══════════════════════════════════════════════════════
     MODAL — EDITAR CATEGORÍA (Item 26)
══════════════════════════════════════════════════════════ -->
<div id="js-modal-edit-cat" class="aura-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="js-edit-cat-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:580px;">
        <div class="aura-modal-header">
            <h3 id="js-edit-cat-title" class="aura-modal-title">
                <span class="dashicons dashicons-edit"></span>
                <?php _e( 'Editar categoría', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" id="js-edit-cat-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <form id="js-form-edit-category" novalidate style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <input type="hidden" id="edit-cat-term-id" name="term_id">
            <div class="aura-modal-body">
                <div id="js-edit-cat-msg" class="aura-inv-notice" style="display:none;"></div>

                <div class="aura-inv-field-group">
                    <div class="aura-inv-field">
                        <label for="edit-cat-name" class="aura-inv-label"><?php _e( 'Nombre *', 'aura-suite' ); ?></label>
                        <input type="text" id="edit-cat-name" name="name" class="form-control" required>
                    </div>
                    <div class="aura-inv-field">
                        <label for="edit-cat-slug" class="aura-inv-label"><?php _e( 'Slug', 'aura-suite' ); ?></label>
                        <input type="text" id="edit-cat-slug" name="slug" class="form-control">
                        <p class="description" style="margin:2px 0 0;font-size:11.5px;"><?php _e( 'Identificador URL automático si se deja vacío.', 'aura-suite' ); ?></p>
                    </div>
                </div>

                <div class="aura-inv-field" style="margin-bottom:14px;">
                    <label for="edit-cat-description" class="aura-inv-label"><?php _e( 'Descripción', 'aura-suite' ); ?></label>
                    <textarea id="edit-cat-description" name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="aura-inv-field" style="margin-bottom:14px;">
                    <label for="edit-cat-interval-type" class="aura-inv-label"><?php _e( 'Intervalo de mantenimiento', 'aura-suite' ); ?></label>
                    <select id="edit-cat-interval-type" name="interval_type" class="form-select">
                        <option value="none"><?php _e( 'Sin programa de mantenimiento', 'aura-suite' ); ?></option>
                        <option value="time"><?php _e( 'Por tiempo (meses)', 'aura-suite' ); ?></option>
                        <option value="hours"><?php _e( 'Por horas de uso', 'aura-suite' ); ?></option>
                        <option value="both"><?php _e( 'Tiempo + horas', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="aura-inv-field-group">
                    <div class="aura-inv-field" id="edit-cat-months-wrap" style="display:none;">
                        <label for="edit-cat-interval-months" class="aura-inv-label"><?php _e( 'Cada cuántos meses', 'aura-suite' ); ?></label>
                        <input type="number" id="edit-cat-interval-months" name="interval_months" min="1" max="120" class="form-control">
                    </div>

                    <div class="aura-inv-field" id="edit-cat-hours-wrap" style="display:none;">
                        <label for="edit-cat-interval-hours" class="aura-inv-label"><?php _e( 'Cada cuántas horas', 'aura-suite' ); ?></label>
                        <input type="number" id="edit-cat-interval-hours" name="interval_hours" min="1" max="10000" class="form-control">
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-glass" id="js-edit-cat-close-btn"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-btn-update-cat">
                    <span class="dashicons dashicons-saved"></span>
                    <?php _e( 'Guardar cambios', 'aura-suite' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var auraInvSettings = {
    ajaxurl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
    nonce:   <?php echo wp_json_encode( $nonce ); ?>,
    txt: {
        confirm_delete: <?php echo wp_json_encode( __( '¿Eliminar esta categoría? (Solo si no tiene equipos asignados)', 'aura-suite' ) ); ?>,
        error_name:     <?php echo wp_json_encode( __( 'El nombre es obligatorio.', 'aura-suite' ) ); ?>,
        saving:         <?php echo wp_json_encode( __( 'Guardando…', 'aura-suite' ) ); ?>,
        installing:     <?php echo wp_json_encode( __( 'Instalando…', 'aura-suite' ) ); ?>,
        adding:         <?php echo wp_json_encode( __( 'Agregando…', 'aura-suite' ) ); ?>
    },
    interval_labels: <?php echo wp_json_encode( $interval_labels ); ?>,
    have_categories: <?php echo wp_json_encode( ! empty( $categories ) ); ?>
};
</script>
