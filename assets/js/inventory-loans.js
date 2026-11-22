/**
 * Aura Business Suite — Inventory Loans JS (Aura Design System)
 * Checkout / Checkin, Child Rows (+), Explorador Visual de Equipos y Terceros/Usuarios
 */
/* global jQuery, DataTable, auraInventoryLoans, AuraUI */
(function ($) {
    'use strict';

    function __( s, domain ) {
        return ( window.wp && wp.i18n && typeof wp.i18n.__ === 'function' ) ? wp.i18n.__( s, domain || 'aura-suite' ) : s;
    }
    function sprintf( fmt ) {
        if ( window.wp && wp.i18n && typeof wp.i18n.sprintf === 'function' ) {
            return wp.i18n.sprintf.apply( wp.i18n, arguments );
        }
        var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
        return String( fmt ).replace( /%[sd]/g, function () { return args[ i++ ]; } );
    }

    if (window.__auraInvLoansBooted) {
        return;
    }
    window.__auraInvLoansBooted = true;

    var cfg;
    var loansTable = null;
    var equipListCache = null;
    var contactListCache = null;

    // ─── INIT ──────────────────────────────────────────────────
    function init() {
        if (typeof auraInventoryLoans === 'undefined') return;
        if (!$('#js-loans-table').length) return;
        cfg = auraInventoryLoans;

        initDataTable();
        bindFilters();
        bindCheckoutModal();
        bindEquipPickerModal();
        bindContactPickerModal();
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
                    if (typeof error === 'function') error(res && res.data ? (res.data.message || res.data) : __('Error', 'aura-suite'));
                }
            })
            .fail(function () {
                if (typeof error === 'function') error(__('Error de conexión.', 'aura-suite'));
            });
    }

    // ─── Render Helpers ────────────────────────────────────────
    function buildThumbCell(item) {
        var full  = item.equipment_photo_full || item.equipment_photo_thumb;
        var thumb = item.equipment_photo_thumb || item.equipment_photo_full;
        if (!full) {
            return '<div class="aura-inv-thumb-empty" data-tooltip="' + escHtml(__('Sin fotografía', 'aura-suite')) + '"><span class="dashicons dashicons-format-image"></span></div>';
        }
        var label = item.equipment_name || __('Equipo', 'aura-suite');
        var sub   = [item.equipment_brand || '', item.equipment_code ? sprintf(__('Cód: %s', 'aura-suite'), item.equipment_code) : ''].filter(Boolean).join(' · ');
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
        var stLbl = { active: txt.active || __('Activo', 'aura-suite'), overdue: txt.overdue || __('Vencido', 'aura-suite'), returned: txt.returned || __('Devuelto', 'aura-suite') }[item.loan_status] || item.loan_status;
        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header"><div class="aura-tip-info">' +
            '<div class="aura-tip-title">' + escHtml(item.equipment_name || __('Equipo', 'aura-suite')) + '</div>' +
            '</div></div>' +
            '<div class="aura-tip-card-body">' +
            '<div class="aura-tip-row"><span>' + escHtml(__('Marca / Modelo', 'aura-suite')) + '</span><strong>' + escHtml([item.equipment_brand, item.equipment_model].filter(Boolean).join(' · ') || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>' + escHtml(__('Prestatario', 'aura-suite')) + '</span><strong>' + escHtml(item.borrower_display || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>' + escHtml(__('Salida', 'aura-suite')) + '</span><strong>' + escHtml(item.loan_date || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>' + escHtml(__('Dev. Esperada', 'aura-suite')) + '</span><strong>' + escHtml(item.expected_return_date || '—') + '</strong></div>' +
            '<div class="aura-tip-row"><span>' + escHtml(__('Estado', 'aura-suite')) + '</span><strong>' + escHtml(stLbl) + '</strong></div>' +
            '</div></div>';
    }

    function buildBorrowerCell(item) {
        var name  = item.borrower_display || item.borrowed_to_name || '—';
        var phone = item.borrowed_to_phone || '';
        var wa    = phone ? ' <a href="https://wa.me/' + phone.replace(/\D/g, '') + '" target="_blank" class="aura-loan-wa-link" data-tooltip="' + escHtml(__('Enviar mensaje WhatsApp', 'aura-suite')) + '"><span class="dashicons dashicons-whatsapp"></span></a>' : '';

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
            ? '<span class="aura-loan-borrower-tag">' + escHtml(__('(Externo)', 'aura-suite')) + '</span>'
            : '<span class="aura-loan-borrower-tag">' + escHtml(__('(Usuario)', 'aura-suite')) + '</span>';

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
                html += '<button type="button" class="aura-btn-action-icon aura-btn-approve checkin js-btn-checkin" data-id="' + item.loan_id + '" data-tooltip="' + escHtml(cfg.txt.checkin || __('Devolver', 'aura-suite')) + '"><span class="dashicons dashicons-yes-alt"></span></button>';
            }
        }
        html += '<button type="button" class="aura-btn-action-icon aura-btn-view js-btn-history" data-id="' + item.equipment_id + '" data-name="' + escHtml(item.equipment_name) + '" data-tooltip="' + escHtml(cfg.txt.history || __('Historial', 'aura-suite')) + '"><span class="dashicons dashicons-clipboard"></span></button>';

        if (cfg.can_loan_edit) {
            var json = window.JSON ? window.JSON.stringify(item).replace(/"/g, '&quot;') : '{}';
            html += '<button type="button" class="aura-btn-action-icon aura-btn-edit js-btn-edit" data-item="' + json + '" data-tooltip="' + escHtml(cfg.txt.edit_loan || __('Editar', 'aura-suite')) + '"><span class="dashicons dashicons-edit"></span></button>';
        }
        if (cfg.can_loan_delete) {
            html += '<button type="button" class="aura-btn-action-icon aura-btn-delete js-btn-delete" data-id="' + item.loan_id + '" data-name="' + escHtml(item.equipment_name) + '" data-tooltip="' + escHtml(cfg.txt.delete_loan || __('Eliminar', 'aura-suite')) + '"><span class="dashicons dashicons-trash"></span></button>';
        }
        html += '</div>';
        return html;
    }

    function renderLoanChildCard(item) {
        var txt   = cfg.txt || {};
        var stLbl = { active: txt.active || __('Activo', 'aura-suite'), overdue: txt.overdue || __('Vencido', 'aura-suite'), returned: txt.returned || __('Devuelto', 'aura-suite') }[item.loan_status] || item.loan_status;
        var full  = item.equipment_photo_full || item.equipment_photo_thumb;
        var thumb = item.equipment_photo_thumb || item.equipment_photo_full;

        var photoHtml = full
            ? '<div class="aura-inv-child-photo aura-table-thumb-preview" data-img-url="' + escHtml(full) + '" data-preview-title="' + escHtml(item.equipment_name) + '"><img src="' + escHtml(thumb) + '" alt="' + escHtml(item.equipment_name) + '"></div>'
            : '<div class="aura-inv-child-photo aura-inv-child-photo-empty"><span class="dashicons dashicons-format-image"></span></div>';

        var stateMap = { good: __('Bueno', 'aura-suite'), fair: __('Regular', 'aura-suite'), poor: __('Deficiente', 'aura-suite'), damaged: __('Dañado', 'aura-suite') };

        var h = '<div class="aura-inv-child-card">';
        h += '<div class="aura-inv-child-layout">';
        h += photoHtml;
        h += '<div class="aura-inv-child-main">';
        h += '<div class="aura-inv-child-top">';
        h += '<div><h4 class="aura-inv-child-title">' + escHtml(item.equipment_name || __('Equipo', 'aura-suite')) + '</h4>' +
             '<div class="aura-inv-child-sub">' + escHtml([item.equipment_brand, item.equipment_model, item.equipment_code ? sprintf(__('Cód: %s', 'aura-suite'), item.equipment_code) : ''].filter(Boolean).join(' · ') || sprintf(__('Movimiento de préstamo #%s', 'aura-suite'), item.loan_id)) + '</div></div>';
        h += '<span class="aura-loan-status-badge aura-loan-status-' + escHtml(item.loan_status) + '">' + escHtml(stLbl) + '</span>';
        h += '</div>';

        h += '<div class="aura-inv-child-specs">';
        h += childSpec(__('Prestatario', 'aura-suite'), item.borrower_display || '—');
        h += childSpec(__('Teléfono', 'aura-suite'), item.borrowed_to_phone || '—');
        h += childSpec(__('Proyecto / Motivo', 'aura-suite'), item.project || '—');
        h += childSpec(__('Fecha salida', 'aura-suite'), item.loan_date || '—');
        h += childSpec(__('Devolución esperada', 'aura-suite'), item.expected_return_date || '—');
        h += childSpec(__('Devolución real', 'aura-suite'), item.actual_return_date || __('Pendiente', 'aura-suite'));
        h += childSpec(__('Estado salida', 'aura-suite'), stateMap[item.equipment_state_out] || item.equipment_state_out || '—');
        h += childSpec(__('Estado regreso', 'aura-suite'), item.return_state ? (stateMap[item.return_state] || item.return_state) : '—');
        h += '</div>';

        if (item.return_observations) {
            h += '<div class="aura-inv-child-desc"><strong>' + escHtml(__('Observaciones de devolución:', 'aura-suite')) + '</strong> ' + escHtml(item.return_observations) + '</div>';
        }

        h += '<div class="aura-inv-child-actions">';
        if ((item.loan_status === 'active' || item.loan_status === 'overdue') && cfg.can_checkin) {
            h += '<button type="button" class="btn btn-indigo btn-sm js-btn-checkin" data-id="' + item.loan_id + '"><span class="dashicons dashicons-yes-alt"></span> ' + escHtml(__('Registrar Devolución', 'aura-suite')) + '</button>';
        }
        h += '<button type="button" class="btn btn-glass btn-sm js-btn-history" data-id="' + item.equipment_id + '" data-name="' + escHtml(item.equipment_name) + '"><span class="dashicons dashicons-clipboard"></span> ' + escHtml(__('Ver Historial', 'aura-suite')) + '</button>';
        if (cfg.can_loan_edit) {
            var json = window.JSON ? window.JSON.stringify(item).replace(/"/g, '&quot;') : '{}';
            h += '<button type="button" class="btn btn-glass btn-sm js-btn-edit" data-item="' + json + '"><span class="dashicons dashicons-edit"></span> ' + escHtml(__('Editar', 'aura-suite')) + '</button>';
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
                processing:  __('Cargando préstamos…', 'aura-suite'),
                zeroRecords: txt.no_loans || __('Sin préstamos registrados.', 'aura-suite'),
                info:        __('_TOTAL_ préstamos', 'aura-suite'),
                infoEmpty:   __('0 préstamos', 'aura-suite'),
                infoFiltered: __('(filtrado de _MAX_ total)', 'aura-suite'),
                lengthMenu:  __('Mostrar _MENU_ por página', 'aura-suite'),
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
                        return '<button type="button" class="aura-inv-row-toggle" data-tooltip="' + escHtml(__('Expandir detalle del préstamo', 'aura-suite')) + '" aria-expanded="false" aria-label="' + escHtml(__('Expandir', 'aura-suite')) + '">+</button>';
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
                        var mobMeta = [item.loan_date ? sprintf(__('Sal: %s', 'aura-suite'), item.loan_date) : '', item.expected_return_date ? sprintf(__('Dev: %s', 'aura-suite'), item.expected_return_date) : ''].filter(Boolean).join(' · ');
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
                            return '<span class="aura-loan-date-returned" data-tooltip="' + escHtml(__('Fecha real de devolución', 'aura-suite')) + '">' + escHtml(item.actual_return_date) + '</span>';
                        }
                        var h = escHtml(data || '');
                        if (item.loan_status === 'overdue' && item.days_overdue > 0) {
                            h += '<span class="aura-loan-overdue-days">+' + item.days_overdue + ' ' + escHtml(txt.days_ago || __('días', 'aura-suite')) + '</span>';
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
        }
        if ($('.aura-modal-overlay.active, .aura-modal-overlay.is-active').length) {
            $('body').css('overflow', 'hidden');
        } else {
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
        bindTwoPhaseBackdropGuard('aura-inv-contact-picker-modal', function () { closeModalById('aura-inv-contact-picker-modal'); });
        bindTwoPhaseBackdropGuard('js-modal-checkout', function () { closeModalById('js-modal-checkout'); });
        bindTwoPhaseBackdropGuard('js-modal-checkin', function () { closeModalById('js-modal-checkin'); });
        bindTwoPhaseBackdropGuard('js-modal-history', function () { closeModalById('js-modal-history'); });
        bindTwoPhaseBackdropGuard('js-modal-edit-loan', function () { closeModalById('js-modal-edit-loan'); });

        $(document).on('click', '.aura-loan-modal-close', function (e) {
            e.preventDefault();
            var $modal = $(this).closest('.aura-modal-overlay');
            if ($modal.length && $modal.attr('id')) {
                closeModalById($modal.attr('id'));
            }
        });

        // Capture-phase Escape guard para evitar que aura-ui cierre el modal padre detrás de pickers apilados
        if (!window.__auraLoansCaptureEscBound) {
            window.__auraLoansCaptureEscBound = true;
            window.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if ($('#aura-inv-contact-picker-modal').is(':visible')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    closeModalById('aura-inv-contact-picker-modal');
                    return;
                }
                if ($('#aura-inv-equip-picker-modal').is(':visible')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    closeModalById('aura-inv-equip-picker-modal');
                }
            }, true);
        }

        $(document).on('keydown.auraLoansEsc', function (e) {
            if (e.key !== 'Escape') return;
            if ($('#aura-tp-explorer-modal').is(':visible') || $('#aura-inv-equip-picker-modal').is(':visible') || $('#aura-inv-contact-picker-modal').is(':visible')) {
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
                    notifyToast(__('Préstamo actualizado correctamente.', 'aura-suite'), 'success');
                    setTimeout(function () { window.location.reload(); }, 450);
                },
                function (msg) {
                    $submit.prop('disabled', false);
                    $('#js-el-feedback').show().find('p').text(msg || __('Error al guardar.', 'aura-suite'));
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
        var msg = (cfg.txt.confirm_delete_loan || __('¿Eliminar este préstamo?', 'aura-suite'));
        if (eqName) msg = '«' + eqName + '» — ' + msg;
        if (!window.confirm(msg)) return;

        doAjax(
            'aura_inventory_delete_loan',
            { loan_id: loanId },
            function () {
                notifyToast(__('Préstamo eliminado.', 'aura-suite'), 'success');
                setTimeout(function () { window.location.reload(); }, 450);
            },
            function (err) {
                notifyToast(err || __('Error al eliminar.', 'aura-suite'), 'error');
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
            if (q.length < 1) {
                $('#co-equip-results').hide().empty();
                return;
            }
            equipTimer = setTimeout(function () {
                doAjax('aura_inventory_get_available_equipment', { search: q }, function (items) {
                    renderEquipResults(items);
                });
            }, 240);
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

        // Cambio exclusivo entre las 3 pestañas de Paso 2 (Usuario | Contacto Aura | Externo)
        $(document).on('click', '.aura-loan-btab', function (e) {
            if ($(e.target).closest('.aura-help-icon').length) return;
            e.preventDefault();
            switchBorrowerTab($(this).data('borrower-mode') || 'user');
        });

        // Helper para aplicar selección de usuario/tercero desde AuraThirdPartySelector
        function applyThirdPartyOrUserBorrower(item) {
            if (!item) return;
            var name   = item.commercial_name || item.name || item.display_name || '';
            var isUser = !!(item.is_wp_user || item.party_type === 'user' || item.user_id);

            if (isUser) {
                switchBorrowerTab('user');
                var uid = String(item.user_id || item.id || '');
                if (uid) {
                    var $sel = $('#co-borrower-user');
                    if (!$sel.find('option[value="' + uid + '"]').length) {
                        $('<option>').val(uid).text(name).appendTo($sel);
                    }
                    $sel.val(uid).trigger('change');
                    if (item.phone) $('#co-borrower-user-phone').val(item.phone);
                    setBorrowerSummary('user', name, item.email || (item.login ? '@' + item.login : ''));
                }
            } else {
                switchBorrowerTab('external');
                $('#co-borrower-ext-name').val(name);
                if (item.phone) $('#co-borrower-ext-phone').val(item.phone);
                setBorrowerSummary('third_party', name, item.phone || item.email || '');
            }
        }

        // Botones "Explorar Usuarios" (AuraThirdPartySelector -> pestaña Usuarios WP)
        $(document).on('click', '.aura-inv-trigger-user-explorer', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.AuraThirdPartySelector === 'undefined') {
                notifyToast(__('El modal de usuarios no está disponible en esta página.', 'aura-suite'), 'error');
                return;
            }
            window.AuraThirdPartySelector.openExplorer({
                defaultTab: 'employee',
                allowUsers: true,
                onSelect: applyThirdPartyOrUserBorrower
            });
        });

        // Botones "Explorar Directorio de Terceros" (AuraThirdPartySelector -> Empresas / Personas / Todo)
        $(document).on('click', '.aura-inv-trigger-tp-explorer', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.AuraThirdPartySelector === 'undefined') {
                notifyToast(__('El explorador de terceros no está disponible en esta página.', 'aura-suite'), 'error');
                return;
            }
            window.AuraThirdPartySelector.openExplorer({
                defaultTab: 'all',
                allowUsers: true,
                onSelect: applyThirdPartyOrUserBorrower
            });
        });

        // Eventos del Contacto Aura (autocompletado)
        $(document).on('aura:contact-selected', '#co-contact-selector', function (e, contact) {
            if (contact) {
                switchBorrowerTab('contact');
                var sub = [contact.category_label || '', contact.phone || contact.email || ''].filter(Boolean).join(' · ');
                setBorrowerSummary('contact', contact.name || __('Contacto', 'aura-suite'), sub);
            }
        });
        $(document).on('aura:contact-cleared', '#co-contact-selector', function () {
            if ($('#co-borrower-mode').val() === 'contact') {
                $('#co-borrower-name, #co-borrower-phone').val('');
                $('#aura-inv-co-borrower-badge').hide().empty();
            }
        });

        // Botón limpiar resumen de prestatario
        $(document).on('click', '.aura-inv-clear-borrower-summary', function (e) {
            e.preventDefault();
            if (window.AuraContactSelector && typeof window.AuraContactSelector.clear === 'function') {
                window.AuraContactSelector.clear('#co-contact-selector');
            }
            $('#co-borrower-name, #co-borrower-phone, #co-borrower-ext-name, #co-borrower-ext-phone, #co-borrower-user-phone').val('');
            if (cfg.current_user_id) {
                $('#co-borrower-user').val(String(cfg.current_user_id));
            }
            $('#aura-inv-co-borrower-badge').hide().empty();
        });

        // Chips de duración rápida (+3d, +7d, +15d, +30d)
        $(document).on('click', '.aura-loan-dur-chip', function (e) {
            e.preventDefault();
            var days = parseInt($(this).data('days') || '0', 10);
            if (!days) return;
            $('.aura-loan-dur-chip').removeClass('is-active');
            $(this).addClass('is-active');
            var baseStr = $('#co-loan-date').val() || cfg.today;
            var parts   = baseStr.split('-');
            if (parts.length === 3) {
                var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                d.setDate(d.getDate() + days);
                var yyyy = d.getFullYear();
                var mm   = String(d.getMonth() + 1).padStart(2, '0');
                var dd   = String(d.getDate()).padStart(2, '0');
                $('#co-expected-return').val(yyyy + '-' + mm + '-' + dd);
            }
        });

        $(document).on('submit', '#js-form-checkout', function (e) {
            e.preventDefault();
            submitCheckout();
        });

        $(document).on('change', '#co-loan-date', function () {
            $('#co-expected-return').attr('min', $(this).val());
        });
    }

    function switchBorrowerTab(mode) {
        if (['user', 'contact', 'external'].indexOf(mode) === -1) mode = 'user';
        $('#co-borrower-mode').val(mode);
        $('.aura-loan-btab').removeClass('active').attr('aria-selected', 'false');
        $('.aura-loan-btab[data-borrower-mode="' + mode + '"]').addClass('active').attr('aria-selected', 'true');
        $('.aura-loan-bpanel').hide();
        $('#co-borrower-panel-' + mode).show();

        // Actualizar o limpiar banner resumen según el modo activo para evitar confusión
        if (mode === 'user') {
            var uName = $.trim($('#co-borrower-user option:selected').text());
            var pillKind = $('#aura-inv-co-borrower-badge .aura-inv-link-pill').attr('data-kind') || '';
            if (pillKind === 'contact' || pillKind === 'third_party') {
                $('#aura-inv-co-borrower-badge').hide().empty();
            } else if (uName && $('#co-borrower-user').val() && String($('#co-borrower-user').val()) !== String(cfg.current_user_id || '')) {
                setBorrowerSummary('user', uName, $('#co-borrower-user-phone').val() || '');
            }
        } else if (mode === 'contact') {
            var cId = $('#co-contact-selector').val();
            if (cId && $('#co-borrower-name').val()) {
                setBorrowerSummary('contact', $('#co-borrower-name').val(), $('#co-borrower-phone').val() || '');
            } else {
                $('#aura-inv-co-borrower-badge').hide().empty();
            }
        } else if (mode === 'external') {
            var extName = $.trim($('#co-borrower-ext-name').val());
            if (extName) {
                setBorrowerSummary('third_party', extName, $('#co-borrower-ext-phone').val() || '');
            } else {
                $('#aura-inv-co-borrower-badge').hide().empty();
            }
        }
    }

    function setBorrowerSummary(kind, title, subtitle) {
        var icons = { user: '👤', contact: '📇', third_party: '🏢' };
        var tags  = { user: __('Usuario del sistema', 'aura-suite'), contact: __('Contacto Aura CRM', 'aura-suite'), third_party: __('Tercero externo', 'aura-suite') };
        var html  = '<div class="aura-loan-bsum-left">' +
            '<span class="aura-loan-bsum-icon">' + (icons[kind] || '👤') + '</span>' +
            '<div>' +
            '<div class="aura-loan-bsum-name">' + escHtml(title) + ' <span class="aura-inv-link-pill" data-kind="' + escHtml(kind) + '">' + escHtml(tags[kind] || '') + '</span></div>' +
            (subtitle ? '<div class="aura-loan-bsum-sub">' + escHtml(subtitle) + '</div>' : '') +
            '</div></div>' +
            '<button type="button" class="btn btn-glass btn-xs aura-inv-clear-borrower-summary" data-tooltip="' + escHtml(__('Quitar prestatario seleccionado', 'aura-suite')) + '">&times;</button>';
        $('#aura-inv-co-borrower-badge').html(html).css('display', 'flex');
    }

    // ─── EXPLORADOR DE CONTACTOS DE AURA (STACKED MODAL) ──────
    function bindContactPickerModal() {
        $(document).on('click', '.aura-inv-trigger-contact-explorer', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openContactPickerModal();
        });
        $('#aura-inv-cp-close, #aura-inv-cp-cancel').on('click', function (e) {
            e.preventDefault();
            closeModalById('aura-inv-contact-picker-modal');
        });
        var cpTimer;
        $('#aura-inv-cp-search').on('input', function () {
            clearTimeout(cpTimer);
            var q = $.trim($(this).val());
            if (!q && contactListCache) {
                filterAndRenderContactRows();
                return;
            }
            cpTimer = setTimeout(function () {
                if (q.length >= 2) {
                    fetchContactsForPicker(q);
                } else {
                    filterAndRenderContactRows();
                }
            }, 220);
        });
        $(document).on('click', '#aura-inv-cp-chips .aura-equip-chip', function (e) {
            e.preventDefault();
            $('#aura-inv-cp-chips .aura-equip-chip').removeClass('is-active');
            $(this).addClass('is-active');
            filterAndRenderContactRows();
        });
        $(document).on('click', '.aura-inv-cp-select-btn, .aura-inv-cp-row', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.aura-inv-cp-row');
            var c    = $row.data('contact');
            if (!c) return;
            switchBorrowerTab('contact');
            if (window.AuraContactSelector && typeof window.AuraContactSelector.setContact === 'function') {
                window.AuraContactSelector.setContact('#co-contact-selector', c);
            } else {
                $('#co-borrower-name').val(c.name || '');
                $('#co-borrower-phone').val(c.phone || '');
                var sub = [c.category_label || '', c.phone || c.email || ''].filter(Boolean).join(' · ');
                setBorrowerSummary('contact', c.name || __('Contacto', 'aura-suite'), sub);
            }
            closeModalById('aura-inv-contact-picker-modal');
        });
    }

    function fetchContactsForPicker(query) {
        var csCfg = window.auraContactSelectorCfg || {};
        $.ajax({
            url: csCfg.ajaxUrl || cfg.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_contacts_selector_search',
                nonce: csCfg.nonce || cfg.nonce,
                q: query || '',
                category: 'all',
                limit: 30
            },
            success: function (res) {
                var items = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                if (!query) {
                    contactListCache = items;
                }
                populateContactPickerCategories(items);
                filterAndRenderContactRows(items);
                hideModalSkeletonById('aura-inv-contact-picker-modal');
                setTimeout(function () { $('#aura-inv-cp-search').focus(); }, 120);
            },
            error: function () {
                $('#aura-inv-cp-tbody').html('<tr><td colspan="4" class="aura-inv-empty-picker">' + escHtml(__('Error al cargar contactos.', 'aura-suite')) + '</td></tr>');
                hideModalSkeletonById('aura-inv-contact-picker-modal');
            }
        });
    }

    function openContactPickerModal() {
        $('#aura-inv-cp-search').val('');
        $('#aura-inv-cp-tbody').empty();
        $('#aura-inv-cp-chips').empty();
        $('#aura-inv-cp-empty').hide();
        $('#aura-inv-cp-count').text(__('Cargando contactos…', 'aura-suite'));
        openModalById('aura-inv-contact-picker-modal', true);

        if (contactListCache) {
            populateContactPickerCategories(contactListCache);
            filterAndRenderContactRows(contactListCache);
            hideModalSkeletonById('aura-inv-contact-picker-modal');
            setTimeout(function () { $('#aura-inv-cp-search').focus(); }, 120);
            return;
        }
        fetchContactsForPicker('');
    }

    function populateContactPickerCategories(items) {
        var cats = {};
        (items || []).forEach(function (it) {
            var lbl = it.category_label || it.category || __('Otro', 'aura-suite');
            cats[lbl] = (cats[lbl] || 0) + 1;
        });
        var $chips = $('#aura-inv-cp-chips').empty();
        $chips.append('<button type="button" class="aura-equip-chip is-active" data-cat="">' + escHtml(sprintf(__('Todos (%s)', 'aura-suite'), items.length)) + '</button>');
        Object.keys(cats).sort().forEach(function (c) {
            $('<button type="button" class="aura-equip-chip">')
                .attr('data-cat', c)
                .text(c + ' (' + cats[c] + ')')
                .appendTo($chips);
        });
    }

    function filterAndRenderContactRows(overrideItems) {
        var items = overrideItems || contactListCache || [];
        var q     = $.trim($('#aura-inv-cp-search').val()).toLowerCase();
        var cat   = $('#aura-inv-cp-chips .aura-equip-chip.is-active').attr('data-cat') || '';

        var filtered = items.filter(function (c) {
            var lbl = c.category_label || c.category || __('Otro', 'aura-suite');
            if (cat && lbl !== cat) return false;
            if (q) {
                var hay = [c.name, c.email, c.phone, c.organization, c.job_title, lbl].filter(Boolean).join(' ').toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });

        $('#aura-inv-cp-count').text(sprintf(__('%s contactos encontrados', 'aura-suite'), filtered.length));
        var $tbody = $('#aura-inv-cp-tbody').empty();
        $('#aura-inv-cp-empty').toggle(filtered.length === 0);

        filtered.forEach(function (c) {
            var initials = (c.name || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return (w[0] || '').toUpperCase(); }).join('');
            var avatar = c.avatar
                ? '<img src="' + escHtml(c.avatar) + '" width="32" height="32" class="aura-inv-mini-avatar" alt="">'
                : '<span class="aura-inv-mini-initials">' + escHtml(initials || '?') + '</span>';
            var subMeta = [c.organization, c.job_title].filter(Boolean).join(' · ');
            var contactData = [c.phone, c.email].filter(Boolean).join(' · ');

            var $tr = $('<tr class="aura-inv-cp-row aura-inv-ep-row">')
                .data('contact', c)
                .html(
                    '<td><div style="display:flex;align-items:center;">' + avatar +
                    '<div><div class="aura-inv-ep-name">' + escHtml(c.name || '—') + '</div>' +
                    (subMeta ? '<div class="aura-inv-ep-meta">' + escHtml(subMeta) + '</div>' : '') +
                    '</div></div></td>' +
                    '<td class="aura-inv-col-secondary"><span class="aura-inv-ep-cat">' + escHtml(c.category_label || c.category || __('Contacto', 'aura-suite')) + '</span></td>' +
                    '<td class="aura-inv-col-secondary"><div class="aura-inv-ep-meta">' + escHtml(contactData || '—') + '</div></td>' +
                    '<td style="text-align:right;"><button type="button" class="btn btn-indigo btn-sm aura-inv-cp-select-btn"><span class="dashicons dashicons-yes"></span> ' + escHtml(__('Seleccionar', 'aura-suite')) + '</button></td>'
                );
            $tbody.append($tr);
        });
    }

    // ─── CATÁLOGO RÁPIDO DE EQUIPOS DISPONIBLES (STACKED) ──────
    function bindEquipPickerModal() {
        $(document).on('click', '#aura-inv-btn-equip-picker', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openEquipPickerModal();
        });
        $('#aura-inv-ep-close, #aura-inv-ep-cancel').on('click', function (e) {
            e.preventDefault();
            closeModalById('aura-inv-equip-picker-modal');
        });
        $('#aura-inv-ep-search').on('input', function () {
            filterAndRenderPickerRows();
        });
        $('#aura-inv-ep-category').on('change', function () {
            var cat = $(this).val();
            $('#aura-inv-ep-chips .aura-equip-chip').removeClass('is-active');
            $('#aura-inv-ep-chips .aura-equip-chip[data-cat="' + cat.replace(/"/g, '\\"') + '"]').addClass('is-active');
            filterAndRenderPickerRows();
        });
        $(document).on('click', '#aura-inv-ep-chips .aura-equip-chip', function (e) {
            e.preventDefault();
            var cat = $(this).data('cat') || '';
            $('#aura-inv-ep-chips .aura-equip-chip').removeClass('is-active');
            $(this).addClass('is-active');
            $('#aura-inv-ep-category').val(cat);
            filterAndRenderPickerRows();
        });
        $(document).on('click', '.aura-inv-ep-select-btn, .aura-inv-ep-row', function (e) {
            if ($(e.target).closest('.aura-table-thumb-preview').length) return;
            e.preventDefault();
            var $row = $(this).closest('.aura-inv-ep-row');
            if (!$row.length) return;
            selectEquip($row.data('id'), $row.data('name'), $row.data('meta'));
            closeModalById('aura-inv-equip-picker-modal');
        });
    }

    function openEquipPickerModal() {
        $('#aura-inv-ep-search').val('');
        $('#aura-inv-ep-category').val('');
        $('#aura-inv-ep-tbody').empty();
        $('#aura-inv-ep-chips').empty();
        $('#aura-inv-ep-empty').hide();
        $('#aura-inv-ep-count').text(__('Cargando catálogo…', 'aura-suite'));
        openModalById('aura-inv-equip-picker-modal', true);

        if (equipListCache) {
            populatePickerCategories(equipListCache);
            filterAndRenderPickerRows();
            hideModalSkeletonById('aura-inv-equip-picker-modal');
            setTimeout(function () { $('#aura-inv-ep-search').focus(); }, 180);
            return;
        }

        doAjax('aura_inventory_get_available_equipment', { search: '' }, function (items) {
            equipListCache = items || [];
            populatePickerCategories(equipListCache);
            filterAndRenderPickerRows();
            hideModalSkeletonById('aura-inv-equip-picker-modal');
            setTimeout(function () { $('#aura-inv-ep-search').focus(); }, 180);
        }, function (err) {
            $('#aura-inv-ep-tbody').html('<tr><td colspan="5" class="aura-inv-empty-picker">' + escHtml(err || __('Error al cargar equipos.', 'aura-suite')) + '</td></tr>');
            hideModalSkeletonById('aura-inv-equip-picker-modal');
        });
    }

    function populatePickerCategories(items) {
        var counts = {};
        items.forEach(function (it) {
            var c = it.category || __('Sin categoría', 'aura-suite');
            counts[c] = (counts[c] || 0) + 1;
        });
        var $sel = $('#aura-inv-ep-category').empty().append('<option value="">' + escHtml(sprintf(__('Todas las categorías (%s)', 'aura-suite'), items.length)) + '</option>');
        var $chips = $('#aura-inv-ep-chips').empty();
        $chips.append('<button type="button" class="aura-equip-chip is-active" data-cat="">' + escHtml(sprintf(__('Todos (%s)', 'aura-suite'), items.length)) + '</button>');

        Object.keys(counts).sort().forEach(function (c) {
            $('<option>').val(c).text(c + ' (' + counts[c] + ')').appendTo($sel);
            $('<button type="button" class="aura-equip-chip">')
                .attr('data-cat', c)
                .text(c + ' (' + counts[c] + ')')
                .appendTo($chips);
        });
    }

    function filterAndRenderPickerRows() {
        var items = equipListCache || [];
        var q     = $.trim($('#aura-inv-ep-search').val()).toLowerCase();
        var cat   = $('#aura-inv-ep-category').val();

        var filtered = items.filter(function (it) {
            var itemCat = it.category || __('Sin categoría', 'aura-suite');
            if (cat && itemCat !== cat) return false;
            if (q) {
                var hay = [it.name, it.brand, it.model, it.internal_code, it.serial_number, it.category, it.location]
                    .filter(Boolean).join(' ').toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });

        $('#aura-inv-ep-count').text(sprintf(__('%s de %s equipos disponibles', 'aura-suite'), filtered.length, items.length));
        var $tbody = $('#aura-inv-ep-tbody').empty();
        $('#aura-inv-ep-empty').toggle(filtered.length === 0);

        if (!filtered.length) return;

        filtered.forEach(function (it) {
            var meta     = [it.brand, it.model, it.internal_code ? sprintf(__('Cód: %s', 'aura-suite'), it.internal_code) : ''].filter(Boolean).join(' · ');
            var fullUrl  = it.photo_full_url || it.photo_thumb_url;
            var thumbUrl = it.photo_thumb_url || it.photo_full_url;
            var imgHtml;

            if (thumbUrl) {
                var hoverCard = '<div class="aura-floating-img-card aura-inv-photo-card">' +
                    '<img src="' + escHtml(fullUrl) + '" alt="' + escHtml(it.name) + '" style="max-width:240px;max-height:240px;width:auto;height:auto;object-fit:contain;border-radius:8px;display:block;margin:0 auto;">' +
                    '<span class="aura-floating-img-title">' + escHtml(it.name) + '</span>' +
                    (meta ? '<span class="aura-floating-img-sub">' + escHtml(meta) + '</span>' : '') +
                    '</div>';
                imgHtml = '<div class="aura-inv-thumb-wrap aura-table-thumb-preview" ' +
                    'data-img-url="' + escHtml(fullUrl) + '" ' +
                    'data-preview-title="' + escHtml(it.name) + '" ' +
                    'data-aura-tooltip="' + escHtml(hoverCard) + '">' +
                    '<img src="' + escHtml(thumbUrl) + '" alt="' + escHtml(it.name) + '" loading="lazy">' +
                    '</div>';
            } else {
                imgHtml = '<div class="aura-inv-thumb-empty"><span class="dashicons dashicons-format-image"></span></div>';
            }

            var $tr = $('<tr class="aura-inv-ep-row">')
                .data('id', it.id)
                .data('name', it.name)
                .data('meta', meta)
                .html(
                    '<td style="text-align:center;">' + imgHtml + '</td>' +
                    '<td>' +
                        '<div class="aura-inv-ep-name">' + escHtml(it.name) + '</div>' +
                        (meta ? '<div class="aura-inv-ep-meta">' + escHtml(meta) + '</div>' : '') +
                        (it.serial_number ? '<div class="aura-inv-ep-meta">' + escHtml(sprintf(__('S/N: %s', 'aura-suite'), it.serial_number)) + '</div>' : '') +
                    '</td>' +
                    '<td class="aura-inv-col-secondary">' + (it.category ? '<span class="aura-inv-ep-cat">' + escHtml(it.category) + '</span>' : '<span class="aura-inv-muted">—</span>') + '</td>' +
                    '<td class="aura-inv-col-secondary">' + (it.location ? '<span class="aura-inv-ep-loc">📍 ' + escHtml(it.location) + '</span>' : '<span class="aura-inv-muted">—</span>') + '</td>' +
                    '<td style="text-align:right;"><button type="button" class="btn btn-indigo btn-sm aura-inv-ep-select-btn"><span class="dashicons dashicons-yes"></span> ' + escHtml(__('Seleccionar', 'aura-suite')) + '</button></td>'
                );
            $tbody.append($tr);
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
                  '<button type="button" class="selected-clear dashicons dashicons-no-alt" data-tooltip="' + escHtml(__('Cambiar equipo', 'aura-suite')) + '" aria-label="' + escHtml(__('Cambiar', 'aura-suite')) + '"></button>')
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
            $('#co-equip-list-items').html('<p class="aura-inv-text-danger">' + escHtml(__('Error al cargar equipos.', 'aura-suite')) + '</p>');
        });
    }

    function renderEquipList(items) {
        var $sel = $('#co-equip-list-select').empty();
        $sel.append('<option value="">' + escHtml(sprintf(__('-- Seleccione un equipo (%s disponibles) --', 'aura-suite'), items.length)) + '</option>');

        if (!items.length) {
            $sel.append('<option value="" disabled>' + escHtml(__('Sin equipos disponibles.', 'aura-suite')) + '</option>');
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

    function openCheckoutModal(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
            e.stopPropagation();
        }
        equipListCache = null;
        resetCheckoutForm();
        openModalById('js-modal-checkout', false);
        setTimeout(function () { $('#co-equipment-search').focus(); }, 100);
    }

    function resetCheckoutForm() {
        var $form = $('#js-form-checkout');
        if ($form.length) $form[0].reset();
        $('#co-loan-date').val(cfg.today);
        $('#co-expected-return').val(cfg.default_return || '').attr('min', cfg.today);
        $('.aura-loan-dur-chip').removeClass('is-active');
        $('.aura-loan-dur-chip.is-default').addClass('is-active');
        if (window.AuraContactSelector && typeof window.AuraContactSelector.clear === 'function') {
            window.AuraContactSelector.clear('#co-contact-selector');
        }
        $('#co-borrower-name, #co-borrower-phone, #co-borrower-ext-name, #co-borrower-ext-phone, #co-borrower-user-phone').val('');
        if (cfg.current_user_id) {
            $('#co-borrower-user').val(String(cfg.current_user_id));
        }
        switchBorrowerTab('user');
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
            showModalMsg($msg, 'error', cfg.txt.select_equip || __('Selecciona un equipo primero.', 'aura-suite'));
            $btn.prop('disabled', false);
            return;
        }

        var mode = $('#co-borrower-mode').val() || 'user';
        var contactId    = 0;
        var borrowedUser = cfg.current_user_id || $('#co-borrower-user').val() || 0;
        var borrowedName = '';
        var borrowedPhone = '';

        if (mode === 'user') {
            borrowedUser  = $('#co-borrower-user').val() || cfg.current_user_id || 0;
            borrowedName  = '';
            borrowedPhone = $('#co-borrower-user-phone').val() || '';
        } else if (mode === 'contact') {
            contactId     = $('#co-contact-selector').val() || 0;
            borrowedUser  = $('#co-borrower-user').val() || cfg.current_user_id || 0;
            borrowedName  = $('#co-borrower-name').val() || '';
            borrowedPhone = $('#co-borrower-phone').val() || '';
            if (!contactId && !$.trim(borrowedName)) {
                showModalMsg($msg, 'error', __('Selecciona un Contacto de Aura CRM o cambia a otra pestaña.', 'aura-suite'));
                $btn.prop('disabled', false);
                return;
            }
        } else {
            borrowedUser  = cfg.current_user_id || 0;
            borrowedName  = $.trim($('#co-borrower-ext-name').val() || '');
            borrowedPhone = $('#co-borrower-ext-phone').val() || '';
            if (!borrowedName) {
                showModalMsg($msg, 'error', __('Escribe el nombre de la persona o elige desde Terceros.', 'aura-suite'));
                $('#co-borrower-ext-name').focus();
                $btn.prop('disabled', false);
                return;
            }
        }

        var data = {
            equipment_id:         equipId,
            contact_id:           contactId,
            loan_date:            $('#co-loan-date').val(),
            expected_return_date: $('#co-expected-return').val(),
            borrowed_by_user_id:  borrowedUser,
            borrowed_to_name:     borrowedName,
            borrowed_to_phone:    borrowedPhone,
            project:              $('#co-project').val(),
            equipment_state_out:  $('input[name="equipment_state_out"]:checked').val() || 'good',
        };

        doAjax('aura_inventory_checkout_equipment', data,
            function (res) {
                $btn.prop('disabled', false);
                showModalMsg($msg, 'success', res.message || __('✅ Préstamo registrado.', 'aura-suite'));
                notifyToast(res.message || __('Préstamo registrado.', 'aura-suite'), 'success');
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
                overdue  = ' <span class="sum-overdue">(' + escHtml(sprintf(__('+%s días de retraso', 'aura-suite'), days)) + ')</span>';
            }

            $summ.html(
                '<div class="sum-row"><span class="sum-label">' + escHtml(__('Equipo', 'aura-suite')) + '</span>' +
                '<span class="sum-value sum-value-equip">' + escHtml((loan.equipment_name || '') + (loan.equipment_brand ? ' · ' + loan.equipment_brand : '')) + '</span></div>' +
                '<div class="sum-row"><span class="sum-label">' + escHtml(__('Prestatario', 'aura-suite')) + '</span>' +
                '<div class="sum-value">' + buildBorrowerCell(loan) + '</div></div>' +
                '<div class="sum-row"><span class="sum-label">' + escHtml(__('Fecha salida', 'aura-suite')) + '</span>' +
                '<span class="sum-value">' + escHtml(loan.loan_date || '-') + '</span></div>' +
                '<div class="sum-row"><span class="sum-label">' + escHtml(__('Devolución esperada', 'aura-suite')) + '</span>' +
                '<span class="sum-value">' + escHtml(loan.expected_return_date || '-') + overdue + '</span></div>'
            );
            hideModalSkeletonById('js-modal-checkin');
        }, function () {
            $summ.html('<p class="aura-inv-text-danger">' + escHtml(__('Error al cargar el préstamo.', 'aura-suite')) + '</p>');
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
                showModalMsg($msg, 'success', res.message || __('✅ Devolución registrada.', 'aura-suite'));
                notifyToast(res.message || __('Devolución registrada.', 'aura-suite'), 'success');
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
        $('#js-history-title').text(sprintf(__('Historial de préstamos: %s', 'aura-suite'), equipmentName));
        $('#js-history-meta').text('');

        openModalById('js-modal-history', true);

        doAjax('aura_inventory_get_equipment_loan_history', { equipment_id: equipmentId }, function (items) {
            if (!items || !items.length) {
                $body.html(
                    '<div class="aura-loan-history-empty">' +
                    '<span class="dashicons dashicons-clipboard"></span>' +
                    '<p>' + escHtml(cfg.txt.no_history || __('Sin préstamos registrados para este equipo.', 'aura-suite')) + '</p>' +
                    '</div>'
                );
                $('#js-history-meta').text(__('0 registros', 'aura-suite'));
                hideModalSkeletonById('js-modal-history');
                return;
            }

            var countActive = 0, countOverdue = 0, countReturned = 0;
            items.forEach(function (it) {
                if (it.loan_status === 'active') countActive++;
                else if (it.loan_status === 'overdue') countOverdue++;
                else if (it.loan_status === 'returned') countReturned++;
            });

            var metaParts = [sprintf(__('%s registros', 'aura-suite'), items.length)];
            if (countActive)   metaParts.push(sprintf(__('%s activo(s)', 'aura-suite'), countActive));
            if (countOverdue)  metaParts.push('<span class="aura-inv-text-danger">' + escHtml(sprintf(__('%s vencido(s)', 'aura-suite'), countOverdue)) + '</span>');
            if (countReturned) metaParts.push(sprintf(__('%s devuelto(s)', 'aura-suite'), countReturned));
            $('#js-history-meta').html(metaParts.join(' · '));

            var statusLabel = function (s) {
                return { active: cfg.txt.active || __('Activo', 'aura-suite'), overdue: cfg.txt.overdue || __('Vencido', 'aura-suite'), returned: cfg.txt.returned || __('Devuelto', 'aura-suite') }[s] || s;
            };
            var stateLabel = function (s) {
                if (!s) return '<span class="aura-loan-history-na">—</span>';
                var map = { good: __('Bueno', 'aura-suite'), fair: __('Regular', 'aura-suite'), poor: __('Deficiente', 'aura-suite'), damaged: __('Dañado', 'aura-suite') };
                return escHtml(map[s] || s);
            };

            var html = '<div class="aura-loan-history-table-wrap">' +
                '<table class="aura-table aura-loan-history-table">' +
                '<thead><tr>' +
                '<th>' + escHtml(__('Prestatario', 'aura-suite')) + '</th>' +
                '<th>' + escHtml(__('Fecha salida', 'aura-suite')) + '</th>' +
                '<th>' + escHtml(__('Dev. esperada', 'aura-suite')) + '</th>' +
                '<th>' + escHtml(__('Dev. real', 'aura-suite')) + '</th>' +
                '<th>' + escHtml(__('Estado salida', 'aura-suite')) + '</th>' +
                '<th>' + escHtml(__('Estado entrada', 'aura-suite')) + '</th>' +
                '<th>' + escHtml(__('Estado préstamo', 'aura-suite')) + '</th>' +
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
