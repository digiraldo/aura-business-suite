<?php
/**
 * Portal de formularios del estudiante — [aura_form_portal]
 *
 * Variables disponibles al incluir este template:
 *  $pending_assignments   — array de stdObject con form_title, form_description,
 *                           form_html, course_name, expires_at, assigned_at, id
 *  $completed_assignments — array de stdObject con form_title, completed_at, submission_id
 *  $student_id            — int
 *  $nonce                 — wp_create_nonce('aura_students_frontend_nonce')
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$pending_count   = is_array( $pending_assignments )   ? count( $pending_assignments )   : 0;
$completed_count = is_array( $completed_assignments ) ? count( $completed_assignments ) : 0;
?>
<div class="aura-form-portal" id="aura-form-portal">

    <?php if ( $pending_count === 0 && $completed_count === 0 ) : ?>
        <div class="aura-form-portal__empty">
            <span class="aura-form-portal__empty-icon">✅</span>
            <p><?php esc_html_e( '¡Estás al día! No tienes formularios pendientes.', 'aura-suite' ); ?></p>
        </div>

    <?php else : ?>

        <?php if ( $pending_count > 0 ) : ?>
        <!-- ── Formularios Pendientes ── -->
        <section class="aura-portal-section aura-portal-section--pending">
            <h3 class="aura-portal-section__title">
                <?php esc_html_e( '📋 Formularios Pendientes', 'aura-suite' ); ?>
                <span class="aura-portal-badge"><?php echo (int) $pending_count; ?></span>
            </h3>

            <div class="aura-portal-cards">
                <?php foreach ( $pending_assignments as $assignment ) : ?>
                <div class="aura-portal-card" id="aura-assignment-<?php echo esc_attr( $assignment->id ); ?>">

                    <div class="aura-portal-card__header">
                        <h4 class="aura-portal-card__title"><?php echo esc_html( $assignment->form_title ); ?></h4>
                        <div class="aura-portal-card__meta">
                            <?php if ( ! empty( $assignment->course_name ) ) : ?>
                                <span class="aura-portal-card__course">
                                    📚 <?php echo esc_html( $assignment->course_name ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( ! empty( $assignment->expires_at ) ) : ?>
                                <span class="aura-portal-badge aura-portal-badge--warning">
                                    <?php
                                    printf(
                                        /* translators: %s date in d/m/Y */
                                        esc_html__( 'Expira: %s', 'aura-suite' ),
                                        esc_html( date_i18n( get_option( 'date_format', 'd/m/Y' ), strtotime( $assignment->expires_at ) ) )
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                            <span class="aura-portal-card__assigned-at">
                                <?php
                                printf(
                                    /* translators: %s date */
                                    esc_html__( 'Asignado: %s', 'aura-suite' ),
                                    esc_html( date_i18n( get_option( 'date_format', 'd/m/Y' ), strtotime( $assignment->assigned_at ) ) )
                                );
                                ?>
                            </span>
                        </div>
                    </div>

                    <?php if ( ! empty( $assignment->form_description ) ) : ?>
                        <p class="aura-portal-card__desc"><?php echo esc_html( $assignment->form_description ); ?></p>
                    <?php endif; ?>

                    <div class="aura-portal-card__actions">
                        <button
                            type="button"
                            class="button button-primary aura-open-form-btn"
                            data-assignment-id="<?php echo esc_attr( $assignment->id ); ?>"
                            aria-expanded="false"
                            aria-controls="aura-form-wrap-<?php echo esc_attr( $assignment->id ); ?>"
                        >
                            ✏️ <?php esc_html_e( 'Completar ahora', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <div
                        class="aura-portal-form-wrap"
                        id="aura-form-wrap-<?php echo esc_attr( $assignment->id ); ?>"
                        style="display:none;"
                        data-form-id="<?php echo esc_attr( $assignment->form_id ); ?>"
                    >
                        <?php
                        // form_html ya fue generado y escapado por render_form()
                        if ( ! empty( $assignment->form_html ) ) {
                            echo $assignment->form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        } else {
                            echo '<p class="aura-form-notice aura-form-notice--error">' .
                                 esc_html__( 'Formulario no disponible.', 'aura-suite' ) . '</p>';
                        }
                        ?>
                    </div>

                </div><!-- .aura-portal-card -->
                <?php endforeach; ?>
            </div><!-- .aura-portal-cards -->
        </section>
        <?php endif; // pending ?>

        <?php if ( $completed_count > 0 ) : ?>
        <!-- ── Formularios Completados ── -->
        <section class="aura-portal-section aura-portal-section--completed">
            <h3 class="aura-portal-section__title">
                <?php esc_html_e( '✅ Formularios Completados', 'aura-suite' ); ?>
            </h3>

            <ul class="aura-portal-completed-list">
                <?php foreach ( $completed_assignments as $assignment ) : ?>
                <li class="aura-portal-completed-item">
                    <span class="aura-portal-completed__title">
                        <?php echo esc_html( $assignment->form_title ); ?>
                    </span>
                    <?php if ( ! empty( $assignment->completed_at ) ) : ?>
                        <span class="aura-portal-completed__date">
                            <?php
                            printf(
                                /* translators: %s date */
                                esc_html__( 'Completado el %s', 'aura-suite' ),
                                esc_html( date_i18n( get_option( 'date_format', 'd/m/Y' ), strtotime( $assignment->completed_at ) ) )
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                    <span class="aura-portal-badge aura-portal-badge--success">
                        <?php esc_html_e( '✔ Enviado', 'aura-suite' ); ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; // completed ?>

    <?php endif; ?>

</div><!-- .aura-form-portal -->

<script type="text/javascript">
/* global jQuery */
( function ( $ ) {
    'use strict';

    $( '#aura-form-portal' ).on( 'click', '.aura-open-form-btn', function () {
        var $btn    = $( this );
        var id      = $btn.data( 'assignment-id' );
        var $wrap   = $( '#aura-form-wrap-' + id );
        var isOpen  = $btn.attr( 'aria-expanded' ) === 'true';

        if ( isOpen ) {
            $wrap.slideUp( 250 );
            $btn.attr( 'aria-expanded', 'false' )
                .html( '✏️ <?php echo esc_js( __( 'Completar ahora', 'aura-suite' ) ); ?>' );
        } else {
            $wrap.slideDown( 250 );
            $btn.attr( 'aria-expanded', 'true' )
                .html( '▲ <?php echo esc_js( __( 'Cerrar formulario', 'aura-suite' ) ); ?>' );
        }
    } );

    // Al completar el formulario, ocultar la tarjeta con fade
    $( '#aura-form-portal' ).on( 'aura_form_submitted_success', function ( e, formId ) {
        $( '.aura-portal-form-wrap[data-form-id="' + formId + '"]' )
            .closest( '.aura-portal-card' )
            .fadeOut( 500, function () {
                $( this ).remove();
                // Si ya no quedan pendientes mostrar estado vacío
                if ( $( '.aura-portal-card' ).length === 0 ) {
                    $( '.aura-portal-section--pending' ).remove();
                    if ( $( '.aura-portal-section--completed' ).length === 0 ) {
                        $( '#aura-form-portal' ).html(
                            '<div class="aura-form-portal__empty">'
                            + '<span class="aura-form-portal__empty-icon">✅</span>'
                            + '<p><?php echo esc_js( __( '¡Estás al día! No tienes formularios pendientes.', 'aura-suite' ) ); ?></p>'
                            + '</div>'
                        );
                    }
                }
            } );
    } );

} )( jQuery );
</script>

<?php // ── Estilos del portal de formularios ── ?>
<style>
.aura-form-portal {
    font-family: inherit;
}
.aura-form-portal__empty {
    text-align: center;
    padding: 40px 20px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    color: #166534;
}
.aura-form-portal__empty-icon {
    font-size: 32px;
    display: block;
    margin-bottom: 10px;
}
.aura-portal-section {
    margin-bottom: 32px;
}
.aura-portal-section__title {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.aura-portal-badge {
    background: #e5e7eb;
    color: #374151;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
}
.aura-portal-badge--warning { background: #fef3c7; color: #92400e; }
.aura-portal-badge--success { background: #d1fae5; color: #065f46; }
.aura-portal-cards {
    display: grid;
    gap: 16px;
}
.aura-portal-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.aura-portal-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.aura-portal-card__title {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}
.aura-portal-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 12px;
}
.aura-portal-card__course { color: #2563eb; font-weight: 500; }
.aura-portal-card__assigned-at { color: #9ca3af; }
.aura-portal-card__desc {
    color: #6b7280;
    font-size: 13px;
    margin: 0 0 12px;
}
.aura-portal-card__actions { margin-bottom: 6px; }
.aura-portal-form-wrap {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f0f0f1;
}
/* Completed list */
.aura-portal-completed-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.aura-portal-completed-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.aura-portal-completed__title {
    font-weight: 500;
    flex: 1;
}
.aura-portal-completed__date {
    color: #6b7280;
    font-size: 12px;
}
</style>
