<?php
/**
 * Asignación de Encuestas — Módulo de Formularios (Fase 5)
 *
 * Dos pestañas:
 *  1. Asignar — seleccionar formulario + estudiantes + crear asignaciones
 *  2. Estado  — tabla de asignaciones con filtros y acciones
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_forms_assign' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

// ── Datos para selector de formularios (solo survey/feedback activos) ──
$survey_forms = $wpdb->get_results(
    "SELECT id, title, type FROM {$wpdb->prefix}aura_forms
      WHERE type IN ('survey','feedback') AND is_active = 1 AND deleted_at IS NULL
      ORDER BY title ASC"
);

// ── Datos para selector de cursos ──
$courses = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_student_courses
      WHERE status = 'active'
      ORDER BY name ASC"
);

$nonce = wp_create_nonce( 'aura_forms_nonce' );

$status_labels = [
    'pending'   => [ 'label' => __( 'Pendiente',   'aura-suite' ), 'class' => 'aura-badge-warning'   ],
    'completed' => [ 'label' => __( 'Completado',  'aura-suite' ), 'class' => 'aura-badge-success'   ],
    'expired'   => [ 'label' => __( 'Expirado',    'aura-suite' ), 'class' => 'aura-badge-secondary'  ],
];

$trigger_labels = [
    'manual'                  => __( 'Manual',        'aura-suite' ),
    'on_enrollment_approved'  => __( 'Auto (aprobación)', 'aura-suite' ),
    'on_course_complete'      => __( 'Auto (curso completado)', 'aura-suite' ),
    'scheduled'               => __( 'Programado',    'aura-suite' ),
];
?>
<div class="wrap aura-forms-wrap">

    <h1 class="wp-heading-inline"><?php esc_html_e( 'Encuestas Asignadas', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <!-- ── Pestañas ── -->
    <nav class="aura-admin-tabs" role="tablist">
        <button class="aura-admin-tab active" data-tab="assign" role="tab" aria-selected="true">
            <?php esc_html_e( '✏️ Asignar', 'aura-suite' ); ?>
        </button>
        <button class="aura-admin-tab" data-tab="status" role="tab" aria-selected="false">
            <?php esc_html_e( '📋 Estado de Asignaciones', 'aura-suite' ); ?>
        </button>
    </nav>

    <!-- ══════════════ PESTAÑA: ASIGNAR ══════════════ -->
    <div id="aura-tab-assign" class="aura-admin-tab-content">
        <div class="aura-assignments-layout">

            <!-- Selector de formulario y opciones -->
            <div class="aura-assignments-config card">
                <h2><?php esc_html_e( 'Configuración de la asignación', 'aura-suite' ); ?></h2>

                <div class="aura-field-group">
                    <label for="assign-form-id"><?php esc_html_e( 'Formulario (encuesta / feedback)', 'aura-suite' ); ?> <span class="required">*</span></label>
                    <select id="assign-form-id" class="widefat">
                        <option value=""><?php esc_html_e( '— Selecciona un formulario —', 'aura-suite' ); ?></option>
                        <?php foreach ( $survey_forms as $sf ) : ?>
                            <option value="<?php echo esc_attr( $sf->id ); ?>">
                                <?php echo esc_html( $sf->title ); ?>
                                (<?php echo esc_html( $sf->type === 'survey' ? __( 'Encuesta', 'aura-suite' ) : __( 'Feedback', 'aura-suite' ) ); ?>)
                            </option>
                        <?php endforeach; ?>
                        <?php if ( empty( $survey_forms ) ) : ?>
                            <option disabled><?php esc_html_e( 'No hay formularios de encuesta activos', 'aura-suite' ); ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="aura-field-group">
                    <label for="assign-expires"><?php esc_html_e( 'Fecha de expiración (opcional)', 'aura-suite' ); ?></label>
                    <input type="date" id="assign-expires" min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
                </div>

                <div class="aura-field-group">
                    <label for="assign-notes"><?php esc_html_e( 'Notas (opcional)', 'aura-suite' ); ?></label>
                    <textarea id="assign-notes" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'Notas internas sobre esta asignación…', 'aura-suite' ); ?>"></textarea>
                </div>

                <hr>

                <p class="aura-assignments-selected-count">
                    <strong id="assign-selected-count">0</strong> <?php esc_html_e( 'estudiante(s) seleccionado(s)', 'aura-suite' ); ?>
                </p>

                <button type="button" id="assign-submit-btn" class="button button-primary" disabled>
                    <?php esc_html_e( 'Asignar a seleccionados', 'aura-suite' ); ?>
                </button>

                <div id="assign-result" class="aura-assign-result" style="display:none;"></div>
            </div>

            <!-- Tabla de selección de estudiantes -->
            <div class="aura-assignments-students card">
                <h2><?php esc_html_e( 'Seleccionar estudiantes', 'aura-suite' ); ?></h2>

                <div class="aura-student-filters">
                    <select id="student-filter-status">
                        <option value="active"><?php esc_html_e( 'Activos', 'aura-suite' ); ?></option>
                        <option value="approved"><?php esc_html_e( 'Aprobados', 'aura-suite' ); ?></option>
                        <option value="graduated"><?php esc_html_e( 'Graduados', 'aura-suite' ); ?></option>
                        <option value="all"><?php esc_html_e( 'Todos', 'aura-suite' ); ?></option>
                    </select>

                    <select id="student-filter-course">
                        <option value=""><?php esc_html_e( 'Todos los cursos', 'aura-suite' ); ?></option>
                        <?php foreach ( $courses as $c ) : ?>
                            <option value="<?php echo esc_attr( $c->id ); ?>"><?php echo esc_html( $c->name ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="search" id="student-search" placeholder="<?php esc_attr_e( 'Buscar por nombre o email…', 'aura-suite' ); ?>">

                    <button type="button" id="students-load-btn" class="button button-secondary">
                        <?php esc_html_e( 'Buscar', 'aura-suite' ); ?>
                    </button>
                </div>

                <div id="students-table-wrap">
                    <p class="aura-muted"><?php esc_html_e( 'Usa los filtros para buscar estudiantes.', 'aura-suite' ); ?></p>
                </div>
            </div>

        </div><!-- .aura-assignments-layout -->
    </div><!-- #aura-tab-assign -->

    <!-- ══════════════ PESTAÑA: ESTADO ══════════════ -->
    <div id="aura-tab-status" class="aura-admin-tab-content" style="display:none;">

        <div class="aura-status-filters">
            <select id="status-filter-form">
                <option value=""><?php esc_html_e( 'Todos los formularios', 'aura-suite' ); ?></option>
                <?php foreach ( $survey_forms as $sf ) : ?>
                    <option value="<?php echo esc_attr( $sf->id ); ?>"><?php echo esc_html( $sf->title ); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="status-filter-status">
                <option value="all"><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                <option value="pending"><?php esc_html_e( 'Pendiente', 'aura-suite' ); ?></option>
                <option value="completed"><?php esc_html_e( 'Completado', 'aura-suite' ); ?></option>
                <option value="expired"><?php esc_html_e( 'Expirado', 'aura-suite' ); ?></option>
            </select>

            <button type="button" id="status-load-btn" class="button button-secondary">
                <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
            </button>
        </div>

        <div id="status-table-wrap">
            <p class="aura-muted"><?php esc_html_e( 'Cargando asignaciones…', 'aura-suite' ); ?></p>
        </div>

        <div id="status-pagination" class="tablenav bottom" style="display:none;">
            <div class="tablenav-pages">
                <span id="status-total-count" class="displaying-num"></span>
                <span id="status-pagination-links" class="pagination-links"></span>
            </div>
        </div>

    </div><!-- #aura-tab-status -->

</div><!-- .wrap -->

<?php // ══════════════ JAVASCRIPT ══════════════ ?>
<script type="text/javascript">
/* global jQuery */
( function ( $ ) {
    'use strict';

    var NONCE   = <?php echo wp_json_encode( $nonce ); ?>;
    var AJAXURL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var statusPaged   = 1;
    var selectedStudentIds = [];

    // ── Helpers ──────────────────────────────────────────────────

    function showNotice( msg, type ) {
        type = type || 'success';
        var $n = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + msg + '</p></div>' );
        $( '.wp-header-end' ).length
            ? $( '.wp-header-end' ).after( $n )
            : $( '.wrap h1' ).first().after( $n );
        setTimeout( function () { $n.fadeOut( 400, function () { $n.remove(); } ); }, 5000 );
    }

    function escHtml( str ) {
        return $( '<span>' ).text( String( str || '' ) ).html();
    }

    // ── Pestañas ─────────────────────────────────────────────────

    $( '.aura-admin-tab' ).on( 'click', function () {
        var target = $( this ).data( 'tab' );
        $( '.aura-admin-tab' ).removeClass( 'active' ).attr( 'aria-selected', 'false' );
        $( this ).addClass( 'active' ).attr( 'aria-selected', 'true' );
        $( '.aura-admin-tab-content' ).hide();
        $( '#aura-tab-' + target ).show();

        if ( target === 'status' && $( '#status-table-wrap' ).find( 'table' ).length === 0 ) {
            loadStatus();
        }
    } );

    // ── Cargar lista de estudiantes ───────────────────────────────

    $( '#students-load-btn' ).on( 'click', loadStudents );
    $( '#student-search' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) { e.preventDefault(); loadStudents(); }
    } );

    function loadStudents() {
        var $wrap = $( '#students-table-wrap' );
        $wrap.html( '<p><?php echo esc_js( __( 'Buscando estudiantes…', 'aura-suite' ) ); ?></p>' );
        selectedStudentIds = [];
        updateSelectedCount();

        $.post( AJAXURL, {
            action:         'aura_forms_get_students_list',
            nonce:          NONCE,
            filter_status:  $( '#student-filter-status' ).val(),
            filter_course:  $( '#student-filter-course' ).val(),
            search:         $( '#student-search' ).val(),
        } )
        .done( function ( res ) {
            if ( ! res || ! res.success ) {
                $wrap.html( '<p class="notice-error">' + escHtml( ( res && res.data && res.data.message ) || '<?php echo esc_js( __( 'Error al cargar estudiantes.', 'aura-suite' ) ); ?>' ) + '</p>' );
                return;
            }

            var students = res.data.students;
            if ( ! students || students.length === 0 ) {
                $wrap.html( '<p class="aura-muted"><?php echo esc_js( __( 'No se encontraron estudiantes.', 'aura-suite' ) ); ?></p>' );
                return;
            }

            var html = '<table class="wp-list-table widefat striped fixed aura-students-select-table">'
                + '<thead><tr>'
                + '<th class="check-column"><input type="checkbox" id="select-all-students"></th>'
                + '<th><?php echo esc_js( __( 'Nombre', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Email', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Estado', 'aura-suite' ) ); ?></th>'
                + '</tr></thead><tbody>';

            $.each( students, function ( _, s ) {
                html += '<tr>'
                    + '<td class="check-column"><input type="checkbox" class="student-checkbox" value="' + escHtml( s.id ) + '"></td>'
                    + '<td>' + escHtml( s.first_name + ' ' + s.last_name ) + '</td>'
                    + '<td>' + escHtml( s.email ) + '</td>'
                    + '<td><span class="aura-badge aura-badge-' + escHtml( s.status ) + '">' + escHtml( s.status ) + '</span></td>'
                    + '</tr>';
            } );
            html += '</tbody></table>';
            $wrap.html( html );
        } )
        .fail( function () {
            $wrap.html( '<p class="notice-error"><?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?></p>' );
        } );
    }

    // ── Selección de estudiantes ──────────────────────────────────

    $( document ).on( 'change', '#select-all-students', function () {
        var checked = $( this ).is( ':checked' );
        $( '.student-checkbox' ).prop( 'checked', checked );
        rebuildSelectedIds();
    } );

    $( document ).on( 'change', '.student-checkbox', function () {
        rebuildSelectedIds();
    } );

    function rebuildSelectedIds() {
        selectedStudentIds = [];
        $( '.student-checkbox:checked' ).each( function () {
            selectedStudentIds.push( parseInt( $( this ).val(), 10 ) );
        } );
        updateSelectedCount();
    }

    function updateSelectedCount() {
        $( '#assign-selected-count' ).text( selectedStudentIds.length );
        $( '#assign-submit-btn' ).prop( 'disabled', selectedStudentIds.length === 0 );
    }

    // ── Crear asignaciones ────────────────────────────────────────

    $( '#assign-submit-btn' ).on( 'click', function () {
        var formId = $( '#assign-form-id' ).val();
        if ( ! formId ) {
            showNotice( '<?php echo esc_js( __( 'Selecciona un formulario primero.', 'aura-suite' ) ); ?>', 'error' );
            return;
        }
        if ( selectedStudentIds.length === 0 ) {
            showNotice( '<?php echo esc_js( __( 'Selecciona al menos un estudiante.', 'aura-suite' ) ); ?>', 'error' );
            return;
        }

        var $btn    = $( this );
        var $result = $( '#assign-result' );

        $btn.text( '…' ).prop( 'disabled', true );
        $result.hide();

        $.post( AJAXURL, {
            action:      'aura_forms_assign',
            nonce:       NONCE,
            form_id:     formId,
            student_ids: selectedStudentIds,
            expires_at:  $( '#assign-expires' ).val(),
            notes:       $( '#assign-notes' ).val(),
        } )
        .done( function ( res ) {
            var msg = ( res && res.data && res.data.message ) || '';
            if ( res && res.success ) {
                $result.removeClass( 'notice-error' ).addClass( 'notice-success' )
                    .text( msg ).show();
                selectedStudentIds = [];
                updateSelectedCount();
                $( '.student-checkbox' ).prop( 'checked', false );
                $( '#select-all-students' ).prop( 'checked', false );
            } else {
                $result.removeClass( 'notice-success' ).addClass( 'notice-error' )
                    .text( msg || '<?php echo esc_js( __( 'Error al asignar.', 'aura-suite' ) ); ?>' ).show();
            }
            $btn.text( '<?php echo esc_js( __( 'Asignar a seleccionados', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
        } )
        .fail( function () {
            $result.removeClass( 'notice-success' ).addClass( 'notice-error' )
                .text( '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>' ).show();
            $btn.text( '<?php echo esc_js( __( 'Asignar a seleccionados', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
        } );
    } );

    // ── Cargar tabla de estado ────────────────────────────────────

    $( '#status-load-btn' ).on( 'click', function () {
        statusPaged = 1;
        loadStatus();
    } );

    function loadStatus() {
        var $wrap = $( '#status-table-wrap' );
        $wrap.html( '<p><?php echo esc_js( __( 'Cargando…', 'aura-suite' ) ); ?></p>' );

        $.post( AJAXURL, {
            action:         'aura_forms_list_assignments',
            nonce:          NONCE,
            filter_form:    $( '#status-filter-form' ).val(),
            filter_status:  $( '#status-filter-status' ).val(),
            paged:          statusPaged,
        } )
        .done( function ( res ) {
            if ( ! res || ! res.success ) {
                $wrap.html( '<p class="notice-error"><?php echo esc_js( __( 'Error al cargar. Intenta de nuevo.', 'aura-suite' ) ); ?></p>' );
                return;
            }

            var data  = res.data;
            var rows  = data.rows;

            $( '#status-total-count' ).text( data.total + ' <?php echo esc_js( __( 'elementos', 'aura-suite' ) ); ?>' );

            if ( ! rows || rows.length === 0 ) {
                $wrap.html( '<p class="aura-muted"><?php echo esc_js( __( 'No se encontraron asignaciones.', 'aura-suite' ) ); ?></p>' );
                $( '#status-pagination' ).hide();
                return;
            }

            var statusMap = {
                'pending':   '<span class="aura-badge aura-badge-warning"><?php echo esc_js( __( 'Pendiente', 'aura-suite' ) ); ?></span>',
                'completed': '<span class="aura-badge aura-badge-success"><?php echo esc_js( __( 'Completado', 'aura-suite' ) ); ?></span>',
                'expired':   '<span class="aura-badge aura-badge-secondary"><?php echo esc_js( __( 'Expirado', 'aura-suite' ) ); ?></span>',
            };

            var html = '<table class="wp-list-table widefat fixed striped aura-status-table">'
                + '<thead><tr>'
                + '<th><?php echo esc_js( __( 'Formulario', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Estudiante', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Asignado el', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Expira el', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Estado', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Completado el', 'aura-suite' ) ); ?></th>'
                + '<th><?php echo esc_js( __( 'Acciones', 'aura-suite' ) ); ?></th>'
                + '</tr></thead><tbody>';

            $.each( rows, function ( _, r ) {
                var badge   = statusMap[ r.status ] || escHtml( r.status );
                var expires = r.expires_at ? escHtml( r.expires_at.substring(0,10) ) : '—';
                var done    = r.completed_at ? escHtml( r.completed_at.substring(0,10) ) : '—';
                var actions = '';

                if ( r.status === 'pending' ) {
                    actions = '<button type="button" class="button button-small aura-revoke-btn" data-id="' + escHtml( r.id ) + '">'
                            + '<?php echo esc_js( __( 'Revocar', 'aura-suite' ) ); ?></button>';
                }

                html += '<tr id="aura-assignment-row-' + escHtml( r.id ) + '">'
                    + '<td>' + escHtml( r.form_title ) + '</td>'
                    + '<td>' + escHtml( r.student_name ) + '<br><small>' + escHtml( r.student_email ) + '</small></td>'
                    + '<td>' + escHtml( ( r.assigned_at || '' ).substring(0,10) ) + '</td>'
                    + '<td>' + expires + '</td>'
                    + '<td>' + badge + '</td>'
                    + '<td>' + done + '</td>'
                    + '<td>' + actions + '</td>'
                    + '</tr>';
            } );

            html += '</tbody></table>';
            $wrap.html( html );

            // Paginación simple
            if ( data.total_pages > 1 ) {
                var paginLinks = '';
                if ( statusPaged > 1 ) {
                    paginLinks += '<a href="#" class="status-page-link button" data-page="' + ( statusPaged - 1 ) + '">&laquo;</a> ';
                }
                paginLinks += '<?php echo esc_js( __( 'Pág.', 'aura-suite' ) ); ?> ' + statusPaged + ' / ' + data.total_pages;
                if ( statusPaged < data.total_pages ) {
                    paginLinks += ' <a href="#" class="status-page-link button" data-page="' + ( statusPaged + 1 ) + '">&raquo;</a>';
                }
                $( '#status-pagination-links' ).html( paginLinks );
                $( '#status-pagination' ).show();
            } else {
                $( '#status-pagination' ).hide();
            }
        } )
        .fail( function () {
            $wrap.html( '<p class="notice-error"><?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?></p>' );
        } );
    }

    // Paginación — links 
    $( document ).on( 'click', '.status-page-link', function ( e ) {
        e.preventDefault();
        statusPaged = parseInt( $( this ).data( 'page' ), 10 );
        loadStatus();
    } );

    // Revocar assignment
    $( document ).on( 'click', '.aura-revoke-btn', function () {
        var $btn = $( this );
        var id   = $btn.data( 'id' );

        if ( ! window.confirm( '<?php echo esc_js( __( '¿Revocar esta asignación? El estudiante ya no verá la encuesta.', 'aura-suite' ) ); ?>' ) ) {
            return;
        }

        $btn.text( '…' ).prop( 'disabled', true );

        $.post( AJAXURL, { action: 'aura_forms_revoke', nonce: NONCE, assignment_id: id } )
            .done( function ( res ) {
                if ( res && res.success ) {
                    $( '#aura-assignment-row-' + id ).fadeOut( 300, function () { $( this ).remove(); } );
                    showNotice( res.data.message );
                } else {
                    var msg = ( res && res.data && res.data.message ) || '<?php echo esc_js( __( 'Error al revocar.', 'aura-suite' ) ); ?>';
                    showNotice( msg, 'error' );
                    $btn.text( '<?php echo esc_js( __( 'Revocar', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
                }
            } )
            .fail( function () {
                showNotice( '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error' );
                $btn.text( '<?php echo esc_js( __( 'Revocar', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
            } );
    } );

    // Cargar estado inicial
    loadStatus();

} )( jQuery );
</script>

