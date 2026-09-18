/**
 * Aura Business Suite - Módulo de Permisos Granulares (CBAC)
 * Lógica JS interactiva estandarizada según prompt-maestro.md
 * @version 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    const data = window.auraPermissionsData || {};
    const i18n = data.i18n || {};

    // ==========================================================================
    // 1. GESTIÓN DE PESTAÑAS (AURA NAVBAR CON PERSISTENCIA EN URL)
    // ==========================================================================
    function switchTab(tabId, updateUrl = true) {
        if (!tabId) return;

        $('.aura-tab-btn').removeClass('is-active');
        $('.aura-tab-btn[data-tab="' + tabId + '"]').addClass('is-active');

        $('.aura-tab-panel').hide();
        $('#aura-tab-panel-' + tabId).fadeIn(150);

        if (updateUrl && window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            window.history.replaceState(null, '', url.toString());
        }

        // Recalcular DataTables si están presentes
        if ($.fn.DataTable) {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
        }
    }

    // Exponer globalmente switchTab para botones inline
    window.switchTab = switchTab;

    $(document).on('click', '.aura-tab-btn', function(e) {
        const tabId = $(this).data('tab');
        if (tabId) {
            e.preventDefault();
            switchTab(tabId);
        }
    });

    $(document).on('click', '#aura-open-templates-btn', function(e) {
        e.preventDefault();
        switchTab('profile-templates');
    });

    // Leer pestaña inicial desde la URL o por defecto
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || ($('#aura-tab-panel-user-permissions').length ? 'user-permissions' : 'active-users');
    if ($('.aura-tab-btn[data-tab="' + initialTab + '"]').length) {
        switchTab(initialTab, false);
    }

    // ==========================================================================
    // 2. FILAS EXPANDIBLES (CHILD ROWS WOW CON .aura-row-toggle Y .aura-user-identity)
    // ==========================================================================
    function toggleChildRow($btn) {
        if (!$btn || !$btn.length) return;
        const $row = $btn.closest('tr');
        const $childRow = $row.next('.aura-child-row');

        if ($childRow.length) {
            if ($childRow.is(':visible')) {
                $childRow.fadeOut(150, function() {
                    $btn.removeClass('is-expanded').text('+');
                    $btn.attr('aria-expanded', 'false');
                });
            } else {
                $childRow.fadeIn(150, function() {
                    $btn.addClass('is-expanded').text('−');
                    $btn.attr('aria-expanded', 'true');
                });
            }
        }
    }

    $(document).on('click', '.aura-row-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleChildRow($(this));
    });

    // En móviles y desktop: Permitir que un toque o clic en la identidad del usuario (.aura-user-identity) también abra/cierre los detalles
    $(document).on('click', '.aura-parent-row .aura-user-identity', function(e) {
        if ($(e.target).closest('a, button, input, select, textarea').length) {
            return;
        }
        const $btn = $(this).closest('tr').find('.aura-row-toggle');
        if ($btn.length) {
            e.preventDefault();
            toggleChildRow($btn);
        }
    });

    // ==========================================================================
    // 3. BUSCADOR EN TIEMPO REAL: USUARIOS ACTIVOS
    // ==========================================================================
    $('#aura-users-table-search').on('input', function() {
        const term = ($(this).val() || '').toLowerCase().trim();
        let matches = 0;

        $('#aura-users-table-body tr.aura-parent-row').each(function() {
            const $row = $(this);
            const $childRow = $row.next('.aura-child-row');
            const haystack = ($row.attr('data-user-search') || '').toLowerCase();
            const visible = (!term || haystack.indexOf(term) !== -1);

            $row.toggle(visible);
            if (!visible && $childRow.length) {
                $childRow.hide();
                $row.find('.aura-row-toggle').removeClass('is-expanded').text('+');
            }
            if (visible) matches++;
        });

        if (term && matches === 0) {
            if (!$('#aura-no-active-matches').length) {
                const emptyRow = '<tr id="aura-no-active-matches"><td colspan="6">' +
                    '<div class="aura-empty-state">' +
                    '<span class="dashicons dashicons-search aura-empty-state-icon"></span>' +
                    '<h4 class="aura-empty-state-title">' + (i18n.noActiveMatches || 'No se encontraron usuarios que coincidan') + '</h4>' +
                    '<p class="aura-empty-state-desc">"' + $('<div>').text(term).html() + '"</p>' +
                    '</div>' +
                    '</td></tr>';
                $('#aura-users-table-body').append(emptyRow);
            } else {
                $('#aura-no-active-matches').show();
            }
        } else {
            $('#aura-no-active-matches').remove();
        }
    });

    // ==========================================================================
    // 4. BUSCADOR EN TIEMPO REAL: USUARIOS WP DISPONIBLES
    // ==========================================================================
    $('#aura-inactive-users-search').on('input', function() {
        const term = ($(this).val() || '').toLowerCase().trim();
        let matches = 0;

        $('#aura-inactive-users-body tr.aura-parent-row').each(function() {
            const $row = $(this);
            const $childRow = $row.next('.aura-child-row');
            const haystack = ($row.attr('data-user-search') || '').toLowerCase();
            const visible = (!term || haystack.indexOf(term) !== -1);

            $row.toggle(visible);
            if (!visible && $childRow.length) {
                $childRow.hide();
                $row.find('.aura-row-toggle').removeClass('is-expanded').text('+');
            }
            if (visible) matches++;
        });

        if (term && matches === 0) {
            if (!$('#aura-no-inactive-matches').length) {
                const emptyRow = '<tr id="aura-no-inactive-matches"><td colspan="5">' +
                    '<div class="aura-empty-state">' +
                    '<span class="dashicons dashicons-search aura-empty-state-icon"></span>' +
                    '<h4 class="aura-empty-state-title">' + (i18n.noInactiveMatches || 'No se encontraron usuarios') + '</h4>' +
                    '<p class="aura-empty-state-desc">"' + $('<div>').text(term).html() + '"</p>' +
                    '</div>' +
                    '</td></tr>';
                $('#aura-inactive-users-body').append(emptyRow);
            } else {
                $('#aura-no-inactive-matches').show();
            }
        } else {
            $('#aura-no-inactive-matches').remove();
        }
    });

    // ==========================================================================
    // 5. MODAL ASIGNAR / CREAR USUARIO
    // ==========================================================================
    function openAuraUserModal(tab) {
        const $m = $('#aura-modal-user-manager');
        $m.addClass('active').css('display', 'flex');
        $('#aura_cu_first_name, #aura_cu_last_name, #aura_cu_email, #aura_cu_user_login, #aura_cu_phone, #aura_cu_password').val('');
        $('#aura_cu_auto_username').prop('checked', true);
        $('#aura-crear-usuario-error').hide().text('');

        if (tab === 'create-new') {
            $('.aura-modal-tab-btn[data-modal-tab="create-new"]').trigger('click');
        } else {
            $('.aura-modal-tab-btn[data-modal-tab="select-wp"]').trigger('click');
            $('#aura-modal-wp-search').val('').trigger('input');
            setTimeout(function() {
                $('#aura-modal-wp-search').focus();
            }, 100);
        }
    }

    function closeAuraUserModal() {
        $('#aura-modal-user-manager').removeClass('active').css('display', 'none');
    }

    $(document).on('click', '#aura-btn-open-user-modal, .aura-btn-open-user-modal', function(e) {
        e.preventDefault();
        openAuraUserModal('select-wp');
    });

    $(document).on('click', '#aura-modal-btn-close, #aura-modal-btn-cancel', function(e) {
        e.preventDefault();
        closeAuraUserModal();
    });

    $(document).on('click', '#aura-modal-user-manager', function(e) {
        if ($(e.target).is('#aura-modal-user-manager')) {
            closeAuraUserModal();
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#aura-modal-user-manager').hasClass('active')) {
            closeAuraUserModal();
        }
    });

    // Auto-generar username desde email
    function syncUsernameFromEmail() {
        if ($('#aura_cu_auto_username').is(':checked')) {
            const emailVal = $.trim($('#aura_cu_email').val());
            const parts = emailVal.split('@');
            if (parts.length > 0 && parts[0]) {
                const clean = parts[0].toLowerCase().replace(/[^a-z0-9._-]/g, '');
                $('#aura_cu_user_login').val(clean);
            } else {
                $('#aura_cu_user_login').val('');
            }
        }
    }

    $('#aura_cu_email').on('input', syncUsernameFromEmail);
    $('#aura_cu_auto_username').on('change', function() {
        if ($(this).is(':checked')) syncUsernameFromEmail();
    });
    $('#aura_cu_user_login').on('input', function() {
        if ($.trim($(this).val())) {
            $('#aura_cu_auto_username').prop('checked', false);
        }
    });

    // Pestañas internas del Modal
    $('.aura-modal-tab-btn').on('click', function() {
        const tab = $(this).data('modal-tab');
        $('.aura-modal-tab-btn').removeClass('is-active');
        $(this).addClass('is-active');

        if (tab === 'select-wp') {
            $('#aura-panel-select-wp').addClass('is-active');
            $('#aura-panel-create-new').removeClass('is-active');
            $('#aura-modal-btn-action-select').show();
            $('#aura-modal-btn-action-create').hide();
        } else {
            $('#aura-panel-create-new').addClass('is-active');
            $('#aura-panel-select-wp').removeClass('is-active');
            $('#aura-modal-btn-action-select').hide();
            $('#aura-modal-btn-action-create').css('display', 'inline-flex');
        }
    });

    // Búsqueda en modal WP
    $('#aura-modal-wp-search').on('input', function() {
        const q = ($(this).val() || '').toLowerCase().trim();
        $('.aura-wp-user-card').each(function() {
            const search = $(this).data('search') || '';
            $(this).toggle(!q || search.indexOf(q) !== -1);
        });
    });

    // Selección de usuario en modal
    $(document).on('click', '.aura-wp-user-card', function() {
        $('.aura-wp-user-card').removeClass('is-selected');
        $(this).addClass('is-selected');
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    // Acción: Configurar usuario seleccionado de WP
    $('#aura-modal-btn-action-select').on('click', function() {
        const selectedId = $('input[name="aura_selected_modal_user"]:checked').val();
        if (!selectedId) {
            alert(i18n.selectUserAlert || 'Por favor seleccione un usuario de la lista.');
            return;
        }
        window.location.href = 'admin.php?page=aura-permissions&user_id=' + selectedId + '&activated=1';
    });

    // Contraseña: Ver/Ocultar
    $('#aura-toggle-pwd').on('click', function() {
        const $inp = $('#aura_cu_password');
        const visible = $inp.attr('type') === 'text';
        $inp.attr('type', visible ? 'password' : 'text');
        $(this).find('.dashicons').toggleClass('dashicons-visibility', visible).toggleClass('dashicons-hidden', !visible);
    });

    // Contraseña: Generador seguro
    $('#aura-generar-pwd').on('click', function() {
        const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
        let pwd = '';
        for (let i = 0; i < 12; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        $('#aura_cu_password').attr('type', 'text').val(pwd);
        $('#aura-toggle-pwd .dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
    });

    // Crear usuario vía AJAX
    $('#aura-modal-btn-action-create').on('click', function() {
        const $err = $('#aura-crear-usuario-error').hide().text('');
        const first     = $.trim($('#aura_cu_first_name').val());
        const email     = $.trim($('#aura_cu_email').val());
        const userLogin = $.trim($('#aura_cu_user_login').val());
        const pwd       = $('#aura_cu_password').val();

        if (!first || !email || !pwd) {
            $err.text(i18n.requiredFields || 'Nombre, email y contraseña son obligatorios.').show();
            return;
        }

        const $btn = $(this).prop('disabled', true).text(i18n.creating || 'Creando...');

        $.post(data.ajaxUrl || ajaxurl, {
            action:     'aura_create_user',
            nonce:      data.nonce,
            first_name: first,
            last_name:  $.trim($('#aura_cu_last_name').val()),
            email:      email,
            user_login: userLogin,
            phone:      $.trim($('#aura_cu_phone').val()),
            password:   pwd
        }, function(res) {
            if (res.success) {
                window.location.href = res.data.redirect_url;
            } else {
                $err.text(res.data.message).show();
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + (i18n.createAndAssign || 'Crear y asignar permisos'));
            }
        }).fail(function() {
            $err.text(i18n.connError || 'Error de conexión. Inténtalo de nuevo.').show();
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + (i18n.createAndAssign || 'Crear y asignar permisos'));
        });
    });

    // ==========================================================================
    // 6. ACCORDION CAPABILITIES & BUSCADOR
    // ==========================================================================
    $(document).on('click', '.aura-perm-module-header', function() {
        const $module = $(this).closest('.aura-perm-module');
        $module.toggleClass('is-open');
        $(this).attr('aria-expanded', $module.hasClass('is-open') ? 'true' : 'false');
    });

    $(document).on('keydown', '.aura-perm-module-header', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    $('#aura-expand-all-btn').on('click', function() {
        $('.aura-perm-module').addClass('is-open');
        $('.aura-perm-module-header').attr('aria-expanded', 'true');
    });

    $('#aura-collapse-all-btn').on('click', function() {
        $('.aura-perm-module').removeClass('is-open');
        $('.aura-perm-module-header').attr('aria-expanded', 'false');
    });

    // Buscador de capabilities
    $('#aura-cap-search').on('input', function() {
        const term = $(this).val().toLowerCase().trim();
        let totalVisible = 0;
        const $count = $('#aura-search-count');

        if (!term) {
            $('.aura-perm-cap-item').removeClass('is-hidden-by-search');
            $('.aura-perm-module').removeClass('is-hidden-by-search');
            $('.aura-perm-group-header').removeClass('is-hidden-by-search');
            $count.hide();
            refreshAllBadges();
            return;
        }

        $('.aura-perm-module').each(function() {
            const $module = $(this);
            let moduleVisible = 0;

            $module.find('.aura-perm-cap-item').each(function() {
                const label = $(this).attr('data-cap-label') || $(this).find('.aura-perm-cap-text').text().toLowerCase();
                if (label.indexOf(term) !== -1) {
                    $(this).removeClass('is-hidden-by-search');
                    moduleVisible++;
                    totalVisible++;
                } else {
                    $(this).addClass('is-hidden-by-search');
                }
            });

            if (moduleVisible === 0) {
                $module.addClass('is-hidden-by-search');
            } else {
                $module.removeClass('is-hidden-by-search').addClass('is-open');
            }
        });

        $('.aura-perm-group-header').each(function() {
            const groupKey = $(this).data('group');
            const hasVisible = $('.aura-perm-module[data-group="' + groupKey + '"]:not(.is-hidden-by-search)').length > 0;
            $(this).toggleClass('is-hidden-by-search', !hasVisible);
        });

        if (totalVisible > 0) {
            $count.text(totalVisible + ' ' + (i18n.results || 'resultados')).show();
        } else {
            $count.text(i18n.noResults || 'Sin resultados').show();
        }
    });

    // Select-All por Módulo
    $(document).on('change', '.select-all-module', function() {
        const moduleKey = $(this).data('module');
        const checked   = $(this).is(':checked');

        $('input[name="capabilities[]"][data-module="' + moduleKey + '"]').prop('checked', checked).each(function() {
            $(this).closest('.aura-perm-cap-item').toggleClass('is-active', checked);
        });

        refreshModuleBadge($(this).closest('.aura-perm-module'));
        updateStickyCount();
    });

    // Cambio individual de Capability
    $(document).on('change', 'input[name="capabilities[]"]', function() {
        const $module = $(this).closest('.aura-perm-module');
        const $item   = $(this).closest('.aura-perm-cap-item');
        $item.toggleClass('is-active', $(this).is(':checked'));
        refreshModuleBadge($module);
        updateStickyCount();
    });

    // Checkbox de Asignación de Áreas
    $(document).on('change', '.aura-area-checkbox', function() {
        const $card = $(this).closest('.aura-area-assign-card');
        $card.toggleClass('is-assigned', $(this).is(':checked'));
    });

    // ==========================================================================
    // 7. HELPERS BADGES Y STICKY SAVE
    // ==========================================================================
    function refreshModuleBadge($module) {
        const $allCaps = $module.find('input[name="capabilities[]"]');
        const total    = $allCaps.length;
        const active   = $allCaps.filter(':checked').length;
        const $badge   = $module.find('.aura-perm-module-badge');
        const $selAll  = $module.find('.select-all-module');

        if (active > 0) {
            $badge.text(active + '/' + total).addClass('aura-perm-module-badge--active');
        } else {
            $badge.text(total).removeClass('aura-perm-module-badge--active');
        }

        $selAll.prop('checked', active === total && total > 0);
        $selAll.prop('indeterminate', active > 0 && active < total);
    }

    function refreshAllBadges() {
        $('.aura-perm-module').each(function() { refreshModuleBadge($(this)); });
    }

    function updateStickyCount() {
        const total = $('input[name="capabilities[]"]').filter(':checked').length;
        const label = total === 1 ? (i18n.permActive || 'permiso activo') : (i18n.permsActive || 'permisos activos');
        $('#aura-perm-sticky-total').html('<strong>' + total + '</strong> ' + label);
    }

    // Inicializar estado indeterminate
    $('.aura-perm-module').each(function() {
        const $allCaps = $(this).find('input[name="capabilities[]"]');
        const total    = $allCaps.length;
        const active   = $allCaps.filter(':checked').length;
        if (active > 0 && active < total) {
            $(this).find('.select-all-module').prop('indeterminate', true);
        }
    });

    // Sticky Save Bar Scroll
    const $stickyBar = $('#aura-perm-sticky-save');
    if ($stickyBar.length) {
        $(window).on('scroll.stickyPerm', function() {
            $stickyBar.toggleClass('is-visible', $(this).scrollTop() > 250);
        });
    }

    // ==========================================================================
    // 8. PLANTILLAS DE PERFILES (FILTROS Y APLICACIÓN)
    // ==========================================================================
    let currentFilter = 'all';
    let currentSearch = '';

    function filterTemplateCards() {
        let visibleCount = 0;
        const $cards = $('.aura-template-card');
        const totalTemplates = $cards.length;

        $cards.each(function() {
            const $card = $(this);
            const category = $card.attr('data-category');
            const keywords = ($card.attr('data-keywords') || '').toLowerCase();

            const matchesCat = (currentFilter === 'all' || category === currentFilter);
            const matchesSearch = (!currentSearch || keywords.indexOf(currentSearch) !== -1);

            if (matchesCat && matchesSearch) {
                $card.fadeIn(150);
                visibleCount++;
            } else {
                $card.hide();
            }
        });

        if (visibleCount === 0) {
            $('#aura-template-empty').fadeIn(150);
        } else {
            $('#aura-template-empty').hide();
        }

        const indicator = visibleCount === totalTemplates 
            ? (i18n.showingTemplates || 'Mostrando %d perfiles estándar').replace('%d', visibleCount)
            : (i18n.showingTemplatesOf || 'Mostrando %d de %d perfiles').replace('%d', visibleCount).replace('%d', totalTemplates);
        $('#aura-templates-count-indicator').text(indicator);
    }

    $('.aura-template-filter-btn').on('click', function(e) {
        e.preventDefault();
        $('.aura-template-filter-btn').removeClass('is-active');
        $(this).addClass('is-active');
        currentFilter = $(this).attr('data-filter') || 'all';
        filterTemplateCards();
    });

    $('#aura-template-search').on('input', function() {
        currentSearch = $.trim($(this).val().toLowerCase());
        filterTemplateCards();
    });

    function applyTemplateToFormCheckboxes(tpl) {
        if (!tpl || !tpl.capabilities) return;
        $('input[name="capabilities[]"]').prop('checked', false);
        $('.aura-perm-cap-item').removeClass('is-active');

        tpl.capabilities.forEach(function(cap) {
            const $cb = $('#cap_' + cap);
            if ($cb.length) {
                $cb.prop('checked', true);
                $cb.closest('.aura-perm-cap-item').addClass('is-active');
                $cb.closest('.aura-perm-module').addClass('is-open');
            }
        });

        refreshAllBadges();
        updateStickyCount();
    }

    window.applyTemplate = function(templateId) {
        const templates = window.auraProfileTemplatesJson || {};
        const tpl = templates[templateId];
        if (!tpl) {
            alert('Plantilla no encontrada: ' + templateId);
            return;
        }

        const tplName = tpl.name || templateId;
        const tplCount = (tpl.capabilities) ? tpl.capabilities.length : 0;

        // Determinar usuario seleccionado
        let selectedUserId = parseInt(data.selectedUserId, 10);
        if (!selectedUserId || isNaN(selectedUserId)) {
            const $hiddenUserId = $('input[name="user_id"]');
            if ($hiddenUserId.length) {
                selectedUserId = parseInt($hiddenUserId.val(), 10);
            }
        }
        if (!selectedUserId || isNaN(selectedUserId)) {
            const urlParams = new URLSearchParams(window.location.search);
            selectedUserId = parseInt(urlParams.get('user_id'), 10) || 0;
        }

        const selectedUserName = data.selectedUserName || 'el usuario seleccionado';

        // Si NO hay usuario seleccionado, orientar al usuario a seleccionar uno
        if (!selectedUserId) {
            const msg = 'Debe seleccionar primero un usuario activo para poder aplicarle este perfil predefinido.\n\n¿Desea ir a la lista de usuarios activos ahora?';
            if (confirm(msg)) {
                switchTab('active-users');
            }
            return;
        }

        const confirmMsg = '¿Deseas aplicar la plantilla "' + tplName + '" (' + tplCount + ' permisos) a ' + selectedUserName + '?\n\nEsta acción asignará y guardará directamente los permisos específicos de este perfil en el sistema.';

        if (!confirm(confirmMsg)) {
            return;
        }

        // Estado visual de carga en el botón
        const $card = $('.aura-template-card[data-template-id="' + templateId + '"]');
        const $btn = $card.find('.aura-template-card__btn');
        const origBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> <span>Aplicando...</span>');

        // Llamada AJAX para guardar formalmente los capabilities en la base de datos
        $.ajax({
            url: data.ajaxUrl || ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_apply_profile_template',
                nonce: data.permissionsNonce,
                user_id: selectedUserId,
                template_id: templateId
            },
            success: function(response) {
                if (response && response.success) {
                    // Actualizar también los checkboxes en el formulario si está cargado
                    applyTemplateToFormCheckboxes(tpl);

                    if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                        window.AuraUI.showToast(response.data.message || 'Plantilla aplicada exitosamente.', 'success');
                    }

                    const targetUrl = (response.data && response.data.redirect_url) 
                        ? response.data.redirect_url 
                        : (window.location.pathname + '?page=aura-permissions&user_id=' + selectedUserId + '&tab=user-permissions&updated=true');

                    setTimeout(function() {
                        window.location.href = targetUrl;
                    }, 400);
                } else {
                    const errorMsg = (response && response.data && response.data.message) ? response.data.message : 'No se pudo aplicar la plantilla.';
                    if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                        window.AuraUI.showToast(errorMsg, 'error');
                    } else {
                        alert('Error: ' + errorMsg);
                    }
                    $btn.prop('disabled', false).html(origBtnHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al aplicar plantilla:', error);
                // Fallback si falla la red: marcar checkboxes locales
                if ($('#aura-perm-caps-section').length) {
                    applyTemplateToFormCheckboxes(tpl);
                    switchTab('user-permissions');
                    alert('Se seleccionaron los permisos de la plantilla en el formulario. Haga clic en "Guardar Permisos" para persistir los cambios.');
                } else {
                    alert('Error de conexión al aplicar la plantilla. Verifique su conexión y vuelva a intentar.');
                }
                $btn.prop('disabled', false).html(origBtnHtml);
            }
        });
    };

    // ==========================================================================
    // 7. VINCULACIÓN DIRECTA DE ESTUDIANTE AURA DESDE USUARIO WP
    // ==========================================================================
    $(document).on('click', '.btn-sync-wp-as-student', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const userId = $btn.data('user-id');
        const userName = $btn.data('user-name') || 'el usuario';

        if (!confirm('¿Desea registrar o vincular a "' + userName + '" como Estudiante en Aura Suite?\n\nSe creará su expediente académico y se le asignará el rol de estudiante.')) {
            return;
        }

        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;visibility:visible;"></span> Sincronizando...');

        $.ajax({
            url: data.ajaxUrl || ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_students_sync_from_wp_user',
                nonce: data.permissionsNonce || data.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response && response.success) {
                    if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                        window.AuraUI.showToast(response.data.message || 'Usuario vinculado como estudiante con éxito.', 'success');
                    } else {
                        alert(response.data.message || 'Usuario vinculado como estudiante con éxito.');
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    const errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Error al vincular el usuario como estudiante.';
                    if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
                        window.AuraUI.showToast(errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                    $btn.prop('disabled', false).html(origHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al sincronizar estudiante:', error);
                alert('Error de conexión al sincronizar con el módulo de estudiantes.');
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    });
});

