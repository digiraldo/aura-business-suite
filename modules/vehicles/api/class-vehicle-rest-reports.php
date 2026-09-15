<?php
/**
 * REST API — Reportes de Vehículos (Fase 6)
 *
 * Endpoints:
 *   GET  /aura/v1/vehicles/reports              — datos de previsualización (JSON)
 *   POST /aura/v1/vehicles/reports/export-csv   — descarga CSV
 *   POST /aura/v1/vehicles/reports/export-pdf   — descarga PDF
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Reports {

    /** Tipos de reporte admitidos */
    private static function valid_types(): array {
        return array( 'trips', 'maintenances', 'costs', 'vehicles', 'mileage' );
    }

    /** Títulos para exportaciones */
    private static function type_label( string $type ): string {
        $labels = array(
            'trips'         => 'Reporte de Salidas',
            'maintenances'  => 'Reporte de Mantenimientos',
            'costs'         => 'Reporte de Costos',
            'vehicles'      => 'Reporte de Flota',
            'mileage'       => 'Reporte de Kilometraje',
        );
        return $labels[ $type ] ?? 'Reporte';
    }

    // ------------------------------------------------------------------
    // Registro de rutas
    // ------------------------------------------------------------------

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        $ns = 'aura/v1';

        // GET — previsualización
        register_rest_route( $ns, '/vehicles/reports', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_preview' ),
            'permission_callback' => array( __CLASS__, 'can_view' ),
            'args'                => self::filter_args(),
        ) );

        // POST — exportar CSV
        register_rest_route( $ns, '/vehicles/reports/export-csv', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'export_csv' ),
            'permission_callback' => array( __CLASS__, 'can_view' ),
            'args'                => self::filter_args(),
        ) );

        // POST — exportar PDF
        register_rest_route( $ns, '/vehicles/reports/export-pdf', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'export_pdf' ),
            'permission_callback' => array( __CLASS__, 'can_view' ),
            'args'                => self::filter_args(),
        ) );
    }

    // ------------------------------------------------------------------
    // Permisos
    // ------------------------------------------------------------------

    public static function can_view(): bool {
        return current_user_can( 'aura_vehicles_reports' )
            || current_user_can( 'manage_options' );
    }

    // ------------------------------------------------------------------
    // Definición de argumentos comunes
    // ------------------------------------------------------------------

    private static function filter_args(): array {
        return array(
            'type'       => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ( $v ) {
                    return in_array( $v, self::valid_types(), true );
                },
            ),
            'period'     => array(
                'default'           => '30d',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ( $v ) {
                    return in_array( $v, array( '7d', '30d', '90d', 'year', 'custom' ), true );
                },
            ),
            'date_from'  => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'date_to'    => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'area_id'    => array(
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
            'vehicle_id' => array(
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
            'trip_type'  => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'status'     => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    // ------------------------------------------------------------------
    // Construir array de filtros desde la request
    // ------------------------------------------------------------------

    private static function build_filters( WP_REST_Request $request ): array {
        return array(
            'period'     => $request->get_param( 'period' ),
            'date_from'  => $request->get_param( 'date_from' ),
            'date_to'    => $request->get_param( 'date_to' ),
            'area_id'    => (int) $request->get_param( 'area_id' ),
            'vehicle_id' => (int) $request->get_param( 'vehicle_id' ),
            'trip_type'  => $request->get_param( 'trip_type' ),
            'status'     => $request->get_param( 'status' ),
            'type'       => $request->get_param( 'type' ),
        );
    }

    // ------------------------------------------------------------------
    // Obtener datos del reporte según tipo
    // ------------------------------------------------------------------

    private static function get_report_data( string $type, array $filters ): array {
        switch ( $type ) {
            case 'maintenances':
                return Aura_Vehicle_Reports::get_maintenances_report( $filters );
            case 'costs':
                return Aura_Vehicle_Reports::get_costs_report( $filters );
            case 'vehicles':
                return Aura_Vehicle_Reports::get_vehicles_report( $filters );
            case 'mileage':
                return Aura_Vehicle_Reports::get_mileage_report( $filters );
            default: // trips
                return Aura_Vehicle_Reports::get_trips_report( $filters );
        }
    }

    // ------------------------------------------------------------------
    // Handlers
    // ------------------------------------------------------------------

    /**
     * GET /aura/v1/vehicles/reports
     * Devuelve JSON de previsualización (cabeceras + filas + totales).
     */
    public static function get_preview( WP_REST_Request $request ): WP_REST_Response {
        $type    = $request->get_param( 'type' );
        $filters = self::build_filters( $request );
        $data    = self::get_report_data( $type, $filters );

        // Convertir objetos a arrays para el JSON
        $rows_array = array();
        foreach ( ( $data['rows'] ?? array() ) as $row ) {
            $rows_array[] = array_values( (array) $row );
        }

        return rest_ensure_response( array(
            'headers' => $data['headers'],
            'rows'    => $rows_array,
            'totals'  => $data['totals'],
        ) );
    }

    /**
     * POST /aura/v1/vehicles/reports/export-csv
     * Descarga directa del CSV.
     */
    public static function export_csv( WP_REST_Request $request ): void {
        $type    = $request->get_param( 'type' );
        $filters = self::build_filters( $request );
        $data    = self::get_report_data( $type, $filters );
        $label   = self::type_label( $type );

        Aura_Vehicle_Audit_Manager::log(
            'report_export',
            'report',
            0,
            array( 'type' => $type, 'format' => 'csv', 'rows' => count( $data['rows'] ?? array() ) )
        );

        $filename = sanitize_file_name( $label ) . '-' . date( 'Y-m-d' ); // phpcs:ignore
        Aura_Vehicle_Reports::export_csv( $data, $filename );
    }

    /**
     * POST /aura/v1/vehicles/reports/export-pdf
     * Descarga directa del PDF.
     */
    public static function export_pdf( WP_REST_Request $request ): void {
        $type     = $request->get_param( 'type' );
        $filters  = self::build_filters( $request );
        $data     = self::get_report_data( $type, $filters );
        $label    = self::type_label( $type );

        // Construir subtítulo con filtros aplicados
        $parts = array();
        if ( $request->get_param( 'area_id' ) ) {
            $area = get_term( (int) $request->get_param( 'area_id' ) );
            $parts[] = 'Área: ' . ( is_object( $area ) ? $area->name : '—' );
        }
        $period = $request->get_param( 'period' );
        if ( 'custom' === $period ) {
            $parts[] = $request->get_param( 'date_from' ) . ' al ' . $request->get_param( 'date_to' );
        } else {
            $period_labels = array( '7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días', '90d' => 'Últimos 90 días', 'year' => 'Este año' );
            $parts[] = $period_labels[ $period ] ?? $period;
        }
        $subtitle = implode( ' | ', $parts );

        Aura_Vehicle_Audit_Manager::log(
            'report_export',
            'report',
            0,
            array( 'type' => $type, 'format' => 'pdf', 'rows' => count( $data['rows'] ?? array() ) )
        );

        Aura_Vehicle_Reports::export_pdf( $data, $label, $subtitle );
    }
}
