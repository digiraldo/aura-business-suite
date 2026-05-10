<?php
/**
 * Template: Gestión de Áreas y Programas
 *
 * Renderizado por Aura_Areas_Admin::render_page()
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.1.0
 * @updated 1.3.0 — Imagen/logo reemplaza selector de icono; DataTables Responsive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$can_manage = current_user_can( 'manage_options' ) || current_user_can( 'aura_areas_manage' );
if ( ! $can_manage ) {
    wp_die( esc_html__( 'No tienes permisos para acceder a esta pagina.', 'aura-suite' ) );
}

// Tipos de área desde la tabla dinámica
$area_types   = Aura_Areas_Setup::get_all_types();
$default_type = 'program';
foreach ( $area_types as $slug => $info ) {
    if ( $info['is_default'] ) {
        $default_type = $slug;
        break;
    }
}
?>

<div class="wrap aura-areas-page">

    <!-- Encabezado -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-groups" style="margin-right:6px;"></span>
        <?php esc_html_e( 'Areas y Programas', 'aura-suite' ); ?>
    </h1>
    <button type="button" class="page-title-action" id="aura-add-area-btn">
        <span class="dashicons dashicons-plus-alt"></span>
        <?php esc_html_e( 'Agregar Area', 'aura-suite' ); ?>
    </button>
    <hr class="wp-header-end">

    <!-- Mensajes de estado -->
    <div id="aura-areas-notice" class="notice" style="display:none;">
        <p id="aura-areas-notice-text"></p>
    </div>

    <!-- Filtros -->
    <div class="aura-filters-container">
        <div class="aura-filters-row">

            <div class="aura-filter-group">
                <label for="aura-filter-search"><?php esc_html_e( 'Buscar:', 'aura-suite' ); ?></label>
                <input type="text" id="aura-filter-search"
                       placeholder="<?php esc_attr_e( 'Nombre o descripcion...', 'aura-suite' ); ?>"
                       class="regular-text">
            </div>

            <div class="aura-filter-group">
                <label for="aura-filter-type"><?php esc_html_e( 'Tipo:', 'aura-suite' ); ?></label>
                <select id="aura-filter-type">
                    <option value=""><?php esc_html_e( 'Todos', 'aura-suite' ); ?></option>
                    <?php foreach ( $area_types as $slug => $info ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $info['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="aura-filter-group">
                <label for="aura-filter-status"><?php esc_html_e( 'Estado:', 'aura-suite' ); ?></label>
                <select id="aura-filter-status">
                    <option value="active"><?php esc_html_e( 'Activas', 'aura-suite' ); ?></option>
                    <option value="archived"><?php esc_html_e( 'Archivadas', 'aura-suite' ); ?></option>
                    <option value=""><?php esc_html_e( 'Todas', 'aura-suite' ); ?></option>
                </select>
            </div>

            <div class="aura-filter-group" style="flex-direction:row;align-items:flex-end;gap:6px;">
                <button type="button" id="aura-apply-filters-btn" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Aplicar', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-clear-filters-btn" class="button">
                    <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                </button>
            </div>

        </div>
    </div>

    <!-- Tabla DataTables -->
    <table id="aura-areas-table" class="wp-list-table widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Logo', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Responsables', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Presupuesto', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</div><!-- .wrap -->

<!-- MODAL: Crear / Editar Area -->
<div id="aura-area-modal" style="display:none;">
    <div id="aura-area-modal-overlay"></div>
    <div id="aura-area-modal-box">

        <!-- Encabezado -->
        <div id="aura-area-modal-header">
            <h2 id="aura-area-modal-title"><?php esc_html_e( 'Nueva Area', 'aura-suite' ); ?></h2>
            <button type="button" id="aura-area-modal-close"
                    aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <!-- Cuerpo -->
        <div id="aura-area-modal-body">
            <form id="aura-area-form" novalidate>
                <input type="hidden" id="aura-area-id"       name="area_id"  value="0">
                <input type="hidden" id="aura-field-icon"    name="icon"     value="dashicons-groups">
                <input type="hidden" id="aura-field-logo-id" name="logo_id"  value="0">

                <!-- Fila 1: Logo + Nombre + Tipo -->
                <div class="aura-form-row">

                    <!-- Logo -->
                    <div class="aura-form-col" style="flex:0 0 150px;">
                        <div class="aura-field">
                            <label><?php esc_html_e( 'Logo / Imagen', 'aura-suite' ); ?></label>
                            <div class="aura-logo-upload-widget">
                                <div class="aura-logo-preview" id="aura-logo-preview">
                                    <span class="dashicons dashicons-format-image aura-logo-placeholder"></span>
                                </div>
                                <div class="aura-logo-actions">
                                    <button type="button" class="button" id="aura-logo-select-btn"
                                            style="margin-bottom:4px;">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php esc_html_e( 'Seleccionar', 'aura-suite' ); ?>
                                    </button>
                                    <button type="button" class="button" id="aura-logo-remove-btn"
                                            style="display:none;color:#d63638;border-color:#d63638;">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php esc_html_e( 'Quitar', 'aura-suite' ); ?>
                                    </button>
                                </div>
                                <p class="description" style="font-size:11px;margin-top:4px;">
                                    <?php esc_html_e( 'JPG, PNG o SVG.', 'aura-suite' ); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Nombre + Tipo + Descripcion -->
                    <div class="aura-form-col" style="flex:1;">

                        <div class="aura-form-row">
                            <div class="aura-form-col" style="flex:2;">
                                <div class="aura-field">
                                    <label for="aura-field-name">
                                        <?php esc_html_e( 'Nombre', 'aura-suite' ); ?>
                                        <span class="required">*</span>
                                    </label>
                                    <input type="text" id="aura-field-name" name="name" required
                                           placeholder="<?php esc_attr_e( 'Ej. Hadime Junior', 'aura-suite' ); ?>">
                                    <span class="aura-field-error" id="error-name" style="display:none;"></span>
                                </div>
                            </div>
                            <div class="aura-form-col" style="flex:1;">
                                <div class="aura-field">
                                    <label for="aura-field-type"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></label>
                                    <select id="aura-field-type" name="type">
                                        <?php foreach ( $area_types as $slug => $info ) : ?>
                                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $info['name'] ); ?></option>
                                        <?php endforeach; ?>
                                        <?php if ( empty( $area_types ) ) : ?>
                                            <option value="program"><?php esc_html_e( 'Programa', 'aura-suite' ); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="aura-field">
                            <label for="aura-field-description">
                                <?php esc_html_e( 'Descripcion', 'aura-suite' ); ?>
                            </label>
                            <textarea id="aura-field-description" name="description" rows="3"
                                      placeholder="<?php esc_attr_e( 'Descripcion del area o programa...', 'aura-suite' ); ?>"></textarea>
                        </div>

                    </div>
                </div><!-- /fila 1 -->

                <!-- Fila 2: Responsable + Area padre -->
                <div class="aura-form-row">
                    <div class="aura-form-col">
                        <div class="aura-field">
                            <label for="aura-field-responsible">
                                <?php esc_html_e( 'Responsable', 'aura-suite' ); ?>
                            </label>
                            <select id="aura-field-responsible" name="responsible_user_id">
                                <option value="0"><?php esc_html_e( '-- Sin asignar --', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="aura-form-col">
                        <div class="aura-field">
                            <label for="aura-field-parent">
                                <?php esc_html_e( 'Area padre', 'aura-suite' ); ?>
                            </label>
                            <select id="aura-field-parent" name="parent_area_id">
                                <option value="0"><?php esc_html_e( '-- Ninguna --', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fila 3: Color + Orden -->
                <div class="aura-form-row">
                    <div class="aura-form-col">
                        <div class="aura-field">
                            <label for="aura-field-color">
                                <?php esc_html_e( 'Color de acento', 'aura-suite' ); ?>
                            </label>
                            <input type="text" id="aura-field-color" name="color"
                                   value="#2271b1" class="aura-color-picker">
                            <p class="description">
                                <?php esc_html_e( 'Se usa en badges y bordes.', 'aura-suite' ); ?>
                            </p>
                        </div>
                    </div>
                    <div class="aura-form-col">
                        <div class="aura-field">
                            <label for="aura-field-sort"><?php esc_html_e( 'Orden', 'aura-suite' ); ?></label>
                            <input type="number" id="aura-field-sort" name="sort_order"
                                   value="0" min="0" style="width:100px;">
                        </div>
                    </div>
                    <div class="aura-form-col"></div>
                </div>

            </form>
        </div><!-- #aura-area-modal-body -->

        <!-- Pie -->
        <div id="aura-area-modal-footer">
            <button type="button" class="button" id="aura-area-cancel-btn">
                <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button button-primary" id="aura-area-save-btn">
                <span class="spinner" id="aura-save-spinner"
                      style="float:none; margin:0 4px 0 0; display:none;"></span>
                <?php esc_html_e( 'Guardar Area', 'aura-suite' ); ?>
            </button>
        </div>

    </div><!-- #aura-area-modal-box -->
</div><!-- #aura-area-modal -->

<!-- MODAL DE RECORTE DE LOGO -->
<div id="aura-crop-modal" style="display:none;">
    <div id="aura-crop-overlay"></div>
    <div id="aura-crop-box">
        <div id="aura-crop-header">
            <h3><?php esc_html_e( 'Recortar imagen', 'aura-suite' ); ?></h3>
            <button type="button" id="aura-crop-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div id="aura-crop-body">
            <p class="description" style="margin-bottom:10px;">
                <?php esc_html_e( 'Ajusta el area de recorte y haz clic en "Aplicar recorte".', 'aura-suite' ); ?>
            </p>
            <div id="aura-crop-preview-container">
                <img id="aura-crop-img" src="" alt="Recorte">
            </div>
        </div>
        <div id="aura-crop-footer">
            <button type="button" class="button" id="aura-crop-cancel">
                <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button button-primary" id="aura-crop-apply">
                <span class="spinner" id="aura-crop-spinner" style="float:none;margin:0 4px 0 0;display:none;"></span>
                <?php esc_html_e( 'Aplicar recorte', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<?php
// Media uploader de WordPress
wp_enqueue_media();

// Color picker de WordPress
wp_enqueue_style( 'wp-color-picker' );
wp_enqueue_script( 'wp-color-picker' );

// Cropper.js
wp_enqueue_style(
    'cropperjs-css',
    'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css',
    [], '1.6.2'
);
wp_enqueue_script(
    'cropperjs',
    'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js',
    [], '1.6.2', true
);

// DataTables
wp_enqueue_style(
    'datatables-css',
    'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
    [], '2.2.2'
);
wp_enqueue_style(
    'datatables-responsive-css',
    'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
    ['datatables-css'], '3.0.4'
);
wp_enqueue_script(
    'datatables-js',
    'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
    ['jquery'], '2.2.2', true
);
wp_enqueue_script(
    'datatables-responsive-js',
    'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
    ['datatables-js'], '3.0.4', true
);
?>

<style>
/* Filtros */
.aura-filters-container { margin: 15px 0; }
.aura-filters-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.aura-filter-group { display:flex; flex-direction:column; gap:4px; }
.aura-filter-group label { font-weight:600; font-size:13px; }

