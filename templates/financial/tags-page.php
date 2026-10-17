<?php
/**
 * Página de Gestión de Etiquetas (Fase 5, Item 5.2)
 *
 * Accesible en: /wp-admin/admin.php?page=aura-financial-tags
 * Módulo CRUD robusto, interactivo y mejorado según directrices de diseño de Aura Business Suite.
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if (!defined('ABSPATH')) {
    exit;
}

$can_view = current_user_can('aura_finance_view_all') || current_user_can('aura_finance_tags') || current_user_can('manage_options') || current_user_can('read');
if (!$can_view) {
    wp_die(esc_html__('No tienes permisos suficientes para acceder a la Gestión de Etiquetas.', 'aura-suite'));
}

$can_create = current_user_can('aura_finance_edit_all') || current_user_can('aura_finance_tags') || current_user_can('manage_options');
$can_edit = $can_create;
$can_delete = current_user_can('aura_finance_delete_all') || current_user_can('aura_finance_tags') || current_user_can('manage_options');

$nonce = wp_create_nonce('aura_tags_nonce');
$all_tags_count = Aura_Financial_Tags::get_all_tags();
$total_unique_tags = count($all_tags_count);
$total_tag_usages = array_sum($all_tags_count);
$currency = get_option('aura_finance_currency', 'USD');
?>

<div class="wrap aura-tags-wrap aura-module-wrap aura-app-context">

    <!-- ── Hero Banner Superior Canónico ───────────────────────────── -->
    <div class="aura-hero-header">
        <div class="aura-hero-content">
            <div class="aura-hero-icon-box">
                <span class="dashicons dashicons-tag"></span>
            </div>
            <div class="aura-hero-titles">
                <div class="aura-hero-subtitle-row">
                    <span class="aura-tag-badge">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e('Aura Finanzas', 'aura-suite'); ?>
                    </span>
                    <span class="aura-tag-badge aura-tag-secondary">
                        <span class="dashicons dashicons-tag"></span>
                        <?php esc_html_e('Taxonomía Transversal', 'aura-suite'); ?>
                    </span>
                    <span class="aura-tag-badge"
                        style="background: rgba(16,185,129,0.2); color:#6ee7b7; border-color: rgba(16,185,129,0.3);">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('CRUD Activo', 'aura-suite'); ?>
                    </span>
                </div>
                <h1 class="aura-hero-title"><?php esc_html_e('Gestión de Etiquetas', 'aura-suite'); ?></h1>
                <p class="aura-hero-desc">
                    <?php esc_html_e('Crea, organiza, colorea, estandariza, fusiona y audita las etiquetas transversales para proyectos, sedes y campañas contables.', 'aura-suite'); ?>
                </p>
            </div>
        </div>

        <div class="aura-hero-actions">
            <?php if ($can_create): ?>
                <button type="button" class="aura-btn aura-btn-primary" id="btn-open-create-modal"
                    style="background:#2563eb; color:#ffffff; border-color:#1d4ed8; font-weight:700; box-shadow: 0 4px 14px rgba(37,99,235,0.35);">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <span><?php esc_html_e('+ Nueva Etiqueta', 'aura-suite'); ?></span>
                </button>
            <?php endif; ?>

            <button type="button" class="aura-btn aura-btn-secondary" id="btn-export-tags-csv"
                data-tooltip="<?php esc_attr_e('Descargar catálogo y balances en archivo CSV', 'aura-suite'); ?>">
                <span class="dashicons dashicons-download"></span>
                <span><?php esc_html_e('Exportar CSV', 'aura-suite'); ?></span>
            </button>

            <button type="button" class="aura-btn aura-btn-secondary" id="btn-toggle-cloud"
                data-tooltip="<?php esc_attr_e('Alternar visualización de la nube de etiquetas', 'aura-suite'); ?>">
                <span class="dashicons dashicons-cloud"></span>
                <span><?php esc_html_e('Nube de Tags', 'aura-suite'); ?></span>
            </button>

            <a href="<?php echo esc_url(admin_url('admin.php?page=aura-financial-search')); ?>"
                class="aura-btn aura-btn-secondary"
                data-tooltip="<?php esc_attr_e('Filtrar y buscar transacciones por etiquetas', 'aura-suite'); ?>">
                <span class="dashicons dashicons-search"></span>
                <span><?php esc_html_e('Búsqueda Avanzada', 'aura-suite'); ?></span>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=aura-financial-transactions')); ?>"
                class="aura-btn aura-btn-secondary"
                data-tooltip="<?php esc_attr_e('Ir al listado general de transacciones', 'aura-suite'); ?>">
                <span class="dashicons dashicons-list-view"></span>
                <span><?php esc_html_e('Transacciones', 'aura-suite'); ?></span>
            </a>
        </div>
    </div>

    <!-- ── Mensajes Globales y Notificaciones ──────────────────────── -->
    <div id="aura-tags-notice" style="display:none;"></div>

    <!-- ── Tarjetas KPI de Resumen de Etiquetas ────────────────────── -->
    <div class="aura-kpi-grid" style="margin-bottom: 24px;">
        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #3b82f6;">
                <span class="dashicons dashicons-tag"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e('Etiquetas en Catálogo', 'aura-suite'); ?></span>
                    <span class="aura-help-icon"
                        data-tooltip="<?php esc_attr_e('Total de etiquetas registradas en el catálogo maestro.', 'aura-suite'); ?>"><span
                            class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #2563eb;">
                    <span id="kpi-total-tags"><?php echo esc_html(number_format($total_unique_tags)); ?></span>
                </div>
                <div class="stat-sub" id="kpi-tags-breakdown">
                    <?php esc_html_e('Catálogo general de taxonomía', 'aura-suite'); ?></div>
            </div>
        </div>

        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e('Movimientos Vinculados', 'aura-suite'); ?></span>
                    <span class="aura-help-icon"
                        data-tooltip="<?php esc_attr_e('Número total de asignaciones a transacciones contables.', 'aura-suite'); ?>"><span
                            class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #059669;">
                    <span id="kpi-total-usages"><?php echo esc_html(number_format($total_tag_usages)); ?></span>
                </div>
                <div class="stat-sub"><?php esc_html_e('Usos en transacciones históricas', 'aura-suite'); ?></div>
            </div>
        </div>

        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #6366f1;">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e('Volumen Financiero', 'aura-suite'); ?></span>
                    <span class="aura-help-icon"
                        data-tooltip="<?php esc_attr_e('Volumen absoluto acumulado de todos los movimientos etiquetados.', 'aura-suite'); ?>"><span
                            class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #4f46e5;">
                    <span id="kpi-total-volume">$0.00</span>
                </div>
                <div class="stat-sub"><?php esc_html_e('Monto global movilizado', 'aura-suite'); ?></div>
            </div>
        </div>

        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #0ea5e9;">
                <span class="dashicons dashicons-chart-pie"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e('Balance Neto Global', 'aura-suite'); ?></span>
                    <span class="aura-help-icon"
                        data-tooltip="<?php esc_attr_e('Ingresos totales etiquetados menos egresos etiquetados.', 'aura-suite'); ?>"><span
                            class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #0284c7;">
                    <span id="kpi-total-balance">$0.00</span>
                </div>
                <div class="stat-sub" id="kpi-balance-status">
                    <?php esc_html_e('Calculando margen...', 'aura-suite'); ?></div>
            </div>
        </div>
    </div>

    <!-- ── Paneles Superiores (Nube y Herramientas Rápidas) ─────────── -->
    <div id="aura-tags-collapsible-panels" class="aura-tags-layout" style="margin-bottom: 24px;">

        <!-- Nube Interactiva de Etiquetas -->
        <div class="aura-card aura-tags-card aura-tags-cloud-card">
            <div class="aura-card-header">
                <div class="aura-card-title-group">
                    <span class="dashicons dashicons-cloud" style="color: #3b82f6;"></span>
                    <h3><?php esc_html_e('Nube Interactiva de Frecuencia', 'aura-suite'); ?></h3>
                    <span class="aura-help-icon"
                        data-tooltip="<?php esc_attr_e('El tamaño visual es proporcional a la frecuencia de uso. Haz clic en una para filtrar la tabla.', 'aura-suite'); ?>"><span
                            class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div>
                    <button type="button" class="aura-btn aura-btn-secondary" id="btn-cloud-reload"
                        style="padding: 4px 8px; font-size: 11.5px;">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </div>
            </div>
            <div class="aura-card-body">
                <div id="aura-tag-cloud" class="aura-tag-cloud">
                    <span class="spinner is-active" style="float:none;"></span>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas Directas -->
        <div class="aura-card aura-tags-card aura-tags-actions-card">
            <div class="aura-card-header">
                <div class="aura-card-title-group">
                    <span class="dashicons dashicons-randomize" style="color: #6366f1;"></span>
                    <h3><?php esc_html_e('Estandarización y Fusión Rápida', 'aura-suite'); ?></h3>
                    <span class="aura-help-icon"
                        data-tooltip="<?php esc_attr_e('Herramientas rápidas para unificar o renombrar tags en todas las transacciones históricas.', 'aura-suite'); ?>"><span
                            class="dashicons dashicons-editor-help"></span></span>
                </div>
            </div>
            <div class="aura-card-body">

                <!-- Renombrar Rápido -->
                <div class="aura-tags-op-box">
                    <h4 class="aura-tags-op-title">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Renombrado Rápido', 'aura-suite'); ?>
                    </h4>
                    <p class="aura-tags-op-desc">
                        <?php esc_html_e('Actualiza el nombre de la etiqueta en el catálogo y en todas las transacciones vinculadas.', 'aura-suite'); ?>
                    </p>
                    <div class="aura-tags-op-row">
                        <input type="text" id="rename-old" class="aura-input"
                            placeholder="<?php esc_attr_e('Etiqueta actual...', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt aura-op-arrow"></span>
                        <input type="text" id="rename-new" class="aura-input"
                            placeholder="<?php esc_attr_e('Nuevo nombre...', 'aura-suite'); ?>">
                        <button type="button" id="btn-rename-tag" class="aura-btn aura-btn-primary">
                            <span class="dashicons dashicons-yes"></span>
                            <span><?php esc_html_e('Renombrar', 'aura-suite'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Fusionar Rápido -->
                <div class="aura-tags-op-box" style="margin-top: 16px;">
                    <h4 class="aura-tags-op-title">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Fusión Directa', 'aura-suite'); ?>
                    </h4>
                    <p class="aura-tags-op-desc">
                        <?php esc_html_e('La etiqueta origen se consolidará en la destino sin duplicar valores en movimientos.', 'aura-suite'); ?>
                    </p>
                    <div class="aura-tags-op-row">
                        <input type="text" id="merge-source" class="aura-input"
                            placeholder="<?php esc_attr_e('Origen (a eliminar)...', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt aura-op-arrow"></span>
                        <input type="text" id="merge-target" class="aura-input"
                            placeholder="<?php esc_attr_e('Destino (a conservar)...', 'aura-suite'); ?>">
                        <button type="button" id="btn-merge-tags" class="aura-btn aura-btn-secondary">
                            <span class="dashicons dashicons-randomize"></span>
                            <span><?php esc_html_e('Fusionar', 'aura-suite'); ?></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div><!-- /#aura-tags-collapsible-panels -->

    <!-- ── Directorio Completo de Etiquetas ────────────────────────── -->
    <div class="aura-card aura-tags-table-card">

        <!-- Cabecera de la Tabla y Filtros -->
        <div class="aura-card-header" style="display: flex; flex-direction: column; gap: 16px;">

            <div
                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%;">
                <div class="aura-card-title-group">
                    <span class="dashicons dashicons-list-view" style="color: #10b981;"></span>
                    <h3 style="margin:0;"><?php esc_html_e('Catálogo Maestro de Etiquetas', 'aura-suite'); ?></h3>
                    <span class="aura-badge aura-badge-blue" id="table-tags-counter">
                        <?php printf(esc_html__('%d etiquetas', 'aura-suite'), $total_unique_tags); ?>
                    </span>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <!-- Buscador en Tiempo Real -->
                    <div class="aura-table-search-box" style="position: relative;">
                        <span class="dashicons dashicons-search search-icon"
                            style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></span>
                        <input type="search" id="tags-table-filter" class="aura-input"
                            placeholder="<?php esc_attr_e('Buscar por nombre o descripción...', 'aura-suite'); ?>"
                            style="padding-left: 32px; width: 280px; height: 38px;">
                    </div>

                    <!-- Selector de Ordenación -->
                    <select id="tags-table-sort" class="aura-select" style="height: 38px; font-size: 13px;">
                        <option value="count_desc"><?php esc_html_e('Más Usadas', 'aura-suite'); ?></option>
                        <option value="count_asc"><?php esc_html_e('Menos Usadas', 'aura-suite'); ?></option>
                        <option value="name_asc"><?php esc_html_e('Nombre (A - Z)', 'aura-suite'); ?></option>
                        <option value="name_desc"><?php esc_html_e('Nombre (Z - A)', 'aura-suite'); ?></option>
                        <option value="volume_desc"><?php esc_html_e('Mayor Volumen ($)', 'aura-suite'); ?></option>
                        <option value="balance_desc"><?php esc_html_e('Mayor Balance Neto ($)', 'aura-suite'); ?>
                        </option>
                        <option value="balance_asc"><?php esc_html_e('Menor Balance Neto ($)', 'aura-suite'); ?>
                        </option>
                        <option value="date_desc"><?php esc_html_e('Último Uso (Reciente)', 'aura-suite'); ?></option>
                    </select>

                    <!-- Selector por Página -->
                    <select id="tags-table-per-page" class="aura-select"
                        style="height: 38px; font-size: 13px; width: 110px;">
                        <option value="10">10 / pág.</option>
                        <option value="25" selected>25 / pág.</option>
                        <option value="50">50 / pág.</option>
                        <option value="100">100 / pág.</option>
                        <option value="all"><?php esc_html_e('Todas', 'aura-suite'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Filtros por Pestañas / Tabs -->
            <div class="aura-filter-tabs-row"
                style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 12px;">
                <button type="button" class="aura-filter-pill is-active" data-filter="all">
                    <span><?php esc_html_e('Todas', 'aura-suite'); ?></span>
                    <span class="aura-pill-count" id="pill-count-all">0</span>
                </button>
                <button type="button" class="aura-filter-pill" data-filter="active">
                    <span class="dashicons dashicons-yes-alt"
                        style="color:#10b981;font-size:14px;width:14px;height:14px;"></span>
                    <span><?php esc_html_e('Con Movimientos', 'aura-suite'); ?></span>
                    <span class="aura-pill-count" id="pill-count-active">0</span>
                </button>
                <button type="button" class="aura-filter-pill" data-filter="empty">
                    <span class="dashicons dashicons-tag"
                        style="color:#64748b;font-size:14px;width:14px;height:14px;"></span>
                    <span><?php esc_html_e('Sin Movimientos (Catálogo)', 'aura-suite'); ?></span>
                    <span class="aura-pill-count" id="pill-count-empty">0</span>
                </button>
                <button type="button" class="aura-filter-pill" data-filter="surplus">
                    <span style="color:#10b981;font-weight:900;">+</span>
                    <span><?php esc_html_e('Superávit Contable', 'aura-suite'); ?></span>
                    <span class="aura-pill-count" id="pill-count-surplus">0</span>
                </button>
                <button type="button" class="aura-filter-pill" data-filter="deficit">
                    <span style="color:#ef4444;font-weight:900;">−</span>
                    <span><?php esc_html_e('Déficit Contable', 'aura-suite'); ?></span>
                    <span class="aura-pill-count" id="pill-count-deficit">0</span>
                </button>
            </div>

        </div><!-- /.aura-card-header -->

        <!-- ── Barra Flotante de Acciones en Lote (Bulk Actions) ─────── -->
        <div id="aura-tags-bulk-bar" class="aura-bulk-actions-bar" style="display:none;">
            <div class="aura-bulk-left">
                <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                <span id="bulk-selected-count">0</span> <?php esc_html_e('etiquetas seleccionadas', 'aura-suite'); ?>
            </div>
            <div class="aura-bulk-actions">
                <button type="button" id="btn-bulk-merge" class="aura-btn aura-btn-secondary"
                    style="font-size:12px; padding:6px 12px;">
                    <span class="dashicons dashicons-randomize"></span>
                    <span><?php esc_html_e('Fusionar Seleccionadas', 'aura-suite'); ?></span>
                </button>
                <?php if ($can_delete): ?>
                    <button type="button" id="btn-bulk-delete" class="aura-btn aura-btn-danger"
                        style="font-size:12px; padding:6px 12px; background:#dc2626; color:#ffffff; border-color:#b91c1c;">
                        <span class="dashicons dashicons-trash"></span>
                        <span><?php esc_html_e('Eliminar Seleccionadas', 'aura-suite'); ?></span>
                    </button>
                <?php endif; ?>
                <button type="button" id="btn-bulk-deselect" class="aura-btn aura-btn-secondary"
                    style="font-size:12px; padding:6px 10px;">
                    <span><?php esc_html_e('Deseleccionar', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>

        <!-- Cuerpo de la Tabla -->
        <div class="aura-card-body" style="padding: 0;">
            <div class="aura-table-responsive-wrap">
                <table id="aura-tags-table" class="aura-modern-table aura-tags-table">
                    <thead>
                        <tr>
                            <th style="width: 46px; text-align: center;">
                                <input type="checkbox" id="tags-select-all" class="aura-checkbox"
                                    title="<?php esc_attr_e('Seleccionar todas', 'aura-suite'); ?>">
                            </th>
                            <th style="width: 55px; text-align: center;">
                                <span>#</span>
                            </th>
                            <th style="width: 25%;">
                                <span><?php esc_html_e('Etiqueta / Color', 'aura-suite'); ?></span>
                            </th>
                            <th style="width: 15%; text-align: center;">
                                <span><?php esc_html_e('Usos / Movimientos', 'aura-suite'); ?></span>
                            </th>
                            <th style="width: 15%; text-align: right;">
                                <span><?php esc_html_e('Volumen Total', 'aura-suite'); ?></span>
                            </th>
                            <th style="width: 15%; text-align: right;">
                                <span><?php esc_html_e('Balance Neto', 'aura-suite'); ?></span>
                            </th>
                            <th style="width: 13%; text-align: center;" class="aura-col-tablet-hidden">
                                <span><?php esc_html_e('Último Uso', 'aura-suite'); ?></span>
                            </th>
                            <th style="width: 140px; text-align: right;">
                                <span><?php esc_html_e('Acciones', 'aura-suite'); ?></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tags-table-body">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px 20px;">
                                <span class="spinner is-active" style="float:none; margin:0 auto 10px auto;"></span>
                                <p style="color:#64748b; margin:0; font-size:13px;">
                                    <?php esc_html_e('Cargando catálogo maestro de etiquetas...', 'aura-suite'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Controles de Paginación ─────────────────────────── -->
            <div id="aura-tags-pagination" class="aura-pagination-bar"
                style="display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-top:1px solid #f1f5f9; flex-wrap:wrap; gap:12px;">
                <div class="aura-pagination-info" style="font-size:13px; color:#64748b;">
                    <span id="pagination-info-text"><?php esc_html_e('Cargando registros...', 'aura-suite'); ?></span>
                </div>
                <div class="aura-pagination-controls" id="pagination-buttons"
                    style="display:flex; gap:6px; align-items:center;">
                    <!-- Botones dinámicos vía JS -->
                </div>
            </div>

        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         MODALES INTEGRADOS
         ═══════════════════════════════════════════════════════════════ -->

    <!-- ═══════════════════════════════════════════════════════════════
         MODALES INTEGRADOS AURA (SISTEMA MODERNO DE TAXONOMÍA)
         ═══════════════════════════════════════════════════════════════ -->

    <!-- ── 1. Modal Crear Nueva Etiqueta ────────────────────────────── -->
    <div id="aura-create-tag-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="create-modal-title">
        <div class="aura-modal-content aura-modal-md">
            <div class="aura-modal-header aura-modal-header--primary">
                <div class="aura-modal-header-main">
                    <div class="aura-modal-icon-badge">
                        <span class="dashicons dashicons-plus-alt2"></span>
                    </div>
                    <div>
                        <h2 id="create-modal-title"><?php esc_html_e('Registrar Nueva Etiqueta', 'aura-suite'); ?></h2>
                        <p class="aura-modal-subtitle"><?php esc_html_e('Añade un nuevo clasificador transversal al catálogo maestro contable.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="btn-close-create-modal" aria-label="<?php esc_attr_e('Cerrar ventana', 'aura-suite'); ?>">&times;</button>
            </div>

            <div class="aura-modal-body">
                <!-- Nombre de la Etiqueta -->
                <div class="aura-modal-field">
                    <label for="create-tag-name" class="aura-modal-label">
                        <?php esc_html_e('Nombre de la Etiqueta', 'aura-suite'); ?> <span class="aura-required-star">*</span>
                    </label>
                    <div class="aura-input-affix-wrap">
                        <span class="aura-input-affix-prefix">#</span>
                        <input type="text" id="create-tag-name" class="aura-modal-input aura-input-with-prefix"
                            placeholder="<?php esc_attr_e('ej. sede-norte, black-friday, remodelacion', 'aura-suite'); ?>"
                            autocomplete="off" maxlength="50" autofocus>
                    </div>
                    <p class="aura-modal-help-text">
                        <?php esc_html_e('Usa nombres cortos sin espacios ni comas. Se normalizará a minúsculas automáticamente.', 'aura-suite'); ?>
                    </p>
                </div>

                <!-- Selector de Color Interactivo -->
                <div class="aura-modal-field">
                    <label class="aura-modal-label">
                        <?php esc_html_e('Color Distintivo', 'aura-suite'); ?>
                    </label>
                    <div class="aura-color-picker-suite">
                        <div class="aura-color-swatches" id="create-color-swatches">
                            <button type="button" class="aura-color-swatch is-selected" data-color="#3b82f6" style="--swatch-color:#3b82f6;" title="<?php esc_attr_e('Azul Clásico', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#10b981" style="--swatch-color:#10b981;" title="<?php esc_attr_e('Esmeralda', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#6366f1" style="--swatch-color:#6366f1;" title="<?php esc_attr_e('Índigo', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#8b5cf6" style="--swatch-color:#8b5cf6;" title="<?php esc_attr_e('Violeta', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#ec4899" style="--swatch-color:#ec4899;" title="<?php esc_attr_e('Rosa Magenta', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#f59e0b" style="--swatch-color:#f59e0b;" title="<?php esc_attr_e('Ámbar / Dorado', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#06b6d4" style="--swatch-color:#06b6d4;" title="<?php esc_attr_e('Cian Eléctrico', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#f97316" style="--swatch-color:#f97316;" title="<?php esc_attr_e('Naranja Vivo', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#64748b" style="--swatch-color:#64748b;" title="<?php esc_attr_e('Grafito Neutral', 'aura-suite'); ?>"></button>
                            <label class="aura-color-custom-btn" title="<?php esc_attr_e('Elegir color personalizado', 'aura-suite'); ?>">
                                <input type="color" id="create-tag-color-custom" value="#3b82f6">
                                <span class="dashicons dashicons-color-picker"></span>
                            </label>
                        </div>
                        <input type="hidden" id="create-tag-color" value="#3b82f6">
                    </div>
                </div>

                <!-- Vista Previa Dinámica del Badge -->
                <div class="aura-tag-preview-card">
                    <span class="aura-preview-card-caption"><?php esc_html_e('Vista Previa en Tiempo Real:', 'aura-suite'); ?></span>
                    <div class="aura-preview-badge-stage">
                        <span id="create-tag-preview" class="aura-tag-chip aura-tag-chip--preview">
                            <span class="dashicons dashicons-tag"></span>
                            <span id="create-tag-preview-text">#etiqueta</span>
                        </span>
                    </div>
                </div>

                <!-- Descripción Opcional -->
                <div class="aura-modal-field">
                    <label for="create-tag-desc" class="aura-modal-label">
                        <?php esc_html_e('Descripción o Propósito', 'aura-suite'); ?> <span class="aura-modal-label-optional">(<?php esc_html_e('Opcional', 'aura-suite'); ?>)</span>
                    </label>
                    <textarea id="create-tag-desc" class="aura-modal-input aura-modal-textarea" rows="2"
                        placeholder="<?php esc_attr_e('Describe el objetivo, proyecto o contexto de esta etiqueta...', 'aura-suite'); ?>"></textarea>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" class="aura-btn aura-btn-secondary" id="btn-cancel-create">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="button" class="aura-btn aura-btn-primary aura-btn-lift" id="btn-submit-create-tag">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e('Guardar Etiqueta', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 2. Modal Editar Etiqueta ─────────────────────────────────── -->
    <div id="aura-edit-tag-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
        <div class="aura-modal-content aura-modal-md">
            <div class="aura-modal-header aura-modal-header--primary">
                <div class="aura-modal-header-main">
                    <div class="aura-modal-icon-badge">
                        <span class="dashicons dashicons-edit"></span>
                    </div>
                    <div>
                        <h2 id="edit-modal-title"><?php esc_html_e('Editar Etiqueta', 'aura-suite'); ?></h2>
                        <p class="aura-modal-subtitle"><?php esc_html_e('Actualiza los atributos, identidad visual o descripción del tag.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="btn-close-edit-modal" aria-label="<?php esc_attr_e('Cerrar ventana', 'aura-suite'); ?>">&times;</button>
            </div>

            <div class="aura-modal-body">
                <input type="hidden" id="edit-tag-id" value="0">
                <input type="hidden" id="edit-tag-old-name" value="">

                <!-- Nombre de la Etiqueta -->
                <div class="aura-modal-field">
                    <label for="edit-tag-name" class="aura-modal-label">
                        <?php esc_html_e('Nombre de la Etiqueta', 'aura-suite'); ?> <span class="aura-required-star">*</span>
                    </label>
                    <div class="aura-input-affix-wrap">
                        <span class="aura-input-affix-prefix">#</span>
                        <input type="text" id="edit-tag-name" class="aura-modal-input aura-input-with-prefix"
                            autocomplete="off" maxlength="50">
                    </div>
                </div>

                <!-- Selector de Color Interactivo -->
                <div class="aura-modal-field">
                    <label class="aura-modal-label">
                        <?php esc_html_e('Color Distintivo', 'aura-suite'); ?>
                    </label>
                    <div class="aura-color-picker-suite">
                        <div class="aura-color-swatches" id="edit-color-swatches">
                            <button type="button" class="aura-color-swatch" data-color="#3b82f6" style="--swatch-color:#3b82f6;" title="<?php esc_attr_e('Azul', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#10b981" style="--swatch-color:#10b981;" title="<?php esc_attr_e('Esmeralda', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#6366f1" style="--swatch-color:#6366f1;" title="<?php esc_attr_e('Índigo', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#8b5cf6" style="--swatch-color:#8b5cf6;" title="<?php esc_attr_e('Violeta', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#ec4899" style="--swatch-color:#ec4899;" title="<?php esc_attr_e('Rosa', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#f59e0b" style="--swatch-color:#f59e0b;" title="<?php esc_attr_e('Ámbar', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#06b6d4" style="--swatch-color:#06b6d4;" title="<?php esc_attr_e('Cian', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#f97316" style="--swatch-color:#f97316;" title="<?php esc_attr_e('Naranja', 'aura-suite'); ?>"></button>
                            <button type="button" class="aura-color-swatch" data-color="#64748b" style="--swatch-color:#64748b;" title="<?php esc_attr_e('Grafito', 'aura-suite'); ?>"></button>
                            <label class="aura-color-custom-btn" title="<?php esc_attr_e('Elegir color personalizado', 'aura-suite'); ?>">
                                <input type="color" id="edit-tag-color-custom" value="#3b82f6">
                                <span class="dashicons dashicons-color-picker"></span>
                            </label>
                        </div>
                        <input type="hidden" id="edit-tag-color" value="#3b82f6">
                    </div>
                </div>

                <!-- Vista Previa Dinámica del Badge -->
                <div class="aura-tag-preview-card">
                    <span class="aura-preview-card-caption"><?php esc_html_e('Vista Previa en Tiempo Real:', 'aura-suite'); ?></span>
                    <div class="aura-preview-badge-stage">
                        <span id="edit-tag-preview" class="aura-tag-chip aura-tag-chip--preview">
                            <span class="dashicons dashicons-tag"></span>
                            <span id="edit-tag-preview-text">#etiqueta</span>
                        </span>
                    </div>
                </div>

                <!-- Descripción -->
                <div class="aura-modal-field">
                    <label for="edit-tag-desc" class="aura-modal-label">
                        <?php esc_html_e('Descripción o Propósito', 'aura-suite'); ?>
                    </label>
                    <textarea id="edit-tag-desc" class="aura-modal-input aura-modal-textarea" rows="2"
                        placeholder="<?php esc_attr_e('Notas o propósito contable de esta etiqueta...', 'aura-suite'); ?>"></textarea>
                </div>

                <!-- Sincronización en Cascada -->
                <div class="aura-modal-switch-card">
                    <div class="aura-switch-control">
                        <input type="checkbox" id="edit-tag-sync-txns" checked>
                        <label for="edit-tag-sync-txns" class="aura-switch-slider"></label>
                    </div>
                    <label for="edit-tag-sync-txns" class="aura-switch-label-group">
                        <strong class="aura-switch-title"><?php esc_html_e('Sincronizar transacciones históricas', 'aura-suite'); ?></strong>
                        <span class="aura-switch-desc"><?php esc_html_e('Si modificas el nombre, actualizar automáticamente todas las transacciones existentes en el libro contable.', 'aura-suite'); ?></span>
                    </label>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" class="aura-btn aura-btn-secondary" id="btn-cancel-edit">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="button" class="aura-btn aura-btn-primary aura-btn-lift" id="btn-submit-edit-tag">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e('Guardar Cambios', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 3. Modal Fusionar Etiqueta (Individual) ──────────────────── -->
    <div id="aura-merge-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="merge-modal-title">
        <div class="aura-modal-content aura-modal-sm">
            <div class="aura-modal-header aura-modal-header--indigo">
                <div class="aura-modal-header-main">
                    <div class="aura-modal-icon-badge">
                        <span class="dashicons dashicons-randomize"></span>
                    </div>
                    <div>
                        <h2 id="merge-modal-title"><?php esc_html_e('Fusionar Etiqueta', 'aura-suite'); ?></h2>
                        <p class="aura-modal-subtitle"><?php esc_html_e('Reasigna movimientos y consolida la taxonomía.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="btn-close-merge-modal" aria-label="<?php esc_attr_e('Cerrar ventana', 'aura-suite'); ?>">&times;</button>
            </div>

            <div class="aura-modal-body">
                <p class="aura-modal-lead-desc">
                    <?php esc_html_e('La etiqueta origen se consolidará en la etiqueta destino. Todas las transacciones asociadas serán migradas de inmediato y la etiqueta origen se eliminará del catálogo.', 'aura-suite'); ?>
                </p>

                <div class="aura-merge-flow-card">
                    <div class="aura-modal-field">
                        <span class="aura-merge-step-badge aura-merge-step-badge--source"><?php esc_html_e('Origen (se eliminará)', 'aura-suite'); ?></span>
                        <input type="text" id="modal-merge-source" class="aura-modal-input aura-input-readonly" readonly>
                    </div>

                    <div class="aura-merge-connector">
                        <div class="aura-merge-connector-line"></div>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </div>

                    <div class="aura-modal-field">
                        <span class="aura-merge-step-badge aura-merge-step-badge--target"><?php esc_html_e('Destino (a conservar)', 'aura-suite'); ?> <span class="aura-required-star">*</span></span>
                        <input type="text" id="modal-merge-target" class="aura-modal-input"
                            placeholder="<?php esc_attr_e('Escribe o busca la etiqueta destino...', 'aura-suite'); ?>" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" class="aura-btn aura-btn-secondary" id="btn-cancel-merge">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="button" class="aura-btn aura-btn-indigo aura-btn-lift" id="btn-confirm-merge">
                    <span class="dashicons dashicons-randomize"></span>
                    <span><?php esc_html_e('Confirmar Fusión', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 4. Modal Fusión Masiva (Bulk Merge) ──────────────────────── -->
    <div id="aura-bulk-merge-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="bulk-merge-modal-title">
        <div class="aura-modal-content aura-modal-md">
            <div class="aura-modal-header aura-modal-header--indigo">
                <div class="aura-modal-header-main">
                    <div class="aura-modal-icon-badge">
                        <span class="dashicons dashicons-randomize"></span>
                    </div>
                    <div>
                        <h2 id="bulk-merge-modal-title"><?php esc_html_e('Fusión Masiva de Etiquetas', 'aura-suite'); ?></h2>
                        <p class="aura-modal-subtitle"><?php esc_html_e('Unifica múltiples etiquetas seleccionadas en un solo clasificador.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="btn-close-bulk-merge-modal" aria-label="<?php esc_attr_e('Cerrar ventana', 'aura-suite'); ?>">&times;</button>
            </div>

            <div class="aura-modal-body">
                <p class="aura-modal-lead-desc">
                    <?php esc_html_e('Las siguientes etiquetas convergerán en una única etiqueta de destino. Sus transacciones se fusionarán sin duplicar importes:', 'aura-suite'); ?>
                </p>

                <div class="aura-chips-scroll-container" id="bulk-merge-chips-container">
                    <!-- Chips dinámicos inyectados por JS -->
                </div>

                <div class="aura-modal-field" style="margin-top: 18px;">
                    <label for="bulk-merge-target" class="aura-modal-label">
                        <?php esc_html_e('Etiqueta Destino Unificada', 'aura-suite'); ?> <span class="aura-required-star">*</span>
                    </label>
                    <input type="text" id="bulk-merge-target" class="aura-modal-input"
                        placeholder="<?php esc_attr_e('ej. proyectos-global, marketing-2026', 'aura-suite'); ?>" autocomplete="off">
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" class="aura-btn aura-btn-secondary" id="btn-cancel-bulk-merge">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="button" class="aura-btn aura-btn-indigo aura-btn-lift" id="btn-confirm-bulk-merge">
                    <span class="dashicons dashicons-randomize"></span>
                    <span><?php esc_html_e('Ejecutar Fusión Masiva', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 5. Modal Confirmación de Eliminación Individual ──────────── -->
    <div id="aura-delete-tag-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <div class="aura-modal-content aura-modal-sm">
            <div class="aura-modal-header aura-modal-header--danger">
                <div class="aura-modal-header-main">
                    <div class="aura-modal-icon-badge aura-modal-icon-badge--danger">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div>
                        <h2 id="delete-modal-title"><?php esc_html_e('Confirmar Eliminación', 'aura-suite'); ?></h2>
                        <p class="aura-modal-subtitle"><?php esc_html_e('Esta acción es permanente e irreversible.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="btn-close-delete-modal" aria-label="<?php esc_attr_e('Cerrar ventana', 'aura-suite'); ?>">&times;</button>
            </div>

            <div class="aura-modal-body">
                <div class="aura-delete-confirm-headline">
                    <?php esc_html_e('¿Estás seguro de que deseas eliminar la etiqueta', 'aura-suite'); ?>
                    <span id="delete-tag-name" class="aura-delete-tag-target">#etiqueta</span>?
                </div>

                <div class="aura-modal-alert-box aura-modal-alert-box--danger">
                    <div class="aura-alert-icon">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="aura-alert-text" id="delete-tag-impact-text">
                        <?php esc_html_e('Esta acción desvinculará la etiqueta de todas las transacciones históricas en la base de datos y la borrará del catálogo maestro.', 'aura-suite'); ?>
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" id="cancel-delete-tag" class="aura-btn aura-btn-secondary">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="button" id="confirm-delete-tag" class="aura-btn aura-btn-danger aura-btn-lift">
                    <span class="dashicons dashicons-trash"></span>
                    <span><?php esc_html_e('Sí, Eliminar Permanentemente', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 6. Modal Confirmación de Eliminación Masiva ──────────────── -->
    <div id="aura-bulk-delete-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="bulk-delete-modal-title">
        <div class="aura-modal-content aura-modal-md">
            <div class="aura-modal-header aura-modal-header--danger">
                <div class="aura-modal-header-main">
                    <div class="aura-modal-icon-badge aura-modal-icon-badge--danger">
                        <span class="dashicons dashicons-trash"></span>
                    </div>
                    <div>
                        <h2 id="bulk-delete-modal-title"><?php esc_html_e('Eliminación Masiva de Etiquetas', 'aura-suite'); ?></h2>
                        <p class="aura-modal-subtitle"><?php esc_html_e('Desvinculación y baja de múltiples taxonomías.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="btn-close-bulk-delete-modal" aria-label="<?php esc_attr_e('Cerrar ventana', 'aura-suite'); ?>">&times;</button>
            </div>

            <div class="aura-modal-body">
                <div class="aura-delete-confirm-headline">
                    <?php esc_html_e('¿Confirmas la eliminación de las siguientes', 'aura-suite'); ?>
                    <strong id="bulk-delete-count" class="aura-delete-count-badge">0</strong>
                    <?php esc_html_e('etiquetas seleccionadas?', 'aura-suite'); ?>
                </div>

                <div class="aura-chips-scroll-container aura-chips-scroll-container--danger" id="bulk-delete-chips-container">
                    <!-- Chips inyectados por JS -->
                </div>

                <div class="aura-modal-alert-box aura-modal-alert-box--danger" style="margin-top: 16px;">
                    <div class="aura-alert-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="aura-alert-text">
                        <?php esc_html_e('Las etiquetas serán removidas del catálogo y desvinculadas de todas las transacciones históricas registradas. Esta operación no se puede deshacer.', 'aura-suite'); ?>
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" id="btn-cancel-bulk-delete" class="aura-btn aura-btn-secondary">
                    <?php esc_html_e('Cancelar', 'aura-suite'); ?>
                </button>
                <button type="button" id="btn-confirm-bulk-delete" class="aura-btn aura-btn-danger aura-btn-lift">
                    <span class="dashicons dashicons-trash"></span>
                    <span><?php esc_html_e('Sí, Eliminar Todas', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 7. Modal Visor Rápido de Transacciones Vinculadas ────────── -->
    <div id="aura-tag-txns-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="txns-modal-tag-name">
        <div class="aura-modal-content aura-modal-lg">
            <div class="aura-modal-header aura-modal-header--dark">
                <div class="aura-modal-header-main">
                    <div class="aura-modal-icon-badge aura-modal-icon-badge--emerald">
                        <span class="dashicons dashicons-list-view"></span>
                    </div>
                    <div>
                        <h2>
                            <?php esc_html_e('Transacciones Asociadas a', 'aura-suite'); ?>
                            <span id="txns-modal-tag-name" class="aura-txns-tag-highlight">#tag</span>
                        </h2>
                        <div id="txns-modal-tag-subtitle" class="aura-modal-subtitle">
                            <?php esc_html_e('Auditoría detallada de movimientos contables y balances en libro mayor', 'aura-suite'); ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="btn-close-txns-modal" aria-label="<?php esc_attr_e('Cerrar ventana', 'aura-suite'); ?>">&times;</button>
            </div>

            <div class="aura-modal-body aura-modal-body--flush">
                <div class="aura-table-responsive-wrap aura-table-scroll-container">
                    <table class="aura-modern-table aura-txns-modal-table" style="margin:0;">
                        <thead>
                            <tr>
                                <th style="width: 55px; text-align: center;">#ID</th>
                                <th style="width: 100px;"><?php esc_html_e('Fecha', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Descripción / Concepto', 'aura-suite'); ?></th>
                                <th style="width: 150px;"><?php esc_html_e('Tercero / Pagador', 'aura-suite'); ?></th>
                                <th style="width: 130px;"><?php esc_html_e('Categoría', 'aura-suite'); ?></th>
                                <th style="width: 120px; text-align: right;"><?php esc_html_e('Monto', 'aura-suite'); ?></th>
                                <th style="width: 70px; text-align: center;"><?php esc_html_e('Acción', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="txns-modal-tbody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <div class="aura-loading-state">
                                        <span class="spinner is-active" style="float:none; margin:0 0 10px 0;"></span>
                                        <p style="margin:0; font-size:13px; color:#94a3b8;"><?php esc_html_e('Consultando libro contable...', 'aura-suite'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="aura-modal-footer aura-modal-footer--split">
                <div class="aura-txns-counter-pill">
                    <span class="dashicons dashicons-chart-pie"></span>
                    <span id="txns-modal-counter">0 <?php esc_html_e('transacciones', 'aura-suite'); ?></span>
                </div>
                <div class="aura-modal-footer-actions">
                    <a href="#" id="txns-modal-search-link" target="_blank" class="aura-btn aura-btn-secondary"
                        title="<?php esc_attr_e('Abrir filtros avanzados para esta etiqueta', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-external"></span>
                        <span><?php esc_html_e('Búsqueda Avanzada', 'aura-suite'); ?></span>
                    </a>
                    <button type="button" class="aura-btn aura-btn-primary" id="btn-done-txns-modal">
                        <?php esc_html_e('Cerrar Auditoría', 'aura-suite'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.wrap -->

<script>
    var auraTagsConfig = {
        nonce: '<?php echo esc_js($nonce); ?>',
        ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
        searchUrl: '<?php echo esc_js(admin_url('admin.php?page=aura-financial-search')); ?>',
        currency: '<?php echo esc_js($currency); ?>',
        canCreate: <?php echo $can_create ? 'true' : 'false'; ?>,
        canEdit: <?php echo $can_edit ? 'true' : 'false'; ?>,
        canDelete: <?php echo $can_delete ? 'true' : 'false'; ?>,
        i18n: {
            confirmMerge: '<?php echo esc_js(__('¿Seguro que deseas fusionar estas etiquetas? Esta acción actualizará todas las transacciones vinculadas.', 'aura-suite')); ?>',
            noResults: '<?php echo esc_js(__('No se encontraron etiquetas registradas en el sistema.', 'aura-suite')); ?>',
            deleteSuccess: '<?php echo esc_js(__('Etiqueta eliminada correctamente de todas las transacciones.', 'aura-suite')); ?>',
            renameSuccess: '<?php echo esc_js(__('Etiqueta renombrada con éxito.', 'aura-suite')); ?>',
            mergeSuccess: '<?php echo esc_js(__('Etiquetas fusionadas con éxito.', 'aura-suite')); ?>',
            createSuccess: '<?php echo esc_js(__('Etiqueta registrada con éxito en el catálogo.', 'aura-suite')); ?>',
            updateSuccess: '<?php echo esc_js(__('Etiqueta actualizada con éxito.', 'aura-suite')); ?>',
            bulkDeleteSuccess: '<?php echo esc_js(__('Etiquetas seleccionadas eliminadas correctamente.', 'aura-suite')); ?>',
            bulkMergeSuccess: '<?php echo esc_js(__('Etiquetas seleccionadas fusionadas correctamente.', 'aura-suite')); ?>'
        }
    };
</script>