<?php
/**
 * Gestión de Tareas y Entregas — Módulo de Calendario
 *
 * Publicación de tareas por materia y evento, recepción de entregas estudiantiles
 * con archivos adjuntos o texto, y retroalimentación docente con asignación de nota.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Tasks {

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_tasks',              [ __CLASS__, 'ajax_get_tasks' ] );
        add_action( 'wp_ajax_aura_cal_get_task',               [ __CLASS__, 'ajax_get_task' ] );
        add_action( 'wp_ajax_aura_cal_save_task',              [ __CLASS__, 'ajax_save_task' ] );
        add_action( 'wp_ajax_aura_cal_delete_task',            [ __CLASS__, 'ajax_delete_task' ] );
        add_action( 'wp_ajax_aura_cal_get_submissions',        [ __CLASS__, 'ajax_get_submissions' ] );
        add_action( 'wp_ajax_aura_cal_grade_submission',       [ __CLASS__, 'ajax_grade_submission' ] );
        add_action( 'wp_ajax_aura_cal_submit_task',            [ __CLASS__, 'ajax_submit_task' ] );
        add_action( 'wp_ajax_aura_cal_search_library_books',   [ __CLASS__, 'ajax_search_library_books' ] );
        add_action( 'wp_ajax_aura_cal_get_program_students',   [ __CLASS__, 'ajax_get_program_students' ] );
    }

    /**
     * Obtener listado de tareas con filtros
     *
     * @param array $args
     * @return array
     */
    public static function get_tasks( array $args = [] ): array {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $table_prog  = $wpdb->prefix . 'aura_cal_programs';
        $table_subj  = $wpdb->prefix . 'aura_cal_subjects';
        $table_subs  = $wpdb->prefix . 'aura_cal_task_submissions';
        $table_books = $wpdb->prefix . 'aura_library_books';

        $defaults = [
            'program_id' => 0,
            'subject_id' => 0,
            'student_id' => 0,
            'status'     => '',
            'search'     => '',
            'limit'      => 100,
            'offset'     => 0,
        ];
        $r = wp_parse_args( $args, $defaults );

        $where  = [ 't.deleted_at IS NULL' ];
        $params = [];

        if ( ! empty( $r['program_id'] ) ) {
            $where[]  = 't.program_id = %d';
            $params[] = intval( $r['program_id'] );
        }

        if ( ! empty( $r['subject_id'] ) ) {
            $where[]  = 't.subject_id = %d';
            $params[] = intval( $r['subject_id'] );
        }

        if ( ! empty( $r['status'] ) ) {
            $where[]  = 't.status = %s';
            $params[] = sanitize_text_field( $r['status'] );
        }

        if ( ! empty( $r['search'] ) ) {
            $where[]  = 't.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $r['search'] ) . '%';
        }

        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT t.*, p.name AS program_name, p.code AS program_code, p.area_id,
                       s.name AS subject_name, s.code AS subject_code,
                       COALESCE(ut.display_name, u.display_name) AS teacher_name,
                       u.display_name AS author_name,
                       b.title AS book_title, b.author AS book_author, b.dewey_number AS book_dewey,
                       b.isbn AS book_isbn, b.cover_image_id AS book_cover_id,
                       (SELECT COUNT(*) FROM {$table_subs} sub WHERE sub.task_id = t.id) AS submissions_count,
                       (SELECT COUNT(*) FROM {$table_subs} sub WHERE sub.task_id = t.id AND sub.status IN ('submitted','late')) AS pending_grade_count
                FROM {$table_tasks} t
                LEFT JOIN {$table_prog} p ON p.id = t.program_id
                LEFT JOIN {$table_subj} s ON s.id = t.subject_id
                LEFT JOIN {$wpdb->users} ut ON ut.ID = s.default_teacher_id
                LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
                LEFT JOIN {$table_books} b ON b.id = t.book_id
                WHERE {$where_sql}
                ORDER BY t.due_datetime DESC, t.id DESC
                LIMIT %d OFFSET %d";

        $params[] = intval( $r['limit'] );
        $params[] = intval( $r['offset'] );

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        if ( ! is_array( $results ) ) {
            return [];
        }

        $student_id = intval( $r['student_id'] ?? 0 );
        $filtered = [];

        foreach ( $results as $row ) {
            $row->target_type = ! empty( $row->target_type ) ? $row->target_type : 'all';
            $target_ids = ! empty( $row->target_student_ids ) ? json_decode( $row->target_student_ids, true ) : [];
            $row->target_student_ids = is_array( $target_ids ) ? array_map( 'intval', $target_ids ) : [];
            $row->student_assignments = ! empty( $row->student_assignments ) ? json_decode( $row->student_assignments, true ) : [];

            if ( $student_id > 0 ) {
                if ( $row->target_type !== 'all' && ! in_array( $student_id, $row->target_student_ids, true ) ) {
                    continue;
                }
                // Si la tarea es diferenciada y este estudiante tiene un libro asignado específico, sobrescribirlo
                if ( $row->target_type === 'differentiated' && isset( $row->student_assignments[ $student_id ] ) ) {
                    $sa = $row->student_assignments[ $student_id ];
                    if ( ! empty( $sa['book_id'] ) ) {
                        $row->book_id = (int) $sa['book_id'];
                    }
                    if ( ! empty( $sa['book_title'] ) ) {
                        $row->book_title = $sa['book_title'];
                    }
                    if ( ! empty( $sa['book_author'] ) ) {
                        $row->book_author = $sa['book_author'];
                    }
                    if ( ! empty( $sa['instructions'] ) ) {
                        $row->student_specific_instructions = $sa['instructions'];
                    }
                }
            }

            if ( ! empty( $row->attachment_urls ) ) {
                $row->attachments = json_decode( $row->attachment_urls, true ) ?: [];
            } else {
                $row->attachments = [];
            }

            if ( ! empty( $row->book_cover_id ) ) {
                $row->book_cover_url = wp_get_attachment_image_url( (int) $row->book_cover_id, 'thumbnail' );
            } else {
                $row->book_cover_url = '';
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * Obtener detalle de una tarea
     *
     * @param int $id
     * @return object|null
     */
    public static function get_task( int $id ): ?object {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $table_prog  = $wpdb->prefix . 'aura_cal_programs';
        $table_subj  = $wpdb->prefix . 'aura_cal_subjects';
        $table_books = $wpdb->prefix . 'aura_library_books';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, p.name AS program_name, p.code AS program_code, p.area_id,
                    s.name AS subject_name, s.code AS subject_code,
                    COALESCE(ut.display_name, u.display_name) AS teacher_name,
                    u.display_name AS author_name,
                    b.title AS book_title, b.author AS book_author, b.dewey_number AS book_dewey,
                    b.isbn AS book_isbn, b.cover_image_id AS book_cover_id
             FROM {$table_tasks} t
             LEFT JOIN {$table_prog} p ON p.id = t.program_id
             LEFT JOIN {$table_subj} s ON s.id = t.subject_id
             LEFT JOIN {$wpdb->users} ut ON ut.ID = s.default_teacher_id
             LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
             LEFT JOIN {$table_books} b ON b.id = t.book_id
             WHERE t.id = %d AND t.deleted_at IS NULL",
            $id
        ) );

        if ( $row ) {
            $row->target_type = ! empty( $row->target_type ) ? $row->target_type : 'all';
            $target_ids = ! empty( $row->target_student_ids ) ? json_decode( $row->target_student_ids, true ) : [];
            $row->target_student_ids = is_array( $target_ids ) ? array_map( 'intval', $target_ids ) : [];
            $row->student_assignments = ! empty( $row->student_assignments ) ? json_decode( $row->student_assignments, true ) : [];

            if ( ! empty( $row->attachment_urls ) ) {
                $row->attachments = json_decode( $row->attachment_urls, true ) ?: [];
            } else {
                $row->attachments = [];
            }

            if ( ! empty( $row->book_cover_id ) ) {
                $row->book_cover_url = wp_get_attachment_image_url( (int) $row->book_cover_id, 'thumbnail' );
            } else {
                $row->book_cover_url = '';
            }
        }

        return $row;
    }

    /**
     * Guardar o actualizar una tarea
     *
     * @param array $data
     * @return int|WP_Error
     */
    public static function save_task( array $data ) {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';

        $id         = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;
        $program_id = ! empty( $data['program_id'] ) ? intval( $data['program_id'] ) : 0;
        $subject_id = ! empty( $data['subject_id'] ) ? intval( $data['subject_id'] ) : null;
        $event_id   = ! empty( $data['event_id'] ) ? intval( $data['event_id'] ) : null;
        $book_id    = ! empty( $data['book_id'] ) ? intval( $data['book_id'] ) : null;
        $title      = sanitize_text_field( $data['title'] ?? '' );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'El título de la tarea es obligatorio.', 'aura' ) );
        }

        if ( ! $program_id ) {
            return new WP_Error( 'missing_program', __( 'Debe asociar un programa.', 'aura' ) );
        }

        $due_dt = sanitize_text_field( $data['due_datetime'] ?? '' );
        if ( empty( $due_dt ) ) {
            $due_dt = date( 'Y-m-d 23:59:59', strtotime( '+7 days' ) );
        }

        $submission_type = ! empty( $data['submission_type'] ) && in_array( $data['submission_type'], [ 'text_only', 'file_only', 'text_or_file' ], true )
            ? sanitize_text_field( $data['submission_type'] )
            : 'text_or_file';

        $min_words = ! empty( $data['min_words'] ) ? max( 0, intval( $data['min_words'] ) ) : 0;

        $target_type = ! empty( $data['target_type'] ) && in_array( $data['target_type'], [ 'all', 'individual', 'differentiated' ], true )
            ? sanitize_text_field( $data['target_type'] )
            : 'all';

        $target_student_ids_json  = null;
        $student_assignments_json = null;

        if ( $target_type === 'individual' ) {
            $raw_ids = $data['target_student_ids'] ?? [];
            if ( is_string( $raw_ids ) ) {
                $decoded = json_decode( stripslashes( $raw_ids ), true );
                $raw_ids = is_array( $decoded ) ? $decoded : explode( ',', $raw_ids );
            }
            $clean_ids = [];
            if ( is_array( $raw_ids ) ) {
                foreach ( $raw_ids as $sid ) {
                    $sid_int = intval( $sid );
                    if ( $sid_int > 0 ) {
                        $clean_ids[] = $sid_int;
                    }
                }
            }
            $clean_ids = array_values( array_unique( $clean_ids ) );
            $target_student_ids_json = wp_json_encode( $clean_ids );
        } elseif ( $target_type === 'differentiated' ) {
            $raw_assignments = $data['student_assignments'] ?? [];
            if ( is_string( $raw_assignments ) ) {
                $raw_assignments = json_decode( stripslashes( $raw_assignments ), true ) ?: [];
            }
            $clean_assignments = [];
            $clean_ids = [];
            if ( is_array( $raw_assignments ) ) {
                foreach ( $raw_assignments as $sid => $asgn ) {
                    $sid_int = intval( $sid );
                    if ( $sid_int > 0 && is_array( $asgn ) ) {
                        $clean_assignments[ $sid_int ] = [
                            'book_id'      => intval( $asgn['book_id'] ?? 0 ),
                            'book_title'   => sanitize_text_field( $asgn['book_title'] ?? '' ),
                            'book_author'  => sanitize_text_field( $asgn['book_author'] ?? '' ),
                            'instructions' => sanitize_textarea_field( $asgn['instructions'] ?? '' ),
                        ];
                        $clean_ids[] = $sid_int;
                    }
                }
            }
            $target_student_ids_json  = wp_json_encode( array_values( array_unique( $clean_ids ) ) );
            $student_assignments_json = wp_json_encode( $clean_assignments );
        }

        $attachments_json = null;
        if ( ! empty( $data['attachment_urls'] ) ) {
            if ( is_array( $data['attachment_urls'] ) ) {
                $clean_urls = array_map( 'esc_url_raw', $data['attachment_urls'] );
                $attachments_json = wp_json_encode( $clean_urls );
            } else {
                $attachments_json = sanitize_textarea_field( $data['attachment_urls'] );
            }
        }

        $fields = [
            'program_id'          => $program_id,
            'subject_id'          => $subject_id,
            'event_id'            => $event_id,
            'book_id'             => $book_id,
            'title'               => $title,
            'description'         => wp_kses_post( $data['description'] ?? '' ),
            'due_datetime'        => $due_dt,
            'max_score'           => floatval( $data['max_score'] ?? 100 ),
            'weight'              => floatval( $data['weight'] ?? 1.0 ),
            'attachment_urls'     => $attachments_json,
            'submission_type'     => $submission_type,
            'min_words'           => $min_words,
            'target_type'         => $target_type,
            'target_student_ids'  => $target_student_ids_json,
            'student_assignments' => $student_assignments_json,
            'status'              => in_array( $data['status'] ?? '', [ 'published', 'draft', 'closed' ], true ) ? $data['status'] : 'published',
            'updated_at'          => current_time( 'mysql' ),
        ];

        $formats = [
            '%d', // program_id
            $subject_id !== null ? '%d' : null,
            $event_id !== null ? '%d' : null,
            $book_id !== null ? '%d' : null,
            '%s', // title
            '%s', // description
            '%s', // due_datetime
            '%f', // max_score
            '%f', // weight
            $attachments_json !== null ? '%s' : null,
            '%s', // submission_type
            '%d', // min_words
            '%s', // target_type
            $target_student_ids_json !== null ? '%s' : null,
            $student_assignments_json !== null ? '%s' : null,
            '%s', // status
            '%s', // updated_at
        ];

        // Limpiar formatos null si wpdb los espera alineados
        $clean_fields  = [];
        $clean_formats = [];
        $idx = 0;
        foreach ( $fields as $k => $v ) {
            $fmt = $formats[ $idx ] ?? '%s';
            if ( $v === null ) {
                $clean_fields[ $k ] = null;
                $clean_formats[]    = null;
            } else {
                $clean_fields[ $k ] = $v;
                $clean_formats[]    = $fmt ?: '%s';
            }
            $idx++;
        }

        if ( $id > 0 ) {
            $wpdb->update( $table_tasks, $clean_fields, [ 'id' => $id ], $clean_formats, [ '%d' ] );
            return $id;
        } else {
            $clean_fields['created_by'] = get_current_user_id();
            $clean_formats[]            = '%d';
            $clean_fields['created_at'] = current_time( 'mysql' );
            $clean_formats[]            = '%s';

            $wpdb->insert( $table_tasks, $clean_fields, $clean_formats );
            return (int) $wpdb->insert_id;
        }
    }

    /**
     * Eliminar tarea (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public static function delete_task( int $id ): bool {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $res = $wpdb->update(
            $table_tasks,
            [ 'deleted_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );
        return $res !== false;
    }

    /**
     * Obtener listado de estudiantes inscritos en un programa académico
     *
     * @param int $program_id
     * @param int $subject_id
     * @return array
     */
    public static function get_program_students( int $program_id, int $subject_id = 0 ): array {
        global $wpdb;
        $table_stud = $wpdb->prefix . 'aura_students';
        $table_enr  = $wpdb->prefix . 'aura_student_enrollments';
        $table_crs  = $wpdb->prefix . 'aura_student_courses';
        $table_prog = $wpdb->prefix . 'aura_cal_programs';

        $has_stud = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stud}'" ) === $table_stud;
        if ( ! $has_stud ) {
            return [];
        }

        $students = [];
        if ( $program_id > 0 && $wpdb->get_var( "SHOW TABLES LIKE '{$table_enr}'" ) === $table_enr ) {
            $students_sql = "SELECT DISTINCT st.id AS student_id, st.first_name, st.last_name, 
                                    COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, 
                                    st.email, st.phone, st.photo_url, st.wp_user_id, st.status
                             FROM {$table_enr} enr
                             JOIN {$table_stud} st ON st.id = enr.student_id
                             LEFT JOIN {$table_crs} c ON c.id = enr.course_id
                             LEFT JOIN {$table_prog} p ON p.id = %d
                             WHERE (enr.course_id = %d OR (p.name IS NOT NULL AND c.name = p.name))
                               AND enr.status IN ('active', 'completed', 'pending')
                             ORDER BY st.last_name ASC, st.first_name ASC";
            $students = $wpdb->get_results( $wpdb->prepare( $students_sql, $program_id, $program_id ) );
        }

        if ( empty( $students ) ) {
            $fallback_sql = "SELECT st.id AS student_id, st.first_name, st.last_name, 
                                    COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, 
                                    st.email, st.phone, st.photo_url, st.wp_user_id, st.status
                             FROM {$table_stud} st
                             WHERE st.status IN ('active', 'approved', 'applicant')
                             ORDER BY st.last_name ASC, st.first_name ASC";
            $students = $wpdb->get_results( $fallback_sql );
        }

        $formatted = [];
        if ( is_array( $students ) ) {
            foreach ( $students as $s ) {
                $avatar = ! empty( $s->photo_url ) ? esc_url( $s->photo_url ) : get_avatar_url( $s->email, [ 'size' => 64 ] );
                $formatted[] = [
                    'id'           => (int) $s->student_id,
                    'first_name'   => $s->first_name,
                    'last_name'    => $s->last_name,
                    'full_name'    => trim( $s->first_name . ' ' . $s->last_name ),
                    'student_code' => $s->student_code,
                    'email'        => $s->email,
                    'phone'        => $s->phone ?? '',
                    'avatar'       => $avatar,
                ];
            }
        }

        return $formatted;
    }

    /**
     * Obtener entregas de una tarea con estado completo (entregados y pendientes)
     * y libro individualizado asignado.
     *
     * @param int $task_id
     * @return array
     */
    public static function get_submissions( int $task_id ): array {
        global $wpdb;
        $table_subs = $wpdb->prefix . 'aura_cal_task_submissions';
        $table_stud = $wpdb->prefix . 'aura_students';

        $has_students = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stud}'" ) === $table_stud;
        if ( ! $has_students ) {
            return [];
        }

        $task = self::get_task( $task_id );
        if ( ! $task ) {
            return [];
        }

        // Determinar qué estudiantes deben realizar la tarea
        $target_type = $task->target_type ?: 'all';
        $all_program_students = self::get_program_students( (int) $task->program_id, (int) $task->subject_id );
        
        $assigned_students = [];
        if ( $target_type === 'individual' || $target_type === 'differentiated' ) {
            $target_ids = array_map( 'intval', $task->target_student_ids ?: [] );
            foreach ( $all_program_students as $st ) {
                if ( in_array( (int) $st['id'], $target_ids, true ) ) {
                    $assigned_students[ (int) $st['id'] ] = $st;
                }
            }
            // Si algún ID no estaba en all_program_students, consultar directo
            foreach ( $target_ids as $tid ) {
                if ( ! isset( $assigned_students[ $tid ] ) && $tid > 0 ) {
                    $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, first_name, last_name, id_number, email, photo_url FROM {$table_stud} WHERE id = %d", $tid ) );
                    if ( $row ) {
                        $assigned_students[ $tid ] = [
                            'id'           => (int) $row->id,
                            'first_name'   => $row->first_name,
                            'last_name'    => $row->last_name,
                            'full_name'    => trim( $row->first_name . ' ' . $row->last_name ),
                            'student_code' => $row->id_number ?: ( 'EST-' . $row->id ),
                            'email'        => $row->email,
                            'avatar'       => ! empty( $row->photo_url ) ? esc_url( $row->photo_url ) : get_avatar_url( $row->email, [ 'size' => 64 ] ),
                        ];
                    }
                }
            }
        } else {
            // Modalidad 'all': todos los estudiantes del programa
            foreach ( $all_program_students as $st ) {
                $assigned_students[ (int) $st['id'] ] = $st;
            }
        }

        // Obtener entregas existentes
        $sql = "SELECT sub.*, st.first_name, st.last_name, COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, st.email,
                       u.display_name AS graded_by_name
                FROM {$table_subs} sub
                JOIN {$table_stud} st ON st.id = sub.student_id
                LEFT JOIN {$wpdb->users} u ON u.ID = sub.graded_by
                WHERE sub.task_id = %d
                ORDER BY sub.submitted_at DESC";
        $existing_subs = $wpdb->get_results( $wpdb->prepare( $sql, $task_id ) );
        $subs_by_student = [];
        if ( is_array( $existing_subs ) ) {
            foreach ( $existing_subs as $es ) {
                $subs_by_student[ (int) $es->student_id ] = $es;
            }
        }

        // Fusionar lista completa de asignados con sus entregas y su libro individualizado
        $final_list = [];
        $differentiated_assignments = $task->student_assignments ?: [];

        foreach ( $assigned_students as $sid => $st_info ) {
            $sub = $subs_by_student[ $sid ] ?? null;

            // Determinar libro asignado a este estudiante en particular
            $book_id          = $task->book_id;
            $book_title       = $task->book_title;
            $book_author      = $task->book_author;
            $custom_instructions = '';

            if ( $target_type === 'differentiated' && isset( $differentiated_assignments[ $sid ] ) ) {
                $da = $differentiated_assignments[ $sid ];
                if ( ! empty( $da['book_id'] ) ) {
                    $book_id = (int) $da['book_id'];
                }
                if ( ! empty( $da['book_title'] ) ) {
                    $book_title = $da['book_title'];
                }
                if ( ! empty( $da['book_author'] ) ) {
                    $book_author = $da['book_author'];
                }
                if ( ! empty( $da['instructions'] ) ) {
                    $custom_instructions = $da['instructions'];
                }
            }

            if ( $sub ) {
                $sub->assigned_book_id          = $book_id;
                $sub->assigned_book_title       = $book_title;
                $sub->assigned_book_author      = $book_author;
                $sub->assigned_instructions     = $custom_instructions;
                $sub->avatar                    = $st_info['avatar'];
                $final_list[] = $sub;
            } else {
                // Registro sintético de estudiante pendiente
                $dummy = (object) [
                    'id'                     => 0,
                    'task_id'                => $task_id,
                    'student_id'             => $sid,
                    'first_name'             => $st_info['first_name'],
                    'last_name'              => $st_info['last_name'],
                    'student_code'           => $st_info['student_code'],
                    'email'                  => $st_info['email'],
                    'avatar'                 => $st_info['avatar'],
                    'submission_text'        => null,
                    'attachment_urls'        => null,
                    'submitted_at'           => null,
                    'status'                 => 'pending_submission',
                    'score'                  => null,
                    'feedback'               => null,
                    'graded_by'              => null,
                    'graded_at'              => null,
                    'graded_by_name'         => null,
                    'assigned_book_id'       => $book_id,
                    'assigned_book_title'    => $book_title,
                    'assigned_book_author'   => $book_author,
                    'assigned_instructions'  => $custom_instructions,
                ];
                $final_list[] = $dummy;
            }
        }

        // Ordenar: primero los que han entregado (para calificar), luego los pendientes
        usort( $final_list, function( $a, $b ) {
            $a_has = ( $a->status !== 'pending_submission' ) ? 1 : 0;
            $b_has = ( $b->status !== 'pending_submission' ) ? 1 : 0;
            if ( $a_has !== $b_has ) {
                return $b_has - $a_has;
            }
            return strcmp( $a->last_name, $b->last_name );
        } );

        return $final_list;
    }

    /**
     * Calificar entrega de tarea
     *
     * @param int $submission_id
     * @param float $score
     * @param string $feedback
     * @return bool
     */
    public static function grade_submission( int $submission_id, float $score, string $feedback = '' ): bool {
        global $wpdb;
        $table_subs = $wpdb->prefix . 'aura_cal_task_submissions';

        $updated = $wpdb->update(
            $table_subs,
            [
                'score'     => $score,
                'feedback'  => sanitize_textarea_field( $feedback ),
                'status'    => 'graded',
                'graded_by' => get_current_user_id(),
                'graded_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $submission_id ],
            [ '%f', '%s', '%s', '%d', '%s' ],
            [ '%d' ]
        );

        return $updated !== false;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_tasks(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_view_tasks' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $tasks = self::get_tasks( [
            'program_id' => intval( $_POST['program_id'] ?? 0 ),
            'subject_id' => intval( $_POST['subject_id'] ?? 0 ),
            'status'     => sanitize_text_field( $_POST['status'] ?? '' ),
            'search'     => sanitize_text_field( $_POST['search'] ?? '' ),
        ] );

        wp_send_json_success( [ 'tasks' => $tasks ] );
    }

    public static function ajax_get_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_view_tasks' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $task = self::get_task( $id );
        if ( ! $task ) {
            wp_send_json_error( [ 'message' => __( 'Tarea no encontrada.', 'aura' ) ] );
        }

        wp_send_json_success( [ 'task' => $task ] );
    }

    public static function ajax_save_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_tasks' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'aura_teach_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $res = self::save_task( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'Tarea guardada correctamente.', 'aura' ),
            'task_id' => $res,
        ] );
    }

    public static function ajax_delete_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_delete_tasks' ) && ! current_user_can( 'aura_cal_manage_tasks' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $ok = self::delete_task( $id );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Tarea eliminada.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar la tarea.', 'aura' ) ] );
        }
    }

    public static function ajax_get_submissions(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_grade_tasks' ) && ! current_user_can( 'aura_cal_manage_tasks' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'aura_teach_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $task_id = intval( $_POST['task_id'] ?? 0 );
        if ( ! $task_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de tarea inválido.', 'aura' ) ] );
        }

        $submissions = self::get_submissions( $task_id );
        wp_send_json_success( [ 'submissions' => $submissions ] );
    }

    public static function ajax_grade_submission(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_grade_tasks' ) && ! current_user_can( 'aura_cal_manage_grades' ) && ! current_user_can( 'aura_record_grades' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para calificar.', 'aura' ) ] );
        }

        $submission_id = intval( $_POST['submission_id'] ?? 0 );
        $score         = floatval( $_POST['score'] ?? 0 );
        $feedback      = sanitize_textarea_field( $_POST['feedback'] ?? '' );

        if ( ! $submission_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de entrega inválido.', 'aura' ) ] );
        }

        $ok = self::grade_submission( $submission_id, $score, $feedback );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Entrega calificada exitosamente.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Error al calificar la entrega.', 'aura' ) ] );
        }
    }

    public static function ajax_submit_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_submit_tasks' ) && ! current_user_can( 'aura_cal_view_tasks' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para entregar tareas.', 'aura' ) ] );
        }

        // Identificar al estudiante por su user_id actual
        global $wpdb;
        $table_stud = $wpdb->prefix . 'aura_students';
        $table_subs = $wpdb->prefix . 'aura_cal_task_submissions';
        $table_tasks= $wpdb->prefix . 'aura_cal_tasks';

        $user_id    = get_current_user_id();
        $student_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_stud} WHERE wp_user_id = %d", $user_id ) );

        if ( ! $student_id ) {
            wp_send_json_error( [ 'message' => __( 'Perfil de estudiante no encontrado para este usuario.', 'aura' ) ] );
        }

        $task_id = intval( $_POST['task_id'] ?? 0 );
        if ( ! $task_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de tarea inválido.', 'aura' ) ] );
        }

        $task = self::get_task( $task_id );
        if ( ! $task || $task->status === 'closed' ) {
            wp_send_json_error( [ 'message' => __( 'La tarea no existe o ya está cerrada.', 'aura' ) ] );
        }

        $submission_text = sanitize_textarea_field( $_POST['submission_text'] ?? '' );
        $attachments     = sanitize_text_field( $_POST['attachment_urls'] ?? '' );

        // Validaciones académicas por modalidad de entrega y mínimo de palabras
        $submission_type = $task->submission_type ?? 'text_or_file';
        $min_words       = intval( $task->min_words ?? 0 );

        if ( $submission_type === 'text_only' && empty( trim( $submission_text ) ) ) {
            wp_send_json_error( [ 'message' => __( 'Esta tarea exige la entrega de un resumen por escrito en la plataforma.', 'aura' ) ] );
        }

        if ( $submission_type === 'file_only' && empty( trim( $attachments ) ) ) {
            wp_send_json_error( [ 'message' => __( 'Esta tarea exige adjuntar un archivo (PDF o documento).', 'aura' ) ] );
        }

        if ( $min_words > 0 ) {
            $words_arr   = preg_split( '/\s+/u', trim( $submission_text ), -1, PREG_SPLIT_NO_EMPTY );
            $words_count = is_array( $words_arr ) ? count( $words_arr ) : 0;
            if ( $words_count < $min_words ) {
                wp_send_json_error( [
                    'message' => sprintf(
                        __( 'El resumen requiere un mínimo de %1$d palabras. Actualmente tu texto cuenta con %2$d palabras.', 'aura' ),
                        $min_words,
                        $words_count
                    ),
                ] );
            }
        }

        $now_str = current_time( 'mysql' );
        $is_late = ! empty( $task->due_datetime ) && ( strtotime( $now_str ) > strtotime( $task->due_datetime ) );
        $sub_status = $is_late ? 'late' : 'submitted';

        $existing_sub_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_subs} WHERE task_id = %d AND student_id = %d",
            $task_id,
            $student_id
        ) );

        if ( $existing_sub_id ) {
            $wpdb->update(
                $table_subs,
                [
                    'submission_text' => $submission_text,
                    'attachment_urls' => $attachments,
                    'submitted_at'    => $now_str,
                    'status'          => $sub_status,
                ],
                [ 'id' => $existing_sub_id ],
                [ '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $table_subs,
                [
                    'task_id'         => $task_id,
                    'student_id'      => $student_id,
                    'submission_text' => $submission_text,
                    'attachment_urls' => $attachments,
                    'submitted_at'    => $now_str,
                    'status'          => $sub_status,
                ],
                [ '%d', '%d', '%s', '%s', '%s', '%s' ]
            );
        }

        wp_send_json_success( [ 'message' => __( '¡Tarea entregada correctamente!', 'aura' ) ] );
    }

    /**
     * AJAX: Búsqueda reactiva de libros en el módulo de Biblioteca para asignar a tareas
     */
    public static function ajax_search_library_books(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_tasks' ) && ! current_user_can( 'aura_cal_view_tasks' ) && ! current_user_can( 'aura_teach_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        global $wpdb;
        $table_books = $wpdb->prefix . 'aura_library_books';
        $has_table   = $wpdb->get_var( "SHOW TABLES LIKE '{$table_books}'" ) === $table_books;

        if ( ! $has_table ) {
            wp_send_json_success( [ 'books' => [] ] );
        }

        $term   = sanitize_text_field( $_POST['term'] ?? '' );
        $where  = [ 'deleted_at IS NULL' ];
        $params = [];

        if ( ! empty( $term ) ) {
            $like     = '%' . $wpdb->esc_like( $term ) . '%';
            $where[]  = '(title LIKE %s OR author LIKE %s OR dewey_number LIKE %s OR isbn LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT id, title, author, dewey_number, isbn, cover_image_id, status, available_copies
                FROM {$table_books}
                WHERE {$where_sql}
                ORDER BY title ASC
                LIMIT 50";

        $rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
        $books = [];

        if ( is_array( $rows ) ) {
            foreach ( $rows as $b ) {
                $cover_url = '';
                if ( ! empty( $b->cover_image_id ) ) {
                    $cover_url = wp_get_attachment_image_url( (int) $b->cover_image_id, 'thumbnail' );
                }
                $books[] = [
                    'id'               => (int) $b->id,
                    'title'            => $b->title,
                    'author'           => $b->author,
                    'dewey'            => $b->dewey_number,
                    'isbn'             => $b->isbn,
                    'cover_url'        => $cover_url,
                    'status'           => $b->status,
                    'available_copies' => (int) $b->available_copies,
                ];
            }
        }

        wp_send_json_success( [ 'books' => $books ] );
    }

    /**
     * AJAX: Obtener lista de estudiantes de un programa con su avatar y código
     */
    public static function ajax_get_program_students(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_tasks' ) && ! current_user_can( 'aura_cal_view_tasks' ) && ! current_user_can( 'aura_teach_calendar' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $program_id = intval( $_POST['program_id'] ?? 0 );
        $subject_id = intval( $_POST['subject_id'] ?? 0 );

        $students = self::get_program_students( $program_id, $subject_id );
        wp_send_json_success( [ 'students' => $students ] );
    }
}
