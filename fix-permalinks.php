<?php
/**
 * Script para configurar permalinks automáticamente
 * Ejecutar UNA VEZ y luego eliminar
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Error: Debes estar logueado como administrador.');
}

echo "<h2>Configurando Permalinks para API REST</h2>";
echo "<hr>";

// Obtener estructura actual
$current_structure = get_option('permalink_structure');
echo "<p><strong>Estructura actual:</strong> " . ($current_structure ? $current_structure : 'Simple (/?p=123)') . "</p>";

// Configurar a "Nombre de la entrada" (/%postname%/)
echo "<p>Cambiando a estructura: /%postname%/</p>";
update_option('permalink_structure', '/%postname%/');

// Forzar regeneración de reglas de rewrite
echo "<p>Regenerando reglas de rewrite...</p>";
flush_rewrite_rules(true);

echo "<p style='color: green;'><strong>✓ Permalinks configurados correctamente</strong></p>";

// Verificar nueva estructura
$new_structure = get_option('permalink_structure');
echo "<p><strong>Nueva estructura:</strong> " . $new_structure . "</p>";

echo "<hr>";
echo "<h3>Verificando archivo .htaccess</h3>";

$htaccess_file = ABSPATH . '.htaccess';
if (file_exists($htaccess_file)) {
    $htaccess_content = file_get_contents($htaccess_file);
    
    if (strpos($htaccess_content, 'mod_rewrite') !== false) {
        echo "<p style='color: green;'>✓ Archivo .htaccess contiene reglas de rewrite</p>";
        echo "<details>";
        echo "<summary>Ver contenido del .htaccess</summary>";
        echo "<pre>" . htmlspecialchars($htaccess_content) . "</pre>";
        echo "</details>";
    } else {
        echo "<p style='color: orange;'>⚠ El .htaccess existe pero no tiene reglas de rewrite</p>";
        echo "<p>WordPress debería haberlo actualizado. Si no funciona, verifica permisos del archivo.</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Archivo .htaccess no existe</p>";
    echo "<p>WordPress no pudo crearlo. Verifica permisos de escritura en la carpeta raíz.</p>";
}

echo "<hr>";
echo "<h3>Prueba de API REST</h3>";

// Probar si wp-json funciona
$site_url = get_site_url();
echo "<p>Probando acceso a: <a href='$site_url/wp-json/' target='_blank'>$site_url/wp-json/</a></p>";

// Usar wp_remote_get para probar
$response = wp_remote_get($site_url . '/wp-json/');
if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    echo "<p style='color: green;'>✓ API REST base responde correctamente</p>";
    
    // Probar namespace aura/v1
    $response_aura = wp_remote_get($site_url . '/wp-json/aura/v1/finance/categories');
    if (!is_wp_error($response_aura)) {
        $code = wp_remote_retrieve_response_code($response_aura);
        if ($code === 200 || $code === 401) { // 401 es OK, significa que requiere auth
            echo "<p style='color: green;'>✓ Endpoint de categorías responde (Status: $code)</p>";
            if ($code === 401) {
                echo "<p style='color: blue;'>ℹ Status 401 es correcto - el endpoint requiere autenticación</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Endpoint de categorías da error: $code</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ API REST base no responde</p>";
    echo "<p>Puede necesitar reiniciar Apache/Nginx</p>";
}

echo "<hr>";
echo "<h3 style='color: green;'>✅ CONFIGURACIÓN COMPLETADA</h3>";
echo "<p><strong>Próximos pasos:</strong></p>";
echo "<ol>";
echo "<li>Reinicia Apache: <code>Laragon → Apache → Restart</code></li>";
echo "<li>Limpia caché del navegador (Ctrl + Shift + R)</li>";
echo "<li>Ejecuta el script de prueba: <code>.\\test-api-rest.ps1</code></li>";
echo "<li><strong>Elimina este archivo por seguridad</strong></li>";
echo "</ol>";

echo "<p><strong>ELIMINAR ESTE ARCHIVO:</strong></p>";
echo "<p><code>C:\\laragon\\www\\diserwp\\wp-content\\plugins\\aura-business-suite\\fix-permalinks.php</code></p>";
?>
