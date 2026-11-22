/**
 * vehicle-settings.js — Fase 9 + 10: Configuración del Módulo
 *
 * - Navegación por pestañas (General | Operación | Auditoría | Notificaciones | Integración Financiera)
 * - Carga y guarda opciones via REST API (/aura/v1/vehicles/settings)
 * - Botón "Ejecutar revisión ahora" para alertas de mantenimiento
 * - Carga dinámica de categorías financieras en selects
 *
 * Depende de: jQuery
 * Config: #aura-veh-cfg-nonce, #aura-veh-cfg-rest-base
 *
 * @package Aura_Business_Suite\Vehicles
 */

/* global jQuery */

( function ( $ ) {
    'use strict';

    var __ = ( window.wp && window.wp.i18n && window.wp.i18n.__ ) ? window.wp.i18n.__ : function ( s ) { return s; };
    var sprintf = ( window.wp && window.wp.i18n && window.wp.i18n.sprintf ) ? window.wp.i18n.sprintf : function () {
        var args = Array.prototype.slice.call( arguments );
        var fmt = args.shift() || '';
        var i = 0;
        return String( fmt ).replace( /%(?:(\d+)\$)?([sd%])/g, function ( m, pos, type ) {
            if ( type === '%' ) return '%';
            var idx = pos ? ( parseInt( pos, 10 ) - 1 ) : i++;
            return args[ idx ] !== undefined ? args[ idx ] : '';
        } );
    };

    // ── Config ─────────────────────────────────────────────────────
    var nonce    = $( '#aura-veh-cfg-nonce' ).val();
    var restBase = $( '#aura-veh-cfg-rest-base' ).val();
    var endpoint = restBase + 'vehicles/settings';

    // ── Referencias DOM ───────────────────────────────────────────
    var $wrap     = $( '#aura-veh-settings-wrap' );
    var $spinner  = $( '#aura-veh-settings-spinner' );
    var $msg      = $( '#aura-veh-settings-msg' );
    var $notice   = $( '#aura-veh-settings-notice' );

    // ── Campos del formulario ──────────────────────────────────────
    var fieldMap = {
        'aura_vehicles_module_name'             : '#veh_module_name',
        'aura_vehicles_rate_per_km'             : '#veh_rate_per_km',
        'aura_vehicles_km_before_maintenance'   : '#veh_km_interval',
        'aura_vehicles_block_with_pending_maint': '#veh_block_rental',
        'aura_vehicles_audit_retention_days'    : '#veh_audit_days',
        'aura_vehicles_alert_emails'            : '#veh_alert_emails',
        // Fase 10: Integración Financial
        'aura_vehicles_fin_integration_enabled' : '#veh_fin_enabled',
        'aura_vehicles_fin_income_category_id'  : '#veh_fin_income_cat',
        'aura_vehicles_fin_expense_category_id' : '#veh_fin_expense_cat',
        'aura_vehicles_fin_sync_trip_expenses'  : '#veh_fin_sync_expenses',
    };

    // IDs guardados para pre-seleccionar tras cargar las opciones de los selects
    var savedIncomeCat  = parseInt( $( '#aura-veh-cfg-fin-income' ).val(), 10 ) || 0;
    var savedExpenseCat = parseInt( $( '#aura-veh-cfg-fin-expense' ).val(), 10 ) || 0;

    // ==============================================================
    // 1. Navegación por pestañas
    // ==============================================================

    var catalogTabs = [ 'tab-destinations', 'tab-purposes', 'tab-expenses' ];

    $wrap.on( 'click', '.aura-veh-settings-tab', function ( e ) {
        e.preventDefault();
        var tabId = $( this ).data( 'tab' );

        $( '.aura-veh-settings-tab' ).removeClass( 'is-active nav-tab-active' ).attr( 'aria-selected', 'false' );
        $( this ).addClass( 'is-active nav-tab-active' ).attr( 'aria-selected', 'true' );

        $( '.aura-veh-settings-panel' ).hide();
        $( '#' + tabId ).show();

        // Mostrar/ocultar barra de guardar según tipo de tab
        if ( catalogTabs.indexOf( tabId ) !== -1 ) {
            $( '#aura-veh-settings-save-bar' ).hide();
            // Cargar catálogo si está vacío
            var type = $( this ).data( 'catalog-type' );
            if ( type ) {
                var $container = $( '#aura-veh-cat-list-' + type );
                if ( $container.find( '.aura-veh-cat-loading' ).length ) {
                    loadCatalogItems( type );
                }
            }
        } else {
            $( '#aura-veh-settings-save-bar' ).show();
        }
    } );

    // ==============================================================
    // 2. Cargar configuración al iniciar
    // ==============================================================

    function loadSettings() {
        $.ajax( {
            url    : endpoint,
            method : 'GET',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
            },
            success: function ( data ) {
                populateForm( data );
            },
            error: function () {
                showNotice( 'error', __( 'No se pudo cargar la configuración actual.', 'aura-suite' ) );
            },
        } );
    }

    function populateForm( data ) {
        $.each( fieldMap, function ( key, selector ) {
            var $field = $( selector );
            if ( ! $field.length ) { return; }

            var val = data[ key ];

            if ( $field.is( ':checkbox' ) ) {
                $field.prop( 'checked', val === true || val === 1 || val === '1' );
            } else {
                $field.val( val !== undefined ? val : '' );
            }
        } );
    }

    // ==============================================================
    // 3. Guardar configuración
    // ==============================================================

    $( '#aura-veh-save-settings' ).on( 'click', function () {
        var payload = collectForm();

        $spinner.css( 'visibility', 'visible' );
        $msg.text( '' );
        clearNotice();

        $.ajax( {
            url    : endpoint,
            method : 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
                xhr.setRequestHeader( 'Content-Type', 'application/json' );
            },
            data   : JSON.stringify( payload ),
            success: function ( response ) {
                $spinner.css( 'visibility', 'hidden' );
                showNotice( 'success', response.message || __( 'Configuración guardada.', 'aura-suite' ) );
            },
            error: function ( xhr ) {
                $spinner.css( 'visibility', 'hidden' );
                var errMsg = __( 'Error al guardar la configuración.', 'aura-suite' );
                if ( xhr.responseJSON && xhr.responseJSON.message ) {
                    errMsg = xhr.responseJSON.message;
                }
                showNotice( 'error', errMsg );
            },
        } );
    } );

    function collectForm() {
        var payload = {};

        $.each( fieldMap, function ( key, selector ) {
            var $field = $( selector );
            if ( ! $field.length ) { return; }

            if ( $field.is( ':checkbox' ) ) {
                payload[ key ] = $field.is( ':checked' );
            } else if ( $field.attr( 'type' ) === 'number' ) {
                payload[ key ] = parseFloat( $field.val() ) || 0;
            } else {
                payload[ key ] = $field.val();
            }
        } );

        return payload;
    }

    // ==============================================================
    // 4. Ejecutar revisión de alertas manualmente
    // ==============================================================

    $( '#aura-veh-run-alerts-now' ).on( 'click', function () {
        var $btn    = $( this );
        var $result = $( '#aura-veh-alerts-result' );

        $btn.prop( 'disabled', true );
        $result.text( __( 'Ejecutando…', 'aura-suite' ) );

        $.ajax( {
            url    : restBase + 'vehicles/settings/run-alerts',
            method : 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
            },
            success: function ( data ) {
                $result.text( data.message || __( 'Revisión completada.', 'aura-suite' ) );
            },
            error: function () {
                $result.text( __( 'Error al ejecutar la revisión.', 'aura-suite' ) );
            },
            complete: function () {
                $btn.prop( 'disabled', false );
            },
        } );
    } );

    // ==============================================================
    // 5. Helpers UI
    // ==============================================================

    function showNotice( type, message ) {
        var cssClass = 'notice notice-' + ( type === 'success' ? 'success' : 'error' ) + ' is-dismissible';
        $notice
            .attr( 'class', cssClass )
            .html( '<p>' + escHtml( message ) + '</p>' )
            .show();

        if ( type === 'success' ) {
            setTimeout( function () { $notice.fadeOut(); }, 4000 );
        }
    }

    function clearNotice() {
        $notice.hide().text( '' );
    }

    function escHtml( text ) {
        return $( '<span>' ).text( text ).html();
    }

    // ==============================================================
    // Init
    // ==============================================================

    $( function () {
        loadFinancialCategories();
        loadSettings();
    } );

    // ==============================================================
    // 5. Cargar categorías financieras en los selects
    // ==============================================================

    function loadFinancialCategories() {
        var $incomeSelect  = $( '#veh_fin_income_cat' );
        var $expenseSelect = $( '#veh_fin_expense_cat' );

        if ( ! $incomeSelect.length ) { return; }

        function buildOptions( $select, items, selectedId ) {
            $select.empty();
            $select.append( $( '<option>', { value: 0, text: __( '— Selecciona una categoría —', 'aura-suite' ) } ) );
            $.each( items, function ( i, cat ) {
                var $opt = $( '<option>', { value: cat.id, text: cat.name } );
                if ( cat.id === selectedId ) {
                    $opt.prop( 'selected', true );
                }
                $select.append( $opt );
            } );
        }

        // Cargar categorías de ingreso
        $.ajax( {
            url    : restBase + 'vehicles/settings/financial-categories',
            method : 'GET',
            data   : { type: 'income' },
            beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', nonce ); },
            success: function ( items ) {
                if ( items && items.length ) {
                    buildOptions( $incomeSelect, items, savedIncomeCat );
                } else {
                    $incomeSelect.html( '<option value="0">' + escHtml( __( '— Módulo Financial no disponible —', 'aura-suite' ) ) + '</option>' );
                }
            },
            error: function () {
                $incomeSelect.html( '<option value="0">' + escHtml( __( '— Error al cargar —', 'aura-suite' ) ) + '</option>' );
            },
        } );

        // Cargar categorías de egreso
        $.ajax( {
            url    : restBase + 'vehicles/settings/financial-categories',
            method : 'GET',
            data   : { type: 'expense' },
            beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', nonce ); },
            success: function ( items ) {
                if ( items && items.length ) {
                    buildOptions( $expenseSelect, items, savedExpenseCat );
                } else {
                    $expenseSelect.html( '<option value="0">' + escHtml( __( '— Módulo Financial no disponible —', 'aura-suite' ) ) + '</option>' );
                }
            },
            error: function () {
                $expenseSelect.html( '<option value="0">' + escHtml( __( '— Error al cargar —', 'aura-suite' ) ) + '</option>' );
            },
        } );
    }

    // ==============================================================
    // 6. CRUD de Catálogos (Destinos / Propósitos / Gastos)
    // ==============================================================

    var catalogEndpoint = restBase + 'vehicles/catalogs';

    var catalogLabels = {
        destination : { singular: __( 'Destino', 'aura-suite' ),   plural: __( 'Destinos', 'aura-suite' )   },
        purpose     : { singular: __( 'Propósito', 'aura-suite' ),  plural: __( 'Propósitos', 'aura-suite' ) },
        expense     : { singular: __( 'Gasto', 'aura-suite' ),      plural: __( 'Gastos', 'aura-suite' )     },
    };

    // ── Cargar lista ──────────────────────────────────────────────
    function loadCatalogItems( type, includeInactive ) {
        var $container = $( '#aura-veh-cat-list-' + type );
        $container.html(
            '<div class="aura-veh-cat-loading">' +
            '<span class="spinner is-active" style="float:none;vertical-align:middle;"></span> ' +
            escHtml( __( 'Cargando…', 'aura-suite' ) ) + '</div>'
        );

        $.ajax( {
            url    : catalogEndpoint,
            method : 'GET',
            data   : {
                type             : type,
                include_inactive : includeInactive ? 1 : 0,
            },
            beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', nonce ); },
            success: function ( response ) {
                var items = response && response.items ? response.items : response;
                renderCatalogTable( type, items );
            },
            error: function () {
                $container.html( '<p class="aura-veh-cat-empty">' + escHtml( __( 'Error al cargar los ítems.', 'aura-suite' ) ) + '</p>' );
            },
        } );
    }

    // ── Renderizar tabla ─────────────────────────────────────────
    function renderCatalogTable( type, items ) {
        var $container = $( '#aura-veh-cat-list-' + type );
        var label = catalogLabels[ type ] || { singular: __( 'Ítem', 'aura-suite' ), plural: __( 'Ítems', 'aura-suite' ) };

        if ( ! items || ! items.length ) {
            $container.html(
                '<p class="aura-veh-cat-empty">' +
                '<span class="dashicons dashicons-info-outline"></span> ' +
                escHtml( sprintf( __( 'No hay %s registrados todavía.', 'aura-suite' ), label.plural.toLowerCase() ) ) +
                '</p>'
            );
            return;
        }

        var html = '<table class="aura-veh-cat-table wp-list-table widefat fixed striped">' +
            '<thead><tr>' +
            '<th style="width:44%">' + escHtml( __( 'Nombre', 'aura-suite' ) ) + '</th>' +
            '<th>' + escHtml( __( 'Descripción', 'aura-suite' ) ) + '</th>' +
            '<th style="width:90px;text-align:center;">' + escHtml( __( 'Estado', 'aura-suite' ) ) + '</th>' +
            '<th style="width:110px;text-align:right;">' + escHtml( __( 'Acciones', 'aura-suite' ) ) + '</th>' +
            '</tr></thead><tbody>';

        $.each( items, function ( i, item ) {
            var activeHtml = item.active
                ? '<span class="aura-veh-cat-badge aura-veh-cat-badge--active">' + escHtml( __( 'Activo', 'aura-suite' ) ) + '</span>'
                : '<span class="aura-veh-cat-badge aura-veh-cat-badge--inactive">' + escHtml( __( 'Inactivo', 'aura-suite' ) ) + '</span>';

            html += '<tr data-id="' + item.id + '" data-active="' + ( item.active ? '1' : '0' ) + '">' +
                '<td><strong>' + escHtml( item.name ) + '</strong></td>' +
                '<td class="aura-veh-cat-desc">' + escHtml( item.description || '' ) + '</td>' +
                '<td style="text-align:center;">' + activeHtml + '</td>' +
                '<td style="text-align:right;">' +
                '<button type="button" class="button button-small aura-veh-cat-edit-btn" ' +
                    'data-id="' + item.id + '" ' +
                    'data-name="' + escAttr( item.name ) + '" ' +
                    'data-description="' + escAttr( item.description || '' ) + '" ' +
                    'data-active="' + ( item.active ? '1' : '0' ) + '" ' +
                    'data-type="' + escAttr( type ) + '" ' +
                    'title="' + escAttr( __( 'Editar', 'aura-suite' ) ) + '">' +
                    '<span class="dashicons dashicons-edit"></span>' +
                '</button> ' +
                '<button type="button" class="button button-small aura-veh-cat-toggle-btn" ' +
                    'data-id="' + item.id + '" ' +
                    'data-active="' + ( item.active ? '1' : '0' ) + '" ' +
                    'data-type="' + escAttr( type ) + '" ' +
                    'title="' + escAttr( item.active ? __( 'Desactivar', 'aura-suite' ) : __( 'Activar', 'aura-suite' ) ) + '">' +
                    '<span class="dashicons ' + ( item.active ? 'dashicons-hidden' : 'dashicons-visibility' ) + '"></span>' +
                '</button> ' +
                '<button type="button" class="button button-small aura-veh-cat-delete-btn" ' +
                    'data-id="' + item.id + '" ' +
                    'data-name="' + escAttr( item.name ) + '" ' +
                    'data-type="' + escAttr( type ) + '" ' +
                    'title="' + escAttr( __( 'Eliminar', 'aura-suite' ) ) + '">' +
                    '<span class="dashicons dashicons-trash"></span>' +
                '</button>' +
                '</td></tr>';
        } );

        html += '</tbody></table>';
        $container.html( html );
    }

    // ── Abrir modal ───────────────────────────────────────────────
    function openCatalogModal( type, item ) {
        var label = catalogLabels[ type ] || { singular: __( 'Ítem', 'aura-suite' ) };
        var isEdit = item && item.id;

        $( '#aura-veh-settings-cat-modal-title' ).text( isEdit ? sprintf( __( 'Editar %s', 'aura-suite' ), label.singular ) : sprintf( __( 'Nuevo %s', 'aura-suite' ), label.singular ) );
        $( '#aura-veh-scat-type' ).val( type );
        $( '#aura-veh-scat-id' ).val( isEdit ? item.id : '' );
        $( '#aura-veh-scat-name' ).val( isEdit ? item.name : '' );
        $( '#aura-veh-scat-description' ).val( isEdit ? ( item.description || '' ) : '' );
        $( '#aura-veh-scat-active' ).prop( 'checked', isEdit ? item.active === '1' || item.active === 1 || item.active === true : true );
        $( '#aura-veh-scat-error' ).hide().text( '' );
        $( '#aura-veh-scat-spinner' ).css( 'visibility', 'hidden' );

        $( '#aura-veh-settings-cat-modal' ).show().removeAttr( 'aria-hidden' );
        setTimeout( function () { $( '#aura-veh-scat-name' ).trigger( 'focus' ); }, 80 );
    }

    function closeCatalogModal() {
        $( '#aura-veh-settings-cat-modal' ).hide().attr( 'aria-hidden', 'true' );
        $( '#aura-veh-settings-cat-form' )[0].reset();
    }

    // ── Botón "Nuevo" ─────────────────────────────────────────────
    $wrap.on( 'click', '.aura-veh-cat-add-btn', function () {
        var type = $( this ).data( 'catalog-type' );
        openCatalogModal( type, null );
    } );

    // ── Botón "Editar" ────────────────────────────────────────────
    $wrap.on( 'click', '.aura-veh-cat-edit-btn', function () {
        var $btn = $( this );
        openCatalogModal( $btn.data( 'type' ), {
            id          : $btn.data( 'id' ),
            name        : $btn.data( 'name' ),
            description : $btn.data( 'description' ),
            active      : $btn.data( 'active' ),
        } );
    } );

    // ── Botón "Toggle activo" ─────────────────────────────────────
    $wrap.on( 'click', '.aura-veh-cat-toggle-btn', function () {
        var $btn   = $( this );
        var id     = $btn.data( 'id' );
        var type   = $btn.data( 'type' );
        var active = $btn.data( 'active' );
        var newActive = active === '1' || active === 1 ? 0 : 1;

        $btn.prop( 'disabled', true );

        $.ajax( {
            url    : catalogEndpoint + '/' + id,
            method : 'PUT',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
                xhr.setRequestHeader( 'Content-Type', 'application/json' );
            },
            data: JSON.stringify( { active: newActive } ),
            success: function ( response ) {
                var showInactive = $( '#aura-veh-cat-show-inactive-' + type ).is( ':checked' );
                loadCatalogItems( type, showInactive );
                if ( response && response.message ) {
                    showNotice( 'success', response.message );
                }
            },
            error: function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : __( 'Error al cambiar estado.', 'aura-suite' );
                showNotice( 'error', msg );
                $btn.prop( 'disabled', false );
            },
        } );
    } );

    // ── Botón "Eliminar" ──────────────────────────────────────────
    $wrap.on( 'click', '.aura-veh-cat-delete-btn', function () {
        var $btn = $( this );
        var id   = $btn.data( 'id' );
        var name = $btn.data( 'name' );
        var type = $btn.data( 'type' );

        if ( ! window.confirm( sprintf( __( '¿Eliminar "%s"? Esta acción no se puede deshacer.', 'aura-suite' ), name ) ) ) {
            return;
        }

        $btn.prop( 'disabled', true );

        $.ajax( {
            url    : catalogEndpoint + '/' + id,
            method : 'DELETE',
            beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', nonce ); },
            success: function ( response ) {
                var showInactive = $( '#aura-veh-cat-show-inactive-' + type ).is( ':checked' );
                loadCatalogItems( type, showInactive );
                showNotice( 'success', response && response.message ? response.message : sprintf( __( '"%s" eliminado correctamente.', 'aura-suite' ), name ) );
            },
            error: function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : __( 'Error al eliminar.', 'aura-suite' );
                showNotice( 'error', msg );
                $btn.prop( 'disabled', false );
            },
        } );
    } );

    // ── Envío del formulario modal ────────────────────────────────
    $( '#aura-veh-settings-cat-form' ).on( 'submit', function ( e ) {
        e.preventDefault();

        var type   = $( '#aura-veh-scat-type' ).val();
        var id     = $( '#aura-veh-scat-id' ).val();
        var name   = $.trim( $( '#aura-veh-scat-name' ).val() );
        var desc   = $.trim( $( '#aura-veh-scat-description' ).val() );
        var active = $( '#aura-veh-scat-active' ).is( ':checked' ) ? 1 : 0;

        if ( ! name ) {
            $( '#aura-veh-scat-error' ).text( __( 'El nombre es obligatorio.', 'aura-suite' ) ).show();
            $( '#aura-veh-scat-name' ).trigger( 'focus' );
            return;
        }

        var payload = { type: type, name: name, description: desc, active: active };
        var isEdit  = !! id;
        var ajaxUrl = isEdit ? catalogEndpoint + '/' + id : catalogEndpoint;
        var method  = isEdit ? 'PUT' : 'POST';

        $( '#aura-veh-scat-error' ).hide();
        $( '#aura-veh-scat-spinner' ).css( 'visibility', 'visible' );
        $( '#aura-veh-scat-submit' ).prop( 'disabled', true );

        $.ajax( {
            url    : ajaxUrl,
            method : method,
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
                xhr.setRequestHeader( 'Content-Type', 'application/json' );
            },
            data: JSON.stringify( payload ),
            success: function ( response ) {
                closeCatalogModal();
                var showInactive = $( '#aura-veh-cat-show-inactive-' + type ).is( ':checked' );
                loadCatalogItems( type, showInactive );
                var label = catalogLabels[ type ] || { singular: __( 'Ítem', 'aura-suite' ) };
                showNotice( 'success', response && response.message ? response.message : ( isEdit ? sprintf( __( '%s actualizado.', 'aura-suite' ), label.singular ) : sprintf( __( '%s creado correctamente.', 'aura-suite' ), label.singular ) ) );
            },
            error: function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : __( 'Error al guardar.', 'aura-suite' );
                $( '#aura-veh-scat-error' ).text( msg ).show();
                $( '#aura-veh-scat-spinner' ).css( 'visibility', 'hidden' );
                $( '#aura-veh-scat-submit' ).prop( 'disabled', false );
            },
        } );
    } );

    // ── Cerrar modal ──────────────────────────────────────────────
    $( '#aura-veh-settings-cat-close, #aura-veh-settings-cat-close-btn' ).on( 'click', closeCatalogModal );
    $( '#aura-veh-settings-cat-overlay' ).on( 'click', closeCatalogModal );
    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' && $( '#aura-veh-settings-cat-modal' ).is( ':visible' ) ) {
            closeCatalogModal();
        }
    } );

    // ── Checkbox "mostrar inactivos" ──────────────────────────────
    $wrap.on( 'change', '#aura-veh-cat-show-inactive-destination', function () {
        loadCatalogItems( 'destination', $( this ).is( ':checked' ) );
    } );
    $wrap.on( 'change', '#aura-veh-cat-show-inactive-purpose', function () {
        loadCatalogItems( 'purpose', $( this ).is( ':checked' ) );
    } );
    $wrap.on( 'change', '#aura-veh-cat-show-inactive-expense', function () {
        loadCatalogItems( 'expense', $( this ).is( ':checked' ) );
    } );

    // ── Helper escAttr ────────────────────────────────────────────
    function escAttr( text ) {
        return String( text )
            .replace( /&/g, '&amp;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#39;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' );
    }

}( jQuery ) );
