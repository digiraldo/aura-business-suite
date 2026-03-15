<?php
/**
 * Página de Búsqueda Avanzada de Transacciones
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$search_nonce  = wp_create_nonce( 'aura_search_nonce' );
$tags_nonce    = wp_create_nonce( 'aura_tags_nonce' );

// Usuarios para "creado por"
$users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

// Categorías financieras
global $wpdb;
$categories = $wpdb->get_results(
    "SELECT id, name, color, icon FROM {$wpdb->prefix}aura_finance_categories
     WHERE is_active = 1 ORDER BY name ASC"
);
?>
<div class="wrap aura-search-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-search" style="margin-right:6px;"></span>
        <?php esc_html_e( 'Búsqueda Avanzada', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <div class="aura-search-layout">

        <!-- Panel de filtros -->
        <aside class="aura-search-sidebar">

            <!-- Búsquedas guardadas -->
            <div class="aura-search-card" id="saved-searches-panel">
                <h3>
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e( 'Búsquedas Guardadas', 'aura-suite' ); ?>
                </h3>
                <ul id="saved-searches-list">
                    <li><em><?php esc_html_e( 'Cargando...', 'aura-suite' ); ?></em></li>
                </ul>
            </div>

            <!-- Filtros -->
            <div class="aura-search-card">
                <h3><?php esc_html_e( 'Filtrar Resultados', 'aura-suite' ); ?></h3>
                <form id="aura-search-form">

                    <!-- Texto libre -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Búsqueda de texto', 'aura-suite' ); ?>
                            <button type="button" class="aura-syntax-toggle" aria-expanded="false"
                                    aria-controls="aura-syntax-help" title="<?php esc_attr_e( 'Ver operadores de búsqueda', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-editor-help"></span>
                            </button>
                        </label>
                        <input type="text" id="filter-text" name="text"
                               placeholder='<?php echo esc_attr( __( '"frase" -excluir tipo:egreso importe:>500', 'aura-suite' ) ); ?>'>
                        <p class="description"><?php esc_html_e( 'Soporta operadores avanzados: "frase exacta", AND, OR, -excluir, campo:valor', 'aura-suite' ); ?></p>

                        <!-- Panel de ayuda de sintaxis (oculto por defecto) -->
                        <div id="aura-syntax-help" class="aura-syntax-help" hidden>
                            <h4><?php esc_html_e( 'Operadores de búsqueda', 'aura-suite' ); ?></h4>
                            <table class="aura-syntax-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Operador', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Ejemplo', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>"frase exacta"</code></td>
                                        <td><code>"pago proveedor"</code></td>
                                        <td><?php esc_html_e( 'Busca la frase literalmente', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>AND</code></td>
                                        <td><code>factura AND julio</code></td>
                                        <td><?php esc_html_e( 'Ambos términos deben aparecer', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>OR</code></td>
                                        <td><code>renta OR alquiler</code></td>
                                        <td><?php esc_html_e( 'Al menos uno de los términos', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>-excluir</code></td>
                                        <td><code>pago -anticipado</code></td>
                                        <td><?php esc_html_e( 'Excluye registros con ese término', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>tipo:</code></td>
                                        <td><code>tipo:egreso</code> / <code>tipo:ingreso</code></td>
                                        <td><?php esc_html_e( 'Filtra por tipo de transacción', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>estado:</code></td>
                                        <td><code>estado:pendiente</code></td>
                                        <td><?php esc_html_e( 'pendiente, aprobado, rechazado', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>metodo:</code></td>
                                        <td><code>metodo:transferencia</code></td>
                                        <td><?php esc_html_e( 'efectivo, transferencia, tarjeta…', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>importe:</code></td>
                                        <td><code>importe:>500</code> / <code>importe:<=200</code></td>
                                        <td><?php esc_html_e( 'Compara con >, <, >=, <=, =', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>categoria:</code></td>
                                        <td><code>categoria:servicios</code></td>
                                        <td><?php esc_html_e( 'Busca por nombre de categoría', 'aura-suite' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>referencia:</code></td>
                                        <td><code>referencia:INV-2024</code></td>
                                        <td><?php esc_html_e( 'Busca en el número de referencia', 'aura-suite' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="aura-syntax-help__combine"><?php esc_html_e( 'Puedes combinar: ', 'aura-suite' ); ?>
                                <code>tipo:egreso importe:>1000 -anticipo "pago proveedor"</code>
                            </p>
                        </div><!-- #aura-syntax-help -->
                    </div>

                    <!-- Buscar en -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Buscar en:', 'aura-suite' ); ?></label>
                        <div class="aura-checkbox-group">
                            <label><input type="checkbox" name="search_in[]" value="description" checked> <?php esc_html_e( 'Descripción', 'aura-suite' ); ?></label>
                            <label><input type="checkbox" name="search_in[]" value="notes"> <?php esc_html_e( 'Notas', 'aura-suite' ); ?></label>
                            <label><input type="checkbox" name="search_in[]" value="reference_number"> <?php esc_html_e( 'Referencia', 'aura-suite' ); ?></label>
                            <label><input type="checkbox" name="search_in[]" value="recipient_payer"> <?php esc_html_e( 'Beneficiario', 'aura-suite' ); ?></label>
                            <label><input type="checkbox" name="search_in[]" value="tags"> <?php esc_html_e( 'Etiquetas', 'aura-suite' ); ?></label>
                        </div>
                    </div>

                    <!-- Rango de fechas -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Rango de fechas', 'aura-suite' ); ?></label>
                        <div class="aura-date-range">
                            <input type="date" name="date_from" id="filter-date-from" placeholder="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>">
                            <span>—</span>
                            <input type="date" name="date_to" id="filter-date-to" placeholder="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>">
                        </div>
                    </div>

                    <!-- Tipo -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></label>
                        <div class="aura-checkbox-group">
                            <label><input type="checkbox" name="types[]" value="income" checked> <?php esc_html_e( 'Ingreso', 'aura-suite' ); ?></label>
                            <label><input type="checkbox" name="types[]" value="expense" checked> <?php esc_html_e( 'Gasto', 'aura-suite' ); ?></label>
                        </div>
                    </div>

                    <!-- Estado -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Estado', 'aura-suite' ); ?></label>
                        <div class="aura-checkbox-group">
                            <label><input type="checkbox" name="statuses[]" value="pending"> <?php esc_html_e( 'Pendiente', 'aura-suite' ); ?></label>
                            <label><input type="checkbox" name="statuses[]" value="approved"> <?php esc_html_e( 'Aprobado', 'aura-suite' ); ?></label>
                            <label><input type="checkbox" name="statuses[]" value="rejected"> <?php esc_html_e( 'Rechazado', 'aura-suite' ); ?></label>
                        </div>
                    </div>

                    <!-- Categorías -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Categorías', 'aura-suite' ); ?></label>
                        <select name="categories[]" id="filter-categories" multiple size="5" style="width:100%;">
                            <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->id ); ?>">
                                <?php echo esc_html( $cat->name ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Ctrl+clic para selección múltiple', 'aura-suite' ); ?></p>
                    </div>

                    <!-- Rango de monto -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Monto', 'aura-suite' ); ?></label>
                        <div class="aura-amount-range">
                            <input type="number" name="amount_min" placeholder="<?php esc_attr_e( 'Mín', 'aura-suite' ); ?>" min="0" step="0.01">
                            <span>—</span>
                            <input type="number" name="amount_max" placeholder="<?php esc_attr_e( 'Máx', 'aura-suite' ); ?>" min="0" step="0.01">
                        </div>
                    </div>

                    <!-- Método de pago -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Método de pago', 'aura-suite' ); ?></label>
                        <select name="methods[]" id="filter-methods" multiple size="4" style="width:100%;">
                            <option value="cash"><?php esc_html_e( 'Efectivo', 'aura-suite' ); ?></option>
                            <option value="transfer"><?php esc_html_e( 'Transferencia', 'aura-suite' ); ?></option>
                            <option value="card"><?php esc_html_e( 'Tarjeta', 'aura-suite' ); ?></option>
                            <option value="check"><?php esc_html_e( 'Cheque', 'aura-suite' ); ?></option>
                            <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Etiquetas -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Etiquetas', 'aura-suite' ); ?></label>
                        <input type="text" id="filter-tags-input" placeholder="<?php esc_attr_e( 'Añadir etiqueta...', 'aura-suite' ); ?>">
                        <div id="filter-tags-chips" class="aura-chips-container"></div>
                        <input type="hidden" id="filter-tags-hidden" name="tags_json" value="[]">
                    </div>

                    <!-- Creado por -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Creado por', 'aura-suite' ); ?></label>
                        <select name="created_by" id="filter-created-by">
                            <option value=""><?php esc_html_e( '— Todos —', 'aura-suite' ); ?></option>
                            <?php foreach ( $users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>">
                                <?php echo esc_html( $u->display_name ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Comprobante -->
                    <div class="aura-filter-group">
                        <label><?php esc_html_e( 'Comprobante adjunto', 'aura-suite' ); ?></label>
                        <select name="has_receipt">
                            <option value=""><?php esc_html_e( 'Ambos', 'aura-suite' ); ?></option>
                            <option value="yes"><?php esc_html_e( 'Sí', 'aura-suite' ); ?></option>
                            <option value="no"><?php esc_html_e( 'No', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Botones -->
                    <div class="aura-filter-buttons">
                        <button type="submit" id="btn-do-search" class="button button-primary">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Buscar', 'aura-suite' ); ?>
                        </button>
                        <button type="button" id="btn-clear-filters" class="button">
                            <?php esc_html_e( 'Limpiar filtros', 'aura-suite' ); ?>
                        </button>
                        <button type="button" id="btn-save-search" class="button button-secondary">
                            <span class="dashicons dashicons-star-empty"></span>
                            <?php esc_html_e( 'Guardar búsqueda', 'aura-suite' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </aside><!-- /.aura-search-sidebar -->

        <!-- Panel de resultados -->
        <section class="aura-search-results-panel" id="search-results-panel">

            <div id="search-stats" style="display:none;" class="aura-search-stats">
                <span id="search-count"></span>
                <span id="search-total-amount"></span>
                <button id="btn-export-search" class="button button-small">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Exportar resultados', 'aura-suite' ); ?>
                </button>
            </div>

            <div id="search-loading" style="display:none;text-align:center;padding:40px;">
                <span class="spinner is-active" style="float:none;width:40px;height:40px;"></span>
                <p><?php esc_html_e( 'Buscando...', 'aura-suite' ); ?></p>
            </div>

            <div id="search-empty" style="display:none;text-align:center;padding:60px 20px;">
                <span class="dashicons dashicons-search" style="font-size:48px;color:#ccc;"></span>
                <p><?php esc_html_e( 'No se encontraron transacciones con los criterios indicados.', 'aura-suite' ); ?></p>
            </div>

            <div id="search-initial" style="text-align:center;padding:60px 20px;">
                <span class="dashicons dashicons-search" style="font-size:48px;color:#c8d7e1;"></span>
                <p><?php esc_html_e( 'Configura los filtros y presiona "Buscar" para ver resultados.', 'aura-suite' ); ?></p>
            </div>

            <table id="search-results-table" class="wp-list-table widefat fixed striped aura-search-table" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:11%"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                        <th style="width:28%"><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                        <th style="width:16%"><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                        <th style="width:10%;text-align:center;"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                        <th style="width:13%;text-align:right;"><?php esc_html_e( 'Monto', 'aura-suite' ); ?></th>
                        <th style="width:12%;text-align:center;"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                        <th style="width:10%"><?php esc_html_e( 'Etiquetas', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody id="search-results-body"></tbody>
            </table>

            <!-- Paginación -->
            <div id="search-pagination" style="display:none;margin-top:16px;text-align:center;"></div>

        </section><!-- /.aura-search-results-panel -->

    </div><!-- /.aura-search-layout -->

    <!-- Modal guardar búsqueda -->
    <div id="save-search-modal" style="display:none;" class="aura-modal-backdrop">
        <div class="aura-modal-box">
            <h3><?php esc_html_e( 'Guardar búsqueda', 'aura-suite' ); ?></h3>
            <div class="aura-filter-group">
                <label><?php esc_html_e( 'Nombre de la búsqueda', 'aura-suite' ); ?></label>
                <input type="text" id="save-search-name" style="width:100%;"
                       placeholder="<?php esc_attr_e( 'Ej: Gastos Q1 2025', 'aura-suite' ); ?>">
            </div>
            <div style="text-align:right;margin-top:16px;">
                <button id="confirm-save-search" class="button button-primary">
                    <?php esc_html_e( 'Guardar', 'aura-suite' ); ?>
                </button>
                <button id="cancel-save-search" class="button">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    </div>

</div><!-- /.wrap -->

<script>
var auraSearchConfig = {
    searchNonce: '<?php echo esc_js( $search_nonce ); ?>',
    tagsNonce:   '<?php echo esc_js( $tags_nonce ); ?>',
    ajaxUrl:     '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
    currency:    '<?php echo esc_js( get_option( 'aura_currency_symbol', '$' ) ); ?>',
    i18n: {
        results:      '<?php echo esc_js( __( 'resultados', 'aura-suite' ) ); ?>',
        total:        '<?php echo esc_js( __( 'Total:', 'aura-suite' ) ); ?>',
        income:       '<?php echo esc_js( __( 'Ingreso', 'aura-suite' ) ); ?>',
        expense:      '<?php echo esc_js( __( 'Gasto', 'aura-suite' ) ); ?>',
        pending:      '<?php echo esc_js( __( 'Pendiente', 'aura-suite' ) ); ?>',
        approved:     '<?php echo esc_js( __( 'Aprobado', 'aura-suite' ) ); ?>',
        rejected:     '<?php echo esc_js( __( 'Rechazado', 'aura-suite' ) ); ?>',
        page:         '<?php echo esc_js( __( 'Pág.', 'aura-suite' ) ); ?>',
        of:           '<?php echo esc_js( __( 'de', 'aura-suite' ) ); ?>',
        saveSuccess:  '<?php echo esc_js( __( 'Búsqueda guardada correctamente.', 'aura-suite' ) ); ?>',
        deleteSearch: '<?php echo esc_js( __( '¿Eliminar esta búsqueda guardada?', 'aura-suite' ) ); ?>',
        noSaved:      '<?php echo esc_js( __( 'Sin búsquedas guardadas.', 'aura-suite' ) ); ?>',
    }
};
</script>
<script>
/* ── Botón de ayuda de sintaxis ── */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var btn  = document.querySelector('.aura-syntax-toggle');
        var panel = document.getElementById('aura-syntax-help');
        if (!btn || !panel) return;
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!open));
            if (open) {
                panel.hidden = true;
            } else {
                panel.hidden = false;
            }
        });
    });
}());
</script>
