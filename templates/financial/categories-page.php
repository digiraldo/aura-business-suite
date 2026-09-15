<?php
/**
 * Template: Página de Gestión de Categorías Financieras — Árbol Jerárquico con Drag & Drop
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="aura-app-wrapper aura-categories-page">
    <div class="wrap aura-app-context">
        
        <!-- CABECERA PRINCIPAL HERO GLASS CARD -->
        <header class="aura-glass-card aura-page-header">
            <div class="aura-page-header__icon">
                <span class="dashicons dashicons-category"></span>
            </div>
            <div class="aura-page-header__content">
                <h1><?php esc_html_e('Categorías Financieras', 'aura-suite'); ?></h1>
                <p><?php esc_html_e('Clasificación jerárquica con soporte Drag & Drop fluido para Ingresos, OpEx y CapEx.', 'aura-suite'); ?></p>
            </div>
            <div class="aura-page-header__badges">
                <span class="aura-badge aura-badge-blue" id="hero-badge-total" data-tooltip="<?php esc_attr_e('Total de categorías en el catálogo', 'aura-suite'); ?>">
                    <span id="hero-count-total">0</span> <?php esc_html_e('categorías', 'aura-suite'); ?>
                </span>
                <span class="aura-badge aura-badge-gray" id="hero-badge-parents" data-tooltip="<?php esc_attr_e('Categorías principales o raíces', 'aura-suite'); ?>">
                    <span id="hero-count-parents">0</span> <?php esc_html_e('principales', 'aura-suite'); ?>
                </span>
                <span class="aura-badge aura-badge-gray" id="hero-badge-subs" data-tooltip="<?php esc_attr_e('Subcategorías anidadas', 'aura-suite'); ?>">
                    <span id="hero-count-subs">0</span> <?php esc_html_e('subcategorías', 'aura-suite'); ?>
                </span>
            </div>
        </header>

        <!-- BARRA DE ACCIONES PRINCIPALES -->
        <div class="aura-cat-top-actions">
            <div class="aura-actions-left">
                <button type="button" class="button button-secondary aura-btn-secondary" id="aura-export-cat-btn" onclick="exportAuraCategoriesCSV(this)">
                    <span class="dashicons dashicons-download"></span>
                    <span><?php esc_html_e('Exportar CSV', 'aura-suite'); ?></span>
                </button>
                <button type="button" class="button button-secondary aura-btn-secondary" id="aura-merge-categories-btn">
                    <span class="dashicons dashicons-randomize"></span>
                    <span><?php esc_html_e('Fusionar Categorías', 'aura-suite'); ?></span>
                </button>
            </div>
            <div class="aura-actions-right">
                <button type="button" class="button button-link-delete aura-btn-danger" id="aura-delete-all-btn">
                    <span class="dashicons dashicons-trash"></span>
                    <span><?php esc_html_e('Eliminar Todas', 'aura-suite'); ?></span>
                </button>
                <button type="button" class="button button-primary aura-btn-primary" id="aura-add-category-btn">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <span><?php esc_html_e('Nueva Categoría', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>

        <script>
        function exportAuraCategoriesCSV(btn) {
            const button = btn || document.getElementById('aura-export-cat-btn');
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; vertical-align: middle;"></span> <?php echo esc_js(__('Exportando...', 'aura-suite')); ?>';
            
            const reqNonce = (typeof auraCategories !== 'undefined' && auraCategories.nonce) ? auraCategories.nonce : '';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=aura_export_categories&nonce=' + encodeURIComponent(reqNonce)
            })
            .then(res => res.json())
            .then(res => {
                button.disabled = false;
                button.innerHTML = originalText;
                if (res.success && res.data && res.data.content) {
                    const byteCharacters = atob(res.data.content);
                    const byteNumbers = new Array(byteCharacters.length);
                    for (let i = 0; i < byteCharacters.length; i++) {
                        byteNumbers[i] = byteCharacters.charCodeAt(i);
                    }
                    const byteArray = new Uint8Array(byteNumbers);
                    const blob = new Blob([byteArray], { type: res.data.mime || 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = res.data.filename || 'categorias.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                } else {
                    alert(res.data?.message || 'Error al exportar categorías');
                }
            })
            .catch(err => {
                button.disabled = false;
                button.innerHTML = originalText;
                alert('Error de conexión al exportar.');
            });
        }
        </script>

        <!-- BANNER DE INSTRUCCIÓN DRAG & DROP -->
        <div class="aura-cat-tip-banner aura-glass-card">
            <span class="aura-cat-tip-icon">💡</span>
            <div class="aura-cat-tip-text">
                <strong><?php esc_html_e('Organización Interactiva y Jerarquía:', 'aura-suite'); ?></strong>
                <?php esc_html_e('Arrastra desde el ícono', 'aura-suite'); ?> <span class="aura-badge-kbd">⠿</span> <?php esc_html_e('de cualquier categoría para reordenarla o anidarla como subcategoría dentro de otra. Para sacarla a categoría principal, suéltala en las zonas de soltado raíz superior o inferior, o haz clic en', 'aura-suite'); ?> <strong>⬅ <?php esc_html_e('Sacar a Principal', 'aura-suite'); ?></strong>.
            </div>
        </div>

        <!-- BARRA DE FILTROS / PESTAÑAS (STICKY COMPACTA) -->
        <div class="aura-cat-filter-bar" id="aura-sticky-filter-bar">
            <div class="aura-cat-tabs" id="category-tabs">
                <button type="button" class="button button-primary tab-filter active" data-filter="active" data-tooltip="<strong>Categorías Activas:</strong> Muestra sólo las categorías y subcategorías habilitadas para registrar transacciones operativas.">
                    <span class="aura-filter-dot dot-active"></span>
                    <?php esc_html_e('Activas', 'aura-suite'); ?> <span class="aura-filter-count" id="count-active">0</span>
                </button>
                <button type="button" class="button button-secondary tab-filter" data-filter="income" data-tooltip="<strong>Categorías de Ingresos:</strong> Agrupa todos los conceptos de captación de recursos, ventas, cobros y entradas de capital.">
                    📈 <?php esc_html_e('Ingresos', 'aura-suite'); ?> <span class="aura-filter-count" id="count-income">0</span>
                </button>
                <button type="button" class="button button-secondary tab-filter" data-filter="expense" data-tooltip="<strong>Gastos Operativos (OpEx):</strong> Egresos habituales del día a día necesarios para el funcionamiento de la empresa.">
                    📉 <?php esc_html_e('Egresos OpEx', 'aura-suite'); ?> <span class="aura-filter-count" id="count-expense">0</span>
                </button>
                <button type="button" class="button button-secondary tab-filter" data-filter="capital" data-tooltip="<strong>Gastos de Capital (CapEx):</strong> Inversiones estratégicas en adquisición, modernización o mejora de activos fijos e infraestructura.">
                    🏗️ <?php esc_html_e('CapEx', 'aura-suite'); ?> <span class="aura-filter-count" id="count-capital">0</span>
                </button>
                <button type="button" class="button button-secondary tab-filter" data-filter="unused" data-tooltip="<strong>Categorías Sin Movimientos:</strong> Categorías y subcategorías con 0 transacciones asociadas. Es 100% seguro editarlas, fusionarlas o eliminarlas sin afectar el balance.">
                    🧹 <?php esc_html_e('Sin Uso', 'aura-suite'); ?> <span class="aura-filter-count" id="count-unused">0</span>
                </button>
                <button type="button" class="button button-secondary tab-filter tab-filter--archived" data-filter="archived" data-tooltip="<strong>Categorías Archivadas:</strong> Categorías desactivadas que han sido pausadas. No aparecen en formularios pero preservan su historial contable.">
                    📦 <?php esc_html_e('Archivadas', 'aura-suite'); ?> <span class="aura-filter-count" id="count-archived">0</span>
                </button>
                <button type="button" class="button button-secondary tab-filter" data-filter="all" data-tooltip="<strong>Catálogo Completo:</strong> Muestra todas las categorías principales y subcategorías del sistema sin ningún filtro.">
                    🌐 <?php esc_html_e('Todas', 'aura-suite'); ?> <span class="aura-filter-count" id="count-all">0</span>
                </button>
            </div>
        </div>

        <!-- BARRA DE BÚSQUEDA Y CONTROLES (NO STICKY) -->
        <div class="aura-card aura-cat-search-bar" id="aura-cat-search-bar">
            <div class="aura-input-group aura-cat-search-group">
                <span class="aura-input-group-text"><span class="dashicons dashicons-search"></span></span>
                <input type="text" id="cat-search-input" class="aura-input" placeholder="<?php esc_attr_e('Buscar por nombre o slug...', 'aura-suite'); ?>">
            </div>
            <div class="aura-cat-tree-actions">
                <button type="button" id="btn-expand-all" class="button button-secondary aura-btn-icon-text" data-tooltip="Expandir todas las ramas y subcategorías del árbol">
                    <span>🔽</span> <?php esc_html_e('Expandir', 'aura-suite'); ?>
                </button>
                <button type="button" id="btn-collapse-all" class="button button-secondary aura-btn-icon-text" data-tooltip="Colapsar todas las categorías para una vista compacta de sólo padres">
                    <span>🔼</span> <?php esc_html_e('Colapsar', 'aura-suite'); ?>
                </button>
            </div>
        </div>

        <!-- CONTENEDOR PRINCIPAL DEL ÁRBOL JERÁRQUICO -->
        <div class="aura-card aura-cat-tree-card">
            <!-- Dropzone Raíz Superior -->
            <div id="aura-root-dropzone-top" class="aura-root-drop-zone aura-root-drop-top" style="display:none;">
                <span class="aura-dropzone-icon">⭐</span>
                <span><?php esc_html_e('Soltar aquí para convertir en Categoría Principal (al inicio)', 'aura-suite'); ?></span>
            </div>

            <div id="categories-tree-container">
                <div class="aura-cat-tree-loading">
                    <span class="spinner is-active"></span>
                    <p><?php esc_html_e('Cargando árbol de categorías financieras...', 'aura-suite'); ?></p>
                </div>
            </div>

            <!-- Dropzone Raíz Inferior -->
            <div id="aura-root-dropzone-bottom" class="aura-root-drop-zone aura-root-drop-bottom" style="display:none;">
                <span class="aura-dropzone-icon">⭐</span>
                <span><?php esc_html_e('Soltar aquí para convertir en Categoría Principal (al final)', 'aura-suite'); ?></span>
            </div>
        </div>

    </div>
</div>

<!-- ============================================== -->
<!-- MODALES DEL SISTEMA AURA (Patrón .active)      -->
<!-- ============================================== -->

<!-- Modal Crear / Editar Categoría -->
<div id="aura-category-modal" class="aura-modal-overlay">
    <div class="aura-modal-content" style="max-width: 660px;">
        <div class="aura-modal-header">
            <h2 class="aura-modal-title" id="aura-modal-title"><?php esc_html_e('Nueva Categoría', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close" data-close-modal>
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div id="aura-modal-loading" style="display: none; text-align:center; padding: 20px;">
                <span class="spinner is-active" style="float:none;"></span>
                <p><?php esc_html_e('Cargando...', 'aura-suite'); ?></p>
            </div>

            <form id="aura-category-form" autocomplete="off">
                <input type="hidden" id="category-id" name="category_id" value="">
                <input type="hidden" id="category-order" name="display_order" value="0">
                
                <div class="aura-field-group">
                    <div class="aura-field">
                        <label class="aura-label" for="category-name">
                            <?php esc_html_e('Nombre de la categoría', 'aura-suite'); ?> <span style="color:#ef4444;">*</span>
                            <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Nombre descriptivo que identificará la categoría en transacciones y presupuestos.', 'aura-suite'); ?></span>
                            </span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-tag"></span></span>
                            <input type="text" id="category-name" name="name" class="aura-input" placeholder="<?php esc_attr_e('Ej. Ventas, Suministros, Mantenimiento...', 'aura-suite'); ?>" required>
                        </div>
                    </div>

                    <div class="aura-field">
                        <label class="aura-label" for="category-slug">
                            <?php esc_html_e('Slug (opcional)', 'aura-suite'); ?>
                            <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Identificador URL único. Si lo dejas vacío se genera automáticamente.', 'aura-suite'); ?></span>
                            </span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-admin-links"></span></span>
                            <input type="text" id="category-slug" name="slug" class="aura-input" placeholder="se-genera-automaticamente">
                        </div>
                    </div>
                    
                    <div class="aura-form-row-2col">
                        <div class="aura-field">
                            <label class="aura-label" for="category-type">
                                <?php esc_html_e('Tipo de flujo', 'aura-suite'); ?> <span style="color:#ef4444;">*</span>
                                <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                    <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                    <span class="aura-tooltip-content"><?php esc_html_e('Define si esta categoría aplica a Ingresos, Egresos (OpEx) o Ambos.', 'aura-suite'); ?></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-chart-area"></span></span>
                                <select name="type" id="category-type" class="aura-input" required>
                                    <option value="income"><?php esc_html_e('📈 Ingresos', 'aura-suite'); ?></option>
                                    <option value="expense"><?php esc_html_e('📉 Egresos (OpEx)', 'aura-suite'); ?></option>
                                    <option value="both" selected><?php esc_html_e('🔄 Ambos Flujos', 'aura-suite'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="category-icon">
                                <span><?php esc_html_e('Ícono / Emoji', 'aura-suite'); ?></span>
                                <span class="aura-label-tip">
                                    <?php esc_html_e('Tip:', 'aura-suite'); ?> <kbd>Win + .</kbd>
                                </span>
                            </label>
                            <div class="aura-input-group aura-icon-input-wrap">
                                <div id="category-icon-preview" class="aura-icon-live-preview" title="<?php esc_attr_e('Vista previa del ícono', 'aura-suite'); ?>">
                                    📁
                                </div>
                                <input type="text" id="category-icon" name="icon" class="aura-input" value="📁" placeholder="📁, 💰, 💼, dashicons-money...">
                            </div>
                        </div>
                    </div>

                    <!-- Paleta Rápida de Emojis y Dashicons Mejorada -->
                    <div class="aura-field aura-icon-picker-section">
                        <div class="aura-picker-header" id="aura-toggle-picker-header" style="cursor: pointer;" title="<?php esc_attr_e('Clic para mostrar u ocultar sugerencias', 'aura-suite'); ?>">
                            <span class="aura-picker-title">
                                <span>✨</span> <?php esc_html_e('Sugerencias Rápidas de Íconos', 'aura-suite'); ?>
                            </span>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="aura-picker-help">
                                    <?php esc_html_e('Shift + Clic = combinar', 'aura-suite'); ?>
                                </span>
                                <span class="dashicons dashicons-arrow-up-alt2 aura-picker-toggle-arrow" style="font-size:14px;width:14px;height:14px;color:#64748b;transition:transform 0.2s ease;"></span>
                            </div>
                        </div>
                        <div id="aura-picker-body-content">

                        <?php
                        $emoji_groups = [
                            'Finanzas y Negocios' => [
                                ['💰','Dinero'], ['💵','Efectivo'], ['💳','Tarjeta'], ['🏦','Banco'],
                                ['🪙','Monedas'], ['📈','Ingresos'], ['📉','Gastos'], ['📊','Reportes'],
                                ['🧾','Facturas'], ['💼','Negocio'], ['🏢','Corporativo'], ['🤝','Socios'], ['📑','Contratos'],
                            ],
                            'Mantenimiento, Limpieza e Instalaciones' => [
                                ['🏊','Piscina'], ['🛠️','Taller'], ['🔧','Herramientas'], ['🧼','Limpieza'],
                                ['🧹','Aseo'], ['🧪','Químicos'], ['💧','Agua'], ['🍃','Jardín'],
                                ['🌱','Plantas'], ['🏠','Inmueble'], ['💡','Iluminación'], ['🛡️','Seguridad'],
                            ],
                            'Vehículos, Transporte y Seguros' => [
                                ['🚗','Auto'], ['🚐','Van'], ['🚚','Camión'], ['⛽','Gasolina'],
                                ['🛡️','Seguro'], ['📋','Trámites'], ['💥','Siniestros'], ['✈️','Viajes'],
                                ['🍔','Restaurantes'], ['🍕','Alimentos'], ['☕','Refrigerios'], ['⭐','Prioritario'],
                            ],
                        ];
                        foreach ($emoji_groups as $group_label => $emojis): ?>
                        <div class="aura-emoji-group">
                            <div class="aura-emoji-group-label"><?php echo esc_html($group_label); ?></div>
                            <div class="aura-emoji-buttons-wrap">
                                <?php foreach ($emojis as [$icon, $label]): ?>
                                <button type="button" class="aura-emoji-btn" data-icon="<?php echo esc_attr($icon); ?>" title="<?php echo esc_attr($label); ?>">
                                    <?php echo $icon; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Combinaciones Frecuentes (Pares Útiles) -->
                        <div class="aura-combos-section">
                            <div class="aura-combos-title">
                                <span>💡</span> <?php esc_html_e('Combinaciones Frecuentes (Pares Útiles)', 'aura-suite'); ?>
                            </div>
                            <div class="aura-combos-buttons-wrap">
                                <?php
                                $combos = [
                                    ['🛠️🏊', 'Soporte\nPiscina'],
                                    ['🧼🏊', 'Limpieza\nPiscina'],
                                    ['🧪💧', 'Químicos\nAgua'],
                                    ['🍃🧹', 'Jardín\nResiduos'],
                                    ['🛡️🚗', 'Seguro\nAuto'],
                                    ['📋🚗', 'Trámites\nAuto'],
                                    ['💥🚗', 'Siniestros\nAuto'],
                                    ['🔧🚗', 'Taller\nAuto'],
                                    ['⛽🚗', 'Gasolina\nVehículo'],
                                    ['💰📈', 'Ingresos\nOp.'],
                                ];
                                foreach ($combos as [$combo_icon, $combo_label]):
                                    $label_html = nl2br(esc_html($combo_label));
                                ?>
                                <button type="button" class="aura-emoji-combo-btn" data-icon="<?php echo esc_attr($combo_icon); ?>" title="<?php echo esc_attr(str_replace('\n', ' ', $combo_label)); ?>">
                                    <span class="aura-combo-icons"><?php echo $combo_icon; ?></span>
                                    <span class="aura-combo-text"><?php echo $label_html; ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Dashicons sugeridos -->
                        <div class="aura-dashicons-section">
                            <div class="aura-emoji-group-label"><?php esc_html_e('Íconos WordPress (Dashicons)', 'aura-suite'); ?></div>
                            <div class="aura-emoji-buttons-wrap">
                                <?php
                                $dashicons = [
                                    ['dashicons-category','Categoría'], ['dashicons-money-alt','Dinero'], ['dashicons-cart','Compras'],
                                    ['dashicons-building','Edificio'], ['dashicons-car','Auto'], ['dashicons-hammer','Herramienta'],
                                    ['dashicons-tag','Etiqueta'], ['dashicons-chart-pie','Gráfico'], ['dashicons-admin-users','Personal'],
                                    ['dashicons-clipboard','Reportes'], ['dashicons-awards','Premios'], ['dashicons-store','Tienda'],
                                    ['dashicons-tickets-alt','Tickets'], ['dashicons-products','Productos'], ['dashicons-coffee','Cafetería'],
                                ];
                                foreach ($dashicons as [$dash_class, $dash_label]): ?>
                                <button type="button" class="aura-dashicon-btn" data-icon="<?php echo esc_attr($dash_class); ?>" title="<?php echo esc_attr($dash_label); ?>">
                                    <span class="dashicons <?php echo esc_attr($dash_class); ?>"></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="aura-field" id="category-parent-selector">
                        <label class="aura-label" for="category-parent-id">
                            <?php esc_html_e('Categoría Padre (Jerarquía)', 'aura-suite'); ?>
                            <span class="aura-tooltip-wrap aura-tooltip-wrap--bottom">
                                <span class="dashicons dashicons-editor-help aura-help-icon"></span>
                                <span class="aura-tooltip-content"><?php esc_html_e('Selecciona una categoría padre para convertirla en subcategoría, o déjala como Ninguna para que sea categoría principal.', 'aura-suite'); ?></span>
                            </span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-category"></span></span>
                            <select id="category-parent-id" name="parent_id" class="aura-input">
                                <option value="0"><?php esc_html_e('--- Ninguna (Categoría Padre / Raíz) ---', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="aura-field">
                        <div class="aura-field-header-split">
                            <label class="aura-label" for="category-color">
                                <?php esc_html_e('Color de Identificación', 'aura-suite'); ?>
                            </label>
                            <span class="aura-label-tip"><?php esc_html_e('Selecciona un color predefinido o personalízalo', 'aura-suite'); ?></span>
                        </div>
                        <div class="aura-color-picker-wrapper">
                            <input type="color" id="category-color" name="color" value="#5D5FEF" title="<?php esc_attr_e('Color personalizado', 'aura-suite'); ?>">
                            
                            <!-- Paleta Ampliada de Colores -->
                            <div class="aura-color-presets-list">
                                <?php
                                $preset_colors = array(
                                    array('hex' => '#5D5FEF', 'name' => __('Azul Eléctrico (Principal)', 'aura-suite')),
                                    array('hex' => '#E05300', 'name' => __('Naranja Intenso', 'aura-suite')),
                                    array('hex' => '#00B67A', 'name' => __('Verde Esmeralda', 'aura-suite')),
                                    array('hex' => '#FF9F1C', 'name' => __('Amarillo Caléndula', 'aura-suite')),
                                    array('hex' => '#3A86FF', 'name' => __('Azul Brillante', 'aura-suite')),
                                    array('hex' => '#9B5DE5', 'name' => __('Violeta Vibrante', 'aura-suite')),
                                    array('hex' => '#06B6D4', 'name' => __('Turquesa Cyan', 'aura-suite')),
                                    array('hex' => '#F15BB5', 'name' => __('Rosa Neón/Fucsia', 'aura-suite')),
                                    array('hex' => '#00F5D4', 'name' => __('Aguamarina Eléctrico', 'aura-suite')),
                                    array('hex' => '#FF006E', 'name' => __('Magenta Vivo', 'aura-suite')),
                                    array('hex' => '#70E000', 'name' => __('Verde Lima Neón', 'aura-suite')),
                                    array('hex' => '#FFBE0B', 'name' => __('Amarillo Oro', 'aura-suite')),
                                    array('hex' => '#FB5607', 'name' => __('Naranja Rojizo', 'aura-suite')),
                                    array('hex' => '#8338EC', 'name' => __('Púrpura Profundo', 'aura-suite')),
                                    array('hex' => '#0077B6', 'name' => __('Azul Océano', 'aura-suite')),
                                    array('hex' => '#00F5D4', 'name' => __('Verde Menta Vivo', 'aura-suite')),
                                    array('hex' => '#FF70A6', 'name' => __('Salmón Encendido', 'aura-suite')),
                                    array('hex' => '#A2D2FF', 'name' => __('Azul Pastel Brillante', 'aura-suite')),
                                    array('hex' => '#D90429', 'name' => __('Rojo Carmín', 'aura-suite')),
                                    array('hex' => '#4CC9F0', 'name' => __('Azul Cielo Eléctrico', 'aura-suite')),
                                );
                                foreach ($preset_colors as $p_col):
                                ?>
                                    <span class="aura-color-preset" 
                                          style="background:<?php echo esc_attr($p_col['hex']); ?>;" 
                                          data-color="<?php echo esc_attr(strtolower($p_col['hex'])); ?>" 
                                          title="<?php echo esc_attr($p_col['name']); ?>"></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="aura-field">
                        <label class="aura-label" for="category-description">
                            <span class="dashicons dashicons-editor-alignleft" style="font-size:16px;width:16px;height:16px;color:#64748b;vertical-align:middle;"></span>
                            <?php esc_html_e('Descripción del Propósito o Alcance (Opcional)', 'aura-suite'); ?>
                        </label>
                        <textarea id="category-description" name="description" class="aura-input aura-textarea" rows="3" placeholder="<?php esc_attr_e('Describe el alcance, reglas o qué tipos de gastos/ingresos se registran bajo esta categoría...', 'aura-suite'); ?>"></textarea>
                    </div>

                    <!-- Tarjetas Toggle para Estado Activo y Naturaleza CapEx -->
                    <div class="aura-toggle-cards-row">
                        <label class="aura-toggle-card" for="category-active">
                            <input type="checkbox" id="category-active" name="is_active" value="1" checked>
                            <div class="aura-toggle-card-content">
                                <div class="aura-toggle-card-title">
                                    <span class="aura-toggle-dot aura-toggle-dot--active"></span>
                                    <?php esc_html_e('Categoría Activa', 'aura-suite'); ?>
                                </div>
                                <div class="aura-toggle-card-desc">
                                    <?php esc_html_e('Habilitada para registrar nuevos movimientos contables.', 'aura-suite'); ?>
                                </div>
                            </div>
                        </label>

                        <label class="aura-toggle-card" for="category-is-capex">
                            <input type="checkbox" id="category-is-capex" name="is_capex" value="1">
                            <div class="aura-toggle-card-content">
                                <div class="aura-toggle-card-title">
                                    <span>🏗️</span>
                                    <?php esc_html_e('Naturaleza CapEx (Capital)', 'aura-suite'); ?>
                                </div>
                                <div class="aura-toggle-card-desc">
                                    <?php esc_html_e('Inversión en activos fijos (no gasto operativo OpEx).', 'aura-suite'); ?>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-btn-secondary" data-close-modal><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
            <button type="button" class="button button-primary aura-btn-primary" id="aura-save-btn">
                <span class="dashicons dashicons-saved" style="margin-top: 2px;"></span>
                <span id="aura-save-btn-text"><?php esc_html_e('Guardar Categoría', 'aura-suite'); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminar -->
<div id="aura-confirm-delete-modal" class="aura-modal-overlay">
    <div class="aura-modal-content" style="max-width: 440px;">
        <div class="aura-modal-header">
            <h2 class="aura-modal-title" style="color:#dc2626;display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Confirmar Eliminación', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" data-close-modal>
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="aura-modal-body">
            <p id="aura-delete-confirm-msg"><?php esc_html_e('¿Estás seguro de que deseas eliminar esta categoría? Si tiene subcategorías, pasarán a ser categorías principales.', 'aura-suite'); ?></p>
            <input type="hidden" id="aura-delete-id" value="">
        </div>
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-btn-secondary" data-close-modal><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
            <button type="button" class="button button-primary aura-btn-danger" id="aura-confirm-delete-btn"><?php esc_html_e('Eliminar', 'aura-suite'); ?></button>
        </div>
    </div>
</div>

<!-- Modal Eliminar Todas -->
<div id="aura-delete-all-modal" class="aura-modal-overlay">
    <div class="aura-modal-content" style="max-width: 450px;">
        <div class="aura-modal-header">
            <h2 class="aura-modal-title" style="color:#ef4444;display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-warning"></span> <?php esc_html_e('Eliminar Categorías', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" data-close-modal>
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="aura-modal-body">
            <p><?php esc_html_e('¿Eliminar todas las categorías? Esta acción no afectará aquellas que tengan transacciones asociadas.', 'aura-suite'); ?></p>
            <div style="margin-top:14px;">
                <label class="aura-checkbox-label">
                    <input type="checkbox" id="aura-delete-all-confirm-check">
                    <span><?php esc_html_e('Entiendo que esta acción es irreversible', 'aura-suite'); ?></span>
                </label>
            </div>
        </div>
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-btn-secondary" data-close-modal><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
            <button type="button" class="button button-primary aura-btn-danger" id="aura-delete-all-confirm" disabled><?php esc_html_e('Sí, eliminar', 'aura-suite'); ?></button>
        </div>
    </div>
</div>

<!-- Modal Fusión de Categorías -->
<div id="aura-merge-modal" class="aura-modal-overlay">
    <div class="aura-modal-content" style="max-width: 640px;">
        <div class="aura-modal-header">
            <h2 class="aura-modal-title" style="display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-randomize"></span>
                <?php esc_html_e('Fusión y Reasignación de Categorías', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" data-close-modal>
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <!-- Noticia informativa -->
            <div class="aura-merge-info-notice">
                <span class="dashicons dashicons-info"></span>
                <span><?php esc_html_e('Esta herramienta migra automáticamente todas las transacciones históricas, presupuestos y subcategorías de la categoría de origen hacia la de destino.', 'aura-suite'); ?></span>
            </div>

            <form id="aura-merge-form" autocomplete="off">
                <!-- Selectores de Categorías Origen y Destino -->
                <div class="aura-form-row-2col" style="margin-bottom:16px;">
                    <div class="aura-field">
                        <label class="aura-label" for="aura-merge-source">
                            <?php esc_html_e('Categoría Origen (A vaciar)', 'aura-suite'); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-upload"></span></span>
                            <select id="aura-merge-source" class="aura-input" required>
                                <option value=""><?php esc_html_e('Selecciona origen...', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="aura-field">
                        <label class="aura-label" for="aura-merge-target">
                            <?php esc_html_e('Categoría Destino (Receptora)', 'aura-suite'); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-text"><span class="dashicons dashicons-download"></span></span>
                            <select id="aura-merge-target" class="aura-input" required>
                                <option value=""><?php esc_html_e('Selecciona destino...', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta de Vista Previa de Impacto en Vivo -->
                <div id="aura-merge-preview-card" class="aura-merge-preview-card" style="display:none;">
                    <div class="aura-merge-preview-header">
                        📊 <?php esc_html_e('Vista Previa de Impacto en Vivo', 'aura-suite'); ?>
                    </div>
                    
                    <div class="aura-merge-comparison">
                        <!-- Caja Origen -->
                        <div class="aura-merge-box aura-merge-box-source">
                            <div class="aura-merge-box-label label-source"><?php esc_html_e('Origen', 'aura-suite'); ?></div>
                            <div id="merge-prev-source-name" class="aura-merge-box-title">—</div>
                            <div class="aura-merge-box-badges">
                                <span id="merge-prev-source-tx" class="aura-badge badge-source-tx">0 txs</span>
                                <span id="merge-prev-source-sub" class="aura-badge badge-source-sub">0 hijas</span>
                            </div>
                        </div>

                        <!-- Flecha -->
                        <div class="aura-merge-arrow">
                            ➔
                        </div>

                        <!-- Caja Destino -->
                        <div class="aura-merge-box aura-merge-box-target">
                            <div class="aura-merge-box-label label-target"><?php esc_html_e('Destino Resultante', 'aura-suite'); ?></div>
                            <div id="merge-prev-target-name" class="aura-merge-box-title">—</div>
                            <div class="aura-merge-box-badges">
                                <span id="merge-prev-target-total" class="aura-badge badge-target-total">0 txs totales</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Opciones de Reasignación -->
                <div class="aura-merge-options-box">
                    <div class="aura-merge-options-title">
                        ⚙️ <?php esc_html_e('Opciones de Consolidación:', 'aura-suite'); ?>
                    </div>

                    <!-- Mover subcategorías -->
                    <div style="margin-bottom:12px;">
                        <label class="aura-checkbox-label">
                            <input type="checkbox" id="aura-merge-reassign-children" name="reassign_children" value="1" checked>
                            <span><?php esc_html_e('Mover subcategorías hijas de la categoría origen a la categoría destino', 'aura-suite'); ?></span>
                        </label>
                    </div>

                    <!-- Acción Post Fusión -->
                    <div class="aura-merge-action-label">
                        <?php esc_html_e('¿Qué hacer con la categoría origen al finalizar?', 'aura-suite'); ?>
                    </div>
                    <div class="aura-merge-radios">
                        <label class="aura-radio-label">
                            <input type="radio" name="post_action" value="archive" checked>
                            <span><strong><?php esc_html_e('Desactivar / Archivar', 'aura-suite'); ?></strong> <span class="aura-radio-tip">(<?php esc_html_e('Recomendado: conserva trazabilidad histórica', 'aura-suite'); ?>)</span></span>
                        </label>
                        <label class="aura-radio-label">
                            <input type="radio" name="post_action" value="delete">
                            <span><strong><?php esc_html_e('Eliminar permanentemente', 'aura-suite'); ?></strong> <span class="aura-radio-danger">(<?php esc_html_e('Seguro: la categoría quedará con 0 transacciones', 'aura-suite'); ?>)</span></span>
                        </label>
                        <label class="aura-radio-label">
                            <input type="radio" name="post_action" value="keep">
                            <span><strong><?php esc_html_e('Conservar activa pero vacía', 'aura-suite'); ?></strong></span>
                        </label>
                    </div>
                </div>

                <div id="aura-merge-warning" class="aura-merge-warning" style="display:none;"></div>
            </form>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-btn-secondary" data-close-modal><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
            <button type="button" class="button button-primary aura-btn-primary" id="aura-confirm-merge-btn" disabled>
                <span class="dashicons dashicons-randomize"></span>
                <?php esc_html_e('Ejecutar Fusión', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Toast Notifier Flotante -->
<div id="aura-category-toast" class="aura-toast">
    <span id="aura-toast-icon">✓</span>
    <span id="aura-toast-msg"><?php esc_html_e('Operación completada', 'aura-suite'); ?></span>
</div>
