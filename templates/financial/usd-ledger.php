<?php
/**
 * Template: Caja Chica USD — Ledger
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$balance     = Aura_Financial_USD_Ledger::get_current_balance();
$can_create  = current_user_can( 'aura_finance_create' ) || current_user_can( 'manage_options' );
$is_admin    = current_user_can( 'manage_options' );
$today       = date( 'Y-m-d' );
?>

<div class="wrap aura-usd-ledger">
    <h1 class="aura-usd-ledger__title">
        <span class="dashicons dashicons-money-alt"></span>
        <?php esc_html_e( 'Caja Chica USD', 'aura-suite' ); ?>
    </h1>

    <?php if ( Aura_Financial_USD_Ledger::needs_install() ) :
        Aura_Financial_USD_Ledger::create_table();
    endif; ?>

    <!-- ── TARJETAS DE RESUMEN ─────────────────────────────────────── -->
    <div class="aura-usd-cards">
        <div class="aura-usd-card aura-usd-card--balance">
            <div class="aura-usd-card__icon">
                <span class="dashicons dashicons-bank"></span>
            </div>
            <div class="aura-usd-card__body">
                <span class="aura-usd-card__label"><?php esc_html_e( 'Saldo Disponible', 'aura-suite' ); ?></span>
                <span class="aura-usd-card__value" id="usd-balance-display">
                    $<?php echo number_format( $balance, 2 ); ?> USD
                </span>
            </div>
        </div>
        <div class="aura-usd-card aura-usd-card--info">
            <div class="aura-usd-card__icon">
                <span class="dashicons dashicons-info-outline"></span>
            </div>
            <div class="aura-usd-card__body">
                <span class="aura-usd-card__label"><?php esc_html_e( 'Cada conversión genera', 'aura-suite' ); ?></span>
                <span class="aura-usd-card__value aura-usd-card__value--sm">
                    <?php esc_html_e( 'una transacción de ingreso en MXN', 'aura-suite' ); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="aura-usd-grid">

        <!-- ── PANEL IZQUIERDO: FORMULARIOS ───────────────────────── -->
        <?php if ( $can_create ) : ?>
        <div class="aura-usd-forms">

            <!-- Formulario: Saldo inicial / Depósito -->
            <div class="aura-usd-panel">
                <h2 class="aura-usd-panel__title">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Agregar USD al Saldo', 'aura-suite' ); ?>
                </h2>
                <form id="form-usd-deposit" class="aura-usd-form">
                    <?php wp_nonce_field( 'aura_usd_ledger_nonce', '_wpnonce', false ); ?>

                    <div class="aura-usd-form__row">
                        <label for="deposit-type"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></label>
                        <select id="deposit-type" name="entry_type">
                            <option value="opening"><?php esc_html_e( 'Saldo de apertura (inicio de año)', 'aura-suite' ); ?></option>
                            <option value="deposit" selected><?php esc_html_e( 'Depósito / Ingreso de USD', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <div class="aura-usd-form__row">
                        <label for="deposit-usd"><?php esc_html_e( 'Monto (USD)', 'aura-suite' ); ?></label>
                        <div class="aura-usd-input-group">
                            <span class="aura-usd-input-prefix">$</span>
                            <input type="number" id="deposit-usd" name="usd_amount"
                                   step="0.01" min="0.01" placeholder="0.00" required>
                            <span class="aura-usd-input-suffix">USD</span>
                        </div>
                    </div>

                    <div class="aura-usd-form__row">
                        <label for="deposit-date"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></label>
                        <input type="date" id="deposit-date" name="entry_date"
                               value="<?php echo esc_attr( $today ); ?>">
                    </div>

                    <div class="aura-usd-form__row">
                        <label for="deposit-notes"><?php esc_html_e( 'Notas', 'aura-suite' ); ?></label>
                        <textarea id="deposit-notes" name="notes" rows="2"
                                  placeholder="<?php esc_attr_e( 'Ej: Saldo inicial ENE26', 'aura-suite' ); ?>"></textarea>
                    </div>

                    <button type="submit" class="button button-primary aura-usd-btn-full" id="btn-deposit">
                        <span class="dashicons dashicons-plus"></span>
                        <?php esc_html_e( 'Registrar USD', 'aura-suite' ); ?>
                    </button>
                    <div class="aura-usd-form__feedback" id="deposit-feedback" style="display:none;"></div>
                </form>
            </div>

            <!-- Formulario: Conversión USD → MXN -->
            <div class="aura-usd-panel aura-usd-panel--convert">
                <h2 class="aura-usd-panel__title">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Convertir USD → MXN', 'aura-suite' ); ?>
                </h2>
                <p class="aura-usd-panel__desc">
                    <?php esc_html_e( 'Al guardar se creará automáticamente una transacción de ingreso en el módulo financiero.', 'aura-suite' ); ?>
                </p>
                <form id="form-usd-convert" class="aura-usd-form">
                    <?php wp_nonce_field( 'aura_usd_ledger_nonce', '_wpnonce', false ); ?>

                    <div class="aura-usd-form__row">
                        <label for="conv-usd"><?php esc_html_e( 'Dólares a convertir', 'aura-suite' ); ?></label>
                        <div class="aura-usd-input-group">
                            <span class="aura-usd-input-prefix">$</span>
                            <input type="number" id="conv-usd" name="usd_amount"
                                   step="0.01" min="0.01" placeholder="0.00" required>
                            <span class="aura-usd-input-suffix">USD</span>
                        </div>
                        <small class="aura-usd-available">
                            <?php esc_html_e( 'Disponible:', 'aura-suite' ); ?>
                            <strong id="conv-available">$<?php echo number_format( $balance, 2 ); ?> USD</strong>
                        </small>
                    </div>

                    <div class="aura-usd-form__row">
                        <label for="conv-rate"><?php esc_html_e( 'Tipo de cambio (MXN por 1 USD)', 'aura-suite' ); ?></label>
                        <div class="aura-usd-input-group">
                            <span class="aura-usd-input-prefix">$</span>
                            <input type="number" id="conv-rate" name="exchange_rate"
                                   step="0.0001" min="0.01" placeholder="20.00" required>
                            <span class="aura-usd-input-suffix">MXN/USD</span>
                        </div>
                    </div>

                    <!-- Calculadora en tiempo real -->
                    <div class="aura-usd-calc" id="conv-preview" style="display:none;">
                        <span class="dashicons dashicons-calculator"></span>
                        <strong><?php esc_html_e( 'Recibirás:', 'aura-suite' ); ?></strong>
                        <span id="conv-mxn-preview">$0.00 MXN</span>
                    </div>

                    <div class="aura-usd-form__row">
                        <label for="conv-date"><?php esc_html_e( 'Fecha de conversión', 'aura-suite' ); ?></label>
                        <input type="date" id="conv-date" name="conversion_date"
                               value="<?php echo esc_attr( $today ); ?>">
                    </div>

                    <div class="aura-usd-form__row">
                        <label for="conv-notes"><?php esc_html_e( 'Notas', 'aura-suite' ); ?></label>
                        <textarea id="conv-notes" name="notes" rows="2"
                                  placeholder="<?php esc_attr_e( 'Ej: Cambio en casa de cambio BBVA', 'aura-suite' ); ?>"></textarea>
                    </div>

                    <button type="submit" class="button button-primary aura-usd-btn-full aura-usd-btn-convert" id="btn-convert">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Registrar Conversión', 'aura-suite' ); ?>
                    </button>
                    <div class="aura-usd-form__feedback" id="convert-feedback" style="display:none;"></div>
                </form>
            </div>

        </div><!-- /.aura-usd-forms -->
        <?php endif; ?>

        <!-- ── PANEL DERECHO: HISTORIAL ───────────────────────────── -->
        <div class="aura-usd-history">
            <div class="aura-usd-panel">
                <h2 class="aura-usd-panel__title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Historial de Movimientos', 'aura-suite' ); ?>
                    <button class="button button-small aura-usd-refresh" id="btn-refresh-history" title="<?php esc_attr_e( 'Actualizar', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </h2>

                <div id="usd-history-wrap">
                    <table class="wp-list-table widefat fixed striped aura-usd-table" id="usd-history-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                                <th class="num"><?php esc_html_e( 'USD', 'aura-suite' ); ?></th>
                                <th class="num"><?php esc_html_e( 'T/C', 'aura-suite' ); ?></th>
                                <th class="num"><?php esc_html_e( 'MXN obtenidos', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Notas', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Usuario', 'aura-suite' ); ?></th>
                                <?php if ( $is_admin ) : ?>
                                <th><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="usd-history-body">
                            <tr><td colspan="<?php echo $is_admin ? 8 : 7; ?>" class="aura-usd-loading">
                                <span class="spinner is-active" style="float:none;margin:0 4px;"></span>
                                <?php esc_html_e( 'Cargando historial...', 'aura-suite' ); ?>
                            </td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pie: saldo acumulado -->
                <div class="aura-usd-history-footer">
                    <strong><?php esc_html_e( 'Saldo actual:', 'aura-suite' ); ?></strong>
                    <span id="usd-footer-balance">$<?php echo number_format( $balance, 2 ); ?> USD</span>
                </div>

            </div><!-- /.aura-usd-panel -->
        </div><!-- /.aura-usd-history -->

    </div><!-- /.aura-usd-grid -->
</div><!-- /.wrap -->

<script type="text/javascript">
    // Pasar variable de is_admin al JS
    window.auraUSD = window.auraUSD || {};
    window.auraUSD.isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
</script>
