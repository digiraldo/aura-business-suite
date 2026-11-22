/**
 * Advanced Search — Aura Business Suite
 * Fase 5, Item 5.2
 * Optimizado con Presets de Fecha, KPIs, Universal Child Rows y Tooltips HTML
 */
/* global auraSearchConfig, jQuery */
(function ($) {
    'use strict';

    function __(s, domain) { return (window.wp && wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__(s, domain || 'aura-suite') : s; }
    function sprintf(fmt, ...args) { return (window.wp && wp.i18n && typeof wp.i18n.sprintf === 'function') ? wp.i18n.sprintf(fmt, ...args) : fmt; }

    const cfg = auraSearchConfig;

    /* ============================================================
       Estado
       ============================================================ */
    let currentPage    = 1;
    let totalPages     = 1;
    let filterTagsList = [];     // tags seleccionados en filtro
    let currentFilters = {};     // guardados para exportar / save
    let lastResults    = [];

    /* ============================================================
       Init
       ============================================================ */
    $(function () {
        initPortalTooltip();
        loadSavedSearches();
        initTagsInput();
        initDatePresets();
        initSegmentButtons();
        initSidebarToggle();
        bindEvents();
        checkUrlParamsAndAutoSearch();
    });

    /* ============================================================
       Portal Global de Tooltips
       ============================================================ */
    function initPortalTooltip() {
        if (typeof AuraUI !== 'undefined' && AuraUI.initTooltips) {
            AuraUI.initTooltips();
        }
    }

    /* ============================================================
       Sidebar Toggle (Ocultar / Mostrar Filtros con Persistencia)
       ============================================================ */
    function initSidebarToggle() {
        const isSavedHidden = localStorage.getItem('aura_search_filters_hidden') === 'true';
        if (isSavedHidden) {
            $('#aura-search-layout').addClass('is-sidebar-hidden');
            $('#btn-toggle-filters-text').text(cfg.i18n.showFilters || __('Mostrar Filtros', 'aura-suite'));
            $('#btn-toggle-filters-top .dashicons').removeClass('dashicons-filter').addClass('dashicons-visibility');
        }

        $(document).on('click', '#toggle-filters, #btn-toggle-filters-top', function (e) {
            e.preventDefault();
            const $layout = $('#aura-search-layout');
            const isHidden = $layout.hasClass('is-sidebar-hidden');

            if (isHidden) {
                $layout.removeClass('is-sidebar-hidden');
                $('#btn-toggle-filters-text').text(cfg.i18n.hideFilters || __('Ocultar Filtros', 'aura-suite'));
                $('#btn-toggle-filters-top .dashicons').removeClass('dashicons-visibility').addClass('dashicons-filter');
                localStorage.setItem('aura_search_filters_hidden', 'false');
            } else {
                $layout.addClass('is-sidebar-hidden');
                $('#btn-toggle-filters-text').text(cfg.i18n.showFilters || __('Mostrar Filtros', 'aura-suite'));
                $('#btn-toggle-filters-top .dashicons').removeClass('dashicons-filter').addClass('dashicons-visibility');
                localStorage.setItem('aura_search_filters_hidden', 'true');
            }
        });
    }

    /* ============================================================
       Presets de Rango de Fechas (Hoy, Mes, Trimestre, Semestre, Año)
       ============================================================ */
    function initDatePresets() {
        $(document).on('click', '.aura-preset-pill', function(e) {
            e.preventDefault();
            const preset = $(this).data('preset');
            $('.aura-preset-pill').removeClass('is-active');
            $(this).addClass('is-active');

            applyDatePreset(preset);
            currentPage = 1;
            doSearch();
        });

        // Si el usuario cambia las fechas manualmente, desactivar preset
        $('#filter-date-from, #filter-date-to').on('change', function() {
            $('.aura-preset-pill').removeClass('is-active');
        });
    }

    function applyDatePreset(preset) {
        const now = new Date();
        let from = '';
        let to = '';

        function fmt(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        switch (preset) {
            case 'today':
                from = fmt(now);
                to   = fmt(now);
                break;

            case 'this-month':
                from = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
                to   = fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0));
                break;

            case 'last-month':
                from = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1));
                to   = fmt(new Date(now.getFullYear(), now.getMonth(), 0));
                break;

            case 'this-quarter':
                const qMonth = Math.floor(now.getMonth() / 3) * 3;
                from = fmt(new Date(now.getFullYear(), qMonth, 1));
                to   = fmt(new Date(now.getFullYear(), qMonth + 3, 0));
                break;

            case 'last-semester':
                // Últimos 6 meses hasta fin del mes actual
                from = fmt(new Date(now.getFullYear(), now.getMonth() - 5, 1));
                to   = fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0));
                break;

            case 'this-year':
                from = `${now.getFullYear()}-01-01`;
                to   = `${now.getFullYear()}-12-31`;
                break;

            case 'all':
            default:
                from = '';
                to   = '';
                break;
        }

        $('#filter-date-from').val(from);
        $('#filter-date-to').val(to);
    }

    /* ============================================================
       Segment Buttons (Tipo y Estado)
       ============================================================ */
    function initSegmentButtons() {
        $(document).on('change', '.aura-seg-btn input[type="checkbox"]', function() {
            const $label = $(this).closest('.aura-seg-btn');
            if ($(this).is(':checked')) {
                $label.addClass('is-active');
            } else {
                $label.removeClass('is-active');
            }
        });
    }

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
                $ul.html('<li style="color:#94a3b8; font-size:12.5px; padding:4px 0;"><em>' + cfg.i18n.noSaved + '</em></li>');
                return;
            }
            const items = res.data.searches.map(function (s) {
                return `<li style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f1f5f9;">
                    <button type="button" class="load-search aura-btn aura-btn-secondary" data-id="${s.id}" data-filters='${JSON.stringify(s.filters)}' style="font-size:12px; padding:4px 8px; text-align:left; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <span class="dashicons dashicons-star-filled" style="font-size:13px;width:13px;height:13px;color:#f59e0b;"></span>
                        ${escHtml(s.name)}
                    </button>
                    <button type="button" class="delete-search aura-btn-icon aura-btn-icon--danger" data-id="${s.id}" title="${__('Eliminar preset', 'aura-suite')}" style="width:24px;height:24px;margin-left:6px;border-radius:4px;">
                        <span class="dashicons dashicons-trash" style="font-size:13px;width:13px;height:13px;"></span>
                    </button>
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
                `<span class="aura-chip" style="display:inline-flex;align-items:center;gap:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;padding:3px 8px;font-size:12px;font-weight:700;">
                    #${escHtml(tag)}
                    <button class="remove-chip" data-tag="${escHtml(tag)}" type="button" style="background:none;border:none;color:#1d4ed8;cursor:pointer;font-weight:bold;padding:0 2px;">&times;</button>
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

        /* Toggle Sintaxis Help */
        $('.aura-syntax-toggle').on('click', function(e) {
            e.preventDefault();
            const $panel = $('#aura-syntax-help');
            if ($panel.is(':hidden')) {
                $panel.slideDown(200);
                $(this).attr('aria-expanded', 'true');
            } else {
                $panel.slideUp(200);
                $(this).attr('aria-expanded', 'false');
            }
        });

        /* Toggle de Filas Expandibles (Universal Child Rows) */
        $(document).on('click', '.aura-row-toggle', function(e) {
            e.stopPropagation();
            const $btn = $(this);
            const $parentRow = $btn.closest('tr.aura-parent-row');
            const rowId = $parentRow.data('row-id');
            const $childRow = $('tr.aura-child-row[data-child-for="' + rowId + '"]');

            if ($childRow.is(':visible')) {
                $childRow.hide();
                $btn.text('+').attr('aria-expanded', 'false').removeClass('is-active');
                $parentRow.removeClass('is-expanded');
            } else {
                $childRow.show();
                $btn.text('−').attr('aria-expanded', 'true').addClass('is-active');
                $parentRow.addClass('is-expanded');
            }
        });

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

            $('.aura-preset-pill').removeClass('is-active');
            $('.aura-preset-pill[data-preset="all"]').addClass('is-active');

            $('input[name="types[]"]').prop('checked', true).closest('.aura-seg-btn').addClass('is-active');
            $('input[name="statuses[]"]').prop('checked', false).closest('.aura-seg-btn').removeClass('is-active');
            $('input[name="statuses[]"][value="approved"], input[name="statuses[]"][value="pending"]').prop('checked', true).closest('.aura-seg-btn').addClass('is-active');

            $('#filter-categories').val([]);
            $('#filter-created-by').val('');
            $('select[name="has_receipt"]').val('');

            currentPage = 1;
            doSearch();
        });

        /* Abrir modal guardar */
        $('#btn-save-search').on('click', function () {
            if (!Object.keys(currentFilters).length) {
                alert(__('Realiza una búsqueda antes de guardarla.', 'aura-suite'));
                return;
            }
            $('#save-search-modal').css('display', 'flex').hide().fadeIn(200);
            $('#save-search-name').val('').focus();
        });

        /* Confirmar guardar */
        $('#confirm-save-search').on('click', function () {
            const name = $('#save-search-name').val().trim();
            if (!name) { alert(__('Ingresa un nombre para el preset.', 'aura-suite')); return; }
            doSaveSearch(name);
        });

        /* Cancelar modal guardar */
        $('#cancel-save-search, #cancel-save-search-btn').on('click', function () {
            $('#save-search-modal').fadeOut(150);
        });

        /* Cerrar modales clicando backdrop o presionando Escape */
        $(document).on('click', '#save-search-modal', function (e) {
            if ($(e.target).is('#save-search-modal')) $('#save-search-modal').fadeOut(150);
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                $('#save-search-modal').fadeOut(150);
            }
        });

        /* Quitar chip de tag en filtro */
        $(document).on('click', '.remove-chip', function () {
            removeTagChip($(this).data('tag'));
        });

        /* Clic en chip dentro de resultados para filtrar por ese tag */
        $(document).on('click', '.btn-filter-by-tag', function (e) {
            e.preventDefault();
            const tag = $(this).data('tag');
            if (tag) {
                addTagChip(tag);
                currentPage = 1;
                doSearch();
            }
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

        /* Paginación */
        $(document).on('click', '.page-btn', function () {
            const p = parseInt($(this).data('page'), 10);
            if (p !== currentPage) {
                currentPage = p;
                doSearch();
                $('html, body').animate({ scrollTop: $('#search-results-panel').offset().top - 40 }, 300);
            }
        });
    }

    /* ============================================================
       Precarga de parámetros URL y autoejecución
       ============================================================ */
    function checkUrlParamsAndAutoSearch() {
        const urlParams = new URLSearchParams(window.location.search);
        const searchTag = urlParams.get('search_tag') || urlParams.get('tag');
        const searchQ   = urlParams.get('q') || urlParams.get('text');

        if (searchTag) {
            addTagChip(searchTag);
        }
        if (searchQ) {
            $('#filter-text').val(searchQ);
        }

        // Ejecutar búsqueda inicial directa
        doSearch();
    }

    /* ============================================================
       Ejecutar búsqueda AJAX
       ============================================================ */
    function doSearch() {
        const formData = collectFilters();
        currentFilters = formData;

        updateKpiFiltersCount(formData);
        showState('loading');

        const postData = buildPostData(formData);
        postData.action = 'aura_advanced_search';
        postData.nonce  = cfg.searchNonce;
        postData.page   = currentPage;

        $.post(cfg.ajaxUrl, postData).done(function (res) {
            if (!res.success) {
                showState('empty');
                renderStats(0, 0);
                return;
            }
            const d = res.data;
            lastResults = d.results || [];

            if (!lastResults || lastResults.length === 0) {
                showState('empty');
                renderStats(0, 0);
                return;
            }

            totalPages = d.pages;
            renderResults(lastResults);
            renderStats(d.total, d.total_amount);
            renderPagination(d.page, d.pages);
            showState('results');
        }).fail(function () {
            showState('empty');
            renderStats(0, 0);
        });
    }

    /* ============================================================
       Recolectar filtros del formulario
       ============================================================ */
    function collectFilters() {
        const f = {};
        f.text       = $('#filter-text').val().trim();
        f.date_from  = $('#filter-date-from').val();
        f.date_to    = $('#filter-date-to').val();
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

    function updateKpiFiltersCount(f) {
        let count = 0;
        if (f.text) count++;
        if (f.date_from || f.date_to) count++;
        if (f.types && f.types.length && f.types.length < 3) count++;
        if (f.categories && f.categories.length) count++;
        if (f.statuses && f.statuses.length && f.statuses.length < 3) count++;
        if (f.amount_min || f.amount_max) count++;
        if (f.methods && f.methods.length) count++;
        if (f.tags && f.tags.length) count++;
        if (f.created_by) count++;
        if (f.has_receipt) count++;

        $('#kpi-search-filters-count').text(count);
    }

    /* ============================================================
       Construir objeto POST desde filtros
       ============================================================ */
    function buildPostData(f) {
        const data = {};
        if (f.text)       data.text = f.text;
        if (f.date_from)  data.date_from = f.date_from;
        if (f.date_to)    data.date_to   = f.date_to;
        if (f.types && f.types.length)           data['types[]']      = f.types;
        if (f.categories && f.categories.length) data['categories[]'] = f.categories;
        if (f.statuses && f.statuses.length)     data['statuses[]']   = f.statuses;
        if (f.amount_min) data.amount_min = f.amount_min;
        if (f.amount_max) data.amount_max = f.amount_max;
        if (f.methods && f.methods.length)       data['methods[]']    = f.methods;
        if (f.tags && f.tags.length)             data['tags[]']       = f.tags;
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
        $('#filter-date-from').val(f.date_from || '');
        $('#filter-date-to').val(f.date_to || '');

        $('.aura-preset-pill').removeClass('is-active');

        // Tipos
        $('input[name="types[]"]').prop('checked', false).closest('.aura-seg-btn').removeClass('is-active');
        (f.types || []).forEach(function (v) {
            $('input[name="types[]"][value="' + v + '"]').prop('checked', true).closest('.aura-seg-btn').addClass('is-active');
        });

        // Estados
        $('input[name="statuses[]"]').prop('checked', false).closest('.aura-seg-btn').removeClass('is-active');
        (f.statuses || []).forEach(function (v) {
            $('input[name="statuses[]"][value="' + v + '"]').prop('checked', true).closest('.aura-seg-btn').addClass('is-active');
        });

        $('#filter-categories').val(f.categories || []);
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
            $('#save-search-modal').fadeOut(150);
            if (res.success) {
                loadSavedSearches();
                showToast(cfg.i18n.saveSuccess, 'success');
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al guardar.', 'aura-suite'));
            }
        });
    }

    /* ============================================================
       Renderizar resultados con Child Rows y Tooltips HTML en TODO
       ============================================================ */
    function renderResults(rows) {
        const $body = $('#search-results-body');
        $body.empty();

        const htmlRows = [];

        rows.forEach(function (row, index) {
            const rowId = 'search-row-' + index;
            const isIncome  = row.transaction_type === 'income';
            const isCapital = row.transaction_type === 'capital';
            const amountVal = parseFloat(row.amount || 0);
            const amountSign = isIncome ? '+' : '-';
            let amountColor = '#dc2626';
            if (isIncome)  amountColor = '#059669';
            if (isCapital) amountColor = '#2563eb';

            let typeLabel = cfg.i18n.expense;
            let typeBadge = `<span class="aura-badge aura-badge-red" style="font-weight:700;">🔴 ${cfg.i18n.expense}</span>`;
            if (isIncome) {
                typeLabel = cfg.i18n.income;
                typeBadge = `<span class="aura-badge aura-badge-green" style="font-weight:700;">🟢 ${cfg.i18n.income}</span>`;
            } else if (isCapital) {
                typeLabel = cfg.i18n.capital || __('Gasto de Capital', 'aura-suite');
                typeBadge = `<span class="aura-badge aura-badge-blue" style="font-weight:700;">🔵 ${typeLabel}</span>`;
            }

            let statusClass = 'aura-badge-blue';
            if (row.status === 'approved') statusClass = 'aura-badge-green';
            if (row.status === 'rejected') statusClass = 'aura-badge-red';
            if (row.status === 'pending')  statusClass = 'aura-badge-orange';

            const statusLabel = cfg.i18n[row.status] || row.status;
            const statusBadge = `<span class="aura-badge ${statusClass}" style="font-size:11px;padding:2px 7px;">${statusLabel}</span>`;

            const descText = row.description_hl || escHtml(row.description || __('Sin concepto', 'aura-suite'));
            const catName = row.category_name ? escHtml(row.category_name) : __('Sin Categoría', 'aura-suite');
            const catColor = row.category_color ? escHtml(row.category_color) : '#64748b';
            
            let catIconHtml = '';
            if (row.category_icon) {
                if (row.category_icon.indexOf('dashicons') !== -1) {
                    const dashClass = row.category_icon.indexOf('dashicons-') === 0 ? row.category_icon : 'dashicons-' + row.category_icon;
                    catIconHtml = `<span class="dashicons ${dashClass}" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:3px;"></span>`;
                } else {
                    catIconHtml = `<span class="aura-cat-emoji" style="font-size:13px;line-height:1;vertical-align:middle;margin-right:3px;">${escHtml(row.category_icon)}</span>`;
                }
            } else {
                catIconHtml = `<span class="dashicons dashicons-category" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:3px;"></span>`;
            }

            // Tags
            const tags = (row.tags || '').split(',').map(t => t.trim()).filter(Boolean);
            const tagChips = tags.map(function(t) {
                const tagTipHtml = `<div class="aura-tip-card">` +
                    `<div class="aura-tip-card-header">` +
                    `<div class="aura-tip-avatar-large" style="background:#3b82f620;color:#2563eb;">#</div>` +
                    `<div class="aura-tip-info">` +
                    `<div class="aura-tip-title">#${escHtml(t)}</div>` +
                    `<div class="aura-tip-subtitle">${__('Taxonomía Contable', 'aura-suite')}</div>` +
                    `</div>` +
                    `</div>` +
                    `<div class="aura-tip-card-body">` +
                    `<div class="aura-tip-row"><span>${__('Acción:', 'aura-suite')}</span><strong style="color:#2563eb;">${sprintf(__('Haz clic para filtrar la búsqueda por #%s', 'aura-suite'), escHtml(t))}</strong></div>` +
                    `</div>` +
                    `</div>`;
                return `<button type="button" class="aura-tag-chip btn-filter-by-tag" data-tag="${escHtml(t)}" data-tooltip="${escAttr(tagTipHtml)}" style="font-size:11px;padding:2px 6px;">#${escHtml(t)}</button>`;
            }).join(' ');

            // Recibo / Adjunto (Columna ADJ)
            let receiptIcon = '<span style="color:#cbd5e1;">—</span>';
            if (row.receipt_file) {
                const isDrive = (row.receipt_file.indexOf('drive.google') !== -1);
                const isPdf = /\.pdf($|\?)/i.test(row.receipt_file);
                let previewUrl = row.receipt_file;
                let thumbUrl = row.receipt_file;
                let downloadUrl = row.receipt_file;

                if (isDrive) {
                    const match = row.receipt_file.match(/\/d\/([a-zA-Z0-9_-]+)/);
                    if (match && match[1]) {
                        previewUrl = 'https://drive.google.com/file/d/' + match[1] + '/preview';
                        thumbUrl = 'https://drive.google.com/thumbnail?id=' + match[1] + '&sz=w1600';
                        downloadUrl = 'https://drive.google.com/uc?export=download&id=' + match[1];
                    }
                }

                const badgeBg = isDrive ? '#eff6ff' : '#ecfdf5';
                const badgeColor = isDrive ? '#2563eb' : '#059669';
                const badgeBorder = isDrive ? '#bfdbfe' : '#a7f3d0';
                const iconClass = isDrive ? 'dashicons-cloud' : (isPdf ? 'dashicons-media-document' : 'dashicons-paperclip');

                const receiptTipHtml = `<div class="aura-tip-card">` +
                    `<div class="aura-tip-card-header">` +
                    `<div class="aura-tip-avatar-large" style="background:${badgeBg};color:${badgeColor};"><span class="dashicons ${iconClass}" style="font-size:24px;"></span></div>` +
                    `<div class="aura-tip-info">` +
                    `<div class="aura-tip-title">${isDrive ? __('Comprobante Google Drive', 'aura-suite') : __('Comprobante Adjunto', 'aura-suite')}</div>` +
                    `<div class="aura-tip-subtitle">${isDrive ? __('Almacenamiento en Nube', 'aura-suite') : __('Archivo Local', 'aura-suite')}</div>` +
                    `</div>` +
                    `</div>` +
                    `<div class="aura-tip-card-body">` +
                    `<div class="aura-tip-row"><span>${__('Transacción:', 'aura-suite')}</span><strong>#${row.id}</strong></div>` +
                    `<div class="aura-tip-row"><span>${__('Tipo:', 'aura-suite')}</span><strong>${isPdf ? __('Documento PDF', 'aura-suite') : __('Imagen / Recibo', 'aura-suite')}</strong></div>` +
                    `<div class="aura-tip-row"><span>${__('Acción:', 'aura-suite')}</span><strong style="color:${badgeColor};">${__('Haz clic para ver en pantalla completa', 'aura-suite')}</strong></div>` +
                    `</div>` +
                    `</div>`;

                receiptIcon = `<button type="button" class="aura-btn-icon aura-open-receipt-viewer" 
                    data-receipt-url="${escAttr(row.receipt_file)}" 
                    data-preview-url="${escAttr(previewUrl)}" 
                    data-download-url="${escAttr(downloadUrl)}" 
                    data-is-drive="${isDrive ? '1' : '0'}" 
                    data-is-pdf="${isPdf ? '1' : '0'}" 
                    data-is-img="${!isPdf ? '1' : '0'}" 
                    data-title="${sprintf(__('Transacción #%s - Comprobante', 'aura-suite'), row.id)}" 
                    data-tooltip="${escAttr(receiptTipHtml)}" 
                    style="width:28px;height:28px;background:${badgeBg};color:${badgeColor};border:1px solid ${badgeBorder};border-radius:6px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;">
                    <span class="dashicons ${iconClass}"></span>
                </button>`;
            }

            // ── TOOLTIPS HTML COMPLETOS (prompt-maestro.md) ──────────
            // 1. Tooltip ID / Fila
            const idTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:${amountColor}20;color:${amountColor};">#${row.id}</div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">${sprintf(__('Transacción #%s', 'aura-suite'), row.id)}</div>` +
                `<div class="aura-tip-subtitle">${escHtml(row.transaction_date || '')} · ${typeLabel}</div>` +
                `<div class="aura-tip-badges">` +
                `<span class="aura-tip-badge" style="background:${amountColor}25;color:${amountColor};">${typeLabel}</span>` +
                `<span class="aura-tip-badge">${statusLabel}</span>` +
                `</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>${__('Concepto:', 'aura-suite')}</span><strong>${escHtml(row.description || __('Sin concepto', 'aura-suite'))}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Importe:', 'aura-suite')}</span><strong style="color:${amountColor}">${amountSign}${cfg.currency} ${formatNumber(amountVal)}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Categoría:', 'aura-suite')}</span><strong>${catName}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Acción:', 'aura-suite')}</span><strong style="color:#2563eb;">${__('Haz clic en [+] para ver la ficha contable completa', 'aura-suite')}</strong></div>` +
                `</div>` +
                `</div>`;

            // 2. Tooltip Fecha
            const dateTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:#3b82f620;color:#2563eb;"><span class="dashicons dashicons-calendar-alt" style="font-size:24px;"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">${__('Fecha Contable', 'aura-suite')}</div>` +
                `<div class="aura-tip-subtitle">${escHtml(row.transaction_date || '')}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>${__('Transacción:', 'aura-suite')}</span><strong>#${row.id}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Registrado por:', 'aura-suite')}</span><strong>${escHtml(row.created_by_name || __('Sistema', 'aura-suite'))}</strong></div>` +
                `${row.approved_at ? `<div class="aura-tip-row"><span>${__('Aprobado en:', 'aura-suite')}</span><strong>${escHtml(row.approved_at)}</strong></div>` : ''}` +
                `</div>` +
                `</div>`;

            // 3. Tooltip Concepto / Descripción
            const descTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:#6366f120;color:#6366f1;"><span class="dashicons dashicons-media-text" style="font-size:24px;"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">${escHtml(row.description || __('Detalle de Concepto', 'aura-suite'))}</div>` +
                `<div class="aura-tip-subtitle">${row.recipient_payer ? __('Beneficiario:', 'aura-suite') + ' ' + escHtml(row.recipient_payer) : __('Movimiento Contable', 'aura-suite')}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>${__('Folio/Ref:', 'aura-suite')}</span><strong>${escHtml(row.reference_number || __('N/A', 'aura-suite'))}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Método:', 'aura-suite')}</span><strong>${formatPaymentMethod(row.payment_method)}</strong></div>` +
                `${row.notes ? `<div class="aura-tip-row"><span>${__('Observaciones:', 'aura-suite')}</span><strong>${escHtml(row.notes)}</strong></div>` : ''}` +
                `</div>` +
                `</div>`;

            // 4. Tooltip Categoría
            const catTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:${catColor}20;color:${catColor};">${catIconHtml}</div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">${catName}</div>` +
                `<div class="aura-tip-subtitle">${__('Clasificación Presupuestal', 'aura-suite')}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>${__('Naturaleza:', 'aura-suite')}</span><strong>${typeLabel}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Transacción:', 'aura-suite')}</span><strong>#${row.id}</strong></div>` +
                `</div>` +
                `</div>`;

            // 5. Tooltip Tipo / Estado
            const typeStatusTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:${amountColor}20;color:${amountColor};">${isIncome ? '🟢' : (isCapital ? '🔵' : '🔴')}</div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">${typeLabel}</div>` +
                `<div class="aura-tip-subtitle">${sprintf(__('Estado: %s', 'aura-suite'), statusLabel)}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>${__('Flujo:', 'aura-suite')}</span><strong>${isIncome ? __('Entrada (+) de Fondos', 'aura-suite') : (isCapital ? __('Inversión de Capital', 'aura-suite') : __('Salida (-) de Fondos', 'aura-suite'))}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Aprobación:', 'aura-suite')}</span><strong>${statusLabel}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Registrado por:', 'aura-suite')}</span><strong>${escHtml(row.created_by_name || __('Sistema', 'aura-suite'))}</strong></div>` +
                `</div>` +
                `</div>`;

            // 6. Tooltip Importe
            const amountTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:${amountColor}20;color:${amountColor};"><span class="dashicons dashicons-money-alt" style="font-size:24px;"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title" style="color:${amountColor};">${amountSign}${cfg.currency} ${formatNumber(amountVal)}</div>` +
                `<div class="aura-tip-subtitle">${__('Importe Operativo Neto', 'aura-suite')}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>${__('Forma de Pago:', 'aura-suite')}</span><strong>${formatPaymentMethod(row.payment_method)}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Moneda:', 'aura-suite')}</span><strong>${cfg.currency}</strong></div>` +
                `<div class="aura-tip-row"><span>${__('Estado:', 'aura-suite')}</span><strong>${statusLabel}</strong></div>` +
                `</div>` +
                `</div>`;

            const catBadge = `<span class="aura-badge" style="background:${catColor}15;color:${catColor};border:1px solid ${catColor}40;font-weight:600;" data-tooltip="${escAttr(catTipHtml)}">
                ${catIconHtml}
                ${catName}
            </span>`;

            // 1. Fila Principal con Tooltips Ricos en cada celda
            const parentRow = `<tr class="aura-parent-row" data-row-id="${rowId}">
                <td style="text-align: center;">
                    <div class="aura-id-toggle-wrap">
                        <button type="button" class="aura-row-toggle" aria-expanded="false" data-tooltip="${escAttr(idTipHtml)}">+</button>
                        <span class="aura-txn-id-pill" data-tooltip="${escAttr(idTipHtml)}">#${row.id}</span>
                    </div>
                </td>
                <td style="text-align: center;">
                    <span class="aura-date-badge" data-tooltip="${escAttr(dateTipHtml)}">${escHtml(row.transaction_date || '')}</span>
                </td>
                <td>
                    <div style="font-weight:600;color:#0f172a;line-height:1.35;cursor:pointer;" data-tooltip="${escAttr(descTipHtml)}">${descText}</div>
                    ${row.notes ? `<div style="font-size:11.5px;color:#64748b;margin-top:2px;" data-tooltip="${escAttr(descTipHtml)}">${row.notes_hl || escHtml(row.notes)}</div>` : ''}
                </td>
                <td>${catBadge}</td>
                <td style="text-align: center;">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;" data-tooltip="${escAttr(typeStatusTipHtml)}">
                        ${typeBadge}
                        ${statusBadge}
                    </div>
                </td>
                <td style="text-align: right;">
                    <span style="font-weight:800;color:${amountColor};font-size:13.5px;white-space:nowrap;cursor:pointer;" data-tooltip="${escAttr(amountTipHtml)}">
                        ${amountSign}${cfg.currency} ${formatNumber(amountVal)}
                    </span>
                </td>
                <td>${tagChips || '<span style="color:#94a3b8;font-size:12px;">—</span>'}</td>
                <td style="text-align: center;">${receiptIcon}</td>
            </tr>`;

            // 2. Fila Hija Expandible
            const childRow = `<tr class="aura-child-row" data-child-for="${rowId}">
                <td colspan="8">
                    <div class="aura-child-card">
                        <div class="aura-child-hero-bar">
                            <div class="aura-child-hero-left">
                                <span class="aura-type-pill" style="background:${amountColor}15;color:${amountColor};font-weight:900;">#${row.id}</span>
                                <div>
                                    <h4 class="aura-child-hero-title">${escHtml(row.description || __('Detalle de Transacción', 'aura-suite'))}</h4>
                                    <div style="font-size:11.5px;color:#64748b;margin-top:2px;">${__('Fecha:', 'aura-suite')} ${escHtml(row.transaction_date || '')} · ${__('Categoría:', 'aura-suite')} ${catName}</div>
                                </div>
                            </div>
                            <div class="aura-child-hero-right" style="display:flex;align-items:center;gap:8px;">
                                ${typeBadge}
                                ${statusBadge}
                                <span style="font-size:15px;font-weight:900;color:${amountColor};">
                                    ${amountSign}${cfg.currency} ${formatNumber(amountVal)}
                                </span>
                            </div>
                        </div>
                        <div class="aura-child-sections-grid">
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-money-alt"></span>${__('Forma de Pago y Bancos', 'aura-suite')}</h5>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Método de Pago:', 'aura-suite')}</span><span class="aura-child-meta-val">${formatPaymentMethod(row.payment_method)}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Referencia / Folio:', 'aura-suite')}</span><span class="aura-child-meta-val">${escHtml(row.reference_number || '—')}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Beneficiario/Pagador:', 'aura-suite')}</span><span class="aura-child-meta-val">${escHtml(row.recipient_payer || '—')}</span></div>
                            </div>
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-admin-users"></span>${__('Auditoría y Trazabilidad', 'aura-suite')}</h5>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Registrado por:', 'aura-suite')}</span><span class="aura-child-meta-val">${escHtml(row.created_by_name || __('Sistema', 'aura-suite'))}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Aprobado en:', 'aura-suite')}</span><span class="aura-child-meta-val">${escHtml(row.approved_at || '—')}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Módulo Origen:', 'aura-suite')}</span><span class="aura-child-meta-val">${formatRelatedModule(row.related_module)}</span></div>
                            </div>
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-tag"></span>${__('Etiquetas y Notas', 'aura-suite')}</h5>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Etiquetas:', 'aura-suite')}</span><span class="aura-child-meta-val">${tagChips || __('Ninguna', 'aura-suite')}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">${__('Notas Adicionales:', 'aura-suite')}</span><span class="aura-child-meta-val">${escHtml(row.notes || __('Sin observaciones', 'aura-suite'))}</span></div>
                                ${row.rejection_reason ? `<div class="aura-child-meta-item"><span class="aura-child-meta-label" style="color:#dc2626;">${__('Motivo Rechazo:', 'aura-suite')}</span><span class="aura-child-meta-val" style="color:#dc2626;">${escHtml(row.rejection_reason)}</span></div>` : ''}
                            </div>
                            ${(function() {
                                if (!row.receipt_file) return '';
                                const isDrive = (row.receipt_file.indexOf('drive.google') !== -1);
                                const isPdf = /\.pdf($|\?)/i.test(row.receipt_file);
                                let previewUrl = row.receipt_file;
                                let thumbUrl = row.receipt_file;
                                let downloadUrl = row.receipt_file;
                                if (isDrive) {
                                    const match = row.receipt_file.match(/\/d\/([a-zA-Z0-9_-]+)/);
                                    if (match && match[1]) {
                                        previewUrl = 'https://drive.google.com/file/d/' + match[1] + '/preview';
                                        thumbUrl = 'https://drive.google.com/thumbnail?id=' + match[1] + '&sz=w1600';
                                        downloadUrl = 'https://drive.google.com/uc?export=download&id=' + match[1];
                                    }
                                }
                                return `<div class="aura-child-section">
                                    <h5 class="aura-child-section-title"><span class="dashicons ${isDrive ? 'dashicons-cloud' : 'dashicons-media-document'}"></span>${__('Comprobante', 'aura-suite')}</h5>
                                    <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
                                        ${!isPdf ? `<img src="${escAttr(thumbUrl)}" alt="${__('Recibo', 'aura-suite')}" class="aura-open-receipt-viewer" data-receipt-url="${escAttr(row.receipt_file)}" data-preview-url="${escAttr(previewUrl)}" data-download-url="${escAttr(downloadUrl)}" data-is-drive="${isDrive ? '1' : '0'}" data-is-pdf="0" data-is-img="1" data-title="${sprintf(__('Transacción #%s - Comprobante', 'aura-suite'), row.id)}" style="width:40px;height:40px;border-radius:6px;object-fit:cover;cursor:pointer;border:1px solid #cbd5e1;" title="${__('Haz clic para ampliar', 'aura-suite')}">` : ''}
                                        <div>
                                            <button type="button" class="button button-small aura-open-receipt-viewer" data-receipt-url="${escAttr(row.receipt_file)}" data-preview-url="${escAttr(previewUrl)}" data-download-url="${escAttr(downloadUrl)}" data-is-drive="${isDrive ? '1' : '0'}" data-is-pdf="${isPdf ? '1' : '0'}" data-is-img="${!isPdf ? '1' : '0'}" data-title="${sprintf(__('Transacción #%s - Comprobante', 'aura-suite'), row.id)}">
                                                <span class="dashicons dashicons-visibility"></span> ${__('Ver en Pantalla Completa', 'aura-suite')}
                                            </button>
                                            <div style="font-size:11px;color:#64748b;margin-top:3px;">${isDrive ? __('Almacenado en Google Drive', 'aura-suite') : __('Almacenamiento Local', 'aura-suite')}</div>
                                        </div>
                                    </div>
                                </div>`;
                            })()}
                        </div>
                    </div>
                </td>
            </tr>`;

            htmlRows.push(parentRow);
            htmlRows.push(childRow);
        });

        $body.html(htmlRows.join(''));
    }

    /* ============================================================
       Estadísticas y KPIs
       ============================================================ */
    function renderStats(total, totalAmount) {
        $('#kpi-search-count').text(new Intl.NumberFormat().format(total));
        $('#kpi-search-amount').text(cfg.currency + ' ' + formatNumber(totalAmount));
        $('#search-stats').css('display', 'flex');
    }

    /* ============================================================
       Paginación
       ============================================================ */
    function renderPagination(page, pages) {
        const $pag = $('#search-pagination');
        if (pages <= 1) { $pag.hide(); return; }

        let html = '';
        if (page > 1) {
            html += `<button type="button" class="page-btn aura-btn aura-btn-secondary" data-page="${page - 1}" style="font-size:12px;padding:4px 10px;">&laquo; ${__('Anterior', 'aura-suite')}</button>`;
        }

        const startP = Math.max(1, page - 2);
        const endP   = Math.min(pages, page + 2);

        for (let i = startP; i <= endP; i++) {
            const active = i === page ? 'aura-btn-primary' : 'aura-btn-secondary';
            html += `<button type="button" class="page-btn aura-btn ${active}" data-page="${i}" style="font-size:12px;padding:4px 10px;min-width:32px;">${i}</button>`;
        }

        if (page < pages) {
            html += `<button type="button" class="page-btn aura-btn aura-btn-secondary" data-page="${page + 1}" style="font-size:12px;padding:4px 10px;">${__('Siguiente', 'aura-suite')} &raquo;</button>`;
        }

        $pag.html(html).css('display', 'flex');
    }

    /* ============================================================
       Exportar resultados a CSV
       ============================================================ */
    function exportResults() {
        if (!lastResults || !lastResults.length) {
            alert(__('No hay resultados para exportar.', 'aura-suite'));
            return;
        }

        const rows = [];
        const headers = [__('ID', 'aura-suite'), __('Fecha', 'aura-suite'), __('Concepto', 'aura-suite'), __('Notas', 'aura-suite'), __('Categoría', 'aura-suite'), __('Tipo', 'aura-suite'), __('Monto', 'aura-suite'), __('Moneda', 'aura-suite'), __('Estado', 'aura-suite'), __('Método', 'aura-suite'), __('Beneficiario', 'aura-suite'), __('Referencia', 'aura-suite'), __('Etiquetas', 'aura-suite'), __('Creado Por', 'aura-suite')];
        rows.push(headers.join(','));

        lastResults.forEach(function(r) {
            let tipoLabel = cfg.i18n.expense;
            if (r.transaction_type === 'income') tipoLabel = cfg.i18n.income;
            if (r.transaction_type === 'capital') tipoLabel = cfg.i18n.capital || __('Gasto de Capital', 'aura-suite');

            const rowData = [
                r.id,
                r.transaction_date || '',
                '"' + (r.description || '').replace(/"/g, '""') + '"',
                '"' + (r.notes || '').replace(/"/g, '""') + '"',
                '"' + (r.category_name || '').replace(/"/g, '""') + '"',
                tipoLabel,
                parseFloat(r.amount || 0).toFixed(2),
                cfg.currency,
                cfg.i18n[r.status] || r.status || '',
                '"' + formatPaymentMethod(r.payment_method).replace(/"/g, '""') + '"',
                '"' + (r.recipient_payer || '').replace(/"/g, '""') + '"',
                '"' + (r.reference_number || '').replace(/"/g, '""') + '"',
                '"' + (r.tags || '').replace(/"/g, '""') + '"',
                '"' + (r.created_by_name || '').replace(/"/g, '""') + '"'
            ];
            rows.push(rowData.join(','));
        });

        const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'auditoria-aura-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    /* ============================================================
       Mostrar estado del panel de resultados
       ============================================================ */
    function showState(state) {
        $('#search-initial, #search-loading, #search-empty, #search-results-table, #search-pagination').hide();
        if (state === 'loading') {
            $('#search-loading').show();
        } else if (state === 'empty') {
            $('#search-empty').show();
        } else if (state === 'results') {
            $('#search-results-table').show();
            if (totalPages > 1) $('#search-pagination').show();
        }
    }

    /* ============================================================
       Toast notification
       ============================================================ */
    function showToast(msg, type) {
        const isSuccess = type === 'success';
        const bg = isSuccess ? '#ecfdf5' : '#fef2f2';
        const border = isSuccess ? '#10b981' : '#ef4444';
        const color = isSuccess ? '#065f46' : '#991b1b';

        const $t = $('<div>').css({
            position: 'fixed', bottom: '24px', right: '24px',
            background: bg, borderLeft: '4px solid ' + border,
            color: color, padding: '12px 18px',
            borderRadius: '8px', boxShadow: '0 10px 25px rgba(0,0,0,0.15)',
            zIndex: 999999, fontSize: '13.5px', fontWeight: '700',
            display: 'flex', alignItems: 'center', gap: '8px'
        }).html(`<span class="dashicons ${isSuccess ? 'dashicons-yes-alt' : 'dashicons-warning'}"></span><span>${escHtml(msg)}</span>`);

        $('body').append($t);
        setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, 4000);
    }

    /* ============================================================
       Utils
       ============================================================ */
    function formatNumber(n) {
        return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return String(str || '')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatPaymentMethod(m) {
        if (!m) return __('Efectivo', 'aura-suite');
        const raw = String(m).toLowerCase().trim();
        const map = {
            'cash': __('Efectivo', 'aura-suite'),
            'efectivo': __('Efectivo', 'aura-suite'),
            'transfer': __('Transferencia Bancaria', 'aura-suite'),
            'bank_transfer': __('Transferencia Bancaria', 'aura-suite'),
            'transferencia': __('Transferencia Bancaria', 'aura-suite'),
            'card': __('Tarjeta (Débito / Crédito)', 'aura-suite'),
            'credit_card': __('Tarjeta de Crédito', 'aura-suite'),
            'debit_card': __('Tarjeta de Débito', 'aura-suite'),
            'tarjeta': __('Tarjeta (Débito / Crédito)', 'aura-suite'),
            'check': __('Cheque', 'aura-suite'),
            'cheque': __('Cheque', 'aura-suite'),
            'digital_wallet': __('Billetera Digital', 'aura-suite'),
            'wallet': __('Billetera Digital', 'aura-suite'),
            'crypto': __('Criptomoneda', 'aura-suite'),
            'stripe': 'Stripe',
            'paypal': 'PayPal',
            'other': __('Otro / Varios', 'aura-suite'),
            'otro': __('Otro / Varios', 'aura-suite')
        };
        return map[raw] || escHtml(m);
    }

    function formatRelatedModule(mod) {
        if (!mod) return __('Finanzas Directas', 'aura-suite');
        const raw = String(mod).toLowerCase().trim();
        const map = {
            'finance': __('Finanzas Directas', 'aura-suite'),
            'financial': __('Finanzas Directas', 'aura-suite'),
            'finanzas': __('Finanzas Directas', 'aura-suite'),
            'inventory': __('Inventario y Mantenimiento', 'aura-suite'),
            'inventario': __('Inventario y Mantenimiento', 'aura-suite'),
            'students': __('Estudiantes y Colegiaturas', 'aura-suite'),
            'estudiantes': __('Estudiantes y Colegiaturas', 'aura-suite'),
            'fleet': __('Vehículos y Flota', 'aura-suite'),
            'vehiculos': __('Vehículos y Flota', 'aura-suite'),
            'certificates': __('Certificados', 'aura-suite'),
            'certificados': __('Certificados', 'aura-suite'),
            'library': __('Biblioteca', 'aura-suite'),
            'biblioteca': __('Biblioteca', 'aura-suite'),
            'direct': __('Movimiento Manual Directo', 'aura-suite'),
            'manual': __('Registro Manual', 'aura-suite')
        };
        return map[raw] || escHtml(mod);
    }

})(jQuery);
