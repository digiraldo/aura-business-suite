<?php
/**
 * Template: Gestión de Becas (Fase 7)
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$te = $wpdb->prefix . 'aura_student_enrollments';
$tc = $wpdb->prefix . 'aura_student_courses';
$ts = $wpdb->prefix . 'aura_students';

// ── Estadísticas para KPIs (calculadas en PHP al render) ──
$stats = [
    'total_active'        => 0,
    'total_discount_all'  => 0.0,
    'total_discount_year' => 0.0,
    'internal_count'      => 0,
    'internal_discount'   => 0.0,
    'external_count'      => 0,
    'external_discount'   => 0.0,
];

$tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$te}'" ) === $te; // phpcs:ignore
if ( $tables_exist ) {
    $year = (int) current_time( 'Y' );
    $stats['total_active']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$te} WHERE scholarship_type != 'none' AND status IN ('active','pending')" ); // phpcs:ignore
    $stats['total_discount_all']  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(base_cost-net_cost),0) FROM {$te} WHERE scholarship_type!='none' AND status IN ('active','pending')" ); // phpcs:ignore
    $stats['total_discount_year'] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(base_cost-net_cost),0) FROM {$te} WHERE scholarship_type!='none' AND status IN ('active','pending') AND YEAR(created_at)=%d", $year ) ); // phpcs:ignore
    $stats['internal_count']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$te} WHERE scholarship_type='internal' AND status IN ('active','pending')" ); // phpcs:ignore
    $stats['internal_discount']   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(base_cost-net_cost),0) FROM {$te} WHERE scholarship_type='internal' AND status IN ('active','pending')" ); // phpcs:ignore
    $stats['external_count']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$te} WHERE scholarship_type='external' AND status IN ('active','pending')" ); // phpcs:ignore
    $stats['external_discount']   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(base_cost-net_cost),0) FROM {$te} WHERE scholarship_type='external' AND status IN ('active','pending')" ); // phpcs:ignore
}

// Cursos para el filtro
$courses = [];
$tc_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tc}'" ) === $tc; // phpcs:ignore
if ( $tc_exists ) {
    $courses = $wpdb->get_results( "SELECT id, name FROM {$tc} WHERE status='active' ORDER BY name ASC" ); // phpcs:ignore
}

$can_assign = current_user_can( 'aura_students_scholarships_assign' ) || current_user_can( 'manage_options' );
$nonce      = wp_create_nonce( 'aura_students_nonce' );
?>
<div class="wrap aura-students-becas">
    <h1><?php esc_html_e( 'Gestión de Becas', 'aura-suite' ); ?></h1>

    <!-- ══════════════ ESTADÍSTICAS KPI ══════════════ -->
    <div class="aura-kpi-row becas-kpis">
        <div class="aura-kpi-card">
            <span class="kpi-icon">🎓</span>
            <div class="kpi-info">
                <span class="kpi-value"><?php echo esc_html( $stats['total_active'] ); ?></span>
                <span class="kpi-label"><?php esc_html_e( 'Becas activas', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-kpi-card kpi-violet">
            <span class="kpi-icon">💰</span>
            <div class="kpi-info">
                <span class="kpi-value">$<?php echo esc_html( number_format( $stats['total_discount_all'], 2 ) ); ?></span>
                <span class="kpi-label"><?php esc_html_e( 'Total descontado (activos)', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-kpi-card kpi-blue">
            <span class="kpi-icon">🏫</span>
            <div class="kpi-info">
                <span class="kpi-value"><?php echo esc_html( $stats['internal_count'] ); ?></span>
                <span class="kpi-sublabel">$<?php echo esc_html( number_format( $stats['internal_discount'], 2 ) ); ?></span>
                <span class="kpi-label"><?php esc_html_e( 'Becas internas', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-kpi-card kpi-green">
            <span class="kpi-icon">🤝</span>
            <div class="kpi-info">
                <span class="kpi-value"><?php echo esc_html( $stats['external_count'] ); ?></span>
                <span class="kpi-sublabel">$<?php echo esc_html( number_format( $stats['external_discount'], 2 ) ); ?></span>
                <span class="kpi-label"><?php esc_html_e( 'Becas externas', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-kpi-card kpi-orange">
            <span class="kpi-icon">📅</span>
            <div class="kpi-info">
                <span class="kpi-value">$<?php echo esc_html( number_format( $stats['total_discount_year'], 2 ) ); ?></span>
                <span class="kpi-label"><?php echo esc_html( sprintf( __( 'Descuento %d', 'aura-suite' ), (int) current_time( 'Y' ) ) ); ?></span>
            </div>
        </div>
    </div>

    <!-- ══════════════ BARRA DE FILTROS ══════════════ -->
    <div class="aura-filter-bar becas-filters">
        <input
            type="text"
            id="sch-search"
            placeholder="<?php esc_attr_e( 'Buscar estudiante…', 'aura-suite' ); ?>"
            class="regular-text"
        />

        <select id="sch-filter-type">
            <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
            <option value="internal"><?php esc_html_e( 'Interna', 'aura-suite' ); ?></option>
            <option value="external"><?php esc_html_e( 'Externa', 'aura-suite' ); ?></option>
        </select>

        <select id="sch-filter-course">
            <option value=""><?php esc_html_e( 'Todos los cursos', 'aura-suite' ); ?></option>
            <?php foreach ( $courses as $course ) : ?>
                <option value="<?php echo esc_attr( $course->id ); ?>">
                    <?php echo esc_html( $course->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="sch-filter-pct">
            <option value="0"><?php esc_html_e( 'Cualquier %', 'aura-suite' ); ?></option>
            <option value="25">≥ 25%</option>
            <option value="50">≥ 50%</option>
            <option value="75">≥ 75%</option>
            <option value="100">100%</option>
        </select>

        <div class="toggle-group">
            <button class="button active" id="btn-only-with-beca" data-mode="only">
                <?php esc_html_e( 'Solo con beca', 'aura-suite' ); ?>
            </button>
            <button class="button" id="btn-show-all" data-mode="all">
                <?php esc_html_e( 'Ver todos', 'aura-suite' ); ?>
            </button>
        </div>

        <button class="button button-secondary" id="btn-reload-scholarships">
            🔄 <?php esc_html_e( 'Actualizar', 'aura-suite' ); ?>
        </button>
    </div>

    <!-- ══════════════ TABLA PRINCIPAL ══════════════ -->
    <div id="becas-table-wrap" class="aura-table-wrap">
        <table class="wp-list-table widefat fixed striped aura-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Curso / Área', 'aura-suite' ); ?></th>
                    <th class="col-center"><?php esc_html_e( 'Beca %', 'aura-suite' ); ?></th>
                    <th class="col-center"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                    <th class="col-right"><?php esc_html_e( 'Costo base', 'aura-suite' ); ?></th>
                    <th class="col-right"><?php esc_html_e( 'Descuento', 'aura-suite' ); ?></th>
                    <th class="col-right"><?php esc_html_e( 'Costo neto', 'aura-suite' ); ?></th>
                    <th class="col-right"><?php esc_html_e( 'Pagado', 'aura-suite' ); ?></th>
                    <th class="col-right"><?php esc_html_e( 'Saldo', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Patrocinador', 'aura-suite' ); ?></th>
                    <?php if ( $can_assign ) : ?>
                        <th class="col-center"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="becas-tbody">
                <tr><td colspan="<?php echo $can_assign ? 11 : 10; ?>" class="aura-loading">
                    <?php esc_html_e( 'Cargando…', 'aura-suite' ); ?>
                </td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div id="becas-pagination" class="aura-pagination" style="display:none;"></div>

    <!-- ══════════════ MODAL: ASIGNAR / EDITAR BECA ══════════════ -->
    <?php if ( $can_assign ) : ?>
    <div id="modal-scholarship" class="aura-modal" style="display:none;">
        <div class="aura-modal-overlay"></div>
        <div class="aura-modal-content aura-modal-md">
            <div class="aura-modal-header">
                <h2 id="modal-sch-title"><?php esc_html_e( 'Asignar Beca', 'aura-suite' ); ?></h2>
                <button class="aura-modal-close" id="btn-close-sch-modal">&times;</button>
            </div>
            <div class="aura-modal-body">

                <!-- Info del estudiante (readonly) -->
                <div class="aura-form-row">
                    <div class="aura-form-col">
                        <label><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></label>
                        <input type="text" id="sch-info-student" readonly class="regular-text aura-readonly" />
                    </div>
                    <div class="aura-form-col">
                        <label><?php esc_html_e( 'Curso', 'aura-suite' ); ?></label>
                        <input type="text" id="sch-info-course" readonly class="regular-text aura-readonly" />
                    </div>
                </div>

                <div class="aura-form-row">
                    <div class="aura-form-col">
                        <label><?php esc_html_e( 'Costo base', 'aura-suite' ); ?></label>
                        <input type="text" id="sch-info-base" readonly class="regular-text aura-readonly" />
                    </div>
                </div>

                <hr />

                <!-- Campos de beca -->
                <div class="aura-form-row">
                    <div class="aura-form-col">
                        <label for="sch-type"><strong><?php esc_html_e( 'Tipo de beca', 'aura-suite' ); ?></strong></label>
                        <select id="sch-type" name="scholarship_type" class="regular-text">
                            <option value="none"><?php esc_html_e( '— Sin beca —', 'aura-suite' ); ?></option>
                            <option value="internal"><?php esc_html_e( 'Interna (cubre el instituto)', 'aura-suite' ); ?></option>
                            <option value="external"><?php esc_html_e( 'Externa (patrocinador)', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div class="aura-form-col">
                        <label for="sch-pct"><strong><?php esc_html_e( 'Porcentaje de descuento (%)', 'aura-suite' ); ?></strong></label>
                        <input type="number" id="sch-pct" name="scholarship_pct"
                               min="0" max="100" value="0" class="small-text" />
                        <div class="sch-quick-pct" style="margin-top:6px;">
                            <button type="button" class="button sch-pct-btn" data-pct="25">25%</button>
                            <button type="button" class="button sch-pct-btn" data-pct="50">50%</button>
                            <button type="button" class="button sch-pct-btn" data-pct="75">75%</button>
                            <button type="button" class="button sch-pct-btn" data-pct="100">100%</button>
                        </div>
                    </div>
                </div>

                <!-- Patrocinador (solo cuando tipo = external) -->
                <div class="aura-form-row" id="row-sponsor" style="display:none;">
                    <div class="aura-form-col aura-form-full">
                        <label for="sch-sponsor"><?php esc_html_e( 'Patrocinador / Entidad', 'aura-suite' ); ?></label>
                        <input type="text" id="sch-sponsor" name="scholarship_sponsor"
                               class="regular-text"
                               placeholder="<?php esc_attr_e( 'Nombre del patrocinador', 'aura-suite' ); ?>" />
                    </div>
                </div>

                <div class="aura-form-row">
                    <div class="aura-form-col aura-form-full">
                        <label for="sch-notes"><?php esc_html_e( 'Notas (opcional)', 'aura-suite' ); ?></label>
                        <textarea id="sch-notes" name="scholarship_notes" rows="2"
                                  class="large-text"
                                  placeholder="<?php esc_attr_e( 'Motivo de la beca, convenio, etc.', 'aura-suite' ); ?>"></textarea>
                    </div>
                </div>

                <!-- Resumen de cálculo en vivo -->
                <div id="sch-calc-preview" class="aura-calc-box" style="display:none;">
                    <table class="sch-calc-table">
                        <tr>
                            <td><?php esc_html_e( 'Costo base:', 'aura-suite' ); ?></td>
                            <td id="calc-base">$0.00</td>
                        </tr>
                        <tr class="calc-discount">
                            <td><?php esc_html_e( 'Descuento (−):', 'aura-suite' ); ?></td>
                            <td id="calc-discount">$0.00</td>
                        </tr>
                        <tr class="calc-net">
                            <td><strong><?php esc_html_e( 'Costo neto:', 'aura-suite' ); ?></strong></td>
                            <td id="calc-net"><strong>$0.00</strong></td>
                        </tr>
                    </table>
                </div>

                <!-- Advertencia cuotas -->
                <div class="aura-notice aura-notice-warning" id="sch-installments-warning" style="display:none;">
                    ⚠️ <?php esc_html_e( 'Se recalcularán las cuotas pendientes con el nuevo costo neto.', 'aura-suite' ); ?>
                </div>

                <!-- Campo oculto -->
                <input type="hidden" id="sch-enrollment-id" value="" />
                <input type="hidden" id="sch-base-cost" value="0" />
                <input type="hidden" id="sch-payment-scheme" value="" />

            </div>
            <div class="aura-modal-footer">
                <button type="button" class="button button-secondary" id="btn-cancel-sch">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-primary" id="btn-save-scholarship">
                    💾 <?php esc_html_e( 'Guardar beca', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /wrap -->

<!-- ══════════════ CSS INLINE ══════════════ -->
<style>
.aura-students-becas { max-width: 1400px; }

/* KPIs */
.becas-kpis { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.aura-kpi-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #8b5cf6;
    border-radius: 8px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 190px;
    flex: 1;
}
.aura-kpi-card.kpi-violet { border-left-color: #7c3aed; }
.aura-kpi-card.kpi-blue   { border-left-color: #3b82f6; }
.aura-kpi-card.kpi-green  { border-left-color: #10b981; }
.aura-kpi-card.kpi-orange { border-left-color: #f59e0b; }
.kpi-icon  { font-size: 1.8em; }
.kpi-info  { display: flex; flex-direction: column; gap: 2px; }
.kpi-value { font-size: 1.35em; font-weight: 700; color: #1f2937; }
.kpi-sublabel { font-size: .82em; color: #6b7280; }
.kpi-label { font-size: .8em; color: #6b7280; }

/* Filtros */
.becas-filters {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}
.toggle-group { display: flex; gap: 0; }
.toggle-group .button { border-radius: 0; }
.toggle-group .button:first-child { border-radius: 3px 0 0 3px; }
.toggle-group .button:last-child  { border-radius: 0 3px 3px 0; }
.toggle-group .button.active { background: #8b5cf6; border-color: #7c3aed; color: #fff; }

/* Tabla */
.aura-table-wrap { margin-bottom: 12px; overflow-x: auto; }
.aura-table { min-width: 900px; }
.col-center { text-align: center !important; }
.col-right  { text-align: right  !important; }
.aura-loading { text-align: center; padding: 20px; color: #6b7280; }

/* Badges */
.badge-none     { background: #e5e7eb; color: #374151; }
.badge-internal { background: #dbeafe; color: #1d4ed8; }
.badge-external { background: #d1fae5; color: #065f46; }
.badge-pct      { background: #ede9fe; color: #5b21b6; font-weight: 700; }
.aura-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: .78em;
    font-weight: 600;
}
.text-red  { color: #dc2626; }
.text-green{ color: #059669; }

/* Paginación */
.aura-pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin: 12px 0 20px;
}
.aura-pagination button { min-width: 36px; }
.aura-pagination button.current { background: #8b5cf6; border-color: #7c3aed; color: #fff; }

/* Modal */
.aura-modal {
    position: fixed; inset: 0; z-index: 99999;
    display: flex; align-items: center; justify-content: center;
}
.aura-modal-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,.5);
}
.aura-modal-content {
    position: relative; z-index: 1;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    width: 600px; max-width: 96vw;
    max-height: 90vh;
    display: flex; flex-direction: column;
}
.aura-modal-md { width: 640px; }
.aura-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
}
.aura-modal-header h2 { margin: 0; font-size: 1.1em; color: #1f2937; }
.aura-modal-close {
    background: none; border: none; font-size: 1.5em;
    cursor: pointer; color: #6b7280;
    line-height: 1; padding: 0 4px;
}
.aura-modal-close:hover { color: #dc2626; }
.aura-modal-body   { padding: 20px; overflow-y: auto; flex: 1; }
.aura-modal-footer {
    padding: 14px 20px;
    border-top: 1px solid #e5e7eb;
    display: flex; justify-content: flex-end; gap: 8px;
}

/* Form layout */
.aura-form-row  { display: flex; gap: 16px; margin-bottom: 14px; }
.aura-form-col  { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.aura-form-full { flex: 2; }
.aura-readonly  { background: #f9fafb !important; color: #6b7280 !important; }

/* Quick pct buttons */
.sch-quick-pct { display: flex; gap: 4px; }
.sch-pct-btn   { padding: 4px 10px !important; font-size: .82em !important; }
.sch-pct-btn.active { background: #8b5cf6; border-color: #7c3aed; color: #fff; }

/* Calc preview */
.aura-calc-box {
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 12px;
}
.sch-calc-table { width: 100%; border-collapse: collapse; }
.sch-calc-table td { padding: 4px 8px; font-size: .9em; }
.sch-calc-table .calc-discount td { color: #dc2626; }
.sch-calc-table .calc-net td { border-top: 1px solid #ddd6fe; color: #5b21b6; font-size: 1em; }

/* Notices */
.aura-notice { border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; font-size: .88em; }
.aura-notice-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
</style>

<!-- ══════════════ JAVASCRIPT ══════════════ -->
<script>
(function ($) {
    'use strict';

    const NONCE     = <?php echo wp_json_encode( $nonce ); ?>;
    const AJAX_URL  = ajaxurl;
    const CAN_ASSIGN= <?php echo $can_assign ? 'true' : 'false'; ?>;
    const COLS      = CAN_ASSIGN ? 11 : 10;

    let currentPage = 1;
    let showAll     = false;

    // ── Helpers ──────────────────────────────────────────
    function fmt(n){ return '$' + parseFloat(n||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }

    function badgeType(type) {
        const labels = { none: '—', internal: '🏫 Interna', external: '🤝 Externa' };
        const cls    = { none: 'badge-none', internal: 'badge-internal', external: 'badge-external' };
        return `<span class="aura-badge ${cls[type]||'badge-none'}">${labels[type]||type}</span>`;
    }

    function badgePct(pct) {
        if (!pct || pct == 0) return '<span class="aura-badge badge-none">0%</span>';
        return `<span class="aura-badge badge-pct">${pct}%</span>`;
    }

    function statusLabel(st) {
        const m = { paid:'✅ Pagado', partial:'🔶 Parcial', unpaid:'⬜ Sin pagar', overdue:'🔴 Vencido' };
        return m[st] || st;
    }

    // ── Cargar tabla ─────────────────────────────────────
    function loadScholarships(page) {
        page = page || 1;
        currentPage = page;

        const tbody = $('#becas-tbody');
        tbody.html(`<tr><td colspan="${COLS}" class="aura-loading">Cargando…</td></tr>`);
        $('#becas-pagination').hide();

        $.post(AJAX_URL, {
            action   : 'aura_students_list_scholarships',
            nonce    : NONCE,
            show_all : showAll ? 1 : 0,
            sch_type : $('#sch-filter-type').val(),
            min_pct  : $('#sch-filter-pct').val(),
            course_id: $('#sch-filter-course').val(),
            search   : $('#sch-search').val(),
            page     : page,
        }, function (res) {
            if (!res.success) {
                tbody.html(`<tr><td colspan="${COLS}" class="aura-loading" style="color:#dc2626">${res.data?.message||'Error'}</td></tr>`);
                return;
            }
            const { rows, total, total_pages } = res.data;

            if (!rows.length) {
                tbody.html(`<tr><td colspan="${COLS}" class="aura-loading">Sin resultados.</td></tr>`);
                return;
            }

            let html = '';
            rows.forEach(function (r) {
                const discount = parseFloat(r.discount_amount || 0);
                const balance  = parseFloat(r.balance_due   || 0);
                const balColor = balance > 0 ? 'text-red' : 'text-green';
                const actionsCol = CAN_ASSIGN
                    ? `<td class="col-center">
                         <button class="button button-small btn-edit-sch"
                                 data-id="${r.enrollment_id}">✏️ Editar</button>
                       </td>`
                    : '';

                html += `<tr>
                    <td>
                        <strong>${escHtml(r.first_name)} ${escHtml(r.last_name)}</strong><br>
                        <small style="color:#6b7280">${escHtml(r.email||'')}</small>
                    </td>
                    <td>
                        ${escHtml(r.course_name)}<br>
                        <small style="color:#6b7280">${escHtml(r.area_name||'')}</small>
                    </td>
                    <td class="col-center">${badgePct(r.scholarship_pct)}</td>
                    <td class="col-center">${badgeType(r.scholarship_type)}</td>
                    <td class="col-right">${fmt(r.base_cost)}</td>
                    <td class="col-right" style="color:#dc2626">${discount > 0 ? '−'+fmt(discount) : '—'}</td>
                    <td class="col-right"><strong>${fmt(r.net_cost)}</strong></td>
                    <td class="col-right">${fmt(r.total_paid)}</td>
                    <td class="col-right ${balColor}">${fmt(balance)}<br><small>${escHtml(statusLabel(r.payment_status))}</small></td>
                    <td>${escHtml(r.scholarship_sponsor||'—')}</td>
                    ${actionsCol}
                </tr>`;
            });
            tbody.html(html);

            // Paginación
            if (total_pages > 1) {
                let pag = '';
                for (let i = 1; i <= total_pages; i++) {
                    pag += `<button class="button${i===page?' current':''}" data-p="${i}">${i}</button>`;
                }
                $('#becas-pagination').html(pag).show();
            }
        });
    }

    function escHtml(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Filtros / reload ──────────────────────────────────
    $('#btn-reload-scholarships, #sch-filter-type, #sch-filter-pct, #sch-filter-course').on('change click', function () {
        loadScholarships(1);
    });

    let searchTimer;
    $('#sch-search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadScholarships(1); }, 350);
    });

    $('#btn-only-with-beca').on('click', function () {
        showAll = false;
        $('#btn-only-with-beca').addClass('active');
        $('#btn-show-all').removeClass('active');
        loadScholarships(1);
    });

    $('#btn-show-all').on('click', function () {
        showAll = true;
        $('#btn-show-all').addClass('active');
        $('#btn-only-with-beca').removeClass('active');
        loadScholarships(1);
    });

    $(document).on('click', '#becas-pagination button', function () {
        loadScholarships(parseInt($(this).data('p'), 10));
    });

    // ── Abrir modal ───────────────────────────────────────
    if (CAN_ASSIGN) {
        $(document).on('click', '.btn-edit-sch', function () {
            const eid = $(this).data('id');
            openScholarshipModal(eid);
        });

        function openScholarshipModal(enrollmentId) {
            $.post(AJAX_URL, {
                action       : 'aura_students_get_enrollment_for_scholarship',
                nonce        : NONCE,
                enrollment_id: enrollmentId,
            }, function (res) {
                if (!res.success) {
                    alert(res.data?.message || 'Error al cargar la inscripción');
                    return;
                }
                const d = res.data;
                $('#modal-sch-title').text(
                    d.scholarship_type && d.scholarship_type !== 'none'
                        ? 'Editar Beca'
                        : 'Asignar Beca'
                );
                $('#sch-enrollment-id').val(d.id);
                $('#sch-base-cost').val(d.base_cost);
                $('#sch-payment-scheme').val(d.payment_scheme);
                $('#sch-info-student').val(d.student_name);
                $('#sch-info-course').val(d.course_name);
                $('#sch-info-base').val('$' + parseFloat(d.base_cost || 0).toFixed(2));
                $('#sch-type').val(d.scholarship_type || 'none');
                $('#sch-pct').val(d.scholarship_pct  || 0);
                $('#sch-sponsor').val(d.scholarship_sponsor || '');
                $('#sch-notes').val(d.scholarship_notes   || '');

                toggleSponsorField();
                updateCalcPreview();

                $('#modal-scholarship').fadeIn(150);
            });
        }

        function toggleSponsorField() {
            if ($('#sch-type').val() === 'external') {
                $('#row-sponsor').show();
            } else {
                $('#row-sponsor').hide();
                $('#sch-sponsor').val('');
            }
        }

        function updateCalcPreview() {
            const base    = parseFloat($('#sch-base-cost').val() || 0);
            const pct     = Math.min(100, Math.max(0, parseFloat($('#sch-pct').val() || 0)));
            const discount= base * (pct / 100);
            const net     = base - discount;
            const scheme  = $('#sch-payment-scheme').val();

            if (pct > 0) {
                $('#calc-base').text('$' + base.toFixed(2));
                $('#calc-discount').text('−$' + discount.toFixed(2));
                $('#calc-net').html('<strong>$' + net.toFixed(2) + '</strong>');
                $('#sch-calc-preview').show();
            } else {
                $('#sch-calc-preview').hide();
            }

            if (scheme === 'installments' && pct > 0) {
                $('#sch-installments-warning').show();
            } else {
                $('#sch-installments-warning').hide();
            }
        }

        // Quick pct buttons
        $(document).on('click', '.sch-pct-btn', function () {
            const pct = $(this).data('pct');
            $('#sch-pct').val(pct);
            $('.sch-pct-btn').removeClass('active');
            $(this).addClass('active');
            updateCalcPreview();
        });

        $('#sch-pct').on('input', function () {
            $('.sch-pct-btn').removeClass('active');
            updateCalcPreview();
        });

        $('#sch-type').on('change', function () {
            toggleSponsorField();
        });

        // Cerrar modal
        $('#btn-close-sch-modal, #btn-cancel-sch, .aura-modal-overlay').on('click', function () {
            $('#modal-scholarship').fadeOut(150);
        });

        // Guardar beca
        $('#btn-save-scholarship').on('click', function () {
            const btn = $(this).prop('disabled', true).text('Guardando…');

            $.post(AJAX_URL, {
                action            : 'aura_students_assign_scholarship',
                nonce             : NONCE,
                enrollment_id     : $('#sch-enrollment-id').val(),
                scholarship_type  : $('#sch-type').val(),
                scholarship_pct   : $('#sch-pct').val(),
                scholarship_sponsor: $('#sch-sponsor').val(),
                scholarship_notes : $('#sch-notes').val(),
            }, function (res) {
                btn.prop('disabled', false).html('💾 Guardar beca');
                if (!res.success) {
                    alert(res.data?.message || 'Error al guardar');
                    return;
                }
                $('#modal-scholarship').fadeOut(150);
                loadScholarships(currentPage);
                // Mini feedback
                const notice = $('<div style="position:fixed;bottom:20px;right:20px;background:#059669;color:#fff;padding:12px 20px;border-radius:8px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.2)">' + escHtml(res.data.message) + '</div>');
                $('body').append(notice);
                setTimeout(function(){ notice.fadeOut(400, function(){ $(this).remove(); }); }, 3500);
            });
        });
    } // end CAN_ASSIGN

    // ── Carga inicial ─────────────────────────────────────
    loadScholarships(1);

})(jQuery);
</script>
