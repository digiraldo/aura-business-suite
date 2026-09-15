/**
 * Aura Vehicles — Catálogos (Fase 4 — Card UI)
 * CRUD de destinos, propósitos y gastos via REST API.
 * Cards visuales, color de acento, drag & drop con SortableJS.
 *
 * @package Aura_Business_Suite\Vehicles
 */

( function ( $ ) {
    'use strict';

    if ( typeof auraVehiclesConfig === 'undefined' ) { return; }

    var API = AuraVehicles.api;

    var DEFAULT_COLOR = '#2271b1';

    var TYPE_META = {
        destination : { singular: 'Destino',   plural: 'destinos',   icon: 'dashicons-location' },
        purpose     : { singular: 'Propósito', plural: 'propósitos', icon: 'dashicons-clipboard' },
        expense     : { singular: 'Gasto',     plural: 'gastos',     icon: 'dashicons-money-alt' },
    };

    var state = {
        currentType  : 'destination',
        items        : { destination: [], purpose: [], expense: [] },
        editingId    : null,
        sortables    : {},
        _noticeTimer : null,
    };

    /* ════════════════════════════════════════════════════════════
       INICIALIZACIÓN
    ════════════════════════════════════════════════════════════ */

    function init() {
        if ( $( '#aura-catalogs-page' ).length === 0 ) { return; }
        bindEvents();
        loadAll();
    }

    function bindEvents() {
        $( document ).on( 'click', '.aura-cat-tab', function () {
            switchTab( $( this ).data( 'type' ) );
        } );
        $( document ).on( 'click', '.aura-cat-btn-new', function () {
            openModal( $( this ).data( 'type' ), null );
        } );
        $( document ).on( 'click', '.aura-cat-btn-edit', function () {
            openModal( state.currentType, parseInt( $( this ).data( 'id' ), 10 ) );
        } );
        $( document ).on( 'click', '.aura-cat-btn-delete', function () {
            confirmDelete( parseInt( $( this ).data( 'id' ), 10 ), $( this ).data( 'name' ) );
        } );
        $( document ).on( 'click', '.aura-cat-btn-toggle', function () {
            var active = $( this ).data( 'active' ) === 1 || $( this ).data( 'active' ) === true;
            toggleActive( parseInt( $( this ).data( 'id' ), 10 ), ! active );
        } );
        $( document ).on( 'submit', '#aura-cat-form', function ( e ) {
            e.preventDefault();
            saveForm();
        } );
        $( document ).on( 'click', '#aura-cat-modal-overlay, .aura-cat-modal-close', closeModal );
        $( document ).on( 'click', '.aura-icon-option', function () {
            $( '.aura-icon-option' ).removeClass( 'is-selected' );
            $( this ).addClass( 'is-selected' );
            $( '#aura-cat-icon' ).val( $( this ).data( 'icon' ) );
        } );
        $( document ).on( 'click', '.aura-color-swatch', function () {
            $( '.aura-color-swatch' ).removeClass( 'is-selected' );
            $( this ).addClass( 'is-selected' );
            $( '#aura-cat-color' ).val( $( this ).data( 'color' ) );
        } );
        $( document ).on( 'change', '[name="area_type"]', function () {
            if ( $( this ).val() === 'area' ) {
                $( '#aura-cat-area-selector' ).slideDown( 150 );
            } else {
                $( '#aura-cat-area-selector' ).slideUp( 150 );
                $( '#aura-cat-area-id' ).val( '' );
            }
        } );
    }

    /* ════════════════════════════════════════════════════════════
       CARGA DE DATOS
    ════════════════════════════════════════════════════════════ */

    function loadAll() {
        setLoading( true );
        API( 'vehicles/catalogs?include_inactive=1', 'GET' )
            .done( function ( resp ) {
                state.items = resp.grouped || { destination: [], purpose: [], expense: [] };
                updateAllCountBadges();
                renderCurrentTab();
            } )
            .fail( function () { showNotice( 'error', 'Error al cargar los catálogos.' ); } )
            .always( function () { setLoading( false ); } );
    }

    function updateAllCountBadges() {
        $.each( [ 'destination', 'purpose', 'expense' ], function ( i, type ) {
            var items  = state.items[ type ] || [];
            var active = items.filter( function ( it ) { return it.active; } ).length;
            $( '#aura-cat-count-' + type ).text( active ).toggleClass( 'has-items', active > 0 );
        } );
    }

    /* ════════════════════════════════════════════════════════════
       PESTAÑAS
    ════════════════════════════════════════════════════════════ */

    function switchTab( type ) {
        state.currentType = type;
        $( '.aura-cat-tab' ).removeClass( 'is-active' );
        $( '.aura-cat-tab[data-type="' + type + '"]' ).addClass( 'is-active' );
        $( '.aura-cat-panel' ).hide();
        $( '#aura-cat-panel-' + type ).show();
        renderCurrentTab();
    }

    /* ════════════════════════════════════════════════════════════
       TARJETAS
    ════════════════════════════════════════════════════════════ */

    function renderCurrentTab() {
        var type = state.currentType;
        renderCards( type, state.items[ type ] || [] );
        initSortable( type );
    }

    function renderCards( type, items ) {
        var $grid = $( '#aura-cat-grid-' + type );
        if ( $grid.length === 0 ) { return; }

        var activeCount = items.filter( function ( it ) { return it.active; } ).length;
        $( '#aura-cat-count-' + type ).text( activeCount ).toggleClass( 'has-items', activeCount > 0 );

        if ( items.length === 0 ) {
            var meta = TYPE_META[ type ] || { plural: 'ítems', singular: 'Ítem', icon: 'dashicons-plus-alt' };
            $grid.html(
                '<div class="aura-cat-empty-state">' +
                '<div class="aura-cat-empty-icon"><span class="dashicons ' + meta.icon + '"></span></div>' +
                '<p>No hay <strong>' + escHtml( meta.plural ) + '</strong> aún. ' +
                'Usa <strong>Nuevo ' + escHtml( meta.singular ) + '</strong> para crear el primero.</p>' +
                '</div>'
            );
            return;
        }

        var html = '';
        $.each( items, function ( i, item ) {
            var color     = item.color || DEFAULT_COLOR;
            var iconBg    = hexToRgba( color, 0.12 );
            var iconHtml  = item.icon
                ? '<span class="dashicons ' + escAttr( item.icon ) + '" style="color:' + escAttr( color ) + '"></span>'
                : '<span class="aura-cat-card-icon-text" style="color:' + escAttr( color ) + '">' +
                      escHtml( item.name.charAt( 0 ).toUpperCase() ) + '</span>';
            var areaLabel = item.area_id
                ? '<span class="aura-cat-area-badge">' + escHtml( item.area_name || ( 'Área #' + item.area_id ) ) + '</span>'
                : '<span class="aura-cat-global-badge">🌐 Global</span>';
            var statusBadge = item.active
                ? '<span class="aura-cat-badge aura-cat-badge-active">● Activo</span>'
                : '<span class="aura-cat-badge aura-cat-badge-inactive">○ Inactivo</span>';
            var toggleTitle = item.active ? 'Desactivar' : 'Activar';
            var toggleIcon  = item.active ? 'dashicons-hidden' : 'dashicons-visibility';

            html +=
                '<div class="aura-cat-card' + ( item.active ? '' : ' is-inactive' ) + '"' +
                '     data-id="' + item.id + '" style="border-left-color:' + escAttr( color ) + '">' +
                '  <div class="aura-cat-card-grip" title="Arrastrar para reordenar">' +
                '    <span class="dashicons dashicons-menu"></span></div>' +
                '  <div class="aura-cat-card-top">' +
                '    <div class="aura-cat-card-icon-wrap" style="background:' + iconBg + '">' +
                       iconHtml + '</div>' +
                '    <div class="aura-cat-card-content">' +
                '      <div class="aura-cat-card-name">' + escHtml( item.name ) + '</div>' +
                ( item.description ? '<div class="aura-cat-card-desc">' + escHtml( item.description ) + '</div>' : '' ) +
                '      <div class="aura-cat-card-meta">' + areaLabel + ' ' + statusBadge + '</div>' +
                '    </div>' +
                '  </div>' +
                '  <div class="aura-cat-card-actions">' +
                '    <button class="aura-cat-action-btn aura-cat-btn-edit" data-id="' + item.id + '">' +
                '      <span class="dashicons dashicons-edit"></span> Editar</button>' +
                '    <button class="aura-cat-action-btn aura-cat-btn-toggle"' +
                '            data-id="' + item.id + '" data-active="' + ( item.active ? 1 : 0 ) + '">' +
                '      <span class="dashicons ' + toggleIcon + '"></span> ' + escHtml( toggleTitle ) + '</button>' +
                '    <button class="aura-cat-action-btn aura-cat-btn-delete is-destructive"' +
                '            data-id="' + item.id + '" data-name="' + escAttr( item.name ) + '"' +
                '            style="flex:0;padding:5px 8px;">' +
                '      <span class="dashicons dashicons-trash"></span></button>' +
                '  </div>' +
                '</div>';
        } );

        $grid.html( html );
    }

    function hexToRgba( hex, alpha ) {
        var r = parseInt( hex.slice( 1, 3 ), 16 );
        var g = parseInt( hex.slice( 3, 5 ), 16 );
        var b = parseInt( hex.slice( 5, 7 ), 16 );
        if ( isNaN(r) || isNaN(g) || isNaN(b) ) { r = 34; g = 113; b = 177; }
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    /* ════════════════════════════════════════════════════════════
       DRAG & DROP
    ════════════════════════════════════════════════════════════ */

    function initSortable( type ) {
        var el = document.getElementById( 'aura-cat-grid-' + type );
        if ( ! el ) { return; }
        if ( state.sortables[ type ] ) { state.sortables[ type ].destroy(); }
        if ( typeof Sortable === 'undefined' ) { return; }

        state.sortables[ type ] = Sortable.create( el, {
            handle    : '.aura-cat-card-grip',
            animation : 200,
            ghostClass : 'aura-cat-card-ghost',
            dragClass  : 'aura-cat-card-dragging',
            onEnd : function () {
                var ids = [];
                $( '#aura-cat-grid-' + type + ' .aura-cat-card[data-id]' ).each( function () {
                    ids.push( parseInt( $( this ).data( 'id' ), 10 ) );
                } );
                persistReorder( ids );
            },
        } );
    }

    function persistReorder( ids ) {
        API( 'vehicles/catalogs/reorder', 'PATCH', { ids: ids } )
            .fail( function () { showNotice( 'error', 'Error al guardar el nuevo orden.' ); } );
    }

    /* ════════════════════════════════════════════════════════════
       MODAL
    ════════════════════════════════════════════════════════════ */

    function openModal( type, id ) {
        state.editingId = id;
        resetForm();
        $( '#aura-cat-field-type' ).val( type );
        var meta = TYPE_META[ type ] || { singular: type };
        $( '#aura-cat-modal-title' ).text( id ? 'Editar ' + meta.singular : 'Nuevo ' + meta.singular );

        if ( id ) {
            API( 'vehicles/catalogs/' + id, 'GET' )
                .done( populateForm )
                .fail( function () {
                    showNotice( 'error', 'Error al cargar el ítem.' );
                    closeModal();
                } );
        }
        $( '#aura-cat-modal' ).fadeIn( 150 );
        $( 'body' ).addClass( 'aura-modal-open' );
    }

    function closeModal() {
        $( '#aura-cat-modal' ).fadeOut( 120 );
        $( 'body' ).removeClass( 'aura-modal-open' );
        state.editingId = null;
    }

    function resetForm() {
        $( '#aura-cat-form' ).trigger( 'reset' );
        $( '#aura-cat-icon' ).val( '' );
        $( '.aura-icon-option' ).removeClass( 'is-selected' );
        $( '#aura-cat-color' ).val( DEFAULT_COLOR );
        $( '.aura-color-swatch' ).removeClass( 'is-selected' );
        $( '.aura-color-swatch[data-color="' + DEFAULT_COLOR + '"]' ).addClass( 'is-selected' );
        $( '#aura-cat-area-selector' ).hide();
        $( '[name="area_type"][value="global"]' ).prop( 'checked', true );
    }

    function populateForm( item ) {
        $( '#aura-cat-name' ).val( item.name );
        $( '#aura-cat-description' ).val( item.description || '' );
        $( '#aura-cat-icon' ).val( item.icon || '' );
        if ( item.icon ) {
            $( '.aura-icon-option[data-icon="' + item.icon + '"]' ).addClass( 'is-selected' );
        }
        var color = item.color || DEFAULT_COLOR;
        $( '#aura-cat-color' ).val( color );
        $( '.aura-color-swatch' ).removeClass( 'is-selected' );
        $( '.aura-color-swatch[data-color="' + color + '"]' ).addClass( 'is-selected' );
        if ( item.area_id ) {
            $( '[name="area_type"][value="area"]' ).prop( 'checked', true );
            $( '#aura-cat-area-selector' ).show();
            $( '#aura-cat-area-id' ).val( item.area_id );
        }
    }

    /* ════════════════════════════════════════════════════════════
       GUARDAR
    ════════════════════════════════════════════════════════════ */

    function saveForm() {
        var data = {
            type        : $( '#aura-cat-field-type' ).val(),
            name        : $.trim( $( '#aura-cat-name' ).val() ),
            description : $.trim( $( '#aura-cat-description' ).val() ),
            icon        : $( '#aura-cat-icon' ).val(),
            color       : $( '#aura-cat-color' ).val() || DEFAULT_COLOR,
            area_id     : $( '[name="area_type"]:checked' ).val() === 'area'
                          ? ( parseInt( $( '#aura-cat-area-id' ).val(), 10 ) || null )
                          : null,
        };

        if ( ! data.name ) {
            showNotice( 'error', 'El nombre es obligatorio.' );
            $( '#aura-cat-name' ).focus();
            return;
        }

        var $btn   = $( '#aura-cat-form button[type="submit"]' );
        $btn.prop( 'disabled', true ).text( 'Guardando…' );

        var isEdit   = !! state.editingId;
        var endpoint = isEdit ? 'vehicles/catalogs/' + state.editingId : 'vehicles/catalogs';
        var method   = isEdit ? 'PUT' : 'POST';

        API( endpoint, method, data )
            .done( function () {
                showNotice( 'success',
                    '<span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:4px;"></span>' +
                    ( isEdit ? 'Ítem actualizado.' : 'Ítem creado correctamente.' )
                );
                closeModal();
                loadAll();
            } )
            .fail( function ( xhr ) {
                var msg = ( xhr.responseJSON && xhr.responseJSON.message )
                    ? xhr.responseJSON.message : 'Error al guardar.';
                showNotice( 'error', msg );
            } )
            .always( function () {
                $btn.prop( 'disabled', false ).text( 'Guardar' );
            } );
    }

    /* ════════════════════════════════════════════════════════════
       TOGGLE / ELIMINAR
    ════════════════════════════════════════════════════════════ */

    function toggleActive( id, newActive ) {
        API( 'vehicles/catalogs/' + id, 'PUT', { active: newActive ? 1 : 0 } )
            .done( loadAll )
            .fail( function () { showNotice( 'error', 'Error al cambiar el estado.' ); } );
    }

    function confirmDelete( id, name ) {
        if ( ! window.confirm(
            '¿Eliminar "' + name + '"?\n\n' +
            'Si tiene salidas registradas, se desactivará en lugar de eliminarse.'
        ) ) { return; }

        API( 'vehicles/catalogs/' + id, 'DELETE' )
            .done( function ( resp ) {
                showNotice( 'success', ( resp && resp.message ) ? resp.message : 'Ítem eliminado.' );
                loadAll();
            } )
            .fail( function () { showNotice( 'error', 'Error al eliminar el ítem.' ); } );
    }

    /* ════════════════════════════════════════════════════════════
       UTILIDADES
    ════════════════════════════════════════════════════════════ */

    function showNotice( type, msg ) {
        var $n = $( '#aura-cat-notice' );
        $n.removeClass( 'notice-success notice-error' )
          .addClass( 'notice notice-' + ( type === 'success' ? 'success' : 'error' ) )
          .find( '.aura-cat-notice-text' ).html( msg );
        $n.slideDown( 200 );
        clearTimeout( state._noticeTimer );
        state._noticeTimer = setTimeout( function () { $n.slideUp( 200 ); }, 4200 );
    }

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
    }

    function escAttr( str ) {
        return String( str ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
    }

    function setLoading( on ) {
        $( '#aura-catalogs-page' ).toggleClass( 'is-loading', on );
    }

    $( document ).ready( init );

} )( jQuery );
