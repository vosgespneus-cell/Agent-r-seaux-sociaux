<?php
declare(strict_types=1);

// Deploy only this file to htdocs/agents.vosgespneus.com/index.php.
// The config and database remain outside htdocs, under the account's home directory.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($path, ['/events', '/index.php'], true)) {
    respond(404, ['error' => 'introuvable']);
}
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    respond(403, ['error' => 'https requis']);
}
$length = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($length === false || $length === null || $length < 1 || $length > 65536) {
    respond(413, ['error' => 'taille invalide']);
}
$raw = file_get_contents('php://input', false, null, 0, 65537);
if ($raw === false || strlen($raw) !== $length) {
    respond(400, ['error' => 'corps invalide']);
}

$configPath = dirname(__DIR__, 2) . '/home/vp_config.php';
if (!is_file($configPath)) {
    respond(503, ['error' => 'service indisponible']);
}
$config = require $configPath;
if (!is_array($config) || !isset($config['webhook_secret'], $config['db_dsn'], $config['db_user'], $config['db_password'])
    || !is_string($config['webhook_secret']) || strlen($config['webhook_secret']) < 32
    || str_starts_with($config['webhook_secret'], 'REPLACE_')) {
    respond(503, ['error' => 'service indisponible']);
}
$signature = $_SERVER['HTTP_X_VP_SIGNATURE'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $raw, $config['webhook_secret']);
if (!hash_equals($expected, $signature)) {
    respond(401, ['error' => 'signature invalide']);
}

$data = json_decode($raw, true);
$sources = ['make', 'contact', 'telephone', 'whatsapp', 'atelier'];
$kinds = ['message', 'appel', 'rendez_vous', 'produit', 'media'];
if (!is_array($data) || !isset($data['event_id'], $data['source'], $data['kind'])
    || !is_string($data['event_id']) || !preg_match('/^[\x21-\x7E]{1,128}$/D', $data['event_id'])
    || !in_array($data['source'], $sources, true) || !in_array($data['kind'], $kinds, true)) {
    respond(400, ['error' => 'événement invalide']);
}
$taskId = 'VP-' . strtoupper(substr(hash('sha256', $data['event_id']), 0, 20));

try {
    $db = new PDO($config['db_dsn'], $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $statement = $db->prepare('INSERT IGNORE INTO vp_events (event_id, source, kind, body, task_id) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$data['event_id'], $data['source'], $data['kind'], $raw, $taskId]);
    $duplicate = $statement->rowCount() === 0;
    if ($duplicate) {
        $lookup = $db->prepare('SELECT task_id, body FROM vp_events WHERE event_id = ?');
        $lookup->execute([$data['event_id']]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($existing === false) {
            throw new RuntimeException('Événement conflictuel');
        }
        if (!hash_equals(hash('sha256', $existing['body']), hash('sha256', $raw))) {
            respond(409, ['error' => 'identifiant déjà utilisé']);
        }
        $taskId = $existing['task_id'];
    }
    respond($duplicate ? 200 : 202, ['task_id' => $taskId, 'duplicate' => $duplicate]);
} catch (Throwable $error) {
    // Never include DB details, customer payloads, or credentials in HTTP responses.
    respond(503, ['error' => 'journal indisponible']);
}
