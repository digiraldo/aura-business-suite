<?php
/**
 * Tab 2: Programas Académicos y Materias
 *
 * CRUD de programas de capacitación (Hadime, Semestrales, Talleres, etc.)
 * y sus materias/asignaturas correspondientes bajo el Design System de Aura.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$programs  = Aura_Calendar_Programs::get_all( [ 'status' => '', 'limit' => 100 ] );
$all_areas = class_exists( 'Aura_Areas_Setup' ) ? Aura_Areas_Setup::get_all_areas() : [];
?>

<div class="aura-programs-view-container">

    <!-- ── BARRA SUPERIOR DE ACCIONES ── -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 class="adp-card-title" style="font-size: 20px; margin: 0;">
                🎓 <?php esc_html_e( 'Programas y Cursos de Capacitación', 'aura' ); ?>
            </h2>
            <p class="adp-card-desc" style="margin: 4px 0 0 0;">
                <?php esc_html_e( 'Administra los programas de formación, sus materias curriculares, vinculación a áreas institucionales y la asignación de profesores.', 'aura' ); ?>
            </p>
        </div>

        <?php if ( current_user_can( 'aura_cal_manage_programs' ) || current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
            <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-create-program">
                ➕ <?php esc_html_e( 'Nuevo Programa', 'aura' ); ?>
            </button>
        <?php endif; ?>
    </div>

    <!-- ── LISTA DE PROGRAMAS (CARDS) ── -->
    <?php if ( empty( $programs ) ) : ?>
        <div class="adp-card" style="text-align: center; padding: 48px 24px; border-radius: 12px;">
            <div style="font-size: 40px; margin-bottom: 12px;">📚</div>
            <h3 style="font-size: 18px; margin-bottom: 6px;"><?php esc_html_e( 'No hay programas académicos registrados', 'aura' ); ?></h3>
            <p style="color: var(--aura-text-secondary); max-width: 460px; margin: 0 auto 20px;">
                <?php esc_html_e( 'Crea tu primer programa de formación para comenzar a estructurar las materias y horarios de clases.', 'aura' ); ?>
            </p>
            <?php if ( current_user_can( 'aura_cal_manage_programs' ) || current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-create-first-program">
                    ➕ <?php esc_html_e( 'Crear Primer Programa', 'aura' ); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ( $programs as $p ) : 
                $subjects = Aura_Calendar_Subjects::get_all( [ 'program_id' => $p->id ] );
                $p_color  = ! empty( $p->color ) ? $p->color : '#6366f1';
            ?>
                <div class="adp-card program-card" data-program-id="<?php echo esc_attr( $p->id ); ?>" style="border-radius: 12px; border-left: 6px solid <?php echo esc_attr( $p_color ); ?>; padding: 22px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap;">
                                <span class="adp-badge badge-slate" style="font-weight: 700; font-size: 12px; letter-spacing: 0.5px;">
                                    <?php echo esc_html( $p->code ); ?>
                                </span>
                                <?php if ( $p->status === 'active' ) : ?>
                                    <span class="adp-badge badge-emerald has-dot"><span class="pulse-dot"></span> <?php esc_html_e( 'Activo', 'aura' ); ?></span>
                                <?php elseif ( $p->status === 'archived' ) : ?>
                                    <span class="adp-badge badge-slate"><?php esc_html_e( 'Archivado', 'aura' ); ?></span>
                                <?php else : ?>
                                    <span class="adp-badge badge-amber"><?php esc_html_e( 'Borrador', 'aura' ); ?></span>
                                <?php endif; ?>

                                <?php if ( ! empty( $p->area_name ) ) : 
                                    $area_badge_color = ! empty( $p->area_color ) ? $p->area_color : '#6366f1';
                                ?>
                                    <span class="adp-badge" style="font-weight: 600; font-size: 12px; background: <?php echo esc_attr( $area_badge_color . '18' ); ?>; color: <?php echo esc_attr( $area_badge_color ); ?>; border: 1px solid <?php echo esc_attr( $area_badge_color . '35' ); ?>;">
                                        🏢 <?php echo esc_html( $p->area_name ); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ( ! empty( $p->academic_period ) ) : ?>
                                    <span style="font-size: 13px; color: var(--aura-text-secondary);">
                                        📅 <?php echo esc_html( $p->academic_period ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 style="font-size: 19px; font-weight: 700; margin: 0 0 6px 0; color: var(--aura-text-primary);">
                                <?php echo esc_html( $p->name ); ?>
                            </h3>

                            <?php if ( ! empty( $p->description ) ) : ?>
                                <p style="font-size: 13px; color: var(--aura-text-secondary); margin: 0 0 10px 0; max-width: 750px;">
                                    <?php echo esc_html( $p->description ); ?>
                                </p>
                            <?php endif; ?>

                            <div style="display: flex; gap: 16px; font-size: 13px; color: var(--aura-text-muted); flex-wrap: wrap;">
                                <?php if ( ! empty( $p->start_date ) && ! empty( $p->end_date ) ) : ?>
                                    <span>🗓️ <?php echo esc_html( date_i18n( 'j M Y', strtotime( $p->start_date ) ) . ' - ' . date_i18n( 'j M Y', strtotime( $p->end_date ) ) ); ?></span>
                                <?php endif; ?>

                                <?php 
                                $coords_display = ! empty( $p->coordinators_names ) ? $p->coordinators_names : ( ! empty( $p->coordinator_name ) ? $p->coordinator_name : '' );
                                if ( ! empty( $coords_display ) ) : 
                                    $is_multiple = ! empty( $p->coordinator_ids ) && count( $p->coordinator_ids ) > 1;
                                ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <strong><?php echo $is_multiple ? esc_html__( 'Coordinadores:', 'aura' ) : esc_html__( 'Coordinador:', 'aura' ); ?></strong>
                                        <?php
                                        if ( ! empty( $p->coordinator_ids ) && is_array( $p->coordinator_ids ) ) {
                                            foreach ( $p->coordinator_ids as $c_id ) {
                                                echo Aura_Calendar_Admin::get_user_avatar_html( (int) $c_id, 22, true );
                                            }
                                        } else {
                                            echo esc_html( $coords_display );
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>

                                <span>📚 <strong><?php echo intval( $p->subjects_count ); ?></strong> <?php esc_html_e( 'materias', 'aura' ); ?></span>
                                <span>📅 <strong><?php echo intval( $p->events_count ); ?></strong> <?php esc_html_e( 'clases agendadas', 'aura' ); ?></span>
                            </div>
                        </div>

                        <!-- Botones de Acción del Programa -->
                        <?php if ( current_user_can( 'aura_cal_manage_programs' ) || current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-ghost btn-edit-program" data-program-id="<?php echo esc_attr( $p->id ); ?>" style="font-size: 13px; padding: 6px 12px;">
                                    ✏️ <?php esc_html_e( 'Editar', 'aura' ); ?>
                                </button>
                                <button type="button" class="btn btn-indigo btn-lift btn-add-subject" data-program-id="<?php echo esc_attr( $p->id ); ?>" data-program-name="<?php echo esc_attr( $p->name ); ?>" style="font-size: 13px; padding: 6px 12px;">
                                    ➕ <?php esc_html_e( 'Añadir Materia', 'aura' ); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── SUB-TABLA DE MATERIAS ASOCIADAS ── -->
                    <div style="margin-top: 18px; border-top: 1px solid var(--aura-border, #e2e8f0); padding-top: 14px;">
                        <h4 style="font-size: 14px; font-weight: 600; margin: 0 0 10px 0; color: var(--aura-text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">
                            📚 <?php esc_html_e( 'Materias del Programa', 'aura' ); ?> (<?php echo count( $subjects ); ?>)
                        </h4>

                        <?php if ( empty( $subjects ) ) : ?>
                            <p style="font-size: 13px; color: var(--aura-text-muted); font-style: italic; margin: 0;">
                                <?php esc_html_e( 'Aún no se han agregado materias a este programa.', 'aura' ); ?>
                            </p>
                        <?php else : ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
                                <?php foreach ( $subjects as $s ) : 
                                    $s_color = ! empty( $s->color ) ? $s->color : '#3b82f6';
                                    $t_mat_count = ! empty( $s->teacher_materials_count ) ? (int) $s->teacher_materials_count : 0;
                                    $st_mat_count = ! empty( $s->student_materials_count ) ? (int) $s->student_materials_count : 0;
                                ?>
                                    <div style="background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #e2e8f0); border-left: 4px solid <?php echo esc_attr( $s_color ); ?>; border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <div style="font-size: 11px; font-weight: 700; color: var(--aura-text-muted);">
                                                <?php echo esc_html( $s->code ); ?>
                                                <?php if ( ! empty( $s->total_hours ) ) : ?>
                                                    &bull; <?php echo intval( $s->total_hours ); ?> hrs
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 14px; font-weight: 600; color: var(--aura-text-primary);">
                                                <?php echo esc_html( $s->name ); ?>
                                            </div>
                                            <?php 
                                            $teachers_display = ! empty( $s->teachers_names ) ? $s->teachers_names : ( ! empty( $s->default_teacher_name ) ? $s->default_teacher_name : '' );
                                            if ( ! empty( $teachers_display ) ) : 
                                                $is_multiple_teach = ! empty( $s->teacher_ids ) && count( $s->teacher_ids ) > 1;
                                            ?>
                                                <div style="font-size: 12px; color: var(--aura-text-secondary); margin-top: 4px; display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                    <span style="font-weight: 600;"><?php echo $is_multiple_teach ? esc_html__( 'Profesores:', 'aura' ) : esc_html__( 'Profesor:', 'aura' ); ?></span>
                                                    <?php
                                                    if ( ! empty( $s->teacher_ids ) && is_array( $s->teacher_ids ) ) {
                                                        foreach ( $s->teacher_ids as $t_id ) {
                                                            echo Aura_Calendar_Admin::get_user_avatar_html( (int) $t_id, 20, true );
                                                        }
                                                    } else {
                                                        echo esc_html( $teachers_display );
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ( $t_mat_count > 0 || $st_mat_count > 0 ) : ?>
                                                <div style="margin-top: 6px; display: flex; gap: 6px; font-size: 11px; flex-wrap: wrap;">
                                                    <?php if ( $t_mat_count > 0 ) : ?>
                                                        <span class="aura-badge aura-badge--sm" title="<?php esc_attr_e( 'Material Pedagógico Docente', 'aura-suite' ); ?>" style="background: rgba(93,95,239,0.1); color: var(--aura-primary,#5d5fef); padding: 2px 6px; border-radius: 6px;">📁 <?php echo $t_mat_count; ?> <?php esc_html_e( 'docente', 'aura' ); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ( $st_mat_count > 0 ) : ?>
                                                        <span class="aura-badge aura-badge--sm" title="<?php esc_attr_e( 'Material para Estudiantes', 'aura-suite' ); ?>" style="background: rgba(16,185,129,0.1); color: #10b981; padding: 2px 6px; border-radius: 6px;">📖 <?php echo $st_mat_count; ?> <?php esc_html_e( 'alumnos', 'aura' ); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; gap: 4px;">
                                            <button type="button" class="btn btn-ghost btn-edit-subject" data-subject-id="<?php echo esc_attr( $s->id ); ?>" style="padding: 4px 8px; font-size: 12px;" title="<?php esc_attr_e( 'Editar materia', 'aura' ); ?>">
                                                ✏️
                                            </button>
                                            <button type="button" class="btn btn-ghost btn-delete-subject" data-subject-id="<?php echo esc_attr( $s->id ); ?>" style="padding: 4px 8px; font-size: 12px; color: #ef4444;" title="<?php esc_attr_e( 'Eliminar materia', 'aura' ); ?>">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL A: CREAR / EDITAR PROGRAMA
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-program-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 580px;">
        <div class="aura-modal-header">
            <h3 id="modal-prog-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                🎓 <?php esc_html_e( 'Nuevo Programa Académico', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-program-editor">&times;</button>
        </div>

        <form id="form-program-editor" class="aura-modal-form">
            <div class="aura-modal-body">
                <input type="hidden" name="id" id="prog-id" value="0">

                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Nombre del Programa / Capacitación', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="name" id="prog-name" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Capacitación Ministerial Hadime 2025', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Código Corto', 'aura' ); ?>
                            </label>
                            <input type="text" name="code" id="prog-code" class="form-control" placeholder="<?php esc_attr_e( 'HADIME25', 'aura' ); ?>" style="width: 100%; border-radius: 8px; text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Período Académico', 'aura' ); ?>
                            </label>
                            <input type="text" name="academic_period" id="prog-period" class="form-control" placeholder="<?php esc_attr_e( '2025-1 / Ene-Jun', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Fecha Inicio', 'aura' ); ?>
                            </label>
                            <input type="date" name="start_date" id="prog-start-date" class="form-control" style="width: 100%; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Fecha Fin', 'aura' ); ?>
                            </label>
                            <input type="date" name="end_date" id="prog-end-date" class="form-control" style="width: 100%; border-radius: 8px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            🎨 <?php esc_html_e( 'Color del Programa', 'aura' ); ?>
                        </label>
                        <div class="aura-color-picker-box">
                            <div class="aura-color-picker-row">
                                <input type="color" name="color" id="prog-color" value="#5D5FEF" class="aura-color-custom-input" title="<?php esc_attr_e( 'Color personalizado', 'aura' ); ?>">
                                <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Paleta de colores oficial:', 'aura' ); ?></span>
                            </div>
                            <?php echo Aura_Calendar_Admin::render_color_palette( 'prog-color', '#5D5FEF' ); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            🏢 <?php esc_html_e( 'Área Institucional (Opcional)', 'aura' ); ?>
                        </label>
                        <select name="area_id" id="prog-area-id" class="form-control" style="width: 100%; border-radius: 8px;">
                            <option value=""><?php esc_html_e( '— Ninguna / Programa General —', 'aura' ); ?></option>
                            <?php foreach ( $all_areas as $area ) : ?>
                                <option value="<?php echo esc_attr( $area->id ); ?>">
                                    <?php echo esc_html( $area->name . ( ! empty( $area->code ) ? ' (' . $area->code . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="font-size: 11.5px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                            <?php esc_html_e( 'Vincular el programa a un área habilita permisos y presupuestos descentralizados para sus coordinadores y líderes de área.', 'aura' ); ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            👥 <?php esc_html_e( 'Coordinador(es) del Programa', 'aura' ); ?>
                        </label>
                        <div id="prog-coordinators-container" class="aura-user-chips-container">
                            <!-- Inyectado dinámicamente con checkboxes desde JS -->
                        </div>
                        <small style="font-size: 11.5px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                            <?php esc_html_e( 'Puedes seleccionar uno o varios coordinadores para este programa.', 'aura' ); ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Descripción u Objetivos', 'aura' ); ?>
                        </label>
                        <textarea name="description" id="prog-desc" rows="2" class="form-control" placeholder="<?php esc_attr_e( 'Breve descripción del programa...', 'aura' ); ?>" style="width: 100%; border-radius: 8px;"></textarea>
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-program-editor">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-program">
                    💾 <?php esc_html_e( 'Guardar Programa', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL B: CREAR / EDITAR MATERIA + MATERIALES PEDAGÓGICOS
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-subject-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 660px;">
        <div class="aura-modal-header">
            <h3 id="modal-subj-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                📚 <?php esc_html_e( 'Añadir Materia', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-subject-editor">&times;</button>
        </div>

        <!-- Sub-navegación de pestañas en modal de materia -->
        <div class="aura-modal-subtabs" style="display: flex; gap: 4px; border-bottom: 1px solid var(--aura-border, #e2e8f0); padding: 0 20px; background: var(--aura-surface-alt, #f8fafc);">
            <button type="button" class="aura-modal-subtab-btn active" data-subtab="subj-tab-general" style="padding: 10px 14px; font-size: 13px; font-weight: 600; border: none; background: transparent; border-bottom: 2px solid var(--aura-primary, #5d5fef); color: var(--aura-primary, #5d5fef); cursor: pointer;">
                ℹ️ <?php esc_html_e( 'General', 'aura' ); ?>
            </button>
            <button type="button" class="aura-modal-subtab-btn" data-subtab="subj-tab-teacher-mat" style="padding: 10px 14px; font-size: 13px; font-weight: 600; border: none; background: transparent; border-bottom: 2px solid transparent; color: var(--aura-text-secondary, #64748b); cursor: pointer;">
                📁 <?php esc_html_e( 'Material Docente (Cátedra)', 'aura' ); ?>
            </button>
            <button type="button" class="aura-modal-subtab-btn" data-subtab="subj-tab-student-mat" style="padding: 10px 14px; font-size: 13px; font-weight: 600; border: none; background: transparent; border-bottom: 2px solid transparent; color: var(--aura-text-secondary, #64748b); cursor: pointer;">
                📖 <?php esc_html_e( 'Material para Alumnos', 'aura' ); ?>
            </button>
        </div>

        <form id="form-subject-editor" class="aura-modal-form">
            <div class="aura-modal-body" style="max-height: 70vh; overflow-y: auto;">
                <input type="hidden" name="id" id="subj-id" value="0">
                <input type="hidden" name="program_id" id="subj-prog-id" value="0">

                <!-- ── TAB 1: INFORMACIÓN GENERAL ── -->
                <div id="subj-tab-general" class="aura-modal-subtab-pane">
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Programa:', 'aura' ); ?></span>
                            <strong id="subj-prog-name-display" style="display: block; font-size: 14px; color: var(--aura-primary);"></strong>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Nombre de la Materia', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" name="name" id="subj-name" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Teología Sistemática I', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                    <?php esc_html_e( 'Código de Materia', 'aura' ); ?>
                                </label>
                                <input type="text" name="code" id="subj-code" class="form-control" placeholder="<?php esc_attr_e( 'TEO101', 'aura' ); ?>" style="width: 100%; border-radius: 8px; text-transform: uppercase;">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                    <?php esc_html_e( 'Horas Académicas', 'aura' ); ?>
                                </label>
                                <input type="number" name="total_hours" id="subj-hours" value="30" min="0" class="form-control" style="width: 100%; border-radius: 8px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                🎨 <?php esc_html_e( 'Color de la Materia', 'aura' ); ?>
                            </label>
                            <div class="aura-color-picker-box">
                                <div class="aura-color-picker-row">
                                    <input type="color" name="color" id="subj-color" value="#3A86FF" class="aura-color-custom-input" title="<?php esc_attr_e( 'Color personalizado', 'aura' ); ?>">
                                    <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Paleta de colores oficial:', 'aura' ); ?></span>
                                </div>
                                <?php echo Aura_Calendar_Admin::render_color_palette( 'subj-color', '#3A86FF' ); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                👨‍🏫 <?php esc_html_e( 'Profesor(es) Titular(es)', 'aura' ); ?>
                            </label>
                            <div id="subj-teachers-container" class="aura-user-chips-container">
                                <!-- Inyectado dinámicamente con checkboxes desde JS -->
                            </div>
                            <small style="font-size: 11.5px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                                <?php esc_html_e( 'Puedes seleccionar uno o varios profesores titulares para esta materia.', 'aura' ); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- ── TAB 2: MATERIAL DOCENTE (CÁTEDRA) ── -->
                <div id="subj-tab-teacher-mat" class="aura-modal-subtab-pane" style="display: none;">
                    <div style="background: rgba(93,95,239,0.06); border: 1px solid rgba(93,95,239,0.2); border-radius: 8px; padding: 12px; margin-bottom: 14px; font-size: 12.5px; color: var(--aura-text-secondary);">
                        <strong style="color: var(--aura-primary); display: block; margin-bottom: 3px;">ℹ️ <?php esc_html_e( 'Material Pedagógico de Cátedra', 'aura' ); ?></strong>
                        <?php esc_html_e( 'Este material es exclusivo para el instructor. Al reasignar esta materia a otro profesor en el futuro, el nuevo docente tendrá acceso a estos documentos, guías de clase y presentaciones para continuar la enseñanza sin perder información.', 'aura' ); ?>
                    </div>

                    <!-- Botones de Acción de Material -->
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                        <input type="file" id="upload-teacher-file-input" style="display: none;">
                        <button type="button" class="btn btn-outline btn-upload-material" data-type="teacher" style="font-size: 12px; padding: 6px 12px;">
                            ☁️ <?php esc_html_e( 'Subir Archivo (Drive / Nube)', 'aura' ); ?>
                        </button>
                        <button type="button" class="btn btn-ghost btn-add-drive-link" data-type="teacher" style="font-size: 12px; padding: 6px 12px;">
                            🔗 <?php esc_html_e( 'Añadir Enlace Google Drive', 'aura' ); ?>
                        </button>
                    </div>

                    <!-- Lista de Materiales Docente -->
                    <div id="subj-teacher-materials-list" class="aura-materials-list" style="display: flex; flex-direction: column; gap: 8px;">
                        <p class="aura-empty-hint" style="font-size: 12px; color: var(--aura-text-muted); font-style: italic;">
                            <?php esc_html_e( 'No hay materiales de cátedra cargados aún.', 'aura' ); ?>
                        </p>
                    </div>
                </div>

                <!-- ── TAB 3: MATERIAL PARA ESTUDIANTES ── -->
                <div id="subj-tab-student-mat" class="aura-modal-subtab-pane" style="display: none;">
                    <div style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.2); border-radius: 8px; padding: 12px; margin-bottom: 14px; font-size: 12.5px; color: var(--aura-text-secondary);">
                        <strong style="color: #10b981; display: block; margin-bottom: 3px;">📖 <?php esc_html_e( 'Material de Estudio para Alumnos', 'aura' ); ?></strong>
                        <?php esc_html_e( 'Lecturas requeridas, guías de estudio, cuestionarios y documentos que los estudiantes matriculados en esta materia podrán consultar y descargar directamente desde su portal.', 'aura' ); ?>
                    </div>

                    <!-- Botones de Acción de Material Estudiantes -->
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                        <input type="file" id="upload-student-file-input" style="display: none;">
                        <button type="button" class="btn btn-outline btn-upload-material" data-type="student" style="font-size: 12px; padding: 6px 12px;">
                            ☁️ <?php esc_html_e( 'Subir Archivo para Alumnos', 'aura' ); ?>
                        </button>
                        <button type="button" class="btn btn-ghost btn-add-drive-link" data-type="student" style="font-size: 12px; padding: 6px 12px;">
                            🔗 <?php esc_html_e( 'Añadir Enlace Google Drive', 'aura' ); ?>
                        </button>
                    </div>

                    <!-- Lista de Materiales Estudiantes -->
                    <div id="subj-student-materials-list" class="aura-materials-list" style="display: flex; flex-direction: column; gap: 8px;">
                        <p class="aura-empty-hint" style="font-size: 12px; color: var(--aura-text-muted); font-style: italic;">
                            <?php esc_html_e( 'No hay materiales para alumnos cargados aún.', 'aura' ); ?>
                        </p>
                    </div>
                </div>

            </div>

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-subject-editor">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-subject">
                    💾 <?php esc_html_e( 'Guardar Materia', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

