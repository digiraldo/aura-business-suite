<?php
/**
 * Template: Paz y Salvo — Fase 6
 *
 * Listado de estudiantes activos con estado de deuda.
 * Filtros: solo morosos / solo al día.
 * Acciones: enviar recordatorio (email o WhatsApp), exportar morosos CSV.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Cargar cursos para el filtro
$tc      = $wpdb->prefix . 'aura_student_courses';
$courses = $wpdb->get_results( "SELECT id, name FROM `{$tc}` WHERE status = 'active' ORDER BY name ASC" );

$nonce         = wp_create_nonce( 'aura_students_nonce' );
$can_remind    = current_user_can( 'aura_students_status_view' ) || current_user_can( 'manage_options' );
$can_whatsapp  = class_exists( 'Aura_Notifications' );
?>
<div class="wrap aura-paz-salvo-wrap">

    <h1 class="wp-heading-inline" style="color:#8b5cf6;">
        <span class="dashicons dashicons-shield-alt" style="vertical-align:middle;"></span>
        <?php esc_html_e( 'Paz y Salvo', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <p style="color:#555;margin-bottom:16px;">
        <?php esc_html_e( 'Estado financiero de todos los estudiantes activos. Aquí puedes identificar morosos, enviar recordatorios y exportar el listado.', 'aura-suite' ); ?>
    </p>

    <!-- ── KPI minibar ── -->
    <div id="paz-kpi-bar" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;"></div>

    <!-- ── Filtros y acciones ── -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">

        <input type="search" id="paz-search"
               placeholder="<?php esc_attr_e( 'Buscar estudiante…', 'aura-suite' ); ?>"
               style="min-width:200px;padding:6px 10px;border:1px solid #ccc;border-radius:4px;">

        <select id="paz-course" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <option value=""><?php esc_html_e( '— Todos los cursos —', 'aura-suite' ); ?></option>
            <?php foreach ( $courses as $c ) : ?>
                <option value="<?php echo esc_attr( $c->id ); ?>"><?php echo esc_html( $c->name ); ?></option>
            <?php endforeach; ?>
        </select>

        <div style="display:flex;gap:6px;">
            <button id="btn-filter-all" class="button button-primary paz-filter-btn" data-filter=""
                    style="background:#8b5cf6;border-color:#7c3aed;">
                <?php esc_html_e( 'Todos', 'aura-suite' ); ?>
            </button>
            <button id="btn-filter-debtors" class="button paz-filter-btn" data-filter="debtors">
                🔴 <?php esc_html_e( 'Solo morosos', 'aura-suite' ); ?>
            </button>
            <button id="btn-filter-current" class="button paz-filter-btn" data-filter="current">
                ✅ <?php esc_html_e( 'Solo al día', 'aura-suite' ); ?>
            </button>
        </div>

        <button id="btn-export-debtors" class="button" title="<?php esc_attr_e( 'Exportar morosos a CSV', 'aura-suite' ); ?>">
            📥 <?php esc_html_e( 'Exportar morosos', 'aura-suite' ); ?>
        </button>

    </div>

    <!-- ── Tabla principal ── -->
    <table class="wp-list-table widefat fixed striped" id="paz-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                <th width="105"><?php esc_html_e( 'Costo Neto', 'aura-suite' ); ?></th>
                <th width="105"><?php esc_html_e( 'Pagado', 'aura-suite' ); ?></th>
                <th width="105"><?php esc_html_e( 'Saldo', 'aura-suite' ); ?></th>
                <th width="95"><?php esc_html_e( 'Cuotas Venc.', 'aura-suite' ); ?></th>
                <th width="110"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <?php if ( $can_remind ) : ?>
                <th width="190"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="paz-tbody">
            <tr>
                <td colspan="<?php echo $can_remind ? 8 : 7; ?>" style="text-align:center;padding:20px;">
                    <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                    <?php esc_html_e( 'Cargando…', 'aura-suite' ); ?>
                </td>
            </tr>
        </tbody>
    </table>
    <div id="paz-pagination" class="tablenav bottom" style="margin-top:8px;"></div>

</div><!-- .wrap -->

<!-- ══════════════════════════════════════════════════════════════
     MODAL: CONFIRMAR RECORDATORIO
════════════════════════════════════════════════════════════════ -->
<div id="modal-reminder" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-modal-box" style="max-width:440px;width:95%;">
        <div class="aura-modal-header" style="background:#8b5cf6;">
            <h2>📢 <?php esc_html_e( 'Enviar Recordatorio', 'aura-suite' ); ?></h2>
            <button class="aura-modal-close" data-modal="modal-reminder" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" style="padding:24px;">
            <input type="hidden" id="reminder-enrollment-id" value="">
            <p id="reminder-student-info" style="margin:0 0 16px;font-size:14px;"></p>

            <label style="display:block;margin-bottom:14px;font-size:13px;">
                <strong><?php esc_html_e( 'Canal de envío:', 'aura-suite' ); ?></strong><br>
                <label style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;margin-right:16px;">
                    <input type="radio" name="reminder-channel" value="email" checked>
                    📧 <?php esc_html_e( 'Email', 'aura-suite' ); ?>
                </label>
                <?php if ( $can_whatsapp ) : ?>
                <label style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;">
                    <input type="radio" name="reminder-channel" value="whatsapp">
                    💬 <?php esc_html_e( 'WhatsApp', 'aura-suite' ); ?>
                </label>
                <?php endif; ?>
            </label>

            <div id="reminder-notice" style="display:none;margin-bottom:12px;"></div>

            <button id="btn-send-reminder" class="button button-primary"
                    style="background:#8b5cf6;border-color:#7c3aed;">
                📤 <?php esc_html_e( 'Enviar', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->

<style>
.aura-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;}
.aura-modal-box{background:#fff;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.22);max-height:90vh;overflow-y:auto;}
.aura-modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-radius:8px 8px 0 0;}
.aura-modal-header h2{margin:0;color:#fff;font-size:18px;}
.aura-modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0 4px;line-height:1;}
.paz-filter-btn.active{background:#7c3aed;color:#fff;border-color:#6d28d9;}
.aura-peace-badge{display:inline-block;border-radius:4px;padding:3px 9px;font-size:12px;font-weight:600;}
.paz-kpi{background:#fff;border:1px solid #e9d5ff;border-radius:8px;padding:12px 18px;flex:1;min-width:140px;}
.paz-kpi .kpi-label{color:#555;font-size:12px;margin:0 0 4px;}
.paz-kpi .kpi-value{font-size:22px;font-weight:700;margin:0;}
</style>

<script>
(function($){
    'use strict';

    var nonce      = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl    = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var currPage   = 1;
    var currFilter = '';
    var canRemind  = <?php echo $can_remind ? 'true' : 'false'; ?>;
    var colCount   = canRemind ? 8 : 7;

    var statusConf = {
        paid:    { label:'✅ Al día',   bg:'#dcfce7', color:'#166534' },
        partial: { label:'🟡 Parcial',  bg:'#fef9c3', color:'#a16207' },
        unpaid:  { label:'🔴 Sin pago', bg:'#fee2e2', color:'#991b1b' },
        overdue: { label:'🚨 Vencido',  bg:'#fce7f3', color:'#9d174d' }
    };

    // ── Modales ──────────────────────────────────────────────────
    $(document).on('click', '.aura-modal-close', function(){ $('#' + $(this).data('modal')).hide(); });
    $(document).on('keydown', function(e){ if (e.key === 'Escape') $('.aura-modal-overlay:visible').hide(); });
    $(document).on('click', '.aura-modal-overlay', function(e){ if ($(e.target).hasClass('aura-modal-overlay')) $(this).hide(); });

    // ── Filtros ───────────────────────────────────────────────────
    var searchTimer;
    $('#paz-search').on('input', function(){ clearTimeout(searchTimer); searchTimer = setTimeout(function(){ loadPaz(1); }, 350); });
    $('#paz-course').on('change', function(){ loadPaz(1); });

    $('.paz-filter-btn').on('click', function(){
        currFilter = $(this).data('filter');
        $('.paz-filter-btn').removeClass('active');
        $(this).addClass('active');
        loadPaz(1);
    });
    $('#btn-filter-all').addClass('active');

    // ── Cargar tabla ─────────────────────────────────────────────
    function loadPaz(page){
        currPage = page || 1;
        var $tbody = $('#paz-tbody');
        $tbody.html('<tr><td colspan="' + colCount + '" style="text-align:center;padding:20px;"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span><?php echo esc_js( __( 'Cargando…', 'aura-suite' ) ); ?></td></tr>');

        $.post(ajaxUrl, {
            action    : 'aura_students_paz_salvo_list',
            nonce     : nonce,
            filter    : currFilter,
            search    : $('#paz-search').val(),
            course_id : $('#paz-course').val() || 0,
            page      : currPage
        }, function(res){
            if (!res.success){ $tbody.html('<tr><td colspan="' + colCount + '">' + escHtml(res.data.message) + '</td></tr>'); return; }
            renderKpis(res.data.rows);
            renderTable(res.data.rows);
            renderPagination('#paz-pagination', res.data.page, res.data.total_pages, loadPaz);
        }).fail(function(){
            $tbody.html('<tr><td colspan="' + colCount + '"><?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?></td></tr>');
        });
    }

    function renderKpis(rows){
        var total = rows.length, debtors = 0, totalBalance = 0, overdueCuotas = 0;
        $.each(rows, function(i, r){
            if (parseFloat(r.balance_due) > 0) debtors++;
            totalBalance  += parseFloat(r.balance_due  || 0);
            overdueCuotas += parseInt(r.overdue_count  || 0);
        });
        var html = '';
        html += '<div class="paz-kpi"><p class="kpi-label"><?php echo esc_js( __( 'Registros mostrados', 'aura-suite' ) ); ?></p><p class="kpi-value">' + total + '</p></div>';
        html += '<div class="paz-kpi" style="' + (debtors > 0 ? 'border-color:#fca5a5;' : '') + '"><p class="kpi-label"><?php echo esc_js( __( 'Con saldo pendiente', 'aura-suite' ) ); ?></p><p class="kpi-value" style="color:' + (debtors > 0 ? '#ef4444' : '#059669') + ';">' + debtors + '</p></div>';
        html += '<div class="paz-kpi"><p class="kpi-label"><?php echo esc_js( __( 'Saldo total pendiente', 'aura-suite' ) ); ?></p><p class="kpi-value" style="color:#d97706;">$' + totalBalance.toFixed(2) + '</p></div>';
        html += '<div class="paz-kpi" style="' + (overdueCuotas > 0 ? 'border-color:#fca5a5;' : '') + '"><p class="kpi-label"><?php echo esc_js( __( 'Cuotas vencidas', 'aura-suite' ) ); ?></p><p class="kpi-value" style="color:' + (overdueCuotas > 0 ? '#ef4444' : '#059669') + ';">' + overdueCuotas + '</p></div>';
        $('#paz-kpi-bar').html(html);
    }

    function renderTable(rows){
        if (!rows || !rows.length){
            $('#paz-tbody').html('<tr><td colspan="' + colCount + '" style="text-align:center;padding:20px;"><?php echo esc_js( __( 'No hay registros con los filtros aplicados.', 'aura-suite' ) ); ?></td></tr>');
            return;
        }
        var html = '';
        $.each(rows, function(i, r){
            var fullName  = escHtml(((r.first_name||'') + ' ' + (r.last_name||'')).trim());
            var balance   = parseFloat(r.balance_due   || 0);
            var netCost   = parseFloat(r.net_cost       || 0);
            var paid      = parseFloat(r.total_paid     || 0);
            var overdue   = parseInt(r.overdue_count    || 0);
            var rowStatus = r.payment_status;

            // Si hay cuotas vencidas, forzar 'overdue' para el badge
            if (overdue > 0 && rowStatus !== 'paid') rowStatus = 'overdue';

            var sConf = statusConf[rowStatus] || { label: rowStatus, bg:'#f3f4f6', color:'#374151' };
            var rowBg = overdue > 0 ? '#fff8f8' : '';

            html += '<tr style="' + (rowBg ? 'background:' + rowBg + ';' : '') + '">';
            html += '<td><strong>' + fullName + '</strong><br><small style="color:#888;">' + escHtml(r.email || '') + '</small></td>';
            html += '<td>' + escHtml(r.course_name || '—') + '</td>';
            html += '<td style="text-align:right;">$' + netCost.toFixed(2) + '</td>';
            html += '<td style="text-align:right;color:#059669;">$' + paid.toFixed(2) + '</td>';
            html += '<td style="text-align:right;color:' + (balance > 0 ? '#ef4444' : '#059669') + ';font-weight:' + (balance > 0 ? '700' : '400') + ';">$' + balance.toFixed(2) + '</td>';
            html += '<td style="text-align:center;">';
            if (overdue > 0) {
                html += '<span style="color:#dc2626;font-weight:700;">🔴 ' + overdue + '</span>';
                if (r.oldest_overdue_date) html += '<br><small style="color:#888;font-size:11px;"><?php echo esc_js( __( 'Desde:', 'aura-suite' ) ); ?> ' + escHtml(r.oldest_overdue_date) + '</small>';
            } else {
                html += '<span style="color:#16a34a;">✅ 0</span>';
            }
            html += '</td>';
            html += '<td><span class="aura-peace-badge" style="background:' + sConf.bg + ';color:' + sConf.color + ';">' + sConf.label + '</span>';
            // F7.5 — badges de biblioteca
            if (r.lib_overdue) { html += '<br><span class="aura-peace-badge" style="background:#fef2f2;color:#dc2626;margin-top:3px;" title="<?php echo esc_attr__( 'Tiene préstamos vencidos en Biblioteca', 'aura-suite' ); ?>">📚 <?php echo esc_js( __( 'Préstamo vencido', 'aura-suite' ) ); ?></span>'; }
            if (r.lib_fines)   { html += '<br><span class="aura-peace-badge" style="background:#fffbeb;color:#d97706;margin-top:3px;" title="<?php echo esc_attr__( 'Tiene multas pendientes en Biblioteca', 'aura-suite' ); ?>">💸 <?php echo esc_js( __( 'Multa pendiente', 'aura-suite' ) ); ?></span>'; }
            html += '</td>';

            if (canRemind) {
                html += '<td>';
                if (balance > 0 || overdue > 0) {
                    html += '<button class="button button-small btn-open-reminder"'
                          + ' data-enrollment-id="' + r.enrollment_id + '"'
                          + ' data-student="' + encodeURIComponent(fullName) + '"'
                          + ' data-balance="' + balance.toFixed(2) + '"'
                          + ' style="font-size:11px;">📢 <?php echo esc_js( __( 'Recordatorio', 'aura-suite' ) ); ?></button>';
                } else {
                    html += '<span style="color:#16a34a;font-size:12px;">✅ <?php echo esc_js( __( 'Al día', 'aura-suite' ) ); ?></span>';
                }
                html += '</td>';
            }
            html += '</tr>';
        });
        $('#paz-tbody').html(html);
    }

    // ── Abrir modal recordatorio ──────────────────────────────────
    $(document).on('click', '.btn-open-reminder', function(){
        var btn = $(this);
        $('#reminder-enrollment-id').val(btn.data('enrollment-id'));
        var studentName = decodeURIComponent(btn.data('student'));
        var balance     = btn.data('balance');
        $('#reminder-student-info').html(
            '<strong>' + escHtml(studentName) + '</strong>' +
            ' — <?php echo esc_js( __( 'Saldo', 'aura-suite' ) ); ?>: <strong style="color:#ef4444;">$' + balance + '</strong>'
        );
        $('#reminder-notice').hide();
        $('#btn-send-reminder').prop('disabled', false).text('📤 <?php echo esc_js( __( 'Enviar', 'aura-suite' ) ); ?>');
        $('input[name="reminder-channel"][value="email"]').prop('checked', true);
        $('#modal-reminder').show();
    });

    // ── Enviar recordatorio ───────────────────────────────────────
    $('#btn-send-reminder').on('click', function(){
        var $btn    = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Enviando…', 'aura-suite' ) ); ?>');
        var channel = $('input[name="reminder-channel"]:checked').val();

        $.post(ajaxUrl, {
            action        : 'aura_students_send_reminder',
            nonce         : nonce,
            enrollment_id : $('#reminder-enrollment-id').val(),
            channel       : channel
        }, function(res){
            $btn.prop('disabled', false).text('📤 <?php echo esc_js( __( 'Enviar', 'aura-suite' ) ); ?>');
            showNotice('#reminder-notice', res.data.message, res.success ? 'success' : 'error');
            if (res.success) { setTimeout(function(){ $('#modal-reminder').hide(); }, 1500); }
        }).fail(function(){
            $btn.prop('disabled', false).text('📤 <?php echo esc_js( __( 'Enviar', 'aura-suite' ) ); ?>');
            showNotice('#reminder-notice', '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error');
        });
    });

    // ── Exportar morosos ─────────────────────────────────────────
    $('#btn-export-debtors').on('click', function(){
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Exportando…', 'aura-suite' ) ); ?>');

        $.post(ajaxUrl, { action: 'aura_students_export_debtors', nonce: nonce }, function(res){
            $btn.prop('disabled', false).text('📥 <?php echo esc_js( __( 'Exportar morosos', 'aura-suite' ) ); ?>');
            if (!res.success) { alert(res.data.message); return; }

            // Descargar CSV
            var blob = new Blob(['\ufeff' + res.data.csv], { type: 'text/csv;charset=utf-8;' });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href   = url;
            a.download = res.data.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }).fail(function(){
            $btn.prop('disabled', false).text('📥 <?php echo esc_js( __( 'Exportar morosos', 'aura-suite' ) ); ?>');
            alert('<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>');
        });
    });

    // ── Paginación ────────────────────────────────────────────────
    function renderPagination(selector, current, total, callback){
        if (total <= 1){ $(selector).html(''); return; }
        var html = '<div class="tablenav-pages">';
        html += '<span class="displaying-num"><?php echo esc_js( __( 'Página', 'aura-suite' ) ); ?> ' + current + ' <?php echo esc_js( __( 'de', 'aura-suite' ) ); ?> ' + total + '</span> ';
        if (current > 1) html += '<a class="button aura-paz-page" data-page="' + (current-1) + '">‹</a> ';
        html += '<strong>' + current + '</strong> ';
        if (current < total) html += '<a class="button aura-paz-page" data-page="' + (current+1) + '">›</a>';
        html += '</div>';
        $(selector).html(html);
        $(selector).find('.aura-paz-page').on('click', function(){ callback(parseInt($(this).data('page'))); });
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
    loadPaz(1);

})(jQuery);
</script>
