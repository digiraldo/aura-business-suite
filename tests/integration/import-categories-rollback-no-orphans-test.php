<?php
/**
 * Integration test: rollback flow does not leave orphan categories.
 *
 * Run:
 *   php tests/integration/import-categories-rollback-no-orphans-test.php
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

$suffix = 'rbk-' . wp_generate_password(8, false, false) . '-' . time();
$parentSlug = 'parent-' . $suffix;
$childSlug = 'child-' . $suffix;
$filename = 'integration-rollback-no-orphans-' . $suffix . '.csv';

$rows = [
    [
        'IT Rollback Parent ' . $suffix,
        $parentSlug,
        'income',
        '',
        '#27AE60',
        'dashicons-category',
        'Rollback test parent',
        '1',
        '20',
    ],
    [
        'IT Rollback Child ' . $suffix,
        $childSlug,
        'income',
        $parentSlug,
        '#3498DB',
        'dashicons-tag',
        'Rollback test child',
        '1',
        '21',
    ],
];

$mapping = [
    'name' => 0,
    'slug' => 1,
    'type' => 2,
    'parent_category_slug' => 3,
    'color' => 4,
    'icon' => 5,
    'description' => 6,
    'is_active' => 7,
    'display_order' => 8,
];

$reflection = new ReflectionClass('Aura_Financial_Import');
$executeCategories = $reflection->getMethod('execute_categories');
$executeCategories->setAccessible(true);

$result = $executeCategories->invoke(null, $rows, $mapping, [], $filename);

$batchId = is_array($result) && isset($result['batch_id']) ? (string) $result['batch_id'] : '';

$catTable = $wpdb->prefix . 'aura_finance_categories';
$logTable = $wpdb->prefix . 'aura_finance_import_log';
$txTable = $wpdb->prefix . 'aura_finance_transactions';

$failures = [];

if (!is_array($result)) {
    $failures[] = 'execute_categories did not return array result';
} else {
    if ((int) ($result['imported'] ?? 0) !== 2) {
        $failures[] = 'expected imported=2';
    }
    if ((int) ($result['failed'] ?? 0) !== 0) {
        $failures[] = 'expected failed=0';
    }
}

$logBefore = $batchId !== ''
    ? $wpdb->get_row($wpdb->prepare("SELECT batch_id, status FROM {$logTable} WHERE batch_id = %s LIMIT 1", $batchId), ARRAY_A)
    : null;

if (!$logBefore) {
    $failures[] = 'import log row not found before rollback simulation';
}

// Simulate rollback effect using the same DB operations as ajax_rollback.
$rollbackAffected = $batchId !== ''
    ? $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$txTable} SET deleted_at = %s, deleted_by = %d WHERE import_batch_id = %s",
            current_time('mysql'),
            (int) get_current_user_id(),
            $batchId
        )
    )
    : false;

if ($rollbackAffected === false) {
    $failures[] = 'rollback simulation query failed';
}

if ($batchId !== '') {
    $updated = $wpdb->update($logTable, ['status' => 'rolled_back'], ['batch_id' => $batchId], ['%s'], ['%s']);
    if ($updated === false) {
        $failures[] = 'failed to update import log to rolled_back';
    }
}

$logAfter = $batchId !== ''
    ? $wpdb->get_row($wpdb->prepare("SELECT batch_id, status FROM {$logTable} WHERE batch_id = %s LIMIT 1", $batchId), ARRAY_A)
    : null;

if ($logAfter && ($logAfter['status'] ?? '') !== 'rolled_back') {
    $failures[] = 'import log status is not rolled_back after rollback simulation';
}

$parentRow = $wpdb->get_row(
    $wpdb->prepare("SELECT id, slug, parent_id FROM {$catTable} WHERE slug = %s LIMIT 1", $parentSlug),
    ARRAY_A
);
$childRow = $wpdb->get_row(
    $wpdb->prepare("SELECT id, slug, parent_id FROM {$catTable} WHERE slug = %s LIMIT 1", $childSlug),
    ARRAY_A
);

if (!$parentRow) {
    $failures[] = 'parent category row not found after rollback simulation';
} elseif ($parentRow['parent_id'] !== null) {
    $failures[] = 'parent parent_id is not NULL';
}

if (!$childRow) {
    $failures[] = 'child category row not found after rollback simulation';
} elseif ($parentRow && (int) $childRow['parent_id'] !== (int) $parentRow['id']) {
    $failures[] = 'child parent_id does not reference parent row after rollback simulation';
}

$orphanCount = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$catTable} c
         LEFT JOIN {$catTable} p ON p.id = c.parent_id
         WHERE c.slug = %s AND c.parent_id IS NOT NULL AND p.id IS NULL",
        $childSlug
    )
);

if ($orphanCount !== 0) {
    $failures[] = 'orphan child category detected after rollback simulation';
}

// Cleanup: child first, then parent, then log.
$wpdb->delete($catTable, ['slug' => $childSlug], ['%s']);
$wpdb->delete($catTable, ['slug' => $parentSlug], ['%s']);
if ($batchId !== '') {
    $wpdb->delete($logTable, ['batch_id' => $batchId], ['%s']);
}

if (!empty($failures)) {
    fwrite(STDERR, "FAIL: " . implode('; ', $failures) . "\n");
    exit(1);
}

echo "PASS: rollback simulation left no orphan categories and log was marked rolled_back\n";
exit(0);
