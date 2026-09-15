/**
 * Aura Vehicles Module — Admin Scripts
 * Fase 1: base JS. Se amplía en Fases 2-9.
 *
 * Accede a la configuración global inyectada por wp_localize_script:
 *   auraVehiclesConfig.nonce   — nonce para REST API (wp_rest)
 *   auraVehiclesConfig.apiBase — URL base de la REST API (aura/v1/)
 *
 * @package Aura_Business_Suite\Vehicles
 */

( function ( $ ) {
    'use strict';

    // Verificar que la configuración global esté disponible
    if ( typeof auraVehiclesConfig === 'undefined' ) {
        return;
    }

    var NONCE    = auraVehiclesConfig.nonce;
    var API_BASE = auraVehiclesConfig.apiBase;

    /**
     * Función auxiliar: petición autenticada a la REST API de Aura.
     *
     * @param {string} endpoint  Ruta relativa, ej. 'vehicles'.
     * @param {string} method    Método HTTP (GET, POST, PUT, DELETE).
     * @param {Object} [data]    Cuerpo de la petición (opcional).
     * @returns {Promise}
     */
    function apiRequest( endpoint, method, data ) {
        var options = {
            url    : API_BASE + endpoint,
            method : method || 'GET',
            headers: { 'X-WP-Nonce': NONCE },
        };

        if ( data ) {
            options.contentType = 'application/json';
            options.data        = JSON.stringify( data );
        }

        return $.ajax( options );
    }

    // Exportar para uso en scripts específicos de cada fase
    window.AuraVehicles = window.AuraVehicles || {};
    window.AuraVehicles.api = apiRequest;

}( jQuery ) );
