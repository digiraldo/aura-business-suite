<?php
/**
 * Template: Formulario de Registro de Estudiante — Fase 3
 *
 * Página independiente para registrar un nuevo estudiante.
 * El guardado se realiza vía AJAX y redirige al listado.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$can_create  = current_user_can( 'aura_students_create' )   || current_user_can( 'manage_options' );
$can_edit    = current_user_can( 'aura_students_edit' )     || current_user_can( 'manage_options' );
$can_notes   = current_user_can( 'aura_students_view_all' ) || current_user_can( 'manage_options' );
$list_url    = admin_url( 'admin.php?page=aura-students-list' );
?>

<div class="wrap aura-app-container aura-student-form-page">

    <!-- ─── CABECERA ─────────────────────────────────────────── -->
    <?php
    Aura_UI::render_page_header([
        'title'    => __( 'Nuevo Estudiante', 'aura-suite' ),
        'subtitle' => __( 'Completa la información personal, postulación y preferencias del nuevo registro.', 'aura-suite' ),
        'icon'     => 'dashicons-plus-alt',
        'badge'    => __( 'Registro', 'aura-suite' ),
        'actions'  => [
            [
                'label'   => __( 'Volver al listado', 'aura-suite' ),
                'url'     => $list_url,
                'class'   => 'aura-btn aura-btn-secondary',
                'icon'    => 'dashicons-arrow-left-alt',
            ],
        ],
    ]);
    ?>

    <!-- ─── AVISO (se muestra tras guardar/error) ────────────── -->
    <div id="form-notice" style="display:none; margin: var(--aura-space-4, 16px) 0;"></div>

    <!-- ─── FORMULARIO PRINCIPAL ─────────────────────────────── -->
    <div style="max-width: 900px; margin-top: var(--aura-space-6, 24px);">
        <form id="form-new-student" novalidate>
            <input type="hidden" id="student-id" name="id" value="0">

            <div class="aura-card" style="padding: 0; overflow: hidden;">

                <!-- ── Tabs de secciones ──────────────────────────── -->
                <nav class="aura-sfp-tabs" role="tablist" style="display:flex; gap:4px; padding: 8px 16px; background: var(--aura-surface-alt, #f8fafc); border-bottom: 1px solid var(--aura-border, #e2e8f0); flex-wrap: wrap;">
                    <button type="button" class="aura-sfp-tab active" data-tab="personal" style="padding: 10px 16px; border: 1px solid transparent; background: transparent; border-radius: var(--aura-radius-md, 8px); cursor: pointer; font-size: 13px; font-weight: 600; color: var(--aura-text-secondary, #64748b); transition: all .15s ease; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-admin-users" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php _e( 'Datos personales', 'aura-suite' ); ?>
                    </button>
                    <button type="button" class="aura-sfp-tab" data-tab="postul" style="padding: 10px 16px; border: 1px solid transparent; background: transparent; border-radius: var(--aura-radius-md, 8px); cursor: pointer; font-size: 13px; font-weight: 600; color: var(--aura-text-secondary, #64748b); transition: all .15s ease; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-clipboard" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php _e( 'Postulación', 'aura-suite' ); ?>
                    </button>
                    <button type="button" class="aura-sfp-tab" data-tab="areas" style="padding: 10px 16px; border: 1px solid transparent; background: transparent; border-radius: var(--aura-radius-md, 8px); cursor: pointer; font-size: 13px; font-weight: 600; color: var(--aura-text-secondary, #64748b); transition: all .15s ease; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-category" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php _e( 'Áreas de interés', 'aura-suite' ); ?>
                    </button>
                    <?php if ( $can_notes ) : ?>
                    <button type="button" class="aura-sfp-tab" data-tab="notes" style="padding: 10px 16px; border: 1px solid transparent; background: transparent; border-radius: var(--aura-radius-md, 8px); cursor: pointer; font-size: 13px; font-weight: 600; color: var(--aura-text-secondary, #64748b); transition: all .15s ease; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php _e( 'Notas internas', 'aura-suite' ); ?>
                    </button>
                    <?php endif; ?>
                </nav>

                <div style="padding: var(--aura-space-6, 24px);">

                    <!-- ════════════════════════════════════════════════
                         SECCIÓN 1: DATOS PERSONALES
                    ════════════════════════════════════════════════ -->
                    <div class="aura-sfp-panel active" id="sfp-tab-personal">
                        <div style="display: flex; flex-direction: column; gap: var(--aura-space-4, 16px);">

                            <!-- Vinculación con Usuario WordPress -->
                            <div class="aura-card" style="background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #e2e8f0); padding: 16px; border-radius: var(--aura-radius-md, 8px);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                                    <label style="font-weight: 700; font-size: 13px; color: var(--aura-text-primary, #1e293b); display: flex; align-items: center; gap: 8px; margin: 0;">
                                        <span class="dashicons dashicons-admin-users" style="color:var(--aura-primary, #4f46e5);"></span>
                                        <?php _e( 'Vinculación con Cuenta de WordPress', 'aura-suite' ); ?>
                                    </label>
                                    <span class="aura-badge aura-badge-subtle" style="font-size: 11px; padding: 2px 8px;"><?php _e( 'Integración AURA CBAC', 'aura-suite' ); ?></span>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr auto; gap: 12px; align-items: center;">
                                    <div>
                                        <select id="sfp-wp-user-id" name="wp_user_id" class="aura-form-control aura-select" style="width:100%;">
                                            <option value=""><?php _e( '— Seleccionar usuario existente de WordPress o ninguno —', 'aura-suite' ); ?></option>
                                        </select>
                                    </div>
                                    <div>
                                        <button type="button" id="sfp-btn-fill-wp-user" class="aura-btn aura-btn-secondary" style="white-space:nowrap; font-size:12px; padding: 8px 12px;" disabled>
                                            <span class="dashicons dashicons-update" style="font-size:15px; width:15px; height:15px; vertical-align:middle;"></span>
                                            <?php _e( '⚡ Rellenar datos', 'aura-suite' ); ?>
                                        </button>
                                    </div>
                                </div>
                                <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" id="sfp-create-wp-user" name="create_wp_user" value="1" style="margin:0;">
                                    <label for="sfp-create-wp-user" style="font-size: 12.5px; color: var(--aura-text-secondary, #64748b); cursor: pointer; margin:0;">
                                        <?php _e( 'Crear automáticamente usuario de WordPress con rol Estudiante si no se selecciona uno existente.', 'aura-suite' ); ?>
                                    </label>
                                </div>
                                <div id="sfp-wp-user-feedback" style="margin-top: 8px; font-size: 12px; display: none;"></div>
                            </div>

                            <div class="aura-sfp-row" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="aura-sfp-field">
                                    <label for="sfp-first-name" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Nombre(s)', 'aura-suite' ); ?> <span style="color:var(--aura-danger-500, #ef4444);">*</span>
                                    </label>
                                    <input type="text" id="sfp-first-name" name="first_name"
                                           class="aura-form-control aura-sfp-input" maxlength="100" required>
                                </div>
                                <div class="aura-sfp-field">
                                    <label for="sfp-last-name" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Apellido(s)', 'aura-suite' ); ?> <span style="color:var(--aura-danger-500, #ef4444);">*</span>
                                    </label>
                                    <input type="text" id="sfp-last-name" name="last_name"
                                           class="aura-form-control aura-sfp-input" maxlength="100" required>
                                </div>
                            </div>

                            <div class="aura-sfp-row" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="aura-sfp-field">
                                    <label for="sfp-email" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Correo electrónico', 'aura-suite' ); ?> <span style="color:var(--aura-danger-500, #ef4444);">*</span>
                                    </label>
                                    <input type="email" id="sfp-email" name="email"
                                           class="aura-form-control aura-sfp-input" maxlength="200" required>
                                </div>
                                <div class="aura-sfp-field">
                                    <label class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Teléfono', 'aura-suite' ); ?>
                                    </label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" id="sfp-phone-country" name="phone_country"
                                               class="aura-form-control aura-sfp-input" style="max-width:90px;" maxlength="6" placeholder="+1">
                                        <input type="text" id="sfp-phone" name="phone"
                                               class="aura-form-control aura-sfp-input" maxlength="30" style="flex:1;">
                                    </div>
                                </div>
                            </div>

                            <div class="aura-sfp-row" style="display:grid; grid-template-columns:180px 1fr; gap:16px;">
                                <div class="aura-sfp-field">
                                    <label for="sfp-id-type" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Tipo de ID', 'aura-suite' ); ?>
                                    </label>
                                    <select id="sfp-id-type" name="id_type" class="aura-form-control aura-select aura-sfp-select">
                                        <option value="cedula"><?php _e( 'Cédula',    'aura-suite' ); ?></option>
                                        <option value="passport"><?php _e( 'Pasaporte', 'aura-suite' ); ?></option>
                                        <option value="ruc"><?php _e( 'RUC',       'aura-suite' ); ?></option>
                                        <option value="dni"><?php _e( 'DNI',       'aura-suite' ); ?></option>
                                        <option value="other"><?php _e( 'Otro',      'aura-suite' ); ?></option>
                                    </select>
                                </div>
                                <div class="aura-sfp-field">
                                    <label for="sfp-id-number" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Número de identificación', 'aura-suite' ); ?>
                                    </label>
                                    <input type="text" id="sfp-id-number" name="id_number"
                                           class="aura-form-control aura-sfp-input" maxlength="50">
                                </div>
                            </div>

                            <div class="aura-sfp-row" style="display:grid; grid-template-columns:180px 180px 1fr; gap:16px;">
                                <div class="aura-sfp-field">
                                    <label for="sfp-birthdate" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Fecha de nacimiento', 'aura-suite' ); ?>
                                    </label>
                                    <input type="date" id="sfp-birthdate" name="birthdate" class="aura-form-control aura-sfp-input">
                                </div>
                                <div class="aura-sfp-field">
                                    <label for="sfp-gender" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Género', 'aura-suite' ); ?>
                                    </label>
                                    <select id="sfp-gender" name="gender" class="aura-form-control aura-select aura-sfp-select">
                                        <option value=""><?php _e( '— Seleccionar —', 'aura-suite' ); ?></option>
                                        <option value="M"><?php _e( 'Masculino',       'aura-suite' ); ?></option>
                                        <option value="F"><?php _e( 'Femenino',        'aura-suite' ); ?></option>
                                        <option value="O"><?php _e( 'Otro',            'aura-suite' ); ?></option>
                                        <option value="P"><?php _e( 'Prefiero no decir','aura-suite' ); ?></option>
                                    </select>
                                </div>
                                <div class="aura-sfp-field">
                                    <label for="sfp-photo-url" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'URL de foto de perfil', 'aura-suite' ); ?>
                                    </label>
                                    <input type="url" id="sfp-photo-url" name="photo_url"
                                           class="aura-form-control aura-sfp-input" maxlength="500" placeholder="https://ejemplo.com/foto.jpg">
                                </div>
                            </div>

                            <div class="aura-sfp-field">
                                <label for="sfp-address" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                    <?php _e( 'Dirección', 'aura-suite' ); ?>
                                </label>
                                <input type="text" id="sfp-address" name="address"
                                       class="aura-form-control aura-sfp-input" maxlength="300">
                            </div>

                            <div class="aura-sfp-row" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="aura-sfp-field">
                                    <label for="sfp-city" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Ciudad', 'aura-suite' ); ?>
                                    </label>
                                    <input type="text" id="sfp-city" name="city"
                                           class="aura-form-control aura-sfp-input" maxlength="100">
                                </div>
                                <div class="aura-sfp-field">
                                    <label for="sfp-country" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'País', 'aura-suite' ); ?>
                                    </label>
                                    <input type="text" id="sfp-country" name="country"
                                           class="aura-form-control aura-sfp-input" maxlength="100">
                                </div>
                            </div>

                        </div>
                    </div><!-- #sfp-tab-personal -->

                    <!-- ════════════════════════════════════════════════
                         SECCIÓN 2: POSTULACIÓN
                    ════════════════════════════════════════════════ -->
                    <div class="aura-sfp-panel" id="sfp-tab-postul" style="display:none;">
                        <div style="display: flex; flex-direction: column; gap: var(--aura-space-4, 16px);">

                            <div class="aura-sfp-field">
                                <label for="sfp-motivation" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                    <?php _e( 'Motivación para postular', 'aura-suite' ); ?>
                                </label>
                                <textarea id="sfp-motivation" name="motivation" rows="4"
                                          class="aura-form-control aura-sfp-input" maxlength="3000"></textarea>
                            </div>

                            <div class="aura-sfp-row" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="aura-sfp-field">
                                    <label for="sfp-supported-by" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Apadrinado / Referido por', 'aura-suite' ); ?>
                                    </label>
                                    <input type="text" id="sfp-supported-by" name="supported_by"
                                           class="aura-form-control aura-sfp-input" maxlength="200">
                                </div>
                                <div class="aura-sfp-field">
                                    <label for="sfp-talent" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                        <?php _e( 'Talentos destacados', 'aura-suite' ); ?>
                                    </label>
                                    <input type="text" id="sfp-talent" name="talent"
                                           class="aura-form-control aura-sfp-input" maxlength="500">
                                </div>
                            </div>

                            <div class="aura-sfp-field">
                                <label for="sfp-experience" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                    <?php _e( 'Experiencia previa', 'aura-suite' ); ?>
                                </label>
                                <textarea id="sfp-experience" name="experience" rows="3"
                                          class="aura-form-control aura-sfp-input" maxlength="3000"></textarea>
                            </div>

                            <div class="aura-sfp-field">
                                <label for="sfp-extra-info" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                    <?php _e( 'Información adicional', 'aura-suite' ); ?>
                                </label>
                                <textarea id="sfp-extra-info" name="extra_info" rows="3"
                                          class="aura-form-control aura-sfp-input" maxlength="3000"></textarea>
                            </div>

                        </div>
                    </div><!-- #sfp-tab-postul -->

                    <!-- ════════════════════════════════════════════════
                         SECCIÓN 3: ÁREAS DE INTERÉS
                    ════════════════════════════════════════════════ -->
                    <div class="aura-sfp-panel" id="sfp-tab-areas" style="display:none;">
                        <div>
                            <p style="color:var(--aura-text-secondary, #64748b); font-size:14px; margin-top:0; margin-bottom:16px;">
                                <?php _e( 'Selecciona los programas o áreas de interés del estudiante. Se guardarán como sus preferencias.', 'aura-suite' ); ?>
                            </p>
                            <div id="sfp-programs-checkboxes"
                                 style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;">
                                <div style="display:flex; align-items:center; gap:8px; color:var(--aura-text-secondary, #64748b);">
                                    <div class="aura-spinner" style="width:16px; height:16px; border-width:2px;"></div>
                                    <?php _e( 'Cargando programas…', 'aura-suite' ); ?>
                                </div>
                            </div>
                        </div>
                    </div><!-- #sfp-tab-areas -->

                    <!-- ════════════════════════════════════════════════
                         SECCIÓN 4: NOTAS INTERNAS
                    ════════════════════════════════════════════════ -->
                    <?php if ( $can_notes ) : ?>
                    <div class="aura-sfp-panel" id="sfp-tab-notes" style="display:none;">
                        <div>
                            <div class="aura-sfp-field">
                                <label for="sfp-notes" class="aura-sfp-label" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                    <?php _e( 'Notas internas', 'aura-suite' ); ?>
                                </label>
                                <textarea id="sfp-notes" name="notes" rows="6"
                                          class="aura-form-control aura-sfp-input" maxlength="5000"></textarea>
                                <small style="display:block; color:var(--aura-text-muted, #94a3b8); margin-top:6px;">
                                    <?php _e( 'Solo visible para administradores y coordinadores autorizados.', 'aura-suite' ); ?>
                                </small>
                            </div>
                        </div>
                    </div><!-- #sfp-tab-notes -->
                    <?php endif; ?>

                </div>

                <!-- ── Navegación entre secciones + botón guardar ─ -->
                <div style="display:flex; justify-content:space-between; align-items:center; padding: var(--aura-space-4, 16px) var(--aura-space-6, 24px); border-top: 1px solid var(--aura-border, #e2e8f0); background: var(--aura-surface-alt, #f8fafc);">
                    <div style="display:flex; gap:8px;">
                        <button type="button" id="sfp-btn-prev" class="aura-btn aura-btn-secondary" style="display:none;">
                            ‹ <?php _e( 'Anterior', 'aura-suite' ); ?>
                        </button>
                        <button type="button" id="sfp-btn-next" class="aura-btn aura-btn-secondary">
                            <?php _e( 'Siguiente', 'aura-suite' ); ?> ›
                        </button>
                    </div>
                    <button type="button" id="sfp-btn-save" class="aura-btn aura-btn-primary" style="padding: 10px 24px;">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e( 'Registrar estudiante', 'aura-suite' ); ?>
                    </button>
                </div>

            </div><!-- .aura-card -->

        </form>
    </div>

</div><!-- .wrap -->


<!-- ══════════════════════════════════════════════════════════════
     ESTILOS COMPLEMENTARIOS
══════════════════════════════════════════════════════════════ -->
<style>
#form-new-student .aura-sfp-tab.active {
    color: var(--aura-primary-700, #6d28d9) !important;
    background: var(--aura-surface, #ffffff) !important;
    border-color: var(--aura-border, #e2e8f0) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
#form-new-student .aura-sfp-tab:hover:not(.active) {
    color: var(--aura-primary-600, #7c3aed);
    background: rgba(255,255,255,0.6);
}
.aura-sfp-program-cb {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid var(--aura-border, #e2e8f0);
    border-radius: var(--aura-radius-md, 8px);
    cursor: pointer;
    background: var(--aura-surface, #fff);
    transition: all .15s ease;
    font-size: 13px;
    font-weight: 500;
}
.aura-sfp-program-cb:hover, .aura-sfp-program-cb.selected {
    background: var(--aura-primary-50, #f5f3ff);
    border-color: var(--aura-primary-500, #8b5cf6);
    color: var(--aura-primary-900, #4c1d95);
}
.aura-sfp-program-cb input[type="checkbox"] {
    margin: 0;
    cursor: pointer;
}
@media (max-width: 640px) {
    .aura-sfp-row { grid-template-columns: 1fr !important; }
}
</style>


<!-- ══════════════════════════════════════════════════════════════
     JS INLINE
══════════════════════════════════════════════════════════════ -->
<script>
(function($){
    'use strict';

    var nonce    = auraStudents.nonce;
    var ajaxUrl  = auraStudents.ajax_url;
    var listUrl  = <?php echo wp_json_encode( $list_url ); ?>;
    var canNotes = <?php echo $can_notes ? 'true' : 'false'; ?>;
    var programs = [];

    var tabs = ['personal', 'postul', 'areas'];
    <?php if ( $can_notes ) : ?>
    tabs.push('notes');
    <?php endif; ?>

    var currentTabIdx = 0;
    var wpUsersMap    = {};

    // ── INICIO ──────────────────────────────────────────────────
    $(function(){
        loadPrograms();
        loadWpUsers();
        updateNavButtons();
        bindEvents();
    });

    // ── CARGAR USUARIOS WORDPRESS ───────────────────────────────
    function loadWpUsers(){
        $.post(ajaxUrl, { action:'aura_students_search_wp_users', nonce:nonce, q:'' }, function(res){
            if ( ! res.success || ! res.data.users ) return;
            var $sel = $('#sfp-wp-user-id');
            $sel.find('option:not(:first)').remove();
            $.each(res.data.users, function(i, u){
                wpUsersMap[u.id] = u;
                var label = u.display_name + ' (@' + u.login + ' - ' + u.email + ')';
                if ( u.is_linked ){
                    label += ' [Ya vinculado: #' + u.student_id + ' ' + u.student_name + ']';
                }
                var $opt = $('<option>').val(u.id).text(label);
                if ( u.is_linked ){
                    $opt.prop('disabled', true);
                }
                $sel.append($opt);
            });
        });
    }

    // ── CARGAR PROGRAMAS ────────────────────────────────────────
    function loadPrograms(){
        $.post(ajaxUrl, { action:'aura_students_get_programs', nonce:nonce }, function(res){
            if ( ! res.success ) {
                $('#sfp-programs-checkboxes').html('<p style="color:var(--aura-text-secondary, #64748b);"><?php _e( "No hay programas activos.", "aura-suite" ); ?></p>');
                return;
            }
            programs = res.data.programs;
            renderPrograms([]);
        }).fail(function(){
            $('#sfp-programs-checkboxes').html('<p style="color:var(--aura-danger-600, #dc2626);"><?php _e( "Error al cargar los programas.", "aura-suite" ); ?></p>');
        });
    }

    function renderPrograms(selectedIds){
        var $container = $('#sfp-programs-checkboxes');
        $container.empty();

        if ( ! programs.length ){
            $container.html('<p style="color:var(--aura-text-secondary, #64748b);"><?php _e( "No hay programas de tipo área activos.", "aura-suite" ); ?></p>');
            return;
        }

        $.each(programs, function(i, p){
            var checked = selectedIds.indexOf(parseInt(p.id)) !== -1 ? 'checked' : '';
            var $lbl = $('<label class="aura-sfp-program-cb'+(checked?' selected':'')+'"></label>');
            var $cb  = $('<input type="checkbox" name="preferred_areas[]" value="'+p.id+'" '+checked+'>');
            $lbl.append($cb).append($('<span>').text(p.name));
            $container.append($lbl);
        });

        $container.on('change', 'input[type="checkbox"]', function(){
            $(this).closest('label').toggleClass('selected', this.checked);
        });
    }

    // ── NAVEGACIÓN ENTRE TABS ───────────────────────────────────
    function switchToTab(idx){
        if ( idx < 0 || idx >= tabs.length ) return;
        currentTabIdx = idx;

        $('.aura-sfp-tab').removeClass('active');
        $('.aura-sfp-tab[data-tab="'+tabs[idx]+'"]').addClass('active');
        $('.aura-sfp-panel').hide();
        $('#sfp-tab-'+tabs[idx]).show();

        updateNavButtons();
    }

    function updateNavButtons(){
        $('#sfp-btn-prev').toggle(currentTabIdx > 0);
        $('#sfp-btn-next').toggle(currentTabIdx < tabs.length - 1);
    }

    // ── GUARDAR ESTUDIANTE ──────────────────────────────────────
    function saveStudent(){
        var firstName = $.trim($('#sfp-first-name').val());
        var lastName  = $.trim($('#sfp-last-name').val());
        var email     = $.trim($('#sfp-email').val());

        if ( ! firstName ){
            switchToTab(0);
            $('#sfp-first-name').focus();
            showNotice('<?php _e( "El nombre es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }
        if ( ! lastName ){
            switchToTab(0);
            $('#sfp-last-name').focus();
            showNotice('<?php _e( "El apellido es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }
        if ( ! email ){
            switchToTab(0);
            $('#sfp-email').focus();
            showNotice('<?php _e( "El correo electrónico es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }

        var selectedAreas = [];
        $('#sfp-programs-checkboxes input[type="checkbox"]:checked').each(function(){
            selectedAreas.push(parseInt($(this).val()));
        });

        var $btn = $('#sfp-btn-save');
        $btn.prop('disabled', true).text('<?php _e( "Registrando…", "aura-suite" ); ?>');

        var data = {
            action          : 'aura_students_save',
            nonce           : nonce,
            id              : 0,
            wp_user_id      : $('#sfp-wp-user-id').val() || 0,
            create_wp_user  : $('#sfp-create-wp-user').is(':checked') ? 1 : 0,
            first_name      : firstName,
            last_name       : lastName,
            email           : email,
            phone           : $('#sfp-phone').val(),
            phone_country   : $('#sfp-phone-country').val(),
            id_type         : $('#sfp-id-type').val(),
            id_number       : $('#sfp-id-number').val(),
            birthdate       : $('#sfp-birthdate').val(),
            gender          : $('#sfp-gender').val(),
            photo_url       : $('#sfp-photo-url').val(),
            address         : $('#sfp-address').val(),
            city            : $('#sfp-city').val(),
            country         : $('#sfp-country').val(),
            motivation      : $('#sfp-motivation').val(),
            supported_by    : $('#sfp-supported-by').val(),
            talent          : $('#sfp-talent').val(),
            experience      : $('#sfp-experience').val(),
            extra_info      : $('#sfp-extra-info').val(),
            preferred_areas : JSON.stringify(selectedAreas)
        };

        if ( canNotes ){
            data.notes = $('#sfp-notes').val();
        }

        $.post(ajaxUrl, data, function(res){
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> <?php _e( "Registrar estudiante", "aura-suite" ); ?>');
            if ( res.success ){
                showNotice(res.data.message, 'success');
                // Redirige al listado tras 1.2s
                setTimeout(function(){
                    window.location.href = listUrl;
                }, 1200);
            } else {
                showNotice(res.data.message, 'error');
            }
        }).fail(function(){
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> <?php _e( "Registrar estudiante", "aura-suite" ); ?>');
            showNotice(auraStudents.i18n.error, 'error');
        });
    }

    // ── AVISO INLINE ─────────────────────────────────────────────
    function showNotice(msg, type){
        var bg = type === 'success' ? '#ecfdf5' : '#fef2f2';
        var border = type === 'success' ? '#a7f3d0' : '#fca5a5';
        var color = type === 'success' ? '#065f46' : '#991b1b';
        var icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
        
        var $n = $('#form-notice');
        $n.css({
            background: bg,
            border: '1px solid ' + border,
            color: color,
            padding: '12px 16px',
            borderRadius: 'var(--aura-radius-md, 8px)',
            fontWeight: '500',
            display: 'flex',
            alignItems: 'center',
            gap: '8px'
        }).html('<span class="dashicons ' + icon + '"></span> <div>' + $('<div/>').text(msg).html() + '</div>').show();
        
        $('html, body').animate({ scrollTop: $n.offset().top - 60 }, 300);
    }

    // ── EVENTOS ─────────────────────────────────────────────────
    function bindEvents(){
        // Tabs clic
        $(document).on('click', '.aura-sfp-tab', function(){
            var tabId = $(this).data('tab');
            switchToTab(tabs.indexOf(tabId));
        });

        // Navegación anterior/siguiente
        $('#sfp-btn-prev').on('click', function(){ switchToTab(currentTabIdx - 1); });
        $('#sfp-btn-next').on('click', function(){ switchToTab(currentTabIdx + 1); });

        // Selector usuario WP cambio
        $('#sfp-wp-user-id').on('change', function(){
            var uid = parseInt($(this).val()) || 0;
            var user = wpUsersMap[uid];
            var $btn = $('#sfp-btn-fill-wp-user');
            var $fb  = $('#sfp-wp-user-feedback');

            if ( uid > 0 && user ){
                $btn.prop('disabled', false);
                $('#sfp-create-wp-user').prop('checked', false).prop('disabled', true);
                $fb.html('<span style="color:#059669;font-weight:600;">✔ Usuario seleccionado: <strong>' + user.display_name + '</strong> (' + user.email + '). Rol: ' + user.roles + '</span>').show();
            } else {
                $btn.prop('disabled', true);
                $('#sfp-create-wp-user').prop('disabled', false);
                $fb.hide();
            }
        });

        // Checkbox crear usuario WP
        $('#sfp-create-wp-user').on('change', function(){
            if ( $(this).is(':checked') ){
                $('#sfp-wp-user-id').val('').trigger('change');
            }
        });

        // Botón autorellenar datos desde WP
        $('#sfp-btn-fill-wp-user').on('click', function(){
            var uid = parseInt($('#sfp-wp-user-id').val()) || 0;
            var user = wpUsersMap[uid];
            if ( ! user ) return;
            if ( user.first_name ) $('#sfp-first-name').val(user.first_name);
            if ( user.last_name )  $('#sfp-last-name').val(user.last_name);
            if ( ! user.first_name && ! user.last_name && user.display_name ){
                var parts = user.display_name.split(' ');
                $('#sfp-first-name').val(parts[0] || '');
                $('#sfp-last-name').val(parts.slice(1).join(' ') || parts[0] || '');
            }
            if ( user.email ) $('#sfp-email').val(user.email);
            $('#sfp-wp-user-feedback').html('<span style="color:#2563eb;font-weight:600;">⚡ Datos personales rellenados a partir de @' + user.login + '.</span>').show();
        });

        // Guardar
        $('#sfp-btn-save').on('click', saveStudent);
    }

})(jQuery);
</script>
