<?php
/**
 * Confirmación post-envío — Módulo de Formularios
 *
 * Variables disponibles al incluir este template:
 *  $form    — objeto de la tabla aura_forms
 *  $message — Mensaje de éxito (kses_post ya aplicado)
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$success_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>';
?>
<div class="aura-form-success" role="status">
    <div class="aura-form-success__icon">
        <?php echo $success_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <?php if ( ! empty( $form->title ) ) : ?>
    <p class="aura-form-success__form-name"><?php echo esc_html( $form->title ); ?></p>
    <?php endif; ?>

    <div class="aura-form-success__message">
        <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — ya kses_post() aplicado en el controlador ?>
    </div>
</div>

