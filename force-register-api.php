<?php
/**
 * Script temporal para forzar el registro de rutas REST API
 * Ejecutar una sola vez y luego eliminar este archivo
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos de administrador
if (!current_user_can('activate_plugins')) {
    die('Error: Debes estar logueado como administrador.');
}

echo "<h2>Forzando Registro de API REST - AURA Business Suite</h2>";
echo "<hr>";

// 1. Desactivar el plugin
echo "<p>1. Desactivando plugin...</p>";
deactivate_plugins('aura-business-suite/aura-business-suite.php');
echo "<p style='color: green;'>✓ Plugin desactivado</p>";

// 2. Limpiar caché de permalinks
echo "<p>2. Limpiando permalinks...</p>";
flush_rewrite_rules(true);
delete_option('rewrite_rules');
echo "<p style='color: green;'>✓ Permalinks limpiados</p>";

// 3. Reactivar el plugin
echo "<p>3. Reactivando plugin...</p>";
activate_plugin('aura-business-suite/aura-business-suite.php');
echo "<p style='color: green;'>✓ Plugin reactivado</p>";

// 4. Forzar flush de permalinks nuevamente
echo "<p>4. Regenerando permalinks...</p>";
flush_rewrite_rules(true);
echo "<p style='color: green;'>✓ Permalinks regenerados</p>";

echo "<hr>";
echo "<h3 style='color: green;'>✅ PROCESO COMPLETADO</h3>";

// 5. Verificar si las rutas están registradas
echo "<h3>Verificando rutas registradas:</h3>";
echo "<pre>";

$rest_server = rest_get_server();
$namespaces = $rest_server->get_namespaces();

if (in_array('aura/v1', $namespaces)) {
    echo "✅ Namespace 'aura/v1' ENCONTRADO\n\n";
    
    $routes = $rest_server->get_routes('aura/v1');
    echo "Rutas disponibles:\n";
    foreach ($routes as $route => $data) {
        if (strpos($route, 'aura/v1') !== false) {
            $methods = array();
            foreach ($data as $endpoint) {
                if (isset($endpoint['methods'])) {
                    foreach ($endpoint['methods'] as $method => $enabled) {
                        if ($enabled) {
                            $methods[] = $method;
                        }
                    }
                }
            }
            echo "  " . implode(', ', array_unique($methods)) . " - " . $route . "\n";
        }
    }
} else {
    echo "❌ Namespace 'aura/v1' NO ENCONTRADO\n";
    echo "\nNamespaces disponibles:\n";
    print_r($namespaces);
}

echo "</pre>";

echo "<hr>";
echo "<h3>Próximos pasos:</h3>";
echo "<ol>";
echo "<li>Este archivo debe ser eliminado por seguridad</li>";
echo "<li>Prueba las URLs en tu navegador:";
echo "<ul>";
echo "<li><a href='https://diserwp.test/wp-json/' target='_blank'>https://diserwp.test/wp-json/</a></li>";
echo "<li><a href='https://diserwp.test/wp-json/aura/v1' target='_blank'>https://diserwp.test/wp-json/aura/v1</a></li>";
echo "<li><a href='https://diserwp.test/wp-json/aura/v1/finance/categories' target='_blank'>https://diserwp.test/wp-json/aura/v1/finance/categories</a></li>";
echo "</ul>";
echo "</li>";
echo "<li>Ejecuta nuevamente el script de prueba: <code>.\\test-api-rest.ps1</code></li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>ELIMINA ESTE ARCHIVO DESPUÉS DE USARLO:</strong></p>";
echo "<p><code>C:\\laragon\\www\\diserwp\\wp-content\\plugins\\aura-business-suite\\force-register-api.php</code></p>";
?>
