<?php
/**
 * Script de Prueba: Reactivar Plugin y Verificar Categorías
 * 
 * INSTRUCCIONES:
 * 1. Acceder a: https://diserwp.test/wp-content/plugins/aura-business-suite/test-plugin-reactivation.php
 * 2. El script desactivará y reactivará el plugin automáticamente
 * 3. Mostrará las categorías instaladas
 * 
 * ELIMINAR DESPUÉS DE USAR
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que es administrador
if (!current_user_can('activate_plugins')) {
    wp_die('Solo administradores pueden ejecutar este script.');
}

echo '<html><head><meta charset="UTF-8"><title>Test Reactivación Plugin</title>';
echo '<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; background: #f0f0f1; }
    h1 { color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
    h2 { color: #2271b1; margin-top: 30px; }
    .step { background: white; padding: 20px; margin: 15px 0; border-left: 4px solid #2271b1; border-radius: 4px; }
    .success { background: #d5f5e3; border-left-color: #27ae60; }
    .error { background: #fadbd8; border-left-color: #e74c3c; }
    .info { background: #ebf5fb; border-left-color: #3498db; }
    table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
    th { background: #2271b1; color: white; padding: 12px; text-align: left; }
    td { padding: 12px; border-bottom: 1px solid #ddd; }
    tr:hover { background: #f8f9fa; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
    .badge-income { background: #d5f5e3; color: #27ae60; }
    .badge-expense { background: #fadbd8; color: #e74c3c; }
    .badge-both { background: #ebf5fb; color: #3498db; }
    .badge-active { background: #d5f5e3; color: #27ae60; }
    .badge-inactive { background: #e8e8e8; color: #666; }
    .color-box { display: inline-block; width: 20px; height: 20px; border-radius: 3px; vertical-align: middle; border: 1px solid #ddd; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style></head><body>';

echo '<h1>🔄 Test de Reactivación del Plugin Aura Business Suite</h1>';

// PASO 1: Desactivar plugin
echo '<div class="step">';
echo '<h2>📋 PASO 1: Desactivar Plugin</h2>';
$result_deactivate = deactivate_plugins('aura-business-suite/aura-business-suite.php');
if (is_wp_error($result_deactivate)) {
    echo '<p class="error">❌ Error al desactivar: ' . $result_deactivate->get_error_message() . '</p>';
} else {
    echo '<p class="success">✅ Plugin desactivado exitosamente</p>';
}
echo '</div>';

// PASO 2: Reactivar plugin
echo '<div class="step">';
echo '<h2>📋 PASO 2: Reactivar Plugin</h2>';
$result_activate = activate_plugin('aura-business-suite/aura-business-suite.php');
if (is_wp_error($result_activate)) {
    echo '<p class="error">❌ Error al activar: ' . $result_activate->get_error_message() . '</p>';
} else {
    echo '<p class="success">✅ Plugin reactivado exitosamente</p>';
    echo '<p>Durante la activación se deben haber instalado las categorías predeterminadas.</p>';
}
echo '</div>';

// PASO 3: Verificar categorías en BD
echo '<div class="step">';
echo '<h2>📋 PASO 3: Verificar Categorías en Base de Datos</h2>';

global $wpdb;
$table_categories = $wpdb->prefix . 'aura_finance_categories';

// Verificar si la tabla existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_categories'") === $table_categories;

if (!$table_exists) {
    echo '<p class="error">❌ La tabla <code>' . $table_categories . '</code> NO existe en la base de datos.</p>';
} else {
    echo '<p class="success">✅ Tabla <code>' . $table_categories . '</code> existe</p>';
    
    // Contar categorías
    $total_categories = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories");
    $income_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories WHERE type = 'income'");
    $expense_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories WHERE type = 'expense'");
    $active_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories WHERE is_active = 1");
    $main_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories WHERE parent_id IS NULL");
    
    echo '<div class="info">';
    echo '<h3>📊 Estadísticas:</h3>';
    echo '<ul>';
    echo '<li><strong>Total de categorías:</strong> ' . $total_categories . '</li>';
    echo '<li><strong>Categorías de ingreso:</strong> ' . $income_count . '</li>';
    echo '<li><strong>Categorías de egreso:</strong> ' . $expense_count . '</li>';
    echo '<li><strong>Categorías activas:</strong> ' . $active_count . '</li>';
    echo '<li><strong>Categorías principales:</strong> ' . $main_count . '</li>';
    echo '<li><strong>Subcategorías:</strong> ' . ($total_categories - $main_count) . '</li>';
    echo '</ul>';
    echo '</div>';
    
    // Obtener todas las categorías
    $categories = $wpdb->get_results("
        SELECT c.*, p.name as parent_name 
        FROM $table_categories c 
        LEFT JOIN $table_categories p ON c.parent_id = p.id 
        ORDER BY c.type, c.parent_id, c.display_order
    ");
    
    if ($categories) {
        echo '<h3>📋 Lista de Categorías Instaladas:</h3>';
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Nombre</th>';
        echo '<th>Slug</th>';
        echo '<th>Tipo</th>';
        echo '<th>Padre</th>';
        echo '<th>Color</th>';
        echo '<th>Estado</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($categories as $cat) {
            echo '<tr>';
            echo '<td>' . $cat->id . '</td>';
            echo '<td>';
            if ($cat->parent_id) echo '└─ ';
            echo esc_html($cat->name);
            echo '</td>';
            echo '<td><code>' . esc_html($cat->slug) . '</code></td>';
            echo '<td>';
            if ($cat->type === 'income') {
                echo '<span class="badge badge-income">INGRESO</span>';
            } elseif ($cat->type === 'expense') {
                echo '<span class="badge badge-expense">EGRESO</span>';
            } else {
                echo '<span class="badge badge-both">AMBOS</span>';
            }
            echo '</td>';
            echo '<td>' . ($cat->parent_name ? esc_html($cat->parent_name) : '-') . '</td>';
            echo '<td><span class="color-box" style="background-color: ' . esc_attr($cat->color) . ';"></span> ' . esc_html($cat->color) . '</td>';
            echo '<td>';
            if ($cat->is_active) {
                echo '<span class="badge badge-active">ACTIVA</span>';
            } else {
                echo '<span class="badge badge-inactive">INACTIVA</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p class="error">❌ No se encontraron categorías en la base de datos.</p>';
    }
}
echo '</div>';

// PASO 4: Verificar capabilities del usuario actual
echo '<div class="step">';
echo '<h2>📋 PASO 4: Verificar Capabilities del Usuario</h2>';

$current_user = wp_get_current_user();
echo '<p><strong>Usuario actual:</strong> ' . $current_user->user_login . ' (ID: ' . $current_user->ID . ')</p>';
echo '<p><strong>Roles:</strong> ' . implode(', ', $current_user->roles) . '</p>';

$required_capabilities = [
    'aura_finance_category_manage' => 'Gestionar categorías financieras',
    'aura_finance_view_all' => 'Ver todas las transacciones',
    'aura_admin_settings' => 'Acceder a configuración',
];

echo '<h3>Capabilities Requeridas:</h3>';
echo '<table>';
echo '<thead><tr><th>Capability</th><th>Descripción</th><th>Estado</th></tr></thead>';
echo '<tbody>';

foreach ($required_capabilities as $cap => $description) {
    $has_cap = current_user_can($cap);
    echo '<tr>';
    echo '<td><code>' . $cap . '</code></td>';
    echo '<td>' . $description . '</td>';
    echo '<td>';
    if ($has_cap) {
        echo '<span class="badge badge-active">✅ TIENE</span>';
    } else {
        echo '<span class="badge badge-inactive">❌ NO TIENE</span>';
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Solución si no tiene capability
if (!current_user_can('aura_finance_category_manage')) {
    echo '<div class="error">';
    echo '<h3>⚠️ Solución: Asignar Capability</h3>';
    echo '<p>El usuario actual NO tiene el capability <code>aura_finance_category_manage</code> necesario para ver el menú "Categorías Financieras".</p>';
    echo '<p><strong>Para solucionar:</strong></p>';
    echo '<form method="post">';
    echo '<button type="submit" name="assign_capability" style="padding: 10px 20px; background: #2271b1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">✨ Asignar Capability a Este Usuario</button>';
    echo '</form>';
    echo '</div>';
    
    // Procesar asignación
    if (isset($_POST['assign_capability'])) {
        $current_user->add_cap('aura_finance_category_manage');
        $current_user->add_cap('aura_finance_view_all');
        $current_user->add_cap('aura_admin_settings');
        echo '<script>location.reload();</script>';
    }
}
echo '</div>';

// PASO 5: Enlaces de acceso
echo '<div class="step info">';
echo '<h2>🔗 PASO 5: Enlaces de Acceso</h2>';
echo '<ul>';
echo '<li><a href="' . admin_url('admin.php?page=aura-suite') . '" target="_blank"><strong>Dashboard Aura Suite</strong></a></li>';
echo '<li><a href="' . admin_url('admin.php?page=aura-financial-categories') . '" target="_blank"><strong>Categorías Financieras</strong></a></li>';
echo '<li><a href="' . admin_url('admin.php?page=aura-settings') . '" target="_blank"><strong>Configuración</strong></a> (para reinstalar categorías)</li>';
echo '</ul>';
echo '</div>';

// PASO 6: Verificar opción de instalación
echo '<div class="step">';
echo '<h2>📋 PASO 6: Verificar Opción de Instalación</h2>';

$installed_version = get_option('aura_finance_categories_installed', false);

if ($installed_version) {
    echo '<p class="success">✅ Opción <code>aura_finance_categories_installed</code> existe</p>';
    echo '<p><strong>Versión instalada:</strong> ' . $installed_version . '</p>';
} else {
    echo '<p class="error">❌ Opción <code>aura_finance_categories_installed</code> NO existe</p>';
    echo '<p>Esto significa que el hook de instalación no se ejecutó correctamente.</p>';
}
echo '</div>';

echo '<div class="step success">';
echo '<h2>✅ Resumen Final</h2>';
if ($total_categories > 0 && current_user_can('aura_finance_category_manage')) {
    echo '<p><strong>TODO ESTÁ CORRECTO:</strong></p>';
    echo '<ul>';
    echo '<li>✅ Plugin reactivado exitosamente</li>';
    echo '<li>✅ ' . $total_categories . ' categorías instaladas</li>';
    echo '<li>✅ Usuario tiene capabilities necesarias</li>';
    echo '<li>✅ Puedes acceder al menú "Categorías Financieras"</li>';
    echo '</ul>';
    echo '<p><a href="' . admin_url('admin.php?page=aura-financial-categories') . '" style="display: inline-block; padding: 10px 20px; background: #27ae60; color: white; text-decoration: none; border-radius: 4px; font-weight: 600;">🎉 IR A CATEGORÍAS FINANCIERAS</a></p>';
} else {
    echo '<p><strong>PROBLEMAS DETECTADOS:</strong></p>';
    echo '<ul>';
    if ($total_categories == 0) {
        echo '<li>❌ No hay categorías instaladas</li>';
    }
    if (!current_user_can('aura_finance_category_manage')) {
        echo '<li>❌ Usuario sin capability necesaria (usar botón arriba para asignar)</li>';
    }
    echo '</ul>';
}
echo '</div>';

echo '<div style="margin-top: 40px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
echo '<p><strong>⚠️ IMPORTANTE:</strong> Elimina este archivo después de usarlo por seguridad:</p>';
echo '<code>wp-content/plugins/aura-business-suite/test-plugin-reactivation.php</code>';
echo '</div>';

echo '</body></html>';
