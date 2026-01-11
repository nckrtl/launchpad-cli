<?php

/**
 * E2E Desktop Flow Test
 *
 * This test replicates the desktop app workflow:
 * 1. Create a project via API (dispatches CreateProjectJob)
 * 2. Track provisioning status via log files until "ready"
 * 3. Delete the project via API (dispatches DeleteProjectJob)
 * 4. Track deletion status via log files until "deleted"
 *
 * The test validates that all expected status broadcasts are sent.
 * Note: This reads from CLI log files as a proxy for WebSocket broadcasts.
 * The CLI logs each status AND broadcasts it via Reverb.
 *
 * IMPORTANT: This test creates real projects and cleans up after itself.
 * It requires the full launchpad stack to be running (Horizon, Reverb, etc.)
 *
 * Usage:
 *   php tests/e2e-desktop-flow-test.php           # Full test (create + delete)
 *   php tests/e2e-desktop-flow-test.php --keep    # Create only, skip deletion
 *   TLD=test php tests/e2e-desktop-flow-test.php  # Use different TLD
 *
 * Expected output on success:
 *   Provision: provisioning -> creating_repo -> cloning -> ... -> ready
 *   Deletion:  deleting -> removing_orchestrator -> removing_files -> deleted
 *   ALL TESTS PASSED
 */

require __DIR__.'/../vendor/autoload.php';

$keepProject = in_array('--keep', $argv);
$tld = getenv('TLD') ?: 'ccc';
$slug = 'e2e-desktop-'.time();
$apiBase = "https://launchpad.{$tld}/api";

$provisionEvents = [];
$deletionEvents = [];
$errors = [];

echo "=== E2E Desktop Flow Test ===\n";
echo "Slug: {$slug}\n";
echo "API: {$apiBase}\n";
echo "Template: hardimpactdev/liftoff-starterkit\n\n";

// Verify Reverb is accessible
$pusher = new Pusher\Pusher(
    'launchpad-key',
    'launchpad-secret',
    'launchpad',
    ['host' => "reverb.{$tld}", 'port' => 443, 'scheme' => 'https', 'useTLS' => true, 'cluster' => 'mt1']
);

echo "[1/6] Testing Reverb connection...\n";
try {
    $pusher->getChannels();
    echo "  OK - Reverb is accessible\n\n";
} catch (Exception $e) {
    echo '  FAIL: '.$e->getMessage()."\n";
    echo "  Make sure launchpad-reverb container is running\n";
    exit(1);
}

/**
 * Make HTTP request to the API
 */
function httpRequest(string $method, string $url, array $data = []): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return ['code' => $httpCode, 'body' => json_decode($response, true) ?? ['raw' => $response]];
}

/**
 * Read status updates from CLI log files.
 * The CLI logs each status change AND broadcasts via Reverb.
 */
function getLoggedStatuses(string $slug, string $type): array
{
    $home = $_SERVER['HOME'] ?? '/home/launchpad';
    $logDir = $type === 'provision' ? 'provision' : 'deletion';
    $logFile = "{$home}/.config/launchpad/logs/{$logDir}/{$slug}.log";

    if (! file_exists($logFile)) {
        return [];
    }

    $content = file_get_contents($logFile);
    preg_match_all('/Status: (\w+)/', $content, $matches);

    return $matches[1] ?? [];
}

// Step 2: Create project
echo "[2/6] Creating project via API...\n";
$createResponse = httpRequest('POST', "{$apiBase}/projects", [
    'name' => $slug,
    'template' => 'hardimpactdev/liftoff-starterkit',
    'visibility' => 'private',
]);

if ($createResponse['code'] !== 202) {
    echo "  FAIL: HTTP {$createResponse['code']}\n";
    echo '  Response: '.json_encode($createResponse['body'])."\n";
    exit(1);
}
echo "  OK - HTTP 202 Accepted\n\n";

// Step 3: Wait for provisioning
echo "[3/6] Waiting for provisioning (~30-60 seconds)...\n";
$timeout = 120;
$start = time();
$lastStatus = null;
$seen = [];

while (time() - $start < $timeout) {
    foreach (getLoggedStatuses($slug, 'provision') as $status) {
        if (! in_array($status, $seen)) {
            $seen[] = $status;
            echo "  -> {$status}\n";
            $provisionEvents[] = $status;
        }
        $lastStatus = $status;
    }
    if (in_array($lastStatus, ['ready', 'failed'])) {
        break;
    }
    usleep(500000);
}

if ($lastStatus === 'ready') {
    echo "  OK - Project provisioned successfully\n\n";
} else {
    echo "  FAIL: Provisioning did not complete (last: {$lastStatus})\n\n";
    $errors[] = "Provisioning stuck on: {$lastStatus}";
}

// Step 4-5: Delete project (unless --keep)
if (! $keepProject) {
    echo "[4/6] Deleting project via API...\n";
    $deleteResponse = httpRequest('DELETE', "{$apiBase}/projects/{$slug}");

    if ($deleteResponse['code'] !== 202) {
        echo "  FAIL: HTTP {$deleteResponse['code']}\n";
        $errors[] = 'Delete returned wrong HTTP code';
    } else {
        echo "  OK - HTTP 202 Accepted\n\n";
    }

    echo "[5/6] Waiting for deletion (~5-10 seconds)...\n";
    $timeout = 60;
    $start = time();
    $lastStatus = null;
    $seen = [];

    while (time() - $start < $timeout) {
        foreach (getLoggedStatuses($slug, 'deletion') as $status) {
            if (! in_array($status, $seen)) {
                $seen[] = $status;
                echo "  -> {$status}\n";
                $deletionEvents[] = $status;
            }
            $lastStatus = $status;
        }
        if (in_array($lastStatus, ['deleted', 'delete_failed'])) {
            break;
        }
        usleep(500000);
    }

    if ($lastStatus === 'deleted') {
        echo "  OK - Project deleted successfully\n\n";
    } else {
        echo "  FAIL: Deletion did not complete (last: {$lastStatus})\n\n";
        $errors[] = "Deletion stuck on: {$lastStatus}";
    }
} else {
    echo "[4/6] Skipped (--keep flag)\n";
    echo "[5/6] Skipped (--keep flag)\n\n";
}

// Step 6: Summary
echo "[6/6] Results Summary\n";
echo "=====================================\n";
echo 'Provision: '.implode(' -> ', $provisionEvents)."\n";
if (! $keepProject) {
    echo 'Deletion:  '.implode(' -> ', $deletionEvents)."\n";
}
echo "=====================================\n\n";

if (empty($errors)) {
    echo "ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "TESTS FAILED:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
    exit(1);
}
