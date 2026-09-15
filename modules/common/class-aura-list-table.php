<?php
/**
 * Clase Base Universal para Tablas en Aura Business Suite
 * Extiende WP_List_Table y provee soporte global para:
 * - Filas Expandibles (Child Rows) con diseño modular "WOW" (Glassmorphism, Sub-cards, Hero Header)
 * - Botón interactivo chevron toggle con accesibilidad (aria-expanded) y micro-animaciones
 * - Puntos de ruptura responsive (Desktop, Tablet, Mobile)
 * - Integración completa con Modo Claro y Modo Oscuro
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

abstract class Aura_List_Table extends WP_List_Table {

    /**
     * Determina si la tabla soporta filas expandibles.
     * Puede ser sobrescrito por clases hijas para desactivarlo si alguna tabla no lo requiere.
     *
     * @var bool
     */
    protected $enable_child_rows = true;

    /**
     * Proxy público para display_tablenav() (protected en WP_List_Table).
     *
     * @param string $which 'top' o 'bottom'
     */
    public function render_tablenav( $which ) {
        $this->display_tablenav( $which );
    }

    /**
     * Obtiene las clases CSS de la tabla excluyendo 'fixed' para permitir
     * un dimensionamiento fluido y que el scroll horizontal responda correctamente.
     *
     * @return string[]
     */
    protected function get_table_classes() {
        $classes = parent::get_table_classes();
        return array_values( array_diff( $classes, array( 'fixed' ) ) );
    }

    /**
     * Proxy público para get_table_classes() (protected en WP_List_Table).
     * Devuelve las clases CSS que WP asigna a la tabla (sin 'fixed').
     *
     * @return string[]
     */
    public function get_classes() {
        return $this->get_table_classes();
    }

    /**
     * Muestra la tabla completa junto con la navegación superior e inferior.
     * Envuelve el elemento <table> en un contenedor de scroll horizontal (.aura-table-inner-scroll)
     * para que la paginación y las acciones en bloque permanezcan siempre alineadas
     * al ancho visible de la pantalla en cualquier dispositivo.
     */
    public function display() {
        $singular = $this->_args['singular'] ?? '';

        $this->display_tablenav( 'top' );

        $this->screen->render_screen_reader_content( 'heading_list' );
        ?>
        <div class="aura-table-inner-scroll">
            <table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
                <thead>
                    <tr>
                        <?php $this->print_column_headers(); ?>
                    </tr>
                </thead>

                <tbody id="the-list"<?php
                if ( $singular ) {
                    echo " data-wp-lists='list:$singular'";
                } ?>>
                    <?php $this->display_rows_or_placeholder(); ?>
                </tbody>

                <tfoot>
                    <tr>
                        <?php $this->print_column_headers( false ); ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
        $this->display_tablenav( 'bottom' );
    }

    /**
     * Renderiza una fila individual y su correspondiente Fila Hija Expandible (Child Row).
     *
     * @param object|array $item Objeto o array de datos de la fila
     */
    public function single_row( $item ) {
        $item_id = is_object( $item ) ? ( $item->id ?? ( $item->ID ?? 0 ) ) : ( $item['id'] ?? ( $item['ID'] ?? 0 ) );
        $row_classes = array( 'aura-parent-row' );
        
        if ( $this->has_child_row( $item ) ) {
            $row_classes[] = 'aura-has-child-row';
        }

        $extra_classes = $this->get_row_classes( $item );
        if ( ! empty( $extra_classes ) ) {
            $row_classes = array_merge( $row_classes, (array) $extra_classes );
        }

        echo '<tr id="aura-row-' . esc_attr( $item_id ) . '" class="' . esc_attr( implode( ' ', array_unique( $row_classes ) ) ) . '" data-id="' . esc_attr( $item_id ) . '">';
        $this->single_row_columns( $item );
        echo '</tr>';

        // Renderizar la Fila Hija (Child Row) solo si está habilitada para este elemento
        if ( $this->enable_child_rows && $this->has_child_row( $item ) ) {
            $total_cols = count( $this->get_columns() );
            echo '<tr id="aura-child-row-' . esc_attr( $item_id ) . '" class="aura-child-row" style="display:none;" data-parent-id="' . esc_attr( $item_id ) . '">';
            echo '<td colspan="' . esc_attr( $total_cols ) . '" class="aura-child-row-cell">';
            echo '<div class="aura-child-card-wrapper">';
            echo '<div class="aura-child-card">';
            $this->render_child_row_content( $item );
            echo '</div>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
    }

    /**
     * Permite a las clases hijas agregar clases CSS personalizadas a la fila padre.
     *
     * @param object|array $item
     * @return array|string
     */
    protected function get_row_classes( $item ) {
        return array();
    }

    /**
     * Verifica si un ítem específico debe tener fila expandible.
     *
     * @param object|array $item
     * @return bool
     */
    protected function has_child_row( $item ) {
        return $this->enable_child_rows;
    }

    /**
     * Renderiza el botón interactivo chevron toggle para expandir/colapsar la fila.
     *
     * @param int|string $item_id
     * @param string     $title
     * @return string
     */
    public function render_row_toggle( $item_id, $title = '' ) {
        if ( ! $this->enable_child_rows ) {
            return '';
        }

        $title = $title ?: __( 'Ver todos los detalles', 'aura-suite' );

        return sprintf(
            '<button type="button" class="aura-row-toggle" data-id="%s" aria-expanded="false" title="%s" aria-label="%s">'
            . '<span class="dashicons dashicons-arrow-down-alt2"></span>'
            . '</button>',
            esc_attr( $item_id ),
            esc_attr( $title ),
            esc_attr( $title )
        );
    }

    /**
     * Helper para renderizar un item de metadato dentro de un sub-bloque de la tarjeta Child Row.
     *
     * @param string $label Etiqueta del metadato
     * @param string $value Valor HTML o texto
     * @param string $icon Dashicon opcional (ej: 'dashicons-calendar')
     * @param string $extra_class Clase CSS adicional opcional
     * @return string
     */
    public static function render_meta_item( $label, $value, $icon = '', $extra_class = '' ) {
        $icon_html = '';
        if ( ! empty( $icon ) ) {
            $icon_html = '<span class="dashicons ' . esc_attr( $icon ) . ' aura-meta-icon"></span>';
        }

        return sprintf(
            '<div class="aura-child-meta-item %s">'
            . '<span class="aura-child-meta-label">%s%s</span>'
            . '<div class="aura-child-meta-value">%s</div>'
            . '</div>',
            esc_attr( $extra_class ),
            $icon_html,
            esc_html( $label ),
            $value
        );
    }

    /**
     * Helper para renderizar un sub-bloque temático con estilo tarjeta moderna ("sub-card").
     *
     * @param string $title Título de la sección
     * @param string $content Contenido HTML interno
     * @param string $icon Dashicon opcional
     * @param string $extra_class
     * @return string
     */
    public static function render_child_section( $title, $content, $icon = '', $extra_class = '' ) {
        $icon_html = '';
        if ( ! empty( $icon ) ) {
            $icon_html = '<span class="dashicons ' . esc_attr( $icon ) . ' aura-section-icon"></span>';
        }

        return sprintf(
            '<div class="aura-child-section %s">'
            . '<div class="aura-child-section-title">%s%s</div>'
            . '<div class="aura-child-section-body">%s</div>'
            . '</div>',
            esc_attr( $extra_class ),
            $icon_html,
            esc_html( $title ),
            $content
        );
    }

    /**
     * Método abstracto que cada tabla específica debe implementar para definir
     * el contenido de la tarjeta de detalles desplegable.
     *
     * @param object|array $item
     */
    abstract protected function render_child_row_content( $item );
}
