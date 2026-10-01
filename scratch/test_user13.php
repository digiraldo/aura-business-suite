<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';

var_dump(get_avatar_url(13));
var_dump(get_avatar_url(13, ['size' => 72, 'default' => 'identicon']));
var_dump(get_avatar_url('masdesandra@hotmail.com'));

global $wpdb;
$t_stud = $wpdb->prefix . 'aura_students';
$photo = $wpdb->get_var("SELECT photo_url FROM {$t_stud} WHERE wp_user_id = 13");
echo "Photo in aura_students: $photo\n";
