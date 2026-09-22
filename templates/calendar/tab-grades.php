<?php
/**
 * Tab 3: Calificaciones y Boletines
 *
 * Visualización y ponderación de notas académicas por materia y estudiante,
 * cálculo de promedios en tiempo real y registro de retroalimentaciones.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$programs = Aura_Calendar_Programs::get_all( [ 'status' => 'active', 'limit' => 100 ] );
?>

<div class="aura-grades-view-container">

    <div class="adp-card" style="padding: 18px 22px; margin-bottom: 20px; border-radius: 12px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 14px; flex: 1;">
                
                <div style="min-width: 240px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-secondary); margin-bottom: 4px; display: block;">
                        🎓 <?php esc_html_e( 'Programa', 'aura' ); ?>
                    </label>
                    <select id="grades-filter-program" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px; font-size: 13px;">
                        <option value=""><?php esc_html_e( 'Selecciona un programa...', 'aura' ); ?></option>
                        <?php foreach ( $programs as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->id ); ?>">
                                <?php echo esc_html( $p->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="min-width: 240px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-secondary); margin-bottom: 4px; display: block;">
                        📚 <?php esc_html_e( 'Materia', 'aura' ); ?>
                    </label>
                    <select id="grades-filter-subject" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px; font-size: 13px;" disabled>
                        <option value=""><?php esc_html_e( 'Primero selecciona un programa', 'aura' ); ?></option>
                    </select>
                </div>

                <div style="align-self: flex-end;">
                    <button type="button" id="btn-load-grades" class="btn btn-indigo btn-lift" style="padding: 9px 16px; font-size: 13px;" disabled>
                        🔍 <?php esc_html_e( 'Cargar Calificaciones', 'aura' ); ?>
                    </button>
                </div>
            </div>

            <?php
            $can_manage_grades = current_user_can( 'aura_cal_manage_grades' ) || current_user_can( 'aura_record_grades' ) || current_user_can( 'manage_options' );
            $can_delete_grades = current_user_can( 'aura_cal_delete_grades' ) || $can_manage_grades;
            if ( $can_manage_grades ) : ?>
                <div style="align-self: flex-end;">
                    <button type="button" class="btn btn-emerald btn-shimmer btn-lift" id="btn-new-grade" style="display: none; padding: 9px 16px; font-size: 13px;">
                        ➕ <?php esc_html_e( 'Registrar Nota', 'aura' ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TABLA DE CALIFICACIONES (MATRIZ) ── -->
    <div class="adp-card" style="padding: 20px; border-radius: 12px;">
        <div id="grades-placeholder" style="text-align: center; padding: 40px 20px; color: var(--aura-text-muted);">
            <div style="font-size: 36px; margin-bottom: 8px;">📊</div>
            <p style="font-size: 14px; margin: 0;">
                <?php esc_html_e( 'Selecciona un programa y una materia para ver y gestionar las notas de los estudiantes.', 'aura' ); ?>
            </p>
        </div>

        <div id="grades-table-container" style="display: none; overflow-x: auto;">
            <table class="adp-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: var(--aura-surface-alt, #f8fafc); border-bottom: 2px solid var(--aura-border);">
                        <th style="padding: 12px 16px; text-align: left;"><?php esc_html_e( 'Estudiante', 'aura' ); ?></th>
                        <th style="padding: 12px 16px; text-align: left;"><?php esc_html_e( 'Código', 'aura' ); ?></th>
                        <th style="padding: 12px 16px; text-align: left;"><?php esc_html_e( 'Evaluaciones Registradas', 'aura' ); ?></th>
                        <th style="padding: 12px 16px; text-align: center; width: 140px;"><?php esc_html_e( 'Promedio Ponderado', 'aura' ); ?></th>
                        <th style="padding: 12px 16px; text-align: center; width: 100px;"><?php esc_html_e( 'Acción', 'aura' ); ?></th>
                    </tr>
                </thead>
                <tbody id="grades-table-body">
                    <!-- Inyectado vía AJAX -->
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: REGISTRAR / EDITAR CALIFICACIÓN
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-grade-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 500px;">
        <div class="aura-modal-header">
            <h3 class="adp-card-title" id="modal-grade-title" style="margin: 0; font-size: 18px;">
                📝 <?php esc_html_e( 'Registrar Calificación', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-grade-editor">&times;</button>
        </div>

        <form id="form-grade-editor" class="aura-modal-form">
            <div class="aura-modal-body">
                <input type="hidden" name="id" id="grd-id" value="0">
                <input type="hidden" name="program_id" id="grd-prog-id" value="0">
                <input type="hidden" name="subject_id" id="grd-subj-id" value="0">
                <input type="hidden" name="student_id" id="grd-stud-id" value="0">

                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div>
                        <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Estudiante:', 'aura' ); ?></span>
                        <strong id="grd-stud-name-display" style="display: block; font-size: 15px; color: var(--aura-text-primary);"></strong>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Nombre de la Evaluación', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="eval_title" id="grd-eval-title" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Examen Parcial 1 / Ensayo Teológico', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Tipo', 'aura' ); ?>
                            </label>
                            <select name="eval_type" id="grd-eval-type" class="form-control" style="width: 100%; border-radius: 8px;">
                                <option value="partial"><?php esc_html_e( 'Parcial', 'aura' ); ?></option>
                                <option value="final"><?php esc_html_e( 'Examen Final', 'aura' ); ?></option>
                                <option value="task"><?php esc_html_e( 'Tarea / Trabajo', 'aura' ); ?></option>
                                <option value="project"><?php esc_html_e( 'Proyecto', 'aura' ); ?></option>
                                <option value="participation"><?php esc_html_e( 'Participación / Asistencia', 'aura' ); ?></option>
                                <option value="other"><?php esc_html_e( 'Otro', 'aura' ); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Ponderación (Peso)', 'aura' ); ?>
                            </label>
                            <input type="number" step="0.1" name="weight" id="grd-weight" value="1.0" class="form-control" style="width: 100%; border-radius: 8px;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Nota Obtenida', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="number" step="0.01" name="score" id="grd-score" required class="form-control" placeholder="100" style="width: 100%; border-radius: 8px; font-weight: 700; font-size: 16px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Nota Máxima', 'aura' ); ?>
                            </label>
                            <input type="number" step="0.01" name="max_score" id="grd-max-score" value="100" class="form-control" style="width: 100%; border-radius: 8px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Retroalimentación / Comentarios', 'aura' ); ?>
                        </label>
                        <textarea name="feedback" id="grd-feedback" rows="2" class="form-control" placeholder="<?php esc_attr_e( 'Excelente trabajo, se recomienda profundizar en...', 'aura' ); ?>" style="width: 100%; border-radius: 8px;"></textarea>
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <?php if ( $can_delete_grades ) : ?>
                        <button type="button" class="btn btn-ghost" id="btn-delete-grade-modal" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.4); display: none;">
                            🗑️ <?php esc_html_e( 'Eliminar Nota', 'aura' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-ghost" data-close-modal="#modal-grade-editor">
                        <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                    </button>
                    <button type="submit" class="btn btn-emerald btn-shimmer btn-lift" id="btn-save-grade">
                        💾 <?php esc_html_e( 'Guardar Nota', 'aura' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
