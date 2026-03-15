/**
 * Advanced Search — Aura Business Suite
 * Fase 5, Item 5.2
 */
/* global auraSearchConfig, jQuery */
(function ($) {
    'use strict';

    const cfg = auraSearchConfig;

    /* ============================================================
       Estado
       ============================================================ */
    let currentPage    = 1;
    let totalPages     = 1;
    let filterTagsList = [];     // tags seleccionados en filtro
    let currentFilters = {};     // guardados para exportar / save

    /* ============================================================
       Init
       ============================================================ */
    $(function () {
        loadSavedSearches();
        initTagsInput();
        bindEvents();
    });

    /* ============================================================
       Cargar búsquedas guardadas
       ============================================================ */
    function loadSavedSearches() {
        $.post(cfg.ajaxUrl, {
            action: 'aura_get_saved_searches',
            nonce:  cfg.searchNonce,
        }).done(function (res) {
            const $ul = $('#saved-searches-list');
            if (!res.success || !res.data.searches.length) {
                $ul.html('<li><em>' + cfg.i18n.noSaved + '</em></li>');
                return;
            }
            const items = res.data.searches.map(function (s) {
                return `<li>
                    <button class="load-search" data-id="${s.id}" data-filters='${JSON.stringify(s.filters)}'>${escHtml(s.name)}</button>
                    <button class="delete-search" data-id="${s.id}" title="Eliminar">✕</button>
                </li>`;
            });
            $ul.html(items.join(''));
        });
    }

    /* ============================================================
       Input de tags con autocompletar (chips)
       ============================================================ */
    function initTagsInput() {
        const $input = $('#filter-tags-input');
        $input.autocomplete({
            source: function (req, response) {
                $.post(cfg.ajaxUrl, {
                    action: 'aura_tags_autocomplete',
                    nonce:  cfg.tagsNonce,
                    term:   req.term,
                }).done(function (res) {
                    response(res.success ? res.data : []);
                });
            },
            select: function (e, ui) {
                e.preventDefault();
                addTagChip(ui.item.value);
                $input.val('');
            },
            minLength: 1,
        });

        $input.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const val = $(this).val().trim().replace(',', '');
                if (val) { addTagChip(val); $(this).val(''); }
            }
        });
    }

    function addTagChip(tag) {
        tag = tag.toLowerCase().trim();
        if (!tag || filterTagsList.includes(tag)) return;
        filterTagsList.push(tag);
        updateTagChipsUI();
        updateTagsHidden();
    }

    function removeTagChip(tag) {
        filterTagsList = filterTagsList.filter(t => t !== tag);
        updateTagChipsUI();
        updateTagsHidden();
    }

    function updateTagChipsUI() {
        const $chips = $('#filter-tags-chips');
        $chips.empty();
        filterTagsList.forEach(function (tag) {
            $chips.append(
                `<span class="aura-chip">${escHtml(tag)}
                    <button class="remove-chip" data-tag="${escHtml(tag)}" type="button">&times;</button>
                 </span>`
            );
        });
    }

    function updateTagsHidden() {
        $('#filter-tags-hidden').val(JSON.stringify(filterTagsList));
    }

    /* ============================================================
       Eventos principales
       ============================================================ */
    function bindEvents() {

        /* Enviar búsqueda */
        $('#aura-search-form').on('submit', function (e) {
            e.preventDefault();
            currentPage = 1;
            doSearch();
        });

        /* Limpiar filtros */
        $('#btn-clear-filters').on('click', function () {
            $('#aura-search-form')[0].reset();
            filterTagsList = [];
            updateTagChipsUI();
            updateTagsHidden();
            // Volver a marcar checkboxes por defecto
            $('input[name="search_in[]"][value="description"]').prop('checked', true);
            $('input[name="types[]"]').prop('checked', true);
        });

        /* Abrir modal guardar */
        $('#btn-save-search').on('click', function () {
            if (!Object.keys(currentFilters).length) {
                alert('Realiza una búsqueda antes de guardarla.');
                return;
            }
            $('#save-search-modal').show();
            $('#save-search-name').val('').focus();
        });

        /* Confirmar guardar */
        $('#confirm-save-search').on('click', function () {
            const name = $('#save-search-name').val().trim();
            if (!name) { alert('Ingresa un nombre'); return; }
            doSaveSearch(name);
        });

        /* Cancelar modal guardar */
        $('#cancel-save-search').on('click', function () {
            $('#save-search-modal').hide();
        });

        /* Cerrar modales clicando backdrop */
        $('.aura-modal-backdrop').on('click', function (e) {
            if ($(e.target).hasClass('aura-modal-backdrop')) $(this).hide();
        });

        /* Quitar chip de tag en filtro */
        $(document).on('click', '.remove-chip', function () {
            removeTagChip($(this).data('tag'));
        });

        /* Cargar búsqueda guardada */
        $(document).on('click', '.load-search', function () {
            const filters = $(this).data('filters');
            applyFilters(filters);
            currentPage = 1;
            doSearch();
        });

        /* Eliminar búsqueda guardada */
        $(document).on('click', '.delete-search', function () {
            if (!confirm(cfg.i18n.deleteSearch)) return;
            const id = $(this).data('id');
            $.post(cfg.ajaxUrl, {
                action: 'aura_delete_saved_search',
                nonce:  cfg.searchNonce,
                id:     id,
            }).done(function () { loadSavedSearches(); });
        });

        /* Exportar resultados */
        $(document).on('click', '#btn-export-search', function () {
            exportResults();
        });

        /* Paginación (delegado) */
        $(document).on('click', '.page-btn', function () {
            const p = parseInt($(this).data('page'));
            if (p !== currentPage) {
                currentPage = p;
                doSearch();
            }
        });
    }

    /* ============================================================
       Ejecutar búsqueda
       ============================================================ */
    function doSearch() {
        const formData = collectFilters();
        currentFilters = formData;

        showState('loading');

        const postData = buildPostData(formData);
        postData.action = 'aura_advanced_search';
        postData.nonce  = cfg.searchNonce;
        postData.page   = currentPage;

        $.post(cfg.ajaxUrl, postData).done(function (res) {
            if (!res.success) {
                showState('empty');
                return;
            }
            const d = res.data;
            if (!d.results || d.results.length === 0) {
                showState('empty');
                return;
            }

            totalPages = d.pages;
            renderResults(d.results);
            renderStats(d.total, d.total_amount);
            renderPagination(d.page, d.pages);
            showState('results');
        }).fail(function () {
            showState('empty');
        });
    }

    /* ============================================================
       Recolectar filtros del formulario
       ============================================================ */
    function collectFilters() {
        const f = {};
        f.text       = $('#filter-text').val().trim();
        f.search_in  = $('input[name="search_in[]"]:checked').map(function () { return $(this).val(); }).get();
        f.date_from  = $('input[name="date_from"]').val();
        f.date_to    = $('input[name="date_to"]').val();
        f.types      = $('input[name="types[]"]:checked').map(function () { return $(this).val(); }).get();
        f.categories = $('#filter-categories').val() || [];
        f.statuses   = $('input[name="statuses[]"]:checked').map(function () { return $(this).val(); }).get();
        f.amount_min = $('input[name="amount_min"]').val();
        f.amount_max = $('input[name="amount_max"]').val();
        f.methods    = $('#filter-methods').val() || [];
        f.tags       = filterTagsList.slice();
        f.created_by = $('#filter-created-by').val();
        f.has_receipt = $('select[name="has_receipt"]').val();
        return f;
    }

    /* ============================================================
       Construir objeto POST desde filtros
       ============================================================ */
    function buildPostData(f) {
        const data = {};
        if (f.text)       data.text = f.text;
        if (f.search_in && f.search_in.length) data['search_in[]'] = f.search_in;
        if (f.date_from)  data.date_from = f.date_from;
        if (f.date_to)    data.date_to   = f.date_to;
        if (f.types && f.types.length)      data['types[]']      = f.types;
        if (f.categories && f.categories.length) data['categories[]'] = f.categories;
        if (f.statuses && f.statuses.length) data['statuses[]']  = f.statuses;
        if (f.amount_min) data.amount_min = f.amount_min;
        if (f.amount_max) data.amount_max = f.amount_max;
        if (f.methods && f.methods.length)  data['methods[]']    = f.methods;
        if (f.tags && f.tags.length)        data['tags[]']       = f.tags;
        if (f.created_by) data.created_by  = f.created_by;
        if (f.has_receipt) data.has_receipt = f.has_receipt;
        return data;
    }

    /* ============================================================
       Aplicar filtros guardados al formulario
       ============================================================ */
    function applyFilters(f) {
        if (!f) return;
        $('#filter-text').val(f.text || '');
        // Checkboxes search_in
        $('input[name="search_in[]"]').prop('checked', false);
        (f.search_in || []).forEach(function (v) {
            $('input[name="search_in[]"][value="' + v + '"]').prop('checked', true);
        });
        $('input[name="date_from"]').val(f.date_from || '');
        $('input[name="date_to"]').val(f.date_to || '');
        $('input[name="types[]"]').prop('checked', false);
        (f.types || []).forEach(function (v) {
            $('input[name="types[]"][value="' + v + '"]').prop('checked', true);
        });
        $('#filter-categories').val(f.categories || []);
        $('input[name="statuses[]"]').prop('checked', false);
        (f.statuses || []).forEach(function (v) {
            $('input[name="statuses[]"][value="' + v + '"]').prop('checked', true);
        });
        $('input[name="amount_min"]').val(f.amount_min || '');
        $('input[name="amount_max"]').val(f.amount_max || '');
        $('#filter-methods').val(f.methods || []);
        filterTagsList = f.tags || [];
        updateTagChipsUI();
        updateTagsHidden();
        $('#filter-created-by').val(f.created_by || '');
        $('select[name="has_receipt"]').val(f.has_receipt || '');
    }

    /* ============================================================
       Guardar búsqueda
       ============================================================ */
    function doSaveSearch(name) {
        $.post(cfg.ajaxUrl, {
            action:  'aura_save_search',
            nonce:   cfg.searchNonce,
            name:    name,
            filters: JSON.stringify(currentFilters),
        }).done(function (res) {
            $('#save-search-modal').hide();
            if (res.success) {
                loadSavedSearches();
                showToast(cfg.i18n.saveSuccess, 'success');
            }
        });
    }

    /* ============================================================
       Renderizar resultados
       ============================================================ */
    function renderResults(rows) {
        const $body = $('#search-results-body');
        $body.empty();

        rows.forEach(function (row) {
            const typeBadge = row.transaction_type === 'income'
                ? `<span class="aura-badge aura-badge-income">${cfg.i18n.income}</span>`
                : `<span class="aura-badge aura-badge-expense">${cfg.i18n.expense}</span>`;

            const statusLabel = cfg.i18n[row.status] || row.status;
            const statusBadge = `<span class="aura-badge aura-badge-${escHtml(row.status)}">${statusLabel}</span>`;

            const desc     = row.description_hl || escHtml(row.description || '');
            const catColor = row.category_color ? `background:${escHtml(row.category_color)};` : '';
            const catBadge = row.category_name
                ? `<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:.78rem;${catColor}
                             color:#fff;">${escHtml(row.category_name)}</span>`
                : '—';

            const tags = (row.tags || '').split(',').map(t => t.trim()).filter(Boolean);
            const tagChips = tags.map(t => `<span class="aura-tag-chip">${escHtml(t)}</span>`).join('');

            const amount = parseFloat(row.amount || 0);
            const amountStr = cfg.currency + ' ' + formatNumber(amount);

            $body.append(`<tr>
                <td>${escHtml(row.transaction_date || '')}</td>
                <td>${desc}</td>
                <td>${catBadge}</td>
                <td style="text-align:center;">${typeBadge}</td>
                <td style="text-align:right;font-weight:600;">${amountStr}</td>
                <td style="text-align:center;">${statusBadge}</td>
                <td>${tagChips || '—'}</td>
            </tr>`);
        });
    }

    /* ============================================================
       Estadísticas
       ============================================================ */
    function renderStats(total, totalAmount) {
        $('#search-count').text(total + ' ' + cfg.i18n.results);
        $('#search-total-amount').text(cfg.i18n.total + ' ' + cfg.currency + ' ' + formatNumber(totalAmount));
        $('#search-stats').show();
    }

    /* ============================================================
       Paginación
       ============================================================ */
    function renderPagination(page, pages) {
        const $pag = $('#search-pagination');
        if (pages <= 1) { $pag.hide(); return; }

        let html = cfg.i18n.page + ' ';
        for (let i = 1; i <= pages; i++) {
            const active = i === page ? 'active' : '';
            html += `<button class="page-btn ${active}" data-page="${i}">${i}</button>`;
        }
        html += ' ' + cfg.i18n.of + ' ' + pages;
        $pag.html(html).show();
    }

    /* ============================================================
       Exportar resultados a CSV (client-side)
       ============================================================ */
    function exportResults() {
        const rows = [];
        const headers = ['Fecha', 'Descripción', 'Categoría', 'Tipo', 'Monto', 'Estado', 'Etiquetas'];
        rows.push(headers.join(','));

        $('#search-results-body tr').each(function () {
            const cells = $(this).find('td').map(function (i) {
                if (i === 0) return $(this).text();  // fecha
                if (i === 4) return $(this).text().replace(cfg.currency, '').trim();
                return '"' + $(this).text().replace(/"/g, '""') + '"';
            }).get();
            rows.push(cells.join(','));
        });

        const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'busqueda-aura-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    /* ============================================================
       Mostrar estado del panel de resultados
       ============================================================ */
    function showState(state) {
        $('#search-initial, #search-loading, #search-empty, #search-results-table, #search-stats, #search-pagination').hide();
        if (state === 'loading') {
            $('#search-loading').show();
        } else if (state === 'empty') {
            $('#search-empty').show();
        } else if (state === 'results') {
            $('#search-results-table, #search-stats, #search-pagination').show();
            if (totalPages > 1) $('#search-pagination').show();
        }
    }

    /* ============================================================
       Toast notification
       ============================================================ */
    function showToast(msg, type) {
        const color = type === 'success' ? '#1e7e34' : '#c00';
        const $t = $('<div>').css({
            position: 'fixed', bottom: '24px', right: '24px',
            background: color, color: '#fff', padding: '12px 20px',
            borderRadius: '6px', boxShadow: '0 4px 12px rgba(0,0,0,.2)',
            zIndex: 99999, fontSize: '14px',
        }).text(msg);
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, 3500);
    }

    /* ============================================================
       Utils
       ============================================================ */
    function formatNumber(n) {
        return Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);
