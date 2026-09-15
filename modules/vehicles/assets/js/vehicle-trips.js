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
        $( '.aura-veh-modal' ).each( function() {
            if ( $( this ).css( 'display' ) !== 'none' ) {
                $( this ).addClass( 'is-closing' );
                var $m = $( this );
                setTimeout( function() {
                    $m.css( 'display', 'none' ).removeClass( 'is-closing' );
                }, 250 );
            }
        });
        $( 'body' ).css( 'overflow', '' );
    }

    function openModal( $modal ) {
        closeAllModals();
        $modal.css( 'display', 'flex' ).removeClass( 'is-closing' );
        $( 'body' ).css( 'overflow', 'hidden' );
        setTimeout( function() {
            $modal.find( '.aura-veh-modal-close' ).first().focus();
        }, 350 );
    }

    function typeLabel( type ) {
        var map = TXT.type || {};
        var label = map[ type ] || type;
        var badgeClass = 'aura-badge aura-badge-' + type;
        return '<span class="' + badgeClass + '">' + label + '</span>';
    }

    function statusBadge( status ) {
        var map = TXT.status || {};
        var label = map[ status ] || status;
        var badgeClass = 'aura-badge aura-badge-status-' + status;
        return '<span class="' + badgeClass + '">' + label + '</span>';
    }

    function statusLabel( status ) {
        var map = TXT.status || {};
        return map[ status ] || status || '—';
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

    function normalizeFuelLevel( value ) {
        var numeric = parseInt( value, 10 );
        if ( isNaN( numeric ) ) {
            return 0;
        }
        numeric = Math.max( 0, Math.min( 100, numeric ) );
        if ( numeric <= 0 ) { return 0; }
        if ( numeric <= 25 ) { return 25; }
        if ( numeric <= 50 ) { return 50; }
        if ( numeric <= 75 ) { return 75; }
        return 100;
    }

    function fuelLevelLabel( value ) {
        var labels = {
            0: 'Vacío',
            25: '1/4',
            50: '1/2',
            75: '3/4',
            100: 'Lleno'
        };
        var level = normalizeFuelLevel( value );
        return labels[ level ] || '—';
    }

    // ── DataTable ─────────────────────────────────────────────

    function initTable() {
        _table = $( '#aura-trips-table' ).DataTable({
            data:       [],
            columns: [
                // 1. Vehículo: Placa (negrita) + Marca/Modelo (pequeño gris)
                { data: null, title: 'Vehículo', responsivePriority: 1, width: '140px',
                  render: function( row ) {
                      var plate = row.plate || '';
                      var brand = row.brand || '';
                      var model = row.model || '';
                      return '<div class="aura-trip-veh-cell" style="line-height:1.3;">'
                           + '<strong style="display:block;font-size:14px;color:#000;">' + $( '<span>' ).text( plate ).html() + '</strong>'
                           + ( brand ? '<small style="display:block;font-size:11px;color:#666;margin-top:2px;">' + $( '<span>' ).text( brand + ' ' + model ).html() + '</small>' : '' )
                           + '</div>';
                  }
                },
                // 2. Tipo: Badge tipo principal + badge subtipo mantenimiento debajo
                { data: null, title: 'Tipo', responsivePriority: 10000, width: '110px',
                  render: function( row ) {
                      var typeHtml = typeLabel( row.trip_type );
                      // Agregar subtipo si es mantenimiento
                      if ( row.trip_type === 'maintenance' && row.maint_subtype ) {
                          var subBadge = '';
                          switch( row.maint_subtype ) {
                              case 'preventive':
                                  subBadge = '<span class="aura-badge aura-badge-preventive">Preventivo</span>';
                                  break;
                              case 'corrective':
                                  subBadge = '<span class="aura-badge aura-badge-corrective">Correctivo</span>';
                                  break;
                              case 'inspection':
                                  subBadge = '<span class="aura-badge aura-badge-inspection">Inspección</span>';
                                  break;
                          }
                          if ( subBadge ) {
                              typeHtml += '<div style="margin-top:4px;">' + subBadge + '</div>';
                          }
                      }
                      return '<div style="line-height:1.2;">' + typeHtml + '</div>';
                  }
                },
                // 3. Responsable/Cliente: Dinámico según trip_type
                { data: null, title: 'Responsable / Cliente', responsivePriority: 10000, width: '120px',
                  render: function( row ) {
                      var name = '—';
                      if ( row.trip_type === 'rental' ) {
                          name = row.client_name || '—';
                      } else if ( row.trip_type === 'maintenance' ) {
                          name = row.assigned_to_name || row.created_by_name || '—';
                      } else {
                          name = row.responsible_name || '—';
                      }
                      return $( '<span>' ).text( name ).html();
                  }
                },
                // 4. Destino: Solo si no es rental, sino mostrar "—"
                { data: null, title: 'Destino', responsivePriority: 10000, width: '120px',
                  render: function( row ) {
                      if ( row.trip_type === 'rental' ) {
                          return '—';
                      }
                      return $( '<span>' ).text( row.destination || '—' ).html();
                  }
                },
                // 5. Salida: Fecha+hora (línea 1) + "(odómetro km)" (línea 2 pequeña)
                { data: null, title: 'Salida', responsivePriority: 3, width: '130px',
                  render: function( row ) {
                      var dt = row.departure_datetime ? fmtDate( row.departure_datetime ) : '—';
                      var odometer = row.departure_odometer ? '(' + Number( row.departure_odometer ).toLocaleString() + ' km)' : '';
                      return '<div style="line-height:1.4;">'
                           + '<div>' + dt + '</div>'
                           + ( odometer ? '<small style="color:#666;font-size:11px;">' + odometer + '</small>' : '' )
                           + '</div>';
                  }
                },
                // 6. Retorno: Idem Salida, o "—" si no retornó
                { data: null, title: 'Retorno', responsivePriority: 10000, width: '130px',
                  render: function( row ) {
                      if ( !row.return_datetime ) {
                          return '—';
                      }
                      var dt = fmtDate( row.return_datetime );
                      var odometer = row.return_odometer ? '(' + Number( row.return_odometer ).toLocaleString() + ' km)' : '';
                      return '<div style="line-height:1.4;">'
                           + '<div>' + dt + '</div>'
                           + ( odometer ? '<small style="color:#666;font-size:11px;">' + odometer + '</small>' : '' )
                           + '</div>';
                  }
                },
                // 7. Km: Kilómetros con formato de miles
                { data: null, title: 'KM', responsivePriority: 10000, width: '80px',
                  render: function( row ) {
                      if ( !row.km_traveled ) {
                          return '—';
                      }
                      return Number( row.km_traveled ).toLocaleString() + ' km';
                  }
                },
                // 8. Estado: Badge del estatus
                { data: null, title: 'Estado', responsivePriority: 2, width: '100px',
                  render: function( row ) {
                      return statusBadge( row.status );
                  }
                },
                // 9. Acciones: Botones circulares contextuales
                { data: null, title: 'Acciones', orderable: false, responsivePriority: 1, width: '140px',
                  render: function( row ) {
                      var html = '<div class="aura-trips-actions" style="display:flex;gap:4px;align-items:center;">';
                      html += '<button class="button button-small aura-trip-action aura-trip-detail" data-id="' + row.id + '" title="Ver detalles" style="padding:4px 8px;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">' +
                          '<span class="dashicons dashicons-visibility" style="width:16px;height:16px;"></span></button>';
                      var canAct = CFG.canEditAll || ( CFG.canEditOwn && row.created_by == CFG.currentUid );
                      var canDelete = CFG.canDeleteAll || ( CFG.canDeleteOwn && row.created_by == CFG.currentUid );
                      if ( row.status === 'active' ) {
                          if ( canAct ) {
                          html += '<button class="button button-small aura-trip-action aura-trip-edit" data-id="' + row.id + '" title="Editar" style="padding:4px 8px;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">' +
                              '<span class="dashicons dashicons-edit" style="width:16px;height:16px;"></span></button>';
                          html += '<button class="button button-small aura-trip-action aura-trip-checkin" data-id="' + row.id + '" title="Registrar retorno" style="padding:4px 8px;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">' +
                              '<span class="dashicons dashicons-yes-alt" style="width:16px;height:16px;"></span></button>';
                          html += '<button class="button button-small aura-trip-action aura-trip-cancel" data-id="' + row.id + '" title="Cancelar" style="padding:4px 8px;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">' +
                              '<span class="dashicons dashicons-dismiss" style="width:16px;height:16px;"></span></button>';
                          }
                      }
                      if ( row.status !== 'active' && canDelete ) {
                      html += '<button class="button button-small aura-trip-action aura-trip-delete" data-id="' + row.id + '" title="Eliminar" style="padding:4px 8px;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">' +
                          '<span class="dashicons dashicons-trash" style="width:16px;height:16px;"></span></button>';
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
            responsive:  false,
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

    function updateFuelGauge( value, prefix, allowEmpty ) {
        var isEmpty = allowEmpty && ( value === '' || value === null || value === undefined );
        prefix = prefix || 'trip';

        if ( isEmpty ) {
            $( '#' + prefix + '-fuel-gauge' ).attr( 'data-empty', '1' ).attr( 'data-value', 0 );
            $( '#' + prefix + '-fuel-gauge-fill' ).css( 'width', '0%' );
            $( '#' + prefix + '-fuel-gauge-state' ).text( '—' );
            return;
        }

        var rounded = normalizeFuelLevel( value );

        $( '#' + prefix + '-fuel-gauge' ).removeAttr( 'data-empty' ).attr( 'data-value', rounded );
        $( '#' + prefix + '-fuel-gauge-fill' ).css( 'width', rounded + '%' );
        $( '#' + prefix + '-fuel-gauge-state' ).text( fuelLevelLabel( rounded ) );
    }

    // ── Modal: Nueva salida ───────────────────────────────────

    function resetFormModal() {
        $( '#aura-trips-form-id' ).val( '' );
        $( '#trip-vehicle_id' ).empty().append( '<option value="">— Seleccionar —</option>' );
        $( '#trip-vehicle_id' ).prop( 'disabled', false );
        $( '#trip-vehicle-info' ).hide();
        $( '#trip-trip_type' ).val( '' );
        $( '.aura-trips-type-card' ).css( 'pointer-events', '' ).removeAttr( 'aria-disabled' );
        $( '#trip-area_id' ).val( '0' );
        $( '#trip-departure_datetime' ).val( getNow() );
        $( '#trip-departure_odometer' ).val( '0' );
        $( '#trip-departure_fuel' ).val( '100' );
        updateFuelGauge( 100 );
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

    function openEditModal( tripId ) {
        resetFormModal();
        loadUsersDropdown();

        apiFetch( 'GET', 'vehicles/trips/' + tripId )
            .done( function( resp ) {
                var trip = resp.trip || resp;

                $( '#aura-trips-form-id' ).val( trip.id );
                $( '#aura-trips-form-title' ).text( 'Editar Salida #' + trip.id );
                $( '#aura-trips-form-submit' ).text( 'Guardar cambios' ).prop( 'disabled', false );

                loadAvailableVehicles( trip.trip_type, trip.vehicle_id );
                $( '#trip-vehicle_id' ).prop( 'disabled', true );

                $( '#trip-trip_type' ).val( trip.trip_type ).trigger( 'change' );
                $( '.aura-trips-type-card' ).removeClass( 'is-active' );
                $( '.aura-trips-type-card[data-type="' + trip.trip_type + '"]' ).addClass( 'is-active' );
                $( '.aura-trips-type-card' ).css( 'pointer-events', 'none' ).attr( 'aria-disabled', 'true' );

                $( '#trip-area_id' ).val( String( trip.area_id || 0 ) );
                loadTripCatalogs( {
                    destination: trip.destination || '',
                    purpose: trip.purpose || ''
                } );

                $( '#trip-departure_datetime' ).val( ( trip.departure_datetime || '' ).replace( ' ', 'T' ).slice( 0, 16 ) );
                $( '#trip-departure_odometer' ).val( trip.departure_odometer || 0 );
                $( '#trip-departure_fuel' ).val( normalizeFuelLevel( trip.departure_fuel || 0 ) );
                updateFuelGauge( $( '#trip-departure_fuel' ).val(), 'trip' );

                $( '#trip-client_name' ).val( trip.client_name || '' );
                $( '#trip-client_phone' ).val( trip.client_phone || '' );
                $( '#trip-client_email' ).val( trip.client_email || '' );
                $( '#trip-client_document' ).val( trip.client_document || '' );
                $( '#trip-rate_per_km' ).val( trip.rate_per_km || '0.00' );

                $( '#trip-responsible_name' ).val( trip.responsible_name || '' );
                $( '#trip-assigned_to' ).val( String( trip.assigned_to || 0 ) );
                $( '#trip-trip_description' ).val( trip.trip_description || '' );

                $( '#trip-maint_subtype' ).val( trip.maint_subtype || 'preventive' );
                $( '#trip-maint_priority' ).val( trip.maint_priority || 'medium' );
                $( '#trip-maint_description' ).val( trip.maint_description || '' );
                $( '#trip-maint_provider' ).val( trip.maint_provider || '' );
                $( '#trip-maint_contact' ).val( trip.maint_contact || '' );
                $( '#trip-maint_estimated_cost' ).val( trip.maint_estimated_cost || '0.00' );

                openModal( $( '#aura-trips-modal-form' ) );
            } )
            .fail( function( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ( TXT.error || 'Error.' );
                showNotice( msg, 'error' );
            } );
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

    $( document ).on( 'change', '#trip-departure_fuel', function() {
        updateFuelGauge( $( this ).val(), 'trip' );
        $( this ).closest( '.aura-veh-form-col' ).removeClass( 'has-error' );
    } );

    $( document ).on( 'change', '#checkin-return_fuel', function() {
        updateFuelGauge( $( this ).val(), 'checkin', true );
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
        var editId  = parseInt( $( '#aura-trips-form-id' ).val(), 10 ) || 0;
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

        apiFetch( editId ? 'PUT' : 'POST', editId ? 'vehicles/trips/' + editId : 'vehicles/trips', data )
            .done( function() {
                closeAllModals();
                showNotice( editId ? ( TXT.updated || 'Salida actualizada.' ) : ( TXT.saved || 'Salida registrada.' ) );
                loadTrips();
            })
            .fail( function( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ( TXT.error || 'Error.' );
                $err.text( msg ).show();
                $btn.prop( 'disabled', false ).text( editId ? 'Guardar cambios' : 'Registrar Salida' );
            });
    });

    // ── Modal: Check-in (retorno) ─────────────────────────────

    function buildCheckinSummary( trip ) {
        var type = trip.trip_type;
        var personLabel = '—';
        if ( type === 'rental' ) {
            personLabel = trip.client_name || '—';
        } else if ( type === 'errand' || type === 'other' ) {
            personLabel = trip.responsible_name || '—';
        } else if ( type === 'maintenance' ) {
            personLabel = trip.assigned_to_name || trip.created_by_name || '—';
        }

        var $div = $( '<div class="aura-trips-checkin-summary-card"></div>' );
        $div.html(
            '<div class="aura-trips-checkin-summary-head">'
                + '<div class="aura-trips-checkin-summary-veh">'
                    + '<span class="dashicons dashicons-car"></span>'
                    + '<div>'
                        + '<strong>' + $( '<span>' ).text( ( trip.plate || '' ) + ' — ' + ( trip.brand || '' ) + ' ' + ( trip.model || '' ) ).html() + '</strong>'
                        + '<small>ID salida #' + Number( trip.id || 0 ) + '</small>'
                    + '</div>'
                + '</div>'
                + '<span class="aura-trip-status aura-trip-status--' + ( trip.status || 'active' ) + '">' + typeLabel( type ) + '</span>'
            + '</div>'
            + '<div class="aura-trips-checkin-summary-grid">'
                + '<div><label>Salida</label><strong>' + fmtDate( trip.departure_datetime ) + '</strong></div>'
                + '<div><label>Odómetro salida</label><strong>' + Number( trip.departure_odometer || 0 ).toLocaleString() + ' km</strong></div>'
                + '<div><label>Combustible salida</label><strong>' + fuelLevelLabel( trip.departure_fuel ) + '</strong></div>'
                + '<div><label>Responsable/Cliente</label><strong>' + $( '<span>' ).text( personLabel ).html() + '</strong></div>'
            + '</div>'
        );
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
                updateFuelGauge( '', 'checkin', true );
                $( '#aura-checkin-preview' ).hide();
                $( '#checkin-additional_charges, #checkin-discounts' ).val( '0.00' );
                $( '#checkin-maint_actual_cost' ).val( '0.00' );
                $( '#checkin-next_service_interval_km' ).val( '0' );
                $( '#checkin-maint_completion_notes' ).val( '' );
                $( '#checkin-section-rental' ).toggle( trip.trip_type === 'rental' );
                $( '#checkin-section-maintenance' ).toggle( trip.trip_type === 'maintenance' );
                $( '#checkin-section-expenses' ).toggle( trip.trip_type !== 'rental' );
                updateNextServiceCalc();
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

    // ── Suma dinámica próximo servicio por km (mantenimiento) ──
    function updateNextServiceCalc() {
        var tripType = $( '#aura-checkin-trip-type' ).val();
        if ( tripType !== 'maintenance' ) {
            $( '#checkin-next-service-calc' ).hide();
            return;
        }
        var retOdometer = parseInt( $( '#checkin-return_odometer' ).val(), 10 ) || 0;
        var interval    = parseInt( $( '#checkin-next_service_interval_km' ).val(), 10 ) || 0;
        var nextService = retOdometer + interval;
        
        // Actualizar valores de cálculo
        $( '#calc-odometer-value' ).text( retOdometer.toLocaleString() + ' km' );
        $( '#calc-interval-value' ).text( interval > 0 ? interval.toLocaleString() + ' km' : '—' );
        $( '#tooltip-next-km' ).text( nextService.toLocaleString() + ' km' );
        
        if ( interval > 0 ) {
            $( '#calc-result-value' ).text( nextService.toLocaleString() + ' km' );
            $( '#checkin-next-service-calc' ).fadeIn( 200 );
        } else {
            $( '#checkin-next-service-calc' ).fadeOut( 200 );
        }
    }

    $( document ).on( 'input', '#checkin-return_odometer, #checkin-next_service_interval_km', updateNextServiceCalc );
    $( document ).on( 'change', '#aura-checkin-trip-type', updateNextServiceCalc );

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
                var person = '—';
                if ( t.trip_type === 'rental' ) {
                    person = t.client_name || '—';
                } else if ( t.trip_type === 'maintenance' ) {
                    person = t.assigned_to_name || t.created_by_name || '—';
                } else {
                    person = t.responsible_name || '—';
                }

                var html = '<div class="aura-trip-detail">'
                    + '<div class="aura-trip-detail-head">'
                        + '<div class="aura-trip-detail-veh">'
                            + '<span class="dashicons dashicons-car"></span>'
                            + '<div>'
                                + '<strong>' + $( '<span>' ).text( ( t.plate || '' ) + ' — ' + ( t.brand || '' ) + ' ' + ( t.model || '' ) ).html() + '</strong>'
                                + '<small>Salida #' + Number( t.id || 0 ) + '</small>'
                            + '</div>'
                        + '</div>'
                        + '<div class="aura-trip-detail-badges">'
                            + '<span class="aura-trip-status aura-trip-status--' + ( t.status || 'active' ) + '">' + statusLabel( t.status ) + '</span>'
                            + '<span class="aura-trip-pill">' + typeLabel( t.trip_type ) + '</span>'
                        + '</div>'
                    + '</div>'
                    + '<div class="aura-trip-detail-grid">'
                        + '<div><label>Fecha salida</label><strong>' + fmtDate( t.departure_datetime ) + '</strong></div>'
                        + '<div><label>Fecha retorno</label><strong>' + fmtDate( t.return_datetime ) + '</strong></div>'
                        + '<div><label>Odómetro salida</label><strong>' + Number( t.departure_odometer || 0 ).toLocaleString() + ' km</strong></div>'
                        + '<div><label>Odómetro retorno</label><strong>' + ( t.return_odometer ? Number( t.return_odometer ).toLocaleString() + ' km' : '—' ) + '</strong></div>'
                        + '<div><label>Combustible salida</label><strong>' + fuelLevelLabel( t.departure_fuel ) + '</strong></div>'
                        + '<div><label>Combustible retorno</label><strong>' + ( t.return_fuel === null || t.return_fuel === undefined ? '—' : fuelLevelLabel( t.return_fuel ) ) + '</strong></div>'
                        + '<div><label>KM recorridos</label><strong>' + ( t.km_traveled ? Number( t.km_traveled ).toLocaleString() : '—' ) + '</strong></div>'
                        + '<div><label>Persona</label><strong>' + $( '<span>' ).text( person ).html() + '</strong></div>'
                    + '</div>';

                if ( t.trip_type === 'rental' ) {
                    html += '<div class="aura-trip-detail-section">'
                        + '<h4>Datos de renta</h4>'
                        + '<div class="aura-trip-detail-grid">'
                            + '<div><label>Cliente</label><strong>' + $( '<span>' ).text( t.client_name || '—' ).html() + '</strong></div>'
                            + '<div><label>Tarifa/km</label><strong>' + ( t.rate_per_km || '—' ) + '</strong></div>'
                            + '<div><label>Total</label><strong>' + ( t.total_amount || '—' ) + '</strong></div>'
                            + '<div><label>Gastos</label><strong>' + ( t.total_expenses || '—' ) + '</strong></div>'
                        + '</div>'
                    + '</div>';
                } else if ( t.trip_type === 'maintenance' ) {
                    html += '<div class="aura-trip-detail-section">'
                        + '<h4>Datos de mantenimiento</h4>'
                        + '<div class="aura-trip-detail-grid">'
                            + '<div><label>Descripción</label><strong>' + $( '<span>' ).text( t.maint_description || '—' ).html() + '</strong></div>'
                            + '<div><label>Subtipo</label><strong>' + $( '<span>' ).text( t.maint_subtype || '—' ).html() + '</strong></div>'
                            + '<div><label>Prioridad</label><strong>' + $( '<span>' ).text( t.maint_priority || '—' ).html() + '</strong></div>'
                            + '<div><label>Costo real</label><strong>' + ( t.maint_actual_cost || '—' ) + '</strong></div>'
                        + '</div>'
                    + '</div>';
                } else {
                    html += '<div class="aura-trip-detail-section">'
                        + '<h4>Datos operativos</h4>'
                        + '<div class="aura-trip-detail-grid">'
                            + '<div><label>Responsable</label><strong>' + $( '<span>' ).text( t.responsible_name || '—' ).html() + '</strong></div>'
                            + '<div><label>Destino</label><strong>' + $( '<span>' ).text( t.destination || '—' ).html() + '</strong></div>'
                            + '<div><label>Propósito</label><strong>' + $( '<span>' ).text( t.purpose || '—' ).html() + '</strong></div>'
                            + '<div><label>Registrado por</label><strong>' + $( '<span>' ).text( t.created_by_name || '—' ).html() + '</strong></div>'
                        + '</div>'
                    + '</div>';
                }

                if ( t.cancellation_reason ) {
                    html += '<div class="aura-trip-detail-note"><strong>Motivo cancelación:</strong> ' + $( '<span>' ).text( t.cancellation_reason ).html() + '</div>';
                }

                html += '</div>';
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

    $( document ).on( 'click', '.aura-trip-edit', function() {
        openEditModal( $( this ).data( 'id' ) );
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
