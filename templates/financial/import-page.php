<?php
/**
 * Vista: Importador Financiero (Wizard 4 pasos)
 * Modernización v2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Sin permisos para importar información financiera', 'aura-suite' ) );
}
?>
<div class="wrap aura-import-wrap">

    <h1 class="aura-import-title">
        <span class="dashicons dashicons-upload"></span>
        <?php esc_html_e( 'Importador Financiero', 'aura-suite' ); ?>
        <a href="#"
           class="button button-secondary aura-template-btn" id="aura-download-template">
            <span class="dashicons dashicons-download"></span>
            <?php esc_html_e( 'Descargar plantilla', 'aura-suite' ); ?>
        </a>
        <button type="button" class="button button-secondary aura-template-btn" onclick="openAuraBundleModal('import')" style="margin-left: 8px; border-color: #7c3aed; color: #7c3aed;">
            <span class="dashicons dashicons-database-export" style="color: #7c3aed;"></span>
            <strong><?php esc_html_e( 'Portabilidad Multimedia (ZIP)', 'aura-suite' ); ?></strong>
        </button>
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

<?php
$req_import_type = sanitize_key( $_GET['type'] ?? 'transactions' );
if ( ! in_array( $req_import_type, [ 'transactions', 'accounts', 'categories', 'areas', 'third_parties' ], true ) ) {
    $req_import_type = 'transactions';
}
?>
            <div class="aura-import-type-select" style="margin-bottom:16px;max-width:380px;">
                <label for="aura-import-type" style="display:block;font-weight:600;margin-bottom:6px;">
                    <?php esc_html_e( 'Tipo de importación', 'aura-suite' ); ?>
                </label>
                <select id="aura-import-type" class="regular-text" style="width:100%;">
                    <option value="transactions" <?php selected( $req_import_type, 'transactions' ); ?>><?php esc_html_e( 'Transacciones', 'aura-suite' ); ?></option>
                    <option value="accounts" <?php selected( $req_import_type, 'accounts' ); ?>><?php esc_html_e( 'Cuentas bancarias', 'aura-suite' ); ?></option>
                    <option value="categories" <?php selected( $req_import_type, 'categories' ); ?>><?php esc_html_e( 'Categorías (padre e hijas)', 'aura-suite' ); ?></option>
                    <option value="areas" <?php selected( $req_import_type, 'areas' ); ?>><?php esc_html_e( 'Áreas y Programas', 'aura-suite' ); ?></option>
                    <option value="third_parties" <?php selected( $req_import_type, 'third_parties' ); ?>><?php esc_html_e( 'Directorio de Terceros y Entidades', 'aura-suite' ); ?></option>
                </select>
                <p class="description" id="aura-import-type-help" style="margin-top:6px;">
                    <?php esc_html_e( 'Selecciona el tipo para descargar la plantilla correcta y validar estructura automáticamente.', 'aura-suite' ); ?>
                </p>
            </div>

            <div class="aura-dropzone" id="aura-dropzone">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <p><?php esc_html_e( 'Arrastra tu archivo aquí o haz clic para seleccionar', 'aura-suite' ); ?></p>
                <p class="aura-dropzone-hint">
                    <?php esc_html_e( 'Formatos: CSV, Excel (.xlsx), ZIP (con comprobantes) · Máximo 50 MB · Hasta 1,000 registros', 'aura-suite' ); ?>
                </p>
                <input type="file" id="aura-import-file" accept=".csv,.xlsx,.zip" style="display:none">
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
            <h2><?php esc_html_e( 'Paso 2: Mapear columnas del archivo a campos de Aura', 'aura-suite' ); ?></h2>

            <div class="aura-file-summary" id="aura-file-summary"></div>

            <!-- Vista previa desplegable o compacta -->
            <div class="aura-preview-section" style="margin-bottom:20px;">
                <h3 style="display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e( 'Vista previa del archivo (primeras 5 filas)', 'aura-suite' ); ?>
                </h3>
                <div class="aura-preview-table-wrap">
                    <table class="aura-preview-table widefat" id="aura-preview-table">
                        <thead id="aura-preview-head"></thead>
                        <tbody id="aura-preview-body"></tbody>
                    </table>
                </div>
            </div>

            <!-- Cabecera de Mapeo y Acciones -->
            <div class="aura-mapping-header-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <h3 style="margin:0; font-size:15px; color:#1d2327;">
                            <span class="dashicons dashicons-randomize" style="color:#2271b1; vertical-align:middle; margin-right:4px;"></span>
                            <?php esc_html_e( 'Asignar columnas del archivo a campos del sistema', 'aura-suite' ); ?>
                        </h3>
                        <p class="description" style="margin:4px 0 0;">
                            <?php esc_html_e( 'Revise cada campo del sistema y seleccione la columna correspondiente de su archivo. Puede ver la muestra en vivo de los datos.', 'aura-suite' ); ?>
                        </p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="button button-small" id="aura-reset-mapping-btn" title="<?php esc_attr_e( 'Volver a aplicar la detección automática', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-image-rotate" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                            <?php esc_html_e( 'Re-detectar automático', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="button button-small" id="aura-clear-mapping-btn" title="<?php esc_attr_e( 'Limpiar todas las asignaciones', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-dismiss" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                            <?php esc_html_e( 'Limpiar asignaciones', 'aura-suite' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Barra de estado de mapeo en tiempo real -->
            <div class="aura-mapping-status-bar" id="aura-mapping-status-bar">
                <div id="aura-mapping-status-text">
                    <span class="dashicons dashicons-info" style="vertical-align:middle;"></span>
                    <span id="aura-mapping-count-summary">Calculando campos asignados...</span>
                </div>
            </div>

            <!-- Contenedor de grupos de mapeo -->
            <div class="aura-mapping-container" id="aura-mapping-grid"></div>

            <div class="aura-import-actions" style="margin-top:25px;">
                <button type="button" class="button" id="aura-back-1">
                    ← <?php esc_html_e( 'Volver a cargar archivo', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-primary" id="aura-validate-btn">
                    <?php esc_html_e( 'Validar y Continuar', 'aura-suite' ); ?> →
                </button>
            </div>

            <div class="aura-import-error" id="aura-step2-error" style="display:none"></div>
        </div>
    </div>

    <!-- ==================== PASO 3: Resultados validación ==================== -->
    <div class="aura-wizard-panel" id="aura-step-3">
        <div class="aura-import-card">
            <h2><?php esc_html_e( 'Paso 3: Análisis y Asignación de Datos', 'aura-suite' ); ?></h2>

            <div class="aura-validation-stats" id="aura-validation-stats"></div>

            <!-- SECCIÓN: Análisis y Selección de Categorías -->
            <div id="aura-category-matching-section" class="aura-import-subpanel" style="display:none; margin-top:20px;">
                <div class="aura-subpanel-header">
                    <span class="dashicons dashicons-category"></span>
                    <h3><?php esc_html_e( 'Comparar y Seleccionar Categorías', 'aura-suite' ); ?></h3>
                </div>
                <p class="description">
                    <?php esc_html_e( 'Revise las categorías encontradas en el archivo. Puede asociarlas a una categoría existente o crearlas automáticamente en la base de datos.', 'aura-suite' ); ?>
                </p>
                <div class="aura-table-responsive">
                    <table class="widefat striped aura-matching-table" id="aura-category-matching-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Categoría en Archivo', 'aura-suite' ); ?></th>
                                <th style="width:100px;"><?php esc_html_e( 'Registros', 'aura-suite' ); ?></th>
                                <th style="width:140px;"><?php esc_html_e( 'Estado en BD', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Acción / Categoría en Sistema', 'aura-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- SECCIÓN: Análisis y Selección de Usuarios WordPress -->
            <div id="aura-user-matching-section" class="aura-import-subpanel" style="display:none; margin-top:25px;">
                <div class="aura-subpanel-header">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h3><?php esc_html_e( 'Asignar Usuarios de WordPress (Creado por / Aprobado por)', 'aura-suite' ); ?></h3>
                </div>
                <p class="description">
                    <?php esc_html_e( 'Seleccione el usuario de WordPress con permisos de Aura para cada nombre detectado en el archivo.', 'aura-suite' ); ?>
                </p>
                <div class="aura-table-responsive">
                    <table class="widefat striped aura-matching-table" id="aura-user-matching-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Nombre en Archivo', 'aura-suite' ); ?></th>
                                <th style="width:140px;"><?php esc_html_e( 'Campo', 'aura-suite' ); ?></th>
                                <th style="width:90px;"><?php esc_html_e( 'Registros', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Usuario WP con Permisos Aura', 'aura-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Selectores por Defecto de Usuario -->
            <div id="aura-default-users-section" class="aura-import-subpanel" style="display:none; margin-top:20px;">
                <div class="aura-default-users-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div>
                        <label for="aura-default-created-by"><strong><?php esc_html_e( 'Usuario "Creado por" por defecto (fallback):', 'aura-suite' ); ?></strong></label>
                        <select id="aura-default-created-by" class="regular-text aura-user-select" style="width:100%; margin-top:5px;"></select>
                    </div>
                    <div>
                        <label for="aura-default-approved-by"><strong><?php esc_html_e( 'Usuario "Aprobador" por defecto (si se aprueba):', 'aura-suite' ); ?></strong></label>
                        <select id="aura-default-approved-by" class="regular-text aura-user-select" style="width:100%; margin-top:5px;"></select>
                    </div>
                </div>
            </div>

            <!-- Errores -->
            <div id="aura-errors-section" style="display:none; margin-top:20px;">
                <h3><?php esc_html_e( 'Filas con errores', 'aura-suite' ); ?></h3>
                <div class="aura-errors-list" id="aura-errors-list"></div>
            </div>

            <!-- Advertencias -->
            <div id="aura-warnings-section" style="display:none; margin-top:20px;">
                <h3><?php esc_html_e( 'Advertencias', 'aura-suite' ); ?></h3>
                <div class="aura-warnings-list" id="aura-warnings-list"></div>
            </div>

            <div class="aura-import-actions" style="margin-top:25px;">
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
                    <span id="aura-execute-label"><?php esc_html_e( 'Importar registros', 'aura-suite' ); ?></span>
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
                    <span class="aura-view-label"><?php esc_html_e( 'Ver transacciones importadas', 'aura-suite' ); ?></span>
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

<?php
// Modal de Migración y Portabilidad Multimedia (ZIP Bundle)
include AURA_PLUGIN_DIR . 'templates/common/portable-bundle-modal.php';
?>
