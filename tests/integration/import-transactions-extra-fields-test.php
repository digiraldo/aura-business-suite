<?php
/**
 * Integration test: transactions import persists optional extra fields.
 *
 * Run:
 *   php tests/integration/import-transactions-extra-fields-test.php
 */

declare(strict_types=1);

if (empty($_SERVER['REQUEST_SCHEME'])) {
    $_SERVER['REQUEST_SCHEME'] = 'https';
}
if (empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'diserwp.test';
}

$root = dirname(__DIR__, 2);
$wpLoad = $root . '/../../../wp-load.php';
$importerFile = $root . '/modules/financial/class-financial-import.php';

if (!file_exists($wpLoad)) {
    fwrite(STDERR, "FAIL: wp-load.php not found at {$wpLoad}\n");
    exit(1);
}

require_once $wpLoad;
require_once $importerFile;

global $wpdb;

if (!$wpdb || !class_exists('Aura_Financial_Import')) {
    fwrite(STDERR, "FAIL: WordPress or Aura_Financial_Import not loaded\n");
    exit(1);
}

Aura_Financial_Import::setup();

$txTable = $wpdb->prefix . 'aura_finance_transactions';
$logTable = $wpdb->prefix . 'aura_finance_import_log';
$catTable = $wpdb->prefix . 'aura_finance_categories';
$accTable = $wpdb->prefix . 'aura_finance_accounts';

$columns = $wpdb->get_col("SHOW COLUMNS FROM {$txTable}");
$requiredColumns = ['source_account_id', 'destination_account_id', 'related_user_id'];
foreach ($requiredColumns as $requiredColumn) {
    if (!in_array($requiredColumn, $columns, true)) {
        echo "SKIP: transaction schema does not include {$requiredColumn}\n";
        exit(0);
    }
}

$categoryId = (int) $wpdb->get_var("SELECT id FROM {$catTable} WHERE is_active = 1 LIMIT 1");
$sourceAccountId = (int) $wpdb->get_var("SELECT id FROM {$accTable} WHERE is_active = 1 LIMIT 1");
$destinationAccountId = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$accTable} WHERE is_active = 1 AND id <> %d LIMIT 1",
    $sourceAccountId
));
$relatedUserId = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->users} ORDER BY ID ASC LIMIT 1");

if (!$categoryId || !$sourceAccountId || !$relatedUserId) {
    echo "SKIP: missing active categories/accounts/users for integration test\n";
    exit(0);
}
if (!$destinationAccountId) {
    $destinationAccountId = $sourceAccountId;
}

$suffix = 'itest-extra-' . wp_generate_password(6, false, false) . '-' . time();
$reference = 'IT-EXTRA-' . $suffix;
$filename = 'integration-transactions-extra-fields-' . $suffix . '.csv';

$rows = [
    [
        date('d/m/Y'),
        'income',
        (string) $categoryId,
        '123.45',
        'IT transaction extra fields',
        'IT notes',
        'transfer',
        $reference,
        (string) $sourceAccountId,
        (string) $destinationAccountId,
        (string) $relatedUserId,
        'approved',
    ],
];

$mapping = [
    'transaction_date' => 0,
    'transaction_type' => 1,
    'category_id' => 2,
    'amount' => 3,
    'description' => 4,
    'notes' => 5,
    'payment_method' => 6,
    'reference_number' => 7,
    'source_account_id' => 8,
    'destination_account_id' => 9,
    'related_user_id' => 10,
    'status' => 11,
];

$reflection = new ReflectionClass('Aura_Financial_Import');
$method = $reflection->getMethod('execute_transactions');
$method->setAccessible(true);

$result = $method->invoke(null, $rows, $mapping, ['default_status' => 'pending'], $filename);
$batchId = is_array($result) && isset($result['batch_id']) ? (string) $result['batch_id'] : '';

$txRow = $wpdb->get_row($wpdb->prepare(
    "SELECT id, status, source_account_id, destination_account_id, related_user_id, reference_number
     FROM {$txTable}
     WHERE import_batch_id = %s
     ORDER BY id DESC
     LIMIT 1",
    $batchId
), ARRAY_A);

$failures = [];

if (!is_array($result)) {
    $failures[] = 'execute_transactions did not return array result';
} else {
    if ((int) ($result['imported'] ?? 0) !== 1) {
        $failures[] = 'expected imported=1';
    }
    if ((int) ($result['failed'] ?? 0) !== 0) {
        $failures[] = 'expected failed=0';
    }
}

if (!$txRow) {
    $failures[] = 'imported transaction row not found';
} else {
    if (($txRow['status'] ?? '') !== 'approved') {
        $failures[] = 'status was not persisted as approved';
    }
    if ((int) ($txRow['source_account_id'] ?? 0) !== $sourceAccountId) {
        $failures[] = 'source_account_id mismatch';
    }
    if ((int) ($txRow['destination_account_id'] ?? 0) !== $destinationAccountId) {
        $failures[] = 'destination_account_id mismatch';
    }
    if ((int) ($txRow['related_user_id'] ?? 0) !== $relatedUserId) {
        $failures[] = 'related_user_id mismatch';
    }
    if (($txRow['reference_number'] ?? '') !== $reference) {
        $failures[] = 'reference_number mismatch';
    }
}

if (!empty($txRow['id'])) {
    $wpdb->delete($txTable, ['id' => (int) $txRow['id']], ['%d']);
}
if ($batchId !== '') {
    $wpdb->delete($logTable, ['batch_id' => $batchId], ['%s']);
}

if (!empty($failures)) {
    fwrite(STDERR, "FAIL: " . implode('; ', $failures) . "\n");
    exit(1);
}

echo "PASS: transactions import persisted status/source/destination/related_user fields\n";
exit(0);
