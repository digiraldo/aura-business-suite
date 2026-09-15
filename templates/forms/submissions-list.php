<?php
/**
 * Template — Listado de respuestas de un formulario
 *
 * Muestra todas las submissions de un formulario con filtros, paginación,
 * acciones individuales (ver detalle, eliminar) y acciones masivas.
 *
 * Accedido vía: admin.php?page=aura-forms-list&action=responses&id=X
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

$form_id = absint( $_GET['id'] ?? 0 );
if ( ! $form_id ) {
    wp_die( __( 'ID de formulario inválido.', 'aura-suite' ) );
}

// ── Cargar formulario ──────────────────────────────────────────
$form = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id, title, type, slug FROM {$wpdb->prefix}aura_forms WHERE id = %d AND deleted_at IS NULL",
        $form_id
    )
);

if ( ! $form ) {
    wp_die( __( 'Formulario no encontrado.', 'aura-suite' ) );
}

// ── Filtros GET ───────────────────────────────────────────────
$filter_status = isset( $_GET['sub_status'] ) ? sanitize_key( $_GET['sub_status'] ) : '';
$filter_search = isset( $_GET['sub_s'] )      ? sanitize_text_field( wp_unslash( $_GET['sub_s'] ) ) : '';
$per_page      = 20;
$paged         = max( absint( $_GET['paged'] ?? 1 ), 1 );
$offset        = ( $paged - 1 ) * $per_page;

// ── Construir query con filtros ───────────────────────────────
$where  = [ 'form_id = %d' ];
$params = [ $form_id ];

$allowed_statuses = [ 'received', 'reviewed', 'spam' ];
if ( $filter_status && in_array( $filter_status, $allowed_statuses, true ) ) {
    $where[]  = 'status = %s';
    $params[] = $filter_status;
}

if ( $filter_search !== '' ) {
    $where[]  = '(submitted_name LIKE %s OR submitted_email LIKE %s)';
    $like     = '%' . $wpdb->esc_like( $filter_search ) . '%';
    $params[] = $like;
    $params[] = $like;
}

$where_sql = implode( ' AND ', $where );

$total = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions WHERE {$where_sql}",
        ...$params
    )
);

$submissions = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, submitted_name, submitted_email, status, ip_address, submitted_at, enrollment_id
           FROM {$wpdb->prefix}aura_form_submissions
          WHERE {$where_sql}
       ORDER BY submitted_at DESC
          LIMIT %d OFFSET %d",
        ...[ ...$params, $per_page, $offset ]
    )
);

// ── Estadísticas rápidas ──────────────────────────────────────
$stats = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT status, COUNT(*) AS cnt
           FROM {$wpdb->prefix}aura_form_submissions
          WHERE form_id = %d
       GROUP BY status",
        $form_id
    ),
    OBJECT_K
);
$total_all  = array_sum( array_column( $stats, 'cnt' ) );
$total_recv = isset( $stats['received'] )  ? (int) $stats['received']->cnt  : 0;
$total_rev  = isset( $stats['reviewed'] )  ? (int) $stats['reviewed']->cnt  : 0;
$total_spam = isset( $stats['spam'] )      ? (int) $stats['spam']->cnt      : 0;

// ── Paginación ────────────────────────────────────────────────
$total_pages = (int) ceil( $total / $per_page );

$back_url      = admin_url( 'admin.php?page=aura-forms-list' );
$base_url_args = [ 'page' => 'aura-forms-list', 'action' => 'responses', 'id' => $form_id ];
if ( $filter_status ) $base_url_args['sub_status'] = $filter_status;
if ( $filter_search ) $base_url_args['sub_s']      = $filter_search;

$status_labels = [
    'received' => [ 'label' => __( 'Recibida',  'aura-suite' ), 'class' => 'aura-badge-info' ],
    'reviewed' => [ 'label' => __( 'Revisada',  'aura-suite' ), 'class' => 'aura-badge-success' ],
    'spam'     => [ 'label' => __( 'Spam',      'aura-suite' ), 'class' => 'aura-badge-secondary' ],
];

$type_labels = [
    'generic'    => __( 'Genérico',       'aura-suite' ),
    'enrollment' => __( 'Inscripción',    'aura-suite' ),
    'survey'     => __( 'Encuesta',       'aura-suite' ),
    'feedback'   => __( 'Feedback auto.', 'aura-suite' ),
];

$can_delete = current_user_can( 'aura_forms_delete' ) || current_user_can( 'manage_options' );
?>
<div class="wrap aura-forms-wrap aura-submissions-wrap">

    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'Respuestas:', 'aura-suite' ); ?>
        <em><?php echo esc_html( $form->title ); ?></em>
    </h1>
    <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
        &larr; <?php esc_html_e( 'Volver a formularios', 'aura-suite' ); ?>
    </a>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=edit&id=' . $form_id ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Editar formulario', 'aura-suite' ); ?>
    </a>
    <?php if ( current_user_can( 'aura_forms_export' ) || current_user_can( 'manage_options' ) ) : ?>
    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=aura_forms_export_csv&form_id=' . $form_id ), 'aura_forms_nonce', 'nonce' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Exportar CSV', 'aura-suite' ); ?>
    </a>
    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=aura_forms_export_excel&form_id=' . $form_id ), 'aura_forms_nonce', 'nonce' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Exportar Excel', 'aura-suite' ); ?>
    </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <!-- Estadísticas rápidas -->
    <div class="aura-sub-stats-bar">
        <span class="aura-stat-item">
            <strong><?php echo absint( $total_all ); ?></strong>
            <?php esc_html_e( 'total', 'aura-suite' ); ?>
        </span>
        <span class="aura-stat-sep">|</span>
        <span class="aura-stat-item aura-stat-received">
            <strong><?php echo absint( $total_recv ); ?></strong>
            <?php esc_html_e( 'nuevas', 'aura-suite' ); ?>
        </span>
        <span class="aura-stat-sep">|</span>
        <span class="aura-stat-item aura-stat-reviewed">
            <strong><?php echo absint( $total_rev ); ?></strong>
            <?php esc_html_e( 'revisadas', 'aura-suite' ); ?>
        </span>
        <?php if ( $total_spam > 0 ) : ?>
        <span class="aura-stat-sep">|</span>
        <span class="aura-stat-item aura-stat-spam">
            <strong><?php echo absint( $total_spam ); ?></strong>
            <?php esc_html_e( 'spam', 'aura-suite' ); ?>
        </span>
        <?php endif; ?>
        <span class="aura-stat-sep aura-stat-type-sep">—</span>
        <span class="aura-stat-item">
            <?php esc_html_e( 'Tipo:', 'aura-suite' ); ?>
            <strong><?php echo esc_html( $type_labels[ $form->type ] ?? $form->type ); ?></strong>
        </span>
    </div>

    <!-- Filtros -->
    <form method="get" class="aura-forms-filters" id="aura-sub-filter-form">
        <input type="hidden" name="page"   value="aura-forms-list">
        <input type="hidden" name="action" value="responses">
        <input type="hidden" name="id"     value="<?php echo absint( $form_id ); ?>">
        <select name="sub_status">
            <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
            <?php foreach ( $status_labels as $val => $info ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_status, $val ); ?>>
                <?php echo esc_html( $info['label'] ); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="search" name="sub_s" value="<?php echo esc_attr( $filter_search ); ?>"
               placeholder="<?php esc_attr_e( 'Buscar por nombre o email…', 'aura-suite' ); ?>" style="width:240px">
        <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'aura-suite' ); ?></button>
        <?php if ( $filter_status || $filter_search ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $form_id ) ); ?>" class="button">
            <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
        </a>
        <?php endif; ?>
    </form>

    <?php if ( empty( $submissions ) ) : ?>
    <div class="aura-notice aura-notice-info">
        <p><?php esc_html_e( 'No se encontraron respuestas con los filtros aplicados.', 'aura-suite' ); ?></p>
    </div>
    <?php else : ?>

    <!-- Acciones masivas -->
    <?php if ( $can_delete ) : ?>
    <div class="tablenav top" id="aura-bulk-bar">
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector" class="screen-reader-text"><?php esc_html_e( 'Seleccionar acción masiva', 'aura-suite' ); ?></label>
            <select id="aura-bulk-action">
                <option value=""><?php esc_html_e( 'Acciones masivas', 'aura-suite' ); ?></option>
                <option value="mark_reviewed"><?php esc_html_e( 'Marcar como revisadas', 'aura-suite' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Eliminar seleccionadas', 'aura-suite' ); ?></option>
            </select>
            <button id="aura-bulk-apply" class="button action"><?php esc_html_e( 'Aplicar', 'aura-suite' ); ?></button>
            <span id="aura-selected-count" style="margin-left:8px;color:#666;font-size:12px;"></span>
        </div>
        <div class="alignright tablenav-pages">
            <?php if ( $total_pages > 1 ) : ?>
            <span class="displaying-num">
                <?php printf(
                    /* translators: %d = total number of items */
                    esc_html( _n( '%d elemento', '%d elementos', $total, 'aura-suite' ) ),
                    absint( $total )
                ); ?>
            </span>
            <?php
            $page_links = paginate_links( [
                'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php' ) ),
                'format'    => '',
                'add_args'  => $base_url_args,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $paged,
                'type'      => 'plain',
            ] );
            echo wp_kses_post( $page_links );
            ?>
            <?php endif; ?>
        </div>
        <br class="clear">
    </div>
    <?php endif; ?>

    <!-- Tabla de respuestas -->
    <table class="wp-list-table widefat fixed striped aura-subs-table" id="aura-subs-table">
        <thead>
            <tr>
                <?php if ( $can_delete ) : ?>
                <th class="manage-column column-cb check-column" style="width:28px">
                    <input type="checkbox" id="cb-select-all">
                </th>
                <?php endif; ?>
                <th style="width:50px"><?php esc_html_e( '#', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Email', 'aura-suite' ); ?></th>
                <th style="width:100px"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th style="width:145px"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                <th style="width:120px"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $submissions as $sub ) :
                $status_info = $status_labels[ $sub->status ] ?? [ 'label' => esc_html( $sub->status ), 'class' => '' ];
                $view_url    = admin_url( 'admin.php?page=aura-forms-list&action=view-submission&sub_id=' . $sub->id . '&form_id=' . $form_id );
            ?>
            <tr data-sub-id="<?php echo absint( $sub->id ); ?>" id="sub-row-<?php echo absint( $sub->id ); ?>">
                <?php if ( $can_delete ) : ?>
                <th class="check-column">
                    <input type="checkbox" class="cb-sub" value="<?php echo absint( $sub->id ); ?>">
                </th>
                <?php endif; ?>
                <td>
                    <a href="<?php echo esc_url( $view_url ); ?>" title="<?php esc_attr_e( 'Ver detalle', 'aura-suite' ); ?>">
                        <?php echo absint( $sub->id ); ?>
                    </a>
                </td>
                <td>
                    <?php if ( $sub->submitted_name ) : ?>
                        <a href="<?php echo esc_url( $view_url ); ?>" class="aura-view-sub" data-id="<?php echo absint( $sub->id ); ?>">
                            <?php echo esc_html( $sub->submitted_name ); ?>
                        </a>
                    <?php else : ?>
                        <span class="aura-text-muted"><?php esc_html_e( '(anónimo)', 'aura-suite' ); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo $sub->submitted_email ? esc_html( $sub->submitted_email ) : '—'; ?></td>
                <td>
                    <span class="aura-badge <?php echo esc_attr( $status_info['class'] ); ?>">
                        <?php echo esc_html( $status_info['label'] ); ?>
                    </span>
                </td>
                <td>
                    <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $sub->submitted_at ) ) ); ?>
                </td>
                <td>
                    <button type="button" class="button button-small aura-view-sub" data-id="<?php echo absint( $sub->id ); ?>">
                        <?php esc_html_e( 'Ver', 'aura-suite' ); ?>
                    </button>
                    <?php if ( $can_delete ) : ?>
                    <button type="button" class="button button-small aura-delete-sub" data-id="<?php echo absint( $sub->id ); ?>">
                        <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;margin-top:3px;color:#d63638"></span>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación inferior -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php printf(
                    esc_html( _n( '%d elemento', '%d elementos', $total, 'aura-suite' ) ),
                    absint( $total )
                ); ?>
            </span>
            <?php echo wp_kses_post( $page_links ); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // end empty check ?>

