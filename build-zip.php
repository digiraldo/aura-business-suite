<?php
/**
 * Script para construir el ZIP de actualización de Aura Business Suite.
 * Ejecutar con: php build-zip.php
 */

$base = __DIR__;
$zipFile = $base . '/aura-business-suite.zip';

if (file_exists($zipFile)) {
    unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Error: No se pudo crear el archivo ZIP en $zipFile\n");
}

// Archivos y directorios a incluir
$filesToInclude = ['aura-business-suite.php', 'composer.json', 'readme.txt', 'LICENSE', 'aura-icono.svg'];
// IMPORTANTE: Incluimos 'vendor' porque añadimos 'google/apiclient'. 
// Es necesario subirlo esta vez para que el servidor tenga las nuevas dependencias.
$dirsToInclude = ['assets', 'modules', 'templates', 'vendor'];

$bomFixed = 0;
$filesAdded = 0;

function removeBom($content, &$bomFixed) {
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $bomFixed++;
        return substr($content, 3);
    }
    return $content;
}

echo "Construyendo $zipFile ...\n";

// Agregar archivos de la raíz
foreach ($filesToInclude as $f) {
    $path = $base . '/' . $f;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if (preg_match('/\.(php|js|css)$/', $f)) {
            $content = removeBom($content, $bomFixed);
        }
        $zip->addFromString('aura-business-suite/' . $f, $content);
        $filesAdded++;
    }
}

// Agregar directorios
foreach ($dirsToInclude as $d) {
    $dirPath = $base . '/' . $d;
    if (!is_dir($dirPath)) continue;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }
        
        $realPath = $file->getPathname();
        $relativePath = substr($realPath, strlen($base) + 1);
        $relativePath = str_replace('\\', '/', $relativePath); // Forzar barras normales (slash) para compatibilidad Linux/WP

        // Optimizar y excluir archivos innecesarios dentro de vendor para mantener el ZIP ultra-liviano
        if (strpos($relativePath, 'vendor/') === 0) {
            // Excluir archivos temporales
            if (preg_match('/(\.zip~|\.tmp|\.bak)$/i', $relativePath)) {
                continue;
            }
            // Excluir paquetes de desarrollo
            if (preg_match('#^vendor/(squizlabs|phpunit|wp-coding-standards|sebastian)/#i', $relativePath)) {
                continue;
            }
            // Excluir más de 320 servicios no utilizados de Google API (conservar solo Drive y Calendar)
            if (preg_match('#^vendor/google/apiclient-services/src/([^/]+)/#i', $relativePath, $m)) {
                $serviceName = $m[1];
                if ($serviceName !== 'Drive' && $serviceName !== 'Calendar') {
                    continue;
                }
            }
            // Excluir fuentes CJK/jeroglíficos gigantes de mpdf (ahorra >60MB sin afectar facturas/reportes en español)
            if (preg_match('#vendor/mpdf/mpdf/ttfonts/(Sun-Ext|UnBatang|Aegyptus|Aegean|Akkadian|Jomolhari|Quivira)#i', $relativePath)) {
                continue;
            }
            // Excluir tests, documentación y artefactos no requeridos en producción
            if (preg_match('#/(tests|Tests|docs|documentation|examples|\.github)/#i', $relativePath) ||
                preg_match('/\.(md|rst|dist|neon|yml|yaml|xml)$/i', $relativePath)) {
                continue;
            }
        }
        
        if (strpos($realPath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
            $zip->addFile($realPath, 'aura-business-suite/' . $relativePath);
        } else {
            $content = file_get_contents($realPath);
            if (preg_match('/\.(php|js|css)$/', $realPath)) {
                $content = removeBom($content, $bomFixed);
            }
            $zip->addFromString('aura-business-suite/' . $relativePath, $content);
        }
        $filesAdded++;
    }
}

$zip->close();

echo "¡Construcción terminada!\n";
echo "Archivos empaquetados: $filesAdded\n";
echo "BOMs corregidos: $bomFixed\n";
echo "Tamaño del ZIP: " . round(filesize($zipFile) / 1024 / 1024, 2) . " MB\n";
echo "=> NOTA: Este ZIP ahora incluye la carpeta 'vendor' con el SDK de Google Drive, e internamente usa barras '/' compatibles con WordPress en Hostinger.\n";
