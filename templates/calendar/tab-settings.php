<?php
/**
 * Tab 5: Configuración del Calendario y Google Calendar
 *
 * Configuración del nombre del calendario compartido, sincronización automática
 * y resolución del Calendar ID con la Service Account de Google.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_gcal_enabled = Aura_Calendar_Google_Sync::is_enabled();
$cal_name        = Aura_Calendar_Google_Sync::get_calendar_name();
$cal_id          = get_option( Aura_Calendar_Google_Sync::CAL_ID_OPTION, '' );
$auto_sync       = get_option( Aura_Calendar_Google_Sync::AUTO_SYNC_OPTION, '1' );
?>

<div class="aura-calendar-settings-container" style="max-width: 860px;">

    <!-- ── ESTADO DEL SERVICIO GLOBAL GOOGLE CALENDAR ── -->
    <?php if ( $is_gcal_enabled ) : ?>
        <div class="alert-card alert-success" style="margin-bottom: 24px; padding: 18px 22px; border-radius: 12px; display: flex; align-items: flex-start; gap: 14px;">
            <div style="font-size: 24px; line-height: 1;">✅</div>
            <div>
                <strong style="font-size: 15px; display: block; margin-bottom: 4px;">
                    <?php esc_html_e( 'Servicio de Google Calendar Activo', 'aura' ); ?>
                </strong>
                <span style="font-size: 13px; color: var(--aura-text-secondary);">
                    <?php esc_html_e( 'Las credenciales de Google Service Account están configuradas y listas para sincronizar clases y eventos.', 'aura' ); ?>
                </span>
            </div>
        </div>
    <?php else : ?>
        <div class="alert-card alert-warning" style="margin-bottom: 24px; padding: 18px 22px; border-radius: 12px; display: flex; align-items: flex-start; gap: 14px;">
            <div style="font-size: 24px; line-height: 1;">⚠️</div>
            <div>
                <strong style="font-size: 15px; display: block; margin-bottom: 4px;">
                    <?php esc_html_e( 'Google Calendar no está configurado globalmente', 'aura' ); ?>
                </strong>
                <span style="font-size: 13px; color: var(--aura-text-secondary); display: block; margin-bottom: 10px;">
                    <?php esc_html_e( 'Para que las clases se sincronicen con Google Calendar, debes habilitar la integración y pegar tu JSON de Service Account en Ajustes Generales.', 'aura' ); ?>
                </span>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings&tab=gcal' ) ); ?>" class="btn btn-indigo btn-lift" style="font-size: 13px; padding: 6px 14px; text-decoration: none;">
                    ⚙️ <?php esc_html_e( 'Ir a Configuración Global de Google Calendar', 'aura' ); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── FORMULARIO DE AJUSTES DEL CALENDARIO ACADÉMICO ── -->
    <div class="adp-card" style="padding: 28px; border-radius: 12px; margin-bottom: 24px;">
        <h3 class="adp-card-title" style="font-size: 18px; margin: 0 0 8px 0;">
            📅 <?php esc_html_e( 'Ajustes del Calendario de Clases', 'aura' ); ?>
        </h3>
        <p class="adp-card-desc" style="margin: 0 0 20px 0;">
            <?php esc_html_e( 'Todos los programas y materias de la institución se sincronizan en un único calendario de Google con visibilidad centralizada.', 'aura' ); ?>
        </p>

        <form id="form-calendar-settings" style="display: flex; flex-direction: column; gap: 20px;">
            
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    <?php esc_html_e( 'Nombre del Calendario en Google Calendar', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                </label>
                <div class="input-group">
                    <span class="input-prefix">🏷️</span>
                    <input type="text" name="cal_name" id="set-cal-name" value="<?php echo esc_attr( $cal_name ); ?>" required class="form-control" placeholder="<?php esc_attr_e( 'Nombre del calendario...', 'aura' ); ?>" style="width: 100%; border-radius: 8px; padding-left: 38px;">
                </div>
                <small style="font-size: 12px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                    <?php esc_html_e( 'Si dejas este campo vacío, se usará automáticamente: "{Nombre Organización} - Clases".', 'aura' ); ?>
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    <?php esc_html_e( 'ID del Calendario Resuelto en Google (Calendar ID)', 'aura' ); ?>
                </label>
                <input type="text" readonly value="<?php echo esc_attr( $cal_id ?: __( 'No resuelto todavía (haz clic en "Probar Conexión")', 'aura' ) ); ?>" class="form-control" style="width: 100%; border-radius: 8px; background: var(--aura-surface-alt, #f8fafc); color: var(--aura-text-secondary); font-family: monospace;">
            </div>

            <div class="form-group" style="padding-top: 6px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                    <input type="checkbox" name="auto_sync" id="set-auto-sync" value="1" <?php checked( $auto_sync, '1' ); ?>>
                    ⚡ <?php esc_html_e( 'Sincronizar automáticamente con Google Calendar al crear, editar o mover clases', 'aura' ); ?>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-settings">
                    💾 <?php esc_html_e( 'Guardar Ajustes', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- ── ACCIONES DE SINCRONIZACIÓN MANUAL ── -->
    <?php if ( $is_gcal_enabled ) : ?>
        <div class="adp-card" style="padding: 24px; border-radius: 12px;">
            <h3 class="adp-card-title" style="font-size: 17px; margin: 0 0 6px 0;">
                🔄 <?php esc_html_e( 'Herramientas de Sincronización Manual', 'aura' ); ?>
            </h3>
            <p class="adp-card-desc" style="margin: 0 0 16px 0;">
                <?php esc_html_e( 'Utiliza estas herramientas para comprobar la conectividad o forzar la actualización masiva de eventos.', 'aura' ); ?>
            </p>

            <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                <button type="button" class="btn btn-ghost" id="btn-test-gcal-conn" style="padding: 9px 16px; font-size: 13px;">
                    🔌 <?php esc_html_e( 'Probar Conexión y Vincular Calendario', 'aura' ); ?>
                </button>

                <button type="button" class="btn btn-emerald btn-shimmer btn-lift" id="btn-sync-all-future" style="padding: 9px 16px; font-size: 13px;">
                    🚀 <?php esc_html_e( 'Sincronizar Todas las Clases Futuras', 'aura' ); ?>
                </button>
            </div>

            <div id="settings-sync-feedback" style="margin-top: 14px; display: none;"></div>
        </div>
    <?php endif; ?>

</div>
