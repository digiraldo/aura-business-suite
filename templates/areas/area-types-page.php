<?php
/**
 * Template: Tipos de Área — CRUD Admin Page
 *
 * Renderizado por Aura_Areas_Types::render_page()
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.2.0
 * @updated 1.7.7 — DataTables Responsive + dark/light mode + estándar PRD §5.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nonce = wp_create_nonce( Aura_Areas_Types::NONCE );

// ── Assets: WordPress Color Picker ───────────────────────────────────
wp_enqueue_style( 'wp-color-picker' );
wp_enqueue_script( 'wp-color-picker' );

// ── Assets: DataTables 2.2.2 + Responsive 3.0.4 (estándar PRD §5.6) ─
wp_enqueue_style(
    'datatables-css',
    'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
    [], '2.2.2'
);
wp_enqueue_style(
    'datatables-responsive-css',
    'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
    [ 'datatables-css' ], '3.0.4'
);
wp_enqueue_script(
    'datatables-js',
    'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
    [ 'jquery' ], '2.2.2', true
);
wp_enqueue_script(
    'datatables-responsive-js',
    'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
    [ 'datatables-js' ], '3.0.4', true
);
?>
<style>
/* ── Variables de color — modo claro ──────────────────────────────── */
#aura-types-app {
    --at-bg:         #f0f0f1;
    --at-surface:    #ffffff;
    --at-modal-surface: #ffffff;
    --at-border:     #dcdcde;
    --at-text:       #1d2327;
    --at-muted:      #646970;
    --at-input-bg:   #ffffff;
    --at-input-ro:   #f6f7f7;
    --at-header-bg:  #1d2327;
    --at-header-txt: #ffffff;
    --at-footer-bg:  #f6f7f7;
    --at-hover-row:  #f6f7f7;
    --at-code-bg:    #f0f0f1;
    --at-code-txt:   #1d2327;
    --at-notice-bg:  #ffffff;
}

/* ── Variables de color — WP Dark Mode plugin ────────────────────── */
html[data-wp-dark-mode-scheme="dark"] #aura-types-app {
    --at-bg:         #13131f;
    --at-surface:    #1e1e2e;
    --at-modal-surface: #1e1e2e;
    --at-border:     #2d2d3d;
    --at-text:       #e2e8f0;
    --at-muted:      #94a3b8;
    --at-input-bg:   #191929;
    --at-input-ro:   #111120;
    --at-header-bg:  #0d0d1a;
    --at-header-txt: #e2e8f0;
    --at-footer-bg:  #191929;
    --at-hover-row:  #191929;
    --at-code-bg:    #111120;
    --at-code-txt:   #93c5fd;
    --at-notice-bg:  #191929;
}

/* ── Variables de color — WP Admin temas Midnight / Coffee ──────── */
body.admin-color-midnight #aura-types-app,
body.admin-color-coffee   #aura-types-app {
    --at-bg:         #16161f;
    --at-surface:    #1e1e2e;
    --at-modal-surface: #1e1e2e;
    --at-border:     #2d2d3d;
    --at-text:       #dde1eb;
    --at-muted:      #8b96aa;
    --at-input-bg:   #191929;
    --at-input-ro:   #111120;
    --at-header-bg:  #0d0d1a;
    --at-header-txt: #dde1eb;
    --at-footer-bg:  #191929;
    --at-hover-row:  #191929;
    --at-code-bg:    #111120;
    --at-code-txt:   #93c5fd;
    --at-notice-bg:  #191929;
}

/* ── Layout base ──────────────────────────────────────────────────── */
#aura-types-app { color: var(--at-text); }

/* ── Barra de filtros ─────────────────────────────────────────────── */
.at-filters-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    background: var(--at-surface);
    border: 1px solid var(--at-border);
    border-radius: 6px;
    padding: 10px 14px;
    margin: 16px 0;
}
.at-filters-bar input[type="search"] {
    background: var(--at-input-bg);
    border: 1px solid var(--at-border);
    color: var(--at-text);
    border-radius: 4px;
    height: 32px;
    padding: 0 8px;
    font-size: 13px;
    width: 240px;
}
.at-filters-bar input[type="search"]::placeholder { color: var(--at-muted); }

