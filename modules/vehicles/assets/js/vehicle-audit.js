/**
 * vehicle-audit.js — Fase 7: Auditoría
 *
 * Gestiona la UI de la página de auditoría con DataTables Responsive:
 *  - Filtros (operación, usuario, fechas, IP, búsqueda libre)
 *  - Carga de registros vía REST → DataTables client-side
 *  - Badges de operaciones con color por categoría
 *  - Modal de detalles con tabla + JSON formateado
 *  - Exportación CSV
 *  - Modal de limpieza de logs antiguos
 *
 * Depende de: jQuery, DataTables 2.x + Responsive, aura-vehicle-admin
 * Config:  #aura-aud-nonce, #aura-aud-rest-base, #aura-aud-op-labels
 *
 * @package Aura_Business_Suite\Vehicles
 */

/* global jQuery */

( function ( $ ) {
    'use strict';

    // ── Refs DOM ─────────────────────────────────────────────────────
    var $app       = $( '#aura-veh-audit-app' );
    var nonce      = $( '#aura-aud-nonce' ).val();
    var restBase   = $( '#aura-aud-rest-base' ).val();

    var opLabels = {};
    try {
        opLabels = JSON.parse( $( '#aura-aud-op-labels' ).text() || '{}' );
    } catch ( e ) {}

    var $spinner     = $( '#aura-aud-spinner' );
    var $summary     = $( '#aura-aud-summary' );
    var $totalText   = $( '#aura-aud-total-text' );
    var $detailModal = $( '#aura-aud-detail-modal' );
    var $detailTable = $( '#aura-aud-detail-table tbody' );
    var $detailJson  = $( '#aura-aud-detail-json' );
    var $cleanModal  = $( '#aura-aud-cleanup-modal' );

    // ── Estado ───────────────────────────────────────────────────────
    var _table      = null;
    var _rowsById   = {};   // caché de filas por id para el modal de detalles
    var lastFilters = {};

    // ── Color de badges por categoría de operación ───────────────────
    var opColors = {
        // Vehículos
        vehicle_created:            '#0073aa',
        vehicle_updated:            '#0073aa',
        vehicle_deleted:            '#d63638',
        vehicle_area_assigned:      '#00a32a',
        vehicle_area_unassigned:    '#dc843a',
        vehicle_marked_unavailable: '#d63638',
        vehicle_restored:           '#00a32a',
        vehicle_transferred:        '#9b51e0',
        vehicle_photo_uploaded:     '#0073aa',
        vehicle_photo_deleted:      '#d63638',
        // Salidas
        trip_create:  '#00a32a',
        trip_checkin: '#0073aa',
        trip_cancel:  '#d63638',
        trip_update:  '#dc843a',
        trip_delete:  '#d63638',
        // Catálogos
        catalog_create:  '#00a32a',
        catalog_update:  '#dc843a',
        catalog_delete:  '#d63638',
        catalog_reorder: '#0073aa',
        // Reportes
        report_export: '#9b51e0',
        // Auditoría
        audit_export_csv: '#9b51e0',
        audit_cleanup:    '#d63638',
    };

    // ── Helpers ──────────────────────────────────────────────────────

    function getBadge( operation ) {
        var label = opLabels[ operation ] || operation;
        var color = opColors[ operation ] || '#50575e';
        return '<span class="aura-aud-badge" style="background:' + color + ';">' + escHtml( label ) + '</span>';
    }

    function escHtml( str ) {
        return $( '<span>' ).text( String( str || '' ) ).html();
    }

    function formatDate( isoStr ) {
        if ( ! isoStr ) { return '—'; }
        var d = new Date( isoStr );
        var pad = function ( n ) { return n < 10 ? '0' + n : n; };
        return pad( d.getDate() ) + '/' + pad( d.getMonth() + 1 ) + '/' + d.getFullYear()
             + ' ' + pad( d.getHours() ) + ':' + pad( d.getMinutes() ) + ':' + pad( d.getSeconds() );
    }

    function setBusy( busy ) {
        $spinner.toggleClass( 'is-active', busy );
        $( '#aura-aud-search-btn' ).prop( 'disabled', busy );
    }

    function collectFilters() {
        return {
            operation: $( '#aura-aud-op' ).val(),
            user_id:   $( '#aura-aud-user' ).val(),
            date_from: $( '#aura-aud-from' ).val(),
            date_to:   $( '#aura-aud-to' ).val(),
            ip:        $( '#aura-aud-ip' ).val().trim(),
            search:    $( '#aura-aud-search' ).val().trim(),
        };
    }

    // ── DataTables ───────────────────────────────────────────────────

    function initTable() {
        _table = $( '#aura-aud-table' ).DataTable( {
            data:      [],
            columns: [
                {
                    data:              'created_at',
                    responsivePriority: 2,
                    render:            function ( val ) {
                        return '<span style="white-space:nowrap;font-size:12px;">' + formatDate( val ) + '</span>';
                    }
                },
                {
                    data:              'operation',
                    responsivePriority: 1,
                    render:            function ( val ) { return getBadge( val ); }
                },
                {
                    data:              'user_name',
                    responsivePriority: 3,
                    render:            function ( val ) {
                        return '<span style="font-size:12px;">' + escHtml( val || '(sistema)' ) + '</span>';
                    }
                },
                {
                    data:              'entity_type',
                    responsivePriority: 10000,
                    render:            function ( val ) {
                        return '<span style="font-size:12px;">' + escHtml( val || '—' ) + '</span>';
                    }
                },
                {
                    data:              'entity_id',
                    responsivePriority: 10000,
                    render:            function ( val ) {
                        return '<span style="font-size:12px;text-align:center;">' + escHtml( val || '—' ) + '</span>';
                    }
                },
                {
                    data:              'ip_address',
                    responsivePriority: 10000,
                    render:            function ( val ) {
                        return '<span style="font-size:11px;font-family:monospace;">' + escHtml( val || '—' ) + '</span>';
                    }
                },
                {
                    data:               null,
                    orderable:          false,
                    responsivePriority: 1,
                    render:             function ( data, type, row ) {
                        if ( row.details && row.details !== 'null' ) {
                            return '<button type="button" class="button button-small aura-aud-detail-btn"'
                                 + ' data-id="' + escHtml( String( row.id ) ) + '"'
                                 + ' title="Ver detalle">'
                                 + '<span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;"></span>'
                                 + '</button>';
                        }
                        return '';
                    }
                }
            ],
            order:      [ [ 0, 'desc' ] ],
            pageLength: 25,
            responsive: true,
            searching:  false,
            dom:        '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            language: {
                emptyTable:  'No se encontraron registros.',
                info:        '_TOTAL_ registros',
                infoEmpty:   '0 registros',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu:  'Mostrar _MENU_ por página',
                paginate:    { first: '«', last: '»', previous: '‹', next: '›' }
            },
            drawCallback: function () {
                var total = _table ? _table.data().count() : 0;
                $totalText.text( 'Total: ' + total + ' registros' );
                if ( total > 0 ) { $summary.show(); } else { $summary.hide(); }
            }
        } );
    }

    // ── Cargar auditoría ─────────────────────────────────────────────

    function loadAudit() {
        if ( ! _table ) { return; }
        setBusy( true );
        _rowsById = {};

        var params = $.extend( {}, lastFilters, { per_page: 500 } );

        $.ajax( {
            url:     restBase,
            method:  'GET',
            data:    params,
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
            },
            success: function ( res ) {
                var items = res.items || [];
                $.each( items, function ( i, row ) {
                    _rowsById[ row.id ] = row;
                } );
                _table.clear().rows.add( items ).draw();
            },
            error: function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Error al cargar el log.';
                _table.clear().draw();
                $app.find( '.aura-audit-filters' ).after(
                    '<div class="notice notice-error is-dismissible" style="margin:10px 0;"><p>' + escHtml( msg ) + '</p></div>'
                );
            },
            complete: function () {
                setBusy( false );
            }
        } );
    }

    // ── Modal de detalles ────────────────────────────────────────────

    function openDetailModal( row ) {
        if ( ! row ) { return; }
        $detailTable.empty();
        $detailJson.text( '' );

        var fixedRows = [
            [ 'ID',          row.id ],
            [ 'Fecha/Hora',  formatDate( row.created_at ) ],
            [ 'Operación',   opLabels[ row.operation ] || row.operation ],
            [ 'Tipo entidad', row.entity_type || '—' ],
            [ 'ID entidad',  row.entity_id || '—' ],
            [ 'Usuario',     ( row.user_name || '(sistema)' ) + ( row.user_email ? ' <' + row.user_email + '>' : '' ) ],
            [ 'IP',          row.ip_address || '—' ],
        ];

        var html = '';
        $.each( fixedRows, function ( i, pair ) {
            html += '<tr><th style="width:100px;vertical-align:top;">' + escHtml( pair[0] )
                  + '</th><td>' + escHtml( pair[1] || '—' ) + '</td></tr>';
        } );

        if ( row.details_parsed && Object.keys( row.details_parsed ).length > 0 ) {
            html += '<tr><td colspan="2" style="background:#f6f7f7;padding:4px 8px;font-size:11px;color:#72777c;font-weight:600;">DETALLES</td></tr>';
            $.each( row.details_parsed, function ( key, val ) {
                var displayVal = ( typeof val === 'object' ) ? JSON.stringify( val, null, 2 ) : val;
                html += '<tr><th style="width:100px;vertical-align:top;">' + escHtml( key )
                      + '</th><td style="font-family:monospace;font-size:11px;">' + escHtml( String( displayVal ) ) + '</td></tr>';
            } );
        }

        $detailTable.html( html );

        if ( row.details ) {
            try {
                var parsed = JSON.parse( row.details );
                $detailJson.text( JSON.stringify( parsed, null, 2 ) );
            } catch ( e ) {
                $detailJson.text( row.details );
            }
        }

        $detailModal.show();
        $( '#aura-aud-detail-close' ).focus();
    }

    // ── Exportar CSV ─────────────────────────────────────────────────

    function doExportCsv() {
        var params = $.extend( {}, lastFilters );
        var url = restBase + '/export-csv?' + $.param( params ) + '&_wpnonce=' + encodeURIComponent( nonce );
        window.location.href = url;
    }

    // ── Limpieza de logs ──────────────────────────────────────────────

    function doCleanup() {
        var days = parseInt( $( '#aura-aud-cleanup-days' ).val(), 10 );
        if ( ! days || days < 7 ) {
            alert( 'El valor mínimo es 7 días.' );
            return;
        }

        $cleanModal.hide();
        setBusy( true );

        $.ajax( {
            url:    restBase + '/cleanup',
            method: 'DELETE',
            data:   JSON.stringify( { days: days } ),
            contentType: 'application/json',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
            },
            success: function ( res ) {
                alert( res.message || 'Limpieza completada.' );
                loadAudit();
            },
            error: function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Error al limpiar los logs.';
                alert( msg );
            },
            complete: function () {
                setBusy( false );
            }
        } );
    }

    // ── Listeners ────────────────────────────────────────────────────

    $( '#aura-aud-search-btn' ).on( 'click', function () {
        lastFilters = collectFilters();
        loadAudit();
    } );

    $app.find( 'input' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) {
            lastFilters = collectFilters();
            loadAudit();
        }
    } );

    $( '#aura-aud-reset-btn' ).on( 'click', function () {
        $( '#aura-aud-op' ).val( '' );
        $( '#aura-aud-user' ).val( '0' );
        $( '#aura-aud-from, #aura-aud-to, #aura-aud-ip, #aura-aud-search' ).val( '' );
    } );

    $( '#aura-aud-csv-btn' ).on( 'click', function () {
        if ( $.isEmptyObject( lastFilters ) ) {
            lastFilters = collectFilters();
        }
        doExportCsv();
    } );

    $( '#aura-aud-cleanup-btn' ).on( 'click', function () {
        $cleanModal.show();
        $( '#aura-aud-cleanup-days' ).focus();
    } );

    $( '#aura-aud-cleanup-confirm' ).on( 'click', function () {
        doCleanup();
    } );

    $( '#aura-aud-cleanup-cancel, #aura-aud-cleanup-close' ).on( 'click', function () {
        $cleanModal.hide();
    } );

    // Delegado en la tabla DataTables (el tbody se regenera en cada draw)
    $( '#aura-aud-table tbody' ).on( 'click', '.aura-aud-detail-btn', function () {
        var id = $( this ).data( 'id' );
        openDetailModal( _rowsById[ id ] );
    } );

    $( '#aura-aud-detail-close' ).on( 'click', function () {
        $detailModal.hide();
    } );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            $detailModal.hide();
            $cleanModal.hide();
        }
    } );

    $( '.aura-modal-overlay' ).on( 'click', function ( e ) {
        if ( $( e.target ).hasClass( 'aura-modal-overlay' ) ) {
            $( this ).hide();
        }
    } );

    // ── Inicialización ───────────────────────────────────────────────

    initTable();

    // Ocultar la paginación manual heredada (DataTables la reemplaza)
    $( '#aura-aud-pagination' ).hide();

    // Carga inicial automática (últimos 30 días)
    ( function () {
        var now  = new Date();
        var from = new Date( now.getTime() - 29 * 24 * 60 * 60 * 1000 );
        var pad  = function ( n ) { return n < 10 ? '0' + n : n; };
        var fmt  = function ( d ) { return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ); };

        $( '#aura-aud-from' ).val( fmt( from ) );
        $( '#aura-aud-to' ).val( fmt( now ) );

        lastFilters = collectFilters();
        loadAudit();
    } )();

} )( jQuery );

        doCleanup();
    } );

    $( '#aura-aud-cleanup-cancel, #aura-aud-cleanup-close' ).on( 'click', function () {
        $cleanModal.hide();
    } );

    // Delegado en la tabla DataTables (el tbody se regenera en cada draw)
    $( '#aura-aud-table tbody' ).on( 'click', '.aura-aud-detail-btn', function () {
        var id = $( this ).data( 'id' );
        openDetailModal( _rowsById[ id ] );
    } );

    $( '#aura-aud-detail-close' ).on( 'click', function () {
        $detailModal.hide();
    } );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            $detailModal.hide();
            $cleanModal.hide();
        }
    } );

    $( '.aura-modal-overlay' ).on( 'click', function ( e ) {
        if ( $( e.target ).hasClass( 'aura-modal-overlay' ) ) {
            $( this ).hide();
        }
    } );

    // ── Inicialización ───────────────────────────────────────────────

    initTable();

    // Ocultar la paginación manual heredada (DataTables la reemplaza)
    $( '#aura-aud-pagination' ).hide();

    // Carga inicial automática (últimos 30 días)
    ( function () {
        var now  = new Date();
        var from = new Date( now.getTime() - 29 * 24 * 60 * 60 * 1000 );
        var pad  = function ( n ) { return n < 10 ? '0' + n : n; };
        var fmt  = function ( d ) { return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ); };

        $( '#aura-aud-from' ).val( fmt( from ) );
        $( '#aura-aud-to' ).val( fmt( now ) );

        lastFilters = collectFilters();
        loadAudit();
    } )();

} )( jQuery );
