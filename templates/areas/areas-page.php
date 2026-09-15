<?php
/**
 * Template: Gestión unificada de Áreas y Tipos de Área.
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$can_manage_areas = current_user_can( 'manage_options' ) || current_user_can( 'aura_areas_manage' );
$can_manage_types = current_user_can( 'manage_options' ) || current_user_can( 'aura_areas_types_manage' );

if ( ! $can_manage_areas && ! $can_manage_types ) {
    wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
}

$area_types = Aura_Areas_Setup::get_all_types();

global $wpdb;
$areas_table    = $wpdb->prefix . Aura_Areas_Setup::TABLE;
$types_table    = $wpdb->prefix . Aura_Areas_Setup::AREA_TYPES_TABLE;
$areas_total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$areas_table}`" );
$areas_active   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$areas_table}` WHERE `status` = 'active'" );
$areas_archived = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$areas_table}` WHERE `status` = 'archived'" );
$areas_roots    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$areas_table}` WHERE COALESCE(`parent_area_id`, 0) = 0" );
$types_total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$types_table}`" );
$types_in_use   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT `type`) FROM `{$areas_table}` WHERE `type` <> ''" );
$types_default  = 0;

foreach ( $area_types as $info ) {
    if ( ! empty( $info['is_default'] ) ) {
        $types_default++;
    }
}

$initial_tab = sanitize_key( $_GET['tab'] ?? '' );
if ( ! in_array( $initial_tab, [ 'areas', 'types' ], true ) ) {
    $initial_tab = $can_manage_areas ? 'areas' : 'types';
}
?>

<div class="aura-app-wrapper">
    <div class="wrap aura-app-context aura-areas-admin-page">
        <div class="aura-layout">
            <main class="aura-content">
                <!-- CABECERA PRINCIPAL -->
                <!-- ── CABECERA PRINCIPAL CANÓNICA (HERO GLASS CARD) ──────────── -->
                <header class="aura-page-header hero-card aura-glass-card fade-up">
                    <div class="aura-header-left" style="display: flex; align-items: center; gap: 18px; flex: 1 1 auto; min-width: 0;">
                        <div class="aura-page-header__icon">
                            <span class="dashicons dashicons-networking"></span>
                        </div>
                        <div class="aura-header-text" style="flex: 1 1 auto; min-width: 0;">
                            <div class="aura-title-with-badge" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <h1 class="aura-page-title" style="margin: 0; color: #ffffff !important; font-weight: 800; font-size: 1.45rem; line-height: 1.25;"><?php esc_html_e( 'Áreas y Tipos de Área', 'aura-suite' ); ?></h1>
                                <span class="badge badge-blue">
                                    <span class="pulse-dot"></span>
                                    <strong id="header-areas-total"><?php echo esc_html( $areas_total ); ?></strong> <?php esc_html_e( 'áreas', 'aura-suite' ); ?>
                                </span>
                                <span class="badge badge-gray">
                                    🏷️ <strong id="header-types-total"><?php echo esc_html( $types_total ); ?></strong> <?php esc_html_e( 'tipos', 'aura-suite' ); ?>
                                </span>
                            </div>
                            <p class="aura-page-subtitle hero-desc" style="margin: 6px 0 0; color: rgba(255, 255, 255, 0.88) !important; font-size: 0.88rem; line-height: 1.45;"><?php esc_html_e( 'Gestiona estructura departamental, organigrama jerárquico y clasificaciones maestras de toda la suite.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-header-right" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto; z-index: 10;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-suite' ) ); ?>" class="btn btn-glass btn-lift">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            <span><?php esc_html_e( 'Dashboard', 'aura-suite' ); ?></span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-import&type=areas' ) ); ?>" class="btn btn-glass btn-lift">
                            <span class="dashicons dashicons-upload"></span>
                            <span><?php esc_html_e( 'Importar', 'aura-suite' ); ?></span>
                        </a>
                        <button type="button" class="btn btn-glass btn-lift" id="aura-header-export-btn" onclick="exportAuraAreasCSV(this)">
                            <span class="dashicons dashicons-download"></span>
                            <span><?php esc_html_e( 'Exportar CSV', 'aura-suite' ); ?></span>
                        </button>
                        <?php if ( $can_manage_areas ) : ?>
                            <button type="button" class="btn btn-primary btn-shimmer btn-lift" onclick="document.getElementById('aura-add-area-btn') && document.getElementById('aura-add-area-btn').click();">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <span><?php esc_html_e( 'Nueva Área', 'aura-suite' ); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </header>

                <!-- ── BARRA DE NAVEGACIÓN DE PESTAÑAS (TABS NAVBAR) ───────────── -->
                <nav class="aura-navbar aura-nav aura-glass-card aura-areas-navbar">
                    <?php if ( $can_manage_areas ) : ?>
                        <button type="button" class="aura-navbar-item aura-tab-btn<?php echo 'areas' === $initial_tab ? ' is-active active' : ''; ?>" data-tab="tab-areas" data-tab-key="areas">
                            <span class="dashicons dashicons-groups"></span>
                            <?php esc_html_e( 'Áreas y Departamentos', 'aura-suite' ); ?>
                            <span class="aura-tab-count" id="tab-count-areas"><?php echo esc_html( $areas_total ); ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ( $can_manage_types ) : ?>
                        <button type="button" class="aura-navbar-item aura-tab-btn<?php echo 'types' === $initial_tab ? ' is-active active' : ''; ?>" data-tab="tab-types" data-tab-key="types">
                            <span class="dashicons dashicons-tag"></span>
                            <?php esc_html_e( 'Tipos de Área', 'aura-suite' ); ?>
                            <span class="aura-tab-count" id="tab-count-types"><?php echo esc_html( $types_total ); ?></span>
                        </button>
                    <?php endif; ?>
                </nav>

                <!-- PESTAÑA: ÁREAS -->
                <?php if ( $can_manage_areas ) : ?>
                    <section id="tab-areas" class="aura-tab-panel<?php echo 'areas' === $initial_tab ? ' active' : ''; ?>">
                        <!-- ── KPI METRICS CARDS CANÓNICAS ULTRA-COMPACTAS (ÁREAS) ── -->
                        <div class="aura-tp-kpi-grid aura-areas-kpi-grid">
                            <!-- Áreas activas -->
                            <article class="aura-stat-card aura-tp-kpi-card card-lift">
                                <div class="aura-tp-kpi-card__info">
                                    <span class="aura-tp-kpi-card__label-wrap">
                                        <span><?php esc_html_e( 'Áreas activas', 'aura-suite' ); ?></span>
                                        <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Estructuras operativas disponibles para asignación de presupuestos y registro de gastos.', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                            <span class="aura-tooltip-content"><?php esc_html_e( 'Estructuras operativas disponibles para asignación de presupuestos y registro de gastos.', 'aura-suite' ); ?></span>
                                        </span>
                                    </span>
                                    <div class="aura-tp-kpi-card__value" id="kpi-areas-active"><?php echo esc_html( $areas_active ); ?></div>
                                </div>
                                <div class="aura-tp-kpi-card__icon-wrap" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                                    <span class="dashicons dashicons-groups"></span>
                                </div>
                            </article>

                            <!-- Archivadas -->
                            <article class="aura-stat-card aura-tp-kpi-card card-lift">
                                <div class="aura-tp-kpi-card__info">
                                    <span class="aura-tp-kpi-card__label-wrap">
                                        <span><?php esc_html_e( 'Archivadas', 'aura-suite' ); ?></span>
                                        <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Áreas inactivas conservadas en el historial para preservar trazabilidad contable.', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                            <span class="aura-tooltip-content"><?php esc_html_e( 'Áreas inactivas conservadas en el historial para preservar trazabilidad contable.', 'aura-suite' ); ?></span>
                                        </span>
                                    </span>
                                    <div class="aura-tp-kpi-card__value" id="kpi-areas-archived"><?php echo esc_html( $areas_archived ); ?></div>
                                </div>
                                <div class="aura-tp-kpi-card__icon-wrap" style="background: rgba(100, 116, 139, 0.12); color: #64748b;">
                                    <span class="dashicons dashicons-archive"></span>
                                </div>
                            </article>

                            <!-- Áreas raíz -->
                            <article class="aura-stat-card aura-tp-kpi-card card-lift">
                                <div class="aura-tp-kpi-card__info">
                                    <span class="aura-tp-kpi-card__label-wrap">
                                        <span><?php esc_html_e( 'Áreas raíz', 'aura-suite' ); ?></span>
                                        <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Nodos principales de nivel superior que estructuran el organigrama.', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                            <span class="aura-tooltip-content"><?php esc_html_e( 'Nodos principales de nivel superior que estructuran el organigrama.', 'aura-suite' ); ?></span>
                                        </span>
                                    </span>
                                    <div class="aura-tp-kpi-card__value" id="kpi-areas-roots"><?php echo esc_html( $areas_roots ); ?></div>
                                </div>
                                <div class="aura-tp-kpi-card__icon-wrap" style="background: rgba(37, 99, 235, 0.12); color: #2563eb;">
                                    <span class="dashicons dashicons-networking"></span>
                                </div>
                            </article>

                            <!-- Tipos disponibles -->
                            <article class="aura-stat-card aura-tp-kpi-card card-lift">
                                <div class="aura-tp-kpi-card__info">
                                    <span class="aura-tp-kpi-card__label-wrap">
                                        <span><?php esc_html_e( 'Tipos disponibles', 'aura-suite' ); ?></span>
                                        <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Clasificaciones y categorías listas para clasificar departamentos.', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                            <span class="aura-tooltip-content"><?php esc_html_e( 'Clasificaciones y categorías listas para clasificar departamentos.', 'aura-suite' ); ?></span>
                                        </span>
                                    </span>
                                    <div class="aura-tp-kpi-card__value" id="kpi-types-total"><?php echo esc_html( $types_total ); ?></div>
                                </div>
                                <div class="aura-tp-kpi-card__icon-wrap" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">
                                    <span class="dashicons dashicons-tag"></span>
                                </div>
                            </article>
                        </div>

                        <!-- SECCIÓN CRUD TABLA -->
                        <section class="aura-glass-card aura-areas-section-card">
                            <div class="aura-areas-section-head">
                                <div>
                                    <h2 style="font-weight: 700; color: var(--aura-text, #0f172a);"><?php esc_html_e( 'Catálogo de Áreas y Departamentos', 'aura-suite' ); ?></h2>
                                    <p style="color: var(--aura-text-muted, #64748b);"><?php esc_html_e( 'Consulta, filtra y edita áreas desde una tabla responsive con formularios optimizados para móvil.', 'aura-suite' ); ?></p>
                                </div>
                                <div class="aura-areas-head-actions" style="display: flex; gap: 8px; align-items: center;">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-import&type=areas' ) ); ?>" class="btn btn-secondary btn-lift">
                                        <span class="dashicons dashicons-upload"></span>
                                        <span><?php esc_html_e( 'Importar', 'aura-suite' ); ?></span>
                                    </a>
                                    <button type="button" class="btn btn-secondary btn-lift" id="aura-export-areas-btn" onclick="exportAuraAreasCSV(this)">
                                        <span class="dashicons dashicons-download"></span>
                                        <span><?php esc_html_e( 'Exportar CSV', 'aura-suite' ); ?></span>
                                    </button>
                                    <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-add-area-btn">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <span><?php esc_html_e( 'Nueva Área', 'aura-suite' ); ?></span>
                                    </button>
                                </div>
                            </div>

                            <div id="aura-areas-notice" class="aura-info-box danger aura-areas-inline-notice aura-is-hidden">
                                <p id="aura-areas-notice-text"></p>
                            </div>

                            <!-- FILTROS -->
                            <div class="aura-areas-filters-grid">
                                <div class="aura-field">
                                    <label class="aura-label" for="aura-filter-search">
                                        <?php esc_html_e( 'Buscar área', 'aura-suite' ); ?>
                                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Búsqueda en tiempo real por nombre, descripción o slug.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                    </label>
                                    <div class="aura-input-group">
                                        <span class="aura-input-group-text"><span class="dashicons dashicons-search"></span></span>
                                        <input type="search" id="aura-filter-search" class="aura-input" placeholder="<?php esc_attr_e( 'Nombre o descripción...', 'aura-suite' ); ?>">
                                    </div>
                                </div>
                                <div class="aura-field">
                                    <label class="aura-label" for="aura-filter-type">
                                        <?php esc_html_e( 'Tipo', 'aura-suite' ); ?>
                                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtra las áreas según su clasificación estructural.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                    </label>
                                    <div class="aura-input-group">
                                        <span class="aura-input-group-text"><span class="dashicons dashicons-tag"></span></span>
                                        <select id="aura-filter-type" class="aura-input">
                                            <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
                                            <?php foreach ( $area_types as $slug => $info ) : ?>
                                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $info['name'] ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="aura-field">
                                    <label class="aura-label" for="aura-filter-status">
                                        <?php esc_html_e( 'Estado', 'aura-suite' ); ?>
                                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Muestra solo áreas activas o archivadas.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                    </label>
                                    <div class="aura-input-group">
                                        <span class="aura-input-group-text"><span class="dashicons dashicons-filter"></span></span>
                                        <select id="aura-filter-status" class="aura-input">
                                            <option value="active"><?php esc_html_e( 'Activas', 'aura-suite' ); ?></option>
                                            <option value="archived"><?php esc_html_e( 'Archivadas', 'aura-suite' ); ?></option>
                                            <option value=""><?php esc_html_e( 'Todas', 'aura-suite' ); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="aura-areas-filter-actions">
                                    <button type="button" id="aura-apply-filters-btn" class="btn btn-secondary btn-lift">
                                        <span class="dashicons dashicons-filter"></span>
                                        <span><?php esc_html_e( 'Filtrar', 'aura-suite' ); ?></span>
                                    </button>
                                    <button type="button" id="aura-clear-filters-btn" class="btn btn-secondary btn-lift">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <span><?php esc_html_e( 'Limpiar', 'aura-suite' ); ?></span>
                                    </button>
                                </div>
                            </div>

                            <!-- TABLA -->
                            <div class="aura-dt-wrapper aura-areas-table-card">
                                <table id="aura-areas-table" class="display responsive nowrap aura-areas-table aura-areas-table-fullwidth">
                                    <thead>
                                        <tr>
                                            <th style="width: 72px;">
                                                <?php esc_html_e( 'Logo', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Flecha de despliegue de detalles e imagen del área.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Área', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Nombre institucional, área superior y descripción.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Tipo', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Clasificación estructural asignada.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Responsables', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Miembros del equipo y roles (Admin, Editor, Colaborador) asignados.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Presupuesto', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Monto anual asignado y balance presupuestario.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Estado', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Disponibilidad operativa (Activa o Archivada).', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th style="min-width: 148px; width: 148px; text-align: right; white-space: nowrap;">
                                                <?php esc_html_e( 'Acciones', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Operaciones de edición, métricas, presupuestos y archivado.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </section>
                    </section>
                <?php endif; ?>

                <!-- PESTAÑA: TIPOS DE ÁREA -->
                <?php if ( $can_manage_types ) : ?>
                    <section id="tab-types" class="aura-tab-panel<?php echo 'types' === $initial_tab ? ' active' : ''; ?>">
                        <!-- ── KPI METRICS CARDS CANÓNICAS ULTRA-COMPACTAS (TIPOS) ── -->
                        <div class="aura-tp-kpi-grid aura-areas-kpi-grid aura-areas-kpi-grid--types">
                            <!-- Tipos registrados -->
                            <article class="aura-stat-card aura-tp-kpi-card card-lift">
                                <div class="aura-tp-kpi-card__info">
                                    <span class="aura-tp-kpi-card__label-wrap">
                                        <span><?php esc_html_e( 'Tipos registrados', 'aura-suite' ); ?></span>
                                        <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Cantidad total de tipos de área registrados en la base de datos.', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                            <span class="aura-tooltip-content"><?php esc_html_e( 'Cantidad total de tipos de área registrados en la base de datos.', 'aura-suite' ); ?></span>
                                        </span>
                                    </span>
                                    <div class="aura-tp-kpi-card__value" id="kpi-types-count"><?php echo esc_html( $types_total ); ?></div>
                                </div>
                                <div class="aura-tp-kpi-card__icon-wrap" style="background: rgba(37, 99, 235, 0.12); color: #2563eb;">
                                    <span class="dashicons dashicons-tag"></span>
                                </div>
                            </article>

                            <!-- Predeterminados -->
                            <article class="aura-stat-card aura-tp-kpi-card card-lift">
                                <div class="aura-tp-kpi-card__info">
                                    <span class="aura-tp-kpi-card__label-wrap">
                                        <span><?php esc_html_e( 'Predeterminados', 'aura-suite' ); ?></span>
                                        <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Tipo que se selecciona automáticamente al crear una nueva área.', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                            <span class="aura-tooltip-content"><?php esc_html_e( 'Tipo que se selecciona automáticamente al crear una nueva área.', 'aura-suite' ); ?></span>
                                        </span>
                                    </span>
                                    <div class="aura-tp-kpi-card__value" id="kpi-types-default"><?php echo esc_html( $types_default ); ?></div>
                                </div>
                                <div class="aura-tp-kpi-card__icon-wrap" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                                    <span class="dashicons dashicons-star-filled"></span>
                                </div>
                            </article>

                            <!-- Tipos en uso -->
                            <article class="aura-stat-card aura-tp-kpi-card card-lift">
                                <div class="aura-tp-kpi-card__info">
                                    <span class="aura-tp-kpi-card__label-wrap">
                                        <span><?php esc_html_e( 'Tipos en uso', 'aura-suite' ); ?></span>
                                        <span class="aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Tipos asignados al menos a una área activa. No pueden eliminarse si están en uso.', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                            <span class="aura-tooltip-content"><?php esc_html_e( 'Tipos asignados al menos a una área activa. No pueden eliminarse si están en uso.', 'aura-suite' ); ?></span>
                                        </span>
                                    </span>
                                    <div class="aura-tp-kpi-card__value" id="kpi-types-in-use"><?php echo esc_html( $types_in_use ); ?></div>
                                </div>
                                <div class="aura-tp-kpi-card__icon-wrap" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                </div>
                            </article>
                        </div>

                        <section class="aura-glass-card aura-areas-section-card">
                            <div class="aura-areas-section-head">
                                <div>
                                    <h2 style="font-weight: 700; color: var(--aura-text, #0f172a);"><?php esc_html_e( 'Catálogo Maestro de Tipos de Área', 'aura-suite' ); ?></h2>
                                    <p style="color: var(--aura-text-muted, #64748b);"><?php esc_html_e( 'Administra el catálogo maestro del campo type sin salir de la misma vista.', 'aura-suite' ); ?></p>
                                </div>
                                <div class="aura-areas-head-actions">
                                    <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-types-new-btn">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <span><?php esc_html_e( 'Nuevo Tipo', 'aura-suite' ); ?></span>
                                    </button>
                                </div>
                            </div>

                            <div id="aura-types-notice" class="aura-info-box danger aura-areas-inline-notice aura-is-hidden">
                                <p id="aura-types-notice-text"></p>
                            </div>

                            <div class="aura-areas-filters-grid aura-areas-filters-grid--types">
                                <div class="aura-field aura-field--span2">
                                    <label class="aura-label" for="at-search">
                                        <?php esc_html_e( 'Buscar tipo', 'aura-suite' ); ?>
                                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Búsqueda por nombre, slug o descripción de tipo.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                    </label>
                                    <div class="aura-input-group">
                                        <span class="aura-input-group-text"><span class="dashicons dashicons-search"></span></span>
                                        <input type="search" id="at-search" class="aura-input" placeholder="<?php esc_attr_e( 'Nombre, slug o descripción...', 'aura-suite' ); ?>">
                                    </div>
                                </div>
                                <div class="aura-areas-filter-actions">
                                    <button type="button" id="at-clear-btn" class="btn btn-secondary btn-lift">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <span><?php esc_html_e( 'Limpiar', 'aura-suite' ); ?></span>
                                    </button>
                                </div>
                            </div>

                            <div class="aura-dt-wrapper aura-areas-table-card">
                                <table id="aura-types-table" class="display responsive nowrap aura-areas-table aura-areas-table-fullwidth">
                                    <thead>
                                        <tr>
                                            <th style="width: 52px; text-align: center;">
                                                <?php esc_html_e( 'Color', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Color distintivo de este tipo de área.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Nombre', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Nombre público del tipo de área.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Slug', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Identificador único en el sistema.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th>
                                                <?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Detalles y propósito del tipo.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th style="width: 110px; text-align: center;">
                                                <?php esc_html_e( 'Predeterminado', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Indica si se selecciona por defecto al crear áreas.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th style="width: 70px; text-align: center;">
                                                <?php esc_html_e( 'Áreas', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Cantidad de áreas que actualmente usan este tipo.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                            <th style="width: 100px; min-width: 100px; text-align: right; white-space: nowrap;">
                                                <?php esc_html_e( 'Acciones', 'aura-suite' ); ?>
                                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Opciones de edición o eliminación.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </section>
                    </section>
                <?php endif; ?>
                <!-- ── FOOTER CANÓNICO GLOBAL ──────────────────────────────── -->
                <div class="adp-footer">
                    <div>
                        Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL: CREAR / EDITAR ÁREA
     ========================================================================= -->
<?php if ( $can_manage_areas ) : ?>
    <div id="aura-area-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-areas-modal-content" role="dialog" aria-modal="true" aria-labelledby="aura-area-modal-title">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-area-modal-title"><?php esc_html_e( 'Nueva Área', 'aura-suite' ); ?></h2>
                    <p class="aura-modal-subtitle"><?php esc_html_e( 'Completa identidad, tipo, jerarquía y responsable en una sola ficha.', 'aura-suite' ); ?></p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-area-modal" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
            </div>

            <form id="aura-area-form" class="aura-modal-body aura-areas-modal-body" novalidate>
                <input type="hidden" id="aura-area-id" name="area_id" value="0">
                <input type="hidden" id="aura-field-icon" name="icon" value="dashicons-groups">
                <input type="hidden" id="aura-field-logo-id" name="logo_id" value="0">

                <div class="aura-field-group cols-2 aura-areas-form-grid">
                    <div class="aura-field aura-areas-logo-field">
                        <label class="aura-label">
                            <?php esc_html_e( 'Logo o imagen', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Sube una imagen o logo institucional. Se recomienda formato cuadrado.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <div class="aura-areas-logo-widget">
                            <div class="aura-areas-logo-preview" id="aura-logo-preview">
                                <span class="dashicons dashicons-format-image"></span>
                            </div>
                            <div class="aura-areas-logo-actions">
                                <button type="button" class="btn btn-secondary btn-lift" id="aura-logo-select-btn">
                                    <span class="dashicons dashicons-upload"></span>
                                    <span><?php esc_html_e( 'Seleccionar', 'aura-suite' ); ?></span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-lift aura-is-hidden" id="aura-logo-remove-btn">
                                    <span class="dashicons dashicons-trash"></span>
                                    <span><?php esc_html_e( 'Quitar', 'aura-suite' ); ?></span>
                                </button>
                            </div>
                            <span class="aura-desc"><?php esc_html_e( 'Se recomienda una imagen cuadrada para mejorar el recorte y la lectura móvil.', 'aura-suite' ); ?></span>
                        </div>
                    </div>

                    <div class="aura-areas-form-stack">
                        <div class="aura-field-group cols-2">
                            <div class="aura-field">
                                <label class="aura-label" for="aura-field-name">
                                    <?php esc_html_e( 'Nombre del Área', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Nombre oficial del departamento, programa o área.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                </label>
                                <div class="aura-input-group">
                                    <span class="aura-input-group-text"><span class="dashicons dashicons-building"></span></span>
                                    <input type="text" id="aura-field-name" name="name" class="aura-input" required placeholder="<?php esc_attr_e( 'Ej: Dirección Ejecutiva', 'aura-suite' ); ?>">
                                </div>
                                <span class="aura-field-error aura-is-hidden" id="error-name"></span>
                            </div>
                            <div class="aura-field">
                                <label class="aura-label" for="aura-field-type">
                                    <?php esc_html_e( 'Tipo de Área', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Clasificación estructural dentro de la organización.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                                </label>
                                <div class="aura-input-group">
                                    <span class="aura-input-group-text"><span class="dashicons dashicons-tag"></span></span>
                                    <select id="aura-field-type" name="type" class="aura-input">
                                        <?php foreach ( $area_types as $slug => $info ) : ?>
                                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $info['name'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="aura-field-description">
                                <?php esc_html_e( 'Descripción y Alcance', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Objetivos, funciones y alcance del departamento o programa.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <textarea id="aura-field-description" name="description" class="aura-input" rows="4" placeholder="<?php esc_attr_e( 'Describe las funciones principales...', 'aura-suite' ); ?>"></textarea>
                        </div>
                    </div>
                </div>

                <div class="aura-field-group cols-2">
                    <div class="aura-field">
                        <label class="aura-label" for="aura-field-responsible">
                            <?php esc_html_e( 'Responsable principal', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Director o líder principal del área. Se le asigna rol de Admin automáticamente.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-admin-users"></span></span>
                            <select id="aura-field-responsible" name="responsible_user_id" class="aura-input">
                                <option value="0"><?php esc_html_e( '-- Sin asignar --', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="aura-field">
                        <label class="aura-label" for="aura-field-parent">
                            <?php esc_html_e( 'Área padre (Jerarquía)', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Área de nivel superior de la cual depende este departamento en el organigrama.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-networking"></span></span>
                            <select id="aura-field-parent" name="parent_area_id" class="aura-input">
                                <option value="0"><?php esc_html_e( '-- Ninguna (Área Raíz) --', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="aura-field-group cols-2">
                    <div class="aura-field">
                        <label class="aura-label" for="aura-field-color">
                            <?php esc_html_e( 'Color de acento', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Color institucional usado en gráficos, badges y presupuestos.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <input type="text" id="aura-field-color" name="color" value="#2271b1" class="aura-input aura-color-picker">
                    </div>
                    <div class="aura-field">
                        <label class="aura-label" for="aura-field-sort">
                            <?php esc_html_e( 'Orden de visualización', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Número entero para ordenar las áreas en listas y selectores.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-sort"></span></span>
                            <input type="number" id="aura-field-sort" name="sort_order" value="0" min="0" class="aura-input">
                        </div>
                    </div>
                </div>
            </form>

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-area-modal">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-area-save-btn">
                    <span class="spinner" id="aura-save-spinner"></span>
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e( 'Guardar Área', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: RECORTAR LOGO -->
    <div id="aura-crop-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-areas-crop-modal" role="dialog" aria-modal="true" aria-labelledby="aura-crop-title">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-crop-title"><?php esc_html_e( 'Recortar imagen', 'aura-suite' ); ?></h2>
                    <p class="aura-modal-subtitle"><?php esc_html_e( 'Define un encuadre cuadrado para el logo del área.', 'aura-suite' ); ?></p>
                </div>
                <button type="button" class="aura-modal-close" id="aura-crop-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
            </div>
            <div class="aura-modal-body aura-areas-crop-body">
                <div class="aura-areas-crop-preview-container">
                    <img id="aura-crop-img" src="" alt="<?php esc_attr_e( 'Vista previa de recorte', 'aura-suite' ); ?>">
                </div>
            </div>
            <div class="aura-modal-footer">
                <button type="button" class="btn btn-secondary btn-lift" id="aura-crop-cancel">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-crop-apply">
                    <span class="spinner" id="aura-crop-spinner"></span>
                    <span class="dashicons dashicons-image-crop"></span>
                    <span><?php esc_html_e( 'Aplicar recorte', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- =========================================================================
     MODAL: CREAR / EDITAR TIPO DE ÁREA
     ========================================================================= -->
<?php if ( $can_manage_types ) : ?>
    <div id="aura-types-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-areas-modal-content aura-areas-modal-content--compact" role="dialog" aria-modal="true" aria-labelledby="aura-types-modal-title">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-types-modal-title"><?php esc_html_e( 'Nuevo Tipo de Área', 'aura-suite' ); ?></h2>
                    <p class="aura-modal-subtitle"><?php esc_html_e( 'Cada tipo alimenta el catálogo estructural de las áreas.', 'aura-suite' ); ?></p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-types-modal" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
            </div>

            <form id="aura-types-form" class="aura-modal-body aura-areas-modal-body" autocomplete="off">
                <input type="hidden" id="aura-type-id" name="id" value="0">

                <div class="aura-field">
                    <label class="aura-label" for="aura-type-name">
                        <?php esc_html_e( 'Nombre del Tipo', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Nombre de la categoría (ej: Departamento, Programa, Proyecto).', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <div class="aura-input-group">
                        <span class="aura-input-group-text"><span class="dashicons dashicons-tag"></span></span>
                        <input type="text" id="aura-type-name" name="name" maxlength="100" required class="aura-input" placeholder="<?php esc_attr_e( 'Ej: Departamento', 'aura-suite' ); ?>">
                    </div>
                </div>

                <div class="aura-field">
                    <label class="aura-label" for="aura-type-slug">
                        <?php esc_html_e( 'Slug (Identificador)', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Identificador único generado automáticamente a partir del nombre.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <div class="aura-input-group">
                        <span class="aura-input-group-text"><span class="dashicons dashicons-admin-links"></span></span>
                        <input type="text" id="aura-type-slug" name="slug" readonly class="aura-input">
                    </div>
                    <span class="aura-desc"><?php esc_html_e( 'Se genera automáticamente a partir del nombre.', 'aura-suite' ); ?></span>
                </div>

                <div class="aura-field">
                    <label class="aura-label" for="aura-type-description">
                        <?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Propósito y características de esta clasificación.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <textarea id="aura-type-description" name="description" rows="3" class="aura-input" placeholder="<?php esc_attr_e( 'Describe el tipo de estructura...', 'aura-suite' ); ?>"></textarea>
                </div>

                <div class="aura-field-group cols-2">
                    <div class="aura-field">
                        <label class="aura-label" for="aura-type-color">
                            <?php esc_html_e( 'Color distintivo', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Color identificador para los badges de este tipo.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <input type="text" id="aura-type-color" name="color" value="#e0e7ff" class="aura-input aura-color-picker">
                    </div>
                    <div class="aura-field">
                        <label class="aura-label" for="aura-type-sort">
                            <?php esc_html_e( 'Orden', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Posición en listados y filtros.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-sort"></span></span>
                            <input type="number" id="aura-type-sort" name="sort_order" value="0" min="0" max="9999" class="aura-input">
                        </div>
                    </div>
                </div>

                <label class="aura-areas-checkbox-row">
                    <input type="checkbox" id="aura-type-default" name="is_default" value="1">
                    <span><?php esc_html_e( 'Usar como tipo predeterminado para nuevas áreas', 'aura-suite' ); ?></span>
                </label>
            </form>

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-types-modal">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <button type="submit" form="aura-types-form" class="btn btn-primary btn-shimmer btn-lift" id="aura-type-save-btn">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e( 'Guardar tipo', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function exportAuraAreasCSV(btn) {
    if (!btn) btn = document.getElementById('aura-export-areas-btn');
    var originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js( __( 'Exportando...', 'aura-suite' ) ); ?>';
    }

    var formData = new FormData();
    formData.append('action', 'aura_export_areas');
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
            alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Error al exportar áreas', 'aura-suite' ) ); ?>');
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
        link.download = res.data.filename || 'areas-programas.csv';
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