/* ── Contenedor de tabla ──────────────────────────────────────────── */
.at-table-wrap { background: var(--at-surface); border: 1px solid var(--at-border); border-radius: 6px; padding: 16px; }

/* ── dataTable filas ─────────────────────────────────────────────── */
#aura-types-table                    { color: var(--at-text) !important; border-color: var(--at-border) !important; }
#aura-types-table thead th           { background: var(--at-surface) !important; color: var(--at-text) !important; border-bottom: 2px solid var(--at-border) !important; }
#aura-types-table tbody tr           { background: var(--at-surface) !important; }
#aura-types-table tbody tr:hover td  { background: var(--at-hover-row) !important; }
#aura-types-table tbody td           { border-bottom: 1px solid var(--at-border) !important; color: var(--at-text) !important; vertical-align: middle; }
#aura-types-table tbody tr.odd   td  { background: var(--at-surface) !important; }
#aura-types-table tbody tr.even  td  { background: var(--at-hover-row) !important; }
#aura-types-table code               { background: var(--at-code-bg); color: var(--at-code-txt); padding: 1px 5px; border-radius: 3px; font-size: 12px; }

/* ── DataTables DOM layout ───────────────────────────────────────── */
.aura-dt-top    { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding: 4px 0; }
.aura-dt-bottom { display: flex; align-items: center; justify-content: flex-end; margin-top: 10px; }
.dataTables_length,
.dataTables_info { font-size: 13px; color: var(--at-muted); }
.dataTables_length select,
.dataTables_length label {
    color: var(--at-text);
    background: var(--at-input-bg);
    border-color: var(--at-border);
}
.dataTables_paginate .paginate_button {
    color: var(--at-text) !important;
    background: var(--at-surface) !important;
    border-color: var(--at-border) !important;
    border-radius: 3px !important;
}
.dataTables_paginate .paginate_button:hover {
    background: var(--at-hover-row) !important;
    color: var(--at-text) !important;
    border-color: var(--at-border) !important;
}
.dataTables_paginate .paginate_button.current {
    background: #2271b1 !important;
    color: #fff !important;
    border-color: #2271b1 !important;
}

/* ── DataTables Responsive — botón expand (Dashicons, estándar PRD) ─ */
table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control,
table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control {
    position: relative !important;
    padding-left: 36px !important;
    cursor: pointer !important;
}
table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control::before,
table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control::before {
    content: "\f344" !important;
    font-family: "dashicons" !important;
    position: absolute !important;
    left: 8px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 22px !important;
    height: 22px !important;
    background-color: #2271b1 !important;
    color: #fff !important;
    border-radius: 50% !important;
    border: none !important;
    box-sizing: border-box !important;
    font-size: 16px !important;
    z-index: 10 !important;
    box-shadow: 0 2px 4px rgba(0,0,0,.3) !important;
    margin: 0 !important;
    text-indent: 0 !important;
}
table.dataTable.dtr-inline.collapsed > tbody > tr.dt-hasChild > td.dtr-control::before,
table.dataTable.dtr-inline.collapsed > tbody > tr.dt-hasChild > th.dtr-control::before {
    content: "\f343" !important;
    background-color: #646970 !important;
}

/* ── Badges de tipo ──────────────────────────────────────────────── */
.at-default-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(0,163,42,.12); color: #00a32a;
    font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px;
}
html[data-wp-dark-mode-scheme="dark"] .at-default-badge,
body.admin-color-midnight .at-default-badge,
body.admin-color-coffee   .at-default-badge {
    background: rgba(0,163,42,.2); color: #6ee7b7;
}

