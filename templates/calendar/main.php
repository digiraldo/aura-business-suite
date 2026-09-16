<?php
/**
 * Vista Principal del Módulo de Calendario y Horarios Académicos
 *
 * Sigue el Design System de Aura Suite (Protocolo P.E.E.):
 * - Variables CSS institucionales (--aura-surface, --aura-border, etc.)
 * - Badges con .pulse-dot horizontal
 * - Input groups con prefijos iconográficos
 * - Botones con clases .btn.btn-{color}.btn-shimmer.btn-lift
 * - Footer estándar .adp-footer
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tab = sanitize_key( $_GET['tab'] ?? ( $active_tab ?? 'calendar' ) );
$allowed_tabs = [ 'calendar', 'programs', 'grades', 'tasks', 'settings' ];
if ( ! in_array( $tab, $allowed_tabs, true ) ) {
    $tab = 'calendar';
}

$base_url = admin_url( 'admin.php?page=aura-calendar' );
$gcal_is_ready = Aura_Calendar_Google_Sync::is_enabled();
$cal_name      = Aura_Calendar_Google_Sync::get_calendar_name();
$cal_id        = get_option( Aura_Calendar_Google_Sync::CAL_ID_OPTION, '' );
$programs      = Aura_Calendar_Programs::get_all( [ 'status' => 'active', 'limit' => 100 ] );
?>


<div class="wrap aura-portal-wrap adp-settings-wrap" style="max-width: 1400px; margin: 20px auto; padding: 0 16px;">

    <!-- ── HEADER AURA SUITE ── -->
    <header class="adp-header" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <span class="adp-badge badge-indigo has-dot">
                        <span class="pulse-dot"></span> <?php esc_html_e( 'Módulo Académico', 'aura' ); ?>
                    </span>
                    <?php if ( $gcal_is_ready ) : ?>
                        <span class="adp-badge badge-emerald has-dot" title="<?php echo esc_attr( $cal_id ? 'ID: ' . $cal_id : 'Pendiente resolver' ); ?>">
                            <span class="pulse-dot"></span> Google Calendar Conectado
                        </span>
                    <?php else : ?>
                        <span class="adp-badge badge-amber has-dot">
                            <span class="pulse-dot"></span> Google Calendar Inactivo
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="adp-header-title" style="font-size: 26px; font-weight: 700; margin: 0; color: var(--aura-text-primary, #1e293b);">
                    📅 <?php esc_html_e( 'Calendario y Horarios de Clases', 'aura' ); ?>
                </h1>
                <p class="adp-header-desc" style="font-size: 14px; color: var(--aura-text-secondary, #64748b); margin-top: 4px;">
                    <?php esc_html_e( 'Gestión de horarios, programas, materias, asistencia, calificaciones y sincronización bidireccional con Google Calendar.', 'aura' ); ?>
                </p>
            </div>

            <!-- Acciones Rápidas del Header -->
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php if ( current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) : ?>
                    <button type="button" class="btn btn-indigo btn-shimmer btn-lift btn-trigger-agendar" id="btn-top-create-event">
                        ➕ <?php esc_html_e( 'Agendar Clase', 'aura' ); ?>
                    </button>
                <?php endif; ?>

                <?php if ( $gcal_is_ready && ( current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) ) : ?>
                    <button type="button" class="btn btn-emerald btn-shimmer btn-lift" id="btn-top-sync-gcal" title="<?php esc_attr_e( 'Sincronizar eventos con Google Calendar', 'aura' ); ?>">
                        🔄 <?php esc_html_e( 'Sincronizar GCal', 'aura' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── SUB-NAV TABS ── -->
        <nav class="adp-nav-tabs" style="display: flex; gap: 8px; margin-top: 24px; border-bottom: 2px solid var(--aura-border, #e2e8f0); padding-bottom: 0;">
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'calendar', $base_url ) ); ?>"
               class="adp-tab-btn <?php echo $tab === 'calendar' ? 'active' : ''; ?>"
               style="text-decoration: none; padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 8px 8px 0 0; display: inline-flex; align-items: center; gap: 6px;">
                🗓️ <?php esc_html_e( 'Calendario', 'aura' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'programs', $base_url ) ); ?>"
               class="adp-tab-btn <?php echo $tab === 'programs' ? 'active' : ''; ?>"
               style="text-decoration: none; padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 8px 8px 0 0; display: inline-flex; align-items: center; gap: 6px;">
                🎓 <?php esc_html_e( 'Programas y Materias', 'aura' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'grades', $base_url ) ); ?>"
               class="adp-tab-btn <?php echo $tab === 'grades' ? 'active' : ''; ?>"
               style="text-decoration: none; padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 8px 8px 0 0; display: inline-flex; align-items: center; gap: 6px;">
                📊 <?php esc_html_e( 'Calificaciones', 'aura' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'tasks', $base_url ) ); ?>"
               class="adp-tab-btn <?php echo $tab === 'tasks' ? 'active' : ''; ?>"
               style="text-decoration: none; padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 8px 8px 0 0; display: inline-flex; align-items: center; gap: 6px;">
                📝 <?php esc_html_e( 'Tareas y Evaluaciones', 'aura' ); ?>
            </a>
            <?php if ( current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>"
                   class="adp-tab-btn <?php echo $tab === 'settings' ? 'active' : ''; ?>"
                   style="text-decoration: none; padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 8px 8px 0 0; display: inline-flex; align-items: center; gap: 6px;">
                    ⚙️ <?php esc_html_e( 'Configuración', 'aura' ); ?>
                </a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- ── CONTENIDO DEL TAB SELECCIONADO ── -->
    <main class="adp-tab-content" style="margin-bottom: 40px;">
        <?php
        switch ( $tab ) {
            case 'programs':
                include AURA_PLUGIN_DIR . 'templates/calendar/tab-programs.php';
                break;
            case 'grades':
                include AURA_PLUGIN_DIR . 'templates/calendar/tab-grades.php';
                break;
            case 'tasks':
                include AURA_PLUGIN_DIR . 'templates/calendar/tab-tasks.php';
                break;
            case 'settings':
                include AURA_PLUGIN_DIR . 'templates/calendar/tab-settings.php';
                break;
            case 'calendar':
            default:
                include AURA_PLUGIN_DIR . 'templates/calendar/tab-calendar.php';
                break;
        }
        ?>
    </main>

    <?php
    // Modales centralizados para todo el módulo (Agendar Clase, Detalle, Asistencia)
    include AURA_PLUGIN_DIR . 'templates/calendar/modal-partials.php';
    ?>

    <!-- ── FOOTER PROTOCOLO P.E.E. ── -->
    <footer class="adp-footer" style="margin-top: 40px; padding: 20px 0; border-top: 1px solid var(--aura-border, #e2e8f0); text-align: center; font-size: 13px; color: var(--aura-text-muted, #94a3b8);">
        <p style="margin: 0;">
            <?php printf( esc_html__( 'Aura Business Suite — Módulo de Calendario y Horarios Académicos v%s', 'aura' ), esc_html( AURA_VERSION ) ); ?>
            &bull; <a href="https://github.com" target="_blank" style="color: var(--aura-primary); text-decoration: none;"><?php esc_html_e( 'Documentación y Soporte', 'aura' ); ?></a>
        </p>
    </footer>
</div>