<?php // ── Estilos inline del panel de asignaciones ── ?>
<style>
.aura-assignments-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 20px;
    margin-top: 16px;
}
.aura-assignments-layout .card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 16px 20px;
}
.aura-assignments-layout h2 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
    color: #111827;
    border-bottom: 1px solid #f0f0f1;
    padding-bottom: 8px;
}
.aura-field-group {
    margin-bottom: 12px;
}
.aura-field-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}
.aura-field-group select,
.aura-field-group input,
.aura-field-group textarea {
    width: 100%;
}
.aura-assignments-selected-count {
    font-size: 13px;
    margin: 0 0 10px;
}
.aura-assign-result {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
}
.aura-assign-result.notice-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.aura-assign-result.notice-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.aura-student-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.aura-student-filters select,
.aura-student-filters input { min-width: 140px; }
.aura-students-select-table .check-column { width: 30px; }
.aura-status-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 12px 0 16px;
    padding: 12px 14px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}
.aura-status-filters select { min-width: 160px; }
.aura-status-table .column-form    { width: 22%; }
.aura-status-table .column-student { width: 20%; }
.aura-muted { color: #6b7280; font-size: 13px; }
/* Pestañas admin */
.aura-admin-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e5e7eb;
    margin: 12px 0 0;
}
.aura-admin-tab {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    color: #6b7280;
    transition: color .15s, border-color .15s;
    margin-bottom: -2px;
}
.aura-admin-tab.active,
.aura-admin-tab:hover {
    color: #2563eb;
    border-bottom-color: #2563eb;
}
.aura-admin-tab-content {
    padding-top: 16px;
}
@media screen and (max-width: 960px) {
    .aura-assignments-layout {
        grid-template-columns: 1fr;
    }
}
</style>
