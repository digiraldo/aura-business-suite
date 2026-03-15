<?php
/**
 * Template: Gestión de Áreas y Programas
 *
 * Renderizado por Aura_Areas_Admin::render_page()
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Lista de Dashicons predefinidos para el selector de ícono
$dashicons_options = [
    // Personas y organización
    'dashicons-groups'              => 'Grupos',
    'dashicons-businessman'         => 'Ejecutivo',
    'dashicons-businesswoman'       => 'Ejecutiva',
    'dashicons-admin-users'         => 'Usuarios',
    'dashicons-nametag'             => 'Credencial',
    'dashicons-id-alt'              => 'Identificación',
    'dashicons-universal-access-alt'=> 'Accesibilidad',
    'dashicons-welcome-add-page'    => 'Agregar',
    'dashicons-admin-multisite'     => 'Red',
    'dashicons-networking'          => 'Red / Conexión',
    // Estructura y lugares
    'dashicons-building'            => 'Edificio',
    'dashicons-admin-site-alt3'     => 'Sitio web',
    'dashicons-admin-home'          => 'Inicio / Sede',
    'dashicons-location-alt'        => 'Ubicación',
    'dashicons-store'               => 'Tienda',
    'dashicons-bank'                => 'Banco',
    'dashicons-hammer'              => 'Construcción',
    'dashicons-admin-site'          => 'Sitio',
    // Finanzas y datos
    'dashicons-money-alt'           => 'Dinero',
    'dashicons-money'               => 'Billete',
    'dashicons-chart-bar'           => 'Gráfico barras',
    'dashicons-chart-line'          => 'Gráfico líneas',
    'dashicons-chart-pie'           => 'Gráfico circular',
    'dashicons-chart-area'          => 'Gráfico área',
    'dashicons-analytics'           => 'Analítica',
    'dashicons-calculator'          => 'Calculadora',
    'dashicons-bank'                => 'Banco',
    'dashicons-database'            => 'Base de datos',
    'dashicons-performance'         => 'Rendimiento',
    // Educación y conocimiento
    'dashicons-book-alt'            => 'Libro',
    'dashicons-book'                => 'Cuaderno',
    'dashicons-welcome-learn-more'  => 'Aprender',
    'dashicons-welcome-write-blog'  => 'Redacción',
    'dashicons-clipboard'           => 'Portapapeles',
    'dashicons-portfolio'           => 'Portafolio',
    'dashicons-editor-paste-text'   => 'Documento',
    'dashicons-format-aside'        => 'Apuntes',
    'dashicons-translation'         => 'Idiomas',
    'dashicons-search'              => 'Investigación',
    // Comunicación
    'dashicons-megaphone'           => 'Megáfono',
    'dashicons-email-alt'           => 'Correo',
    'dashicons-email-alt2'          => 'Correo 2',
    'dashicons-admin-comments'      => 'Comentarios',
    'dashicons-bell'                => 'Notificación',
    'dashicons-share'               => 'Compartir',
    'dashicons-rss'                 => 'RSS / Noticias',
    'dashicons-format-chat'         => 'Chat',
    'dashicons-phone'               => 'Teléfono',
    'dashicons-buddicons-topics'     => 'Foro',
    // Salud y bienestar
    'dashicons-heart'               => 'Corazón',
    'dashicons-sos'                 => 'Emergencia',
    'dashicons-plus-alt'            => 'Cruz / Salud',
    'dashicons-carrot'              => 'Alimentación',
    'dashicons-palmtree'            => 'Naturaleza',
    'dashicons-universal-access'    => 'Inclusión',
    // Símbolos y logros
    'dashicons-star-filled'         => 'Estrella',
    'dashicons-star-half'           => 'Media estrella',
    'dashicons-awards'              => 'Premio / Trofeo',
    'dashicons-shield-alt'          => 'Escudo',
    'dashicons-flag'                => 'Bandera',
    'dashicons-thumbs-up'           => 'Aprobado',
    'dashicons-yes-alt'             => 'Verificado',
    'dashicons-superhero'           => 'Super héroe',
    'dashicons-smiley'              => 'Comunidad',
    'dashicons-id'                  => 'Insignia',
    // Herramientas y tecnología
    'dashicons-lightbulb'           => 'Idea / Innovación',
    'dashicons-admin-tools'         => 'Herramientas',
    'dashicons-admin-settings'      => 'Configuración',
    'dashicons-laptop'              => 'Laptop',
    'dashicons-smartphone'          => 'Móvil',
    'dashicons-desktop'             => 'Escritorio',
    'dashicons-code-standards'      => 'Código',
    'dashicons-admin-plugins'       => 'Plugins / Módulos',
    'dashicons-cloud'               => 'Nube',
    'dashicons-lock'                => 'Seguridad',
    // Tiempo y planificación
    'dashicons-calendar-alt'        => 'Calendario',
    'dashicons-clock'               => 'Reloj',
    'dashicons-backup'              => 'Historial',
    'dashicons-update'              => 'Actualizar',
    'dashicons-controls-repeat'     => 'Ciclo / Repetir',
    'dashicons-list-view'           => 'Lista',
    'dashicons-grid-view'           => 'Cuadrícula',
    'dashicons-sort'                => 'Ordenar',
    // Multimedia
    'dashicons-images-alt2'         => 'Imagen / Galeria',
    'dashicons-video-alt3'          => 'Video',
    'dashicons-admin-media'         => 'Multimedia',
    'dashicons-microphone'          => 'Micrófono',
    'dashicons-playlist-audio'      => 'Playlist',
    'dashicons-format-gallery'      => 'Galería',
    'dashicons-camera'              => 'Cámara',
    'dashicons-art'                 => 'Arte / Diseño',
    // Transporte
    'dashicons-car'                 => 'Vehículo',
    'dashicons-airplane'            => 'Vuelo / Viajes',
    'dashicons-location'            => 'Mapa',
    'dashicons-migrate'             => 'Migración',
    // Navegación y acciones
    'dashicons-arrow-right-alt'     => 'Siguiente',
    'dashicons-arrow-left-alt'      => 'Anterior',
    'dashicons-arrow-up-alt'        => 'Subir',
    'dashicons-arrow-down-alt'      => 'Bajar',
    'dashicons-external'            => 'Enlace externo',
    'dashicons-download'            => 'Descargar',
    'dashicons-upload'              => 'Subir archivo',
    'dashicons-undo'                => 'Deshacer',
    'dashicons-redo'                => 'Rehacer',
    'dashicons-move'                => 'Mover',
    // Documentos y archivos
    'dashicons-media-document'      => 'Documento',
    'dashicons-media-spreadsheet'   => 'Hoja de cálculo',
    'dashicons-media-archive'       => 'Archivo ZIP',
    'dashicons-media-audio'         => 'Archivo audio',
    'dashicons-media-video'         => 'Archivo video',
    'dashicons-media-default'       => 'Archivo genérico',
    'dashicons-index-card'          => 'Tarjeta índice',
    'dashicons-paperclip'           => 'Adjunto',
    'dashicons-pdf'                 => 'PDF',
    'dashicons-shortcode'           => 'Código corto',
    // Comercio y productos
    'dashicons-cart'                => 'Carrito',
    'dashicons-products'            => 'Productos',
    'dashicons-tag'                 => 'Etiqueta',
    'dashicons-tickets'             => 'Entradas',
    'dashicons-tickets-alt'         => 'Evento',
    'dashicons-filter'              => 'Filtrar',
    'dashicons-randomize'           => 'Aleatorio',
    'dashicons-saved'               => 'Guardado',
    'dashicons-trash'               => 'Papelera',
    'dashicons-dismiss'             => 'Rechazar',
    // Seguridad y acceso
    'dashicons-unlock'              => 'Desbloqueado',
    'dashicons-shield'              => 'Protección',
    'dashicons-privacy'             => 'Privacidad',
    'dashicons-visibility'          => 'Visible',
    'dashicons-hidden'              => 'Oculto',
    'dashicons-vault'               => 'Bóveda',
    'dashicons-warning'             => 'Advertencia',
    'dashicons-info'                => 'Información',
    'dashicons-info-outline'        => 'Info detalle',
    'dashicons-thumbs-down'         => 'Rechazado',
    // Redes sociales
    'dashicons-facebook-alt'        => 'Facebook',
    'dashicons-twitter'             => 'Twitter / X',
    'dashicons-instagram'           => 'Instagram',
    'dashicons-linkedin'            => 'LinkedIn',
    'dashicons-youtube'             => 'YouTube',
    'dashicons-whatsapp'            => 'WhatsApp',
    'dashicons-reddit'              => 'Reddit',
    'dashicons-pinterest'           => 'Pinterest',
    'dashicons-google'              => 'Google',
    'dashicons-wordpress'           => 'WordPress',
    // Interfaz y presentación
    'dashicons-layout'              => 'Diseño / Layout',
    'dashicons-slides'              => 'Presentación',
    'dashicons-format-image'        => 'Imagen',
    'dashicons-format-video'        => 'Video formato',
    'dashicons-format-audio'        => 'Audio formato',
    'dashicons-format-quote'        => 'Cita',
    'dashicons-format-standard'     => 'Estándar',
    'dashicons-block-default'       => 'Bloque',
    'dashicons-widgets'             => 'Widgets',
    'dashicons-cover-image'         => 'Imagen portada',
    // Tecnología avanzada
    'dashicons-rest-api'            => 'API REST',
    'dashicons-editor-code'         => 'Editor código',
    'dashicons-html'                => 'HTML',
    'dashicons-database-add'        => 'Agregar BD',
    'dashicons-database-export'     => 'Exportar BD',
    'dashicons-database-import'     => 'Importar BD',
    'dashicons-dashboard'           => 'Dashboard',
    'dashicons-plugins-checked'     => 'Plugin activo',
    'dashicons-cloud-upload'        => 'Subir a nube',
    'dashicons-cloud-saved'         => 'Nube guardada',
    // Comunidad y social
    'dashicons-buddicons-friends'   => 'Amigos',
    'dashicons-buddicons-community' => 'Comunidad',
    'dashicons-buddicons-groups'    => 'Grupos sociales',
    'dashicons-buddicons-activity'  => 'Actividad social',
    'dashicons-buddicons-pm'        => 'Mensajes privados',
    'dashicons-buddicons-forums'    => 'Foros',
    'dashicons-buddicons-replies'   => 'Respuestas',
    'dashicons-superhero-alt'       => 'Héroe',
    'dashicons-feedback'            => 'Retroalimentación',
    'dashicons-star-empty'          => 'Sin calificar',
    // Admin y sistema
    'dashicons-admin-appearance'    => 'Apariencia',
    'dashicons-admin-page'          => 'Páginas',
    'dashicons-admin-post'          => 'Entradas blog',
    'dashicons-admin-links'         => 'Vínculos',
    'dashicons-admin-network'       => 'Red admin',
    'dashicons-admin-site-alt'      => 'Sitio alt',
    'dashicons-admin-site-alt2'     => 'Sitio alt2',
    'dashicons-welcome-comments'    => 'Comentar',
    'dashicons-welcome-view-site'   => 'Ver sitio',
    'dashicons-welcome-widgets-menus' => 'Widgets y menús',
    // Multimedia extendido
    'dashicons-playlist-video'      => 'Playlist video',
    'dashicons-controls-play'       => 'Reproducir',
    'dashicons-controls-pause'      => 'Pausar',
    'dashicons-controls-volumeon'   => 'Volumen',
    'dashicons-video-alt'           => 'Video alt',
    'dashicons-video-alt2'          => 'Video alt 2',
    'dashicons-images-alt'          => 'Galería alt',
    'dashicons-games'               => 'Juegos',
    'dashicons-camera-alt'          => 'Cámara alt',
    'dashicons-color-picker'        => 'Selector color',
    // Naturaleza y medio ambiente
    'dashicons-fire'                => 'Fogata / Fuego',
    'dashicons-editor-ul'           => 'Raíces / Árbol',
    'dashicons-image-rotate'        => 'Ciclo natural',
    'dashicons-admin-site-alt3'     => 'Origen / Tierra',
    'dashicons-embed-generic'       => 'Brote / Semilla',
    'dashicons-slides'              => 'Hojas / Follaje',
    'dashicons-random'              => 'Dispersión',
    'dashicons-format-status'       => 'Crecimiento',
    'dashicons-peding'              => 'Florecimiento',
    'dashicons-update-alt'          => 'Renovación',
    // Construcción y proyectos
    'dashicons-admin-tools'         => 'Taller',
    'dashicons-editor-spellcheck'   => 'Revisión',
    'dashicons-align-center'        => 'Plano / Diseño',
    'dashicons-align-full-width'    => 'Expansión',
    'dashicons-welcome-add-page'    => 'Nueva etapa',
    'dashicons-insert'              => 'Insertar',
    'dashicons-remove'              => 'Retirar',
    'dashicons-minus'               => 'Reducción',
    'dashicons-plus'                => 'Ampliación',
    'dashicons-edit-large'          => 'Edición mayor',
    // Personas específicas
    'dashicons-admin-users'         => 'Directorio',
    'dashicons-groups'              => 'Equipo base',
    'dashicons-migrate'             => 'Traslado',
    'dashicons-redo'                => 'Retorno',
    'dashicons-arrow-right-alt2'    => 'Avance',
    'dashicons-arrow-left-alt2'     => 'Retroceso',
    'dashicons-arrow-up-alt2'       => 'Ascenso',
    'dashicons-arrow-down-alt2'     => 'Descenso',
    // Logística y operaciones
    'dashicons-index-card'          => 'Ficha',
    'dashicons-text-page'           => 'Hoja informativa',
    'dashicons-editor-table'        => 'Tabla / Registro',
    'dashicons-list-view'           => 'Inventario',
    'dashicons-menu-alt'            => 'Menú extendido',
    'dashicons-menu-alt2'           => 'Opciones',
    'dashicons-menu-alt3'           => 'Submenú',
    'dashicons-ellipsis'            => 'Más opciones',
    'dashicons-controls-skipback'   => 'Inicio',
    'dashicons-controls-skipforward' => 'Final',
    // Servicios y atención
    'dashicons-editor-help'         => 'Ayuda',
    'dashicons-sos'                 => 'Urgente',
    'dashicons-phone'               => 'Contacto',
    'dashicons-whatsapp'            => 'Mensajería',
    'dashicons-format-chat'         => 'Atención',
    'dashicons-megaphone'           => 'Difusión',
    'dashicons-admin-comments'      => 'Consulta',
    'dashicons-format-quote'        => 'Testimonio',
    'dashicons-visibility'          => 'Transparencia',
    'dashicons-info-outline'        => 'Detalle',
    // Financiero extendido
    'dashicons-money'               => 'Efectivo',
    'dashicons-cart'                => 'Compras',
    'dashicons-tag'                 => 'Precio',
    'dashicons-tickets'             => 'Recibo',
    'dashicons-clipboard'           => 'Informe',
    'dashicons-media-spreadsheet'   => 'Contabilidad',
    'dashicons-chart-bar'           => 'Estadística',
    'dashicons-chart-line'          => 'Tendencia',
    'dashicons-analytics'           => 'Métricas',
    'dashicons-performance'         => 'Indicadores',
    // Eventos y actividades
    'dashicons-calendar'            => 'Agenda',
    'dashicons-calendar-alt'        => 'Programación',
    'dashicons-clock'               => 'Horario',
    'dashicons-backup'              => 'Registro histórico',
    'dashicons-star-filled'         => 'Destacado',
    'dashicons-awards'              => 'Reconocimiento',
    'dashicons-flag'                => 'Meta',
    'dashicons-location-alt'        => 'Punto de encuentro',
    'dashicons-tickets-alt'         => 'Convocatoria',
    'dashicons-playlist-audio'      => 'Difusión audio',
    // Formación y capacitación
    'dashicons-welcome-learn-more'  => 'Formación',
    'dashicons-book'                => 'Manual',
    'dashicons-book-alt'            => 'Guía',
    'dashicons-welcome-write-blog'  => 'Elaboración',
    'dashicons-search'              => 'Diagnóstico',
    'dashicons-portfolio'           => 'Proyecto',
    'dashicons-translation'         => 'Multilingüe',
    'dashicons-format-aside'        => 'Nota interna',
    'dashicons-editor-paste-text'   => 'Transcripción',
    'dashicons-screenoptions'       => 'Configuración avanzada',
];
?>

<div class="wrap aura-areas-page">

    <!-- ── Encabezado ─────────────────────────────────────────────────── -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-groups" style="margin-right:6px;"></span>
        <?php esc_html_e( 'Áreas y Programas', 'aura-suite' ); ?>
    </h1>
    <button type="button" class="page-title-action" id="aura-add-area-btn">
        <span class="dashicons dashicons-plus-alt"></span>
        <?php esc_html_e( 'Agregar Área', 'aura-suite' ); ?>
    </button>
    <hr class="wp-header-end">

    <!-- ── Mensajes de estado ─────────────────────────────────────────── -->
    <div id="aura-areas-notice" class="notice" style="display:none;">
        <p id="aura-areas-notice-text"></p>
    </div>

    <!-- ── Filtros ────────────────────────────────────────────────────── -->
    <div class="aura-filters-container" style="margin: 15px 0;">
        <div class="aura-filters-row" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">

            <div class="aura-filter-group">
                <label for="aura-filter-search" style="display:block; font-weight:600; margin-bottom:4px;">
                    <?php esc_html_e( 'Buscar:', 'aura-suite' ); ?>
                </label>
                <input type="text" id="aura-filter-search"
                       placeholder="<?php esc_attr_e( 'Nombre o descripción…', 'aura-suite' ); ?>"
                       style="min-width:200px;">
            </div>

            <div class="aura-filter-group">
                <label for="aura-filter-type" style="display:block; font-weight:600; margin-bottom:4px;">
                    <?php esc_html_e( 'Tipo:', 'aura-suite' ); ?>
                </label>
                <select id="aura-filter-type">
                    <option value=""><?php esc_html_e( 'Todos', 'aura-suite' ); ?></option>
                    <option value="program"><?php esc_html_e( 'Programa', 'aura-suite' ); ?></option>
                    <option value="activity"><?php esc_html_e( 'Actividad', 'aura-suite' ); ?></option>
                    <option value="department"><?php esc_html_e( 'Departamento', 'aura-suite' ); ?></option>
                    <option value="team"><?php esc_html_e( 'Equipo', 'aura-suite' ); ?></option>
                </select>
            </div>

            <div class="aura-filter-group">
                <label for="aura-filter-status" style="display:block; font-weight:600; margin-bottom:4px;">
                    <?php esc_html_e( 'Estado:', 'aura-suite' ); ?>
                </label>
                <select id="aura-filter-status">
                    <option value="active"><?php esc_html_e( 'Activas', 'aura-suite' ); ?></option>
                    <option value="archived"><?php esc_html_e( 'Archivadas', 'aura-suite' ); ?></option>
                    <option value=""><?php esc_html_e( 'Todas', 'aura-suite' ); ?></option>
                </select>
            </div>

            <div class="aura-filter-group">
                <button type="button" id="aura-apply-filters-btn" class="button button-primary">
                    <?php esc_html_e( 'Aplicar', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-clear-filters-btn" class="button" style="margin-left:4px;">
                    <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                </button>
            </div>

        </div>
    </div>

    <!-- ── Tabla ──────────────────────────────────────────────────────── -->
    <table class="wp-list-table widefat fixed striped" id="aura-areas-table">
        <thead>
            <tr>
                <th style="width:30%"><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                <th style="width:12%"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                <th style="width:18%"><?php esc_html_e( 'Responsable', 'aura-suite' ); ?></th>
                <th style="width:14%"><?php esc_html_e( 'Presupuesto', 'aura-suite' ); ?></th>
                <th style="width:10%"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th style="width:16%"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody id="aura-areas-tbody">
            <tr id="aura-areas-loading">
                <td colspan="6" style="text-align:center; padding:40px;">
                    <span class="spinner is-active" style="float:none; margin:0 8px 0 0;"></span>
                    <?php esc_html_e( 'Cargando áreas…', 'aura-suite' ); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Paginación -->
    <div id="aura-areas-pagination" style="margin-top:12px; display:none;">
        <button type="button" class="button" id="aura-areas-prev">&laquo; <?php esc_html_e( 'Anterior', 'aura-suite' ); ?></button>
        <span id="aura-areas-page-info" style="margin:0 10px;"></span>
        <button type="button" class="button" id="aura-areas-next"><?php esc_html_e( 'Siguiente', 'aura-suite' ); ?> &raquo;</button>
    </div>

</div><!-- .wrap -->

<!-- ════════════════════════════════════════════════════════════════════════
     MODAL: Crear / Editar Área
     ════════════════════════════════════════════════════════════════════════ -->
<div id="aura-area-modal" style="display:none;">
    <div id="aura-area-modal-overlay"></div>
    <div id="aura-area-modal-box">

        <!-- Encabezado -->
        <div id="aura-area-modal-header">
            <h2 id="aura-area-modal-title"><?php esc_html_e( 'Nueva Área', 'aura-suite' ); ?></h2>
            <button type="button" id="aura-area-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <!-- Cuerpo -->
        <div id="aura-area-modal-body">
            <form id="aura-area-form" novalidate>
                <input type="hidden" id="aura-area-id" name="area_id" value="0">

                <div class="aura-form-row">
                    <div class="aura-form-col aura-col-2">
                        <!-- Nombre -->
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

                    <div class="aura-form-col aura-col-1">
                        <!-- Tipo -->
                        <div class="aura-field">
                            <label for="aura-field-type"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></label>
                            <select id="aura-field-type" name="type">
                                <option value="program"><?php esc_html_e( 'Programa', 'aura-suite' ); ?></option>
                                <option value="activity"><?php esc_html_e( 'Actividad', 'aura-suite' ); ?></option>
                                <option value="department"><?php esc_html_e( 'Departamento', 'aura-suite' ); ?></option>
                                <option value="team"><?php esc_html_e( 'Equipo', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div><!-- .aura-form-row -->

                <!-- Descripción -->
                <div class="aura-field">
                    <label for="aura-field-description"><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></label>
                    <textarea id="aura-field-description" name="description" rows="3"
                              placeholder="<?php esc_attr_e( 'Descripción del área o programa…', 'aura-suite' ); ?>"></textarea>
                </div>

                <div class="aura-form-row">
                    <div class="aura-form-col aura-col-1">
                        <!-- Responsable -->
                        <div class="aura-field">
                            <label for="aura-field-responsible"><?php esc_html_e( 'Responsable', 'aura-suite' ); ?></label>
                            <select id="aura-field-responsible" name="responsible_user_id">
                                <option value="0"><?php esc_html_e( '— Sin asignar —', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="aura-form-col aura-col-1">
                        <!-- Área padre -->
                        <div class="aura-field">
                            <label for="aura-field-parent"><?php esc_html_e( 'Área padre', 'aura-suite' ); ?></label>
                            <select id="aura-field-parent" name="parent_area_id">
                                <option value="0"><?php esc_html_e( '— Ninguna —', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="aura-form-row">
                    <div class="aura-form-col aura-col-1">
                        <!-- Color -->
                        <div class="aura-field">
                            <label for="aura-field-color"><?php esc_html_e( 'Color', 'aura-suite' ); ?></label>
                            <input type="text" id="aura-field-color" name="color"
                                   value="#2271b1" class="aura-color-picker">
                        </div>
                    </div>

                    <div class="aura-form-col aura-col-2">
                        <!-- Ícono Dashicons -->
                        <div class="aura-field">
                            <label><?php esc_html_e( 'Ícono', 'aura-suite' ); ?></label>

                            <!-- Campo oculto que guarda el valor real -->
                            <input type="hidden" id="aura-field-icon" name="icon" value="dashicons-groups">

                            <!-- Buscador + preview -->
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                <input type="text" id="aura-icon-search"
                                       placeholder="<?php esc_attr_e('Buscar…','aura-suite'); ?>"
                                       style="flex:1;padding:4px 8px;border:1px solid #8c8f94;border-radius:3px;">
                                <span id="aura-icon-preview" class="dashicons dashicons-groups"
                                      style="font-size:32px;color:#2271b1;flex-shrink:0;"></span>
                            </div>

                            <!-- Grid de iconos -->
                            <div id="aura-icon-grid" style="
                                display:grid;
                                grid-template-columns:repeat(8,1fr);
                                gap:4px;
                                max-height:220px;
                                overflow-y:auto;
                                border:1px solid #dcdcde;
                                border-radius:4px;
                                padding:6px;
                                background:#fafafa;
                            ">
                                <?php foreach ( $dashicons_options as $value => $label ) : ?>
                                    <button type="button"
                                        class="aura-icon-item"
                                        data-icon="<?php echo esc_attr( $value ); ?>"
                                        data-label="<?php echo esc_attr( $label ); ?>"
                                        title="<?php echo esc_attr( $label ); ?>"
                                        style="
                                            display:flex;align-items:center;justify-content:center;
                                            width:100%;aspect-ratio:1;border:2px solid transparent;
                                            border-radius:4px;background:#fff;cursor:pointer;
                                            transition:border-color .15s,background .15s;
                                        ">
                                        <span class="dashicons <?php echo esc_attr( $value ); ?>"
                                              style="font-size:22px;"></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <!-- Nombre del ícono seleccionado -->
                            <div id="aura-icon-label"
                                 style="margin-top:4px;font-size:11px;color:#646970;text-align:center;"></div>
                        </div>
                    </div>

                    <div class="aura-form-col aura-col-1">
                        <!-- Orden -->
                        <div class="aura-field">
                            <label for="aura-field-sort"><?php esc_html_e( 'Orden', 'aura-suite' ); ?></label>
                            <input type="number" id="aura-field-sort" name="sort_order"
                                   value="0" min="0" style="width:80px;">
                        </div>
                    </div>
                </div>

            </form>
        </div><!-- #aura-area-modal-body -->

        <!-- Pie -->
        <div id="aura-area-modal-footer">
            <button type="button" class="button" id="aura-area-cancel-btn">
                <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button button-primary" id="aura-area-save-btn">
                <span class="spinner" id="aura-save-spinner" style="float:none; margin:0 4px 0 0; display:none;"></span>
                <?php esc_html_e( 'Guardar Área', 'aura-suite' ); ?>
            </button>
        </div>

    </div><!-- #aura-area-modal-box -->
</div><!-- #aura-area-modal -->

<!-- ════════════════════════════════════════════════════════════════════════
     ESTILOS INLINE
     ════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Tooltip hover effect ──────────────────────────────────── */
