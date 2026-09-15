<?php
require_once dirname(__FILE__, 4) . '/wp-load.php';
global $wpdb;

echo "=== CHECK IF FORMS 7,8,9,11 ARE DELETED ===\n\n";
$forms = $wpdb->get_results(
    "SELECT id, title, type, course_id, deleted_at FROM {$wpdb->prefix}aura_forms WHERE id IN (7,8,9,11,12) ORDER BY id"
);
foreach ($forms as $f) {
    echo "Form #{$f->id}: type={$f->type} | course_id=" . ($f->course_id ?? 'NULL') . " | deleted_at=" . ($f->deleted_at ?? 'NULL') . " | {$f->title}\n";
}