/* ── Acciones de fila ────────────────────────────────────────────── */
.at-row-actions { display: flex; gap: 4px; align-items: center; }
.at-btn-action {
    display: inline-flex; align-items: center; justify-content: center;
    background: none; border: 1px solid var(--at-border); border-radius: 4px;
    width: 28px; height: 28px; padding: 0; cursor: pointer;
    color: var(--at-muted); transition: all .15s; vertical-align: middle;
}
.at-btn-action .dashicons { font-size: 14px; width: 14px; height: 14px; line-height: 1; }
.at-btn-action:hover { background: var(--at-hover-row); color: #2271b1; border-color: #2271b1; }
.at-btn-action.danger { border-color: #d63638; color: #d63638; }
.at-btn-action.danger:hover { background: rgba(214,54,56,.1); }

/* ── Aviso ───────────────────────────────────────────────────────── */
#aura-types-notice {
    margin: 10px 0;
    background: var(--at-notice-bg);
    border-left-width: 4px;
    border-left-style: solid;
}
#aura-types-notice.notice-success { border-left-color: #00a32a; }
#aura-types-notice.notice-error   { border-left-color: #d63638; }
#aura-types-notice p { color: var(--at-text); margin: .5em 0; }

/* ── Modal ───────────────────────────────────────────────────────── */
.at-modal-wrap {
    position: fixed; inset: 0; z-index: 160000;
    display: flex; align-items: flex-start; justify-content: center;
    padding-top: 60px;
    isolation: isolate;
    opacity: 1 !important;
}
.at-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 0;
    background: rgba(15, 23, 42, .48);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
}
.at-modal-box {
    position: relative; z-index: 2;
    background: #ffffff !important;
    background-color: var(--at-modal-surface, #ffffff) !important;
    background-image: none !important;
    border: 1px solid var(--at-border);
    border-radius: 6px;
    box-shadow: 0 8px 32px rgba(0,0,0,.28);
    width: 520px; max-width: 95vw; max-height: 90vh;
    display: flex; flex-direction: column; overflow: hidden;
    opacity: 1 !important;
    -webkit-backdrop-filter: none !important;
    backdrop-filter: none !important;
    filter: none !important;
    mix-blend-mode: normal !important;
}
.at-modal-box * { opacity: 1; }
body.at-modal-open { overflow: hidden; }
.at-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px;
    background: var(--at-header-bg);
    color: var(--at-header-txt);
    border-bottom: 1px solid var(--at-border);
}
.at-modal-header h2 { margin: 0; font-size: 16px; font-weight: 700; color: var(--at-header-txt); }
.at-modal-close-btn {
    background: none; border: none; cursor: pointer; border-radius: 4px;
    color: rgba(255,255,255,.7); font-size: 20px; line-height: 1; padding: 4px 6px;
    transition: all .2s ease-out;
}
.at-modal-close-btn:hover { color: #fff; background: rgba(255,255,255,.1); }
.at-modal-body {
    padding: 28px;
    overflow-y: auto;
    flex: 1;
    background: var(--at-modal-surface, #ffffff) !important;
    max-height: calc(90vh - 180px);
}
.at-modal-footer {
    padding: 16px 28px;
    border-top: 1px solid var(--at-border);
    background: var(--at-modal-surface, #ffffff) !important;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* ── Campos de formulario ────────────────────────────────────────── */
.at-field { margin-bottom: 18px; position: relative; }
.at-field label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 6px;
    color: var(--at-text);
    letter-spacing: .3px;
}
.at-field label .required { color: #d63638; font-weight: 700; margin-left: 2px; }
.at-field input[type="text"],
.at-field input[type="number"],
.at-field textarea {
    width: 100%;
    box-sizing: border-box;
    background: var(--at-input-bg);
    border: 1.5px solid var(--at-border);
    color: var(--at-text);
    border-radius: 4px;
    padding: 8px 10px;
    font-size: 13px;
    font-family: inherit;
    transition: all .25s cubic-bezier(.4, 0, .2, 1);
}
.at-field input[type="text"]:focus,
.at-field input[type="number"]:focus,
.at-field textarea:focus {
    outline: none;
    border-color: #2271b1;
    box-shadow: 0 0 0 3px rgba(34, 113, 177, .1);
    background: var(--at-input-bg);
}
.at-field input[readonly] {
    background: var(--at-input-ro);
    color: var(--at-muted);
    cursor: default;
    border-color: var(--at-border);
}
.at-field input[readonly]:focus {
    box-shadow: none;
    border-color: var(--at-border);
}
.at-field textarea {
    resize: vertical;
    min-height: 70px;
}
.at-field .description {
    font-size: 12px;
    color: var(--at-muted);
    margin-top: 4px;
    line-height: 1.4;
}
.at-field.has-error input,
.at-field.has-error textarea {
    border-color: #d63638;
    box-shadow: 0 0 0 3px rgba(214, 54, 56, .1);
}
.at-field.has-error .description {
    color: #d63638;
    font-weight: 500;
}
.at-field-row {
    display: flex;
    gap: 16px;
    margin-bottom: 18px;
}
.at-field-row .at-field {
    flex: 1;
    margin-bottom: 0;
}
.at-field-row .at-field-sm {
    flex: 0 0 120px;
}
.at-checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: var(--at-text);
    font-size: 13px;
    padding: 4px 0;
    transition: color .2s ease-out;
}
.at-checkbox-label:hover { color: #2271b1; }
.at-checkbox-label input[type="checkbox"] {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

/* ── Botones mejorados ────────────────────────────────────────── */
#aura-types-form .button {
    border-radius: 4px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    transition: all .2s ease-out;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
#aura-types-form .button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
}
#aura-types-form .button:active {
    transform: translateY(0);
}
#aura-types-form .button.button-primary {
    background: #2271b1;
    color: #fff;
}
#aura-types-form .button.button-primary:hover {
    background: #135e96;
}
#aura-types-form .button-secondary {
    background: #f3f4f6;
    color: #1d2327;
    border: 1px solid #dcdcde;
}
#aura-types-form .button-secondary:hover {
    background: #e5e7eb;
    color: #000;
}

