<?php
/**
 * Template: Portal del Estudiante — Vista de Pagos
 * Incluido desde portal.php al activar la pestaña "Mis Pagos"
 *
 * Variables disponibles (inyectadas desde JS via AJAX):
 *  $student  — objeto del estudiante
 *  $nonce    — nonce de seguridad
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div id="aura-tab-payments" class="aura-portal-tab-content" data-tab="payments" style="display:none;">

    <!-- Resumen de deuda -->
    <div class="aura-pay-summary" id="aura-pay-summary">
        <div class="aura-summary-kpi">
            <span class="kpi-label"><?php esc_html_e( 'Total cancelado', 'aura-suite' ); ?></span>
            <span class="kpi-value kpi-green" id="summary-paid">$0.00</span>
        </div>
        <div class="aura-summary-kpi">
            <span class="kpi-label"><?php esc_html_e( 'Saldo pendiente', 'aura-suite' ); ?></span>
            <span class="kpi-value kpi-red" id="summary-balance">$0.00</span>
        </div>
        <div class="aura-summary-kpi">
            <span class="kpi-label"><?php esc_html_e( 'Costo neto total', 'aura-suite' ); ?></span>
            <span class="kpi-value" id="summary-net">$0.00</span>
        </div>
    </div>

    <!-- Cuotas pendientes -->
    <div id="aura-pending-section" style="display:none;">
        <h4 class="aura-section-sub"><?php esc_html_e( 'Cuotas pendientes', 'aura-suite' ); ?></h4>
        <table class="aura-portal-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Cuota #', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Vence', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Monto', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="aura-pending-tbody">
                <tr><td colspan="5" class="aura-loading"><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Historial de pagos -->
    <h4 class="aura-section-sub"><?php esc_html_e( 'Historial de pagos', 'aura-suite' ); ?></h4>
    <div id="aura-payments-loading" class="aura-loading"><?php esc_html_e( 'Cargando historial…', 'aura-suite' ); ?></div>
    <table class="aura-portal-table" id="aura-payments-table" style="display:none;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Cuota #', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Monto', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Método', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Referencia', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Comprobante', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody id="aura-payments-tbody"></tbody>
    </table>
    <p id="aura-payments-empty" style="display:none;color:#6b7280;">
        <?php esc_html_e( 'No hay pagos registrados aún.', 'aura-suite' ); ?>
    </p>

</div>
