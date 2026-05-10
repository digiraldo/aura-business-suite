<?php
/**
 * Template — Vista detallada de una respuesta individual
 *
 * Muestra todos los campos de una submission con sus etiquetas,
 * metadatos, y acciones (marcar revisada, eliminar).
 *
 * Accedido vía: admin.php?page=aura-forms-list&action=view-submission&sub_id=X&form_id=Y
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

$sub_id  = absint( $_GET['sub_id']  ?? 0 );
$form_id = absint( $_GET['form_id'] ?? 0 );

if ( ! $sub_id ) {
    wp_die( __( 'ID de respuesta inválido.', 'aura-suite' ) );
}

// ── Cargar submission ──────────────────────────────────────────
$sub = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aura_form_submissions WHERE id = %d",
        $sub_id
    )
);

if ( ! $sub ) {
    wp_die( __( 'Respuesta no encontrada.', 'aura-suite' ) );
}

// Usar form_id de la submission si no viene en GET (por seguridad usamos el de la BD)
$form_id = (int) $sub->form_id;

// ── Cargar formulario ──────────────────────────────────────────
$form = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id, title, type, slug FROM {$wpdb->prefix}aura_forms WHERE id = %d",
        $form_id
    )
);

// ── Cargar etiquetas de campos ────────────────────────────────
$fields = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT field_uid, label, field_type
           FROM {$wpdb->prefix}aura_form_fields
          WHERE form_id = %d
       ORDER BY sort_order ASC",
        $form_id
    )
);

$label_map = [];
foreach ( $fields as $f ) {
    $label_map[ $f->field_uid ] = [ 'label' => $f->label, 'type' => $f->field_type ];
}

// ── Parsear data_json ─────────────────────────────────────────
$data_raw    = json_decode( $sub->data_json, true ) ?: [];
$data_parsed = [];

foreach ( $data_raw as $uid => $value ) {
    if ( str_ends_with( (string) $uid, '_iso_date' ) ) continue;

    $label = isset( $label_map[ $uid ] ) ? $label_map[ $uid ]['label'] : $uid;
    $ftype = $label_map[ $uid ]['type'] ?? 'text';

    // Fecha de nacimiento: dos filas separadas — {label} (fecha) y {label} (edad)
    if ( $ftype === 'birthdate' ) {
        $iso_date = $data_raw[ $uid . '_iso_date' ] ?? '';
        // Fila 1: fecha de nacimiento
        $data_parsed[] = [
            'uid'   => $uid . '_iso_date',
            'label' => $label . ' (fecha)',
            'type'  => 'text',
            'value' => $iso_date ?: '—',
        ];
        // Fila 2: edad calculada
        $data_parsed[] = [
            'uid'   => $uid,
            'label' => $label . ' (edad)',
            'type'  => 'text',
            'value' => $value !== '' && $value !== null ? sprintf( '%d años', (int) $value ) : '—',
        ];
        continue;
    }

    // Terms (agree/disagree): mostrar en español
    if ( $ftype === 'terms' ) {
        $terms_labels = [ 'agree' => 'De acuerdo', 'disagree' => 'En desacuerdo' ];
        $value = $terms_labels[ (string) $value ] ?? $value;
    }

    // Accept only terms: mostrar en español
    if ( $ftype === 'accept_only_terms' ) {
        if ( $value === 'accepted' ) {
            $value = 'Aceptado';
        } elseif ( $value === null || (string) $value === '' ) {
            $value = 'No aceptado';
        }
    }

    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( is_array( $decoded ) ) {
            $value = implode( ', ', array_map( 'sanitize_text_field', $decoded ) );
        }
    }

    $data_parsed[] = [
        'uid'   => $uid,
        'label' => $label,
        'type'  => $ftype,
        'value' => $value,
    ];
}

// ── URLs de navegación ────────────────────────────────────────
$back_url = admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $form_id );

$status_labels = [
    'received' => __( 'Recibida', 'aura-suite' ),
    'reviewed' => __( 'Revisada', 'aura-suite' ),
    'spam'     => __( 'Spam',     'aura-suite' ),
];
$status_classes = [
    'received' => 'aura-badge-info',
    'reviewed' => 'aura-badge-success',
    'spam'     => 'aura-badge-secondary',
];

$can_delete = current_user_can( 'aura_forms_delete' ) || current_user_can( 'manage_options' );

$type_labels = [
    'generic'    => __( 'Genérico',       'aura-suite' ),
    'enrollment' => __( 'Inscripción',    'aura-suite' ),
    'survey'     => __( 'Encuesta',       'aura-suite' ),
    'feedback'   => __( 'Feedback auto.', 'aura-suite' ),
];
?>
<div class="wrap aura-forms-wrap aura-sub-detail-wrap">

    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'Respuesta #', 'aura-suite' ); ?><?php echo absint( $sub->id ); ?>
    </h1>
    <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
        &larr; <?php esc_html_e( 'Volver a respuestas', 'aura-suite' ); ?>
    </a>
    <hr class="wp-header-end">

    <div class="aura-sub-detail-layout">

        <!-- Panel izquierdo: campos de respuesta -->
        <div class="aura-sub-fields-panel">
            <div class="aura-panel-box">
                <h2 class="aura-panel-title"><?php esc_html_e( 'Respuestas del formulario', 'aura-suite' ); ?></h2>
                <?php if ( empty( $data_parsed ) ) : ?>
                    <p class="aura-text-muted"><?php esc_html_e( 'No hay datos de respuesta registrados.', 'aura-suite' ); ?></p>
                <?php else : ?>
                    <dl class="aura-field-dl">
                    <?php foreach ( $data_parsed as $item ) : ?>
                        <dt class="aura-field-dt"><?php echo esc_html( $item['label'] ); ?></dt>
                        <dd class="aura-field-dd">
                        <?php if ( $item['value'] !== null && $item['value'] !== '' ) : ?>
                            <?php if ( $item['type'] === 'textarea' ) : ?>
                                <p style="white-space:pre-wrap;margin:0"><?php echo esc_html( $item['value'] ); ?></p>
                            <?php elseif ( $item['type'] === 'file' ) : ?>
                                <?php
                                    $upload_dir = wp_upload_dir();
                                    $file_url   = $upload_dir['baseurl'] . '/' . ltrim( (string) $item['value'], '/' );
                                ?>
                                <a href="<?php echo esc_url( $file_url ); ?>" target="_blank">
                                    <?php echo esc_html( basename( (string) $item['value'] ) ); ?>
                                </a>
                            <?php elseif ( in_array( $item['type'], [ 'radio', 'select', 'terms', 'accept_only_terms' ], true ) ) : ?>
                                <span class="aura-badge aura-badge-secondary"><?php echo esc_html( $item['value'] ); ?></span>
                            <?php else : ?>
                                <?php echo esc_html( $item['value'] ); ?>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="aura-text-muted"><?php esc_html_e( '(sin respuesta)', 'aura-suite' ); ?></span>
                        <?php endif; ?>
                        </dd>
                    <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel derecho: metadatos y acciones -->
        <div class="aura-sub-meta-panel">

            <!-- Meta: Estado -->
            <div class="aura-panel-box">
                <h3 class="aura-panel-title"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></h3>
                <span class="aura-badge <?php echo esc_attr( $status_classes[ $sub->status ] ?? '' ); ?>">
                    <?php echo esc_html( $status_labels[ $sub->status ] ?? $sub->status ); ?>
                </span>

                <?php if ( $sub->status === 'received' && ( current_user_can( 'aura_forms_view_responses_all' ) || current_user_can( 'manage_options' ) ) ) : ?>
                <button type="button" class="button button-small aura-mark-reviewed-btn" style="margin-top:12px"
                        data-id="<?php echo absint( $sub->id ); ?>">
                    <?php esc_html_e( 'Marcar como revisada', 'aura-suite' ); ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Meta: Información del envío -->
            <div class="aura-panel-box" style="margin-top:12px">
                <h3 class="aura-panel-title"><?php esc_html_e( 'Información del envío', 'aura-suite' ); ?></h3>
                <table class="aura-meta-table">
                    <tr>
                        <th><?php esc_html_e( 'Formulario:', 'aura-suite' ); ?></th>
                        <td>
                            <?php if ( $form ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=edit&id=' . $form_id ) ); ?>">
                                <?php echo esc_html( $form->title ); ?>
                            </a>
                            <?php else : ?>
                                #<?php echo absint( $form_id ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ( $form ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Tipo:', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( $type_labels[ $form->type ] ?? $form->type ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e( 'Fecha:', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i:s', strtotime( $sub->submitted_at ) ) ); ?></td>
                    </tr>
                    <?php if ( $sub->submitted_name ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Nombre:', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( $sub->submitted_name ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $sub->submitted_email ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Email:', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( $sub->submitted_email ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $sub->ip_address ) : ?>
                    <tr>
                        <th>IP:</th>
                        <td><?php echo esc_html( $sub->ip_address ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $sub->wp_user_id ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Usuario WP:', 'aura-suite' ); ?></th>
                        <td>
                            <?php
                            $wp_user = get_userdata( $sub->wp_user_id );
                            echo $wp_user
                                ? '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $sub->wp_user_id ) ) . '">' . esc_html( $wp_user->display_name ) . '</a>'
                                : '#' . absint( $sub->wp_user_id );
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $sub->enrollment_id ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Inscripción:', 'aura-suite' ); ?></th>
                        <td>#<?php echo absint( $sub->enrollment_id ); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Acciones -->
            <?php if ( $can_delete ) : ?>
            <div class="aura-panel-box" style="margin-top:12px">
                <h3 class="aura-panel-title"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></h3>
                <a href="#" class="button button-link-delete aura-sub-detail-delete"
                   data-id="<?php echo absint( $sub->id ); ?>"
                   data-return="<?php echo esc_attr( $back_url ); ?>">
                    <?php esc_html_e( 'Eliminar esta respuesta', 'aura-suite' ); ?>
                </a>
            </div>
            <?php endif; ?>

        </div><!-- .aura-sub-meta-panel -->
    </div><!-- .aura-sub-detail-layout -->

</div><!-- .wrap -->

<style>
.aura-sub-detail-layout {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 16px;
    margin-top: 16px;
}
.aura-panel-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 16px 20px;
}
.aura-panel-title {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #666;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}
.aura-field-dl { margin: 0; }
.aura-field-dt {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: #888;
    margin: 12px 0 2px;
}
.aura-field-dt:first-child { margin-top: 0; }
.aura-field-dd {
    margin: 0;
    font-size: 13px;
    color: #1d2327;
    padding-bottom: 10px;
    border-bottom: 1px solid #f6f6f6;
    word-break: break-word;
}
.aura-field-dd:last-child { border-bottom: none; }
.aura-meta-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.aura-meta-table th {
    text-align: left;
    font-weight: 600;
    color: #666;
    padding: 4px 8px 4px 0;
    vertical-align: top;
    white-space: nowrap;
}
.aura-meta-table td { padding: 4px 0; color: #1d2327; }
.aura-text-muted { color: #bbb; font-style: italic; }
@media ( max-width: 782px ) {
    .aura-sub-detail-layout { grid-template-columns: 1fr; }
}
</style>

<script>
( function ( $ ) {
    'use strict';

    const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    const nonce   = <?php echo wp_json_encode( wp_create_nonce( 'aura_forms_nonce' ) ); ?>;

    // Marcar como revisada
    $( '.aura-mark-reviewed-btn' ).on( 'click', function () {
        const $btn  = $( this );
        const subId = $btn.data( 'id' );
        $btn.prop( 'disabled', true ).text( '…' );

        $.post( ajaxUrl, {
            action      : 'aura_forms_bulk_submissions',
            bulk_action : 'mark_reviewed',
            ids         : [ subId ],
            nonce       : nonce,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                // Actualizar badge de estado en DOM
                $( '.aura-panel-box .aura-badge' ).first().text( <?php echo wp_json_encode( __( 'Revisada', 'aura-suite' ) ); ?> )
                    .removeClass( 'aura-badge-info' ).addClass( 'aura-badge-success' );
                $btn.remove();
            } else {
                alert( res.data && res.data.message ? res.data.message : 'Error' );
                $btn.prop( 'disabled', false ).text( <?php echo wp_json_encode( __( 'Marcar como revisada', 'aura-suite' ) ); ?> );
            }
        } )
        .fail( function () {
            alert( 'Error de red.' );
            $btn.prop( 'disabled', false ).text( <?php echo wp_json_encode( __( 'Marcar como revisada', 'aura-suite' ) ); ?> );
        } );
    } );

    // Eliminar desde la vista de detalle
    $( '.aura-sub-detail-delete' ).on( 'click', function ( e ) {
        e.preventDefault();
        if ( ! window.confirm( <?php echo wp_json_encode( __( '¿Eliminar esta respuesta? La acción no se puede deshacer.', 'aura-suite' ) ); ?> ) ) return;

        const $btn    = $( this );
        const subId   = $btn.data( 'id' );
        const retUrl  = $btn.data( 'return' );

        $btn.text( '…' ).prop( 'disabled', true );

        $.post( ajaxUrl, { action: 'aura_forms_delete_submission', sub_id: subId, nonce: nonce } )
            .done( function ( res ) {
                if ( res.success ) {
                    window.location.href = retUrl;
                } else {
                    alert( res.data && res.data.message ? res.data.message : 'Error' );
                    $btn.prop( 'disabled', false ).text( <?php echo wp_json_encode( __( 'Eliminar esta respuesta', 'aura-suite' ) ); ?> );
                }
            } )
            .fail( function () {
                alert( 'Error de red.' );
                $btn.prop( 'disabled', false ).text( <?php echo wp_json_encode( __( 'Eliminar esta respuesta', 'aura-suite' ) ); ?> );
            } );
    } );

} ( jQuery ) );
</script>

