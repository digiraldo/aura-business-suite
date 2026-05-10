/**
 * Inventario — Mantenimientos JS
 * Módulos: MaintenanceList (listado AJAX) y MaintenanceForm (formulario AJAX)
 *
 * Depende de: jQuery, auraMaintList (inyectado en maintenance-list.php)
 *             auraMaintForm (inyectado en maintenance-form.php)
 */

/* global jQuery, auraMaintList, auraMaintForm, wp */

(function ($) {
    'use strict';

    // ─────────────────────────────────────────────────────────────
    // MÓDULO: LISTADO DE MANTENIMIENTOS — DataTables
    // ─────────────────────────────────────────────────────────────
    var MaintenanceList = {

        cfg:   null,
        table: null,

        init: function () {
            if (typeof auraMaintList === 'undefined') return;
            this.cfg = auraMaintList;

            // Pre-seleccionar equipo si viene por URL
            if (this.cfg.preselectEquip) {
                $('#aura-maint-filter-equipment').val(this.cfg.preselectEquip);
            }
            this.initDataTable();
            this.bindEvents();
        },

        // ── Helpers de render ────────────────────────────────────
        _thumb: function (m) {
            return m.photo_thumb_url
                ? '<div class="aura-inv-thumb-wrap" data-full="' + escHtml(m.photo_full_url || m.photo_thumb_url) + '">' +
                  '<img src="' + escHtml(m.photo_thumb_url) + '" alt="" width="44" height="33"' +
                  ' style="border-radius:3px;object-fit:cover;display:block;border:1px solid #dcdcde;">' +
                  '</div>'
                : '<span class="dashicons dashicons-format-image" style="color:#c3c4c7;font-size:24px;" title="Sin foto"></span>';
        },

        _typeBadge: function (m) {
            var cfg = this.cfg;
            return '<span class="aura-maint-type-badge aura-maint-type-' + m.type + '">' +
                   escHtml(cfg.txt.type_labels[m.type] || m.type) + '</span>';
        },

        _executor: function (m) {
            var cfg = this.cfg;
            return m.performed_by === 'external'
                ? '<span class="aura-maint-exec-badge external"><span class="dashicons dashicons-store"></span>' +
                  escHtml(m.workshop_name || cfg.txt.performed_labels.external) + '</span>'
                : '<span class="aura-maint-exec-badge"><span class="dashicons dashicons-businessman"></span>' +
                  escHtml(m.tech_name || cfg.txt.performed_labels.internal) + '</span>';
        },

        _costCell: function (m) {
            var currency = this.cfg.currency || '$';
            return m.total_cost > 0
                ? '<span class="aura-maint-cost-cell">' + currency +
                  parseFloat(m.total_cost).toLocaleString('es', {minimumFractionDigits:2}) + '</span>'
                : '<span class="aura-maint-cost-cell zero">—</span>';
        },

        _psBadge: function (m) {
            var cfg = this.cfg;
            return '<span class="aura-maint-ps-badge aura-maint-ps-' + m.post_status + '">' +
                   escHtml(cfg.txt.post_status_labels[m.post_status] || m.post_status) + '</span>';
        },

        _finBadge: function (m) {
            var cfg = this.cfg;
            return m.has_finance
                ? '<span class="aura-maint-finance-ok"><span class="dashicons dashicons-yes-alt"></span>' + cfg.txt.has_finance + '</span>'
                : '<span style="color:#c3c4c7;">' + cfg.txt.no_finance + '</span>';
        },

        _actions: function (m) {
            var cfg = this.cfg;
            var h = '<div class="aura-inv-row-actions">';
            h += '<button class="aura-inv-btn-icon aura-maint-btn-detail" data-id="' + m.id + '" title="Ver detalle">' +
                 '<span class="dashicons dashicons-visibility"></span></button>';
            if (m.can_edit) {
                h += '<button class="aura-inv-btn-icon aura-maint-btn-edit" data-id="' + m.id + '" title="Editar">' +
                     '<span class="dashicons dashicons-edit"></span></button>';
            }
            if (m.can_delete) {
                h += '<button class="aura-inv-btn-icon delete aura-maint-btn-delete" data-id="' + m.id + '" title="Eliminar">' +
                     '<span class="dashicons dashicons-trash"></span></button>';
            }
            h += '</div>';
            return h;
        },

        // ── Inicializar DataTable ────────────────────────────────
        initDataTable: function () {
            var self = this;
            var cfg  = this.cfg;

            // Evitar re-inicialización si WP ya cargó el script antes
            if ($.fn.DataTable.isDataTable('#aura-maint-table')) {
                this.table = $('#aura-maint-table').DataTable();
                return;
            }

            this.table = new DataTable('#aura-maint-table', {
                responsive: true,
                processing: true,
                serverSide: false,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                order: [[1, 'desc']],       // Fecha descendente por defecto
                language: {
                    processing:  cfg.txt.loading || 'Cargando…',
                    zeroRecords: cfg.txt.no_results || 'No se encontraron mantenimientos.',
                    info:        '_TOTAL_ registros',
                    infoEmpty:   '0 registros',
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
                            action:       'aura_inventory_maintenance_get_list',
                            nonce:        cfg.nonce,
                            page:         1,
                            per_page:     9999,
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
                        title: 'Foto',
                        data: 'photo_thumb_url',
                        orderable: false,
                        className: 'col-photo',
                        width: '58px',
                        render: function (data, type, item) {
                            return type === 'display' ? self._thumb(item) : item.equipment_name || '';
                        },
                    },
                    {
                        title: 'Equipo', data: 'equipment_name',
                        render: function (data, type, m) {
                            if (type !== 'display') return data || '';
                            var h = '<strong>' + escHtml(m.equipment_name) + '</strong>';
                            var sub = [
                                m.equipment_brand ? escHtml(m.equipment_brand) : '',
                                m.equipment_code  ? 'Cód: ' + escHtml(m.equipment_code) : ''
                            ].filter(Boolean).join(' · ');
                            if (sub) h += '<br><small style="color:#646970;">' + sub + '</small>';
                            return h;
                        },
                    },
                    {
                        title: 'Fecha', data: 'maintenance_date',
                        render: function (data) { return escHtml(data || '—'); },
                    },
                    {
                        title: 'Tipo', data: 'type',
                        render: function (d, t, m) {
                            return t === 'display' ? self._typeBadge(m) : (m.type || '');
                        },
                    },
                    {
                        title: 'Ejecutor', data: 'performed_by',
                        render: function (d, t, m) {
                            return t === 'display' ? self._executor(m) : (m.workshop_name || m.tech_name || '');
                        },
                    },
                    {
                        title: 'Costo total', data: 'total_cost',
                        render: function (d, t, m) {
                            return t === 'display' ? self._costCell(m) : (parseFloat(d) || 0);
                        },
                    },
                    {
                        title: 'Estado post-mant.', data: 'post_status',
                        render: function (d, t, m) {
                            return t === 'display' ? self._psBadge(m) : (d || '');
                        },
                    },
                    {
                        title: 'Finanzas', data: 'has_finance',
                        render: function (d, t, m) {
                            return t === 'display' ? self._finBadge(m) : (d ? '1' : '0');
                        },
                    },
                    {
                        title: 'Acciones', data: null, orderable: false, searchable: false,
                        render: function (d, t, m) {
                            return t === 'display' ? self._actions(m) : '';
                        },
                    },
                ],
            });
        },

        // ── Filtros externos → recargar tabla ────────────────────
        bindEvents: function () {
            var self = this;
            var cfg  = this.cfg;

            $('#aura-maint-filter-apply').on('click', function () { self.table.ajax.reload(); });
            $('#aura-maint-filter-clear').on('click',  function () { self.clearFilters(); });
            $('#aura-maint-search').on('keydown', function (e) {
                if (e.key === 'Enter') self.table.ajax.reload();
            });
            // Enlace "Ver →" de seguimientos pendientes
            $('#aura-maint-filter-followup').on('click', function (e) {
                e.preventDefault();
                $('#aura-maint-filter-post-status').val('needs_followup');
                self.table.ajax.reload();
            });

            // Acciones delegadas
            $(document).on('click', '.aura-maint-btn-detail', function () {
                self.showDetail($(this).data('id'));
            });
            $(document).on('click', '.aura-maint-btn-edit', function () {
                window.location.href = cfg.newUrl + '&id=' + $(this).data('id');
            });
            $(document).on('click', '.aura-maint-btn-delete', function () {
                self.deleteMaintenance($(this).data('id'));
            });

            // Cerrar modales
            $(document).on('click', '.aura-inv-modal-close, .aura-inv-modal-overlay', function () {
                $('.aura-inv-modal').hide();
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') $('.aura-inv-modal').hide();
            });
        },

        clearFilters: function () {
            $('#aura-maint-search').val('');
            $('#aura-maint-filter-equipment, #aura-maint-filter-type, #aura-maint-filter-performed, #aura-maint-filter-post-status').val('');
            $('#aura-maint-filter-date-from, #aura-maint-filter-date-to').val('');
            this.table.ajax.reload();
        },

        showDetail: function (id) {
            var cfg = this.cfg;
            var $modal = $('#aura-maint-detail-modal');
            var $body  = $('#aura-maint-detail-body');

            $body.html('<span class="spinner is-active" style="display:block;margin:30px auto;float:none;"></span>');
            $modal.show();

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_maintenance_get_detail', nonce: cfg.nonce, id: id },
                success: function (res) {
                    if (!res.success) { $body.html('<p>' + (res.data.message || cfg.txt.error) + '</p>'); return; }
                    var m = res.data.maintenance;
                    var currency = cfg.currency || '$';

                    $('#aura-maint-detail-title').text(
                        (cfg.txt.type_labels[m.type] || m.type) + ' — ' + (m.equipment_name || '')
                    );

                    var html = '';
                    if (m.equipment_photo) {
                        html += '<div style="text-align:center;margin-bottom:16px;">'
                             + '<img src="' + escHtml(m.equipment_photo) + '" alt="' + escHtml(m.equipment_name || '') + '"'
                             + ' style="max-width:100%;max-height:220px;object-fit:contain;border-radius:6px;border:1px solid #dcdcde;">'
                             + '</div>';
                    }
                    html += '<div class="aura-maint-detail-grid">';
                    html += dRow('Equipo',            escHtml(m.equipment_name || '—'));
                    html += dRow('Fecha',              escHtml(m.maintenance_date));
                    html += dRow('Tipo',               '<span class="aura-maint-type-badge aura-maint-type-' + m.type + '">' + escHtml(cfg.txt.type_labels[m.type] || m.type) + '</span>');
                    html += dRow('Estado resultante',  '<span class="aura-maint-ps-badge aura-maint-ps-' + m.post_status + '">' + escHtml(cfg.txt.post_status_labels[m.post_status] || m.post_status) + '</span>');
                    html += dRow('Horas del equipo',    m.equipment_hours ? m.equipment_hours + ' h' : '—');
                    html += dRow('Próxima acción',      m.next_action_date || '—');

                    html += '<div class="aura-maint-detail-section">Ejecutor</div>';
                    if (m.performed_by === 'external') {
                        html += dRow('Taller',        escHtml(m.workshop_name || '—'));
                        html += dRow('N° Factura',    escHtml(m.invoice_number || '—'));
                    } else {
                        html += dRow('Técnico interno', escHtml(m.tech_name || '—'));
                    }

                    html += '<div class="aura-maint-detail-section">Costos</div>';
                    html += dRow('Repuestos',       currency + parseFloat(m.parts_cost).toLocaleString('es', {minimumFractionDigits:2}));
                    html += dRow('Mano de obra',    currency + parseFloat(m.labor_cost).toLocaleString('es', {minimumFractionDigits:2}));
                    html += dRow('Total',           '<strong>' + currency + parseFloat(m.total_cost).toLocaleString('es', {minimumFractionDigits:2}) + '</strong>');

                    if (m.description) {
                        html += '<div class="aura-maint-detail-section">Descripción</div>';
                        html += '<div class="aura-maint-detail-row aura-maint-detail-text-row"><div class="aura-maint-detail-value">' + escHtml(m.description) + '</div></div>';
                    }
                    if (m.parts_replaced) {
                        html += '<div class="aura-maint-detail-section">Piezas / repuestos</div>';
                        html += '<div class="aura-maint-detail-row aura-maint-detail-text-row"><div class="aura-maint-detail-value">' + escHtml(m.parts_replaced) + '</div></div>';
                    }
                    if (m.observations) {
                        html += '<div class="aura-maint-detail-section">Observaciones</div>';
                        html += '<div class="aura-maint-detail-row aura-maint-detail-text-row"><div class="aura-maint-detail-value">' + escHtml(m.observations) + '</div></div>';
                    }

                    if (m.finance_transaction_id) {
                        html += '<div class="aura-maint-detail-section">Finanzas</div>';
                        html += dRow('Transacción', '<span class="aura-maint-finance-ok"><span class="dashicons dashicons-yes-alt"></span> #' + m.finance_transaction_id + '</span>');
                    }

                    html += '<div class="aura-maint-detail-section"></div>';
                    html += dRow('Registrado por', escHtml(m.registered_by_name || '—'));
                    html += dRow('Registrado el',  escHtml(m.registered_at ? m.registered_at.substring(0, 10) : '—'));

                    html += '</div>';
                    $body.html(html);
                },
                error: function () { $body.html('<p>' + cfg.txt.error + '</p>'); },
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
                        $('#aura-maint-row-' + id).fadeOut(300, function () { $(this).remove(); });
                        var msg = res.data.had_finance
                            ? res.data.message
                            : cfg.txt.deleted;
                        self.showNotice(msg, res.data.had_finance ? 'warning' : 'success');
                    } else {
                        self.showNotice(res.data.message || cfg.txt.error, 'error');
                    }
                },
                error: function () { self.showNotice(cfg.txt.error, 'error'); },
            });
        },

        showNotice: function (msg, type) {
            var cls = type === 'error'   ? 'notice-error'
                    : type === 'warning' ? 'notice-warning'
                    : 'notice-success';
            var $n = $('<div class="notice ' + cls + ' is-dismissible" style="margin:12px 0;"><p>' + msg + '</p></div>');
            $('.wp-header-end').after($n);
            setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 5000);
        },
    };

    // ─────────────────────────────────────────────────────────────
    // MÓDULO: FORMULARIO DE MANTENIMIENTO
    // ─────────────────────────────────────────────────────────────
    var MaintenanceForm = {

        cfg: null,

        init: function () {
            if (typeof auraMaintForm === 'undefined') return;
            this.cfg = auraMaintForm;
            this.bindEvents();
            this.updateTotal();
        },

        bindEvents: function () {
            var self = this;

            // Toggle interno / externo
            $('input[name="performed_by"]').on('change', function () {
                var ext = $(this).val() === 'external';
                $('#maint_internal_fields').toggle(!ext);
                $('#maint_external_fields').toggle(ext);
            });

            // Toggle campos follow-up
            $('input[name="post_status"]').on('change', function () {
                $('#maint_row_next_action').toggle($(this).val() !== 'operational');
            }).filter(':checked').trigger('change');

            // Toggle finanzas
            $('#maint_create_finance').on('change', function () {
                $('#maint_finance_fields').toggle(this.checked);
            });

            // Cálculo automático del total
            $('#maint_parts_cost, #maint_labor_cost').on('input', function () {
                self.updateTotal();
            });

            // Media uploader para factura
            $('#maint_select_invoice').on('click', function (e) {
                e.preventDefault();
                if (typeof wp === 'undefined' || !wp.media) return;
                var frame = wp.media({ title: 'Seleccionar archivo', button: { text: 'Usar archivo' }, multiple: false });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#maint_workshop_invoice').val(att.url);
                });
                frame.open();
            });

            // Guardar — bind en click del botón (más robusto que form submit)
            $(document).on('click', '#aura-maint-save-btn', function (e) {
                e.preventDefault();
                self.submit();
            });

            // Foto del equipo al cambiar el select
            $('#maint_equipment_id').on('change', function () {
                var equipId = $(this).val();
                self.loadEquipmentPhoto( equipId );
                self.loadEquipmentInstructions( equipId );
            });
        },

        loadEquipmentPhoto: function (equipId) {
            var cfg  = this.cfg;
            var $wrap = $('#maint-equip-photo-wrap');
            var $img  = $('#maint-equip-photo-img');

            if (!equipId) {
                $wrap.hide();
                $img.attr('src', '');
                return;
            }

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data: {
                    action: 'aura_inventory_equipment_get_photo',
                    nonce:  cfg.nonce,
                    id:     equipId,
                },
                success: function (res) {
                    if (res.success && res.data) {
                        var url = res.data.photo_full_url || '';
                        if (url) {
                            $img.attr('src', url);
                            $wrap.show();
                        } else {
                            $wrap.hide();
                        }
                    } else {
                        $wrap.hide();
                    }
                },
                error: function () { $wrap.hide(); }
            });
        },

        /**
         * Cargar instrucciones de mantenimiento y último mantenimiento previo
         * al seleccionar un equipo en el formulario.
         */
        loadEquipmentInstructions: function (equipId) {
            var cfg = this.cfg;
            var txt = cfg.txt;

            var $panel        = $('#maint-instructions-panel');
            var $instrBlock   = $('#maint-instructions-block');
            var $instrText    = $('#maint-instructions-text');
            var $prevBlock    = $('#maint-prev-block');
            var $prevContent  = $('#maint-prev-content');

            if (!equipId) {
                $panel.hide();
                return;
            }

            // Usar el endpoint get_form_data del equipo (retorna el equipo completo)
            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data: {
                    action: 'aura_inventory_equipment_get_form_data',
                    nonce:  cfg.nonce,
                    id:     equipId,
                },
                success: function (res) {
                    if (!res.success || !res.data) { $panel.hide(); return; }

                    var eq           = res.data.equipment || {};
                    var instructions = (eq.maintenance_instructions || '').trim();
                    var showPanel    = false;

                    // — Bloque A: instrucciones —
                    if (instructions) {
                        $instrText.text(instructions);
                        $instrBlock.show();
                        showPanel = true;
                    } else {
                        $instrBlock.hide();
                    }

                    // — Bloque B: último mantenimiento —
                    // Cargamos el historial desde get_detail (ya lo retorna)
                    $.ajax({
                        url:    cfg.ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'aura_inventory_equipment_get_detail',
                            nonce:  cfg.nonce,
                            id:     equipId,
                        },
                        success: function (res2) {
                            if (res2.success && res2.data) {
                                var history = res2.data.maintenance_history || [];
                                if (history.length > 0) {
                                    var prev = history[0]; // ya viene ordenado DESC por fecha
                                    var typeLabel = (txt.type_labels && txt.type_labels[prev.type]) || prev.type;
                                    var psIcon    = (txt.ps_icons  && txt.ps_icons[prev.post_status])  || '';
                                    var psLabel   = (txt.ps_labels && txt.ps_labels[prev.post_status]) || prev.post_status;

                                    var html = '<table style="border-collapse:collapse;width:100%;">';
                                    html += '<tr><td style="padding:2px 8px 2px 0;font-weight:600;">' + escHtml(txt.date_label || 'Fecha:') + '</td>'
                                          + '<td>' + escHtml(prev.maintenance_date || '—') + '</td></tr>';
                                    html += '<tr><td style="padding:2px 8px 2px 0;font-weight:600;">' + escHtml(txt.type_label || 'Tipo:') + '</td>'
                                          + '<td>' + escHtml(typeLabel) + '</td></tr>';
                                    if (prev.description) {
                                        html += '<tr><td style="padding:2px 8px 2px 0;font-weight:600;vertical-align:top;">' + escHtml(txt.work_label || 'Trabajo:') + '</td>'
                                              + '<td>' + escHtml(prev.description).replace(/\n/g,'<br>') + '</td></tr>';
                                    }
                                    if (prev.performed_by === 'external' && prev.technician_name) {
                                        html += '<tr><td style="padding:2px 8px 2px 0;font-weight:600;">' + escHtml(txt.workshop_label || 'Taller:') + '</td>'
                                              + '<td>' + escHtml(prev.technician_name) + '</td></tr>';
                                    }
                                    html += '<tr><td style="padding:2px 8px 2px 0;font-weight:600;">' + escHtml(txt.status_label || 'Estado:') + '</td>'
                                          + '<td>' + psIcon + ' ' + escHtml(psLabel) + '</td></tr>';
                                    html += '</table>';

                                    $prevContent.html(html);
                                    $prevBlock.show();
                                    showPanel = true;
                                } else {
                                    if (!instructions) {
                                        $prevBlock.hide();
                                    } else {
                                        $prevContent.html('<em>' + escHtml(txt.no_prev_maint || 'Sin mantenimientos previos.') + '</em>');
                                        $prevBlock.show();
                                        showPanel = true;
                                    }
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
                        error: function () { $prevBlock.hide(); if (showPanel) { $panel.slideDown(200); } else { $panel.hide(); } }
                    });
                },
                error: function () { $panel.hide(); }
            });
        },

        updateTotal: function () {
            var parts = parseFloat($('#maint_parts_cost').val()) || 0;
            var labor = parseFloat($('#maint_labor_cost').val()) || 0;
            var total = (parts + labor).toFixed(2);
            $('#maint_total_value').text(total);
        },

        submit: function () {
            var self = this;
            var cfg  = this.cfg;
            var $btn = $('#aura-maint-save-btn');
            var $notice = $('#aura-maint-form-notice');

            // Validar
            if (!$('#maint_equipment_id').val()) {
                self.showFormNotice(cfg.txt.required_equip, 'error'); return;
            }
            if (!$('#maint_date').val()) {
                self.showFormNotice(cfg.txt.required_date, 'error'); return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aura-maint-spin"></span> ' + cfg.txt.saving);
            $notice.html('');

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   $('#aura-maint-form').serialize() +
                        '&action=aura_inventory_maintenance_save' +
                        '&nonce=' + encodeURIComponent(cfg.nonce) +
                        '&id='    + encodeURIComponent(cfg.maintId || 0),
                success: function (res) {
                    $btn.prop('disabled', false);
                    var btnLabel = cfg.isEdit ? 'Actualizar mantenimiento' : 'Registrar mantenimiento';
                    if (res.success) {
                        var msg = cfg.txt.saved;
                        if (res.data.finance_id) msg += cfg.txt.finance_ok;
                        $btn.html('<span class="dashicons dashicons-yes"></span> ' + btnLabel);
                        self.showFormNotice(msg, 'success');
                        if (!cfg.isEdit) {
                            setTimeout(function () { window.location.href = cfg.listUrl; }, 1800);
                        }
                    } else {
                        self.showFormNotice(res.data.message || cfg.txt.error, 'error');
                        $btn.html('<span class="dashicons dashicons-yes"></span> ' + btnLabel);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    var btnLabel = cfg.isEdit ? 'Actualizar mantenimiento' : 'Registrar mantenimiento';
                    $btn.html('<span class="dashicons dashicons-yes"></span> ' + btnLabel);
                    self.showFormNotice(cfg.txt.error, 'error');
                },
            });
        },

        showFormNotice: function (msg, type) {
            var cls = type === 'error' ? 'notice-error' : 'notice-success';
            var icon = type === 'error' ? 'warning' : 'yes-alt';

            // Banner al tope de la página (estilo WP nativo)
            $('#aura-maint-top-notice').remove();
            var $topNotice = $(
                '<div id="aura-maint-top-notice" class="notice ' + cls + '" style="display:flex;align-items:center;gap:10px;padding:12px 14px;">' +
                    '<span class="dashicons dashicons-' + icon + '" style="font-size:20px;line-height:20px;flex-shrink:0;"></span>' +
                    '<p style="margin:0;font-size:13px;">' + msg + '</p>' +
                    '<button type="button" class="notice-dismiss" style="margin-left:auto;" title="Cerrar"><span class="screen-reader-text">Cerrar aviso</span></button>' +
                '</div>'
            );
            $topNotice.find('.notice-dismiss').on('click', function () {
                $topNotice.slideUp(150, function () { $topNotice.remove(); });
            });
            var $wrap = $('.aura-inv-maintenance-form');
            $wrap.find('hr.wp-header-end').after($topNotice);
            $('html, body').animate({ scrollTop: $wrap.offset().top - 32 }, 250);

            // También actualizar el pequeño indicador junto al botón
            var color = type === 'error' ? '#d63638' : '#00a32a';
            $('#aura-maint-form-notice').html(
                '<span style="color:' + color + ';font-weight:600;font-size:12px;">' + msg + '</span>'
            );
        },
    };

    // ─────────────────────────────────────────────────────────────
    // HELPERS LOCALES
    // ─────────────────────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function dRow(label, value) {
        return '<div class="aura-maint-detail-row">' +
            '<span class="aura-maint-detail-label">' + escHtml(label) + '</span>' +
            '<span class="aura-maint-detail-value">'  + value          + '</span>' +
            '</div>';
    }

    // ─────────────────────────────────────────────────────────────
    // ARRANQUE
    // ─────────────────────────────────────────────────────────────
    $(function () {
        MaintenanceList.init();
        MaintenanceForm.init();
    });

}(jQuery));
