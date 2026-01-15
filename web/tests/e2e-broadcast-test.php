#!/usr/bin/env php
<?php

/**
 * E2E Broadcast Test
 *
 * Tests the full project lifecycle:
 * 1. Create project via API → job dispatched
 * 2. Wait for provisioning to complete
 * 3. Verify project was created
 * 4. Delete project via API → job dispatched
 * 5. Wait for deletion to complete
 * 6. Verify project was deleted
 *
 * Note: Broadcast verification is done by checking CLI logs which record
 * each broadcast attempt. The actual WebSocket delivery would require
 * a WebSocket client.
 *
 * Usage: php tests/e2e-broadcast-test.php [--tld=ccc] [--timeout=120]
 */
$options = getopt('', ['tld:', 'timeout:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php tests/e2e-broadcast-test.php [--tld=ccc] [--timeout=120]\n";
    exit(0);
}

$tld = $options['tld'] ?? getenv('TLD') ?: 'ccc';
$timeout = (int) ($options['timeout'] ?? 120);
$slug = 'e2e-test-'.time();
$apiBase = "https://orbit.{$tld}/api";
$home = getenv('HOME') ?: '/home/launchpad';

echo "=== E2E Broadcast Test ===\n";
echo "TLD: {$tld}\n";
echo "API Base: {$apiBase}\n";
echo "Test slug: {$slug}\n";
echo "Timeout: {$timeout}s\n\n";

// Helper functions
function httpRequest(string $url, string $method = 'GET', ?array $data = null): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?? ['raw' => $response],
        'error' => $error,
    ];
}

function checkProvisionLog(string $slug, string $home): array
{
    $logFile = "{$home}/.config/orbit/logs/provision/{$slug}.log";
    if (! file_exists($logFile)) {
        return ['exists' => false, 'content' => ''];
    }

    return ['exists' => true, 'content' => file_get_contents($logFile)];
}

function checkDeletionLog(string $slug, string $home): array
{
    $logFile = "{$home}/.config/orbit/logs/deletion/{$slug}.log";
    if (! file_exists($logFile)) {
        return ['exists' => false, 'content' => ''];
    }

    return ['exists' => true, 'content' => file_get_contents($logFile)];
}

function projectExists(string $slug, string $home): bool
{
    $paths = ["{$home}/projects/{$slug}", "{$home}/Sites/{$slug}"];
    foreach ($paths as $path) {
        if (is_dir($path)) {
            return true;
        }
    }

    return false;
}

// Track results
$results = [
    'create_api' => false,
    'provision_complete' => false,
    'provision_broadcasts' => [],
    'project_created' => false,
    'delete_api' => false,
    'deletion_complete' => false,
    'deletion_broadcasts' => [],
    'project_deleted' => false,
];

// ===== PHASE 1: CREATE PROJECT =====
echo "[1] Creating project via API...\n";
$createResponse = httpRequest("{$apiBase}/projects", 'POST', [
    'name' => $slug,
    'template' => 'hardimpactdev/liftoff-starterkit',
    'visibility' => 'private',
    'minimal' => true,  // Faster for testing
]);

if ($createResponse['code'] === 202 && ($createResponse['body']['success'] ?? false)) {
    echo "    ✓ API accepted request (202)\n";
    $results['create_api'] = true;
} else {
    echo '    ✗ API request failed: '.json_encode($createResponse)."\n";
    exit(1);
}

// ===== PHASE 2: WAIT FOR PROVISIONING =====
echo "[2] Waiting for provisioning (max {$timeout}s)...\n";
$start = time();
$lastStatus = '';

while (time() - $start < $timeout) {
    // Check provision log for status updates
    $log = checkProvisionLog($slug, $home);
    if ($log['exists']) {
        // Extract status broadcasts from log
        preg_match_all('/Status: (\w+)/', $log['content'], $matches);
        if (! empty($matches[1])) {
            $currentStatus = end($matches[1]);
            if ($currentStatus !== $lastStatus) {
                echo "    Status: {$currentStatus}\n";
                $lastStatus = $currentStatus;
                $results['provision_broadcasts'] = array_unique($matches[1]);
            }

            if (in_array($currentStatus, ['ready', 'failed'])) {
                $results['provision_complete'] = ($currentStatus === 'ready');
                break;
            }
        }
    }

    // Also check if project directory exists
    if (projectExists($slug, $home)) {
        // Give it a moment for finalization
        sleep(2);
        $results['provision_complete'] = true;
        break;
    }

    sleep(2);
}

