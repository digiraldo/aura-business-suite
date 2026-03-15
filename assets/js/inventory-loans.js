/**
 * Aura Business Suite — Inventory Loans JS (FASE 5)
 * Checkout / Checkin de equipos con modales, filtros y paginación
 */
/* global jQuery, auraInventoryLoans */
(function ($) {
    'use strict';

    var cfg;
    var currentPage    = 1;
    var currentFilters = {};
    var searchTimer    = null;

    // ─── Init ────────────────────────────────────────────────
    function init() {
        if (typeof auraInventoryLoans === 'undefined') return;
        cfg = auraInventoryLoans;

        bindFilters();
        bindCheckoutModal();
        bindCheckinModal();
        loadLoans(1);
    }

    // ─── AJAX helper ────────────────────────────────────────
    function doAjax(action, data, success, error) {
        $.post(cfg.ajaxurl, $.extend({ action: action, nonce: cfg.nonce }, data))
            .done(function (res) {
                if (res.success) {
                    if (typeof success === 'function') success(res.data);
                } else {
                    if (typeof error === 'function') error(res.data ? (res.data.message || res.data) : 'Error');
                }
            })
            .fail(function () {
                if (typeof error === 'function') error('Error de conexión.');
            });
    }

    // ─── Filters ────────────────────────────────────────────
    function bindFilters() {
        $('#js-loan-filter-btn').on('click', function () { loadLoans(1); });
        $('#js-loan-clear-btn').on('click', function () {
            $('#js-loan-search, #js-loan-date-from, #js-loan-date-to').val('');
            $('#js-loan-status').val('');
            loadLoans(1);
        });
        $('#js-loan-search').on('keydown', function (e) {
            if (e.key === 'Enter') loadLoans(1);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { loadLoans(1); }, 600);
        });
        $('#js-loan-status').on('change', function () { loadLoans(1); });
    }

    function getFilters() {
        return {
            search:      $('#js-loan-search').val(),
            loan_status: $('#js-loan-status').val(),
            date_from:   $('#js-loan-date-from').val(),
            date_to:     $('#js-loan-date-to').val(),
        };
    }

    // ─── Load Loans ─────────────────────────────────────────
    function loadLoans(page) {
        currentPage    = page;
        currentFilters = getFilters();

        var $loading = $('#js-loans-loading').show();
        var $table   = $('#js-loans-table').hide();
        var $empty   = $('#js-loans-empty').hide();
        $('#js-loans-pagination').empty();

        doAjax(
            'aura_inventory_get_loans_list',
            $.extend({ page: page, per_page: 20 }, currentFilters),
            function (data) {
                $loading.hide();
                if (!data.items || !data.items.length) {
                    $empty.show();
                    return;
                }
                renderLoansTable(data.items);
                $table.show();
                renderPagination(data);
            },
            function (msg) {
                $loading.hide();
                $empty.text(msg).show();
            }
        );
    }

    // ─── Render table ────────────────────────────────────────
    function renderLoansTable(items) {
        var $tbody = $('#js-loans-tbody').empty();
        var txt    = cfg.txt || {};

        $.each(items, function (i, item) {
            var status      = item.loan_status;
            var statusLabel = { active: txt.active, overdue: txt.overdue, returned: txt.returned }[status] || status;
            var badgeClass  = 'aura-loan-status-' + status;

            var overdueHtml = '';
            if (status === 'overdue' && item.days_overdue > 0) {
                overdueHtml = '<span class="aura-loan-overdue-days">+' + item.days_overdue + ' ' + (txt.days_ago || 'días') + '</span>';
            }

            var projectHtml = item.project
                ? '<span title="' + escHtml(item.project) + '">' + escHtml(item.project).substring(0, 30) + (item.project.length > 30 ? '…' : '') + '</span>'
                : '<span style="color:#8c8f94">-</span>';

            var returnDateHtml = item.actual_return_date
                ? '<span style="color:#00a32a;">' + item.actual_return_date + '</span>'
                : item.expected_return_date + overdueHtml;

            var actions = buildActionButtons(item, status);

            var tr = '<tr data-loan-id="' + item.id + '">' +
                '<td style="width:54px;text-align:center;">' +
                    (item.equipment_photo_thumb
                        ? '<img src="' + escHtml(item.equipment_photo_thumb) + '" alt="" width="44" height="33"' +
                          ' style="border-radius:3px;object-fit:cover;display:block;border:1px solid #dcdcde;">'
                        : '<span class="dashicons dashicons-format-image" style="color:#c3c4c7;font-size:24px;" title="Sin foto"></span>'
                    ) +
                '</td>' +
                '<td class="aura-loan-equip-cell">' +
                    '<strong>' + escHtml(item.equipment_name || '-') + '</strong>' +
                    (item.equipment_brand ? '<small>' + escHtml(item.equipment_brand) + '</small>' : '') +
                '</td>' +
                '<td>' + buildBorrowerCell(item) + '</td>' +
                '<td>' + projectHtml + '</td>' +
                '<td>' + item.loan_date + '</td>' +
                '<td>' + returnDateHtml + '</td>' +
                '<td><span class="aura-loan-status-badge ' + badgeClass + '">' + escHtml(statusLabel) + '</span></td>' +
                '<td>' + actions + '</td>' +
                '</tr>';

            $tbody.append(tr);
        });

        bindTableActions();
    }

    // Construye la celda prestatario con avatar o iniciales
    function buildBorrowerCell(item) {
        var name = item.borrower_display || '-';
        var avatarHtml = '';

        if (item.borrower_avatar) {
            // Usuario WP registrado → muestra su foto de perfil
            avatarHtml = '<img class="aura-loan-borrower-avatar" src="' + escHtml(item.borrower_avatar) + '" alt="" width="32" height="32">';
        } else {
            // Externo o usuario sin foto → círculo con iniciales
            var initials = name
                .split(' ')
                .filter(Boolean)
                .slice(0, 2)
                .map(function (w) { return (w[0] || '').toUpperCase(); })
                .join('');
            if (!initials) initials = '?';
            avatarHtml = '<span class="aura-loan-borrower-initials">' + escHtml(initials) + '</span>';
        }

        return '<div class="aura-loan-borrower-cell">' + avatarHtml +
               '<span class="aura-loan-borrower-name">' + escHtml(name) + '</span></div>';
    }

    function buildActionButtons(item, status) {
        var html = '<div class="aura-loan-action-wrap">';

        // Checkin — solo para préstamos no devueltos
        if (cfg.can_checkin && status !== 'returned') {
            html += '<button type="button" class="aura-loan-action-btn checkin js-btn-checkin"' +
                    ' data-loan-id="' + item.id + '" title="' + (cfg.txt.checkin || 'Devolver') + '">' +
                    '<span class="dashicons dashicons-yes-alt"></span>' +
                    '</button>';
        }

        // Historial
        html += '<button type="button" class="aura-loan-action-btn js-btn-history"' +
                ' data-equipment-id="' + item.equipment_id + '"' +
                ' data-equipment-name="' + escHtml(item.equipment_name || '') + '"' +
                ' title="' + (cfg.txt.history || 'Historial') + '">' +
                '<span class="dashicons dashicons-list-view"></span>' +
                '</button>';

        // Editar préstamo
        if (cfg.can_loan_edit || (item.can_edit)) {
            html += '<button type="button" class="aura-loan-action-btn js-btn-edit-loan"' +
                    ' data-loan-id="' + item.id + '"' +
                    ' data-loan-date="' + escHtml(item.loan_date || '') + '"' +
                    ' data-expected-return="' + escHtml(item.expected_return_date || '') + '"' +
                    ' data-borrowed-name="' + escHtml(item.borrowed_to_name || '') + '"' +
                    ' data-borrowed-phone="' + escHtml(item.borrowed_to_phone || '') + '"' +
                    ' data-project="' + escHtml(item.project || '') + '"' +
                    ' data-equipment-name="' + escHtml(item.equipment_name || '') + '"' +
                    ' title="' + (cfg.txt.edit_loan || 'Editar') + '">' +
                    '<span class="dashicons dashicons-edit"></span>' +
                    '</button>';
        }

        // Eliminar préstamo
        if (cfg.can_loan_delete || (item.can_delete)) {
            html += '<button type="button" class="aura-loan-action-btn aura-loan-btn-danger js-btn-delete-loan"' +
                    ' data-loan-id="' + item.id + '"' +
                    ' data-equipment-name="' + escHtml(item.equipment_name || '') + '"' +
                    ' title="' + (cfg.txt.delete_loan || 'Eliminar') + '">' +
                    '<span class="dashicons dashicons-trash"></span>' +
                    '</button>';
        }

        html += '</div>';
        return html;
    }

    function bindTableActions() {
        // Checkin
        $(document).off('click', '.js-btn-checkin').on('click', '.js-btn-checkin', function () {
            var loanId = $(this).data('loan-id');
            openCheckinModal(loanId);
        });
        // Historial
        $(document).off('click', '.js-btn-history').on('click', '.js-btn-history', function () {
            var eqId   = $(this).data('equipment-id');
            var eqName = $(this).data('equipment-name');
            openHistoryModal(eqId, eqName);
        });
        // Editar préstamo
        $(document).off('click', '.js-btn-edit-loan').on('click', '.js-btn-edit-loan', function () {
            var $btn = $(this);
            openEditLoanModal({
                loan_id:              $btn.data('loan-id'),
                equipment_name:       $btn.data('equipment-name'),
                loan_date:            $btn.data('loan-date'),
                expected_return_date: $btn.data('expected-return'),
                borrowed_to_name:     $btn.data('borrowed-name'),
                borrowed_to_phone:    $btn.data('borrowed-phone'),
                project:              $btn.data('project')
            });
        });
        // Eliminar préstamo
        $(document).off('click', '.js-btn-delete-loan').on('click', '.js-btn-delete-loan', function () {
            var loanId = $(this).data('loan-id');
            var eqName = $(this).data('equipment-name');
            deleteLoan(loanId, eqName);
        });
    }

    // ─── Pagination ──────────────────────────────────────────
    function renderPagination(data) {
        if (data.total_pages <= 1) return;
        var $pg = $('#js-loans-pagination');
        var txt = cfg.txt || {};

        $pg.append(
            $('<button>').text('‹').prop('disabled', data.page <= 1).on('click', function () {
                loadLoans(data.page - 1);
            })
        );

        for (var p = 1; p <= data.total_pages; p++) {
            (function (page) {
                var $btn = $('<button>').text(page).toggleClass('active', page === data.page)
                    .on('click', function () { loadLoans(page); });
                $pg.append($btn);
            }(p));
        }

        $pg.append(
            $('<button>').text('›').prop('disabled', data.page >= data.total_pages).on('click', function () {
                loadLoans(data.page + 1);
            })
        );

        $pg.append(
            $('<span class="aura-loan-pg-info">').text(
                data.page + ' / ' + data.total_pages + ' (' + data.total + ' préstamos)'
            )
        );
    }

    // ─── EDIT LOAN MODAL ─────────────────────────────────────
    function openEditLoanModal(item) {
        $('#el-loan-id').val(item.loan_id);
        $('#js-el-equip-name').text(item.equipment_name || '');
        $('#el-loan-date').val(item.loan_date || '');
        $('#el-expected-return').val(item.expected_return_date || '');
        $('#el-borrowed-name').val(item.borrowed_to_name || '');
        $('#el-borrowed-phone').val(item.borrowed_to_phone || '');
        $('#el-project').val(item.project || '');
        $('#js-el-feedback').hide().find('p').text('');
        $('#js-modal-edit-loan').show();
        $('#js-overlay').show();
    }

    $(document).on('click', '.js-close-edit-loan', function () {
        $('#js-modal-edit-loan').hide();
        $('#js-overlay').hide();
    });

    $(document).on('submit', '#js-form-edit-loan', function (e) {
        e.preventDefault();
        var $submit = $('#js-el-submit').prop('disabled', true);
        $submit.find('.spinner').addClass('is-active');
        $('#js-el-feedback').hide();

        doAjax(
            'aura_inventory_update_loan',
            {
                loan_id:              $('#el-loan-id').val(),
                loan_date:            $('#el-loan-date').val(),
                expected_return_date: $('#el-expected-return').val(),
                borrowed_to_name:     $('#el-borrowed-name').val(),
                borrowed_to_phone:    $('#el-borrowed-phone').val(),
                project:              $('#el-project').val(),
            },
            function () {
                $submit.prop('disabled', false).find('.spinner').removeClass('is-active');
                $('#js-modal-edit-loan').hide();
                $('#js-overlay').hide();
                loadLoans(currentPage);
            },
            function (msg) {
                $submit.prop('disabled', false).find('.spinner').removeClass('is-active');
                $('#js-el-feedback').show().find('p').text(msg || 'Error al guardar.');
            }
        );
    });

    // ─── DELETE LOAN ─────────────────────────────────────────
    function deleteLoan(loanId, eqName) {
        var msg = (cfg.txt.confirm_delete_loan || '¿Eliminar este préstamo?');
        if (eqName) msg = '«' + eqName + '» — ' + msg;
        if (!window.confirm(msg)) return;

        doAjax(
            'aura_inventory_delete_loan',
            { loan_id: loanId },
            function () { loadLoans(currentPage); },
            function (err) { window.alert(err || 'Error al eliminar.'); }
        );
    }

    // ─── CHECKOUT MODAL ──────────────────────────────────────
    var equipListCache = null; // cache de equipos disponibles para el modo listado

    function bindCheckoutModal() {
        // Abrir
        $(document).on('click', '#js-open-checkout', openCheckoutModal);

        // Cerrar
        $(document).on('click', '.aura-loan-modal-close, #js-overlay', function () {
            closeAllModals();
        });

        // ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeAllModals();
        });

        // ── Tabs: Buscar / Ver listado ────────────────────────
        $(document).on('click', '.aura-loan-equip-tab', function () {
            var mode = $(this).data('mode');
            $('.aura-loan-equip-tab').removeClass('active');
            $(this).addClass('active');

            if (mode === 'search') {
                $('#co-equip-mode-list').hide();
                $('#co-equip-mode-search').show();
                $('#co-equipment-search').focus();
            } else {
                $('#co-equip-mode-search').hide();
                $('#co-equip-results').hide().empty();
                $('#co-equip-mode-list').show();
                loadEquipList();
            }
        });

        // ── Filtro en tiempo real del listado ─────────────────
        var listFilterTimer;
        $(document).on('input', '#co-equip-list-filter', function () {
            clearTimeout(listFilterTimer);
            var q = $(this).val().trim().toLowerCase();
            listFilterTimer = setTimeout(function () {
                if (!equipListCache) return;
                var filtered = q.length < 1
                    ? equipListCache
                    : equipListCache.filter(function (it) {
                        return (it.name + ' ' + (it.brand || '') + ' ' + (it.internal_code || ''))
                               .toLowerCase().indexOf(q) !== -1;
                    });
                renderEquipList(filtered);
            }, 250);
        });

        // ── Seleccionar equipo desde el listado ───────────────
        $(document).on('click', '.aura-loan-equip-list-card', function () {
            selectEquip(
                $(this).data('id'),
                $(this).data('name'),
                $(this).data('meta')
            );
        });

        // ── Búsqueda de equipos disponibles (autocomplete) ────
        var equipTimer;
        $(document).on('input', '#co-equipment-search', function () {
            clearTimeout(equipTimer);
            var q = $(this).val().trim();
            if (q.length < 2) {
                $('#co-equip-results').hide().empty();
                return;
            }
            equipTimer = setTimeout(function () {
                doAjax('aura_inventory_get_available_equipment', { search: q }, function (items) {
                    renderEquipResults(items);
                });
            }, 350);
        });

        // ── Seleccionar equipo de los resultados autocomplete ──
        $(document).on('click', '.aura-loan-equip-result-item', function () {
            selectEquip(
                $(this).data('id'),
                $(this).find('.equip-res-name').text(),
                $(this).find('.equip-res-meta').text()
            );
        });

        // ── Cambiar equipo seleccionado ───────────────────────
        $(document).on('click', '.selected-clear', function () {
            $('#co-equipment-id').val('');
            $('#co-equip-selected').hide().empty();
            // Reactivar la pestaña activa
            var activeMode = $('.aura-loan-equip-tab.active').data('mode') || 'search';
            if (activeMode === 'search') {
                $('#co-equip-mode-search').show();
                $('#co-equipment-search').val('').focus();
            } else {
                $('#co-equip-mode-list').show();
                $('#co-equip-list-filter').val('');
                if (equipListCache) renderEquipList(equipListCache);
            }
        });

        // ── Submit checkout ───────────────────────────────────
        $(document).on('submit', '#js-form-checkout', function (e) {
            e.preventDefault();
            submitCheckout();
        });

        // ── expected_return mínimo = loan_date ────────────────
        $(document).on('change', '#co-loan-date', function () {
            $('#co-expected-return').attr('min', $(this).val());
        });
    }

    // Selección común para ambos modos
    function selectEquip(id, name, meta) {
        $('#co-equipment-id').val(id);
        $('#co-equipment-search').val('');
        $('#co-equip-results').hide().empty();
        $('#co-equip-mode-search').hide();
        $('#co-equip-mode-list').hide();
        $('#co-equip-selected')
            .html('<span class="selected-name">' + escHtml(name) + '</span>' +
                  '<small style="color:#646970;">' + escHtml(meta || '') + '</small>' +
                  '<span class="selected-clear dashicons dashicons-no-alt" title="Cambiar equipo"></span>')
            .show();
    }

    function loadEquipList() {
        if (equipListCache) {
            renderEquipList(equipListCache);
            return;
        }
        $('#co-equip-list-loading').show();
        $('#co-equip-list-items').empty();
        doAjax('aura_inventory_get_available_equipment', { search: '' }, function (items) {
            equipListCache = items;
            $('#co-equip-list-loading').hide();
            renderEquipList(items);
        }, function () {
            $('#co-equip-list-loading').hide();
            $('#co-equip-list-items').html('<p style="color:#d63638;padding:8px 0;">Error al cargar equipos.</p>');
        });
    }

    function renderEquipList(items) {
        var $grid = $('#co-equip-list-items').empty();
        if (!items.length) {
            $grid.html('<p style="color:#646970;padding:8px 0;">Sin equipos disponibles.</p>');
            return;
        }
        $.each(items, function (i, item) {
            var meta = [item.brand, item.model, item.internal_code].filter(Boolean).join(' · ');
            $grid.append(
                $('<div class="aura-loan-equip-list-card">')
                    .attr({ 'data-id': item.id, 'data-name': item.name, 'data-meta': meta })
                    .html('<span class="equip-card-name">' + escHtml(item.name) + '</span>' +
                          (meta ? '<span class="equip-card-meta">' + escHtml(meta) + '</span>' : '') +
                          (item.category ? '<span class="equip-card-cat">' + escHtml(item.category) + '</span>' : ''))
            );
        });
    }

    function openCheckoutModal() {
        equipListCache = null; // refrescar al abrir
        resetCheckoutForm();
        $('#js-modal-checkout, #js-overlay').show();
        setTimeout(function () { $('#co-equipment-search').focus(); }, 100);
    }

    function resetCheckoutForm() {
        var $form = $('#js-form-checkout');
        $form[0].reset();
        $('#co-loan-date').val(cfg.today);
        $('#co-expected-return').val('').attr('min', cfg.today);
        $('#co-equipment-id').val('');
        $('#co-equip-selected').hide().empty();
        // Volver al modo búsqueda
        $('.aura-loan-equip-tab').removeClass('active');
        $('.aura-loan-equip-tab[data-mode="search"]').addClass('active');
        $('#co-equip-mode-search').show();
        $('#co-equip-mode-list').hide();
        $('#co-equipment-search').val('');
        $('#co-equip-results').hide().empty();
        $('#co-equip-list-filter').val('');
        $('#co-equip-list-items').empty();
        $('#js-checkout-msg').hide().removeClass('success error').text('');
    }

    function renderEquipResults(items) {
        var $res = $('#co-equip-results');
        $res.empty();
        if (!items.length) {
            $res.html('<div style="padding:10px 12px;color:#646970;font-size:13px;">' +
                      (cfg.txt.no_loans || 'Sin equipos disponibles') + '</div>').show();
            return;
        }
        $.each(items, function (i, item) {
            var meta = [item.brand, item.model, item.internal_code].filter(Boolean).join(' · ');
            $res.append(
                $('<div class="aura-loan-equip-result-item">')
                    .data('id', item.id)
                    .html('<span class="equip-res-name">' + escHtml(item.name) + '</span>' +
                          '<span class="equip-res-meta">' + escHtml(meta) + '</span>')
            );
        });
        $res.show();
    }

    function submitCheckout() {
        var $btn = $('#js-checkout-submit').prop('disabled', true);
        var $msg = $('#js-checkout-msg').hide().removeClass('success error');
        var equipId = $('#co-equipment-id').val();

        if (!equipId) {
            showModalMsg($msg, 'error', cfg.txt.select_equip || 'Selecciona un equipo primero.');
            $btn.prop('disabled', false);
            return;
        }

        var data = {
            equipment_id:         equipId,
            loan_date:            $('#co-loan-date').val(),
            expected_return_date: $('#co-expected-return').val(),
            borrowed_by_user_id:  $('#co-borrower-user').val(),
            borrowed_to_name:     $('#co-borrower-name').val(),
            project:              $('#co-project').val(),
            equipment_state_out:  $('input[name="equipment_state_out"]:checked').val() || 'good',
        };

        doAjax('aura_inventory_checkout_equipment', data,
            function (res) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'success', res.message || '✅ Préstamo registrado.');
                setTimeout(function () {
                    closeAllModals();
                    loadLoans(1);
                }, 1500);
            },
            function (errMsg) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'error', errMsg);
            }
        );
    }

    // ─── CHECKIN MODAL ───────────────────────────────────────
    function bindCheckinModal() {
        // Mostrar advertencia de daño
        $(document).on('change', 'input[name="return_state"]', function () {
            var val = $(this).val();
            $('#ci-damaged-warning').toggle(val === 'damaged');
        });

        // Submit checkin
        $(document).on('submit', '#js-form-checkin', function (e) {
            e.preventDefault();
            submitCheckin();
        });
    }

    function openCheckinModal(loanId) {
        var $modal = $('#js-modal-checkin');
        var $msg   = $('#js-checkin-msg').hide().removeClass('success error');
        var $summ  = $('#ci-summary').html('<span class="spinner is-active" style="float:none;"></span>');

        $('#ci-loan-id').val(loanId);
        $('#ci-return-date').val(cfg.today);
        $('input[name="return_state"][value="good"]').prop('checked', true);
        $('#ci-req-maint').prop('checked', false);
        $('#ci-hours').val('');
        $('#ci-observations').val('');
        $('#ci-damaged-warning').hide();
        $msg.hide();

        $modal.show();
        $('#js-overlay').show();

        doAjax('aura_inventory_get_loan_detail', { loan_id: loanId }, function (loan) {
            var today   = cfg.today;
            var overdue = '';
            if (!loan.actual_return_date && loan.expected_return_date < today) {
                var days = Math.round((new Date(today) - new Date(loan.expected_return_date)) / 86400000);
                overdue  = ' <span class="sum-overdue">(+' + days + ' ' + (cfg.txt.days || 'días') + ' de retraso)</span>';
            }

            $summ.html(
                '<div class="sum-row"><span class="sum-label">Equipo</span>' +
                '<span class="sum-value sum-value-equip">' + escHtml((loan.equipment_name || '') + (loan.equipment_brand ? ' ' + loan.equipment_brand : '')) + '</span></div>' +
                '<div class="sum-row"><span class="sum-label">Prestatario</span>' +
                '<span class="sum-value">' + buildBorrowerCell(loan) + '</span></div>' +
                '<div class="sum-row"><span class="sum-label">Fecha salida</span>' +
                '<span class="sum-value">' + escHtml(loan.loan_date || '-') + '</span></div>' +
                '<div class="sum-row"><span class="sum-label">Devolución esperada</span>' +
                '<span class="sum-value">' + escHtml(loan.expected_return_date || '-') + overdue + '</span></div>'
            );
        }, function () {
            $summ.html('<p style="color:#d63638;">Error al cargar el préstamo.</p>');
        });
    }

    function submitCheckin() {
        var $btn = $('#js-checkin-submit').prop('disabled', true);
        var $msg = $('#js-checkin-msg').hide().removeClass('success error');

        var data = {
            loan_id:                     $('#ci-loan-id').val(),
            actual_return_date:          $('#ci-return-date').val(),
            return_state:                $('input[name="return_state"]:checked').val() || 'good',
            hours_used:                  $('#ci-hours').val(),
            requires_maintenance_after:  $('#ci-req-maint').is(':checked') ? 1 : 0,
            return_observations:         $('#ci-observations').val(),
        };

        doAjax('aura_inventory_checkin_equipment', data,
            function (res) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'success', res.message || '✅ Devolución registrada.');
                setTimeout(function () {
                    closeAllModals();
                    loadLoans(currentPage);
                }, 1500);
            },
            function (errMsg) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'error', errMsg);
            }
        );
    }

    // ─── HISTORY MODAL ───────────────────────────────────────
    function openHistoryModal(equipmentId, equipmentName) {
        var $modal = $('#js-modal-history');
        var $body  = $('#js-history-body').html(
            '<p class="aura-loan-history-loading"><span class="spinner is-active"></span>Cargando historial…</p>'
        );
        $('#js-history-title').text('Historial de préstamos: ' + equipmentName);
        $('#js-history-meta').text('');

        $modal.show();
        $('#js-overlay').show();

        doAjax('aura_inventory_get_equipment_loan_history', { equipment_id: equipmentId }, function (items) {
            if (!items || !items.length) {
                $body.html(
                    '<div class="aura-loan-history-empty">' +
                    '<span class="dashicons dashicons-clipboard"></span>' +
                    '<p>' + (cfg.txt.no_history || 'Sin préstamos registrados para este equipo.') + '</p>' +
                    '</div>'
                );
                $('#js-history-meta').text('0 registros');
                return;
            }

            // Contadores de resumen
            var countActive   = 0, countOverdue = 0, countReturned = 0;
            $.each(items, function (i, it) {
                if (it.loan_status === 'active')   countActive++;
                else if (it.loan_status === 'overdue')  countOverdue++;
                else if (it.loan_status === 'returned') countReturned++;
            });

            var metaParts = [items.length + ' registros'];
            if (countActive)   metaParts.push(countActive   + ' activo(s)');
            if (countOverdue)  metaParts.push('<span style="color:#d63638;">' + countOverdue + ' vencido(s)</span>');
            if (countReturned) metaParts.push(countReturned + ' devuelto(s)');
            $('#js-history-meta').html(metaParts.join(' · '));

            var statusLabel = function (s) {
                return { active: cfg.txt.active || 'Activo', overdue: cfg.txt.overdue || 'Vencido', returned: cfg.txt.returned || 'Devuelto' }[s] || s;
            };

            // Los valores de estado vienen del ENUM de BD: good/fair/poor/damaged
            var stateLabel = function (s) {
                if (!s) return '<span class="aura-loan-history-na">—</span>';
                var map = { good: 'Bueno', fair: 'Regular', poor: 'Deficiente', damaged: 'Dañado' };
                return escHtml(map[s] || s);
            };

            var html = '<div class="aura-loan-history-table-wrap">' +
                '<table class="aura-loan-history-table">' +
                '<thead><tr>' +
                '<th>Prestatario</th>' +
                '<th>Fecha salida</th>' +
                '<th>Dev. esperada</th>' +
                '<th>Dev. real</th>' +
                '<th>Estado salida</th>' +
                '<th>Estado entrada</th>' +
                '<th>Estado préstamo</th>' +
                '</tr></thead><tbody>';

            $.each(items, function (i, item) {
                var status     = item.loan_status;
                var isOverdue  = status === 'overdue';
                var trClass    = isOverdue ? ' class="aura-loan-history-row-overdue"' : '';

                html += '<tr' + trClass + '>' +
                    '<td>' + buildBorrowerCell(item) + '</td>' +
                    '<td class="aura-loan-history-date">' + escHtml(item.loan_date || '-') + '</td>' +
                    '<td class="aura-loan-history-date' + (isOverdue ? ' aura-loan-history-date-overdue' : '') + '">' +
                        escHtml(item.expected_return_date || '-') + '</td>' +
                    '<td class="aura-loan-history-date">' + (item.actual_return_date ? escHtml(item.actual_return_date) : '<span class="aura-loan-history-na">—</span>') + '</td>' +
                    '<td>' + stateLabel(item.equipment_state_out) + '</td>' +
                    '<td>' + stateLabel(item.return_state) + '</td>' +
                    '<td><span class="aura-loan-status-badge aura-loan-status-' + escHtml(status) + '">' +
                         escHtml(statusLabel(status)) + '</span></td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
            $body.html(html);
        }, function (err) {
            $body.html('<p class="aura-loan-history-error"><span class="dashicons dashicons-warning"></span> ' + escHtml(err) + '</p>');
        });
    }

    // ─── Utilities ───────────────────────────────────────────
    function closeAllModals() {
        $('.aura-loan-modal, #js-overlay').hide();
        $('#co-equip-results').hide();
    }

    function showModalMsg($el, type, msg) {
        $el.removeClass('success error').addClass(type).text(msg).show();
    }

    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    // ─── Boot ────────────────────────────────────────────────
    $(function () { init(); });

}(jQuery));
