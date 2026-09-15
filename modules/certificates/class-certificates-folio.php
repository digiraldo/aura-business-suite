<?php
/**
 * Generador de Folios para Certificados
 *
 * Genera secuencias atómicas de folios por año.
 * Formato resultante: CEM-2026-0042
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Folio {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Sin hooks de WP: este módulo es usado por Issuer.
    }

    // ─────────────────────────────────────────────────────────────
    // GENERACIÓN DE FOLIO
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera el siguiente folio único para el año indicado.
     *
     * Usa INSERT ... ON DUPLICATE KEY UPDATE para garantizar atomicidad
     * incluso con peticiones concurrentes.
     *
     * @param int|null $year Año (por defecto: año actual).
     * @return string Folio con formato PREFIX-YYYY-NNNN (ej: CEM-2026-0042)
     */
    public static function generate( ?int $year = null ): string {
        global $wpdb;

        $year   = $year ?? (int) date( 'Y' );
        $table  = $wpdb->prefix . 'aura_certificate_folio_seq';
        $prefix = strtoupper( sanitize_key( Aura_Certificates_Settings::get( 'folio_prefix', 'CEM' ) ) );
        $pad    = max( 1, (int) Aura_Certificates_Settings::get( 'folio_padding', 4 ) );

        // Atomic increment: inserta una nueva fila para el año (seq=1)
        // o incrementa en +1 si ya existe.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (year, last_seq)
                 VALUES (%d, 1)
                 ON DUPLICATE KEY UPDATE last_seq = last_seq + 1",
                $year
            )
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $seq = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT last_seq FROM {$table} WHERE year = %d", $year )
        );

        $folio = sprintf( '%s-%d-%s', $prefix, $year, str_pad( (string) $seq, $pad, '0', STR_PAD_LEFT ) );

        return $folio;
    }

    // ─────────────────────────────────────────────────────────────
    // VALIDACIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Comprueba si un folio existe en la tabla de certificados emitidos.
     *
     * @param string $folio El folio a verificar.
     * @return bool
     */
    public static function exists( string $folio ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE folio = %s", $folio ) );
        return $count > 0;
    }

    /**
     * Comprueba si un folio tiene el formato válido esperado.
     * Ej: CEM-2026-0042
     *
     * @param string $folio
     * @return bool
     */
    public static function is_valid_format( string $folio ): bool {
        return (bool) preg_match( '/^[A-Z]{1,10}-\d{4}-\d+$/', strtoupper( $folio ) );
    }
}
