<?php
/**
 * Template: Log de Auditoría — Biblioteca (Design System v2 + UX/UI Mejorado)
 *
 * KPIs resumen, tabla paginada responsiva, tooltips enriquecidos,
 * inspección de cambios (diff card + modal) y exportación a CSV.
 *
 * @package AuraBusinessSuite
 * @subpackage Library
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$nonce         = wp_create_nonce( 'aura_library_nonce' );
$action_labels = class_exists( 'Aura_Library_Audit' ) ? Aura_Library_Audit::get_action_labels() : [];

global $wpdb;
$audit_table = $wpdb->prefix . 'aura_library_audit';
$audit_stats = [
    'total'  => 0,
    'today'  => 0,
    'week'   => 0,
    'actors' => 0,
];
if ( $wpdb->get_var( "SHOW TABLES LIKE '$audit_table'" ) === $audit_table ) {
    $row_audit = $wpdb->get_row( "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS week_cnt,
        COUNT(DISTINCT NULLIF(user_id, 0)) AS actors_cnt
        FROM {$audit_table}" );
    if ( $row_audit ) {
        $audit_stats['total']  = (int) $row_audit->total;
        $audit_stats['today']  = (int) $row_audit->today_cnt;
        $audit_stats['week']   = (int) $row_audit->week_cnt;
        $audit_stats['actors'] = (int) $row_audit->actors_cnt;
    }
}
?>
<div class="aura-app-wrapper aura-library-audit">
    <div class="wrap aura-app-context">

    <?php
    Aura_UI::render_page_header( [
        'title'    => __( 'Auditoría de Biblioteca', 'aura-business-suite' ),
        'subtitle' => __( 'Historial y trazabilidad de eventos, préstamos, libros y configuraciones', 'aura-business-suite' ),
        'icon'     => 'dashicons-list-view',
        'badges'   => [
            [ 'label' => __( 'Trazabilidad Completa', 'aura-business-suite' ), 'variant' => 'indigo' ],
        ],
        'actions'  => [
            [
                'label' => __( 'Exportar CSV', 'aura-business-suite' ),
                'url'   => '#',
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-download',
                'attr'  => 'id="btn-audit-export" role="button" data-tooltip="' . esc_attr__( 'Descargar registro histórico en formato CSV', 'aura-business-suite' ) . '"',
            ],
        ],
    ] );
    ?>

    <div class="aura-lib-audit-live-bar">
        <span class="live-chip" data-tooltip="<?php esc_attr_e( 'Cada modificación de catálogo, préstamo, reserva o ajuste queda registrada en tiempo real', 'aura-business-suite' ); ?>">
            <span class="pulse-dot"></span>
            <?php esc_html_e( 'Registro de auditoría activo', 'aura-business-suite' ); ?>
        </span>
        <span class="aura-lib-audit-count-pill" id="audit-total-pill" style="display:none;"></span>
    </div>

    <?php
    Aura_UI::render_stats_grid( [
        [
            'id'      => 'kpi-audit-total',
            'icon'    => 'dashicons-shield',
            'value'   => number_format_i18n( $audit_stats['total'] ),
            'label'   => __( 'Eventos Registrados', 'aura-business-suite' ),
            'variant' => 'capital',
            'tooltip' => __( 'Total acumulado de movimientos auditados en biblioteca', 'aura-business-suite' ),
        ],
        [
            'id'      => 'kpi-audit-today',
            'icon'    => 'dashicons-clock',
            'value'   => number_format_i18n( $audit_stats['today'] ),
            'label'   => __( 'Eventos Hoy', 'aura-business-suite' ),
            'variant' => 'income',
            'tooltip' => __( 'Operaciones registradas en el día actual', 'aura-business-suite' ),
        ],
        [
            'id'      => 'kpi-audit-week',
            'icon'    => 'dashicons-calendar-alt',
            'value'   => number_format_i18n( $audit_stats['week'] ),
            'label'   => __( 'Últimos 7 Días', 'aura-business-suite' ),
            'variant' => 'balance',
            'tooltip' => __( 'Actividad reciente registrada en la última semana', 'aura-business-suite' ),
        ],
        [
            'id'      => 'kpi-audit-actors',
            'icon'    => 'dashicons-admin-users',
            'value'   => number_format_i18n( $audit_stats['actors'] ),
            'label'   => __( 'Usuarios Activos', 'aura-business-suite' ),
            'variant' => 'investment',
            'tooltip' => __( 'Cantidad de responsables distintos que ejecutaron cambios', 'aura-business-suite' ),
        ],
    ] );
    ?>

    <div id="audit-notice" style="display:none;" class="notice"></div>

    <!-- ── Filtros (Bootstrap 5.3 Input Groups + Tooltips) ────── -->
    <div class="aura-glass-card aura-filters-card aura-lib-audit-filters">
        <div class="aura-lib-audit-filters-grid">

            <div class="aura-lib-audit-filter-col">
                <label for="audit-filter-action">
                    <?php esc_html_e( 'Acción', 'aura-business-suite' ); ?>
                    <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Filtra por tipo de operación (alta de libro, préstamo, devolución, multa, ajuste)', 'aura-business-suite' ); ?>">?</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">⚡</span>
                    <select id="audit-filter-action" class="form-control">
                        <option value=""><?php esc_html_e( '— Todas —', 'aura-business-suite' ); ?></option>
                        <?php foreach ( $action_labels as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="aura-lib-audit-filter-col">
                <label for="audit-filter-entity">
                    <?php esc_html_e( 'Entidad', 'aura-business-suite' ); ?>
                    <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Filtra por objeto afectado en la base de datos', 'aura-business-suite' ); ?>">?</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">📦</span>
                    <select id="audit-filter-entity" class="form-control">
                        <option value=""><?php esc_html_e( '— Todas —', 'aura-business-suite' ); ?></option>
                        <option value="book"><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?></option>
                        <option value="loan"><?php esc_html_e( 'Préstamo', 'aura-business-suite' ); ?></option>
                        <option value="reservation"><?php esc_html_e( 'Reserva', 'aura-business-suite' ); ?></option>
                        <option value="settings"><?php esc_html_e( 'Configuración', 'aura-business-suite' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="aura-lib-audit-filter-date">
                <label for="audit-date-from">
                    <?php esc_html_e( 'Desde', 'aura-business-suite' ); ?>
                    <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha inicial del rango a consultar', 'aura-business-suite' ); ?>">?</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="audit-date-from" class="form-control">
                </div>
            </div>

            <div class="aura-lib-audit-filter-date">
                <label for="audit-date-to">
                    <?php esc_html_e( 'Hasta', 'aura-business-suite' ); ?>
                    <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha final del rango a consultar', 'aura-business-suite' ); ?>">?</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="audit-date-to" class="form-control">
                </div>
            </div>

            <div class="aura-lib-audit-filter-search">
                <label for="audit-search">
                    <?php esc_html_e( 'Buscar', 'aura-business-suite' ); ?>
                    <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Busca por nombre de usuario, código de acción o tipo de entidad', 'aura-business-suite' ); ?>">?</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">🔍</span>
                    <input type="search" id="audit-search" placeholder="<?php esc_attr_e( 'Acción, entidad, usuario…', 'aura-business-suite' ); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="aura-lib-audit-filter-actions">
                <button type="button" id="btn-audit-filter" class="btn btn-shimmer btn-indigo btn-lift"
                        data-tooltip="<?php esc_attr_e( 'Aplicar los filtros activos al log de auditoría', 'aura-business-suite' ); ?>">
                    <span class="dashicons dashicons-filter"></span>
                    <span><?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?></span>
                </button>
                <button type="button" id="btn-audit-reset" class="btn btn-glass btn-lift"
                        data-tooltip="<?php esc_attr_e( 'Restablecer todos los filtros y recargar la lista', 'aura-business-suite' ); ?>">
                    <span><?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── Tabla Responsiva con Tooltips Enriquecidos ─────────── -->
    <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
        <table class="aura-table display responsive aura-lib-audit-table aura-lib-responsive-table" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:155px;"><?php esc_html_e( 'Fecha', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha y hora exacta en que ocurrió la operación', 'aura-business-suite' ); ?>">?</span></th>
                    <th style="width:170px;"><?php esc_html_e( 'Usuario', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Usuario del sistema que ejecutó la acción', 'aura-business-suite' ); ?>">?</span></th>
                    <th style="width:175px;"><?php esc_html_e( 'Acción', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Tipo de evento auditado con indicador cromático', 'aura-business-suite' ); ?>">?</span></th>
                    <th style="width:120px;"><?php esc_html_e( 'Entidad', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Objeto o tabla afectada (Libro, Préstamo, Reserva, Configuración)', 'aura-business-suite' ); ?>">?</span></th>
                    <th style="width:76px;text-align:center;"><?php esc_html_e( 'ID', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Identificador primario del registro afectado', 'aura-business-suite' ); ?>">?</span></th>
                    <th><?php esc_html_e( 'Detalle (cambios)', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Comparativa antes/después; pasa el cursor o haz clic para inspeccionar todos los campos', 'aura-business-suite' ); ?>">?</span></th>
                    <th style="width:130px;"><?php esc_html_e( 'IP', 'aura-business-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Dirección IP de origen de la petición', 'aura-business-suite' ); ?>">?</span></th>
                </tr>
            </thead>
            <tbody id="audit-tbody">
                <tr>
                    <td colspan="7" style="text-align:center;padding:30px;">
                        <span class="spinner is-active" style="float:none;margin:0 auto;display:block;"></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ── Paginación ────────────────────────────────────────── -->
        <div id="audit-pagination" class="aura-lib-audit-pagination" style="display:none;">
            <span id="audit-page-info" class="aura-lib-audit-page-info"></span>
            <div class="aura-lib-page-btns">
                <button type="button" id="audit-prev" class="btn btn-glass btn-sm btn-lift"
                        data-tooltip="<?php esc_attr_e( 'Página anterior de eventos', 'aura-business-suite' ); ?>">‹</button>
                <button type="button" id="audit-next" class="btn btn-glass btn-sm btn-lift"
                        data-tooltip="<?php esc_attr_e( 'Página siguiente de eventos', 'aura-business-suite' ); ?>">›</button>
            </div>
        </div>
    </div>

    <div class="adp-footer">
        <div>
            Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<!-- Modal Inspector de Cambios (Item 26) -->
<div id="audit-diff-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="audit-diff-modal-title" aria-hidden="true">
    <div class="aura-modal-container aura-lib-audit-modal-container">
        <div class="aura-modal-header">
            <h2 id="audit-diff-modal-title">🔎 <?php esc_html_e( 'Detalle Completo del Evento Auditado', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-modal-close aura-lib-audit-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" id="audit-diff-modal-body"></div>
        <div class="aura-modal-footer">
            <button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-audit-modal-close">
                <?php esc_html_e( 'Cerrar', 'aura-business-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
.aura-lib-audit-live-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}
.aura-lib-audit-count-pill {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 999px;
    background: var(--aura-surface-alt, #f1f5f9);
    border: 1px solid var(--aura-border-light, #e2e8f0);
    color: var(--aura-text-muted, #475569);
}
.aura-lib-audit-filters {
    padding: 16px 20px;
    margin-bottom: 20px;
}
.aura-lib-audit-filters-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.aura-lib-audit-filters-grid label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--aura-text-muted, #64748b);
}
.aura-lib-audit-filter-col  { flex: 0 1 195px; min-width: 170px; }
.aura-lib-audit-filter-date { flex: 0 1 180px; min-width: 160px; }
.aura-lib-audit-filter-search { flex: 1 1 230px; min-width: 200px; }
.aura-lib-audit-filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.aura-lib-audit-table td { vertical-align: middle; font-size: 13px; }
.aura-lib-audit-meta { white-space: nowrap; color: var(--aura-text-muted, #475569); font-size: 12px; font-weight: 500; }
.aura-lib-audit-muted { color: var(--aura-text-muted, #94a3b8); font-size: 12px; }

.aura-lib-audit-user-cell {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.aura-lib-audit-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--aura-indigo-bg, #eef2ff);
    color: var(--aura-indigo, #4f46e5);
    border: 1px solid var(--aura-indigo-border, #c7d2fe);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}
.audit-diff-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-width: 420px;
}
.audit-diff {
    font-size: 11.5px;
    background: var(--aura-surface-alt, #f8fafc);
    border: 1px solid var(--aura-border-light, rgba(148, 163, 184, 0.28));
    border-radius: 8px;
    padding: 7px 10px;
    line-height: 1.45;
}
.audit-diff .diff-key { color: var(--aura-text-muted, #475569); font-weight: 700; }
.audit-diff .diff-old { color: var(--aura-red, #dc2626); text-decoration: line-through; opacity: 0.85; }
.audit-diff .diff-new { color: var(--aura-emerald, #059669); font-weight: 600; }
.aura-audit-inspect-btn {
    align-self: flex-start;
    font-size: 11px !important;
    padding: 2px 8px !important;
    min-height: 24px !important;
}
.aura-lib-audit-pagination {
    margin-top: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.aura-lib-audit-page-info {
    font-size: 13px;
    color: var(--aura-text-muted, #64748b);
    font-weight: 500;
}
.aura-lib-page-btns { display: flex; gap: 6px; }

/* Modal Item 26 */
.aura-lib-modal.aura-modal-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 18px;
}
.aura-lib-audit-modal-container {
    max-width: 680px !important;
    width: min(94vw, 680px) !important;
    margin: auto !important;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 36px);
    overflow: hidden;
}
.aura-lib-audit-modal-container > .aura-modal-header,
.aura-lib-audit-modal-container > .aura-modal-footer { flex-shrink: 0; }
.aura-lib-audit-modal-container > .aura-modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 18px 22px;
}
.aura-audit-modal-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
    padding: 12px;
    border-radius: 10px;
    background: var(--aura-surface-alt, #f8fafc);
    border: 1px solid var(--aura-border-light, #e2e8f0);
    font-size: 12.5px;
}
.aura-audit-modal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}
.aura-audit-modal-table th,
.aura-audit-modal-table td {
    padding: 8px 10px;
    border: 1px solid var(--aura-border-light, #e2e8f0);
    vertical-align: top;
    word-break: break-word;
}
.aura-audit-modal-table th {
    background: var(--aura-surface-alt, #f8fafc);
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    color: var(--aura-text-muted, #64748b);
}

.aura-dark-mode .aura-lib-audit-count-pill {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
}
.aura-dark-mode .audit-diff,
.aura-dark-mode .aura-audit-modal-meta,
.aura-dark-mode .aura-audit-modal-table th {
    background: #0f172a;
    border-color: #334155;
}
.aura-dark-mode .aura-audit-modal-table td {
    border-color: #334155;
}

/* Botón Expandir (+) & Child Rows (Desktop & Mobile) */
.aura-id-toggle-wrap { display: inline-flex; align-items: center; gap: 8px; }
.aura-lib-row-toggle {
    width: 28px; height: 28px; border-radius: 50%;
    border: 1px solid var(--aura-border, #cbd5e1);
    background: var(--aura-surface-card, #ffffff);
    color: var(--aura-indigo, #4f46e5);
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer; padding: 0; transition: all 0.18s ease;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); flex-shrink: 0;
}
.aura-lib-row-toggle .dashicons { font-size: 16px; width: 16px; height: 16px; line-height: 16px; }
.aura-lib-row-toggle:hover,
.aura-lib-row-toggle.is-active {
    background: var(--aura-indigo, #4f46e5);
    border-color: var(--aura-indigo, #4f46e5);
    color: #ffffff;
}
tr.aura-child-row { display: none; background: var(--aura-surface-alt, #f8fafc) !important; }
tr.aura-child-row.is-open { display: table-row; }
tr.aura-child-row > td { padding: 0 !important; border-bottom: 1px solid var(--aura-border-light, #e2e8f0); }
.aura-lib-child-card {
    padding: 14px 18px;
    background: var(--aura-surface-alt, #f8fafc);
    border-left: 4px solid var(--aura-indigo, #4f46e5);
    text-align: left;
}
.aura-child-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px 12px;
    margin-bottom: 10px;
}
.aura-child-meta-item {
    display: flex; flex-direction: column; gap: 2px; padding: 6px 10px;
    border-radius: 8px; background: var(--aura-surface-card, #ffffff);
    border: 1px solid var(--aura-border-light, #e2e8f0);
}
.aura-child-meta-item span { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--aura-text-muted, #64748b); font-weight: 700; }
.aura-child-meta-item strong { font-size: 12.5px; color: var(--tx-primary, var(--aura-text, #0f172a)); }
.aura-dark-mode .aura-lib-row-toggle,
.aura-dark-mode .aura-child-meta-item { background: #1e293b; border-color: #334155; color: #e2e8f0; }
.aura-dark-mode .aura-lib-child-card { background: #0f172a; }
.aura-dark-mode .aura-child-meta-item strong { color: #f8fafc; }

/* Responsive Desktop & Móvil */
.aura-table-responsive-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
@media (max-width: 782px) {
    .aura-lib-responsive-table thead { display: none; }
    .aura-lib-responsive-table,
    .aura-lib-responsive-table tbody {
        display: block;
        width: 100%;
        box-sizing: border-box;
    }
    .aura-lib-responsive-table tbody tr.aura-parent-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        padding: 12px 14px;
        border: 1px solid var(--aura-border-light, #e2e8f0);
        border-radius: 12px;
        background: var(--aura-surface-card, #ffffff);
    }
    .aura-lib-responsive-table tbody tr.aura-parent-row.is-expanded {
        margin-bottom: 0;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
        border-color: var(--aura-indigo-border, #a5b4fc);
    }
    .aura-lib-responsive-table tbody tr.aura-parent-row > td.aura-lib-col-secondary {
        display: none !important;
    }
    .aura-lib-responsive-table tbody tr.aura-parent-row > td {
        border-bottom: none !important;
        padding: 2px 0 !important;
        width: auto;
        white-space: normal !important;
    }
    .aura-lib-responsive-table tbody tr.aura-parent-row > td.aura-lib-col-expand {
        order: 1;
        flex: 1 1 160px;
    }
    .aura-lib-responsive-table tbody tr.aura-parent-row > td.aura-lib-col-user {
        order: 2;
        flex: 0 0 auto;
    }
    .aura-lib-responsive-table tbody tr.aura-parent-row > td.aura-lib-col-action {
        order: 3;
        flex: 1 0 100%;
        padding-top: 6px !important;
    }
    .aura-lib-responsive-table tbody tr.aura-child-row {
        display: none;
        width: 100%;
        margin-bottom: 12px;
    }
    .aura-lib-responsive-table tbody tr.aura-child-row.is-open {
        display: block !important;
    }
    .aura-lib-responsive-table tbody tr.aura-child-row > td {
        display: block;
        width: 100%;
        padding: 0 !important;
        border: none !important;
    }
    .aura-lib-row-toggle { width: 34px; height: 34px; }
    .audit-diff-wrap { max-width: 100%; text-align: left; }
    .aura-dark-mode .aura-lib-responsive-table tbody tr.aura-parent-row {
        background: #1e293b;
        border-color: #334155;
    }
}
</style>

<script>
(function($){
    'use strict';

    var nonce   = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var currPage = 1;
    var lastRows = [];

    var actionBadgeClasses = {
        create_book:        'badge-emerald',
        update_book:        'badge-blue',
        delete_book:        'badge-red',
        create_loan:        'badge-violet',
        return_book:        'badge-emerald',
        extend_loan:        'badge-amber',
        create_reservation: 'badge-cyan',
        cancel_reservation: 'badge-rose',
        register_fine:      'badge-amber',
        update_settings:    'badge-slate'
    };

    var entityMeta = {
        book:        { label: 'Libro',         cls: 'badge-indigo', tip: 'Registro bibliográfico del catálogo' },
        loan:        { label: 'Préstamo',      cls: 'badge-violet', tip: 'Operación de préstamo o devolución' },
        reservation: { label: 'Reserva',       cls: 'badge-cyan',   tip: 'Cola o asignación de reserva' },
        settings:    { label: 'Configuración', cls: 'badge-slate',  tip: 'Parámetros globales de biblioteca' }
    };

    var escHtml = function(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

    function loadAudit(page){
        currPage = page || 1;
        var $tbody = $('#audit-tbody');
        $tbody.html('<tr><td colspan="7" style="text-align:center;padding:30px;"><span class="spinner is-active" style="float:none;display:block;margin:0 auto;"></span></td></tr>');

        $.post(ajaxUrl, {
            action        : 'aura_library_audit_list',
            nonce         : nonce,
            page          : currPage,
            action_filter : $('#audit-filter-action').val(),
            entity_type   : $('#audit-filter-entity').val(),
            date_from     : $('#audit-date-from').val(),
            date_to       : $('#audit-date-to').val(),
            search        : $('#audit-search').val(),
            _t            : Date.now()
        }, function(res){
            if (!res.success){ $tbody.html('<tr><td colspan="7">' + escHtml(res.data && res.data.message ? res.data.message : 'Error') + '</td></tr>'); return; }
            lastRows = res.data.rows || [];
            renderTable(lastRows);
            renderPagination(res.data.page, res.data.total_pages, res.data.total);
            $('#audit-total-pill').text(res.data.total + ' <?php echo esc_js( __( 'eventos coincidentes', 'aura-business-suite' ) ); ?>').show();
        }).fail(function(){
            $tbody.html('<tr><td colspan="7"><?php echo esc_js( __( 'Error de conexión.', 'aura-business-suite' ) ); ?></td></tr>');
        });
    }

    function buildAuditChildCard(r, i){
        var eInfo = entityMeta[r.entity_type] || { label: r.entity_type, cls: 'badge-slate' };
        var oldD = r.old_data || {};
        var newD = r.new_data || {};
        var keys = Object.keys(Object.assign({}, oldD, newD));
        var diffBlock = '';
        if (keys.length) {
            diffBlock = '<div class="audit-diff" style="max-width:100%;">';
            keys.forEach(function(k){
                var vOld = oldD[k] !== undefined && oldD[k] !== null ? String(oldD[k]) : '—';
                var vNew = newD[k] !== undefined && newD[k] !== null ? String(newD[k]) : '—';
                diffBlock += '<div><span class="diff-key">' + escHtml(k) + ':</span> ' +
                    (r.old_data ? '<span class="diff-old">' + escHtml(vOld) + '</span> → ' : '') +
                    '<span class="diff-new">' + escHtml(vNew) + '</span></div>';
            });
            diffBlock += '</div>';
        }
        return '<div class="aura-child-card aura-lib-child-card">' +
            '<div class="aura-child-meta-grid">' +
            '<div class="aura-child-meta-item"><span>Fecha / Hora</span><strong>' + escHtml(r.created_at) + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>Responsable</span><strong>' + escHtml(r.user_name) + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>Entidad / ID</span><strong>' + escHtml(eInfo.label) + ' #' + parseInt(r.entity_id, 10) + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>Dirección IP</span><strong><code>' + escHtml(r.ip_address || '—') + '</code></strong></div>' +
            '</div>' +
            diffBlock +
            '<div style="margin-top:8px;"><button type="button" class="btn btn-glass btn-sm aura-audit-inspect-btn" data-idx="' + i + '">🔎 Abrir en Modal Comparativo</button></div>' +
            '</div>';
    }

    function renderTable(rows){
        if (!rows || !rows.length){
            $('#audit-tbody').html('<tr><td colspan="7" style="text-align:center;padding:24px;"><?php echo esc_js( __( 'No hay registros con los filtros aplicados.', 'aura-business-suite' ) ); ?></td></tr>');
            return;
        }
        var html = '';
        $.each(rows, function(i, r){
            var badgeCls = actionBadgeClasses[r.action] || 'badge-slate';
            var eInfo    = entityMeta[r.entity_type] || { label: r.entity_type, cls: 'badge-slate', tip: 'Entidad afectada' };
            var initials = (r.user_name || '?').trim().charAt(0).toUpperCase();
            var diffHtml = buildDiff(r.old_data, r.new_data, i);

            html += '<tr class="aura-parent-row" data-row-idx="' + i + '">';
            html += '<td data-label="Fecha" class="aura-lib-audit-meta aura-lib-col-expand">' +
                '<div class="aura-id-toggle-wrap">' +
                '<button type="button" class="aura-lib-row-toggle aura-audit-row-toggle" data-idx="' + i + '" aria-expanded="false" data-tooltip="Desplegar todos los detalles del evento"><span class="dashicons dashicons-plus-alt2"></span></button>' +
                '<span data-tooltip="Registrado: ' + escHtml(r.created_at) + '">' + escHtml(r.created_at) + '</span>' +
                '</div></td>';
            html += '<td data-label="Usuario" class="aura-lib-col-user"><div class="aura-lib-audit-user-cell" data-tooltip="Responsable: ' + escHtml(r.user_name) + '"><span class="aura-lib-audit-avatar">' + escHtml(initials) + '</span><span>' + escHtml(r.user_name) + '</span></div></td>';
            html += '<td data-label="Acción" class="aura-lib-col-action"><span class="badge ' + badgeCls + '" data-tooltip="Código de evento: ' + escHtml(r.action) + '">' + escHtml(r.action_label) + '</span></td>';
            html += '<td data-label="Entidad" class="aura-lib-col-secondary"><span class="badge ' + eInfo.cls + '" data-tooltip="' + escHtml(eInfo.tip) + '">' + escHtml(eInfo.label) + '</span></td>';
            html += '<td data-label="ID" class="aura-lib-col-secondary" style="text-align:center;"><code data-tooltip="ID de entidad #' + parseInt(r.entity_id, 10) + '">#' + parseInt(r.entity_id, 10) + '</code></td>';
            html += '<td data-label="Detalle" class="aura-lib-col-secondary">' + diffHtml + '</td>';
            html += '<td data-label="IP" class="aura-lib-audit-muted aura-lib-col-secondary"><span data-tooltip="Dirección IP origen: ' + escHtml(r.ip_address || 'No registrada') + '">' + escHtml(r.ip_address || '—') + '</span></td>';
            html += '</tr>';
            html += '<tr class="aura-child-row" id="audit-child-row-' + i + '"><td colspan="7">' + buildAuditChildCard(r, i) + '</td></tr>';
        });
        $('#audit-tbody').html(html);
    }

    function buildDiffTip(keys, oldData, newData){
        var tip = '<div class="aura-tip-card"><div class="aura-tip-hdr">🔎 Resumen completo (' + keys.length + ' campos)</div>';
        keys.slice(0, 10).forEach(function(k){
            var vOld = oldData && oldData[k] !== undefined && oldData[k] !== null ? String(oldData[k]) : '';
            var vNew = newData && newData[k] !== undefined && newData[k] !== null ? String(newData[k]) : '';
            var valTxt = oldData ? (vOld + ' → ' + vNew) : vNew;
            tip += '<div class="aura-tip-row"><span class="aura-tip-lbl">' + escHtml(k) + ':</span><span class="aura-tip-val">' + escHtml(valTxt) + '</span></div>';
        });
        if (keys.length > 10) {
            tip += '<div class="aura-tip-row"><span class="aura-tip-lbl">+ ' + (keys.length - 10) + ' campos más</span><span class="aura-tip-val">Haz clic para ver todo</span></div>';
        }
        tip += '</div>';
        return encodeURIComponent(tip);
    }

    function buildDiff(oldData, newData, rowIdx){
        if (!newData && !oldData) return '<span class="aura-lib-audit-muted" data-tooltip="Sin payload de cambios asociado">—</span>';
        if (newData && !oldData) {
            var keys = Object.keys(newData);
            if (keys.length === 0) return '<span class="aura-lib-audit-muted">—</span>';
            var out = '<div class="audit-diff-wrap"><div class="audit-diff" data-aura-tooltip="' + buildDiffTip(keys, null, newData) + '">';
            keys.slice(0, 4).forEach(function(k){
                out += '<div><span class="diff-key">' + escHtml(k) + ':</span> <span class="diff-new">' + escHtml(String(newData[k])) + '</span></div>';
            });
            if (keys.length > 4) out += '<div class="aura-lib-audit-muted">…+' + (keys.length - 4) + ' más</div>';
            out += '</div><button type="button" class="btn btn-glass btn-sm aura-audit-inspect-btn" data-idx="' + rowIdx + '" data-tooltip="Abrir comparativa detallada de todos los campos">🔎 Ver detalle</button></div>';
            return out;
        }
        if (oldData && newData) {
            var allKeys = Object.keys(Object.assign({}, oldData, newData));
            var changed = allKeys.filter(function(k){ return String(oldData[k] || '') !== String(newData[k] || ''); });
            if (!changed.length) return '<span class="aura-lib-audit-muted" data-tooltip="Sin variaciones detectadas entre antes y después">Sin cambios</span>';
            var out = '<div class="audit-diff-wrap"><div class="audit-diff" data-aura-tooltip="' + buildDiffTip(changed, oldData, newData) + '">';
            changed.slice(0, 4).forEach(function(k){
                out += '<div><span class="diff-key">' + escHtml(k) + ':</span> ';
                out += '<span class="diff-old">' + escHtml(String(oldData[k] || '—')) + '</span> → ';
                out += '<span class="diff-new">' + escHtml(String(newData[k] || '—')) + '</span></div>';
            });
            if (changed.length > 4) out += '<div class="aura-lib-audit-muted">…+' + (changed.length - 4) + ' más</div>';
            out += '</div><button type="button" class="btn btn-glass btn-sm aura-audit-inspect-btn" data-idx="' + rowIdx + '" data-tooltip="Abrir comparativa completa antes / después">🔎 Ver detalle (' + changed.length + ')</button></div>';
            return out;
        }
        return '<span class="aura-lib-audit-muted">—</span>';
    }

    function openDiffModal(row){
        if (!row) return;
        var badgeCls = actionBadgeClasses[row.action] || 'badge-slate';
        var html = '<div class="aura-audit-modal-meta">' +
            '<div><strong>Fecha:</strong><br>' + escHtml(row.created_at) + '</div>' +
            '<div><strong>Usuario:</strong><br>' + escHtml(row.user_name) + '</div>' +
            '<div><strong>Acción:</strong><br><span class="badge ' + badgeCls + '">' + escHtml(row.action_label) + '</span></div>' +
            '<div><strong>Entidad / ID:</strong><br><code>' + escHtml(row.entity_type) + ' #' + parseInt(row.entity_id, 10) + '</code></div>' +
            '</div>';

        var oldD = row.old_data || {};
        var newD = row.new_data || {};
        var keys = Object.keys(Object.assign({}, oldD, newD));
        if (keys.length) {
            html += '<table class="aura-audit-modal-table"><thead><tr><th>Campo</th><th>Valor Anterior</th><th>Valor Nuevo</th></tr></thead><tbody>';
            keys.forEach(function(k){
                var vOld = oldD[k] !== undefined && oldD[k] !== null ? String(oldD[k]) : '—';
                var vNew = newD[k] !== undefined && newD[k] !== null ? String(newD[k]) : '—';
                var changed = vOld !== vNew;
                html += '<tr><td><strong>' + escHtml(k) + '</strong></td>' +
                    '<td class="' + (changed && row.old_data ? 'diff-old' : 'aura-lib-audit-muted') + '">' + escHtml(vOld) + '</td>' +
                    '<td class="' + (changed ? 'diff-new' : '') + '">' + escHtml(vNew) + '</td></tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<p class="aura-lib-audit-muted">No se registraron campos adicionales para este evento.</p>';
        }

        $('#audit-diff-modal-body').html(html);
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.openModal === 'function') {
            AuraUI.openModal('audit-diff-modal');
        } else {
            $('#audit-diff-modal').css('display', 'flex').addClass('active is-active');
        }
    }

    function closeDiffModal(){
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.closeModal === 'function') {
            AuraUI.closeModal('audit-diff-modal');
        } else {
            $('#audit-diff-modal').removeClass('active is-active').hide();
        }
    }

    function renderPagination(page, totalPages, total){
        if (totalPages <= 1){ $('#audit-pagination').hide(); return; }
        $('#audit-page-info').text(
            '<?php echo esc_js( __( 'Pág.', 'aura-business-suite' ) ); ?> ' + page + ' / ' + totalPages +
            ' — ' + total + ' <?php echo esc_js( __( 'registros', 'aura-business-suite' ) ); ?>'
        );
        $('#audit-prev').prop('disabled', page <= 1);
        $('#audit-next').prop('disabled', page >= totalPages);
        $('#audit-pagination').css('display', 'flex');
    }

    // ── Eventos ─────────────────────────────────────────────────
    $('#btn-audit-filter').on('click', function(){ loadAudit(1); });
    $('#audit-search').on('keypress', function(e){ if (e.which === 13) loadAudit(1); });
    $('#btn-audit-reset').on('click', function(){
        $('#audit-filter-action, #audit-filter-entity').val('');
        $('#audit-date-from, #audit-date-to, #audit-search').val('');
        loadAudit(1);
    });
    $('#audit-prev').on('click', function(){ if (currPage > 1) loadAudit(currPage - 1); });
    $('#audit-next').on('click', function(){ loadAudit(currPage + 1); });

    $(document).on('click', '.aura-audit-row-toggle', function(e){
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var idx  = parseInt($btn.data('idx'), 10);
        var $tr  = $btn.closest('tr.aura-parent-row');
        var $ch  = $('#audit-child-row-' + idx);
        var isOpen = $ch.hasClass('is-open');
        if (isOpen) {
            $ch.removeClass('is-open');
            $tr.removeClass('is-expanded');
            $btn.removeClass('is-active').attr('aria-expanded', 'false')
                .html('<span class="dashicons dashicons-plus-alt2"></span>');
        } else {
            $ch.addClass('is-open');
            $tr.addClass('is-expanded');
            $btn.addClass('is-active').attr('aria-expanded', 'true')
                .html('<span class="dashicons dashicons-minus"></span>');
        }
    });

    $(document).on('click', '.aura-audit-inspect-btn', function(){
        var idx = parseInt($(this).data('idx'), 10);
        if (lastRows[idx]) openDiffModal(lastRows[idx]);
    });

    var _auditBackdropDown = false;
    $(document).on('mousedown', '#audit-diff-modal', function(e){ _auditBackdropDown = (e.target === this); });
    $(document).on('click', '#audit-diff-modal', function(e){ if (_auditBackdropDown && e.target === this) closeDiffModal(); _auditBackdropDown = false; });
    $(document).on('click', '.aura-lib-audit-modal-close', function(){ closeDiffModal(); });
    $(document).on('keydown', function(e){ if (e.key === 'Escape') closeDiffModal(); });

    // ── Exportar CSV ─────────────────────────────────────────────
    $('#btn-audit-export').on('click', function(e){
        e.preventDefault();
        var $f = $('<form method="POST" action="<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>" style="display:none;">');
        $f.append('<input name="action" value="aura_library_audit_export">');
        $f.append('<input name="nonce" value="' + nonce + '">');
        $('body').append($f);
        $f.submit().remove();
    });

    // ── Carga inicial ────────────────────────────────────────────
    loadAudit(1);

})(jQuery);
</script>
