/**
 * Aura Business Suite — Inventory Settings JS (Aura Design System)
 * Gestión de categorías con filas expandibles (+), configuración, notificaciones y finanzas
 */
/* global jQuery, auraInvSettings, AuraUI */
(function ($) {
    'use strict';

    if (window.__auraInvSettingsBooted) {
        return;
    }
    window.__auraInvSettingsBooted = true;

    var cfg;

    function init() {
        if (typeof auraInvSettings === 'undefined') return;
        if (!$('.aura-inventory-settings').length) return;
        cfg = auraInvSettings;

        bindTabs();

        var savedTab = sessionStorage.getItem('aura_inv_active_tab');
        if (savedTab) {
            sessionStorage.removeItem('aura_inv_active_tab');
            $('.aura-inv-tab[data-tab="' + savedTab + '"]').trigger('click');
        }

        bindCategories();
        bindCategoryChildRows();
        bindEditCategoryModal();
        bindSettingsForms();
        bindIntervalTypeToggle();
    }

    function doAjax(action, data, success, error) {
        $.post(cfg.ajaxurl, $.extend({ action: action, nonce: cfg.nonce }, data))
            .done(function (res) {
                if (res && res.success) {
                    if (typeof success === 'function') success(res.data);
                } else {
                    var msg = res && res.data ? (res.data.message || res.data) : 'Error';
                    if (typeof error === 'function') error(msg);
                }
            })
            .fail(function () {
                if (typeof error === 'function') error('Error de conexión.');
            });
    }

    function bindTabs() {
        $(document).on('click', '.aura-inv-tab', function () {
            var tab = $(this).data('tab');
            $('.aura-inv-tab').removeClass('active');
            $(this).addClass('active');
            $('.aura-inv-tab-panel').hide();
            $('.aura-inv-tab-panel[data-panel="' + tab + '"]').show();
        });
    }

    // ─── Filas Expandibles (+) Categorías ────────────────────
    function bindCategoryChildRows() {
        $(document).on('click', '.aura-settings-cat-toggle', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $tr  = $btn.closest('tr');
            var termId = $tr.attr('data-term-id');
            var $existing = $('#js-cat-child-' + termId);

            if ($existing.length) {
                $existing.remove();
                $tr.removeClass('aura-row-expanded');
                $btn.removeClass('is-open').text('+').attr('aria-expanded', 'false');
                return;
            }

            var name        = $tr.attr('data-name') || '';
            var slug        = $tr.attr('data-slug') || '';
            var desc        = $tr.attr('data-description') || 'Sin descripción';
            var intervalStr = $tr.attr('data-interval-str') || 'Sin programa';
            var count       = $tr.attr('data-count') || '0';

            var card = '<tr id="js-cat-child-' + escAttr(termId) + '" class="aura-child-row"><td colspan="6">' +
                '<div class="aura-inv-child-card">' +
                '<div class="aura-inv-child-specs">' +
                '<div class="aura-inv-child-spec"><span class="aura-inv-child-spec-lbl">Categoría</span><strong class="aura-inv-child-spec-val">' + escHtml(name) + '</strong></div>' +
                '<div class="aura-inv-child-spec"><span class="aura-inv-child-spec-lbl">Slug</span><strong class="aura-inv-child-spec-val"><code>' + escHtml(slug) + '</code></strong></div>' +
                '<div class="aura-inv-child-spec"><span class="aura-inv-child-spec-lbl">Programa Mant.</span><strong class="aura-inv-child-spec-val">' + escHtml(intervalStr) + '</strong></div>' +
                '<div class="aura-inv-child-spec"><span class="aura-inv-child-spec-lbl">Equipos Asociados</span><strong class="aura-inv-child-spec-val">' + escHtml(count) + '</strong></div>' +
                '</div>' +
                '<div class="aura-inv-child-desc"><strong>Descripción:</strong> ' + escHtml(desc) + '</div>' +
                '</div></td></tr>';

            $tr.after(card).addClass('aura-row-expanded');
            $btn.addClass('is-open').text('−').attr('aria-expanded', 'true');
        });
    }

    // ─── Categorías CRUD ─────────────────────────────────────
    function bindCategories() {
        $(document).on('click', '#js-install-defaults', function () {
            var $btn = $(this).prop('disabled', true).text(cfg.txt.installing);
            hideNotice('#js-inv-settings-notice');

            doAjax('aura_inventory_settings_install_defaults', {},
                function (data) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Instalar categorías predeterminadas');
                    showNotice('#js-inv-settings-notice', 'success', data.message);
                    reloadCategoriesTable();
                },
                function (msg) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Instalar categorías predeterminadas');
                    showNotice('#js-inv-settings-notice', 'error', msg);
                }
            );
        });

        $(document).on('click', '.aura-inv-btn-delete-cat', function () {
            var termId = $(this).data('term-id');
            var name   = $(this).data('name');
            if (!window.confirm(cfg.txt.confirm_delete + '\n"' + name + '"')) return;

            var $row = $(this).closest('tr');
            var $btn = $(this).prop('disabled', true);

            doAjax('aura_inventory_settings_delete_category', { term_id: termId },
                function (data) {
                    showNotice('#js-inv-settings-notice', 'success', data.message);
                    $('#js-cat-child-' + termId).remove();
                    $row.fadeOut(250, function () { $(this).remove(); checkEmptyTable(); });
                },
                function (msg) {
                    $btn.prop('disabled', false);
                    showNotice('#js-inv-settings-notice', 'error', msg);
                }
            );
        });

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
                name:            $('#cat-name').val(),
                slug:            $('#cat-slug').val(),
                description:     $('#cat-description').val(),
                interval_type:   $('#cat-interval-type').val(),
                interval_months: $('#cat-interval-months').val(),
                interval_hours:  $('#cat-interval-hours').val(),
            };

            doAjax('aura_inventory_settings_add_category', data,
                function (cat) {
                    showNotice('#js-add-cat-msg', 'success', cat.message);
                    $('#js-form-add-category')[0].reset();
                    updateIntervalFields();
                    appendCategoryRow(cat);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus"></span> Agregar categoría');
                },
                function (msg) {
                    showNotice('#js-add-cat-msg', 'error', msg);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus"></span> Agregar categoría');
                }
            );
        });
    }

    function appendCategoryRow(cat) {
        var intervalStr = buildIntervalStr(cat);
        $('#js-cats-empty').remove();

        var tipHtml = '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header"><div class="aura-tip-info"><div class="aura-tip-title">' + escHtml(cat.name) + '</div></div></div>' +
            '<div class="aura-tip-card-body">' +
            '<div class="aura-tip-row"><span>Slug</span><strong>' + escHtml(cat.slug) + '</strong></div>' +
            '<div class="aura-tip-row"><span>Programa</span><strong>' + escHtml(intervalStr) + '</strong></div>' +
            '<div class="aura-tip-row"><span>Equipos</span><strong>' + (cat.count || 0) + '</strong></div>' +
            '</div></div>';

        var row = '<tr data-term-id="' + escAttr(cat.term_id) + '" data-name="' + escAttr(cat.name) + '" data-slug="' + escAttr(cat.slug) + '" data-description="' + escAttr(cat.description || '') + '" data-interval-str="' + escAttr(intervalStr) + '" data-count="' + escAttr(cat.count || 0) + '">' +
            '<td><button type="button" class="aura-inv-row-toggle aura-settings-cat-toggle" data-tooltip="Expandir detalles de la categoría" aria-expanded="false">+</button></td>' +
            '<td class="col-name"><strong class="aura-inv-cat-name-lbl" data-aura-tooltip="' + escAttr(tipHtml) + '">' + escHtml(cat.name) + '</strong></td>' +
            '<td class="col-slug aura-inv-col-secondary"><code>' + escHtml(cat.slug) + '</code></td>' +
            '<td class="col-interval"><span class="aura-inv-interval-badge aura-inv-interval-' + escAttr(cat.interval_type || 'none') + '">' + escHtml(intervalStr) + '</span></td>' +
            '<td class="col-count aura-inv-col-secondary"><span class="aura-inv-count-badge">' + (cat.count || 0) + '</span></td>' +
            '<td class="col-actions"><div class="aura-inv-action-group">' +
            '<button type="button" class="aura-btn-action-icon aura-btn-edit aura-inv-btn-edit-cat" ' +
            'data-term-id="' + escAttr(cat.term_id) + '" data-name="' + escAttr(cat.name) + '" data-slug="' + escAttr(cat.slug) + '" data-description="' + escAttr(cat.description || '') + '" ' +
            'data-interval-type="' + escAttr(cat.interval_type || 'none') + '" data-interval-months="' + escAttr(cat.interval_months || '') + '" data-interval-hours="' + escAttr(cat.interval_hours || '') + '" ' +
            'data-tooltip="Editar categoría"><span class="dashicons dashicons-edit"></span></button>' +
            '<button type="button" class="aura-btn-action-icon aura-btn-delete aura-inv-btn-delete-cat" data-term-id="' + escAttr(cat.term_id) + '" data-name="' + escAttr(cat.name) + '" data-tooltip="Eliminar categoría"><span class="dashicons dashicons-trash"></span></button>' +
            '</div></td></tr>';

        $('#js-categories-tbody').append(row);
    }

    function buildIntervalStr(cat) {
        switch (cat.interval_type) {
            case 'time':  return 'Cada ' + cat.interval_months + (parseInt(cat.interval_months, 10) === 1 ? ' mes' : ' meses');
            case 'hours': return 'Cada ' + cat.interval_hours + ' horas';
            case 'both':  return 'Cada ' + cat.interval_months + ' meses / ' + cat.interval_hours + ' h';
            default:      return 'Sin programa';
        }
    }

    function reloadCategoriesTable() {
        doAjax('aura_inventory_settings_get_categories', {}, function (cats) {
            var $tbody = $('#js-categories-tbody').empty();
            if (!cats || !cats.length) {
                checkEmptyTable();
                return;
            }
            cats.forEach(function (cat) { appendCategoryRow(cat); });
        });
    }

    function checkEmptyTable() {
        if ($('#js-categories-tbody tr').length === 0) {
            $('#js-categories-tbody').html(
                '<tr id="js-cats-empty"><td colspan="6" style="padding:24px;text-align:center;" class="aura-inv-muted">' +
                'No hay categorías. Haz clic en "Instalar categorías predeterminadas" para agregar las más comunes.</td></tr>'
            );
        }
    }

    // ─── Modal Editar Categoría (Item 26) ─────────────────────
    function bindEditCategoryModal() {
        var closeEditModal = function () {
            if (window.AuraUI && typeof window.AuraUI.closeModal === 'function') {
                window.AuraUI.closeModal('js-modal-edit-cat');
            } else {
                $('#js-modal-edit-cat').removeClass('active is-active').hide();
                $('body').css('overflow', '');
            }
        };

        $(document).on('click', '.aura-inv-btn-edit-cat', function () {
            var $btn = $(this);
            $('#edit-cat-term-id').val($btn.data('term-id'));
            $('#edit-cat-name').val($btn.data('name'));
            $('#edit-cat-slug').val($btn.data('slug'));
            $('#edit-cat-description').val($btn.data('description') || '');
            $('#edit-cat-interval-type').val($btn.data('interval-type') || 'none');
            $('#edit-cat-interval-months').val($btn.data('interval-months') || '');
            $('#edit-cat-interval-hours').val($btn.data('interval-hours') || '');
            hideNotice('#js-edit-cat-msg');
            updateEditIntervalFields();

            if (window.AuraUI && typeof window.AuraUI.openModal === 'function') {
                window.AuraUI.openModal('js-modal-edit-cat');
            } else {
                $('#js-modal-edit-cat').addClass('active is-active').css('display', 'flex');
            }
        });

        $(document).on('click', '#js-edit-cat-close, #js-edit-cat-close-btn', closeEditModal);
        bindTwoPhaseBackdropGuard('#js-modal-edit-cat', closeEditModal);
        $(document).on('keydown.auraSettingsCat', function (e) {
            if (e.key === 'Escape' && $('#js-modal-edit-cat').is(':visible')) {
                closeEditModal();
            }
        });

        $(document).on('change', '#edit-cat-interval-type', updateEditIntervalFields);

        $(document).on('submit', '#js-form-edit-category', function (e) {
            e.preventDefault();
            var name = $.trim($('#edit-cat-name').val());
            if (!name) {
                showNotice('#js-edit-cat-msg', 'error', cfg.txt.error_name);
                $('#edit-cat-name').focus();
                return;
            }

            var $btn = $('#js-btn-update-cat').prop('disabled', true).html('<span class="dashicons dashicons-update aura-spin"></span> ' + escHtml(cfg.txt.saving));
            hideNotice('#js-edit-cat-msg');

            var payload = {
                term_id:         $('#edit-cat-term-id').val(),
                name:            $('#edit-cat-name').val(),
                slug:            $('#edit-cat-slug').val(),
                description:     $('#edit-cat-description').val(),
                interval_type:   $('#edit-cat-interval-type').val(),
                interval_months: $('#edit-cat-interval-months').val(),
                interval_hours:  $('#edit-cat-interval-hours').val(),
            };

            doAjax('aura_inventory_settings_update_category', payload,
                function (cat) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar cambios');

                    var $row = $('tr[data-term-id="' + cat.term_id + '"]');
                    var intervalStr = buildIntervalStr(cat);
                    $row.attr('data-name', cat.name)
                        .attr('data-slug', cat.slug)
                        .attr('data-description', cat.description || '')
                        .attr('data-interval-str', intervalStr);

                    $row.find('.col-name .aura-inv-cat-name-lbl').text(cat.name);
                    $row.find('.col-slug code').text(cat.slug);
                    $row.find('.col-interval .aura-inv-interval-badge')
                        .attr('class', 'aura-inv-interval-badge aura-inv-interval-' + (cat.interval_type || 'none'))
                        .text(intervalStr);

                    var $editBtn = $row.find('.aura-inv-btn-edit-cat');
                    $editBtn
                        .data('name', cat.name).attr('data-name', escAttr(cat.name))
                        .data('slug', cat.slug).attr('data-slug', escAttr(cat.slug))
                        .data('description', cat.description || '').attr('data-description', escAttr(cat.description || ''))
                        .data('interval-type', cat.interval_type).attr('data-interval-type', cat.interval_type || 'none')
                        .data('interval-months', cat.interval_months || '').attr('data-interval-months', cat.interval_months || '')
                        .data('interval-hours', cat.interval_hours || '').attr('data-interval-hours', cat.interval_hours || '');

                    $('#js-cat-child-' + cat.term_id).remove();
                    $row.removeClass('aura-row-expanded').find('.aura-settings-cat-toggle').removeClass('is-open').text('+');

                    showNotice('#js-inv-settings-notice', 'success', cat.message);
                    closeEditModal();
                },
                function (msg) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar cambios');
                    showNotice('#js-edit-cat-msg', 'error', msg);
                }
            );
        });
    }

    function updateEditIntervalFields() {
        var type = $('#edit-cat-interval-type').val();
        $('#edit-cat-months-wrap').toggle(type === 'time' || type === 'both');
        $('#edit-cat-hours-wrap').toggle(type === 'hours' || type === 'both');
    }

    function bindIntervalTypeToggle() {
        $(document).on('change', '#cat-interval-type', updateIntervalFields);
        updateIntervalFields();
    }

    function updateIntervalFields() {
        var type = $('#cat-interval-type').val();
        $('#cat-months-wrap').toggle(type === 'time' || type === 'both');
        $('#cat-hours-wrap').toggle(type === 'hours' || type === 'both');
    }

    // ─── Settings Forms ─────────────────────────────────────
    function bindSettingsForms() {
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

        $(document).on('submit', '#js-form-notifications', function (e) {
            e.preventDefault();
            var $btn = $('#js-btn-save-notif').prop('disabled', true);
            hideNotice('#js-notif-msg');

            var data = {
                items_per_page:    $('#set-items-per-page').val() || 20,
                alert_days_before: $('#set-alert-days').val() || 7,
                loan_max_days:     $('#set-loan-max').val() || 30,
                show_retired:      $('#set-show-retired').is(':checked') ? 1 : 0,
                currency_symbol:   $('#set-currency').val() || '$',
                currency_position: $('#set-currency-pos').val() || 'before',
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

        $(document).on('click', '#js-btn-test-email', function () {
            var $btn = $(this).prop('disabled', true).html('<span class="dashicons dashicons-update aura-spin"></span> Enviando...');
            hideNotice('#js-notif-msg');

            $.post(cfg.ajaxurl, { action: 'aura_inventory_send_test_email', nonce: cfg.nonce })
                .done(function (resp) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> Enviar correo de prueba');
                    if (resp && resp.success) {
                        showNotice('#js-notif-msg', 'success', resp.data.message);
                    } else {
                        showNotice('#js-notif-msg', 'error', (resp && resp.data && resp.data.message) || 'Error desconocido.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> Enviar correo de prueba');
                    showNotice('#js-notif-msg', 'error', 'Error de red al enviar la prueba.');
                });
        });

        $(document).on('click', '#js-btn-gcal-resync', function () {
            var $btn = $(this).prop('disabled', true).html('<span class="dashicons dashicons-update aura-spin"></span> Sincronizando...');
            hideNotice('#js-gcal-msg');

            $.post(cfg.ajaxurl, { action: 'aura_inventory_gcal_resync_all', nonce: cfg.nonce })
                .done(function (resp) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Resincronizar todos los equipos con Google Calendar');
                    if (resp && resp.success) {
                        showNotice('#js-gcal-msg', 'success', resp.data.message);
                        sessionStorage.setItem('aura_inv_active_tab', 'gcal');
                        setTimeout(function () { window.location.reload(); }, 1500);
                    } else {
                        showNotice('#js-gcal-msg', 'error', (resp && resp.data && resp.data.message) || 'Error al resincronizar.');
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Resincronizar todos los equipos con Google Calendar');
                    showNotice('#js-gcal-msg', 'error', 'Error de red al resincronizar.');
                });
        });

        $(document).on('submit', '#js-form-finanzas', function (e) {
            e.preventDefault();
            var $btn = $('#js-btn-save-finanzas').prop('disabled', true);
            hideNotice('#js-finanzas-msg');

            var data = {
                finance_category_id:       $('#set-finance-cat').val(),
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

    // ─── Utilities ──────────────────────────────────────────
    function showNotice(selector, type, msg) {
        $(selector).removeClass('success error info').addClass(type).text(msg).show();
        if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
            window.AuraUI.showToast(msg, type === 'error' ? 'error' : 'success');
        }
    }

    function hideNotice(selector) {
        $(selector).hide().text('').removeClass('success error info');
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escAttr(str) {
        return escHtml(str);
    }

    function bindTwoPhaseBackdropGuard(selector, onClose) {
        var downOnBackdrop = false;
        $(document).off('mousedown.guard_' + selector + ' click.guard_' + selector)
            .on('mousedown.guard_' + selector, selector, function (e) {
                downOnBackdrop = (e.target === e.currentTarget);
            })
            .on('click.guard_' + selector, selector, function (e) {
                if (downOnBackdrop && e.target === e.currentTarget) {
                    onClose();
                }
                downOnBackdrop = false;
            });
    }

    $(function () { init(); });

}(jQuery));
