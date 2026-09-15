<?php
/**
 * Postulantes Pendientes (tipo enrollment) — Módulo de Formularios
 *
 * Panel de revisión de postulaciones automáticas generadas desde formularios
 * de tipo 'enrollment'. Cada fila muestra el postulante, el curso, el estado
 * del enrollment y permite aprobar, rechazar o marcar como retirado.
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_forms_enrollment_review' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

// ─────────────────────────────────────────────────────────────
// FILTROS
// ─────────────────────────────────────────────────────────────

$filter_form   = isset( $_GET['filter_form'] )   ? absint( $_GET['filter_form'] )      : 0;
$filter_course = isset( $_GET['filter_course'] ) ? absint( $_GET['filter_course'] )    : 0;
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : 'all';
$paged         = isset( $_GET['paged'] )         ? max( 1, absint( $_GET['paged'] ) )  : 1;
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

$valid_statuses = [ 'all', 'pending', 'active', 'withdrawn', 'suspended' ];
if ( ! in_array( $filter_status, $valid_statuses, true ) ) {
    $filter_status = 'all';
}

// ─────────────────────────────────────────────────────────────
// OPCIONES DE FILTRO
// ─────────────────────────────────────────────────────────────

$forms_options = $wpdb->get_results(
    "SELECT id, title FROM {$wpdb->prefix}aura_forms
      WHERE type = 'enrollment' AND is_active = 1 AND deleted_at IS NULL
      ORDER BY title ASC"
);

$courses_options = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_student_courses
      WHERE status = 'active'
      ORDER BY name ASC"
);

// ─────────────────────────────────────────────────────────────
// QUERY PRINCIPAL
// ─────────────────────────────────────────────────────────────

$where_parts = [ 'fs.enrollment_id IS NOT NULL', 'f.type = \'enrollment\'' ];
$where_args  = [];

if ( $filter_form ) {
    $where_parts[] = 'fs.form_id = %d';
    $where_args[]  = $filter_form;
}

if ( $filter_course ) {
    $where_parts[] = 'se.course_id = %d';
    $where_args[]  = $filter_course;
}

if ( $filter_status !== 'all' ) {
    $where_parts[] = 'se.status = %s';
    $where_args[]  = $filter_status;
}

$where_sql = implode( ' AND ', $where_parts );

$count_sql = "
    SELECT COUNT(*)
      FROM {$wpdb->prefix}aura_form_submissions fs
 LEFT JOIN {$wpdb->prefix}aura_forms f               ON f.id = fs.form_id
 LEFT JOIN {$wpdb->prefix}aura_student_enrollments se ON se.id = fs.enrollment_id
 LEFT JOIN {$wpdb->prefix}aura_students st            ON st.id = se.student_id
     WHERE {$where_sql}
";

$data_sql = "
    SELECT
        fs.id           AS submission_id,
        fs.form_id,
        fs.submitted_at AS submitted_at,
        fs.data_json,
        f.title         AS form_title,
        se.id           AS enrollment_id,
        se.status       AS enrollment_status,
        se.course_id,
        se.enrollment_date,
        c.name          AS course_name,
        st.id           AS student_id,
        st.first_name,
        st.last_name,
        st.email,
        st.phone,
        st.status       AS student_status
      FROM {$wpdb->prefix}aura_form_submissions fs
 LEFT JOIN {$wpdb->prefix}aura_forms f               ON f.id = fs.form_id
 LEFT JOIN {$wpdb->prefix}aura_student_enrollments se ON se.id = fs.enrollment_id
 LEFT JOIN {$wpdb->prefix}aura_students st            ON st.id = se.student_id
 LEFT JOIN {$wpdb->prefix}aura_student_courses c      ON c.id = se.course_id
     WHERE {$where_sql}
  ORDER BY fs.id DESC
     LIMIT %d OFFSET %d
";

$where_args_count = $where_args;
$where_args_data  = array_merge( $where_args, [ $per_page, $offset ] );

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
$total_items = $where_args_count
    ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_args_count ) )
    : (int) $wpdb->get_var( $count_sql );

$rows = $where_args_data
    ? $wpdb->get_results( $wpdb->prepare( $data_sql, $where_args_data ) )
    : $wpdb->get_results( sprintf( $data_sql, $per_page, $offset ) );
// phpcs:enable

$total_pages = (int) ceil( $total_items / $per_page );

// ─────────────────────────────────────────────────────────────
// ETIQUETAS DE ESTADO
// ─────────────────────────────────────────────────────────────

$enrollment_labels = [
    'pending'   => [ 'label' => __( 'Pendiente',  'aura-suite' ), 'class' => 'aura-badge-warning'  ],
    'active'    => [ 'label' => __( 'Activo',     'aura-suite' ), 'class' => 'aura-badge-success'  ],
    'withdrawn' => [ 'label' => __( 'Retirado',   'aura-suite' ), 'class' => 'aura-badge-secondary'],
    'suspended' => [ 'label' => __( 'Suspendido', 'aura-suite' ), 'class' => 'aura-badge-danger'   ],
    'completed' => [ 'label' => __( 'Completado', 'aura-suite' ), 'class' => 'aura-badge-info'     ],
];

$student_labels = [
    'applicant' => [ 'label' => __( 'Postulante', 'aura-suite' ), 'class' => 'aura-badge-primary'   ],
    'approved'  => [ 'label' => __( 'Aprobado',   'aura-suite' ), 'class' => 'aura-badge-success'   ],
    'active'    => [ 'label' => __( 'Activo',      'aura-suite' ), 'class' => 'aura-badge-success'  ],
    'rejected'  => [ 'label' => __( 'Rechazado',  'aura-suite' ), 'class' => 'aura-badge-danger'    ],
    'withdrawn' => [ 'label' => __( 'Retirado',   'aura-suite' ), 'class' => 'aura-badge-secondary' ],
    'graduated' => [ 'label' => __( 'Graduado',   'aura-suite' ), 'class' => 'aura-badge-info'      ],
];

// ─────────────────────────────────────────────────────────────
// NONCE
// ─────────────────────────────────────────────────────────────

$nonce = wp_create_nonce( 'aura_forms_nonce' );

// ─────────────────────────────────────────────────────────────
// HELPERS LOCALES
// ─────────────────────────────────────────────────────────────

/**
 * Devuelve la URL base del panel de postulantes (sin parámetros de filtro).
 */
