<?php
/**
 * Plantilla del Directorio Global de Terceros y Entidades Maestras
 *
 * Cumple rigurosamente con el Prompt Maestro Global de UX/UI, Responsive Design,
 * Input Groups, Child Rows, Tooltips Flotantes y Modo Oscuro de Aura Business Suite.
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 1.8.3
 */

if (!defined('ABSPATH')) {
    exit;
}

$can_view   = current_user_can('manage_options') || current_user_can('aura_third_parties_view') || current_user_can('aura_third_parties_create') || current_user_can('aura_third_parties_edit') || current_user_can('aura_third_parties_delete') || current_user_can('aura_finance_create') || current_user_can('aura_finance_view_all');
if (!$can_view) {
    wp_die(__('No tienes permisos suficientes para acceder al Directorio de Terceros.', 'aura-suite'));
}

$can_create = current_user_can('manage_options') || current_user_can('aura_third_parties_create');
$can_edit   = current_user_can('manage_options') || current_user_can('aura_third_parties_edit');
$can_delete = current_user_can('manage_options') || current_user_can('aura_third_parties_delete');
$can_create_user = current_user_can('manage_options') || current_user_can('create_users') || current_user_can('aura_third_parties_create_wp_user') || current_user_can('aura_admin_users_create');

$third_parties = Aura_Third_Parties::get_third_parties(true);
$kpis = Aura_Third_Parties::get_kpis();

$party_type_labels = array(
    'company'                 => __('Empresa', 'aura-suite'),
    'store'                   => __('Tienda', 'aura-suite'),
    'organization_foundation' => __('Fundación', 'aura-suite'),
    'person'                  => __('Persona Natural', 'aura-suite'),
    'religious'               => __('Entidad Religiosa', 'aura-suite'),
    'other'                   => __('Otra Entidad', 'aura-suite'),
);

$party_type_icons = array(
    'company'                 => 'dashicons-building',
    'store'                   => 'dashicons-cart',
    'organization_foundation' => 'dashicons-heart',
    'person'                  => 'dashicons-businessman',
    'religious'               => 'dashicons-location-alt',
    'other'                   => 'dashicons-category',
);

$party_type_emojis = array(
    'company'                 => '🏢',
    'store'                   => '🛒',
    'organization_foundation' => '🏛️',
    'person'                  => '👤',
    'religious'               => '⛪',
    'other'                   => '🏷️',
);

$party_type_descriptions = array(
    'company'                 => __('Empresa / Sociedad Jurídica: Sociedades comerciales, corporaciones y personas jurídicas con registro mercantil.', 'aura-suite'),
    'store'                   => __('Tienda / Local Comercial: Establecimientos comerciales minoristas o mayoristas, locales y proveedores de insumos.', 'aura-suite'),
    'organization_foundation' => __('Fundación / ONG: Organizaciones no gubernamentales, fundaciones e instituciones benéficas sin ánimo de lucro.', 'aura-suite'),
    'person'                  => __('Persona Natural: Contratistas, clientes, prestatarios, lectores de biblioteca o particulares.', 'aura-suite'),
    'religious'               => __('Entidad Religiosa: Parroquias, diócesis, templos, iglesias y congregaciones de culto.', 'aura-suite'),
    'other'                   => __('Otra Entidad: Prestaciones de servicios públicos, entidades municipales o instituciones no catalogadas.', 'aura-suite'),
);

$initial_tab = sanitize_key($_GET['type'] ?? '');
if (!in_array($initial_tab, array('', 'company', 'store', 'organization_foundation', 'person', 'religious', 'other', 'inactive'), true)) {
    $initial_tab = '';
}
?>

