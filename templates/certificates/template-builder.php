<?php
/**
 * Template: Editor de Plantillas (Fabric.js Canvas Builder)
 *
 * Esta página ocupa toda la pantalla. Los assets de Fabric.js se cargan
 * condicionalmente por class-certificates-admin.php cuando action=edit.
 *
 * @package AuraBusinessSuite
 * @var int $template_id  ID de la plantilla (0 = nueva)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$template_id = absint( $_GET['id'] ?? 0 );
$is_new      = $template_id === 0 || ( $_GET['id'] ?? '' ) === 'new';

// Datos de la plantilla existente
$template_data = null;
if ( ! $is_new && $template_id > 0 ) {
    global $wpdb;
    $table         = $wpdb->prefix . 'aura_certificate_templates';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $template_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $template_id ) );
}

$page_title = $is_new
    ? __( 'Nueva Plantilla', 'aura-suite' )
    : sprintf( __( 'Editar: %s', 'aura-suite' ), $template_data->name ?? '' );
?>
<div class="wrap aura-cert-builder-wrap" style="margin:0;padding:0;max-width:100%;">

    <!-- Barra superior del editor -->
    <div class="aura-cert-builder-topbar">
        <div class="aura-cert-builder-topbar-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-templates' ) ); ?>"
               class="aura-cert-builder-back" title="<?php esc_attr_e( 'Volver al listado', 'aura-suite' ); ?>">
                ← <?php esc_html_e( 'Plantillas', 'aura-suite' ); ?>
            </a>
            <span class="aura-cert-builder-title"><?php echo esc_html( $page_title ); ?></span>
        </div>
        <div class="aura-cert-builder-topbar-center">
            <button type="button" id="aura-cb-undo" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Deshacer (Ctrl+Z)', 'aura-suite' ); ?>">↩ <?php esc_html_e( 'Deshacer', 'aura-suite' ); ?></button>
            <button type="button" id="aura-cb-redo" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Rehacer (Ctrl+Y)', 'aura-suite' ); ?>">↪ <?php esc_html_e( 'Rehacer', 'aura-suite' ); ?></button>
            <span style="width:1px;height:24px;background:rgba(255,255,255,.3);margin:0 4px;"></span>
            <button type="button" id="aura-cb-zoom-out"   class="aura-cb-tool-btn">－</button>
            <span id="aura-cb-zoom-label" style="min-width:46px;text-align:center;font-size:.85rem;">100%</span>
            <button type="button" id="aura-cb-zoom-in"    class="aura-cb-tool-btn">＋</button>
            <button type="button" id="aura-cb-zoom-reset" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Ajustar a pantalla', 'aura-suite' ); ?>">⊡</button>
        </div>
        <div class="aura-cert-builder-topbar-right">
            <button type="button" id="aura-cb-preview" class="button">
                <?php esc_html_e( 'Vista Previa', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cb-save" class="button button-primary">
                <?php esc_html_e( 'Guardar Plantilla', 'aura-suite' ); ?>
            </button>
        </div>
    </div>

    <div class="aura-cert-builder-layout">

        <!-- Panel de herramientas (izquierda) -->
        <div class="aura-cert-builder-tools">
            <h3><?php esc_html_e( 'Elementos', 'aura-suite' ); ?></h3>
            <button type="button" class="aura-cb-elem-btn" data-type="textbox">
                <span>T</span><?php esc_html_e( 'Texto', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-cb-elem-btn" data-type="image">
                <span>🖼</span><?php esc_html_e( 'Imagen', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-cb-elem-btn" data-type="rect">
                <span>▭</span><?php esc_html_e( 'Rectángulo', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-cb-elem-btn" data-type="circle">
                <span>⬤</span><?php esc_html_e( 'Círculo', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-cb-elem-btn" data-type="line">
                <span>—</span><?php esc_html_e( 'Línea', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-cb-elem-btn" data-type="qr">
                <span>🔲</span><?php esc_html_e( 'Código QR', 'aura-suite' ); ?>
            </button>

            <h3 style="margin-top:16px;"><?php esc_html_e( 'Variables', 'aura-suite' ); ?></h3>
            <div id="aura-cb-vars-list" class="aura-cb-vars-list">
                <!-- Poblado por JS desde auraCertBuilder.dynamicVars -->
            </div>

            <h3 style="margin-top:16px;"><?php esc_html_e( 'Diseños Base', 'aura-suite' ); ?></h3>
            <div id="aura-cb-prebuilt-list" class="aura-cb-prebuilt-list">
                <!-- Poblado por JS desde auraCertBuilder.prebuiltDesigns -->
            </div>
        </div>

        <!-- Canvas (centro) -->
        <div class="aura-cert-builder-canvas-wrap">
            <canvas id="aura-cert-canvas"></canvas>
        </div>

        <!-- Panel de propiedades (derecha) -->
        <div class="aura-cert-builder-props">
            <!-- Propiedades del documento -->
            <div id="aura-cb-doc-props" class="aura-cb-props-section">
                <h3><?php esc_html_e( 'Documento', 'aura-suite' ); ?></h3>
                <label><?php esc_html_e( 'Nombre de la plantilla', 'aura-suite' ); ?>
                    <input type="text" id="aura-cb-tmpl-name" class="aura-input" style="width:100%;"
                           value="<?php echo $template_data ? esc_attr( $template_data->name ) : ''; ?>"
                           placeholder="<?php esc_attr_e( 'Ej: Diploma Graduación 2026', 'aura-suite' ); ?>">
                </label>
                <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                    <textarea id="aura-cb-tmpl-desc" class="aura-input" style="width:100%;" rows="2"
                              placeholder="<?php esc_attr_e( 'Descripción opcional', 'aura-suite' ); ?>"><?php echo $template_data ? esc_textarea( $template_data->description ) : ''; ?></textarea>
                </label>
                <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Tamaño y Orientación', 'aura-suite' ); ?>
                    <select id="aura-cb-orientation" class="aura-input" style="width:100%;">
                        <optgroup label="A4 (297 × 210 mm)">
                            <option value="landscape"         <?php selected( $template_data->orientation ?? 'landscape', 'landscape' ); ?>><?php esc_html_e( 'A4 Horizontal', 'aura-suite' ); ?></option>
                            <option value="portrait"          <?php selected( $template_data->orientation ?? '', 'portrait' ); ?>><?php esc_html_e( 'A4 Vertical', 'aura-suite' ); ?></option>
                        </optgroup>
                        <optgroup label="Carta / Letter (279 × 216 mm)">
                            <option value="letter_landscape"  <?php selected( $template_data->orientation ?? '', 'letter_landscape' ); ?>><?php esc_html_e( 'Carta Horizontal', 'aura-suite' ); ?></option>
                            <option value="letter_portrait"   <?php selected( $template_data->orientation ?? '', 'letter_portrait' ); ?>><?php esc_html_e( 'Carta Vertical', 'aura-suite' ); ?></option>
                        </optgroup>
                    </select>
                </label>
                <div id="aura-cb-custom-dims" style="display:none;margin-top:8px;opacity:.5;pointer-events:none;">
                    <label><?php esc_html_e( 'Ancho (mm)', 'aura-suite' ); ?>
                        <input type="number" id="aura-cb-width-mm" class="aura-input" style="width:100%;"
                               value="<?php echo esc_attr( $template_data->width_mm ?? 297 ); ?>" min="50" max="1200">
                    </label>
                    <label style="margin-top:4px;display:block;"><?php esc_html_e( 'Alto (mm)', 'aura-suite' ); ?>
                        <input type="number" id="aura-cb-height-mm" class="aura-input" style="width:100%;"
                               value="<?php echo esc_attr( $template_data->height_mm ?? 210 ); ?>" min="50" max="1200">
                    </label>
                </div>
                <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Color de fondo', 'aura-suite' ); ?>
                    <input type="color" id="aura-cb-bg-color" value="#ffffff" style="width:48px;height:32px;padding:2px;">
                </label>
                <div style="margin-top:8px;">
                    <button type="button" id="aura-cb-bg-image-btn" class="button button-small">
                        <?php esc_html_e( 'Fondo desde imagen', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- Propiedades del objeto seleccionado -->
            <div id="aura-cb-obj-props" class="aura-cb-props-section" style="display:none;">
                <h3><?php esc_html_e( 'Elemento Seleccionado', 'aura-suite' ); ?></h3>

                <!-- Texto -->
                <div id="aura-cb-text-props" style="display:none;">
                    <label><?php esc_html_e( 'Texto', 'aura-suite' ); ?>
                        <textarea id="aura-cb-text-content" class="aura-input" style="width:100%;" rows="3"></textarea>
                    </label>
                    <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Fuente', 'aura-suite' ); ?>
                        <select id="aura-cb-obj-font-family" class="aura-input" style="width:100%;"></select>
                    </label>
                    <label style="margin-top:4px;display:block;"><?php esc_html_e( 'Tamaño (px)', 'aura-suite' ); ?>
                        <input type="number" id="aura-cb-obj-font-size" class="aura-input" style="width:80px;" min="6" max="300" value="24">
                    </label>
                    <div style="margin-top:4px;display:flex;gap:4px;">
                        <button type="button" id="aura-cb-obj-bold"   class="button aura-cb-toggle-btn"><strong>N</strong></button>
                        <button type="button" id="aura-cb-obj-italic" class="button aura-cb-toggle-btn"><em>I</em></button>
                        <input type="color" id="aura-cb-obj-font-color" value="#000000" title="<?php esc_attr_e( 'Color del texto', 'aura-suite' ); ?>" style="width:36px;height:32px;padding:2px;">
                    </div>
                    <label style="margin-top:4px;display:block;"><?php esc_html_e( 'Alineación', 'aura-suite' ); ?>
                        <select id="aura-cb-obj-text-align" class="aura-input" style="width:100%;">
                            <option value="left"><?php esc_html_e( 'Izquierda', 'aura-suite' ); ?></option>
                            <option value="center"><?php esc_html_e( 'Centrado', 'aura-suite' ); ?></option>
                            <option value="right"><?php esc_html_e( 'Derecha', 'aura-suite' ); ?></option>
                        </select>
                    </label>
                </div>

                <!-- Posición / tamaño (todos los objetos) -->
                <div id="aura-cb-pos-props" style="margin-top:8px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;">
                        <label><?php esc_html_e( 'X', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-x" class="aura-input" style="width:100%;"></label>
                        <label><?php esc_html_e( 'Y', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-y" class="aura-input" style="width:100%;"></label>
                        <label><?php esc_html_e( 'Ancho', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-w" class="aura-input" style="width:100%;"></label>
                        <label><?php esc_html_e( 'Alto', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-h" class="aura-input" style="width:100%;"></label>
                    </div>
                    <label style="margin-top:4px;display:block;"><?php esc_html_e( 'Rotación (°)', 'aura-suite' ); ?>
                        <input type="number" id="aura-cb-obj-angle" class="aura-input" style="width:80px;" min="-360" max="360" value="0">
                    </label>
                    <label style="margin-top:4px;display:block;"><?php esc_html_e( 'Opacidad', 'aura-suite' ); ?>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input type="range" id="aura-cb-obj-opacity" min="0" max="100" step="5" value="100" style="flex:1;">
                            <span id="aura-cb-obj-opacity-label" style="font-size:.75rem;color:var(--cert-gray-500);min-width:32px;">100%</span>
                        </div>
                    </label>
                    <div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap;">
                        <button type="button" id="aura-cb-bring-forward"  class="button button-small">↑ <?php esc_html_e( 'Adelante', 'aura-suite' ); ?></button>
                        <button type="button" id="aura-cb-send-backward"  class="button button-small">↓ <?php esc_html_e( 'Atrás', 'aura-suite' ); ?></button>
                        <button type="button" id="aura-cb-delete-obj"     class="button button-small" style="color:#dc2626;">🗑 <?php esc_html_e( 'Eliminar', 'aura-suite' ); ?></button>
                        <button type="button" id="aura-cb-clone-obj"      class="button button-small">⎘ <?php esc_html_e( 'Clonar', 'aura-suite' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Firmantes disponibles -->
            <div class="aura-cb-props-section" style="margin-top:12px;">
                <h3><?php esc_html_e( 'Firmantes Activos', 'aura-suite' ); ?></h3>
                <div id="aura-cb-signers-list" class="aura-cb-signers">
                    <!-- Poblado por JS desde auraCertBuilder.signers -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden inputs para el save -->
<input type="hidden" id="aura-cb-template-id"
       value="<?php echo esc_attr( $is_new ? '0' : $template_id ); ?>">
<input type="hidden" id="aura-cb-templates-list-url"
       value="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-templates' ) ); ?>">
