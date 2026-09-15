/**
 * Aura Business Suite — Integraciones Contables (Fase 5, Item 5.5)
 * Wizard de exportación en 4 pasos.
 */
(function ($) {
    'use strict';

    if (typeof auraIntegrationsConfig === 'undefined') return;

    const cfg = auraIntegrationsConfig;

    /* ===================================================================
     * ESTADO
     * =================================================================== */
    var state = {
        currentStep : 1,
        software    : 'quickbooks',
        dateFrom    : '',
        dateTo      : '',
        onlyApproved: false,
        excludedCats: [],
        mapping     : cfg.savedMapping || {},
        customCols  : [],
        exportResult: null,
    };

    /* ===================================================================
     * NAVEGACIÓN DEL WIZARD
     * =================================================================== */
    function goToStep(n) {
        // Validación antes de avanzar
        if (n > state.currentStep) {
            if (!validateStep(state.currentStep)) return;
        }

        // Ocultar panel actual
        $('#auraStep' + state.currentStep).removeClass('active');
        $('.aura-step-item[data-step="' + state.currentStep + '"]').removeClass('active').addClass('completed');

        // Activar nuevo panel
        state.currentStep = n;
        $('#auraStep' + n).addClass('active');
        $('.aura-step-item[data-step="' + n + '"]').addClass('active').removeClass('completed');

        // Marcar steps anteriores como completados
        for (var i = 1; i < n; i++) {
            $('.aura-step-item[data-step="' + i + '"]').addClass('completed');
        }

        // Acciones al llegar a un paso
        if (n === 3) loadMappingStep();
        if (n === 4) loadPreviewStep();
    }

    function validateStep(step) {
        if (step === 1) {
            state.software = $('input[name="aura_software"]:checked').val();
            state.customCols = [];
            $('input[name="aura_custom_cols[]"]:checked').each(function () {
                state.customCols.push($(this).val());
            });
            if (!state.software) {
                alert('Por favor selecciona un software destino.');
                return false;
            }
        }
        if (step === 2) {
            state.dateFrom     = $('#aura_date_from').val();
            state.dateTo       = $('#aura_date_to').val();
            state.onlyApproved = $('#aura_only_approved').is(':checked');
            state.excludedCats = [];
            $('#aura_excluded_cats option:selected').each(function () {
                state.excludedCats.push($(this).val());
            });
        }
        return true;
    }

    // Botones siguiente / anterior
    $(document).on('click', '.aura-step-next', function () {
        var to = parseInt($(this).data('to'), 10);
        goToStep(to);
    });

    $(document).on('click', '.aura-step-prev', function () {
        var to = parseInt($(this).data('to'), 10);
        goToStep(to);
    });

    // Nueva exportación
    $(document).on('click', '#auraNewExport', function () {
        state.exportResult = null;
        goToStep(1);
    });

    /* ===================================================================
     * PASO 1: mostrar/ocultar opción de columnas Excel y RFC SAT
     * =================================================================== */
    $(document).on('change', 'input[name="aura_software"]', function () {
        var sw = $(this).val();
        $('#auraExcelColsOpts').toggle(sw === 'excel');
    });

    /* ===================================================================
     * PASO 2: mostrar RFC para XML MX
     * =================================================================== */
    $(document).on('change', 'input[name="aura_software"]', function () {
        var sw = $(this).val();
        $('#auraSatOpts').toggle(sw === 'contabilidad_mx' || sw === 'sap');
    });

    /* ===================================================================
     * PASO 3: CARGAR MAPEO
     * =================================================================== */
    function loadMappingStep() {
        var $container = $('#auraMappingContainer');
        $container.html('<p class="aura-loading-msg"><span class="spinner is-active"></span> ' + cfg.i18n.loading + '</p>');

        $.ajax({
            url    : cfg.ajaxUrl,
            method : 'POST',
            data   : {
                action : 'aura_integrations_get_categories',
                nonce  : cfg.nonce,
            },
            success: function (res) {
                if (!res.success || !res.data || !res.data.length) {
                    $container.html('<p class="notice notice-warning">' +
                        'No se encontraron categorías activas.</p>');
                    return;
                }
                renderMappingTable(res.data);
            },
            error: function () {
                $container.html('<p class="notice notice-error">' + cfg.i18n.error + '</p>');
            }
        });
    }

    function renderMappingTable(categories) {
        var $container = $('#auraMappingContainer');
        var html = '<table class="widefat aura-mapping-table">';
        html += '<thead><tr>' +
                '<th>Categoría</th>' +
                '<th>Tipo</th>' +
                '<th>Número / Nombre de cuenta contable</th>' +
                '</tr></thead><tbody>';

        $.each(categories, function (i, cat) {
            var saved = state.mapping[cat.id] || '';
            var typeLabel, typeBadge;
            if (cat.type === 'income') {
                typeLabel = cfg.i18n.income;
                typeBadge = 'aura-badge-green';
            } else if (cat.type === 'expense') {
                typeLabel = cfg.i18n.expense;
                typeBadge = 'aura-badge-red';
            } else {
                typeLabel = cfg.i18n.both;
                typeBadge = 'aura-badge-gray';
            }

            html += '<tr>' +
                    '<td>' + escHtml(cat.name) + '</td>' +
                    '<td><span class="aura-badge ' + typeBadge + '">' + typeLabel + '</span></td>' +
                    '<td>' +
                    '<input type="text" class="aura-mapping-input regular-text" ' +
                    '       data-cat-id="' + cat.id + '" ' +
                    '       value="' + escHtml(saved) + '" ' +
                    '       placeholder="' + cfg.i18n.accountPlaceholder + '">' +
                    '</td>' +
                    '</tr>';
        });

        html += '</tbody></table>';
        $container.html(html);

        // Actualizar state.mapping cuando cambia un input
        $container.on('input change', '.aura-mapping-input', function () {
            var catId = $(this).data('cat-id');
            state.mapping[catId] = $(this).val().trim();
        });
    }

    /* ===================================================================
     * GUARDAR MAPEO
     * =================================================================== */
    $('#auraSaveMapping').on('click', function () {
        // Recoge todos los inputs antes de guardar
        $('.aura-mapping-input').each(function () {
            var catId = $(this).data('cat-id');
            state.mapping[catId] = $(this).val().trim();
        });

        var $btn    = $(this);
        var $status = $('#auraMappingStatus');

        $btn.prop('disabled', true).text(cfg.i18n.saving);
        $status.text('').removeClass('aura-status-ok aura-status-error');

        $.ajax({
            url   : cfg.ajaxUrl,
            method: 'POST',
            data  : {
                action : 'aura_integrations_save_mapping',
                nonce  : cfg.nonce,
                mapping: state.mapping,
            },
            success: function (res) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar mapeo para reutilizar');
                if (res.success) {
                    $status.text(cfg.i18n.saved).addClass('aura-status-ok');
                } else {
                    $status.text(res.data && res.data.message ? res.data.message : cfg.i18n.error).addClass('aura-status-error');
                }
                setTimeout(function () { $status.text(''); }, 4000);
            },
            error: function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar mapeo para reutilizar');
                $status.text(cfg.i18n.error).addClass('aura-status-error');
            }
        });
    });

    /* ===================================================================
     * PASO 4: VISTA PREVIA
     * =================================================================== */
    function loadPreviewStep() {
        var $container = $('#auraPreviewContainer');
        var $meta      = $('#auraPreviewMeta');

        $container.html('<p class="aura-loading-msg"><span class="spinner is-active"></span> ' + cfg.i18n.loading + '</p>');
        $meta.html('<span class="aura-badge aura-badge-blue">' +
                   (cfg.softwareLabels[state.software] || state.software) + '</span>');

        // Recoge mapping final
        $('.aura-mapping-input').each(function () {
            var catId = $(this).data('cat-id');
            state.mapping[catId] = $(this).val().trim();
        });

        $.ajax({
            url   : cfg.ajaxUrl,
            method: 'POST',
            data  : {
                action       : 'aura_integrations_preview',
                nonce        : cfg.nonce,
                software     : state.software,
                date_from    : state.dateFrom,
                date_to      : state.dateTo,
                only_approved: state.onlyApproved ? 1 : 0,
                excluded_cats: state.excludedCats,
            },
            success: function (res) {
                if (!res.success) {
                    $container.html('<div class="notice notice-warning inline"><p>' +
                        escHtml(res.data && res.data.message ? res.data.message : cfg.i18n.noData) +
                        '</p></div>');
                    return;
                }
                $container.html(res.data.html);
            },
            error: function () {
                $container.html('<p class="notice notice-error">' + cfg.i18n.error + '</p>');
            }
        });
    }

    /* ===================================================================
     * DESCARGA DEL ARCHIVO
     * =================================================================== */
    $('#auraDownloadBtn').on('click', function () {
        var $btn      = $(this);
        var $progress = $('#auraDownloadProgress');

        // Recoge mapping final
        $('.aura-mapping-input').each(function () {
            var catId = $(this).data('cat-id');
            state.mapping[catId] = $(this).val().trim();
        });

        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>' + cfg.i18n.downloading);
        $progress.fadeIn();

        $.ajax({
            url   : cfg.ajaxUrl,
            method: 'POST',
            data  : {
                action       : 'aura_export_accounting_format',
                nonce        : cfg.nonce,
                software     : state.software,
                date_from    : state.dateFrom,
                date_to      : state.dateTo,
                only_approved: state.onlyApproved ? 1 : 0,
                excluded_cats: state.excludedCats,
                custom_cols  : state.customCols,
            },
            success: function (res) {
                $progress.fadeOut();
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Descargar archivo');

                if (!res.success) {
                    alert(res.data && res.data.message ? res.data.message : cfg.i18n.error);
                    return;
                }

                state.exportResult = res.data;
                triggerDownload(res.data.content, res.data.filename, res.data.mime_type);

                // Mostrar contador
                $('#auraPreviewMeta').html(
                    '<span class="aura-badge aura-badge-blue">' +
                    (cfg.softwareLabels[state.software] || state.software) + '</span> ' +
                    '<span class="aura-badge aura-badge-green">✓ ' + res.data.count + ' transacciones exportadas</span>'
                );
            },
            error: function () {
                $progress.fadeOut();
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Descargar archivo');
                alert(cfg.i18n.error);
            }
        });
    });

    function triggerDownload(base64Content, filename, mimeType) {
        try {
            var binary = atob(base64Content);
            var bytes  = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            var blob = new Blob([bytes], { type: mimeType });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href     = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(function () {
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 500);
        } catch (e) {
            alert('Error al generar el archivo: ' + e.message);
        }
    }

    /* ===================================================================
     * UTILIDADES
     * =================================================================== */
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

})(jQuery);
