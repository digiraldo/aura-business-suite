/**
 * Aura Business Suite — Script del Directorio Maestro de Contactos y Google Sync
 *
 * Administra el estado interactivo, búsqueda reactiva, paginación, modales,
 * sincronización individual/masiva con Google People API y vinculación de Terceros/WP Users.
 */

(function($) {
    'use strict';

    var cfg = window.auraContactsData || {};

    var state = {
        page: 1,
        perPage: 25,
        search: '',
        category: '',
        syncStatus: '',
        activeTab: 'directory',
        totalItems: 0,
        totalPages: 1,
        items: [],
        isLoading: false
    };

    var searchTimer = null;

    var categoryIcons = {
        student: '🎓',
        teacher: '👨‍🏫',
        staff: '👔',
        donor: '🤝',
        supplier: '🏢',
        member: '⭐',
        volunteer: '🤲',
        facilitator: '🎙️',
        other: '🏷️'
    };

    // ── Inicialización al cargar el DOM ──────────────────────────────────────────
    $(document).ready(function() {
        initTabs();
        initFilters();
        initModals();
        initGoogleSettings();
        initImportExport();
        initDuplicatesMerge();
        initGlobalActions();
        initBulkEdit();

        // Cargar primera página de contactos
        fetchContacts();

        // Verificar si la URL trae un hash directo (#new, #sync, #google, #import, #duplicates)
        handleUrlHash();
    });

    function handleUrlHash() {
        var hash = window.location.hash;
        if (hash === '#new') {
            openCreateModal();
        } else if (hash === '#sync') {
            switchTab('sync_logs');
        } else if (hash === '#google') {
            switchTab('google_settings');
        } else if (hash === '#import') {
            switchTab('import_export');
        } else if (hash === '#duplicates') {
            switchTab('duplicates_merge');
            $('#btn-scan-duplicates').trigger('click');
        }
    }

    // ── Control de Pestañas (Tabs) ───────────────────────────────────────────────
    function initTabs() {
        $('.aura-contacts-navbar .aura-tab-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var tabTarget = $btn.data('tab');
            var catFilter = $btn.data('category');

            $('.aura-contacts-navbar .aura-tab-btn').removeClass('is-active active');
            $btn.addClass('is-active active');

            if (tabTarget === 'directory') {
                state.category = (typeof catFilter !== 'undefined') ? catFilter : '';
                $('#contacts-filter-category').val(state.category);
                state.page = 1;
                switchTab('directory');
                fetchContacts();
            } else if (tabTarget === 'sync_logs') {
                switchTab('sync_logs');
                fetchLogs();
            } else if (tabTarget === 'google_settings') {
                switchTab('google_settings');
            } else if (tabTarget === 'import_export') {
                switchTab('import_export');
            } else if (tabTarget === 'duplicates_merge') {
                switchTab('duplicates_merge');
            }
        });
    }

    function switchTab(tabKey) {
        state.activeTab = tabKey;
        $('.contacts-tab-content').hide().removeClass('is-active');
        $('#contacts-tab-' + tabKey).show().addClass('is-active');
    }

    // ── Filtros y Búsqueda Reactiva ──────────────────────────────────────────────
    function initFilters() {
        $('#contacts-search-input').on('input', function() {
            var val = $(this).val().trim();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                state.search = val;
                state.page = 1;
                fetchContacts();
            }, 300);
        });

        $('#contacts-filter-sync').on('change', function() {
            state.syncStatus = $(this).val();
            state.page = 1;
            fetchContacts();
        });

        $('#contacts-filter-category').on('change', function() {
            state.category = $(this).val();
            state.page = 1;
            // Actualizar estilo visual en navbar
            $('.aura-contacts-navbar .aura-tab-btn').removeClass('is-active active');
            var $matchingTab = $('.aura-contacts-navbar .aura-tab-btn[data-category="' + state.category + '"]');
            if ($matchingTab.length) {
                $matchingTab.addClass('is-active active');
            } else {
                $('.aura-contacts-navbar .aura-tab-btn[data-category=""]').addClass('is-active active');
            }
            fetchContacts();
        });

        $('#contacts-per-page').on('change', function() {
            state.perPage = parseInt($(this).val(), 10) || 25;
            state.page = 1;
            fetchContacts();
        });

        $('#btn-refresh-list').on('click', function() {
            fetchContacts();
        });

        // Clic en tarjetas KPI para filtrar automáticamente el directorio
        $('.kpi-card.accent-rose, #kpi-error-val').css('cursor', 'pointer').on('click', function() {
            $('.aura-navbar-item[data-tab="directory"]').trigger('click');
            $('#contacts-filter-sync').val('error').trigger('change');
        });

        $('.kpi-card.accent-amber, #kpi-pending-val').css('cursor', 'pointer').on('click', function() {
            $('.aura-navbar-item[data-tab="directory"]').trigger('click');
            $('#contacts-filter-sync').val('pending').trigger('change');
        });

        $('.kpi-card.accent-emerald, #kpi-synced-val').css('cursor', 'pointer').on('click', function() {
            $('.aura-navbar-item[data-tab="directory"]').trigger('click');
            $('#contacts-filter-sync').val('synced').trigger('change');
        });
    }

    // ── Obtener Listado de Contactos vía AJAX ────────────────────────────────────
    function fetchContacts() {
        if (state.isLoading) return;
        state.isLoading = true;

        var $tbody = $('#contacts-tbody');
        $tbody.html('<tr><td colspan="8" style="text-align: center; padding: 36px;"><span class="dashicons dashicons-update spin" style="font-size:24px;width:24px;height:24px;"></span><p style="margin:8px 0 0; color:#94a3b8;">Cargando contactos...</p></td></tr>');

        $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_contacts_get_list',
                nonce: cfg.nonce,
                page: state.page,
                per_page: state.perPage,
                search: state.search,
                category: state.category,
                sync_status: state.syncStatus
            },
            success: function(res) {
                state.isLoading = false;
                if (!res.success) {
                    $tbody.html('<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 24px;">' + (res.data && res.data.message ? res.data.message : 'Error al cargar contactos') + '</td></tr>');
                    return;
                }

                var data = res.data || {};
                state.items = data.items || [];
                state.totalItems = data.total || 0;
                state.totalPages = data.pages || 1;

                renderTableRows(state.items);
                renderPagination(data);
                refreshStats();
            },
            error: function() {
                state.isLoading = false;
                $tbody.html('<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 24px;">' + (cfg.i18n.connError || 'Error de conexión con el servidor') + '</td></tr>');
            }
        });
    }

    // ── Renderizado de Filas de la Tabla ─────────────────────────────────────────
    function renderTableRows(items) {
        var $tbody = $('#contacts-tbody');
        if (!items || items.length === 0) {
            $tbody.html('<tr><td colspan="8" style="text-align: center; padding: 48px; color: #94a3b8;"><span style="font-size: 32px;">📇</span><p style="margin: 8px 0 0; font-weight: 600;">No se encontraron contactos</p><p style="margin: 4px 0 0; font-size: 0.8rem;">Prueba con otros términos de búsqueda o registra un nuevo contacto.</p></td></tr>');
            return;
        }

        var html = '';
        items.forEach(function(item) {
            var fullName = escapeHtml(item.first_name + (item.last_name ? ' ' + item.last_name : ''));
            var initials = escapeHtml(((item.first_name || '').charAt(0) + (item.last_name || '').charAt(0)).toUpperCase() || 'C');
            var catKey = item.category || 'other';
            var catLabel = cfg.categories[catKey] || catKey;
            var catIcon = categoryIcons[catKey] || '🏷️';

            // Avatar: foto personalizada, avatar de WP / Gravatar o iniciales con fallback automático
            var avatarUrl = item.avatar_url || item.photo_url || '';
            if (avatarUrl) {
                // Eliminar cualquier residuo de :/ o :// malformado
                if (avatarUrl.indexOf(':/wp-content') !== -1 || avatarUrl.indexOf('/:/') !== -1 || avatarUrl.indexOf(':/') === 0) {
                    avatarUrl = avatarUrl.replace('/:/', '/').replace(':/wp-content', '/wp-content');
                    if (avatarUrl.indexOf('http') !== 0) {
                        var baseUrl = window.location.origin;
                        avatarUrl = baseUrl + '/' + avatarUrl.replace(/^[:\/]+/, '');
                    }
                }
            }
            var avatarHtml = '';
            if (avatarUrl) {
                avatarHtml = '<img src="' + escapeHtml(avatarUrl) + '" alt="' + fullName + '" class="contact-avatar-img" onerror="this.style.display=\'none\'; if(this.nextElementSibling) this.nextElementSibling.style.display=\'flex\';"><div class="contact-avatar-initials" style="display:none;">' + initials + '</div>';
            } else {
                avatarHtml = '<div class="contact-avatar-initials">' + initials + '</div>';
            }

            // Datos de contacto normalizados
            var emailVal = item.email || item.email_primary || '';
            var phoneVal = item.phone || item.phone_primary || item.whatsapp || '';

            // Sync Badge
            var syncBadge = renderSyncBadge(item);

            // Vinculaciones
            var linkBadges = '';
            if (item.third_party_id && parseInt(item.third_party_id, 10) > 0) {
                linkBadges += '<span class="entity-link-badge entity-link-badge--tp" title="Vinculado a Tercero ID #' + item.third_party_id + '">🏢 Tercero</span>';
            }
            if (item.wp_user_id && parseInt(item.wp_user_id, 10) > 0) {
                linkBadges += '<span class="entity-link-badge entity-link-badge--wp" title="Vinculado a Usuario WP ID #' + item.wp_user_id + '">👤 Usuario WP</span>';
            }
            if (!linkBadges) {
                linkBadges = '<span style="color:#64748b;font-size:0.75rem;">Sin vincular</span>';
            }

            // Acciones
            var actionsHtml = '<div style="display:flex; justify-content:flex-end; gap:6px;">';
            if (cfg.canEdit) {
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.editContact(' + item.id + ')" title="Editar contacto"><span class="dashicons dashicons-edit"></span></button>';
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.openLinkEntityModal(' + item.id + ', \'' + fullName.replace(/'/g, "\\'") + '\')" title="Vincular a Tercero / Usuario"><span class="dashicons dashicons-admin-links"></span></button>';
            }
            if (cfg.canSync && cfg.oauthConnected) {
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.syncSingleContact(' + item.id + ')" title="Sincronizar con Google"><span class="dashicons dashicons-cloud"></span></button>';
            }
            if (cfg.canDelete) {
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.deleteContact(' + item.id + ')" title="Mover a papelera" style="color:#f87171;"><span class="dashicons dashicons-trash"></span></button>';
            }
            actionsHtml += '</div>';

            html += '<tr data-contact-id="' + item.id + '">';
            html += '<td style="text-align: center;"><input type="checkbox" class="aura-checkbox contact-row-check" value="' + item.id + '"></td>';
            html += '<td><div class="contact-avatar-wrap">' + avatarHtml + '<div><div class="contact-name-main">' + fullName + '</div>' + (item.tags ? '<div class="contact-name-sub">' + renderTags(item.tags) + '</div>' : '') + '</div></div></td>';
            html += '<td><span class="category-pill category-pill--' + catKey + '">' + catIcon + ' ' + escapeHtml(catLabel) + '</span></td>';
            html += '<td><div style="font-weight:600;font-size:0.84rem;">' + (emailVal ? '<a href="mailto:' + escapeHtml(emailVal) + '" style="color:#38bdf8;text-decoration:none;">' + escapeHtml(emailVal) + '</a>' : '<span style="color:#64748b;">—</span>') + '</div><div style="font-size:0.76rem;color:#94a3b8;margin-top:2px;">' + (phoneVal ? escapeHtml(phoneVal) : '') + '</div></td>';
            html += '<td><div>' + (item.organization ? escapeHtml(item.organization) : '<span style="color:#64748b;">—</span>') + '</div><div style="font-size:0.75rem;color:#94a3b8;">' + (item.job_title ? escapeHtml(item.job_title) : '') + '</div></td>';
            html += '<td>' + linkBadges + '</td>';
            html += '<td>' + syncBadge + '</td>';
            html += '<td style="text-align: right;">' + actionsHtml + '</td>';
            html += '</tr>';
        });

        $tbody.html(html);

        $('#contacts-select-all').prop('checked', false);
        if (typeof updateBulkState === 'function') {
            updateBulkState();
        }
    }

    function renderSyncBadge(item) {
        var status = item.sync_status || 'not_synced';
        if (status === 'synced') {
            return '<span class="sync-badge sync-badge--synced" title="Sincronizado con Google People API"><span class="dashicons dashicons-yes-alt" style="font-size:13px;width:13px;height:13px;"></span> Sincronizado</span>';
        } else if (status === 'pending') {
            return '<span class="sync-badge sync-badge--pending" title="Cambios locales pendientes de subir a Google"><span class="dashicons dashicons-update" style="font-size:13px;width:13px;height:13px;"></span> Pendiente</span>';
        } else if (status === 'error') {
            return '<span class="sync-badge sync-badge--error" title="' + escapeHtml(item.last_sync_error || 'Error de sincronización') + '"><span class="dashicons dashicons-warning" style="font-size:13px;width:13px;height:13px;"></span> Error</span>';
        }
        return '<span class="sync-badge sync-badge--not_synced"><span class="dashicons dashicons-minus" style="font-size:13px;width:13px;height:13px;"></span> No vinculado</span>';
    }

    function renderTags(tags) {
        if (!tags) return '';
        var arr = [];
        if (typeof tags === 'string') {
            try { arr = JSON.parse(tags); } catch(e) { arr = tags.split(','); }
        } else if (Array.isArray(tags)) {
            arr = tags;
        }
        if (!Array.isArray(arr) || arr.length === 0) return '';
        return arr.slice(0, 3).map(function(t) {
            return '<span style="display:inline-block;padding:1px 5px;background:rgba(255,255,255,0.06);border-radius:4px;font-size:0.7rem;margin-right:3px;">#' + escapeHtml(String(t).trim()) + '</span>';
        }).join('');
    }

    // ── Paginación Robusta e Interactiva ───────────────────────────────────────
    function renderPagination(data) {
        var total = parseInt(data.total, 10);
        if (isNaN(total) || total < 0) total = 0;

        var perPage = parseInt(data.per_page || state.perPage, 10) || 25;
        state.perPage = perPage;

        // Calcular número real de páginas
        var calculatedPages = Math.ceil(total / perPage);
        var pages = parseInt(data.pages || data.total_pages, 10);
        if (isNaN(pages) || pages < 1 || pages < calculatedPages) {
            pages = calculatedPages;
        }
        if (pages < 1) pages = 1;
        state.totalPages = pages;

        // Normalizar página activa
        var currentPage = parseInt(data.page || state.page, 10) || 1;
        if (currentPage > pages) currentPage = pages;
        if (currentPage < 1) currentPage = 1;
        state.page = currentPage;

        var start = total > 0 ? (state.page - 1) * state.perPage + 1 : 0;
        var end = Math.min(state.page * state.perPage, total);
        $('#contacts-pagination-info').text('Mostrando ' + start + ' - ' + end + ' de ' + total + ' contactos');

        var $controls = $('#contacts-pagination-controls').empty();

        if (total === 0) {
            return;
        }

        // Botón Anterior
        var prevDisabled = (state.page <= 1);
        var $prev = $('<button type="button" class="btn btn-glass btn-xs' + (prevDisabled ? ' disabled' : '') + '" ' + 
            (prevDisabled ? 'disabled style="opacity:0.35;cursor:not-allowed;"' : 'style="cursor:pointer;"') + 
            ' title="Página anterior"><span class="dashicons dashicons-arrow-left-alt2"></span></button>');
        
        $prev.on('click', function(e) {
            e.preventDefault();
            if (state.page > 1 && !state.isLoading) {
                state.page--;
                fetchContacts();
            }
        });
        $controls.append($prev);

        // Páginas numeradas
        var maxButtons = 5;
        var startPage = Math.max(1, state.page - 2);
        var endPage = Math.min(pages, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        if (startPage > 1) {
            var $pFirst = $('<button type="button" class="btn btn-glass btn-xs" style="cursor:pointer;">1</button>');
            $pFirst.on('click', function(e) {
                e.preventDefault();
                if (state.page !== 1 && !state.isLoading) {
                    state.page = 1;
                    fetchContacts();
                }
            });
            $controls.append($pFirst);
            if (startPage > 2) {
                $controls.append('<span style="padding: 0 4px; color: #94a3b8; user-select:none;">...</span>');
            }
        }

        for (var p = startPage; p <= endPage; p++) {
            var isCurrent = (p === state.page);
            var $pBtn = $('<button type="button" class="btn btn-xs ' + (isCurrent ? 'btn-primary' : 'btn-glass') + '" ' + 
                (isCurrent ? 'style="font-weight:700;box-shadow:0 0 10px rgba(59,130,246,0.5);cursor:default;"' : 'style="cursor:pointer;"') + 
                '>' + p + '</button>');

            (function(pageIndex) {
                $pBtn.on('click', function(e) {
                    e.preventDefault();
                    if (state.page !== pageIndex && !state.isLoading) {
                        state.page = pageIndex;
                        fetchContacts();
                    }
                });
            })(p);

            $controls.append($pBtn);
        }

        if (endPage < pages) {
            if (endPage < pages - 1) {
                $controls.append('<span style="padding: 0 4px; color: #94a3b8; user-select:none;">...</span>');
            }
            var $pLast = $('<button type="button" class="btn btn-glass btn-xs" style="cursor:pointer;">' + pages + '</button>');
            $pLast.on('click', function(e) {
                e.preventDefault();
                if (state.page !== pages && !state.isLoading) {
                    state.page = pages;
                    fetchContacts();
                }
            });
            $controls.append($pLast);
        }

        // Botón Siguiente
        var nextDisabled = (state.page >= pages);
        var $next = $('<button type="button" class="btn btn-glass btn-xs' + (nextDisabled ? ' disabled' : '') + '" ' + 
            (nextDisabled ? 'disabled style="opacity:0.35;cursor:not-allowed;"' : 'style="cursor:pointer;"') + 
            ' title="Página siguiente"><span class="dashicons dashicons-arrow-right-alt2"></span></button>');
        
        $next.on('click', function(e) {
            e.preventDefault();
            if (state.page < pages && !state.isLoading) {
                state.page++;
                fetchContacts();
            }
        });
        $controls.append($next);
    }

    // ── Actualizar Estadísticas y Contadores ─────────────────────────────────────
    function refreshStats() {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_get_stats', nonce: cfg.nonce }, function(res) {
            if (res.success && res.data) {
                var s = res.data;
                var total = s.total || s.total_active || 0;
                $('#header-cnt-total').text(total);
                $('#kpi-total-val').text(total);
                $('#kpi-synced-val').text(s.synced || 0);
                $('#kpi-pending-val').text(s.pending || 0);
                $('#kpi-error-val').text(s.error || s.errors || 0);
                $('#kpi-third-val').text(s.with_third_party || 0);
                $('#tab-count-all').text(total);

                if (s.by_category) {
                    $('#tab-count-student').text(s.by_category.student || 0);
                    $('#tab-count-teacher').text((s.by_category.teacher || 0) + (s.by_category.facilitator || 0));
                    $('#tab-count-staff').text(s.by_category.staff || 0);
                    $('#tab-count-supplier').text(s.by_category.supplier || 0);
                    $('#tab-count-donor').text(s.by_category.donor || 0);
                    $('#tab-count-member').text(s.by_category.member || 0);
                }
            }
        });
    }

    // ── Helper de UI para Tipo de Contacto ────────────────────────────────────────
    function setContactType(type) {
        if (!type || (type !== 'company' && type !== 'organization')) {
            type = 'person';
        }
        $('#contact-type-input').val(type);
        $('.contact-type-btn').removeClass('is-active');
        $('.contact-type-btn[data-type="' + type + '"]').addClass('is-active');

        var $iconBadge = $('#contact-modal-icon-badge');
        var $firstLabel = $('#contact-label-first-name');
        var $orgLabel = $('#contact-label-organization');

        if (type === 'company' || type === 'organization') {
            $iconBadge.html('<span class="dashicons dashicons-building"></span>');
            $firstLabel.html((cfg.i18n.contactPerson || 'Representante / Contacto directo') + ' <span style="font-size:0.75rem;color:#94a3b8;font-weight:normal;">(Opcional)</span>');
            $orgLabel.html((type === 'company' ? 'Razón Social / Empresa' : 'Nombre de la Organización / Institución') + ' <span style="color:#ef4444;">*</span>');
            $('#contact-first-name').prop('required', false);
            $('#contact-organization').prop('required', true);
        } else {
            $iconBadge.html('<span class="dashicons dashicons-id-alt"></span>');
            $firstLabel.html((cfg.i18n.firstName || 'Nombre(s)') + ' <span style="color:#ef4444;">*</span>');
            $orgLabel.html(cfg.i18n.organization || 'Organización / Empresa');
            $('#contact-first-name').prop('required', true);
            $('#contact-organization').prop('required', false);
        }
        updateAvatarPreview();
    }

    // ── Helper de Iniciales y Preview de Avatar ───────────────────────────────────
    function computeInitials(str1, str2) {
        var a = (str1 || '').trim().charAt(0);
        var b = (str2 || '').trim().charAt(0);
        if (!a && !b) return 'AU';
        return (a + b).toUpperCase();
    }

    function updateAvatarPreview() {
        var customUrl = ($('#contact-avatar-url').val() || '').trim();
        var $img = $('#contact-avatar-preview-img');
        var $initials = $('#contact-avatar-preview-initials');
        var $btnRemove = $('#btn-remove-contact-avatar');

        if (customUrl) {
            $img.attr('src', customUrl).show();
            $initials.hide();
            $btnRemove.show();
        } else {
            $img.attr('src', '').hide();
            $btnRemove.hide();
            var fName = $('#contact-first-name').val();
            var lName = $('#contact-last-name').val();
            var org = $('#contact-organization').val();
            var ini = '';
            if (fName || lName) {
                ini = computeInitials(fName, lName);
            } else if (org) {
                var parts = org.trim().split(/\s+/);
                ini = computeInitials(parts[0], parts[1] || '');
            } else {
                ini = 'AU';
            }
            $initials.text(ini).show();
        }
    }

    // ── Gestión del Modal de Contacto (Crear / Editar) ───────────────────────────
    var mediaFrame = null;

    function initModals() {
        $('#btn-open-create-modal').on('click', function() {
            openCreateModal();
        });

        $('#contact-edit-form').on('submit', function(e) {
            e.preventDefault();
            saveContact();
        });

        // Mini-Tabs del Modal
        $('.contact-modal-tab-btn').on('click', function(e) {
            e.preventDefault();
            $('.contact-modal-tab-btn').removeClass('is-active');
            $(this).addClass('is-active');
            var targetPane = $(this).data('tab-target');
            $('.contact-modal-tab-pane').hide();
            $(targetPane).fadeIn(150);
        });

        // Selector de Tipo de Contacto
        $('.contact-type-btn').on('click', function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            setContactType(type);
        });

        // Escucha cambios de texto para iniciales en tiempo real
        $('#contact-first-name, #contact-last-name, #contact-organization').on('input', function() {
            if (!$('#contact-avatar-url').val()) {
                updateAvatarPreview();
            }
        });

        // Media Uploader de WordPress para Avatar
        $('#btn-upload-contact-avatar').on('click', function(e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) {
                var manualUrl = prompt('Ingresa la URL de la foto o avatar del contacto:');
                if (manualUrl) {
                    $('#contact-avatar-url').val(manualUrl.trim());
                    updateAvatarPreview();
                }
                return;
            }

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: 'Seleccionar Foto o Logotipo del Contacto',
                button: { text: 'Usar como Avatar' },
                multiple: false,
                library: { type: 'image' }
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                var selectedUrl = attachment.url;
                if (attachment.sizes && attachment.sizes.thumbnail) {
                    selectedUrl = attachment.sizes.thumbnail.url;
                }
                $('#contact-avatar-url').val(selectedUrl);
                updateAvatarPreview();
            });

            mediaFrame.open();
        });

        $('#btn-remove-contact-avatar').on('click', function(e) {
            e.preventDefault();
            $('#contact-avatar-url').val('');
            updateAvatarPreview();
        });


        // Cerrar modal al hacer clic en el backdrop oscuro
        $('.aura-modal-overlay').on('click', function(e) {
            if ($(e.target).hasClass('aura-modal-overlay')) {
                $(this).removeClass('active').addClass('is-hidden').css({ display: 'none', opacity: 0, visibility: 'hidden' });
            }
        });
    }

    function openCreateModal() {
        $('#contact-modal-title').html('📇 ' + (cfg.i18n.newContact || 'Nuevo Contacto'));
        $('#contact-modal-subtitle').text('Directorio institucional integrado con Google Contacts y el ecosistema Aura Suite.');
        $('#contact-form-id').val(0);
        
        var form = document.getElementById('contact-edit-form');
        if (form && typeof form.reset === 'function') {
            form.reset();
        }

        $('#contact-avatar-url').val('');
        $('#contact-third-party-id').val(0);
        $('#contact-third-party-selected').hide();
        $('#contact-form-create-tp-wrap').hide();
        $('#contact-wp-user-id').val(0);
        $('#contact-wp-user-selected').hide();

        setContactType('person');
        updateAvatarPreview();

        // Resetear a pestaña principal
        $('.contact-modal-tab-btn').removeClass('is-active');
        $('.contact-modal-tab-btn[data-tab-target="#cmodal-tab-general"]').addClass('is-active');
        $('.contact-modal-tab-pane').hide();
        $('#cmodal-tab-general').show();

        $('#modal-contact-form')
            .removeClass('is-hidden')
            .addClass('active')
            .css({ display: 'flex', opacity: 1, visibility: 'visible' });

        setTimeout(function() {
            $('#contact-first-name').focus();
        }, 100);
    }

    function openEditModal(id) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_get_detail', nonce: cfg.nonce, id: id }, function(res) {
            if (!res.success || !res.data) {
                alert('No se pudo cargar la información del contacto.');
                return;
            }
            var c = (res.data && res.data.contact) ? res.data.contact : res.data;
            if (!c || !c.id) {
                alert('No se pudo procesar la información del contacto.');
                return;
            }
            var contactTitle = (c.first_name || '') + ' ' + (c.last_name || '');
            if (!contactTitle.trim()) contactTitle = c.organization || ('Contacto #' + c.id);

            $('#contact-modal-title').html('✏️ ' + 'Editar Contacto: ' + escapeHtml(contactTitle));
            $('#contact-modal-subtitle').text('ID #' + c.id + ' • Sincronización: ' + (c.sync_status || 'pending').toUpperCase());
            
            $('#contact-form-id').val(c.id);
            $('#contact-first-name').val(c.first_name || '');
            $('#contact-last-name').val(c.last_name || '');
            $('#contact-organization').val(c.organization || '');
            $('#contact-job-title').val(c.job_title || '');
            $('#contact-category').val(c.category || 'other');

            $('#contact-email').val(c.email_primary || c.email || '');
            $('#contact-email-secondary').val(c.email_secondary || '');
            $('#contact-phone').val(c.phone_primary || c.phone || '');
            $('#contact-whatsapp').val(c.whatsapp || '');

            $('#contact-address').val(c.address_street || c.address || '');
            $('#contact-address-city').val(c.address_city || '');
            $('#contact-address-state').val(c.address_state || '');
            $('#contact-address-postal').val(c.address_postal || '');

            $('#contact-notes').val(c.notes || '');

            // Avatar
            var avUrl = c.avatar_url || c.photo_url || '';
            $('#contact-avatar-url').val(avUrl);

            // Tipo de contacto
            setContactType(c.contact_type || 'person');
            updateAvatarPreview();

            // Tags
            var tagsStr = '';
            if (Array.isArray(c.tags)) {
                tagsStr = c.tags.join(', ');
            } else if (typeof c.tags === 'string') {
                try { tagsStr = JSON.parse(c.tags).join(', '); } catch(e) { tagsStr = c.tags; }
            }
            $('#contact-tags').val(tagsStr);

            // Tercero vinculado
            if (c.third_party_id && parseInt(c.third_party_id, 10) > 0) {
                $('#contact-third-party-id').val(c.third_party_id);
                $('#contact-third-party-name').text(c.third_party_name || ('Tercero #' + c.third_party_id));
                $('#contact-third-party-selected').show();
                $('#contact-form-create-tp-wrap').hide();
            } else {
                $('#contact-third-party-id').val(0);
                $('#contact-third-party-selected').hide();
                $('#contact-form-create-tp-wrap').show();
            }

            // Usuario WordPress vinculado
            if (c.wp_user_id && parseInt(c.wp_user_id, 10) > 0) {
                $('#contact-wp-user-id').val(c.wp_user_id);
                $('#contact-wp-user-name').text(c.wp_user_name || ('Usuario WP #' + c.wp_user_id));
                $('#contact-wp-user-selected').show();
            } else {
                $('#contact-wp-user-id').val(0);
                $('#contact-wp-user-selected').hide();
            }

            // Pestaña inicial
            $('.contact-modal-tab-btn').removeClass('is-active');
            $('.contact-modal-tab-btn[data-tab-target="#cmodal-tab-general"]').addClass('is-active');
            $('.contact-modal-tab-pane').hide();
            $('#cmodal-tab-general').show();

            $('#modal-contact-form')
                .removeClass('is-hidden')
                .addClass('active')
                .css({ display: 'flex', opacity: 1, visibility: 'visible' });
        });
    }

    function saveContact() {
        var contactType = $('#contact-type-input').val() || 'person';
        var firstName = ($('#contact-first-name').val() || '').trim();
        var org = ($('#contact-organization').val() || '').trim();

        if (contactType === 'person' && !firstName && !org) {
            alert('Por favor ingresa al menos el Nombre del contacto.');
            $('#contact-first-name').focus();
            return;
        }

        if ((contactType === 'company' || contactType === 'organization') && !org && !firstName) {
            alert('Por favor ingresa la Razón Social o Nombre de la Organización.');
            $('#contact-organization').focus();
            return;
        }

        var $btn = $('#btn-save-contact');
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Guardando...');

        var formData = $('#contact-edit-form').serialize();
        formData += '&action=aura_contacts_save&nonce=' + encodeURIComponent(cfg.nonce);

        $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html(originalHtml);
                if (res.success) {
                    closeContactModal();
                    fetchContacts();
                    refreshStats();
                    showToast(cfg.i18n.saveSuccess || 'Contacto guardado correctamente.');
                } else {
                    alert(res.data && res.data.message ? res.data.message : (cfg.i18n.saveError || 'Error al guardar'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
                alert(cfg.i18n.connError || 'Error de conexión');
            }
        });
    }

    window.openCreateModal = openCreateModal;
    window.openEditModal = openEditModal;

    window.closeContactModal = function() {
        $('#modal-contact-form')
            .removeClass('active')
            .addClass('is-hidden')
            .css({ display: 'none', opacity: 0, visibility: 'hidden' });
    };

    window.unlinkThirdPartyInForm = function() {
        $('#contact-third-party-id').val(0);
        $('#contact-third-party-selected').hide();
        if ($('#contact-form-id').val() > 0) {
            $('#contact-form-create-tp-wrap').show();
        }
    };

    window.unlinkWpUserInForm = function() {
        $('#contact-wp-user-id').val(0);
        $('#contact-wp-user-selected').hide();
    };

    // ── Sincronización con Google ────────────────────────────────────────────────
    function initGlobalActions() {
        $('#btn-sync-all, #btn-sync-all-from-logs').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sincronizando...');

            $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_all', nonce: cfg.nonce }, function(res) {
                $btn.prop('disabled', false).html(orig);
                var d = (res.data && res.data.details) ? res.data.details : {};
                var summary = '\n• Subidos a Google: ' + (d.pushed_success || 0) + ' (errores: ' + (d.pushed_errors || 0) + ')' +
                    '\n• Descargados de Google: ' + ((d.pulled_created || 0) + (d.pulled_updated || 0)) + ' (eliminados: ' + (d.pulled_deleted || 0) + ', errores: ' + (d.pulled_errors || 0) + ')';
                if (res.success) {
                    showToast('Sincronización completada.' + summary.replace(/\n/g, ' '));
                } else {
                    var base = (res.data && res.data.message) ? res.data.message : 'Error al sincronizar con Google';
                    var errs = (d.errors && d.errors.length) ? '\n' + d.errors.join('\n') : '';
                    alert(base + (d.message ? '\n' + d.message : '') + errs);
                }
                fetchContacts();
                refreshStats();
                if (state.activeTab === 'sync_logs') {
                    fetchLogs();
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión al sincronizar');
            });
        });

        $('#btn-refresh-logs').on('click', function() {
            fetchLogs();
        });

        // ── Sincronización Manual Lote: Usuarios de WordPress (Fase 4 P2) ──
        $('#btn-sync-wp-users').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sincronizando...');

            $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_wp_users', nonce: cfg.nonce }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    var msg = res.data && res.data.message ? res.data.message : 'Usuarios sincronizados exitosamente.';
                    showToast(msg);
                    fetchContacts();
                    refreshStats();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al sincronizar usuarios de WordPress');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión al sincronizar usuarios de WordPress');
            });
        });

        // ── Sincronización Manual Lote: Terceros de Aura (Fase 4 P2) ──
        $('#btn-sync-third-parties').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sincronizando...');

            $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_third_parties', nonce: cfg.nonce }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    var msg = res.data && res.data.message ? res.data.message : 'Terceros sincronizados exitosamente.';
                    showToast(msg);
                    fetchContacts();
                    refreshStats();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al sincronizar terceros');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión al sincronizar terceros');
            });
        });

        // ── Sincronización Manual Lote: Estudiantes de Aura (Fase 4 P3) ──
        $('#btn-sync-students').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sincronizando...');

            $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_students', nonce: cfg.nonce }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    var msg = res.data && res.data.message ? res.data.message : 'Estudiantes sincronizados exitosamente.';
                    showToast(msg);
                    fetchContacts();
                    refreshStats();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al sincronizar estudiantes');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión al sincronizar estudiantes');
            });
        });
    }

    function syncSingle(id) {
        var $row = $('tr[data-contact-id="' + id + '"]');
        var $btn = $row.find('button[onclick*="syncSingleContact"]');
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');

        $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_single', nonce: cfg.nonce, contact_id: id }, function(res) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            if (res.success) {
                showToast('Contacto sincronizado con Google exitosamente.');
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error al sincronizar contacto con Google');
            }
        }).fail(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            alert('Error de conexión');
        });
    }

    function deleteContactItem(id) {
        if (!confirm(cfg.i18n.confirmDelete || '¿Estás seguro de mover este contacto a la papelera?')) {
            return;
        }

        $.post(cfg.ajaxUrl, { action: 'aura_contacts_delete', nonce: cfg.nonce, id: id, hard: 0 }, function(res) {
            if (res.success) {
                showToast('Contacto movido a la papelera.');
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error al eliminar contacto');
            }
        });
    }

    // ── Historial de Logs ────────────────────────────────────────────────────────
    function fetchLogs() {
        var $tbody = $('#contacts-logs-tbody');
        $tbody.html('<tr><td colspan="6" style="text-align: center; padding: 24px;"><span class="dashicons dashicons-update spin"></span> Cargando historial de sincronización...</td></tr>');

        $.post(cfg.ajaxUrl, { action: 'aura_contacts_get_sync_logs', nonce: cfg.nonce, limit: 150 }, function(res) {
            var logs = (res.data && res.data.logs) ? res.data.logs : (Array.isArray(res.data) ? res.data : []);
            if (!res.success || logs.length === 0) {
                $tbody.html('<tr><td colspan="6" style="text-align: center; padding: 30px; color:#94a3b8;">No hay registros de sincronización registrados aún.</td></tr>');
                return;
            }

            var html = '';
            logs.forEach(function(l) {
                var dirBadge = l.direction === 'push' ? '<span class="badge badge-blue">Local ➔ Google</span>' : '<span class="badge badge-purple">Google ➔ Local</span>';
                var statusBadge = l.status === 'success' ? '<span class="sync-badge sync-badge--synced">Éxito</span>' : '<span class="sync-badge sync-badge--error">Fallo</span>';

                html += '<tr>';
                html += '<td style="font-size:0.8rem;color:#cbd5e1;">' + escapeHtml(l.created_at || '') + '</td>';
                html += '<td>' + dirBadge + '</td>';
                html += '<td style="font-weight:600;font-size:0.82rem;">' + escapeHtml(l.action || '') + '</td>';
                html += '<td>' + (l.contact_id ? '#' + l.contact_id : '—') + '</td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td style="font-size:0.8rem;color:#94a3b8;">' + escapeHtml(l.message || l.error_message || l.details || '—') + '</td>';
                html += '</tr>';
            });
            $tbody.html(html);
        }).fail(function() {
            $tbody.html('<tr><td colspan="6" style="text-align: center; padding: 30px; color:#f87171;">Error de conexión al cargar el historial de sincronización.</td></tr>');
        });
    }

    // ── Configuración Google OAuth & Workspace DWD ──────────────────────────────
    function initGoogleSettings() {
        // Selector de modo de autenticación (OAuth vs Service Account DWD)
        $('#google_auth_mode_selector').on('change', function() {
            var selectedMode = $(this).val();
            $('.google-auth-subpanel').hide();
            $('#auth-panel-' + selectedMode).fadeIn(150);

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_switch_auth_mode',
                nonce: cfg.nonce,
                mode: selectedMode
            }, function(res) {
                if (res.success) {
                    showToast('Modo de autenticación cambiado a ' + (selectedMode === 'service_account' ? 'Google Workspace DWD' : 'OAuth 2.0'));
                }
            });
        });

        // Guardar credenciales OAuth individual
        $('#contacts-google-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#btn-save-google-credentials');
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Guardando...');

            var data = {
                action: 'aura_contacts_save_google_settings',
                nonce: cfg.nonce,
                client_id: $('#google_client_id').val().trim(),
                client_secret: $('#google_client_secret').val().trim()
            };

            $.post(cfg.ajaxUrl, data, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    alert('Credenciales de Google OAuth guardadas exitosamente. La página se recargará para actualizar las URLs de autorización.');
                    window.location.reload();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al guardar credenciales');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión');
            });
        });

        // Guardar credenciales de Service Account DWD
        $('#contacts-sa-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#btn-save-sa-credentials');
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Guardando...');

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_save_sa_settings',
                nonce: cfg.nonce,
                sa_json: $('#sa_json_credentials').val().trim(),
                delegated_user: $('#sa_delegated_user').val().trim()
            }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    alert('Credenciales de Service Account DWD guardadas exitosamente.');
                    window.location.reload();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al guardar credenciales DWD');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión');
            });
        });

        // Probar conexión en vivo de Service Account DWD
        $('#btn-test-sa-connection').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Probando conexión...');

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_test_sa_connection',
                nonce: cfg.nonce
            }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    alert('✅ Conexión con Google People API exitosa:\n' + (res.data.message || 'Token DWD generado y People API accesible'));
                } else {
                    alert('❌ Error al conectar con Google:\n' + (res.data && res.data.message ? res.data.message : 'No se pudo autorizar'));
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión al servidor');
            });
        });

        $('#btn-disconnect-google').on('click', function() {
            if (!confirm(cfg.i18n.confirmDisconnect || '¿Estás seguro de desconectar la cuenta de Google?')) {
                return;
            }
            $.post(cfg.ajaxUrl, { action: 'aura_contacts_disconnect_google', nonce: cfg.nonce }, function(res) {
                if (res.success) {
                    alert('Cuenta de Google desconectada correctamente.');
                    window.location.reload();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al desconectar');
                }
            });
        });

        // Abrir Tutorial de Google OAuth
        $('#btn-open-oauth-tutorial').on('click', function() {
            $('#modal-oauth-tutorial').fadeIn(150);
        });
    }

    window.closeOAuthTutorial = function() {
        $('#modal-oauth-tutorial').fadeOut(150);
    };

    // ── Módulo de Importación y Exportación Masiva ───────────────────────────────
    function initImportExport() {
        // Exportar CSV
        $('#btn-export-csv').on('click', function(e) {
            e.preventDefault();
            var cat = $('#export_filter_category').val();
            var postUrl = cfg.postUrl || cfg.ajaxUrl.replace('admin-ajax.php', 'admin-post.php');
            var url = postUrl + '?action=aura_contacts_export_csv&nonce=' + encodeURIComponent(cfg.exportNonce || cfg.nonce) + '&category=' + encodeURIComponent(cat || '');
            window.location.href = url;
        });

        // Exportar vCard (.vcf)
        $('#btn-export-vcf').on('click', function(e) {
            e.preventDefault();
            var cat = $('#export_filter_category').val();
            var postUrl = cfg.postUrl || cfg.ajaxUrl.replace('admin-ajax.php', 'admin-post.php');
            var url = postUrl + '?action=aura_contacts_export_vcf&nonce=' + encodeURIComponent(cfg.exportNonce || cfg.nonce) + '&category=' + encodeURIComponent(cat || '');
            window.location.href = url;
        });

        // Importar archivo CSV / vCard
        $('#form-contacts-import').on('submit', function(e) {
            e.preventDefault();
            var fileInput = document.getElementById('import_file_input');
            if (!fileInput || !fileInput.files.length) {
                alert('Por favor selecciona un archivo (.csv o .vcf) para importar.');
                return;
            }

            var formData = new FormData(this);
            formData.append('action', 'aura_contacts_import_file');
            formData.append('nonce', cfg.nonce);
            formData.append('strategy', $('#import_duplicate_strategy').val() || 'skip');
            formData.append('default_category', $('#import_default_category').val() || 'other');

            var $btn = $('#btn-submit-import');
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Procesando archivo...');

            $.ajax({
                url: cfg.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    $btn.prop('disabled', false).html(orig);
                    if (res.success && res.data) {
                        var d = res.data;
                        var totalCount = (typeof d.total !== 'undefined') ? d.total : (d.total_rows || 0);
                        $('#res-total').text(totalCount);
                        $('#res-created').text(d.created || 0);
                        $('#res-updated').text(d.updated || 0);
                        $('#res-skipped').text(d.skipped || 0);
                        $('#res-errors').text(d.errors || 0);

                        if (d.message) {
                            $('#res-message').text(d.message);
                        }

                        if (d.details && d.details.length > 0) {
                            var detailsHtml = '';
                            d.details.forEach(function(item) {
                                detailsHtml += '<li style="margin-bottom:3px;">' + escapeHtml(item) + '</li>';
                            });
                            $('#res-details-list').html(detailsHtml);
                            $('#res-details-wrapper').show();
                        } else {
                            $('#res-details-wrapper').hide();
                        }

                        $('#import-results-card').slideDown(200);

                        showToast('Importación finalizada: ' + (d.created || 0) + ' creados, ' + (d.updated || 0) + ' actualizados.');
                        fetchContacts();
                        refreshStats();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Error al procesar el archivo');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(orig);
                    alert('Error en la solicitud de importación.');
                }
            });
        });
    }

    // ── Detección y Fusión de Duplicados ─────────────────────────────────────────
    function initDuplicatesMerge() {
        $('#btn-scan-duplicates').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Escaneando...');
            $('#duplicates-container').html('<div style="text-align:center;padding:50px 20px;"><span class="dashicons dashicons-update spin" style="font-size:32px;width:32px;height:32px;color:#38bdf8;"></span><p style="margin-top:12px;color:#94a3b8;">Analizando similitudes en la base de datos...</p></div>');

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_find_duplicates',
                nonce: cfg.nonce
            }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (!res.success) {
                    $('#duplicates-container').html('<div class="aura-notice-box is-error"><p>' + escapeHtml(res.data && res.data.message ? res.data.message : 'Error al escanear') + '</p></div>');
                    return;
                }

                var clusters = res.data.clusters || [];
                if (!clusters.length) {
                    $('#duplicates-container').html(
                        '<div style="text-align:center;padding:50px 20px;">' +
                        '<div style="font-size:42px;margin-bottom:12px;">🎉</div>' +
                        '<h3 style="margin:0 0 8px;color:#34d399;font-size:1.15rem;">¡Directorio Impecable!</h3>' +
                        '<p style="margin:0;color:#94a3b8;font-size:0.9rem;">No se encontraron registros duplicados por correo, teléfono ni nombres similares.</p>' +
                        '</div>'
                    );
                    return;
                }

                renderDuplicateClusters(clusters);
            }).fail(function(xhr) {
                $btn.prop('disabled', false).html(orig);
                var errDetail = '';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errDetail = ' (' + xhr.responseJSON.data.message + ')';
                }
                $('#duplicates-container').html('<div class="aura-notice-box is-error"><p>Error de conexión al escanear duplicados' + escapeHtml(errDetail) + '. Intenta nuevamente.</p></div>');
            });
        });
    }

    function renderDuplicateClusters(clusters) {
        var html = '<div style="display:flex;flex-direction:column;gap:20px;">';
        clusters.forEach(function(cluster, idx) {
            var rawReasons = cluster.reasons || [
                { type: cluster.type || 'email', label: cluster.type_label || 'Coincidencia', value: cluster.match_value || '' }
            ];
            var reasonsHtml = rawReasons.map(function(r) {
                var bg = r.type === 'email' ? 'background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);' :
                         (r.type === 'phone' ? 'background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.3);' :
                         (r.type === 'org' ? 'background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);' :
                         'background:rgba(167,139,250,0.15);color:#a78bfa;border:1px solid rgba(167,139,250,0.3);'));
                return '<span style="font-size:0.75rem;padding:3px 8px;border-radius:6px;' + bg + '"><strong>' + escapeHtml(r.label || '') + ':</strong> ' + escapeHtml(r.value || '') + '</span>';
            }).join(' ');

            var confBadge = '<span style="font-size:0.75rem;padding:3px 8px;border-radius:6px;background:rgba(99,102,241,0.18);color:#a5b4fc;border:1px solid rgba(99,102,241,0.35);font-weight:700;">🎯 ' + (cluster.confidence || 90) + '% Certeza</span>';

            html += '<div class="aura-glass-card cluster-card" style="padding:18px;border-radius:12px;background:rgba(255,255,255,0.03);" data-cluster="' + idx + '">';
            html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">';
            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
            html += '<span style="font-weight:700;color:#fff;font-size:0.95rem;">Grupo ' + (idx + 1) + ' (' + cluster.contacts.length + ' coincidencias)</span>';
            html += confBadge;
            html += reasonsHtml;
            html += '</div>';

            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
            html += '<button type="button" class="btn btn-primary btn-sm btn-lift btn-open-merge-wizard" data-cluster="' + idx + '" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);border:none;box-shadow:0 0 14px rgba(139,92,246,0.4);"><span class="dashicons dashicons-randomize"></span> <span>Comparar y Fusionar Asistido</span></button>';
            html += '<button type="button" class="btn btn-secondary btn-sm btn-lift btn-merge-cluster-quick" data-cluster="' + idx + '" title="Fusiona automáticamente en el contacto maestro marcado"><span class="dashicons dashicons-yes-alt"></span> <span>Fusión Rápida</span></button>';
            html += '</div>';
            html += '</div>';

            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">';
            cluster.contacts.forEach(function(c, cIdx) {
                var isChecked = cIdx === 0 ? 'checked' : '';
                var avatarUrl = c.avatar_url || c.photo_url || '';

                html += '<div class="contact-card-selectable" style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:8px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);position:relative;">';
                html += '<div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-top:2px;">';
                html += '<input type="checkbox" class="cluster-chk-include" data-cluster="' + idx + '" value="' + c.id + '" checked title="Incluir en la fusión">';
                html += '<input type="radio" name="master_cluster_' + idx + '" value="' + c.id + '" ' + isChecked + ' title="Marcar como registro base maestro" style="accent-color:#8b5cf6;">';
                html += '</div>';
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">';
                if (avatarUrl) {
                    html += '<img src="' + escapeHtml(avatarUrl) + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" onerror="this.style.display=\'none\';">';
                }
                html += '<strong style="color:#fff;font-size:0.88rem;">' + escapeHtml(c.first_name + ' ' + (c.last_name || '')) + '</strong>';
                html += '<span style="font-size:0.7rem;color:#94a3b8;">#' + c.id + '</span>';
                html += '</div>';
                html += '<div style="font-size:0.8rem;color:#94a3b8;display:flex;flex-direction:column;gap:2px;">';
                if (c.email_primary) html += '<span>✉️ ' + escapeHtml(c.email_primary) + '</span>';
                if (c.phone_primary) html += '<span>📞 ' + escapeHtml(c.phone_primary) + '</span>';
                if (c.organization) html += '<span>🏢 ' + escapeHtml(c.organization) + (c.job_title ? ' (' + escapeHtml(c.job_title) + ')' : '') + '</span>';
                if (c.third_party_id && parseInt(c.third_party_id) > 0) html += '<span style="color:#38bdf8;">🔗 Tercero vinculado</span>';
                if (c.wp_user_id && parseInt(c.wp_user_id) > 0) html += '<span style="color:#34d399;">👤 Usuario WP vinculado</span>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';

        $('#duplicates-container').html(html);

        // Handler: Abrir Wizard de Fusión Asistida
        $('.btn-open-merge-wizard').on('click', function() {
            var clusterIdx = $(this).data('cluster');
            openMergeWizard(clusters[clusterIdx], clusterIdx);
        });

        // Handler: Fusión Rápida en Maestro marcado
        $('.btn-merge-cluster-quick').on('click', function() {
            var clusterIdx = $(this).data('cluster');
            var cluster = clusters[clusterIdx];
            var primaryId = $('input[name="master_cluster_' + clusterIdx + '"]:checked').val();
            if (!primaryId) {
                alert('Por favor selecciona el contacto maestro que prevalecerá.');
                return;
            }

            // Considerar solo los contactos con checkbox marcado
            var checkedIds = [];
            $('.cluster-chk-include[data-cluster="' + clusterIdx + '"]:checked').each(function() {
                checkedIds.push(parseInt($(this).val()));
            });

            if (checkedIds.indexOf(parseInt(primaryId)) === -1) {
                alert('El contacto maestro seleccionado debe estar incluido en la lista de fusión.');
                return;
            }

            var secondaryIds = checkedIds.filter(function(id) { return id !== parseInt(primaryId); });
            if (!secondaryIds.length) {
                alert('Debes incluir al menos dos contactos para fusionar.');
                return;
            }

            if (!confirm('¿Estás seguro de fusionar de forma rápida estos ' + secondaryIds.length + ' contacto(s) en el contacto #' + primaryId + '?\n\nLos teléfonos, emails, notas y relaciones se unificarán en el maestro y los registros redundantes se archivarán.')) {
                return;
            }

            executeMerge(primaryId, secondaryIds, {}, clusterIdx, $(this));
        });
    }

    // ── Wizard de Fusión Asistida y Selección Campo a Campo ──────────────────────
    window.closeMergeModal = function() {
        $('#modal-merge-contacts').fadeOut(150);
    };

    function openMergeWizard(cluster, clusterIdx) {
        // Filtrar contactos según checkboxes marcados en el cluster
        var checkedIds = [];
        $('.cluster-chk-include[data-cluster="' + clusterIdx + '"]:checked').each(function() {
            checkedIds.push(parseInt($(this).val()));
        });

        var activeContacts = cluster.contacts.filter(function(c) {
            return checkedIds.indexOf(parseInt(c.id)) !== -1;
        });

        if (activeContacts.length < 2) {
            alert('Debes mantener al menos 2 contactos seleccionados en este grupo para realizar la fusión.');
            return;
        }

        var preselectedMasterId = parseInt($('input[name="master_cluster_' + clusterIdx + '"]:checked').val()) || activeContacts[0].id;

        // Construir contenido dinámico del modal
        var mHtml = '';

        // 1. Selector de Registro Maestro (Destino del ID)
        mHtml += '<div style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.25);border-radius:12px;padding:16px;margin-bottom:20px;">';
        mHtml += '<div style="font-size:0.85rem;font-weight:700;color:#c4b5fd;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">👑 1. Selecciona el Contacto Maestro (ID Base que se conservará en BD):</div>';
        mHtml += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:10px;">';
        activeContacts.forEach(function(c) {
            var isMaster = (parseInt(c.id) === preselectedMasterId);
            mHtml += '<label class="merge-option-label ' + (isMaster ? 'is-selected' : '') + '">';
            mHtml += '<input type="radio" name="wizard_target_id" value="' + c.id + '" ' + (isMaster ? 'checked' : '') + ' class="merge-option-radio" onchange="onWizardMasterChange(this);">';
            mHtml += '<div><strong style="color:#fff;font-size:0.9rem;">#' + c.id + ' — ' + escapeHtml(c.first_name + ' ' + (c.last_name || '')) + '</strong>';
            mHtml += '<div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">' + (c.email_primary ? escapeHtml(c.email_primary) : 'Sin correo') + '</div></div>';
            mHtml += '</label>';
        });
        mHtml += '</div></div>';

        // 2. Campo: Nombre y Apellidos
        var distinctNames = [];
        activeContacts.forEach(function(c) {
            var fn = (c.first_name || '').trim();
            var ln = (c.last_name || '').trim();
            var full = (fn + ' ' + ln).trim();
            var exists = distinctNames.some(function(n) { return n.full === full; });
            if (!exists && full) {
                distinctNames.push({ full: full, first: fn, last: ln, sourceId: c.id });
            }
        });

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">👤 Nombre y Apellidos</span><span style="font-size:0.75rem;color:#94a3b8;">Elige una opción o edita el nombre final</span></div>';
        mHtml += '<div class="merge-options-grid">';
        distinctNames.forEach(function(n, nIdx) {
            var isChk = (nIdx === 0);
            mHtml += '<label class="merge-option-label ' + (isChk ? 'is-selected' : '') + '">';
            mHtml += '<input type="radio" name="wizard_field_name" value="' + nIdx + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" data-first="' + escapeHtml(n.first) + '" data-last="' + escapeHtml(n.last) + '" onchange="onWizardNameChange(this);">';
            mHtml += '<div><strong style="color:#fff;">' + escapeHtml(n.full) + '</strong><span style="font-size:0.75rem;color:#94a3b8;margin-left:6px;">(de #' + n.sourceId + ')</span></div>';
            mHtml += '</label>';
        });
        mHtml += '<label class="merge-option-label" id="label-wizard-name-custom">';
        mHtml += '<input type="radio" name="wizard_field_name" value="custom" class="merge-option-radio" onchange="onWizardNameChange(this);">';
        mHtml += '<div><strong style="color:#a78bfa;">✏️ Personalizar Nombre y Apellidos</strong></div>';
        mHtml += '</label>';
        mHtml += '</div>';

        // Inputs en vivo de Nombre
        var defaultFirst = distinctNames[0] ? distinctNames[0].first : '';
        var defaultLast = distinctNames[0] ? distinctNames[0].last : '';
        mHtml += '<div class="merge-custom-input-box" id="box-wizard-name-inputs" style="display:block;">';
        mHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
        mHtml += '<div><label style="font-size:0.75rem;color:#cbd5e1;display:block;margin-bottom:4px;">Nombre(s) Final *</label><input type="text" id="merge_inp_first_name" class="aura-input" value="' + escapeHtml(defaultFirst) + '" oninput="updateMergePreview();"></div>';
        mHtml += '<div><label style="font-size:0.75rem;color:#cbd5e1;display:block;margin-bottom:4px;">Apellidos Final</label><input type="text" id="merge_inp_last_name" class="aura-input" value="' + escapeHtml(defaultLast) + '" oninput="updateMergePreview();"></div>';
        mHtml += '</div></div>';
        mHtml += '</div>';

        // 3. Campo: Organización y Cargo
        var distinctOrgs = [];
        activeContacts.forEach(function(c) {
            var org = (c.organization || '').trim();
            var job = (c.job_title || '').trim();
            if (org || job) {
                var exists = distinctOrgs.some(function(o) { return o.org === org && o.job === job; });
                if (!exists) distinctOrgs.push({ org: org, job: job, sourceId: c.id });
            }
        });

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">🏢 Organización y Cargo</span></div>';
        mHtml += '<div class="merge-options-grid">';
        distinctOrgs.forEach(function(o, oIdx) {
            var isChk = (oIdx === 0);
            mHtml += '<label class="merge-option-label ' + (isChk ? 'is-selected' : '') + '">';
            mHtml += '<input type="radio" name="wizard_field_org" value="' + oIdx + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" data-org="' + escapeHtml(o.org) + '" data-job="' + escapeHtml(o.job) + '" onchange="onWizardOrgChange(this);">';
            mHtml += '<div><strong style="color:#fff;">' + (o.org ? escapeHtml(o.org) : 'Sin empresa') + '</strong>' + (o.job ? ' — <span style="color:#94a3b8;">' + escapeHtml(o.job) + '</span>' : '') + '<span style="font-size:0.75rem;color:#94a3b8;margin-left:6px;">(#' + o.sourceId + ')</span></div>';
            mHtml += '</label>';
        });
        mHtml += '<label class="merge-option-label">';
        mHtml += '<input type="radio" name="wizard_field_org" value="custom" class="merge-option-radio" onchange="onWizardOrgChange(this);">';
        mHtml += '<div><strong style="color:#a78bfa;">✏️ Personalizar Empresa y Cargo</strong></div>';
        mHtml += '</label>';
        mHtml += '</div>';

        var defaultOrg = distinctOrgs[0] ? distinctOrgs[0].org : '';
        var defaultJob = distinctOrgs[0] ? distinctOrgs[0].job : '';
        mHtml += '<div class="merge-custom-input-box" id="box-wizard-org-inputs" style="display:block;">';
        mHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
        mHtml += '<div><label style="font-size:0.75rem;color:#cbd5e1;display:block;margin-bottom:4px;">Empresa / Organización</label><input type="text" id="merge_inp_organization" class="aura-input" value="' + escapeHtml(defaultOrg) + '" oninput="updateMergePreview();"></div>';
        mHtml += '<div><label style="font-size:0.75rem;color:#cbd5e1;display:block;margin-bottom:4px;">Cargo / Ocupación</label><input type="text" id="merge_inp_job_title" class="aura-input" value="' + escapeHtml(defaultJob) + '" oninput="updateMergePreview();"></div>';
        mHtml += '</div></div>';
        mHtml += '</div>';

        // 4. Campo: Correos Electrónicos (Primario y Secundario)
        var allEmails = [];
        activeContacts.forEach(function(c) {
            [c.email_primary, c.email_secondary].forEach(function(em) {
                em = (em || '').trim().toLowerCase();
                if (em && allEmails.indexOf(em) === -1) allEmails.push(em);
            });
        });

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">✉️ Correos Electrónicos</span><span style="font-size:0.75rem;color:#34d399;">El principal prevalecerá en Google y el alterno se guardará como secundario</span></div>';
        if (allEmails.length > 0) {
            mHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">';
            mHtml += '<div><label style="font-size:0.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px;">Correo Principal (Primary) *</label>';
            allEmails.forEach(function(em, emIdx) {
                var isChk = (emIdx === 0);
                mHtml += '<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(15,23,42,0.5);border:1px solid rgba(255,255,255,0.06);border-radius:6px;margin-bottom:6px;cursor:pointer;">';
                mHtml += '<input type="radio" name="wizard_email_primary" value="' + escapeHtml(em) + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.85rem;color:#fff;">' + escapeHtml(em) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';

            mHtml += '<div><label style="font-size:0.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px;">Correo Secundario (Secondary)</label>';
            mHtml += '<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(15,23,42,0.5);border:1px solid rgba(255,255,255,0.06);border-radius:6px;margin-bottom:6px;cursor:pointer;">';
            mHtml += '<input type="radio" name="wizard_email_secondary" value="" ' + (allEmails.length <= 1 ? 'checked' : '') + ' onchange="updateMergePreview();">';
            mHtml += '<span style="font-size:0.85rem;color:#94a3b8;">Ninguno / Dejar vacío</span>';
            mHtml += '</label>';
            allEmails.forEach(function(em, emIdx) {
                var isChk = (allEmails.length > 1 && emIdx === 1);
                mHtml += '<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(15,23,42,0.5);border:1px solid rgba(255,255,255,0.06);border-radius:6px;margin-bottom:6px;cursor:pointer;">';
                mHtml += '<input type="radio" name="wizard_email_secondary" value="' + escapeHtml(em) + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.85rem;color:#fff;">' + escapeHtml(em) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';
            mHtml += '</div>';
        } else {
            mHtml += '<p style="color:#94a3b8;font-size:0.85rem;margin:0;">Ninguno de los contactos tiene correo electrónico registrado.</p>';
        }
        mHtml += '</div>';

        // 5. Campo: Teléfonos
        var allPhones = [];
        activeContacts.forEach(function(c) {
            [c.phone_primary, c.phone_secondary, c.whatsapp].forEach(function(ph) {
                ph = (ph || '').trim();
                if (ph && allPhones.indexOf(ph) === -1) allPhones.push(ph);
            });
        });

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">📞 Teléfonos y WhatsApp</span></div>';
        if (allPhones.length > 0) {
            mHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">';
            mHtml += '<div><label style="font-size:0.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px;">Teléfono Principal *</label>';
            allPhones.forEach(function(ph, pIdx) {
                var isChk = (pIdx === 0);
                mHtml += '<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(15,23,42,0.5);border:1px solid rgba(255,255,255,0.06);border-radius:6px;margin-bottom:6px;cursor:pointer;">';
                mHtml += '<input type="radio" name="wizard_phone_primary" value="' + escapeHtml(ph) + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.85rem;color:#fff;">' + escapeHtml(ph) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';

            mHtml += '<div><label style="font-size:0.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px;">Teléfono Secundario</label>';
            mHtml += '<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(15,23,42,0.5);border:1px solid rgba(255,255,255,0.06);border-radius:6px;margin-bottom:6px;cursor:pointer;">';
            mHtml += '<input type="radio" name="wizard_phone_secondary" value="" ' + (allPhones.length <= 1 ? 'checked' : '') + ' onchange="updateMergePreview();">';
            mHtml += '<span style="font-size:0.85rem;color:#94a3b8;">Ninguno / Dejar vacío</span>';
            mHtml += '</label>';
            allPhones.forEach(function(ph, pIdx) {
                var isChk = (allPhones.length > 1 && pIdx === 1);
                mHtml += '<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(15,23,42,0.5);border:1px solid rgba(255,255,255,0.06);border-radius:6px;margin-bottom:6px;cursor:pointer;">';
                mHtml += '<input type="radio" name="wizard_phone_secondary" value="' + escapeHtml(ph) + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.85rem;color:#fff;">' + escapeHtml(ph) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';
            mHtml += '</div>';
        } else {
            mHtml += '<p style="color:#94a3b8;font-size:0.85rem;margin:0;">Ninguno de los contactos tiene teléfono registrado.</p>';
        }
        mHtml += '</div>';

        // 6. Campo: Avatar / Foto
        var allAvatars = [];
        activeContacts.forEach(function(c) {
            var av = (c.avatar_url || c.photo_url || '').trim();
            if (av && allAvatars.indexOf(av) === -1) allAvatars.push({ url: av, sourceId: c.id });
        });

        if (allAvatars.length > 0) {
            mHtml += '<div class="merge-field-card">';
            mHtml += '<div class="merge-field-header"><span class="merge-field-title">🖼️ Foto de Perfil / Avatar</span></div>';
            mHtml += '<div style="display:flex;gap:12px;flex-wrap:wrap;">';
            allAvatars.forEach(function(av, avIdx) {
                var isChk = (avIdx === 0);
                mHtml += '<label style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:8px;cursor:pointer;">';
                mHtml += '<input type="radio" name="wizard_avatar_url" value="' + escapeHtml(av.url) + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();">';
                mHtml += '<img src="' + escapeHtml(av.url) + '" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">';
                mHtml += '<span style="font-size:0.8rem;color:#94a3b8;">Foto de #' + av.sourceId + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div></div>';
        }

        // 7. Campo: Categoría
        var allCats = [];
        activeContacts.forEach(function(c) {
            if (c.category && allCats.indexOf(c.category) === -1) allCats.push(c.category);
        });

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">🏷️ Categoría de la Ficha Maestra</span></div>';
        mHtml += '<div style="display:flex;gap:10px;flex-wrap:wrap;">';
        ['student', 'teacher', 'staff', 'supplier', 'donor', 'member', 'other'].forEach(function(catKey) {
            var label = cfg.categories[catKey] || catKey;
            var isChk = (allCats.length > 0 && allCats[0] === catKey);
            mHtml += '<label style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(15,23,42,0.5);border:1px solid rgba(255,255,255,0.08);border-radius:6px;cursor:pointer;">';
            mHtml += '<input type="radio" name="wizard_category" value="' + catKey + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();">';
            mHtml += '<span style="font-size:0.84rem;color:#fff;">' + escapeHtml(label) + '</span>';
            mHtml += '</label>';
        });
        mHtml += '</div></div>';

        // 8. Notas adicionales
        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">📝 Notas Consolidadas y Relaciones</span><span style="font-size:0.75rem;color:#38bdf8;">Todas las notas e historiales previos se preservarán automáticamente</span></div>';
        mHtml += '<textarea id="merge_inp_custom_notes" class="aura-textarea" rows="2" placeholder="Nota adicional explicativa de la fusión (opcional)..." oninput="updateMergePreview();"></textarea>';
        mHtml += '</div>';

        // 9. TARJETA EN VIVO: PREVISUALIZACIÓN DEL REGISTRO RESULTANTE
        mHtml += '<div class="merge-preview-card" id="merge-live-preview-box">';
        mHtml += '<div style="font-size:0.8rem;font-weight:700;color:#34d399;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">🌟 Vista Previa del Contacto Maestro Resultante:</div>';
        mHtml += '<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">';
        mHtml += '<div id="prev-avatar-wrap" style="width:52px;height:52px;border-radius:50%;background:#8b5cf6;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;font-weight:700;flex-shrink:0;">👤</div>';
        mHtml += '<div style="flex:1;min-width:200px;">';
        mHtml += '<div style="font-size:1.15rem;font-weight:800;color:#fff;" id="prev-name">Cargando...</div>';
        mHtml += '<div style="font-size:0.85rem;color:#cbd5e1;" id="prev-org-job"></div>';
        mHtml += '<div style="display:flex;gap:14px;margin-top:6px;font-size:0.82rem;color:#94a3b8;flex-wrap:wrap;">';
        mHtml += '<span id="prev-email">✉️ —</span>';
        mHtml += '<span id="prev-phone">📞 —</span>';
        mHtml += '<span id="prev-cat" style="color:#a78bfa;">🏷️ —</span>';
        mHtml += '</div>';
        mHtml += '</div>';
        mHtml += '</div>';
        mHtml += '</div>';

        $('#merge-modal-content').html(mHtml);

        // Guardar contexto en el botón de confirmación
        $('#btn-submit-merge-action').data('cluster-idx', clusterIdx);
        $('#btn-submit-merge-action').data('active-contacts', activeContacts);

        // Actualizar previsualización inicial
        updateMergePreview();

        // Mostrar modal con centrado inteligente
        $('#modal-merge-contacts').css('display', 'flex').hide().fadeIn(150);
    }

    // Handlers dinámicos del Wizard de Fusión
    window.onWizardMasterChange = function(radio) {
        $('.merge-option-label').removeClass('is-selected');
        $(radio).closest('.merge-option-label').addClass('is-selected');
        updateMergePreview();
    };

    window.onWizardNameChange = function(radio) {
        $('input[name="wizard_field_name"]').closest('.merge-option-label').removeClass('is-selected');
        $(radio).closest('.merge-option-label').addClass('is-selected');
        if (radio.value === 'custom') {
            $('#box-wizard-name-inputs').slideDown(150);
            $('#merge_inp_first_name').focus();
        } else {
            var first = $(radio).data('first') || '';
            var last = $(radio).data('last') || '';
            $('#merge_inp_first_name').val(first);
            $('#merge_inp_last_name').val(last);
        }
        updateMergePreview();
    };

    window.onWizardOrgChange = function(radio) {
        $('input[name="wizard_field_org"]').closest('.merge-option-label').removeClass('is-selected');
        $(radio).closest('.merge-option-label').addClass('is-selected');
        if (radio.value === 'custom') {
            $('#box-wizard-org-inputs').slideDown(150);
            $('#merge_inp_organization').focus();
        } else {
            var org = $(radio).data('org') || '';
            var job = $(radio).data('job') || '';
            $('#merge_inp_organization').val(org);
            $('#merge_inp_job_title').val(job);
        }
        updateMergePreview();
    };

    window.updateMergePreview = function() {
        var fn = ($('#merge_inp_first_name').val() || '').trim();
        var ln = ($('#merge_inp_last_name').val() || '').trim();
        var full = fn + (ln ? ' ' + ln : '');
        if (!full) full = 'Contacto Sin Nombre';

        var org = ($('#merge_inp_organization').val() || '').trim();
        var job = ($('#merge_inp_job_title').val() || '').trim();
        var orgJobText = org ? org + (job ? ' (' + job + ')' : '') : (job ? job : 'Sin organización');

        var emPrimary = $('input[name="wizard_email_primary"]:checked').val() || '';
        var phPrimary = $('input[name="wizard_phone_primary"]:checked').val() || '';
        var catKey = $('input[name="wizard_category"]:checked').val() || 'other';
        var catLabel = cfg.categories[catKey] || catKey;
        var avatarUrl = $('input[name="wizard_avatar_url"]:checked').val() || '';

        $('#prev-name').text(full);
        $('#prev-org-job').text(orgJobText);
        $('#prev-email').text(emPrimary ? '✉️ ' + emPrimary : '✉️ Sin correo');
        $('#prev-phone').text(phPrimary ? '📞 ' + phPrimary : '📞 Sin teléfono');
        $('#prev-cat').text('🏷️ ' + catLabel);

        if (avatarUrl) {
            $('#prev-avatar-wrap').html('<img src="' + escapeHtml(avatarUrl) + '" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">');
        } else {
            var initial = fn ? fn.charAt(0).toUpperCase() : '👤';
            $('#prev-avatar-wrap').html(initial);
        }
    };

    // Confirmación y envío del Wizard
    $('#btn-submit-merge-action').on('click', function() {
        var $btn = $(this);
        var clusterIdx = $btn.data('cluster-idx');
        var activeContacts = $btn.data('active-contacts') || [];

        var targetId = parseInt($('input[name="wizard_target_id"]:checked').val());
        if (!targetId) {
            alert('Por favor selecciona el contacto maestro de destino.');
            return;
        }

        var sourceIds = activeContacts.map(function(c) { return parseInt(c.id); }).filter(function(id) { return id !== targetId; });
        if (!sourceIds.length) {
            alert('No hay contactos secundarios para fusionar.');
            return;
        }

        // Recolectar sobreescrituras (field_overrides)
        var overrides = {
            first_name: ($('#merge_inp_first_name').val() || '').trim(),
            last_name: ($('#merge_inp_last_name').val() || '').trim(),
            organization: ($('#merge_inp_organization').val() || '').trim(),
            job_title: ($('#merge_inp_job_title').val() || '').trim(),
            email_primary: $('input[name="wizard_email_primary"]:checked').val() || '',
            email_secondary: $('input[name="wizard_email_secondary"]:checked').val() || '',
            phone_primary: $('input[name="wizard_phone_primary"]:checked').val() || '',
            phone_secondary: $('input[name="wizard_phone_secondary"]:checked').val() || '',
            avatar_url: $('input[name="wizard_avatar_url"]:checked').val() || '',
            category: $('input[name="wizard_category"]:checked').val() || '',
            custom_notes: ($('#merge_inp_custom_notes').val() || '').trim()
        };

        var orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Fusionando contactos...');

        executeMerge(targetId, sourceIds, overrides, clusterIdx, $btn, orig);
    });

    function executeMerge(targetId, sourceIds, overrides, clusterIdx, $btn, origHtml) {
        $.post(cfg.ajaxUrl, {
            action: 'aura_contacts_merge',
            nonce: cfg.nonce,
            target_id: targetId,
            source_ids: sourceIds,
            field_overrides: overrides
        }, function(res) {
            if (res.success) {
                closeMergeModal();
                showToast('Fusión exitosa: Se unificaron ' + sourceIds.length + ' contacto(s) en la ficha #' + targetId);
                $('.cluster-card[data-cluster="' + clusterIdx + '"]').fadeOut(300, function() {
                    $(this).remove();
                    if (!$('.cluster-card').length) {
                        $('#duplicates-container').html(
                            '<div style="text-align:center;padding:50px 20px;">' +
                            '<div style="font-size:42px;margin-bottom:12px;">🎉</div>' +
                            '<h3 style="margin:0 0 8px;color:#34d399;font-size:1.15rem;">¡Fusión Completada!</h3>' +
                            '<p style="margin:0;color:#94a3b8;font-size:0.9rem;">Todos los grupos de duplicados han sido resueltos satisfactoriamente.</p>' +
                            '</div>'
                        );
                    }
                });
                fetchContacts();
                refreshStats();
            } else {
                if (origHtml) $btn.prop('disabled', false).html(origHtml);
                alert(res.data && res.data.message ? res.data.message : 'Error al fusionar contactos.');
            }
        }).fail(function() {
            if (origHtml) $btn.prop('disabled', false).html(origHtml);
            alert('Error de conexión durante el proceso de fusión.');
        });
    }

    // ── Modal de Vinculación de Entidades ────────────────────────────────────────
    window.openLinkEntityModal = function(contactId, contactName) {
        $('#link-target-contact-id').val(contactId);
        $('#link-contact-name-banner').text('Vincular a: ' + contactName);
        $('#link-modal-tp-search').val('');
        $('#link-modal-wp-search').val('');
        $('#link-modal-tp-results').hide().empty();
        $('#link-modal-wp-results').hide().empty();
        $('#modal-link-entity').css('display', 'flex').hide().fadeIn(150);

        // Búsqueda Tercero en Modal Link
        var tpTimer = null;
        $('#link-modal-tp-search').off('input').on('input', function() {
            var q = $(this).val().trim();
            clearTimeout(tpTimer);
            if (q.length < 2) {
                $('#link-modal-tp-results').hide().empty();
                return;
            }
            tpTimer = setTimeout(function() {
                searchThirdParties(q, function(items) {
                    var $box = $('#link-modal-tp-results').empty();
                    if (!items || items.length === 0) {
                        $box.append('<div class="autocomplete-item" style="color:#94a3b8;">No se encontraron terceros</div>').show();
                        return;
                    }
                    items.forEach(function(tp) {
                        var $item = $('<div class="autocomplete-item"><span><strong>' + escapeHtml(tp.name) + '</strong> (' + escapeHtml(tp.type_label || '') + ')</span><span style="font-size:0.75rem;color:#38bdf8;">Vincular</span></div>');
                        $item.on('click', function() {
                            linkThirdParty(contactId, tp.id);
                        });
                        $box.append($item);
                    });
                    $box.show();
                });
            }, 250);
        });

        // Búsqueda Usuario WP en Modal Link
        var wpTimer = null;
        $('#link-modal-wp-search').off('input').on('input', function() {
            var q = $(this).val().trim();
            clearTimeout(wpTimer);
            if (q.length < 2) {
                $('#link-modal-wp-results').hide().empty();
                return;
            }
            wpTimer = setTimeout(function() {
                searchWpUsers(q, function(users) {
                    var $box = $('#link-modal-wp-results').empty();
                    if (!users || users.length === 0) {
                        $box.append('<div class="autocomplete-item" style="color:#94a3b8;">No se encontraron usuarios</div>').show();
                        return;
                    }
                    users.forEach(function(u) {
                        var $item = $('<div class="autocomplete-item"><span><strong>' + escapeHtml(u.name) + '</strong> (' + escapeHtml(u.email) + ')</span><span style="font-size:0.75rem;color:#c084fc;">Vincular</span></div>');
                        $item.on('click', function() {
                            linkWpUser(contactId, u.id);
                        });
                        $box.append($item);
                    });
                    $box.show();
                });
            }, 250);
        });
    };

    window.closeLinkModal = function() {
        $('#modal-link-entity')
            .removeClass('active')
            .addClass('is-hidden')
            .css({ display: 'none', opacity: 0, visibility: 'hidden' });
    };

    function linkThirdParty(contactId, thirdPartyId) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_link_third_party', nonce: cfg.nonce, contact_id: contactId, third_party_id: thirdPartyId }, function(res) {
            if (res.success) {
                showToast('Tercero vinculado exitosamente.');
                closeLinkModal();
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error al vincular');
            }
        });
    }

    function linkWpUser(contactId, wpUserId) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_link_wp_user', nonce: cfg.nonce, contact_id: contactId, wp_user_id: wpUserId }, function(res) {
            if (res.success) {
                showToast('Usuario WordPress vinculado exitosamente.');
                closeLinkModal();
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error al vincular');
            }
        });
    }

    function createThirdPartyFromContact(contactId) {
        contactId = contactId || $('#link-target-contact-id').val();
        if (!contactId || parseInt(contactId, 10) <= 0) return;

        if (!confirm('¿Deseas registrar este contacto como un nuevo Tercero en el catálogo comercial y contable de Finanzas?')) {
            return;
        }

        var $btn = $('#btn-link-modal-create-tp');
        var origText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creando...');

        $.post(cfg.ajaxUrl, {
            action: 'aura_contacts_create_third_party',
            nonce: cfg.nonce,
            contact_id: contactId
        }, function(res) {
            $btn.prop('disabled', false).html(origText);
            if (res.success) {
                showToast(res.data && res.data.message ? res.data.message : '¡Tercero creado y vinculado con éxito!');
                closeLinkModal();
                fetchContacts();
                refreshStats();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error al crear el Tercero.');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(origText);
            alert('Error de conexión al intentar crear el Tercero.');
        });
    }

    function createThirdPartyFromForm() {
        var contactId = $('#contact-form-id').val();
        if (!contactId || parseInt(contactId, 10) <= 0) {
            alert('Debes guardar el contacto primero antes de poder crearlo como Tercero.');
            return;
        }

        if (!confirm('¿Deseas crear y registrar este contacto en el catálogo comercial de Terceros de Finanzas?')) {
            return;
        }

        var $btn = $('#btn-form-create-tp');
        var origText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creando...');

        $.post(cfg.ajaxUrl, {
            action: 'aura_contacts_create_third_party',
            nonce: cfg.nonce,
            contact_id: contactId
        }, function(res) {
            $btn.prop('disabled', false).html(origText);
            if (res.success && res.data) {
                $('#contact-third-party-id').val(res.data.third_party_id);
                $('#contact-third-party-name').text(res.data.third_party_name || ('Tercero #' + res.data.third_party_id));
                $('#contact-third-party-selected').show();
                $('#contact-form-create-tp-wrap').hide();
                showToast(res.data.message || '¡Tercero creado y vinculado con éxito!');
                fetchContacts();
                refreshStats();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error al crear el Tercero.');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(origText);
            alert('Error de conexión al intentar crear el Tercero.');
        });
    }

    function searchThirdParties(term, callback) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_search_third_parties', nonce: cfg.nonce, term: term }, function(res) {
            var list = (res && res.success && res.data) ? (Array.isArray(res.data) ? res.data : (res.data.items || [])) : [];
            callback(list);
        });
    }

    function searchWpUsers(term, callback) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_search_wp_users', nonce: cfg.nonce, term: term }, function(res) {
            var list = (res && res.success && res.data) ? (Array.isArray(res.data) ? res.data : (res.data.items || [])) : [];
            callback(list);
        });
    }

    // ── Actualización Reactiva de Estadísticas y Contadores del Navbar ──────────
    function refreshStats() {
        $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_contacts_get_stats',
                nonce: cfg.nonce
            },
            success: function(res) {
                if (!res.success || !res.data) return;
                var stats = res.data;
                var total = stats.total || stats.total_active || 0;
                $('#header-cnt-total').text(total);
                $('#kpi-total-val').text(total);
                $('#kpi-synced-val').text(stats.synced || 0);
                $('#kpi-pending-val').text(stats.pending || 0);
                $('#kpi-error-val').text(stats.error || stats.errors || 0);
                $('#kpi-third-val').text(stats.with_third_party || 0);
                $('#tab-count-all').text(total);
                if (stats.by_category) {
                    $('#tab-count-student').text(stats.by_category.student || 0);
                    $('#tab-count-teacher').text((stats.by_category.teacher || 0) + (stats.by_category.facilitator || 0));
                    $('#tab-count-staff').text(stats.by_category.staff || 0);
                    $('#tab-count-supplier').text(stats.by_category.supplier || 0);
                    $('#tab-count-donor').text(stats.by_category.donor || 0);
                    $('#tab-count-member').text(stats.by_category.member || 0);
                }
            }
        });
    }

    // ── Funcionalidad de Edición Masiva (Aura Enterprise Bulk Actions) ─────────
    function getSelectedContactIds() {
        var ids = [];
        $('.contact-row-check:checked').each(function() {
            var val = parseInt($(this).val(), 10);
            if (!isNaN(val) && val > 0) {
                ids.push(val);
            }
        });
        return ids;
    }

    function updateBulkState() {
        var ids = getSelectedContactIds();
        var count = ids.length;

        if (count > 0) {
            $('#btn-contacts-bulk-edit').show().css('display', 'inline-flex');
            $('#contacts-bulk-count').text(count);
            $('#contacts-bulk-bar').slideDown(150).css('display', 'flex');
            $('#contacts-bulk-badge-circle').text(count);
            $('#contacts-bulk-bar-label').text(count + (count === 1 ? ' contacto seleccionado' : ' contactos seleccionados'));
        } else {
            $('#btn-contacts-bulk-edit').hide();
            $('#contacts-bulk-bar').slideUp(150);
            $('#contacts-select-all').prop('checked', false);
        }
    }

    function initBulkEdit() {
        // Seleccionar / Deseleccionar todo
        $(document).on('change', '#contacts-select-all', function() {
            var isChecked = $(this).is(':checked');
            $('.contact-row-check').prop('checked', isChecked);
            updateBulkState();
        });

        // Checkbox individual por fila
        $(document).on('change', '.contact-row-check', function() {
            var total = $('.contact-row-check').length;
            var checked = $('.contact-row-check:checked').length;
            $('#contacts-select-all').prop('checked', total > 0 && total === checked);
            updateBulkState();
        });

        // Deseleccionar desde la barra flotante
        $(document).on('click', '#btn-bulk-bar-clear', function(e) {
            e.preventDefault();
            $('.contact-row-check, #contacts-select-all').prop('checked', false);
            updateBulkState();
        });

        // Abrir modal de Edición Masiva
        $(document).on('click', '#btn-contacts-bulk-edit, #btn-bulk-bar-edit', function(e) {
            e.preventDefault();
            var ids = getSelectedContactIds();
            if (!ids.length) {
                alert('Selecciona al menos un contacto para editar.');
                return;
            }

            $('#bulk-edit-modal-count').text(ids.length);

            // Reiniciar estado de activación de campos (Regla de Seguridad)
            $('#bulk-chk-category, #bulk-chk-tags, #bulk-chk-status').prop('checked', false);
            $('#bulk-chk-sync').prop('checked', true);
            $('#bulk-wrap-category, #bulk-wrap-tags, #bulk-wrap-status').css({ opacity: 0.45, 'pointer-events': 'none' });
            $('#bulk-val-tags').val('');

            $('#modal-bulk-edit-contacts').fadeIn(180);
        });

        // Cerrar modal
        $(document).on('click', '#btn-close-bulk-modal, #btn-cancel-bulk-modal', function(e) {
            e.preventDefault();
            $('#modal-bulk-edit-contacts').fadeOut(180);
        });

        // Cerrar modal al hacer clic en el backdrop
        $(document).on('click', '#modal-bulk-edit-contacts', function(e) {
            if ($(e.target).is('#modal-bulk-edit-contacts')) {
                $('#modal-bulk-edit-contacts').fadeOut(180);
            }
        });

        // Activación individual de campos en el modal (Regla de Seguridad)
        $('#bulk-chk-category').on('change', function() {
            var active = $(this).is(':checked');
            $('#bulk-wrap-category').css({ opacity: active ? 1 : 0.45, 'pointer-events': active ? 'auto' : 'none' });
        });

        $('#bulk-chk-tags').on('change', function() {
            var active = $(this).is(':checked');
            $('#bulk-wrap-tags').css({ opacity: active ? 1 : 0.45, 'pointer-events': active ? 'auto' : 'none' });
        });

        $('#bulk-chk-status').on('change', function() {
            var active = $(this).is(':checked');
            $('#bulk-wrap-status').css({ opacity: active ? 1 : 0.45, 'pointer-events': active ? 'auto' : 'none' });
        });

        // Guardar cambios masivos
        $('#btn-submit-bulk-modal').on('click', function(e) {
            e.preventDefault();
            var ids = getSelectedContactIds();
            if (!ids.length) {
                alert('No hay contactos seleccionados.');
                return;
            }

            var applyCategory = $('#bulk-chk-category').is(':checked');
            var applyTags     = $('#bulk-chk-tags').is(':checked');
            var applyStatus   = $('#bulk-chk-status').is(':checked');
            var applySync     = $('#bulk-chk-sync').is(':checked');

            if (!applyCategory && !applyTags && !applyStatus && !applySync) {
                alert('Debes marcar al menos una casilla para aplicar cambios a los contactos seleccionados.');
                return;
            }

            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Aplicando cambios...');

            $.ajax({
                url: cfg.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'aura_contacts_bulk_edit',
                    nonce: cfg.nonce,
                    ids: ids,
                    apply_category: applyCategory ? 1 : 0,
                    category: $('#bulk-val-category').val(),
                    apply_tags: applyTags ? 1 : 0,
                    tag_mode: $('input[name="bulk-tag-mode"]:checked').val() || 'add',
                    tags: $('#bulk-val-tags').val(),
                    apply_status: applyStatus ? 1 : 0,
                    is_active: $('#bulk-val-status').val(),
                    apply_sync: applySync ? 1 : 0
                },
                success: function(res) {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (res && res.success) {
                        $('#modal-bulk-edit-contacts').fadeOut(180);
                        showToast(res.data && res.data.message ? res.data.message : 'Edición masiva aplicada con éxito');
                        $('.contact-row-check, #contacts-select-all').prop('checked', false);
                        updateBulkState();
                        fetchContacts();
                    } else {
                        alert(res && res.data && res.data.message ? res.data.message : 'Error al guardar cambios masivos');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    alert('Error de comunicación con el servidor al procesar la edición masiva');
                }
            });
        });

        // Re-sincronizar contactos seleccionados con Google People API
        $(document).on('click', '#btn-bulk-bar-sync', function(e) {
            e.preventDefault();
            var ids = getSelectedContactIds();
            if (!ids.length) return;

            if (!confirm('¿Deseas sincronizar los ' + ids.length + ' contactos seleccionados con Google Contacts ahora?')) {
                return;
            }

            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sincronizando (0/' + ids.length + ')...');

            var successCount = 0;
            var errorCount = 0;

            function syncItem(index) {
                if (index >= ids.length) {
                    $btn.prop('disabled', false).html(originalHtml);
                    showToast('Sincronización masiva finalizada: ' + successCount + ' procesados' + (errorCount ? ', ' + errorCount + ' con error' : ''));
                    $('.contact-row-check, #contacts-select-all').prop('checked', false);
                    updateBulkState();
                    fetchContacts();
                    return;
                }

                $btn.html('<span class="dashicons dashicons-update spin"></span> Sincronizando (' + (index + 1) + '/' + ids.length + ')...');

                $.ajax({
                    url: cfg.ajaxUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'aura_contacts_sync_single',
                        nonce: cfg.nonce,
                        contact_id: ids[index]
                    },
                    success: function(res) {
                        if (res && res.success) {
                            successCount++;
                        } else {
                            errorCount++;
                        }
                        syncItem(index + 1);
                    },
                    error: function() {
                        errorCount++;
                        syncItem(index + 1);
                    }
                });
            }

            syncItem(0);
        });
    }

    // ── Funciones Auxiliares y Exposición Global ─────────────────────────────────
    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function showToast(msg) {
        var $toast = $('<div class="aura-toast" style="position:fixed;bottom:24px;right:24px;background:#1e293b;border:1px solid #10b981;color:#ffffff;padding:12px 20px;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,0.4);z-index:999999;font-weight:600;display:flex;align-items:center;gap:10px;animation:modalSlideUp 0.2s;"><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>' + escapeHtml(msg) + '</div>');
        $('body').append($toast);
        setTimeout(function() {
            $toast.fadeOut(300, function() { $(this).remove(); });
        }, 3200);
    }

    // Exponer API para botones inline
    window.AuraContactsApp = {
        editContact: openEditModal,
        openLinkEntityModal: window.openLinkEntityModal,
        createThirdPartyFromContact: createThirdPartyFromContact,
        createThirdPartyFromForm: createThirdPartyFromForm,
        syncSingleContact: syncSingle,
        deleteContact: deleteContactItem,
        refresh: fetchContacts,
        refreshStats: refreshStats,
        openBulkEditModal: function() { $('#btn-contacts-bulk-edit').trigger('click'); },
        getSelectedIds: getSelectedContactIds
    };

})(jQuery);

