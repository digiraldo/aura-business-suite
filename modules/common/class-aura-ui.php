<?php
/**
 * Motor Centralizado de Componentes UI — Aura Business Suite
 * Generador canónico de componentes visuales estándar para todos los módulos.
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_UI {

    /**
     * Renderiza la cabecera estándar de página (Hero Glass Card o Hero Banner Superior).
     *
     * @param array $args {
     *     @type string $title        Título principal de la página.
     *     @type string $subtitle     Descripción o contexto secundario.
     *     @type string $icon         Nombre de clase Dashicon (ej: 'dashicons-category').
     *     @type string $variant      'glass' (default) o 'hero' (estilo oscuro expandido).
     *     @type array  $badges       Lista de badges ['label' => '', 'variant' => 'blue', 'tooltip' => ''].
     *     @type array  $actions      Botones de acción ['label' => '', 'url' => '', 'class' => '', 'icon' => '', 'attr' => ''].
     *     @type string $module_tag   Etiqueta de módulo (para variante hero).
     *     @type string $sub_tag      Etiqueta de subpágina (para variante hero).
     * }
     */
    public static function render_page_header( array $args ): void {
        echo self::get_page_header( $args );
    }

    /**
     * Retorna el HTML de la cabecera estándar de página.
     *
     * @param array $args
     * @return string
     */
    public static function get_page_header( array $args ): string {
        $title      = $args['title'] ?? '';
        $subtitle   = $args['subtitle'] ?? '';
        $icon       = $args['icon'] ?? 'dashicons-admin-generic';
        $variant    = $args['variant'] ?? 'glass';
        $badges     = $args['badges'] ?? [];
        $actions    = $args['actions'] ?? [];
        $module_tag = $args['module_tag'] ?? '';
        $sub_tag    = $args['sub_tag'] ?? '';

        ob_start();

        if ( $variant === 'wow-vip' || $variant === 'dashboard' ) :
            $current_user = wp_get_current_user();
            $org_name     = get_option('aura_org_name', get_bloginfo('name'));
            $org_logo_url = get_option('aura_org_logo_url', '');
            $org_tagline  = get_option('aura_org_tagline', '');
            $user_avatar  = get_avatar_url($current_user->ID, ['size' => 64]);
            $user_first   = $current_user->first_name ?: $current_user->display_name;
            $hour         = (int) current_time('H');
            $greeting     = ($hour >= 6 && $hour < 12) ? __('Buenos días', 'aura-suite') : (($hour >= 12 && $hour < 19) ? __('Buenas tardes', 'aura-suite') : __('Buenas noches', 'aura-suite'));
            $user_roles   = (array) $current_user->roles;
            $user_role_label = class_exists('Aura_Roles_Manager') ? Aura_Roles_Manager::get_role_label(reset($user_roles) ?: 'subscriber') : (reset($user_roles) ?: 'Usuario');
            ?>
            <header class="wow-vip-card aura-dashboard-vip-header fade-up">
                <div class="aura-vip-header__top-row flex items-center justify-between flex-wrap gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <?php if ( $icon && strpos($icon, 'dashicons') !== false ) : ?>
                            <div class="avatar avatar-lg" style="background:linear-gradient(135deg,#eab308,#ca8a04);color:#1e1b4b;box-shadow:0 8px 24px rgba(234,179,8,0.35);font-size:24px;">
                                <span class="dashicons <?php echo esc_attr($icon); ?>" style="font-size:26px;width:26px;height:26px;"></span>
                            </div>
                        <?php elseif ($org_logo_url) : ?>
                            <div class="avatar avatar-lg avatar-zoomable" data-preview-title="<?php echo esc_attr($org_name); ?>" style="background:linear-gradient(135deg,#eab308,#ca8a04);padding:3px;box-shadow:0 8px 24px rgba(234,179,8,0.35);cursor:zoom-in;">
                                <img src="<?php echo esc_url($org_logo_url); ?>" alt="<?php echo esc_attr($org_name); ?>" style="width:100%;height:100%;object-fit:contain;border-radius:50%;background:#0f172a;padding:4px;">
                            </div>
                        <?php else : ?>
                            <div class="avatar avatar-lg" style="background:linear-gradient(135deg,#eab308,#ca8a04);color:#1e1b4b;box-shadow:0 8px 24px rgba(234,179,8,0.35);font-size:24px;">👑</div>
                        <?php endif; ?>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 style="font-size:1.4rem;font-weight:800;color:var(--tx-primary);margin:0;line-height:1.2;"><?php echo esc_html($title ?: $org_name); ?></h2>
                                <span class="badge aura-vip-badge aura-vip-badge--gold"><?php _e('Nivel 1 Empresarial', 'aura-suite'); ?></span>
                            </div>
                            <div class="text-sm text-muted" style="margin-top:2px;">
                                <?php echo esc_html($subtitle ?: $org_tagline); ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="live-chip aura-vip-live-chip">
                            <span class="pulse-dot" style="background-color:var(--aura-emerald);"></span> <?php _e('Sistema en línea', 'aura-suite'); ?>
                        </span>
                        <div class="aura-vip-user-card">
                            <div class="avatar avatar-md avatar-ring avatar-ring-indigo avatar-zoomable" data-preview-title="<?php echo esc_attr($user_first . ' (' . $user_role_label . ')'); ?>" style="cursor:zoom-in;">
                                <img src="<?php echo esc_url($user_avatar); ?>" alt="<?php echo esc_attr($user_first); ?>">
                            </div>
                            <div>
                                <div class="aura-vip-user-card__greeting">
                                    <?php echo esc_html($greeting) . ', ' . esc_html($user_first) . '!'; ?>
                                </div>
                                <span class="badge badge-indigo aura-vip-user-card__role">
                                    <span class="dashicons dashicons-id-alt" style="font-size:11px;width:11px;height:11px;"></span>
                                    <?php echo esc_html($user_role_label); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ( ! empty($actions) ) : ?>
                    <div class="flex items-center justify-between flex-wrap gap-3" style="border-top:1px solid var(--border);padding-top:14px;">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="badge aura-vip-badge aura-vip-badge--indigo"><?php _e('Núcleo AURA 2.0', 'aura-suite'); ?></span>
                            <span class="badge aura-vip-badge aura-vip-badge--cyan"><?php _e('Sincronización Nube Activa', 'aura-suite'); ?></span>
                            <span class="badge aura-vip-badge aura-vip-badge--rose"><?php _e('Seguridad AAA', 'aura-suite'); ?></span>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php foreach ($actions as $action) :
                                $btn_label = $action['label'] ?? '';
                                $btn_url   = $action['url'] ?? '#';
                                $btn_class = $action['class'] ?? 'btn btn-secondary btn-sm btn-lift';
                                $btn_icon  = $action['icon'] ?? '';
                            ?>
                                <a href="<?php echo esc_url($btn_url); ?>" class="<?php echo esc_attr($btn_class); ?>">
                                    <?php if ($btn_icon) : ?>
                                        <span class="dashicons <?php echo esc_attr($btn_icon); ?>"></span>
                                    <?php endif; ?>
                                    <span><?php echo esc_html($btn_label); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </header>
        <?php elseif ( $variant === 'hero' ) : ?>
            <div class="aura-hero-header">
                <div class="aura-hero-content">
                    <div class="aura-hero-icon-box">
                        <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                    </div>
                    <div class="aura-hero-titles">
                        <?php if ( $module_tag || $sub_tag ) : ?>
                            <div class="aura-hero-subtitle-row">
                                <?php if ( $module_tag ) : ?>
                                    <span class="aura-tag-badge"><?php echo esc_html( $module_tag ); ?></span>
                                <?php endif; ?>
                                <?php if ( $sub_tag ) : ?>
                                    <span class="aura-tag-badge aura-tag-secondary"><?php echo esc_html( $sub_tag ); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <h1 class="aura-hero-title"><?php echo esc_html( $title ); ?></h1>
                        <?php if ( $subtitle ) : ?>
                            <p class="aura-hero-desc"><?php echo esc_html( $subtitle ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( ! empty( $actions ) ) : ?>
                    <div class="aura-hero-actions">
                        <?php foreach ( $actions as $action ) :
                            $btn_label = $action['label'] ?? '';
                            $btn_url   = $action['url'] ?? '#';
                            $btn_class = $action['class'] ?? 'aura-btn aura-btn-primary';
                            $btn_icon  = $action['icon'] ?? '';
                            $btn_attr  = $action['attr'] ?? '';
                        ?>
                            <a href="<?php echo esc_url( $btn_url ); ?>" class="<?php echo esc_attr( $btn_class ); ?>" <?php echo $btn_attr; ?>>
                                <?php if ( $btn_icon ) : ?>
                                    <span class="dashicons <?php echo esc_attr( $btn_icon ); ?>"></span>
                                <?php endif; ?>
                                <?php echo esc_html( $btn_label ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <header class="aura-glass-card aura-page-header">
                <div class="aura-page-header__icon">
                    <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                </div>
                <div class="aura-page-header__content">
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <?php if ( $subtitle ) : ?>
                        <p><?php echo esc_html( $subtitle ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( ! empty( $badges ) || ! empty( $actions ) ) : ?>
                    <div class="aura-page-header__badges">
                        <?php foreach ( $badges as $badge ) :
                            $b_label   = $badge['label'] ?? '';
                            $b_variant = $badge['variant'] ?? 'blue';
                            $b_tip     = $badge['tooltip'] ?? '';
                        ?>
                            <span class="aura-badge aura-badge-<?php echo esc_attr( $b_variant ); ?>" <?php echo $b_tip ? 'data-tooltip="' . esc_attr( $b_tip ) . '"' : ''; ?>>
                                <?php echo esc_html( $b_label ); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php foreach ( $actions as $action ) :
                            $btn_label = $action['label'] ?? '';
                            $btn_url   = $action['url'] ?? '#';
                            $btn_class = $action['class'] ?? 'aura-btn aura-btn-primary';
                            $btn_icon  = $action['icon'] ?? '';
                            $btn_attr  = $action['attr'] ?? '';
                        ?>
                            <a href="<?php echo esc_url( $btn_url ); ?>" class="<?php echo esc_attr( $btn_class ); ?>" <?php echo $btn_attr; ?>>
                                <?php if ( $btn_icon ) : ?>
                                    <span class="dashicons <?php echo esc_attr( $btn_icon ); ?>"></span>
                                <?php endif; ?>
                                <?php echo esc_html( $btn_label ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </header>
        <?php endif;

        return ob_get_clean();
    }

    /**
     * Renderiza la barra de acciones superior (ej: botón nuevo, exportar, filtros toggle).
     *
     * @param array $left   Elementos alineados a la izquierda.
     * @param array $right  Elementos alineados a la derecha.
     */
    public static function render_top_actions( array $left = [], array $right = [] ): void {
        echo self::get_top_actions( $left, $right );
    }

    public static function get_top_actions( array $left = [], array $right = [] ): string {
        ob_start();
        ?>
        <div class="aura-top-actions aura-cat-top-actions">
            <div class="aura-actions-left">
                <?php foreach ( $left as $item ) {
                    echo is_callable( $item ) ? $item() : $item;
                } ?>
            </div>
            <div class="aura-actions-right">
                <?php foreach ( $right as $item ) {
                    echo is_callable( $item ) ? $item() : $item;
                } ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza un grid de tarjetas KPI / Estadísticas.
     *
     * @param array $kpis Array de tarjetas KPI:
     *     [
     *         'label'   => 'Total Ingresos',
     *         'value'   => '$12,450.00',
     *         'icon'    => 'dashicons-money-alt',
     *         'variant' => 'income|expense|capital|balance|count|pending',
     *         'tooltip' => 'Descripción contextual',
     *         'sub'     => 'Subtítulo opcional'
     *     ]
     * @param array $options Opciones opcionales ['columns' => 4, 'class' => '']
     */
    public static function render_stats_grid( array $kpis, array $options = [] ): void {
        echo self::get_stats_grid( $kpis, $options );
    }

    public static function get_stats_grid( array $kpis, array $options = [] ): string {
        $extra_class = ! empty( $options['class'] ) ? ' ' . esc_attr( $options['class'] ) : '';
        $cols = ! empty( $options['columns'] ) ? ' style="grid-template-columns: repeat(' . absint( $options['columns'] ) . ', 1fr);"' : '';
        ob_start();
        ?>
        <div class="aura-stats-grid aura-ud-stats-grid<?php echo $extra_class; ?>"<?php echo $cols; ?>>
            <?php foreach ( $kpis as $kpi ) :
                $label   = $kpi['label'] ?? '';
                $value   = $kpi['value'] ?? '0';
                $icon    = $kpi['icon'] ?? 'dashicons-chart-pie';
                $variant = $kpi['variant'] ?? 'count';
                $tooltip = $kpi['tooltip'] ?? '';
                $sub     = $kpi['sub'] ?? '';
                $id_attr = ! empty( $kpi['id'] ) ? ' id="' . esc_attr( $kpi['id'] ) . '"' : '';
            ?>
                <div class="aura-stat-card aura-ud-stat-card <?php echo esc_attr( $variant ); ?>" <?php echo $tooltip ? 'data-tooltip="' . esc_attr( $tooltip ) . '"' : ''; ?>>
                    <div class="aura-stat-icon-wrap aura-ud-stat-icon-wrap">
                        <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                    </div>
                    <div class="aura-stat-body aura-ud-stat-body">
                        <div class="aura-stat-label-wrap aura-ud-stat-label-wrap">
                            <span class="aura-stat-label aura-ud-stat-label"><?php echo esc_html( $label ); ?></span>
                            <?php if ( $tooltip ) : ?>
                                <div class="aura-tooltip-wrap">
                                    <span class="aura-help-icon"><span class="dashicons dashicons-editor-help"></span></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="aura-stat-value aura-ud-stat-value"<?php echo $id_attr; ?>><?php echo esc_html( $value ); ?></span>
                        <?php if ( $sub ) : ?>
                            <span class="aura-stat-sub aura-ud-stat-sub"><?php echo esc_html( $sub ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza el sidebar de filtros colapsable.
     *
     * @param string $id       ID del contenedor sidebar.
     * @param array  $sections Contenido HTML o callables de las secciones de filtros.
     * @param array  $options  Opciones adicionales ['active_count' => 0, 'title' => 'Filtros'].
     */
    public static function render_filters_sidebar( string $id, array $sections, array $options = [] ): void {
        $active_count = $options['active_count'] ?? 0;
        $title        = $options['title'] ?? __( 'Filtros', 'aura-suite' );
        ?>
        <aside class="aura-filters-card" id="<?php echo esc_attr( $id ); ?>">
            <div class="aura-filters-header">
                <div class="aura-filters-header-title">
                    <span class="dashicons dashicons-filter" style="color: #3b82f6;"></span>
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( $active_count > 0 ) : ?>
                        <span class="aura-active-count-chip"><?php echo intval( $active_count ); ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" class="aura-toggle-sidebar-btn" id="toggle-filters" data-tooltip="<?php esc_attr_e( 'Ocultar filtros', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
            </div>
            <div class="aura-filters-body">
                <?php foreach ( $sections as $section ) {
                    echo is_callable( $section ) ? $section() : $section;
                } ?>
            </div>
        </aside>
        <?php
    }

    /**
     * Renderiza contenedor de tabla DataTables responsive (zero horizontal scroll).
     *
     * @param string $table_id ID de la tabla HTML.
     * @param array  $columns  Array con nombres/encabezados de columnas.
     * @param array  $options  Opciones ['class' => 'display responsive nowrap', 'footer' => false].
     */
    public static function render_table_container( string $table_id, array $columns, array $options = [] ): void {
        $extra_class = $options['class'] ?? 'display responsive nowrap';
        $has_footer  = $options['footer'] ?? false;
        ?>
        <div class="aura-dt-wrapper">
            <table id="<?php echo esc_attr( $table_id ); ?>" class="dataTable aura-table <?php echo esc_attr( $extra_class ); ?>" style="width:100%">
                <thead>
                    <tr>
                        <?php foreach ( $columns as $col ) : ?>
                            <th><?php echo esc_html( $col ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- Filas dinámicas o cargadas por DataTables -->
                </tbody>
                <?php if ( $has_footer ) : ?>
                    <tfoot>
                        <tr>
                            <?php foreach ( $columns as $col ) : ?>
                                <th><?php echo esc_html( $col ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Renderiza una botonera de acciones (solo iconos con tooltips).
     *
     * @param array $buttons Lista de botones ['type'=>'view|edit|delete|approve|reject|print|download|link', 'url'=>'#', 'tooltip'=>'', 'attr'=>'', 'icon'=>'']
     * @return string HTML de los botones
     */
    public static function render_action_buttons( array $buttons ): string {
        $icon_map = [
            'view'     => 'dashicons-visibility',
            'edit'     => 'dashicons-edit',
            'delete'   => 'dashicons-trash',
            'approve'  => 'dashicons-yes',
            'reject'   => 'dashicons-no',
            'print'    => 'dashicons-printer',
            'download' => 'dashicons-download',
            'link'     => 'dashicons-external',
        ];

        $html = '<div class="aura-row-actions">';
        foreach ( $buttons as $btn ) {
            $type    = $btn['type'] ?? 'view';
            $url     = $btn['url'] ?? '#';
            $tooltip = $btn['tooltip'] ?? ucfirst( $type );
            $attr    = $btn['attr'] ?? '';
            $icon    = $btn['icon'] ?? ( $icon_map[ $type ] ?? 'dashicons-admin-generic' );

            $html .= sprintf(
                '<a href="%s" class="aura-btn-action-icon aura-btn-%s" data-tooltip="%s" %s><span class="dashicons %s"></span></a>',
                esc_url( $url ),
                esc_attr( $type ),
                esc_attr( $tooltip ),
                $attr,
                esc_attr( $icon )
            );
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Renderiza avatar circular con imagen o fallback de Dashicon.
     *
     * @param string $image_url URL de la imagen del avatar.
     * @param string $name      Nombre de la persona o entidad (para alt/title).
     * @param string $size      'sm' (28px), 'md' (36px, default), 'lg' (48px).
     * @param string $type      'user' o 'business'.
     * @return string
     */
    public static function render_avatar( string $image_url, string $name = '', string $size = 'md', string $type = 'user' ): string {
        $size_class = $size === 'sm' ? 'aura-avatar-circle--sm' : ( $size === 'lg' ? 'aura-avatar-circle--lg' : '' );
        $default_icon = $type === 'business' ? 'dashicons-building' : 'dashicons-admin-users';

        $html = '<div class="aura-avatar-circle ' . esc_attr( $size_class ) . '" title="' . esc_attr( $name ) . '">';
        if ( ! empty( $image_url ) ) {
            $html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '" loading="lazy">';
        } else {
            $html .= '<span class="dashicons ' . esc_attr( $default_icon ) . '"></span>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Renderiza el Navbar sticky con pestañas (Auto-shrink).
     *
     * @param array  $tabs       Array de pestañas ['id'=>'', 'label'=>'', 'icon'=>'dashicons-...', 'count'=>0, 'url'=>'#']
     * @param string $active_tab ID de la pestaña activa.
     */
    public static function render_navbar( array $tabs, string $active_tab = '' ): void {
        ?>
        <nav class="aura-navbar aura-nav aura-glass-card">
            <div class="aura-navbar-menu">
                <?php foreach ( $tabs as $tab ) :
                    $tab_id    = $tab['id'] ?? '';
                    $label     = $tab['label'] ?? '';
                    $icon      = $tab['icon'] ?? '';
                    $count     = $tab['count'] ?? null;
                    $url       = $tab['url'] ?? '#';
                    $is_active = ( $tab_id === $active_tab );
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" 
                       class="aura-navbar-item aura-tab-btn <?php echo $is_active ? 'active is-active' : ''; ?>" 
                       data-tab="<?php echo esc_attr( $tab_id ); ?>">
                        <?php if ( $icon ) : ?>
                            <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                        <?php endif; ?>
                        <?php echo esc_html( $label ); ?>
                        <?php if ( $count !== null ) : ?>
                            <span class="aura-tab-count"><?php echo intval( $count ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
        <?php
    }

    /**
     * Renderiza botones de selección rápida de período (chips).
     *
     * @param array  $periods        Lista de períodos permitidos.
     * @param string $active_period  Período activo actualmente.
     */
    public static function render_date_chips( array $periods = [], string $active_period = 'this_month' ): void {
        $default_periods = [
            'this_month' => __( 'Este Mes', 'aura-suite' ),
            'last_month' => __( 'Mes Anterior', 'aura-suite' ),
            'quarter'    => __( 'Trimestre', 'aura-suite' ),
            'this_year'  => __( 'Este Año', 'aura-suite' ),
            'all'        => __( 'Todo', 'aura-suite' ),
        ];
        $periods = ! empty( $periods ) ? $periods : $default_periods;
        ?>
        <div class="aura-quick-date-chips">
            <span class="aura-chips-label"><span class="dashicons dashicons-calendar"></span> <?php esc_html_e( 'Período Rápido:', 'aura-suite' ); ?></span>
            <?php foreach ( $periods as $key => $label ) : ?>
                <button type="button" 
                        class="aura-chip-btn <?php echo $key === $active_period ? 'active is-active' : ''; ?>" 
                        data-period="<?php echo esc_attr( $key ); ?>">
                    <?php echo esc_html( $label ); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Renderiza un Empty State estándar cuando no hay registros.
     *
     * @param string $icon         Dashicon (ej: 'dashicons-portfolio').
     * @param string $title        Título del estado vacío.
     * @param string $message      Mensaje explicativo.
     * @param string $action_label Texto del botón de acción (opcional).
     * @param string $action_url   URL del botón de acción (opcional).
     * @param string $action_class Clase CSS del botón.
     */
    /**
     * Renderiza un indicador de estado en vivo con onda de pulso (Live Pulse Indicator).
     *
     * @param string $label   Etiqueta de texto (ej: 'Servicio Activo', 'En línea').
     * @param string $status  'online', 'warning', 'danger', 'primary', 'info'.
     * @param string $class   Clases adicionales.
     */
    public static function render_live_indicator( string $label, string $status = 'online', string $class = '' ): void {
        $status_class = in_array( $status, [ 'online', 'warning', 'danger', 'primary', 'info' ], true ) ? $status : 'online';
        ?>
        <span class="aura-live-indicator aura-live-indicator--<?php echo esc_attr( $status_class ); ?> <?php echo esc_attr( $class ); ?>">
            <span class="aura-pulse-dot aura-pulse-dot--<?php echo esc_attr( $status_class ); ?>"></span>
            <span><?php echo esc_html( $label ); ?></span>
        </span>
        <?php
    }

    /**
     * Renderiza un separador horizontal con desvanecimiento (Gradient Divider).
     *
     * @param bool   $thick  Si es true, usa la variante de 2px más acentuada.
     * @param string $class  Clases adicionales.
     */
    public static function render_divider_gradient( bool $thick = false, string $class = '' ): void {
        $variant_class = $thick ? 'aura-divider-gradient--thick' : '';
        echo '<hr class="aura-divider-gradient ' . esc_attr( $variant_class ) . ' ' . esc_attr( $class ) . '">';
    }

    /**
     * Renderiza una barra de carga dinámica ultra-delgada.
     *
     * @param string $class Clases adicionales.
     */
    public static function render_loading_bar( string $class = '' ): void {
        echo '<div class="aura-loading-bar ' . esc_attr( $class ) . '"></div>';
    }
}

