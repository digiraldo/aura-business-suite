<?php
/**
 * Gestión de Programas Académicos — Módulo de Calendario
 *
 * CRUD de programas académicos (ej: HADIME, Capacitación Ministerial, Cursos Semestrales)
 * con asignación de coordinadores, períodos académicos, códigos y colores temáticos.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Programs {

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_programs',   [ __CLASS__, 'ajax_get_programs' ] );
        add_action( 'wp_ajax_aura_cal_get_program',    [ __CLASS__, 'ajax_get_program' ] );
        add_action( 'wp_ajax_aura_cal_save_program',   [ __CLASS__, 'ajax_save_program' ] );
        add_action( 'wp_ajax_aura_cal_delete_program', [ __CLASS__, 'ajax_delete_program' ] );
    }

    /**
     * Obtener lista de programas
     *
     * @param array $args Filtros opcionales (status, search, limit, offset)
     * @return array
     */
    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $table_prog = $wpdb->prefix . 'aura_cal_programs';
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';
        $table_evts = $wpdb->prefix . 'aura_cal_events';

        $defaults = [
            'status'  => '', // active, archived, draft o vacío para no eliminados
            'search'  => '',
            'orderby' => 'p.created_at',
            'order'   => 'DESC',
            'limit'   => 100,
            'offset'  => 0,
        ];
        $r = wp_parse_args( $args, $defaults );

        $where = [ 'p.deleted_at IS NULL' ];
        $params = [];

        if ( ! empty( $r['status'] ) ) {
            $where[]  = 'p.status = %s';
            $params[] = sanitize_text_field( $r['status'] );
        }

        if ( ! empty( $r['search'] ) ) {
            $where[]  = '(p.name LIKE %s OR p.code LIKE %s OR p.academic_period LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $r['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $orderby   = in_array( strtoupper( $r['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $r['order'] ) : 'DESC';

        $sql = "SELECT p.*, u.display_name AS coordinator_name, u.user_email AS coordinator_email,
                       (SELECT COUNT(*) FROM {$table_subj} s WHERE s.program_id = p.id AND s.deleted_at IS NULL) AS subjects_count,
                       (SELECT COUNT(*) FROM {$table_evts} e WHERE e.program_id = p.id AND e.deleted_at IS NULL) AS events_count
                FROM {$table_prog} p
                LEFT JOIN {$wpdb->users} u ON u.ID = p.coordinator_id
                WHERE {$where_sql}
                ORDER BY {$r['orderby']} {$orderby}
                LIMIT %d OFFSET %d";

        $params[] = intval( $r['limit'] );
        $params[] = intval( $r['offset'] );

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        return is_array( $results ) ? $results : [];
    }

    /**
     * Obtener un programa por ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        $table_prog = $wpdb->prefix . 'aura_cal_programs';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT p.*, u.display_name AS coordinator_name, u.user_email AS coordinator_email
             FROM {$table_prog} p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.coordinator_id
             WHERE p.id = %d AND p.deleted_at IS NULL",
            $id
        ) );

        return $row ?: null;
    }

    /**
     * Crear o actualizar un programa
     *
     * @param array $data
     * @return int|WP_Error ID del programa o error
     */
    public static function save( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_cal_programs';

        $id = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;

        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'El nombre del programa es obligatorio.', 'aura' ) );
        }

        $code = sanitize_text_field( $data['code'] ?? '' );
        if ( empty( $code ) ) {
            // Autogenerar código a partir del nombre
            $code = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $name ), 0, 8 ) );
        }

        // Verificar código único
        $code_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE code = %s AND id != %d AND deleted_at IS NULL",
            $code,
            $id
        ) );
        if ( $code_exists ) {
            $code .= '-' . wp_rand( 10, 99 );
        }

        $color = sanitize_hex_color( $data['color'] ?? '' );
        if ( empty( $color ) ) {
            $color = '#6366f1'; // Default Indigo
        }

        $fields = [
            'name'            => $name,
            'code'            => $code,
            'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
            'academic_period' => sanitize_text_field( $data['academic_period'] ?? '' ),
            'start_date'      => ! empty( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null,
            'end_date'        => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
            'color'           => $color,
            'status'          => in_array( $data['status'] ?? '', [ 'active', 'archived', 'draft' ], true ) ? $data['status'] : 'active',
            'coordinator_id'  => ! empty( $data['coordinator_id'] ) ? intval( $data['coordinator_id'] ) : null,
            'updated_at'      => current_time( 'mysql' ),
        ];

        // Validar coherencia de rango de fechas
        if ( ! empty( $fields['start_date'] ) && ! empty( $fields['end_date'] ) ) {
            if ( strtotime( $fields['end_date'] ) < strtotime( $fields['start_date'] ) ) {
                return new WP_Error( 'invalid_dates', __( 'La fecha de fin no puede ser anterior a la fecha de inicio.', 'aura' ) );
            }
        }

        $formats = [
            '%s', '%s', '%s', '%s',
            $fields['start_date'] !== null ? '%s' : null,
            $fields['end_date'] !== null ? '%s' : null,
            '%s', '%s',
            $fields['coordinator_id'] !== null ? '%d' : null,
            '%s',
        ];

        // Filtrar nulos en formats
        $clean_fields  = [];
        $clean_formats = [];
        $i = 0;
        foreach ( $fields as $key => $val ) {
            if ( $val !== null ) {
                $clean_fields[ $key ] = $val;
                $clean_formats[]      = $formats[ $i ];
            }
            $i++;
        }

        if ( $id > 0 ) {
            $updated = $wpdb->update( $table, $clean_fields, [ 'id' => $id ], $clean_formats, [ '%d' ] );
            if ( $updated === false ) {
                return new WP_Error( 'db_error', __( 'Error al actualizar el programa.', 'aura' ) );
            }
            return $id;
        } else {
            $clean_fields['created_by'] = get_current_user_id();
            $clean_formats[]            = '%d';
            $clean_fields['created_at'] = current_time( 'mysql' );
            $clean_formats[]            = '%s';

            $inserted = $wpdb->insert( $table, $clean_fields, $clean_formats );
            if ( ! $inserted ) {
                return new WP_Error( 'db_error', __( 'Error al registrar el programa.', 'aura' ) );
            }
            return (int) $wpdb->insert_id;
        }
    }

    /**
     * Eliminar programa (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_cal_programs';

        $updated = $wpdb->update(
            $table,
            [
                'deleted_at' => current_time( 'mysql' ),
                'status'     => 'archived',
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return $updated !== false;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_programs(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $programs = self::get_all( [
            'status' => sanitize_text_field( $_POST['status'] ?? '' ),
            'search' => sanitize_text_field( $_POST['search'] ?? '' ),
        ] );

        wp_send_json_success( [ 'programs' => $programs ] );
    }

    public static function ajax_get_program(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $program = self::get( $id );
        if ( ! $program ) {
            wp_send_json_error( [ 'message' => __( 'Programa no encontrado.', 'aura' ) ] );
        }

        wp_send_json_success( [ 'program' => $program ] );
    }

    public static function ajax_save_program(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para gestionar programas.', 'aura' ) ] );
        }

        $res = self::save( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => __( 'Programa guardado con éxito.', 'aura' ),
            'program_id' => $res,
        ] );
    }

    public static function ajax_delete_program(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_delete_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $ok = self::delete( $id );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Programa archivado correctamente.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo archivar el programa.', 'aura' ) ] );
        }
    }
}
