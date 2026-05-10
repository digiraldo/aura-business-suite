<?php
/**
 * REST Stats — Fase 5: Dashboard y KPIs
 *
 * GET /aura/v1/vehicles/stats
 *     KPIs generales: conteos por estado, activos, KM, ingresos, costos.
 *
 * GET /aura/v1/vehicles/stats/chart
 *     Datasets para Chart.js según ?type=& ?period=
 *
 * Filtrado por áreas del usuario si no tiene `aura_vehicles_view_all`.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles\API
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Stats {

    // ─────────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    // ─────────────────────────────────────────────────────────────────
    // RUTAS
    // ─────────────────────────────────────────────────────────────────

    public static function register_routes() {

        // GET /aura/v1/vehicles/stats
        register_rest_route(
            'aura/v1/vehicles',
            '/stats',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_stats' ),
                'permission_callback' => array( __CLASS__, 'can_view' ),
                'args'                => array(
                    'period'  => array(
                        'type'              => 'string',
                        'default'           => '30d',
                        'sanitize_callback' => 'sanitize_text_field',
                        'enum'              => array( '7d', '30d', '90d', 'year' ),
                    ),
                    'area_id' => array(
                        'type'              => 'integer',
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // GET /aura/v1/vehicles/stats/chart
        register_rest_route(
            'aura/v1/vehicles',
            '/stats/chart',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_chart' ),
                'permission_callback' => array( __CLASS__, 'can_view' ),
                'args'                => array(
                    'type'    => array(
                        'type'              => 'string',
                        'default'           => 'fleet-status',
                        'sanitize_callback' => 'sanitize_text_field',
                        'enum'              => array( 'fleet-status', 'km-by-vehicle', 'usage-by-area', 'monthly-activity', 'cost-vs-income' ),
                    ),
                    'period'  => array(
                        'type'              => 'string',
                        'default'           => '30d',
                        'sanitize_callback' => 'sanitize_text_field',
                        'enum'              => array( '7d', '30d', '90d', 'year' ),
                    ),
                    'area_id' => array(
                        'type'              => 'integer',
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // PERMISO
    // ─────────────────────────────────────────────────────────────────

    public static function can_view() {
        return current_user_can( 'aura_vehicles_view_all' )
            || current_user_can( 'aura_vehicles_exits_create' )
            || current_user_can( 'aura_vehicles_reports' )
            || current_user_can( 'manage_options' );
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Calcula el rango de fechas para un período dado.
     *
     * @param  string $period  '7d'|'30d'|'90d'|'year'
     * @return array{ from: string, to: string }
     */
    private static function period_range( $period ) {
        $now = current_time( 'timestamp' );
        $to  = date( 'Y-m-d 23:59:59', $now ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

        switch ( $period ) {
            case '7d':
                $from = date( 'Y-m-d 00:00:00', strtotime( '-6 days', $now ) ); // phpcs:ignore
                break;
            case '90d':
                $from = date( 'Y-m-d 00:00:00', strtotime( '-89 days', $now ) ); // phpcs:ignore
                break;
            case 'year':
                $from = date( 'Y', $now ) . '-01-01 00:00:00'; // phpcs:ignore
                break;
            default: // 30d
                $from = date( 'Y-m-d 00:00:00', strtotime( '-29 days', $now ) ); // phpcs:ignore
                break;
        }

        return array( 'from' => $from, 'to' => $to );
    }

    /**
     * Cláusula WHERE de área para vehículos.
     * Devuelve cadena SQL segura con prefijo AND (o vacía si no aplica).
     *
     * @param  int $forced_area  0 = auto-detect por usuario.
     * @return string
     */
    private static function vehicle_area_where( $forced_area = 0 ) {
        global $wpdb;

        $forced_area = (int) $forced_area;

        if ( $forced_area > 0 ) {
            return $wpdb->prepare(
                "AND v.id IN (SELECT vehicle_id FROM {$wpdb->prefix}aura_vehicle_area WHERE area_id = %d)",
                $forced_area
            );
        }

        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            $uid = (int) get_current_user_id();
            return "AND v.id IN (
                SELECT va.vehicle_id
                FROM {$wpdb->prefix}aura_vehicle_area va
                INNER JOIN {$wpdb->prefix}aura_area_users au ON au.area_id = va.area_id
                WHERE au.user_id = {$uid}
            )";
        }

        return '';
    }

    /**
     * Cláusula WHERE de área para trips.
     * Devuelve cadena SQL segura con prefijo AND (o vacía si no aplica).
     *
     * @param  int $forced_area  0 = auto-detect por usuario.
     * @return string
     */
    private static function trip_area_where( $forced_area = 0 ) {
        global $wpdb;

        $forced_area = (int) $forced_area;

        if ( $forced_area > 0 ) {
            return $wpdb->prepare( 'AND t.area_id = %d', $forced_area );
        }

        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            $uid = (int) get_current_user_id();
            return "(t.area_id IN (
                    SELECT area_id FROM {$wpdb->prefix}aura_area_users WHERE user_id = {$uid}
                ) OR t.created_by = {$uid})";
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────
    // ENDPOINT: KPIs (/stats)
    // ─────────────────────────────────────────────────────────────────

    public static function get_stats( WP_REST_Request $request ) {
        global $wpdb;

        $period  = $request->get_param( 'period' ) ?: '30d';
        $area_id = (int) $request->get_param( 'area_id' );
        $range   = self::period_range( $period );

        $veh_area  = self::vehicle_area_where( $area_id );
        $trip_area = self::trip_area_where( $area_id );
        $trip_area_sql = $trip_area ? "AND {$trip_area}" : '';

        // ── 1. Conteos de vehículos por estado ────────────────────
        $veh_rows = $wpdb->get_results(
            "SELECT v.status, COUNT(*) as cnt
             FROM {$wpdb->prefix}aura_vehicles v
             WHERE v.active = 1 {$veh_area}
             GROUP BY v.status"
        );

        $status_counts = array(
            'available'   => 0,
            'rented'      => 0,
            'maintenance' => 0,
            'unavailable' => 0,
        );
        foreach ( $veh_rows as $row ) {
            if ( array_key_exists( $row->status, $status_counts ) ) {
                $status_counts[ $row->status ] = (int) $row->cnt;
            }
        }
        $total_vehicles = array_sum( $status_counts );

        // ── 2. Salidas activas en este momento ─────────────────────
        $active_trips = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}aura_vehicle_trips t
             WHERE t.deleted = 0 AND t.status = 'active' {$trip_area_sql}"
        );

        // ── 3. Salidas activas creadas hoy ─────────────────────────
        $today_start  = current_time( 'Y-m-d' ) . ' 00:00:00';
        $today_end    = current_time( 'Y-m-d' ) . ' 23:59:59';
        $trips_today  = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->prefix}aura_vehicle_trips t
                 WHERE t.deleted = 0
                   AND t.departure_datetime BETWEEN %s AND %s
                   {$trip_area_sql}",
                $today_start,
                $today_end
            )
        );

        // ── 4. Métricas del período (solo salidas retornadas) ──────
        $base_sql = $wpdb->prepare(
            "FROM {$wpdb->prefix}aura_vehicle_trips t
             WHERE t.deleted = 0
               AND t.status = 'returned'
               AND t.departure_datetime BETWEEN %s AND %s
               {$trip_area_sql}",
            $range['from'],
            $range['to']
        );

        $km_total     = (int)   $wpdb->get_var( "SELECT COALESCE(SUM(t.km_traveled),0) {$base_sql}" );
        $income_total = (float) $wpdb->get_var( "SELECT COALESCE(SUM(t.total_amount),0) {$base_sql} AND t.trip_type = 'rental'" );
        $costs_total  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(t.maint_actual_cost + t.total_expenses),0) {$base_sql}" );
        $trips_period = (int)   $wpdb->get_var( "SELECT COUNT(*) {$base_sql}" );

        return rest_ensure_response(
            array(
                'status_counts'  => $status_counts,
                'total_vehicles' => $total_vehicles,
                'active_trips'   => $active_trips,
                'trips_today'    => $trips_today,
                'km_total'       => $km_total,
                'income_total'   => $income_total,
                'costs_total'    => $costs_total,
                'trips_period'   => $trips_period,
                'period'         => $period,
            )
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // ENDPOINT: DATASETS CHART.JS (/stats/chart)
    // ─────────────────────────────────────────────────────────────────

    public static function get_chart( WP_REST_Request $request ) {
        global $wpdb;

        $type    = $request->get_param( 'type' )    ?: 'fleet-status';
        $period  = $request->get_param( 'period' )  ?: '30d';
        $area_id = (int) $request->get_param( 'area_id' );
        $range   = self::period_range( $period );

        $veh_area  = self::vehicle_area_where( $area_id );
        $trip_area = self::trip_area_where( $area_id );
        $trip_area_sql = $trip_area ? "AND {$trip_area}" : '';

        $data = array();

        switch ( $type ) {

            // ── Doughnut: vehículos por estado ─────────────────────
            case 'fleet-status':
                $rows = $wpdb->get_results(
                    "SELECT v.status, COUNT(*) as cnt
                     FROM {$wpdb->prefix}aura_vehicles v
                     WHERE v.active = 1 {$veh_area}
                     GROUP BY v.status"
                );

                $labels_map = array(
                    'available'   => 'Disponibles',
                    'rented'      => 'En Uso',
                    'maintenance' => 'Mantenimiento',
                    'unavailable' => 'No Disponibles',
                );
                $colors_map = array(
                    'available'   => '#00ba88',
                    'rented'      => '#2271b1',
                    'maintenance' => '#f0b849',
                    'unavailable' => '#a7aaad',
                );

                $labels = array();
                $values = array();
                $colors = array();

                foreach ( $rows as $row ) {
                    $labels[] = $labels_map[ $row->status ] ?? $row->status;
                    $values[] = (int) $row->cnt;
                    $colors[] = $colors_map[ $row->status ] ?? '#c3c4c7';
                }

                $data = array( 'labels' => $labels, 'values' => $values, 'colors' => $colors );
                break;

            // ── Bar: top 10 vehículos por KM ───────────────────────
            case 'km-by-vehicle':
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT CONCAT(v.brand, ' ', v.model, ' (', v.plate, ')') AS label,
                                COALESCE(SUM(t.km_traveled), 0) AS km
                         FROM {$wpdb->prefix}aura_vehicle_trips t
                         INNER JOIN {$wpdb->prefix}aura_vehicles v ON v.id = t.vehicle_id
                         WHERE t.deleted = 0
                           AND t.status = 'returned'
                           AND t.departure_datetime BETWEEN %s AND %s
                           {$trip_area_sql}
                         GROUP BY t.vehicle_id, v.brand, v.model, v.plate
                         ORDER BY km DESC
                         LIMIT 10",
                        $range['from'],
                        $range['to']
                    )
                );

                $data = array(
                    'labels' => array_column( (array) $rows, 'label' ),
                    'values' => array_map( 'intval', array_column( (array) $rows, 'km' ) ),
                );
                break;

            // ── Bar horizontal: salidas por área ───────────────────
            case 'usage-by-area':
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT COALESCE(a.name, 'Sin área') AS label, COUNT(*) AS cnt
                         FROM {$wpdb->prefix}aura_vehicle_trips t
                         LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = t.area_id
                         WHERE t.deleted = 0
                           AND t.departure_datetime BETWEEN %s AND %s
                           {$trip_area_sql}
                         GROUP BY t.area_id, a.name
                         ORDER BY cnt DESC
                         LIMIT 12",
                        $range['from'],
                        $range['to']
                    )
                );

                $data = array(
                    'labels' => array_column( (array) $rows, 'label' ),
                    'values' => array_map( 'intval', array_column( (array) $rows, 'cnt' ) ),
                );
                break;

            // ── Line: salidas por día ──────────────────────────────
            case 'monthly-activity':
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT DATE(t.departure_datetime) AS day, COUNT(*) AS cnt
                         FROM {$wpdb->prefix}aura_vehicle_trips t
                         WHERE t.deleted = 0
                           AND t.departure_datetime BETWEEN %s AND %s
                           {$trip_area_sql}
                         GROUP BY DATE(t.departure_datetime)
                         ORDER BY day ASC",
                        $range['from'],
                        $range['to']
                    )
                );

                $data = array(
                    'labels' => array_column( (array) $rows, 'day' ),
                    'values' => array_map( 'intval', array_column( (array) $rows, 'cnt' ) ),
                );
                break;

            // ── Bar agrupado: costos vs ingresos por mes ───────────
            case 'cost-vs-income':
                // Siempre muestra los últimos 6 meses independientemente del período
                $months_from_ts = strtotime( '-5 months', current_time( 'timestamp' ) );
                $months_from    = date( 'Y-m-01 00:00:00', $months_from_ts ); // phpcs:ignore

                // DATE_FORMAT usa %m — para evitar conflicto con wpdb::prepare() se usa %%
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT DATE_FORMAT(t.departure_datetime, '%%Y-%%m') AS month,
                                COALESCE(SUM(CASE WHEN t.trip_type = 'rental' THEN t.total_amount ELSE 0 END), 0) AS income,
                                COALESCE(SUM(t.maint_actual_cost + t.total_expenses), 0) AS costs
                         FROM {$wpdb->prefix}aura_vehicle_trips t
                         WHERE t.deleted = 0
                           AND t.status = 'returned'
                           AND t.departure_datetime >= %s
                           {$trip_area_sql}
                         GROUP BY month
                         ORDER BY month ASC",
                        $months_from
                    )
                );

                $data = array(
                    'labels' => array_column( (array) $rows, 'month' ),
                    'income' => array_map( 'floatval', array_column( (array) $rows, 'income' ) ),
                    'costs'  => array_map( 'floatval', array_column( (array) $rows, 'costs' ) ),
                );
                break;

            default:
                return new WP_REST_Response( array( 'code' => 'invalid_type', 'message' => 'Tipo de gráfica no válido.' ), 400 );
        }

        return rest_ensure_response(
            array(
                'type'   => $type,
                'period' => $period,
                'data'   => $data,
            )
        );
    }
}