/* ── Color Picker ────────────────────────────────────────── */
.aura-color-picker {
    cursor: color-picker !important;\n}
.wp-color-result { margin: 0; }\n.wp-color-result::before {\n    border-radius: 4px;\n}\n\n/* ── Validación ────────────────────────────────────────── */\n#aura-types-form input:invalid:not(:placeholder-shown) {\n    border-color: #d63638;\n    box-shadow: 0 0 0 3px rgba(214, 54, 56, .1);\n}\n#aura-types-form input:valid:not(:placeholder-shown) {\n    border-color: #11a861;\n}\n\n/* ── Encabezado del formulario ────────────────────────────────────────── */\n.at-modal-body > .at-field:first-child {\n    margin-top: 0;\n}\n.at-modal-body .at-field {\n    animation: fadeInUp .3s ease-out;\n}\n@keyframes fadeInUp {\n    from {\n        opacity: 0;\n        transform: translateY(8px);\n    }\n    to {\n        opacity: 1;\n        transform: translateY(0);\n    }\n}\n\n/* ── Estado deshabilitado ────────────────────────────────────────── */\n#aura-types-form .button:disabled {\n    opacity: .5;\n    cursor: not-allowed;\n    transform: none !important;\n}\n\n/* ── Loading state ────────────────────────────────────────── */\n#aura-types-form .button.is-loading::before {\n    display: inline-block;\n    width: 14px;\n    height: 14px;\n    border: 2px solid currentColor;\n    border-right-color: transparent;\n    border-radius: 50%;\n    animation: spin .6s linear infinite;\n    margin-right: 4px;\n}\n@keyframes spin {\n    to { transform: rotate(360deg); }\n}\n

@media (max-width: 600px) {
    .at-modal-body { padding: 20px; }
    .at-modal-footer { padding: 12px 20px; }
    .at-field-row {
        flex-direction: column;
        gap: 0;
        margin-bottom: 0;
    }
    .at-field-row .at-field { flex: 1; }
    .at-field-row .at-field-sm { flex: 1; }
    .at-filters-bar input[type="search"] { width: 100%; }
    #aura-types-form .button { width: 100%; justify-content: center; }
}
</style>

