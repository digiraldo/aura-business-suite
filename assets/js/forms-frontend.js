/**
 * forms-frontend.js — Módulo de Formularios y Encuestas
 * Validación client-side y envío AJAX del formulario público.
 *
 * Depende de:
 *  - jQuery (incluido en WP)
 *  - auraFormsFrontend (wp_localize_script): { ajaxUrl, formNonce, formId, i18n }
 *
 * @package AuraBusinessSuite
 */
/* global jQuery, auraFormsFrontend */
(function ($) {
    'use strict';

    // ─────────────────────────────────────────────────────────────
    // OBJETO PRINCIPAL
    // ─────────────────────────────────────────────────────────────

    window.AuraFormsFrontend = {

        init: function () {
            this.bindScaleButtons();
            this.bindBirthdateAge();
            this.bindTermsToggle();
            this.bindOtherOption();
            this.bindFileValidation();
            this.bindFormSubmit();
        },

        // ── Botones de escala NPS/Likert ──────────────────────────
        bindScaleButtons: function () {
            $(document).on('click', '.aura-scale-btn', function () {
                var $btn    = $(this);
                var $wrap   = $btn.closest('.aura-field-scale');
                var $hidden = $wrap.find('input[type="hidden"]');
                var val     = $btn.data('value');

                // Toggle: si ya está seleccionado, deseleccionar
                if ( $btn.hasClass('is-selected') ) {
                    $btn.removeClass('is-selected');
                    $hidden.val('');
                } else {
                    $wrap.find('.aura-scale-btn').removeClass('is-selected');
                    $btn.addClass('is-selected');
                    $hidden.val(val);
                }
            });
        },

        // ── Cálculo de edad en vivo para birthdate ────────────────
        bindBirthdateAge: function () {
            $(document).on('change', '.aura-birthdate-input', function () {
                var $input   = $(this);
                var fieldName = $input.attr('name');
                var $ageEl   = $('#' + fieldName + '_age_display');
                var dateVal  = $input.val();

                if ( ! dateVal || ! $ageEl.length ) return;

                var birth = new Date(dateVal);
                var today = new Date();
                var age   = today.getFullYear() - birth.getFullYear();
                var m     = today.getMonth() - birth.getMonth();
                if ( m < 0 || (m === 0 && today.getDate() < birth.getDate()) ) {
                    age--;
                }

                if ( age >= 0 && age <= 130 ) {
                    $ageEl.text(age + ' ' + (window.auraFormsFrontend && auraFormsFrontend.i18n
                        ? '' : 'años'));
                } else {
                    $ageEl.text('');
                }
            });
        },

        // ── Mensaje de desacuerdo en terms ────────────────────────
        bindTermsToggle: function () {
            $(document).on('change', '.aura-terms-radio', function () {
                var $radio  = $(this);
                var $wrap   = $radio.closest('.aura-field-terms');
                var $msgEl  = $wrap.find('.aura-terms-disagree-msg');
                var isDisag = $radio.val() === 'disagree';

                if ( $msgEl.length ) {
                    if ( isDisag ) {
                        $msgEl.slideDown(200);
                    } else {
                        $msgEl.slideUp(200);
                    }
                }
            });
        },

        // ── Opción "Otro" en radio y select ───────────────────────
        bindOtherOption: function () {
            // Radio: habilitar / deshabilitar input de texto
            $(document).on('change', '.aura-other-radio', function () {
                var $wrap      = $(this).closest('.aura-field-radio');
                var $textInput = $wrap.find('.aura-other-text-input');
                $textInput.prop('disabled', false).trigger('focus');
            });

            $(document).on('change', '.aura-radio-group input[type="radio"]:not(.aura-other-radio)', function () {
                var $wrap      = $(this).closest('.aura-field-radio');
                var $textInput = $wrap.find('.aura-other-text-input');
                $textInput.prop('disabled', true).val('');
            });

            // Select: mostrar / ocultar campo de texto
            $(document).on('change', '.aura-field-select .aura-input[type!="text"]', function () {
                var $select    = $(this);
                var $wrap      = $select.closest('.aura-field-select');
                var $textInput = $wrap.find('.aura-other-text-input');
                if ( ! $textInput.length ) return;

                if ( $select.val() === '__other__' || (Array.isArray($select.val()) && $select.val().indexOf('__other__') !== -1) ) {
                    $textInput.show().trigger('focus');
                } else {
                    $textInput.hide().val('');
                }
            });
        },

        // ── Validación de archivo en tiempo real ──────────────────
        bindFileValidation: function () {
            $(document).on('change', '.aura-file-input', function () {
                var $input  = $(this);
                var maxKb   = parseInt( $input.data('max-kb') || 5120, 10 );
                var allowed = ($input.data('allowed-ext') || '').toLowerCase().split(',').map(function(e){ return e.trim(); });
                var $err    = $input.siblings('.aura-input-error');
                if ( ! $err.length ) {
                    $err = $('<span class="aura-input-error"></span>').insertAfter($input);
                }

                if ( ! this.files || ! this.files[0] ) return;
                var file  = this.files[0];
                var ext   = file.name.split('.').pop().toLowerCase();
                var sizeKb = Math.ceil(file.size / 1024);
                var err   = '';
                var i18n  = window.auraFormsFrontend ? auraFormsFrontend.i18n : {};

                if ( allowed.length && ! allowed.includes(ext) ) {
                    err = i18n.fileExt || 'Tipo de archivo no permitido.';
                } else if ( sizeKb > maxKb ) {
                    err = i18n.fileSize || 'El archivo supera el tamaño máximo permitido.';
                }

                $err.text(err);
                $input.toggleClass('aura-input--error', !!err);
            });
        },

        // ── Envío del formulario vía AJAX ─────────────────────────
        bindFormSubmit: function () {
            $(document).on('submit', '.aura-form', function (e) {
                e.preventDefault();

                var $form    = $(this);
                var $btn     = $form.find('.aura-form-submit-btn');
                var $msgs    = $form.closest('.aura-form-wrap').find('.aura-form-messages');
                var i18n     = window.auraFormsFrontend ? auraFormsFrontend.i18n : {};

                // Limpiar estado anterior
                $msgs.empty().hide();
                $form.find('.aura-input--error').removeClass('aura-input--error');
                $form.find('.aura-input-error').remove();

                // ── Validación client-side ────────────────────────
                var valid = AuraFormsFrontend.validateForm($form, i18n);
                if ( ! valid ) return;

                // ── Deshabilitar botón y mostrar estado enviando ──
                var origText = $btn.text();
                $btn.prop('disabled', true).text(i18n.sending || 'Enviando…');

                // ── Construir FormData (soporta archivos) ─────────
                var formData = new FormData( $form[0] );

                // Asegurar nonce y action
                formData.set('action',   'aura_form_submit');
                formData.set('form_id',  $form.data('form-id'));
                // El nonce viene ya en el campo hidden del formulario

                $.ajax({
                    url:         window.auraFormsFrontend ? auraFormsFrontend.ajaxUrl : '/wp-admin/admin-ajax.php',
                    type:        'POST',
                    data:        formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if ( response.success ) {
                            AuraFormsFrontend.handleSuccess($form, $msgs, response.data);
                        } else {
                            var msg = (response.data && response.data.message)
                                ? response.data.message
                                : (i18n.serverError || 'Error del servidor. Intenta nuevamente.');
                            AuraFormsFrontend.showError($msgs, msg);
                            $btn.prop('disabled', false).text(origText);
                        }
                    },
                    error: function () {
                        AuraFormsFrontend.showError($msgs, i18n.serverError || 'Error del servidor. Intenta nuevamente.');
                        $btn.prop('disabled', false).text(origText);
                    }
                });
            });
        },

        // ── Validación client-side completa ───────────────────────
        validateForm: function ($form, i18n) {
            var valid = true;

            // Campos required genéricos
            $form.find('[required]').each(function () {
                var $el  = $(this);
                var val  = $el.val();
                var type = $el.attr('type') || $el.prop('tagName').toLowerCase();

                if ( type === 'radio' ) return; // los radios se validan por grupo
                if ( type === 'checkbox' ) {
                    if ( ! $el.is(':checked') ) {
                        AuraFormsFrontend.markError($el, i18n.required || 'Este campo es obligatorio.');
                        valid = false;
                    }
                    return;
                }

                if ( ! val || val.trim() === '' ) {
                    AuraFormsFrontend.markError($el, i18n.required || 'Este campo es obligatorio.');
                    valid = false;
                } else if ( type === 'email' && ! AuraFormsFrontend.isValidEmail(val) ) {
                    AuraFormsFrontend.markError($el, i18n.invalidEmail || 'Correo electrónico inválido.');
                    valid = false;
                }
            });

            // Grupos de radio requeridos
            $form.find('input[type="radio"][required]').each(function () {
                var name     = $(this).attr('name');
                var $checked = $form.find('input[name="' + name + '"]:checked');
                if ( ! $checked.length ) {
                    var $group = $form.find('input[name="' + name + '"]').first().closest('fieldset');
                    AuraFormsFrontend.markGroupError($group, i18n.required || 'Este campo es obligatorio.');
                    valid = false;
                }
            });

            // Grupos de checkbox con data-required-group
            var checkedGroups = {};
            $form.find('[data-required-group]').each(function () {
                var grp = $(this).data('required-group');
                if ( ! (grp in checkedGroups) ) checkedGroups[grp] = false;
                if ( $(this).is(':checked') ) checkedGroups[grp] = true;
            });
            $.each(checkedGroups, function (grp, checked) {
                if ( ! checked ) {
                    var $group = $form.find('[data-required-group="' + grp + '"]').first().closest('fieldset');
                    AuraFormsFrontend.markGroupError($group, i18n.required || 'Este campo es obligatorio.');
                    valid = false;
                }
            });

            // Escala requerida (hidden con data-required)
            $form.find('input[type="hidden"][data-required]').each(function () {
                if ( ! $(this).val() ) {
                    var $wrap = $(this).closest('.aura-field-scale');
                    AuraFormsFrontend.markGroupError($wrap, i18n.required || 'Este campo es obligatorio.');
                    valid = false;
                }
            });

            // Archivos con errores de validación previos
            if ( $form.find('.aura-input--error').length ) {
                valid = false;
            }

            return valid;
        },

        // ── Manejar respuesta de éxito ────────────────────────────
        handleSuccess: function ($form, $msgs, data) {
            var msg          = data.message || '¡Gracias! Tu respuesta ha sido enviada.';
            var redirectUrl  = data.redirect_url || $form.data('redirect') || '';

            if ( redirectUrl ) {
                // Mostrar breve mensaje antes de redirigir
                $msgs.html(
                    '<div class="aura-form-success">' + msg + '</div>'
                ).show();
                setTimeout(function () {
                    window.location.href = redirectUrl;
                }, 1200);
            } else {
                // Reemplazar formulario con el template de éxito
                $form.slideUp(300, function () {
                    $msgs.html(
                        '<div class="aura-form-success">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>' +
                        '<div class="aura-form-success__message">' + msg + '</div>' +
                        '</div>'
                    ).show();
                });
            }
        },

        // ── Utilidades de error ───────────────────────────────────
        showError: function ($container, message) {
            $container.html(
                '<div class="aura-form-notice aura-form-notice--error">' + message + '</div>'
            ).show()[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },

        markError: function ($el, message) {
            $el.addClass('aura-input--error');
            if ( ! $el.siblings('.aura-input-error').length ) {
                $('<span class="aura-input-error" role="alert">' + message + '</span>').insertAfter($el);
            }
        },

        markGroupError: function ($container, message) {
            if ( ! $container.find('.aura-input-error').length ) {
                $('<span class="aura-input-error" role="alert">' + message + '</span>').appendTo($container);
            }
        },

        isValidEmail: function (email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
    };

    $(document).ready(function () {
        window.AuraFormsFrontend.init();
    });

}(jQuery));

