<?php
/**
 * Template: Reportes de Estudiantes (Fase 10)
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$ta = $wpdb->prefix . 'aura_areas';
$tc = $wpdb->prefix . 'aura_student_courses';

// Áreas activas para el filtro
$areas = [];
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ta}'" ) === $ta ) { // phpcs:ignore
    $areas = $wpdb->get_results( // phpcs:ignore
        "SELECT id, name FROM {$ta} WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
    );
}

// Todos los cursos activos (el JS los filtrará por area_id)
$courses = [];
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tc}'" ) === $tc ) { // phpcs:ignore
    $courses = $wpdb->get_results( // phpcs:ignore
        "SELECT id, name, area_id FROM {$tc} WHERE status = 'active' ORDER BY name ASC"
    );
}

// Reportes que no admiten PDF
$no_pdf_types = [ 'income_by_area', 'income_projection', 'scholarships' ];
?>
<div class="wrap aura-app-container" id="aura-students-reports-app">

    <!-- ══════════════════ CABECERA ══════════════════ -->
    <?php
    Aura_UI::render_page_header([
        'title'    => __( 'Reportes de Estudiantes', 'aura-suite' ),
        'subtitle' => __( 'Genera, visualiza y exporta reportes detallados y proyecciones del módulo de estudiantes.', 'aura-suite' ),
        'icon'     => 'dashicons-chart-bar',
        'badge'    => __( 'Fase 10', 'aura-suite' ),
    ]);
    ?>

    <div class="aura-reports-layout" style="display:grid; grid-template-columns: 320px 1fr; gap: var(--aura-space-6, 24px); align-items: start; margin-top: var(--aura-space-6, 24px);">

        <!-- ══════════════════ PANEL IZQUIERDO: Configuración ══════════════════ -->
        <aside class="aura-reports-sidebar">

            <!-- Formulario de configuración -->
            <div class="aura-card" style="padding: var(--aura-space-5, 20px);">
                <div class="aura-card-header" style="margin-bottom: var(--aura-space-4, 16px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                    <h3 class="aura-card-title" style="margin: 0; font-size: var(--aura-text-base, 1rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95); display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-filter" style="color: var(--aura-primary-600, #7c3aed);"></span>
                        <?php esc_html_e( 'Configurar Reporte', 'aura-suite' ); ?>
                    </h3>
                </div>

                <form id="aura-students-report-form" autocomplete="off" style="display: flex; flex-direction: column; gap: var(--aura-space-4, 16px);">

                    <!-- Tipo de reporte -->
                    <div class="aura-form-group" style="margin:0;">
                        <label for="rep-type" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                            <?php esc_html_e( 'Tipo de reporte', 'aura-suite' ); ?> <span style="color:var(--aura-danger-500, #ef4444);">*</span>
                        </label>
                        <select id="rep-type" name="report_type" class="aura-form-control aura-select" style="width: 100%;" required>
                            <option value=""><?php esc_html_e( '— Seleccionar —', 'aura-suite' ); ?></option>
                            <option value="students_list">👨‍🎓 <?php esc_html_e( 'Lista completa de estudiantes', 'aura-suite' ); ?></option>
                            <option value="payments_by_course">💳 <?php esc_html_e( 'Estado de pagos por curso', 'aura-suite' ); ?></option>
                            <option value="enrolled_by_area">🏫 <?php esc_html_e( 'Inscritos por área/programa', 'aura-suite' ); ?></option>
                            <option value="income_by_area">💰 <?php esc_html_e( 'Ingresos por área/programa', 'aura-suite' ); ?></option>
                            <option value="overdue">⚠️ <?php esc_html_e( 'Morosos (cuotas vencidas)', 'aura-suite' ); ?></option>
                            <option value="income_projection">📈 <?php esc_html_e( 'Proyección de ingresos por mes', 'aura-suite' ); ?></option>
                            <option value="scholarships">🎓 <?php esc_html_e( 'Becas otorgadas', 'aura-suite' ); ?></option>
                            <option value="graduates">🏆 <?php esc_html_e( 'Graduados por período', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Período -->
                    <div class="aura-form-group" style="margin:0;">
                        <label style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                            <?php esc_html_e( 'Período', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-date-presets" style="display:flex; gap:6px; margin-bottom: 8px;">
                            <button type="button" class="aura-btn aura-btn-sm aura-btn-secondary aura-preset-btn" data-preset="month" style="flex:1; padding: 4px 8px; font-size: 11px;">
                                <?php esc_html_e( 'Este mes', 'aura-suite' ); ?>
                            </button>
                            <button type="button" class="aura-btn aura-btn-sm aura-btn-secondary aura-preset-btn" data-preset="quarter" style="flex:1; padding: 4px 8px; font-size: 11px;">
                                <?php esc_html_e( 'Trimestre', 'aura-suite' ); ?>
                            </button>
                            <button type="button" class="aura-btn aura-btn-sm aura-btn-secondary aura-preset-btn" data-preset="year" style="flex:1; padding: 4px 8px; font-size: 11px;">
                                <?php esc_html_e( 'Este año', 'aura-suite' ); ?>
                            </button>
                        </div>
                        <div class="aura-date-range" style="display:flex; align-items:center; gap:8px;">
                            <input type="date" id="rep-start" name="start" class="aura-form-control aura-input" style="flex:1; font-size: var(--aura-text-sm, 0.875rem);"
                                   value="<?php echo esc_attr( date( 'Y-01-01' ) ); ?>">
                            <span style="color:var(--aura-text-muted, #94a3b8); font-weight:bold;">—</span>
                            <input type="date" id="rep-end" name="end" class="aura-form-control aura-input" style="flex:1; font-size: var(--aura-text-sm, 0.875rem);"
                                   value="<?php echo esc_attr( date( 'Y-12-31' ) ); ?>">
                        </div>
                    </div>

                    <!-- Área / Programa -->
                    <?php if ( ! empty( $areas ) ) : ?>
                    <div class="aura-form-group" style="margin:0;">
                        <label for="rep-area" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                            <?php esc_html_e( 'Área / Programa', 'aura-suite' ); ?>
                        </label>
                        <select id="rep-area" name="area_id" class="aura-form-control aura-select" style="width: 100%;">
                            <option value="0"><?php esc_html_e( '— Todas las áreas —', 'aura-suite' ); ?></option>
                            <?php foreach ( $areas as $area ) : ?>
                                <option value="<?php echo esc_attr( $area->id ); ?>">
                                    <?php echo esc_html( $area->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Curso -->
                    <?php if ( ! empty( $courses ) ) : ?>
                    <div class="aura-form-group" style="margin:0;">
                        <label for="rep-course" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                            <?php esc_html_e( 'Curso', 'aura-suite' ); ?>
                        </label>
                        <select id="rep-course" name="course_id" class="aura-form-control aura-select" style="width: 100%;">
                            <option value="0"><?php esc_html_e( '— Todos los cursos —', 'aura-suite' ); ?></option>
                            <?php foreach ( $courses as $course ) : ?>
                                <option value="<?php echo esc_attr( $course->id ); ?>"
                                        data-area="<?php echo esc_attr( $course->area_id ); ?>">
                                    <?php echo esc_html( $course->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Tipo de perfil -->
                    <div class="aura-form-group" style="margin:0;">
                        <label for="rep-profile-type" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                            <?php esc_html_e( 'Tipo de perfil', 'aura-suite' ); ?>
                        </label>
                        <select id="rep-profile-type" name="profile_type" class="aura-form-control aura-select" style="width: 100%;">
                            <option value=""><?php esc_html_e( '— Todos —', 'aura-suite' ); ?></option>
                            <option value="student"><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></option>
                            <option value="worker"><?php esc_html_e( 'Trabajador', 'aura-suite' ); ?></option>
                            <option value="external"><?php esc_html_e( 'Externo', 'aura-suite' ); ?></option>
                            <option value="teacher"><?php esc_html_e( 'Docente', 'aura-suite' ); ?></option>
                            <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Estado del estudiante -->
                    <div class="aura-form-group" style="margin:0;">
                        <label for="rep-status" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                            <?php esc_html_e( 'Estado del estudiante', 'aura-suite' ); ?>
                        </label>
                        <select id="rep-status" name="status" class="aura-form-control aura-select" style="width: 100%;">
                            <option value=""><?php esc_html_e( '— Todos —', 'aura-suite' ); ?></option>
                            <option value="active"><?php esc_html_e( 'Activo', 'aura-suite' ); ?></option>
                            <option value="inactive"><?php esc_html_e( 'Inactivo', 'aura-suite' ); ?></option>
                            <option value="graduated"><?php esc_html_e( 'Graduado', 'aura-suite' ); ?></option>
                            <option value="suspended"><?php esc_html_e( 'Suspendido', 'aura-suite' ); ?></option>
                            <option value="withdrawn"><?php esc_html_e( 'Retirado', 'aura-suite' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendiente', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Botón generar -->
                    <div class="aura-form-group" style="margin-top: 8px;">
                        <button type="submit" id="btn-st-generate"
                                class="aura-btn aura-btn-primary"
                                style="width:100%; justify-content:center; padding: 10px 16px;" disabled>
                            <span class="dashicons dashicons-visibility" style="margin-top:0;"></span>
                            <?php esc_html_e( 'Generar Reporte', 'aura-suite' ); ?>
                        </button>
                    </div>

                </form>
            </div><!-- /.aura-card (form) -->

            <!-- Exportación -->
            <div class="aura-card" id="st-export-card" style="display:none; margin-top: var(--aura-space-4, 16px); padding: var(--aura-space-5, 20px);">
                <div class="aura-card-header" style="margin-bottom: var(--aura-space-3, 12px); padding-bottom: var(--aura-space-2, 8px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                    <h3 class="aura-card-title" style="margin: 0; font-size: var(--aura-text-base, 1rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95); display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-download" style="color: var(--aura-primary-600, #7c3aed);"></span>
                        <?php esc_html_e( 'Exportar Resultados', 'aura-suite' ); ?>
                    </h3>
                </div>
                <div class="aura-export-buttons" style="display:flex; flex-direction:column; gap:8px;">
                    <button type="button" id="btn-st-excel"
                            class="aura-btn aura-btn-success"
                            style="width: 100%; justify-content:center;">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php esc_html_e( 'Descargar Excel (.xlsx)', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="btn-st-pdf"
                            class="aura-btn aura-btn-primary"
                            style="width: 100%; justify-content:center;">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php esc_html_e( 'Descargar PDF', 'aura-suite' ); ?>
                    </button>
                </div>
                <small id="st-pdf-note" style="display:none; color:var(--aura-text-muted, #94a3b8); margin-top:8px; font-size: 12px; display:block;">
                    <?php esc_html_e( 'Este tipo de reporte no admite exportación PDF.', 'aura-suite' ); ?>
                </small>
            </div><!-- /.aura-card (export) -->

        </aside><!-- /.aura-reports-sidebar -->

        <!-- ══════════════════ PANEL DERECHO: Resultados ══════════════════ -->
        <main class="aura-reports-main" id="st-report-output">

            <div class="aura-card" style="padding: var(--aura-space-6, 24px); min-height: 360px;">
                <!-- Estado vacío inicial -->
                <div class="aura-report-empty" id="st-report-empty" style="text-align: center; padding: 64px 24px; border: 2px dashed var(--aura-border, #e2e8f0); border-radius: var(--aura-radius-xl, 16px); background: var(--aura-surface-alt, #f8fafc);">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--aura-primary-50, #f5f3ff); color: var(--aura-primary-600, #7c3aed); display: inline-flex; align-items: center; justify-content: center; margin-bottom: var(--aura-space-4, 16px);">
                        <span class="dashicons dashicons-chart-bar" style="font-size:32px; width:32px; height:32px;"></span>
                    </div>
                    <h4 style="margin: 0 0 8px; color: var(--aura-text-primary, #1e293b); font-size: 16px; font-weight: 600;"><?php esc_html_e( 'Panel de Vista Previa', 'aura-suite' ); ?></h4>
                    <p style="color:var(--aura-text-secondary, #64748b); max-width: 420px; margin: 0 auto; font-size: 14px;">
                        <?php esc_html_e( 'Selecciona un tipo de reporte y haz clic en "Generar Reporte" para visualizar los resultados.', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Loader -->
                <div class="aura-report-loader" id="st-report-loader" style="display:none; text-align:center; padding:64px 0;">
                    <div class="aura-spinner" style="width: 40px; height: 40px; border-width: 3px; border-color: var(--aura-primary-200, #ddd6fe); border-top-color: var(--aura-primary-600, #7c3aed); margin: 0 auto 16px;"></div>
                    <p style="color:var(--aura-text-secondary, #64748b); font-size:14px; font-weight:500;">
                        <?php esc_html_e( 'Generando reporte…', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Cabecera del reporte -->
                <div id="st-report-header" style="display:none; margin-bottom: var(--aura-space-5, 20px); padding-bottom: var(--aura-space-4, 16px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                        <div>
                            <h2 id="st-report-title" style="margin:0 0 4px; color:var(--aura-primary-900, #4c1d95); font-size:18px; font-weight: 700;"></h2>
                            <small id="st-report-meta" style="color:var(--aura-text-secondary, #64748b); font-size: 13px;"></small>
                        </div>
                        <div id="st-report-count" class="aura-badge aura-badge-primary" style="font-size: 13px; padding: 6px 14px; border-radius: var(--aura-radius-full, 9999px); font-weight: 600;">
                        </div>
                    </div>
                </div>

                <!-- Contenedor de la tabla -->
                <div id="st-report-table-wrap" class="aura-table-responsive" style="display:none;"></div>
            </div>

        </main><!-- /.aura-reports-main -->

    </div><!-- /.aura-reports-layout -->

</div><!-- /.aura-students-wrap -->

<?php
// Datos para pasar a JS (tipado de reportes sin PDF)
$no_pdf_json = wp_json_encode( $no_pdf_types );
?>

<script>
(function($) {
    'use strict';

    // Localization object published by enqueue_assets()
    var cfg = (typeof auraStudentsReports !== 'undefined') ? auraStudentsReports : {
        ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'aura_students_reports_nonce' ) ); ?>',
        exportNonce: '<?php echo esc_js( wp_create_nonce( 'aura_students_reports_export' ) ); ?>'
    };

    var noPdfTypes = <?php echo $no_pdf_json; // phpcs:ignore ?>;
    var $form      = $('#aura-students-report-form');
    var $genBtn    = $('#btn-st-generate');
    var $exportCard = $('#st-export-card');
    var $pdfBtn    = $('#btn-st-pdf');
    var $pdfNote   = $('#st-pdf-note');

    // Habilitar botón cuando se selecciona tipo de reporte
    $('#rep-type').on('change', function() {
        var val = $(this).val();
        $genBtn.prop('disabled', val === '');
        if (val !== '') {
            var noPdf = noPdfTypes.indexOf(val) > -1;
            $pdfBtn.toggle(!noPdf);
            $pdfNote.toggle(noPdf);
        }
    });

    // Filtrar cursos por área seleccionada
    $('#rep-area').on('change', function() {
        var areaId = $(this).val();
        $('#rep-course option').each(function() {
            var $opt = $(this);
            if ($opt.val() === '0' || areaId === '0' || $opt.data('area') == areaId) {
                $opt.show();
            } else {
                $opt.hide();
            }
        });
        // Reset course to "all" if the selected course belongs to a different area
        var $selCourse = $('#rep-course option:selected');
        if ($selCourse.val() !== '0' && areaId !== '0' && $selCourse.data('area') != areaId) {
            $('#rep-course').val('0');
        }
    });

    // Presets de fecha
    $('.aura-preset-btn').on('click', function() {
        var preset = $(this).data('preset');
        var now    = new Date();
        var y      = now.getFullYear();
        var m      = now.getMonth(); // 0-indexed
        var startD, endD;

        if (preset === 'month') {
            startD = new Date(y, m, 1);
            endD   = new Date(y, m + 1, 0);
        } else if (preset === 'quarter') {
            var q  = Math.floor(m / 3);
            startD = new Date(y, q * 3, 1);
            endD   = new Date(y, q * 3 + 3, 0);
        } else if (preset === 'year') {
            startD = new Date(y, 0, 1);
            endD   = new Date(y, 11, 31);
        }

        if (startD) {
            $('#rep-start').val(fmt(startD));
            $('#rep-end').val(fmt(endD));
        }
    });

    function fmt(d) {
        var mm = ('0' + (d.getMonth() + 1)).slice(-2);
        var dd = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    // Recoger parámetros actuales del formulario
    function getParams() {
        return {
            report_type:  $('#rep-type').val(),
            start:        $('#rep-start').val(),
            end:          $('#rep-end').val(),
            area_id:      $('#rep-area').val() || '0',
            course_id:    $('#rep-course').val() || '0',
            profile_type: $('#rep-profile-type').val(),
            status:       $('#rep-status').val()
        };
    }

    // ── Generar vista previa ──
    $form.on('submit', function(e) {
        e.preventDefault();

        var params = getParams();
        if (!params.report_type) return;

        showLoader();

        $.post(cfg.ajaxUrl, $.extend(params, {
            action: 'aura_students_generate_report',
            nonce:  cfg.nonce
        }), function(res) {
            if (res && res.success) {
                renderReport(res.data);
            } else {
                var msg = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Error al generar el reporte.', 'aura-suite' ) ); ?>';
                showError(msg);
            }
        }, 'json').fail(function() {
            showError('<?php echo esc_js( __( 'Error de conexión. Inténtelo de nuevo.', 'aura-suite' ) ); ?>');
        });
    });

    // ── Exportar Excel ──
    $('#btn-st-excel').on('click', function() { doExport('excel'); });

    // ── Exportar PDF ──
    $('#btn-st-pdf').on('click', function() { doExport('pdf'); });

    function doExport(format) {
        var params = getParams();
        if (!params.report_type) return;

        var action = (format === 'pdf') ? 'aura_students_export_pdf' : 'aura_students_export_excel';

        var qs = $.param($.extend(params, {
            action:       action,
            export_nonce: cfg.exportNonce
        }));
        window.location.href = cfg.ajaxUrl + '?' + qs;
    }

    // ── Render ──
    function renderReport(data) {
        hideLoader();

        var headers = data.headers || [];
        var rows    = data.rows    || [];
        var title   = data.title   || '';
        var total   = data.total   !== undefined ? data.total : rows.length;

        // Cabecera
        $('#st-report-title').text(title);
        $('#st-report-meta').text(
            '<?php echo esc_js( __( 'Período:', 'aura-suite' ) ); ?> ' + $('#rep-start').val() + ' — ' + $('#rep-end').val()
        );
        $('#st-report-count').text(total + ' <?php echo esc_js( __( 'registros', 'aura-suite' ) ); ?>');
        $('#st-report-header').show();

        if (rows.length === 0) {
            $('#st-report-table-wrap').html(
                '<p style="color:var(--aura-text-secondary, #64748b); padding:24px; text-align:center;"><?php echo esc_js( __( 'No se encontraron registros para esta selección.', 'aura-suite' ) ); ?></p>'
            ).show();
        } else {
            var html = '<table class="aura-table"><thead><tr>';
            headers.forEach(function(h) { html += '<th>' + escHtml(h) + '</th>'; });
            html += '</tr></thead><tbody>';

            rows.forEach(function(row) {
                html += '<tr>';
                headers.forEach(function(h, i) {
                    var cell = (row[i] !== null && row[i] !== undefined) ? row[i] : '';
                    html += '<td>' + escHtml(String(cell)) + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            $('#st-report-table-wrap').html(html).show();
        }

        // Mostrar panel de exportación
        $exportCard.show();
    }

    function showLoader() {
        $('#st-report-empty').hide();
        $('#st-report-header').hide();
        $('#st-report-table-wrap').hide();
        $exportCard.hide();
        $('#st-report-loader').show();
    }

    function hideLoader() {
        $('#st-report-loader').hide();
    }

    function showError(msg) {
        hideLoader();
        $('#st-report-header').hide();
        $('#st-report-table-wrap').html(
            '<div style="padding:16px; background:#fef2f2; border:1px solid #fca5a5; border-radius: var(--aura-radius-md, 8px); color:#991b1b;">' +
            escHtml(msg) + '</div>'
        ).show();
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})(jQuery);
</script>
