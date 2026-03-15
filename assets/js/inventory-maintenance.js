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
    // MÓDULO: LISTADO DE MANTENIMIENTOS
    // ─────────────────────────────────────────────────────────────
    var MaintenanceList = {

        cfg:         null,
        currentPage: 1,
        sortBy:      'maintenance_date',
        sortDir:     'DESC',

        init: function () {
            if (typeof auraMaintList === 'undefined') return;
            this.cfg = auraMaintList;
            this.bindEvents();

            // Pre-seleccionar equipo si viene por URL
            if (this.cfg.preselectEquip) {
                $('#aura-maint-filter-equipment').val(this.cfg.preselectEquip);
            }
            this.load();
        },

        bindEvents: function () {
            var self = this;
            var cfg  = this.cfg;

            // Filtros
            $('#aura-maint-filter-apply').on('click',  function () { self.currentPage = 1; self.load(); });
            $('#aura-maint-filter-clear').on('click',  function () { self.clearFilters(); });
            $('#aura-maint-search').on('keydown', function (e) {
                if (e.key === 'Enter') { self.currentPage = 1; self.load(); }
            });
            // Enlace "Ver →" de seguimientos pendientes
            $('#aura-maint-filter-followup').on('click', function (e) {
                e.preventDefault();
                $('#aura-maint-filter-post-status').val('needs_followup');
                self.currentPage = 1; self.load();
            });

            // Paginación
            $('#aura-maint-prev').on('click', function () { if (self.currentPage > 1) { self.currentPage--; self.load(); } });
            $('#aura-maint-next').on('click', function () { self.currentPage++; self.load(); });

            // Ordenamiento
            $(document).on('click', '#aura-maint-table th.sortable', function () {
                var col = $(this).data('sort');
                if (self.sortBy === col) {
                    self.sortDir = self.sortDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    self.sortBy  = col;
                    self.sortDir = 'DESC';
                }
                self.currentPage = 1; self.load();
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
            this.currentPage = 1; this.load();
        },

        load: function () {
            var self = this;
            var cfg  = this.cfg;

            var $tbody = $('#aura-maint-tbody');
            $tbody.html('<tr><td colspan="9" style="text-align:center;padding:30px;">' +
                '<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>' + cfg.txt.loading + '</td></tr>');

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data: {
                    action:       'aura_inventory_maintenance_get_list',
                    nonce:        cfg.nonce,
                    page:         self.currentPage,
                    per_page:     20,
                    search:       $('#aura-maint-search').val(),
                    equipment_id: $('#aura-maint-filter-equipment').val() || 0,
                    type:         $('#aura-maint-filter-type').val(),
                    performed_by: $('#aura-maint-filter-performed').val(),
                    post_status:  $('#aura-maint-filter-post-status').val(),
                    date_from:    $('#aura-maint-filter-date-from').val(),
                    date_to:      $('#aura-maint-filter-date-to').val(),
                    sort_by:      self.sortBy,
                    sort_dir:     self.sortDir,
                },
                success: function (res) {
                    if (!res.success) {
                        $tbody.html('<tr><td colspan="8">' + (res.data.message || cfg.txt.error) + '</td></tr>');
                        return;
                    }
                    var d = res.data;
                    self.renderRows(d.items, $tbody);
                    self.updatePagination(d);
                    self.updateSortHeaders();
                },
                error: function () {
                    $tbody.html('<tr><td colspan="8">' + cfg.txt.error + '</td></tr>');
                },
            });
        },

        renderRows: function (items, $tbody) {
            var cfg = this.cfg;
            if (!items || items.length === 0) {
                $tbody.html('<tr><td colspan="9" style="text-align:center;padding:30px;color:#646970;">' + cfg.txt.no_results + '</td></tr>');
                return;
            }

            var currency = cfg.currency || '$';
            var html = '';
            items.forEach(function (m) {
                var typeBadge = '<span class="aura-maint-type-badge aura-maint-type-' + m.type + '">' +
                    escHtml(cfg.txt.type_labels[m.type] || m.type) + '</span>';

                var executor = m.performed_by === 'external'
                    ? '<span class="aura-maint-exec-badge external"><span class="dashicons dashicons-store"></span>' + escHtml(m.workshop_name || cfg.txt.performed_labels.external) + '</span>'
                    : '<span class="aura-maint-exec-badge"><span class="dashicons dashicons-businessman"></span>' + escHtml(m.tech_name || cfg.txt.performed_labels.internal) + '</span>';

                var costCell = m.total_cost > 0
                    ? '<span class="aura-maint-cost-cell">' + currency + parseFloat(m.total_cost).toLocaleString('es', {minimumFractionDigits:2}) + '</span>'
                    : '<span class="aura-maint-cost-cell zero">—</span>';

                var psBadge = '<span class="aura-maint-ps-badge aura-maint-ps-' + m.post_status + '">' +
                    escHtml(cfg.txt.post_status_labels[m.post_status] || m.post_status) + '</span>';

                var finBadge = m.has_finance
                    ? '<span class="aura-maint-finance-ok"><span class="dashicons dashicons-yes-alt"></span>' + cfg.txt.has_finance + '</span>'
                    : '<span style="color:#c3c4c7;">' + cfg.txt.no_finance + '</span>';

                var actions = '<div class="aura-inv-row-actions">';
                actions += '<button class="aura-inv-btn-icon aura-maint-btn-detail" data-id="' + m.id + '" title="Ver detalle"><span class="dashicons dashicons-visibility"></span></button>';
                if (m.can_edit) {
                    actions += '<button class="aura-inv-btn-icon aura-maint-btn-edit" data-id="' + m.id + '" title="Editar"><span class="dashicons dashicons-edit"></span></button>';
                }
                if (m.can_delete) {
                    actions += '<button class="aura-inv-btn-icon delete aura-maint-btn-delete" data-id="' + m.id + '" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>';
                }
                actions += '</div>';

                // Thumbnail del equipo
                var thumbCell = m.photo_thumb_url
                    ? '<img src="' + escHtml(m.photo_thumb_url) + '" alt="" width="44" height="33"' +
                      ' style="border-radius:3px;object-fit:cover;display:block;border:1px solid #dcdcde;">'
                    : '<span class="dashicons dashicons-format-image" style="color:#c3c4c7;font-size:24px;" title="Sin foto"></span>';

                html += '<tr id="aura-maint-row-' + m.id + '">' +
                    '<td style="width:54px;text-align:center;">' + thumbCell + '</td>' +
                    '<td>' + escHtml(m.maintenance_date) + '</td>' +
                    '<td><strong>' + escHtml(m.equipment_name) + '</strong>' +
                    ((m.equipment_brand || m.equipment_code)
                        ? '<br><small style="color:#646970;">' +
                          [m.equipment_brand ? escHtml(m.equipment_brand) : '', m.equipment_code ? 'Cód: ' + escHtml(m.equipment_code) : ''].filter(Boolean).join(' &middot; ') +
                          '</small>'
                        : '') +
                    '</td>' +
                    '<td>' + typeBadge  + '</td>' +
                    '<td>' + executor   + '</td>' +
                    '<td>' + costCell   + '</td>' +
                    '<td>' + psBadge    + '</td>' +
                    '<td>' + finBadge   + '</td>' +
                    '<td>' + actions    + '</td>' +
                    '</tr>';
            });

            $tbody.html(html);
        },

        updatePagination: function (d) {
            var cfg = this.cfg;
            var $pag = $('#aura-maint-pagination');
            $pag.show();
            $('#aura-maint-total-count').text(d.total + ' ' + cfg.txt.n_items.replace('%s', ''));
            $('#aura-maint-page-info').text(
                cfg.txt.page_of.replace('%1$s', d.page).replace('%2$s', d.total_pages)
            );
            $('#aura-maint-prev').prop('disabled', d.page <= 1);
            $('#aura-maint-next').prop('disabled', d.page >= d.total_pages);
        },

        updateSortHeaders: function () {
            var sortBy = this.sortBy;
            var sortDir = this.sortDir;
            $('#aura-maint-table th.sortable').each(function () {
                $(this).removeClass('sorted-asc sorted-desc');
                if ($(this).data('sort') === sortBy) {
                    $(this).addClass(sortDir === 'ASC' ? 'sorted-asc' : 'sorted-desc');
                }
            });
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
                self.loadEquipmentPhoto($(this).val());
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
