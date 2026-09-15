<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "Ejecutando create_tables() con dbDelta...\n";
Aura_Calendar_Setup::create_tables();
echo "Migración completada. Versión actual en options: " . get_option(Aura_Calendar_Setup::DB_VERSION_OPTION) . "\n";
