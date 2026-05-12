<?php
/**
 * Template: Caja USD (transicion legacy)
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$balance   = Aura_Financial_USD_Ledger::get_current_balance();
$summary   = Aura_Financial_USD_Ledger::get_transition_summary();
$history   = Aura_Financial_USD_Ledger::get_unified_history( 120 );
$banks_url = admin_url( 'admin.php?page=aura-financial-accounts&account_type=usd_cash&currency=USD' );
?>

<div class="wrap aura-usd-ledger aura-usd-ledger--transition">
    <h1 class="aura-usd-ledger__title">
        <span class="dashicons dashicons-money-alt"></span>
        <?php esc_html_e( 'Caja USD', 'aura-suite' ); ?>
    </h1>

    <div class="notice notice-warning inline">
        <p><strong><?php esc_html_e( 'Modo transición activado.', 'aura-suite' ); ?></strong> <?php esc_html_e( 'Caja USD ya fue absorbida por Bancos. Esta pantalla se conserva temporalmente solo para consulta.', 'aura-suite' ); ?></p>
        <p>
            <a href="<?php echo esc_url( $banks_url ); ?>" class="button button-primary">
                <?php esc_html_e( 'Abrir Bancos filtrado por Caja USD', 'aura-suite' ); ?>
            </a>
        </p>
    </div>

    <div class="aura-usd-cards">
        <div class="aura-usd-card aura-usd-card--balance">
            <div class="aura-usd-card__icon">
                <span class="dashicons dashicons-bank"></span>
            </div>
            <div class="aura-usd-card__body">
                <span class="aura-usd-card__label"><?php esc_html_e( 'Saldo actual en modelo unificado', 'aura-suite' ); ?></span>
                <span class="aura-usd-card__value">
                    $<?php echo esc_html( number_format( $balance, 2 ) ); ?> USD
                </span>
            </div>
        </div>

        <div class="aura-usd-card aura-usd-card--info">
            <div class="aura-usd-card__icon">
                <span class="dashicons dashicons-migrate"></span>
            </div>
            <div class="aura-usd-card__body">
                <span class="aura-usd-card__label"><?php esc_html_e( 'Migración legacy', 'aura-suite' ); ?></span>
                <span class="aura-usd-card__value aura-usd-card__value--sm">
                    <?php
                    echo esc_html(
                        sprintf(
                            __( '%1$d de %2$d registros migrados', 'aura-suite' ),
                            (int) ( $summary['migrated_rows_total'] ?? 0 ),
                            (int) ( $summary['legacy_rows'] ?? 0 )
                        )
                    );
                    ?>
                </span>
                <?php if ( ! empty( $summary['last_run_at'] ) ) : ?>
                    <small><?php echo esc_html( sprintf( __( 'Última ejecución: %s', 'aura-suite' ), $summary['last_run_at'] ) ); ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="aura-usd-panel">
        <h2 class="aura-usd-panel__title">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'Historial migrado de Caja USD', 'aura-suite' ); ?>
        </h2>
        <p class="aura-usd-panel__desc">
            <?php esc_html_e( 'Este historial ya sale del modelo unificado de cuentas y movimientos. No se permiten nuevas conversiones ni depósitos desde esta pantalla legacy.', 'aura-suite' ); ?>
        </p>

        <table class="wp-list-table widefat striped aura-usd-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Movimiento', 'aura-suite' ); ?></th>
                    <th class="num"><?php esc_html_e( 'USD', 'aura-suite' ); ?></th>
                    <th class="num"><?php esc_html_e( 'T/C', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Notas', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Usuario', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $history ) ) : ?>
                    <tr>
                        <td colspan="6" class="aura-usd-loading"><?php esc_html_e( 'Sin movimientos USD migrados todavía.', 'aura-suite' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $history as $row ) : ?>
                        <?php
                        $type_label = $row->movement_type;
                        if ( 'opening' === $row->movement_type ) {
                            $type_label = __( 'Apertura', 'aura-suite' );
                        } elseif ( 'credit' === $row->movement_type ) {
                            $type_label = __( 'Ingreso USD', 'aura-suite' );
                        } elseif ( 'debit' === $row->movement_type ) {
                            $type_label = __( 'Salida / Conversión USD', 'aura-suite' );
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( substr( (string) $row->created_at, 0, 10 ) ); ?></td>
                            <td><?php echo esc_html( $type_label ); ?></td>
                            <td class="num"><?php echo esc_html( number_format( (float) $row->amount, 4 ) ); ?> USD</td>
                            <td class="num"><?php echo null !== $row->exchange_rate ? esc_html( number_format( (float) $row->exchange_rate, 4 ) ) : '—'; ?></td>
                            <td><?php echo esc_html( (string) $row->notes ); ?></td>
                            <td><?php echo esc_html( $row->display_name ? $row->display_name : '—' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
