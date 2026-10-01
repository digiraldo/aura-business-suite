/**
 * Selector Reutilizable de Terceros, Empresas y Personas (Aura Suite)
 * 
 * Permite autocompletado inteligente agrupado por rol contable
 * y apertura de Modal Explorador de Terceros y Usuarios clasificado por filtros contables y roles del sistema.
 * 
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 1.8.2
 */

window.AuraThirdPartySelector = (function($) {
    'use strict';

    const partyTypeIcons = {
        'company': 'dashicons-building',
        'store': 'dashicons-cart',
        'organization_foundation': 'dashicons-heart',
        'person': 'dashicons-businessman',
        'religious': 'dashicons-location-alt',
        'other': 'dashicons-category',
        'user': 'dashicons-admin-users'
    };

    let activeExplorerOptions = null;
    let catalogCache = null;
    let currentFilterRole = 'all';
    let currentFilterType = 'all';
    let currentFilterWpRole = 'all';
    let currentSort = 'name_asc';

    /**
     * Adjuntar autocompletado inteligente a un campo de texto
     */
    function attach(options) {
        const settings = $.extend({
            input: null,
            hiddenId: null,
            previewContainer: null,
            allowUsers: true,
            onSelect: null,
            onClear: null
        }, options);

        const $input = $(settings.input);
        if (!$input.length) return;

        const $hiddenId = settings.hiddenId ? $(settings.hiddenId) : null;
        const $preview = settings.previewContainer ? $(settings.previewContainer) : null;

        const ajaxUrl = (window.auraCounterpartiesData && auraCounterpartiesData.ajaxUrl) || (window.auraTransactionData && auraTransactionData.ajaxUrl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
        const nonce = (window.auraCounterpartiesData && auraCounterpartiesData.nonce) || (window.auraTransactionData && auraTransactionData.nonce) || '';

        $input.autocomplete({
            minLength: 1,
            delay: 200,
            source: function(request, response) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aura_search_counterparties',
                        nonce: nonce,
                        term: request.term
                    },
                    success: function(res) {
                        if (res.success && Array.isArray(res.data)) {
                            response(res.data);
                        } else {
                            response([]);
                        }
                    },
                    error: function() { response([]); }
                });
            },
            select: function(event, ui) {
                const item = ui.item;
                $input.val(item.commercial_name || item.name || item.value).trigger('input').trigger('change');

                if ($hiddenId && $hiddenId.length) {
                    let idVal;
                    if (settings.formatId === 'prefixed') {
                        const isUser = item.type === 'wp_user' || item.is_wp_user || String(item.id).indexOf('user_') === 0 || String(item.id).indexOf('wp:') === 0;
                        const rawId = item.user_id || item.third_party_id || String(item.id).replace('user_', '').replace('wp_', '').replace('tp_', '').replace('wp:', '').replace('tp:', '');
                        idVal = isUser ? ('wp:' + rawId) : ('tp:' + rawId);
                    } else {
                        idVal = item.type === 'third_party' ? (item.third_party_id || item.id) : (item.user_id || item.id);
                    }
                    $hiddenId.val(idVal).trigger('change');
                }

                // Actualizar tarjeta preview si está configurada
                if (settings.previewContainer) {
                    const $prev = $(settings.previewContainer);
                    if ($prev.length) {
                        $prev.css('display', 'flex');
                        const $text = settings.previewText ? $(settings.previewText) : $prev.find('.entity-name, #aura-tp-preview-text');
                        const $badge = settings.previewBadge ? $(settings.previewBadge) : $prev.find('.entity-badge, #aura-tp-preview-badge');
                        const $avatar = settings.previewAvatar ? $(settings.previewAvatar) : $prev.find('.avatar-box, #aura-tp-preview-avatar');

                        const isUser = item.type === 'wp_user' || item.is_wp_user;
                        const docText = item.document_id ? ' (' + (item.tax_id_type || 'NIT') + ': ' + item.document_id + ')' : (item.email ? ' (' + item.email + ')' : '');
                        if ($text.length) $text.text((item.commercial_name || item.name) + docText);
                        if ($badge.length) $badge.text(item.accounting_role_label || item.party_type_label || (isUser ? 'Usuario' : 'Tercero'));
                        if ($avatar.length) {
                            if (item.avatar_url) {
                                $avatar.html('<img src="' + item.avatar_url + '" width="28" height="28" style="object-fit:cover;border-radius:6px;width:100%;height:100%;">');
                            } else {
                                const icon = isUser ? 'dashicons-admin-users' : (partyTypeIcons[item.party_type] || 'dashicons-businessman');
                                $avatar.html('<span class="dashicons ' + icon + '" style="font-size:16px;width:16px;height:16px;color:#64748b;"></span>');
                            }
                        }
                    }
                }

                if (typeof settings.onSelect === 'function') {
                    settings.onSelect(item);
                }

                return false;
            }
        });

        // Sobrescribir renderizado de menú para agrupar visualmente por Rol Contable
        const autocompInstance = $input.autocomplete('instance');
        if (autocompInstance) {
            autocompInstance._renderMenu = function(ul, items) {
                const that = this;
                let currentCategory = "";
                ul.addClass('aura-ac-dropdown-menu');

                $.each(items, function(index, item) {
                    const category = item.category || item.accounting_role_label || 'Terceros';
                    if (category !== currentCategory) {
                        const iconClass = item.icon || 'dashicons-category';
                        ul.append(
                            '<li class="ui-autocomplete-category aura-ac-category-header">' +
                            '<span class="dashicons ' + iconClass + '" style="font-size:14px;width:14px;height:14px;line-height:1;margin-right:6px;vertical-align:middle;"></span>' +
                            $('<span>').text(category).html() +
                            '</li>'
                        );
                        currentCategory = category;
                    }
                    that._renderItemData(ul, item);
                });
            };

            autocompInstance._renderItem = function(ul, item) {
                let avatarContent = '';
                if (item.avatar_url) {
                    avatarContent = '<img src="' + item.avatar_url + '" width="28" height="28" style="border-radius:6px;object-fit:cover;flex-shrink:0;">';
                } else {
                    const icon = partyTypeIcons[item.party_type] || 'dashicons-building';
                    avatarContent = '<div style="width:28px;height:28px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><span class="dashicons ' + icon + '" style="font-size:16px;width:16px;height:16px;color:#64748b;"></span></div>';
                }

                const badgeClass = item.category_badge || (item.type === 'wp_user' ? 'aura-pill--info' : 'aura-pill--muted');
                const badgeLabel = item.accounting_role_label || item.party_type_label || item.type;
                const badge = '<span class="aura-pill ' + badgeClass + '" style="font-size:10px;padding:2px 7px;font-weight:700;">' + $('<span>').text(badgeLabel).html() + '</span>';
                const sub = item.document_id ? ((item.tax_id_type || 'NIT') + ': ' + item.document_id) : (item.email || '');

                return $('<li class="aura-ac-item">')
                    .append(
                        '<div style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;">' +
                        avatarContent +
                        '<div style="line-height:1.35;flex-grow:1;min-width:0;">' +
                        '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">' +
                        '<strong style="font-size:13px;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + $('<span>').text(item.commercial_name || item.name).html() + '</strong>' +
                        badge +
                        '</div>' +
                        (sub ? '<div style="color:#64748b;font-size:11.5px;margin-top:2px;">' + $('<span>').text(sub).html() + '</div>' : '') +
                        '</div>' +
                        '</div>'
                    )
                    .appendTo(ul);
            };
        }
    }

    /**
     * Modal Explorador de Terceros y Entidades Comerciales
     */
    function ensureExplorerModalDOM() {
        if ($('#aura-tp-explorer-modal').length) return;

        const modalHtml = `
        <div id="aura-tp-explorer-modal" class="aura-tp-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);z-index:1002000 !important;align-items:center;justify-content:center;backdrop-filter:blur(6px);padding:16px;box-sizing:border-box;">
            <div class="aura-tp-modal-dialog" style="background:#fff;border-radius:18px;width:100%;max-width:960px;max-height:min(92vh, 840px);height:auto;display:flex;flex-direction:column;box-shadow:0 25px 60px -15px rgba(0,0,0,0.35);overflow:hidden;border:1px solid #cbd5e1;">
                
                <!-- Modal Header -->
                <div class="aura-tp-modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc;flex-shrink:0;flex:0 0 auto;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div id="aura-tp-modal-icon-box" style="width:40px;height:40px;border-radius:10px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;border:1px solid #bfdbfe;flex-shrink:0;">
                            <span class="dashicons dashicons-groups" style="font-size:24px;width:24px;height:24px;"></span>
                        </div>
                        <div>
                            <h3 id="aura-tp-modal-title" style="margin:0;font-size:1.2rem;font-weight:700;color:#0f172a;letter-spacing:-0.2px;">Catálogo de Terceros y Entidades Comerciales</h3>
                            <p id="aura-tp-modal-subtitle" style="margin:2px 0 0;font-size:0.85rem;color:#64748b;">Explora entidades por tipo (Empresas, Tiendas, Fundaciones, Personas) y rol contable o registra uno nuevo.</p>
                        </div>
                    </div>
                    <button type="button" class="aura-tp-modal-close-icon" style="background:none;border:none;font-size:24px;cursor:pointer;color:#64748b;line-height:1;padding:6px 10px;border-radius:8px;transition:background .15s;" title="Cerrar (Esc)">&times;</button>
                </div>

                <!-- Controls & Filters Bar -->
                <div class="aura-tp-modal-filters-bar" style="padding:14px 24px 10px;background:#fff;border-bottom:1px solid #f1f5f9;display:flex;flex-direction:column;gap:10px;flex-shrink:0;flex:0 0 auto;">
                    
                    <!-- Search Input + Filter & Sort Controls -->
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <div style="position:relative;flex:1;min-width:260px;">
                            <input type="text" id="aura-tp-explorer-search" placeholder="Buscar por nombre, razón social, NIT / Documento, @login o correo..." style="width:100%;height:42px;padding:0 36px 0 38px;border-radius:10px;border:1px solid #cbd5e1;font-size:0.92rem;box-sizing:border-box;background:#f8fafc;transition:border-color .15s, box-shadow .15s;">
                            <span class="dashicons dashicons-search" style="position:absolute;left:11px;top:11px;color:#94a3b8;font-size:20px;"></span>
                            <button type="button" id="aura-tp-search-clear" style="position:absolute;right:8px;top:10px;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;display:none;padding:2px 6px;">✕</button>
                        </div>

                        <!-- Filtro de Rol Contable Secundario -->
                        <div id="aura-tp-role-dropdown-wrap" style="display:flex;align-items:center;gap:6px;">
                            <label for="aura-tp-filter-role" style="font-size:12px;font-weight:600;color:#64748b;margin:0;">Rol Contable:</label>
                            <select id="aura-tp-filter-role" style="height:42px;border-radius:10px;border:1px solid #cbd5e1;font-size:12.5px;padding:0 24px 0 10px;background:#fff;color:#334155;cursor:pointer;">
                                <option value="all">Todos los Roles</option>
                                <option value="supplier">🏢 Proveedores / Comercios</option>
                                <option value="customer">👤 Clientes</option>
                                <option value="bank_creditor">🏦 Bancos / Acreedores</option>
                                <option value="tax_authority">🏛️ Impuestos / DIAN</option>
                                <option value="employee">👥 Empleados / Nómina</option>
                                <option value="other">🏷️ Otros</option>
                            </select>
                        </div>

                        <!-- Ordenar -->
                        <div style="display:flex;align-items:center;gap:6px;">
                            <label for="aura-tp-sort" style="font-size:12px;font-weight:600;color:#64748b;margin:0;">Ordenar:</label>
                            <select id="aura-tp-sort" style="height:42px;border-radius:10px;border:1px solid #cbd5e1;font-size:12.5px;padding:0 24px 0 10px;background:#fff;color:#334155;cursor:pointer;">
                                <option value="name_asc">Nombre (A - Z)</option>
                                <option value="name_desc">Nombre (Z - A)</option>
                                <option value="type">Por Tipo de Entidad</option>
                                <option value="role">Por Rol Contable</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tabs de Tipo / Naturaleza de Terceros -->
                    <div id="aura-tp-type-tabs" style="display:flex;gap:6px;overflow-x:auto;padding-bottom:2px;white-space:nowrap;">
                        <button type="button" class="aura-type-tab-btn is-active" data-type="all">Todos <span class="badge" data-type-count="all">0</span></button>
                        <button type="button" class="aura-type-tab-btn" data-type="company">🏢 Empresas <span class="badge" data-type-count="company">0</span></button>
                        <button type="button" class="aura-type-tab-btn" data-type="store">🛗️ Tiendas <span class="badge" data-type-count="store">0</span></button>
                        <button type="button" class="aura-type-tab-btn" data-type="organization_foundation">🎗️ Fundaciones <span class="badge" data-type-count="organization_foundation">0</span></button>
                        <button type="button" class="aura-type-tab-btn" data-type="person">👤 Persona Natural <span class="badge" data-type-count="person">0</span></button>
                        <button type="button" class="aura-type-tab-btn" data-type="religious">⛪ Religiosas <span class="badge" data-type-count="religious">0</span></button>
                        <button type="button" class="aura-type-tab-btn" data-type="other">🏷️ Otras <span class="badge" data-type-count="other">0</span></button>
                        <button type="button" class="aura-type-tab-btn" data-type="user">👥 Usuarios Sistema <span class="badge" data-type-count="user">0</span></button>
                    </div>


                    <!-- Pills de Roles WordPress (Modo Usuarios Sistema) -->
                    <div id="aura-user-role-filters" style="display:none;gap:6px;overflow-x:auto;padding-bottom:2px;white-space:nowrap;">
                        <button type="button" class="aura-role-pill is-active" data-wp-role="all">Todos los Roles <span class="badge" data-wp-count="all">0</span></button>
                    </div>

                    <!-- Resumen de Resultados -->
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#64748b;padding-top:2px;">
                        <span id="aura-tp-results-count">Cargando catálogo...</span>
                        <span id="aura-tp-active-filter-badge" style="font-size:11.5px;color:#2563eb;font-weight:600;"></span>
                    </div>

                </div>

                <!-- Items Grid / List -->
                <div id="aura-tp-explorer-body" style="padding:16px 24px;overflow-y:auto;flex:1 1 auto;min-height:0;background:#f8fafc;">
                    <div id="aura-tp-explorer-loading" style="text-align:center;padding:50px 0;color:#64748b;">
                        <span class="spinner is-active" style="float:none;width:32px;height:32px;"></span>
                        <p style="margin-top:12px;font-size:0.9rem;font-weight:500;">Cargando catálogo y entidades...</p>
                    </div>
                    
                    <div id="aura-tp-explorer-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:14px;"></div>
                    
                    <!-- Empty State -->
                    <div id="aura-tp-explorer-empty" style="display:none;text-align:center;padding:50px 20px;color:#64748b;">
                        <div style="width:54px;height:54px;border-radius:50%;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                            <span class="dashicons dashicons-search" style="font-size:28px;width:28px;height:28px;color:#64748b;"></span>
                        </div>
                        <h4 style="margin:0 0 4px;font-size:1rem;color:#1e293b;">No se encontraron resultados coincidentes</h4>
                        <p id="aura-tp-empty-hint" style="margin:0 0 16px;font-size:0.85rem;color:#64748b;">Intenta cambiar el término de búsqueda o seleccionar otra categoría.</p>
                        <button type="button" id="aura-tp-reset-filters-btn" class="button button-secondary" style="font-size:12px;">Restablecer Filtros</button>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="aura-tp-modal-footer" style="display:flex;justify-content:space-between;align-items:center;padding:12px 24px;border-top:1px solid #e2e8f0;background:#fff;flex-shrink:0;flex:0 0 auto;">
                    <div id="aura-tp-footer-actions">
                        <a href="${adminUrl('admin.php?page=aura-third-parties')}" target="_blank" class="aura-tp-link-manage" style="font-size:12.5px;color:#2563eb;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                            <span class="dashicons dashicons-plus-alt" style="font-size:15px;width:15px;height:15px;"></span> Registrar Nuevo Tercero en el Directorio
                        </a>
                    </div>
                    <button type="button" class="button button-large aura-tp-modal-btn-cancel" style="height:36px;padding:0 20px;border-radius:8px;font-weight:600;min-width:100px;">Cerrar</button>
                </div>

            </div>
        </div>
        `;

        $('body').append(modalHtml);
        bindExplorerEvents();
    }

    function adminUrl(path) {
        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl.replace('admin-ajax.php', path);
        }
        return '/wp-admin/' + path;
    }

    function bindExplorerEvents() {
        const $modal = $('#aura-tp-explorer-modal');

        // Cerrar modal
        $modal.on('click', '.aura-tp-modal-close-icon, .aura-tp-modal-btn-cancel, .aura-tp-modal-close', function(e) {
            e.preventDefault();
            closeExplorer();
        });

        // Cerrar con backdrop
        $modal.on('click', function(e) {
            if ($(e.target).is('#aura-tp-explorer-modal')) {
                closeExplorer();
                e.stopPropagation();
            }
        });

        // Cerrar con Escape
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#aura-tp-explorer-modal').is(':visible')) {
                closeExplorer();
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
            }
        });

        // Cambio de pestaña de Tipo / Naturaleza (Empresas, Tiendas, Fundaciones, Persona Natural, Usuarios)
        $modal.on('click', '.aura-type-tab-btn', function(e) {
            e.preventDefault();
            $modal.find('.aura-type-tab-btn').removeClass('is-active');
            $(this).addClass('is-active');
            currentFilterType = $(this).data('type') || 'all';

            if (currentFilterType === 'user') {
                $('#aura-user-role-filters').css('display', 'flex');
                $('#aura-tp-role-dropdown-wrap').hide();
            } else {
                $('#aura-user-role-filters').hide();
                $('#aura-tp-role-dropdown-wrap').show();
            }

            renderExplorerItems();
        });

        // Cambio de filtro dropdown de Rol Contable
        $modal.on('change', '#aura-tp-filter-role', function() {
            currentFilterRole = $(this).val() || 'all';
            renderExplorerItems();
        });

        // Cambio de pill de rol WordPress (en modo usuario)
        $modal.on('click', '.aura-role-pill', function(e) {
            e.preventDefault();
            $modal.find('.aura-role-pill').removeClass('is-active');
            $(this).addClass('is-active');
            currentFilterWpRole = $(this).data('wp-role') || 'all';
            renderExplorerItems();
        });

        // Ordenamiento
        $modal.on('change', '#aura-tp-sort', function() {
            currentSort = $(this).val();
            renderExplorerItems();
        });

        // Búsqueda en vivo con debounce
        let searchTimer;
        $modal.on('input', '#aura-tp-explorer-search', function() {
            const val = $(this).val();
            if (val) {
                $('#aura-tp-search-clear').show();
            } else {
                $('#aura-tp-search-clear').hide();
            }
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                renderExplorerItems();
            }, 120);
        });

        // Botón limpiar búsqueda
        $modal.on('click', '#aura-tp-search-clear', function(e) {
            e.preventDefault();
            $('#aura-tp-explorer-search').val('').trigger('input').focus();
        });

        // Restablecer filtros
        $modal.on('click', '#aura-tp-reset-filters-btn', function(e) {
            e.preventDefault();
            $('#aura-tp-explorer-search').val('');
            $('#aura-tp-search-clear').hide();
            $('#aura-tp-filter-role').val('all');
            $('#aura-tp-sort').val('name_asc');
            $modal.find('.aura-type-tab-btn').removeClass('is-active').filter('[data-type="all"]').addClass('is-active');
            $modal.find('.aura-role-pill').removeClass('is-active').filter('[data-wp-role="all"]').addClass('is-active');
            $('#aura-user-role-filters').hide();
            $('#aura-tp-role-dropdown-wrap').show();
            currentFilterType = 'all';
            currentFilterRole = 'all';
            currentFilterWpRole = 'all';
            currentSort = 'name_asc';
            renderExplorerItems();
        });

        // Selección de un tercero o usuario
        $modal.on('click', '.aura-tp-card-select-btn', function(e) {
            e.preventDefault();
            const isWpUser = $(this).data('is-user') == 1;
            const tpId = $(this).data('id');
            const userId = $(this).data('user-id') || 0;
            const role = $(this).data('role') || 'supplier';
            const roleLabel = $(this).data('role-label') || 'Tercero';
            const name = $(this).data('name');
            const commercial = $(this).data('commercial') || '';
            const doc = $(this).data('doc') || '';
            const taxType = $(this).data('tax') || 'NIT';
            const logo = $(this).data('logo') || '';
            const login = $(this).data('login') || '';
            const email = $(this).data('email') || '';
            const phone = $(this).data('phone') || '';
            const partyType = $(this).data('party-type') || (isWpUser ? 'user' : 'company');
            const partyTypeLabel = $(this).data('party-type-label') || '';

            const item = {
                id: isWpUser ? ('user_' + userId) : ('tp_' + tpId),
                third_party_id: isWpUser ? null : parseInt(tpId, 10),
                user_id: isWpUser ? parseInt(userId, 10) : null,
                type: isWpUser ? 'wp_user' : 'third_party',
                is_wp_user: isWpUser,
                name: name,
                login: login,
                email: email,
                phone: phone,
                commercial_name: commercial,
                party_type: partyType,
                party_type_label: partyTypeLabel,
                document_id: doc,
                tax_id_type: taxType,
                accounting_role: role,
                accounting_role_label: roleLabel,
                avatar_url: logo,
                logo_url: logo,
                wp_user_id: isWpUser ? parseInt(userId, 10) : null,
                value: commercial || name,
                label: (commercial || name) + (doc ? ' (' + taxType + ': ' + doc + ')' : '')
            };

            if (activeExplorerOptions) {
                const targetInput = activeExplorerOptions.targetInput;
                const targetHiddenId = activeExplorerOptions.targetHiddenId;

                // Caso A: El selector se abrió para el campo de Usuario Vinculado (#related_user_search)
                if (targetInput === '#related_user_search' || targetHiddenId === '#related_user_id') {
                    if (isWpUser) {
                        const fullUserName = name + (login ? ' (@' + login + ')' : '');
                        $('#related_user_search').val(fullUserName).trigger('input').trigger('change');
                        $('#related_user_id').data('user-name', fullUserName).val(userId).trigger('change');
                        $('#aura-user-avatar').attr('src', logo);
                        $('#aura-user-name').html('<strong>' + name + '</strong> <span style="color:#64748b;font-size:12px;">(@' + login + ' · ' + email + ')</span>');
                        $('#aura-user-preview').css('display', 'flex');
                    } else {
                        $('#related_user_search').val(commercial || name).trigger('input').trigger('change');
                        $('#related_user_id').data('user-name', '').val('').trigger('change');
                        $('#aura-user-preview').hide();
                    }
                } 
                // Caso B: El selector se abrió para Pagador / Beneficiario (#recipient_payer) o selector general
                else {
                    const isPrefixed = activeExplorerOptions.format === 'prefixed' || $(activeExplorerOptions.targetHiddenId).data('format') === 'prefixed';
                    const fullTpName = item.value || item.commercial_name || item.name;

                    if (targetInput) {
                        $(targetInput).val(fullTpName).trigger('input').trigger('change');
                    }
                    if (targetHiddenId) {
                        let idVal;
                        if (isPrefixed) {
                            idVal = isWpUser ? ('wp:' + userId) : ('tp:' + tpId);
                        } else {
                            idVal = isWpUser ? '' : item.third_party_id;
                        }
                        $(targetHiddenId).data('tp-name', fullTpName).val(idVal).trigger('change');
                    }

                    // Determinar contenedor de preview
                    const previewSel = activeExplorerOptions.targetPreview || activeExplorerOptions.previewContainer;
                    const $preview = previewSel ? $(previewSel) : $('#aura-tp-preview');

                    if ($preview.length) {
                        $preview.css('display', 'flex');
                        const $text = activeExplorerOptions.targetText ? $(activeExplorerOptions.targetText) : $preview.find('.entity-name, #aura-tp-preview-text');
                        const $badge = activeExplorerOptions.targetBadge ? $(activeExplorerOptions.targetBadge) : $preview.find('.entity-badge, #aura-tp-preview-badge');
                        const $avatar = activeExplorerOptions.targetAvatar ? $(activeExplorerOptions.targetAvatar) : $preview.find('.avatar-box, #aura-tp-preview-avatar');

                        const docText = item.document_id ? ' (' + (item.tax_id_type || 'NIT') + ': ' + item.document_id + ')' : (item.email ? ' (' + item.email + ')' : '');
                        if ($text.length) $text.text((item.commercial_name || item.name) + docText);
                        if ($badge.length) $badge.text(item.accounting_role_label || (isWpUser ? 'Usuario' : 'Tercero'));
                        if ($avatar.length) {
                            if (item.avatar_url) {
                                $avatar.html('<img src="' + item.avatar_url + '" width="28" height="28" style="object-fit:cover;border-radius:6px;width:100%;height:100%;">');
                            } else {
                                const icon = isWpUser ? 'dashicons-admin-users' : (partyTypeIcons[item.party_type] || 'dashicons-businessman');
                                $avatar.html('<span class="dashicons ' + icon + '" style="font-size:16px;width:16px;height:16px;color:#64748b;"></span>');
                            }
                        }
                    }

                    if (isWpUser && !activeExplorerOptions.targetPreview && !activeExplorerOptions.format) {
                        // Sincronizar también con Usuario Vinculado del sistema en formulario de transacción
                        const $relUserId = $('#related_user_id');
                        const $relUserSearch = $('#related_user_search');
                        if ($relUserId.length && !$relUserId.val()) {
                            $relUserId.val(userId).trigger('change');
                            $relUserSearch.val(name + (login ? ' (@' + login + ')' : ''));
                            $('#aura-user-avatar').attr('src', logo);
                            $('#aura-user-name').html('<strong>' + name + '</strong> <span style="color:#64748b;font-size:12px;">(@' + login + ' · ' + email + ')</span>');
                            $('#aura-user-preview').css('display', 'flex');
                        }
                    }
                }

                if (typeof activeExplorerOptions.onSelect === 'function') {
                    activeExplorerOptions.onSelect(item);
                }
            }

            closeExplorer();
        });
    }

    function fetchCatalog(callback) {
        const ajaxUrl = (window.auraCounterpartiesData && auraCounterpartiesData.ajaxUrl) || (window.auraTransactionData && auraTransactionData.ajaxUrl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
        const nonce = (window.auraCounterpartiesData && auraCounterpartiesData.nonce) || (window.auraTransactionData && auraTransactionData.nonce) || '';

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_catalog_by_role',
                nonce: nonce
            },
            success: function(res) {
                if (res.success && res.data) {
                    catalogCache = res.data;
                    setupUserRolesFilterDOM(res.data.user_roles);
                    if (typeof callback === 'function') callback(res.data);
                }
            }
        });
    }

    function setupUserRolesFilterDOM(userRoles) {
        const $wrap = $('#aura-user-role-filters');
        if (!$wrap.length || !Array.isArray(userRoles)) return;

        let totalUsers = 0;
        userRoles.forEach(function(r) { totalUsers += (r.count || 0); });

        let pillsHtml = `<button type="button" class="aura-role-pill is-active" data-wp-role="all">Todos los Roles <span class="badge">${totalUsers}</span></button>`;
        userRoles.forEach(function(r) {
            pillsHtml += `<button type="button" class="aura-role-pill" data-wp-role="${r.slug}">${r.label} <span class="badge">${r.count}</span></button>`;
        });
        $wrap.html(pillsHtml);
    }

    function renderExplorerItems() {
        if (!catalogCache) return;

        const $modal = $('#aura-tp-explorer-modal');
        const isUserMode = activeExplorerOptions && (activeExplorerOptions.defaultTab === 'employee' || activeExplorerOptions.targetInput === '#related_user_search');
        const searchTerm = ($('#aura-tp-explorer-search').val() || '').toLowerCase().trim();

        // Actualizar badges de conteo por tipo
        if (catalogCache.counts_by_type) {
            $.each(catalogCache.counts_by_type, function(typeKey, count) {
                $modal.find('[data-type-count="' + typeKey + '"]').text(count);
            });
        }

        // Obtener todos los elementos base
        let allItems = catalogCache.catalog['all'] || [];

        // Filtrado
        let items = allItems.filter(function(item) {
            // Si está en modo usuario estricto
            if (isUserMode) {
                if (!item.is_wp_user) return false;
                if (currentFilterWpRole !== 'all' && item.user_role_slug !== currentFilterWpRole) return false;
                return true;
            }

            // Filtrado por Tipo de Entidad (Tabs)
            if (currentFilterType !== 'all') {
                if (currentFilterType === 'user') {
                    if (!item.is_wp_user) return false;
                    if (currentFilterWpRole !== 'all' && item.user_role_slug !== currentFilterWpRole) return false;
                } else {
                    if (item.is_wp_user) return false;
                    if (item.party_type !== currentFilterType) return false;
                }
            }

            // Filtrado por Rol Contable (Dropdown)
            if (currentFilterRole !== 'all') {
                if (item.accounting_role !== currentFilterRole) return false;
            }

            return true;
        });

        // Aplicar búsqueda textual
        let filtered = items.filter(function(item) {
            if (!searchTerm) return true;
            return (item.name && item.name.toLowerCase().includes(searchTerm)) ||
                   (item.commercial_name && item.commercial_name.toLowerCase().includes(searchTerm)) ||
                   (item.document_id && item.document_id.toLowerCase().includes(searchTerm)) ||
                   (item.email && item.email.toLowerCase().includes(searchTerm)) ||
                   (item.phone && item.phone.toLowerCase().includes(searchTerm)) ||
                   (item.login && item.login.toLowerCase().includes(searchTerm));
        });

        // Aplicar ordenamiento
        filtered.sort(function(a, b) {
            const nameA = (a.commercial_name || a.name || '').toLowerCase();
            const nameB = (b.commercial_name || b.name || '').toLowerCase();
            if (currentSort === 'name_desc') {
                return nameB.localeCompare(nameA);
            }
            if (currentSort === 'type') {
                const typeA = a.party_type_label || a.party_type || '';
                const typeB = b.party_type_label || b.party_type || '';
                if (typeA !== typeB) return typeA.localeCompare(typeB);
            }
            if (currentSort === 'role') {
                const roleA = a.accounting_role_label || a.accounting_role || '';
                const roleB = b.accounting_role_label || b.accounting_role || '';
                if (roleA !== roleB) return roleA.localeCompare(roleB);
            }
            return nameA.localeCompare(nameB);
        });

        const $grid = $('#aura-tp-explorer-grid').empty();
        $('#aura-tp-explorer-loading').hide();

        // Actualizar contador
        const totalShown = filtered.length;
        const totalBase = allItems.length;
        $('#aura-tp-results-count').text(`Mostrando ${totalShown} de ${totalBase} registros`);

        if (!filtered.length) {
            $('#aura-tp-explorer-empty').show();
            if (searchTerm) {
                $('#aura-tp-empty-hint').text(`No hay coincidencias para "${$('#aura-tp-explorer-search').val()}".`);
            } else {
                $('#aura-tp-empty-hint').text('No hay registros con los filtros seleccionados.');
            }
            return;
        }

        $('#aura-tp-explorer-empty').hide();

        filtered.forEach(function(item) {
            let avatarHtml = '';
            if (item.logo_url) {
                avatarHtml = `<img src="${item.logo_url}" width="42" height="42" style="border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid #cbd5e1;">`;
            } else {
                const icon = item.party_type_icon || partyTypeIcons[item.party_type] || 'dashicons-building';
                avatarHtml = `<div style="width:42px;height:42px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><span class="dashicons ${icon}" style="font-size:22px;width:22px;height:22px;color:#475569;"></span></div>`;
            }

            const docText = item.document_id ? (item.tax_id_type + ': ' + item.document_id) : (item.email || 'Sin documento');
            const badgeClass = item.badge_class || 'aura-pill--muted';
            const isUser = item.is_wp_user;
            const extraMeta = isUser 
                ? (item.user_role_label ? `<span class="aura-pill aura-pill--muted" style="font-size:10px;font-weight:600;">${item.user_role_label}</span>` : '')
                : (item.phone ? `<span style="font-size:11px;color:#64748b;">📞 ${item.phone}</span>` : '');

            const cardHtml = `
            <div class="aura-tp-explorer-card">
                <div style="display:flex;align-items:flex-start;gap:12px;">
                    ${avatarHtml}
                    <div style="min-width:0;flex:1;">
                        <strong style="display:block;font-size:13.5px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:-0.1px;" title="${item.display_name}">${item.display_name}</strong>
                        <span style="font-size:11.5px;color:#64748b;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">${docText}</span>
                        ${item.email && isUser ? `<span style="font-size:11px;color:#94a3b8;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">✉️ ${item.email}</span>` : ''}
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;padding-top:10px;border-top:1px solid #f1f5f9;gap:6px;">
                    <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                        <span class="aura-pill aura-pill--muted" style="font-size:10.5px;padding:2px 7px;font-weight:600;background:#f1f5f9;border:1px solid #e2e8f0;">${item.party_type_label}</span>
                        <span class="aura-pill ${badgeClass}" style="font-size:10.5px;padding:2px 8px;font-weight:700;">${item.accounting_role_label}</span>
                        ${extraMeta}
                    </div>
                    <button type="button" class="button button-small button-primary aura-tp-card-select-btn" 
                            data-id="${item.id}"
                            data-user-id="${item.user_id || 0}"
                            data-is-user="${item.is_wp_user ? '1' : '0'}"
                            data-role="${item.accounting_role}"
                            data-role-label="${item.accounting_role_label}"
                            data-party-type="${item.party_type}"
                            data-party-type-label="${item.party_type_label}"
                            data-name="${item.name}"
                            data-login="${item.login || ''}"
                            data-email="${item.email || ''}"
                            data-phone="${item.phone || ''}"
                            data-commercial="${item.commercial_name || ''}"
                            data-doc="${item.document_id || ''}"
                            data-tax="${item.tax_id_type || 'NIT'}"
                            data-logo="${item.logo_url || ''}"
                            style="font-size:11.5px;font-weight:600;height:28px;padding:0 12px;border-radius:8px;display:inline-flex;align-items:center;gap:4px;flex-shrink:0;">
                        <span class="dashicons dashicons-yes" style="font-size:13px;width:13px;height:13px;"></span> Seleccionar
                    </button>
                </div>
            </div>
            `;

            $grid.append(cardHtml);
        });
    }

    function openExplorer(options) {
        activeExplorerOptions = options || {};
        ensureExplorerModalDOM();

        const $modal = $('#aura-tp-explorer-modal');
        const isUserMode = activeExplorerOptions.defaultTab === 'employee' || activeExplorerOptions.targetInput === '#related_user_search';

        // Configuración contextual del modal
        if (isUserMode) {
            $('#aura-tp-modal-title').text('Directorio de Usuarios del Sistema');
            $('#aura-tp-modal-subtitle').text('Selecciona un usuario o colaborador registrado en el sistema Aura / WordPress.');
            $('#aura-tp-modal-icon-box').html('<span class="dashicons dashicons-admin-users" style="font-size:24px;width:24px;height:24px;"></span>').css({'background':'#f5f3ff','color':'#7c3aed','borderColor':'#ddd6fe'});
            $('#aura-tp-type-tabs').hide();
            $('#aura-tp-role-dropdown-wrap').hide();
            $('#aura-user-role-filters').css('display', 'flex');
            $('#aura-tp-footer-actions').html(`
                <a href="${adminUrl('users.php')}" target="_blank" style="font-size:12.5px;color:#7c3aed;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                    <span class="dashicons dashicons-admin-users" style="font-size:15px;width:15px;height:15px;"></span> Administrar Usuarios de WordPress
                </a>
            `);
        } else {
            $('#aura-tp-modal-title').text('Catálogo de Terceros y Entidades Comerciales');
            $('#aura-tp-modal-subtitle').text('Explora entidades por tipo (Empresas, Tiendas, Fundaciones, Personas) y rol contable (Proveedores, Clientes, Bancos, etc.) o registra uno nuevo.');
            $('#aura-tp-modal-icon-box').html('<span class="dashicons dashicons-groups" style="font-size:24px;width:24px;height:24px;"></span>').css({'background':'#eff6ff','color':'#2563eb','borderColor':'#bfdbfe'});
            $('#aura-tp-type-tabs').css('display', 'flex');
            $('#aura-tp-role-dropdown-wrap').show();
            $('#aura-user-role-filters').hide();
            $('#aura-tp-footer-actions').html(`
                <a href="${adminUrl('admin.php?page=aura-third-parties')}" target="_blank" style="font-size:12.5px;color:#2563eb;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                    <span class="dashicons dashicons-plus-alt" style="font-size:15px;width:15px;height:15px;"></span> Registrar Nuevo Tercero en el Directorio
                </a>
            `);
        }

        const defaultTab = activeExplorerOptions.defaultTab || 'all';
        $modal.find('.aura-type-tab-btn').removeClass('is-active');
        $modal.find('.aura-type-tab-btn[data-type="' + defaultTab + '"]').addClass('is-active');
        $modal.find('.aura-role-pill').removeClass('is-active').filter('[data-wp-role="all"]').addClass('is-active');
        $('#aura-tp-filter-role').val('all');

        currentFilterType = defaultTab;
        currentFilterRole = 'all';
        currentFilterWpRole = 'all';

        $('#aura-tp-explorer-search').val('');
        $('#aura-tp-search-clear').hide();

        // Si la pantalla o calendario está en Fullscreen, mover el modal adentro del contenedor fullscreen activo
        const $fsEl = document.fullscreenElement ? $(document.fullscreenElement) : ($('.aura-calendar-is-fullscreen').length ? $('.aura-calendar-is-fullscreen').first() : null);
        if ($fsEl && $fsEl.length) {
            if (!$modal.closest($fsEl).length) {
                $modal.appendTo($fsEl);
            }
        } else {
            // Asegurar el mismo stacking context insertando después del modal activo (por ejemplo, #modal-event-editor)
            const $activeModal = $('.aura-modal-overlay.active:visible, .aura-modal-overlay.is-active:visible, #modal-event-editor:visible').last();
            if ($activeModal.length && !$modal.closest($activeModal.parent()).length) {
                $modal.insertAfter($activeModal);
            }
        }

        $modal.css({
            'display': 'flex',
            'z-index': '1002000'
        });
        $('#aura-tp-explorer-loading').show();
        $('#aura-tp-explorer-grid').empty();
        $('#aura-tp-explorer-empty').hide();

        fetchCatalog(function() {
            renderExplorerItems();
            setTimeout(function() {
                $('#aura-tp-explorer-search').focus();
            }, 100);
        });
    }

    function closeExplorer() {
        $('#aura-tp-explorer-modal').hide();
    }

    // Auto-bind para botones con data-action="open-third-party-explorer" o .aura-btn-open-tp-explorer
    $(document).on('click', '[data-action="open-third-party-explorer"], .aura-btn-open-tp-explorer', function(e) {
        e.preventDefault();
        const targetInput = $(this).data('target-input') || '#recipient_payer';
        const targetHiddenId = $(this).data('target-hidden') || '#third_party_id';
        const targetPreview = $(this).data('target-preview') || '';
        const targetAvatar = $(this).data('target-avatar') || '';
        const targetText = $(this).data('target-text') || '';
        const targetBadge = $(this).data('target-badge') || '';
        const defaultTab = $(this).data('default-tab') || 'all';
        const format = $(this).data('format') || '';

        openExplorer({
            targetInput: targetInput,
            targetHiddenId: targetHiddenId,
            targetPreview: targetPreview,
            targetAvatar: targetAvatar,
            targetText: targetText,
            targetBadge: targetBadge,
            defaultTab: defaultTab,
            format: format,
            onSelect: function(item) {
                // Callback de selección
            }
        });
    });

    return {
        attach: attach,
        openExplorer: openExplorer,
        closeExplorer: closeExplorer
    };

})(jQuery);


