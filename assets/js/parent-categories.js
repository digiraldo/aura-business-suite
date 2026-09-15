/* global auraCategories, jQuery */
/**
 * Categorías Padre — Sin DataTables (HTML Directo para tabs ocultos)
 */
(function ($) {
    'use strict';

    var nonce           = auraCategories.nonce;
    var ajaxUrl         = auraCategories.ajaxUrl;
    var pendingDeleteId = null;
    var _parentsData    = [];    // array de datos de padres

    /* ── Helpers ── */
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    function typeLabel(type) {
        var map = { income: 'Ingreso', expense: 'Egreso', both: 'Ambos' };
        var colors = { income: '#27ae60', expense: '#e74c3c', both: '#3498db' };
        var t = map[type] || type;
        var c = colors[type] || '#888';
        return '<span class="aura-type-badge" style="background:' + c + ';color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;">' + t + '</span>';
    }

    function statusBadge(active) {
        return active
            ? '<span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;">Activa</span>'
            : '<span style="background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;">Inactiva</span>';
    }

    function showNotice(msg, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        var $n  = $('<div class="notice ' + cls + ' is-dismissible" style="margin:10px 0;"><p>' + msg + '</p></div>');
        $('.aura-parent-categories-page .wp-header-end').after($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
    }

    /* ── Cargar datos y (re)poblar Tabla ── */
    function loadParents() {
        var $tbody = $('#aura-parent-tree-tbody');
        var $noParents = $('#aura-no-parents-message');
        
        $tbody.html('<tr><td colspan="7" style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none;"></span><p>Cargando categorías padre...</p></td></tr>');
        $noParents.hide();
        $('#aura-parent-cat-table').show();

        $.ajax({
            url:  ajaxUrl,
            type: 'POST',
            data: { action: 'aura_get_parent_categories', nonce: nonce, search: '' },
            success: function (resp) {
                if (!resp.success) { showNotice(resp.data.message, 'error'); return; }
                _parentsData = resp.data.parents || [];
                renderTable(_parentsData);
            },
            error: function () { 
                showNotice('Error de comunicación con el servidor.', 'error'); 
                $tbody.html('<tr><td colspan="7" style="text-align: center; padding: 40px; color: #dc2626;">Error al cargar.</td></tr>');
            }
        });
    }

    function renderTable(parents) {
        var $tbody = $('#aura-parent-tree-tbody');
        var $noParents = $('#aura-no-parents-message');
        
        $tbody.empty();
        
        if (parents.length === 0) {
            $('#aura-parent-cat-table').hide();
            $noParents.show();
            return;
        }

        $('#aura-parent-cat-table').show();
        $noParents.hide();

        parents.forEach(function(p) {
            var subCount = parseInt(p.subcategory_count, 10) || 0;
            var hasChildren = subCount > 0;

            var toggleCell = hasChildren
                ? '<button class="aura-tree-toggle button button-small" data-parent-id="' + p.id + '" title="Ver subcategorías" style="background:none;border:1px solid #c3c4c7;padding:2px 6px;"><span class="dashicons dashicons-arrow-right-alt2" style="vertical-align:middle;font-size:14px;"></span></button>'
                : '<span style="color:#ccc;font-size:11px;">-</span>';

            var rawIcon     = (p.icon || '📁').trim();
            var iconCell    = (rawIcon.indexOf('dashicons-') === 0 || rawIcon.indexOf('dashicons') !== -1)
                ? '<span class="dashicons ' + escHtml(rawIcon) + '" style="color:' + escHtml(p.color || '#4f46e5') + ';font-size:20px;width:20px;height:20px;vertical-align:middle;" aria-hidden="true"></span>'
                : '<span class="aura-cat-emoji" style="font-size:20px;line-height:1;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;">' + escHtml(rawIcon) + '</span>';
            var nameCell    = '<div style="display:flex;align-items:center;gap:8px;">' + iconCell + '<div><strong>' + escHtml(p.name) + '</strong>' + (p.description ? '<br><small style="color:#8c8f94;">' + escHtml(p.description) + '</small>' : '') + '</div></div>';
            var typeCell    = typeLabel(p.type);
            var subcatCell  = hasChildren
                ? '<span style="background:#dbeafe;color:#1e40af;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;">' + subCount + ' subcat' + (subCount > 1 ? 's.' : '.') + '</span>'
                : '<span style="color:#ccc;font-size:12px;">0</span>';
                
            var actionsCell = '<div style="display:inline-flex;gap:4px;">' +
                '<button class="button button-small aura-edit-parent" data-id="' + p.id + '" title="Editar">' +
                    '<span class="dashicons dashicons-edit" style="vertical-align:middle;" aria-hidden="true"></span>' +
                '</button>' +
                '<button class="button button-small aura-delete-parent" data-id="' + p.id + '" data-name="' + escHtml(p.name) + '" data-subs="' + subCount + '" title="Eliminar" style="color:#dc3545;">' +
                    '<span class="dashicons dashicons-trash" style="vertical-align:middle;" aria-hidden="true"></span>' +
                '</button>' +
                '</div>';

            var html = '<tr class="aura-parent-row" data-name="' + escHtml(p.name).toLowerCase() + '" data-desc="' + escHtml(p.description).toLowerCase() + '">' +
                '<td style="width: 50px; text-align: center;">' + toggleCell + '</td>' +
                '<td>' + nameCell + '</td>' +
                '<td style="width: 100px;">' + typeCell + '</td>' +
                '<td style="width: 120px; text-align: center;" class="hide-on-mobile">' + subcatCell + '</td>' +
                '<td style="width: 80px; text-align: center;" class="hide-on-mobile">' + escHtml(p.display_order) + '</td>' +
                '<td style="width: 120px; text-align: center;">' + statusBadge(p.is_active) + '</td>' +
                '<td style="width: 120px;">' + actionsCell + '</td>' +
            '</tr>';
            
            $tbody.append(html);
        });
    }

    /* ── Inicialización al activar la pestaña ── */
    $(document).on('click', '.aura-tab-btn[data-target="parents"]', function () {
        // Cargar datos solo si no se han cargado antes, o forzar recarga
        if ($('#aura-parent-tree-tbody tr.aura-parent-row').length === 0) {
            loadParents();
        }
    });

    /* ── Búsqueda en vivo (Manual filter) ── */
    $(document).on('input', '#aura-parent-search', function () {
        var q = $(this).val().toLowerCase().trim();
        if (q === '') {
            $('.aura-parent-row').show();
            // Cerrar todos los hijos para mantener UI limpia
            $('.aura-tree-children-row').hide();
            $('.aura-tree-toggle').removeClass('is-open')
                .find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
            return;
        }

        $('.aura-parent-row').each(function() {
            var $row = $(this);
            var name = $row.data('name') || '';
            var desc = $row.data('desc') || '';
            if (name.indexOf(q) > -1 || desc.indexOf(q) > -1) {
                $row.show();
            } else {
                $row.hide();
                // Ocultar fila de hijos si está abierta
                $row.next('.aura-tree-children-row').hide();
            }
        });
    });

    $(document).on('click', '#aura-parent-clear-search', function () {
        $('#aura-parent-search').val('').trigger('input');
    });

    /* ── Abrir modal Crear ── */
    $(document).on('click', '#aura-add-parent-btn', function () {
        resetForm();
        $('#aura-parent-modal-title').text('Nueva Categoría Padre');
        openModal('#aura-parent-modal');
    });

    /* ── Abrir modal Editar ── */
    $(document).on('click', '.aura-edit-parent', function () {
        var id = $(this).data('id');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'aura_get_category_by_id', nonce: nonce, category_id: id },
            success: function (resp) {
                if (!resp.success) { showNotice(resp.data.message, 'error'); return; }
                var c = resp.data.category;
                $('#parent-cat-id').val(c.id);
                $('#parent-cat-name').val(c.name);
                $('#parent-cat-slug').val(c.slug);
                $('[name="type"][value="' + c.type + '"]').prop('checked', true);
                $('#parent-cat-color').val(c.color).trigger('change');
                $('#parent-cat-order').val(c.display_order);
                $('#parent-cat-icon').val(c.icon).trigger('input');
                $('#parent-cat-desc').val(c.description);
                $('#parent-cat-active').prop('checked', !!c.is_active);
                $('#aura-parent-modal-title').text('Editar Categoría Padre');
                openModal('#aura-parent-modal');
            }
        });
    });

    /* ── Guardar ── */
    $(document).on('click', '#aura-parent-save-btn', function () {
        var $btn = $(this).prop('disabled', true).text('Guardando...');
        var active = $('#parent-cat-active').is(':checked') ? 'true' : 'false';
        var colorVal = '';
        if ($.fn.wpColorPicker && $('#parent-cat-color').wpColorPicker('color')) {
            colorVal = $('#parent-cat-color').wpColorPicker('color');
        } else {
            colorVal = $('#parent-cat-color').val();
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_save_parent_category',
                nonce: nonce,
                category_id: $('#parent-cat-id').val(),
                name: $('#parent-cat-name').val(),
                slug: $('#parent-cat-slug').val(),
                type: $('[name="type"]:checked').val(),
                color: colorVal,
                display_order: $('#parent-cat-order').val(),
                icon: $('#parent-cat-icon').val(),
                description: $('#parent-cat-desc').val(),
                is_active: active,
            },
            success: function (resp) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar');
                if (!resp.success) { showNotice(resp.data.message, 'error'); return; }
                closeModal('#aura-parent-modal');
                showNotice(resp.data.message, 'success');
                loadParents();
            },
            error: function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar');
                showNotice('Error de comunicación.', 'error');
            }
        });
    });

    /* ── Eliminar: mostrar confirmación ── */
    $(document).on('click', '.aura-delete-parent', function () {
        pendingDeleteId = $(this).data('id');
        var name = $(this).data('name');
        var subs = parseInt($(this).data('subs'), 10);
        var msg;
        if (subs > 0) {
            msg = 'La categoría "' + name + '" tiene ' + subs + ' subcategoría(s). No se puede eliminar hasta reasignarlas.';
        } else {
            msg = '¿Eliminar la categoría padre "' + name + '"? Esta acción no se puede deshacer.';
        }
        $('#aura-parent-delete-msg').text(msg);
        $('#aura-parent-delete-confirm').prop('disabled', subs > 0);
        openModal('#aura-parent-delete-modal');
    });

    /* ── Confirmar eliminación ── */
    $(document).on('click', '#aura-parent-delete-confirm', function () {
        if (!pendingDeleteId) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'aura_delete_parent_category', nonce: nonce, category_id: pendingDeleteId },
            success: function (resp) {
                $btn.prop('disabled', false);
                closeModal('#aura-parent-delete-modal');
                if (!resp.success) { showNotice(resp.data.message, 'error'); return; }
                showNotice(resp.data.message, 'success');
                loadParents();
                pendingDeleteId = null;
            },
            error: function () {
                $btn.prop('disabled', false);
                showNotice('Error de comunicación.', 'error');
            }
        });
    });

    /* ── Preview icono ── */
    $(document).on('input change', '#parent-cat-icon', function () {
        var raw = ($(this).val() || '📁').trim();
        if (raw.indexOf('dashicons-') === 0 || raw.indexOf('dashicons') !== -1) {
            $('#parent-icon-preview').html('<span class="dashicons ' + escHtml(raw) + '" style="font-size:22px;"></span>');
        } else {
            $('#parent-icon-preview').html('<span style="font-size:22px;line-height:1;">' + escHtml(raw) + '</span>');
        }
    });

    /* ── Árbol: Toggle expandir/colapsar subcategorías ── */
    $(document).on('click', '.aura-tree-toggle', function () {
        var $btn      = $(this);
        var parentId  = $btn.data('parent-id');
        var $row      = $btn.closest('tr');
        var $next     = $row.next('.aura-tree-children-row[data-parent-id="' + parentId + '"]');
        var isOpen    = $btn.hasClass('is-open');

        if (isOpen) {
            // Colapsar
            $next.slideUp(200);
            $btn.removeClass('is-open')
                .find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
        } else {
            // Expandir: crear fila si no existe
            if (!$next.length) {
                var $childRow = $('<tr class="aura-tree-children-row" data-parent-id="' + parentId + '" style="display:none;"><td colspan="7" style="padding:0;"><div class="aura-tree-children-inner" style="padding:8px 12px 8px 48px;"><em style="color:#8c8f94;">Cargando...</em></div></td></tr>');
                $row.after($childRow);
                loadSubcategories(parentId, $childRow.find('.aura-tree-children-inner'));
            }
            $next = $row.next('.aura-tree-children-row[data-parent-id="' + parentId + '"]');
            $next.slideDown(200);
            $btn.addClass('is-open')
                .find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });

    function loadSubcategories(parentId, $container) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'aura_get_subcategories', nonce: nonce, parent_id: parentId },
            success: function (resp) {
                if (!resp.success || !resp.data.subcategories.length) {
                    $container.html('<em style="color:#8c8f94;font-size:12px;">Sin subcategorías.</em>');
                    return;
                }
                var html = '<table style="width:100%;border-collapse:collapse;">';
                resp.data.subcategories.forEach(function (sub, idx) {
                    var isLast = idx === resp.data.subcategories.length - 1;
                    var connector = isLast ? '└─' : '├─';
                    var typeColors = { income: '#16a34a', expense: '#dc2626', both: '#2563eb' };
                    var typeLbls   = { income: 'Ingreso', expense: 'Egreso', both: 'Ambos' };
                    var tColor = typeColors[sub.type] || '#888';
                    var tLabel = typeLbls[sub.type]  || sub.type;
                    var status = sub.is_active
                        ? '<span style="background:#d1fae5;color:#065f46;padding:1px 8px;border-radius:999px;font-size:11px;">Activa</span>'
                        : '<span style="background:#fee2e2;color:#991b1b;padding:1px 8px;border-radius:999px;font-size:11px;">Inactiva</span>';
                    var subIcon = (sub.icon || '📁').trim();
                    var subIconHtml = (subIcon.indexOf('dashicons-') === 0 || subIcon.indexOf('dashicons') !== -1)
                        ? '<span class="dashicons ' + escHtml(subIcon) + '" style="color:' + escHtml(sub.color || '#3b82f6') + ';font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>'
                        : '<span class="aura-cat-emoji" style="font-size:14px;line-height:1;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;">' + escHtml(subIcon) + '</span>';
                    html +=
                        '<tr style="border-bottom:' + (isLast ? 'none' : '1px solid #f0f0f1') + ';">' +
                        '<td style="padding:6px 4px;width:32px;color:#94a3b8;font-family:monospace;">' + connector + '</td>' +
                        '<td style="padding:6px 8px;">' +
                            subIconHtml + ' ' +
                            '<strong>' + escHtml(sub.name) + '</strong>' +
                        '</td>' +
                        '<td style="padding:6px 8px;width:90px;"><span style="background:' + tColor + ';color:#fff;padding:1px 8px;border-radius:999px;font-size:11px;">' + tLabel + '</span></td>' +
                        '<td style="padding:6px 8px;width:80px;">' + status + '</td>' +
                        '</tr>';
                });
                html += '</table>';
                $container.html(html);
            },
            error: function () {
                $container.html('<span style="color:#dc2626;font-size:12px;">Error al cargar.</span>');
            }
        });
    }

    /* ── Eliminar Todas las Categorías Padre ── */
    $(document).on('click', '#aura-delete-all-parents-btn', function () {
        $('#aura-delete-all-parents-check').prop('checked', false);
        $('#aura-delete-all-parents-confirm').prop('disabled', true).css({ opacity: 0.5, cursor: 'not-allowed' });
        $('#aura-delete-all-parents-modal').fadeIn(200);
    });

    $(document).on('change', '#aura-delete-all-parents-check', function () {
        var $btn = $('#aura-delete-all-parents-confirm');
        if ($(this).is(':checked')) {
            $btn.prop('disabled', false).css({ opacity: 1, cursor: 'pointer' });
        } else {
            $btn.prop('disabled', true).css({ opacity: 0.5, cursor: 'not-allowed' });
        }
    });

    $(document).on('click', '#aura-delete-all-parents-cancel', function () {
        $('#aura-delete-all-parents-modal').fadeOut(200);
    });

    $(document).on('click', '#aura-delete-all-parents-confirm', function () {
        var $btn = $(this).prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px -4px;"></span> Eliminando...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'aura_delete_all_categories', nonce: nonce, scope: 'parents_only' },
            success: function (resp) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> Sí, eliminar todas');
                $('#aura-delete-all-parents-modal').fadeOut(200);
                if (resp.success) {
                    showNotice(resp.data.message, 'success');
                    loadParents();
                } else {
                    showNotice(resp.data.message || 'Error al eliminar.', 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                showNotice('Error de comunicación.', 'error');
            }
        });
    });

    /* ── Modal helpers ── */
    function openModal(selector) { $(selector).addClass('active'); }
    function closeModal(selector) { $(selector).removeClass('active'); }

    $('#aura-parent-modal-cancel, #aura-parent-delete-cancel').on('click', function () {
        closeModal('.aura-modal-overlay');
    });
    $('.aura-modal-close, .aura-modal-overlay').on('click', function (e) {
        if (e.target === this) { closeModal($(this).closest('.aura-modal-overlay')); }
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { closeModal('.aura-modal-overlay'); }
    });

    function resetForm() {
        $('#parent-cat-id').val('');
        $('#parent-cat-name, #parent-cat-slug, #parent-cat-desc').val('');
        $('[name="type"][value="both"]').prop('checked', true);
        $('#parent-cat-color').val('#3498db').trigger('change');
        $('#parent-cat-order').val('0');
        $('#parent-cat-icon').val('dashicons-category').trigger('input');
        $('#parent-cat-active').prop('checked', true);
    }

    /* ── Color Picker ── */
    if ($.fn.wpColorPicker) {
        $('#parent-cat-color').wpColorPicker({
            change: function (event, ui) {
                $('#parent-icon-preview').css('color', ui.color.toString());
            }
        });
    }

})(jQuery);
