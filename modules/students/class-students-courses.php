<?php
/**
 * CRUD de Cursos y Programas — Fase 2
 *
 * Responsabilidades:
 *  - Registrar hooks AJAX para crear, editar, listar y archivar cursos
 *  - Cargar el template de listado con modal de creación/edición embebido
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Courses {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'maybe_upgrade_table' ] );
        add_action( 'wp_ajax_aura_students_save_course',      [ __CLASS__, 'ajax_save_course' ] );
        add_action( 'wp_ajax_aura_students_delete_course',    [ __CLASS__, 'ajax_delete_course' ] );
        add_action( 'wp_ajax_aura_students_get_course',       [ __CLASS__, 'ajax_get_course' ] );
        add_action( 'wp_ajax_aura_students_list_courses',     [ __CLASS__, 'ajax_list_courses' ] );
        add_action( 'wp_ajax_aura_students_get_areas',        [ __CLASS__, 'ajax_get_areas' ] );
        add_action( 'wp_ajax_aura_students_get_instructors',  [ __CLASS__, 'ajax_get_instructors' ] );
        add_action( 'wp_ajax_aura_students_get_finance_cats', [ __CLASS__, 'ajax_get_finance_cats' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        require_once AURA_PLUGIN_DIR . 'templates/students/course-list.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: GUARDAR CURSO (CREAR / EDITAR)
    // ─────────────────────────────────────────────────────────────


    /** Bandera para evitar bucles infinitos de sincronización entre módulos */
    public static $is_syncing = false;

    /**
     * Asegurar que las columnas de enlace bidireccional existan en la BD
     */
    public static function maybe_upgrade_table(): void {
        global $wpdb;
        $t_courses  = $wpdb->prefix . 'aura_student_courses';
        $t_programs = $wpdb->prefix . 'aura_cal_programs';

        // 1. Columna cal_program_id en aura_student_courses
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_courses}'" ) === $t_courses ) {
            $cols_c = $wpdb->get_col( "SHOW COLUMNS FROM `{$t_courses}`" );
            if ( ! in_array( 'cal_program_id', $cols_c, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_courses}` ADD COLUMN `cal_program_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `finance_cat_id`, ADD INDEX `idx_cal_program` (`cal_program_id`)" );
            }
        }

        // 2. Columna course_id en aura_cal_programs
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_programs}'" ) === $t_programs ) {
            $cols_p = $wpdb->get_col( "SHOW COLUMNS FROM `{$t_programs}`" );
            if ( ! in_array( 'course_id', $cols_p, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_programs}` ADD COLUMN `course_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `area_id`, ADD INDEX `idx_course_id` (`course_id`)" );
            }
        }

        // 3. Ejecutar sincronización y emparejamiento automático de una sola vez
        if ( ! get_option( 'aura_courses_programs_synced_v1' ) ) {
            self::sync_all_unlinked();
            update_option( 'aura_courses_programs_synced_v1', true, false );
        }
    }

    /**
     * Sincronizar un curso de Estudiantes hacia Programas de Calendario
     */
    public static function sync_to_calendar_program( int $course_id ): ?int {
        if ( self::$is_syncing || $course_id <= 0 ) {
            return null;
        }

        self::$is_syncing = true;
        global $wpdb;
        $t_courses  = $wpdb->prefix . 'aura_student_courses';
        $t_programs = $wpdb->prefix . 'aura_cal_programs';

        try {
            $course = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_courses} WHERE id = %d", $course_id ) );
            if ( ! $course ) {
                self::$is_syncing = false;
                return null;
            }

            // Buscar programa correspondiente en Calendario
            $prog_id = null;
            if ( ! empty( $course->cal_program_id ) ) {
                $prog_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_programs} WHERE id = %d", $course->cal_program_id ) );
            }
            if ( ! $prog_id ) {
                $prog_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_programs} WHERE course_id = %d", $course->id ) );
            }
            if ( ! $prog_id ) {
                $prog_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_programs} WHERE name = %s AND deleted_at IS NULL LIMIT 1", $course->name ) );
            }

            $area_id = ! empty( $course->area_id ) ? (int) $course->area_id : null;
            $coord_id = ! empty( $course->instructor_id ) ? (int) $course->instructor_id : null;
            $is_archived = ( $course->status === 'archived' );

            if ( $prog_id ) {
                // Actualizar programa existente
                $wpdb->update(
                    $t_programs,
                    [
                        'name'                 => $course->name,
                        'description'          => $course->description ?? '',
                        'area_id'              => $area_id,
                        'start_date'           => ! empty( $course->start_date ) ? $course->start_date : null,
                        'end_date'             => ! empty( $course->end_date ) ? $course->end_date : null,
                        'coordinator_id'       => $coord_id,
                        'course_id'            => $course->id,
                        'status'               => $is_archived ? 'archived' : 'active',
                        'deleted_at'           => $is_archived ? current_time( 'mysql' ) : null,
                        'updated_at'           => current_time( 'mysql' ),
                    ],
                    [ 'id' => $prog_id ]
                );

                if ( (int) $course->cal_program_id !== $prog_id ) {
                    $wpdb->update( $t_courses, [ 'cal_program_id' => $prog_id ], [ 'id' => $course->id ] );
                }
            } else {
                // Insertar nuevo programa en Calendario
                $slug_code = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $course->slug ?: $course->name ), 0, 8 ) );
                if ( empty( $slug_code ) ) {
                    $slug_code = 'PROG';
                }
                $code_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_programs} WHERE code = %s", $slug_code ) );
                if ( $code_exists ) {
                    $slug_code .= '-' . $course->id;
                }

                $inserted = $wpdb->insert(
                    $t_programs,
                    [
                        'name'                 => $course->name,
                        'code'                 => $slug_code,
                        'description'          => $course->description ?? '',
                        'color'                => '#6366f1',
                        'area_id'              => $area_id,
                        'start_date'           => ! empty( $course->start_date ) ? $course->start_date : null,
                        'end_date'             => ! empty( $course->end_date ) ? $course->end_date : null,
                        'coordinator_id'       => $coord_id,
                        'course_id'            => $course->id,
                        'status'               => $is_archived ? 'archived' : 'active',
                        'deleted_at'           => $is_archived ? current_time( 'mysql' ) : null,
                        'created_by'           => $course->created_by ?: get_current_user_id(),
                        'created_at'           => $course->created_at ?: current_time( 'mysql' ),
                        'updated_at'           => current_time( 'mysql' ),
                    ]
                );

                if ( $inserted ) {
                    $prog_id = (int) $wpdb->insert_id;
                    $wpdb->update( $t_courses, [ 'cal_program_id' => $prog_id ], [ 'id' => $course->id ] );
                }
            }

            self::$is_syncing = false;
            return $prog_id;
        } catch ( \Throwable $e ) {
            self::$is_syncing = false;
            return null;
        }
    }

    /**
     * Sincronizar y emparejar todos los cursos y programas existentes
     */
    public static function sync_all_unlinked(): void {
        if ( self::$is_syncing ) return;
        self::$is_syncing = true;
        global $wpdb;
        $t_courses  = $wpdb->prefix . 'aura_student_courses';
        $t_programs = $wpdb->prefix . 'aura_cal_programs';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_courses}'" ) !== $t_courses || $wpdb->get_var( "SHOW TABLES LIKE '{$t_programs}'" ) !== $t_programs ) {
            self::$is_syncing = false;
            return;
        }

        // 1. Sincronizar de Cursos hacia Programas
        $courses = $wpdb->get_results( "SELECT * FROM {$t_courses} WHERE status != 'archived'" );
        foreach ( $courses as $c ) {
            $prog = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$t_programs} WHERE (id = %d OR course_id = %d OR name = %s) AND deleted_at IS NULL LIMIT 1",
                (int) ($c->cal_program_id ?? 0), (int) $c->id, $c->name
            ) );

            if ( $prog ) {
                $wpdb->update( $t_programs, [ 'course_id' => $c->id ], [ 'id' => $prog->id ] );
                $wpdb->update( $t_courses, [ 'cal_program_id' => $prog->id ], [ 'id' => $c->id ] );
            } else {
                // Crear programa
                $code = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $c->slug ?: $c->name ), 0, 8 ) );
                $code_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_programs} WHERE code = %s", $code ) );
                if ( $code_exists ) $code .= '-' . $c->id;

                $wpdb->insert( $t_programs, [
                    'name'           => $c->name,
                    'code'           => $code,
                    'description'    => $c->description ?? '',
                    'area_id'        => ! empty( $c->area_id ) ? (int) $c->area_id : null,
                    'start_date'     => ! empty( $c->start_date ) ? $c->start_date : null,
                    'end_date'       => ! empty( $c->end_date ) ? $c->end_date : null,
                    'coordinator_id' => ! empty( $c->instructor_id ) ? (int) $c->instructor_id : null,
                    'course_id'      => $c->id,
                    'status'         => 'active',
                    'color'          => '#6366f1',
                    'created_by'     => $c->created_by ?: get_current_user_id(),
                    'created_at'     => $c->created_at ?: current_time( 'mysql' ),
                ] );
                $new_pid = (int) $wpdb->insert_id;
                if ( $new_pid ) {
                    $wpdb->update( $t_courses, [ 'cal_program_id' => $new_pid ], [ 'id' => $c->id ] );
                }
            }
        }

        // 2. Programas que no existan en cursos
        $programs = $wpdb->get_results( "SELECT * FROM {$t_programs} WHERE deleted_at IS NULL AND (course_id IS NULL OR course_id = 0)" );
        foreach ( $programs as $p ) {
            $match_c = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$t_courses} WHERE name = %s LIMIT 1", $p->name ) );
            if ( $match_c ) {
                $wpdb->update( $t_programs, [ 'course_id' => $match_c->id ], [ 'id' => $p->id ] );
                $wpdb->update( $t_courses, [ 'cal_program_id' => $p->id ], [ 'id' => $match_c->id ] );
            } else {
                $slug = self::generate_slug( $p->name );
                $wpdb->insert( $t_courses, [
                    'name'           => $p->name,
                    'slug'           => $slug,
                    'description'    => $p->description ?? '',
                    'area_id'        => ! empty( $p->area_id ) ? (int) $p->area_id : null,
                    'instructor_id'  => ! empty( $p->coordinator_id ) ? (int) $p->coordinator_id : null,
                    'start_date'     => ! empty( $p->start_date ) ? $p->start_date : null,
                    'end_date'       => ! empty( $p->end_date ) ? $p->end_date : null,
                    'status'         => $p->status === 'active' ? 'active' : 'inactive',
                    'cal_program_id' => $p->id,
                    'created_by'     => $p->created_by ?: get_current_user_id(),
                    'created_at'     => $p->created_at ?: current_time( 'mysql' ),
                ] );
                $new_cid = (int) $wpdb->insert_id;
                if ( $new_cid ) {
                    $wpdb->update( $t_programs, [ 'course_id' => $new_cid ], [ 'id' => $p->id ] );
                }
            }
        }

        self::$is_syncing = false;
    }

    public static function ajax_save_course(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_courses_manage' ) &&
            ! current_user_can( 'aura_areas_enrollment_manage' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_student_courses';

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        // Sanitize inputs
        $name           = isset( $_POST['name'] )           ? sanitize_text_field( $_POST['name'] )           : '';
        $description    = isset( $_POST['description'] )    ? sanitize_textarea_field( $_POST['description'] ) : '';
        $area_id        = isset( $_POST['area_id'] )        ? absint( $_POST['area_id'] )                      : 0;
        $instructor_id  = isset( $_POST['instructor_id'] )  ? absint( $_POST['instructor_id'] )                : 0;
        $duration_weeks = isset( $_POST['duration_weeks'] ) ? absint( $_POST['duration_weeks'] )               : 0;
        $max_students   = isset( $_POST['max_students'] )   ? absint( $_POST['max_students'] )                 : 0;
        $base_cost      = isset( $_POST['base_cost'] )      ? (float) $_POST['base_cost']                      : 0.00;
        $currency       = isset( $_POST['currency'] )       ? sanitize_text_field( $_POST['currency'] )        : 'USD';
        $status         = isset( $_POST['status'] )         ? sanitize_text_field( $_POST['status'] )          : 'active';
        $start_date     = isset( $_POST['start_date'] )     ? sanitize_text_field( $_POST['start_date'] )      : '';
        $end_date       = isset( $_POST['end_date'] )       ? sanitize_text_field( $_POST['end_date'] )        : '';
        $finance_cat_id = isset( $_POST['finance_cat_id'] ) ? absint( $_POST['finance_cat_id'] )               : 0;

        // Validation
        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => __( 'El nombre del curso es obligatorio.', 'aura-suite' ) ] );
        }

        $valid_statuses = [ 'active', 'inactive', 'archived' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $status = 'active';
        }

        $valid_currencies = [ 'USD', 'EUR', 'COP', 'MXN', 'PEN', 'ARS', 'CLP', 'BRL', 'GTQ', 'HNL', 'NIO', 'DOP', 'CRC', 'BOB' ];
        if ( ! in_array( $currency, $valid_currencies, true ) ) {
            $currency = 'USD';
        }

        if ( $base_cost < 0 ) {
            $base_cost = 0.00;
        }

        $start_date     = ( $start_date && strtotime( $start_date ) ) ? $start_date : null;
        $end_date       = ( $end_date   && strtotime( $end_date ) )   ? $end_date   : null;
        $area_id        = $area_id        ?: null;
        $instructor_id  = $instructor_id  ?: null;
        $finance_cat_id = $finance_cat_id ?: null;
        $duration_weeks = $duration_weeks ?: null;

        // Aislamiento multi-área CBAC
        $is_restricted = (
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'aura_areas_view_all' )
        );

        if ( $is_restricted && class_exists( 'Aura_Areas_Setup' ) ) {
            $allowed_area_ids = Aura_Areas_Setup::get_user_area_ids( get_current_user_id() );
            if ( $area_id && ! in_array( (int) $area_id, $allowed_area_ids, true ) ) {
                wp_send_json_error( [ 'message' => __( 'No tienes autorización para asignar cursos a esta área.', 'aura-suite' ) ], 403 );
            }
            if ( $id > 0 ) {
                $prev_area_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT area_id FROM {$table} WHERE id = %d", $id ) );
                if ( $prev_area_id && ! in_array( $prev_area_id, $allowed_area_ids, true ) ) {
                    wp_send_json_error( [ 'message' => __( 'No tienes autorización para modificar cursos de esta área.', 'aura-suite' ) ], 403 );
                }
            }
        }

        if ( $id > 0 ) {
            // UPDATE
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d",
                $id
            ) );
            if ( ! $existing ) {
                wp_send_json_error( [ 'message' => __( 'Curso no encontrado.', 'aura-suite' ) ] );
            }

            $slug = self::generate_slug( $name, $id );

            $result = $wpdb->update(
                $table,
                [
                    'name'           => $name,
                    'slug'           => $slug,
                    'description'    => $description,
                    'area_id'        => $area_id,
                    'instructor_id'  => $instructor_id,
                    'duration_weeks' => $duration_weeks,
                    'max_students'   => $max_students,
                    'base_cost'      => $base_cost,
                    'currency'       => $currency,
                    'status'         => $status,
                    'start_date'     => $start_date,
                    'end_date'       => $end_date,
                    'finance_cat_id' => $finance_cat_id,
                ],
                [ 'id' => $id ]
            );

            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el curso. Intente de nuevo.', 'aura-suite' ) ] );
            }

            self::sync_to_calendar_program( $id );

            wp_send_json_success( [
                'message' => __( 'Curso actualizado y sincronizado correctamente con Calendario.', 'aura-suite' ),
                'id'      => $id,
            ] );

        } else {
            // INSERT
            $slug = self::generate_slug( $name );

            $result = $wpdb->insert(
                $table,
                [
                    'name'           => $name,
                    'slug'           => $slug,
                    'description'    => $description,
                    'area_id'        => $area_id,
                    'instructor_id'  => $instructor_id,
                    'duration_weeks' => $duration_weeks,
                    'max_students'   => $max_students,
                    'base_cost'      => $base_cost,
                    'currency'       => $currency,
                    'status'         => $status,
                    'start_date'     => $start_date,
                    'end_date'       => $end_date,
                    'finance_cat_id' => $finance_cat_id,
                    'created_by'     => get_current_user_id(),
                ]
            );

            if ( ! $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al crear el curso. Intente de nuevo.', 'aura-suite' ) ] );
            }

            $new_course_id = (int) $wpdb->insert_id;
            self::sync_to_calendar_program( $new_course_id );

            wp_send_json_success( [
                'message' => __( 'Curso creado y sincronizado correctamente con Calendario.', 'aura-suite' ),
                'id'      => $new_course_id,
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ARCHIVAR CURSO (soft-delete)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_course(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de curso inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $enroll_table  = $wpdb->prefix . 'aura_student_enrollments';
        $courses_table = $wpdb->prefix . 'aura_student_courses';

        $is_restricted = (
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'aura_areas_view_all' )
        );

        if ( $is_restricted && class_exists( 'Aura_Areas_Setup' ) ) {
            $course_area_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT area_id FROM {$courses_table} WHERE id = %d", $id ) );
            $allowed_area_ids = Aura_Areas_Setup::get_user_area_ids( get_current_user_id() );
            if ( $course_area_id && ! in_array( $course_area_id, $allowed_area_ids, true ) ) {
                wp_send_json_error( [ 'message' => __( 'No tienes autorización para eliminar cursos de esta área.', 'aura-suite' ) ], 403 );
            }
        }

        // Bloquear si hay inscripciones activas o pendientes
        $active_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$enroll_table} WHERE course_id = %d AND status IN ('pending','active')",
            $id
        ) );

        if ( $active_count > 0 ) {
            wp_send_json_error( [
                'message' => sprintf(
                    _n(
                        'No se puede archivar: hay %d inscripción activa.',
                        'No se puede archivar: hay %d inscripciones activas.',
                        $active_count,
                        'aura-suite'
                    ),
                    $active_count
                ),
            ] );
        }

        $result = $wpdb->update( $courses_table, [ 'status' => 'archived' ], [ 'id' => $id ] );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al archivar el curso.', 'aura-suite' ) ] );
        }

        self::sync_to_calendar_program( $id );
        wp_send_json_success( [ 'message' => __( 'Curso archivado y sincronizado con Calendario.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER CURSO POR ID
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_course(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_courses_manage' ) &&
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'aura_areas_view_all' ) &&
            ! current_user_can( 'aura_areas_view_own' ) &&
            ! current_user_can( 'aura_areas_enrollment_manage' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ], 403 );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.*,
                    a.name AS area_name,
                    fc.name AS finance_cat_name,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_enrollments e
                     WHERE e.course_id = c.id AND e.status IN ('pending','active')) AS enrolled_count
             FROM {$wpdb->prefix}aura_student_courses c
             LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = c.area_id
             LEFT JOIN {$wpdb->prefix}aura_finance_categories fc ON fc.id = c.finance_cat_id
             WHERE c.id = %d",
            $id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Curso no encontrado.', 'aura-suite' ) ] );
        }

        $is_restricted = (
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'aura_areas_view_all' )
        );

        if ( $is_restricted && ! empty( $row->area_id ) && class_exists( 'Aura_Areas_Setup' ) ) {
            $allowed_area_ids = Aura_Areas_Setup::get_user_area_ids( get_current_user_id() );
            if ( ! in_array( (int) $row->area_id, $allowed_area_ids, true ) ) {
                wp_send_json_error( [ 'message' => __( 'Acceso no autorizado al curso de esta área.', 'aura-suite' ) ], 403 );
            }
        }

        $row->instructor_name = self::get_instructor_name( (int) $row->instructor_id );

        wp_send_json_success( [ 'course' => self::format_course( $row ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTADO PAGINADO DE CURSOS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_courses(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_courses_manage' ) &&
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'aura_areas_view_all' ) &&
            ! current_user_can( 'aura_areas_view_own' ) &&
            ! current_user_can( 'aura_areas_enrollment_manage' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;

        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;
        $status   = isset( $_POST['status'] )  ? sanitize_text_field( $_POST['status'] )  : '';
        $area_id  = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] )               : 0;
        $search   = isset( $_POST['search'] )  ? sanitize_text_field( $_POST['search'] )  : '';

        $where  = [ '1=1' ];
        $params = [];

        // Scoping multi-área CBAC
        $is_restricted = (
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'aura_areas_view_all' ) &&
            ( current_user_can( 'aura_areas_view_own' ) || current_user_can( 'aura_areas_enrollment_manage' ) )
        );

        if ( $is_restricted && class_exists( 'Aura_Areas_Setup' ) ) {
            $allowed_area_ids = Aura_Areas_Setup::get_user_area_ids( get_current_user_id() );
            if ( empty( $allowed_area_ids ) ) {
                wp_send_json_success( [
                    'courses' => [],
                    'total'   => 0,
                    'pages'   => 0,
                    'page'    => $page,
                ] );
                return;
            }
            if ( $area_id && ! in_array( $area_id, $allowed_area_ids, true ) ) {
                $area_id = 0;
            }
            $placeholders = implode( ',', array_fill( 0, count( $allowed_area_ids ), '%d' ) );
            $where[]  = "c.area_id IN ({$placeholders})";
            $params   = array_merge( $params, $allowed_area_ids );
        }

        if ( $status && in_array( $status, [ 'active', 'inactive', 'archived' ], true ) ) {
            $where[]  = 'c.status = %s';
            $params[] = $status;
        }

        if ( $area_id ) {
            $where[]  = 'c.area_id = %d';
            $params[] = $area_id;
        }

        if ( $search ) {
            $where[]  = '(c.name LIKE %s OR c.description LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        // Total rows
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_courses c WHERE {$where_sql}";
        $total     = (int) ( $params
            ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
            : $wpdb->get_var( $count_sql )
        );

        // Rows for current page
        $data_sql = "SELECT c.*,
                            a.name AS area_name,
                            fc.name AS finance_cat_name,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_enrollments e
                             WHERE e.course_id = c.id AND e.status IN ('pending','active')) AS enrolled_count,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}aura_cal_subjects s
                             WHERE (s.program_id = c.cal_program_id OR (c.cal_program_id IS NULL AND s.program_id = c.id)) AND s.deleted_at IS NULL) AS subjects_count
                     FROM {$wpdb->prefix}aura_student_courses c
                     LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = c.area_id
                     LEFT JOIN {$wpdb->prefix}aura_finance_categories fc ON fc.id = c.finance_cat_id
                     WHERE {$where_sql}
                     ORDER BY c.created_at DESC
                     LIMIT %d OFFSET %d";

        $all_params = array_merge( $params, [ $per_page, $offset ] );
        $rows       = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$all_params ) ) ?: [];

        foreach ( $rows as &$r ) {
            $r->instructor_name = self::get_instructor_name( (int) $r->instructor_id );
        }
        unset( $r );

        wp_send_json_success( [
            'courses'      => array_map( [ __CLASS__, 'format_course' ], $rows ),
            'total'        => $total,
            'page'         => $page,
            'total_pages'  => (int) ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ÁREAS (para selects del formulario)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_areas(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_areas';

        // Tabla puede no existir si el módulo de áreas no está activo
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            wp_send_json_success( [ 'areas' => [] ] );
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT id, name FROM {$table} WHERE status = 'active' ORDER BY name ASC"
        ) ?: [];

        wp_send_json_success( [ 'areas' => $rows ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: INSTRUCTORES (usuarios WP con permisos relevantes)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_instructors(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $admins   = get_users( [ 'role' => 'administrator', 'fields' => [ 'ID', 'display_name' ] ] );
        $editors  = get_users( [ 'role' => 'editor',        'fields' => [ 'ID', 'display_name' ] ] );
        $cap_users = get_users( [ 'capability' => 'aura_students_courses_manage', 'fields' => [ 'ID', 'display_name' ] ] );

        $seen        = [];
        $instructors = [];

        foreach ( array_merge( $admins, $editors, $cap_users ) as $u ) {
            if ( ! isset( $seen[ $u->ID ] ) ) {
                $seen[ $u->ID ] = true;
                $instructors[]  = [ 'id' => (int) $u->ID, 'name' => $u->display_name ];
            }
        }

        usort( $instructors, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

        wp_send_json_success( [ 'instructors' => $instructors ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: CATEGORÍAS FINANCIERAS TIPO INGRESO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_finance_cats(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            wp_send_json_success( [ 'categories' => [] ] );
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT id, name FROM {$table} WHERE type IN ('income','both') AND is_active = 1 ORDER BY name ASC"
        ) ?: [];

        wp_send_json_success( [ 'categories' => $rows ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera slug único para el curso.
     */
    private static function generate_slug( string $name, int $exclude_id = 0 ): string {
        global $wpdb;
        $table  = $wpdb->prefix . 'aura_student_courses';
        $base   = sanitize_title( $name );
        $slug   = $base;
        $suffix = 2;

        while ( true ) {
            if ( $exclude_id > 0 ) {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1",
                    $slug,
                    $exclude_id
                ) );
            } else {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                    $slug
                ) );
            }

            if ( ! $exists ) break;
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Devuelve el nombre visible del instructor.
     */
    private static function get_instructor_name( int $user_id ): string {
        if ( ! $user_id ) return '—';

        $first = get_user_meta( $user_id, 'first_name', true );
        $last  = get_user_meta( $user_id, 'last_name',  true );
        $name  = trim( "$first $last" );

        if ( $name ) return $name;

        $ud = get_userdata( $user_id );
        return $ud ? $ud->display_name : '—';
    }

    /**
     * Normaliza una fila BD para respuesta JSON.
     */
    private static function format_course( object $row ): array {
        return [
            'id'               => (int)   $row->id,
            'name'             =>          $row->name,
            'slug'             =>          $row->slug,
            'description'      =>          $row->description      ?? '',
            'area_id'          => $row->area_id        ? (int) $row->area_id        : null,
            'area_name'        =>          $row->area_name        ?? '—',
            'instructor_id'    => $row->instructor_id  ? (int) $row->instructor_id  : null,
            'instructor_name'  =>          $row->instructor_name  ?? '—',
            'duration_weeks'   => $row->duration_weeks ? (int) $row->duration_weeks : null,
            'max_students'     => (int)   $row->max_students,
            'base_cost'        => (float) $row->base_cost,
            'currency'         =>          $row->currency,
            'status'           =>          $row->status,
            'start_date'       =>          $row->start_date       ?? '',
            'end_date'         =>          $row->end_date         ?? '',
            'finance_cat_id'   => $row->finance_cat_id ? (int) $row->finance_cat_id : null,
            'finance_cat_name' =>          $row->finance_cat_name ?? '—',
            'enrolled_count'   => (int)   ( $row->enrolled_count  ?? 0 ),
            'cal_program_id'   => $row->cal_program_id ? (int) $row->cal_program_id : null,
            'subjects_count'   => (int)   ( $row->subjects_count  ?? 0 ),
            'created_at'       =>          $row->created_at       ?? '',
        ];
    }
}
