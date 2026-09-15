/* global auraUSD */
jQuery( function ( $ ) {
    'use strict';

    // ── Helpers ──────────────────────────────────────────────────────

    function showFeedback( $el, msg, isSuccess ) {
        $el.removeClass( 'is-success is-error' )
           .addClass( isSuccess ? 'is-success' : 'is-error' )
           .html( msg )
           .show();
    }

    function updateBalanceDisplay( balance ) {
        var formatted = '$' + parseFloat( balance ).toLocaleString( 'es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        } ) + ' USD';
        $( '#usd-balance-display' ).text( formatted );
        $( '#conv-available' ).text( formatted );
        $( '#usd-footer-balance' ).text( formatted );
    }

    function typeBadge( type ) {
        var labels = {
            opening:    auraUSD.i18n.opening    || 'Apertura',
            deposit:    auraUSD.i18n.deposit    || 'Depósito',
            conversion: auraUSD.i18n.conversion || 'Conversión'
        };
        return '<span class="aura-usd-badge aura-usd-badge--' + type + '">'
             + ( labels[ type ] || type ) + '</span>';
    }

    function formatUSD( n ) {
        return '$' + parseFloat( n ).toLocaleString( 'es-MX', {
            minimumFractionDigits: 4, maximumFractionDigits: 4
        } ) + ' USD';
    }

    function formatMXN( n ) {
        if ( ! n ) return '—';
        return '$' + parseFloat( n ).toLocaleString( 'es-MX', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        } ) + ' MXN';
    }

    // ── Calculadora en tiempo real ────────────────────────────────────

    function updatePreview() {
        var usd  = parseFloat( $( '#conv-usd' ).val() ) || 0;
        var rate = parseFloat( $( '#conv-rate' ).val() ) || 0;
        if ( usd > 0 && rate > 0 ) {
            var mxn = ( usd * rate ).toLocaleString( 'es-MX', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            } );
            $( '#conv-mxn-preview' ).text( '$' + mxn + ' MXN' );
            $( '#conv-preview' ).show();
        } else {
            $( '#conv-preview' ).hide();
        }
    }

    $( '#conv-usd, #conv-rate' ).on( 'input', updatePreview );

    // ── Cargar historial ─────────────────────────────────────────────

    function loadHistory() {
        $( '#usd-history-body' ).html(
            '<tr><td colspan="8" class="aura-usd-loading">'
            + '<span class="spinner is-active" style="float:none;margin:0 4px;"></span> Cargando...</td></tr>'
        );
        $.post( auraUSD.ajaxUrl, {
            action: 'aura_usd_get_history',
            nonce:  auraUSD.nonce
        }, function ( res ) {
            if ( ! res.success ) {
                $( '#usd-history-body' ).html(
                    '<tr><td colspan="8" style="color:red;">Error al cargar.</td></tr>'
                );
                return;
            }
            updateBalanceDisplay( res.data.balance );

            var cols = window.auraUSD.isAdmin ? 8 : 7;
            if ( ! res.data.rows || res.data.rows.length === 0 ) {
                $( '#usd-history-body' ).html(
                    '<tr><td colspan="' + cols + '" style="text-align:center;color:#888;">Sin movimientos registrados.</td></tr>'
                );
                return;
            }

            var html = '';
            $.each( res.data.rows, function ( i, row ) {
                var txLink = '';
                if ( row.entry_type === 'conversion' && row.transaction_id ) {
                    txLink = '<a class="aura-usd-mxn-link" href="'
                           + auraUSD.adminUrl + 'admin.php?page=aura-financial-transactions'
                           + '" target="_blank">Ver transacción MXN #' + row.transaction_id + '</a>';
                }

                var rateCell = row.exchange_rate
                    ? '$' + parseFloat( row.exchange_rate ).toFixed( 4 )
                    : '—';

                var sign = row.entry_type === 'conversion' ? '−' : '+';
                var usdClass = row.entry_type === 'conversion' ? 'color:#c0392b' : 'color:#27ae60';

                html += '<tr>';
                html += '<td>' + ( row.created_at ? row.created_at.substring( 0, 10 ) : '—' ) + '</td>';
                html += '<td>' + typeBadge( row.entry_type ) + '</td>';
                html += '<td class="num" style="' + usdClass + '">'
                      + sign + ' ' + formatUSD( row.usd_amount ) + '</td>';
                html += '<td class="num">' + rateCell + '</td>';
                html += '<td class="num">' + formatMXN( row.mxn_amount ) + txLink + '</td>';
                html += '<td class="notes-cell" title="' + ( row.notes || '' ) + '">'
                      + ( row.notes || '—' ) + '</td>';
                html += '<td>' + ( row.display_name || '—' ) + '</td>';

                if ( window.auraUSD.isAdmin ) {
                    html += '<td><button class="button button-small btn-delete-entry" '
                          + 'data-id="' + row.id + '" style="color:#c0392b;">'
                          + '<span class="dashicons dashicons-trash"></span></button></td>';
                }

                html += '</tr>';
            } );

            $( '#usd-history-body' ).html( html );
        } );
    }

    loadHistory();
    $( '#btn-refresh-history' ).on( 'click', loadHistory );

    // ── Formulario: Depósito / Apertura ──────────────────────────────

    $( '#form-usd-deposit' ).on( 'submit', function ( e ) {
        e.preventDefault();
        var $btn      = $( '#btn-deposit' );
        var $feedback = $( '#deposit-feedback' );

        $btn.prop( 'disabled', true ).text( auraUSD.i18n.saving );
        $feedback.hide();

        $.post( auraUSD.ajaxUrl, {
            action:     'aura_usd_set_balance',
            nonce:      auraUSD.nonce,
            usd_amount: $( '#deposit-usd' ).val(),
            entry_type: $( '#deposit-type' ).val(),
            entry_date: $( '#deposit-date' ).val(),
            notes:      $( '#deposit-notes' ).val()
        }, function ( res ) {
            $btn.prop( 'disabled', false ).html(
                '<span class="dashicons dashicons-plus"></span> Registrar USD'
            );
            if ( res.success ) {
                showFeedback( $feedback, res.data.message, true );
                updateBalanceDisplay( res.data.balance );
                $( '#form-usd-deposit' ).trigger( 'reset' );
                $( '#deposit-date' ).val( new Date().toISOString().slice( 0, 10 ) );
                loadHistory();
            } else {
                showFeedback( $feedback, res.data.message || auraUSD.i18n.error, false );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            showFeedback( $feedback, auraUSD.i18n.error, false );
        } );
    } );

    // ── Formulario: Conversión USD → MXN ─────────────────────────────

    $( '#form-usd-convert' ).on( 'submit', function ( e ) {
        e.preventDefault();
        var $btn      = $( '#btn-convert' );
        var $feedback = $( '#convert-feedback' );

        $btn.prop( 'disabled', true ).text( auraUSD.i18n.saving );
        $feedback.hide();

        $.post( auraUSD.ajaxUrl, {
            action:          'aura_usd_convert',
            nonce:           auraUSD.nonce,
            usd_amount:      $( '#conv-usd' ).val(),
            exchange_rate:   $( '#conv-rate' ).val(),
            conversion_date: $( '#conv-date' ).val(),
            notes:           $( '#conv-notes' ).val()
        }, function ( res ) {
            $btn.prop( 'disabled', false ).html(
                '<span class="dashicons dashicons-update"></span> Registrar Conversión'
            );
            if ( res.success ) {
                showFeedback( $feedback, res.data.message, true );
                updateBalanceDisplay( res.data.balance );
                $( '#form-usd-convert' ).trigger( 'reset' );
                $( '#conv-date' ).val( new Date().toISOString().slice( 0, 10 ) );
                $( '#conv-preview' ).hide();
                loadHistory();
            } else {
                showFeedback( $feedback, res.data.message || auraUSD.i18n.error, false );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            showFeedback( $feedback, auraUSD.i18n.error, false );
        } );
    } );

    // ── Eliminar entrada (solo admin) ─────────────────────────────────

    $( '#usd-history-body' ).on( 'click', '.btn-delete-entry', function () {
        if ( ! confirm( auraUSD.i18n.confirmDelete ) ) return;
        var id  = $( this ).data( 'id' );
        var $tr = $( this ).closest( 'tr' );
        $tr.css( 'opacity', '.4' );

        $.post( auraUSD.ajaxUrl, {
            action:   'aura_usd_delete_entry',
            nonce:    auraUSD.nonce,
            entry_id: id
        }, function ( res ) {
            if ( res.success ) {
                updateBalanceDisplay( res.data.balance );
                $tr.remove();
            } else {
                $tr.css( 'opacity', '1' );
                alert( res.data.message || auraUSD.i18n.error );
            }
        } );
    } );
} );
