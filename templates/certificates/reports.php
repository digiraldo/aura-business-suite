<?php
/**
 * Template: Reportes de Certificados
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap aura-certificates-wrap">
    <h1 class="wp-heading-inline">📊 <?php esc_html_e( 'Reportes de Certificados', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <!-- Selector de reporte -->
    <div class="aura-card" style="max-width:700px;margin-bottom:24px;">
        <h2><?php esc_html_e( 'Generar Reporte', 'aura-suite' ); ?></h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end;">
            <label>
                <?php esc_html_e( 'Tipo de Reporte', 'aura-suite' ); ?>
                <select id="aura-cert-report-type" class="aura-input" style="width:100%;margin-top:4px;">
                    <option value="issued_by_period"><?php esc_html_e( 'Emitidos por período', 'aura-suite' ); ?></option>
                    <option value="issued_by_course"><?php esc_html_e( 'Emitidos por curso', 'aura-suite' ); ?></option>
                    <option value="revoked"><?php esc_html_e( 'Certificados revocados', 'aura-suite' ); ?></option>
                    <option value="pending_emit"><?php esc_html_e( 'Pendientes de emitir', 'aura-suite' ); ?></option>
                </select>
            </label>
            <label id="aura-cert-report-date-wrap">
                <?php esc_html_e( 'Período', 'aura-suite' ); ?>
                <div style="display:flex;gap:4px;margin-top:4px;">
                    <input type="date" id="aura-cert-report-from" class="aura-input"
                           placeholder="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>">
                    <input type="date" id="aura-cert-report-to" class="aura-input"
                           placeholder="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>">
                </div>
            </label>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" id="aura-cert-report-generate" class="button button-primary">
                <?php esc_html_e( 'Generar', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cert-report-excel" class="button" disabled>
                <?php esc_html_e( '⬇ Excel', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cert-report-pdf" class="button" disabled>
                <?php esc_html_e( '⬇ PDF', 'aura-suite' ); ?>
            </button>
        </div>
    </div>

    <!-- Resultado del reporte -->
    <div id="aura-cert-report-result" style="display:none;">
        <p id="aura-cert-report-summary" style="color:#555;margin-bottom:8px;"></p>
        <div id="aura-cert-report-table-wrap" style="overflow-x:auto;"></div>
    </div>

    <!-- Estado de carga -->
    <div id="aura-cert-report-loading" style="display:none;color:#555;">
        🔄 <?php esc_html_e( 'Generando reporte…', 'aura-suite' ); ?>
    </div>
</div>
