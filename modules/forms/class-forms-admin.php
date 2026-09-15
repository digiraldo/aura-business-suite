<?php
/**
 * Admin del Módulo de Formularios y Encuestas
 *
 * Responsabilidades:
 *  - Registrar los 8 menús de WordPress Admin del módulo
 *  - Encolar CSS y JS del módulo exclusivamente en páginas de formularios
 *  - SortableJS solo en la página del builder de formularios
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Admin {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ], 18 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // MENÚS
    // ─────────────────────────────────────────────────────────────

    public static function register_menus(): void {
        $has_access = (
            current_user_can( 'aura_forms_view_responses_all' ) ||
            current_user_can( 'aura_forms_create' )             ||
            current_user_can( 'aura_forms_enrollment_review' )  ||
            current_user_can( 'manage_options' )
        );

        if ( ! $has_access ) {
            return;
        }

        // ── Menú principal ────────────────────────────────────────
        add_menu_page(
            __( 'Formularios — AURA', 'aura-suite' ),
            __( 'Formularios', 'aura-suite' ),
            'read',
            'aura-forms',
            [ __CLASS__, 'render_dashboard' ],
            'dashicons-feedback',
            3.5
        );

        // Dashboard (duplicado del principal para el primer submenú)
        add_submenu_page(
            'aura-forms',
            __( 'Dashboard Formularios', 'aura-suite' ),
            __( 'Dashboard', 'aura-suite' ),
            'read',
            'aura-forms',
            [ __CLASS__, 'render_dashboard' ]
        );

        // Todos los formularios
        if ( current_user_can( 'aura_forms_view_responses_all' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-forms',
                __( 'Todos los Formularios', 'aura-suite' ),
                __( 'Todos los Formularios', 'aura-suite' ),
                'read',
                'aura-forms-list',
                [ __CLASS__, 'render_list' ]
            );
        }

        // Nuevo formulario
        if ( current_user_can( 'aura_forms_create' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-forms',
                __( 'Nuevo Formulario', 'aura-suite' ),
                __( 'Nuevo Formulario', 'aura-suite' ),
                'read',
                'aura-forms-new',
                [ __CLASS__, 'render_builder' ]
            );
        }

        // Postulantes (Inscripciones)
        if ( current_user_can( 'aura_forms_enrollment_review' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-forms',
                __( 'Postulantes (Inscripciones)', 'aura-suite' ),
                __( 'Postulantes', 'aura-suite' ),
                'read',
                'aura-forms-enrollments',
                [ __CLASS__, 'render_enrollments' ]
            );
        }

        // Encuestas Asignadas
        if ( current_user_can( 'aura_forms_assign' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-forms',
                __( 'Encuestas Asignadas', 'aura-suite' ),
                __( 'Encuestas Asignadas', 'aura-suite' ),
                'read',
                'aura-forms-assignments',
                [ __CLASS__, 'render_assignments' ]
            );
        }

        // Análisis de Respuestas
        if ( current_user_can( 'aura_forms_analytics' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-forms',
                __( 'Análisis de Respuestas', 'aura-suite' ),
                __( 'Análisis', 'aura-suite' ),
                'read',
                'aura-forms-analytics',
                [ __CLASS__, 'render_analytics' ]
            );
        }

        // Reportes
        if ( current_user_can( 'aura_forms_reports' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-forms',
                __( 'Reportes de Formularios', 'aura-suite' ),
                __( 'Reportes', 'aura-suite' ),
                'read',
                'aura-forms-reports',
                [ 'Aura_Forms_Reports', 'render' ]
            );
        }

        // Configuración
        if ( current_user_can( 'aura_forms_settings' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-forms',
                __( 'Configuración Formularios', 'aura-suite' ),
                __( 'Configuración', 'aura-suite' ),
                'read',
                'aura-forms-settings',
                [ 'Aura_Forms_Settings', 'render' ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDERS (callbacks de menú)
    // ─────────────────────────────────────────────────────────────

    public static function render_dashboard(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/dashboard.php';
    }

    public static function render_list(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/list.php';
    }

    public static function render_builder(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/builder.php';
    }

    public static function render_enrollments(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/enrollments-pending.php';
    }

    public static function render_assignments(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/assignments.php';
    }

    public static function render_analytics(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/analytics.php';
    }

    // ─────────────────────────────────────────────────────────────
    // ASSETS
    // ─────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        // Hooks de todas las páginas del módulo de formularios
        $forms_hooks = [
            'toplevel_page_aura-forms',
            'formularios_page_aura-forms-list',
            'formularios_page_aura-forms-new',
            'formularios_page_aura-forms-enrollments',
            'formularios_page_aura-forms-assignments',
            'formularios_page_aura-forms-analytics',
            'formularios_page_aura-forms-reports',
            'formularios_page_aura-forms-settings',
        ];

        // También cargar al editar un formulario existente (parámetro action=edit)
        $is_forms_page = in_array( $hook, $forms_hooks, true );

        if ( ! $is_forms_page ) {
            return;
        }

        $ver = AURA_VERSION;

        // CSS del módulo
        wp_enqueue_style(
            'aura-forms-admin',
            AURA_PLUGIN_URL . 'assets/css/forms-admin.css',
            [ 'aura-admin-styles' ],
            $ver
        );

        // Determinar si estamos en el builder (página new o list con action=edit)
        $is_builder = (
            $hook === 'formularios_page_aura-forms-new' ||
            ( $hook === 'formularios_page_aura-forms-list' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' )
        );

        if ( $is_builder ) {
            // Media Library (necesario para wp.media en el builder)
            wp_enqueue_media();

            // SortableJS para drag & drop del builder
            $sortable_local = AURA_PLUGIN_DIR . 'assets/js/vendor/sortable.min.js';
            if ( file_exists( $sortable_local ) ) {
                wp_enqueue_script( 'sortablejs', AURA_PLUGIN_URL . 'assets/js/vendor/sortable.min.js', [], '1.15.3', true );
            } else {
                wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js', [], '1.15.3', true );
            }

            wp_enqueue_script(
                'aura-forms-builder',
                AURA_PLUGIN_URL . 'assets/js/forms-builder.js',
                [ 'jquery', 'sortablejs' ],
                $ver,
                true
            );

            wp_localize_script( 'aura-forms-builder', 'auraFormsBuilder', self::localize_builder_data() );
        }

        // JS general del módulo (en todas las páginas del módulo menos el builder en sí)
        wp_enqueue_script(
            'aura-forms-admin',
            AURA_PLUGIN_URL . 'assets/js/forms-admin.js',
            [ 'jquery' ],
            $ver,
            true
        );

        // QRCode.js — solo en el listado de formularios (para botón de compartir QR)
        if ( $hook === 'formularios_page_aura-forms-list' && ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) ) {
            wp_enqueue_script(
                'qrcodejs',
                'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
                [],
                '1.0.0',
                true
            );
        }

        wp_localize_script( 'aura-forms-admin', 'auraFormsAdmin', self::localize_data() );

        // Chart.js solo en las páginas de analytics y reportes
        if ( $hook === 'formularios_page_aura-forms-analytics' || $hook === 'formularios_page_aura-forms-reports' ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
                [],
                '4.4.4',
                true
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DATOS LOCALIZADOS PARA JS
    // ─────────────────────────────────────────────────────────────

    private static function localize_data(): array {
        return [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'aura_forms_nonce' ),
            'siteUrl'   => site_url(),
            'pluginUrl' => AURA_PLUGIN_URL,
            'i18n'      => [
                'confirmDelete'     => __( '¿Eliminar este formulario? Esta acción no se puede deshacer.', 'aura-suite' ),
                'confirmDeleteField' => __( '¿Eliminar este campo?', 'aura-suite' ),
                'saved'             => __( 'Guardado correctamente.', 'aura-suite' ),
                'error'             => __( 'Error al guardar. Intenta nuevamente.', 'aura-suite' ),
                'loading'           => __( 'Cargando…', 'aura-suite' ),
                'noForms'           => __( 'No hay formularios todavía.', 'aura-suite' ),
                'copied'            => __( 'URL copiada al portapapeles.', 'aura-suite' ),
            ],
        ];
    }

    private static function localize_builder_data(): array {
        $base = self::localize_data();

        // Campos tipo para el panel de la paleta del builder
        $base['fieldTypes'] = [
            [ 'type' => 'text',             'label' => __( 'Texto Corto',                    'aura-suite' ), 'icon' => 'dashicons-editor-textcolor',  'group' => 'basic' ],
            [ 'type' => 'textarea',         'label' => __( 'Párrafo',                        'aura-suite' ), 'icon' => 'dashicons-text-page',          'group' => 'basic' ],
            [ 'type' => 'email',            'label' => __( 'Correo Electrónico',             'aura-suite' ), 'icon' => 'dashicons-email-alt',          'group' => 'basic' ],
            [ 'type' => 'tel',              'label' => __( 'Número de Teléfono',             'aura-suite' ), 'icon' => 'dashicons-phone',              'group' => 'basic' ],
            [ 'type' => 'number',           'label' => __( 'Número',                         'aura-suite' ), 'icon' => 'dashicons-calculator',         'group' => 'basic' ],
            [ 'type' => 'date',             'label' => __( 'Fecha',                          'aura-suite' ), 'icon' => 'dashicons-calendar-alt',       'group' => 'basic' ],
            [ 'type' => 'time',             'label' => __( 'Hora',                           'aura-suite' ), 'icon' => 'dashicons-clock',              'group' => 'basic' ],
            [ 'type' => 'birthdate',        'label' => __( 'Fecha de Nacimiento',            'aura-suite' ), 'icon' => 'dashicons-universal-access',   'group' => 'basic' ],
            [ 'type' => 'radio',            'label' => __( 'Opción Múltiple (única)',         'aura-suite' ), 'icon' => 'dashicons-controls-play',      'group' => 'choice' ],
            [ 'type' => 'checkbox',         'label' => __( 'Casillas (múltiple)',             'aura-suite' ), 'icon' => 'dashicons-yes-alt',            'group' => 'choice' ],
            [ 'type' => 'select',           'label' => __( 'Desplegable',                    'aura-suite' ), 'icon' => 'dashicons-arrow-down-alt2',    'group' => 'choice' ],
            [ 'type' => 'scale',            'label' => __( 'Escala (NPS / Likert)',           'aura-suite' ), 'icon' => 'dashicons-star-filled',        'group' => 'choice' ],
            [ 'type' => 'file',             'label' => __( 'Cargar Documento',               'aura-suite' ), 'icon' => 'dashicons-upload',             'group' => 'media' ],
            [ 'type' => 'image',            'label' => __( 'Imagen',                         'aura-suite' ), 'icon' => 'dashicons-format-image',       'group' => 'media' ],
            [ 'type' => 'downloadable',     'label' => __( 'Descargar Documento',            'aura-suite' ), 'icon' => 'dashicons-download',           'group' => 'media' ],
            [ 'type' => 'terms',            'label' => __( 'Aceptación de Términos',         'aura-suite' ), 'icon' => 'dashicons-shield',             'group' => 'legal' ],
            [ 'type' => 'accept_only_terms','label' => __( 'Términos (Solo Aceptar)',        'aura-suite' ), 'icon' => 'dashicons-shield-alt',         'group' => 'legal' ],
            [ 'type' => 'hidden',           'label' => __( 'Campo Oculto',                   'aura-suite' ), 'icon' => 'dashicons-hidden',             'group' => 'advanced' ],
            [ 'type' => 'section_title',    'label' => __( 'Título de Sección',              'aura-suite' ), 'icon' => 'dashicons-editor-bold',        'group' => 'layout' ],
            [ 'type' => 'paragraph',        'label' => __( 'Texto Explicativo',              'aura-suite' ), 'icon' => 'dashicons-editor-alignleft',   'group' => 'layout' ],
        ];

        // Claves de mapeo disponibles para formularios tipo enrollment
        $base['mappingKeys'] = [
            ''           => __( '— Sin mapeo —',              'aura-suite' ),
            'first_name' => __( 'Nombre',                    'aura-suite' ),
            'last_name'  => __( 'Apellido',                  'aura-suite' ),
            'email'      => __( 'Correo electrónico',        'aura-suite' ),
            'phone'      => __( 'Teléfono',                  'aura-suite' ),
            'id_number'  => __( 'Número de identificación',  'aura-suite' ),
            'birthdate'  => __( 'Fecha de nacimiento',       'aura-suite' ),
            'address'    => __( 'Dirección',                 'aura-suite' ),
            'area_id'    => __( 'Área / Programa',           'aura-suite' ),
            'course_id'  => __( 'Curso específico',          'aura-suite' ),
            'notes'      => __( 'Observaciones',             'aura-suite' ),
        ];

        $base['formId'] = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        return $base;
    }
}
