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
                ➕ <?php esc_html_e( 'Crear Evento', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-event-editor">&times;</button>
        </div>

        <form id="form-event-editor" class="aura-modal-form" novalidate>
            <input type="hidden" name="id" id="evt-id" value="0">

            <div class="aura-modal-body">
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    
                    <!-- Título del Evento -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Título o Nombre del Evento', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-prefix">🏷️</span>
                            <input type="text" name="title" id="evt-title" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Taller de Liderazgo — Módulo 1', 'aura' ); ?>" style="width: 100%; border-radius: 8px; padding-left: 38px;">
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
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 4px;">
                            <span>👨‍🏫 <?php esc_html_e( 'Profesor(es) o Instructor(es) a Cargo', 'aura' ); ?></span>
                            <span style="font-size: 11px; font-weight: normal; color: #d97706; display: inline-flex; align-items: center; gap: 3px;">
                                ⭐ <?php esc_html_e( 'Clic en la estrella para definir al Titular / Principal', 'aura' ); ?>
                            </span>
                        </label>
                        <input type="hidden" name="primary_teacher_id" id="evt-primary-teacher-id" value="0">
                        <div id="evt-teachers-container" style="display: flex; flex-wrap: wrap; gap: 8px; max-height: 120px; overflow-y: auto; padding: 10px; border: 1px solid var(--aura-border, #cbd5e1); border-radius: 8px; background: var(--aura-surface-alt, #f8fafc);">
                            <!-- Inyectado dinámicamente con checkboxes desde JS -->
                        </div>
                    </div>

                    <!-- Instructores Terceros / Externos -->
                    <div class="form-group" style="background: rgba(16, 185, 129, 0.04); border: 1px dashed rgba(16, 185, 129, 0.35); border-radius: 8px; padding: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin: 0; display: flex; align-items: center; gap: 6px;">
                                <span>🏢 <?php esc_html_e( 'Instructores Terceros / Externos', 'aura' ); ?></span>
                            </label>
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-third-parties' ) ); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-ghost" style="font-size: 11px; padding: 3px 6px; color: var(--aura-text-muted, #64748b);" title="<?php esc_attr_e( 'Abrir Directorio de Terceros en nueva pestaña', 'aura' ); ?>">
                                    ↗ <?php esc_html_e( 'Directorio', 'aura' ); ?>
                                </a>
                                <button type="button" id="btn-open-tp-catalog-explorer" class="btn btn-xs btn-primary" style="font-size: 11.5px; padding: 3px 10px; display: inline-flex; align-items: center; gap: 5px; background: #0284c7; border-color: #0284c7; color: #fff; font-weight: 600; border-radius: 6px; box-shadow: 0 1px 3px rgba(2,132,199,0.3);">
                                    🏛️ <?php esc_html_e( 'Catálogo de Terceros', 'aura' ); ?>
                                </button>
                                <button type="button" id="btn-toggle-add-external-inst" class="btn btn-xs btn-outline" style="font-size: 11.5px; padding: 3px 8px; border-radius: 6px;">
                                    ➕ <?php esc_html_e( 'Agregar Manual', 'aura' ); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Formulario desplegable para nuevo externo -->
                        <div id="box-add-external-inst" style="display: none; background: var(--aura-surface, #ffffff); border: 1px solid var(--aura-border, #cbd5e1); border-radius: 8px; padding: 12px; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                            <input type="hidden" id="ext-inst-third-party-id" value="">
                            <input type="hidden" id="ext-inst-wp-user-id" value="">
                            <input type="hidden" id="ext-inst-avatar-url" value="">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;"><?php esc_html_e( 'Nombre Completo / Razón Social', 'aura' ); ?> <span style="color:#ef4444;">*</span></label>
                                    <input type="text" id="ext-inst-name" class="form-control" style="font-size: 12px; padding: 5px 8px;" placeholder="<?php esc_attr_e( 'Escribe para autocompletar o registrar...', 'aura' ); ?>">
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;"><?php esc_html_e( 'Correo Electrónico', 'aura' ); ?></label>
                                    <input type="email" id="ext-inst-email" class="form-control" style="font-size: 12px; padding: 5px 8px;" placeholder="contacto@empresa.com">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;"><?php esc_html_e( 'Organización / Institución', 'aura' ); ?></label>
                                    <input type="text" id="ext-inst-org" class="form-control" style="font-size: 12px; padding: 5px 8px;" placeholder="<?php esc_attr_e( 'Empresa / Institución', 'aura' ); ?>">
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;"><?php esc_html_e( 'Teléfono', 'aura' ); ?></label>
                                    <input type="text" id="ext-inst-phone" class="form-control" style="font-size: 12px; padding: 5px 8px;" placeholder="+502 ...">
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;"><?php esc_html_e( 'Rol en la Clase', 'aura' ); ?></label>
                                    <select id="ext-inst-role" class="form-control" style="font-size: 12px; padding: 5px 8px;">
                                        <option value="guest"><?php esc_html_e( 'Invitado / Ponente', 'aura' ); ?></option>
                                        <option value="lead"><?php esc_html_e( 'Titular Externo', 'aura' ); ?></option>
                                        <option value="assistant"><?php esc_html_e( 'Co-instructor', 'aura' ); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px; border-top: 1px solid #f1f5f9;">
                                <div id="ext-inst-linked-badge" style="display: none; align-items: center; gap: 4px; font-size: 11px; color: #0284c7; font-weight: 600;">
                                    <span class="dashicons dashicons-admin-links" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                    <span><?php esc_html_e( 'Vinculado a Entidad del Catálogo', 'aura' ); ?></span>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 6px; margin-left: auto;">
                                    <button type="button" id="btn-cancel-add-external" class="btn btn-xs btn-ghost" style="font-size: 11px;"><?php esc_html_e( 'Cancelar', 'aura' ); ?></button>
                                    <button type="button" id="btn-save-external-inst" class="btn btn-xs btn-emerald" style="font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Añadir a la Clase', 'aura' ); ?></button>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de terceros asignados con chips -->
                        <div id="evt-external-instructors-list" style="display: flex; flex-wrap: wrap; gap: 6px; min-height: 24px; align-items: center;">
                            <span id="evt-ext-none-hint" style="font-size: 11.5px; color: var(--aura-text-muted); font-style: italic;"><?php esc_html_e( 'Sin instructores externos asignados.', 'aura' ); ?></span>
                        </div>
                        <input type="hidden" name="external_instructors_json" id="evt-external-instructors-json" value="[]">
                    </div>

                    <!-- Estudiantes Líderes / Responsables de la Actividad -->
                    <div class="form-group" style="background: rgba(93,95,239,0.04); border: 1px solid rgba(93,95,239,0.15); border-radius: 8px; padding: 12px;">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 4px;">
                            <span>🌟 <?php esc_html_e( 'Asignar Estudiantes a Actividad (Liderazgo / Roles)', 'aura' ); ?></span>
                            <span style="font-size: 11px; font-weight: normal; color: var(--aura-text-muted);"><?php esc_html_e( 'Líderes de programa, expositores, monitores', 'aura' ); ?></span>
                        </label>
                        
                        <!-- Mini selector para añadir estudiante con rol -->
                        <div style="display: grid; grid-template-columns: 1.4fr 1.2fr auto; gap: 8px; margin-bottom: 10px; align-items: center;">
                            <select id="select-add-leader-user" class="form-control" style="font-size: 12.5px; padding: 6px 10px; border-radius: 6px;">
                                <option value=""><?php esc_html_e( 'Seleccionar estudiante...', 'aura' ); ?></option>
                            </select>
                            <select id="select-add-leader-role" class="form-control" style="font-size: 12.5px; padding: 6px 10px; border-radius: 6px;">
                                <option value="activity_leader"><?php esc_html_e( '🎯 Líder de Actividad', 'aura' ); ?></option>
                                <option value="program_leader"><?php esc_html_e( '👑 Líder de Programa', 'aura' ); ?></option>
                                <option value="presenter"><?php esc_html_e( '🗣️ Expositor / Dar Clase', 'aura' ); ?></option>
                                <option value="monitor"><?php esc_html_e( '🛡️ Monitor / Moderador', 'aura' ); ?></option>
                            </select>
                            <button type="button" id="btn-add-leader-to-event" class="btn btn-outline" style="font-size: 12px; padding: 6px 12px; white-space: nowrap;">
                                ➕ <?php esc_html_e( 'Asignar', 'aura' ); ?>
                            </button>
                        </div>
                        
                        <!-- Lista de líderes asignados con chips y avatar -->
                        <div id="evt-student-leaders-list" style="display: flex; flex-wrap: wrap; gap: 8px; min-height: 28px; align-items: center;">
                            <!-- Inyectado dinámicamente desde JS -->
                        </div>
                        <input type="hidden" name="student_leaders_json" id="evt-student-leaders-json" value="[]">
                    </div>

                    <!-- ── SECCIÓN DE RECURRENCIA (SÓLO CREACIÓN) ── -->
                    <div id="sec-recurrence-toggle" style="background: var(--aura-primary-alpha, rgba(99, 102, 241, 0.06)); border: 1px dashed var(--aura-primary, #6366f1); border-radius: 10px; padding: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="is_recurring" id="evt-is-recurring" value="1">
                            🔁 <?php esc_html_e( 'Crear como serie recurrente (ej: Lunes a Viernes de 9 a 12)', 'aura' ); ?>
                        </label>

                        <div id="box-recurrence-details" style="display: none; margin-top: 14px; flex-direction: column; gap: 12px;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                                    <span style="font-size: 12px; font-weight: 600; color: var(--aura-text-secondary);">
                                        <?php esc_html_e( 'Días de la semana:', 'aura' ); ?>
                                    </span>
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <button type="button" class="btn-rec-preset" data-preset="same-day" style="font-size: 11px; padding: 2px 7px; border-radius: 4px; border: 1px solid var(--aura-border, #cbd5e1); background: var(--aura-surface, #f8fafc); cursor: pointer; font-weight: 600;">
                                            📅 <?php esc_html_e( 'Mismo día', 'aura' ); ?>
                                        </button>
                                        <button type="button" class="btn-rec-preset" data-preset="weekdays" style="font-size: 11px; padding: 2px 7px; border-radius: 4px; border: 1px solid var(--aura-border, #cbd5e1); background: var(--aura-surface, #f8fafc); cursor: pointer; font-weight: 600;">
                                            <?php esc_html_e( 'Lun - Vie', 'aura' ); ?>
                                        </button>
                                        <button type="button" class="btn-rec-preset" data-preset="all" style="font-size: 11px; padding: 2px 7px; border-radius: 4px; border: 1px solid var(--aura-border, #cbd5e1); background: var(--aura-surface, #f8fafc); cursor: pointer; font-weight: 600;">
                                            <?php esc_html_e( 'Todos', 'aura' ); ?>
                                        </button>
                                        <button type="button" class="btn-rec-preset" data-preset="weekend" style="font-size: 11px; padding: 2px 7px; border-radius: 4px; border: 1px solid var(--aura-border, #cbd5e1); background: var(--aura-surface, #f8fafc); cursor: pointer; font-weight: 600;">
                                            <?php esc_html_e( 'Fin de semana', 'aura' ); ?>
                                        </button>
                                    </div>
                                </div>
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

                    <!-- Color Distintivo con Paleta Oficial -->
                    <div class="form-group" style="margin-bottom: 4px;">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            🎨 <?php esc_html_e( 'Color Distintivo del Evento', 'aura' ); ?>
                        </label>
                        <div class="aura-color-picker-box">
                            <div class="aura-color-picker-row">
                                <input type="color" name="color" id="evt-color" value="#5D5FEF" class="aura-color-custom-input" title="<?php esc_attr_e( 'Color personalizado', 'aura' ); ?>">
                                <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Paleta de colores oficial:', 'aura' ); ?></span>
                            </div>
                            <?php echo Aura_Calendar_Admin::render_color_palette( 'evt-color', '#5D5FEF' ); ?>
                        </div>
                    </div>

                    <!-- Descripción y Temario -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            📝 <?php esc_html_e( 'Descripción y Temario', 'aura' ); ?>
                        </label>
                        <textarea name="description" id="evt-description" rows="4" class="form-control" placeholder="<?php esc_attr_e( "Detalles, temario y puntos clave:\n- Texto 1\n- Texto 2\n- Texto 3", 'aura' ); ?>" style="width: 100%; border-radius: 8px; font-family: inherit; line-height: 1.5; white-space: pre-wrap;"></textarea>
                    </div>

                    <!-- ── SECCIÓN DE EVENTOS RÁPIDOS Y GENÉRICOS (TOGGLE) ── -->
                    <div class="form-group box-quick-generic-section">
                        <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; margin: 0; user-select: none;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 16px;">⚡</span>
                                <div>
                                    <strong style="font-size: 13px; color: var(--aura-text-primary, #0f172a); display: block;">
                                        <?php esc_html_e( 'Eventos Rápidos y Genéricos', 'aura' ); ?>
                                    </strong>
                                    <span style="font-size: 11.5px; color: var(--aura-text-muted, #64748b);">
                                        <?php esc_html_e( 'Rellena título, tipo, color y auto-calcula duración de 30 min', 'aura' ); ?>
                                    </span>
                                </div>
                            </div>
                            <input type="checkbox" id="toggle-generic-events" class="aura-switch-input" style="cursor: pointer; width: 18px; height: 18px;">
                        </label>

                        <div id="container-quick-generic-events" style="display: none; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--aura-border, #e2e8f0);">
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-muted, #64748b); margin-bottom: 8px;">
                                <?php esc_html_e( 'Selecciona para autocompletar (100% editable):', 'aura' ); ?>
                            </div>
                            <div id="quick-generic-chips-list" style="display: flex; flex-wrap: wrap; gap: 6px;">
                                <!-- Inyectado dinámicamente desde calendar-admin.js -->
                            </div>
                        </div>
                    </div>

                    <!-- Enviar invitaciones por correo (Fase 7) -->
                    <div class="form-group" style="background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 8px; padding: 10px 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; cursor: pointer; user-select: none; margin: 0;">
                            <input type="checkbox" name="send_calendar_invitations" id="evt-send-invitations" value="1" style="width: 16px; height: 16px;">
                            <span>✉️ <?php esc_html_e( 'Enviar invitación de calendario por correo a los instructores al guardar', 'aura' ); ?></span>
                        </label>
                        <div style="font-size: 11.5px; color: var(--aura-text-muted, #64748b); margin-left: 24px; margin-top: 2px;">
                            <?php esc_html_e( 'Incluye archivo adjunto .ics y enlaces directos para Google Calendar y Outlook.', 'aura' ); ?>
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
                    💾 <?php esc_html_e( 'Guardar Evento', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL 2: DETALLE RÁPIDO Y MODERNO DE EVENTO / CLASE
     ══════════════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/modal-event-detail.php'; ?>

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
