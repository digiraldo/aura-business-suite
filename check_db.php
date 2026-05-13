<?php
// Check database values
$conn = new mysqli('localhost', 'root', '', 'diserwp');
$result = $conn->query('SELECT id, name, account_type, current_balance FROM wp_aura_finance_accounts WHERE account_type = "usd_cash" LIMIT 1');
$row = $result->fetch_assoc();
echo json_encode($row, JSON_PRETTY_PRINT);
$conn->close();
?>
