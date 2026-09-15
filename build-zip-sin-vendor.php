<?php
/**
 * Script para construir el ZIP de actualización SIN la carpeta vendor.
 * Usar cuando el servidor ya tiene vendor instalado.
 * Ejecutar con: php build-zip-sin-vendor.php
 */

$base    = __DIR__;
$zipFile = $base . '/aura-business-suite.zip';

if (file_exists($zipFile)) {
    unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Error: No se pudo crear el archivo ZIP en $zipFile\n");
}

// Archivos raíz a incluir
$filesToInclude = ['aura-business-suite.php', 'composer.json', 'LICENSE', 'aura-icono.svg'];

// Directorios a incluir — SIN vendor (ya existe en el servidor)
$dirsToInclude = ['assets', 'modules', 'templates'];

$bomFixed   = 0;
$filesAdded = 0;

function removeBom($content, &$bomFixed) {
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $bomFixed++;
        return substr($content, 3);
    }
    return $content;
}

echo "Construyendo $zipFile (SIN vendor)...\n";

// Archivos raíz
foreach ($filesToInclude as $f) {
    $path = $base . '/' . $f;
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    if (preg_match('/\.(php|js|css)$/', $f)) {
        $content = removeBom($content, $bomFixed);
    }
    $zip->addFromString('aura-business-suite/' . $f, $content);
    $filesAdded++;
}

// Directorios
foreach ($dirsToInclude as $d) {
    $dirPath = $base . '/' . $d;
    if (!is_dir($dirPath)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) continue;

        $realPath     = $file->getPathname();
        $relativePath = substr($realPath, strlen($base) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);

        $content = file_get_contents($realPath);
        if (preg_match('/\.(php|js|css)$/', $realPath)) {
            $content = removeBom($content, $bomFixed);
        }

        $zip->addFromString('aura-business-suite/' . $relativePath, $content);
        $filesAdded++;
    }
}

$zip->close();

echo "Construccion terminada!\n";
echo "Archivos empaquetados: $filesAdded\n";
echo "BOMs corregidos:       $bomFixed\n";
echo "Tamano del ZIP:        " . round(filesize($zipFile) / 1024 / 1024, 2) . " MB\n";
echo "=> ZIP SIN vendor listo. El servidor ya tiene la carpeta vendor instalada.\n";
