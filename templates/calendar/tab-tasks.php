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

global $wpdb;
$library_table = $wpdb->prefix . 'aura_library_books';
$books = [];
if ( $wpdb->get_var( "SHOW TABLES LIKE '$library_table'" ) === $library_table ) {
    $books = $wpdb->get_results( "SELECT id, title, author, isbn, call_number FROM {$library_table} ORDER BY title ASC LIMIT 300" );
}
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

        <?php if ( current_user_can( 'aura_cal_manage_tasks' ) || current_user_can( 'aura_teach_calendar' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) : ?>
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
            <?php if ( current_user_can( 'aura_cal_manage_tasks' ) || current_user_can( 'aura_teach_calendar' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) : ?>
                <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-create-first-task">
                    ➕ <?php esc_html_e( 'Crear Primera Tarea', 'aura' ); ?>
                </button>
            <?php endif; ?>
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

                        <?php if ( ! empty( $t->book_title ) ) : ?>
                            <div style="display: flex; align-items: center; gap: 8px; background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); border-radius: 8px; padding: 8px 10px; margin-bottom: 10px;">
                                <span style="font-size: 18px;">📖</span>
                                <div style="font-size: 12px; line-height: 1.3;">
                                    <strong style="color: var(--aura-text-primary);"><?php echo esc_html( $t->book_title ); ?></strong>
                                    <?php if ( ! empty( $t->book_author ) ) : ?>
                                        <div style="color: var(--aura-text-muted); font-size: 11px;"><?php echo esc_html( $t->book_author ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $t->submission_type ) && $t->submission_type === 'text_only' ) : ?>
                            <div style="font-size: 11px; color: #6366f1; font-weight: 600; margin-bottom: 8px;">
                                ✍️ <?php esc_html_e( 'Entrega por escrito en plataforma', 'aura' ); ?>
                                <?php if ( ! empty( $t->min_words ) ) : ?>
                                    (mín. <?php echo intval( $t->min_words ); ?> palabras)
                                <?php endif; ?>
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
    <div class="aura-modal-container" style="max-width: 620px;">
        <div class="aura-modal-header">
            <h3 id="modal-task-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                📝 <?php esc_html_e( 'Nueva Tarea / Evaluación', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-task-editor">&times;</button>
        </div>

        <form id="form-task-editor" class="aura-modal-form">
            <div class="aura-modal-body">
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

                    <!-- Vincular con Libro de Biblioteca -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            📖 <?php esc_html_e( 'Libro Asociado de la Biblioteca (Opcional para Resumen / Control de Lectura)', 'aura' ); ?>
                        </label>
                        <select name="book_id" id="tsk-book-id" class="form-control" style="width: 100%; border-radius: 8px;">
                            <option value=""><?php esc_html_e( '-- Ninguno (Tarea Estándar) --', 'aura' ); ?></option>
                            <?php foreach ( $books as $bk ) : ?>
                                <option value="<?php echo esc_attr( $bk->id ); ?>">
                                    <?php echo esc_html( $bk->title . ( $bk->author ? ' — ' . $bk->author : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--aura-text-muted); font-size: 11px; display: block; margin-top: 4px;">
                            <?php esc_html_e( 'Si asocias un libro, los estudiantes verán la ficha del ejemplar y podrán redactar y enviar su resumen directamente en el portal.', 'aura' ); ?>
                        </small>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                ✍️ <?php esc_html_e( 'Modalidad de Entrega', 'aura' ); ?>
                            </label>
                            <select name="submission_type" id="tsk-submission-type" class="form-control" style="width: 100%; border-radius: 8px;">
                                <option value="text_or_file"><?php esc_html_e( 'Texto Escrito o Archivo (Flexible)', 'aura' ); ?></option>
                                <option value="text_only"><?php esc_html_e( 'Solo Resumen Escrito en Plataforma', 'aura' ); ?></option>
                                <option value="file_only"><?php esc_html_e( 'Solo Archivo Adjunto (PDF / Doc)', 'aura' ); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                📏 <?php esc_html_e( 'Palabras Mínimas (0 = Sin límite)', 'aura' ); ?>
                            </label>
                            <input type="number" name="min_words" id="tsk-min-words" value="0" min="0" step="10" class="form-control" placeholder="Ej: 300" style="width: 100%; border-radius: 8px;">
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
            </div>

            <div class="aura-modal-footer">
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
