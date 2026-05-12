<?php
/**
 * Aura Vehicles REST API — Trips (Fase 3)
 * Endpoints para el ciclo de vida de salidas vehiculares.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Trips {

    const NS   = 'aura/v1';
    const BASE = 'vehicles/trips';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // RUTAS
    // ─────────────────────────────────────────────────────────────

    public static function register_routes(): void {
        $ns   = self::NS;
        $base = self::BASE;

        // Colección: listar / crear
        register_rest_route( $ns, '/' . $base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_list' ),
                'permission_callback' => array( __CLASS__, 'can_view' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'create' ),
                'permission_callback' => array( __CLASS__, 'can_create' ),
            ),
        ) );

        // Ítem individual: GET / PUT
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_one' ),
                'permission_callback' => array( __CLASS__, 'can_view' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update' ),
                'permission_callback' => array( __CLASS__, 'can_edit' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( __CLASS__, 'delete' ),
                'permission_callback' => array( __CLASS__, 'can_delete' ),
            ),
        ) );

        // Check-in (retorno)
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/checkin', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'checkin' ),
                'permission_callback' => array( __CLASS__, 'can_checkin' ),
            ),
        ) );

        // Cancelar
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/cancel', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'cancel' ),
                'permission_callback' => array( __CLASS__, 'can_edit' ),
            ),
        ) );

        // Vehículos disponibles para la lista de selección
        register_rest_route( $ns, '/vehicles/available-for-trip', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'available_vehicles' ),
                'permission_callback' => array( __CLASS__, 'can_create' ),
            ),
        ) );

        // Usuarios WP (para selector de responsable en encargos)
        register_rest_route( $ns, '/vehicles/users-dropdown', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'users_dropdown' ),
                'permission_callback' => array( __CLASS__, 'can_create' ),
            ),
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /aura/v1/vehicles/trips
     */
    public static function get_list( WP_REST_Request $request ): WP_REST_Response {
        $result = Aura_Vehicle_Trip_Manager::get_list( array(
            'type'      => $request->get_param( 'type' ),
            'status'    => $request->get_param( 'status' ),
            'vehicle_id'=> absint( $request->get_param( 'vehicle_id' ) ),
            'area_id'   => absint( $request->get_param( 'area_id' ) ),
            'date_from' => $request->get_param( 'date_from' ),
            'date_to'   => $request->get_param( 'date_to' ),
            'page'      => absint( $request->get_param( 'page' ) ?: 1 ),
            'per_page'  => absint( $request->get_param( 'per_page' ) ?: 20 ),
            'sort_by'   => $request->get_param( 'sort_by' ),
            'sort_dir'  => $request->get_param( 'sort_dir' ),
        ) );
        return new WP_REST_Response( $result, 200 );
    }

    /**
     * POST /aura/v1/vehicles/trips
     */
    public static function create( WP_REST_Request $request ): WP_REST_Response {
        $result = Aura_Vehicle_Trip_Manager::create( $request->get_json_params() ?: array() );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
                400
            );
        }

        return new WP_REST_Response( array( 'id' => $result ), 201 );
    }

    /**
     * GET /aura/v1/vehicles/trips/{id}
     */
    public static function get_one( WP_REST_Request $request ): WP_REST_Response {
        $trip = Aura_Vehicle_Trip_Manager::get( absint( $request->get_param( 'id' ) ) );

        if ( ! $trip ) {
            return new WP_REST_Response( array( 'message' => __( 'Salida no encontrada.', 'aura-suite' ) ), 404 );
        }

        // Brecha #4 — CBAC: verificar acceso por área/propietario cuando el usuario no tiene view_all.
        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            if ( ! self::user_can_access_trip( $trip ) ) {
                return new WP_REST_Response( array( 'message' => __( 'No tienes acceso a esta salida.', 'aura-suite' ) ), 403 );
            }
        }

        return new WP_REST_Response( $trip, 200 );
    }

    /**
     * PUT|PATCH /aura/v1/vehicles/trips/{id}
     */
    public static function update( WP_REST_Request $request ): WP_REST_Response {
        $result = Aura_Vehicle_Trip_Manager::update(
            absint( $request->get_param( 'id' ) ),
            $request->get_json_params() ?: array()
        );

        if ( is_wp_error( $result ) ) {
            $status_code = 403 === ( $result->get_error_data()['status'] ?? 400 ) ? 403 : 400;
            return new WP_REST_Response(
                array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
                $status_code
            );
        }

        return new WP_REST_Response( array( 'updated' => true ), 200 );
    }

    /**
     * DELETE /aura/v1/vehicles/trips/{id}
     */
    public static function delete( WP_REST_Request $request ): WP_REST_Response {
        $result = Aura_Vehicle_Trip_Manager::delete( absint( $request->get_param( 'id' ) ) );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
                400
            );
        }

        return new WP_REST_Response( array( 'deleted' => true ), 200 );
    }

    /**
     * POST /aura/v1/vehicles/trips/{id}/checkin
     */
    public static function checkin( WP_REST_Request $request ): WP_REST_Response {
        $result = Aura_Vehicle_Trip_Manager::check_in(
            absint( $request->get_param( 'id' ) ),
            $request->get_json_params() ?: array()
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
                400
            );
        }

        return new WP_REST_Response( array( 'checked_in' => true ), 200 );
    }

    /**
     * POST /aura/v1/vehicles/trips/{id}/cancel
     */
    public static function cancel( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params() ?: array();
        $reason = sanitize_text_field( $params['reason'] ?? '' );

        $result = Aura_Vehicle_Trip_Manager::cancel(
            absint( $request->get_param( 'id' ) ),
            $reason
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
                400
            );
        }

        return new WP_REST_Response( array( 'cancelled' => true ), 200 );
    }

    /**
     * GET /aura/v1/vehicles/available-for-trip
     * Lista solo vehículos disponibles (status=available) para el selector del formulario de salida.
     */
    public static function available_vehicles( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $type = sanitize_text_field( $request->get_param( 'type' ) ?: '' );

        // Para mantenimiento también se muestran los en 'maintenance'
        $statuses = ( 'maintenance' === $type )
            ? array( 'available', 'maintenance' )
            : array( 'available' );

        // Brecha #6 — CBAC: filtrar por áreas del usuario si no tiene view_all.
        $area_sql = '';
        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            $uid      = (int) get_current_user_id();
            $area_sql = " AND id IN (
                SELECT va.vehicle_id
                FROM {$wpdb->prefix}aura_vehicle_area va
                INNER JOIN {$wpdb->prefix}aura_area_users au ON au.area_id = va.area_id
                WHERE au.user_id = {$uid}
            )";
        }

        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $rows         = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, plate, brand, model, status, mileage, rate_per_km
                 FROM {$wpdb->prefix}aura_vehicles
                 WHERE status IN ({$placeholders}) AND active = 1{$area_sql}
                 ORDER BY brand ASC, model ASC",
                ...$statuses
            ),
            ARRAY_A
        ) ?: array();

        return new WP_REST_Response( array( 'items' => $rows ), 200 );
    }

    /**
     * GET /aura/v1/vehicles/users-dropdown
     * Usuarios con capability de crear/editar salidas para selector de responsable.
     */
    public static function users_dropdown( WP_REST_Request $request ): WP_REST_Response {
        $users = get_users( array(
            'fields'  => array( 'ID', 'display_name' ),
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 200,
        ) );

        $items = array_map( function ( $u ) {
            return array( 'id' => (int) $u->ID, 'name' => $u->display_name );
        }, $users );

        return new WP_REST_Response( array( 'items' => $items ), 200 );
    }

    // ─────────────────────────────────────────────────────────────
    // PERMISSION CALLBACKS
    // ─────────────────────────────────────────────────────────────

    public static function can_view(): bool {
        if ( ! is_user_logged_in() ) return false;
        return current_user_can( 'aura_vehicles_view_all' )
            || current_user_can( 'aura_vehicles_exits_create' )
            || current_user_can( 'manage_options' );
    }

    public static function can_create(): bool {
        if ( ! is_user_logged_in() ) return false;
        return current_user_can( 'aura_vehicles_exits_create' )
            || current_user_can( 'manage_options' );
    }

    public static function can_edit( WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) return false;

        // exits_edit_all o manage_options → acceso pleno
        if ( current_user_can( 'aura_vehicles_exits_edit_all' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        // exits_edit_own → comprobación real se hace en Trip_Manager::update()
        return current_user_can( 'aura_vehicles_exits_edit_own' );
    }

    public static function can_checkin( WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) return false;

        if ( current_user_can( 'aura_vehicles_exits_edit_all' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        // exits_edit_own también puede hacer checkin de sus propias salidas.
        // aura_vehicles_km_update permite registrar retorno/kilometraje (brecha #1 corregida).
        return current_user_can( 'aura_vehicles_exits_edit_own' )
            || current_user_can( 'aura_vehicles_km_update' );
    }

    public static function can_delete( WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) return false;

        if ( current_user_can( 'aura_vehicles_exits_delete_all' )
            || current_user_can( 'aura_vehicles_delete' )
            || current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( ! current_user_can( 'aura_vehicles_exits_delete_own' ) ) {
            return false;
        }

        $trip = Aura_Vehicle_Trip_Manager::get( absint( $request->get_param( 'id' ) ) );
        if ( ! $trip ) {
            return false;
        }

        return (int) ( $trip['created_by'] ?? 0 ) === (int) get_current_user_id();
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER CBAC PRIVADO
    // ─────────────────────────────────────────────────────────────

    /**
     * Brecha #4 — CBAC: verificar si el usuario tiene acceso a un trip concreto.
     * El acceso se cumple si:
     *   a) el usuario creó el trip, o
     *   b) el trip pertenece a un área donde el usuario está asignado.
     *
     * @param array $trip Array con claves 'created_by' y 'area_id' (resultado de Trip_Manager::get()).
     */
    private static function user_can_access_trip( array $trip ): bool {
        $user_id = get_current_user_id();

        if ( (int) ( $trip['created_by'] ?? 0 ) === $user_id ) {
            return true;
        }

        $area_id = (int) ( $trip['area_id'] ?? 0 );
        if ( ! $area_id ) {
            return false;
        }

        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aura_area_users WHERE area_id = %d AND user_id = %d",
            $area_id,
            $user_id
        ) );
        return $count > 0;
    }
}
