(function ($) {
    'use strict';

    var cfg = window.auraAreasAdmin || {};
    var strings = cfg.strings || {};
    var areasTable = null;
    var typesTable = null;
    var cropper = null;
    var cropAttachmentId = 0;
    var mediaFrame = null;
    var currentAreasList = [];

    function escHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function showToast(message, type) {
        if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
            window.AuraUI.showToast(message, type || 'success');
            return;
        }

        window.alert(message);
    }

    function showNotice(selector, message, isSuccess) {
        var $notice = $(selector);
        if (!$notice.length) {
            return;
        }

        $notice.removeClass('aura-is-hidden success danger')
            .addClass(isSuccess ? 'success' : 'danger')
            .find('p').text(message);

        setTimeout(function () {
            $notice.addClass('aura-is-hidden');
        }, 4500);
    }

    function api(action, nonce, data) {
        return $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: nonce }, data || {}));
    }

    function formatCurrency(value) {
        if (!value && value !== 0) {
            return '&mdash;';
        }

        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(Number(value || 0));
    }

    function switchTab(tabKey) {
        if (!tabKey || tabKey === 'undefined') {
            return;
        }

        $('.aura-tab-btn').removeClass('active');
        $('.aura-tab-panel').removeClass('active');

        $('.aura-tab-btn[data-tab-key="' + tabKey + '"]').addClass('active');
        $('#tab-' + tabKey).addClass('active');

        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            // Solo establecer tab si estamos en la vista de gestión general
            if (!url.searchParams.get('view')) {
                url.searchParams.set('tab', tabKey);
                window.history.replaceState({}, '', url.toString());
            }
        }

        setTimeout(function () {
            if ($.fn.dataTable) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
            }
        }, 60);
    }

    function openModal(modalId) {
        $('#' + modalId).addClass('active').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function closeModal(modalId) {
        $('#' + modalId).removeClass('active').attr('aria-hidden', 'true');
        if (!$('.aura-modal-overlay.active').length) {
            $('body').css('overflow', '');
        }
    }

    function renderAreaLogo(area) {
        var logoHtml = '';
        if (area.logo_thumb_url || area.logo_url) {
            var fullUrl = area.logo_url || area.logo_thumb_url;
            var thumbUrl = area.logo_thumb_url || area.logo_url;
            logoHtml = '<div class="aura-tooltip-wrap aura-table-thumb-wrap">'
                + '<div class="aura-table-thumb-preview aura-img-preview aura-img-preview-trigger" data-img-url="' + escHtml(fullUrl) + '" data-img-title="' + escHtml(area.name) + '" data-preview-img="' + escHtml(fullUrl) + '" data-preview-title="' + escHtml(area.name) + '">'
                + '<img src="' + escHtml(thumbUrl) + '" alt="' + escHtml(area.name) + '">'
                + '</div></div>';
        } else {
            logoHtml = '<span class="aura-area-logo-placeholder"><span class="dashicons dashicons-format-image"></span></span>';
        }

        return '<div class="aura-logo-toggle-cell">'
            + '<button type="button" class="aura-row-toggle js-row-toggle" aria-expanded="false" data-id="' + area.id + '" data-tooltip="Desplegar información completa del área">'
            + '<span class="dashicons dashicons-arrow-right-alt2"></span>'
            + '</button>'
            + logoHtml
            + '</div>';
    }

    function renderAreaName(area) {
        var parent = area.parent_name
            ? '<div class="aura-area-parent" data-tooltip="Depende de: ' + escHtml(area.parent_name) + '"><span class="dashicons dashicons-networking"></span> ' + escHtml(area.parent_name) + '</div>'
            : '';
        var descText = (area.description || '').trim();
        var shortDesc = descText.length > 38 ? descText.substring(0, 38) + '…' : descText;
        var description = descText
            ? '<div class="aura-area-description" data-tooltip="' + escHtml(descText) + '">' + escHtml(shortDesc) + '</div>'
            : '';

        return '<div class="aura-area-name-cell">'
            + '<span class="aura-color-badge" style="background:' + escHtml(area.color || '#2271b1') + ';" data-tooltip="Color identificador: ' + escHtml(area.color || '#2271b1') + '"></span>'
            + '<div class="aura-area-name-info"><div class="aura-area-name" data-tooltip="' + escHtml(area.name) + '">' + escHtml(area.name) + '</div>' + parent + description + '</div>'
            + '</div>';
    }

    function renderUserTipCard(user, areaName) {
        return '<div class="aura-tip-card">'
            + '<div class="aura-tip-card-header">'
            +   '<div class="aura-tip-avatar-large">'
            +     '<img src="' + escHtml(user.avatar_url) + '" alt="' + escHtml(user.display_name) + '">'
            +   '</div>'
            +   '<div class="aura-tip-info">'
            +     '<div class="aura-tip-title">' + escHtml(user.display_name) + '</div>'
            +     '<div class="aura-tip-subtitle">' + escHtml(user.email || 'Personal asignado') + '</div>'
            +     '<div class="aura-tip-badges">'
            +       '<span class="aura-tip-badge aura-tip-badge--role">👤 Miembro de área</span>'
            +     '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="aura-tip-card-body">'
            +   '<div class="aura-tip-row"><span>🏢 Adscrito a:</span> <strong>' + escHtml(areaName) + '</strong></div>'
            +   (user.email ? '<div class="aura-tip-row"><span>✉️ Correo electrónico:</span> <strong>' + escHtml(user.email) + '</strong></div>' : '')
            + '</div>'
            + '</div>';
    }

    function renderMoreUsersTipCard(users, areaName) {
        var extraCount = users.length - 3;
        var html = '<div class="aura-tip-card">'
            + '<div class="aura-tip-card-header">'
            +   '<div class="aura-tip-info">'
            +     '<div class="aura-tip-title">Otros ' + extraCount + ' miembros asignados</div>'
            +     '<div class="aura-tip-subtitle">' + escHtml(areaName) + '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="aura-tip-card-body" style="max-height: 180px; overflow-y: auto;">';
        users.slice(3).forEach(function (u) {
            html += '<div class="aura-tip-row" style="margin-bottom: 6px; align-items: center;">'
                + '<div style="display:flex; align-items:center; gap:6px;">'
                + '<img src="' + escHtml(u.avatar_url) + '" style="width:22px; height:22px; border-radius:50%; object-fit:cover;">'
                + '<strong style="font-size:12px;">' + escHtml(u.display_name) + '</strong>'
                + '</div>'
                + '<span style="font-size:11px; color:#94a3b8;">' + escHtml(u.email || '') + '</span>'
                + '</div>';
        });
        html += '</div></div>';
        return html;
    }

    function renderAssignedUsers(area) {
        if (!area.assigned_users || !area.assigned_users.length) {
            return '<em style="color:#94a3b8; font-size:12px;">' + escHtml(strings.none || 'Sin asignar') + '</em>';
        }

        var html = '<div class="aura-users-stack">';
        area.assigned_users.slice(0, 3).forEach(function (user) {
            var tipHtml = renderUserTipCard(user, area.name);
            html += '<span class="aura-user-av aura-has-tooltip" data-aura-tooltip="' + encodeURIComponent(tipHtml) + '">'
                + '<img src="' + escHtml(user.avatar_url) + '" alt="' + escHtml(user.display_name) + '"></span>';
        });

        if (area.assigned_users.length > 3) {
            var moreTipHtml = renderMoreUsersTipCard(area.assigned_users, area.name);
            html += '<span class="aura-users-more aura-has-tooltip" data-aura-tooltip="' + encodeURIComponent(moreTipHtml) + '">+' + (area.assigned_users.length - 3) + '</span>';
        }

        html += '</div>';
        return html;
    }

    function renderTypeTipCard(type) {
        var defaultBadge = type.is_default 
            ? '<span class="aura-tip-badge" style="background:rgba(16,185,129,0.18);color:#10b981;border:1px solid rgba(16,185,129,0.35);">⭐ Predeterminado</span>'
            : '<span class="aura-tip-badge" style="background:rgba(148,163,184,0.18);color:#94a3b8;border:1px solid rgba(148,163,184,0.35);">Clasificación regular</span>';

        return '<div class="aura-tip-card">'
            + '<div class="aura-tip-card-header">'
            +   '<div class="aura-tip-avatar-large" style="width:42px;height:42px;border-radius:10px;background:' + escHtml(type.color || '#2563eb') + ';display:flex;align-items:center;justify-content:center;color:#ffffff;box-shadow:0 2px 8px rgba(0,0,0,0.3);flex-shrink:0;">'
            +     '<span class="dashicons dashicons-tag" style="font-size:20px;width:20px;height:20px;"></span>'
            +   '</div>'
            +   '<div class="aura-tip-info">'
            +     '<div class="aura-tip-title">' + escHtml(type.name) + '</div>'
            +     '<div class="aura-tip-subtitle"><code>' + escHtml(type.slug) + '</code></div>'
            +     '<div class="aura-tip-badges">' + defaultBadge + '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="aura-tip-card-body">'
            +   '<div class="aura-tip-row"><span>🎨 Código de color:</span> <strong><code style="background:transparent;color:#38bdf8;">' + escHtml(type.color || '#2563eb') + '</code></strong></div>'
            +   '<div class="aura-tip-row"><span>🏢 Áreas asociadas:</span> <strong>' + parseInt(type.areas_count || 0, 10) + ' área(s)</strong></div>'
            +   (type.description ? '<div class="aura-tip-row" style="flex-direction:column;align-items:flex-start;gap:3px;"><span style="color:#94a3b8;">📝 Propósito del tipo:</span><p style="margin:2px 0 0;font-size:11px;color:#cbd5e1;line-height:1.4;">' + escHtml(type.description) + '</p></div>' : '')
            + '</div>'
            + '</div>';
    }

    function renderTypeAreasTipCard(type) {
        var count = parseInt(type.areas_count || 0, 10);
        return '<div class="aura-tip-card">'
            + '<div class="aura-tip-card-header">'
            +   '<div class="aura-tip-info">'
            +     '<div class="aura-tip-title">🏢 ' + count + ' ' + (count === 1 ? 'Área vinculada' : 'Áreas vinculadas') + '</div>'
            +     '<div class="aura-tip-subtitle">Tipo: ' + escHtml(type.name) + '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="aura-tip-card-body">'
            +   '<div class="aura-tip-row" style="white-space:normal;"><span>Estado en catálogo:</span> <strong>' + (count > 0 ? 'En uso activo' : 'Sin áreas registradas') + '</strong></div>'
            +   (count > 0 ? '<div class="aura-tip-row" style="font-size:10.5px;color:#94a3b8;">No es posible eliminar el tipo mientras mantenga áreas asociadas.</div>' : '')
            + '</div>'
            + '</div>';
    }

    function typeRow(type) {
        var tipCardHtml = renderTypeTipCard(type);
        var areasTipCardHtml = renderTypeAreasTipCard(type);
        var descText = (type.description || '').trim();
        var shortDesc = descText.length > 42 ? descText.substring(0, 42) + '…' : descText;
        var descHtml = descText
            ? '<span class="aura-type-desc" data-tooltip="' + escHtml(descText) + '">' + escHtml(shortDesc) + '</span>'
            : '<span style="color:#94a3b8;">&mdash;</span>';

        return [
            '<span class="aura-type-swatch aura-has-tooltip" data-aura-tooltip="' + encodeURIComponent(tipCardHtml) + '" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:' + escHtml(type.color || '#2563eb') + ';box-shadow:0 1px 3px rgba(0,0,0,0.18);cursor:pointer;border:2px solid #ffffff;"><span class="dashicons dashicons-tag" style="font-size:13px;width:13px;height:13px;color:#ffffff;"></span></span>',
            '<strong class="aura-type-title aura-has-tooltip" data-aura-tooltip="' + encodeURIComponent(tipCardHtml) + '" style="cursor:pointer;color:#0f172a;">' + escHtml(type.name) + '</strong>',
            '<code>' + escHtml(type.slug) + '</code>',
            descHtml,
            type.is_default ? '<span class="aura-badge aura-badge-green" data-tooltip="Se selecciona por defecto al dar de alta una nueva área">⭐ Sí</span>' : '<span style="color:#94a3b8;">&mdash;</span>',
            '<span class="aura-badge aura-badge-blue aura-has-tooltip" data-aura-tooltip="' + encodeURIComponent(areasTipCardHtml) + '" style="cursor:help;font-weight:700;padding:4px 10px;">' + parseInt(type.areas_count || 0, 10) + '</span>',
            '<button type="button" class="aura-action-btn js-type-edit" data-id="' + type.id + '" data-tooltip="Editar tipo: ' + escHtml(type.name) + '"><span class="dashicons dashicons-edit"></span></button>'
            + '<button type="button" class="aura-action-btn danger js-type-delete" data-id="' + type.id + '" data-count="' + type.areas_count + '" data-tooltip="Eliminar tipo: ' + escHtml(type.name) + '"><span class="dashicons dashicons-trash"></span></button>'
        ];
    }

    function renderAreaChildCard(area) {
        var dashboardUrl = 'admin.php?page=aura-areas&view=dashboard&area_id=' + area.id;
        var budgetUrl = 'admin.php?page=aura-financial-budgets&area_id=' + area.id;
        var archiveAction = area.status === 'active'
            ? '<button type="button" class="aura-btn aura-btn-secondary danger aura-btn-sm js-area-archive" data-id="' + area.id + '"><span class="dashicons dashicons-archive"></span> Archivar</button>'
            : '<button type="button" class="aura-btn aura-btn-secondary success aura-btn-sm js-area-reactivate" data-id="' + area.id + '"><span class="dashicons dashicons-update"></span> Reactivar</button>';

        var budgetFormatted = (area.budget_assigned !== null && area.budget_assigned !== undefined && Number(area.budget_assigned) > 0)
            ? formatCurrency(area.budget_assigned)
            : 'Sin presupuesto asignado';

        var usersHtml = '';
        if (area.assigned_users && area.assigned_users.length) {
            usersHtml = '<div class="aura-child-users-list">';
            area.assigned_users.forEach(function (user) {
                usersHtml += '<div class="aura-child-user-item">'
                    + '<img class="aura-child-user-av" src="' + escHtml(user.avatar_url) + '" alt="' + escHtml(user.display_name) + '">'
                    + '<div class="aura-child-user-info"><span class="aura-child-user-name">' + escHtml(user.display_name) + '</span>'
                    + (user.email ? '<span class="aura-child-user-email">' + escHtml(user.email) + '</span>' : '')
                    + '</div></div>';
            });
            usersHtml += '</div>';
        } else {
            usersHtml = '<span class="aura-child-empty-text">Sin miembros ni responsables asignados</span>';
        }

        var logoImg = (area.logo_thumb_url || area.logo_url)
            ? '<img src="' + escHtml(area.logo_thumb_url || area.logo_url) + '" alt="' + escHtml(area.name) + '" class="aura-child-hero-logo" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">'
            : '<span class="aura-area-logo-placeholder"><span class="dashicons dashicons-format-image"></span></span>';

        return '<div class="aura-child-card">'
            + '<div class="aura-child-hero-bar">'
            +   '<div class="aura-child-hero-left">'
            +     logoImg
            +     '<div class="aura-child-hero-text">'
            +       '<div class="aura-child-hero-title">' + escHtml(area.name) + '</div>'
            +       (area.parent_name ? '<div class="aura-child-hero-parent"><span class="dashicons dashicons-networking"></span> Pertenece a: <strong>' + escHtml(area.parent_name) + '</strong></div>' : '')
            +     '</div>'
            +   '</div>'
            +   '<div class="aura-child-hero-badges">'
            +     '<span class="aura-type-badge" data-tooltip="Clasificación: ' + escHtml(area.type_label) + '">' + escHtml(area.type_label) + '</span>'
            +     '<span class="aura-status-badge ' + (area.status === 'active' ? 'aura-status-active' : 'aura-status-archived') + '" data-tooltip="Estado: ' + escHtml(area.status === 'active' ? 'Activa' : 'Archivada') + '">' + escHtml(area.status === 'active' ? 'Activa' : 'Archivada') + '</span>'
            +   '</div>'
            + '</div>'
            + '<div class="aura-child-sections-grid">'
            +   '<div class="aura-child-subcard">'
            +     '<div class="aura-child-subcard-header"><span class="dashicons dashicons-info"></span> Información General</div>'
            +     '<div class="aura-child-subcard-body">'
            +       '<p class="aura-child-description">' + (area.description ? escHtml(area.description) : '<em>Sin descripción registrada.</em>') + '</p>'
            +       '<div class="aura-child-meta-item"><span>Color:</span> <span class="aura-color-badge" style="background:' + escHtml(area.color || '#2271b1') + '; display:inline-block; vertical-align:middle; margin-left:6px;"></span> <code>' + escHtml(area.color || '#2271b1') + '</code></div>'
            +     '</div>'
            +   '</div>'
            +   '<div class="aura-child-subcard">'
            +     '<div class="aura-child-subcard-header"><span class="dashicons dashicons-money-alt"></span> Presupuesto Asignado</div>'
            +     '<div class="aura-child-subcard-body">'
            +       '<div class="aura-child-budget-val">' + budgetFormatted + '</div>'
            +       (Number(area.budget_assigned) > 0 ? '<a href="' + budgetUrl + '" class="aura-child-inline-link"><span class="dashicons dashicons-external"></span> Administrar partidas presupuestarias</a>' : '')
            +     '</div>'
            +   '</div>'
            +   '<div class="aura-child-subcard aura-child-subcard-full">'
            +     '<div class="aura-child-subcard-header"><span class="dashicons dashicons-admin-users"></span> Personal Asignado (' + (area.assigned_users ? area.assigned_users.length : 0) + ')</div>'
            +     '<div class="aura-child-subcard-body">' + usersHtml + '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="aura-child-action-dock">'
            +   '<button type="button" class="aura-btn aura-btn-secondary aura-btn-sm js-area-edit" data-id="' + area.id + '"><span class="dashicons dashicons-edit"></span> Editar</button>'
            +   '<a href="' + dashboardUrl + '" class="aura-btn aura-btn-secondary aura-btn-sm"><span class="dashicons dashicons-chart-bar"></span> Dashboard</a>'
            +   '<a href="' + budgetUrl + '" class="aura-btn aura-btn-secondary aura-btn-sm"><span class="dashicons dashicons-money-alt"></span> Presupuesto</a>'
            +   archiveAction
            + '</div>'
            + '</div>';
    }

    function areaRow(area) {
        var dashboardUrl = 'admin.php?page=aura-areas&view=dashboard&area_id=' + area.id;
        var budgetUrl = 'admin.php?page=aura-financial-budgets&area_id=' + area.id;
        var archiveAction = area.status === 'active'
            ? '<button type="button" class="aura-action-btn danger js-area-archive" data-id="' + area.id + '" data-tooltip="Archivar"><span class="dashicons dashicons-archive"></span></button>'
            : '<button type="button" class="aura-action-btn success js-area-reactivate" data-id="' + area.id + '" data-tooltip="Reactivar"><span class="dashicons dashicons-update"></span></button>';

        var budgetDisplay = (area.budget_assigned !== null && area.budget_assigned !== undefined && Number(area.budget_assigned) > 0)
            ? '<a href="' + budgetUrl + '" style="font-weight:700;color:#059669;text-decoration:none;" data-tooltip="Ver presupuesto de esta área">' + formatCurrency(area.budget_assigned) + '</a>'
            : '<span style="color:#94a3b8;">&mdash;</span>';

        return [
            renderAreaLogo(area),
            renderAreaName(area),
            '<span class="aura-type-badge">' + escHtml(area.type_label) + '</span>',
            renderAssignedUsers(area),
            budgetDisplay,
            '<span class="aura-status-badge ' + (area.status === 'active' ? 'aura-status-active' : 'aura-status-archived') + '">' + escHtml(area.status === 'active' ? 'Activa' : 'Archivada') + '</span>',
            '<button type="button" class="aura-action-btn js-area-edit" data-id="' + area.id + '" data-tooltip="Editar"><span class="dashicons dashicons-edit"></span></button>'
            + '<a href="' + dashboardUrl + '" class="aura-action-btn" data-tooltip="Dashboard"><span class="dashicons dashicons-chart-bar"></span></a>'
            + '<a href="' + budgetUrl + '" class="aura-action-btn" data-tooltip="Presupuesto"><span class="dashicons dashicons-money-alt"></span></a>'
            + archiveAction
        ];
    }

    function typeRow(type) {
        return [
            '<span style="display:inline-block;width:22px;height:22px;border-radius:999px;background:' + escHtml(type.color) + ';border:1px solid rgba(0,0,0,.12);"></span>',
            escHtml(type.name),
            '<code>' + escHtml(type.slug) + '</code>',
            type.description ? escHtml(type.description) : '—',
            type.is_default ? '<span class="aura-badge aura-badge-green">Sí</span>' : '',
            '<strong>' + parseInt(type.areas_count || 0, 10) + '</strong>',
            '<button type="button" class="aura-action-btn js-type-edit" data-id="' + type.id + '" data-tooltip="Editar"><span class="dashicons dashicons-edit"></span></button>'
            + '<button type="button" class="aura-action-btn danger js-type-delete" data-id="' + type.id + '" data-count="' + type.areas_count + '" data-tooltip="Eliminar"><span class="dashicons dashicons-trash"></span></button>'
        ];
    }

    function initAreasTable() {
        if (!$('#aura-areas-table').length) {
            return;
        }

        areasTable = $('#aura-areas-table').DataTable({
            responsive: {
                details: {
                    renderer: function (api, rowIdx, columns) {
                        var area = currentAreasList[rowIdx];
                        if (!area) {
                            var subData = $.map(columns, function (col, i) {
                                return col.hidden ? '<li data-dtr-index="' + col.columnIndex + '"><span class="dtr-title">' + col.title + ':</span> <span class="dtr-data">' + col.data + '</span></li>' : '';
                            }).join('');
                            return subData ? $('<ul class="dtr-details"/>').append(subData) : false;
                        }

                        return $(renderAreaChildCard(area));
                    }
                }
            },
            searching: false,
            dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            pageLength: 20,
            lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
            language: {
                lengthMenu: 'Mostrar _MENU_ áreas',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ áreas',
                paginate: { previous: '‹', next: '›' },
                zeroRecords: 'No se encontraron áreas.',
                emptyTable: 'Sin áreas disponibles.'
            },
            columnDefs: [
                { responsivePriority: 1, targets: 1 },
                { responsivePriority: 2, targets: 5 },
                { responsivePriority: 3, targets: 6 },
                { responsivePriority: 4, targets: 0 },
                { responsivePriority: 5, targets: 2 },
                { responsivePriority: 6, targets: 4 },
                { responsivePriority: 7, targets: 3 },
                { orderable: false, targets: [0, 3, 6] }
            ],
            data: [],
            columns: [null, null, null, null, null, null, null]
        });

        loadAreas();
    }

    function initTypesTable() {
        if (!$('#aura-types-table').length) {
            return;
        }

        typesTable = $('#aura-types-table').DataTable({
            responsive: true,
            searching: true,
            dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            pageLength: 20,
            language: {
                info: 'Mostrando _START_ a _END_ de _TOTAL_ tipos',
                infoEmpty: '0 tipos',
                infoFiltered: '(filtrado de _MAX_)',
                lengthMenu: 'Mostrar _MENU_ tipos',
                zeroRecords: 'No se encontraron tipos de área.',
                emptyTable: 'Sin tipos de área registrados.',
                paginate: { previous: '‹', next: '›' }
            },
            columnDefs: [
                { responsivePriority: 1, targets: 1 },
                { responsivePriority: 2, targets: 6 },
                { responsivePriority: 3, targets: 5 },
                { responsivePriority: 4, targets: 0 },
                { responsivePriority: 5, targets: 4 },
                { responsivePriority: 6, targets: 2 },
                { responsivePriority: 7, targets: 3 },
                { orderable: false, targets: [0, 4, 6] }
            ],
            data: [],
            columns: [null, null, null, null, null, null, null]
        });

        loadTypes();
    }

    function refreshAreaTypeOptions(selectedType) {
        if (!$('#aura-field-type').length && !$('#aura-filter-type').length) {
            return $.Deferred().resolve().promise();
        }

        return api('aura_areas_types_dropdown', cfg.nonces.areas, {}).done(function (response) {
            if (!response.success) {
                return;
            }

            var $field = $('#aura-field-type');
            var $filter = $('#aura-filter-type');
            var currentFilter = $filter.val() || '';

            if ($field.length) {
                $field.empty();
            }

            if ($filter.length) {
                $filter.empty().append('<option value="">Todos los tipos</option>');
            }

            (response.data || []).forEach(function (type) {
                if ($field.length) {
                    $field.append($('<option>').val(type.slug).text(type.name));
                }
                if ($filter.length) {
                    $filter.append($('<option>').val(type.slug).text(type.name));
                }
            });

            if ($field.length) {
                $field.val(selectedType || cfg.defaultType);
            }
            if ($filter.length) {
                $filter.val(currentFilter);
            }
        });
    }

    function loadAreas() {
        if (!areasTable) {
            return;
        }

        api('aura_areas_list', cfg.nonces.areas, {
            status: $('#aura-filter-status').val(),
            type: $('#aura-filter-type').val(),
            search: $('#aura-filter-search').val(),
            paged: 1
        }).done(function (response) {
            if (!response.success) {
                showNotice('#aura-areas-notice', (response.data && response.data.message) || strings.error, false);
                return;
            }

            currentAreasList = response.data.areas || [];
            areasTable.clear().rows.add(currentAreasList.map(areaRow)).draw();
        }).fail(function () {
            showNotice('#aura-areas-notice', strings.error || 'Error', false);
        });
    }

    function loadTypes() {
        if (!typesTable) {
            return;
        }

        api('aura_areas_types_list', cfg.nonces.types, {}).done(function (response) {
            if (!response.success) {
                showNotice('#aura-types-notice', (response.data && response.data.message) || strings.error, false);
                return;
            }

            typesTable.clear().rows.add((response.data.data || []).map(typeRow)).draw();
            refreshAreaTypeOptions($('#aura-field-type').val() || cfg.defaultType);
        }).fail(function () {
            showNotice('#aura-types-notice', strings.error || 'Error', false);
        });
    }

    function loadUsers(selectedId) {
        if (!$('#aura-field-responsible').length) {
            return;
        }

        api('aura_areas_users', cfg.nonces.areas, {}).done(function (response) {
            if (!response.success) {
                return;
            }

            var $select = $('#aura-field-responsible');
            $select.find('option:not(:first)').remove();
            (response.data.users || []).forEach(function (user) {
                $select.append($('<option>').val(user.id).text(user.name + ' (' + user.email + ')'));
            });
            $select.val(selectedId || 0);
        });
    }

    function loadParentAreas(exceptId, selectedId) {
        if (!$('#aura-field-parent').length) {
            return;
        }

        api('aura_areas_areas_dropdown', cfg.nonces.areas, { except_id: exceptId || 0 }).done(function (response) {
            if (!response.success) {
                return;
            }

            var $select = $('#aura-field-parent');
            $select.find('option:not(:first)').remove();
            (response.data.areas || []).forEach(function (area) {
                $select.append($('<option>').val(area.id).text(area.name));
            });
            $select.val(selectedId || 0);
        });
    }

    function setLogoPreview(url, attachmentId) {
        var $preview = $('#aura-logo-preview');
        $preview.empty();

        if (url) {
            $preview.html('<img src="' + escHtml(url) + '" alt="Logo" data-preview-img="' + escHtml(url) + '" data-preview-title="Vista previa del logo">');
            $('#aura-field-logo-id').val(attachmentId || 0);
            $('#aura-logo-remove-btn').removeClass('aura-is-hidden');
        } else {
            $preview.html('<span class="dashicons dashicons-format-image"></span>');
            $('#aura-field-logo-id').val(0);
            $('#aura-logo-remove-btn').addClass('aura-is-hidden');
        }
    }

    function openMediaLibrary() {
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: strings.selectImage || 'Seleccionar imagen',
            button: { text: 'Usar esta imagen' },
            library: { type: 'image' },
            multiple: false
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            cropAttachmentId = attachment.id;
            $('#aura-crop-img').attr('src', attachment.url);
            openCropModal();
        });

        mediaFrame.open();
    }

    function openCropModal() {
        openModal('aura-crop-modal');

        var image = document.getElementById('aura-crop-img');
        if (cropper) {
            cropper.destroy();
        }

        image.onload = function () {
            cropper = new Cropper(image, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 0.8,
                responsive: true
            });
        };

        if (image.complete && image.naturalWidth > 0) {
            image.onload();
        }
    }

    function closeCropModal() {
        closeModal('aura-crop-modal');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function applyCrop() {
        if (!cropper) {
            return;
        }

        var cropData = cropper.getData(true);
        $('#aura-crop-spinner').show();
        $('#aura-crop-apply').prop('disabled', true);

        api('aura_areas_crop_logo', cfg.nonces.areas, {
            attachment_id: cropAttachmentId,
            x: cropData.x,
            y: cropData.y,
            width: cropData.width,
            height: cropData.height
        }).done(function (response) {
            $('#aura-crop-spinner').hide();
            $('#aura-crop-apply').prop('disabled', false);

            if (!response.success) {
                showNotice('#aura-areas-notice', (response.data && response.data.message) || strings.error, false);
                return;
            }

            setLogoPreview(response.data.url, response.data.attachment_id);
            closeCropModal();
        }).fail(function () {
            $('#aura-crop-spinner').hide();
            $('#aura-crop-apply').prop('disabled', false);
            showNotice('#aura-areas-notice', strings.error || 'Error', false);
        });
    }

    function resetAreaForm() {
        if (!$('#aura-area-form').length) {
            return;
        }

        $('#aura-area-form')[0].reset();
        $('#aura-area-id').val(0);
        $('#aura-field-icon').val('dashicons-groups');
        $('#aura-field-sort').val(0);
        $('#error-name').text('').addClass('aura-is-hidden');
        setLogoPreview('', 0);
        loadUsers(0);
        loadParentAreas(0, 0);
        refreshAreaTypeOptions(cfg.defaultType);

        try {
            $('#aura-field-color').wpColorPicker('color', '#2271b1');
        } catch (e) {}
    }

    function openAreaModal(area) {
        var isEdit = !!(area && area.id);
        $('#aura-area-modal-title').text(isEdit ? strings.editArea : strings.newArea);
        $('#aura-area-id').val(isEdit ? area.id : 0);
        $('#aura-field-name').val(isEdit ? area.name : '');
        $('#aura-field-description').val(isEdit ? area.description : '');
        $('#aura-field-sort').val(isEdit ? area.sort_order : 0);
        $('#aura-field-icon').val(isEdit ? (area.icon || 'dashicons-groups') : 'dashicons-groups');
        $('#error-name').text('').addClass('aura-is-hidden');

        refreshAreaTypeOptions(isEdit ? area.type : cfg.defaultType);
        loadUsers(isEdit ? area.responsible_user_id : 0);
        loadParentAreas(isEdit ? area.id : 0, isEdit ? area.parent_area_id : 0);
        setLogoPreview(isEdit ? area.logo_url : '', isEdit ? area.logo_id : 0);

        try {
            $('#aura-field-color').wpColorPicker('color', isEdit ? area.color : '#2271b1');
        } catch (e) {}

        openModal('aura-area-modal');
        setTimeout(function () {
            $('#aura-field-name').trigger('focus');
        }, 120);
    }

    function saveArea() {
        var name = ($('#aura-field-name').val() || '').trim();
        if (!name) {
            $('#error-name').text(strings.nameRequired || 'Campo requerido').removeClass('aura-is-hidden');
            $('#aura-field-name').trigger('focus');
            return;
        }

        $('#error-name').addClass('aura-is-hidden');
        $('#aura-save-spinner').show();
        $('#aura-area-save-btn').prop('disabled', true);

        api('aura_areas_save', cfg.nonces.areas, {
            area_id: $('#aura-area-id').val(),
            name: name,
            type: $('#aura-field-type').val(),
            description: $('#aura-field-description').val(),
            color: $('#aura-field-color').val(),
            icon: $('#aura-field-icon').val(),
            logo_id: $('#aura-field-logo-id').val(),
            sort_order: $('#aura-field-sort').val(),
            responsible_user_id: $('#aura-field-responsible').val(),
            parent_area_id: $('#aura-field-parent').val()
        }).done(function (response) {
            $('#aura-save-spinner').hide();
            $('#aura-area-save-btn').prop('disabled', false);

            if (!response.success) {
                showNotice('#aura-areas-notice', (response.data && response.data.message) || strings.error, false);
                return;
            }

            closeModal('aura-area-modal');
            showToast((response.data && response.data.message) || strings.saved, 'success');
            loadAreas();
        }).fail(function () {
            $('#aura-save-spinner').hide();
            $('#aura-area-save-btn').prop('disabled', false);
            showNotice('#aura-areas-notice', strings.error || 'Error', false);
        });
    }

    function toggleAreaArchive(id, action) {
        var message = action === 'reactivate' ? strings.confirmReactivate : strings.confirmArchive;
        if (!window.confirm(message)) {
            return;
        }

        api('aura_areas_delete', cfg.nonces.areas, {
            area_id: id,
            archive_action: action
        }).done(function (response) {
            if (!response.success) {
                showNotice('#aura-areas-notice', (response.data && response.data.message) || strings.error, false);
                return;
            }

            showToast(response.data.message || strings.saved, 'success');
            loadAreas();
        }).fail(function () {
            showNotice('#aura-areas-notice', strings.error || 'Error', false);
        });
    }

    function openTypeModal(type) {
        var isEdit = !!(type && type.id);
        $('#aura-types-modal-title').text(isEdit ? strings.editType : strings.newType);
        $('#aura-type-id').val(isEdit ? type.id : 0);
        $('#aura-type-name').val(isEdit ? type.name : '');
        $('#aura-type-slug').val(isEdit ? type.slug : '');
        $('#aura-type-description').val(isEdit ? type.description : '');
        $('#aura-type-sort').val(isEdit ? type.sort_order : 0);
        $('#aura-type-default').prop('checked', isEdit ? !!type.is_default : false);

        try {
            $('#aura-type-color').wpColorPicker('color', isEdit ? type.color : '#e0e7ff');
        } catch (e) {}

        openModal('aura-types-modal');
        setTimeout(function () {
            $('#aura-type-name').trigger('focus');
        }, 120);
    }

    function saveType(event) {
        event.preventDefault();

        var name = ($('#aura-type-name').val() || '').trim();
        if (!name) {
            showNotice('#aura-types-notice', strings.typeNameRequired || 'Campo requerido', false);
            return;
        }

        $('#aura-type-save-btn').prop('disabled', true);

        api('aura_areas_types_save', cfg.nonces.types, {
            id: $('#aura-type-id').val(),
            name: name,
            description: $('#aura-type-description').val(),
            color: $('#aura-type-color').val(),
            sort_order: $('#aura-type-sort').val(),
            is_default: $('#aura-type-default').is(':checked') ? 1 : 0
        }).done(function (response) {
            $('#aura-type-save-btn').prop('disabled', false);

            if (!response.success) {
                showNotice('#aura-types-notice', (response.data && response.data.message) || strings.error, false);
                return;
            }

            closeModal('aura-types-modal');
            showToast(response.data.message || strings.saved, 'success');
            loadTypes();
        }).fail(function () {
            $('#aura-type-save-btn').prop('disabled', false);
            showNotice('#aura-types-notice', strings.error || 'Error', false);
        });
    }

    function bindEvents() {
        $('.aura-tab-btn').on('click', function () {
            switchTab($(this).attr('data-tab-key'));
        });

        $('#aura-apply-filters-btn').on('click', loadAreas);
        $('#aura-filter-search').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadAreas();
            }
        });
        $('#aura-clear-filters-btn').on('click', function () {
            $('#aura-filter-search').val('');
            $('#aura-filter-type').val('');
            $('#aura-filter-status').val('active');
            loadAreas();
        });

        $('#aura-add-area-btn').on('click', function () {
            resetAreaForm();
            openAreaModal(null);
        });

        $('#aura-area-save-btn').on('click', saveArea);

        $(document).on('click', '.js-area-edit', function () {
            api('aura_areas_get', cfg.nonces.areas, { area_id: $(this).data('id') }).done(function (response) {
                if (!response.success) {
                    showNotice('#aura-areas-notice', (response.data && response.data.message) || strings.error, false);
                    return;
                }

                openAreaModal(response.data.area);
            });
        });

        $(document).on('click', '.js-row-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!areasTable) {
                return;
            }

            var $btn = $(this);
            var $tr = $btn.closest('tr');
            var row = areasTable.row($tr);
            var areaId = parseInt($btn.data('id'), 10);
            var area = null;
            if (currentAreasList && currentAreasList.length) {
                area = currentAreasList.find(function (a) { return parseInt(a.id, 10) === areaId; });
                if (!area && row.length) {
                    area = currentAreasList[row.index()];
                }
            }

            if (!area) {
                return;
            }

            if (row.child.isShown()) {
                row.child.hide();
                $tr.removeClass('shown is-expanded');
                $btn.removeClass('is-active').attr('aria-expanded', 'false');
            } else {
                row.child(renderAreaChildCard(area), 'aura-dt-child-row').show();
                $tr.addClass('shown is-expanded');
                $btn.addClass('is-active').attr('aria-expanded', 'true');
                if (window.AuraUI && typeof window.AuraUI.initTooltips === 'function') {
                    window.AuraUI.initTooltips();
                }
            }
        });

        $(document).on('click', '.js-area-archive', function () {
            toggleAreaArchive($(this).data('id'), 'archive');
        });

        $(document).on('click', '.js-area-reactivate', function () {
            toggleAreaArchive($(this).data('id'), 'reactivate');
        });

        $('#aura-logo-select-btn').on('click', openMediaLibrary);
        $('#aura-logo-remove-btn').on('click', function () {
            setLogoPreview('', 0);
        });

        $('#aura-crop-apply').on('click', applyCrop);
        $('#aura-crop-close, #aura-crop-cancel').on('click', closeCropModal);

        $('#at-search').on('input', function () {
            if (typesTable) {
                typesTable.search($(this).val() || '').draw();
            }
        });

        $('#at-clear-btn').on('click', function () {
            $('#at-search').val('');
            if (typesTable) {
                typesTable.search('').draw();
            }
        });

        $('#aura-types-new-btn').on('click', function () {
            if ($('#aura-types-form').length) {
                $('#aura-types-form')[0].reset();
            }
            $('#aura-type-id').val(0);
            $('#aura-type-slug').val('');
            try {
                $('#aura-type-color').wpColorPicker('color', '#e0e7ff');
            } catch (e) {}
            openTypeModal(null);
        });

        $(document).on('input', '#aura-type-name', function () {
            var slug = ($(this).val() || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
            $('#aura-type-slug').val(slug);
        });

        $(document).on('click', '.js-type-edit', function () {
            api('aura_areas_types_get', cfg.nonces.types, { id: $(this).data('id') }).done(function (response) {
                if (!response.success) {
                    showNotice('#aura-types-notice', (response.data && response.data.message) || strings.error, false);
                    return;
                }

                openTypeModal(response.data);
            });
        });

        $(document).on('click', '.js-type-delete', function () {
            var count = parseInt($(this).data('count'), 10) || 0;
            if (count > 0) {
                showNotice('#aura-types-notice', strings.typeDeleteBlocked || strings.error, false);
                return;
            }

            if (!window.confirm(strings.typeDelete || 'Confirmar')) {
                return;
            }

            api('aura_areas_types_delete', cfg.nonces.types, { id: $(this).data('id') }).done(function (response) {
                if (!response.success) {
                    showNotice('#aura-types-notice', (response.data && response.data.message) || strings.error, false);
                    return;
                }

                showToast(response.data.message || strings.saved, 'success');
                loadTypes();
            }).fail(function () {
                showNotice('#aura-types-notice', strings.error || 'Error', false);
            });
        });

        $('#aura-types-form').on('submit', saveType);

        $(document).on('click', '[data-modal-close]', function () {
            closeModal($(this).attr('data-modal-close'));
        });

        $(document).on('click', '.aura-modal-overlay', function (event) {
            if (!$(event.target).hasClass('aura-modal-overlay')) {
                return;
            }

            if ($(this).attr('id') === 'aura-crop-modal') {
                closeCropModal();
                return;
            }

            closeModal($(this).attr('id'));
        });

        $(document).on('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if ($('#aura-crop-modal').hasClass('active')) {
                closeCropModal();
                return;
            }

            $('.aura-modal-overlay.active').each(function () {
                closeModal($(this).attr('id'));
            });
        });
    }

    function initFloatingImagePreview() {
        var $preview = $('#aura-floating-img-preview');
        if (!$preview.length) {
            $preview = $('<div id="aura-floating-img-preview" class="aura-floating-img-preview"><img src="" alt=""><span class="aura-floating-img-title"></span></div>');
            $('body').append($preview);
        }

        var hideTimeout = null;

        $(document).on('mouseenter', '.aura-img-preview[data-preview-img], #aura-logo-preview img', function () {
            clearTimeout(hideTimeout);
            var $el = $(this);
            var src = $el.attr('data-preview-img') || $el.attr('src');
            var title = $el.attr('data-preview-title') || $el.attr('alt') || '';

            if (!src) {
                return;
            }

            var $img = $preview.find('img');
            var $title = $preview.find('.aura-floating-img-title');

            $img.attr('src', src);
            if (title) {
                $title.text(title).show();
            } else {
                $title.hide();
            }

            $preview.css({ display: 'block' });

            var rect = this.getBoundingClientRect();
            var previewWidth = 216;
            var previewHeight = title ? 240 : 216;

            var left = rect.right + 12;
            if (left + previewWidth > $(window).width() - 16) {
                left = rect.left - previewWidth - 12;
            }
            if (left < 16) {
                left = 16;
            }

            var top = rect.top + (rect.height / 2) - (previewHeight / 2);
            if (top + previewHeight > $(window).height() - 16) {
                top = $(window).height() - previewHeight - 16;
            }
            if (top < 16) {
                top = 16;
            }

            $preview.css({
                top: Math.round(top) + 'px',
                left: Math.round(left) + 'px'
            });

            requestAnimationFrame(function () {
                $preview.addClass('is-visible');
            });
        });

        $(document).on('mouseleave', '.aura-img-preview[data-preview-img], #aura-logo-preview img', function () {
            $preview.removeClass('is-visible');
            hideTimeout = setTimeout(function () {
                if (!$preview.hasClass('is-visible')) {
                    $preview.hide();
                }
            }, 180);
        });
    }

    $(function () {
        if ($('.aura-color-picker').length && typeof $.fn.wpColorPicker === 'function') {
            $('.aura-color-picker').wpColorPicker();
        }

        initAreasTable();
        initTypesTable();
        bindEvents();
        initFloatingImagePreview();

        if ($('.aura-tab-btn').length) {
            if ($('.aura-tab-btn[data-tab-key="' + cfg.tab + '"]').length) {
                switchTab(cfg.tab);
            } else {
                switchTab($('.aura-tab-btn').first().attr('data-tab-key'));
            }
        }
    });
}(jQuery));