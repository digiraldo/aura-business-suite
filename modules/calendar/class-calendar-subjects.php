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
        add_action( 'wp_ajax_aura_cal_get_subjects',            [ __CLASS__, 'ajax_get_subjects' ] );
        add_action( 'wp_ajax_aura_cal_get_subject',             [ __CLASS__, 'ajax_get_subject' ] );
        add_action( 'wp_ajax_aura_cal_save_subject',            [ __CLASS__, 'ajax_save_subject' ] );
        add_action( 'wp_ajax_aura_cal_delete_subject',          [ __CLASS__, 'ajax_delete_subject' ] );
        add_action( 'wp_ajax_aura_cal_upload_subject_material', [ __CLASS__, 'ajax_upload_material' ] );
        add_action( 'wp_ajax_aura_cal_delete_subject_material', [ __CLASS__, 'ajax_delete_material' ] );
        add_action( 'wp_ajax_aura_cal_get_unassigned_subjects', [ __CLASS__, 'ajax_get_unassigned_subjects' ] );
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

        if ( ! empty( $r['assignment_status'] ) ) {
            if ( $r['assignment_status'] === 'unassigned' ) {
                $where[] = "(SELECT COUNT(*) FROM {$table_evts} e WHERE e.subject_id = s.id AND e.deleted_at IS NULL) = 0";
            } elseif ( $r['assignment_status'] === 'assigned' ) {
                $where[] = "(SELECT COUNT(*) FROM {$table_evts} e WHERE e.subject_id = s.id AND e.deleted_at IS NULL) > 0";
            }
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
        return self::populate_scheduled_events( self::populate_teachers( $subjects ) );
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

            // Decodificar listas de materiales docentes y estudiantiles
            $s->teacher_materials_list = [];
            if ( ! empty( $s->teacher_materials ) ) {
                $dec_tm = json_decode( $s->teacher_materials, true );
                if ( is_array( $dec_tm ) ) {
                    $s->teacher_materials_list = $dec_tm;
                }
            }

            $s->student_materials_list = [];
            if ( ! empty( $s->student_materials ) ) {
                $dec_sm = json_decode( $s->student_materials, true );
                if ( is_array( $dec_sm ) ) {
                    $s->student_materials_list = $dec_sm;
                }
            }

            $s->teacher_materials_count = count( $s->teacher_materials_list );
            $s->student_materials_count = count( $s->student_materials_list );
            $s->total_materials_count   = $s->teacher_materials_count + $s->student_materials_count;
        }
        unset( $s );

        return $subjects;
    }

    /**
     * Enriquecer materias con los eventos y fechas programadas en el calendario
     *
     * @param array $subjects
     * @return array
     */
    private static function populate_scheduled_events( array $subjects ): array {
        if ( empty( $subjects ) ) {
            return [];
        }

        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $sub_ids    = array_values( array_filter( array_map( function( $s ) {
            return (int) ( $s->id ?? 0 );
        }, $subjects ) ) );

        if ( empty( $sub_ids ) ) {
            return $subjects;
        }

        $ids_placeholder = implode( ',', $sub_ids );
        $rows = $wpdb->get_results(
            "SELECT id, program_id, subject_id, title, event_type, start_datetime, end_datetime, 
                    location, online_url, status, color
             FROM {$table_evts}
             WHERE subject_id IN ({$ids_placeholder}) AND deleted_at IS NULL
             ORDER BY start_datetime ASC"
        );

        $events_by_subject = [];
        $time_fmt = get_option( 'time_format', 'H:i' );

        $type_labels = [
            'class'    => [ 'label' => __( 'Clase Regular', 'aura' ), 'icon' => '📖' ],
            'exam'     => [ 'label' => __( 'Examen / Evaluación', 'aura' ), 'icon' => '📝' ],
            'workshop' => [ 'label' => __( 'Taller / Práctica', 'aura' ), 'icon' => '🔬' ],
            'activity' => [ 'label' => __( 'Actividad', 'aura' ), 'icon' => '🎯' ],
            'break'    => [ 'label' => __( 'Receso', 'aura' ), 'icon' => '☕' ],
            'other'    => [ 'label' => __( 'Otro', 'aura' ), 'icon' => '📍' ],
        ];

        $status_labels = [
            'scheduled' => __( 'Programado', 'aura' ),
            'completed' => __( 'Completado', 'aura' ),
            'cancelled' => __( 'Cancelado', 'aura' ),
            'postponed' => __( 'Pospuesto', 'aura' ),
        ];

        if ( ! empty( $rows ) ) {
            foreach ( $rows as $ev ) {
                $sid = (int) $ev->subject_id;
                if ( ! isset( $events_by_subject[ $sid ] ) ) {
                    $events_by_subject[ $sid ] = [];
                }

                $type_info = $type_labels[ $ev->event_type ] ?? [ 'label' => ucfirst( $ev->event_type ), 'icon' => '📌' ];
                $start_ts  = strtotime( $ev->start_datetime );
                $end_ts    = strtotime( $ev->end_datetime );

                $date_str      = $start_ts ? date_i18n( 'D, j M Y', $start_ts ) : substr( $ev->start_datetime, 0, 10 );
                $date_full     = $start_ts ? date_i18n( 'l, j \d\e F \d\e Y', $start_ts ) : $date_str;
                $day_num       = $start_ts ? date_i18n( 'j', $start_ts ) : '';
                $month_num     = $start_ts ? (int) date_i18n( 'n', $start_ts ) : 1;
                $month_short   = $start_ts ? strtoupper( date_i18n( 'M', $start_ts ) ) : '';
                $weekday_short = $start_ts ? ucfirst( date_i18n( 'D', $start_ts ) ) : '';
                $year_num      = $start_ts ? date_i18n( 'Y', $start_ts ) : '';

                $time_str = ( $start_ts && $end_ts )
                    ? date_i18n( $time_fmt, $start_ts ) . ' – ' . date_i18n( $time_fmt, $end_ts )
                    : '';

                $events_by_subject[ $sid ][] = [
                    'id'             => (int) $ev->id,
                    'title'          => $ev->title,
                    'event_type'     => $ev->event_type,
                    'type_label'     => $type_info['label'],
                    'type_icon'      => $type_info['icon'],
                    'start_datetime' => $ev->start_datetime,
                    'end_datetime'   => $ev->end_datetime,
                    'date_formatted' => $date_str,
                    'date_full'      => $date_full,
                    'day_num'        => $day_num,
                    'month_num'      => $month_num,
                    'month_short'    => $month_short,
                    'weekday_short'  => $weekday_short,
                    'year_num'       => $year_num,
                    'time_formatted' => $time_str,
                    'location'       => $ev->location ?: '',
                    'online_url'     => $ev->online_url ?: '',
                    'status'         => $ev->status,
                    'status_label'   => $status_labels[ $ev->status ] ?? ucfirst( $ev->status ),
                    'color'          => $ev->color ?: '#5D5FEF',
                ];
            }
        }

        foreach ( $subjects as &$s ) {
            $sid = (int) ( $s->id ?? 0 );
            $s->scheduled_events = $events_by_subject[ $sid ] ?? [];
            $s->has_calendar     = ! empty( $s->scheduled_events );
            $s->events_count     = count( $s->scheduled_events );
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

        $populated = self::populate_scheduled_events( self::populate_teachers( [ $row ] ) );
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

        // Procesar listas de materiales docentes y estudiantiles
        $teacher_materials_val = null;
        if ( isset( $data['teacher_materials'] ) ) {
            if ( is_array( $data['teacher_materials'] ) ) {
                $teacher_materials_val = wp_json_encode( $data['teacher_materials'] );
            } elseif ( is_string( $data['teacher_materials'] ) ) {
                $teacher_materials_val = stripslashes( $data['teacher_materials'] );
            }
        }

        $student_materials_val = null;
        if ( isset( $data['student_materials'] ) ) {
            if ( is_array( $data['student_materials'] ) ) {
                $student_materials_val = wp_json_encode( $data['student_materials'] );
            } elseif ( is_string( $data['student_materials'] ) ) {
                $student_materials_val = stripslashes( $data['student_materials'] );
            }
        }

        $gdrive_folder_id = ! empty( $data['gdrive_folder_id'] ) ? sanitize_text_field( $data['gdrive_folder_id'] ) : null;
        $module_name      = ! empty( $data['module_name'] ) ? sanitize_text_field( $data['module_name'] ) : null;
        $module_order     = isset( $data['module_order'] ) ? max( 1, intval( $data['module_order'] ) ) : 1;

        $fields = [
            'program_id'         => $program_id,
            'name'               => $name,
            'code'               => $code,
            'description'        => sanitize_textarea_field( $data['description'] ?? '' ),
            'module_name'        => $module_name,
            'module_order'       => $module_order,
            'total_hours'        => max( 0, intval( $data['total_hours'] ?? 0 ) ),
            'color'              => $color,
            'default_teacher_id' => $primary_teacher,
            'teachers'           => $teachers_json,
            'teacher_materials'  => $teacher_materials_val,
            'student_materials'  => $student_materials_val,
            'gdrive_folder_id'   => $gdrive_folder_id,
            'status'             => in_array( $data['status'] ?? '', [ 'active', 'inactive' ], true ) ? $data['status'] : 'active',
            'order_index'        => intval( $data['order_index'] ?? 0 ),
            'updated_at'         => current_time( 'mysql' ),
        ];

        $formats = [
            '%d', '%s', '%s', '%s',
            $fields['module_name'] !== null ? '%s' : null,
            '%d', '%d', '%s',
            $fields['default_teacher_id'] !== null ? '%d' : null,
            $fields['teachers'] !== null ? '%s' : null,
            $fields['teacher_materials'] !== null ? '%s' : null,
            $fields['student_materials'] !== null ? '%s' : null,
            $fields['gdrive_folder_id'] !== null ? '%s' : null,
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

        if ( ! current_user_can( 'aura_cal_view_calendar' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
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

        if ( ! current_user_can( 'aura_cal_view_calendar' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

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

        if ( ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
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

        if ( ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_delete_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
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

    /**
     * AJAX: Subir material de estudio (Docente o Estudiante) a Google Drive o almacenamiento local
     */
    public static function ajax_upload_material(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $can_manage = current_user_can( 'aura_cal_manage_programs' ) ||
                      current_user_can( 'aura_cal_manage_calendar' ) ||
                      current_user_can( 'aura_create_calendar_events' ) ||
                      current_user_can( 'manage_options' );

        $subject_id = intval( $_POST['subject_id'] ?? 0 );
        $user_id    = get_current_user_id();

        // Si no tiene capabilities administrativas, verificar si es docente asignado a esta materia
        if ( ! $can_manage && $subject_id > 0 ) {
            $subject = self::get( $subject_id );
            if ( $subject && ! empty( $subject->teacher_ids ) && in_array( $user_id, $subject->teacher_ids, true ) ) {
                $can_manage = true;
            }
        }

        if ( ! $can_manage ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para subir material.', 'aura' ) ] );
        }

        $audience     = sanitize_text_field( $_POST['audience'] ?? ( $_POST['material_type'] ?? 'student' ) );
        if ( ! in_array( $audience, [ 'teacher', 'student' ], true ) ) {
            $audience = 'student';
        }

        $title        = sanitize_text_field( $_POST['title'] ?? ( $_POST['material_title'] ?? ( $_POST['file_name'] ?? '' ) ) );
        $external_url = esc_url_raw( $_POST['external_url'] ?? ( $_POST['drive_link'] ?? '' ) );

        // Incluir gestor de Google Drive si existe
        if ( ! class_exists( 'Aura_Drive_Manager' ) ) {
            $gdrive_path = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR . 'modules/financial/class-google-drive-manager.php' : '';
            if ( ! empty( $gdrive_path ) && file_exists( $gdrive_path ) ) {
                require_once $gdrive_path;
            }
        }

        $file_entry = null;

        // ── CASO A: SUBIDA DE ARCHIVO FÍSICO ──
        if ( ! empty( $_FILES['material_file'] ) && ! empty( $_FILES['material_file']['name'] ) ) {
            $file      = $_FILES['material_file'];
            $file_name = sanitize_file_name( $file['name'] );
            $file_tmp  = $file['tmp_name'];
            $file_size = intval( $file['size'] );
            $file_type = wp_check_filetype( $file_name );
            $mime_type = ! empty( $file_type['type'] ) ? $file_type['type'] : 'application/octet-stream';

            if ( empty( $title ) ) {
                $title = pathinfo( $file_name, PATHINFO_FILENAME );
            }

            $uploaded_to_drive = false;

            // Intentar subir a Google Drive si está configurado
            if ( class_exists( 'Aura_Drive_Manager' ) ) {
                $drive = new \Aura_Drive_Manager();
                if ( $drive->is_ready() ) {
                    $drive_res = $drive->upload_file( $file_tmp, $file_name, $mime_type, 'Academico_Materias' );
                    if ( $drive_res && ! empty( $drive_res['url'] ) ) {
                        $file_id      = $drive_res['file_id'] ?? \Aura_Drive_Manager::extract_file_id( $drive_res['url'] );
                        $preview_url  = ! empty( $drive_res['preview_url'] ) ? $drive_res['preview_url'] : \Aura_Drive_Manager::get_preview_url( $drive_res['url'] );
                        $download_url = ! empty( $drive_res['download_url'] ) ? $drive_res['download_url'] : \Aura_Drive_Manager::get_download_url( $drive_res['url'] );

                        $file_entry = [
                            'id'             => uniqid( 'mat_' ),
                            'name'           => $file_name,
                            'title'          => $title,
                            'url'            => $drive_res['url'],
                            'file_id'        => $file_id,
                            'preview_url'    => $preview_url,
                            'download_url'   => $download_url,
                            'storage'        => 'gdrive',
                            'size_formatted' => size_format( $file_size ),
                            'mime'           => $mime_type,
                            'uploaded_at'    => current_time( 'mysql' ),
                            'uploaded_by'    => $user_id,
                            'uploader_name'  => wp_get_current_user()->display_name,
                        ];
                        $uploaded_to_drive = true;
                    }
                }
            }

            // Fallback a almacenamiento local de WordPress si Google Drive no está disponible
            if ( ! $uploaded_to_drive ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $overrides = [ 'test_form' => false ];
                $upload    = wp_handle_upload( $file, $overrides );

                if ( isset( $upload['error'] ) ) {
                    wp_send_json_error( [ 'message' => $upload['error'] ] );
                }

                $local_url = $upload['url'];
                $preview   = $local_url;
                if ( in_array( $mime_type, [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ], true ) ) {
                    $preview = 'https://docs.google.com/viewer?url=' . rawurlencode( $local_url ) . '&embedded=true';
                }

                $file_entry = [
                    'id'             => uniqid( 'mat_' ),
                    'name'           => $file_name,
                    'title'          => $title,
                    'url'            => $local_url,
                    'file_id'        => '',
                    'preview_url'    => $preview,
                    'download_url'   => $local_url,
                    'storage'        => 'local',
                    'size_formatted' => size_format( $file_size ),
                    'mime'           => $mime_type,
                    'uploaded_at'    => current_time( 'mysql' ),
                    'uploaded_by'    => $user_id,
                    'uploader_name'  => wp_get_current_user()->display_name,
                ];
            }
        }
        // ── CASO B: ENLACE WEB O GOOGLE DRIVE DIRECTO ──
        elseif ( ! empty( $external_url ) ) {
            if ( empty( $title ) ) {
                $title = __( 'Documento de Estudio en la Nube', 'aura' );
            }

            $is_drive = class_exists( 'Aura_Drive_Manager' ) && \Aura_Drive_Manager::is_drive_url( $external_url );
            $file_id  = $is_drive ? \Aura_Drive_Manager::extract_file_id( $external_url ) : '';
            $preview  = $is_drive ? \Aura_Drive_Manager::get_preview_url( $external_url ) : $external_url;
            $download = $is_drive ? \Aura_Drive_Manager::get_download_url( $external_url ) : $external_url;

            $file_entry = [
                'id'             => uniqid( 'mat_' ),
                'name'           => $title,
                'title'          => $title,
                'url'            => $external_url,
                'file_id'        => $file_id,
                'preview_url'    => $preview,
                'download_url'   => $download,
                'storage'        => $is_drive ? 'gdrive' : 'external',
                'size_formatted' => '—',
                'mime'           => 'link',
                'uploaded_at'    => current_time( 'mysql' ),
                'uploaded_by'    => $user_id,
                'uploader_name'  => wp_get_current_user()->display_name,
            ];
        } else {
            wp_send_json_error( [ 'message' => __( 'Debe adjuntar un archivo o ingresar una URL de Google Drive.', 'aura' ) ] );
        }

        // Si la materia ya existe en la base de datos, guardar el archivo en su registro de inmediato
        if ( $subject_id > 0 && ! empty( $file_entry ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'aura_cal_subjects';
            $field = $audience === 'teacher' ? 'teacher_materials' : 'student_materials';

            $current_raw = $wpdb->get_var( $wpdb->prepare( "SELECT {$field} FROM {$table} WHERE id = %d", $subject_id ) );
            $list = [];
            if ( ! empty( $current_raw ) ) {
                $dec = json_decode( $current_raw, true );
                if ( is_array( $dec ) ) {
                    $list = $dec;
                }
            }
            $list[] = $file_entry;

            $wpdb->update(
                $table,
                [ $field => wp_json_encode( $list ) ],
                [ 'id' => $subject_id ],
                [ '%s' ],
                [ '%d' ]
            );
        }

        wp_send_json_success( [
            'material' => $file_entry,
            'audience' => $audience,
            'message'  => __( 'Material registrado exitosamente.', 'aura' ),
        ] );
    }

    /**
     * AJAX: Eliminar material de estudio de una materia
     */
    public static function ajax_delete_material(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $can_manage = current_user_can( 'aura_cal_manage_programs' ) ||
                      current_user_can( 'aura_cal_manage_calendar' ) ||
                      current_user_can( 'aura_delete_calendar_events' ) ||
                      current_user_can( 'manage_options' );

        $subject_id  = intval( $_POST['subject_id'] ?? 0 );
        $material_id = sanitize_text_field( $_POST['material_id'] ?? '' );
        $audience    = sanitize_text_field( $_POST['audience'] ?? 'student' );
        $user_id     = get_current_user_id();

        if ( ! in_array( $audience, [ 'teacher', 'student' ], true ) ) {
            $audience = 'student';
        }

        if ( ! $can_manage && $subject_id > 0 ) {
            $subject = self::get( $subject_id );
            if ( $subject && ! empty( $subject->teacher_ids ) && in_array( $user_id, $subject->teacher_ids, true ) ) {
                $can_manage = true;
            }
        }

        if ( ! $can_manage ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        if ( $subject_id > 0 && ! empty( $material_id ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'aura_cal_subjects';
            $field = $audience === 'teacher' ? 'teacher_materials' : 'student_materials';

            $current_raw = $wpdb->get_var( $wpdb->prepare( "SELECT {$field} FROM {$table} WHERE id = %d", $subject_id ) );
            if ( ! empty( $current_raw ) ) {
                $list = json_decode( $current_raw, true );
                if ( is_array( $list ) ) {
                    $filtered = array_values( array_filter( $list, function( $item ) use ( $material_id ) {
                        return ( $item['id'] ?? '' ) !== $material_id;
                    } ) );

                    $wpdb->update(
                        $table,
                        [ $field => wp_json_encode( $filtered ) ],
                        [ 'id' => $subject_id ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                }
            }
        }

        wp_send_json_success( [
            'material_id' => $material_id,
            'audience'    => $audience,
            'message'     => __( 'Material eliminado correctamente.', 'aura' ),
        ] );
    }

    /**
     * AJAX: Obtener materias con estado de asignación al calendario (para Drawer y filtros)
     */
    public static function ajax_get_unassigned_subjects(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $program_id    = intval( $_POST['program_id'] ?? 0 );
        $status_filter = sanitize_text_field( $_POST['status_filter'] ?? 'unassigned' );

        $args = [
            'status'  => 'active',
            'limit'   => 300,
            'orderby' => 's.module_order ASC, s.order_index ASC, s.name',
            'order'   => 'ASC',
        ];
        if ( $program_id > 0 ) {
            $args['program_id'] = $program_id;
        }
        if ( in_array( $status_filter, [ 'unassigned', 'assigned' ], true ) ) {
            $args['assignment_status'] = $status_filter;
        }

        $subjects = self::get_all( $args );

        global $wpdb;
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $prog_sql   = $program_id > 0 ? $wpdb->prepare( "AND s.program_id = %d", $program_id ) : "";

        $unassigned_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_subj} s 
             WHERE s.deleted_at IS NULL AND s.status = 'active' {$prog_sql}
             AND (SELECT COUNT(*) FROM {$table_evts} e WHERE e.subject_id = s.id AND e.deleted_at IS NULL) = 0"
        );

        $total_subjects = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_subj} s 
             WHERE s.deleted_at IS NULL AND s.status = 'active' {$prog_sql}"
        );

        wp_send_json_success( [
            'subjects'         => $subjects,
            'unassigned_count' => $unassigned_count,
            'total_subjects'   => $total_subjects,
            'assigned_count'   => max( 0, $total_subjects - $unassigned_count ),
        ] );
    }
}
