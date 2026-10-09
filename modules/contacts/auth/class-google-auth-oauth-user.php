<?php
/**
 * Proveedor de Autenticación OAuth2 de Usuario para Google Contacts / People API.
 *
 * Implementa el flujo Authorization Code con offline access (refresh token)
 * para cuentas personales de Gmail (centromateo@gmail.com) o cuentas Workspace individuales.
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts\Auth
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/interface-google-auth-provider.php';
require_once __DIR__ . '/class-google-auth-crypto.php';

class Aura_Google_Auth_OAuth_User implements Aura_Google_Auth_Provider {

    const OPTION_CLIENT_ID       = 'aura_contacts_oauth_client_id';
    const OPTION_CLIENT_SECRET   = 'aura_contacts_oauth_client_secret';
    const OPTION_REFRESH_TOKEN   = 'aura_contacts_oauth_refresh_token';
    const OPTION_ACCESS_TOKEN    = 'aura_contacts_oauth_access_token';
    const OPTION_TOKEN_EXPIRES   = 'aura_contacts_oauth_token_expires';
    const OPTION_ACCOUNT_EMAIL   = 'aura_contacts_oauth_account_email';
    const OPTION_LAST_ERROR      = 'aura_contacts_oauth_last_error';

    const GOOGLE_AUTH_ENDPOINT   = 'https://accounts.google.com/o/oauth2/v2/auth';
    const GOOGLE_TOKEN_ENDPOINT  = 'https://oauth2.googleapis.com/token';
    const GOOGLE_USERINFO_URL    = 'https://www.googleapis.com/oauth2/v2/userinfo';

    const SCOPE                  = 'https://www.googleapis.com/auth/contacts https://www.googleapis.com/auth/userinfo.email';

    /**
     * Inicializa los hooks de WordPress para escuchar el callback de OAuth2.
     */
    public static function init() {
        add_action( 'admin_post_aura_contacts_oauth_callback', array( __CLASS__, 'handle_oauth_callback' ) );
    }

    /**
     * Obtiene la URI de redirección autorizada en Google Cloud Console.
     *
     * @return string
     */
    public static function get_redirect_uri(): string {
        return admin_url( 'admin-post.php?action=aura_contacts_oauth_callback' );
    }

    /**
     * Genera la URL para iniciar el flujo de consentimiento en Google.
     *
     * @return string URL completa de autorización o cadena vacía si faltan credenciales.
     */
    public function get_auth_url(): string {
        $client_id = $this->get_client_id();
        if ( empty( $client_id ) ) {
            return '';
        }

        $state = wp_create_nonce( 'aura_contacts_oauth_state' );

        $params = array(
            'client_id'              => $client_id,
            'redirect_uri'           => self::get_redirect_uri(),
            'response_type'          => 'code',
            'scope'                  => self::SCOPE,
            'access_type'            => 'offline',
            'prompt'                 => 'consent', // Garantiza que Google devuelva refresh_token
            'state'                  => $state,
            'include_granted_scopes' => 'true',
        );

        return self::GOOGLE_AUTH_ENDPOINT . '?' . http_build_query( $params );
    }

    /**
     * Manejador del callback OAuth tras la autorización del usuario en Google.
     */
    public static function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_contacts_manage_google' ) ) {
            wp_die( esc_html__( 'No tienes permisos suficientes para autorizar la integración de Google.', 'aura-suite' ), 403 );
        }

        $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        if ( ! wp_verify_nonce( $state, 'aura_contacts_oauth_state' ) ) {
            self::redirect_with_notice( 'error', __( 'Error de seguridad (nonce o state inválido). Intenta nuevamente.', 'aura-suite' ) );
            return;
        }

        if ( isset( $_GET['error'] ) ) {
            $error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
            self::redirect_with_notice( 'error', sprintf( __( 'Google canceló la autorización: %s', 'aura-suite' ), $error ) );
            return;
        }

        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        if ( empty( $code ) ) {
            self::redirect_with_notice( 'error', __( 'Código de autorización no recibido desde Google.', 'aura-suite' ) );
            return;
        }

        $provider = new self();
        $client_id     = $provider->get_client_id();
        $client_secret = $provider->get_client_secret();

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            self::redirect_with_notice( 'error', __( 'Credenciales Client ID / Client Secret no configuradas.', 'aura-suite' ) );
            return;
        }

        // Intercambiar el código por tokens
        $response = wp_remote_post(
            self::GOOGLE_TOKEN_ENDPOINT,
            array(
                'timeout' => 20,
                'body'    => array(
                    'code'          => $code,
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'redirect_uri'  => self::get_redirect_uri(),
                    'grant_type'    => 'authorization_code',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::redirect_with_notice( 'error', $response->get_error_message() );
            return;
        }

        $body_raw = wp_remote_retrieve_body( $response );
        $body     = json_decode( $body_raw, true );

        if ( empty( $body['access_token'] ) ) {
            $error_desc = $body['error_description'] ?? ( $body['error'] ?? 'Respuesta inválida de Google.' );
            self::redirect_with_notice( 'error', esc_html( $error_desc ) );
            return;
        }

        // Guardar access token cifrado
        $provider->set_access_token( $body['access_token'], (int) ( $body['expires_in'] ?? 3600 ) );

        // Si vino refresh token, guardarlo cifrado
        if ( ! empty( $body['refresh_token'] ) ) {
            $provider->set_refresh_token( $body['refresh_token'] );
        }

        // Obtener el correo electrónico de la cuenta conectada
        $email = $provider->fetch_user_email( $body['access_token'] );
        if ( ! empty( $email ) ) {
            update_option( self::OPTION_ACCOUNT_EMAIL, sanitize_email( $email ) );
        }

        delete_option( self::OPTION_LAST_ERROR );

        self::redirect_with_notice(
            'success',
            sprintf(
                __( '¡Cuenta de Google conectada exitosamente (%s)! Sincronización habilitada.', 'aura-suite' ),
                $email ?: 'Google Contacts'
            )
        );
    }

    /**
     * Consulta la API de userinfo de Google para descubrir qué cuenta fue autorizada.
     *
     * @param string $access_token
     * @return string Email de la cuenta o cadena vacía.
     */
    private function fetch_user_email( string $access_token ): string {
        $response = wp_remote_get(
            self::GOOGLE_USERINFO_URL,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $data['email'] ) ? (string) $data['email'] : '';
    }

    /**
     * Redirige al panel de administración con un mensaje de aviso.
     *
     * @param string $type 'success'|'error'
     * @param string $message
     */
    private static function redirect_with_notice( string $type, string $message ) {
        $url = add_query_arg(
            array(
                'page'               => 'aura-contacts',
                'tab'                => 'settings',
                'aura_oauth_notice'  => $type,
                'aura_oauth_message' => rawurlencode( $message ),
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Implementación de Aura_Google_Auth_Provider::get_access_token()
     *
     * @return string|WP_Error
     */
    public function get_access_token() {
        $expires_at = (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );
        $token_enc  = get_option( self::OPTION_ACCESS_TOKEN, '' );

        // Si el access token aún es válido (buffer de 60 segundos)
        if ( $expires_at > ( time() + 60 ) && ! empty( $token_enc ) ) {
            $token = Aura_Google_Auth_Crypto::decrypt( $token_enc );
            if ( ! empty( $token ) ) {
                return $token;
            }
        }

        // Si expiró o no existe, refrescar con refresh_token
        return $this->refresh_access_token();
    }

    /**
     * Solicita un nuevo access token a Google mediante el refresh token.
     *
     * @return string|WP_Error
     */
    public function refresh_access_token() {
        $refresh_token = $this->get_refresh_token();
        if ( empty( $refresh_token ) ) {
            return new WP_Error(
                'no_refresh_token',
                __( 'No hay autorización de Google Contacts vinculada. Conecta tu cuenta primero.', 'aura-suite' )
            );
        }

        $client_id     = $this->get_client_id();
        $client_secret = $this->get_client_secret();

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new WP_Error(
                'missing_credentials',
                __( 'Faltan credenciales de Google OAuth (Client ID o Client Secret).', 'aura-suite' )
            );
        }

        $response = wp_remote_post(
            self::GOOGLE_TOKEN_ENDPOINT,
            array(
                'timeout' => 20,
                'body'    => array(
                    'refresh_token' => $refresh_token,
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type'    => 'refresh_token',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            update_option( self::OPTION_LAST_ERROR, $response->get_error_message() );
            return $response;
        }

        $body_raw = wp_remote_retrieve_body( $response );
        $body     = json_decode( $body_raw, true );

        if ( empty( $body['access_token'] ) ) {
            $err = $body['error'] ?? 'unknown_error';
            $msg = $body['error_description'] ?? $err;

            if ( 'invalid_grant' === $err ) {
                // El refresh token expiró (ej. pasaron 7 días en Testing) o fue revocado
                update_option( self::OPTION_LAST_ERROR, __( 'La autorización expiró o fue revocada por Google (invalid_grant). Vuelve a conectar la cuenta.', 'aura-suite' ) );
                return new WP_Error( 'invalid_grant', __( 'Autorización expirada o revocada. Vuelve a conectar tu cuenta.', 'aura-suite' ) );
            }

            update_option( self::OPTION_LAST_ERROR, $msg );
            return new WP_Error( 'google_token_error', $msg );
        }

        $access_token = $body['access_token'];
        $expires_in   = (int) ( $body['expires_in'] ?? 3600 );

        $this->set_access_token( $access_token, $expires_in );

        // Algunos proveedores rotan refresh_tokens
        if ( ! empty( $body['refresh_token'] ) ) {
            $this->set_refresh_token( $body['refresh_token'] );
        }

        delete_option( self::OPTION_LAST_ERROR );

        return $access_token;
    }

    /**
     * Guarda el access token de forma cifrada en la base de datos.
     *
     * @param string $token
     * @param int    $expires_in
     */
    public function set_access_token( string $token, int $expires_in = 3600 ): void {
        update_option( self::OPTION_ACCESS_TOKEN, Aura_Google_Auth_Crypto::encrypt( $token ) );
        update_option( self::OPTION_TOKEN_EXPIRES, time() + $expires_in );
    }

    /**
     * Guarda el refresh token de forma cifrada en la base de datos.
     *
     * @param string $token
     */
    public function set_refresh_token( string $token ): void {
        update_option( self::OPTION_REFRESH_TOKEN, Aura_Google_Auth_Crypto::encrypt( $token ) );
    }

    /**
     * Obtiene el refresh token descifrado.
     *
     * @return string
     */
    public function get_refresh_token(): string {
        $enc = get_option( self::OPTION_REFRESH_TOKEN, '' );
        return Aura_Google_Auth_Crypto::decrypt( $enc );
    }

    /**
     * Implementación de Aura_Google_Auth_Provider::get_account_email()
     *
     * @return string
     */
    public function get_account_email(): string {
        return (string) get_option( self::OPTION_ACCOUNT_EMAIL, '' );
    }

    /**
     * Verifica si las credenciales del cliente están configuradas.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return ! empty( $this->get_client_id() ) && ! empty( $this->get_client_secret() );
    }

    /**
     * Verifica si la cuenta está autenticada con Google (alias de is_connected).
     *
     * @return bool
     */
    public function is_authenticated(): bool {
        return $this->is_connected();
    }

    /**
     * Implementación de Aura_Google_Auth_Provider::is_connected()
     *
     * @return bool
     */
    public function is_connected(): bool {
        return ! empty( $this->get_client_id() ) &&
               ! empty( $this->get_client_secret() ) &&
               ! empty( $this->get_refresh_token() );
    }

    /**
     * Implementación de Aura_Google_Auth_Provider::disconnect()
     */
    public function disconnect(): void {
        delete_option( self::OPTION_REFRESH_TOKEN );
        delete_option( self::OPTION_ACCESS_TOKEN );
        delete_option( self::OPTION_TOKEN_EXPIRES );
        delete_option( self::OPTION_ACCOUNT_EMAIL );
        delete_option( self::OPTION_LAST_ERROR );
        delete_option( 'aura_contacts_google_sync_token' );
    }

    /**
     * Implementación de Aura_Google_Auth_Provider::get_auth_mode()
     *
     * @return string
     */
    public function get_auth_mode(): string {
        return 'oauth_user';
    }

    /**
     * Implementación de Aura_Google_Auth_Provider::get_label()
     *
     * @return string
     */
    public function get_label(): string {
        return __( 'OAuth2 de Usuario (Gmail Personal / Workspace)', 'aura-suite' );
    }

    /**
     * Guarda las credenciales Client ID y Client Secret de forma cifrada.
     *
     * @param string $client_id
     * @param string $client_secret
     */
    public function save_credentials( string $client_id, string $client_secret ): void {
        update_option( self::OPTION_CLIENT_ID, Aura_Google_Auth_Crypto::encrypt( trim( $client_id ) ) );
        if ( ! empty( $client_secret ) ) {
            update_option( self::OPTION_CLIENT_SECRET, Aura_Google_Auth_Crypto::encrypt( trim( $client_secret ) ) );
        }
    }

    /**
     * Obtiene el Client ID descifrado.
     *
     * @return string
     */
    public function get_client_id(): string {
        $enc = get_option( self::OPTION_CLIENT_ID, '' );
        return Aura_Google_Auth_Crypto::decrypt( $enc );
    }

    /**
     * Obtiene el Client Secret descifrado.
     *
     * @return string
     */
    public function get_client_secret(): string {
        $enc = get_option( self::OPTION_CLIENT_SECRET, '' );
        return Aura_Google_Auth_Crypto::decrypt( $enc );
    }

    /**
     * Obtiene una versión enmascarada del Client Secret para mostrar en el formulario sin exponerlo.
     *
     * @return string
     */
    public function get_masked_client_secret(): string {
        $secret = $this->get_client_secret();
        if ( empty( $secret ) ) {
            return '';
        }
        $len = strlen( $secret );
        if ( $len <= 8 ) {
            return '••••••••';
        }
        return substr( $secret, 0, 4 ) . str_repeat( '•', $len - 8 ) . substr( $secret, -4 );
    }

    /**
     * Obtiene el último error registrado en el flujo OAuth.
     *
     * @return string
     */
    public function get_last_error(): string {
        return (string) get_option( self::OPTION_LAST_ERROR, '' );
    }
}