if ( ! function_exists( 'aura_enrollment_panel_url' ) ) {
    function aura_enrollment_panel_url(): string {
        return admin_url( 'admin.php?page=aura-forms-enrollments' );
    }
}

/**
 * Construye la URL conservando los filtros actuales y reemplazando el parámetro dado.
 */
if ( ! function_exists( 'aura_enrollment_filter_url' ) ) {
    function aura_enrollment_filter_url( string $param, $value ): string {
        $args = [
            'page'          => 'aura-forms-enrollments',
            'filter_form'   => isset( $_GET['filter_form'] )   ? absint( $_GET['filter_form'] )                       : '',
            'filter_course' => isset( $_GET['filter_course'] ) ? absint( $_GET['filter_course'] )                     : '',
            'filter_status' => isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : 'all',
        ];
        $args[ $param ] = $value;
        return add_query_arg( array_filter( $args, fn( $v ) => $v !== '' && $v !== '0' ), admin_url( 'admin.php' ) );
    }
}
?>
<div class="wrap aura-forms-wrap">

    <h1 class="wp-heading-inline"><?php esc_html_e( 'Postulantes — Inscripciones desde Formularios', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <?php // ── Filtros ── ?>
    <form method="get" class="aura-enrollment-filters">
        <input type="hidden" name="page" value="aura-forms-enrollments">

        <select name="filter_form">
            <option value=""><?php esc_html_e( 'Todos los formularios', 'aura-suite' ); ?></option>
            <?php foreach ( $forms_options as $fo ) : ?>
                <option value="<?php echo esc_attr( $fo->id ); ?>"
                    <?php selected( $filter_form, $fo->id ); ?>>
                    <?php echo esc_html( $fo->title ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="filter_course">
            <option value=""><?php esc_html_e( 'Todos los cursos', 'aura-suite' ); ?></option>
            <?php foreach ( $courses_options as $co ) : ?>
                <option value="<?php echo esc_attr( $co->id ); ?>"
                    <?php selected( $filter_course, $co->id ); ?>>
                    <?php echo esc_html( $co->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="filter_status">
            <option value="all" <?php selected( $filter_status, 'all' ); ?>><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="pending"   <?php selected( $filter_status, 'pending' );   ?>><?php esc_html_e( 'Pendiente',  'aura-suite' ); ?></option>
            <option value="active"    <?php selected( $filter_status, 'active' );    ?>><?php esc_html_e( 'Activo',     'aura-suite' ); ?></option>
            <option value="withdrawn" <?php selected( $filter_status, 'withdrawn' ); ?>><?php esc_html_e( 'Retirado',   'aura-suite' ); ?></option>
            <option value="suspended" <?php selected( $filter_status, 'suspended' ); ?>><?php esc_html_e( 'Suspendido', 'aura-suite' ); ?></option>
        </select>

        <button type="submit" class="button button-secondary">
            <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
        </button>

        <?php if ( $filter_form || $filter_course || $filter_status !== 'all' ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-enrollments' ) ); ?>" class="button button-link">
                <?php esc_html_e( 'Limpiar filtros', 'aura-suite' ); ?>
            </a>
        <?php endif; ?>
    </form>

    <?php // ── Tabla ── ?>
    <?php if ( empty( $rows ) ) : ?>
        <div class="aura-notice aura-notice-info">
            <p><?php esc_html_e( 'No se encontraron postulantes con los filtros seleccionados.', 'aura-suite' ); ?></p>
        </div>
    <?php else : ?>

    <p class="aura-enrollment-total">
        <?php
        printf(
            /* translators: %d = total de registros */
            esc_html( _n( '%d postulante', '%d postulantes', $total_items, 'aura-suite' ) ),
            (int) $total_items
        );
        ?>
    </p>

    <table class="wp-list-table widefat fixed striped aura-enrollment-table">
        <thead>
            <tr>
                <th scope="col" class="column-name"><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                <th scope="col" class="column-email"><?php esc_html_e( 'Email', 'aura-suite' ); ?></th>
                <th scope="col" class="column-course"><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                <th scope="col" class="column-form"><?php esc_html_e( 'Formulario', 'aura-suite' ); ?></th>
                <th scope="col" class="column-date"><?php esc_html_e( 'Postulación', 'aura-suite' ); ?></th>
                <th scope="col" class="column-estatus"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th scope="col" class="column-actions"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $rows as $row ) :
            $es    = $enrollment_labels[ $row->enrollment_status ] ?? [ 'label' => esc_html( $row->enrollment_status ), 'class' => '' ];
            $full_name = trim( $row->first_name . ' ' . $row->last_name );
            if ( ! $full_name ) {
                $full_name = $row->email;
            }
        ?>
            <tr id="aura-enroll-row-<?php echo esc_attr( $row->submission_id ); ?>">

                <td class="column-name">
                    <strong><?php echo esc_html( $full_name ); ?></strong>
                    <?php if ( $row->phone ) : ?>
                        <br><small><?php echo esc_html( $row->phone ); ?></small>
                    <?php endif; ?>
                </td>

                <td class="column-email">
                    <a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a>
                </td>

                <td class="column-course">
                    <?php echo $row->course_name ? esc_html( $row->course_name ) : '<em>' . esc_html__( 'Sin curso', 'aura-suite' ) . '</em>'; ?>
                </td>

                <td class="column-form">
                    <?php echo esc_html( $row->form_title ); ?>
                </td>

                <td class="column-date">
                    <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row->submitted_at ) ) ); ?>
                </td>

                <td class="column-estatus">
                    <span class="aura-badge <?php echo esc_attr( $es['class'] ); ?>">
                        <?php echo esc_html( $es['label'] ); ?>
                    </span>
                </td>

                <td class="column-actions">
                    <button
                        type="button"
                        class="button button-small aura-view-detail"
                        data-id="<?php echo esc_attr( $row->submission_id ); ?>"
                        data-form-id="<?php echo esc_attr( $row->form_id ); ?>"
                        data-json="<?php echo esc_attr( $row->data_json ); ?>"
                        data-name="<?php echo esc_attr( $full_name ); ?>">
                        <?php esc_html_e( 'Ver', 'aura-suite' ); ?>
                    </button>

                    <?php if ( in_array( $row->enrollment_status, [ 'pending' ], true ) ) : ?>
                        <button
                            type="button"
                            class="button button-small button-primary aura-enroll-approve"
                            data-id="<?php echo esc_attr( $row->submission_id ); ?>"
                            data-name="<?php echo esc_attr( $full_name ); ?>">
                            <?php esc_html_e( 'Aprobar', 'aura-suite' ); ?>
                        </button>

                        <button
                            type="button"
                            class="button button-small aura-enroll-reject"
                            data-id="<?php echo esc_attr( $row->submission_id ); ?>"
                            data-name="<?php echo esc_attr( $full_name ); ?>">
                            <?php esc_html_e( 'Rechazar', 'aura-suite' ); ?>
                        </button>
                    <?php endif; ?>

                    <?php if ( in_array( $row->enrollment_status, [ 'pending', 'active' ], true ) ) : ?>
                        <button
                            type="button"
                            class="button button-small aura-enroll-withdraw"
                            data-id="<?php echo esc_attr( $row->submission_id ); ?>"
                            data-name="<?php echo esc_attr( $full_name ); ?>">
                            <?php esc_html_e( 'Retirado', 'aura-suite' ); ?>
                        </button>
                    <?php endif; ?>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php // ── Paginación ── ?>
    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php
                printf(
                    esc_html( _n( '%d elemento', '%d elementos', $total_items, 'aura-suite' ) ),
                    (int) $total_items
                );
                ?>
            </span>
            <?php
            $pagination = paginate_links( [
                'base'      => add_query_arg( 'paged', '%#%' ),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type'      => 'array',
            ] );
            if ( $pagination ) {
                echo '<span class="pagination-links">' . implode( ' ', $pagination ) . '</span>'; // phpcs:ignore
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // empty rows ?>

</div><!-- .wrap -->

<?php // ═══════════════════════════════════════════════════════════ ?>
<?php // MODAL: DETALLE DEL POSTULANTE                               ?>
<?php // ═══════════════════════════════════════════════════════════ ?>
<div id="aura-enrollment-detail-modal" class="aura-modal-overlay" style="display:none;" aria-modal="true" role="dialog">
    <div class="aura-modal aura-modal-md">
        <div class="aura-modal-header">
            <h2 id="aura-detail-modal-title"><?php esc_html_e( 'Detalle del postulante', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div class="aura-modal-body">
            <div id="aura-detail-modal-content">
                <p><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></p>
            </div>
        </div>
        <div class="aura-modal-footer">
            <button type="button" class="button aura-modal-close"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>

<?php // ═══════════════════════════════════════════════════════════ ?>
<?php // MODAL: MOTIVO DE RECHAZO                                    ?>
<?php // ═══════════════════════════════════════════════════════════ ?>
<div id="aura-enrollment-reject-modal" class="aura-modal-overlay" style="display:none;" aria-modal="true" role="dialog">
    <div class="aura-modal aura-modal-sm">
        <div class="aura-modal-header">
            <h2><?php esc_html_e( 'Rechazar postulante', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div class="aura-modal-body">
            <p id="aura-reject-modal-desc"></p>
            <label for="aura-rejection-reason">
                <strong><?php esc_html_e( 'Motivo de rechazo (opcional):', 'aura-suite' ); ?></strong>
            </label>
            <textarea id="aura-rejection-reason" class="widefat" rows="4" placeholder="<?php esc_attr_e( 'Escribe el motivo…', 'aura-suite' ); ?>"></textarea>
        </div>
        <div class="aura-modal-footer">
            <button type="button" id="aura-reject-confirm" class="button button-primary">
                <?php esc_html_e( 'Confirmar rechazo', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button aura-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>

<?php // ═══════════════════════════════════════════════════════════ ?>
<?php // DATOS PARA JS                                               ?>
<?php // ═══════════════════════════════════════════════════════════ ?>
<script type="text/javascript">
/* global jQuery */
( function ( $ ) {
    'use strict';

    var NONCE   = <?php echo wp_json_encode( $nonce ); ?>;
    var AJAXURL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

    // Caché de etiquetas por formId
    var fieldLabelsCache = {};

    // ── Helpers ──────────────────────────────────────────────────

    function showAdminNotice( message, type ) {
        type = type || 'success';
        var $n = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );
        $( '.wp-header-end' ).length
            ? $( '.wp-header-end' ).after( $n )
            : $( '.wrap h1' ).first().after( $n );
        setTimeout( function () { $n.fadeOut( 400, function () { $n.remove(); } ); }, 5000 );
    }

    function openModal( $modal ) {
        $modal.show();
        $modal.find( '.aura-modal-close' ).first().focus();
    }

    function closeModal( $modal ) {
        $modal.hide();
    }

    $( document ).on( 'click', '.aura-modal-close', function () {
        closeModal( $( this ).closest( '.aura-modal-overlay' ) );
    } );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            $( '.aura-modal-overlay:visible' ).each( function () { closeModal( $( this ) ); } );
        }
    } );

    // ── Ver detalle ───────────────────────────────────────────────

    $( document ).on( 'click', '.aura-view-detail', function () {
        var $btn    = $( this );
        var formId  = $btn.data( 'form-id' );
        var dataRaw = $btn.data( 'json' );
        var name    = $btn.data( 'name' );

        $( '#aura-detail-modal-title' ).text(
            <?php echo wp_json_encode( __( 'Detalle:', 'aura-suite' ) ); ?> + ' ' + name
        );
        $( '#aura-detail-modal-content' ).html( '<p><?php echo esc_js( __( 'Cargando…', 'aura-suite' ) ); ?></p>' );
        openModal( $( '#aura-enrollment-detail-modal' ) );

        var data;
        try {
            data = JSON.parse( dataRaw );
        } catch ( err ) {
            $( '#aura-detail-modal-content' ).html( '<p><?php echo esc_js( __( 'Error al leer los datos.', 'aura-suite' ) ); ?></p>' );
            return;
        }

        if ( fieldLabelsCache[ formId ] ) {
            renderDetail( data, fieldLabelsCache[ formId ] );
        } else {
            $.post( AJAXURL, { action: 'aura_forms_get_field_labels', form_id: formId, nonce: NONCE } )
                .done( function ( res ) {
                    var labels = {};
                    if ( res && res.success && res.data && res.data.labels ) {
                        labels = res.data.labels;
                    }
                    fieldLabelsCache[ formId ] = labels;
                    renderDetail( data, labels );
                } )
                .fail( function () {
                    renderDetail( data, {} );
                } );
        }
    } );

    function renderDetail( data, labels ) {
        if ( ! data || typeof data !== 'object' ) {
            $( '#aura-detail-modal-content' ).html( '<p><?php echo esc_js( __( 'Sin datos.', 'aura-suite' ) ); ?></p>' );
            return;
        }

        var html = '<dl class="aura-detail-list">';
        $.each( data, function ( uid, value ) {
            var label = labels[ uid ] || uid;
            var display;

            if ( Array.isArray( value ) ) {
                display = value.join( ', ' );
            } else if ( value === null || value === '' ) {
                display = '<em><?php echo esc_js( __( '(vacío)', 'aura-suite' ) ); ?></em>';
            } else {
                display = $( '<span>' ).text( String( value ) ).html();
            }

            html += '<dt>' + $( '<span>' ).text( String( label ) ).html() + '</dt>';
            html += '<dd>' + display + '</dd>';
        } );
        html += '</dl>';

        $( '#aura-detail-modal-content' ).html( html );
    }

    // ── Aprobar ───────────────────────────────────────────────────

    $( document ).on( 'click', '.aura-enroll-approve', function () {
        var $btn  = $( this );
        var id    = $btn.data( 'id' );
        var name  = $btn.data( 'name' );

        if ( ! window.confirm(
            '<?php echo esc_js( __( '¿Aprobar la postulación de', 'aura-suite' ) ); ?> ' + name + '?'
        ) ) {
            return;
        }

        $btn.text( '…' ).prop( 'disabled', true );

        $.post( AJAXURL, { action: 'aura_forms_approve_enrollment', submission_id: id, nonce: NONCE } )
            .done( function ( res ) {
                if ( res && res.success ) {
                    showAdminNotice( res.data.message );
                    location.reload();
                } else {
                    var msg = ( res && res.data && res.data.message )
                        ? res.data.message
                        : '<?php echo esc_js( __( 'Error al aprobar.', 'aura-suite' ) ); ?>';
                    showAdminNotice( msg, 'error' );
                    $btn.text( '<?php echo esc_js( __( 'Aprobar', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
                }
            } )
            .fail( function () {
                showAdminNotice( '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error' );
                $btn.text( '<?php echo esc_js( __( 'Aprobar', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
            } );
    } );

    // ── Rechazar ──────────────────────────────────────────────────

    var _rejectSubmissionId = null;

    $( document ).on( 'click', '.aura-enroll-reject', function () {
        _rejectSubmissionId = $( this ).data( 'id' );
        var name = $( this ).data( 'name' );

        $( '#aura-reject-modal-desc' ).text(
            '<?php echo esc_js( __( 'Vas a rechazar la postulación de:', 'aura-suite' ) ); ?> ' + name
        );
        $( '#aura-rejection-reason' ).val( '' );
        openModal( $( '#aura-enrollment-reject-modal' ) );
    } );

    $( '#aura-reject-confirm' ).on( 'click', function () {
        if ( ! _rejectSubmissionId ) return;

        var $btn   = $( this );
        var reason = $.trim( $( '#aura-rejection-reason' ).val() );

        $btn.text( '…' ).prop( 'disabled', true );

        $.post( AJAXURL, {
            action:           'aura_forms_reject_enrollment',
            submission_id:    _rejectSubmissionId,
            rejection_reason: reason,
            nonce:            NONCE,
        } )
        .done( function ( res ) {
            closeModal( $( '#aura-enrollment-reject-modal' ) );
            if ( res && res.success ) {
                showAdminNotice( res.data.message );
                location.reload();
            } else {
                var msg = ( res && res.data && res.data.message )
                    ? res.data.message
                    : '<?php echo esc_js( __( 'Error al rechazar.', 'aura-suite' ) ); ?>';
                showAdminNotice( msg, 'error' );
            }
            $btn.text( '<?php echo esc_js( __( 'Confirmar rechazo', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
        } )
        .fail( function () {
            closeModal( $( '#aura-enrollment-reject-modal' ) );
            showAdminNotice( '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error' );
            $btn.text( '<?php echo esc_js( __( 'Confirmar rechazo', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
        } );
    } );

    // ── Retirar ───────────────────────────────────────────────────

    $( document ).on( 'click', '.aura-enroll-withdraw', function () {
        var $btn = $( this );
        var id   = $btn.data( 'id' );
        var name = $btn.data( 'name' );

        if ( ! window.confirm(
            '<?php echo esc_js( __( '¿Marcar como retirado a', 'aura-suite' ) ); ?> ' + name + '?'
        ) ) {
            return;
        }

        $btn.text( '…' ).prop( 'disabled', true );

        $.post( AJAXURL, { action: 'aura_forms_mark_withdrawn', submission_id: id, nonce: NONCE } )
            .done( function ( res ) {
                if ( res && res.success ) {
                    showAdminNotice( res.data.message );
                    location.reload();
                } else {
                    var msg = ( res && res.data && res.data.message )
                        ? res.data.message
                        : '<?php echo esc_js( __( 'Error al marcar como retirado.', 'aura-suite' ) ); ?>';
                    showAdminNotice( msg, 'error' );
                    $btn.text( '<?php echo esc_js( __( 'Retirado', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
                }
            } )
            .fail( function () {
                showAdminNotice( '<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error' );
                $btn.text( '<?php echo esc_js( __( 'Retirado', 'aura-suite' ) ); ?>' ).prop( 'disabled', false );
            } );
    } );

} )( jQuery );
</script>
