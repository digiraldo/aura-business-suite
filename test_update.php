<?php
// Test script para verificar el UPDATE
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
echo "ID: " . $usd_account['id'] . "\n";
echo "Current Balance: " . $usd_account['current_balance'] . "\n\n";

// Intentar actualizar el saldo
$result = $wpdb->update(
    $table,
    array(
        'name' => 'Caja USD TEST',
        'current_balance' => 5555.99,
        'updated_at' => current_time('mysql'),
    ),
    array('id' => $usd_account['id']),
    array('%s', '%f', '%s'),
    array('%d')
);

echo "Update result: $result\n";
echo "Last error: " . $wpdb->last_error . "\n\n";

// Verificar después
$after = $wpdb->get_row(
    "SELECT id, name, current_balance FROM $table WHERE id = " . $usd_account['id'],
    ARRAY_A
);

echo "After update:\n";
echo "Name: " . $after['name'] . "\n";
echo "Current Balance: " . $after['current_balance'] . "\n";
?>
