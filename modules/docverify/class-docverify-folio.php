<?php
/**
 * Generador de Folios, Códigos de Verificación, Tokens HMAC y Enmascaramiento
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_Folio {

    /** Alfabeto sin caracteres ambiguos (sin 0, O, 1, I, L) */
    const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * Genera el siguiente folio atómico único para el tipo documental y año.
     * Ejemplo: INV-2026-0001 o DOC-2026-0014.
     *
     * @param string   $doc_type Tipo documental.
     * @param int|null $year     Año de emisión.
     * @return string
     */
    public static function generate( string $doc_type = 'other', ?int $year = null ): string {
        global $wpdb;

        $year    = $year ?? (int) current_time( 'Y' );
        $prefix  = Aura_DocVerify_Settings::get_prefix_for_type( $doc_type );
        $pad     = max( 3, (int) Aura_DocVerify_Settings::get( 'folio_padding', 4 ) );
        $seq_key = $prefix . '-' . $year;
        $table   = $wpdb->prefix . 'aura_docverify_folio_seq';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (seq_key, last_seq)
                 VALUES (%s, 1)
                 ON DUPLICATE KEY UPDATE last_seq = last_seq + 1",
                $seq_key
            )
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $seq = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT last_seq FROM {$table} WHERE seq_key = %s", $seq_key )
        );

        $folio = sprintf( '%s-%d-%s', $prefix, $year, str_pad( (string) max( 1, $seq ), $pad, '0', STR_PAD_LEFT ) );

        // Garantía extra ante inserciones manuales previas
        while ( self::exists( $folio ) ) {
            $seq++;
            $folio = sprintf( '%s-%d-%s', $prefix, $year, str_pad( (string) $seq, $pad, '0', STR_PAD_LEFT ) );
        }

        return $folio;
    }

    /**
     * Comprueba si un folio ya existe en BD.
     */
    public static function exists( string $folio ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_verified_documents';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE folio = %s", $folio ) );
        return $count > 0;
    }

    /**
     * Valida formato de folio (PREFIX-YYYY-NNNN).
     */
    public static function is_valid_format( string $folio ): bool {
        return (bool) preg_match( '/^[A-Z0-9]{2,10}-\d{4}-\d{1,10}$/', strtoupper( trim( $folio ) ) );
    }

    /**
     * Genera un código de verificación único de alta entropía (ej. A8F3-92KD-7Q1M).
     */
    public static function generate_verification_code( int $groups = 3, int $group_len = 4 ): string {
        global $wpdb;
        $table    = $wpdb->prefix . 'aura_verified_documents';
        $alphabet = self::CODE_ALPHABET;
        $max_idx  = strlen( $alphabet ) - 1;

        for ( $attempt = 0; $attempt < 10; $attempt++ ) {
            $parts = [];
            for ( $g = 0; $g < $groups; $g++ ) {
                $chunk = '';
                $bytes = random_bytes( $group_len );
                for ( $i = 0; $i < $group_len; $i++ ) {
                    $idx    = ord( $bytes[ $i ] ) % ( $max_idx + 1 );
                    $chunk .= $alphabet[ $idx ];
                }
                $parts[] = $chunk;
            }
            $code = implode( '-', $parts );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $used = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE verification_code = %s", $code )
            );
            if ( $used === 0 ) {
                return $code;
            }
        }

        return strtoupper( substr( bin2hex( random_bytes( 6 ) ), 0, 12 ) );
    }

    /**
     * Normaliza un código de verificación ingresado por usuario.
     */
    public static function normalize_code( string $code ): string {
        $clean = strtoupper( preg_replace( '/[^A-Z0-9]/', '', strtoupper( trim( $code ) ) ) );
        if ( strlen( $clean ) === 12 ) {
            return substr( $clean, 0, 4 ) . '-' . substr( $clean, 4, 4 ) . '-' . substr( $clean, 8, 4 );
        }
        return strtoupper( trim( $code ) );
    }

    /**
     * Genera token HMAC-SHA256 truncado (24 chars hex) para URLs de QR y descarga.
     */
    public static function build_hmac_token( string $folio, string $verification_code, string $purpose = 'verify' ): string {
        $payload = strtoupper( trim( $folio ) ) . '|' . self::normalize_code( $verification_code ) . '|' . $purpose;
        return substr( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), 0, 24 );
    }

    /**
     * Verifica token HMAC en tiempo constante.
     */
    public static function verify_hmac_token( string $folio, string $verification_code, string $token, string $purpose = 'verify' ): bool {
        $token = trim( $token );
        if ( $token === '' ) {
            return false;
        }
        $expected = self::build_hmac_token( $folio, $verification_code, $purpose );
        return hash_equals( $expected, $token );
    }

    /**
     * Construye la URL pública de verificación para codificar en QR.
     */
    public static function build_verify_url( string $folio, string $verification_code ): string {
        $token    = self::build_hmac_token( $folio, $verification_code, 'verify' );
        $url_mode = (string) Aura_DocVerify_Settings::get( 'url_mode', 'clean_slug' );
        $page_id  = (int) Aura_DocVerify_Settings::get( 'verify_page_id', 0 );

        if ( $url_mode === 'wp_page' && $page_id > 0 && get_post_status( $page_id ) === 'publish' ) {
            $base = get_permalink( $page_id );
            return add_query_arg(
                [
                    'doc' => $folio,
                    't'   => $token,
                ],
                $base
            );
        }

        $slug = class_exists( 'Aura_DocVerify_Setup' )
            ? Aura_DocVerify_Setup::get_verify_slug()
            : ( sanitize_title( (string) Aura_DocVerify_Settings::get( 'verify_slug', 'verificar-documento' ) ) ?: 'verificar-documento' );
        return add_query_arg(
            [ 't' => $token ],
            home_url( '/' . $slug . '/' . rawurlencode( $folio ) )
        );
    }

    /**
     * Enmascara un número de pasaporte/documento dejando visibles solo los últimos caracteres.
     * Ej: "AB9874821" -> "*****4821"
     */
    public static function mask_document_number( string $raw ): string {
        $clean = preg_replace( '/\s+/', '', trim( $raw ) );
        if ( $clean === '' ) {
            return '';
        }
        $len = strlen( $clean );
        if ( $len <= 4 ) {
            return str_repeat( '*', max( 1, $len - 1 ) ) . substr( $clean, -1 );
        }
        $visible = 4;
        return str_repeat( '*', $len - $visible ) . substr( $clean, -$visible );
    }

    /**
     * Hash criptográfico normalizado para cotejo privado de documento/pasaporte sin almacenarlo en texto plano.
     */
    public static function hash_document_number( string $raw ): string {
        $norm = strtoupper( preg_replace( '/[^A-Z0-9]/', '', strtoupper( trim( $raw ) ) ) );
        if ( $norm === '' ) {
            return '';
        }
        return hash( 'sha256', $norm );
    }
}
