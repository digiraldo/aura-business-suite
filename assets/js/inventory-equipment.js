/**
 * Inventario — Equipment JS (Design System Aura)
 * Maneja: listado DataTables con filas expandibles (+), tooltips enriquecidos,
 *         previsualización sin recorte, modal 360°, formulario AJAX y Cropper.
 *
 * Depende de: jQuery, DataTables, AuraUI, auraInventoryEquipment / auraInvFormData, wp
 */

/* global jQuery, DataTable, auraInventoryEquipment, auraInvFormData, AuraUI, Cropper, wp */

(function ($) {
    'use strict';

    // Proteger contra doble ejecución accidental si el script se encolara más de una vez
    if (window.__auraInvEquipmentBooted) {
        return;
    }
    window.__auraInvEquipmentBooted = true;

    // ─────────────────────────────────────────────────────────────
    // MÓDULO: LISTADO DE EQUIPOS — DataTables + Filas Expandibles (+)
    // ─────────────────────────────────────────────────────────────

    var EquipmentList = {

        cfg:   null,
        table: null,

        init: function () {
            if (typeof auraInventoryEquipment === 'undefined') return;
            if (!$('#aura-inv-equipment-table').length) return;
            this.cfg = auraInventoryEquipment;
            this.initDataTable();
            this.bindEvents();
        },

        // ── Helpers de render ────────────────────────────────────
        _thumb: function (eq) {
            if (!eq.photo_thumb_url && !eq.photo_full_url) {
                return '<div class="aura-inv-thumb-empty" data-tooltip="Sin fotografía registrada">' +
                    '<span class="dashicons dashicons-format-image"></span>' +
                    '</div>';
            }
            var fullUrl  = eq.photo_full_url || eq.photo_thumb_url;
            var thumbUrl = eq.photo_thumb_url || eq.photo_full_url;
            var label    = eq.name || 'Equipo';
            var subMeta  = [eq.brand || '', eq.internal_code ? ('Cód: ' + eq.internal_code) : ''].filter(Boolean).join(' · ');

            var tooltipCard = '<div class="aura-floating-img-card aura-inv-photo-card">' +
                '<img src="' + escHtml(fullUrl) + '" alt="' + escHtml(label) + '" style="max-width:260px;max-height:260px;width:auto;height:auto;object-fit:contain;border-radius:8px;display:block;margin:0 auto;">' +
                '<span class="aura-floating-img-title">' + escHtml(label) + '</span>' +
                (subMeta ? '<span class="aura-floating-img-sub">' + escHtml(subMeta) + '</span>' : '') +
                '</div>';

            return '<div class="aura-inv-thumb-wrap aura-table-thumb-preview" ' +
                'data-img-url="' + escHtml(fullUrl) + '" ' +
                'data-preview-title="' + escHtml(label) + '" ' +
                'data-aura-tooltip="' + escHtml(tooltipCard) + '">' +
                '<img src="' + escHtml(thumbUrl) + '" alt="' + escHtml(label) + '" loading="lazy">' +
                '</div>';
        },

        _maintBadge: function (eq) {
            var cfg = this.cfg;
            var st  = eq.maintenance_status || 'none';
            var html = '';
            if (st && st !== 'none') {
                var lbl = (cfg.txt && cfg.txt.maint_labels && cfg.txt.maint_labels[st]) ? cfg.txt.maint_labels[st] : st;
                html += '<span class="aura-inv-maint-badge aura-inv-maint-' + escHtml(st) + '">' + escHtml(lbl) + '</span>';
                if (eq.next_maintenance_date) {
                    html += '<div class="aura-inv-cell-sub">' + escHtml(eq.next_maintenance_date) + '</div>';
                }
                return html;
            }
            if (eq.next_maintenance_date) {
                var okLbl = (cfg.txt && cfg.txt.maint_labels && cfg.txt.maint_labels.ok) ? cfg.txt.maint_labels.ok : 'Al día';
                return '<span class="aura-inv-maint-badge aura-inv-maint-ok">' + escHtml(okLbl) + '</span>' +
                    '<div class="aura-inv-cell-sub">' + escHtml(eq.next_maintenance_date) + '</div>';
            }
            return '<span class="aura-inv-muted">—</span>';
        },

        _statusCell: function (eq) {
            var cfg = this.cfg;
            var labels = (cfg.txt && cfg.txt.status_labels) ? cfg.txt.status_labels : {};
            return eq.can_edit
                ? '<select class="aura-inv-status-select form-select form-select-sm aura-inv-status-' + escHtml(eq.status) + '" data-id="' + eq.id + '" data-tooltip="Cambiar estado operativo">' +
                  renderStatusOptions(labels, eq.status) + '</select>'
                : '<span class="aura-inv-status-badge aura-inv-status-' + escHtml(eq.status) + '">' + escHtml(eq.status_label || eq.status) + '</span>';
        },

        _buildEquipmentTooltip: function (eq) {
            var cfg = this.cfg;
            var stLbl = (cfg.txt && cfg.txt.status_labels && cfg.txt.status_labels[eq.status]) || eq.status_label || eq.status;
            var card = '<div class="aura-tip-card">' +
                '<div class="aura-tip-card-header"><div class="aura-tip-info">' +
                '<div class="aura-tip-title">' + escHtml(eq.name) + '</div>' +
                '</div></div>' +
                '<div class="aura-tip-card-body">' +
                '<div class="aura-tip-row"><span>Categoría</span><strong>' + escHtml(eq.category || '—') + '</strong></div>' +
                '<div class="aura-tip-row"><span>Estado</span><strong>' + escHtml(stLbl) + '</strong></div>' +
                '<div class="aura-tip-row"><span>Marca / Modelo</span><strong>' + escHtml([eq.brand, eq.model].filter(Boolean).join(' · ') || '—') + '</strong></div>' +
                '<div class="aura-tip-row"><span>Cód. / Serie</span><strong>' + escHtml([eq.internal_code, eq.serial_number].filter(Boolean).join(' / ') || '—') + '</strong></div>' +
                '<div class="aura-tip-row"><span>Ubicación</span><strong>' + escHtml(eq.location || '—') + '</strong></div>' +
                '<div class="aura-tip-row"><span>Responsable</span><strong>' + escHtml(eq.responsible_name || '—') + '</strong></div>' +
                '</div></div>';
            return card;
        },

        _actions: function (eq) {
            var cfg = this.cfg;
            var h = '<div class="aura-row-actions aura-inv-row-actions">';
            h += '<button type="button" class="aura-btn-action-icon aura-btn-view aura-inv-btn-detail" data-id="' + eq.id + '" data-tooltip="Ver ficha 360°" aria-label="Ver ficha">' +
                 '<span class="dashicons dashicons-visibility"></span></button>';
            if (cfg.can_maint !== false) {
                h += '<button type="button" class="aura-btn-action-icon aura-btn-maint aura-inv-btn-maint" data-id="' + eq.id + '" data-tooltip="' + escHtml((cfg.txt && cfg.txt.register_maint) || 'Registrar Mantenimiento') + '" aria-label="Registrar mantenimiento">' +
                     '<span class="dashicons dashicons-admin-tools"></span></button>';
            }
            if (eq.can_edit) {
                h += '<button type="button" class="aura-btn-action-icon aura-btn-edit aura-inv-btn-edit" data-id="' + eq.id + '" data-tooltip="Editar equipo" aria-label="Editar">' +
                     '<span class="dashicons dashicons-edit"></span></button>';
            }
            if (eq.can_delete) {
                h += '<button type="button" class="aura-btn-action-icon aura-btn-delete delete aura-inv-btn-delete" data-id="' + eq.id +
                     '" data-name="' + escHtml(eq.name) + '" data-tooltip="Eliminar equipo" aria-label="Eliminar">' +
                     '<span class="dashicons dashicons-trash"></span></button>';
            }
            h += '</div>';
            return h;
        },

        // ── Render Child Row Expandible (+) Desktop y Móvil ──────
        renderEquipmentChildCard: function (eq) {
            var cfg = this.cfg;
            var stLbl = (cfg.txt && cfg.txt.status_labels && cfg.txt.status_labels[eq.status]) || eq.status_label || eq.status;
            var photoHtml = '';
            if (eq.photo_full_url || eq.photo_thumb_url) {
                var pFull  = eq.photo_full_url || eq.photo_thumb_url;
                var pThumb = eq.photo_thumb_url || eq.photo_full_url;
                photoHtml = '<div class="aura-inv-child-photo aura-table-thumb-preview" data-img-url="' + escHtml(pFull) + '" data-preview-title="' + escHtml(eq.name) + '">' +
                    '<img src="' + escHtml(pThumb) + '" alt="' + escHtml(eq.name) + '">' +
                    '</div>';
            } else {
                photoHtml = '<div class="aura-inv-child-photo aura-inv-child-photo-empty"><span class="dashicons dashicons-format-image"></span></div>';
            }

            var costText = (parseFloat(eq.cost || 0) > 0) ? '$' + parseFloat(eq.cost).toLocaleString() : '—';
            var estText  = (parseFloat(eq.estimated_value || 0) > 0) ? '$' + parseFloat(eq.estimated_value).toLocaleString() : '—';

            var h = '<div class="aura-inv-child-card">';
            h += '<div class="aura-inv-child-layout">';
            h += photoHtml;
            h += '<div class="aura-inv-child-main">';
            h += '<div class="aura-inv-child-top">';
            h += '<div><h4 class="aura-inv-child-title">' + escHtml(eq.name) + '</h4>';
            var subtitle = [eq.brand, eq.model].filter(Boolean).join(' · ');
            if (subtitle) {
                h += '<div class="aura-inv-child-sub">' + escHtml(subtitle) + '</div>';
            }
            h += '</div>';
            h += '<div class="aura-inv-child-badges">' +
                 '<span class="aura-inv-status-badge aura-inv-status-' + escHtml(eq.status) + '">' + escHtml(stLbl) + '</span>' +
                 '</div>';
            h += '</div>'; // .aura-inv-child-top

            h += '<div class="aura-inv-child-specs">';
            h += childSpec('Categoría', eq.category || '—');
            h += childSpec('Cód. Interno', eq.internal_code || '—');
            h += childSpec('Núm. Serie', eq.serial_number || '—');
            h += childSpec('Ubicación', eq.location || '—');
            h += childSpec('Área', eq.area_name || '—');
            h += childSpec('Responsable', eq.responsible_name || '—');
            h += childSpec('Próx. Mantenimiento', eq.next_maintenance_date || 'No programado');
            h += childSpec('Costo / Valor Est.', costText + ' / ' + estText);
            h += '</div>';

            if (eq.description) {
                h += '<div class="aura-inv-child-desc"><strong>Descripción:</strong> ' + escHtml(eq.description) + '</div>';
            }

            h += '<div class="aura-inv-child-actions">';
            h += '<button type="button" class="btn btn-indigo btn-sm aura-inv-btn-detail" data-id="' + eq.id + '">' +
                 '<span class="dashicons dashicons-visibility"></span> Ver Ficha 360°</button>';
            if (cfg.can_maint !== false) {
                h += '<button type="button" class="btn btn-glass btn-sm aura-inv-btn-maint" data-id="' + eq.id + '">' +
                     '<span class="dashicons dashicons-admin-tools"></span> Mantenimiento</button>';
            }
            if (eq.can_edit) {
                h += '<button type="button" class="btn btn-glass btn-sm aura-inv-btn-edit" data-id="' + eq.id + '">' +
                     '<span class="dashicons dashicons-edit"></span> Editar</button>';
            }
            if (eq.can_delete) {
                h += '<button type="button" class="btn btn-glass btn-sm aura-btn-danger-outline aura-inv-btn-delete" data-id="' + eq.id + '" data-name="' + escHtml(eq.name) + '">' +
                     '<span class="dashicons dashicons-trash"></span> Eliminar</button>';
            }
            h += '</div>'; // .aura-inv-child-actions
            h += '</div>'; // .aura-inv-child-main
            h += '</div>'; // .aura-inv-child-layout
            h += '</div>'; // .aura-inv-child-card
            return h;
        },

        // ── Inicializar DataTable ────────────────────────────────
        initDataTable: function () {
            var self = this;
            var cfg  = this.cfg;

            if ($.fn.DataTable.isDataTable('#aura-inv-equipment-table')) {
                this.table = $('#aura-inv-equipment-table').DataTable();
                return;
            }

            this.table = new DataTable('#aura-inv-equipment-table', {
                responsive: false, // Manejamos filas expandibles (+) propias desktop & mobile
                processing: true,
                serverSide: false,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                order: [[2, 'asc']],
                language: {
                    processing:  (cfg.txt && cfg.txt.loading) || 'Cargando…',
                    zeroRecords: (cfg.txt && cfg.txt.no_results) || 'No se encontraron equipos.',
                    info:        '_TOTAL_ equipos registrados',
                    infoEmpty:   '0 equipos',
                    infoFiltered: '(filtrado de _MAX_ en total)',
                    search:      '',
                    lengthMenu:  'Mostrar _MENU_ por página',
                    paginate: { first: '«', last: '»', next: '›', previous: '‹' }
                },
                searching:  false,
                dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',

                ajax: {
                    url:  cfg.ajaxurl,
                    type: 'POST',
                    data: function () {
                        return {
                            action:             'aura_inventory_equipment_get_list',
                            nonce:              cfg.nonce,
                            page:               1,
                            per_page:           2000,
                            search:             $('#aura-inv-search').val(),
                            category:           $('#aura-inv-filter-category').val(),
                            status:             $('#aura-inv-filter-status').val(),
                            area_id:            $('#aura-inv-filter-area').val() || 0,
                            maintenance_status: $('#aura-inv-filter-maintenance').val(),
                        };
                    },
                    dataSrc: function (json) {
                        if (!json.success) return [];
                        return json.data.items || [];
                    },
                },

                createdRow: function (row, eq) {
                    $(row).attr('id', 'aura-inv-row-' + eq.id);
                },

                columns: [
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'aura-inv-col-toggle',
                        width: '40px',
                        render: function (data, type, eq) {
                            if (type !== 'display') return '';
                            return '<button type="button" class="aura-inv-row-toggle" data-id="' + eq.id + '" ' +
                                'data-tooltip="Desplegar ficha completa del equipo" aria-expanded="false" aria-label="Expandir fila">+</button>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'aura-inv-col-photo',
                        width: '68px',
                        render: function (data, type, eq) {
                            if (type === 'display') return self._thumb(eq);
                            return eq.name || '';
                        },
                    },
                    {
                        data: 'name',
                        className: 'aura-inv-col-main',
                        render: function (data, type, eq) {
                            if (type !== 'display') return data || '';
                            var tip = self._buildEquipmentTooltip(eq);
                            var h = '<a href="javascript:void(0)" class="aura-inv-title-link aura-inv-btn-detail" data-id="' + eq.id + '" ' +
                                    'data-aura-tooltip="' + escHtml(tip) + '">' + escHtml(eq.name) + '</a>';
                            var sub = [
                                eq.brand ? escHtml(eq.brand) : '',
                                eq.internal_code ? 'Cód: ' + escHtml(eq.internal_code) : ''
                            ].filter(Boolean).join(' · ');
                            if (sub) h += '<div class="aura-inv-cell-sub">' + sub + '</div>';

                            // Chips móviles compactos (visibles solo <= 782px cuando columnas secundarias se ocultan)
                            var mobParts = [];
                            if (eq.category) mobParts.push(escHtml(eq.category));
                            if (eq.next_maintenance_date) mobParts.push('Mant: ' + escHtml(eq.next_maintenance_date));
                            if (mobParts.length) {
                                h += '<div class="aura-inv-mobile-meta">' + mobParts.join(' · ') + '</div>';
                            }
                            return h;
                        },
                    },
                    {
                        data: 'next_maintenance_date',
                        className: 'aura-inv-col-secondary column-maintenance',
                        render: function (data, type, eq) {
                            if (type !== 'display') return data || '';
                            return self._maintBadge(eq);
                        },
                    },
                    {
                        data: 'status',
                        className: 'column-status',
                        render: function (data, type, eq) {
                            if (type !== 'display') return eq.status_label || data;
                            return self._statusCell(eq);
                        },
                    },
                    {
                        data: 'category',
                        className: 'aura-inv-col-secondary',
                        render: function (data) { return data ? '<span class="aura-inv-cat-chip">' + escHtml(data) + '</span>' : '<span class="aura-inv-muted">—</span>'; },
                    },
                    {
                        data: 'location',
                        className: 'aura-inv-col-secondary',
                        render: function (data) { return escHtml(data || '—'); },
                    },
                    {
                        data: 'responsible_name',
                        className: 'aura-inv-col-secondary',
                        render: function (data) { return escHtml(data || '—'); },
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'column-actions',
                        render: function (data, type, eq) {
                            if (type !== 'display') return '';
                            return self._actions(eq);
                        },
                    },
                ],
            });
        },

        // ── Eventos ──────────────────────────────────────────────
        bindEvents: function () {
            var self = this;

            $('#aura-inv-filter-apply').on('click', function () {
                self.table.ajax.reload();
            });
            $('#aura-inv-filter-clear').on('click', function () {
                $('#aura-inv-search').val('');
                $('#aura-inv-filter-category, #aura-inv-filter-status, #aura-inv-filter-area').val('');
                $('#aura-inv-filter-maintenance').val('-1');
                self.table.ajax.reload();
            });
            $('#aura-inv-search').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.table.ajax.reload();
                }
            });
            $('#aura-inv-filter-category, #aura-inv-filter-status, #aura-inv-filter-area, #aura-inv-filter-maintenance').on('change', function () {
                self.table.ajax.reload();
            });

            // Toggle fila expandible (+)
            $(document).on('click', '#aura-inv-equipment-table .aura-inv-row-toggle', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var $tr  = $btn.closest('tr');
                var row  = self.table.row($tr);
                var eq   = row.data();
                if (!eq) return;

                if (row.child.isShown()) {
                    row.child.hide();
                    $tr.removeClass('aura-row-expanded');
                    $btn.removeClass('is-open').text('+').attr('aria-expanded', 'false');
                } else {
                    row.child(self.renderEquipmentChildCard(eq), 'aura-child-row').show();
                    $tr.addClass('aura-row-expanded');
                    $btn.addClass('is-open').text('−').attr('aria-expanded', 'true');
                }
            });

            // Acciones delegadas en la tabla y child rows
            $(document).on('click', '.aura-inv-btn-detail', function () {
                self.showDetail($(this).data('id'));
            });
            $(document).on('click', '.aura-inv-btn-maint', function () {
                var eqId = $(this).data('id');
                window.location.href = self.cfg.newMaintUrl + '&equipment_id=' + eqId;
            });
            $(document).on('click', '.aura-inv-btn-edit', function () {
                window.location.href = self.cfg.newEquipUrl + '&id=' + $(this).data('id');
            });
            $(document).on('click', '.aura-inv-btn-delete', function () {
                self.deleteEquipment($(this).data('id'), $(this).data('name'));
            });
            $(document).on('change', '.aura-inv-status-select', function () {
                var $sel = $(this);
                self.updateStatus($sel.data('id'), $sel.val(), $sel);
            });

            // Modales: botones cerrar & guardar guardia 2-fases contra arrastre de texto
            $(document).on('click', '#aura-inv-detail-modal .aura-modal-close, #aura-inv-detail-modal .aura-inv-modal-dismiss', function () {
                self.closeDetailModal();
            });
            bindTwoPhaseBackdropGuard('#aura-inv-detail-modal', function () {
                self.closeDetailModal();
            });
            $(document).on('keydown.auraInvModal', function (e) {
                if (e.key === 'Escape' && $('#aura-inv-detail-modal').hasClass('active')) {
                    self.closeDetailModal();
                }
            });
        },

        closeDetailModal: function () {
            if (window.AuraUI && typeof window.AuraUI.closeModal === 'function') {
                window.AuraUI.closeModal('aura-inv-detail-modal');
            } else {
                $('#aura-inv-detail-modal').removeClass('active is-active').hide();
                $('body').css('overflow', '');
            }
        },

        showDetail: function (id) {
            var cfg    = this.cfg;
            var modalEl = document.getElementById('aura-inv-detail-modal');
            var $body  = $('#aura-inv-detail-body');
            var $editBtn = $('#aura-inv-detail-edit-btn');

            if ($editBtn.length && cfg.newEquipUrl) {
                $editBtn.attr('href', cfg.newEquipUrl + '&id=' + id);
            }

            $body.html('');
            if (window.AuraUI && typeof window.AuraUI.openModal === 'function') {
                window.AuraUI.openModal('aura-inv-detail-modal', { skeleton: true });
            } else {
                $('#aura-inv-detail-modal').addClass('active is-active').css('display', 'flex');
            }

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_equipment_get_detail', nonce: cfg.nonce, id: id },
                success: function (res) {
                    if (!res.success) {
                        $body.html('<div class="aura-inv-empty-state"><p>' + escHtml((res.data && res.data.message) || (cfg.txt && cfg.txt.error) || 'Error') + '</p></div>');
                        if (window.AuraUI && modalEl) window.AuraUI.hideModalSkeleton(modalEl);
                        return;
                    }
                    var eq     = res.data.equipment;
                    var mhist  = res.data.maintenance_history;
                    var lhist  = res.data.loan_history;
                    var comps  = res.data.components || [];

                    $('#aura-inv-detail-title').text(eq.name + (eq.brand ? ' · ' + eq.brand : ''));

                    var statusLbl = (cfg.txt && cfg.txt.status_labels && cfg.txt.status_labels[eq.status]) || eq.status_label || eq.status;
                    var photoUrl  = eq.photo_full_url || eq.photo;
                    var html = '';

                    // HERO 360° (imagen natural sin recorte + datos clave + botones rápidos)
                    html += '<div class="aura-inv-detail-hero">';
                    html += '<div class="aura-inv-detail-hero-photo">';
                    if (photoUrl) {
                        html += '<img src="' + escHtml(photoUrl) + '" alt="' + escHtml(eq.name) + '" class="aura-table-thumb-preview" ' +
                                'data-img-url="' + escHtml(photoUrl) + '" data-preview-title="' + escHtml(eq.name) + '">';
                    } else {
                        html += '<div class="aura-inv-detail-no-photo"><span class="dashicons dashicons-format-image"></span><small>Sin fotografía</small></div>';
                    }
                    html += '</div>';

                    html += '<div class="aura-inv-detail-hero-info">';
                    html += '<div class="aura-inv-detail-badges">' +
                            '<span class="aura-inv-status-badge aura-inv-status-' + escHtml(eq.status) + '">' + escHtml(statusLbl) + '</span>' +
                            (eq.category ? '<span class="aura-inv-cat-chip">' + escHtml(eq.category) + '</span>' : '') +
                            (eq.internal_code ? '<span class="aura-inv-code-chip">Cód: ' + escHtml(eq.internal_code) + '</span>' : '') +
                            '</div>';
                    html += '<h3 class="aura-inv-detail-name">' + escHtml(eq.name) + '</h3>';
                    if (eq.brand || eq.model) {
                        html += '<p class="aura-inv-detail-brand">' + escHtml([eq.brand, eq.model].filter(Boolean).join(' · ')) + '</p>';
                    }
                    if (eq.description) {
                        html += '<div class="aura-inv-detail-desc">' + escHtml(eq.description).replace(/\n/g, '<br>') + '</div>';
                    }
                    if (cfg.can_maint !== false) {
                        html += '<div class="aura-inv-detail-quick-actions">';
                        html += '<a href="' + cfg.newMaintUrl + '&equipment_id=' + eq.id + '" class="btn btn-indigo btn-sm">' +
                                '<span class="dashicons dashicons-admin-tools"></span> ' + escHtml((cfg.txt && cfg.txt.register_maint) || 'Registrar Mantenimiento') + '</a>';
                        if (cfg.maintListUrl) {
                            html += '<a href="' + cfg.maintListUrl + '&equipment_id=' + eq.id + '" class="btn btn-glass btn-sm">' +
                                    '<span class="dashicons dashicons-list-view"></span> ' + escHtml((cfg.txt && cfg.txt.view_maint_history) || 'Historial de Mantenimientos') + '</a>';
                        }
                        html += '</div>';
                    }
                    html += '</div>'; // .aura-inv-detail-hero-info
                    html += '</div>'; // .aura-inv-detail-hero

                    var startSection = function (title, icon) {
                        return '<div class="aura-inv-detail-section">' +
                            '<div class="aura-inv-detail-section-title">' +
                            (icon ? '<span class="dashicons dashicons-' + icon + '"></span> ' : '') + escHtml(title) + '</div>' +
                            '<div class="aura-inv-detail-grid">';
                    };

                    // SECCIÓN I: INFORMACIÓN GENERAL
                    html += startSection('Información General', 'info');
                    html += detailRow('Marca', escHtml(eq.brand || '—'));
                    html += detailRow('Modelo', escHtml(eq.model || '—'));
                    html += detailRow('Código interno', escHtml(eq.internal_code || '—'));
                    html += detailRow('Número de serie', escHtml(eq.serial_number || '—'));
                    html += detailRow('Categoría', escHtml(eq.category || '—'));
                    html += detailRow('Ubicación', escHtml(eq.location || '—'));
                    html += detailRow('Responsable', escHtml(eq.responsible_name || '—'));
                    if (eq.parent_equipment_id && eq.parent_equipment_name) {
                        html += detailRow('Componente de', '<span class="aura-inv-parent-link">↑ ' + escHtml(eq.parent_equipment_name) + '</span>');
                    }
                    html += '</div></div>';

                    // SECCIÓN II: ADQUISICIÓN Y VALOR
                    html += startSection('Adquisición y Valor', 'cart');
                    html += detailRow('Fecha adquisición', escHtml(eq.acquisition_date || '—'));
                    html += detailRow('Proveedor', escHtml(eq.supplier || '—'));
                    html += detailRow('Vencimiento garantía', escHtml(eq.warranty_date || '—'));
                    html += detailRow('Costo', eq.cost > 0 ? '$' + parseFloat(eq.cost).toLocaleString() : '—');
                    html += detailRow('Valor estimado', eq.estimated_value > 0 ? '$' + parseFloat(eq.estimated_value).toLocaleString() : '—');
                    html += '</div></div>';

                    // SECCIÓN III: ESPECIFICACIONES TÉCNICAS
                    if (eq.oil_type || eq.oil_capacity || eq.fuel_type || eq.voltage || eq.hydraulic_pressure) {
                        html += startSection('Especificaciones Técnicas', 'dashboard');
                        if (eq.oil_type || eq.oil_capacity) html += detailRow('Aceite', escHtml((eq.oil_capacity ? eq.oil_capacity + 'L ' : '') + (eq.oil_type || '—')));
                        if (eq.fuel_type) html += detailRow('Combustible', escHtml(eq.fuel_type));
                        if (eq.voltage) html += detailRow('Voltaje', escHtml(eq.voltage + 'V'));
                        if (eq.hydraulic_pressure) html += detailRow('Presión', escHtml(eq.hydraulic_pressure));
                        html += '</div></div>';
                    }

                    // SECCIÓN IV: MANTENIMIENTO
                    html += startSection('Mantenimiento Programado', 'admin-tools');
                    var intervalStr = 'No requiere';
                    if (eq.requires_maintenance == 1) {
                        var p = [];
                        if (eq.interval_months && (eq.interval_type === 'time' || eq.interval_type === 'both')) p.push('Cada ' + eq.interval_months + ' meses');
                        if (eq.interval_hours && (eq.interval_type === 'hours' || eq.interval_type === 'both')) p.push('Cada ' + eq.interval_hours + ' horas');
                        intervalStr = p.join(' o ') || 'Definido';
                    }
                    html += detailRow('Frecuencia requerida', escHtml(intervalStr));
                    html += detailRow('Último mantenimiento', escHtml(eq.last_maintenance_date || '—'));
                    html += detailRow('Próximo mantenimiento', eq.next_maintenance_date ? '<strong class="aura-inv-text-danger">' + escHtml(eq.next_maintenance_date) + '</strong>' : '—');
                    html += detailRow('Total invertido', eq.total_maintenance_cost > 0 ? '$' + parseFloat(eq.total_maintenance_cost).toLocaleString() : '—');
                    html += '</div></div>';

                    if (eq.maintenance_instructions) {
                        html += '<div class="aura-inv-maint-instr-callout">';
                        html += '<div class="aura-inv-maint-instr-hdr"><span class="dashicons dashicons-clipboard"></span> Instrucciones de mantenimiento</div>';
                        html += '<div class="aura-inv-maint-instr-body">' + escHtml(eq.maintenance_instructions).replace(/\n/g, '<br>') + '</div>';
                        html += '</div>';
                    }

                    // Accesorios incluidos
                    if (eq.accessories) {
                        var accItems = eq.accessories.split('\n').filter(function (l) { return l.trim() !== ''; });
                        if (accItems.length > 0) {
                            html += '<div class="aura-inv-detail-section">';
                            html += '<div class="aura-inv-detail-section-title"><span class="dashicons dashicons-products"></span> Accesorios incluidos</div>';
                            html += '<ul class="aura-inv-acc-list">';
                            accItems.forEach(function (item) {
                                html += '<li>' + escHtml(item.trim()) + '</li>';
                            });
                            html += '</ul></div>';
                        }
                    }

                    // Componentes vinculados
                    if (comps.length > 0) {
                        html += '<div class="aura-inv-detail-section">';
                        html += '<div class="aura-inv-detail-section-title"><span class="dashicons dashicons-networking"></span> Componentes vinculados (' + comps.length + ')</div>';
                        html += '<div class="aura-inv-subtable-wrap"><table class="aura-table aura-inv-detail-subtable">';
                        html += '<thead><tr><th>Nombre</th><th>Categoría</th><th>Cód. interno</th><th>Estado</th></tr></thead><tbody>';
                        comps.forEach(function (c) {
                            html += '<tr>' +
                                '<td><strong>' + escHtml(c.name) + '</strong>' + (c.brand ? ' <small>' + escHtml(c.brand) + '</small>' : '') + '</td>' +
                                '<td>' + escHtml(c.category || '—') + '</td>' +
                                '<td>' + escHtml(c.internal_code || '—') + '</td>' +
                                '<td><span class="aura-inv-status-badge aura-inv-status-' + escHtml(c.status) + '">' + escHtml(c.status_label || c.status) + '</span></td>' +
                                '</tr>';
                        });
                        html += '</tbody></table></div></div>';
                    }

                    // Últimos mantenimientos
                    if (mhist && mhist.length > 0) {
                        html += '<div class="aura-inv-detail-section">';
                        html += '<div class="aura-inv-detail-section-title"><span class="dashicons dashicons-hammer"></span> Últimos mantenimientos (' + mhist.length + ')</div>';
                        html += '<div class="aura-inv-subtable-wrap"><table class="aura-table aura-inv-detail-subtable">';
                        html += '<thead><tr><th>Fecha</th><th>Tipo</th><th>Total</th><th>Ejecutor</th><th style="width:60px;text-align:center;">Detalle</th></tr></thead><tbody>';
                        mhist.forEach(function (m, index) {
                            var descHTML  = m.description ? escHtml(m.description).replace(/\n/g, '<br>') : '—';
                            var partsHTML = m.replaced_parts ? escHtml(m.replaced_parts).replace(/\n/g, '<br>') : '';
                            var rowId     = 'maint-desc-' + m.id + '-' + index;

                            html += '<tr><td>' + escHtml(m.maintenance_date) + '</td><td>' + escHtml(m.type_label) +
                                '</td><td>$' + parseFloat(m.total_cost).toLocaleString() +
                                '</td><td>' + escHtml(m.performed_by === 'external'
                                    ? (m.workshop_name || 'Externo')
                                    : (m.technician_name || 'Interno')) + '</td>' +
                                '<td style="text-align:center;">' +
                                '<button type="button" class="btn btn-glass btn-sm" onclick="auraToggleMaint(this, \'' + rowId + '\')" data-tooltip="Ver descripción del trabajo">' +
                                '<span class="dashicons dashicons-text-page"></span></button>' +
                                '</td></tr>';

                            html += '<tr id="' + rowId + '" class="aura-inv-maint-desc-row" style="display:none;">' +
                                '<td colspan="5"><div class="aura-inv-maint-desc-box">' +
                                '<strong>Descripción del trabajo:</strong><div class="aura-inv-maint-desc-text">' + descHTML + '</div>' +
                                (partsHTML ? '<strong style="margin-top:10px;display:block;">Repuestos/Insumos usados:</strong><div class="aura-inv-maint-desc-text">' + partsHTML + '</div>' : '') +
                                '</div></td></tr>';
                        });
                        html += '</tbody></table></div></div>';
                    }

                    // Últimos préstamos
                    if (lhist && lhist.length > 0) {
                        html += '<div class="aura-inv-detail-section">';
                        html += '<div class="aura-inv-detail-section-title"><span class="dashicons dashicons-migrate"></span> Últimos préstamos</div>';
                        html += '<div class="aura-inv-subtable-wrap"><table class="aura-table aura-inv-detail-subtable">';
                        html += '<thead><tr><th>Fecha salida</th><th>Tomado por</th><th>Devolución esperada</th><th>Devuelto</th></tr></thead><tbody>';
                        lhist.forEach(function (l) {
                            var bname = l.borrower_display || l.borrower_name || '—';
                            var avatarHtml;
                            if (l.borrower_avatar) {
                                avatarHtml = '<img src="' + escHtml(l.borrower_avatar) + '" width="28" height="28" ' +
                                    'class="aura-inv-mini-avatar avatar-zoomable" data-preview-title="' + escHtml(bname) + '" alt="' + escHtml(bname) + '">';
                            } else {
                                var initials = bname.split(' ').filter(Boolean).slice(0, 2)
                                    .map(function (w) { return (w[0] || '').toUpperCase(); }).join('');
                                avatarHtml = '<span class="aura-inv-mini-initials">' + escHtml(initials || '?') + '</span>';
                            }
                            html += '<tr>' +
                                '<td>' + escHtml(l.loan_date) + '</td>' +
                                '<td class="aura-inv-borrower-td">' + avatarHtml + '<span>' + escHtml(bname) + '</span></td>' +
                                '<td>' + escHtml(l.expected_return_date || '—') + '</td>' +
                                '<td>' + (l.actual_return_date
                                    ? '<span class="aura-inv-text-ok">' + escHtml(l.actual_return_date) + '</span>'
                                    : '<span class="aura-inv-text-danger">Pendiente</span>') + '</td>' +
                                '</tr>';
                        });
                        html += '</tbody></table></div></div>';
                    }

                    $body.html(html);
                    if (window.AuraUI && modalEl) {
                        window.AuraUI.hideModalSkeleton(modalEl);
                    }
                },
                error: function () {
                    $body.html('<div class="aura-inv-empty-state"><p>' + escHtml((cfg.txt && cfg.txt.error) || 'Error al cargar detalle') + '</p></div>');
                    if (window.AuraUI && modalEl) {
                        window.AuraUI.hideModalSkeleton(modalEl);
                    }
                },
            });
        },

        deleteEquipment: function (id, name) {
            var cfg  = this.cfg;
            var self = this;
            var msg  = (cfg.txt && cfg.txt.confirm_delete) ? cfg.txt.confirm_delete.replace('{name}', name) : ('¿Eliminar "' + name + '"?');
            if (!window.confirm(msg)) return;

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_equipment_delete', nonce: cfg.nonce, id: id },
                success: function (res) {
                    if (res.success) {
                        self.showNotice((cfg.txt && cfg.txt.deleted) || 'Equipo eliminado.', 'success');
                        setTimeout(function () { window.location.reload(); }, 450);
                    } else {
                        self.showNotice((res.data && res.data.message) || (cfg.txt && cfg.txt.error) || 'Error', 'error');
                    }
                },
                error: function () { self.showNotice((cfg.txt && cfg.txt.error) || 'Error de conexión.', 'error'); },
            });
        },

        updateStatus: function (id, newStatus, $sel) {
            var cfg  = this.cfg;
            var self = this;
            if ($sel && $sel.length) {
                $sel.removeClass('aura-inv-status-available aura-inv-status-in_use aura-inv-status-maintenance aura-inv-status-repair aura-inv-status-retired')
                    .addClass('aura-inv-status-' + newStatus);
            }
            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_equipment_update_status', nonce: cfg.nonce, id: id, status: newStatus },
                success: function (res) {
                    if (res.success) {
                        self.showNotice((res.data && res.data.message) || 'Estado actualizado.', 'success');
                    } else {
                        self.showNotice((res.data && res.data.message) || (cfg.txt && cfg.txt.error) || 'Error', 'error');
                    }
                },
                error: function () {
                    self.showNotice((cfg.txt && cfg.txt.error) || 'Error de conexión.', 'error');
                }
            });
        },

        showNotice: function (msg, type) {
            if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                window.AuraUI.showToast(msg, type === 'error' ? 'error' : 'success');
                return;
            }
            var cls = type === 'error' ? 'notice-error' : 'notice-success';
            var $n  = $('<div class="notice ' + cls + ' is-dismissible" style="margin:12px 0;"><p>' + escHtml(msg) + '</p></div>');
            $('.aura-page-header, .wp-header-end').first().after($n);
            setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
        },
    };

    // ─────────────────────────────────────────────────────────────
    // MÓDULO: FORMULARIO DE EQUIPO
    // ─────────────────────────────────────────────────────────────
    var EquipmentForm = {

        cfg: null,
        cropperInstance: null,
        pendingAttachmentId: null,
        pendingAttachmentUrl: null,
        currentRatio: NaN,

        init: function () {
            if (typeof auraInvFormData === 'undefined') return;
            this.cfg = auraInvFormData;
            this.bindEvents();
            this.bindCropModal();
            this.initThirdPartySelectors();
        },

        initThirdPartySelectors: function () {
            if (typeof window.AuraThirdPartySelector === 'undefined') return;

            function applySupplierItem(item) {
                var name = item.commercial_name || item.name || '';
                var tpId = item.third_party_id || item.id || '';
                $('#inv_supplier').val(name);
                $('#inv_supplier_third_party_id').val(tpId);
                if (tpId) {
                    $('#aura-inv-supplier-badge').show();
                    $('#aura-inv-btn-supplier-clear').show();
                }
            }

            function applyResponsibleItem(item) {
                var name = item.commercial_name || item.name || '';
                var isUser = !!(item.is_wp_user || item.party_type === 'user' || item.user_id);
                $('#inv_responsible_display').val(name);
                if (isUser) {
                    $('#inv_responsible_user_id').val(item.user_id || item.id || '');
                    $('#inv_responsible_third_party_id').val('');
                    $('#aura-inv-responsible-badge-text').text('Usuario WP');
                } else {
                    $('#inv_responsible_third_party_id').val(item.third_party_id || item.id || '');
                    $('#inv_responsible_user_id').val('');
                    $('#aura-inv-responsible-badge-text').text('Tercero');
                }
                $('#aura-inv-responsible-badge').show();
                $('#aura-inv-btn-responsible-clear').show();
            }

            window.AuraThirdPartySelector.attach({
                input: '#inv_supplier',
                hiddenId: '#inv_supplier_third_party_id',
                allowUsers: false,
                onSelect: applySupplierItem,
                onClear: function () {
                    $('#inv_supplier_third_party_id').val('');
                    $('#aura-inv-supplier-badge, #aura-inv-btn-supplier-clear').hide();
                }
            });

            $('#aura-inv-btn-supplier-explorer').on('click', function () {
                window.AuraThirdPartySelector.openExplorer({
                    defaultTab: 'all',
                    onSelect: applySupplierItem
                });
            });

            $('#aura-inv-btn-supplier-clear').on('click', function () {
                $('#inv_supplier').val('');
                $('#inv_supplier_third_party_id').val('');
                $('#aura-inv-supplier-badge, #aura-inv-btn-supplier-clear').hide();
            });

            window.AuraThirdPartySelector.attach({
                input: '#inv_responsible_display',
                allowUsers: true,
                onSelect: applyResponsibleItem,
                onClear: function () {
                    $('#inv_responsible_user_id, #inv_responsible_third_party_id').val('');
                    $('#aura-inv-responsible-badge, #aura-inv-btn-responsible-clear').hide();
                }
            });

            $('#aura-inv-btn-responsible-explorer').on('click', function () {
                window.AuraThirdPartySelector.openExplorer({
                    defaultTab: 'all',
                    allowUsers: true,
                    onSelect: applyResponsibleItem
                });
            });

            $('#aura-inv-btn-responsible-clear').on('click', function () {
                $('#inv_responsible_display').val('');
                $('#inv_responsible_user_id, #inv_responsible_third_party_id').val('');
                $('#aura-inv-responsible-badge, #aura-inv-btn-responsible-clear').hide();
            });
        },

        bindEvents: function () {
            var self = this;
            var cfg  = this.cfg;

            $('#inv_requires_maintenance').on('change', function () {
                $('#aura-inv-maintenance-fields').toggle(this.checked);
            });

            $('input[name="interval_type"]').on('change', function () {
                self.toggleIntervalRows($(this).val());
            }).filter(':checked').trigger('change');

            $('#inv_category').on('change', function () {
                var slug = $(this).val();
                var hint = (cfg.categoryHints && cfg.categoryHints[slug]) ? cfg.categoryHints[slug].hint : '';
                $('#inv_category_hint').text(hint);

                if (cfg.categoryHints && cfg.categoryHints[slug]) {
                    var cat = cfg.categoryHints[slug];
                    if (cat.interval_type && cat.interval_type !== 'none') {
                        $('input[name="interval_type"][value="' + cat.interval_type + '"]').prop('checked', true).trigger('change');
                        if (cat.interval_months) $('#inv_interval_months').val(cat.interval_months);
                        if (cat.interval_hours)  $('#inv_interval_hours').val(cat.interval_hours);
                        $('#inv_requires_maintenance').prop('checked', true);
                        $('#aura-inv-maintenance-fields').show();
                    }
                }
            });

            $('#aura-inv-select-photo').on('click', function (e) {
                e.preventDefault();
                var frame = wp.media({
                    title:    'Seleccionar foto del equipo',
                    button:   { text: 'Usar esta imagen' },
                    multiple: false,
                    library:  { type: 'image' }
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    self.openCropModal(attachment.id, attachment.url);
                });
                frame.open();
            });

            $(document).on('click', '#aura-inv-remove-photo', function () {
                $('#inv_photo').val('');
                $('#aura-inv-photo-preview').html('<span class="dashicons dashicons-format-image aura-inv-photo-placeholder"></span>');
            });

            $('#aura-inv-equipment-form').on('submit', function (e) {
                e.preventDefault();
                self.submit();
            });
        },

        // ── Crop modal (Item 26 + ratio flexible) ─────────────────
        openCropModal: function (attachmentId, imageUrl) {
            var self = this;
            self.pendingAttachmentId  = attachmentId;
            self.pendingAttachmentUrl = imageUrl;

            var $modal = $('#aura-crop-modal');
            var $img   = $('#aura-crop-img');

            if (self.cropperInstance) {
                self.cropperInstance.destroy();
                self.cropperInstance = null;
            }

            $img.attr('src', imageUrl);
            if (window.AuraUI && typeof window.AuraUI.openModal === 'function') {
                window.AuraUI.openModal('aura-crop-modal');
            } else {
                $modal.addClass('active is-active').css('display', 'flex');
            }

            $img.off('load.cropper').on('load.cropper', function () {
                if (typeof Cropper === 'undefined') return;
                self.cropperInstance = new Cropper($img[0], {
                    aspectRatio:   isNaN(self.currentRatio) ? NaN : self.currentRatio,
                    viewMode:      1,
                    dragMode:      'move',
                    autoCropArea:  0.95,
                    responsive:    true,
                    checkCrossOrigin: false,
                });
            });
            if ($img[0] && $img[0].complete && $img[0].naturalWidth) {
                $img.trigger('load.cropper');
            }
        },

        bindCropModal: function () {
            var self = this;
            var cfg  = this.cfg;

            $(document).on('click', '.aura-crop-ratio-btn', function () {
                $('.aura-crop-ratio-btn').removeClass('is-active');
                $(this).addClass('is-active');
                var raw = String($(this).data('ratio'));
                self.currentRatio = (raw === 'NaN') ? NaN : parseFloat(raw);
                if (self.cropperInstance) {
                    self.cropperInstance.setAspectRatio(self.currentRatio);
                }
            });

            $('#aura-crop-use-original').on('click', function () {
                if (!self.pendingAttachmentId || !self.pendingAttachmentUrl) return;
                $('#inv_photo').val(self.pendingAttachmentId);
                $('#aura-inv-photo-preview').html(
                    '<img src="' + escHtml(self.pendingAttachmentUrl) + '" style="max-width:100%;max-height:240px;width:auto;height:auto;object-fit:contain;margin:0 auto;border-radius:8px;">'
                );
                self.closeCropModal();
            });

            $('#aura-crop-apply').on('click', function () {
                if (!self.cropperInstance) return;
                var $btn = $(this);
                $btn.prop('disabled', true);
                $btn.find('.dashicons').attr('class', 'dashicons dashicons-update aura-maint-spin');

                var data = self.cropperInstance.getData(true);

                $.ajax({
                    url:    cfg.ajaxurl,
                    method: 'POST',
                    data: {
                        action:        'aura_inventory_equipment_crop_photo',
                        nonce:         cfg.nonce,
                        attachment_id: self.pendingAttachmentId,
                        x:             data.x,
                        y:             data.y,
                        width:         data.width,
                        height:        data.height,
                    },
                    success: function (res) {
                        if (res.success) {
                            $('#inv_photo').val(res.data.attachment_id);
                            $('#aura-inv-photo-preview').html(
                                '<img src="' + escHtml(res.data.full_url) + '" style="max-width:100%;max-height:240px;width:auto;height:auto;object-fit:contain;margin:0 auto;border-radius:8px;">'
                            );
                            self.closeCropModal();
                        } else {
                            EquipmentList.showNotice((res.data && res.data.message) || 'Error al procesar imagen.', 'error');
                        }
                    },
                    error: function () {
                        EquipmentList.showNotice('Error de conexión al procesar la imagen.', 'error');
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                        $btn.find('.dashicons').attr('class', 'dashicons dashicons-yes');
                    },
                });
            });

            $('#aura-crop-cancel, #aura-crop-close').on('click', function () {
                self.closeCropModal();
            });
            bindTwoPhaseBackdropGuard('#aura-crop-modal', function () {
                self.closeCropModal();
            });
            $(document).on('keydown.aura-crop', function (e) {
                if (e.key === 'Escape' && ($('#aura-crop-modal').hasClass('active') || $('#aura-crop-modal').is(':visible'))) {
                    self.closeCropModal();
                }
            });
        },

        closeCropModal: function () {
            if (this.cropperInstance) {
                this.cropperInstance.destroy();
                this.cropperInstance = null;
            }
            if (window.AuraUI && typeof window.AuraUI.closeModal === 'function') {
                window.AuraUI.closeModal('aura-crop-modal');
            } else {
                $('#aura-crop-modal').removeClass('active is-active').hide();
                $('body').css('overflow', '');
            }
            this.pendingAttachmentId  = null;
            this.pendingAttachmentUrl = null;
        },

        toggleIntervalRows: function (type) {
            $('#inv_row_months').toggle(type === 'time' || type === 'both');
            $('#inv_row_hours').toggle(type === 'hours' || type === 'both');
        },

        submit: function () {
            var self    = this;
            var cfg     = this.cfg;
            var $btn    = $('#aura-inv-save-btn');
            var $notice = $('#aura-inv-form-notice');

            var name = $.trim($('#inv_name').val());
            if (!name) {
                self.showFormNotice(cfg.txt.required, 'error');
                return;
            }

            $btn.prop('disabled', true).text(cfg.txt.saving);
            $notice.html('');

            var formData = new FormData(document.getElementById('aura-inv-equipment-form'));
            formData.append('action', 'aura_inventory_equipment_save');
            formData.append('nonce',  cfg.nonce);
            formData.append('id',     cfg.equipmentId || 0);

            $.ajax({
                url:         cfg.ajaxurl,
                method:      'POST',
                data:        formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-yes"></span> ' + (cfg.isEdit ? 'Actualizar equipo' : 'Registrar equipo'));
                    if (res.success) {
                        self.showFormNotice(cfg.txt.saved, 'success');
                        if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                            window.AuraUI.showToast(cfg.txt.saved, 'success');
                        }
                        if (!cfg.isEdit) {
                            setTimeout(function () { window.location.href = cfg.listUrl; }, 900);
                        } else {
                            setTimeout(function () { window.location.reload(); }, 900);
                        }
                    } else {
                        self.showFormNotice((res.data && res.data.message) || cfg.txt.error, 'error');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> ' + (cfg.isEdit ? 'Actualizar equipo' : 'Registrar equipo'));
                    self.showFormNotice(cfg.txt.error, 'error');
                },
            });
        },

        showFormNotice: function (msg, type) {
            var isErr = type === 'error';
            var cls   = isErr ? 'aura-inv-form-alert-error' : 'aura-inv-form-alert-ok';
            $('#aura-inv-form-notice').html('<div class="aura-inv-form-alert ' + cls + '">' + escHtml(msg) + '</div>');
        },
    };

    // ─────────────────────────────────────────────────────────────
    // CONTROLADOR GLOBAL PARA ACORDEÓN DE MANTENIMIENTOS EN FICHA 360°
    // ─────────────────────────────────────────────────────────────
    window.auraToggleMaint = function (btn, rowId) {
        var $btn  = $(btn);
        var $row  = $('#' + rowId);
        var $icon = $btn.find('.dashicons');

        $('.aura-inv-maint-desc-row').not($row).hide();
        $('.aura-inv-detail-subtable .dashicons-arrow-up-alt2')
            .removeClass('dashicons-arrow-up-alt2')
            .addClass('dashicons-text-page');

        if ($row.css('display') === 'none') {
            $row.css('display', 'table-row');
            $icon.removeClass('dashicons-text-page').addClass('dashicons-arrow-up-alt2');
        } else {
            $row.css('display', 'none');
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-text-page');
        }
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

    function detailRow(label, value) {
        return '<div class="aura-inv-detail-row">' +
            '<span class="aura-inv-detail-label">' + escHtml(label) + '</span>' +
            '<span class="aura-inv-detail-value">' + value + '</span>' +
            '</div>';
    }

    function childSpec(label, value) {
        return '<div class="aura-inv-child-spec">' +
            '<span class="aura-inv-child-spec-lbl">' + escHtml(label) + '</span>' +
            '<strong class="aura-inv-child-spec-val">' + escHtml(value) + '</strong>' +
            '</div>';
    }

    function renderStatusOptions(labels, current) {
        var html = '';
        Object.keys(labels).forEach(function (key) {
            html += '<option value="' + escHtml(key) + '"' + (key === current ? ' selected' : '') + '>' + escHtml(labels[key]) + '</option>';
        });
        return html;
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

    // ─────────────────────────────────────────────────────────────
    // ARRANQUE
    // ─────────────────────────────────────────────────────────────
    $(function () {
        EquipmentList.init();
        EquipmentForm.init();
    });

}(jQuery));
