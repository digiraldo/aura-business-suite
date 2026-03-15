<?php
/**
 * Plantilla: Integraciones con Software Contable (Fase 5, Item 5.5)
 *
 * @package Aura_Business_Suite
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
}

// Categorías para exclusión
global $wpdb;
$all_categories = $wpdb->get_results(
    "SELECT id, name, type FROM {$wpdb->prefix}aura_finance_categories WHERE is_active = 1 ORDER BY type, name"
) ?: [];

// Mapeo guardado
$saved_mapping = (array) get_option( 'aura_accounting_mapping', [] );

// RFC empresa
$company_rfc  = get_option( 'aura_company_rfc', '' );
$company_name = get_option( 'aura_company_name', get_bloginfo( 'name' ) );
?>
<div class="wrap aura-integrations-wrap">

    <h1 class="aura-page-title">
        <span class="dashicons dashicons-randomize"></span>
        <?php esc_html_e( 'Integraciones Contables', 'aura-suite' ); ?>
    </h1>
    <p class="aura-page-description">
        <?php esc_html_e( 'Exporta tus transacciones en el formato requerido por tu software contable. Sigue los pasos del asistente para configurar y descargar el archivo.', 'aura-suite' ); ?>
    </p>

    <!-- ===================== WIZARD STEPS ===================== -->
    <div class="aura-wizard-steps" id="auraWizardSteps">
        <div class="aura-step-item active" data-step="1">
            <span class="aura-step-num">1</span>
            <span class="aura-step-label"><?php esc_html_e( 'Software', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-step-connector"></div>
        <div class="aura-step-item" data-step="2">
            <span class="aura-step-num">2</span>
            <span class="aura-step-label"><?php esc_html_e( 'Período y Filtros', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-step-connector"></div>
        <div class="aura-step-item" data-step="3">
            <span class="aura-step-num">3</span>
            <span class="aura-step-label"><?php esc_html_e( 'Mapeo de Cuentas', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-step-connector"></div>
        <div class="aura-step-item" data-step="4">
            <span class="aura-step-num">4</span>
            <span class="aura-step-label"><?php esc_html_e( 'Vista Previa', 'aura-suite' ); ?></span>
        </div>
    </div>

    <!-- ===================== PASO 1: SOFTWARE ===================== -->
    <div class="aura-wizard-panel active" id="auraStep1">
        <h2><?php esc_html_e( 'Paso 1 — Selecciona el software destino', 'aura-suite' ); ?></h2>
        <p><?php esc_html_e( 'Elige el formato de exportación que necesitas para tu sistema contable.', 'aura-suite' ); ?></p>

        <div class="aura-software-grid">

            <label class="aura-software-card" for="sw_quickbooks">
                <input type="radio" id="sw_quickbooks" name="aura_software" value="quickbooks" checked>
                <div class="aura-software-card-inner">
                    <div class="aura-software-icon qb-icon">QB</div>
                    <strong>QuickBooks</strong>
                    <span class="aura-software-ext">.IIF</span>
                    <p><?php esc_html_e( 'Intuit Interchange Format. Compatible con QuickBooks Desktop y Online.', 'aura-suite' ); ?></p>
                </div>
            </label>

            <label class="aura-software-card" for="sw_contabilidad_mx">
                <input type="radio" id="sw_contabilidad_mx" name="aura_software" value="contabilidad_mx">
                <div class="aura-software-card-inner">
                    <div class="aura-software-icon sat-icon">SAT</div>
                    <strong><?php esc_html_e( 'Contabilidad Electrónica MX', 'aura-suite' ); ?></strong>
                    <span class="aura-software-ext">.XML</span>
                    <p><?php esc_html_e( 'Balanza de comprobación en formato SAT México (schema 1.3).', 'aura-suite' ); ?></p>
                </div>
            </label>

            <label class="aura-software-card" for="sw_sap">
                <input type="radio" id="sw_sap" name="aura_software" value="sap">
                <div class="aura-software-card-inner">
                    <div class="aura-software-icon sap-icon">SAP</div>
                    <strong>SAP / ERP</strong>
                    <span class="aura-software-ext">.XML</span>
                    <p><?php esc_html_e( 'Exportación XML estructurada compatible con SAP y otros ERP.', 'aura-suite' ); ?></p>
                </div>
            </label>

            <label class="aura-software-card" for="sw_excel">
                <input type="radio" id="sw_excel" name="aura_software" value="excel">
                <div class="aura-software-card-inner">
                    <div class="aura-software-icon xl-icon">XL</div>
                    <strong><?php esc_html_e( 'Excel Personalizado', 'aura-suite' ); ?></strong>
                    <span class="aura-software-ext">.XLSX</span>
                    <p><?php esc_html_e( 'Hoja de cálculo con columnas configurables y formato contable.', 'aura-suite' ); ?></p>
                </div>
            </label>

        </div>

        <!-- Opciones Excel: selección de columnas -->
        <div class="aura-excel-cols-opts" id="auraExcelColsOpts" style="display:none;">
            <h3><?php esc_html_e( 'Columnas a incluir', 'aura-suite' ); ?></h3>
            <div class="aura-cols-grid">
                <?php
                $cols_avail = [
                    'id'              => 'ID',
                    'fecha'           => 'Fecha',
                    'tipo'            => 'Tipo',
                    'categoria'       => 'Categoría',
                    'cuenta_contable' => 'Cuenta Contable',
                    'monto'           => 'Monto',
                    'descripcion'     => 'Descripción',
                    'estado'          => 'Estado',
                    'metodo_pago'     => 'Método de Pago',
                ];
                foreach ( $cols_avail as $col_key => $col_label ) :
                ?>
                <label class="aura-col-check">
                    <input type="checkbox" name="aura_custom_cols[]" value="<?php echo esc_attr( $col_key ); ?>" checked>
                    <?php echo esc_html( $col_label ); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="aura-wizard-nav">
            <button type="button" class="button button-primary aura-step-next" data-to="2">
                <?php esc_html_e( 'Siguiente →', 'aura-suite' ); ?>
            </button>
        </div>
    </div><!-- /#auraStep1 -->

    <!-- ===================== PASO 2: PERÍODO Y FILTROS ===================== -->
    <div class="aura-wizard-panel" id="auraStep2">
        <h2><?php esc_html_e( 'Paso 2 — Período y filtros', 'aura-suite' ); ?></h2>
        <p><?php esc_html_e( 'Define el rango de fechas y criterios de filtrado para la exportación.', 'aura-suite' ); ?></p>

        <div class="aura-filters-grid">

            <div class="aura-form-row">
                <label for="aura_date_from">
                    <?php esc_html_e( 'Fecha inicio (fiscal)', 'aura-suite' ); ?>
                </label>
                <input type="date" id="aura_date_from" name="aura_date_from"
                       value="<?php echo esc_attr( date( 'Y-01-01' ) ); ?>">
            </div>

            <div class="aura-form-row">
                <label for="aura_date_to">
                    <?php esc_html_e( 'Fecha fin', 'aura-suite' ); ?>
                </label>
                <input type="date" id="aura_date_to" name="aura_date_to"
                       value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>

            <div class="aura-form-row aura-full-row">
                <label class="aura-checkbox-label">
                    <input type="checkbox" id="aura_only_approved" name="aura_only_approved" value="1">
                    <?php esc_html_e( 'Exportar solo transacciones aprobadas', 'aura-suite' ); ?>
                </label>
            </div>

            <?php if ( ! empty( $all_categories ) ) : ?>
            <div class="aura-form-row aura-full-row">
                <label for="aura_excluded_cats">
                    <?php esc_html_e( 'Excluir categorías internas', 'aura-suite' ); ?>
                    <span class="aura-hint"><?php esc_html_e( '(Ctrl+clic para selección múltiple)', 'aura-suite' ); ?></span>
                </label>
                <select id="aura_excluded_cats" name="aura_excluded_cats[]" multiple size="5" class="aura-multiselect">
                    <?php foreach ( $all_categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->id ); ?>">
                            <?php echo esc_html( $cat->name ); ?>
                            (<?php echo esc_html( $cat->type === 'income' ? 'Ingreso' : ( $cat->type === 'expense' ? 'Egreso' : 'Ambos' ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- RFC / Empresa para XML MX -->
            <div class="aura-form-row aura-sat-opts" id="auraSatOpts" style="display:none;">
                <label for="aura_rfc">
                    <?php esc_html_e( 'RFC de la empresa', 'aura-suite' ); ?>
                </label>
                <input type="text" id="aura_rfc" value="<?php echo esc_attr( $company_rfc ); ?>"
                       placeholder="XAXX010101000" maxlength="13"
                       style="text-transform:uppercase">
                <p class="description">
                    <?php esc_html_e( 'Este RFC se incluirá en el XML. Para guardarlo permanentemente, actualiza la Configuración del plugin.', 'aura-suite' ); ?>
                </p>
            </div>

        </div><!-- /.aura-filters-grid -->

        <div class="aura-wizard-nav">
            <button type="button" class="button aura-step-prev" data-to="1">
                ← <?php esc_html_e( 'Anterior', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button button-primary aura-step-next" data-to="3">
                <?php esc_html_e( 'Siguiente →', 'aura-suite' ); ?>
            </button>
        </div>
    </div><!-- /#auraStep2 -->

    <!-- ===================== PASO 3: MAPEO DE CUENTAS ===================== -->
    <div class="aura-wizard-panel" id="auraStep3">
        <h2><?php esc_html_e( 'Paso 3 — Mapeo de cuentas contables', 'aura-suite' ); ?></h2>
        <p><?php esc_html_e( 'Asigna a cada categoría AURA el número o nombre de cuenta contable correspondiente en tu software.', 'aura-suite' ); ?></p>

        <div class="aura-mapping-toolbar">
            <button type="button" class="button" id="auraSaveMapping">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Guardar mapeo para reutilizar', 'aura-suite' ); ?>
            </button>
            <span class="aura-mapping-status" id="auraMappingStatus"></span>
        </div>

        <div id="auraMappingContainer">
            <p class="aura-loading-msg">
                <span class="spinner is-active"></span>
                <?php esc_html_e( 'Cargando categorías…', 'aura-suite' ); ?>
            </p>
        </div>

        <div class="aura-mapping-legend">
            <span class="aura-badge aura-badge-green"><?php esc_html_e( 'Ingreso', 'aura-suite' ); ?></span>
            <span class="aura-badge aura-badge-red"><?php esc_html_e( 'Egreso', 'aura-suite' ); ?></span>
            <span class="aura-badge aura-badge-gray"><?php esc_html_e( 'Ambos', 'aura-suite' ); ?></span>
        </div>

        <div class="aura-wizard-nav">
            <button type="button" class="button aura-step-prev" data-to="2">
                ← <?php esc_html_e( 'Anterior', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button button-primary aura-step-next" data-to="4" id="auraGoToPreview">
                <?php esc_html_e( 'Vista previa →', 'aura-suite' ); ?>
            </button>
        </div>
    </div><!-- /#auraStep3 -->

    <!-- ===================== PASO 4: VISTA PREVIA + DESCARGA ===================== -->
    <div class="aura-wizard-panel" id="auraStep4">
        <h2><?php esc_html_e( 'Paso 4 — Vista previa y descarga', 'aura-suite' ); ?></h2>

        <div class="aura-preview-header">
            <div class="aura-preview-meta" id="auraPreviewMeta"></div>
            <div class="aura-preview-actions">
                <button type="button" class="button button-primary button-hero" id="auraDownloadBtn">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Descargar archivo', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <div class="aura-preview-notice notice notice-info inline" style="margin-bottom:16px;">
            <p>
                <?php esc_html_e( 'Se muestran las primeras 10 transacciones como muestra. El archivo final incluirá todas las transacciones que cumplan los filtros.', 'aura-suite' ); ?>
            </p>
        </div>

        <div id="auraPreviewContainer">
            <p class="aura-loading-msg">
                <span class="spinner is-active"></span>
                <?php esc_html_e( 'Generando vista previa…', 'aura-suite' ); ?>
            </p>
        </div>

        <div class="aura-download-progress" id="auraDownloadProgress" style="display:none;">
            <span class="spinner is-active"></span>
            <?php esc_html_e( 'Generando archivo, por favor espera…', 'aura-suite' ); ?>
        </div>

        <div class="aura-wizard-nav">
            <button type="button" class="button aura-step-prev" data-to="3">
                ← <?php esc_html_e( 'Volver al mapeo', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button" id="auraNewExport">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Nueva exportación', 'aura-suite' ); ?>
            </button>
        </div>
    </div><!-- /#auraStep4 -->

</div><!-- /.wrap.aura-integrations-wrap -->

<?php
// Pasar configuración a JS
$ajax_nonce = wp_create_nonce( Aura_Financial_Integrations::NONCE );
?>
<script>
var auraIntegrationsConfig = <?php echo wp_json_encode( [
    'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
    'nonce'        => $ajax_nonce,
    'savedMapping' => (object) $saved_mapping,
    'i18n'         => [
        'loading'       => __( 'Cargando…', 'aura-suite' ),
        'saving'        => __( 'Guardando…', 'aura-suite' ),
        'saved'         => __( 'Mapeo guardado ✓', 'aura-suite' ),
        'error'         => __( 'Error al procesar la solicitud.', 'aura-suite' ),
        'downloading'   => __( 'Generando archivo…', 'aura-suite' ),
        'noData'        => __( 'No hay transacciones para el período seleccionado.', 'aura-suite' ),
        'income'        => __( 'Ingreso', 'aura-suite' ),
        'expense'       => __( 'Egreso', 'aura-suite' ),
        'both'          => __( 'Ambos', 'aura-suite' ),
        'accountPlaceholder' => __( 'Ej. 4010', 'aura-suite' ),
    ],
    'softwareLabels' => [
        'quickbooks'      => 'QuickBooks (.IIF)',
        'contabilidad_mx' => 'Contabilidad MX (.XML)',
        'sap'             => 'SAP / ERP (.XML)',
        'excel'           => 'Excel (.XLSX)',
    ],
] ); ?>;
</script>
