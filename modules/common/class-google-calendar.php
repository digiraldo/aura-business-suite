<?php
/**
 * Servicio Global de Google Calendar
 *
 * Infraestructura compartida para crear/actualizar/eliminar eventos en
 * Google Calendar mediante Service Account (JWT / RSA-256), sin OAuth de
 * usuario ni librerías Composer externas. Requiere la extensión PHP openssl.
 *
 * Cualquier módulo del plugin llama a los métodos públicos de esta clase:
 *   - Aura_Google_Calendar::is_enabled()
 *   - Aura_Google_Calendar::get_or_create_calendar( $name, $option_key )
 *   - Aura_Google_Calendar::share_calendar( $cal_id, $force )
 *   - Aura_Google_Calendar::sync_event( $cal_id, $event_id_key, $event )
 *   - Aura_Google_Calendar::delete_event( $cal_id, $event_id_key )
 *
 * Configuración en: Ajustes → Aura Business Suite → Google Calendar
 * (opciones wp_options: aura_gcal_enabled, aura_gcal_service_account_json,
 *  aura_gcal_share_email, aura_gcal_reminder_days, aura_gcal_calendar_id_resolved)
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Google_Calendar {

    /** Transient para almacenar el access token (caché 3600 s) */
    const TOKEN_TRANSIENT = 'aura_gcal_token';

    /** Scope requerido por la Calendar API */
    const SCOPE = 'https://www.googleapis.com/auth/calendar';

    /** Nombre del calendario principal creado por AURA */
    const CALENDAR_NAME = 'Mantenimientos CEM';

    /** wp_option que cachea el Calendar ID resuelto */
    const CAL_ID_OPTION = 'aura_gcal_calendar_id_resolved';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_global_gcal_test', [ __CLASS__, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_aura_global_gcal_save', [ __CLASS__, 'ajax_save_settings'   ] );
        add_action( 'wp_ajax_aura_global_gcal_test_whatsapp', [ __CLASS__, 'ajax_test_whatsapp' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // ESTADO — ¿Integración activa y configurada?
    // ─────────────────────────────────────────────────────────────

    public static function is_enabled(): bool {
        if ( ! get_option( 'aura_gcal_enabled', '0' ) ) {
            return false;
        }
        $creds = self::get_credentials();
        return ! empty( $creds['private_key'] ) && ! empty( $creds['client_email'] );
    }

    // ─────────────────────────────────────────────────────────────
    // CREDENCIALES — Service Account JSON
    // ─────────────────────────────────────────────────────────────

    private static function get_credentials(): ?array {
        $json = get_option( 'aura_gcal_service_account_json', '' );
        if ( empty( $json ) ) {
            return null;
        }
        $creds = json_decode( $json, true );
        if ( ! is_array( $creds )
            || empty( $creds['private_key'] )
            || empty( $creds['client_email'] )
            || ( $creds['type'] ?? '' ) !== 'service_account'
        ) {
            return null;
        }
        return $creds;
    }

    // ─────────────────────────────────────────────────────────────
    // JWT — Base64url + firma RSA-SHA256
    // ─────────────────────────────────────────────────────────────

    private static function base64url( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private static function generate_jwt( array $creds ): string {
        $now = time();

        $header  = self::base64url( (string) wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
        $payload = self::base64url( (string) wp_json_encode( [
            'iss'   => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ] ) );

        $data      = $header . '.' . $payload;
        $signature = '';

        if ( ! openssl_sign( $data, $signature, $creds['private_key'], 'SHA256' ) ) {
            return '';
        }

        return $data . '.' . self::base64url( $signature );
    }

    // ─────────────────────────────────────────────────────────────
    // TOKEN — Obtener access token (con caché en transient)
    // ─────────────────────────────────────────────────────────────

    private static function get_access_token(): ?string {
        $cached = get_transient( self::TOKEN_TRANSIENT );
        if ( $cached ) {
            return $cached;
        }

        $creds = self::get_credentials();
        if ( ! $creds ) {
            error_log( '[AURA GCal] get_access_token: credenciales no encontradas o inválidas.' );
            return null;
        }

        $jwt = self::generate_jwt( $creds );
        if ( empty( $jwt ) ) {
            error_log( '[AURA GCal] get_access_token: generate_jwt() falló. Verifica que openssl esté disponible y que private_key sea válida.' );
            return null;
        }

        $resp = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            'body'    => http_build_query( [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ] ),
        ] );

        if ( is_wp_error( $resp ) ) {
            error_log( '[AURA GCal] get_access_token: WP_Error al llamar oauth2.googleapis.com/token → ' . $resp->get_error_message() );
            return null;
        }

        $http_code = wp_remote_retrieve_response_code( $resp );
        $body      = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( empty( $body['access_token'] ) ) {
            error_log( sprintf(
                '[AURA GCal] get_access_token: No se obtuvo access_token. HTTP %d. Respuesta: %s',
                $http_code,
                wp_json_encode( $body )
            ) );
            return null;
        }

        $expires = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
        set_transient( self::TOKEN_TRANSIENT, $body['access_token'], $expires );

        return $body['access_token'];
    }

    // ─────────────────────────────────────────────────────────────
    // API — Peticiones autenticadas a Google Calendar
    // ─────────────────────────────────────────────────────────────

    /**
     * Realiza una petición autenticada a la Google Calendar API.
     *
     * @param  string     $method  HTTP method: GET, POST, PUT, DELETE, PATCH
     * @param  string     $url     URL completa del endpoint.
     * @param  array|null $body    Cuerpo de la petición (se serializa a JSON).
     * @return array|null          Respuesta decodificada, [] para 204 No Content, null si hay error.
     */
    public static function api_request( string $method, string $url, ?array $body = null ): ?array {
        $token = self::get_access_token();
        if ( ! $token ) {
            error_log( '[AURA GCal] api_request: sin token — ' . $method . ' ' . $url );
            return null;
        }

        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ];

        if ( $body !== null ) {
            $args['body'] = (string) wp_json_encode( $body );
        }

        $resp = wp_remote_request( $url, $args );
        if ( is_wp_error( $resp ) ) {
            error_log( '[AURA GCal] api_request: WP_Error → ' . $resp->get_error_message() . ' | ' . $method . ' ' . $url );
            return null;
        }

        $code      = wp_remote_retrieve_response_code( $resp );
        $resp_body = wp_remote_retrieve_body( $resp );

        if ( $code >= 400 ) {
            error_log( sprintf(
                '[AURA GCal] api_request: HTTP %d en %s %s → %s',
                $code, $method, $url, $resp_body
            ) );
            return null;
        }

        if ( $code === 204 ) {
            return []; // DELETE / respuesta vacía exitosa
        }

        $data = json_decode( $resp_body, true );
        return is_array( $data ) ? $data : [];
    }

    // ─────────────────────────────────────────────────────────────
    // CALENDARIO — Obtener o crear calendario por nombre
    // ─────────────────────────────────────────────────────────────

    /**
     * Busca o crea el calendario $name en la cuenta de la Service Account.
     * El Calendar ID resuelto se cachea en la wp_option $cal_id_option.
     *
     * @param  string      $name          Nombre del calendario en Google.
     * @param  string      $cal_id_option Clave de wp_option para cachear el ID.
     * @return string|null                Calendar ID o null si falla.
     */
    public static function get_or_create_calendar( string $name, string $cal_id_option ): ?string {
        $cached = get_option( $cal_id_option, '' );
        if ( ! empty( $cached ) ) {
            return $cached;
        }

        // Buscar en lista de calendarios existentes
        $list = self::api_request( 'GET', 'https://www.googleapis.com/calendar/v3/users/me/calendarList' );
        if ( is_array( $list ) && ! empty( $list['items'] ) ) {
            foreach ( $list['items'] as $cal ) {
                if ( ( $cal['summary'] ?? '' ) === $name ) {
                    update_option( $cal_id_option, $cal['id'] );
                    return $cal['id'];
                }
            }
        }

        // Crear calendario nuevo
        $tz  = wp_timezone_string();
        $new = self::api_request( 'POST', 'https://www.googleapis.com/calendar/v3/calendars', [
            'summary'     => $name,
            'description' => 'Calendario generado automáticamente por AURA Business Suite.',
            'timeZone'    => $tz ?: 'America/Bogota',
        ] );

        if ( empty( $new['id'] ) ) {
            return null;
        }

        $cal_id = $new['id'];
        update_option( $cal_id_option, $cal_id );

        // Compartir con correos configurados
        self::share_calendar( $cal_id );

        return $cal_id;
    }

    // ─────────────────────────────────────────────────────────────
    // COMPARTIR — Invitar correos al calendario
    // ─────────────────────────────────────────────────────────────

    /**
     * Comparte $cal_id con los correos configurados en aura_gcal_share_email.
     * Soporta múltiples correos separados por coma.
     *
     * @param  string $cal_id  ID del calendario de Google.
     * @param  bool   $force   Si true elimina la regla ACL previa y re-envía invitación.
     * @return array           Mapa email → 'invited'|'already'|'failed'
     */
    public static function share_calendar( string $cal_id, bool $force = false ): array {
        $raw = get_option( 'aura_gcal_share_email', '' );
        if ( empty( $raw ) ) {
            $raw = get_option( 'admin_email', '' );
        }
        if ( empty( $raw ) ) {
            return [];
        }

        $emails  = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        $results = [];

        foreach ( $emails as $email ) {
            if ( ! is_email( $email ) ) {
                continue;
            }

            $rule_id  = 'user:' . $email;
            $existing = self::api_request(
                'GET',
                'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/acl/' . rawurlencode( $rule_id )
            );

            if ( ! empty( $existing['role'] ) && ! $force ) {
                $results[ $email ] = 'already';
            } else {
                if ( $force && ! empty( $existing['role'] ) ) {
                    self::api_request(
                        'DELETE',
                        'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/acl/' . rawurlencode( $rule_id )
                    );
                }
                $result = self::api_request(
                    'POST',
                    'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/acl?sendNotifications=true',
                    [
                        'role'  => 'writer',
                        'scope' => [ 'type' => 'user', 'value' => $email ],
                    ]
                );
                $results[ $email ] = ! empty( $result ) ? 'invited' : 'failed';
            }
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────
    // SYNC — Crear o actualizar un evento genérico
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea o actualiza el evento $event en el calendario $cal_id.
     * El ID del evento Google se almacena en la wp_option $event_id_key.
     *
     * @param string $cal_id       ID del calendario de Google.
     * @param string $event_id_key Clave de wp_option para el ID del evento.
     * @param array  $event        EventResource de Google Calendar API v3.
     */
    public static function sync_event( string $cal_id, string $event_id_key, array $event ): void {
        $existing_id = get_option( $event_id_key, '' );
        $result      = null;

        if ( $existing_id ) {
            $result = self::api_request(
                'PUT',
                'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id )
                    . '/events/' . rawurlencode( $existing_id ),
                $event
            );
            if ( $result === null ) {
                error_log( "[AURA GCal] sync_event: evento {$existing_id} no encontrado en GCal, se creará uno nuevo. (key={$event_id_key})" );
                $existing_id = '';
            }
        }

        if ( ! $existing_id ) {
            $result = self::api_request(
                'POST',
                'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/events',
                $event
            );
            if ( ! empty( $result['id'] ) ) {
                update_option( $event_id_key, $result['id'], false );
                error_log( "[AURA GCal] sync_event: evento creado → id={$result['id']} (key={$event_id_key})" );
                update_option( 'aura_gcal_last_sync_status', 'ok:' . current_time( 'mysql' ) );
            } else {
                error_log( "[AURA GCal] sync_event: FALLO al crear evento (key={$event_id_key}). Respuesta: " . wp_json_encode( $result ) );
                update_option( 'aura_gcal_last_sync_status', 'error:event_create_failed:' . current_time( 'mysql' ) );
            }
        } else {
            if ( ! empty( $result ) ) {
                error_log( "[AURA GCal] sync_event: evento actualizado → {$existing_id} (key={$event_id_key})" );
                update_option( 'aura_gcal_last_sync_status', 'ok:' . current_time( 'mysql' ) );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE — Eliminar un evento genérico
    // ─────────────────────────────────────────────────────────────

    /**
     * Elimina el evento cuyo ID está almacenado en $event_id_key y borra la option.
     *
     * @param string $cal_id       ID del calendario de Google.
     * @param string $event_id_key Clave de wp_option que contiene el ID del evento.
     */
    public static function delete_event( string $cal_id, string $event_id_key ): void {
        $event_id = get_option( $event_id_key, '' );
        if ( empty( $event_id ) ) {
            return;
        }

        self::api_request(
            'DELETE',
            'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id )
                . '/events/' . rawurlencode( $event_id )
        );

        delete_option( $event_id_key );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Probar conexión (nonce global: save_aura_settings)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_test_connection(): void {
        check_ajax_referer( 'save_aura_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        // Guardar JSON si se envió en esta petición
        if ( ! empty( $_POST['service_account_json'] ) ) {
            $raw    = wp_unslash( $_POST['service_account_json'] );
            $parsed = json_decode( $raw, true );
            if ( ! is_array( $parsed ) || ( $parsed['type'] ?? '' ) !== 'service_account' ) {
                wp_send_json_error( [
                    'message' => __( 'El JSON no corresponde a una Service Account de Google. Verifica el archivo descargado.', 'aura-suite' ),
                ] );
            }
            update_option( 'aura_gcal_service_account_json', $raw );
        }

        // Forzar autenticación fresca
        delete_transient( self::TOKEN_TRANSIENT );

        if ( ! function_exists( 'openssl_sign' ) ) {
            wp_send_json_error( [
                'message' => __( 'La extensión PHP openssl no está habilitada en este servidor. Es necesaria para esta integración.', 'aura-suite' ),
            ] );
        }

        $token = self::get_access_token();
        if ( ! $token ) {
            wp_send_json_error( [
                'message' => __( 'No se pudo obtener el token de Google. Verifica que el JSON sea correcto y que la Calendar API esté habilitada en Google Cloud.', 'aura-suite' ),
            ] );
        }

        // Limpiar caché del calendario para re-resolver
        delete_option( self::CAL_ID_OPTION );
        $cal_id = self::get_or_create_calendar( self::CALENDAR_NAME, self::CAL_ID_OPTION );
        if ( ! $cal_id ) {
            wp_send_json_error( [
                'message' => __( 'Token válido pero no se pudo crear/acceder al calendario. Asegúrate de que la Calendar API esté habilitada en Google Cloud Console.', 'aura-suite' ),
            ] );
        }

        // Forzar re-envío de invitaciones
        $share_results = self::share_calendar( $cal_id, true );

        $invited = array_keys( array_filter( $share_results, fn( $s ) => $s === 'invited' ) );
        $already = array_keys( array_filter( $share_results, fn( $s ) => $s === 'already' ) );
        $failed  = array_keys( array_filter( $share_results, fn( $s ) => $s === 'failed' ) );

        $msg_parts = [];
        if ( $invited ) { $msg_parts[] = sprintf( __( 'Invitación enviada a: %s', 'aura-suite' ),  implode( ', ', $invited ) ); }
        if ( $already ) { $msg_parts[] = sprintf( __( 'Ya tenían acceso: %s', 'aura-suite' ),      implode( ', ', $already ) ); }
        if ( $failed  ) { $msg_parts[] = sprintf( __( 'Fallo al invitar: %s', 'aura-suite' ),      implode( ', ', $failed  ) ); }

        $share_detail = $msg_parts
            ? implode( ' | ', $msg_parts )
            : __( 'Sin destinatarios configurados.', 'aura-suite' );

        $creds = self::get_credentials();
        wp_send_json_success( [
            'message'       => sprintf(
                __( '✅ Conexión exitosa. Calendario "%1$s" listo. %2$s', 'aura-suite' ),
                self::CALENDAR_NAME,
                $share_detail
            ),
            'calendar_id'   => $cal_id,
            'service_email' => $creds['client_email'] ?? '',
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Guardar configuración GCal (nonce global: save_aura_settings)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_settings(): void {
        check_ajax_referer( 'save_aura_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        update_option( 'aura_gcal_enabled', ! empty( $_POST['gcal_enabled'] ) ? '1' : '0' );

        if ( isset( $_POST['gcal_share_email'] ) ) {
            $emails_raw = wp_unslash( $_POST['gcal_share_email'] );
            $emails     = array_filter( array_map( function( $e ) {
                return sanitize_email( trim( $e ) );
            }, explode( ',', $emails_raw ) ) );
            update_option( 'aura_gcal_share_email', implode( ', ', $emails ) );
        }

        if ( isset( $_POST['service_account_json'] ) && trim( $_POST['service_account_json'] ) !== '' ) {
            $raw    = wp_unslash( $_POST['service_account_json'] );
            $parsed = json_decode( $raw, true );
            if ( ! is_array( $parsed ) || ( $parsed['type'] ?? '' ) !== 'service_account' ) {
                wp_send_json_error( [ 'message' => __( 'El JSON no corresponde a una Service Account válida.', 'aura-suite' ) ] );
            }
            update_option( 'aura_gcal_service_account_json', $raw );
        }

        if ( isset( $_POST['reminder_days'] ) ) {
            $sanitized = implode( ',', array_filter( array_map(
                function( $v ) {
                    $n = intval( $v );
                    return ( $n >= 1 && $n <= 28 ) ? $n : null;
                },
                explode( ',', $_POST['reminder_days'] )
            ) ) );
            update_option( 'aura_gcal_reminder_days', $sanitized ?: '15,7,3,1' );
        }

        delete_transient( self::TOKEN_TRANSIENT );
        delete_option( self::CAL_ID_OPTION );

        wp_send_json_success( [ 'message' => __( 'Configuración de Google Calendar guardada.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Prueba de WhatsApp (nonce global: save_aura_settings)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_test_whatsapp(): void {
        check_ajax_referer( 'save_aura_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        if ( empty( $phone ) ) {
            wp_send_json_error( [ 'message' => __( 'Ingresa un número de teléfono de destino.', 'aura-suite' ) ] );
        }

        $message = sprintf(
            __( '✅ Prueba AURA Business Suite — %s. Si recibes este mensaje, WhatsApp está configurado correctamente.', 'aura-suite' ),
            date_i18n( 'd/m/Y H:i' )
        );

        $ok = Aura_Notifications::send_whatsapp( $phone, $message );

        if ( $ok ) {
            wp_send_json_success( [ 'message' => sprintf( __( '✅ Mensaje de prueba enviado a %s.', 'aura-suite' ), $phone ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( '❌ No se pudo enviar el mensaje. Verifica el proveedor, el token API y el número teléfono.', 'aura-suite' ) ] );
        }
    }
}
