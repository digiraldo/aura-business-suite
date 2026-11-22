/**
 * Inventario — Mantenimientos JS (Aura Design System)
 * Módulos: MaintenanceList (DataTables + Child Rows +) y MaintenanceForm (Ficha + Picker)
 */
/* global jQuery, DataTable, auraMaintList, auraMaintForm, AuraUI, wp */
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

    if (window.__auraInvMaintBooted) {
        return;
    }
    window.__auraInvMaintBooted = true;

    // ─────────────────────────────────────────────────────────────
    // MÓDULO: LISTADO DE MANTENIMIENTOS
    // ─────────────────────────────────────────────────────────────
    var MaintenanceList = {

        cfg:   null,
        table: null,

        init: function () {
            if (typeof auraMaintList === 'undefined') return;
            if (!$('#aura-maint-table').length) return;
            this.cfg = auraMaintList;

            if (this.cfg.preselectEquip) {
                $('#aura-maint-filter-equipment').val(this.cfg.preselectEquip);
            }
            this.initDataTable();
            this.bindEvents();
        },

        _thumb: function (m) {
            var full  = m.photo_full_url || m.photo_thumb_url;
            var thumb = m.photo_thumb_url || m.photo_full_url;
            if (!full) {
                return '<div class="aura-inv-thumb-empty" data-tooltip="' + escHtml(__('Sin fotografía', 'aura-suite')) + '"><span class="dashicons dashicons-format-image"></span></div>';
            }
            var label = m.equipment_name || __('Equipo', 'aura-suite');
            var card  = '<div class="aura-floating-img-card aura-inv-photo-card">' +
                '<img src="' + escHtml(full) + '" alt="' + escHtml(label) + '" style="max-width:260px;max-height:260px;width:auto;height:auto;object-fit:contain;border-radius:8px;display:block;margin:0 auto;">' +
                '<span class="aura-floating-img-title">' + escHtml(label) + '</span>' +
                (m.equipment_code ? '<span class="aura-floating-img-sub">' + escHtml(sprintf(__('Cód: %s', 'aura-suite'), m.equipment_code)) + '</span>' : '') +
                '</div>';

            return '<div class="aura-inv-thumb-wrap aura-table-thumb-preview" ' +
                'data-img-url="' + escHtml(full) + '" ' +
                'data-preview-title="' + escHtml(label) + '" ' +
                'data-aura-tooltip="' + escHtml(card) + '">' +
                '<img src="' + escHtml(thumb) + '" alt="' + escHtml(label) + '" loading="lazy">' +
                '</div>';
        },

        _typeBadge: function (m) {
            var cfg = this.cfg;
            var lbl = (cfg.txt && cfg.txt.type_labels && cfg.txt.type_labels[m.type]) || m.type;
            return '<span class="aura-maint-type-badge aura-maint-type-' + escHtml(m.type) + '">' + escHtml(lbl) + '</span>';
        },

        _executor: function (m) {
            var cfg = this.cfg;
            return m.performed_by === 'external'
                ? '<span class="aura-maint-exec-badge external"><span class="dashicons dashicons-store"></span>' +
                  escHtml(m.workshop_name || (cfg.txt && cfg.txt.performed_labels.external) || __('Externo', 'aura-suite')) + '</span>'
                : '<span class="aura-maint-exec-badge"><span class="dashicons dashicons-businessman"></span>' +
                  escHtml(m.tech_name || (cfg.txt && cfg.txt.performed_labels.internal) || __('Interno', 'aura-suite')) + '</span>';
        },

        _costCell: function (m) {
            var currency = this.cfg.currency || '$';
            return m.total_cost > 0
                ? '<span class="aura-maint-cost-cell">' + escHtml(currency) +
                  parseFloat(m.total_cost).toLocaleString('es', { minimumFractionDigits: 2 }) + '</span>'
                : '<span class="aura-maint-cost-cell zero">—</span>';
        },

        _psBadge: function (m) {
            var cfg = this.cfg;
            var lbl = (cfg.txt && cfg.txt.post_status_labels && cfg.txt.post_status_labels[m.post_status]) || m.post_status;
            return '<span class="aura-maint-ps-badge aura-maint-ps-' + escHtml(m.post_status) + '">' + escHtml(lbl) + '</span>';
        },

        _finBadge: function (m) {
            var cfg = this.cfg;
            return m.has_finance
                ? '<span class="aura-maint-finance-ok" data-tooltip="' + escHtml(__('Gasto registrado en Finanzas', 'aura-suite')) + '"><span class="dashicons dashicons-yes-alt"></span>' + escHtml((cfg.txt && cfg.txt.has_finance) || __('Vinculado', 'aura-suite')) + '</span>'
                : '<span class="aura-inv-muted">' + escHtml((cfg.txt && cfg.txt.no_finance) || '—') + '</span>';
        },

        _buildTooltip: function (m) {
            var cfg = this.cfg;
            var currency = cfg.currency || '$';
            var typeLbl  = (cfg.txt && cfg.txt.type_labels && cfg.txt.type_labels[m.type]) || m.type;
            var execLbl  = m.performed_by === 'external' ? (m.workshop_name || __('Taller externo', 'aura-suite')) : (m.tech_name || __('Personal interno', 'aura-suite'));
            return '<div class="aura-tip-card">' +
                '<div class="aura-tip-card-header"><div class="aura-tip-info">' +
                '<div class="aura-tip-title">' + escHtml(m.equipment_name || __('Equipo', 'aura-suite')) + '</div>' +
                '</div></div>' +
                '<div class="aura-tip-card-body">' +
                '<div class="aura-tip-row"><span>' + escHtml(__('Fecha', 'aura-suite')) + '</span><strong>' + escHtml(m.maintenance_date || '—') + '</strong></div>' +
                '<div class="aura-tip-row"><span>' + escHtml(__('Tipo', 'aura-suite')) + '</span><strong>' + escHtml(typeLbl) + '</strong></div>' +
                '<div class="aura-tip-row"><span>' + escHtml(__('Ejecutor', 'aura-suite')) + '</span><strong>' + escHtml(execLbl) + '</strong></div>' +
                '<div class="aura-tip-row"><span>' + escHtml(__('Costo total', 'aura-suite')) + '</span><strong>' + escHtml(currency) + parseFloat(m.total_cost || 0).toLocaleString('es', { minimumFractionDigits: 2 }) + '</strong></div>' +
                '</div></div>';
        },

        _actions: function (m) {
            var h = '<div class="aura-row-actions">';
            h += '<button type="button" class="aura-btn-action-icon aura-btn-view aura-maint-btn-detail" data-id="' + m.id + '" data-tooltip="' + escHtml(__('Ver ficha completa del servicio', 'aura-suite')) + '"><span class="dashicons dashicons-visibility"></span></button>';
            if (m.can_edit) {
                h += '<button type="button" class="aura-btn-action-icon aura-btn-edit aura-maint-btn-edit" data-id="' + m.id + '" data-tooltip="' + escHtml(__('Editar mantenimiento', 'aura-suite')) + '"><span class="dashicons dashicons-edit"></span></button>';
            }
            if (m.can_delete) {
                h += '<button type="button" class="aura-btn-action-icon aura-btn-delete delete aura-maint-btn-delete" data-id="' + m.id + '" data-tooltip="' + escHtml(__('Eliminar registro', 'aura-suite')) + '"><span class="dashicons dashicons-trash"></span></button>';
            }
            h += '</div>';
            return h;
        },

        renderMaintChildCard: function (m) {
            var cfg      = this.cfg;
            var currency = cfg.currency || '$';
            var full     = m.photo_full_url || m.photo_thumb_url;
            var thumb    = m.photo_thumb_url || m.photo_full_url;
            var photoHtml = full
                ? '<div class="aura-inv-child-photo aura-table-thumb-preview" data-img-url="' + escHtml(full) + '" data-preview-title="' + escHtml(m.equipment_name) + '"><img src="' + escHtml(thumb) + '" alt="' + escHtml(m.equipment_name) + '"></div>'
                : '<div class="aura-inv-child-photo aura-inv-child-photo-empty"><span class="dashicons dashicons-format-image"></span></div>';

            var typeLbl = (cfg.txt && cfg.txt.type_labels && cfg.txt.type_labels[m.type]) || m.type;
            var psLbl   = (cfg.txt && cfg.txt.post_status_labels && cfg.txt.post_status_labels[m.post_status]) || m.post_status;
            var execLbl = m.performed_by === 'external' ? (m.workshop_name || __('Taller externo', 'aura-suite')) : (m.tech_name || __('Personal interno', 'aura-suite'));

            var h = '<div class="aura-inv-child-card">';
            h += '<div class="aura-inv-child-layout">';
            h += photoHtml;
            h += '<div class="aura-inv-child-main">';
            h += '<div class="aura-inv-child-top">';
            h += '<div><h4 class="aura-inv-child-title">' + escHtml(m.equipment_name) + '</h4>' +
                 '<div class="aura-inv-child-sub">' + escHtml([m.equipment_brand, m.equipment_code ? sprintf(__('Cód: %s', 'aura-suite'), m.equipment_code) : '', sprintf(__('Orden #%s', 'aura-suite'), m.id)].filter(Boolean).join(' · ')) + '</div></div>';
            h += '<span class="aura-maint-ps-badge aura-maint-ps-' + escHtml(m.post_status) + '">' + escHtml(psLbl) + '</span>';
            h += '</div>';

            h += '<div class="aura-inv-child-specs">';
            h += childSpec(__('Fecha ejecución', 'aura-suite'), m.maintenance_date || '—');
            h += childSpec(__('Tipo servicio', 'aura-suite'), typeLbl);
            h += childSpec(__('Ejecutor', 'aura-suite'), execLbl);
            h += childSpec(__('Horas registradas', 'aura-suite'), m.equipment_hours !== null && m.equipment_hours !== undefined ? m.equipment_hours + ' h' : '—');
            h += childSpec(__('Repuestos', 'aura-suite'), currency + parseFloat(m.parts_cost || 0).toLocaleString('es', { minimumFractionDigits: 2 }));
            h += childSpec(__('Mano de obra', 'aura-suite'), currency + parseFloat(m.labor_cost || 0).toLocaleString('es', { minimumFractionDigits: 2 }));
            h += childSpec(__('Total', 'aura-suite'), currency + parseFloat(m.total_cost || 0).toLocaleString('es', { minimumFractionDigits: 2 }));
            h += childSpec(__('Próx. acción / Factura', 'aura-suite'), [m.next_action_date, m.invoice_number ? sprintf(__('Fact: %s', 'aura-suite'), m.invoice_number) : ''].filter(Boolean).join(' · ') || '—');
            h += '</div>';

            if (m.description) {
                h += '<div class="aura-inv-child-desc"><strong>' + escHtml(__('Trabajo realizado:', 'aura-suite')) + '</strong> ' + escHtml(m.description) + '</div>';
            }
            if (m.parts_replaced) {
                h += '<div class="aura-inv-child-desc"><strong>' + escHtml(__('Piezas reemplazadas:', 'aura-suite')) + '</strong> ' + escHtml(m.parts_replaced) + '</div>';
            }

            h += '<div class="aura-inv-child-actions">';
            h += '<button type="button" class="btn btn-indigo btn-sm aura-maint-btn-detail" data-id="' + m.id + '"><span class="dashicons dashicons-visibility"></span> ' + escHtml(__('Ver Ficha Completa', 'aura-suite')) + '</button>';
            if (m.can_edit) {
                h += '<button type="button" class="btn btn-glass btn-sm aura-maint-btn-edit" data-id="' + m.id + '"><span class="dashicons dashicons-edit"></span> ' + escHtml(__('Editar', 'aura-suite')) + '</button>';
            }
            if (m.can_delete) {
                h += '<button type="button" class="btn btn-glass btn-sm aura-btn-danger-outline aura-maint-btn-delete" data-id="' + m.id + '"><span class="dashicons dashicons-trash"></span> ' + escHtml(__('Eliminar', 'aura-suite')) + '</button>';
            }
            h += '</div>';

            h += '</div></div></div>';
            return h;
        },

        initDataTable: function () {
            var self = this;
            var cfg  = this.cfg;

            if ($.fn.DataTable.isDataTable('#aura-maint-table')) {
                this.table = $('#aura-maint-table').DataTable();
                return;
            }

            this.table = new DataTable('#aura-maint-table', {
                responsive: false,
                processing: true,
                serverSide: false,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                order: [[3, 'desc']],
                language: {
                    processing:  (cfg.txt && cfg.txt.loading) || __('Cargando…', 'aura-suite'),
                    zeroRecords: (cfg.txt && cfg.txt.no_results) || __('No se encontraron mantenimientos.', 'aura-suite'),
                    info:        __('_TOTAL_ registros', 'aura-suite'),
                    infoEmpty:   __('0 registros', 'aura-suite'),
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
                            action:       'aura_inventory_maintenance_get_list',
                            nonce:        cfg.nonce,
                            page:         1,
                            per_page:     2000,
                            search:       $('#aura-maint-search').val(),
                            equipment_id: $('#aura-maint-filter-equipment').val() || 0,
                            type:         $('#aura-maint-filter-type').val(),
                            performed_by: $('#aura-maint-filter-performed').val(),
                            post_status:  $('#aura-maint-filter-post-status').val(),
                            date_from:    $('#aura-maint-filter-date-from').val(),
                            date_to:      $('#aura-maint-filter-date-to').val(),
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
                        render: function () {
                            return '<button type="button" class="aura-inv-row-toggle" data-tooltip="' + escHtml(__('Desplegar reporte completo', 'aura-suite')) + '" aria-expanded="false" aria-label="' + escHtml(__('Expandir', 'aura-suite')) + '">+</button>';
                        }
                    },
                    {
                        data: 'photo_thumb_url', orderable: false,
                        className: 'col-photo aura-inv-col-photo', width: '68px',
                        render: function (data, type, item) {
                            return type === 'display' ? self._thumb(item) : (item.equipment_name || '');
                        },
                    },
                    {
                        data: 'equipment_name',
                        render: function (data, type, m) {
                            if (type !== 'display') return data || '';
                            var tip = self._buildTooltip(m);
                            var h = '<a href="javascript:void(0)" class="aura-inv-title-link aura-maint-btn-detail" data-id="' + m.id + '" ' +
                                    'data-aura-tooltip="' + escHtml(tip) + '">' + escHtml(m.equipment_name) + '</a>';
                            var sub = [m.equipment_brand ? escHtml(m.equipment_brand) : '', m.equipment_code ? escHtml(sprintf(__('Cód: %s', 'aura-suite'), m.equipment_code)) : ''].filter(Boolean).join(' · ');
                            if (sub) h += '<div class="aura-inv-cell-sub">' + sub + '</div>';

                            var mob = [m.maintenance_date || '', (self.cfg.currency || '$') + parseFloat(m.total_cost || 0).toFixed(2)].filter(Boolean).join(' · ');
                            if (mob) h += '<div class="aura-inv-mobile-meta">' + escHtml(mob) + '</div>';
                            return h;
                        },
                    },
                    {
                        data: 'maintenance_date',
                        className: 'aura-inv-col-secondary col-date',
                        render: function (data) { return escHtml(data || '—'); },
                    },
                    {
                        data: 'type',
                        className: 'col-type',
                        render: function (d, t, m) {
                            return t === 'display' ? self._typeBadge(m) : (m.type || '');
                        },
                    },
                    {
                        data: 'performed_by',
                        className: 'aura-inv-col-secondary',
                        render: function (d, t, m) {
                            return t === 'display' ? self._executor(m) : (m.workshop_name || m.tech_name || '');
                        },
                    },
                    {
                        data: 'total_cost',
                        className: 'aura-inv-col-secondary col-cost',
                        render: function (d, t, m) {
                            return t === 'display' ? self._costCell(m) : (parseFloat(d) || 0);
                        },
                    },
                    {
                        data: 'post_status',
                        className: 'aura-inv-col-secondary col-post-status',
                        render: function (d, t, m) {
                            return t === 'display' ? self._psBadge(m) : (d || '');
                        },
                    },
                    {
                        data: 'has_finance',
                        className: 'aura-inv-col-secondary col-finance',
                        render: function (d, t, m) {
                            return t === 'display' ? self._finBadge(m) : (d ? '1' : '0');
                        },
                    },
                    {
                        data: null, orderable: false, searchable: false,
                        className: 'col-actions',
                        render: function (d, t, m) {
                            return t === 'display' ? self._actions(m) : '';
                        },
                    },
                ],
            });
        },

        bindEvents: function () {
            var self = this;
            var cfg  = this.cfg;

            $('#aura-maint-filter-apply').on('click', function () { self.table.ajax.reload(); });
            $('#aura-maint-filter-clear').on('click', function () { self.clearFilters(); });
            $('#aura-maint-search').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.table.ajax.reload();
                }
            });
            $('#aura-maint-filter-equipment, #aura-maint-filter-type, #aura-maint-filter-performed, #aura-maint-filter-post-status, #aura-maint-filter-date-from, #aura-maint-filter-date-to').on('change', function () {
                self.table.ajax.reload();
            });

            $('#aura-maint-filter-followup').on('click', function (e) {
                e.preventDefault();
                $('#aura-maint-filter-post-status').val('needs_followup');
                self.table.ajax.reload();
            });

            // Toggle child row (+)
            $(document).on('click', '#aura-maint-table .aura-inv-row-toggle', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var $tr  = $btn.closest('tr');
                var row  = self.table.row($tr);
                var item = row.data();
                if (!item) return;

                if (row.child.isShown()) {
                    row.child.hide();
                    $tr.removeClass('aura-row-expanded');
                    $btn.removeClass('is-open').text('+').attr('aria-expanded', 'false');
                } else {
                    row.child(self.renderMaintChildCard(item), 'aura-child-row').show();
                    $tr.addClass('aura-row-expanded');
                    $btn.addClass('is-open').text('−').attr('aria-expanded', 'true');
                }
            });

            $(document).on('click', '.aura-maint-btn-detail', function () {
                self.showDetail($(this).data('id'));
            });
            $(document).on('click', '.aura-maint-btn-edit', function () {
                window.location.href = cfg.newUrl + '&id=' + $(this).data('id');
            });
            $(document).on('click', '.aura-maint-btn-delete', function () {
                self.deleteMaintenance($(this).data('id'));
            });

            $(document).on('click', '#aura-maint-detail-modal .aura-modal-close, #aura-maint-detail-modal .aura-maint-modal-dismiss', function () {
                self.closeDetailModal();
            });
            bindTwoPhaseBackdropGuard('#aura-maint-detail-modal', function () {
                self.closeDetailModal();
            });
            $(document).on('keydown.auraMaintDetail', function (e) {
                if (e.key === 'Escape' && $('#aura-maint-detail-modal').is(':visible')) {
                    self.closeDetailModal();
                }
            });
        },

        closeDetailModal: function () {
            if (window.AuraUI && typeof window.AuraUI.closeModal === 'function') {
                window.AuraUI.closeModal('aura-maint-detail-modal');
            } else {
                $('#aura-maint-detail-modal').removeClass('active is-active').hide();
                $('body').css('overflow', '');
            }
        },

        clearFilters: function () {
            $('#aura-maint-search').val('');
            $('#aura-maint-filter-equipment, #aura-maint-filter-type, #aura-maint-filter-performed, #aura-maint-filter-post-status').val('');
            $('#aura-maint-filter-date-from, #aura-maint-filter-date-to').val('');
            this.table.ajax.reload();
        },

        showDetail: function (id) {
            var cfg     = this.cfg;
            var modalEl = document.getElementById('aura-maint-detail-modal');
            var $body   = $('#aura-maint-detail-body');
            var $edit   = $('#aura-maint-detail-edit-btn');

            if ($edit.length && cfg.newUrl) {
                $edit.attr('href', cfg.newUrl + '&id=' + id);
            }

            $body.empty();
            if (window.AuraUI && typeof window.AuraUI.openModal === 'function') {
                window.AuraUI.openModal('aura-maint-detail-modal', { skeleton: true });
            } else {
                $('#aura-maint-detail-modal').addClass('active is-active').css('display', 'flex');
            }

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_maintenance_get_detail', nonce: cfg.nonce, id: id },
                success: function (res) {
                    if (!res.success) {
                        $body.html('<div class="aura-inv-empty-state"><p>' + escHtml((res.data && res.data.message) || cfg.txt.error) + '</p></div>');
                        if (window.AuraUI && modalEl) window.AuraUI.hideModalSkeleton(modalEl);
                        return;
                    }
                    var m = res.data.maintenance;
                    var currency = cfg.currency || '$';

                    $('#aura-maint-detail-title').text((cfg.txt.type_labels[m.type] || m.type) + ' — ' + (m.equipment_name || ''));

                    var html = '<div class="aura-maint-detail-hero">';
                    html += '<div class="aura-maint-detail-hero-photo">';
                    if (m.equipment_photo) {
                        html += '<img src="' + escHtml(m.equipment_photo) + '" alt="' + escHtml(m.equipment_name || '') + '" class="aura-table-thumb-preview" data-img-url="' + escHtml(m.equipment_photo) + '" data-preview-title="' + escHtml(m.equipment_name || '') + '">';
                    } else {
                        html += '<span class="dashicons dashicons-format-image aura-inv-muted" style="font-size:38px;width:38px;height:38px;"></span>';
                    }
                    html += '</div>';
                    html += '<div class="aura-maint-detail-hero-info">';
                    html += '<div class="aura-maint-hero-badges">' +
                            '<span class="aura-maint-type-badge aura-maint-type-' + escHtml(m.type) + '">' + escHtml(cfg.txt.type_labels[m.type] || m.type) + '</span>' +
                            '<span class="aura-maint-ps-badge aura-maint-ps-' + escHtml(m.post_status) + '">' + escHtml(cfg.txt.post_status_labels[m.post_status] || m.post_status) + '</span>' +
                            '</div>';
                    html += '<h3 class="aura-maint-detail-name">' + escHtml(m.equipment_name || '—') + '</h3>';
                    html += '<p class="aura-maint-detail-sub">' + escHtml([m.equipment_brand, m.equipment_model, m.equipment_code ? sprintf(__('Cód: %s', 'aura-suite'), m.equipment_code) : ''].filter(Boolean).join(' · ') || sprintf(__('Fecha: %s', 'aura-suite'), m.maintenance_date)) + '</p>';
                    html += '</div></div>';

                    html += '<div class="aura-maint-detail-grid">';
                    html += dRow(__('Fecha ejecución', 'aura-suite'), escHtml(m.maintenance_date));
                    html += dRow(__('Horas del equipo', 'aura-suite'), m.equipment_hours ? escHtml(m.equipment_hours + ' h') : '—');
                    html += dRow(__('Próxima acción', 'aura-suite'), escHtml(m.next_action_date || '—'));

                    html += '<div class="aura-maint-detail-section">' + escHtml(__('Ejecutor y Facturación', 'aura-suite')) + '</div>';
                    if (m.performed_by === 'external') {
                        html += dRow(__('Taller / Empresa', 'aura-suite'), escHtml(m.workshop_name || '—'));
                        html += dRow(__('N° Factura', 'aura-suite'), escHtml(m.invoice_number || '—'));
                    } else {
                        html += dRow(__('Técnico interno', 'aura-suite'), escHtml(m.tech_name || '—'));
                    }

                    html += '<div class="aura-maint-detail-section">' + escHtml(__('Desglose de Costos', 'aura-suite')) + '</div>';
                    html += dRow(__('Repuestos / insumos', 'aura-suite'), escHtml(currency) + parseFloat(m.parts_cost || 0).toLocaleString('es', { minimumFractionDigits: 2 }));
                    html += dRow(__('Mano de obra', 'aura-suite'), escHtml(currency) + parseFloat(m.labor_cost || 0).toLocaleString('es', { minimumFractionDigits: 2 }));
                    html += dRow(__('Costo Total', 'aura-suite'), '<strong>' + escHtml(currency) + parseFloat(m.total_cost || 0).toLocaleString('es', { minimumFractionDigits: 2 }) + '</strong>');

                    if (m.description) {
                        html += '<div class="aura-maint-detail-section">' + escHtml(__('Descripción del Trabajo', 'aura-suite')) + '</div>';
                        html += '<div class="aura-maint-detail-row aura-maint-detail-text-row"><div class="aura-maint-detail-value">' + escHtml(m.description) + '</div></div>';
                    }
                    if (m.parts_replaced) {
                        html += '<div class="aura-maint-detail-section">' + escHtml(__('Piezas y Repuestos', 'aura-suite')) + '</div>';
                        html += '<div class="aura-maint-detail-row aura-maint-detail-text-row"><div class="aura-maint-detail-value">' + escHtml(m.parts_replaced) + '</div></div>';
                    }
                    if (m.observations) {
                        html += '<div class="aura-maint-detail-section">' + escHtml(__('Observaciones', 'aura-suite')) + '</div>';
                        html += '<div class="aura-maint-detail-row aura-maint-detail-text-row"><div class="aura-maint-detail-value">' + escHtml(m.observations) + '</div></div>';
                    }

                    if (m.finance_transaction_id) {
                        html += '<div class="aura-maint-detail-section">' + escHtml(__('Integración Finanzas', 'aura-suite')) + '</div>';
                        html += dRow(__('Transacción vinculada', 'aura-suite'), '<span class="aura-maint-finance-ok"><span class="dashicons dashicons-yes-alt"></span> #' + m.finance_transaction_id + '</span>');
                    }

                    html += '<div class="aura-maint-detail-section">' + escHtml(__('Auditoría', 'aura-suite')) + '</div>';
                    html += dRow(__('Registrado por', 'aura-suite'), escHtml(m.registered_by_name || '—'));
                    html += dRow(__('Registrado el', 'aura-suite'), escHtml(m.registered_at ? m.registered_at.substring(0, 10) : '—'));
                    html += '</div>';

                    $body.html(html);
                    if (window.AuraUI && modalEl) window.AuraUI.hideModalSkeleton(modalEl);
                },
                error: function () {
                    $body.html('<div class="aura-inv-empty-state"><p>' + escHtml(cfg.txt.error) + '</p></div>');
                    if (window.AuraUI && modalEl) window.AuraUI.hideModalSkeleton(modalEl);
                },
            });
        },

        deleteMaintenance: function (id) {
            var self = this;
            var cfg  = this.cfg;
            if (!window.confirm(cfg.txt.confirm_delete)) return;

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_maintenance_delete', nonce: cfg.nonce, id: id },
                success: function (res) {
                    if (res.success) {
                        var msg = (res.data && res.data.had_finance) ? res.data.message : cfg.txt.deleted;
                        self.showNotice(msg, (res.data && res.data.had_finance) ? 'warning' : 'success');
                        setTimeout(function () { window.location.reload(); }, 500);
                    } else {
                        self.showNotice((res.data && res.data.message) || cfg.txt.error, 'error');
                    }
                },
                error: function () { self.showNotice(cfg.txt.error, 'error'); },
            });
        },

        showNotice: function (msg, type) {
            if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                window.AuraUI.showToast(msg, type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'));
                return;
            }
            var cls = type === 'error' ? 'notice-error' : (type === 'warning' ? 'notice-warning' : 'notice-success');
            var $n  = $('<div class="notice ' + cls + ' is-dismissible" style="margin:12px 0;"><p>' + escHtml(msg) + '</p></div>');
            $('.aura-page-header').first().after($n);
            setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4500);
        },
    };

    // ─────────────────────────────────────────────────────────────
    // MÓDULO: FORMULARIO DE MANTENIMIENTO
    // ─────────────────────────────────────────────────────────────
    var MaintenanceForm = {

        cfg: null,

        init: function () {
            if (typeof auraMaintForm === 'undefined') return;
            if (!$('#aura-maint-form').length) return;
            this.cfg = auraMaintForm;
            this.bindEvents();
            this.initEquipmentCatalogPicker();
            this.updateTotal();

            if ($('#maint_equipment_id').val()) {
                $('#maint_equipment_id').trigger('change');
            }
        },

        bindEvents: function () {
            var self = this;

            $('input[name="performed_by"]').on('change', function () {
                var ext = $(this).val() === 'external';
                $('#maint_internal_fields').toggle(!ext);
                $('#maint_external_fields').toggle(ext);
            });

            $('input[name="post_status"]').on('change', function () {
                $('#maint_row_next_action').toggle($(this).val() !== 'operational');
            }).filter(':checked').trigger('change');

            $('#maint_create_finance').on('change', function () {
                $('#maint_finance_fields').toggle(this.checked);
            });

            $('#maint_parts_cost, #maint_labor_cost').on('input', function () {
                self.updateTotal();
            });

            $('#maint_select_invoice').on('click', function (e) {
                e.preventDefault();
                if (typeof wp === 'undefined' || !wp.media) return;
                var frame = wp.media({ title: __('Seleccionar archivo', 'aura-suite'), button: { text: __('Usar archivo', 'aura-suite') }, multiple: false });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#maint_workshop_invoice').val(att.url);
                });
                frame.open();
            });

            $(document).on('click', '#aura-maint-save-btn', function (e) {
                e.preventDefault();
                self.submit();
            });

            $('#maint_equipment_id').on('change', function () {
                var equipId = $(this).val();
                self.updateEquipmentCard(equipId);
                self.loadEquipmentPhoto(equipId);
                self.loadEquipmentInstructions(equipId);
            });

            // Selector de Usuarios para Técnico Responsable
            $('#aura-maint-btn-tech-explorer').on('click', function () {
                if (typeof window.AuraThirdPartySelector === 'undefined') {
                    self.showNotice(__('El explorador de usuarios no está disponible en esta página.', 'aura-suite'), 'error');
                    return;
                }
                window.AuraThirdPartySelector.openExplorer({
                    defaultTab: 'employee',
                    allowUsers: true,
                    onSelect: function (item) {
                        var userId = item.user_id || item.id || '';
                        var name   = item.commercial_name || item.name || item.display_name || '';
                        if (!userId) return;
                        var $sel = $('#maint_technician');
                        if (!$sel.find('option[value="' + userId + '"]').length) {
                            $sel.append($('<option>', { value: userId, text: name }));
                        }
                        $sel.val(String(userId)).trigger('change');
                    }
                });
            });
        },

        initEquipmentCatalogPicker: function () {
            var self   = this;
            var $modal = $('#aura-equip-picker-modal');
            if (!$modal.length) return;

            var closePicker = function () {
                if (window.AuraUI && typeof window.AuraUI.closeModal === 'function') {
                    window.AuraUI.closeModal('aura-equip-picker-modal');
                } else {
                    $modal.removeClass('active is-active').hide();
                    $('body').css('overflow', '');
                }
            };

            $('#aura-open-equip-catalog-btn').on('click', function (e) {
                e.preventDefault();
                if (window.AuraUI && typeof window.AuraUI.openModal === 'function') {
                    window.AuraUI.openModal('aura-equip-picker-modal');
                } else {
                    $modal.addClass('active is-active').css('display', 'flex');
                }
                $('#aura-equip-picker-search').val('').focus();
                $('#aura-equip-picker-chips .aura-equip-chip').removeClass('is-active');
                $('#aura-equip-picker-chips .aura-equip-chip[data-filter="all"]').addClass('is-active');
                self.applyCatalogFilter('all', '');
            });

            $modal.on('click', '.aura-equip-modal-close', function (e) {
                e.preventDefault();
                closePicker();
            });

            bindTwoPhaseBackdropGuard('#aura-equip-picker-modal', closePicker);

            $(document).on('keydown.auraEquipPicker', function (e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    closePicker();
                }
            });

            var currentChip = 'all';
            $('#aura-equip-picker-chips').on('click', '.aura-equip-chip', function (e) {
                e.preventDefault();
                $('#aura-equip-picker-chips .aura-equip-chip').removeClass('is-active');
                $(this).addClass('is-active');
                currentChip = $(this).data('filter') || 'all';
                self.applyCatalogFilter(currentChip, $('#aura-equip-picker-search').val());
            });

            $('#aura-equip-picker-search').on('input', function () {
                self.applyCatalogFilter(currentChip, $(this).val());
            });

            $modal.on('click', '.aura-equip-picker-select-btn', function (e) {
                e.preventDefault();
                var equipId = $(this).data('id');
                if (equipId) {
                    $('#maint_equipment_id').val(equipId).trigger('change');
                    closePicker();
                }
            });

            $('#aura-fast-forward-maint-btn').on('click', function (e) {
                e.preventDefault();
                $('#maint_type').val('preventive');
                var today = new Date().toISOString().substring(0, 10);
                $('#maint_date').val(today);
                var currentDesc = $('#maint_description').val().trim();
                var note = __('⚡ Mantenimiento preventivo adelantado por retorno de préstamo / inspección de piezas.', 'aura-suite');
                if (!currentDesc) {
                    $('#maint_description').val(note);
                } else if (currentDesc.indexOf(note) === -1) {
                    $('#maint_description').val(note + '\n\n' + currentDesc);
                }
                if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                    window.AuraUI.showToast(__('Campos pre-configurados para mantenimiento adelantado.', 'aura-suite'), 'success');
                }
            });
        },

        applyCatalogFilter: function (groupFilter, searchQuery) {
            var q = (searchQuery || '').toLowerCase().trim();
            var visibleCount = 0;

            $('#aura-equip-picker-tbody .aura-equip-picker-row').each(function () {
                var $row      = $(this);
                var rowGroup  = $row.data('group') || '';
                var rowSearch = ($row.data('search') || '').toString().toLowerCase();

                var matchGroup  = (groupFilter === 'all') || (rowGroup === groupFilter);
                var matchSearch = !q || (rowSearch.indexOf(q) !== -1);

                if (matchGroup && matchSearch) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            $('#aura-equip-picker-empty').toggle(visibleCount === 0);
        },

        updateEquipmentCard: function (equipId) {
            var $card = $('#maint-equip-card');
            if (!$card.length) return;

            if (!equipId) {
                $card.hide();
                return;
            }

            var $opt = $('#maint_equipment_id').find('option:selected');
            if (!$opt.length || !$opt.val()) {
                $card.hide();
                return;
            }

            var rawText  = $opt.text().trim();
            var title    = rawText.replace(/^[🔴🟡🔵✅\s]+/, '').split(' — ')[0].trim();
            var code     = $opt.data('code') || '';
            var status   = $opt.data('status') || 'available';
            var hours    = $opt.data('hours') || 0;
            var oil      = $opt.data('oil') || __('No especificado', 'aura-suite');
            var interval = $opt.data('interval') || __('Bajo demanda', 'aura-suite');
            var photo    = $opt.data('photo') || '';

            $('#maint-card-title').text(title);
            $('#maint-card-code-badge').text(code ? sprintf(__('Cód: %s', 'aura-suite'), code) : __('Activo', 'aura-suite')).show();

            var statusText = {
                available: __('✅ Disponible', 'aura-suite'),
                in_use: __('🔵 En Uso / Prestado', 'aura-suite'),
                maintenance: __('🟡 En Mantenimiento', 'aura-suite'),
                repair: __('🔴 En Reparación', 'aura-suite'),
                retired: __('⚪ De Baja', 'aura-suite')
            }[status] || status;

            $('#maint-card-status-badge')
                .attr('class', 'aura-inv-status-badge aura-inv-status-' + status)
                .text(statusText);
            $('#maint-card-hours').text(parseFloat(hours).toLocaleString('es', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' h');
            $('#maint-card-oil').text(oil);
            $('#maint-card-interval').text(interval);

            if (photo) {
                $('#maint-equip-photo-img').attr('src', photo).attr('data-img-url', photo).attr('data-preview-title', title).show();
                $('#maint-equip-photo-placeholder').hide();
            } else {
                $('#maint-equip-photo-img').hide();
                $('#maint-equip-photo-placeholder').show();
            }

            var $hoursInput = $('#maint_hours');
            if ($hoursInput.length && (!$hoursInput.val() || parseFloat($hoursInput.val()) === 0)) {
                if (parseFloat(hours) > 0) {
                    $hoursInput.val(hours);
                }
            }

            $card.slideDown(200);
        },

        loadEquipmentPhoto: function (equipId) {
            var cfg   = this.cfg;
            var $img  = $('#maint-equip-photo-img');
            var $ph   = $('#maint-equip-photo-placeholder');

            if (!equipId) {
                $img.attr('src', '').hide();
                $ph.show();
                return;
            }

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data: { action: 'aura_inventory_equipment_get_photo', nonce: cfg.nonce, id: equipId },
                success: function (res) {
                    if (res.success && res.data && res.data.photo_full_url) {
                        $img.attr('src', res.data.photo_full_url).attr('data-img-url', res.data.photo_full_url).show();
                        $ph.hide();
                    } else if (!$img.attr('src')) {
                        $img.hide();
                        $ph.show();
                    }
                }
            });
        },

        loadEquipmentInstructions: function (equipId) {
            var cfg = this.cfg;
            var txt = cfg.txt;

            var $panel       = $('#maint-instructions-panel');
            var $instrBlock  = $('#maint-instructions-block');
            var $instrText   = $('#maint-instructions-text');
            var $prevBlock   = $('#maint-prev-block');
            var $prevContent = $('#maint-prev-content');

            if (!equipId) {
                $panel.hide();
                return;
            }

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data: { action: 'aura_inventory_equipment_get_form_data', nonce: cfg.nonce, id: equipId },
                success: function (res) {
                    if (!res.success || !res.data) { $panel.hide(); return; }

                    var eq           = res.data.equipment || {};
                    var instructions = (eq.maintenance_instructions || '').trim();
                    var showPanel    = false;

                    if (instructions) {
                        $instrText.text(instructions);
                        $instrBlock.show();
                        showPanel = true;
                    } else {
                        $instrBlock.hide();
                    }

                    $.ajax({
                        url:    cfg.ajaxurl,
                        method: 'POST',
                        data: { action: 'aura_inventory_equipment_get_detail', nonce: cfg.nonce, id: equipId },
                        success: function (res2) {
                            if (res2.success && res2.data) {
                                var history = res2.data.maintenance_history || [];
                                if (history.length > 0) {
                                    var prev      = history[0];
                                    var typeLabel = (txt.type_labels && txt.type_labels[prev.type]) || prev.type;
                                    var psIcon    = (txt.ps_icons && txt.ps_icons[prev.post_status]) || '';
                                    var psLabel   = (txt.ps_labels && txt.ps_labels[prev.post_status]) || prev.post_status;

                                    var html = '<table class="aura-maint-prev-tbl">';
                                    html += '<tr><td>' + escHtml(txt.date_label || __('Fecha:', 'aura-suite')) + '</td><td>' + escHtml(prev.maintenance_date || '—') + '</td></tr>';
                                    html += '<tr><td>' + escHtml(txt.type_label || __('Tipo:', 'aura-suite')) + '</td><td>' + escHtml(typeLabel) + '</td></tr>';
                                    if (prev.description) {
                                        html += '<tr><td>' + escHtml(txt.work_label || __('Trabajo:', 'aura-suite')) + '</td><td>' + escHtml(prev.description).replace(/\n/g, '<br>') + '</td></tr>';
                                    }
                                    if (prev.performed_by === 'external' && prev.technician_name) {
                                        html += '<tr><td>' + escHtml(txt.workshop_label || __('Taller:', 'aura-suite')) + '</td><td>' + escHtml(prev.technician_name) + '</td></tr>';
                                    }
                                    html += '<tr><td>' + escHtml(txt.status_label || __('Estado:', 'aura-suite')) + '</td><td>' + psIcon + ' ' + escHtml(psLabel) + '</td></tr>';
                                    html += '</table>';

                                    $prevContent.html(html);
                                    $prevBlock.show();
                                    showPanel = true;
                                } else if (instructions) {
                                    $prevContent.html('<em>' + escHtml(txt.no_prev_maint || __('Sin mantenimientos previos.', 'aura-suite')) + '</em>');
                                    $prevBlock.show();
                                    showPanel = true;
                                } else {
                                    $prevBlock.hide();
                                }
                            } else {
                                $prevBlock.hide();
                            }

                            if (showPanel) {
                                $panel.slideDown(200);
                            } else {
                                $panel.hide();
                            }
                        },
                        error: function () {
                            $prevBlock.hide();
                            if (showPanel) { $panel.slideDown(200); } else { $panel.hide(); }
                        }
                    });
                },
                error: function () { $panel.hide(); }
            });
        },

        updateTotal: function () {
            var parts = parseFloat($('#maint_parts_cost').val()) || 0;
            var labor = parseFloat($('#maint_labor_cost').val()) || 0;
            $('#maint_total_value').text((parts + labor).toFixed(2));
        },

        submit: function () {
            var self    = this;
            var cfg     = this.cfg;
            var $btn    = $('#aura-maint-save-btn');
            var $notice = $('#aura-maint-form-notice');

            if (!$('#maint_equipment_id').val()) {
                self.showFormNotice(cfg.txt.required_equip, 'error');
                return;
            }
            if (!$('#maint_date').val()) {
                self.showFormNotice(cfg.txt.required_date, 'error');
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aura-maint-spin"></span> ' + escHtml(cfg.txt.saving));
            $notice.html('');

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   $('#aura-maint-form').serialize() +
                        '&action=aura_inventory_maintenance_save' +
                        '&nonce=' + encodeURIComponent(cfg.nonce) +
                        '&id=' + encodeURIComponent(cfg.maintId || 0),
                success: function (res) {
                    $btn.prop('disabled', false);
                    var btnLabel = cfg.isEdit ? __('Actualizar mantenimiento', 'aura-suite') : __('Registrar mantenimiento', 'aura-suite');
                    $btn.html('<span class="dashicons dashicons-yes"></span> ' + btnLabel);

                    if (res.success) {
                        var msg = cfg.txt.saved;
                        if (res.data && res.data.finance_id) msg += cfg.txt.finance_ok;
                        self.showFormNotice(msg, 'success');
                        if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                            window.AuraUI.showToast(msg, 'success');
                        }
                        if (!cfg.isEdit) {
                            setTimeout(function () { window.location.href = cfg.listUrl; }, 1000);
                        } else {
                            setTimeout(function () { window.location.reload(); }, 900);
                        }
                    } else {
                        self.showFormNotice((res.data && res.data.message) || cfg.txt.error, 'error');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    var btnLabel = cfg.isEdit ? __('Actualizar mantenimiento', 'aura-suite') : __('Registrar mantenimiento', 'aura-suite');
                    $btn.html('<span class="dashicons dashicons-yes"></span> ' + btnLabel);
                    self.showFormNotice(cfg.txt.error, 'error');
                },
            });
        },

        showFormNotice: function (msg, type) {
            var isErr = type === 'error';
            var cls   = isErr ? 'aura-inv-form-alert-error' : 'aura-inv-form-alert-ok';
            $('#aura-maint-form-notice').html('<div class="aura-inv-form-alert ' + cls + '">' + escHtml(msg) + '</div>');
        },
    };

    // ─────────────────────────────────────────────────────────────
    // HELPERS LOCALES
    // ─────────────────────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function dRow(label, value) {
        return '<div class="aura-maint-detail-row">' +
            '<span class="aura-maint-detail-label">' + escHtml(label) + '</span>' +
            '<span class="aura-maint-detail-value">' + value + '</span>' +
            '</div>';
    }

    function childSpec(label, value) {
        return '<div class="aura-inv-child-spec">' +
            '<span class="aura-inv-child-spec-lbl">' + escHtml(label) + '</span>' +
            '<strong class="aura-inv-child-spec-val">' + escHtml(value) + '</strong>' +
            '</div>';
    }

    function bindTwoPhaseBackdropGuard(selector, onClose) {
        var downOnBackdrop = false;
        $(document).off('mousedown.guard_' + selector + ' click.guard_' + selector)
            .on('mousedown.guard_' + selector, selector, function (e) {
                downOnBackdrop = (e.target === e.currentTarget);
            })
            .on('click.guard_' + selector, selector, function (e) {
                if (downOnBackdrop && e.target === e.currentTarget) {
                    onClose();
                }
                downOnBackdrop = false;
            });
    }

    window.MaintenanceList = MaintenanceList;
    window.MaintenanceForm = MaintenanceForm;

    $(function () {
        MaintenanceList.init();
        MaintenanceForm.init();
    });

}(jQuery));
