<?php
/**
 * Modales Centralizados — Módulo de Calendario y Horarios Académicos
 * Aura Business Suite
 *
 * Contiene:
 * 1. #modal-event-editor: Creación y edición de clases / actividades académicas
 * 2. #modal-event-detail: Detalle rápido de la sesión con accesos a asistencia y edición
 * 3. #modal-attendance: Control y pase de lista de asistencia con estados dinámicos
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! isset( $programs ) || ! is_array( $programs ) ) {
    $programs = Aura_Calendar_Programs::get_all( [ 'status' => 'active', 'limit' => 100 ] );
}
?>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL 1: CREADOR / EDITOR DE CLASES Y ACTIVIDADES
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-event-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 680px;">
        <div class="aura-modal-header">
            <h3 id="modal-event-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                ➕ <?php esc_html_e( 'Agendar Clase o Actividad', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-event-editor">&times;</button>
        </div>

        <form id="form-event-editor" class="aura-modal-form">
            <input type="hidden" name="id" id="evt-id" value="0">

            <div class="aura-modal-body">
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    
                    <!-- Título del Evento -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Título o Nombre de la Clase / Sesión', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-prefix">🏷️</span>
                            <input type="text" name="title" id="evt-title" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Identidad en Cristo — Módulo 1', 'aura' ); ?>" style="width: 100%; border-radius: 8px; padding-left: 38px;">
                        </div>
                    </div>

                    <!-- Fila: Programa y Materia -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                🎓 <?php esc_html_e( 'Programa Académico', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <select name="program_id" id="evt-program-id" required class="form-control" style="width: 100%; border-radius: 8px; padding: 10px 12px;">
                                <option value=""><?php esc_html_e( 'Seleccionar programa...', 'aura' ); ?></option>
                                <?php foreach ( $programs as $p ) : ?>
                                    <option value="<?php echo esc_attr( $p->id ); ?>" data-color="<?php echo esc_attr( $p->color ); ?>">
                                        <?php echo esc_html( $p->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                📚 <?php esc_html_e( 'Materia / Asignatura', 'aura' ); ?>
                            </label>
                            <select name="subject_id" id="evt-subject-id" class="form-control" style="width: 100%; border-radius: 8px; padding: 10px 12px;">
                                <option value=""><?php esc_html_e( 'General / Sin materia específica', 'aura' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila: Tipo de Evento y Estado -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                📌 <?php esc_html_e( 'Tipo de Evento', 'aura' ); ?>
                            </label>
                            <select name="event_type" id="evt-type" class="form-control" style="width: 100%; border-radius: 8px; padding: 10px 12px;">
                                <option value="class">📖 <?php esc_html_e( 'Clase Regular', 'aura' ); ?></option>
                                <option value="exam">📝 <?php esc_html_e( 'Examen / Evaluación', 'aura' ); ?></option>
                                <option value="workshop">🔬 <?php esc_html_e( 'Taller / Práctica', 'aura' ); ?></option>
                                <option value="activity">🎯 <?php esc_html_e( 'Actividad / Devocional / Deporte', 'aura' ); ?></option>
                                <option value="break">☕ <?php esc_html_e( 'Receso / Almuerzo', 'aura' ); ?></option>
                                <option value="other">📍 <?php esc_html_e( 'Otro', 'aura' ); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                🔄 <?php esc_html_e( 'Estado', 'aura' ); ?>
                            </label>
                            <select name="status" id="evt-status" class="form-control" style="width: 100%; border-radius: 8px; padding: 10px 12px;">
                                <option value="scheduled"><?php esc_html_e( 'Programado', 'aura' ); ?></option>
                                <option value="completed"><?php esc_html_e( 'Completado', 'aura' ); ?></option>
                                <option value="cancelled"><?php esc_html_e( 'Cancelado', 'aura' ); ?></option>
                                <option value="postponed"><?php esc_html_e( 'Pospuesto', 'aura' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Profesores Asignados -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            👨‍🏫 <?php esc_html_e( 'Profesor(es) o Instructor(es) a Cargo', 'aura' ); ?>
                        </label>
                        <div id="evt-teachers-container" style="display: flex; flex-wrap: wrap; gap: 8px; max-height: 100px; overflow-y: auto; padding: 10px; border: 1px solid var(--aura-border, #cbd5e1); border-radius: 8px; background: var(--aura-surface-alt, #f8fafc);">
                            <!-- Inyectado dinámicamente con checkboxes desde JS -->
                        </div>
                    </div>

                    <!-- ── SECCIÓN DE RECURRENCIA (SÓLO CREACIÓN) ── -->
                    <div id="sec-recurrence-toggle" style="background: var(--aura-primary-alpha, rgba(99, 102, 241, 0.06)); border: 1px dashed var(--aura-primary, #6366f1); border-radius: 10px; padding: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="is_recurring" id="evt-is-recurring" value="1">
                            🔁 <?php esc_html_e( 'Crear como serie recurrente (ej: Lunes a Viernes de 9 a 12)', 'aura' ); ?>
                        </label>

                        <div id="box-recurrence-details" style="display: none; margin-top: 14px; flex-direction: column; gap: 12px;">
                            <div>
                                <span style="font-size: 12px; font-weight: 600; color: var(--aura-text-secondary); display: block; margin-bottom: 6px;">
                                    <?php esc_html_e( 'Días de la semana:', 'aura' ); ?>
                                </span>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <?php
                                    $dias = [
                                        1 => __( 'Lun', 'aura' ),
                                        2 => __( 'Mar', 'aura' ),
                                        3 => __( 'Mié', 'aura' ),
                                        4 => __( 'Jue', 'aura' ),
                                        5 => __( 'Vie', 'aura' ),
                                        6 => __( 'Sáb', 'aura' ),
                                        7 => __( 'Dom', 'aura' ),
                                    ];
                                    foreach ( $dias as $val => $dia_label ) :
                                    ?>
                                        <label class="btn-day-pill" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid var(--aura-border); cursor: pointer;">
                                            <input type="checkbox" name="recurring_days[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, [ 1, 2, 3, 4, 5 ], true ) ); ?>>
                                            <?php echo esc_html( $dia_label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Hora Inicio', 'aura' ); ?></label>
                                    <input type="time" name="rec_time_start" id="rec-time-start" value="09:00" class="form-control" style="width: 100%; border-radius: 8px;">
                                </div>
                                <div>
                                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Hora Fin', 'aura' ); ?></label>
                                    <input type="time" name="rec_time_end" id="rec-time-end" value="12:00" class="form-control" style="width: 100%; border-radius: 8px;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Desde (Fecha Inicio)', 'aura' ); ?></label>
                                    <input type="date" name="rec_date_start" id="rec-date-start" class="form-control" style="width: 100%; border-radius: 8px;">
                                </div>
                                <div>
                                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Hasta (Fecha Fin)', 'aura' ); ?></label>
                                    <input type="date" name="rec_date_end" id="rec-date-end" class="form-control" style="width: 100%; border-radius: 8px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── FECHAS / HORAS SIMPLES (SI NO ES RECURRENTE) ── -->
                    <div id="box-single-datetime" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                🕐 <?php esc_html_e( 'Inicio (Fecha y Hora)', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="datetime-local" name="start_datetime" id="evt-start-dt" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                🕐 <?php esc_html_e( 'Fin (Fecha y Hora)', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="datetime-local" name="end_datetime" id="evt-end-dt" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px;">
                        </div>
                    </div>

                    <!-- Fila: Ubicación Física y Enlace Online -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                📍 <?php esc_html_e( 'Aula / Salón Físico', 'aura' ); ?>
                            </label>
                            <input type="text" name="location" id="evt-location" class="form-control" placeholder="<?php esc_attr_e( 'Ej: Aula Magna 2', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                💻 <?php esc_html_e( 'URL de Videollamada (Zoom/Meet)', 'aura' ); ?>
                            </label>
                            <input type="url" name="online_url" id="evt-online-url" class="form-control" placeholder="<?php esc_attr_e( 'https://meet.google.com/...', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                        </div>
                    </div>

                    <!-- Color y Descripción -->
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 14px; align-items: start;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                🎨 <?php esc_html_e( 'Color Distintivo', 'aura' ); ?>
                            </label>
                            <input type="color" name="color" id="evt-color" value="#6366f1" style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid var(--aura-border); cursor: pointer;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                📝 <?php esc_html_e( 'Descripción y Temario', 'aura' ); ?>
                            </label>
                            <textarea name="description" id="evt-description" rows="2" class="form-control" placeholder="<?php esc_attr_e( 'Detalles de la sesión, lecturas recomendadas...', 'aura' ); ?>" style="width: 100%; border-radius: 8px;"></textarea>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer del Modal (Fijo y Siempre Visible) -->
            <div class="aura-modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-event-editor">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-event">
                    💾 <?php esc_html_e( 'Guardar Clase', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL 2: DETALLE RÁPIDO DE EVENTO / CLASE
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-event-detail" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 560px;">
        <div class="aura-modal-header">
            <h3 id="det-title" class="adp-card-title" style="margin: 0; font-size: 18px;"></h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-event-detail">&times;</button>
        </div>

        <div class="aura-modal-body">
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <span id="det-type-badge" class="adp-badge badge-indigo"></span>
                    <span id="det-status-badge" class="adp-badge badge-emerald"></span>
                    <span id="det-gcal-badge" class="adp-badge badge-slate"></span>
                </div>

                <div style="background: var(--aura-surface-alt, #f8fafc); border-radius: 8px; padding: 14px; display: flex; flex-direction: column; gap: 8px; font-size: 14px;">
                    <div>🎓 <strong><?php esc_html_e( 'Programa:', 'aura' ); ?></strong> <span id="det-program"></span></div>
                    <div>📚 <strong><?php esc_html_e( 'Materia:', 'aura' ); ?></strong> <span id="det-subject"></span></div>
                    <div>🕐 <strong><?php esc_html_e( 'Horario:', 'aura' ); ?></strong> <span id="det-time"></span></div>
                    <div id="row-det-teachers" style="display: none;">👨‍🏫 <strong><?php esc_html_e( 'Profesor(es):', 'aura' ); ?></strong> <span id="det-teachers"></span></div>
                    <div id="row-det-location" style="display: none;">📍 <strong><?php esc_html_e( 'Aula:', 'aura' ); ?></strong> <span id="det-location"></span></div>
                    <div id="row-det-online" style="display: none;">💻 <strong><?php esc_html_e( 'Enlace Virtual:', 'aura' ); ?></strong> <a id="det-online" href="#" target="_blank" style="color: var(--aura-primary); text-decoration: underline;"></a></div>
                </div>

                <div id="box-det-desc" style="display: none; font-size: 13px; color: var(--aura-text-secondary); background: var(--aura-surface); border-left: 3px solid var(--aura-primary); padding: 10px 14px; border-radius: 0 6px 6px 0;"></div>
            </div>
        </div>

        <div class="aura-modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <?php if ( current_user_can( 'aura_take_attendance' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-emerald btn-lift" id="btn-det-attendance">
                        📋 <?php esc_html_e( 'Control de Asistencia', 'aura' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 8px;">
                <?php if ( current_user_can( 'aura_delete_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-ghost" id="btn-det-delete" style="color: #ef4444;">
                        🗑️ <?php esc_html_e( 'Eliminar', 'aura' ); ?>
                    </button>
                <?php endif; ?>

                <?php if ( current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-indigo btn-lift" id="btn-det-edit">
                        ✏️ <?php esc_html_e( 'Editar Clase', 'aura' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL 3: CONTROL Y TOMA DE ASISTENCIA
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-attendance" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 780px;">
        <div class="aura-modal-header">
            <div>
                <h3 id="att-modal-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                    📋 <?php esc_html_e( 'Control de Asistencia', 'aura' ); ?>
                </h3>
                <span id="att-modal-subtitle" style="font-size: 13px; color: var(--aura-text-secondary);"></span>
            </div>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-attendance">&times;</button>
        </div>

        <div class="aura-modal-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <span style="font-size: 13px; font-weight: 600; color: var(--aura-text-secondary);">
                    👥 <?php esc_html_e( 'Lista de Estudiantes', 'aura' ); ?>: <strong id="att-roster-count">0</strong>
                </span>
                <button type="button" id="btn-att-mark-all-present" class="btn btn-ghost" style="font-size: 12px; padding: 6px 12px;">
                    ✅ <?php esc_html_e( 'Marcar Todos Presentes', 'aura' ); ?>
                </button>
            </div>

            <div style="max-height: 380px; overflow-y: auto; border: 1px solid var(--aura-border); border-radius: 8px;">
                <table class="adp-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: var(--aura-surface-alt, #f8fafc); border-bottom: 1px solid var(--aura-border);">
                            <th style="padding: 10px 14px; text-align: left;"><?php esc_html_e( 'Estudiante', 'aura' ); ?></th>
                            <th style="padding: 10px 14px; text-align: center; width: 260px;"><?php esc_html_e( 'Estado', 'aura' ); ?></th>
                            <th style="padding: 10px 14px; text-align: left;"><?php esc_html_e( 'Observaciones', 'aura' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="att-table-body">
                        <!-- Filas de estudiantes inyectadas vía JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="aura-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn btn-ghost" data-close-modal="#modal-attendance">
                <?php esc_html_e( 'Cerrar', 'aura' ); ?>
            </button>
            <button type="button" class="btn btn-emerald btn-shimmer btn-lift" id="btn-save-attendance">
                💾 <?php esc_html_e( 'Guardar Asistencia', 'aura' ); ?>
            </button>
        </div>
    </div>
</div>
