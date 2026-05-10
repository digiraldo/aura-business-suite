/**
 * Aura Vehicles — Módulo Vehículos (Fase 2)
 * DataTables + CRUD completo via REST API.
 *
 * Depende de: jQuery, DataTables, auraVehiclesListCfg (inline en page-vehicles.php)
 *
 * @package Aura_Business_Suite\Vehicles
 */

( function ( $ ) {
    'use strict';

    if ( typeof auraVehiclesListCfg === 'undefined' ) {
        return;
    }

    var CFG      = auraVehiclesListCfg;
    var API      = CFG.apiBase + 'vehicles';
    var NONCE    = CFG.nonce;
    var TXT      = CFG.txt;

    // ── Estado local ─────────────────────────────────────────────
    var _table          = null;   // Instancia DataTables
    var _allAreas       = [];     // Cache de áreas
    var _filters        = { search: '', status: '', type: '', area_id: 0 };
    var _cropperInst    = null;   // Instancia Cropper.js para foto principal
    var _cropAttachId   = 0;      // attachment_id seleccionado de la Media Library
    var _lightboxImages = [];     // Lista de imágenes del vehículo en detalle
    var _lightboxIndex  = 0;      // Índice actual del lightbox

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Petición autenticada a la REST API.
     *
     * @param {string}  url
     * @param {string}  method
     * @param {Object}  [data]
     * @param {boolean} [isForm]  true para FormData (upload fotos)
     * @returns {jqXHR}
     */
    function api( url, method, data, isForm ) {
        var options = {
            url    : url,
            method : method || 'GET',
            headers: { 'X-WP-Nonce': NONCE },
        };

        if ( isForm ) {
            options.data        = data;
            options.processData = false;
            options.contentType = false;
        } else if ( data ) {
            options.data        = JSON.stringify( data );
            options.contentType = 'application/json';
        }

        return $.ajax( options );
    }

    function showMsg( $el, msg, isError ) {
        $el.html( '<p class="' + ( isError ? 'notice-error' : 'notice-success' ) + '" style="padding:6px 10px;margin:0;">' + escHtml( msg ) + '</p>' ).show();
    }

    function escHtml( str ) {
        return $( '<div>' ).text( str || '' ).html();
    }

    function badge( statusKey ) {
        var label = ( TXT.status[ statusKey ] ) || statusKey;
        var color = { available: '#2271b1', rented: '#814c97', maintenance: '#d63638', unavailable: '#666' }[ statusKey ] || '#999';
        return '<span class="aura-veh-badge" style="background:' + color + ';color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">' + escHtml( label ) + '</span>';
    }

    // ── DataTables ────────────────────────────────────────────────

    function initTable() {
        _table = $( '#aura-veh-table' ).DataTable( {
            language : {
                emptyTable : TXT.no_results,
                loadingRecords: TXT.loading,
                search     : '',
                searchPlaceholder: '',
                paginate   : { first: '«', last: '»', previous: '‹', next: '›' },
                info       : '_TOTAL_ vehículos',
                infoEmpty  : '0 vehículos',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu : 'Mostrar _MENU_ por página',
            },
            dom      : '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            columns  : [
                { data: null, orderable: false, render: renderThumb, width: '70px', responsivePriority: 10000 },
                { data: 'plate', render: $.fn.dataTable.render.text(), responsivePriority: 1 },
                { data: null, render: renderBrandModel, responsivePriority: 1 },
                { data: 'status', render: function ( v ) { return badge( v ); }, responsivePriority: 2 },
                { data: 'type', render: function ( v ) { return escHtml( TXT.type[ v ] || v ); }, responsivePriority: 10000 },
                { data: 'mileage', render: function ( v ) { return Number( v ).toLocaleString(); }, responsivePriority: 10000 },
                { data: null, render: renderAreas, responsivePriority: 10000 },
                { data: null, orderable: false, render: renderActions, responsivePriority: 1 },
            ],
            order         : [ [ 1, 'asc' ] ],
            pageLength    : 20,
            lengthMenu    : [ 10, 20, 50, 100 ],
            searching     : false,  // búsqueda manual via filtros
            paging        : true,
            info          : true,
            responsive    : true,
            autoWidth     : false,
        } );
    }

    function renderThumb( row ) {
        // Preferir foto principal (Cropper.js) luego galería legacy
        var thumb = row.photo_thumb_url || ( row.photos && row.photos.length ? ( row.photos[0].url || '' ) : '' );
        var full  = row.photo_url      || thumb;
        if ( ! thumb ) { return '<span style="color:#ccc;">—</span>'; }
        return '<div class="aura-img-preview">'
             + '<img src="' + escHtml( thumb ) + '" class="aura-thumb" alt="">'
             + '<div class="aura-img-tooltip"><img src="' + escHtml( full ) + '" alt=""></div>'
             + '</div>';
    }

    function renderBrandModel( row ) {
        return escHtml( row.brand ) + ' ' + escHtml( row.model ) + ( row.year ? ' <small>(' + escHtml( String( row.year ) ) + ')</small>' : '' );
    }

    function renderAreas( row ) {
        if ( ! row.areas || ! row.areas.length ) return '<em style="color:#999;">Sin áreas</em>';
        return row.areas.map( function ( a ) {
            return '<span style="background:' + escHtml( a.color || '#ccc' ) + ';color:#fff;padding:1px 7px;border-radius:9px;font-size:11px;margin:1px;">' + escHtml( a.name ) + '</span>';
        } ).join( ' ' );
    }

    function renderActions( row ) {
        var html  = '<div class="aura-veh-actions">';
        var id    = row.id;

        html += '<button class="button button-small aura-veh-action aura-veh-action--view" data-action="view" data-id="' + id + '" title="Ver detalle"><span class="dashicons dashicons-visibility"></span></button> ';

        if ( CFG.canEdit ) {
            html += '<button class="button button-small aura-veh-action aura-veh-action--edit" data-action="edit" data-id="' + id + '" title="Editar"><span class="dashicons dashicons-edit"></span></button> ';
            html += '<button class="button button-small aura-veh-action aura-veh-action--areas" data-action="areas" data-id="' + id + '" title="Áreas"><span class="dashicons dashicons-networking"></span></button> ';
            html += '<button class="button button-small aura-veh-action aura-veh-action--photos" data-action="photos" data-id="' + id + '" title="Fotos"><span class="dashicons dashicons-format-image"></span></button> ';

            if ( row.status !== 'unavailable' ) {
                html += '<button class="button button-small aura-veh-action aura-veh-action--unavailable" data-action="unavailable" data-id="' + id + '" title="Dar de baja"><span class="dashicons dashicons-remove"></span></button> ';
            } else {
                html += '<button class="button button-small aura-veh-action aura-veh-action--restore" data-action="restore" data-id="' + id + '" title="Restaurar"><span class="dashicons dashicons-update"></span></button> ';
            }

            if ( row.areas && row.areas.length > 1 ) {
                html += '<button class="button button-small aura-veh-action aura-veh-action--transfer" data-action="transfer" data-id="' + id + '" title="Transferir"><span class="dashicons dashicons-migrate"></span></button> ';
            }
        }

        // Botón QR (siempre visible para quienes pueden editar)
        if ( CFG.canEdit ) {
            html += '<button class="button button-small aura-veh-action aura-veh-action--qr" data-action="qr" data-id="' + id + '" data-plate="' + escHtml( row.plate ) + '" data-name="' + escHtml( row.brand + ' ' + row.model ) + '" title="Código QR"><span class="dashicons dashicons-share"></span></button> ';
        }

        if ( CFG.canDelete ) {
            html += '<button class="button button-small button-link-delete aura-veh-action aura-veh-action--delete" data-action="delete" data-id="' + id + '" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>';
        }

        html += '</div>';
        return html;
    }

    // ── Cargar / recargar tabla ───────────────────────────────────

    function loadVehicles() {
        if ( ! _table ) { return; }
        var params = $.param( {
            search   : _filters.search,
            status   : _filters.status,
            type     : _filters.type,
            area_id  : _filters.area_id,
            per_page : 200,
        } );

        api( API + '?' + params, 'GET' ).done( function ( res ) {
            var data = res.items || [];

            // Normalizar photos como array de objetos {filename, url}
            $.each( data, function ( i, v ) {
                if ( $.isArray( v.photos ) && v.photos.length && typeof v.photos[0] === 'string' ) {
                    v.photos = $.map( v.photos, function ( f ) {
                        return { filename: f, url: f };
                    } );
                }
            } );

            _table.clear().rows.add( data ).draw();
        } ).fail( function () {
            alert( TXT.error );
        } );
    }

    // ── Cargar áreas disponibles (una vez) ───────────────────────

    function loadAreasDropdown( $select, selectedIds ) {
        selectedIds = selectedIds || [];
        if ( _allAreas.length ) {
            fillAreaSelect( $select, selectedIds );
            return;
        }
        api( CFG.apiBase + 'vehicles/areas-dropdown', 'GET' ).done( function ( res ) {
            _allAreas = res.items || [];
            fillAreaSelect( $select, selectedIds );
        } );
    }

    function fillAreaSelect( $select, selectedIds ) {
        $select.empty().append( '<option value="">— Seleccionar área —</option>' );
        $.each( _allAreas, function ( i, a ) {
            var opt = $( '<option>' ).val( a.id ).text( a.name );
            if ( $.inArray( a.id, selectedIds ) >= 0 ) opt.prop( 'selected', true );
            $select.append( opt );
        } );
    }

    // ══════════════════════════════════════════════════════════════
    // MODALES — helpers genéricos
    // ══════════════════════════════════════════════════════════════

    function openModal( id ) {
        $( id ).show();
        $( 'body' ).addClass( 'aura-veh-modal-open' );
    }

    function closeModal( id ) {
        $( id ).hide();
        $( 'body' ).removeClass( 'aura-veh-modal-open' );
    }

    function closeAllModals() {
        $( '.aura-veh-modal' ).hide();
        $( 'body' ).removeClass( 'aura-veh-modal-open' );
        // Limpiar cropper si el modal de crop se cierra por esta vía
        if ( _cropperInst ) {
            _cropperInst.destroy();
            _cropperInst = null;
        }

        _lightboxImages = [];
        _lightboxIndex  = 0;
        $( '#aura-veh-lightbox-img' ).attr( 'src', '' );
        $( '#aura-veh-lightbox-thumbs' ).empty();
    }

    // ══════════════════════════════════════════════════════════════
    // MODAL: Ver detalle
    // ══════════════════════════════════════════════════════════════

    function openViewModal( vehicleId ) {
        var $modal = $( '#aura-veh-modal-view' );
        var $body  = $modal.find( '#aura-veh-view-body' );

        $body.html( '<div class="aura-veh-view-loading"><span class="spinner is-active"></span><p>Cargando detalle del vehículo...</p></div>' );
        openModal( '#aura-veh-modal-view' );

        api( API + '/' + vehicleId, 'GET' ).done( function ( v ) {
            var vehicleName = ( ( v.brand || '' ) + ' ' + ( v.model || '' ) ).trim();
            var heroPhoto   = v.photo_url || '';
            var firstGallery = ( v.photo_urls && v.photo_urls.length ) ? ( v.photo_urls[0] || '' ) : '';
            if ( ! heroPhoto ) {
                heroPhoto = firstGallery;
            }

            // Construir galería única para navegación en lightbox
            var gallery = [];
            if ( v.photo_url ) {
                gallery.push( v.photo_url );
            }
            if ( $.isArray( v.photo_urls ) && v.photo_urls.length ) {
                $.each( v.photo_urls, function ( i, imgUrl ) {
                    if ( imgUrl ) {
                        gallery.push( imgUrl );
                    }
                } );
            }
            _lightboxImages = gallery.filter( function ( url, idx, arr ) {
                return arr.indexOf( url ) === idx;
            } );
            _lightboxIndex = 0;
            renderLightboxThumbs();

            var areas = ( v.areas || [] ).map( function ( a ) {
                var color = a.color || '#64748b';
                return '<span class="aura-veh-view-chip" style="background:' + escHtml( color ) + ';">' + escHtml( a.name ) + '</span>';
            } ).join( '' );

            var html = ''
                + '<div class="aura-veh-view-hero">'
                + '  <div class="aura-veh-view-photo">'
                + ( heroPhoto
                    ? '    <img src="' + escHtml( heroPhoto ) + '" alt="Vehículo" class="aura-veh-view-photo-img" data-full="' + escHtml( heroPhoto ) + '" data-index="0">'
                    : '    <div class="aura-veh-view-photo-empty"><span class="dashicons dashicons-car"></span><p>Sin foto</p></div>' )
                + '  </div>'
                + '  <div class="aura-veh-view-main">'
                + '    <h3>' + escHtml( vehicleName || 'Vehículo' ) + '</h3>'
                + '    <div class="aura-veh-license-plate" title="Placa del vehículo">' + escHtml( v.plate || 'N/D' ) + '</div>'
                + '    <div class="aura-veh-view-meta">'
                +          badge( v.status )
                + '      <span class="aura-veh-view-pill">' + escHtml( TXT.type[ v.type ] || v.type || 'N/D' ) + '</span>'
                + '      <span class="aura-veh-view-pill">' + escHtml( Number( v.mileage || 0 ).toLocaleString() ) + ' km</span>'
                + '    </div>'
                + '  </div>'
                + '</div>'
                + '<div class="aura-veh-view-grid">'
                + '  <div class="aura-veh-view-card">'
                + '    <h4>Identificación</h4>'
                + '    <dl>'
                + '      <div><dt>Marca</dt><dd>' + escHtml( v.brand || 'N/D' ) + '</dd></div>'
                + '      <div><dt>Modelo</dt><dd>' + escHtml( v.model || 'N/D' ) + '</dd></div>'
                + '      <div><dt>Año</dt><dd>' + escHtml( v.year ? String( v.year ) : 'N/D' ) + '</dd></div>'
                + '      <div><dt>Color</dt><dd>' + escHtml( v.color || 'N/D' ) + '</dd></div>'
                + '      <div><dt>VIN</dt><dd>' + escHtml( v.vin || 'N/D' ) + '</dd></div>'
                + '    </dl>'
                + '  </div>'
                + '  <div class="aura-veh-view-card">'
                + '    <h4>Operación</h4>'
                + '    <dl>'
                + '      <div><dt>Combustible</dt><dd>' + escHtml( TXT.fuel[ v.fuel_type ] || v.fuel_type || 'N/D' ) + '</dd></div>'
                + '      <div><dt>Transmisión</dt><dd>' + escHtml( TXT.transmission[ v.transmission ] || v.transmission || 'N/D' ) + '</dd></div>'
                + '      <div><dt>Tarifa por km</dt><dd>$ ' + escHtml( Number( v.rate_per_km || 0 ).toLocaleString() ) + '</dd></div>'
                + '      <div><dt>Áreas</dt><dd>' + ( areas || '<span style="color:#6b7280;">Sin áreas</span>' ) + '</dd></div>'
                + '    </dl>'
                + '  </div>'
                + '</div>'
                + '<div class="aura-veh-view-card aura-veh-view-notes">'
                + '  <h4>Notas</h4>'
                + '  <p>' + escHtml( v.notes || 'Sin observaciones registradas.' ) + '</p>'
                + '</div>';

            $body.html( html );
        } ).fail( function ( xhr ) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
            $body.html( '<div class="notice notice-error" style="margin:0;"><p>' + escHtml( msg ) + '</p></div>' );
        } );
    }

    // ══════════════════════════════════════════════════════════════
    // MODAL: Crear / Editar vehículo
    // ══════════════════════════════════════════════════════════════

    function openFormModal( vehicleId ) {
        var $modal = $( '#aura-veh-modal-form' );

        $modal.find( '#aura-veh-form' )[0].reset();
        $modal.find( '#aura-veh-form-id' ).val( '' );
        $modal.find( '#aura-veh-form-error' ).hide().text( '' );
        $modal.find( '#aura-veh-modal-form-title' ).text( vehicleId ? 'Editar Vehículo' : 'Nuevo Vehículo' );
        $modal.find( '#veh-photo' ).val( 0 );
        $modal.find( '#aura-veh-photo-preview' ).empty();
        $modal.find( '#aura-veh-photo-remove-btn' ).hide();

        if ( vehicleId ) {
            api( API + '/' + vehicleId, 'GET' ).done( function ( v ) {
                $modal.find( '#aura-veh-form-id' ).val( v.id );
                $modal.find( '#veh-plate' ).val( v.plate );
                $modal.find( '#veh-brand' ).val( v.brand );
                $modal.find( '#veh-model' ).val( v.model );
                $modal.find( '#veh-year' ).val( v.year );
                $modal.find( '#veh-color' ).val( v.color );
                $modal.find( '#veh-vin' ).val( v.vin );
                $modal.find( '#veh-type' ).val( v.type );
                $modal.find( '#veh-fuel_type' ).val( v.fuel_type );
                $modal.find( '#veh-transmission' ).val( v.transmission );
                $modal.find( '#veh-mileage' ).val( v.mileage );
                $modal.find( '#veh-rate_per_km' ).val( v.rate_per_km );
                $modal.find( '#veh-status' ).val( v.status );
                $modal.find( '#veh-notes' ).val( v.notes );

                // Foto principal
                $modal.find( '#veh-photo' ).val( v.photo || 0 );
                var $preview = $modal.find( '#aura-veh-photo-preview' ).empty();
                if ( v.photo_thumb_url ) {
                    $preview.html( '<img src="' + escHtml( v.photo_thumb_url ) + '" alt="" style="max-height:80px;border-radius:4px;display:block;">' );
                    $modal.find( '#aura-veh-photo-remove-btn' ).show();
                } else {
                    $modal.find( '#aura-veh-photo-remove-btn' ).hide();
                }
            } ).fail( function () {
                alert( TXT.error );
                return;
            } );
        }

        openModal( '#aura-veh-modal-form' );
    }

    function submitForm() {
        var $modal  = $( '#aura-veh-modal-form' );
        var $err    = $modal.find( '#aura-veh-form-error' );
        var id      = $modal.find( '#aura-veh-form-id' ).val();
        var isNew   = ! id;

        var data = {
            plate       : $modal.find( '#veh-plate'        ).val(),
            brand       : $modal.find( '#veh-brand'        ).val(),
            model       : $modal.find( '#veh-model'        ).val(),
            year        : $modal.find( '#veh-year'         ).val() || null,
            color       : $modal.find( '#veh-color'        ).val(),
            vin         : $modal.find( '#veh-vin'          ).val(),
            type        : $modal.find( '#veh-type'         ).val(),
            fuel_type   : $modal.find( '#veh-fuel_type'    ).val(),
            transmission: $modal.find( '#veh-transmission' ).val(),
            mileage     : $modal.find( '#veh-mileage'      ).val(),
            rate_per_km : $modal.find( '#veh-rate_per_km'  ).val(),
            status      : $modal.find( '#veh-status'       ).val(),
            notes       : $modal.find( '#veh-notes'        ).val(),
            photo       : parseInt( $modal.find( '#veh-photo' ).val() ) || 0,
        };

        if ( ! data.plate || ! data.brand || ! data.model ) {
            $err.text( 'Placa, marca y modelo son obligatorios.' ).show();
            return;
        }

        $err.hide();
        var $btn = $( '#aura-veh-form-submit' ).prop( 'disabled', true ).text( '…' );

        var req = isNew
            ? api( API,          'POST',  data )
            : api( API + '/' + id, 'PUT', data );

        req.done( function () {
            closeAllModals();
            loadVehicles();
            showAdminNotice( TXT.saved, 'success' );
        } ).fail( function ( xhr ) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
            $err.text( msg ).show();
        } ).always( function () {
            $btn.prop( 'disabled', false ).text( 'Guardar' );
        } );
    }

    // ══════════════════════════════════════════════════════════════
    // MODAL: Áreas
    // ══════════════════════════════════════════════════════════════

    function openAreasModal( vehicleId ) {
        var $modal = $( '#aura-veh-modal-areas' );
        $modal.find( '#aura-veh-areas-vehicle-id' ).val( vehicleId );
        $modal.find( '#aura-veh-areas-msg' ).hide().text( '' );

        api( API + '/' + vehicleId, 'GET' ).done( function ( v ) {
            renderCurrentAreas( v.areas || [], vehicleId );

            var assignedIds = $.map( v.areas || [], function ( a ) { return parseInt( a.id ); } );
            loadAreasDropdown( $modal.find( '#aura-veh-area-select' ), [] );
        } );

        openModal( '#aura-veh-modal-areas' );
    }

    function renderCurrentAreas( areas, vehicleId ) {
        var $wrap = $( '#aura-veh-current-areas' ).empty();

        if ( ! areas.length ) {
            $wrap.html( '<em style="color:#999;">Sin áreas asignadas.</em>' );
            return;
        }

        $.each( areas, function ( i, a ) {
            var $tag = $( '<span class="aura-veh-area-tag">' )
                .css( { background: a.color || '#ccc', color: '#fff', padding: '3px 10px', borderRadius: '12px', margin: '3px', display: 'inline-block' } )
                .text( a.name );

            if ( CFG.canEdit ) {
                var $rm = $( '<button type="button" title="Quitar">' )
                    .css( { background: 'none', border: 'none', color: '#fff', cursor: 'pointer', marginLeft: '4px' } )
                    .html( '&times;' )
                    .on( 'click', function () {
                        unassignArea( vehicleId, a.id );
                    } );
                $tag.append( $rm );
            }

            $wrap.append( $tag );
        } );
    }

    function unassignArea( vehicleId, areaId ) {
        api( API + '/' + vehicleId + '/areas/' + areaId, 'DELETE' ).done( function () {
            showMsg( $( '#aura-veh-areas-msg' ), TXT.area_unassigned, false );
            reloadAreasInModal( vehicleId );
            loadVehicles();
        } ).fail( function ( xhr ) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
            showMsg( $( '#aura-veh-areas-msg' ), msg, true );
        } );
    }

    function reloadAreasInModal( vehicleId ) {
        api( API + '/' + vehicleId, 'GET' ).done( function ( v ) {
            renderCurrentAreas( v.areas || [], vehicleId );
        } );
    }

    // ══════════════════════════════════════════════════════════════
    // MODAL: Dar de baja
    // ══════════════════════════════════════════════════════════════

    function openUnavailableModal( vehicleId ) {
        var $modal = $( '#aura-veh-modal-unavailable' );
        $modal.find( '#aura-veh-unavail-vehicle-id' ).val( vehicleId );
        $modal.find( '#aura-veh-unavail-reason' ).val( '' );
        $modal.find( '#aura-veh-unavail-notes' ).val( '' );
        $modal.find( '#aura-veh-unavail-msg' ).hide().text( '' );
        openModal( '#aura-veh-modal-unavailable' );
    }

    // ══════════════════════════════════════════════════════════════
    // MODAL: Transferir
    // ══════════════════════════════════════════════════════════════

    function openTransferModal( vehicleId ) {
        var $modal = $( '#aura-veh-modal-transfer' );
        $modal.find( '#aura-veh-transfer-vehicle-id' ).val( vehicleId );
        $modal.find( '#aura-veh-transfer-msg' ).hide().text( '' );

        api( API + '/' + vehicleId, 'GET' ).done( function ( v ) {
            var $from = $modal.find( '#aura-veh-transfer-from' );
            var $to   = $modal.find( '#aura-veh-transfer-to' );

            $from.empty().append( '<option value="">— Origen —</option>' );
            $to.empty().append( '<option value="">— Destino —</option>' );

            $.each( v.areas || [], function ( i, a ) {
                $from.append( $( '<option>' ).val( a.id ).text( a.name ) );
            } );

            loadAreasDropdown( $to, [] );
        } );

        openModal( '#aura-veh-modal-transfer' );
    }

    // ══════════════════════════════════════════════════════════════
    // MODAL: Fotos
    // ══════════════════════════════════════════════════════════════

    function openPhotosModal( vehicleId ) {
        var $modal = $( '#aura-veh-modal-photos' );
        $modal.find( '#aura-veh-photos-vehicle-id' ).val( vehicleId );
        $modal.find( '#aura-veh-photos-msg' ).hide().text( '' );
        $modal.find( '#aura-veh-photo-upload-btn' ).hide();
        $modal.find( '#aura-veh-photo-filename' ).text( '' );
        $modal.find( '#aura-veh-photo-file' ).val( '' );

        loadPhotosGallery( vehicleId );
        openModal( '#aura-veh-modal-photos' );
    }

    function loadPhotosGallery( vehicleId ) {
        api( API + '/' + vehicleId, 'GET' ).done( function ( v ) {
            var $gallery = $( '#aura-veh-photos-gallery' ).empty();
            var photos   = v.photos || [];

            if ( ! photos.length ) {
                $gallery.html( '<p style="color:#999;">Sin fotos.</p>' );
                return;
            }

            $.each( photos, function ( i, p ) {
                var url      = ( typeof p === 'object' ) ? p.url : p;
                var filename = ( typeof p === 'object' ) ? p.filename : p;
                var $item = $( '<div class="aura-veh-photo-item">' );
                var $img  = $( '<img>' ).attr( { src: url, alt: '' } ).css( { width: '120px', height: '90px', objectFit: 'cover', borderRadius: '4px', display: 'block' } );
                $item.append( $img );

                if ( CFG.canEdit ) {
                    var $del = $( '<button type="button" title="Eliminar foto">' )
                        .addClass( 'button button-small button-link-delete' )
                        .css( { marginTop: '4px', width: '100%' } )
                        .html( '<span class="dashicons dashicons-trash"></span>' )
                        .on( 'click', function () {
                            if ( confirm( '¿Eliminar esta foto?' ) ) {
                                deletePhoto( vehicleId, filename );
                            }
                        } );
                    $item.append( $del );
                }

                $gallery.append( $item );
            } );
        } );
    }

    function deletePhoto( vehicleId, filename ) {
        api( API + '/' + vehicleId + '/photos', 'DELETE', { filename: filename } ).done( function () {
            showMsg( $( '#aura-veh-photos-msg' ), TXT.photo_deleted, false );
            loadPhotosGallery( vehicleId );
            loadVehicles();
        } ).fail( function ( xhr ) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
            showMsg( $( '#aura-veh-photos-msg' ), msg, true );
        } );
    }

    // ══════════════════════════════════════════════════════════════
    // MODAL: Eliminar
    // ══════════════════════════════════════════════════════════════

    function openDeleteModal( vehicleId ) {
        $( '#aura-veh-delete-vehicle-id' ).val( vehicleId );
        openModal( '#aura-veh-modal-delete' );
    }

    // ── Notificación admin ────────────────────────────────────────

    function showAdminNotice( msg, type ) {
        var cls   = type === 'error' ? 'notice-error' : 'notice-success';
        var $note = $( '<div class="notice ' + cls + ' is-dismissible"><p>' + escHtml( msg ) + '</p></div>' );
        $( '.wp-header-end' ).after( $note );
        setTimeout( function () { $note.fadeOut( function () { $note.remove(); } ); }, 4000 );
    }

    // ══════════════════════════════════════════════════════════════
    // INIT — Eventos
    // ══════════════════════════════════════════════════════════════

    $( function () {

        // Inicializar DataTables
        try {
            initTable();
        } catch ( e ) {
            // DataTables no disponible (CDN bloqueado, conflicto, etc.)
            // Los handlers de eventos siguen funcionando
            console.error( 'DataTables init error:', e );
        }
        loadVehicles();

        // Precargar áreas para los selects
        api( CFG.apiBase + 'vehicles/areas-dropdown', 'GET' ).done( function ( res ) {
            _allAreas = res.items || [];

            // Poblar filtro de áreas si #aura-veh-filter-area existe
            var $filterArea = $( '#aura-veh-filter-area' );
            if ( $filterArea.length && ! $filterArea.find( 'option' ).length ) {
                fillAreaSelect( $filterArea, [] );
            }
        } );

        // ── Nuevo vehículo ────────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-btn-create', function () {
            openFormModal( null );
        } );

        // ── Acciones de la tabla ──────────────────────────────────
        $( document ).on( 'click', '.aura-veh-action', function () {
            var $btn   = $( this );
            var action = $btn.data( 'action' );
            var id     = $btn.data( 'id' );

            switch ( action ) {
                case 'view':
                    openViewModal( id );
                    break;
                case 'edit':
                    openFormModal( id );
                    break;
                case 'areas':
                    openAreasModal( id );
                    break;
                case 'photos':
                    openPhotosModal( id );
                    break;
                case 'unavailable':
                    openUnavailableModal( id );
                    break;
                case 'restore':
                    if ( confirm( '¿Restaurar este vehículo?' ) ) {
                        api( API + '/' + id + '/restore', 'POST' ).done( function () {
                            showAdminNotice( TXT.restored, 'success' );
                            loadVehicles();
                        } ).fail( function () { alert( TXT.error ); } );
                    }
                    break;
                case 'transfer':
                    openTransferModal( id );
                    break;
                case 'qr':
                    openQrModal( id, $btn.data( 'plate' ) + ' — ' + $btn.data( 'name' ) );
                    break;
                case 'delete':
                    openDeleteModal( id );
                    break;
            }
        } );

        // ── Lightbox de imagen en modal detalle ────────────────
        $( document ).on( 'click', '.aura-veh-view-photo-img', function () {
            var src = $( this ).data( 'full' ) || $( this ).attr( 'src' );
            if ( ! src ) {
                return;
            }
            var idx = parseInt( $( this ).data( 'index' ), 10 );
            if ( isNaN( idx ) ) {
                idx = 0;
            }
            _lightboxIndex = idx;
            updateLightboxImage( src );
            openModal( '#aura-veh-modal-lightbox' );
        } );

        $( document ).on( 'click', '#aura-veh-lightbox-prev', function () {
            if ( _lightboxImages.length <= 1 ) {
                return;
            }
            _lightboxIndex = ( _lightboxIndex - 1 + _lightboxImages.length ) % _lightboxImages.length;
            updateLightboxImage( _lightboxImages[ _lightboxIndex ] );
        } );

        $( document ).on( 'click', '#aura-veh-lightbox-next', function () {
            if ( _lightboxImages.length <= 1 ) {
                return;
            }
            _lightboxIndex = ( _lightboxIndex + 1 ) % _lightboxImages.length;
            updateLightboxImage( _lightboxImages[ _lightboxIndex ] );
        } );

        // ── Guardar formulario ────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-form-submit', submitForm );

        // ── Asignar área ──────────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-area-assign-btn', function () {
            var vehicleId = $( '#aura-veh-areas-vehicle-id' ).val();
            var areaId    = $( '#aura-veh-area-select' ).val();

            if ( ! areaId ) {
                showMsg( $( '#aura-veh-areas-msg' ), 'Elige un área.', true );
                return;
            }

            api( API + '/' + vehicleId + '/areas', 'POST', { area_id: parseInt( areaId ) } ).done( function () {
                showMsg( $( '#aura-veh-areas-msg' ), TXT.area_assigned, false );
                reloadAreasInModal( vehicleId );
                loadVehicles();
            } ).fail( function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
                showMsg( $( '#aura-veh-areas-msg' ), msg, true );
            } );
        } );

        // ── Dar de baja ───────────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-unavail-submit', function () {
            var vehicleId = $( '#aura-veh-unavail-vehicle-id' ).val();
            var reason    = $( '#aura-veh-unavail-reason' ).val().trim();
            var notes     = $( '#aura-veh-unavail-notes' ).val().trim();

            if ( ! reason ) {
                showMsg( $( '#aura-veh-unavail-msg' ), 'El motivo es obligatorio.', true );
                return;
            }

            api( API + '/' + vehicleId + '/unavailable', 'POST', { reason: reason, notes: notes } ).done( function () {
                closeAllModals();
                showAdminNotice( TXT.unavailable_done, 'success' );
                loadVehicles();
            } ).fail( function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
                showMsg( $( '#aura-veh-unavail-msg' ), msg, true );
            } );
        } );

        // ── Transferir ────────────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-transfer-submit', function () {
            var vehicleId = $( '#aura-veh-transfer-vehicle-id' ).val();
            var from      = $( '#aura-veh-transfer-from' ).val();
            var to        = $( '#aura-veh-transfer-to' ).val();

            if ( ! from || ! to ) {
                showMsg( $( '#aura-veh-transfer-msg' ), 'Selecciona origen y destino.', true );
                return;
            }
            if ( from === to ) {
                showMsg( $( '#aura-veh-transfer-msg' ), 'El origen y destino no pueden ser iguales.', true );
                return;
            }

            api( API + '/' + vehicleId + '/transfer', 'POST', { from_area: parseInt( from ), to_area: parseInt( to ) } ).done( function () {
                closeAllModals();
                showAdminNotice( TXT.transferred, 'success' );
                loadVehicles();
            } ).fail( function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
                showMsg( $( '#aura-veh-transfer-msg' ), msg, true );
            } );
        } );

        // ── Confirmar eliminar ────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-delete-confirm', function () {
            var vehicleId = $( '#aura-veh-delete-vehicle-id' ).val();

            api( API + '/' + vehicleId, 'DELETE' ).done( function () {
                closeAllModals();
                showAdminNotice( TXT.deleted, 'success' );
                loadVehicles();
            } ).fail( function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
                closeAllModals();
                showAdminNotice( msg, 'error' );
            } );
        } );

        // ── Fotos: seleccionar archivo ────────────────────────────
        $( document ).on( 'change', '#aura-veh-photo-file', function () {
            var file = this.files[0];
            if ( file ) {
                $( '#aura-veh-photo-filename' ).text( file.name );
                $( '#aura-veh-photo-upload-btn' ).show();
            } else {
                $( '#aura-veh-photo-filename' ).text( '' );
                $( '#aura-veh-photo-upload-btn' ).hide();
            }
        } );

        // ── Fotos: subir ──────────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-photo-upload-btn', function () {
            var vehicleId = $( '#aura-veh-photos-vehicle-id' ).val();
            var fileInput = $( '#aura-veh-photo-file' )[0];

            if ( ! fileInput.files.length ) return;

            var fd = new FormData();
            fd.append( 'photo', fileInput.files[0] );

            var $btn = $( this ).prop( 'disabled', true ).text( '…' );

            api( API + '/' + vehicleId + '/photos', 'POST', fd, true ).done( function () {
                showMsg( $( '#aura-veh-photos-msg' ), TXT.photo_uploaded, false );
                $( '#aura-veh-photo-file' ).val( '' );
                $( '#aura-veh-photo-filename' ).text( '' );
                $( '#aura-veh-photo-upload-btn' ).hide();
                loadPhotosGallery( vehicleId );
                loadVehicles();
            } ).fail( function ( xhr ) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : TXT.error;
                showMsg( $( '#aura-veh-photos-msg' ), msg, true );
            } ).always( function () {
                $btn.prop( 'disabled', false ).html( 'Subir' );
            } );
        } );

        // ── Filtros ───────────────────────────────────────────────
        $( document ).on( 'click', '#aura-veh-filter-apply', function () {
            _filters.search  = $( '#aura-veh-search' ).val();
            _filters.status  = $( '#aura-veh-filter-status' ).val();
            _filters.type    = $( '#aura-veh-filter-type' ).val();
            _filters.area_id = $( '#aura-veh-filter-area' ).val() || 0;
            loadVehicles();
        } );

        $( document ).on( 'click', '#aura-veh-filter-clear', function () {
            $( '#aura-veh-search' ).val( '' );
            $( '#aura-veh-filter-status' ).val( '' );
            $( '#aura-veh-filter-type' ).val( '' );
            $( '#aura-veh-filter-area' ).val( '' );
            _filters = { search: '', status: '', type: '', area_id: 0 };
            loadVehicles();
        } );

        $( '#aura-veh-search' ).on( 'keypress', function ( e ) {
            if ( e.which === 13 ) $( '#aura-veh-filter-apply' ).trigger( 'click' );
        } );

        // ── Cerrar modales ────────────────────────────────────────
        $( document ).on( 'click', '.aura-veh-modal-close, .aura-veh-modal-overlay', closeAllModals );

        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) closeAllModals();
        } );

        // ── Foto principal: Seleccionar desde Media Library ───────
        $( document ).on( 'click', '#aura-veh-photo-select-btn', function () {
            if ( typeof wp === 'undefined' || ! wp.media ) {
                alert( 'La librería de medios no está disponible.' );
                return;
            }

            var frame = wp.media( {
                title   : 'Seleccionar foto del vehículo',
                button  : { text: 'Usar esta imagen' },
                multiple: false,
                library : { type: 'image' },
            } );

            frame.on( 'select', function () {
                var att = frame.state().get( 'selection' ).first().toJSON();
                _cropAttachId = att.id;
                openVehCropModal( att.url );
            } );

            frame.open();
        } );

        // Quitar foto principal
        $( document ).on( 'click', '#aura-veh-photo-remove-btn', function () {
            $( '#veh-photo' ).val( 0 );
            $( '#aura-veh-photo-preview' ).empty();
            $( this ).hide();
        } );

        // ── Cropper.js: Abrir modal ───────────────────────────────
        function openVehCropModal( imgUrl ) {
            var $img = $( '#aura-veh-crop-img' );
            $img.attr( 'src', imgUrl );
            $( '#aura-veh-crop-msg' ).hide().text( '' );

            if ( _cropperInst ) {
                _cropperInst.destroy();
                _cropperInst = null;
            }

            $( '#aura-veh-crop-modal' ).show();
            $( 'body' ).addClass( 'aura-veh-modal-open' );

            // Esperar a que la imagen cargue antes de inicializar Cropper
            $img.off( 'load.vehcrop' ).on( 'load.vehcrop', function () {
                _cropperInst = new Cropper( this, {
                    aspectRatio  : 4 / 3,
                    viewMode     : 1,
                    dragMode     : 'move',
                    autoCropArea : 1,
                    restore      : false,
                    guides       : true,
                    center       : true,
                    highlight    : true,
                    cropBoxMovable  : true,
                    cropBoxResizable: true,
                } );
            } );

            // Disparar load si la imagen ya está cacheada
            if ( $img[0].complete && $img[0].naturalWidth ) {
                $img.trigger( 'load.vehcrop' );
            }
        }

        // ── Cropper.js: Aplicar recorte ───────────────────────────
        $( document ).on( 'click', '#aura-veh-crop-apply', function () {
            if ( ! _cropperInst ) return;

            var $btn  = $( this ).prop( 'disabled', true ).text( '…' );
            var $msg  = $( '#aura-veh-crop-msg' );
            var data  = _cropperInst.getData( true );

            $.ajax( {
                url     : CFG.ajaxurl,
                method  : 'POST',
                data    : {
                    action       : 'aura_vehicle_crop_photo',
                    nonce        : CFG.vehNonce,
                    attachment_id: _cropAttachId,
                    x            : data.x,
                    y            : data.y,
                    width        : data.width,
                    height       : data.height,
                },
            } ).done( function ( res ) {
                if ( res.success ) {
                    var d = res.data;
                    $( '#veh-photo' ).val( d.attachment_id );
                    $( '#aura-veh-photo-preview' ).html(
                        '<img src="' + escHtml( d.thumb_url ) + '" alt="" style="max-height:80px;border-radius:4px;display:block;">'
                    );
                    $( '#aura-veh-photo-remove-btn' ).show();

                    if ( _cropperInst ) { _cropperInst.destroy(); _cropperInst = null; }
                    $( '#aura-veh-crop-modal' ).hide();
                    $( 'body' ).removeClass( 'aura-veh-modal-open' );
                } else {
                    var errMsg = ( res.data && res.data.message ) ? res.data.message : 'Error al procesar la imagen.';
                    $msg.html( '<p class="notice-error" style="padding:6px 10px;margin:0;">' + escHtml( errMsg ) + '</p>' ).show();
                }
            } ).fail( function () {
                $msg.html( '<p class="notice-error" style="padding:6px 10px;margin:0;">Error de conexión.</p>' ).show();
            } ).always( function () {
                $btn.prop( 'disabled', false ).text( 'Aplicar recorte' );
            } );
        } );

        // Cerrar crop modal limpia el cropper
        $( document ).on( 'click', '#aura-veh-crop-modal .aura-veh-modal-close, #aura-veh-crop-modal .aura-veh-modal-overlay', function () {
            if ( _cropperInst ) { _cropperInst.destroy(); _cropperInst = null; }
            $( '#aura-veh-crop-modal' ).hide();
            $( 'body' ).removeClass( 'aura-veh-modal-open' );
        } );

    } );

    // ══════════════════════════════════════════════════════════════
    // MODAL: Código QR
    // ══════════════════════════════════════════════════════════════

    var _qrInstance = null;  // QRCode instance actual
    var _qrUrl      = '';    // URL actual del QR
    var _qrVehicleId = 0;

    function openQrModal( vehicleId, vehicleName ) {
        _qrVehicleId = vehicleId;
        var $modal = $( '#aura-veh-modal-qr' );

        $modal.find( '#aura-veh-qr-vehicle-id' ).val( vehicleId );
        $modal.find( '#aura-veh-qr-vehicle-name' ).text( vehicleName );
        $modal.find( '#aura-veh-qr-loader' ).show();
        $modal.find( '#aura-veh-qr-canvas-wrap' ).hide();
        $modal.find( '#aura-veh-qr-empty' ).hide();
        $modal.find( '#aura-veh-qr-actions' ).hide();
        $modal.find( '#aura-veh-qr-msg' ).hide();
        $modal.find( '#aura-veh-qr-generate' ).hide();

        openModal( '#aura-veh-modal-qr' );

        // Consultar QR existente (idempotente)
        api( CFG.apiBase + 'vehicles/' + vehicleId + '/qr', 'GET' ).done( function ( res ) {
            $modal.find( '#aura-veh-qr-loader' ).hide();

            if ( res.has_qr ) {
                renderQrCode( res.qr_token, res.qr_url );
            } else {
                // Sin QR: mostrar botón generar
                $modal.find( '#aura-veh-qr-empty' ).show();
                $modal.find( '#aura-veh-qr-generate' ).show();
            }
        } ).fail( function () {
            $modal.find( '#aura-veh-qr-loader' ).hide();
            $modal.find( '#aura-veh-qr-empty' ).show();
            $modal.find( '#aura-veh-qr-generate' ).show();
        } );
    }

    function renderQrCode( token, url ) {
        _qrUrl = url;
        var $wrap = $( '#aura-veh-qr-canvas-wrap' );
        var $canvas = $( '#aura-veh-qr-canvas' ).empty();

        // Generar QR con QRCode.js
        if ( typeof QRCode !== 'undefined' ) {
            _qrInstance = new QRCode( $canvas[0], {
                text        : url,
                width       : 220,
                height      : 220,
                colorDark   : '#1a1a2e',
                colorLight  : '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            } );
        }

        $( '#aura-veh-qr-url-text' ).text( url );
        $wrap.show();
        $( '#aura-veh-qr-actions' ).css( 'display', 'flex' ).show();
    }

    function updateLightboxImage( src ) {
        $( '#aura-veh-lightbox-img' ).attr( 'src', src || '' );

        var hasGallery = _lightboxImages.length > 1;
        $( '#aura-veh-lightbox-prev, #aura-veh-lightbox-next' ).prop( 'disabled', ! hasGallery );

        if ( _lightboxImages.length ) {
            $( '#aura-veh-lightbox-counter' ).text( ( _lightboxIndex + 1 ) + ' / ' + _lightboxImages.length );
        } else {
            $( '#aura-veh-lightbox-counter' ).text( '1 / 1' );
        }

        $( '#aura-veh-lightbox-thumbs .aura-veh-lightbox-thumb' ).removeClass( 'is-active' );
        $( '#aura-veh-lightbox-thumbs .aura-veh-lightbox-thumb[data-index="' + _lightboxIndex + '"]' ).addClass( 'is-active' );
    }

    function renderLightboxThumbs() {
        var $wrap = $( '#aura-veh-lightbox-thumbs' ).empty();

        if ( ! _lightboxImages.length ) {
            return;
        }

        $.each( _lightboxImages, function ( idx, url ) {
            var $btn = $( '<button type="button" class="aura-veh-lightbox-thumb" aria-label="Ir a foto"></button>' )
                .attr( 'data-index', idx )
                .append( $( '<img alt="">' ).attr( 'src', url ) );

            if ( idx === _lightboxIndex ) {
                $btn.addClass( 'is-active' );
            }

            $wrap.append( $btn );
        } );
    }

    $( document ).on( 'click', '.aura-veh-lightbox-thumb', function () {
        var idx = parseInt( $( this ).data( 'index' ), 10 );
        if ( isNaN( idx ) || !_lightboxImages[ idx ] ) {
            return;
        }

        _lightboxIndex = idx;
        updateLightboxImage( _lightboxImages[ _lightboxIndex ] );
    } );

    $( document ).on( 'keydown', function ( e ) {
        if ( $( '#aura-veh-modal-lightbox' ).is( ':visible' ) ) {
            if ( e.key === 'ArrowLeft' ) {
                $( '#aura-veh-lightbox-prev' ).trigger( 'click' );
            }
            if ( e.key === 'ArrowRight' ) {
                $( '#aura-veh-lightbox-next' ).trigger( 'click' );
            }
        }
    } );

    // Generar QR (primera vez)
    $( document ).on( 'click', '#aura-veh-qr-generate', function () {
        var vehicleId = $( '#aura-veh-qr-vehicle-id' ).val();
        var $btn = $( this ).prop( 'disabled', true ).text( '…' );

        api( CFG.apiBase + 'vehicles/' + vehicleId + '/qr', 'POST' ).done( function ( res ) {
            $( '#aura-veh-qr-empty' ).hide();
            $( '#aura-veh-qr-generate' ).hide();
            renderQrCode( res.qr_token, res.qr_url );
        } ).fail( function () {
            showMsg( $( '#aura-veh-qr-msg' ), 'Error al generar el QR.', true );
        } ).always( function () {
            $btn.prop( 'disabled', false ).text( 'Generar QR' );
        } );
    } );

    // Invalidar / regenerar QR
    $( document ).on( 'click', '#aura-veh-qr-invalidate', function () {
        if ( ! confirm( '¿Regenerar el QR? El código impreso anterior quedará inválido.' ) ) { return; }
        var vehicleId = $( '#aura-veh-qr-vehicle-id' ).val();
        var $btn = $( this ).prop( 'disabled', true ).text( '…' );

        $( '#aura-veh-qr-canvas' ).empty();
        _qrInstance = null;

        api( CFG.apiBase + 'vehicles/' + vehicleId + '/qr', 'DELETE' ).done( function ( res ) {
            renderQrCode( res.qr_token, res.qr_url );
            showMsg( $( '#aura-veh-qr-msg' ), 'QR regenerado. El anterior ya no es válido.', false );
        } ).fail( function () {
            showMsg( $( '#aura-veh-qr-msg' ), 'Error al regenerar el QR.', true );
        } ).always( function () {
            $btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update" style="vertical-align:middle;"></span> Regenerar QR' );
        } );
    } );

    // Descargar QR como PNG
    $( document ).on( 'click', '#aura-veh-qr-download', function () {
        var $img = $( '#aura-veh-qr-canvas img' ).first();
        if ( ! $img.length ) { return; }
        var a = document.createElement( 'a' );
        a.href     = $img.attr( 'src' );
        a.download = 'qr-vehiculo-' + $( '#aura-veh-qr-vehicle-id' ).val() + '.png';
        a.click();
    } );

    // Copiar URL al portapapeles
    $( document ).on( 'click', '#aura-veh-qr-copy', function () {
        if ( _qrUrl && navigator.clipboard ) {
            navigator.clipboard.writeText( _qrUrl ).then( function () {
                showMsg( $( '#aura-veh-qr-msg' ), 'URL copiada al portapapeles.', false );
            } );
        }
    } );

    // Imprimir QR
    $( document ).on( 'click', '#aura-veh-qr-print', function () {
        var $img = $( '#aura-veh-qr-canvas img' ).first();
        if ( ! $img.length ) { return; }
        var vehicleName = $( '#aura-veh-qr-vehicle-name' ).text();
        var w = window.open( '', '_blank', 'width=400,height=500' );
        w.document.write(
            '<!DOCTYPE html><html><head><title>QR Vehículo</title><style>'
            + 'body{font-family:sans-serif;text-align:center;padding:20px;}'
            + 'h2{font-size:16px;margin:0 0 12px;}'
            + 'img{display:block;margin:0 auto;border:1px solid #ddd;padding:10px;}'
            + 'p{font-size:11px;word-break:break-all;color:#555;margin-top:10px;}'
            + '</style></head><body>'
            + '<h2>' + vehicleName + '</h2>'
            + '<img src="' + $img.attr( 'src' ) + '" width="220" height="220">'
            + '<p>' + _qrUrl + '</p>'
            + '<script>window.onload=function(){window.print();window.close();}<\/script>'
            + '</body></html>'
        );
        w.document.close();
    } );

}( jQuery ) );
