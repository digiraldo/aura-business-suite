<?php
/**
 * Tab 5: Configuración del Calendario, Portal del Instructor y Google Calendar
 *
 * Configuración del Portal Frontend del Instructor [aura_teacher_portal],
 * nomenclatura y prefijos de códigos docentes, criterios de asignación CBAC
 * y sincronización centralizada con Google Calendar.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_gcal_enabled        = Aura_Calendar_Google_Sync::is_enabled();
$cal_name               = Aura_Calendar_Google_Sync::get_calendar_name();
$cal_id                 = get_option( Aura_Calendar_Google_Sync::CAL_ID_OPTION, '' );
$auto_sync              = get_option( Aura_Calendar_Google_Sync::AUTO_SYNC_OPTION, '1' );

$teacher_portal_page_id = Aura_Calendar_Admin::get_teacher_portal_page_id();
$teacher_code_prefix    = Aura_Calendar_Admin::get_teacher_code_prefix();
$instructors_list       = Aura_Calendar_Admin::get_instructors();
$all_wp_pages           = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );

$teacher_portal_url = $teacher_portal_page_id > 0 ? get_permalink( $teacher_portal_page_id ) : '';
?>

<div class="aura-calendar-settings-container" style="max-width: 920px;">

    <form id="form-calendar-settings" style="display: flex; flex-direction: column; gap: 24px;">

        <!-- ═════════════════════════════════════════════════════════════
             1. PORTAL FRONTEND DEL INSTRUCTOR Y GESTIÓN DE PROFESORES
             ═════════════════════════════════════════════════════════════ -->
        <div class="adp-card" style="padding: 28px; border-radius: 14px; box-shadow: var(--aura-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
                <div>
                    <span class="adp-badge badge-indigo has-dot" style="margin-bottom: 8px; display: inline-flex;">
                        <span class="pulse-dot"></span> <?php esc_html_e( 'Portal Institucional Docente', 'aura' ); ?>
                    </span>
                    <h3 class="adp-card-title" style="font-size: 19px; margin: 0 0 6px 0; display: flex; align-items: center; gap: 8px;">
                        <span>👨‍🏫</span> <?php esc_html_e( 'Portal Frontend del Instructor y Nomenclatura', 'aura' ); ?>
                    </h3>
                    <p class="adp-card-desc" style="margin: 0; font-size: 13px;">
                        <?php esc_html_e( 'Configura la página pública donde los profesores acceden a sus clases, pasan lista, asignan tareas y califican.', 'aura' ); ?>
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="aura-count-badge badge badge-emerald" style="padding: 6px 12px; font-size: 13px; font-weight: 600;">
                        <span class="dashicons dashicons-businessman" style="font-size: 15px; width: 15px; height: 15px;"></span>
                        <?php printf( esc_html__( '%d Instructores Habilitados', 'aura' ), count( $instructors_list ) ); ?>
                    </span>
                </div>
            </div>

            <!-- Shortcode Callout -->
            <div style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%); border: 1px solid rgba(79, 70, 229, 0.2); border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-indigo, #4f46e5); display: block; margin-bottom: 4px;">
                            <?php esc_html_e( 'Shortcode Oficial para la Vista de Profesores', 'aura' ); ?>
                        </span>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <code style="font-size: 15px; font-weight: 700; background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #c7d2fe); color: var(--aura-primary, #4f46e5); padding: 4px 10px; border-radius: 6px;">[aura_teacher_portal]</code>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="navigator.clipboard.writeText('[aura_teacher_portal]'); alert('¡Shortcode copiado al portapapeles!');" style="font-size: 12px; padding: 4px 10px;">
                                📋 <?php esc_html_e( 'Copiar', 'aura' ); ?>
                            </button>
                        </div>
                    </div>

                    <?php if ( ! empty( $teacher_portal_url ) ) : ?>
                        <a href="<?php echo esc_url( $teacher_portal_url ); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-indigo btn-lift" style="text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                            <span><?php esc_html_e( 'Ver Portal en Vivo', 'aura' ); ?></span>
                            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Campos de Configuración del Portal -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                
                <!-- Selector de Página WP -->
                <div class="form-group">
                    <label class="form-label" for="set-teacher-portal-page" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        <?php esc_html_e( 'Página asignada para el Portal del Instructor', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <div style="display: flex; gap: 8px;">
                        <select name="teacher_portal_page_id" id="set-teacher-portal-page" class="form-control" style="width: 100%; border-radius: 8px;">
                            <option value="0"><?php esc_html_e( '— Seleccionar página de WordPress —', 'aura' ); ?></option>
                            <?php foreach ( $all_wp_pages as $p ) : ?>
                                <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $teacher_portal_page_id, $p->ID ); ?>>
                                    <?php echo esc_html( $p->post_title ); ?> (#<?php echo (int) $p->ID; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm" id="btn-create-teacher-portal-page" title="<?php esc_attr_e( 'Crear página automáticamente si no existe', 'aura' ); ?>" style="white-space: nowrap; font-size: 12px; padding: 0 12px; flex-shrink: 0;">
                            ⚡ <?php esc_html_e( 'Auto-crear', 'aura' ); ?>
                        </button>
                    </div>
                    <small style="font-size: 12px; color: var(--aura-text-muted); display: block; margin-top: 5px;">
                        <?php esc_html_e( 'El plugin detectará esta página para redirigir a los instructores al autenticarse en el login unificado.', 'aura' ); ?>
                    </small>
                </div>

                <!-- Prefijo de Código Docente -->
                <div class="form-group">
                    <label class="form-label" for="set-teacher-code-prefix" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        <?php esc_html_e( 'Prefijo del Código de Instructor', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-prefix" style="font-size: 14px;">🏷️</span>
                        <input type="text" name="teacher_code_prefix" id="set-teacher-code-prefix" value="<?php echo esc_attr( $teacher_code_prefix ); ?>" maxlength="20" class="form-control" placeholder="CEM-PROF" style="width: 100%; border-radius: 8px; text-transform: uppercase; font-weight: 700;">
                    </div>
                    <small style="font-size: 12px; color: var(--aura-text-muted); display: block; margin-top: 5px;">
                        <?php esc_html_e( 'Estandariza los carnets y códigos docentes (Ej: CEM-PROF-001), en paridad con el prefijo estudiantil.', 'aura' ); ?>
                    </small>
                </div>

            </div>

            <!-- Caja Informativa de Criterios CBAC para Asignar Profesores -->
            <div style="background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #e2e8f0); border-radius: 10px; padding: 18px 20px;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                    <div style="max-width: 640px;">
                        <strong style="font-size: 14px; color: var(--aura-text-primary, #0f172a); display: block; margin-bottom: 4px;">
                            🛡️ <?php esc_html_e( '¿Cómo convertir a cualquier usuario del sistema en Instructor?', 'aura' ); ?>
                        </strong>
                        <p style="font-size: 12.5px; color: var(--aura-text-secondary, #475569); margin: 0 0 8px 0; line-height: 1.5;">
                            <?php esc_html_e( 'En Aura Suite no requieres volver a crear al usuario si ya existe en Formularios, Finanzas o WordPress. Simplemente dirígete al Gestor de Permisos CBAC y asígnale la plantilla de rol predefinida "Profesor / Docente" (academic_teacher).', 'aura' ); ?>
                        </p>
                        <ul style="font-size: 12px; color: var(--aura-text-muted, #64748b); margin: 0; padding-left: 18px;">
                            <li><?php esc_html_e( 'Habilita visualización de calendario y asignación como instructor titular en Materias.', 'aura' ); ?></li>
                            <li><?php esc_html_e( 'Permite pase de lista en vivo, registro de notas y publicación de tareas con libros vinculados.', 'aura' ); ?></li>
                        </ul>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-permissions' ) ); ?>" class="btn btn-sm btn-ghost" style="white-space: nowrap; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-shield"></span>
                        <span><?php esc_html_e( 'Ir a Permisos CBAC', 'aura' ); ?> →</span>
                    </a>
                </div>
            </div>

        </div>

        <!-- ═════════════════════════════════════════════════════════════
             2. SINCRONIZACIÓN GOOGLE CALENDAR
             ═════════════════════════════════════════════════════════════ -->
        <?php if ( $is_gcal_enabled ) : ?>
            <div class="alert-card alert-success" style="padding: 16px 20px; border-radius: 12px; display: flex; align-items: flex-start; gap: 14px;">
                <div style="font-size: 22px; line-height: 1;">✅</div>
                <div>
                    <strong style="font-size: 14px; display: block; margin-bottom: 2px;">
                        <?php esc_html_e( 'Servicio de Google Calendar Activo', 'aura' ); ?>
                    </strong>
                    <span style="font-size: 12.5px; color: var(--aura-text-secondary);">
                        <?php esc_html_e( 'Las credenciales de Google Service Account están configuradas y listas para sincronizar clases y eventos.', 'aura' ); ?>
                    </span>
                </div>
            </div>
        <?php else : ?>
            <div class="alert-card alert-warning" style="padding: 16px 20px; border-radius: 12px; display: flex; align-items: flex-start; gap: 14px;">
                <div style="font-size: 22px; line-height: 1;">⚠️</div>
                <div>
                    <strong style="font-size: 14px; display: block; margin-bottom: 2px;">
                        <?php esc_html_e( 'Google Calendar no está configurado globalmente', 'aura' ); ?>
                    </strong>
                    <span style="font-size: 12.5px; color: var(--aura-text-secondary); display: block; margin-bottom: 8px;">
                        <?php esc_html_e( 'Para sincronizar clases con Google Calendar en tiempo real, pega tu JSON de Service Account en Ajustes Generales.', 'aura' ); ?>
                    </span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings&tab=gcal' ) ); ?>" class="btn btn-indigo btn-sm btn-lift" style="font-size: 12px; padding: 5px 12px; text-decoration: none;">
                        ⚙️ <?php esc_html_e( 'Configurar Google Calendar Global', 'aura' ); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="adp-card" style="padding: 28px; border-radius: 14px; box-shadow: var(--aura-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));">
            <h3 class="adp-card-title" style="font-size: 18px; margin: 0 0 8px 0;">
                📅 <?php esc_html_e( 'Ajustes del Calendario de Google', 'aura' ); ?>
            </h3>
            <p class="adp-card-desc" style="margin: 0 0 20px 0; font-size: 13px;">
                <?php esc_html_e( 'Todos los programas y materias de la institución se sincronizan en un único calendario de Google con visibilidad centralizada.', 'aura' ); ?>
            </p>

            <div class="form-group" style="margin-bottom: 18px;">
                <label class="form-label" for="set-cal-name" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    <?php esc_html_e( 'Nombre del Calendario en Google Calendar', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                </label>
                <div class="input-group">
                    <span class="input-prefix">🏷️</span>
                    <input type="text" name="cal_name" id="set-cal-name" value="<?php echo esc_attr( $cal_name ); ?>" required class="form-control" placeholder="<?php esc_attr_e( 'Nombre del calendario...', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                </div>
                <small style="font-size: 12px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                    <?php esc_html_e( 'Si dejas este campo vacío, se usará automáticamente: "{Nombre Organización} - Clases".', 'aura' ); ?>
                </small>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
                <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                    <?php esc_html_e( 'ID del Calendario Resuelto en Google (Calendar ID)', 'aura' ); ?>
                </label>
                <input type="text" readonly value="<?php echo esc_attr( $cal_id ?: __( 'No resuelto todavía (haz clic en "Probar Conexión")', 'aura' ) ); ?>" class="form-control" style="width: 100%; border-radius: 8px; background: var(--aura-surface-alt, #f8fafc); color: var(--aura-text-secondary); font-family: monospace;">
                
                <?php if ( ! empty( $cal_id ) ) : 
                    $gcal_subscribe_url = 'https://calendar.google.com/calendar/render?cid=' . rawurlencode( $cal_id );
                    $shared_accounts    = get_option( 'aura_gcal_share_email', '' ) ?: get_option( 'admin_email', '' );
                ?>
                    <div style="margin-top: 10px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; background: rgba(99, 102, 241, 0.06); padding: 12px 16px; border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.2);">
                        <div style="font-size: 12px; color: var(--aura-text-secondary); max-width: 580px;">
                            <strong><?php esc_html_e( '¿No ves el calendario en tu cuenta personal?', 'aura' ); ?></strong><br>
                            <?php esc_html_e( 'Google Calendar requiere que aceptes la suscripción. Está compartido con:', 'aura' ); ?>
                            <code><?php echo esc_html( $shared_accounts ); ?></code>
                        </div>
                        <a href="<?php echo esc_url( $gcal_subscribe_url ); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-indigo btn-sm btn-lift" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 6px 14px;">
                            📅 <?php esc_html_e( 'Añadir a mi Google Calendar', 'aura' ); ?> ↗
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="padding-top: 4px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13.5px; cursor: pointer;">
                    <input type="checkbox" name="auto_sync" id="set-auto-sync" value="1" <?php checked( $auto_sync, '1' ); ?>>
                    ⚡ <?php esc_html_e( 'Sincronizar automáticamente con Google Calendar al crear, editar o mover clases', 'aura' ); ?>
                </label>
            </div>
        </div>

        <!-- Botón Global de Guardar -->
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 4px;">
            <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-settings" style="padding: 12px 26px; font-size: 14px; font-weight: 600;">
                💾 <?php esc_html_e( 'Guardar Todos los Ajustes', 'aura' ); ?>
            </button>
        </div>

    </form>

    <!-- ── ACCIONES DE SINCRONIZACIÓN MANUAL ── -->
    <?php if ( $is_gcal_enabled ) : ?>
        <div class="adp-card" style="padding: 24px; border-radius: 14px; margin-top: 24px;">
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
