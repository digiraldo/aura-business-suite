<?php
/**
 * Template: Pagos y Cuotas — Fase 5
 *
 * Vista principal: tabla de inscripciones con estado de pagos.
 * Al expandir una fila: schedule de cuotas + historial de pagos.
 * Modal: registro de nuevo pago.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Cargar áreas y cursos para filtros
$areas_table  = $wpdb->prefix . 'aura_areas';
$areas_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $areas_table ) );
$areas        = $areas_exists
    ? $wpdb->get_results( "SELECT id, name FROM `{$areas_table}` WHERE status = 'active' AND type = 'program' ORDER BY name ASC" )
    : [];

$courses_table = $wpdb->prefix . 'aura_student_courses';
$courses = $wpdb->get_results(
    "SELECT id, name, area_id FROM `{$courses_table}` WHERE status = 'active' ORDER BY name ASC"
);

$nonce       = wp_create_nonce( 'aura_students_nonce' );
$can_register = current_user_can( 'aura_students_payments_register' ) || current_user_can( 'manage_options' );
$can_delete   = current_user_can( 'manage_options' );

$current_year  = (int) current_time( 'Y' );
$current_month = (int) current_time( 'n' );
?>
<div class="wrap aura-students-payments-wrap">

    <h1 class="wp-heading-inline" style="color:#8b5cf6;">
        <span class="dashicons dashicons-money-alt" style="vertical-align:middle;"></span>
        <?php esc_html_e( 'Pagos y Cuotas', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <!-- ── KPI minibar ── -->
    <div id="payments-kpi-bar" style="display:flex;gap:16px;flex-wrap:wrap;margin:14px 0 18px;"></div>

    <!-- ── Filtros ── -->
    <div class="aura-filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
        <input type="search" id="pay-search"
               placeholder="<?php esc_attr_e( 'Buscar estudiante…', 'aura-suite' ); ?>"
               style="min-width:200px;padding:6px 10px;border:1px solid #ccc;border-radius:4px;">

        <?php if ( $areas ) : ?>
        <select id="pay-area" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <option value=""><?php esc_html_e( '— Todas las áreas —', 'aura-suite' ); ?></option>
            <?php foreach ( $areas as $area ) : ?>
                <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <select id="pay-course" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <option value=""><?php esc_html_e( '— Todos los cursos —', 'aura-suite' ); ?></option>
            <?php foreach ( $courses as $c ) : ?>
                <option value="<?php echo esc_attr( $c->id ); ?>" data-area="<?php echo esc_attr( $c->area_id ); ?>">
                    <?php echo esc_html( $c->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="pay-status" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <option value=""><?php esc_html_e( '— Todos los estados —', 'aura-suite' ); ?></option>
            <option value="unpaid"><?php esc_html_e( '🔴 Sin pago', 'aura-suite' ); ?></option>
            <option value="partial"><?php esc_html_e( '🟡 Pago parcial', 'aura-suite' ); ?></option>
            <option value="overdue"><?php esc_html_e( '🚨 Vencido', 'aura-suite' ); ?></option>
            <option value="paid"><?php esc_html_e( '✅ Pagado', 'aura-suite' ); ?></option>
        </select>

        <select id="pay-month" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <option value=""><?php esc_html_e( '— Todos los meses —', 'aura-suite' ); ?></option>
            <?php
            $months = [
                1  => __( 'Enero', 'aura-suite' ),   2 => __( 'Febrero', 'aura-suite' ),
                3  => __( 'Marzo', 'aura-suite' ),    4 => __( 'Abril', 'aura-suite' ),
                5  => __( 'Mayo', 'aura-suite' ),      6 => __( 'Junio', 'aura-suite' ),
                7  => __( 'Julio', 'aura-suite' ),    8 => __( 'Agosto', 'aura-suite' ),
                9  => __( 'Septiembre', 'aura-suite' ), 10 => __( 'Octubre', 'aura-suite' ),
                11 => __( 'Noviembre', 'aura-suite' ), 12 => __( 'Diciembre', 'aura-suite' ),
            ];
            foreach ( $months as $num => $name ) :
            ?>
                <option value="<?php echo esc_attr( $num ); ?>" <?php selected( $num, $current_month ); ?>><?php echo esc_html( $name ); ?></option>
            <?php endforeach; ?>
        </select>

        <select id="pay-year" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <option value=""><?php esc_html_e( '— Todos los años —', 'aura-suite' ); ?></option>
            <?php for ( $y = $current_year; $y >= $current_year - 4; $y-- ) : ?>
                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $y, $current_year ); ?>><?php echo esc_html( $y ); ?></option>
            <?php endfor; ?>
        </select>

        <button id="btn-refresh-payments" class="button"><?php esc_html_e( 'Actualizar', 'aura-suite' ); ?></button>
        <button id="btn-clear-filters" class="button"><?php esc_html_e( 'Limpiar filtros', 'aura-suite' ); ?></button>
    </div>

    <!-- ── Tabla principal ── -->
    <table class="wp-list-table widefat fixed striped" id="payments-table">
        <thead>
            <tr>
                <th width="24"></th><!-- Toggle expand -->
                <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Curso / Área', 'aura-suite' ); ?></th>
                <th width="80"><?php esc_html_e( 'Beca %', 'aura-suite' ); ?></th>
                <th width="105"><?php esc_html_e( 'Costo Neto', 'aura-suite' ); ?></th>
                <th width="105"><?php esc_html_e( 'Pagado', 'aura-suite' ); ?></th>
                <th width="105"><?php esc_html_e( 'Saldo', 'aura-suite' ); ?></th>
                <th width="115"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th width="155"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody id="payments-tbody">
            <tr>
                <td colspan="9" style="text-align:center;padding:20px;">
                    <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                    <?php esc_html_e( 'Cargando…', 'aura-suite' ); ?>
                </td>
            </tr>
        </tbody>
    </table>
    <div id="payments-pagination" class="tablenav bottom" style="margin-top:8px;"></div>

</div><!-- .wrap -->

<!-- ══════════════════════════════════════════════════════════════
     MODAL: REGISTRAR PAGO
════════════════════════════════════════════════════════════════ -->
<div id="modal-payment" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-modal-box" style="max-width:560px;width:95%;">
        <div class="aura-modal-header" style="background:#8b5cf6;">
            <h2>💰 <?php esc_html_e( 'Registrar Pago', 'aura-suite' ); ?></h2>
            <button class="aura-modal-close" data-modal="modal-payment" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" style="padding:24px;">

            <!-- Resumen readonly de la inscripción -->
            <div id="payment-enrollment-summary"
                 style="background:#f9f5ff;border-radius:6px;padding:12px 14px;margin-bottom:20px;font-size:13px;">
                <strong id="pay-modal-student">—</strong>
                <span style="color:#888;margin:0 6px;">›</span>
                <span id="pay-modal-course">—</span>
                <br>
                <span style="color:#555;"><?php esc_html_e( 'Saldo pendiente:', 'aura-suite' ); ?></span>
                <strong id="pay-modal-balance" style="color:#ef4444;">$0.00</strong>
            </div>

            <input type="hidden" id="pay-enrollment-id" value="">
            <input type="hidden" id="pay-has-schedule" value="0">

            <table class="form-table" style="margin:0;">
                <tr id="pay-row-installment">
                    <th scope="row">
                        <label for="pay-installment-select"><?php esc_html_e( 'Cuota', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <select id="pay-installment-select" style="min-width:280px;">
                            <option value=""><?php esc_html_e( 'Cargando cuotas…', 'aura-suite' ); ?></option>
                        </select>
                        <span id="pay-installment-urgency" style="margin-left:8px;font-size:13px;"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pay-amount"><?php esc_html_e( 'Monto *', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="pay-amount" min="0.01" step="0.01" value=""
                               style="width:120px;" required>
                        <span style="margin-left:6px;color:#666;" id="pay-currency">USD</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pay-date"><?php esc_html_e( 'Fecha de Pago *', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="date" id="pay-date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pay-method"><?php esc_html_e( 'Método de Pago', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <select id="pay-method">
                            <option value="cash"><?php esc_html_e( 'Efectivo', 'aura-suite' ); ?></option>
                            <option value="transfer"><?php esc_html_e( 'Transferencia', 'aura-suite' ); ?></option>
                            <option value="card"><?php esc_html_e( 'Tarjeta', 'aura-suite' ); ?></option>
                            <option value="check"><?php esc_html_e( 'Cheque', 'aura-suite' ); ?></option>
                            <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pay-reference"><?php esc_html_e( 'N° de Referencia', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="pay-reference" class="regular-text"
                               placeholder="<?php esc_attr_e( 'Número de transferencia, cheque, etc.', 'aura-suite' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pay-notes"><?php esc_html_e( 'Notas', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <textarea id="pay-notes" rows="2" class="large-text"></textarea>
                    </td>
                </tr>
            </table>

            <div id="payment-notice" style="margin-top:12px;display:none;"></div>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                <button id="btn-save-payment" class="button button-primary"
                        style="background:#8b5cf6;border-color:#7c3aed;">
                    💾 <?php esc_html_e( 'Guardar Pago', 'aura-suite' ); ?>
                </button>
                <span id="pay-finance-badge" style="display:none;font-size:12px;color:#059669;">
                    🔗 <?php esc_html_e( 'Se creará transacción en Finanzas', 'aura-suite' ); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     ESTILOS
════════════════════════════════════════════════════════════════ -->
<style>
.aura-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;}
.aura-modal-box{background:#fff;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.22);max-height:90vh;overflow-y:auto;}
.aura-modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-radius:8px 8px 0 0;}
.aura-modal-header h2{margin:0;color:#fff;font-size:18px;}
.aura-modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0 4px;line-height:1;}
.aura-expand-btn{cursor:pointer;color:#8b5cf6;font-size:16px;line-height:1;background:none;border:none;padding:0;}
.aura-detail-row td{background:#faf5ff;padding:16px 20px !important;}
.aura-kpi-card{background:#fff;border:1px solid #e9d5ff;border-radius:8px;padding:12px 18px;min-width:160px;flex:1;}
.aura-kpi-card .kpi-label{color:#555;font-size:12px;margin:0 0 4px;}
.aura-kpi-card .kpi-value{color:#8b5cf6;font-size:22px;font-weight:700;margin:0;}
.aura-kpi-card.kpi-red .kpi-value{color:#ef4444;}
.aura-kpi-card.kpi-green .kpi-value{color:#059669;}
.aura-status-badge{display:inline-block;border-radius:4px;padding:2px 8px;font-size:12px;font-weight:500;}
tr.aura-is-expanded > td:first-child { color:#8b5cf6; }
</style>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════ -->
<script>
(function($){
    'use strict';

    var nonce    = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var currPage = 1;

    var payStatusConfig = {
        paid:    { label:'✅ Pagado',    bg:'#dcfce7', color:'#166534' },
        partial: { label:'🟡 Parcial',   bg:'#fef9c3', color:'#a16207' },
        unpaid:  { label:'🔴 Sin pago',  bg:'#fee2e2', color:'#991b1b' },
        overdue: { label:'🚨 Vencido',   bg:'#fce7f3', color:'#9d174d' }
    };

    var urgencyConfig = {
        paid:    { icon:'✅', color:'#059669' },
        partial: { icon:'🟡', color:'#a16207' },
        overdue: { icon:'🔴', color:'#dc2626' },
        soon:    { icon:'🟡', color:'#d97706' },
        ok:      { icon:'🟢', color:'#059669' },
        pending: { icon:'⏳', color:'#6b7280' }
    };

    // ── Modales ──────────────────────────────────────────────────
    $(document).on('click', '.aura-modal-close', function(){ $('#' + $(this).data('modal')).hide(); });
    $(document).on('keydown', function(e){ if (e.key === 'Escape') $('.aura-modal-overlay:visible').hide(); });
    $(document).on('click', '.aura-modal-overlay', function(e){ if ($(e.target).hasClass('aura-modal-overlay')) $(this).hide(); });

    // ── Filtros área → cursos ─────────────────────────────────────
    $('#pay-area').on('change', function(){
        var aId = $(this).val();
        $('#pay-course option').each(function(){
            var opt = $(this);
            if (!opt.val()) { opt.show(); return; }
            opt.toggle(!aId || opt.data('area') == aId);
        });
        $('#pay-course').val('');
        loadPayments(1);
    });

    // ── Filtros y búsqueda ────────────────────────────────────────
    var searchTimer;
    $('#pay-search').on('input', function(){ clearTimeout(searchTimer); searchTimer = setTimeout(function(){ loadPayments(1); }, 350); });
    $('#pay-course, #pay-status, #pay-month, #pay-year').on('change', function(){ loadPayments(1); });
    $('#btn-refresh-payments').on('click', function(){ loadPayments(1); });
    $('#btn-clear-filters').on('click', function(){
        $('#pay-search').val('');
        $('#pay-area, #pay-course, #pay-status').val('');
        $('#pay-month').val('<?php echo esc_js( $current_month ); ?>');
        $('#pay-year').val('<?php echo esc_js( $current_year ); ?>');
        loadPayments(1);
    });

    // ══════════════════════════════════════════════
    // CARGAR LISTADO PRINCIPAL
    // ══════════════════════════════════════════════

    function loadPayments(page){
        currPage = page || 1;
        var $tbody = $('#payments-tbody');
        $tbody.html('<tr><td colspan="9" style="text-align:center;padding:20px;"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span><?php echo esc_js( __( 'Cargando…', 'aura-suite' ) ); ?></td></tr>');

        $.post(ajaxUrl, {
            action    : 'aura_students_list_payments',
            nonce     : nonce,
            page      : currPage,
            search    : $('#pay-search').val(),
            area_id   : $('#pay-area').val() || 0,
            course_id : $('#pay-course').val() || 0,
            pay_status: $('#pay-status').val(),
            month     : $('#pay-month').val() || 0,
            year      : $('#pay-year').val() || 0
        }, function(res){
            if (!res.success){ $tbody.html('<tr><td colspan="9">' + escHtml(res.data.message) + '</td></tr>'); return; }
            renderKPIs(res.data.rows);
            renderPaymentsTable(res.data.rows);
            renderPagination('#payments-pagination', res.data.page, res.data.total_pages, loadPayments);
        }).fail(function(){
            $tbody.html('<tr><td colspan="9"><?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?></td></tr>');
        });
    }

    function renderKPIs(rows){
        var totalNet = 0, totalPaid = 0, totalBalance = 0, overdueCount = 0;
        $.each(rows, function(i, r){
            totalNet     += parseFloat(r.net_cost     || 0);
            totalPaid    += parseFloat(r.total_paid   || 0);
            totalBalance += parseFloat(r.balance_due  || 0);
            overdueCount += parseInt(r.overdue_count  || 0);
        });
        var html  = '<div class="aura-kpi-card"><p class="kpi-label"><?php echo esc_js( __( 'Costo Total Neto', 'aura-suite' ) ); ?></p><p class="kpi-value">$' + totalNet.toFixed(2) + '</p></div>';
        html += '<div class="aura-kpi-card aura-kpi-green"><p class="kpi-label"><?php echo esc_js( __( 'Total Pagado', 'aura-suite' ) ); ?></p><p class="kpi-value" style="color:#059669;">$' + totalPaid.toFixed(2) + '</p></div>';
        html += '<div class="aura-kpi-card"><p class="kpi-label"><?php echo esc_js( __( 'Saldo Pendiente', 'aura-suite' ) ); ?></p><p class="kpi-value" style="color:#d97706;">$' + totalBalance.toFixed(2) + '</p></div>';
        html += '<div class="aura-kpi-card ' + (overdueCount > 0 ? 'kpi-red' : '') + '"><p class="kpi-label"><?php echo esc_js( __( 'Cuotas Vencidas', 'aura-suite' ) ); ?></p><p class="kpi-value">' + overdueCount + '</p></div>';
        $('#payments-kpi-bar').html(html);
    }

    function renderPaymentsTable(rows){
        if (!rows || !rows.length){
            $('#payments-tbody').html('<tr><td colspan="9" style="text-align:center;padding:20px;"><?php echo esc_js( __( 'No hay inscripciones con los filtros aplicados.', 'aura-suite' ) ); ?></td></tr>');
            return;
        }

        var html = '';
        $.each(rows, function(i, r){
            var fullName    = escHtml(((r.first_name||'') + ' ' + (r.last_name||'')).trim());
            var courseName  = escHtml(r.course_name || '—');
            var areaName    = escHtml(r.area_name   || '');
            var netCost     = parseFloat(r.net_cost   || 0).toFixed(2);
            var paid        = parseFloat(r.total_paid  || 0).toFixed(2);
            var balance     = parseFloat(r.balance_due || 0).toFixed(2);
            var balColor    = parseFloat(balance) > 0 ? '#ef4444' : '#059669';
            var pct         = r.scholarship_pct > 0 ? r.scholarship_pct + '%' : '—';
            var pConf       = payStatusConfig[r.payment_status] || { label: r.payment_status, bg:'#f3f4f6', color:'#374151' };
            var overdue     = parseInt(r.overdue_count || 0);
            var nextDue     = r.next_due_date ? r.next_due_date : '';
            var overdueHtml = overdue > 0 ? ' <span style="color:#dc2626;font-size:11px;font-weight:600;">(' + overdue + ' <?php echo esc_js( __( 'venc.', 'aura-suite' ) ); ?>)</span>' : '';

            // Fila principal
            html += '<tr data-enrollment-id="' + r.id + '" data-balance="' + escHtml(balance) + '" data-has-cat="' + (r.finance_cat_id ? 1 : 0) + '">';
            html += '<td><button class="aura-expand-btn" data-id="' + r.id + '" title="<?php echo esc_js( __( 'Ver cuotas', 'aura-suite' ) ); ?>">▶</button></td>';
            html += '<td><strong>' + fullName + '</strong><br><small style="color:#888;">' + escHtml(r.email||'') + '</small></td>';
            html += '<td>' + courseName + (areaName ? '<br><small style="color:#8b5cf6;">' + areaName + '</small>' : '') + '</td>';
            html += '<td style="text-align:center;">' + pct + '</td>';
            html += '<td style="text-align:right;">$' + netCost + '</td>';
            html += '<td style="text-align:right;color:#059669;">$' + paid + '</td>';
            html += '<td style="text-align:right;color:' + balColor + ';">$' + balance + overdueHtml + '</td>';
            html += '<td><span class="aura-status-badge" style="background:' + pConf.bg + ';color:' + pConf.color + ';">' + pConf.label + '</span>';
            if (nextDue && r.payment_status !== 'paid') {
                html += '<br><small style="color:#888;font-size:11px;">' + '<?php echo esc_js( __( 'Próx.:', 'aura-suite' ) ); ?> ' + escHtml(nextDue) + '</small>';
            }
            html += '</td>';
            html += '<td>';
            if (parseFloat(balance) > 0) {
                html += '<button class="button button-small btn-open-payment"'
                      + ' style="background:#8b5cf6;color:#fff;border-color:#7c3aed;"'
                      + ' data-id="' + r.id + '" data-student="' + encodeURIComponent(fullName) + '"'
                      + ' data-course="' + encodeURIComponent(courseName) + '"'
                      + ' data-balance="' + escHtml(balance) + '"'
                      + ' data-has-cat="' + (r.finance_cat_id ? 1 : 0) + '">➕ <?php echo esc_js( __( 'Pago', 'aura-suite' ) ); ?></button>';
            }
            html += '</td>';
            html += '</tr>';

            // Fila de detalle (oculta, cargada dinámicamente)
            html += '<tr class="aura-detail-row" id="detail-' + r.id + '" style="display:none;">';
            html += '<td colspan="9"><div id="detail-content-' + r.id + '" style="min-height:40px;">';
            html += '<span style="color:#8b5cf6;"><?php echo esc_js( __( 'Cargando cuotas…', 'aura-suite' ) ); ?></span>';
            html += '</div></td>';
            html += '</tr>';
        });

        $('#payments-tbody').html(html);
    }

    // ── Expandir / colapsar fila de cuotas ───────────────────────
    $(document).on('click', '.aura-expand-btn', function(){
        var enrollId = $(this).data('id');
        var $detailRow = $('#detail-' + enrollId);
        var $detailContent = $('#detail-content-' + enrollId);
        var $btn = $(this);

        if ($detailRow.is(':visible')){
            $detailRow.hide();
            $btn.text('▶').closest('tr').removeClass('aura-is-expanded');
            return;
        }

        $detailRow.show();
        $btn.text('▼').closest('tr').addClass('aura-is-expanded');

        // Cargar solo si no fue cargado aún
        if ($detailContent.data('loaded')) return;

        $.post(ajaxUrl, {
            action        : 'aura_students_get_installments',
            nonce         : nonce,
            enrollment_id : enrollId
        }, function(res){
            if (!res.success){ $detailContent.html('<span style="color:#ef4444;">' + escHtml(res.data.message) + '</span>'); return; }
            $detailContent.html(buildInstallmentDetail(res.data, enrollId));
            $detailContent.data('loaded', true);
        });
    });

    function buildInstallmentDetail(data, enrollId){
        var installments   = data.installments   || [];
        var paymentHistory = data.payment_history || [];

        var html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap;">';

        // ── Columna 1: Schedule de cuotas ──
        html += '<div>';
        html += '<h4 style="margin:0 0 10px;color:#8b5cf6;">📅 <?php echo esc_js( __( 'Calendario de Pagos', 'aura-suite' ) ); ?></h4>';
        html += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        html += '<thead><tr style="background:#f3e8ff;">';
        html += '<th style="padding:4px 8px;text-align:left;">#</th>';
        html += '<th style="padding:4px 8px;text-align:left;"><?php echo esc_js( __( 'Vence', 'aura-suite' ) ); ?></th>';
        html += '<th style="padding:4px 8px;text-align:right;"><?php echo esc_js( __( 'Esperado', 'aura-suite' ) ); ?></th>';
        html += '<th style="padding:4px 8px;text-align:right;"><?php echo esc_js( __( 'Pagado', 'aura-suite' ) ); ?></th>';
        html += '<th style="padding:4px 8px;"><?php echo esc_js( __( 'Estado', 'aura-suite' ) ); ?></th>';
        html += '</tr></thead><tbody>';

        $.each(installments, function(i, inst){
            var uConf = urgencyConfig[inst.urgency] || urgencyConfig.ok;
            html += '<tr style="border-bottom:1px solid #f3e8ff;">';
            html += '<td style="padding:5px 8px;">' + escHtml(inst.installment_num) + '</td>';
            html += '<td style="padding:5px 8px;">' + escHtml(inst.due_date) + '</td>';
            html += '<td style="padding:5px 8px;text-align:right;">$' + parseFloat(inst.expected_amount).toFixed(2) + '</td>';
            html += '<td style="padding:5px 8px;text-align:right;">$' + parseFloat(inst.paid_amount || 0).toFixed(2) + '</td>';
            html += '<td style="padding:5px 8px;"><span style="color:' + uConf.color + ';">' + uConf.icon + ' ' + escHtml(inst.status) + '</span></td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';

        // ── Columna 2: Historial de pagos ──
        html += '<div>';
        html += '<h4 style="margin:0 0 10px;color:#8b5cf6;">💳 <?php echo esc_js( __( 'Pagos Registrados', 'aura-suite' ) ); ?></h4>';

        if (!paymentHistory.length){
            html += '<p style="color:#888;font-style:italic;"><?php echo esc_js( __( 'Sin pagos registrados.', 'aura-suite' ) ); ?></p>';
        } else {
            var methodLabels = { cash:'Efectivo', transfer:'Transferencia', card:'Tarjeta', check:'Cheque', other:'Otro' };
            html += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
            html += '<thead><tr style="background:#f3e8ff;"><th style="padding:4px 8px;"><?php echo esc_js( __( 'Fecha', 'aura-suite' ) ); ?></th><th style="padding:4px 8px;text-align:right;"><?php echo esc_js( __( 'Monto', 'aura-suite' ) ); ?></th><th style="padding:4px 8px;"><?php echo esc_js( __( 'Método', 'aura-suite' ) ); ?></th><th style="padding:4px 8px;">#</th></tr></thead><tbody>';
            $.each(paymentHistory, function(i, p){
                html += '<tr style="border-bottom:1px solid #f3e8ff;">';
                html += '<td style="padding:4px 8px;">' + escHtml(p.payment_date) + '</td>';
                html += '<td style="padding:4px 8px;text-align:right;color:#059669;font-weight:600;">$' + parseFloat(p.amount).toFixed(2) + '</td>';
                html += '<td style="padding:4px 8px;">' + escHtml(methodLabels[p.payment_method] || p.payment_method) + '</td>';
                html += '<td style="padding:4px 8px;font-size:11px;color:#888;">' + escHtml(p.reference_number || '—') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        }

        html += '</div>';
        html += '</div>';// end grid

        return html;
    }

    // ── Abrir modal Registrar Pago ────────────────────────────────
    $(document).on('click', '.btn-open-payment', function(){
        var btn = $(this);
        var enrollId = btn.data('id');
        $('#pay-enrollment-id').val(enrollId);
        $('#pay-modal-student').text(decodeURIComponent(btn.data('student')));
        $('#pay-modal-course').text(decodeURIComponent(btn.data('course')));
        $('#pay-modal-balance').text('$' + btn.data('balance'));
        $('#payment-notice').hide();
        $('#btn-save-payment').prop('disabled', false);
        $('#pay-finance-badge').toggle(btn.data('has-cat') === 1 || btn.data('has-cat') === '1');

        // Cargar cuotas pendientes
        $('#pay-row-installment').show();
        $('#pay-installment-select').html('<option><?php echo esc_js( __( 'Cargando…', 'aura-suite' ) ); ?></option>').prop('disabled', true);
        $('#pay-amount').val('');

        $.post(ajaxUrl, {
            action: 'aura_students_get_installments', nonce: nonce, enrollment_id: enrollId
        }, function(res){
            $('#pay-installment-select').prop('disabled', false);
            if (!res.success){ $('#pay-installment-select').html('<option value=""><?php echo esc_js( __( 'Error al cargar', 'aura-suite' ) ); ?></option>'); return; }
            var inst = res.data.installments || [];
            if (!inst.length || (inst[0].installment_num == 1 && inst.length == 1 && !res.data.enrollment.installment_count)){
                // Pago único — ocultar selector
                $('#pay-row-installment').hide();
                $('#pay-has-schedule').val('0');
                $('#pay-amount').val(parseFloat(res.data.enrollment.balance_due || 0).toFixed(2));
                return;
            }
            $('#pay-has-schedule').val('1');
            var opts = '<option value=""><?php echo esc_js( __( '— Seleccionar cuota —', 'aura-suite' ) ); ?></option>';
            $.each(inst, function(i, q){
                if (q.status === 'paid') return; // solo pendientes
                var uConf = urgencyConfig[q.urgency] || urgencyConfig.ok;
                var diff  = parseFloat(q.expected_amount) - parseFloat(q.paid_amount || 0);
                opts += '<option value="' + q.installment_num + '" data-amount="' + diff.toFixed(2) + '" data-urgency="' + escHtml(q.urgency) + '">';
                opts += '#' + q.installment_num + ' — ' + escHtml(q.due_date) + ' — $' + diff.toFixed(2) + ' (' + uConf.icon + ')';
                opts += '</option>';
            });
            $('#pay-installment-select').html(opts);
        });

        $('#modal-payment').show();
    });

    // Auto-fill monto al seleccionar cuota
    $('#pay-installment-select').on('change', function(){
        var sel = $(this).find(':selected');
        var amount = sel.data('amount');
        if (amount) $('#pay-amount').val(parseFloat(amount).toFixed(2));
        var urgency = sel.data('urgency') || '';
        var uConf = urgencyConfig[urgency] || {};
        $('#pay-installment-urgency').html(uConf.icon ? '<span style="color:' + uConf.color + ';">' + uConf.icon + '</span>' : '');
    });

    // Guardar pago
    $('#btn-save-payment').on('click', function(){
        var enrollId    = $('#pay-enrollment-id').val();
        var amount      = parseFloat($('#pay-amount').val());
        var payDate     = $('#pay-date').val();
        var hasSchedule = $('#pay-has-schedule').val() === '1';
        var instNum     = hasSchedule ? $('#pay-installment-select').val() : '';

        if (!enrollId || !amount || amount <= 0){ showNotice('#payment-notice', '<?php echo esc_js( __( 'Monto inválido.', 'aura-suite' ) ); ?>', 'error'); return; }
        if (!payDate){ showNotice('#payment-notice', '<?php echo esc_js( __( 'Fecha obligatoria.', 'aura-suite' ) ); ?>', 'error'); return; }
        if (hasSchedule && !instNum){ showNotice('#payment-notice', '<?php echo esc_js( __( 'Selecciona una cuota.', 'aura-suite' ) ); ?>', 'error'); return; }

        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Guardando…', 'aura-suite' ) ); ?>');

        $.post(ajaxUrl, {
            action           : 'aura_students_register_payment',
            nonce            : nonce,
            enrollment_id    : enrollId,
            amount           : amount,
            payment_date     : payDate,
            payment_method   : $('#pay-method').val(),
            reference_number : $('#pay-reference').val(),
            installment_num  : instNum || 0,
            notes            : $('#pay-notes').val()
        }, function(res){
            $btn.prop('disabled', false).text('💾 <?php echo esc_js( __( 'Guardar Pago', 'aura-suite' ) ); ?>');
            if (!res.success){ showNotice('#payment-notice', res.data.message, 'error'); return; }
            showNotice('#payment-notice', res.data.message, 'success');
            // Invalidar cache de filas expandidas
            $('[id^="detail-content-"]').removeData('loaded');
            setTimeout(function(){ $('#modal-payment').hide(); loadPayments(currPage); }, 1300);
        }).fail(function(){
            $btn.prop('disabled', false).text('💾 <?php echo esc_js( __( 'Guardar Pago', 'aura-suite' ) ); ?>');
            showNotice('#payment-notice', '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error');
        });
    });

    // ── Paginación ────────────────────────────────────────────────
    function renderPagination(selector, current, total, callback){
        if (total <= 1){ $(selector).html(''); return; }
        var html = '<div class="tablenav-pages">';
        html += '<span class="displaying-num"><?php echo esc_js( __( 'Página', 'aura-suite' ) ); ?> ' + current + ' <?php echo esc_js( __( 'de', 'aura-suite' ) ); ?> ' + total + '</span> ';
        if (current > 1) html += '<a class="button aura-page-btn" data-page="' + (current-1) + '">‹</a> ';
        html += '<strong>' + current + '</strong> ';
        if (current < total) html += '<a class="button aura-page-btn" data-page="' + (current+1) + '">›</a>';
        html += '</div>';
        $(selector).html(html);
        $(selector).find('.aura-page-btn').on('click', function(){ callback(parseInt($(this).data('page'))); });
    }

    // ── Utilidades ────────────────────────────────────────────────
    function showNotice(selector, msg, type){
        var color  = type === 'success' ? '#dcfce7' : '#fee2e2';
        var border = type === 'success' ? '#16a34a' : '#dc2626';
        $(selector).html('<p style="margin:0;padding:10px 14px;background:' + color + ';border-left:4px solid ' + border + ';border-radius:4px;">' + escHtml(msg) + '</p>').show();
    }

    function escHtml(text){
        if (text === undefined || text === null) return '';
        return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Inicializar ────────────────────────────────────────────────
    loadPayments(1);

})(jQuery);
</script>