/* DataTables Responsive expand button (Dashicons) */
table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control::before,
table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control::before {
    font-family: dashicons; content: "\f347";
    background: none; color: #2271b1; border: none; box-shadow: none;
    display: inline-block; font-size: 18px; line-height: 1;
    width: auto; height: auto; margin: 0; padding: 0;
    vertical-align: middle; cursor: pointer;
}
table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control::before,
table.dataTable.dtr-inline.collapsed > tbody > tr.parent > th.dtr-control::before {
    content: "\f460"; color: #d63638;
}

/* DataTables DOM */
.aura-dt-top {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:8px; padding:4px 0;
}
.aura-dt-bottom {
    display:flex; align-items:center; justify-content:flex-end; margin-top:10px;
}
.dataTables_length, .dataTables_info { font-size:13px; }

/* Imagen con tooltip */
.aura-img-preview { position:relative; display:inline-block; cursor:default; }
.aura-img-preview img {
    width:48px; height:48px; object-fit:cover;
    border-radius:6px; border:2px solid #e5e5e5; display:block; transition:border-color .15s;
}
.aura-img-preview:hover img { border-color:#2271b1; }
.aura-img-preview-popup {
    display:none; position:absolute; left:56px; top:0; z-index:9999;
    background:#fff; border:1px solid #dcdcde; border-radius:6px; padding:4px;
    box-shadow:0 4px 16px rgba(0,0,0,.2); pointer-events:none;
}
.aura-img-preview-popup img {
    width:160px; height:160px; object-fit:cover; border-radius:4px; display:block;
}
.aura-img-preview:hover .aura-img-preview-popup { display:block; }

/* Placeholder */
.aura-area-logo-placeholder {
    width:48px; height:48px; display:flex; align-items:center; justify-content:center;
    background:#f0f0f1; border-radius:6px; border:2px solid #e5e5e5;
}
.aura-area-logo-placeholder .dashicons {
    font-size:28px; color:#c3c4c7; width:28px; height:28px; line-height:1;
}

/* Name cell */
.aura-color-badge {
    display:inline-block; width:10px; height:30px;
    border-radius:3px; flex-shrink:0; margin-right:6px; border:1px solid rgba(0,0,0,.1);
}
.aura-area-name-cell { display:flex; align-items:center; gap:6px; }
.aura-area-name-cell .aura-area-name { font-weight:600; font-size:13px; color:#1d2327; }
.aura-area-name-cell .aura-area-parent { font-size:11px; color:#6b7280; margin-top:2px; }
.aura-area-description { font-size:12px; color:#6b7280; margin-top:3px; line-height:1.3; }

/* Badges */
.aura-status-badge {
    display:inline-block; padding:2px 8px; border-radius:20px;
    font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;
}
.aura-status-active   { background:#d1fae5; color:#065f46; }
.aura-status-archived { background:#f3f4f6; color:#6b7280; }
.aura-type-badge {
    display:inline-block; padding:2px 7px; border-radius:4px;
    font-size:11px; background:#e0e7ff; color:#3730a3; font-weight:600;
}

/* Action buttons */
.aura-action-btn {
    position:relative; display:inline-flex; align-items:center; justify-content:center;
    background:none; border:1px solid #ccc; border-radius:4px;
    width:28px; height:28px; padding:0; cursor:pointer;
    font-size:12px; margin-right:3px; transition:all .15s;
    vertical-align:middle; text-decoration:none; color:#50575e;
}
.aura-action-btn .dashicons { font-size:14px; width:14px; height:14px; line-height:1; }
.aura-action-btn:hover { background:#f0f0f0; color:#2271b1; border-color:#2271b1; }
.aura-action-btn.danger { border-color:#d63638; color:#d63638; }
.aura-action-btn.danger:hover { background:#fce8e8; }
.aura-action-btn.success { border-color:#00a32a; color:#00a32a; }
.aura-action-btn.success:hover { background:#e8f5e9; }
.aura-action-btn::after {
    content:attr(data-tooltip); position:absolute; bottom:calc(100% + 6px); left:50%;
    transform:translateX(-50%); background:#1d2327; color:#fff; font-size:11px;
    white-space:nowrap; padding:3px 8px; border-radius:3px; pointer-events:none;
    opacity:0; transition:opacity .15s; z-index:99999;
}
.aura-action-btn:hover::after { opacity:1; }

/* Responsables */
.aura-users-stack { display:flex; align-items:center; gap:4px; }
.aura-user-av { position:relative; display:inline-block; }
.aura-user-av img {
    width:30px; height:30px; border-radius:50%;
    border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,.2); display:block;
}
.aura-user-av::after {
    content:attr(data-name); position:absolute; bottom:calc(100% + 5px); left:50%;
    transform:translateX(-50%); background:#1d2327; color:#fff; font-size:11px;
    white-space:nowrap; padding:3px 7px; border-radius:3px; pointer-events:none;
    opacity:0; transition:opacity .15s; z-index:9999;
}
.aura-user-av:hover::after { opacity:1; }
.aura-users-more { font-size:11px; color:#6b7280; margin-left:2px; }

/* Notice */
#aura-areas-notice { margin:12px 0; border-left-width:4px; border-left-style:solid; }
#aura-areas-notice.notice-success { border-left-color:#00a32a; }
#aura-areas-notice.notice-error   { border-left-color:#d63638; }

/* Modal area */
#aura-area-modal {
    position:fixed; inset:0; z-index:100000;
    display:flex; align-items:center; justify-content:center;
}
#aura-area-modal-overlay { position:absolute; inset:0; background:rgba(0,0,0,.55); }
#aura-area-modal-box {
    position:relative; z-index:1; background:#fff; border-radius:8px;
    width:760px; max-width:95vw; max-height:90vh;
    display:flex; flex-direction:column;
    box-shadow:0 8px 32px rgba(0,0,0,.25); overflow:hidden;
}
#aura-area-modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px; background:#1d2327; color:#fff;
}
#aura-area-modal-header h2 { margin:0; font-size:16px; color:#fff; }
#aura-area-modal-close {
    background:none; border:none; cursor:pointer; color:#aaa;
    font-size:20px; line-height:1; padding:4px; transition:color .2s;
}
#aura-area-modal-close:hover { color:#fff; }
#aura-area-modal-body { padding:20px; overflow-y:auto; flex:1; }
#aura-area-modal-footer {
    padding:14px 20px; border-top:1px solid #e5e5e5;
    display:flex; justify-content:flex-end; gap:8px; background:#f6f7f7;
}

/* Form layout */
.aura-form-row { display:flex; gap:16px; margin-bottom:16px; }
.aura-form-col { flex:1; }
.aura-field { margin-bottom:14px; }
.aura-field label { display:block; font-weight:600; margin-bottom:5px; font-size:13px; }
.aura-field label .required { color:#d63638; margin-left:2px; }
.aura-field input[type="text"],
.aura-field input[type="number"],
.aura-field textarea,
.aura-field select { width:100%; box-sizing:border-box; }
.aura-field-error { color:#d63638; font-size:12px; margin-top:4px; display:block; }

/* Logo upload widget */
.aura-logo-upload-widget { display:flex; flex-direction:column; gap:8px; }
.aura-logo-preview {
    width:120px; height:120px; border:2px dashed #c3c4c7; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    overflow:hidden; background:#f6f7f7; transition:border-color .15s;
}
.aura-logo-preview:hover { border-color:#2271b1; }
.aura-logo-preview img { width:100%; height:100%; object-fit:cover; display:block; }
.aura-logo-placeholder { color:#c3c4c7; font-size:40px; width:40px; height:40px; }
.aura-logo-actions { display:flex; flex-direction:column; gap:4px; }

/* Modal recorte */
#aura-crop-modal {
    position:fixed; inset:0; z-index:200000;
    display:flex; align-items:center; justify-content:center;
}
#aura-crop-overlay { position:absolute; inset:0; background:rgba(0,0,0,.7); }
#aura-crop-box {
    position:relative; z-index:1; background:#fff; border-radius:8px;
    width:640px; max-width:95vw; max-height:90vh;
    display:flex; flex-direction:column;
    box-shadow:0 8px 40px rgba(0,0,0,.35); overflow:hidden;
}
#aura-crop-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 18px; background:#1d2327; color:#fff;
}
#aura-crop-header h3 { margin:0; font-size:15px; color:#fff; }
#aura-crop-close { background:none; border:none; cursor:pointer; color:#aaa; font-size:20px; line-height:1; }
#aura-crop-close:hover { color:#fff; }
#aura-crop-body { padding:16px; overflow:auto; flex:1; }
#aura-crop-preview-container { max-height:400px; overflow:hidden; }
#aura-crop-img { max-width:100%; display:block; }
#aura-crop-footer {
    padding:12px 18px; border-top:1px solid #e5e5e5;
    display:flex; justify-content:flex-end; gap:8px; background:#f6f7f7;
}
</style>

<script>
var auraAreasData = <?php echo wp_json_encode( [
    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'aura_areas_nonce' ),
    'defaultType' => $default_type,
    'strings' => [
        'confirmArchive'    => __( 'Archivar esta area? Podras reactivarla mas tarde.', 'aura-suite' ),
        'saved'             => __( 'Area guardada exitosamente.', 'aura-suite' ),
        'archived'          => __( 'Area archivada.', 'aura-suite' ),
        'error'             => __( 'Ocurrio un error. Intenta nuevamente.', 'aura-suite' ),
        'nameRequired'      => __( 'El nombre del area es obligatorio.', 'aura-suite' ),
        'loading'           => __( 'Cargando...', 'aura-suite' ),
        'noAreas'           => __( 'No se encontraron areas.', 'aura-suite' ),
        'confirmReactivate' => __( 'Reactivar esta area?', 'aura-suite' ),
        'reactivated'       => __( 'Area reactivada.', 'aura-suite' ),
        'selectImage'       => __( 'Seleccionar imagen de logo', 'aura-suite' ),
    ],
] ); ?>;
</script>
<script>
(function($) {
    'use strict';

    var cfg       = window.auraAreasData || {};
    var s         = cfg.strings || {};
    var dtTable   = null;
    var cropper   = null;
    var cropAttId = 0;

    // AJAX helper
    function ajax(action, data, done) {
        $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: cfg.nonce }, data))
            .done(function(r) {
                if (r.success) { done(r.data); }
                else { showNotice((r.data && r.data.message) || s.error, 'error'); }
            })
            .fail(function() { showNotice(s.error, 'error'); });
    }

    function showNotice(msg, type) {
        type = type || 'success';
        var $n = $('#aura-areas-notice');
        $n.removeClass('notice-success notice-error')
          .addClass('notice-' + type)
          .find('#aura-areas-notice-text').text(msg);
        $n.show();
        setTimeout(function() { $n.fadeOut(); }, 4500);
    }

    function fmtCurrency(val) {
        if (!val && val !== 0) return '&mdash;';
        return new Intl.NumberFormat('es-MX', {
            style: 'currency', currency: 'MXN', minimumFractionDigits: 0
        }).format(val);
    }

    function esc(str) {
        return $('<span>').text(str || '').html();
    }

    function renderLogoCell(area) {
        if (area.logo_thumb_url) {
            return '<div class="aura-img-preview">' +
                   '<img src="' + esc(area.logo_thumb_url) + '" alt="" loading="lazy">' +
                   '<div class="aura-img-preview-popup">' +
                   '<img src="' + esc(area.logo_url || area.logo_thumb_url) + '" alt="">' +
                   '</div></div>';
        }
        return '<div class="aura-area-logo-placeholder">' +
               '<span class="dashicons dashicons-format-image"></span></div>';
    }

    function renderNameCell(area) {
        var name   = esc(area.name);
        var color  = esc(area.color || '#2271b1');
        var parent = area.parent_name
            ? '<div class="aura-area-parent">' + esc(area.parent_name) + '</div>'
            : '';
        var desc   = area.description
            ? '<div class="aura-area-description">' +
              esc(area.description.substring(0, 90)) +
              (area.description.length > 90 ? '&hellip;' : '') + '</div>'
            : '';
        return '<div class="aura-area-name-cell">' +
               '<span class="aura-color-badge" style="background:' + color + ';"></span>' +
               '<div><div class="aura-area-name">' + name + '</div>' + parent + '</div>' +
               '</div>' + desc;
    }

    function renderUsersCell(area) {
        if (!area.assigned_users || area.assigned_users.length === 0) {
            return '<em style="color:#aaa;font-size:12px;">Sin asignar</em>';
        }
        var max  = 3;
        var html = '<div class="aura-users-stack">';
        $.each(area.assigned_users.slice(0, max), function(i, u) {
            var nm = esc(u.display_name);
            html += '<span class="aura-user-av" data-name="' + nm + '">' +
                    '<img src="' + esc(u.avatar_url) + '" alt="' + nm + '" loading="lazy">' +
                    '</span>';
        });
        if (area.assigned_users.length > max) {
            html += '<span class="aura-users-more">+' + (area.assigned_users.length - max) + '</span>';
        }
        return html + '</div>';
    }

    function areaToRow(area) {
        var statusClass = area.status === 'active' ? 'aura-status-active' : 'aura-status-archived';
        var statusLabel = area.status === 'active' ? 'Activa' : 'Archivada';
        var id          = area.id;

        var archiveBtn = area.status === 'active'
            ? '<button class="aura-action-btn danger aura-archive-btn" data-id="' + id + '" data-tooltip="Archivar">' +
              '<span class="dashicons dashicons-archive"></span></button>'
            : '<button class="aura-action-btn success aura-reactivate-btn" data-id="' + id + '" data-tooltip="Reactivar">' +
              '<span class="dashicons dashicons-update"></span></button>';

        var budgetUrl    = 'admin.php?page=aura-financial-budgets&area_id=' + id;
        var dashboardUrl = 'admin.php?page=aura-areas&view=dashboard&area_id=' + id;

        return [
            renderLogoCell(area),
            renderNameCell(area),
            '<span class="aura-type-badge">' + esc(area.type_label) + '</span>',
            renderUsersCell(area),
            fmtCurrency(area.budget_assigned),
            '<span class="aura-status-badge ' + statusClass + '">' + statusLabel + '</span>',
            '<button class="aura-action-btn aura-edit-btn" data-id="' + id + '" data-tooltip="Editar">' +
            '<span class="dashicons dashicons-edit"></span></button>' +
            '<a href="' + dashboardUrl + '" class="aura-action-btn" data-tooltip="Dashboard">' +
            '<span class="dashicons dashicons-chart-bar"></span></a>' +
            '<a href="' + budgetUrl + '" class="aura-action-btn" data-tooltip="Presupuesto">' +
            '<span class="dashicons dashicons-money-alt"></span></a>' +
            archiveBtn,
        ];
    }

    function initDataTable() {
        dtTable = $('#aura-areas-table').DataTable({
            responsive:  true,
            searching:   false,
            dom:         '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            pageLength:  20,
            lengthMenu:  [[10, 20, 50, 100], [10, 20, 50, 100]],
            language: {
                lengthMenu:  'Mostrar _MENU_ areas',
                info:        'Mostrando _START_-_END_ de _TOTAL_ areas',
                paginate: { previous: '&laquo;', next: '&raquo;' },
                zeroRecords: 'No se encontraron areas.',
                emptyTable:  'Sin areas disponibles.',
            },
            columnDefs: [
                { responsivePriority: 10000, targets: 0 },
                { responsivePriority: 1,     targets: 1 },
                { responsivePriority: 2,     targets: 6 },
                { responsivePriority: 3,     targets: 5 },
                { responsivePriority: 4,     targets: 2 },
                { responsivePriority: 5,     targets: 4 },
                { responsivePriority: 6,     targets: 3 },
                { orderable: false,  targets: [0, 3, 6] },
            ],
            data: [],
            columns: [null,null,null,null,null,null,null],
        });
    }

    function loadAreas() {
        if (!dtTable) return;
        ajax('aura_areas_list', {
            status:   $('#aura-filter-status').val(),
            type:     $('#aura-filter-type').val(),
            search:   $('#aura-filter-search').val(),
            paged:    1,
        }, function(data) {
            dtTable.clear();
            if (data.areas && data.areas.length > 0) {
                dtTable.rows.add(data.areas.map(areaToRow));
            }
            dtTable.draw();
        });
    }

    function loadUsers(selectedId) {
        if ($('#aura-field-responsible option').length > 1) {
            if (selectedId !== undefined) $('#aura-field-responsible').val(selectedId);
            return;
        }
        ajax('aura_areas_users', {}, function(data) {
            var $sel = $('#aura-field-responsible');
            $sel.find('option:not(:first)').remove();
            $.each(data.users, function(i, u) {
                $sel.append($('<option>').val(u.id).text(u.name + ' (' + u.email + ')'));
            });
            if (selectedId) $sel.val(selectedId);
        });
    }

    function loadParentAreas(exceptId, selectedId) {
        ajax('aura_areas_areas_dropdown', { except_id: exceptId || 0 }, function(data) {
            var $sel = $('#aura-field-parent');
            $sel.find('option:not(:first)').remove();
            $.each(data.areas, function(i, a) {
                $sel.append($('<option>').val(a.id).text(a.name));
            });
            if (selectedId) $sel.val(selectedId);
        });
    }

    function setLogoPreview(url, attId) {
        var $prev = $('#aura-logo-preview');
        $prev.empty();
        if (url) {
            $prev.html('<img src="' + url + '" alt="Logo" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">');
            $('#aura-field-logo-id').val(attId || 0);
            $('#aura-logo-remove-btn').show();
        } else {
            $prev.html('<span class="dashicons dashicons-format-image aura-logo-placeholder"></span>');
            $('#aura-field-logo-id').val(0);
            $('#aura-logo-remove-btn').hide();
        }
    }

    var mediaFrame = null;
    function openMediaLibrary() {
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title:   s.selectImage || 'Seleccionar imagen',
            button:  { text: 'Usar esta imagen' },
            library: { type: 'image' },
            multiple: false,
        });
        mediaFrame.on('select', function() {
            var att = mediaFrame.state().get('selection').first().toJSON();
            cropAttId = att.id;
            openCropModal(att.url);
        });
        mediaFrame.open();
    }

    function openCropModal(imgUrl) {
        var $img = $('#aura-crop-img');
        $img.attr('src', imgUrl);
        $('#aura-crop-modal').show();
        if (cropper) { cropper.destroy(); cropper = null; }

        $img.off('load.cropper').on('load.cropper', function() {
            cropper = new Cropper(this, {
                aspectRatio:  1,
                viewMode:     1,
                autoCropArea: 0.8,
                responsive:   true,
            });
        });
        if ($img[0].complete && $img[0].naturalWidth > 0) {
            $img.trigger('load.cropper');
        }
    }

    function closeCropModal() {
        $('#aura-crop-modal').hide();
        if (cropper) { cropper.destroy(); cropper = null; }
    }

    function applyCrop() {
        if (!cropper) return;
        var data = cropper.getData(true);
        $('#aura-crop-spinner').show();
        $('#aura-crop-apply').prop('disabled', true);

        $.post(cfg.ajaxUrl, {
            action:        'aura_areas_crop_logo',
            nonce:         cfg.nonce,
            attachment_id: cropAttId,
            x:             data.x,
            y:             data.y,
            width:         data.width,
            height:        data.height,
        })
        .done(function(r) {
            $('#aura-crop-spinner').hide();
            $('#aura-crop-apply').prop('disabled', false);
            if (r.success) {
                setLogoPreview(r.data.url, r.data.attachment_id);
                closeCropModal();
            } else {
                showNotice((r.data && r.data.message) || s.error, 'error');
            }
        })
        .fail(function() {
            $('#aura-crop-spinner').hide();
            $('#aura-crop-apply').prop('disabled', false);
            showNotice(s.error, 'error');
        });
    }

    function openModal(title, area) {
        $('#aura-area-modal-title').text(title);
        var isEdit = area && area.id;

        $('#aura-area-id').val(isEdit ? area.id : 0);
        $('#aura-field-name').val(isEdit ? area.name : '');
        $('#aura-field-type').val(isEdit ? area.type : (cfg.defaultType || 'program'));
        $('#aura-field-description').val(isEdit ? area.description : '');
        $('#aura-field-color').val(isEdit ? area.color : '#2271b1');
        $('#aura-field-icon').val(isEdit ? (area.icon || 'dashicons-groups') : 'dashicons-groups');
        $('#aura-field-sort').val(isEdit ? area.sort_order : 0);
        $('#error-name').hide();

        if (isEdit && area.logo_url) {
            setLogoPreview(area.logo_url, area.logo_id);
        } else {
            setLogoPreview('', 0);
        }

        try {
            $('#aura-field-color').wpColorPicker('color', isEdit ? area.color : '#2271b1');
        } catch(e) {}

        loadUsers(isEdit ? area.responsible_user_id : 0);
        loadParentAreas(isEdit ? area.id : 0, isEdit ? area.parent_area_id : 0);

        $('#aura-area-modal').show();
        setTimeout(function() { $('#aura-field-name').focus(); }, 100);
    }

    function closeModal() {
        $('#aura-area-modal').hide();
    }

    function saveArea() {
        var name = $('#aura-field-name').val().trim();
        if (!name) {
            $('#error-name').text(s.nameRequired).show();
            $('#aura-field-name').focus();
            return;
        }
        $('#error-name').hide();
        $('#aura-save-spinner').show();
        $('#aura-area-save-btn').prop('disabled', true);

        ajax('aura_areas_save', {
            area_id:             $('#aura-area-id').val(),
            name:                name,
            type:                $('#aura-field-type').val(),
            description:         $('#aura-field-description').val(),
            color:               $('#aura-field-color').val(),
            icon:                $('#aura-field-icon').val(),
            logo_id:             $('#aura-field-logo-id').val(),
            sort_order:          $('#aura-field-sort').val(),
            responsible_user_id: $('#aura-field-responsible').val(),
            parent_area_id:      $('#aura-field-parent').val(),
        }, function(data) {
            $('#aura-save-spinner').hide();
            $('#aura-area-save-btn').prop('disabled', false);
            showNotice(data.message || s.saved, 'success');
            closeModal();
            loadAreas();
        });

        setTimeout(function() {
            $('#aura-save-spinner').hide();
            $('#aura-area-save-btn').prop('disabled', false);
        }, 5000);
    }

    function toggleArchive(id, action) {
        var msg = action === 'reactivate' ? s.confirmReactivate : s.confirmArchive;
        if (!confirm(msg)) return;
        ajax('aura_areas_delete', { area_id: id, archive_action: action }, function(data) {
            showNotice(data.message, 'success');
            loadAreas();
        });
    }

    $(document).ready(function() {

        initDataTable();

        $('.aura-color-picker').wpColorPicker();

        loadAreas();

        // Filtros
        $('#aura-apply-filters-btn').on('click', function() { loadAreas(); });
        $('#aura-filter-search').on('keypress', function(e) { if (e.which === 13) loadAreas(); });
        $('#aura-clear-filters-btn').on('click', function() {
            $('#aura-filter-search').val('');
            $('#aura-filter-type').val('');
            $('#aura-filter-status').val('active');
            loadAreas();
        });

        // Modal area
        $('#aura-add-area-btn').on('click', function() { openModal('Nueva Area', null); });
        $('#aura-area-modal-close, #aura-area-cancel-btn').on('click', closeModal);
        $('#aura-area-modal-overlay').on('click', closeModal);
        $('#aura-area-save-btn').on('click', saveArea);

        // Tabla delegado
        $(document).on('click', '.aura-edit-btn', function() {
            var id = $(this).data('id');
            ajax('aura_areas_get', { area_id: id }, function(data) {
                openModal('Editar Area', data.area);
            });
        });
        $(document).on('click', '.aura-archive-btn', function() {
            toggleArchive($(this).data('id'), 'archive');
        });
        $(document).on('click', '.aura-reactivate-btn', function() {
            toggleArchive($(this).data('id'), 'reactivate');
        });

        // Logo upload
        $('#aura-logo-select-btn').on('click', openMediaLibrary);
        $('#aura-logo-remove-btn').on('click', function() { setLogoPreview('', 0); });

        // Crop
        $('#aura-crop-close, #aura-crop-cancel').on('click', closeCropModal);
        $('#aura-crop-overlay').on('click', closeCropModal);
        $('#aura-crop-apply').on('click', applyCrop);

        // ESC global
        $(document).on('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if ($('#aura-crop-modal').is(':visible')) {
                closeCropModal();
            } else {
                closeModal();
            }
        });

    });

})(jQuery);
</script>