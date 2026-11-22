/**
 * Aura Business Suite — Script del Directorio Maestro de Contactos y Google Sync
 *
 * Administra el estado interactivo, búsqueda reactiva, paginación, modales,
 * sincronización individual/masiva con Google People API y vinculación de Terceros/WP Users.
 */

(function($) {
    'use strict';

    var cfg = window.auraContactsData || {};
    var __ = (window.wp && window.wp.i18n && window.wp.i18n.__) ? window.wp.i18n.__ : function(s) { return s; };
    var sprintf = (window.wp && window.wp.i18n && window.wp.i18n.sprintf) ? window.wp.i18n.sprintf : function(fmt) {
        var args = Array.prototype.slice.call(arguments, 1), i = 0;
        return String(fmt).replace(/%(?:(\d+)\$)?[sd]/g, function(m, pos) {
            var idx = pos ? (parseInt(pos, 10) - 1) : i++;
            return args[idx] !== undefined ? args[idx] : '';
        });
    };

    var state = {
        page: 1,
        perPage: 25,
        search: '',
        category: '',
        syncStatus: '',
        activeTab: 'directory',
        filterId: (cfg.initialId ? parseInt(cfg.initialId, 10) : 0),
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
        initSyncLogs();
        initContactCard();

        // Inicializar filtro de ID previo a la primera petición AJAX si viene en URL o Hash
        initUrlParamsState();

        // Cargar primera página de contactos y al completar ejecutar la acción de URL
        fetchContacts(function() {
            handleUrlActions();
        });

        // Escuchar cambios de hash en la ventana
        $(window).on('hashchange', function() {
            initUrlParamsState();
            fetchContacts(function() {
                handleUrlActions();
            });
        });
    });

    function initUrlParamsState() {
        var hash = window.location.hash || '';
        var urlParams = null;
        try {
            urlParams = new URLSearchParams(window.location.search);
        } catch (e) {
            urlParams = null;
        }

        var idParam = cfg.initialId || (urlParams ? parseInt(urlParams.get('id') || urlParams.get('contact_id') || '0', 10) : 0) || 0;
        var hashMatch = hash.match(/^#(view|edit)-(\d+)$/);
        if (hashMatch) {
            idParam = parseInt(hashMatch[2], 10);
        }

        if (idParam > 0) {
            state.filterId = idParam;
        }
    }

    function handleUrlActions() {
        var hash = window.location.hash || '';
        var urlParams = null;
        try {
            urlParams = new URLSearchParams(window.location.search);
        } catch (e) {
            urlParams = null;
        }

        var actionParam = cfg.initialAction || (urlParams ? urlParams.get('action') : '') || '';
        var idParam = state.filterId || cfg.initialId || (urlParams ? parseInt(urlParams.get('id') || urlParams.get('contact_id') || '0', 10) : 0) || 0;

        // Comprobar patrones de hash como #view-123 o #edit-123
        var hashMatch = hash.match(/^#(view|edit)-(\d+)$/);
        if (hashMatch) {
            actionParam = hashMatch[1];
            idParam = parseInt(hashMatch[2], 10);
        }

        if (idParam > 0) {
            if (actionParam === 'edit') {
                openEditModal(idParam);
            } else {
                // 'view' o por defecto al especificar id: abrir tarjeta
                viewContactCard(idParam);
            }
        } else if (hash === '#new') {
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

    function clearIdFilter() {
        state.filterId = 0;
        $('#contacts-id-filter-banner').slideUp(180);
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.pathname + '?page=aura-contacts';
            window.history.replaceState({}, document.title, cleanUrl);
        }
        state.page = 1;
        fetchContacts();
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
                if (state.filterId) {
                    state.filterId = 0;
                    $('#contacts-id-filter-banner').hide();
                }
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
        // Si no estamos en el directorio, asegurar que la barra de pestañas esté visible y la barra bulk oculta
        if (tabKey !== 'directory') {
            $('#contacts-bulk-bar').hide();
            $('.aura-contacts-navbar').show().css('display', 'flex');
        }
    }

    // ── Filtros y Búsqueda Reactiva ──────────────────────────────────────────────
    function initFilters() {
        $('#contacts-search-input').on('input', function() {
            var val = $(this).val().trim();
            if (state.filterId) {
                state.filterId = 0;
                $('#contacts-id-filter-banner').hide();
            }
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
            if (state.filterId) {
                state.filterId = 0;
                $('#contacts-id-filter-banner').hide();
            }
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

        // Botón para limpiar filtro de contacto individual
        $('#btn-clear-id-filter').on('click', function(e) {
            e.preventDefault();
            clearIdFilter();
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
    function fetchContacts(cb) {
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
                sync_status: state.syncStatus,
                id: state.filterId || 0
            },
            success: function(res) {
                state.isLoading = false;
                if (!res.success) {
                    $tbody.html('<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 24px;">' + (res.data && res.data.message ? res.data.message : __('Error al cargar contactos', 'aura-suite')) + '</td></tr>');
                    if (typeof cb === 'function') cb(res);
                    return;
                }

                var data = res.data || {};
                state.items = data.items || [];
                state.totalItems = data.total || 0;
                state.totalPages = data.pages || 1;

                if (state.filterId > 0) {
                    var singleContact = (state.items && state.items.length) ? state.items[0] : null;
                    var contactName = '';
                    if (singleContact) {
                        var fn = ((singleContact.first_name || '') + ' ' + (singleContact.last_name || '')).trim();
                        contactName = fn || singleContact.organization || ('#' + singleContact.id);
                    }
                    $('#contacts-id-filter-label').text('#' + state.filterId + (contactName ? ' (' + contactName + ')' : ''));
                    $('#contacts-id-filter-banner').css('display', 'flex').show();
                } else {
                    $('#contacts-id-filter-banner').hide();
                }

                renderTableRows(state.items);
                renderPagination(data);
                refreshStats();

                if (typeof cb === 'function') {
                    cb(res);
                }
            },
            error: function() {
                state.isLoading = false;
                $tbody.html('<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 24px;">' + (cfg.i18n.connError || __('Error de conexión con el servidor', 'aura-suite')) + '</td></tr>');
                if (typeof cb === 'function') {
                    cb(null);
                }
            }
        });
    }

    // ── Renderizado de Filas de la Tabla ─────────────────────────────────────────
    function renderTableRows(items) {
        var $tbody = $('#contacts-tbody');
        if (!items || items.length === 0) {
            $tbody.html('<tr><td colspan="8" style="text-align: center; padding: 48px; color: #94a3b8;"><span style="font-size: 32px;">📇</span><p style="margin: 8px 0 0; font-weight: 600;">' + __('No se encontraron contactos', 'aura-suite') + '</p><p style="margin: 4px 0 0; font-size: 0.8rem;">' + __('Prueba con otros términos de búsqueda o registra un nuevo contacto.', 'aura-suite') + '</p></td></tr>');
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
                linkBadges += '<span class="entity-link-badge entity-link-badge--tp" title="' + escapeHtml(sprintf(__('Vinculado a Tercero ID #%s', 'aura-suite'), item.third_party_id)) + '">🏢 ' + __('Tercero', 'aura-suite') + '</span>';
            }
            if (item.wp_user_id && parseInt(item.wp_user_id, 10) > 0) {
                linkBadges += '<span class="entity-link-badge entity-link-badge--wp" title="' + escapeHtml(sprintf(__('Vinculado a Usuario WP ID #%s', 'aura-suite'), item.wp_user_id)) + '">👤 ' + __('Usuario WP', 'aura-suite') + '</span>';
            }
            if (!linkBadges) {
                linkBadges = '<span style="color:#64748b;font-size:0.75rem;">' + __('Sin vincular', 'aura-suite') + '</span>';
            }

            // Acciones
            var actionsHtml = '<div style="display:flex; justify-content:flex-end; gap:6px;">';
            actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.viewContactCard(' + item.id + ')" title="' + escapeHtml(__('Ver Tarjeta de Contacto', 'aura-suite')) + '" style="color:#38bdf8; border-color: rgba(56, 189, 248, 0.35);"><span class="dashicons dashicons-visibility"></span></button>';
            if (cfg.canEdit) {
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.editContact(' + item.id + ')" title="' + escapeHtml(__('Editar contacto', 'aura-suite')) + '"><span class="dashicons dashicons-edit"></span></button>';
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.openLinkEntityModal(' + item.id + ', \'' + fullName.replace(/'/g, "\\'") + '\')" title="' + escapeHtml(__('Vincular a Tercero / Usuario', 'aura-suite')) + '"><span class="dashicons dashicons-admin-links"></span></button>';
            }
            if (cfg.canSync && cfg.oauthConnected) {
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.syncSingleContact(' + item.id + ')" title="' + escapeHtml(__('Sincronizar con Google', 'aura-suite')) + '"><span class="dashicons dashicons-cloud"></span></button>';
            }
            if (cfg.canDelete) {
                actionsHtml += '<button type="button" class="btn btn-glass btn-xs" onclick="window.AuraContactsApp.deleteContact(' + item.id + ')" title="' + escapeHtml(__('Mover a papelera', 'aura-suite')) + '" style="color:#f87171;"><span class="dashicons dashicons-trash"></span></button>';
            }
            actionsHtml += '</div>';

            html += '<tr data-contact-id="' + item.id + '">';
            html += '<td style="text-align: center;"><input type="checkbox" class="aura-checkbox contact-row-check" value="' + item.id + '"></td>';
            html += '<td><div class="contact-avatar-wrap" onclick="window.AuraContactsApp.viewContactCard(' + item.id + ')" style="cursor: pointer;" title="' + escapeHtml(__('Ver Tarjeta de Contacto', 'aura-suite')) + '">' + avatarHtml + '<div><div class="contact-name-main" style="color:#fff; transition: color 0.15s ease;">' + fullName + '</div>' + (item.tags ? '<div class="contact-name-sub">' + renderTags(item.tags) + '</div>' : '') + '</div></div></td>';
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
            return '<span class="sync-badge sync-badge--synced" title="' + escapeHtml(__('Sincronizado con Google People API', 'aura-suite')) + '"><span class="dashicons dashicons-yes-alt" style="font-size:13px;width:13px;height:13px;"></span> ' + __('Sincronizado', 'aura-suite') + '</span>';
        } else if (status === 'pending') {
            return '<span class="sync-badge sync-badge--pending" title="' + escapeHtml(__('Cambios locales pendientes de subir a Google', 'aura-suite')) + '"><span class="dashicons dashicons-update" style="font-size:13px;width:13px;height:13px;"></span> ' + __('Pendiente', 'aura-suite') + '</span>';
        } else if (status === 'error') {
            return '<span class="sync-badge sync-badge--error" title="' + escapeHtml(item.last_sync_error || __('Error de sincronización', 'aura-suite')) + '"><span class="dashicons dashicons-warning" style="font-size:13px;width:13px;height:13px;"></span> ' + __('Error', 'aura-suite') + '</span>';
        }
        return '<span class="sync-badge sync-badge--not_synced"><span class="dashicons dashicons-minus" style="font-size:13px;width:13px;height:13px;"></span> ' + __('No vinculado', 'aura-suite') + '</span>';
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
        $('#contacts-pagination-info').text(sprintf(__('Mostrando %1$d - %2$d de %3$d contactos', 'aura-suite'), start, end, total));

        var $controls = $('#contacts-pagination-controls').empty();

        if (total === 0) {
            return;
        }

        // Botón Anterior
        var prevDisabled = (state.page <= 1);
        var $prev = $('<button type="button" class="btn btn-glass btn-xs' + (prevDisabled ? ' disabled' : '') + '" ' + 
            (prevDisabled ? 'disabled style="opacity:0.35;cursor:not-allowed;"' : 'style="cursor:pointer;"') + 
            ' title="' + escapeHtml(__('Página anterior', 'aura-suite')) + '"><span class="dashicons dashicons-arrow-left-alt2"></span></button>');
        
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
            ' title="' + escapeHtml(__('Página siguiente', 'aura-suite')) + '"><span class="dashicons dashicons-arrow-right-alt2"></span></button>');
        
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
            $firstLabel.html((cfg.i18n.contactPerson || __('Representante / Contacto directo', 'aura-suite')) + ' <span style="font-size:0.75rem;color:#94a3b8;font-weight:normal;">(' + __('Opcional', 'aura-suite') + ')</span>');
            $orgLabel.html((type === 'company' ? __('Razón Social / Empresa', 'aura-suite') : __('Nombre de la Organización / Institución', 'aura-suite')) + ' <span style="color:#ef4444;">*</span>');
            $('#contact-first-name').prop('required', false);
            $('#contact-organization').prop('required', true);
        } else {
            $iconBadge.html('<span class="dashicons dashicons-id-alt"></span>');
            $firstLabel.html((cfg.i18n.firstName || __('Nombre(s)', 'aura-suite')) + ' <span style="color:#ef4444;">*</span>');
            $orgLabel.html(cfg.i18n.organization || __('Organización / Empresa', 'aura-suite'));
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
                var manualUrl = prompt(__('Ingresa la URL de la foto o avatar del contacto:', 'aura-suite'));
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
                title: __('Seleccionar Foto o Logotipo del Contacto', 'aura-suite'),
                button: { text: __('Usar como Avatar', 'aura-suite') },
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

    /**
     * Configura y sincroniza una nube interactiva de etiquetas (tags chips)
     * vinculada a un input de texto.
     */
    function setupTagsCloud($cloudContainer, $inputElement) {
        if (!$cloudContainer.length || !$inputElement.length) return;

        var systemTags = Array.isArray(cfg.availableTags) ? cfg.availableTags : [];
        if (!systemTags.length) {
            $cloudContainer.html('<span style="font-size:0.75rem;color:#64748b;font-style:italic;">' + __('No hay etiquetas creadas aún. Escribe en el campo para crearlas.', 'aura-suite') + '</span>');
            return;
        }

        function getActiveTags() {
            var val = $inputElement.val() || '';
            return val.split(',')
                .map(function(t) { return t.trim(); })
                .filter(function(t) { return t.length > 0; });
        }

        function renderChips() {
            var activeTags = getActiveTags().map(function(t) { return t.toLowerCase(); });
            var html = '';

            systemTags.forEach(function(tag) {
                var isActive = activeTags.indexOf(tag.toLowerCase()) !== -1;
                var activeStyle = isActive
                    ? 'background: rgba(59, 130, 246, 0.25); border-color: #3b82f6; color: #60a5fa; font-weight: 600;'
                    : 'background: rgba(255, 255, 255, 0.04); border-color: rgba(255, 255, 255, 0.12); color: #cbd5e1;';

                html += '<button type="button" class="aura-tag-chip' + (isActive ? ' is-active' : '') + '" data-tag="' + escapeHtml(tag) + '" style="border-radius: 20px; padding: 3px 11px; font-size: 0.77rem; cursor: pointer; border: 1px solid; display: inline-flex; align-items: center; gap: 5px; transition: all 0.15s ease; user-select: none; ' + activeStyle + '">';
                html += (isActive ? '✓ ' : '+ ') + escapeHtml(tag);
                html += '</button>';
            });

            $cloudContainer.html(html);
        }

        renderChips();

        // Al hacer clic en un chip de la nube
        $cloudContainer.off('click', '.aura-tag-chip').on('click', '.aura-tag-chip', function(e) {
            e.preventDefault();
            var tagClicked = $(this).attr('data-tag');
            if (!tagClicked) return;

            var current = getActiveTags();
            var lowerList = current.map(function(t) { return t.toLowerCase(); });
            var pos = lowerList.indexOf(tagClicked.toLowerCase());

            if (pos !== -1) {
                current.splice(pos, 1);
            } else {
                current.push(tagClicked);
            }

            $inputElement.val(current.join(', ')).trigger('change');
            renderChips();
        });

        // Actualizar chips al tipear manualmente en el input
        $inputElement.off('input.tagsCloud').on('input.tagsCloud', function() {
            renderChips();
        });
    }

    function openCreateModal() {
        $('#contact-modal-title').html('📇 ' + (cfg.i18n.newContact || __('Nuevo Contacto', 'aura-suite')));
        $('#contact-modal-subtitle').text(__('Directorio institucional integrado con Google Contacts y el ecosistema Aura Suite.', 'aura-suite'));
        $('#contact-form-id').val(0);
        
        var form = document.getElementById('contact-edit-form');
        if (form && typeof form.reset === 'function') {
            form.reset();
        }

        $('#contact-avatar-url').val('');
        $('#contact-tags').val('');
        setupTagsCloud($('#contact-tags-cloud'), $('#contact-tags'));

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
        // Abrir modal de inmediato (0ms lag) con Skeleton en ola continua
        $('#contact-modal-title').html('✏️ ' + __('Cargando contacto...', 'aura-suite'));
        $('#contact-modal-subtitle').text(__('Consultando información del directorio...', 'aura-suite'));
        $('#modal-contact-form')
            .removeClass('is-hidden')
            .addClass('active')
            .css({ display: 'flex', opacity: 1, visibility: 'visible' });

        if (typeof AuraUI !== 'undefined') {
            AuraUI.showModalSkeleton('modal-contact-form', { async: true });
        }

        $.post(cfg.ajaxUrl, { action: 'aura_contacts_get_detail', nonce: cfg.nonce, id: id }, function(res) {
            if (!res.success || !res.data) {
                if (typeof AuraUI !== 'undefined') {
                    AuraUI.hideModalSkeleton('modal-contact-form');
                }
                closeContactModal();
                alert(__('No se pudo cargar la información del contacto.', 'aura-suite'));
                return;
            }
            var c = (res.data && res.data.contact) ? res.data.contact : res.data;
            if (!c || !c.id) {
                if (typeof AuraUI !== 'undefined') {
                    AuraUI.hideModalSkeleton('modal-contact-form');
                }
                closeContactModal();
                alert(__('No se pudo procesar la información del contacto.', 'aura-suite'));
                return;
            }
            var contactTitle = (c.first_name || '') + ' ' + (c.last_name || '');
            if (!contactTitle.trim()) contactTitle = c.organization || sprintf(__('Contacto #%s', 'aura-suite'), c.id);

            $('#contact-modal-title').html('✏️ ' + __('Editar Contacto:', 'aura-suite') + ' ' + escapeHtml(contactTitle));
            $('#contact-modal-subtitle').text(sprintf(__('ID #%1$s • Sincronización: %2$s', 'aura-suite'), c.id, (c.sync_status || 'pending').toUpperCase()));
            
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
            setupTagsCloud($('#contact-tags-cloud'), $('#contact-tags'));

            // Tercero vinculado
            if (c.third_party_id && parseInt(c.third_party_id, 10) > 0) {
                $('#contact-third-party-id').val(c.third_party_id);
                $('#contact-third-party-name').text(c.third_party_name || sprintf(__('Tercero #%s', 'aura-suite'), c.third_party_id));
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
                $('#contact-wp-user-name').text(c.wp_user_name || sprintf(__('Usuario WP #%s', 'aura-suite'), c.wp_user_id));
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

            // Ocultar Skeleton y revelar contenido fluido
            if (typeof AuraUI !== 'undefined') {
                AuraUI.hideModalSkeleton('modal-contact-form');
            }
        }).fail(function() {
            if (typeof AuraUI !== 'undefined') {
                AuraUI.hideModalSkeleton('modal-contact-form');
            }
            closeContactModal();
            alert(__('Error de conexión al cargar la información del contacto.', 'aura-suite'));
        });
    }

    function saveContact() {
        var contactType = $('#contact-type-input').val() || 'person';
        var firstName = ($('#contact-first-name').val() || '').trim();
        var org = ($('#contact-organization').val() || '').trim();

        if (contactType === 'person' && !firstName && !org) {
            alert(__('Por favor ingresa al menos el Nombre del contacto.', 'aura-suite'));
            $('#contact-first-name').focus();
            return;
        }

        if ((contactType === 'company' || contactType === 'organization') && !org && !firstName) {
            alert(__('Por favor ingresa la Razón Social o Nombre de la Organización.', 'aura-suite'));
            $('#contact-organization').focus();
            return;
        }

        var $btn = $('#btn-save-contact');
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Guardando...', 'aura-suite'));

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
                    showToast(cfg.i18n.saveSuccess || __('Contacto guardado correctamente.', 'aura-suite'));
                } else {
                    alert(res.data && res.data.message ? res.data.message : (cfg.i18n.saveError || __('Error al guardar', 'aura-suite')));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
                alert(cfg.i18n.connError || __('Error de conexión', 'aura-suite'));
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
    // ── Sincronización Progresiva con Google por Lotes (Protegida contra Timeouts) ──
    var syncProcess = {
        isRunning: false,
        isCancelled: false,
        phase: 'push',
        batchIndex: 0,
        totalTarget: 0,
        totalSuccess: 0,
        totalErrors: 0,
        totalPulled: 0,
        errorsList: []
    };

    function openSyncModal() {
        $('#modal-sync-progress').fadeIn(180).css('display', 'flex');
        $('#btn-sync-modal-finish').hide();
        $('#btn-sync-modal-cancel').show().prop('disabled', false).text(__('Detener', 'aura-suite'));
        $('#sync-modal-spinner').show().addClass('spin');
        $('#sync-modal-icon-wrap').css({ 'background': 'rgba(59, 130, 246, 0.2)', 'color': '#60a5fa' });
        $('#sync-modal-progress-bar').css({ 'width': '0%', 'background': 'linear-gradient(90deg, #3b82f6 0%, #06b6d4 100%)' });
        $('#sync-modal-percent-text').text('0%');
        $('#sync-modal-status-text').text(__('Calculando contactos pendientes de sincronización...', 'aura-suite'));
        $('#sync-kpi-total').text('0');
        $('#sync-kpi-success').text('0');
        $('#sync-kpi-pulled').text('0');
        $('#sync-kpi-errors').text('0');
        $('#sync-modal-log-console').html('<div style="color: #64748b;">' + __('Conectando con Google Contacts API...', 'aura-suite') + '</div>');
    }

    function closeSyncModal() {
        if (syncProcess.isRunning && !syncProcess.isCancelled) {
            if (!confirm(__('La sincronización se está ejecutando. ¿Deseas detenerla ahora?', 'aura-suite'))) {
                return;
            }
            syncProcess.isCancelled = true;
        }
        $('#modal-sync-progress').fadeOut(180);
        fetchContacts();
        refreshStats();
        if (state.activeTab === 'sync_logs') {
            fetchLogs();
        }
    }

    function cancelSync() {
        syncProcess.isCancelled = true;
        $('#btn-sync-modal-cancel').prop('disabled', true).text(__('Deteniendo...', 'aura-suite'));
        appendSyncLog(__('⚠️ Detención solicitada por el usuario. Los contactos ya subidos se conservan en Google.', 'aura-suite'), 'warning');
    }

    function appendSyncLog(msg, type) {
        var color = '#cbd5e1';
        if (type === 'error') color = '#f87171';
        else if (type === 'success') color = '#34d399';
        else if (type === 'warning') color = '#fbbf24';
        else if (type === 'info') color = '#38bdf8';

        var time = new Date().toLocaleTimeString();
        var line = '<div style="color: ' + color + '; margin-bottom: 3px;">[' + time + '] ' + escapeHtml(msg) + '</div>';
        var $console = $('#sync-modal-log-console');
        $console.append(line);
        $console.scrollTop($console[0].scrollHeight);
    }

    function startProgressiveGoogleSync() {
        if (syncProcess.isRunning) return;

        syncProcess.isRunning = true;
        syncProcess.isCancelled = false;
        syncProcess.phase = 'push';
        syncProcess.batchIndex = 0;
        syncProcess.totalTarget = 0;
        syncProcess.totalSuccess = 0;
        syncProcess.totalErrors = 0;
        syncProcess.totalPulled = 0;
        syncProcess.errorsList = [];

        openSyncModal();
        appendSyncLog(__('Iniciando sincronización por lotes (protegida contra límites de servidor)...', 'aura-suite'), 'info');

        processNextSyncBatch();
    }

    function processNextSyncBatch() {
        if (syncProcess.isCancelled) {
            finalizeSyncProcess(false, __('Sincronización detenida por el usuario.', 'aura-suite'));
            return;
        }

        syncProcess.batchIndex++;
        $('#sync-live-batch-tag').text(sprintf(__('Lote %d', 'aura-suite'), syncProcess.batchIndex));

        if (syncProcess.phase === 'push') {
            $('#sync-modal-phase-badge').removeClass('aura-badge--success aura-badge--warning').addClass('aura-badge--info').text(__('Fase 1: Subida (Push)', 'aura-suite'));
            $('#sync-modal-status-text').text(sprintf(__('Subiendo contactos hacia Google (Lote %d)...', 'aura-suite'), syncProcess.batchIndex));
        } else if (syncProcess.phase === 'pull') {
            $('#sync-modal-phase-badge').removeClass('aura-badge--info aura-badge--warning').addClass('aura-badge--success').text(__('Fase 2: Descarga (Pull)', 'aura-suite'));
            $('#sync-modal-status-text').text(__('Recibiendo cambios desde Google Contacts...', 'aura-suite'));
        }

        $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_contacts_sync_batch',
                nonce: cfg.nonce,
                phase: syncProcess.phase,
                direction: 'both',
                batch_size: 10
            },
            timeout: 65000
        }).done(function(res) {
            if (!res || !res.success) {
                var errTxt = (res && res.data && res.data.message) ? res.data.message : __('Aviso al procesar lote en Google API.', 'aura-suite');
                appendSyncLog('⚠️ ' + errTxt, 'error');
                syncProcess.totalErrors += 1;
                $('#sync-kpi-errors').text(syncProcess.totalErrors);

                if (syncProcess.phase === 'push') {
                    appendSyncLog(__('Pasando a fase de verificación y descarga (Pull)...', 'aura-suite'), 'warning');
                    syncProcess.phase = 'pull';
                    setTimeout(processNextSyncBatch, 1000);
                } else {
                    finalizeSyncProcess(false, errTxt);
                }
                return;
            }

            var d = res.data;

            if (d.phase === 'push') {
                if (syncProcess.totalTarget === 0 && d.total_target) {
                    syncProcess.totalTarget = d.total_target;
                    $('#sync-kpi-total').text(syncProcess.totalTarget);
                } else if (syncProcess.totalTarget === 0 && d.initial_pending) {
                    syncProcess.totalTarget = d.initial_pending;
                    $('#sync-kpi-total').text(syncProcess.totalTarget);
                }

                syncProcess.totalSuccess += (d.success_in_batch || 0);
                syncProcess.totalErrors += (d.errors_in_batch || 0);

                $('#sync-kpi-success').text(syncProcess.totalSuccess);
                $('#sync-kpi-errors').text(syncProcess.totalErrors);

                if (d.error_messages && d.error_messages.length) {
                    $.each(d.error_messages, function(i, msg) {
                        appendSyncLog(sprintf(__('Aviso: %s', 'aura-suite'), msg), 'warning');
                        syncProcess.errorsList.push(msg);
                    });
                }

                // Cálculo de porcentaje dinámico
                var target = syncProcess.totalTarget || 1;
                var remaining = (d.remaining_pending !== undefined) ? d.remaining_pending : 0;
                var currentDone = Math.max(0, target - remaining);
                var pct = Math.min(92, Math.max(5, Math.round((currentDone / target) * 88)));
                $('#sync-modal-progress-bar').css('width', pct + '%');
                $('#sync-modal-percent-text').text(pct + '%');

                appendSyncLog(sprintf(__('Lote %1$d: %2$d subidos exitosamente. Pendientes: %3$d', 'aura-suite'), syncProcess.batchIndex, (d.success_in_batch || 0), remaining), 'success');

                if (d.is_push_completed || remaining === 0 || d.next_phase === 'pull') {
                    syncProcess.phase = 'pull';
                    appendSyncLog(__('Fase 1 (Push) concluida. Verificando cambios en Google (Pull)...', 'aura-suite'), 'info');
                    setTimeout(processNextSyncBatch, 800);
                } else {
                    // Pequeña pausa de 600ms entre lotes para no saturar cuota de Google
                    setTimeout(processNextSyncBatch, 600);
                }
            } else if (d.phase === 'pull') {
                syncProcess.totalPulled += (d.pulled_created || 0) + (d.pulled_updated || 0);
                $('#sync-kpi-pulled').text(syncProcess.totalPulled);

                if (d.pulled_errors) {
                    syncProcess.totalErrors += d.pulled_errors;
                    $('#sync-kpi-errors').text(syncProcess.totalErrors);
                }

                if (d.error_messages && d.error_messages.length) {
                    $.each(d.error_messages, function(i, msg) {
                        appendSyncLog(sprintf(__('Aviso Pull: %s', 'aura-suite'), msg), 'warning');
                    });
                }

                $('#sync-modal-progress-bar').css({ 'width': '100%', 'background': 'linear-gradient(90deg, #10b981 0%, #06b6d4 100%)' });
                $('#sync-modal-percent-text').text('100%');
                appendSyncLog(sprintf(__('Fase 2 (Pull) completada: %1$d nuevos, %2$d actualizados.', 'aura-suite'), (d.pulled_created || 0), (d.pulled_updated || 0)), 'success');

                finalizeSyncProcess(true, __('¡Sincronización bidireccional completada con éxito!', 'aura-suite'));
            }
        }).fail(function(xhr, status, error) {
            appendSyncLog(sprintf(__('Aviso de red en Lote %1$d: %2$s', 'aura-suite'), syncProcess.batchIndex, (xhr.statusText || error || __('Conexión lenta', 'aura-suite'))), 'warning');
            if (syncProcess.batchIndex < 35 && !syncProcess.isCancelled) {
                appendSyncLog(__('Reintentando siguiente lote en 2 segundos...', 'aura-suite'), 'info');
                setTimeout(processNextSyncBatch, 2000);
            } else {
                finalizeSyncProcess(false, __('Se completó la sincronización parcial de lotes.', 'aura-suite'));
            }
        });
    }

    function finalizeSyncProcess(isSuccess, finalMsg) {
        syncProcess.isRunning = false;
        $('#sync-modal-spinner').removeClass('spin').hide();
        $('#btn-sync-modal-cancel').hide();
        $('#btn-sync-modal-finish').show();

        if (isSuccess) {
            $('#sync-modal-icon-wrap').css({ 'background': 'rgba(16, 185, 129, 0.2)', 'color': '#10b981' });
            $('#sync-modal-phase-badge').removeClass('aura-badge--info aura-badge--warning').addClass('aura-badge--success').text(__('Completado', 'aura-suite'));
            $('#sync-modal-status-text').text(finalMsg || __('¡Sincronización finalizada con éxito!', 'aura-suite'));
            $('#sync-modal-progress-bar').css({ 'width': '100%', 'background': 'linear-gradient(90deg, #10b981 0%, #059669 100%)' });
            $('#sync-modal-percent-text').text('100%');
            appendSyncLog(__('🎉 Proceso de sincronización completado.', 'aura-suite'), 'success');
            showToast(__('¡Sincronización con Google Contacts completada exitosamente!', 'aura-suite'));
        } else {
            $('#sync-modal-icon-wrap').css({ 'background': 'rgba(245, 158, 11, 0.2)', 'color': '#f59e0b' });
            $('#sync-modal-phase-badge').removeClass('aura-badge--info aura-badge--success').addClass('aura-badge--warning').text(__('Finalizado', 'aura-suite'));
            $('#sync-modal-status-text').text(finalMsg || __('Sincronización procesada.', 'aura-suite'));
            appendSyncLog('ℹ️ ' + finalMsg, 'warning');
        }

        fetchContacts();
        refreshStats();
        if (state.activeTab === 'sync_logs') {
            fetchLogs();
        }
    }

    function initGlobalActions() {
        $('#btn-sync-all, #btn-sync-all-from-logs').on('click', function() {
            startProgressiveGoogleSync();
        });

        $('#btn-refresh-logs').on('click', function() {
            fetchLogs();
        });

        // ── Sincronización Manual Lote: Usuarios de WordPress (Fase 4 P2) ──
        $('#btn-sync-wp-users').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Sincronizando...', 'aura-suite'));

            $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_wp_users', nonce: cfg.nonce }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    var msg = res.data && res.data.message ? res.data.message : __('Usuarios sincronizados exitosamente.', 'aura-suite');
                    showToast(msg);
                    fetchContacts();
                    refreshStats();
                } else {
                    alert(res.data && res.data.message ? res.data.message : __('Error al sincronizar usuarios de WordPress', 'aura-suite'));
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert(__('Error de conexión al sincronizar usuarios de WordPress', 'aura-suite'));
            });
        });

        // ── Sincronización Manual Lote: Terceros de Aura (Fase 4 P2) ──
        $('#btn-sync-third-parties').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Sincronizando...', 'aura-suite'));

            $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_third_parties', nonce: cfg.nonce }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    var msg = res.data && res.data.message ? res.data.message : __('Terceros sincronizados exitosamente.', 'aura-suite');
                    showToast(msg);
                    fetchContacts();
                    refreshStats();
                } else {
                    alert(res.data && res.data.message ? res.data.message : __('Error al sincronizar terceros', 'aura-suite'));
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert(__('Error de conexión al sincronizar terceros', 'aura-suite'));
            });
        });

        // ── Sincronización Manual Lote: Estudiantes de Aura (Fase 4 P3) ──
        $('#btn-sync-students').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Sincronizando...', 'aura-suite'));

            $.post(cfg.ajaxUrl, { action: 'aura_contacts_sync_students', nonce: cfg.nonce }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    var msg = res.data && res.data.message ? res.data.message : __('Estudiantes sincronizados exitosamente.', 'aura-suite');
                    showToast(msg);
                    fetchContacts();
                    refreshStats();
                } else {
                    alert(res.data && res.data.message ? res.data.message : __('Error al sincronizar estudiantes', 'aura-suite'));
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert(__('Error de conexión al sincronizar estudiantes', 'aura-suite'));
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
                showToast(__('Contacto sincronizado con Google exitosamente.', 'aura-suite'));
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al sincronizar contacto con Google', 'aura-suite'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            alert(__('Error de conexión', 'aura-suite'));
        });
    }

    function deleteContactItem(id) {
        if (!confirm(cfg.i18n.confirmDelete || __('¿Estás seguro de mover este contacto a la papelera?', 'aura-suite'))) {
            return;
        }

        $.post(cfg.ajaxUrl, { action: 'aura_contacts_delete', nonce: cfg.nonce, id: id, hard: 0 }, function(res) {
            if (res.success) {
                showToast(__('Contacto movido a la papelera.', 'aura-suite'));
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al eliminar contacto', 'aura-suite'));
            }
        });
    }

    // ── Historial y Registro de Auditoría de Sincronización (Audit v2) ──────────
    var logsState = {
        page: 1,
        perPage: 25,
        status: '',
        direction: '',
        search: '',
        totalItems: 0,
        totalPages: 1,
        isLoading: false,
        rawLogs: []
    };

    var logsSearchTimer = null;

    function initSyncLogs() {
        // Filtro por Estado
        $('#logs-filter-status').on('change', function() {
            logsState.status = $(this).val();
            logsState.page = 1;
            fetchLogs(1);
        });

        // Filtro por Dirección
        $('#logs-filter-direction').on('change', function() {
            logsState.direction = $(this).val();
            logsState.page = 1;
            fetchLogs(1);
        });

        // Selector por Página
        $('#logs-filter-per-page').on('change', function() {
            logsState.perPage = parseInt($(this).val(), 10) || 25;
            logsState.page = 1;
            fetchLogs(1);
        });

        // Buscador reactivo de logs con debounce
        $('#logs-filter-search').on('input', function() {
            var val = $(this).val();
            clearTimeout(logsSearchTimer);
            logsSearchTimer = setTimeout(function() {
                logsState.search = val.trim();
                logsState.page = 1;
                fetchLogs(1);
            }, 320);
        });

        // Clic en la tarjeta de fallas/errores para filtrar automáticamente
        $('#logs-kpi-errors-card').on('click', function() {
            $('#logs-filter-status').val('error');
            logsState.status = 'error';
            logsState.page = 1;
            fetchLogs(1);
        });

        // Botón Copiar Diagnóstico en el modal
        $('#btn-copy-log-detail').on('click', function() {
            var text = $('#log-detail-message').text();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showToast(__('Diagnóstico copiado al portapapeles.', 'aura-suite'));
                }).catch(function() {
                    showToast(__('Error al copiar automáticamente. Por favor selecciónalo y cópialo manualmente.', 'aura-suite'));
                });
            } else {
                showToast(__('Selecciona el texto y usa Ctrl+C para copiarlo.', 'aura-suite'));
            }
        });
    }

    function fetchLogs(page) {
        if (typeof page === 'number') {
            logsState.page = page;
        }

        var $tbody = $('#contacts-logs-tbody');
        $tbody.html('<tr><td colspan="7" style="text-align: center; padding: 28px; color:#cbd5e1;"><span class="dashicons dashicons-update spin"></span> ' + __('Cargando registros de auditoría...', 'aura-suite') + '</td></tr>');

        logsState.isLoading = true;

        $.post(cfg.ajaxUrl, {
            action: 'aura_contacts_get_sync_logs',
            nonce: cfg.nonce,
            page: logsState.page,
            per_page: logsState.perPage,
            status: logsState.status,
            direction: logsState.direction,
            search: logsState.search
        }, function(res) {
            logsState.isLoading = false;

            if (!res.success) {
                var errMsg = (res.data && res.data.message) ? res.data.message : __('Error al cargar logs', 'aura-suite');
                $tbody.html('<tr><td colspan="7" style="text-align: center; padding: 30px; color:#f87171;">' + escapeHtml(errMsg) + '</td></tr>');
                return;
            }

            var d = res.data || {};
            var logs = d.logs || [];
            logsState.rawLogs = logs;
            logsState.totalItems = d.total_items || 0;
            logsState.totalPages = d.total_pages || 1;
            logsState.page = d.current_page || 1;

            // Actualizar KPIs superiores
            if (d.stats) {
                $('#logs-kpi-total').text(d.stats.total_all || 0);
                $('#logs-kpi-success').text((d.stats.total_success || 0) + ' (' + (d.stats.success_rate || 100) + '%)');
                $('#logs-kpi-errors').text(d.stats.total_errors || 0);
                $('#logs-kpi-last-sync').text(d.stats.last_sync ? d.stats.last_sync : __('Sin registros', 'aura-suite'));
            }

            // Renderizar Filas de la Tabla
            if (logs.length === 0) {
                var emptyMsg = logsState.search || logsState.status || logsState.direction
                    ? __('No se encontraron registros de auditoría que coincidan con los filtros aplicados.', 'aura-suite')
                    : __('No hay eventos de sincronización registrados aún.', 'aura-suite');
                $tbody.html('<tr><td colspan="7" style="text-align: center; padding: 36px; color:#94a3b8;">' + escapeHtml(emptyMsg) + '</td></tr>');
                $('#logs-pagination-container').hide();
                return;
            }

            $('#logs-pagination-container').show();

            var html = '';
            logs.forEach(function(l, idx) {
                var isPush = (l.direction === 'push');
                var dirBadge = isPush
                    ? '<span class="badge-sync-direction is-push"><span class="dashicons dashicons-arrow-up-alt"></span> ' + __('Local ➔ Google', 'aura-suite') + '</span>'
                    : '<span class="badge-sync-direction is-pull"><span class="dashicons dashicons-arrow-down-alt"></span> ' + __('Google ➔ Local', 'aura-suite') + '</span>';

                var isSuccess = (l.status === 'success');
                var statusBadge = isSuccess
                    ? '<span class="sync-badge sync-badge--synced" style="display:inline-flex;align-items:center;gap:3px;"><span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;"></span> ' + __('Éxito', 'aura-suite') + '</span>'
                    : '<span class="sync-badge sync-badge--error" style="display:inline-flex;align-items:center;gap:3px;"><span class="dashicons dashicons-no-alt" style="font-size:14px;width:14px;height:14px;"></span> ' + __('Fallo', 'aura-suite') + '</span>';

                var actionLabel = escapeHtml(l.action || '');
                if (l.action === 'create') actionLabel = '✨ ' + __('Crear', 'aura-suite');
                else if (l.action === 'update') actionLabel = '🔄 ' + __('Actualizar', 'aura-suite');
                else if (l.action === 'delete') actionLabel = '🗑️ ' + __('Eliminar', 'aura-suite');
                else if (l.action === 'skip') actionLabel = '⏭️ ' + __('Omitir', 'aura-suite');

                // Ficha de contacto
                var contactHtml = '—';
                if (l.contact_id) {
                    var cName = (l.first_name || l.last_name) ? (l.first_name + ' ' + (l.last_name || '')).trim() : sprintf(__('Contacto #%s', 'aura-suite'), l.contact_id);
                    var cEmail = l.email_primary || '';
                    contactHtml = '<div style="display:flex;align-items:center;gap:8px;min-width:0;">';
                    if (l.avatar_url) {
                        contactHtml += '<img src="' + escapeHtml(l.avatar_url) + '" style="width:24px;height:24px;border-radius:50%;object-fit:cover;flex-shrink:0;">';
                    } else {
                        contactHtml += '<div style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,0.08);color:#94a3b8;display:flex;align-items:center;justify-content:center;font-size:0.7rem;flex-shrink:0;">👤</div>';
                    }
                    contactHtml += '<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;">';
                    contactHtml += '<strong style="color:#fff;font-size:0.8rem;white-space:nowrap;">#' + l.contact_id + ' ' + escapeHtml(cName) + '</strong>';
                    if (cEmail) {
                        contactHtml += '<div style="color:#94a3b8;font-size:0.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(cEmail) + '</div>';
                    }
                    contactHtml += '</div></div>';
                }

                var msgFull = l.message || l.error_message || l.details || '—';
                var msgShort = msgFull.length > 95 ? msgFull.substring(0, 95) + '...' : msgFull;

                html += '<tr>';
                html += '<td style="font-size:0.78rem;color:#cbd5e1;white-space:nowrap;">' + escapeHtml(l.created_at || '') + '</td>';
                html += '<td>' + dirBadge + '</td>';
                html += '<td><span class="badge-sync-action">' + actionLabel + '</span></td>';
                html += '<td>' + contactHtml + '</td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td style="font-size:0.78rem;color:' + (isSuccess ? '#94a3b8' : '#f87171') + ';word-break:break-word;" title="' + escapeHtml(msgFull) + '">' + escapeHtml(msgShort) + '</td>';
                html += '<td style="text-align:center;">';
                html += '<button type="button" class="btn btn-glass btn-sm btn-view-log-detail" data-idx="' + idx + '" title="' + escapeHtml(__('Ver diagnóstico detallado', 'aura-suite')) + '" style="padding:2px 6px;height:26px;border-radius:6px;">';
                html += '<span class="dashicons dashicons-visibility" style="font-size:15px;width:15px;height:15px;margin-top:2px;"></span>';
                html += '</button>';
                html += '</td>';
                html += '</tr>';
            });

            $tbody.html(html);

            // Listener de apertura de modal de detalle
            $tbody.find('.btn-view-log-detail').on('click', function() {
                var idx = $(this).data('idx');
                var log = logsState.rawLogs[idx];
                if (log) {
                    openLogDetailModal(log);
                }
            });

            // Renderizar controles de paginación
            renderLogsPagination(logsState.totalItems, logsState.totalPages, logsState.page, logsState.perPage);

        }).fail(function() {
            logsState.isLoading = false;
            $tbody.html('<tr><td colspan="7" style="text-align: center; padding: 30px; color:#f87171;">' + __('Error de comunicación con el servidor al cargar registros.', 'aura-suite') + '</td></tr>');
        });
    }

    function renderLogsPagination(totalItems, totalPages, currentPage, perPage) {
        var start = totalItems > 0 ? (currentPage - 1) * perPage + 1 : 0;
        var end = Math.min(currentPage * perPage, totalItems);

        $('#logs-pagination-info').html(
            sprintf(
                __('Mostrando %1$s a %2$s de %3$s registros', 'aura-suite'),
                '<strong style="color:#fff;">' + start + '</strong>',
                '<strong style="color:#fff;">' + end + '</strong>',
                '<strong style="color:#38bdf8;">' + totalItems + '</strong>'
            )
        );

        var $ctrls = $('#logs-pagination-controls');
        $ctrls.empty();

        if (totalPages <= 1) {
            return;
        }

        // Botón Anterior
        var $btnPrev = $('<button type="button" class="logs-page-btn" title="' + escapeHtml(__('Página anterior', 'aura-suite')) + '"><span class="dashicons dashicons-arrow-left-alt2" style="font-size:14px;width:14px;height:14px;margin-top:2px;"></span></button>');
        if (currentPage <= 1) {
            $btnPrev.prop('disabled', true);
        } else {
            $btnPrev.on('click', function() { fetchLogs(currentPage - 1); });
        }
        $ctrls.append($btnPrev);

        // Páginas numéricas (máx 5 botones visibles con elipsis)
        var maxButtons = 5;
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        if (startPage > 1) {
            var $btnFirst = $('<button type="button" class="logs-page-btn">1</button>').on('click', function() { fetchLogs(1); });
            $ctrls.append($btnFirst);
            if (startPage > 2) {
                $ctrls.append('<span style="color:#64748b;padding:0 2px;">...</span>');
            }
        }

        for (var p = startPage; p <= endPage; p++) {
            (function(pageNum) {
                var $btnPage = $('<button type="button" class="logs-page-btn' + (pageNum === currentPage ? ' is-active' : '') + '">' + pageNum + '</button>');
                if (pageNum === currentPage) {
                    $btnPage.prop('disabled', true);
                } else {
                    $btnPage.on('click', function() { fetchLogs(pageNum); });
                }
                $ctrls.append($btnPage);
            })(p);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                $ctrls.append('<span style="color:#64748b;padding:0 2px;">...</span>');
            }
            var $btnLast = $('<button type="button" class="logs-page-btn">' + totalPages + '</button>').on('click', function() { fetchLogs(totalPages); });
            $ctrls.append($btnLast);
        }

        // Botón Siguiente
        var $btnNext = $('<button type="button" class="logs-page-btn" title="' + escapeHtml(__('Página siguiente', 'aura-suite')) + '"><span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px;width:14px;height:14px;margin-top:2px;"></span></button>');
        if (currentPage >= totalPages) {
            $btnNext.prop('disabled', true);
        } else {
            $btnNext.on('click', function() { fetchLogs(currentPage + 1); });
        }
        $ctrls.append($btnNext);
    }

    function openLogDetailModal(log) {
        var metaHtml = '';
        metaHtml += '<div><span style="color:#94a3b8;">' + __('Log ID:', 'aura-suite') + '</span> <strong style="color:#fff;">#' + (log.id || '—') + '</strong></div>';
        metaHtml += '<div><span style="color:#94a3b8;">' + __('Fecha:', 'aura-suite') + '</span> <strong style="color:#fff;">' + escapeHtml(log.created_at || '—') + '</strong></div>';
        metaHtml += '<div><span style="color:#94a3b8;">' + __('Dirección:', 'aura-suite') + '</span> <strong style="color:#38bdf8;">' + (log.direction === 'push' ? __('Local ➔ Google (Push)', 'aura-suite') : __('Google ➔ Local (Pull)', 'aura-suite')) + '</strong></div>';
        metaHtml += '<div><span style="color:#94a3b8;">' + __('Acción:', 'aura-suite') + '</span> <strong style="color:#a78bfa;">' + escapeHtml(log.action || '—') + '</strong></div>';
        metaHtml += '<div><span style="color:#94a3b8;">' + __('Estado:', 'aura-suite') + '</span> <strong style="color:' + (log.status === 'success' ? '#34d399' : '#f87171') + ';">' + (log.status === 'success' ? __('Éxito', 'aura-suite') : __('Fallo / Error', 'aura-suite')) + '</strong></div>';
        metaHtml += '<div><span style="color:#94a3b8;">' + __('Contacto ID:', 'aura-suite') + '</span> <strong style="color:#fff;">' + (log.contact_id ? '#' + log.contact_id : __('Sin contacto asociado', 'aura-suite')) + '</strong></div>';
        if (log.first_name || log.last_name) {
            metaHtml += '<div style="grid-column: 1 / -1;"><span style="color:#94a3b8;">' + __('Nombre Contacto:', 'aura-suite') + '</span> <strong style="color:#fff;">' + escapeHtml((log.first_name || '') + ' ' + (log.last_name || '')) + '</strong>' + (log.email_primary ? ' &lt;' + escapeHtml(log.email_primary) + '&gt;' : '') + '</div>';
        }
        if (log.google_account) {
            metaHtml += '<div style="grid-column: 1 / -1;"><span style="color:#94a3b8;">' + __('Cuenta Google:', 'aura-suite') + '</span> <strong style="color:#fbbf24;">' + escapeHtml(log.google_account) + '</strong></div>';
        }

        $('#log-detail-meta').html(metaHtml);
        $('#log-detail-message').text(log.message || log.error_message || __('Sin mensaje adicional.', 'aura-suite'));

        $('#modal-log-detail').css('display', 'flex').hide().fadeIn(140);
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
                    showToast(sprintf(__('Modo de autenticación cambiado a %s', 'aura-suite'), (selectedMode === 'service_account' ? 'Google Workspace DWD' : 'OAuth 2.0')));
                }
            });
        });

        // Guardar credenciales OAuth individual
        $('#contacts-google-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#btn-save-google-credentials');
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Guardando...', 'aura-suite'));

            var data = {
                action: 'aura_contacts_save_google_settings',
                nonce: cfg.nonce,
                client_id: $('#google_client_id').val().trim(),
                client_secret: $('#google_client_secret').val().trim()
            };

            $.post(cfg.ajaxUrl, data, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    alert(__('Credenciales de Google OAuth guardadas exitosamente. La página se recargará para actualizar las URLs de autorización.', 'aura-suite'));
                    window.location.reload();
                } else {
                    alert(res.data && res.data.message ? res.data.message : __('Error al guardar credenciales', 'aura-suite'));
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert(__('Error de conexión', 'aura-suite'));
            });
        });

        // Guardar credenciales de Service Account DWD
        $('#contacts-sa-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#btn-save-sa-credentials');
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Guardando...', 'aura-suite'));

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_save_sa_settings',
                nonce: cfg.nonce,
                sa_json: $('#sa_json_credentials').val().trim(),
                delegated_user: $('#sa_delegated_user').val().trim()
            }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    alert(__('Credenciales de Service Account DWD guardadas exitosamente.', 'aura-suite'));
                    window.location.reload();
                } else {
                    alert(res.data && res.data.message ? res.data.message : __('Error al guardar credenciales DWD', 'aura-suite'));
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert(__('Error de conexión', 'aura-suite'));
            });
        });

        // Probar conexión en vivo de Service Account DWD
        $('#btn-test-sa-connection').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Probando conexión...', 'aura-suite'));

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_test_sa_connection',
                nonce: cfg.nonce
            }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (res.success) {
                    alert(__('✅ Conexión con Google People API exitosa:', 'aura-suite') + '\n' + (res.data.message || __('Token DWD generado y People API accesible', 'aura-suite')));
                } else {
                    alert(__('❌ Error al conectar con Google:', 'aura-suite') + '\n' + (res.data && res.data.message ? res.data.message : __('No se pudo autorizar', 'aura-suite')));
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert(__('Error de conexión al servidor', 'aura-suite'));
            });
        });

        $('#btn-disconnect-google').on('click', function() {
            if (!confirm(cfg.i18n.confirmDisconnect || __('¿Estás seguro de desconectar la cuenta de Google?', 'aura-suite'))) {
                return;
            }
            $.post(cfg.ajaxUrl, { action: 'aura_contacts_disconnect_google', nonce: cfg.nonce }, function(res) {
                if (res.success) {
                    alert(__('Cuenta de Google desconectada correctamente.', 'aura-suite'));
                    window.location.reload();
                } else {
                    alert(res.data && res.data.message ? res.data.message : __('Error al desconectar', 'aura-suite'));
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

    // ── Módulo de Portabilidad, Importación y Exportación Masiva ─────────────────
    function initImportExport() {
        // Formatos de exportación y textos dinámicos
        var exportFormatLabels = {
            'csv_universal': {
                text: __('Descargar Archivo CSV Universal', 'aura-suite'),
                action: 'aura_contacts_export_csv',
                icon: 'dashicons-media-spreadsheet'
            },
            'csv_google': {
                text: __('Descargar CSV Oficial Google Contacts', 'aura-suite'),
                action: 'aura_contacts_export_google_csv',
                icon: 'dashicons-google'
            },
            'vcf': {
                text: __('Descargar vCard 3.0 (.vcf)', 'aura-suite'),
                action: 'aura_contacts_export_vcf',
                icon: 'dashicons-id'
            },
            'json': {
                text: __('Descargar Respaldo JSON Completo', 'aura-suite'),
                action: 'aura_contacts_export_json',
                icon: 'dashicons-backup'
            }
        };

        // Cambio visual de formato de exportación
        $('input[name="export_format"]').on('change', function() {
            var selectedFormat = $(this).val();
            
            $('.export-format-card').removeClass('is-selected').css({
                'border-color': 'rgba(255, 255, 255, 0.1)',
                'background': 'rgba(255, 255, 255, 0.03)'
            });
            $(this).closest('.export-format-card').addClass('is-selected').css({
                'border-color': 'rgba(56, 189, 248, 0.7)',
                'background': 'rgba(56, 189, 248, 0.12)'
            });

            var conf = exportFormatLabels[selectedFormat] || exportFormatLabels['csv_universal'];
            $('#btn-export-text').text(conf.text);

            // Mostrar delimitador solo si es CSV Universal
            if (selectedFormat === 'csv_universal') {
                $('#export-csv-options').slideDown(150);
            } else {
                $('#export-csv-options').slideUp(150);
            }
        });

        // Botón principal de Exportar
        $('#btn-do-export').on('click', function(e) {
            e.preventDefault();
            var format = $('input[name="export_format"]:checked').val() || 'csv_universal';
            var cat = $('#export_filter_category').val() || '';
            var syncStatus = $('#export_filter_sync').val() || '';
            var delimiter = $('input[name="export_delimiter"]:checked').val() || ',';

            var conf = exportFormatLabels[format] || exportFormatLabels['csv_universal'];
            var postUrl = cfg.postUrl || (cfg.ajaxUrl ? cfg.ajaxUrl.replace('admin-ajax.php', 'admin-post.php') : 'admin-post.php');
            var nonce = cfg.exportNonce || cfg.nonce || '';

            var url = postUrl + '?action=' + encodeURIComponent(conf.action) +
                      '&nonce=' + encodeURIComponent(nonce) +
                      '&category=' + encodeURIComponent(cat) +
                      '&sync_status=' + encodeURIComponent(syncStatus);

            if (format === 'csv_universal') {
                url += '&delimiter=' + encodeURIComponent(delimiter);
            }

            showToast(sprintf(__('Generando descarga de %s...', 'aura-suite'), conf.text));
            window.location.href = url;
        });

        // Botón Descargar Plantilla CSV Oficial
        $('#btn-download-csv-template').on('click', function(e) {
            e.preventDefault();
            var postUrl = cfg.postUrl || (cfg.ajaxUrl ? cfg.ajaxUrl.replace('admin-ajax.php', 'admin-post.php') : 'admin-post.php');
            var nonce = cfg.exportNonce || cfg.nonce || '';
            var url = postUrl + '?action=aura_contacts_download_template&nonce=' + encodeURIComponent(nonce);
            showToast(__('Descargando plantilla CSV oficial de ejemplo...', 'aura-suite'));
            window.location.href = url;
        });

        // ── Drag & Drop y Previsualización de Archivo ──────────────────────────
        var $dropzone = $('#import-dropzone');
        var fileInput = document.getElementById('import_file_input');

        function updateDropzonePreview(file) {
            if (!file) {
                $('#dropzone-selected').hide();
                $('#dropzone-idle').show();
                return;
            }

            var name = file.name || __('archivo', 'aura-suite');
            var size = file.size ? (file.size > 1048576 ? (file.size / 1048576).toFixed(2) + ' MB' : (file.size / 1024).toFixed(1) + ' KB') : '0 KB';
            var ext = name.split('.').pop().toLowerCase();

            var icon = '📄';
            if (ext === 'csv') icon = '📊';
            else if (ext === 'vcf' || ext === 'vcard') icon = '📇';
            else if (ext === 'json') icon = '📦';

            $('#file-icon-badge').text(icon);
            $('#file-name-display').text(name);
            $('#file-size-display').text(size + ' — ' + __('Archivo listo para importar', 'aura-suite'));

            $('#dropzone-idle').hide();
            $('#dropzone-selected').css('display', 'flex').hide().fadeIn(180);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length) {
                    updateDropzonePreview(this.files[0]);
                } else {
                    updateDropzonePreview(null);
                }
            });
        }

        // Eventos visuales de arrastrar archivo
        $dropzone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.addClass('is-dragover');
        });

        $dropzone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.removeClass('is-dragover');
        });

        $dropzone.on('drop', function(e) {
            var dt = e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                fileInput.files = dt.files;
                updateDropzonePreview(dt.files[0]);
            }
        });

        $('#btn-clear-file').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (fileInput) fileInput.value = '';
            updateDropzonePreview(null);
        });

        // ── Envío de Formulario de Importación ────────────────────────────────
        $('#form-contacts-import').on('submit', function(e) {
            e.preventDefault();
            if (!fileInput || !fileInput.files.length) {
                alert(__('Por favor arrastra o selecciona un archivo (.csv, .vcf o .json) para importar.', 'aura-suite'));
                return;
            }

            var formData = new FormData(this);
            formData.append('action', 'aura_contacts_import_file');
            formData.append('nonce', cfg.nonce);
            formData.append('strategy', $('#import_duplicate_strategy').val() || 'update');
            formData.append('default_category', $('#import_default_category').val() || 'other');
            formData.append('mark_for_google_sync', $('#import_mark_google_sync').is(':checked') ? '1' : '0');

            var $btn = $('#btn-submit-import');
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Procesando importación...', 'aura-suite'));

            $.ajax({
                url: cfg.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    $btn.prop('disabled', false).html(origHtml);
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

                        $('#import-results-card').slideDown(250);

                        showToast(sprintf(__('Importación completada: %1$d creados, %2$d actualizados.', 'aura-suite'), (d.created || 0), (d.updated || 0)));
                        fetchContacts();
                        refreshStats();
                    } else {
                        var msg = (res.data && res.data.message) ? res.data.message : __('Error al procesar el archivo de importación.', 'aura-suite');
                        alert(msg);
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(origHtml);
                    alert(__('Error en la comunicación con el servidor al procesar la importación.', 'aura-suite'));
                }
            });
        });

        // Copiar detalle de importación al portapapeles
        $('#btn-copy-import-logs').on('click', function() {
            var lines = [];
            $('#res-details-list li').each(function() {
                lines.push($(this).text());
            });
            if (!lines.length) {
                showToast(__('No hay detalles para copiar.', 'aura-suite'));
                return;
            }
            var textToCopy = '--- ' + __('REGISTRO DE IMPORTACIÓN AURA SUITE', 'aura-suite') + ' ---\n' +
                             __('Resumen:', 'aura-suite') + ' ' + $('#res-message').text() + '\n' +
                             __('Detalles:', 'aura-suite') + '\n' + lines.join('\n');

            navigator.clipboard.writeText(textToCopy).then(function() {
                showToast(__('Detalle de importación copiado al portapapeles.', 'aura-suite'));
            }).catch(function() {
                showToast(__('No se pudo copiar automáticamente.', 'aura-suite'));
            });
        });

        // Botón para nueva importación
        $('#btn-reset-import').on('click', function() {
            if (fileInput) fileInput.value = '';
            updateDropzonePreview(null);
            $('#import-results-card').slideUp(200);
            $('#form-contacts-import')[0].reset();
            $('#import_mark_google_sync').prop('checked', true);
            $('html, body').animate({
                scrollTop: $('#contacts-tab-import_export').offset().top - 80
            }, 300);
        });
    }

    // ── Módulo de Tarjeta de Identidad Digital del Contacto (Ficha con Ojo) ──────
    var currentCardContact = null;

    function initContactCard() {
        // Copiar información al portapapeles
        $('#card-quick-copy').on('click', function(e) {
            e.preventDefault();
            if (!currentCardContact) return;
            var c = currentCardContact;
            var fullName = (c.first_name || '') + (c.last_name ? ' ' + c.last_name : '');
            var lines = [
                '📇 ' + fullName.trim(),
                c.job_title ? __('Cargo:', 'aura-suite') + ' ' + c.job_title : null,
                c.organization ? __('Organización:', 'aura-suite') + ' ' + c.organization : null,
                c.email_primary ? __('Email:', 'aura-suite') + ' ' + c.email_primary : (c.email ? __('Email:', 'aura-suite') + ' ' + c.email : null),
                c.phone_primary ? __('Teléfono:', 'aura-suite') + ' ' + c.phone_primary : (c.phone ? __('Teléfono:', 'aura-suite') + ' ' + c.phone : null),
                c.whatsapp ? 'WhatsApp: ' + c.whatsapp : null,
                c.address_street ? __('Dirección:', 'aura-suite') + ' ' + [c.address_street, c.address_city, c.address_state, c.address_country].filter(Boolean).join(', ') : null,
                c.notes ? __('Notas:', 'aura-suite') + ' ' + c.notes : null
            ].filter(Boolean);

            navigator.clipboard.writeText(lines.join('\n')).then(function() {
                showToast(__('Ficha de contacto copiada al portapapeles.', 'aura-suite'));
            }).catch(function() {
                showToast(__('No se pudo copiar automáticamente.', 'aura-suite'));
            });
        });

        // Botón Editar desde la Tarjeta
        $('#card-btn-edit').on('click', function() {
            if (!currentCardContact) return;
            var cid = currentCardContact.id;
            closeContactCard();
            openEditModal(cid);
        });

        // Botón Sincronizar desde la Tarjeta
        $('#card-btn-sync').on('click', function() {
            if (!currentCardContact) return;
            var cid = currentCardContact.id;
            closeContactCard();
            syncSingle(cid);
        });
    }

    function viewContactCard(id) {
        var contactId = parseInt(id, 10);
        if (!contactId) return;

        // 1. Buscar primero en la memoria local cargada
        var item = null;
        if (state.items && state.items.length) {
            item = state.items.find(function(c) { return parseInt(c.id, 10) === contactId; });
        }

        if (item) {
            populateAndShowContactCard(item);
        } else {
            // 2. Si no está en memoria, abrir el modal de inmediato con Skeleton en espera del AJAX
            $('#modal-contact-card')
                .removeClass('is-hidden')
                .addClass('active')
                .css({ display: 'flex', opacity: 1, visibility: 'visible' });

            if (typeof AuraUI !== 'undefined') {
                AuraUI.showModalSkeleton('modal-contact-card', { async: true });
            }

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_get_detail',
                id: contactId,
                nonce: cfg.nonce
            }, function(res) {
                if (res && res.success && res.data) {
                    var contact = res.data.contact || res.data;
                    populateAndShowContactCard(contact);
                } else {
                    if (typeof AuraUI !== 'undefined') {
                        AuraUI.hideModalSkeleton('modal-contact-card');
                    }
                    closeContactCard();
                    var msg = (res && res.data && res.data.message) ? res.data.message : __('No se pudo obtener la información del contacto.', 'aura-suite');
                    if (typeof showToast === 'function') {
                        showToast(msg);
                    } else {
                        alert(msg);
                    }
                }
            }).fail(function(xhr, status, err) {
                console.warn('Error al conectar con el servidor para detalle de contacto:', status, err);
                if (typeof AuraUI !== 'undefined') {
                    AuraUI.hideModalSkeleton('modal-contact-card');
                }
                closeContactCard();
                if (typeof showToast === 'function') {
                    showToast(__('Error al conectar con el servidor.', 'aura-suite'));
                }
            });
        }
    }

    function populateAndShowContactCard(item) {
        currentCardContact = item;

        var fullName = (item.first_name || '') + (item.last_name ? ' ' + item.last_name : '');
        fullName = fullName.trim() || item.organization || sprintf(__('Contacto #%s', 'aura-suite'), item.id);
        var initials = ((item.first_name || '').charAt(0) + (item.last_name || '').charAt(0)).toUpperCase() || 'C';

        var catKey = item.category || 'other';
        var catLabel = (cfg.categories && cfg.categories[catKey]) ? cfg.categories[catKey] : catKey;
        var catIcon = categoryIcons[catKey] || '🏷️';

        // Avatar: foto personalizada o iniciales
        var avatarUrl = item.avatar_url || item.photo_url || '';
        if (avatarUrl) {
            if (avatarUrl.indexOf(':/wp-content') !== -1 || avatarUrl.indexOf('/:/') !== -1 || avatarUrl.indexOf(':/') === 0) {
                avatarUrl = avatarUrl.replace('/:/', '/').replace(':/wp-content', '/wp-content');
                if (avatarUrl.indexOf('http') !== 0) {
                    avatarUrl = window.location.origin + '/' + avatarUrl.replace(/^[:\/]+/, '');
                }
            }
            $('#card-avatar-img').attr('src', avatarUrl).show();
            $('#card-avatar-initials').hide();
        } else {
            $('#card-avatar-img').hide();
            $('#card-avatar-initials').text(initials).show();
        }

        // Títulos Principales
        $('#card-full-name').text(fullName);
        $('#card-job-title').text(item.job_title || __('Sin cargo registrado', 'aura-suite'));
        $('#card-org-text').text(item.organization || __('Sin organización', 'aura-suite'));

        // Badges
        $('#card-category-badge').attr('class', 'category-pill category-pill--' + catKey).html(catIcon + ' ' + escapeHtml(catLabel));
        
        var typeLabel = '👤 ' + __('Persona', 'aura-suite');
        if (item.contact_type === 'company') typeLabel = '🏢 ' + __('Empresa', 'aura-suite');
        else if (item.contact_type === 'organization') typeLabel = '🏛️ ' + __('Organización', 'aura-suite');
        $('#card-type-badge').text(typeLabel);

        $('#card-sync-badge').html(renderSyncBadge(item));

        // Acciones Rápidas
        var primaryPhone = item.phone_primary || item.phone || '';
        if (primaryPhone) {
            $('#card-quick-call').attr('href', 'tel:' + primaryPhone.replace(/\s+/g, '')).css('opacity', '1').show();
        } else {
            $('#card-quick-call').attr('href', 'javascript:void(0)').css('opacity', '0.4');
        }

        var waPhone = item.whatsapp || primaryPhone || '';
        var waDigits = waPhone.replace(/\D+/g, '');
        if (waDigits) {
            $('#card-quick-wa').attr('href', 'https://wa.me/' + waDigits).css('opacity', '1').show();
        } else {
            $('#card-quick-wa').attr('href', 'javascript:void(0)').css('opacity', '0.4');
        }

        var primaryEmail = item.email_primary || item.email || '';
        if (primaryEmail) {
            $('#card-quick-mail').attr('href', 'mailto:' + primaryEmail).css('opacity', '1').show();
        } else {
            $('#card-quick-mail').attr('href', 'javascript:void(0)').css('opacity', '0.4');
        }

        // Canales Directos
        $('#card-email-primary').html(primaryEmail ? '<a href="mailto:' + escapeHtml(primaryEmail) + '" style="color:#38bdf8;text-decoration:none;">' + escapeHtml(primaryEmail) + '</a>' : '<span style="color:#64748b;">—</span>');
        $('#card-email-secondary').html(item.email_secondary ? '<a href="mailto:' + escapeHtml(item.email_secondary) + '" style="color:#38bdf8;text-decoration:none;">' + escapeHtml(item.email_secondary) + '</a>' : '<span style="color:#64748b;">—</span>');
        $('#card-phone-primary').html(primaryPhone ? '<a href="tel:' + escapeHtml(primaryPhone.replace(/\s+/g, '')) + '" style="color:#fff;text-decoration:none;">' + escapeHtml(primaryPhone) + '</a>' : '<span style="color:#64748b;">—</span>');
        $('#card-phone-secondary').html(item.phone_secondary ? '<a href="tel:' + escapeHtml(item.phone_secondary.replace(/\s+/g, '')) + '" style="color:#cbd5e1;text-decoration:none;">' + escapeHtml(item.phone_secondary) + '</a>' : '<span style="color:#64748b;">—</span>');

        // Dirección
        var addressParts = [
            item.address_street,
            item.address_city,
            item.address_state,
            item.address_postal,
            item.address_country
        ].filter(Boolean);
        var fullAddress = addressParts.join(', ');

        if (fullAddress) {
            $('#card-full-address').text(fullAddress);
            $('#card-quick-map').attr('href', 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(fullAddress)).css('opacity', '1').show();
            $('#card-address-block').show();
        } else {
            $('#card-full-address').html('<span style="color:#64748b;font-style:italic;">' + __('Sin dirección física registrada', 'aura-suite') + '</span>');
            $('#card-quick-map').attr('href', 'javascript:void(0)').css('opacity', '0.4');
        }

        // Tags
        var tagsContainer = $('#card-tags-container');
        if (item.tags) {
            var tagsArr = [];
            if (typeof item.tags === 'string') {
                try { tagsArr = JSON.parse(item.tags); } catch(e) { tagsArr = item.tags.split(','); }
            } else if (Array.isArray(item.tags)) {
                tagsArr = item.tags;
            }
            if (tagsArr.length) {
                var tHtml = '';
                tagsArr.forEach(function(t) {
                    var cleanTag = String(t).trim();
                    if (cleanTag) {
                        tHtml += '<span style="display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;font-size:0.75rem;font-weight:600;background:rgba(192,132,252,0.12);color:#c084fc;border:1px solid rgba(192,132,252,0.25);">🏷️ #' + escapeHtml(cleanTag) + '</span>';
                    }
                });
                tagsContainer.html(tHtml || '<span style="color:#64748b;font-size:0.8rem;">' + __('Sin etiquetas', 'aura-suite') + '</span>');
            } else {
                tagsContainer.html('<span style="color:#64748b;font-size:0.8rem;">' + __('Sin etiquetas', 'aura-suite') + '</span>');
            }
        } else {
            tagsContainer.html('<span style="color:#64748b;font-size:0.8rem;">' + __('Sin etiquetas', 'aura-suite') + '</span>');
        }

        // Vinculaciones
        if (item.third_party_id && parseInt(item.third_party_id, 10) > 0) {
            $('#card-link-tp').html('🏢 ' + sprintf(__('Vinculado a Tercero ID %s', 'aura-suite'), '<strong>#' + item.third_party_id + '</strong>')).css('color', '#60a5fa');
        } else {
            $('#card-link-tp').html('🏢 ' + __('Sin Tercero vinculado', 'aura-suite')).css('color', '#64748b');
        }

        if (item.wp_user_id && parseInt(item.wp_user_id, 10) > 0) {
            $('#card-link-wp').html('👤 ' + sprintf(__('Vinculado a Usuario WP ID %s', 'aura-suite'), '<strong>#' + item.wp_user_id + '</strong>')).css('color', '#c084fc');
        } else {
            $('#card-link-wp').html('👤 ' + __('Sin Usuario WP vinculado', 'aura-suite')).css('color', '#64748b');
        }

        // Notas
        if (item.notes && item.notes.trim()) {
            $('#card-notes-text').text(item.notes).css('font-style', 'normal').css('color', '#e2e8f0');
            $('#card-notes-block').show();
        } else {
            $('#card-notes-text').text(__('Sin notas registradas.', 'aura-suite')).css('font-style', 'italic').css('color', '#64748b');
        }

        // Metadatos
        $('#card-contact-id').text(item.id);
        $('#card-created-at').text(item.created_at || '—');
        if (item.google_resource_name) {
            $('#card-google-resource').html('Google: <code>' + escapeHtml(item.google_resource_name) + '</code>').show();
        } else {
            $('#card-google-resource').empty().hide();
        }

        // Botón Sync
        if (cfg.canSync && cfg.oauthConnected) {
            $('#card-btn-sync').show();
        } else {
            $('#card-btn-sync').hide();
        }

        $('#modal-contact-card').css('display', 'flex').hide().fadeIn(200);
        if (typeof AuraUI !== 'undefined') {
            AuraUI.hideModalSkeleton('modal-contact-card');
        }
    }

    function closeContactCard() {
        if (typeof AuraUI !== 'undefined') {
            AuraUI.hideModalSkeleton('modal-contact-card');
        }
        $('#modal-contact-card').fadeOut(180);
        currentCardContact = null;
    }

    function initDuplicatesMerge() {
        $('#btn-scan-duplicates').on('click', function() {
            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Escaneando...', 'aura-suite'));
            $('#duplicates-container').html('<div style="text-align:center;padding:50px 20px;"><span class="dashicons dashicons-update spin" style="font-size:32px;width:32px;height:32px;color:#38bdf8;"></span><p style="margin-top:12px;color:#94a3b8;">' + __('Analizando similitudes en la base de datos...', 'aura-suite') + '</p></div>');

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_find_duplicates',
                nonce: cfg.nonce
            }, function(res) {
                $btn.prop('disabled', false).html(orig);
                if (!res.success) {
                    $('#duplicates-container').html('<div class="aura-notice-box is-error"><p>' + escapeHtml(res.data && res.data.message ? res.data.message : __('Error al escanear', 'aura-suite')) + '</p></div>');
                    return;
                }

                var clusters = res.data.clusters || [];
                if (!clusters.length) {
                    $('#duplicates-container').html(
                        '<div style="text-align:center;padding:50px 20px;">' +
                        '<div style="font-size:42px;margin-bottom:12px;">🎉</div>' +
                        '<h3 style="margin:0 0 8px;color:#34d399;font-size:1.15rem;">' + __('¡Directorio Impecable!', 'aura-suite') + '</h3>' +
                        '<p style="margin:0;color:#94a3b8;font-size:0.9rem;">' + __('No se encontraron registros duplicados por correo, teléfono ni nombres similares.', 'aura-suite') + '</p>' +
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
                $('#duplicates-container').html('<div class="aura-notice-box is-error"><p>' + sprintf(__('Error de conexión al escanear duplicados%s. Intenta nuevamente.', 'aura-suite'), escapeHtml(errDetail)) + '</p></div>');
            });
        });
    }

    function renderDuplicateClusters(clusters) {
        var html = '<div style="display:flex;flex-direction:column;gap:20px;">';
        clusters.forEach(function(cluster, idx) {
            var rawReasons = cluster.reasons || [
                { type: cluster.type || 'email', label: cluster.type_label || __('Coincidencia', 'aura-suite'), value: cluster.match_value || '' }
            ];
            var reasonsHtml = rawReasons.map(function(r) {
                var bg = r.type === 'email' ? 'background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);' :
                         (r.type === 'phone' ? 'background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.3);' :
                         (r.type === 'org' ? 'background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);' :
                         'background:rgba(167,139,250,0.15);color:#a78bfa;border:1px solid rgba(167,139,250,0.3);'));
                return '<span style="font-size:0.75rem;padding:3px 8px;border-radius:6px;' + bg + '"><strong>' + escapeHtml(r.label || '') + ':</strong> ' + escapeHtml(r.value || '') + '</span>';
            }).join(' ');

            var confBadge = '<span style="font-size:0.75rem;padding:3px 8px;border-radius:6px;background:rgba(99,102,241,0.18);color:#a5b4fc;border:1px solid rgba(99,102,241,0.35);font-weight:700;">🎯 ' + sprintf(__('%d%% Certeza', 'aura-suite'), (cluster.confidence || 90)) + '</span>';

            html += '<div class="aura-glass-card cluster-card" style="padding:18px;border-radius:12px;background:rgba(255,255,255,0.03);" data-cluster="' + idx + '">';
            html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">';
            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
            html += '<span style="font-weight:700;color:#fff;font-size:0.95rem;">' + sprintf(__('Grupo %1$d (%2$d coincidencias)', 'aura-suite'), (idx + 1), cluster.contacts.length) + '</span>';
            html += confBadge;
            html += reasonsHtml;
            html += '</div>';

            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
            html += '<button type="button" class="btn btn-primary btn-sm btn-lift btn-open-merge-wizard" data-cluster="' + idx + '" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);border:none;box-shadow:0 0 14px rgba(139,92,246,0.4);"><span class="dashicons dashicons-randomize"></span> <span>' + __('Comparar y Fusionar Asistido', 'aura-suite') + '</span></button>';
            html += '<button type="button" class="btn btn-secondary btn-sm btn-lift btn-merge-cluster-quick" data-cluster="' + idx + '" title="' + escapeHtml(__('Fusiona automáticamente en el contacto maestro marcado', 'aura-suite')) + '"><span class="dashicons dashicons-yes-alt"></span> <span>' + __('Fusión Rápida', 'aura-suite') + '</span></button>';
            html += '</div>';
            html += '</div>';

            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">';
            cluster.contacts.forEach(function(c, cIdx) {
                var isChecked = cIdx === 0 ? 'checked' : '';
                var avatarUrl = c.avatar_url || c.photo_url || '';

                html += '<div class="contact-card-selectable" style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:8px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);position:relative;">';
                html += '<div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-top:2px;">';
                html += '<input type="checkbox" class="cluster-chk-include" data-cluster="' + idx + '" value="' + c.id + '" checked title="' + escapeHtml(__('Incluir en la fusión', 'aura-suite')) + '">';
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
                if (c.tags && c.tags.length) {
                    var tgList = Array.isArray(c.tags) ? c.tags : (typeof c.tags === 'string' ? (function(){ try { return JSON.parse(c.tags); } catch(e){ return c.tags.split(','); } })() : []);
                    if (tgList.length) {
                        html += '<span style="color:#a78bfa;">🏷️ ' + escapeHtml(tgList.join(', ')) + '</span>';
                    }
                }
                if (c.third_party_id && parseInt(c.third_party_id) > 0) html += '<span style="color:#38bdf8;">🔗 ' + __('Tercero vinculado', 'aura-suite') + '</span>';
                if (c.wp_user_id && parseInt(c.wp_user_id) > 0) html += '<span style="color:#34d399;">👤 ' + __('Usuario WP vinculado', 'aura-suite') + '</span>';
                html += '</div>';

                // Selectores de Fusión Rápida (ID Maestro y Nombre que prevalece)
                html += '<div style="display:flex;align-items:center;gap:12px;margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.06);flex-wrap:wrap;">';
                html += '<label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;font-size:0.74rem;color:#c4b5fd;font-weight:600;" title="' + escapeHtml(__('Conservar este ID como registro maestro definitivo en Base de Datos', 'aura-suite')) + '">';
                html += '<input type="radio" name="master_cluster_' + idx + '" value="' + c.id + '" ' + isChecked + ' style="accent-color:#8b5cf6;">';
                html += '<span>👑 ' + __('ID Maestro (BD)', 'aura-suite') + '</span>';
                html += '</label>';
                html += '<label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;font-size:0.74rem;color:#38bdf8;font-weight:600;" title="' + escapeHtml(__('Conservar este Nombre y Apellido para la ficha unificada', 'aura-suite')) + '">';
                html += '<input type="radio" name="quick_name_cluster_' + idx + '" value="' + c.id + '" ' + isChecked + ' data-first="' + escapeHtml(c.first_name || '') + '" data-last="' + escapeHtml(c.last_name || '') + '" style="accent-color:#38bdf8;">';
                html += '<span>👤 ' + __('Nombre a Conservar', 'aura-suite') + '</span>';
                html += '</label>';
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
            var $btn = $(this);
            var origHtml = $btn.html();
            var clusterIdx = $btn.data('cluster');
            var cluster = clusters[clusterIdx];
            var primaryId = $('input[name="master_cluster_' + clusterIdx + '"]:checked').val();
            if (!primaryId) {
                alert(__('Por favor selecciona el contacto maestro que prevalecerá.', 'aura-suite'));
                return;
            }

            // Considerar solo los contactos con checkbox marcado
            var checkedIds = [];
            $('.cluster-chk-include[data-cluster="' + clusterIdx + '"]:checked').each(function() {
                checkedIds.push(parseInt($(this).val()));
            });

            if (checkedIds.indexOf(parseInt(primaryId)) === -1) {
                alert(__('El contacto maestro seleccionado debe estar incluido en la lista de fusión.', 'aura-suite'));
                return;
            }

            var secondaryIds = checkedIds.filter(function(id) { return id !== parseInt(primaryId); });
            if (!secondaryIds.length) {
                alert(__('Debes incluir al menos dos contactos para fusionar.', 'aura-suite'));
                return;
            }

            // Nombre elegido para la Fusión Rápida (puede ser el de la derecha o izquierda)
            var chosenNameRadio = $('input[name="quick_name_cluster_' + clusterIdx + '"]:checked');
            var quickOverrides = {};
            var nameAlertLabel = '';
            if (chosenNameRadio.length) {
                var qFirst = (chosenNameRadio.data('first') || '').trim();
                var qLast  = (chosenNameRadio.data('last') || '').trim();
                quickOverrides.first_name = qFirst;
                quickOverrides.last_name  = qLast;
                nameAlertLabel = ' ' + sprintf(__('con el nombre "%s"', 'aura-suite'), (qFirst + (qLast ? ' ' + qLast : '')));
            }

            if (!confirm(sprintf(__('¿Estás seguro de fusionar de forma rápida estos %1$d contacto(s) en la ficha maestra #%2$s%3$s?', 'aura-suite'), secondaryIds.length, primaryId, nameAlertLabel) + '\n\n' + __('Los teléfonos, emails, etiquetas, notas y relaciones se unificarán en el maestro y los registros redundantes se archivarán.', 'aura-suite'))) {
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Fusionando...', 'aura-suite'));
            executeMerge(primaryId, secondaryIds, quickOverrides, clusterIdx, $btn, origHtml);
        });
    }

    // ── Wizard de Fusión Asistida y Selección Campo a Campo ──────────────────────
    window.closeMergeModal = function() {
        $('#modal-merge-contacts').fadeOut(150);
        $('#btn-submit-merge-action').prop('disabled', false).html('<span class="dashicons dashicons-randomize"></span> <span>' + __('Confirmar y Fusionar Contactos', 'aura-suite') + '</span>');
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
            alert(__('Debes mantener al menos 2 contactos seleccionados en este grupo para realizar la fusión.', 'aura-suite'));
            return;
        }

        var preselectedMasterId = parseInt($('input[name="master_cluster_' + clusterIdx + '"]:checked').val()) || activeContacts[0].id;

        // Construir contenido dinámico del modal ultra-compacto
        var mHtml = '';

        // 1. Selector Superior: Registro Maestro (Destino del ID)
        mHtml += '<div class="merge-master-bar">';
        mHtml += '<div class="merge-master-title"><span class="dashicons dashicons-star-filled" style="color:#fbbf24;font-size:14px;width:14px;height:14px;"></span> ' + __('1. Contacto Maestro Base (Prevalecerá su ID en BD):', 'aura-suite') + '</div>';
        mHtml += '<div class="merge-master-options">';
        activeContacts.forEach(function(c) {
            var isMaster = (parseInt(c.id) === preselectedMasterId);
            var fullName = (c.first_name || '') + (c.last_name ? ' ' + c.last_name : '');
            var infoExtra = c.email_primary || c.phone_primary || (c.organization ? c.organization : __('Sin email/tel', 'aura-suite'));
            mHtml += '<label class="merge-master-pill ' + (isMaster ? 'is-selected' : '') + '">';
            mHtml += '<input type="radio" name="wizard_target_id" value="' + c.id + '" ' + (isMaster ? 'checked' : '') + ' class="merge-option-radio" onchange="onWizardMasterChange(this);">';
            mHtml += '<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">';
            mHtml += '<strong style="color:#fff;font-size:0.82rem;">👑 #' + c.id + ' — ' + escapeHtml(fullName) + '</strong>';
            mHtml += '<div style="font-size:0.71rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(infoExtra) + '</div>';
            mHtml += '</div></label>';
        });
        mHtml += '</div></div>';

        // 2. Banner Superior: Previsualización en Vivo de la Ficha Resultante
        mHtml += '<div class="merge-preview-card" id="merge-live-preview-box">';
        mHtml += '<div class="merge-preview-header"><span class="dashicons dashicons-visibility" style="font-size:13px;width:13px;height:13px;"></span> ' + __('Ficha Maestra Resultante (Previsualización en Vivo):', 'aura-suite') + '</div>';
        mHtml += '<div class="merge-preview-content">';
        mHtml += '<div id="prev-avatar-wrap" class="merge-preview-avatar">👤</div>';
        mHtml += '<div class="merge-preview-details">';
        mHtml += '<div class="merge-preview-name-row">';
        mHtml += '<span class="merge-preview-name" id="prev-name">' + __('Cargando...', 'aura-suite') + '</span>';
        mHtml += '<span class="merge-preview-org" id="prev-org-job"></span>';
        mHtml += '</div>';
        mHtml += '<div class="merge-preview-chips">';
        mHtml += '<span id="prev-email">✉️ —</span>';
        mHtml += '<span id="prev-phone">📞 —</span>';
        mHtml += '<span id="prev-cat" style="color:#a78bfa;">🏷️ —</span>';
        mHtml += '<span id="prev-tags" style="color:#38bdf8;">🏷️ —</span>';
        mHtml += '</div>';
        mHtml += '</div>';
        mHtml += '</div>';
        mHtml += '</div>';

        // 3. Grid de Campos a 2 Columnas
        mHtml += '<div class="merge-fields-grid-2col">';

        // ── COLUMNA 1: IDENTIDAD & EMPRESA ──
        mHtml += '<div class="merge-column">';

        // Campo: Nombre y Apellidos
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
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">👤 ' + __('Nombre y Apellidos', 'aura-suite') + '</span><span style="font-size:0.71rem;color:#94a3b8;">' + __('Elige u edita abajo', 'aura-suite') + '</span></div>';
        mHtml += '<div class="merge-options-grid">';
        distinctNames.forEach(function(n, nIdx) {
            var isChk = (nIdx === 0);
            mHtml += '<label class="merge-option-label ' + (isChk ? 'is-selected' : '') + '">';
            mHtml += '<input type="radio" name="wizard_field_name" value="' + nIdx + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" data-first="' + escapeHtml(n.first) + '" data-last="' + escapeHtml(n.last) + '" onchange="onWizardNameChange(this);">';
            mHtml += '<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;"><strong style="color:#fff;">' + escapeHtml(n.full) + '</strong><span style="font-size:0.71rem;color:#94a3b8;margin-left:5px;">(#' + n.sourceId + ')</span></div>';
            mHtml += '</label>';
        });
        mHtml += '<label class="merge-option-label" id="label-wizard-name-custom">';
        mHtml += '<input type="radio" name="wizard_field_name" value="custom" class="merge-option-radio" onchange="onWizardNameChange(this);">';
        mHtml += '<div><strong style="color:#a78bfa;">✏️ ' + __('Personalizar Nombre y Apellidos', 'aura-suite') + '</strong></div>';
        mHtml += '</label>';
        mHtml += '</div>';

        // Inputs en vivo de Nombre
        var defaultFirst = distinctNames[0] ? distinctNames[0].first : '';
        var defaultLast = distinctNames[0] ? distinctNames[0].last : '';
        mHtml += '<div class="merge-custom-input-box" id="box-wizard-name-inputs">';
        mHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">';
        mHtml += '<div><label style="font-size:0.72rem;color:#cbd5e1;display:block;margin-bottom:2px;">' + __('Nombre(s) Final *', 'aura-suite') + '</label><input type="text" id="merge_inp_first_name" class="aura-input" value="' + escapeHtml(defaultFirst) + '" oninput="updateMergePreview();"></div>';
        mHtml += '<div><label style="font-size:0.72rem;color:#cbd5e1;display:block;margin-bottom:2px;">' + __('Apellidos Final', 'aura-suite') + '</label><input type="text" id="merge_inp_last_name" class="aura-input" value="' + escapeHtml(defaultLast) + '" oninput="updateMergePreview();"></div>';
        mHtml += '</div></div>';
        mHtml += '</div>'; // Fin card nombre

        // Campo: Organización y Cargo
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
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">🏢 ' + __('Organización y Cargo', 'aura-suite') + '</span></div>';
        mHtml += '<div class="merge-options-grid">';
        distinctOrgs.forEach(function(o, oIdx) {
            var isChk = (oIdx === 0);
            mHtml += '<label class="merge-option-label ' + (isChk ? 'is-selected' : '') + '">';
            mHtml += '<input type="radio" name="wizard_field_org" value="' + oIdx + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" data-org="' + escapeHtml(o.org) + '" data-job="' + escapeHtml(o.job) + '" onchange="onWizardOrgChange(this);">';
            mHtml += '<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;"><strong style="color:#fff;">' + (o.org ? escapeHtml(o.org) : __('Sin empresa', 'aura-suite')) + '</strong>' + (o.job ? ' — <span style="color:#94a3b8;">' + escapeHtml(o.job) + '</span>' : '') + '<span style="font-size:0.71rem;color:#94a3b8;margin-left:5px;">(#' + o.sourceId + ')</span></div>';
            mHtml += '</label>';
        });
        mHtml += '<label class="merge-option-label">';
        mHtml += '<input type="radio" name="wizard_field_org" value="custom" class="merge-option-radio" onchange="onWizardOrgChange(this);">';
        mHtml += '<div><strong style="color:#a78bfa;">✏️ ' + __('Personalizar Empresa y Cargo', 'aura-suite') + '</strong></div>';
        mHtml += '</label>';
        mHtml += '</div>';

        var defaultOrg = distinctOrgs[0] ? distinctOrgs[0].org : '';
        var defaultJob = distinctOrgs[0] ? distinctOrgs[0].job : '';
        mHtml += '<div class="merge-custom-input-box" id="box-wizard-org-inputs">';
        mHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">';
        mHtml += '<div><label style="font-size:0.72rem;color:#cbd5e1;display:block;margin-bottom:2px;">' + __('Empresa / Entidad', 'aura-suite') + '</label><input type="text" id="merge_inp_organization" class="aura-input" value="' + escapeHtml(defaultOrg) + '" oninput="updateMergePreview();"></div>';
        mHtml += '<div><label style="font-size:0.72rem;color:#cbd5e1;display:block;margin-bottom:2px;">' + __('Cargo / Ocupación', 'aura-suite') + '</label><input type="text" id="merge_inp_job_title" class="aura-input" value="' + escapeHtml(defaultJob) + '" oninput="updateMergePreview();"></div>';
        mHtml += '</div></div>';
        mHtml += '</div>'; // Fin card org

        // Campo: Avatar / Foto (si hay)
        var allAvatars = [];
        activeContacts.forEach(function(c) {
            var av = (c.avatar_url || c.photo_url || '').trim();
            if (av && allAvatars.indexOf(av) === -1) allAvatars.push({ url: av, sourceId: c.id });
        });

        if (allAvatars.length > 0) {
            mHtml += '<div class="merge-field-card">';
            mHtml += '<div class="merge-field-header"><span class="merge-field-title">🖼️ ' + __('Foto de Perfil / Avatar', 'aura-suite') + '</span></div>';
            mHtml += '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
            allAvatars.forEach(function(av, avIdx) {
                var isChk = (avIdx === 0);
                mHtml += '<label style="display:flex;align-items:center;gap:6px;padding:4px 8px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:6px;cursor:pointer;">';
                mHtml += '<input type="radio" name="wizard_avatar_url" value="' + escapeHtml(av.url) + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();" style="margin:0;accent-color:#8b5cf6;">';
                mHtml += '<img src="' + escapeHtml(av.url) + '" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">';
                mHtml += '<span style="font-size:0.74rem;color:#94a3b8;">#' + av.sourceId + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div></div>';
        }

        mHtml += '</div>'; // Fin Columna 1

        // ── COLUMNA 2: CONTACTO & CLASIFICACIÓN ──
        mHtml += '<div class="merge-column">';

        // Campo: Correos Electrónicos
        var allEmails = [];
        activeContacts.forEach(function(c) {
            [c.email_primary, c.email_secondary].forEach(function(em) {
                em = (em || '').trim().toLowerCase();
                if (em && allEmails.indexOf(em) === -1) allEmails.push(em);
            });
        });

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">✉️ ' + __('Correos Electrónicos', 'aura-suite') + '</span></div>';
        if (allEmails.length > 0) {
            mHtml += '<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px;width:100%;box-sizing:border-box;">';
            mHtml += '<div style="min-width:0;"><label style="font-size:0.72rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:3px;">' + __('Principal *', 'aura-suite') + '</label>';
            allEmails.forEach(function(em, emIdx) {
                var isChk = (emIdx === 0);
                mHtml += '<label class="merge-option-label" style="padding:4px 6px;margin-bottom:4px;" title="' + escapeHtml(em) + '">';
                mHtml += '<input type="radio" name="wizard_email_primary" value="' + escapeHtml(em) + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.75rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">' + escapeHtml(em) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';

            mHtml += '<div style="min-width:0;"><label style="font-size:0.72rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:3px;">' + __('Secundario', 'aura-suite') + '</label>';
            mHtml += '<label class="merge-option-label" style="padding:4px 6px;margin-bottom:4px;" title="' + escapeHtml(__('Ninguno', 'aura-suite')) + '">';
            mHtml += '<input type="radio" name="wizard_email_secondary" value="" ' + (allEmails.length <= 1 ? 'checked' : '') + ' class="merge-option-radio" onchange="updateMergePreview();">';
            mHtml += '<span style="font-size:0.75rem;color:#94a3b8;white-space:nowrap;">(' + __('Ninguno', 'aura-suite') + ')</span>';
            mHtml += '</label>';
            allEmails.forEach(function(em, emIdx) {
                var isChk = (allEmails.length > 1 && emIdx === 1);
                mHtml += '<label class="merge-option-label" style="padding:4px 6px;margin-bottom:4px;" title="' + escapeHtml(em) + '">';
                mHtml += '<input type="radio" name="wizard_email_secondary" value="' + escapeHtml(em) + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.75rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">' + escapeHtml(em) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';
            mHtml += '</div>';
        } else {
            mHtml += '<p style="color:#94a3b8;font-size:0.76rem;margin:0;">' + __('Sin correos registrados en los contactos seleccionados.', 'aura-suite') + '</p>';
        }
        mHtml += '</div>'; // Fin card email

        // Campo: Teléfonos
        var allPhones = [];
        activeContacts.forEach(function(c) {
            [c.phone_primary, c.phone_secondary, c.whatsapp].forEach(function(ph) {
                ph = (ph || '').trim();
                if (ph && allPhones.indexOf(ph) === -1) allPhones.push(ph);
            });
        });

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">📞 ' + __('Teléfonos y WhatsApp', 'aura-suite') + '</span></div>';
        if (allPhones.length > 0) {
            mHtml += '<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px;width:100%;box-sizing:border-box;">';
            mHtml += '<div style="min-width:0;"><label style="font-size:0.72rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:3px;">' + __('Principal *', 'aura-suite') + '</label>';
            allPhones.forEach(function(ph, pIdx) {
                var isChk = (pIdx === 0);
                mHtml += '<label class="merge-option-label" style="padding:4px 6px;margin-bottom:4px;" title="' + escapeHtml(ph) + '">';
                mHtml += '<input type="radio" name="wizard_phone_primary" value="' + escapeHtml(ph) + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.75rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">' + escapeHtml(ph) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';

            mHtml += '<div style="min-width:0;"><label style="font-size:0.72rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:3px;">' + __('Secundario', 'aura-suite') + '</label>';
            mHtml += '<label class="merge-option-label" style="padding:4px 6px;margin-bottom:4px;" title="' + escapeHtml(__('Ninguno', 'aura-suite')) + '">';
            mHtml += '<input type="radio" name="wizard_phone_secondary" value="" ' + (allPhones.length <= 1 ? 'checked' : '') + ' class="merge-option-radio" onchange="updateMergePreview();">';
            mHtml += '<span style="font-size:0.75rem;color:#94a3b8;white-space:nowrap;">(' + __('Ninguno', 'aura-suite') + ')</span>';
            mHtml += '</label>';
            allPhones.forEach(function(ph, pIdx) {
                var isChk = (allPhones.length > 1 && pIdx === 1);
                mHtml += '<label class="merge-option-label" style="padding:4px 6px;margin-bottom:4px;" title="' + escapeHtml(ph) + '">';
                mHtml += '<input type="radio" name="wizard_phone_secondary" value="' + escapeHtml(ph) + '" ' + (isChk ? 'checked' : '') + ' class="merge-option-radio" onchange="updateMergePreview();">';
                mHtml += '<span style="font-size:0.75rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">' + escapeHtml(ph) + '</span>';
                mHtml += '</label>';
            });
            mHtml += '</div>';
            mHtml += '</div>';
        } else {
            mHtml += '<p style="color:#94a3b8;font-size:0.76rem;margin:0;">' + __('Sin teléfonos registrados en este grupo.', 'aura-suite') + '</p>';
        }
        mHtml += '</div>'; // Fin card telefonos

        // Campo: Categoría & Etiquetas
        var allCats = [];
        activeContacts.forEach(function(c) {
            if (c.category && allCats.indexOf(c.category) === -1) allCats.push(c.category);
        });

        var allClusterTags = [];
        var contactTagsMap = {};
        activeContacts.forEach(function(c) {
            var cTags = [];
            if (Array.isArray(c.tags)) {
                cTags = c.tags;
            } else if (typeof c.tags === 'string' && c.tags.trim()) {
                try {
                    var p = JSON.parse(c.tags);
                    if (Array.isArray(p)) cTags = p;
                    else cTags = c.tags.split(',');
                } catch(e) {
                    cTags = c.tags.split(',');
                }
            }
            cTags = cTags.map(function(t) { return (t || '').trim(); }).filter(function(t) { return t.length > 0; });
            contactTagsMap[c.id] = cTags;
            cTags.forEach(function(t) {
                if (allClusterTags.indexOf(t) === -1) allClusterTags.push(t);
            });
        });
        var combinedTagsStr = allClusterTags.join(', ');

        mHtml += '<div class="merge-field-card">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">🏷️ ' + __('Categoría y Etiquetas', 'aura-suite') + '</span></div>';
        
        // Categoría chips
        mHtml += '<div style="margin-bottom:8px;">';
        mHtml += '<label style="font-size:0.72rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:4px;">' + __('Categoría Principal', 'aura-suite') + '</label>';
        mHtml += '<div style="display:flex;gap:5px;flex-wrap:wrap;">';
        ['student', 'teacher', 'staff', 'supplier', 'donor', 'member', 'other'].forEach(function(catKey) {
            var label = cfg.categories[catKey] || catKey;
            var isChk = (allCats.length > 0 && allCats[0] === catKey);
            mHtml += '<label style="display:inline-flex;align-items:center;gap:4px;padding:3px 7px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:6px;cursor:pointer;font-size:0.75rem;">';
            mHtml += '<input type="radio" name="wizard_category" value="' + catKey + '" ' + (isChk ? 'checked' : '') + ' onchange="updateMergePreview();" style="margin:0;accent-color:#8b5cf6;">';
            mHtml += '<span style="color:#fff;">' + escapeHtml(label) + '</span>';
            mHtml += '</label>';
        });
        mHtml += '</div></div>';

        // Etiquetas modos
        mHtml += '<label style="font-size:0.72rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:4px;">' + __('Etiquetas de la Ficha', 'aura-suite') + '</label>';
        mHtml += '<div class="merge-options-grid">';
        mHtml += '<label class="merge-option-label is-selected" style="padding:4px 6px;">';
        mHtml += '<input type="radio" name="wizard_tags_mode" value="combine" checked class="merge-option-radio" data-tags="' + escapeHtml(combinedTagsStr) + '" onchange="onWizardTagsChange(this);">';
        mHtml += '<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;"><strong style="color:#fff;font-size:0.76rem;">✨ ' + __('Combinar todas', 'aura-suite') + '</strong>' + (allClusterTags.length > 0 ? ' <span style="font-size:0.72rem;color:#a78bfa;">(' + escapeHtml(combinedTagsStr) + ')</span>' : '') + '</div>';
        mHtml += '</label>';

        activeContacts.forEach(function(c) {
            var cTags = contactTagsMap[c.id] || [];
            if (cTags.length > 0) {
                var cTagsStr = cTags.join(', ');
                mHtml += '<label class="merge-option-label" style="padding:4px 6px;">';
                mHtml += '<input type="radio" name="wizard_tags_mode" value="contact_' + c.id + '" class="merge-option-radio" data-tags="' + escapeHtml(cTagsStr) + '" onchange="onWizardTagsChange(this);">';
                mHtml += '<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;"><span style="color:#cbd5e1;font-size:0.76rem;">' + sprintf(__('Solo #%s', 'aura-suite'), c.id) + '</span> <span style="font-size:0.72rem;color:#94a3b8;">(' + escapeHtml(cTagsStr) + ')</span></div>';
                mHtml += '</label>';
            }
        });

        mHtml += '<label class="merge-option-label" style="padding:4px 6px;">';
        mHtml += '<input type="radio" name="wizard_tags_mode" value="custom" class="merge-option-radio" onchange="onWizardTagsChange(this);">';
        mHtml += '<div><strong style="color:#a78bfa;font-size:0.76rem;">✏️ ' + __('Personalizar etiquetas', 'aura-suite') + '</strong></div>';
        mHtml += '</label>';
        mHtml += '</div>';

        mHtml += '<div class="merge-custom-input-box" id="box-wizard-tags-inputs" style="margin-top:6px;">';
        mHtml += '<input type="text" id="merge_inp_tags" class="aura-input" value="' + escapeHtml(combinedTagsStr) + '" placeholder="' + escapeHtml(__('Etiquetas separadas por comas...', 'aura-suite')) + '" oninput="updateMergePreview();">';
        mHtml += '</div>';
        mHtml += '</div>'; // Fin card categoria & tags

        mHtml += '</div>'; // Fin Columna 2

        mHtml += '</div>'; // Fin 2-Col Grid

        // 4. Fila Final: Notas Consolidadas (Opcional)
        mHtml += '<div class="merge-field-card" style="margin-top:2px;">';
        mHtml += '<div class="merge-field-header"><span class="merge-field-title">📝 ' + __('Notas Consolidadas (Opcional)', 'aura-suite') + '</span><span style="font-size:0.71rem;color:#38bdf8;">' + __('Historial previo se preserva automáticamente', 'aura-suite') + '</span></div>';
        mHtml += '<textarea id="merge_inp_custom_notes" class="aura-textarea" rows="2" placeholder="' + escapeHtml(__('Nota adicional explicativa de la fusión...', 'aura-suite')) + '" oninput="updateMergePreview();" style="width:100%;box-sizing:border-box;resize:vertical;"></textarea>';
        mHtml += '</div>';

        $('#merge-modal-content').html(mHtml);

        // Guardar contexto en el botón de confirmación
        $('#btn-submit-merge-action').data('cluster-idx', clusterIdx);
        $('#btn-submit-merge-action').data('active-contacts', activeContacts);

        // Actualizar previsualización inicial
        updateMergePreview();

        // Reactivar botón de confirmación
        $('#btn-submit-merge-action').prop('disabled', false).html('<span class="dashicons dashicons-randomize"></span> <span>' + __('Confirmar y Fusionar Contactos', 'aura-suite') + '</span>');

        // Mostrar modal con centrado inteligente
        $('#modal-merge-contacts').css('display', 'flex').hide().fadeIn(150);
    }

    // Handlers dinámicos del Wizard de Fusión
    window.onWizardMasterChange = function(radio) {
        $('.merge-master-pill').removeClass('is-selected');
        $(radio).closest('.merge-master-pill').addClass('is-selected');
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

    window.onWizardTagsChange = function(radio) {
        $('input[name="wizard_tags_mode"]').closest('.merge-option-label').removeClass('is-selected');
        $(radio).closest('.merge-option-label').addClass('is-selected');
        if (radio.value === 'custom') {
            $('#box-wizard-tags-inputs').slideDown(150);
            $('#merge_inp_tags').focus();
        } else {
            var tags = $(radio).data('tags') || '';
            $('#merge_inp_tags').val(tags);
        }
        updateMergePreview();
    };

    window.updateMergePreview = function() {
        var fn = ($('#merge_inp_first_name').val() || '').trim();
        var ln = ($('#merge_inp_last_name').val() || '').trim();
        var full = fn + (ln ? ' ' + ln : '');
        if (!full) full = __('Contacto Sin Nombre', 'aura-suite');

        var org = ($('#merge_inp_organization').val() || '').trim();
        var job = ($('#merge_inp_job_title').val() || '').trim();
        var orgJobText = org ? org + (job ? ' (' + job + ')' : '') : (job ? job : __('Sin organización', 'aura-suite'));

        var emPrimary = $('input[name="wizard_email_primary"]:checked').val() || '';
        var phPrimary = $('input[name="wizard_phone_primary"]:checked').val() || '';
        var catKey = $('input[name="wizard_category"]:checked').val() || 'other';
        var catLabel = cfg.categories[catKey] || catKey;
        var avatarUrl = $('input[name="wizard_avatar_url"]:checked').val() || '';
        var tagsVal = ($('#merge_inp_tags').val() || '').trim();

        $('#prev-name').text(full);
        $('#prev-org-job').text(orgJobText);
        $('#prev-email').text(emPrimary ? '✉️ ' + emPrimary : '✉️ ' + __('Sin correo', 'aura-suite'));
        $('#prev-phone').text(phPrimary ? '📞 ' + phPrimary : '📞 ' + __('Sin teléfono', 'aura-suite'));
        $('#prev-cat').text('🏷️ ' + catLabel);
        $('#prev-tags').text(tagsVal ? '🏷️ ' + tagsVal : '🏷️ ' + __('Sin etiquetas', 'aura-suite'));

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
            alert(__('Por favor selecciona el contacto maestro de destino.', 'aura-suite'));
            return;
        }

        var sourceIds = activeContacts.map(function(c) { return parseInt(c.id); }).filter(function(id) { return id !== targetId; });
        if (!sourceIds.length) {
            alert(__('No hay contactos secundarios para fusionar.', 'aura-suite'));
            return;
        }

        var tagsMode = $('input[name="wizard_tags_mode"]:checked').val() || 'combine';
        var customTags = ($('#merge_inp_tags').val() || '').trim();

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
            tags_mode: tagsMode === 'combine' ? 'combine' : 'replace',
            custom_tags: customTags,
            custom_notes: ($('#merge_inp_custom_notes').val() || '').trim()
        };

        var orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Fusionando contactos...', 'aura-suite'));

        executeMerge(targetId, sourceIds, overrides, clusterIdx, $btn, orig);
    });

    function executeMerge(targetId, sourceIds, overrides, clusterIdx, $btn, origHtml) {
        if (!origHtml && $btn && $btn.length) {
            origHtml = $btn.html();
        }
        if ($btn && $btn.length) {
            $btn.prop('disabled', true);
        }
        $('#btn-submit-merge-action').prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Fusionando contactos...', 'aura-suite'));

        $.post(cfg.ajaxUrl, {
            action: 'aura_contacts_merge',
            nonce: cfg.nonce,
            target_id: targetId,
            source_ids: sourceIds,
            field_overrides: overrides
        }, function(res) {
            if (res.success) {
                closeMergeModal();
                showToast(sprintf(__('Fusión exitosa: Se unificaron %1$d contacto(s) en la ficha #%2$s', 'aura-suite'), sourceIds.length, targetId));
                $('.cluster-card[data-cluster="' + clusterIdx + '"]').fadeOut(300, function() {
                    $(this).remove();
                    if (!$('.cluster-card').length) {
                        $('#duplicates-container').html(
                            '<div style="text-align:center;padding:50px 20px;">' +
                            '<div style="font-size:42px;margin-bottom:12px;">🎉</div>' +
                            '<h3 style="margin:0 0 8px;color:#34d399;font-size:1.15rem;">' + __('¡Fusión Completada!', 'aura-suite') + '</h3>' +
                            '<p style="margin:0;color:#94a3b8;font-size:0.9rem;">' + __('Todos los grupos de duplicados han sido resueltos satisfactoriamente.', 'aura-suite') + '</p>' +
                            '<button type="button" class="btn btn-secondary btn-sm btn-lift" onclick="$(\'#btn-scan-duplicates\').trigger(\'click\');" style="margin-top:14px;"><span class="dashicons dashicons-search"></span> ' + __('Re-escanear ahora', 'aura-suite') + '</button>' +
                            '</div>'
                        );
                    }
                });
                fetchContacts();
                refreshStats();
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al fusionar contactos.', 'aura-suite'));
            }
        }).fail(function(xhr) {
            var msg = __('Error de conexión durante el proceso de fusión.', 'aura-suite');
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg += ' (' + xhr.responseJSON.data.message + ')';
            }
            alert(msg);
        }).always(function() {
            // Restaurar SIEMPRE tanto el botón del modal como el botón del cluster
            $('#btn-submit-merge-action').prop('disabled', false).html('<span class="dashicons dashicons-randomize"></span> <span>' + __('Confirmar y Fusionar Contactos', 'aura-suite') + '</span>');
            if ($btn && $btn.length && origHtml) {
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    }

    // ── Modal de Vinculación de Entidades ────────────────────────────────────────
    window.openLinkEntityModal = function(contactId, contactName) {
        $('#link-target-contact-id').val(contactId);
        $('#link-contact-name-banner').text(sprintf(__('Vincular a: %s', 'aura-suite'), contactName));
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
                        $box.append('<div class="autocomplete-item" style="color:#94a3b8;">' + __('No se encontraron terceros', 'aura-suite') + '</div>').show();
                        return;
                    }
                    items.forEach(function(tp) {
                        var $item = $('<div class="autocomplete-item"><span><strong>' + escapeHtml(tp.name) + '</strong> (' + escapeHtml(tp.type_label || '') + ')</span><span style="font-size:0.75rem;color:#38bdf8;">' + __('Vincular', 'aura-suite') + '</span></div>');
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
                        $box.append('<div class="autocomplete-item" style="color:#94a3b8;">' + __('No se encontraron usuarios', 'aura-suite') + '</div>').show();
                        return;
                    }
                    users.forEach(function(u) {
                        var $item = $('<div class="autocomplete-item"><span><strong>' + escapeHtml(u.name) + '</strong> (' + escapeHtml(u.email) + ')</span><span style="font-size:0.75rem;color:#c084fc;">' + __('Vincular', 'aura-suite') + '</span></div>');
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
                showToast(__('Tercero vinculado exitosamente.', 'aura-suite'));
                closeLinkModal();
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al vincular', 'aura-suite'));
            }
        });
    }

    function linkWpUser(contactId, wpUserId) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_link_wp_user', nonce: cfg.nonce, contact_id: contactId, wp_user_id: wpUserId }, function(res) {
            if (res.success) {
                showToast(__('Usuario WordPress vinculado exitosamente.', 'aura-suite'));
                closeLinkModal();
                fetchContacts();
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al vincular', 'aura-suite'));
            }
        });
    }

    function createThirdPartyFromContact(contactId) {
        contactId = contactId || $('#link-target-contact-id').val();
        if (!contactId || parseInt(contactId, 10) <= 0) return;

        if (!confirm(__('¿Deseas registrar este contacto como un nuevo Tercero en el catálogo comercial y contable de Finanzas?', 'aura-suite'))) {
            return;
        }

        var $btn = $('#btn-link-modal-create-tp');
        var origText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Creando...', 'aura-suite'));

        $.post(cfg.ajaxUrl, {
            action: 'aura_contacts_create_third_party',
            nonce: cfg.nonce,
            contact_id: contactId
        }, function(res) {
            $btn.prop('disabled', false).html(origText);
            if (res.success) {
                showToast(res.data && res.data.message ? res.data.message : __('¡Tercero creado y vinculado con éxito!', 'aura-suite'));
                closeLinkModal();
                fetchContacts();
                refreshStats();
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al crear el Tercero.', 'aura-suite'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(origText);
            alert(__('Error de conexión al intentar crear el Tercero.', 'aura-suite'));
        });
    }

    function createThirdPartyFromForm() {
        var contactId = $('#contact-form-id').val();
        if (!contactId || parseInt(contactId, 10) <= 0) {
            alert(__('Debes guardar el contacto primero antes de poder crearlo como Tercero.', 'aura-suite'));
            return;
        }

        if (!confirm(__('¿Deseas crear y registrar este contacto en el catálogo comercial de Terceros de Finanzas?', 'aura-suite'))) {
            return;
        }

        var $btn = $('#btn-form-create-tp');
        var origText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Creando...', 'aura-suite'));

        $.post(cfg.ajaxUrl, {
            action: 'aura_contacts_create_third_party',
            nonce: cfg.nonce,
            contact_id: contactId
        }, function(res) {
            $btn.prop('disabled', false).html(origText);
            if (res.success && res.data) {
                $('#contact-third-party-id').val(res.data.third_party_id);
                $('#contact-third-party-name').text(res.data.third_party_name || sprintf(__('Tercero #%s', 'aura-suite'), res.data.third_party_id));
                $('#contact-third-party-selected').show();
                $('#contact-form-create-tp-wrap').hide();
                showToast(res.data.message || __('¡Tercero creado y vinculado con éxito!', 'aura-suite'));
                fetchContacts();
                refreshStats();
            } else {
                alert(res.data && res.data.message ? res.data.message : __('Error al crear el Tercero.', 'aura-suite'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(origText);
            alert(__('Error de conexión al intentar crear el Tercero.', 'aura-suite'));
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
            if (cfg.canDelete) {
                $('#btn-contacts-bulk-delete').show().css('display', 'inline-flex');
                $('#contacts-bulk-delete-count').text(count);
            }
            // Ocultar la barra de pestañas para que no queden dos barras apiladas
            $('.aura-contacts-navbar').slideUp(150);
            $('#contacts-bulk-bar').slideDown(150).css('display', 'flex');
            $('#contacts-bulk-badge-circle').text(count);
            $('#contacts-bulk-bar-label').text(count === 1 ? sprintf(__('%d contacto seleccionado', 'aura-suite'), count) : sprintf(__('%d contactos seleccionados', 'aura-suite'), count));
        } else {
            $('#btn-contacts-bulk-edit').hide();
            $('#btn-contacts-bulk-delete').hide();
            $('#contacts-bulk-bar').slideUp(150);
            $('#contacts-select-all').prop('checked', false);
            // Restaurar la barra de pestañas flotante
            $('.aura-contacts-navbar').slideDown(150).css('display', 'flex');
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
            openBulkModal();
        });

        // Cerrar modal
        $(document).on('click', '#btn-close-bulk-modal, #btn-cancel-bulk-modal', function(e) {
            e.preventDefault();
            closeBulkModal();
        });

        // Cerrar modal al hacer clic en el backdrop
        $(document).on('click', '#modal-bulk-edit-contacts', function(e) {
            if ($(e.target).is('#modal-bulk-edit-contacts')) {
                closeBulkModal();
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
                alert(__('No hay contactos seleccionados.', 'aura-suite'));
                return;
            }

            var applyCategory = $('#bulk-chk-category').is(':checked');
            var applyTags     = $('#bulk-chk-tags').is(':checked');
            var applyStatus   = $('#bulk-chk-status').is(':checked');
            var applySync     = $('#bulk-chk-sync').is(':checked');

            if (!applyCategory && !applyTags && !applyStatus && !applySync) {
                alert(__('Debes marcar al menos una casilla para aplicar cambios a los contactos seleccionados.', 'aura-suite'));
                return;
            }

            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + __('Aplicando cambios...', 'aura-suite'));

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
                        closeBulkModal();
                        showToast(res.data && res.data.message ? res.data.message : __('Edición masiva aplicada con éxito', 'aura-suite'));
                        $('.contact-row-check, #contacts-select-all').prop('checked', false);
                        updateBulkState();
                        fetchContacts();
                    } else {
                        alert(res && res.data && res.data.message ? res.data.message : __('Error al guardar cambios masivos', 'aura-suite'));
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(originalHtml);
                    var msg = __('Error de comunicación con el servidor al procesar la edición masiva', 'aura-suite');
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    } else if (xhr && xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.data && parsed.data.message) {
                                msg = parsed.data.message;
                            }
                        } catch (err) {}
                    }
                    alert(msg);
                }
            });
        });

        // Re-sincronizar contactos seleccionados con Google People API
        $(document).on('click', '#btn-bulk-bar-sync', function(e) {
            e.preventDefault();
            var ids = getSelectedContactIds();
            if (!ids.length) return;

            if (!confirm(sprintf(__('¿Deseas sincronizar los %d contactos seleccionados con Google Contacts ahora?', 'aura-suite'), ids.length))) {
                return;
            }

            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + sprintf(__('Sincronizando (0/%d)...', 'aura-suite'), ids.length));

            var successCount = 0;
            var errorCount = 0;

            function syncItem(index) {
                if (index >= ids.length) {
                    $btn.prop('disabled', false).html(originalHtml);
                    showToast(sprintf(__('Sincronización masiva finalizada: %d procesados', 'aura-suite'), successCount) + (errorCount ? ', ' + sprintf(__('%d con error', 'aura-suite'), errorCount) : ''));
                    $('.contact-row-check, #contacts-select-all').prop('checked', false);
                    updateBulkState();
                    fetchContacts();
                    return;
                }

                $btn.html('<span class="dashicons dashicons-update spin"></span> ' + sprintf(__('Sincronizando (%1$d/%2$d)...', 'aura-suite'), (index + 1), ids.length));

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

        // Eliminar contactos seleccionados en lote (Bulk Delete)
        $(document).on('click', '#btn-bulk-bar-delete, #btn-contacts-bulk-delete, #btn-bulk-modal-delete', function(e) {
            e.preventDefault();
            bulkDeleteContacts(false);
        });
    }

    // ── Función de Eliminación Masiva (Bulk Delete) ──────────────────────────────
    function bulkDeleteContacts(force) {
        var ids = getSelectedContactIds();
        if (!ids.length) {
            alert(__('Por favor selecciona al menos un contacto en la tabla para eliminar.', 'aura-suite'));
            return;
        }

        var confirmMsg = force
            ? (cfg.i18n && cfg.i18n.confirmBulkPermanent 
                ? cfg.i18n.confirmBulkPermanent.replace('%d', ids.length) 
                : sprintf(__('¿Estás seguro de eliminar permanentemente los %d contactos seleccionados? Esta acción no se puede deshacer.', 'aura-suite'), ids.length))
            : (cfg.i18n && cfg.i18n.confirmBulkDelete 
                ? cfg.i18n.confirmBulkDelete.replace('%d', ids.length) 
                : sprintf(__('¿Estás seguro de mover los %d contactos seleccionados a la papelera?', 'aura-suite'), ids.length));

        if (!confirm(confirmMsg)) {
            return;
        }

        var $buttons = $('#btn-bulk-bar-delete, #btn-contacts-bulk-delete, #btn-bulk-modal-delete');
        $buttons.prop('disabled', true);

        $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_contacts_bulk_delete',
                nonce: cfg.nonce,
                ids: ids,
                force: force ? 1 : 0
            },
            success: function(res) {
                $buttons.prop('disabled', false);
                if (res && res.success) {
                    closeBulkModal();
                    showToast(res.data && res.data.message ? res.data.message : __('Contactos eliminados correctamente.', 'aura-suite'));
                    $('.contact-row-check, #contacts-select-all').prop('checked', false);
                    updateBulkState();
                    fetchContacts();
                    if (typeof refreshStats === 'function') {
                        refreshStats();
                    }
                } else {
                    alert(res && res.data && res.data.message ? res.data.message : __('Error al eliminar los contactos seleccionados.', 'aura-suite'));
                }
            },
            error: function(xhr) {
                $buttons.prop('disabled', false);
                var msg = __('Error de comunicación con el servidor al eliminar contactos.', 'aura-suite');
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                alert(msg);
            }
        });
    }

    window.bulkDeleteContacts = bulkDeleteContacts;

    // ── Control del Modal de Edición Masiva ──────────────────────────────────────
    function openBulkModal() {
        var ids = getSelectedContactIds();
        if (!ids.length) {
            alert(__('Por favor selecciona al menos un contacto en la tabla para editar.', 'aura-suite'));
            return;
        }

        $('#bulk-edit-modal-count').text(ids.length);

        // Reiniciar estado de activación de campos (Regla de Seguridad)
        $('#bulk-chk-category, #bulk-chk-tags, #bulk-chk-status').prop('checked', false);
        $('#bulk-chk-sync').prop('checked', true);
        $('#bulk-wrap-category, #bulk-wrap-tags, #bulk-wrap-status').css({ opacity: 0.45, 'pointer-events': 'none' });
        $('#bulk-val-tags').val('');
        setupTagsCloud($('#bulk-tags-cloud'), $('#bulk-val-tags'));

        $('#modal-bulk-edit-contacts')
            .removeClass('is-hidden')
            .addClass('active')
            .css({ display: 'flex', opacity: 1, visibility: 'visible', 'z-index': 999999 });
    }

    function closeBulkModal() {
        $('#modal-bulk-edit-contacts')
            .removeClass('active')
            .addClass('is-hidden')
            .css({ display: 'none', opacity: 0, visibility: 'hidden' });
    }

    window.openBulkModal = openBulkModal;
    window.closeBulkModal = closeBulkModal;

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

    // ── Cierre de Modales por Teclado (Escape) y Clic en Overlay ─────────────
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if ($('#modal-contact-form').is(':visible')) {
                closeContactModal();
            } else if ($('#modal-bulk-edit-contacts').is(':visible')) {
                closeBulkModal();
            } else if ($('#modal-merge-contacts').is(':visible')) {
                closeMergeModal();
            } else if ($('#modal-link-entity').is(':visible')) {
                closeLinkModal();
            } else if ($('#modal-oauth-tutorial').is(':visible')) {
                closeOAuthTutorial();
            } else if ($('#modal-contact-card').is(':visible')) {
                closeContactCard();
            } else if ($('#modal-sync-progress').is(':visible')) {
                closeSyncModal();
            }
        }
    });

    $(document).on('click', '.aura-modal-overlay', function(e) {
        if ($(e.target).hasClass('aura-modal-overlay')) {
            var id = $(this).attr('id');
            if (id === 'modal-contact-form') {
                closeContactModal();
            } else if (id === 'modal-bulk-edit-contacts') {
                closeBulkModal();
            } else if (id === 'modal-merge-contacts') {
                closeMergeModal();
            } else if (id === 'modal-link-entity') {
                closeLinkModal();
            } else if (id === 'modal-oauth-tutorial') {
                closeOAuthTutorial();
            } else if (id === 'modal-contact-card') {
                closeContactCard();
            } else if (id === 'modal-sync-progress') {
                closeSyncModal();
            }
        }
    });

    // Exponer API para botones inline
    window.AuraContactsApp = {
        editContact: openEditModal,
        viewContactCard: viewContactCard,
        closeContactCard: closeContactCard,
        startSync: startProgressiveGoogleSync,
        openSyncModal: openSyncModal,
        closeSyncModal: closeSyncModal,
        cancelSync: cancelSync,
        openLinkEntityModal: window.openLinkEntityModal,
        createThirdPartyFromContact: createThirdPartyFromContact,
        createThirdPartyFromForm: createThirdPartyFromForm,
        syncSingleContact: syncSingle,
        deleteContact: deleteContactItem,
        bulkDelete: bulkDeleteContacts,
        refresh: fetchContacts,
        refreshStats: refreshStats,
        openBulkEditModal: openBulkModal,
        closeBulkEditModal: closeBulkModal,
        getSelectedIds: getSelectedContactIds
    };

})(jQuery);

