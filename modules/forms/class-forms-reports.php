<?php
/**
 * Reports del Módulo de Formularios — Reportes exportables
 *
 * Genera reportes consolidados:
 *  - Actividad del módulo (respuestas por mes)
 *  - Inscripciones por curso (% aprobadas / rechazadas / retiradas)
 *  - Encuestas asignadas vs completadas (tasa de respuesta por área/curso)
 *
 * AJAX actions:
 *  - aura_forms_report_activity    — KPIs + submissions por mes
 *  - aura_forms_report_enrollments — Postulaciones por curso, estados
 *  - aura_forms_report_surveys     — Encuestas asignadas vs completadas
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Reports {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_forms_report_activity',    [ __CLASS__, 'ajax_report_activity' ] );
        add_action( 'wp_ajax_aura_forms_report_enrollments', [ __CLASS__, 'ajax_report_enrollments' ] );
        add_action( 'wp_ajax_aura_forms_report_surveys',     [ __CLASS__, 'ajax_report_surveys' ] );
    }

    /**
     * Callback de menú — renderiza la página de reportes.
     */
    public static function render(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/reports.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: REPORTE DE ACTIVIDAD
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve:
     * - KPIs: forms activos, total submissions, submissions este mes.
     * - Formularios por tipo (para breakdown).
     * - Submissions por mes (últimos 12 meses) para gráfico de línea.
     */
    public static function ajax_report_activity(): void {
        self::verify();
        global $wpdb;

        $t_forms = $wpdb->prefix . 'aura_forms';
        $t_sub   = $wpdb->prefix . 'aura_form_submissions';

        // ── KPIs ────────────────────────────────────────────────
        $active_forms = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_forms} WHERE is_active = 1 AND deleted_at IS NULL"
        );

        $total_sub = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_sub}"
        );

        $this_month = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_sub} WHERE submitted_at >= %s",
            gmdate( 'Y-m-01' )
        ) );

        // ── Formularios por tipo ─────────────────────────────────
        $by_type_rows = $wpdb->get_results(
            "SELECT type, COUNT(*) as total FROM {$t_forms} WHERE deleted_at IS NULL GROUP BY type"
        );
        $by_type = [];
        foreach ( $by_type_rows as $r ) {
            $by_type[ $r->type ] = (int) $r->total;
        }

        // ── Submissions por mes (últimos 12 meses) ───────────────
        $month_rows = $wpdb->get_results(
            "SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS mes, COUNT(*) AS total
               FROM {$t_sub}
              WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY mes
              ORDER BY mes ASC"
        );

        // Rellenar meses sin datos
        $labels     = [];
        $month_data = [];
        for ( $i = 11; $i >= 0; $i-- ) {
            $m              = gmdate( 'Y-m', strtotime( "-{$i} months" ) );
            $labels[]       = $m;
            $month_data[$m] = 0;
        }
        foreach ( $month_rows as $row ) {
            if ( isset( $month_data[ $row->mes ] ) ) {
                $month_data[ $row->mes ] = (int) $row->total;
            }
        }

        wp_send_json_success( [
            'kpis' => [
                'active_forms'   => $active_forms,
                'total_sub'      => $total_sub,
                'this_month'     => $this_month,
            ],
            'by_type' => $by_type,
            'chart'   => [
                'labels' => $labels,
                'data'   => array_values( $month_data ),
            ],
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: REPORTE DE INSCRIPCIONES
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve:
     * - KPIs: total postulaciones, pendientes, aprobadas, retiradas.
     * - Breakdown por curso para gráfico de barras agrupado.
     * - Tabla detallada.
     */
    public static function ajax_report_enrollments(): void {
        self::verify();
        global $wpdb;

        $t_sub = $wpdb->prefix . 'aura_form_submissions';
        $t_enr = $wpdb->prefix . 'aura_student_enrollments';
        $t_stu = $wpdb->prefix . 'aura_students';
        $t_crs = $wpdb->prefix . 'aura_student_courses';

        // ── KPIs globales por estado de enrollment ───────────────
        $status_rows = $wpdb->get_results(
            "SELECT e.status, COUNT(*) AS total
               FROM {$t_sub} s
               LEFT JOIN {$t_enr} e ON e.id = s.enrollment_id
              WHERE s.enrollment_id IS NOT NULL
              GROUP BY e.status"
        );
        $status_map = [];
        foreach ( $status_rows as $r ) {
            $status_map[ $r->status ?? 'unknown' ] = (int) $r->total;
        }

        $kpi_approved  = ( $status_map['active'] ?? 0 ) + ( $status_map['completed'] ?? 0 );
        $kpi_pending   = $status_map['pending']   ?? 0;
        $kpi_withdrawn = $status_map['withdrawn'] ?? 0;
        $kpi_total     = array_sum( $status_map );

        // ── Por curso ────────────────────────────────────────────
        $no_course_label = esc_sql( __( 'Sin curso asignado', 'aura-suite' ) );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $by_course = $wpdb->get_results(
            "SELECT COALESCE(c.name, '{$no_course_label}') AS course_name,
                    SUM(CASE WHEN e.status = 'pending'   THEN 1 ELSE 0 END)            AS pending,
                    SUM(CASE WHEN e.status IN ('active','completed') THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN e.status = 'withdrawn' THEN 1 ELSE 0 END)            AS withdrawn,
                    COUNT(*) AS total
               FROM {$t_sub} s
               LEFT JOIN {$t_enr} e ON e.id   = s.enrollment_id
               LEFT JOIN {$t_stu} st ON st.id = e.student_id
               LEFT JOIN {$t_crs} c  ON c.id  = e.course_id
              WHERE s.enrollment_id IS NOT NULL
              GROUP BY c.id, c.name
              ORDER BY total DESC
              LIMIT 20"
        );
        // phpcs:enable

        $table_rows = [];
        foreach ( $by_course as $r ) {
            $pct = (int) $r->total > 0
                ? round( ( (int) $r->approved / (int) $r->total ) * 100 )
                : 0;
            $table_rows[] = [
                'course'    => $r->course_name,
                'pending'   => (int) $r->pending,
                'approved'  => (int) $r->approved,
                'withdrawn' => (int) $r->withdrawn,
                'total'     => (int) $r->total,
                'pct'       => $pct,
            ];
        }

        wp_send_json_success( [
            'kpis' => [
                'total'     => $kpi_total,
                'pending'   => $kpi_pending,
                'approved'  => $kpi_approved,
                'withdrawn' => $kpi_withdrawn,
            ],
            'chart' => [
                'labels'    => array_column( $table_rows, 'course' ),
                'pending'   => array_column( $table_rows, 'pending' ),
                'approved'  => array_column( $table_rows, 'approved' ),
                'withdrawn' => array_column( $table_rows, 'withdrawn' ),
            ],
            'table' => $table_rows,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: REPORTE DE ENCUESTAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve:
     * - KPIs: total asignadas, completadas, pendientes, expiradas, tasa.
     * - Por formulario encuesta: completadas / pendientes / expiradas.
     */
    public static function ajax_report_surveys(): void {
        self::verify();
        global $wpdb;

        $t_asgn  = $wpdb->prefix . 'aura_form_assignments';
        $t_forms = $wpdb->prefix . 'aura_forms';

        // ── KPIs ────────────────────────────────────────────────
        $total_assigned  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_asgn}" );
        $total_completed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_asgn} WHERE status = 'completed'" );
        $total_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_asgn} WHERE status = 'pending'" );
        $total_expired   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_asgn} WHERE status = 'expired'" );
        $completion_rate = $total_assigned > 0 ? round( ( $total_completed / $total_assigned ) * 100 ) : 0;

        // ── Por formulario ───────────────────────────────────────
        $per_form = $wpdb->get_results(
            "SELECT f.title,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN a.status = 'pending'   THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN a.status = 'expired'   THEN 1 ELSE 0 END) AS expired,
                    COUNT(*) AS total
               FROM {$t_asgn} a
               LEFT JOIN {$t_forms} f ON f.id = a.form_id
              GROUP BY a.form_id, f.title
              ORDER BY total DESC
              LIMIT 15"
        );

        $table_rows = [];
        foreach ( $per_form as $r ) {
            $rate = (int) $r->total > 0
                ? round( ( (int) $r->completed / (int) $r->total ) * 100 )
                : 0;
            $table_rows[] = [
                'title'     => $r->title ?? '—',
                'total'     => (int) $r->total,
                'completed' => (int) $r->completed,
                'pending'   => (int) $r->pending,
                'expired'   => (int) $r->expired,
                'rate'      => $rate,
            ];
        }

        wp_send_json_success( [
            'kpis' => [
                'total_assigned'  => $total_assigned,
                'total_completed' => $total_completed,
                'total_pending'   => $total_pending,
                'total_expired'   => $total_expired,
                'completion_rate' => $completion_rate,
            ],
            'chart' => [
                'labels'    => array_column( $table_rows, 'title' ),
                'completed' => array_column( $table_rows, 'completed' ),
                'pending'   => array_column( $table_rows, 'pending' ),
                'expired'   => array_column( $table_rows, 'expired' ),
            ],
            'table' => $table_rows,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER PRIVADO
    // ─────────────────────────────────────────────────────────────

    private static function verify(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_forms_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permiso para ver reportes.', 'aura-suite' ) ] );
        }
    }
}