<div class="wrap aura-app-context aura-tp-wrapper">
    <div class="aura-layout">
        <main class="aura-content">
            <!-- ── CABECERA PRINCIPAL CANÓNICA (HERO GLASS CARD) ──────────── -->
            <header class="aura-page-header hero-card aura-glass-card fade-up">
                <div class="aura-header-left" style="display: flex; align-items: center; gap: 18px; flex: 1 1 auto; min-width: 0;">
                    <div class="aura-page-header__icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="aura-header-text" style="flex: 1 1 auto; min-width: 0;">
                        <div class="aura-title-with-badge" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <h1 class="aura-page-title" style="margin: 0; color: #ffffff !important; font-weight: 800; font-size: 1.45rem; line-height: 1.25;"><?php esc_html_e('Directorio de Terceros', 'aura-suite'); ?></h1>
                            <span class="badge badge-blue">
                                <span class="pulse-dot"></span>
                                <strong id="header-tp-total"><?php echo esc_html($kpis['total'] ?? 0); ?></strong> <?php esc_html_e('registrados', 'aura-suite'); ?>
                            </span>
                            <span class="badge badge-gray">
                                🏢 <strong id="header-tp-company"><?php echo esc_html($kpis['company'] ?? 0); ?></strong> <?php esc_html_e('empresas', 'aura-suite'); ?>
                            </span>
                        </div>
                        <p class="aura-page-subtitle hero-desc" style="margin: 6px 0 0; color: rgba(255, 255, 255, 0.88) !important; font-size: 0.88rem; line-height: 1.45;"><?php esc_html_e('Gestión centralizada de Empresas, Tiendas, Fundaciones, Entidades Religiosas, Personas Naturales y otras entidades vinculadas a toda la suite.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <div class="aura-header-right" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto; z-index: 10;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-suite' ) ); ?>" class="btn btn-glass btn-lift">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <span><?php esc_html_e('Dashboard', 'aura-suite'); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-import&type=third_parties' ) ); ?>" class="btn btn-glass btn-lift">
                        <span class="dashicons dashicons-upload"></span>
                        <span><?php esc_html_e('Importar', 'aura-suite'); ?></span>
                    </a>
                    <button type="button" class="btn btn-glass btn-lift" id="aura-export-third-parties-btn" onclick="exportAuraThirdPartiesCSV(this)">
                        <span class="dashicons dashicons-download"></span>
                        <span><?php esc_html_e('Exportar CSV', 'aura-suite'); ?></span>
                    </button>
                    <?php if ($can_create) : ?>
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-tp-btn-new">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <span><?php esc_html_e('Registrar Tercero / Empresa', 'aura-suite'); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- ── BARRA DE NAVEGACIÓN DE PESTAÑAS (TABS NAVBAR) ───────────── -->
            <nav class="aura-navbar aura-nav aura-glass-card aura-tp-navbar">
                <button type="button" class="aura-navbar-item aura-tab-btn<?php echo $initial_tab === '' ? ' is-active active' : ''; ?>" data-type="" data-tab-key="all">
                    <span class="dashicons dashicons-grid-view"></span>
                    <?php esc_html_e('Todos', 'aura-suite'); ?>
                    <span class="aura-tab-count" id="tab-count-all"><?php echo esc_html($kpis['total'] ?? 0); ?></span>
                </button>
                <button type="button" class="aura-navbar-item aura-tab-btn<?php echo $initial_tab === 'company' ? ' is-active active' : ''; ?>" data-type="company" data-tab-key="company">
                    <span class="dashicons dashicons-building"></span>
                    <?php esc_html_e('Empresas', 'aura-suite'); ?>
                    <span class="aura-tab-count" id="tab-count-company"><?php echo esc_html($kpis['company'] ?? 0); ?></span>
                </button>
                <button type="button" class="aura-navbar-item aura-tab-btn<?php echo $initial_tab === 'store' ? ' is-active active' : ''; ?>" data-type="store" data-tab-key="store">
                    <span class="dashicons dashicons-cart"></span>
                    <?php esc_html_e('Tiendas', 'aura-suite'); ?>
                    <span class="aura-tab-count" id="tab-count-store"><?php echo esc_html($kpis['store'] ?? 0); ?></span>
                </button>
                <button type="button" class="aura-navbar-item aura-tab-btn<?php echo $initial_tab === 'organization_foundation' ? ' is-active active' : ''; ?>" data-type="organization_foundation" data-tab-key="organization_foundation">
                    <span class="dashicons dashicons-heart"></span>
                    <?php esc_html_e('Fundaciones', 'aura-suite'); ?>
                    <span class="aura-tab-count" id="tab-count-foundation"><?php echo esc_html($kpis['organization_foundation'] ?? 0); ?></span>
                </button>
                <button type="button" class="aura-navbar-item aura-tab-btn<?php echo $initial_tab === 'person' ? ' is-active active' : ''; ?>" data-type="person" data-tab-key="person">
                    <span class="dashicons dashicons-businessman"></span>
                    <?php esc_html_e('Personas', 'aura-suite'); ?>
                    <span class="aura-tab-count" id="tab-count-person"><?php echo esc_html($kpis['person'] ?? 0); ?></span>
                </button>
                <button type="button" class="aura-navbar-item aura-tab-btn<?php echo $initial_tab === 'religious' ? ' is-active active' : ''; ?>" data-type="religious" data-tab-key="religious">
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php esc_html_e('Religiosas', 'aura-suite'); ?>
                    <span class="aura-tab-count" id="tab-count-religious"><?php echo esc_html($kpis['religious'] ?? 0); ?></span>
                </button>
                <button type="button" class="aura-navbar-item aura-tab-btn<?php echo $initial_tab === 'other' ? ' is-active active' : ''; ?>" data-type="other" data-tab-key="other">
                    <span class="dashicons dashicons-category"></span>
                    <?php esc_html_e('Otras', 'aura-suite'); ?>
                    <span class="aura-tab-count" id="tab-count-other"><?php echo esc_html($kpis['other'] ?? 0); ?></span>
                </button>
                <button type="button" class="aura-navbar-item aura-tab-btn aura-tab-btn--inactive<?php echo $initial_tab === 'inactive' ? ' is-active active' : ''; ?>" data-type="inactive" data-tab-key="inactive" style="margin-left:auto;">
                    <span class="dashicons dashicons-hidden" style="color:var(--aura-danger,#ef4444);"></span>
                    <span style="color:var(--aura-danger,#ef4444);font-weight:600;"><?php esc_html_e('Desactivados', 'aura-suite'); ?></span>
                    <span class="aura-tab-count aura-tab-count--danger" id="tab-count-inactive"><?php echo esc_html($kpis['inactive'] ?? 0); ?></span>
                </button>
            </nav>

            <!-- ── KPI METRICS CARDS CANÓNICAS ULTRA-COMPACTAS (CON AYUDAS CONTEXTUALES) ── -->
            <div class="aura-tp-kpi-grid">
                <!-- Total Registrados -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--total card-lift">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Total Registrados', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Cantidad global de todas las entidades y personas registradas en el sistema.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Cantidad global de todas las entidades y personas registradas en el sistema.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Cantidad global de todas las entidades y personas registradas en el sistema.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-total"><?php echo esc_html($kpis['total'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                </article>

                <!-- Empresas -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--company card-lift">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Empresas / Jurídicos', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Sociedades comerciales, corporaciones y personas jurídicas activas.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Sociedades comerciales, corporaciones y personas jurídicas activas.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Sociedades comerciales, corporaciones y personas jurídicas activas.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-company"><?php echo esc_html($kpis['company'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-building"></span>
                    </div>
                </article>

                <!-- Tiendas -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--store card-lift">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Tiendas / Comercios', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Establecimientos comerciales, locales y proveedores de insumos.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Establecimientos comerciales, locales y proveedores de insumos.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Establecimientos comerciales, locales y proveedores de insumos.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-store"><?php echo esc_html($kpis['store'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                </article>

                <!-- Fundaciones -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--foundation card-lift">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Fundaciones / ONGs', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Organizaciones no gubernamentales, fundaciones e instituciones sin ánimo de lucro.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Organizaciones no gubernamentales, fundaciones e instituciones sin ánimo de lucro.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Organizaciones no gubernamentales, fundaciones e instituciones sin ánimo de lucro.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-foundation"><?php echo esc_html($kpis['organization_foundation'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-heart"></span>
                    </div>
                </article>

                <!-- Personas -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--person card-lift">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Personas Naturales', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Personas naturales, contratistas, lectores de biblioteca o prestatarios.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Personas naturales, contratistas, lectores de biblioteca o prestatarios.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Personas naturales, contratistas, lectores de biblioteca o prestatarios.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-person"><?php echo esc_html($kpis['person'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                </article>

                <!-- Entidades Religiosas -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--religious card-lift">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Entidades Religiosas', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Iglesias, parroquias, templos y otras instituciones religiosas.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Iglesias, parroquias, templos y otras instituciones religiosas.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Iglesias, parroquias, templos y otras instituciones religiosas.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-religious"><?php echo esc_html($kpis['religious'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-location-alt"></span>
                    </div>
                </article>

                <!-- Otras Entidades -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--other card-lift">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Otras Entidades', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Servicios varios como recolectores de basura, entidades municipales u otras prestaciones no clasificadas.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Servicios varios como recolectores de basura, entidades municipales u otras prestaciones no clasificadas.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Servicios varios como recolectores de basura, entidades municipales u otras prestaciones no clasificadas.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-other"><?php echo esc_html($kpis['other'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-category"></span>
                    </div>
                </article>

                <!-- Desactivados / Inactivos -->
                <article class="aura-stat-card aura-tp-kpi-card aura-tp-kpi-card--inactive card-lift" id="aura-kpi-card-inactive" style="cursor:pointer;" title="<?php esc_attr_e('Clic para ver la tabla de terceros desactivados', 'aura-suite'); ?>">
                    <div class="aura-tp-kpi-card__info">
                        <span class="aura-tp-kpi-card__label-wrap">
                            <span><?php esc_html_e('Desactivados', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e('Terceros inactivos u ocultos para nuevos registros. Su historial contable se conserva y pueden reactivarse en cualquier momento.', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-editor-help aura-help-icon" data-tooltip="<?php esc_attr_e('Terceros inactivos u ocultos para nuevos registros. Su historial contable se conserva y pueden reactivarse en cualquier momento.', 'aura-suite'); ?>"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Terceros inactivos u ocultos para nuevos registros. Su historial contable se conserva y pueden reactivarse en cualquier momento.', 'aura-suite'); ?></span>
                            </span>
                        </span>
                        <div class="aura-tp-kpi-card__value" id="kpi-tp-inactive"><?php echo esc_html($kpis['inactive'] ?? 0); ?></div>
                    </div>
                    <div class="aura-tp-kpi-card__icon-wrap">
                        <span class="dashicons dashicons-hidden"></span>
                    </div>
                </article>
            </div>

            <!-- ── TABLA PRINCIPAL Y TOOLBAR ───────────────────────────────────── -->
            <div class="aura-glass-card aura-tp-card">
                <div class="aura-tp-toolbar">
                    <div class="aura-tp-toolbar-title">
                        <span class="dashicons dashicons-list-view"></span>
                        <span><?php esc_html_e('Directorio y Catálogo de Entidades', 'aura-suite'); ?></span>
                    </div>

                    <!-- Filtro por Estado (Todos / Activos / Desactivados) -->
                    <div class="aura-tp-status-toggles" id="aura-tp-status-filter-group" role="group" aria-label="<?php esc_attr_e('Filtro de Estado', 'aura-suite'); ?>">
                        <button type="button" class="aura-tp-status-btn<?php echo $initial_tab !== 'inactive' ? ' is-active' : ''; ?>" data-status="all" title="<?php esc_attr_e('Ver todos los terceros', 'aura-suite'); ?>">
                            <?php esc_html_e('Todos', 'aura-suite'); ?> <span class="aura-tp-status-badge" id="status-badge-all"><?php echo esc_html($kpis['total'] ?? 0); ?></span>
                        </button>
                        <button type="button" class="aura-tp-status-btn" data-status="1" title="<?php esc_attr_e('Ver únicamente terceros activos para operaciones', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Activos', 'aura-suite'); ?> <span class="aura-tp-status-badge aura-tp-status-badge--success" id="status-badge-active"><?php echo esc_html(($kpis['total'] ?? 0) - ($kpis['inactive'] ?? 0)); ?></span>
                        </button>
                        <button type="button" class="aura-tp-status-btn aura-tp-status-btn--danger<?php echo $initial_tab === 'inactive' ? ' is-active' : ''; ?>" data-status="0" title="<?php esc_attr_e('Ver terceros desactivados / inactivos', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-hidden"></span> <?php esc_html_e('Desactivados', 'aura-suite'); ?> <span class="aura-tp-status-badge aura-tp-status-badge--danger" id="status-badge-inactive"><?php echo esc_html($kpis['inactive'] ?? 0); ?></span>
                        </button>
                    </div>

                    <!-- Buscador y Recarga (Item 17: Buscador Continuo) -->
                    <div class="aura-tp-search-wrap" style="display:flex;align-items:center;gap:8px;">
                        <div class="input-wrap aura-tp-search-group" style="min-width:280px;">
                            <span class="input-icon">🔍</span>
                            <input type="search" id="aura-tp-search-input" class="input-fancy aura-tp-search-input" placeholder="<?php esc_attr_e('Buscar por nombre, NIT, email o teléfono...', 'aura-suite'); ?>">
                        </div>
                        <button type="button" class="btn btn-secondary btn-lift btn-icon" id="aura-tp-refresh-btn" title="<?php esc_attr_e('Recargar listado', 'aura-suite'); ?>" style="height:38px;width:38px;padding:0;display:inline-flex;align-items:center;justify-content:center;">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                </div>

                <!-- Alerta contextual cuando se filtra por desactivados -->
                <div id="aura-tp-inactive-alert" class="aura-alert aura-alert--warning" style="<?php echo $initial_tab === 'inactive' ? 'display:flex;' : 'display:none;'; ?>margin:12px 16px 0;align-items:center;justify-content:space-between;padding:10px 16px;border-radius:8px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);color:#d97706;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="dashicons dashicons-info" style="font-size:20px;"></span>
                        <span><strong><?php esc_html_e('Vista de Terceros Desactivados:', 'aura-suite'); ?></strong> <?php esc_html_e('Estos terceros están ocultos en los selectores de nuevos ingresos y egresos, pero conservan su historial contable. Puedes reactivar cualquiera pulsando el botón verde.', 'aura-suite'); ?></span>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm btn-lift" id="aura-tp-btn-show-all-from-alert" style="cursor:pointer;"><?php esc_html_e('Ver todos', 'aura-suite'); ?></button>
                </div>

                <div class="aura-dt-wrapper aura-tp-table-wrap">
                    <table id="aura-third-parties-table" class="display responsive nowrap aura-areas-table-fullwidth">
                        <thead>
                            <tr>
                                <th style="width:62px;text-align:center;">
                                    <?php esc_html_e('Logo', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Logotipo o fotografía de la entidad. Pasa el cursor o toca para desplegar la previsualización HD ampliada.', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                                <th>
                                    <?php esc_html_e('Razón Social / Nombre', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Nombre legal, razón social o nombre completo del tercero.', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                                <th>
                                    <?php esc_html_e('Nombre Comercial', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Nombre de marca, rótulo comercial o nombre de fantasía.', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                                <th>
                                    <?php esc_html_e('Tipo Entidad', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Clasificación: Empresa, Tienda, Fundación, Persona Natural, Religiosa u Otra.', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                                <th>
                                    <?php esc_html_e('Documento / NIT', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Número de identificación fiscal, NIT, RUT o documento de identidad.', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                                <th>
                                    <?php esc_html_e('Contacto', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Acceso directo a WhatsApp y correo electrónico.', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                                <th style="width:80px;text-align:center;">
                                    <?php esc_html_e('Estado', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Estado operativo de la entidad: Activo (habilitado) o Inactivo (deshabilitado temporalmente).', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                                <th style="width:110px;text-align:right;">
                                    <?php esc_html_e('Acciones', 'aura-suite'); ?>
                                    <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                        <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                        <span class="aura-tooltip-content"><?php esc_html_e('Operaciones disponibles: Ficha 360°, edición completa y activación o desactivación.', 'aura-suite'); ?></span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($third_parties)) : ?>
                                <?php foreach ($third_parties as $tp) :
                                    $ptype = !empty($tp['party_type']) ? $tp['party_type'] : 'company';
                                    $ptype_label = $party_type_labels[$ptype] ?? $party_type_labels['company'];
                                    $ptype_icon = $party_type_icons[$ptype] ?? 'dashicons-building';
                                    $ptype_desc = $party_type_descriptions[$ptype] ?? $party_type_descriptions['company'];
                                    $logo_url = !empty($tp['logo_url']) ? $tp['logo_url'] : '';
                                    $is_active = (int) ($tp['is_active'] ?? 1) === 1;
                                    $display_name = !empty($tp['commercial_name']) && $tp['commercial_name'] !== $tp['full_name'] ? $tp['commercial_name'] : $tp['full_name'];
                                ?>
                                    <tr data-id="<?php echo esc_attr($tp['id']); ?>" data-type="<?php echo esc_attr($ptype); ?>" data-active="<?php echo $is_active ? '1' : '0'; ?>">
                                        <td class="aura-table-logo-cell" style="text-align:center;">
                                            <div class="aura-tooltip-wrap aura-table-thumb-wrap">
                                                <?php if ($logo_url) : ?>
                                                    <div class="aura-table-thumb-preview aura-img-preview-trigger" data-img-url="<?php echo esc_url($logo_url); ?>" data-img-title="<?php echo esc_attr($display_name); ?>">
                                                        <img src="<?php echo esc_url($logo_url); ?>" width="40" height="40" alt="<?php echo esc_attr($tp['full_name']); ?>">
                                                    </div>
                                                <?php else : ?>
                                                    <div class="aura-table-thumb-placeholder is-emoji" title="<?php echo esc_attr($ptype_label); ?>">
                                                        <span class="aura-tp-emoji"><?php echo esc_html($party_type_emojis[$ptype] ?? '🏢'); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="aura-tooltip-content">
                                                    <?php if ($logo_url) : ?>
                                                        <div class="aura-floating-img-card">
                                                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($display_name); ?>">
                                                            <span class="aura-floating-img-title"><?php echo esc_html($display_name); ?></span>
                                                            <?php if (!empty($tp['commercial_name']) && $tp['commercial_name'] !== $tp['full_name']) : ?>
                                                                <span class="aura-floating-img-sub"><?php echo esc_html($tp['full_name']); ?></span>
                                                            <?php endif; ?>
                                                            <div class="aura-tip-badges" style="margin-top:6px;justify-content:center;">
                                                                <span class="aura-tip-badge aura-tip-badge--<?php echo esc_attr($ptype); ?>">
                                                                    <span class="aura-tp-pill-emoji"><?php echo esc_html($party_type_emojis[$ptype] ?? '🏢'); ?></span> <?php echo esc_html($ptype_label); ?>
                                                                </span>
                                                                <span class="aura-tip-badge aura-tip-badge--<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                                                    <?php echo $is_active ? esc_html__('Activo', 'aura-suite') : esc_html__('Inactivo', 'aura-suite'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    <?php else : ?>
                                                        <div class="aura-tip-card">
                                                            <div class="aura-tip-card-header">
                                                                <div class="aura-tip-avatar-large is-emoji">
                                                                    <span class="aura-tp-emoji"><?php echo esc_html($party_type_emojis[$ptype] ?? '🏢'); ?></span>
                                                                </div>
                                                                <div class="aura-tip-info">
                                                                    <div class="aura-tip-title"><?php echo esc_html($display_name); ?></div>
                                                                    <?php if (!empty($tp['commercial_name']) && $tp['commercial_name'] !== $tp['full_name']) : ?>
                                                                        <div class="aura-tip-subtitle"><?php echo esc_html($tp['full_name']); ?></div>
                                                                    <?php endif; ?>
                                                                    <div class="aura-tip-badges">
                                                                        <span class="aura-tip-badge aura-tip-badge--<?php echo esc_attr($ptype); ?>">
                                                                            <span class="aura-tp-pill-emoji"><?php echo esc_html($party_type_emojis[$ptype] ?? '🏢'); ?></span> <?php echo esc_html($ptype_label); ?>
                                                                        </span>
                                                                        <span class="aura-tip-badge aura-tip-badge--<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                                                            <?php echo $is_active ? esc_html__('Activo', 'aura-suite') : esc_html__('Inactivo', 'aura-suite'); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="aura-tooltip-wrap aura-tp-name-wrap">
                                                <div class="aura-tp-name-block">
                                                    <strong class="aura-tp-fullname"><?php echo esc_html($tp['full_name']); ?></strong>
                                                    <?php if (!empty($tp['wp_user_id']) && !empty($tp['wp_user_login'])) : ?>
                                                        <span class="aura-badge-wp-user" data-tooltip="<?php echo esc_attr(sprintf(__('Usuario WP vinculado: @%s (%s)', 'aura-suite'), $tp['wp_user_login'] ?? '', $tp['wp_user_email'] ?? '')); ?>">
                                                            <span class="dashicons dashicons-admin-users"></span> WP
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="aura-tooltip-content">
                                                    <div class="aura-tip-card">
                                                        <div class="aura-tip-card-header">
                                                            <div class="aura-tip-avatar-large<?php echo $logo_url ? '' : ' is-emoji'; ?>">
                                                                <?php if ($logo_url) : ?>
                                                                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($display_name); ?>">
                                                                <?php else : ?>
                                                                    <span class="aura-tp-emoji"><?php echo esc_html($party_type_emojis[$ptype] ?? '🏢'); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="aura-tip-info">
                                                                <div class="aura-tip-title"><?php echo esc_html($display_name); ?></div>
                                                                <?php if (!empty($tp['commercial_name']) && $tp['commercial_name'] !== $tp['full_name']) : ?>
                                                                    <div class="aura-tip-subtitle"><?php echo esc_html($tp['full_name']); ?></div>
                                                                <?php endif; ?>
                                                                <div class="aura-tip-badges">
                                                                    <span class="aura-tip-badge aura-tip-badge--<?php echo esc_attr($ptype); ?>">
                                                                        <span class="dashicons <?php echo esc_attr($ptype_icon); ?>"></span> <?php echo esc_html($ptype_label); ?>
                                                                    </span>
                                                                    <span class="aura-tip-badge aura-tip-badge--<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                                                        <?php echo $is_active ? esc_html__('Activo', 'aura-suite') : esc_html__('Inactivo', 'aura-suite'); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="aura-tip-card-body">
                                                            <div class="aura-tip-row">
                                                                <span>🆔 Código / ID:</span>
                                                                <strong>#TP-<?php echo esc_html($tp['id']); ?></strong>
                                                            </div>
                                                            <?php if (!empty($tp['wp_user_id']) && !empty($tp['wp_user_login'])) : ?>
                                                                <div class="aura-tip-row">
                                                                    <span>👤 Usuario WP:</span>
                                                                    <strong>@<?php echo esc_html($tp['wp_user_login'] ?? ''); ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($tp['document_id'])) : ?>
                                                                <div class="aura-tip-row">
                                                                    <span>📄 <?php echo esc_html($tp['tax_id_type'] ?: 'NIT'); ?>:</span>
                                                                    <strong><?php echo esc_html($tp['document_id']); ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($tp['phone'])) : ?>
                                                                <div class="aura-tip-row">
                                                                    <span>📞 Teléfono:</span>
                                                                    <strong><?php echo esc_html($tp['phone']); ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($tp['email'])) : ?>
                                                                <div class="aura-tip-row">
                                                                    <span>✉️ Correo:</span>
                                                                    <strong><?php echo esc_html($tp['email']); ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($tp['address'])) : ?>
                                                                <div class="aura-tip-row">
                                                                    <span>📍 Dirección:</span>
                                                                    <strong><?php echo esc_html($tp['address']); ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($tp['website'])) : ?>
                                                                <div class="aura-tip-row">
                                                                    <span>🌐 Sitio Web:</span>
                                                                    <strong><?php echo esc_html($tp['website']); ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($tp['commercial_name']) && $tp['commercial_name'] !== $tp['full_name']) : ?>
                                                <div class="aura-tooltip-wrap">
                                                    <span class="aura-tp-commname"><?php echo esc_html($tp['commercial_name']); ?></span>
                                                    <div class="aura-tooltip-content">
                                                        <div class="aura-tip-card">
                                                            <div class="aura-tip-card-header">
                                                                <div class="aura-tip-avatar-large">
                                                                    <span class="dashicons dashicons-store"></span>
                                                                </div>
                                                                <div class="aura-tip-info">
                                                                    <div class="aura-tip-title"><?php echo esc_html($tp['commercial_name']); ?></div>
                                                                    <div class="aura-tip-subtitle"><?php echo esc_html($tp['full_name']); ?></div>
                                                                    <div class="aura-tip-badges">
                                                                        <span class="aura-tip-badge aura-tip-badge--<?php echo esc_attr($ptype); ?>">
                                                                            <span class="dashicons <?php echo esc_attr($ptype_icon); ?>"></span> <?php echo esc_html($ptype_label); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="aura-tip-card-body">
                                                                <div class="aura-tip-row">
                                                                    <span>🏷️ Rótulo Comercial:</span>
                                                                    <strong><?php echo esc_html($tp['commercial_name']); ?></strong>
                                                                </div>
                                                                <div class="aura-tip-row">
                                                                    <span>🏢 Razón Social Formal:</span>
                                                                    <strong><?php echo esc_html($tp['full_name']); ?></strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else : ?>
                                                <em class="aura-text-muted">-</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="aura-tooltip-wrap">
                                                <span class="aura-pill is-party-<?php echo esc_attr($ptype); ?>">
                                                    <span class="aura-tp-pill-emoji" style="margin-right:4px;font-size:13px;line-height:1;"><?php echo esc_html($party_type_emojis[$ptype] ?? '🏢'); ?></span>
                                                    <?php echo esc_html($ptype_label); ?>
                                                </span>
                                                <div class="aura-tooltip-content">
                                                    <div class="aura-tip-card">
                                                        <div class="aura-tip-card-header">
                                                            <div class="aura-tip-avatar-large is-emoji">
                                                                <span class="aura-tp-emoji"><?php echo esc_html($party_type_emojis[$ptype] ?? '🏢'); ?></span>
                                                            </div>
                                                            <div class="aura-tip-info">
                                                                <div class="aura-tip-title"><?php echo esc_html($ptype_label); ?></div>
                                                                <div class="aura-tip-subtitle"><?php esc_html_e('Clasificación Maestro', 'aura-suite'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="aura-tip-card-body">
                                                            <div style="color:#cbd5e1;line-height:1.5;">
                                                                <?php echo esc_html($ptype_desc); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($tp['document_id'])) : ?>
                                                <div class="aura-tooltip-wrap">
                                                    <span class="aura-doc-badge">
                                                        <strong><?php echo esc_html($tp['tax_id_type'] ?: 'NIT'); ?>:</strong> <?php echo esc_html($tp['document_id']); ?>
                                                    </span>
                                                    <div class="aura-tooltip-content">
                                                        <div class="aura-tip-card">
                                                            <div class="aura-tip-card-header">
                                                                <div class="aura-tip-avatar-large">
                                                                    <span class="dashicons dashicons-id"></span>
                                                                </div>
                                                                <div class="aura-tip-info">
                                                                    <div class="aura-tip-title"><?php echo esc_html($tp['tax_id_type'] ?: 'NIT'); ?>: <?php echo esc_html($tp['document_id']); ?></div>
                                                                    <div class="aura-tip-subtitle"><?php echo esc_html($display_name); ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="aura-tip-card-body">
                                                                <div class="aura-tip-row">
                                                                    <span>📄 Tipo de Documento:</span>
                                                                    <strong><?php echo esc_html($tp['tax_id_type'] ?: 'NIT'); ?></strong>
                                                                </div>
                                                                <div class="aura-tip-row">
                                                                    <span>🔢 Número Identificación:</span>
                                                                    <strong><?php echo esc_html($tp['document_id']); ?></strong>
                                                                </div>
                                                                <div class="aura-tip-row">
                                                                    <span>🏛️ Titular Registrado:</span>
                                                                    <strong><?php echo esc_html($tp['full_name']); ?></strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else : ?>
                                                <em class="aura-text-muted">-</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $contact_items = array();
                                            if (!empty($tp['phone'])) {
                                                $clean_phone = preg_replace('/[^0-9+]/', '', $tp['phone']);
                                                $wa_phone = ltrim($clean_phone, '+');
                                                $wa_tip = sprintf(__('💬 Abrir chat directo de WhatsApp con %s (%s)', 'aura-suite'), $display_name, $tp['phone']);
                                                $contact_items[] = '<a href="https://wa.me/' . esc_attr($wa_phone) . '" target="_blank" class="aura-contact-link aura-contact-link--wa" data-tooltip="' . esc_attr($wa_tip) . '"><span class="dashicons dashicons-whatsapp"></span> ' . esc_html($tp['phone']) . '</a>';
                                            }
                                            if (!empty($tp['email'])) {
                                                $email_tip = sprintf(__('✉️ Enviar correo electrónico a %s (%s)', 'aura-suite'), $display_name, $tp['email']);
                                                $contact_items[] = '<a href="mailto:' . esc_attr($tp['email']) . '" class="aura-contact-link aura-contact-link--email" data-tooltip="' . esc_attr($email_tip) . '"><span class="dashicons dashicons-email-alt"></span> ' . esc_html($tp['email']) . '</a>';
                                            }
                                            if (!empty($contact_items)) {
                                                echo implode('<br>', $contact_items);
                                            } else {
                                                echo '<em class="aura-text-muted">-</em>';
                                            }
                                            ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <div class="aura-tooltip-wrap">
                                                <?php if ($is_active) : ?>
                                                    <span class="aura-pill is-active"><?php esc_html_e('Activo', 'aura-suite'); ?></span>
                                                <?php else : ?>
                                                    <span class="aura-pill is-inactive"><?php esc_html_e('Inactivo', 'aura-suite'); ?></span>
                                                <?php endif; ?>
                                                <div class="aura-tooltip-content">
                                                    <div class="aura-tip-card">
                                                        <div class="aura-tip-card-header">
                                                            <div class="aura-tip-avatar-large">
                                                                <span class="dashicons <?php echo $is_active ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                                                            </div>
                                                            <div class="aura-tip-info">
                                                                <div class="aura-tip-title"><?php echo $is_active ? esc_html__('Entidad Activa', 'aura-suite') : esc_html__('Entidad Inactiva', 'aura-suite'); ?></div>
                                                                <div class="aura-tip-subtitle"><?php echo esc_html($display_name); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="aura-tip-card-body">
                                                            <div style="color:#cbd5e1;line-height:1.5;">
                                                                <?php echo $is_active 
                                                                    ? esc_html__('✅ Entidad Operativa: Habilitada para transacciones financieras, presupuestos, contratos y biblioteca.', 'aura-suite')
                                                                    : esc_html__('⛔ Entidad Deshabilitada: Bloqueada temporalmente para nuevas operaciones en el sistema.', 'aura-suite'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:right;">
                                            <div class="aura-table-actions">
                                                <button type="button" class="aura-btn-action aura-btn-action--view aura-btn-360" data-id="<?php echo esc_attr($tp['id']); ?>" data-tooltip="<?php esc_attr_e('👁️ Ficha 360°: Resumen integral, balance contable y movimientos vinculados', 'aura-suite'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                                <?php if (!empty($tp['wp_user_id']) && !empty($tp['wp_user_login'])) : ?>
                                                    <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . (int) $tp['wp_user_id'])); ?>" class="aura-btn-action aura-btn-action--wp-user" target="_blank" data-tooltip="<?php echo esc_attr(sprintf(__('👤 Usuario WordPress: @%s (Ver perfil)', 'aura-suite'), $tp['wp_user_login'] ?? '')); ?>">
                                                        <span class="dashicons dashicons-admin-users"></span>
                                                    </a>
                                                <?php elseif ($can_create_user) : ?>
                                                    <button type="button" class="aura-btn-action aura-btn-action--create-wp aura-btn-create-wp" data-id="<?php echo esc_attr($tp['id']); ?>" data-tooltip="<?php esc_attr_e('👤 Crear usuario de WordPress (Suscriptor)', 'aura-suite'); ?>">
                                                        <span class="dashicons dashicons-admin-users"></span>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_edit) : ?>
                                                    <button type="button" class="aura-btn-action aura-btn-action--edit aura-btn-edit" data-id="<?php echo esc_attr($tp['id']); ?>" data-tooltip="<?php esc_attr_e('✏️ Editar Tercero: Actualizar razón social, contacto, identificación y logotipo', 'aura-suite'); ?>">
                                                        <span class="dashicons dashicons-edit"></span>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_delete) : ?>
                                                    <button type="button" class="aura-btn-action aura-btn-action--toggle <?php echo $is_active ? 'aura-btn-action--deactivate' : 'aura-btn-action--activate'; ?> aura-btn-toggle" data-id="<?php echo esc_attr($tp['id']); ?>" data-tooltip="<?php echo $is_active ? esc_attr__('⛔ Desactivar Tercero: Ocultar de nuevos registros manteniendo historial', 'aura-suite') : esc_attr__('✅ Reactivar Tercero: Restablecer operatividad en la suite', 'aura-suite'); ?>">
                                                        <span class="dashicons <?php echo $is_active ? 'dashicons-hidden' : 'dashicons-undo'; ?>"></span>
                                                    </button>
                                                    <button type="button" class="aura-btn-action aura-btn-action--delete aura-btn-hard-delete" data-id="<?php echo esc_attr($tp['id']); ?>" data-name="<?php echo esc_attr($display_name); ?>" data-tooltip="<?php esc_attr_e('🗑️ Eliminar Tercero: Borrar definitivamente de la base de datos sin dejar rastro', 'aura-suite'); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── FOOTER GLOBAL CANÓNICO ─────────────────────────────────── -->
            <div class="adp-footer">
                <p>
                    Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong> &nbsp;|&nbsp; © 2026 AURA Business Suite
                </p>
            </div>
        </main>
    </div>
</div>

<!-- ── MODAL: CREAR / EDITAR TERCERO (INPUT GROUPS BOOTSTRAP Y ARQUITECTURA FLEXBOX) ── -->
<div id="aura-third-party-modal" class="aura-tp-modal-overlay" style="display:none;">
    <div class="aura-tp-modal-content">
        <header class="aura-tp-modal-header">
            <h2 id="aura-tp-modal-title" class="aura-tp-modal-header__title">
                <span class="dashicons dashicons-businessman" style="font-size:22px;width:22px;height:22px;color:#2563eb;"></span>
                <span><?php esc_html_e('Registrar Tercero / Empresa', 'aura-suite'); ?></span>
            </h2>
            <button type="button" class="aura-tp-modal-close" id="aura-tp-modal-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">&times;</button>
        </header>

        <form id="aura-tp-form" style="display:contents;">
            <div class="aura-tp-modal-body">
                <input type="hidden" id="aura_tp_id" name="id" value="">

                <!-- Logo / Foto Uploader -->
                <div class="aura-tp-uploader-box">
                    <div id="aura-tp-logo-preview" class="aura-tp-uploader-preview is-emoji" style="font-size:28px;display:flex;align-items:center;justify-content:center;">
                        <span class="aura-tp-preview-emoji" style="font-size:28px;line-height:1;">🏢</span>
                    </div>
                    <div style="flex-grow:1;">
                        <input type="hidden" id="aura_tp_logo_id" name="logo_id" value="">
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button type="button" class="btn btn-secondary btn-sm btn-lift" id="aura-tp-btn-upload-logo">
                                <span class="dashicons dashicons-camera"></span>
                                <span><?php esc_html_e('Subir Logo / Foto', 'aura-suite'); ?></span>
                            </button>
                            <button type="button" class="btn btn-rose btn-sm btn-lift" id="aura-tp-btn-remove-logo" style="display:none;font-size:12px;">
                                <?php esc_html_e('Quitar imagen', 'aura-suite'); ?>
                            </button>
                        </div>
                        <div class="aura-tp-field-hint">
                            <?php esc_html_e('Formatos recomendados: PNG, JPG, SVG o WebP.', 'aura-suite'); ?>
                        </div>
                    </div>
                </div>

                <!-- Tipo de Entidad -->
                <div class="aura-tp-form-group">
                    <label for="aura_tp_party_type" class="aura-tp-label">
                        <span><?php esc_html_e('Tipo de Entidad', 'aura-suite'); ?> *</span>
                        <span class="aura-tooltip-wrap">
                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                            <span class="aura-tooltip-content"><?php esc_html_e('Define si el tercero es una empresa, tienda, fundación o persona natural.', 'aura-suite'); ?></span>
                        </span>
                    </label>
                    <div class="aura-input-group">
                        <span class="aura-input-group-text" id="addon-party-type" style="font-size:16px;">
                            <span class="aura-tp-select-emoji" style="font-size:16px;line-height:1;">🏢</span>
                        </span>
                        <select id="aura_tp_party_type" name="party_type" class="aura-tp-select" aria-describedby="addon-party-type">
                            <option value="company">🏢 <?php esc_html_e('Empresa / Negocio Jurídico', 'aura-suite'); ?></option>
                            <option value="store">🛒 <?php esc_html_e('Tienda / Local Comercial / Proveedor', 'aura-suite'); ?></option>
                            <option value="organization_foundation">🏛️ <?php esc_html_e('Fundación / ONG / Institución', 'aura-suite'); ?></option>
                            <option value="person">👤 <?php esc_html_e('Persona Natural / Contratista / Lector', 'aura-suite'); ?></option>
                            <option value="religious">⛪ <?php esc_html_e('Entidad Religiosa / Iglesia / Parroquia', 'aura-suite'); ?></option>
                            <option value="other">🏷️ <?php esc_html_e('Otra Entidad / Servicio Municipal / Varios', 'aura-suite'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Razón Social y Nombre Comercial -->
                <div class="aura-tp-grid-2 aura-tp-form-group">
                    <div>
                        <label for="aura_tp_full_name" class="aura-tp-label">
                            <span><?php esc_html_e('Razón Social / Nombre Completo', 'aura-suite'); ?> *</span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-full-name">
                                <span class="dashicons dashicons-businessman"></span>
                            </span>
                            <input type="text" id="aura_tp_full_name" name="full_name" required class="aura-tp-input" placeholder="<?php esc_attr_e('Ej: Inversiones Globales S.A.S', 'aura-suite'); ?>" aria-describedby="addon-full-name">
                        </div>
                    </div>
                    <div>
                        <label for="aura_tp_commercial_name" class="aura-tp-label">
                            <span><?php esc_html_e('Nombre Comercial (Opcional)', 'aura-suite'); ?></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-comm-name">
                                <span class="dashicons dashicons-store"></span>
                            </span>
                            <input type="text" id="aura_tp_commercial_name" name="commercial_name" class="aura-tp-input" placeholder="<?php esc_attr_e('Ej: Supertienda Central', 'aura-suite'); ?>" aria-describedby="addon-comm-name">
                        </div>
                    </div>
                </div>

                <!-- Tipo Doc y Número -->
                <div class="aura-tp-grid-doc aura-tp-form-group">
                    <div>
                        <label for="aura_tp_tax_id_type" class="aura-tp-label">
                            <span><?php esc_html_e('Tipo Doc.', 'aura-suite'); ?></span>
                            <span class="aura-tooltip-wrap">
                                <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                <span class="aura-tooltip-content" style="max-width:300px;text-align:left;line-height:1.5;">
                                    <strong><?php esc_html_e('Tipos de Documento:', 'aura-suite'); ?></strong><br>
                                    • <strong>NIT:</strong> <?php esc_html_e('Núm. Identificación Tributaria (Empresas / Jurídicas)', 'aura-suite'); ?><br>
                                    • <strong>RUT:</strong> <?php esc_html_e('Registro Único Tributario', 'aura-suite'); ?><br>
                                    • <strong>CIF:</strong> <?php esc_html_e('Código Identificación Fiscal (Internacional)', 'aura-suite'); ?><br>
                                    • <strong>RFC:</strong> <?php esc_html_e('Reg. Federal Contribuyentes (México)', 'aura-suite'); ?><br>
                                    • <strong>CC:</strong> <?php esc_html_e('Cédula de Ciudadanía (Personas)', 'aura-suite'); ?><br>
                                    • <strong>CE:</strong> <?php esc_html_e('Cédula de Extranjería', 'aura-suite'); ?><br>
                                    • <strong>Pasaporte:</strong> <?php esc_html_e('Identificación Internacional', 'aura-suite'); ?><br>
                                    • <strong>Otro:</strong> <?php esc_html_e('Cualquier otro documento fiscal', 'aura-suite'); ?>
                                </span>
                            </span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-tax-type">
                                <span class="dashicons dashicons-text-page"></span>
                            </span>
                            <select id="aura_tp_tax_id_type" name="tax_id_type" class="aura-tp-select" aria-describedby="addon-tax-type">
                                <option value="NIT">NIT — Núm. Identificación Tributaria</option>
                                <option value="RUT">RUT — Registro Único Tributario</option>
                                <option value="CIF">CIF — Código Identificación Fiscal</option>
                                <option value="RFC">RFC — Reg. Federal Contribuyentes</option>
                                <option value="CC">CC — Cédula de Ciudadanía</option>
                                <option value="CE">CE — Cédula de Extranjería</option>
                                <option value="Pasaporte">Pasaporte — Identificación Int.</option>
                                <option value="Otro">Otro — Documento Alternativo</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="aura_tp_document_id" class="aura-tp-label">
                            <span><?php esc_html_e('Número de Documento / NIT', 'aura-suite'); ?></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-doc-id">
                                <span class="dashicons dashicons-id"></span>
                            </span>
                            <input type="text" id="aura_tp_document_id" name="document_id" class="aura-tp-input" placeholder="<?php esc_attr_e('Ej: 900.123.456-7', 'aura-suite'); ?>" aria-describedby="addon-doc-id">
                        </div>
                    </div>
                </div>

                <!-- Teléfono y Email -->
                <div class="aura-tp-grid-2 aura-tp-form-group">
                    <div>
                        <label for="aura_tp_phone" class="aura-tp-label">
                            <span><?php esc_html_e('Teléfono / WhatsApp', 'aura-suite'); ?></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-phone">
                                <span class="dashicons dashicons-phone"></span>
                            </span>
                            <input type="tel" id="aura_tp_phone" name="phone" class="aura-tp-input" placeholder="<?php esc_attr_e('+57 300 1234567', 'aura-suite'); ?>" aria-describedby="addon-phone">
                        </div>
                    </div>
                    <div>
                        <label for="aura_tp_email" class="aura-tp-label">
                            <span><?php esc_html_e('Correo Electrónico', 'aura-suite'); ?></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-email">
                                <span class="dashicons dashicons-email-alt"></span>
                            </span>
                            <input type="email" id="aura_tp_email" name="email" class="aura-tp-input" placeholder="<?php esc_attr_e('contacto@empresa.com', 'aura-suite'); ?>" aria-describedby="addon-email">
                        </div>
                    </div>
                </div>

                <!-- Web y Dirección -->
                <div class="aura-tp-grid-2 aura-tp-form-group">
                    <div>
                        <label for="aura_tp_website" class="aura-tp-label">
                            <span><?php esc_html_e('Sitio Web', 'aura-suite'); ?></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-website">
                                <span class="dashicons dashicons-admin-site-alt3"></span>
                            </span>
                            <input type="url" id="aura_tp_website" name="website" class="aura-tp-input" placeholder="<?php esc_attr_e('https://empresa.com', 'aura-suite'); ?>" aria-describedby="addon-website">
                        </div>
                    </div>
                    <div>
                        <label for="aura_tp_address" class="aura-tp-label">
                            <span><?php esc_html_e('Dirección Física', 'aura-suite'); ?></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text" id="addon-address">
                                <span class="dashicons dashicons-location"></span>
                            </span>
                            <input type="text" id="aura_tp_address" name="address" class="aura-tp-input" placeholder="<?php esc_attr_e('Calle 123 # 45-67', 'aura-suite'); ?>" aria-describedby="addon-address">
                        </div>
                    </div>
                </div>

                <!-- Notas -->
                <div class="aura-tp-form-group" style="margin-bottom:0;">
                    <label for="aura_tp_notes" class="aura-tp-label">
                        <span><?php esc_html_e('Notas Internas', 'aura-suite'); ?></span>
                    </label>
                    <textarea id="aura_tp_notes" name="notes" rows="2" class="aura-tp-textarea" placeholder="<?php esc_attr_e('Observaciones adicionales...', 'aura-suite'); ?>"></textarea>
                </div>
            </div>

            <footer class="aura-tp-modal-footer">
                <button type="button" class="btn btn-secondary btn-lift" id="aura-tp-modal-cancel"><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
                <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-tp-modal-submit">
                    <?php esc_html_e('Guardar Tercero', 'aura-suite'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>

<!-- ── MODAL: FICHA 360° DE LA ENTIDAD (DISEÑO BLINDADO) ──────────────── -->
<div id="aura-tp-modal-360" class="aura-tp-modal-overlay" style="display:none;">
    <div class="aura-tp-modal-content aura-tp-360-modal-content">
        <header class="aura-tp-modal-header">
            <div class="aura-tp-360-header-info">
                <div id="aura-tp-360-avatar" class="aura-tp-uploader-preview aura-tp-360-avatar-box">
                    <span class="dashicons dashicons-building"></span>
                </div>
                <div>
                    <h2 id="aura-tp-360-name" class="aura-tp-360-title">-</h2>
                    <div id="aura-tp-360-meta" class="aura-tp-360-meta">
                        <span id="aura-tp-360-badge" class="aura-pill">-</span>
                        <span id="aura-tp-360-doc" class="aura-doc-badge">-</span>
                    </div>
                </div>
            </div>
            <button type="button" class="aura-tp-modal-close" id="aura-tp-360-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">&times;</button>
        </header>

        <div class="aura-tp-modal-body">
            <!-- Loader de Espera Animado -->
            <div id="aura-tp-360-loader" class="aura-tp-360-loader">
                <div class="aura-tp-spinner"></div>
                <div class="aura-tp-360-loader-text"><?php esc_html_e('Cargando información y movimientos...', 'aura-suite'); ?></div>
            </div>

            <!-- Contenido Principal 360 -->
            <div id="aura-tp-360-content-wrap">
                <!-- KPIs 360 -->
                <div class="aura-360-kpis">
                    <div class="aura-360-kpi-item">
                        <div class="aura-360-kpi-label"><?php esc_html_e('Transacciones', 'aura-suite'); ?></div>
                        <div class="aura-360-kpi-value" id="tp-360-tx-count">0</div>
                    </div>
                    <div class="aura-360-kpi-item">
                        <div class="aura-360-kpi-label"><?php esc_html_e('Total Egresos', 'aura-suite'); ?></div>
                        <div class="aura-360-kpi-value is-expense" id="tp-360-total-out">$0</div>
                    </div>
                    <div class="aura-360-kpi-item">
                        <div class="aura-360-kpi-label"><?php esc_html_e('Equipos Préstamo', 'aura-suite'); ?></div>
                        <div class="aura-360-kpi-value is-inventory" id="tp-360-inv-loans">0</div>
                    </div>
                    <div class="aura-360-kpi-item">
                        <div class="aura-360-kpi-label"><?php esc_html_e('Libros Préstamo', 'aura-suite'); ?></div>
                        <div class="aura-360-kpi-value is-library" id="tp-360-lib-loans">0</div>
                    </div>
                </div>

                <!-- Datos de Contacto y Ubicación -->
                <div class="aura-360-card-section">
                    <h4 class="aura-360-section-title"><?php esc_html_e('Información de Contacto y Ubicación', 'aura-suite'); ?></h4>
                    <div class="aura-360-details-grid">
                        <div><strong><?php esc_html_e('Teléfono / WA:', 'aura-suite'); ?></strong> <span id="tp-360-phone">-</span></div>
                        <div><strong><?php esc_html_e('Email:', 'aura-suite'); ?></strong> <span id="tp-360-email">-</span></div>
                        <div><strong><?php esc_html_e('Sitio Web:', 'aura-suite'); ?></strong> <span id="tp-360-web">-</span></div>
                        <div><strong><?php esc_html_e('Dirección:', 'aura-suite'); ?></strong> <span id="tp-360-address">-</span></div>
                        <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 8px;">
                            <strong><?php esc_html_e('Usuario WordPress:', 'aura-suite'); ?></strong> 
                            <span id="tp-360-wp-user">-</span>
                        </div>
                    </div>
                    <div class="aura-360-notes-box" id="tp-360-notes-wrap">
                        <strong><?php esc_html_e('Notas:', 'aura-suite'); ?></strong> <span id="tp-360-notes">-</span>
                    </div>
                </div>

                <!-- Últimas Transacciones Registradas -->
                <div class="aura-360-card-section">
                    <h4 class="aura-360-section-title"><?php esc_html_e('Últimas Transacciones Registradas', 'aura-suite'); ?></h4>
                    <div id="tp-360-recent-txs" class="aura-360-recent-txs">
                        <p class="aura-text-muted"><?php esc_html_e('No hay transacciones registradas para este tercero.', 'aura-suite'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <footer class="aura-tp-modal-footer">
            <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-tp-360-btn-close"><?php esc_html_e('Cerrar Ficha', 'aura-suite'); ?></button>
        </footer>
    </div>
</div>

<!-- ── MODAL: CREAR USUARIO DE WORDPRESS (SUSCRIPTOR) ── -->
<div id="aura-modal-create-wp-user" class="aura-tp-modal-overlay" style="display:none;">
    <div class="aura-tp-modal-content aura-modal-wp-user-content" style="max-width:520px;">
        <header class="aura-tp-modal-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="background:rgba(37,99,235,0.12);color:#2563eb;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span class="dashicons dashicons-admin-users" style="font-size:22px;width:22px;height:22px;"></span>
                </div>
                <div>
                    <h2 class="aura-tp-modal-header__title" style="margin:0;font-size:1.15rem;font-weight:700;">
                        <?php esc_html_e('Crear Usuario en WordPress', 'aura-suite'); ?>
                    </h2>
                    <p style="margin:2px 0 0;font-size:0.82rem;color:var(--aura-text-muted, #64748b);">
                        <?php esc_html_e('El tercero obtendrá acceso a WordPress con rol Suscriptor.', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-tp-modal-close" id="aura-wp-user-modal-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">&times;</button>
        </header>

        <form id="aura-form-create-wp-user" style="display:contents;">
            <div class="aura-tp-modal-body" style="padding:20px;display:flex;flex-direction:column;gap:16px;">
                <input type="hidden" id="aura_wp_user_tp_id" name="id" value="">

                <!-- Info Box -->
                <div style="background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.2);border-radius:8px;padding:12px 14px;display:flex;align-items:flex-start;gap:10px;">
                    <span class="dashicons dashicons-info" style="color:#2563eb;font-size:20px;flex-shrink:0;margin-top:2px;"></span>
                    <span style="font-size:0.85rem;line-height:1.45;">
                        <?php esc_html_e('Se asignará el rol ', 'aura-suite'); ?><strong><?php esc_html_e('Suscriptor (Subscriber)', 'aura-suite'); ?></strong><?php esc_html_e('. Si el correo ya existe en WordPress, se vinculará de forma segura sin modificar su clave.', 'aura-suite'); ?>
                    </span>
                </div>

                <!-- Nombre Tercero (Lectura) -->
                <div class="aura-tp-form-group">
                    <label class="aura-tp-label">
                        <span><?php esc_html_e('Nombre / Razón Social del Tercero', 'aura-suite'); ?></span>
                    </label>
                    <input type="text" id="aura_wp_user_name" class="aura-tp-input" readonly style="opacity:0.85;cursor:default;">
                </div>

                <!-- Correo Electrónico -->
                <div class="aura-tp-form-group">
                    <label for="aura_wp_user_email" class="aura-tp-label">
                        <span><?php esc_html_e('Correo Electrónico (Requerido)', 'aura-suite'); ?></span>
                        <span class="aura-tp-required" style="color:#ef4444;">*</span>
                    </label>
                    <div class="aura-input-group">
                        <span class="aura-input-group-text"><span class="dashicons dashicons-email-alt"></span></span>
                        <input type="email" id="aura_wp_user_email" name="email" class="aura-tp-input" required placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <!-- Nombre de Usuario (Opcional / Sugerido) -->
                <div class="aura-tp-form-group">
                    <label for="aura_wp_user_login" class="aura-tp-label">
                        <span><?php esc_html_e('Nombre de Usuario (Login)', 'aura-suite'); ?></span>
                        <span style="font-size:0.75rem;color:var(--aura-text-muted, #64748b);">(<?php esc_html_e('Opcional, se autogenera si está vacío', 'aura-suite'); ?>)</span>
                    </label>
                    <div class="aura-input-group">
                        <span class="aura-input-group-text"><span class="dashicons dashicons-admin-users"></span></span>
                        <input type="text" id="aura_wp_user_login" name="user_login" class="aura-tp-input" placeholder="ej: nombre.apellido">
                    </div>
                </div>

                <!-- Checkbox Enviar Notificación -->
                <div style="display:flex;align-items:flex-start;gap:8px;margin-top:2px;">
                    <input type="checkbox" id="aura_wp_user_notify" name="send_notification" value="1" checked style="margin-top:3px;border-radius:4px;">
                    <label for="aura_wp_user_notify" style="font-size:0.85rem;cursor:pointer;line-height:1.4;">
                        <?php esc_html_e('Enviar correo de bienvenida al usuario con instrucciones para configurar su contraseña.', 'aura-suite'); ?>
                    </label>
                </div>

                <!-- Contenedor de Alerta / Estado -->
                <div id="aura-wp-user-alert" style="display:none;padding:10px 14px;border-radius:8px;font-size:0.88rem;"></div>
            </div>

            <footer class="aura-tp-modal-footer">
                <button type="button" class="btn btn-secondary btn-lift" id="aura-wp-user-modal-cancel"><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
                <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-wp-user-modal-submit" style="display:inline-flex;align-items:center;gap:6px;">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e('Crear Usuario Suscriptor', 'aura-suite'); ?></span>
                </button>
            </footer>
        </form>
    </div>
</div>

<script>
function exportAuraThirdPartiesCSV(btn) {
    if (!btn) btn = document.getElementById('aura-export-third-parties-btn');
    var originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js( __( 'Exportando...', 'aura-suite' ) ); ?>';
    }

    var formData = new FormData();
    formData.append('action', 'aura_export_third_parties');
    formData.append('nonce', '<?php echo wp_create_nonce( 'aura_export_nonce' ); ?>');

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(res) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
        if (!res.success) {
            alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Error al exportar terceros', 'aura-suite' ) ); ?>');
            return;
        }
        var raw = atob(res.data.content);
        var bytes = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            bytes[i] = raw.charCodeAt(i);
        }
        var blob = new Blob([bytes], { type: res.data.mime || 'text/csv;charset=utf-8' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = res.data.filename || 'directorio-terceros.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    })
    .catch(function(err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
        alert('Error en la exportación: ' + err.message);
    });
}
</script>
