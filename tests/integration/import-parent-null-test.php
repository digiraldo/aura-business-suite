<?php
/**
 * Integration test: categories import keeps parent_id as NULL for parent rows.
 *
 * Run:
 *   php tests/integration/import-parent-null-test.php
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

$suffix = 'itest-' . wp_generate_password(8, false, false) . '-' . time();
$parentSlug = 'parent-' . $suffix;
$childSlug = 'child-' . $suffix;
$filename = 'integration-parent-null-' . $suffix . '.csv';

$rows = [
    [
        'IT Parent ' . $suffix,
        $parentSlug,
        'income',
        '',
        '#27AE60',
        'dashicons-category',
        'Integration parent row',
        '1',
        '10',
    ],
    [
        'IT Child ' . $suffix,
        $childSlug,
        'income',
        $parentSlug,
        '#3498DB',
        'dashicons-tag',
        'Integration child row',
        '1',
        '11',
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
$method = $reflection->getMethod('execute_categories');
$method->setAccessible(true);

$result = $method->invoke(null, $rows, $mapping, [], $filename);

$batchId = is_array($result) && isset($result['batch_id']) ? (string) $result['batch_id'] : '';

$catTable = $wpdb->prefix . 'aura_finance_categories';
$logTable = $wpdb->prefix . 'aura_finance_import_log';

$parentRow = $wpdb->get_row(
    $wpdb->prepare("SELECT id, slug, parent_id FROM {$catTable} WHERE slug = %s LIMIT 1", $parentSlug),
    ARRAY_A
);
$childRow = $wpdb->get_row(
    $wpdb->prepare("SELECT id, slug, parent_id FROM {$catTable} WHERE slug = %s LIMIT 1", $childSlug),
    ARRAY_A
);

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

if (!$parentRow) {
    $failures[] = 'parent category row not found';
} else {
    if ($parentRow['parent_id'] !== null) {
        $failures[] = 'parent parent_id is not NULL';
    }
}

if (!$childRow) {
    $failures[] = 'child category row not found';
} elseif ($parentRow) {
    if ((int) $childRow['parent_id'] !== (int) $parentRow['id']) {
        $failures[] = 'child parent_id does not reference parent row id';
    }
}

// Cleanup: child first, then parent.
$wpdb->delete($catTable, ['slug' => $childSlug], ['%s']);
$wpdb->delete($catTable, ['slug' => $parentSlug], ['%s']);
if ($batchId !== '') {
    $wpdb->delete($logTable, ['batch_id' => $batchId], ['%s']);
}

if (!empty($failures)) {
    fwrite(STDERR, "FAIL: " . implode('; ', $failures) . "\n");
    exit(1);
}

echo "PASS: parent_id NULL invariant verified for imported parent category\n";
exit(0);
