<?php
/**
 * REST API — Auditoría de Vehículos (Fase 7)
 *
 * Endpoints:
 *   GET    /aura/v1/vehicles/audit            — listado paginado con filtros
 *   GET    /aura/v1/vehicles/audit/export-csv — descarga CSV de registros filtrados
 *   DELETE /aura/v1/vehicles/audit/cleanup    — elimina logs anteriores a N días
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Audit {

    // ------------------------------------------------------------------
    // Registro de rutas
    // ------------------------------------------------------------------

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        $ns = 'aura/v1';

        // GET — listado con filtros y paginación
        register_rest_route( $ns, '/vehicles/audit', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_logs' ),
            'permission_callback' => array( __CLASS__, 'can_manage' ),
            'args'                => self::filter_args(),
        ) );

        // GET — exportar CSV
        register_rest_route( $ns, '/vehicles/audit/export-csv', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'export_csv' ),
            'permission_callback' => array( __CLASS__, 'can_manage' ),
            'args'                => self::filter_args(),
        ) );

        // DELETE — limpiar logs antiguos
        register_rest_route( $ns, '/vehicles/audit/cleanup', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( __CLASS__, 'cleanup' ),
            'permission_callback' => array( __CLASS__, 'can_manage' ),
            'args'                => array(
                'days' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ( $v ) {
                        return $v >= 7 && $v <= 3650;
                    },
                ),
            ),
        ) );
    }

    // ------------------------------------------------------------------
    // Permisos
    // ------------------------------------------------------------------

    public static function can_manage(): bool {
        return current_user_can( 'aura_vehicles_audit' )
            || current_user_can( 'manage_options' );
    }

    // ------------------------------------------------------------------
    // Argumentos comunes
    // ------------------------------------------------------------------

    private static function filter_args(): array {
        return array(
            'operation'  => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
            'user_id'    => array( 'default' => 0,  'sanitize_callback' => 'absint' ),
            'date_from'  => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
            'date_to'    => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
            'ip'         => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
            'search'     => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
            'page'       => array( 'default' => 1,  'sanitize_callback' => 'absint' ),
            'per_page'   => array(
                'default'           => 50,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ( $v ) { return $v >= 1 && $v <= 200; },
            ),
        );
    }

    // ------------------------------------------------------------------
    // Construir cláusulas WHERE comunes
    // ------------------------------------------------------------------

    private static function build_where( WP_REST_Request $request ): array {
        global $wpdb;

        $where  = array( '1=1' );
        $params = array();

        $operation = $request->get_param( 'operation' );
        if ( $operation ) {
            $where[]  = 'a.operation = %s';
            $params[] = $operation;
        }

        $user_id = (int) $request->get_param( 'user_id' );
        if ( $user_id ) {
            $where[]  = 'a.user_id = %d';
            $params[] = $user_id;
        }

        $date_from = $request->get_param( 'date_from' );
        if ( $date_from ) {
            $where[]  = 'a.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        $date_to = $request->get_param( 'date_to' );
        if ( $date_to ) {
            $where[]  = 'a.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $ip = $request->get_param( 'ip' );
        if ( $ip ) {
            $where[]  = 'a.ip_address LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $ip ) . '%';
        }

        $search = $request->get_param( 'search' );
        if ( $search ) {
            $where[]  = '(a.operation LIKE %s OR a.entity_type LIKE %s OR a.details LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return array(
            'sql'    => implode( ' AND ', $where ),
            'params' => $params,
        );
    }

    // ------------------------------------------------------------------
    // GET /aura/v1/vehicles/audit
    // ------------------------------------------------------------------

    public static function get_logs( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = max( 1, (int) $request->get_param( 'per_page' ) );
        $offset   = ( $page - 1 ) * $per_page;

        $cond   = self::build_where( $request );
        $where  = $cond['sql'];
        $params = $cond['params'];

        $base_sql = "FROM {$wpdb->prefix}aura_vehicle_audit a
                     LEFT JOIN {$wpdb->prefix}users u ON u.ID = a.user_id
                     WHERE {$where}";

        // Total de registros
        if ( ! empty( $params ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base_sql}", $params ) ); // phpcs:ignore
        } else {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) {$base_sql}" ); // phpcs:ignore
        }

        // Registros de la página
        $select_sql = "SELECT a.id, a.operation, a.entity_type, a.entity_id,
                              a.user_id, u.display_name AS user_name, u.user_email,
                              a.ip_address, a.user_agent, a.details, a.created_at
                       {$base_sql}
                       ORDER BY a.id DESC
                       LIMIT %d OFFSET %d";

        $query_params = array_merge( $params, array( $per_page, $offset ) );
        $rows         = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_params ) ); // phpcs:ignore
        $rows         = $rows ?: array();

        // Parsear JSON de details
        foreach ( $rows as $row ) {
            if ( $row->details ) {
                $decoded = json_decode( $row->details, true );
                $row->details_parsed = is_array( $decoded ) ? $decoded : array();
            } else {
                $row->details_parsed = array();
            }
        }

        return rest_ensure_response( array(
            'items'       => $rows,
            'total'       => $total,
            'pages'       => (int) ceil( $total / $per_page ),
            'current'     => $page,
            'per_page'    => $per_page,
        ) );
    }

    // ------------------------------------------------------------------
    // GET /aura/v1/vehicles/audit/export-csv
    // ------------------------------------------------------------------

    public static function export_csv( WP_REST_Request $request ): void {
        global $wpdb;

        $cond   = self::build_where( $request );
        $where  = $cond['sql'];
        $params = $cond['params'];

        $select_sql = "SELECT a.id, a.operation, a.entity_type, a.entity_id,
                              a.user_id, u.display_name AS user_name,
                              a.ip_address, a.details, a.created_at
                       FROM {$wpdb->prefix}aura_vehicle_audit a
                       LEFT JOIN {$wpdb->prefix}users u ON u.ID = a.user_id
                       WHERE {$where}
                       ORDER BY a.id DESC
                       LIMIT 50000";

        if ( ! empty( $params ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare( $select_sql, $params ) ); // phpcs:ignore
        } else {
            $rows = $wpdb->get_results( $select_sql ); // phpcs:ignore
        }
        $rows = $rows ?: array();

        $filename = 'auditoria-vehiculos-' . date( 'Y-m-d' ) . '.csv'; // phpcs:ignore

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output = fopen( 'php://output', 'w' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs
        fputs( $output, "\xEF\xBB\xBF" );

        fputcsv( $output, array( 'ID', 'Fecha/Hora', 'Operación', 'Tipo Entidad', 'ID Entidad', 'Usuario', 'IP', 'Detalles' ) );

        foreach ( $rows as $row ) {
            fputcsv( $output, array(
                $row->id,
                $row->created_at,
                $row->operation,
                $row->entity_type,
                $row->entity_id,
                $row->user_name ?? '(sistema)',
                $row->ip_address,
                $row->details,
            ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $output );

        Aura_Vehicle_Audit_Manager::log(
            'audit_export_csv',
            'audit',
            0,
            array( 'rows' => count( $rows ) )
        );

        exit;
    }

    // ------------------------------------------------------------------
    // DELETE /aura/v1/vehicles/audit/cleanup
    // ------------------------------------------------------------------

    public static function cleanup( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $days    = (int) $request->get_param( 'days' );
        $cutoff  = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ); // phpcs:ignore

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}aura_vehicle_audit WHERE created_at < %s",
                $cutoff
            )
        );

        Aura_Vehicle_Audit_Manager::log(
            'audit_cleanup',
            'audit',
            0,
            array( 'days' => $days, 'deleted_rows' => (int) $deleted, 'cutoff' => $cutoff )
        );

        return rest_ensure_response( array(
            'deleted' => (int) $deleted,
            'message' => sprintf( 'Se eliminaron %d registros anteriores a %s.', (int) $deleted, $cutoff ),
        ) );
    }
}
