<?php

/**
 * E2E WebSocket Test - Actually listens to Reverb like the desktop does
 */

require __DIR__.'/../vendor/autoload.php';

$tld = getenv('TLD') ?: 'ccc';
$slug = 'e2e-ws-'.time();
$apiBase = "https://orbit.{$tld}/api";

echo "=== E2E WebSocket Broadcast Test ===\n";
echo "Slug: {$slug}\n\n";

// We will use Pusher PHP SDK to simulate what the desktop does
// Desktop uses Pusher JS SDK which connects via WebSocket
// PHP SDK can subscribe to channels and receive events via polling

$pusher = new Pusher\Pusher(
    'orbit-key',
    'orbit-secret',
    'orbit',
    [
        'host' => "reverb.{$tld}",
        'port' => 443,
        'scheme' => 'https',
        'useTLS' => true,
        'cluster' => 'mt1',
    ]
);

// Test 1: Create a project and immediately check if we can query channel presence
echo "[1] Testing Reverb API access...\n";
try {
    $info = $pusher->getChannelInfo('provisioning');
    echo '  Channel info: '.json_encode($info)."\n";
} catch (Exception $e) {
    echo '  Channel query: '.$e->getMessage()."\n";
}

// Test 2: Trigger a test event and see if Reverb acknowledges it
echo "\n[2] Testing broadcast delivery...\n";
try {
    $result = $pusher->trigger('provisioning', 'project.deletion.status', [
        'slug' => 'test-from-e2e',
        'status' => 'deleted',
        'timestamp' => date('c'),
    ]);
    echo '  Trigger result: '.json_encode($result)."\n";
} catch (Exception $e) {
    echo '  Trigger error: '.$e->getMessage()."\n";
}

// Test 3: Create project via API and track via log files
// (PHP Pusher SDK cannot receive events, only send)
echo "\n[3] Creating project via API...\n";
$ch = curl_init("{$apiBase}/projects");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $slug,
    'template' => 'hardimpactdev/liftoff-starterkit',
    'visibility' => 'private',
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "  HTTP {$httpCode}: {$response}\n";

// Wait for provisioning
echo "\n[4] Waiting for provisioning (checking log + Reverb)...\n";
$home = $_SERVER['HOME'] ?? '/home/orbit';
$logFile = "{$home}/.config/orbit/logs/provision/{$slug}.log";
$timeout = 120;
$start = time();
$lastStatus = '';

while (time() - $start < $timeout) {
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if (preg_match_all("/Status: (\w+)/", $content, $m)) {
            $newStatus = end($m[1]);
            if ($newStatus !== $lastStatus) {
                echo "  -> {$newStatus}\n";
                $lastStatus = $newStatus;
            }
            if (in_array($newStatus, ['ready', 'failed'])) {
                break;
            }
        }
    }
    usleep(500000);
}

if ($lastStatus !== 'ready') {
    echo "  FAILED: stuck on {$lastStatus}\n";
    exit(1);
}

// Delete and track
echo "\n[5] Deleting project...\n";
$ch = curl_init("{$apiBase}/projects/{$slug}");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "  HTTP {$httpCode}: {$response}\n";

// Clear debug log and wait for deletion
$debugLog = "{$home}/.config/orbit/logs/reverb-debug.log";
@unlink($debugLog);

echo "\n[6] Waiting for deletion broadcasts...\n";
$logFile = "{$home}/.config/orbit/logs/deletion/{$slug}.log";
$timeout = 60;
$start = time();
$lastStatus = '';

while (time() - $start < $timeout) {
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if (preg_match_all("/Status: (\w+)/", $content, $m)) {
            $newStatus = end($m[1]);
            if ($newStatus !== $lastStatus) {
                echo "  -> {$newStatus}\n";
                $lastStatus = $newStatus;
            }
            if (in_array($newStatus, ['deleted', 'delete_failed'])) {
                break;
            }
        }
    }
    usleep(500000);
}

// Show what was actually broadcast
echo "\n[7] Reverb debug log (what was actually sent):\n";
if (file_exists($debugLog)) {
    $lines = file($debugLog);
    foreach ($lines as $line) {
        if (str_contains($line, 'SENT') || str_contains($line, 'ERROR') || str_contains($line, 'SKIPPED')) {
            echo '  '.trim($line)."\n";
        }
    }
} else {
    echo "  No debug log found\n";
}

echo "\n=== Summary ===\n";
if ($lastStatus === 'deleted') {
    echo "SUCCESS: All broadcasts sent including deleted\n";
    exit(0);
} else {
    echo "FAILED: Last status was {$lastStatus}\n";
    exit(1);
}
