<?php
/**
 * Configuración del Módulo de Certificados y Diplomas
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Settings {

    const OPTION_KEY = 'aura_certificates_settings';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_cert_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_aura_cert_get_settings',  [ __CLASS__, 'ajax_get_settings'  ] );
    }

    // ─────────────────────────────────────────────────────────────
    // VALORES POR DEFECTO
    // ─────────────────────────────────────────────────────────────

    public static function defaults(): array {
        return [
            'org_name'                   => '',
            'org_logo_url'               => '',
            'folio_prefix'               => 'CEM',
            'folio_padding'              => 4,
            'verify_slug'                => 'verificar-certificado',
            'require_paz_salvo'          => false,
            'default_send_email'         => true,
            'default_send_whatsapp'      => false,
            'default_include_signatures' => true,
            'cert_page_id'               => 0,
            'pdf_dpi'                    => 150,
            'max_active_signers'         => 4,
        ];
    }

    /**
     * Obtener un valor de configuración.
     *
     * @param string $key      Clave de configuración.
     * @param mixed  $fallback Valor de retorno si la clave no existe.
     * @return mixed
     */
    public static function get( string $key, $fallback = null ) {
        $opts = get_option( self::OPTION_KEY, [] );
        $all  = array_merge( self::defaults(), is_array( $opts ) ? $opts : [] );
        return $all[ $key ] ?? $fallback;
    }

    /**
     * Obtener todas las opciones fusionadas con valores por defecto.
     */
    public static function get_all(): array {
        $opts = get_option( self::OPTION_KEY, [] );
        return array_merge( self::defaults(), is_array( $opts ) ? $opts : [] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Guardar configuración
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_settings(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Sin permisos.', 'aura-suite' ) );
        }

        $current = self::get_all();

        // Pestaña General
        if ( isset( $_POST['folio_prefix'] ) ) {
            $current['org_name']      = sanitize_text_field( wp_unslash( $_POST['org_name'] ?? '' ) );
            $current['org_logo_url']  = esc_url_raw( wp_unslash( $_POST['org_logo_url'] ?? '' ) );
            $current['folio_prefix']  = strtoupper( sanitize_text_field( wp_unslash( $_POST['folio_prefix'] ) ) );
            $current['folio_padding'] = max( 1, min( 8, absint( $_POST['folio_padding'] ?? 4 ) ) );
            $current['verify_slug']   = sanitize_title( wp_unslash( $_POST['verify_slug'] ?? 'verificar-certificado' ) );
            $current['pdf_dpi']       = in_array( absint( $_POST['pdf_dpi'] ?? 150 ), [ 72, 96, 150, 300 ], true )
                                        ? absint( $_POST['pdf_dpi'] )
                                        : 150;
            $current['max_active_signers'] = max( 1, min( 6, absint( $_POST['max_active_signers'] ?? 4 ) ) );
        }

        // Pestaña Emisión
        if ( isset( $_POST['require_paz_salvo'] ) ) {
            $current['require_paz_salvo']          = ! empty( $_POST['require_paz_salvo'] );
            $current['default_send_email']         = ! empty( $_POST['default_send_email'] );
            $current['default_send_whatsapp']      = ! empty( $_POST['default_send_whatsapp'] );
            $current['default_include_signatures'] = ! empty( $_POST['default_include_signatures'] );
        }

        // Pestaña Páginas
        if ( isset( $_POST['cert_page_id'] ) ) {
            $current['cert_page_id'] = absint( $_POST['cert_page_id'] );
        }

        update_option( self::OPTION_KEY, $current );

        // Regenerar rewrite rules si el slug cambió
        flush_rewrite_rules( false );

        wp_send_json_success( __( 'Configuración guardada correctamente.', 'aura-suite' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Obtener configuración (para el JS del formulario)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_settings(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Sin permisos.', 'aura-suite' ) );
        }

        wp_send_json_success( self::get_all() );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render(): void {
        include AURA_PLUGIN_DIR . 'templates/certificates/settings.php';
    }
}
