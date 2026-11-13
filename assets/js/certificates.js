/**
 * Aura Business Suite — Certificados: Admin JS
 * Maneja: listado, emisión individual, detalle, plantillas, firmantes, emisión masiva, reportes y ajustes.
 */
(function ($) {
    'use strict';

    var NONCE    = (window.auraCertificates && auraCertificates.nonce)   || '';
    var AJAX_URL = (window.auraCertificates && auraCertificates.ajaxUrl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

    /* ──────────────────────────────────────────────
       UTILIDADES
       ────────────────────────────────────────────── */

    function btnLoading($btn) {
        if (!$btn || !$btn.length) return;
        $btn.data('original-text', $btn.html());
        $btn.prop('disabled', true).text('…');
    }

    function btnReset($btn) {
        if (!$btn || !$btn.length) return;
        var orig = $btn.data('original-text');
        $btn.prop('disabled', false);
        if (orig) $btn.html(orig);
    }

    function ajaxPost(action, data) {
        return $.post(AJAX_URL, $.extend({ action: action, nonce: NONCE }, data));
    }

    function calcPercent(done, total) {
        if (!total) return 0;
        return Math.min(100, Math.round((done / total) * 100));
    }

    function escHtml(str) {
        return $('<span>').text(str == null ? '' : String(str)).html();
    }

    function extractError(res, fallback) {
        if (!res) return fallback || 'Ocurrió un error.';
        if (typeof res.data === 'string' && res.data) return res.data;
        if (res.data && typeof res.data.message === 'string' && res.data.message) return res.data.message;
        if (typeof res.message === 'string' && res.message) return res.message;
        return fallback || 'Ocurrió un error. Intenta de nuevo.';
    }

    function parseCounterpartyIds(item) {
        if (!item) return { type: 'manual', wpUserId: 0, thirdPartyId: 0, label: '', email: '', document: '' };
        var isWpUser = item.type === 'wp_user' || !!item.is_wp_user || String(item.id || '').indexOf('user_') === 0 || String(item.id || '').indexOf('wp:') === 0;
        var rawId = parseInt(
            (isWpUser ? (item.user_id || item.id) : (item.third_party_id || item.id))
                .toString()
                .replace(/^(user_|wp_|tp_|wp:|tp:)/, ''),
            10
        ) || 0;

        return {
            type         : isWpUser ? 'wp_user' : 'third_party',
            wpUserId     : isWpUser ? rawId : 0,
            thirdPartyId : isWpUser ? 0 : rawId,
            label        : item.commercial_name || item.name || item.display_name || item.label || '',
            email        : item.email || item.user_email || '',
            document     : item.document_id || '',
            badgeLabel   : item.accounting_role_label || item.party_type_label || (isWpUser ? 'Usuario WP' : 'Tercero')
        };
    }

    function initCounterpartyAutocomplete($input, $hiddenRef, onSelectItem, onClearItem) {
        if (!$input.length || !window.AuraThirdPartySelector || typeof window.AuraThirdPartySelector.attach !== 'function') {
            return;
        }
        window.AuraThirdPartySelector.attach({
            input      : $input,
            hiddenId   : $hiddenRef || null,
            formatId   : 'prefixed',
            allowUsers : true,
            onSelect   : function (item) {
                if (typeof onSelectItem === 'function') {
                    onSelectItem(parseCounterpartyIds(item), item);
                }
            },
            onClear    : function () {
                if (typeof onClearItem === 'function') {
                    onClearItem();
                }
            }
        });
    }

    /* ──────────────────────────────────────────────
       FILTRO EN LISTADO DE CERTIFICADOS
       ────────────────────────────────────────────── */
    var listFilters = {
        init: function () {
            var $input = $('#aura-cert-filter-counterparty');
            var $ref   = $('#aura-cert-filter-counterparty-ref');
            if (!$input.length) return;

            initCounterpartyAutocomplete($input, $ref, function (parsed) {
                $input.val(parsed.label);
                $ref.val(parsed.wpUserId ? ('wp:' + parsed.wpUserId) : ('tp:' + parsed.thirdPartyId));
            }, function () {
                $ref.val('');
            });

            $input.on('input', function () {
                if (!$(this).val().trim()) {
                    $ref.val('');
                }
            });

            $('#aura-cert-filter-counterparty-explore').on('click', function (e) {
                e.preventDefault();
                if (!window.AuraThirdPartySelector) return;
                window.AuraThirdPartySelector.openExplorer({
                    defaultTab : 'all',
                    allowUsers : true,
                    onSelect   : function (item) {
                        var parsed = parseCounterpartyIds(item);
                        $input.val(parsed.label);
                        $ref.val(parsed.wpUserId ? ('wp:' + parsed.wpUserId) : ('tp:' + parsed.thirdPartyId));
                    }
                });
            });
        }
    };

    /* ──────────────────────────────────────────────
       MODAL REVOCAR
       ────────────────────────────────────────────── */
    var revokeModal = {
        $modal   : null,
        $id      : null,
        $folio   : null,
        $label   : null,
        $reason  : null,
        $confirm : null,

        init: function () {
            this.$modal   = $('#aura-revoke-modal');
            if (!this.$modal.length) return;

            this.$id      = this.$modal.find('#aura-revoke-id');
            this.$folio   = this.$modal.find('#aura-revoke-folio');
            this.$label   = this.$modal.find('#aura-revoke-folio-label');
            this.$reason  = this.$modal.find('#aura-revoke-reason');
            this.$confirm = this.$modal.find('#aura-revoke-confirm');

            $(document).on('click', '.aura-cert-revoke-btn', function () {
                var id    = $(this).data('id') || '';
                var folio = $(this).data('folio') || '';
                revokeModal.$id.val(id);
                if (revokeModal.$folio.length) revokeModal.$folio.val(folio);
                if (revokeModal.$label.length) revokeModal.$label.text(folio ? '(' + folio + ')' : '');
                revokeModal.$reason.val('');
                revokeModal.$modal.fadeIn(120);
            });

            this.$modal.on('click', '.aura-modal-close, .aura-modal-overlay, #aura-revoke-cancel', function (e) {
                e.preventDefault();
                revokeModal.$modal.fadeOut(120);
            });

            this.$confirm.on('click', function () {
                var reason = revokeModal.$reason.val().trim();
                if (!reason) {
                    alert('Por favor ingresa el motivo de revocación.');
                    revokeModal.$reason.focus();
                    return;
                }
                btnLoading(revokeModal.$confirm);
                ajaxPost('aura_cert_revoke', {
                    id     : revokeModal.$id.val(),
                    folio  : revokeModal.$folio.length ? revokeModal.$folio.val() : '',
                    reason : reason
                }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(extractError(res, 'Error al revocar.'));
                        btnReset(revokeModal.$confirm);
                    }
                }).fail(function () {
                    alert('Error de conexión.');
                    btnReset(revokeModal.$confirm);
                });
            });
        }
    };

    /* ──────────────────────────────────────────────
       DETALLE DE CERTIFICADO (action=view)
       ────────────────────────────────────────────── */
    var viewPage = {
        init: function () {
            var $page = $('#aura-cert-view-page');
            if (!$page.length) return;

            $('#aura-cert-copy-verify-btn').on('click', function () {
                var $btn = $(this);
                var url  = $btn.data('url') || '';
                if (!url) return;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        var orig = $btn.text();
                        $btn.text('✓ Copiado');
                        setTimeout(function () { $btn.text(orig); }, 2000);
                    });
                } else {
                    window.prompt('Copia este enlace de verificación:', url);
                }
            });

            $('#aura-cert-resend-email-btn').on('click', function () {
                var $btn = $(this);
                var id   = $btn.data('id');
                if (!id) return;
                btnLoading($btn);
                ajaxPost('aura_cert_resend_email', { id: id }).done(function (res) {
                    btnReset($btn);
                    if (res.success) {
                        alert((res.data && res.data.message) || 'Correo reenviado correctamente.');
                    } else {
                        alert(extractError(res, 'No se pudo reenviar el correo.'));
                    }
                }).fail(function () {
                    btnReset($btn);
                    alert('Error de conexión.');
                });
            });
        }
    };

    /* ──────────────────────────────────────────────
       EMISIÓN INDIVIDUAL (action=issue)
       ────────────────────────────────────────────── */
    var issuePage = {
        searchTimer: null,

        init: function () {
            var $page = $('#aura-cert-issue-page');
            if (!$page.length) return;

            // Tabs tipo de destinatario
            $page.on('click', '.aura-recipient-tab', function () {
                var mode = $(this).data('mode');
                $page.find('.aura-recipient-tab').removeClass('is-active');
                $(this).addClass('is-active');
                $('#aura-issue-recipient-mode').val(mode);
                $page.find('.aura-recipient-panel').hide();
                $page.find('.aura-recipient-panel[data-panel="' + mode + '"]').show();
            });

            // Selector de plantilla -> preview
            function updateTplPreview() {
                var $opt  = $('#aura-issue-template-id option:selected');
                var thumb = $opt.attr('data-thumb') || '';
                var $box  = $('#aura-issue-tpl-preview');
                if (thumb) {
                    $box.find('img').attr('src', thumb);
                    $box.show();
                } else {
                    $box.hide();
                }
            }
            $('#aura-issue-template-id').on('change', updateTplPreview);
            updateTplPreview();

            // Buscador de estudiantes / matrículas
            var $stuSearch = $('#aura-issue-student-search');
            var $stuDrop   = $('#aura-issue-student-results');

            $stuSearch.on('input', function () {
                var term = $(this).val().trim();
                clearTimeout(issuePage.searchTimer);
                if (term.length < 2) {
                    $stuDrop.hide().empty();
                    return;
                }
                issuePage.searchTimer = setTimeout(function () {
                    ajaxPost('aura_cert_search_students', { term: term }).done(function (res) {
                        if (!res.success || !res.data || !Array.isArray(res.data.items) || !res.data.items.length) {
                            $stuDrop.html('<div class="aura-search-empty">Sin resultados para "' + escHtml(term) + '".</div>').show();
                            return;
                        }
                        var html = '';
                        res.data.items.forEach(function (it) {
                            var full = $.trim((it.first_name || '') + ' ' + (it.last_name || ''));
                            html += '<div class="aura-search-item" data-item=\'' + escHtml(JSON.stringify(it)) + '\'>' +
                                '<div class="aura-search-item-title"><strong>' + escHtml(full) + '</strong> ' +
                                (it.has_cert ? '<span class="aura-cert-badge aura-cert-badge--warning">Ya certificado</span>' : '') +
                                '</div>' +
                                '<div class="aura-search-item-sub">' +
                                escHtml(it.email || '') +
                                (it.course_name ? ' • <em>' + escHtml(it.course_name) + '</em>' : '') +
                                '</div>' +
                            '</div>';
                        });
                        $stuDrop.html(html).show();
                    });
                }, 250);
            });

            $stuDrop.on('click', '.aura-search-item', function () {
                var raw = $(this).attr('data-item');
                if (!raw) return;
                var it = JSON.parse(raw);
                var full = $.trim((it.first_name || '') + ' ' + (it.last_name || ''));

                $('#aura-issue-student-id').val(it.student_id || 0);
                $('#aura-issue-enrollment-id').val(it.enrollment_id || 0);
                $stuSearch.val(full);
                $('#aura-issue-student-clear').show();

                var chipHtml = '<strong>' + escHtml(full) + '</strong>' +
                    (it.email ? ' <span>(' + escHtml(it.email) + ')</span>' : '') +
                    (it.course_name ? ' <span class="aura-cert-badge aura-cert-badge--info">' + escHtml(it.course_name) + '</span>' : '');
                $('#aura-issue-student-selected').html(chipHtml).show();
                $stuDrop.hide();

                $('#aura-issue-recipient-name').val(full);
                if (it.email) $('#aura-issue-recipient-email').val(it.email);
                if (it.course_name && !$('#aura-issue-course-name').val()) $('#aura-issue-course-name').val(it.course_name);
                if (it.program_name && !$('#aura-issue-program-name').val()) $('#aura-issue-program-name').val(it.program_name);
                if (it.graduation_date) $('#aura-issue-graduation-date').val(String(it.graduation_date).substring(0, 10));
            });

            $('#aura-issue-student-clear').on('click', function () {
                $('#aura-issue-student-id').val('0');
                $('#aura-issue-enrollment-id').val('0');
                $stuSearch.val('').focus();
                $('#aura-issue-student-selected').hide().empty();
                $(this).hide();
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#aura-issue-student-search, #aura-issue-student-results').length) {
                    $stuDrop.hide();
                }
            });

            // Selector de Usuario WP o Tercero
            function applyCounterpartySelection(parsed) {
                $('#aura-issue-wp-user-id').val(parsed.wpUserId);
                $('#aura-issue-third-party-id').val(parsed.thirdPartyId);
                $('#aura-issue-counterparty-search').val(parsed.label);
                $('#aura-issue-counterparty-clear').show();

                var chip = '<span class="aura-cert-badge aura-cert-badge--' + (parsed.type === 'wp_user' ? 'wpuser' : 'thirdparty') + '">' +
                    escHtml(parsed.badgeLabel) + '</span> <strong>' + escHtml(parsed.label) + '</strong>' +
                    (parsed.email ? ' <span>(' + escHtml(parsed.email) + ')</span>' : '') +
                    (parsed.document ? ' <span>• Doc: ' + escHtml(parsed.document) + '</span>' : '');
                $('#aura-issue-counterparty-preview').html(chip).show();

                $('#aura-issue-recipient-name').val(parsed.label);
                if (parsed.email) $('#aura-issue-recipient-email').val(parsed.email);
                if (parsed.document) $('#aura-issue-recipient-document').val(parsed.document);
            }

            initCounterpartyAutocomplete(
                $('#aura-issue-counterparty-search'),
                $('#aura-issue-counterparty-ref'),
                applyCounterpartySelection,
                function () {
                    $('#aura-issue-wp-user-id').val('0');
                    $('#aura-issue-third-party-id').val('0');
                    $('#aura-issue-counterparty-preview').hide().empty();
                    $('#aura-issue-counterparty-clear').hide();
                }
            );

            $('#aura-issue-counterparty-explore').on('click', function (e) {
                e.preventDefault();
                if (!window.AuraThirdPartySelector) {
                    alert('El selector de Usuarios y Terceros no está disponible en esta página.');
                    return;
                }
                window.AuraThirdPartySelector.openExplorer({
                    defaultTab : 'all',
                    allowUsers : true,
                    onSelect   : function (item) {
                        applyCounterpartySelection(parseCounterpartyIds(item));
                    }
                });
            });

            $('#aura-issue-counterparty-clear').on('click', function () {
                $('#aura-issue-wp-user-id').val('0');
                $('#aura-issue-third-party-id').val('0');
                $('#aura-issue-counterparty-ref').val('');
                $('#aura-issue-counterparty-search').val('');
                $('#aura-issue-counterparty-preview').hide().empty();
                $(this).hide();
            });

            // Enviar emisión
            $('#aura-cert-issue-form').on('submit', function (e) {
                e.preventDefault();
                var mode = $('#aura-issue-recipient-mode').val();
                var recipientName = $('#aura-issue-recipient-name').val().trim();
                var courseName    = $('#aura-issue-course-name').val().trim();

                if (mode === 'student' && !parseInt($('#aura-issue-student-id').val(), 10) && !recipientName) {
                    alert('Selecciona un estudiante o ingresa el nombre del destinatario.');
                    return;
                }
                if (mode === 'counterparty' && !parseInt($('#aura-issue-wp-user-id').val(), 10) && !parseInt($('#aura-issue-third-party-id').val(), 10) && !recipientName) {
                    alert('Selecciona un Usuario WP o Tercero.');
                    return;
                }
                if (!recipientName) {
                    alert('El nombre en el certificado es obligatorio.');
                    $('#aura-issue-recipient-name').focus();
                    return;
                }
                if (!courseName) {
                    alert('El curso o motivo del certificado es obligatorio.');
                    $('#aura-issue-course-name').focus();
                    return;
                }

                var $btn = $('#aura-issue-submit-btn');
                btnLoading($btn);

                ajaxPost('aura_cert_issue', {
                    recipient_mode     : mode,
                    student_id         : $('#aura-issue-student-id').val(),
                    enrollment_id      : $('#aura-issue-enrollment-id').val(),
                    wp_user_id         : $('#aura-issue-wp-user-id').val(),
                    third_party_id     : $('#aura-issue-third-party-id').val(),
                    recipient_name     : recipientName,
                    recipient_email    : $('#aura-issue-recipient-email').val().trim(),
                    recipient_document : $('#aura-issue-recipient-document').val().trim(),
                    template_id        : $('#aura-issue-template-id').val(),
                    course_name        : courseName,
                    program_name       : $('#aura-issue-program-name').val().trim(),
                    category           : $('#aura-issue-category').val().trim(),
                    hours              : $('#aura-issue-hours').val().trim(),
                    grade              : $('#aura-issue-grade').val().trim(),
                    instructor_name    : $('#aura-issue-instructor').val().trim(),
                    graduation_date    : $('#aura-issue-graduation-date').val(),
                    city               : $('#aura-issue-city').val().trim(),
                    description        : $('#aura-issue-description').val().trim(),
                    include_signatures : $('#aura-issue-include-signatures').is(':checked') ? 1 : 0,
                    send_email         : $('#aura-issue-send-email').is(':checked') ? 1 : 0,
                    send_whatsapp      : $('#aura-issue-send-whatsapp').is(':checked') ? 1 : 0
                }).done(function (res) {
                    btnReset($btn);
                    if (res.success && res.data) {
                        var d = res.data;
                        var pdfUrl = AJAX_URL + '?action=aura_cert_download&folio=' + encodeURIComponent(d.folio);
                        var html = '<div class="notice notice-success inline" style="padding:14px 16px;margin:0;">' +
                            '<p style="margin:0 0 8px;"><strong>✅ ' + escHtml(d.message || 'Certificado emitido correctamente.') + '</strong> ' +
                            'Folio: <code>' + escHtml(d.folio) + '</code></p>' +
                            '<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
                            (d.view_url ? '<a href="' + escHtml(d.view_url) + '" class="button button-primary">Ver Certificado</a>' : '') +
                            '<a href="' + escHtml(pdfUrl) + '" class="button" target="_blank">⬇ Descargar PDF</a>' +
                            '<button type="button" class="button aura-issue-another-btn">Emitir otro</button>' +
                            '</div></div>';
                        $('#aura-issue-result').html(html).slideDown(150);
                        $('html, body').animate({ scrollTop: 0 }, 250);
                    } else {
                        alert(extractError(res, 'Error al emitir el certificado.'));
                    }
                }).fail(function () {
                    btnReset($btn);
                    alert('Error de conexión.');
                });
            });

            $page.on('click', '.aura-issue-another-btn', function () {
                $('#aura-issue-result').slideUp(120);
                $('#aura-issue-student-id').val('0');
                $('#aura-issue-enrollment-id').val('0');
                $('#aura-issue-wp-user-id').val('0');
                $('#aura-issue-third-party-id').val('0');
                $('#aura-issue-student-search, #aura-issue-counterparty-search, #aura-issue-recipient-name, #aura-issue-recipient-email, #aura-issue-recipient-document').val('');
                $('#aura-issue-student-selected, #aura-issue-counterparty-preview, #aura-issue-student-clear, #aura-issue-counterparty-clear').hide();
            });
        }
    };

    /* ──────────────────────────────────────────────
       PLANTILLAS — ACCIONES EN LISTA
       ────────────────────────────────────────────── */
    var templatesList = {
        init: function () {
            if (!$('.aura-cert-templates-grid').length) return;

            $(document).on('click', '.aura-cert-set-default-btn', function () {
                var $btn = $(this);
                var id   = $btn.data('id');
                if (!confirm('¿Establecer esta plantilla como predeterminada?')) return;
                btnLoading($btn);
                ajaxPost('aura_cert_set_default', { id: id }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(extractError(res, 'Error al actualizar.'));
                        btnReset($btn);
                    }
                });
            });

            $(document).on('click', '.aura-cert-toggle-active-btn', function () {
                var $btn      = $(this);
                var id        = $btn.data('id');
                var isActive  = parseInt($btn.attr('data-active'), 10) === 1;
                btnLoading($btn);
                ajaxPost('aura_cert_toggle_active', { id: id, active: isActive ? 0 : 1 }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(extractError(res, 'Error al cambiar estado.'));
                        btnReset($btn);
                    }
                });
            });

            $(document).on('click', '.aura-cert-delete-template-btn', function () {
                var $btn = $(this);
                var id   = $btn.data('id');
                if (!confirm('¿Eliminar esta plantilla? Esta acción no se puede deshacer.')) return;
                btnLoading($btn);
                ajaxPost('aura_cert_delete_template', { id: id }).done(function (res) {
                    if (res.success) {
                        $btn.closest('.aura-cert-template-card').fadeOut(300, function () {
                            $(this).remove();
                        });
                    } else {
                        alert(extractError(res, 'Error al eliminar.'));
                        btnReset($btn);
                    }
                });
            });
        }
    };

    /* ──────────────────────────────────────────────
       FIRMANTES
       ────────────────────────────────────────────── */
    var signersPage = {
        editingId : 0,
        $form     : null,
        $table    : null,

        init: function () {
            this.$form  = $('#aura-signer-form');
            this.$table = $('#aura-signers-sortable');

            if (!this.$form.length) return;

            // Selector Usuario WP o Tercero para vincular firmante
            function applySignerLink(parsed) {
                $('#aura-signer-wp-user-id').val(parsed.wpUserId);
                $('#aura-signer-third-party-id').val(parsed.thirdPartyId);
                $('#aura-signer-counterparty-search').val(parsed.label);
                $('#aura-signer-counterparty-clear').show();
                if (!$('#aura-signer-name').val().trim()) {
                    $('#aura-signer-name').val(parsed.label);
                }
                $('#aura-signer-counterparty-preview').html(
                    '<span class="aura-cert-badge aura-cert-badge--' + (parsed.type === 'wp_user' ? 'wpuser' : 'thirdparty') + '">' +
                    escHtml(parsed.badgeLabel) + '</span> <strong>' + escHtml(parsed.label) + '</strong>'
                ).show();
            }

            initCounterpartyAutocomplete(
                $('#aura-signer-counterparty-search'),
                null,
                applySignerLink,
                function () {
                    $('#aura-signer-wp-user-id').val('0');
                    $('#aura-signer-third-party-id').val('0');
                    $('#aura-signer-counterparty-preview').hide().empty();
                    $('#aura-signer-counterparty-clear').hide();
                }
            );

            $('#aura-signer-counterparty-explore').on('click', function (e) {
                e.preventDefault();
                if (!window.AuraThirdPartySelector) return;
                window.AuraThirdPartySelector.openExplorer({
                    defaultTab : 'user',
                    allowUsers : true,
                    onSelect   : function (item) {
                        applySignerLink(parseCounterpartyIds(item));
                    }
                });
            });

            $('#aura-signer-counterparty-clear').on('click', function () {
                $('#aura-signer-wp-user-id').val('0');
                $('#aura-signer-third-party-id').val('0');
                $('#aura-signer-counterparty-search').val('');
                $('#aura-signer-counterparty-preview').hide().empty();
                $(this).hide();
            });

            // WP Media para imagen de firma
            $(document).on('click', '#aura-signer-upload-btn', function (e) {
                e.preventDefault();
                if (typeof wp === 'undefined' || !wp.media) {
                    alert('El selector multimedia de WordPress no está cargado.');
                    return;
                }
                var frame = wp.media({
                    title    : 'Seleccionar imagen de firma',
                    button   : { text: 'Usar esta imagen' },
                    library  : { type: 'image' },
                    multiple : false
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#aura-signer-attachment-id').val(att.id || 0);
                    $('#aura-signer-signature-url').val(att.url || '');
                    $('#aura-signer-sig-preview').html('<img src="' + escHtml(att.url) + '" style="max-height:56px;max-width:150px;object-fit:contain;">');
                });
                frame.open();
            });

            // Guardar firmante
            $(document).on('submit', '#aura-signer-form', function (e) {
                e.preventDefault();
                var name = $('#aura-signer-name').val().trim();
                if (!name) {
                    alert('El nombre del firmante es obligatorio.');
                    $('#aura-signer-name').focus();
                    return;
                }
                var $btn = $('#aura-signer-save-btn');
                btnLoading($btn);
                ajaxPost('aura_cert_save_signer', {
                    id             : signersPage.editingId || $('#aura-signer-id').val() || 0,
                    name           : name,
                    title          : $('#aura-signer-title').val().trim(),
                    attachment_id  : $('#aura-signer-attachment-id').val() || 0,
                    signature_url  : $('#aura-signer-signature-url').val() || '',
                    wp_user_id     : $('#aura-signer-wp-user-id').val() || 0,
                    third_party_id : $('#aura-signer-third-party-id').val() || 0,
                    is_active      : $('#aura-signer-active').is(':checked') ? 1 : 0
                }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(extractError(res, 'Error al guardar firmante.'));
                        btnReset($btn);
                    }
                }).fail(function () {
                    alert('Error de conexión.');
                    btnReset($btn);
                });
            });

            // Editar firmante
            $(document).on('click', '.aura-signer-edit-btn', function () {
                var $btn  = $(this);
                var id    = parseInt($btn.data('id'), 10) || 0;
                signersPage.editingId = id;
                $('#aura-signer-id').val(id);
                $('#aura-signer-form-title').text('Editar Firmante');
                $('#aura-signer-name').val($btn.attr('data-name') || '');
                $('#aura-signer-title').val($btn.attr('data-title') || '');
                $('#aura-signer-active').prop('checked', String($btn.attr('data-active')) === '1');

                var attId  = $btn.attr('data-attachment') || '0';
                var sigUrl = $btn.attr('data-sig-url') || '';
                $('#aura-signer-attachment-id').val(attId);
                $('#aura-signer-signature-url').val(sigUrl);

                if (sigUrl) {
                    $('#aura-signer-sig-preview').html('<img src="' + escHtml(sigUrl) + '" style="max-height:56px;max-width:150px;object-fit:contain;">');
                } else {
                    $('#aura-signer-sig-preview').html('<span style="color:#aaa;">Sin imagen</span>');
                }

                var wpUid = parseInt($btn.attr('data-wp-user-id'), 10) || 0;
                var tpId  = parseInt($btn.attr('data-third-party-id'), 10) || 0;
                var lLbl  = $btn.attr('data-linked-label') || '';
                $('#aura-signer-wp-user-id').val(wpUid);
                $('#aura-signer-third-party-id').val(tpId);
                if ((wpUid || tpId) && lLbl) {
                    $('#aura-signer-counterparty-preview').html('<strong>' + escHtml(lLbl) + '</strong>').show();
                    $('#aura-signer-counterparty-clear').show();
                } else {
                    $('#aura-signer-counterparty-preview').hide().empty();
                    $('#aura-signer-counterparty-clear').hide();
                }

                $('#aura-signer-cancel-btn').show();
                $('html, body').animate({ scrollTop: signersPage.$form.offset().top - 40 }, 300);
            });

            // Cancelar edición
            $(document).on('click', '#aura-signer-cancel-btn', function () {
                signersPage.resetForm();
            });

            // Eliminar firmante
            $(document).on('click', '.aura-signer-delete-btn', function () {
                if (!confirm('¿Eliminar este firmante?')) return;
                var $btn = $(this);
                var id   = $btn.data('id');
                ajaxPost('aura_cert_delete_signer', { id: id }).done(function (res) {
                    if (res.success) {
                        $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                    } else {
                        alert(extractError(res, 'Error al eliminar.'));
                    }
                });
            });

            // Activar / desactivar firmante
            $(document).on('click', '.aura-signer-toggle-btn', function () {
                var $btn     = $(this);
                var id       = $btn.data('id');
                var isActive = String($btn.attr('data-active')) === '1';
                ajaxPost('aura_cert_toggle_signer', { id: id, active: isActive ? 0 : 1 }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(extractError(res, 'Error al actualizar estado.'));
                    }
                });
            });

            // Ordenamiento por drag
            if (this.$table.length && $.fn.sortable) {
                this.$table.sortable({
                    handle : '.aura-drag-handle',
                    update : function () {
                        var ids = [];
                        signersPage.$table.find('tr').each(function () {
                            ids.push($(this).data('id'));
                        });
                        ajaxPost('aura_cert_reorder_signers', { order: ids });
                    }
                });
            }
        },

        resetForm: function () {
            this.editingId = 0;
            this.$form[0].reset();
            $('#aura-signer-id').val('0');
            $('#aura-signer-form-title').text('Agregar Firmante');
            $('#aura-signer-attachment-id').val('0');
            $('#aura-signer-signature-url').val('');
            $('#aura-signer-wp-user-id').val('0');
            $('#aura-signer-third-party-id').val('0');
            $('#aura-signer-counterparty-preview, #aura-signer-counterparty-clear, #aura-signer-cancel-btn').hide();
            $('#aura-signer-sig-preview').html('<span style="color:#aaa;">Sin imagen</span>');
        }
    };

    /* ──────────────────────────────────────────────
       EMISIÓN MASIVA (Estudiantes + Usuarios WP / Terceros)
       ────────────────────────────────────────────── */
    var bulkIssue = {
        batchId : null,
        cpItems : [],

        init: function () {
            var $page = $('#aura-bulk-issue-page');
            if (!$page.length) return;

            // Tabs Estudiantes / Usuarios WP & Terceros
            $page.on('click', '.aura-recipient-tab[data-bulk-tab]', function () {
                var tab = $(this).data('bulk-tab');
                $page.find('.aura-recipient-tab[data-bulk-tab]').removeClass('is-active');
                $(this).addClass('is-active');
                $page.find('.aura-bulk-tab-panel').hide();
                $page.find('.aura-bulk-tab-panel[data-bulk-panel="' + tab + '"]').show();
            });

            // Contador de seleccionados (Estudiantes)
            function refreshStudentSelectionUI() {
                var count = $('.aura-bulk-cert-check:checked').length;
                $('#aura-bulk-selected-count').text(count + ' seleccionado(s)');
                $('#aura-bulk-issue-btn').prop('disabled', count === 0);
            }

            $(document).on('change', '.aura-bulk-cert-check, #aura-bulk-check-all', function () {
                if ($(this).is('#aura-bulk-check-all')) {
                    $('.aura-bulk-cert-check').prop('checked', $(this).is(':checked'));
                }
                refreshStudentSelectionUI();
            });

            $(document).on('click', '#aura-bulk-select-all', function () {
                $('.aura-bulk-cert-check, #aura-bulk-check-all').prop('checked', true);
                refreshStudentSelectionUI();
            });

            $(document).on('click', '#aura-bulk-deselect-all', function () {
                $('.aura-bulk-cert-check, #aura-bulk-check-all').prop('checked', false);
                refreshStudentSelectionUI();
            });

            // Lote de Usuarios WP y Terceros
            function renderCounterpartyList() {
                var $tbody = $('#aura-bulk-cp-tbody');
                $tbody.empty();

                if (!bulkIssue.cpItems.length) {
                    $('#aura-bulk-cp-table').hide();
                    $('#aura-bulk-cp-empty').show();
                    $('#aura-bulk-cp-issue-btn').prop('disabled', true);
                    $('#aura-bulk-cp-count').text('');
                    return;
                }

                $('#aura-bulk-cp-empty').hide();
                $('#aura-bulk-cp-table').show();
                $('#aura-bulk-cp-issue-btn').prop('disabled', false);
                $('#aura-bulk-cp-count').text(bulkIssue.cpItems.length + ' destinatario(s) en cola');

                bulkIssue.cpItems.forEach(function (it, idx) {
                    var badgeCls = it.wp_user_id ? 'aura-cert-badge--wpuser' : 'aura-cert-badge--thirdparty';
                    var badgeTxt = it.wp_user_id ? 'Usuario WP' : 'Tercero';
                    $tbody.append(
                        '<tr>' +
                        '<td><strong>' + escHtml(it.name) + '</strong></td>' +
                        '<td><span class="aura-cert-badge ' + badgeCls + '">' + badgeTxt + '</span></td>' +
                        '<td>' + escHtml(it.email || '—') + '</td>' +
                        '<td>' + escHtml(it.document_id || '—') + '</td>' +
                        '<td><button type="button" class="button button-small aura-bulk-cp-remove" data-idx="' + idx + '">✕</button></td>' +
                        '</tr>'
                    );
                });
            }

            function addCounterpartyItem(parsed) {
                if (!parsed.wpUserId && !parsed.thirdPartyId) return;
                var exists = bulkIssue.cpItems.some(function (x) {
                    return (parsed.wpUserId && x.wp_user_id === parsed.wpUserId) ||
                           (parsed.thirdPartyId && x.third_party_id === parsed.thirdPartyId);
                });
                if (!exists) {
                    bulkIssue.cpItems.push({
                        wp_user_id     : parsed.wpUserId,
                        third_party_id : parsed.thirdPartyId,
                        name           : parsed.label,
                        email          : parsed.email,
                        document_id    : parsed.document
                    });
                    renderCounterpartyList();
                }
                $('#aura-bulk-cp-search').val('');
            }

            initCounterpartyAutocomplete($('#aura-bulk-cp-search'), null, addCounterpartyItem);

            $('#aura-bulk-cp-explore').on('click', function (e) {
                e.preventDefault();
                if (!window.AuraThirdPartySelector) return;
                window.AuraThirdPartySelector.openExplorer({
                    defaultTab : 'all',
                    allowUsers : true,
                    onSelect   : function (item) {
                        addCounterpartyItem(parseCounterpartyIds(item));
                    }
                });
            });

            $(document).on('click', '.aura-bulk-cp-remove', function () {
                var idx = parseInt($(this).data('idx'), 10);
                if (!isNaN(idx)) {
                    bulkIssue.cpItems.splice(idx, 1);
                    renderCounterpartyList();
                }
            });

            // Iniciar emisión masiva (Estudiantes)
            $(document).on('click', '#aura-bulk-issue-btn', function () {
                var items = [];
                $('.aura-bulk-cert-check:checked').each(function () {
                    items.push({
                        student_id    : $(this).data('student-id'),
                        enrollment_id : $(this).data('enrollment-id')
                    });
                });
                if (!items.length) return;
                bulkIssue.queueAndRun($(this), items, {});
            });

            // Iniciar emisión masiva (Usuarios WP y Terceros)
            $(document).on('click', '#aura-bulk-cp-issue-btn', function () {
                if (!bulkIssue.cpItems.length) return;
                var course = $('#aura-bulk-cp-course').val().trim();
                if (!course) {
                    alert('Ingresa el nombre del curso o evento para el lote.');
                    $('#aura-bulk-cp-course').focus();
                    return;
                }
                bulkIssue.queueAndRun($(this), bulkIssue.cpItems, {
                    course_name     : course,
                    program_name    : $('#aura-bulk-cp-program').val().trim(),
                    category        : $('#aura-bulk-cp-category').val().trim(),
                    hours           : $('#aura-bulk-cp-hours').val().trim(),
                    instructor_name : $('#aura-bulk-cp-instructor').val().trim(),
                    city            : $('#aura-bulk-cp-city').val().trim(),
                    graduation_date : $('#aura-bulk-cp-date').val(),
                    description     : $('#aura-bulk-cp-description').val().trim()
                });
            });
        },

        queueAndRun: function ($btn, items, extraMeta) {
            btnLoading($btn);
            var payload = $.extend({
                template_id        : $('#aura-bulk-template-id').val() || 0,
                include_signatures : $('#aura-bulk-include-signatures').is(':checked') ? 1 : 0,
                send_email         : $('#aura-bulk-send-email').is(':checked') ? 1 : 0,
                send_whatsapp      : $('#aura-bulk-send-whatsapp').is(':checked') ? 1 : 0,
                items              : JSON.stringify(items)
            }, extraMeta);

            ajaxPost('aura_cert_queue_bulk', payload).done(function (res) {
                if (res.success && res.data && res.data.batch_id) {
                    bulkIssue.batchId = res.data.batch_id;
                    $btn.prop('disabled', true);
                    bulkIssue.runStep(res.data.total || items.length);
                } else {
                    alert(extractError(res, 'Error al iniciar el lote.'));
                    btnReset($btn);
                }
            }).fail(function () {
                alert('Error de conexión.');
                btnReset($btn);
            });
        },

        runStep: function (total) {
            var $wrap = $('#aura-bulk-progress-wrap');
            var $bar  = $('#aura-bulk-progress-bar');
            var $txt  = $('#aura-bulk-progress-text');
            $wrap.show();

            ajaxPost('aura_cert_bulk_step', { batch_id: bulkIssue.batchId }).done(function (res) {
                if (!res.success || !res.data) {
                    $txt.text(extractError(res, 'Error procesando lote.'));
                    return;
                }
                var d   = res.data;
                var pct = d.percent != null ? d.percent : calcPercent(d.done, d.total || total);
                $bar.css('width', pct + '%');
                $txt.text((d.done || 0) + ' / ' + (d.total || total) + ' procesados (' + (d.success || 0) + ' exitosos, ' + (d.failed || 0) + ' fallidos)');

                if (d.status === 'done' || (d.done >= (d.total || total))) {
                    $bar.css('background', '#16a34a');
                    $txt.append(' — ✅ Proceso completado.');
                    setTimeout(function () { location.reload(); }, 1800);
                } else {
                    setTimeout(function () { bulkIssue.runStep(total); }, 350);
                }
            }).fail(function () {
                $txt.text('Error de conexión procesando el lote.');
            });
        }
    };

    /* ──────────────────────────────────────────────
       REPORTES
       ────────────────────────────────────────────── */
    var reports = {
        lastData : null,

        init: function () {
            var $page = $('#aura-cert-reports-page');
            if (!$page.length) return;

            $(document).on('click', '#aura-cert-report-generate', function () {
                var $btn = $(this);
                btnLoading($btn);
                $('#aura-cert-report-loading').show();

                ajaxPost('aura_cert_generate_report', {
                    report_type : $('#aura-cert-report-type').val(),
                    date_from   : $('#aura-cert-date-from').val(),
                    date_to     : $('#aura-cert-date-to').val()
                }).done(function (res) {
                    btnReset($btn);
                    $('#aura-cert-report-loading').hide();
                    if (res.success && res.data) {
                        reports.lastData = res.data;
                        var rows = res.data.rows || [];
                        $('#aura-cert-report-summary').text(rows.length + ' registro(s) encontrado(s).');
                        reports.renderTable(rows, res.data.columns || []);
                        $('#aura-cert-report-result').show();
                        $('#aura-cert-report-excel, #aura-cert-report-pdf').prop('disabled', rows.length === 0);
                    } else {
                        alert(extractError(res, 'Error al generar reporte.'));
                    }
                }).fail(function () {
                    btnReset($btn);
                    $('#aura-cert-report-loading').hide();
                    alert('Error de conexión.');
                });
            });

            $(document).on('click', '#aura-cert-report-excel', function () {
                reports.submitExport('excel');
            });

            $(document).on('click', '#aura-cert-report-pdf', function () {
                reports.submitExport('pdf');
            });
        },

        renderTable: function (rows, cols) {
            var $wrap = $('#aura-cert-report-table-wrap');
            if (!rows || !rows.length) {
                $wrap.html('<p><em>Sin resultados para el período seleccionado.</em></p>');
                return;
            }
            if (!cols.length && rows.length) {
                cols = Object.keys(rows[0]);
            }
            var html = '<table class="widefat striped"><thead><tr>';
            cols.forEach(function (c) { html += '<th>' + escHtml(c) + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.forEach(function (row) {
                html += '<tr>';
                Object.values(row).forEach(function (v) { html += '<td>' + escHtml(v || '—') + '</td>'; });
                html += '</tr>';
            });
            html += '</tbody></table>';
            $wrap.html(html);
        },

        submitExport: function (format) {
            var $form = $('<form method="POST" action="' + AJAX_URL + '" style="display:none">');
            $form.append($('<input>').attr({ type: 'hidden', name: 'action', value: 'aura_cert_export_' + format }));
            $form.append($('<input>').attr({ type: 'hidden', name: 'nonce', value: NONCE }));
            $form.append($('<input>').attr({ type: 'hidden', name: 'report_type', value: $('#aura-cert-report-type').val() }));
            $form.append($('<input>').attr({ type: 'hidden', name: 'date_from', value: $('#aura-cert-date-from').val() }));
            $form.append($('<input>').attr({ type: 'hidden', name: 'date_to', value: $('#aura-cert-date-to').val() }));
            $('body').append($form);
            $form.submit();
            setTimeout(function () { $form.remove(); }, 2000);
        }
    };

    /* ──────────────────────────────────────────────
       AJUSTES
       ────────────────────────────────────────────── */
    var settingsPage = {
        init: function () {
            var $form = $('#aura-cert-settings-form');
            if (!$form.length) return;

            $(document).on('click', '#aura-cert-logo-btn', function (e) {
                e.preventDefault();
                if (typeof wp === 'undefined' || !wp.media) return;
                var frame = wp.media({
                    title    : 'Seleccionar logo',
                    button   : { text: 'Usar este logo' },
                    library  : { type: 'image' },
                    multiple : false
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#aura-cert-org-logo').val(att.url);
                    $('#aura-cert-logo-preview').attr('src', att.url).show();
                    $('#aura-cert-logo-clear').show();
                });
                frame.open();
            });

            $(document).on('click', '#aura-cert-logo-clear', function (e) {
                e.preventDefault();
                $('#aura-cert-org-logo').val('');
                $('#aura-cert-logo-preview').attr('src', '').hide();
                $(this).hide();
            });

            $(document).on('input', '#aura-cert-verify-slug', function () {
                var slug = $(this).val().trim() || 'verificar-certificado';
                var base = window.location.origin;
                $('#aura-cert-verify-url-preview').text(base + '/' + slug + '/CEM-2026-0001');
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                var $btn = $form.find('button[type="submit"]');
                btnLoading($btn);

                var data = {};
                $form.serializeArray().forEach(function (f) {
                    data[f.name] = f.value;
                });
                $form.find('input[type="checkbox"]').each(function () {
                    if (!$(this).is(':checked')) {
                        data[$(this).attr('name')] = '0';
                    }
                });

                ajaxPost('aura_cert_save_settings', data).done(function (res) {
                    btnReset($btn);
                    if (res.success) {
                        var msg = (res.data && res.data.message) || (typeof res.data === 'string' ? res.data : 'Ajustes guardados.');
                        var $notice = $('<div class="notice notice-success is-dismissible"><p>' + escHtml(msg) + '</p></div>');
                        $form.find('.aura-ajustes-notices').html($notice);
                        setTimeout(function () { $notice.fadeOut(); }, 4000);
                    } else {
                        alert(extractError(res, 'Error al guardar ajustes.'));
                    }
                }).fail(function () {
                    btnReset($btn);
                    alert('Error de conexión.');
                });
            });

            $(document).on('click', '.nav-tab', function (e) {
                e.preventDefault();
                var target = $(this).data('tab');
                if (!target) return;
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.aura-tab-content').hide();
                $('#aura-tab-' + target).show();
                $('#aura-cert-active-tab').val(target);
            });
        }
    };

    /* ──────────────────────────────────────────────
       MODAL EMITIR CERTIFICADO (legacy desde Estudiantes)
       ────────────────────────────────────────────── */
    var issueModal = {
        $modal : null,

        init: function () {
            this.$modal = $('#aura-issue-cert-modal');
            if (!this.$modal.length) return;

            var $form    = this.$modal.find('#aura-issue-cert-form');
            var $confirm = this.$modal.find('#aura-issue-cert-confirm');

            $(document).on('click', '.aura-cert-issue-btn', function () {
                issueModal.$modal.find('#aura-issue-student-id').val($(this).data('student-id'));
                issueModal.$modal.find('#aura-issue-enrollment-id').val($(this).data('enrollment-id'));
                issueModal.$modal.show();
            });

            this.$modal.on('click', '.aura-modal-close, .aura-modal-overlay', function () {
                issueModal.$modal.hide();
            });

            $confirm.on('click', function () {
                btnLoading($confirm);
                ajaxPost('aura_cert_issue', {
                    student_id    : $form.find('#aura-issue-student-id').val(),
                    enrollment_id : $form.find('#aura-issue-enrollment-id').val(),
                    template_id   : $form.find('#aura-issue-template-id').val(),
                    send_email    : $form.find('#aura-issue-send-email').is(':checked') ? 1 : 0,
                    send_whatsapp : $form.find('#aura-issue-send-whatsapp').is(':checked') ? 1 : 0
                }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(extractError(res, 'Error al emitir certificado.'));
                        btnReset($confirm);
                    }
                }).fail(function () {
                    alert('Error de conexión.');
                    btnReset($confirm);
                });
            });
        }
    };

    /* ──────────────────────────────────────────────
       INIT
       ────────────────────────────────────────────── */
    $(function () {
        listFilters.init();
        revokeModal.init();
        viewPage.init();
        issuePage.init();
        templatesList.init();
        signersPage.init();
        bulkIssue.init();
        reports.init();
        settingsPage.init();
        issueModal.init();
    });

}(jQuery));
