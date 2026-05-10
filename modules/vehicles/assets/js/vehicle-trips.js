/**
 * Aura Business Suite — Salidas vehiculares (Trips)
 * Requiere: jQuery, DataTables 2.x, auraTripsListCfg
 */
/* global $, jQuery, auraTripsListCfg, DataTable */

(function( $ ) {
    'use strict';

    var CFG        = auraTripsListCfg || {};
    var API        = ( CFG.apiBase || '/wp-json/aura/v1/' );
    var NONCE      = CFG.nonce    || '';
    var TXT        = CFG.txt      || {};
    var _table       = null;
    var _tripsData   = [];
    var _vehiclesMap = {};
    var _tripCatalogs = { destination: [], purpose: [] };

    // ── Helpers ────────────────────────────────────────────────

    function apiUrl( path ) {
        return API + path;
    }

    function apiFetch( method, path, data ) {
        var opts = {
            url:      apiUrl( path ),
            method:   method,
            headers:  { 'X-WP-Nonce': NONCE },
            dataType: 'json'
        };
        if ( data ) {
            opts.contentType = 'application/json';
            opts.data        = JSON.stringify( data );
        }
        return $.ajax( opts );
    }

    function showNotice( msg, type ) {
        type = type || 'success';
        var cls  = 'success' === type ? 'notice-success' : 'notice-error';
        var html = '<div class="notice ' + cls + ' is-dismissible" style="margin:10px 0;padding:8px 12px;">' +
                   '<p>' + $( '<span>' ).text( msg ).html() + '</p>' +
                   '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Cerrar</span></button>' +
                   '</div>';
        $( '.aura-vehicles-trips h1' ).after( html );
        $( document ).on( 'click', '.notice-dismiss', function() { $( this ).closest( '.notice' ).remove(); } );
    }

    function closeAllModals() {
        $( '.aura-veh-modal' ).css( 'display', 'none' );
        $( 'body' ).css( 'overflow', '' );
    }

    function openModal( $modal ) {
        closeAllModals();
        $modal.css( 'display', 'flex' );
        $( 'body' ).css( 'overflow', 'hidden' );
        $modal.find( '.aura-veh-modal-close' ).first().focus();
    }

    function typeLabel( type ) {
        var map = TXT.type || {};
        return map[ type ] || type;
    }

    function statusBadge( status ) {
        var map = TXT.status || {};
        var label = map[ status ] || status;
        var colors = {
            active:    { bg: '#e9f5fb', border: '#2271b1', text: '#2271b1' },
            returned:  { bg: '#edfaef', border: '#00a32a', text: '#00a32a' },
            cancelled: { bg: '#f6f7f7', border: '#787c82', text: '#787c82' }
        };
        var c = colors[ status ] || colors.cancelled;
        return '<span style="border-radius:3px;border:1px solid ' + c.border + ';background:' + c.bg +
               ';color:' + c.text + ';padding:2px 8px;font-size:12px;white-space:nowrap;">' + label + '</span>';
    }

    function fmtDate( dt ) {
        if ( ! dt ) { return '—'; }
        var d = new Date( dt );
        if ( isNaN( d ) ) { return dt; }
        return d.toLocaleDateString( 'es', { day: '2-digit', month: '2-digit', year: 'numeric' } ) +
               ' ' + d.toLocaleTimeString( 'es', { hour: '2-digit', minute: '2-digit' } );
    }

    function getNow() {
        var now = new Date();
        var pad = function( n ) { return String( n ).padStart( 2, '0' ); };
        return now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ) +
               'T' + pad( now.getHours() ) + ':' + pad( now.getMinutes() );
    }

    // ── DataTable ─────────────────────────────────────────────

    function initTable() {
        _table = $( '#aura-trips-table' ).DataTable({
            data:       [],
            columns: [
                { data: 'id',    title: 'ID', width: '60px', responsivePriority: 10000 },
                { data: null,    title: 'Vehículo', responsivePriority: 1,
                  render: function( row ) {
                      var v = row;
                      var plate = v.plate || '';
                      var brand = v.brand || '';
                      var model = v.model || '';
                      return '<strong>' + $( '<span>' ).text( plate ).html() + '</strong>' +
                             ( brand ? '<br><small>' + $( '<span>' ).text( brand + ' ' + model ).html() + '</small>' : '' );
                  }
                },
                { data: 'trip_type', title: 'Tipo', responsivePriority: 10000,
                  render: function( val ) { return typeLabel( val ); }
                },
                { data: 'status', title: 'Estado', responsivePriority: 2,
                  render: function( val ) { return statusBadge( val ); }
                },
                { data: null, title: 'Responsable / Cliente', responsivePriority: 10000,
                  render: function( row ) {
                      if ( row.trip_type === 'rental' )    { return $( '<span>' ).text( row.client_name || '—' ).html(); }
                      if ( row.trip_type === 'maintenance' ) {
                          return $( '<span>' ).text( row.assigned_to_name || row.created_by_name || '—' ).html();
                      }
                      return $( '<span>' ).text( row.responsible_name || '—' ).html();
                  }
                },
                { data: 'departure_datetime', title: 'Salida', responsivePriority: 3,
                  render: function( val ) { return fmtDate( val ); }
                },
                { data: 'return_datetime', title: 'Retorno', responsivePriority: 10000,
                  render: function( val ) { return fmtDate( val ); }
                },
                { data: 'km_traveled', title: 'KM', responsivePriority: 10000,
                  render: function( val ) { return val ? Number( val ).toLocaleString() : '—'; }
                },
                { data: null, title: 'Acciones', orderable: false, responsivePriority: 1,
                  render: function( row ) {
                      var html = '<div class="aura-trips-actions">';
                      html += '<button class="button button-small aura-trip-detail" data-id="' + row.id + '" title="Ver detalle">' +
                              '<span class="dashicons dashicons-visibility" style="margin-top:3px;font-size:14px;"></span></button>';
                      var canAct = CFG.canEditAll || ( CFG.canEditOwn && row.created_by == CFG.currentUid );
                      if ( row.status === 'active' ) {
                          if ( canAct ) {
                              html += ' <button class="button button-primary button-small aura-trip-checkin" data-id="' + row.id + '" title="Registrar retorno">' +
                                      '<span class="dashicons dashicons-yes-alt" style="margin-top:3px;font-size:14px;"></span></button>';
                              html += ' <button class="button button-small aura-trip-cancel" data-id="' + row.id + '" title="Cancelar">' +
                                      '<span class="dashicons dashicons-dismiss" style="margin-top:3px;font-size:14px;"></span></button>';
                          }
                      }
                      if ( row.status !== 'active' && CFG.canDelete ) {
                          html += ' <button class="button button-link-delete button-small aura-trip-delete" data-id="' + row.id + '" title="Eliminar">' +
                                  '<span class="dashicons dashicons-trash" style="margin-top:3px;font-size:14px;"></span></button>';
                      }
                      html += '</div>';
                      return html;
                  }
                }
            ],
            paging:      true,
            pageLength:  25,
            ordering:    true,
            order:       [ [ 0, 'desc' ] ],
            searching:   false,
            responsive:  true,
            dom:         '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            language: {
                emptyTable:   TXT.no_results || 'Sin resultados.',
                info:         '_TOTAL_ salidas',
                infoEmpty:    '0 salidas',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu:   'Mostrar _MENU_ por página',
                paginate:     { first: '«', last: '»', previous: '‹', next: '›' }
            }
        });
    }

    // ── Carga de datos ────────────────────────────────────────

    function buildFilters() {
        var params = {};
        var type   = $( '#aura-trips-filter-type' ).val();
        var status = $( '#aura-trips-filter-status' ).val();
        var area   = $( '#aura-trips-filter-area' ).val();
        var from   = $( '#aura-trips-filter-from' ).val();
        var to     = $( '#aura-trips-filter-to' ).val();
        if ( type )   { params.type     = type; }
        if ( status ) { params.status   = status; }
        if ( area && area !== '0' ) { params.area_id = area; }
        if ( from )   { params.date_from = from; }
        if ( to )     { params.date_to   = to; }
        params.per_page = 500;
        return params;
    }

    function loadTrips() {
        if ( ! _table ) { return; }
        var params = buildFilters();
        var qs = Object.keys( params ).map( function( k ) {
            return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
        }).join( '&' );

        apiFetch( 'GET', 'vehicles/trips' + ( qs ? '?' + qs : '' ) )
            .done( function( resp ) {
                var rows = ( resp && resp.items ) ? resp.items : [];
                _tripsData = rows;
                _table.clear();
                _table.rows.add( rows );
                _table.draw();
            })
            .fail( function() {
                showNotice( TXT.error || 'Error al cargar salidas.', 'error' );
            });
    }

    // ── Vehículos disponibles ─────────────────────────────────

    function loadAvailableVehicles( tripType, selectedId ) {
        var $sel = $( '#trip-vehicle_id' );
        $sel.empty().append( '<option value="">' + ( TXT.loading || 'Cargando…' ) + '</option>' );
        $( '#trip-vehicle-info' ).hide();
        _vehiclesMap = {};
        var qs = tripType ? '?type=' + tripType : '';
        apiFetch( 'GET', 'vehicles/available-for-trip' + qs )
            .done( function( resp ) {
                $sel.empty().append( '<option value="">— Seleccionar —</option>' );
                var vehicles = resp.items || [];
                $.each( vehicles, function( i, v ) {
                    _vehiclesMap[ String( v.id ) ] = v;
                    var text = v.plate + ' — ' + ( v.brand || '' ) + ' ' + ( v.model || '' );
                    var opt  = $( '<option>' ).val( v.id ).text( text );
                    if ( selectedId && String( v.id ) === String( selectedId ) ) { opt.prop( 'selected', true ); }
                    $sel.append( opt );
                });
                if ( selectedId ) { showVehicleInfo( selectedId ); }
            })
            .fail( function() {
                $sel.empty().append( '<option value="">Error al cargar</option>' );
            });
    }

    function showVehicleInfo( vehicleId ) {
        var $panel = $( '#trip-vehicle-info' );
        var v      = _vehiclesMap[ String( vehicleId ) ];
        if ( ! vehicleId || ! v ) { $panel.hide(); return; }

        var mileage = ( v.mileage !== null && v.mileage !== undefined && v.mileage !== '' )
            ? Number( v.mileage ).toLocaleString() + ' km' : '—';
        var rate = ( v.rate_per_km && parseFloat( v.rate_per_km ) > 0 )
            ? parseFloat( v.rate_per_km ).toFixed(2) + '/km' : null;

        var html = '<span class="dashicons dashicons-car"></span> ' +
            '<strong>' + $( '<span>' ).text( v.plate ).html() + '</strong>' +
            ' — ' + $( '<span>' ).text( ( v.brand || '' ) + ' ' + ( v.model || '' ) ).html() +
            ' &nbsp;·&nbsp; <span class="dashicons dashicons-dashboard"></span>' +
            ' Odóm.: <strong>' + mileage + '</strong>';
        if ( rate ) {
            html += ' &nbsp;·&nbsp; Tarifa: <strong>' + rate + '</strong>';
        }
        $panel.html( html ).show();

        var currentOdo = parseInt( $( '#trip-departure_odometer' ).val(), 10 );
        if ( v.mileage && ! currentOdo ) {
            $( '#trip-departure_odometer' ).val( v.mileage );
        }
        if ( $( '#trip-trip_type' ).val() === 'rental' && v.rate_per_km ) {
            var currentRate = parseFloat( $( '#trip-rate_per_km' ).val() );
            if ( ! currentRate ) {
                $( '#trip-rate_per_km' ).val( parseFloat( v.rate_per_km ).toFixed(2) );
            }
        }
    }

    // ── Usuarios dropdown ─────────────────────────────────────

    function loadUsersDropdown() {
        var $sel = $( '#trip-assigned_to' );
        if ( ! $sel.length ) { return; }
        apiFetch( 'GET', 'vehicles/users-dropdown' )
            .done( function( resp ) {
                $sel.find( 'option:not(:first)' ).remove();
                var users = resp.items || [];
                $.each( users, function( i, u ) {
                    $sel.append( $( '<option>' ).val( u.id ).text( u.name ) );
                });
            });
    }

    // ── Catálogos de destino / propósito ─────────────────────

    function fillCatalogSelect( selector, items, placeholder, selectedValue ) {
        var $sel = $( selector );
        if ( ! $sel.length ) { return; }

        $sel.empty().append( $( '<option>' ).val( '' ).text( placeholder ) );

        $.each( items || [], function( i, item ) {
            if ( ! item || ! item.name ) { return; }
            $sel.append( $( '<option>' ).val( item.name ).text( item.name ) );
        } );

        if ( selectedValue ) {
            $sel.val( selectedValue );
        }
    }

    function renderTripCatalogs( selectedValues ) {
        selectedValues = selectedValues || {};
        fillCatalogSelect(
            '#trip-destination',
            _tripCatalogs.destination,
            '— Seleccionar destino —',
            selectedValues.destination || ''
        );
        fillCatalogSelect(
            '#trip-purpose',
            _tripCatalogs.purpose,
            '— Seleccionar propósito —',
            selectedValues.purpose || ''
        );
    }

    function loadTripCatalogs( selectedValues ) {
        var areaId = parseInt( $( '#trip-area_id' ).val(), 10 ) || 0;
        var path = 'vehicles/catalogs';
        var query = [ 'include_inactive=0', 'include_global=1' ];

        if ( areaId > 0 ) {
            query.push( 'area_id=' + areaId );
        }

        apiFetch( 'GET', path + '?' + query.join( '&' ) )
            .done( function( resp ) {
                var grouped = resp && resp.grouped ? resp.grouped : {};
                _tripCatalogs.destination = grouped.destination || [];
                _tripCatalogs.purpose     = grouped.purpose || [];
                renderTripCatalogs( selectedValues );
            } )
            .fail( function() {
                _tripCatalogs.destination = [];
                _tripCatalogs.purpose     = [];
                renderTripCatalogs( selectedValues );
            } );
    }

    // ── Modal: Nueva salida ───────────────────────────────────

    function resetFormModal() {
        $( '#aura-trips-form-id' ).val( '' );
        $( '#trip-vehicle_id' ).empty().append( '<option value="">— Seleccionar —</option>' );
        $( '#trip-vehicle-info' ).hide();
        $( '#trip-trip_type' ).val( '' );
        $( '#trip-area_id' ).val( '0' );
        $( '#trip-departure_datetime' ).val( getNow() );
        $( '#trip-departure_odometer' ).val( '0' );
        $( '#trip-departure_fuel' ).val( '100' );
        $( '#trip-client_name, #trip-client_phone, #trip-client_email, #trip-client_document' ).val( '' );
        $( '#trip-rate_per_km' ).val( '0.00' );
        $( '#trip-responsible_name' ).val( '' );
        $( '#trip-assigned_to' ).val( '0' );
        $( '#trip-trip_description' ).val( '' );
        $( '#trip-maint_subtype' ).val( 'preventive' );
        $( '#trip-maint_priority' ).val( 'medium' );
        $( '#trip-maint_description, #trip-maint_provider, #trip-maint_contact' ).val( '' );
        $( '#trip-maint_estimated_cost' ).val( '0.00' );
        $( '.aura-trips-type-section' ).hide();
        $( '.aura-trips-type-card' ).removeClass( 'is-active' );
        $( '.aura-veh-form-col.has-error' ).removeClass( 'has-error' );
        $( '#aura-trips-form-error' ).hide().text( '' );
        $( '#aura-trips-form-title' ).text( 'Nueva Salida' );
        $( '#aura-trips-form-submit' ).text( 'Registrar Salida' ).prop( 'disabled', false );
        renderTripCatalogs();
    }

    function showTypeSection( type ) {
        $( '.aura-trips-type-section' ).hide();
        if ( type === 'rental' ) {
            $( '#trip-section-rental' ).show();
        } else if ( type === 'errand' ) {
            $( '#trip-section-errand-title' ).text( 'Datos del encargo' );
            $( '#trip-section-errand-icon' ).attr( 'class', 'dashicons dashicons-clipboard' );
            $( '#trip-section-errand' ).show();
        } else if ( type === 'other' ) {
            $( '#trip-section-errand-title' ).text( 'Datos de la salida' );
            $( '#trip-section-errand-icon' ).attr( 'class', 'dashicons dashicons-marker' );
            $( '#trip-section-errand' ).show();
        } else if ( type === 'maintenance' ) {
            $( '#trip-section-maintenance' ).show();
        }
    }

    function openFormModal() {
        resetFormModal();
        loadAvailableVehicles( '' );
        loadUsersDropdown();
        loadTripCatalogs();
        openModal( $( '#aura-trips-modal-form' ) );
    }

    $( document ).on( 'change', '#trip-area_id', function() {
        loadTripCatalogs( {
            destination: $( '#trip-destination' ).val(),
            purpose: $( '#trip-purpose' ).val()
        } );
    } );

    $( document ).on( 'click', '.aura-trips-type-card', function() {
        var type = $( this ).data( 'type' );
        $( '.aura-trips-type-card' ).removeClass( 'is-active' );
        $( this ).addClass( 'is-active' );
        $( '#trip-trip_type' ).val( type ).trigger( 'change' );
    } );

    $( document ).on( 'change', '#trip-trip_type', function() {
        var type  = $( this ).val();
        var btnMap = {
            rental:      'Registrar Renta',
            errand:      'Registrar Encargo',
            maintenance: 'Registrar Mantenimiento',
            other:       'Registrar Salida'
        };
        showTypeSection( type );
        $( '#aura-trips-form-submit' ).text( btnMap[ type ] || 'Registrar Salida' );
        if ( type ) { loadAvailableVehicles( type ); }
    } );

    $( document ).on( 'change', '#trip-vehicle_id', function() {
        showVehicleInfo( $( this ).val() );
        $( this ).closest( '.aura-veh-form-col' ).removeClass( 'has-error' );
    } );

    $( document ).on( 'change input', '#aura-trips-modal-form input, #aura-trips-modal-form select, #aura-trips-modal-form textarea', function() {
        $( this ).closest( '.aura-veh-form-col' ).removeClass( 'has-error' );
        if ( ! $( '#aura-trips-modal-form .aura-veh-form-col.has-error' ).length ) {
            $( '#aura-trips-form-error' ).hide();
        }
    } );

    function collectFormData() {
        var type = $( '#trip-trip_type' ).val();
        var data = {
            vehicle_id:          parseInt( $( '#trip-vehicle_id' ).val(), 10 )     || 0,
            trip_type:           type,
            area_id:             parseInt( $( '#trip-area_id' ).val(), 10 )        || 0,
            departure_datetime:  $( '#trip-departure_datetime' ).val(),
            departure_odometer:  parseInt( $( '#trip-departure_odometer' ).val(), 10 ) || 0,
            departure_fuel:      parseInt( $( '#trip-departure_fuel' ).val(), 10 ) || 0
        };
        if ( type === 'rental' ) {
            data.client_name       = $( '#trip-client_name' ).val().trim();
            data.client_phone      = $( '#trip-client_phone' ).val().trim();
            data.client_email      = $( '#trip-client_email' ).val().trim();
            data.client_document   = $( '#trip-client_document' ).val().trim();
            data.rate_per_km       = parseFloat( $( '#trip-rate_per_km' ).val() ) || 0;
        } else if ( type === 'errand' || type === 'other' ) {
            data.responsible_name  = $( '#trip-responsible_name' ).val().trim();
            data.assigned_to       = parseInt( $( '#trip-assigned_to' ).val(), 10 ) || 0;
            data.destination       = $( '#trip-destination' ).val().trim();
            data.purpose           = $( '#trip-purpose' ).val().trim();
            data.trip_description  = $( '#trip-trip_description' ).val().trim();
        } else if ( type === 'maintenance' ) {
            data.maint_subtype          = $( '#trip-maint_subtype' ).val();
            data.maint_priority         = $( '#trip-maint_priority' ).val();
            data.maint_description      = $( '#trip-maint_description' ).val().trim();
            data.maint_provider         = $( '#trip-maint_provider' ).val().trim();
            data.maint_contact          = $( '#trip-maint_contact' ).val().trim();
            data.maint_estimated_cost   = parseFloat( $( '#trip-maint_estimated_cost' ).val() ) || 0;
        }
        return data;
    }

    $( document ).on( 'click', '#aura-trips-form-submit', function() {
        var $btn    = $( this );
        var $err    = $( '#aura-trips-form-error' );
        var data    = collectFormData();
        $err.hide().text( '' );

        if ( ! data.vehicle_id ) {
            $( '#trip-vehicle_id' ).closest( '.aura-veh-form-col' ).addClass( 'has-error' );
            $err.text( 'Selecciona un vehículo.' ).show(); return;
        }
        if ( ! data.trip_type ) {
            $err.text( 'Selecciona el tipo de salida.' ).show(); return;
        }
        if ( ! data.departure_datetime ) {
            $( '#trip-departure_datetime' ).closest( '.aura-veh-form-col' ).addClass( 'has-error' );
            $err.text( 'Indica la fecha y hora de salida.' ).show(); return;
        }

        $btn.prop( 'disabled', true ).text( 'Guardando…' );

        apiFetch( 'POST', 'vehicles/trips', data )
            .done( function() {
                closeAllModals();
                showNotice( TXT.saved || 'Salida registrada.' );
                loadTrips();
            })
            .fail( function( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ( TXT.error || 'Error.' );
                $err.text( msg ).show();
                $btn.prop( 'disabled', false ).text( 'Registrar Salida' );
            });
    });

    // ── Modal: Check-in (retorno) ─────────────────────────────

    function buildCheckinSummary( trip ) {
        var type = trip.trip_type;
        var lines = [];
        lines.push( '<strong>Vehículo:</strong> ' + $( '<span>' ).text( (trip.plate || '') + ' — ' + (trip.brand||'') + ' ' + (trip.model||'') ).html() );
        lines.push( '<strong>Tipo:</strong> ' + typeLabel( type ) );
        if ( type === 'rental' )   { lines.push( '<strong>Cliente:</strong> ' + $( '<span>' ).text( trip.client_name || '—' ).html() ); }
        if ( type === 'errand' || type === 'other' ) { lines.push( '<strong>Responsable:</strong> ' + $( '<span>' ).text( trip.responsible_name || '—' ).html() ); }
        if ( type === 'maintenance' ) { lines.push( '<strong>Trabajo:</strong> ' + $( '<span>' ).text( trip.maint_description || '—' ).html() ); }
        lines.push( '<strong>Salida:</strong> ' + fmtDate( trip.departure_datetime ) + ' | <strong>Odómetro:</strong> ' + Number( trip.departure_odometer ).toLocaleString() + ' km' );
        var $div = $( '<div class="aura-trips-checkin-summary-inner" style="background:#f0f7ff;border:1px solid #c3c4c7;border-radius:4px;padding:10px 14px;margin-bottom:12px;font-size:13px;"></div>' );
        $div.html( lines.join( ' &nbsp;|&nbsp; ' ) );
        return $div;
    }

    function openCheckinModal( tripId ) {
        apiFetch( 'GET', 'vehicles/trips/' + tripId )
            .done( function( resp ) {
                var trip = resp.trip || resp;
                $( '#aura-checkin-trip-id' ).val( trip.id );
                $( '#aura-checkin-trip-type' ).val( trip.trip_type );
                $( '#aura-checkin-departure-odometer' ).val( trip.departure_odometer || 0 );
                $( '#aura-checkin-rate-per-km' ).val( trip.rate_per_km || 0 );
                $( '#aura-checkin-summary' ).empty().append( buildCheckinSummary( trip ) );
                $( '#checkin-return_datetime' ).val( '' );
                $( '#checkin-return_odometer' ).val( trip.departure_odometer || 0 );
                $( '#checkin-return_fuel' ).val( '' );
                $( '#aura-checkin-preview' ).hide();
                $( '#checkin-additional_charges, #checkin-discounts' ).val( '0.00' );
                $( '#checkin-maint_actual_cost' ).val( '0.00' );
                $( '#checkin-next_service_interval_km' ).val( '0' );
                $( '#checkin-maint_completion_notes' ).val( '' );
                $( '#checkin-section-rental' ).toggle( trip.trip_type === 'rental' );
                $( '#checkin-section-maintenance' ).toggle( trip.trip_type === 'maintenance' );
                $( '#checkin-section-expenses' ).toggle( trip.trip_type !== 'rental' );
                $( '#checkin-expenses-lines' ).empty();
                updateExpensesTotal();
                $( '#aura-checkin-error' ).hide().text( '' );
                $( '#aura-checkin-submit' ).prop( 'disabled', false ).text( 'Registrar Retorno' );
                if ( trip.trip_type === 'rental' ) { updateCheckinPreview(); }
                openModal( $( '#aura-trips-modal-checkin' ) );
            })
            .fail( function() {
                showNotice( TXT.error || 'Error al cargar salida.', 'error' );
            });
    }

    function updateCheckinPreview() {
        var depOdometer = parseInt( $( '#aura-checkin-departure-odometer' ).val(), 10 ) || 0;
        var retOdometer = parseInt( $( '#checkin-return_odometer' ).val(), 10 ) || 0;
        var rate        = parseFloat( $( '#aura-checkin-rate-per-km' ).val() ) || 0;
        var type        = $( '#aura-checkin-trip-type' ).val();
        if ( type !== 'rental' ) { $( '#aura-checkin-preview' ).hide(); return; }
        var km    = Math.max( 0, retOdometer - depOdometer );
        var total = km * rate;
        var add   = parseFloat( $( '#checkin-additional_charges' ).val() ) || 0;
        var disc  = parseFloat( $( '#checkin-discounts' ).val() )          || 0;
        var final = total + add - disc;
        $( '#preview-km' ).text( km.toLocaleString() );
        $( '#preview-total' ).text( final.toFixed(2) );
        $( '#aura-checkin-preview' ).show();
    }

    $( document ).on( 'input', '#checkin-return_odometer, #checkin-additional_charges, #checkin-discounts', updateCheckinPreview );

    // Expenses detail for errand/maintenance/other
    var _expenseLineCount = 0;

    function addExpenseLine( expObj ) {
        _expenseLineCount++;
        var i   = _expenseLineCount;
        var obj = expObj || {};
        var $row = $( '<div class="aura-expense-line" style="display:flex;gap:8px;margin-bottom:6px;align-items:center;">' +
            '<input type="text"   class="regular-text exp-desc"   placeholder="Descripción"    value="' + $( '<span>' ).text( obj.desc  || '' ).html() + '">' +
            '<input type="text"   class="small-text  exp-type"    placeholder="Tipo"            value="' + $( '<span>' ).text( obj.type  || '' ).html() + '">' +
            '<input type="number" class="small-text  exp-amount"  placeholder="Monto" min="0" step="0.01" value="' + ( obj.amount || '0' ) + '">' +
            '<button type="button" class="button button-small aura-rm-expense" data-line="' + i + '">✕</button>' +
        '</div>' );
        $( '#checkin-expenses-lines' ).append( $row );
        $row.find( '.exp-amount' ).on( 'input', updateExpensesTotal );
    }

    function updateExpensesTotal() {
        var sum = 0;
        $( '#checkin-expenses-lines .exp-amount' ).each( function() {
            sum += parseFloat( $( this ).val() ) || 0;
        });
        $( '#checkin-total-expenses' ).text( sum.toFixed(2) );
    }

    $( document ).on( 'click', '#checkin-add-expense', addExpenseLine.bind( null, null ) );

    $( document ).on( 'click', '.aura-rm-expense', function() {
        $( this ).closest( '.aura-expense-line' ).remove();
        updateExpensesTotal();
    });

    function collectExpenses() {
        var lines = [];
        $( '#checkin-expenses-lines .aura-expense-line' ).each( function() {
            var desc   = $( this ).find( '.exp-desc' ).val().trim();
            var type   = $( this ).find( '.exp-type' ).val().trim();
            var amount = parseFloat( $( this ).find( '.exp-amount' ).val() ) || 0;
            if ( desc || amount ) {
                lines.push({ desc: desc, type: type, amount: amount });
            }
        });
        return lines;
    }

    $( document ).on( 'click', '#aura-checkin-submit', function() {
        var $btn   = $( this );
        var $err   = $( '#aura-checkin-error' );
        var tripId = $( '#aura-checkin-trip-id' ).val();
        var type   = $( '#aura-checkin-trip-type' ).val();
        $err.hide().text( '' );

        var data = {
            return_datetime:  $( '#checkin-return_datetime' ).val(),
            return_odometer:  parseInt( $( '#checkin-return_odometer' ).val(), 10 ) || 0,
            return_fuel:      parseInt( $( '#checkin-return_fuel' ).val(), 10 ) || 0
        };
        if ( ! data.return_datetime ) {
            $err.text( 'Indica la fecha y hora de retorno.' ).show(); return;
        }
        if ( type === 'rental' ) {
            data.additional_charges = parseFloat( $( '#checkin-additional_charges' ).val() ) || 0;
            data.discounts          = parseFloat( $( '#checkin-discounts' ).val() )          || 0;
        } else if ( type === 'maintenance' ) {
            data.maint_actual_cost        = parseFloat( $( '#checkin-maint_actual_cost' ).val() ) || 0;
            data.next_service_interval_km = parseInt( $( '#checkin-next_service_interval_km' ).val(), 10 ) || 0;
            data.maint_completion_notes   = $( '#checkin-maint_completion_notes' ).val().trim();
        }
        if ( type !== 'rental' ) {
            var expenses = collectExpenses();
            if ( expenses.length ) { data.expenses_detail = expenses; }
        }

        $btn.prop( 'disabled', true ).text( 'Guardando…' );

        apiFetch( 'POST', 'vehicles/trips/' + tripId + '/checkin', data )
            .done( function() {
                closeAllModals();
                showNotice( TXT.checked_in || 'Retorno registrado.' );
                loadTrips();
            })
            .fail( function( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ( TXT.error || 'Error.' );
                $err.text( msg ).show();
                $btn.prop( 'disabled', false ).text( 'Registrar Retorno' );
            });
    });

    // ── Modal: Cancelar ───────────────────────────────────────

    function openCancelModal( tripId ) {
        $( '#aura-cancel-trip-id' ).val( tripId );
        $( '#aura-cancel-reason' ).val( '' );
        $( '#aura-cancel-error' ).hide().text( '' );
        $( '#aura-cancel-submit' ).prop( 'disabled', false );
        openModal( $( '#aura-trips-modal-cancel' ) );
    }

    $( document ).on( 'click', '#aura-cancel-submit', function() {
        var $btn   = $( this );
        var $err   = $( '#aura-cancel-error' );
        var id     = $( '#aura-cancel-trip-id' ).val();
        var reason = $( '#aura-cancel-reason' ).val().trim();
        $err.hide().text( '' );
        if ( ! reason ) {
            $err.text( 'Indica el motivo de cancelación.' ).show(); return;
        }
        $btn.prop( 'disabled', true );
        apiFetch( 'POST', 'vehicles/trips/' + id + '/cancel', { reason: reason } )
            .done( function() {
                closeAllModals();
                showNotice( TXT.cancelled || 'Salida cancelada.' );
                loadTrips();
            })
            .fail( function( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ( TXT.error || 'Error.' );
                $err.text( msg ).show();
                $btn.prop( 'disabled', false );
            });
    });

    // ── Modal: Eliminar ───────────────────────────────────────

    function openDeleteModal( tripId ) {
        $( '#aura-delete-trip-id' ).val( tripId );
        openModal( $( '#aura-trips-modal-delete' ) );
    }

    $( document ).on( 'click', '#aura-delete-trip-confirm', function() {
        var $btn = $( this );
        var id   = $( '#aura-delete-trip-id' ).val();
        $btn.prop( 'disabled', true );
        apiFetch( 'DELETE', 'vehicles/trips/' + id )
            .done( function() {
                closeAllModals();
                showNotice( TXT.deleted || 'Salida eliminada.' );
                loadTrips();
            })
            .fail( function( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ( TXT.error || 'Error.' );
                showNotice( msg, 'error' );
                $btn.prop( 'disabled', false );
                closeAllModals();
            });
    });

    // ── Modal: Detalle (solo lectura) ────────────────────────

    function openDetailModal( tripId ) {
        apiFetch( 'GET', 'vehicles/trips/' + tripId )
            .done( function( resp ) {
                var t = resp.trip || resp;
                var html = '<table class="widefat striped" style="max-width:600px;">';
                var rows = [
                    [ 'ID',       t.id ],
                    [ 'Vehículo', ( t.plate || '' ) + ' — ' + ( t.brand || '' ) + ' ' + ( t.model || '' ) ],
                    [ 'Tipo',     typeLabel( t.trip_type ) ],
                    [ 'Estado',   statusBadge( t.status ) ],
                    [ 'Salida',   fmtDate( t.departure_datetime ) ],
                    [ 'Odóm. salida', Number( t.departure_odometer ).toLocaleString() + ' km' ],
                    [ 'Retorno',  fmtDate( t.return_datetime ) ],
                    [ 'Odóm. retorno', t.return_odometer ? Number( t.return_odometer ).toLocaleString() + ' km' : '—' ],
                    [ 'KM recorridos', t.km_traveled ? Number( t.km_traveled ).toLocaleString() : '—' ],
                ];
                if ( t.trip_type === 'rental' ) {
                    rows.push( [ 'Cliente',     t.client_name    || '—' ] );
                    rows.push( [ 'Tarifa/km',   t.rate_per_km    || '—' ] );
                    rows.push( [ 'Total',        t.total_amount  || '—' ] );
                } else if ( t.trip_type === 'maintenance' ) {
                    rows.push( [ 'Descripción',  t.maint_description  || '—' ] );
                    rows.push( [ 'Subtipo',      t.maint_subtype      || '—' ] );
                    rows.push( [ 'Prioridad',    t.maint_priority     || '—' ] );
                    rows.push( [ 'Costo real',   t.maint_actual_cost  || '—' ] );
                } else {
                    rows.push( [ 'Responsable',  t.responsible_name   || '—' ] );
                    rows.push( [ 'Destino',      t.destination        || '—' ] );
                    rows.push( [ 'Propósito',    t.purpose            || '—' ] );
                }
                rows.push( [ 'Registrado por', t.created_by_name || '—' ] );
                if ( t.cancellation_reason ) { rows.push( [ 'Motivo cancelación', t.cancellation_reason ] ); }

                rows.forEach( function( pair ) {
                    html += '<tr><th style="width:180px;">' + pair[0] + '</th>' +
                            '<td>' + ( pair[1] !== null && pair[1] !== undefined ? pair[1] : '—' ) + '</td></tr>';
                });
                html += '</table>';
                $( '#aura-trips-detail-content' ).html( html );
                openModal( $( '#aura-trips-modal-detail' ) );
            })
            .fail( function() {
                showNotice( TXT.error || 'Error.', 'error' );
            });
    }

    // ── Delegación de eventos del DOM ─────────────────────────

    $( document ).on( 'click', '#aura-trips-btn-create', function() {
        if ( CFG.canCreate ) { openFormModal(); }
    });

    $( document ).on( 'click', '.aura-trip-checkin', function() {
        openCheckinModal( $( this ).data( 'id' ) );
    });

    $( document ).on( 'click', '.aura-trip-cancel', function() {
        openCancelModal( $( this ).data( 'id' ) );
    });

    $( document ).on( 'click', '.aura-trip-delete', function() {
        openDeleteModal( $( this ).data( 'id' ) );
    });

    $( document ).on( 'click', '.aura-trip-detail', function() {
        openDetailModal( $( this ).data( 'id' ) );
    });

    $( document ).on( 'click', '#aura-trips-filter-apply', loadTrips );

    $( document ).on( 'input', '#aura-trips-search', function() {
        if ( _table ) { _table.search( $( this ).val() ).draw(); }
    } );
    $( document ).on( 'keydown', '#aura-trips-search', function( e ) {
        if ( e.key === 'Enter' ) { e.preventDefault(); }
    } );

    $( document ).on( 'click', '#aura-trips-filter-clear', function() {
        $( '#aura-trips-filter-type, #aura-trips-filter-status, #aura-trips-filter-area' ).val( '' );
        $( '#aura-trips-filter-from, #aura-trips-filter-to' ).val( '' );
        $( '#aura-trips-search' ).val( '' );
        if ( _table ) { _table.search( '' ); }
        loadTrips();
    });

    // Cerrar modales al hacer clic en overlay o botón .aura-veh-modal-close
    $( document ).on( 'click', '.aura-veh-modal-overlay, .aura-veh-modal-close', closeAllModals );

    // Tecla Escape
    $( document ).on( 'keydown', function( e ) {
        if ( e.key === 'Escape' ) { closeAllModals(); }
    });

    // ── Init ──────────────────────────────────────────────────

    $( document ).ready( function() {
        if ( ! $( '#aura-trips-table' ).length ) { return; }
        try {
            initTable();
        } catch ( e ) {
            console.error( 'DataTables init error (trips):', e );
        }
        loadTrips();
    });

})( jQuery );