.aura-user-avatar-tooltip:hover .aura-tooltip {
    opacity: 1 !important;
}

/* ── Modal overlay ─────────────────────────────────────────── */
#aura-area-modal {
    position: fixed; inset: 0; z-index: 100000;
    display: flex; align-items: center; justify-content: center;
}
#aura-area-modal-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,.55);
}
#aura-area-modal-box {
    position: relative; z-index: 1;
    background: #fff;
    border-radius: 8px;
    width: 720px; max-width: 95vw;
    max-height: 90vh;
    display: flex; flex-direction: column;
    box-shadow: 0 8px 32px rgba(0,0,0,.25);
    overflow: hidden;
}

/* ── Modal header ──────────────────────────────────────────── */
#aura-area-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px;
    background: #1d2327;
    color: #fff;
}
#aura-area-modal-header h2 { margin: 0; font-size: 16px; color: #fff; }
#aura-area-modal-close {
    background: none; border: none; cursor: pointer;
    color: #aaa; font-size: 20px; line-height: 1; padding: 4px;
    transition: color .2s;
}
#aura-area-modal-close:hover { color: #fff; }

/* ── Modal body ────────────────────────────────────────────── */
#aura-area-modal-body {
    padding: 20px; overflow-y: auto; flex: 1;
}

/* ── Modal footer ──────────────────────────────────────────── */
#aura-area-modal-footer {
    padding: 14px 20px;
    border-top: 1px solid #e5e5e5;
    display: flex; justify-content: flex-end; gap: 8px;
    background: #f6f7f7;
}

