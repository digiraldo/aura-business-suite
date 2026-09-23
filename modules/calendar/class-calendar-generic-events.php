<?php
/**
 * Gestión de Eventos Genéricos y Rápidos — Módulo de Calendario y Horarios Académicos
 * Aura Business Suite
 *
 * Administra el catálogo de eventos rápidos (recesos, devocionales, actividades, comidas)
 * con duraciones predefinidas de 30 minutos (u otras personalizables), colores y tipos,
 * permitiendo su inserción ágil en el calendario con un solo clic y posterior edición libre.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Generic_Events {

    const OPTION_KEY = 'aura_cal_generic_events';

    /**
     * Inicializar hooks AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_generic_events',   [ __CLASS__, 'ajax_get_events' ] );
        add_action( 'wp_ajax_aura_cal_save_generic_event',   [ __CLASS__, 'ajax_save_event' ] );
        add_action( 'wp_ajax_aura_cal_delete_generic_event', [ __CLASS__, 'ajax_delete_event' ] );
        add_action( 'wp_ajax_aura_cal_reset_generic_events', [ __CLASS__, 'ajax_reset_events' ] );
    }

    /**
     * Catálogo base predeterminado de 11 eventos rápidos solicitados
     */
    public static function get_default_events(): array {
        return [
            [
                'id'       => 'descanso',
                'name'     => 'Descanso',
                'duration' => 30,
                'type'     => 'break',
                'color'    => '#10b981',
                'icon'     => '☕',
                'active'   => 1,
            ],
            [
                'id'       => 'introduccion',
                'name'     => 'Introducción',
                'duration' => 30,
                'type'     => 'activity',
                'color'    => '#6366f1',
                'icon'     => '🎙️',
                'active'   => 1,
            ],
            [
                'id'       => 'reflexion',
                'name'     => 'Reflexión',
                'duration' => 30,
                'type'     => 'activity',
                'color'    => '#8b5cf6',
                'icon'     => '🧘',
                'active'   => 1,
            ],
            [
                'id'       => 'deportes',
                'name'     => 'Deportes',
                'duration' => 30,
                'type'     => 'activity',
                'color'    => '#f59e0b',
                'icon'     => '⚽',
                'active'   => 1,
            ],
            [
                'id'       => 'lectura',
                'name'     => 'Lectura',
                'duration' => 30,
                'type'     => 'class',
                'color'    => '#3b82f6',
                'icon'     => '📖',
                'active'   => 1,
            ],
            [
                'id'       => 'trabajo',
                'name'     => 'Trabajo',
                'duration' => 30,
                'type'     => 'activity',
                'color'    => '#0284c7',
                'icon'     => '💼',
                'active'   => 1,
            ],
            [
                'id'       => 'refrigerio',
                'name'     => 'Refrigerio',
                'duration' => 30,
                'type'     => 'break',
                'color'    => '#14b8a6',
                'icon'     => '🥪',
                'active'   => 1,
            ],
            [
                'id'       => 'desayuno',
                'name'     => 'Desayuno',
                'duration' => 30,
                'type'     => 'break',
                'color'    => '#f97316',
                'icon'     => '🥞',
                'active'   => 1,
            ],
            [
                'id'       => 'almuerzo',
                'name'     => 'Almuerzo',
                'duration' => 30,
                'type'     => 'break',
                'color'    => '#ef4444',
                'icon'     => '🍲',
                'active'   => 1,
            ],
            [
                'id'       => 'comida',
                'name'     => 'Comida',
                'duration' => 30,
                'type'     => 'break',
                'color'    => '#ec4899',
                'icon'     => '🍽️',
                'active'   => 1,
            ],
            [
                'id'       => 'cena',
                'name'     => 'Cena',
                'duration' => 30,
                'type'     => 'break',
                'color'    => '#64748b',
                'icon'     => '🌙',
                'active'   => 1,
            ],
        ];
    }

    /**
     * Obtener todos los eventos genéricos registrados
     *
     * @param bool $active_only Si solo se retornan los activos
     * @return array
     */
    public static function get_all( bool $active_only = false ): array {
        $events = get_option( self::OPTION_KEY, null );
        if ( ! is_array( $events ) || empty( $events ) ) {
            $events = self::get_default_events();
            update_option( self::OPTION_KEY, $events );
        }

        if ( $active_only ) {
            return array_values( array_filter( $events, function( $ev ) {
                return ! empty( $ev['active'] );
            } ) );
        }

        return array_values( $events );
    }

    /**
     * Obtener un evento genérico por su ID
     */
    public static function get_item( string $id ): ?array {
        $events = self::get_all();
        foreach ( $events as $ev ) {
            if ( (string) $ev['id'] === (string) $id ) {
                return $ev;
            }
        }
        return null;
    }

    /**
     * Guardar o actualizar un evento genérico
     *
     * @param array $data Datos del item
     * @return array|WP_Error Item guardado o error
     */
    public static function save_item( array $data ) {
        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'El nombre del evento genérico es obligatorio.', 'aura' ) );
        }

        $id       = ! empty( $data['id'] ) ? sanitize_key( $data['id'] ) : sanitize_title( $name );
        $duration = max( 5, min( 1440, intval( $data['duration'] ?? 30 ) ) );
        $type     = in_array( $data['type'] ?? '', [ 'break', 'activity', 'class', 'workshop', 'exam', 'other' ], true ) ? $data['type'] : 'break';
        $color    = sanitize_hex_color( $data['color'] ?? '' ) ?: '#5D5FEF';
        $icon     = sanitize_text_field( $data['icon'] ?? '⚡' );
        $active   = isset( $data['active'] ) ? (int) (bool) $data['active'] : 1;

        if ( empty( $id ) ) {
            $id = 'gen_' . wp_generate_password( 6, false );
        }

        $events   = self::get_all();
        $updated  = false;
        $new_item = [
            'id'       => $id,
            'name'     => $name,
            'duration' => $duration,
            'type'     => $type,
            'color'    => $color,
            'icon'     => $icon,
            'active'   => $active,
        ];

        foreach ( $events as $idx => $ev ) {
            if ( (string) $ev['id'] === (string) $id ) {
                $events[ $idx ] = $new_item;
                $updated        = true;
                break;
            }
        }

        if ( ! $updated ) {
            $events[] = $new_item;
        }

        update_option( self::OPTION_KEY, $events );
        return $new_item;
    }

    /**
     * Eliminar un evento genérico por su ID
     */
    public static function delete_item( string $id ): bool {
        $events = self::get_all();
        $filtered = array_filter( $events, function( $ev ) use ( $id ) {
            return (string) $ev['id'] !== (string) $id;
        } );

        if ( count( $filtered ) === count( $events ) ) {
            return false;
        }

        update_option( self::OPTION_KEY, array_values( $filtered ) );
        return true;
    }

    /**
     * Restablecer los eventos predeterminados
     */
    public static function reset_defaults(): bool {
        return update_option( self::OPTION_KEY, self::get_default_events() );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Obtener lista de eventos genéricos
     */
    public static function ajax_get_events(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $active_only = ! empty( $_POST['active_only'] );
        $events      = self::get_all( $active_only );

        wp_send_json_success( [
            'events' => $events,
        ] );
    }

    /**
     * AJAX: Crear o actualizar evento genérico
     */
    public static function ajax_save_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permisos para modificar eventos genéricos.', 'aura' ) ] );
        }

        $res = self::save_item( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'Evento genérico guardado correctamente.', 'aura' ),
            'item'    => $res,
            'events'  => self::get_all(),
        ] );
    }

    /**
     * AJAX: Eliminar evento genérico
     */
    public static function ajax_delete_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permisos para eliminar eventos genéricos.', 'aura' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );
        if ( empty( $id ) ) {
            wp_send_json_error( [ 'message' => __( 'ID de evento genérico no proporcionado.', 'aura' ) ] );
        }

        $deleted = self::delete_item( $id );
        if ( ! $deleted ) {
            wp_send_json_error( [ 'message' => __( 'No se encontró el evento genérico a eliminar.', 'aura' ) ] );
        }

        wp_send_json_success( [
            'message' => __( 'Evento genérico eliminado.', 'aura' ),
            'events'  => self::get_all(),
        ] );
    }

    /**
     * AJAX: Restablecer valores por defecto
     */
    public static function ajax_reset_events(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permisos para restablecer eventos genéricos.', 'aura' ) ] );
        }

        self::reset_defaults();

        wp_send_json_success( [
            'message' => __( 'Se han restablecido los 11 eventos genéricos predeterminados con éxito.', 'aura' ),
            'events'  => self::get_all(),
        ] );
    }
}
