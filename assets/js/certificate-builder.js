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

    var CUSTOM_PROPS = [
        'name', 'auraElementType', 'signerSlot',
        'auraSvgMarkup', 'auraSvgOriginal', 'auraClipartLabel',
        'auraPhotoShape', 'auraPhotoRadius', 'auraPhotoPad', 'auraPhotoBorderStyle', 'auraCustomPhotoSrc',
        'rx', 'ry', 'strokeDashArray', 'auraLocked', 'shadow', 'perPixelTargetFind', 'visible'
    ];

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
        '{firmante_2_cargo}'    : (CB.signers && CB.signers[1] && CB.signers[1].title) || 'Coordinadora General',
        '{firmante_3_nombre}'   : (CB.signers && CB.signers[2] && CB.signers[2].name) || 'Mg. Andrés Torres',
        '{firmante_3_cargo}'    : (CB.signers && CB.signers[2] && CB.signers[2].title) || 'Secretario Académico',
        '{firmante_4_nombre}'   : (CB.signers && CB.signers[3] && CB.signers[3].name) || 'Lic. Paula Mendoza',
        '{firmante_4_cargo}'    : (CB.signers && CB.signers[3] && CB.signers[3].title) || 'Decana de Facultad'
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
        var label = orientation.indexOf('letter') === 0 ? 'Carta' : 'A4';
        $('#aura-cb-hud-dims').text(label + ' · ' + d.w_mm + ' × ' + d.h_mm + ' mm (' + d.w + '×' + d.h + 'px)');
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

    function isPhotoObject(obj) {
        return !!(obj && (obj.auraElementType === 'student_photo' || obj.name === 'student_photo_placeholder'));
    }

    function getObjectLabel(obj, idx) {
        if (!obj) return 'Elemento ' + (idx + 1);
        if (isPhotoObject(obj)) return '👤 Foto Estudiante';
        if (obj.auraElementType === 'qr' || obj.name === 'qr_placeholder') return 'Código QR';
        if (obj.auraElementType === 'signer_sig' || (obj.name && String(obj.name).indexOf('signer_slot_') === 0)) {
            var slotNum = obj.signerSlot || String(obj.name || '').replace('signer_slot_', '') || '1';
            return '✍ Slot Firma #' + slotNum;
        }
        if (obj.auraElementType === 'svg_clipart' || obj.auraSvgMarkup) {
            return '🎨 ' + (obj.auraClipartLabel || 'SVG Clipart');
        }
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

    function syncTransparentHitTest(obj) {
        if (!obj) return;
        var isTransparent = !obj.fill || obj.fill === 'transparent' || obj.fill === 'none';
        if (obj.type === 'rect' || obj.type === 'circle' || obj.type === 'triangle' || obj.type === 'polygon' || obj.name === 'decorative_frame') {
            obj.set('perPixelTargetFind', isTransparent);
        }
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
            selection              : true,
            targetFindTolerance    : 8
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

        var srcW = (parsed.canvasW || docW);
        var srcH = (parsed.canvasH || docH);
        if (parsed.canvasW && parsed.canvasH && !onDone) {
            docW = parsed.canvasW;
            docH = parsed.canvasH;
        }
        preloadCanvasFonts(parsed.objects || []);

        canvas.loadFromJSON(parsed, function () {
            canvas.getObjects().forEach(function (obj) {
                if (obj.auraLocked) {
                    applyLockState(obj, true);
                }
                if (obj.visible === false) {
                    obj.set({ selectable: false, evented: false });
                }
                if (isPhotoObject(obj) || (obj.type === 'image' && obj.auraPhotoShape)) {
                    decoratePhotoObject(obj);
                }
                syncTransparentHitTest(obj);
            });

            if (srcW && srcH && (Math.abs(srcW - docW) > 4 || Math.abs(srcH - docH) > 4)) {
                adaptCanvasToDimensions(srcW, srcH, docW, docH);
            }

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
        var areaW = Math.max(240, $wrap.width() - 44);
        var areaH = Math.max(200, $wrap.height() - 48);
        currentZoom = Math.min(areaW / docW, areaH / docH, 1.35);
        currentZoom = Math.max(0.15, Math.round(currentZoom * 100) / 100);
        applyZoom();
    }

    function zoomIn()  { currentZoom = Math.min(3, Math.round((currentZoom + 0.1) * 10) / 10); applyZoom(); }
    function zoomOut() { currentZoom = Math.max(0.15, Math.round((currentZoom - 0.1) * 10) / 10); applyZoom(); }

    function updateZoomLabel() {
        var pct = Math.round(currentZoom * 100) + '%';
        $('#aura-cb-zoom-label').text(pct);
        var $sel = $('#aura-cb-zoom-select');
        if ($sel.length) {
            var matched = false;
            $sel.find('option').each(function () {
                if (this.value !== 'fit' && Math.abs(parseFloat(this.value) - currentZoom) < 0.02) {
                    $sel.val(this.value);
                    matched = true;
                    return false;
                }
            });
            if (!matched) $sel.val('fit');
        }
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
        if (e && e.e) {
            $('.aura-cb-rpane-tab[data-rpane="props"]').trigger('click');
        }
    }

    function onSelectionCleared() {
        $('#aura-cb-obj-props').hide();
        $('#aura-cb-text-props').hide();
        $('#aura-cb-shape-props').hide();
        $('#aura-cb-photo-props').hide();
        $('#aura-cb-svg-props').hide();
        renderLayersList();
    }

    function renderPhotoPropsPanel(obj) {
        if (!obj) return;
        var shape  = obj.auraPhotoShape || 'circle';
        var radius = obj.auraPhotoRadius != null ? parseInt(obj.auraPhotoRadius, 10) : (obj.rx || 20);
        var bStyle = obj.auraPhotoBorderStyle || (Array.isArray(obj.strokeDashArray) && obj.strokeDashArray.length ? 'dashed' : ((obj.strokeWidth || 0) > 0 ? 'solid' : 'none'));
        var bWidth = obj.strokeWidth != null ? parseFloat(obj.strokeWidth) : 4;
        var bColor = toHexColor(obj.stroke, '#c9a84c');
        var bgCol  = toHexColor(obj.fill, '#ffffff');
        var pad    = obj.auraPhotoPad != null ? parseInt(obj.auraPhotoPad, 10) : 4;

        $('.aura-cb-photo-shape-btn').removeClass('is-active');
        $('.aura-cb-photo-shape-btn[data-photo-shape="' + shape + '"]').addClass('is-active');
        $('#aura-cb-photo-radius-row').toggle(shape === 'rounded');
        $('#aura-cb-photo-radius, #aura-cb-photo-radius-range').val(radius);

        $('#aura-cb-photo-border-style').val(bStyle);
        $('#aura-cb-photo-border-width').val(bWidth);
        $('#aura-cb-photo-border-color').val(bColor);
        $('#aura-cb-photo-bg-color').val(bgCol);
        $('#aura-cb-photo-pad').val(pad);
        $('#aura-cb-photo-sample-clear').toggle(!!obj.auraCustomPhotoSrc);
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
            $('#aura-cb-photo-props').hide();
            $('#aura-cb-svg-props').hide();

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
        } else if (isPhotoObject(obj) || obj.type === 'image') {
            $('#aura-cb-text-props').hide();
            $('#aura-cb-shape-props').hide();
            $('#aura-cb-svg-props').hide();
            $('#aura-cb-photo-props').show();
            renderPhotoPropsPanel(obj);
        } else if (obj.auraElementType === 'svg_clipart' || obj.auraSvgMarkup) {
            $('#aura-cb-text-props').hide();
            $('#aura-cb-shape-props').hide();
            $('#aura-cb-photo-props').hide();
            $('#aura-cb-svg-props').show();
            renderSvgColorsPanel(obj);
        } else {
            $('#aura-cb-text-props').hide();
            $('#aura-cb-photo-props').hide();
            $('#aura-cb-svg-props').hide();

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

    var ICON_EYE_OPEN   = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var ICON_EYE_CLOSED = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    var ICON_CHAIN_LOCK = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

    function getLayerTypeBadge(obj) {
        if (!obj) return '◆';
        if (isPhotoObject(obj)) return '👤';
        if (obj.auraElementType === 'qr' || obj.name === 'qr_placeholder') return 'QR';
        if (obj.auraElementType === 'signer_sig' || (obj.name && String(obj.name).indexOf('signer_slot_') === 0)) return '✍';
        if (obj.auraElementType === 'svg_clipart' || obj.auraSvgMarkup) return 'SVG';
        if (obj.name === 'decorative_frame') return '▣';
        if (isTextObject(obj)) return 'T';
        if (obj.type === 'image') return '🖼';
        return '◇';
    }

    function renderLayersList() {
        var $list = $('#aura-cb-layers-list');
        if (!$list.length || !canvas) return;
        $list.empty();

        var objs   = canvas.getObjects();
        var active = canvas.getActiveObject();
        $('#aura-cb-layers-count').text(objs.length);

        var hasSel = !!active;
        $('.aura-cb-layers-toolbar button').prop('disabled', !hasSel);

        if (!objs.length) {
            $list.html('<p class="aura-cb-muted">Sin elementos en el lienzo.</p>');
            return;
        }

        for (var i = objs.length - 1; i >= 0; i--) {
            var obj      = objs[i];
            var isSel    = active === obj;
            var isHidden = obj.visible === false;
            var isLocked = !!obj.auraLocked;
            var canUp    = i < objs.length - 1;
            var canDown  = i > 0;

            var $row = $('<div class="aura-cb-layer-item" draggable="true">')
                .toggleClass('is-selected', isSel)
                .toggleClass('is-hidden-layer', isHidden)
                .toggleClass('is-locked-layer', isLocked)
                .attr('data-index', i)
                .html(
                    '<span class="aura-cb-layer-grip" title="Arrastrar para reordenar">⠿</span>' +
                    '<span class="aura-cb-layer-badge">' + escHtml(getLayerTypeBadge(obj)) + '</span>' +
                    '<span class="aura-cb-layer-name" title="' + escHtml(getObjectLabel(obj, i)) + '">' + escHtml(getObjectLabel(obj, i)) + '</span>' +
                    '<span class="aura-cb-layer-actions">' +
                        '<button type="button" class="aura-cb-layer-act aura-cb-layer-move-up" title="Subir capa (encima)"' + (canUp ? '' : ' disabled') + '>↑</button>' +
                        '<button type="button" class="aura-cb-layer-act aura-cb-layer-move-down" title="Bajar capa (detrás)"' + (canDown ? '' : ' disabled') + '>↓</button>' +
                        '<button type="button" class="aura-cb-layer-act aura-cb-layer-vis' + (isHidden ? ' is-off' : '') + '" title="' + (isHidden ? 'Mostrar capa' : 'Ocultar capa') + '">' + (isHidden ? ICON_EYE_CLOSED : ICON_EYE_OPEN) + '</button>' +
                        '<button type="button" class="aura-cb-layer-act aura-cb-layer-lock' + (isLocked ? ' is-on' : '') + '" title="' + (isLocked ? 'Desbloquear (eslabón activo)' : 'Bloquear posición (eslabón)') + '">' + ICON_CHAIN_LOCK + '</button>' +
                    '</span>'
                );
            $list.append($row);
        }
    }

    // Seleccionar capa al hacer clic en su fila
    $(document).on('click', '.aura-cb-layer-item', function (e) {
        if ($(e.target).closest('.aura-cb-layer-act').length) return;
        var idx  = parseInt($(this).attr('data-index'), 10);
        var objs = canvas.getObjects();
        var obj  = !isNaN(idx) ? objs[idx] : null;
        if (!obj) return;
        if (obj.visible === false) {
            obj.set({ visible: true, selectable: true, evented: true });
        }
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        updateObjectPanel(obj);
        renderLayersList();
    });

    // Ver / Ocultar capa (Ojo)
    $(document).on('click', '.aura-cb-layer-vis', function (e) {
        e.stopPropagation();
        var idx  = parseInt($(this).closest('.aura-cb-layer-item').attr('data-index'), 10);
        var objs = canvas.getObjects();
        var obj  = !isNaN(idx) ? objs[idx] : null;
        if (!obj) return;
        var nextVis = (obj.visible === false);
        obj.set({
            visible    : nextVis,
            selectable : nextVis,
            evented    : nextVis
        });
        if (!nextVis && canvas.getActiveObject() === obj) {
            canvas.discardActiveObject();
            onSelectionCleared();
        }
        canvas.requestRenderAll();
        renderLayersList();
        saveHistoryStep();
    });

    // Bloquear / Desbloquear capa (Eslabón de cadena)
    $(document).on('click', '.aura-cb-layer-lock', function (e) {
        e.stopPropagation();
        var idx  = parseInt($(this).closest('.aura-cb-layer-item').attr('data-index'), 10);
        var objs = canvas.getObjects();
        var obj  = !isNaN(idx) ? objs[idx] : null;
        if (!obj) return;
        var nextLock = !obj.auraLocked;
        applyLockState(obj, nextLock);
        if (canvas.getActiveObject() === obj) {
            $('#aura-cb-obj-lock').prop('checked', nextLock);
        }
        canvas.requestRenderAll();
        renderLayersList();
        saveHistoryStep();
    });

    // Subir / Bajar 1 nivel desde botones de cada fila
    $(document).on('click', '.aura-cb-layer-move-up', function (e) {
        e.stopPropagation();
        var idx  = parseInt($(this).closest('.aura-cb-layer-item').attr('data-index'), 10);
        var objs = canvas.getObjects();
        var obj  = !isNaN(idx) ? objs[idx] : null;
        if (!obj) return;
        canvas.bringForward(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        updateObjectPanel(obj);
        renderLayersList();
        saveHistoryStep();
    });

    $(document).on('click', '.aura-cb-layer-move-down', function (e) {
        e.stopPropagation();
        var idx  = parseInt($(this).closest('.aura-cb-layer-item').attr('data-index'), 10);
        var objs = canvas.getObjects();
        var obj  = !isNaN(idx) ? objs[idx] : null;
        if (!obj) return;
        canvas.sendBackwards(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        updateObjectPanel(obj);
        renderLayersList();
        saveHistoryStep();
    });

    // Toolbar superior dentro de la pestaña Capas
    $(document).on('click', '#aura-cb-layer-front', function () {
        var obj = canvas.getActiveObject();
        if (obj) { canvas.bringToFront(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
    });
    $(document).on('click', '#aura-cb-layer-up', function () {
        var obj = canvas.getActiveObject();
        if (obj) { canvas.bringForward(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
    });
    $(document).on('click', '#aura-cb-layer-down', function () {
        var obj = canvas.getActiveObject();
        if (obj) { canvas.sendBackwards(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
    });
    $(document).on('click', '#aura-cb-layer-back', function () {
        var obj = canvas.getActiveObject();
        if (obj) { canvas.sendToBack(obj); canvas.requestRenderAll(); renderLayersList(); saveHistoryStep(); }
    });

    // Drag & Drop entre capas
    var draggedLayerIdx = null;

    $(document).on('dragstart', '.aura-cb-layer-item', function (e) {
        draggedLayerIdx = parseInt($(this).attr('data-index'), 10);
        $(this).addClass('is-dragging');
        if (e.originalEvent && e.originalEvent.dataTransfer) {
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/plain', String(draggedLayerIdx));
        }
    });

    $(document).on('dragend', '.aura-cb-layer-item', function () {
        draggedLayerIdx = null;
        $('.aura-cb-layer-item').removeClass('is-dragging is-drop-before is-drop-after');
    });

    $(document).on('dragover', '.aura-cb-layer-item', function (e) {
        e.preventDefault();
        if (draggedLayerIdx === null) return;
        var rect = this.getBoundingClientRect();
        var isBefore = (e.clientY - rect.top) < (rect.height / 2);
        $('.aura-cb-layer-item').removeClass('is-drop-before is-drop-after');
        $(this).addClass(isBefore ? 'is-drop-before' : 'is-drop-after');
    });

    $(document).on('drop', '.aura-cb-layer-item', function (e) {
        e.preventDefault();
        var toIdx   = parseInt($(this).attr('data-index'), 10);
        var fromIdx = draggedLayerIdx;
        var isBefore = $(this).hasClass('is-drop-before');
        $('.aura-cb-layer-item').removeClass('is-dragging is-drop-before is-drop-after');
        draggedLayerIdx = null;

        if (fromIdx === null || isNaN(fromIdx) || isNaN(toIdx) || fromIdx === toIdx) return;
        var objs = canvas.getObjects();
        var n    = objs.length;
        var obj  = objs[fromIdx];
        if (!obj || n < 2) return;

        var fromUi   = (n - 1) - fromIdx;
        var toUi     = (n - 1) - toIdx;
        var insertUi = isBefore ? toUi : (toUi + 1);
        if (fromUi < insertUi) insertUi -= 1;
        var newFabricIdx = Math.max(0, Math.min(n - 1, (n - 1) - insertUi));

        obj.moveTo(newFabricIdx);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        updateObjectPanel(obj);
        renderLayersList();
        saveHistoryStep();
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
                syncTransparentHitTest(obj);
                canvas.requestRenderAll();
                saveHistoryStep();
            }
        });

        $('#aura-cb-obj-fill-transparent').on('click', function () {
            var obj = canvas.getActiveObject();
            if (obj && !isTextObject(obj)) {
                obj.set('fill', 'transparent');
                syncTransparentHitTest(obj);
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

        // Color de fondo y orientación del documento (reflow inteligente entre A4/Carta/Horiz/Vert)
        $('#aura-cb-bg-color').on('input change', function () {
            canvas.setBackgroundColor($(this).val(), function () {
                canvas.requestRenderAll();
                saveHistoryStep();
            });
        });

        $('#aura-cb-orientation').on('change', function () {
            var ori   = $(this).val();
            var dim   = DIM[ori] || DIM.landscape;
            var oldW  = docW;
            var oldH  = docH;
            docW = dim.w;
            docH = dim.h;
            syncDimInputs(ori);
            adaptCanvasToDimensions(oldW, oldH, docW, docH);
            fitToScreen();
            updateObjectPanel(canvas.getActiveObject());
            saveHistoryStep();
        });
    }

    /**
     * Adapta proporcionalmente todos los elementos cuando cambia el tamaño/orientación
     * del documento (ej. A4 -> Carta o Horizontal <-> Vertical).
     */
    function adaptCanvasToDimensions(oldW, oldH, newW, newH) {
        if (!canvas || !oldW || !oldH || (Math.abs(oldW - newW) < 2 && Math.abs(oldH - newH) < 2)) {
            return;
        }
        var rx        = newW / oldW;
        var ry        = newH / oldH;
        var uScale    = Math.min(rx, ry);
        var flipped   = (oldW > oldH) !== (newW > newH);
        var fontScale = flipped ? Math.max(0.74, Math.min(1.12, uScale * 1.1)) : Math.max(0.88, Math.min(1.06, uScale));

        if (canvas.backgroundImage) {
            var bg = canvas.backgroundImage;
            bg.set({
                scaleX : newW / (bg.width || newW),
                scaleY : newH / (bg.height || newH)
            });
        }

        canvas.getObjects().forEach(function (obj) {
            var curW = obj.getScaledWidth ? obj.getScaledWidth() : ((obj.width || 0) * (obj.scaleX || 1));
            var curH = obj.getScaledHeight ? obj.getScaledHeight() : ((obj.height || 0) * (obj.scaleY || 1));
            var isFrameOrWide = (
                obj.name === 'decorative_frame' ||
                (obj.type === 'rect' && (curW > oldW * 0.65 || curH > oldH * 0.65 || curW > oldW * 0.65 && curH <= 8))
            );

            if (isFrameOrWide) {
                var newRectW = Math.max(4, curW * rx);
                var newRectH = Math.max(1, curH * ry);
                obj.set({
                    left   : Math.round((obj.left || 0) * rx),
                    top    : Math.round((obj.top || 0) * ry),
                    width  : Math.round(newRectW),
                    height : Math.round(newRectH),
                    scaleX : 1,
                    scaleY : 1
                });
            } else if (obj.type === 'line') {
                obj.set({
                    left   : Math.round((obj.left || 0) * rx),
                    top    : Math.round((obj.top || 0) * ry),
                    scaleX : (obj.scaleX || 1) * rx,
                    scaleY : (obj.scaleY || 1) * ry
                });
            } else if (isTextObject(obj)) {
                var newBoxW = obj.width ? Math.min(newW - 24, Math.max(40, Math.round(obj.width * rx))) : obj.width;
                var newLeft = Math.round((obj.left || 0) * rx);
                if (newBoxW && newLeft + newBoxW > newW - 8) {
                    newLeft = Math.max(8, Math.round((newW - newBoxW) / 2));
                }
                obj.set({
                    left     : newLeft,
                    top      : Math.max(8, Math.round((obj.top || 0) * ry)),
                    width    : newBoxW,
                    fontSize : Math.max(8, Math.round((obj.fontSize || 16) * fontScale * 10) / 10)
                });
            } else {
                var oldCx = (obj.left || 0) + curW / 2;
                var oldCy = (obj.top || 0) + curH / 2;
                var nextScaleX = (obj.scaleX || 1) * uScale;
                var nextScaleY = (obj.scaleY || 1) * uScale;
                var nextW = (obj.width || curW) * nextScaleX;
                var nextH = (obj.height || curH) * nextScaleY;
                obj.set({
                    scaleX : nextScaleX,
                    scaleY : nextScaleY,
                    left   : Math.round(oldCx * rx - nextW / 2),
                    top    : Math.round(oldCy * ry - nextH / 2)
                });
            }
            obj.setCoords();
        });

        canvas.requestRenderAll();
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
            if (isPhotoObject(cloned) || (cloned.type === 'image' && cloned.auraPhotoShape)) {
                decoratePhotoObject(cloned);
            }
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

    function cssColorToHex(val) {
        if (!val || typeof val !== 'string') return '';
        var s = val.trim().toLowerCase();
        if (!s || s === 'none' || s === 'transparent' || s.indexOf('url(') === 0 || s === 'currentcolor') {
            return '';
        }
        if (/^#[0-9a-f]{6}$/.test(s)) return s;
        if (/^#[0-9a-f]{3}$/.test(s)) {
            return '#' + s[1] + s[1] + s[2] + s[2] + s[3] + s[3];
        }
        if (/^rgba?\(/.test(s)) return toHexColor(s, '').toLowerCase();
        try {
            var probe = document.createElement('span');
            probe.style.color = '';
            probe.style.color = s;
            if (!probe.style.color) return '';
            document.body.appendChild(probe);
            var computed = window.getComputedStyle(probe).color;
            document.body.removeChild(probe);
            return computed ? toHexColor(computed, '').toLowerCase() : '';
        } catch (e) {
            return '';
        }
    }

    function normalizeSvgForColoring(rawSvg) {
        if (!rawSvg || typeof rawSvg !== 'string') return '';
        try {
            var parser = new DOMParser();
            var doc    = parser.parseFromString(rawSvg.trim(), 'image/svg+xml');
            var root   = doc.querySelector('svg');
            if (!root) return '';

            doc.querySelectorAll('script, foreignObject, iframe, object, embed').forEach(function (n) {
                if (n.parentNode) n.parentNode.removeChild(n);
            });

            if (!root.getAttribute('xmlns')) {
                root.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            }
            if (!root.getAttribute('viewBox')) {
                var rw = parseFloat(root.getAttribute('width')) || 120;
                var rh = parseFloat(root.getAttribute('height')) || 120;
                root.setAttribute('viewBox', '0 0 ' + rw + ' ' + rh);
            }

            var shapeSelector = 'path, circle, rect, polygon, polyline, ellipse, line';
            doc.querySelectorAll(shapeSelector).forEach(function (el) {
                var st = el.getAttribute('style') || '';
                if (st) {
                    var fillMatch = st.match(/(?:^|;)\s*fill\s*:\s*([^;]+)/i);
                    if (fillMatch && !el.getAttribute('fill')) el.setAttribute('fill', fillMatch[1].trim());
                    var strokeMatch = st.match(/(?:^|;)\s*stroke\s*:\s*([^;]+)/i);
                    if (strokeMatch && !el.getAttribute('stroke')) el.setAttribute('stroke', strokeMatch[1].trim());
                    el.removeAttribute('style');
                }

                var fAttr = el.getAttribute('fill');
                if (fAttr) {
                    var fHex = cssColorToHex(fAttr);
                    if (fHex) el.setAttribute('fill', fHex);
                } else {
                    var pFill = el.parentElement && el.parentElement.getAttribute('fill');
                    if (el.tagName.toLowerCase() === 'line' || el.tagName.toLowerCase() === 'polyline') {
                        el.setAttribute('fill', 'none');
                    } else if (pFill) {
                        var pfHex = cssColorToHex(pFill);
                        if (pfHex) el.setAttribute('fill', pfHex);
                    } else {
                        el.setAttribute('fill', '#1e293b');
                    }
                }

                var sAttr = el.getAttribute('stroke');
                if (sAttr) {
                    var sHex = cssColorToHex(sAttr);
                    if (sHex) el.setAttribute('stroke', sHex);
                }
            });

            return new XMLSerializer().serializeToString(root);
        } catch (e) {
            return rawSvg;
        }
    }

    function updateSvgMarkupColors(svgMarkup, mutator) {
        if (!svgMarkup) return svgMarkup;
        try {
            var doc  = new DOMParser().parseFromString(svgMarkup, 'image/svg+xml');
            var root = doc.querySelector('svg');
            if (!root) return svgMarkup;
            doc.querySelectorAll('path, circle, rect, polygon, polyline, ellipse, line, g').forEach(function (el) {
                ['fill', 'stroke'].forEach(function (attr) {
                    var raw = el.getAttribute(attr);
                    var hex = cssColorToHex(raw);
                    if (hex) {
                        var next = mutator(hex, attr, el);
                        if (next) el.setAttribute(attr, next);
                    }
                });
            });
            return new XMLSerializer().serializeToString(root);
        } catch (e) {
            return svgMarkup;
        }
    }

    function getSvgColorTargets(obj) {
        if (!obj) return [];
        if (obj.type === 'group' && typeof obj.getObjects === 'function') {
            var out = [];
            obj.getObjects().forEach(function (child) {
                out = out.concat(getSvgColorTargets(child));
            });
            return out;
        }
        return [obj];
    }

    function extractSvgColors(obj) {
        var colors = [];
        getSvgColorTargets(obj).forEach(function (t) {
            [t.fill, t.stroke].forEach(function (raw) {
                var hex = cssColorToHex(raw);
                if (hex && colors.indexOf(hex) === -1) {
                    colors.push(hex);
                }
            });
        });
        return colors;
    }

    function replaceSvgColor(obj, oldHex, newHex) {
        var targetOld = oldHex.toLowerCase();
        var nextHex   = newHex.toLowerCase();
        getSvgColorTargets(obj).forEach(function (t) {
            if (cssColorToHex(t.fill) === targetOld) {
                t.set('fill', nextHex);
            }
            if (cssColorToHex(t.stroke) === targetOld) {
                t.set('stroke', nextHex);
            }
        });
        if (obj.auraSvgMarkup) {
            obj.auraSvgMarkup = updateSvgMarkupColors(obj.auraSvgMarkup, function (hex) {
                return hex === targetOld ? nextHex : hex;
            });
        }
        obj.dirty = true;
        canvas.requestRenderAll();
    }

    function tintSvgMonochrome(obj, newHex) {
        var nextHex = newHex.toLowerCase();
        getSvgColorTargets(obj).forEach(function (t) {
            if (cssColorToHex(t.fill)) t.set('fill', nextHex);
            if (cssColorToHex(t.stroke)) t.set('stroke', nextHex);
        });
        if (obj.auraSvgMarkup) {
            obj.auraSvgMarkup = updateSvgMarkupColors(obj.auraSvgMarkup, function () {
                return nextHex;
            });
        }
        obj.dirty = true;
        canvas.requestRenderAll();
        renderSvgColorsPanel(obj);
    }

    function resetSvgColors(obj) {
        if (!obj || !obj.auraSvgOriginal) return;
        fabric.loadSVGFromString(obj.auraSvgOriginal, function (srcObjs) {
            var destTargets = getSvgColorTargets(obj);
            var flatSrc     = [];
            srcObjs.forEach(function (s) {
                flatSrc = flatSrc.concat(getSvgColorTargets(s));
            });
            destTargets.forEach(function (t, idx) {
                if (flatSrc[idx]) {
                    t.set({
                        fill   : flatSrc[idx].fill,
                        stroke : flatSrc[idx].stroke
                    });
                }
            });
            obj.auraSvgMarkup = obj.auraSvgOriginal;
            obj.dirty         = true;
            canvas.requestRenderAll();
            renderSvgColorsPanel(obj);
            saveHistoryStep();
        });
    }

    function renderSvgColorsPanel(obj) {
        var $list = $('#aura-cb-svg-colors-list');
        if (!$list.length || !obj) return;
        $list.empty();
        var colors = extractSvgColors(obj);
        if (!colors.length) {
            $list.html('<p class="aura-cb-muted">No se detectaron colores editables en este SVG.</p>');
            return;
        }
        colors.forEach(function (hex, i) {
            var $row = $('<div class="aura-cb-svg-color-row">');
            var $lbl = $('<span>').text('Color ' + (i + 1));
            var $hex = $('<code>').text(hex);
            var $inp = $('<input type="color" class="aura-cb-svg-color-input">')
                .val(hex)
                .attr('data-old-color', hex);
            $row.append($inp, $lbl, $hex);
            $list.append($row);
        });
        $('#aura-cb-svg-mono-color').val(colors[0]);
    }

    function addSvgFromString(rawSvg, label, extraOpts) {
        var cleanSvg = normalizeSvgForColoring(rawSvg);
        if (!cleanSvg || typeof fabric === 'undefined') {
            alert('No se pudo leer el contenido SVG.');
            return;
        }
        fabric.loadSVGFromString(cleanSvg, function (objects, options) {
            if (!objects || !objects.length) {
                alert('El SVG no contiene trazos vectoriales visibles.');
                return;
            }
            var svgObj = fabric.util.groupSVGElements(objects, options);
            var maxDim = (extraOpts && extraOpts.maxW) || 140;
            var curW   = svgObj.width || 120;
            var curH   = svgObj.height || 120;
            var scale  = Math.min(maxDim / curW, maxDim / curH, 2.5);
            if (scale !== 1 && curW > 0) {
                svgObj.scale(scale);
            }
            svgObj.set($.extend({
                left             : Math.round((docW - svgObj.getScaledWidth()) / 2),
                top              : Math.round((docH - svgObj.getScaledHeight()) / 2),
                name             : 'svg_clipart_' + Date.now(),
                auraElementType  : 'svg_clipart',
                auraClipartLabel : label || 'Clipart SVG',
                auraSvgMarkup    : cleanSvg,
                auraSvgOriginal  : cleanSvg
            }, extraOpts || {}));
            canvas.add(svgObj);
            canvas.setActiveObject(svgObj);
            canvas.requestRenderAll();
        });
    }

    function handleImageUrlInsert(attOrUrl, label, extraOpts) {
        var url  = typeof attOrUrl === 'string' ? attOrUrl : (attOrUrl && attOrUrl.url);
        if (!url) return;
        var mime = (attOrUrl && (attOrUrl.mime || attOrUrl.subtype)) || '';
        var isSvg = mime.indexOf('svg') !== -1 || /\.svg(?:\?.*)?$/i.test(url);

        if (isSvg) {
            $.ajax({ url: url, dataType: 'text' }).done(function (svgText) {
                if (svgText && svgText.indexOf('<svg') !== -1) {
                    addSvgFromString(svgText, label || (attOrUrl && attOrUrl.title) || 'SVG', extraOpts);
                } else {
                    addImage(url, extraOpts);
                }
            }).fail(function () {
                addImage(url, extraOpts);
            });
            return;
        }
        addImage(url, extraOpts);
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
            handleImageUrlInsert(logoUrl, 'Logo Organización', { maxW: 180, top: 48, name: 'org_logo' });
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
            left               : pad,
            top                : pad,
            width              : docW - pad * 2,
            height             : docH - pad * 2,
            fill               : 'transparent',
            stroke             : '#b8860b',
            strokeWidth        : 4,
            rx                 : 4,
            ry                 : 4,
            name               : 'decorative_frame',
            perPixelTargetFind : true
        });
        canvas.add(obj);
        canvas.sendToBack(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        renderLayersList();
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

    /* ─── Foto de Perfil del Estudiante / Imágenes con Marco, Forma y Sombra ─── */

    function buildRoundedPath(ctx, x, y, w, h, r) {
        var rr = Math.max(0, Math.min(r, w / 2, h / 2));
        ctx.beginPath();
        ctx.moveTo(x + rr, y);
        ctx.arcTo(x + w, y, x + w, y + h, rr);
        ctx.arcTo(x + w, y + h, x, y + h, rr);
        ctx.arcTo(x, y + h, x, y, rr);
        ctx.arcTo(x, y, x + w, y, rr);
        ctx.closePath();
    }

    function decoratePhotoObject(obj) {
        if (!obj || obj._auraPhotoDecorated) return;
        obj._auraPhotoDecorated = true;

        function syncSampleImg(target) {
            var src = target.auraCustomPhotoSrc || '';
            if (!src) {
                target._sampleImgEl = null;
                target._sampleImgSrc = '';
                return;
            }
            if (target._sampleImgSrc === src && target._sampleImgEl) return;
            target._sampleImgSrc = src;
            var imgEl = new Image();
            imgEl.crossOrigin = 'anonymous';
            imgEl.onload = function () {
                target._sampleImgEl = imgEl;
                target.dirty = true;
                if (canvas) canvas.requestRenderAll();
            };
            imgEl.src = src;
        }

        syncSampleImg(obj);
        obj._syncSampleImg = function () { syncSampleImg(obj); };

        var baseRender = obj._render;
        obj._render = function (ctx) {
            var w      = this.width || 130;
            var h      = this.height || 130;
            var shape  = this.auraPhotoShape || 'circle';
            var bStyle = this.auraPhotoBorderStyle || (Array.isArray(this.strokeDashArray) && this.strokeDashArray.length ? 'dashed' : ((this.strokeWidth || 0) > 0 ? 'solid' : 'none'));
            var sw     = bStyle === 'none' ? 0 : Math.max(0, parseFloat(this.strokeWidth) || 0);
            var pad    = Math.max(0, parseFloat(this.auraPhotoPad) || 0);
            var rx     = shape === 'circle'
                ? Math.min(w, h) / 2
                : (shape === 'rounded' ? Math.max(4, parseFloat(this.auraPhotoRadius != null ? this.auraPhotoRadius : (this.rx || 20))) : 0);

            if (this.type === 'rect') {
                this.rx = rx;
                this.ry = rx;
            }

            // Si es un rect de student_photo o imagen con estilo
            if (isPhotoObject(this)) {
                baseRender.call(this, ctx);

                var inset = Math.max(1, sw / 2) + pad;
                var ix    = -w / 2 + inset;
                var iy    = -h / 2 + inset;
                var iw    = Math.max(16, w - inset * 2);
                var ih    = Math.max(16, h - inset * 2);
                var irx   = shape === 'circle' ? Math.min(iw, ih) / 2 : Math.max(0, rx - pad);

                ctx.save();
                if (shape === 'circle') {
                    ctx.beginPath();
                    ctx.arc(0, 0, Math.min(iw, ih) / 2, 0, Math.PI * 2);
                    ctx.closePath();
                } else {
                    buildRoundedPath(ctx, ix, iy, iw, ih, irx);
                }
                ctx.clip();

                if (this._sampleImgEl && this._sampleImgEl.complete && this._sampleImgEl.naturalWidth > 0) {
                    var nw = this._sampleImgEl.naturalWidth;
                    var nh = this._sampleImgEl.naturalHeight;
                    var sc = Math.max(iw / nw, ih / nh);
                    var dw = nw * sc;
                    var dh = nh * sc;
                    ctx.drawImage(this._sampleImgEl, -dw / 2, -dh / 2, dw, dh);
                } else {
                    // Retrato de estudiante vectorial elegante
                    var grad = ctx.createLinearGradient(ix, iy, ix, iy + ih);
                    grad.addColorStop(0, '#e2e8f0');
                    grad.addColorStop(1, '#cbd5e1');
                    ctx.fillStyle = grad;
                    ctx.fillRect(ix, iy, iw, ih);

                    // Silueta cabeza + hombros
                    var minD = Math.min(iw, ih);
                    ctx.fillStyle = '#64748b';
                    ctx.beginPath();
                    ctx.arc(0, -minD * 0.11, minD * 0.19, 0, Math.PI * 2);
                    ctx.fill();

                    ctx.beginPath();
                    ctx.ellipse(0, minD * 0.36, minD * 0.36, minD * 0.28, 0, Math.PI, 0, false);
                    ctx.closePath();
                    ctx.fill();

                    // Cinta inferior "FOTO ESTUDIANTE"
                    var bandH = Math.max(18, minD * 0.22);
                    ctx.fillStyle = 'rgba(15, 23, 42, 0.76)';
                    ctx.fillRect(ix, iy + ih - bandH, iw, bandH);
                    ctx.fillStyle = '#f8fafc';
                    ctx.font = 'bold ' + Math.max(8, Math.round(minD * 0.085)) + 'px Montserrat, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('FOTO PERFIL', 0, iy + ih - bandH / 2);
                }
                ctx.restore();

                // Si el estilo de marco es doble anillo ("double"), dibujar segundo aro interior
                if (bStyle === 'double' && sw > 0 && this.stroke) {
                    ctx.save();
                    var dOff = Math.max(3, sw * 1.15);
                    ctx.strokeStyle = this.stroke;
                    ctx.lineWidth   = Math.max(1, sw * 0.45);
                    ctx.globalAlpha = 0.78;
                    if (shape === 'circle') {
                        var rIn = Math.max(6, Math.min(w, h) / 2 - sw / 2 - dOff);
                        ctx.beginPath();
                        ctx.arc(0, 0, rIn, 0, Math.PI * 2);
                        ctx.stroke();
                    } else {
                        var bx = -w / 2 + sw / 2 + dOff;
                        var by = -h / 2 + sw / 2 + dOff;
                        var bw = Math.max(10, w - sw - dOff * 2);
                        var bh = Math.max(10, h - sw - dOff * 2);
                        buildRoundedPath(ctx, bx, by, bw, bh, Math.max(0, rx - dOff));
                        ctx.stroke();
                    }
                    ctx.restore();
                }
                return;
            }

            // Imagen subida normal con forma/marco aplicado
            if (this.type === 'image' && this.auraPhotoShape) {
                ctx.save();
                if (shape === 'circle') {
                    ctx.beginPath();
                    ctx.arc(0, 0, Math.min(w, h) / 2, 0, Math.PI * 2);
                    ctx.closePath();
                } else {
                    buildRoundedPath(ctx, -w / 2, -h / 2, w, h, rx);
                }
                ctx.clip();
                baseRender.call(this, ctx);
                ctx.restore();

                if (sw > 0 && this.stroke) {
                    ctx.save();
                    ctx.strokeStyle = this.stroke;
                    ctx.lineWidth   = sw;
                    if (bStyle === 'dashed') ctx.setLineDash([8, 5]);
                    if (shape === 'circle') {
                        ctx.beginPath();
                        ctx.arc(0, 0, Math.max(4, Math.min(w, h) / 2 - sw / 2), 0, Math.PI * 2);
                        ctx.stroke();
                    } else {
                        buildRoundedPath(ctx, -w / 2 + sw / 2, -h / 2 + sw / 2, w - sw, h - sw, rx);
                        ctx.stroke();
                    }
                    ctx.restore();
                }
                return;
            }

            baseRender.call(this, ctx);
        };
    }

    function applyPhotoStyleToObject(obj, changes) {
        if (!obj) return;
        decoratePhotoObject(obj);
        if (changes) {
            obj.set(changes);
        }
        var shape  = obj.auraPhotoShape || 'circle';
        var radius = parseInt(obj.auraPhotoRadius != null ? obj.auraPhotoRadius : 20, 10) || 20;
        var bStyle = obj.auraPhotoBorderStyle || 'solid';
        var bWidth = parseFloat(obj.strokeWidth != null ? obj.strokeWidth : 4);
        var w      = obj.width || 130;
        var h      = obj.height || 130;

        if (bStyle === 'none') {
            obj.set({ strokeWidth: 0, strokeDashArray: null });
        } else {
            if (!bWidth || bWidth <= 0) bWidth = 3;
            obj.set({
                strokeWidth     : bWidth,
                stroke          : obj.stroke || '#c9a84c',
                strokeDashArray : bStyle === 'dashed' ? [7, 5] : null
            });
        }

        if (obj.type === 'rect') {
            var rx = shape === 'circle' ? Math.round(Math.min(w, h) / 2) : (shape === 'rounded' ? radius : 0);
            obj.set({ rx: rx, ry: rx });
        }

        if (typeof obj._syncSampleImg === 'function') {
            obj._syncSampleImg();
        }
        obj.dirty = true;
        canvas.requestRenderAll();
    }

    function addStudentPhotoPlaceholder() {
        var size = 136;
        var obj  = new fabric.Rect({
            left                 : Math.round((docW - size) / 2),
            top                  : Math.round(docH * 0.14),
            width                : size,
            height               : size,
            fill                 : '#ffffff',
            stroke               : '#c9a84c',
            strokeWidth          : 4,
            rx                   : size / 2,
            ry                   : size / 2,
            name                 : 'student_photo_placeholder',
            auraElementType      : 'student_photo',
            auraPhotoShape       : 'circle',
            auraPhotoRadius      : 22,
            auraPhotoPad         : 4,
            auraPhotoBorderStyle : 'double',
            shadow               : new fabric.Shadow({ color: 'rgba(15, 23, 42, 0.25)', blur: 12, offsetX: 0, offsetY: 4 })
        });
        decoratePhotoObject(obj);
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        updateObjectPanel(obj);
        renderLayersList();
    }

    function createSingleSignatureColumn(slot, centerX, lineTop, blockW) {
        var sigW = Math.min(130, blockW - 14);
        var sigH = 44;
        var sigSlot = new fabric.Rect({
            left            : Math.round(centerX - sigW / 2),
            top             : Math.round(lineTop - sigH - 6),
            width           : sigW,
            height          : sigH,
            fill            : 'rgba(99, 102, 241, 0.05)',
            stroke          : '#94a3b8',
            strokeWidth     : 1,
            strokeDashArray : [4, 3],
            rx              : 4,
            ry              : 4,
            name            : 'signer_slot_' + slot,
            auraElementType : 'signer_sig',
            signerSlot      : slot
        });
        var line = new fabric.Line([
            Math.round(centerX - (blockW - 16) / 2), lineTop,
            Math.round(centerX + (blockW - 16) / 2), lineTop
        ], {
            stroke      : '#1e293b',
            strokeWidth : 1.5
        });
        var txt = new fabric.Textbox('{firmante_' + slot + '_nombre}\n{firmante_' + slot + '_cargo}', {
            left       : Math.round(centerX - blockW / 2),
            top        : lineTop + 8,
            width      : blockW,
            fontSize   : slot > 2 ? 11.5 : 13,
            fontFamily : 'Lato',
            fill       : '#1e293b',
            textAlign  : 'center',
            lineHeight : 1.25
        });
        return [sigSlot, line, txt];
    }

    function insertSignaturesRow(count) {
        count = Math.max(1, Math.min(4, parseInt(count, 10) || 1));
        var marginX  = Math.round(docW * 0.06);
        var usableW  = docW - marginX * 2;
        var gap      = count === 4 ? 16 : (count === 3 ? 24 : 40);
        var blockW   = Math.min(230, Math.floor((usableW - gap * (count - 1)) / count));
        var totalRow = blockW * count + gap * (count - 1);
        var startX   = Math.round((docW - totalRow) / 2);
        var lineTop  = docH - 135;
        var added    = [];

        historyLock = true;
        for (var i = 0; i < count; i++) {
            var slot    = i + 1;
            var centerX = startX + i * (blockW + gap) + blockW / 2;
            var parts   = createSingleSignatureColumn(slot, centerX, lineTop, blockW);
            parts.forEach(function (obj) {
                canvas.add(obj);
                added.push(obj);
            });
        }
        historyLock = false;

        if (added.length) {
            if (added.length > 1) {
                var sel = new fabric.ActiveSelection(added, { canvas: canvas });
                canvas.setActiveObject(sel);
            } else {
                canvas.setActiveObject(added[0]);
            }
        }
        canvas.requestRenderAll();
        saveHistoryStep();
        renderLayersList();
    }

    function addSignatureBlock() {
        insertSignaturesRow(1);
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

    function addSignerImage(signer, slot) {
        if (!signer) return;
        slot = parseInt(slot, 10) || 1;
        var marginX = Math.round(docW * 0.08);
        var stepX   = Math.round((docW - marginX * 2) / 4);
        var posX    = marginX + (Math.min(slot, 4) - 1) * stepX + 10;
        var baseTop = Math.max(120, docH - 135);

        if (signer.signature_url) {
            fabric.Image.fromURL(signer.signature_url, function (img) {
                img.set({
                    left            : posX + 20,
                    top             : baseTop - 52,
                    name            : 'signer_slot_' + slot,
                    auraElementType : 'signer_sig',
                    signerSlot      : slot
                });
                img.scaleToWidth(140);
                canvas.add(img);
                canvas.requestRenderAll();
            }, { crossOrigin: 'anonymous' });
        }
        addTextbox('{firmante_' + slot + '_nombre}\n{firmante_' + slot + '_cargo}', {
            left      : posX,
            top       : baseTop + 6,
            width     : 185,
            fontSize  : 12,
            fontFamily: 'Lato',
            textAlign : 'center'
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
        var vKey = $(this).data('var');
        if (vKey === '{foto_estudiante}') {
            addStudentPhotoPlaceholder();
            return;
        }
        addTextbox(vKey, { width: 340, fontSize: 20, fontFamily: 'Montserrat', textAlign: 'center' });
    });

    /* ═════════════════════════════════════════
       DISEÑOS PREDEFINIDOS (13 PLANTILLAS BASE)
       ═════════════════════════════════════════ */

    function populatePrebuilt(filterMode) {
        var $list = $('#aura-cb-prebuilt-list');
        if (!$list.length) return;
        $list.empty();
        filterMode = filterMode || ($('#aura-cb-preset-filters .is-active').attr('data-filter') || 'all');

        var designs = Array.isArray(CB.prebuiltDesigns)
            ? CB.prebuiltDesigns
            : Object.values(CB.prebuiltDesigns || {});

        if (!designs.length) {
            $list.html('<p class="aura-cb-muted">Sin diseños predefinidos.</p>');
            return;
        }

        var oriLabels = {
            landscape        : 'A4 Horiz.',
            portrait         : 'A4 Vert.',
            letter_landscape : 'Carta Horiz.',
            letter_portrait  : 'Carta Vert.'
        };

        var shown = 0;
        designs.forEach(function (d) {
            var objs     = (d.json && d.json.objects) || [];
            var hasPhoto = objs.some(function (o) {
                return o && (o.auraElementType === 'student_photo' || o.name === 'student_photo_placeholder');
            });
            var sigCount = objs.filter(function (o) {
                return o && (o.auraElementType === 'signer_sig' || (o.name && String(o.name).indexOf('signer_slot_') === 0));
            }).length;
            var isVert   = d.orientation === 'portrait' || d.orientation === 'letter_portrait';

            if (filterMode === 'photo' && !hasPhoto) return;
            if (filterMode === 'landscape' && isVert) return;
            if (filterMode === 'portrait' && !isVert) return;
            shown++;

            var bgPrev = (d.json && d.json.background) || '#ffffff';
            var accent = d.accent || '#7c3aed';
            var badges = '<span class="aura-cb-preset-tag">' + escHtml(oriLabels[d.orientation] || d.orientation) + '</span>';
            if (hasPhoto) {
                badges += '<span class="aura-cb-preset-tag is-photo">👤 Con Foto</span>';
            }
            if (sigCount > 0) {
                badges += '<span class="aura-cb-preset-tag">✍ ' + sigCount + (sigCount === 1 ? ' Firma' : ' Firmas') + '</span>';
            }

            var $card = $('<button type="button" class="aura-cb-prebuilt-btn">')
                .html(
                    '<span class="aura-cb-prebuilt-swatch' + (isVert ? ' is-vert' : '') + '" style="background:' + escHtml(bgPrev) + ';border-top:4px solid ' + escHtml(accent) + ';border-color:' + escHtml(accent) + ';">' +
                        (hasPhoto ? '<i class="aura-cb-swatch-dot" style="border-color:' + escHtml(accent) + ';"></i>' : '') +
                    '</span>' +
                    '<span class="aura-cb-prebuilt-meta">' +
                        '<strong>' + escHtml(d.name) + '</strong>' +
                        '<span class="aura-cb-preset-tags">' + badges + '</span>' +
                        (d.description ? '<small>' + escHtml(d.description) + '</small>' : '') +
                    '</span>'
                );
            $card.data('design', d);
            $list.append($card);
        });

        if (!shown) {
            $list.html('<p class="aura-cb-muted">No hay plantillas en esta categoría.</p>');
        }
    }

    $(document).on('click', '.aura-cb-preset-filter', function () {
        $('.aura-cb-preset-filter').removeClass('is-active');
        $(this).addClass('is-active');
        populatePrebuilt($(this).attr('data-filter'));
    });

    $(document).on('click', '.aura-cb-prebuilt-btn', function () {
        var d = $(this).data('design');
        if (!d || !d.json) return;
        if (canvas.getObjects().length && !confirm('¿Reemplazar el diseño actual con "' + d.name + '"?')) return;

        var presetOri  = d.orientation || 'landscape';
        var currentOri = $('#aura-cb-orientation').val() || 'landscape';
        // Si el usuario ya eligió Tamaño Carta, adaptamos automáticamente el diseño A4 al Carta equivalente
        var targetOri = presetOri;
        if (currentOri.indexOf('letter_') === 0) {
            targetOri = presetOri === 'portrait' ? 'letter_portrait' : 'letter_landscape';
        }

        $('#aura-cb-orientation').val(targetOri);
        var presetDim = DIM[presetOri] || DIM.landscape;
        var targetDim = DIM[targetOri] || DIM.landscape;
        docW = targetDim.w;
        docH = targetDim.h;
        syncDimInputs(targetOri);

        if (!$('#aura-cb-tmpl-name').val().trim()) {
            $('#aura-cb-tmpl-name').val(d.name);
        }

        var jsonClone = $.extend(true, {}, d.json, {
            canvasW: presetDim.w,
            canvasH: presetDim.h
        });

        restoreCanvasFromData(jsonClone, function () {
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
        var signers = (CB.signers || []).slice(0, 4);
        $('#aura-cb-signers-count').text(signers.length);
        if (!signers.length) {
            $list.html('<p class="aura-cb-muted">No hay firmantes activos configurados. Puedes usar los botones "1–4 Firmas" arriba para añadir espacios dinámicos.</p>');
            return;
        }
        signers.forEach(function (s, idx) {
            var slot = idx + 1;
            var thumb = s.signature_url
                ? '<img src="' + escHtml(s.signature_url) + '" alt="" class="aura-cb-signer-thumb">'
                : '<span class="aura-cb-signer-nothumb">Sin imagen</span>';
            var $btn = $('<button type="button" class="aura-cb-signer-btn" title="Insertar firma #' + slot + ' en el lienzo">')
                .html(
                    '<div class="aura-cb-signer-head"><span class="aura-cb-signer-slot-badge">#' + slot + '</span>' + thumb + '</div>' +
                    '<div class="aura-cb-signer-info"><strong>' + escHtml(s.name) + '</strong>' +
                    (s.title ? '<small>' + escHtml(s.title) + '</small>' : '') +
                    '<code>{firmante_' + slot + '_nombre}</code></div>'
                );
            $btn.data('signer', s);
            $btn.data('slot', slot);
            $list.append($btn);
        });
    }

    $(document).on('click', '.aura-cb-signer-btn', function () {
        addSignerImage($(this).data('signer'), $(this).data('slot'));
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

    $(document).on('input', '.aura-cb-svg-color-input', function () {
        var obj    = canvas.getActiveObject();
        var oldHex = $(this).attr('data-old-color');
        var newHex = $(this).val();
        if (!obj || !oldHex || !newHex) return;
        replaceSvgColor(obj, oldHex, newHex);
        $(this).attr('data-old-color', newHex.toLowerCase());
        $(this).closest('.aura-cb-svg-color-row').find('code').text(newHex.toLowerCase());
    });

    $(document).on('change', '.aura-cb-svg-color-input', function () {
        saveHistoryStep();
    });

    $(document).on('click', '#aura-cb-svg-mono-apply', function () {
        var obj    = canvas.getActiveObject();
        var newHex = $('#aura-cb-svg-mono-color').val();
        if (!obj || !newHex) return;
        tintSvgMonochrome(obj, newHex);
        saveHistoryStep();
    });

    $(document).on('click', '#aura-cb-svg-reset-colors', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        resetSvgColors(obj);
    });

    /* ═════════════════════════════════════════
       CLIPART LIBRARY Y SUBIDA SVG / PNG / JPG
       ═════════════════════════════════════════ */

    function populateCliparts() {
        var $grid = $('#aura-cb-clipart-grid');
        var $cat  = $('#aura-cb-clipart-cat');
        if (!$grid.length) return;
        var items = CB.clipartLibrary || [];

        if ($cat.length && !$cat.data('populated')) {
            var cats = [];
            items.forEach(function (it) {
                if (it.category && cats.indexOf(it.category) === -1) cats.push(it.category);
            });
            cats.forEach(function (c) {
                $cat.append($('<option>').val(c).text(c));
            });
            $cat.data('populated', true);
        }

        var query  = ($('#aura-cb-clipart-search').val() || '').trim().toLowerCase();
        var selCat = $cat.val() || '';
        $grid.empty();

        var filtered = items.filter(function (it) {
            if (selCat && it.category !== selCat) return false;
            if (query && ((it.name || '') + ' ' + (it.category || '')).toLowerCase().indexOf(query) === -1) return false;
            return true;
        });

        if (!filtered.length) {
            $grid.html('<p class="aura-cb-muted" style="grid-column:1/-1;">No se encontraron cliparts con ese filtro.</p>');
            return;
        }

        filtered.forEach(function (it) {
            var $card = $('<button type="button" class="aura-cb-clipart-card" title="Insertar ' + escHtml(it.name) + '">')
                .html('<div class="aura-cb-clipart-thumb">' + it.svg + '</div><span>' + escHtml(it.name) + '</span>');
            $card.data('clipart', it);
            $grid.append($card);
        });
    }

    $(document).on('input', '#aura-cb-clipart-search', populateCliparts);
    $(document).on('change', '#aura-cb-clipart-cat', populateCliparts);

    $(document).on('click', '.aura-cb-clipart-card', function () {
        var item = $(this).data('clipart');
        if (!item || !item.svg) return;
        addSvgFromString(item.svg, item.name);
    });

    function uploadAndInsertMediaFile(file) {
        if (!file) return;
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if (['svg', 'png', 'jpg', 'jpeg', 'webp'].indexOf(ext) === -1) {
            alert('Formato no permitido. Sube archivos SVG, PNG, JPG o WEBP.');
            return;
        }

        var $st = $('#aura-cb-upload-status');
        $st.text('Subiendo ' + file.name + '…').removeClass('is-err').show();

        var fd = new FormData();
        fd.append('action', 'aura_cert_upload_media');
        fd.append('nonce', NONCE);
        fd.append('file', file);

        $.ajax({
            url         : AJAX,
            type        : 'POST',
            data        : fd,
            processData : false,
            contentType : false
        }).done(function (res) {
            if (res && res.success && res.data) {
                $st.text('✓ Insertado: ' + file.name);
                setTimeout(function () { $st.fadeOut(300); }, 2200);
                if (res.data.is_svg && res.data.svg_markup) {
                    addSvgFromString(res.data.svg_markup, file.name);
                } else {
                    addImage(res.data.url);
                }
            } else {
                // Fallback local para SVG si fallara la subida al servidor
                if (ext === 'svg' && window.FileReader) {
                    var reader = new FileReader();
                    reader.onload = function (ev) {
                        $st.hide();
                        addSvgFromString(ev.target.result, file.name);
                    };
                    reader.readAsText(file);
                } else {
                    $st.text(extractError(res, 'Error al subir archivo.')).addClass('is-err');
                }
            }
        }).fail(function () {
            if (ext === 'svg' && window.FileReader) {
                var reader = new FileReader();
                reader.onload = function (ev) {
                    $st.hide();
                    addSvgFromString(ev.target.result, file.name);
                };
                reader.readAsText(file);
            } else {
                $st.text('Error de conexión al subir el archivo.').addClass('is-err');
            }
        });
    }

    $(document).on('change', '#aura-cb-file-upload', function () {
        if (this.files && this.files[0]) {
            uploadAndInsertMediaFile(this.files[0]);
            $(this).val('');
        }
    });

    $(document).on('dragover', '#aura-cb-upload-dropzone', function (e) {
        e.preventDefault();
        $(this).addClass('is-dragover');
    }).on('dragleave drop', '#aura-cb-upload-dropzone', function (e) {
        e.preventDefault();
        $(this).removeClass('is-dragover');
        var dt = e.originalEvent && e.originalEvent.dataTransfer;
        if (e.type === 'drop' && dt && dt.files && dt.files[0]) {
            uploadAndInsertMediaFile(dt.files[0]);
        }
    });

    function openWpMediaPicker() {
        if (!window.wp || !wp.media) {
            alert('La librería multimedia de WordPress no está disponible.');
            return;
        }
        var frame = wp.media({
            title    : 'Insertar imagen o SVG',
            button   : { text: 'Insertar' },
            library  : { type: ['image', 'image/svg+xml'] },
            multiple : false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            handleImageUrlInsert(att, att.title || 'Imagen');
        });
        frame.open();
    }

    $(document).on('click', '#aura-cb-open-wp-media', function (e) {
        e.preventDefault();
        openWpMediaPicker();
    });

    $(document).on('click', '.aura-cb-elem-btn[data-type="image"]', function (e) {
        e.stopImmediatePropagation();
        openWpMediaPicker();
    });

    $(document).on('click', '.aura-cb-elem-btn[data-type="cliparts-tab"]', function (e) {
        e.stopImmediatePropagation();
        $('.aura-cb-side-tab[data-lpane="cliparts"]').trigger('click');
    });

    $(document).on('click', '.aura-cb-sig-row-btn', function () {
        var count = parseInt($(this).attr('data-sig-count'), 10) || 1;
        insertSignaturesRow(count);
    });

    $(document).on('click', '.aura-cb-side-tab[data-lpane]', function () {
        var pane = $(this).attr('data-lpane');
        $('.aura-cb-side-tab[data-lpane]').removeClass('is-active');
        $(this).addClass('is-active');
        $('[data-lpane-content]').removeClass('is-active');
        $('[data-lpane-content="' + pane + '"]').addClass('is-active');
    });

    $(document).on('click', '.aura-cb-rpane-tab[data-rpane]', function () {
        var pane = $(this).attr('data-rpane');
        $('.aura-cb-rpane-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('[data-rpane-content]').removeClass('is-active');
        $('[data-rpane-content="' + pane + '"]').addClass('is-active');
    });

    $(document).on('click', '#aura-cb-toggle-left', function () {
        $('#aura-cb-layout').toggleClass('is-left-collapsed');
        $(this).toggleClass('is-active', !$('#aura-cb-layout').hasClass('is-left-collapsed'));
        setTimeout(fitToScreen, 180);
    });

    $(document).on('click', '#aura-cb-toggle-right', function () {
        $('#aura-cb-layout').toggleClass('is-right-collapsed');
        $(this).toggleClass('is-active', !$('#aura-cb-layout').hasClass('is-right-collapsed'));
        setTimeout(fitToScreen, 180);
    });

    $(document).on('change', '#aura-cb-zoom-select', function () {
        var val = $(this).val();
        if (val === 'fit') {
            fitToScreen();
        } else {
            var z = parseFloat(val);
            if (!isNaN(z) && z > 0) {
                currentZoom = z;
                applyZoom();
            }
        }
    });

    $(document).on('click', '#aura-cb-native-fullscreen', function () {
        var docEl = document.documentElement;
        if (!document.fullscreenElement) {
            if (docEl.requestFullscreen) docEl.requestFullscreen().catch(function () {});
        } else {
            if (document.exitFullscreen) document.exitFullscreen().catch(function () {});
        }
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
            case 'student-photo':   addStudentPhotoPlaceholder(); break;
            case 'signature-block': addSignatureBlock(); break;
            case 'seal':            addOfficialSeal(); break;
        }
    });

    /* ─── Controles de Forma, Marco y Sombra de Foto / Imagen ─── */
    $(document).on('click', '.aura-cb-photo-shape-btn', function () {
        var obj   = canvas.getActiveObject();
        var shape = $(this).attr('data-photo-shape');
        if (!obj || !shape) return;
        $('.aura-cb-photo-shape-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $('#aura-cb-photo-radius-row').toggle(shape === 'rounded');
        applyPhotoStyleToObject(obj, { auraPhotoShape: shape });
        saveHistoryStep();
    });

    $(document).on('input change', '#aura-cb-photo-radius-range, #aura-cb-photo-radius', function () {
        var val = Math.max(0, Math.min(120, parseInt($(this).val(), 10) || 0));
        $('#aura-cb-photo-radius-range, #aura-cb-photo-radius').val(val);
        var obj = canvas.getActiveObject();
        if (!obj) return;
        applyPhotoStyleToObject(obj, { auraPhotoRadius: val, auraPhotoShape: 'rounded' });
        saveHistoryStep();
    });

    $(document).on('change', '#aura-cb-photo-border-style', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        var st = $(this).val();
        var curW = parseFloat($('#aura-cb-photo-border-width').val()) || 0;
        if (st !== 'none' && curW <= 0) {
            curW = 4;
            $('#aura-cb-photo-border-width').val(4);
        }
        applyPhotoStyleToObject(obj, { auraPhotoBorderStyle: st, strokeWidth: st === 'none' ? 0 : curW });
        saveHistoryStep();
    });

    $(document).on('input change', '#aura-cb-photo-border-width', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        var w = Math.max(0, parseFloat($(this).val()) || 0);
        var bStyle = w === 0 ? 'none' : ($('#aura-cb-photo-border-style').val() === 'none' ? 'solid' : $('#aura-cb-photo-border-style').val());
        $('#aura-cb-photo-border-style').val(bStyle);
        applyPhotoStyleToObject(obj, { strokeWidth: w, auraPhotoBorderStyle: bStyle });
        saveHistoryStep();
    });

    $(document).on('input change', '#aura-cb-photo-border-color', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        applyPhotoStyleToObject(obj, { stroke: $(this).val() });
        saveHistoryStep();
    });

    $(document).on('input change', '#aura-cb-photo-bg-color', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        applyPhotoStyleToObject(obj, { fill: $(this).val() });
        saveHistoryStep();
    });

    $(document).on('input change', '#aura-cb-photo-pad', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        var p = Math.max(0, Math.min(28, parseInt($(this).val(), 10) || 0));
        applyPhotoStyleToObject(obj, { auraPhotoPad: p });
        saveHistoryStep();
    });

    $(document).on('click', '.aura-cb-shadow-preset', function () {
        var obj    = canvas.getActiveObject();
        var preset = $(this).attr('data-shadow-preset');
        if (!obj) return;
        var col = $('#aura-cb-obj-shadow-color').val() || '#0f172a';
        var sh  = null;
        if (preset === 'soft') {
            sh = new fabric.Shadow({ color: 'rgba(15, 23, 42, 0.16)', blur: 8, offsetX: 0, offsetY: 3 });
        } else if (preset === 'medium') {
            sh = new fabric.Shadow({ color: 'rgba(15, 23, 42, 0.28)', blur: 14, offsetX: 0, offsetY: 5 });
        } else if (preset === 'deep') {
            sh = new fabric.Shadow({ color: col, blur: 22, offsetX: 0, offsetY: 8 });
        }
        obj.set('shadow', sh);
        $('#aura-cb-obj-shadow').prop('checked', !!sh);
        canvas.requestRenderAll();
        saveHistoryStep();
    });

    $(document).on('click', '#aura-cb-photo-sample-btn', function () {
        var obj = canvas.getActiveObject();
        if (!obj || !window.wp || !wp.media) return;
        var frame = wp.media({
            title    : 'Seleccionar foto de muestra / perfil',
            button   : { text: 'Usar foto' },
            library  : { type: 'image' },
            multiple : false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            if (att && att.url) {
                applyPhotoStyleToObject(obj, { auraCustomPhotoSrc: att.url });
                $('#aura-cb-photo-sample-clear').show();
                saveHistoryStep();
            }
        });
        frame.open();
    });

    $(document).on('click', '#aura-cb-photo-sample-clear', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        applyPhotoStyleToObject(obj, { auraCustomPhotoSrc: '' });
        $(this).hide();
        saveHistoryStep();
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
        document.documentElement.classList.add('aura-cb-fullscreen-mode');
        if (typeof fabric === 'undefined') {
            alert('Error: No se pudo cargar Fabric.js. Verifica tu conexión o recarga la página.');
            return;
        }

        initCanvas();
        populateFonts();
        populateCliparts();
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
                var areaW = $wrap.width() - 44;
                var areaH = $wrap.height() - 48;
                var fitZ  = Math.min(areaW / docW, areaH / docH, 1.35);
                if (currentZoom <= fitZ + 0.05) {
                    fitToScreen();
                }
            }, 200);
        });
    });

}(jQuery));
