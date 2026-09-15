<?php
/**
 * Aura Vehicles REST API — Catálogos (Fase 4)
 * Endpoints para destinos, propósitos y gastos configurables.
 *
 * Namespace:  /wp-json/aura/v1/
 * Base path:  vehicles/catalogs
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Catalogs {

    const NS   = 'aura/v1';
    const BASE = 'vehicles/catalogs';

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

        // ── Reorder (antes que el ítem para que no lo capture el regex) ──
        register_rest_route( $ns, '/' . $base . '/reorder', array(
            'methods'             => 'PATCH',
            'callback'            => array( __CLASS__, 'reorder' ),
            'permission_callback' => array( __CLASS__, 'can_manage' ),
        ) );

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
                'permission_callback' => array( __CLASS__, 'can_manage' ),
            ),
        ) );

        // ── Ítem individual ───────────────────────────────────────
        register_rest_route( $ns, '/' . $base . '/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_one' ),
                'permission_callback' => array( __CLASS__, 'can_view' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update' ),
                'permission_callback' => array( __CLASS__, 'can_manage' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( __CLASS__, 'delete' ),
                'permission_callback' => array( __CLASS__, 'can_manage' ),
            ),
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // PERMISOS
    // ─────────────────────────────────────────────────────────────

    public static function can_view(): bool {
        return is_user_logged_in() && (
            current_user_can( 'aura_vehicles_settings' ) ||
            current_user_can( 'aura_vehicles_view_all' ) ||
            current_user_can( 'aura_vehicles_edit' ) ||
            current_user_can( 'aura_vehicles_exits_create' ) ||
            current_user_can( 'manage_options' )
        );
    }

    public static function can_manage(): bool {
        return is_user_logged_in() && (
            current_user_can( 'aura_vehicles_settings' ) ||
            current_user_can( 'aura_vehicles_edit' ) ||
            current_user_can( 'manage_options' )
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /aura/v1/vehicles/catalogs
     * Parámetros: type, area_id, include_inactive, include_global
     */
    public static function get_list( WP_REST_Request $request ): WP_REST_Response {
        $filters = array(
            'type'             => sanitize_text_field( $request->get_param( 'type' ) ?? '' ),
            'include_inactive' => (bool) $request->get_param( 'include_inactive' ),
            'include_global'   => $request->get_param( 'include_global' ) !== '0',
        );

        $area_id_param = $request->get_param( 'area_id' );
        if ( null !== $area_id_param ) {
            $filters['area_id'] = '' === $area_id_param ? null : (int) $area_id_param;
        }

        $items = Aura_Vehicle_Catalog_Manager::get_list( $filters );

        // Agrupar por tipo para facilidad en el frontend
        $grouped = array(
            'destination' => array(),
            'purpose'     => array(),
            'expense'     => array(),
        );

        foreach ( $items as $item ) {
            if ( isset( $grouped[ $item['type'] ] ) ) {
                $grouped[ $item['type'] ][] = $item;
            }
        }

        return rest_ensure_response( array(
            'items'   => $items,
            'grouped' => $grouped,
            'total'   => count( $items ),
        ) );
    }

    /**
     * GET /aura/v1/vehicles/catalogs/{id}
     */
    public static function get_one( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $id    = (int) $request->get_param( 'id' );
        $table = $wpdb->prefix . 'aura_vehicle_catalogs';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

        if ( ! $row ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Ítem de catálogo no encontrado.', 'aura-suite' ) ),
                404
            );
        }

        return rest_ensure_response( array(
            'id'          => (int)    $row['id'],
            'type'        =>          $row['type'],
            'name'        =>          $row['name'],
            'description' =>          $row['description'] ?? '',
            'icon'        =>          $row['icon']        ?? '',
            'active'      => (bool)   $row['active'],
            'sort_order'  => (int)    $row['sort_order'],
            'area_id'     => ! empty( $row['area_id'] ) ? (int) $row['area_id'] : null,
            'created_at'  =>          $row['created_at'],
            'updated_at'  =>          $row['updated_at'] ?? null,
        ) );
    }

    /**
     * POST /aura/v1/vehicles/catalogs
     */
    public static function create( WP_REST_Request $request ) {
        $data   = $request->get_json_params() ?: (array) $request->get_body_params();
        $result = Aura_Vehicle_Catalog_Manager::create( $data );

        if ( is_wp_error( $result ) ) {
            $status = 'catalog_not_found' === $result->get_error_code() ? 404 : 422;
            return new WP_REST_Response(
                array( 'message' => $result->get_error_message() ),
                $status
            );
        }

        $response = rest_ensure_response( array(
            'id'      => $result,
            'message' => __( 'Ítem de catálogo creado correctamente.', 'aura-suite' ),
        ) );
        $response->set_status( 201 );

        return $response;
    }

    /**
     * PUT /aura/v1/vehicles/catalogs/{id}
     */
    public static function update( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $data   = $request->get_json_params() ?: (array) $request->get_body_params();
        $result = Aura_Vehicle_Catalog_Manager::update( $id, $data );

        if ( is_wp_error( $result ) ) {
            $status = 'catalog_not_found' === $result->get_error_code() ? 404 : 422;
            return new WP_REST_Response(
                array( 'message' => $result->get_error_message() ),
                $status
            );
        }

        return rest_ensure_response( array(
            'id'      => $id,
            'message' => __( 'Ítem de catálogo actualizado correctamente.', 'aura-suite' ),
        ) );
    }

    /**
     * DELETE /aura/v1/vehicles/catalogs/{id}
     */
    public static function delete( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $result = Aura_Vehicle_Catalog_Manager::delete( $id );

        if ( is_wp_error( $result ) ) {
            $status = 'catalog_not_found' === $result->get_error_code() ? 404 : 422;
            return new WP_REST_Response(
                array( 'message' => $result->get_error_message() ),
                $status
            );
        }

        if ( 'deactivated' === $result ) {
            return rest_ensure_response( array(
                'id'          => $id,
                'deactivated' => true,
                'message'     => __( 'El ítem tiene salidas asociadas y fue desactivado (no eliminado).', 'aura-suite' ),
            ) );
        }

        return rest_ensure_response( array(
            'id'      => $id,
            'deleted' => true,
            'message' => __( 'Ítem de catálogo eliminado correctamente.', 'aura-suite' ),
        ) );
    }

    /**
     * PATCH /aura/v1/vehicles/catalogs/reorder
     * Body: { "ids": [3, 1, 2] }
     */
    public static function reorder( WP_REST_Request $request ) {
        $data = $request->get_json_params() ?: (array) $request->get_body_params();
        $ids  = isset( $data['ids'] ) && is_array( $data['ids'] ) ? array_map( 'intval', $data['ids'] ) : array();

        if ( empty( $ids ) ) {
            return new WP_REST_Response(
                array( 'message' => __( 'La lista de IDs no puede estar vacía.', 'aura-suite' ) ),
                422
            );
        }

        $result = Aura_Vehicle_Catalog_Manager::reorder( $ids );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 422 );
        }

        return rest_ensure_response( array( 'message' => __( 'Orden actualizado.', 'aura-suite' ) ) );
    }
}
