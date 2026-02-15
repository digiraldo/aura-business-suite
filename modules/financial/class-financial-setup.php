<?php
/**
 * Financial Setup - Install Default Categories
 * 
 * Gestiona la instalación de categorías predeterminadas del módulo financiero,
 * incluyendo categorías personalizadas para instituto con actividades múltiples.
 *
 * @package    Aura_Business_Suite
 * @subpackage Financial
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Aura_Financial_Setup
 * 
 * Maneja la configuración inicial del módulo financiero
 */
class Aura_Financial_Setup {
    
    /**
     * Tabla de categorías financieras
     *
     * @var string
     */
    private $table_categories;
    
    /**
     * Usuario ID para categorías del sistema
     *
     * @var int
     */
    private $system_user_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_categories = $wpdb->prefix . 'aura_finance_categories';
        $this->system_user_id = 1; // Admin user
    }
    
    /**
     * Instalar categorías predeterminadas
     * Se ejecuta en la activación del plugin o manualmente desde ajustes
     *
     * @param bool $force_reinstall Reinstalar incluso si ya existen categorías
     * @return array Array con resultado: success, message, stats
     */
    public function install_default_categories($force_reinstall = false) {
        global $wpdb;
        
        // Verificar si ya existen categorías
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories}");
        
        if ($existing_count > 0 && !$force_reinstall) {
            return [
                'success' => false,
                'message' => __('Ya existen categorías en el sistema. Use la opción "Reinstalar" para sobrescribir.', 'aura'),
                'stats' => [
                    'existing' => $existing_count,
                    'created' => 0,
                ]
            ];
        }
        
        // Si es reinstalación, eliminar categorías del sistema existentes
        if ($force_reinstall) {
            $wpdb->query("DELETE FROM {$this->table_categories} WHERE created_by = 1 AND id <= 1000");
        }
        
        $created_count = 0;
        $error_count = 0;
        $errors = [];
        
        // Array para mapear IDs de categorías padre
        $category_map = [];
        
        try {
            // ============================================
            // CATEGORÍAS DE INGRESOS
            // ============================================
            
            // 1. Donaciones
            $donaciones_id = $this->create_category([
                'name' => 'Donaciones',
                'slug' => 'donaciones',
                'type' => 'income',
                'color' => '#27ae60',
                'icon' => 'dashicons-heart',
                'description' => 'Donaciones recibidas de personas o instituciones',
                'order' => 10
            ]);
            
            if ($donaciones_id) {
                $created_count++;
                $category_map['donaciones'] = $donaciones_id;
                
                // Subcategorías de Donaciones
                $subcats_donaciones = [
                    ['name' => 'Donación General', 'slug' => 'donacion-general'],
                    ['name' => 'Donación Especial', 'slug' => 'donacion-especial'],
                    ['name' => 'Donación de Misiones', 'slug' => 'donacion-misiones'],
                    ['name' => 'Donación de Construcción', 'slug' => 'donacion-construccion'],
                    ['name' => 'Donación de Emergencia', 'slug' => 'donacion-emergencia'],
                    ['name' => 'Donación de Alimentos', 'slug' => 'donacion-alimentos'],
                    ['name' => 'Donación de Voluntarios', 'slug' => 'donacion-voluntarios'],
                ];
                
                foreach ($subcats_donaciones as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'income',
                        'parent_id' => $donaciones_id,
                        'color' => '#27ae60',
                        'icon' => 'dashicons-heart',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 2. Ofrendas
            $ofrendas_id = $this->create_category([
                'name' => 'Ofrendas',
                'slug' => 'ofrendas',
                'type' => 'income',
                'color' => '#2ecc71',
                'icon' => 'dashicons-groups',
                'description' => 'Ofrendas recibidas en servicios religiosos',
                'order' => 20
            ]);
            
            if ($ofrendas_id) {
                $created_count++;
                
                $subcats_ofrendas = [
                    ['name' => 'Ofrenda General', 'slug' => 'ofrenda-general'],
                    ['name' => 'Ofrenda Especial', 'slug' => 'ofrenda-especial'],
                    ['name' => 'Ofrenda de Misiones', 'slug' => 'ofrenda-misiones'],
                    ['name' => 'Ofrenda de Construcción', 'slug' => 'ofrenda-construccion'],
                    ['name' => 'Ofrenda de Emergencia', 'slug' => 'ofrenda-emergencia'],
                ];
                
                foreach ($subcats_ofrendas as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'income',
                        'parent_id' => $ofrendas_id,
                        'color' => '#2ecc71',
                        'icon' => 'dashicons-groups',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 3. Alquileres y Rentas (NUEVO - específico del instituto)
            $alquileres_id = $this->create_category([
                'name' => 'Alquileres y Rentas',
                'slug' => 'alquileres-rentas',
                'type' => 'income',
                'color' => '#3498db',
                'icon' => 'dashicons-admin-home',
                'description' => 'Ingresos por alquiler de instalaciones y equipos',
                'order' => 30
            ]);
            
            if ($alquileres_id) {
                $created_count++;
                
                $subcats_alquileres = [
                    ['name' => 'Alquiler de Instalaciones', 'slug' => 'alquiler-instalaciones', 'desc' => 'Alquiler de espacios generales'],
                    ['name' => 'Alquiler a Iglesias', 'slug' => 'alquiler-iglesias', 'desc' => 'Alquiler de instalaciones a iglesias cristianas'],
                    ['name' => 'Alquiler de Equipo de Sonido', 'slug' => 'alquiler-equipo-sonido', 'desc' => 'Alquiler de mixer, cabinas, micrófonos'],
                    ['name' => 'Alquiler de Kiosco/Terraza', 'slug' => 'alquiler-kiosco-terraza', 'desc' => 'Alquiler del kiosco o terraza para eventos'],
                ];
                
                foreach ($subcats_alquileres as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'income',
                        'parent_id' => $alquileres_id,
                        'color' => '#3498db',
                        'icon' => 'dashicons-admin-home',
                        'description' => $subcat['desc'] ?? '',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 4. Inscripciones y Matrículas (NUEVO - específico del instituto)
            $inscripciones_id = $this->create_category([
                'name' => 'Inscripciones y Matrículas',
                'slug' => 'inscripciones-matriculas',
                'type' => 'income',
                'color' => '#2980b9',
                'icon' => 'dashicons-welcome-learn-more',
                'description' => 'Ingresos por inscripciones de estudiantes y cursos',
                'order' => 40
            ]);
            
            if ($inscripciones_id) {
                $created_count++;
                
                $subcats_inscripciones = [
                    ['name' => 'Inscripción de Estudiantes', 'slug' => 'inscripcion-estudiantes'],
                    ['name' => 'Cursos y Talleres', 'slug' => 'cursos-talleres'],
                ];
                
                foreach ($subcats_inscripciones as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'income',
                        'parent_id' => $inscripciones_id,
                        'color' => '#2980b9',
                        'icon' => 'dashicons-welcome-learn-more',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 5-9. Otras categorías de ingresos
            $other_income_categories = [
                ['name' => 'Ventas de Productos', 'slug' => 'ventas-productos', 'color' => '#1e8a98', 'icon' => 'dashicons-cart', 'order' => 50],
                ['name' => 'Ventas de Servicios', 'slug' => 'ventas-servicios', 'color' => '#16a085', 'icon' => 'dashicons-admin-tools', 'order' => 60],
                ['name' => 'Subvenciones', 'slug' => 'subvenciones', 'color' => '#8e44ad', 'icon' => 'dashicons-money-alt', 'order' => 70],
                ['name' => 'Intereses Bancarios', 'slug' => 'intereses-bancarios', 'color' => '#9b59b6', 'icon' => 'dashicons-chart-line', 'order' => 80],
                ['name' => 'Otros Ingresos', 'slug' => 'otros-ingresos', 'color' => '#95a5a6', 'icon' => 'dashicons-plus-alt', 'order' => 90],
            ];
            
            foreach ($other_income_categories as $cat) {
                if ($this->create_category([
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'type' => 'income',
                    'color' => $cat['color'],
                    'icon' => $cat['icon'],
                    'order' => $cat['order']
                ])) {
                    $created_count++;
                }
            }
            
            // ============================================
            // CATEGORÍAS DE EGRESOS
            // ============================================
            
            // 1. Salarios y Sueldos
            $salarios_id = $this->create_category([
                'name' => 'Salarios y Sueldos',
                'slug' => 'salarios-sueldos',
                'type' => 'expense',
                'color' => '#e74c3c',
                'icon' => 'dashicons-groups',
                'description' => 'Pagos a personal y colaboradores',
                'order' => 10
            ]);
            
            if ($salarios_id) {
                $created_count++;
                
                $subcats_salarios = [
                    ['name' => 'Salario', 'slug' => 'salario'],
                    ['name' => 'Honorarios', 'slug' => 'honorarios'],
                    ['name' => 'Voluntarios', 'slug' => 'voluntarios'],
                ];
                
                foreach ($subcats_salarios as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'expense',
                        'parent_id' => $salarios_id,
                        'color' => '#e74c3c',
                        'icon' => 'dashicons-groups',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 2. Servicios Públicos
            $servicios_id = $this->create_category([
                'name' => 'Servicios Públicos',
                'slug' => 'servicios-publicos',
                'type' => 'expense',
                'color' => '#e67e22',
                'icon' => 'dashicons-lightbulb',
                'description' => 'Pagos de servicios básicos',
                'order' => 20
            ]);
            
            if ($servicios_id) {
                $created_count++;
                
                $subcats_servicios = [
                    ['name' => 'Electricidad', 'slug' => 'servicios-electricidad', 'desc' => 'Integra con módulo Electricidad'],
                    ['name' => 'Internet', 'slug' => 'servicios-internet'],
                    ['name' => 'Gas', 'slug' => 'servicios-gas'],
                    ['name' => 'Agua', 'slug' => 'servicios-agua'],
                    ['name' => 'Teléfono', 'slug' => 'servicios-telefono'],
                ];
                
                foreach ($subcats_servicios as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'expense',
                        'parent_id' => $servicios_id,
                        'color' => '#e67e22',
                        'icon' => 'dashicons-lightbulb',
                        'description' => $subcat['desc'] ?? '',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 3. Mantenimiento (con integración a Inventario y Vehículos)
            $mantenimiento_id = $this->create_category([
                'name' => 'Mantenimiento',
                'slug' => 'mantenimiento',
                'type' => 'expense',
                'color' => '#d35400',
                'icon' => 'dashicons-admin-tools',
                'description' => 'Mantenimiento de vehículos, herramientas y equipos',
                'order' => 30
            ]);
            
            if ($mantenimiento_id) {
                $created_count++;
                
                $subcats_mantenimiento = [
                    ['name' => 'Vehículos', 'slug' => 'mantenimiento-vehiculos', 'desc' => 'Integra con módulo Vehículos'],
                    ['name' => 'Instalaciones', 'slug' => 'mantenimiento-instalaciones'],
                    ['name' => 'Herramientas Eléctricas', 'slug' => 'mantenimiento-herramientas-electricas', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Herramientas de Motor', 'slug' => 'mantenimiento-herramientas-motor', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Equipo de Sonido', 'slug' => 'mantenimiento-equipo-sonido', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Sistema de Riego', 'slug' => 'mantenimiento-sistema-riego', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Jardinería', 'slug' => 'mantenimiento-jardineria'],
                ];
                
                foreach ($subcats_mantenimiento as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'expense',
                        'parent_id' => $mantenimiento_id,
                        'color' => '#d35400',
                        'icon' => 'dashicons-admin-tools',
                        'description' => $subcat['desc'] ?? '',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 4. Compra de Herramientas y Equipos (NUEVO - específico del instituto)
            $compra_herramientas_id = $this->create_category([
                'name' => 'Compra de Herramientas y Equipos',
                'slug' => 'compra-herramientas-equipos',
                'type' => 'expense',
                'color' => '#f39c12',
                'icon' => 'dashicons-admin-tools',
                'description' => 'Adquisición de herramientas y equipos nuevos',
                'order' => 40
            ]);
            
            if ($compra_herramientas_id) {
                $created_count++;
                
                $subcats_compra_herramientas = [
                    ['name' => 'Herramientas Eléctricas', 'slug' => 'compra-herramientas-electricas', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Herramientas de Batería', 'slug' => 'compra-herramientas-bateria', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Herramientas de Motor', 'slug' => 'compra-herramientas-motor', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Equipo de Sonido', 'slug' => 'compra-equipo-sonido', 'desc' => 'Integra con módulo Inventario'],
                    ['name' => 'Mobiliario', 'slug' => 'compra-mobiliario', 'desc' => 'Integra con módulo Inventario'],
                ];
                
                foreach ($subcats_compra_herramientas as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'expense',
                        'parent_id' => $compra_herramientas_id,
                        'color' => '#f39c12',
                        'icon' => 'dashicons-admin-tools',
                        'description' => $subcat['desc'] ?? '',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 5. Biblioteca (NUEVO - específico del instituto)
            $biblioteca_id = $this->create_category([
                'name' => 'Biblioteca',
                'slug' => 'biblioteca',
                'type' => 'expense',
                'color' => '#9b59b6',
                'icon' => 'dashicons-book',
                'description' => 'Gastos relacionados con la biblioteca',
                'order' => 50
            ]);
            
            if ($biblioteca_id) {
                $created_count++;
                
                $subcats_biblioteca = [
                    ['name' => 'Adquisición de Libros', 'slug' => 'adquisicion-libros', 'desc' => 'Integra con módulo Biblioteca'],
                    ['name' => 'Materiales Bibliográficos', 'slug' => 'materiales-bibliograficos', 'desc' => 'Integra con módulo Biblioteca'],
                ];
                
                foreach ($subcats_biblioteca as $subcat) {
                    if ($this->create_category([
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'type' => 'expense',
                        'parent_id' => $biblioteca_id,
                        'color' => '#9b59b6',
                        'icon' => 'dashicons-book',
                        'description' => $subcat['desc'] ?? '',
                    ])) {
                        $created_count++;
                    }
                }
            }
            
            // 6-12. Otras categorías de egresos
            $other_expense_categories = [
                ['name' => 'Suministros de Oficina', 'slug' => 'suministros-oficina', 'color' => '#16a085', 'icon' => 'dashicons-portfolio', 'order' => 60],
                ['name' => 'Suministros de Limpieza', 'slug' => 'suministros-limpieza', 'color' => '#1abc9c', 'icon' => 'dashicons-admin-home', 'order' => 70, 'desc' => 'Productos de limpieza para voluntarios'],
                ['name' => 'Programas y Proyectos', 'slug' => 'programas-proyectos', 'color' => '#8e44ad', 'icon' => 'dashicons-welcome-learn-more', 'order' => 80],
                ['name' => 'Becas y Ayudas', 'slug' => 'becas-ayudas', 'color' => '#27ae60', 'icon' => 'dashicons-heart', 'order' => 90],
                ['name' => 'Marketing', 'slug' => 'marketing', 'color' => '#34495e', 'icon' => 'dashicons-megaphone', 'order' => 100],
                ['name' => 'Tecnología', 'slug' => 'tecnologia', 'color' => '#2c3e50', 'icon' => 'dashicons-desktop', 'order' => 110],
                ['name' => 'Otros Gastos', 'slug' => 'otros-gastos', 'color' => '#7f8c8d', 'icon' => 'dashicons-minus', 'order' => 120],
            ];
            
            foreach ($other_expense_categories as $cat) {
                if ($this->create_category([
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'type' => 'expense',
                    'color' => $cat['color'],
                    'icon' => $cat['icon'],
                    'description' => $cat['desc'] ?? '',
                    'order' => $cat['order']
                ])) {
                    $created_count++;
                }
            }
            
        } catch (Exception $e) {
            $error_count++;
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => $error_count === 0,
            'message' => sprintf(
                __('Se crearon %d categorías correctamente. Errores: %d', 'aura'),
                $created_count,
                $error_count
            ),
            'stats' => [
                'created' => $created_count,
                'errors' => $error_count,
                'error_messages' => $errors
            ]
        ];
    }
    
    /**
     * Crear una categoría individual
     *
     * @param array $data Datos de la categoría
     * @return int|false ID de la categoría creada o false en error
     */
    private function create_category($data) {
        global $wpdb;
        
        // Valores por defecto
        $defaults = [
            'name' => '',
            'slug' => '',
            'type' => 'both',
            'parent_id' => null,
            'color' => '#3498db',
            'icon' => 'dashicons-category',
            'description' => '',
            'is_active' => 1,
            'order' => 0,
            'created_by' => $this->system_user_id,
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Verificar si ya existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_categories} WHERE slug = %s",
            $data['slug']
        ));
        
        if ($existing) {
            return $existing; // Retornar ID existente
        }
        
        // Insertar categoría
        $inserted = $wpdb->insert(
            $this->table_categories,
            [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'type' => $data['type'],
                'parent_id' => $data['parent_id'],
                'color' => $data['color'],
                'icon' => $data['icon'],
                'description' => $data['description'],
                'is_active' => $data['is_active'],
                'display_order' => $data['order'],
                'created_by' => $data['created_by'],
            ],
            [
                '%s', // name
                '%s', // slug
                '%s', // type
                '%d', // parent_id
                '%s', // color
                '%s', // icon
                '%s', // description
                '%d', // is_active
                '%d', // display_order
                '%d', // created_by
            ]
        );
        
        return $inserted ? $wpdb->insert_id : false;
    }
    
    /**
     * Exportar categorías a formato JSON
     *
     * @return string JSON con todas las categorías
     */
    public function export_categories_json() {
        global $wpdb;
        
        $categories = $wpdb->get_results(
            "SELECT name, slug, type, parent_id, color, icon, description, display_order, is_active 
             FROM {$this->table_categories} 
             ORDER BY display_order ASC",
            ARRAY_A
        );
        
        $export_data = [
            'version' => '1.0',
            'export_date' => current_time('mysql'),
            'categories' => $categories
        ];
        
        return json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Importar categorías desde formato JSON
     *
     * @param string $json_content Contenido JSON
     * @return array Resultado de la importación
     */
    public function import_categories_json($json_content) {
        $data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => __('Error al decodificar JSON: ', 'aura') . json_last_error_msg(),
            ];
        }
        
        if (!isset($data['categories']) || !is_array($data['categories'])) {
            return [
                'success' => false,
                'message' => __('Formato de JSON inválido. Falta el array de categorías.', 'aura'),
            ];
        }
        
        $imported = 0;
        $skipped = 0;
        
        foreach ($data['categories'] as $cat) {
            $result = $this->create_category($cat);
            if ($result) {
                $imported++;
            } else {
                $skipped++;
            }
        }
        
        return [
            'success' => true,
            'message' => sprintf(
                __('Importación completada. Importadas: %d, Omitidas: %d', 'aura'),
                $imported,
                $skipped
            ),
            'stats' => [
                'imported' => $imported,
                'skipped' => $skipped
            ]
        ];
    }
    
    /**
     * Obtener estadísticas de categorías
     *
     * @return array Estadísticas
     */
    public function get_categories_stats() {
        global $wpdb;
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories}");
        $income = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories} WHERE type = 'income'");
        $expense = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories} WHERE type = 'expense'");
        $both = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories} WHERE type = 'both'");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories} WHERE is_active = 1");
        $with_parent = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories} WHERE parent_id IS NOT NULL");
        
        return [
            'total' => intval($total),
            'income' => intval($income),
            'expense' => intval($expense),
            'both' => intval($both),
            'active' => intval($active),
            'inactive' => intval($total - $active),
            'subcategories' => intval($with_parent),
            'main_categories' => intval($total - $with_parent),
        ];
    }
}