if ($results['provision_complete']) {
    echo "    ✓ Provisioning completed\n";
} else {
    echo "    ✗ Provisioning timed out or failed\n";
}

// ===== PHASE 3: VERIFY PROJECT CREATED =====
echo "[3] Verifying project was created...\n";
if (projectExists($slug, $home)) {
    echo "    ✓ Project directory exists\n";
    $results['project_created'] = true;
} else {
    echo "    ✗ Project directory not found\n";
}

// ===== PHASE 4: DELETE PROJECT =====
echo "[4] Deleting project via API...\n";
$deleteResponse = httpRequest("{$apiBase}/projects/{$slug}", 'DELETE');

if ($deleteResponse['code'] === 202 && ($deleteResponse['body']['success'] ?? false)) {
    echo "    ✓ API accepted delete request (202)\n";
    $results['delete_api'] = true;
} else {
    echo '    ✗ Delete API request failed: '.json_encode($deleteResponse)."\n";
}

// ===== PHASE 5: WAIT FOR DELETION =====
if ($results['delete_api']) {
    echo "[5] Waiting for deletion (max 60s)...\n";
    $start = time();
    $lastStatus = '';

    while (time() - $start < 60) {
        // Check deletion log for status updates
        $log = checkDeletionLog($slug, $home);
        if ($log['exists']) {
            preg_match_all('/Status: (\w+)/', $log['content'], $matches);
            if (! empty($matches[1])) {
                $currentStatus = end($matches[1]);
                if ($currentStatus !== $lastStatus) {
                    echo "    Status: {$currentStatus}\n";
                    $lastStatus = $currentStatus;
                    $results['deletion_broadcasts'] = array_unique($matches[1]);
                }

                if (in_array($currentStatus, ['deleted', 'delete_failed'])) {
                    $results['deletion_complete'] = ($currentStatus === 'deleted');
                    break;
                }
            }
        }

        // Check if project was removed
        if (! projectExists($slug, $home)) {
            $results['deletion_complete'] = true;
            break;
        }

        sleep(2);
    }

    if ($results['deletion_complete']) {
        echo "    ✓ Deletion completed\n";
    } else {
        echo "    ✗ Deletion timed out\n";
    }
}

// ===== PHASE 6: VERIFY PROJECT DELETED =====
echo "[6] Verifying project was deleted...\n";
if (! projectExists($slug, $home)) {
    echo "    ✓ Project directory removed\n";
    $results['project_deleted'] = true;
} else {
    echo "    ✗ Project directory still exists\n";
}

// ===== RESULTS =====
echo "\n=== Test Results ===\n";
echo 'Create API call: '.($results['create_api'] ? '✓' : '✗')."\n";
echo 'Provisioning complete: '.($results['provision_complete'] ? '✓' : '✗')."\n";
echo 'Provision broadcasts: '.implode(' → ', $results['provision_broadcasts'])."\n";
echo 'Project created: '.($results['project_created'] ? '✓' : '✗')."\n";
echo 'Delete API call: '.($results['delete_api'] ? '✓' : '✗')."\n";
echo 'Deletion complete: '.($results['deletion_complete'] ? '✓' : '✗')."\n";
echo 'Deletion broadcasts: '.implode(' → ', $results['deletion_broadcasts'])."\n";
echo 'Project deleted: '.($results['project_deleted'] ? '✓' : '✗')."\n";

// Final verdict
$passed = $results['create_api']
    && $results['provision_complete']
    && $results['delete_api']
    && $results['deletion_complete']
    && $results['project_deleted'];

echo "\n".($passed ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED')."\n";
exit($passed ? 0 : 1);
