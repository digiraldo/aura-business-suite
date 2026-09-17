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

    <!-- ── BARRA DE FILTROS SUPERIOR ── -->
    <div class="adp-card" style="padding: 16px 20px; margin-bottom: 20px; border-radius: 12px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px; flex: 1;">
                
                <!-- Filtro Programa -->
                <div style="min-width: 220px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-secondary, #64748b); margin-bottom: 4px; display: block;">
                        🎓 <?php esc_html_e( 'Programa', 'aura' ); ?>
                    </label>
                    <select id="filter-program" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px; font-size: 13px;">
                        <option value=""><?php esc_html_e( 'Todos los programas', 'aura' ); ?></option>
                        <?php foreach ( $programs as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->id ); ?>" data-color="<?php echo esc_attr( $p->color ); ?>">
                                <?php echo esc_html( $p->name . ( ! empty( $p->code ) ? ' (' . $p->code . ')' : '' ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro Materia -->
                <div style="min-width: 200px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-secondary, #64748b); margin-bottom: 4px; display: block;">
                        📚 <?php esc_html_e( 'Materia', 'aura' ); ?>
                    </label>
                    <select id="filter-subject" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px; font-size: 13px;">
                        <option value=""><?php esc_html_e( 'Todas las materias', 'aura' ); ?></option>
                    </select>
                </div>

                <!-- Filtro Tipo de Evento -->
                <div style="min-width: 180px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-secondary, #64748b); margin-bottom: 4px; display: block;">
                        📌 <?php esc_html_e( 'Tipo de Evento', 'aura' ); ?>
                    </label>
                    <select id="filter-event-type" class="form-control" style="width: 100%; border-radius: 8px; padding: 8px 12px; font-size: 13px;">
                        <option value=""><?php esc_html_e( 'Todos los tipos', 'aura' ); ?></option>
                        <option value="class">📖 <?php esc_html_e( 'Clases regulares', 'aura' ); ?></option>
                        <option value="exam">📝 <?php esc_html_e( 'Exámenes / Evaluaciones', 'aura' ); ?></option>
                        <option value="workshop">🔬 <?php esc_html_e( 'Talleres / Prácticas', 'aura' ); ?></option>
                        <option value="activity">🎯 <?php esc_html_e( 'Actividades', 'aura' ); ?></option>
                        <option value="break">☕ <?php esc_html_e( 'Recesos / Descansos', 'aura' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- Acciones rápidas de vista -->
            <div style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">
                <?php if ( current_user_can( 'aura_cal_create_events' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" id="btn-create-event-modal" class="btn btn-indigo btn-shimmer btn-lift btn-trigger-agendar" style="padding: 8px 14px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                        ➕ <?php esc_html_e( 'Agendar Clase', 'aura' ); ?>
                    </button>
                <?php endif; ?>

                <button type="button" id="btn-filter-reset" class="btn btn-ghost" style="padding: 8px 14px; font-size: 13px;">
                    🧹 <?php esc_html_e( 'Limpiar Filtros', 'aura' ); ?>
                </button>

                <button type="button" id="btn-toggle-fullscreen" class="btn btn-ghost" style="padding: 8px 14px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;" title="<?php esc_attr_e( 'Ver calendario en pantalla completa', 'aura' ); ?>">
                    ⛶ <?php esc_html_e( 'Pantalla Completa', 'aura' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ── CONTENEDOR DEL FULLCALENDAR ── -->
    <div class="adp-card aura-calendar-card" style="padding: 20px; border-radius: 14px; box-shadow: var(--aura-shadow-sm, 0 1px 3px rgba(0,0,0,0.05)); position: relative;">
        <div id="aura-main-calendar" style="min-height: 700px;"></div>
    </div>

</div>
