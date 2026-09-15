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
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ], 16 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
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
        include AURA_PLUGIN_DIR . 'templates/certificates/list.php';
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

        // También puede estar bajo el menú "Estudiantes" cuando se lanza el modal de emisión
        $is_cert_page    = in_array( $hook, $cert_hooks, true );
        $is_student_page = str_starts_with( $hook, 'estudiantes_page_aura-students' ) ||
                           $hook === 'toplevel_page_aura-students';

        if ( ! $is_cert_page && ! $is_student_page ) {
            return;
        }

        // Media uploader (necesario para wp.media en la página de ajustes)
        if ( $hook === 'certificados_page_aura-certificates-settings' ) {
            wp_enqueue_media();
        }

        $ver = AURA_VERSION;

        wp_enqueue_style(
            'aura-certificates',
            AURA_PLUGIN_URL . 'assets/css/certificates.css',
            [ 'aura-admin-styles' ],
            $ver
        );

        // JS principal del módulo — NO cargar en el builder (tiene su propio JS)
        $is_builder = $is_cert_page &&
                      $hook === 'certificados_page_aura-certificates-templates' &&
                      isset( $_GET['action'] ) && $_GET['action'] === 'edit';

        if ( ! $is_builder ) {
            wp_enqueue_script(
                'aura-certificates',
                AURA_PLUGIN_URL . 'assets/js/certificates.js',
                [ 'jquery' ],
                $ver,
                true
            );
            wp_localize_script( 'aura-certificates', 'auraCertificates', self::localize_data() );
        }

        // Fabric.js + builder JS — solo en la página del editor de plantillas
        if ( $is_builder ) {
            // Google Fonts para el editor
            wp_enqueue_style(
                'aura-cert-google-fonts',
                'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;700&family=Lato:wght@300;400;700&family=Great+Vibes&family=Roboto:wght@300;400;700&family=Open+Sans:wght@300;400;700&family=Raleway:wght@300;400;700&family=Merriweather:wght@300;400;700&family=Cinzel:wght@400;700&family=Dancing+Script:wght@400;700&display=swap',
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

            wp_enqueue_script(
                'aura-certificate-builder',
                AURA_PLUGIN_URL . 'assets/js/certificate-builder.js',
                [ 'jquery', 'fabric-js' ],
                $ver,
                true
            );

            wp_localize_script( 'aura-certificate-builder', 'auraCertBuilder', array_merge(
                self::localize_data(),
                [
                    'templateId'      => absint( $_GET['id'] ?? 0 ),
                    'dynamicVars'     => Aura_Certificates_Templates::get_dynamic_variables_list(),
                    'signers'         => Aura_Certificates_Signers::get_active_signers_for_js(),
                    'prebuiltDesigns' => self::get_prebuilt_designs(),
                ]
            ) );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DATOS LOCALIZADOS PARA JS
    // ─────────────────────────────────────────────────────────────

    public static function localize_data(): array {
        return [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'aura_certificates_nonce' ),
            'siteUrl'     => site_url(),
            'verifySlug'  => Aura_Certificates_Settings::get( 'verify_slug', 'verificar-certificado' ),
            'orgName'     => get_option( 'aura_org_name', get_bloginfo( 'name' ) ),
            'orgLogoUrl'  => get_option( 'aura_org_logo_url', '' ),
            'i18n'        => [
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
        ];
    }

    // ─── Diploma Clásico Dorado ───────────────────────────────────
    private static function preset_diploma_clasico_dorado(): array {
        $w = 1122;  // A4 landscape, 96 dpi
        $h = 794;

        $objects = [
            // ── Fondo crema ──────────────────────────────────────
            [ 'type' => 'rect', 'left' => 0, 'top' => 0,
              'width' => $w, 'height' => $h,
              'fill' => '#FFFDF0', 'stroke' => 'transparent', 'strokeWidth' => 0,
              'selectable' => false, 'evented' => false ],

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
              'name' => 'qr_placeholder' ],
            [ 'type' => 'textbox', 'left' => $w - 128, 'top' => $h - 80,
              'width' => 96, 'text' => 'QR',
              'fontSize' => 11, 'fontFamily' => 'Arial',
              'fill' => '#AAAAAA', 'textAlign' => 'center' ],
        ];

        return [
            'name'        => __( 'Diploma Clásico Dorado', 'aura-suite' ),
            'orientation' => 'landscape',
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
              'fontSize' => 80, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#111827', 'textAlign' => 'left' ],
            // Línea de acento
            [ 'type' => 'rect', 'left' => 60, 'top' => 175, 'width' => 120, 'height' => 5,
              'fill' => '#8b5cf6', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 200,
              'width' => $w - 100, 'text' => 'Se certifica que:',
              'fontSize' => 18, 'fontFamily' => 'Arial',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 235,
              'width' => $w - 100, 'text' => '{nombre_completo}',
              'fontSize' => 50, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#8b5cf6', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 310,
              'width' => $w - 100, 'text' => 'Ha completado exitosamente el curso:',
              'fontSize' => 17, 'fontFamily' => 'Arial',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 342,
              'width' => $w - 100, 'text' => '{curso}',
              'fontSize' => 40, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#111827', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 400,
              'width' => 600, 'text' => '{programa}',
              'fontSize' => 20, 'fontFamily' => 'Arial',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            // Footer
            [ 'type' => 'rect', 'left' => 60, 'top' => 700, 'width' => 500, 'height' => 1,
              'fill' => '#e5e7eb', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 710,
              'width' => 350, 'text' => '{organizacion}',
              'fontSize' => 17, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#374151', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 60, 'top' => 734,
              'width' => 350, 'text' => '{fecha_emision}',
              'fontSize' => 16, 'fontFamily' => 'Arial',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 650, 'top' => 710,
              'width' => 400, 'text' => 'Folio: {folio}',
              'fontSize' => 14, 'fontFamily' => 'Arial',
              'fill' => '#9ca3af', 'textAlign' => 'right' ],
        ];

        return [
            'name'        => __( 'Moderno Minimalista', 'aura-suite' ),
            'orientation' => 'landscape',
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
            // Fondo verde claro
            [ 'type' => 'rect', 'left' => 0, 'top' => 0, 'width' => $w, 'height' => $h,
              'fill' => '#f0fdf4', 'stroke' => null, 'selectable' => false, 'evented' => false ],
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
            // Footer
            [ 'type' => 'rect', 'left' => 61, 'top' => 700, 'width' => $w - 122, 'height' => 1,
              'fill' => '#86efac', 'stroke' => null ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 712,
              'width' => 350, 'text' => '{organizacion}',
              'fontSize' => 17, 'fontFamily' => 'Arial',
              'fontWeight' => 'bold', 'fill' => '#15803d', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 80, 'top' => 734,
              'width' => 350, 'text' => '{fecha_emision}',
              'fontSize' => 16, 'fontFamily' => 'Arial',
              'fill' => '#6b7280', 'textAlign' => 'left' ],
            [ 'type' => 'textbox', 'left' => 650, 'top' => 712,
              'width' => 400, 'text' => 'Folio: {folio}',
              'fontSize' => 14, 'fontFamily' => 'Arial',
              'fill' => '#86efac', 'textAlign' => 'right' ],
        ];

        return [
            'name'        => __( 'Certificado de Participación', 'aura-suite' ),
            'orientation' => 'landscape',
            'json'        => [
                'version'    => '5.3.1',
                'background' => '#f0fdf4',
                'objects'    => $objects,
            ],
        ];
    }
}
