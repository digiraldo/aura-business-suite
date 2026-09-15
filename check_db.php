<?php
require_once("../../../wp-load.php");
global $wpdb;
$res = $wpdb->get_results("SELECT id, name, is_active FROM " . $wpdb->prefix . "aura_finance_categories");
print_r($res);