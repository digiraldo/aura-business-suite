<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

echo "--- BUSCANDO SANDRA MOSQUERA EN BD ---\n";
$u = get_user_by('email', 'masdesandra@hotmail.com');
if ($u) {
    echo "WP User ID: {$u->ID}, Name: {$u->display_name}, Email: {$u->user_email}\n";
    echo "Avatar: " . get_avatar_url($u->ID) . "\n";
} else {
    echo "No WP User found with email masdesandra@hotmail.com\n";
}

$t_tp = $wpdb->prefix . 'aura_finance_third_parties';
$tp = $wpdb->get_results("SELECT * FROM {$t_tp} WHERE email LIKE '%masdesandra%' OR commercial_name LIKE '%Sandra%' OR full_name LIKE '%Sandra%'");
echo "Third parties matching Sandra: " . count($tp) . "\n";
foreach ($tp as $t) {
    echo "ID: {$t->id}, Name: {$t->full_name}, Comm: {$t->commercial_name}, wp_user_id: {$t->wp_user_id}, logo_id: {$t->logo_id}\n";
}

$subjs = $wpdb->get_results("SELECT id, name, teachers, external_teachers, default_teacher_id FROM wp_aura_cal_subjects");
echo "\n--- MATERIAS Y SUS TERCEROS ---\n";
foreach ($subjs as $s) {
    echo "Materia #{$s->id} [{$s->name}]: teachers={$s->teachers}, ext={$s->external_teachers}\n";
}
