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
        add_action( 'wp_ajax_aura_cal_get_programs',          [ __CLASS__, 'ajax_get_programs' ] );
        add_action( 'wp_ajax_aura_cal_get_program',           [ __CLASS__, 'ajax_get_program' ] );
        add_action( 'wp_ajax_aura_cal_save_program',          [ __CLASS__, 'ajax_save_program' ] );
        add_action( 'wp_ajax_aura_cal_delete_program',        [ __CLASS__, 'ajax_delete_program' ] );
        add_action( 'wp_ajax_aura_cal_restore_program',       [ __CLASS__, 'ajax_restore_program' ] );
        add_action( 'wp_ajax_aura_cal_sync_student_courses',  [ __CLASS__, 'ajax_sync_student_courses' ] );
    }

    /**
     * Obtener lista de programas
     *
     * @param array $args Filtros opcionales (status, search, limit, offset)
     * @return array
     */
    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $table_prog  = $wpdb->prefix . 'aura_cal_programs';
        $table_subj  = $wpdb->prefix . 'aura_cal_subjects';
        $table_evts  = $wpdb->prefix . 'aura_cal_events';
        $table_areas = $wpdb->prefix . 'aura_areas';

        $defaults = [
            'status'           => '', // active, archived, draft o vacío para no eliminados
            'search'           => '',
            'area_id'          => null,
            'orderby'          => 'p.created_at',
            'order'            => 'DESC',
            'limit'            => 100,
            'offset'           => 0,
            'include_archived' => false, // true: incluye soft-deleted; 'only': solo archivados
        ];
        $r = wp_parse_args( $args, $defaults );

        // Filtrado de borrado lógico según parámetro include_archived
        if ( $r['include_archived'] === 'only' ) {
            $where = [ 'p.deleted_at IS NOT NULL' ];
        } elseif ( $r['include_archived'] ) {
            $where = [ '1=1' ]; // Sin filtro de deleted_at
        } else {
            $where = [ 'p.deleted_at IS NULL' ];
        }
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

        if ( $r['area_id'] !== null && $r['area_id'] !== '' ) {
            $where[]  = 'p.area_id = %d';
            $params[] = intval( $r['area_id'] );
        }

        $where_sql = implode( ' AND ', $where );
        $orderby   = in_array( strtoupper( $r['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $r['order'] ) : 'DESC';

        $sql = "SELECT p.*, u.display_name AS coordinator_name, u.user_email AS coordinator_email,
                       a.name AS area_name, a.slug AS area_code, a.color AS area_color,
                       a.logo_id AS area_logo_id, a.icon AS area_icon,
                       (SELECT COUNT(*) FROM {$table_subj} s WHERE s.program_id = p.id AND s.deleted_at IS NULL) AS subjects_count,
                       (SELECT COUNT(*) FROM {$table_evts} e WHERE e.program_id = p.id AND e.deleted_at IS NULL) AS events_count
                FROM {$table_prog} p
                LEFT JOIN {$wpdb->users} u ON u.ID = p.coordinator_id
                LEFT JOIN {$table_areas} a ON a.id = p.area_id
                WHERE {$where_sql}
                ORDER BY {$r['orderby']} {$orderby}
                LIMIT %d OFFSET %d";

        $params[] = intval( $r['limit'] );
        $params[] = intval( $r['offset'] );

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        $programs = is_array( $results ) ? $results : [];
        return self::populate_coordinators( $programs );
    }

    /**
     * Enriquecer lista de programas con la resolución de múltiples coordinadores
     *
     * @param array $programs
     * @return array
     */
    private static function populate_coordinators( array $programs ): array {
        if ( empty( $programs ) ) {
            return [];
        }

        global $wpdb;
        $t_tp = $wpdb->prefix . 'aura_finance_third_parties';

        foreach ( $programs as &$p ) {
            $area_logo_url = '';
            if ( ! empty( $p->area_logo_id ) ) {
                $area_logo_url = wp_get_attachment_image_url( (int) $p->area_logo_id, 'medium' ) ?: wp_get_attachment_url( (int) $p->area_logo_id ) ?: '';
            }
            $p->area_logo_url = $area_logo_url;

            $ids = [];
            if ( ! empty( $p->coordinators ) ) {
                $decoded = json_decode( $p->coordinators, true );
                if ( is_array( $decoded ) ) {
                    $ids = array_values( array_unique( array_filter( array_map( 'intval', $decoded ) ) ) );
                }
            }

            if ( empty( $ids ) && ! empty( $p->coordinator_id ) ) {
                $ids = [ intval( $p->coordinator_id ) ];
            }

            $p->coordinator_ids   = $ids;
            $p->coordinators_data = [];
            $names                = [];

            foreach ( $ids as $uid ) {
                $user = get_userdata( $uid );
                if ( $user ) {
                    $p->coordinators_data[] = [
                        'id'    => $user->ID,
                        'name'  => $user->display_name,
                        'email' => $user->user_email,
                    ];
                    $names[] = $user->display_name;
                }
            }

            // Procesar coordinadores externos / catálogo de terceros
            $p->external_coordinators_list = [];
            if ( ! empty( $p->external_coordinators ) ) {
                $dec_ext = json_decode( $p->external_coordinators, true );
                if ( is_array( $dec_ext ) ) {
                    foreach ( $dec_ext as $ext_item ) {
                        $tp_id  = ! empty( $ext_item['third_party_id'] ) ? intval( $ext_item['third_party_id'] ) : null;
                        $wp_uid = ! empty( $ext_item['wp_user_id'] ) ? intval( $ext_item['wp_user_id'] ) : ( ! empty( $ext_item['user_id'] ) ? intval( $ext_item['user_id'] ) : null );

                        // Si tiene third_party_id, verificar si en el futuro se convirtió en usuario WP
                        if ( $tp_id ) {
                            $tp_row = $wpdb->get_row( $wpdb->prepare(
                                "SELECT wp_user_id, full_name, commercial_name, email, phone, logo_id, party_type FROM {$t_tp} WHERE id = %d",
                                $tp_id
                            ), ARRAY_A );

                            if ( $tp_row ) {
                                if ( ! empty( $tp_row['wp_user_id'] ) ) {
                                    $wp_uid = intval( $tp_row['wp_user_id'] );
                                }
                                if ( empty( $ext_item['name'] ) ) {
                                    $ext_item['name'] = $tp_row['commercial_name'] ?: $tp_row['full_name'];
                                }
                                if ( empty( $ext_item['email'] ) && ! empty( $tp_row['email'] ) ) {
                                    $ext_item['email'] = $tp_row['email'];
                                }
                                if ( empty( $ext_item['phone'] ) && ! empty( $tp_row['phone'] ) ) {
                                    $ext_item['phone'] = $tp_row['phone'];
                                }
                                if ( ! empty( $tp_row['logo_id'] ) ) {
                                    $ext_item['logo_url'] = wp_get_attachment_image_url( (int) $tp_row['logo_id'], 'thumbnail' ) ?: '';
                                }
                            }
                        }

                        $ext_item['wp_user_id'] = $wp_uid;
                        if ( $wp_uid ) {
                            $wp_u = get_userdata( $wp_uid );
                            if ( $wp_u ) {
                                $ext_item['is_wp_user'] = true;
                                $ext_item['user_display_name'] = $wp_u->display_name;
                                $ext_item['avatar_url'] = get_avatar_url( $wp_uid, [ 'size' => 72 ] );
                            }
                        }

                        $p->external_coordinators_list[] = $ext_item;
                        $ext_name = $ext_item['commercial_name'] ?? $ext_item['name'] ?? '';
                        if ( $ext_name ) {
                            $names[] = $ext_name . ' (🏛️)';
                        }
                    }
                }
            }

            $p->coordinators_names = ! empty( $names ) ? implode( ', ', $names ) : ( $p->coordinator_name ?: '' );
        }
        unset( $p );

        return $programs;
    }

    /**
     * Obtener un programa por ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        $table_prog  = $wpdb->prefix . 'aura_cal_programs';
        $table_areas = $wpdb->prefix . 'aura_areas';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT p.*, u.display_name AS coordinator_name, u.user_email AS coordinator_email,
                    a.name AS area_name, a.slug AS area_code, a.color AS area_color,
                    a.logo_id AS area_logo_id, a.icon AS area_icon
             FROM {$table_prog} p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.coordinator_id
             LEFT JOIN {$table_areas} a ON a.id = p.area_id
             WHERE p.id = %d AND p.deleted_at IS NULL",
            $id
        ) );

        if ( ! $row ) {
            return null;
        }

        $populated = self::populate_coordinators( [ $row ] );
        return ! empty( $populated ) ? $populated[0] : $row;
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

        // Procesar área institucional (opcional)
        $area_id = ! empty( $data['area_id'] ) ? intval( $data['area_id'] ) : null;

        // Procesar coordinadores múltiples
        $coord_ids = [];
        if ( isset( $data['coordinator_ids'] ) ) {
            if ( is_array( $data['coordinator_ids'] ) ) {
                $coord_ids = array_values( array_unique( array_filter( array_map( 'intval', $data['coordinator_ids'] ) ) ) );
            } elseif ( is_string( $data['coordinator_ids'] ) && ! empty( $data['coordinator_ids'] ) ) {
                $decoded = json_decode( stripslashes( $data['coordinator_ids'] ), true );
                if ( is_array( $decoded ) ) {
                    $coord_ids = array_values( array_unique( array_filter( array_map( 'intval', $decoded ) ) ) );
                } else {
                    $coord_ids = array_values( array_unique( array_filter( array_map( 'intval', explode( ',', $data['coordinator_ids'] ) ) ) ) );
                }
            }
        } elseif ( ! empty( $data['coordinator_id'] ) ) {
            $coord_ids = [ intval( $data['coordinator_id'] ) ];
        }

        $primary_coordinator = ! empty( $coord_ids ) ? $coord_ids[0] : null;
        $coordinators_json   = ! empty( $coord_ids ) ? wp_json_encode( $coord_ids ) : null;

        // Procesar coordinadores externos / catálogo de terceros
        $clean_ext_coords = [];
        if ( isset( $data['external_coordinators'] ) ) {
            $raw_ext = $data['external_coordinators'];
            if ( is_string( $raw_ext ) ) {
                $raw_ext = json_decode( stripslashes( $raw_ext ), true );
            }
            if ( is_array( $raw_ext ) ) {
                $t_tp = $wpdb->prefix . 'aura_finance_third_parties';
                foreach ( $raw_ext as $ext ) {
                    if ( empty( $ext['name'] ) && empty( $ext['commercial_name'] ) ) continue;
                    $tp_id  = ! empty( $ext['third_party_id'] ) ? intval( $ext['third_party_id'] ) : null;
                    $wp_uid = ! empty( $ext['wp_user_id'] ) ? intval( $ext['wp_user_id'] ) : ( ! empty( $ext['user_id'] ) ? intval( $ext['user_id'] ) : null );

                    // Si tiene third_party_id pero no wp_user_id, verificar si en wp_aura_finance_third_parties ya tiene wp_user_id
                    if ( $tp_id && ! $wp_uid ) {
                        $found_uid = $wpdb->get_var( $wpdb->prepare( "SELECT wp_user_id FROM {$t_tp} WHERE id = %d", $tp_id ) );
                        if ( ! empty( $found_uid ) ) {
                            $wp_uid = intval( $found_uid );
                        }
                    }

                    $clean_ext_coords[] = [
                        'name'           => sanitize_text_field( $ext['name'] ?? '' ),
                        'commercial_name'=> sanitize_text_field( $ext['commercial_name'] ?? '' ),
                        'email'          => sanitize_email( $ext['email'] ?? '' ),
                        'phone'          => sanitize_text_field( $ext['phone'] ?? '' ),
                        'organization'   => sanitize_text_field( $ext['organization'] ?? '' ),
                        'role'           => sanitize_key( $ext['role'] ?? 'coord_lead' ),
                        'third_party_id' => $tp_id,
                        'wp_user_id'     => $wp_uid,
                    ];
                }
            }
        }
        $ext_coords_json = ! empty( $clean_ext_coords ) ? wp_json_encode( $clean_ext_coords ) : null;

        $fields = [
            'name'                 => $name,
            'code'                 => $code,
            'description'          => sanitize_textarea_field( $data['description'] ?? '' ),
            'academic_period'      => sanitize_text_field( $data['academic_period'] ?? '' ),
            'start_date'           => ! empty( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null,
            'end_date'             => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
            'color'                => $color,
            'status'               => in_array( $data['status'] ?? '', [ 'active', 'archived', 'draft' ], true ) ? $data['status'] : 'active',
            'coordinator_id'       => $primary_coordinator,
            'coordinators'         => $coordinators_json,
            'external_coordinators'=> $ext_coords_json,
            'area_id'              => $area_id,
            'updated_at'           => current_time( 'mysql' ),
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
            $fields['coordinators'] !== null ? '%s' : null,
            $fields['external_coordinators'] !== null ? '%s' : null,
            $fields['area_id'] !== null ? '%d' : null,
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
    public static function delete( int $id, bool $force = false ): bool {
        global $wpdb;
        $table      = $wpdb->prefix . 'aura_cal_programs';
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';

        if ( $force ) {
            // Eliminación permanente en cascada
            $wpdb->delete( $table_subj, [ 'program_id' => $id ], [ '%d' ] );
            $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
            return $deleted !== false;
        }

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

        if ( $updated !== false ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table_subj} SET deleted_at = %s, status = 'archived' WHERE program_id = %d AND deleted_at IS NULL",
                current_time( 'mysql' ),
                $id
            ) );
        }

        return $updated !== false;
    }

    /**
     * Restaurar programa archivado (revertir soft delete)
     *
     * @param int $id
     * @return bool
     */
    public static function restore( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_cal_programs';

        $updated = $wpdb->update(
            $table,
            [
                'deleted_at' => null,
                'status'     => 'active',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ null, '%s', '%s' ],
            [ '%d' ]
        );

        // Restaurar también las materias del programa
        if ( $updated !== false ) {
            $table_subj = $wpdb->prefix . 'aura_cal_subjects';
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table_subj} SET deleted_at = NULL, status = 'active' WHERE program_id = %d AND deleted_at IS NOT NULL",
                $id
            ) );
        }

        return $updated !== false;
    }

    /**
     * Sincronizar cursos de Estudiantes (wp_aura_student_courses) como Programas de Calendario
     * Crea en wp_aura_cal_programs los cursos de estudiantes que no tengan ya un programa equivalente.
     *
     * @return array { created: int[], skipped: int[] }
     */
    public static function sync_from_student_courses(): array {
        global $wpdb;
        $t_courses  = $wpdb->prefix . 'aura_student_courses';
        $t_programs = $wpdb->prefix . 'aura_cal_programs';

        // Verificar que la tabla de cursos exista
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_courses}'" ) !== $t_courses ) {
            return [ 'created' => [], 'skipped' => [], 'error' => 'Tabla de cursos de estudiantes no encontrada.' ];
        }

        $courses = $wpdb->get_results(
            "SELECT id, name, slug, description, area_id, base_cost, status, created_by, created_at
             FROM {$t_courses}
             WHERE status = 'active'",
            ARRAY_A
        );

        if ( empty( $courses ) ) {
            return [ 'created' => [], 'skipped' => [] ];
        }

        $created = [];
        $skipped = [];

        foreach ( $courses as $course ) {
            // Verificar si ya existe un programa con este nombre o slug-código
            $slug_code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $course['slug'] ?? '' ) );
            if ( empty( $slug_code ) ) {
                $slug_code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $course['name'] ) );
            }
            $slug_code = substr( $slug_code, 0, 20 );

            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$t_programs} WHERE (name = %s OR code = %s) AND deleted_at IS NULL LIMIT 1",
                $course['name'],
                $slug_code
            ) );

            if ( $exists ) {
                $skipped[] = (int) $course['id'];
                continue;
            }

            // Verificar unicidad del código
            $code_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$t_programs} WHERE code = %s AND deleted_at IS NULL LIMIT 1",
                $slug_code
            ) );
            if ( $code_exists ) {
                $slug_code .= '-SC';
            }

            $area_id = ! empty( $course['area_id'] ) ? intval( $course['area_id'] ) : null;

            $inserted = $wpdb->insert(
                $t_programs,
                [
                    'code'        => $slug_code,
                    'name'        => $course['name'],
                    'description' => $course['description'] ?? '',
                    'color'       => '#6366f1',
                    'status'      => 'active',
                    'area_id'     => $area_id,
                    'created_by'  => $course['created_by'] ?? get_current_user_id(),
                    'created_at'  => $course['created_at'] ?? current_time( 'mysql' ),
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [
                    '%s', '%s', '%s', '%s', '%s',
                    $area_id !== null ? '%d' : null,
                    '%d', '%s', '%s',
                ]
            );

            if ( $inserted ) {
                $created[] = (int) $wpdb->insert_id;
            }
        }

        return [ 'created' => $created, 'skipped' => $skipped ];
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_programs(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_view_calendar' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para ver programas.', 'aura' ) ] );
        }

        $area_id          = isset( $_POST['area_id'] ) && $_POST['area_id'] !== '' ? intval( $_POST['area_id'] ) : null;
        $include_archived = sanitize_text_field( $_POST['include_archived'] ?? '' );

        // Mapear valor del filtro del frontend
        $archived_filter = false;
        if ( $include_archived === 'only' ) {
            $archived_filter = 'only';
        } elseif ( $include_archived === 'all' ) {
            $archived_filter = true;
        }

        $programs = self::get_all( [
            'status'           => sanitize_text_field( $_POST['status'] ?? '' ),
            'search'           => sanitize_text_field( $_POST['search'] ?? '' ),
            'area_id'          => $area_id,
            'include_archived' => $archived_filter,
        ] );

        wp_send_json_success( [ 'programs' => $programs ] );
    }

    public static function ajax_get_program(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_view_calendar' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

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

        if ( ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
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

        if ( ! current_user_can( 'aura_cal_delete_programs' ) && ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para eliminar programas.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $force = ! empty( $_POST['force'] );
        $ok    = self::delete( $id, $force );
        if ( $ok ) {
            $msg = $force
                ? __( 'Programa y sus materias eliminados permanentemente.', 'aura' )
                : __( 'Programa archivado correctamente.', 'aura' );
            wp_send_json_success( [ 'message' => $msg ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo eliminar el programa.', 'aura' ) ] );
        }
    }

    public static function ajax_restore_program(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para restaurar programas.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $ok = self::restore( $id );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Programa y sus materias han sido restaurados exitosamente.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo restaurar el programa.', 'aura' ) ] );
        }
    }

    public static function ajax_sync_student_courses(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para sincronizar cursos.', 'aura' ) ] );
        }

        $result = self::sync_from_student_courses();

        $created_count = count( $result['created'] ?? [] );
        $skipped_count = count( $result['skipped'] ?? [] );

        if ( ! empty( $result['error'] ) ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        /* translators: %1$d: programas creados, %2$d: cursos ya existentes */
        $message = sprintf(
            __( 'Sincronización completada: %1$d programa(s) nuevo(s) importado(s) desde Cursos de Estudiantes. %2$d ya existía(n) en el Calendario.', 'aura' ),
            $created_count,
            $skipped_count
        );

        wp_send_json_success( [
            'message'  => $message,
            'created'  => $result['created'],
            'skipped'  => $result['skipped'],
            'reload'   => true,
        ] );
    }
}
