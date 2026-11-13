<?php
/**
 * Admin del Módulo de Certificados y Diplomas
 *
 * Responsabilidades:
 *  - Registrar menús en WordPress Admin (7 submenús)
 *  - Encolar CSS y JS del módulo exclusivamente en páginas de certificados
 *  - Fabric.js solo en la página del editor de plantillas
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Admin {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'admin_menu',                  [ __CLASS__, 'register_menus' ], 16 );
        add_action( 'admin_enqueue_scripts',       [ __CLASS__, 'enqueue_assets' ] );
        add_filter( 'upload_mimes',                [ __CLASS__, 'allow_svg_mime' ] );
        add_filter( 'wp_check_filetype_and_ext',   [ __CLASS__, 'fix_svg_filetype' ], 10, 5 );
        add_filter( 'wp_handle_upload_prefilter',  [ __CLASS__, 'sanitize_svg_upload' ] );
        add_action( 'wp_ajax_aura_cert_upload_media', [ __CLASS__, 'ajax_upload_media' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // MENÚS
    // ─────────────────────────────────────────────────────────────

    public static function register_menus(): void {
        $has_access = (
            current_user_can( 'aura_cert_view_all' ) ||
            current_user_can( 'aura_cert_issue' ) ||
            current_user_can( 'aura_cert_template_view' ) ||
            current_user_can( 'manage_options' )
        );

        if ( ! $has_access ) {
            return;
        }

        // ── Menú principal ────────────────────────────────────────
        add_menu_page(
            __( 'Certificados — AURA', 'aura-suite' ),
            __( 'Certificados', 'aura-suite' ),
            'read',
            'aura-certificates',
            [ __CLASS__, 'render_dashboard' ],
            'dashicons-awards',
            3.4
        );

        // Dashboard
        add_submenu_page(
            'aura-certificates',
            __( 'Dashboard Certificados', 'aura-suite' ),
            __( 'Dashboard', 'aura-suite' ),
            'read',
            'aura-certificates',
            [ __CLASS__, 'render_dashboard' ]
        );

        // Certificados Emitidos
        if ( current_user_can( 'aura_cert_view_all' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-certificates',
                __( 'Certificados Emitidos', 'aura-suite' ),
                __( 'Emitidos', 'aura-suite' ),
                'read',
                'aura-certificates-list',
                [ __CLASS__, 'render_list' ]
            );
        }

        // Plantillas de Diseño
        if ( current_user_can( 'aura_cert_template_view' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-certificates',
                __( 'Plantillas de Diseño', 'aura-suite' ),
                __( 'Plantillas', 'aura-suite' ),
                'read',
                'aura-certificates-templates',
                [ __CLASS__, 'render_templates_list' ]
            );
        }

        // Firmantes
        if ( current_user_can( 'aura_cert_signatures_manage' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-certificates',
                __( 'Firmantes', 'aura-suite' ),
                __( 'Firmantes', 'aura-suite' ),
                'read',
                'aura-certificates-signers',
                [ __CLASS__, 'render_signers' ]
            );
        }

        // Emisión Masiva
        if ( current_user_can( 'aura_cert_issue' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-certificates',
                __( 'Emisión Masiva', 'aura-suite' ),
                __( 'Emisión Masiva', 'aura-suite' ),
                'read',
                'aura-certificates-bulk',
                [ __CLASS__, 'render_bulk_issue' ]
            );
        }

        // Reportes
        if ( current_user_can( 'aura_cert_reports' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-certificates',
                __( 'Reportes de Certificados', 'aura-suite' ),
                __( 'Reportes', 'aura-suite' ),
                'read',
                'aura-certificates-reports',
                [ 'Aura_Certificates_Reports', 'render' ]
            );
        }

        // Configuración
        if ( current_user_can( 'aura_cert_settings' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-certificates',
                __( 'Configuración Certificados', 'aura-suite' ),
                __( 'Configuración', 'aura-suite' ),
                'read',
                'aura-certificates-settings',
                [ 'Aura_Certificates_Settings', 'render' ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDERS (callbacks de menú)
    // ─────────────────────────────────────────────────────────────

    public static function render_dashboard(): void {
        include AURA_PLUGIN_DIR . 'templates/certificates/dashboard.php';
    }

    public static function render_list(): void {
        $action = sanitize_key( $_GET['action'] ?? '' );
        if ( $action === 'issue' ) {
            include AURA_PLUGIN_DIR . 'templates/certificates/issue.php';
        } elseif ( $action === 'view' ) {
            include AURA_PLUGIN_DIR . 'templates/certificates/view.php';
        } else {
            include AURA_PLUGIN_DIR . 'templates/certificates/list.php';
        }
    }

    public static function render_templates_list(): void {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            include AURA_PLUGIN_DIR . 'templates/certificates/template-builder.php';
        } else {
            include AURA_PLUGIN_DIR . 'templates/certificates/templates-list.php';
        }
    }

    public static function render_signers(): void {
        include AURA_PLUGIN_DIR . 'templates/certificates/signers.php';
    }

    public static function render_bulk_issue(): void {
        include AURA_PLUGIN_DIR . 'templates/certificates/bulk-issue.php';
    }

    // ─────────────────────────────────────────────────────────────
    // ASSETS
    // ─────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        // Hooks de todas las páginas del módulo
        $cert_hooks = [
            'toplevel_page_aura-certificates',
            'certificados_page_aura-certificates-list',
            'certificados_page_aura-certificates-templates',
            'certificados_page_aura-certificates-signers',
            'certificados_page_aura-certificates-bulk',
            'certificados_page_aura-certificates-reports',
            'certificados_page_aura-certificates-settings',
        ];

        $page            = sanitize_key( $_GET['page'] ?? '' );
        $is_cert_page    = in_array( $hook, $cert_hooks, true ) || str_starts_with( $page, 'aura-certificates' );
        $is_student_page = str_starts_with( $hook, 'estudiantes_page_aura-students' ) ||
                           $hook === 'toplevel_page_aura-students' ||
                           str_starts_with( $page, 'aura-students' );

        if ( ! $is_cert_page && ! $is_student_page ) {
            return;
        }

        $is_builder = $is_cert_page &&
                      ( $hook === 'certificados_page_aura-certificates-templates' || $page === 'aura-certificates-templates' ) &&
                      isset( $_GET['action'] ) && $_GET['action'] === 'edit';

        // Media uploader (necesario para wp.media en ajustes, firmantes y diseñador de plantillas)
        if (
            $is_builder ||
            $page === 'aura-certificates-settings' ||
            $page === 'aura-certificates-signers' ||
            $hook === 'certificados_page_aura-certificates-settings' ||
            $hook === 'certificados_page_aura-certificates-signers'
        ) {
            wp_enqueue_media();
        }

        // Sortable para reordenar firmantes
        if ( $page === 'aura-certificates-signers' || $hook === 'certificados_page_aura-certificates-signers' ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
        }

        $css_ver = @filemtime( AURA_PLUGIN_DIR . 'assets/css/certificates.css' ) ?: AURA_VERSION;
        wp_enqueue_style(
            'aura-certificates',
            AURA_PLUGIN_URL . 'assets/css/certificates.css',
            [ 'aura-admin-styles' ],
            (string) $css_ver
        );

        // JS principal del módulo — NO cargar en el builder (tiene su propio JS)
        if ( ! $is_builder ) {
            if ( ! wp_script_is( 'aura-third-party-selector', 'registered' ) && file_exists( AURA_PLUGIN_DIR . 'assets/js/third-party-selector.js' ) ) {
                $tp_ver = @filemtime( AURA_PLUGIN_DIR . 'assets/js/third-party-selector.js' ) ?: AURA_VERSION;
                wp_enqueue_script(
                    'aura-third-party-selector',
                    AURA_PLUGIN_URL . 'assets/js/third-party-selector.js',
                    [ 'jquery', 'jquery-ui-autocomplete' ],
                    (string) $tp_ver,
                    true
                );
                wp_localize_script( 'aura-third-party-selector', 'auraCounterpartiesData', [
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'aura_search_counterparties_nonce' ),
                ] );
            }
            $js_ver = @filemtime( AURA_PLUGIN_DIR . 'assets/js/certificates.js' ) ?: AURA_VERSION;
            wp_enqueue_script(
                'aura-certificates',
                AURA_PLUGIN_URL . 'assets/js/certificates.js',
                [ 'jquery', 'aura-third-party-selector' ],
                (string) $js_ver,
                true
            );
            wp_localize_script( 'aura-certificates', 'auraCertificates', self::localize_data() );
        }

        // Fabric.js + builder JS — solo en la página del editor de plantillas
        if ( $is_builder ) {
            // Google Fonts para el editor
            wp_enqueue_style(
                'aura-cert-google-fonts',
                'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;600;700&family=Lato:wght@300;400;700&family=Great+Vibes&family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&family=Raleway:wght@300;400;600;700&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Cinzel:wght@400;600;700&family=Dancing+Script:wght@400;700&display=swap',
                [],
                null
            );

            // Fabric.js — usa archivo local si existe, fallback CDN
            $fabric_local = AURA_PLUGIN_DIR . 'assets/js/vendor/fabric.min.js';
            if ( file_exists( $fabric_local ) ) {
                wp_enqueue_script(
                    'fabric-js',
                    AURA_PLUGIN_URL . 'assets/js/vendor/fabric.min.js',
                    [],
                    '5.3.1',
                    true
                );
            } else {
                wp_enqueue_script(
                    'fabric-js',
                    'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
                    [],
                    '5.3.1',
                    true
                );
            }

            $builder_ver = @filemtime( AURA_PLUGIN_DIR . 'assets/js/certificate-builder.js' ) ?: AURA_VERSION;
            wp_enqueue_script(
                'aura-certificate-builder',
                AURA_PLUGIN_URL . 'assets/js/certificate-builder.js',
                [ 'jquery', 'fabric-js' ],
                (string) $builder_ver,
                true
            );

            wp_localize_script( 'aura-certificate-builder', 'auraCertBuilder', array_merge(
                self::localize_data(),
                [
                    'templateId'      => absint( $_GET['id'] ?? 0 ),
                    'templatesUrl'    => admin_url( 'admin.php?page=aura-certificates-templates' ),
                    'dynamicVars'     => Aura_Certificates_Templates::get_dynamic_variables_list(),
                    'signers'         => Aura_Certificates_Signers::get_active_signers_for_js(),
                    'prebuiltDesigns' => self::get_prebuilt_designs(),
                    'clipartLibrary'  => self::get_clipart_library(),
                ]
            ) );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DATOS LOCALIZADOS PARA JS
    // ─────────────────────────────────────────────────────────────

    public static function localize_data(): array {
        return [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'aura_certificates_nonce' ),
            'siteUrl'       => site_url(),
            'listUrl'       => admin_url( 'admin.php?page=aura-certificates-list' ),
            'verifySlug'    => Aura_Certificates_Settings::get( 'verify_slug', 'verificar-certificado' ),
            'orgName'       => Aura_Certificates_Settings::get( 'org_name', get_bloginfo( 'name' ) ),
            'orgLogoUrl'    => Aura_Certificates_Settings::get( 'org_logo_url', '' ),
            'i18n'          => [
                'confirmRevoke' => __( '¿Revocar este certificado? Esta acción no se puede deshacer.', 'aura-suite' ),
                'confirmDelete' => __( '¿Eliminar esta plantilla? Esta acción es irreversible.', 'aura-suite' ),
                'issuing'       => __( 'Emitiendo certificado…', 'aura-suite' ),
                'success'       => __( 'Certificado emitido correctamente.', 'aura-suite' ),
                'error'         => __( 'Ocurrió un error. Intenta de nuevo.', 'aura-suite' ),
                'saving'        => __( 'Guardando…', 'aura-suite' ),
                'saved'         => __( 'Guardado correctamente.', 'aura-suite' ),
                'loading'       => __( 'Cargando…', 'aura-suite' ),
                'revokeReason'  => __( 'Motivo de revocación (obligatorio):', 'aura-suite' ),
                'revoked'       => __( 'Certificado revocado.', 'aura-suite' ),
                'copied'        => __( 'Enlace copiado al portapapeles.', 'aura-suite' ),
                'bulkQueued'    => __( 'Certificados en cola de emisión. Procesando…', 'aura-suite' ),
                'selectAll'     => __( 'Seleccionar todos', 'aura-suite' ),
                'deselect'      => __( 'Deseleccionar', 'aura-suite' ),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // PLANTILLAS PREDISEÑADAS (JSON Fabric.js)
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve los diseños prediseñados como array indexado.
     * Cada diseño incluye 'json' con el canvas completo de Fabric.js 5.x.
     */
    private static function get_prebuilt_designs(): array {
        return [
            self::preset_diploma_clasico_dorado(),
            self::preset_credencial_academica_foto(),
            self::preset_academico_azul_real(),
            self::preset_ministerial_liderazgo(),
            self::preset_moderno_minimalista(),
            self::preset_corporativo_oscuro_vip(),
            self::preset_participacion(),
            self::preset_constancia_taller(),
            self::preset_institucional_4_firmas(),
            self::preset_diploma_solemne_vertical(),
            self::preset_ejecutivo_foto_vertical(),
            self::preset_reconocimiento_honorifico(),
            self::preset_gala_excelencia_carta(),
        ];
    }

    // ─── 1. Diploma Clásico Dorado (A4 Landscape) ─────────────────
    private static function preset_diploma_clasico_dorado(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // ── Marco exterior e interior (click-through) ────────
            [ 'type' => 'rect', 'left' => 22, 'top' => 22, 'width' => $w - 44, 'height' => $h - 44,
              'fill' => 'transparent', 'stroke' => '#B8860B', 'strokeWidth' => 4, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 34, 'top' => 34, 'width' => $w - 68, 'height' => $h - 68,
              'fill' => 'transparent', 'stroke' => '#D4AA4A', 'strokeWidth' => 1.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            // ── Esquinas ornamentales alineadas al marco ─────────
            [ 'type' => 'rect', 'left' => 18,      'top' => 18,      'width' => 24, 'height' => 24, 'fill' => '#B8860B', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => $w - 42, 'top' => 18,      'width' => 24, 'height' => 24, 'fill' => '#B8860B', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 18,      'top' => $h - 42, 'width' => 24, 'height' => 24, 'fill' => '#B8860B', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => $w - 42, 'top' => $h - 42, 'width' => 24, 'height' => 24, 'fill' => '#B8860B', 'stroke' => null ],

            // ── Institución superior ─────────────────────────────
            [ 'type' => 'textbox', 'left' => 80, 'top' => 58, 'width' => $w - 160,
              'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Cinzel',
              'fontWeight' => 'bold', 'fill' => '#7B6040', 'textAlign' => 'center', 'charSpacing' => 200 ],

            // ── Título DIPLOMA ───────────────────────────────────
            [ 'type' => 'textbox', 'left' => 80, 'top' => 88, 'width' => $w - 160,
              'text' => 'D I P L O M A', 'fontSize' => 58, 'fontFamily' => 'Times New Roman',
              'fontWeight' => 'bold', 'fill' => '#B8860B', 'textAlign' => 'center', 'charSpacing' => 180 ],

            // ── Separador dorado bajo título ─────────────────────
            [ 'type' => 'rect', 'left' => 331, 'top' => 164, 'width' => 460, 'height' => 2.5,
              'fill' => '#B8860B', 'stroke' => null ],

            // ── Introducción ─────────────────────────────────────
            [ 'type' => 'textbox', 'left' => 80, 'top' => 184, 'width' => $w - 160,
              'text' => 'Se otorga el presente Diploma de Honor a:',
              'fontSize' => 17, 'fontFamily' => 'Times New Roman',
              'fontStyle' => 'italic', 'fill' => '#7B6040', 'textAlign' => 'center' ],

            // ── Nombre del estudiante ────────────────────────────
            [ 'type' => 'textbox', 'left' => 80, 'top' => 218, 'width' => $w - 160,
              'text' => '{nombre_completo}', 'fontSize' => 48, 'fontFamily' => 'Playfair Display',
              'fontWeight' => 'bold', 'fill' => '#2D1B0E', 'textAlign' => 'center' ],

            // ── Separador bajo nombre ────────────────────────────
            [ 'type' => 'rect', 'left' => 311, 'top' => 286, 'width' => 500, 'height' => 1.5,
              'fill' => '#D4AA4A', 'stroke' => null ],

            // ── Motivo y Curso ───────────────────────────────────
            [ 'type' => 'textbox', 'left' => 80, 'top' => 304, 'width' => $w - 160,
              'text' => 'Por haber completado satisfactoriamente el programa académico:',
              'fontSize' => 16, 'fontFamily' => 'Times New Roman',
              'fontStyle' => 'italic', 'fill' => '#7B6040', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 336, 'width' => $w - 160,
              'text' => '{curso}', 'fontSize' => 34, 'fontFamily' => 'Times New Roman',
              'fontWeight' => 'bold', 'fontStyle' => 'italic', 'fill' => '#9A6E08', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 160, 'top' => 390, 'width' => $w - 320,
              'text' => '{programa}  •  Intensidad: {horas}', 'fontSize' => 15, 'fontFamily' => 'Times New Roman',
              'fill' => '#5C4B32', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 180, 'top' => 420, 'width' => $w - 360,
              'text' => '{descripcion}', 'fontSize' => 14, 'fontFamily' => 'Times New Roman',
              'fontStyle' => 'italic', 'fill' => '#7C7259', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 180, 'top' => 468, 'width' => $w - 360,
              'text' => 'Dado en {ciudad}, a los {fecha_emision_larga}', 'fontSize' => 14, 'fontFamily' => 'Times New Roman',
              'fill' => '#6E6248', 'textAlign' => 'center' ],

            // ── Firmante 1 (Izquierda) ───────────────────────────
            [ 'type' => 'rect', 'left' => 165, 'top' => 548, 'width' => 170, 'height' => 60,
              'fill' => 'transparent', 'stroke' => '#D4AA4A', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 130, 'top' => 616, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0,
              'stroke' => '#8B6914', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 120, 'top' => 626, 'width' => 260,
              'text' => "{firmante_1_nombre}\n{firmante_1_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Times New Roman', 'fill' => '#2D1B0E', 'textAlign' => 'center' ],

            // ── QR Central + Folio ───────────────────────────────
            [ 'type' => 'rect', 'left' => 519, 'top' => 560, 'width' => 84, 'height' => 84,
              'fill' => '#FFFDF7', 'stroke' => '#D4AA4A', 'strokeWidth' => 1.5,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 441, 'top' => 652, 'width' => 240,
              'text' => 'Folio: {folio}', 'fontSize' => 12, 'fontFamily' => 'Courier New',
              'fill' => '#8C7A5B', 'textAlign' => 'center' ],

            // ── Firmante 2 (Derecha) ─────────────────────────────
            [ 'type' => 'rect', 'left' => 787, 'top' => 548, 'width' => 170, 'height' => 60,
              'fill' => 'transparent', 'stroke' => '#D4AA4A', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 752, 'top' => 616, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0,
              'stroke' => '#8B6914', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 742, 'top' => 626, 'width' => 260,
              'text' => "{firmante_2_nombre}\n{firmante_2_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Times New Roman', 'fill' => '#2D1B0E', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Diploma Clásico Dorado', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#B8860B',
            'description' => __( 'Marco dorado tradicional con doble firma y QR central', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#FFFDF2',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 2. Credencial & Diploma con Foto (A4 Landscape - NUEVA) ──
    private static function preset_credencial_academica_foto(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // Marcos
            [ 'type' => 'rect', 'left' => 20, 'top' => 20, 'width' => $w - 40, 'height' => $h - 40,
              'fill' => 'transparent', 'stroke' => '#1e3a8a', 'strokeWidth' => 3, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 30, 'top' => 30, 'width' => $w - 60, 'height' => $h - 60,
              'fill' => 'transparent', 'stroke' => '#c9a84c', 'strokeWidth' => 1.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            // Franja decorativa izquierda azul real
            [ 'type' => 'rect', 'left' => 30, 'top' => 30, 'width' => 270, 'height' => $h - 60,
              'fill' => '#0f172a', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 300, 'top' => 30, 'width' => 6, 'height' => $h - 60,
              'fill' => '#c9a84c', 'stroke' => null ],

            // Foto de perfil del estudiante (Circular con borde dorado y sombra)
            [ 'type' => 'rect', 'left' => 95, 'top' => 88, 'width' => 140, 'height' => 140,
              'fill' => '#1e293b', 'stroke' => '#c9a84c', 'strokeWidth' => 4,
              'rx' => 70, 'ry' => 70,
              'name' => 'student_photo_placeholder',
              'auraElementType' => 'student_photo',
              'auraPhotoShape' => 'circle',
              'auraPhotoRadius' => 70,
              'auraPhotoPad' => 4,
              'auraPhotoBorderStyle' => 'double',
              'shadow' => [ 'color' => 'rgba(0,0,0,0.35)', 'blur' => 12, 'offsetX' => 0, 'offsetY' => 4 ] ],

            // Datos bajo la foto en columna lateral
            [ 'type' => 'textbox', 'left' => 48, 'top' => 252, 'width' => 234,
              'text' => "ACREDITACIÓN OFICIAL\n{tipo_destinatario}", 'fontSize' => 12, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#fde68a', 'textAlign' => 'center', 'charSpacing' => 140 ],
            [ 'type' => 'rect', 'left' => 95, 'top' => 302, 'width' => 140, 'height' => 1.5,
              'fill' => '#c9a84c', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 48, 'top' => 320, 'width' => 234,
              'text' => "ID: {documento}\nCalificación: {calificacion}\nIntensidad: {horas}",
              'fontSize' => 12.5, 'fontFamily' => 'Lato', 'fill' => '#cbd5e1', 'textAlign' => 'center', 'lineHeight' => 1.45 ],

            // QR en la columna lateral
            [ 'type' => 'rect', 'left' => 113, 'top' => 560, 'width' => 104, 'height' => 104,
              'fill' => '#ffffff', 'stroke' => '#c9a84c', 'strokeWidth' => 2,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 48, 'top' => 676, 'width' => 234,
              'text' => "FOLIO: {folio}\nCÓD: {codigo_verificacion}",
              'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#94a3b8', 'textAlign' => 'center' ],

            // Cuerpo principal derecho
            [ 'type' => 'textbox', 'left' => 350, 'top' => 66, 'width' => 700,
              'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#1e3a8a', 'textAlign' => 'left', 'charSpacing' => 180 ],
            [ 'type' => 'textbox', 'left' => 350, 'top' => 102, 'width' => 700,
              'text' => 'DIPLOMA DE EXCELENCIA', 'fontSize' => 38, 'fontFamily' => 'Cinzel',
              'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'left', 'charSpacing' => 60 ],
            [ 'type' => 'rect', 'left' => 350, 'top' => 158, 'width' => 180, 'height' => 3,
              'fill' => '#c9a84c', 'stroke' => null ],

            [ 'type' => 'textbox', 'left' => 350, 'top' => 188, 'width' => 700,
              'text' => 'Se confiere la presente acreditación académica y profesional a:',
              'fontSize' => 16, 'fontFamily' => 'Merriweather', 'fontStyle' => 'italic',
              'fill' => '#475569', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 350, 'top' => 226, 'width' => 700,
              'text' => '{nombre_completo}', 'fontSize' => 44, 'fontFamily' => 'Playfair Display',
              'fontWeight' => 'bold', 'fill' => '#1e3a8a', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 350, 'top' => 298, 'width' => 700,
              'text' => 'Por haber cursado y aprobado satisfactoriamente el programa:',
              'fontSize' => 15.5, 'fontFamily' => 'Lato', 'fill' => '#334155', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 350, 'top' => 332, 'width' => 700,
              'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 350, 'top' => 386, 'width' => 680,
              'text' => '{descripcion}', 'fontSize' => 14.5, 'fontFamily' => 'Lato',
              'fontStyle' => 'italic', 'fill' => '#64748b', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 350, 'top' => 452, 'width' => 680,
              'text' => 'Área: {programa}  •  Expedido en {ciudad}, el {fecha_emision_larga}',
              'fontSize' => 13.5, 'fontFamily' => 'Lato', 'fill' => '#475569', 'textAlign' => 'left' ],

            // 2 Firmas alineadas en el panel derecho
            [ 'type' => 'rect', 'left' => 390, 'top' => 560, 'width' => 160, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 360, 'top' => 626, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0,
              'stroke' => '#1e3a8a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 350, 'top' => 636, 'width' => 240,
              'text' => "{firmante_1_nombre}\n{firmante_1_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 740, 'top' => 560, 'width' => 160, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 710, 'top' => 626, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0,
              'stroke' => '#1e3a8a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 700, 'top' => 636, 'width' => 240,
              'text' => "{firmante_2_nombre}\n{firmante_2_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Credencial & Diploma con Foto', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#1e3a8a',
            'description' => __( 'Incluye foto circular del estudiante, QR y doble firma', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#ffffff',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 3. Moderno Minimalista (A4 Landscape) ───────────────────
    private static function preset_moderno_minimalista(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // Barra superior e inferior violeta
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => $w, 'height' => 10, 'fill' => '#7c3aed', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 0, 'top' => $h - 10, 'width' => $w, 'height' => 10, 'fill' => '#7c3aed', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => 10, 'height' => $h, 'fill' => '#7c3aed', 'stroke' => null ],

            // Marco interior sutil (click-through)
            [ 'type' => 'rect', 'left' => 36, 'top' => 32, 'width' => $w - 72, 'height' => $h - 64,
              'fill' => 'transparent', 'stroke' => '#ede9fe', 'strokeWidth' => 2, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            // Organización arriba
            [ 'type' => 'textbox', 'left' => 72, 'top' => 58, 'width' => 700,
              'text' => '{organizacion}', 'fontSize' => 15, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#7c3aed', 'textAlign' => 'left', 'charSpacing' => 180 ],

            // Título
            [ 'type' => 'textbox', 'left' => 72, 'top' => 88, 'width' => 800,
              'text' => 'CERTIFICADO', 'fontSize' => 62, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#111827', 'textAlign' => 'left', 'charSpacing' => 60 ],

            // Línea de acento
            [ 'type' => 'rect', 'left' => 72, 'top' => 168, 'width' => 130, 'height' => 5,
              'fill' => '#7c3aed', 'stroke' => null ],

            [ 'type' => 'textbox', 'left' => 72, 'top' => 196, 'width' => 880,
              'text' => 'Se certifica oficialmente que:', 'fontSize' => 17, 'fontFamily' => 'Lato',
              'fill' => '#6b7280', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 72, 'top' => 228, 'width' => 940,
              'text' => '{nombre_completo}', 'fontSize' => 48, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#6d28d9', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 72, 'top' => 300, 'width' => 880,
              'text' => 'Ha completado exitosamente el programa de formación:',
              'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#6b7280', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 72, 'top' => 332, 'width' => 920,
              'text' => '{curso}', 'fontSize' => 36, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#111827', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 72, 'top' => 388, 'width' => 820,
              'text' => '{programa}  •  Intensidad: {horas}  •  Calificación: {calificacion}',
              'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#4b5563', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 72, 'top' => 424, 'width' => 820,
              'text' => '{descripcion}', 'fontSize' => 14.5, 'fontFamily' => 'Lato',
              'fontStyle' => 'italic', 'fill' => '#6b7280', 'textAlign' => 'left' ],

            // 2 Firmas + QR a la derecha
            [ 'type' => 'rect', 'left' => 102, 'top' => 538, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#ddd6fe', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 72, 'top' => 602, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0,
              'stroke' => '#7c3aed', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 72, 'top' => 612, 'width' => 230,
              'text' => "{firmante_1_nombre}\n{firmante_1_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#374151', 'textAlign' => 'left' ],

            [ 'type' => 'rect', 'left' => 412, 'top' => 538, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#ddd6fe', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 382, 'top' => 602, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0,
              'stroke' => '#7c3aed', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 382, 'top' => 612, 'width' => 230,
              'text' => "{firmante_2_nombre}\n{firmante_2_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#374151', 'textAlign' => 'left' ],

            [ 'type' => 'rect', 'left' => 938, 'top' => 548, 'width' => 96, 'height' => 96,
              'fill' => '#f8fafc', 'stroke' => '#7c3aed', 'strokeWidth' => 1.5,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],

            // Footer limpio y bien separado del borde inferior
            [ 'type' => 'rect', 'left' => 72, 'top' => 686, 'width' => $w - 144, 'height' => 1,
              'fill' => '#e5e7eb', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 72, 'top' => 702, 'width' => 520,
              'text' => 'Expedido en {ciudad} el {fecha_emision_larga}',
              'fontSize' => 13.5, 'fontFamily' => 'Lato', 'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 680, 'top' => 702, 'width' => 368,
              'text' => 'Folio: {folio}  •  Cód: {codigo_verificacion}',
              'fontSize' => 12.5, 'fontFamily' => 'Courier New', 'fill' => '#6b7280', 'textAlign' => 'right' ],
        ];

        return [
            'name'        => __( 'Moderno Minimalista', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#7c3aed',
            'description' => __( 'Tipografía moderna, doble firma y acento violeta', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#ffffff',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 4. Certificado de Participación (A4 Landscape) ──────────
    private static function preset_participacion(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // Marco verde (click-through)
            [ 'type' => 'rect', 'left' => 20, 'top' => 20, 'width' => $w - 40, 'height' => $h - 40,
              'fill' => 'transparent', 'stroke' => '#16a34a', 'strokeWidth' => 4,
              'rx' => 12, 'ry' => 12, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 30, 'top' => 30, 'width' => $w - 60, 'height' => $h - 60,
              'fill' => 'transparent', 'stroke' => '#86efac', 'strokeWidth' => 1.5,
              'rx' => 8, 'ry' => 8, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 54, 'width' => $w - 160,
              'text' => '{organizacion}', 'fontSize' => 15, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#15803d', 'textAlign' => 'center', 'charSpacing' => 180 ],

            // Encabezado
            [ 'type' => 'textbox', 'left' => 61, 'top' => 84, 'width' => 1000,
              'text' => 'CERTIFICADO', 'fontSize' => 56, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#14532d', 'textAlign' => 'center', 'charSpacing' => 160 ],
            [ 'type' => 'textbox', 'left' => 61, 'top' => 152, 'width' => 1000,
              'text' => 'DE PARTICIPACIÓN Y CUMPLIMIENTO', 'fontSize' => 17, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#16a34a', 'textAlign' => 'center', 'charSpacing' => 220 ],

            // Separador
            [ 'type' => 'rect', 'left' => 381, 'top' => 186, 'width' => 360, 'height' => 3,
              'fill' => '#16a34a', 'stroke' => null ],

            [ 'type' => 'textbox', 'left' => 61, 'top' => 206, 'width' => 1000,
              'text' => 'Se otorga el presente certificado a:',
              'fontSize' => 17, 'fontFamily' => 'Lato', 'fill' => '#3f6212', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 61, 'top' => 238, 'width' => 1000,
              'text' => '{nombre_completo}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display',
              'fontWeight' => 'bold', 'fill' => '#14532d', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 61, 'top' => 306, 'width' => 1000,
              'text' => 'Por su activa y destacada participación en:',
              'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#3f6212', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 81, 'top' => 338, 'width' => 960,
              'text' => '{curso}', 'fontSize' => 32, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#15803d', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 141, 'top' => 392, 'width' => 840,
              'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato',
              'fontStyle' => 'italic', 'fill' => '#4b5563', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 141, 'top' => 446, 'width' => 840,
              'text' => 'Intensidad: {horas}  •  Fecha: {fecha_emision_larga}  •  {ciudad}',
              'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#4b7c59', 'textAlign' => 'center' ],

            // 2 Firmantes + QR central
            [ 'type' => 'rect', 'left' => 165, 'top' => 540, 'width' => 170, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#86efac', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 130, 'top' => 606, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0,
              'stroke' => '#16a34a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 120, 'top' => 616, 'width' => 260,
              'text' => "{firmante_1_nombre}\n{firmante_1_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#14532d', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 517, 'top' => 552, 'width' => 88, 'height' => 88,
              'fill' => '#ffffff', 'stroke' => '#16a34a', 'strokeWidth' => 1.5,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 441, 'top' => 648, 'width' => 240,
              'text' => 'Folio: {folio}', 'fontSize' => 12, 'fontFamily' => 'Courier New',
              'fill' => '#15803d', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 787, 'top' => 540, 'width' => 170, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#86efac', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 752, 'top' => 606, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0,
              'stroke' => '#16a34a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 742, 'top' => 616, 'width' => 260,
              'text' => "{firmante_2_nombre}\n{firmante_2_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#14532d', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Certificado de Participación', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#15803d',
            'description' => __( 'Estilo limpio y fresco en tonos esmeralda con doble firma', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#f0fdf4',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 5. Académico Azul Real & Oro (A4 Landscape) ─────────────
    private static function preset_academico_azul_real(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => $w, 'height' => 24, 'fill' => '#1e3a8a', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 0, 'top' => $h - 24, 'width' => $w, 'height' => 24, 'fill' => '#1e3a8a', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 26, 'top' => 38, 'width' => $w - 52, 'height' => $h - 76,
              'fill' => 'transparent', 'stroke' => '#ca8a04', 'strokeWidth' => 2.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 36, 'top' => 48, 'width' => $w - 72, 'height' => $h - 96,
              'fill' => 'transparent', 'stroke' => '#93c5fd', 'strokeWidth' => 1, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            // Medalla superior perfectamente centrada (centro X = 561, diámetro = 56 → left = 533)
            [ 'type' => 'circle', 'left' => 533, 'top' => 64, 'radius' => 28, 'fill' => '#1e3a8a', 'stroke' => '#ca8a04', 'strokeWidth' => 3 ],
            [ 'type' => 'textbox', 'left' => 533, 'top' => 77, 'width' => 56, 'text' => '★', 'fontSize' => 24, 'fontFamily' => 'Georgia', 'fill' => '#fde047', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 132, 'width' => 962, 'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#1e3a8a', 'textAlign' => 'center', 'charSpacing' => 180 ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 166, 'width' => 962, 'text' => 'CERTIFICADO ACADÉMICO', 'fontSize' => 40, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center', 'charSpacing' => 120 ],
            [ 'type' => 'rect', 'left' => 411, 'top' => 224, 'width' => 300, 'height' => 3, 'fill' => '#ca8a04', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 100, 'top' => 246, 'width' => 922, 'text' => 'Se otorga con distinción el presente reconocimiento a:', 'fontSize' => 16, 'fontFamily' => 'Merriweather', 'fontStyle' => 'italic', 'fill' => '#475569', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 282, 'width' => 962, 'text' => '{nombre_completo}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#1e3a8a', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 140, 'top' => 350, 'width' => 842, 'text' => 'Por haber completado y aprobado satisfactoriamente el programa de', 'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#334155', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 380, 'width' => 942, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 130, 'top' => 432, 'width' => 862, 'text' => 'Intensidad horaria: {horas}  •  Calificación: {calificacion}  •  {programa}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 130, 'top' => 462, 'width' => 862, 'text' => 'Expedido en {ciudad}, el {fecha_emision_larga}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],

            // Firmas con slots dinámicos y líneas alineadas
            [ 'type' => 'rect', 'left' => 185, 'top' => 548, 'width' => 170, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#93c5fd', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 150, 'top' => 614, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0, 'stroke' => '#1e3a8a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 140, 'top' => 624, 'width' => 260, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 565, 'top' => 548, 'width' => 170, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#93c5fd', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 530, 'top' => 614, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0, 'stroke' => '#1e3a8a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 520, 'top' => 624, 'width' => 260, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 935, 'top' => 552, 'width' => 90, 'height' => 90, 'fill' => '#f8fafc', 'stroke' => '#cbd5e1', 'strokeWidth' => 1.5, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 905, 'top' => 650, 'width' => 150, 'text' => 'Folio: {folio}', 'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#475569', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Académico Azul Real & Oro', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#1e3a8a',
            'description' => __( 'Ideal para diplomados y programas académicos', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#ffffff',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 6. Ordenación & Liderazgo Ministerial (A4 Landscape - NUEVA) ──
    private static function preset_ministerial_liderazgo(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // Doble marco vino tinto y oro viejo (click-through)
            [ 'type' => 'rect', 'left' => 22, 'top' => 22, 'width' => $w - 44, 'height' => $h - 44,
              'fill' => 'transparent', 'stroke' => '#7f1d1d', 'strokeWidth' => 3.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 34, 'top' => 34, 'width' => $w - 68, 'height' => $h - 68,
              'fill' => 'transparent', 'stroke' => '#b45309', 'strokeWidth' => 1.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            // Cabecera solemne
            [ 'type' => 'textbox', 'left' => 80, 'top' => 58, 'width' => 962,
              'text' => '{organizacion}', 'fontSize' => 17, 'fontFamily' => 'Cinzel',
              'fontWeight' => 'bold', 'fill' => '#7f1d1d', 'textAlign' => 'center', 'charSpacing' => 200 ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 92, 'width' => 962,
              'text' => 'CERTIFICADO MINISTERIAL', 'fontSize' => 42, 'fontFamily' => 'Cinzel',
              'fontWeight' => 'bold', 'fill' => '#1c1917', 'textAlign' => 'center', 'charSpacing' => 100 ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 148, 'width' => 962,
              'text' => 'ESCUELA DE FORMACIÓN Y LIDERAZGO', 'fontSize' => 14, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#b45309', 'textAlign' => 'center', 'charSpacing' => 240 ],

            [ 'type' => 'rect', 'left' => 381, 'top' => 178, 'width' => 360, 'height' => 2,
              'fill' => '#b45309', 'stroke' => null ],

            [ 'type' => 'textbox', 'left' => 100, 'top' => 198, 'width' => 922,
              'text' => 'Con gratitud y reconocimiento espiritual, se extiende el presente certificado a:',
              'fontSize' => 16, 'fontFamily' => 'Georgia', 'fontStyle' => 'italic',
              'fill' => '#57534e', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 232, 'width' => 962,
              'text' => '{nombre_completo}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display',
              'fontWeight' => 'bold', 'fill' => '#7f1d1d', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 120, 'top' => 298, 'width' => 882,
              'text' => 'Por su fidelidad, dedicación y culminación satisfactoria de:',
              'fontSize' => 15.5, 'fontFamily' => 'Lato', 'fill' => '#44403c', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 330, 'width' => 962,
              'text' => '{curso}', 'fontSize' => 31, 'fontFamily' => 'Playfair Display',
              'fontWeight' => 'bold', 'fill' => '#1c1917', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 140, 'top' => 382, 'width' => 842,
              'text' => '{descripcion}', 'fontSize' => 14.5, 'fontFamily' => 'Georgia',
              'fontStyle' => 'italic', 'fill' => '#57534e', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 140, 'top' => 436, 'width' => 842,
              'text' => '{programa}  •  Intensidad: {horas}  •  Expedido en {ciudad}, {fecha_emision_larga}',
              'fontSize' => 13.5, 'fontFamily' => 'Lato', 'fill' => '#78716c', 'textAlign' => 'center' ],

            // 3 bloques de firma + QR a la derecha
            [ 'type' => 'rect', 'left' => 95,  'top' => 538, 'width' => 150, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#d6d3d1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 65,  'top' => 602, 'x1' => 0, 'y1' => 0, 'x2' => 210, 'y2' => 0, 'stroke' => '#7f1d1d', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 55,  'top' => 612, 'width' => 230,
              'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 12.5, 'fontFamily' => 'Lato', 'fill' => '#292524', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 375, 'top' => 538, 'width' => 150, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#d6d3d1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 345, 'top' => 602, 'x1' => 0, 'y1' => 0, 'x2' => 210, 'y2' => 0, 'stroke' => '#7f1d1d', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 335, 'top' => 612, 'width' => 230,
              'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 12.5, 'fontFamily' => 'Lato', 'fill' => '#292524', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 655, 'top' => 538, 'width' => 150, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#d6d3d1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_3', 'auraElementType' => 'signer_sig', 'signerSlot' => 3 ],
            [ 'type' => 'line', 'left' => 625, 'top' => 602, 'x1' => 0, 'y1' => 0, 'x2' => 210, 'y2' => 0, 'stroke' => '#7f1d1d', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 615, 'top' => 612, 'width' => 230,
              'text' => "{firmante_3_nombre}\n{firmante_3_cargo}", 'fontSize' => 12.5, 'fontFamily' => 'Lato', 'fill' => '#292524', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 940, 'top' => 545, 'width' => 92, 'height' => 92,
              'fill' => '#ffffff', 'stroke' => '#b45309', 'strokeWidth' => 1.5,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 906, 'top' => 646, 'width' => 160,
              'text' => 'Folio: {folio}', 'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#78716c', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Liderazgo Ministerial (3 Firmas)', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#7f1d1d',
            'description' => __( 'Diseño solemne en vino tinto y oro con 3 firmas y QR', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#fffdf7',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 7. Corporativo Oscuro & Turquesa VIP (A4 Landscape) ─────
    private static function preset_corporativo_oscuro_vip(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            [ 'type' => 'rect', 'left' => 28, 'top' => 28, 'width' => $w - 56, 'height' => $h - 56,
              'fill' => 'transparent', 'stroke' => '#2dd4bf', 'strokeWidth' => 2, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 40, 'top' => 40, 'width' => 14, 'height' => $h - 80, 'fill' => '#14b8a6', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 68, 'width' => 700, 'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#5eead4', 'textAlign' => 'left', 'charSpacing' => 200 ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 104, 'width' => 820, 'text' => 'CERTIFICACIÓN EJECUTIVA', 'fontSize' => 40, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#f8fafc', 'textAlign' => 'left' ],
            [ 'type' => 'rect', 'left' => 90, 'top' => 162, 'width' => 140, 'height' => 4, 'fill' => '#14b8a6', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 202, 'width' => 880, 'text' => 'OTORGADO OFICIALMENTE A', 'fontSize' => 13, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#94a3b8', 'textAlign' => 'left', 'charSpacing' => 220 ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 232, 'width' => 940, 'text' => '{nombre_completo}', 'fontSize' => 48, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 302, 'width' => 880, 'text' => 'ID: {documento}  •  Intensidad: {horas}  •  Calificación: {calificacion}', 'fontSize' => 14, 'fontFamily' => 'Roboto', 'fill' => '#94a3b8', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 348, 'width' => 880, 'text' => 'Por acreditar las competencias del programa:', 'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#cbd5e1', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 380, 'width' => 900, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#2dd4bf', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 432, 'width' => 760, 'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#94a3b8', 'textAlign' => 'left' ],

            // 2 Firmas con slots dinámicos + metadatos y QR
            [ 'type' => 'rect', 'left' => 115, 'top' => 544, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#334155', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 90, 'top' => 608, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#2dd4bf', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 618, 'width' => 230, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#e2e8f0', 'textAlign' => 'left' ],

            [ 'type' => 'rect', 'left' => 405, 'top' => 544, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#334155', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 380, 'top' => 608, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#2dd4bf', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 380, 'top' => 618, 'width' => 230, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#e2e8f0', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 650, 'top' => 612, 'width' => 250, 'text' => "Expedido: {fecha_emision}\nCiudad: {ciudad}\nFolio: {folio}", 'fontSize' => 12.5, 'fontFamily' => 'Lato', 'fill' => '#94a3b8', 'textAlign' => 'left', 'lineHeight' => 1.4 ],
            [ 'type' => 'rect', 'left' => 940, 'top' => 560, 'width' => 96, 'height' => 96, 'fill' => '#ffffff', 'stroke' => '#2dd4bf', 'strokeWidth' => 2, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 918, 'top' => 664, 'width' => 140, 'text' => 'VERIFICAR QR', 'fontSize' => 11, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#5eead4', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Corporativo Oscuro & Turquesa', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#0f172a',
            'description' => __( 'Elegante fondo oscuro con acentos turquesa VIP y doble firma', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#0f172a',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 8. Diploma Solemne Vertical (A4 Portrait) ───────────────
    private static function preset_diploma_solemne_vertical(): array {
        $w = 794;
        $h = 1122;

        $objects = [
            [ 'type' => 'rect', 'left' => 28, 'top' => 28, 'width' => $w - 56, 'height' => $h - 56,
              'fill' => 'transparent', 'stroke' => '#7c2d12', 'strokeWidth' => 3, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 40, 'top' => 40, 'width' => $w - 80, 'height' => $h - 80,
              'fill' => 'transparent', 'stroke' => '#d97706', 'strokeWidth' => 1.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            [ 'type' => 'textbox', 'left' => 70, 'top' => 84, 'width' => $w - 140, 'text' => '{organizacion}', 'fontSize' => 18, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#7c2d12', 'textAlign' => 'center', 'charSpacing' => 140 ],
            [ 'type' => 'textbox', 'left' => 70, 'top' => 138, 'width' => $w - 140, 'text' => 'DIPLOMA', 'fontSize' => 54, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#1c1917', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 70, 'top' => 206, 'width' => $w - 140, 'text' => 'DE HONOR Y MÉRITO', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fill' => '#b45309', 'textAlign' => 'center', 'charSpacing' => 260 ],
            [ 'type' => 'rect', 'left' => 267, 'top' => 250, 'width' => 260, 'height' => 2, 'fill' => '#d97706', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 296, 'width' => $w - 160, 'text' => 'Por la presente se confiere el presente diploma a:', 'fontSize' => 17, 'fontFamily' => 'Georgia', 'fontStyle' => 'italic', 'fill' => '#57534e', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 350, 'width' => $w - 120, 'text' => '{nombre_completo}', 'fontSize' => 46, 'fontFamily' => 'Great Vibes', 'fill' => '#7c2d12', 'textAlign' => 'center' ],
            [ 'type' => 'rect', 'left' => 197, 'top' => 424, 'width' => 400, 'height' => 1.5, 'fill' => '#d6d3d1', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 464, 'width' => $w - 160, 'text' => 'Por haber concluido con excelencia académica:', 'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#44403c', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 70, 'top' => 506, 'width' => $w - 140, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#1c1917', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 580, 'width' => $w - 180, 'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#57534e', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 664, 'width' => $w - 180, 'text' => 'Duración: {horas}  •  Ciudad: {ciudad}  •  Fecha: {fecha_emision_larga}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#78716c', 'textAlign' => 'center' ],

            // 2 Firmas con slots dinámicos
            [ 'type' => 'rect', 'left' => 140, 'top' => 776, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#d6d3d1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 110, 'top' => 840, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#44403c', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 100, 'top' => 850, 'width' => 240, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#292524', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 494, 'top' => 776, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#d6d3d1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 464, 'top' => 840, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#44403c', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 454, 'top' => 850, 'width' => 240, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#292524', 'textAlign' => 'center' ],

            // QR y Folio holgados dentro del marco inferior
            [ 'type' => 'rect', 'left' => 353, 'top' => 926, 'width' => 88, 'height' => 88, 'fill' => '#ffffff', 'stroke' => '#d97706', 'strokeWidth' => 1.5, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 247, 'top' => 1024, 'width' => 300, 'text' => 'Folio: {folio}  •  Cód: {codigo_verificacion}', 'fontSize' => 11.5, 'fontFamily' => 'Courier New', 'fill' => '#78716c', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Diploma Solemne Vertical (A4)', 'aura-suite' ),
            'orientation' => 'portrait',
            'accent'      => '#7c2d12',
            'description' => __( 'Formato vertical clásico listo para enmarcar', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#fffbeb',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 9. Diploma Ejecutivo con Foto Vertical (A4 Portrait - NUEVA) ──
    private static function preset_ejecutivo_foto_vertical(): array {
        $w = 794;
        $h = 1122;

        $objects = [
            // Cabecera azul oscuro / esmeralda
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => $w, 'height' => 180, 'fill' => '#0f172a', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 0, 'top' => 180, 'width' => $w, 'height' => 6, 'fill' => '#c9a84c', 'stroke' => null ],

            // Marco interior (click-through)
            [ 'type' => 'rect', 'left' => 32, 'top' => 206, 'width' => $w - 64, 'height' => $h - 238,
              'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            [ 'type' => 'textbox', 'left' => 60, 'top' => 42, 'width' => $w - 120,
              'text' => '{organizacion}', 'fontSize' => 15, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#fde68a', 'textAlign' => 'center', 'charSpacing' => 200 ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 76, 'width' => $w - 120,
              'text' => 'ACREDITACIÓN PROFESIONAL', 'fontSize' => 32, 'fontFamily' => 'Cinzel',
              'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'center', 'charSpacing' => 80 ],

            // Foto del estudiante (Cuadrada con bordes redondeados superpuesta al banner)
            [ 'type' => 'rect', 'left' => 332, 'top' => 132, 'width' => 130, 'height' => 130,
              'fill' => '#ffffff', 'stroke' => '#c9a84c', 'strokeWidth' => 4,
              'rx' => 22, 'ry' => 22,
              'name' => 'student_photo_placeholder',
              'auraElementType' => 'student_photo',
              'auraPhotoShape' => 'rounded',
              'auraPhotoRadius' => 22,
              'auraPhotoPad' => 4,
              'auraPhotoBorderStyle' => 'solid',
              'shadow' => [ 'color' => 'rgba(15,23,42,0.28)', 'blur' => 12, 'offsetX' => 0, 'offsetY' => 4 ] ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 286, 'width' => $w - 160,
              'text' => 'Se certifica que el titular:', 'fontSize' => 16, 'fontFamily' => 'Lato',
              'fill' => '#64748b', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 60, 'top' => 320, 'width' => $w - 120,
              'text' => '{nombre_completo}', 'fontSize' => 42, 'fontFamily' => 'Playfair Display',
              'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 100, 'top' => 380, 'width' => $w - 200,
              'text' => 'Documento: {documento}', 'fontSize' => 14, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#b45309', 'textAlign' => 'center', 'charSpacing' => 100 ],

            [ 'type' => 'rect', 'left' => 277, 'top' => 414, 'width' => 240, 'height' => 2, 'fill' => '#e2e8f0', 'stroke' => null ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 442, 'width' => $w - 160,
              'text' => 'Ha aprobado satisfactoriamente todos los créditos y evaluaciones de:',
              'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#334155', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 70, 'top' => 480, 'width' => $w - 140,
              'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 90, 'top' => 552, 'width' => $w - 180,
              'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato',
              'fontStyle' => 'italic', 'fill' => '#475569', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 636, 'width' => $w - 160,
              'text' => 'Programa: {programa}  •  Horas: {horas}  •  Calificación: {calificacion}',
              'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 668, 'width' => $w - 160,
              'text' => 'Expedido en {ciudad}, el {fecha_emision_larga}',
              'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],

            // 2 Firmas + QR inferior
            [ 'type' => 'rect', 'left' => 135, 'top' => 768, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 105, 'top' => 832, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#0f172a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 95, 'top' => 842, 'width' => 240,
              'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 499, 'top' => 768, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 469, 'top' => 832, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#0f172a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 459, 'top' => 842, 'width' => 240,
              'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 353, 'top' => 924, 'width' => 88, 'height' => 88,
              'fill' => '#f8fafc', 'stroke' => '#c9a84c', 'strokeWidth' => 1.5,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 247, 'top' => 1022, 'width' => 300,
              'text' => 'Folio: {folio}  •  Verificación: {codigo_verificacion}',
              'fontSize' => 11.5, 'fontFamily' => 'Courier New', 'fill' => '#64748b', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Ejecutivo con Foto Vertical (A4)', 'aura-suite' ),
            'orientation' => 'portrait',
            'accent'      => '#0f172a',
            'description' => __( 'Vertical moderno con foto redondeada del estudiante', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#ffffff',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 10. Reconocimiento Honorífico (Carta Horizontal) ─────────
    private static function preset_reconocimiento_honorifico(): array {
        $w = 1056;
        $h = 816;

        $objects = [
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => 190, 'height' => $h, 'fill' => '#581c87', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 190, 'top' => 0, 'width' => 10, 'height' => $h, 'fill' => '#f59e0b', 'stroke' => null ],
            // Sello dorado centrado en barra de 190px (radio 42 → ancho 84 → left = (190-84)/2 = 53)
            [ 'type' => 'circle', 'left' => 53, 'top' => 68, 'radius' => 42, 'fill' => '#f59e0b', 'stroke' => '#fef3c7', 'strokeWidth' => 3 ],
            [ 'type' => 'textbox', 'left' => 53, 'top' => 92, 'width' => 84, 'text' => '★', 'fontSize' => 32, 'fontFamily' => 'Georgia', 'fill' => '#581c87', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 20, 'top' => 176, 'width' => 150, 'text' => "MENCIÓN\nDE HONOR\n{anio_actual}", 'fontSize' => 15, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#fef3c7', 'textAlign' => 'center', 'lineHeight' => 1.35 ],
            [ 'type' => 'rect', 'left' => 45, 'top' => 596, 'width' => 100, 'height' => 100, 'fill' => '#ffffff', 'stroke' => '#f59e0b', 'strokeWidth' => 2, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 20, 'top' => 708, 'width' => 150, 'text' => 'Folio: {folio}', 'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#e9d5ff', 'textAlign' => 'center' ],

            // Cuerpo principal
            [ 'type' => 'textbox', 'left' => 255, 'top' => 76, 'width' => 730, 'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#6b21a8', 'textAlign' => 'left', 'charSpacing' => 150 ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 114, 'width' => 730, 'text' => 'RECONOCIMIENTO ESPECIAL', 'fontSize' => 38, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#1e1b4b', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 186, 'width' => 730, 'text' => 'Concedido con gratitud y distinción a:', 'fontSize' => 17, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 228, 'width' => 740, 'text' => '{nombre_completo}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#581c87', 'textAlign' => 'left' ],
            [ 'type' => 'rect', 'left' => 255, 'top' => 296, 'width' => 480, 'height' => 2, 'fill' => '#e2e8f0', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 326, 'width' => 730, 'text' => '{curso}', 'fontSize' => 28, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 382, 'width' => 730, 'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#475569', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 456, 'width' => 730, 'text' => 'Categoría: {categoria}  •  Otorgado en {ciudad}, el {fecha_emision_larga}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'left' ],

            // 2 Firmas con slots dinámicos
            [ 'type' => 'rect', 'left' => 285, 'top' => 580, 'width' => 165, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#e9d5ff', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 255, 'top' => 646, 'x1' => 0, 'y1' => 0, 'x2' => 230, 'y2' => 0, 'stroke' => '#581c87', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 656, 'width' => 240, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'left' ],

            [ 'type' => 'rect', 'left' => 630, 'top' => 580, 'width' => 165, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#e9d5ff', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 600, 'top' => 646, 'x1' => 0, 'y1' => 0, 'x2' => 230, 'y2' => 0, 'stroke' => '#581c87', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 600, 'top' => 656, 'width' => 240, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'left' ],
        ];

        return [
            'name'        => __( 'Reconocimiento Honorífico (Carta)', 'aura-suite' ),
            'orientation' => 'letter_landscape',
            'accent'      => '#581c87',
            'description' => __( 'Banda lateral púrpura y dorado en formato Carta', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#ffffff',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 11. Gala de Excelencia con Foto (Carta Horizontal - NUEVA) ──
    private static function preset_gala_excelencia_carta(): array {
        $w = 1056;
        $h = 816;

        $objects = [
            // Marco dorado imperial sobre fondo negro carbón (click-through)
            [ 'type' => 'rect', 'left' => 24, 'top' => 24, 'width' => $w - 48, 'height' => $h - 48,
              'fill' => 'transparent', 'stroke' => '#d4af37', 'strokeWidth' => 3, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 36, 'top' => 36, 'width' => $w - 72, 'height' => $h - 72,
              'fill' => 'transparent', 'stroke' => '#785c12', 'strokeWidth' => 1.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            // Foto de perfil del estudiante a la derecha superior
            [ 'type' => 'rect', 'left' => 846, 'top' => 76, 'width' => 130, 'height' => 130,
              'fill' => '#1e293b', 'stroke' => '#d4af37', 'strokeWidth' => 3.5,
              'rx' => 65, 'ry' => 65,
              'name' => 'student_photo_placeholder',
              'auraElementType' => 'student_photo',
              'auraPhotoShape' => 'circle',
              'auraPhotoRadius' => 65,
              'auraPhotoPad' => 3,
              'auraPhotoBorderStyle' => 'double',
              'shadow' => [ 'color' => 'rgba(0,0,0,0.45)', 'blur' => 12, 'offsetX' => 0, 'offsetY' => 4 ] ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 74, 'width' => 720,
              'text' => '{organizacion}', 'fontSize' => 15, 'fontFamily' => 'Cinzel',
              'fontWeight' => 'bold', 'fill' => '#d4af37', 'textAlign' => 'left', 'charSpacing' => 220 ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 110, 'width' => 720,
              'text' => 'GALARDÓN A LA EXCELENCIA', 'fontSize' => 38, 'fontFamily' => 'Cinzel',
              'fontWeight' => 'bold', 'fill' => '#fef9c3', 'textAlign' => 'left', 'charSpacing' => 60 ],

            [ 'type' => 'rect', 'left' => 80, 'top' => 168, 'width' => 220, 'height' => 2.5,
              'fill' => '#d4af37', 'stroke' => null ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 204, 'width' => 740,
              'text' => 'Se confiere este reconocimiento de honor a:', 'fontSize' => 16, 'fontFamily' => 'Merriweather',
              'fontStyle' => 'italic', 'fill' => '#cbd5e1', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 242, 'width' => 880,
              'text' => '{nombre_completo}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display',
              'fontWeight' => 'bold', 'fill' => '#fde047', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 314, 'width' => 880,
              'text' => 'Por su sobresaliente desempeño y mérito en:', 'fontSize' => 16, 'fontFamily' => 'Lato',
              'fill' => '#cbd5e1', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 348, 'width' => 880,
              'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 404, 'width' => 840,
              'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato',
              'fontStyle' => 'italic', 'fill' => '#94a3b8', 'textAlign' => 'left' ],

            [ 'type' => 'textbox', 'left' => 80, 'top' => 466, 'width' => 840,
              'text' => 'Programa: {programa}  •  Mención: {calificacion}  •  {ciudad}, {fecha_emision_larga}',
              'fontSize' => 13.5, 'fontFamily' => 'Lato', 'fill' => '#d4af37', 'textAlign' => 'left' ],

            // 2 Firmas + QR
            [ 'type' => 'rect', 'left' => 110, 'top' => 572, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#785c12', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 80, 'top' => 636, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#d4af37', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 646, 'width' => 230,
              'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#fef9c3', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 430, 'top' => 572, 'width' => 160, 'height' => 56,
              'fill' => 'transparent', 'stroke' => '#785c12', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 400, 'top' => 636, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#d4af37', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 400, 'top' => 646, 'width' => 230,
              'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#fef9c3', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 866, 'top' => 580, 'width' => 96, 'height' => 96,
              'fill' => '#ffffff', 'stroke' => '#d4af37', 'strokeWidth' => 2,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 824, 'top' => 686, 'width' => 180,
              'text' => 'Folio: {folio}', 'fontSize' => 11.5, 'fontFamily' => 'Courier New', 'fill' => '#d4af37', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Gala de Excelencia con Foto (Carta)', 'aura-suite' ),
            'orientation' => 'letter_landscape',
            'accent'      => '#b45309',
            'description' => __( 'Edición de lujo en negro y oro con foto en formato Carta', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#111827',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 12. Constancia de Taller / Seminario (A4 Landscape) ─────
    private static function preset_constancia_taller(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // Marco tarjeta redondeado (click-through)
            [ 'type' => 'rect', 'left' => 36, 'top' => 36, 'width' => $w - 72, 'height' => $h - 72,
              'fill' => 'transparent', 'stroke' => '#0284c7', 'strokeWidth' => 2.5,
              'rx' => 16, 'ry' => 16, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],

            // Cabecera interna con bordes redondeados (sin sobresalir del marco)
            [ 'type' => 'rect', 'left' => 52, 'top' => 52, 'width' => $w - 104, 'height' => 104,
              'fill' => '#0284c7', 'stroke' => null, 'rx' => 12, 'ry' => 12 ],

            [ 'type' => 'textbox', 'left' => 84, 'top' => 72, 'width' => 650, 'text' => 'CONSTANCIA DE PARTICIPACIÓN', 'fontSize' => 28, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 84, 'top' => 110, 'width' => 650, 'text' => '{organizacion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#e0f2fe', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 770, 'top' => 88, 'width' => 260, 'text' => 'FOLIO: {folio}', 'fontSize' => 14, 'fontFamily' => 'Courier New', 'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'right' ],

            [ 'type' => 'textbox', 'left' => 86, 'top' => 196, 'width' => 950, 'text' => 'Se extiende la presente constancia a:', 'fontSize' => 17, 'fontFamily' => 'Lato', 'fill' => '#475569', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 86, 'top' => 236, 'width' => 950, 'text' => '{nombre_completo}', 'fontSize' => 44, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0369a1', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 120, 'top' => 308, 'width' => 882, 'text' => 'Por su destacada participación en el taller / seminario:', 'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#334155', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 344, 'width' => 942, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 140, 'top' => 400, 'width' => 842, 'text' => '{descripcion}', 'fontSize' => 14.5, 'fontFamily' => 'Lato', 'fontStyle' => 'italic', 'fill' => '#64748b', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 140, 'top' => 448, 'width' => 842, 'text' => 'Duración: {horas}  •  Fecha: {fecha_emision_larga}  •  {ciudad}', 'fontSize' => 14.5, 'fontFamily' => 'Lato', 'fill' => '#475569', 'textAlign' => 'center' ],

            // 2 Firmas con slots dinámicos + QR
            [ 'type' => 'rect', 'left' => 205, 'top' => 548, 'width' => 170, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#bae6fd', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 170, 'top' => 614, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0, 'stroke' => '#0284c7', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 160, 'top' => 624, 'width' => 260, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 555, 'top' => 548, 'width' => 170, 'height' => 58,
              'fill' => 'transparent', 'stroke' => '#bae6fd', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4],
              'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 520, 'top' => 614, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0, 'stroke' => '#0284c7', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 510, 'top' => 624, 'width' => 260, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 920, 'top' => 556, 'width' => 96, 'height' => 96, 'fill' => '#f8fafc', 'stroke' => '#0284c7', 'strokeWidth' => 1.5, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 898, 'top' => 660, 'width' => 140, 'text' => 'Cód: {codigo_verificacion}', 'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#64748b', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Constancia de Taller / Seminario', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#0284c7',
            'description' => __( 'Tarjeta moderna azul cielo para eventos y talleres', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#ffffff',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── 13. Institucional 4 Firmas (A4 Landscape) ───────────────
    private static function preset_institucional_4_firmas(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            [ 'type' => 'rect', 'left' => 24, 'top' => 24, 'width' => $w - 48, 'height' => $h - 48,
              'fill' => 'transparent', 'stroke' => '#1e293b', 'strokeWidth' => 3, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'rect', 'left' => 34, 'top' => 34, 'width' => $w - 68, 'height' => $h - 68,
              'fill' => 'transparent', 'stroke' => '#b45309', 'strokeWidth' => 1.5, 'perPixelTargetFind' => true, 'targetFindTolerance' => 8 ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 64, 'width' => 962, 'text' => '{organizacion}', 'fontSize' => 17, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#1e293b', 'textAlign' => 'center', 'charSpacing' => 160 ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 104, 'width' => 962, 'text' => 'DIPLOMA DE GRADUACIÓN', 'fontSize' => 42, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center', 'charSpacing' => 100 ],
            [ 'type' => 'rect', 'left' => 411, 'top' => 162, 'width' => 300, 'height' => 2.5, 'fill' => '#b45309', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 100, 'top' => 188, 'width' => 922, 'text' => 'Se confiere el presente título y diploma oficial a:', 'fontSize' => 16, 'fontFamily' => 'Merriweather', 'fontStyle' => 'italic', 'fill' => '#475569', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 228, 'width' => 962, 'text' => '{nombre_completo}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 120, 'top' => 294, 'width' => 882, 'text' => 'Por haber completado y aprobado todos los requisitos del programa:', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#334155', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 328, 'width' => 962, 'text' => '{curso}', 'fontSize' => 29, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#92400e', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 120, 'top' => 376, 'width' => 882, 'text' => 'Programa: {programa}  •  Intensidad: {horas}  •  Expedido en {ciudad}, {fecha_emision_larga}', 'fontSize' => 13.5, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],
            // 4 bloques de firma alineados
            [ 'type' => 'rect', 'left' => 92,  'top' => 480, 'width' => 140, 'height' => 54, 'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4], 'name' => 'signer_slot_1', 'auraElementType' => 'signer_sig', 'signerSlot' => 1 ],
            [ 'type' => 'line', 'left' => 62,  'top' => 542, 'x1' => 0, 'y1' => 0, 'x2' => 200, 'y2' => 0, 'stroke' => '#1e293b', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 52,  'top' => 550, 'width' => 220, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 12, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 342, 'top' => 480, 'width' => 140, 'height' => 54, 'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4], 'name' => 'signer_slot_2', 'auraElementType' => 'signer_sig', 'signerSlot' => 2 ],
            [ 'type' => 'line', 'left' => 312, 'top' => 542, 'x1' => 0, 'y1' => 0, 'x2' => 200, 'y2' => 0, 'stroke' => '#1e293b', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 302, 'top' => 550, 'width' => 220, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 12, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 592, 'top' => 480, 'width' => 140, 'height' => 54, 'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4], 'name' => 'signer_slot_3', 'auraElementType' => 'signer_sig', 'signerSlot' => 3 ],
            [ 'type' => 'line', 'left' => 562, 'top' => 542, 'x1' => 0, 'y1' => 0, 'x2' => 200, 'y2' => 0, 'stroke' => '#1e293b', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 552, 'top' => 550, 'width' => 220, 'text' => "{firmante_3_nombre}\n{firmante_3_cargo}", 'fontSize' => 12, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            [ 'type' => 'rect', 'left' => 842, 'top' => 480, 'width' => 140, 'height' => 54, 'fill' => 'transparent', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'strokeDashArray' => [4, 4], 'name' => 'signer_slot_4', 'auraElementType' => 'signer_sig', 'signerSlot' => 4 ],
            [ 'type' => 'line', 'left' => 812, 'top' => 542, 'x1' => 0, 'y1' => 0, 'x2' => 200, 'y2' => 0, 'stroke' => '#1e293b', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 802, 'top' => 550, 'width' => 220, 'text' => "{firmante_4_nombre}\n{firmante_4_cargo}", 'fontSize' => 12, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],

            // Pie + QR
            [ 'type' => 'rect', 'left' => 62, 'top' => 650, 'width' => 84, 'height' => 84, 'fill' => '#ffffff', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 162, 'top' => 682, 'width' => 500, 'text' => 'Folio: {folio}  •  Verificación: {codigo_verificacion}', 'fontSize' => 12, 'fontFamily' => 'Courier New', 'fill' => '#475569', 'textAlign' => 'left' ],
        ];

        return [
            'name'        => __( 'Institucional (4 Firmas)', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#92400e',
            'description' => __( 'Cuatro espacios de firma + slots dinámicos y código QR', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#fffef8',
                'objects'    => $objects,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // SOPORTE SVG SEGURO Y SUBIDA DESDE EL EDITOR
    // ─────────────────────────────────────────────────────────────

    public static function allow_svg_mime( array $mimes ): array {
        if (
            current_user_can( 'upload_files' ) ||
            current_user_can( 'aura_cert_template_edit' ) ||
            current_user_can( 'manage_options' )
        ) {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }

    public static function fix_svg_filetype( array $data, string $file, string $filename, ?array $mimes, $real_mime = '' ): array {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( $ext === 'svg' ) {
            $data['ext']  = 'svg';
            $data['type'] = 'image/svg+xml';
        }
        return $data;
    }

    public static function sanitize_svg_markup( string $svg ): string {
        $svg = trim( $svg );
        if ( $svg === '' || stripos( $svg, '<svg' ) === false ) {
            return '';
        }

        // Extraer desde <svg hasta </svg> eliminando prólogos XML / DOCTYPE inseguros
        if ( preg_match( '/<svg\b[^>]*>.*<\/svg>/is', $svg, $m ) ) {
            $svg = $m[0];
        } else {
            return '';
        }

        // Eliminar etiquetas ejecutables o externas peligrosas
        $svg = preg_replace( '#<(script|foreignObject|iframe|object|embed|link|meta|applet)\b[^>]*>.*?</\1>#is', '', $svg );
        $svg = preg_replace( '#<(script|foreignObject|iframe|object|embed|link|meta|applet)\b[^>]*/?>#is', '', $svg );

        // Eliminar atributos de eventos on* y URLs javascript:/data:text/html
        $svg = preg_replace( '/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg );
        $svg = preg_replace( '/\s+(?:xlink:)?href\s*=\s*(?:"\s*(?:javascript:|data:text)[^"]*"|\'\s*(?:javascript:|data:text)[^\']*\')/i', '', $svg );

        // Asegurar namespace xmlns
        if ( stripos( substr( $svg, 0, 220 ), 'xmlns=' ) === false ) {
            $svg = preg_replace( '/^<svg\b/i', '<svg xmlns="http://www.w3.org/2000/svg"', $svg, 1 );
        }

        return $svg;
    }

    public static function sanitize_svg_upload( array $file ): array {
        $name = (string) ( $file['name'] ?? '' );
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( $ext !== 'svg' ) {
            return $file;
        }
        if ( empty( $file['tmp_name'] ) || ! file_exists( $file['tmp_name'] ) ) {
            return $file;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw   = (string) file_get_contents( $file['tmp_name'] );
        $clean = self::sanitize_svg_markup( $raw );
        if ( $clean === '' ) {
            $file['error'] = __( 'El archivo SVG no es válido o no contiene un elemento raíz <svg>.', 'aura-suite' );
            return $file;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $file['tmp_name'], $clean );
        $file['type'] = 'image/svg+xml';
        return $file;
    }

    public static function ajax_upload_media(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_cert_template_edit' ) &&
            ! current_user_can( 'aura_cert_template_create' ) &&
            ! current_user_can( 'upload_files' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para subir archivos.', 'aura-suite' ) ], 403 );
        }

        if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No se recibió ningún archivo.', 'aura-suite' ) ], 400 );
        }

        $filename = sanitize_file_name( $_FILES['file']['name'] ?? '' );
        $ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $allowed  = [ 'svg', 'png', 'jpg', 'jpeg', 'webp' ];

        if ( ! in_array( $ext, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato no permitido. Sube archivos SVG, PNG, JPG o WEBP.', 'aura-suite' ) ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $svg_markup = '';
        if ( $ext === 'svg' && ! empty( $_FILES['file']['tmp_name'] ) && file_exists( $_FILES['file']['tmp_name'] ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $svg_markup = self::sanitize_svg_markup( (string) file_get_contents( $_FILES['file']['tmp_name'] ) );
            if ( $svg_markup === '' ) {
                wp_send_json_error( [ 'message' => __( 'Archivo SVG vacío o inválido.', 'aura-suite' ) ], 400 );
            }
        }

        $attach_id = media_handle_upload( 'file', 0 );
        if ( is_wp_error( $attach_id ) ) {
            wp_send_json_error( [ 'message' => $attach_id->get_error_message() ], 400 );
        }

        $url = wp_get_attachment_url( $attach_id );
        wp_send_json_success( [
            'id'         => (int) $attach_id,
            'url'        => (string) $url,
            'name'       => $filename,
            'is_svg'     => ( $ext === 'svg' ),
            'svg_markup' => $svg_markup,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // BIBLIOTECA DE CLIPARTS SVG EDITABLES EN COLOR
    // ─────────────────────────────────────────────────────────────

    /**
     * Cliparts vectoriales SVG integrados con colores hexadecimales limpios
     * listos para edición de color en tiempo real desde el editor.
     */
    public static function get_clipart_library(): array {
        return [
            [
                'id'       => 'medal-gold',
                'name'     => __( 'Medalla de Honor', 'aura-suite' ),
                'category' => __( 'Sellos & Medallas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><polygon points="36,68 24,114 48,102 60,116 60,68" fill="#1e3a8a"/><polygon points="84,68 96,114 72,102 60,116 60,68" fill="#1d4ed8"/><circle cx="60" cy="46" r="34" fill="#d97706"/><circle cx="60" cy="46" r="28" fill="#f59e0b"/><circle cx="60" cy="46" r="22" fill="#fef3c7" stroke="#b45309" stroke-width="2"/><polygon points="60,29 65,40 77,41 68,49 71,61 60,55 49,61 52,49 43,41 55,40" fill="#b45309"/></svg>',
            ],
            [
                'id'       => 'laurel-wreath',
                'name'     => __( 'Corona de Laurel', 'aura-suite' ),
                'category' => __( 'Sellos & Medallas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M36,96 C16,78 16,42 38,22 C36,42 36,68 48,90 Z" fill="#b45309"/><path d="M84,96 C104,78 104,42 82,22 C84,42 84,68 72,90 Z" fill="#b45309"/><circle cx="24" cy="64" r="6" fill="#d97706"/><circle cx="23" cy="46" r="6" fill="#d97706"/><circle cx="30" cy="30" r="5" fill="#d97706"/><circle cx="96" cy="64" r="6" fill="#d97706"/><circle cx="97" cy="46" r="6" fill="#d97706"/><circle cx="90" cy="30" r="5" fill="#d97706"/><polygon points="60,18 65,29 77,30 68,38 71,50 60,44 49,50 52,38 43,30 55,29" fill="#f59e0b"/><rect x="44" y="92" width="32" height="10" rx="5" fill="#1e293b"/></svg>',
            ],
            [
                'id'       => 'wax-seal',
                'name'     => __( 'Sello Notarial', 'aura-suite' ),
                'category' => __( 'Sellos & Medallas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><polygon points="60,6 70,18 85,12 88,27 104,29 99,44 112,53 101,65 109,79 94,85 95,101 80,99 73,113 60,104 47,113 40,99 25,101 26,85 11,79 19,65 8,53 21,44 16,29 32,27 35,12 50,18" fill="#991b1b"/><circle cx="60" cy="60" r="34" fill="#dc2626" stroke="#fef2f2" stroke-width="2.5"/><circle cx="60" cy="60" r="24" fill="#b91c1c" stroke="#fde047" stroke-width="2"/><polygon points="60,42 65,53 77,54 68,62 71,74 60,68 49,74 52,62 43,54 55,53" fill="#fde047"/></svg>',
            ],
            [
                'id'       => 'trophy-cup',
                'name'     => __( 'Copa Trofeo', 'aura-suite' ),
                'category' => __( 'Sellos & Medallas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M32,22 H88 V50 C88,70 76,82 60,82 C44,82 32,70 32,50 Z" fill="#f59e0b"/><path d="M32,28 H16 C16,48 22,58 34,58" fill="none" stroke="#d97706" stroke-width="6"/><path d="M88,28 H104 C104,48 98,58 86,58" fill="none" stroke="#d97706" stroke-width="6"/><rect x="53" y="80" width="14" height="18" fill="#d97706"/><rect x="36" y="98" width="48" height="12" rx="4" fill="#1e293b"/><polygon points="60,34 64,43 74,44 67,50 69,60 60,55 51,60 53,50 46,44 56,43" fill="#fef3c7"/></svg>',
            ],
            [
                'id'       => 'ribbon-banner',
                'name'     => __( 'Cinta Banner', 'aura-suite' ),
                'category' => __( 'Sellos & Medallas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 80"><polygon points="8,24 44,24 44,64 8,64 22,44" fill="#1e3a8a"/><polygon points="212,24 176,24 176,64 212,64 198,44" fill="#1e3a8a"/><rect x="32" y="14" width="156" height="44" rx="6" fill="#2563eb"/><rect x="38" y="20" width="144" height="32" rx="4" fill="none" stroke="#fde047" stroke-width="2"/></svg>',
            ],
            [
                'id'       => 'grad-cap',
                'name'     => __( 'Birrete Académico', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><polygon points="60,22 108,46 60,70 12,46" fill="#1e293b"/><path d="M30,57 V82 C30,92 90,92 90,82 V57 L60,72 Z" fill="#334155"/><line x1="98" y1="50" x2="98" y2="86" stroke="#f59e0b" stroke-width="4"/><circle cx="98" cy="90" r="6" fill="#d97706"/></svg>',
            ],
            [
                'id'       => 'scroll-diploma',
                'name'     => __( 'Pergamino Diploma', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect x="22" y="20" width="76" height="80" rx="8" fill="#fef3c7" stroke="#b45309" stroke-width="3"/><rect x="34" y="34" width="52" height="6" rx="3" fill="#92400e"/><rect x="34" y="48" width="52" height="5" rx="2.5" fill="#d97706"/><rect x="34" y="60" width="40" height="5" rx="2.5" fill="#d97706"/><circle cx="78" cy="82" r="12" fill="#dc2626"/><polygon points="73,92 78,110 84,96" fill="#991b1b"/></svg>',
            ],
            [
                'id'       => 'open-book',
                'name'     => __( 'Libro Abierto', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M12,30 C32,22 50,26 60,38 C70,26 88,22 108,30 V92 C88,84 70,88 60,98 C50,88 32,84 12,92 Z" fill="#1e3a8a"/><path d="M18,34 C34,28 48,32 58,42 V88 C48,80 34,78 18,84 Z" fill="#f8fafc"/><path d="M102,34 C86,28 72,32 62,42 V88 C72,80 86,78 102,84 Z" fill="#e2e8f0"/><line x1="26" y1="48" x2="48" y2="48" stroke="#64748b" stroke-width="3"/><line x1="26" y1="60" x2="48" y2="60" stroke="#64748b" stroke-width="3"/><line x1="72" y1="48" x2="94" y2="48" stroke="#64748b" stroke-width="3"/><line x1="72" y1="60" x2="94" y2="60" stroke="#64748b" stroke-width="3"/></svg>',
            ],
            [
                'id'       => 'bible-cross',
                'name'     => __( 'Biblia & Cruz', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect x="26" y="16" width="68" height="88" rx="6" fill="#4c1d95"/><rect x="34" y="16" width="6" height="88" fill="#7c3aed"/><rect x="57" y="34" width="10" height="44" rx="2" fill="#f59e0b"/><rect x="44" y="46" width="36" height="10" rx="2" fill="#f59e0b"/><rect x="32" y="96" width="58" height="6" fill="#fde68a"/></svg>',
            ],
            [
                'id'       => 'peace-dove',
                'name'     => __( 'Paloma & Luz', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="46" fill="#eff6ff" stroke="#3b82f6" stroke-width="3"/><path d="M28,74 C44,72 52,58 58,42 C64,28 78,24 90,32 C80,38 78,48 84,58 C72,60 66,70 72,82 C54,84 40,80 28,74 Z" fill="#1d4ed8"/><polygon points="88,30 98,28 91,36" fill="#f59e0b"/></svg>',
            ],
            [
                'id'       => 'torch-flame',
                'name'     => __( 'Antorcha / Sabiduría', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M60,12 C76,28 78,46 60,54 C42,46 44,28 60,12 Z" fill="#ef4444"/><path d="M60,24 C69,33 70,44 60,50 C50,44 51,33 60,24 Z" fill="#fbbf24"/><polygon points="44,54 76,54 66,104 54,104" fill="#1e293b"/><rect x="42" y="52" width="36" height="8" rx="3" fill="#d97706"/></svg>',
            ],
            [
                'id'       => 'corner-ornament',
                'name'     => __( 'Esquina Clásica', 'aura-suite' ),
                'category' => __( 'Ornamentos & Esquinas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M12,108 V12 H108 V24 H24 V108 Z" fill="#b45309"/><path d="M32,88 V32 H88 V40 H40 V88 Z" fill="#d97706"/><circle cx="24" cy="24" r="8" fill="#92400e"/></svg>',
            ],
            [
                'id'       => 'divider-classic',
                'name'     => __( 'Separador Elegante', 'aura-suite' ),
                'category' => __( 'Ornamentos & Esquinas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 60"><line x1="12" y1="30" x2="96" y2="30" stroke="#b45309" stroke-width="3"/><line x1="144" y1="30" x2="228" y2="30" stroke="#b45309" stroke-width="3"/><polygon points="120,12 138,30 120,48 102,30" fill="#d97706"/><circle cx="120" cy="30" r="6" fill="#fef3c7"/></svg>',
            ],
            [
                'id'       => 'heraldic-shield',
                'name'     => __( 'Escudo Heráldico', 'aura-suite' ),
                'category' => __( 'Ornamentos & Esquinas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M20,18 H100 V62 C100,88 60,108 60,108 C60,108 20,88 20,62 Z" fill="#1e3a8a" stroke="#d97706" stroke-width="5"/><polygon points="60,36 66,49 80,50 69,59 72,73 60,65 48,73 51,59 40,50 54,49" fill="#fde047"/></svg>',
            ],
            [
                'id'       => 'royal-crown',
                'name'     => __( 'Corona Real', 'aura-suite' ),
                'category' => __( 'Ornamentos & Esquinas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><polygon points="16,84 22,36 44,58 60,26 76,58 98,36 104,84" fill="#f59e0b"/><rect x="16" y="84" width="88" height="14" rx="4" fill="#b45309"/><circle cx="22" cy="32" r="5" fill="#dc2626"/><circle cx="60" cy="22" r="6" fill="#dc2626"/><circle cx="98" cy="32" r="5" fill="#dc2626"/></svg>',
            ],
            [
                'id'       => 'triple-stars',
                'name'     => __( 'Tres Estrellas', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 80"><polygon points="40,22 45,34 58,35 48,44 51,57 40,50 29,57 32,44 22,35 35,34" fill="#d97706"/><polygon points="90,10 97,27 115,28 101,40 105,58 90,48 75,58 79,40 65,28 83,27" fill="#f59e0b"/><polygon points="140,22 145,34 158,35 148,44 151,57 140,50 129,57 132,44 122,35 135,34" fill="#d97706"/></svg>',
            ],
            [
                'id'       => 'verified-check',
                'name'     => __( 'Sello Verificado', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="46" fill="#059669"/><circle cx="60" cy="60" r="38" fill="none" stroke="#a7f3d0" stroke-width="2.5" stroke-dasharray="6,4"/><polyline points="38,62 54,78 84,42" fill="none" stroke="#ffffff" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            ],
            [
                'id'       => 'sparkle-star',
                'name'     => __( 'Estrella Brillo', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M60,8 L72,48 L112,60 L72,72 L60,112 L48,72 L8,60 L48,48 Z" fill="#7c3aed"/><circle cx="60" cy="60" r="10" fill="#fde047"/></svg>',
            ],
            [
                'id'       => 'diamond-badge',
                'name'     => __( 'Diamante Excelencia', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><polygon points="32,24 88,24 108,48 60,102 12,48" fill="#0284c7"/><polygon points="32,24 88,24 72,48 48,48" fill="#38bdf8"/><polygon points="48,48 72,48 60,102" fill="#0369a1"/></svg>',
            ],
            [
                'id'       => 'heart-service',
                'name'     => __( 'Corazón Servicio', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M60,100 C60,100 14,72 14,42 C14,26 27,16 40,16 C50,16 56,22 60,30 C64,22 70,16 80,16 C93,16 106,26 106,42 C106,72 60,100 60,100 Z" fill="#e11d48"/><circle cx="40" cy="36" r="6" fill="#fda4af"/></svg>',
            ],
            [
                'id'       => 'rosette-gold',
                'name'     => __( 'Roseta Ceremonial', 'aura-suite' ),
                'category' => __( 'Sellos & Medallas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><polygon points="44,72 34,114 50,104 58,114 60,72" fill="#991b1b"/><polygon points="76,72 86,114 70,104 62,114 60,72" fill="#b91c1c"/><circle cx="60" cy="48" r="34" fill="#b45309"/><circle cx="60" cy="48" r="28" fill="#f59e0b"/><circle cx="60" cy="48" r="20" fill="#fef3c7" stroke="#92400e" stroke-width="2"/><circle cx="60" cy="48" r="8" fill="#d97706"/></svg>',
            ],
            [
                'id'       => 'laurel-shield',
                'name'     => __( 'Escudo con Laurel', 'aura-suite' ),
                'category' => __( 'Sellos & Medallas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M28,88 C12,72 12,42 30,26 C26,46 30,68 42,84 Z" fill="#d97706"/><path d="M92,88 C108,72 108,42 90,26 C94,46 90,68 78,84 Z" fill="#d97706"/><path d="M36,24 H84 V56 C84,78 60,94 60,94 C60,94 36,78 36,56 Z" fill="#1e3a8a" stroke="#f59e0b" stroke-width="3"/><polygon points="60,38 64,48 75,49 67,56 69,67 60,61 51,67 53,56 45,49 56,48" fill="#fde047"/></svg>',
            ],
            [
                'id'       => 'radiant-cross',
                'name'     => __( 'Cruz Radiante', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="50" r="34" fill="#fef3c7"/><rect x="52" y="16" width="16" height="86" rx="3" fill="#92400e"/><rect x="30" y="38" width="60" height="16" rx="3" fill="#92400e"/><rect x="56" y="20" width="8" height="78" rx="2" fill="#d97706"/><rect x="34" y="42" width="52" height="8" rx="2" fill="#d97706"/></svg>',
            ],
            [
                'id'       => 'sanctuary-church',
                'name'     => __( 'Templo / Ministerio', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect x="57" y="8" width="6" height="18" fill="#b45309"/><rect x="51" y="13" width="18" height="5" fill="#b45309"/><polygon points="60,24 22,56 98,56" fill="#1e3a8a"/><rect x="28" y="56" width="64" height="48" fill="#f8fafc" stroke="#1e293b" stroke-width="3"/><rect x="48" y="72" width="24" height="32" rx="12" fill="#b45309"/><circle cx="60" cy="43" r="6" fill="#fde047"/></svg>',
            ],
            [
                'id'       => 'spirit-flame',
                'name'     => __( 'Llama del Espíritu', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M60,12 C86,36 96,68 78,94 C70,104 50,104 42,94 C24,68 34,36 60,12 Z" fill="#dc2626"/><path d="M60,34 C76,50 80,72 68,90 C63,96 57,96 52,90 C40,72 44,50 60,34 Z" fill="#f59e0b"/><path d="M60,56 C68,66 70,78 60,88 C50,78 52,66 60,56 Z" fill="#fef08a"/></svg>',
            ],
            [
                'id'       => 'quill-ink',
                'name'     => __( 'Pluma de Caligrafía', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M96,14 C72,18 46,38 34,74 L48,86 C78,72 96,42 96,14 Z" fill="#1e40af"/><path d="M34,74 L24,94 L48,86 Z" fill="#f59e0b"/><rect x="20" y="92" width="34" height="16" rx="4" fill="#1e293b"/><rect x="26" y="88" width="22" height="6" rx="2" fill="#b45309"/></svg>',
            ],
            [
                'id'       => 'knowledge-key',
                'name'     => __( 'Llave del Saber', 'aura-suite' ),
                'category' => __( 'Académico & Ministerio', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="38" cy="60" r="22" fill="#f59e0b" stroke="#92400e" stroke-width="4"/><circle cx="38" cy="60" r="9" fill="#fef3c7"/><rect x="58" y="54" width="46" height="12" rx="4" fill="#d97706"/><rect x="82" y="66" width="8" height="14" rx="2" fill="#92400e"/><rect x="94" y="66" width="8" height="10" rx="2" fill="#92400e"/></svg>',
            ],
            [
                'id'       => 'corner-filigree',
                'name'     => __( 'Esquina Filigrana', 'aura-suite' ),
                'category' => __( 'Ornamentos & Esquinas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M10,110 V10 H110 V22 H22 V110 Z" fill="#1e3a8a"/><path d="M26,94 V26 H94 V34 H34 V94 Z" fill="#d97706"/><rect x="12" y="12" width="16" height="16" rx="3" fill="#f59e0b"/><circle cx="46" cy="46" r="6" fill="#92400e"/></svg>',
            ],
            [
                'id'       => 'crest-header',
                'name'     => __( 'Cresta Ornamental', 'aura-suite' ),
                'category' => __( 'Ornamentos & Esquinas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 60"><line x1="10" y1="34" x2="86" y2="34" stroke="#1e293b" stroke-width="2.5"/><line x1="154" y1="34" x2="230" y2="34" stroke="#1e293b" stroke-width="2.5"/><polygon points="120,10 144,34 120,52 96,34" fill="#1e3a8a"/><polygon points="120,17 136,34 120,45 104,34" fill="#f59e0b"/><circle cx="86" cy="34" r="4" fill="#b45309"/><circle cx="154" cy="34" r="4" fill="#b45309"/></svg>',
            ],
            [
                'id'       => 'corner-botanical',
                'name'     => __( 'Esquina Hojas Oro', 'aura-suite' ),
                'category' => __( 'Ornamentos & Esquinas', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M14,104 C14,44 44,14 104,14 C72,30 30,72 14,104 Z" fill="#b45309"/><circle cx="24" cy="24" r="10" fill="#f59e0b"/><circle cx="48" cy="20" r="6" fill="#d97706"/><circle cx="20" cy="48" r="6" fill="#d97706"/></svg>',
            ],
            [
                'id'       => 'five-stars-arc',
                'name'     => __( 'Cinco Estrellas', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 70"><polygon points="30,28 34,37 44,38 36,44 38,54 30,49 22,54 24,44 16,38 26,37" fill="#d97706"/><polygon points="68,18 72,27 82,28 74,34 76,44 68,39 60,44 62,34 54,28 64,27" fill="#f59e0b"/><polygon points="110,10 116,22 128,23 119,31 121,44 110,37 99,44 101,31 92,23 104,22" fill="#eab308"/><polygon points="152,18 156,27 166,28 158,34 160,44 152,39 144,44 146,34 138,28 148,27" fill="#f59e0b"/><polygon points="190,28 194,37 204,38 196,44 198,54 190,49 182,54 184,44 176,38 186,37" fill="#d97706"/></svg>',
            ],
            [
                'id'       => 'fellowship-hands',
                'name'     => __( 'Unidad y Servicio', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="46" fill="#0f766e"/><circle cx="38" cy="48" r="10" fill="#fef3c7"/><circle cx="82" cy="48" r="10" fill="#fde68a"/><circle cx="60" cy="36" r="12" fill="#ffffff"/><path d="M28,84 C28,66 92,66 92,84 Z" fill="#14b8a6"/></svg>',
            ],
            [
                'id'       => 'compass-vision',
                'name'     => __( 'Brújula / Visión', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="46" fill="#1e293b" stroke="#f59e0b" stroke-width="5"/><circle cx="60" cy="60" r="34" fill="#f8fafc"/><polygon points="60,24 72,60 60,54 48,60" fill="#dc2626"/><polygon points="60,96 72,60 60,66 48,60" fill="#1e3a8a"/><circle cx="60" cy="60" r="5" fill="#f59e0b"/></svg>',
            ],
            [
                'id'       => 'tech-award-gear',
                'name'     => __( 'Mérito Técnico', 'aura-suite' ),
                'category' => __( 'Insignias & Iconos', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="40" fill="#334155" stroke="#0284c7" stroke-width="8" stroke-dasharray="14,8"/><circle cx="60" cy="60" r="26" fill="#0ea5e9"/><polyline points="46,60 56,70 76,48" fill="none" stroke="#ffffff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            ],
            // ── Perfiles & Graduados ──
            [
                'id'       => 'profile-male-neutral',
                'name'     => __( 'Perfil Hombre (Neutro)', 'aura-suite' ),
                'category' => __( 'Perfiles & Graduados', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="56" fill="#e0f2fe"/><path d="M17,96 C23,82 40,78 60,78 C80,78 97,82 103,96 A56,56 0 0,1 17,96 Z" fill="#1e3a8a"/><polygon points="50,78 60,96 70,78" fill="#ffffff"/><polygon points="57,84 63,84 62,102 60,106 58,102" fill="#2563eb"/><rect x="53" y="65" width="14" height="16" rx="6" fill="#eab696"/><ellipse cx="43" cy="51" rx="3.5" ry="5" fill="#eab696"/><ellipse cx="77" cy="51" rx="3.5" ry="5" fill="#eab696"/><ellipse cx="60" cy="49" rx="17" ry="20" fill="#f5cbb0"/><path d="M43,45 C42,30 50,24 61,24 C72,24 78,31 77,45 C75,38 71,34 65,34 C57,34 51,36 43,45 Z" fill="#3b2f2f"/><circle cx="53.5" cy="49.5" r="2" fill="#1e293b"/><circle cx="66.5" cy="49.5" r="2" fill="#1e293b"/><path d="M55,58 Q60,62 65,58" fill="none" stroke="#b47b66" stroke-width="2" stroke-linecap="round"/></svg>',
            ],
            [
                'id'       => 'profile-female-neutral',
                'name'     => __( 'Perfil Mujer (Neutro)', 'aura-suite' ),
                'category' => __( 'Perfiles & Graduados', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="56" fill="#f3e8ff"/><path d="M36,52 C36,30 46,23 60,23 C74,23 84,30 84,52 L86,79 C86,83 80,86 74,84 L46,84 C40,86 34,83 34,79 Z" fill="#4a2c2a"/><path d="M17,96 C23,82 40,78 60,78 C80,78 97,82 103,96 A56,56 0 0,1 17,96 Z" fill="#6d28d9"/><path d="M48,78 L60,95 L72,78 Z" fill="#ffffff"/><rect x="54" y="66" width="12" height="15" rx="5" fill="#eab696"/><ellipse cx="60" cy="50" rx="16.5" ry="19.5" fill="#f5cbb0"/><path d="M43,46 C45,31 53,26 62,27 C70,27 76,33 77,46 C72,38 66,35 58,36 C51,37 46,41 43,46 Z" fill="#4a2c2a"/><circle cx="43.5" cy="56" r="2" fill="#eab308"/><circle cx="76.5" cy="56" r="2" fill="#eab308"/><circle cx="53.5" cy="50" r="2" fill="#1e293b"/><circle cx="66.5" cy="50" r="2" fill="#1e293b"/><path d="M55,59 Q60,63 65,59" fill="none" stroke="#c06c7a" stroke-width="2" stroke-linecap="round"/></svg>',
            ],
            [
                'id'       => 'profile-male-graduate',
                'name'     => __( 'Estudiante Hombre Graduado', 'aura-suite' ),
                'category' => __( 'Perfiles & Graduados', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="56" fill="#dbeafe"/><path d="M20,99 C26,85 42,81 60,81 C78,81 94,85 100,99 A56,56 0 0,1 20,99 Z" fill="#1e293b"/><polygon points="44,82 52,81 56,115 47,114" fill="#2563eb"/><polygon points="76,82 68,81 64,115 73,114" fill="#2563eb"/><polygon points="52,81 60,96 68,81" fill="#ffffff"/><rect x="54" y="70" width="12" height="14" rx="5" fill="#eab696"/><ellipse cx="44" cy="56" rx="3.2" ry="4.5" fill="#eab696"/><ellipse cx="76" cy="56" rx="3.2" ry="4.5" fill="#eab696"/><ellipse cx="60" cy="56" rx="16" ry="18" fill="#f5cbb0"/><path d="M44,50 C45,42 52,39 60,39 C68,39 75,42 76,50 C72,46 66,44 60,44 C54,44 48,46 44,50 Z" fill="#3b2f2f"/><circle cx="54" cy="56.5" r="1.9" fill="#1e293b"/><circle cx="66" cy="56.5" r="1.9" fill="#1e293b"/><path d="M55,64 Q60,68 65,64" fill="none" stroke="#b47b66" stroke-width="2" stroke-linecap="round"/><path d="M43,39 L77,39 L75,48 C66,51 54,51 45,48 Z" fill="#1e293b"/><polygon points="60,18 92,30 60,42 28,30" fill="#0f172a"/><circle cx="60" cy="30" r="2.4" fill="#eab308"/><path d="M60,30 L85,35 L86,52" fill="none" stroke="#eab308" stroke-width="2.4" stroke-linecap="round"/><circle cx="86" cy="54" r="3" fill="#f59e0b"/></svg>',
            ],
            [
                'id'       => 'profile-female-graduate',
                'name'     => __( 'Estudiante Mujer Graduada', 'aura-suite' ),
                'category' => __( 'Perfiles & Graduados', 'aura-suite' ),
                'svg'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="60" cy="60" r="56" fill="#ede9fe"/><path d="M38,54 C38,38 48,34 60,34 C72,34 82,38 82,54 L84,82 C84,86 78,88 73,86 L47,86 C42,88 36,86 36,82 Z" fill="#4a2c2a"/><path d="M20,99 C26,85 42,81 60,81 C78,81 94,85 100,99 A56,56 0 0,1 20,99 Z" fill="#1e293b"/><polygon points="44,82 52,81 56,115 47,114" fill="#7c3aed"/><polygon points="76,82 68,81 64,115 73,114" fill="#7c3aed"/><polygon points="52,81 60,96 68,81" fill="#ffffff"/><rect x="54" y="70" width="12" height="14" rx="5" fill="#eab696"/><ellipse cx="60" cy="56" rx="15.5" ry="17.5" fill="#f5cbb0"/><path d="M44.5,51 C46,42 53,39 60,39 C67,39 74,42 75.5,51 C70,45 64,43 58,44 C52,44 48,47 44.5,51 Z" fill="#4a2c2a"/><circle cx="44" cy="61" r="1.9" fill="#eab308"/><circle cx="76" cy="61" r="1.9" fill="#eab308"/><circle cx="54" cy="56.5" r="1.9" fill="#1e293b"/><circle cx="66" cy="56.5" r="1.9" fill="#1e293b"/><path d="M55,64.5 Q60,68 65,64.5" fill="none" stroke="#c06c7a" stroke-width="2" stroke-linecap="round"/><path d="M43,39 L77,39 L75,48 C66,51 54,51 45,48 Z" fill="#1e293b"/><polygon points="60,18 92,30 60,42 28,30" fill="#0f172a"/><circle cx="60" cy="30" r="2.4" fill="#eab308"/><path d="M60,30 L85,35 L86,52" fill="none" stroke="#eab308" stroke-width="2.4" stroke-linecap="round"/><circle cx="86" cy="54" r="3" fill="#f59e0b"/></svg>',
            ],
        ];
    }

    /**
     * Devuelve el SVG de avatar de perfil por defecto cuando el destinatario no tiene foto.
     *
     * @param string $preset_id ID del clipart o 'auto' / 'none'.
     * @param string $gender    Género opcional del estudiante ('M', 'F', etc.).
     * @return string Markup SVG o '' si está configurado como 'none'.
     */
    public static function get_default_profile_avatar_svg( string $preset_id = 'profile-male-graduate', string $gender = '' ): string {
        $preset_id = sanitize_key( $preset_id );
        if ( $preset_id === 'none' ) {
            return '';
        }

        if ( $preset_id === '' || $preset_id === 'auto' ) {
            $g = strtoupper( trim( $gender ) );
            $preset_id = ( $g === 'F' ) ? 'profile-female-graduate' : 'profile-male-graduate';
        }

        foreach ( self::get_clipart_library() as $item ) {
            if ( ( $item['id'] ?? '' ) === $preset_id && ! empty( $item['svg'] ) ) {
                return (string) $item['svg'];
            }
        }

        return '';
    }
}
