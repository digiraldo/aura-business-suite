<?php
/**
 * Reportes y Exportación — Fase 6
 *
 * Provee los 5 tipos de reporte del módulo de vehículos con filtros,
 * exportación a CSV y exportación a PDF (vía mPDF).
 *
 * Métodos públicos principales:
 *   get_trips_report(array $filters)        → array{ headers, rows, totals }
 *   get_maintenances_report(array $filters) → array{ headers, rows, totals }
 *   get_costs_report(array $filters)        → array{ headers, rows, totals }
 *   get_vehicles_report(array $filters)     → array{ headers, rows, totals }
 *   get_mileage_report(array $filters)      → array{ headers, rows, totals }
 *   export_csv(array $data, string $filename)   — envía CSV al navegador y termina
 *   export_pdf(array $data, string $title)      — envía PDF al navegador y termina
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Reports {

    /**
     * Convierte nivel de combustible numérico (0-100) a escala textual de 5 pasos.
     */
    private static function fuel_level_label( $value ): string {
        if ( null === $value || '' === $value ) {
            return '—';
        }

        $numeric = (int) $value;
        $numeric = max( 0, min( 100, $numeric ) );

        if ( $numeric <= 0 ) {
            return 'Vacío';
        }
        if ( $numeric <= 25 ) {
            return '1/4';
        }
        if ( $numeric <= 50 ) {
            return '1/2';
        }
        if ( $numeric <= 75 ) {
            return '3/4';
        }

        return 'Lleno';
    }

    // ─────────────────────────────────────────────────────────────────
    // UTILIDADES INTERNAS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Convierte un período legible en fechas FROM / TO.
     *
     * @param  string $period  '7d'|'30d'|'90d'|'year'|'custom'
     * @param  string $from    Fecha inicio manual (si period='custom')
     * @param  string $to      Fecha fin manual (si period='custom')
     * @return array{ from: string, to: string }
     */
    private static function period_to_dates( string $period, string $from = '', string $to = '' ): array {
        $now = current_time( 'timestamp' );

        if ( 'custom' === $period ) {
            return array(
                'from' => $from ? $from . ' 00:00:00' : '2000-01-01 00:00:00',
                'to'   => $to   ? $to   . ' 23:59:59' : date( 'Y-m-d 23:59:59', $now ), // phpcs:ignore
            );
        }

        switch ( $period ) {
            case '7d':
                return array(
                    'from' => date( 'Y-m-d 00:00:00', strtotime( '-6 days', $now ) ), // phpcs:ignore
                    'to'   => date( 'Y-m-d 23:59:59', $now ),                          // phpcs:ignore
                );
            case '90d':
                return array(
                    'from' => date( 'Y-m-d 00:00:00', strtotime( '-89 days', $now ) ), // phpcs:ignore
                    'to'   => date( 'Y-m-d 23:59:59', $now ),                           // phpcs:ignore
                );
            case 'year':
                return array(
                    'from' => date( 'Y', $now ) . '-01-01 00:00:00', // phpcs:ignore
                    'to'   => date( 'Y-m-d 23:59:59', $now ),        // phpcs:ignore
                );
            default: // 30d
                return array(
                    'from' => date( 'Y-m-d 00:00:00', strtotime( '-29 days', $now ) ), // phpcs:ignore
                    'to'   => date( 'Y-m-d 23:59:59', $now ),                           // phpcs:ignore
                );
        }
    }

    /**
     * Cláusula SQL de filtro de área para trips (con prefijo AND).
     *
     * @param  int $area_id  0 = todas las áreas (o las del usuario si no tiene view_all).
     * @return string
     */
    private static function trip_area_where( int $area_id ): string {
        global $wpdb;

        if ( $area_id > 0 ) {
            return $wpdb->prepare( 'AND t.area_id = %d', $area_id );
        }

        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            $uid = (int) get_current_user_id();
            return "AND (t.area_id IN (
                SELECT area_id FROM {$wpdb->prefix}aura_area_users WHERE user_id = {$uid}
            ) OR t.created_by = {$uid})";
        }

        return '';
    }

    /**
     * Cláusula SQL de filtro de área para vehículos (con prefijo AND).
     */
    private static function vehicle_area_where( int $area_id ): string {
        global $wpdb;

        if ( $area_id > 0 ) {
            return $wpdb->prepare(
                "AND v.id IN (SELECT vehicle_id FROM {$wpdb->prefix}aura_vehicle_area WHERE area_id = %d)",
                $area_id
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

    // ─────────────────────────────────────────────────────────────────
    // REPORTE 1: SALIDAS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Reporte de salidas (trips) con filtros.
     *
     * @param  array{period?:string, date_from?:string, date_to?:string, area_id?:int, vehicle_id?:int, trip_type?:string} $filters
     * @return array{ headers: string[], rows: object[], totals: array }
     */
    public static function get_trips_report( array $filters = array() ): array {
        global $wpdb;

        $period     = $filters['period']     ?? '30d';
        $date_from  = $filters['date_from']  ?? '';
        $date_to    = $filters['date_to']    ?? '';
        $area_id    = (int) ( $filters['area_id']    ?? 0 );
        $vehicle_id = (int) ( $filters['vehicle_id'] ?? 0 );
        $trip_type  = $filters['trip_type']  ?? '';

        $dates      = self::period_to_dates( $period, $date_from, $date_to );
        $area_sql   = self::trip_area_where( $area_id );

        $vehicle_sql = '';
        if ( $vehicle_id > 0 ) {
            $vehicle_sql = $wpdb->prepare( 'AND t.vehicle_id = %d', $vehicle_id );
        }

        $type_sql = '';
        $valid_types = array( 'rental', 'errand', 'maintenance', 'other' );
        if ( $trip_type && in_array( $trip_type, $valid_types, true ) ) {
            $type_sql = $wpdb->prepare( 'AND t.trip_type = %s', $trip_type );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    t.id,
                    CONCAT(v.brand, ' ', v.model) AS vehiculo,
                    v.plate AS placa,
                    COALESCE(a.name, '—') AS area,
                    t.trip_type AS tipo,
                    t.status AS estado,
                    DATE(t.departure_datetime) AS fecha_salida,
                    DATE(t.return_datetime) AS fecha_retorno,
                    t.departure_fuel AS combustible_salida,
                    t.return_fuel AS combustible_retorno,
                    t.km_traveled AS km,
                    t.total_amount AS monto,
                    t.total_expenses AS gastos,
                    CASE t.trip_type
                        WHEN 'rental'      THEN COALESCE(t.client_name, '—')
                        WHEN 'errand'      THEN COALESCE(t.responsible_name, '—')
                        WHEN 'maintenance' THEN COALESCE(t.maint_provider, '—')
                        ELSE COALESCE(t.responsible_name, '—')
                    END AS persona
                FROM {$wpdb->prefix}aura_vehicle_trips t
                INNER JOIN {$wpdb->prefix}aura_vehicles v ON v.id = t.vehicle_id
                LEFT JOIN  {$wpdb->prefix}aura_areas a    ON a.id = t.area_id
                WHERE t.deleted = 0
                  AND t.departure_datetime BETWEEN %s AND %s
                  {$area_sql} {$vehicle_sql} {$type_sql}
                ORDER BY t.departure_datetime DESC",
                $dates['from'],
                $dates['to']
            )
        );

        $rows = $rows ?: array();

        $km_total    = array_sum( array_column( $rows, 'km' ) );
        $monto_total = array_sum( array_column( $rows, 'monto' ) );
        $gastos_total = array_sum( array_column( $rows, 'gastos' ) );

        $type_labels = array(
            'rental'      => 'Alquiler',
            'errand'      => 'Encargo',
            'maintenance' => 'Mantenimiento',
            'other'       => 'Otro',
        );
        $status_labels = array(
            'active'    => 'Activa',
            'returned'  => 'Retornada',
            'cancelled' => 'Cancelada',
        );

        foreach ( $rows as $row ) {
            $row->tipo   = $type_labels[ $row->tipo ]   ?? $row->tipo;
            $row->estado = $status_labels[ $row->estado ] ?? $row->estado;
            $row->combustible_salida  = self::fuel_level_label( $row->combustible_salida );
            $row->combustible_retorno = self::fuel_level_label( $row->combustible_retorno );
        }

        return array(
            'headers' => array( 'ID', 'Vehículo', 'Placa', 'Área', 'Tipo', 'Estado', 'Fecha Salida', 'Fecha Retorno', 'Combustible Salida', 'Combustible Retorno', 'KM', 'Monto ($)', 'Gastos ($)', 'Persona' ),
            'rows'    => $rows,
            'totals'  => array(
                'registros' => count( $rows ),
                'km'        => $km_total,
                'monto'     => $monto_total,
                'gastos'    => $gastos_total,
            ),
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // REPORTE 2: MANTENIMIENTOS
    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  array{period?:string, date_from?:string, date_to?:string, area_id?:int, vehicle_id?:int} $filters
     * @return array{ headers: string[], rows: object[], totals: array }
     */
    public static function get_maintenances_report( array $filters = array() ): array {
        global $wpdb;

        $period     = $filters['period']     ?? '30d';
        $date_from  = $filters['date_from']  ?? '';
        $date_to    = $filters['date_to']    ?? '';
        $area_id    = (int) ( $filters['area_id']    ?? 0 );
        $vehicle_id = (int) ( $filters['vehicle_id'] ?? 0 );

        $dates    = self::period_to_dates( $period, $date_from, $date_to );
        $area_sql = self::trip_area_where( $area_id );

        $vehicle_sql = '';
        if ( $vehicle_id > 0 ) {
            $vehicle_sql = $wpdb->prepare( 'AND t.vehicle_id = %d', $vehicle_id );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    t.id,
                    CONCAT(v.brand, ' ', v.model) AS vehiculo,
                    v.plate AS placa,
                    t.maint_subtype AS subtipo,
                    t.maint_priority AS prioridad,
                    COALESCE(t.maint_provider, '—') AS proveedor,
                    DATE(t.departure_datetime) AS fecha_inicio,
                    DATE(t.return_datetime) AS fecha_fin,
                    t.maint_estimated_cost AS costo_estimado,
                    t.maint_actual_cost AS costo_real,
                    t.status AS estado,
                    COALESCE(t.maint_description, '—') AS descripcion
                FROM {$wpdb->prefix}aura_vehicle_trips t
                INNER JOIN {$wpdb->prefix}aura_vehicles v ON v.id = t.vehicle_id
                WHERE t.deleted = 0
                  AND t.trip_type = 'maintenance'
                  AND t.departure_datetime BETWEEN %s AND %s
                  {$area_sql} {$vehicle_sql}
                ORDER BY t.departure_datetime DESC",
                $dates['from'],
                $dates['to']
            )
        );

        $rows = $rows ?: array();

        $priority_labels = array( 'low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente' );
        $subtype_labels  = array( 'preventive' => 'Preventivo', 'corrective' => 'Correctivo', 'inspection' => 'Inspección' );
        $status_labels   = array( 'active' => 'En proceso', 'returned' => 'Finalizado', 'cancelled' => 'Cancelado' );

        foreach ( $rows as $row ) {
            $row->subtipo   = $subtype_labels[ $row->subtipo ]   ?? $row->subtipo ?? '—';
            $row->prioridad = $priority_labels[ $row->prioridad ] ?? $row->prioridad ?? '—';
            $row->estado    = $status_labels[ $row->estado ]     ?? $row->estado;
        }

        $costo_est_total  = array_sum( array_column( $rows, 'costo_estimado' ) );
        $costo_real_total = array_sum( array_column( $rows, 'costo_real' ) );

        return array(
            'headers' => array( 'ID', 'Vehículo', 'Placa', 'Subtipo', 'Prioridad', 'Proveedor', 'Fecha Inicio', 'Fecha Fin', 'Costo Est.', 'Costo Real', 'Estado', 'Descripción' ),
            'rows'    => $rows,
            'totals'  => array(
                'registros'     => count( $rows ),
                'costo_est'     => $costo_est_total,
                'costo_real'    => $costo_real_total,
                'diferencia'    => $costo_est_total - $costo_real_total,
            ),
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // REPORTE 3: COSTOS Y GASTOS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Resumen agrupado por vehículo: costos (mantenimiento + gastos viaje) vs ingresos rental.
     *
     * @param  array{period?:string, date_from?:string, date_to?:string, area_id?:int, vehicle_id?:int} $filters
     * @return array{ headers: string[], rows: object[], totals: array }
     */
    public static function get_costs_report( array $filters = array() ): array {
        global $wpdb;

        $period     = $filters['period']    ?? '30d';
        $date_from  = $filters['date_from'] ?? '';
        $date_to    = $filters['date_to']   ?? '';
        $area_id    = (int) ( $filters['area_id']    ?? 0 );
        $vehicle_id = (int) ( $filters['vehicle_id'] ?? 0 );

        $dates    = self::period_to_dates( $period, $date_from, $date_to );
        $area_sql = self::trip_area_where( $area_id );

        $vehicle_sql = '';
        if ( $vehicle_id > 0 ) {
            $vehicle_sql = $wpdb->prepare( 'AND t.vehicle_id = %d', $vehicle_id );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    v.id,
                    CONCAT(v.brand, ' ', v.model) AS vehiculo,
                    v.plate AS placa,
                    COUNT(t.id) AS total_salidas,
                    COALESCE(SUM(CASE WHEN t.trip_type = 'rental' THEN t.total_amount ELSE 0 END), 0) AS ingresos,
                    COALESCE(SUM(t.maint_actual_cost), 0) AS costo_mant,
                    COALESCE(SUM(t.total_expenses), 0) AS gastos_viaje,
                    COALESCE(SUM(t.maint_actual_cost + t.total_expenses), 0) AS total_costos,
                    COALESCE(SUM(CASE WHEN t.trip_type = 'rental' THEN t.total_amount ELSE 0 END), 0)
                        - COALESCE(SUM(t.maint_actual_cost + t.total_expenses), 0) AS balance
                FROM {$wpdb->prefix}aura_vehicle_trips t
                INNER JOIN {$wpdb->prefix}aura_vehicles v ON v.id = t.vehicle_id
                WHERE t.deleted = 0
                  AND t.status = 'returned'
                  AND t.departure_datetime BETWEEN %s AND %s
                  {$area_sql} {$vehicle_sql}
                GROUP BY v.id, v.brand, v.model, v.plate
                ORDER BY total_costos DESC",
                $dates['from'],
                $dates['to']
            )
        );

        $rows = $rows ?: array();

        return array(
            'headers' => array( 'ID Veh.', 'Vehículo', 'Placa', 'Salidas', 'Ingresos ($)', 'Costo Mant. ($)', 'Gastos Viaje ($)', 'Total Costos ($)', 'Balance ($)' ),
            'rows'    => $rows,
            'totals'  => array(
                'vehiculos'    => count( $rows ),
                'ingresos'     => array_sum( array_column( $rows, 'ingresos' ) ),
                'total_costos' => array_sum( array_column( $rows, 'total_costos' ) ),
                'balance'      => array_sum( array_column( $rows, 'balance' ) ),
            ),
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // REPORTE 4: VEHÍCULOS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Inventario de la flota con estado actual.
     *
     * @param  array{area_id?:int, status?:string, type?:string} $filters
     * @return array{ headers: string[], rows: object[], totals: array }
     */
    public static function get_vehicles_report( array $filters = array() ): array {
        global $wpdb;

        $area_id    = (int) ( $filters['area_id'] ?? 0 );
        $status     = $filters['status'] ?? '';
        $veh_type   = $filters['type']   ?? '';
        $area_sql   = self::vehicle_area_where( $area_id );

        $status_sql  = '';
        $valid_statuses = array( 'available', 'rented', 'maintenance', 'unavailable' );
        if ( $status && in_array( $status, $valid_statuses, true ) ) {
            $status_sql = $wpdb->prepare( 'AND v.status = %s', $status );
        }

        $type_sql   = '';
        $valid_types = array( 'sedan', 'suv', 'pickup', 'van', 'bus', 'motorcycle', 'truck', 'other' );
        if ( $veh_type && in_array( $veh_type, $valid_types, true ) ) {
            $type_sql = $wpdb->prepare( 'AND v.type = %s', $veh_type );
        }

        $rows = $wpdb->get_results(
            "SELECT
                v.id,
                v.plate AS placa,
                CONCAT(v.brand, ' ', v.model) AS vehiculo,
                v.year AS año,
                v.type AS tipo,
                v.status AS estado,
                v.mileage AS kilometraje,
                v.fuel_type AS combustible,
                v.transmission AS transmision,
                COALESCE(GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ', '), '—') AS areas,
                v.created_at AS registrado
             FROM {$wpdb->prefix}aura_vehicles v
             LEFT JOIN {$wpdb->prefix}aura_vehicle_area va ON va.vehicle_id = v.id
             LEFT JOIN {$wpdb->prefix}aura_areas a         ON a.id = va.area_id
             WHERE v.active = 1 {$area_sql} {$status_sql} {$type_sql}
             GROUP BY v.id
             ORDER BY v.brand, v.model"
        );

        $rows = $rows ?: array();

        $status_labels   = array( 'available' => 'Disponible', 'rented' => 'En Uso', 'maintenance' => 'Mantenimiento', 'unavailable' => 'No Disponible' );
        $type_labels     = array( 'sedan' => 'Sedán', 'suv' => 'SUV', 'pickup' => 'Pickup', 'van' => 'Minivan', 'bus' => 'Bus', 'motorcycle' => 'Moto', 'truck' => 'Camión', 'other' => 'Otro' );
        $fuel_labels     = array( 'gasoline' => 'Gasolina', 'diesel' => 'Diésel', 'electric' => 'Eléctrico', 'hybrid' => 'Híbrido', 'gas' => 'Gas' );
        $trans_labels    = array( 'manual' => 'Manual', 'automatic' => 'Automática' );

        foreach ( $rows as $row ) {
            $row->estado      = $status_labels[ $row->estado ]      ?? $row->estado;
            $row->tipo        = $type_labels[ $row->tipo ]          ?? $row->tipo;
            $row->combustible = $fuel_labels[ $row->combustible ]   ?? $row->combustible;
            $row->transmision = $trans_labels[ $row->transmision ]  ?? $row->transmision;
        }

        $km_promedio = count( $rows ) > 0
            ? round( array_sum( array_column( $rows, 'kilometraje' ) ) / count( $rows ) )
            : 0;

        return array(
            'headers' => array( 'ID', 'Placa', 'Vehículo', 'Año', 'Tipo', 'Estado', 'KM Acum.', 'Combustible', 'Transmisión', 'Áreas', 'Registrado' ),
            'rows'    => $rows,
            'totals'  => array(
                'total'       => count( $rows ),
                'km_promedio' => $km_promedio,
            ),
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // REPORTE 5: KILOMETRAJE
    // ─────────────────────────────────────────────────────────────────

    /**
     * KM recorridos por vehículo en el período, con desglose por tipo de salida.
     *
     * @param  array{period?:string, date_from?:string, date_to?:string, area_id?:int, vehicle_id?:int} $filters
     * @return array{ headers: string[], rows: object[], totals: array }
     */
    public static function get_mileage_report( array $filters = array() ): array {
        global $wpdb;

        $period     = $filters['period']    ?? '30d';
        $date_from  = $filters['date_from'] ?? '';
        $date_to    = $filters['date_to']   ?? '';
        $area_id    = (int) ( $filters['area_id']    ?? 0 );
        $vehicle_id = (int) ( $filters['vehicle_id'] ?? 0 );

        $dates    = self::period_to_dates( $period, $date_from, $date_to );
        $area_sql = self::trip_area_where( $area_id );

        $vehicle_sql = '';
        if ( $vehicle_id > 0 ) {
            $vehicle_sql = $wpdb->prepare( 'AND t.vehicle_id = %d', $vehicle_id );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    v.id,
                    CONCAT(v.brand, ' ', v.model) AS vehiculo,
                    v.plate AS placa,
                    v.mileage AS km_acumulado,
                    COUNT(t.id) AS total_salidas,
                    COALESCE(SUM(t.km_traveled), 0) AS km_periodo,
                    COALESCE(SUM(CASE WHEN t.trip_type = 'rental'      THEN t.km_traveled ELSE 0 END), 0) AS km_rental,
                    COALESCE(SUM(CASE WHEN t.trip_type = 'errand'      THEN t.km_traveled ELSE 0 END), 0) AS km_encargo,
                    COALESCE(SUM(CASE WHEN t.trip_type = 'maintenance' THEN t.km_traveled ELSE 0 END), 0) AS km_mant,
                    COALESCE(SUM(CASE WHEN t.trip_type = 'other'       THEN t.km_traveled ELSE 0 END), 0) AS km_otro
                FROM {$wpdb->prefix}aura_vehicle_trips t
                INNER JOIN {$wpdb->prefix}aura_vehicles v ON v.id = t.vehicle_id
                WHERE t.deleted = 0
                  AND t.status = 'returned'
                  AND t.departure_datetime BETWEEN %s AND %s
                  {$area_sql} {$vehicle_sql}
                GROUP BY v.id, v.brand, v.model, v.plate, v.mileage
                ORDER BY km_periodo DESC",
                $dates['from'],
                $dates['to']
            )
        );

        $rows = $rows ?: array();

        return array(
            'headers' => array( 'ID Veh.', 'Vehículo', 'Placa', 'KM Acum.', 'Salidas', 'KM Período', 'KM Alquiler', 'KM Encargo', 'KM Mant.', 'KM Otro' ),
            'rows'    => $rows,
            'totals'  => array(
                'vehiculos'  => count( $rows ),
                'km_periodo' => array_sum( array_column( $rows, 'km_periodo' ) ),
            ),
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORTACIÓN CSV
    // ─────────────────────────────────────────────────────────────────

    /**
     * Enviar una exportación CSV al navegador y terminar la ejecución.
     *
     * @param array  $data      Array con keys 'headers' y 'rows'.
     * @param string $filename  Nombre del archivo sin extensión.
     */
    public static function export_csv( array $data, string $filename ): void {
        $headers = $data['headers'] ?? array();
        $rows    = $data['rows']    ?? array();

        $clean_filename = sanitize_file_name( $filename ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $clean_filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Marca BOM para Excel con UTF-8
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output = fopen( 'php://output', 'w' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs
        fputs( $output, "\xEF\xBB\xBF" );

        if ( ! empty( $headers ) ) {
            fputcsv( $output, $headers );
        }

        foreach ( $rows as $row ) {
            fputcsv( $output, array_values( (array) $row ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $output );
        exit;
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORTACIÓN PDF
    // ─────────────────────────────────────────────────────────────────

    /**
     * Enviar una exportación PDF al navegador y terminar la ejecución.
     * Requiere mPDF disponible en el vendor.
     *
     * @param array  $data    Array con keys 'headers', 'rows', 'totals'.
     * @param string $title   Título visible en el documento.
     * @param string $subtitle  Subtítulo opcional (filtros aplicados).
     */
    public static function export_pdf( array $data, string $title, string $subtitle = '' ): void {
        if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
            wp_die( esc_html__( 'La librería mPDF no está disponible.', 'aura-suite' ), 500 );
        }

        $headers = $data['headers'] ?? array();
        $rows    = $data['rows']    ?? array();
        $totals  = $data['totals']  ?? array();
        $org     = get_option( 'blogname', 'AURA Business Suite' );
        $date    = current_time( 'd/m/Y H:i' );

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body      { font-family: Arial, sans-serif; font-size: 9px; color: #1d2327; margin: 0; }
    .header   { background: #1e3a5f; color: #fff; padding: 10px 12px 8px; margin-bottom: 10px; }
    .header h1 { margin: 0 0 2px; font-size: 14px; font-weight: 700; }
    .header p  { margin: 0; font-size: 9px; opacity: .8; }
    .subtitle  { font-size: 9px; color: #50575e; margin: 0 0 8px 12px; }
    table     { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
    th        { background: #2271b1; color: #fff; padding: 5px 6px; font-size: 9px; font-weight: 600; text-align: left; }
    td        { padding: 4px 6px; border-bottom: 1px solid #e8eaf0; font-size: 8.5px; }
    tr:nth-child(even) td { background: #f6f8fb; }
    .totals   { margin: 0 0 0 auto; background: #f6f7f7; border: 1px solid #dde0e4; border-radius: 3px; padding: 6px 10px; font-size: 9px; width: auto; display: inline-block; }
    .totals strong { font-size: 10px; }
    .footer   { font-size: 8px; color: #72777c; text-align: right; margin-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1><?php echo esc_html( $org ); ?> — <?php echo esc_html( $title ); ?></h1>
    <p><?php esc_html_e( 'Generado:', 'aura-suite' ); ?> <?php echo esc_html( $date ); ?></p>
</div>
<?php if ( $subtitle ) : ?>
<p class="subtitle"><?php echo esc_html( $subtitle ); ?></p>
<?php endif; ?>
<table>
    <thead>
        <tr>
            <?php foreach ( $headers as $h ) : ?>
            <th><?php echo esc_html( $h ); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $rows as $row ) : ?>
        <tr>
            <?php foreach ( array_values( (array) $row ) as $v ) : ?>
            <td><?php echo esc_html( (string) $v ); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php if ( empty( $rows ) ) : ?>
        <tr><td colspan="<?php echo esc_attr( count( $headers ) ); ?>" style="text-align:center;padding:12px;color:#72777c;">
            <?php esc_html_e( 'Sin registros para los filtros aplicados.', 'aura-suite' ); ?>
        </td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php if ( ! empty( $totals ) ) : ?>
<div class="totals">
    <?php foreach ( $totals as $key => $val ) : ?>
    <div><strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?>:</strong> <?php echo esc_html( is_float( $val ) ? number_format( $val, 2 ) : number_format( (float) $val ) ); ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="footer"><?php echo esc_html( $org ); ?> · AURA Business Suite</div>
</body>
</html>
        <?php
        $html = ob_get_clean();

        $filename = sanitize_file_name( $title ) . '-' . date( 'Y-m-d' ) . '.pdf'; // phpcs:ignore

        try {
            $mpdf = new \Mpdf\Mpdf( array(
                'format'       => 'A4',
                'orientation'  => 'L',
                'margin_top'   => 5,
                'margin_left'  => 8,
                'margin_right' => 8,
                'margin_bottom'=> 8,
            ) );
            $mpdf->SetTitle( $title );
            $mpdf->WriteHTML( $html );
            $mpdf->Output( $filename, 'D' );
        } catch ( \Exception $e ) {
            wp_die( esc_html( $e->getMessage() ), 500 );
        }

        exit;
    }
}

