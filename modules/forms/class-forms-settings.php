<?php
/**
 * Settings del Módulo de Formularios — Configuración global
 *
 * Gestiona las opciones de configuración del módulo almacenadas en wp_options:
 *  - Página de formulario público
 *  - Página del portal de formularios
 *  - Habilitar honeypot anti-spam
 *  - Máximo de envíos por IP / ventana de tiempo
 *  - Email de notificación al admin por defecto
 *  - Texto del correo de asignación de encuesta
 *
 * AJAX actions registradas:
 *  - aura_forms_save_settings — Guardar configuración
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Settings {

    const OPTION_KEY = 'aura_forms_settings';

    public static function init(): void {
        add_action( 'wp_ajax_aura_forms_save_settings',   [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_aura_forms_get_settings',    [ __CLASS__, 'ajax_get_settings' ] );
        add_action( 'wp_ajax_aura_forms_flush_rewrites',  [ __CLASS__, 'ajax_flush_rewrites' ] );
    }

    /**
     * Callback de menú — renderiza la página de configuración.
     */
    public static function render(): void {
        include AURA_PLUGIN_DIR . 'templates/forms/settings.php';
    }

    /**
     * Devuelve el valor de una opción de configuración o su valor por defecto.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get( string $key, $default = '' ) {
        $settings = get_option( self::OPTION_KEY, [] );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Defaults del módulo.
     */
    public static function defaults(): array {
        return [
            'public_form_page'        => '',
            'portal_page'             => '',
            'honeypot_enabled'        => '1',
            'rate_limit_count'        => '5',
            'rate_limit_minutes'      => '10',
            'admin_notification_email' => get_option( 'admin_email', '' ),
            'assignment_email_subject' => __( 'Tienes una encuesta pendiente', 'aura-suite' ),
            'assignment_email_body'    => __( "Hola {nombre},\n\nSe te ha asignado una nueva encuesta: {formulario}.\n\nPuedes completarla aquí: {url}\n\nFecha límite: {expira}", 'aura-suite' ),
        ];
    }

    /**
     * Guardar configuración vía AJAX.
     */
    public static function ajax_save_settings(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'No tienes permiso para realizar esta acción.', 'aura-suite' ) );
        }

        $current  = get_option( self::OPTION_KEY, [] );
        $defaults = self::defaults();

        $updated = [
            'public_form_page'         => absint( $_POST['public_form_page'] ?? 0 ),
            'portal_page'              => absint( $_POST['portal_page'] ?? 0 ),
            'honeypot_enabled'         => isset( $_POST['honeypot_enabled'] ) ? '1' : '0',
            'rate_limit_count'         => absint( $_POST['rate_limit_count'] ?? 5 ),
            'rate_limit_minutes'       => absint( $_POST['rate_limit_minutes'] ?? 10 ),
            'admin_notification_email' => sanitize_email( $_POST['admin_notification_email'] ?? '' ),
            'assignment_email_subject' => sanitize_text_field( $_POST['assignment_email_subject'] ?? '' ),
            'assignment_email_body'    => wp_kses_post( $_POST['assignment_email_body'] ?? '' ),
        ];

        update_option( self::OPTION_KEY, array_merge( $defaults, $current, $updated ) );

        wp_send_json_success( __( 'Configuración guardada correctamente.', 'aura-suite' ) );
    }

    /**
     * Obtener configuración vía AJAX.
     */
    public static function ajax_get_settings(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'No tienes permiso para realizar esta acción.', 'aura-suite' ) );
        }

        $settings = array_merge(
            self::defaults(),
            get_option( self::OPTION_KEY, [] )
        );

        wp_send_json_success( $settings );
    }

    /**
     * Regenerar las rewrite rules de formularios (URL amigable /formulario/{slug}/).
     * Registra el query var + la regla y hace hard flush (actualiza .htaccess en Apache).
     */
    public static function ajax_flush_rewrites(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'No tienes permiso para realizar esta acción.', 'aura-suite' ) );
        }

        // Forzar registro de query var y regla antes de flushear
        if ( method_exists( 'Aura_Forms_Setup', 'register_rewrite_rules' ) ) {
            Aura_Forms_Setup::register_rewrite_rules();
        }
        // Hard flush: actualiza tanto la opción de BD como .htaccess (Apache/Nginx via WP)
        flush_rewrite_rules( true );
        // Actualiza el flag para que maybe_flush_rewrite no duplique el proceso
        update_option( 'aura_forms_rewrite_version', Aura_Forms_Setup::DB_VERSION );

        wp_send_json_success( __( 'URLs regeneradas correctamente. Visita /formulario/prueba/ para verificar.', 'aura-suite' ) );
    }
}
