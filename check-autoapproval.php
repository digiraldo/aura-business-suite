<?php
$m = new mysqli('127.0.0.1', 'root', '', 'diserwp');
$keys = "'aura_finance_auto_approval_enabled','aura_finance_auto_approval_threshold','aura_finance_auto_approval_apply_to_expenses_only','aura_finance_auto_approval_apply_to_income_only'";
$r = $m->query("SELECT option_name, option_value FROM wp_options WHERE option_name IN ($keys)");
while ($row = $r->fetch_assoc()) {
    echo $row['option_name'] . " = " . var_export($row['option_value'], true) . "\n";
}
