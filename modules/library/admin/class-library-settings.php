<?php
/**
 * Configuración del Módulo de Biblioteca — Fase 8
 *
 * Gestiona las 14 opciones del módulo almacenadas en wp_options con prefijo
 * `aura_library_`. Provee endpoints AJAX para guardar y leer la configuración.
 *
 * @package Aura_Business_Suite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Settings {

    const NONCE = 'aura_library_nonce';

    /**
     * Mapa de opciones: clave => [tipo, default]
     * Tipos posibles: 'int', 'bool', 'float', 'string', 'email'
     *
     * @var array<string, array{0: string, 1: mixed}>
     */
    private const SCHEMA = [
        'aura_library_loan_days'               => [ 'int',    14        ],
        'aura_library_extension_days'          => [ 'int',    7         ],
        'aura_library_max_extensions'          => [ 'int',    2         ],
        'aura_library_fines_enabled'           => [ 'bool',   false     ],
        'aura_library_fine_per_day'            => [ 'float',  0.00      ],
        'aura_library_grace_days'              => [ 'int',    1         ],
        'aura_library_fine_max'                => [ 'float',  0.00      ],
        'aura_library_reservation_expire_days' => [ 'int',    2         ],
        'aura_library_email_alerts'            => [ 'bool',   true      ],
        'aura_library_email_extra'             => [ 'email',  ''        ],
        'aura_library_whatsapp_alerts'         => [ 'bool',   false     ],
        'aura_library_cron_hour'               => [ 'int',    8         ],
        'aura_library_fines_to_finance'        => [ 'bool',   false     ],
        'aura_library_paz_y_salvo'             => [ 'bool',   false     ],
    ];

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_library_settings_get',  [ __CLASS__, 'ajax_get' ] );
        add_action( 'wp_ajax_aura_library_settings_save', [ __CLASS__, 'ajax_save' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Obtener configuración actual
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if (
            ! current_user_can( 'aura_library_settings' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        wp_send_json_success( self::get_all() );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Guardar configuración (sección por sección)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if (
            ! current_user_can( 'aura_library_settings' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $saved   = [];
        $errors  = [];

        foreach ( self::SCHEMA as $key => [ $type, $default ] ) {
            // Solo procesar claves enviadas en el request
            $field = str_replace( 'aura_library_', '', $key );
            if ( ! array_key_exists( $field, $_POST ) ) {
                continue;
            }

            $raw   = $_POST[ $field ];
            $value = self::cast( $raw, $type );

            if ( $value === null ) {
                $errors[ $field ] = __( 'Valor no válido.', 'aura-business-suite' );
                continue;
            }

            // Validaciones adicionales
            if ( $key === 'aura_library_cron_hour' && ( $value < 0 || $value > 23 ) ) {
                $errors[ $field ] = __( 'La hora debe estar entre 0 y 23.', 'aura-business-suite' );
                continue;
            }
            if ( in_array( $key, [ 'aura_library_fine_per_day', 'aura_library_fine_max' ], true ) && $value < 0 ) {
                $errors[ $field ] = __( 'El valor no puede ser negativo.', 'aura-business-suite' );
                continue;
            }

            update_option( $key, $value );
            $saved[ $field ] = $value;
        }

        // Si cambió la hora del cron, re-programar
        if ( isset( $saved['cron_hour'] ) ) {
            if ( class_exists( 'Aura_Library_Cron' ) ) {
                Aura_Library_Cron::reschedule();
            }
        }

        // Auditar el cambio
        if ( ! empty( $saved ) && class_exists( 'Aura_Library_Setup' ) ) {
            Aura_Library_Setup::log( 'update_settings', 'settings', 0, [], $saved );
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'message' => __( 'Algunos valores no son válidos.', 'aura-business-suite' ), 'errors' => $errors ] );
        }

        wp_send_json_success( [
            'message' => __( 'Configuración guardada correctamente.', 'aura-business-suite' ),
            'saved'   => $saved,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PÚBLICOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener todas las opciones con sus valores actuales (o default).
     *
     * @return array<string, mixed>
     */
    public static function get_all(): array {
        $result = [];
        foreach ( self::SCHEMA as $key => [ $type, $default ] ) {
            $raw   = get_option( $key, $default );
            $field = str_replace( 'aura_library_', '', $key );
            $result[ $field ] = self::cast( $raw, $type ) ?? $default;
        }
        return $result;
    }

    /**
     * Obtener una opción individual.
     *
     * @param string $key  Clave completa con prefijo (ej. 'aura_library_loan_days')
     * @return mixed
     */
    public static function get( string $key ) {
        if ( ! isset( self::SCHEMA[ $key ] ) ) {
            return null;
        }
        [ $type, $default ] = self::SCHEMA[ $key ];
        return self::cast( get_option( $key, $default ), $type ) ?? $default;
    }

    // ─────────────────────────────────────────────────────────────
    // CAST INTERNO
    // ─────────────────────────────────────────────────────────────

    /**
     * Castear y sanitizar un valor según su tipo declarado.
     *
     * @param mixed  $value Valor crudo
     * @param string $type  'int'|'bool'|'float'|'string'|'email'
     * @return mixed|null Null si la validación falla para tipos estrictos
     */
    private static function cast( $value, string $type ) {
        switch ( $type ) {
            case 'int':
                return absint( $value );
            case 'bool':
                if ( is_bool( $value ) ) return $value;
                return in_array( $value, [ '1', 1, 'true', true, 'on', 'yes' ], true );
            case 'float':
                $v = (float) $value;
                return $v >= 0 ? round( $v, 2 ) : null;
            case 'email':
                $v = sanitize_email( $value );
                return $v;
            case 'string':
            default:
                return sanitize_text_field( $value );
        }
    }
}