</div><!-- .aura-submissions-wrap -->

<!-- Modal de detalle de respuesta -->
<div id="aura-sub-modal-overlay" class="aura-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="aura-sub-modal-title">
    <div class="aura-modal-box aura-sub-detail-box">
        <div class="aura-modal-header">
            <h2 id="aura-sub-modal-title" style="margin:0"><?php esc_html_e( 'Detalle de respuesta', 'aura-suite' ); ?></h2>
            <button type="button" id="aura-sub-modal-close" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div id="aura-sub-modal-body" class="aura-modal-body">
            <p class="aura-modal-loading"><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></p>
        </div>
        <div class="aura-modal-footer">
            <button type="button" id="aura-sub-modal-close-btn" class="button">
                <?php esc_html_e( 'Cerrar', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
.aura-sub-stats-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 10px 0 14px;
    font-size: 13px;
    color: #555;
}
.aura-stat-item strong { font-size: 15px; color: #1d2327; }
.aura-stat-received strong { color: #0073aa; }
.aura-stat-reviewed strong { color: #00a32a; }
.aura-stat-spam     strong { color: #999; }
.aura-stat-sep { color: #ccc; }
.aura-stat-type-sep { margin: 0 4px; }
.aura-subs-table .check-column { width: 2.2em !important; }
/* Modal */
.aura-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.aura-sub-detail-box {
    background: #fff;
    border-radius: 6px;
    width: 680px;
    max-width: 96vw;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 32px rgba(0,0,0,.22);
}
.aura-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
    border-radius: 6px 6px 0 0;
}
.aura-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    color: #666;
    padding: 0 4px;
    border-radius: 4px;
    transition: color .15s;
}
.aura-modal-close:hover { color: #d63638; }
.aura-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}
.aura-modal-footer {
    padding: 12px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
    background: #f9f9f9;
    border-radius: 0 0 6px 6px;
}
.aura-modal-loading { color: #666; font-style: italic; }
/* Meta info dentro del modal */
.aura-sub-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 16px;
    font-size: 12px;
    color: #666;
    background: #f6f7f7;
    border-radius: 4px;
    padding: 10px 14px;
    margin-bottom: 16px;
}
.aura-sub-meta strong { color: #1d2327; }
/* Campos de respuesta */
.aura-field-row {
    display: flex;
    flex-direction: column;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}
.aura-field-row:last-child { border-bottom: none; }
.aura-field-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #888;
    margin-bottom: 3px;
}
.aura-field-value {
    font-size: 13px;
    color: #1d2327;
    word-break: break-word;
    white-space: pre-wrap;
}
.aura-field-empty { color: #bbb; font-style: italic; }
</style>

<script>
( function ( $ ) {
    'use strict';

    const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    const nonce   = <?php echo wp_json_encode( wp_create_nonce( 'aura_forms_nonce' ) ); ?>;
    const i18n    = {
        confirmDelete : <?php echo wp_json_encode( __( '¿Eliminar esta respuesta? La acción no se puede deshacer.', 'aura-suite' ) ); ?>,
        confirmBulk   : <?php echo wp_json_encode( __( '¿Aplicar esta acción a las respuestas seleccionadas?', 'aura-suite' ) ); ?>,
        errorLoad     : <?php echo wp_json_encode( __( 'Error al cargar la respuesta.', 'aura-suite' ) ); ?>,
        noSelection   : <?php echo wp_json_encode( __( 'Selecciona al menos una respuesta.', 'aura-suite' ) ); ?>,
        noAction      : <?php echo wp_json_encode( __( 'Selecciona una acción.', 'aura-suite' ) ); ?>,
        selected      : <?php echo wp_json_encode( __( 'seleccionada(s)', 'aura-suite' ) ); ?>,
    };

    const statusLabels = {
        received : <?php echo wp_json_encode( __( 'Recibida',  'aura-suite' ) ); ?>,
        reviewed : <?php echo wp_json_encode( __( 'Revisada',  'aura-suite' ) ); ?>,
        spam     : <?php echo wp_json_encode( __( 'Spam',      'aura-suite' ) ); ?>,
    };

    const $overlay = $( '#aura-sub-modal-overlay' );
    const $body    = $( '#aura-sub-modal-body' );

    // ── Ver detalle (modal) ─────────────────────────────────────
    $( document ).on( 'click', '.aura-view-sub', function ( e ) {
        e.preventDefault();
        const subId = $( this ).data( 'id' );
        openModal( subId );
    } );

    function openModal( subId ) {
        $body.html( '<p class="aura-modal-loading">' + <?php echo wp_json_encode( __( 'Cargando…', 'aura-suite' ) ); ?> + '</p>' );
        $overlay.show();
        $( 'body' ).addClass( 'modal-open' );

        $.post( ajaxUrl, {
            action  : 'aura_forms_get_submission',
            sub_id  : subId,
            nonce   : nonce,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                $body.html( buildDetailHtml( res.data ) );
            } else {
                $body.html( '<p style="color:#d63638">' + ( res.data || i18n.errorLoad ) + '</p>' );
            }
        } )
        .fail( function () {
            $body.html( '<p style="color:#d63638">' + i18n.errorLoad + '</p>' );
        } );
    }

    function buildDetailHtml( data ) {
        const sub    = data.sub;
        const fields = data.fields;

        const statusLabel = statusLabels[ sub.status ] || sub.status;

        let html = '<div class="aura-sub-meta">';
        html += '<div><span>' + <?php echo wp_json_encode( __( 'ID:',     'aura-suite' ) ); ?> + ' </span><strong>#' + esc( sub.id ) + '</strong></div>';
        if ( sub.submitted_name  ) html += '<div><span>' + <?php echo wp_json_encode( __( 'Nombre:', 'aura-suite' ) ); ?> + ' </span><strong>' + esc( sub.submitted_name ) + '</strong></div>';
        if ( sub.submitted_email ) html += '<div><span>' + <?php echo wp_json_encode( __( 'Email:', 'aura-suite' ) ); ?> + ' </span><strong>' + esc( sub.submitted_email ) + '</strong></div>';
        html += '<div><span>' + <?php echo wp_json_encode( __( 'Estado:', 'aura-suite' ) ); ?> + ' </span><strong>' + esc( statusLabel ) + '</strong></div>';
        html += '<div><span>' + <?php echo wp_json_encode( __( 'Fecha:', 'aura-suite' ) ); ?> + ' </span><strong>' + esc( sub.submitted_at ) + '</strong></div>';
        if ( sub.ip_address )  html += '<div><span>IP: </span><strong>' + esc( sub.ip_address ) + '</strong></div>';
        html += '</div>';

        // Campos de respuesta
        if ( fields && fields.length ) {
            html += '<div class="aura-field-list">';
            fields.forEach( function ( f ) {
                html += '<div class="aura-field-row">';
                html += '<div class="aura-field-label">' + esc( f.label ) + '</div>';
                if ( f.value !== null && f.value !== undefined && f.value !== '' ) {
                    html += '<div class="aura-field-value">' + esc( String( f.value ) ) + '</div>';
                } else {
                    html += '<div class="aura-field-value aura-field-empty">' + <?php echo wp_json_encode( __( '(sin respuesta)', 'aura-suite' ) ); ?> + '</div>';
                }
                html += '</div>';
            } );
            html += '</div>';
        } else {
            html += '<p style="color:#888">' + <?php echo wp_json_encode( __( 'No hay campos de respuesta disponibles.', 'aura-suite' ) ); ?> + '</p>';
        }

        return html;
    }

    function esc( str ) {
        return $( '<span>' ).text( String( str ) ).html();
    }

    // ── Cerrar modal ────────────────────────────────────────────
    function closeModal() {
        $overlay.hide();
        $( 'body' ).removeClass( 'modal-open' );
    }
    $( '#aura-sub-modal-close, #aura-sub-modal-close-btn' ).on( 'click', closeModal );
    $overlay.on( 'click', function ( e ) {
        if ( $( e.target ).is( $overlay ) ) closeModal();
    } );
    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) closeModal();
    } );

    // ── Eliminar individual ─────────────────────────────────────
    $( document ).on( 'click', '.aura-delete-sub', function () {
        if ( ! window.confirm( i18n.confirmDelete ) ) return;
        const $btn  = $( this );
        const subId = $btn.data( 'id' );
        $btn.prop( 'disabled', true );

        $.post( ajaxUrl, { action: 'aura_forms_delete_submission', sub_id: subId, nonce: nonce } )
            .done( function ( res ) {
                if ( res.success ) {
                    $( '#sub-row-' + subId ).fadeOut( 300, function () { $( this ).remove(); } );
                } else {
                    alert( res.data && res.data.message ? res.data.message : 'Error' );
                    $btn.prop( 'disabled', false );
                }
            } )
            .fail( function () {
                alert( 'Error de red.' );
                $btn.prop( 'disabled', false );
            } );
    } );

    // ── Checkbox master ────────────────────────────────────────
    $( '#cb-select-all' ).on( 'change', function () {
        $( '.cb-sub' ).prop( 'checked', this.checked );
        updateSelectedCount();
    } );
    $( document ).on( 'change', '.cb-sub', updateSelectedCount );

    function updateSelectedCount() {
        const cnt = $( '.cb-sub:checked' ).length;
        $( '#aura-selected-count' ).text( cnt ? cnt + ' ' + i18n.selected : '' );
    }

    // ── Acciones masivas ────────────────────────────────────────
    $( '#aura-bulk-apply' ).on( 'click', function () {
        const action = $( '#aura-bulk-action' ).val();
        if ( ! action ) { alert( i18n.noAction ); return; }

        const ids = $( '.cb-sub:checked' ).map( function () { return this.value; } ).get();
        if ( ! ids.length ) { alert( i18n.noSelection ); return; }

        if ( ! window.confirm( i18n.confirmBulk ) ) return;

        $( this ).prop( 'disabled', true );

        $.post( ajaxUrl, {
            action      : 'aura_forms_bulk_submissions',
            bulk_action : action,
            ids         : ids,
            nonce       : nonce,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                if ( action === 'delete' ) {
                    ids.forEach( function ( id ) {
                        $( '#sub-row-' + id ).fadeOut( 200, function () { $( this ).remove(); } );
                    } );
                } else {
                    window.location.reload();
                }
            } else {
                alert( res.data && res.data.message ? res.data.message : 'Error' );
            }
            $( '#aura-bulk-apply' ).prop( 'disabled', false );
        } )
        .fail( function () {
            alert( 'Error de red.' );
            $( '#aura-bulk-apply' ).prop( 'disabled', false );
        } );
    } );

} ( jQuery ) );
</script>
