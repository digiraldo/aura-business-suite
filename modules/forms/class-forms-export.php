<?php
/**
 * Export del Módulo de Formularios — Exportación de respuestas
 *
 * Exporta todas las respuestas de un formulario a CSV y Excel (.xlsx)
 * usando PhpSpreadsheet (ya disponible en vendor/).
 *
 * AJAX actions registradas:
 *  - aura_forms_export_csv   — Descarga en CSV
 *  - aura_forms_export_excel — Descarga en .xlsx
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Export {

    /** Tipos de campo sin respuesta (decorativos). */
    private const SKIP_TYPES = [ 'section_title', 'paragraph', 'image', 'downloadable' ];

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_forms_export_csv',   [ __CLASS__, 'ajax_export_csv' ] );
        add_action( 'wp_ajax_aura_forms_export_excel', [ __CLASS__, 'ajax_export_excel' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS (GET — descarga directa)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_csv(): void {
        self::verify_request();

        $form_id = absint( $_GET['form_id'] ?? 0 );
        $form    = self::get_form_or_die( $form_id );
        $fields  = self::get_fields( $form_id );
        $rows    = self::get_submissions( $form_id, $form->form_type );

        $slug     = sanitize_title( $form->slug ?: $form->title );
        $filename = 'formulario-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv';

        if ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // BOM UTF-8 para compatibilidad con Excel
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, self::build_header( $fields, $form->form_type ) );

        foreach ( $rows as $row ) {
            fputcsv( $out, self::parse_row( $row, $fields, $form->form_type ) );
        }

        fclose( $out );
        exit;
    }

    public static function ajax_export_excel(): void {
        self::verify_request();

        $form_id = absint( $_GET['form_id'] ?? 0 );
        $form    = self::get_form_or_die( $form_id );
        $fields  = self::get_fields( $form_id );
        $rows    = self::get_submissions( $form_id, $form->form_type );

        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
            if ( file_exists( $autoload ) ) {
                require_once $autoload;
            } else {
                wp_die( __( 'PhpSpreadsheet no está disponible. Usa la exportación CSV.', 'aura-suite' ) );
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $headers     = self::build_header( $fields, $form->form_type );

        // ── Estilo encabezado: fondo azul, texto blanco, negrita ─
        $header_style = [
            'font'      => [ 'bold' => true, 'color' => [ 'rgb' => 'FFFFFF' ] ],
            'fill'      => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [ 'rgb' => '2563EB' ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ];

        foreach ( $headers as $col_idx => $label ) {
            $cell_ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col_idx + 1 ) . '1';
            $sheet->setCellValue( $cell_ref, $label );
            $sheet->getStyle( $cell_ref )->applyFromArray( $header_style );
        }

        // ── Filas de datos ───────────────────────────────────────
        $row_num = 2;
        foreach ( $rows as $row ) {
            $values = self::parse_row( $row, $fields, $form->form_type );
            foreach ( $values as $col_idx => $value ) {
                $cell_ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col_idx + 1 ) . $row_num;
                $sheet->setCellValue( $cell_ref, $value );
            }
            $row_num++;
        }

        // ── Auto-size en todas las columnas ──────────────────────
        $total_cols = count( $headers );
        for ( $i = 1; $i <= $total_cols; $i++ ) {
            $sheet->getColumnDimensionByColumn( $i )->setAutoSize( true );
        }

        $slug     = sanitize_title( $form->slug ?: $form->title );
        $filename = 'formulario-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.xlsx';

        if ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( 'php://output' );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    private static function verify_request(): void {
        $nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'aura_forms_nonce' ) ) {
            wp_die( __( 'Enlace expirado. Recarga la página e intenta de nuevo.', 'aura-suite' ), '', [ 'response' => 403 ] );
        }
        if ( ! current_user_can( 'aura_forms_export' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para exportar respuestas.', 'aura-suite' ), '', [ 'response' => 403 ] );
        }
    }

    private static function get_form_or_die( int $form_id ): object {
        global $wpdb;
        if ( ! $form_id ) {
            wp_die( __( 'ID de formulario inválido.', 'aura-suite' ), '', [ 'response' => 400 ] );
        }
        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, slug, type AS form_type
               FROM {$wpdb->prefix}aura_forms
              WHERE id = %d AND deleted_at IS NULL",
            $form_id
        ) );
        if ( ! $form ) {
            wp_die( __( 'Formulario no encontrado.', 'aura-suite' ), '', [ 'response' => 404 ] );
        }
        return $form;
    }

    /**
     * Devuelve los campos del formulario excluyendo tipos decorativos,
     * ordenados por sort_order.
     *
     * @return object[]
     */
    private static function get_fields( int $form_id ): array {
        global $wpdb;

        // Construir lista de marcadores de posición para la cláusula NOT IN
        $skip_count   = count( self::SKIP_TYPES );
        $placeholders = implode( ', ', array_fill( 0, $skip_count, '%s' ) );

        $query_args   = array_merge( [ $form_id ], self::SKIP_TYPES );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT field_uid, label, field_type
               FROM {$wpdb->prefix}aura_form_fields
              WHERE form_id = %d
                AND field_type NOT IN ({$placeholders})
              ORDER BY sort_order ASC",
            ...$query_args
        ) );
    }

    /**
     * Carga todas las submissions.
     * Para formularios de tipo 'enrollment' incluye columnas extra
     * mediante JOINs con enrollments, students y wp_users.
     *
     * @return object[]
     */
    private static function get_submissions( int $form_id, string $form_type ): array {
        global $wpdb;

        $t_sub = $wpdb->prefix . 'aura_form_submissions';
        $t_enr = $wpdb->prefix . 'aura_student_enrollments';
        $t_stu = $wpdb->prefix . 'aura_students';
        $t_usr = $wpdb->prefix . 'users';

        if ( $form_type === 'enrollment' ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT s.id,
                        s.submitted_at,
                        s.submitted_name,
                        s.submitted_email,
                        s.ip_address,
                        s.status,
                        s.data_json,
                        s.reviewed_at,
                        u.display_name  AS reviewed_by_name,
                        e.status        AS enrollment_status,
                        st.rejection_reason
                   FROM {$t_sub} s
              LEFT JOIN {$t_enr} e  ON e.id  = s.enrollment_id
              LEFT JOIN {$t_stu} st ON st.id = e.student_id
              LEFT JOIN {$t_usr} u  ON u.ID  = s.reviewed_by
                  WHERE s.form_id = %d
               ORDER BY s.submitted_at ASC",
                $form_id
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id,
                    s.submitted_at,
                    s.submitted_name,
                    s.submitted_email,
                    s.ip_address,
                    s.status,
                    s.data_json
               FROM {$t_sub} s
              WHERE s.form_id = %d
           ORDER BY s.submitted_at ASC",
            $form_id
        ) );
    }

    /**
     * Construye el array de cabeceras de columna.
     *
     * @param object[] $fields
     * @param string   $form_type
     * @return string[]
     */
    private static function build_header( array $fields, string $form_type ): array {
        $header = [
            __( 'ID',            'aura-suite' ),
            __( 'Fecha enviado', 'aura-suite' ),
            __( 'Nombre',        'aura-suite' ),
            __( 'Email',         'aura-suite' ),
            __( 'IP',            'aura-suite' ),
            __( 'Estado',        'aura-suite' ),
        ];

        foreach ( $fields as $field ) {
            if ( $field->field_type === 'birthdate' ) {
                $header[] = $field->label . ' (' . __( 'fecha', 'aura-suite' ) . ')';
                $header[] = $field->label . ' (' . __( 'edad', 'aura-suite' ) . ')';
            } else {
                $header[] = $field->label;
            }
        }

        if ( $form_type === 'enrollment' ) {
            $header[] = __( 'Estado inscripción', 'aura-suite' );
            $header[] = __( 'Revisado por',       'aura-suite' );
            $header[] = __( 'Fecha revisión',     'aura-suite' );
            $header[] = __( 'Motivo de rechazo',  'aura-suite' );
        }

        return $header;
    }

    /**
     * Construye una fila de datos para una submission.
     *
     * @param object   $submission
     * @param object[] $fields
     * @param string   $form_type
     * @return array
     */
    private static function parse_row( object $submission, array $fields, string $form_type ): array {
        $data = [];
        if ( ! empty( $submission->data_json ) ) {
            $decoded = json_decode( $submission->data_json, true );
            $data    = is_array( $decoded ) ? $decoded : [];
        }

        $row = [
            (int) $submission->id,
            $submission->submitted_at    ?? '',
            $submission->submitted_name  ?? '',
            $submission->submitted_email ?? '',
            $submission->ip_address      ?? '',
            $submission->status          ?? '',
        ];

        foreach ( $fields as $field ) {
            $raw = $data[ $field->field_uid ] ?? '';
            if ( $field->field_type === 'birthdate' ) {
                $iso_date = $data[ $field->field_uid . '_iso_date' ] ?? '';
                $row[] = (string) $iso_date;  // fecha ISO
                $row[] = $raw !== '' ? (int) $raw . ' ' . __( 'años', 'aura-suite' ) : '';  // edad
            } else {
                $row[] = self::format_value( $raw, $field->field_type );
            }
        }

        if ( $form_type === 'enrollment' ) {
            $row[] = $submission->enrollment_status ?? '';
            $row[] = $submission->reviewed_by_name  ?? '';
            $row[] = $submission->reviewed_at        ?? '';
            $row[] = $submission->rejection_reason   ?? '';
        }

        return $row;
    }

    /**
     * Formatea un valor de campo para la exportación.
     * Arrays (checkboxes, selects múltiples) se unen con ", ".
     *
     * @param mixed  $value
     * @param string $field_type
     * @return string
     */
    private static function format_value( $value, string $field_type ): string {
        if ( is_array( $value ) ) {
            return implode( ', ', array_map( 'strval', $value ) );
        }

        if ( is_string( $value ) && str_starts_with( $value, '[' ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                return implode( ', ', array_map( 'strval', $decoded ) );
            }
        }

        return (string) $value;
    }
}
