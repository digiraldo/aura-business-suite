<?php
/**
 * Clase: Configuración del Módulo Estudiantes (Fase 11)
 *
 * Almacena todas las opciones en un único registro de WordPress Options
 * bajo la clave `aura_students_settings` (array serializado).
 *
 * @package AuraBusinessSuite
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Settings {

    /** Clave de la opción en wp_options */
    const OPTION_KEY = 'aura_students_settings';

    // ─────────────────────────────────────────────────────────────
    // BOOTSTRAP
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_save_settings',          [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_aura_students_create_portal_pages',   [ __CLASS__, 'ajax_create_portal_pages' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER (invocado desde el menú de admin)
    // ─────────────────────────────────────────────────────────────

    public static function render(): void {
        if ( ! current_user_can( 'aura_students_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
        }
        require_once AURA_PLUGIN_DIR . 'templates/students/settings.php';
    }

    // ─────────────────────────────────────────────────────────────
    // ACCESO A CONFIGURACIÓN (estático, para uso en otros módulos)
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene una clave específica de la configuración.
     *
     * @param  string $key     Nombre de la clave.
     * @param  mixed  $default Valor por defecto (si la clave no existe).
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        $all = self::get_all();
        if ( array_key_exists( $key, $all ) ) {
            return $all[ $key ];
        }
        return $default ?? ( self::defaults()[ $key ] ?? null );
    }

    /**
     * Retorna todas las configuraciones fusionadas con los valores por defecto.
     *
     * @return array
     */
    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
    }

    /**
     * Valores por defecto del módulo.
     *
     * @return array
     */
    public static function defaults(): array {
        return [
            'student_code_prefix'          => 'CEM-EST',
            'default_currency'             => 'USD',
            'portal_page_id'               => 0,
            'login_page_id'                => 0,
            'enrollment_page_id'           => 0,
            'enrollment_form_fields'       => [
                'phone', 'whatsapp', 'address', 'birth_date',
                'document_type', 'document_number',
                'profile_type', 'preferred_areas',
                'emergency_contact', 'how_did_you_hear', 'notes',
            ],
            'auto_generate_password'       => true,
            'send_credentials_email'       => true,
            'finance_integration_enabled'  => true,
            'reminder_days_before'         => 3,
            'overdue_alert_enabled'        => true,
        ];
    }

    /**
     * Diccionario de campos disponibles para el formulario público de inscripción.
     *
     * @return array<string, string>  [ 'field_key' => 'Etiqueta legible' ]
     */
    public static function available_form_fields(): array {
        return [
            'phone'             => __( 'Teléfono', 'aura-suite' ),
            'whatsapp'          => __( 'WhatsApp', 'aura-suite' ),
            'address'           => __( 'Dirección', 'aura-suite' ),
            'birth_date'        => __( 'Fecha de nacimiento', 'aura-suite' ),
            'document_type'     => __( 'Tipo de documento', 'aura-suite' ),
            'document_number'   => __( 'Número de documento', 'aura-suite' ),
            'profile_type'      => __( 'Tipo de perfil (interno / externo)', 'aura-suite' ),
            'preferred_areas'   => __( 'Áreas de interés preferidas', 'aura-suite' ),
            'emergency_contact' => __( 'Contacto de emergencia', 'aura-suite' ),
            'how_did_you_hear'  => __( 'Cómo se enteró del curso', 'aura-suite' ),
            'notes'             => __( 'Notas / Observaciones', 'aura-suite' ),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: GUARDAR
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ], 403 );
        }

        $cleaned = self::sanitize( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
        update_option( self::OPTION_KEY, $cleaned );

        wp_send_json_success( [ 'message' => __( 'Configuración guardada correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: CREAR / VINCULAR PÁGINAS DEL PORTAL AUTOMÁTICAMENTE
    // ─────────────────────────────────────────────────────────────

    public static function ajax_create_portal_pages(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ], 403 );
        }

        $pages_def = [
            'login_page_id' => [
                'title'     => __( 'Acceso a la Plataforma', 'aura-suite' ),
                'slug'      => 'acceso',
                'shortcode' => '[aura_login]',
            ],
            'portal_page_id' => [
                'title'     => __( 'Portal del Estudiante', 'aura-suite' ),
                'slug'      => 'portal-estudiante',
                'shortcode' => '[aura_portal]',
            ],
            'enrollment_page_id' => [
                'title'     => __( 'Inscripción a Programas', 'aura-suite' ),
                'slug'      => 'inscripcion-estudiantes',
                'shortcode' => '[aura_enrollment_form]',
            ],
        ];

        $settings = self::get_all();
        $results  = [];

        foreach ( $pages_def as $key => $pinfo ) {
            $page_id = (int) ( $settings[ $key ] ?? 0 );
            $post    = $page_id ? get_post( $page_id ) : null;

            // Si ya está asignada una página existente y publicada, la mantenemos
            if ( $post && 'publish' === $post->post_status ) {
                $results[ $key ] = [
                    'id'     => $post->ID,
                    'title'  => $post->post_title,
                    'url'    => get_permalink( $post->ID ),
                    'status' => 'existing',
                ];
                continue;
            }

            // Buscar si ya existe alguna página publicada con ese slug
            $found_id = 0;
            $existing_page = get_page_by_path( $pinfo['slug'] );
            if ( $existing_page && 'publish' === $existing_page->post_status ) {
                $found_id = $existing_page->ID;
            } else {
                // Buscar por contenido que tenga el shortcode
                global $wpdb;
                $found_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like( $pinfo['shortcode'] ) . '%'
                ) );
            }

            if ( $found_id ) {
                $settings[ $key ] = $found_id;
                $results[ $key ]  = [
                    'id'     => $found_id,
                    'title'  => get_the_title( $found_id ),
                    'url'    => get_permalink( $found_id ),
                    'status' => 'linked',
                ];
            } else {
                // Crear la página
                $new_id = wp_insert_post( [
                    'post_title'   => $pinfo['title'],
                    'post_name'    => $pinfo['slug'],
                    'post_content' => $pinfo['shortcode'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ] );

                if ( $new_id && ! is_wp_error( $new_id ) ) {
                    $settings[ $key ] = $new_id;
                    $results[ $key ]  = [
                        'id'     => $new_id,
                        'title'  => $pinfo['title'],
                        'url'    => get_permalink( $new_id ),
                        'status' => 'created',
                    ];
                }
            }
        }

        update_option( self::OPTION_KEY, $settings );

        wp_send_json_success( [
            'message' => __( 'Páginas del portal vinculadas correctamente.', 'aura-suite' ),
            'pages'   => $results,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // SANITIZACIÓN
    // ─────────────────────────────────────────────────────────────

    private static function sanitize( array $raw ): array {
        $defs = self::defaults();

        $settings = [
            'student_code_prefix'          => strtoupper( sanitize_text_field( $raw['student_code_prefix'] ?? $defs['student_code_prefix'] ) ),
            'default_currency'             => strtoupper( sanitize_text_field( $raw['default_currency']    ?? $defs['default_currency'] ) ),
            'portal_page_id'               => absint( $raw['portal_page_id']      ?? 0 ),
            'login_page_id'                => absint( $raw['login_page_id']       ?? 0 ),
            'enrollment_page_id'           => absint( $raw['enrollment_page_id']  ?? 0 ),
            'auto_generate_password'       => ! empty( $raw['auto_generate_password'] ),
            'send_credentials_email'       => ! empty( $raw['send_credentials_email'] ),
            'finance_integration_enabled'  => ! empty( $raw['finance_integration_enabled'] ),
            'overdue_alert_enabled'        => ! empty( $raw['overdue_alert_enabled'] ),
            'reminder_days_before'         => max( 1, min( 30, absint( $raw['reminder_days_before'] ?? $defs['reminder_days_before'] ) ) ),
        ];

        // enrollment_form_fields: filtrar solo claves permitidas
        $allowed = array_keys( self::available_form_fields() );
        if ( isset( $raw['enrollment_form_fields'] ) && is_array( $raw['enrollment_form_fields'] ) ) {
            $settings['enrollment_form_fields'] = array_values(
                array_intersect(
                    array_map( 'sanitize_key', $raw['enrollment_form_fields'] ),
                    $allowed
                )
            );
        } else {
            // Si no se envía ningún campo marcado, guardar array vacío (todos ocultos)
            $settings['enrollment_form_fields'] = [];
        }

        return $settings;
    }
}
