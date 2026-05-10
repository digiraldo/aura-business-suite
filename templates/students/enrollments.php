<?php
/**
 * Template: Inscripciones y Aprobaciones — Fase 4
 *
 * Sección 1: Postulantes pendientes (aprobar / rechazar)
 * Sección 2: Inscripciones activas (editar / graduar)
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Cargar áreas disponibles (con guard)
$areas_table  = $wpdb->prefix . 'aura_areas';
$areas_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $areas_table ) );
$areas        = $areas_exists
    ? $wpdb->get_results( "SELECT id, name FROM `{$areas_table}` WHERE status = 'active' AND type = 'program' ORDER BY name ASC" )
    : [];

$nonce = wp_create_nonce( 'aura_students_nonce' );
$can_approve = current_user_can( 'aura_students_approve' ) || current_user_can( 'manage_options' );
$can_manage  = current_user_can( 'aura_students_enrollments_manage' ) || current_user_can( 'manage_options' );
?>
<div class="wrap aura-students-enrollments-wrap">

    <h1 class="wp-heading-inline" style="color:#8b5cf6;">
        <span class="dashicons dashicons-clipboard" style="vertical-align:middle;"></span>
        <?php esc_html_e( 'Inscripciones y Aprobaciones', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <!-- ── PESTAÑAS ── -->
    <nav class="nav-tab-wrapper aura-tab-wrapper" style="margin-top:12px;">
        <a href="javascript:void(0)" class="nav-tab nav-tab-active aura-enrollment-tab" data-tab="applicants">
            ⏳ <?php esc_html_e( 'Postulantes', 'aura-suite' ); ?>
            <span id="badge-applicants" class="aura-badge" style="background:#8b5cf6;color:#fff;border-radius:10px;padding:1px 8px;font-size:11px;margin-left:4px;"></span>
        </a>
        <a href="javascript:void(0)" class="nav-tab aura-enrollment-tab" data-tab="enrollments">
            📋 <?php esc_html_e( 'Inscripciones Activas', 'aura-suite' ); ?>
        </a>
    </nav>

    <!-- ══════════════════════════════════════════════
         TAB 1: POSTULANTES
    ══════════════════════════════════════════════ -->
    <div id="tab-applicants" class="aura-enrollment-panel" style="display:block;">
        <div class="aura-filter-bar" style="margin:16px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="search" id="applicant-search" placeholder="<?php esc_attr_e( 'Buscar por nombre o email…', 'aura-suite' ); ?>"
                   style="min-width:220px;padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <select id="applicant-profile" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                <option value=""><?php esc_html_e( '— Todos los perfiles —', 'aura-suite' ); ?></option>
                <option value="student"><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></option>
                <option value="volunteer"><?php esc_html_e( 'Voluntario', 'aura-suite' ); ?></option>
                <option value="teacher"><?php esc_html_e( 'Instructor', 'aura-suite' ); ?></option>
                <option value="participant"><?php esc_html_e( 'Participante', 'aura-suite' ); ?></option>
                <option value="donor"><?php esc_html_e( 'Donante', 'aura-suite' ); ?></option>
            </select>
            <button id="btn-refresh-applicants" class="button"><?php esc_html_e( 'Actualizar', 'aura-suite' ); ?></button>
        </div>

        <table class="wp-list-table widefat fixed striped" id="applicants-table">
            <thead>
                <tr>
                    <th width="52"><?php esc_html_e( 'Foto', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'aura-suite' ); ?></th>
                    <th width="120"><?php esc_html_e( 'Perfil', 'aura-suite' ); ?></th>
                    <th width="130"><?php esc_html_e( 'Solicitud', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Motivación', 'aura-suite' ); ?></th>
                    <th width="160"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="applicants-tbody">
                <tr><td colspan="7" style="text-align:center;padding:20px;">
                    <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                    <?php esc_html_e( 'Cargando postulantes…', 'aura-suite' ); ?>
                </td></tr>
            </tbody>
        </table>
        <div id="applicants-pagination" class="tablenav bottom" style="margin-top:8px;"></div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 2: INSCRIPCIONES ACTIVAS
    ══════════════════════════════════════════════ -->
    <div id="tab-enrollments" class="aura-enrollment-panel" style="display:none;">
        <div class="aura-filter-bar" style="margin:16px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="search" id="enrollment-search" placeholder="<?php esc_attr_e( 'Buscar estudiante…', 'aura-suite' ); ?>"
                   style="min-width:220px;padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
            <?php if ( $areas ) : ?>
            <select id="enrollment-area" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                <option value=""><?php esc_html_e( '— Todas las áreas —', 'aura-suite' ); ?></option>
                <?php foreach ( $areas as $area ) : ?>
                    <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select id="enrollment-status" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                <option value="active"><?php esc_html_e( 'Activos', 'aura-suite' ); ?></option>
                <option value="pending"><?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></option>
                <option value="completed"><?php esc_html_e( 'Completados', 'aura-suite' ); ?></option>
                <option value="withdrawn"><?php esc_html_e( 'Retirados', 'aura-suite' ); ?></option>
                <option value="suspended"><?php esc_html_e( 'Suspendidos', 'aura-suite' ); ?></option>
                <option value=""><?php esc_html_e( 'Todos', 'aura-suite' ); ?></option>
            </select>
            <button id="btn-refresh-enrollments" class="button"><?php esc_html_e( 'Actualizar', 'aura-suite' ); ?></button>
        </div>

        <table class="wp-list-table widefat fixed striped" id="enrollments-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Curso / Área', 'aura-suite' ); ?></th>
                    <th width="80"><?php esc_html_e( 'Beca %', 'aura-suite' ); ?></th>
                    <th width="100"><?php esc_html_e( 'Costo Neto', 'aura-suite' ); ?></th>
                    <th width="100"><?php esc_html_e( 'Pagado', 'aura-suite' ); ?></th>
                    <th width="100"><?php esc_html_e( 'Saldo', 'aura-suite' ); ?></th>
                    <th width="110"><?php esc_html_e( 'Pago', 'aura-suite' ); ?></th>
                    <th width="160"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="enrollments-tbody">
                <tr><td colspan="8" style="text-align:center;padding:20px;">
                    <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                    <?php esc_html_e( 'Cargando inscripciones…', 'aura-suite' ); ?>
                </td></tr>
            </tbody>
        </table>
        <div id="enrollments-pagination" class="tablenav bottom" style="margin-top:8px;"></div>
    </div>

</div><!-- .wrap -->

<!-- ══════════════════════════════════════════════════════════════
     MODAL: APROBAR + INSCRIBIR
════════════════════════════════════════════════════════════════ -->
<div id="modal-approve" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-modal-box" style="max-width:680px;width:95%;">
        <div class="aura-modal-header" style="background:#8b5cf6;">
            <h2>✅ <?php esc_html_e( 'Aprobar e Inscribir', 'aura-suite' ); ?></h2>
            <button class="aura-modal-close" data-modal="modal-approve" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" style="padding:24px;">

            <!-- Resumen del postulante (solo lectura) -->
            <div id="approve-student-summary" style="background:#f9f5ff;border-radius:6px;padding:14px;margin-bottom:20px;">
                <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Postulante:', 'aura-suite' ); ?></strong>
                    <span id="approve-student-name">—</span></p>
                <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Email:', 'aura-suite' ); ?></strong>
                    <span id="approve-student-email">—</span></p>
                <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Teléfono:', 'aura-suite' ); ?></strong>
                    <span id="approve-student-phone">—</span></p>
                <p style="margin:0;"><strong><?php esc_html_e( 'Motivación:', 'aura-suite' ); ?></strong>
                    <span id="approve-student-motivation">—</span></p>
            </div>

            <input type="hidden" id="approve-student-id" value="">

            <!-- Paso 1: Aprobar primero, luego inscribir -->
            <div id="approve-step-1">
                <h3 style="margin-top:0;color:#8b5cf6;"><?php esc_html_e( 'Paso 1 — Aprobar postulante', 'aura-suite' ); ?></h3>
                <p style="color:#555;"><?php esc_html_e( 'Al aprobar, se creará automáticamente un usuario WordPress para el estudiante y se le enviarán sus credenciales por email.', 'aura-suite' ); ?></p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button id="btn-do-approve" class="button button-primary" style="background:#8b5cf6;border-color:#7c3aed;">
                        ✅ <?php esc_html_e( 'Aprobar postulante', 'aura-suite' ); ?>
                    </button>
                    <button id="btn-skip-approve-to-enroll" class="button" style="display:none;">
                        📋 <?php esc_html_e( 'Ya aprobado — ir a inscripción', 'aura-suite' ); ?>
                    </button>
                </div>
                <div id="approve-step-1-notice" style="margin-top:10px;display:none;"></div>
            </div>

            <!-- Paso 2: Inscripción al curso -->
            <div id="approve-step-2" style="display:none;border-top:1px solid #e9d5ff;margin-top:20px;padding-top:20px;">
                <h3 style="margin-top:0;color:#8b5cf6;"><?php esc_html_e( 'Paso 2 — Inscribir en un curso', 'aura-suite' ); ?></h3>

                <table class="form-table" style="margin:0;">
                    <tr>
                        <th scope="row"><label for="enroll-area"><?php esc_html_e( 'Área / Programa', 'aura-suite' ); ?></label></th>
                        <td>
                            <select id="enroll-area" style="width:100%;max-width:340px;">
                                <option value=""><?php esc_html_e( '— Seleccionar área —', 'aura-suite' ); ?></option>
                                <?php foreach ( $areas as $area ) : ?>
                                    <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="enroll-course"><?php esc_html_e( 'Curso *', 'aura-suite' ); ?></label></th>
                        <td>
                            <select id="enroll-course" required style="width:100%;max-width:340px;">
                                <option value=""><?php esc_html_e( '— Seleccionar curso —', 'aura-suite' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="enroll-base-cost"><?php esc_html_e( 'Costo Base', 'aura-suite' ); ?></label></th>
                        <td>
                            <input type="number" id="enroll-base-cost" min="0" step="0.01" value="0"
                                   style="width:120px;" class="aura-cost-input">
                            <span id="enroll-currency" style="margin-left:6px;color:#666;">USD</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="enroll-scholarship-type"><?php esc_html_e( 'Tipo de Beca', 'aura-suite' ); ?></label></th>
                        <td>
                            <select id="enroll-scholarship-type">
                                <option value="none"><?php esc_html_e( 'Sin beca', 'aura-suite' ); ?></option>
                                <option value="internal"><?php esc_html_e( 'Interna (instituto)', 'aura-suite' ); ?></option>
                                <option value="external"><?php esc_html_e( 'Externa (patrocinador)', 'aura-suite' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr id="row-scholarship-pct" style="display:none;">
                        <th scope="row"><label for="enroll-scholarship-pct"><?php esc_html_e( 'Porcentaje Beca', 'aura-suite' ); ?></label></th>
                        <td>
                            <input type="number" id="enroll-scholarship-pct" min="0" max="100" step="1" value="0"
                                   class="aura-cost-input" style="width:80px;"> %
                            <div style="margin-top:6px;">
                                <button type="button" class="button aura-pct-btn" data-pct="25">25%</button>
                                <button type="button" class="button aura-pct-btn" data-pct="50">50%</button>
                                <button type="button" class="button aura-pct-btn" data-pct="75">75%</button>
                                <button type="button" class="button aura-pct-btn" data-pct="100">100%</button>
                            </div>
                        </td>
                    </tr>
                    <tr id="row-scholarship-sponsor" style="display:none;">
                        <th scope="row"><label for="enroll-scholarship-sponsor"><?php esc_html_e( 'Patrocinador', 'aura-suite' ); ?></label></th>
                        <td><input type="text" id="enroll-scholarship-sponsor" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="enroll-payment-scheme"><?php esc_html_e( 'Esquema de Pago', 'aura-suite' ); ?></label></th>
                        <td>
                            <select id="enroll-payment-scheme">
                                <option value="full"><?php esc_html_e( 'Pago único', 'aura-suite' ); ?></option>
                                <option value="installments"><?php esc_html_e( 'Cuotas', 'aura-suite' ); ?></option>
                                <option value="scholarship_full"><?php esc_html_e( 'Beca total (sin pago)', 'aura-suite' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr id="row-installments" style="display:none;">
                        <th scope="row"><label for="enroll-installment-count"><?php esc_html_e( 'Número de Cuotas', 'aura-suite' ); ?></label></th>
                        <td><input type="number" id="enroll-installment-count" min="1" max="36" value="6" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="enroll-first-payment"><?php esc_html_e( 'Primer Pago', 'aura-suite' ); ?></label></th>
                        <td><input type="date" id="enroll-first-payment" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Resumen de Costos', 'aura-suite' ); ?></th>
                        <td>
                            <div class="aura-cost-summary" style="background:#f9f5ff;border-radius:6px;padding:12px;min-width:200px;display:inline-block;">
                                <div><?php esc_html_e( 'Costo base:', 'aura-suite' ); ?> <strong id="summary-base">$0.00</strong></div>
                                <div><?php esc_html_e( 'Descuento beca:', 'aura-suite' ); ?> <strong id="summary-discount" style="color:#059669;">−$0.00</strong></div>
                                <div style="border-top:1px solid #e9d5ff;margin-top:6px;padding-top:6px;">
                                    <?php esc_html_e( 'Costo neto:', 'aura-suite' ); ?> <strong id="summary-net" style="font-size:1.1em;color:#8b5cf6;">$0.00</strong>
                                </div>
                                <div id="summary-installment-row" style="display:none;margin-top:4px;">
                                    <?php esc_html_e( 'Cuota mensual:', 'aura-suite' ); ?> <strong id="summary-installment">$0.00</strong>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="enroll-notes"><?php esc_html_e( 'Notas', 'aura-suite' ); ?></label></th>
                        <td><textarea id="enroll-notes" rows="3" class="large-text"></textarea></td>
                    </tr>
                </table>

                <div id="enroll-notice" style="margin-top:12px;display:none;"></div>

                <div style="margin-top:16px;display:flex;gap:10px;">
                    <button id="btn-do-enroll" class="button button-primary" style="background:#8b5cf6;border-color:#7c3aed;">
                        📋 <?php esc_html_e( 'Inscribir estudiante', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: RECHAZAR
════════════════════════════════════════════════════════════════ -->
<div id="modal-reject" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-modal-box" style="max-width:480px;width:95%;">
        <div class="aura-modal-header" style="background:#ef4444;">
            <h2>❌ <?php esc_html_e( 'Rechazar Solicitud', 'aura-suite' ); ?></h2>
            <button class="aura-modal-close" data-modal="modal-reject" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" style="padding:24px;">
            <p><strong id="reject-student-name">—</strong></p>
            <input type="hidden" id="reject-student-id" value="">
            <label for="reject-reason"><strong><?php esc_html_e( 'Motivo del rechazo (opcional):', 'aura-suite' ); ?></strong></label>
            <textarea id="reject-reason" rows="4" class="large-text" style="margin-top:8px;"
                      placeholder="<?php esc_attr_e( 'Explique el motivo para que el postulante lo reciba por email…', 'aura-suite' ); ?>"></textarea>
            <div id="reject-notice" style="margin-top:12px;display:none;"></div>
            <div style="margin-top:16px;display:flex;gap:10px;">
                <button id="btn-do-reject" class="button" style="background:#ef4444;color:#fff;border-color:#dc2626;">
                    ❌ <?php esc_html_e( 'Confirmar rechazo', 'aura-suite' ); ?>
                </button>
                <button class="button aura-modal-close" data-modal="modal-reject">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: EDITAR INSCRIPCIÓN
════════════════════════════════════════════════════════════════ -->
<div id="modal-edit-enrollment" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-modal-box" style="max-width:600px;width:95%;">
        <div class="aura-modal-header" style="background:#8b5cf6;">
            <h2>✏️ <?php esc_html_e( 'Editar Inscripción', 'aura-suite' ); ?></h2>
            <button class="aura-modal-close" data-modal="modal-edit-enrollment" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" style="padding:24px;">
            <p style="margin-top:0;"><strong id="edit-enrollment-student">—</strong> — <span id="edit-enrollment-course">—</span></p>
            <input type="hidden" id="edit-enrollment-id" value="">

            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="edit-scholarship-type"><?php esc_html_e( 'Tipo de Beca', 'aura-suite' ); ?></label></th>
                    <td>
                        <select id="edit-scholarship-type">
                            <option value="none"><?php esc_html_e( 'Sin beca', 'aura-suite' ); ?></option>
                            <option value="internal"><?php esc_html_e( 'Interna', 'aura-suite' ); ?></option>
                            <option value="external"><?php esc_html_e( 'Externa', 'aura-suite' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit-scholarship-pct"><?php esc_html_e( 'Porcentaje Beca', 'aura-suite' ); ?></label></th>
                    <td>
                        <input type="number" id="edit-scholarship-pct" min="0" max="100" step="1" value="0"
                               class="aura-edit-cost-input" style="width:80px;"> %
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit-scholarship-sponsor"><?php esc_html_e( 'Patrocinador', 'aura-suite' ); ?></label></th>
                    <td><input type="text" id="edit-scholarship-sponsor" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit-payment-scheme"><?php esc_html_e( 'Esquema de Pago', 'aura-suite' ); ?></label></th>
                    <td>
                        <select id="edit-payment-scheme">
                            <option value="full"><?php esc_html_e( 'Pago único', 'aura-suite' ); ?></option>
                            <option value="installments"><?php esc_html_e( 'Cuotas', 'aura-suite' ); ?></option>
                            <option value="scholarship_full"><?php esc_html_e( 'Beca total', 'aura-suite' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="edit-row-installments" style="display:none;">
                    <th scope="row"><label for="edit-installment-count"><?php esc_html_e( 'Número de Cuotas', 'aura-suite' ); ?></label></th>
                    <td><input type="number" id="edit-installment-count" min="1" max="36" value="6" style="width:80px;"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit-first-payment"><?php esc_html_e( 'Primer Pago', 'aura-suite' ); ?></label></th>
                    <td><input type="date" id="edit-first-payment"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit-enrollment-status-field"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></label></th>
                    <td>
                        <select id="edit-enrollment-status-field">
                            <option value="active"><?php esc_html_e( 'Activo', 'aura-suite' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendiente', 'aura-suite' ); ?></option>
                            <option value="withdrawn"><?php esc_html_e( 'Retirado', 'aura-suite' ); ?></option>
                            <option value="suspended"><?php esc_html_e( 'Suspendido', 'aura-suite' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Costo Neto Estimado', 'aura-suite' ); ?></th>
                    <td><strong id="edit-summary-net" style="color:#8b5cf6;font-size:1.1em;">$0.00</strong>
                        <input type="hidden" id="edit-base-cost" value="0"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit-enrollment-notes"><?php esc_html_e( 'Notas', 'aura-suite' ); ?></label></th>
                    <td><textarea id="edit-enrollment-notes" rows="3" class="large-text"></textarea></td>
                </tr>
            </table>

            <div id="edit-enrollment-notice" style="margin-top:12px;display:none;"></div>
            <div style="margin-top:16px;">
                <button id="btn-save-enrollment" class="button button-primary" style="background:#8b5cf6;border-color:#7c3aed;">
                    💾 <?php esc_html_e( 'Guardar cambios', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: CONFIRMAR GRADUACIÓN
════════════════════════════════════════════════════════════════ -->
<div id="modal-graduate" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-modal-box" style="max-width:440px;width:95%;">
        <div class="aura-modal-header" style="background:#059669;">
            <h2>🎓 <?php esc_html_e( 'Confirmar Graduación', 'aura-suite' ); ?></h2>
            <button class="aura-modal-close" data-modal="modal-graduate" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" style="padding:24px;">
            <p><?php esc_html_e( '¿Estás seguro de que deseas graduar a:', 'aura-suite' ); ?></p>
            <p><strong id="graduate-student-name">—</strong></p>
            <p style="color:#555;"><?php esc_html_e( 'Esta acción marcará la inscripción como completada y el estudiante como graduado. Se habilitará la emisión de certificado.', 'aura-suite' ); ?></p>
            <input type="hidden" id="graduate-enrollment-id" value="">
            <div id="graduate-notice" style="margin-top:12px;display:none;"></div>
            <div style="margin-top:16px;display:flex;gap:10px;">
                <button id="btn-do-graduate" class="button button-primary" style="background:#059669;border-color:#047857;">
                    🎓 <?php esc_html_e( 'Sí, graduar', 'aura-suite' ); ?>
                </button>
                <button class="button aura-modal-close" data-modal="modal-graduate">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     ESTILOS
════════════════════════════════════════════════════════════════ -->
<style>
.aura-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;}
.aura-modal-box{background:#fff;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.22);max-height:90vh;overflow-y:auto;}
.aura-modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-radius:8px 8px 0 0;}
.aura-modal-header h2{margin:0;color:#fff;font-size:18px;}
.aura-modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0 4px;line-height:1;}
.aura-enrollment-tab.nav-tab-active{border-bottom-color:#fff;background:#fff;}
.aura-badge{display:none;}
.aura-badge.has-count{display:inline-block;}
</style>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════ -->
<script>
jQuery(function($){
    'use strict';

    var nonce     = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var currApplicantPage   = 1;
    var currEnrollmentPage  = 1;

    // ── PESTAÑAS ──────────────────────────────────────────────────
    $('.aura-enrollment-tab').on('click', function(e){
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.aura-enrollment-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.aura-enrollment-panel').hide();
        $('#tab-' + tab).show();
        if ( tab === 'applicants' )  loadApplicants(1);
        if ( tab === 'enrollments' ) loadEnrollments(1);
    });

    // ── ABRIR / CERRAR MODALES ────────────────────────────────────
    $(document).on('click', '.aura-modal-close', function(){
        $('#' + $(this).data('modal')).hide();
    });
    $(document).on('keydown', function(e){
        if (e.key === 'Escape') $('.aura-modal-overlay').hide();
    });
    $(document).on('click', '.aura-modal-overlay', function(e){
        if ($(e.target).hasClass('aura-modal-overlay')) $(this).hide();
    });

    // ══════════════════════════════════════════════
    // TAB 1: POSTULANTES
    // ══════════════════════════════════════════════

    function loadApplicants(page){
        currApplicantPage = page || 1;
        $.post(ajaxUrl, {
            action  : 'aura_students_list_applicants',
            nonce   : nonce,
            page    : currApplicantPage,
            search  : $('#applicant-search').val(),
            profile_type: $('#applicant-profile').val()
        }, function(res){
            if (!res.success) { $('#applicants-tbody').html('<tr><td colspan="7">' + res.data.message + '</td></tr>'); return; }
            var d = res.data;
            updateApplicantsBadge(d.total);
            renderApplicants(d.rows);
            renderPagination('#applicants-pagination', d.page, d.total_pages, loadApplicants);
        });
    }

    function updateApplicantsBadge(total){
        var $b = $('#badge-applicants');
        if (total > 0){ $b.text(total).addClass('has-count'); } else { $b.text('').removeClass('has-count'); }
    }

    function renderApplicants(rows){
        if (!rows || !rows.length){
            $('#applicants-tbody').html('<tr><td colspan="7" style="text-align:center;padding:20px;"><?php echo esc_js( __( 'No hay postulantes pendientes.', 'aura-suite' ) ); ?></td></tr>');
            return;
        }
        var profileLabels = { student:'Estudiante', volunteer:'Voluntario', teacher:'Instructor', participant:'Participante', donor:'Donante' };
        var html = '';
        $.each(rows, function(i, r){
            var photo = r.photo_url
                ? '<img src="' + escHtml(r.photo_url) + '" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">'
                : '<span class="dashicons dashicons-admin-users" style="font-size:32px;color:#8b5cf6;width:36px;height:36px;"></span>';
            var fullName   = escHtml((r.first_name || '') + ' ' + (r.last_name || '')).trim();
            var motivation = r.motivation ? escHtml(r.motivation.substring(0, 80)) + (r.motivation.length > 80 ? '…' : '') : '—';
            var profile    = profileLabels[r.profile_type] || r.profile_type || '—';
            var dateStr    = r.created_at ? r.created_at.substring(0,10) : '—';
            html += '<tr>';
            html += '<td>' + photo + '</td>';
            html += '<td><strong>' + fullName + '</strong></td>';
            html += '<td>' + escHtml(r.email || '—') + '</td>';
            html += '<td><span style="background:#ede9fe;color:#5b21b6;border-radius:4px;padding:2px 8px;font-size:12px;">' + profile + '</span></td>';
            html += '<td>' + dateStr + '</td>';
            html += '<td style="font-size:12px;color:#555;">' + motivation + '</td>';
            html += '<td>';
            html += '<button class="button button-small btn-open-approve" style="background:#8b5cf6;color:#fff;border-color:#7c3aed;margin-right:4px;" '
                  + 'data-id="' + r.id + '" data-name="' + encodeURIComponent(fullName) + '" '
                  + 'data-email="' + encodeURIComponent(r.email || '') + '" '
                  + 'data-phone="' + encodeURIComponent(r.phone || '') + '" '
                  + 'data-motivation="' + encodeURIComponent(r.motivation || '') + '">✅ <?php echo esc_js( __( 'Aprobar', 'aura-suite' ) ); ?></button>';
            html += '<button class="button button-small btn-open-reject" style="color:#ef4444;border-color:#ef4444;" '
                  + 'data-id="' + r.id + '" data-name="' + encodeURIComponent(fullName) + '">❌ <?php echo esc_js( __( 'Rechazar', 'aura-suite' ) ); ?></button>';
            html += '</td>';
            html += '</tr>';
        });
        $('#applicants-tbody').html(html);
    }

    // Abrir modal Aprobar
    $(document).on('click', '.btn-open-approve', function(){
        var btn = $(this);
        $('#approve-student-id').val(btn.data('id'));
        $('#approve-student-name').text(decodeURIComponent(btn.data('name')));
        $('#approve-student-email').text(decodeURIComponent(btn.data('email')));
        $('#approve-student-phone').text(decodeURIComponent(btn.data('phone')) || '—');
        $('#approve-student-motivation').text(decodeURIComponent(btn.data('motivation')) || '—');
        $('#approve-step-1').show();
        $('#approve-step-2').hide();
        $('#approve-step-1-notice').hide();
        $('#enroll-notice').hide();
        $('#btn-skip-approve-to-enroll').hide();
        resetEnrollForm();
        $('#modal-approve').show();
    });

    // Botón Aprobar (paso 1)
    $('#btn-do-approve').on('click', function(){
        var id = $('#approve-student-id').val();
        if (!id) return;
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Aprobando…', 'aura-suite' ) ); ?>');
        $.post(ajaxUrl, { action:'aura_students_approve', nonce:nonce, id:id }, function(res){
            $btn.prop('disabled', false).html('✅ <?php echo esc_js( __( 'Aprobar postulante', 'aura-suite' ) ); ?>');
            if (!res.success){ showNotice('#approve-step-1-notice', res.data.message, 'error'); return; }
            showNotice('#approve-step-1-notice', res.data.message, 'success');
            $('#approve-step-1 button').prop('disabled', true);
            $('#approve-step-2').show();
        }).fail(function(){ $btn.prop('disabled', false); showNotice('#approve-step-1-notice', '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error'); });
    });

    // Abrir modal Rechazar
    $(document).on('click', '.btn-open-reject', function(){
        var btn = $(this);
        $('#reject-student-id').val(btn.data('id'));
        $('#reject-student-name').text(decodeURIComponent(btn.data('name')));
        $('#reject-reason').val('');
        $('#reject-notice').hide();
        $('#modal-reject').show();
    });

    // Botón Rechazar
    $('#btn-do-reject').on('click', function(){
        var id = $('#reject-student-id').val();
        if (!id) return;
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'aura_students_reject', nonce:nonce, id:id,
            rejection_reason: $('#reject-reason').val()
        }, function(res){
            $btn.prop('disabled', false);
            if (!res.success){ showNotice('#reject-notice', res.data.message, 'error'); return; }
            showNotice('#reject-notice', res.data.message, 'success');
            setTimeout(function(){ $('#modal-reject').hide(); loadApplicants(currApplicantPage); }, 1200);
        }).fail(function(){ $btn.prop('disabled', false); showNotice('#reject-notice', '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error'); });
    });

    // ── Filtros de área/curso en modal de aprobación ──────────────
    $('#enroll-area').on('change', function(){
        var areaId = $(this).val();
        $('#enroll-course').html('<option><?php echo esc_js( __( 'Cargando…', 'aura-suite' ) ); ?></option>').prop('disabled', true);
        $.post(ajaxUrl, { action:'aura_students_get_courses_by_area', nonce:nonce, area_id:areaId }, function(res){
            $('#enroll-course').prop('disabled', false);
            if (!res.success){ $('#enroll-course').html('<option><?php echo esc_js( __( 'Error al cargar', 'aura-suite' ) ); ?></option>'); return; }
            var opts = '<option value=""><?php echo esc_js( __( '— Seleccionar curso —', 'aura-suite' ) ); ?></option>';
            $.each(res.data.courses, function(i, c){ opts += '<option value="' + c.id + '" data-cost="' + c.base_cost + '" data-currency="' + escHtml(c.currency) + '">' + escHtml(c.name) + '</option>'; });
            $('#enroll-course').html(opts);
        });
    });

    $('#enroll-course').on('change', function(){
        var cost = parseFloat($(this).find(':selected').data('cost')) || 0;
        var cur  = $(this).find(':selected').data('currency') || 'USD';
        $('#enroll-base-cost').val(cost.toFixed(2));
        $('#enroll-currency').text(cur);
        updateCostSummary();
    });

    $(document).on('input change', '#enroll-base-cost, #enroll-scholarship-pct, #enroll-installment-count', updateCostSummary);
    $(document).on('change', '#enroll-scholarship-type', function(){
        var type = $(this).val();
        $('#row-scholarship-pct, #row-scholarship-sponsor').toggle(type !== 'none');
        if (type === 'none') $('#enroll-scholarship-pct').val(0);
        updateCostSummary();
    });
    $(document).on('change', '#enroll-payment-scheme', function(){
        $('#row-installments').toggle($(this).val() === 'installments');
        updateCostSummary();
    });
    $(document).on('click', '.aura-pct-btn', function(){
        $('#enroll-scholarship-pct').val($(this).data('pct'));
        updateCostSummary();
    });

    function updateCostSummary(){
        var base    = parseFloat($('#enroll-base-cost').val()) || 0;
        var pct     = parseFloat($('#enroll-scholarship-pct').val()) || 0;
        var count   = parseInt($('#enroll-installment-count').val()) || 1;
        var scheme  = $('#enroll-payment-scheme').val();
        var discount = base * pct / 100;
        var net      = base - discount;
        $('#summary-base').text('$' + base.toFixed(2));
        $('#summary-discount').text('−$' + discount.toFixed(2));
        $('#summary-net').text('$' + net.toFixed(2));
        if (scheme === 'installments' && count > 0){
            var installAmt = net / count;
            $('#summary-installment').text('$' + installAmt.toFixed(2));
            $('#summary-installment-row').show();
        } else {
            $('#summary-installment-row').hide();
        }
    }

    function resetEnrollForm(){
        $('#enroll-area').val('');
        $('#enroll-course').html('<option value=""><?php echo esc_js( __( '— Seleccionar área primero —', 'aura-suite' ) ); ?></option>');
        $('#enroll-base-cost').val('0');
        $('#enroll-scholarship-type').val('none');
        $('#enroll-scholarship-pct').val('0');
        $('#enroll-scholarship-sponsor').val('');
        $('#enroll-payment-scheme').val('full');
        $('#enroll-installment-count').val('6');
        $('#enroll-first-payment').val('<?php echo esc_js( current_time( 'Y-m-d' ) ); ?>');
        $('#enroll-notes').val('');
        $('#row-scholarship-pct, #row-scholarship-sponsor, #row-installments').hide();
        updateCostSummary();
    }

    // Botón inscribir (paso 2)
    $('#btn-do-enroll').on('click', function(){
        var studentId = $('#approve-student-id').val();
        var courseId  = $('#enroll-course').val();
        if (!studentId || !courseId){ showNotice('#enroll-notice', '<?php echo esc_js( __( 'Selecciona un curso.', 'aura-suite' ) ); ?>', 'error'); return; }
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, {
            action             : 'aura_students_enroll',
            nonce              : nonce,
            student_id         : studentId,
            course_id          : courseId,
            base_cost          : $('#enroll-base-cost').val(),
            scholarship_type   : $('#enroll-scholarship-type').val(),
            scholarship_pct    : $('#enroll-scholarship-pct').val(),
            scholarship_sponsor: $('#enroll-scholarship-sponsor').val(),
            payment_scheme     : $('#enroll-payment-scheme').val(),
            installment_count  : $('#enroll-installment-count').val(),
            first_payment_date : $('#enroll-first-payment').val(),
            notes              : $('#enroll-notes').val()
        }, function(res){
            $btn.prop('disabled', false);
            if (!res.success){ showNotice('#enroll-notice', res.data.message, 'error'); return; }
            showNotice('#enroll-notice', res.data.message, 'success');
            setTimeout(function(){ $('#modal-approve').hide(); loadApplicants(currApplicantPage); }, 1400);
        }).fail(function(){ $btn.prop('disabled', false); showNotice('#enroll-notice', '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error'); });
    });

    // ═══════════════════════════════════════════════
    // TAB 2: INSCRIPCIONES ACTIVAS
    // ═══════════════════════════════════════════════

    function loadEnrollments(page){
        currEnrollmentPage = page || 1;
        $.post(ajaxUrl, {
            action  : 'aura_students_list_enrollments',
            nonce   : nonce,
            page    : currEnrollmentPage,
            search  : $('#enrollment-search').val(),
            area_id : $('#enrollment-area').val() || 0,
            status  : $('#enrollment-status').val()
        }, function(res){
            if (!res.success){ $('#enrollments-tbody').html('<tr><td colspan="8">' + res.data.message + '</td></tr>'); return; }
            renderEnrollments(res.data.rows);
            renderPagination('#enrollments-pagination', res.data.page, res.data.total_pages, loadEnrollments);
        });
    }

    function renderEnrollments(rows){
        if (!rows || !rows.length){
            $('#enrollments-tbody').html('<tr><td colspan="8" style="text-align:center;padding:20px;"><?php echo esc_js( __( 'No hay inscripciones.', 'aura-suite' ) ); ?></td></tr>');
            return;
        }
        var paymentColors = { paid:'#059669', partial:'#f59e0b', unpaid:'#ef4444', overdue:'#dc2626' };
        var paymentLabels = { paid:'✅ Pagado', partial:'🟡 Parcial', unpaid:'🔴 Sin pago', overdue:'🚨 Vencido' };
        var html = '';
        $.each(rows, function(i, r){
            var fullName   = escHtml((r.first_name || '') + ' ' + (r.last_name || '')).trim();
            var course     = escHtml(r.course_name || '—');
            var area       = escHtml(r.area_name || '—');
            var pctLabel   = r.scholarship_pct > 0 ? r.scholarship_pct + '%' : '—';
            var netCost    = parseFloat(r.net_cost || 0).toFixed(2);
            var paid       = parseFloat(r.total_paid || 0).toFixed(2);
            var balance    = parseFloat(r.balance_due || 0).toFixed(2);
            var pStatus    = r.payment_status || 'unpaid';
            var pColor     = paymentColors[pStatus] || '#666';
            var pLabel     = paymentLabels[pStatus] || pStatus;
            html += '<tr>';
            html += '<td><strong>' + fullName + '</strong><br><small style="color:#888;">' + escHtml(r.email||'') + '</small></td>';
            html += '<td>' + course + '<br><small style="color:#8b5cf6;">' + area + '</small></td>';
            html += '<td style="text-align:center;">' + pctLabel + '</td>';
            html += '<td style="text-align:right;">$' + netCost + '</td>';
            html += '<td style="text-align:right;color:#059669;">$' + paid + '</td>';
            html += '<td style="text-align:right;color:' + (parseFloat(balance) > 0 ? '#ef4444' : '#059669') + ';">$' + balance + '</td>';
            html += '<td><span style="color:' + pColor + ';font-size:12px;">' + pLabel + '</span></td>';
            html += '<td>';
            html += '<button class="button button-small btn-open-edit-enrollment" style="margin-right:4px;" '
                  + 'data-id="' + r.id + '">✏️ <?php echo esc_js( __( 'Editar', 'aura-suite' ) ); ?></button>';
            if (r.status === 'active' || r.status === 'pending'){
                html += '<button class="button button-small btn-open-graduate" '
                      + 'style="color:#059669;border-color:#059669;" '
                      + 'data-id="' + r.id + '" data-name="' + encodeURIComponent(fullName) + '">🎓 <?php echo esc_js( __( 'Graduar', 'aura-suite' ) ); ?></button>';
            }
            html += '</td>';
            html += '</tr>';
        });
        $('#enrollments-tbody').html(html);
    }

    // Abrir modal Editar inscripción
    $(document).on('click', '.btn-open-edit-enrollment', function(){
        var id = $(this).data('id');
        $.post(ajaxUrl, { action:'aura_students_get_enrollment', nonce:nonce, enrollment_id:id }, function(res){
            if (!res.success){ alert(res.data.message); return; }
            var e = res.data.enrollment;
            $('#edit-enrollment-id').val(e.id);
            $('#edit-enrollment-student').text((e.first_name||'') + ' ' + (e.last_name||''));
            $('#edit-enrollment-course').text(e.course_name||'—');
            $('#edit-scholarship-type').val(e.scholarship_type||'none');
            $('#edit-scholarship-pct').val(e.scholarship_pct||0);
            $('#edit-scholarship-sponsor').val(e.scholarship_sponsor||'');
            $('#edit-payment-scheme').val(e.payment_scheme||'full');
            $('#edit-installment-count').val(e.installment_count||6);
            $('#edit-first-payment').val(e.first_payment_date||'');
            $('#edit-enrollment-status-field').val(e.status||'active');
            $('#edit-enrollment-notes').val(e.notes||'');
            $('#edit-base-cost').val(e.base_cost||0);
            $('#edit-row-installments').toggle(e.payment_scheme === 'installments');
            updateEditCostSummary();
            $('#edit-enrollment-notice').hide();
            $('#modal-edit-enrollment').show();
        });
    });

    $(document).on('input change', '#edit-scholarship-pct, #edit-installment-count', updateEditCostSummary);
    $(document).on('change', '#edit-payment-scheme', function(){
        $('#edit-row-installments').toggle($(this).val() === 'installments');
        updateEditCostSummary();
    });

    function updateEditCostSummary(){
        var base = parseFloat($('#edit-base-cost').val()) || 0;
        var pct  = parseFloat($('#edit-scholarship-pct').val()) || 0;
        var net  = base * (1 - pct/100);
        $('#edit-summary-net').text('$' + net.toFixed(2));
    }

    $('#btn-save-enrollment').on('click', function(){
        var id = $('#edit-enrollment-id').val();
        if (!id) return;
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, {
            action             : 'aura_students_update_enrollment',
            nonce              : nonce,
            enrollment_id      : id,
            scholarship_type   : $('#edit-scholarship-type').val(),
            scholarship_pct    : $('#edit-scholarship-pct').val(),
            scholarship_sponsor: $('#edit-scholarship-sponsor').val(),
            payment_scheme     : $('#edit-payment-scheme').val(),
            installment_count  : $('#edit-installment-count').val(),
            first_payment_date : $('#edit-first-payment').val(),
            status             : $('#edit-enrollment-status-field').val(),
            notes              : $('#edit-enrollment-notes').val()
        }, function(res){
            $btn.prop('disabled', false);
            if (!res.success){ showNotice('#edit-enrollment-notice', res.data.message, 'error'); return; }
            showNotice('#edit-enrollment-notice', res.data.message, 'success');
            setTimeout(function(){ $('#modal-edit-enrollment').hide(); loadEnrollments(currEnrollmentPage); }, 1200);
        }).fail(function(){ $btn.prop('disabled', false); showNotice('#edit-enrollment-notice', '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error'); });
    });

    // Abrir modal Graduar
    $(document).on('click', '.btn-open-graduate', function(){
        $('#graduate-enrollment-id').val($(this).data('id'));
        $('#graduate-student-name').text(decodeURIComponent($(this).data('name')));
        $('#graduate-notice').hide();
        $('#btn-do-graduate').prop('disabled', false);
        $('#modal-graduate').show();
    });

    $('#btn-do-graduate').on('click', function(){
        var id = $('#graduate-enrollment-id').val();
        if (!id) return;
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, { action:'aura_students_graduate', nonce:nonce, enrollment_id:id }, function(res){
            $btn.prop('disabled', false);
            if (!res.success){ showNotice('#graduate-notice', res.data.message, 'error'); return; }
            showNotice('#graduate-notice', res.data.message, 'success');
            setTimeout(function(){ $('#modal-graduate').hide(); loadEnrollments(currEnrollmentPage); }, 1400);
        }).fail(function(){ $btn.prop('disabled', false); showNotice('#graduate-notice', '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error'); });
    });

    // ── Filtros y búsqueda ────────────────────────────────────────
    var searchTimer;
    $('#applicant-search').on('input', function(){ clearTimeout(searchTimer); searchTimer = setTimeout(function(){ loadApplicants(1); }, 350); });
    $('#applicant-profile').on('change', function(){ loadApplicants(1); });
    $('#btn-refresh-applicants').on('click', function(){ loadApplicants(1); });

    $('#enrollment-search').on('input', function(){ clearTimeout(searchTimer); searchTimer = setTimeout(function(){ loadEnrollments(1); }, 350); });
    $('#enrollment-area, #enrollment-status').on('change', function(){ loadEnrollments(1); });
    $('#btn-refresh-enrollments').on('click', function(){ loadEnrollments(1); });

    // ── PAGINACIÓN ────────────────────────────────────────────────
    function renderPagination(selector, current, total, callback){
        if (total <= 1){ $(selector).html(''); return; }
        var html = '<div class="tablenav-pages"><span class="displaying-num"><?php echo esc_js( __( 'Página', 'aura-suite' ) ); ?> ' + current + ' <?php echo esc_js( __( 'de', 'aura-suite' ) ); ?> ' + total + '</span> ';
        if (current > 1) html += '<a class="button aura-page-btn" data-page="' + (current-1) + '">‹</a> ';
        html += '<strong>' + current + '</strong> ';
        if (current < total) html += '<a class="button aura-page-btn" data-page="' + (current+1) + '">›</a>';
        html += '</div>';
        $(selector).html(html);
        $(selector).find('.aura-page-btn').on('click', function(){ callback(parseInt($(this).data('page'))); });
    }

    // ── UTILIDADES ────────────────────────────────────────────────
    function showNotice(selector, msg, type){
        var color = type === 'success' ? '#dcfce7' : '#fee2e2';
        var border = type === 'success' ? '#16a34a' : '#dc2626';
        $(selector).html('<p style="margin:0;padding:10px 14px;background:' + color + ';border-left:4px solid ' + border + ';border-radius:4px;">' + escHtml(msg) + '</p>').show();
    }

    function escHtml(text){
        if (!text) return '';
        return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── INICIALIZAR ───────────────────────────────────────────────
    loadApplicants(1);

});
</script>
