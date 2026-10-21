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

<div class="aura-calendar-settings-container" style="width: 100%; max-width: 100%; box-sizing: border-box;">

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

        <!-- ═════════════════════════════════════════════════════════════
             2.1 CONTROL DE ACCESOS Y CALENDARIO COMPARTIDO (GOOGLE CALENDAR ACL - FULL CRUD)
             ═════════════════════════════════════════════════════════════ -->
        <div class="adp-card" id="card-gcal-acl-manager" style="padding: 28px; border-radius: 14px; box-shadow: var(--aura-shadow-sm, 0 1px 3px rgba(0,0,0,0.06)); background: #ffffff;">
            
            <!-- Encabezado con Título y Acciones Principales -->
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
                <div>
                    <span class="adp-badge badge-indigo has-dot" style="margin-bottom: 8px; display: inline-flex;">
                        <span class="pulse-dot"></span> <?php esc_html_e( 'Gestión Oficial de Permisos ACL — Google Calendar', 'aura' ); ?>
                    </span>
                    <h3 class="adp-card-title" style="font-size: 20px; margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--aura-text-primary, #0f172a);">
                        <span>🛡️</span> <?php esc_html_e( 'Control de Accesos y Calendario Compartido', 'aura' ); ?>
                    </h3>
                    <p class="adp-card-desc" style="margin: 0; font-size: 13.5px; color: var(--aura-text-secondary, #475569); max-width: 860px; line-height: 1.5;">
                        <?php esc_html_e( 'Gestiona de extremo a extremo (CRUD) los permisos del calendario en Google Calendar vinculado al Directorio de Contactos de Aura. Puedes conceder accesos, alternar roles entre Lector y Editor con 1 clic en tiempo real, o revocar de inmediato cuando un colaborador finalice su ciclo.', 'aura' ); ?>
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm btn-ghost" id="btn-refresh-gcal-acl" style="font-size: 12.5px; padding: 8px 14px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; border-radius: 8px;">
                        <span class="dashicons dashicons-update" style="font-size: 15px; width: 15px; height: 15px;"></span>
                        <?php esc_html_e( 'Sincronizar Lista', 'aura' ); ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-indigo btn-lift" id="btn-open-share-contact-modal" style="font-size: 13px; font-weight: 700; padding: 8px 18px; display: inline-flex; align-items: center; gap: 8px; border-radius: 8px; box-shadow: 0 4px 12px rgba(79,70,229,0.25);">
                        <span>➕</span> <?php esc_html_e( 'Compartir con un Contacto', 'aura' ); ?>
                    </button>
                </div>
            </div>

            <!-- Resumen de Métricas / KPI Badges de Accesos -->
            <div id="gcal-acl-kpis-bar" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; padding: 12px 18px; background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #e2e8f0); border-radius: 12px;">
                <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-muted, #64748b); margin-right: 6px;">
                    <?php esc_html_e( 'Estado ACL:', 'aura' ); ?>
                </div>
                <div id="kpi-badge-total" class="adp-badge badge-ghost" style="font-size: 12px; font-weight: 600; padding: 4px 10px;">
                    📊 <?php esc_html_e( 'Total:', 'aura' ); ?> <span id="kpi-count-total" style="font-weight: 700; margin-left: 4px;">0</span>
                </div>
                <div id="kpi-badge-writers" class="adp-badge badge-indigo" style="font-size: 12px; font-weight: 600; padding: 4px 10px;">
                    ✏️ <?php esc_html_e( 'Editores:', 'aura' ); ?> <span id="kpi-count-writers" style="font-weight: 700; margin-left: 4px;">0</span>
                </div>
                <div id="kpi-badge-readers" class="adp-badge badge-blue" style="font-size: 12px; font-weight: 600; padding: 4px 10px; background: rgba(59,130,246,0.1); color: #1d4ed8;">
                    👁️ <?php esc_html_e( 'Lectores:', 'aura' ); ?> <span id="kpi-count-readers" style="font-weight: 700; margin-left: 4px;">0</span>
                </div>
                <div id="kpi-badge-contacts" class="adp-badge badge-emerald" style="font-size: 12px; font-weight: 600; padding: 4px 10px;">
                    ✓ <?php esc_html_e( 'Directorio Aura:', 'aura' ); ?> <span id="kpi-count-contacts" style="font-weight: 700; margin-left: 4px;">0</span>
                </div>
                <div id="kpi-badge-system" class="adp-badge badge-ghost" style="font-size: 12px; font-weight: 600; padding: 4px 10px; opacity: 0.85;">
                    ⚙️ <?php esc_html_e( 'Sistema / API:', 'aura' ); ?> <span id="kpi-count-system" style="font-weight: 700; margin-left: 4px;">0</span>
                </div>
            </div>

            <!-- Callout Informativo de Trazabilidad con Contactos de Aura -->
            <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(59, 130, 246, 0.04) 100%); border: 1px solid rgba(99, 102, 241, 0.22); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div style="font-size: 13px; color: var(--aura-text-secondary, #334155); line-height: 1.5; max-width: 820px;">
                    <strong style="color: var(--aura-primary, #4f46e5); display: inline-flex; align-items: center; gap: 6px;">
                        <span>👥</span> <?php esc_html_e( 'Directorio Maestro de Contactos:', 'aura' ); ?>
                    </strong>
                    <?php esc_html_e( 'Para maximizar la trazabilidad y seguridad institucional, las invitaciones se cruzan directamente con la ficha de Contactos de Aura Suite. Si el contacto cuenta con correo principal o secundario, puedes auditarlo y cambiar su rol de lectura/escritura de forma reactiva sin interrumpir la sincronización.', 'aura' ); ?>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-contacts' ) ); ?>" class="btn btn-sm btn-ghost btn-lift" target="_blank" style="font-size: 12px; white-space: nowrap; text-decoration: none; font-weight: 600; padding: 6px 14px; border: 1px solid rgba(99,102,241,0.25); border-radius: 8px;">
                    👥 <?php esc_html_e( 'Gestionar Contactos', 'aura' ); ?> ↗
                </a>
            </div>

            <!-- Barra de Búsqueda y Filtros Rápidos en Vivo -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                <div style="position: relative; flex: 1; min-width: 260px; max-width: 440px;">
                    <input type="text" id="gcal-acl-filter-search" class="form-control" placeholder="<?php esc_attr_e( 'Filtrar por nombre, correo o empresa...', 'aura' ); ?>" style="width: 100%; border-radius: 8px; padding-left: 36px; height: 38px; font-size: 13px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; opacity: 0.5;">🔍</span>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="gcal-acl-filter-role" style="font-size: 12.5px; font-weight: 600; color: var(--aura-text-secondary, #64748b);">
                        <?php esc_html_e( 'Filtrar rol:', 'aura' ); ?>
                    </label>
                    <select id="gcal-acl-filter-role" class="form-control" style="border-radius: 8px; height: 38px; font-size: 12.5px; padding: 0 12px; min-width: 170px;">
                        <option value="all"><?php esc_html_e( 'Todos los Roles', 'aura' ); ?></option>
                        <option value="owner"><?php esc_html_e( '👑 Propietarios', 'aura' ); ?></option>
                        <option value="writer"><?php esc_html_e( '✏️ Editores (Escritura)', 'aura' ); ?></option>
                        <option value="reader"><?php esc_html_e( '👁️ Lectores (Solo Lectura)', 'aura' ); ?></option>
                        <option value="contacts_only"><?php esc_html_e( '✓ Solo Directorio Aura', 'aura' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- Feedback de ACL -->
            <div id="gcal-acl-feedback" style="margin-bottom: 16px; display: none;"></div>

            <!-- Tabla de Usuarios Compartidos (ACL - Full CRUD) -->
            <div class="adp-table-responsive" style="border: 1px solid var(--aura-border, #e2e8f0); border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <table class="adp-table" id="table-gcal-acl-users" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--aura-surface-alt, #f8fafc); border-bottom: 2px solid var(--aura-border, #e2e8f0); text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-secondary, #475569);">
                            <th style="padding: 14px 18px; width: 32%;"><?php esc_html_e( 'Contacto en Directorio', 'aura' ); ?></th>
                            <th style="padding: 14px 18px; width: 28%;"><?php esc_html_e( 'Cuenta Google Autorizada', 'aura' ); ?></th>
                            <th style="padding: 14px 18px; width: 20%;"><?php esc_html_e( 'Nivel de Permiso (Rol)', 'aura' ); ?></th>
                            <th style="padding: 14px 18px; width: 10%;"><?php esc_html_e( 'Trazabilidad', 'aura' ); ?></th>
                            <th style="padding: 14px 18px; text-align: right; width: 10%;"><?php esc_html_e( 'Acciones', 'aura' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-gcal-acl-users">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 32px; color: var(--aura-text-muted);">
                                <span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
                                <?php esc_html_e( 'Cargando accesos de Google Calendar...', 'aura' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════
             3. CATÁLOGO DE EVENTOS GENÉRICOS Y RÁPIDOS (CRUD)
             ═════════════════════════════════════════════════════════════ -->
        <?php
        $generic_events = class_exists( 'Aura_Calendar_Generic_Events' ) ? Aura_Calendar_Generic_Events::get_all() : [];
        $type_labels = [
            'class'    => __( 'Clase regular', 'aura' ),
            'exam'     => __( 'Examen / Evaluación', 'aura' ),
            'workshop' => __( 'Taller / Laboratorio', 'aura' ),
            'activity' => __( 'Actividad / Devocional / Deporte', 'aura' ),
            'break'    => __( 'Receso / Descanso / Comida', 'aura' ),
            'other'    => __( 'Otro', 'aura' ),
        ];
        ?>
        <div class="adp-card" style="padding: 28px; border-radius: 14px; box-shadow: var(--aura-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
                <div>
                    <span class="adp-badge badge-emerald has-dot" style="margin-bottom: 8px; display: inline-flex;">
                        <span class="pulse-dot"></span> <?php esc_html_e( 'Agilidad en Planificación', 'aura' ); ?>
                    </span>
                    <h3 class="adp-card-title" style="font-size: 19px; margin: 0 0 6px 0; display: flex; align-items: center; gap: 8px;">
                        <span>⚡</span> <?php esc_html_e( 'Catálogo de Eventos Genéricos y Rápidos', 'aura' ); ?>
                    </h3>
                    <p class="adp-card-desc" style="margin: 0; font-size: 13px;">
                        <?php esc_html_e( 'Configura los eventos rápidos predefinidos (recesos, devocionales, actividades, comidas) que se pueden añadir al calendario con 1 clic en el modal con duración automática personalizable.', 'aura' ); ?>
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm btn-ghost" id="btn-reset-generic-events" title="<?php esc_attr_e( 'Restablecer a los 11 eventos iniciales recomendados', 'aura' ); ?>" style="font-size: 12px; padding: 6px 12px;">
                        ↺ <?php esc_html_e( 'Restablecer Predeterminados', 'aura' ); ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-indigo btn-lift" id="btn-add-generic-event" style="font-size: 12px; font-weight: 600; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
                        ➕ <?php esc_html_e( 'Agregar Evento Genérico', 'aura' ); ?>
                    </button>
                </div>
            </div>

            <!-- Tabla de Eventos Genéricos -->
            <div class="adp-table-responsive" style="border: 1px solid var(--aura-border, #e2e8f0); border-radius: 10px; overflow: hidden;">
                <table class="adp-table" id="table-generic-events" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--aura-surface-alt, #f8fafc); border-bottom: 1px solid var(--aura-border, #e2e8f0); text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-secondary, #64748b);">
                            <th style="padding: 12px 16px;"><?php esc_html_e( 'Icono & Nombre', 'aura' ); ?></th>
                            <th style="padding: 12px 16px;"><?php esc_html_e( 'Tipo de Evento', 'aura' ); ?></th>
                            <th style="padding: 12px 16px;"><?php esc_html_e( 'Duración Auto', 'aura' ); ?></th>
                            <th style="padding: 12px 16px;"><?php esc_html_e( 'Color Distintivo', 'aura' ); ?></th>
                            <th style="padding: 12px 16px;"><?php esc_html_e( 'Estado', 'aura' ); ?></th>
                            <th style="padding: 12px 16px; text-align: right;"><?php esc_html_e( 'Acciones', 'aura' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-generic-events">
                        <?php foreach ( $generic_events as $gev ) : 
                            $gev_id    = esc_attr( $gev['id'] );
                            $gev_name  = esc_html( $gev['name'] );
                            $gev_icon  = esc_html( $gev['icon'] ?: '⚡' );
                            $gev_dur   = intval( $gev['duration'] ?: 30 );
                            $gev_type  = esc_attr( $gev['type'] ?: 'break' );
                            $gev_color = esc_attr( $gev['color'] ?: '#5D5FEF' );
                            $gev_act   = ! empty( $gev['active'] );
                        ?>
                            <tr data-generic-id="<?php echo $gev_id; ?>" style="border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                                <td style="padding: 12px 16px; font-weight: 600;">
                                    <span style="font-size: 16px; margin-right: 6px;"><?php echo $gev_icon; ?></span>
                                    <span><?php echo $gev_name; ?></span>
                                </td>
                                <td style="padding: 12px 16px; font-size: 13px; color: var(--aura-text-secondary);">
                                    <?php echo esc_html( $type_labels[ $gev_type ] ?? ucfirst( $gev_type ) ); ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span class="adp-badge badge-ghost" style="font-size: 12px; font-weight: 600;">
                                        ⏱️ <?php printf( esc_html__( '%d min', 'aura' ), $gev_dur ); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <div style="display: inline-flex; align-items: center; gap: 8px;">
                                        <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: <?php echo $gev_color; ?>; box-shadow: 0 0 0 2px rgba(0,0,0,0.1);"></span>
                                        <code style="font-size: 11px;"><?php echo $gev_color; ?></code>
                                    </div>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php if ( $gev_act ) : ?>
                                        <span class="adp-badge badge-emerald" style="font-size: 11px;">✓ <?php esc_html_e( 'Activo', 'aura' ); ?></span>
                                    <?php else : ?>
                                        <span class="adp-badge badge-ghost" style="font-size: 11px; opacity: 0.6;"><?php esc_html_e( 'Inactivo', 'aura' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-ghost btn-sm btn-edit-generic-event" 
                                            data-id="<?php echo $gev_id; ?>"
                                            data-name="<?php echo esc_attr( $gev['name'] ); ?>"
                                            data-icon="<?php echo esc_attr( $gev['icon'] ); ?>"
                                            data-duration="<?php echo $gev_dur; ?>"
                                            data-type="<?php echo $gev_type; ?>"
                                            data-color="<?php echo $gev_color; ?>"
                                            data-active="<?php echo $gev_act ? 1 : 0; ?>"
                                            style="padding: 4px 8px; font-size: 12px;">
                                        ✏️ <?php esc_html_e( 'Editar', 'aura' ); ?>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-sm btn-delete-generic-event" data-id="<?php echo $gev_id; ?>" data-name="<?php echo esc_attr( $gev['name'] ); ?>" style="padding: 4px 8px; font-size: 12px; color: #ef4444;">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

                <button type="button" class="btn btn-ghost" id="btn-repair-cal-db" style="padding: 9px 16px; font-size: 13px;" title="<?php esc_attr_e( 'Repara y sincroniza tablas, columnas de profesores y roles en la base de datos.', 'aura' ); ?>">
                    🛠️ <?php esc_html_e( 'Sincronizar y Reparar BD', 'aura' ); ?>
                </button>
            </div>

            <div id="settings-sync-feedback" style="margin-top: 14px; display: none;"></div>

            <!-- Visor del Registro Detallado de la Última Sincronización -->
            <?php
            $last_sync_log = Aura_Calendar_Google_Sync::get_last_sync_log();
            ?>
            <div id="box-gcal-sync-log" style="margin-top: 20px; <?php echo empty( $last_sync_log['items'] ) ? 'display: none;' : ''; ?>">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid var(--aura-border, #e2e8f0); padding-bottom: 8px;">
                    <strong style="font-size: 13.5px; color: var(--aura-text-primary, #0f172a); display: flex; align-items: center; gap: 6px;">
                        📋 <?php esc_html_e( 'Registro de la Última Sincronización', 'aura' ); ?>
                        <span id="gcal-sync-log-timestamp" style="font-weight: 400; font-size: 12px; color: var(--aura-text-muted, #64748b);">
                            <?php if ( ! empty( $last_sync_log['synced_at'] ) ) : ?>
                                (<?php echo esc_html( $last_sync_log['synced_at'] ); ?>)
                            <?php endif; ?>
                        </span>
                    </strong>
                    <div id="gcal-sync-log-summary" style="display: flex; gap: 8px; font-size: 12px;">
                        <?php if ( ! empty( $last_sync_log ) ) : ?>
                            <span class="badge" style="background: rgba(16,185,129,0.12); color: #059669; font-weight: 600; padding: 2px 8px; border-radius: 6px;">
                                ✅ <?php echo (int) ( $last_sync_log['synced'] ?? 0 ); ?> <?php esc_html_e( 'Correctos', 'aura' ); ?>
                            </span>
                            <?php if ( ! empty( $last_sync_log['failed'] ) ) : ?>
                                <span class="badge" style="background: rgba(239,68,68,0.12); color: #dc2626; font-weight: 600; padding: 2px 8px; border-radius: 6px;">
                                    ❌ <?php echo (int) $last_sync_log['failed']; ?> <?php esc_html_e( 'Errores', 'aura' ); ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="gcal-sync-log-table-container" style="max-height: 280px; overflow-y: auto; border: 1px solid var(--aura-border, #e2e8f0); border-radius: 8px;">
                    <table class="adp-table" style="width: 100%; font-size: 12.5px; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--aura-surface-alt, #f8fafc); text-align: left; border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                                <th style="padding: 8px 12px;"><?php esc_html_e( 'Evento', 'aura' ); ?></th>
                                <th style="padding: 8px 12px;"><?php esc_html_e( 'Horario / Rango', 'aura' ); ?></th>
                                <th style="padding: 8px 12px; width: 110px;"><?php esc_html_e( 'Estado', 'aura' ); ?></th>
                                <th style="padding: 8px 12px;"><?php esc_html_e( 'Detalle / Razón', 'aura' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="gcal-sync-log-tbody">
                            <?php if ( ! empty( $last_sync_log['items'] ) ) : ?>
                                <?php foreach ( $last_sync_log['items'] as $item ) : ?>
                                    <tr style="border-bottom: 1px solid var(--aura-border, #f1f5f9);">
                                        <td style="padding: 8px 12px; font-weight: 600;">
                                            #<?php echo (int) $item['id']; ?> <?php echo esc_html( $item['title'] ); ?>
                                        </td>
                                        <td style="padding: 8px 12px; color: var(--aura-text-secondary, #475569); font-size: 11.5px;">
                                            <?php echo esc_html( $item['dates'] ); ?>
                                        </td>
                                        <td style="padding: 8px 12px;">
                                            <?php if ( ! empty( $item['success'] ) ) : ?>
                                                <span style="color: #10b981; font-weight: 600;">✅ Sincronizado</span>
                                            <?php else : ?>
                                                <span style="color: #ef4444; font-weight: 600;">❌ Falló</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 8px 12px; color: <?php echo ! empty( $item['success'] ) ? '#64748b' : '#ef4444'; ?>; font-size: 12px;">
                                            <?php echo esc_html( $item['message'] ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: CREADOR / EDITOR DE EVENTO GENÉRICO
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-generic-event-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 520px;">
        <div class="aura-modal-header">
            <h3 id="modal-generic-event-title" class="adp-card-title" style="margin: 0; font-size: 17px;">
                ⚡ <?php esc_html_e( 'Evento Genérico / Rápido', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-generic-event-editor">&times;</button>
        </div>

        <form id="form-generic-event-editor" class="aura-modal-form">
            <input type="hidden" name="id" id="gen-id" value="">

            <div class="aura-modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
                
                <!-- Nombre e Icono -->
                <div style="display: grid; grid-template-columns: 80px 1fr; gap: 12px; align-items: flex-end;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 12px; margin-bottom: 4px; display: block;">
                            <?php esc_html_e( 'Icono', 'aura' ); ?>
                        </label>
                        <input type="text" name="icon" id="gen-icon" value="⚡" maxlength="4" class="form-control" style="font-size: 20px; text-align: center; border-radius: 8px; padding: 6px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 12px; margin-bottom: 4px; display: block;">
                            <?php esc_html_e( 'Nombre del Evento', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="name" id="gen-name" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Descanso, Meditación, Refrigerio...', 'aura' ); ?>" style="border-radius: 8px; padding: 8px 12px;">
                    </div>
                </div>

                <!-- Duración y Tipo -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 12px; margin-bottom: 4px; display: block;">
                            ⏱️ <?php esc_html_e( 'Duración (minutos)', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="number" name="duration" id="gen-duration" value="30" min="5" max="1440" step="5" required class="form-control" style="border-radius: 8px; padding: 8px 12px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 12px; margin-bottom: 4px; display: block;">
                            📌 <?php esc_html_e( 'Tipo de Evento', 'aura' ); ?>
                        </label>
                        <select name="type" id="gen-type" class="form-control" style="border-radius: 8px; padding: 8px 12px;">
                            <option value="break">☕ <?php esc_html_e( 'Receso / Comida', 'aura' ); ?></option>
                            <option value="activity">🎯 <?php esc_html_e( 'Actividad / Devocional', 'aura' ); ?></option>
                            <option value="class">📖 <?php esc_html_e( 'Clase / Lectura', 'aura' ); ?></option>
                            <option value="workshop">🔬 <?php esc_html_e( 'Taller / Práctica', 'aura' ); ?></option>
                            <option value="exam">📝 <?php esc_html_e( 'Examen / Evaluación', 'aura' ); ?></option>
                            <option value="other">📍 <?php esc_html_e( 'Otro', 'aura' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Color Distintivo -->
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; font-size: 12px; margin-bottom: 4px; display: block;">
                        🎨 <?php esc_html_e( 'Color Distintivo', 'aura' ); ?>
                    </label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="color" name="color" id="gen-color" value="#5D5FEF" style="width: 44px; height: 38px; border: 1px solid var(--aura-border, #cbd5e1); border-radius: 8px; padding: 2px; cursor: pointer;">
                        <input type="text" id="gen-color-hex" value="#5D5FEF" class="form-control" style="width: 110px; border-radius: 8px; font-family: monospace; font-size: 13px;" readonly>
                    </div>
                </div>

                <!-- Estado Activo -->
                <div class="form-group" style="padding-top: 4px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" id="gen-active" value="1" checked>
                        <span><?php esc_html_e( 'Habilitado para mostrarse en el modal del calendario', 'aura' ); ?></span>
                    </label>
                </div>

                <div id="gen-editor-msg" style="display: none;"></div>

            </div>

            <div class="aura-modal-footer" style="padding: 14px 20px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--aura-border, #e2e8f0);">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-generic-event-editor">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-lift" id="btn-save-generic-item">
                    💾 <?php esc_html_e( 'Guardar Evento', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: COMPARTIR CALENDARIO CON CONTACTO DE AURA (GOOGLE CALENDAR ACL)
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-gcal-share-contact" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 560px; border-radius: 16px; overflow: hidden; padding: 0;">
        
        <!-- Header con degradado Glassmorphism -->
        <div class="aura-modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: #fff; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: none;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 24px; line-height: 1;">🛡️</span>
                <div>
                    <h3 style="margin: 0; font-size: 16.5px; font-weight: 700; color: #fff; line-height: 1.2;">
                        <?php esc_html_e( 'Compartir Calendario con un Contacto', 'aura' ); ?>
                    </h3>
                    <p style="margin: 2px 0 0 0; font-size: 12px; opacity: 0.9; color: rgba(255,255,255,0.9);">
                        <?php esc_html_e( 'Otorga acceso oficial mediante Google Calendar ACL', 'aura' ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-gcal-share-contact" style="color: #fff; font-size: 20px; line-height: 1; padding: 6px 10px; border-radius: 8px; background: rgba(255,255,255,0.15); border: none; cursor: pointer;">&times;</button>
        </div>

        <form id="form-gcal-share-contact" class="aura-modal-form" style="margin: 0; display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <div class="aura-modal-body" style="padding: 22px 24px; overflow-y: auto; max-height: min(72vh, 600px); display: flex; flex-direction: column; gap: 18px;">
                
                <!-- Paso 1: Buscador de Contactos -->
                <div class="form-group" style="margin: 0; position: relative;">
                    <label class="form-label" for="gcal-search-contact-input" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block; color: var(--aura-text-primary, #0f172a);">
                        👥 <?php esc_html_e( 'Buscar Contacto en el Directorio', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="text" id="gcal-search-contact-input" class="form-control" placeholder="<?php esc_attr_e( 'Escribe nombre, apellido, empresa o correo...', 'aura' ); ?>" autocomplete="off" style="width: 100%; border-radius: 8px; padding-left: 36px; padding-right: 36px; height: 40px; font-size: 13.5px;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; opacity: 0.5;">🔍</span>
                        <div id="gcal-search-contact-spinner" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); display: none;">
                            <span class="spinner is-active" style="float: none; margin: 0;"></span>
                        </div>
                    </div>
                    <small style="font-size: 11.5px; color: var(--aura-text-muted, #64748b); display: block; margin-top: 5px;">
                        <?php esc_html_e( 'Solo se muestran contactos que tengan al menos un correo electrónico registrado en el Directorio.', 'aura' ); ?>
                    </small>

                    <!-- Lista desplegable flotante de resultados -->
                    <div id="gcal-contact-dropdown-results" style="display: none; position: absolute; z-index: 1000; left: 0; right: 0; top: 100%; max-height: 230px; overflow-y: auto; background: #ffffff; border: 1px solid var(--aura-border, #cbd5e1); border-radius: 10px; box-shadow: 0 12px 24px -4px rgba(0,0,0,0.15); margin-top: 6px;"></div>
                </div>

                <!-- Tarjeta de Contacto Seleccionado -->
                <div id="gcal-selected-contact-card" style="display: none; background: rgba(79, 70, 229, 0.05); border: 1px solid rgba(79, 70, 229, 0.3); border-radius: 10px; padding: 14px 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div id="gcal-selected-avatar" style="width: 44px; height: 44px; border-radius: 50%; background: #4f46e5; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; overflow: hidden; flex-shrink: 0; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></div>
                            <div>
                                <h4 id="gcal-selected-name" style="margin: 0 0 2px 0; font-size: 14px; font-weight: 700; color: var(--aura-text-primary, #0f172a);"></h4>
                                <span id="gcal-selected-meta" style="font-size: 12px; color: var(--aura-text-secondary, #475569);"></span>
                            </div>
                        </div>
                        <button type="button" id="btn-gcal-unselect-contact" class="btn btn-ghost btn-sm" style="font-size: 12px; padding: 4px 10px; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px;">
                            ✕ <?php esc_html_e( 'Cambiar', 'aura' ); ?>
                        </button>
                    </div>
                </div>

                <input type="hidden" name="contact_id" id="gcal-share-contact-id" value="">

                <!-- Paso 2: Selector de Correo Electrónico (cuando tiene 1 o 2) -->
                <div id="gcal-email-selection-container" style="display: none;">
                    <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block; color: var(--aura-text-primary, #0f172a);">
                        ✉️ <?php esc_html_e( 'Selecciona la Cuenta / Correo para Compartir', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <div id="gcal-email-options-list" style="display: flex; flex-direction: column; gap: 8px;"></div>
                    <small style="font-size: 11.5px; color: var(--aura-text-muted, #64748b); display: block; margin-top: 6px;">
                        <?php esc_html_e( 'Elige la cuenta con la que este contacto iniciará sesión en Google Calendar.', 'aura' ); ?>
                    </small>
                </div>

                <!-- Paso 3: Selector de Nivel de Rol / Permiso -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="gcal-share-role" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block; color: var(--aura-text-primary, #0f172a);">
                        🔑 <?php esc_html_e( 'Nivel de Permiso (Rol ACL en Google Calendar)', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="role" id="gcal-share-role" class="form-control" style="width: 100%; border-radius: 8px; height: 40px; font-size: 13.5px;">
                        <option value="reader" selected>👁️ <?php esc_html_e( 'Lector (Solo Lectura) — Consulta eventos y horarios (Recomendado para Docentes/Alumnos)', 'aura' ); ?></option>
                        <option value="writer">✏️ <?php esc_html_e( 'Editor (Lectura y Escritura) — Puede crear, editar y mover clases en Google Calendar', 'aura' ); ?></option>
                    </select>
                </div>

                <!-- Paso 4: Enviar Notificación de Invitación -->
                <div style="background: var(--aura-surface-alt, #f8fafc); padding: 12px 14px; border-radius: 10px; border: 1px solid var(--aura-border, #e2e8f0);">
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; cursor: pointer; margin: 0;">
                        <input type="checkbox" name="send_notifications" id="gcal-share-notifications" value="1" checked style="margin-top: 3px;">
                        <div>
                            <strong style="display: block; color: var(--aura-text-primary, #0f172a); font-size: 13px;"><?php esc_html_e( 'Enviar correo oficial de invitación desde Google', 'aura' ); ?></strong>
                            <span style="font-size: 12px; color: var(--aura-text-secondary, #475569); display: block; line-height: 1.4;">
                                <?php esc_html_e( 'Google Calendar enviará un correo automático con el botón "Añadir este calendario" a la cuenta seleccionada.', 'aura' ); ?>
                            </span>
                        </div>
                    </label>
                </div>

                <!-- Mensajes de feedback dentro del modal -->
                <div id="modal-gcal-share-feedback" style="display: none;"></div>

            </div>

            <div class="aura-modal-footer" style="padding: 16px 24px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--aura-border, #e2e8f0); background: #fafafa; flex-shrink: 0;">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-gcal-share-contact" style="padding: 8px 16px; font-size: 13px;">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-lift" id="btn-confirm-gcal-share" disabled style="font-weight: 600; padding: 8px 22px; font-size: 13.5px;">
                    🚀 <?php esc_html_e( 'Conceder Acceso y Compartir', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

