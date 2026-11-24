<?php
/**
 * Portal Público de Verificación Documental (Consular / Institucional)
 *
 * Soporta:
 *  - URL limpia /{verify_slug}/{folio}?t={hmac}
 *  - Shortcode [aura_verificar_documento] con ?doc={folio}&t={hmac} o búsqueda por Folio + Código
 *  - Modo bilingüe ES / EN, rate limiting anti-enumeración, cotejo SHA-256 en navegador y desbloqueo con PIN/Pasaporte
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_Verify {

    /**
     * Inicializar hooks públicos.
     */
    public static function init(): void {
        add_action( 'template_redirect',  [ __CLASS__, 'intercept_verify_page' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
        add_shortcode( 'aura_verificar_documento', [ __CLASS__, 'shortcode_verificar' ] );

        // Endpoints públicos AJAX (sin login requerido para embajadas/receptores)
        add_action( 'wp_ajax_nopriv_aura_docverify_unlock',         [ __CLASS__, 'ajax_unlock_document' ] );
        add_action( 'wp_ajax_aura_docverify_unlock',                [ __CLASS__, 'ajax_unlock_document' ] );
        add_action( 'wp_ajax_nopriv_aura_docverify_public_pdf',     [ __CLASS__, 'ajax_public_pdf_download' ] );
        add_action( 'wp_ajax_aura_docverify_public_pdf',            [ __CLASS__, 'ajax_public_pdf_download' ] );
        add_action( 'wp_ajax_nopriv_aura_docverify_log_hash_check', [ __CLASS__, 'ajax_log_hash_check' ] );
        add_action( 'wp_ajax_aura_docverify_log_hash_check',        [ __CLASS__, 'ajax_log_hash_check' ] );
    }

    /**
     * Intercepta URL /{verify_slug}/{folio}.
     */
    public static function intercept_verify_page(): void {
        $folio = get_query_var( 'aura_doc_verify' );
        if ( empty( $folio ) ) {
            return;
        }

        $folio = strtoupper( sanitize_text_field( (string) $folio ) );
        $token = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );
        $code  = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );

        self::enqueue_assets_force();
        status_header( 200 );

        $result = self::verify_lookup( $folio, $token, $code, 'qr' );
        get_header();
        self::include_template( 'verify-public.php', [ 'verify_result' => $result ] );
        get_footer();
        exit;
    }

    /**
     * Shortcode [aura_verificar_documento].
     */
    public static function shortcode_verificar( array $atts = [] ): string {
        self::enqueue_assets_force();

        $atts  = shortcode_atts( [ 'doc' => '' ], $atts );
        $folio = strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['doc'] ?? $atts['doc'] ) ) );
        $token = sanitize_text_field( wp_unslash( $_REQUEST['t'] ?? '' ) );
        $code  = sanitize_text_field( wp_unslash( $_REQUEST['code'] ?? '' ) );

        ob_start();
        if ( $folio !== '' ) {
            $mode   = $token !== '' ? 'qr' : 'manual';
            $result = self::verify_lookup( $folio, $token, $code, $mode );
            self::include_template( 'verify-public.php', [ 'verify_result' => $result ] );
        } else {
            self::include_template( 'verify-search.php', [ 'rate_limited' => false ] );
        }
        return (string) ob_get_clean();
    }

    /**
     * Encola CSS y JS en páginas que tengan el shortcode o ruta de verificación.
     */
    public static function enqueue_frontend_assets(): void {
        global $post;
        $is_route = ! empty( get_query_var( 'aura_doc_verify' ) );
        $has_sc   = ( $post && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'aura_verificar_documento' ) );

        if ( $is_route || $has_sc ) {
            self::enqueue_assets_force();
        }
    }

    private static function enqueue_assets_force(): void {
        if ( class_exists( 'Aura_Page_Manager' ) ) {
            Aura_Page_Manager::enqueue_theme_assets( [ 'dashicons' ] );
        }

        $plugin_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR : dirname( __DIR__, 2 ) . '/';
        $plugin_url = defined( 'AURA_PLUGIN_URL' ) ? AURA_PLUGIN_URL : plugin_dir_url( dirname( __DIR__, 2 ) . '/aura-business-suite.php' );
        $ver_base   = defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.9.0';

        $css_file = $plugin_dir . 'assets/css/docverify-frontend.css';
        $js_file  = $plugin_dir . 'assets/js/docverify-frontend.js';

        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'aura-docverify-frontend',
                $plugin_url . 'assets/css/docverify-frontend.css',
                [],
                $ver_base . '.' . filemtime( $css_file )
            );
        }

        if ( file_exists( $js_file ) ) {
            wp_enqueue_script(
                'aura-docverify-frontend',
                $plugin_url . 'assets/js/docverify-frontend.js',
                [ 'jquery' ],
                $ver_base . '.' . filemtime( $js_file ),
                true
            );
            wp_localize_script( 'aura-docverify-frontend', 'auraDocVerifyPublic', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'aura_docverify_public_nonce' ),
            ] );
        }
    }

    /**
     * Ejecuta la verificación segura de folio + token HMAC o código de verificación.
     */
    public static function verify_lookup( string $folio, string $token, string $code, string $mode = 'manual' ): array {
        if ( self::is_rate_limited() ) {
            return [
                'found'        => false,
                'rate_limited' => true,
                'status'       => 'rate_limited',
                'folio'        => $folio,
            ];
        }

        self::increment_rate_limit();

        if ( ! Aura_DocVerify_Folio::is_valid_format( $folio ) ) {
            Aura_DocVerify_Manager::log_event( 0, $folio, $mode === 'qr' ? 'verify_qr' : 'verify_manual', 'not_found', [ 'reason' => 'bad_format' ] );
            return [ 'found' => false, 'status' => 'not_found', 'folio' => $folio ];
        }

        $doc = Aura_DocVerify_Manager::get_document_by_folio( $folio );
        if ( ! $doc ) {
            Aura_DocVerify_Manager::log_event( 0, $folio, $mode === 'qr' ? 'verify_qr' : 'verify_manual', 'not_found' );
            return [ 'found' => false, 'status' => 'not_found', 'folio' => $folio ];
        }

        // Validación criptográfica por HMAC (QR) o código manual
        $authorized = false;
        if ( $token !== '' && Aura_DocVerify_Folio::verify_hmac_token( (string) $doc->folio, (string) $doc->verification_code, $token, 'verify' ) ) {
            $authorized = true;
            $mode       = 'qr';
        } elseif ( $code !== '' ) {
            $norm_in  = Aura_DocVerify_Folio::normalize_code( $code );
            $norm_db  = Aura_DocVerify_Folio::normalize_code( (string) $doc->verification_code );
            if ( hash_equals( $norm_db, $norm_in ) ) {
                $authorized = true;
                $mode       = 'manual';
            }
        }

        if ( ! $authorized ) {
            Aura_DocVerify_Manager::log_event( (int) $doc->id, (string) $doc->folio, $mode === 'qr' ? 'verify_qr' : 'verify_manual', 'invalid_token' );
            return [ 'found' => false, 'status' => 'invalid_token', 'folio' => $folio ];
        }

        $eff_status = Aura_DocVerify_Manager::get_effective_status( $doc );

        // Incrementar contador de consultas válidas
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}aura_verified_documents SET verified_count = verified_count + 1, last_verified_at = %s WHERE id = %d",
                current_time( 'mysql' ),
                (int) $doc->id
            )
        );

        Aura_DocVerify_Manager::log_event(
            (int) $doc->id,
            (string) $doc->folio,
            $mode === 'qr' ? 'verify_qr' : 'verify_manual',
            $eff_status === 'active' ? 'valid' : $eff_status
        );

        $settings    = Aura_DocVerify_Settings::get_all();
        $type_labels = Aura_DocVerify_Settings::get_document_type_labels( (string) $doc->doc_type );
        $has_pin     = ! empty( $doc->access_pin_hash ) || ! empty( $doc->holder_doc_hash );
        $is_locked   = ( $doc->privacy_mode === 'pin_protected' && $has_pin );

        $verify_sig  = Aura_DocVerify_Folio::build_hmac_token( (string) $doc->folio, (string) $doc->verification_code, 'verify' );
        $pdf_sig     = Aura_DocVerify_Folio::build_hmac_token( (string) $doc->folio, (string) $doc->verification_code, 'public_pdf' );

        return [
            'found'                 => true,
            'status'                => $eff_status,
            'id'                    => (int) $doc->id,
            'folio'                 => $doc->folio,
            'verification_code'     => $doc->verification_code,
            'verify_sig'            => $verify_sig,
            'pdf_sig'               => $pdf_sig,
            'doc_type'              => $doc->doc_type,
            'doc_type_es'           => $type_labels['es'],
            'doc_type_en'           => $type_labels['en'],
            'title_subject'         => $doc->title_subject,
            'recipient_entity'      => $doc->recipient_entity,
            'recipient_country'     => $doc->recipient_country,
            'purpose'               => $doc->purpose,
            'holder_name'           => $doc->holder_name,
            'holder_doc_type'       => $doc->holder_doc_type,
            'holder_doc_masked'     => $doc->holder_doc_masked,
            'travel_or_event_start' => $doc->travel_or_event_start,
            'travel_or_event_end'   => $doc->travel_or_event_end,
            'stay_address'          => $doc->stay_address ?? '',
            'signer_name'           => $doc->signer_name,
            'signer_title'          => $doc->signer_title,
            'signer_email'          => $doc->signer_email,
            'issued_at'             => $doc->issued_at,
            'expires_at'            => $doc->expires_at,
            'revoke_reason'         => $eff_status === 'revoked' ? $doc->revoke_reason : '',
            'revoked_at'            => $eff_status === 'revoked' ? $doc->revoked_at : '',
            'sha256_original'       => strtolower( (string) $doc->sha256_original ),
            'sha256_stamped'        => strtolower( (string) $doc->sha256_stamped ),
            'privacy_mode'          => $doc->privacy_mode,
            'is_locked'             => $is_locked,
            'allow_public_pdf'      => (int) $doc->allow_public_pdf === 1,
            'has_pin_or_doc'        => $has_pin,
            'verified_count'        => (int) $doc->verified_count + 1,
            'checked_at_utc'        => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
            'org'                   => [
                'name'    => $settings['org_name'],
                'logo'    => $settings['org_logo_url'],
                'tax_id'  => $settings['org_tax_id'],
                'address' => $settings['org_address'],
                'country' => $settings['org_country'],
                'email'   => $settings['consular_email'],
                'phone'   => $settings['consular_phone'],
            ],
        ];
    }

    /**
     * AJAX público: desbloquea detalle/descarga mediante PIN o Pasaporte/Documento del titular.
     */
    public static function ajax_unlock_document(): void {
        check_ajax_referer( 'aura_docverify_public_nonce', 'nonce' );

        if ( self::is_rate_limited() ) {
            wp_send_json_error( [ 'message' => __( 'Demasiados intentos. Espera unos minutos.', 'aura-suite' ) ], 429 );
        }

        $folio  = strtoupper( sanitize_text_field( wp_unslash( $_POST['folio'] ?? '' ) ) );
        $sig    = sanitize_text_field( wp_unslash( $_POST['sig'] ?? '' ) );
        $secret = trim( sanitize_text_field( wp_unslash( $_POST['secret'] ?? '' ) ) );

        $doc = Aura_DocVerify_Manager::get_document_by_folio( $folio );
        if ( ! $doc || ! Aura_DocVerify_Folio::verify_hmac_token( (string) $doc->folio, (string) $doc->verification_code, $sig, 'verify' ) ) {
            self::increment_rate_limit();
            wp_send_json_error( [ 'message' => __( 'Documento o firma de consulta inválidos.', 'aura-suite' ) ], 403 );
        }

        $valid = false;
        if ( ! empty( $doc->access_pin_hash ) && wp_check_password( $secret, (string) $doc->access_pin_hash ) ) {
            $valid = true;
        }
        if ( ! $valid && ! empty( $doc->holder_doc_hash ) ) {
            $secret_hash = Aura_DocVerify_Folio::hash_document_number( $secret );
            if ( $secret_hash !== '' && hash_equals( strtolower( (string) $doc->holder_doc_hash ), strtolower( $secret_hash ) ) ) {
                $valid = true;
            }
        }

        if ( ! $valid ) {
            self::increment_rate_limit();
            Aura_DocVerify_Manager::log_event( (int) $doc->id, (string) $doc->folio, 'pin_unlock', 'failed_pin' );
            wp_send_json_error( [ 'message' => __( 'El PIN o número de documento/pasaporte no coincide.', 'aura-suite' ) ], 403 );
        }

        Aura_DocVerify_Manager::log_event( (int) $doc->id, (string) $doc->folio, 'pin_unlock', 'ok' );

        $unlock_token = Aura_DocVerify_Folio::build_hmac_token( (string) $doc->folio, (string) $doc->verification_code, 'unlock_ok' );
        $pdf_sig      = Aura_DocVerify_Folio::build_hmac_token( (string) $doc->folio, (string) $doc->verification_code, 'public_pdf' );

        $dl_url = '';
        if ( (int) $doc->allow_public_pdf === 1 ) {
            $dl_url = add_query_arg( [
                'action' => 'aura_docverify_public_pdf',
                'folio'  => $doc->folio,
                'sig'    => $pdf_sig,
                'unlock' => $unlock_token,
            ], admin_url( 'admin-ajax.php' ) );
        }

        wp_send_json_success( [
            'unlocked'          => true,
            'download_pdf_url'  => $dl_url,
            'recipient_entity'  => $doc->recipient_entity,
            'recipient_country' => $doc->recipient_country,
            'purpose'           => $doc->purpose,
            'holder_name'       => $doc->holder_name,
            'holder_doc_masked' => $doc->holder_doc_masked,
            'stay_address'      => $doc->stay_address ?? '',
            'period'            => trim( (string) ( $doc->travel_or_event_start ?? '' ) . ' → ' . (string) ( $doc->travel_or_event_end ?? '' ), ' →' ),
        ] );
    }

    /**
     * Descarga pública segura vía proxy (desde Google Drive o local) cuando está habilitada.
     */
    public static function ajax_public_pdf_download(): void {
        $folio  = strtoupper( sanitize_text_field( wp_unslash( $_GET['folio'] ?? '' ) ) );
        $sig    = sanitize_text_field( wp_unslash( $_GET['sig'] ?? '' ) );
        $unlock = sanitize_text_field( wp_unslash( $_GET['unlock'] ?? '' ) );

        $doc = Aura_DocVerify_Manager::get_document_by_folio( $folio );
        if ( ! $doc || (int) $doc->allow_public_pdf !== 1 ) {
            wp_die( esc_html__( 'Descarga pública no habilitada para este documento.', 'aura-suite' ), 403 );
        }

        if ( ! Aura_DocVerify_Folio::verify_hmac_token( (string) $doc->folio, (string) $doc->verification_code, $sig, 'public_pdf' ) ) {
            wp_die( esc_html__( 'Token de descarga inválido.', 'aura-suite' ), 403 );
        }

        $requires_unlock = ( $doc->privacy_mode === 'pin_protected' ) && ( ! empty( $doc->access_pin_hash ) || ! empty( $doc->holder_doc_hash ) );
        if ( $requires_unlock && ! Aura_DocVerify_Folio::verify_hmac_token( (string) $doc->folio, (string) $doc->verification_code, $unlock, 'unlock_ok' ) ) {
            wp_die( esc_html__( 'Se requiere validar PIN o número de pasaporte antes de descargar.', 'aura-suite' ), 403 );
        }

        $file = Aura_DocVerify_Manager::read_pdf_binary( $doc, 'stamped' );
        if ( is_wp_error( $file ) ) {
            wp_die( esc_html( $file->get_error_message() ), 404 );
        }

        Aura_DocVerify_Manager::log_event( (int) $doc->id, (string) $doc->folio, 'download_pdf', 'ok', [ 'context' => 'public' ] );

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $file['filename'] . '"' );
        header( 'Content-Length: ' . strlen( $file['content'] ) );
        echo $file['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Registra en auditoría el resultado de un cotejo SHA-256 local en el navegador.
     */
    public static function ajax_log_hash_check(): void {
        check_ajax_referer( 'aura_docverify_public_nonce', 'nonce' );

        $folio  = strtoupper( sanitize_text_field( wp_unslash( $_POST['folio'] ?? '' ) ) );
        $sig    = sanitize_text_field( wp_unslash( $_POST['sig'] ?? '' ) );
        $match  = sanitize_key( wp_unslash( $_POST['match'] ?? 'none' ) );
        $doc    = Aura_DocVerify_Manager::get_document_by_folio( $folio );

        if ( ! $doc || ! Aura_DocVerify_Folio::verify_hmac_token( (string) $doc->folio, (string) $doc->verification_code, $sig, 'verify' ) ) {
            wp_send_json_error( [], 403 );
        }

        $is_ok = in_array( $match, [ 'original', 'stamped' ], true );
        Aura_DocVerify_Manager::log_event(
            (int) $doc->id,
            (string) $doc->folio,
            $is_ok ? 'verify_hash_ok' : 'verify_hash_fail',
            $is_ok ? 'valid' : 'mismatch',
            [ 'matched_variant' => $match ]
        );

        wp_send_json_success( [ 'logged' => true ] );
    }

    private static function get_rate_limit_key(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        return 'aura_dv_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 20 );
    }

    private static function is_rate_limited(): bool {
        $max   = max( 5, (int) Aura_DocVerify_Settings::get( 'rate_limit_max', 20 ) );
        $count = (int) get_transient( self::get_rate_limit_key() );
        return $count >= $max;
    }

    private static function increment_rate_limit(): void {
        $key  = self::get_rate_limit_key();
        $mins = max( 1, (int) Aura_DocVerify_Settings::get( 'rate_limit_minutes', 10 ) );
        $cur  = (int) get_transient( $key );
        set_transient( $key, $cur + 1, $mins * MINUTE_IN_SECONDS );
    }

    private static function include_template( string $filename, array $vars = [] ): void {
        extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        $theme_path  = get_stylesheet_directory() . '/aura-docverify/' . $filename;
        $plugin_path = AURA_PLUGIN_DIR . 'templates/docverify/' . $filename;
        $file        = file_exists( $theme_path ) ? $theme_path : $plugin_path;
        if ( file_exists( $file ) ) {
            include $file;
        }
    }
}
