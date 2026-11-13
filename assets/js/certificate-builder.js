/**
 * Aura Business Suite — Certificate Builder
 * Editor visual de plantillas basado en Fabric.js v5.x
 *
 * Datos localizados en auraCertBuilder:
 *   ajaxUrl, nonce, templateId, templatesUrl, dynamicVars, signers,
 *   prebuiltDesigns, orgName, orgLogoUrl, verifySlug
 */
(function ($) {
    'use strict';

    /* ─────────────────────────────────────────
       CONFIG
       ───────────────────────────────────────── */

    var CB      = window.auraCertBuilder || {};
    var AJAX    = CB.ajaxUrl  || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    var NONCE   = CB.nonce    || '';
    var TMPL_ID = parseInt(CB.templateId, 10) || parseInt($('#aura-cb-template-id').val(), 10) || 0;

    var CUSTOM_PROPS = ['name', 'auraElementType', 'rx', 'ry', 'strokeDashArray', 'auraLocked', 'shadow'];

    // Dimensiones lógicas del documento en píxeles (96 dpi) y milímetros
    var DIM = {
        landscape        : { w: 1122, h: 794,  w_mm: 297, h_mm: 210 }, // A4 Horizontal
        portrait         : { w: 794,  h: 1122, w_mm: 210, h_mm: 297 }, // A4 Vertical
        letter_landscape : { w: 1056, h: 816,  w_mm: 279, h_mm: 216 }, // Carta Horizontal
        letter_portrait  : { w: 816,  h: 1056, w_mm: 216, h_mm: 279 }  // Carta Vertical
    };

    var FONTS = [
        'Arial', 'Times New Roman', 'Georgia', 'Verdana', 'Courier New', 'Tahoma',
        'Playfair Display', 'Montserrat', 'Lato', 'Great Vibes',
        'Roboto', 'Open Sans', 'Raleway', 'Merriweather', 'Cinzel', 'Dancing Script'
    ];

    var SAMPLE_DATA = {
        '{nombre_estudiante}'   : 'María Fernanda Gómez',
        '{nombre_completo}'     : 'María Fernanda Gómez',
        '{documento}'           : 'CC 1.045.829.310',
        '{correo}'              : 'maria.gomez@ejemplo.com',
        '{tipo_destinatario}'   : 'Estudiante',
        '{curso}'               : 'Diplomado en Liderazgo y Gestión',
        '{programa}'            : 'Escuela de Liderazgo Empresarial',
        '{categoria}'           : 'Excelencia Académica',
        '{horas}'               : '60 horas',
        '{calificacion}'        : '98 / 100',
        '{instructor}'          : 'Dr. Carlos Andrés Mejía',
        '{fecha_graduacion}'    : '15 de octubre de 2026',
        '{descripcion}'         : 'Por haber culminado satisfactoriamente todas las evaluaciones y proyectos prácticos del programa.',
        '{organizacion}'        : CB.orgName || 'Centro de Formación Aura',
        '{folio}'               : 'CEM-2026-0148',
        '{codigo_verificacion}' : 'A7F2-9C4B-E108',
        '{fecha_emision}'       : '15/10/2026',
        '{fecha_emision_larga}' : '15 de octubre de 2026',
        '{anio_actual}'         : '2026',
        '{ciudad}'              : 'Bogotá D.C.',
        '{url_verificacion}'    : (CB.siteUrl || window.location.origin) + '/' + (CB.verifySlug || 'verificar-certificado') + '/CEM-2026-0148',
        '{firmante_1_nombre}'   : (CB.signers && CB.signers[0] && CB.signers[0].name) || 'Dr. Juan García',
        '{firmante_1_cargo}'    : (CB.signers && CB.signers[0] && CB.signers[0].title) || 'Director Académico',
        '{firmante_2_nombre}'   : (CB.signers && CB.signers[1] && CB.signers[1].name) || 'Dra. Sofía Restrepo',
        '{firmante_2_cargo}'    : (CB.signers && CB.signers[1] && CB.signers[1].title) || 'Coordinadora General'
    };

    /* ─────────────────────────────────────────
       ESTADO
       ───────────────────────────────────────── */

    var canvas;
    var docW          = 1122;
    var docH          = 794;
    var history       = [];
    var historyFuture = [];
    var historyLock   = false;
    var historyTimer  = null;
    var currentZoom   = 1;
    var snapEnabled   = false;
    var guidesEnabled = false;
    var snapGrid      = 10;

    /* ─────────────────────────────────────────
       HELPERS
       ───────────────────────────────────────── */

    function ajaxPost(action, data) {
        return $.post(AJAX, $.extend({ action: action, nonce: NONCE }, data));
    }

    function escHtml(str) {
        return $('<span>').text(str == null ? '' : String(str)).html();
    }

    function extractError(res, fallback) {
        if (!res) return fallback || 'Ocurrió un error.';
        if (typeof res.data === 'string' && res.data) return res.data;
        if (res.data && typeof res.data.message === 'string' && res.data.message) return res.data.message;
        if (typeof res.message === 'string' && res.message) return res.message;
        return fallback || 'Ocurrió un error.';
    }

    function toHexColor(color, fallback) {
        if (!color || typeof color !== 'string') return fallback || '#000000';
        var c = color.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(c)) return c;
        if (/^#[0-9a-fA-F]{3}$/.test(c)) {
            return '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
        }
        var m = c.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
        if (m) {
            return '#' + [m[1], m[2], m[3]].map(function (n) {
                var h = Math.max(0, Math.min(255, parseInt(n, 10))).toString(16);
                return h.length < 2 ? '0' + h : h;
            }).join('');
        }
        return fallback || '#000000';
    }

    function showSaveStatus(text, isError) {
        var $st = $('#aura-cb-save-status');
        if (!$st.length) return;
        $st.text(text)
           .removeClass('is-error is-ok')
           .addClass(isError ? 'is-error' : 'is-ok')
           .fadeIn(120);
        clearTimeout($st.data('timer'));
        $st.data('timer', setTimeout(function () {
            $st.fadeOut(300);
        }, 3500));
    }

    function syncDimInputs(orientation) {
        var d = DIM[orientation] || DIM.landscape;
        $('#aura-cb-width-mm').val(d.w_mm);
        $('#aura-cb-height-mm').val(d.h_mm);
    }

    function isTextObject(obj) {
        return obj && (obj.type === 'textbox' || obj.type === 'i-text' || obj.type === 'text');
    }

    function preloadCanvasFonts(objects) {
        if (!document.fonts || !Array.isArray(objects)) return;
        var seen = {};
        objects.forEach(function (o) {
            if (o && o.fontFamily && !seen[o.fontFamily]) {
                seen[o.fontFamily] = true;
                try {
                    document.fonts.load('16px "' + o.fontFamily + '"').then(function () {
                        if (canvas) canvas.requestRenderAll();
                    });
                } catch (e) { /* ignore */ }
            }
        });
    }

    function getObjectLabel(obj, idx) {
        if (!obj) return 'Elemento ' + (idx + 1);
        if (obj.auraElementType === 'qr' || obj.name === 'qr_placeholder') return 'Código QR';
        if (obj.name && String(obj.name).indexOf('signer_') === 0) return 'Firma (' + obj.name + ')';
        if (isTextObject(obj)) {
            var txt = (obj.text || '').replace(/\s+/g, ' ').trim();
            return 'T: ' + (txt.length > 22 ? txt.substring(0, 22) + '…' : (txt || 'Texto'));
        }
        var names = {
            rect     : 'Rectángulo',
            circle   : 'Círculo',
            triangle : 'Triángulo',
            polygon  : 'Estrella / Polígono',
            line     : 'Línea',
            image    : 'Imagen',
            group    : 'Grupo / Bloque'
        };
        return names[obj.type] || obj.type || ('Objeto #' + (idx + 1));
    }

    /* ═════════════════════════════════════════
       INICIALIZACIÓN DEL CANVAS
       ═════════════════════════════════════════ */

    function initCanvas() {
        var orientation = $('#aura-cb-orientation').val() || 'landscape';
        var dim         = DIM[orientation] || DIM.landscape;
        var bgColor     = $('#aura-cb-bg-color').val() || '#ffffff';

        docW = dim.w;
        docH = dim.h;
        syncDimInputs(orientation);

        canvas = new fabric.Canvas('aura-cert-canvas', {
            width                  : dim.w,
            height                 : dim.h,
            backgroundColor        : bgColor,
            preserveObjectStacking : true,
            selection              : true
        });

        // Selección
        canvas.on('selection:created', onSelectionChange);
        canvas.on('selection:updated', onSelectionChange);
        canvas.on('selection:cleared', onSelectionCleared);

        // Modificación + historial + capas
        canvas.on('object:modified', function () {
            saveHistoryStep();
            updateObjectPanel(canvas.getActiveObject());
            renderLayersList();
        });
        canvas.on('object:added', function () {
            saveHistoryStep();
            renderLayersList();
        });
        canvas.on('object:removed', function () {
            saveHistoryStep();
            renderLayersList();
        });

        // Snap a cuadrícula mientras se mueve
        canvas.on('object:moving', function (e) {
            if (snapEnabled && e.target) {
                e.target.set({
                    left : Math.round(e.target.left / snapGrid) * snapGrid,
                    top  : Math.round(e.target.top / snapGrid) * snapGrid
                });
            }
            updateObjectPanel(e.target);
        });
        canvas.on('object:scaling',  function (e) { updateObjectPanel(e.target); });
        canvas.on('object:rotating', function (e) { updateObjectPanel(e.target); });

        canvas.on('text:changed', function (e) {
            if (e.target) {
                $('#aura-cb-text-content').val(e.target.text);
                renderLayersList();
            }
        });

        if (TMPL_ID > 0) {
            loadTemplate(TMPL_ID);
        } else {
            history.push(serializeCanvas());
            updateHistoryButtons();
            fitToScreen();
            renderLayersList();
        }
    }

    function serializeCanvas() {
        var obj = canvas.toJSON(CUSTOM_PROPS);
        obj.canvasW = docW;
        obj.canvasH = docH;
        return JSON.stringify(obj);
    }

    function applyLockState(obj, locked) {
        if (!obj) return;
        obj.set({
            auraLocked   : !!locked,
            lockMovementX: !!locked,
            lockMovementY: !!locked,
            lockScalingX : !!locked,
            lockScalingY : !!locked,
            lockRotation : !!locked
        });
    }

    function restoreCanvasFromData(designObj, onDone) {
        historyLock = true;
        var parsed = typeof designObj === 'string' ? JSON.parse(designObj) : designObj;

        if (parsed.canvasW && parsed.canvasH) {
            docW = parsed.canvasW;
            docH = parsed.canvasH;
        }
        preloadCanvasFonts(parsed.objects || []);

        canvas.loadFromJSON(parsed, function () {
            canvas.getObjects().forEach(function (obj) {
                if (obj.auraLocked) {
                    applyLockState(obj, true);
                }
            });

            var bg = canvas.backgroundColor;
            if (typeof bg === 'string' && bg) {
                $('#aura-cb-bg-color').val(toHexColor(bg, '#ffffff'));
            }
            if (canvas.backgroundImage) {
                $('#aura-cb-bg-image-remove').show();
            } else {
                $('#aura-cb-bg-image-remove').hide();
            }

            canvas.requestRenderAll();
            historyLock = false;
            fitToScreen();
            renderLayersList();
            if (typeof onDone === 'function') onDone();
        });
    }

    function loadTemplate(id) {
        ajaxPost('aura_cert_get_template', { id: id }).done(function (res) {
            if (!res.success || !res.data) {
                alert(extractError(res, 'No se pudo cargar la plantilla.'));
                history.push(serializeCanvas());
                fitToScreen();
                return;
            }
            var t = res.data;
            $('#aura-cb-tmpl-name').val(t.name || '');
            $('#aura-cb-tmpl-desc').val(t.description || '');
            $('#aura-cb-topbar-title').text('Editar: ' + (t.name || ''));

            var ori = t.orientation || 'landscape';
            $('#aura-cb-orientation').val(ori);
            var dim = DIM[ori] || DIM.landscape;
            docW = dim.w;
            docH = dim.h;
            syncDimInputs(ori);

            if (t.design_json) {
                try {
                    restoreCanvasFromData(t.design_json, function () {
                        history       = [serializeCanvas()];
                        historyFuture = [];
                        updateHistoryButtons();
                    });
                } catch (e) {
                    console.error('[AuraCertBuilder] JSON inválido:', e);
                    history = [serializeCanvas()];
                    fitToScreen();
                }
            } else {
                history = [serializeCanvas()];
                fitToScreen();
            }
        });
    }

    /* ═════════════════════════════════════════
       ZOOM & GUÍAS
       ═════════════════════════════════════════ */

    function applyZoom() {
        if (!canvas) return;
        canvas.setDimensions({ width: Math.round(docW * currentZoom), height: Math.round(docH * currentZoom) });
        canvas.setZoom(currentZoom);
        canvas.requestRenderAll();
        updateZoomLabel();
        updateGuidesSize();
    }

    function fitToScreen() {
        var $wrap = $('.aura-cert-builder-canvas-wrap');
        if (!$wrap.length) return;
        var areaW = Math.max(240, $wrap.width() - 60);
        var areaH = Math.max(200, $wrap.height() - 60);
        currentZoom = Math.min(areaW / docW, areaH / docH, 1);
        currentZoom = Math.max(0.15, Math.round(currentZoom * 100) / 100);
        applyZoom();
    }

    function zoomIn()  { currentZoom = Math.min(3, Math.round((currentZoom + 0.1) * 10) / 10); applyZoom(); }
    function zoomOut() { currentZoom = Math.max(0.15, Math.round((currentZoom - 0.1) * 10) / 10); applyZoom(); }

    function updateZoomLabel() {
        $('#aura-cb-zoom-label').text(Math.round(currentZoom * 100) + '%');
    }

    function updateGuidesSize() {
        var $g = $('#aura-cb-guides-overlay');
        if (!$g.length) return;
        $g.css({
            width  : Math.round(docW * currentZoom) + 'px',
            height : Math.round(docH * currentZoom) + 'px'
        });
    }

    $(document).on('click', '#aura-cb-zoom-in', zoomIn);
    $(document).on('click', '#aura-cb-zoom-out', zoomOut);
    $(document).on('click', '#aura-cb-zoom-reset', fitToScreen);

    $(document).on('click', '#aura-cb-toggle-guides', function () {
        guidesEnabled = !guidesEnabled;
        $(this).toggleClass('is-active', guidesEnabled);
        $('#aura-cb-guides-overlay').toggle(guidesEnabled);
        updateGuidesSize();
    });

    $(document).on('click', '#aura-cb-toggle-snap', function () {
        snapEnabled = !snapEnabled;
        $(this).toggleClass('is-active', snapEnabled);
        $('#aura-cb-stage').toggleClass('is-snap-grid', snapEnabled);
    });

    /* ═════════════════════════════════════════
       HISTORIAL (UNDO / REDO)
       ═════════════════════════════════════════ */

    function saveHistoryStep() {
        if (historyLock || !canvas) return;
        clearTimeout(historyTimer);
        historyTimer = setTimeout(function () {
            var json = serializeCanvas();
            if (history[history.length - 1] !== json) {
                history.push(json);
                if (history.length > 60) history.shift();
                historyFuture = [];
                updateHistoryButtons();
            }
        }, 120);
    }

    function undo() {
        if (history.length <= 1) return;
        historyFuture.push(history.pop());
        restoreHistoryState(history[history.length - 1]);
    }

    function redo() {
        if (!historyFuture.length) return;
        var next = historyFuture.pop();
        history.push(next);
        restoreHistoryState(next);
    }

    function restoreHistoryState(json) {
        restoreCanvasFromData(json, function () {
            onSelectionCleared();
            updateHistoryButtons();
        });
    }

    function updateHistoryButtons() {
        $('#aura-cb-undo').prop('disabled', history.length <= 1);
        $('#aura-cb-redo').prop('disabled', !historyFuture.length);
    }

    $(document).on('click', '#aura-cb-undo', undo);
    $(document).on('click', '#aura-cb-redo', redo);

    /* ═════════════════════════════════════════
       PANEL DE PROPIEDADES Y CAPAS
       ═════════════════════════════════════════ */

    function onSelectionChange(e) {
        var obj = e.selected && e.selected[0] ? e.selected[0] : canvas.getActiveObject();
        updateObjectPanel(obj);
        renderLayersList();
    }

    function onSelectionCleared() {
        $('#aura-cb-obj-props').hide();
        $('#aura-cb-text-props').hide();
        $('#aura-cb-shape-props').hide();
        renderLayersList();
    }

    function updateObjectPanel(obj) {
        if (!obj) {
            onSelectionCleared();
            return;
        }
        $('#aura-cb-obj-props').show();

        // Posición, dimensiones, ángulo, opacidad, sombra, bloqueo
        $('#aura-cb-obj-x').val(Math.round(obj.left || 0));
        $('#aura-cb-obj-y').val(Math.round(obj.top || 0));
        $('#aura-cb-obj-w').val(Math.round(obj.getScaledWidth ? obj.getScaledWidth() : (obj.width || 0)));
        $('#aura-cb-obj-h').val(Math.round(obj.getScaledHeight ? obj.getScaledHeight() : (obj.height || 0)));
        $('#aura-cb-obj-angle').val(Math.round(obj.angle || 0));

        var opPct = Math.round((obj.opacity !== undefined ? obj.opacity : 1) * 100);
        $('#aura-cb-obj-opacity').val(opPct);
        $('#aura-cb-obj-opacity-label').text(opPct + '%');
        $('#aura-cb-obj-lock').prop('checked', !!obj.auraLocked);

        var hasShadow = !!(obj.shadow && (obj.shadow.blur > 0 || obj.shadow.offsetX || obj.shadow.offsetY));
        $('#aura-cb-obj-shadow').prop('checked', hasShadow);
        if (hasShadow && obj.shadow.color) {
            $('#aura-cb-obj-shadow-color').val(toHexColor(obj.shadow.color, '#0f172a'));
        }

        if (isTextObject(obj)) {
            $('#aura-cb-text-props').show();
            $('#aura-cb-shape-props').hide();

            $('#aura-cb-text-content').val(obj.text || '');
            $('#aura-cb-obj-font-family').val(obj.fontFamily || 'Arial');
            $('#aura-cb-obj-font-size').val(obj.fontSize || 24);
            $('#aura-cb-obj-line-height').val(obj.lineHeight || 1.16);
            $('#aura-cb-obj-char-spacing').val(obj.charSpacing || 0);
            $('#aura-cb-obj-font-color').val(toHexColor(obj.fill, '#000000'));
            $('#aura-cb-obj-text-bg').val(toHexColor(obj.textBackgroundColor, '#ffffff'));
            $('#aura-cb-obj-text-align').val(obj.textAlign || 'left');

            $('#aura-cb-obj-bold').toggleClass('is-active', obj.fontWeight === 'bold' || obj.fontWeight === 700 || obj.fontWeight === '700');
            $('#aura-cb-obj-italic').toggleClass('is-active', obj.fontStyle === 'italic');
            $('#aura-cb-obj-underline').toggleClass('is-active', !!obj.underline);
            $('#aura-cb-obj-linethrough').toggleClass('is-active', !!obj.linethrough);
        } else {
            $('#aura-cb-text-props').hide();

            if (obj.type === 'rect' || obj.type === 'circle' || obj.type === 'triangle' || obj.type === 'polygon' || obj.type === 'line') {
                $('#aura-cb-shape-props').show();
                $('#aura-cb-obj-fill').val(toHexColor(obj.fill, '#8b5cf6'));
                $('#aura-cb-obj-stroke').val(toHexColor(obj.stroke, '#000000'));
                $('#aura-cb-obj-stroke-width').val(obj.strokeWidth || 0);

                var dashType = 'solid';
                if (Array.isArray(obj.strokeDashArray) && obj.strokeDashArray.length) {
                    dashType = obj.strokeDashArray[0] <= 4 ? 'dotted' : 'dashed';
                }
                $('#aura-cb-obj-stroke-dash').val(dashType);

                if (obj.type === 'rect') {
                    $('#aura-cb-obj-rx-wrap').show();
                    $('#aura-cb-obj-rx').val(obj.rx || 0);
                } else {
                    $('#aura-cb-obj-rx-wrap').hide();
                }
            } else {
                $('#aura-cb-shape-props').hide();
            }
        }
    }

    function renderLayersList() {
        var $list = $('#aura-cb-layers-list');
        if (!$list.length || !canvas) return;
        $list.empty();

        var objs   = canvas.getObjects();
        var active = canvas.getActiveObject();

        if (!objs.length) {
            $list.html('<p class="aura-cb-muted">Sin elementos en el lienzo.</p>');
            return;
        }

        for (var i = objs.length - 1; i >= 0; i--) {
            var obj = objs[i];
            var isSel = active === obj;
            var $row = $('<div class="aura-cb-layer-item">')
                .toggleClass('is-selected', isSel)
                .attr('data-index', i)
                .html(
                    '<span class="aura-cb-layer-name">' + escHtml(getObjectLabel(obj, i)) + '</span>' +
                    (obj.auraLocked ? '<span title="Bloqueado">🔒</span>' : '')
                );
            $list.append($row);
        }
    }

    $(document).on('click', '.aura-cb-layer-item', function () {
        var idx  = parseInt($(this).attr('data-index'), 10);
        var objs = canvas.getObjects();
        if (!isNaN(idx) && objs[idx]) {
            canvas.setActiveObject(objs[idx]);
            canvas.requestRenderAll();
            updateObjectPanel(objs[idx]);
            renderLayersList();
        }
    });

    function populateFonts() {
        var $sel = $('#aura-cb-obj-font-family');
        if (!$sel.length) return;
        $sel.empty();
        FONTS.forEach(function (f) {
            $sel.append($('<option>').val(f).text(f).css('font-family', f));
        });
    }

    /* ─── Bind propiedades en tiempo real ─── */

    function bindPropInputs() {
        // Contenido texto
        $('#aura-cb-text-content').on('input', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                obj.set('text', $(this).val());
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        // Insertar variable inline dentro del texto seleccionado
        $('#aura-cb-insert-var-inline').on('change', function () {
            var v = $(this).val();
            $(this).val('');
            if (!v) return;
            var obj = canvas.getActiveObject();
            if (!isTextObject(obj)) return;
            var $ta = $('#aura-cb-text-content');
            var cur = $ta.val() || '';
            var pos = $ta[0].selectionStart != null ? $ta[0].selectionStart : cur.length;
            var next = cur.slice(0, pos) + v + cur.slice(pos);
            $ta.val(next);
            obj.set('text', next);
            canvas.requestRenderAll();
            saveHistoryStep();
        });

        $('#aura-cb-obj-font-family').on('change', function () {
            var obj = canvas.getActiveObject();
            var font = $(this).val();
            if (isTextObject(obj)) {
                obj.set('fontFamily', font);
                preloadCanvasFonts([obj]);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-font-size').on('change input', function () {
            var obj = canvas.getActiveObject();
            var val = parseInt($(this).val(), 10);
            if (isTextObject(obj) && val >= 6) {
                obj.set('fontSize', val);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-line-height').on('change input', function () {
            var obj = canvas.getActiveObject();
            var val = parseFloat($(this).val());
            if (isTextObject(obj) && val >= 0.5) {
                obj.set('lineHeight', val);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-char-spacing').on('change input', function () {
            var obj = canvas.getActiveObject();
            var val = parseInt($(this).val(), 10);
            if (isTextObject(obj) && !isNaN(val)) {
                obj.set('charSpacing', val);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-font-color').on('input change', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                obj.set('fill', $(this).val());
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-text-bg').on('input change', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                obj.set('textBackgroundColor', $(this).val());
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-text-bg-clear').on('click', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                obj.set('textBackgroundColor', '');
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-bold').on('click', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                var next = (obj.fontWeight === 'bold' || obj.fontWeight === 700) ? 'normal' : 'bold';
                obj.set('fontWeight', next);
                $(this).toggleClass('is-active', next === 'bold');
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-italic').on('click', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                var next = obj.fontStyle === 'italic' ? 'normal' : 'italic';
                obj.set('fontStyle', next);
                $(this).toggleClass('is-active', next === 'italic');
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-underline').on('click', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                var next = !obj.underline;
                obj.set('underline', next);
                $(this).toggleClass('is-active', next);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-linethrough').on('click', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                var next = !obj.linethrough;
                obj.set('linethrough', next);
                $(this).toggleClass('is-active', next);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-uppercase').on('click', function () {
            var obj = canvas.getActiveObject();
            if (!isTextObject(obj)) return;
            var cur  = obj.text || '';
            var next = cur === cur.toUpperCase() ? cur.toLowerCase() : cur.toUpperCase();
            obj.set('text', next);
            $('#aura-cb-text-content').val(next);
            canvas.requestRenderAll();
            saveHistoryStep();
        });

        $('#aura-cb-obj-text-align').on('change', function () {
            var obj = canvas.getActiveObject();
            if (isTextObject(obj)) {
                obj.set('textAlign', $(this).val());
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        // Propiedades de Forma y Trazo
        $('#aura-cb-obj-fill').on('input change', function () {
            var obj = canvas.getActiveObject();
            if (obj && !isTextObject(obj)) {
                obj.set('fill', $(this).val());
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-fill-transparent').on('click', function () {
            var obj = canvas.getActiveObject();
            if (obj && !isTextObject(obj)) {
                obj.set('fill', 'transparent');
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-stroke').on('input change', function () {
            var obj = canvas.getActiveObject();
            if (obj) {
                obj.set('stroke', $(this).val());
                if (!obj.strokeWidth) {
                    obj.set('strokeWidth', 2);
                    $('#aura-cb-obj-stroke-width').val(2);
                }
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-stroke-none').on('click', function () {
            var obj = canvas.getActiveObject();
            if (obj) {
                obj.set({ stroke: null, strokeWidth: 0 });
                $('#aura-cb-obj-stroke-width').val(0);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-stroke-width').on('change input', function () {
            var obj = canvas.getActiveObject();
            if (obj) {
                var w = parseFloat($(this).val()) || 0;
                obj.set('strokeWidth', w);
                if (w > 0 && !obj.stroke) {
                    obj.set('stroke', $('#aura-cb-obj-stroke').val() || '#000000');
                }
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-stroke-dash').on('change', function () {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            var mode = $(this).val();
            var arr  = mode === 'dashed' ? [12, 8] : (mode === 'dotted' ? [3, 6] : null);
            obj.set('strokeDashArray', arr);
            canvas.requestRenderAll();
            saveHistoryStep();
        });

        $('#aura-cb-obj-rx').on('change input', function () {
            var obj = canvas.getActiveObject();
            if (obj && obj.type === 'rect') {
                var r = Math.max(0, parseInt($(this).val(), 10) || 0);
                obj.set({ rx: r, ry: r });
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        // Posición X / Y / W / H / Ángulo
        $('#aura-cb-obj-x').on('change', function () {
            var obj = canvas.getActiveObject();
            if (obj) { obj.set('left', parseFloat($(this).val()) || 0); obj.setCoords(); canvas.requestRenderAll(); saveHistoryStep(); }
        });
        $('#aura-cb-obj-y').on('change', function () {
            var obj = canvas.getActiveObject();
            if (obj) { obj.set('top', parseFloat($(this).val()) || 0); obj.setCoords(); canvas.requestRenderAll(); saveHistoryStep(); }
        });
        $('#aura-cb-obj-w').on('change', function () {
            var obj = canvas.getActiveObject();
            var w   = parseFloat($(this).val());
            if (!obj || isNaN(w) || w <= 0) return;
            if (obj.type === 'textbox') {
                obj.set({ width: w, scaleX: 1 });
            } else if (obj.width) {
                obj.set('scaleX', w / obj.width);
            }
            obj.setCoords();
            canvas.requestRenderAll();
            saveHistoryStep();
        });
        $('#aura-cb-obj-h').on('change', function () {
            var obj = canvas.getActiveObject();
            var h   = parseFloat($(this).val());
            if (!obj || isNaN(h) || h <= 0) return;
            if (obj.height) obj.set('scaleY', h / obj.height);
            obj.setCoords();
            canvas.requestRenderAll();
            saveHistoryStep();
        });
        $('#aura-cb-obj-angle').on('change input', function () {
            var obj = canvas.getActiveObject();
            if (obj) { obj.set('angle', parseFloat($(this).val()) || 0); obj.setCoords(); canvas.requestRenderAll(); saveHistoryStep(); }
        });

        // Opacidad
        $('#aura-cb-obj-opacity').on('input change', function () {
            var obj = canvas.getActiveObject();
            var pct = parseInt($(this).val(), 10);
            $('#aura-cb-obj-opacity-label').text(pct + '%');
            if (obj) {
                obj.set('opacity', pct / 100);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        // Sombra
        function updateShadow() {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            if ($('#aura-cb-obj-shadow').is(':checked')) {
                var col = $('#aura-cb-obj-shadow-color').val() || '#0f172a';
                obj.set('shadow', new fabric.Shadow({ color: col, blur: 10, offsetX: 3, offsetY: 4 }));
            } else {
                obj.set('shadow', null);
            }
            canvas.requestRenderAll();
            saveHistoryStep();
        }
        $('#aura-cb-obj-shadow').on('change', updateShadow);
        $('#aura-cb-obj-shadow-color').on('input change', function () {
            if ($('#aura-cb-obj-shadow').is(':checked')) updateShadow();
        });

        // Bloquear
        $('#aura-cb-obj-lock').on('change', function () {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            applyLockState(obj, $(this).is(':checked'));
            canvas.requestRenderAll();
            renderLayersList();
            saveHistoryStep();
        });

        // Alinear en el lienzo
        $(document).on('click', '.aura-cb-align-btn', function () {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            var mode = $(this).data('align');
            var w = obj.getScaledWidth();
            var h = obj.getScaledHeight();

            switch (mode) {
                case 'left':     obj.set('left', 40); break;
                case 'center-h': obj.set('left', Math.round((docW - w) / 2)); break;
                case 'right':    obj.set('left', Math.max(0, docW - w - 40)); break;
                case 'top':      obj.set('top', 40); break;
                case 'center-v': obj.set('top', Math.round((docH - h) / 2)); break;
                case 'bottom':   obj.set('top', Math.max(0, docH - h - 40)); break;
            }
            obj.setCoords();
            canvas.requestRenderAll();
            updateObjectPanel(obj);
            saveHistoryStep();
        });

        // Orden de capas / Clonar / Eliminar
        $('#aura-cb-bring-front').on('click', function () {
            var obj = canvas.getActiveObject();
            if (obj) { canvas.bringToFront(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
        });
        $('#aura-cb-bring-forward').on('click', function () {
            var obj = canvas.getActiveObject();
            if (obj) { canvas.bringForward(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
        });
        $('#aura-cb-send-backward').on('click', function () {
            var obj = canvas.getActiveObject();
            if (obj) { canvas.sendBackwards(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
        });
        $('#aura-cb-send-back').on('click', function () {
            var obj = canvas.getActiveObject();
            if (obj) { canvas.sendToBack(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
        });
        $('#aura-cb-delete-obj').on('click', deleteSelected);
        $('#aura-cb-clone-obj').on('click', cloneSelected);

        // Color de fondo y orientación del documento
        $('#aura-cb-bg-color').on('input change', function () {
            canvas.setBackgroundColor($(this).val(), function () {
                canvas.requestRenderAll();
                saveHistoryStep();
            });
        });

        $('#aura-cb-orientation').on('change', function () {
            var ori = $(this).val();
            var dim = DIM[ori] || DIM.landscape;
            docW = dim.w;
            docH = dim.h;
            syncDimInputs(ori);
            fitToScreen();
            saveHistoryStep();
        });
    }

    function deleteSelected() {
        var active = canvas.getActiveObjects();
        if (!active.length) return;
        active.forEach(function (o) { canvas.remove(o); });
        canvas.discardActiveObject();
        canvas.requestRenderAll();
        onSelectionCleared();
    }

    function cloneSelected() {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        obj.clone(function (cloned) {
            cloned.set({
                left : (obj.left || 0) + 20,
                top  : (obj.top || 0) + 20
            });
            CUSTOM_PROPS.forEach(function (p) {
                if (obj[p] !== undefined && cloned[p] === undefined) {
                    cloned[p] = obj[p];
                }
            });
            canvas.add(cloned);
            canvas.setActiveObject(cloned);
            canvas.requestRenderAll();
        }, CUSTOM_PROPS);
    }

    /* ═════════════════════════════════════════
       AGREGAR ELEMENTOS AL CANVAS
       ═════════════════════════════════════════ */

    function addTextbox(text, opts) {
        opts = opts || {};
        var tb = new fabric.Textbox(text || 'Texto', {
            left       : opts.left !== undefined ? opts.left : Math.round((docW - (opts.width || 280)) / 2),
            top        : opts.top  !== undefined ? opts.top  : Math.round(docH * 0.42),
            width      : opts.width || 280,
            fontSize   : opts.fontSize || 24,
            fontFamily : opts.fontFamily || 'Montserrat',
            fontWeight : opts.fontWeight || 'normal',
            fontStyle  : opts.fontStyle || 'normal',
            fill       : opts.fill || '#1e293b',
            textAlign  : opts.textAlign || 'center',
            charSpacing: opts.charSpacing || 0
        });
        canvas.add(tb);
        canvas.setActiveObject(tb);
        canvas.requestRenderAll();
    }

    function addImage(url, extraOpts) {
        if (!url) return;
        fabric.Image.fromURL(url, function (img) {
            var maxW = (extraOpts && extraOpts.maxW) || 260;
            if (img.width > maxW) img.scaleToWidth(maxW);
            img.set($.extend({
                left : Math.round((docW - img.getScaledWidth()) / 2),
                top  : 60
            }, extraOpts || {}));
            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.requestRenderAll();
        }, { crossOrigin: 'anonymous' });
    }

    function addOrgLogo() {
        var logoUrl = CB.orgLogoUrl || '';
        if (logoUrl) {
            addImage(logoUrl, { maxW: 180, top: 48, name: 'org_logo' });
        } else {
            addTextbox(CB.orgName || '{organizacion}', {
                top: 56, width: 500, fontSize: 22, fontFamily: 'Cinzel', fontWeight: 'bold', fill: '#1e3a8a', charSpacing: 120
            });
        }
    }

    function addRect() {
        var obj = new fabric.Rect({
            left: 120, top: 120, width: 240, height: 110,
            fill: '#8b5cf6', stroke: null, strokeWidth: 0, rx: 6, ry: 6
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addFrame() {
        var pad = 28;
        var obj = new fabric.Rect({
            left        : pad,
            top         : pad,
            width       : docW - pad * 2,
            height      : docH - pad * 2,
            fill        : 'transparent',
            stroke      : '#b8860b',
            strokeWidth : 4,
            rx          : 4,
            ry          : 4,
            name        : 'decorative_frame'
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addCircle() {
        var obj = new fabric.Circle({
            left: 140, top: 140, radius: 60, fill: '#8b5cf6', stroke: null, strokeWidth: 0
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addTriangle() {
        var obj = new fabric.Triangle({
            left: 150, top: 150, width: 120, height: 105, fill: '#f59e0b', stroke: null, strokeWidth: 0
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function createStarPoints(spikes, outerR, innerR) {
        var pts  = [];
        var step = Math.PI / spikes;
        var rot  = -Math.PI / 2;
        for (var i = 0; i < spikes * 2; i++) {
            var r = (i % 2 === 0) ? outerR : innerR;
            pts.push({ x: Math.cos(rot) * r + outerR, y: Math.sin(rot) * r + outerR });
            rot += step;
        }
        return pts;
    }

    function addStar() {
        var obj = new fabric.Polygon(createStarPoints(5, 48, 21), {
            left: 160, top: 140, fill: '#eab308', stroke: '#ca8a04', strokeWidth: 2
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addBadge() {
        var rect = new fabric.Rect({
            width: 220, height: 42, fill: '#1e3a8a', rx: 20, ry: 20
        });
        var txt = new fabric.Textbox('EXCELENCIA', {
            left: 10, top: 11, width: 200, fontSize: 16, fontFamily: 'Montserrat',
            fontWeight: 'bold', fill: '#fde047', textAlign: 'center', charSpacing: 150
        });
        var grp = new fabric.Group([rect, txt], {
            left: Math.round((docW - 220) / 2), top: 52, name: 'badge_ribbon'
        });
        canvas.add(grp);
        canvas.setActiveObject(grp);
        canvas.requestRenderAll();
    }

    function addLine() {
        var obj = new fabric.Line([0, 0, 360, 0], {
            left: Math.round((docW - 360) / 2), top: Math.round(docH / 2),
            stroke: '#334155', strokeWidth: 2
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addOrnateDivider() {
        var w = 440;
        var lineL = new fabric.Line([0, 10, 190, 10], { stroke: '#b8860b', strokeWidth: 1.5 });
        var diamond = new fabric.Rect({ left: 210, top: 2, width: 16, height: 16, fill: '#b8860b', angle: 45 });
        var lineR = new fabric.Line([245, 10, w, 10], { stroke: '#b8860b', strokeWidth: 1.5 });
        var grp = new fabric.Group([lineL, diamond, lineR], {
            left: Math.round((docW - w) / 2), top: 245, name: 'ornate_divider'
        });
        canvas.add(grp);
        canvas.setActiveObject(grp);
        canvas.requestRenderAll();
    }

    function addQrPlaceholder() {
        var group = new fabric.Group([
            new fabric.Rect({
                width: 96, height: 96,
                fill: '#f8fafc', stroke: '#94a3b8', strokeWidth: 1.5
            }),
            new fabric.Text('QR', {
                fontSize: 20, fontFamily: 'Montserrat', fontWeight: 'bold', fill: '#475569',
                originX: 'center', originY: 'center', left: 48, top: 48
            })
        ], {
            left: docW - 145, top: docH - 145,
            name: 'qr_placeholder', auraElementType: 'qr'
        });
        canvas.add(group);
        canvas.setActiveObject(group);
        canvas.requestRenderAll();
    }

    function addSignatureBlock() {
        var line = new fabric.Line([10, 0, 230, 0], { stroke: '#1e293b', strokeWidth: 1.5 });
        var txt  = new fabric.Textbox('{firmante_1_nombre}\n{firmante_1_cargo}', {
            left: 0, top: 10, width: 240, fontSize: 13, fontFamily: 'Lato',
            fill: '#1e293b', textAlign: 'center'
        });
        var grp = new fabric.Group([line, txt], {
            left: 160, top: docH - 170, name: 'signature_block'
        });
        canvas.add(grp);
        canvas.setActiveObject(grp);
        canvas.requestRenderAll();
    }

    function addOfficialSeal() {
        var outer = new fabric.Circle({
            radius: 48, fill: 'rgba(184,134,11,0.1)', stroke: '#b8860b', strokeWidth: 3
        });
        var inner = new fabric.Circle({
            left: 8, top: 8, radius: 40, fill: 'transparent', stroke: '#b8860b', strokeWidth: 1, strokeDashArray: [4, 3]
        });
        var txt = new fabric.Text('SELLO\nOFICIAL', {
            left: 48, top: 48, originX: 'center', originY: 'center',
            fontSize: 12, fontFamily: 'Cinzel', fontWeight: 'bold', fill: '#92400e', textAlign: 'center'
        });
        var grp = new fabric.Group([outer, inner, txt], {
            left: docW - 260, top: docH - 180, name: 'official_seal'
        });
        canvas.add(grp);
        canvas.setActiveObject(grp);
        canvas.requestRenderAll();
    }

    function addSignerImage(signer) {
        if (!signer) return;
        var baseTop = Math.max(120, docH - 220);
        if (signer.signature_url) {
            fabric.Image.fromURL(signer.signature_url, function (img) {
                img.set({ left: 140, top: baseTop - 70, name: 'signer_' + signer.id });
                img.scaleToWidth(160);
                canvas.add(img);
                canvas.requestRenderAll();
            }, { crossOrigin: 'anonymous' });
        }
        addTextbox(signer.name + '\n' + (signer.title || ''), {
            left: 110, top: baseTop, width: 220, fontSize: 13, fontFamily: 'Lato', textAlign: 'center'
        });
    }

    /* ═════════════════════════════════════════
       VARIABLES DINÁMICAS (AGRUPADAS)
       ═════════════════════════════════════════ */

    function populateVarsList(filterText) {
        var $list   = $('#aura-cb-vars-list');
        var $inline = $('#aura-cb-insert-var-inline');
        var vars    = CB.dynamicVars || [];
        var query   = (filterText || '').trim().toLowerCase();

        if ($inline.length && !$inline.data('populated')) {
            vars.forEach(function (v) {
                $inline.append($('<option>').val(v.key).text(v.label + ' (' + v.key + ')'));
            });
            $inline.data('populated', true);
        }

        if (!$list.length) return;
        $list.empty();

        var groups = {};
        vars.forEach(function (v) {
            var labelStr = (v.label || '') + ' ' + (v.key || '');
            if (query && labelStr.toLowerCase().indexOf(query) === -1) return;
            var cat = v.category || 'General';
            if (!groups[cat]) groups[cat] = [];
            groups[cat].push(v);
        });

        var cats = Object.keys(groups);
        if (!cats.length) {
            $list.html('<p class="aura-cb-muted">Sin variables coincidentes.</p>');
            return;
        }

        cats.forEach(function (cat) {
            $list.append($('<div class="aura-cb-var-cat">').text(cat));
            groups[cat].forEach(function (v) {
                var $btn = $('<button type="button" class="aura-cb-var-btn" title="' + escHtml(v.key) + '">')
                    .html('<span>' + escHtml(v.label) + '</span><code>' + escHtml(v.key) + '</code>');
                $btn.data('var', v.key);
                $list.append($btn);
            });
        });
    }

    $(document).on('input', '#aura-cb-var-search', function () {
        populateVarsList($(this).val());
    });

    $(document).on('click', '.aura-cb-var-btn', function () {
        addTextbox($(this).data('var'), { width: 340, fontSize: 20, fontFamily: 'Montserrat', textAlign: 'center' });
    });

    /* ═════════════════════════════════════════
       DISEÑOS PREDEFINIDOS (8 PLANTILLAS BASE)
       ═════════════════════════════════════════ */

    function populatePrebuilt() {
        var $list = $('#aura-cb-prebuilt-list');
        if (!$list.length) return;
        $list.empty();

        var designs = Array.isArray(CB.prebuiltDesigns)
            ? CB.prebuiltDesigns
            : Object.values(CB.prebuiltDesigns || {});

        if (!designs.length) {
            $list.html('<p class="aura-cb-muted">Sin diseños predefinidos.</p>');
            return;
        }

        designs.forEach(function (d) {
            var oriLabels = {
                landscape        : 'A4 Horiz.',
                portrait         : 'A4 Vert.',
                letter_landscape : 'Carta Horiz.',
                letter_portrait  : 'Carta Vert.'
            };
            var bgPrev = (d.json && d.json.background) || '#ffffff';
            var accent = d.accent || '#7c3aed';
            var $card = $('<button type="button" class="aura-cb-prebuilt-btn">')
                .html(
                    '<span class="aura-cb-prebuilt-swatch" style="background:' + escHtml(bgPrev) + ';border-top:4px solid ' + escHtml(accent) + ';"></span>' +
                    '<span class="aura-cb-prebuilt-meta">' +
                    '<strong>' + escHtml(d.name) + '</strong>' +
                    '<small>' + escHtml(oriLabels[d.orientation] || d.orientation) + (d.description ? ' • ' + escHtml(d.description) : '') + '</small>' +
                    '</span>'
                );
            $card.data('design', d);
            $list.append($card);
        });
    }

    $(document).on('click', '.aura-cb-prebuilt-btn', function () {
        var d = $(this).data('design');
        if (!d || !d.json) return;
        if (canvas.getObjects().length && !confirm('¿Reemplazar el diseño actual con "' + d.name + '"?')) return;

        var ori = d.orientation || 'landscape';
        $('#aura-cb-orientation').val(ori);
        var dim = DIM[ori] || DIM.landscape;
        docW = dim.w;
        docH = dim.h;
        syncDimInputs(ori);

        if (!$('#aura-cb-tmpl-name').val().trim()) {
            $('#aura-cb-tmpl-name').val(d.name);
        }

        restoreCanvasFromData(d.json, function () {
            onSelectionCleared();
            history.push(serializeCanvas());
            historyFuture = [];
            updateHistoryButtons();
        });
    });

    /* ═════════════════════════════════════════
       FIRMANTES ACTIVOS
       ═════════════════════════════════════════ */

    function populateSigners() {
        var $list   = $('#aura-cb-signers-list');
        if (!$list.length) return;
        $list.empty();
        var signers = CB.signers || [];
        if (!signers.length) {
            $list.html('<p class="aura-cb-muted">No hay firmantes activos configurados.</p>');
            return;
        }
        signers.forEach(function (s) {
            var $btn = $('<button type="button" class="aura-cb-signer-btn">')
                .html('✍ <strong>' + escHtml(s.name) + '</strong>' + (s.title ? '<small>' + escHtml(s.title) + '</small>' : ''));
            $btn.data('signer', s);
            $list.append($btn);
        });
    }

    $(document).on('click', '.aura-cb-signer-btn', function () {
        addSignerImage($(this).data('signer'));
    });

    /* ═════════════════════════════════════════
       FONDO Y ELEMENTOS MULTIMEDIA
       ═════════════════════════════════════════ */

    $(document).on('click', '#aura-cb-bg-image-btn', function (e) {
        e.preventDefault();
        if (!window.wp || !wp.media) {
            alert('La librería multimedia de WordPress no está disponible.');
            return;
        }
        var frame = wp.media({
            title    : 'Seleccionar imagen de fondo',
            button   : { text: 'Usar como fondo' },
            library  : { type: 'image' },
            multiple : false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            fabric.Image.fromURL(att.url, function (img) {
                canvas.setBackgroundImage(img, function () {
                    $('#aura-cb-bg-image-remove').show();
                    canvas.requestRenderAll();
                    saveHistoryStep();
                }, {
                    scaleX: docW / img.width,
                    scaleY: docH / img.height
                });
            }, { crossOrigin: 'anonymous' });
        });
        frame.open();
    });

    $(document).on('click', '#aura-cb-bg-image-remove', function () {
        canvas.setBackgroundImage(null, function () {
            $('#aura-cb-bg-image-remove').hide();
            canvas.requestRenderAll();
            saveHistoryStep();
        });
    });

    $(document).on('click', '.aura-cb-elem-btn[data-type="image"]', function (e) {
        e.stopImmediatePropagation();
        if (!window.wp || !wp.media) {
            alert('La librería multimedia de WordPress no está disponible.');
            return;
        }
        var frame = wp.media({
            title    : 'Insertar imagen',
            button   : { text: 'Insertar' },
            library  : { type: 'image' },
            multiple : false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            addImage(att.url);
        });
        frame.open();
    });

    $(document).on('click', '.aura-cb-elem-btn', function () {
        var type = $(this).data('type');
        switch (type) {
            case 'textbox':         addTextbox('Texto de certificado', { fontSize: 20, fontFamily: 'Lato' }); break;
            case 'heading':         addTextbox('CERTIFICADO', { top: 90, width: 620, fontSize: 44, fontFamily: 'Cinzel', fontWeight: 'bold', fill: '#0f172a', charSpacing: 120 }); break;
            case 'logo':            addOrgLogo(); break;
            case 'rect':            addRect(); break;
            case 'frame':           addFrame(); break;
            case 'circle':          addCircle(); break;
            case 'triangle':        addTriangle(); break;
            case 'star':            addStar(); break;
            case 'badge':           addBadge(); break;
            case 'line':            addLine(); break;
            case 'divider':         addOrnateDivider(); break;
            case 'qr':              addQrPlaceholder(); break;
            case 'signature-block': addSignatureBlock(); break;
            case 'seal':            addOfficialSeal(); break;
        }
    });

    /* ═════════════════════════════════════════
       VISTA PREVIA (NORMAL Y CON EJEMPLO)
       ═════════════════════════════════════════ */

    function renderPreview(withSample) {
        var prevZoom = currentZoom;
        var originals = [];

        if (withSample) {
            historyLock = true;
            function applySampleRecursive(obj) {
                if (!obj) return;
                if (obj.type === 'group' && typeof obj.getObjects === 'function') {
                    obj.getObjects().forEach(applySampleRecursive);
                    return;
                }
                if (isTextObject(obj) && typeof obj.text === 'string') {
                    originals.push({ obj: obj, text: obj.text });
                    var replaced = obj.text;
                    Object.keys(SAMPLE_DATA).forEach(function (k) {
                        replaced = replaced.split(k).join(SAMPLE_DATA[k]);
                    });
                    obj.set('text', replaced);
                }
            }
            canvas.getObjects().forEach(applySampleRecursive);
        }

        canvas.discardActiveObject();
        canvas.setDimensions({ width: docW, height: docH });
        canvas.setZoom(1);
        canvas.renderAll();

        var dataUrl = '';
        try {
            dataUrl = canvas.toDataURL({ format: 'jpeg', quality: 0.92 });
        } catch (err) {
            console.warn('[AuraCertBuilder] toDataURL falló:', err);
        }

        if (withSample && originals.length) {
            originals.forEach(function (item) {
                item.obj.set('text', item.text);
            });
            historyLock = false;
        }

        currentZoom = prevZoom;
        applyZoom();

        if (!dataUrl) {
            alert('No se pudo generar la vista previa (alguna imagen externa bloquea canvas CORS).');
            return;
        }

        var w = window.open('', '_blank');
        if (w) {
            w.document.write(
                '<html><head><title>' + (withSample ? 'Vista Previa con Datos de Ejemplo' : 'Vista Previa de Plantilla') + '</title></head>' +
                '<body style="margin:0;background:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh">' +
                '<img src="' + dataUrl + '" style="max-width:95vw;max-height:95vh;box-shadow:0 12px 40px rgba(0,0,0,.6);background:#fff;"/>' +
                '</body></html>'
            );
            w.document.close();
        }
    }

    $(document).on('click', '#aura-cb-preview', function () { renderPreview(false); });
    $(document).on('click', '#aura-cb-preview-sample', function () { renderPreview(true); });

    /* ═════════════════════════════════════════
       GUARDAR PLANTILLA (+ THUMBNAIL)
       ═════════════════════════════════════════ */

    function saveTemplate(exitAfter) {
        var name = $('#aura-cb-tmpl-name').val().trim();
        if (!name) {
            alert('El nombre de la plantilla es obligatorio.');
            $('#aura-cb-tmpl-name').focus();
            return;
        }

        var $btn     = $('#aura-cb-save');
        var $exitBtn = $('#aura-cb-save-exit');
        $btn.prop('disabled', true);
        $exitBtn.prop('disabled', true);
        showSaveStatus('Guardando…', false);

        var orientation = $('#aura-cb-orientation').val() || 'landscape';
        var dim         = DIM[orientation] || DIM.landscape;
        var desc        = $('#aura-cb-tmpl-desc').val().trim();

        var prevZoom = currentZoom;
        canvas.discardActiveObject();
        canvas.setDimensions({ width: docW, height: docH });
        canvas.setZoom(1);
        canvas.renderAll();

        var canvasJson   = serializeCanvas();
        var thumbnailB64 = '';
        try {
            thumbnailB64 = canvas.toDataURL({ format: 'png', quality: 0.82 });
        } catch (err) {
            console.warn('[AuraCertBuilder] No se pudo generar miniatura:', err);
        }

        currentZoom = prevZoom;
        applyZoom();

        ajaxPost('aura_cert_save_template', {
            id          : TMPL_ID,
            name        : name,
            description : desc,
            orientation : orientation,
            width_mm    : dim.w_mm,
            height_mm   : dim.h_mm,
            design_json : canvasJson
        }).done(function (res) {
            if (!res.success || !res.data) {
                var errMsg = extractError(res, 'Error al guardar plantilla.');
                showSaveStatus(errMsg, true);
                alert(errMsg);
                $btn.prop('disabled', false);
                $exitBtn.prop('disabled', false);
                return;
            }

            var savedId = parseInt(res.data.id, 10) || TMPL_ID;
            TMPL_ID = savedId;
            $('#aura-cb-template-id').val(savedId);
            $('#aura-cb-topbar-title').text('Editar: ' + name);

            if (window.history && window.history.replaceState && window.location.search.indexOf('id=new') !== -1) {
                var newUrl = window.location.href.replace('id=new', 'id=' + savedId);
                window.history.replaceState(null, '', newUrl);
            }

            var finishSave = function () {
                $btn.prop('disabled', false);
                $exitBtn.prop('disabled', false);
                showSaveStatus('✓ Guardado', false);
                if (exitAfter) {
                    var listUrl = $('#aura-cb-templates-list-url').val() || CB.templatesUrl;
                    if (listUrl) {
                        window.location.href = listUrl;
                    }
                }
            };

            if (thumbnailB64) {
                ajaxPost('aura_cert_save_thumbnail', {
                    template_id : savedId,
                    data_url    : thumbnailB64
                }).always(finishSave);
            } else {
                finishSave();
            }
        }).fail(function () {
            showSaveStatus('Error de conexión', true);
            alert('Error de conexión al guardar.');
            $btn.prop('disabled', false);
            $exitBtn.prop('disabled', false);
        });
    }

    $(document).on('click', '#aura-cb-save', function () { saveTemplate(false); });
    $(document).on('click', '#aura-cb-save-exit', function () { saveTemplate(true); });

    /* ═════════════════════════════════════════
       INICIALIZACIÓN Y ATAJOS DE TECLADO
       ═════════════════════════════════════════ */

    $(function () {
        if (!document.getElementById('aura-cert-canvas')) return;
        if (typeof fabric === 'undefined') {
            alert('Error: No se pudo cargar Fabric.js. Verifica tu conexión o recarga la página.');
            return;
        }

        initCanvas();
        populateFonts();
        populateVarsList('');
        populatePrebuilt();
        populateSigners();
        bindPropInputs();
        updateZoomLabel();

        var wrapEl = document.querySelector('.aura-cert-builder-canvas-wrap');
        if (wrapEl) {
            wrapEl.addEventListener('wheel', function (e) {
                if (!e.ctrlKey && !e.metaKey) return;
                e.preventDefault();
                if (e.deltaY < 0) { zoomIn(); } else { zoomOut(); }
            }, { passive: false });
        }

        $(document).on('keydown', function (e) {
            var activeEl = document.activeElement;
            var isTyping = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT');
            var activeObj = canvas ? canvas.getActiveObject() : null;
            var isEditingCanvasText = activeObj && activeObj.isEditing;

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                saveTemplate(false);
                return;
            }

            if (isTyping || isEditingCanvasText) return;

            if (e.key === 'Escape' && canvas) {
                canvas.discardActiveObject();
                canvas.requestRenderAll();
                onSelectionCleared();
            } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            } else if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) {
                e.preventDefault();
                redo();
            } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd' && activeObj) {
                e.preventDefault();
                cloneSelected();
            } else if ((e.key === 'Delete' || e.key === 'Backspace') && activeObj) {
                e.preventDefault();
                deleteSelected();
            } else if (activeObj && !activeObj.auraLocked && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) !== -1) {
                e.preventDefault();
                var step = e.shiftKey ? 10 : 1;
                if (e.key === 'ArrowUp')    activeObj.set('top',  (activeObj.top || 0) - step);
                if (e.key === 'ArrowDown')  activeObj.set('top',  (activeObj.top || 0) + step);
                if (e.key === 'ArrowLeft')  activeObj.set('left', (activeObj.left || 0) - step);
                if (e.key === 'ArrowRight') activeObj.set('left', (activeObj.left || 0) + step);
                activeObj.setCoords();
                canvas.requestRenderAll();
                updateObjectPanel(activeObj);
                saveHistoryStep();
            }
        });

        var resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                var $wrap = $('.aura-cert-builder-canvas-wrap');
                if (!$wrap.length) return;
                var areaW = $wrap.width() - 60;
                var areaH = $wrap.height() - 60;
                var fitZ  = Math.min(areaW / docW, areaH / docH, 1);
                if (currentZoom <= fitZ + 0.05) {
                    fitToScreen();
                }
            }, 200);
        });
    });

}(jQuery));
