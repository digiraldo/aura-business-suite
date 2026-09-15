/**
 * Gastos de Capital (CapEx) — Frontend JS
 * Página: aura-capital-expenses
 * @since 1.1.0
 */
(function ($) {
    'use strict';

    var nonce   = auraCapital.nonce;
    var ajaxUrl = auraCapital.ajaxUrl;
    var currentPage = 1;
    var totalPages  = 1;
    var statsData   = {};

    /* ── Utils ── */
    function fmt(n) {
        return '$' + parseFloat(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }
    function getFilters() {
        return {
            year:        $('#cap-filter-year').val(),
            month:       $('#cap-filter-month').val(),
            category_id: $('#cap-filter-category').val(),
            status:      $('#cap-filter-status').val(),
            search:      $('#cap-filter-search').val().trim(),
            page:        currentPage,
        };
    }

    /* ── Cargar categorías ── */
    function loadCategories() {
        $.post(ajaxUrl, { action: 'aura_get_capital_categories', nonce: nonce }, function (resp) {
            if (!resp.success) return;
            var $sel = $('#cap-filter-category');
            resp.data.categories.forEach(function (c) {
                $sel.append('<option value="' + c.id + '">' + escHtml(c.name) + '</option>');
            });
        });
    }

    /* ── Cargar estadísticas ── */
    function loadStats() {
        var year = $('#cap-filter-year').val();
        $.post(ajaxUrl, { action: 'aura_get_capital_stats', nonce: nonce, year: year }, function (resp) {
            if (!resp.success) return;
            var d = resp.data;
            statsData = d;

            $('#stat-total-year').text(fmt(d.total_year));
            $('#stat-pending').text(d.pending);
            $('#stat-year-label').text(d.year);

            if (d.by_category && d.by_category.length > 0) {
                $('#stat-top-cat').text(d.by_category[0].name + ' — ' + fmt(d.by_category[0].total));
            } else {
                $('#stat-top-cat').text('—');
            }

            renderChart(d.by_month);
            renderByCat(d.by_category);
        });
    }

    /* ── Gráfico de barras mensual ── */
    var MONTH_NAMES = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    function renderChart(byMonth) {
        var $chart  = $('#aura-capital-chart');
        var $labels = $('#aura-capital-chart-labels');
        $chart.empty();
        $labels.empty();

        // Construir mapa mes->total
        var map = {};
        (byMonth || []).forEach(function (r) { map[parseInt(r.month)] = parseFloat(r.total); });

        var max = 0;
        for (var m = 1; m <= 12; m++) { if ((map[m] || 0) > max) max = map[m] || 0; }
        if (max === 0) {
            $chart.html('<span style="color:#bbb;align-self:center;width:100%;text-align:center;">Sin datos</span>');
            return;
        }

        $chart.css('display', 'flex');
        for (var m = 1; m <= 12; m++) {
            var val    = map[m] || 0;
            var pct    = max > 0 ? (val / max * 100) : 0;
            var color  = val > 0 ? '#e67e22' : '#f0e8da';
            var $bar   = $('<div>').css({
                flex: 1,
                background: color,
                height: Math.max(pct, 2) + '%',
                borderRadius: '4px 4px 0 0',
                alignSelf: 'flex-end',
                cursor: val > 0 ? 'pointer' : 'default',
                transition: 'height .3s ease',
                position: 'relative',
            });
            if (val > 0) {
                $bar.attr('title', MONTH_NAMES[m] + ': ' + fmt(val));
            }
            $chart.append($bar);

            var $lbl = $('<div>').css({ flex: 1, textAlign: 'center', fontSize: '10px', color: '#999' }).text(MONTH_NAMES[m]);
            $labels.append($lbl);
        }
    }

    /* ── Desglose por categoría ── */
    function renderByCat(byCat) {
        var $el = $('#aura-capital-by-cat');
        if (!byCat || !byCat.length) {
            $el.html('<span style="color:#bbb;font-size:13px;">Sin datos para este año.</span>');
            return;
        }
        var maxVal = parseFloat(byCat[0].total);
        var html = '<table style="width:100%;border-collapse:collapse;">';
        byCat.forEach(function (c) {
            var pct = maxVal > 0 ? (parseFloat(c.total) / maxVal * 100) : 0;
            html +=
                '<tr style="border-bottom:1px solid #f5f5f5;">' +
                '<td style="padding:10px 8px;width:36px;"><span class="dashicons ' + escHtml(c.icon) + '" style="color:' + escHtml(c.color) + ';font-size:18px;vertical-align:middle;"></span></td>' +
                '<td style="padding:10px 8px;">' +
                    '<div style="font-weight:600;font-size:13px;">' + escHtml(c.name) + '</div>' +
                    '<div style="height:6px;background:#f0e8da;border-radius:3px;margin-top:4px;">' +
                        '<div style="height:100%;width:' + pct + '%;background:' + escHtml(c.color) + ';border-radius:3px;transition:width .4s;"></div>' +
                    '</div>' +
                '</td>' +
                '<td style="padding:10px 8px;text-align:right;white-space:nowrap;">' +
                    '<strong style="color:#e67e22;">' + fmt(c.total) + '</strong><br>' +
                    '<small style="color:#aaa;">' + c.count + ' tx</small>' +
                '</td>' +
                '</tr>';
        });
        html += '</table>';
        $el.html(html);
    }

    /* ── Cargar transacciones ── */
    function loadTransactions() {
        var $tbody = $('#aura-capital-tbody');
        $tbody.html('<tr><td colspan="7" style="text-align:center;padding:40px;color:#888;"><span class="spinner is-active" style="float:none;margin:0 auto 8px;display:block;"></span>' + escHtml(auraCapital.strings.loading) + '</td></tr>');

        var params = $.extend({ action: 'aura_get_capital_expenses', nonce: nonce }, getFilters());

        $.post(ajaxUrl, params, function (resp) {
            if (!resp.success) {
                $tbody.html('<tr><td colspan="7" style="text-align:center;padding:40px;color:#dc2626;">' + escHtml(auraCapital.strings.error) + '</td></tr>');
                return;
            }
            var d = resp.data;
            totalPages = d.pages;
            updatePagination(d.page, d.pages, d.total);

            if (!d.items || !d.items.length) {
                $tbody.html('<tr><td colspan="7" style="text-align:center;padding:60px;color:#888;">' + escHtml(auraCapital.strings.noResults) + '</td></tr>');
                return;
            }

            var statusColors = {
                approved: { bg: '#d1fae5', color: '#065f46', label: 'Aprobado' },
                pending:  { bg: '#fef3c7', color: '#92400e', label: 'Pendiente' },
                rejected: { bg: '#fee2e2', color: '#991b1b', label: 'Rechazado' },
            };

            var rows = '';
            d.items.forEach(function (tx) {
                var st  = statusColors[tx.status] || { bg: '#e2e8f0', color: '#475569', label: tx.status };
                var cat = tx.category_name
                    ? '<span style="background:' + escHtml(tx.category_color || '#e67e22') + '20;color:' + escHtml(tx.category_color || '#e67e22') + ';padding:2px 10px;border-radius:999px;font-size:12px;"><span class="dashicons ' + escHtml(tx.category_icon || '') + '" style="font-size:11px;vertical-align:middle;"></span> ' + escHtml(tx.category_name) + '</span>'
                    : '<span style="color:#bbb;font-size:12px;">—</span>';

                var notes = tx.notes ? '<span style="font-size:11px;color:#888;" title="' + escHtml(tx.notes) + '">' + escHtml(tx.notes.substring(0, 60)) + (tx.notes.length > 60 ? '…' : '') + '</span>' : '—';

                rows +=
                    '<tr style="border-bottom:1px solid #f5f5f5;transition:background .15s;" onmouseenter="this.style.background=\'#fdf2e5\'" onmouseleave="this.style.background=\'\'">' +
                    '<td style="padding:12px 16px;font-size:13px;white-space:nowrap;">' + escHtml(tx.transaction_date) + '</td>' +
                    '<td style="padding:12px 16px;font-size:13px;max-width:280px;">' + escHtml(tx.description) + '</td>' +
                    '<td style="padding:12px 16px;">' + cat + '</td>' +
                    '<td style="padding:12px 16px;">' + notes + '</td>' +
                    '<td style="padding:12px 16px;text-align:right;font-weight:700;color:#e67e22;white-space:nowrap;">' + fmt(tx.amount) + '</td>' +
                    '<td style="padding:12px 16px;text-align:center;"><span style="background:' + st.bg + ';color:' + st.color + ';padding:2px 10px;border-radius:999px;font-size:11px;">' + st.label + '</span></td>' +
                    '<td style="padding:12px 16px;text-align:center;font-size:11px;color:#94a3b8;">' + escHtml(tx.reference_number || '—') + '</td>' +
                    '</tr>';
            });
            $tbody.html(rows);
        });
    }

    /* ── Paginación ── */
    function updatePagination(page, pages, total) {
        currentPage = page;
        totalPages  = pages;
        $('#cap-page-info').text('Mostrando página ' + page + ' de ' + pages + ' (' + total + ' registros)');
        $('#cap-prev-page').prop('disabled', page <= 1);
        $('#cap-next-page').prop('disabled', page >= pages);
    }

    /* ── Eventos ── */
    $('#cap-filter-year, #cap-filter-month, #cap-filter-category, #cap-filter-status').on('change', function () {
        currentPage = 1;
        loadStats();
        loadTransactions();
    });

    var searchTimer;
    $('#cap-filter-search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { currentPage = 1; loadTransactions(); }, 350);
    });

    $('#cap-clear-filters').on('click', function () {
        $('#cap-filter-year').val(new Date().getFullYear());
        $('#cap-filter-month').val('0');
        $('#cap-filter-category').val('0');
        $('#cap-filter-status').val('');
        $('#cap-filter-search').val('');
        currentPage = 1;
        loadStats();
        loadTransactions();
    });

    $('#cap-prev-page').on('click', function () {
        if (currentPage > 1) { currentPage--; loadTransactions(); }
    });
    $('#cap-next-page').on('click', function () {
        if (currentPage < totalPages) { currentPage++; loadTransactions(); }
    });

    /* ── Init ── */
    loadCategories();
    loadStats();
    loadTransactions();

}(jQuery));
