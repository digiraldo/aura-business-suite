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
        importType  : 'transactions',
        headers     : [],
        totalRows   : 0,
        filename    : '',
        validStats  : null,
        batchId     : null,
        errorLogData: [],
    };

    function getSelectedImportType() {
        return ($('#aura-import-type').val() || 'transactions').toString();
    }

    function getFieldsForType(type) {
        var fieldsMap = (auraImport.fieldsByType || {});
        return fieldsMap[type] || fieldsMap.transactions || [];
    }

    var groupMeta = {
        required: {
            title: '1. Campos Obligatorios (*)',
            desc: 'Estos campos son indispensables para procesar las transacciones.',
            icon: 'dashicons-star-filled',
            className: 'group-required'
        },
        users: {
            title: '2. Usuarios y Aprobaciones (WordPress)',
            desc: 'Asignación de autores, aprobadores y usuarios relacionados.',
            icon: 'dashicons-admin-users',
            className: 'group-users'
        },
        payment: {
            title: '3. Cuentas y Métodos de Pago',
            desc: 'Cuentas bancarias de origen/destino, forma de pago y referencias.',
            icon: 'dashicons-money-alt',
            className: 'group-payment'
        },
        details: {
            title: '4. Información Adicional y Clasificación',
            desc: 'Conceptos, descripciones, centro de costos/áreas y etiquetas.',
            icon: 'dashicons-tag',
            className: 'group-details'
        }
    };

    function buildMappingGrid(headers, autoMapping, importType, previewRows) {
        state.headers      = headers || [];
        state.autoMapping  = autoMapping || {};
        state.previewRows  = previewRows || [];
        var fields         = getFieldsForType(importType);
        var $container     = $('#aura-mapping-grid').empty();

        // Agrupar campos
        var grouped = {};
        fields.forEach(function (f) {
            var g = f.group || (f.required ? 'required' : 'details');
            if (!grouped[g]) grouped[g] = [];
            grouped[g].push(f);
        });

        // Orden de grupos preferido
        var groupOrder = ['required', 'users', 'payment', 'details'];
        groupOrder.forEach(function (gKey) {
            var gFields = grouped[gKey];
            if (!gFields || !gFields.length) return;

            var gInfo = groupMeta[gKey] || {
                title: gKey.toUpperCase(),
                desc: '',
                icon: 'dashicons-admin-generic',
                className: 'group-' + gKey
            };

            var $groupWrapper = $('<div class="aura-mapping-group ' + gInfo.className + '"></div>');
            var groupHeaderHtml = [
                '<div class="aura-group-header">',
                    '<span class="dashicons ' + gInfo.icon + '"></span>',
                    '<h4>' + escHtml(gInfo.title) + '</h4>',
                    '<span class="aura-group-desc">' + escHtml(gInfo.desc) + '</span>',
                '</div>'
            ].join('');
            $groupWrapper.append(groupHeaderHtml);

            var $list = $('<div class="aura-mapping-list"></div>');

            gFields.forEach(function (field) {
                var fieldIcon = field.icon || 'dashicons-admin-generic';
                var reqBadgeClass = field.required ? 'req' : 'opt';
                var reqBadgeText  = field.required ? 'Obligatorio' : 'Opcional';

                var cardHtml = [
                    '<div class="aura-mapping-card" id="card-' + escHtml(field.key) + '">',
                        '<div class="aura-field-info-wrap">',
                            '<span class="aura-field-icon dashicons ' + fieldIcon + '"></span>',
                            '<div class="aura-field-text">',
                                '<div class="aura-field-title-line">',
                                    '<span class="aura-field-title">' + escHtml(field.label || field.key) + '</span>',
                                    '<span class="aura-req-badge ' + reqBadgeClass + '">' + reqBadgeText + '</span>',
                                '</div>',
                                '<span class="aura-field-desc-text">' + escHtml(field.desc || '') + '</span>',
                            '</div>',
                        '</div>',
                        '<div class="aura-mapping-arrow-col">→</div>',
                        '<div class="aura-field-select-col">',
                            '<select class="aura-col-select regular-text" data-field="' + escHtml(field.key) + '" data-required="' + (field.required ? '1' : '0') + '" id="map-' + escHtml(field.key) + '">',
                                '<option value="">— No asignar (Ignorar columna) —</option>',
                            '</select>',
                            '<div class="aura-sample-preview" id="sample-' + escHtml(field.key) + '">',
                                '<span class="dashicons dashicons-visibility"></span> <em>Muestra: (no asignada)</em>',
                            '</div>',
                        '</div>',
                        '<div class="aura-status-col" id="status-wrap-' + escHtml(field.key) + '">',
                        '</div>',
                    '</div>'
                ].join('');

                var $card = $(cardHtml);
                var $select = $card.find('.aura-col-select');

                headers.forEach(function (h, idx) {
                    $select.append($('<option></option>').val(idx).text('Columna: ' + h));
                });

                $list.append($card);
            });

            $groupWrapper.append($list);
            $container.append($groupWrapper);
        });

        // Aplicar auto-mapping inicial
        if (autoMapping) {
            Object.keys(autoMapping).forEach(function (field) {
                $('#map-' + field).val(autoMapping[field]);
            });
        }

        // Actualizar visualmente cada fila y la barra de estado
        fields.forEach(function (field) {
            refreshFieldCardVisual(field.key);
        });
        updateMappingStatusBar();
    }

    function refreshFieldCardVisual(fieldKey) {
        var $select   = $('#map-' + fieldKey);
        if (!$select.length) return;

        var val       = $select.val();
        var isReq     = $select.data('required') === 1 || $select.data('required') === '1';
        var $card     = $('#card-' + fieldKey);
        var $sample   = $('#sample-' + fieldKey);
        var $status   = $('#status-wrap-' + fieldKey);

        if (val !== '' && val !== null && val !== undefined) {
            var colIdx = parseInt(val, 10);
            var sampleText = '';
            if (state.previewRows && state.previewRows.length > 0 && state.previewRows[0][colIdx] !== undefined) {
                sampleText = String(state.previewRows[0][colIdx]);
            }
            if (!sampleText) sampleText = '(Vacío en fila 1)';

            $sample.html('<span class="dashicons dashicons-visibility"></span> Muestra (fila 1): <strong>' + escHtml(sampleText) + '</strong>');
            $card.addClass('is-mapped').removeClass('is-required-missing');
            $status.html('<span class="aura-map-pill status-mapped"><span class="dashicons dashicons-yes"></span> ' + (isReq ? 'Mapeado' : 'Asignado') + '</span>');
        } else {
            $sample.html('<span class="dashicons dashicons-visibility"></span> <em>Muestra: (no asignada)</em>');
            $card.removeClass('is-mapped');
            if (isReq) {
                $card.addClass('is-required-missing');
                $status.html('<span class="aura-map-pill status-missing"><span class="dashicons dashicons-warning"></span> Falta asignar</span>');
            } else {
                $card.removeClass('is-required-missing');
                $status.html('<span class="aura-map-pill status-ignored">Ignorado</span>');
            }
        }
    }

    function updateMappingStatusBar() {
        var fields = getFieldsForType(state.importType);
        var totalReq = 0;
        var mappedReq = 0;
        var mappedOpt = 0;

        fields.forEach(function (f) {
            var val = $('#map-' + f.key).val();
            var isMapped = (val !== '' && val !== null && val !== undefined);
            if (f.required) {
                totalReq++;
                if (isMapped) mappedReq++;
            } else {
                if (isMapped) mappedOpt++;
            }
        });

        var allReqMapped = (mappedReq >= totalReq);
        var textHtml = '';
        if (allReqMapped) {
            textHtml = '<span style="color:#15803d;"><span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span> <strong>¡Excelente!</strong> Todos los ' + totalReq + ' campos obligatorios están asignados (' + mappedOpt + ' opcionales).</span>';
            $('#aura-mapping-status-bar').css({ 'background': '#f0fdf4', 'border-color': '#bbf7d0', 'color': '#166534' });
        } else {
            var missingCount = totalReq - mappedReq;
            textHtml = '<span style="color:#b91c1c;"><span class="dashicons dashicons-warning" style="vertical-align:middle;"></span> <strong>Atención:</strong> Faltan ' + missingCount + ' campos obligatorios por asignar (' + mappedReq + ' de ' + totalReq + ' listos).</span>';
            $('#aura-mapping-status-bar').css({ 'background': '#fff1f2', 'border-color': '#fecdd3', 'color': '#9f1239' });
        }
        $('#aura-mapping-count-summary').html(textHtml);
    }

    // Eventos interactivos en cambios de selector
    $(document).on('change', '.aura-col-select', function () {
        var fieldKey = $(this).data('field');
        refreshFieldCardVisual(fieldKey);
        updateMappingStatusBar();
    });

    // Botón re-detectar automático
    $(document).on('click', '#aura-reset-mapping-btn', function () {
        $('.aura-col-select').val('');
        if (state.autoMapping) {
            Object.keys(state.autoMapping).forEach(function (field) {
                $('#map-' + field).val(state.autoMapping[field]);
            });
        }
        var fields = getFieldsForType(state.importType);
        fields.forEach(function (f) {
            refreshFieldCardVisual(f.key);
        });
        updateMappingStatusBar();
    });

    // Botón limpiar asignaciones
    $(document).on('click', '#aura-clear-mapping-btn', function () {
        $('.aura-col-select').val('');
        var fields = getFieldsForType(state.importType);
        fields.forEach(function (f) {
            refreshFieldCardVisual(f.key);
        });
        updateMappingStatusBar();
    });

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

    $('#aura-import-type').on('change', function () {
        state.importType = getSelectedImportType();
        $('#aura-download-template').trigger('focus');
    });

    $('#aura-download-template').on('click', function (e) {
        e.preventDefault();
        var type = getSelectedImportType();
        var url = auraImport.ajaxurl + '?action=aura_download_import_template&nonce=' + encodeURIComponent(auraImport.nonce) + '&type=' + encodeURIComponent(type);
        window.open(url, '_blank');
    });

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
        fd.append('import_type', getSelectedImportType());
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
            state.importType = (res.data.import_type || getSelectedImportType());
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

        buildMappingGrid(data.headers || [], data.auto_mapping || {}, state.importType, data.preview || []);
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

        // Validar campos requeridos según tipo
        var required = getFieldsForType(state.importType).filter(function (f) { return !!f.required; }).map(function (f) { return f.key; });
        for (var i = 0; i < required.length; i++) {
            if (!Object.prototype.hasOwnProperty.call(mapping, required[i])) {
                showError(2, auraImport.txt.map_required);
                return;
            }
        }

        var $btn = $(this).prop('disabled', true).text(auraImport.txt.validating + '…');

        $.post(auraImport.ajaxurl, {
            action : 'aura_validate_import',
            nonce  : auraImport.nonce,
            token  : state.token,
            import_type: state.importType,
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

    // ── Paso 3: Validación y Matching ─────────────────────────────────
    function buildStep3(data) {
        var html = '<div class="aura-stat-boxes">';
        html += statBox(data.total,   auraImport.txt.stat_total,   'total');
        html += statBox(data.valid,   auraImport.txt.stat_valid,   'valid');
        html += statBox(data.invalid, auraImport.txt.stat_invalid, 'invalid');
        html += statBox(data.warnings,auraImport.txt.stat_warnings,'warning');
        html += '</div>';
        $('#aura-validation-stats').html(html);

        // 1. Renderizar tabla de Categorías
        var catAnalysis = data.categories_analysis || [];
        var availCats   = data.available_categories || [];
        if (state.importType === 'transactions' && catAnalysis.length > 0) {
            var catTbody = '';
            $.each(catAnalysis, function (i, cat) {
                var badgeClass = cat.exists_in_db ? 'aura-badge-success' : 'aura-badge-warning';
                var badgeText  = cat.exists_in_db ? (auraImport.txt.found_in_db || 'Existe en BD') : (auraImport.txt.not_in_db || 'No existe en BD');
                
                var selectHtml = '<select class="aura-category-map-select regular-text" data-raw-category="' + escHtml(cat.name) + '">';
                selectHtml += '<option value="__create__"' + (!cat.exists_in_db ? ' selected' : '') + '>' + (auraImport.txt.create_auto || '+ Crear automáticamente en BD') + ' ("' + escHtml(cat.name) + '")</option>';
                
                $.each(availCats, function (j, c) {
                    var isSelected = (cat.matched_id && cat.matched_id == c.id) ? ' selected' : '';
                    selectHtml += '<option value="' + c.id + '"' + isSelected + '>' + escHtml(c.name) + ' (' + escHtml(c.type) + ')</option>';
                });
                selectHtml += '</select>';

                catTbody += '<tr>' +
                    '<td><strong>' + escHtml(cat.name) + '</strong></td>' +
                    '<td>' + cat.count + '</td>' +
                    '<td><span class="aura-badge ' + badgeClass + '">' + badgeText + '</span></td>' +
                    '<td>' + selectHtml + '</td>' +
                '</tr>';
            });
            $('#aura-category-matching-table tbody').html(catTbody);
            $('#aura-category-matching-section').show();
        } else {
            $('#aura-category-matching-section').hide();
        }

        // 2. Renderizar tabla de Usuarios WordPress
        var userAnalysis = data.users_analysis || [];
        var availUsers   = data.available_users || [];
        if (state.importType === 'transactions' && userAnalysis.length > 0) {
            var userTbody = '';
            $.each(userAnalysis, function (i, u) {
                var selectHtml = '<select class="aura-user-map-select regular-text" data-raw-user="' + escHtml(u.name) + '" data-field-type="' + escHtml(u.field_type) + '">';
                selectHtml += '<option value="">-- ' + (auraImport.txt.unmatched_user || 'Sin asignar') + ' --</option>';
                
                $.each(availUsers, function (j, usr) {
                    var isSelected = (u.matched_user_id && u.matched_user_id == usr.id) ? ' selected' : '';
                    selectHtml += '<option value="' + usr.id + '"' + isSelected + '>' + escHtml(usr.display_name) + ' (' + escHtml(usr.user_login) + ')</option>';
                });
                selectHtml += '</select>';

                userTbody += '<tr>' +
                    '<td><strong>' + escHtml(u.name) + '</strong></td>' +
                    '<td><span class="aura-badge aura-badge-info">' + escHtml(u.field_label || u.field_type) + '</span></td>' +
                    '<td>' + u.count + '</td>' +
                    '<td>' + selectHtml + '</td>' +
                '</tr>';
            });
            $('#aura-user-matching-table tbody').html(userTbody);
            $('#aura-user-matching-section').show();
        } else {
            $('#aura-user-matching-section').hide();
        }

        // 3. Selectores de usuarios por defecto
        if (state.importType === 'transactions' && availUsers.length > 0) {
            var currentUid = data.current_user_id || 0;
            var defCreatedHtml = '';
            var defApprovedHtml = '<option value="0">-- Mismo usuario creador --</option>';
            $.each(availUsers, function (i, usr) {
                var selCreated = (usr.id == currentUid) ? ' selected' : '';
                defCreatedHtml += '<option value="' + usr.id + '"' + selCreated + '>' + escHtml(usr.display_name) + ' (' + escHtml(usr.user_login) + ')</option>';
                defApprovedHtml += '<option value="' + usr.id + '">' + escHtml(usr.display_name) + ' (' + escHtml(usr.user_login) + ')</option>';
            });
            $('#aura-default-created-by').html(defCreatedHtml);
            $('#aura-default-approved-by').html(defApprovedHtml);
            $('#aura-default-users-section').show();
        } else {
            $('#aura-default-users-section').hide();
        }

        // Errores
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

        // Advertencias
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
        var labels = (auraImport.importTypeLabels || {});
        var singular = labels[state.importType] || auraImport.txt.default_record_label || 'registros';
        $('#aura-import-summary').html(
            '<p>' + auraImport.txt.ready_to_import.replace('%d', '<strong>' + n + '</strong>').replace('%s', singular) + '</p>'
        );
        $('#aura-execute-label').text(auraImport.txt.import_n.replace('%d', n).replace('%s', singular));

        var isTransactions = state.importType === 'transactions';
        $('.aura-options-grid').toggle(isTransactions);

        // Asegurar que los botones de acción se muestren y la barra de progreso esté oculta
        $('#aura-step4-actions').show();
        $('#aura-execute-btn').prop('disabled', false);
        $('#aura-exec-progress').hide();
        $('#aura-step4-error').hide();
        $('#aura-exec-bar').css('width', '0%');
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

        // Recolectar mapeo de categorías personalizado
        var categoryMapping = {};
        $('#aura-category-matching-table .aura-category-map-select').each(function () {
            var raw = $(this).data('raw-category');
            if (raw) {
                categoryMapping[raw] = $(this).val();
            }
        });

        // Recolectar mapeo de usuarios WP personalizado
        var userMapping = {};
        $('#aura-user-matching-table .aura-user-map-select').each(function () {
            var raw = $(this).data('raw-user');
            var fType = $(this).data('field-type');
            var val = $(this).val();
            if (raw && val) {
                if (fType) {
                    userMapping[fType + '_' + raw] = val;
                }
                userMapping[raw] = val;
            }
        });

        var options = {
            default_status       : $('input[name="default_status"]:checked').val() || 'pending',
            auto_create_category : $('input[name="auto_create_category"]:checked').val() || '0',
            duplicate_action     : $('input[name="duplicate_action"]:checked').val() || 'ask',
            category_mapping     : categoryMapping,
            user_mapping         : userMapping,
            default_created_by   : $('#aura-default-created-by').val() || '',
            default_approved_by  : $('#aura-default-approved-by').val() || '',
        };

        $.post(auraImport.ajaxurl, {
            action : 'aura_execute_import',
            nonce  : auraImport.nonce,
            token  : state.token,
            import_type: state.importType,
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

        var viewMap = auraImport.viewByType || {};
        var viewCfg = viewMap[state.importType] || viewMap.transactions || null;
        if (viewCfg && viewCfg.url) {
            var $viewBtn = $('#aura-view-transactions');
            var $label = $viewBtn.find('.aura-view-label');
            if (!$label.length) {
                $label = $('<span class="aura-view-label"></span>').appendTo($viewBtn);
            }
            $viewBtn.attr('href', viewCfg.url);
            $label.text(viewCfg.label || auraImport.txt.default_record_label);
            $('#aura-view-transactions').show();
        } else {
            $('#aura-view-transactions').hide();
        }

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
        state = {
            step        : 1,
            file        : null,
            token       : null,
            importType  : getSelectedImportType(),
            headers     : [],
            totalRows   : 0,
            filename    : '',
            validStats  : null,
            batchId     : null,
            errorLogData: [],
            mapping     : {},
        };
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
                    // Verificar si aún está dentro de 1 mes
                    var importDate = new Date(log.created_at.replace(' ', 'T'));
                    if (isNaN(importDate.getTime())) {
                        importDate = new Date(log.created_at);
                    }
                    var ageMs = Date.now() - importDate.getTime();
                    if (ageMs <= 30 * 24 * 60 * 60 * 1000) {
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
