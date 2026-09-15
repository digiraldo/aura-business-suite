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
                'class' => 'aura-btn aura-btn-primary',
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

        <!-- Filtros (Glass Card) -->
        <div class="aura-glass-card" style="padding: 18px 20px; margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                <input type="search" id="aura-lib-search"
                       placeholder="<?php esc_attr_e( 'Buscar por título, autor, ISBN…', 'aura-suite' ); ?>"
                       style="flex: 1; min-width: 200px; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px;">

                <select id="aura-lib-filter-dewey" style="height: 38px; min-width: 170px; border-radius: 6px; border: 1px solid #cbd5e1;">
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

                <?php if ( ! empty( $categories ) ) : ?>
                <select id="aura-lib-filter-category" style="height: 38px; min-width: 150px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value=""><?php esc_html_e( 'Todas las categorías', 'aura-suite' ); ?></option>
                    <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <select id="aura-lib-filter-status" style="height: 38px; min-width: 140px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                    <option value="available"><?php      esc_html_e( 'Disponible',    'aura-suite' ); ?></option>
                    <option value="unavailable"><?php    esc_html_e( 'Sin stock',     'aura-suite' ); ?></option>
                    <option value="reference_only"><?php esc_html_e( 'Solo consulta', 'aura-suite' ); ?></option>
                    <option value="lost"><?php           esc_html_e( 'Perdido',       'aura-suite' ); ?></option>
                    <option value="withdrawn"><?php      esc_html_e( 'Retirado',      'aura-suite' ); ?></option>
                </select>

                <?php if ( ! empty( $areas ) ) : ?>
                <select id="aura-lib-filter-area" style="height: 38px; min-width: 140px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value="0"><?php esc_html_e( 'Todas las áreas', 'aura-suite' ); ?></option>
                    <?php foreach ( $areas as $area ) : ?>
                    <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <button id="aura-lib-filter-apply" class="button aura-btn-primary" style="height: 38px;">
                    <span class="dashicons dashicons-search" style="vertical-align: middle;"></span> <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
                </button>
                <button id="aura-lib-filter-clear" class="button aura-btn-secondary" style="height: 38px;">
                    <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <!-- Tabla fluida DataTables -->
        <div class="aura-dt-wrapper">
            <table id="aura-lib-books-table" class="dataTable aura-table display responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Portada',   'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Título',    'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Autor',     'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Estado',    'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Copias',    'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Dewey',     'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Ubicación', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Acciones',  'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<?php
wp_enqueue_script(
    'aura-lib-books',
    AURA_PLUGIN_URL . 'assets/js/library-books.js',
    [ 'jquery', 'datatables-responsive-js', 'aura-ui-core' ],
    AURA_VERSION, true
);
wp_enqueue_style(
    'aura-lib-books-css',
    AURA_PLUGIN_URL . 'assets/css/library-books.css',
    [ 'aura-design-system' ], AURA_VERSION
);
// Media uploader para modal de form
wp_enqueue_media();
?>

<!-- Modal: Nuevo / Editar Libro -->
<div id="aura-lib-book-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-modal-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content aura-lib-modal-large">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-modal-title"><?php esc_html_e( 'Nuevo Libro', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body" id="aura-lib-modal-body">
            <?php include AURA_PLUGIN_DIR . 'templates/library/book-form.php'; ?>
        </div>
    </div>
</div>

<!-- Modal: Detalle del Libro -->
<div id="aura-lib-detail-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content aura-lib-modal-large">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-detail-title"><?php esc_html_e( 'Detalle del Libro', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body" id="aura-lib-detail-body">
            <span class="spinner is-active" style="float:none;"></span>
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
<div id="aura-lib-reserve-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-reserve-modal-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content" style="max-width:460px;">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-reserve-modal-title"><?php esc_html_e( 'Reservar Libro', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body">
            <form id="aura-lib-reserve-form">
                <p>
                    <?php esc_html_e( 'Libro:', 'aura-business-suite' ); ?>
                    <strong id="aura-lib-reserve-book-title"></strong>
                </p>
                <input type="hidden" id="aura-lib-reserve-book-id">

                <div class="aura-lib-form-row">
                    <label for="aura-lib-reserve-notes"><?php esc_html_e( 'Notas (opcional)', 'aura-business-suite' ); ?></label>
                    <textarea id="aura-lib-reserve-notes" rows="2" class="large-text"></textarea>
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

                <div class="aura-lib-modal-footer">
                    <button type="submit" class="button button-primary" id="aura-lib-reserve-save">
                        <?php esc_html_e( 'Confirmar Reserva', 'aura-business-suite' ); ?>
                    </button>
                    <button type="button" class="button aura-lib-modal-close">
                        <?php esc_html_e( 'Cancelar', 'aura-business-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
