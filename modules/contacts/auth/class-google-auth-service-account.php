<?php
/**
 * Proveedor de Autenticación con Cuenta de Servicio y Delegación de Dominio (DWD)
 * para Google Workspace y People API.
 *
 * Implementa RFC 7523 (JWT Bearer Token flow con firma asimétrica RS256).
 * Permite a Aura Suite actuar en nombre de cualquier usuario o administrador
 * de un dominio Google Workspace institucional (Domain-Wide Delegation).
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts\Auth
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/interface-google-auth-provider.php';
require_once __DIR__ . '/class-google-auth-crypto.php';

class Aura_Google_Auth_Service_Account implements Aura_Google_Auth_Provider {

    const OPTION_JSON             = 'aura_contacts_sa_json';
    const OPTION_DELEGATED_USER   = 'aura_contacts_sa_delegated_user';
    const OPTION_LAST_ERROR       = 'aura_contacts_sa_last_error';
    const TRANSIENT_TOKEN         = 'aura_contacts_sa_access_token';

    const TOKEN_ENDPOINT          = 'https://oauth2.googleapis.com/token';
    const SCOPE                   = 'https://www.googleapis.com/auth/contacts https://www.googleapis.com/auth/userinfo.email';

    /**
     * Inicializa los hooks asociados a la cuenta de servicio.
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_contacts_save_sa_settings', array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_aura_contacts_test_sa_connection', array( __CLASS__, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_aura_contacts_switch_auth_mode', array( __CLASS__, 'ajax_switch_mode' ) );
    }

    /**
     * Obtiene el token de acceso activo.
     * Si no existe o expiró, genera un nuevo JWT firmado con la llave privada RS256.
     *
     * @return string|WP_Error Token de acceso o WP_Error en caso de fallo.
     */
    public function get_access_token() {
        $cached = get_transient( self::TRANSIENT_TOKEN );
        if ( ! empty( $cached ) && is_string( $cached ) ) {
            return $cached;
        }

        $creds = $this->get_credentials();
        if ( ! $creds ) {
            $err = __( 'No hay credenciales de Cuenta de Servicio configuradas o el archivo JSON es inválido.', 'aura-suite' );
            update_option( self::OPTION_LAST_ERROR, $err );
            return new WP_Error( 'missing_credentials', $err );
        }

        $jwt = $this->generate_jwt( $creds );
        if ( is_wp_error( $jwt ) ) {
            update_option( self::OPTION_LAST_ERROR, $jwt->get_error_message() );
            return $jwt;
        }

        $response = wp_remote_post( self::TOKEN_ENDPOINT, array(
            'timeout' => 20,
            'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
            'body'    => http_build_query( array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            $msg = sprintf( __( 'Error al conectar con el servidor de autenticación de Google: %s', 'aura-suite' ), $response->get_error_message() );
            update_option( self::OPTION_LAST_ERROR, $msg );
            return new WP_Error( 'google_token_request_failed', $msg );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status || empty( $body['access_token'] ) ) {
            $err_desc = $body['error_description'] ?? ( $body['error'] ?? 'Error desconocido de autorización JWT.' );
            $msg      = sprintf( __( 'Google rechazó el token JWT de Cuenta de Servicio (%d): %s', 'aura-suite' ), $status, $err_desc );
            update_option( self::OPTION_LAST_ERROR, $msg );
            return new WP_Error( 'google_jwt_rejected', $msg );
        }

        $token      = (string) $body['access_token'];
        $expires_in = isset( $body['expires_in'] ) ? max( 300, (int) $body['expires_in'] - 120 ) : 3400;

        set_transient( self::TRANSIENT_TOKEN, $token, $expires_in );
        delete_option( self::OPTION_LAST_ERROR );

        return $token;
    }

    /**
     * Genera la aserción JWT firmada con SHA256withRSA.
     *
     * @param array $creds
     * @return string|WP_Error
     */
    private function generate_jwt( array $creds ) {
        if ( ! function_exists( 'openssl_sign' ) ) {
            return new WP_Error( 'openssl_missing', __( 'La extensión OpenSSL de PHP no está disponible en este servidor.', 'aura-suite' ) );
        }

        $now             = time();
        $delegated_user  = $this->get_delegated_user();

        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT',
        );

        $payload = array(
            'iss'   => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_ENDPOINT,
            'exp'   => $now + 3600,
            'iat'   => $now,
        );

        // Delegación de Dominio: impersonar usuario Workspace
        if ( ! empty( $delegated_user ) ) {
            $payload['sub'] = $delegated_user;
        }

        $encoded_header  = $this->base64url_encode( (string) wp_json_encode( $header ) );
        $encoded_payload = $this->base64url_encode( (string) wp_json_encode( $payload ) );
        $data_to_sign    = $encoded_header . '.' . $encoded_payload;

        $private_key = $creds['private_key'] ?? '';
        $binary_signature = '';

        $signed = openssl_sign( $data_to_sign, $binary_signature, $private_key, 'SHA256' );
        if ( ! $signed ) {
            return new WP_Error( 'jwt_signing_error', __( 'Fallo al firmar el token JWT con la llave privada de la Cuenta de Servicio.', 'aura-suite' ) );
        }

        $encoded_signature = $this->base64url_encode( $binary_signature );

        return $data_to_sign . '.' . $encoded_signature;
    }

    /**
     * Codificador base64url compatible con RFC 7515 / JWT.
     *
     * @param string $data
     * @return string
     */
    private function base64url_encode( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Obtiene el correo de la cuenta vinculada (usuario impersonado o email de la Service Account).
     *
     * @return string
     */
    public function get_account_email(): string {
        $delegated = $this->get_delegated_user();
        if ( ! empty( $delegated ) ) {
            return $delegated;
        }
        $creds = $this->get_credentials();
        return $creds['client_email'] ?? '';
    }

    /**
     * Verifica si el archivo JSON de credenciales está configurado.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $creds = $this->get_credentials();
        return ! empty( $creds['client_email'] ) && ! empty( $creds['private_key'] );
    }

    /**
     * Verifica si la cuenta está autenticada actualmente.
     *
     * @return bool
     */
    public function is_authenticated(): bool {
        if ( ! $this->is_configured() ) {
            return false;
        }
        $token = get_transient( self::TRANSIENT_TOKEN );
        return ! empty( $token );
    }

    /**
     * Verifica si el proveedor está conectado y listo para operar.
     *
     * @return bool
     */
    public function is_connected(): bool {
        if ( ! $this->is_configured() ) {
            return false;
        }
        $token = $this->get_access_token();
        return ! is_wp_error( $token ) && ! empty( $token );
    }

    /**
     * Desconecta el proveedor limpiando caché de tokens y credenciales.
     *
     * @return void
     */
    public function disconnect(): void {
        delete_transient( self::TRANSIENT_TOKEN );
        delete_option( self::OPTION_LAST_ERROR );
    }

    /**
     * Elimina por completo las credenciales guardadas.
     *
     * @return void
     */
    public function reset_all(): void {
        $this->disconnect();
        delete_option( self::OPTION_JSON );
        delete_option( self::OPTION_DELEGATED_USER );
    }

    /**
     * Slug identificador del modo de autenticación.
     *
     * @return string
     */
    public function get_auth_mode(): string {
        return 'service_account_dwd';
    }

    /**
     * Etiqueta descriptiva del proveedor.
     *
     * @return string
     */
    public function get_label(): string {
        return __( 'Cuenta de Servicio con Delegación de Dominio (Google Workspace DWD)', 'aura-suite' );
    }

    /**
     * Obtiene el array de credenciales parseado desde la opción segura.
     *
     * @return array|null
     */
    public function get_credentials(): ?array {
        $raw = get_option( self::OPTION_JSON, '' );
        if ( empty( $raw ) ) {
            return null;
        }

        $decrypted = Aura_Google_Auth_Crypto::decrypt( $raw );
        $json_str  = $decrypted ?: $raw;

        $data = json_decode( $json_str, true );
        if ( ! is_array( $data ) || empty( $data['private_key'] ) || empty( $data['client_email'] ) ) {
            return null;
        }

        return $data;
    }

    /**
     * Obtiene el correo del usuario de Google Workspace a impersonar (claim sub).
     *
     * @return string
     */
    public function get_delegated_user(): string {
        return trim( (string) get_option( self::OPTION_DELEGATED_USER, '' ) );
    }

    /**
     * Guarda las credenciales de la Cuenta de Servicio y el usuario delegado.
     *
     * @param string $json_raw
     * @param string $delegated_user
     * @return true|WP_Error
     */
    public function save_credentials( string $json_raw, string $delegated_user = '' ) {
        $parsed = json_decode( $json_raw, true );
        if ( ! is_array( $parsed ) || ( $parsed['type'] ?? '' ) !== 'service_account' ) {
            return new WP_Error( 'invalid_sa_json', __( 'El texto ingresado no es un JSON válido de Cuenta de Servicio de Google (debe incluir "type": "service_account").', 'aura-suite' ) );
        }

        if ( empty( $parsed['client_email'] ) || empty( $parsed['private_key'] ) ) {
            return new WP_Error( 'missing_sa_fields', __( 'El JSON no contiene client_email o private_key válidos.', 'aura-suite' ) );
        }

        // Cifrar antes de persistir
        $encrypted = Aura_Google_Auth_Crypto::encrypt( $json_raw );
        update_option( self::OPTION_JSON, $encrypted );

        if ( ! empty( $delegated_user ) ) {
            update_option( self::OPTION_DELEGATED_USER, sanitize_email( $delegated_user ) );
        } else {
            delete_option( self::OPTION_DELEGATED_USER );
        }

        // Limpiar token anterior
        $this->disconnect();

        return true;
    }

    /**
     * Prueba la conexión en vivo con Google People API.
     *
     * @return array { success: bool, message: string, email: string, details?: array }
     */
    public function test_connection(): array {
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return array(
                'success' => false,
                'message' => $token->get_error_message(),
                'email'   => $this->get_account_email(),
            );
        }

        // Llamar a Google People API para verificar permisos de lectura
        $url = 'https://people.googleapis.com/v1/people/me/connections?pageSize=1&personFields=names';
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Error al contactar Google People API: %s', 'aura-suite' ), $response->get_error_message() ),
                'email'   => $this->get_account_email(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            $err_msg = $body['error']['message'] ?? __( 'Google People API devolvió un código de error inesperado.', 'aura-suite' );
            return array(
                'success' => false,
                'message' => sprintf( __( 'Fallo de acceso a People API (%d): %s', 'aura-suite' ), $code, $err_msg ),
                'email'   => $this->get_account_email(),
            );
        }

        $total_people = (int) ( $body['totalPeople'] ?? 0 );

        return array(
            'success'      => true,
            'message'      => sprintf( __( '¡Conexión DWD exitosa! Autenticado correctamente con Google Workspace. (%d contactos accesibles)', 'aura-suite' ), $total_people ),
            'email'        => $this->get_account_email(),
            'total_people' => $total_people,
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CONTROLADORES AJAX
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Guardar credenciales de Service Account DWD.
     */
    public static function ajax_save_settings(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_manage_google' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ) );
        }

        $json_raw       = isset( $_POST['sa_json'] ) ? wp_unslash( $_POST['sa_json'] ) : '';
        $delegated_user = isset( $_POST['delegated_user'] ) ? sanitize_email( wp_unslash( $_POST['delegated_user'] ) ) : '';

        if ( empty( $json_raw ) ) {
            wp_send_json_error( array( 'message' => __( 'Debes ingresar el contenido JSON de la Cuenta de Servicio.', 'aura-suite' ) ) );
        }

        $provider = new self();
        $result   = $provider->save_credentials( $json_raw, $delegated_user );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'        => __( 'Credenciales de Cuenta de Servicio guardadas con éxito.', 'aura-suite' ),
            'account_email'  => $provider->get_account_email(),
            'delegated_user' => $delegated_user,
        ) );
    }

    /**
     * AJAX: Probar conexión DWD en vivo.
     */
    public static function ajax_test_connection(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_manage_google' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ) );
        }

        $provider = new self();
        $result   = $provider->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Cambiar modo de autenticación (oauth_user <-> service_account_dwd).
     */
    public static function ajax_switch_mode(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_manage_google' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ) );
        }

        $mode = sanitize_key( $_POST['auth_mode'] ?? 'oauth_user' );
        Aura_Google_Auth_Factory::set_active_mode( $mode );

        wp_send_json_success( array(
            'message'   => sprintf( __( 'Modo de autenticación cambiado a: %s', 'aura-suite' ), $mode ),
            'auth_mode' => $mode,
        ) );
    }
}
