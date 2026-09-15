/**
 * forms-admin.js — Módulo de Formularios y Encuestas
 * Lógica general del backend: listados, duplicar, eliminar, copiar URL.
 * Fase 2 — implementación completa.
 *
 * @package AuraBusinessSuite
 */
/* global auraFormsAdmin, jQuery */
( function ( $ ) {
    'use strict';

    const { ajaxUrl, nonce, i18n } = auraFormsAdmin;

    // ── Helpers ────────────────────────────────────────────────

    function showNotice( message, type ) {
        type = type || 'success';
        const $notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );
        const $after = $( '.wp-header-end' );
        if ( $after.length ) {
            $after.after( $notice );
        } else {
            $( '.wrap h1' ).first().after( $notice );
        }
        setTimeout( function () { $notice.fadeOut( 400, function () { $notice.remove(); } ); }, 4000 );
    }

    // ── Duplicar formulario ────────────────────────────────────

    $( document ).on( 'click', '.aura-forms-duplicate', function ( e ) {
        e.preventDefault();
        var $btn = $( this );
        var id   = $btn.data( 'id' );

        if ( ! window.confirm( '¿Duplicar este formulario?' ) ) return;

        $btn.text( '…' ).prop( 'disabled', true );

        $.post( ajaxUrl, { action: 'aura_forms_duplicate', id: id, nonce: nonce } )
            .done( function ( res ) {
                if ( res.success ) {
                    showNotice( res.data.message );
                    setTimeout( function () {
                        window.location.href = 'admin.php?page=aura-forms-list&action=edit&id=' + res.data.new_form_id;
                    }, 800 );
                } else {
                    showNotice( ( res.data && res.data.message ) || i18n.error, 'error' );
                    $btn.text( 'Duplicar' ).prop( 'disabled', false );
                }
            } )
            .fail( function () {
                showNotice( i18n.error, 'error' );
                $btn.text( 'Duplicar' ).prop( 'disabled', false );
            } );
    } );

    // ── Eliminar formulario ────────────────────────────────────

    $( document ).on( 'click', '.aura-forms-delete', function ( e ) {
        e.preventDefault();
        var $btn = $( this );
        var id   = $btn.data( 'id' );

        if ( ! window.confirm( i18n.confirmDelete ) ) return;

        $btn.text( '…' ).prop( 'disabled', true );

        $.post( ajaxUrl, { action: 'aura_forms_delete', id: id, nonce: nonce } )
            .done( function ( res ) {
                if ( res.success ) {
                    var $row = $btn.closest( 'tr' );
                    $row.fadeOut( 300, function () { $row.remove(); } );
                    showNotice( res.data.message );
                } else {
                    showNotice( ( res.data && res.data.message ) || i18n.error, 'error' );
                    $btn.text( 'Eliminar' ).prop( 'disabled', false );
                }
            } )
            .fail( function () {
                showNotice( i18n.error, 'error' );
                $btn.text( 'Eliminar' ).prop( 'disabled', false );
            } );
    } );

    // ── Copiar URL al portapapeles (enlace de texto en row-actions) ────────────

    $( document ).on( 'click', '.aura-forms-copy-url', function ( e ) {
        e.preventDefault();
        var url = $( this ).data( 'url' );
        copyToClipboard( url );
    } );

    // ── Helpers de portapapeles ────────────────────────────────

    function copyToClipboard( url, $btn ) {
        if ( navigator.clipboard ) {
            navigator.clipboard.writeText( url ).then( function () {
                showNotice( i18n.copied );
                if ( $btn ) {
                    $btn.addClass( 'copied' );
                    $btn.find( '.dashicons' ).removeClass( 'dashicons-clipboard' ).addClass( 'dashicons-yes' );
                    setTimeout( function () {
                        $btn.removeClass( 'copied' );
                        $btn.find( '.dashicons' ).removeClass( 'dashicons-yes' ).addClass( 'dashicons-clipboard' );
                    }, 2000 );
                }
            } );
        } else {
            var $tmp = $( '<textarea>' ).css( { position: 'absolute', left: '-9999px' } );
            $( 'body' ).append( $tmp );
            $tmp.val( url ).select();
            document.execCommand( 'copy' );
            $tmp.remove();
            showNotice( i18n.copied );
        }
    }

    // ── Botón redondo: Copiar URL ──────────────────────────────

    $( document ).on( 'click', '.aura-url-copy-btn', function ( e ) {
        e.preventDefault();
        e.stopPropagation();
        copyToClipboard( $( this ).data( 'url' ), $( this ) );
    } );

    // ── Botón redondo: Generar código QR ──────────────────────

    var $qrModal  = null;
    var qrCodeObj = null;
    var qrActiveUrl = '';

    function ensureQrModal() {
        if ( ! $qrModal ) {
            $qrModal = $( '#aura-qr-modal' );
        }
        return $qrModal;
    }

    function openQrModal( url, title ) {
        var $modal = ensureQrModal();
        qrActiveUrl = url;
        $modal.find( '.aura-qr-form-title' ).text( title );
        $modal.find( '.aura-qr-url' ).text( url );
        $( '#aura-qr-canvas' ).empty();
        qrCodeObj = null;

        if ( typeof QRCode !== 'undefined' ) {
            qrCodeObj = new QRCode( document.getElementById( 'aura-qr-canvas' ), {
                text:         url,
                width:        240,
                height:       240,
                colorDark:    '#1e293b',
                colorLight:   '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            } );
        } else {
            $( '#aura-qr-canvas' ).html( '<p class="aura-qr-no-lib">La biblioteca QR no est\u00e1 disponible. Verifica la conexi\u00f3n a internet.</p>' );
        }

        $modal.fadeIn( 150 );
        $( 'body' ).addClass( 'aura-qr-open' );
    }

    function closeQrModal() {
        ensureQrModal().fadeOut( 150 );
        $( 'body' ).removeClass( 'aura-qr-open' );
    }

    $( document ).on( 'click', '.aura-url-qr-btn', function ( e ) {
        e.preventDefault();
        e.stopPropagation();
        openQrModal( $( this ).data( 'url' ), $( this ).data( 'title' ) );
    } );

    $( document ).on( 'click', '#aura-qr-close, .aura-qr-overlay', function () {
        closeQrModal();
    } );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            closeQrModal();
        }
    } );

    $( document ).on( 'click', '#aura-qr-download', function () {
        var $canvas = $( '#aura-qr-canvas canvas' );
        if ( ! $canvas.length ) return;
        var a = document.createElement( 'a' );
        a.download = 'qr-formulario.png';
        a.href     = $canvas[ 0 ].toDataURL( 'image/png' );
        a.click();
    } );

    $( document ).on( 'click', '#aura-qr-copy-url', function () {
        if ( qrActiveUrl ) {
            copyToClipboard( qrActiveUrl );
        }
    } );

} ( jQuery ) );

