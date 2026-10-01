<?php
/**
 * Modal Unificado: Detalle Rápido y Moderno de Evento / Clase
 * Aura Business Suite
 *
 * Reutilizable en:
 * - Backend (admin.php?page=aura-calendar)
 * - Portal Docente y Académico ([aura_teacher_portal])
 * - Portal de Estudiantes ([aura_student_schedule])
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- ══════════════════════════════════════════════════════════════════
     MODAL UNIFICADO: DETALLE RÁPIDO Y MODERNO DE EVENTO / CLASE
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-event-detail" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 999999; align-items: center; justify-content: center; padding: 16px;">
    <div class="aura-modal-container" style="max-width: 580px; width: 100%; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.45); border: 1px solid var(--aura-border, #cbd5e1); background: var(--aura-surface-card, #ffffff);">
        
        <!-- Header con Badges y Botón de Cierre -->
        <div class="aura-modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--aura-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; background: var(--aura-surface-alt, #f8fafc);">
            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                <span id="det-type-badge" class="adp-badge badge-indigo" style="font-size: 11px; font-weight: 700;">📖 Clase</span>
                <span id="det-status-badge" class="adp-badge badge-emerald" style="font-size: 11px; font-weight: 600;">Programado</span>
                <span id="det-gcal-badge" class="adp-badge badge-emerald" style="font-size: 10.5px; display: none;">✓ Google Calendar</span>
            </div>
            <button type="button" class="aura-modal-close btn-close-evt-detail" data-close-modal="#modal-event-detail" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--aura-text-muted, #64748b); line-height: 1; padding: 2px 6px;" aria-label="<?php esc_attr_e( 'Cerrar', 'aura' ); ?>">&times;</button>
        </div>

        <!-- Body con Scroll Suave -->
        <div class="aura-modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 14px; max-height: 75vh; overflow-y: auto;">
            
            <!-- Título Principal -->
            <h3 id="det-title" style="margin: 0; font-size: 19px; font-weight: 700; color: var(--aura-text-primary, #0f172a); line-height: 1.35;"></h3>

            <!-- Tarjeta Destacada de Profesores con Avatar Grande (44px) + Ring Animado y Stack -->
            <div id="box-det-teachers" style="background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #e2e8f0); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                <div id="det-teachers-avatars" style="display: flex; align-items: center;"></div>
                <div style="flex: 1; min-width: 0;">
                    <span style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--aura-text-muted, #64748b); display: block;">
                        <?php esc_html_e( 'Profesor(es) o Instructor(es)', 'aura' ); ?>
                    </span>
                    <div id="det-teachers-names" style="font-size: 13.5px; font-weight: 600; color: var(--aura-text-primary, #0f172a); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">-</div>
                </div>
            </div>

            <!-- Banner especial para estudiante si tiene rol o responsabilidad de liderazgo -->
            <div id="det-student-personal-leader-banner" style="display: none; padding: 10px 14px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); color: #b45309; font-size: 12.5px; font-weight: 600;">
                🌟 <span id="det-student-personal-leader-text"><?php esc_html_e( '¡Tienes una responsabilidad asignada en esta actividad!', 'aura' ); ?></span>
            </div>

            <!-- Grid de Programa, Materia, Horario y Aula -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #e2e8f0); border-radius: 12px; padding: 12px 14px;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--aura-text-muted, #64748b); display: block;">🎓 <?php esc_html_e( 'Programa', 'aura' ); ?></span>
                    <strong id="det-program" style="color: var(--aura-text-primary, #0f172a); font-size: 13px;">—</strong>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--aura-text-muted, #64748b); display: block;">📚 <?php esc_html_e( 'Materia', 'aura' ); ?></span>
                    <strong id="det-subject" style="color: var(--aura-text-primary, #0f172a); font-size: 13px;">—</strong>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--aura-text-muted, #64748b); display: block;">🕐 <?php esc_html_e( 'Horario', 'aura' ); ?></span>
                    <strong id="det-time" style="color: var(--aura-text-primary, #0f172a); font-size: 13px;">—</strong>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--aura-text-muted, #64748b); display: block;">📍 <?php esc_html_e( 'Aula / Salón', 'aura' ); ?></span>
                    <strong id="det-location" style="color: var(--aura-text-primary, #0f172a); font-size: 13px;">—</strong>
                </div>
            </div>

            <!-- Botón / Caja de Clase Virtual (si aplica) -->
            <div id="row-det-online" style="display: none; padding: 12px 14px; border-radius: 10px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.22); align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                <div>
                    <div style="font-size: 12.5px; font-weight: 700; color: var(--aura-primary, #4f46e5);">💻 <?php esc_html_e( 'Sesión Virtual en Línea', 'aura' ); ?></div>
                    <div style="font-size: 11.5px; color: var(--aura-text-secondary, #64748b);"><?php esc_html_e( 'Accede a la sala de videoconferencia asignada', 'aura' ); ?></div>
                </div>
                <a id="det-online-btn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-indigo btn-lift" style="font-size: 12px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                    🚀 <?php esc_html_e( 'Unirse a la Sesión', 'aura' ); ?>
                </a>
            </div>

            <!-- Estudiantes Líderes / Responsables -->
            <div id="row-det-leaders" style="display: none; border-top: 1px solid var(--aura-border, #e2e8f0); padding-top: 12px;">
                <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--aura-text-muted, #64748b); display: block; margin-bottom: 8px;">
                    ⭐ <?php esc_html_e( 'Estudiantes con Responsabilidad en la Actividad', 'aura' ); ?>
                </span>
                <div id="det-leaders" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
            </div>

            <!-- Detalle / Temario / Descripción -->
            <div id="box-det-desc" style="display: none; font-size: 13px; color: var(--aura-text-secondary); background: var(--aura-surface-alt, #f8fafc); border-left: 3px solid var(--aura-primary, #6366f1); padding: 10px 14px; border-radius: 0 8px 8px 0; white-space: pre-wrap; line-height: 1.55;"></div>

            <!-- Calendario & Invitaciones (Fase 7) -->
            <div id="box-det-calendar-actions" style="border-top: 1px solid var(--aura-border, #e2e8f0); padding-top: 12px; margin-top: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--aura-text-muted, #64748b); display: flex; align-items: center; gap: 5px;">
                        📅 <?php esc_html_e( 'Sincronizar con Calendario Personal', 'aura' ); ?>
                    </span>
                    <?php if ( current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                        <button type="button" id="btn-det-open-email-invite" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 5px; font-weight: 600;">
                            ✉️ <?php esc_html_e( 'Enviar Invitación a Profesores', 'aura' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a id="btn-det-add-google" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="font-size: 11.5px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                        <svg width="13" height="13" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                        <?php esc_html_e( 'Google Calendar', 'aura' ); ?>
                    </a>
                    <a id="btn-det-add-outlook" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="font-size: 11.5px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                        <svg width="13" height="13" viewBox="0 0 24 24"><path fill="#0078D4" d="M24 7.25v9.5c0 .97-.78 1.75-1.75 1.75H14V5.5h8.25c.97 0 1.75.78 1.75 1.75z"/><path fill="#28A8EA" d="M14 5.5H1.75C.78 5.5 0 6.28 0 7.25v9.5c0 .97.78 1.75 1.75 1.75H14V5.5z"/><path fill="#002050" d="M8.5 8h-3c-.28 0-.5.22-.5.5v7c0 .28.22.5.5.5h3c1.93 0 3.5-1.57 3.5-3.5v-1c0-1.93-1.57-3.5-3.5-3.5zm1.5 4.5c0 1.1-.9 2-2 2H7v-5h1c1.1 0 2 .9 2 2v1z"/></svg>
                        <?php esc_html_e( 'Outlook / 365', 'aura' ); ?>
                    </a>
                    <a id="btn-det-download-ics" href="#" download class="btn btn-secondary btn-sm" style="font-size: 11.5px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                        <span>📥</span> <?php esc_html_e( 'Descargar .ics (Apple / iCal)', 'aura' ); ?>
                    </a>
                </div>
            </div>

        </div>

        <!-- Footer del Modal -->
        <div class="aura-modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--aura-border, #e2e8f0); background: var(--aura-surface-alt, #f8fafc); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div id="det-actions-left" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <?php if ( current_user_can( 'aura_cal_take_attendance' ) || current_user_can( 'aura_take_attendance' ) || current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-emerald btn-lift" id="btn-det-take-attendance" style="font-size: 12.5px; padding: 6px 14px;">
                        📋 <?php esc_html_e( 'Control de Asistencia', 'aura' ); ?>
                    </button>
                <?php endif; ?>

                <?php if ( current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-det-copy" title="<?php esc_attr_e( 'Copiar evento al portapapeles para pegarlo en otra celda', 'aura' ); ?>" style="font-size: 12px; padding: 6px 10px; display: inline-flex; align-items: center; gap: 4px;">
                        <span>📋</span> <span><?php esc_html_e( 'Copiar', 'aura' ); ?></span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-det-clone" title="<?php esc_attr_e( 'Clonar este evento exactamente igual de forma inmediata', 'aura' ); ?>" style="font-size: 12px; padding: 6px 10px; display: inline-flex; align-items: center; gap: 4px;">
                        <span>⚡</span> <span><?php esc_html_e( 'Clonar', 'aura' ); ?></span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-det-repeat" title="<?php esc_attr_e( 'Repetir en el mismo día y horario entre fecha inicio y fin', 'aura' ); ?>" style="font-size: 12px; padding: 6px 10px; display: inline-flex; align-items: center; gap: 4px;">
                        <span>🔁</span> <span><?php esc_html_e( 'Repetir Serie', 'aura' ); ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <div id="det-actions-right" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <?php if ( current_user_can( 'aura_cal_delete_events' ) || current_user_can( 'aura_delete_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-ghost" id="btn-det-delete" style="color: #ef4444; font-size: 12.5px; padding: 6px 12px;">
                        🗑️ <?php esc_html_e( 'Eliminar', 'aura' ); ?>
                    </button>
                <?php endif; ?>

                <?php if ( current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_cal_create_events' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-indigo btn-lift" id="btn-det-edit" style="font-size: 12.5px; padding: 6px 14px;">
                        ✏️ <?php esc_html_e( 'Editar Evento', 'aura' ); ?>
                    </button>
                <?php endif; ?>

                <button type="button" class="btn btn-ghost btn-close-evt-detail" data-close-modal="#modal-event-detail" style="font-size: 12.5px; padding: 6px 14px;">
                    <?php esc_html_e( 'Cerrar', 'aura' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL SECUNDARIO: REPETIR EVENTO EN SERIE
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-repeat-series" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 1000000; align-items: center; justify-content: center; padding: 16px;">
    <div class="aura-modal-container" style="max-width: 500px; width: 100%; border-radius: 16px; overflow: hidden; background: var(--aura-surface-card, #ffffff); border: 1px solid var(--aura-border, #cbd5e1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
        <div class="aura-modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--aura-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; background: var(--aura-surface-alt, #f8fafc);">
            <h3 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--aura-text-primary, #0f172a); display: flex; align-items: center; gap: 8px;">
                🔁 <?php esc_html_e( 'Repetir Evento en Serie', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-repeat-series" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--aura-text-muted, #64748b); line-height: 1; padding: 2px 6px;">&times;</button>
        </div>
        <form id="form-repeat-series" style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
            <input type="hidden" id="repeat-event-id" value="0">
            <div id="repeat-event-summary" style="margin: 0; font-size: 13px; color: var(--aura-text-primary, #0f172a); background: rgba(99,102,241,0.08); padding: 10px 14px; border-radius: 10px; border-left: 3px solid #6366f1; line-height: 1.45;"></div>

            <div class="form-group">
                <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 6px; color: var(--aura-text-primary, #0f172a);">
                    <?php esc_html_e( 'Frecuencia de Repetición:', 'aura' ); ?>
                </label>
                <select id="repeat-frequency" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px; font-size: 13px;">
                    <option value="weekly"><?php esc_html_e( 'Semanal (mismo día de la semana y mismo horario)', 'aura' ); ?></option>
                    <option value="daily"><?php esc_html_e( 'Diario (todos los días consecutivos)', 'aura' ); ?></option>
                    <option value="weekdays"><?php esc_html_e( 'Días hábiles (Lunes a Viernes)', 'aura' ); ?></option>
                    <option value="biweekly"><?php esc_html_e( 'Quincenal (cada 2 semanas)', 'aura' ); ?></option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px; color: var(--aura-text-primary, #0f172a);"><?php esc_html_e( 'Desde (Inicio de serie)', 'aura' ); ?></label>
                    <input type="date" id="repeat-date-start" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 10px; font-size: 13px;" required>
                </div>
                <div class="form-group">
                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px; color: var(--aura-text-primary, #0f172a);"><?php esc_html_e( 'Hasta (Fecha Fin)', 'aura' ); ?></label>
                    <input type="date" id="repeat-date-end" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 10px; font-size: 13px;" required>
                </div>
            </div>

            <div style="font-size: 12px; color: var(--aura-text-muted, #64748b); line-height: 1.45;">
                💡 <?php esc_html_e( 'Se generarán eventos con el mismo horario, profesores, materia y aula vinculados como una serie.', 'aura' ); ?>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-repeat-series" style="font-size: 12.5px; padding: 6px 14px;">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-lift" id="btn-submit-repeat-series" style="font-size: 12.5px; padding: 6px 16px;">
                    🔁 <?php esc_html_e( 'Generar Repeticiones', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     BARRA FLOTANTE DEL PORTAPAPELES (COPIAR Y PEGAR EVENTOS)
     ══════════════════════════════════════════════════════════════════ -->
<div id="aura-calendar-clipboard-bar" style="display: none; position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #1e1b4b; color: #ffffff; padding: 10px 20px; border-radius: 30px; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.5), 0 0 0 1px rgba(99,102,241,0.5); z-index: 999999; align-items: center; gap: 14px; font-size: 13px;">
    <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 16px;">📋</span>
        <span><strong><?php esc_html_e( 'Evento copiado:', 'aura' ); ?></strong> <span id="clipboard-bar-event-title" style="color: #a5b4fc; font-weight: 600;">-</span></span>
    </div>
    <div style="font-size: 12px; opacity: 0.85; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 10px;">
        <?php esc_html_e( 'Haz clic en cualquier fecha/hora para pegarlo', 'aura' ); ?>
    </div>
    <button type="button" id="btn-clipboard-cancel" style="background: rgba(255,255,255,0.15); border: none; color: #ffffff; border-radius: 16px; padding: 3px 10px; font-size: 11px; cursor: pointer; transition: background 0.15s ease;" title="<?php esc_attr_e( 'Descartar evento copiado', 'aura' ); ?>">
        ✕ <?php esc_html_e( 'Cancelar', 'aura' ); ?>
    </button>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL TERCIARIO: ENVIAR INVITACIÓN POR CORREO (FASE 7)
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-send-invitation" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 1000000; align-items: center; justify-content: center; padding: 16px;">
    <div class="aura-modal-container" style="max-width: 520px; width: 100%; border-radius: 16px; overflow: hidden; background: var(--aura-surface-card, #ffffff); border: 1px solid var(--aura-border, #cbd5e1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
        <div class="aura-modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--aura-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; background: var(--aura-surface-alt, #f8fafc);">
            <h3 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--aura-text-primary, #0f172a); display: flex; align-items: center; gap: 8px;">
                ✉️ <?php esc_html_e( 'Enviar Invitación de Calendario', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-send-invitation" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--aura-text-muted, #64748b); line-height: 1; padding: 2px 6px;">&times;</button>
        </div>
        <form id="form-send-invitation" style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
            <input type="hidden" id="invite-event-id" value="0">
            <div id="invite-event-summary" style="margin: 0; font-size: 13px; color: var(--aura-text-primary, #0f172a); background: rgba(99,102,241,0.08); padding: 10px 14px; border-radius: 10px; border-left: 3px solid #6366f1; line-height: 1.45;"></div>

            <div class="form-group">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--aura-text-primary, #0f172a); margin-bottom: 4px;">
                    👥 <?php esc_html_e( 'Destinatarios (Profesores asignados)', 'aura' ); ?>
                </label>
                <div id="invite-recipients-list" style="font-size: 12.5px; color: var(--aura-text-secondary, #475569); background: var(--aura-surface-alt, #f8fafc); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--aura-border, #e2e8f0); min-height: 38px; display: flex; flex-direction: column; gap: 4px;">
                    <em><?php esc_html_e( 'Cargando instructores...', 'aura' ); ?></em>
                </div>
            </div>

            <div class="form-group">
                <label for="invite-custom-emails" style="display: block; font-size: 12px; font-weight: 600; color: var(--aura-text-primary, #0f172a); margin-bottom: 4px;">
                    ➕ <?php esc_html_e( 'Correos Adicionales (opcional, separados por coma)', 'aura' ); ?>
                </label>
                <input type="text" id="invite-custom-emails" class="form-control" placeholder="ejemplo1@dominio.com, ejemplo2@dominio.com" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--aura-border, #cbd5e1); font-size: 13px;">
            </div>

            <div class="form-group">
                <label for="invite-custom-note" style="display: block; font-size: 12px; font-weight: 600; color: var(--aura-text-primary, #0f172a); margin-bottom: 4px;">
                    📝 <?php esc_html_e( 'Nota Personalizada o Indicaciones (opcional)', 'aura' ); ?>
                </label>
                <textarea id="invite-custom-note" class="form-control" rows="3" placeholder="<?php esc_attr_e( 'Mensaje especial para los profesores que acompañará la invitación...', 'aura' ); ?>" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--aura-border, #cbd5e1); font-size: 13px; resize: vertical;"></textarea>
            </div>

            <div style="font-size: 12px; color: var(--aura-text-muted, #64748b); line-height: 1.45; background: rgba(16, 185, 129, 0.08); border-left: 3px solid #10b981; padding: 8px 12px; border-radius: 0 8px 8px 0;">
                📎 <?php esc_html_e( 'El correo incluirá el archivo estándar .ics adjunto y botones de acceso directo para Google Calendar y Outlook.', 'aura' ); ?>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-send-invitation" style="font-size: 12.5px; padding: 6px 14px;">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-lift" id="btn-submit-send-invite" style="font-size: 12.5px; padding: 6px 16px; display: inline-flex; align-items: center; gap: 6px;">
                    <span id="btn-invite-text">🚀 <?php esc_html_e( 'Enviar Invitaciones', 'aura' ); ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

