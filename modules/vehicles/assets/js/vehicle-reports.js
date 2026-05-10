/**
 * vehicle-reports.js — Fase 6: Reportes y Exportación
 *
 * Gestiona la UI de la página de reportes:
 *  - Cambio de tipo de reporte (tabs)
 *  - Panel de filtros (período personalizado, visibilidad condicional)
 *  - Previsualización vía GET REST → DataTables
 *  - Descarga de CSV y PDF vía formulario POST hacia REST
 *
 * Depende de: jQuery, DataTables 2.x
 * Config: #aura-rep-nonce, #aura-rep-rest-url (inputs hidden en la vista)
 *
 * @package Aura_Business_Suite\Vehicles
 */

/* global jQuery, $ */

( function ( $ ) {
    'use strict';

    // ── Refs DOM ─────────────────────────────────────────────────────
    var $app       = $( '#aura-veh-reports-app' );
    var $typeBtns  = $app.find( '.aura-rep-type-btn' );
    var $period    = $( '#aura-rep-period' );
    var $dateFrom  = $( '#aura-rep-date-from' );
    var $dateTo    = $( '#aura-rep-date-to' );
    var $vehicle   = $( '#aura-rep-vehicle' );
    var $area      = $( '#aura-rep-area' );
    var $tripType  = $( '#aura-rep-trip-type' );
    var $tripWrap  = $( '#aura-rep-trip-type-wrap' );
    var $preview   = $( '#aura-rep-preview' );
    var $noData    = $( '#aura-rep-no-data' );
    var $thead     = $( '#aura-rep-thead' );
    var $tbody     = $( '#aura-rep-tbody' );
    var $tfoot     = $( '#aura-rep-tfoot' );
    var $title     = $( '#aura-rep-preview-title' );
    var $rowCount  = $( '#aura-rep-row-count' );
    var $totals    = $( '#aura-rep-totals' );
    var $spinner   = $( '#aura-rep-spinner' );
    var $notice    = $( '#aura-rep-notice' );
    var $btnPrev   = $( '#aura-rep-preview-btn' );
    var $btnCsv    = $( '#aura-rep-csv-btn' );
    var $btnPdf    = $( '#aura-rep-pdf-btn' );

    var nonce      = $( '#aura-rep-nonce' ).val()   || '';
    var restBase   = $( '#aura-rep-rest-url' ).val() || '';

    // ── Estado ───────────────────────────────────────────────────────
    var currentType = 'trips';
    var dtInstance  = null;
    var lastData    = null;   // Último resultado de previsualización

    // Labels de tipos
    var typeLabels = {
        trips:        'Salidas',
        maintenances: 'Mantenimientos',
        costs:        'Costos',
        vehicles:     'Flota',
        mileage:      'Kilometraje'
    };

    // ── Helpers ──────────────────────────────────────────────────────

    function showNotice( msg, type ) {
        var cls = 'notice notice-' + ( type || 'info' ) + ' inline';
        $notice.attr( 'class', cls ).html( '<p>' + msg + '</p>' ).show();
    }

    function hideNotice() {
        $notice.hide();
    }

    function setBusy( busy ) {
        $spinner.toggleClass( 'is-active', busy );
        $btnPrev.prop( 'disabled', busy );
    }

    function buildParams() {
        var params = {
            type:       currentType,
            period:     $period.val(),
            date_from:  $dateFrom.val(),
            date_to:    $dateTo.val(),
            vehicle_id: $vehicle.length ? $vehicle.val() : 0,
            area_id:    $area.length    ? $area.val()    : 0,
            trip_type:  $tripType.val()
        };
        return params;
    }

    // ── Inicialización de DataTables ─────────────────────────────────

    function initDataTable( headers ) {
        // Destruir instancia previa si existe
        if ( dtInstance ) {
            dtInstance.destroy();
            dtInstance = null;
        }

        // Renderizar cabeceras
        var thHtml = '';
        $.each( headers, function ( i, h ) {
            thHtml += '<th>' + $( '<span>' ).text( h ).html() + '</th>';
        } );
        $thead.html( thHtml );
        $tfoot.html( thHtml );

        // Columnas para DataTables (índice = posición)
        var columns = [];
        $.each( headers, function () {
            columns.push( { defaultContent: '—' } );
        } );

        dtInstance = $( '#aura-rep-table' ).DataTable( {
            data:      [],
            columns:   columns,
            language:  {
                emptyTable:  'Sin registros para los filtros aplicados.',
                info:        '_TOTAL_ registros',
                infoEmpty:   '0 registros',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu:  'Mostrar _MENU_ por página',
                paginate:    { first: '«', last: '»', previous: '‹', next: '›' }
            },
            pageLength:  25,
            order:       [],
            responsive:  true,
            searching:   false,
            autoWidth:   false,
            dom:         '<"aura-dt-top"li>rt<"aura-dt-bottom"p>'
        } );
    }

    // ── Construir barra de totales ───────────────────────────────────

    function renderTotals( totals ) {
        if ( ! totals || typeof totals !== 'object' || Object.keys( totals ).length === 0 ) {
            $totals.hide();
            return;
        }

        var html = '<div class="aura-rep-totals-inner">';
        $.each( totals, function ( key, val ) {
            var label = key.replace( /_/g, ' ' );
            label     = label.charAt( 0 ).toUpperCase() + label.slice( 1 );

            var valStr = ( typeof val === 'number' && ! Number.isInteger( val ) )
                ? parseFloat( val ).toLocaleString( 'es', { minimumFractionDigits: 2 } )
                : parseFloat( val ).toLocaleString( 'es' );

            html += '<div class="aura-rep-total-item">'
                + '<span class="aura-rep-total-label">' + label + '</span>'
                + '<strong class="aura-rep-total-value">' + valStr + '</strong>'
                + '</div>';
        } );
        html += '</div>';

        $totals.html( html ).show();
    }

    // ── Previsualización ─────────────────────────────────────────────

    function doPreview() {
        hideNotice();
        setBusy( true );
        lastData = null;
        $btnCsv.prop( 'disabled', true );
        $btnPdf.prop( 'disabled', true );

        $.ajax( {
            url:     restBase,
            method:  'GET',
            data:    buildParams(),
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
            },
            success: function ( res ) {
                var headers = res.headers || [];
                var rows    = res.rows    || [];
                var totals  = res.totals  || {};

                $title.text( typeLabels[ currentType ] || currentType );
                $rowCount.text( '(' + rows.length + ' registro' + ( rows.length !== 1 ? 's' : '' ) + ')' );

                initDataTable( headers );

                if ( rows.length > 0 ) {
                    dtInstance.rows.add( rows ).draw();
                    $noData.hide();
                    $btnCsv.prop( 'disabled', false );
                    $btnPdf.prop( 'disabled', false );
                } else {
                    $noData.show();
                }

                renderTotals( totals );

                $preview.show();
                lastData = res;
            },
            error: function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Error al obtener los datos. Por favor intenta de nuevo.';
                showNotice( msg, 'error' );
                $preview.hide();
                $totals.hide();
            },
            complete: function () {
                setBusy( false );
            }
        } );
    }

    // ── Exportación ─────────────────────────────────────────────────

    /**
     * Ejecuta una exportación (CSV o PDF) enviando la petición
     * como formulario POST para que el navegador reciba el archivo.
     *
     * @param {string} format  'export-csv' | 'export-pdf'
     */
    function doExport( format ) {
        var params  = buildParams();
        var url     = restBase + '/' + format;

        // Construimos un form temporal para POST
        var $form   = $( '<form>', { method: 'POST', action: url, style: 'display:none;' } );

        $.each( params, function ( key, val ) {
            $form.append( $( '<input>', { type: 'hidden', name: key, value: val } ) );
        } );

        // Nonce para la REST API via cookie/header
        $form.append( $( '<input>', { type: 'hidden', name: '_wpnonce', value: nonce } ) );

        $( 'body' ).append( $form );
        $form.submit();
        $form.remove();
    }

    // ── Listeners ────────────────────────────────────────────────────

    // Cambio de tipo (tabs)
    $typeBtns.on( 'click', function () {
        var $btn = $( this );
        currentType = $btn.data( 'type' );

        $typeBtns.removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
        $btn.addClass( 'is-active' ).attr( 'aria-selected', 'true' );

        // El filtro de tipo de salida solo aplica a "trips" y "maintenances"
        var showTripType = ( currentType === 'trips' || currentType === 'maintenances' );
        $tripWrap.toggle( showTripType );

        // Resetear previsualización al cambiar de tipo
        $preview.hide();
        $totals.hide();
        $btnCsv.prop( 'disabled', true );
        $btnPdf.prop( 'disabled', true );
        lastData = null;
        hideNotice();
    } );

    // Mostrar / ocultar rangos personalizados
    $period.on( 'change', function () {
        var isCustom = 'custom' === $( this ).val();
        $( '#aura-rep-custom-dates, #aura-rep-custom-dates-to' ).toggle( isCustom );
    } );

    // Vista previa
    $btnPrev.on( 'click', function () {
        doPreview();
    } );

    // Descargar CSV
    $btnCsv.on( 'click', function () {
        doExport( 'export-csv' );
    } );

    // Descargar PDF
    $btnPdf.on( 'click', function () {
        doExport( 'export-pdf' );
    } );

    // ── Init ─────────────────────────────────────────────────────────
    // El filtro "tipo de salida" es visible solo para trips al init
    $tripWrap.show();

} )( jQuery );
