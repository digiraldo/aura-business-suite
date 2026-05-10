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
<div class="wrap aura-students-wrap aura-reports-wrap" id="aura-students-reports-app">

    <!-- ══════════════════ CABECERA ══════════════════ -->
    <div class="aura-reports-header">
        <h1>📊 <?php esc_html_e( 'Reportes de Estudiantes', 'aura-suite' ); ?></h1>
        <p class="aura-reports-subtitle">
            <?php esc_html_e( 'Genera, visualiza y exporta reportes del módulo de estudiantes.', 'aura-suite' ); ?>
        </p>
    </div>

    <div class="aura-reports-layout">

        <!-- ══════════════════ PANEL IZQUIERDO: Configuración ══════════════════ -->
        <aside class="aura-reports-sidebar">

            <!-- Formulario de configuración -->
            <div class="aura-reports-card">
                <h2 class="aura-reports-card__title">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e( 'Configurar Reporte', 'aura-suite' ); ?>
                </h2>

                <form id="aura-students-report-form" autocomplete="off">

                    <!-- Tipo de reporte -->
                    <div class="aura-form-group">
                        <label for="rep-type"><?php esc_html_e( 'Tipo de reporte', 'aura-suite' ); ?></label>
                        <select id="rep-type" name="report_type" class="aura-select" required>
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
                    <div class="aura-form-group">
                        <label><?php esc_html_e( 'Período', 'aura-suite' ); ?></label>
                        <div class="aura-date-presets">
                            <button type="button" class="aura-preset-btn" data-preset="month">
                                <?php esc_html_e( 'Este mes', 'aura-suite' ); ?>
                            </button>
                            <button type="button" class="aura-preset-btn" data-preset="quarter">
                                <?php esc_html_e( 'Trimestre', 'aura-suite' ); ?>
                            </button>
                            <button type="button" class="aura-preset-btn" data-preset="year">
                                <?php esc_html_e( 'Este año', 'aura-suite' ); ?>
                            </button>
                        </div>
                        <div class="aura-date-range">
                            <input type="date" id="rep-start" name="start" class="aura-input"
                                   value="<?php echo esc_attr( date( 'Y-01-01' ) ); ?>">
                            <span>—</span>
                            <input type="date" id="rep-end" name="end" class="aura-input"
                                   value="<?php echo esc_attr( date( 'Y-12-31' ) ); ?>">
                        </div>
                    </div>

                    <!-- Área / Programa -->
                    <?php if ( ! empty( $areas ) ) : ?>
                    <div class="aura-form-group">
                        <label for="rep-area"><?php esc_html_e( 'Área / Programa', 'aura-suite' ); ?></label>
                        <select id="rep-area" name="area_id" class="aura-select">
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
                    <div class="aura-form-group">
                        <label for="rep-course"><?php esc_html_e( 'Curso', 'aura-suite' ); ?></label>
                        <select id="rep-course" name="course_id" class="aura-select">
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
                    <div class="aura-form-group">
                        <label for="rep-profile-type"><?php esc_html_e( 'Tipo de perfil', 'aura-suite' ); ?></label>
                        <select id="rep-profile-type" name="profile_type" class="aura-select">
                            <option value=""><?php esc_html_e( '— Todos —', 'aura-suite' ); ?></option>
                            <option value="student"><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></option>
                            <option value="worker"><?php esc_html_e( 'Trabajador', 'aura-suite' ); ?></option>
                            <option value="external"><?php esc_html_e( 'Externo', 'aura-suite' ); ?></option>
                            <option value="teacher"><?php esc_html_e( 'Docente', 'aura-suite' ); ?></option>
                            <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Estado del estudiante -->
                    <div class="aura-form-group">
                        <label for="rep-status"><?php esc_html_e( 'Estado del estudiante', 'aura-suite' ); ?></label>
                        <select id="rep-status" name="status" class="aura-select">
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
                    <div class="aura-form-group">
                        <button type="submit" id="btn-st-generate"
                                class="button button-primary aura-btn-generate"
                                style="width:100%;" disabled>
                            <span class="dashicons dashicons-visibility" style="margin-top:3px;"></span>
                            <?php esc_html_e( 'Generar Reporte', 'aura-suite' ); ?>
                        </button>
                    </div>

                </form>
            </div><!-- /.aura-reports-card (form) -->

            <!-- Exportación -->
            <div class="aura-reports-card" id="st-export-card" style="display:none;">
                <h2 class="aura-reports-card__title">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Exportar', 'aura-suite' ); ?>
                </h2>
                <div class="aura-export-buttons" style="display:flex;flex-direction:column;gap:8px;">
                    <button type="button" id="btn-st-excel"
                            class="aura-export-btn aura-export-btn--excel"
                            style="display:flex;align-items:center;gap:6px;">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php esc_html_e( 'Descargar Excel (.xlsx)', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="btn-st-pdf"
                            class="aura-export-btn aura-export-btn--print"
                            style="display:flex;align-items:center;gap:6px;">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php esc_html_e( 'Descargar PDF', 'aura-suite' ); ?>
                    </button>
                </div>
                <small id="st-pdf-note" style="display:none;color:#6b7280;margin-top:8px;display:block;">
                    <?php esc_html_e( 'Este tipo de reporte no admite exportación PDF.', 'aura-suite' ); ?>
                </small>
            </div><!-- /.aura-reports-card (export) -->

        </aside><!-- /.aura-reports-sidebar -->

        <!-- ══════════════════ PANEL DERECHO: Resultados ══════════════════ -->
        <main class="aura-reports-main" id="st-report-output">

            <!-- Estado vacío inicial -->
            <div class="aura-report-empty" id="st-report-empty">
                <span class="dashicons dashicons-chart-bar aura-report-empty__icon"
                      style="font-size:48px;width:48px;height:48px;color:#8b5cf6;"></span>
                <p style="color:#6b7280;margin-top:12px;">
                    <?php esc_html_e( 'Selecciona un tipo de reporte y haz clic en "Generar Reporte" para visualizar los resultados.', 'aura-suite' ); ?>
                </p>
            </div>

            <!-- Loader -->
            <div class="aura-report-loader" id="st-report-loader" style="display:none;text-align:center;padding:48px 0;">
                <span class="dashicons dashicons-update-alt"
                      style="font-size:36px;width:36px;height:36px;color:#8b5cf6;animation:spin 1s linear infinite;"></span>
                <p style="color:#6b7280;margin-top:12px;">
                    <?php esc_html_e( 'Generando reporte…', 'aura-suite' ); ?>
                </p>
            </div>

            <!-- Cabecera del reporte -->
            <div id="st-report-header" style="display:none;margin-bottom:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <div>
                        <h2 id="st-report-title" style="margin:0;color:#5b21b6;font-size:18px;"></h2>
                        <small id="st-report-meta" style="color:#6b7280;"></small>
                    </div>
                    <div id="st-report-count"
                         style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;padding:6px 14px;color:#5b21b6;font-weight:600;">
                    </div>
                </div>
            </div>

            <!-- Contenedor de la tabla -->
            <div id="st-report-table-wrap" style="display:none;overflow-x:auto;"></div>

        </main><!-- /.aura-reports-main -->

    </div><!-- /.aura-reports-layout -->

</div><!-- /.aura-students-wrap -->

<?php
// Datos para pasar a JS (tipado de reportes sin PDF)
$no_pdf_json = wp_json_encode( $no_pdf_types );
?>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
#aura-students-reports-app .aura-reports-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    align-items: start;
}
#aura-students-reports-app .aura-reports-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
}
#aura-students-reports-app .aura-reports-card__title {
    font-size: 14px;
    font-weight: 600;
    color: #5b21b6;
    margin: 0 0 16px;
    display: flex;
    align-items: center;
    gap: 6px;
}
#aura-students-reports-app .aura-reports-header { margin-bottom: 20px; }
#aura-students-reports-app .aura-reports-header h1 { margin-bottom: 4px; }
#aura-students-reports-app .aura-reports-subtitle { color: #6b7280; margin: 0; }
#aura-students-reports-app .aura-form-group { margin-bottom: 14px; }
#aura-students-reports-app .aura-form-group label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 4px;
}
#aura-students-reports-app .aura-select,
#aura-students-reports-app .aura-input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    font-size: 13px;
    box-sizing: border-box;
}
#aura-students-reports-app .aura-date-range {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
}
#aura-students-reports-app .aura-date-range .aura-input { flex: 1; }
#aura-students-reports-app .aura-date-presets {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}
#aura-students-reports-app .aura-preset-btn {
    padding: 2px 8px;
    font-size: 11px;
    border: 1px solid #8b5cf6;
    background: #f5f3ff;
    color: #5b21b6;
    border-radius: 4px;
    cursor: pointer;
}
#aura-students-reports-app .aura-preset-btn:hover { background: #ede9fe; }
#aura-students-reports-app .aura-export-btn {
    padding: 8px 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    width: 100%;
    justify-content: center;
}
#aura-students-reports-app .aura-export-btn--excel { background:#059669;color:#fff; }
#aura-students-reports-app .aura-export-btn--excel:hover { background:#047857; }
#aura-students-reports-app .aura-export-btn--print { background:#7c3aed;color:#fff; }
#aura-students-reports-app .aura-export-btn--print:hover { background:#6d28d9; }
#aura-students-reports-app .aura-report-empty {
    text-align: center;
    padding: 64px 32px;
    background: #fafafa;
    border: 2px dashed #e5e7eb;
    border-radius: 8px;
}
#aura-students-reports-app #st-report-table-wrap table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
#aura-students-reports-app #st-report-table-wrap th {
    background: #5b21b6;
    color: #fff;
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
    white-space: nowrap;
}
#aura-students-reports-app #st-report-table-wrap td {
    padding: 7px 12px;
    border-bottom: 1px solid #e5e7eb;
    color: #374151;
}
#aura-students-reports-app #st-report-table-wrap tr:nth-child(even) td { background: #f5f3ff; }
#aura-students-reports-app #st-report-table-wrap tr:hover td { background: #ede9fe; }
#aura-students-reports-app .aura-reports-main {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    min-height: 300px;
}
@media (max-width: 960px) {
    #aura-students-reports-app .aura-reports-layout { grid-template-columns: 1fr; }
}
</style>

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
                '<p style="color:#6b7280;padding:16px;"><?php echo esc_js( __( 'No se encontraron registros para esta selección.', 'aura-suite' ) ); ?></p>'
            ).show();
        } else {
            var html = '<table><thead><tr>';
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
            '<div style="padding:16px;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;">' +
            escHtml(msg) + '</div>'
        ).show();
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})(jQuery);
</script>
