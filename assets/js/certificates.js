/**
 * Aura Business Suite — Certificados: Admin JS
 * Maneja: lista, emisión, firmantes, bulk issue, reportes, ajustes.
 */
(function ($) {
    'use strict';

    var NONCE    = (window.auraCertificates && auraCertificates.nonce)   || '';
    var AJAX_URL = (window.auraCertificates && auraCertificates.ajaxUrl) || ajaxurl;

    /* ──────────────────────────────────────────────
       UTILIDADES
       ────────────────────────────────────────────── */

    /**
     * Muestra un spinner inline sobre un botón
     * @param {jQuery} $btn
     */
    function btnLoading($btn) {
        $btn.data('original-text', $btn.text());
        $btn.prop('disabled', true).text('...');
    }

    /**
     * Restaura un botón después de una operación
     * @param {jQuery} $btn
     */
    function btnReset($btn) {
        $btn.prop('disabled', false).text($btn.data('original-text') || $btn.text());
    }

    /**
     * POST AJAX genérico con nonce incorporado.
     * @param {string} action
     * @param {object} data
     * @returns {jQuery.jqXHR}
     */
    function ajaxPost(action, data) {
        return $.post(AJAX_URL, $.extend({ action: action, nonce: NONCE }, data));
    }

    /**
     * Formatea porcentaje para la barra de progreso.
     * @param {number} done
     * @param {number} total
     * @returns {number}
     */
    function calcPercent(done, total) {
        if (!total) return 0;
        return Math.min(100, Math.round((done / total) * 100));
    }

    /* ──────────────────────────────────────────────
       MODAL REVOCAR
       ────────────────────────────────────────────── */
    var revokeModal = {
        $modal   : null,
        $form    : null,
        $folio   : null,
        $reason  : null,
        $confirm : null,

        init: function () {
            this.$modal   = $('#aura-revoke-modal');
            this.$form    = this.$modal.find('form');
            this.$folio   = this.$modal.find('#aura-revoke-folio');
            this.$reason  = this.$modal.find('#aura-revoke-reason');
            this.$confirm = this.$modal.find('#aura-revoke-confirm');

            if (!this.$modal.length) return;

            // Abrir modal
            $(document).on('click', '.aura-cert-revoke-btn', function () {
                var folio = $(this).data('folio');
                revokeModal.$folio.val(folio);
                revokeModal.$reason.val('');
                revokeModal.$modal.show();
            });

            // Cerrar modal
            this.$modal.on('click', '.aura-modal-close, .aura-modal-overlay', function () {
                revokeModal.$modal.hide();
            });

            // Confirmar revocación
            this.$confirm.on('click', function () {
                var reason = revokeModal.$reason.val().trim();
                if (!reason) {
                    alert('Por favor ingresa el motivo de revocación.');
                    return;
                }
                btnLoading(revokeModal.$confirm);
                ajaxPost('aura_cert_revoke', {
                    folio  : revokeModal.$folio.val(),
                    reason : reason
                }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error al revocar.');
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
       PLANTILLAS — ACCIONES EN LISTA
       ────────────────────────────────────────────── */
    var templatesList = {
        init: function () {
            if (!$('.aura-cert-templates-grid').length) return;

            // Establecer como predeterminada
            $(document).on('click', '.aura-cert-set-default-btn', function () {
                var $btn = $(this);
                var id   = $btn.data('id');
                if (!confirm('¿Establecer esta plantilla como predeterminada?')) return;
                btnLoading($btn);
                ajaxPost('aura_cert_set_default', { id: id }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error.');
                        btnReset($btn);
                    }
                });
            });

            // Activar / Desactivar plantilla
            $(document).on('click', '.aura-cert-toggle-active-btn', function () {
                var $btn = $(this);
                var id   = $btn.data('id');
                btnLoading($btn);
                ajaxPost('aura_cert_toggle_active', { id: id }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error.');
                        btnReset($btn);
                    }
                });
            });

            // Eliminar plantilla
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
                        alert(res.data || 'Error al eliminar.');
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

            // WP Media para imagen de firma
            $(document).on('click', '#aura-signer-signature-btn', function (e) {
                e.preventDefault();
                var frame = wp.media({
                    title  : 'Seleccionar imagen de firma',
                    button : { text: 'Usar esta imagen' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#aura-signer-signature-url').val(att.url);
                    $('#aura-signer-signature-preview').attr('src', att.url).show();
                });
                frame.open();
            });

            // Guardar firmante
            $(document).on('submit', '#aura-signer-form', function (e) {
                e.preventDefault();
                var $btn = $(this).find('button[type="submit"]');
                btnLoading($btn);
                ajaxPost('aura_cert_save_signer', {
                    id            : signersPage.editingId,
                    name          : $('#aura-signer-name').val(),
                    title         : $('#aura-signer-title').val(),
                    signature_url : $('#aura-signer-signature-url').val(),
                    is_active     : $('#aura-signer-active').is(':checked') ? 1 : 0
                }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error al guardar firmante.');
                        btnReset($btn);
                    }
                }).fail(function () {
                    alert('Error de conexión.');
                    btnReset($btn);
                });
            });

            // Editar firmante
            $(document).on('click', '.aura-signer-edit-btn', function () {
                var id   = $(this).data('id');
                var $row = $(this).closest('tr');
                signersPage.editingId = id;
                $('#aura-signer-id-field').val(id);
                $('#aura-signer-name').val($row.data('name'));
                $('#aura-signer-title').val($row.data('title'));
                var sigUrl = $row.data('signature-url');
                $('#aura-signer-signature-url').val(sigUrl);
                if (sigUrl) {
                    $('#aura-signer-signature-preview').attr('src', sigUrl).show();
                } else {
                    $('#aura-signer-signature-preview').hide();
                }
                $('html, body').animate({ scrollTop: signersPage.$form.offset().top - 40 }, 300);
            });

            // Cancelar edición
            $(document).on('click', '#aura-signer-cancel', function () {
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
                        alert(res.data || 'Error.');
                    }
                });
            });

            // Activar / desactivar firmante
            $(document).on('click', '.aura-signer-toggle-btn', function () {
                var $btn = $(this);
                var id   = $btn.data('id');
                ajaxPost('aura_cert_toggle_signer', { id: id }).done(function (res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error.');
                    }
                });
            });

            // Ordenamiento por drag
            if (this.$table.length && $.fn.sortable) {
                this.$table.sortable({
                    handle  : '.aura-drag-handle',
                    update  : function () {
                        var ids = [];
                        signersPage.$table.find('tr').each(function () {
                            ids.push($(this).data('id'));
                        });
                        ajaxPost('aura_cert_reorder_signers', { ids: ids });
                    }
                });
            }
        },

        resetForm: function () {
            this.editingId = 0;
            this.$form[0].reset();
            $('#aura-signer-signature-preview').hide();
        }
    };

    /* ──────────────────────────────────────────────
       BULK ISSUE
       ────────────────────────────────────────────── */
    var bulkIssue = {
        pollInterval : null,
        batchId      : null,

        init: function () {
            var $page = $('#aura-bulk-issue-page');
            if (!$page.length) return;

            // Contador de seleccionados
            $(document).on('change', '.aura-bulk-cert-check, #aura-bulk-check-all', function () {
                if ($(this).is('#aura-bulk-check-all')) {
                    var checked = $(this).is(':checked');
                    $('.aura-bulk-cert-check').prop('checked', checked);
                }
                var count = $('.aura-bulk-cert-check:checked').length;
                $('#aura-bulk-selected-count').text(count + ' seleccionados');
                $('#aura-bulk-issue-btn').prop('disabled', count === 0);
            });

            // Select / Deselect All
            $(document).on('click', '#aura-bulk-select-all', function () {
                $('.aura-bulk-cert-check').prop('checked', true);
                $('#aura-bulk-check-all').prop('checked', true);
                $('#aura-bulk-selected-count').text($('.aura-bulk-cert-check').length + ' seleccionados');
                $('#aura-bulk-issue-btn').prop('disabled', false);
            });
            $(document).on('click', '#aura-bulk-deselect-all', function () {
                $('.aura-bulk-cert-check, #aura-bulk-check-all').prop('checked', false);
                $('#aura-bulk-selected-count').text('0 seleccionados');
                $('#aura-bulk-issue-btn').prop('disabled', true);
            });

            // Iniciar emisión
            $(document).on('click', '#aura-bulk-issue-btn', function () {
                var items = [];
                $('.aura-bulk-cert-check:checked').each(function () {
                    items.push({
                        student_id    : $(this).data('student-id'),
                        enrollment_id : $(this).data('enrollment-id')
                    });
                });
                var template_id = $('#aura-bulk-template-id').val();
                if (!template_id) {
                    alert('Selecciona una plantilla antes de continuar.');
                    return;
                }
                if (!items.length) return;

                var $btn = $(this);
                btnLoading($btn);
                ajaxPost('aura_cert_queue_bulk', {
                    template_id : template_id,
                    items       : JSON.stringify(items)
                }).done(function (res) {
                    if (res.success) {
                        bulkIssue.batchId = res.data.batch_id;
                        bulkIssue.startPolling(res.data.total);
                        $btn.hide();
                    } else {
                        alert(res.data || 'Error al encolar.');
                        btnReset($btn);
                    }
                }).fail(function () {
                    alert('Error de conexión.');
                    btnReset($btn);
                });
            });
        },

        startPolling: function (total) {
            var $bar     = $('#aura-bulk-progress-bar');
            var $wrap    = $('#aura-bulk-progress-wrap');
            var $counter = $('#aura-bulk-progress-counter');
            $wrap.show();

            this.pollInterval = setInterval(function () {
                ajaxPost('aura_cert_bulk_status', { batch_id: bulkIssue.batchId }).done(function (res) {
                    if (res.success) {
                        var d = res.data;
                        var pct = calcPercent(d.done, d.total);
                        $bar.css('width', pct + '%').text(pct + '%');
                        $counter.text(d.done + ' / ' + d.total);
                        if (d.done >= d.total) {
                            clearInterval(bulkIssue.pollInterval);
                            $bar.css('background', '#16a34a');
                            setTimeout(function () { location.reload(); }, 1500);
                        }
                    }
                });
            }, 3000);
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

            // Generar reporte
            $(document).on('click', '#aura-cert-report-generate', function () {
                var $btn = $(this);
                btnLoading($btn);
                ajaxPost('aura_cert_generate_report', {
                    report_type : $('#aura-cert-report-type').val(),
                    date_from   : $('#aura-cert-date-from').val(),
                    date_to     : $('#aura-cert-date-to').val()
                }).done(function (res) {
                    btnReset($btn);
                    if (res.success) {
                        reports.lastData = res.data;
                        reports.renderTable(res.data.rows, res.data.columns);
                        $('#aura-cert-report-excel, #aura-cert-report-pdf').prop('disabled', false);
                    } else {
                        alert(res.data || 'Error al generar.');
                    }
                }).fail(function () {
                    btnReset($btn);
                    alert('Error de conexión.');
                });
            });

            // Exportar Excel
            $(document).on('click', '#aura-cert-report-excel', function () {
                reports.submitExport('excel');
            });

            // Exportar PDF
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
            var html = '<table class="widefat striped"><thead><tr>';
            cols.forEach(function (c) { html += '<th>' + c + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.forEach(function (row) {
                html += '<tr>';
                Object.values(row).forEach(function (v) { html += '<td>' + (v || '-') + '</td>'; });
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

            // WP Media para logo de organización
            $(document).on('click', '#aura-cert-logo-btn', function (e) {
                e.preventDefault();
                var frame = wp.media({
                    title   : 'Seleccionar logo',
                    button  : { text: 'Usar este logo' },
                    library : { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#aura-cert-org-logo').val(att.url);
                    $('#aura-cert-logo-preview').attr('src', att.url).show();
                });
                frame.open();
            });

            // Preview URL del slug de verificación
            $(document).on('input', '#aura-cert-verify-slug', function () {
                var slug = $(this).val().trim() || 'verificar-certificado';
                var base = window.location.origin;
                $('#aura-cert-verify-url-preview').text(base + '/' + slug + '/CEM-2025-0001');
            });

            // Guardar ajustes (AJAX)
            $form.on('submit', function (e) {
                e.preventDefault();
                var $btn = $form.find('button[type="submit"]');
                btnLoading($btn);

                var data = {};
                $form.serializeArray().forEach(function (f) {
                    data[f.name] = f.value;
                });
                // Checkboxes desmarcados no aparecen en serializeArray
                $form.find('input[type="checkbox"]').each(function () {
                    if (!$(this).is(':checked')) {
                        data[$(this).attr('name')] = '0';
                    }
                });

                ajaxPost('aura_cert_save_settings', data).done(function (res) {
                    btnReset($btn);
                    var msg = typeof res.data === 'string' ? res.data : (res.data && res.data.message ? res.data.message : null);
                    if (res.success) {
                        var $notice = $('<div class="notice notice-success is-dismissible"><p>' + (msg || 'Ajustes guardados.') + '</p></div>');
                        $form.find('.aura-ajustes-notices').html($notice);
                        setTimeout(function () { $notice.fadeOut(); }, 4000);
                    } else {
                        alert(msg || 'Error al guardar.');
                    }
                }).fail(function () {
                    btnReset($btn);
                    alert('Error de conexión.');
                });
            });

            // Tabs
            $(document).on('click', '.nav-tab', function (e) {
                e.preventDefault();
                var target = $(this).data('tab');
                if (!target) return;
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.aura-tab-content').hide();
                $('#aura-tab-' + target).show();
                // Sincroniza el campo oculto para que el AJAX sepa qué pestaña es
                $('#aura-cert-active-tab').val(target);
            });
        }
    };

    /* ──────────────────────────────────────────────
       MODAL EMITIR CERTIFICADO
       ────────────────────────────────────────────── */
    var issueModal = {
        $modal : null,

        init: function () {
            this.$modal = $('#aura-issue-cert-modal');
            if (!this.$modal.length) return;

            var $form    = this.$modal.find('#aura-issue-cert-form');
            var $confirm = this.$modal.find('#aura-issue-cert-confirm');

            // Abrir modal
            $(document).on('click', '.aura-cert-issue-btn', function () {
                issueModal.$modal.find('#aura-issue-student-id').val($(this).data('student-id'));
                issueModal.$modal.find('#aura-issue-enrollment-id').val($(this).data('enrollment-id'));
                issueModal.$modal.show();
            });

            // Cerrar modal
            this.$modal.on('click', '.aura-modal-close, .aura-modal-overlay', function () {
                issueModal.$modal.hide();
            });

            // Emitir
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
                        alert(res.data || 'Error al emitir certificado.');
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
    $(document).ready(function () {
        revokeModal.init();
        templatesList.init();
        signersPage.init();
        bulkIssue.init();
        reports.init();
        settingsPage.init();
        issueModal.init();
    });

}(jQuery));
