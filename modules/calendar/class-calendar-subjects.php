<?php
/**
 * Gestión de Materias y Asignaturas — Módulo de Calendario
 *
 * CRUD de materias por programa académico, vinculando carga horaria,
 * instructor principal asignado y color distintivo.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Subjects {

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_subjects',   [ __CLASS__, 'ajax_get_subjects' ] );
        add_action( 'wp_ajax_aura_cal_get_subject',    [ __CLASS__, 'ajax_get_subject' ] );
        add_action( 'wp_ajax_aura_cal_save_subject',   [ __CLASS__, 'ajax_save_subject' ] );
        add_action( 'wp_ajax_aura_cal_delete_subject', [ __CLASS__, 'ajax_delete_subject' ] );
    }

    /**
     * Obtener materias con filtros
     *
     * @param array $args Filtros opcionales (program_id, status, search)
     * @return array
     */
    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';
        $table_prog = $wpdb->prefix . 'aura_cal_programs';
        $table_evts = $wpdb->prefix . 'aura_cal_events';

        $defaults = [
            'program_id' => 0,
            'status'     => '',
            'search'     => '',
            'orderby'    => 's.order_index ASC, s.name',
            'order'      => 'ASC',
            'limit'      => 200,
            'offset'     => 0,
        ];
        $r = wp_parse_args( $args, $defaults );

        $where  = [ 's.deleted_at IS NULL' ];
        $params = [];

        if ( ! empty( $r['program_id'] ) ) {
            $where[]  = 's.program_id = %d';
            $params[] = intval( $r['program_id'] );
        }

        if ( ! empty( $r['status'] ) ) {
            $where[]  = 's.status = %s';
            $params[] = sanitize_text_field( $r['status'] );
        }

        if ( ! empty( $r['search'] ) ) {
            $where[]  = '(s.name LIKE %s OR s.code LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $r['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $orderby   = in_array( strtoupper( $r['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $r['order'] ) : 'ASC';

        $sql = "SELECT s.*, p.name AS program_name, p.code AS program_code, p.color AS program_color,
                       u.display_name AS default_teacher_name, u.user_email AS default_teacher_email,
                       (SELECT COUNT(*) FROM {$table_evts} e WHERE e.subject_id = s.id AND e.deleted_at IS NULL) AS events_count
                FROM {$table_subj} s
                LEFT JOIN {$table_prog} p ON p.id = s.program_id
                LEFT JOIN {$wpdb->users} u ON u.ID = s.default_teacher_id
                WHERE {$where_sql}
                ORDER BY {$r['orderby']} {$orderby}
                LIMIT %d OFFSET %d";

        $params[] = intval( $r['limit'] );
        $params[] = intval( $r['offset'] );

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        $subjects = is_array( $results ) ? $results : [];
        return self::populate_teachers( $subjects );
    }

    /**
     * Enriquecer lista de materias con la resolución de múltiples profesores titulares
     *
     * @param array $subjects
     * @return array
     */
    private static function populate_teachers( array $subjects ): array {
        if ( empty( $subjects ) ) {
            return [];
        }

        foreach ( $subjects as &$s ) {
            $ids = [];
            if ( ! empty( $s->teachers ) ) {
                $decoded = json_decode( $s->teachers, true );
                if ( is_array( $decoded ) ) {
                    $ids = array_values( array_unique( array_filter( array_map( 'intval', $decoded ) ) ) );
                }
            }

            if ( empty( $ids ) && ! empty( $s->default_teacher_id ) ) {
                $ids = [ intval( $s->default_teacher_id ) ];
            }

            $s->teacher_ids   = $ids;
            $s->teachers_data = [];
            $names            = [];

            foreach ( $ids as $uid ) {
                $user = get_userdata( $uid );
                if ( $user ) {
                    $s->teachers_data[] = [
                        'id'    => $user->ID,
                        'name'  => $user->display_name,
                        'email' => $user->user_email,
                    ];
                    $names[] = $user->display_name;
                }
            }

            $s->teachers_names = ! empty( $names ) ? implode( ', ', $names ) : ( $s->default_teacher_name ?: '' );
        }
        unset( $s );

        return $subjects;
    }

    /**
     * Obtener una materia por ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';
        $table_prog = $wpdb->prefix . 'aura_cal_programs';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, p.name AS program_name, p.code AS program_code,
                    u.display_name AS default_teacher_name, u.user_email AS default_teacher_email
             FROM {$table_subj} s
             LEFT JOIN {$table_prog} p ON p.id = s.program_id
             LEFT JOIN {$wpdb->users} u ON u.ID = s.default_teacher_id
             WHERE s.id = %d AND s.deleted_at IS NULL",
            $id
        ) );

        if ( ! $row ) {
            return null;
        }

        $populated = self::populate_teachers( [ $row ] );
        return ! empty( $populated ) ? $populated[0] : $row;
    }

    /**
     * Crear o actualizar una materia
     *
     * @param array $data
     * @return int|WP_Error ID de la materia o error
     */
    public static function save( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_cal_subjects';

        $id         = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;
        $program_id = ! empty( $data['program_id'] ) ? intval( $data['program_id'] ) : 0;

        if ( ! $program_id ) {
            return new WP_Error( 'missing_program', __( 'Debe seleccionar un programa académico para la materia.', 'aura' ) );
        }

        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'El nombre de la materia es obligatorio.', 'aura' ) );
        }

        $code = sanitize_text_field( $data['code'] ?? '' );
        if ( empty( $code ) ) {
            $code = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $name ), 0, 6 ) );
        }

        $color = sanitize_hex_color( $data['color'] ?? '' );
        if ( empty( $color ) ) {
            $color = '#3b82f6'; // Default Blue
        }

        // Procesar profesores titulares múltiples
        $teacher_ids = [];
        if ( isset( $data['teacher_ids'] ) ) {
            if ( is_array( $data['teacher_ids'] ) ) {
                $teacher_ids = array_values( array_unique( array_filter( array_map( 'intval', $data['teacher_ids'] ) ) ) );
            } elseif ( is_string( $data['teacher_ids'] ) && ! empty( $data['teacher_ids'] ) ) {
                $decoded = json_decode( stripslashes( $data['teacher_ids'] ), true );
                if ( is_array( $decoded ) ) {
                    $teacher_ids = array_values( array_unique( array_filter( array_map( 'intval', $decoded ) ) ) );
                } else {
                    $teacher_ids = array_values( array_unique( array_filter( array_map( 'intval', explode( ',', $data['teacher_ids'] ) ) ) ) );
                }
            }
        } elseif ( ! empty( $data['default_teacher_id'] ) ) {
            $teacher_ids = [ intval( $data['default_teacher_id'] ) ];
        }

        $primary_teacher = ! empty( $teacher_ids ) ? $teacher_ids[0] : null;
        $teachers_json   = ! empty( $teacher_ids ) ? wp_json_encode( $teacher_ids ) : null;

        $fields = [
            'program_id'         => $program_id,
            'name'               => $name,
            'code'               => $code,
            'description'        => sanitize_textarea_field( $data['description'] ?? '' ),
            'total_hours'        => max( 0, intval( $data['total_hours'] ?? 0 ) ),
            'color'              => $color,
            'default_teacher_id' => $primary_teacher,
            'teachers'           => $teachers_json,
            'status'             => in_array( $data['status'] ?? '', [ 'active', 'inactive' ], true ) ? $data['status'] : 'active',
            'order_index'        => intval( $data['order_index'] ?? 0 ),
            'updated_at'         => current_time( 'mysql' ),
        ];

        $formats = [
            '%d', '%s', '%s', '%s', '%d', '%s',
            $fields['default_teacher_id'] !== null ? '%d' : null,
            $fields['teachers'] !== null ? '%s' : null,
            '%s', '%d', '%s',
        ];

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
                return new WP_Error( 'db_error', __( 'Error al actualizar la materia.', 'aura' ) );
            }
            return $id;
        } else {
            $clean_fields['created_at'] = current_time( 'mysql' );
            $clean_formats[]            = '%s';

            $inserted = $wpdb->insert( $table, $clean_fields, $clean_formats );
            if ( ! $inserted ) {
                return new WP_Error( 'db_error', __( 'Error al registrar la materia.', 'aura' ) );
            }
            return (int) $wpdb->insert_id;
        }
    }

    /**
     * Eliminar materia (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_cal_subjects';

        $updated = $wpdb->update(
            $table,
            [
                'deleted_at' => current_time( 'mysql' ),
                'status'     => 'inactive',
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

    public static function ajax_get_subjects(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $program_id = intval( $_POST['program_id'] ?? 0 );
        $subjects   = self::get_all( [
            'program_id' => $program_id,
            'status'     => sanitize_text_field( $_POST['status'] ?? '' ),
            'search'     => sanitize_text_field( $_POST['search'] ?? '' ),
        ] );

        wp_send_json_success( [ 'subjects' => $subjects ] );
    }

    public static function ajax_get_subject(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $subject = self::get( $id );
        if ( ! $subject ) {
            wp_send_json_error( [ 'message' => __( 'Materia no encontrada.', 'aura' ) ] );
        }

        wp_send_json_success( [ 'subject' => $subject ] );
    }

    public static function ajax_save_subject(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para gestionar materias.', 'aura' ) ] );
        }

        $res = self::save( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => __( 'Materia guardada con éxito.', 'aura' ),
            'subject_id' => $res,
        ] );
    }

    public static function ajax_delete_subject(): void {
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
            wp_send_json_success( [ 'message' => __( 'Materia eliminada correctamente.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo eliminar la materia.', 'aura' ) ] );
        }
    }
}
