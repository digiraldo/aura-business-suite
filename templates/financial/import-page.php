<?php
/**
 * Vista: Importar Transacciones (Wizard 4 pasos)
 * Fase 4, Item 4.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Sin permisos para importar transacciones', 'aura-suite' ) );
}
?>
<div class="wrap aura-import-wrap">

    <h1 class="aura-import-title">
        <span class="dashicons dashicons-upload"></span>
        <?php esc_html_e( 'Importar Transacciones', 'aura-suite' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=aura_download_import_template&nonce=' . wp_create_nonce( 'aura_import_nonce' ) ) ); ?>"
           class="button button-secondary aura-template-btn" id="aura-download-template">
            <span class="dashicons dashicons-download"></span>
            <?php esc_html_e( 'Descargar plantilla CSV', 'aura-suite' ); ?>
        </a>
    </h1>

    <!-- Indicador de pasos -->
    <div class="aura-wizard-steps">
        <div class="aura-step active" data-step="1">
            <span class="aura-step-num">1</span>
            <span class="aura-step-label"><?php esc_html_e( 'Subir archivo', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-step-line"></div>
        <div class="aura-step" data-step="2">
            <span class="aura-step-num">2</span>
            <span class="aura-step-label"><?php esc_html_e( 'Mapear columnas', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-step-line"></div>
        <div class="aura-step" data-step="3">
            <span class="aura-step-num">3</span>
            <span class="aura-step-label"><?php esc_html_e( 'Validar datos', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-step-line"></div>
        <div class="aura-step" data-step="4">
            <span class="aura-step-num">4</span>
            <span class="aura-step-label"><?php esc_html_e( 'Confirmar e importar', 'aura-suite' ); ?></span>
        </div>
    </div>

    <!-- ==================== PASO 1: Subir archivo ==================== -->
    <div class="aura-wizard-panel active" id="aura-step-1">
        <div class="aura-import-card">
            <h2><?php esc_html_e( 'Paso 1: Seleccionar archivo', 'aura-suite' ); ?></h2>

            <div class="aura-dropzone" id="aura-dropzone">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <p><?php esc_html_e( 'Arrastra tu archivo aquí o haz clic para seleccionar', 'aura-suite' ); ?></p>
                <p class="aura-dropzone-hint">
                    <?php esc_html_e( 'Formatos: CSV, Excel (.xlsx) · Máximo 5 MB · Hasta 1,000 registros', 'aura-suite' ); ?>
                </p>
                <input type="file" id="aura-import-file" accept=".csv,.xlsx" style="display:none">
                <button type="button" class="button button-primary" id="aura-select-file-btn">
                    <?php esc_html_e( 'Seleccionar archivo', 'aura-suite' ); ?>
                </button>
            </div>

            <div class="aura-selected-file" id="aura-selected-file" style="display:none">
                <span class="dashicons dashicons-yes-alt"></span>
                <span id="aura-selected-filename"></span>
                <button type="button" class="button-link aura-remove-file" id="aura-remove-file">✕</button>
            </div>

            <div class="aura-import-actions">
                <button type="button" class="button button-primary button-hero" id="aura-upload-btn" disabled>
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e( 'Subir y analizar', 'aura-suite' ); ?>
                </button>
            </div>

            <div class="aura-import-progress" id="aura-upload-progress" style="display:none">
                <div class="aura-progress-bar"><div class="aura-progress-fill" style="width:0%"></div></div>
                <p><?php esc_html_e( 'Analizando archivo…', 'aura-suite' ); ?></p>
            </div>

            <div class="aura-import-error" id="aura-step1-error" style="display:none"></div>
        </div>
    </div>

    <!-- ==================== PASO 2: Mapear columnas ==================== -->
    <div class="aura-wizard-panel" id="aura-step-2">
        <div class="aura-import-card">
            <h2><?php esc_html_e( 'Paso 2: Mapear columnas', 'aura-suite' ); ?></h2>

            <div class="aura-file-summary" id="aura-file-summary"></div>

            <!-- Vista previa -->
            <h3><?php esc_html_e( 'Vista previa (primeras 5 filas)', 'aura-suite' ); ?></h3>
            <div class="aura-preview-table-wrap">
                <table class="aura-preview-table widefat" id="aura-preview-table">
                    <thead id="aura-preview-head"></thead>
                    <tbody id="aura-preview-body"></tbody>
                </table>
            </div>

            <!-- Mapeo -->
            <h3><?php esc_html_e( 'Asignar columnas del archivo a campos del sistema', 'aura-suite' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'Los campos marcados con * son obligatorios. Se detectó un mapeo automático; revísalo y ajusta si es necesario.', 'aura-suite' ); ?>
            </p>

            <div class="aura-mapping-grid" id="aura-mapping-grid">
                <?php
                $system_fields = [
                    'transaction_date' => [ 'label' => __( 'Fecha *', 'aura-suite' ),          'required' => true ],
                    'transaction_type' => [ 'label' => __( 'Tipo (ingreso/egreso) *', 'aura-suite' ), 'required' => true ],
                    'category_id'      => [ 'label' => __( 'Categoría *', 'aura-suite' ),       'required' => true ],
                    'amount'           => [ 'label' => __( 'Monto *', 'aura-suite' ),            'required' => true ],
                    'description'      => [ 'label' => __( 'Descripción', 'aura-suite' ),        'required' => false ],
                    'notes'            => [ 'label' => __( 'Notas', 'aura-suite' ),              'required' => false ],
                    'payment_method'   => [ 'label' => __( 'Método de pago', 'aura-suite' ),     'required' => false ],
                    'reference_number' => [ 'label' => __( 'N° Referencia', 'aura-suite' ),      'required' => false ],
                ];
                foreach ( $system_fields as $field => $info ) :
                ?>
                <div class="aura-mapping-row">
                    <label class="aura-field-label <?php echo $info['required'] ? 'required' : ''; ?>">
                        <?php echo esc_html( $info['label'] ); ?>
                    </label>
                    <div class="aura-mapping-arrow">→</div>
                    <select class="aura-col-select" data-field="<?php echo esc_attr( $field ); ?>" id="map-<?php echo esc_attr( $field ); ?>">
                        <option value=""><?php esc_html_e( '— Ignorar —', 'aura-suite' ); ?></option>
                        <!-- Opciones se insertan por JS -->
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="aura-import-actions">
                <button type="button" class="button" id="aura-back-1">
                    ← <?php esc_html_e( 'Volver', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-primary" id="aura-validate-btn">
                    <?php esc_html_e( 'Validar datos', 'aura-suite' ); ?> →
                </button>
            </div>

            <div class="aura-import-error" id="aura-step2-error" style="display:none"></div>
        </div>
    </div>

    <!-- ==================== PASO 3: Resultados validación ==================== -->
    <div class="aura-wizard-panel" id="aura-step-3">
        <div class="aura-import-card">
            <h2><?php esc_html_e( 'Paso 3: Resultado de validación', 'aura-suite' ); ?></h2>

            <div class="aura-validation-stats" id="aura-validation-stats"></div>

            <!-- Errores -->
            <div id="aura-errors-section" style="display:none">
                <h3><?php esc_html_e( 'Filas con errores', 'aura-suite' ); ?></h3>
                <div class="aura-errors-list" id="aura-errors-list"></div>
            </div>

            <!-- Advertencias -->
            <div id="aura-warnings-section" style="display:none">
                <h3><?php esc_html_e( 'Advertencias', 'aura-suite' ); ?></h3>
                <div class="aura-warnings-list" id="aura-warnings-list"></div>
            </div>

            <div class="aura-import-actions">
                <button type="button" class="button" id="aura-back-2">
                    ← <?php esc_html_e( 'Volver al mapeo', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-primary" id="aura-confirm-btn">
                    <?php esc_html_e( 'Continuar a Opciones de Importación', 'aura-suite' ); ?> →
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== PASO 4: Confirmar e importar ==================== -->
    <div class="aura-wizard-panel" id="aura-step-4">
        <div class="aura-import-card">
            <h2><?php esc_html_e( 'Paso 4: Opciones de importación', 'aura-suite' ); ?></h2>

            <div class="aura-options-grid">
                <!-- Estado -->
                <div class="aura-option-group">
                    <h4><?php esc_html_e( 'Estado de las transacciones importadas', 'aura-suite' ); ?></h4>
                    <label class="aura-radio-option">
                        <input type="radio" name="default_status" value="pending">
                        <span><?php esc_html_e( 'Pendientes (requieren aprobación)', 'aura-suite' ); ?></span>
                    </label>
                    <label class="aura-radio-option selected">
                        <input type="radio" name="default_status" value="approved" checked>
                        <span><?php esc_html_e( 'Aprobadas automáticamente', 'aura-suite' ); ?></span>
                    </label>
                </div>

                <!-- Categorías -->
                <div class="aura-option-group">
                    <h4><?php esc_html_e( 'Si la categoría no existe', 'aura-suite' ); ?></h4>
                    <label class="aura-radio-option selected">
                        <input type="radio" name="auto_create_category" value="1" checked>
                        <span><?php esc_html_e( 'Crear automáticamente', 'aura-suite' ); ?></span>
                    </label>
                    <label class="aura-radio-option">
                        <input type="radio" name="auto_create_category" value="0">
                        <span><?php esc_html_e( 'Marcar fila como error', 'aura-suite' ); ?></span>
                    </label>
                </div>

                <!-- Duplicados -->
                <div class="aura-option-group">
                    <h4><?php esc_html_e( 'Duplicados (misma fecha + monto + descripción similar)', 'aura-suite' ); ?></h4>
                    <label class="aura-radio-option">
                        <input type="radio" name="duplicate_action" value="ignore">
                        <span><?php esc_html_e( 'Ignorar fila', 'aura-suite' ); ?></span>
                    </label>
                    <label class="aura-radio-option">
                        <input type="radio" name="duplicate_action" value="import">
                        <span><?php esc_html_e( 'Importar como nueva transacción', 'aura-suite' ); ?></span>
                    </label>
                    <label class="aura-radio-option selected">
                        <input type="radio" name="duplicate_action" value="ask" checked>
                        <span><?php esc_html_e( 'Importar (se mostrará aviso)', 'aura-suite' ); ?></span>
                    </label>
                </div>
            </div>

            <div class="aura-import-summary" id="aura-import-summary"></div>

            <!-- Barra de progreso -->
            <div class="aura-import-progress" id="aura-exec-progress" style="display:none">
                <div class="aura-progress-bar">
                    <div class="aura-progress-fill" id="aura-exec-bar" style="width:0%"></div>
                </div>
                <p id="aura-exec-progress-text"><?php esc_html_e( 'Importando…', 'aura-suite' ); ?></p>
            </div>

            <div class="aura-import-error" id="aura-step4-error" style="display:none"></div>

            <div class="aura-import-actions" id="aura-step4-actions">
                <button type="button" class="button" id="aura-back-3">
                    ← <?php esc_html_e( 'Volver a validación', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-primary button-hero" id="aura-execute-btn">
                    <span class="dashicons dashicons-upload"></span>
                    <span id="aura-execute-label"><?php esc_html_e( 'Importar transacciones', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== RESULTADO FINAL ==================== -->
    <div class="aura-wizard-panel" id="aura-step-result">
        <div class="aura-import-card aura-result-card">
            <div class="aura-result-icon" id="aura-result-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <h2 id="aura-result-title"><?php esc_html_e( 'Importación completada', 'aura-suite' ); ?></h2>

            <div class="aura-result-stats" id="aura-result-stats"></div>

            <div class="aura-result-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-transactions' ) ); ?>"
                   class="button button-primary button-hero" id="aura-view-transactions">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Ver transacciones importadas', 'aura-suite' ); ?>
                </a>

                <button type="button" class="button button-secondary" id="aura-download-error-log" style="display:none">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Descargar log de errores', 'aura-suite' ); ?>
                </button>

                <button type="button" class="button" id="aura-rollback-btn" style="display:none">
                    <span class="dashicons dashicons-undo"></span>
                    <?php esc_html_e( 'Deshacer esta importación', 'aura-suite' ); ?>
                </button>

                <button type="button" class="button button-secondary" id="aura-import-another">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e( 'Importar otro archivo', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== HISTORIAL DE IMPORTACIONES ==================== -->
    <div class="aura-import-history-wrap">
        <h2><?php esc_html_e( 'Historial de importaciones recientes', 'aura-suite' ); ?></h2>
        <div id="aura-import-history">
            <p class="aura-loading"><?php esc_html_e( 'Cargando historial…', 'aura-suite' ); ?></p>
        </div>
    </div>

</div><!-- .aura-import-wrap -->