<div class="wrap" id="aura-types-app">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-tag" style="font-size:26px;vertical-align:middle;margin-right:6px;"></span>
        <?php esc_html_e( 'Tipos de Área', 'aura-suite' ); ?>
    </h1>
    <button type="button" id="aura-types-new-btn" class="page-title-action">
        + <?php esc_html_e( 'Nuevo Tipo', 'aura-suite' ); ?>
    </button>
    <hr class="wp-header-end">

    <div id="aura-types-notice" style="display:none;" role="alert"></div>

    <!-- Barra de filtros -->
    <div class="at-filters-bar">
        <input type="search" id="at-search" placeholder="<?php esc_attr_e( 'Buscar tipo...', 'aura-suite' ); ?>">
        <button type="button" id="at-clear-btn" class="button">
            <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
        </button>
    </div>

    <!-- Tabla DataTables -->
    <div class="at-table-wrap">
        <table id="aura-types-table" class="wp-list-table widefat" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Color', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Predeterminado', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Áreas', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div><!-- /wrap -->

<!-- Modal Crear / Editar -->
<div id="aura-types-modal" class="at-modal-wrap" style="display:none;" aria-modal="true" role="dialog">
    <div class="at-modal-backdrop aura-modal-close"></div>
    <div class="at-modal-box">
        <div class="at-modal-header">
            <h2 id="aura-types-modal-title"><?php esc_html_e( 'Nuevo Tipo', 'aura-suite' ); ?></h2>
            <button type="button" class="at-modal-close-btn aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <form id="aura-types-form" autocomplete="off" class="at-modal-body">
            <input type="hidden" id="aura-type-id" name="id" value="0">

            <div class="at-field">
                <label for="aura-type-name">
                    <?php esc_html_e( 'Nombre', 'aura-suite' ); ?>
                    <span class="required" title="Campo requerido">*</span>
                </label>
                <input type="text" id="aura-type-name" name="name" maxlength="100" required
                       placeholder="<?php esc_attr_e( 'Ej. Ministerio, Célula...', 'aura-suite' ); ?>"
                       aria-label="<?php esc_attr_e( 'Nombre del tipo de área', 'aura-suite' ); ?>">
                <span class="description"><?php esc_html_e( 'Nombre único para identificar este tipo de área.', 'aura-suite' ); ?></span>
            </div>

            <div class="at-field">
                <label for="aura-type-slug">
                    <?php esc_html_e( 'Slug', 'aura-suite' ); ?>
                    <span style="font-size:11px;color:var(--at-muted);font-weight:400;">(<?php esc_html_e( 'auto', 'aura-suite' ); ?>)</span>
                </label>
                <input type="text" id="aura-type-slug" name="slug" readonly 
                       aria-label="<?php esc_attr_e( 'Identificador único generado automáticamente', 'aura-suite' ); ?>">
                <span class="description"><?php esc_html_e( '🔄 Se genera automáticamente basado en el nombre.', 'aura-suite' ); ?></span>
            </div>

            <div class="at-field">
                <label for="aura-type-description">
                    <?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                </label>
                <textarea id="aura-type-description" name="description" rows="3"
                          placeholder="<?php esc_attr_e( 'Agrega detalles del tipo de área... (opcional)', 'aura-suite' ); ?>"
                          aria-label="<?php esc_attr_e( 'Descripción del tipo de área', 'aura-suite' ); ?>"></textarea>
                <span class="description"><?php esc_html_e( 'Información adicional para clasificar mejor las áreas.', 'aura-suite' ); ?></span>
            </div>

            <div class="at-field-row">
                <div class="at-field">
                    <label for="aura-type-color">
                        <?php esc_html_e( 'Color de etiqueta', 'aura-suite' ); ?>
                        <span style="font-size:11px;color:var(--at-muted);font-weight:400;"><?php esc_html_e( '(opcional)', 'aura-suite' ); ?></span>
                    </label>
                    <input type="text" id="aura-type-color" name="color" value="#e0e7ff" class="aura-color-picker"
                           aria-label="<?php esc_attr_e( 'Color para las etiquetas de este tipo', 'aura-suite' ); ?>">
                    <span class="description"><?php esc_html_e( '🎨 Selecciona un color para identificar este tipo.', 'aura-suite' ); ?></span>
                </div>
                <div class="at-field at-field-sm">
                    <label for="aura-type-sort">
                        <?php esc_html_e( 'Orden', 'aura-suite' ); ?>
                    </label>
                    <input type="number" id="aura-type-sort" name="sort_order" value="0" min="0" max="9999" class="small-text"
                           aria-label="<?php esc_attr_e( 'Orden de aparición', 'aura-suite' ); ?>">
                    <span class="description"><?php esc_html_e( '↕️ Orden de aparición', 'aura-suite' ); ?></span>
                </div>
            </div>

            <div class="at-field">
                <label class="at-checkbox-label">
                    <input type="checkbox" id="aura-type-default" name="is_default" value="1"
                           aria-label="<?php esc_attr_e( 'Usar como tipo predeterminado', 'aura-suite' ); ?>">
                    <span>
                        <?php esc_html_e( 'Usar como tipo predeterminado para nuevas áreas', 'aura-suite' ); ?>
                    </span>
                </label>
                <span class="description"><?php esc_html_e( '⭐ El primer tipo de área que se sugiere al crear nuevas áreas.', 'aura-suite' ); ?></span>
            </div>
        </form>

        <div class="at-modal-footer">
            <button type="button" class="button button-secondary aura-modal-close">
                <span class="dashicons dashicons-no-alt" style="line-height:28px;"></span>
                <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
            </button>
            <button type="submit" form="aura-types-form" class="button button-primary">
                <span class="dashicons dashicons-yes-alt" style="line-height:28px;"></span>
                <?php esc_html_e( 'Guardar tipo', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    var CFG = <?php echo wp_json_encode( [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => $nonce,
        'i18n'    => [
            'nuevo'         => __( 'Nuevo Tipo', 'aura-suite' ),
            'editar'        => __( 'Editar Tipo', 'aura-suite' ),
            'confirmDelete' => __( '¿Eliminar este tipo de área? Esta acción no se puede deshacer.', 'aura-suite' ),
            'noDelete'      => __( 'No se puede eliminar: este tipo tiene áreas asociadas.', 'aura-suite' ),
            'editLabel'     => __( 'Editar', 'aura-suite' ),
            'deleteLabel'   => __( 'Eliminar', 'aura-suite' ),
            'nameRequired'  => __( 'El nombre es requerido.', 'aura-suite' ),
            'saveError'     => __( 'Error al guardar.', 'aura-suite' ),
            'connError'     => __( 'Error de conexión.', 'aura-suite' ),
        ],
    ] ); ?>;

    var dtInstance = null;

    /* ------------------------------------------------------------------
     * Escape HTML
     * ------------------------------------------------------------------ */
    function escHtml(str) {
        if (!str) { return ''; }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ------------------------------------------------------------------
     * Inicializar DataTable — estándar PRD §5.6
     * ------------------------------------------------------------------ */
    function initTable() {
        dtInstance = $('#aura-types-table').DataTable({
            responsive:  true,
            searching:   false,
            dom:         '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            pageLength:  20,
            language: {
                info:         '_TOTAL_ tipos',
                infoEmpty:    '0 tipos',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu:   'Mostrar _MENU_ por página',
                zeroRecords:  'No se encontraron tipos.',
                paginate:     { first: '«', last: '»', previous: '‹', next: '›' }
            },
            columns: [
                {
                    data: 'color',
                    orderable: false,
                    responsivePriority: 10000,
                    render: function(v) {
                        return '<span style="display:inline-block;width:22px;height:22px;border-radius:50%;'
                             + 'background:' + escHtml(v) + ';border:1px solid rgba(0,0,0,.15);vertical-align:middle;"></span>';
                    }
                },
                {
                    data: 'name',
                    responsivePriority: 1
                },
                {
                    data: 'slug',
                    responsivePriority: 2,
                    render: function(v) { return '<code>' + escHtml(v) + '</code>'; }
                },
                {
                    data: 'description',
                    responsivePriority: 10000,
                    render: function(v) {
                        return v ? escHtml(v) : '<span style="color:var(--at-muted,#646970);">—</span>';
                    }
                },
                {
                    data: 'is_default',
                    responsivePriority: 3,
                    className: 'dt-center',
                    render: function(v) {
                        return v
                            ? '<span class="at-default-badge"><span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;"></span><?php echo esc_js( __( 'Sí', 'aura-suite' ) ); ?></span>'
                            : '';
                    }
                },
                {
                    data: 'areas_count',
                    responsivePriority: 2,
                    className: 'dt-center',
                    render: function(v) {
                        var n = parseInt(v, 10);
                        return '<strong style="color:' + (n > 0 ? '#2271b1' : 'var(--at-muted,#646970)') + ';">' + n + '</strong>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    responsivePriority: 1,
                    className: 'dt-center',
                    render: function(row) {
                        var edit = '<button type="button" class="at-btn-action btn-edit-type" data-id="' + row.id + '" title="' + escHtml(CFG.i18n.editLabel) + '">'
                                 + '<span class="dashicons dashicons-edit"></span></button>';
                        var del  = '<button type="button" class="at-btn-action danger btn-delete-type" data-id="' + row.id + '" data-count="' + row.areas_count + '" title="' + escHtml(CFG.i18n.deleteLabel) + '">'
                                 + '<span class="dashicons dashicons-trash"></span></button>';
                        return '<div class="at-row-actions">' + edit + del + '</div>';
                    }
                }
            ]
        });

        loadTypes();
    }

    /* ------------------------------------------------------------------
     * Cargar tipos vía AJAX
     * ------------------------------------------------------------------ */
    function loadTypes() {
        $.post(CFG.ajaxUrl, { action: 'aura_areas_types_list', nonce: CFG.nonce }, function(res) {
            if (res.success) {
                dtInstance.clear().rows.add(res.data.data).draw();
            } else {
                showNotice('error', (res.data && res.data.message) || 'Error al cargar tipos.');
            }
        });
    }

    /* ------------------------------------------------------------------
     * Filtro de búsqueda (client-side)
     * ------------------------------------------------------------------ */
    $(document).on('input', '#at-search', function() {
        // La búsqueda nativa está desactivada; filtrar manualmente
        var val = $(this).val().toLowerCase().trim();
        dtInstance.rows().every(function() {
            var d = this.data();
            var match = (d.name + ' ' + d.slug + ' ' + (d.description || '')).toLowerCase().indexOf(val) !== -1;
            $(this.node()).toggle(match);
        });
        // Actualizar info
        dtInstance.draw(false);
    });

    $('#at-clear-btn').on('click', function() {
        $('#at-search').val('');
        dtInstance.rows().every(function() { $(this.node()).show(); });
        dtInstance.draw(false);
    });

    /* ------------------------------------------------------------------
     * Auto-generar slug desde nombre
     * ------------------------------------------------------------------ */
    $(document).on('input', '#aura-type-name', function() {
        var slug = $(this).val()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
        $('#aura-type-slug').val(slug);
    });

    /* ------------------------------------------------------------------
     * Abrir / cerrar modal
     * ------------------------------------------------------------------ */
    function openModal(data) {
        var isEdit = !!(data && data.id);
        $('#aura-types-modal-title').text(isEdit ? CFG.i18n.editar : CFG.i18n.nuevo);
        $('#aura-type-id').val(isEdit ? data.id : 0);
        $('#aura-type-name').val(isEdit ? data.name : '');
        $('#aura-type-slug').val(isEdit ? data.slug : '');
        $('#aura-type-description').val(isEdit ? data.description : '');
        $('#aura-type-sort').val(isEdit ? data.sort_order : 0);
        $('#aura-type-default').prop('checked', isEdit ? !!data.is_default : false);

        var targetColor = isEdit ? data.color : '#e0e7ff';
        var $cp = $('#aura-type-color');
        $cp.val(targetColor);
        if (typeof $cp.wpColorPicker === 'function') {
            if ($cp.data('wp-color-picker-initialized')) {
                $cp.wpColorPicker('color', targetColor);
            } else {
                $cp.wpColorPicker({ defaultColor: '#e0e7ff' });
                $cp.data('wp-color-picker-initialized', true);
            }
        }

        var $modal = $('#aura-types-modal');
        $modal.stop(true, true).css({ display: 'flex', opacity: '1' }).show();
        $('body').addClass('at-modal-open');
        setTimeout(function() { $('#aura-type-name').trigger('focus'); }, 210);
    }

    function closeModal() {
        var $modal = $('#aura-types-modal');
        $modal.stop(true, true).css('opacity', '1').hide();
        $('body').removeClass('at-modal-open');
    }

    /* ------------------------------------------------------------------
     * Eventos
     * ------------------------------------------------------------------ */
    $('#aura-types-new-btn').on('click', function() { openModal(null); });

    $(document).on('click', '.btn-edit-type', function() {
        var id = $(this).data('id');
        $.post(CFG.ajaxUrl, { action: 'aura_areas_types_get', nonce: CFG.nonce, id: id }, function(res) {
            if (res.success) { openModal(res.data); }
            else { showNotice('error', (res.data && res.data.message) || 'Error'); }
        });
    });

    $(document).on('click', '.btn-delete-type', function() {
        var id    = $(this).data('id');
        var count = parseInt($(this).data('count'), 10);
        if (count > 0) { showNotice('error', CFG.i18n.noDelete); return; }
        if (!confirm(CFG.i18n.confirmDelete)) { return; }
        $.post(CFG.ajaxUrl, { action: 'aura_areas_types_delete', nonce: CFG.nonce, id: id }, function(res) {
            if (res.success) { showNotice('success', res.data.message); loadTypes(); }
            else { showNotice('error', (res.data && res.data.message) || 'Error al eliminar.'); }
        });
    });

    $(document).on('click', '.aura-modal-close', function() { closeModal(); });
    $(document).on('keydown', function(e) { if (e.key === 'Escape') { closeModal(); } });

    /* ------------------------------------------------------------------
     * Submit
     * ------------------------------------------------------------------ */
    $('#aura-types-form').on('submit', function(e) {
        e.preventDefault();

        var $cp = $('#aura-type-color');
        var colorVal = $cp.val();
        if (typeof $cp.wpColorPicker === 'function' && $cp.data('wp-color-picker-initialized')) {
            colorVal = $cp.wpColorPicker('color') || colorVal;
        }

        var data = {
            action:      'aura_areas_types_save',
            nonce:       CFG.nonce,
            id:          $('#aura-type-id').val(),
            name:        $('#aura-type-name').val().trim(),
            description: $('#aura-type-description').val(),
            color:       colorVal,
            sort_order:  $('#aura-type-sort').val(),
            is_default:  $('#aura-type-default').is(':checked') ? 1 : 0
        };

        if (!data.name) { showNotice('error', CFG.i18n.nameRequired); return; }

        var $btn = $('[type=submit][form=aura-types-form]').add($(this).find('[type=submit]')).first();
        $btn.prop('disabled', true);

        $.post(CFG.ajaxUrl, data, function(res) {
            $btn.prop('disabled', false);
            if (res.success) { showNotice('success', res.data.message); closeModal(); loadTypes(); }
            else { showNotice('error', (res.data && res.data.message) || CFG.i18n.saveError); }
        }).fail(function() {
            $btn.prop('disabled', false);
            showNotice('error', CFG.i18n.connError);
        });
    });

    /* ------------------------------------------------------------------
     * Aviso
     * ------------------------------------------------------------------ */
    function showNotice(type, msg) {
        var $n = $('#aura-types-notice');
        $n.removeClass('notice-success notice-error')
          .addClass('notice ' + (type === 'success' ? 'notice-success' : 'notice-error'))
          .html('<p>' + escHtml(msg) + '</p>')
          .show();
        clearTimeout($n.data('timer'));
        $n.data('timer', setTimeout(function() { $n.fadeOut(300); }, 6000));
    }

    /* ------------------------------------------------------------------
     * Boot
     * ------------------------------------------------------------------ */
    $(function() { initTable(); });

})(jQuery);
</script>
