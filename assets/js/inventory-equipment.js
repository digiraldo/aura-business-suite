/**
 * Inventario — Equipment JS
 * Maneja: listado con filtros/paginación, modal de detalle,
 *         formulario AJAX, cambio de estado y confirmación de borrado.
 *
 * Depende de: jQuery, auraInventoryEquipment (inyectado en equipment-list.php)
 *             auraInvFormData               (inyectado en equipment-form.php)
 */

/* global jQuery, auraInventoryEquipment, auraInvFormData, wp */

(function ($) {
    'use strict';

    // ─────────────────────────────────────────────────────────────
    // MÓDULO: LISTADO DE EQUIPOS — DataTables
    // ─────────────────────────────────────────────────────────────

    var EquipmentList = {

        cfg:   null,
        table: null,   // instancia DataTables

        init: function () {
            if (typeof auraInventoryEquipment === 'undefined') return;
            this.cfg = auraInventoryEquipment;
            this.initDataTable();
            this.bindEvents();
        },

        // ── Helpers de render ────────────────────────────────────
        _thumb: function (eq) {
            return eq.photo_thumb_url
                ? '<div class="aura-inv-thumb-wrap" data-full="' + escHtml(eq.photo_full_url || eq.photo_thumb_url) + '">' +
                  '<img src="' + escHtml(eq.photo_thumb_url) + '" alt="" width="44" height="33"' +
                  ' style="border-radius:3px;object-fit:cover;display:block;border:1px solid #dcdcde;">' +
                  '</div>'
                : '<span class="dashicons dashicons-format-image" style="color:#c3c4c7;font-size:24px;" title="Sin foto"></span>';
        },

        _maintBadge: function (eq) {
            var cfg = this.cfg;
            if (eq.maintenance_status && eq.maintenance_status !== 'none') {
                var badge = '<span class="aura-inv-maint-badge aura-inv-maint-' + eq.maintenance_status + '">' +
                    (cfg.txt.maint_labels[eq.maintenance_status] || eq.maintenance_status) + '</span>';
                if (eq.next_maintenance_date) badge += '<br><small>' + eq.next_maintenance_date + '</small>';
                return badge;
            }
            if (eq.next_maintenance_date) {
                return '<span class="aura-inv-maint-badge aura-inv-maint-ok">' + cfg.txt.maint_labels.ok + '</span>' +
                       '<br><small>' + eq.next_maintenance_date + '</small>';
            }
            return '<span style="color:#c3c4c7;">—</span>';
        },

        _statusCell: function (eq) {
            var cfg = this.cfg;
            return eq.can_edit
                ? '<select class="aura-inv-status-select" data-id="' + eq.id + '">' +
                  renderStatusOptions(cfg.txt.status_labels, eq.status) + '</select>'
                : '<span class="aura-inv-status-badge aura-inv-status-' + eq.status + '">' + eq.status_label + '</span>';
        },

        _actions: function (eq) {
            var cfg = this.cfg;
            var h = '<div class="aura-inv-row-actions">';
            h += '<button class="aura-inv-btn-icon aura-inv-btn-detail" data-id="' + eq.id + '" title="Ver detalle">' +
                 '<span class="dashicons dashicons-visibility"></span></button>';
            if (eq.can_edit) {
                h += '<button class="aura-inv-btn-icon aura-inv-btn-edit" data-id="' + eq.id + '" title="Editar">' +
                     '<span class="dashicons dashicons-edit"></span></button>';
            }
            if (eq.can_delete) {
                h += '<button class="aura-inv-btn-icon delete aura-inv-btn-delete" data-id="' + eq.id +
                     '" data-name="' + escHtml(eq.name) + '" title="Eliminar">' +
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
            if ($.fn.DataTable.isDataTable('#aura-inv-equipment-table')) {
                this.table = $('#aura-inv-equipment-table').DataTable();
                return;
            }

            this.table = new DataTable('#aura-inv-equipment-table', {
                responsive: true,
                processing: true,
                serverSide: false,           // DataTables maneja paginación client-side
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                language: {
                    processing:  cfg.txt.loading || 'Cargando…',
                    zeroRecords: cfg.txt.no_results || 'No se encontraron equipos.',
                    info:        '_TOTAL_ equipos',
                    infoEmpty:   '0 equipos',
                    infoFiltered: '(filtrado de _MAX_ total)',
                    search:      '',
                    searchPlaceholder: cfg.txt.loading ? '' : 'Buscar…',
                    lengthMenu:  'Mostrar _MENU_ por página',
                    paginate: { first:'«', last:'»', next:'›', previous:'‹' }
                },
                // Ocultar búsqueda y longitud propias de DataTables (usamos los filtros del plugin)
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
                            per_page:           9999,   // traer todo; paginación client-side
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

                columns: [
                    {
                        title: 'Foto',
                        data: null,
                        orderable: false,
                        className: 'aura-inv-col-photo',
                        width: '58px',
                        render: function (data, type, eq) {
                            if (type === 'display') return self._thumb(eq);
                            return eq.name || '';
                        },
                    },
                    {
                        title: 'Equipo',
                        data: 'name',
                        render: function (data, type, eq) {
                            if (type !== 'display') return data || '';
                            var h = '<strong>' + escHtml(eq.name) + '</strong>';
                            var sub = [
                                eq.brand ? escHtml(eq.brand) : '',
                                eq.internal_code ? 'Cód: ' + escHtml(eq.internal_code) : ''
                            ].filter(Boolean).join(' · ');
                            if (sub) h += '<br><small style="color:#646970;">' + sub + '</small>';
                            return h;
                        },
                    },
                    {
                        title: 'Mantenimiento',
                        data: 'next_maintenance_date',
                        render: function (data, type, eq) {
                            if (type !== 'display') return data || '';
                            return self._maintBadge(eq);
                        },
                    },
                    {
                        title: 'Estado',
                        data: 'status',
                        render: function (data, type, eq) {
                            if (type !== 'display') return eq.status_label || data;
                            return self._statusCell(eq);
                        },
                    },
                    {
                        title: 'Categoría',
                        data: 'category',
                        render: function (data) { return escHtml(data || '—'); },
                    },
                    {
                        title: 'Ubicación',
                        data: 'location',
                        render: function (data) { return escHtml(data || '—'); },
                    },
                    {
                        title: 'Responsable',
                        data: 'responsible_name',
                        render: function (data) { return escHtml(data || '—'); },
                    },
                    {
                        title: 'Acciones',
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (data, type, eq) {
                            if (type !== 'display') return '';
                            return self._actions(eq);
                        },
                    },
                ],
            });
        },

        // ── Filtros externos → recargar tabla ────────────────────
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
                if (e.key === 'Enter') self.table.ajax.reload();
            });

            // Acciones delegadas en la tabla
            $(document).on('click', '.aura-inv-btn-detail', function () {
                self.showDetail($(this).data('id'));
            });
            $(document).on('click', '.aura-inv-btn-edit', function () {
                window.location.href = self.cfg.newEquipUrl + '&id=' + $(this).data('id');
            });
            $(document).on('click', '.aura-inv-btn-delete', function () {
                self.deleteEquipment($(this).data('id'), $(this).data('name'));
            });
            $(document).on('change', '.aura-inv-status-select', function () {
                self.updateStatus($(this).data('id'), $(this).val());
            });

            // El evento para el detalle de mantenimiento ahora se maneja mediante un onclick inline
            // que llama a window.auraToggleMaint(btn, rowId)


            // Cerrar modales
            $(document).on('click', '.aura-inv-modal-close, .aura-inv-modal-overlay', function () {
                $('.aura-inv-modal').hide();
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') $('.aura-inv-modal').hide();
            });
        },

        showDetail: function (id) {

            var cfg = this.cfg;
            var $modal = $('#aura-inv-detail-modal');
            var $body  = $('#aura-inv-detail-body');

            $body.html('<span class="spinner is-active" style="display:block;margin:30px auto;float:none;"></span>');
            $modal.show();

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_equipment_get_detail', nonce: cfg.nonce, id: id },
                success: function (res) {
                    if (!res.success) {
                        $body.html('<p>' + (res.data.message || cfg.txt.error) + '</p>');
                        return;
                    }
                    var eq     = res.data.equipment;
                    var mhist  = res.data.maintenance_history;
                    var lhist  = res.data.loan_history;
                    var comps  = res.data.components || [];

                    $('#aura-inv-detail-title').text(eq.name + (eq.brand ? ' · ' + eq.brand : ''));

                    var html = '';
                    // Prioridad: URL resolución completa; fallback a valor raw
                    var photoUrl = eq.photo_full_url || eq.photo;
                    if (photoUrl) {
                        html += '<div style="text-align:center;margin-bottom:16px;">'
                             + '<img src="' + escHtml(photoUrl) + '" alt="' + escHtml(eq.name) + '"'
                             + ' style="max-width:100%;max-height:260px;object-fit:contain;border-radius:6px;border:1px solid #dcdcde;">'
                             + '</div>';
                    }
                    // Descripción general (ancho completo)
                    if (eq.description) {
                        html += '<div style="margin-bottom:16px;font-size:14px;color:#50575e;background:#f6f7f7;padding:12px;border-radius:6px;border-left:4px solid #ccd0d4;">';
                        html += '<strong>Descripción:</strong><br>' + escHtml(eq.description).replace(/\n/g, '<br>');
                        html += '</div>';
                    }

                    // Función auxiliar para secciones
                    var startSection = function(title) {
                        return '<div class="aura-inv-detail-section-title" style="margin-top:20px;border-bottom:1px solid #dcdcde;padding-bottom:4px;margin-bottom:12px;">' + title + '</div>' + '<div class="aura-inv-detail-grid">';
                    };

                    // SECCIÓN I: INFORMACIÓN GENERAL
                    html += startSection('Información General');
                    html += detailRow('Marca', eq.brand || '—');
                    html += detailRow('Modelo', eq.model || '—');
                    html += detailRow('Código interno', eq.internal_code || '—');
                    html += detailRow('Número de serie', eq.serial_number || '—');
                    html += detailRow('Categoría', eq.category || '—');
                    html += detailRow('Estado', '<span class="aura-inv-status-badge aura-inv-status-' + eq.status + '">' + (cfg.txt.status_labels[eq.status] || eq.status) + '</span>');
                    html += detailRow('Ubicación', eq.location || '—');
                    html += detailRow('Responsable', eq.responsible_name || '—');
                    if (eq.parent_equipment_id && eq.parent_equipment_name) {
                        html += detailRow('Componente de', '<span style="font-weight:600;color:#2271b1;">↑ ' + escHtml(eq.parent_equipment_name) + '</span>');
                    }
                    html += '</div>';

                    // SECCIÓN II: ADQUISICIÓN Y FECHAS
                    html += startSection('Adquisición');
                    html += detailRow('Fecha adquisición', eq.acquisition_date || '—');
                    html += detailRow('Proveedor', eq.supplier || '—');
                    html += detailRow('Vencimiento garantía', eq.warranty_date || '—');
                    html += detailRow('Costo', eq.cost > 0 ? '$' + parseFloat(eq.cost).toLocaleString() : '—');
                    html += detailRow('Valor estimado', eq.estimated_value > 0 ? '$' + parseFloat(eq.estimated_value).toLocaleString() : '—');
                    html += '</div>';

                    // SECCIÓN III: ESPECIFICACIONES TÉCNICAS (Sólo si existen)
                    if (eq.oil_type || eq.oil_capacity || eq.fuel_type || eq.voltage || eq.hydraulic_pressure) {
                        html += startSection('Especificaciones Técnicas');
                        if (eq.oil_type || eq.oil_capacity) html += detailRow('Aceite', (eq.oil_capacity ? eq.oil_capacity + 'L ' : '') + (eq.oil_type || '—'));
                        if (eq.fuel_type) html += detailRow('Combustible', eq.fuel_type);
                        if (eq.voltage) html += detailRow('Voltaje', eq.voltage + 'V');
                        if (eq.hydraulic_pressure) html += detailRow('Presión', eq.hydraulic_pressure);
                        html += '</div>';
                    }

                    // SECCIÓN IV: MANTENIMIENTO
                    html += startSection('Mantenimiento');
                    var intervalStr = '—';
                    if (eq.requires_maintenance == 1) {
                        var p = [];
                        if (eq.interval_months && (eq.interval_type === 'time' || eq.interval_type === 'both')) p.push('Cada ' + eq.interval_months + ' meses');
                        if (eq.interval_hours && (eq.interval_type === 'hours' || eq.interval_type === 'both')) p.push('Cada ' + eq.interval_hours + ' horas');
                        intervalStr = p.join(' o ') || 'Definido';
                    } else {
                        intervalStr = 'No requiere';
                    }
                    html += detailRow('Frecuencia requerida', intervalStr);
                    html += detailRow('Último mantenimiento', eq.last_maintenance_date || '—');
                    html += detailRow('Próximo mantenimiento', eq.next_maintenance_date ? '<strong style="color:#d63638;">' + eq.next_maintenance_date + '</strong>' : '—');
                    html += detailRow('Total invertido', eq.total_maintenance_cost > 0 ? '$' + parseFloat(eq.total_maintenance_cost).toLocaleString() : '—');
                    html += '</div>';

                    // Instrucciones de mantenimiento
                    if (eq.maintenance_instructions) {
                        html += '<div style="margin-top:16px;background:#e5f5fa;border:1px solid #72aee6;border-radius:4px;padding:12px;">';
                        html += '<strong style="color:#005a9e;display:block;margin-bottom:6px;"><span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-right:4px;"></span> Instrucciones de mantenimiento:</strong>';
                        html += '<div style="font-size:13px;color:#1d2327;">' + escHtml(eq.maintenance_instructions).replace(/\n/g, '<br>') + '</div>';
                        html += '</div>';
                    }

                    // Accesorios de dotación
                    if (eq.accessories) {
                        var accItems = eq.accessories.split('\n').filter(function (l) { return l.trim() !== ''; });
                        if (accItems.length > 0) {
                            html += '<div class="aura-inv-detail-section-title">Accesorios incluidos</div>';
                            html += '<ul style="margin:6px 0 12px 18px;padding:0;">';
                            accItems.forEach(function (item) {
                                html += '<li style="list-style:disc;margin-bottom:3px;">' + escHtml(item.trim()) + '</li>';
                            });
                            html += '</ul>';
                        }
                    }

                    // Componentes / accesorios con vida útil propia
                    if (comps.length > 0) {
                        html += '<div class="aura-inv-detail-section-title">Componentes vinculados (' + comps.length + ')</div>';
                        html += '<table class="wp-list-table widefat striped" style="margin-top:8px;">';
                        html += '<thead><tr><th>Nombre</th><th>Categoría</th><th>Cód. interno</th><th>Estado</th></tr></thead><tbody>';
                        comps.forEach(function (c) {
                            html += '<tr>' +
                                '<td><strong>' + escHtml(c.name) + '</strong>' + (c.brand ? ' <small>' + escHtml(c.brand) + '</small>' : '') + '</td>' +
                                '<td>' + escHtml(c.category || '—') + '</td>' +
                                '<td>' + escHtml(c.internal_code || '—') + '</td>' +
                                '<td><span class="aura-inv-status-badge aura-inv-status-' + escHtml(c.status) + '">' + escHtml(c.status_label || c.status) + '</span></td>' +
                                '</tr>';
                        });
                        html += '</tbody></table>';
                    }

                    // Últimos mantenimientos
                    if (mhist && mhist.length > 0) {
                        html += '<div class="aura-inv-detail-section-title">Últimos mantenimientos (' + mhist.length + ')</div>';
                        html += '<table class="wp-list-table widefat striped" style="margin-top:8px;">';
                        html += '<thead><tr><th>Fecha</th><th>Tipo</th><th>Total</th><th>Ejecutor</th><th style="width:50px;text-align:center;">Detalle</th></tr></thead><tbody>';
                        mhist.forEach(function (m, index) {
                            var descHTML = m.description ? escHtml(m.description).replace(/\n/g, '<br>') : '—';
                            var partsHTML = m.replaced_parts ? escHtml(m.replaced_parts).replace(/\n/g, '<br>') : '';
                            var rowId = 'maint-desc-' + m.id + '-' + index;
                            
                            html += '<tr><td>' + escHtml(m.maintenance_date) + '</td><td>' + escHtml(m.type_label) +
                                '</td><td>$' + parseFloat(m.total_cost).toLocaleString() +
                                '</td><td>' + escHtml(m.performed_by === 'external'
                                    ? (m.workshop_name || 'Externo')
                                    : (m.technician_name || 'Interno')) + '</td>' +
                                '<td style="text-align:center;">' +
                                '<button type="button" class="button button-small" onclick="auraToggleMaint(this, \'' + rowId + '\')" title="Ver descripción del trabajo">' +
                                '<span class="dashicons dashicons-text-page" style="margin-top:4px;"></span></button>' +
                                '</td></tr>';

                            html += '<tr id="' + rowId + '" class="aura-inv-maint-desc-row" style="display:none;">' +
                                '<td colspan="5" style="padding:12px 16px;background:#f0f6fc;border-left:4px solid #2271b1;font-size:13px;border-bottom:1px solid #dcdcde;">' +
                                '<strong style="color:#1d2327;">Descripción del trabajo:</strong><br>' +
                                '<div style="color:#50575e;margin-top:4px;margin-bottom:' + (partsHTML ? '12px' : '0') + ';">' + descHTML + '</div>' +
                                (partsHTML ? '<strong style="color:#1d2327;">Repuestos/Insumos usados:</strong><br><div style="color:#50575e;margin-top:4px;">' + partsHTML + '</div>' : '') +
                                '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }

                    // Últimos préstamos
                    if (lhist && lhist.length > 0) {
                        html += '<div class="aura-inv-detail-section-title">Últimos préstamos</div>';
                        html += '<table class="wp-list-table widefat striped" style="margin-top:8px;">';
                        html += '<thead><tr><th>Fecha salida</th><th>Tomado por</th><th>Devolución esperada</th><th>Devuelto</th></tr></thead><tbody>';
                        lhist.forEach(function (l) {
                            var name = l.borrower_display || l.borrower_name || '—';
                            var avatarHtml;
                            if (l.borrower_avatar) {
                                avatarHtml = '<img src="' + escHtml(l.borrower_avatar) + '" width="28" height="28"' +
                                    ' style="border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:7px;" alt="">';
                            } else {
                                var initials = name.split(' ').filter(Boolean).slice(0, 2)
                                    .map(function (w) { return (w[0] || '').toUpperCase(); }).join('');
                                avatarHtml = '<span style="display:inline-flex;align-items:center;justify-content:center;' +
                                    'width:28px;height:28px;border-radius:50%;background:#2271b1;color:#fff;' +
                                    'font-size:11px;font-weight:700;vertical-align:middle;margin-right:7px;">' +
                                    escHtml(initials || '?') + '</span>';
                            }
                            html += '<tr>' +
                                '<td>' + escHtml(l.loan_date) + '</td>' +
                                '<td style="white-space:nowrap;">' + avatarHtml + escHtml(name) + '</td>' +
                                '<td>' + escHtml(l.expected_return_date) + '</td>' +
                                '<td>' + (l.actual_return_date
                                    ? escHtml(l.actual_return_date)
                                    : '<span style="color:#d63638;font-weight:600;">Pendiente</span>') + '</td>' +
                                '</tr>';
                        });
                        html += '</tbody></table>';
                    }

                    $body.html(html);
                },
                error: function () {
                    $body.html('<p>' + cfg.txt.error + '</p>');
                },
            });
        },

        deleteEquipment: function (id, name) {
            var cfg  = this.cfg;
            var self = this;
            if (!window.confirm(cfg.txt.confirm_delete.replace('{name}', name))) return;

            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_equipment_delete', nonce: cfg.nonce, id: id },
                success: function (res) {
                    if (res.success) {
                        $('#aura-inv-row-' + id).fadeOut(300, function () { $(this).remove(); });
                        self.showNotice(cfg.txt.deleted, 'success');
                    } else {
                        self.showNotice(res.data.message || cfg.txt.error, 'error');
                    }
                },
                error: function () { self.showNotice(cfg.txt.error, 'error'); },
            });
        },

        updateStatus: function (id, newStatus) {
            var cfg = this.cfg;
            $.ajax({
                url:    cfg.ajaxurl,
                method: 'POST',
                data:   { action: 'aura_inventory_equipment_update_status', nonce: cfg.nonce, id: id, status: newStatus },
                success: function (res) {
                    if (!res.success) alert(res.data.message || cfg.txt.error);
                },
            });
        },

        showNotice: function (msg, type) {
            var cls  = type === 'error' ? 'notice-error' : 'notice-success';
            var $n   = $('<div class="notice ' + cls + ' is-dismissible" style="margin:12px 0;"><p>' + msg + '</p></div>');
            $('.wp-header-end').after($n);
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

        init: function () {
            if (typeof auraInvFormData === 'undefined') return;
            this.cfg = auraInvFormData;
            this.bindEvents();
            this.bindCropModal();
        },

        bindEvents: function () {
            var self = this;
            var cfg  = this.cfg;

            // Toggle campos de mantenimiento
            $('#inv_requires_maintenance').on('change', function () {
                $('#aura-inv-maintenance-fields').toggle(this.checked);
            });

            // Toggle filas meses/horas según tipo de intervalo
            $('input[name="interval_type"]').on('change', function () {
                self.toggleIntervalRows($(this).val());
            }).filter(':checked').trigger('change');

            // Hint de categoría
            $('#inv_category').on('change', function () {
                var slug = $(this).val();
                var hint = (cfg.categoryHints && cfg.categoryHints[slug]) ? cfg.categoryHints[slug].hint : '';
                $('#inv_category_hint').text(hint);

                // Autocompletar intervalo si hay datos
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

            // Media uploader — abre WP media y luego muestra modal de recorte
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

            $('#aura-inv-remove-photo').on('click', function () {
                $('#inv_photo').val('');
                $('#aura-inv-photo-preview').html('<span class="dashicons dashicons-format-image" style="font-size:60px;color:#c3c4c7;display:block;text-align:center;padding:20px 0;"></span>');
            });

            // Submit AJAX
            $('#aura-inv-equipment-form').on('submit', function (e) {
                e.preventDefault();
                self.submit();
            });
        },

        // ── Crop modal ─────────────────────────────────────────
        openCropModal: function (attachmentId, imageUrl) {
            var self = this;
            self.pendingAttachmentId  = attachmentId;
            self.pendingAttachmentUrl = imageUrl;

            var $modal = $('#aura-crop-modal');
            var $img   = $('#aura-crop-img');

            // Destruir instancia anterior si existe
            if (self.cropperInstance) {
                self.cropperInstance.destroy();
                self.cropperInstance = null;
            }

            $img.attr('src', imageUrl);
            $modal.css('display', 'flex');

            // Esperar a que la imagen cargue antes de inicializar Cropper
            $img.off('load.cropper').on('load.cropper', function () {
                self.cropperInstance = new Cropper($img[0], {
                    aspectRatio:   4 / 3,
                    viewMode:      1,
                    dragMode:      'move',
                    autoCropArea:  0.9,
                    responsive:    true,
                    checkCrossOrigin: false,
                });
            });
            // Si la imagen ya estaba cacheada, el evento load no dispara
            if ($img[0].complete && $img[0].naturalWidth) {
                $img.trigger('load.cropper');
            }
        },

        bindCropModal: function () {
            var self = this;
            var cfg  = this.cfg;

            // Aplicar recorte
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
                                '<img src="' + res.data.full_url + '" style="max-width:100%;border-radius:4px;">'
                            );
                            self.closeCropModal();
                        } else {
                            alert(res.data ? res.data.message : 'Error al procesar imagen.');
                        }
                    },
                    error: function () {
                        alert('Error de conexión al procesar la imagen.');
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                        $btn.find('.dashicons').attr('class', 'dashicons dashicons-yes');
                    },
                });
            });

            // Cancelar / cerrar
            $('#aura-crop-cancel, #aura-crop-close').on('click', function () {
                self.closeCropModal();
            });
            // Cerrar con Escape
            $(document).on('keydown.aura-crop', function (e) {
                if (e.key === 'Escape') self.closeCropModal();
            });
        },

        closeCropModal: function () {
            if (this.cropperInstance) {
                this.cropperInstance.destroy();
                this.cropperInstance = null;
            }
            $('#aura-crop-modal').hide();
            this.pendingAttachmentId  = null;
            this.pendingAttachmentUrl = null;
        },

        toggleIntervalRows: function (type) {
            $('#inv_row_months').toggle(type === 'time' || type === 'both');
            $('#inv_row_hours').toggle(type === 'hours' || type === 'both');
        },

        submit: function () {
            var self = this;
            var cfg  = this.cfg;
            var $btn = $('#aura-inv-save-btn');
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
                    if (res.success) {
                        self.showFormNotice(cfg.txt.saved, 'success');
                        $btn.text(cfg.isEdit ? 'Actualizar equipo' : 'Registrar equipo');
                        // Si es alta nueva, redirigir al listado tras 1.5s
                        if (!cfg.isEdit) {
                            setTimeout(function () { window.location.href = cfg.listUrl; }, 1500);
                        }
                    } else {
                        self.showFormNotice(res.data.message || cfg.txt.error, 'error');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).text(cfg.isEdit ? 'Actualizar equipo' : 'Registrar equipo');
                    self.showFormNotice(cfg.txt.error, 'error');
                },
            });
        },

        showFormNotice: function (msg, type) {
            var cls = type === 'error' ? '#d63638' : '#00a32a';
            $('#aura-inv-form-notice').html(
                '<span style="color:' + cls + ';font-weight:600;">' + msg + '</span>'
            );
        },
    };

    // ─────────────────────────────────────────────────────────────
    // CONTROLADOR GLOBAL PARA ACORDEÓN DE MANTENIMIENTOS
    // ─────────────────────────────────────────────────────────────
    window.auraToggleMaint = function(btn, rowId) {
        var $btn = $(btn);
        var $row = $('#' + rowId);
        var $icon = $btn.find('.dashicons');

        // Cerrar otras filas (opcional)
        $('.aura-inv-maint-desc-row').not($row).hide();
        $('.dashicons-arrow-up-alt2').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-text-page');

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
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function detailRow(label, value) {
        return '<div class="aura-inv-detail-row">' +
            '<span class="aura-inv-detail-label">' + escHtml(label) + '</span>' +
            '<span class="aura-inv-detail-value">' + value + '</span>' +
            '</div>';
    }

    function renderStatusOptions(labels, current) {
        var html = '';
        Object.keys(labels).forEach(function (key) {
            html += '<option value="' + key + '"' + (key === current ? ' selected' : '') + '>' + labels[key] + '</option>';
        });
        return html;
    }

    // ─────────────────────────────────────────────────────────────
    // TOOLTIP DE IMAGEN: div flotante que sigue al cursor
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
            requestAnimationFrame(function () {
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

    // ─────────────────────────────────────────────────────────────
    // ARRANQUE
    // ─────────────────────────────────────────────────────────────
    $(function () {
        EquipmentList.init();
        EquipmentForm.init();
    });

}(jQuery));
