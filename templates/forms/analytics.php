<?php
/**
 * Análisis de Respuestas — Módulo de Formularios (Fase 6)
 *
 * Muestra KPIs de resumen + gráficos por campo usando Chart.js.
 * Los datos se cargan vía AJAX (aura_forms_analytics).
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_forms_analytics' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

// ── Cargar lista de formularios activos para el selector ──────────────
$forms_list = $wpdb->get_results(
    "SELECT id, title, type FROM {$wpdb->prefix}aura_forms
      WHERE deleted_at IS NULL
      ORDER BY title ASC"
);

// El formulario seleccionado (si viene por GET o POST)
$selected_form_id = absint( $_GET['form_id'] ?? 0 );

$nonce = wp_create_nonce( 'aura_forms_nonce' );
?>
<div class="wrap aura-forms-wrap aura-analytics-wrap">

    <h1 class="wp-heading-inline"><?php esc_html_e( 'Análisis de Respuestas', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <!-- ── Selector de formulario ── -->
    <div class="aura-analytics-header card">
        <div class="aura-analytics-selector">
            <label for="analytics-form-select"><strong><?php esc_html_e( 'Selecciona un formulario:', 'aura-suite' ); ?></strong></label>
            <select id="analytics-form-select">
                <option value=""><?php esc_html_e( '— Elige un formulario —', 'aura-suite' ); ?></option>
                <?php foreach ( $forms_list as $f ) : ?>
                    <?php
                    $type_labels = [
                        'generic'    => __( 'Genérico',   'aura-suite' ),
                        'enrollment' => __( 'Inscripción','aura-suite' ),
                        'survey'     => __( 'Encuesta',   'aura-suite' ),
                        'feedback'   => __( 'Feedback',   'aura-suite' ),
                    ];
                    $type_label = $type_labels[ $f->type ] ?? $f->type;
                    ?>
                    <option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $f->id, $selected_form_id ); ?>>
                        <?php echo esc_html( $f->title ); ?> (<?php echo esc_html( $type_label ); ?>)
                    </option>
                <?php endforeach; ?>
                <?php if ( empty( $forms_list ) ) : ?>
                    <option disabled><?php esc_html_e( 'No hay formularios', 'aura-suite' ); ?></option>
                <?php endif; ?>
            </select>
            <button type="button" id="analytics-load-btn" class="button button-primary">
                <?php esc_html_e( 'Ver análisis', 'aura-suite' ); ?>
            </button>
        </div>
    </div>

    <!-- ── Área de contenido (se llena vía AJAX) ── -->
    <div id="analytics-loading" style="display:none;" class="aura-loading">
        <?php esc_html_e( 'Cargando análisis…', 'aura-suite' ); ?>
    </div>

    <div id="analytics-error" class="notice notice-error" style="display:none;"></div>

    <div id="analytics-content" style="display:none;">

        <!-- KPIs de resumen -->
        <div class="aura-analytics-kpis" id="analytics-kpis"></div>

        <!-- Gráfico de actividad (línea de tiempo de 30 días) -->
        <div class="aura-analytics-section card" id="analytics-timeline-wrap" style="display:none;">
            <h2><?php esc_html_e( 'Respuestas en los últimos 30 días', 'aura-suite' ); ?></h2>
            <div class="aura-chart-wrap aura-chart-wrap--wide">
                <canvas id="chart-timeline"></canvas>
            </div>
        </div>

        <!-- Análisis por campo -->
        <div id="analytics-fields"></div>

        <!-- Mensaje si no hay campos -->
        <p id="analytics-no-data" style="display:none;color:#6b7280;">
            <?php esc_html_e( 'Este formulario aún no tiene respuestas registradas.', 'aura-suite' ); ?>
        </p>

    </div><!-- #analytics-content -->

    <!-- Mensaje initial -->
    <p id="analytics-initial" class="aura-muted" style="margin-top:16px;">
        <?php esc_html_e( 'Selecciona un formulario para visualizar el análisis de sus respuestas.', 'aura-suite' ); ?>
    </p>

</div><!-- .wrap -->

<?php /* ─── JavaScript de la página ─── */ ?>
<script type="text/javascript">
/* global jQuery, Chart, auraFormsAdmin */
( function ( $ ) {
    'use strict';

    var NONCE   = <?php echo wp_json_encode( $nonce ); ?>;
    var AJAXURL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

    /** Instancias Chart.js activas — para destruirlas al recargar */
    var activeCharts = {};

    // ── Strings i18n ─────────────────────────────────────────────
    var STR = {
        total_responses : <?php echo wp_json_encode( __( 'Total respuestas', 'aura-suite' ) ); ?>,
        unique_users    : <?php echo wp_json_encode( __( 'Usuarios únicos', 'aura-suite' ) ); ?>,
        first           : <?php echo wp_json_encode( __( 'Primera respuesta', 'aura-suite' ) ); ?>,
        last            : <?php echo wp_json_encode( __( 'Última respuesta', 'aura-suite' ) ); ?>,
        response_rate   : <?php echo wp_json_encode( __( 'Tasa de respuesta', 'aura-suite' ) ); ?>,
        avg             : <?php echo wp_json_encode( __( 'Promedio', 'aura-suite' ) ); ?>,
        median          : <?php echo wp_json_encode( __( 'Mediana', 'aura-suite' ) ); ?>,
        min             : <?php echo wp_json_encode( __( 'Mínimo', 'aura-suite' ) ); ?>,
        max             : <?php echo wp_json_encode( __( 'Máximo', 'aura-suite' ) ); ?>,
        nps             : <?php echo wp_json_encode( __( 'NPS', 'aura-suite' ) ); ?>,
        no_data         : <?php echo wp_json_encode( __( 'Sin datos', 'aura-suite' ) ); ?>,
        responses       : <?php echo wp_json_encode( __( 'Respuestas', 'aura-suite' ) ); ?>,
        top_responses   : <?php echo wp_json_encode( __( 'Respuestas más frecuentes', 'aura-suite' ) ); ?>,
        avg_len         : <?php echo wp_json_encode( __( 'Longitud promedio (caracteres)', 'aura-suite' ) ); ?>,
        unique_count    : <?php echo wp_json_encode( __( 'Respuestas únicas', 'aura-suite' ) ); ?>,
        never           : <?php echo wp_json_encode( __( '—', 'aura-suite' ) ); ?>,
    };

    // Colores de Chart.js
    var PALETTE = [
        '#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6',
        '#06b6d4','#ec4899','#f97316','#84cc16','#14b8a6',
        '#6366f1','#a78bfa','#fb7185','#fbbf24','#4ade80',
    ];

    // ── Initializacion ─────────────────────────────────────────────
    $( '#analytics-load-btn' ).on( 'click', function () {
        var id = $( '#analytics-form-select' ).val();
        if ( ! id ) { alert( '<?php echo esc_js( __( 'Selecciona un formulario.', 'aura-suite' ) ); ?>' ); return; }
        loadAnalytics( id );
    } );

    // Auto-cargar si viene form_id en la URL
    var urlParams = new URLSearchParams( window.location.search );
    var preload   = parseInt( urlParams.get( 'form_id' ) || '0', 10 );
    if ( preload ) {
        $( '#analytics-form-select' ).val( preload );
        loadAnalytics( preload );
    }

    // ── Carga de datos ─────────────────────────────────────────────
    function loadAnalytics( formId ) {
        destroyAllCharts();
        $( '#analytics-initial, #analytics-content, #analytics-error' ).hide();
        $( '#analytics-loading' ).show();

        $.post( AJAXURL, {
            action  : 'aura_forms_analytics',
            nonce   : NONCE,
            form_id : formId,
        } )
        .done( function ( res ) {
            $( '#analytics-loading' ).hide();
            if ( ! res || ! res.success ) {
                var msg = ( res && res.data && res.data.message ) || '<?php echo esc_js( __( 'Error al cargar.', 'aura-suite' ) ); ?>';
                $( '#analytics-error' ).html( '<p>' + escHtml( msg ) + '</p>' ).show();
                return;
            }
            renderAnalytics( res.data );
        } )
        .fail( function () {
            $( '#analytics-loading' ).hide();
            $( '#analytics-error' ).html( '<p><?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?></p>' ).show();
        } );
    }

    // ── Renderizado principal ─────────────────────────────────────
    function renderAnalytics( data ) {
        var summary = data.summary;
        var fields  = data.fields;

        // KPIs
        renderKPIs( summary );

        // Timeline
        if ( summary.by_day_labels && summary.by_day_labels.length > 0 ) {
            $( '#analytics-timeline-wrap' ).show();
            renderTimeline( summary );
        } else {
            $( '#analytics-timeline-wrap' ).hide();
        }

        // Campos
        var $fieldsWrap = $( '#analytics-fields' ).empty();
        $( '#analytics-no-data' ).hide();

        if ( ! fields || fields.length === 0 || summary.total === 0 ) {
            $( '#analytics-no-data' ).show();
        } else {
            $.each( fields, function ( _, f ) {
                if ( f.chart_type === 'none' || ! f.data ) return; // skip sin datos
                $fieldsWrap.append( buildFieldCard( f ) );
                renderFieldChart( f );
            } );
        }

        $( '#analytics-content' ).show();
    }

    // ── KPIs ──────────────────────────────────────────────────────
    function renderKPIs( summary ) {
        var first = summary.first_submission ? summary.first_submission.substring( 0, 10 ) : STR.never;
        var last  = summary.last_submission  ? summary.last_submission.substring( 0, 10 )  : STR.never;

        $( '#analytics-kpis' ).html(
            kpiCard( summary.total,        STR.total_responses, 'dashicons-forms', '#2563eb' ) +
            kpiCard( summary.unique_users, STR.unique_users,    'dashicons-groups', '#10b981' ) +
            kpiCard( first,                STR.first,           'dashicons-calendar-alt', '#f59e0b' ) +
            kpiCard( last,                 STR.last,            'dashicons-calendar', '#8b5cf6' )
        );
    }

    function kpiCard( value, label, icon, color ) {
        return '<div class="aura-kpi-card">'
            + '<span class="dashicons ' + escHtml( icon ) + '" style="color:' + escHtml( color ) + '"></span>'
            + '<div class="aura-kpi-value">' + escHtml( String( value ) ) + '</div>'
            + '<div class="aura-kpi-label">' + escHtml( label ) + '</div>'
            + '</div>';
    }

    // ── Timeline ──────────────────────────────────────────────────
    function renderTimeline( summary ) {
        var ctx = document.getElementById( 'chart-timeline' );
        if ( ! ctx ) return;
        if ( activeCharts['timeline'] ) activeCharts['timeline'].destroy();

        activeCharts['timeline'] = new Chart( ctx, {
            type: 'line',
            data: {
                labels: summary.by_day_labels,
                datasets: [{
                    label: STR.responses,
                    data:  summary.by_day_data,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,.15)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                },
            },
        } );
    }

    // ── Tarjeta por campo ─────────────────────────────────────────
    function buildFieldCard( f ) {
        var canvasId = 'chart-field-' + escHtml( f.field_uid );
        var statsHtml = buildFieldStats( f );

        // Para tipo text_list no se renderiza canvas
        var chartHtml = ( f.chart_type === 'text_list' )
            ? buildTextList( f.data )
            : '<div class="aura-chart-wrap"><canvas id="' + canvasId + '"></canvas></div>';

        return '<div class="aura-analytics-section card aura-field-card" data-uid="' + escHtml( f.field_uid ) + '">'
            + '<div class="aura-field-card-header">'
            + '<h3>' + escHtml( f.label ) + '</h3>'
            + '<span class="aura-badge">' + escHtml( f.type ) + '</span>'
            + '<span class="aura-badge aura-badge-secondary">' + escHtml( f.n ) + ' ' + STR.responses
            + ' (' + escHtml( f.response_rate ) + '%)</span>'
            + '</div>'
            + statsHtml
            + chartHtml
            + '</div>';
    }

    function buildFieldStats( f ) {
        var d = f.data;
        if ( ! d ) return '';

        var items = [];
        if ( d.avg     !== null && d.avg     !== undefined ) items.push( [ STR.avg,    d.avg ] );
        if ( d.median  !== null && d.median  !== undefined ) items.push( [ STR.median, d.median ] );
        if ( d.min     !== null && d.min     !== undefined ) items.push( [ STR.min,    d.min ] );
        if ( d.max     !== null && d.max     !== undefined ) items.push( [ STR.max,    d.max ] );
        if ( d.nps     !== null && d.nps     !== undefined ) items.push( [ STR.nps,    d.nps ] );
        if ( d.avg_length !== undefined )                    items.push( [ STR.avg_len, d.avg_length ] );
        if ( d.total_unique !== undefined )                  items.push( [ STR.unique_count, d.total_unique ] );

        if ( items.length === 0 ) return '';

        var html = '<div class="aura-field-stats">';
        $.each( items, function ( _, pair ) {
            html += '<div class="aura-stat-pill"><strong>' + escHtml( String( pair[1] ) )
                 + '</strong><small>' + escHtml( pair[0] ) + '</small></div>';
        } );
        html += '</div>';
        return html;
    }

    function buildTextList( data ) {
        if ( ! data || ! data.top_responses ) return '';
        var html = '<div class="aura-text-responses">';

        var entries = Object.entries( data.top_responses );
        if ( entries.length === 0 ) {
            return '<p class="aura-muted">' + escHtml( STR.no_data ) + '</p>';
        }

        var maxCount = entries[0][1];
        entries.forEach( function ( pair ) {
            var pct = maxCount > 0 ? Math.round( pair[1] / maxCount * 100 ) : 0;
            html += '<div class="aura-text-row">'
                + '<span class="aura-text-row__text">' + escHtml( pair[0] ) + '</span>'
                + '<div class="aura-text-row__bar-wrap"><div class="aura-text-row__bar" style="width:' + pct + '%"></div></div>'
                + '<span class="aura-text-row__count">' + escHtml( String( pair[1] ) ) + '</span>'
                + '</div>';
        } );
        html += '</div>';
        return html;
    }

    // ── Renderizar Chart.js por tipo ──────────────────────────────
    function renderFieldChart( f ) {
        var ct = f.chart_type;
        if ( ct === 'text_list' ) return; // Ya se renderizó como lista

        var canvasId = 'chart-field-' + f.field_uid;
        var ctx = document.getElementById( canvasId );
        if ( ! ctx ) return;

        var d      = f.data;
        var config = null;

        if ( ct === 'doughnut' ) {
            config = {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.counts,
                        backgroundColor: PALETTE.slice( 0, d.counts.length ),
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: {
                            callbacks: {
                                label: function ( ctx ) {
                                    var pct = d.percents[ ctx.dataIndex ];
                                    return ' ' + ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                                },
                            },
                        },
                    },
                },
            };
        } else if ( ct === 'bar_horizontal' ) {
            config = {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: STR.responses,
                        data:  d.counts,
                        backgroundColor: PALETTE.slice( 0, d.counts.length ),
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            };
            // Necesita altura fija para horizontal
            $( ctx ).closest( '.aura-chart-wrap' ).css( 'height', Math.max( 200, d.labels.length * 32 ) + 'px' );
        } else if ( ct === 'bar_scale' ) {
            config = {
                type: 'bar',
                data: {
                    labels: d.labels.map( String ),
                    datasets: [{
                        label: STR.responses,
                        data:  d.counts,
                        backgroundColor: d.labels.map( function ( v ) {
                            if ( d.max >= 10 ) {
                                return v <= 6 ? '#ef4444' : v <= 8 ? '#f59e0b' : '#10b981';
                            }
                            return PALETTE[ ( v - 1 ) % PALETTE.length ];
                        } ),
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            };
        } else if ( ct === 'bar_number' || ct === 'bar_timeline' ) {
            config = {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: STR.responses,
                        data:  d.counts,
                        backgroundColor: 'rgba(59,130,246,.7)',
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            };
        }

        if ( config ) {
            activeCharts[ f.field_uid ] = new Chart( ctx, config );
        }
    }

    // ── Utilidades ────────────────────────────────────────────────
    function destroyAllCharts() {
        Object.values( activeCharts ).forEach( function ( c ) { try { c.destroy(); } catch(e){} } );
        activeCharts = {};
    }

    function escHtml( str ) {
        return $( '<span>' ).text( String( str || '' ) ).html();
    }

} )( jQuery );
</script>

<?php /* ─── Estilos inline ─── */ ?>
<style>
/* ── Layout general ─────────────────────────────────────────── */
.aura-analytics-header.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 14px 18px;
    margin: 12px 0 18px;
    display: flex;
    align-items: center;
}
.aura-analytics-selector {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.aura-analytics-selector label { margin-right: 4px; }
.aura-analytics-selector select { min-width: 280px; }

/* ── KPIs ───────────────────────────────────────────────────── */
.aura-analytics-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.aura-kpi-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 16px;
    text-align: center;
}
.aura-kpi-card .dashicons {
    font-size: 26px;
    width: 26px;
    height: 26px;
    display: block;
    margin: 0 auto 8px;
}
.aura-kpi-value {
    font-size: 22px;
    font-weight: 700;
    color: #111827;
    line-height: 1.1;
}
.aura-kpi-label {
    font-size: 11px;
    color: #6b7280;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

/* ── Secciones de campo ─────────────────────────────────────── */
.aura-analytics-section.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 18px;
}
.aura-analytics-section h2 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 14px;
    color: #1f2937;
}
.aura-field-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.aura-field-card-header h3 {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
    flex: 1;
}
.aura-badge {
    background: #e5e7eb;
    color: #374151;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.aura-badge-secondary { background: #f0f3ff; color: #3730a3; }

/* ── Estadísticas ───────────────────────────────────────────── */
.aura-field-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.aura-stat-pill {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 6px 12px;
    text-align: center;
    min-width: 80px;
}
.aura-stat-pill strong {
    display: block;
    font-size: 18px;
    color: #111827;
    line-height: 1.1;
}
.aura-stat-pill small {
    font-size: 10px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .04em;
}

/* ── Gráficos ───────────────────────────────────────────────── */
.aura-chart-wrap {
    position: relative;
    max-width: 480px;
    margin: 0 auto;
}
.aura-chart-wrap--wide {
    max-width: 100%;
}
.aura-chart-wrap canvas {
    width: 100% !important;
}

/* ── Respuestas de texto ─────────────────────────────────────── */
.aura-text-responses {
    max-width: 600px;
}
.aura-text-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    font-size: 13px;
}
.aura-text-row__text {
    flex: 0 0 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #374151;
}
.aura-text-row__bar-wrap {
    flex: 1;
    background: #f0f3ff;
    border-radius: 4px;
    height: 10px;
    overflow: hidden;
}
.aura-text-row__bar {
    background: #3b82f6;
    height: 100%;
    border-radius: 4px;
    transition: width .3s ease;
}
.aura-text-row__count {
    flex: 0 0 32px;
    text-align: right;
    color: #6b7280;
    font-weight: 600;
    font-size: 12px;
}
.aura-muted { color: #6b7280; font-size: 13px; }
</style>
