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

    // ── Paginación ───────────────────────────────────────────────────────────────
    function renderPagination(data) {
        var total = data.total || 0;
        var start = total > 0 ? (state.page - 1) * state.perPage + 1 : 0;
        var end = Math.min(state.page * state.perPage, total);
        $('#contacts-pagination-info').text('Mostrando ' + start + ' - ' + end + ' de ' + total + ' contactos');

        var pages = data.pages || 1;
        var $controls = $('#contacts-pagination-controls').empty();

        // Botón Anterior (siempre visible)
        var $prev = $('<button type="button" class="btn btn-glass btn-xs" ' + (state.page <= 1 ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : '') + ' title="Página anterior"><span class="dashicons dashicons-arrow-left-alt2"></span></button>');
        $prev.on('click', function() {
            if (state.page > 1) {
                state.page--;
                fetchContacts();
            }
        });
        $controls.append($prev);

        // Páginas numeradas
        for (var p = 1; p <= pages; p++) {
            if (p === 1 || p === pages || (p >= state.page - 2 && p <= state.page + 2)) {
                var $pBtn = $('<button type="button" class="btn btn-xs ' + (p === state.page ? 'btn-primary' : 'btn-glass') + '">' + p + '</button>');
                (function(pageIndex) {
                    $pBtn.on('click', function() {
                        if (state.page !== pageIndex) {
                            state.page = pageIndex;
                            fetchContacts();
                        }
                    });
                })(p);
                $controls.append($pBtn);
            } else if (p === state.page - 3 || p === state.page + 3) {
                $controls.append('<span style="padding: 0 4px; color: #94a3b8;">...</span>');
            }
        }

        // Botón Siguiente (siempre visible)
        var $next = $('<button type="button" class="btn btn-glass btn-xs" ' + (state.page >= pages ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : '') + ' title="Página siguiente"><span class="dashicons dashicons-arrow-right-alt2"></span></button>');
        $next.on('click', function() {
            if (state.page < pages) {
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

    // ── Gestión del Modal de Contacto (Crear / Editar) ───────────────────────────
    function initModals() {
        $('#btn-open-create-modal').on('click', function() {
            openCreateModal();
        });

        $('#contact-edit-form').on('submit', function(e) {
            e.preventDefault();
            saveContact();
        });

        // Autocomplete en formulario de creación para vincular tercero
        var tpSearchTimer = null;
        $('#contact-link-third-party-search').on('input', function() {
            var q = $(this).val().trim();
            clearTimeout(tpSearchTimer);
            if (q.length < 2) {
                $('#contact-third-party-results').hide().empty();
                return;
            }
            tpSearchTimer = setTimeout(function() {
                searchThirdParties(q, function(results) {
                    var $box = $('#contact-third-party-results').empty();
                    if (!results || results.length === 0) {
                        $box.append('<div class="autocomplete-item" style="color:#94a3b8;">No se encontraron terceros</div>').show();
                        return;
                    }
                    results.forEach(function(item) {
                        var $item = $('<div class="autocomplete-item"><span><strong>' + escapeHtml(item.name) + '</strong> (' + escapeHtml(item.type_label || '') + ')</span><span style="font-size:0.75rem;color:#38bdf8;">Seleccionar</span></div>');
                        $item.on('click', function() {
                            $('#contact-third-party-id').val(item.id);
                            $('#contact-third-party-name').text(item.name);
                            $('#contact-third-party-selected').show();
                            $('#contact-link-third-party-search').val('');
                            $box.hide().empty();
                        });
                        $box.append($item);
                    });
                    $box.show();
                });
            }, 250);
        });
        // Cerrar modal al hacer clic en el backdrop oscuro
        $('.aura-modal-overlay').on('click', function(e) {
            if ($(e.target).hasClass('aura-modal-overlay')) {
                $(this).removeClass('active').addClass('is-hidden').css({ display: 'none', opacity: 0, visibility: 'hidden' });
            }
        });
    }

    function openCreateModal() {
        $('#contact-modal-title').html('📇 ' + 'Nuevo Contacto');
        $('#contact-form-id').val(0);
        var form = document.getElementById('contact-edit-form');
        if (form && typeof form.reset === 'function') {
            form.reset();
        }
        $('#contact-third-party-id').val(0);
        $('#contact-third-party-selected').hide();
        $('#modal-contact-form')
            .removeClass('is-hidden')
            .addClass('active')
            .css({ display: 'flex', opacity: 1, visibility: 'visible' });
        setTimeout(function() {
            $('#contact-first-name').focus();
        }, 60);
    }

    function openEditModal(id) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_get_detail', nonce: cfg.nonce, id: id }, function(res) {
            if (!res.success || !res.data) {
                alert('No se pudo cargar la información del contacto.');
                return;
            }
            var c = res.data;
            $('#contact-modal-title').html('✏️ ' + 'Editar Contacto: ' + escapeHtml(c.first_name + ' ' + (c.last_name || '')));
            $('#contact-form-id').val(c.id);
            $('#contact-first-name').val(c.first_name || '');
            $('#contact-last-name').val(c.last_name || '');
            $('#contact-email').val(c.email || '');
            $('#contact-phone').val(c.phone || '');
            $('#contact-category').val(c.category || 'other');
            $('#contact-organization').val(c.organization || '');
            $('#contact-job-title').val(c.job_title || '');
            $('#contact-address').val(c.address || '');
            $('#contact-notes').val(c.notes || '');

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
            } else {
                $('#contact-third-party-id').val(0);
                $('#contact-third-party-selected').hide();
            }

            $('#modal-contact-form')
                .removeClass('is-hidden')
                .addClass('active')
                .css({ display: 'flex', opacity: 1, visibility: 'visible' });
        });
    }

    function saveContact() {
        var $btn = $('#btn-save-contact');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Guardando...');

        var formData = $('#contact-edit-form').serialize();
        formData += '&action=aura_contacts_save&nonce=' + encodeURIComponent(cfg.nonce);

        $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html(originalText);
                if (res.success) {
                    closeContactModal();
                    fetchContacts();
                    showToast(cfg.i18n.saveSuccess || 'Contacto guardado correctamente.');
                } else {
                    alert(res.data && res.data.message ? res.data.message : (cfg.i18n.saveError || 'Error al guardar'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
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

        $.post(cfg.ajaxUrl, { action: 'aura_contacts_get_sync_logs', nonce: cfg.nonce, limit: 50 }, function(res) {
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
                        $('#res-total').text(d.total || 0);
                        $('#res-created').text(d.created || 0);
                        $('#res-updated').text(d.updated || 0);
                        $('#res-skipped').text(d.skipped || 0);
                        $('#import-results-card').slideDown(200);

                        showToast('Importación completada: ' + (d.created || 0) + ' creados, ' + (d.updated || 0) + ' actualizados.');
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
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                $('#duplicates-container').html('<div class="aura-notice-box is-error"><p>Error de conexión al escanear duplicados.</p></div>');
            });
        });
    }

    function renderDuplicateClusters(clusters) {
        var html = '<div style="display:flex;flex-direction:column;gap:20px;">';
        clusters.forEach(function(cluster, idx) {
            var reasonsHtml = cluster.reasons.map(function(r) {
                var bg = r.type === 'email' ? 'background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);' :
                         (r.type === 'phone' ? 'background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.3);' :
                         'background:rgba(167,139,250,0.15);color:#a78bfa;border:1px solid rgba(167,139,250,0.3);');
                return '<span style="font-size:0.75rem;padding:3px 8px;border-radius:6px;' + bg + '"><strong>' + escapeHtml(r.label) + ':</strong> ' + escapeHtml(r.value) + '</span>';
            }).join(' ');

            html += '<div class="aura-glass-card cluster-card" style="padding:18px;border-radius:12px;background:rgba(255,255,255,0.03);" data-cluster="' + idx + '">';
            html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">';
            html += '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
            html += '<span style="font-weight:700;color:#fff;font-size:0.95rem;">Grupo ' + (idx + 1) + ' (' + cluster.contacts.length + ' coincidencias)</span>';
            html += reasonsHtml;
            html += '</div>';
            html += '<button type="button" class="btn btn-emerald btn-sm btn-lift btn-merge-cluster" data-cluster="' + idx + '"><span class="dashicons dashicons-randomize"></span> <span>Fusionar en Maestro</span></button>';
            html += '</div>';

            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">';
            cluster.contacts.forEach(function(c, cIdx) {
                var isChecked = cIdx === 0 ? 'checked' : '';
                var avatarUrl = c.avatar_url || c.photo_url || '';

                html += '<label class="contact-card-selectable" style="display:flex;align-items:flex-start;gap:12px;padding:12px;border-radius:8px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);cursor:pointer;">';
                html += '<input type="radio" name="master_cluster_' + idx + '" value="' + c.id + '" ' + isChecked + ' style="margin-top:4px;">';
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">';
                if (avatarUrl) {
                    html += '<img src="' + escapeHtml(avatarUrl) + '" style="width:26px;height:26px;border-radius:50%;object-fit:cover;" onerror="this.style.display=\'none\';">';
                }
                html += '<strong style="color:#fff;font-size:0.88rem;">' + escapeHtml(c.first_name + ' ' + (c.last_name || '')) + '</strong>';
                html += '<span style="font-size:0.7rem;color:#94a3b8;">#' + c.id + '</span>';
                html += '</div>';
                html += '<div style="font-size:0.8rem;color:#94a3b8;display:flex;flex-direction:column;gap:2px;">';
                if (c.email_primary) html += '<span>✉️ ' + escapeHtml(c.email_primary) + '</span>';
                if (c.phone_primary) html += '<span>📞 ' + escapeHtml(c.phone_primary) + '</span>';
                if (c.organization) html += '<span>🏢 ' + escapeHtml(c.organization) + '</span>';
                if (c.third_party_id && parseInt(c.third_party_id) > 0) html += '<span style="color:#38bdf8;">🔗 Vinculado a Tercero</span>';
                html += '</div>';
                html += '</div>';
                html += '</label>';
            });
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';

        $('#duplicates-container').html(html);

        // Handler del botón de fusión
        $('.btn-merge-cluster').on('click', function() {
            var clusterIdx = $(this).data('cluster');
            var cluster = clusters[clusterIdx];
            var primaryId = $('input[name="master_cluster_' + clusterIdx + '"]:checked').val();
            if (!primaryId) {
                alert('Por favor selecciona el contacto maestro que prevalecerá.');
                return;
            }

            var secondaryIds = cluster.contacts
                .map(function(c) { return parseInt(c.id); })
                .filter(function(id) { return id !== parseInt(primaryId); });

            if (!secondaryIds.length) {
                alert('No hay contactos duplicados para fusionar en este grupo.');
                return;
            }

            if (!confirm('¿Estás seguro de fusionar ' + secondaryIds.length + ' contacto(s) en el contacto #' + primaryId + '?\n\nLos teléfonos, emails, notas y relaciones se unificarán en el maestro y los registros redundantes se archivarán.')) {
                return;
            }

            var $btn = $(this);
            var orig = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Fusionando...');

            $.post(cfg.ajaxUrl, {
                action: 'aura_contacts_merge',
                nonce: cfg.nonce,
                target_id: primaryId,
                source_ids: secondaryIds
            }, function(res) {
                if (res.success) {
                    showToast('Contactos fusionados correctamente en el registro #' + primaryId);
                    $('.cluster-card[data-cluster="' + clusterIdx + '"]').fadeOut(300, function() {
                        $(this).remove();
                        if (!$('.cluster-card').length) {
                            $('#duplicates-container').html(
                                '<div style="text-align:center;padding:50px 20px;">' +
                                '<div style="font-size:42px;margin-bottom:12px;">🎉</div>' +
                                '<h3 style="margin:0 0 8px;color:#34d399;font-size:1.15rem;">¡Fusión Completada!</h3>' +
                                '<p style="margin:0;color:#94a3b8;font-size:0.9rem;">Todos los grupos detectados han sido resueltos satisfactoriamente.</p>' +
                                '</div>'
                            );
                        }
                    });
                    fetchContacts();
                    refreshStats();
                } else {
                    $btn.prop('disabled', false).html(orig);
                    alert(res.data && res.data.message ? res.data.message : 'Error al fusionar contactos');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(orig);
                alert('Error de conexión durante la fusión');
            });
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

    function searchThirdParties(term, callback) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_search_third_parties', nonce: cfg.nonce, term: term }, function(res) {
            callback(res.success ? res.data : []);
        });
    }

    function searchWpUsers(term, callback) {
        $.post(cfg.ajaxUrl, { action: 'aura_contacts_search_wp_users', nonce: cfg.nonce, term: term }, function(res) {
            callback(res.success ? res.data : []);
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
        syncSingleContact: syncSingle,
        deleteContact: deleteContactItem,
        refresh: fetchContacts,
        refreshStats: refreshStats
    };

})(jQuery);

