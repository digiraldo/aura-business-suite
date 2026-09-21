<?php
/**
 * Template: Mis Tareas y Controles de Lectura — Portal del Estudiante
 *
 * Muestra las tareas publicadas, libros asociados de la biblioteca institucional,
 * estado de entrega, redactor de resumen escrito en plataforma y calificaciones docentes.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_tasks = $wpdb->prefix . 'aura_cal_tasks';
$table_subs  = $wpdb->prefix . 'aura_cal_task_submissions';
$table_books = $wpdb->prefix . 'aura_library_books';
$table_subj  = $wpdb->prefix . 'aura_cal_subjects';
$table_prog  = $wpdb->prefix . 'aura_cal_programs';

$table_areas = $wpdb->prefix . 'aura_cal_areas';

$student_id = intval( $student->id ?? 0 );

$has_tasks_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_tasks}'" ) === $table_tasks;
$has_books_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_books}'" ) === $table_books;
$has_areas_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_areas}'" ) === $table_areas;

$tasks = [];
if ( $has_tasks_table ) {
    $book_join = $has_books_table ? "LEFT JOIN {$table_books} b ON b.id = t.book_id" : "";
    $book_cols = $has_books_table ? ", b.title AS book_title, b.author AS book_author, b.isbn AS book_isbn, b.dewey_number AS book_dewey, b.cover_image_id" : "";
    $area_join = $has_areas_table ? "LEFT JOIN {$table_areas} a ON (a.id = s.area_id OR a.id = p.area_id)" : "";
    $area_col  = $has_areas_table ? ", a.name AS area_name" : "";

    $raw_tasks = $wpdb->get_results( $wpdb->prepare(
        "SELECT t.*,
                p.name AS program_name, p.code AS program_code,
                s.name AS subject_name, s.code AS subject_code
                {$area_col}
                {$book_cols},
                u.display_name AS created_by_name,
                prof.display_name AS teacher_name,
                sub.id AS submission_id, sub.status AS submission_status, sub.submission_text, sub.attachment_urls, sub.score, sub.feedback, sub.submitted_at
         FROM {$table_tasks} t
         LEFT JOIN {$table_prog} p ON p.id = t.program_id
         LEFT JOIN {$table_subj} s ON s.id = t.subject_id
         LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
         LEFT JOIN {$wpdb->users} prof ON prof.ID = s.teacher_id
         {$area_join}
         {$book_join}
         LEFT JOIN {$table_subs} sub ON (sub.task_id = t.id AND sub.student_id = %d)
         WHERE t.status = 'published' AND t.deleted_at IS NULL
         ORDER BY (sub.id IS NOT NULL) ASC, t.due_datetime ASC",
        $student_id
    ) );

    // Filtrar tareas que le corresponden al estudiante según modalidad y mapear libros diferenciados
    $assigned_tasks = [];
    foreach ( (array) $raw_tasks as $tsk ) {
        $target_type = $tsk->target_type ?: 'all';
        $target_ids  = ! empty( $tsk->target_student_ids ) ? json_decode( $tsk->target_student_ids, true ) : [];
        if ( ! is_array( $target_ids ) ) $target_ids = [];
        $assignments = ! empty( $tsk->student_assignments ) ? json_decode( $tsk->student_assignments, true ) : [];
        if ( ! is_array( $assignments ) ) $assignments = [];

        if ( $target_type === 'individual' ) {
            if ( ! in_array( $student_id, array_map( 'intval', $target_ids ), true ) ) {
                continue;
            }
        } elseif ( $target_type === 'differentiated' ) {
            if ( ! isset( $assignments[ $student_id ] ) && ! isset( $assignments[ (string) $student_id ] ) ) {
                continue;
            }
            $my_diff = $assignments[ $student_id ] ?? ( $assignments[ (string) $student_id ] ?? [] );
            if ( ! empty( $my_diff['book_id'] ) ) {
                $tsk->book_id     = (int) $my_diff['book_id'];
                $tsk->book_title  = $my_diff['book_title'] ?? $tsk->book_title;
                $tsk->book_author = $my_diff['book_author'] ?? $tsk->book_author;
                if ( $has_books_table && ! empty( $tsk->book_id ) ) {
                    $bk_row = $wpdb->get_row( $wpdb->prepare( "SELECT cover_image_id, isbn, dewey_number FROM {$table_books} WHERE id = %d", $tsk->book_id ) );
                    if ( $bk_row ) {
                        $tsk->cover_image_id = $bk_row->cover_image_id;
                        $tsk->book_isbn      = $bk_row->isbn;
                        $tsk->book_dewey     = $bk_row->dewey_number;
                    }
                }
            }
            if ( ! empty( $my_diff['instructions'] ) ) {
                $tsk->individual_instructions = $my_diff['instructions'];
            }
        }

        $assigned_tasks[] = $tsk;
    }
    $tasks = $assigned_tasks;
}
?>

<div class="aura-student-tasks-wrapper" style="margin-top: 10px;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h3 class="adp-card-title" style="font-size: 18px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-welcome-write-blog" style="color: #6366f1;"></span>
                <span><?php esc_html_e( 'Mis Tareas, Lecturas y Evaluaciones', 'aura-suite' ); ?></span>
            </h3>
            <p class="adp-card-desc" style="margin: 4px 0 0 0; font-size: 13px;">
                <?php esc_html_e( 'Consulta las lecturas asignadas por tus instructores, redacta tus resúmenes y revisa tus calificaciones.', 'aura-suite' ); ?>
            </p>
        </div>
        <div style="font-size: 12px; color: var(--aura-text-muted);">
            📋 <?php echo count( $tasks ); ?> <?php esc_html_e( 'actividades asignadas a ti', 'aura-suite' ); ?>
        </div>
    </div>

    <?php if ( empty( $tasks ) ) : ?>
        <div class="adp-card" style="text-align: center; padding: 48px 24px; border-radius: 12px; background: var(--aura-surface); border: 1px solid var(--aura-border);">
            <div style="font-size: 40px; margin-bottom: 12px;">🎉</div>
            <h4 style="font-size: 17px; margin-bottom: 6px; color: var(--aura-text-primary);">
                <?php esc_html_e( '¡Estás al día con tus actividades!', 'aura-suite' ); ?>
            </h4>
            <p style="color: var(--aura-text-secondary); max-width: 440px; margin: 0 auto; font-size: 13px;">
                <?php esc_html_e( 'No tienes tareas pendientes ni controles de lectura asignados en este momento.', 'aura-suite' ); ?>
            </p>
        </div>
    <?php else : ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 18px;">
            <?php foreach ( $tasks as $tsk ) :
                $is_submitted = ! empty( $tsk->submission_id );
                $is_graded    = $is_submitted && ( $tsk->submission_status === 'graded' || $tsk->score !== null );
                $is_expired   = ! empty( $tsk->due_datetime ) && ( strtotime( current_time( 'mysql' ) ) > strtotime( $tsk->due_datetime ) );

                $cover_url = '';
                if ( ! empty( $tsk->cover_image_id ) ) {
                    $cover_url = wp_get_attachment_image_url( (int) $tsk->cover_image_id, 'thumbnail' );
                }
                $recipient_name = $tsk->teacher_name ?: ( $tsk->created_by_name ?: __( 'Profesor Titular', 'aura-suite' ) );
            ?>
                <div class="adp-card" style="padding: 20px; border-radius: 12px; background: var(--aura-surface); border: 1px solid var(--aura-border); display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <!-- Encabezado de la Tarjeta -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <span class="adp-badge badge-indigo">
                                    🎓 <?php echo esc_html( $tsk->program_code ?: ( $tsk->program_name ?: 'Programa' ) ); ?>
                                </span>
                                <?php if ( ! empty( $tsk->area_name ) ) : ?>
                                    <span class="adp-badge badge-slate" style="font-size: 11px;">
                                        🏛️ <?php echo esc_html( $tsk->area_name ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ( $is_graded ) : ?>
                                <span class="adp-badge badge-emerald has-dot">
                                    <span class="pulse-dot"></span> <?php printf( esc_html__( 'Nota: %s / %s', 'aura-suite' ), esc_html( (string) $tsk->score ), esc_html( (string) $tsk->max_score ) ); ?>
                                </span>
                            <?php elseif ( $is_submitted ) : ?>
                                <span class="adp-badge badge-indigo">
                                    <?php esc_html_e( 'Entregada (En Revisión)', 'aura-suite' ); ?>
                                </span>
                            <?php elseif ( $is_expired ) : ?>
                                <span class="adp-badge badge-slate" style="color: #ef4444; border-color: rgba(239,68,68,0.3);">
                                    ⚠️ <?php esc_html_e( 'Plazo Vencido', 'aura-suite' ); ?>
                                </span>
                            <?php else : ?>
                                <span class="adp-badge badge-amber has-dot">
                                    <span class="pulse-dot"></span> <?php esc_html_e( 'Pendiente', 'aura-suite' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h4 style="font-size: 16px; font-weight: 700; margin: 0 0 6px 0; color: var(--aura-text-primary); line-height: 1.3;">
                            <?php echo esc_html( $tsk->title ); ?>
                        </h4>

                        <!-- Destinatario / Docente a quien entregar -->
                        <div style="font-size: 12px; color: var(--aura-text-secondary); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <span>👤 <strong><?php esc_html_e( 'Entregar a:', 'aura-suite' ); ?></strong></span>
                            <span><?php echo esc_html( $recipient_name ); ?></span>
                        </div>

                        <?php if ( ! empty( $tsk->subject_name ) ) : ?>
                            <div style="font-size: 12px; font-weight: 600; color: var(--aura-text-secondary); margin-bottom: 8px;">
                                📚 <?php echo esc_html( $tsk->subject_name ); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Ficha de Libro Asignado de la Biblioteca -->
                        <?php if ( ! empty( $tsk->book_title ) ) : ?>
                            <div style="background: rgba(99,102,241,0.07); border: 1px solid rgba(99,102,241,0.25); border-radius: 8px; padding: 10px; margin-bottom: 12px; display: flex; gap: 10px; align-items: center;">
                                <?php if ( $cover_url ) : ?>
                                    <img src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $tsk->book_title ); ?>" style="width: 42px; height: 56px; border-radius: 4px; object-fit: cover; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                                <?php else : ?>
                                    <div style="width: 42px; height: 56px; border-radius: 4px; background: rgba(99,102,241,0.2); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                                        📖
                                    </div>
                                <?php endif; ?>
                                <div style="font-size: 12px; line-height: 1.3;">
                                    <div style="font-size: 10px; text-transform: uppercase; font-weight: 700; color: #6366f1; letter-spacing: 0.5px;"><?php esc_html_e( 'Tu Lectura Asignada', 'aura-suite' ); ?></div>
                                    <strong style="color: var(--aura-text-primary); display: block; margin-top: 1px;"><?php echo esc_html( $tsk->book_title ); ?></strong>
                                    <?php if ( ! empty( $tsk->book_author ) ) : ?>
                                        <div style="color: var(--aura-text-muted); font-size: 11px;"><?php echo esc_html( $tsk->book_author ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $tsk->book_dewey ) ) : ?>
                                        <div style="color: var(--aura-text-muted); font-size: 10px; font-family: monospace;">Dewey: <?php echo esc_html( $tsk->book_dewey ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Instrucción específica para el estudiante (si la tarea es diferenciada) -->
                        <?php if ( ! empty( $tsk->individual_instructions ) ) : ?>
                            <div style="background: rgba(99,102,241,0.08); border-left: 3px solid #6366f1; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; font-size: 12px;">
                                <strong style="color: #6366f1;">🎯 <?php esc_html_e( 'Instrucción específica para ti:', 'aura-suite' ); ?></strong>
                                <span style="color: var(--aura-text-primary); display: block; margin-top: 2px;"><?php echo esc_html( $tsk->individual_instructions ); ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Modalidad de Entrega y Exigencia -->
                        <?php if ( ! empty( $tsk->submission_type ) && $tsk->submission_type === 'text_only' ) : ?>
                            <div style="font-size: 11px; color: #6366f1; font-weight: 600; margin-bottom: 8px;">
                                ✍️ <?php esc_html_e( 'Exige Resumen Escrito en Plataforma', 'aura-suite' ); ?>
                                <?php if ( ! empty( $tsk->min_words ) ) : ?>
                                    (mín. <?php echo intval( $tsk->min_words ); ?> palabras)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $tsk->description ) ) : ?>
                            <p style="font-size: 13px; color: var(--aura-text-secondary); margin: 0 0 12px 0; max-height: 48px; overflow: hidden; text-overflow: ellipsis; line-height: 1.4;">
                                <?php echo esc_html( wp_strip_all_tags( $tsk->description ) ); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Si ya tiene feedback del profesor -->
                        <?php if ( ! empty( $tsk->feedback ) ) : ?>
                            <div style="background: rgba(16,185,129,0.08); border-left: 3px solid #10b981; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; font-size: 12px;">
                                <strong style="color: #10b981;"><?php esc_html_e( 'Retroalimentación Docente:', 'aura-suite' ); ?></strong>
                                <span style="color: var(--aura-text-primary); display: block; margin-top: 2px;"><?php echo esc_html( $tsk->feedback ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="border-top: 1px solid var(--aura-border); padding-top: 12px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div style="font-size: 11px; color: var(--aura-text-muted);">
                            ⏰ <?php esc_html_e( 'Vence:', 'aura-suite' ); ?> <strong><?php echo esc_html( date_i18n( 'j M H:i', strtotime( $tsk->due_datetime ) ) ); ?></strong>
                        </div>

                        <button type="button"
                                class="btn <?php echo $is_graded ? 'btn-ghost' : ( $is_submitted ? 'btn-ghost' : 'btn-indigo btn-shimmer' ); ?> btn-student-open-submission"
                                data-task-id="<?php echo esc_attr( $tsk->id ); ?>"
                                data-task-title="<?php echo esc_attr( $tsk->title ); ?>"
                                data-book-title="<?php echo esc_attr( $tsk->book_title ?? '' ); ?>"
                                data-book-author="<?php echo esc_attr( $tsk->book_author ?? '' ); ?>"
                                data-submission-type="<?php echo esc_attr( $tsk->submission_type ?? 'text_or_file' ); ?>"
                                data-min-words="<?php echo intval( $tsk->min_words ?? 0 ); ?>"
                                data-due="<?php echo esc_attr( $tsk->due_datetime ); ?>"
                                data-is-expired="<?php echo $is_expired ? '1' : '0'; ?>"
                                data-is-graded="<?php echo $is_graded ? '1' : '0'; ?>"
                                data-score="<?php echo esc_attr( $tsk->score ?? '' ); ?>"
                                data-max-score="<?php echo esc_attr( $tsk->max_score ?? '100' ); ?>"
                                data-feedback="<?php echo esc_attr( $tsk->feedback ?? '' ); ?>"
                                data-sub-text="<?php echo esc_attr( $tsk->submission_text ?? '' ); ?>"
                                data-sub-attachment="<?php echo esc_attr( $tsk->attachment_urls ?? '' ); ?>"
                                data-desc="<?php echo esc_attr( $tsk->description ?? '' ); ?>"
                                style="font-size: 12px; padding: 6px 12px;">
                            <?php if ( $is_graded ) : ?>
                                👁️ <?php esc_html_e( 'Ver Calificación', 'aura-suite' ); ?>
                            <?php elseif ( $is_submitted ) : ?>
                                ✏️ <?php esc_html_e( 'Editar Entrega', 'aura-suite' ); ?>
                            <?php else : ?>
                                ✍️ <?php esc_html_e( 'Entregar Resumen / Tarea', 'aura-suite' ); ?>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════
         MODAL DEL ESTUDIANTE: REDACCIÓN DE RESUMEN Y ENTREGA DE TAREA
         ══════════════════════════════════════════════════════════════ -->
    <div id="modal-student-submission" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
        <div class="aura-modal-container" style="background: var(--aura-surface); max-width: 680px; width: 100%; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); border: 1px solid var(--aura-border);">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--aura-border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 id="stu-modal-title" style="margin: 0; font-size: 17px; font-weight: 700; color: var(--aura-text-primary);">
                        ✍️ <?php esc_html_e( 'Entrega de Resumen Académico', 'aura-suite' ); ?>
                    </h3>
                    <div id="stu-modal-book-box" style="font-size: 12px; color: #6366f1; margin-top: 3px; font-weight: 600; display: none;"></div>
                </div>
                <button type="button" class="stu-close-modal" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--aura-text-muted);">&times;</button>
            </div>

            <form id="form-student-task-submit">
                <input type="hidden" name="task_id" id="stu-submit-task-id" value="0">

                <div style="padding: 20px; display: flex; flex-direction: column; gap: 14px; max-height: 70vh; overflow-y: auto;">
                    <!-- Instrucciones del profesor -->
                    <div id="stu-modal-instructions" style="background: var(--aura-surface-alt); border: 1px solid var(--aura-border); border-radius: 8px; padding: 12px; font-size: 13px; color: var(--aura-text-secondary); line-height: 1.5; display: none;"></div>

                    <!-- Si está calificada: Banner de Nota -->
                    <div id="stu-modal-grade-banner" style="display: none; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; padding: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <strong style="color: #10b981; font-size: 15px;"><?php esc_html_e( 'Calificación Obtenida', 'aura-suite' ); ?></strong>
                            <span id="stu-modal-score-badge" class="adp-badge badge-emerald" style="font-size: 14px; font-weight: 700;"></span>
                        </div>
                        <div id="stu-modal-feedback-text" style="font-size: 13px; color: var(--aura-text-primary); line-height: 1.4;"></div>
                    </div>

                    <!-- Redactor de Resumen Escrito -->
                    <div class="form-group" id="stu-box-text-submission">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="font-weight: 600; font-size: 13px; margin: 0; color: var(--aura-text-primary);">
                                ✍️ <?php esc_html_e( 'Redacción de tu Resumen / Respuesta', 'aura-suite' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <span id="stu-words-badge" style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: var(--aura-surface-alt); border: 1px solid var(--aura-border);">
                                0 palabras
                            </span>
                        </div>
                        <textarea name="submission_text" id="stu-task-text" rows="8" class="form-control" placeholder="<?php esc_attr_e( 'Escribe aquí tu resumen analítico, ideas principales, conclusiones y aprendizajes...', 'aura-suite' ); ?>" style="width: 100%; border-radius: 8px; padding: 12px; border: 1px solid var(--aura-border); background: var(--aura-surface-alt); color: var(--aura-text-primary); line-height: 1.5; resize: vertical; font-family: inherit; font-size: 13px;"></textarea>
                        <div id="stu-min-words-hint" style="font-size: 11px; color: var(--aura-text-muted); margin-top: 4px;"></div>
                    </div>

                    <!-- Adjuntar archivo opcional o requerido -->
                    <div class="form-group" id="stu-box-file-submission">
                        <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--aura-text-primary);">
                            📎 <?php esc_html_e( 'Enlace a Documento o Archivo Adjunto (Google Drive, Dropbox, PDF)', 'aura-suite' ); ?>
                        </label>
                        <input type="url" name="attachment_urls" id="stu-task-attachment" class="form-control" placeholder="https://..." style="width: 100%; border-radius: 8px; padding: 8px 12px; border: 1px solid var(--aura-border); background: var(--aura-surface-alt); color: var(--aura-text-primary); font-size: 13px;">
                        <small style="font-size: 11px; color: var(--aura-text-muted); display: block; margin-top: 3px;">
                            <?php esc_html_e( 'Pega el enlace público a tu trabajo o documento de soporte si aplica.', 'aura-suite' ); ?>
                        </small>
                    </div>
                </div>

                <div style="padding: 14px 20px; border-top: 1px solid var(--aura-border); display: flex; justify-content: flex-end; gap: 10px; background: var(--aura-surface-alt);">
                    <button type="button" class="btn btn-ghost stu-close-modal">
                        <?php esc_html_e( 'Cerrar', 'aura-suite' ); ?>
                    </button>
                    <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-submit-student-task">
                        🚀 <?php esc_html_e( 'Enviar Entrega al Profesor', 'aura-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- SCRIPT DE ENTREGA DE TAREAS Y CONTADOR DE PALABRAS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var $ = jQuery;
    var currentMinWords = 0;

    function countWords(str) {
        if (!str) return 0;
        return str.trim().split(/\s+/).filter(Boolean).length;
    }

    function updateWordCount() {
        var text = $('#stu-task-text').val() || '';
        var words = countWords(text);
        var $badge = $('#stu-words-badge');

        if (currentMinWords > 0) {
            $badge.text(words + ' / ' + currentMinWords + ' palabras requeridas');
            if (words >= currentMinWords) {
                $badge.css({ 'background': '#10b981', 'color': '#ffffff', 'border-color': '#059669' });
            } else {
                $badge.css({ 'background': 'rgba(239,68,68,0.15)', 'color': '#ef4444', 'border-color': 'rgba(239,68,68,0.3)' });
            }
        } else {
            $badge.text(words + ' palabras').css({ 'background': 'var(--aura-surface-alt)', 'color': 'var(--aura-text-secondary)', 'border-color': 'var(--aura-border)' });
        }
    }

    $('#stu-task-text').on('input keyup paste', updateWordCount);

    $('.stu-close-modal').on('click', function() {
        $('#modal-student-submission').fadeOut(150);
    });

    // Abrir Modal de Entrega
    $('.btn-student-open-submission').on('click', function() {
        var $b = $(this);
        var taskId    = $b.data('task-id');
        var taskTitle = $b.data('task-title');
        var bookTitle = $b.data('book-title');
        var bookAuthor= $b.data('book-author');
        var subType   = $b.data('submission-type') || 'text_or_file';
        var minWords  = parseInt($b.data('min-words'), 10) || 0;
        var isGraded  = $b.data('is-graded') === 1 || $b.data('is-graded') === '1';
        var score     = $b.data('score');
        var maxScore  = $b.data('max-score') || '100';
        var feedback  = $b.data('feedback');
        var subText   = $b.data('sub-text');
        var subAttach = $b.data('sub-attachment');
        var desc      = $b.data('desc');

        currentMinWords = minWords;
        $('#stu-submit-task-id').val(taskId);
        $('#stu-modal-title').text('✍️ ' + taskTitle);

        if (bookTitle) {
            $('#stu-modal-book-box').html('📖 <strong>Libro de Lectura:</strong> ' + $('<div>').text(bookTitle + (bookAuthor ? ' — ' + bookAuthor : '')).html()).show();
        } else {
            $('#stu-modal-book-box').hide();
        }

        if (desc) {
            $('#stu-modal-instructions').html('<strong>Instrucciones:</strong><br>' + $('<div>').text(desc).html()).show();
        } else {
            $('#stu-modal-instructions').hide();
        }

        if (isGraded) {
            $('#stu-modal-grade-banner').show();
            $('#stu-modal-score-badge').text(score + ' / ' + maxScore + ' pts');
            $('#stu-modal-feedback-text').text(feedback ? 'Retroalimentación: ' + feedback : 'Sin comentarios adicionales.');
            $('#btn-submit-student-task').hide();
            $('#stu-task-text').prop('readonly', true);
            $('#stu-task-attachment').prop('readonly', true);
        } else {
            $('#stu-modal-grade-banner').hide();
            $('#btn-submit-student-task').show();
            $('#stu-task-text').prop('readonly', false);
            $('#stu-task-attachment').prop('readonly', false);
        }

        // Tipo de entrega
        if (subType === 'file_only') {
            $('#stu-box-text-submission').hide();
            $('#stu-box-file-submission').show();
        } else if (subType === 'text_only') {
            $('#stu-box-text-submission').show();
            $('#stu-box-file-submission').hide();
        } else {
            $('#stu-box-text-submission').show();
            $('#stu-box-file-submission').show();
        }

        if (minWords > 0) {
            $('#stu-min-words-hint').text('📏 Este resumen exige un mínimo de ' + minWords + ' palabras escritas.');
        } else {
            $('#stu-min-words-hint').text('');
        }

        $('#stu-task-text').val(subText || '');
        $('#stu-task-attachment').val(subAttach || '');
        updateWordCount();

        $('#modal-student-submission').css({ display: 'flex' }).hide().fadeIn(150);
    });

    // Enviar formulario de entrega vía AJAX
    $('#form-student-task-submit').on('submit', function(e) {
        e.preventDefault();

        if (currentMinWords > 0) {
            var words = countWords($('#stu-task-text').val() || '');
            if (words < currentMinWords) {
                alert('⚠️ Tu resumen cuenta con ' + words + ' palabras. Debes escribir al menos ' + currentMinWords + ' palabras para poder enviar la tarea.');
                $('#stu-task-text').focus();
                return;
            }
        }

        var $btn = $('#btn-submit-student-task');
        $btn.prop('disabled', true).text('Enviando entrega...');

        var postData = $(this).serializeArray();
        postData.push({ name: 'action', value: 'aura_cal_submit_task' });
        // Utilizar el nonce del calendario o de Aura
        postData.push({ name: 'nonce', value: (typeof auraCalData !== 'undefined' ? auraCalData.nonce : '<?php echo wp_create_nonce( "aura_cal_nonce" ); ?>') });

        var ajaxUrl = typeof auraCalData !== 'undefined' ? auraCalData.ajax_url : '<?php echo admin_url( "admin-ajax.php" ); ?>';

        $.post(ajaxUrl, postData, function(res) {
            $btn.prop('disabled', false).text('🚀 Enviar Entrega al Profesor');
            if (res && res.success) {
                alert(res.data.message || '¡Tarea entregada exitosamente!');
                $('#modal-student-submission').fadeOut(150);
                location.reload();
            } else {
                var err = res && res.data && res.data.message ? res.data.message : 'Error al enviar la tarea.';
                alert('⚠️ ' + err);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('🚀 Enviar Entrega al Profesor');
            alert('Error de conexión con el servidor al enviar la entrega.');
        });
    });
});
</script>
