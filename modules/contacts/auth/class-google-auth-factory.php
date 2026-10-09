<?php
/**
 * Factory para instanciar el Proveedor de Autenticación de Google activo en Aura Suite.
 *
 * Permite cambiar sin problemas entre OAuth2 de Usuario (Gmail Personal / Workspace)
 * y Service Account con Delegación de Dominio sin alterar la lógica de negocio.
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts\Auth
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/interface-google-auth-provider.php';
require_once __DIR__ . '/class-google-auth-crypto.php';
require_once __DIR__ . '/class-google-auth-oauth-user.php';
require_once __DIR__ . '/class-google-auth-service-account.php';

class Aura_Google_Auth_Factory {

    const OPTION_AUTH_MODE = 'aura_contacts_auth_mode';

    /**
     * Inicializa los proveedores de autenticación registrados.
     */
    public static function init() {
        Aura_Google_Auth_OAuth_User::init();
        Aura_Google_Auth_Service_Account::init();
    }

    /**
     * Obtiene la instancia del proveedor de autenticación actualmente configurado.
     *
     * @return Aura_Google_Auth_Provider
     */
    public static function get_provider(): Aura_Google_Auth_Provider {
        $mode = self::get_active_mode();

        switch ( $mode ) {
            case 'service_account_dwd':
                // Si existe la clase de Service Account DWD la instanciamos
                $sa_file = __DIR__ . '/class-google-auth-service-account.php';
                if ( file_exists( $sa_file ) ) {
                    require_once $sa_file;
                    if ( class_exists( 'Aura_Google_Auth_Service_Account' ) ) {
                        return new Aura_Google_Auth_Service_Account();
                    }
                }
                // Fallback a OAuth2 si la clase aún no está implementada
                return new Aura_Google_Auth_OAuth_User();

            case 'oauth_user':
            default:
                return new Aura_Google_Auth_OAuth_User();
        }
    }

    /**
     * Obtiene la instancia específica del proveedor OAuth2 de usuario.
     *
     * @return Aura_Google_Auth_OAuth_User
     */
    public static function get_oauth_provider(): Aura_Google_Auth_OAuth_User {
        return new Aura_Google_Auth_OAuth_User();
    }

    /**
     * Obtiene el modo de autenticación activo ('oauth_user'|'service_account_dwd').
     *
     * @return string
     */
    public static function get_active_mode(): string {
        return get_option( self::OPTION_AUTH_MODE, 'oauth_user' );
    }

    /**
     * Establece el modo de autenticación activo.
     *
     * @param string $mode
     */
    public static function set_active_mode( string $mode ): void {
        if ( in_array( $mode, array( 'oauth_user', 'service_account_dwd' ), true ) ) {
            update_option( self::OPTION_AUTH_MODE, $mode );
        }
    }

    /**
     * Lista de modos de autenticación soportados con sus descripciones.
     *
     * @return array
     */
    public static function get_available_modes(): array {
        return array(
            'oauth_user'          => array(
                'label'       => __( 'OAuth2 de Usuario (Gmail Personal / Workspace)', 'aura-suite' ),
                'description' => __( 'Recomendado para centromateo@gmail.com u organizaciones sin Google Workspace propio.', 'aura-suite' ),
                'available'   => true,
            ),
            'service_account_dwd' => array(
                'label'       => __( 'Cuenta de Servicio con Delegación en todo el Dominio (Google Workspace)', 'aura-suite' ),
                'description' => __( 'Para dominios administrados con Google Workspace de organización.', 'aura-suite' ),
                'available'   => file_exists( __DIR__ . '/class-google-auth-service-account.php' ),
            ),
        );
    }
}
