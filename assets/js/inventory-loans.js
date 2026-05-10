/**
 * Aura Business Suite — Inventory Loans JS (FASE 5)
 * Checkout / Checkin de equipos con modales, filtros y paginación
 */
/* global jQuery, auraInventoryLoans */
(function ($) {
    'use strict';

    var cfg;
    var loansTable = null;  // instancia DataTables

    // ─── INIT ───────────────────────────────────────────────
    function init() {
        if (typeof auraInventoryLoans === 'undefined') return;
        cfg = auraInventoryLoans;
        initDataTable();
        bindFilters();
        bindCheckoutModal();
        bindCheckinModal();
        
        // Eventos delegados globales para botones de la tabla
        $(document).on('click', '.js-btn-checkin', function () { openCheckinModal($(this).data('id')); });
        $(document).on('click', '.js-btn-history', function () { openHistoryModal($(this).data('id'), $(this).data('name')); });
        $(document).on('click', '.js-btn-edit', function () { openEditLoanModal($(this).data('item')); });
        $(document).on('click', '.js-btn-delete', function () { deleteLoan($(this).data('id'), $(this).data('name')); });
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

    // ─── Funciones Render Helper Mínimas ────────────────────
    function buildBorrowerCell(item) {
        if (item.borrowed_to_name) {
            var phone = item.borrowed_to_phone ? ' <a href="https://wa.me/' + item.borrowed_to_phone.replace(/\D/g, '') + '" target="_blank" title="WhatsApp"><span class="dashicons dashicons-whatsapp" style="color:#25D366;font-size:16px;vertical-align:text-bottom;"></span></a>' : '';
            return '<strong>' + escHtml(item.borrowed_to_name) + '</strong>' + phone + '<br><small style="color:#646970;">(Externo)</small>';
        } else if (item.borrower_display) {
            return '<div style="display:flex;align-items:center;gap:8px;">' +
                   (item.borrower_avatar || '<span class="dashicons dashicons-admin-users" style="color:#a7aaad;"></span>') +
                   '<span><strong>' + escHtml(item.borrower_display) + '</strong></span></div>';
        }
        return '—';
    }

    function buildActionButtons(item, status) {
        var html = '<div class="aura-loan-action-wrap">';
        if (status === 'active' || status === 'overdue') {
            if (cfg.can_checkin) html += '<button type="button" class="aura-loan-action-btn checkin js-btn-checkin" data-id="' + item.loan_id + '" title="' + cfg.txt.checkin + '"><span class="dashicons dashicons-yes-alt"></span></button>';
        }
        html += '<button type="button" class="aura-loan-action-btn js-btn-history" data-id="' + item.equipment_id + '" data-name="' + escHtml(item.equipment_name) + '" title="' + cfg.txt.history + '"><span class="dashicons dashicons-clipboard"></span></button>';
        
        if (cfg.can_loan_edit) {
            var json = window.JSON ? window.JSON.stringify(item).replace(/"/g, '&quot;') : '{}';
            html += '<button type="button" class="aura-loan-action-btn js-btn-edit" data-item="' + json + '" title="' + cfg.txt.edit_loan + '"><span class="dashicons dashicons-edit"></span></button>';
        }
        if (cfg.can_loan_delete) {
            html += '<button type="button" class="aura-loan-action-btn aura-loan-btn-danger js-btn-delete" data-id="' + item.loan_id + '" data-name="' + escHtml(item.equipment_name) + '" title="' + cfg.txt.delete_loan + '"><span class="dashicons dashicons-trash"></span></button>';
        }
        html += '</div>';
        return html;
    }

    // ─── DataTable ──────────────────────────────────────────
    function initDataTable() {
        var txt = cfg.txt || {};

        // Evitar re-inicialización si WP ya cargó el script antes
        if ($.fn.DataTable.isDataTable('#js-loans-table')) {
            loansTable = $('#js-loans-table').DataTable();
            return;
        }

        loansTable = new DataTable('#js-loans-table', {
            responsive: true,
            processing: true,
            serverSide: false,
            pageLength: 20,
            lengthMenu: [10, 20, 50, 100],
            order: [[4, 'desc']],   // Fecha salida descendente
            language: {
                processing:  'Cargando préstamos…',
                zeroRecords: txt.no_loans || 'Sin préstamos registrados.',
                info:        '_TOTAL_ préstamos',
                infoEmpty:   '0 préstamos',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu:  'Mostrar _MENU_ por página',
                paginate: { first:'«', last:'»', next:'›', previous:'‹' }
            },
            searching: false,
            dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',

            ajax: {
                url:  cfg.ajaxurl,
                type: 'POST',
                data: function () {
                    return {
                        action:      'aura_inventory_get_loans_list',
                        nonce:       cfg.nonce,
                        page:        1,
                        per_page:    9999,
                        search:      $('#js-loan-search').val(),
                        loan_status: $('#js-loan-status').val(),
                        date_from:   $('#js-loan-date-from').val(),
                        date_to:     $('#js-loan-date-to').val(),
                    };
                },
                dataSrc: function (json) {
                    if (!json.success) return [];
                    return json.data.items || [];
                },
            },

            columns: [
                {
                    title: 'Foto', data: null, orderable: false,
                    className: 'aura-inv-col-photo', width: '58px',
                    render: function (d, t, item) {
                        if (t !== 'display') return item.equipment_name || '';
                        return item.equipment_photo_thumb
                            ? '<div class="aura-inv-thumb-wrap" data-full="' + escHtml(item.equipment_photo_thumb) + '">' +
                              '<img src="' + escHtml(item.equipment_photo_thumb) + '" alt="" width="44" height="33"' +
                              ' style="border-radius:3px;object-fit:cover;display:block;border:1px solid #dcdcde;"></div>'
                            : '<span class="dashicons dashicons-format-image" style="color:#c3c4c7;font-size:24px;" title="Sin foto"></span>';
                    },
                },
                {
                    title: 'Equipo', data: 'equipment_name',
                    render: function (data, t, item) {
                        if (t !== 'display') return data || '';
                        return '<strong>' + escHtml(item.equipment_name || '-') + '</strong>' +
                               (item.equipment_brand ? '<small>' + escHtml(item.equipment_brand) + '</small>' : '');
                    },
                },
                {
                    title: 'Prestatario', data: 'borrower_display',
                    render: function (d, t, item) {
                        return t === 'display' ? buildBorrowerCell(item) : (d || '');
                    },
                },
                {
                    title: 'Proyecto / Motivo', data: 'project',
                    render: function (data) {
                        if (!data) return '<span style="color:#8c8f94">-</span>';
                        return '<span title="' + escHtml(data) + '">' +
                               escHtml(data).substring(0, 30) + (data.length > 30 ? '…' : '') + '</span>';
                    },
                },
                {
                    title: 'Fecha salida', data: 'loan_date',
                    render: function (data) { return escHtml(data || '—'); },
                },
                {
                    title: 'Devolución esperada', data: 'expected_return_date',
                    render: function (data, t, item) {
                        if (t !== 'display') return data || '';
                        if (item.actual_return_date) {
                            return '<span style="color:#00a32a;">' + item.actual_return_date + '</span>';
                        }
                        var h = escHtml(data || '');
                        if (item.loan_status === 'overdue' && item.days_overdue > 0) {
                            h += '<span class="aura-loan-overdue-days">+' + item.days_overdue + ' ' + (txt.days_ago || 'días') + '</span>';
                        }
                        return h;
                    },
                },
                {
                    title: 'Estado', data: 'loan_status',
                    render: function (data, t) {
                        if (t !== 'display') return data || '';
                        var lbl = { active: txt.active, overdue: txt.overdue, returned: txt.returned }[data] || data;
                        return '<span class="aura-loan-status-badge aura-loan-status-' + data + '">' + escHtml(lbl) + '</span>';
                    },
                },
                {
                    title: 'Acciones', data: null, orderable: false, searchable: false,
                    render: function (d, t, item) {
                        if (t !== 'display') return '';
                        return buildActionButtons(item, item.loan_status);
                    },
                },
            ],

            drawCallback: function () {
                // Eventos manejados globalmente arriba en init()
            },
        });
    }

    // ─── Filters ────────────────────────────────────────────
    function bindFilters() {
        $('#js-loan-filter-btn').on('click', function () { loansTable.ajax.reload(); });
        $('#js-loan-clear-btn').on('click', function () {
            $('#js-loan-search, #js-loan-date-from, #js-loan-date-to').val('');
            $('#js-loan-status').val('');
            loansTable.ajax.reload();
        });
        $('#js-loan-search').on('keydown', function (e) {
            if (e.key === 'Enter') loansTable.ajax.reload();
        });
        $('#js-loan-status').on('change', function () { loansTable.ajax.reload(); });
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
                loansTable.ajax.reload();
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
            function () { loansTable.ajax.reload(); },
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

        // ── Botón: Seleccionar equipo desde el listado select ───────────────
        $(document).on('click', '#js-btn-select-listed-equip', function () {
            var $sel = $('#co-equip-list-select');
            var id = $sel.val();
            if (!id) return;
            var name = $sel.find('option:selected').text();
            var meta = $sel.find('option:selected').data('meta') || '';
            selectEquip(id, name, meta);
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
        var $sel = $('#co-equip-list-select').empty();
        $sel.append('<option value="">-- Seleccione un equipo (' + items.length + ' disponibles) --</option>');
        
        if (!items.length) {
            $sel.append('<option value="" disabled>Sin equipos disponibles.</option>');
        } else {
            // Ordenar alfabéticamente
            items.sort(function(a, b) {
                return (a.name || '').localeCompare(b.name || '');
            });
            $.each(items, function (i, item) {
                var meta = [item.brand, item.model, item.internal_code].filter(Boolean).join(' · ');
                var label = item.name + (meta ? ' (' + meta + ')' : '');
                $('<option>')
                    .val(item.id)
                    .text(label)
                    .data('meta', meta)
                    .appendTo($sel);
            });
        }
        $('#co-equip-list-select-wrap').css('display', 'flex');
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
                    loansTable.ajax.reload();
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
                    loansTable.ajax.reload();
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

    // ─────────────────────────────────────────────────────────────
    // TOOLTIP DE FOTO (FLOTANTE)
    // ─────────────────────────────────────────────────────────────
    (function () {
        var tooltipW = 260, tooltipH = 195, gap = 14;

        // Crear el div del tooltip una sola vez y anexarlo al body
        var $tt = $(
            '<div id="aura-inv-photo-tooltip" style="' +
                'display:none;' +
                'position:fixed;' +
                'width:' + tooltipW + 'px;' +
                'height:' + tooltipH + 'px;' +
                'border-radius:8px;' +
                'border:2px solid #fff;' +
                'box-shadow:0 8px 24px rgba(0,0,0,.38);' +
                'background-size:cover;' +
                'background-position:center;' +
                'pointer-events:none;' +
                'z-index:99999;' +
                'opacity:0;' +
                'transition:opacity .14s ease, transform .14s ease;' +
                'transform:scale(.92);' +
            '"></div>'
        ).appendTo('body');

        function show(url, e) {
            $tt.css('background-image', 'url("' + url + '")');
            reposition(e);
            $tt.show();
            // Tiny delay so the browser paints the position before fading in
            window.requestAnimationFrame(function () {
                $tt.css({ opacity: 1, transform: 'scale(1)' });
            });
        }

        function hide() {
            $tt.css({ opacity: 0, transform: 'scale(.92)' });
            setTimeout(function () { $tt.hide(); }, 160);
        }

        function reposition(e) {
            var vw = window.innerWidth, vh = window.innerHeight;
            var x = e.clientX + gap;
            var y = e.clientY - tooltipH / 2;
            if (x + tooltipW > vw - 8)  { x = e.clientX - tooltipW - gap; }
            if (y + tooltipH > vh - 8)  { y = vh - tooltipH - 8; }
            if (y < 8)                  { y = 8; }
            $tt.css({ top: y + 'px', left: x + 'px' });
        }

        $(document)
            .on('mouseenter', '.aura-inv-thumb-wrap', function (e) {
                var url = $(this).attr('data-full');
                if (url) { show(url, e); }
            })
            .on('mousemove', '.aura-inv-thumb-wrap', function (e) {
                reposition(e);
            })
            .on('mouseleave', '.aura-inv-thumb-wrap', function () {
                hide();
            });
    }());

    // ─── Boot ────────────────────────────────────────────────
    $(function () { init(); });

}(jQuery));
