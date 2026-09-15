<?php
/**
 * Cálculo y Registro de Multas — Biblioteca (Fase 3)
 *
 * Calcula multas por días de retraso y opcionalmente registra el cobro
 * como ingreso en el módulo de Finanzas (si está activo).
 *
 * @package Aura_Business_Suite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Fines {

    // ─────────────────────────────────────────────────────────────
    // CÁLCULO DE MULTA
    // ─────────────────────────────────────────────────────────────

    /**
     * Calcular el monto de multa para un préstamo.
     *
     * @param string      $due_date    Fecha límite del préstamo (Y-m-d).
     * @param string|null $return_date Fecha real de devolución (Y-m-d) o null = hoy.
     * @return float Monto de la multa (0.00 si no hay multas activas o no hay retraso).
     */
    public static function calculate_fine( string $due_date, ?string $return_date = null ): float {

        // Si las multas no están habilitadas, retornar 0
        if ( ! get_option( 'aura_library_fines_enabled', false ) ) {
            return 0.00;
        }

        $fine_per_day  = (float) get_option( 'aura_library_fine_per_day', 0.00 );
        if ( $fine_per_day <= 0 ) {
            return 0.00;
        }

        $check_date = $return_date ?: gmdate( 'Y-m-d' );
        $grace_days = (int) get_option( 'aura_library_grace_days', 1 );

        // Días de retraso = diferencia en días - período de gracia
        $due_ts    = strtotime( $due_date );
        $check_ts  = strtotime( $check_date );
        $diff_days = (int) ( ( $check_ts - $due_ts ) / DAY_IN_SECONDS );
        $late_days = max( 0, $diff_days - $grace_days );

        if ( $late_days === 0 ) {
            return 0.00;
        }

        $fine     = $late_days * $fine_per_day;
        $fine_max = (float) get_option( 'aura_library_fine_max', 0.00 );

        if ( $fine_max > 0 ) {
            $fine = min( $fine, $fine_max );
        }

        return round( $fine, 2 );
    }

    // ─────────────────────────────────────────────────────────────
    // REGISTRAR PAGO EN FINANZAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear un ingreso en el módulo de Finanzas por cobro de multa.
     * Solo se llama si la integración está activa y la clase existe.
     *
     * @param int    $loan_id   ID del préstamo.
     * @param float  $amount    Monto de la multa.
     * @param string $book_title Título del libro (para la descripción).
     * @param string $borrower  Nombre del lector.
     * @return int|null ID de la transacción creada, o null si falló.
     */
    public static function register_in_finance( int $loan_id, float $amount, string $book_title, string $borrower ): ?int {
        if ( ! class_exists( 'Aura_Financial_Transactions' ) ) {
            return null;
        }

        global $wpdb;
        $t_categories = $wpdb->prefix . 'aura_financial_categories';

        // Buscar o crear la categoría "Multas Biblioteca"
        $category_id = (int) $wpdb->get_var(
            "SELECT id FROM {$t_categories}
             WHERE name = 'Multas Biblioteca' AND type = 'income' AND deleted_at IS NULL
             LIMIT 1"
        );

        if ( ! $category_id ) {
            $wpdb->insert( $t_categories, [
                'name'       => 'Multas Biblioteca',
                'type'       => 'income',
                'color'      => '#0073aa',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ] );
            $category_id = $wpdb->insert_id;
        }

        if ( ! $category_id ) {
            return null;
        }

        $t_transactions = $wpdb->prefix . 'aura_transactions';

        $description = sprintf(
            /* translators: 1: ID del préstamo, 2: título del libro, 3: nombre del lector */
            __( 'Multa por retraso — préstamo #%1$d — %2$s (%3$s)', 'aura-business-suite' ),
            $loan_id,
            $book_title,
            $borrower
        );

        $result = $wpdb->insert( $t_transactions, [
            'category_id' => $category_id,
            'amount'      => $amount,
            'type'        => 'income',
            'description' => $description,
            'status'      => 'approved',
            'date'        => gmdate( 'Y-m-d' ),
            'created_by'  => get_current_user_id(),
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ] );

        return ( $result !== false ) ? (int) $wpdb->insert_id : null;
    }
}
