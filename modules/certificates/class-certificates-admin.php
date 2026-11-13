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
            self::preset_moderno_minimalista(),
            self::preset_participacion(),
            self::preset_academico_azul_real(),
            self::preset_corporativo_oscuro_vip(),
            self::preset_diploma_solemne_vertical(),
            self::preset_reconocimiento_honorifico(),
            self::preset_constancia_taller(),
            self::preset_institucional_4_firmas(),
        ];
    }

    // ─── Diploma Clásico Dorado ───────────────────────────────────
    private static function preset_diploma_clasico_dorado(): array {
        $w = 1122;  // A4 landscape, 96 dpi
        $h = 794;

        $objects = [
            // ── Esquinas doradas (4 cuadrados decorativos) ──────
            [ 'type' => 'rect', 'left' => 0,      'top' => 0,      'width' => 50, 'height' => 50, 'fill' => '#B8860B', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => $w - 50, 'top' => 0,      'width' => 50, 'height' => 50, 'fill' => '#B8860B', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 0,      'top' => $h - 50, 'width' => 50, 'height' => 50, 'fill' => '#B8860B', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => $w - 50,'top' => $h - 50, 'width' => 50, 'height' => 50, 'fill' => '#B8860B', 'stroke' => null ],

            // ── Marco exterior e interior ────────────────────────
            [ 'type' => 'rect', 'left' => 12, 'top' => 12,
              'width' => $w - 24, 'height' => $h - 24,
              'fill' => 'rgba(0,0,0,0)', 'stroke' => '#B8860B', 'strokeWidth' => 5 ],
            [ 'type' => 'rect', 'left' => 22, 'top' => 22,
              'width' => $w - 44, 'height' => $h - 44,
              'fill' => 'rgba(0,0,0,0)', 'stroke' => '#D4AA4A', 'strokeWidth' => 1.5 ],

            // ── Título DIPLOMA ────────────────────────────────────
            [ 'type' => 'textbox', 'left' => 61, 'top' => 62,
              'width' => 1000, 'text' => 'D I P L O M A',
              'fontSize' => 72, 'fontFamily' => 'Times New Roman',
              'fontWeight' => 'bold', 'fill' => '#B8860B',
              'textAlign' => 'center', 'charSpacing' => 250 ],

            // ── Separador dorado bajo título ─────────────────────
            [ 'type' => 'rect', 'left' => 261, 'top' => 157, 'width' => 600, 'height' => 3,
              'fill' => '#B8860B', 'stroke' => null ],

            // ── "Se otorga el presente Diploma a:" ───────────────
            [ 'type' => 'textbox', 'left' => 61, 'top' => 172,
              'width' => 1000, 'text' => 'Se otorga el presente Diploma a:',
              'fontSize' => 18, 'fontFamily' => 'Times New Roman',
              'fontStyle' => 'italic', 'fill' => '#7B6040', 'textAlign' => 'center' ],

            // ── Nombre del estudiante ─────────────────────────────
            [ 'type' => 'textbox', 'left' => 61, 'top' => 204,
              'width' => 1000, 'text' => '{nombre_completo}',
              'fontSize' => 52, 'fontFamily' => 'Times New Roman',
              'fontWeight' => 'bold', 'fill' => '#2D1B0E', 'textAlign' => 'center' ],

            // ── Separador bajo nombre ────────────────────────────
            [ 'type' => 'rect', 'left' => 281, 'top' => 276, 'width' => 560, 'height' => 2,
              'fill' => '#B8860B', 'stroke' => null ],

            // ── "Por haber completado..." ────────────────────────
            [ 'type' => 'textbox', 'left' => 61, 'top' => 292,
              'width' => 1000, 'text' => 'Por haber completado satisfactoriamente el curso:',
              'fontSize' => 17, 'fontFamily' => 'Times New Roman',
              'fontStyle' => 'italic', 'fill' => '#7B6040', 'textAlign' => 'center' ],

            // ── Nombre del curso ─────────────────────────────────
            [ 'type' => 'textbox', 'left' => 61, 'top' => 326,
              'width' => 1000, 'text' => '{curso}',
              'fontSize' => 38, 'fontFamily' => 'Times New Roman',
              'fontWeight' => 'bold', 'fontStyle' => 'italic',
              'fill' => '#B8860B', 'textAlign' => 'center' ],

            // ── Programa ─────────────────────────────────────────
            [ 'type' => 'textbox', 'left' => 61, 'top' => 380,
              'width' => 1000, 'text' => '{programa}',
              'fontSize' => 20, 'fontFamily' => 'Times New Roman',
              'fill' => '#555540', 'textAlign' => 'center' ],

            // ── Descripción opcional ─────────────────────────────
            [ 'type' => 'textbox', 'left' => 201, 'top' => 418,
              'width' => 720, 'text' => '{descripcion}',
              'fontSize' => 15, 'fontFamily' => 'Times New Roman',
              'fontStyle' => 'italic', 'fill' => '#888876', 'textAlign' => 'center' ],

            // ── Separador footer ─────────────────────────────────
            [ 'type' => 'rect', 'left' => 61, 'top' => 624, 'width' => $w - 122, 'height' => 1,
              'fill' => '#B8860B', 'stroke' => null ],

            // ── Fecha (izquierda) ────────────────────────────────
            [ 'type' => 'textbox', 'left' => 80, 'top' => 636,
              'width' => 340, 'text' => 'Otorgado el día:',
              'fontSize' => 13, 'fontFamily' => 'Times New Roman',
              'fill' => '#888876', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 654,
              'width' => 340, 'text' => '{fecha_emision}',
              'fontSize' => 17, 'fontFamily' => 'Times New Roman',
              'fontWeight' => 'bold', 'fill' => '#444433', 'textAlign' => 'left' ],

            // ── Organización (derecha) ───────────────────────────
            [ 'type' => 'textbox', 'left' => 702, 'top' => 636,
              'width' => 340, 'text' => '{organizacion}',
              'fontSize' => 20, 'fontFamily' => 'Times New Roman',
              'fontWeight' => 'bold', 'fill' => '#2D1B0E', 'textAlign' => 'right' ],

            // ── Instructor (derecha bajo organización) ───────────
            [ 'type' => 'textbox', 'left' => 702, 'top' => 662,
              'width' => 340, 'text' => '{instructor}',
              'fontSize' => 14, 'fontFamily' => 'Times New Roman',
              'fontStyle' => 'italic', 'fill' => '#888876', 'textAlign' => 'right' ],

            // ── Folio (centro, pie) ──────────────────────────────
            [ 'type' => 'textbox', 'left' => 411, 'top' => 654,
              'width' => 300, 'text' => 'Folio: {folio}',
              'fontSize' => 13, 'fontFamily' => 'Times New Roman',
              'fill' => '#BBBBAA', 'textAlign' => 'center' ],

            // ── Placeholder QR (esquina inferior derecha) ────────
            [ 'type' => 'rect', 'left' => $w - 130, 'top' => $h - 130,
              'width' => 100, 'height' => 100,
              'fill' => '#EEE8D5', 'stroke' => '#BBBBAA', 'strokeWidth' => 1,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
        ];

        return [
            'name'        => __( 'Diploma Clásico Dorado', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#B8860B',
            'description' => __( 'Marco dorado tradicional sobre fondo marfil', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#FFFDF0',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── Moderno Minimalista (A4 Landscape) ──────────────────────
    private static function preset_moderno_minimalista(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // Barra superior violeta
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => $w, 'height' => 10,
              'fill' => '#8b5cf6', 'stroke' => null ],
            // Barra inferior violeta
            [ 'type' => 'rect', 'left' => 0, 'top' => $h - 10, 'width' => $w, 'height' => 10,
              'fill' => '#8b5cf6', 'stroke' => null ],
            // Rectángulo lateral izquierdo
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => 8, 'height' => $h,
              'fill' => '#8b5cf6', 'stroke' => null ],
            // Título
            [ 'type' => 'textbox', 'left' => 60, 'top' => 70,
              'width' => $w - 100, 'text' => 'DIPLOMA',
              'fontSize' => 80, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#111827', 'textAlign' => 'left' ],
            // Línea de acento
            [ 'type' => 'rect', 'left' => 60, 'top' => 175, 'width' => 120, 'height' => 5,
              'fill' => '#8b5cf6', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 200,
              'width' => $w - 100, 'text' => 'Se certifica que:',
              'fontSize' => 18, 'fontFamily' => 'Lato',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 235,
              'width' => $w - 100, 'text' => '{nombre_completo}',
              'fontSize' => 50, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#8b5cf6', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 310,
              'width' => $w - 100, 'text' => 'Ha completado exitosamente el curso:',
              'fontSize' => 17, 'fontFamily' => 'Lato',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 342,
              'width' => $w - 100, 'text' => '{curso}',
              'fontSize' => 40, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#111827', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 400,
              'width' => 600, 'text' => '{programa}',
              'fontSize' => 20, 'fontFamily' => 'Lato',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            // QR y Firmante
            [ 'type' => 'line', 'left' => 60, 'top' => 590, 'x1' => 0, 'y1' => 0, 'x2' => 230, 'y2' => 0,
              'stroke' => '#cbd5e1', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 600,
              'width' => 240, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#374151', 'textAlign' => 'left' ],
            [ 'type' => 'rect', 'left' => $w - 160, 'top' => 555, 'width' => 96, 'height' => 96,
              'fill' => '#f8fafc', 'stroke' => '#8b5cf6', 'strokeWidth' => 1.5,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            // Footer
            [ 'type' => 'rect', 'left' => 60, 'top' => 700, 'width' => 500, 'height' => 1,
              'fill' => '#e5e7eb', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 710,
              'width' => 350, 'text' => '{organizacion}',
              'fontSize' => 17, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#374151', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 734,
              'width' => 350, 'text' => '{fecha_emision}',
              'fontSize' => 16, 'fontFamily' => 'Lato',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 650, 'top' => 710,
              'width' => 400, 'text' => 'Folio: {folio}',
              'fontSize' => 14, 'fontFamily' => 'Courier New',
              'fill' => '#9ca3af', 'textAlign' => 'right' ],
        ];

        return [
            'name'        => __( 'Moderno Minimalista', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#8b5cf6',
            'description' => __( 'Tipografía moderna y barra de acento violeta', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#ffffff',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── Certificado de Participación (A4 Landscape) ─────────────
    private static function preset_participacion(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            // Marco verde
            [ 'type' => 'rect', 'left' => 20, 'top' => 20, 'width' => $w - 40, 'height' => $h - 40,
              'fill' => 'rgba(0,0,0,0)', 'stroke' => '#16a34a', 'strokeWidth' => 4,
              'rx' => 12, 'ry' => 12 ],
            [ 'type' => 'rect', 'left' => 28, 'top' => 28, 'width' => $w - 56, 'height' => $h - 56,
              'fill' => 'rgba(0,0,0,0)', 'stroke' => '#86efac', 'strokeWidth' => 1.5,
              'rx' => 8, 'ry' => 8 ],
            // Encabezado
            [ 'type' => 'textbox', 'left' => 61, 'top' => 60,
              'width' => 1000, 'text' => 'CERTIFICADO',
              'fontSize' => 68, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#15803d', 'textAlign' => 'center',
              'charSpacing' => 180 ],
            [ 'type' => 'textbox', 'left' => 61, 'top' => 148,
              'width' => 1000, 'text' => 'DE PARTICIPACIÓN',
              'fontSize' => 24, 'fontFamily' => 'Arial',
              'fill' => '#4ade80', 'textAlign' => 'center', 'charSpacing' => 100 ],
            // Separador
            [ 'type' => 'rect', 'left' => 361, 'top' => 185, 'width' => 400, 'height' => 3,
              'fill' => '#16a34a', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 61, 'top' => 204,
              'width' => 1000, 'text' => 'Este certificado se otorga a:',
              'fontSize' => 18, 'fontFamily' => 'Arial',
              'fill' => '#4b7c59', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 61, 'top' => 236,
              'width' => 1000, 'text' => '{nombre_completo}',
              'fontSize' => 50, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#14532d', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 61, 'top' => 306,
              'width' => 1000, 'text' => 'Por su participación en:',
              'fontSize' => 17, 'fontFamily' => 'Arial',
              'fill' => '#4b7c59', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 61, 'top' => 336,
              'width' => 1000, 'text' => '{curso}',
              'fontSize' => 36, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#15803d', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 61, 'top' => 388,
              'width' => 1000, 'text' => '{descripcion}',
              'fontSize' => 16, 'fontFamily' => 'Arial',
              'fontStyle' => 'italic', 'fill' => '#6b7280', 'textAlign' => 'center' ],
            // Firmante y QR
            [ 'type' => 'line', 'left' => 140, 'top' => 615, 'x1' => 0, 'y1' => 0, 'x2' => 230, 'y2' => 0,
              'stroke' => '#16a34a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 130, 'top' => 625,
              'width' => 250, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}",
              'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#14532d', 'textAlign' => 'center' ],
            [ 'type' => 'rect', 'left' => $w - 165, 'top' => 570, 'width' => 94, 'height' => 94,
              'fill' => '#ffffff', 'stroke' => '#16a34a', 'strokeWidth' => 1.5,
              'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            // Footer
            [ 'type' => 'rect', 'left' => 61, 'top' => 700, 'width' => $w - 122, 'height' => 1,
              'fill' => '#86efac', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 712,
              'width' => 350, 'text' => '{organizacion}',
              'fontSize' => 17, 'fontFamily' => 'Montserrat',
              'fontWeight' => 'bold', 'fill' => '#15803d', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 734,
              'width' => 350, 'text' => '{fecha_emision}',
              'fontSize' => 16, 'fontFamily' => 'Lato',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 650, 'top' => 712,
              'width' => 400, 'text' => 'Folio: {folio}',
              'fontSize' => 14, 'fontFamily' => 'Courier New',
              'fill' => '#15803d', 'textAlign' => 'right' ],
        ];

        return [
            'name'        => __( 'Certificado de Participación', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#15803d',
            'description' => __( 'Estilo limpio y fresco en tonos verdes', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#f0fdf4',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── Académico Azul Real & Oro ───────────────────────────────
    private static function preset_academico_azul_real(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => $w, 'height' => 28, 'fill' => '#1e3a8a', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 0, 'top' => $h - 28, 'width' => $w, 'height' => 28, 'fill' => '#1e3a8a', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 26, 'top' => 42, 'width' => $w - 52, 'height' => $h - 84, 'fill' => 'transparent', 'stroke' => '#ca8a04', 'strokeWidth' => 2.5 ],
            [ 'type' => 'rect', 'left' => 36, 'top' => 52, 'width' => $w - 72, 'height' => $h - 104, 'fill' => 'transparent', 'stroke' => '#93c5fd', 'strokeWidth' => 1 ],
            [ 'type' => 'circle', 'left' => 533, 'top' => 74, 'radius' => 28, 'fill' => '#1e3a8a', 'stroke' => '#ca8a04', 'strokeWidth' => 3 ],
            [ 'type' => 'textbox', 'left' => 533, 'top' => 91, 'width' => 56, 'text' => '★', 'fontSize' => 24, 'fontFamily' => 'Georgia', 'fill' => '#fde047', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 145, 'width' => 962, 'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#1e3a8a', 'textAlign' => 'center', 'charSpacing' => 180 ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 182, 'width' => 962, 'text' => 'CERTIFICADO ACADÉMICO', 'fontSize' => 40, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center', 'charSpacing' => 120 ],
            [ 'type' => 'rect', 'left' => 411, 'top' => 242, 'width' => 300, 'height' => 3, 'fill' => '#ca8a04', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 100, 'top' => 266, 'width' => 922, 'text' => 'Se otorga con distinción el presente reconocimiento a:', 'fontSize' => 16, 'fontFamily' => 'Merriweather', 'fontStyle' => 'italic', 'fill' => '#475569', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 310, 'width' => 962, 'text' => '{nombre_estudiante}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#1e3a8a', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 140, 'top' => 378, 'width' => 842, 'text' => 'Por haber completado y aprobado satisfactoriamente el programa de', 'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#334155', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 410, 'width' => 942, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 130, 'top' => 462, 'width' => 862, 'text' => 'Intensidad horaria: {horas}  •  Calificación: {calificacion}  •  {programa}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 130, 'top' => 494, 'width' => 862, 'text' => 'Expedido en {ciudad}, el {fecha_emision_larga}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],
            [ 'type' => 'line', 'left' => 180, 'top' => 645, 'x1' => -110, 'y1' => 0, 'x2' => 110, 'y2' => 0, 'stroke' => '#1e3a8a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 160, 'top' => 655, 'width' => 260, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],
            [ 'type' => 'line', 'left' => 560, 'top' => 645, 'x1' => -110, 'y1' => 0, 'x2' => 110, 'y2' => 0, 'stroke' => '#1e3a8a', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 540, 'top' => 655, 'width' => 260, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],
            [ 'type' => 'rect', 'left' => 940, 'top' => 585, 'width' => 90, 'height' => 90, 'fill' => '#f8fafc', 'stroke' => '#cbd5e1', 'strokeWidth' => 1, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 920, 'top' => 682, 'width' => 130, 'text' => 'Folio: {folio}', 'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#475569', 'textAlign' => 'center' ],
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

    // ─── Corporativo Oscuro & Turquesa VIP ───────────────────────
    private static function preset_corporativo_oscuro_vip(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            [ 'type' => 'rect', 'left' => 28, 'top' => 28, 'width' => $w - 56, 'height' => $h - 56, 'fill' => 'transparent', 'stroke' => '#2dd4bf', 'strokeWidth' => 2 ],
            [ 'type' => 'rect', 'left' => 40, 'top' => 40, 'width' => 14, 'height' => $h - 80, 'fill' => '#14b8a6', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 76, 'width' => 700, 'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#5eead4', 'textAlign' => 'left', 'charSpacing' => 200 ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 118, 'width' => 780, 'text' => 'CERTIFICACIÓN EJECUTIVA', 'fontSize' => 40, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#f8fafc', 'textAlign' => 'left' ],
            [ 'type' => 'rect', 'left' => 90, 'top' => 176, 'width' => 140, 'height' => 4, 'fill' => '#14b8a6', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 226, 'width' => 880, 'text' => 'OTORGADO A', 'fontSize' => 13, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#94a3b8', 'textAlign' => 'left', 'charSpacing' => 220 ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 256, 'width' => 940, 'text' => '{nombre_estudiante}', 'fontSize' => 48, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 324, 'width' => 880, 'text' => 'Doc.: {documento}  •  {correo}', 'fontSize' => 14, 'fontFamily' => 'Roboto', 'fill' => '#94a3b8', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 378, 'width' => 880, 'text' => 'Por acreditar las competencias del programa:', 'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#cbd5e1', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 410, 'width' => 900, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#2dd4bf', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 462, 'width' => 760, 'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#94a3b8', 'textAlign' => 'left' ],
            [ 'type' => 'line', 'left' => 90, 'top' => 638, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0, 'stroke' => '#2dd4bf', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 648, 'width' => 260, 'text' => "{instructor}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#e2e8f0', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 410, 'top' => 648, 'width' => 300, 'text' => "Fecha: {fecha_emision}\nFolio: {folio}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#94a3b8', 'textAlign' => 'left' ],
            [ 'type' => 'rect', 'left' => 940, 'top' => 580, 'width' => 96, 'height' => 96, 'fill' => '#ffffff', 'stroke' => '#2dd4bf', 'strokeWidth' => 2, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 918, 'top' => 686, 'width' => 140, 'text' => 'VERIFICAR QR', 'fontSize' => 11, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#5eead4', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Corporativo Oscuro & Turquesa', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#0f172a',
            'description' => __( 'Elegante fondo oscuro con acentos turquesa VIP', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#0f172a',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── Diploma Solemne Vertical (A4 Portrait) ───────────────────
    private static function preset_diploma_solemne_vertical(): array {
        $w = 794;
        $h = 1122;

        $objects = [
            [ 'type' => 'rect', 'left' => 28, 'top' => 28, 'width' => $w - 56, 'height' => $h - 56, 'fill' => 'transparent', 'stroke' => '#7c2d12', 'strokeWidth' => 3 ],
            [ 'type' => 'rect', 'left' => 40, 'top' => 40, 'width' => $w - 80, 'height' => $h - 80, 'fill' => 'transparent', 'stroke' => '#d97706', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 70, 'top' => 92, 'width' => $w - 140, 'text' => '{organizacion}', 'fontSize' => 18, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#7c2d12', 'textAlign' => 'center', 'charSpacing' => 140 ],
            [ 'type' => 'textbox', 'left' => 70, 'top' => 152, 'width' => $w - 140, 'text' => 'DIPLOMA', 'fontSize' => 54, 'fontFamily' => 'Cinzel', 'fontWeight' => 'bold', 'fill' => '#1c1917', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 70, 'top' => 222, 'width' => $w - 140, 'text' => 'DE HONOR Y MÉRITO', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fill' => '#b45309', 'textAlign' => 'center', 'charSpacing' => 260 ],
            [ 'type' => 'rect', 'left' => 267, 'top' => 270, 'width' => 260, 'height' => 2, 'fill' => '#d97706', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 325, 'width' => $w - 160, 'text' => 'Por la presente se confiere el presente diploma a:', 'fontSize' => 17, 'fontFamily' => 'Georgia', 'fontStyle' => 'italic', 'fill' => '#57534e', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 388, 'width' => $w - 120, 'text' => '{nombre_estudiante}', 'fontSize' => 46, 'fontFamily' => 'Great Vibes', 'fill' => '#7c2d12', 'textAlign' => 'center' ],
            [ 'type' => 'rect', 'left' => 197, 'top' => 458, 'width' => 400, 'height' => 1.5, 'fill' => '#d6d3d1', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 502, 'width' => $w - 160, 'text' => 'Por haber concluido con excelencia académica:', 'fontSize' => 16, 'fontFamily' => 'Lato', 'fill' => '#44403c', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 70, 'top' => 544, 'width' => $w - 140, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#1c1917', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 618, 'width' => $w - 180, 'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#57534e', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 700, 'width' => $w - 180, 'text' => 'Duración: {horas}  •  Ciudad: {ciudad}  •  Fecha: {fecha_emision_larga}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#78716c', 'textAlign' => 'center' ],
            [ 'type' => 'line', 'left' => 120, 'top' => 895, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#44403c', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 110, 'top' => 905, 'width' => 240, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#292524', 'textAlign' => 'center' ],
            [ 'type' => 'line', 'left' => 454, 'top' => 895, 'x1' => 0, 'y1' => 0, 'x2' => 220, 'y2' => 0, 'stroke' => '#44403c', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 444, 'top' => 905, 'width' => 240, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#292524', 'textAlign' => 'center' ],
            [ 'type' => 'rect', 'left' => 352, 'top' => 965, 'width' => 90, 'height' => 90, 'fill' => '#ffffff', 'stroke' => '#d97706', 'strokeWidth' => 1, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 247, 'top' => 1062, 'width' => 300, 'text' => 'Folio: {folio}', 'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#78716c', 'textAlign' => 'center' ],
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

    // ─── Reconocimiento Honorífico (Carta Horizontal) ─────────────
    private static function preset_reconocimiento_honorifico(): array {
        $w = 1056;
        $h = 816;

        $objects = [
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => 190, 'height' => $h, 'fill' => '#581c87', 'stroke' => null ],
            [ 'type' => 'rect', 'left' => 190, 'top' => 0, 'width' => 12, 'height' => $h, 'fill' => '#f59e0b', 'stroke' => null ],
            [ 'type' => 'circle', 'left' => 52, 'top' => 70, 'radius' => 42, 'fill' => '#f59e0b', 'stroke' => '#fef3c7', 'strokeWidth' => 3 ],
            [ 'type' => 'textbox', 'left' => 20, 'top' => 180, 'width' => 150, 'text' => "MENCIÓN\nDE HONOR\n{anio_actual}", 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#fef3c7', 'textAlign' => 'center' ],
            [ 'type' => 'rect', 'left' => 45, 'top' => 620, 'width' => 100, 'height' => 100, 'fill' => '#ffffff', 'stroke' => '#f59e0b', 'strokeWidth' => 2, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
            [ 'type' => 'textbox', 'left' => 20, 'top' => 732, 'width' => 150, 'text' => '{folio}', 'fontSize' => 11, 'fontFamily' => 'Courier New', 'fill' => '#e9d5ff', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 86, 'width' => 730, 'text' => '{organizacion}', 'fontSize' => 16, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#6b21a8', 'textAlign' => 'left', 'charSpacing' => 150 ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 128, 'width' => 730, 'text' => 'RECONOCIMIENTO ESPECIAL', 'fontSize' => 38, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#1e1b4b', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 210, 'width' => 730, 'text' => 'Concedido con gratitud y distinción a:', 'fontSize' => 17, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 258, 'width' => 740, 'text' => '{nombre_estudiante}', 'fontSize' => 46, 'fontFamily' => 'Playfair Display', 'fontWeight' => 'bold', 'fill' => '#581c87', 'textAlign' => 'left' ],
            [ 'type' => 'rect', 'left' => 255, 'top' => 324, 'width' => 480, 'height' => 2, 'fill' => '#e2e8f0', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 360, 'width' => 730, 'text' => '{curso}', 'fontSize' => 26, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 414, 'width' => 730, 'text' => '{descripcion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#475569', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 495, 'width' => 730, 'text' => 'Categoría: {categoria}  •  Otorgado el {fecha_emision_larga}', 'fontSize' => 14, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'left' ],
            [ 'type' => 'line', 'left' => 255, 'top' => 675, 'x1' => 0, 'y1' => 0, 'x2' => 230, 'y2' => 0, 'stroke' => '#581c87', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 255, 'top' => 686, 'width' => 240, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'left' ],
            [ 'type' => 'line', 'left' => 600, 'top' => 675, 'x1' => 0, 'y1' => 0, 'x2' => 230, 'y2' => 0, 'stroke' => '#581c87', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 600, 'top' => 686, 'width' => 240, 'text' => "{firmante_2_nombre}\n{firmante_2_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'left' ],
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

    // ─── Constancia de Taller / Seminario ────────────────────────
    private static function preset_constancia_taller(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            [ 'type' => 'rect', 'left' => 36, 'top' => 36, 'width' => $w - 72, 'height' => $h - 72, 'fill' => '#ffffff', 'stroke' => '#0284c7', 'strokeWidth' => 2, 'rx' => 16, 'ry' => 16 ],
            [ 'type' => 'rect', 'left' => 36, 'top' => 36, 'width' => $w - 72, 'height' => 110, 'fill' => '#0284c7', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 76, 'top' => 64, 'width' => 650, 'text' => 'CONSTANCIA DE PARTICIPACIÓN', 'fontSize' => 30, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 76, 'top' => 104, 'width' => 650, 'text' => '{organizacion}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#e0f2fe', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 780, 'top' => 78, 'width' => 260, 'text' => 'FOLIO: {folio}', 'fontSize' => 14, 'fontFamily' => 'Courier New', 'fontWeight' => 'bold', 'fill' => '#ffffff', 'textAlign' => 'right' ],
            [ 'type' => 'textbox', 'left' => 86, 'top' => 205, 'width' => 950, 'text' => 'Se extiende la presente constancia a:', 'fontSize' => 18, 'fontFamily' => 'Lato', 'fill' => '#475569', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 86, 'top' => 255, 'width' => 950, 'text' => '{nombre_estudiante}', 'fontSize' => 44, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0369a1', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 120, 'top' => 332, 'width' => 882, 'text' => 'Por su destacada participación en el taller / evento:', 'fontSize' => 17, 'fontFamily' => 'Lato', 'fill' => '#334155', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 90, 'top' => 372, 'width' => 942, 'text' => '{curso}', 'fontSize' => 30, 'fontFamily' => 'Montserrat', 'fontWeight' => 'bold', 'fill' => '#0f172a', 'textAlign' => 'center' ],
            [ 'type' => 'textbox', 'left' => 140, 'top' => 432, 'width' => 842, 'text' => 'Duración: {horas}  •  Fecha: {fecha_emision_larga}  •  {ciudad}', 'fontSize' => 15, 'fontFamily' => 'Lato', 'fill' => '#64748b', 'textAlign' => 'center' ],
            [ 'type' => 'line', 'left' => 190, 'top' => 625, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0, 'stroke' => '#0284c7', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 180, 'top' => 636, 'width' => 260, 'text' => "{instructor}\nInstructor / Facilitador", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],
            [ 'type' => 'line', 'left' => 540, 'top' => 625, 'x1' => 0, 'y1' => 0, 'x2' => 240, 'y2' => 0, 'stroke' => '#0284c7', 'strokeWidth' => 1.5 ],
            [ 'type' => 'textbox', 'left' => 530, 'top' => 636, 'width' => 260, 'text' => "{firmante_1_nombre}\n{firmante_1_cargo}", 'fontSize' => 13, 'fontFamily' => 'Lato', 'fill' => '#1e293b', 'textAlign' => 'center' ],
            [ 'type' => 'rect', 'left' => 925, 'top' => 565, 'width' => 95, 'height' => 95, 'fill' => '#f8fafc', 'stroke' => '#0284c7', 'strokeWidth' => 1.5, 'name' => 'qr_placeholder', 'auraElementType' => 'qr' ],
        ];

        return [
            'name'        => __( 'Constancia de Taller / Seminario', 'aura-suite' ),
            'orientation' => 'landscape',
            'accent'      => '#0284c7',
            'description' => __( 'Tarjeta moderna azul cielo para eventos y talleres', 'aura-suite' ),
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#f1f5f9',
                'objects'    => $objects,
            ],
        ];
    }

    // ─── Institucional 4 Firmas (A4 Landscape) ────────────────────
    private static function preset_institucional_4_firmas(): array {
        $w = 1122;
        $h = 794;

        $objects = [
            [ 'type' => 'rect', 'left' => 24, 'top' => 24, 'width' => $w - 48, 'height' => $h - 48, 'fill' => 'transparent', 'stroke' => '#1e293b', 'strokeWidth' => 3 ],
            [ 'type' => 'rect', 'left' => 34, 'top' => 34, 'width' => $w - 68, 'height' => $h - 68, 'fill' => 'transparent', 'stroke' => '#b45309', 'strokeWidth' => 1.5 ],
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
        ];
    }
}
