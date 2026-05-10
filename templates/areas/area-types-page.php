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
    position: fixed; inset: 0; z-index: 100000;
    display: flex; align-items: flex-start; justify-content: center;
    padding-top: 60px;
}
.at-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.55); }
.at-modal-box {
    position: relative; z-index: 1;
    background: var(--at-surface);
    border: 1px solid var(--at-border);
    border-radius: 6px;
    box-shadow: 0 8px 32px rgba(0,0,0,.28);
    width: 520px; max-width: 95vw; max-height: 90vh;
    display: flex; flex-direction: column; overflow: hidden;
}
.at-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px;
    background: var(--at-header-bg);
    color: var(--at-header-txt);
    border-bottom: 1px solid var(--at-border);
}
.at-modal-header h2 { margin: 0; font-size: 15px; color: var(--at-header-txt); }
.at-modal-close-btn {
    background: none; border: none; cursor: pointer;
    color: rgba(255,255,255,.6); font-size: 20px; line-height: 1; padding: 2px;
}
.at-modal-close-btn:hover { color: #fff; }
.at-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.at-modal-footer {
    padding: 12px 18px;
    border-top: 1px solid var(--at-border);
    background: var(--at-footer-bg);
    display: flex; justify-content: flex-end; gap: 8px;
}

/* ── Campos de formulario ────────────────────────────────────────── */
.at-field { margin-bottom: 14px; }
.at-field label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; color: var(--at-text); }
.at-field input[type="text"],
.at-field input[type="number"],
.at-field textarea {
    width: 100%; box-sizing: border-box;
    background: var(--at-input-bg);
    border: 1px solid var(--at-border);
    color: var(--at-text);
    border-radius: 3px;
    padding: 6px 8px;
    font-size: 13px;
}
.at-field input[readonly] {
    background: var(--at-input-ro);
    color: var(--at-muted);
    cursor: default;
}
.at-field textarea { resize: vertical; }
.at-field .description { font-size: 12px; color: var(--at-muted); margin-top: 3px; }
.at-field-row { display: flex; gap: 14px; }
.at-field-row .at-field { flex: 1; }
.at-field-row .at-field-sm { flex: 0 0 110px; }
.at-checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--at-text); font-size: 13px; }

@media (max-width: 600px) {
    .at-field-row { flex-direction: column; }
    .at-field-row .at-field-sm { flex: 1; }
    .at-filters-bar input[type="search"] { width: 100%; }
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
                <label for="aura-type-name"><?php esc_html_e( 'Nombre', 'aura-suite' ); ?> <span style="color:#d63638;">*</span></label>
                <input type="text" id="aura-type-name" name="name" maxlength="100"
                       placeholder="<?php esc_attr_e( 'Ej. Ministerio, Célula...', 'aura-suite' ); ?>">
            </div>

            <div class="at-field">
                <label for="aura-type-slug"><?php esc_html_e( 'Slug (auto)', 'aura-suite' ); ?></label>
                <input type="text" id="aura-type-slug" name="slug" readonly>
                <span class="description"><?php esc_html_e( 'Generado automáticamente desde el nombre.', 'aura-suite' ); ?></span>
            </div>

            <div class="at-field">
                <label for="aura-type-description"><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></label>
                <textarea id="aura-type-description" name="description" rows="3"
                          placeholder="<?php esc_attr_e( 'Descripción opcional...', 'aura-suite' ); ?>"></textarea>
            </div>

            <div class="at-field-row">
                <div class="at-field">
                    <label for="aura-type-color"><?php esc_html_e( 'Color de etiqueta', 'aura-suite' ); ?></label>
                    <input type="text" id="aura-type-color" name="color" value="#e0e7ff" class="aura-color-picker">
                </div>
                <div class="at-field at-field-sm">
                    <label for="aura-type-sort"><?php esc_html_e( 'Orden', 'aura-suite' ); ?></label>
                    <input type="number" id="aura-type-sort" name="sort_order" value="0" min="0" max="9999" class="small-text">
                </div>
            </div>

            <div class="at-field">
                <label class="at-checkbox-label">
                    <input type="checkbox" id="aura-type-default" name="is_default" value="1">
                    <?php esc_html_e( 'Usar como tipo predeterminado para nuevas áreas', 'aura-suite' ); ?>
                </label>
            </div>
        </form>

        <div class="at-modal-footer">
            <button type="submit" form="aura-types-form" class="button button-primary">
                <span class="dashicons dashicons-saved" style="line-height:28px;"></span>
                <?php esc_html_e( 'Guardar', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button aura-modal-close">
                <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
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

        $('#aura-types-modal').fadeIn(200);
        setTimeout(function() { $('#aura-type-name').trigger('focus'); }, 210);
    }

    function closeModal() {
        $('#aura-types-modal').fadeOut(200);
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
