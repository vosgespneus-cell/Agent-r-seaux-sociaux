<?php
declare(strict_types=1);

// CLI-only synthetic test: no caller name, phone number, recording or secret is printed.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = require __DIR__ . '/vp_config.php';
$payload = json_encode([
    'event_id' => 'smoke-telephone-route-20260929',
    'source' => 'telephone',
    'kind' => 'appel',
    'test' => true,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$endpoint = 'https://agents.vosgespneus.com/events';

function postTest(string $endpoint, string $payload, string $signature): int {
    $handle = curl_init($endpoint);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-VP-Signature: ' . $signature,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    if ($response === false) {
        fwrite(STDERR, "VP_SMOKE_NETWORK_ERROR\n");
        exit(1);
    }
    return $status;
}

$signature = 'sha256=' . hash_hmac('sha256', $payload, $config['webhook_secret']);
$first = postTest($endpoint, $payload, $signature);
$repeat = postTest($endpoint, $payload, $signature);
$invalid = postTest($endpoint, $payload, 'sha256=invalid');
echo "first={$first} repeat={$repeat} invalid={$invalid}\n";
if ($first !== 202 && $first !== 200 || $repeat !== 200 || $invalid !== 401) {
    exit(1);
}
echo "VP_SMOKE_OK\n";
