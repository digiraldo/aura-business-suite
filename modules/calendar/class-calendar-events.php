<?php
/**
 * Gestión de Eventos y Horarios Académicos — Módulo de Calendario
 *
 * CRUD de clases, talleres, evaluaciones y actividades con soporte para
 * recurrencia simplificada por días de la semana, sincronización con Google Calendar,
 * asignación de múltiples instructores y filtrado según permisos de rol (CBAC).
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Events {

    const OPTION_SYNC_VERSION = 'aura_cal_sync_version';

    /**
     * Catálogo de responsabilidades / roles de liderazgo estudiantil
     *
     * @return array
     */
    public static function get_student_roles(): array {
        return [
            'activity_leader' => [
                'label'       => __( 'Líder de Actividad', 'aura' ),
                'badge_color' => '#f59e0b',
                'icon'        => '🎯',
                'description' => __( 'Coordina la dinámica de grupo y la participación activa.', 'aura' ),
            ],
            'program_leader'  => [
                'label'       => __( 'Líder de Programa', 'aura' ),
                'badge_color' => '#6366f1',
                'icon'        => '👑',
                'description' => __( 'Representante estudiantil del cohorte o programa académico.', 'aura' ),
            ],
            'presenter'       => [
                'label'       => __( 'Expositor / Dar Clase', 'aura' ),
                'badge_color' => '#10b981',
                'icon'        => '🎙️',
                'description' => __( 'A cargo de la exposición o ponencia principal de la sesión.', 'aura' ),
            ],
            'monitor'         => [
                'label'       => __( 'Monitor / Moderador', 'aura' ),
                'badge_color' => '#06b6d4',
                'icon'        => '🛡️',
                'description' => __( 'Asiste en control de tiempo, preguntas y apoyo al docente.', 'aura' ),
            ],
        ];
    }

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_events',          [ __CLASS__, 'ajax_get_events' ] );
        add_action( 'wp_ajax_aura_cal_get_event',           [ __CLASS__, 'ajax_get_event' ] );
        add_action( 'wp_ajax_aura_cal_save_event',          [ __CLASS__, 'ajax_save_event' ] );
        add_action( 'wp_ajax_aura_cal_delete_event',        [ __CLASS__, 'ajax_delete_event' ] );
        add_action( 'wp_ajax_aura_cal_update_event_dates',  [ __CLASS__, 'ajax_update_event_dates' ] );
        add_action( 'wp_ajax_aura_cal_heartbeat_sync',       [ __CLASS__, 'ajax_heartbeat_sync' ] );
        add_action( 'wp_ajax_aura_cal_duplicate_event',     [ __CLASS__, 'ajax_duplicate_event' ] );
        add_action( 'wp_ajax_aura_cal_replicate_series',    [ __CLASS__, 'ajax_replicate_series' ] );
    }

    /**
     * Formatear una fecha/hora local de la BD respetando la zona horaria del sitio sin desfasar horas
     *
     * @param string $datetime_str Fecha y hora en formato YYYY-MM-DD HH:MM:SS
     * @param string $format Formato de salida de PHP/WordPress
     * @return string
     */
    public static function format_local_datetime( string $datetime_str, string $format ): string {
        if ( empty( $datetime_str ) ) {
            return '';
        }
        try {
            $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
            $dt = date_create( $datetime_str, $tz );
            if ( ! $dt ) {
                return '';
            }
            return wp_date( $format, $dt->getTimestamp() );
        } catch ( Exception $e ) {
            return '';
        }
    }

    /**
     * Calcular color de contraste óptimo (blanco o negro/oscuro) según la luminancia del fondo
     *
     * @param string $hex_color
     * @return string '#ffffff' o '#0f172a'
     */
    public static function get_contrast_color( string $hex_color ): string {
        $hex = ltrim( trim( $hex_color ), '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) !== 6 ) {
            return '#ffffff';
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        $yiq = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
        return ( $yiq >= 145 ) ? '#0f172a' : '#ffffff';
    }

    /**
     * Obtener eventos formateados para FullCalendar v6
     *
     * @param array $filters (start, end, program_id, subject_id, teacher_id, event_type, status)
     * @param int|null $user_id Usuario actual para filtrado CBAC (default: current_user)
     * @return array
     */
    public static function get_events( array $filters = [], ?int $user_id = null ): array {
        global $wpdb;
        $table_evts        = $wpdb->prefix . 'aura_cal_events';
        $table_prog        = $wpdb->prefix . 'aura_cal_programs';
        $table_subj        = $wpdb->prefix . 'aura_cal_subjects';
        $table_inst        = $wpdb->prefix . 'aura_cal_event_instructors';
        $table_att         = $wpdb->prefix . 'aura_cal_attendance';

        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        $where  = [ 'e.deleted_at IS NULL' ];
        $params = [];

        // Rango de fechas (FullCalendar envía start y end, comparamos con hora local de la BD sin desfasar)
        if ( ! empty( $filters['start'] ) ) {
            $start_dt = str_replace( 'T', ' ', substr( $filters['start'], 0, 19 ) );
            if ( strlen( $start_dt ) === 10 ) {
                $start_dt .= ' 00:00:00';
            }
            $where[]  = 'e.end_datetime >= %s';
            $params[] = $start_dt;
        }

        if ( ! empty( $filters['end'] ) ) {
            $end_dt   = str_replace( 'T', ' ', substr( $filters['end'], 0, 19 ) );
            if ( strlen( $end_dt ) === 10 ) {
                $end_dt .= ' 23:59:59';
            }
            $where[]  = 'e.start_datetime <= %s';
            $params[] = $end_dt;
        }

        if ( ! empty( $filters['program_id'] ) ) {
            $where[]  = 'e.program_id = %d';
            $params[] = intval( $filters['program_id'] );
        }

        if ( ! empty( $filters['subject_id'] ) ) {
            $where[]  = 'e.subject_id = %d';
            $params[] = intval( $filters['subject_id'] );
        }

        if ( ! empty( $filters['event_type'] ) ) {
            $where[]  = 'e.event_type = %s';
            $params[] = sanitize_text_field( $filters['event_type'] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'e.status = %s';
            $params[] = sanitize_text_field( $filters['status'] );
        }

        // Filtro específico por profesor
        if ( ! empty( $filters['teacher_id'] ) ) {
            $where[]  = "EXISTS (SELECT 1 FROM {$table_inst} ei_filter WHERE ei_filter.event_id = e.id AND ei_filter.teacher_id = %d)";
            $params[] = intval( $filters['teacher_id'] );
        }

        // ── FILTRADO CBAC Y POR ÁREAS SEGÚN ROL/PERMISOS DEL USUARIO ──
        $can_manage_all = current_user_can( 'manage_options' ) ||
                          current_user_can( 'aura_cal_manage_calendar' ) ||
                          current_user_can( 'aura_manage_calendar' );

        $portal_scope = ! empty( $filters['portal_scope'] ) ? sanitize_key( $filters['portal_scope'] ) : '';
        $can_portal_view_all = current_user_can( 'aura_cal_portal_view_all' ) || $can_manage_all;

        if ( ! $can_manage_all && $user_id > 0 ) {
            $is_restricted_view = current_user_can( 'aura_cal_view_own' ) || current_user_can( 'aura_teach_calendar' );
            $is_student         = current_user_can( 'aura_student_portal_access' );

            // Si el docente solicita expresamente alternar entre 'all' y 'own' desde el portal
            if ( $portal_scope === 'own' ) {
                $is_restricted_view = true;
            } elseif ( $portal_scope === 'all' && $can_portal_view_all ) {
                $is_restricted_view = false;
            }

            // Si es un líder de área o tiene asignadas áreas específicas en wp_aura_area_users
            $user_areas = [];
            $table_area_users = $wpdb->prefix . 'aura_area_users';
            $has_area_users_tbl = $wpdb->get_var( "SHOW TABLES LIKE '{$table_area_users}'" ) === $table_area_users;
            if ( $has_area_users_tbl ) {
                $user_areas = $wpdb->get_col( $wpdb->prepare(
                    "SELECT area_id FROM {$table_area_users} WHERE user_id = %d",
                    $user_id
                ) );
            }

            if ( ! empty( $user_areas ) && ! $is_restricted_view && ! $is_student ) {
                // Líder o coordinador de área: ve eventos de programas vinculados a sus áreas asignadas o sin área
                $area_ids_sql = implode( ',', array_map( 'intval', $user_areas ) );
                $where[] = "(p.area_id IN ({$area_ids_sql}) OR p.area_id IS NULL)";
            } elseif ( $is_restricted_view && ! $is_student ) {
                // Profesor o usuario con vista propia: eventos donde es instructor, coordina el programa o programas de su área
                $area_clause = '';
                if ( ! empty( $user_areas ) && $portal_scope !== 'own' ) {
                    $area_ids_sql = implode( ',', array_map( 'intval', $user_areas ) );
                    $area_clause = " OR p.area_id IN ({$area_ids_sql})";
                }
                $where[]  = "(EXISTS (SELECT 1 FROM {$table_inst} ei_cbac WHERE ei_cbac.event_id = e.id AND ei_cbac.teacher_id = %d) OR p.coordinator_id = %d OR p.coordinators LIKE %s{$area_clause})";
                $params[] = $user_id;
                $params[] = $user_id;
                $params[] = '%"' . $user_id . '"%';
            } elseif ( $is_student ) {
                // Estudiante ve los eventos de los programas donde está inscrito activamente
                $table_enroll = $wpdb->prefix . 'aura_student_enrollments';
                $table_stud   = $wpdb->prefix . 'aura_students';
                $has_students = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stud}'" ) === $table_stud;

                if ( $has_students ) {
                    $where[] = "e.program_id IN (
                        SELECT enr.course_id FROM {$table_enroll} enr
                        JOIN {$table_stud} st ON st.id = enr.student_id
                        WHERE st.wp_user_id = %d AND enr.status = 'active'
                    )";
                    $params[] = $user_id;
                }
            }
        } elseif ( $can_manage_all && $portal_scope === 'own' && $user_id > 0 ) {
            // Incluso un admin puede probar el modo 'Solo mis clases'
            $where[]  = "EXISTS (SELECT 1 FROM {$table_inst} ei_cbac WHERE ei_cbac.event_id = e.id AND ei_cbac.teacher_id = %d)";
            $params[] = $user_id;
        }

        $where_sql = implode( ' AND ', $where );

        $sql = "SELECT e.*,
                       p.name AS program_name, p.code AS program_code, p.color AS program_color, p.description AS program_description,
                       s.name AS subject_name, s.code AS subject_code, s.color AS subject_color, s.description AS subject_description,
                       s.module_name, s.module_order,
                       s.default_teacher_id AS subject_default_teacher_id, s.teachers AS subject_teachers, s.external_teachers AS subject_external_teachers,
                       (SELECT COUNT(*) FROM {$table_att} a WHERE a.event_id = e.id) AS attendance_count
                FROM {$table_evts} e
                LEFT JOIN {$table_prog} p ON p.id = e.program_id
                LEFT JOIN {$table_subj} s ON s.id = e.subject_id
                WHERE {$where_sql}
                ORDER BY e.start_datetime ASC";

        $rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
        if ( empty( $rows ) ) {
            return [];
        }

        // Obtener instructores de todos los eventos encontrados en una sola consulta
        $event_ids = wp_list_pluck( $rows, 'id' );
        $instructors_by_event = [];
        if ( ! empty( $event_ids ) ) {
            $ids_placeholder = implode( ',', array_map( 'intval', $event_ids ) );

            // Asegurar que la tabla event_instructors tenga columnas correctas en runtime
            if ( class_exists( 'Aura_Calendar_Setup' ) ) {
                Aura_Calendar_Setup::maybe_add_event_instructors_columns();
            }

            $inst_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table_inst}`" );
            $order_clause = in_array( 'role', $inst_cols, true )
                ? "ORDER BY CASE WHEN ei.role = 'lead' THEN 0 ELSE 1 END, ei.id ASC"
                : "ORDER BY ei.id ASC";

            $user_id_col = in_array( 'teacher_id', $inst_cols, true )
                ? ( in_array( 'instructor_id', $inst_cols, true )
                    ? "COALESCE(NULLIF(ei.teacher_id, 0), ei.instructor_id, 0)"
                    : "ei.teacher_id" )
                : ( in_array( 'instructor_id', $inst_cols, true ) ? "ei.instructor_id" : "0" );

            $inst_rows = $wpdb->get_results(
                "SELECT ei.*, u.display_name, u.user_email
                 FROM {$table_inst} ei
                 LEFT JOIN {$wpdb->users} u ON u.ID = {$user_id_col}
                 WHERE ei.event_id IN ({$ids_placeholder})
                 {$order_clause}"
            );

            // Cargar fotos de perfil de wp_aura_students si está disponible
            $t_students = $wpdb->prefix . 'aura_students';
            $custom_photos = [];
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_students}'" ) === $t_students && ! empty( $inst_rows ) ) {
                $twp_ids = array_unique( array_filter( array_map( function( $r ) {
                    return (int) ( $r->teacher_id ?: ( $r->instructor_id ?? 0 ) );
                }, $inst_rows ) ) );
                if ( ! empty( $twp_ids ) ) {
                    $twp_in = implode( ',', $twp_ids );
                    $photos = $wpdb->get_results( "SELECT wp_user_id, photo_url FROM {$t_students} WHERE wp_user_id IN ({$twp_in}) AND photo_url IS NOT NULL AND photo_url != ''" );
                    if ( is_array( $photos ) ) {
                        foreach ( $photos as $p ) {
                            $custom_photos[ (int) $p->wp_user_id ] = $p->photo_url;
                        }
                    }
                }
            }

            $tp_ids = array_unique( array_filter( array_map( function( $ir ) {
                return (int) ( $ir->third_party_id ?? 0 );
            }, $inst_rows ) ) );
            $tp_map = [];
            if ( ! empty( $tp_ids ) ) {
                $t_tp = $wpdb->prefix . 'aura_finance_third_parties';
                if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_tp}'" ) === $t_tp ) {
                    $in_tp = implode( ',', array_map( 'intval', $tp_ids ) );
                    $tp_records = $wpdb->get_results( "SELECT id, wp_user_id, logo_id, commercial_name, full_name, email, phone FROM {$t_tp} WHERE id IN ({$in_tp})", OBJECT_K );
                    if ( is_array( $tp_records ) ) {
                        $tp_map = $tp_records;
                    }
                }
            }

            foreach ( $inst_rows as $ir ) {
                $is_ext  = ! empty( $ir->is_external );
                $real_id = (int) ( $ir->teacher_id ?: ( $ir->instructor_id ?? 0 ) );
                $tp_id   = ! empty( $ir->third_party_id ) ? (int) $ir->third_party_id : 0;
                $tp_info = ( $tp_id > 0 && isset( $tp_map[ $tp_id ] ) ) ? $tp_map[ $tp_id ] : null;

                $is_wp_user = false;
                $av_url     = '';

                if ( ! $is_ext ) {
                    $av_url = ! empty( $custom_photos[ $real_id ] )
                        ? $custom_photos[ $real_id ]
                        : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                } else {
                    if ( ! empty( $ir->avatar_url ) ) {
                        $av_url = $ir->avatar_url;
                    }

                    if ( $real_id > 0 ) {
                        $is_wp_user = true;
                        if ( empty( $av_url ) ) {
                            $av_url = ! empty( $custom_photos[ $real_id ] )
                                ? $custom_photos[ $real_id ]
                                : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                        }
                    } elseif ( $tp_info ) {
                        if ( ! empty( $tp_info->wp_user_id ) && (int) $tp_info->wp_user_id > 0 ) {
                            $is_wp_user = true;
                            $real_id    = (int) $tp_info->wp_user_id;
                            if ( empty( $av_url ) ) {
                                $av_url = ! empty( $custom_photos[ $real_id ] )
                                    ? $custom_photos[ $real_id ]
                                    : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                            }
                        } elseif ( ! empty( $tp_info->logo_id ) && empty( $av_url ) ) {
                            $av_url = wp_get_attachment_image_url( (int) $tp_info->logo_id, 'thumbnail' ) ?: ( wp_get_attachment_url( (int) $tp_info->logo_id ) ?: '' );
                        }
                    }

                    if ( empty( $av_url ) && ! empty( $ir->external_email ) ) {
                        $u_by_email = get_user_by( 'email', $ir->external_email );
                        if ( $u_by_email ) {
                            $is_wp_user = true;
                            $real_id    = (int) $u_by_email->ID;
                            $av_url     = ! empty( $custom_photos[ $real_id ] )
                                ? $custom_photos[ $real_id ]
                                : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                        } else {
                            $av_url = get_avatar_url( $ir->external_email, [ 'size' => 64, 'default' => 'identicon' ] );
                        }
                    }
                }

                if ( ! empty( $av_url ) && str_starts_with( $av_url, ':/' ) ) {
                    $av_url = site_url( substr( $av_url, 1 ) );
                }

                $inst_name  = $is_ext ? ( $ir->external_name ?: ( ( $tp_info && ! empty( $tp_info->commercial_name ) ) ? $tp_info->commercial_name : ( ( $tp_info && ! empty( $tp_info->full_name ) ) ? $tp_info->full_name : __( 'Instructor Externo', 'aura' ) ) ) ) : ( $ir->display_name ?: __( 'Profesor', 'aura' ) );
                $inst_email = $is_ext ? ( $ir->external_email ?: ( ( $tp_info && ! empty( $tp_info->email ) ) ? $tp_info->email : '' ) ) : $ir->user_email;

                $instructors_by_event[ $ir->event_id ][] = [
                    'id'             => $real_id,
                    'name'           => $inst_name,
                    'email'          => $inst_email,
                    'role'           => $ir->role,
                    'is_external'    => $is_ext ? 1 : 0,
                    'is_wp_user'     => $is_wp_user,
                    'wp_user_id'     => $is_wp_user ? $real_id : null,
                    'third_party_id' => $tp_id ?: null,
                    'external_name'  => $inst_name,
                    'external_email' => $inst_email,
                    'external_phone' => $ir->external_phone ?? ( ( $tp_info && ! empty( $tp_info->phone ) ) ? $tp_info->phone : '' ),
                    'external_org'   => $ir->external_org ?? ( ( $tp_info && ! empty( $tp_info->commercial_name ) ) ? $tp_info->commercial_name : '' ),
                    'avatar'         => $av_url,
                    'avatar_url'     => $av_url,
                ];
            }
        }

        // Mapear al formato esperado por FullCalendar
        $fc_events = [];
        $roles_map = [
            'program_leader'  => __( 'Líder de Programa', 'aura' ),
            'activity_leader' => __( 'Líder de Actividad', 'aura' ),
            'presenter'       => __( 'Expositor / Dar Clase', 'aura' ),
            'monitor'         => __( 'Monitor / Moderador', 'aura' ),
        ];

        foreach ( $rows as $row ) {
            // Determinar color de fondo
            $bg_color = ! empty( $row->color ) ? $row->color : ( ! empty( $row->subject_color ) ? $row->subject_color : ( ! empty( $row->program_color ) ? $row->program_color : '#6366f1' ) );

            // Si está cancelado, atenuar visualmente
            if ( $row->status === 'cancelled' ) {
                $bg_color = '#94a3b8';
            }

            // Título para FullCalendar
            $title = $row->title;
            if ( ! empty( $row->subject_name ) ) {
                $title = $row->subject_name . ' - ' . $title;
            }

            $inst_list = $instructors_by_event[ $row->id ] ?? [];

            // Fallback a los profesores y docentes externos de la materia si el evento no tiene instructores explícitos
            if ( empty( $inst_list ) && ! empty( $row->subject_id ) ) {
                $subj_teachers = [];
                if ( ! empty( $row->subject_teachers ) ) {
                    $dec = json_decode( $row->subject_teachers, true );
                    if ( is_array( $dec ) ) {
                        $subj_teachers = array_values( array_unique( array_filter( array_map( 'intval', $dec ) ) ) );
                    }
                }
                if ( empty( $subj_teachers ) && ! empty( $row->subject_default_teacher_id ) ) {
                    $subj_teachers = [ intval( $row->subject_default_teacher_id ) ];
                }

                foreach ( $subj_teachers as $s_uid ) {
                    $u = get_userdata( $s_uid );
                    if ( $u ) {
                        $s_av = ! empty( $custom_photos[ $s_uid ] ) ? $custom_photos[ $s_uid ] : get_avatar_url( $s_uid, [ 'size' => 64, 'default' => 'identicon' ] );
                        if ( ! empty( $s_av ) && str_starts_with( $s_av, ':/' ) ) {
                            $s_av = site_url( substr( $s_av, 1 ) );
                        }
                        $inst_list[] = [
                            'id'             => $s_uid,
                            'name'           => $u->display_name,
                            'email'          => $u->user_email,
                            'role'           => ( count( $inst_list ) === 0 ) ? 'lead' : 'secondary',
                            'is_external'    => 0,
                            'is_wp_user'     => true,
                            'wp_user_id'     => $s_uid,
                            'third_party_id' => null,
                            'external_name'  => '',
                            'external_email' => '',
                            'external_phone' => '',
                            'external_org'   => '',
                            'avatar'         => $s_av,
                            'avatar_url'     => $s_av,
                        ];
                    }
                }

                if ( ! empty( $row->subject_external_teachers ) ) {
                    $dec_subj_ext = json_decode( $row->subject_external_teachers, true );
                    if ( is_array( $dec_subj_ext ) ) {
                        foreach ( $dec_subj_ext as $se ) {
                            $se_name = ! empty( $se['commercial_name'] ) ? $se['commercial_name'] : ( ! empty( $se['name'] ) ? $se['name'] : ( $se['external_name'] ?? '' ) );
                            if ( empty( $se_name ) ) {
                                continue;
                            }
                            $se_av = ! empty( $se['avatar_url'] ) ? $se['avatar_url'] : ( $se['avatar'] ?? '' );
                            $se_uid = ! empty( $se['wp_user_id'] ) ? intval( $se['wp_user_id'] ) : 0;
                            if ( empty( $se_av ) && $se_uid > 0 ) {
                                $se_av = ! empty( $custom_photos[ $se_uid ] ) ? $custom_photos[ $se_uid ] : get_avatar_url( $se_uid, [ 'size' => 64, 'default' => 'identicon' ] );
                            }
                            if ( empty( $se_av ) && ! empty( $se['email'] ) ) {
                                $u_em = get_user_by( 'email', $se['email'] );
                                if ( $u_em ) {
                                    $se_uid = (int) $u_em->ID;
                                    $se_av = get_avatar_url( $se_uid, [ 'size' => 64, 'default' => 'identicon' ] );
                                } else {
                                    $se_av = get_avatar_url( $se['email'], [ 'size' => 64, 'default' => 'identicon' ] );
                                }
                            }
                            if ( ! empty( $se_av ) && str_starts_with( $se_av, ':/' ) ) {
                                $se_av = site_url( substr( $se_av, 1 ) );
                            }

                            $inst_list[] = [
                                'id'             => $se_uid,
                                'name'           => $se_name,
                                'email'          => $se['email'] ?? '',
                                'role'           => ( count( $inst_list ) === 0 ) ? 'lead' : 'secondary',
                                'is_external'    => 1,
                                'is_wp_user'     => $se_uid > 0,
                                'wp_user_id'     => $se_uid ?: null,
                                'third_party_id' => ! empty( $se['third_party_id'] ) ? intval( $se['third_party_id'] ) : null,
                                'external_name'  => $se_name,
                                'external_email' => $se['email'] ?? '',
                                'external_phone' => $se['phone'] ?? '',
                                'external_org'   => $se['commercial_name'] ?? ( $se['org'] ?? '' ),
                                'avatar'         => $se_av,
                                'avatar_url'     => $se_av,
                            ];
                        }
                    }
                }
            }

            // Decodificar líderes estudiantiles asignados
            $student_leaders_list = [];
            if ( ! empty( $row->student_leaders ) ) {
                $raw_leaders = json_decode( $row->student_leaders, true );
                if ( is_array( $raw_leaders ) ) {
                    foreach ( $raw_leaders as $sl ) {
                        $sid = intval( $sl['student_id'] ?? 0 );
                        if ( ! $sid ) {
                            continue;
                        }
                        $s_user = get_userdata( $sid );
                        $s_name = $s_user ? $s_user->display_name : ( $sl['student_name'] ?? ( '#' . $sid ) );
                        $role_k = $sl['role'] ?? 'activity_leader';
                        $student_leaders_list[] = [
                            'student_id' => $sid,
                            'name'       => $s_name,
                            'role'       => $role_k,
                            'role_label' => $roles_map[ $role_k ] ?? $role_k,
                            'notes'      => $sl['notes'] ?? '',
                            'avatar'     => get_avatar_url( $sid, [ 'size' => 64, 'default' => 'identicon' ] ),
                        ];
                    }
                }
            }

            $primary_avatar = ! empty( $inst_list[0]['avatar'] ) ? $inst_list[0]['avatar'] : ( ! empty( $student_leaders_list[0]['avatar'] ) ? $student_leaders_list[0]['avatar'] : '' );
            $primary_name   = ! empty( $inst_list[0]['name'] ) ? $inst_list[0]['name'] : ( ! empty( $student_leaders_list[0]['name'] ) ? $student_leaders_list[0]['name'] : '' );

            $text_color = self::get_contrast_color( $bg_color );

            $fc_events[] = [
                'id'              => (string) $row->id,
                'title'           => $title,
                'start'           => str_replace( ' ', 'T', $row->start_datetime ),
                'end'             => str_replace( ' ', 'T', $row->end_datetime ),
                'backgroundColor' => $bg_color,
                'borderColor'     => $bg_color,
                'textColor'       => $text_color,
                'extendedProps'   => [
                    'text_color'           => $text_color,
                    'color'                => $bg_color,
                    'raw_title'            => $row->title,
                    'program_id'           => (int) $row->program_id,
                    'program_name'         => $row->program_name,
                    'program_code'         => $row->program_code,
                    'subject_id'           => (int) $row->subject_id,
                    'subject_name'         => $row->subject_name,
                    'subject_code'         => $row->subject_code,
                    'event_type'           => $row->event_type,
                    'location'             => $row->location,
                    'online_url'           => $row->online_url,
                    'status'               => $row->status,
                    'start_raw'            => $row->start_datetime,
                    'end_raw'              => $row->end_datetime,
                    'start_local_iso'      => str_replace( ' ', 'T', substr( $row->start_datetime, 0, 16 ) ),
                    'end_local_iso'        => str_replace( ' ', 'T', substr( $row->end_datetime, 0, 16 ) ),
                    'start_time_label'     => self::format_local_datetime( $row->start_datetime, get_option( 'time_format', 'H:i' ) ),
                    'end_time_label'       => self::format_local_datetime( $row->end_datetime, get_option( 'time_format', 'H:i' ) ),
                    'date_label'           => self::format_local_datetime( $row->start_datetime, get_option( 'date_format', 'd-m-Y' ) ),
                    'description'          => $row->description,
                    'program_description'  => $row->program_description ?? '',
                    'subject_description'  => $row->subject_description ?? '',
                    'module_name'          => $row->module_name ?? '',
                    'module_order'         => (int) ( $row->module_order ?? 0 ),
                    'recurrence_group_id'  => $row->recurrence_group_id,
                    'gcal_event_id'        => $row->gcal_event_id,
                    'gcal_sync_status'     => $row->gcal_sync_status,
                    'attendance_taken'     => intval( $row->attendance_count ) > 0,
                    'instructors'          => $inst_list,
                    'external_instructors' => array_values( array_filter( $inst_list, function( $i ) { return ! empty( $i['is_external'] ); } ) ),
                    'teacher_ids'          => array_values( array_filter( array_map( function( $i ) { return empty( $i['is_external'] ) ? (int) $i['id'] : 0; }, $inst_list ) ) ),
                    'student_leaders'      => $student_leaders_list,
                    'primary_avatar'       => $primary_avatar,
                    'primary_name'         => $primary_name,
                    'primary_teacher_id'   => ! empty( $inst_list[0]['id'] ) ? (int) $inst_list[0]['id'] : 0,
                ],
            ];
        }

        return $fc_events;
    }

    /**
     * Obtener el detalle de un evento por ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $table_prog = $wpdb->prefix . 'aura_cal_programs';
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';
        $table_inst = $wpdb->prefix . 'aura_cal_event_instructors';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*,
                    p.name AS program_name, p.code AS program_code,
                    s.name AS subject_name, s.code AS subject_code,
                    s.default_teacher_id AS subject_default_teacher_id, s.teachers AS subject_teachers, s.external_teachers AS subject_external_teachers
             FROM {$table_evts} e
             LEFT JOIN {$table_prog} p ON p.id = e.program_id
             LEFT JOIN {$table_subj} s ON s.id = e.subject_id
             WHERE e.id = %d AND e.deleted_at IS NULL",
            $id
        ) );

        if ( ! $row ) {
            return null;
        }

        // Obtener instructores asociados con sus avatares y nombres normalizados
        if ( class_exists( 'Aura_Calendar_Setup' ) ) {
            Aura_Calendar_Setup::maybe_add_event_instructors_columns();
        }

        $inst_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table_inst}`" );
        $order_clause = in_array( 'role', $inst_cols, true )
            ? "ORDER BY CASE WHEN ei.role = 'lead' THEN 0 ELSE 1 END, ei.id ASC"
            : "ORDER BY ei.id ASC";

        $user_id_col = in_array( 'teacher_id', $inst_cols, true )
            ? ( in_array( 'instructor_id', $inst_cols, true )
                ? "COALESCE(NULLIF(ei.teacher_id, 0), ei.instructor_id, 0)"
                : "ei.teacher_id" )
            : ( in_array( 'instructor_id', $inst_cols, true ) ? "ei.instructor_id" : "0" );

        $instructors = $wpdb->get_results( $wpdb->prepare(
            "SELECT ei.*, u.display_name, u.user_email
             FROM {$table_inst} ei
             LEFT JOIN {$wpdb->users} u ON u.ID = {$user_id_col}
             WHERE ei.event_id = %d
             {$order_clause}",
            $id
        ) );

        $t_students = $wpdb->prefix . 'aura_students';
        $custom_photos = [];

        if ( is_array( $instructors ) ) {
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_students}'" ) === $t_students && ! empty( $instructors ) ) {
                $twp_ids = array_unique( array_filter( array_map( function( $inst ) {
                    return (int) ( $inst->teacher_id ?: ( $inst->instructor_id ?? 0 ) );
                }, $instructors ) ) );
                if ( ! empty( $twp_ids ) ) {
                    $twp_in = implode( ',', $twp_ids );
                    $photos = $wpdb->get_results( "SELECT wp_user_id, photo_url FROM {$t_students} WHERE wp_user_id IN ({$twp_in}) AND photo_url IS NOT NULL AND photo_url != ''" );
                    if ( is_array( $photos ) ) {
                        foreach ( $photos as $p ) {
                            $custom_photos[ (int) $p->wp_user_id ] = $p->photo_url;
                        }
                    }
                }
            }

            $tp_ids = array_unique( array_filter( array_map( function( $inst ) {
                return (int) ( $inst->third_party_id ?? 0 );
            }, $instructors ) ) );
            $tp_map = [];
            if ( ! empty( $tp_ids ) ) {
                $t_tp = $wpdb->prefix . 'aura_finance_third_parties';
                if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_tp}'" ) === $t_tp ) {
                    $in_tp = implode( ',', array_map( 'intval', $tp_ids ) );
                    $tp_records = $wpdb->get_results( "SELECT id, wp_user_id, logo_id, commercial_name, full_name, email, phone FROM {$t_tp} WHERE id IN ({$in_tp})", OBJECT_K );
                    if ( is_array( $tp_records ) ) {
                        $tp_map = $tp_records;
                    }
                }
            }

            foreach ( $instructors as &$inst ) {
                $is_ext  = ! empty( $inst->is_external );
                $real_id = (int) ( $inst->teacher_id ?: ( $inst->instructor_id ?? 0 ) );
                $tp_id   = ! empty( $inst->third_party_id ) ? (int) $inst->third_party_id : 0;
                $tp_info = ( $tp_id > 0 && isset( $tp_map[ $tp_id ] ) ) ? $tp_map[ $tp_id ] : null;

                $is_wp_user = false;
                $av_url     = '';

                if ( ! $is_ext ) {
                    $av_url = ! empty( $custom_photos[ $real_id ] )
                        ? $custom_photos[ $real_id ]
                        : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                } else {
                    if ( ! empty( $inst->avatar_url ) ) {
                        $av_url = $inst->avatar_url;
                    }

                    if ( $real_id > 0 ) {
                        $is_wp_user = true;
                        if ( empty( $av_url ) ) {
                            $av_url = ! empty( $custom_photos[ $real_id ] )
                                ? $custom_photos[ $real_id ]
                                : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                        }
                    } elseif ( $tp_info ) {
                        if ( ! empty( $tp_info->wp_user_id ) && (int) $tp_info->wp_user_id > 0 ) {
                            $is_wp_user = true;
                            $real_id    = (int) $tp_info->wp_user_id;
                            if ( empty( $av_url ) ) {
                                $av_url = ! empty( $custom_photos[ $real_id ] )
                                    ? $custom_photos[ $real_id ]
                                    : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                            }
                        } elseif ( ! empty( $tp_info->logo_id ) && empty( $av_url ) ) {
                            $av_url = wp_get_attachment_image_url( (int) $tp_info->logo_id, 'thumbnail' ) ?: ( wp_get_attachment_url( (int) $tp_info->logo_id ) ?: '' );
                        }
                    }

                    if ( empty( $av_url ) && ! empty( $inst->external_email ) ) {
                        $u_by_email = get_user_by( 'email', $inst->external_email );
                        if ( $u_by_email ) {
                            $is_wp_user = true;
                            $real_id    = (int) $u_by_email->ID;
                            $av_url     = ! empty( $custom_photos[ $real_id ] )
                                ? $custom_photos[ $real_id ]
                                : get_avatar_url( $real_id, [ 'size' => 64, 'default' => 'identicon' ] );
                        } else {
                            $av_url = get_avatar_url( $inst->external_email, [ 'size' => 64, 'default' => 'identicon' ] );
                        }
                    }
                }

                if ( ! empty( $av_url ) && str_starts_with( $av_url, ':/' ) ) {
                    $av_url = site_url( substr( $av_url, 1 ) );
                }

                $inst_name  = $is_ext ? ( $inst->external_name ?: ( ( $tp_info && ! empty( $tp_info->commercial_name ) ) ? $tp_info->commercial_name : ( ( $tp_info && ! empty( $tp_info->full_name ) ) ? $tp_info->full_name : __( 'Instructor Externo', 'aura' ) ) ) ) : ( $inst->display_name ?: __( 'Profesor', 'aura' ) );
                $inst_email = $is_ext ? ( $inst->external_email ?: ( ( $tp_info && ! empty( $tp_info->email ) ) ? $tp_info->email : '' ) ) : $inst->user_email;

                $inst->avatar         = $av_url;
                $inst->avatar_url     = $av_url;
                $inst->is_wp_user     = $is_wp_user;
                $inst->wp_user_id     = $is_wp_user ? $real_id : null;
                $inst->name           = $inst_name;
                $inst->id             = $real_id;
                $inst->is_external    = $is_ext ? 1 : 0;
                $inst->external_name  = $inst_name;
                $inst->external_email = $inst_email;
                $inst->external_phone = $inst->external_phone ?? ( ( $tp_info && ! empty( $tp_info->phone ) ) ? $tp_info->phone : '' );
                $inst->external_org   = $inst->external_org ?? ( ( $tp_info && ! empty( $tp_info->commercial_name ) ) ? $tp_info->commercial_name : '' );
                $inst->third_party_id = $tp_id ?: null;
            }
            unset( $inst );
        }

        // Fallback a los profesores y docentes externos de la materia si el evento no tiene instructores explícitos
        if ( empty( $instructors ) && ! empty( $row->subject_id ) ) {
            $instructors = [];
            $subj_teachers = [];
            if ( ! empty( $row->subject_teachers ) ) {
                $dec = json_decode( $row->subject_teachers, true );
                if ( is_array( $dec ) ) {
                    $subj_teachers = array_values( array_unique( array_filter( array_map( 'intval', $dec ) ) ) );
                }
            }
            if ( empty( $subj_teachers ) && ! empty( $row->subject_default_teacher_id ) ) {
                $subj_teachers = [ intval( $row->subject_default_teacher_id ) ];
            }

            foreach ( $subj_teachers as $s_uid ) {
                $u = get_userdata( $s_uid );
                if ( $u ) {
                    $s_av = ! empty( $custom_photos[ $s_uid ] ) ? $custom_photos[ $s_uid ] : get_avatar_url( $s_uid, [ 'size' => 64, 'default' => 'identicon' ] );
                    if ( ! empty( $s_av ) && str_starts_with( $s_av, ':/' ) ) {
                        $s_av = site_url( substr( $s_av, 1 ) );
                    }
                    $instructors[] = (object) [
                        'id'             => $s_uid,
                        'name'           => $u->display_name,
                        'email'          => $u->user_email,
                        'role'           => ( count( $instructors ) === 0 ) ? 'lead' : 'secondary',
                        'is_external'    => 0,
                        'is_wp_user'     => true,
                        'wp_user_id'     => $s_uid,
                        'third_party_id' => null,
                        'external_name'  => '',
                        'external_email' => '',
                        'external_phone' => '',
                        'external_org'   => '',
                        'avatar'         => $s_av,
                        'avatar_url'     => $s_av,
                    ];
                }
            }

            if ( ! empty( $row->subject_external_teachers ) ) {
                $dec_subj_ext = json_decode( $row->subject_external_teachers, true );
                if ( is_array( $dec_subj_ext ) ) {
                    foreach ( $dec_subj_ext as $se ) {
                        $se_name = ! empty( $se['commercial_name'] ) ? $se['commercial_name'] : ( ! empty( $se['name'] ) ? $se['name'] : ( $se['external_name'] ?? '' ) );
                        if ( empty( $se_name ) ) {
                            continue;
                        }
                        $se_av = ! empty( $se['avatar_url'] ) ? $se['avatar_url'] : ( $se['avatar'] ?? '' );
                        $se_uid = ! empty( $se['wp_user_id'] ) ? intval( $se['wp_user_id'] ) : 0;
                        if ( empty( $se_av ) && $se_uid > 0 ) {
                            $se_av = ! empty( $custom_photos[ $se_uid ] ) ? $custom_photos[ $se_uid ] : get_avatar_url( $se_uid, [ 'size' => 64, 'default' => 'identicon' ] );
                        }
                        if ( empty( $se_av ) && ! empty( $se['email'] ) ) {
                            $u_em = get_user_by( 'email', $se['email'] );
                            if ( $u_em ) {
                                $se_uid = (int) $u_em->ID;
                                $se_av = get_avatar_url( $se_uid, [ 'size' => 64, 'default' => 'identicon' ] );
                            } else {
                                $se_av = get_avatar_url( $se['email'], [ 'size' => 64, 'default' => 'identicon' ] );
                            }
                        }
                        if ( ! empty( $se_av ) && str_starts_with( $se_av, ':/' ) ) {
                            $se_av = site_url( substr( $se_av, 1 ) );
                        }

                        $instructors[] = (object) [
                            'id'             => $se_uid,
                            'name'           => $se_name,
                            'email'          => $se['email'] ?? '',
                            'role'           => ( count( $instructors ) === 0 ) ? 'lead' : 'secondary',
                            'is_external'    => 1,
                            'is_wp_user'     => $se_uid > 0,
                            'wp_user_id'     => $se_uid ?: null,
                            'third_party_id' => ! empty( $se['third_party_id'] ) ? intval( $se['third_party_id'] ) : null,
                            'external_name'  => $se_name,
                            'external_email' => $se['email'] ?? '',
                            'external_phone' => $se['phone'] ?? '',
                            'external_org'   => $se['commercial_name'] ?? ( $se['org'] ?? '' ),
                            'avatar'         => $se_av,
                            'avatar_url'     => $se_av,
                        ];
                    }
                }
            }
        }

        $row->instructors = is_array( $instructors ) ? $instructors : [];
        $row->external_instructors = array_values( array_filter( $row->instructors, function( $inst ) {
            return ! empty( $inst->is_external );
        } ) );
        $row->teacher_ids = array_values( array_filter( array_map( function( $i ) {
            return empty( $i->is_external ) ? (int) $i->id : 0;
        }, $row->instructors ) ) );
        $row->primary_teacher_id = ! empty( $row->instructors ) ? (int) $row->instructors[0]->id : 0;

        // Decodificar líderes estudiantiles asociados con avatares y etiquetas de rol
        $row->student_leaders_list = [];
        if ( ! empty( $row->student_leaders ) ) {
            $raw_leaders = json_decode( $row->student_leaders, true );
            if ( is_array( $raw_leaders ) ) {
                $roles_map = [
                    'program_leader'  => __( 'Líder de Programa', 'aura' ),
                    'activity_leader' => __( 'Líder de Actividad', 'aura' ),
                    'presenter'       => __( 'Expositor / Dar Clase', 'aura' ),
                    'monitor'         => __( 'Monitor / Moderador', 'aura' ),
                ];
                foreach ( $raw_leaders as $sl ) {
                    $sid = intval( $sl['student_id'] ?? 0 );
                    if ( ! $sid ) {
                        continue;
                    }
                    $s_user = get_userdata( $sid );
                    $s_name = $s_user ? $s_user->display_name : ( $sl['student_name'] ?? ( '#' . $sid ) );
                    $role_k = $sl['role'] ?? 'activity_leader';
                    $row->student_leaders_list[] = [
                        'student_id' => $sid,
                        'name'       => $s_name,
                        'email'      => $s_user ? $s_user->user_email : '',
                        'role'       => $role_k,
                        'role_label' => $roles_map[ $role_k ] ?? $role_k,
                        'notes'      => $sl['notes'] ?? '',
                        'avatar'     => get_avatar_url( $sid, [ 'size' => 64, 'default' => 'identicon' ] ),
                    ];
                }
            }
        }

        return $row;
    }

    /**
     * Crear o actualizar un evento (soporta recurrencia simplificada)
     *
     * @param array $data Datos del formulario
     * @return array|WP_Error ['ids' => array, 'message' => string]
     */
    public static function save( array $data ) {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $table_inst = $wpdb->prefix . 'aura_cal_event_instructors';

        $id           = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;
        $program_id   = ! empty( $data['program_id'] ) ? intval( $data['program_id'] ) : 0;
        $subject_id   = ! empty( $data['subject_id'] ) ? intval( $data['subject_id'] ) : null;
        $title        = sanitize_text_field( $data['title'] ?? '' );
        $event_type   = in_array( $data['event_type'] ?? '', [ 'class', 'exam', 'workshop', 'activity', 'break', 'other' ], true ) ? $data['event_type'] : 'class';
        $description  = sanitize_textarea_field( wp_unslash( $data['description'] ?? '' ) );
        $location     = sanitize_text_field( $data['location'] ?? '' );
        $online_url   = esc_url_raw( $data['online_url'] ?? '' );
        $color        = sanitize_hex_color( $data['color'] ?? '' );
        $status       = in_array( $data['status'] ?? '', [ 'scheduled', 'completed', 'cancelled', 'postponed' ], true ) ? $data['status'] : 'scheduled';
        $is_recurring = ! empty( $data['is_recurring'] ) && $data['is_recurring'] === '1';

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'El título del evento es obligatorio.', 'aura' ) );
        }

        if ( ! $program_id ) {
            return new WP_Error( 'missing_program', __( 'Debe seleccionar un programa.', 'aura' ) );
        }

        // Obtener instructores seleccionados (array de IDs)
        $teacher_ids = [];
        if ( ! empty( $data['teacher_ids'] ) ) {
            if ( is_array( $data['teacher_ids'] ) ) {
                $teacher_ids = array_unique( array_filter( array_map( 'intval', $data['teacher_ids'] ) ) );
            } else {
                $teacher_ids = array_unique( array_filter( array_map( 'intval', explode( ',', $data['teacher_ids'] ) ) ) );
            }
        }

        // Si se especificó un profesor principal, priorizarlo en la primera posición
        $primary_teacher_id = ! empty( $data['primary_teacher_id'] ) ? intval( $data['primary_teacher_id'] ) : ( ! empty( $teacher_ids ) ? $teacher_ids[0] : 0 );
        if ( $primary_teacher_id && in_array( $primary_teacher_id, $teacher_ids, true ) ) {
            $teacher_ids = array_values( array_diff( $teacher_ids, [ $primary_teacher_id ] ) );
            array_unshift( $teacher_ids, $primary_teacher_id );
        }
        $has_instructor_id_col = (bool) $wpdb->get_results( "SHOW COLUMNS FROM `{$table_inst}` LIKE 'instructor_id'" );
        $has_tp_id_col         = (bool) $wpdb->get_results( "SHOW COLUMNS FROM `{$table_inst}` LIKE 'third_party_id'" );
        $has_avatar_url_col    = (bool) $wpdb->get_results( "SHOW COLUMNS FROM `{$table_inst}` LIKE 'avatar_url'" );

        // Procesar líderes estudiantiles asignados
        $student_leaders_json = null;
        if ( isset( $data['student_leaders'] ) ) {
            if ( is_array( $data['student_leaders'] ) ) {
                $clean_leaders = [];
                foreach ( $data['student_leaders'] as $sl ) {
                    $sid = intval( $sl['student_id'] ?? 0 );
                    if ( $sid > 0 ) {
                        $clean_leaders[] = [
                            'student_id'   => $sid,
                            'role'         => sanitize_text_field( $sl['role'] ?? 'activity_leader' ),
                            'notes'        => sanitize_text_field( $sl['notes'] ?? '' ),
                            'student_name' => sanitize_text_field( $sl['student_name'] ?? '' ),
                        ];
                    }
                }
                $student_leaders_json = ! empty( $clean_leaders ) ? wp_json_encode( $clean_leaders ) : null;
            } elseif ( is_string( $data['student_leaders'] ) && ! empty( $data['student_leaders'] ) ) {
                $student_leaders_json = stripslashes( $data['student_leaders'] );
            }
        }

        // Procesar instructores externos (terceros)
        $external_instructors = [];
        if ( isset( $data['external_instructors_json'] ) && ! empty( $data['external_instructors_json'] ) ) {
            $dec = json_decode( stripslashes( $data['external_instructors_json'] ), true );
            if ( is_array( $dec ) ) {
                $external_instructors = $dec;
            }
        } elseif ( isset( $data['external_instructors'] ) ) {
            if ( is_array( $data['external_instructors'] ) ) {
                $external_instructors = $data['external_instructors'];
            } elseif ( is_string( $data['external_instructors'] ) ) {
                $dec = json_decode( stripslashes( $data['external_instructors'] ), true );
                if ( is_array( $dec ) ) {
                    $external_instructors = $dec;
                }
            }
        }

        // Helper para persistir instructores internos y externos
        $persist_instructors = function( $target_evt_id ) use ( $wpdb, $table_inst, $teacher_ids, $primary_teacher_id, $has_instructor_id_col, $has_tp_id_col, $has_avatar_url_col, $external_instructors ) {
            foreach ( $teacher_ids as $tid ) {
                $inst_payload = [
                    'event_id'   => $target_evt_id,
                    'teacher_id' => $tid,
                    'role'       => ( $tid === $primary_teacher_id ) ? 'lead' : 'assistant',
                    'is_external'=> 0,
                    'created_at' => current_time( 'mysql' ),
                ];
                $inst_formats = [ '%d', '%d', '%s', '%d', '%s' ];
                if ( $has_instructor_id_col ) {
                    $inst_payload['instructor_id'] = $tid;
                    $inst_formats[] = '%d';
                }
                $wpdb->insert( $table_inst, $inst_payload, $inst_formats );
            }

            $has_internal = ! empty( $teacher_ids );
            $ext_idx = 0;
            foreach ( $external_instructors as $ext ) {
                $ext_name = sanitize_text_field( $ext['name'] ?? ( $ext['external_name'] ?? '' ) );
                if ( empty( $ext_name ) ) {
                    continue;
                }
                $ext_wp_user_id = ! empty( $ext['wp_user_id'] ) ? intval( $ext['wp_user_id'] ) : ( ! empty( $ext['user_id'] ) ? intval( $ext['user_id'] ) : 0 );
                $ext_email = sanitize_email( $ext['email'] ?? ( $ext['external_email'] ?? '' ) );
                if ( empty( $ext_wp_user_id ) && ! empty( $ext_email ) ) {
                    $u_chk = get_user_by( 'email', $ext_email );
                    if ( $u_chk ) {
                        $ext_wp_user_id = (int) $u_chk->ID;
                    }
                }

                $ext_role = sanitize_key( $ext['role'] ?? 'guest' );
                if ( ! $has_internal && $ext_idx === 0 && ( $ext_role === 'lead' || $ext_role === 'primary' || $ext_role === 'guest' ) ) {
                    $ext_role = 'lead';
                }

                $ext_avatar_url = ! empty( $ext['avatar_url'] ) ? esc_url_raw( $ext['avatar_url'] ) : ( ! empty( $ext['avatar'] ) ? esc_url_raw( $ext['avatar'] ) : '' );
                if ( empty( $ext_avatar_url ) && $ext_wp_user_id > 0 ) {
                    $raw_av = get_avatar_url( $ext_wp_user_id );
                    if ( ! empty( $raw_av ) ) {
                        if ( str_starts_with( $raw_av, ':/' ) ) {
                            $raw_av = site_url( substr( $raw_av, 1 ) );
                        }
                        $ext_avatar_url = esc_url_raw( $raw_av );
                    }
                }

                $tp_id = ! empty( $ext['third_party_id'] ) ? intval( $ext['third_party_id'] ) : ( ! empty( $ext['tp_id'] ) ? intval( $ext['tp_id'] ) : 0 );
                if ( empty( $ext_avatar_url ) && $tp_id > 0 ) {
                    $t_tp = $wpdb->prefix . 'aura_finance_third_parties';
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_tp}'" ) === $t_tp ) {
                        $tp_row = $wpdb->get_row( $wpdb->prepare( "SELECT wp_user_id, logo_id FROM {$t_tp} WHERE id = %d", $tp_id ) );
                        if ( $tp_row ) {
                            if ( ! empty( $tp_row->wp_user_id ) ) {
                                $raw_av = get_avatar_url( (int) $tp_row->wp_user_id );
                                if ( ! empty( $raw_av ) ) {
                                    if ( str_starts_with( $raw_av, ':/' ) ) {
                                        $raw_av = site_url( substr( $raw_av, 1 ) );
                                    }
                                    $ext_avatar_url = esc_url_raw( $raw_av );
                                }
                            } elseif ( ! empty( $tp_row->logo_id ) ) {
                                $ext_avatar_url = esc_url_raw( wp_get_attachment_image_url( (int) $tp_row->logo_id, 'thumbnail' ) ?: wp_get_attachment_url( (int) $tp_row->logo_id ) );
                            }
                        }
                    }
                }

                $ext_payload = [
                    'event_id'       => $target_evt_id,
                    'teacher_id'     => $ext_wp_user_id,
                    'role'           => $ext_role,
                    'is_external'    => 1,
                    'external_name'  => $ext_name,
                    'external_email' => $ext_email,
                    'external_phone' => sanitize_text_field( $ext['phone'] ?? ( $ext['external_phone'] ?? '' ) ),
                    'external_org'   => sanitize_text_field( $ext['organization'] ?? ( $ext['org'] ?? ( $ext['external_org'] ?? '' ) ) ),
                    'notes'          => sanitize_text_field( $ext['notes'] ?? '' ),
                    'created_at'     => current_time( 'mysql' ),
                ];
                $ext_formats = [ '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ];
                if ( $has_instructor_id_col ) {
                    $ext_payload['instructor_id'] = $ext_wp_user_id;
                    $ext_formats[] = '%d';
                }
                if ( $has_tp_id_col && $tp_id > 0 ) {
                    $ext_payload['third_party_id'] = $tp_id;
                    $ext_formats[] = '%d';
                }
                if ( $has_avatar_url_col && ! empty( $ext_avatar_url ) ) {
                    $ext_payload['avatar_url'] = $ext_avatar_url;
                    $ext_formats[] = '%s';
                }
                $wpdb->insert( $table_inst, $ext_payload, $ext_formats );
                $ext_idx++;
            }
        };

        $created_event_ids = [];

        // ── CASO A: EVENTO RECURRENTE (NUEVA SERIE) ──
        if ( $id === 0 && $is_recurring ) {
            $rec_days = isset( $data['recurring_days'] ) && is_array( $data['recurring_days'] ) ? array_map( 'intval', $data['recurring_days'] ) : [];
            if ( empty( $rec_days ) ) {
                return new WP_Error( 'missing_recurrence_days', __( 'Debe seleccionar al menos un día de la semana para la recurrencia.', 'aura' ) );
            }

            $date_start = sanitize_text_field( $data['rec_date_start'] ?? '' );
            $date_end   = sanitize_text_field( $data['rec_date_end'] ?? '' );
            $time_start = sanitize_text_field( $data['rec_time_start'] ?? '' );
            $time_end   = sanitize_text_field( $data['rec_time_end'] ?? '' );

            if ( empty( $date_start ) || empty( $date_end ) || empty( $time_start ) || empty( $time_end ) ) {
                return new WP_Error( 'missing_recurrence_dates', __( 'Debe completar el rango de fechas y horas para la serie recurrente.', 'aura' ) );
            }

            $ts_current = strtotime( $date_start );
            $ts_end     = strtotime( $date_end );

            if ( $ts_end < $ts_current ) {
                return new WP_Error( 'invalid_dates', __( 'La fecha fin no puede ser anterior a la fecha de inicio.', 'aura' ) );
            }

            // Generar identificador de grupo recurrente
            $recurrence_group_id = wp_generate_uuid4();
            $rule_summary = sprintf( 'Dias: %s | Horas: %s - %s', implode( ',', $rec_days ), $time_start, $time_end );

            // Límite de seguridad para evitar bucles infinitos (máx 365 días)
            $max_days = 365;
            $days_count = 0;

            while ( $ts_current <= $ts_end && $days_count < $max_days ) {
                $day_of_week = (int) date( 'N', $ts_current ); // 1 (Lunes) a 7 (Domingo)

                if ( in_array( $day_of_week, $rec_days, true ) ) {
                    $curr_date_str = date( 'Y-m-d', $ts_current );
                    $evt_start     = $curr_date_str . ' ' . $time_start . ':00';
                    $evt_end       = $curr_date_str . ' ' . $time_end . ':00';

                    $wpdb->insert(
                        $table_evts,
                        [
                            'program_id'          => $program_id,
                            'subject_id'          => $subject_id,
                            'title'               => $title,
                            'description'         => $description,
                            'student_leaders'     => $student_leaders_json,
                            'event_type'          => $event_type,
                            'start_datetime'      => $evt_start,
                            'end_datetime'        => $evt_end,
                            'location'            => $location,
                            'online_url'          => $online_url,
                            'color'               => $color,
                            'status'              => $status,
                            'recurrence_group_id' => $recurrence_group_id,
                            'recurrence_rule'     => $rule_summary,
                            'gcal_sync_status'    => 'pending',
                            'created_by'          => get_current_user_id(),
                            'created_at'          => current_time( 'mysql' ),
                            'updated_at'          => current_time( 'mysql' ),
                        ],
                        [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                    );

                    $new_evt_id = (int) $wpdb->insert_id;
                    if ( $new_evt_id > 0 ) {
                        $created_event_ids[] = $new_evt_id;
                        $persist_instructors( $new_evt_id );
                    }
                }

                $ts_current = strtotime( '+1 day', $ts_current );
                $days_count++;
            }

            // Sincronizar con Google Calendar en lote (primeros 20 inmediatos, resto batch)
            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                $sync_batch = array_slice( $created_event_ids, 0, 20 );
                foreach ( $sync_batch as $sid ) {
                    Aura_Calendar_Google_Sync::sync_event( $sid );
                }
            }

            self::bump_sync_version();

            return [
                'ids'     => $created_event_ids,
                'count'   => count( $created_event_ids ),
                'message' => sprintf( __( 'Se crearon con éxito %d eventos en la serie recurrente.', 'aura' ), count( $created_event_ids ) ),
            ];
        }

        // ── CASO B: EVENTO ÚNICO (CREACIÓN O ACTUALIZACIÓN) ──
        $start_raw = ! empty( $data['start_datetime'] ) ? $data['start_datetime'] : ( $data['start'] ?? '' );
        $end_raw   = ! empty( $data['end_datetime'] ) ? $data['end_datetime'] : ( $data['end'] ?? '' );
        $start_dt  = str_replace( 'T', ' ', sanitize_text_field( $start_raw ) );
        if ( strlen( $start_dt ) === 16 ) {
            $start_dt .= ':00';
        }
        $end_dt = str_replace( 'T', ' ', sanitize_text_field( $end_raw ) );
        if ( strlen( $end_dt ) === 16 ) {
            $end_dt .= ':00';
        }

        if ( empty( $start_dt ) || empty( $end_dt ) ) {
            return new WP_Error( 'missing_dates', __( 'Debe indicar fecha/hora de inicio y fin.', 'aura' ) );
        }

        if ( strtotime( $end_dt ) < strtotime( $start_dt ) ) {
            return new WP_Error( 'invalid_dates', __( 'La fecha y hora de fin debe ser posterior a la de inicio.', 'aura' ) );
        }

        $fields = [
            'program_id'      => $program_id,
            'subject_id'      => $subject_id,
            'title'           => $title,
            'description'     => $description,
            'student_leaders' => $student_leaders_json,
            'event_type'      => $event_type,
            'start_datetime'  => $start_dt,
            'end_datetime'    => $end_dt,
            'location'        => $location,
            'online_url'      => $online_url,
            'color'           => $color,
            'status'          => $status,
            'updated_at'      => current_time( 'mysql' ),
        ];

        $formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

        if ( $id > 0 ) {
            // Actualizar evento existente
            $wpdb->update( $table_evts, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
            $event_id = $id;

            // Re-asignar instructores (internos y externos)
            $wpdb->delete( $table_inst, [ 'event_id' => $event_id ], [ '%d' ] );
            $persist_instructors( $event_id );

            // Sincronizar actualización con Google Calendar
            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                Aura_Calendar_Google_Sync::sync_event( $event_id );
            }

            self::bump_sync_version();

            return [
                'ids'     => [ $event_id ],
                'count'   => 1,
                'message' => __( 'Evento actualizado con éxito.', 'aura' ),
            ];
        } else {
            // Crear evento individual nuevo
            $fields['gcal_sync_status'] = 'pending';
            $formats[]                  = '%s';
            $fields['created_by']       = get_current_user_id();
            $formats[]                  = '%d';
            $fields['created_at']       = current_time( 'mysql' );
            $formats[]                  = '%s';

            $wpdb->insert( $table_evts, $fields, $formats );
            $event_id = (int) $wpdb->insert_id;

            $persist_instructors( $event_id );

            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                Aura_Calendar_Google_Sync::sync_event( $event_id );
            }

            self::bump_sync_version();

            return [
                'ids'     => [ $event_id ],
                'count'   => 1,
                'message' => __( 'Evento creado con éxito.', 'aura' ),
            ];
        }
    }

    /**
     * Eliminar evento (y opcionalmente toda la serie recurrente)
     *
     * @param int $id ID del evento
     * @param bool $delete_series Si true y pertenece a una serie, elimina todos los eventos de la serie
     * @return bool
     */
    public static function delete( int $id, bool $delete_series = false ): bool {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';

        $event = $wpdb->get_row( $wpdb->prepare( "SELECT id, recurrence_group_id FROM {$table_evts} WHERE id = %d", $id ) );
        if ( ! $event ) {
            return false;
        }

        $ids_to_delete = [ $id ];

        if ( $delete_series && ! empty( $event->recurrence_group_id ) ) {
            $series_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$table_evts} WHERE recurrence_group_id = %s AND deleted_at IS NULL",
                $event->recurrence_group_id
            ) );
            if ( ! empty( $series_ids ) ) {
                $ids_to_delete = array_map( 'intval', $series_ids );
            }
        }

        foreach ( $ids_to_delete as $del_id ) {
            // Eliminar de Google Calendar
            if ( Aura_Calendar_Google_Sync::is_enabled() ) {
                Aura_Calendar_Google_Sync::delete_event( $del_id );
            }

            // Soft delete en BD
            $wpdb->update(
                $table_evts,
                [
                    'deleted_at' => current_time( 'mysql' ),
                    'status'     => 'cancelled',
                ],
                [ 'id' => $del_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        self::bump_sync_version();

        return true;
    }

    /**
     * Actualizar fechas por arrastre (drag & drop) o redimensionamiento
     *
     * @param int $id
     * @param string $start_dt
     * @param string $end_dt
     * @return bool
     */
    public static function update_dates( int $id, string $start_dt, string $end_dt ): bool {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';

        $start_clean = str_replace( 'T', ' ', substr( $start_dt, 0, 19 ) );
        if ( strlen( $start_clean ) === 16 ) {
            $start_clean .= ':00';
        }
        $end_clean = str_replace( 'T', ' ', substr( $end_dt, 0, 19 ) );
        if ( strlen( $end_clean ) === 16 ) {
            $end_clean .= ':00';
        }

        $updated = $wpdb->update(
            $table_evts,
            [
                'start_datetime' => $start_clean,
                'end_datetime'   => $end_clean,
                'updated_at'     => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $updated !== false ) {
            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                Aura_Calendar_Google_Sync::sync_event( $id );
            }
            self::bump_sync_version();
            return true;
        }

        return false;
    }

    /**
     * Incrementar versión de sincronización del calendario para colaboración en tiempo real
     */
    public static function bump_sync_version(): void {
        update_option( 'aura_cal_sync_version', time() );
        $user = wp_get_current_user();
        update_option( 'aura_cal_sync_author', $user ? $user->display_name : 'Usuario' );
    }

    /**
     * AJAX: Heartbeat de sincronización en tiempo real
     */
    public static function ajax_heartbeat_sync(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $client_version = intval( $_POST['last_version'] ?? ( $_POST['last_sync'] ?? 0 ) );
        $server_version = intval( get_option( self::OPTION_SYNC_VERSION, 0 ) );
        $author         = get_option( 'aura_cal_sync_author', '' );

        $has_updates = $server_version > $client_version;

        wp_send_json_success( [
            'has_updates'     => $has_updates,
            'current_version' => $server_version,
            'sync_version'    => $server_version,
            'updated_by'      => $author,
            'author'          => $author,
            'server_time'     => time(),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_events(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_view_calendar' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $filters = [
            'start'        => sanitize_text_field( $_POST['start'] ?? '' ),
            'end'          => sanitize_text_field( $_POST['end'] ?? '' ),
            'program_id'   => intval( $_POST['program_id'] ?? 0 ),
            'subject_id'   => intval( $_POST['subject_id'] ?? 0 ),
            'teacher_id'   => intval( $_POST['teacher_id'] ?? 0 ),
            'event_type'   => sanitize_text_field( $_POST['event_type'] ?? '' ),
            'status'       => sanitize_text_field( $_POST['status'] ?? '' ),
            'portal_scope' => sanitize_key( $_POST['portal_scope'] ?? '' ),
        ];

        $events = self::get_events( $filters );
        wp_send_json_success( [ 'events' => $events ] );
    }

    public static function ajax_get_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_view_calendar' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $event = self::get( $id );
        if ( ! $event ) {
            wp_send_json_error( [ 'message' => __( 'Evento no encontrado.', 'aura' ) ] );
        }

        wp_send_json_success( [ 'event' => $event ] );
    }

    public static function ajax_save_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para crear o editar eventos.', 'aura' ) ] );
        }

        $res = self::save( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( $res );
    }

    public static function ajax_delete_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_delete_events' ) && ! current_user_can( 'aura_delete_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para eliminar eventos.', 'aura' ) ] );
        }

        $id            = intval( $_POST['id'] ?? 0 );
        $delete_series = ! empty( $_POST['delete_series'] ) && $_POST['delete_series'] === '1';

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $ok = self::delete( $id, $delete_series );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Evento retirado con éxito.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo retirar el evento.', 'aura' ) ] );
        }
    }

    public static function ajax_update_event_dates(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_edit_calendar_events' ) && ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para reprogramar eventos.', 'aura' ) ] );
        }

        $id       = intval( $_POST['id'] ?? 0 );
        $start_dt = sanitize_text_field( $_POST['start'] ?? '' );
        $end_dt   = sanitize_text_field( $_POST['end'] ?? '' );

        if ( ! $id || empty( $start_dt ) || empty( $end_dt ) ) {
            wp_send_json_error( [ 'message' => __( 'Parámetros inválidos.', 'aura' ) ] );
        }

        $ok = self::update_dates( $id, $start_dt, $end_dt );
        if ( $ok ) {
            $time_fmt = get_option( 'time_format', 'H:i' );
            $date_fmt = get_option( 'date_format', 'd-m-Y' );
            wp_send_json_success( [
                'message'          => __( 'Horario actualizado.', 'aura' ),
                'start_time_label' => self::format_local_datetime( $start_dt, $time_fmt ),
                'end_time_label'   => self::format_local_datetime( $end_dt, $time_fmt ),
                'date_label'       => self::format_local_datetime( $start_dt, $date_fmt ),
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo actualizar el horario.', 'aura' ) ] );
        }
    }

    /**
     * Duplicar o clonar un evento existente
     *
     * @param int $id ID del evento a duplicar
     * @param string|null $new_start Nueva fecha/hora de inicio (opcional)
     * @param string|null $new_end Nueva fecha/hora de fin (opcional)
     * @param string|null $title_prefix Prefijo para el título
     * @return int|WP_Error ID del nuevo evento creado
     */
    public static function duplicate( int $id, ?string $new_start = null, ?string $new_end = null, ?string $title_prefix = null ) {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $table_inst = $wpdb->prefix . 'aura_cal_event_instructors';

        $event = self::get( $id );
        if ( ! $event ) {
            return new WP_Error( 'not_found', __( 'Evento original no encontrado.', 'aura' ) );
        }

        $orig_start = $event->start_datetime;
        $orig_end   = $event->end_datetime;
        $duration   = max( 1800, strtotime( $orig_end ) - strtotime( $orig_start ) );

        if ( ! empty( $new_start ) ) {
            $start_clean = str_replace( 'T', ' ', substr( $new_start, 0, 19 ) );
            if ( strlen( $start_clean ) === 16 ) {
                $start_clean .= ':00';
            }
            if ( ! empty( $new_end ) ) {
                $end_clean = str_replace( 'T', ' ', substr( $new_end, 0, 19 ) );
                if ( strlen( $end_clean ) === 16 ) {
                    $end_clean .= ':00';
                }
            } else {
                $end_clean = date( 'Y-m-d H:i:s', strtotime( $start_clean ) + $duration );
            }
            $title = ( $title_prefix !== null ? $title_prefix : '' ) . $event->title;
        } else {
            $start_clean = $orig_start;
            $end_clean   = $orig_end;
            $title       = ( $title_prefix !== null ? $title_prefix : __( '(Copia) ', 'aura' ) ) . $event->title;
        }

        $student_leaders_json = ! empty( $event->student_leaders ) ? $event->student_leaders : null;

        $inserted = $wpdb->insert(
            $table_evts,
            [
                'program_id'          => $event->program_id,
                'subject_id'          => $event->subject_id,
                'title'               => $title,
                'description'         => $event->description,
                'student_leaders'     => $student_leaders_json,
                'event_type'          => $event->event_type,
                'start_datetime'      => $start_clean,
                'end_datetime'        => $end_clean,
                'location'            => $event->location,
                'online_url'          => $event->online_url,
                'color'               => $event->color,
                'status'              => 'scheduled',
                'recurrence_group_id' => null,
                'gcal_sync_status'    => 'pending',
                'created_by'          => get_current_user_id(),
                'created_at'          => current_time( 'mysql' ),
                'updated_at'          => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', __( 'Error al duplicar el registro en la base de datos.', 'aura' ) );
        }

        $new_id = (int) $wpdb->insert_id;

        // Copiar instructores
        $inst_cols   = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table_inst}`" );
        $has_ext     = in_array( 'is_external', $inst_cols, true );
        $has_teach   = in_array( 'teacher_id', $inst_cols, true );
        $has_inst_id = in_array( 'instructor_id', $inst_cols, true );

        $orig_insts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_inst} WHERE event_id = %d", $id ) );
        if ( ! empty( $orig_insts ) ) {
            foreach ( $orig_insts as $oi ) {
                $payload = [
                    'event_id'   => $new_id,
                    'role'       => $oi->role ?? 'assistant',
                    'created_at' => current_time( 'mysql' ),
                ];
                if ( $has_teach ) {
                    $payload['teacher_id'] = (int) ( $oi->teacher_id ?? 0 );
                }
                if ( $has_inst_id ) {
                    $payload['instructor_id'] = (int) ( $oi->instructor_id ?? ( $oi->teacher_id ?? 0 ) );
                }
                if ( $has_ext ) {
                    $payload['is_external']    = (int) ( $oi->is_external ?? 0 );
                    $payload['external_name']  = $oi->external_name ?? null;
                    $payload['external_email'] = $oi->external_email ?? null;
                    $payload['external_phone'] = $oi->external_phone ?? null;
                    $payload['external_org']   = $oi->external_org ?? null;
                }
                $wpdb->insert( $table_inst, $payload );
            }
        }

        self::bump_sync_version();
        return $new_id;
    }

    /**
     * Replicar un evento en una serie recurrente en el mismo día y horario
     *
     * @param int $id ID del evento base
     * @param string $repeat_type 'weekly', 'daily', 'weekdays', 'biweekly'
     * @param string $date_start Fecha inicial (YYYY-MM-DD)
     * @param string $date_end Fecha final (YYYY-MM-DD)
     * @return array|WP_Error
     */
    public static function replicate_series( int $id, string $repeat_type, string $date_start, string $date_end ) {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $table_inst = $wpdb->prefix . 'aura_cal_event_instructors';

        $base_event = self::get( $id );
        if ( ! $base_event ) {
            return new WP_Error( 'not_found', __( 'Evento base no encontrado.', 'aura' ) );
        }

        $ts_start = strtotime( $date_start );
        $ts_end   = strtotime( $date_end );
        if ( ! $ts_start || ! $ts_end || $ts_end < $ts_start ) {
            return new WP_Error( 'invalid_dates', __( 'Rango de fechas inválido para la serie.', 'aura' ) );
        }

        $orig_start_ts = strtotime( $base_event->start_datetime );
        $orig_end_ts   = strtotime( $base_event->end_datetime );
        $duration      = max( 1800, $orig_end_ts - $orig_start_ts );

        $time_start_str   = date( 'H:i:s', $orig_start_ts );
        $base_day_of_week = (int) date( 'N', $orig_start_ts ); // 1 (Lun) a 7 (Dom)

        $recurrence_group_id = ! empty( $base_event->recurrence_group_id ) ? $base_event->recurrence_group_id : wp_generate_uuid4();

        if ( empty( $base_event->recurrence_group_id ) ) {
            $wpdb->update( $table_evts, [ 'recurrence_group_id' => $recurrence_group_id ], [ 'id' => $id ] );
        }

        $created_ids = [];
        $ts_curr     = $ts_start;
        $max_steps   = 366;
        $steps       = 0;

        $orig_insts  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_inst} WHERE event_id = %d", $id ) );
        $inst_cols   = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table_inst}`" );
        $has_ext     = in_array( 'is_external', $inst_cols, true );
        $has_teach   = in_array( 'teacher_id', $inst_cols, true );
        $has_inst_id = in_array( 'instructor_id', $inst_cols, true );

        while ( $ts_curr <= $ts_end && $steps < $max_steps ) {
            $steps++;
            $day_of_week = (int) date( 'N', $ts_curr );
            $curr_date   = date( 'Y-m-d', $ts_curr );

            $match = false;
            if ( $repeat_type === 'weekly' ) {
                $match = ( $day_of_week === $base_day_of_week );
            } elseif ( $repeat_type === 'daily' ) {
                $match = true;
            } elseif ( $repeat_type === 'weekdays' ) {
                $match = ( $day_of_week <= 5 );
            } elseif ( $repeat_type === 'biweekly' ) {
                $diff_days = (int) round( ( $ts_curr - $ts_start ) / 86400 );
                $match = ( $day_of_week === $base_day_of_week && ( $diff_days % 14 === 0 ) );
            }

            if ( $match ) {
                $evt_start_str = $curr_date . ' ' . $time_start_str;
                $evt_end_str   = date( 'Y-m-d H:i:s', strtotime( $evt_start_str ) + $duration );

                if ( substr( $base_event->start_datetime, 0, 10 ) !== $curr_date ) {
                    $ins = $wpdb->insert(
                        $table_evts,
                        [
                            'program_id'          => $base_event->program_id,
                            'subject_id'          => $base_event->subject_id,
                            'title'               => $base_event->title,
                            'description'         => $base_event->description,
                            'student_leaders'     => $base_event->student_leaders,
                            'event_type'          => $base_event->event_type,
                            'start_datetime'      => $evt_start_str,
                            'end_datetime'        => $evt_end_str,
                            'location'            => $base_event->location,
                            'online_url'          => $base_event->online_url,
                            'color'               => $base_event->color,
                            'status'              => 'scheduled',
                            'recurrence_group_id' => $recurrence_group_id,
                            'recurrence_rule'     => "Repetición {$repeat_type}",
                            'gcal_sync_status'    => 'pending',
                            'created_by'          => get_current_user_id(),
                            'created_at'          => current_time( 'mysql' ),
                            'updated_at'          => current_time( 'mysql' ),
                        ],
                        [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                    );

                    if ( $ins ) {
                        $new_id = (int) $wpdb->insert_id;
                        $created_ids[] = $new_id;

                        foreach ( $orig_insts as $oi ) {
                            $payload = [
                                'event_id'   => $new_id,
                                'role'       => $oi->role ?? 'assistant',
                                'created_at' => current_time( 'mysql' ),
                            ];
                            if ( $has_teach ) {
                                $payload['teacher_id'] = (int) ( $oi->teacher_id ?? 0 );
                            }
                            if ( $has_inst_id ) {
                                $payload['instructor_id'] = (int) ( $oi->instructor_id ?? ( $oi->teacher_id ?? 0 ) );
                            }
                            if ( $has_ext ) {
                                $payload['is_external']    = (int) ( $oi->is_external ?? 0 );
                                $payload['external_name']  = $oi->external_name ?? null;
                                $payload['external_email'] = $oi->external_email ?? null;
                                $payload['external_phone'] = $oi->external_phone ?? null;
                                $payload['external_org']   = $oi->external_org ?? null;
                            }
                            $wpdb->insert( $table_inst, $payload );
                        }
                    }
                }
            }

            $ts_curr += 86400;
        }

        self::bump_sync_version();

        return [
            'count'               => count( $created_ids ),
            'created_ids'         => $created_ids,
            'recurrence_group_id' => $recurrence_group_id,
            'message'             => sprintf( __( 'Se generaron %d repeticiones para la serie.', 'aura' ), count( $created_ids ) ),
        ];
    }

    public static function ajax_duplicate_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para duplicar eventos.', 'aura' ) ] );
        }

        $id        = intval( $_POST['id'] ?? 0 );
        $new_start = ! empty( $_POST['new_start'] ) ? sanitize_text_field( $_POST['new_start'] ) : null;
        $new_end   = ! empty( $_POST['new_end'] ) ? sanitize_text_field( $_POST['new_end'] ) : null;
        $title_pfx = isset( $_POST['title_prefix'] ) ? sanitize_text_field( $_POST['title_prefix'] ) : null;

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de evento inválido.', 'aura' ) ] );
        }

        $res = self::duplicate( $id, $new_start, $new_end, $title_pfx );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        $duplicated_event = self::get( $res );
        wp_send_json_success( [
            'message'  => __( 'Evento duplicado exitosamente.', 'aura' ),
            'event_id' => $res,
            'event'    => $duplicated_event,
        ] );
    }

    public static function ajax_replicate_series(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para crear series recurrentes.', 'aura' ) ] );
        }

        $id          = intval( $_POST['id'] ?? 0 );
        $repeat_type = sanitize_key( $_POST['repeat_type'] ?? 'weekly' );
        $date_start  = sanitize_text_field( $_POST['date_start'] ?? '' );
        $date_end    = sanitize_text_field( $_POST['date_end'] ?? '' );

        if ( ! $id || empty( $date_start ) || empty( $date_end ) ) {
            wp_send_json_error( [ 'message' => __( 'Faltan parámetros requeridos para replicar la serie.', 'aura' ) ] );
        }

        $res = self::replicate_series( $id, $repeat_type, $date_start, $date_end );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( $res );
    }
}