/* ── Form layout ───────────────────────────────────────────── */
.aura-form-row { display: flex; gap: 16px; margin-bottom: 0; }
.aura-form-col { flex: 1; }
.aura-form-col.aura-col-2 { flex: 2; }
.aura-field { margin-bottom: 14px; }
.aura-field label {
    display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;
}
.aura-field label .required { color: #d63638; margin-left: 2px; }
.aura-field input[type="text"],
.aura-field input[type="number"],
.aura-field textarea,
.aura-field select {
    width: 100%; box-sizing: border-box;
}
.aura-field-error { color: #d63638; font-size: 12px; margin-top: 4px; display: block; }

/* ── Tabla ─────────────────────────────────────────────────── */
#aura-areas-table th { padding: 10px 12px; }
#aura-areas-table td { padding: 10px 12px; vertical-align: middle; }

/* ── Badge color ───────────────────────────────────────────── */
.aura-color-badge {
    display: inline-block;
    width: 14px; height: 14px;
    border-radius: 3px;
    margin-right: 6px;
    vertical-align: middle;
    border: 1px solid rgba(0,0,0,.15);
    flex-shrink: 0;
}
.aura-area-name-cell {
    display: flex; align-items: center; gap: 6px;
}
.aura-area-icon { font-size: 18px; }

/* ── Status badge ──────────────────────────────────────────── */
.aura-status-badge {
    display: inline-block; padding: 2px 8px;
    border-radius: 20px; font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .5px;
}
.aura-status-active   { background: #d1fae5; color: #065f46; }
.aura-status-archived { background: #f3f4f6; color: #6b7280; }

/* ── Type badge ────────────────────────────────────────────── */
.aura-type-badge {
    display: inline-block; padding: 2px 7px;
    border-radius: 4px; font-size: 11px;
    background: #e0e7ff; color: #3730a3; font-weight: 600;
}

/* ── Action buttons ────────────────────────────────────────── */
.aura-action-btn {
    position: relative;
    display: inline-flex; align-items: center; justify-content: center;
    background: none; border: 1px solid #ccc; border-radius: 4px;
    width: 28px; height: 28px; padding: 0; cursor: pointer;
    font-size: 12px; margin-right: 3px; transition: all .15s;
    vertical-align: middle; text-decoration: none; color: #50575e;
}
.aura-action-btn .dashicons { font-size: 14px; width: 14px; height: 14px; line-height: 1; }
.aura-action-btn:hover { background: #f0f0f0; color: #2271b1; border-color: #2271b1; }
.aura-action-btn.danger { border-color: #d63638; color: #d63638; }
.aura-action-btn.danger:hover { background: #fce8e8; }
.aura-action-btn.success { border-color: #00a32a; color: #00a32a; }
.aura-action-btn.success:hover { background: #e8f5e9; }
/* Tooltip */
.aura-action-btn::after {
    content: attr(data-tooltip);
    position: absolute; bottom: calc(100% + 6px); left: 50%;
    transform: translateX(-50%);
    background: #1d2327; color: #fff; font-size: 11px; white-space: nowrap;
    padding: 3px 8px; border-radius: 3px; pointer-events: none;
    opacity: 0; transition: opacity .15s; z-index: 9999;
}
.aura-action-btn:hover::after { opacity: 1; }

/* ── Notice ────────────────────────────────────────────────── */
#aura-areas-notice {
    margin: 12px 0;
    border-left-width: 4px;
    border-left-style: solid;
}
#aura-areas-notice.notice-success { border-left-color: #00a32a; }
#aura-areas-notice.notice-error   { border-left-color: #d63638; }

/* Grid de iconos */
.aura-icon-item:hover {
    border-color: #72aee6 !important;
    background: #f0f6ff !important;
}
.aura-icon-item:hover .dashicons {
    color: #2271b1;
}
#aura-icon-grid {
    scrollbar-width: thin;
    scrollbar-color: #c3c4c7 transparent;
}
</style>

<!-- ════════════════════════════════════════════════════════════════════════
     JAVASCRIPT INLINE
     ════════════════════════════════════════════════════════════════════════ -->
<script>
var auraAreasData = <?php echo wp_json_encode( [
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'aura_areas_nonce' ),
    'strings' => [
        'confirmArchive'    => __( '¿Archivar esta área? Podrás reactivarla más tarde.', 'aura-suite' ),
        'saved'             => __( 'Área guardada exitosamente.', 'aura-suite' ),
        'archived'          => __( 'Área archivada.', 'aura-suite' ),
        'error'             => __( 'Ocurrió un error. Intenta nuevamente.', 'aura-suite' ),
        'nameRequired'      => __( 'El nombre del área es obligatorio.', 'aura-suite' ),
        'loading'           => __( 'Cargando...', 'aura-suite' ),
        'noAreas'           => __( 'No se encontraron áreas.', 'aura-suite' ),
        'confirmReactivate' => __( '¿Reactivar esta área?', 'aura-suite' ),
        'reactivated'       => __( 'Área reactivada.', 'aura-suite' ),
    ],
] ); ?>;
</script>
<script>
(function($) {
    'use strict';

    /* ── Estado ──────────────────────────────────────────────────────── */
    const state = {
        page:       1,
        totalPages: 1,
        editing:    false,
    };

    const cfg = window.auraAreasData || {};
    const s   = cfg.strings || {};

    /* ── Utilidades ──────────────────────────────────────────────────── */
    function ajax(action, data, done, fail) {
        $.post(cfg.ajaxUrl, Object.assign({ action, nonce: cfg.nonce }, data))
            .done(r => r.success ? done(r.data) : showNotice(r.data?.message || s.error, 'error'))
            .fail(() => showNotice(s.error, 'error'));
    }

    function showNotice(msg, type = 'success') {
        const $n = $('#aura-areas-notice');
        $n.removeClass('notice-success notice-error')
          .addClass('notice-' + type)
          .find('#aura-areas-notice-text').text(msg);
        $n.show();
        setTimeout(() => $n.fadeOut(), 4000);
    }

    function fmtCurrency(val) {
        if (val === null || val === undefined) return '—';
        return new Intl.NumberFormat('es-MX', {
            style: 'currency', currency: 'MXN', minimumFractionDigits: 0
        }).format(val);
    }
    
    /**
     * Renderiza los responsables con avatares y tooltips
     */
    function renderResponsibles(area) {
        if (!area.assigned_users || area.assigned_users.length === 0) {
            return '<em style="color:#aaa;">Sin asignar</em>';
        }
        
        const maxVisible = 3;
        let html = '<div style="display:flex; align-items:center; gap:4px;">';
        
        area.assigned_users.slice(0, maxVisible).forEach((user) => {
            const avatarUrl = user.avatar_url || '';
            const userName = $('<div>').text(user.display_name).html();
            const roleBadge = user.role === 'coordinator' ? ' 👥' : user.role === 'viewer' ? ' 👁️' : '';
            
            html += `
                <div class="aura-user-avatar-tooltip" style="position:relative; display:inline-block;">
                    <img src="${avatarUrl}" 
                         alt="${userName}" 
                         title="${userName}${roleBadge}" 
                         style="width:32px; height:32px; border-radius:50%; border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.2); cursor:pointer;" />
                    <span class="aura-tooltip" style="
                        position:absolute; 
                        bottom:100%; 
                        left:50%; 
                        transform:translateX(-50%); 
                        background:#000; 
                        color:#fff; 
                        padding:4px 8px; 
                        border-radius:4px; 
                        font-size:11px; 
                        white-space:nowrap; 
                        opacity:0; 
                        pointer-events:none; 
                        transition:opacity 0.2s; 
                        margin-bottom:5px;
                        z-index:1000;
                    ">${userName}${roleBadge}</span>
                </div>
            `;
        });
        
        if (area.assigned_users.length > maxVisible) {
            const remaining = area.assigned_users.length - maxVisible;
            html += `<span style="color:#6b7280; font-size:12px; margin-left:4px;">+${remaining} más</span>`;
        }
        
        html += '</div>';
        return html;
    }

    /* ── Renderizar tabla ────────────────────────────────────────────── */
    function renderAreas(data) {
        const $tbody = $('#aura-areas-tbody');
        $tbody.empty();

        if (!data.areas || data.areas.length === 0) {
            $tbody.append(
                '<tr><td colspan="6" style="text-align:center;padding:30px;">' +
                s.noAreas + '</td></tr>'
            );
            $('#aura-areas-pagination').hide();
            return;
        }

        data.areas.forEach(area => {
            const statusClass = area.status === 'active' ? 'aura-status-active' : 'aura-status-archived';
            const statusLabel = area.status === 'active' ? 'Activa' : 'Archivada';

            const archiveBtn = area.status === 'active'
                ? `<button class="aura-action-btn danger aura-archive-btn" data-id="${area.id}" data-tooltip="Archivar">
                       <span class="dashicons dashicons-archive"></span>
                   </button>`
                : `<button class="aura-action-btn success aura-reactivate-btn" data-id="${area.id}" data-tooltip="Reactivar">
                       <span class="dashicons dashicons-update"></span>
                   </button>`;

            const budgetUrl    = `admin.php?page=aura-financial-budgets&area_id=${area.id}`;
            const dashboardUrl = `admin.php?page=aura-areas&view=dashboard&area_id=${area.id}`;

            $tbody.append(`
                <tr data-id="${area.id}">
                    <td>
                        <div class="aura-area-name-cell">
                            <span class="aura-color-badge" style="background:${area.color};"></span>
                            <span class="dashicons ${area.icon} aura-area-icon" style="color:${area.color};"></span>
                            <strong>${$('<span>').text(area.name).html()}</strong>
                            ${area.parent_name ? `<span style="color:#6b7280;font-size:12px;">(${$('<span>').text(area.parent_name).html()})</span>` : ''}
                        </div>
                        ${area.description ? `<small style="color:#6b7280;">${$('<span>').text(area.description.substring(0,80)).html()}${area.description.length > 80 ? '…' : ''}</small>` : ''}
                    </td>
                    <td><span class="aura-type-badge">${area.type_label}</span></td>
                    <td>${renderResponsibles(area)}</td>
                    <td>${fmtCurrency(area.budget_assigned)}</td>
                    <td><span class="aura-status-badge ${statusClass}">${statusLabel}</span></td>
                    <td style="white-space:nowrap;">
                        <button class="aura-action-btn aura-edit-btn" data-id="${area.id}" data-tooltip="Editar">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <a href="${dashboardUrl}" class="aura-action-btn" data-tooltip="Dashboard financiero">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </a>
                        <a href="${budgetUrl}" class="aura-action-btn" data-tooltip="Presupuesto">
                            <span class="dashicons dashicons-money-alt"></span>
                        </a>
                        ${archiveBtn}
                    </td>
                </tr>
            `);
        });

        // Paginación
        state.totalPages = data.pages || 1;
        if (data.total > 20) {
            $('#aura-areas-page-info').text(`Página ${data.page} de ${data.pages} (${data.total} áreas)`);
            $('#aura-areas-prev').prop('disabled', data.page <= 1);
            $('#aura-areas-next').prop('disabled', data.page >= data.pages);
            $('#aura-areas-pagination').show();
        } else {
            $('#aura-areas-pagination').hide();
        }
    }

    /* ── Cargar lista ────────────────────────────────────────────────── */
    function loadAreas(page) {
        state.page = page || 1;
        $('#aura-areas-tbody').html(
            '<tr><td colspan="6" style="text-align:center;padding:30px;">' +
            '<span class="spinner is-active" style="float:none;"></span> ' + s.loading + '</td></tr>'
        );

        ajax('aura_areas_list', {
            status: $('#aura-filter-status').val(),
            type:   $('#aura-filter-type').val(),
            search: $('#aura-filter-search').val(),
            paged:  state.page,
        }, renderAreas);
    }

    /* ── Cargar usuarios en select ───────────────────────────────────── */
    function loadUsers(selectedId) {
        if ($('#aura-field-responsible option').length > 1) {
            if (selectedId !== undefined) {
                $('#aura-field-responsible').val(selectedId);
            }
            return;
        }
        ajax('aura_areas_users', {}, data => {
            const $sel = $('#aura-field-responsible');
            $sel.find('option:not(:first)').remove();
            data.users.forEach(u => {
                $sel.append($('<option>').val(u.id).text(`${u.name} (${u.email})`));
            });
            if (selectedId) $sel.val(selectedId);
        });
    }

    /* ── Cargar áreas en select padre ────────────────────────────────── */
    function loadParentAreas(exceptId, selectedId) {
        ajax('aura_areas_areas_dropdown', { except_id: exceptId || 0 }, data => {
            const $sel = $('#aura-field-parent');
            $sel.find('option:not(:first)').remove();
            data.areas.forEach(a => {
                $sel.append($('<option>').val(a.id).text(a.name));
            });
            if (selectedId) $sel.val(selectedId);
        });
    }

    /* ── Abrir modal ─────────────────────────────────────────────────── */
    function openModal(title, area) {
        $('#aura-area-modal-title').text(title);
        const isEdit = area && area.id;

        $('#aura-area-id').val(isEdit ? area.id : 0);
        $('#aura-field-name').val(isEdit ? area.name : '');
        $('#aura-field-type').val(isEdit ? area.type : 'program');
        $('#aura-field-description').val(isEdit ? area.description : '');
        $('#aura-field-color').val(isEdit ? area.color : '#2271b1');
        $('#aura-field-icon').val(isEdit ? area.icon : 'dashicons-groups');
        $('#aura-field-sort').val(isEdit ? area.sort_order : 0);
        $('#error-name').hide();
        // Limpiar búsqueda de iconos y mostrar todos
        $('#aura-icon-search').val('');
        $('#aura-icon-grid .aura-icon-item').show();
        $('#aura-icon-label').text('');

        // Color picker
        try {
            $('#aura-field-color').wpColorPicker('color', isEdit ? area.color : '#2271b1');
        } catch(e) {}

        // Preview del ícono
        updateIconPreview(isEdit ? area.icon : 'dashicons-groups', isEdit ? area.color : '#2271b1');
        // Hacer scroll al ícono seleccionado tras un tick (el modal ya está visible)
        setTimeout(() => selectIconInGrid(isEdit ? area.icon : 'dashicons-groups'), 80);

        loadUsers(isEdit ? area.responsible_user_id : 0);
        loadParentAreas(isEdit ? area.id : 0, isEdit ? area.parent_area_id : 0);

        $('#aura-area-modal').show();
        setTimeout(() => $('#aura-field-name').focus(), 100);
    }

    function closeModal() {
        $('#aura-area-modal').hide();
    }

    function updateIconPreview(icon, color) {
        const $preview = $('#aura-icon-preview');
        $preview.attr('class', 'dashicons ' + icon).css('color', color || '#2271b1');
        // Sincronizar highlight en el grid
        selectIconInGrid(icon);
    }

    function selectIconInGrid(icon) {
        const $items = $('#aura-icon-grid .aura-icon-item');
        $items.css({ borderColor: 'transparent', background: '#fff' });
        const $active = $items.filter('[data-icon="' + icon + '"]');
        $active.css({ borderColor: '#2271b1', background: '#e8f0fa' });
        // Mostrar nombre
        const label = $active.data('label') || '';
        $('#aura-icon-label').text(label);
        // Scroll al elemento
        if ($active.length) {
            const grid = document.getElementById('aura-icon-grid');
            const item = $active[0];
            const gridTop  = grid.scrollTop;
            const gridBot  = gridTop + grid.clientHeight;
            const itemTop  = item.offsetTop;
            const itemBot  = itemTop + item.offsetHeight;
            if (itemTop < gridTop || itemBot > gridBot) {
                grid.scrollTop = itemTop - grid.clientHeight / 2;
            }
        }
    }

    /* ── Guardar área ────────────────────────────────────────────────── */
    function saveArea() {
        const name = $('#aura-field-name').val().trim();
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
            sort_order:          $('#aura-field-sort').val(),
            responsible_user_id: $('#aura-field-responsible').val(),
            parent_area_id:      $('#aura-field-parent').val(),
        }, data => {
            showNotice(data.message || s.saved, 'success');
            closeModal();
            loadAreas(state.page);
        });

        setTimeout(() => {
            $('#aura-save-spinner').hide();
            $('#aura-area-save-btn').prop('disabled', false);
        }, 2000);
    }

    /* ── Archivar / reactivar ────────────────────────────────────────── */
    function toggleArchive(id, action) {
        const msg = action === 'reactivate' ? s.confirmReactivate : s.confirmArchive;
        if (!confirm(msg)) return;

        ajax('aura_areas_delete', { area_id: id, archive_action: action }, data => {
            showNotice(data.message, 'success');
            loadAreas(state.page);
        });
    }

    /* ── Eventos ─────────────────────────────────────────────────────── */
    $(document).ready(function() {

        // Inicializar Color Picker
        $('.aura-color-picker').wpColorPicker({
            change: function(event, ui) {
                updateIconPreview($('#aura-field-icon').val(), ui.color.toString());
            }
        });

        // Selección de ícono desde el grid
        $(document).on('click', '.aura-icon-item', function(e) {
            e.preventDefault();
            const icon = $(this).data('icon');
            $('#aura-field-icon').val(icon);
            updateIconPreview(icon, $('#aura-field-color').val() || '#2271b1');
        });

        // Buscador de iconos
        $(document).on('input', '#aura-icon-search', function() {
            const q = $(this).val().toLowerCase().trim();
            $('#aura-icon-grid .aura-icon-item').each(function() {
                const label = ($(this).data('label') || '').toLowerCase();
                const icon  = ($(this).data('icon')  || '').toLowerCase();
                $(this).toggle(!q || label.includes(q) || icon.includes(q));
            });
        });

        // Botón agregar
        $('#aura-add-area-btn').on('click', () => openModal('Nueva Área', null));

        // Botón editar (delegado)
        $(document).on('click', '.aura-edit-btn', function() {
            const id = $(this).data('id');
            ajax('aura_areas_get', { area_id: id }, data => {
                openModal('Editar Área', data.area);
            });
        });

        // Archivar (delegado)
        $(document).on('click', '.aura-archive-btn', function() {
            toggleArchive($(this).data('id'), 'archive');
        });

        // Reactivar (delegado)
        $(document).on('click', '.aura-reactivate-btn', function() {
            toggleArchive($(this).data('id'), 'reactivate');
        });

        // Cerrar modal
        $('#aura-area-modal-close, #aura-area-cancel-btn').on('click', closeModal);
        $('#aura-area-modal-overlay').on('click', closeModal);
        $(document).on('keydown', e => { if (e.key === 'Escape') closeModal(); });

        // Guardar
        $('#aura-area-save-btn').on('click', saveArea);

        // Filtros
        $('#aura-apply-filters-btn').on('click', () => loadAreas(1));
        $('#aura-filter-search').on('keypress', e => { if (e.which === 13) loadAreas(1); });
        $('#aura-clear-filters-btn').on('click', () => {
            $('#aura-filter-search').val('');
            $('#aura-filter-type').val('');
            $('#aura-filter-status').val('active');
            loadAreas(1);
        });

        // Paginación
        $('#aura-areas-prev').on('click', () => { if (state.page > 1) loadAreas(state.page - 1); });
        $('#aura-areas-next').on('click', () => { if (state.page < state.totalPages) loadAreas(state.page + 1); });

        // Carga inicial
        loadAreas(1);
    });

})(jQuery);
</script>
