/**
 * Aura Business Suite — Inventory Settings JS
 * Gestión de categorías y configuración del módulo de inventario
 */
/* global jQuery, auraInvSettings */
(function ($) {
    'use strict';

    var cfg;

    // ─── Init ────────────────────────────────────────────────
    function init() {
        if (typeof auraInvSettings === 'undefined') return;
        cfg = auraInvSettings;

        bindTabs();

        // Restaurar pestaña activa tras recarga (ej: después de guardar GCal)
        var savedTab = sessionStorage.getItem('aura_inv_active_tab');
        if (savedTab) {
            sessionStorage.removeItem('aura_inv_active_tab');
            $('.aura-inv-tab[data-tab="' + savedTab + '"]').trigger('click');
        }

        bindCategories();
        bindEditCategoryModal();
        bindSettingsForms();
        bindIntervalTypeToggle();
    }

    // ─── AJAX helper ────────────────────────────────────────
    function doAjax(action, data, success, error) {
        $.post(cfg.ajaxurl, $.extend({ action: action, nonce: cfg.nonce }, data))
            .done(function (res) {
                if (res.success) {
                    if (typeof success === 'function') success(res.data);
                } else {
                    var msg = res.data ? (res.data.message || res.data) : 'Error';
                    if (typeof error === 'function') error(msg);
                }
            })
            .fail(function () {
                if (typeof error === 'function') error('Error de conexión.');
            });
    }

    // ─── Tabs ────────────────────────────────────────────────
    function bindTabs() {
        $(document).on('click', '.aura-inv-tab', function () {
            var tab = $(this).data('tab');
            $('.aura-inv-tab').removeClass('active');
            $(this).addClass('active');
            $('.aura-inv-tab-panel').hide();
            $('.aura-inv-tab-panel[data-panel="' + tab + '"]').show();
        });
    }

    // ─── Categories ─────────────────────────────────────────

    function bindCategories() {
        // Instalar predeterminadas
        $(document).on('click', '#js-install-defaults', function () {
            var $btn = $(this).prop('disabled', true).text(cfg.txt.installing);
            hideNotice('#js-inv-settings-notice');

            doAjax('aura_inventory_settings_install_defaults', {},
                function (data) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-download"></span> Instalar categorías predeterminadas'
                    );
                    showNotice('#js-inv-settings-notice', 'success', data.message);
                    reloadCategoriesTable();
                },
                function (msg) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-download"></span> Instalar categorías predeterminadas'
                    );
                    showNotice('#js-inv-settings-notice', 'error', msg);
                }
            );
        });

        // Eliminar categoría
        $(document).on('click', '.aura-inv-btn-delete-cat', function () {
            var termId = $(this).data('term-id');
            var name   = $(this).data('name');

            if (!confirm(cfg.txt.confirm_delete + '\n"' + name + '"')) return;

            var $row = $(this).closest('tr');
            var $btn = $(this).prop('disabled', true);

            doAjax('aura_inventory_settings_delete_category', { term_id: termId },
                function (data) {
                    showNotice('#js-inv-settings-notice', 'success', data.message);
                    $row.fadeOut(300, function () { $(this).remove(); checkEmptyTable(); });
                },
                function (msg) {
                    $btn.prop('disabled', false);
                    showNotice('#js-inv-settings-notice', 'error', msg);
                }
            );
        });

        // Agregar categoría
        $(document).on('submit', '#js-form-add-category', function (e) {
            e.preventDefault();
            var name = $.trim($('#cat-name').val());
            if (!name) {
                showNotice('#js-add-cat-msg', 'error', cfg.txt.error_name);
                $('#cat-name').focus();
                return;
            }

            var $btn = $('#js-btn-add-cat').prop('disabled', true).text(cfg.txt.adding);
            hideNotice('#js-add-cat-msg');

            var data = {
                name:             $('#cat-name').val(),
                slug:             $('#cat-slug').val(),
                description:      $('#cat-description').val(),
                interval_type:    $('#cat-interval-type').val(),
                interval_months:  $('#cat-interval-months').val(),
                interval_hours:   $('#cat-interval-hours').val(),
            };

            doAjax('aura_inventory_settings_add_category', data,
                function (cat) {
                    showNotice('#js-add-cat-msg', 'success', cat.message);
                    $('#js-form-add-category')[0].reset();
                    updateIntervalFields();
                    appendCategoryRow(cat);
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-plus"></span> Agregar categoría'
                    );
                },
                function (msg) {
                    showNotice('#js-add-cat-msg', 'error', msg);
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-plus"></span> Agregar categoría'
                    );
                }
            );
        });
    }

    function appendCategoryRow(cat) {
        var intervalStr = buildIntervalStr(cat);

        // Quitar fila vacía si existe
        $('#js-cats-empty').remove();

        var row = '<tr data-term-id="' + cat.term_id + '">' +
            '<td class="col-name"><strong>' + escHtml(cat.name) + '</strong></td>' +
            '<td class="col-slug"><code>' + escHtml(cat.slug) + '</code></td>' +
            '<td class="col-interval">' +
                '<span class="aura-inv-interval-badge aura-inv-interval-' + escHtml(cat.interval_type || 'none') + '">' +
                    escHtml(intervalStr) +
                '</span>' +
            '</td>' +
            '<td class="col-count" style="text-align:center;"><span class="aura-inv-count-badge">' + (cat.count || 0) + '</span></td>' +
            '<td class="col-actions">' +
                '<div class="aura-inv-action-group">' +
                '<button type="button" class="button button-small aura-inv-btn-edit-cat"' +
                ' data-term-id="' + cat.term_id + '"' +
                ' data-name="' + escAttr(cat.name) + '"' +
                ' data-slug="' + escAttr(cat.slug) + '"' +
                ' data-description="' + escAttr(cat.description || '') + '"' +
                ' data-interval-type="' + escAttr(cat.interval_type || 'none') + '"' +
                ' data-interval-months="' + escAttr(cat.interval_months || '') + '"' +
                ' data-interval-hours="' + escAttr(cat.interval_hours || '') + '"' +
                ' title="Editar categoría">' +
                '<span class="dashicons dashicons-edit"></span>' +
                '</button>' +
                '<button type="button" class="button button-small aura-inv-btn-delete-cat"' +
                ' data-term-id="' + cat.term_id + '"' +
                ' data-name="' + escAttr(cat.name) + '"' +
                ' title="Eliminar categoría">' +
                '<span class="dashicons dashicons-trash"></span>' +
                '</button>' +
                '</div>' +
            '</td>' +
            '</tr>';

        $('#js-categories-tbody').append(row);
    }

    function buildIntervalStr(cat) {
        switch (cat.interval_type) {
            case 'time':
                return 'Cada ' + cat.interval_months + (parseInt(cat.interval_months) === 1 ? ' mes' : ' meses');
            case 'hours':
                return 'Cada ' + cat.interval_hours + ' horas';
            case 'both':
                return 'Cada ' + cat.interval_months + ' meses / ' + cat.interval_hours + ' h';
            default:
                return 'Sin programa';
        }
    }

    function reloadCategoriesTable() {
        doAjax('aura_inventory_settings_get_categories', {},
            function (cats) {
                var $tbody = $('#js-categories-tbody').empty();
                if (!cats.length) {
                    $tbody.html('<tr id="js-cats-empty"><td colspan="5" style="padding:20px;text-align:center;color:#646970;">' +
                        'No hay categorías. Haz clic en "Instalar categorías predeterminadas" para agregar las más comunes.' +
                        '</td></tr>');
                    return;
                }
                $.each(cats, function (i, cat) {
                    appendCategoryRow(cat);
                });
            }
        );
    }

    function checkEmptyTable() {
        if ($('#js-categories-tbody tr').length === 0) {
            $('#js-categories-tbody').html(
                '<tr id="js-cats-empty"><td colspan="5" style="padding:20px;text-align:center;color:#646970;">' +
                'No hay categorías. Haz clic en "Instalar categorías predeterminadas" para agregar las más comunes.' +
                '</td></tr>'
            );
        }
    }
    // ─── Edit category modal ──────────────────────────────────────
    function bindEditCategoryModal() {
        // Abrir modal al hacer clic en editar
        $(document).on('click', '.aura-inv-btn-edit-cat', function () {
            var $btn = $(this);
            var data = {
                term_id:          $btn.data('term-id'),
                name:             $btn.data('name'),
                slug:             $btn.data('slug'),
                description:      $btn.data('description') || '',
                interval_type:    $btn.data('interval-type') || 'none',
                interval_months:  $btn.data('interval-months') || '',
                interval_hours:   $btn.data('interval-hours') || '',
            };

            // Poblar modal
            $('#edit-cat-term-id').val(data.term_id);
            $('#edit-cat-name').val(data.name);
            $('#edit-cat-slug').val(data.slug);
            $('#edit-cat-description').val(data.description);
            $('#edit-cat-interval-type').val(data.interval_type);
            $('#edit-cat-interval-months').val(data.interval_months);
            $('#edit-cat-interval-hours').val(data.interval_hours);
            hideNotice('#js-edit-cat-msg');
            updateEditIntervalFields();

            $('#js-modal-edit-cat').fadeIn(150);
        });

        // Cerrar modal
        $(document).on('click', '#js-edit-cat-close, #js-edit-cat-close-btn', function () {
            $('#js-modal-edit-cat').fadeOut(150);
        });
        $(document).on('click', '#js-modal-edit-cat', function (e) {
            if ($(e.target).is('#js-modal-edit-cat')) {
                $('#js-modal-edit-cat').fadeOut(150);
            }
        });

        // Cambio de intervalo en modal de edición
        $(document).on('change', '#edit-cat-interval-type', updateEditIntervalFields);

        // Enviar formulario de edición
        $(document).on('submit', '#js-form-edit-category', function (e) {
            e.preventDefault();

            var name = $.trim($('#edit-cat-name').val());
            if (!name) {
                showNotice('#js-edit-cat-msg', 'error', cfg.txt.error_name);
                $('#edit-cat-name').focus();
                return;
            }

            var $btn = $('#js-btn-update-cat').prop('disabled', true).html(
                '<span class="dashicons dashicons-update aura-spin"></span> ' + cfg.txt.saving
            );
            hideNotice('#js-edit-cat-msg');

            var payload = {
                term_id:          $('#edit-cat-term-id').val(),
                name:             $('#edit-cat-name').val(),
                slug:             $('#edit-cat-slug').val(),
                description:      $('#edit-cat-description').val(),
                interval_type:    $('#edit-cat-interval-type').val(),
                interval_months:  $('#edit-cat-interval-months').val(),
                interval_hours:   $('#edit-cat-interval-hours').val(),
            };

            doAjax('aura_inventory_settings_update_category', payload,
                function (cat) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-saved"></span> Guardar cambios'
                    );
                    showNotice('#js-edit-cat-msg', 'success', cat.message);

                    // Actualizar fila en tabla
                    var $row = $('[data-term-id="' + cat.term_id + '"]');
                    $row.find('.col-name strong').text(cat.name);
                    $row.find('.col-slug code').text(cat.slug);
                    var intervalStr = buildIntervalStr(cat);
                    $row.find('.col-interval .aura-inv-interval-badge')
                        .attr('class', 'aura-inv-interval-badge aura-inv-interval-' + (cat.interval_type || 'none'))
                        .text(intervalStr);

                    // Actualizar data-attributes del botón editar
                    var $editBtn = $row.find('.aura-inv-btn-edit-cat');
                    $editBtn
                        .data('name',             cat.name)
                        .data('slug',             cat.slug)
                        .data('interval-type',    cat.interval_type)
                        .data('interval-months',  cat.interval_months || '')
                        .data('interval-hours',   cat.interval_hours  || '');
                    $editBtn.attr('data-name',             escAttr(cat.name));
                    $editBtn.attr('data-slug',             escAttr(cat.slug));
                    $editBtn.attr('data-interval-type',    cat.interval_type || 'none');
                    $editBtn.attr('data-interval-months',  cat.interval_months || '');
                    $editBtn.attr('data-interval-hours',   cat.interval_hours  || '');

                    showNotice('#js-inv-settings-notice', 'success', cat.message);
                    setTimeout(function () { $('#js-modal-edit-cat').fadeOut(200); }, 900);
                },
                function (msg) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-saved"></span> Guardar cambios'
                    );
                    showNotice('#js-edit-cat-msg', 'error', msg);
                }
            );
        });
    }

    function updateEditIntervalFields() {
        var type = $('#edit-cat-interval-type').val();
        $('#edit-cat-months-wrap').toggle(type === 'time' || type === 'both');
        $('#edit-cat-hours-wrap').toggle(type  === 'hours' || type === 'both');
    }
    // ─── Interval type toggle ────────────────────────────────
    function bindIntervalTypeToggle() {
        $(document).on('change', '#cat-interval-type', updateIntervalFields);
        updateIntervalFields();
    }

    function updateIntervalFields() {
        var type = $('#cat-interval-type').val();
        $('#cat-months-wrap').toggle(type === 'time' || type === 'both');
        $('#cat-hours-wrap').toggle(type  === 'hours' || type === 'both');
    }

    // ─── Settings forms ─────────────────────────────────────
    function bindSettingsForms() {
        // General settings
        $(document).on('submit', '#js-form-settings', function (e) {
            e.preventDefault();
            var $btn = $('#js-btn-save-general').prop('disabled', true);
            hideNotice('#js-general-msg');

            var data = {
                items_per_page:    $('#set-items-per-page').val(),
                alert_days_before: $('#set-alert-days').val(),
                loan_max_days:     $('#set-loan-max').val(),
                show_retired:      $('#set-show-retired').is(':checked') ? 1 : 0,
                currency_symbol:   $('#set-currency').val(),
                currency_position: $('#set-currency-pos').val(),
                email_alerts:      $('#set-email-alerts').is(':checked') ? 1 : 0,
                email_extra:       $('#set-email-extra').val(),
            };

            doAjax('aura_inventory_settings_save_settings', data,
                function (res) {
                    $btn.prop('disabled', false);
                    showNotice('#js-general-msg', 'success', res.message);
                },
                function (msg) {
                    $btn.prop('disabled', false);
                    showNotice('#js-general-msg', 'error', msg);
                }
            );
        });

        // Notifications settings
        $(document).on('submit', '#js-form-notifications', function (e) {
            e.preventDefault();
            var $btn = $('#js-btn-save-notif').prop('disabled', true);
            hideNotice('#js-notif-msg');

            var data = {
                items_per_page:    $('#set-items-per-page').val() || 20,
                alert_days_before: $('#set-alert-days').val() || 7,
                loan_max_days:     $('#set-loan-max').val() || 30,
                show_retired:      0,
                currency_symbol:   '$',
                currency_position: 'before',
                email_alerts:      $('#set-email-alerts').is(':checked') ? 1 : 0,
                email_extra:       $('#set-email-extra').val(),
            };

            doAjax('aura_inventory_settings_save_settings', data,
                function (res) {
                    $btn.prop('disabled', false);
                    showNotice('#js-notif-msg', 'success', res.message);
                },
                function (msg) {
                    $btn.prop('disabled', false);
                    showNotice('#js-notif-msg', 'error', msg);
                }
            );
        });

        // ── Notificaciones — Enviar correo de prueba ──────────
        $(document).on('click', '#js-btn-test-email', function () {
            var $btn = $(this).prop('disabled', true).html(
                '<span class="dashicons dashicons-update" style="animation:rotation 1s linear infinite;"></span> Enviando...'
            );
            hideNotice('#js-notif-msg');

            $.post(cfg.ajaxurl, { action: 'aura_inventory_send_test_email', nonce: cfg.nonce })
                .done(function (resp) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-email-alt"></span> Enviar correo de prueba'
                    );
                    if (resp.success) {
                        showNotice('#js-notif-msg', 'success', resp.data.message);
                    } else {
                        showNotice('#js-notif-msg', 'error', (resp.data && resp.data.message) || 'Error desconocido.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-email-alt"></span> Enviar correo de prueba'
                    );
                    showNotice('#js-notif-msg', 'error', 'Error de red al enviar la prueba.');
                });
        });

        // ── WhatsApp — Visibilidad de campos según proveedor ──
        function updateWAProviderFields() {
            var provider = $('#set-wa-provider').val();
            $('.js-wa-desc-callmebot, .js-wa-desc-twilio, .js-wa-desc-meta').hide();
            $('.js-wa-desc-' + provider).show();
            $('.js-wa-desc-from-callmebot, .js-wa-desc-from-twilio, .js-wa-desc-from-meta').hide();
            $('.js-wa-desc-from-' + provider).show();
            $('.js-wa-row-twilio').toggle(provider === 'twilio');
            $('.js-wa-row-meta').toggle(provider === 'meta');
        }
        updateWAProviderFields();
        $(document).on('change', '#set-wa-provider', updateWAProviderFields);

        // Botón "Probar WhatsApp": habilitar/mostrar panel de prueba
        $(document).on('click', '#js-btn-test-whatsapp', function () {
            $('#js-wa-test-panel').slideToggle(200);
        });
        // Habilitar botón probar si WhatsApp está activo
        $(document).on('change', '#set-wa-enabled', function () {
            $('#js-btn-test-whatsapp').prop('disabled', !$(this).is(':checked'));
        });
        if ($('#set-wa-enabled').is(':checked')) {
            $('#js-btn-test-whatsapp').prop('disabled', false);
        }

        // ── WhatsApp — Guardar configuración ─────────────────
        $(document).on('submit', '#js-form-whatsapp', function (e) {
            e.preventDefault();
            var $btn = $('#js-btn-save-whatsapp').prop('disabled', true);
            hideNotice('#js-whatsapp-msg');

            var data = {
                whatsapp_enabled:       $('#set-wa-enabled').is(':checked') ? 1 : 0,
                whatsapp_provider:      $('#set-wa-provider').val(),
                whatsapp_api_token:     $('#set-wa-token').val(),
                whatsapp_from:          $('#set-wa-from').val().trim(),
                whatsapp_twilio_sid:    $('#set-wa-twilio-sid').val().trim(),
                whatsapp_meta_phone_id: $('#set-wa-meta-phone-id').val().trim(),
                whatsapp_signature:     $('#set-wa-signature').val().trim(),
            };

            $.post(cfg.ajaxurl, $.extend({ action: 'aura_inventory_save_whatsapp', nonce: cfg.nonce }, data))
                .done(function (resp) {
                    $btn.prop('disabled', false);
                    if (resp.success) {
                        showNotice('#js-whatsapp-msg', 'success', resp.data.message);
                        $('#js-btn-test-whatsapp').prop('disabled', !$('#set-wa-enabled').is(':checked'));
                    } else {
                        showNotice('#js-whatsapp-msg', 'error', (resp.data && resp.data.message) || 'Error desconocido.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false);
                    showNotice('#js-whatsapp-msg', 'error', 'Error de red al guardar la configuración.');
                });
        });

        // ── WhatsApp — Enviar mensaje de prueba ───────────────
        $(document).on('click', '#js-btn-send-wa-test', function () {
            var phone = $('#js-wa-test-phone').val().trim();
            if (!phone) {
                showNotice('#js-whatsapp-msg', 'error', 'Ingresa un número de teléfono de prueba con código de país, ej. +521234567890');
                return;
            }
            var $btn = $(this).prop('disabled', true).html(
                '<span class="dashicons dashicons-update" style="animation:rotation 1s linear infinite;"></span> Enviando...'
            );
            hideNotice('#js-whatsapp-msg');

            $.post(cfg.ajaxurl, { action: 'aura_inventory_test_whatsapp', nonce: cfg.nonce, phone: phone })
                .done(function (resp) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-arrow-right-alt"></span> Enviar mensaje de prueba'
                    );
                    if (resp.success) {
                        showNotice('#js-whatsapp-msg', 'success', resp.data.message);
                    } else {
                        showNotice('#js-whatsapp-msg', 'error', (resp.data && resp.data.message) || 'Error desconocido.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-arrow-right-alt"></span> Enviar mensaje de prueba'
                    );
                    showNotice('#js-whatsapp-msg', 'error', 'Error de red al enviar la prueba.');
                });
        });

        // ── Google Calendar — Guardar configuración ──────────
        $(document).on('click', '#js-btn-gcal-save', function (e) {
            e.preventDefault();
            var $btn = $(this).prop('disabled', true);
            hideNotice('#js-gcal-msg');

            var data = {
                gcal_enabled:          $('#set-gcal-enabled').is(':checked') ? 1 : 0,
                gcal_share_email:      $('#set-gcal-email').val().trim(),
                service_account_json:  $('#set-gcal-json').val().trim(),
                reminder_days:         $('#set-gcal-days').val().trim(),
            };

            $.post(cfg.ajaxurl, $.extend({ action: 'aura_inventory_gcal_save', nonce: cfg.nonce }, data))
                .done(function (resp) {
                    if (resp.success) {
                        showNotice('#js-gcal-msg', 'success', resp.data.message + ' Recargando...');
                        // Guardar pestaña activa y recargar para mostrar el estado actualizado
                        sessionStorage.setItem('aura_inv_active_tab', 'gcal');
                        setTimeout(function () { window.location.reload(); }, 1500);
                    } else {
                        $btn.prop('disabled', false);
                        showNotice('#js-gcal-msg', 'error', (resp.data && resp.data.message) || 'Error desconocido.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false);
                    showNotice('#js-gcal-msg', 'error', 'Error de red al guardar la configuraci\u00f3n.');
                });
        });

        // ── Google Calendar — Probar conexión ────────────────
        $(document).on('click', '#js-btn-gcal-test', function () {
            var $btn = $(this).prop('disabled', true).text('Probando...');
            hideNotice('#js-gcal-msg');

            var data = {
                action:               'aura_inventory_gcal_test',
                nonce:                cfg.nonce,
                service_account_json: $('#set-gcal-json').val().trim(),
            };

            $.post(cfg.ajaxurl, data)
                .done(function (resp) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-networking"></span> Probar conexi\u00f3n'
                    );
                    if (resp.success) {
                        var msg = resp.data.message;
                        if (resp.data.calendar_id) {
                            msg += '<br><br><strong>📋 ID del calendario (para agregar manualmente):</strong><br>'
                                + '<code style="user-select:all;background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:11px;word-break:break-all;">'
                                + resp.data.calendar_id + '</code>'
                                + '<br><small style="color:#555;">Si no recibes la invitación por email, ve a <strong>Google Calendar → Otros calendarios → + → Suscribirse a un calendario</strong> y pega este ID.</small>';
                        }
                        showNotice('#js-gcal-msg', 'success', msg);
                    } else {
                        showNotice('#js-gcal-msg', 'error', (resp.data && resp.data.message) || 'Error desconocido.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-networking"></span> Probar conexi\u00f3n'
                    );
                    showNotice('#js-gcal-msg', 'error', 'Error de red al probar la conexi\u00f3n.');
                });
        });

        // ── Google Calendar — Resincronizar todos los equipos ──
        $(document).on('click', '#js-btn-gcal-resync', function () {
            var $btn = $(this).prop('disabled', true).html(
                '<span class="dashicons dashicons-update" style="animation:rotation 1s linear infinite;"></span> Sincronizando...'
            );
            hideNotice('#js-gcal-msg');

            $.post(cfg.ajaxurl, { action: 'aura_inventory_gcal_resync_all', nonce: cfg.nonce })
                .done(function (resp) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-update"></span> Resincronizar todos los equipos'
                    );
                    if (resp.success) {
                        showNotice('#js-gcal-msg', 'success', resp.data.message);
                        // Recargar la página para mostrar el estado de último sync
                        setTimeout(function () { location.reload(); }, 2000);
                    } else {
                        showNotice('#js-gcal-msg', 'error', (resp.data && resp.data.message) || 'Error al resincronizar.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-update"></span> Resincronizar todos los equipos'
                    );
                    showNotice('#js-gcal-msg', 'error', 'Error de red al resincronizar.');
                });
        });

        // ── Finanzas — Guardar configuración ─────────────────
        $(document).on('submit', '#js-form-finanzas', function (e) {
            e.preventDefault();
            var $btn = $('#js-btn-save-finanzas').prop('disabled', true);
            hideNotice('#js-finanzas-msg');

            var data = {
                finance_category_id:      $('#set-finance-cat').val(),
                auto_approve_transactions: $('#set-auto-approve').val(),
            };

            doAjax('aura_inventory_settings_save_finance_settings', data,
                function (res) {
                    $btn.prop('disabled', false);
                    showNotice('#js-finanzas-msg', 'success', res.message);
                },
                function (msg) {
                    $btn.prop('disabled', false);
                    showNotice('#js-finanzas-msg', 'error', msg);
                }
            );
        });
    }

    // ─── Utilities ───────────────────────────────────────────
    function showNotice(selector, type, msg) {
        $(selector).removeClass('success error info').addClass(type).text(msg).show();
    }

    function hideNotice(selector) {
        $(selector).hide().text('').removeClass('success error info');
    }

    function escHtml(str) {
        return $('<div>').text(String(str || '')).html();
    }

    function escAttr(str) {
        return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ─── Boot ────────────────────────────────────────────────
    $(function () { init(); });

}(jQuery));
