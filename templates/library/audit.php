<?php
/**
 * Template: Log de Auditoría — Biblioteca
 *
 * Tabla paginada con todos los eventos registrados.
 * Filtros: acción, tipo de entidad, usuario, rango de fechas.
 * Exportación a CSV.
 *
 * @package AuraBusinessSuite
 * @subpackage Library
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$nonce        = wp_create_nonce( 'aura_library_nonce' );
$action_labels = class_exists( 'Aura_Library_Audit' ) ? Aura_Library_Audit::get_action_labels() : [];
?>
<div class="wrap aura-lib-wrap">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-list-view" style="color:#8b5cf6;vertical-align:middle;"></span>
        <?php esc_html_e( 'Auditoría — Biblioteca', 'aura-business-suite' ); ?>
    </h1>

    <button id="btn-audit-export" class="page-title-action" style="background:#8b5cf6;color:#fff;border-color:#7c3aed;">
        ⬇ <?php esc_html_e( 'Exportar CSV', 'aura-business-suite' ); ?>
    </button>

    <hr class="wp-header-end">
    <div id="audit-notice" style="display:none;" class="notice"></div>

    <!-- ── Filtros ───────────────────────────────────────────── -->
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:16px 0;">

        <div>
            <label style="display:block;font-size:12px;margin-bottom:3px;"><?php esc_html_e( 'Acción', 'aura-business-suite' ); ?></label>
            <select id="audit-filter-action" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;">
                <option value=""><?php esc_html_e( '— Todas —', 'aura-business-suite' ); ?></option>
                <?php foreach ( $action_labels as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display:block;font-size:12px;margin-bottom:3px;"><?php esc_html_e( 'Entidad', 'aura-business-suite' ); ?></label>
            <select id="audit-filter-entity" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;">
                <option value=""><?php esc_html_e( '— Todas —', 'aura-business-suite' ); ?></option>
                <option value="book"><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?></option>
                <option value="loan"><?php esc_html_e( 'Préstamo', 'aura-business-suite' ); ?></option>
                <option value="reservation"><?php esc_html_e( 'Reserva', 'aura-business-suite' ); ?></option>
                <option value="settings"><?php esc_html_e( 'Configuración', 'aura-business-suite' ); ?></option>
            </select>
        </div>

        <div>
            <label style="display:block;font-size:12px;margin-bottom:3px;"><?php esc_html_e( 'Desde', 'aura-business-suite' ); ?></label>
            <input type="date" id="audit-date-from" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;">
        </div>

        <div>
            <label style="display:block;font-size:12px;margin-bottom:3px;"><?php esc_html_e( 'Hasta', 'aura-business-suite' ); ?></label>
            <input type="date" id="audit-date-to" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;">
        </div>

        <div>
            <label style="display:block;font-size:12px;margin-bottom:3px;"><?php esc_html_e( 'Buscar', 'aura-business-suite' ); ?></label>
            <input type="search" id="audit-search" placeholder="<?php esc_attr_e( 'Acción, entidad, usuario…', 'aura-business-suite' ); ?>"
                   style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;min-width:200px;">
        </div>

        <button id="btn-audit-filter" class="button button-primary" style="background:#8b5cf6;border-color:#7c3aed;">
            🔍 <?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?>
        </button>
        <button id="btn-audit-reset" class="button">
            ↺ <?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?>
        </button>
    </div>

    <!-- ── Tabla ─────────────────────────────────────────────── -->
    <div style="overflow-x:auto;">
        <table class="widefat striped aura-lib-table" style="font-size:0.9em;">
            <thead>
                <tr>
                    <th width="155"><?php esc_html_e( 'Fecha', 'aura-business-suite' ); ?></th>
                    <th width="150"><?php esc_html_e( 'Usuario', 'aura-business-suite' ); ?></th>
                    <th width="180"><?php esc_html_e( 'Acción', 'aura-business-suite' ); ?></th>
                    <th width="120"><?php esc_html_e( 'Entidad', 'aura-business-suite' ); ?></th>
                    <th width="70"><?php esc_html_e( 'ID', 'aura-business-suite' ); ?></th>
                    <th><?php esc_html_e( 'Detalle (cambios)', 'aura-business-suite' ); ?></th>
                    <th width="130"><?php esc_html_e( 'IP', 'aura-business-suite' ); ?></th>
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
    </div>

    <!-- ── Paginación ────────────────────────────────────────── -->
    <div id="audit-pagination" class="tablenav bottom" style="display:none;">
        <div class="tablenav-pages">
            <span id="audit-page-info" class="displaying-num" style="margin-right:8px;"></span>
            <span class="pagination-links">
                <button id="audit-prev" class="button" style="margin-right:4px;">‹</button>
                <button id="audit-next" class="button">›</button>
            </span>
        </div>
    </div>

</div><!-- .aura-lib-wrap -->

<style>
.aura-lib-table th { background: #f9f7ff; }
.aura-lib-table td { vertical-align: top; }
.audit-diff { font-size: 11px; background: #f9f9f9; border-radius: 4px; padding: 4px 8px; max-width: 300px; }
.audit-diff .diff-key { color: #6b7280; font-weight: 600; }
.audit-diff .diff-old { color: #ef4444; }
.audit-diff .diff-new { color: #059669; }
.audit-action-badge {
    display: inline-block;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
}
</style>

<script>
(function($){
    'use strict';

    var nonce   = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var currPage = 1;

    var actionColors = {
        create_book:        { bg:'#dcfce7', color:'#166534' },
        update_book:        { bg:'#dbeafe', color:'#1e40af' },
        delete_book:        { bg:'#fee2e2', color:'#991b1b' },
        create_loan:        { bg:'#ede9fe', color:'#5b21b6' },
        return_book:        { bg:'#d1fae5', color:'#065f46' },
        extend_loan:        { bg:'#fef9c3', color:'#854d0e' },
        create_reservation: { bg:'#e0f2fe', color:'#0c4a6e' },
        cancel_reservation: { bg:'#fce7f3', color:'#9d174d' },
        register_fine:      { bg:'#fff7ed', color:'#c2410c' },
        update_settings:    { bg:'#f3f4f6', color:'#374151' }
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
            search        : $('#audit-search').val()
        }, function(res){
            if (!res.success){ $tbody.html('<tr><td colspan="7">' + escHtml(res.data.message||'Error') + '</td></tr>'); return; }
            renderTable(res.data.rows);
            renderPagination(res.data.page, res.data.total_pages, res.data.total);
        }).fail(function(){
            $tbody.html('<tr><td colspan="7"><?php echo esc_js( __( 'Error de conexión.', 'aura-business-suite' ) ); ?></td></tr>');
        });
    }

    function renderTable(rows){
        if (!rows || !rows.length){
            $('#audit-tbody').html('<tr><td colspan="7" style="text-align:center;padding:24px;"><?php echo esc_js( __( 'No hay registros con los filtros aplicados.', 'aura-business-suite' ) ); ?></td></tr>');
            return;
        }
        var html = '';
        $.each(rows, function(i, r){
            var ac = actionColors[r.action] || { bg:'#f3f4f6', color:'#374151' };
            var diffHtml = buildDiff(r.old_data, r.new_data);

            html += '<tr>';
            html += '<td style="white-space:nowrap;color:#555;font-size:0.85em;">' + escHtml(r.created_at) + '</td>';
            html += '<td>' + escHtml(r.user_name) + '</td>';
            html += '<td><span class="audit-action-badge" style="background:' + ac.bg + ';color:' + ac.color + ';">' + escHtml(r.action_label) + '</span></td>';
            html += '<td><code style="font-size:0.85em;">' + escHtml(r.entity_type) + '</code></td>';
            html += '<td style="text-align:center;">' + r.entity_id + '</td>';
            html += '<td>' + diffHtml + '</td>';
            html += '<td style="font-size:0.82em;color:#888;">' + escHtml(r.ip_address||'—') + '</td>';
            html += '</tr>';
        });
        $('#audit-tbody').html(html);
    }

    function buildDiff(oldData, newData){
        if (!newData && !oldData) return '<span style="color:#aaa;">—</span>';
        if (newData && !oldData) {
            var keys = Object.keys(newData);
            if (keys.length === 0) return '—';
            var out = '<div class="audit-diff">';
            keys.slice(0,5).forEach(function(k){
                out += '<div><span class="diff-key">' + escHtml(k) + ':</span> <span class="diff-new">' + escHtml(String(newData[k])) + '</span></div>';
            });
            if (keys.length > 5) out += '<div style="color:#aaa;">…+' + (keys.length-5) + ' más</div>';
            return out + '</div>';
        }
        if (oldData && newData) {
            var allKeys = Object.keys(Object.assign({}, oldData, newData));
            var changed = allKeys.filter(function(k){ return String(oldData[k]||'') !== String(newData[k]||''); });
            if (!changed.length) return '<span style="color:#aaa;">Sin cambios</span>';
            var out = '<div class="audit-diff">';
            changed.slice(0,5).forEach(function(k){
                out += '<div><span class="diff-key">' + escHtml(k) + ':</span> ';
                out += '<span class="diff-old">' + escHtml(String(oldData[k]||'—')) + '</span> → ';
                out += '<span class="diff-new">' + escHtml(String(newData[k]||'—')) + '</span></div>';
            });
            if (changed.length > 5) out += '<div style="color:#aaa;">…+' + (changed.length-5) + ' más</div>';
            return out + '</div>';
        }
        return '—';
    }

    function renderPagination(page, totalPages, total){
        if (totalPages <= 1){ $('#audit-pagination').hide(); return; }
        $('#audit-page-info').text(
            '<?php echo esc_js( __( 'Pág.', 'aura-business-suite' ) ); ?> ' + page + ' / ' + totalPages +
            ' — ' + total + ' <?php echo esc_js( __( 'registros', 'aura-business-suite' ) ); ?>'
        );
        $('#audit-prev').prop('disabled', page <= 1);
        $('#audit-next').prop('disabled', page >= totalPages);
        $('#audit-pagination').show();
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

    // ── Exportar CSV ─────────────────────────────────────────────
    $('#btn-audit-export').on('click', function(){
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
