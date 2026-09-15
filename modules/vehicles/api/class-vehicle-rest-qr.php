<?php
/**
 * Aura Vehicle REST — QR
 * Endpoints para generar, consultar e invalidar el token QR de un vehículo.
 *
 * GET    /wp-json/aura/v1/vehicles/{id}/qr        → info del QR (token + URL pública)
 * POST   /wp-json/aura/v1/vehicles/{id}/qr        → genera token (idempotente: solo si no existe)
 * DELETE /wp-json/aura/v1/vehicles/{id}/qr        → invalida / regenera token
 * GET    /wp-json/aura/v1/vehicles/qr/{token}     → validar token (público) y retornar datos del vehículo
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Qr {

    const NAMESPACE = 'aura/v1';

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

        // Registrar query var ?vqr= para que WordPress lo reconozca
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );

        // Interceptar la petición y renderizar el template móvil
        add_action( 'template_redirect', array( __CLASS__, 'maybe_render_mobile_page' ) );
    }

    /** Agrega el query var al sistema de WordPress */
    public static function add_query_vars( array $vars ): array {
        $vars[] = 'vqr';
        return $vars;
    }

    /** Si la URL contiene ?vqr=, renderiza el template móvil y termina */
    public static function maybe_render_mobile_page(): void {
        $token = get_query_var( 'vqr', '' );
        if ( empty( $token ) ) {
            return;
        }

        $template = plugin_dir_path( __FILE__ ) . '../templates/vehicle-qr-mobile.php';
        if ( file_exists( $template ) ) {
            include $template;
            exit;
        }
    }

    public static function register_routes(): void {
        // GET/POST /vehicles/{id}/qr
        register_rest_route( self::NAMESPACE, '/vehicles/(?P<id>\d+)/qr', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_qr' ),
                'permission_callback' => array( __CLASS__, 'perm_manage' ),
                'args'                => array(
                    'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'generate_qr' ),
                'permission_callback' => array( __CLASS__, 'perm_manage' ),
                'args'                => array(
                    'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( __CLASS__, 'invalidate_qr' ),
                'permission_callback' => array( __CLASS__, 'perm_manage' ),
                'args'                => array(
                    'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
                ),
            ),
        ) );

        // GET /vehicles/qr/{token}  — PÚBLICO, sin auth
        register_rest_route( self::NAMESPACE, '/vehicles/qr/(?P<token>[a-f0-9]{8,16})', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'validate_token' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'token' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        // POST /vehicles/qr/{token}/trip  — registrar salida / retorno (PÚBLICO)
        register_rest_route( self::NAMESPACE, '/vehicles/qr/(?P<token>[a-f0-9]{8,16})/trip', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'register_trip_via_qr' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'token'       => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'action'      => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'driver_name' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // PERMISOS
    // ─────────────────────────────────────────────────────────────

    public static function perm_manage(): bool {
        return current_user_can( 'aura_vehicles_edit' ) || current_user_can( 'manage_options' );
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS
    // ─────────────────────────────────────────────────────────────

    /** GET /vehicles/{id}/qr */
    public static function get_qr( WP_REST_Request $req ): WP_REST_Response {
        $vehicle = self::get_vehicle( $req['id'] );
        if ( is_wp_error( $vehicle ) ) {
            return rest_ensure_response( $vehicle );
        }

        if ( empty( $vehicle->qr_token ) ) {
            return new WP_REST_Response( array(
                'has_qr'  => false,
                'message' => __( 'Este vehículo aún no tiene QR generado.', 'aura-suite' ),
            ), 200 );
        }

        return new WP_REST_Response( array(
            'has_qr'          => true,
            'qr_token'        => $vehicle->qr_token,
            'qr_generated_at' => $vehicle->qr_generated_at,
            'qr_url'          => self::build_url( $vehicle->qr_token ),
        ), 200 );
    }

    /** POST /vehicles/{id}/qr  — Idempotente: solo genera si no existe */
    public static function generate_qr( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        $vehicle = self::get_vehicle( $req['id'] );
        if ( is_wp_error( $vehicle ) ) {
            return rest_ensure_response( $vehicle );
        }

        // Si ya tiene token, devolver el existente sin tocar nada
        if ( ! empty( $vehicle->qr_token ) ) {
            return new WP_REST_Response( array(
                'generated'       => false,
                'qr_token'        => $vehicle->qr_token,
                'qr_generated_at' => $vehicle->qr_generated_at,
                'qr_url'          => self::build_url( $vehicle->qr_token ),
            ), 200 );
        }

        // Generar token seguro de 8 chars hex
        $token = bin2hex( random_bytes( 4 ) );
        $now   = current_time( 'mysql' );

        $updated = $wpdb->update(
            $wpdb->prefix . 'aura_vehicles',
            array( 'qr_token' => $token, 'qr_generated_at' => $now ),
            array( 'id' => $req['id'] ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            return new WP_REST_Response( array( 'error' => __( 'Error al guardar el QR.', 'aura-suite' ) ), 500 );
        }

        Aura_Vehicle_Audit_Manager::log( 'qr_generated', 'vehicle', $req['id'], array(
            'plate' => $vehicle->plate,
        ) );

        return new WP_REST_Response( array(
            'generated'       => true,
            'qr_token'        => $token,
            'qr_generated_at' => $now,
            'qr_url'          => self::build_url( $token ),
        ), 201 );
    }

    /** DELETE /vehicles/{id}/qr  — Invalida y genera un token nuevo */
    public static function invalidate_qr( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        $vehicle = self::get_vehicle( $req['id'] );
        if ( is_wp_error( $vehicle ) ) {
            return rest_ensure_response( $vehicle );
        }

        $token = bin2hex( random_bytes( 4 ) );
        $now   = current_time( 'mysql' );

        $wpdb->update(
            $wpdb->prefix . 'aura_vehicles',
            array( 'qr_token' => $token, 'qr_generated_at' => $now ),
            array( 'id' => $req['id'] ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        Aura_Vehicle_Audit_Manager::log( 'qr_invalidated', 'vehicle', $req['id'], array(
            'plate' => $vehicle->plate,
        ) );

        return new WP_REST_Response( array(
            'regenerated'     => true,
            'qr_token'        => $token,
            'qr_generated_at' => $now,
            'qr_url'          => self::build_url( $token ),
        ), 200 );
    }

    /**
     * GET /vehicles/qr/{token}
     * Público. Devuelve datos del vehículo + estado de salida activa.
     */
    public static function validate_token( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        $token   = sanitize_text_field( $req['token'] );
        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, plate, brand, model, year, color, type, status, mileage, photo
               FROM {$wpdb->prefix}aura_vehicles
              WHERE qr_token = %s AND active = 1",
            $token
        ) );

        if ( ! $vehicle ) {
            return new WP_REST_Response( array(
                'valid'   => false,
                'message' => __( 'QR inválido, expirado o vehículo inactivo.', 'aura-suite' ),
            ), 404 );
        }

        // Salida activa para este vehículo
        $active_trip = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, trip_type, responsible_name, client_name, departure_datetime, departure_odometer, destination, purpose
               FROM {$wpdb->prefix}aura_vehicle_trips
              WHERE vehicle_id = %d AND status = 'active' AND deleted = 0
              ORDER BY departure_datetime DESC
              LIMIT 1",
            $vehicle->id
        ) );

        // URL de foto del vehículo
        $photo_url = '';
        if ( ! empty( $vehicle->photo ) ) {
            $photo_url = (string) ( wp_get_attachment_image_url( (int) $vehicle->photo, 'medium' ) ?: '' );
        }

        $type_labels = array(
            'sedan'      => 'Sedán',
            'suv'        => 'SUV',
            'pickup'     => 'Pickup',
            'van'        => 'Van',
            'bus'        => 'Bus',
            'motorcycle' => 'Moto',
            'truck'      => 'Camión',
            'other'      => 'Otro',
        );

        return new WP_REST_Response( array(
            'valid'      => true,
            'vehicle'    => array(
                'id'        => (int) $vehicle->id,
                'plate'     => $vehicle->plate,
                'brand'     => $vehicle->brand,
                'model'     => $vehicle->model,
                'year'      => $vehicle->year,
                'color'     => $vehicle->color,
                'type'      => $vehicle->type,
                'type_label'=> $type_labels[ $vehicle->type ] ?? $vehicle->type,
                'status'    => $vehicle->status,
                'mileage'   => (int) $vehicle->mileage,
                'photo_url' => $photo_url,
            ),
            'active_trip' => $active_trip ? array(
                'id'                 => (int) $active_trip->id,
                'trip_type'          => $active_trip->trip_type,
                'responsible_name'   => $active_trip->responsible_name,
                'client_name'        => $active_trip->client_name,
                'departure_datetime' => $active_trip->departure_datetime,
                'destination'        => $active_trip->destination,
                'purpose'            => $active_trip->purpose,
            ) : null,
        ), 200 );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /vehicles/qr/{token}/trip
     * Registra una salida (checkout) o retorno (return) desde el QR móvil.
     */
    public static function register_trip_via_qr( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        $token  = sanitize_text_field( $req['token'] );
        $action = sanitize_text_field( $req->get_param( 'action' ) );

        if ( ! in_array( $action, array( 'checkout', 'return' ), true ) ) {
            return new WP_REST_Response( array( 'message' => 'Acción no válida.' ), 400 );
        }

        // Obtener vehículo por token
        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, plate, brand, model, mileage, status
               FROM {$wpdb->prefix}aura_vehicles
              WHERE qr_token = %s AND active = 1",
            $token
        ) );

        if ( ! $vehicle ) {
            return new WP_REST_Response( array( 'message' => 'QR inválido o vehículo no encontrado.' ), 404 );
        }

        $driver_name = sanitize_text_field( $req->get_param( 'driver_name' ) );
        if ( empty( $driver_name ) ) {
            return new WP_REST_Response( array( 'message' => 'El nombre del conductor es obligatorio.' ), 400 );
        }

        $now = current_time( 'mysql' );

        if ( 'checkout' === $action ) {
            // Verificar que el vehículo esté disponible
            if ( ! in_array( $vehicle->status, array( 'available' ), true ) ) {
                return new WP_REST_Response( array( 'message' => 'El vehículo no está disponible para salida.' ), 409 );
            }

            // Verificar que no tenga salida activa
            $active = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aura_vehicle_trips
                  WHERE vehicle_id = %d AND status = 'active' AND deleted = 0 LIMIT 1",
                $vehicle->id
            ) );
            if ( $active ) {
                return new WP_REST_Response( array( 'message' => 'Este vehículo ya tiene una salida activa.' ), 409 );
            }

            $purpose = sanitize_text_field( $req->get_param( 'purpose' ) ?? '' );
            $mileage = absint( $req->get_param( 'mileage' ) ?? $vehicle->mileage );

            $allowed_types = array( 'rental', 'errand', 'maintenance', 'other' );
            $trip_type     = sanitize_text_field( $req->get_param( 'trip_type' ) ?? '' );
            if ( ! in_array( $trip_type, $allowed_types, true ) ) {
                $trip_type = 'errand';
            }

            // Si es mantenimiento, el estado del vehículo será 'maintenance'
            $new_vehicle_status = ( 'maintenance' === $trip_type ) ? 'maintenance' : 'rented';

            $inserted = $wpdb->insert(
                $wpdb->prefix . 'aura_vehicle_trips',
                array(
                    'vehicle_id'          => $vehicle->id,
                    'trip_type'           => $trip_type,
                    'responsible_name'    => $driver_name,
                    'destination'         => $purpose,
                    'purpose'             => $purpose,
                    'departure_datetime'  => $now,
                    'departure_odometer'  => $mileage,
                    'status'              => 'active',
                    'deleted'             => 0,
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
            );

            if ( ! $inserted ) {
                return new WP_REST_Response( array( 'message' => 'Error al registrar la salida.' ), 500 );
            }

            $trip_id = $wpdb->insert_id;

            // Actualizar estado del vehículo
            $wpdb->update(
                $wpdb->prefix . 'aura_vehicles',
                array( 'status' => $new_vehicle_status, 'mileage' => $mileage ),
                array( 'id'     => $vehicle->id ),
                array( '%s', '%d' ),
                array( '%d' )
            );

            return new WP_REST_Response( array(
                'success'  => true,
                'action'   => 'checkout',
                'trip_id'  => $trip_id,
                'message'  => 'Salida registrada correctamente.',
            ), 201 );

        } else {
            // RETURN — registrar retorno
            $trip_id = absint( $req->get_param( 'trip_id' ) ?? 0 );
            $mileage = absint( $req->get_param( 'mileage' ) ?? $vehicle->mileage );
            $notes   = sanitize_textarea_field( $req->get_param( 'notes' ) ?? '' );

            // Buscar salida activa si no se proveyó ID
            if ( ! $trip_id ) {
                $trip_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}aura_vehicle_trips
                      WHERE vehicle_id = %d AND status = 'active' AND deleted = 0 LIMIT 1",
                    $vehicle->id
                ) );
            }

            if ( ! $trip_id ) {
                return new WP_REST_Response( array( 'message' => 'No se encontró salida activa para este vehículo.' ), 404 );
            }

            $wpdb->update(
                $wpdb->prefix . 'aura_vehicle_trips',
                array(
                    'status'               => 'completed',
                    'return_datetime'      => $now,
                    'return_odometer'      => $mileage,
                    'notes'                => $notes,
                ),
                array( 'id' => $trip_id ),
                array( '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );

            // Actualizar estado del vehículo
            $wpdb->update(
                $wpdb->prefix . 'aura_vehicles',
                array( 'status' => 'available', 'mileage' => $mileage ),
                array( 'id'     => $vehicle->id ),
                array( '%s', '%d' ),
                array( '%d' )
            );

            return new WP_REST_Response( array(
                'success'  => true,
                'action'   => 'return',
                'trip_id'  => $trip_id,
                'message'  => 'Retorno registrado correctamente.',
            ), 200 );
        }
    }

    private static function get_vehicle( int $id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, plate, brand, model, qr_token, qr_generated_at, photo
               FROM {$wpdb->prefix}aura_vehicles
              WHERE id = %d AND active = 1",
            $id
        ) );

        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ), array( 'status' => 404 ) );
        }
        return $row;
    }

    /** Construye la URL pública que se codifica en el QR */
    public static function build_url( string $token ): string {
        return add_query_arg( 'vqr', $token, get_site_url() );
    }

    /**
     * AJAX handler: login desde la página móvil de QR.
     * Acepta usuario/pass y devuelve JSON {success, data}.
     * Sin sesión previa (wp_ajax_nopriv).
     */
    public static function ajax_qr_login(): void {
        // Limpiar output buffer para que el JSON no llegue contaminado con HTML/notices.
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        // ── Verificar nonce ──────────────────────────────────────
        if ( ! check_ajax_referer( 'aura_qr_login', '_wpnonce', false ) ) {
            wp_send_json_error( 'Sesión expirada. Recarga la página e intenta de nuevo.', 403 );
            return;
        }

        // ── Leer credenciales ─────────────────────────────────────
        // sanitize_text_field conserva el valor intacto (a diferencia de
        // sanitize_user que puede eliminar caracteres); wp_unslash elimina
        // los magic-quotes que WordPress añade a $_POST.
        $log  = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) );
        $pwd  = wp_unslash( $_POST['pwd'] ?? '' );

        if ( empty( $log ) || empty( $pwd ) ) {
            wp_send_json_error( 'Usuario y contraseña requeridos.' );
            return;
        }

        // ── Autenticar con wp_signon ──────────────────────────────
        // wp_signon() es el método oficial de WordPress para login programático:
        // verifica credenciales, establece cookies y dispara el hook wp_login.
        // El segundo parámetro (secure_cookie) usa HTTPS automáticamente cuando
        // la petición llega por SSL, evitando conflictos de cookies.
        $user = wp_signon(
            array(
                'user_login'    => $log,
                'user_password' => $pwd,
                'remember'      => false,
            ),
            is_ssl()
        );

        if ( is_wp_error( $user ) ) {
            // En modo debug se expone el mensaje real para diagnóstico;
            // en producción siempre se muestra el mensaje genérico.
            $msg = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
                ? '[DEBUG] ' . $user->get_error_message()
                : 'Usuario o contraseña incorrectos.';
            wp_send_json_error( $msg );
            return;
        }

        wp_send_json_success( array( 'display_name' => $user->display_name ) );
    }
}
