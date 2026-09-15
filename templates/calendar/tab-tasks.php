<?php
/**
 * Tab 4: Tareas y Evaluaciones Académicas
 *
 * Publicación de actividades, tareas y proyectos por programa/materia,
 * recepción de entregas estudiantiles y calificación.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tasks = Aura_Calendar_Tasks::get_tasks();
$programs = Aura_Calendar_Programs::get_all( [ 'status' => 'active', 'limit' => 100 ] );
?>

<div class="aura-tasks-view-container">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 class="adp-card-title" style="font-size: 20px; margin: 0;">
                📝 <?php esc_html_e( 'Tareas, Trabajos y Entregas', 'aura' ); ?>
            </h2>
            <p class="adp-card-desc" style="margin: 4px 0 0 0;">
                <?php esc_html_e( 'Asigna lecturas, ensayos y proyectos evaluables para los estudiantes de cada programa.', 'aura' ); ?>
            </p>
        </div>

        <?php if ( current_user_can( 'aura_teach_calendar' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) : ?>
            <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-create-task">
                ➕ <?php esc_html_e( 'Nueva Tarea', 'aura' ); ?>
            </button>
        <?php endif; ?>
    </div>

    <!-- ── LISTA DE TAREAS ── -->
    <?php if ( empty( $tasks ) ) : ?>
        <div class="adp-card" style="text-align: center; padding: 48px 24px; border-radius: 12px;">
            <div style="font-size: 40px; margin-bottom: 12px;">📝</div>
            <h3 style="font-size: 18px; margin-bottom: 6px;"><?php esc_html_e( 'No hay tareas registradas', 'aura' ); ?></h3>
            <p style="color: var(--aura-text-secondary); max-width: 460px; margin: 0 auto 20px;">
                <?php esc_html_e( 'Crea una tarea o trabajo práctico para que los estudiantes puedan entregar sus respuestas desde el portal.', 'aura' ); ?>
            </p>
            <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-create-first-task">
                ➕ <?php esc_html_e( 'Crear Primera Tarea', 'aura' ); ?>
            </button>
        </div>
    <?php else : ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px;">
            <?php foreach ( $tasks as $t ) : ?>
                <div class="adp-card task-card" style="padding: 20px; border-radius: 12px; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                            <span class="adp-badge badge-indigo">
                                <?php echo esc_html( $t->program_code ?: $t->program_name ); ?>
                            </span>
                            <?php if ( $t->status === 'published' ) : ?>
                                <span class="adp-badge badge-emerald has-dot"><span class="pulse-dot"></span> <?php esc_html_e( 'Publicada', 'aura' ); ?></span>
                            <?php elseif ( $t->status === 'closed' ) : ?>
                                <span class="adp-badge badge-slate"><?php esc_html_e( 'Cerrada', 'aura' ); ?></span>
                            <?php else : ?>
                                <span class="adp-badge badge-amber"><?php esc_html_e( 'Borrador', 'aura' ); ?></span>
                            <?php endif; ?>
                        </div>

                        <h3 style="font-size: 17px; font-weight: 700; margin: 0 0 6px 0; color: var(--aura-text-primary);">
                            <?php echo esc_html( $t->title ); ?>
                        </h3>

                        <?php if ( ! empty( $t->subject_name ) ) : ?>
                            <div style="font-size: 12px; font-weight: 600; color: var(--aura-text-secondary); margin-bottom: 8px;">
                                📚 <?php echo esc_html( $t->subject_name ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $t->description ) ) : ?>
                            <p style="font-size: 13px; color: var(--aura-text-secondary); margin: 0 0 14px 0; max-height: 60px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo esc_html( wp_strip_all_tags( $t->description ) ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div style="border-top: 1px solid var(--aura-border); padding-top: 12px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 12px; color: var(--aura-text-muted);">
                            ⏰ <?php esc_html_e( 'Vence:', 'aura' ); ?> <strong><?php echo esc_html( date_i18n( 'j M H:i', strtotime( $t->due_datetime ) ) ); ?></strong>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn btn-ghost btn-view-submissions" data-task-id="<?php echo esc_attr( $t->id ); ?>" data-task-title="<?php echo esc_attr( $t->title ); ?>" style="font-size: 12px; padding: 5px 10px;">
                                📥 <?php echo intval( $t->submissions_count ); ?> <?php esc_html_e( 'Entregas', 'aura' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: CREAR / EDITAR TAREA
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-task-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 600px;">
        <div class="aura-modal-header">
            <h3 id="modal-task-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                📝 <?php esc_html_e( 'Nueva Tarea / Evaluación', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-task-editor">&times;</button>
        </div>

        <form id="form-task-editor" style="padding: 20px 24px;">
            <input type="hidden" name="id" id="tsk-id" value="0">

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        <?php esc_html_e( 'Título de la Tarea', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="title" id="tsk-title" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Ensayo sobre la Gracia y la Redención', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            🎓 <?php esc_html_e( 'Programa', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="program_id" id="tsk-prog-id" required class="form-control" style="width: 100%; border-radius: 8px;">
                            <option value=""><?php esc_html_e( 'Seleccionar...', 'aura' ); ?></option>
                            <?php foreach ( $programs as $p ) : ?>
                                <option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            📚 <?php esc_html_e( 'Materia', 'aura' ); ?>
                        </label>
                        <select name="subject_id" id="tsk-subj-id" class="form-control" style="width: 100%; border-radius: 8px;">
                            <option value=""><?php esc_html_e( 'General / Opcional', 'aura' ); ?></option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            ⏰ <?php esc_html_e( 'Fecha y Hora Límite', 'aura' ); ?>
                        </label>
                        <input type="datetime-local" name="due_datetime" id="tsk-due" class="form-control" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Puntaje Máximo', 'aura' ); ?>
                        </label>
                        <input type="number" step="0.1" name="max_score" id="tsk-max-score" value="100" class="form-control" style="width: 100%; border-radius: 8px;">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        <?php esc_html_e( 'Instrucciones y Requisitos', 'aura' ); ?>
                    </label>
                    <textarea name="description" id="tsk-desc" rows="3" class="form-control" placeholder="<?php esc_attr_e( 'Escribe detalladamente las instrucciones de la tarea...', 'aura' ); ?>" style="width: 100%; border-radius: 8px;"></textarea>
                </div>
            </div>

            <div class="aura-modal-footer" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-task-editor">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-task">
                    💾 <?php esc_html_e( 'Guardar Tarea', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: ENTREGAS DE TAREA RECIBIDAS
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-task-submissions" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 740px;">
        <div class="aura-modal-header">
            <div>
                <h3 id="subs-modal-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                    📥 <?php esc_html_e( 'Entregas Recibidas', 'aura' ); ?>
                </h3>
            </div>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-task-submissions">&times;</button>
        </div>

        <div style="padding: 16px 24px; max-height: 480px; overflow-y: auto;">
            <div id="subs-container">
                <!-- Inyectado vía AJAX -->
            </div>
        </div>
    </div>
</div>
