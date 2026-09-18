<?php
/**
 * Exportar / Importar Programas Académicos y sus Materias
 *
 * Soporta: JSON (con jerarquía completa programa→materias) y CSV (plano, editable en Excel).
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Export {

    /**
     * Registrar hooks AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_export_programs', [ __CLASS__, 'ajax_export_programs' ] );
        add_action( 'wp_ajax_aura_cal_import_programs', [ __CLASS__, 'ajax_import_programs' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORTAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener datos estructurados de uno o varios programas con sus materias.
     *
     * @param array $program_ids  Array de IDs. Vacío = todos los activos.
     * @return array
     */
    public static function get_export_data( array $program_ids = [] ): array {
        $args = [ 'limit' => 500 ];
        if ( ! empty( $program_ids ) ) {
            $all      = Aura_Calendar_Programs::get_all( $args );
            $programs = array_filter( $all, fn( $p ) => in_array( (int) $p->id, $program_ids, true ) );
        } else {
            $programs = Aura_Calendar_Programs::get_all( $args );
        }

        $export = [];

        foreach ( $programs as $p ) {
            $subjects_raw = Aura_Calendar_Subjects::get_all( [ 'program_id' => $p->id, 'limit' => 500 ] );

            $subjects = [];
            foreach ( $subjects_raw as $s ) {
                $subjects[] = [
                    'code'           => $s->code ?? '',
                    'name'           => $s->name ?? '',
                    'description'    => $s->description ?? '',
                    'total_hours'    => $s->total_hours ?? null,
                    'color'          => $s->color ?? '',
                    'status'         => $s->status ?? 'active',
                    'teacher_names'  => $s->teachers_names ?? '',
                    'schedule_notes' => $s->schedule_notes ?? '',
                    'modality'       => $s->modality ?? '',
                    'credits'        => $s->credits ?? null,
                    'order'          => $s->order_index ?? null,
                ];
            }

            $export[] = [
                'code'              => $p->code ?? '',
                'name'              => $p->name ?? '',
                'description'       => $p->description ?? '',
                'academic_period'   => $p->academic_period ?? '',
                'start_date'        => $p->start_date ?? '',
                'end_date'          => $p->end_date ?? '',
                'color'             => $p->color ?? '#6366f1',
                'status'            => $p->status ?? 'active',
                'area_name'         => $p->area_name ?? '',
                'coordinator_names' => $p->coordinators_names ?? $p->coordinator_name ?? '',
                'subjects'          => $subjects,
            ];
        }

        return $export;
    }

    /**
     * Generar JSON de exportación.
     *
     * @param array $program_ids
     * @return string
     */
    public static function to_json( array $program_ids = [] ): string {
        $data = [
            'export_version' => '1.8.1',
            'plugin'         => 'aura-business-suite',
            'exported_at'    => current_time( 'c' ),
            'programs'       => self::get_export_data( $program_ids ),
        ];
        return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Generar CSV de exportación (formato plano: 1 fila por materia + columnas de programa).
     *
     * @param array $program_ids
     * @return string
     */
    public static function to_csv( array $program_ids = [] ): string {
        $data  = self::get_export_data( $program_ids );
        $lines = [];

        $headers = [
            'programa_codigo', 'programa_nombre', 'programa_descripcion',
            'periodo_academico', 'fecha_inicio', 'fecha_fin', 'color_programa',
            'estado_programa', 'area', 'coordinadores',
            'materia_codigo', 'materia_nombre', 'materia_descripcion',
            'horas_totales', 'creditos', 'color_materia',
            'estado_materia', 'modalidad', 'profesores', 'notas_horario', 'orden',
        ];
        $lines[] = self::csv_row( $headers );

        foreach ( $data as $prog ) {
            if ( empty( $prog['subjects'] ) ) {
                $lines[] = self::csv_row( [
                    $prog['code'], $prog['name'], $prog['description'],
                    $prog['academic_period'], $prog['start_date'], $prog['end_date'],
                    $prog['color'], $prog['status'], $prog['area_name'], $prog['coordinator_names'],
                    '', '', '', '', '', '', '', '', '', '', '',
                ] );
            } else {
                foreach ( $prog['subjects'] as $s ) {
                    $lines[] = self::csv_row( [
                        $prog['code'], $prog['name'], $prog['description'],
                        $prog['academic_period'], $prog['start_date'], $prog['end_date'],
                        $prog['color'], $prog['status'], $prog['area_name'], $prog['coordinator_names'],
                        $s['code'], $s['name'], $s['description'],
                        $s['total_hours'], $s['credits'], $s['color'],
                        $s['status'], $s['modality'], $s['teacher_names'],
                        $s['schedule_notes'], $s['order'],
                    ] );
                }
            }
        }

        return implode( "\r\n", $lines );
    }

    /**
     * Convertir array a fila CSV correctamente escapada (compatible Excel UTF-8).
     *
     * @param array $fields
     * @return string
     */
    private static function csv_row( array $fields ): string {
        return implode( ',', array_map( function( $f ) {
            return '"' . str_replace( '"', '""', (string) $f ) . '"';
        }, $fields ) );
    }

    // ─────────────────────────────────────────────────────────────
    // IMPORTAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Importar programas y materias desde JSON.
     *
     * @param string $json_string
     * @param bool   $skip_existing  Si true, omite registros con el mismo código/nombre.
     * @return array { created_programs, created_subjects, skipped, errors }
     */
    public static function import_from_json( string $json_string, bool $skip_existing = true ): array {
        $json = json_decode( $json_string, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [ 'created_programs' => 0, 'created_subjects' => 0, 'skipped' => 0,
                     'errors' => [ 'JSON inválido: ' . json_last_error_msg() ] ];
        }

        if ( empty( $json['programs'] ) || ! is_array( $json['programs'] ) ) {
            return [ 'created_programs' => 0, 'created_subjects' => 0, 'skipped' => 0,
                     'errors' => [ 'El archivo JSON no contiene programas válidos.' ] ];
        }

        global $wpdb;
        $created_programs = 0;
        $created_subjects = 0;
        $skipped          = 0;
        $errors           = [];

        foreach ( $json['programs'] as $prog_data ) {
            $name = sanitize_text_field( $prog_data['name'] ?? '' );
            $code = sanitize_text_field( $prog_data['code'] ?? '' );

            if ( empty( $name ) ) {
                $errors[] = 'Programa sin nombre omitido.';
                $skipped++;
                continue;
            }

            $t           = $wpdb->prefix . 'aura_cal_programs';
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$t} WHERE (code = %s OR name = %s) AND deleted_at IS NULL LIMIT 1",
                $code, $name
            ) );

            if ( $existing_id && $skip_existing ) {
                $skipped++;
                $prog_id = (int) $existing_id;
            } else {
                $prog_id = Aura_Calendar_Programs::save( [
                    'name'            => $name,
                    'code'            => $code,
                    'description'     => sanitize_textarea_field( $prog_data['description'] ?? '' ),
                    'academic_period' => sanitize_text_field( $prog_data['academic_period'] ?? '' ),
                    'start_date'      => sanitize_text_field( $prog_data['start_date'] ?? '' ),
                    'end_date'        => sanitize_text_field( $prog_data['end_date'] ?? '' ),
                    'color'           => sanitize_hex_color( $prog_data['color'] ?? '' ) ?: '#6366f1',
                    'status'          => in_array( $prog_data['status'] ?? '', [ 'active', 'archived', 'draft' ], true )
                                         ? $prog_data['status'] : 'active',
                ] );

                if ( is_wp_error( $prog_id ) ) {
                    $errors[] = "Programa '{$name}': " . $prog_id->get_error_message();
                    $skipped++;
                    continue;
                }

                $created_programs++;
            }

            if ( ! empty( $prog_data['subjects'] ) && is_array( $prog_data['subjects'] ) ) {
                $t_subj = $wpdb->prefix . 'aura_cal_subjects';

                foreach ( $prog_data['subjects'] as $s_data ) {
                    $s_name = sanitize_text_field( $s_data['name'] ?? '' );
                    $s_code = sanitize_text_field( $s_data['code'] ?? '' );

                    if ( empty( $s_name ) ) {
                        continue;
                    }

                    $s_exists = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$t_subj} WHERE program_id = %d AND (code = %s OR name = %s) AND deleted_at IS NULL LIMIT 1",
                        $prog_id, $s_code, $s_name
                    ) );

                    if ( $s_exists && $skip_existing ) {
                        continue;
                    }

                    $s_result = Aura_Calendar_Subjects::save( [
                        'program_id'     => $prog_id,
                        'code'           => $s_code,
                        'name'           => $s_name,
                        'description'    => sanitize_textarea_field( $s_data['description'] ?? '' ),
                        'total_hours'    => ! empty( $s_data['total_hours'] ) ? intval( $s_data['total_hours'] ) : null,
                        'credits'        => ! empty( $s_data['credits'] ) ? intval( $s_data['credits'] ) : null,
                        'color'          => sanitize_hex_color( $s_data['color'] ?? '' ) ?: '#3b82f6',
                        'status'         => in_array( $s_data['status'] ?? '', [ 'active', 'archived', 'draft' ], true )
                                             ? $s_data['status'] : 'active',
                        'modality'       => sanitize_text_field( $s_data['modality'] ?? '' ),
                        'schedule_notes' => sanitize_textarea_field( $s_data['schedule_notes'] ?? '' ),
                        'order_index'    => ! empty( $s_data['order'] ) ? intval( $s_data['order'] ) : null,
                    ] );

                    if ( ! is_wp_error( $s_result ) ) {
                        $created_subjects++;
                    }
                }
            }
        }

        return [
            'created_programs' => $created_programs,
            'created_subjects' => $created_subjects,
            'skipped'          => $skipped,
            'errors'           => $errors,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Exportar programas como JSON o CSV (responde con base64 del archivo).
     */
    public static function ajax_export_programs(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para exportar.', 'aura' ) ] );
        }

        $format      = sanitize_key( $_POST['format'] ?? 'json' );
        $program_ids = [];
        // Acepta 'program_id' (singular, desde JS individual) o 'program_ids' (array)
        if ( ! empty( $_POST['program_id'] ) ) {
            $program_ids = [ (int) $_POST['program_id'] ];
        } elseif ( ! empty( $_POST['program_ids'] ) ) {
            $program_ids = array_filter( array_map( 'intval', (array) $_POST['program_ids'] ) );
        }

        $is_all  = empty( $program_ids );
        $suffix  = $is_all ? 'todos' : 'prog-' . implode( '-', $program_ids );
        $date    = current_time( 'Y-m-d' );

        if ( $format === 'csv' ) {
            $content  = self::to_csv( $program_ids );
            $filename = "aura-programas-{$suffix}-{$date}.csv";
            $mime     = 'text/csv';
        } else {
            $content  = self::to_json( $program_ids );
            $filename = "aura-programas-{$suffix}-{$date}.json";
            $mime     = 'application/json';
        }

        wp_send_json_success( [
            'filename' => $filename,
            'mime'     => $mime,
            'content'  => $content, // Texto plano; el JS maneja el BOM en downloadBlob()
        ] );
    }

    /**
     * AJAX: Importar programas desde JSON (recibe base64 del archivo).
     */
    public static function ajax_import_programs(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_programs' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para importar.', 'aura' ) ] );
        }

        // Acepta tanto 'json_data' (texto plano desde FileReader) como 'file_content' (base64 legado)
        $raw_content   = $_POST['json_data'] ?? $_POST['file_content'] ?? '';
        $skip_existing = ( $_POST['skip_existing'] ?? '1' ) !== '0';

        if ( empty( $raw_content ) ) {
            wp_send_json_error( [ 'message' => __( 'No se recibió contenido del archivo.', 'aura' ) ] );
        }

        // Si parece base64, decodificar; si ya es JSON, usar directamente
        $json_string = $raw_content;
        if ( ! str_starts_with( trim( $raw_content ), '{' ) && ! str_starts_with( trim( $raw_content ), '[' ) ) {
            $decoded = base64_decode( $raw_content, true );
            if ( $decoded !== false ) {
                $json_string = $decoded;
            }
        }

        $result = self::import_from_json( $json_string, $skip_existing );

        $message = sprintf(
            /* translators: %1$d programas, %2$d materias, %3$d omitidos */
            __( 'Importación completada: %1$d programa(s) creado(s), %2$d materia(s) creada(s), %3$d omitido(s) por duplicado.', 'aura' ),
            $result['created_programs'],
            $result['created_subjects'],
            $result['skipped']
        );

        if ( ! empty( $result['errors'] ) ) {
            $message .= ' ' . __( 'Errores:', 'aura' ) . ' ' . implode( '; ', array_slice( $result['errors'], 0, 5 ) );
        }

        wp_send_json_success( [
            'message'          => $message,
            'created_programs' => $result['created_programs'],
            'created_subjects' => $result['created_subjects'],
            'skipped'          => $result['skipped'],
            'errors'           => $result['errors'],
            'reload'           => ( $result['created_programs'] + $result['created_subjects'] ) > 0,
        ] );
    }
}
