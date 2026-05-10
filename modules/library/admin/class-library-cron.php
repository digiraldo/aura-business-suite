<?php
/**
 * Cron y alertas automáticas del Módulo de Biblioteca
 *
 * Programa el cron diario, procesa alertas de vencimiento y permite
 * la ejecución manual desde la Configuración de Biblioteca.
 *
 * @package AuraBusinessSuite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Cron {

    const CRON_HOOK = 'aura_library_daily_cron';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp',                                   [ __CLASS__, 'schedule_cron_jobs'  ] );
        add_action( self::CRON_HOOK,                        [ __CLASS__, 'process_loan_alerts' ] );
        add_action( 'wp_ajax_aura_library_run_cron_now',    [ __CLASS__, 'ajax_run_cron_now'   ] );
    }

    // ─────────────────────────────────────────────────────────────
    // PROGRAMAR / LIMPIAR CRON
    // ─────────────────────────────────────────────────────────────

    /**
     * Programa el cron diario si no está ya programado.
     * Se invoca en el hook 'wp' para garantizar que WP esté cargado.
     */
    public static function schedule_cron_jobs(): void {
        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            return;
        }

        $cron_hour = get_option( 'aura_library_cron_hour', '08:00' );
        $first_run = strtotime( 'today ' . $cron_hour . ':00' );
        if ( $first_run <= time() ) {
            $first_run = strtotime( 'tomorrow ' . $cron_hour . ':00' );
        }

        wp_schedule_event( $first_run, 'daily', self::CRON_HOOK );
    }

    /**
     * Elimina el cron programado (llamar desde deactivate()).
     */
    public static function clear(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    // ─────────────────────────────────────────────────────────────
    // PROCESO PRINCIPAL DE ALERTAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Revisa todos los préstamos activos/extendidos y:
     * 1. Envía recordatorio N días antes del vencimiento.
     * 2. Envía alerta el día del vencimiento.
     * 3. Marca préstamos vencidos como 'overdue' y envía alerta.
     * 4. Actualiza fine_amount en préstamos vencidos.
     */
    public static function process_loan_alerts(): void {
        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';

        $reminder_days = (int) get_option( 'aura_library_reminder_days', 3 );
        $today         = gmdate( 'Y-m-d' );
        $reminder_date = gmdate( 'Y-m-d', strtotime( "+{$reminder_days} days" ) );

        // Obtener todos los préstamos activos y extendidos
        $loans = $wpdb->get_results(
            "SELECT id, due_date, status, fine_amount
             FROM {$t_loans}
             WHERE status IN ('active','extended')
             ORDER BY due_date ASC"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( empty( $loans ) ) {
            return;
        }

        foreach ( $loans as $loan ) {
            $loan_id  = (int) $loan->id;
            $due_date = $loan->due_date;

            if ( $due_date === $reminder_date ) {
                // Recordatorio N días antes
                if ( class_exists( 'Aura_Library_Notifications' ) ) {
                    Aura_Library_Notifications::send_reminder( $loan_id, '3days' );
                }
            } elseif ( $due_date === $today ) {
                // Vence hoy
                if ( class_exists( 'Aura_Library_Notifications' ) ) {
                    Aura_Library_Notifications::send_reminder( $loan_id, 'today' );
                }
            } elseif ( $due_date < $today ) {
                // Vencido — marcar y calcular multa
                $new_fine = class_exists( 'Aura_Library_Fines' )
                    ? Aura_Library_Fines::calculate_fine( $due_date, null )
                    : (float) $loan->fine_amount;

                $wpdb->update(
                    $t_loans,
                    [
                        'status'      => 'overdue',
                        'fine_amount' => $new_fine,
                    ],
                    [ 'id' => $loan_id ],
                    [ '%s', '%f' ],
                    [ '%d' ]
                );

                if ( class_exists( 'Aura_Library_Notifications' ) ) {
                    Aura_Library_Notifications::send_overdue_alert( $loan_id );
                }
            }
        }

        // Actualizar fine_amount de los que ya estaban como 'overdue' (recalcular acumulado)
        $overdue_loans = $wpdb->get_results(
            "SELECT id, due_date FROM {$t_loans} WHERE status = 'overdue'"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( ! empty( $overdue_loans ) && class_exists( 'Aura_Library_Fines' ) ) {
            foreach ( $overdue_loans as $ol ) {
                $updated_fine = Aura_Library_Fines::calculate_fine( $ol->due_date, null );
                $wpdb->update(
                    $t_loans,
                    [ 'fine_amount' => $updated_fine ],
                    [ 'id' => (int) $ol->id ],
                    [ '%f' ],
                    [ '%d' ]
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Ejecutar alertas manualmente (F5.5)
    // ─────────────────────────────────────────────────────────────

    /**
     * Permite al bibliotecario ejecutar el cron manualmente desde Configuración.
     */
    public static function ajax_run_cron_now(): void {
        check_ajax_referer( 'aura_library_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_library_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para ejecutar el cron.', 'aura-business-suite' ) ] );
        }

        self::process_loan_alerts();

        wp_send_json_success( [
            'message' => __( 'Alertas ejecutadas correctamente.', 'aura-business-suite' ),
        ] );
    }
}
