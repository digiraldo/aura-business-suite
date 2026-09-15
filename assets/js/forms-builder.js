/**
 * forms-builder.js — Editor visual de formularios
 *
 * Responsabilidades:
 *  - SortableJS: reordenar campos via drag & drop
 *  - Paleta de tipos: agregar campos al canvas al hacer clic
 *  - Panel de configuración lateral por campo (aura-field-config-overlay)
 *  - Guardar metadatos del formulario (AJAX aura_forms_save)
 *  - Guardar campos individuales (AJAX aura_forms_field_save)
 *  - Eliminar campos (AJAX aura_forms_field_delete)
 *  - Reordenar tras drag & drop (AJAX aura_forms_field_reorder)
 *  - Selector dinámico Área → Cursos (AJAX aura_forms_get_courses)
 *  - Media Library picker para campos image / downloadable
 *
 * @package AuraBusinessSuite
 */
/* global auraFormsBuilder, jQuery, Sortable, wp */
( function ( $ ) {
    'use strict';

    var cfg    = auraFormsBuilder;           // datos localizados
    var formId = cfg.formId || 0;
    var sortableInstance = null;

    // ── Tipos sin campo requerido / sin label obligatorio ─────────────────
    var NO_REQUIRED_TYPES = [ 'section_title', 'paragraph', 'image', 'downloadable' ];
    var NO_RESPONSE_TYPES = [ 'section_title', 'paragraph', 'image', 'downloadable' ];

    // ── Config. de visibilidad de campos del panel según tipo ─────────────
    var FIELD_VISIBILITY = {
        text             : [ 'label', 'description', 'required', 'placeholder', 'default', 'mapping' ],
        textarea         : [ 'label', 'description', 'required', 'placeholder' ],
        email            : [ 'label', 'description', 'required', 'placeholder', 'default', 'mapping' ],
        tel              : [ 'label', 'description', 'required', 'placeholder', 'default', 'mapping' ],
        number           : [ 'label', 'description', 'required', 'placeholder', 'default', 'minmax', 'mapping' ],
        date             : [ 'label', 'description', 'required', 'default', 'mapping' ],
        time             : [ 'label', 'description', 'required' ],
        birthdate        : [ 'label', 'description', 'required', 'mapping' ],
        radio            : [ 'label', 'description', 'required', 'options', 'has_other', 'mapping' ],
        checkbox         : [ 'label', 'description', 'required', 'options', 'mapping' ],
        select           : [ 'label', 'description', 'required', 'options', 'multiple_select', 'has_other', 'mapping' ],
        scale            : [ 'label', 'description', 'required', 'scale_max', 'mapping' ],
        file             : [ 'label', 'description', 'required', 'file', 'mapping' ],
        image            : [ 'label', 'description', 'image' ],
        downloadable     : [ 'label', 'description', 'downloadable' ],
        terms            : [ 'label', 'description', 'required', 'terms', 'disagreement' ],
        accept_only_terms: [ 'label', 'description', 'required', 'terms' ],
        hidden           : [ 'label', 'default' ],
        section_title    : [ 'label', 'description' ],
        paragraph        : [ 'label', 'description' ],
    };

    // ── Nombre legible por tipo ───────────────────────────────────────────
    var TYPE_LABELS = {};
    if ( cfg.fieldTypes ) {
        cfg.fieldTypes.forEach( function ( ft ) {
            TYPE_LABELS[ ft.type ] = ft.label;
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // INIT
    // ═════════════════════════════════════════════════════════════════════

    function init() {
        initSortable();
        bindPalette();
        bindCanvasActions();
        bindFormSave();
        bindConfigPanel();
        bindAreaCourseSelector();
        bindTypeVisibility();
        bindMediaPickers();
        bindDownloadableTabs();
        bindEnrollmentDefaults();
    }

    // ═════════════════════════════════════════════════════════════════════
    // SORTABLE
    // ═════════════════════════════════════════════════════════════════════

    function initSortable() {
        var canvas = document.getElementById( 'aura-fields-canvas' );
        if ( ! canvas || typeof Sortable === 'undefined' ) return;

        sortableInstance = new Sortable( canvas, {
            handle       : '.aura-field-item-handle',
            animation    : 150,
            ghostClass   : 'aura-field-item--dragging',
            onEnd        : function () {
                saveFieldOrder();
            },
        } );
    }

    function saveFieldOrder() {
        if ( ! formId ) return;
        var order = [];
        $( '#aura-fields-canvas .aura-field-item' ).each( function () {
            order.push( $( this ).data( 'field-id' ) );
        } );
        $.post( cfg.ajaxUrl, {
            action  : 'aura_forms_field_reorder',
            form_id : formId,
            order   : order,
            nonce   : cfg.nonce,
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // PALETA — Agregar campos
    // ═════════════════════════════════════════════════════════════════════

    function bindPalette() {
        $( document ).on( 'click', '.aura-palette-item', function () {
            var type = $( this ).data( 'type' );
            if ( ! formId ) {
                // El formulario aún no se guardó: auto-guardamos primero.
                autoSaveFormThenAddField( type );
            } else {
                openConfigPanel( 0, type, {} );
            }
        } );
    }

    /**
     * Guarda automáticamente el formulario (si tiene título) y después abre
     * el panel de configuración del tipo de campo pendiente.
     */
    function autoSaveFormThenAddField( pendingType ) {
        var title = $( '#form-title' ).val().trim();
        if ( ! title ) {
            showNotice( 'Escribe primero el título del formulario y después agrega campos.', 'error' );
            $( '#form-title' ).focus();
            return;
        }

        showNotice( cfg.i18n.loading || 'Guardando formulario…' );

        var data = { action: 'aura_forms_save', nonce: cfg.nonce };
        $( '#aura-meta-panel input, #aura-meta-panel select, #aura-meta-panel textarea' ).each( function () {
            var $el  = $( this );
            var name = $el.attr( 'name' );
            if ( ! name ) return;
            data[ name ] = ( $el.attr( 'type' ) === 'checkbox' ) ? ( $el.is( ':checked' ) ? 1 : undefined ) : $el.val();
        } );

        $.post( cfg.ajaxUrl, data )
            .done( function ( res ) {
                if ( res.success && res.data && res.data.form ) {
                    formId = parseInt( res.data.form.id, 10 );
                    // Actualizar URL sin recargar para que el formulario quede en modo edición
                    var newUrl = 'admin.php?page=aura-forms-list&action=edit&id=' + formId;
                    window.history.replaceState( {}, '', newUrl );
                    showNotice( res.data.message );
                    // Ahora sí abrir el panel del campo
                    openConfigPanel( 0, pendingType, {} );
                } else {
                    showNotice( ( res.data && res.data.message ) || cfg.i18n.error, 'error' );
                }
            } )
            .fail( function () {
                showNotice( cfg.i18n.error, 'error' );
            } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // CANVAS — Editar / Eliminar campos existentes
    // ═════════════════════════════════════════════════════════════════════

    function bindCanvasActions() {
        // Editar campo
        $( document ).on( 'click', '.aura-field-edit', function () {
            var fieldId   = $( this ).data( 'field-id' );
            var $item     = $( this ).closest( '.aura-field-item' );
            var fieldType = $item.data( 'type' );

            // Cargar datos del campo desde el DOM (data-* en el item) o via AJAX
            loadFieldData( fieldId, fieldType );
        } );

        // Eliminar campo
        $( document ).on( 'click', '.aura-field-delete', function () {
            if ( ! window.confirm( cfg.i18n.confirmDeleteField ) ) return;

            var $btn    = $( this );
            var fieldId = $btn.data( 'field-id' );

            $.post( cfg.ajaxUrl, {
                action   : 'aura_forms_field_delete',
                field_id : fieldId,
                form_id  : formId,
                nonce    : cfg.nonce,
            } )
            .done( function ( res ) {
                if ( res.success ) {
                    $( '.aura-field-item[data-field-id="' + fieldId + '"]' ).fadeOut( 250, function () {
                        $( this ).remove();
                        checkCanvasEmpty();
                    } );
                    showNotice( res.data.message );
                } else {
                    showNotice( ( res.data && res.data.message ) || cfg.i18n.error, 'error' );
                }
            } )
            .fail( function () { showNotice( cfg.i18n.error, 'error' ); } );
        } );
    }

    function loadFieldData( fieldId, fieldType ) {
        // Solicitar datos completos del campo al servidor
        $.get( cfg.ajaxUrl, {
            action : 'aura_forms_get',
            id     : formId,
            nonce  : cfg.nonce,
        } )
        .done( function ( res ) {
            if ( ! res.success ) return;
            var field = null;
            $.each( res.data.fields, function ( i, f ) {
                if ( parseInt( f.id, 10 ) === parseInt( fieldId, 10 ) ) {
                    field = f;
                    return false;
                }
            } );
            if ( field ) {
                openConfigPanel( fieldId, fieldType, field );
            }
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // GUARDAR FORMULARIO (metadatos)
    // ═════════════════════════════════════════════════════════════════════

    function bindFormSave() {
        $( document ).on( 'click', '#aura-builder-save', function () {
            var $btn = $( this );
            $btn.prop( 'disabled', true ).text( cfg.i18n.loading );

            var data = { action: 'aura_forms_save', nonce: cfg.nonce };

            // Recoger todos los campos del panel de metadatos
            $( '#aura-meta-panel input, #aura-meta-panel select, #aura-meta-panel textarea' ).each( function () {
                var $el  = $( this );
                var name = $el.attr( 'name' );
                if ( ! name ) return;
                if ( $el.attr( 'type' ) === 'checkbox' ) {
                    data[ name ] = $el.is( ':checked' ) ? 1 : undefined;
                } else {
                    data[ name ] = $el.val();
                }
            } );

            if ( formId ) data.id = formId;

            $.post( cfg.ajaxUrl, data )
                .done( function ( res ) {
                    $btn.prop( 'disabled', false ).text( 'Guardar formulario' );
                    if ( res.success ) {
                        showNotice( res.data.message );
                        if ( res.data.form ) {
                            var newId = parseInt( res.data.form.id, 10 );
                            if ( ! formId ) {
                                // Formulario nuevo: actualizar formId y redirigir
                                formId = newId;
                                window.location.href = 'admin.php?page=aura-forms-list&action=edit&id=' + newId;
                            } else {
                                formId = newId; // Asegurar consistencia en actualizaciones
                            }
                        }
                    } else {
                        showNotice( ( res.data && res.data.message ) || cfg.i18n.error, 'error' );
                    }
                } )
                .fail( function () {
                    $btn.prop( 'disabled', false ).text( 'Guardar formulario' );
                    showNotice( cfg.i18n.error, 'error' );
                } );
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // PANEL DE CONFIGURACIÓN DE CAMPO
    // ═════════════════════════════════════════════════════════════════════

    function openConfigPanel( fieldId, fieldType, fieldData ) {
        var $overlay = $( '#aura-field-config-overlay' );
        var label    = TYPE_LABELS[ fieldType ] || fieldType;

        $( '#aura-config-panel-title' ).text(
            fieldId ? 'Editar: ' + label : 'Agregar: ' + label
        );
        $( '#config-field-id' ).val( fieldId );
        $( '#config-field-type' ).val( fieldType );
        $( '#config-form-id' ).val( formId );

        // Rellenar valores existentes
        $( '#config-label' ).val( fieldData.label || '' );
        $( '#config-description' ).val( fieldData.description || '' );
        $( '#config-required' ).prop( 'checked', fieldData.is_required == 1 );
        $( '#config-placeholder' ).val( fieldData.placeholder || '' );
        $( '#config-default' ).val( fieldData.default_value || '' );
        $( '#config-mapping' ).val( fieldData.mapping_key || '' );
        $( '#config-extensions' ).val( fieldData.allowed_extensions || 'pdf,doc,docx,jpg,jpeg,png' );
        $( '#config-file-size' ).val( fieldData.max_file_size_kb || 5120 );
        $( '#config-max-value' ).val( fieldData.max_value || 10 );
        $( '#config-min' ).val( fieldData.min_value || '' );
        $( '#config-max' ).val( fieldData.max_value || '' );
        $( '#config-image-url' ).val( fieldData.image_url || '' );
        $( '#config-file-uploaded' ).val( fieldData.file_uploaded || '' );
        $( '#config-file-url' ).val( fieldData.file_url || '' );
        $( '#config-instructions' ).val( fieldData.instructions || '' );
        $( '#config-terms-text' ).val( fieldData.terms_text || '' );
        $( '#config-disagreement' ).val( fieldData.disagreement_message || '' );
        $( '#config-multiple-select' ).prop( 'checked', fieldData.multiple_select == 1 );
        $( '#config-has-other' ).prop( 'checked', fieldData.has_other == 1 );

        // Opciones: convertir JSON array → texto una por línea
        var optionsText = '';
        if ( fieldData.options && Array.isArray( fieldData.options ) ) {
            optionsText = fieldData.options.join( '\n' );
        } else if ( fieldData.options_json ) {
            try {
                var arr = JSON.parse( fieldData.options_json );
                if ( Array.isArray( arr ) ) optionsText = arr.join( '\n' );
            } catch ( err ) {}
        }
        $( '#config-options' ).val( optionsText );

        // Aplicar visibilidad según tipo
        applyFieldVisibility( fieldType );

        // Mostrar overlay
        $overlay.fadeIn( 200 );
    }

    function bindConfigPanel() {
        // Cerrar panel
        $( document ).on( 'click', '#aura-field-config-close, #aura-field-config-cancel', function () {
            $( '#aura-field-config-overlay' ).fadeOut( 200 );
        } );

        // Cerrar al clic en el fondo
        $( document ).on( 'click', '#aura-field-config-overlay', function ( e ) {
            if ( $( e.target ).is( '#aura-field-config-overlay' ) ) {
                $( this ).fadeOut( 200 );
            }
        } );

        // Guardar campo
        $( document ).on( 'click', '#aura-field-config-save', function () {
            var $btn    = $( this ).prop( 'disabled', true );

            // ── Resolver form_id desde múltiples fuentes (máxima robustez) ──────
            // 1. Variable del módulo  2. Campo oculto del panel  3. Parámetro ?id= en URL
            var _urlId = parseInt( new URLSearchParams( window.location.search ).get( 'id' ), 10 ) || 0;
            var _panelId = parseInt( $( '#config-form-id' ).val(), 10 ) || 0;
            var _resolved = formId || _panelId || _urlId;
            if ( _resolved && ! formId ) {
                formId = _resolved; // Sincronizar la variable del módulo
            }

            function proceedWithFieldSave() {
                var fieldId = $( '#config-field-id' ).val();
                var fType   = $( '#config-field-type' ).val();

                // Construir opciones JSON desde textarea
                var optionsRaw    = $( '#config-options' ).val();
                var optionsLines  = optionsRaw
                    ? optionsRaw.split( '\n' ).map( function ( l ) { return l.trim(); } ).filter( Boolean )
                    : [];
                var optionsJson   = optionsLines.length ? JSON.stringify( optionsLines ) : '';

                var postData = {
                    action      : 'aura_forms_field_save',
                    nonce       : cfg.nonce,
                    form_id     : formId,
                    field_id    : parseInt( fieldId, 10 ) || 0,
                    field_type  : fType,
                    label       : $( '#config-label' ).val(),
                    description : $( '#config-description' ).val(),
                    is_required : $( '#config-required' ).is( ':checked' ) ? 1 : 0,
                    placeholder : $( '#config-placeholder' ).val(),
                    default_value   : $( '#config-default' ).val(),
                    options_json    : optionsJson,
                    multiple_select : $( '#config-multiple-select' ).is( ':checked' ) ? 1 : 0,
                    has_other       : $( '#config-has-other' ).is( ':checked' ) ? 1 : 0,
                    min_value       : $( '#config-min' ).val(),
                    max_value       : fType === 'scale' ? $( '#config-max-value' ).val() : $( '#config-max' ).val(),
                    allowed_extensions : $( '#config-extensions' ).val(),
                    max_file_size_kb   : $( '#config-file-size' ).val(),
                    image_url          : $( '#config-image-url' ).val(),
                    file_uploaded      : $( '#config-file-uploaded' ).val(),
                    file_url           : $( '#config-file-url' ).val(),
                    instructions       : $( '#config-instructions' ).val(),
                    terms_text         : $( '#config-terms-text' ).val(),
                    disagreement_message: $( '#config-disagreement' ).val(),
                    mapping_key        : $( '#config-mapping' ).val(),
                };

                $.post( cfg.ajaxUrl, postData )
                    .done( function ( res ) {
                        $btn.prop( 'disabled', false );
                        if ( res.success ) {
                            var field = res.data.field;
                            updateCanvasItem( field );
                            $( '#aura-field-config-overlay' ).fadeOut( 200 );
                            showNotice( res.data.message );
                        } else {
                            showNotice( ( res.data && res.data.message ) || cfg.i18n.error, 'error' );
                        }
                    } )
                    .fail( function () {
                        $btn.prop( 'disabled', false );
                        showNotice( cfg.i18n.error, 'error' );
                    } );
            }

            if ( ! formId ) {
                // Formulario no guardado: guardarlo primero, luego guardar el campo
                var title = $( '#form-title' ).val().trim();
                if ( ! title ) {
                    $btn.prop( 'disabled', false );
                    $( '#aura-field-config-overlay' ).fadeOut( 200 );
                    showNotice( 'Escribe primero el t\u00edtulo del formulario.', 'error' );
                    $( '#form-title' ).focus();
                    return;
                }
                var metaData = { action: 'aura_forms_save', nonce: cfg.nonce };
                $( '#aura-meta-panel input, #aura-meta-panel select, #aura-meta-panel textarea' ).each( function () {
                    var $el  = $( this );
                    var name = $el.attr( 'name' );
                    if ( ! name ) return;
                    metaData[ name ] = ( $el.attr( 'type' ) === 'checkbox' ) ? ( $el.is( ':checked' ) ? 1 : undefined ) : $el.val();
                } );
                showNotice( cfg.i18n.loading || 'Guardando formulario\u2026' );
                $.post( cfg.ajaxUrl, metaData )
                    .done( function ( res ) {
                        if ( res.success && res.data && res.data.form ) {
                            formId = parseInt( res.data.form.id, 10 );
                            window.history.replaceState( {}, '', 'admin.php?page=aura-forms-list&action=edit&id=' + formId );
                            proceedWithFieldSave();
                        } else {
                            $btn.prop( 'disabled', false );
                            showNotice( ( res.data && res.data.message ) || cfg.i18n.error, 'error' );
                        }
                    } )
                    .fail( function () {
                        $btn.prop( 'disabled', false );
                        showNotice( cfg.i18n.error, 'error' );
                    } );
                return;
            }

            proceedWithFieldSave();
        } );
    }

    // ── Insertar o actualizar ítem en el canvas ───────────────────────────

    function updateCanvasItem( field ) {
        var $canvas   = $( '#aura-fields-canvas' );
        var fieldId   = field.id;
        var $existing = $( '.aura-field-item[data-field-id="' + fieldId + '"]' );
        var metaText  = field.field_type;
        if ( field.is_required == 1 ) metaText += ' · <em>Requerido</em>';
        if ( field.mapping_key )      metaText += ' · Mapeo: <code>' + field.mapping_key + '</code>';

        var $item = $(
            '<div class="aura-field-item" data-field-id="' + fieldId + '" data-type="' + field.field_type + '">' +
                '<div class="aura-field-item-handle"><span class="dashicons dashicons-menu"></span></div>' +
                '<div class="aura-field-item-info">' +
                    '<strong class="aura-field-item-label">' + ( field.label || '(' + field.field_type + ')' ) + '</strong>' +
                    '<span class="aura-field-item-meta">' + metaText + '</span>' +
                '</div>' +
                '<div class="aura-field-item-actions">' +
                    '<button type="button" class="button button-small aura-field-edit" data-field-id="' + fieldId + '">Editar</button>' +
                    '<button type="button" class="button button-small aura-field-delete" data-field-id="' + fieldId + '"><span class="dashicons dashicons-trash"></span></button>' +
                '</div>' +
            '</div>'
        );

        if ( $existing.length ) {
            $existing.replaceWith( $item );
        } else {
            $( '#aura-canvas-empty' ).hide();
            $canvas.append( $item );
        }
    }

    function checkCanvasEmpty() {
        if ( $( '#aura-fields-canvas .aura-field-item' ).length === 0 ) {
            $( '#aura-canvas-empty' ).show();
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // VISIBILIDAD DEL PANEL SEGÚN TIPO DE CAMPO
    // ═════════════════════════════════════════════════════════════════════

    function applyFieldVisibility( type ) {
        var visible = FIELD_VISIBILITY[ type ] || [ 'label', 'description', 'required' ];

        var allRows = {
            label          : '.config-row-label',
            description    : '.config-row-description',
            required       : '.config-row-required',
            placeholder    : '.config-row-placeholder',
            'default'      : '.config-row-default',
            options        : '.config-row-options',
            multiple_select: '.config-row-multiple-select',
            has_other      : '.config-row-has-other',
            scale_max      : '.config-row-scale-max',
            minmax         : '.config-row-minmax',
            file           : '.config-row-file',
            image          : '.config-row-image',
            downloadable   : '.config-row-downloadable',
            terms          : '.config-row-terms',
            disagreement   : '.config-row-disagreement',
            mapping        : '.config-row-mapping',
        };

        $.each( allRows, function ( key, selector ) {
            if ( visible.indexOf( key ) !== -1 ) {
                $( selector ).show();
            } else {
                $( selector ).hide();
            }
        } );

        // Mostrar mapeo solo si el formulario es tipo enrollment
        var formType = $( '#form-type' ).val();
        if ( formType !== 'enrollment' ) {
            $( '.config-row-mapping' ).hide();
        }
    }

    // Actualizar visibilidad del panel cuando cambia el tipo
    $( document ).on( 'change', '#config-field-type', function () {
        applyFieldVisibility( $( this ).val() );
    } );

    // ═════════════════════════════════════════════════════════════════════
    // SELECTOR DINÁMICO ÁREA → CURSOS
    // ═════════════════════════════════════════════════════════════════════

    function bindAreaCourseSelector() {
        $( document ).on( 'change', '#form-area', function () {
            var areaId = $( this ).val();
            var $select = $( '#form-course' );
            $select.prop( 'disabled', true ).empty().append(
                '<option value="">' + ( cfg.i18n.loading || 'Cargando…' ) + '</option>'
            );

            $.get( cfg.ajaxUrl, {
                action  : 'aura_forms_get_courses',
                area_id : areaId,
                nonce   : cfg.nonce,
            } )
            .done( function ( res ) {
                $select.empty().append( '<option value="">— Sin curso fijo —</option>' );
                if ( res.success && res.data.courses ) {
                    $.each( res.data.courses, function ( i, course ) {
                        $select.append( '<option value="' + course.id + '">' + course.name + '</option>' );
                    } );
                }
                $select.prop( 'disabled', false );
            } )
            .fail( function () {
                $select.prop( 'disabled', false );
            } );
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // VISIBILIDAD TIPO DE FORM (enrollment / feedback)
    // ═════════════════════════════════════════════════════════════════════

    function bindTypeVisibility() {
        $( document ).on( 'change', '#form-type', function () {
            var type = $( this ).val();
            var isEnrollmentLike = [ 'enrollment', 'survey', 'feedback' ].indexOf( type ) !== -1;
            $( '.aura-enrollment-fields' ).toggle( isEnrollmentLike );
            $( '.aura-feedback-fields' ).toggle( type === 'feedback' );
            $( '#aura-enrollment-defaults-banner' ).toggle( type === 'enrollment' );
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // CAMPOS PREDETERMINADOS DE INSCRIPCIÓN
    // ═════════════════════════════════════════════════════════════════════

    function bindEnrollmentDefaults() {
        $( document ).on( 'click', '#aura-insert-enrollment-defaults', function () {
            var $btn = $( this ).prop( 'disabled', true );

            if ( ! formId ) {
                // Auto-guardar formulario primero
                var title = $( '#form-title' ).val().trim();
                if ( ! title ) {
                    $btn.prop( 'disabled', false );
                    showNotice( 'Escribe primero el t\u00edtulo del formulario.', 'error' );
                    $( '#form-title' ).focus();
                    return;
                }
                var metaData = { action: 'aura_forms_save', nonce: cfg.nonce };
                $( '#aura-meta-panel input, #aura-meta-panel select, #aura-meta-panel textarea' ).each( function () {
                    var $el  = $( this );
                    var name = $el.attr( 'name' );
                    if ( ! name ) return;
                    metaData[ name ] = ( $el.attr( 'type' ) === 'checkbox' ) ? ( $el.is( ':checked' ) ? 1 : undefined ) : $el.val();
                } );
                showNotice( cfg.i18n.loading || 'Guardando formulario\u2026' );
                $.post( cfg.ajaxUrl, metaData ).done( function ( res ) {
                    if ( res.success && res.data && res.data.form ) {
                        formId = parseInt( res.data.form.id, 10 );
                        window.history.replaceState( {}, '', 'admin.php?page=aura-forms-list&action=edit&id=' + formId );
                        doInsertDefaults( $btn );
                    } else {
                        $btn.prop( 'disabled', false );
                        showNotice( ( res.data && res.data.message ) || cfg.i18n.error, 'error' );
                    }
                } ).fail( function () {
                    $btn.prop( 'disabled', false );
                    showNotice( cfg.i18n.error, 'error' );
                } );
                return;
            }

            doInsertDefaults( $btn );
        } );
    }

    function doInsertDefaults( $btn ) {
        showNotice( 'Insertando campos predeterminados\u2026' );
        $.post( cfg.ajaxUrl, {
            action  : 'aura_forms_insert_enrollment_defaults',
            form_id : formId,
            nonce   : cfg.nonce,
        } )
        .done( function ( res ) {
            $btn.prop( 'disabled', false );
            if ( res.success ) {
                showNotice( res.data.message );
                // Recargar la p\u00e1gina para mostrar los nuevos campos en el canvas
                window.location.reload();
            } else {
                showNotice( ( res.data && res.data.message ) || cfg.i18n.error, 'error' );
            }
        } )
        .fail( function () {
            $btn.prop( 'disabled', false );
            showNotice( cfg.i18n.error, 'error' );
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // MEDIA LIBRARY PICKER
    // ═════════════════════════════════════════════════════════════════════

    function bindMediaPickers() {
        $( document ).on( 'click', '.aura-media-picker', function ( e ) {
            e.preventDefault();
            var targetId = $( this ).data( 'target' );
            if ( typeof wp === 'undefined' || ! wp.media ) {
                window.alert( 'La biblioteca de medios no está disponible.' );
                return;
            }
            var frame = wp.media( {
                title    : 'Seleccionar archivo',
                button   : { text: 'Usar este archivo' },
                multiple : false,
            } );
            frame.on( 'select', function () {
                var attachment = frame.state().get( 'selection' ).first().toJSON();
                $( '#' + targetId ).val( attachment.url );
            } );
            frame.open();
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // TABS DE DESCARGABLE
    // ═════════════════════════════════════════════════════════════════════

    function bindDownloadableTabs() {
        $( document ).on( 'click', '.aura-dl-tab', function () {
            var tab = $( this ).data( 'tab' );
            $( '.aura-dl-tab' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            if ( tab === 'upload' ) {
                $( '#aura-dl-upload' ).show();
                $( '#aura-dl-url' ).hide();
            } else {
                $( '#aura-dl-upload' ).hide();
                $( '#aura-dl-url' ).show();
            }
        } );
    }

    // ═════════════════════════════════════════════════════════════════════
    // HELPER: Mostrar notificación
    // ═════════════════════════════════════════════════════════════════════

    function showNotice( message, type ) {
        type = type || 'success';
        var $notice = $( '<div class="aura-builder-notice aura-builder-notice--' + type + '">' + message + '</div>' );
        var $container = $( '#aura-builder-notice' );
        $container.empty().append( $notice ).show();
        setTimeout( function () { $container.fadeOut( 400 ); }, 3500 );
    }

    // ═════════════════════════════════════════════════════════════════════
    // ARRANQUE
    // ═════════════════════════════════════════════════════════════════════

    $( document ).ready( function () {
        init();
    } );

} ( jQuery ) );

