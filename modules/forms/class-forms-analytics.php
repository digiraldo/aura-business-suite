<?php
/**
 * Analytics del Módulo de Formularios — Análisis de respuestas
 *
 * Agrega y calcula estadísticas por campo usando los datos de
 * aura_form_submissions.data_json para renderizar gráficos con Chart.js.
 *
 * AJAX actions registradas:
 *  - aura_forms_analytics  — Devuelve datos agregados por formulario
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Analytics {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_forms_analytics', [ __CLASS__, 'ajax_get_analytics' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER DATOS DE ANALÍTICA
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve datos agregados de un formulario listos para Chart.js.
     *
     * Request: nonce, form_id
     * Response: {
     *   summary: { total, unique_users, first_submission, last_submission, date_range },
     *   fields:  [{ id, field_uid, label, type, chart_type, data: {...} }, ...]
     * }
     */
    public static function ajax_get_analytics(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_analytics' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permiso.', 'aura-suite' ) ], 403 );
        }

        $form_id = absint( $_POST['form_id'] ?? 0 );
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'form_id requerido.', 'aura-suite' ) ], 400 );
        }

        global $wpdb;

        // ── 1. Cargar formulario ──────────────────────────────────
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, type FROM {$wpdb->prefix}aura_forms WHERE id = %d AND deleted_at IS NULL",
                $form_id
            )
        );

        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Formulario no encontrado.', 'aura-suite' ) ], 404 );
        }

        // ── 2. Cargar campos (excluir decorativos sin respuesta) ──
        $fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, field_uid, label, field_type, options_json, min_value, max_value
                   FROM {$wpdb->prefix}aura_form_fields
                  WHERE form_id = %d
                    AND field_type NOT IN ('section_title','paragraph','image','downloadable')
                  ORDER BY sort_order ASC",
                $form_id
            )
        );

        if ( empty( $fields ) ) {
            wp_send_json_success( [
                'summary' => self::build_summary( $form_id, $wpdb ),
                'fields'  => [],
            ] );
        }

        // ── 3. Cargar todas las submissions del formulario ────────
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, data_json, wp_user_id, submitted_at
                   FROM {$wpdb->prefix}aura_form_submissions
                  WHERE form_id = %d
                  ORDER BY submitted_at ASC",
                $form_id
            )
        );

        // ── 4. Construir índice field_uid → campo ─────────────────
        $field_map = [];
        foreach ( $fields as $f ) {
            $field_map[ $f->field_uid ] = $f;
        }

        // ── 5. Inicializar acumuladores por campo ─────────────────
        // Estructura: $accum[field_uid] = [ 'values' => [...], 'raw' => [...] ]
        $accum = [];
        foreach ( $fields as $f ) {
            $accum[ $f->field_uid ] = [ 'values' => [], 'count' => 0 ];
        }

        // ── 6. Acumular respuestas ────────────────────────────────
        foreach ( $submissions as $sub ) {
            if ( empty( $sub->data_json ) ) continue;

            $data = json_decode( $sub->data_json, true );
            if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) continue;

            foreach ( $data as $uid => $value ) {
                if ( ! isset( $accum[ $uid ] ) ) continue;

                $accum[ $uid ]['count']++;

                // Normalizar arrays (checkbox / select múltiple)
                if ( is_string( $value ) ) {
                    // Intenta parsear JSON si parece array
                    $decoded = json_decode( $value, true );
                    if ( is_array( $decoded ) ) {
                        $value = $decoded;
                    }
                }

                if ( is_array( $value ) ) {
                    foreach ( $value as $v ) {
                        $accum[ $uid ]['values'][] = (string) $v;
                    }
                } else {
                    $accum[ $uid ]['values'][] = (string) $value;
                }
            }
        }

        // ── 7. Calcular estadísticas por campo ────────────────────
        $field_analytics = [];
        foreach ( $fields as $f ) {
            $uid     = $f->field_uid;
            $values  = $accum[ $uid ]['values'] ?? [];
            $n       = count( $values );

            $stat = self::compute_field_stats( $f, $values, $n, count( $submissions ) );
            if ( $stat !== null ) {
                $field_analytics[] = $stat;
            }
        }

        wp_send_json_success( [
            'form'    => [ 'id' => (int) $form->id, 'title' => $form->title, 'type' => $form->type ],
            'summary' => self::build_summary( $form_id, $wpdb, $submissions ),
            'fields'  => $field_analytics,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RESUMEN GENERAL
    // ─────────────────────────────────────────────────────────────

    /**
     * @param int       $form_id
     * @param \wpdb     $wpdb
     * @param array     $submissions Pre-cargadas (para no requerir dos queries)
     */
    private static function build_summary( int $form_id, $wpdb, array $submissions = [] ): array {
        if ( empty( $submissions ) ) {
            $submissions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, wp_user_id, submitted_at FROM {$wpdb->prefix}aura_form_submissions WHERE form_id = %d",
                    $form_id
                )
            );
        }

        $total     = count( $submissions );
        $unique    = count( array_unique( array_filter( array_column( (array) $submissions, 'wp_user_id' ) ) ) );
        $dates     = array_filter( array_column( (array) $submissions, 'submitted_at' ) );
        $first     = ! empty( $dates ) ? min( $dates ) : null;
        $last      = ! empty( $dates ) ? max( $dates ) : null;

        // Respuestas por día (últimos 30 días)
        $cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
        $by_day    = [];
        foreach ( $submissions as $s ) {
            if ( $s->submitted_at >= $cutoff ) {
                $day = substr( $s->submitted_at, 0, 10 );
                $by_day[ $day ] = ( $by_day[ $day ] ?? 0 ) + 1;
            }
        }
        ksort( $by_day );

        return [
            'total'            => $total,
            'unique_users'     => $unique,
            'first_submission' => $first,
            'last_submission'  => $last,
            'by_day_labels'    => array_keys( $by_day ),
            'by_day_data'      => array_values( $by_day ),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // ESTADÍSTICAS POR TIPO DE CAMPO
    // ─────────────────────────────────────────────────────────────

    /**
     * Calcula estadísticas y el tipo de gráfico adecuado para un campo.
     *
     * @param object $field   Fila de aura_form_fields.
     * @param array  $values  Todos los valores recibidos para ese campo (string[]).
     * @param int    $n       Total de respuestas del campo.
     * @param int    $total   Total de submissions del formulario.
     * @return array|null     Null si no hay datos útiles.
     */
    private static function compute_field_stats( object $field, array $values, int $n, int $total ): ?array {
        $base = [
            'field_uid'   => $field->field_uid,
            'field_id'    => (int) $field->id,
            'label'       => $field->label,
            'type'        => $field->field_type,
            'n'           => $n,
            'total'       => $total,
            'response_rate' => $total > 0 ? round( $n / $total * 100, 1 ) : 0,
        ];

        if ( $n === 0 ) {
            return array_merge( $base, [ 'chart_type' => 'none', 'data' => null ] );
        }

        switch ( $field->field_type ) {

            // ── Radio / Select (única) — dona o barras ────────────
            case 'radio':
            case 'select': {
                $counts = array_count_values( $values );
                arsort( $counts );
                return array_merge( $base, [
                    'chart_type' => 'doughnut',
                    'data'       => [
                        'labels'   => array_keys( $counts ),
                        'counts'   => array_values( $counts ),
                        'percents' => array_map( fn( $c ) => round( $c / $n * 100, 1 ), array_values( $counts ) ),
                    ],
                ] );
            }

            case 'terms':
            case 'accept_only_terms': {
                $counts = array_count_values( $values );
                arsort( $counts );
                $i18n = [
                    'agree'    => 'De acuerdo',
                    'disagree' => 'En desacuerdo',
                    'accepted' => 'Aceptado',
                    ''         => 'No aceptado',
                ];
                $translated = [];
                foreach ( $counts as $k => $v ) {
                    $key = $i18n[ (string) $k ] ?? $k;
                    $translated[ $key ] = ( $translated[ $key ] ?? 0 ) + $v;
                }
                return array_merge( $base, [
                    'chart_type' => 'doughnut',
                    'data'       => [
                        'labels'   => array_keys( $translated ),
                        'counts'   => array_values( $translated ),
                        'percents' => array_map( fn( $c ) => round( $c / $n * 100, 1 ), array_values( $translated ) ),
                    ],
                ] );
            }

            // ── Checkbox / Select múltiple — barras horizontales ──
            case 'checkbox': {
                $counts = array_count_values( $values );
                arsort( $counts );
                return array_merge( $base, [
                    'chart_type' => 'bar_horizontal',
                    'data'       => [
                        'labels'  => array_keys( $counts ),
                        'counts'  => array_values( $counts ),
                        'percents' => array_map( fn( $c ) => round( $c / $n * 100, 1 ), array_values( $counts ) ),
                    ],
                ] );
            }

            // ── Escala (NPS / Likert) — barras + promedio + NPS ──
            case 'scale': {
                $numeric  = array_filter( $values, fn( $v ) => is_numeric( $v ) );
                $float_v  = array_map( 'floatval', $numeric );
                $max_val  = (int) ( $field->max_value ?: 10 );

                $counts = [];
                for ( $i = 1; $i <= $max_val; $i++ ) {
                    $counts[ $i ] = 0;
                }
                foreach ( $float_v as $v ) {
                    $key = (int) round( $v );
                    if ( isset( $counts[ $key ] ) ) $counts[ $key ]++;
                }

                $avg = ! empty( $float_v ) ? round( array_sum( $float_v ) / count( $float_v ), 2 ) : null;

                // NPS solo cuando max=10: promotores(9-10), pasivos(7-8), detractores(0-6)
                $nps = null;
                if ( $max_val === 10 && count( $float_v ) > 0 ) {
                    $promoters  = count( array_filter( $float_v, fn( $v ) => $v >= 9 ) );
                    $detractors = count( array_filter( $float_v, fn( $v ) => $v <= 6 ) );
                    $total_resp = count( $float_v );
                    $nps = round( ( $promoters - $detractors ) / $total_resp * 100 );
                }

                return array_merge( $base, [
                    'chart_type' => 'bar_scale',
                    'data'       => [
                        'labels'  => array_keys( $counts ),
                        'counts'  => array_values( $counts ),
                        'avg'     => $avg,
                        'max'     => $max_val,
                        'nps'     => $nps,
                    ],
                ] );
            }

            // ── Número — promedio, min, max, distribución ─────────
            case 'number':
            case 'birthdate': {
                $numeric = array_filter( $values, fn( $v ) => is_numeric( $v ) );
                if ( empty( $numeric ) ) {
                    return array_merge( $base, [ 'chart_type' => 'none', 'data' => null ] );
                }
                $float_v = array_map( 'floatval', $numeric );
                sort( $float_v );

                // Histograma: distribuir en hasta 8 rangos
                $min_v  = min( $float_v );
                $max_v  = max( $float_v );
                $bins   = self::build_histogram( $float_v, $min_v, $max_v, 8 );

                return array_merge( $base, [
                    'chart_type' => 'bar_number',
                    'data'       => [
                        'avg'     => round( array_sum( $float_v ) / count( $float_v ), 2 ),
                        'min'     => $min_v,
                        'max'     => $max_v,
                        'median'  => self::median( $float_v ),
                        'labels'  => $bins['labels'],
                        'counts'  => $bins['counts'],
                    ],
                ] );
            }

            // ── Fecha — distribución por mes ──────────────────────
            case 'date':
            case 'time': {
                // Agrupar por mes (YYYY-MM) para date, por hora para time
                $groups = [];
                foreach ( $values as $v ) {
                    if ( empty( $v ) ) continue;
                    if ( $field->field_type === 'date' ) {
                        $key = substr( $v, 0, 7 ); // YYYY-MM
                    } else {
                        $key = substr( $v, 0, 2 ) . ':00'; // HH:00
                    }
                    $groups[ $key ] = ( $groups[ $key ] ?? 0 ) + 1;
                }
                ksort( $groups );

                return array_merge( $base, [
                    'chart_type' => 'bar_timeline',
                    'data'       => [
                        'labels' => array_keys( $groups ),
                        'counts' => array_values( $groups ),
                    ],
                ] );
            }

            // ── Texto libre — top N respuestas más frecuentes ─────
            case 'text':
            case 'email':
            case 'tel':
            case 'textarea': {
                // Top 20 respuestas más frecuentes (no se genera word-cloud en JS, solo tabla)
                $non_empty = array_filter( $values, fn( $v ) => trim( $v ) !== '' );
                $counts    = array_count_values( array_map( 'mb_strtolower', $non_empty ) );
                arsort( $counts );
                $top = array_slice( $counts, 0, 20, true );

                // Promedio de longitud de respuesta
                $lengths = array_map( 'mb_strlen', $non_empty );
                $avg_len = ! empty( $lengths ) ? round( array_sum( $lengths ) / count( $lengths ) ) : 0;

                return array_merge( $base, [
                    'chart_type' => 'text_list',
                    'data'       => [
                        'top_responses' => $top,
                        'avg_length'    => $avg_len,
                        'total_unique'  => count( $counts ),
                    ],
                ] );
            }

            // ── Hidden / Section / etc. — sin gráfico ────────────
            default:
                return null;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS ESTADÍSTICOS
    // ─────────────────────────────────────────────────────────────

    /** Calcula la mediana de un array float ordenado. */
    private static function median( array $sorted ): float {
        $c = count( $sorted );
        if ( $c === 0 ) return 0.0;
        $mid = (int) floor( $c / 2 );
        return ( $c % 2 === 0 )
            ? ( $sorted[ $mid - 1 ] + $sorted[ $mid ] ) / 2.0
            : (float) $sorted[ $mid ];
    }

    /**
     * Construye un histograma de hasta $bins rangos iguales.
     *
     * @param float[] $sorted_values Array ordenado ascendente.
     * @param float   $min
     * @param float   $max
     * @param int     $bins
     * @return array{ labels: string[], counts: int[] }
     */
    private static function build_histogram( array $sorted_values, float $min, float $max, int $bins ): array {
        if ( $min === $max ) {
            return [ 'labels' => [ (string) $min ], 'counts' => [ count( $sorted_values ) ] ];
        }

        $step   = ( $max - $min ) / $bins;
        $labels = [];
        $counts = array_fill( 0, $bins, 0 );

        for ( $i = 0; $i < $bins; $i++ ) {
            $from     = $min + $i * $step;
            $to       = $from + $step;
            $labels[] = round( $from, 1 ) . '–' . round( $to, 1 );
        }

        foreach ( $sorted_values as $v ) {
            $idx = (int) floor( ( $v - $min ) / $step );
            if ( $idx >= $bins ) $idx = $bins - 1;
            $counts[ $idx ]++;
        }

        return [ 'labels' => $labels, 'counts' => $counts ];
    }
}
