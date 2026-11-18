<?php
/**
 * Template: Catálogo de Libros
 * Fase 2 — DataTables Responsive con 8 columnas + filtros
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_library_view_catalog' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-business-suite' ) );
}

global $wpdb;

// Categorías únicas para el filtro
$categories = $wpdb->get_col(
    "SELECT DISTINCT category FROM {$wpdb->prefix}aura_library_books
     WHERE deleted_at IS NULL AND category IS NOT NULL AND category != ''
     ORDER BY category ASC"
) ?: [];

// Áreas para el filtro
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: [];

$can_create = current_user_can( 'aura_library_create' )  || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_library_edit' )    || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_library_delete' )  || current_user_can( 'manage_options' );
$can_loan   = current_user_can( 'aura_library_loan_create' ) || current_user_can( 'manage_options' );
?>
<div class="aura-app-wrapper aura-library-books-page">
    <div class="wrap aura-app-context">

        <?php
        $header_actions = [];
        if ( $can_create ) {
            $header_actions[] = [
                'label' => __( 'Nuevo Libro', 'aura-suite' ),
                'url'   => '#',
                'class' => 'btn btn-shimmer btn-indigo btn-lift',
                'icon'  => 'dashicons-plus-alt',
                'attr'  => 'id="aura-lib-btn-new-book"',
            ];
        }

        Aura_UI::render_page_header([
            'title'    => __( 'Catálogo de Libros', 'aura-suite' ),
            'subtitle' => __( 'Gestión bibliotecaria, clasificación Dewey, inventario físico y control de disponibilidad.', 'aura-suite' ),
            'icon'     => 'dashicons-book-alt',
            'badges'   => [
                ['label' => sprintf( __('%d categorías', 'aura-suite'), count( $categories ) ), 'variant' => 'blue', 'tooltip' => __( 'Categorías catalogadas', 'aura-suite' )],
            ],
            'actions'  => $header_actions,
        ]);
        ?>

        <!-- Filtros (Glass Card + Bootstrap 5.3 Input Groups) -->
        <div class="aura-glass-card aura-filters-card aura-lib-filters-card">
            <div class="aura-lib-filters-grid">
                <div class="aura-lib-filter-search">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">🔍</span>
                        <input type="search" id="aura-lib-search" class="form-control"
                               placeholder="<?php esc_attr_e( 'Buscar por título, autor, ISBN…', 'aura-suite' ); ?>">
                    </div>
                </div>

                <div class="aura-lib-filter-select">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">📚</span>
                        <select id="aura-lib-filter-dewey" class="form-control" aria-label="<?php esc_attr_e( 'Clase Dewey', 'aura-suite' ); ?>">
                            <option value=""><?php esc_html_e( 'Todas las clases Dewey', 'aura-suite' ); ?></option>
                            <option value="0"><?php esc_html_e( '000 — Informática y Generalidades', 'aura-suite' ); ?></option>
                            <option value="1"><?php esc_html_e( '100 — Filosofía y Psicología', 'aura-suite' ); ?></option>
                            <option value="2"><?php esc_html_e( '200 — Religión', 'aura-suite' ); ?></option>
                            <option value="3"><?php esc_html_e( '300 — Ciencias Sociales', 'aura-suite' ); ?></option>
                            <option value="4"><?php esc_html_e( '400 — Lengua y Lingüística', 'aura-suite' ); ?></option>
                            <option value="5"><?php esc_html_e( '500 — Ciencias Puras', 'aura-suite' ); ?></option>
                            <option value="6"><?php esc_html_e( '600 — Tecnología Aplicada', 'aura-suite' ); ?></option>
                            <option value="7"><?php esc_html_e( '700 — Artes y Recreación', 'aura-suite' ); ?></option>
                            <option value="8"><?php esc_html_e( '800 — Literatura', 'aura-suite' ); ?></option>
                            <option value="9"><?php esc_html_e( '900 — Historia y Geografía', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                <?php if ( ! empty( $categories ) ) : ?>
                <div class="aura-lib-filter-select">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">🏷️</span>
                        <select id="aura-lib-filter-category" class="form-control" aria-label="<?php esc_attr_e( 'Categoría', 'aura-suite' ); ?>">
                            <option value=""><?php esc_html_e( 'Todas las categorías', 'aura-suite' ); ?></option>
                            <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="aura-lib-filter-select">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">🟢</span>
                        <select id="aura-lib-filter-status" class="form-control" aria-label="<?php esc_attr_e( 'Estado', 'aura-suite' ); ?>">
                            <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                            <option value="available"><?php      esc_html_e( 'Disponible',    'aura-suite' ); ?></option>
                            <option value="unavailable"><?php    esc_html_e( 'Sin stock',     'aura-suite' ); ?></option>
                            <option value="reference_only"><?php esc_html_e( 'Solo consulta', 'aura-suite' ); ?></option>
                            <option value="lost"><?php           esc_html_e( 'Perdido',       'aura-suite' ); ?></option>
                            <option value="withdrawn"><?php      esc_html_e( 'Retirado',      'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                <?php if ( ! empty( $areas ) ) : ?>
                <div class="aura-lib-filter-select">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">🏢</span>
                        <select id="aura-lib-filter-area" class="form-control" aria-label="<?php esc_attr_e( 'Área', 'aura-suite' ); ?>">
                            <option value="0"><?php esc_html_e( 'Todas las áreas', 'aura-suite' ); ?></option>
                            <?php foreach ( $areas as $area ) : ?>
                            <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="aura-lib-filter-actions">
                    <button type="button" id="aura-lib-filter-apply" class="btn btn-shimmer btn-indigo btn-lift"
                            data-tooltip="<?php esc_attr_e( 'Aplicar los filtros seleccionados al catálogo', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-search"></span>
                        <span><?php esc_html_e( 'Filtrar', 'aura-suite' ); ?></span>
                    </button>
                    <button type="button" id="aura-lib-filter-clear" class="btn btn-glass btn-lift"
                            data-tooltip="<?php esc_attr_e( 'Restablecer búsqueda y filtros activos', 'aura-suite' ); ?>">
                        <span><?php esc_html_e( 'Limpiar', 'aura-suite' ); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla fluida DataTables + Mobile Responsive Cards -->
        <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
            <table id="aura-lib-books-table" class="dataTable aura-table display aura-lib-responsive-table" style="width:100%">
                <thead>
                    <tr>
                        <th class="aura-lib-col-expand" style="width:44px;text-align:center;" aria-label="<?php esc_attr_e( 'Expandir', 'aura-suite' ); ?>">
                            <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Haz clic en + en cualquier libro para desplegar toda su información en la tabla.', 'aura-suite' ); ?>">+</span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Portada', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Pasa el cursor sobre la portada para verla en tamaño completo.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Título', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Título principal, subtítulo y ficha rápida al pasar el cursor.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Autor', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Autor(es) registrados en la ficha bibliográfica.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Estado', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Disponibilidad actual del título para préstamos o consulta.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Copias', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Ejemplares disponibles sobre el inventario físico total.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Dewey', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Código de clasificación decimal Dewey asignado al libro.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Categoría', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Categoría y temática general del ejemplar.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Ubicación', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Sala física y código de estantería donde reposa.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Acciones', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Ver ficha 360°, editar ficha, eliminar o reservar ejemplar.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="adp-footer">
            <div>
                Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
            </div>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<?php
$books_js_ver  = file_exists( AURA_PLUGIN_DIR . 'assets/js/library-books.js' ) ? AURA_VERSION . '.' . filemtime( AURA_PLUGIN_DIR . 'assets/js/library-books.js' ) : AURA_VERSION;
$books_css_ver = file_exists( AURA_PLUGIN_DIR . 'assets/css/library-books.css' ) ? AURA_VERSION . '.' . filemtime( AURA_PLUGIN_DIR . 'assets/css/library-books.css' ) : AURA_VERSION;

wp_enqueue_script(
    'html5-qrcode',
    'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js',
    [],
    '2.3.8', true
);
wp_enqueue_script(
    'qrcodejs',
    'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
    [],
    '1.0.0', true
);
wp_enqueue_script(
    'aura-lib-books',
    AURA_PLUGIN_URL . 'assets/js/library-books.js',
    [ 'jquery', 'datatables-responsive-js', 'aura-ui-core', 'html5-qrcode' ],
    $books_js_ver, true
);
wp_enqueue_style(
    'aura-lib-books-css',
    AURA_PLUGIN_URL . 'assets/css/library-books.css',
    [ 'aura-design-system' ], $books_css_ver
);
// Media uploader para modal de form
wp_enqueue_media();
?>

<!-- Modal: Nuevo / Editar Libro -->
<div id="aura-lib-book-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-modal-title" aria-hidden="true">
    <div class="aura-modal-container aura-lib-modal-container aura-lib-modal-large">
        <div class="aura-modal-header">
            <div>
                <h2 id="aura-lib-modal-title"><?php esc_html_e( 'Nuevo Libro', 'aura-business-suite' ); ?></h2>
            </div>
            <button type="button" class="aura-modal-close aura-lib-modal-close"
                    data-modal-close="aura-lib-book-modal"
                    aria-label="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body aura-lib-modal-body" id="aura-lib-modal-body">
            <?php include AURA_PLUGIN_DIR . 'templates/library/book-form.php'; ?>
        </div>
    </div>
</div>

<!-- Modal: Detalle del Libro -->
<div id="aura-lib-detail-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-detail-title" aria-hidden="true" data-async-modal="true">
    <div class="aura-modal-container aura-lib-modal-container aura-lib-modal-large">
        <div class="aura-modal-header">
            <div>
                <h2 id="aura-lib-detail-title"><?php esc_html_e( 'Detalle del Libro', 'aura-business-suite' ); ?></h2>
            </div>
            <button type="button" class="aura-modal-close aura-lib-modal-close"
                    data-modal-close="aura-lib-detail-modal"
                    aria-label="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body aura-lib-modal-body" id="aura-lib-detail-body">
            <p class="aura-lib-detail-empty"><?php esc_html_e( 'Cargando información del libro…', 'aura-business-suite' ); ?></p>
        </div>
        <div class="aura-modal-footer">
            <?php if ( $can_edit ) : ?>
            <button type="button" class="btn btn-indigo btn-lift aura-lib-detail-edit-btn" id="aura-lib-detail-edit-btn" style="display:none;" data-id="0">
                <span class="dashicons dashicons-edit"></span>
                <span><?php esc_html_e( 'Editar Libro', 'aura-business-suite' ); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close" data-modal-close="aura-lib-detail-modal">
                <?php esc_html_e( 'Cerrar', 'aura-business-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<?php
$areas_js = array_map( fn( $a ) => [ 'id' => $a->id, 'name' => $a->name ], $areas );

$js_data = wp_json_encode( [
    'ajaxurl'    => admin_url( 'admin-ajax.php' ),
    'nonce'      => wp_create_nonce( 'aura_library_nonce' ),
    'can_create' => $can_create,
    'can_edit'   => $can_edit,
    'can_delete' => $can_delete,
    'can_loan'   => $can_loan,
    'areas'      => $areas_js,
    'txt'        => [
        'loading'        => __( 'Cargando…',                                          'aura-business-suite' ),
        'no_results'     => __( 'No se encontraron libros.',                          'aura-business-suite' ),
        'confirm_delete' => __( '¿Eliminar este libro? Esta acción no se puede deshacer.', 'aura-business-suite' ),
        'error'          => __( 'Error al procesar la solicitud.',                    'aura-business-suite' ),
        'deleted'        => __( 'Libro eliminado correctamente.',                     'aura-business-suite' ),
        'saved'          => __( 'Libro guardado correctamente.',                      'aura-business-suite' ),
        'page_of'        => __( 'Página %1$s de %2$s',                               'aura-business-suite' ),
        'n_items'        => __( '%s libros',                                          'aura-business-suite' ),
        'new_title'      => __( 'Nuevo Libro',                                        'aura-business-suite' ),
        'edit_title'     => __( 'Editar Libro',                                       'aura-business-suite' ),
        'status_labels'  => [
            'available'      => __( 'Disponible',    'aura-business-suite' ),
            'unavailable'    => __( 'Sin stock',     'aura-business-suite' ),
            'reference_only' => __( 'Solo consulta', 'aura-business-suite' ),
            'lost'           => __( 'Perdido',       'aura-business-suite' ),
            'withdrawn'      => __( 'Retirado',      'aura-business-suite' ),
        ],
        'loan_status_labels' => [
            'active'   => __( 'Activo',    'aura-business-suite' ),
            'returned' => __( 'Devuelto',  'aura-business-suite' ),
            'overdue'  => __( 'Vencido',   'aura-business-suite' ),
            'lost'     => __( 'Perdido',   'aura-business-suite' ),
            'extended' => __( 'Extendido', 'aura-business-suite' ),
        ],
    ],
] );
?>
<script>var auraLibraryBooks = <?php echo $js_data; // phpcs:ignore WordPress.Security.EscapeOutput ?>;</script>

<?php if ( $can_loan ) : ?>
<!-- Modal: Reservar Libro (Fase 4) — aparece cuando available_copies == 0 -->
<div id="aura-lib-reserve-modal" class="aura-modal-overlay aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-reserve-modal-title" aria-hidden="true">
    <div class="aura-modal-container aura-lib-modal-container" style="max-width:480px;">
        <div class="aura-modal-header">
            <div>
                <h2 id="aura-lib-reserve-modal-title"><?php esc_html_e( 'Reservar Libro', 'aura-business-suite' ); ?></h2>
            </div>
            <button type="button" class="aura-modal-close aura-lib-modal-close"
                    data-modal-close="aura-lib-reserve-modal"
                    aria-label="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>">✕</button>
        </div>
        <form id="aura-lib-reserve-form" class="aura-modal-form-flex">
            <div class="aura-modal-body aura-lib-modal-body">
                <p class="aura-lib-reserve-summary">
                    <?php esc_html_e( 'Libro:', 'aura-business-suite' ); ?>
                    <strong id="aura-lib-reserve-book-title"></strong>
                </p>
                <input type="hidden" id="aura-lib-reserve-book-id">

                <div class="aura-lib-form-row">
                    <label for="aura-lib-reserve-notes"><?php esc_html_e( 'Notas (opcional)', 'aura-business-suite' ); ?></label>
                    <textarea id="aura-lib-reserve-notes" rows="3" class="form-control large-text"></textarea>
                </div>

                <p class="description">
                    <?php
                    $expire = absint( get_option( 'aura_library_reservation_expire_days', 7 ) );
                    printf(
                        /* translators: %d number of days */
                        esc_html__( 'Cuando el libro esté disponible, tendrás %d días para retirarlo antes de que la reserva expire.', 'aura-business-suite' ),
                        $expire
                    );
                    ?>
                </p>
            </div>
            <div class="aura-modal-footer aura-lib-modal-footer">
                <button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close" data-modal-close="aura-lib-reserve-modal">
                    <?php esc_html_e( 'Cancelar', 'aura-business-suite' ); ?>
                </button>
                <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="aura-lib-reserve-save">
                    <?php esc_html_e( 'Confirmar Reserva', 'aura-business-suite' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
