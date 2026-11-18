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

$nonce         = wp_create_nonce( 'aura_library_nonce' );
$action_labels = class_exists( 'Aura_Library_Audit' ) ? Aura_Library_Audit::get_action_labels() : [];
?>
<div class="aura-app-wrapper aura-library-audit">
    <div class="wrap aura-app-context">

    <?php
    Aura_UI::render_page_header( [
        'title'    => __( 'Auditoría de Biblioteca', 'aura-business-suite' ),
        'subtitle' => __( 'Historial y trazabilidad de eventos, préstamos, libros y configuraciones', 'aura-business-suite' ),
        'icon'     => 'dashicons-list-view',
        'badges'   => [
            [ 'label' => __( 'Trazabilidad Completa', 'aura-suite' ), 'variant' => 'indigo' ],
        ],
        'actions'  => [
            [
                'label' => __( 'Exportar CSV', 'aura-business-suite' ),
                'url'   => '#',
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-download',
                'attr'  => 'id="btn-audit-export" role="button"',
            ],
        ],
    ] );
    ?>

    <div id="audit-notice" style="display:none;" class="notice"></div>

    <!-- ── Filtros (Bootstrap 5.3 Input Groups) ──────────────── -->
    <div class="aura-glass-card aura-filters-card aura-lib-audit-filters">
        <div class="aura-lib-audit-filters-grid">

            <div class="aura-lib-audit-filter-col">
                <label for="audit-filter-action"><?php esc_html_e( 'Acción', 'aura-business-suite' ); ?></label>
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
                <label for="audit-filter-entity"><?php esc_html_e( 'Entidad', 'aura-business-suite' ); ?></label>
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
                <label for="audit-date-from"><?php esc_html_e( 'Desde', 'aura-business-suite' ); ?></label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="audit-date-from" class="form-control">
                </div>
            </div>

            <div class="aura-lib-audit-filter-date">
                <label for="audit-date-to"><?php esc_html_e( 'Hasta', 'aura-business-suite' ); ?></label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="audit-date-to" class="form-control">
                </div>
            </div>

            <div class="aura-lib-audit-filter-search">
                <label for="audit-search"><?php esc_html_e( 'Buscar', 'aura-business-suite' ); ?></label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">🔍</span>
                    <input type="search" id="audit-search" placeholder="<?php esc_attr_e( 'Acción, entidad, usuario…', 'aura-business-suite' ); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="aura-lib-audit-filter-actions">
                <button type="button" id="btn-audit-filter" class="btn btn-shimmer btn-indigo btn-lift">
                    <span class="dashicons dashicons-filter"></span>
                    <span><?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?></span>
                </button>
                <button type="button" id="btn-audit-reset" class="btn btn-glass btn-lift">
                    <span><?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── Tabla ─────────────────────────────────────────────── -->
    <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
        <table class="aura-table display responsive nowrap aura-lib-audit-table" style="width:100%;">
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

        <!-- ── Paginación ────────────────────────────────────────── -->
        <div id="audit-pagination" class="aura-lib-audit-pagination" style="display:none;">
            <span id="audit-page-info" class="aura-lib-audit-page-info"></span>
            <div class="aura-lib-page-btns">
                <button type="button" id="audit-prev" class="btn btn-glass btn-sm btn-lift">‹</button>
                <button type="button" id="audit-next" class="btn btn-glass btn-sm btn-lift">›</button>
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

<style>
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
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--aura-text-muted, #64748b);
}
.aura-lib-audit-filter-col  { flex: 0 1 195px; min-width: 170px; }
.aura-lib-audit-filter-date { flex: 0 1 180px; min-width: 160px; }
.aura-lib-audit-filter-search { flex: 1 1 230px; min-width: 200px; }
.aura-lib-audit-filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.aura-lib-audit-table td { vertical-align: top; font-size: 13px; }
.aura-lib-audit-meta { white-space: nowrap; color: var(--aura-text-muted, #64748b); font-size: 12px; }
.aura-lib-audit-muted { color: var(--aura-text-muted, #94a3b8); font-size: 12px; }
.audit-diff {
    font-size: 11.5px;
    background: var(--aura-surface-alt, #f8fafc);
    border: 1px solid var(--aura-border, rgba(148, 163, 184, 0.25));
    border-radius: 6px;
    padding: 6px 10px;
    max-width: 340px;
}
.audit-diff .diff-key { color: var(--aura-text-muted, #64748b); font-weight: 600; }
.audit-diff .diff-old { color: var(--aura-red, #ef4444); }
.audit-diff .diff-new { color: var(--aura-emerald, #10b981); }
.aura-lib-audit-pagination {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
}
.aura-lib-audit-page-info {
    font-size: 13px;
    color: var(--aura-text-muted, #64748b);
}
.aura-lib-page-btns { display: flex; gap: 6px; }
</style>

<script>
(function($){
    'use strict';

    var nonce   = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var currPage = 1;

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
            var badgeCls = actionBadgeClasses[r.action] || 'badge-slate';
            var diffHtml = buildDiff(r.old_data, r.new_data);

            html += '<tr>';
            html += '<td class="aura-lib-audit-meta">' + escHtml(r.created_at) + '</td>';
            html += '<td>' + escHtml(r.user_name) + '</td>';
            html += '<td><span class="badge ' + badgeCls + '">' + escHtml(r.action_label) + '</span></td>';
            html += '<td><code>' + escHtml(r.entity_type) + '</code></td>';
            html += '<td style="text-align:center;">' + r.entity_id + '</td>';
            html += '<td>' + diffHtml + '</td>';
            html += '<td class="aura-lib-audit-muted">' + escHtml(r.ip_address||'—') + '</td>';
            html += '</tr>';
        });
        $('#audit-tbody').html(html);
    }

    function buildDiff(oldData, newData){
        if (!newData && !oldData) return '<span class="aura-lib-audit-muted">—</span>';
        if (newData && !oldData) {
            var keys = Object.keys(newData);
            if (keys.length === 0) return '—';
            var out = '<div class="audit-diff">';
            keys.slice(0,5).forEach(function(k){
                out += '<div><span class="diff-key">' + escHtml(k) + ':</span> <span class="diff-new">' + escHtml(String(newData[k])) + '</span></div>';
            });
            if (keys.length > 5) out += '<div class="aura-lib-audit-muted">…+' + (keys.length-5) + ' más</div>';
            return out + '</div>';
        }
        if (oldData && newData) {
            var allKeys = Object.keys(Object.assign({}, oldData, newData));
            var changed = allKeys.filter(function(k){ return String(oldData[k]||'') !== String(newData[k]||''); });
            if (!changed.length) return '<span class="aura-lib-audit-muted">Sin cambios</span>';
            var out = '<div class="audit-diff">';
            changed.slice(0,5).forEach(function(k){
                out += '<div><span class="diff-key">' + escHtml(k) + ':</span> ';
                out += '<span class="diff-old">' + escHtml(String(oldData[k]||'—')) + '</span> → ';
                out += '<span class="diff-new">' + escHtml(String(newData[k]||'—')) + '</span></div>';
            });
            if (changed.length > 5) out += '<div class="aura-lib-audit-muted">…+' + (changed.length-5) + ' más</div>';
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
