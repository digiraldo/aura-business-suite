<?php
/**
 * Aura Vehicles REST API — Fase 2
 * Registra todos los endpoints REST del módulo de vehículos bajo /aura/v1/.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Vehicles {

    const NS   = 'aura/v1';
    const BASE = 'vehicles';

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

        // ── Colección ─────────────────────────────────────────────
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

        // ── Ítem ──────────────────────────────────────────────────
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

        // ── Asignar área ──────────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/areas', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'assign_area' ),
            'permission_callback' => array( __CLASS__, 'can_edit' ),
        ) );

        // ── Desasignar área ───────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/areas/(?P<area_id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( __CLASS__, 'unassign_area' ),
            'permission_callback' => array( __CLASS__, 'can_edit' ),
        ) );

        // ── Dar de baja ───────────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/unavailable', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'mark_unavailable' ),
            'permission_callback' => array( __CLASS__, 'can_edit' ),
        ) );

        // ── Restaurar ─────────────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/restore', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'restore' ),
            'permission_callback' => array( __CLASS__, 'can_edit' ),
        ) );

        // ── Transferir ────────────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/transfer', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'transfer' ),
            'permission_callback' => array( __CLASS__, 'can_edit' ),
        ) );

        // ── Fotos ─────────────────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)/photos', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'upload_photo' ),
                'permission_callback' => array( __CLASS__, 'can_edit' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( __CLASS__, 'delete_photo' ),
                'permission_callback' => array( __CLASS__, 'can_edit' ),
            ),
        ) );

        // ── Dropdown de áreas ─────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/areas-dropdown', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'areas_dropdown' ),
            'permission_callback' => array( __CLASS__, 'can_view' ),
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // PERMISSION CALLBACKS
    // ─────────────────────────────────────────────────────────────

    public static function can_view(): bool {
        return is_user_logged_in() && (
            current_user_can( 'aura_vehicles_view_all' ) ||
            current_user_can( 'manage_options' ) ||
            self::user_has_vehicle_area()
        );
    }

    public static function can_create(): bool {
        return is_user_logged_in() && (
            current_user_can( 'aura_vehicles_create' ) ||
            current_user_can( 'manage_options' )
        );
    }

    public static function can_edit(): bool {
        return is_user_logged_in() && (
            current_user_can( 'aura_vehicles_edit' ) ||
            current_user_can( 'manage_options' )
        );
    }

    public static function can_delete(): bool {
        return is_user_logged_in() && (
            current_user_can( 'aura_vehicles_delete' ) ||
            current_user_can( 'manage_options' )
        );
    }

    /**
     * Verificar que el usuario pertenezca a al menos un área.
     */
    private static function user_has_vehicle_area(): bool {
        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aura_area_users WHERE user_id = %d",
            get_current_user_id()
        ) );
        return $count > 0;
    }

    /**
     * Brecha #3 — CBAC: verificar que el usuario tiene acceso a un vehículo concreto
     * por pertenecer a alguna de las áreas a las que ese vehículo está asignado.
     */
    private static function user_can_access_vehicle( int $vehicle_id ): bool {
        global $wpdb;
        $user_id = get_current_user_id();
        $count   = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}aura_vehicle_area va
             INNER JOIN {$wpdb->prefix}aura_area_users au ON au.area_id = va.area_id
             WHERE va.vehicle_id = %d AND au.user_id = %d",
            $vehicle_id,
            $user_id
        ) );
        return $count > 0;
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS DE RUTA
    // ─────────────────────────────────────────────────────────────

    public static function get_list( WP_REST_Request $request ): WP_REST_Response {
        $filters = array(
            'page'     => (int) $request->get_param( 'page' ),
            'per_page' => (int) $request->get_param( 'per_page' ),
            'search'   => $request->get_param( 'search' ),
            'status'   => $request->get_param( 'status' ),
            'type'     => $request->get_param( 'type' ),
            'area_id'  => (int) $request->get_param( 'area_id' ),
            'sort_by'  => $request->get_param( 'sort_by' ),
            'sort_dir' => $request->get_param( 'sort_dir' ),
        );
        return rest_ensure_response( Aura_Vehicle_Manager::get_list( $filters ) );
    }

    public static function create( WP_REST_Request $request ) {
        $data   = $request->get_json_params() ?: (array) $request->get_body_params();
        $result = Aura_Vehicle_Manager::create( $data );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        $response = rest_ensure_response( array(
            'id'      => $result,
            'message' => __( 'Vehículo registrado correctamente.', 'aura-suite' ),
        ) );
        $response->set_status( 201 );

        return $response;
    }

    public static function get_one( WP_REST_Request $request ) {
        $id      = (int) $request->get_param( 'id' );
        $vehicle = Aura_Vehicle_Manager::get( $id );

        if ( ! $vehicle ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ), array( 'status' => 404 ) );
        }

        // Brecha #3 — CBAC: verificar acceso por área cuando el usuario no tiene view_all.
        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            if ( ! self::user_can_access_vehicle( $id ) ) {
                return new WP_Error( 'forbidden', __( 'No tienes acceso a este vehículo.', 'aura-suite' ), array( 'status' => 403 ) );
            }
        }

        return rest_ensure_response( $vehicle );
    }

    public static function update( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $data   = $request->get_json_params() ?: (array) $request->get_body_params();
        $result = Aura_Vehicle_Manager::update( $id, $data );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Vehículo actualizado correctamente.', 'aura-suite' ) ) );
    }

    public static function delete( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $result = Aura_Vehicle_Manager::delete( $id );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Vehículo eliminado correctamente.', 'aura-suite' ) ) );
    }

    public static function assign_area( WP_REST_Request $request ) {
        $id      = (int) $request->get_param( 'id' );
        $data    = $request->get_json_params() ?: (array) $request->get_body_params();
        $area_id = (int) ( $data['area_id'] ?? 0 );

        if ( ! $area_id ) {
            return new WP_Error( 'missing_area', __( 'El área es obligatoria.', 'aura-suite' ), array( 'status' => 422 ) );
        }

        $result = Aura_Vehicle_Manager::assign_area( $id, $area_id );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Área asignada correctamente.', 'aura-suite' ) ) );
    }

    public static function unassign_area( WP_REST_Request $request ) {
        $id      = (int) $request->get_param( 'id' );
        $area_id = (int) $request->get_param( 'area_id' );
        $result  = Aura_Vehicle_Manager::unassign_area( $id, $area_id );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Área desasignada correctamente.', 'aura-suite' ) ) );
    }

    public static function mark_unavailable( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $data   = $request->get_json_params() ?: (array) $request->get_body_params();
        $result = Aura_Vehicle_Manager::mark_unavailable( $id, $data );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Vehículo dado de baja correctamente.', 'aura-suite' ) ) );
    }

    public static function restore( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $result = Aura_Vehicle_Manager::restore( $id );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Vehículo restaurado correctamente.', 'aura-suite' ) ) );
    }

    public static function transfer( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $data = $request->get_json_params() ?: (array) $request->get_body_params();
        $from = (int) ( $data['from_area'] ?? 0 );
        $to   = (int) ( $data['to_area']   ?? 0 );

        if ( ! $from || ! $to ) {
            return new WP_Error(
                'missing_areas',
                __( 'Los campos from_area y to_area son obligatorios.', 'aura-suite' ),
                array( 'status' => 422 )
            );
        }

        $result = Aura_Vehicle_Manager::transfer( $id, $from, $to );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Vehículo transferido correctamente.', 'aura-suite' ) ) );
    }

    public static function upload_photo( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $file = $request->get_file_params()['photo'] ?? null;

        if ( ! $file || empty( $file['tmp_name'] ) ) {
            return new WP_Error( 'missing_file', __( 'No se recibió ninguna imagen.', 'aura-suite' ), array( 'status' => 422 ) );
        }

        $result = Aura_Vehicle_Manager::upload_photo( $id, $file );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        $response = rest_ensure_response( array(
            'url'     => $result,
            'message' => __( 'Foto subida correctamente.', 'aura-suite' ),
        ) );
        $response->set_status( 201 );

        return $response;
    }

    public static function delete_photo( WP_REST_Request $request ) {
        $id       = (int) $request->get_param( 'id' );
        $data     = $request->get_json_params() ?: (array) $request->get_body_params();
        $filename = sanitize_text_field( $data['filename'] ?? '' );

        if ( ! $filename ) {
            return new WP_Error( 'missing_filename', __( 'El nombre del archivo es obligatorio.', 'aura-suite' ), array( 'status' => 422 ) );
        }

        $result = Aura_Vehicle_Manager::delete_photo( $id, $filename );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
        }

        return rest_ensure_response( array( 'message' => __( 'Foto eliminada correctamente.', 'aura-suite' ) ) );
    }

    public static function areas_dropdown(): WP_REST_Response {
        global $wpdb;
        $areas = $wpdb->get_results(
            "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
        ) ?: array();

        return rest_ensure_response( array( 'items' => $areas ) );
    }
}
