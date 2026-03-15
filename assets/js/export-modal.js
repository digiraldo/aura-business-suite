/**
 * Modal de Exportación Multi-formato – Fase 4, Item 4.1
 *
 * Gestiona la interfaz para seleccionar formato, columnas, alcance y opciones
 * adicionales antes de generar y descargar el archivo exportado.
 *
 * @package AuraBusinessSuite
 */
/* global auraExport, jQuery */

(function ($) {
    'use strict';

    /* ============================================================ */
    /* ABRIR / CERRAR MODAL                                          */
    /* ============================================================ */

    $(document).on('click', '#aura-export-btn', function () {
        refreshFilterCount();
        $('#aura-export-modal').show();
    });

    $(document).on('click', '.aura-export-modal-close, #aura-export-modal .aura-export-overlay', function () {
        closeExportModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') closeExportModal();
    });

    function closeExportModal() {
        $('#aura-export-modal').hide();
        resetState();
    }

    /* ============================================================ */
    /* ESTADO                                                        */
    /* ============================================================ */

    function resetState() {
        $('#aura-export-progress').hide();
        $('#aura-export-progress-bar').css('width', '0%');
        $('#aura-export-error').hide().text('');
        $('#aura-export-submit').prop('disabled', false).text(auraExport.txt.export_btn);
    }

    /* ============================================================ */
    /* CONTADOR DE REGISTROS FILTRADOS                               */
    /* ============================================================ */

    function refreshFilterCount() {
        const count = parseInt($('#aura-filtered-count').val() || '0');
        if (count > 0) {
            $('#export-scope-filtered-label').text(
                auraExport.txt.use_filters.replace('%d', count)
            );
        } else {
            $('#export-scope-filtered').prop('disabled', true);
            // Si no hay filtros activos, seleccionar "todas"
            $('#export-scope-all').prop('checked', true);
        }
    }

    /* ============================================================ */
    /* OPCIONES SEGÚN FORMATO                                        */
    /* ============================================================ */

    $(document).on('change', 'input[name="export_format"]', function () {
        const fmt = $(this).val();
        // Mostrar/ocultar opciones específicas
        $('.export-opt-csv').toggle(fmt === 'csv');
        $('.export-opt-excel-pdf').toggle(fmt === 'excel' || fmt === 'pdf');
        $('.export-opt-pdf').toggle(fmt === 'pdf');
    });

    /* ============================================================ */
    /* SELECCIONAR / DESELECCIONAR TODAS LAS COLUMNAS               */
    /* ============================================================ */

    $(document).on('click', '#export-select-all-cols', function () {
        $('#aura-export-columns input[type="checkbox"]').prop('checked', true);
    });

    $(document).on('click', '#export-deselect-cols', function () {
        // Al menos id, fecha, monto deben quedar seleccionados
        const required = ['id', 'transaction_date', 'amount'];
        $('#aura-export-columns input[type="checkbox"]').each(function () {
            if (!required.includes($(this).val())) {
                $(this).prop('checked', false);
            }
        });
    });

    /* ============================================================ */
    /* SELECCIÓN ACTUAL (checkboxes de la lista de transacciones)   */
    /* ============================================================ */

    function getSelectedIds() {
        const ids = [];
        $('input.aura-tx-checkbox:checked').each(function () {
            ids.push($(this).val());
        });
        return ids;
    }

    $(document).on('change', 'input[name="export_scope"]', function () {
        const scope = $(this).val();
        if (scope === 'selected') {
            const ids = getSelectedIds();
            if (!ids.length) {
                alert(auraExport.txt.no_selection);
                $('#export-scope-filtered').prop('checked', true);
            } else {
                $('#export-scope-selected-count').text(
                    auraExport.txt.selected_count.replace('%d', ids.length)
                );
            }
        }
        $('#export-scope-selected-info').toggle(scope === 'selected');
    });

    /* ============================================================ */
    /* ENVIAR FORMULARIO                                              */
    /* ============================================================ */

    $(document).on('submit', '#aura-export-form', function (e) {
        e.preventDefault();

        const $btn  = $('#aura-export-submit');
        const $prog = $('#aura-export-progress');
        const $err  = $('#aura-export-error');

        $err.hide();
        $btn.prop('disabled', true).text(auraExport.txt.generating);
        $prog.show();
        animateProgress();

        const format  = $('input[name="export_format"]:checked').val() || 'csv';
        const scope   = $('input[name="export_scope"]:checked').val() || 'filtered';
        const columns = [];

        $('#aura-export-columns input:checked').each(function () {
            columns.push($(this).val());
        });

        if (!columns.length) {
            $err.text(auraExport.txt.no_columns).show();
            $btn.prop('disabled', false).text(auraExport.txt.export_btn);
            $prog.hide();
            return;
        }

        const ids = scope === 'selected' ? getSelectedIds() : [];

        // Recolectar filtros activos del formulario de la página
        const filterData = {};
        $('#aura-filters-form').serializeArray().forEach(function (field) {
            if (filterData[field.name]) {
                if (!Array.isArray(filterData[field.name])) {
                    filterData[field.name] = [filterData[field.name]];
                }
                filterData[field.name].push(field.value);
            } else {
                filterData[field.name] = field.value;
            }
        });

        const postData = Object.assign({}, filterData, {
            action:          'aura_export_transactions',
            nonce:           auraExport.nonce,
            format,
            scope,
            columns,
            ids,
            include_totals:  $('#export-opt-totals').is(':checked') ? 1 : 0,
            delimiter:       $('select[name="export_delimiter"]').val() || ',',
        });

        $.ajax({
            url:  auraExport.ajaxurl,
            type: 'POST',
            data: postData,
        })
        .done(function (res) {
            $prog.hide();
            $btn.prop('disabled', false).text(auraExport.txt.export_btn);

            if (!res.success) {
                $err.text(res.data?.message || auraExport.txt.error_generic).show();
                return;
            }

            const data = res.data;

            if (data.type === 'url') {
                if (data.open_in_tab) {
                    // PDF guardado en disco → abrir en nueva pestaña
                    window.open(data.url, '_blank');
                    closeExportModal();
                    showSuccessNotice(data.filename);
                } else {
                    // Excel, archivos grandes → descarga directa
                    triggerUrlDownload(data.url, data.filename);
                    closeExportModal();
                    showSuccessNotice(data.filename);
                }
            } else if (data.type === 'base64') {
                // Blob download para CSV / JSON / XML / HTML
                if (data.open_in_tab) {
                    // PDF (HTML) → abrir en nueva pestaña
                    const binary = atob(data.content);
                    const bytes  = new Uint8Array(binary.length);
                    for (let i = 0; i < binary.length; i++) {
                        bytes[i] = binary.charCodeAt(i);
                    }
                    const blob = new Blob([bytes], { type: 'text/html;charset=utf-8' });
                    const url  = URL.createObjectURL(blob);
                    window.open(url, '_blank');
                    closeExportModal();
                    showSuccessNotice(data.filename);
                } else {
                    triggerBase64Download(data.content, data.filename, data.mime || 'application/octet-stream');
                    closeExportModal();
                    showSuccessNotice(data.filename);
                }
            }
        })
        .fail(function () {
            $prog.hide();
            $btn.prop('disabled', false).text(auraExport.txt.export_btn);
            $err.text(auraExport.txt.error_generic).show();
        });
    });

    /* ============================================================ */
    /* DOWNLOAD HELPERS                                              */
    /* ============================================================ */

    function triggerUrlDownload(url, filename) {
        const a   = document.createElement('a');
        a.href    = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function triggerBase64Download(b64, filename, mime) {
        const binary = atob(b64);
        const bytes  = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        const blob = new Blob([bytes], { type: mime });
        const url  = URL.createObjectURL(blob);
        triggerUrlDownload(url, filename);
        setTimeout(() => URL.revokeObjectURL(url), 5000);
    }

    /* ============================================================ */
    /* ANIMACIÓN DE BARRA DE PROGRESO                                */
    /* ============================================================ */

    let progressInterval = null;

    function animateProgress() {
        let pct = 0;
        clearInterval(progressInterval);
        progressInterval = setInterval(function () {
            pct = Math.min(pct + Math.random() * 8, 90);
            $('#aura-export-progress-bar').css('width', pct + '%');
        }, 200);
    }

    /* ============================================================ */
    /* NOTIFICACIÓN DE ÉXITO                                         */
    /* ============================================================ */

    function showSuccessNotice(filename) {
        const msg = auraExport.txt.success.replace('%s', filename);
        const $n  = $('<div class="notice notice-success is-dismissible"><p>' + escHtml(msg) + '</p></div>');
        $('.aura-transactions-list-page h1').after($n);
        setTimeout(() => $n.fadeOut(() => $n.remove()), 4000);
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ============================================================ */
    /* INIT                                                          */
    /* ============================================================ */

    $(function () {
        // Disparar change inicial para mostrar opciones correctas
        $('input[name="export_format"]:checked').trigger('change');

        // Mostrar sólo el primer tab del modal activo
        refreshFilterCount();
    });

})(jQuery);
