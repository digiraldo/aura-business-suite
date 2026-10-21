<?php
/**
 * Tab 1: Vista de Calendario y Horarios Académicos
 *
 * Renderiza la interfaz de FullCalendar v6 con filtros por programa/materia/tipo,
 * barra de acciones con Agendar Clase, Limpiar Filtros y Modo Pantalla Completa.
 * Los modales están centralizados en templates/calendar/modal-partials.php.
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

<div class="aura-calendar-view-container">

    <!-- ── BARRA DE FILTROS SUPERIOR (COMPACT TOOLBAR) ── -->
    <div class="adp-card aura-calendar-filter-bar">
        <div class="aura-cal-toolbar-inner">
            <!-- Zona de Filtros Rápidos -->
            <div class="aura-cal-filters-group">
                <span class="aura-cal-filter-header" title="<?php esc_attr_e( 'Filtros del Calendario', 'aura' ); ?>">
                    <span class="dashicons dashicons-filter" style="font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
                    <span class="aura-cal-filter-label-text"><?php esc_html_e( 'Filtros', 'aura' ); ?></span>
                </span>

                <!-- Filtro Programa -->
                <div class="aura-cal-filter-item" title="<?php esc_attr_e( 'Filtrar por Programa', 'aura' ); ?>">
                    <span class="aura-cal-item-icon" aria-hidden="true">🎓</span>
                    <select id="filter-program" class="aura-cal-select" aria-label="<?php esc_attr_e( 'Programa', 'aura' ); ?>">
                        <option value=""><?php esc_html_e( 'Programa: Todos', 'aura' ); ?></option>
                        <?php foreach ( $programs as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->id ); ?>" data-color="<?php echo esc_attr( $p->color ); ?>">
                                <?php echo esc_html( $p->name . ( ! empty( $p->code ) ? ' (' . $p->code . ')' : '' ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro Materia -->
                <div class="aura-cal-filter-item" title="<?php esc_attr_e( 'Filtrar por Materia', 'aura' ); ?>">
                    <span class="aura-cal-item-icon" aria-hidden="true">📚</span>
                    <select id="filter-subject" class="aura-cal-select" aria-label="<?php esc_attr_e( 'Materia', 'aura' ); ?>">
                        <option value=""><?php esc_html_e( 'Materia: Todas', 'aura' ); ?></option>
                    </select>
                </div>

                <!-- Filtro Tipo de Evento -->
                <div class="aura-cal-filter-item" title="<?php esc_attr_e( 'Filtrar por Tipo de Evento', 'aura' ); ?>">
                    <span class="aura-cal-item-icon" aria-hidden="true">📌</span>
                    <select id="filter-event-type" class="aura-cal-select" aria-label="<?php esc_attr_e( 'Tipo de Evento', 'aura' ); ?>">
                        <option value=""><?php esc_html_e( 'Tipo: Todos', 'aura' ); ?></option>
                        <option value="class">📖 <?php esc_html_e( 'Clases regulares', 'aura' ); ?></option>
                        <option value="exam">📝 <?php esc_html_e( 'Exámenes', 'aura' ); ?></option>
                        <option value="workshop">🔬 <?php esc_html_e( 'Talleres / Prácticas', 'aura' ); ?></option>
                        <option value="activity">🎯 <?php esc_html_e( 'Actividades', 'aura' ); ?></option>
                        <option value="break">☕ <?php esc_html_e( 'Recesos', 'aura' ); ?></option>
                    </select>
                </div>

                <!-- Filtro Asignación al Calendario -->
                <div class="aura-cal-filter-item" title="<?php esc_attr_e( 'Filtrar por Asignación', 'aura' ); ?>">
                    <span class="aura-cal-item-icon" aria-hidden="true">📋</span>
                    <select id="filter-assignment-status" class="aura-cal-select" aria-label="<?php esc_attr_e( 'Asignación', 'aura' ); ?>">
                        <option value=""><?php esc_html_e( 'Asignación: Todas', 'aura' ); ?></option>
                        <option value="unassigned"><?php esc_html_e( '⏳ Sin agendar', 'aura' ); ?></option>
                    </select>
                </div>

                <!-- Botón Reset filtros compacto -->
                <button type="button" id="btn-filter-reset" class="aura-cal-btn-icon-subtle" title="<?php esc_attr_e( 'Restablecer todos los filtros', 'aura' ); ?>" aria-label="<?php esc_attr_e( 'Limpiar Filtros', 'aura' ); ?>">
                    <span class="dashicons dashicons-image-rotate" style="font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
                    <span class="btn-text-sm"><?php esc_html_e( 'Limpiar', 'aura' ); ?></span>
                </button>
            </div>

            <!-- Zona de Acciones Rápidas -->
            <div class="aura-cal-actions-group">
                <?php if ( current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_cal_create_events' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" id="btn-toggle-unassigned-drawer" class="aura-cal-action-btn btn-unassigned-toggle" title="<?php esc_attr_e( 'Ver materias pendientes de programar y arrastrarlas al calendario', 'aura' ); ?>">
                        <span class="btn-icon">📦</span>
                        <span class="btn-label"><?php esc_html_e( 'Pendientes', 'aura' ); ?></span>
                        <span id="unassigned-badge-count" class="aura-unassigned-pill">0</span>
                    </button>

                    <button type="button" id="btn-create-event-modal" class="aura-cal-action-btn btn-primary-event btn-trigger-agendar" title="<?php esc_attr_e( 'Crear nuevo evento en el calendario', 'aura' ); ?>">
                        <span class="btn-icon">➕</span>
                        <span class="btn-label"><?php esc_html_e( 'Crear Evento', 'aura' ); ?></span>
                    </button>
                <?php endif; ?>

                <button type="button" id="btn-toggle-fullscreen" class="aura-cal-action-btn btn-ghost-compact" title="<?php esc_attr_e( 'Ver calendario en pantalla completa', 'aura' ); ?>">
                    <span class="btn-icon">⛶</span>
                    <span class="btn-label-responsive"><?php esc_html_e( 'Pantalla Completa', 'aura' ); ?></span>
                </button>

                <a href="https://calendar.google.com/calendar/u/0/r" target="_blank" rel="noopener noreferrer" id="btn-open-gcal" class="aura-cal-action-btn btn-ghost-compact aura-btn-gcal-link" title="<?php esc_attr_e( 'Abrir esta misma fecha y vista en Google Calendar', 'aura' ); ?>">
                    <span class="btn-icon">📅</span>
                    <span class="btn-label-responsive"><?php esc_html_e( 'Google Cal', 'aura' ); ?></span>
                    <span class="btn-arrow" style="font-size: 11px; opacity: 0.7;">↗</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ── CONTENEDOR DEL FULLCALENDAR ── -->
    <div class="adp-card aura-calendar-card" style="padding: 20px; border-radius: 14px; box-shadow: var(--aura-shadow-sm, 0 1px 3px rgba(0,0,0,0.05)); position: relative;">
        <!-- Barra de controles flotantes en Pantalla Completa (Backend) -->
        <div class="aura-calendar-floating-fs-bar aura-backend-fs-bar" role="toolbar" aria-label="<?php esc_attr_e( 'Controles de Pantalla Completa', 'aura' ); ?>">
            <?php if ( current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_cal_create_events' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                <button type="button" class="aura-fs-bar-btn aura-fs-btn-create btn-trigger-agendar" id="btn-fs-create-event" title="<?php esc_attr_e( 'Crear Evento', 'aura' ); ?>" aria-label="<?php esc_attr_e( 'Crear Evento', 'aura' ); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </button>
            <?php endif; ?>
            <button type="button" class="aura-fs-bar-btn aura-fs-btn-exit" id="btn-fs-exit-fullscreen" title="<?php esc_attr_e( 'Salir de Pantalla Completa (ESC)', 'aura' ); ?>" aria-label="<?php esc_attr_e( 'Salir de Pantalla Completa', 'aura' ); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <!-- Flecha esquina superior-izquierda hacia el centro -->
                    <polyline points="5 9 5 5 9 5"></polyline><line x1="5" y1="5" x2="10" y2="10"></line>
                    <!-- Flecha esquina superior-derecha hacia el centro -->
                    <polyline points="19 9 19 5 15 5"></polyline><line x1="19" y1="5" x2="14" y2="10"></line>
                    <!-- Flecha esquina inferior-izquierda hacia el centro -->
                    <polyline points="5 15 5 19 9 19"></polyline><line x1="5" y1="19" x2="10" y2="14"></line>
                    <!-- Flecha esquina inferior-derecha hacia el centro -->
                    <polyline points="19 15 19 19 15 19"></polyline><line x1="19" y1="19" x2="14" y2="14"></line>
                </svg>
            </button>
        </div>

        <div id="aura-main-calendar" style="min-height: 700px;"></div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════
     DRAWER LATERAL: MATERIAS NO ASIGNADAS (DRAG & DROP AL CALENDARIO)
     ══════════════════════════════════════════════════════════════════ -->
<div id="aura-unassigned-drawer-backdrop" class="aura-drawer-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); z-index: 99998; transition: opacity 0.25s ease;"></div>

<aside id="aura-unassigned-drawer" class="aura-unassigned-drawer" style="position: fixed; top: 0; right: -420px; width: 400px; max-width: 90vw; height: 100vh; background: var(--aura-surface-card, #ffffff); box-shadow: -5px 0 25px rgba(0,0,0,0.15); z-index: 99999; display: flex; flex-direction: column; transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <!-- Cabecera del Drawer -->
    <div style="padding: 18px 20px; border-bottom: 1px solid var(--aura-border, #e2e8f0); display: flex; justify-content: space-between; align-items: flex-start; background: var(--aura-surface-alt, #f8fafc);">
        <div>
            <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: var(--aura-text-primary, #0f172a); display: flex; align-items: center; gap: 8px;">
                📦 <?php esc_html_e( 'Materias sin Asignar', 'aura' ); ?>
            </h3>
            <p style="margin: 0; font-size: 12px; color: var(--aura-text-muted, #64748b); line-height: 1.35;">
                <?php esc_html_e( 'Arrastra cualquier materia directamente a una fecha y hora del calendario.', 'aura' ); ?>
            </p>
        </div>
        <button type="button" id="btn-close-unassigned-drawer" style="background: none; border: none; font-size: 24px; line-height: 1; cursor: pointer; color: var(--aura-text-muted); padding: 2px 6px;" title="<?php esc_attr_e( 'Cerrar panel', 'aura' ); ?>">&times;</button>
    </div>

    <!-- Barra de búsqueda y filtro dentro del Drawer -->
    <div style="padding: 12px 18px; border-bottom: 1px solid var(--aura-border, #e2e8f0); background: var(--aura-surface, #ffffff); display: flex; flex-direction: column; gap: 8px;">
        <div style="position: relative;">
            <input type="text" id="drawer-search-subj" class="form-control" placeholder="<?php esc_attr_e( 'Buscar por nombre, código o módulo...', 'aura' ); ?>" style="width: 100%; font-size: 12.5px; padding: 7px 12px; border-radius: 8px;">
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: var(--aura-text-secondary);">
            <span id="drawer-count-label"><?php esc_html_e( 'Cargando materias...', 'aura' ); ?></span>
            <button type="button" id="btn-refresh-unassigned" style="background: none; border: none; color: #6366f1; cursor: pointer; font-weight: 600; padding: 0;">🔄 <?php esc_html_e( 'Actualizar', 'aura' ); ?></button>
        </div>
    </div>

    <!-- Lista de materias arrastrables -->
    <div id="aura-unassigned-subjects-list" style="flex: 1; overflow-y: auto; padding: 14px 18px; display: flex; flex-direction: column; gap: 10px;">
        <!-- Inyectado dinámicamente con cards arrastrables -->
    </div>

    <!-- Footer del Drawer con Tip informativo -->
    <div style="padding: 12px 18px; border-top: 1px solid var(--aura-border, #e2e8f0); background: var(--aura-surface-alt, #f8fafc); font-size: 11.5px; color: var(--aura-text-muted); display: flex; align-items: center; gap: 8px;">
        <span>💡</span>
        <span><?php esc_html_e( 'Al soltar una materia en el calendario se abrirá el formulario para confirmar aula y horario.', 'aura' ); ?></span>
    </div>
</aside>

