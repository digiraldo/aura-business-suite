<?php
/**
 * Configuración del Módulo de Validación Documental
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_Settings {

    const OPTION_KEY = 'aura_docverify_settings';

    /**
     * Inicializar hooks AJAX.
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_docverify_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_aura_docverify_get_settings',  [ __CLASS__, 'ajax_get_settings' ] );
    }

    /**
     * Valores por defecto integrados con Identidad de la Organización de AURA.
     */
    public static function defaults(): array {
        $org_name = function_exists( 'aura_get_org_name' )
            ? aura_get_org_name()
            : get_option( 'aura_org_name', get_bloginfo( 'name' ) );

        $org_logo = function_exists( 'aura_get_org_logo_url' )
            ? aura_get_org_logo_url( 'medium' )
            : (string) get_option( 'aura_org_logo_url', '' );

        return [
            'org_name'             => (string) $org_name,
            'org_logo_url'         => (string) $org_logo,
            'org_tax_id'           => (string) get_option( 'aura_org_tax_id', '' ),
            'org_address'          => (string) get_option( 'aura_org_address', '' ),
            'org_country'          => '',
            'consular_email'       => (string) get_option( 'admin_email', '' ),
            'consular_phone'       => '',
            'verify_slug'          => 'verificar-documento',
            'verify_page_id'       => 0,
            'folio_padding'        => 4,
            'prefix_invitation'    => 'INV',
            'prefix_migration'     => 'MIG',
            'prefix_official'      => 'OFI',
            'prefix_constancy'     => 'CON',
            'prefix_sponsorship'   => 'PAT',
            'prefix_authorization' => 'AUT',
            'prefix_agreement'     => 'CNV',
            'prefix_other'         => 'DOC',
            'default_stamp_mode'   => 'annex_page',
            'default_stamp_pos'    => 'bottom_right',
            'default_privacy_mode' => 'standard_masked',
            'use_google_drive'     => true,
            'keep_local_backup'    => false,
            'drive_folder_name'    => 'Validación Documental',
            'qr_scale'             => 6,
            'max_upload_mb'        => 15,
            'rate_limit_max'       => 20,
            'rate_limit_minutes'   => 10,
        ];
    }

    /**
     * Obtener una opción con fallback.
     *
     * @param string $key
     * @param mixed  $fallback
     * @return mixed
     */
    public static function get( string $key, $fallback = null ) {
        $all = self::get_all();
        return $all[ $key ] ?? $fallback;
    }

    /**
     * Obtener todas las opciones combinadas con defaults.
     */
    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        $all   = array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );

        if ( trim( (string) $all['org_name'] ) === '' ) {
            $all['org_name'] = function_exists( 'aura_get_org_name' )
                ? aura_get_org_name()
                : get_bloginfo( 'name' );
        }
        if ( trim( (string) $all['org_logo_url'] ) === '' && function_exists( 'aura_get_org_logo_url' ) ) {
            $all['org_logo_url'] = aura_get_org_logo_url( 'medium' );
        }

        return $all;
    }

    /**
     * Catálogo de tipos documentales institucionales.
     */
    public static function get_document_types(): array {
        return [
            'invitation_letter'    => __( 'Carta de Invitación (Visa / Embajada)', 'aura-suite' ),
            'migration_invitation' => __( 'Carta de Invitación (Migración / Control Fronterizo)', 'aura-suite' ),
            'official_letter'      => __( 'Oficio / Comunicado Oficial', 'aura-suite' ),
            'constancy'            => __( 'Constancia / Certificación Institucional', 'aura-suite' ),
            'sponsorship'          => __( 'Carta de Patrocinio / Apoyo', 'aura-suite' ),
            'authorization'        => __( 'Autorización / Poder / Permiso', 'aura-suite' ),
            'agreement'            => __( 'Convenio / Acta / Resolución', 'aura-suite' ),
            'other'                => __( 'Otro Documento Institucional', 'aura-suite' ),
        ];
    }

    /**
     * Etiqueta bilingüe del tipo documental (útil en Hoja Anexa y portal consular ES/EN).
     */
    public static function get_document_type_labels( string $type ): array {
        $map = [
            'invitation_letter' => [
                'es' => 'Carta de Invitación (Visa / Trámite Consular)',
                'en' => 'Official Letter of Invitation (Visa / Consular)',
            ],
            'migration_invitation' => [
                'es' => 'Carta de Invitación (Migración / Control Fronterizo)',
                'en' => 'Invitation Letter (Immigration / Border Control)',
            ],
            'official_letter' => [
                'es' => 'Oficio / Comunicado Oficial',
                'en' => 'Official Letter / Memorandum',
            ],
            'constancy' => [
                'es' => 'Constancia / Certificación Institucional',
                'en' => 'Institutional Certificate / Attestation',
            ],
            'sponsorship' => [
                'es' => 'Carta de Patrocinio / Apoyo Institucional',
                'en' => 'Letter of Sponsorship / Institutional Support',
            ],
            'authorization' => [
                'es' => 'Autorización / Permiso Oficial',
                'en' => 'Official Authorization / Consent',
            ],
            'agreement' => [
                'es' => 'Convenio / Acta / Resolución',
                'en' => 'Agreement / Resolution / Official Record',
            ],
            'other' => [
                'es' => 'Documento Institucional Verificable',
                'en' => 'Verified Institutional Document',
            ],
        ];
        return $map[ $type ] ?? $map['other'];
    }

    /**
     * Prefijo configurado según el tipo documental.
     */
    public static function get_prefix_for_type( string $doc_type ): string {
        $map = [
            'invitation_letter'    => 'prefix_invitation',
            'migration_invitation' => 'prefix_migration',
            'official_letter'      => 'prefix_official',
            'constancy'         => 'prefix_constancy',
            'sponsorship'       => 'prefix_sponsorship',
            'authorization'     => 'prefix_authorization',
            'agreement'         => 'prefix_agreement',
            'other'             => 'prefix_other',
        ];
        $key    = $map[ $doc_type ] ?? 'prefix_other';
        $prefix = strtoupper( preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) self::get( $key, 'DOC' ) ) ) );
        return $prefix !== '' ? substr( $prefix, 0, 8 ) : 'DOC';
    }

    /**
     * AJAX: Obtener configuración actual.
     */
    public static function ajax_get_settings(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ], 403 );
        }

        wp_send_json_success( self::get_all() );
    }

    /**
     * AJAX: Guardar configuración del módulo.
     */
    public static function ajax_save_settings(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para modificar la configuración.', 'aura-suite' ) ], 403 );
        }

        $current = self::get_all();
        $tab     = sanitize_key( wp_unslash( $_POST['settings_tab'] ?? 'all' ) );

        if ( $tab === 'institutional' || $tab === 'all' ) {
            $current['org_name']       = sanitize_text_field( wp_unslash( $_POST['org_name'] ?? $current['org_name'] ) );
            $current['org_logo_url']   = esc_url_raw( wp_unslash( $_POST['org_logo_url'] ?? $current['org_logo_url'] ) );
            $current['org_tax_id']     = sanitize_text_field( wp_unslash( $_POST['org_tax_id'] ?? $current['org_tax_id'] ) );
            $current['org_address']    = sanitize_text_field( wp_unslash( $_POST['org_address'] ?? $current['org_address'] ) );
            $current['org_country']    = sanitize_text_field( wp_unslash( $_POST['org_country'] ?? $current['org_country'] ) );
            $current['consular_email'] = sanitize_email( wp_unslash( $_POST['consular_email'] ?? $current['consular_email'] ) );
            $current['consular_phone'] = sanitize_text_field( wp_unslash( $_POST['consular_phone'] ?? $current['consular_phone'] ) );
        }

        if ( $tab === 'folios' || $tab === 'all' ) {
            $slug = sanitize_title( wp_unslash( $_POST['verify_slug'] ?? $current['verify_slug'] ) );
            $current['verify_slug']   = $slug ?: 'verificar-documento';
            $current['folio_padding'] = max( 3, min( 8, absint( $_POST['folio_padding'] ?? $current['folio_padding'] ) ) );

            $route_mode = sanitize_key( wp_unslash( $_POST['route_mode'] ?? '' ) );
            if ( $route_mode === 'clean_slug' ) {
                $current['verify_page_id'] = 0;
            } elseif ( $route_mode === 'wp_page' && empty( $current['verify_page_id'] ) && class_exists( 'Aura_Page_Manager' ) ) {
                $linked_id = Aura_Page_Manager::get_id( 'doc_verify' );
                if ( $linked_id > 0 && get_post_status( $linked_id ) === 'publish' ) {
                    $current['verify_page_id'] = $linked_id;
                }
            }

            $prefix_keys = [
                'prefix_invitation',
                'prefix_migration',
                'prefix_official',
                'prefix_constancy',
                'prefix_sponsorship',
                'prefix_authorization',
                'prefix_agreement',
                'prefix_other',
            ];
            foreach ( $prefix_keys as $pk ) {
                if ( isset( $_POST[ $pk ] ) ) {
                    $clean = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', sanitize_text_field( wp_unslash( $_POST[ $pk ] ) ) ) );
                    if ( $clean !== '' ) {
                        $current[ $pk ] = substr( $clean, 0, 8 );
                    }
                }
            }
        }

        if ( $tab === 'storage_pdf' || $tab === 'all' ) {
            $stamp_mode = sanitize_key( wp_unslash( $_POST['default_stamp_mode'] ?? $current['default_stamp_mode'] ) );
            if ( in_array( $stamp_mode, [ 'annex_page', 'overlay_corner', 'none' ], true ) ) {
                $current['default_stamp_mode'] = $stamp_mode;
            }
            $stamp_pos = sanitize_key( wp_unslash( $_POST['default_stamp_pos'] ?? $current['default_stamp_pos'] ) );
            if ( in_array( $stamp_pos, [ 'bottom_right', 'bottom_left', 'bottom_bar' ], true ) ) {
                $current['default_stamp_pos'] = $stamp_pos;
            }
            $privacy = sanitize_key( wp_unslash( $_POST['default_privacy_mode'] ?? $current['default_privacy_mode'] ) );
            if ( in_array( $privacy, [ 'standard_masked', 'pin_protected', 'public_summary' ], true ) ) {
                $current['default_privacy_mode'] = $privacy;
            }
            $current['use_google_drive']  = ! empty( $_POST['use_google_drive'] );
            $current['keep_local_backup'] = ! empty( $_POST['keep_local_backup'] );
            $drive_folder = sanitize_text_field( wp_unslash( $_POST['drive_folder_name'] ?? $current['drive_folder_name'] ) );
            $current['drive_folder_name'] = $drive_folder !== '' ? $drive_folder : 'Validación Documental';
            $current['max_upload_mb']     = max( 2, min( 50, absint( $_POST['max_upload_mb'] ?? $current['max_upload_mb'] ) ) );
            $current['qr_scale']          = max( 4, min( 10, absint( $_POST['qr_scale'] ?? $current['qr_scale'] ) ) );
        }

        if ( $tab === 'security' || $tab === 'all' ) {
            $current['rate_limit_max']     = max( 5, min( 120, absint( $_POST['rate_limit_max'] ?? $current['rate_limit_max'] ) ) );
            $current['rate_limit_minutes'] = max( 1, min( 60, absint( $_POST['rate_limit_minutes'] ?? $current['rate_limit_minutes'] ) ) );
            if ( isset( $_POST['verify_page_id'] ) ) {
                $current['verify_page_id'] = absint( $_POST['verify_page_id'] );
            }
        }

        update_option( self::OPTION_KEY, $current );
        Aura_DocVerify_Setup::register_rewrite_rules();
        flush_rewrite_rules( false );

        wp_send_json_success( [
            'message'  => __( 'Configuración de Validación Documental guardada correctamente.', 'aura-suite' ),
            'settings' => $current,
        ] );
    }
}
