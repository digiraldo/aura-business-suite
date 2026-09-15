<?php
// Test script para verificar UPDATE con todos los campos
require_once('c:/laragon/www/diserwp/wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'aura_finance_accounts';

// Buscar Caja USD
$usd_account = $wpdb->get_row(
    "SELECT * FROM $table WHERE account_type = 'usd_cash' LIMIT 1",
    ARRAY_A
);

if (!$usd_account) {
    echo "No account found\n";
    exit;
}

echo "Before update:\n";
echo "Current Balance: " . $usd_account['current_balance'] . "\n\n";

// Intentar actualizar con todos los campos (como el AJAX)
$name = 'Caja USD';
$account_type = 'usd_cash';
$currency = 'USD';
$owner_user_id = null; // NULL
$institution = 'Caja Chica USD de CEM para cambiar a MXN';
$account_number_masked = '';
$initial_balance = 0.00;
$current_balance = 7777.99;
$is_active = 1;

$result = $wpdb->update(
    $table,
    array(
        'name' => $name,
        'account_type' => $account_type,
        'currency' => $currency,
        'owner_user_id' => $owner_user_id,
        'institution' => $institution,
        'account_number_masked' => $account_number_masked,
        'initial_balance' => $initial_balance,
        'current_balance' => $current_balance,
        'is_active' => $is_active,
        'updated_at' => current_time('mysql'),
    ),
    array('id' => $usd_account['id']),
    array('%s', '%s', '%s', '%d', '%s', '%s', '%f', '%f', '%d', '%s'),
    array('%d')
);

echo "Update result: $result\n";
echo "Last error: " . $wpdb->last_error . "\n\n";

// Verificar después
$after = $wpdb->get_row(
    "SELECT id, current_balance FROM $table WHERE id = " . $usd_account['id'],
    ARRAY_A
);

echo "After update:\n";
echo "Current Balance: " . $after['current_balance'] . "\n";
?>
