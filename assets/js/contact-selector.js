/**
 * Aura Contact Selector — Reusable JS Component
 *
 * @package AuraBusinessSuite
 * @subpackage Contacts
 */

(function($) {
    'use strict';

    var AuraContactSelector = {

        init: function($context) {
            var $containers = ($context && $context.length)
                ? $context.find('.aura-contact-selector-container')
                : $('.aura-contact-selector-container');

            $containers.each(function() {
                var $container = $(this);
                if ($container.data('aura-cs-initialized')) {
                    return;
                }
                AuraContactSelector.setupInstance($container);
            });
        },

        setupInstance: function($container) {
            $container.data('aura-cs-initialized', true);

            var $inputWrap    = $container.find('.aura-cs-input-wrap');
            var $searchInput  = $container.find('.aura-cs-search-input');
            var $spinner      = $container.find('.aura-cs-spinner');
            var $dropdown     = $container.find('.aura-cs-dropdown');
            var $resultsList  = $container.find('.aura-cs-results-list');
            var $hiddenId     = $container.find('.aura-cs-hidden-id');
            var $selectedCard = $container.find('.aura-cs-selected-card');
            var $searchBox    = $container.find('.aura-cs-search-box');
            var $btnClear     = $container.find('.aura-cs-btn-clear');

            var category      = $container.data('category') || 'all';
            var syncFieldsRaw = $container.data('sync-fields');
            var syncFields    = {};

            if (typeof syncFieldsRaw === 'string') {
                try {
                    syncFields = JSON.parse(syncFieldsRaw);
                } catch(e) {
                    syncFields = {};
                }
            } else if (typeof syncFieldsRaw === 'object' && syncFieldsRaw !== null) {
                syncFields = syncFieldsRaw;
            }

            var timer = null;
            var currentResults = [];
            var focusedIndex = -1;

            // Debounced Search Input
            $searchInput.on('input', function() {
                var q = $(this).val().trim();
                clearTimeout(timer);

                if (q.length === 0) {
                    $dropdown.hide();
                    $spinner.hide();
                    return;
                }

                $spinner.show();
                timer = setTimeout(function() {
                    AuraContactSelector.fetchContacts(q, category, function(err, items) {
                        $spinner.hide();
                        if (err) {
                            $resultsList.html('<li class="aura-cs-message">Error al buscar.</li>');
                            $dropdown.show();
                            return;
                        }

                        currentResults = items || [];
                        focusedIndex = -1;
                        AuraContactSelector.renderResults($resultsList, currentResults);
                        $dropdown.show();
                    });
                }, 250);
            });

            // Teclado (Up, Down, Enter, Esc)
            $searchInput.on('keydown', function(e) {
                var $items = $resultsList.find('.aura-cs-item');
                if (!$dropdown.is(':visible') || !$items.length) {
                    return;
                }

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    focusedIndex = Math.min(focusedIndex + 1, $items.length - 1);
                    $items.removeClass('aura-cs-item-focused');
                    if (focusedIndex >= 0) {
                        var $target = $items.eq(focusedIndex);
                        $target.addClass('aura-cs-item-focused');
                        $dropdown.scrollTop($target.position().top + $dropdown.scrollTop() - 40);
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    focusedIndex = Math.max(focusedIndex - 1, 0);
                    $items.removeClass('aura-cs-item-focused');
                    if (focusedIndex >= 0) {
                        var $target = $items.eq(focusedIndex);
                        $target.addClass('aura-cs-item-focused');
                        $dropdown.scrollTop($target.position().top + $dropdown.scrollTop() - 40);
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (focusedIndex >= 0 && currentResults[focusedIndex]) {
                        AuraContactSelector.selectContact($container, currentResults[focusedIndex], syncFields);
                    }
                } else if (e.key === 'Escape') {
                    $dropdown.hide();
                }
            });

            // Click en ítem de la lista
            $resultsList.on('click', '.aura-cs-item', function(e) {
                e.preventDefault();
                var index = $(this).data('index');
                if (currentResults[index]) {
                    AuraContactSelector.selectContact($container, currentResults[index], syncFields);
                }
            });

            // Cerrar dropdown al hacer click fuera
            $(document).on('click', function(e) {
                if (!$container.is(e.target) && $container.has(e.target).length === 0) {
                    $dropdown.hide();
                }
            });

            // Botón Limpiar Selección
            $btnClear.on('click', function(e) {
                e.preventDefault();
                AuraContactSelector.clearSelection($container, syncFields);
            });
        },

        fetchContacts: function(query, category, callback) {
            var cfg = window.auraContactSelectorCfg || {};
            var ajaxUrl = cfg.ajaxUrl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            var nonce   = cfg.nonce   || '';

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'aura_contacts_selector_search',
                    nonce: nonce,
                    q: query,
                    category: category,
                    limit: 15
                },
                success: function(res) {
                    if (res && res.success) {
                        callback(null, res.data);
                    } else {
                        callback(new Error(res ? res.data.message : 'Error desconocido'));
                    }
                },
                error: function(xhr, status, error) {
                    callback(error);
                }
            });
        },

        renderResults: function($list, items) {
            $list.empty();
            if (!items || !items.length) {
                $list.html('<li class="aura-cs-no-results">No se encontraron contactos.</li>');
                return;
            }

            $.each(items, function(idx, item) {
                var avatarHtml = '';
                if (item.avatar) {
                    avatarHtml = '<img src="' + $('<div>').text(item.avatar).html() + '" alt="">';
                } else {
                    var init = item.name ? item.name.charAt(0).toUpperCase() : '?';
                    avatarHtml = $('<div>').text(init).html();
                }

                var badgeStyle = 'color:' + (item.category_color || '#3b82f6') + '; background-color:' + (item.category_bg || '#eff6ff') + ';';

                var metaParts = [];
                if (item.email) {
                    metaParts.push('<span><span class="dashicons dashicons-email-alt" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> ' + $('<div>').text(item.email).html() + '</span>');
                }
                if (item.phone) {
                    metaParts.push('<span><span class="dashicons dashicons-phone" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> ' + $('<div>').text(item.phone).html() + '</span>');
                }
                if (item.organization) {
                    metaParts.push('<span><span class="dashicons dashicons-building" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> ' + $('<div>').text(item.organization).html() + '</span>');
                }

                var $li = $('<li>')
                    .addClass('aura-cs-item')
                    .attr('data-index', idx)
                    .html(
                        '<div class="aura-cs-item-avatar">' + avatarHtml + '</div>' +
                        '<div class="aura-cs-item-content">' +
                            '<div class="aura-cs-item-top">' +
                                '<strong class="aura-cs-item-name">' + $('<div>').text(item.name).html() + '</strong>' +
                                '<span class="aura-cs-badge" style="' + badgeStyle + '">' + $('<div>').text(item.category_label || '').html() + '</span>' +
                            '</div>' +
                            '<div class="aura-cs-item-meta">' + metaParts.join(' &bull; ') + '</div>' +
                        '</div>'
                    );

                $list.append($li);
            });
        },

        selectContact: function($container, contact, syncFields) {
            var $hiddenId     = $container.find('.aura-cs-hidden-id');
            var $selectedCard = $container.find('.aura-cs-selected-card');
            var $searchBox    = $container.find('.aura-cs-search-box');
            var $searchInput  = $container.find('.aura-cs-search-input');
            var $dropdown     = $container.find('.aura-cs-dropdown');

            // Fija valor
            $hiddenId.val(contact.id).trigger('change');
            $dropdown.hide();
            $searchInput.val('');

            // Actualiza Card
            var avatarWrap = $selectedCard.find('.aura-cs-avatar-wrap');
            if (contact.avatar) {
                avatarWrap.html('<img src="' + $('<div>').text(contact.avatar).html() + '" class="aura-cs-avatar" alt="">');
            } else {
                var init = contact.name ? contact.name.charAt(0).toUpperCase() : '?';
                avatarWrap.html('<div class="aura-cs-avatar-initials">' + $('<div>').text(init).html() + '</div>');
            }

            $selectedCard.find('.aura-cs-name').text(contact.name);

            var $badge = $selectedCard.find('.aura-cs-badge');
            $badge.text(contact.category_label || '')
                  .css({
                      'color': contact.category_color || '#3b82f6',
                      'background-color': contact.category_bg || '#eff6ff'
                  });

            var $metaRow = $selectedCard.find('.aura-cs-meta-row').empty();
            if (contact.email) {
                $metaRow.append('<span class="aura-cs-meta-item aura-cs-meta-email"><span class="dashicons dashicons-email-alt"></span> ' + $('<div>').text(contact.email).html() + '</span>');
            }
            if (contact.phone) {
                $metaRow.append('<span class="aura-cs-meta-item aura-cs-meta-phone"><span class="dashicons dashicons-phone"></span> ' + $('<div>').text(contact.phone).html() + '</span>');
            }
            if (contact.organization) {
                $metaRow.append('<span class="aura-cs-meta-item aura-cs-meta-org"><span class="dashicons dashicons-building"></span> ' + $('<div>').text(contact.organization).html() + '</span>');
            }

            $searchBox.hide();
            $selectedCard.fadeIn(150);

            // Sincronización automática de campos
            if (syncFields && typeof syncFields === 'object') {
                if (syncFields.name && $(syncFields.name).length) {
                    $(syncFields.name).val(contact.name).trigger('input').trigger('change');
                }
                if (syncFields.phone && $(syncFields.phone).length) {
                    $(syncFields.phone).val(contact.phone || '').trigger('input').trigger('change');
                }
                if (syncFields.email && $(syncFields.email).length) {
                    $(syncFields.email).val(contact.email || '').trigger('input').trigger('change');
                }
                if (syncFields.document && $(syncFields.document).length) {
                    $(syncFields.document).val(contact.id_number || '').trigger('input').trigger('change');
                }
                if (syncFields.user_id && $(syncFields.user_id).length && contact.wp_user_id > 0) {
                    $(syncFields.user_id).val(contact.wp_user_id).trigger('change');
                }
            }

            // Disparar eventos personalizados
            $container.trigger('aura:contact-selected', [contact]);
            $(document).trigger('aura:contact-selected', [$container, contact]);
        },

        clearSelection: function($container, syncFields) {
            var $hiddenId     = $container.find('.aura-cs-hidden-id');
            var $selectedCard = $container.find('.aura-cs-selected-card');
            var $searchBox    = $container.find('.aura-cs-search-box');
            var $searchInput  = $container.find('.aura-cs-search-input');

            $hiddenId.val('').trigger('change');
            $selectedCard.hide();
            $searchBox.fadeIn(150);
            $searchInput.val('').focus();

            // Disparar eventos personalizados
            $container.trigger('aura:contact-cleared');
            $(document).trigger('aura:contact-cleared', [$container]);
        },

        // API Externa: Asignar contacto programáticamente
        setContact: function(containerSelector, contactData) {
            var $container = $(containerSelector);
            if ($container.length && contactData) {
                var syncFieldsRaw = $container.data('sync-fields');
                var syncFields = (typeof syncFieldsRaw === 'object') ? syncFieldsRaw : {};
                AuraContactSelector.selectContact($container, contactData, syncFields);
            }
        },

        // API Externa: Limpiar selección programáticamente
        clear: function(containerSelector) {
            var $container = $(containerSelector);
            if ($container.length) {
                var syncFieldsRaw = $container.data('sync-fields');
                var syncFields = (typeof syncFieldsRaw === 'object') ? syncFieldsRaw : {};
                AuraContactSelector.clearSelection($container, syncFields);
            }
        }
    };

    window.AuraContactSelector = AuraContactSelector;

    $(document).ready(function() {
        AuraContactSelector.init();
    });

})(jQuery);
