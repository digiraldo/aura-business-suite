/**
 * Aura Business Suite — Certificate Builder
 * Editor visual de plantillas basado en Fabric.js v5.x
 *
 * Datos localizados en auraCertBuilder:
 *   ajaxUrl, nonce, templateId, dynamicVars, signers,
 *   prebuiltDesigns, orgName, orgLogoUrl, verifySlug
 */
(function ($) {
    'use strict';

    /* ─────────────────────────────────────────
       CONFIG
       ───────────────────────────────────────── */

    var CB      = window.auraCertBuilder || {};
    var AJAX    = CB.ajaxUrl  || ajaxurl;
    var NONCE   = CB.nonce    || '';
    var TMPL_ID = parseInt(CB.templateId, 10) || 0;

    // Dimensiones lógicas del documento en píxeles (96 dpi)
    var DIM = {
        landscape        : { w: 1122, h: 794  },  // A4  Horizontal
        portrait         : { w: 794,  h: 1122 },  // A4  Vertical
        letter_landscape : { w: 1056, h: 816  },  // Carta Horizontal (8.5×11")
        letter_portrait  : { w: 816,  h: 1056 }   // Carta Vertical
    };

    // Fuentes disponibles (las de Google se pre-cargan en PHP)
    var FONTS = [
        'Arial', 'Times New Roman', 'Georgia', 'Verdana', 'Courier New', 'Tahoma',
        'Playfair Display', 'Montserrat', 'Lato', 'Great Vibes',
        'Roboto', 'Open Sans', 'Raleway', 'Merriweather', 'Cinzel', 'Dancing Script'
    ];

    /* ─────────────────────────────────────────
       ESTADO
       ───────────────────────────────────────── */

    var canvas;
    var docW          = 1122;   // ancho lógico del documento (sin zoom)
    var docH          = 794;    // alto  lógico del documento (sin zoom)
    var history       = [];     // pila undo
    var historyFuture = [];     // pila redo
    var historyLock   = false;
    var historyTimer  = null;
    var currentZoom   = 1;
    var isTextEditing = false;  // true mientras se edita texto directamente en canvas

    /* ─────────────────────────────────────────
       HELPERS
       ───────────────────────────────────────── */

    function ajaxPost(action, data) {
        return $.post(AJAX, $.extend({ action: action, nonce: NONCE }, data));
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

        canvas = new fabric.Canvas('aura-cert-canvas', {
            width              : dim.w,
            height             : dim.h,
            backgroundColor    : bgColor,
            preserveObjectStacking : true,
            selection          : true
        });

        // ── Eventos de selección ──
        canvas.on('selection:created', onSelectionChange);
        canvas.on('selection:updated', onSelectionChange);
        canvas.on('selection:cleared', onSelectionCleared);

        // ── Eventos de modificación ──
        canvas.on('object:modified', function () {
            saveHistoryStep();
            updateObjectPanel(canvas.getActiveObject());
        });
        canvas.on('object:added',   saveHistoryStep);
        canvas.on('object:removed', saveHistoryStep);

        // ── Live-update del panel mientras se arrastra/escala/rota ──
        canvas.on('object:moving',   function (e) { updateObjectPanel(e.target); });
        canvas.on('object:scaling',  function (e) { updateObjectPanel(e.target); });
        canvas.on('object:rotating', function (e) { updateObjectPanel(e.target); });

        // ── Sincronizar textarea cuando el usuario edita texto en canvas ──
        canvas.on('text:changed', function (e) {
            if (e.target) {
                $('#aura-cb-text-content').val(e.target.text);
            }
        });
        canvas.on('text:editing:entered', function () { isTextEditing = true; });
        canvas.on('text:editing:exited',  function () { isTextEditing = false; });

        // ── Estilo del contenedor Fabric (necesario para scroll/centering) ──
        $('.canvas-container').css({
            margin     : 'auto',
            flexShrink : '0'
        });

        // Cargar diseño existente o iniciar vacío
        if (TMPL_ID > 0) {
            loadExistingTemplate();
        } else {
            saveHistoryStep();
            setTimeout(fitToScreen, 120);
        }
    }

    /* ═════════════════════════════════════════
       CARGAR PLANTILLA EXISTENTE
       ═════════════════════════════════════════ */

    function loadExistingTemplate() {
        ajaxPost('aura_cert_load_template', { id: TMPL_ID }).done(function (res) {
            if (!res.success) { setTimeout(fitToScreen, 120); return; }
            var t = res.data;

            if (t.name)        $('#aura-cb-tmpl-name').val(t.name);
            if (t.description) $('#aura-cb-tmpl-desc').val(t.description);
            if (t.orientation) {
                $('#aura-cb-orientation').val(t.orientation);
                changeOrientation(t.orientation);
            }
            if (t.design_json) {
                try {
                    var json = JSON.parse(t.design_json);
                    canvas.loadFromJSON(json, function () {
                        canvas.renderAll();
                        if (json.background) {
                            $('#aura-cb-bg-color').val(json.background);
                        }
                        history = [];
                        historyFuture = [];
                        saveHistoryStep();
                        updateObjectPanel(null);
                        setTimeout(fitToScreen, 120);
                    });
                } catch (e) {
                    setTimeout(fitToScreen, 120);
                }
            } else {
                setTimeout(fitToScreen, 120);
            }
        }).fail(function () {
            setTimeout(fitToScreen, 120);
        });
    }

    /* ═════════════════════════════════════════
       CAMBIAR ORIENTACIÓN / REDIMENSIONAR
       ═════════════════════════════════════════ */

    function changeOrientation(orientation) {
        var dim = DIM[orientation] || DIM.landscape;
        docW = dim.w;
        docH = dim.h;
        applyZoom();
    }

    /* ═════════════════════════════════════════
       SISTEMA DE ZOOM
       ═════════════════════════════════════════

       El canvas HTML se redimensiona a docW*zoom × docH*zoom.
       El viewport-transform de Fabric aplica el zoom visual.
       El contenedor padre (overflow:auto) genera scrollbars
       cuando el canvas es más grande que el área visible.
       ═════════════════════════════════════════ */

    function applyZoom() {
        var vw = Math.round(docW * currentZoom);
        var vh = Math.round(docH * currentZoom);
        canvas.setDimensions({ width: vw, height: vh });
        canvas.setViewportTransform([currentZoom, 0, 0, currentZoom, 0, 0]);
        canvas.requestRenderAll();
        updateZoomLabel();
    }

    function fitToScreen() {
        var $wrap = $('.aura-cert-builder-canvas-wrap');
        var areaW = $wrap.width()  - 60;
        var areaH = $wrap.height() - 60;
        if (areaW < 100 || areaH < 100) return; // DOM no listo

        var zoomX = areaW / docW;
        var zoomY = areaH / docH;
        currentZoom = parseFloat(Math.min(zoomX, zoomY, 1).toFixed(2));
        applyZoom();
    }

    function zoomIn() {
        currentZoom = Math.min(3, parseFloat((currentZoom + 0.1).toFixed(1)));
        applyZoom();
    }

    function zoomOut() {
        currentZoom = Math.max(0.1, parseFloat((currentZoom - 0.1).toFixed(1)));
        applyZoom();
    }

    function updateZoomLabel() {
        $('#aura-cb-zoom-label').text(Math.round(currentZoom * 100) + '%');
    }

    /* ═════════════════════════════════════════
       HISTORIAL UNDO / REDO
       ═════════════════════════════════════════ */

    function saveHistoryStep() {
        if (historyLock) return;
        history.push(JSON.stringify(canvas.toJSON(['name'])));
        historyFuture = [];
        if (history.length > 50) history.shift();
    }

    /** Guarda estado con debounce (para cambios continuos como slider, typing) */
    function debouncedHistoryStep() {
        clearTimeout(historyTimer);
        historyTimer = setTimeout(saveHistoryStep, 400);
    }

    function undo() {
        if (history.length <= 1) return;
        historyLock = true;
        historyFuture.push(history.pop());
        var state = history[history.length - 1];
        canvas.loadFromJSON(JSON.parse(state), function () {
            canvas.renderAll();
            historyLock = false;
            onSelectionCleared();
        });
    }

    function redo() {
        if (!historyFuture.length) return;
        historyLock = true;
        var state = historyFuture.pop();
        history.push(state);
        canvas.loadFromJSON(JSON.parse(state), function () {
            canvas.renderAll();
            historyLock = false;
            onSelectionCleared();
        });
    }

    /* ═════════════════════════════════════════
       PANEL PROPIEDADES — SELECCIÓN
       ═════════════════════════════════════════ */

    function onSelectionChange() {
        updateObjectPanel(canvas.getActiveObject());
    }

    function onSelectionCleared() {
        updateObjectPanel(null);
    }

    function updateObjectPanel(obj) {
        var $objPanel  = $('#aura-cb-obj-props');
        var $textProps = $('#aura-cb-text-props');

        if (!obj) {
            $objPanel.hide();
            return;
        }
        $objPanel.show();

        // Posición y dimensiones
        $('#aura-cb-obj-x').val(Math.round(obj.left   || 0));
        $('#aura-cb-obj-y').val(Math.round(obj.top    || 0));
        $('#aura-cb-obj-w').val(Math.round((obj.width  || 0) * (obj.scaleX || 1)));
        $('#aura-cb-obj-h').val(Math.round((obj.height || 0) * (obj.scaleY || 1)));
        $('#aura-cb-obj-angle').val(Math.round(obj.angle || 0));

        // Opacidad (0-100)
        var opVal = Math.round((obj.opacity !== undefined ? obj.opacity : 1) * 100);
        $('#aura-cb-obj-opacity').val(opVal);
        $('#aura-cb-obj-opacity-label').text(opVal + '%');

        // Propiedades de texto
        if (obj.type === 'textbox' || obj.type === 'i-text' || obj.type === 'text') {
            $textProps.show();
            $('#aura-cb-text-content').val(obj.text || '');
            $('#aura-cb-obj-font-family').val(obj.fontFamily || 'Arial');
            $('#aura-cb-obj-font-size').val(obj.fontSize    || 20);
            $('#aura-cb-obj-font-color').val(obj.fill        || '#000000');
            $('#aura-cb-obj-text-align').val(obj.textAlign   || 'left');
            $('#aura-cb-obj-bold').toggleClass('active', obj.fontWeight === 'bold');
            $('#aura-cb-obj-italic').toggleClass('active', obj.fontStyle === 'italic');
        } else {
            $textProps.hide();
        }
    }

    /* ═════════════════════════════════════════
       PANEL PROPIEDADES — INPUTS → CANVAS
       ═════════════════════════════════════════ */

    function bindPropInputs() {

        // ── Texto del objeto ──
        $(document).on('input', '#aura-cb-text-content', function () {
            applyToActive({ text: $(this).val() });
        });

        // ── Posición / tamaño ──
        $(document).on('change', '#aura-cb-obj-x', function () {
            applyToActive({ left: parseFloat($(this).val()) });
        });
        $(document).on('change', '#aura-cb-obj-y', function () {
            applyToActive({ top: parseFloat($(this).val()) });
        });
        $(document).on('change', '#aura-cb-obj-w', function () {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            var newW = parseFloat($(this).val());
            obj.set('scaleX', newW / (obj.width || 1));
            obj.setCoords();
            canvas.requestRenderAll();
            debouncedHistoryStep();
        });
        $(document).on('change', '#aura-cb-obj-h', function () {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            var newH = parseFloat($(this).val());
            obj.set('scaleY', newH / (obj.height || 1));
            obj.setCoords();
            canvas.requestRenderAll();
            debouncedHistoryStep();
        });
        $(document).on('change', '#aura-cb-obj-angle', function () {
            applyToActive({ angle: parseFloat($(this).val()) });
        });
        $(document).on('input', '#aura-cb-obj-opacity', function () {
            var val = parseInt($(this).val(), 10);
            $('#aura-cb-obj-opacity-label').text(val + '%');
            applyToActive({ opacity: val / 100 });
        });

        // ── Texto: fuente, tamaño, color, alineación ──
        $(document).on('change', '#aura-cb-obj-font-family', function () {
            loadGoogleFont($(this).val());
            applyToActive({ fontFamily: $(this).val() });
        });
        $(document).on('change', '#aura-cb-obj-font-size', function () {
            applyToActive({ fontSize: parseInt($(this).val(), 10) });
        });
        $(document).on('input', '#aura-cb-obj-font-color', function () {
            applyToActive({ fill: $(this).val() });
        });
        $(document).on('change', '#aura-cb-obj-text-align', function () {
            applyToActive({ textAlign: $(this).val() });
        });
        $(document).on('click', '#aura-cb-obj-bold', function () {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            var isBold = obj.fontWeight === 'bold';
            applyToActive({ fontWeight: isBold ? 'normal' : 'bold' });
            $(this).toggleClass('active', !isBold);
        });
        $(document).on('click', '#aura-cb-obj-italic', function () {
            var obj = canvas.getActiveObject();
            if (!obj) return;
            var isItalic = obj.fontStyle === 'italic';
            applyToActive({ fontStyle: isItalic ? 'normal' : 'italic' });
            $(this).toggleClass('active', !isItalic);
        });

        // ── Fondo del canvas ──
        $(document).on('input', '#aura-cb-bg-color', function () {
            canvas.setBackgroundColor($(this).val(), canvas.renderAll.bind(canvas));
        });

        // ── Orientación → redimensionar + ajustar zoom ──
        $(document).on('change', '#aura-cb-orientation', function () {
            changeOrientation($(this).val());
            setTimeout(fitToScreen, 60);
        });
    }

    function applyToActive(props) {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        obj.set(props);
        obj.setCoords();
        canvas.requestRenderAll();
        debouncedHistoryStep();
    }

    /* ═════════════════════════════════════════
       DISEÑO DESDE PALETA (legacy)
       ═════════════════════════════════════════ */

    function applyPaletteDesign(design) {
        var p = design.palette || {};
        var w = docW;
        var h = docH;

        canvas.clear();
        canvas.setBackgroundColor(p.secondary || '#ffffff', canvas.renderAll.bind(canvas));

        // Borde
        canvas.add(new fabric.Rect({
            left: 15, top: 15, width: w - 30, height: h - 30,
            fill: 'rgba(0,0,0,0)', stroke: p.primary || '#333', strokeWidth: 4
        }));
        // Título
        canvas.add(new fabric.Textbox('DIPLOMA', {
            left: 40, top: 60, width: w - 80,
            fontSize: 68, fontFamily: design.font_title || 'Georgia',
            fontWeight: 'bold', fill: p.primary || '#333', textAlign: 'center'
        }));
        // Nombre del alumno
        canvas.add(new fabric.Textbox('{nombre_completo}', {
            left: 40, top: 180, width: w - 80,
            fontSize: 44, fontFamily: design.font_title || 'Georgia',
            fontWeight: 'bold', fill: p.text || '#111', textAlign: 'center'
        }));
        // Curso
        canvas.add(new fabric.Textbox('{curso}', {
            left: 40, top: 310, width: w - 80,
            fontSize: 34, fontFamily: design.font_body || 'Arial',
            fontStyle: 'italic', fill: p.primary || '#333', textAlign: 'center'
        }));
        // Organización
        canvas.add(new fabric.Textbox('{organizacion}', {
            left: 40, top: h - 80, width: w - 80,
            fontSize: 18, fontFamily: design.font_body || 'Arial',
            fontWeight: 'bold', fill: p.text || '#111', textAlign: 'center'
        }));
        canvas.renderAll();
        saveHistoryStep();
    }

    /* ═════════════════════════════════════════
       CARGA DE FUENTE GOOGLE
       ═════════════════════════════════════════ */

    var loadedFonts = {};

    function loadGoogleFont(family) {
        if (!family || family === 'Arial' || family === 'Times New Roman'
            || family === 'Georgia' || family === 'Verdana'
            || family === 'Courier New' || family === 'Tahoma'
            || loadedFonts[family]) return;
        loadedFonts[family] = true;
        var link  = document.createElement('link');
        link.rel  = 'stylesheet';
        link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family) + ':wght@400;700&display=swap';
        document.head.appendChild(link);
    }

    /* ═════════════════════════════════════════
       POBLAR SELECTOR DE FUENTES
       ═════════════════════════════════════════ */

    function populateFonts() {
        var $sel = $('#aura-cb-obj-font-family');
        if (!$sel.length) return;
        $sel.empty();
        FONTS.forEach(function (f) {
            $sel.append($('<option>').val(f).text(f));
        });
    }

    /* ═════════════════════════════════════════
       AGREGAR ELEMENTOS
       ═════════════════════════════════════════ */

    function addTextbox(text, extra) {
        var obj = new fabric.Textbox(text || 'Texto', $.extend({
            left       : 50,
            top        : 50,
            width      : 300,
            fontSize   : 24,
            fontFamily : 'Arial',
            fill       : '#000000',
            textAlign  : 'left',
            editable   : true
        }, extra || {}));
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addImage(url, name) {
        fabric.Image.fromURL(url, function (img) {
            img.set({ left: 100, top: 100, name: name || '' });
            var maxW = 300;
            if (img.width > maxW) img.scaleToWidth(maxW);
            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.requestRenderAll();
        }, { crossOrigin: 'anonymous' });
    }

    function addRect() {
        var obj = new fabric.Rect({
            left: 80, top: 80, width: 200, height: 100,
            fill: '#8b5cf6', rx: 4, ry: 4
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addCircle() {
        var obj = new fabric.Circle({
            left: 80, top: 80, radius: 60, fill: '#8b5cf6'
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addLine() {
        var obj = new fabric.Line([50, 100, 400, 100], {
            stroke: '#333333', strokeWidth: 2
        });
        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addQrPlaceholder() {
        var group = new fabric.Group([
            new fabric.Rect({
                width: 100, height: 100,
                fill: '#e5e7eb', stroke: '#9ca3af', strokeWidth: 1
            }),
            new fabric.Text('QR', {
                fontSize: 18, fill: '#6b7280',
                originX: 'center', originY: 'center',
                left: 50, top: 50
            })
        ], { left: 60, top: 60, name: 'qr_placeholder' });
        canvas.add(group);
        canvas.setActiveObject(group);
        canvas.requestRenderAll();
    }

    function addSignerImage(signer) {
        if (!signer || !signer.signature_url) return;
        fabric.Image.fromURL(signer.signature_url, function (img) {
            img.set({ left: 100, top: 100, name: 'signer_' + signer.id });
            img.scaleToWidth(160);
            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.requestRenderAll();
        }, { crossOrigin: 'anonymous' });

        addTextbox(signer.name + '\n' + (signer.title || ''), {
            left: 100, top: 200, width: 200, fontSize: 14, textAlign: 'center'
        });
    }

    /* ═════════════════════════════════════════
       VARIABLES DINÁMICAS
       ═════════════════════════════════════════ */

    function populateVarsList() {
        var $list = $('#aura-cb-vars-list');
        if (!$list.length) return;
        $list.empty();
        var vars = CB.dynamicVars || [];
        vars.forEach(function (v) {
            var $btn = $('<button type="button" class="aura-cb-var-btn">').text(v.label);
            $btn.data('var', v.key);
            $list.append($btn);
        });
    }

    $(document).on('click', '.aura-cb-var-btn', function () {
        addTextbox($(this).data('var'), { width: 320, fontSize: 20, textAlign: 'center' });
    });

    /* ═════════════════════════════════════════
       DISEÑOS PREDEFINIDOS
       ═════════════════════════════════════════ */

    function populatePrebuilt() {
        var $list = $('#aura-cb-prebuilt-list');
        if (!$list.length) return;
        $list.empty();
        var designs = Array.isArray(CB.prebuiltDesigns)
            ? CB.prebuiltDesigns
            : Object.values(CB.prebuiltDesigns || {});
        if (!designs.length) {
            $list.html('<p style="font-size:.75rem;color:#888">Sin diseños predefinidos.</p>');
            return;
        }
        designs.forEach(function (d, idx) {
            var $btn = $('<button type="button" class="aura-cb-prebuilt-btn">').text(d.name || ('Diseño ' + (idx + 1)));
            $btn.data('index', idx);
            $list.append($btn);
        });
    }

    $(document).on('click', '.aura-cb-prebuilt-btn', function () {
        var idx     = $(this).data('index');
        var designs = Array.isArray(CB.prebuiltDesigns)
            ? CB.prebuiltDesigns
            : Object.values(CB.prebuiltDesigns || {});
        var design = designs[idx];
        if (!design) return;
        if (!confirm('Esto reemplazará el diseño actual. ¿Continuar?')) return;

        // Cambiar orientación del diseño si viene especificada
        if (design.orientation) {
            $('#aura-cb-orientation').val(design.orientation);
            changeOrientation(design.orientation);
        }

        if (design.json) {
            var jsonData = typeof design.json === 'string' ? JSON.parse(design.json) : design.json;
            canvas.loadFromJSON(jsonData, function () {
                canvas.renderAll();
                history = [];
                historyFuture = [];
                saveHistoryStep();
                updateObjectPanel(null);
                setTimeout(fitToScreen, 60);
            });
        } else if (design.palette) {
            applyPaletteDesign(design);
            setTimeout(fitToScreen, 60);
        }
    });

    /* ═════════════════════════════════════════
       FIRMANTES EN PANEL
       ═════════════════════════════════════════ */

    function populateSigners() {
        var $wrap = $('#aura-cb-signers-list');
        if (!$wrap.length) return;
        $wrap.empty();
        var signers = CB.signers || [];
        if (!signers.length) {
            $wrap.html('<p style="font-size:.8rem;color:#888">Sin firmantes activos.</p>');
            return;
        }
        signers.forEach(function (s) {
            var $item = $('<div class="aura-cb-signer-item">');
            if (s.signature_url) {
                $item.append($('<img>').attr('src', s.signature_url));
            }
            $item.append($('<span>').text(s.name));
            var $btn = $('<button type="button" class="button button-small">Agregar</button>');
            $btn.on('click', function () { addSignerImage(s); });
            $item.append($btn);
            $wrap.append($item);
        });
    }

    /* ═════════════════════════════════════════
       CAPA / ORDEN
       ═════════════════════════════════════════ */

    $(document).on('click', '#aura-cb-bring-forward', function () {
        var obj = canvas.getActiveObject();
        if (obj) { canvas.bringForward(obj); canvas.requestRenderAll(); }
    });
    $(document).on('click', '#aura-cb-send-backward', function () {
        var obj = canvas.getActiveObject();
        if (obj) { canvas.sendBackwards(obj); canvas.requestRenderAll(); }
    });

    // Eliminar objeto seleccionado
    $(document).on('click', '#aura-cb-delete-obj', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        canvas.remove(obj);
        canvas.discardActiveObject();
        canvas.requestRenderAll();
        onSelectionCleared();
    });

    // Clonar objeto
    $(document).on('click', '#aura-cb-clone-obj', function () {
        var obj = canvas.getActiveObject();
        if (!obj) return;
        obj.clone(function (clone) {
            clone.set({ left: obj.left + 20, top: obj.top + 20 });
            canvas.add(clone);
            canvas.setActiveObject(clone);
            canvas.requestRenderAll();
        }, ['name']);
    });

    // Eliminar con teclado (no cuando se edita texto o input)
    $(document).on('keydown', function (e) {
        if (isTextEditing) return;
        var tag = document.activeElement.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (e.key === 'Delete') {
            var obj = canvas.getActiveObject();
            if (obj) {
                canvas.remove(obj);
                canvas.discardActiveObject();
                canvas.requestRenderAll();
                onSelectionCleared();
            }
        }
    });

    /* ═════════════════════════════════════════
       UNDO / REDO — BOTONES + ATAJOS
       ═════════════════════════════════════════ */

    $(document).on('click', '#aura-cb-undo', undo);
    $(document).on('click', '#aura-cb-redo', redo);

    $(document).on('keydown', function (e) {
        var tag = document.activeElement.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') { e.preventDefault(); undo(); }
        if ((e.ctrlKey || e.metaKey) && (e.shiftKey && e.key === 'z' || e.key === 'y')) { e.preventDefault(); redo(); }
    });

    /* ═════════════════════════════════════════
       ZOOM — BOTONES
       ═════════════════════════════════════════ */

    $(document).on('click', '#aura-cb-zoom-in',    zoomIn);
    $(document).on('click', '#aura-cb-zoom-out',   zoomOut);
    $(document).on('click', '#aura-cb-zoom-reset', fitToScreen);

    /* ═════════════════════════════════════════
       ZOOM — RUEDA DEL RATÓN (Ctrl + Scroll)
       ═════════════════════════════════════════ */

    $(document).on('ready', function () {
        var $wrap = $('.aura-cert-builder-canvas-wrap');
        if (!$wrap.length) return;
        $wrap[0].addEventListener('wheel', function (e) {
            if (!e.ctrlKey && !e.metaKey) return; // sin Ctrl = scroll normal
            e.preventDefault();
            if (e.deltaY < 0) {
                zoomIn();
            } else {
                zoomOut();
            }
        }, { passive: false });
    });

    /* ═════════════════════════════════════════
       FONDO DESDE IMAGEN (WP Media Library)
       ═════════════════════════════════════════ */

    $(document).on('click', '#aura-cb-bg-image-btn', function (e) {
        e.preventDefault();
        if (!window.wp || !wp.media) return;
        var frame = wp.media({
            title   : 'Seleccionar imagen de fondo',
            button  : { text: 'Usar esta imagen' },
            library : { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            fabric.Image.fromURL(att.url, function (img) {
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                    scaleX: docW / img.width,
                    scaleY: docH / img.height
                });
            }, { crossOrigin: 'anonymous' });
        });
        frame.open();
    });

    $(document).on('click', '#aura-cb-bg-image-remove', function () {
        canvas.setBackgroundImage(null, canvas.renderAll.bind(canvas));
    });

    /* ═════════════════════════════════════════
       AGREGAR IMAGEN DESDE MEDIA LIBRARY
       ═════════════════════════════════════════ */

    $(document).on('click', '.aura-cb-elem-btn[data-type="image"]', function (e) {
        e.stopImmediatePropagation(); // evitar que el handler genérico también se ejecute
        if (!window.wp || !wp.media) {
            alert('wp.media no disponible.');
            return;
        }
        var frame = wp.media({
            title   : 'Insertar imagen',
            button  : { text: 'Insertar' },
            library : { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            addImage(att.url);
        });
        frame.open();
    });

    /* ═════════════════════════════════════════
       BOTONES AÑADIR ELEMENTOS (genérico)
       ═════════════════════════════════════════ */

    $(document).on('click', '.aura-cb-elem-btn', function () {
        var type = $(this).data('type');
        switch (type) {
            case 'textbox': addTextbox('Texto');    break;
            case 'rect':    addRect();              break;
            case 'circle':  addCircle();            break;
            case 'line':    addLine();              break;
            case 'qr':      addQrPlaceholder();     break;
            // 'image' se maneja en su propio listener
        }
    });

    /* ═════════════════════════════════════════
       VISTA PREVIA
       ═════════════════════════════════════════ */

    $(document).on('click', '#aura-cb-preview', function () {
        // Temporalmente resetear zoom a 1 para captura completa
        var prevZoom = currentZoom;
        canvas.setDimensions({ width: docW, height: docH });
        canvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
        canvas.renderAll();

        var dataUrl = canvas.toDataURL({ format: 'jpeg', quality: 0.92 });

        // Restaurar zoom
        currentZoom = prevZoom;
        applyZoom();

        var w = window.open('', '_blank');
        if (w) {
            w.document.write(
                '<html><head><title>Vista Previa</title></head>' +
                '<body style="margin:0;background:#555;display:flex;align-items:center;justify-content:center;min-height:100vh">' +
                '<img src="' + dataUrl + '" style="max-width:95vw;max-height:95vh;box-shadow:0 4px 24px rgba(0,0,0,.5)"/>' +
                '</body></html>'
            );
            w.document.close();
        }
    });

    /* ═════════════════════════════════════════
       GUARDAR
       ═════════════════════════════════════════ */

    $(document).on('click', '#aura-cb-save', function () {
        var name = $('#aura-cb-tmpl-name').val().trim();
        if (!name) {
            alert('El nombre de la plantilla es requerido.');
            $('#aura-cb-tmpl-name').focus();
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Guardando…');

        var orientation = $('#aura-cb-orientation').val() || 'landscape';
        var desc        = $('#aura-cb-tmpl-desc').val().trim();

        // Resetear zoom a 1 para generar JSON y thumbnail al tamaño real
        var prevZoom = currentZoom;
        canvas.setDimensions({ width: docW, height: docH });
        canvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
        canvas.renderAll();

        var jsonObj    = canvas.toJSON(['name']);
        jsonObj.canvasW = docW;
        jsonObj.canvasH = docH;
        var canvasJson   = JSON.stringify(jsonObj);
        var thumbnailB64 = canvas.toDataURL({ format: 'png', quality: 0.8 });

        // Restaurar zoom
        currentZoom = prevZoom;
        applyZoom();

        ajaxPost('aura_cert_save_template', {
            id          : TMPL_ID,
            name        : name,
            description : desc,
            orientation : orientation,
            design_json : canvasJson
        }).done(function (res) {
            if (!res.success) {
                alert(res.data || 'Error al guardar.');
                $btn.prop('disabled', false).text('Guardar Plantilla');
                return;
            }
            var savedId = parseInt(res.data.id, 10) || TMPL_ID;

            ajaxPost('aura_cert_save_thumbnail', {
                id        : savedId,
                thumbnail : thumbnailB64
            }).always(function () {
                var listUrl = $('#aura-cb-templates-list-url').val();
                if (listUrl) {
                    window.location.href = listUrl;
                } else {
                    window.history.back();
                }
            });
        }).fail(function () {
            alert('Error de conexión.');
            $btn.prop('disabled', false).text('Guardar Plantilla');
        });
    });

    /* ═════════════════════════════════════════
       INICIALIZACIÓN COMPLETA
       ═════════════════════════════════════════ */

    $(document).ready(function () {
        if (!document.getElementById('aura-cert-canvas')) return;
        if (typeof fabric === 'undefined') {
            console.error('[AuraCertBuilder] Fabric.js no cargado.');
            return;
        }

        initCanvas();
        populateFonts();
        populateVarsList();
        populatePrebuilt();
        populateSigners();
        bindPropInputs();
        updateZoomLabel();

        // Mouse wheel zoom — registrar en canvas-wrap
        var wrapEl = document.querySelector('.aura-cert-builder-canvas-wrap');
        if (wrapEl) {
            wrapEl.addEventListener('wheel', function (e) {
                if (!e.ctrlKey && !e.metaKey) return;
                e.preventDefault();
                if (e.deltaY < 0) { zoomIn(); } else { zoomOut(); }
            }, { passive: false });
        }

        // Escape → deseleccionar
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                canvas.discardActiveObject();
                canvas.requestRenderAll();
                onSelectionCleared();
            }
        });

        // Ctrl+S → guardar
        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('#aura-cb-save').trigger('click');
            }
        });

        // Resize ventana → re-calcular fit (si zoom era ajustado a pantalla)
        var resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                // Solo re-ajustar si el zoom actual es menor o igual al fit
                var $wrap = $('.aura-cert-builder-canvas-wrap');
                var areaW = $wrap.width()  - 60;
                var areaH = $wrap.height() - 60;
                var fitZ  = Math.min(areaW / docW, areaH / docH, 1);
                if (currentZoom <= fitZ + 0.05) {
                    fitToScreen();
                }
            }, 200);
        });
    });

}(jQuery));
