<?php
/**
 * Template — Listado de formularios
 *
 * Muestra todos los formularios con filtros por tipo, estado y búsqueda.
 * Acciones por fila: Editar, Duplicar, Copiar URL, Eliminar.
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

// ── Modo edición: si viene ?action=edit&id=X cargamos el builder ──────────
if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && ! empty( $_GET['id'] ) ) {
    include AURA_PLUGIN_DIR . 'templates/forms/builder.php';
    return;
}
// ── Modo respuestas: si viene ?action=responses&id=X listamos submissions ──
 if ( isset( $_GET['action'] ) && $_GET['action'] === 'responses' && ! empty( $_GET['id'] ) ) {
    include AURA_PLUGIN_DIR . 'templates/forms/submissions-list.php';
    return;
}

// ── Modo detalle: si viene ?action=view-submission&sub_id=X ────────────
if ( isset( $_GET['action'] ) && $_GET['action'] === 'view-submission' && ! empty( $_GET['sub_id'] ) ) {
    include AURA_PLUGIN_DIR . 'templates/forms/submission-detail.php';
    return;
}
global $wpdb;

// Filtros GET
$filter_type   = isset( $_GET['type'] )   ? sanitize_key( $_GET['type'] )     : '';
$filter_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] )   : '';
$filter_search = isset( $_GET['s'] )      ? sanitize_text_field( $_GET['s'] ) : '';

// Construir query
$where  = [ 'f.deleted_at IS NULL' ];
$params = [];

if ( $filter_type && in_array( $filter_type, [ 'generic', 'enrollment', 'survey', 'feedback' ], true ) ) {
    $where[]  = 'f.type = %s';
    $params[] = $filter_type;
}
if ( $filter_status === 'active' ) {
    $where[] = 'f.is_active = 1';
} elseif ( $filter_status === 'inactive' ) {
    $where[] = 'f.is_active = 0';
}
if ( $filter_search !== '' ) {
    $where[]  = 'f.title LIKE %s';
    $params[] = '%' . $wpdb->esc_like( $filter_search ) . '%';
}

$where_sql = implode( ' AND ', $where );

$sql = "SELECT
            f.*,
            (SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions s WHERE s.form_id = f.id) AS total_responses,
            (SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_fields ff WHERE ff.form_id = f.id) AS total_fields
        FROM {$wpdb->prefix}aura_forms f
        WHERE {$where_sql}
        ORDER BY f.created_at DESC";

if ( ! empty( $params ) ) {
    $forms = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
} else {
    $forms = $wpdb->get_results( $sql );
}

$new_form_url = admin_url( 'admin.php?page=aura-forms-new' );

$type_labels = [
    'generic'    => [ 'label' => __( 'Genérico',       'aura-suite' ), 'class' => 'type-generic' ],
    'enrollment' => [ 'label' => __( 'Inscripción',    'aura-suite' ), 'class' => 'type-enrollment' ],
    'survey'     => [ 'label' => __( 'Encuesta',       'aura-suite' ), 'class' => 'type-survey' ],
    'feedback'   => [ 'label' => __( 'Feedback auto.', 'aura-suite' ), 'class' => 'type-feedback' ],
];
?>
<div class="wrap aura-forms-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Todos los Formularios', 'aura-suite' ); ?></h1>
    <a href="<?php echo esc_url( $new_form_url ); ?>" class="page-title-action">
        <?php esc_html_e( '+ Nuevo Formulario', 'aura-suite' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['notice'] ) ) :
        $notice_map = [
            'deleted'    => [ 'success', __( 'Formulario eliminado.', 'aura-suite' ) ],
            'duplicated' => [ 'success', __( 'Formulario duplicado correctamente.', 'aura-suite' ) ],
        ];
        $key = sanitize_key( $_GET['notice'] );
        if ( isset( $notice_map[ $key ] ) ) :
            [ $notice_type, $notice_msg ] = $notice_map[ $key ]; ?>
        <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
            <p><?php echo esc_html( $notice_msg ); ?></p>
        </div>
    <?php endif; endif; ?>

    <!-- Filtros -->
    <form method="get" class="aura-forms-filters">
        <input type="hidden" name="page" value="aura-forms-list">
        <select name="type">
            <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
            <?php foreach ( $type_labels as $val => $info ) : ?>
                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_type, $val ); ?>>
                    <?php echo esc_html( $info['label'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="active"   <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Activo', 'aura-suite' ); ?></option>
            <option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>><?php esc_html_e( 'Inactivo', 'aura-suite' ); ?></option>
        </select>
        <input type="search" name="s" value="<?php echo esc_attr( $filter_search ); ?>"
               placeholder="<?php esc_attr_e( 'Buscar por título…', 'aura-suite' ); ?>">
        <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'aura-suite' ); ?></button>
        <?php if ( $filter_type || $filter_status || $filter_search ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list' ) ); ?>" class="button">
                <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
            </a>
        <?php endif; ?>
    </form>

    <?php if ( empty( $forms ) ) : ?>
        <div class="aura-notice aura-notice-info">
            <p>
                <?php esc_html_e( 'No se encontraron formularios.', 'aura-suite' ); ?>
                <a href="<?php echo esc_url( $new_form_url ); ?>"><?php esc_html_e( 'Crea el primero', 'aura-suite' ); ?></a>.
            </p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped aura-forms-table">
            <thead>
                <tr>
                    <th class="col-title"><?php esc_html_e( 'Título', 'aura-suite' ); ?></th>
                    <th class="col-type"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                    <th class="col-fields"><?php esc_html_e( 'Campos', 'aura-suite' ); ?></th>
                    <th class="col-responses"><?php esc_html_e( 'Respuestas', 'aura-suite' ); ?></th>
                    <th class="col-status"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                    <th class="col-date"><?php esc_html_e( 'Creado', 'aura-suite' ); ?></th>
                    <th class="col-public-url"><?php esc_html_e( 'URL Pública', 'aura-suite' ); ?></th>
                    <th class="col-actions"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $forms as $form ) :
                    $edit_url  = admin_url( 'admin.php?page=aura-forms-list&action=edit&id=' . $form->id );
                    $type_info = $type_labels[ $form->type ] ?? [ 'label' => esc_html( $form->type ), 'class' => '' ];
                    $form_url  = Aura_Forms_Frontend::get_form_url( $form->slug );
                ?>
                <tr data-form-id="<?php echo esc_attr( $form->id ); ?>">
                    <td class="col-title">
                        <strong>
                            <a href="<?php echo esc_url( $edit_url ); ?>">
                                <?php echo esc_html( $form->title ); ?>
                            </a>
                        </strong>
                        <?php if ( current_user_can( 'aura_forms_export' ) || current_user_can( 'manage_options' ) ) : ?>
                        <div class="aura-forms-export-links">
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=aura_forms_export_csv&form_id=' . $form->id ), 'aura_forms_nonce', 'nonce' ) ); ?>" class="aura-export-link">
                                <?php esc_html_e( 'CSV', 'aura-suite' ); ?>
                            </a>
                            <span class="aura-export-sep">|</span>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=aura_forms_export_excel&form_id=' . $form->id ), 'aura_forms_nonce', 'nonce' ) ); ?>" class="aura-export-link">
                                <?php esc_html_e( 'Excel', 'aura-suite' ); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-type">
                        <span class="aura-badge <?php echo esc_attr( $type_info['class'] ); ?>">
                            <?php echo esc_html( $type_info['label'] ); ?>
                        </span>
                    </td>
                    <td class="col-fields"><?php echo absint( $form->total_fields ); ?></td>
                    <td class="col-responses">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $form->id ) ); ?>">
                            <?php echo absint( $form->total_responses ); ?>
                        </a>
                    </td>
                    <td class="col-status">
                        <?php if ( $form->is_active ) : ?>
                            <span class="aura-badge aura-badge-success"><?php esc_html_e( 'Activo', 'aura-suite' ); ?></span>
                        <?php else : ?>
                            <span class="aura-badge aura-badge-secondary"><?php esc_html_e( 'Inactivo', 'aura-suite' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="col-date">
                        <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $form->created_at ) ) ); ?>
                    </td>                    <td class="col-public-url">
                        <div class="aura-url-actions">
                            <a href="<?php echo esc_url( $form_url ); ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="aura-url-btn aura-url-open-btn"
                               title="<?php esc_attr_e( 'Abrir formulario en nueva pestaña', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Abrir en nueva pestaña', 'aura-suite' ); ?></span>
                            </a>
                            <button type="button" class="aura-url-btn aura-url-copy-btn"
                                    data-url="<?php echo esc_attr( $form_url ); ?>"
                                    title="<?php esc_attr_e( 'Copiar URL del formulario', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Copiar URL', 'aura-suite' ); ?></span>
                            </button>
                            <button type="button" class="aura-url-btn aura-url-qr-btn"
                                    data-url="<?php echo esc_attr( $form_url ); ?>"
                                    data-title="<?php echo esc_attr( $form->title ); ?>"
                                    title="<?php esc_attr_e( 'Generar código QR para compartir', 'aura-suite' ); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect width="5" height="5" x="3" y="3" rx="1"></rect>
                                    <rect width="5" height="5" x="16" y="3" rx="1"></rect>
                                    <rect width="5" height="5" x="3" y="16" rx="1"></rect>
                                    <path d="M21 16h-3a2 2 0 0 0-2 2v3"></path>
                                    <path d="M21 21v.01"></path>
                                    <path d="M12 7v3a2 2 0 0 1-2 2H7"></path>
                                    <path d="M3 12h.01"></path>
                                    <path d="M12 3h.01"></path>
                                    <path d="M12 16v.01"></path>
                                    <path d="M16 12h1"></path>
                                    <path d="M21 12v.01"></path>
                                    <path d="M12 21v-1"></path>
                                </svg>
                                <span class="screen-reader-text"><?php esc_html_e( 'Código QR', 'aura-suite' ); ?></span>
                            </button>
                        </div>
                    </td>                    <td class="col-actions">
                        <div class="aura-row-actions">
                            <!-- Ver Respuestas -->
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $form->id ) ); ?>"
                               class="aura-action-btn aura-action-btn--view"
                               title="<?php esc_attr_e( 'Ver Respuestas', 'aura-suite' ); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span class="screen-reader-text"><?php esc_html_e( 'Ver Respuestas', 'aura-suite' ); ?></span>
                            </a>
                            <!-- Editar -->
                            <a href="<?php echo esc_url( $edit_url ); ?>"
                               class="aura-action-btn aura-action-btn--edit"
                               title="<?php esc_attr_e( 'Editar formulario', 'aura-suite' ); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                <span class="screen-reader-text"><?php esc_html_e( 'Editar', 'aura-suite' ); ?></span>
                            </a>
                            <!-- Duplicar -->
                            <?php if ( current_user_can( 'aura_forms_create' ) || current_user_can( 'manage_options' ) ) : ?>
                            <button type="button"
                                    class="aura-action-btn aura-action-btn--duplicate aura-forms-duplicate"
                                    data-id="<?php echo esc_attr( $form->id ); ?>"
                                    title="<?php esc_attr_e( 'Duplicar formulario', 'aura-suite' ); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                <span class="screen-reader-text"><?php esc_html_e( 'Duplicar', 'aura-suite' ); ?></span>
                            </button>
                            <?php endif; ?>
                            <!-- Eliminar -->
                            <?php if ( current_user_can( 'aura_forms_delete' ) || current_user_can( 'manage_options' ) ) : ?>
                            <button type="button"
                                    class="aura-action-btn aura-action-btn--delete aura-forms-delete"
                                    data-id="<?php echo esc_attr( $form->id ); ?>"
                                    title="<?php esc_attr_e( 'Eliminar formulario', 'aura-suite' ); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                <span class="screen-reader-text"><?php esc_html_e( 'Eliminar', 'aura-suite' ); ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div><!-- .aura-forms-wrap -->

<!-- ── Modal Código QR ──────────────────────────────────────────── -->
<div id="aura-qr-modal" class="aura-qr-modal" role="dialog" aria-modal="true" aria-labelledby="aura-qr-title" style="display:none;">
    <div class="aura-qr-overlay"></div>
    <div class="aura-qr-box">
        <div class="aura-qr-header">
            <h2 id="aura-qr-title"><?php esc_html_e( 'Código QR del Formulario', 'aura-suite' ); ?></h2>
            <button type="button" id="aura-qr-close" class="aura-qr-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="aura-qr-body">
            <p class="aura-qr-form-title"></p>
            <div id="aura-qr-canvas"></div>
            <p class="aura-qr-url"></p>
        </div>
        <div class="aura-qr-footer">
            <button type="button" id="aura-qr-download" class="button button-primary">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Descargar QR (PNG)', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-qr-copy-url" class="button">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'Copiar URL', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>
