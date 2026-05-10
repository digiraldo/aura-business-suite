<?php
/**
 * Template: Listado de Estudiantes — Fase 3
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$can_create  = current_user_can( 'aura_students_create' )   || current_user_can( 'manage_options' );
$can_edit    = current_user_can( 'aura_students_edit' )     || current_user_can( 'manage_options' );
$can_delete  = current_user_can( 'aura_students_delete' )   || current_user_can( 'manage_options' );
$can_view_all= current_user_can( 'aura_students_view_all' ) || current_user_can( 'manage_options' );
$can_approve = current_user_can( 'aura_students_approve' )  || current_user_can( 'manage_options' );
$can_notes   = current_user_can( 'aura_students_view_all' ) || current_user_can( 'manage_options' );
?>

<div class="wrap aura-students-list">

    <!-- ─── CABECERA ─────────────────────────────────────────── -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-groups"
              style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#8b5cf6;"></span>
        <?php _e( 'Estudiantes', 'aura-suite' ); ?>
    </h1>

    <?php if ( $can_create ) : ?>
    <button type="button" id="btn-nuevo-estudiante" class="page-title-action">
        + <?php _e( 'Nuevo Estudiante', 'aura-suite' ); ?>
    </button>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ─── BARRA DE FILTROS ──────────────────────────────────── -->
    <div class="aura-stu-filter-bar" style="margin:16px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">

        <input type="text"
               id="filter-search"
               placeholder="<?php esc_attr_e( 'Buscar por nombre o correo…', 'aura-suite' ); ?>"
               class="regular-text"
               style="max-width:240px;">

        <select id="filter-status">
            <option value=""><?php _e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="applicant"><?php _e( '⏳ Postulante', 'aura-suite' ); ?></option>
            <option value="approved"><?php _e( '✅ Aprobado',   'aura-suite' ); ?></option>
            <option value="active"><?php _e( '🟢 Activo',     'aura-suite' ); ?></option>
            <option value="graduated"><?php _e( '🏅 Graduado',  'aura-suite' ); ?></option>
            <option value="withdrawn"><?php _e( '↩ Retirado',  'aura-suite' ); ?></option>
            <option value="rejected"><?php _e( '❌ Rechazado', 'aura-suite' ); ?></option>
        </select>

        <select id="filter-profile">
            <option value=""><?php _e( 'Todos los perfiles', 'aura-suite' ); ?></option>
            <option value="student"><?php _e( 'Estudiante',  'aura-suite' ); ?></option>
            <option value="volunteer"><?php _e( 'Voluntario', 'aura-suite' ); ?></option>
            <option value="teacher"><?php _e( 'Docente',    'aura-suite' ); ?></option>
            <option value="participant"><?php _e( 'Participante','aura-suite' ); ?></option>
            <option value="intern"><?php _e( 'Practicante', 'aura-suite' ); ?></option>
        </select>

        <select id="filter-area">
            <option value=""><?php _e( 'Todas las áreas', 'aura-suite' ); ?></option>
            <!-- Opciones cargadas vía JS -->
        </select>

        <button type="button" id="btn-apply-filters" class="button">
            <?php _e( 'Filtrar', 'aura-suite' ); ?>
        </button>

        <button type="button" id="btn-clear-filters" class="button button-link">
            <?php _e( 'Limpiar', 'aura-suite' ); ?>
        </button>
    </div>

    <!-- ─── TABLA DE ESTUDIANTES ─────────────────────────────── -->
    <div id="students-table-wrap" style="overflow-x:auto;">
        <table class="aura-stu-table wp-list-table widefat striped">
            <thead>
                <tr>
                    <th style="width:42px;min-width:42px;"><?php _e( 'Foto', 'aura-suite' ); ?></th>
                    <th style="min-width:160px;"><?php _e( 'Nombre', 'aura-suite' ); ?></th>
                    <th style="min-width:160px;"><?php _e( 'Correo', 'aura-suite' ); ?></th>
                    <th style="min-width:100px;"><?php _e( 'Perfil', 'aura-suite' ); ?></th>
                    <th style="min-width:110px;"><?php _e( 'Estado', 'aura-suite' ); ?></th>
                    <th style="min-width:70px;" class="num"><?php _e( 'Cursos', 'aura-suite' ); ?></th>
                    <th style="min-width:100px;" class="num"><?php _e( 'Saldo pend.', 'aura-suite' ); ?></th>
                    <th style="min-width:170px;white-space:nowrap;"><?php _e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="students-tbody">
                <tr>
                    <td colspan="8" style="text-align:center;padding:20px;">
                        <span class="spinner is-active" style="float:none;margin-right:8px;"></span>
                        <?php _e( 'Cargando estudiantes…', 'aura-suite' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ─── PAGINACIÓN ───────────────────────────────────────── -->
    <div id="students-pagination" class="tablenav bottom" style="display:none;">
        <div class="tablenav-pages">
            <span id="pagination-count" class="displaying-num"></span>
            <span class="pagination-links">
                <button id="page-prev" class="button" disabled>‹</button>
                <span id="page-info" style="padding:0 8px;"></span>
                <button id="page-next" class="button" disabled>›</button>
            </span>
        </div>
    </div>

</div><!-- .wrap -->


<!-- ══════════════════════════════════════════════════════════════
     MODAL: VER DETALLE DEL ESTUDIANTE
══════════════════════════════════════════════════════════════ -->
<div id="modal-student-detail" class="aura-stu-modal" style="display:none;" role="dialog" aria-modal="true"
     aria-labelledby="modal-detail-title">
    <div class="aura-stu-modal-backdrop"></div>
    <div class="aura-stu-modal-container" style="max-width:760px;">

        <div class="aura-stu-modal-header">
            <h2 id="modal-detail-title"><?php _e( 'Detalle del Estudiante', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-stu-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <div class="aura-stu-modal-body" id="student-detail-body">
            <!-- Contenido cargado dinámicamente -->
        </div>

        <div class="aura-stu-modal-footer">
            <button type="button" class="button aura-stu-modal-close"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
            <?php if ( $can_edit ) : ?>
            <button type="button" id="btn-edit-from-detail" class="button button-primary">
                <?php _e( 'Editar', 'aura-suite' ); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: CREAR / EDITAR ESTUDIANTE
══════════════════════════════════════════════════════════════ -->
<div id="modal-student-form" class="aura-stu-modal" style="display:none;" role="dialog" aria-modal="true"
     aria-labelledby="modal-form-title">
    <div class="aura-stu-modal-backdrop"></div>
    <div class="aura-stu-modal-container" style="max-width:860px;">

        <div class="aura-stu-modal-header">
            <h2 id="modal-form-title"><?php _e( 'Nuevo Estudiante', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-stu-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <div class="aura-stu-modal-body">
            <form id="form-student" novalidate>
                <input type="hidden" id="student-id" name="id" value="0">

                <!-- ── Tabs de secciones ──────────────────────── -->
                <div class="aura-stu-tabs" style="border-bottom:2px solid #e2e0ef;margin-bottom:20px;display:flex;gap:4px;">
                    <button type="button" class="aura-stu-tab active" data-tab="personal">
                        👤 <?php _e( 'Datos personales', 'aura-suite' ); ?>
                    </button>
                    <button type="button" class="aura-stu-tab" data-tab="postul">
                        📋 <?php _e( 'Postulación', 'aura-suite' ); ?>
                    </button>
                    <button type="button" class="aura-stu-tab" data-tab="areas">
                        🎯 <?php _e( 'Áreas de interés', 'aura-suite' ); ?>
                    </button>
                    <?php if ( $can_edit ) : ?>
                    <button type="button" class="aura-stu-tab" data-tab="status">
                        🔖 <?php _e( 'Estado / Perfil', 'aura-suite' ); ?>
                    </button>
                    <?php endif; ?>
                    <?php if ( $can_notes ) : ?>
                    <button type="button" class="aura-stu-tab" data-tab="notes">
                        📝 <?php _e( 'Notas internas', 'aura-suite' ); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- ── SECCIÓN 1: DATOS PERSONALES ───────────── -->
                <div class="aura-stu-tab-panel active" id="tab-personal">

                    <div class="aura-stu-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="aura-stu-field">
                            <label for="stu-first-name" class="aura-stu-label">
                                <?php _e( 'Nombre(s)', 'aura-suite' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" id="stu-first-name" name="first_name"
                                   class="regular-text aura-stu-input" maxlength="100" required>
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-last-name" class="aura-stu-label">
                                <?php _e( 'Apellido(s)', 'aura-suite' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" id="stu-last-name" name="last_name"
                                   class="regular-text aura-stu-input" maxlength="100" required>
                        </div>
                    </div>

                    <div class="aura-stu-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px;">
                        <div class="aura-stu-field">
                            <label for="stu-email" class="aura-stu-label">
                                <?php _e( 'Correo electrónico', 'aura-suite' ); ?> <span class="required">*</span>
                            </label>
                            <input type="email" id="stu-email" name="email"
                                   class="regular-text aura-stu-input" maxlength="200" required>
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-phone" class="aura-stu-label"><?php _e( 'Teléfono', 'aura-suite' ); ?></label>
                            <div style="display:flex;gap:6px;">
                                <input type="text" id="stu-phone-country" name="phone_country"
                                       class="aura-stu-input" style="width:80px;" maxlength="6"
                                       placeholder="+1">
                                <input type="text" id="stu-phone" name="phone"
                                       class="aura-stu-input" maxlength="30" style="flex:1;">
                            </div>
                        </div>
                    </div>

                    <div class="aura-stu-form-row" style="display:grid;grid-template-columns:140px 1fr;gap:16px;margin-top:12px;">
                        <div class="aura-stu-field">
                            <label for="stu-id-type" class="aura-stu-label"><?php _e( 'Tipo de ID', 'aura-suite' ); ?></label>
                            <select id="stu-id-type" name="id_type" class="aura-stu-select">
                                <option value="cedula"><?php _e( 'Cédula', 'aura-suite' ); ?></option>
                                <option value="passport"><?php _e( 'Pasaporte', 'aura-suite' ); ?></option>
                                <option value="ruc"><?php _e( 'RUC', 'aura-suite' ); ?></option>
                                <option value="dni"><?php _e( 'DNI', 'aura-suite' ); ?></option>
                                <option value="other"><?php _e( 'Otro', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-id-number" class="aura-stu-label"><?php _e( 'Número de identificación', 'aura-suite' ); ?></label>
                            <input type="text" id="stu-id-number" name="id_number"
                                   class="regular-text aura-stu-input" maxlength="50">
                        </div>
                    </div>

                    <div class="aura-stu-form-row" style="display:grid;grid-template-columns:160px 160px 1fr;gap:16px;margin-top:12px;">
                        <div class="aura-stu-field">
                            <label for="stu-birthdate" class="aura-stu-label"><?php _e( 'Fecha de nacimiento', 'aura-suite' ); ?></label>
                            <input type="date" id="stu-birthdate" name="birthdate" class="aura-stu-input">
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-gender" class="aura-stu-label"><?php _e( 'Género', 'aura-suite' ); ?></label>
                            <select id="stu-gender" name="gender" class="aura-stu-select">
                                <option value=""><?php _e( '— Seleccionar —', 'aura-suite' ); ?></option>
                                <option value="M"><?php _e( 'Masculino', 'aura-suite' ); ?></option>
                                <option value="F"><?php _e( 'Femenino', 'aura-suite' ); ?></option>
                                <option value="O"><?php _e( 'Otro', 'aura-suite' ); ?></option>
                                <option value="P"><?php _e( 'Prefiero no decir', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-photo-url" class="aura-stu-label"><?php _e( 'URL de foto', 'aura-suite' ); ?></label>
                            <input type="url" id="stu-photo-url" name="photo_url"
                                   class="large-text aura-stu-input" maxlength="500">
                        </div>
                    </div>

                    <div class="aura-stu-field" style="margin-top:12px;">
                        <label for="stu-address" class="aura-stu-label"><?php _e( 'Dirección', 'aura-suite' ); ?></label>
                        <input type="text" id="stu-address" name="address"
                               class="large-text aura-stu-input" maxlength="300">
                    </div>

                    <div class="aura-stu-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px;">
                        <div class="aura-stu-field">
                            <label for="stu-city" class="aura-stu-label"><?php _e( 'Ciudad', 'aura-suite' ); ?></label>
                            <input type="text" id="stu-city" name="city"
                                   class="regular-text aura-stu-input" maxlength="100">
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-country" class="aura-stu-label"><?php _e( 'País', 'aura-suite' ); ?></label>
                            <input type="text" id="stu-country" name="country"
                                   class="regular-text aura-stu-input" maxlength="100">
                        </div>
                    </div>

                </div><!-- #tab-personal -->

                <!-- ── SECCIÓN 2: POSTULACIÓN ─────────────────── -->
                <div class="aura-stu-tab-panel" id="tab-postul" style="display:none;">

                    <div class="aura-stu-field">
                        <label for="stu-motivation" class="aura-stu-label"><?php _e( 'Motivación para postular', 'aura-suite' ); ?></label>
                        <textarea id="stu-motivation" name="motivation" rows="4"
                                  class="large-text aura-stu-input" maxlength="3000"></textarea>
                    </div>

                    <div class="aura-stu-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px;">
                        <div class="aura-stu-field">
                            <label for="stu-supported-by" class="aura-stu-label"><?php _e( 'Apadrinado / Referido por', 'aura-suite' ); ?></label>
                            <input type="text" id="stu-supported-by" name="supported_by"
                                   class="regular-text aura-stu-input" maxlength="200">
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-talent" class="aura-stu-label"><?php _e( 'Talentos destacados', 'aura-suite' ); ?></label>
                            <input type="text" id="stu-talent" name="talent"
                                   class="regular-text aura-stu-input" maxlength="500">
                        </div>
                    </div>

                    <div class="aura-stu-field" style="margin-top:12px;">
                        <label for="stu-experience" class="aura-stu-label"><?php _e( 'Experiencia previa', 'aura-suite' ); ?></label>
                        <textarea id="stu-experience" name="experience" rows="3"
                                  class="large-text aura-stu-input" maxlength="3000"></textarea>
                    </div>

                    <div class="aura-stu-field" style="margin-top:12px;">
                        <label for="stu-extra-info" class="aura-stu-label"><?php _e( 'Información adicional', 'aura-suite' ); ?></label>
                        <textarea id="stu-extra-info" name="extra_info" rows="3"
                                  class="large-text aura-stu-input" maxlength="3000"></textarea>
                    </div>

                </div><!-- #tab-postul -->

                <!-- ── SECCIÓN 3: ÁREAS DE INTERÉS ───────────── -->
                <div class="aura-stu-tab-panel" id="tab-areas" style="display:none;">
                    <p style="color:#6b7280;font-size:.88rem;">
                        <?php _e( 'Selecciona los programas/áreas de interés del estudiante. Se guardan como sus preferencias.', 'aura-suite' ); ?>
                    </p>
                    <div id="programs-checkboxes" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-top:12px;">
                        <p style="color:#6b7280;">
                            <span class="spinner is-active" style="float:none;"></span>
                            <?php _e( 'Cargando programas…', 'aura-suite' ); ?>
                        </p>
                    </div>
                </div><!-- #tab-areas -->

                <!-- ── SECCIÓN 4: ESTADO / PERFIL ─────────────── -->
                <?php if ( $can_edit ) : ?>
                <div class="aura-stu-tab-panel" id="tab-status" style="display:none;">

                    <div class="aura-stu-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="aura-stu-field">
                            <label for="stu-profile-type" class="aura-stu-label"><?php _e( 'Tipo de perfil', 'aura-suite' ); ?></label>
                            <select id="stu-profile-type" name="profile_type" class="aura-stu-select">
                                <option value="student"><?php _e( 'Estudiante', 'aura-suite' ); ?></option>
                                <option value="volunteer"><?php _e( 'Voluntario', 'aura-suite' ); ?></option>
                                <option value="teacher"><?php _e( 'Docente', 'aura-suite' ); ?></option>
                                <option value="participant"><?php _e( 'Participante', 'aura-suite' ); ?></option>
                                <option value="intern"><?php _e( 'Practicante', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                        <div class="aura-stu-field">
                            <label for="stu-status" class="aura-stu-label"><?php _e( 'Estado', 'aura-suite' ); ?></label>
                            <select id="stu-status" name="status" class="aura-stu-select">
                                <option value="applicant"><?php _e( '⏳ Postulante',  'aura-suite' ); ?></option>
                                <option value="approved"><?php _e( '✅ Aprobado',    'aura-suite' ); ?></option>
                                <option value="active"><?php _e( '🟢 Activo',      'aura-suite' ); ?></option>
                                <option value="graduated"><?php _e( '🏅 Graduado',   'aura-suite' ); ?></option>
                                <option value="withdrawn"><?php _e( '↩ Retirado',   'aura-suite' ); ?></option>
                                <option value="rejected"><?php _e( '❌ Rechazado',  'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="aura-stu-field" id="field-rejection-reason" style="display:none;margin-top:12px;">
                        <label for="stu-rejection-reason" class="aura-stu-label"><?php _e( 'Motivo de rechazo', 'aura-suite' ); ?></label>
                        <textarea id="stu-rejection-reason" name="rejection_reason" rows="3"
                                  class="large-text aura-stu-input" maxlength="1000"></textarea>
                    </div>

                </div><!-- #tab-status -->
                <?php endif; ?>

                <!-- ── SECCIÓN 5: NOTAS INTERNAS ─────────────── -->
                <?php if ( $can_notes ) : ?>
                <div class="aura-stu-tab-panel" id="tab-notes" style="display:none;">
                    <div class="aura-stu-field">
                        <label for="stu-notes" class="aura-stu-label"><?php _e( 'Notas internas', 'aura-suite' ); ?></label>
                        <textarea id="stu-notes" name="notes" rows="6"
                                  class="large-text aura-stu-input" maxlength="5000"></textarea>
                        <small style="color:#6b7280;"><?php _e( 'Solo visible para administradores y coordinadores.', 'aura-suite' ); ?></small>
                    </div>
                </div><!-- #tab-notes -->
                <?php endif; ?>

            </form>
        </div><!-- .modal-body -->

        <div class="aura-stu-modal-footer">
            <button type="button" class="button aura-stu-modal-close"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="btn-save-student" class="button button-primary">
                <?php _e( 'Guardar estudiante', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div><!-- #modal-student-form -->


<!-- ══════════════════════════════════════════════════════════════
     ESTILOS INLINE
══════════════════════════════════════════════════════════════ -->
<style>
/* Modal overlay (reutiliza .aura-stu-modal del CSS de cursos) */
.aura-stu-modal { position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center; }
.aura-stu-modal-backdrop { position:absolute;inset:0;background:rgba(0,0,0,.55); }
.aura-stu-modal-container { position:relative;background:#fff;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.25);width:90%;max-height:92vh;display:flex;flex-direction:column;overflow:hidden; }
.aura-stu-modal-header { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e0ef; }
.aura-stu-modal-header h2 { margin:0;font-size:1.2rem;color:#1e1b4b; }
.aura-stu-modal-close { background:none;border:none;cursor:pointer;font-size:18px;color:#6b7280;padding:0 4px; }
.aura-stu-modal-close:hover { color:#111; }
.aura-stu-modal-body { padding:20px;overflow-y:auto;flex:1; }
.aura-stu-modal-footer { padding:14px 20px;border-top:1px solid #e2e0ef;display:flex;justify-content:flex-end;gap:10px; }

/* Form helpers */
.aura-stu-label { display:block;font-weight:600;margin-bottom:4px;font-size:.85rem;color:#374151; }
.aura-stu-input,.aura-stu-select { width:100%;box-sizing:border-box; }
.aura-stu-field small { display:block;color:#6b7280;font-size:.78rem;margin-top:2px; }
.required { color:#dc2626; }

/* Tabs */
.aura-stu-tab { border:none;border-bottom:3px solid transparent;background:none;cursor:pointer;padding:8px 14px;color:#6b7280;font-size:.88rem;font-weight:600;transition:color .15s,border-color .15s; }
.aura-stu-tab:hover { color:#8b5cf6; }
.aura-stu-tab.active { color:#8b5cf6;border-bottom-color:#8b5cf6; }

/* Status badges */
.stu-badge { display:inline-block;padding:2px 10px;border-radius:99px;font-size:.75rem;font-weight:600; }
.stu-badge-applicant { background:#f3f4f6;color:#6b7280; }
.stu-badge-approved  { background:#dbeafe;color:#1d4ed8; }
.stu-badge-active    { background:#dcfce7;color:#166534; }
.stu-badge-graduated { background:#fef3c7;color:#92400e; }
.stu-badge-withdrawn { background:#fed7aa;color:#9a3412; }
.stu-badge-rejected  { background:#fee2e2;color:#991b1b; }

/* Detail grid */
.aura-stu-detail-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px 24px; }
.aura-stu-detail-item label { display:block;font-size:.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em; }
.aura-stu-detail-item span { display:block;color:#111827;margin-top:2px; }
.aura-stu-detail-full { grid-column:1/-1; }

/* Program checkboxes */
.aura-stu-program-cb { display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;transition:background .15s; }
.aura-stu-program-cb:hover, .aura-stu-program-cb.selected { background:#f5f3ff;border-color:#8b5cf6; }
.aura-stu-program-cb input[type="checkbox"] { margin:0;cursor:pointer; }

@media (max-width:640px) {
    .aura-stu-form-row { grid-template-columns:1fr !important; }
    .aura-stu-detail-grid { grid-template-columns:1fr; }
    .aura-stu-tabs { flex-wrap:wrap; }
}
</style>


<!-- ══════════════════════════════════════════════════════════════
     JS INLINE
══════════════════════════════════════════════════════════════ -->
<script>
(function($){
    'use strict';

    var nonce       = auraStudents.nonce;
    var ajaxUrl     = auraStudents.ajax_url;
    var currentPage = 1;
    var programs    = []; // cache de programas cargados

    var canEdit    = <?php echo $can_edit    ? 'true' : 'false'; ?>;
    var canDelete  = <?php echo $can_delete  ? 'true' : 'false'; ?>;
    var canApprove = <?php echo $can_approve ? 'true' : 'false'; ?>;
    var canNotes   = <?php echo $can_notes   ? 'true' : 'false'; ?>;

    var statusLabels = {
        applicant: '⏳ <?php _e( "Postulante", "aura-suite" ); ?>',
        approved : '✅ <?php _e( "Aprobado",   "aura-suite" ); ?>',
        active   : '🟢 <?php _e( "Activo",     "aura-suite" ); ?>',
        graduated: '🏅 <?php _e( "Graduado",   "aura-suite" ); ?>',
        withdrawn: '↩ <?php _e( "Retirado",   "aura-suite" ); ?>',
        rejected : '❌ <?php _e( "Rechazado",  "aura-suite" ); ?>'
    };

    var profileLabels = {
        student    : '<?php _e( "Estudiante",   "aura-suite" ); ?>',
        volunteer  : '<?php _e( "Voluntario",   "aura-suite" ); ?>',
        teacher    : '<?php _e( "Docente",      "aura-suite" ); ?>',
        participant: '<?php _e( "Participante", "aura-suite" ); ?>',
        intern     : '<?php _e( "Practicante",  "aura-suite" ); ?>'
    };

    // ── INICIO ──────────────────────────────────────────────────
    $(function(){
        loadAreas();
        loadStudents(1);
        bindEvents();
    });

    // ── CARGAR ÁREAS EN FILTRO ──────────────────────────────────
    function loadAreas(){
        $.post(ajaxUrl, { action:'aura_students_get_programs', nonce:nonce }, function(res){
            if ( ! res.success ) return;
            programs = res.data.programs;
            var $sel = $('#filter-area');
            $.each(programs, function(i, p){
                $sel.append('<option value="'+p.id+'">'+esc(p.name)+'</option>');
            });
        });
    }

    // ── LISTAR ESTUDIANTES ──────────────────────────────────────
    function loadStudents(page){
        currentPage = page;
        $('#students-tbody').html(
            '<tr><td colspan="8" style="text-align:center;padding:20px;">' +
            '<span class="spinner is-active" style="float:none;margin-right:8px;"></span>' +
            '<?php _e( "Cargando…", "aura-suite" ); ?>' +
            '</td></tr>'
        );
        $.post(ajaxUrl, {
            action      : 'aura_students_list',
            nonce       : nonce,
            page        : page,
            status      : $('#filter-status').val(),
            profile_type: $('#filter-profile').val(),
            area_id     : $('#filter-area').val(),
            search      : $('#filter-search').val()
        }, function(res){
            if ( ! res.success ){
                AuraStudents.notify(res.data.message, 'error');
                return;
            }
            renderTable(res.data);
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    // ── RENDERIZAR TABLA ─────────────────────────────────────────
    function renderTable(data){
        var $tbody = $('#students-tbody');
        $tbody.empty();

        if ( ! data.students.length ){
            $tbody.html(
                '<tr><td colspan="8" style="text-align:center;padding:30px;color:#6b7280;">' +
                '<?php _e( "No se encontraron estudiantes.", "aura-suite" ); ?>' +
                '</td></tr>'
            );
            $('#students-pagination').hide();
            return;
        }

        $.each(data.students, function(i, s){
            var photo = s.photo_url
                ? '<img src="'+esc(s.photo_url)+'" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">'
                : '<span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:14px;">👤</span>';

            var badge = '<span class="stu-badge stu-badge-'+s.status+'">'+
                        (statusLabels[s.status] || s.status) +
                        '</span>';

            var profile = esc(profileLabels[s.profile_type] || s.profile_type);

            var balance = s.pending_balance > 0
                ? '<span style="color:#dc2626;font-weight:600;">$'+parseFloat(s.pending_balance).toFixed(2)+'</span>'
                : '<span style="color:#6b7280;">—</span>';

            var actions = '<button class="button button-small btn-view-student" data-id="'+s.id+'" style="margin-right:4px;">' +
                          '👁 <?php _e( "Ver", "aura-suite" ); ?></button>';

            if ( canEdit ){
                actions += '<button class="button button-small btn-edit-student" data-id="'+s.id+'" style="margin-right:4px;">✏️</button>';
            }
            if ( canDelete ){
                actions += '<button class="button button-small btn-delete-student" data-id="'+s.id+'" style="color:#b91c1c;">🗑️</button>';
            }

            $tbody.append(
                '<tr data-id="'+s.id+'">' +
                '<td>'+photo+'</td>' +
                '<td><strong>'+esc(s.full_name)+'</strong></td>' +
                '<td>'+esc(s.email)+'</td>' +
                '<td>'+profile+'</td>' +
                '<td>'+badge+'</td>' +
                '<td class="num">'+s.active_enrollments+'</td>' +
                '<td class="num">'+balance+'</td>' +
                '<td>'+actions+'</td>' +
                '</tr>'
            );
        });

        // Paginación
        $('#pagination-count').text(data.total + ' <?php _e( "estudiantes", "aura-suite" ); ?>');
        $('#page-info').text('<?php _e( "Pág.", "aura-suite" ); ?> ' + data.page + ' <?php _e( "de", "aura-suite" ); ?> ' + data.total_pages);
        $('#page-prev').prop('disabled', data.page <= 1);
        $('#page-next').prop('disabled', data.page >= data.total_pages);
        $('#students-pagination').toggle(data.total > 0);
    }

    // ── ABRIR MODAL CREAR ───────────────────────────────────────
    function openCreateModal(){
        resetStudentForm();
        $('#modal-form-title').text('<?php _e( "Nuevo Estudiante", "aura-suite" ); ?>');
        $('#stu-status').val('applicant');
        loadProgramCheckboxes([]);
        openModal('#modal-student-form');
        switchTab('personal');
    }

    // ── ABRIR MODAL EDITAR ──────────────────────────────────────
    function openEditModal(id){
        $.post(ajaxUrl, { action:'aura_students_get', nonce:nonce, id:id }, function(res){
            if ( ! res.success ){
                AuraStudents.notify(res.data.message, 'error');
                return;
            }
            var s = res.data.student;
            fillStudentForm(s);
            $('#modal-form-title').text('<?php _e( "Editar Estudiante", "aura-suite" ); ?>');
            loadProgramCheckboxes(s.preferred_areas || []);
            openModal('#modal-student-form');
            switchTab('personal');
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    // ── ABRIR MODAL DETALLE ─────────────────────────────────────
    function openDetailModal(id){
        $('#student-detail-body').html(
            '<p style="text-align:center;padding:30px;">' +
            '<span class="spinner is-active" style="float:none;"></span></p>'
        );
        openModal('#modal-student-detail');

        $.post(ajaxUrl, { action:'aura_students_get', nonce:nonce, id:id }, function(res){
            if ( ! res.success ){
                AuraStudents.notify(res.data.message, 'error');
                closeModals();
                return;
            }
            var s   = res.data.student;
            var enr = res.data.enrollments;
            var pay = res.data.payments;

            $('#btn-edit-from-detail').data('id', s.id);

            var programNames = [];
            if ( s.preferred_areas && s.preferred_areas.length ){
                $.each(s.preferred_areas, function(i, pid){
                    var found = null;
                    $.each(programs, function(j, p){ if ( p.id == pid ) { found = p.name; return false; } });
                    if (found) programNames.push(found);
                });
            }

            var html = '<div class="aura-stu-detail-grid">';

            if ( s.photo_url ){
                html += '<div class="aura-stu-detail-item aura-stu-detail-full" style="text-align:center;">' +
                        '<img src="'+esc(s.photo_url)+'" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #8b5cf6;">' +
                        '</div>';
            }

            html +=
                '<div class="aura-stu-detail-item aura-stu-detail-full">' +
                    '<label>'+esc('<?php _e( "Nombre completo", "aura-suite" ); ?>')+'</label>' +
                    '<span style="font-size:1.1rem;font-weight:700;">'+esc(s.full_name)+'</span>' +
                '</div>' +
                makeDI('<?php _e( "Correo", "aura-suite" ); ?>', s.email) +
                makeDI('<?php _e( "Teléfono", "aura-suite" ); ?>', s.phone ? (s.phone_country ? s.phone_country+' ' : '')+s.phone : '—') +
                makeDI('<?php _e( "Perfil", "aura-suite" ); ?>', profileLabels[s.profile_type] || s.profile_type) +
                '<div class="aura-stu-detail-item"><label><?php _e( "Estado", "aura-suite" ); ?></label>' +
                    '<span><span class="stu-badge stu-badge-'+s.status+'">'+statusLabels[s.status]+'</span></span>' +
                '</div>' +
                makeDI('<?php _e( "Ciudad / País", "aura-suite" ); ?>', [s.city, s.country].filter(Boolean).join(', ') || '—') +
                makeDI('<?php _e( "Identificación", "aura-suite" ); ?>', s.id_number ? s.id_type.toUpperCase()+': '+s.id_number : '—') +
                makeDI('<?php _e( "Nacimiento", "aura-suite" ); ?>', s.birthdate || '—');

            if ( programNames.length ){
                html += '<div class="aura-stu-detail-item aura-stu-detail-full"><label><?php _e( "Áreas de interés", "aura-suite" ); ?></label>' +
                        '<span>'+esc(programNames.join(', '))+'</span></div>';
            }

            if ( s.motivation ){
                html += '<div class="aura-stu-detail-item aura-stu-detail-full"><label><?php _e( "Motivación", "aura-suite" ); ?></label>' +
                        '<span>'+esc(s.motivation)+'</span></div>';
            }

            if ( canNotes && s.notes ){
                html += '<div class="aura-stu-detail-item aura-stu-detail-full" style="background:#faf5ff;padding:10px;border-radius:6px;">' +
                        '<label>📝 <?php _e( "Notas internas", "aura-suite" ); ?></label>' +
                        '<span>'+esc(s.notes)+'</span></div>';
            }

            html += '</div>';

            // Inscripciones
            if ( enr && enr.length ){
                html += '<hr style="margin:16px 0;"><strong>📚 <?php _e( "Inscripciones", "aura-suite" ); ?></strong>' +
                        '<table style="width:100%;margin-top:8px;font-size:.85rem;border-collapse:collapse;">' +
                        '<thead><tr><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:4px 8px;"><?php _e( "Curso", "aura-suite" ); ?></th>' +
                        '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:4px 8px;"><?php _e( "Estado", "aura-suite" ); ?></th>' +
                        '<th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:4px 8px;"><?php _e( "Beca", "aura-suite" ); ?></th>' +
                        '<th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:4px 8px;"><?php _e( "Costo neto", "aura-suite" ); ?></th></tr></thead><tbody>';
                $.each(enr, function(i, e){
                    html += '<tr><td style="padding:4px 8px;border-bottom:1px solid #f3f4f6;">'+esc(e.course_name)+'</td>' +
                            '<td style="padding:4px 8px;border-bottom:1px solid #f3f4f6;">'+esc(e.status)+'</td>' +
                            '<td style="text-align:right;padding:4px 8px;border-bottom:1px solid #f3f4f6;">'+e.scholarship_pct+'%</td>' +
                            '<td style="text-align:right;padding:4px 8px;border-bottom:1px solid #f3f4f6;">'+e.currency+' '+parseFloat(e.net_cost).toFixed(2)+'</td></tr>';
                });
                html += '</tbody></table>';
            }

            $('#student-detail-body').html(html);
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    function makeDI(label, val){
        return '<div class="aura-stu-detail-item"><label>'+label+'</label><span>'+esc(String(val||'—'))+'</span></div>';
    }

    // ── RELLENAR FORMULARIO PARA EDICIÓN ────────────────────────
    function fillStudentForm(s){
        $('#student-id').val(s.id);
        $('#stu-first-name').val(s.first_name);
        $('#stu-last-name').val(s.last_name);
        $('#stu-email').val(s.email);
        $('#stu-phone').val(s.phone);
        $('#stu-phone-country').val(s.phone_country);
        $('#stu-id-type').val(s.id_type);
        $('#stu-id-number').val(s.id_number);
        $('#stu-birthdate').val(s.birthdate);
        $('#stu-gender').val(s.gender);
        $('#stu-photo-url').val(s.photo_url);
        $('#stu-address').val(s.address);
        $('#stu-city').val(s.city);
        $('#stu-country').val(s.country);
        $('#stu-motivation').val(s.motivation);
        $('#stu-supported-by').val(s.supported_by);
        $('#stu-talent').val(s.talent);
        $('#stu-experience').val(s.experience);
        $('#stu-extra-info').val(s.extra_info);
        if ( canEdit ){
            $('#stu-profile-type').val(s.profile_type);
            $('#stu-status').val(s.status);
            toggleRejectionField(s.status);
            $('#stu-rejection-reason').val(s.rejection_reason);
        }
        if ( canNotes ){
            $('#stu-notes').val(s.notes);
        }
    }

    // ── CARGAR CHECKBOXES DE PROGRAMAS ──────────────────────────
    function loadProgramCheckboxes(selectedIds){
        var $container = $('#programs-checkboxes');
        $container.empty();

        if ( ! programs.length ){
            // Carga tardía si aún no llegaron
            $.post(ajaxUrl, { action:'aura_students_get_programs', nonce:nonce }, function(res){
                if ( res.success ) {
                    programs = res.data.programs;
                    renderProgramCheckboxes($container, selectedIds);
                } else {
                    $container.html('<p style="color:#6b7280;"><?php _e( "No hay programas disponibles.", "aura-suite" ); ?></p>');
                }
            });
        } else {
            renderProgramCheckboxes($container, selectedIds);
        }
    }

    function renderProgramCheckboxes($container, selectedIds){
        if ( ! programs.length ){
            $container.html('<p style="color:#6b7280;"><?php _e( "No hay programas de tipo área activos.", "aura-suite" ); ?></p>');
            return;
        }
        $container.empty();
        $.each(programs, function(i, p){
            var checked = selectedIds.indexOf(parseInt(p.id)) !== -1 ? 'checked' : '';
            var $label = $('<label class="aura-stu-program-cb'+(checked?' selected':'')+'"></label>');
            var $cb = $('<input type="checkbox" name="preferred_areas[]" value="'+p.id+'" '+ checked +'>');
            $label.append($cb).append($('<span>').text(p.name));
            $container.append($label);
        });
        $container.on('change', 'input[type="checkbox"]', function(){
            $(this).closest('label').toggleClass('selected', this.checked);
        });
    }

    // ── GUARDAR ESTUDIANTE ──────────────────────────────────────
    function saveStudent(){
        var $btn = $('#btn-save-student');
        var firstName = $.trim($('#stu-first-name').val());
        var lastName  = $.trim($('#stu-last-name').val());
        var email     = $.trim($('#stu-email').val());

        if ( ! firstName ){
            switchTab('personal');
            $('#stu-first-name').focus();
            AuraStudents.notify('<?php _e( "El nombre es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }
        if ( ! lastName ){
            switchTab('personal');
            $('#stu-last-name').focus();
            AuraStudents.notify('<?php _e( "El apellido es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }
        if ( ! email ){
            switchTab('personal');
            $('#stu-email').focus();
            AuraStudents.notify('<?php _e( "El correo electrónico es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }

        // Recolectar áreas seleccionadas
        var selectedAreas = [];
        $('#programs-checkboxes input[type="checkbox"]:checked').each(function(){
            selectedAreas.push(parseInt($(this).val()));
        });

        $btn.prop('disabled', true).text('<?php _e( "Guardando…", "aura-suite" ); ?>');

        var data = {
            action          : 'aura_students_save',
            nonce           : nonce,
            id              : $('#student-id').val(),
            first_name      : firstName,
            last_name       : lastName,
            email           : email,
            phone           : $('#stu-phone').val(),
            phone_country   : $('#stu-phone-country').val(),
            id_type         : $('#stu-id-type').val(),
            id_number       : $('#stu-id-number').val(),
            birthdate       : $('#stu-birthdate').val(),
            gender          : $('#stu-gender').val(),
            photo_url       : $('#stu-photo-url').val(),
            address         : $('#stu-address').val(),
            city            : $('#stu-city').val(),
            country         : $('#stu-country').val(),
            motivation      : $('#stu-motivation').val(),
            supported_by    : $('#stu-supported-by').val(),
            talent          : $('#stu-talent').val(),
            experience      : $('#stu-experience').val(),
            extra_info      : $('#stu-extra-info').val(),
            preferred_areas : JSON.stringify(selectedAreas)
        };

        if ( canEdit ){
            data.profile_type       = $('#stu-profile-type').val();
            data.status             = $('#stu-status').val();
            data.rejection_reason   = $('#stu-rejection-reason').val();
        }
        if ( canNotes ){
            data.notes = $('#stu-notes').val();
        }

        $.post(ajaxUrl, data, function(res){
            $btn.prop('disabled', false).text('<?php _e( "Guardar estudiante", "aura-suite" ); ?>');
            if ( res.success ){
                AuraStudents.notify(res.data.message, 'success');
                closeModals();
                loadStudents(currentPage);
            } else {
                AuraStudents.notify(res.data.message, 'error');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('<?php _e( "Guardar estudiante", "aura-suite" ); ?>');
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    // ── ELIMINAR ESTUDIANTE ─────────────────────────────────────
    function deleteStudent(id){
        if ( ! confirm(auraStudents.i18n.confirm_del) ) return;
        $.post(ajaxUrl, { action:'aura_students_delete', nonce:nonce, id:id }, function(res){
            if ( res.success ){
                AuraStudents.notify(res.data.message, 'success');
                loadStudents(currentPage);
            } else {
                AuraStudents.notify(res.data.message, 'error');
            }
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    // ── HELPERS ─────────────────────────────────────────────────
    function switchTab(tabId){
        $('.aura-stu-tab').removeClass('active');
        $('.aura-stu-tab[data-tab="'+tabId+'"]').addClass('active');
        $('.aura-stu-tab-panel').hide();
        $('#tab-'+tabId).show();

        if ( tabId === 'areas' ){
            // Asegurar que los checkboxes estén montados
            if ( ! $('#programs-checkboxes').children().length || $('#programs-checkboxes .spinner').length ){
                var selected = getSelectedAreas();
                loadProgramCheckboxes(selected);
            }
        }
    }

    function getSelectedAreas(){
        var ids = [];
        $('#programs-checkboxes input[type="checkbox"]:checked').each(function(){
            ids.push(parseInt($(this).val()));
        });
        return ids;
    }

    function toggleRejectionField(status){
        $('#field-rejection-reason').toggle(status === 'rejected');
    }

    function openModal(selector){
        $(selector).fadeIn(150);
        $('body').css('overflow','hidden');
    }

    function closeModals(){
        $('.aura-stu-modal').fadeOut(150);
        $('body').css('overflow','');
    }

    function resetStudentForm(){
        $('#form-student')[0].reset();
        $('#student-id').val(0);
        $('#field-rejection-reason').hide();
        $('#programs-checkboxes').empty();
    }

    function esc(str){
        return $('<div/>').text(str).html();
    }

    // ── EVENTOS ─────────────────────────────────────────────────
    function bindEvents(){
        // Botón nuevo
        $('#btn-nuevo-estudiante').on('click', openCreateModal);

        // Acciones en tabla
        $('#students-tbody').on('click', '.btn-view-student', function(){
            openDetailModal($(this).data('id'));
        }).on('click', '.btn-edit-student', function(){
            openEditModal($(this).data('id'));
        }).on('click', '.btn-delete-student', function(){
            deleteStudent($(this).data('id'));
        });

        // Editar desde modal detalle
        $('#btn-edit-from-detail').on('click', function(){
            var id = $(this).data('id');
            closeModals();
            setTimeout(function(){ openEditModal(id); }, 200);
        });

        // Guardar
        $('#btn-save-student').on('click', saveStudent);

        // Cerrar modales
        $(document).on('click', '.aura-stu-modal-close, .aura-stu-modal-backdrop', closeModals);
        $(document).on('keydown', function(e){ if ( e.key === 'Escape' ) closeModals(); });

        // Tabs
        $(document).on('click', '.aura-stu-tab', function(){
            switchTab($(this).data('tab'));
        });

        // Mostrar/ocultar campo rechazo
        $('#stu-status').on('change', function(){
            if ( canEdit ) toggleRejectionField($(this).val());
        });

        // Filtros
        $('#btn-apply-filters').on('click', function(){ loadStudents(1); });
        $('#btn-clear-filters').on('click', function(){
            $('#filter-search, #filter-status, #filter-profile, #filter-area').val('');
            loadStudents(1);
        });
        $('#filter-search').on('keydown', function(e){
            if ( e.key === 'Enter' ) loadStudents(1);
        });

        // Paginación
        $('#page-prev').on('click', function(){ if ( currentPage > 1 ) loadStudents(currentPage - 1); });
        $('#page-next').on('click', function(){ loadStudents(currentPage + 1); });
    }

})(jQuery);
</script>
