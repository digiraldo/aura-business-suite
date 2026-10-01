<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once __DIR__ . '/../templates/calendar/tab-programs.php';

$ext1 = ['name' => 'Melody Vidal', 'commercial_name' => '', 'email' => 'melodyv@multiply.net', 'wp_user_id' => 5];
echo "RESULT 1 (Melody):\n" . aura_avatar_stack_item_external($ext1, 'Docente Externo') . "\n\n";

$ext2 = ['name' => 'Sandra Mosquera', 'commercial_name' => '', 'email' => 'masdesandra@hotmail.com', 'third_party_id' => 9];
echo "RESULT 2 (Sandra):\n" . aura_avatar_stack_item_external($ext2, 'Docente Externo') . "\n";
