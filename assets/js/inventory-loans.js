/**
 * Aura Business Suite — Inventory Loans JS (Aura Design System)
 * Checkout / Checkin, Child Rows (+), Explorador Visual de Equipos y Terceros/Usuarios
 */
/* global jQuery, DataTable, auraInventoryLoans, AuraUI */
(function ($) {
    'use strict';

    if (window.__auraInvLoansBooted) {
        return;
    }
    window.__auraInvLoansBooted = true;

    var cfg;
    var loansTable = null;
    var equipListCache = null;

    // ─── INIT ──────────────────────────────────────────────────
    function init() {
        if (typeof auraInventoryLoans === 'undefined') return;
        if (!$('#js-loans-table').length) return;
        cfg = auraInventoryLoans;

        initDataTable();
        bindFilters();
        bindCheckoutModal();
        bindEquipPickerModal();
        bindCheckinModal();
        bindEditLoanModal();
        bindGlobalModalGuards();

        $(document).on('click', '#js-loans-table .aura-inv-row-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $tr  = $btn.closest('tr');
            var row  = loansTable.row($tr);
            var item = row.data();
            if (!item) return;

            if (row.child.isShown()) {
                row.child.hide();
                $tr.removeClass('aura-row-expanded');
                $btn.removeClass('is-open').text('+').attr('aria-expanded', 'false');
            } else {
                row.child(renderLoanChildCard(item), 'aura-child-row').show();
                $tr.addClass('aura-row-expanded');
                $btn.addClass('is-open').text('−').attr('aria-expanded', 'true');
            }
        });

        $(document).on('click', '.js-btn-checkin', function () { openCheckinModal($(this).data('id')); });
        $(document).on('click', '.js-btn-history', function () { openHistoryModal($(this).data('id'), $(this).data('name')); });
        $(document).on('click', '.js-btn-edit', function () {
            var item = $(this).data('item');
            if (typeof item === 'string') {
                try { item = JSON.parse(item); } catch (e) { item = null; }
            }
            if (item) openEditLoanModal(item);
        });
        $(document).on('click', '.js-btn-delete', function () { deleteLoan($(this).data('id'), $(this).data('name')); });
    }

    // ─── AJAX Helper ───────────────────────────────────────────
    function doAjax(action, data, success, error) {
        $.post(cfg.ajaxurl, $.extend({ action: action, nonce: cfg.nonce }, data))
            .done(function (res) {
                if (res && res.success) {
                    if (typeof success === 'function') success(res.data);
                } else {
                    if (typeof error === 'function') error(res && res.data ? (res.data.message || res.data) : 'Error');
                }
            })
            .fail(function () {
                if (typeof error === 'function') error('Error de conexión.');
            });
    }

    // ─── Render Helpers ────────────────────────────────────────
    function buildThumbCell(item) {
        var full  = item.equipment_photo_full || item.equipment_photo_thumb;
        var thumb = item.equipment_photo_thumb || item.equipment_photo_full;
        if (!full) {
            return '<div class="aura-inv-thumb-empty" data-tooltip="Sin fotografía"><span class="dashicons dashicons-format-image"></span></div>';
        }
        var label = item.equipment_name || 'Equipo';
        var sub   = [item.equipment_brand || '', item.equipment_code ? ('Cód: ' + item.equipment_code) : ''].filter(Boolean).join(' · ');
        var card  = '<div class="aura-floating-img-card aura-inv-photo-card">' +
            '<img src="' + escHtml(full) + '" alt="' + escHtml(label) + '" style="max-width:260px;max-height:260px;width:auto;height:auto;object-fit:contain;border-radius:8px;display:block;margin:0 auto;">' +
            '<span class="aura-floating-img-title">' + escHtml(label) + '</span>' +
            (sub ? '<span class="aura-floating-img-sub">' + escHtml(sub) + '</span>' : '') +
            '</div>';

        return '<div class="aura-inv-thumb-wrap aura-table-thumb-preview" ' +
            'data-img-url="' + escHtml(full) + '" ' +
            'data-preview-title="' + escHtml(label) + '" ' +
            'data-aura-tooltip="' + escHtml(card) + '">' +
            '<img src="' + escHtml(thumb) + '" alt="' + escHtml(label) + '" loading="lazy">' +
            '</div>';
    }

    function buildEquipmentTooltip(item) {
        var txt = cfg.txt || {};
        var stLbl = { active: txt.active || 'Activo', overdue: txt.overdue || 'Vencido', returned: txt.returned || 'Devuelto' }[item.loan_status] || item.loan_status;
        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header"><div class="aura-tip-info">' +
            '<div class="aura-tip-title">' + escHtml(item.equipment_name || 'Equipo') + '</div>' +
            '</div></div>' +
            '<div class="aura-tip-card-body">' +
            '<div class="aura-tip-row"><span>Marca / Modelo</span><strong>' + escHtml([item.equipment_brand, item.equipment_model].filter(Boolean).join(' · ') || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>Prestatario</span><strong>' + escHtml(item.borrower_display || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>Salida</span><strong>' + escHtml(item.loan_date || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>Dev. Esperada</span><strong>' + escHtml(item.expected_return_date || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>Estado</span><strong>' + escHtml(stLbl) + '</strong></div>' +
            '</div></div>';
    }

    function buildBorrowerCell(item) {
        var name  = item.borrower_display || item.borrowed_to_name || '—';
        var phone = item.borrowed_to_phone || '';
        var wa    = phone ? ' <a href="https://wa.me/' + phone.replace(/\D/g, '') + '" target="_blank" class="aura-loan-wa-link" data-tooltip="Enviar mensaje WhatsApp"><span class="dashicons dashicons-whatsapp"></span></a>' : '';

        var avatarHtml = '';
        if (item.borrower_avatar) {
            avatarHtml = '<img src="' + escHtml(item.borrower_avatar) + '" alt="' + escHtml(name) + '" ' +
                'class="aura-loan-borrower-avatar avatar-zoomable" data-preview-title="' + escHtml(name) + '">';
        } else {
            var initials = String(name).split(' ').filter(Boolean).slice(0, 2)
                .map(function (w) { return (w[0] || '').toUpperCase(); }).join('');
            avatarHtml = '<span class="aura-loan-borrower-initials">' + escHtml(initials || '?') + '</span>';
        }

        var subLabel = item.is_external
            ? '<span class="aura-loan-borrower-tag">(Externo)</span>'
            : '<span class="aura-loan-borrower-tag">(Usuario)</span>';

        return '<div class="aura-loan-borrower-cell">' +
               avatarHtml +
               '<div class="aura-loan-borrower-meta">' +
               '<span class="aura-loan-borrower-name">' + escHtml(name) + '</span>' + wa +
               subLabel +
               '</div></div>';
    }

    function buildActionButtons(item, status) {
        var html = '<div class="aura-row-actions aura-loan-action-wrap">';
        if (status === 'active' || status === 'overdue') {
            if (cfg.can_checkin) {
                html += '<button type="button" class="aura-btn-action-icon aura-btn-approve checkin js-btn-checkin" data-id="' + item.loan_id + '" data-tooltip="' + escHtml(cfg.txt.checkin || 'Devolver') + '"><span class="dashicons dashicons-yes-alt"></span></button>';
            }
        }
        html += '<button type="button" class="aura-btn-action-icon aura-btn-view js-btn-history" data-id="' + item.equipment_id + '" data-name="' + escHtml(item.equipment_name) + '" data-tooltip="' + escHtml(cfg.txt.history || 'Historial') + '"><span class="dashicons dashicons-clipboard"></span></button>';

        if (cfg.can_loan_edit) {
            var json = window.JSON ? window.JSON.stringify(item).replace(/"/g, '&quot;') : '{}';
            html += '<button type="button" class="aura-btn-action-icon aura-btn-edit js-btn-edit" data-item="' + json + '" data-tooltip="' + escHtml(cfg.txt.edit_loan || 'Editar') + '"><span class="dashicons dashicons-edit"></span></button>';
        }
        if (cfg.can_loan_delete) {
            html += '<button type="button" class="aura-btn-action-icon aura-btn-delete js-btn-delete" data-id="' + item.loan_id + '" data-name="' + escHtml(item.equipment_name) + '" data-tooltip="' + escHtml(cfg.txt.delete_loan || 'Eliminar') + '"><span class="dashicons dashicons-trash"></span></button>';
        }
        html += '</div>';
        return html;
    }

    function renderLoanChildCard(item) {
        var txt   = cfg.txt || {};
        var stLbl = { active: txt.active || 'Activo', overdue: txt.overdue || 'Vencido', returned: txt.returned || 'Devuelto' }[item.loan_status] || item.loan_status;
        var full  = item.equipment_photo_full || item.equipment_photo_thumb;
        var thumb = item.equipment_photo_thumb || item.equipment_photo_full;

        var photoHtml = full
            ? '<div class="aura-inv-child-photo aura-table-thumb-preview" data-img-url="' + escHtml(full) + '" data-preview-title="' + escHtml(item.equipment_name) + '"><img src="' + escHtml(thumb) + '" alt="' + escHtml(item.equipment_name) + '"></div>'
            : '<div class="aura-inv-child-photo aura-inv-child-photo-empty"><span class="dashicons dashicons-format-image"></span></div>';

        var stateMap = { good: 'Bueno', fair: 'Regular', poor: 'Deficiente', damaged: 'Dañado' };

        var h = '<div class="aura-inv-child-card">';
        h += '<div class="aura-inv-child-layout">';
        h += photoHtml;
        h += '<div class="aura-inv-child-main">';
        h += '<div class="aura-inv-child-top">';
        h += '<div><h4 class="aura-inv-child-title">' + escHtml(item.equipment_name || 'Equipo') + '</h4>' +
             '<div class="aura-inv-child-sub">' + escHtml([item.equipment_brand, item.equipment_model, item.equipment_code ? ('Cód: ' + item.equipment_code) : ''].filter(Boolean).join(' · ') || 'Movimiento de préstamo #' + item.loan_id) + '</div></div>';
        h += '<span class="aura-loan-status-badge aura-loan-status-' + escHtml(item.loan_status) + '">' + escHtml(stLbl) + '</span>';
        h += '</div>';

        h += '<div class="aura-inv-child-specs">';
        h += childSpec('Prestatario', item.borrower_display || '—');
        h += childSpec('Teléfono', item.borrowed_to_phone || '—');
        h += childSpec('Proyecto / Motivo', item.project || '—');
        h += childSpec('Fecha salida', item.loan_date || '—');
        h += childSpec('Devolución esperada', item.expected_return_date || '—');
        h += childSpec('Devolución real', item.actual_return_date || 'Pendiente');
        h += childSpec('Estado salida', stateMap[item.equipment_state_out] || item.equipment_state_out || '—');
        h += childSpec('Estado regreso', item.return_state ? (stateMap[item.return_state] || item.return_state) : '—');
        h += '</div>';

        if (item.return_observations) {
            h += '<div class="aura-inv-child-desc"><strong>Observaciones de devolución:</strong> ' + escHtml(item.return_observations) + '</div>';
        }

        h += '<div class="aura-inv-child-actions">';
        if ((item.loan_status === 'active' || item.loan_status === 'overdue') && cfg.can_checkin) {
            h += '<button type="button" class="btn btn-indigo btn-sm js-btn-checkin" data-id="' + item.loan_id + '"><span class="dashicons dashicons-yes-alt"></span> Registrar Devolución</button>';
        }
        h += '<button type="button" class="btn btn-glass btn-sm js-btn-history" data-id="' + item.equipment_id + '" data-name="' + escHtml(item.equipment_name) + '"><span class="dashicons dashicons-clipboard"></span> Ver Historial</button>';
        if (cfg.can_loan_edit) {
            var json = window.JSON ? window.JSON.stringify(item).replace(/"/g, '&quot;') : '{}';
            h += '<button type="button" class="btn btn-glass btn-sm js-btn-edit" data-item="' + json + '"><span class="dashicons dashicons-edit"></span> Editar</button>';
        }
        h += '</div>';

        h += '</div></div></div>';
        return h;
    }

    // ─── DataTable ─────────────────────────────────────────────
    function initDataTable() {
        var txt = cfg.txt || {};

        if ($.fn.DataTable.isDataTable('#js-loans-table')) {
            loansTable = $('#js-loans-table').DataTable();
            return;
        }

        loansTable = new DataTable('#js-loans-table', {
            responsive: false,
            processing: true,
            serverSide: false,
            pageLength: 20,
            lengthMenu: [10, 20, 50, 100],
            order: [[5, 'desc']],
            language: {
                processing:  'Cargando préstamos…',
                zeroRecords: txt.no_loans || 'Sin préstamos registrados.',
                info:        '_TOTAL_ préstamos',
                infoEmpty:   '0 préstamos',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu:  'Mostrar _MENU_ por página',
                paginate: { first: '«', last: '»', next: '›', previous: '‹' }
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
                        per_page:    2000,
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
                    data: null, orderable: false, searchable: false,
                    className: 'aura-inv-col-toggle', width: '40px',
                    render: function (d, t, item) {
                        if (t !== 'display') return '';
                        return '<button type="button" class="aura-inv-row-toggle" data-tooltip="Expandir detalle del préstamo" aria-expanded="false" aria-label="Expandir">+</button>';
                    }
                },
                {
                    data: null, orderable: false,
                    className: 'aura-inv-col-photo', width: '68px',
                    render: function (d, t, item) {
                        if (t !== 'display') return item.equipment_name || '';
                        return buildThumbCell(item);
                    },
                },
                {
                    data: 'equipment_name',
                    render: function (data, t, item) {
                        if (t !== 'display') return data || '';
                        var tip = buildEquipmentTooltip(item);
                        var h = '<a href="javascript:void(0)" class="aura-inv-title-link js-btn-history" ' +
                                'data-id="' + item.equipment_id + '" data-name="' + escHtml(item.equipment_name) + '" ' +
                                'data-aura-tooltip="' + escHtml(tip) + '">' + escHtml(item.equipment_name || '-') + '</a>';
                        if (item.equipment_brand) {
                            h += '<div class="aura-inv-cell-sub">' + escHtml(item.equipment_brand) + '</div>';
                        }
                        var mobMeta = [item.loan_date ? ('Sal: ' + item.loan_date) : '', item.expected_return_date ? ('Dev: ' + item.expected_return_date) : ''].filter(Boolean).join(' · ');
                        if (mobMeta) {
                            h += '<div class="aura-inv-mobile-meta">' + escHtml(mobMeta) + '</div>';
                        }
                        return h;
                    },
                },
                {
                    data: 'borrower_display',
                    render: function (d, t, item) {
                        return t === 'display' ? buildBorrowerCell(item) : (d || '');
                    },
                },
                {
                    data: 'project',
                    className: 'aura-inv-col-secondary',
                    render: function (data) {
                        if (!data) return '<span class="aura-inv-muted">—</span>';
                        var truncated = data.length > 30 ? (data.substring(0, 30) + '…') : data;
                        return '<span data-tooltip="' + escHtml(data) + '">' + escHtml(truncated) + '</span>';
                    },
                },
                {
                    data: 'loan_date',
                    className: 'aura-inv-col-secondary',
                    render: function (data) { return escHtml(data || '—'); },
                },
                {
                    data: 'expected_return_date',
                    className: 'aura-inv-col-secondary',
                    render: function (data, t, item) {
                        if (t !== 'display') return data || '';
                        if (item.actual_return_date) {
                            return '<span class="aura-loan-date-returned" data-tooltip="Fecha real de devolución">' + escHtml(item.actual_return_date) + '</span>';
                        }
                        var h = escHtml(data || '');
                        if (item.loan_status === 'overdue' && item.days_overdue > 0) {
                            h += '<span class="aura-loan-overdue-days">+' + item.days_overdue + ' ' + escHtml(txt.days_ago || 'días') + '</span>';
                        }
                        return h;
                    },
                },
                {
                    data: 'loan_status',
                    render: function (data, t) {
                        if (t !== 'display') return data || '';
                        var lbl = { active: txt.active, overdue: txt.overdue, returned: txt.returned }[data] || data;
                        return '<span class="aura-loan-status-badge aura-loan-status-' + escHtml(data) + '">' + escHtml(lbl) + '</span>';
                    },
                },
                {
                    data: null, orderable: false, searchable: false,
                    className: 'column-actions',
                    render: function (d, t, item) {
                        if (t !== 'display') return '';
                        return buildActionButtons(item, item.loan_status);
                    },
                },
            ],
        });
    }

    // ─── Filters ───────────────────────────────────────────────
    function bindFilters() {
        $('#js-loan-filter-btn').on('click', function () { loansTable.ajax.reload(); });
        $('#js-loan-clear-btn').on('click', function () {
            $('#js-loan-search, #js-loan-date-from, #js-loan-date-to').val('');
            $('#js-loan-status').val('');
            loansTable.ajax.reload();
        });
        $('#js-loan-search').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loansTable.ajax.reload();
            }
        });
        $('#js-loan-status, #js-loan-date-from, #js-loan-date-to').on('change', function () {
            loansTable.ajax.reload();
        });
    }

    // ─── Modal Open/Close Helpers + Guards (Item 26) ───────────
    function openModalById(id, asyncSkeleton) {
        if (window.AuraUI && typeof window.AuraUI.openModal === 'function') {
            window.AuraUI.openModal(id, asyncSkeleton ? { skeleton: true } : undefined);
        } else {
            $('#' + id).addClass('active is-active').css('display', 'flex');
        }
    }

    function hideModalSkeletonById(id) {
        var el = document.getElementById(id);
        if (window.AuraUI && el && typeof window.AuraUI.hideModalSkeleton === 'function') {
            window.AuraUI.hideModalSkeleton(el);
        }
    }

    function closeModalById(id) {
        if (window.AuraUI && typeof window.AuraUI.closeModal === 'function') {
            window.AuraUI.closeModal(id);
        } else {
            $('#' + id).removeClass('active is-active').hide();
            $('body').css('overflow', '');
        }
    }

    function bindTwoPhaseBackdropGuard(modalId, onClose) {
        var sel = '#' + modalId;
        var downOnBackdrop = false;
        $(document).off('mousedown.guard_' + modalId + ' click.guard_' + modalId)
            .on('mousedown.guard_' + modalId, sel, function (e) {
                downOnBackdrop = (e.target === e.currentTarget);
            })
            .on('click.guard_' + modalId, sel, function (e) {
                if (downOnBackdrop && e.target === e.currentTarget) {
                    onClose();
                }
                downOnBackdrop = false;
            });
    }

    function bindGlobalModalGuards() {
        bindTwoPhaseBackdropGuard('aura-inv-equip-picker-modal', function () { closeModalById('aura-inv-equip-picker-modal'); });
        bindTwoPhaseBackdropGuard('js-modal-checkout', function () { closeModalById('js-modal-checkout'); });
        bindTwoPhaseBackdropGuard('js-modal-checkin', function () { closeModalById('js-modal-checkin'); });
        bindTwoPhaseBackdropGuard('js-modal-history', function () { closeModalById('js-modal-history'); });
        bindTwoPhaseBackdropGuard('js-modal-edit-loan', function () { closeModalById('js-modal-edit-loan'); });

        $(document).on('click', '.aura-loan-modal-close', function () {
            var $modal = $(this).closest('.aura-modal-overlay');
            if ($modal.length && $modal.attr('id')) {
                closeModalById($modal.attr('id'));
            }
        });

        $(document).on('keydown.auraLoansEsc', function (e) {
            if (e.key !== 'Escape') return;
            // Cerrar primero el modal apilado superior si está abierto
            if ($('#aura-inv-equip-picker-modal').is(':visible')) {
                closeModalById('aura-inv-equip-picker-modal');
                return;
            }
            ['js-modal-edit-loan', 'js-modal-history', 'js-modal-checkin', 'js-modal-checkout'].forEach(function (id) {
                if ($('#' + id).is(':visible')) closeModalById(id);
            });
        });
    }

    // ─── EDIT LOAN MODAL ──────────────────────────────────────
    function bindEditLoanModal() {
        $(document).on('click', '.js-close-edit-loan', function () {
            closeModalById('js-modal-edit-loan');
        });

        $(document).on('submit', '#js-form-edit-loan', function (e) {
            e.preventDefault();
            var $submit = $('#js-el-submit').prop('disabled', true);
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
                    $submit.prop('disabled', false);
                    closeModalById('js-modal-edit-loan');
                    notifyToast('Préstamo actualizado correctamente.', 'success');
                    setTimeout(function () { window.location.reload(); }, 450);
                },
                function (msg) {
                    $submit.prop('disabled', false);
                    $('#js-el-feedback').show().find('p').text(msg || 'Error al guardar.');
                }
            );
        });
    }

    function openEditLoanModal(item) {
        $('#el-loan-id').val(item.loan_id);
        $('#js-el-equip-name').text(item.equipment_name || '');
        $('#el-loan-date').val(item.loan_date || '');
        $('#el-expected-return').val(item.expected_return_date || '');
        $('#el-borrowed-name').val(item.borrowed_to_name || '');
        $('#el-borrowed-phone').val(item.borrowed_to_phone || '');
        $('#el-project').val(item.project || '');
        $('#js-el-feedback').hide().find('p').text('');
        openModalById('js-modal-edit-loan', false);
    }

    // ─── DELETE LOAN ──────────────────────────────────────────
    function deleteLoan(loanId, eqName) {
        var msg = (cfg.txt.confirm_delete_loan || '¿Eliminar este préstamo?');
        if (eqName) msg = '«' + eqName + '» — ' + msg;
        if (!window.confirm(msg)) return;

        doAjax(
            'aura_inventory_delete_loan',
            { loan_id: loanId },
            function () {
                notifyToast('Préstamo eliminado.', 'success');
                setTimeout(function () { window.location.reload(); }, 450);
            },
            function (err) {
                notifyToast(err || 'Error al eliminar.', 'error');
            }
        );
    }

    // ─── CHECKOUT MODAL + EXPLORADOR USUARIOS ─────────────────
    function bindCheckoutModal() {
        $(document).on('click', '#js-open-checkout', openCheckoutModal);

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

        $(document).on('click', '#js-btn-select-listed-equip', function () {
            var $sel = $('#co-equip-list-select');
            var id   = $sel.val();
            if (!id) return;
            var name = $sel.find('option:selected').text();
            var meta = $sel.find('option:selected').data('meta') || '';
            selectEquip(id, name, meta);
        });

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
            }, 300);
        });

        $(document).on('click', '.aura-loan-equip-result-item', function () {
            selectEquip(
                $(this).data('id'),
                $(this).find('.equip-res-name').text(),
                $(this).find('.equip-res-meta').text()
            );
        });

        $(document).on('click', '.selected-clear', function () {
            $('#co-equipment-id').val('');
            $('#co-equip-selected').hide().empty();
            var activeMode = $('.aura-loan-equip-tab.active').data('mode') || 'search';
            if (activeMode === 'search') {
                $('#co-equip-mode-search').show();
                $('#co-equipment-search').val('').focus();
            } else {
                $('#co-equip-mode-list').show();
                if (equipListCache) renderEquipList(equipListCache);
            }
        });

        // Botón "Explorar Todos los Usuarios" (AuraThirdPartySelector)
        $(document).on('click', '#aura-inv-btn-borrower-explorer', function () {
            if (typeof window.AuraThirdPartySelector === 'undefined') {
                notifyToast('Explorador de usuarios no disponible en esta página.', 'error');
                return;
            }
            window.AuraThirdPartySelector.openExplorer({
                defaultTab: 'employee',
                allowUsers: true,
                onSelect: function (item) {
                    var name   = item.commercial_name || item.name || '';
                    var isUser = !!(item.is_wp_user || item.party_type === 'user' || item.user_id);
                    if (isUser) {
                        var uid = String(item.user_id || item.id || '');
                        if (uid) {
                            var $sel = $('#co-borrower-user');
                            if (!$sel.find('option[value="' + uid + '"]').length) {
                                $('<option>').val(uid).text(name).appendTo($sel);
                            }
                            $sel.val(uid);
                            $('#co-borrower-name').val('');
                            $('#co-borrower-phone').val(item.phone || '');
                            $('#aura-inv-co-borrower-badge').html(
                                '<span class="aura-inv-link-pill">👤 Usuario WP: ' + escHtml(name) + '</span>'
                            ).show();
                        }
                    } else {
                        $('#co-borrower-name').val(name);
                        if (item.phone) $('#co-borrower-phone').val(item.phone);
                        $('#aura-inv-co-borrower-badge').html(
                            '<span class="aura-inv-link-pill">🔗 Externo / Empleado: ' + escHtml(name) + '</span>'
                        ).show();
                    }
                }
            });
        });

        $(document).on('submit', '#js-form-checkout', function (e) {
            e.preventDefault();
            submitCheckout();
        });

        $(document).on('change', '#co-loan-date', function () {
            $('#co-expected-return').attr('min', $(this).val());
        });
    }

    // ─── VISUAL EQUIPMENT PICKER MODAL (STACKED) ──────────────
    function bindEquipPickerModal() {
        $(document).on('click', '#aura-inv-btn-equip-picker', function () {
            openEquipPickerModal();
        });
        $('#aura-inv-ep-close, #aura-inv-ep-cancel').on('click', function () {
            closeModalById('aura-inv-equip-picker-modal');
        });
        $('#aura-inv-ep-search').on('input', function () {
            filterAndRenderPickerCards();
        });
        $('#aura-inv-ep-category').on('change', function () {
            filterAndRenderPickerCards();
        });
        $(document).on('click', '.aura-inv-ep-card', function () {
            var $c = $(this);
            selectEquip($c.data('id'), $c.data('name'), $c.data('meta'));
            closeModalById('aura-inv-equip-picker-modal');
        });
    }

    function openEquipPickerModal() {
        $('#aura-inv-ep-search').val('');
        $('#aura-inv-ep-category').val('');
        $('#aura-inv-ep-grid').empty();
        $('#aura-inv-ep-count').text('');
        openModalById('aura-inv-equip-picker-modal', true);

        if (equipListCache) {
            populatePickerCategories(equipListCache);
            filterAndRenderPickerCards();
            hideModalSkeletonById('aura-inv-equip-picker-modal');
            setTimeout(function () { $('#aura-inv-ep-search').focus(); }, 180);
            return;
        }

        doAjax('aura_inventory_get_available_equipment', { search: '' }, function (items) {
            equipListCache = items || [];
            populatePickerCategories(equipListCache);
            filterAndRenderPickerCards();
            hideModalSkeletonById('aura-inv-equip-picker-modal');
            setTimeout(function () { $('#aura-inv-ep-search').focus(); }, 180);
        }, function (err) {
            $('#aura-inv-ep-grid').html('<p class="aura-inv-empty-picker">' + escHtml(err || 'Error al cargar equipos.') + '</p>');
            hideModalSkeletonById('aura-inv-equip-picker-modal');
        });
    }

    function populatePickerCategories(items) {
        var cats = {};
        items.forEach(function (it) {
            if (it.category) cats[it.category] = true;
        });
        var $sel = $('#aura-inv-ep-category').empty().append('<option value="">Todas las categorías</option>');
        Object.keys(cats).sort().forEach(function (c) {
            $('<option>').val(c).text(c).appendTo($sel);
        });
    }

    function filterAndRenderPickerCards() {
        var items = equipListCache || [];
        var q     = $.trim($('#aura-inv-ep-search').val()).toLowerCase();
        var cat   = $('#aura-inv-ep-category').val();

        var filtered = items.filter(function (it) {
            if (cat && it.category !== cat) return false;
            if (q) {
                var hay = [it.name, it.brand, it.model, it.internal_code, it.serial_number, it.category, it.location]
                    .filter(Boolean).join(' ').toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });

        $('#aura-inv-ep-count').text(filtered.length + ' equipos disponibles');
        var $grid = $('#aura-inv-ep-grid').empty();

        if (!filtered.length) {
            $grid.html('<div class="aura-inv-empty-picker"><span class="dashicons dashicons-search"></span><p>No hay equipos disponibles que coincidan con tu búsqueda.</p></div>');
            return;
        }

        filtered.forEach(function (it) {
            var meta  = [it.brand, it.model, it.internal_code ? ('Cód: ' + it.internal_code) : ''].filter(Boolean).join(' · ');
            var thumb = it.photo_thumb_url || it.photo_full_url;
            var imgHtml = thumb
                ? '<div class="aura-inv-ep-thumb"><img src="' + escHtml(thumb) + '" alt="' + escHtml(it.name) + '" loading="lazy"></div>'
                : '<div class="aura-inv-ep-thumb aura-inv-ep-thumb-empty"><span class="dashicons dashicons-format-image"></span></div>';

            var card = $('<button type="button" class="aura-inv-ep-card">')
                .data('id', it.id)
                .data('name', it.name)
                .data('meta', meta)
                .html(
                    imgHtml +
                    '<div class="aura-inv-ep-body">' +
                    '<div class="aura-inv-ep-name">' + escHtml(it.name) + '</div>' +
                    (meta ? '<div class="aura-inv-ep-meta">' + escHtml(meta) + '</div>' : '') +
                    '<div class="aura-inv-ep-tags">' +
                    (it.category ? '<span class="aura-inv-ep-cat">' + escHtml(it.category) + '</span>' : '') +
                    (it.location ? '<span class="aura-inv-ep-loc">📍 ' + escHtml(it.location) + '</span>' : '') +
                    '</div></div>'
                );
            $grid.append(card);
        });
    }

    function selectEquip(id, name, meta) {
        $('#co-equipment-id').val(id);
        $('#co-equipment-search').val('');
        $('#co-equip-results').hide().empty();
        $('#co-equip-mode-search').hide();
        $('#co-equip-mode-list').hide();
        $('#co-equip-selected')
            .html('<div class="aura-loan-selected-info"><span class="selected-name">' + escHtml(name) + '</span>' +
                  (meta ? '<small class="selected-meta">' + escHtml(meta) + '</small>' : '') + '</div>' +
                  '<button type="button" class="selected-clear dashicons dashicons-no-alt" data-tooltip="Cambiar equipo" aria-label="Cambiar"></button>')
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
            equipListCache = items || [];
            $('#co-equip-list-loading').hide();
            renderEquipList(equipListCache);
        }, function () {
            $('#co-equip-list-loading').hide();
            $('#co-equip-list-items').html('<p class="aura-inv-text-danger">Error al cargar equipos.</p>');
        });
    }

    function renderEquipList(items) {
        var $sel = $('#co-equip-list-select').empty();
        $sel.append('<option value="">-- Seleccione un equipo (' + items.length + ' disponibles) --</option>');

        if (!items.length) {
            $sel.append('<option value="" disabled>Sin equipos disponibles.</option>');
        } else {
            items.slice().sort(function (a, b) {
                return (a.name || '').localeCompare(b.name || '');
            }).forEach(function (item) {
                var meta  = [item.brand, item.model, item.internal_code].filter(Boolean).join(' · ');
                var label = item.name + (meta ? ' (' + meta + ')' : '');
                $('<option>').val(item.id).text(label).data('meta', meta).appendTo($sel);
            });
        }
        $('#co-equip-list-select-wrap').css('display', 'flex');
    }

    function openCheckoutModal() {
        equipListCache = null;
        resetCheckoutForm();
        openModalById('js-modal-checkout', false);
        setTimeout(function () { $('#co-equipment-search').focus(); }, 100);
    }

    function resetCheckoutForm() {
        var $form = $('#js-form-checkout');
        if ($form.length) $form[0].reset();
        $('#co-loan-date').val(cfg.today);
        $('#co-expected-return').val('').attr('min', cfg.today);
        $('#co-equipment-id').val('');
        $('#co-equip-selected').hide().empty();
        $('#aura-inv-co-borrower-badge').hide().empty();
        $('.aura-loan-equip-tab').removeClass('active');
        $('.aura-loan-equip-tab[data-mode="search"]').addClass('active');
        $('#co-equip-mode-search').show();
        $('#co-equip-mode-list').hide();
        $('#co-equipment-search').val('');
        $('#co-equip-results').hide().empty();
        $('#co-equip-list-items').empty();
        $('#js-checkout-msg').hide().removeClass('success error').text('');
    }

    function renderEquipResults(items) {
        var $res = $('#co-equip-results').empty();
        if (!items || !items.length) {
            $res.html('<div class="aura-loan-equip-empty">' + escHtml(cfg.txt.no_loans || 'Sin equipos disponibles') + '</div>').show();
            return;
        }
        items.forEach(function (item) {
            var meta = [item.brand, item.model, item.internal_code].filter(Boolean).join(' · ');
            $('<div class="aura-loan-equip-result-item">')
                .data('id', item.id)
                .html('<span class="equip-res-name">' + escHtml(item.name) + '</span>' +
                      '<span class="equip-res-meta">' + escHtml(meta) + '</span>')
                .appendTo($res);
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
            contact_id:           $('#co-contact-selector').val() || 0,
            loan_date:            $('#co-loan-date').val(),
            expected_return_date: $('#co-expected-return').val(),
            borrowed_by_user_id:  $('#co-borrower-user').val(),
            borrowed_to_name:     $('#co-borrower-name').val(),
            borrowed_to_phone:    $('#co-borrower-phone').val(),
            project:              $('#co-project').val(),
            equipment_state_out:  $('input[name="equipment_state_out"]:checked').val() || 'good',
        };

        doAjax('aura_inventory_checkout_equipment', data,
            function (res) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'success', res.message || '✅ Préstamo registrado.');
                notifyToast(res.message || 'Préstamo registrado.', 'success');
                setTimeout(function () {
                    closeModalById('js-modal-checkout');
                    window.location.reload();
                }, 700);
            },
            function (errMsg) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'error', errMsg);
            }
        );
    }

    // ─── CHECKIN MODAL ─────────────────────────────────────────
    function bindCheckinModal() {
        $(document).on('change', 'input[name="return_state"]', function () {
            $('#ci-damaged-warning').toggle($(this).val() === 'damaged');
        });
        $(document).on('submit', '#js-form-checkin', function (e) {
            e.preventDefault();
            submitCheckin();
        });
    }

    function openCheckinModal(loanId) {
        var $msg  = $('#js-checkin-msg').hide().removeClass('success error');
        var $summ = $('#ci-summary').empty();

        $('#ci-loan-id').val(loanId);
        $('#ci-return-date').val(cfg.today);
        $('input[name="return_state"][value="good"]').prop('checked', true);
        $('#ci-req-maint').prop('checked', false);
        $('#ci-hours').val('');
        $('#ci-observations').val('');
        $('#ci-damaged-warning').hide();
        $msg.hide();

        openModalById('js-modal-checkin', true);

        doAjax('aura_inventory_get_loan_detail', { loan_id: loanId }, function (loan) {
            var today   = cfg.today;
            var overdue = '';
            if (!loan.actual_return_date && loan.expected_return_date < today) {
                var days = Math.round((new Date(today) - new Date(loan.expected_return_date)) / 86400000);
                overdue  = ' <span class="sum-overdue">(+' + days + ' ' + escHtml(cfg.txt.days || 'días') + ' de retraso)</span>';
            }

            $summ.html(
                '<div class="sum-row"><span class="sum-label">Equipo</span>' +
                '<span class="sum-value sum-value-equip">' + escHtml((loan.equipment_name || '') + (loan.equipment_brand ? ' · ' + loan.equipment_brand : '')) + '</span></div>' +
                '<div class="sum-row"><span class="sum-label">Prestatario</span>' +
                '<div class="sum-value">' + buildBorrowerCell(loan) + '</div></div>' +
                '<div class="sum-row"><span class="sum-label">Fecha salida</span>' +
                '<span class="sum-value">' + escHtml(loan.loan_date || '-') + '</span></div>' +
                '<div class="sum-row"><span class="sum-label">Devolución esperada</span>' +
                '<span class="sum-value">' + escHtml(loan.expected_return_date || '-') + overdue + '</span></div>'
            );
            hideModalSkeletonById('js-modal-checkin');
        }, function () {
            $summ.html('<p class="aura-inv-text-danger">Error al cargar el préstamo.</p>');
            hideModalSkeletonById('js-modal-checkin');
        });
    }

    function submitCheckin() {
        var $btn = $('#js-checkin-submit').prop('disabled', true);
        var $msg = $('#js-checkin-msg').hide().removeClass('success error');

        var data = {
            loan_id:                    $('#ci-loan-id').val(),
            actual_return_date:         $('#ci-return-date').val(),
            return_state:               $('input[name="return_state"]:checked').val() || 'good',
            hours_used:                 $('#ci-hours').val(),
            requires_maintenance_after: $('#ci-req-maint').is(':checked') ? 1 : 0,
            return_observations:        $('#ci-observations').val(),
        };

        doAjax('aura_inventory_checkin_equipment', data,
            function (res) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'success', res.message || '✅ Devolución registrada.');
                notifyToast(res.message || 'Devolución registrada.', 'success');
                setTimeout(function () {
                    closeModalById('js-modal-checkin');
                    window.location.reload();
                }, 700);
            },
            function (errMsg) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'error', errMsg);
            }
        );
    }

    // ─── HISTORY MODAL ────────────────────────────────────────
    function openHistoryModal(equipmentId, equipmentName) {
        var $body = $('#js-history-body').empty();
        $('#js-history-title').text('Historial de préstamos: ' + equipmentName);
        $('#js-history-meta').text('');

        openModalById('js-modal-history', true);

        doAjax('aura_inventory_get_equipment_loan_history', { equipment_id: equipmentId }, function (items) {
            if (!items || !items.length) {
                $body.html(
                    '<div class="aura-loan-history-empty">' +
                    '<span class="dashicons dashicons-clipboard"></span>' +
                    '<p>' + escHtml(cfg.txt.no_history || 'Sin préstamos registrados para este equipo.') + '</p>' +
                    '</div>'
                );
                $('#js-history-meta').text('0 registros');
                hideModalSkeletonById('js-modal-history');
                return;
            }

            var countActive = 0, countOverdue = 0, countReturned = 0;
            items.forEach(function (it) {
                if (it.loan_status === 'active') countActive++;
                else if (it.loan_status === 'overdue') countOverdue++;
                else if (it.loan_status === 'returned') countReturned++;
            });

            var metaParts = [items.length + ' registros'];
            if (countActive)   metaParts.push(countActive + ' activo(s)');
            if (countOverdue)  metaParts.push('<span class="aura-inv-text-danger">' + countOverdue + ' vencido(s)</span>');
            if (countReturned) metaParts.push(countReturned + ' devuelto(s)');
            $('#js-history-meta').html(metaParts.join(' · '));

            var statusLabel = function (s) {
                return { active: cfg.txt.active || 'Activo', overdue: cfg.txt.overdue || 'Vencido', returned: cfg.txt.returned || 'Devuelto' }[s] || s;
            };
            var stateLabel = function (s) {
                if (!s) return '<span class="aura-loan-history-na">—</span>';
                var map = { good: 'Bueno', fair: 'Regular', poor: 'Deficiente', damaged: 'Dañado' };
                return escHtml(map[s] || s);
            };

            var html = '<div class="aura-loan-history-table-wrap">' +
                '<table class="aura-table aura-loan-history-table">' +
                '<thead><tr>' +
                '<th>Prestatario</th>' +
                '<th>Fecha salida</th>' +
                '<th>Dev. esperada</th>' +
                '<th>Dev. real</th>' +
                '<th>Estado salida</th>' +
                '<th>Estado entrada</th>' +
                '<th>Estado préstamo</th>' +
                '</tr></thead><tbody>';

            items.forEach(function (item) {
                var status    = item.loan_status;
                var isOverdue = status === 'overdue';
                html += '<tr' + (isOverdue ? ' class="aura-loan-history-row-overdue"' : '') + '>' +
                    '<td>' + buildBorrowerCell(item) + '</td>' +
                    '<td class="aura-loan-history-date">' + escHtml(item.loan_date || '-') + '</td>' +
                    '<td class="aura-loan-history-date' + (isOverdue ? ' aura-loan-history-date-overdue' : '') + '">' + escHtml(item.expected_return_date || '-') + '</td>' +
                    '<td class="aura-loan-history-date">' + (item.actual_return_date ? escHtml(item.actual_return_date) : '<span class="aura-loan-history-na">—</span>') + '</td>' +
                    '<td>' + stateLabel(item.equipment_state_out) + '</td>' +
                    '<td>' + stateLabel(item.return_state) + '</td>' +
                    '<td><span class="aura-loan-status-badge aura-loan-status-' + escHtml(status) + '">' + escHtml(statusLabel(status)) + '</span></td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
            $body.html(html);
            hideModalSkeletonById('js-modal-history');
        }, function (err) {
            $body.html('<p class="aura-loan-history-error"><span class="dashicons dashicons-warning"></span> ' + escHtml(err) + '</p>');
            hideModalSkeletonById('js-modal-history');
        });
    }

    // ─── Utilities ────────────────────────────────────────────
    function notifyToast(msg, type) {
        if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
            window.AuraUI.showToast(msg, type === 'error' ? 'error' : 'success');
        }
    }

    function showModalMsg($el, type, msg) {
        $el.removeClass('success error').addClass(type).text(msg).show();
    }

    function childSpec(label, value) {
        return '<div class="aura-inv-child-spec">' +
            '<span class="aura-inv-child-spec-lbl">' + escHtml(label) + '</span>' +
            '<strong class="aura-inv-child-spec-val">' + escHtml(value) + '</strong>' +
            '</div>';
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    $(function () { init(); });

}(jQuery));
