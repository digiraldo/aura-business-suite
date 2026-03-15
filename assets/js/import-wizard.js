/**
 * Import Wizard JS – Fase 4, Item 4.2
 */
/* global auraImport, $ */
(function ($) {
    'use strict';

    // ── Estado del wizard ──────────────────────────────────────────────
    var state = {
        step        : 1,
        file        : null,
        token       : null,
        headers     : [],
        totalRows   : 0,
        filename    : '',
        validStats  : null,
        batchId     : null,
        errorLogData: [],
    };

    // ── Helpers ────────────────────────────────────────────────────────
    function showStep(n) {
        $('.aura-wizard-panel').removeClass('active');
        $('#aura-step-' + n).addClass('active');
        $('.aura-step').removeClass('active completed');
        for (var i = 1; i < n; i++) {
            $('[data-step="' + i + '"]').addClass('completed');
        }
        $('[data-step="' + n + '"]').addClass('active');
        state.step = n;
        $('html, body').animate({ scrollTop: $('.aura-wizard-steps').offset().top - 30 }, 200);
    }

    function showResult() {
        $('.aura-wizard-panel').removeClass('active');
        $('#aura-step-result').addClass('active');
        $('.aura-step').addClass('completed');
        $('html, body').animate({ scrollTop: 0 }, 200);
    }

    function showError(stepId, msg) {
        var $el = $('#aura-step' + stepId + '-error');
        $el.html('<span class="dashicons dashicons-warning"></span> ' + msg).show();
        setTimeout(function () { $el.hide(); }, 8000);
    }

    function progressAnimate($bar, duration, endPct) {
        var current = 0;
        var interval = setInterval(function () {
            current += 3;
            if (current >= endPct) { clearInterval(interval); current = endPct; }
            $bar.css('width', current + '%');
        }, duration / (endPct / 3));
    }

    // ── Paso 1: Seleccionar archivo ────────────────────────────────────
    $('#aura-select-file-btn').on('click', function () {
        $('#aura-import-file').trigger('click');
    });

    $('#aura-import-file').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        state.file = file;
        $('#aura-selected-filename').text(file.name);
        $('#aura-selected-file').show();
        $('#aura-dropzone').addClass('has-file');
        $('#aura-upload-btn').prop('disabled', false);
    });

    // Drag & drop
    var $dropzone = $('#aura-dropzone');
    $dropzone.on('dragover', function (e) { e.preventDefault(); $(this).addClass('drag-over'); });
    $dropzone.on('dragleave drop', function () { $(this).removeClass('drag-over'); });
    $dropzone.on('drop', function (e) {
        e.preventDefault();
        var file = e.originalEvent.dataTransfer.files[0];
        if (file) {
            state.file = file;
            $('#aura-selected-filename').text(file.name);
            $('#aura-selected-file').show();
            $('#aura-dropzone').addClass('has-file');
            $('#aura-upload-btn').prop('disabled', false);
        }
    });

    $('#aura-remove-file').on('click', function () {
        state.file = null;
        $('#aura-import-file').val('');
        $('#aura-selected-file').hide();
        $('#aura-dropzone').removeClass('has-file');
        $('#aura-upload-btn').prop('disabled', true);
    });

    // Subir archivo
    $('#aura-upload-btn').on('click', function () {
        if (!state.file) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#aura-upload-progress').show();
        progressAnimate($('#aura-upload-progress .aura-progress-fill'), 3000, 85);

        var fd = new FormData();
        fd.append('action', 'aura_upload_import_file');
        fd.append('nonce', auraImport.nonce);
        fd.append('import_file', state.file);

        $.ajax({
            url        : auraImport.ajaxurl,
            method     : 'POST',
            data       : fd,
            processData: false,
            contentType: false,
        }).done(function (res) {
            $('#aura-upload-progress .aura-progress-fill').css('width', '100%');
            setTimeout(function () { $('#aura-upload-progress').hide(); }, 400);
            if (!res.success) {
                showError(1, res.data.message || auraImport.txt.error_generic);
                $btn.prop('disabled', false);
                return;
            }
            state.token    = res.data.token;
            state.headers  = res.data.headers;
            state.totalRows= res.data.total_rows;
            state.filename = res.data.filename;
            buildStep2(res.data);
            showStep(2);
        }).fail(function () {
            showError(1, auraImport.txt.error_generic);
            $btn.prop('disabled', false);
            $('#aura-upload-progress').hide();
        });
    });

    // ── Paso 2: Mapear columnas ────────────────────────────────────────
    function buildStep2(data) {
        // Resumen
        $('#aura-file-summary').html(
            '<p><strong>' + auraImport.txt.file_label + ':</strong> ' + escHtml(data.filename) +
            ' &nbsp;|&nbsp; <strong>' + auraImport.txt.rows_label + ':</strong> ' + data.total_rows + '</p>'
        );

        // Encabezado tabla preview
        var $thead = $('#aura-preview-head').empty();
        var $hRow = $('<tr>');
        $.each(data.headers, function (i, h) { $hRow.append($('<th>').text(h)); });
        $thead.append($hRow);

        // Filas preview
        var $tbody = $('#aura-preview-body').empty();
        $.each(data.preview, function (i, row) {
            var $tr = $('<tr>');
            $.each(row, function (j, cell) { $tr.append($('<td>').text(cell)); });
            $tbody.append($tr);
        });

        // Opciones en selects de mapeo
        var $selects = $('.aura-col-select');
        $selects.each(function () {
            var $select = $(this);
            $select.find('option:not(:first)').remove();
            $.each(data.headers, function (i, h) {
                $select.append($('<option>').val(i).text(h));
            });
            $select.val(''); // reset
        });

        // Aplicar auto-mapping
        if (data.auto_mapping) {
            $.each(data.auto_mapping, function (field, colIdx) {
                $('#map-' + field).val(colIdx);
            });
        }
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    $('#aura-back-1').on('click', function () { showStep(1); });

    $('#aura-validate-btn').on('click', function () {
        var mapping = {};
        $('.aura-col-select').each(function () {
            var field = $(this).data('field');
            var val   = $(this).val();
            if (val !== '') mapping[field] = val;
        });

        // Validar campos requeridos
        var required = ['transaction_date', 'transaction_type', 'category_id', 'amount'];
        for (var i = 0; i < required.length; i++) {
            if (!mapping.hasOwnProperty(required[i])) {
                showError(2, auraImport.txt.map_required);
                return;
            }
        }

        var $btn = $(this).prop('disabled', true).text(auraImport.txt.validating + '…');

        $.post(auraImport.ajaxurl, {
            action : 'aura_validate_import',
            nonce  : auraImport.nonce,
            token  : state.token,
            mapping: mapping,
        }).done(function (res) {
            $btn.prop('disabled', false).text(auraImport.txt.validate_btn);
            if (!res.success) {
                showError(2, res.data.message || auraImport.txt.error_generic);
                return;
            }
            state.validStats = res.data;
            state.mapping    = mapping;
            buildStep3(res.data);
            showStep(3);
        }).fail(function () {
            showError(2, auraImport.txt.error_generic);
            $btn.prop('disabled', false).text(auraImport.txt.validate_btn);
        });
    });

    // ── Paso 3: Validación ─────────────────────────────────────────────
    function buildStep3(data) {
        var html = '<div class="aura-stat-boxes">';
        html += statBox(data.total,   auraImport.txt.stat_total,   'total');
        html += statBox(data.valid,   auraImport.txt.stat_valid,   'valid');
        html += statBox(data.invalid, auraImport.txt.stat_invalid, 'invalid');
        html += statBox(data.warnings,auraImport.txt.stat_warnings,'warning');
        html += '</div>';
        $('#aura-validation-stats').html(html);

        if (data.errors && data.errors.length) {
            var errHtml = '';
            $.each(data.errors, function (i, e) {
                errHtml += '<div class="aura-error-row">' +
                    '<strong>' + auraImport.txt.row + ' ' + e.row + ':</strong> ' +
                    '<span class="dashicons dashicons-dismiss"></span> ' +
                    escHtml(e.errors.join('; ')) + '</div>';
            });
            $('#aura-errors-list').html(errHtml);
            $('#aura-errors-section').show();
        } else {
            $('#aura-errors-section').hide();
        }

        if (data.warn_list && data.warn_list.length) {
            var warnHtml = '<div class="aura-auto-cat-notice notice notice-warning inline"><p>' + auraImport.txt.auto_cat_note + '</p></div>';
            $.each(data.warn_list, function (i, w) {
                warnHtml += '<div class="aura-warn-row">' +
                    '<strong>' + auraImport.txt.row + ' ' + w.row + ':</strong> ' +
                    '<span class="dashicons dashicons-info-outline"></span> ' +
                    escHtml(w.warnings.join('; ')) + '</div>';
            });
            $('#aura-warnings-list').html(warnHtml);
            $('#aura-warnings-section').show();
        } else {
            $('#aura-warnings-section').hide();
        }

        // Deshabilitar "continuar" si no hay válidas
        if (data.valid === 0) {
            $('#aura-confirm-btn').prop('disabled', true).text(auraImport.txt.no_valid);
        } else {
            $('#aura-confirm-btn').prop('disabled', false);
        }
    }

    function statBox(val, label, type) {
        return '<div class="aura-stat-box ' + type + '"><span class="aura-stat-num">' + val + '</span><span class="aura-stat-label">' + label + '</span></div>';
    }

    $('#aura-back-2').on('click', function () { showStep(2); });

    $('#aura-confirm-btn').on('click', function () {
        buildStep4();
        showStep(4);
    });

    // ── Paso 4: Confirmar ──────────────────────────────────────────────
    function buildStep4() {
        var d = state.validStats;
        var n = d ? d.valid : '?';
        $('#aura-import-summary').html(
            '<p>' + auraImport.txt.ready_to_import.replace('%d', '<strong>' + n + '</strong>') + '</p>'
        );
        $('#aura-execute-label').text(auraImport.txt.import_n.replace('%d', n));
    }

    // Radio styling
    $(document).on('change', 'input[type=radio]', function () {
        var name = $(this).attr('name');
        $('input[name="' + name + '"]').closest('.aura-radio-option').removeClass('selected');
        $(this).closest('.aura-radio-option').addClass('selected');
    });

    $('#aura-back-3').on('click', function () { showStep(3); });

    $('#aura-execute-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        $('#aura-step4-actions').hide();
        $('#aura-exec-progress').show();
        progressAnimate($('#aura-exec-bar'), 8000, 90);
        $('#aura-exec-progress-text').text(auraImport.txt.importing);

        var options = {
            default_status       : $('input[name="default_status"]:checked').val() || 'pending',
            auto_create_category : $('input[name="auto_create_category"]:checked').val() || '0',
            duplicate_action     : $('input[name="duplicate_action"]:checked').val() || 'ask',
        };

        $.post(auraImport.ajaxurl, {
            action : 'aura_execute_import',
            nonce  : auraImport.nonce,
            token  : state.token,
            mapping: state.mapping,
            options: options,
        }).done(function (res) {
            $('#aura-exec-bar').css('width', '100%');
            if (!res.success) {
                $('#aura-exec-progress').hide();
                showError(4, res.data.message || auraImport.txt.error_generic);
                $('#aura-step4-actions').show();
                $btn.prop('disabled', false);
                return;
            }
            state.batchId     = res.data.batch_id;
            state.errorLogData = res.data.error_log || [];
            setTimeout(function () { buildResult(res.data); showResult(); }, 500);
        }).fail(function () {
            $('#aura-exec-progress').hide();
            showError(4, auraImport.txt.error_generic);
            $('#aura-step4-actions').show();
            $btn.prop('disabled', false);
        });
    });

    // ── Resultado ──────────────────────────────────────────────────────
    function buildResult(data) {
        var html = '<div class="aura-stat-boxes">';
        html += statBox(data.imported, auraImport.txt.stat_imported, 'valid');
        html += statBox(data.failed,   auraImport.txt.stat_failed,   data.failed > 0 ? 'invalid' : 'total');
        html += '</div>';
        $('#aura-result-stats').html(html);

        // Botón descargar log de errores
        if (data.error_log && data.error_log.length) {
            $('#aura-download-error-log').show();
        }

        // Botón rollback (siempre disponible para admin o propietario)
        $('#aura-rollback-btn').show().data('batch-id', data.batch_id);

        // Recargar historial
        loadHistory();
    }

    // Descargar log de errores como CSV
    $('#aura-download-error-log').on('click', function () {
        if (!state.errorLogData.length) return;
        var csv = 'Fila,Motivo\n';
        $.each(state.errorLogData, function (i, e) {
            csv += e.row + ',"' + (e.reason || '').replace(/"/g, '""') + '"\n';
        });
        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = 'errores-importacion.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // Rollback
    $('#aura-rollback-btn').on('click', function () {
        if (!confirm(auraImport.txt.confirm_rollback)) return;
        var $btn     = $(this).prop('disabled', true);
        var batchId  = $(this).data('batch-id') || state.batchId;

        $.post(auraImport.ajaxurl, {
            action  : 'aura_rollback_import',
            nonce   : auraImport.nonce,
            batch_id: batchId,
        }).done(function (res) {
            if (!res.success) {
                alert(res.data.message || auraImport.txt.error_generic);
                $btn.prop('disabled', false);
                return;
            }
            $('#aura-result-title').text(auraImport.txt.rollback_done.replace('%d', res.data.reverted));
            $btn.hide();
            $('#aura-result-icon .dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-undo');
            loadHistory();
        }).fail(function () {
            alert(auraImport.txt.error_generic);
            $btn.prop('disabled', false);
        });
    });

    // Importar otro archivo
    $('#aura-import-another').on('click', function () {
        state = { step: 1, file: null, token: null, headers: [], totalRows: 0, filename: '', validStats: null, batchId: null, errorLogData: [] };
        $('#aura-import-file').val('');
        $('#aura-selected-file').hide();
        $('#aura-dropzone').removeClass('has-file');
        $('#aura-upload-btn').prop('disabled', true);
        showStep(1);
    });

    // ── Historial de importaciones ─────────────────────────────────────
    function loadHistory() {
        $.post(auraImport.ajaxurl, {
            action: 'aura_import_log_list',
            nonce : auraImport.nonce,
        }).done(function (res) {
            if (!res.success || !res.data.logs.length) {
                $('#aura-import-history').html('<p class="description">' + auraImport.txt.no_history + '</p>');
                return;
            }
            var html = '<table class="widefat striped"><thead><tr>' +
                '<th>' + auraImport.txt.h_date + '</th>' +
                '<th>' + auraImport.txt.h_file + '</th>' +
                '<th>' + auraImport.txt.h_total + '</th>' +
                '<th>' + auraImport.txt.h_imported + '</th>' +
                '<th>' + auraImport.txt.h_failed + '</th>' +
                '<th>' + auraImport.txt.h_status + '</th>' +
                '<th>' + auraImport.txt.h_actions + '</th>' +
                '</tr></thead><tbody>';

            $.each(res.data.logs, function (i, log) {
                var statusBadge = log.status === 'rolled_back'
                    ? '<span class="aura-badge rolled-back">' + auraImport.txt.rolled_back + '</span>'
                    : '<span class="aura-badge completed">' + auraImport.txt.completed + '</span>';

                var rollbackBtn;
                if (log.status === 'rolled_back') {
                    rollbackBtn = '—';
                } else {
                    // Verificar si aún está dentro de las 24 horas
                    var importDate = new Date(log.created_at.replace(' ', 'T') + 'Z');
                    var ageMs = Date.now() - importDate.getTime();
                    if (ageMs <= 86400000) {
                        rollbackBtn = '<button class="button-link aura-hist-rollback" data-batch="' + escHtml(log.batch_id) + '">' + auraImport.txt.undo + '</button>';
                    } else {
                        rollbackBtn = '<span class="description aura-expired-label">' + auraImport.txt.rollback_expired + '</span>';
                    }
                }

                html += '<tr>' +
                    '<td>' + escHtml(log.created_at) + '</td>' +
                    '<td>' + escHtml(log.filename) + '</td>' +
                    '<td>' + log.rows_total + '</td>' +
                    '<td>' + log.rows_imported + '</td>' +
                    '<td>' + log.rows_failed + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + rollbackBtn + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            $('#aura-import-history').html(html);
        });
    }

    // Rollback desde historial
    $(document).on('click', '.aura-hist-rollback', function () {
        if (!confirm(auraImport.txt.confirm_rollback)) return;
        var $btn    = $(this).prop('disabled', true);
        var batchId = $(this).data('batch');

        $.post(auraImport.ajaxurl, {
            action  : 'aura_rollback_import',
            nonce   : auraImport.nonce,
            batch_id: batchId,
        }).done(function (res) {
            if (!res.success) {
                alert(res.data.message || auraImport.txt.error_generic);
                $btn.prop('disabled', false);
                return;
            }
            loadHistory();
        }).fail(function () {
            alert(auraImport.txt.error_generic);
            $btn.prop('disabled', false);
        });
    });

    // Cargar historial al iniciar
    loadHistory();

}(jQuery));
