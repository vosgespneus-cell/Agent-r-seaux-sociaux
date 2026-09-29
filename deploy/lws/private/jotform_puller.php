<?php
declare(strict_types=1);

// CLI only. Install as home/vp_jotform.php, next to the private config files.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$apiFile = __DIR__ . '/vp_jotform_config.php';
if (!is_file($apiFile)) {
    echo "JOTFORM_NOT_CONFIGURED\n";
    exit(0);
}
$api = require $apiFile;
if (!is_array($api) || !is_string($api['api_key'] ?? null)
    || strlen($api['api_key']) < 16 || str_starts_with($api['api_key'], 'REPLACE_')) {
    echo "JOTFORM_NOT_CONFIGURED\n";
    exit(0);
}

$formId = '262624697144060'; // Demande de pneus – VOSGES PNEUS
$source = 'jotform:pneus:' . $formId;
$config = require __DIR__ . '/vp_config.php';
$db = new PDO($config['db_dsn'], $config['db_user'], $config['db_password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
if ((int) $db->query("SELECT GET_LOCK('vp_jotform_pneus', 0)")->fetchColumn() !== 1) {
    exit(0);
}

try {
    $query = $db->prepare('SELECT cursor FROM vp_sync_state WHERE source = ?');
    $query->execute([$source]);
    $cursor = (string) ($query->fetchColumn() ?: '0');
    if (!preg_match('/^[0-9]{1,32}$/D', $cursor)) {
        throw new RuntimeException('Invalid cursor');
    }
    $maxId = $cursor;
    $offset = 0;
    $insert = $db->prepare('INSERT IGNORE INTO vp_events (event_id, source, kind, body, task_id) VALUES (?, ?, ?, ?, ?)');
    for ($page = 0; $page < 10; $page++) {
        $params = http_build_query([
            'limit' => 100,
            'offset' => $offset,
            'orderby' => 'id',
            'filter' => json_encode(['id:gt' => $cursor], JSON_THROW_ON_ERROR),
        ]);
        $url = 'https://api.jotform.com/form/' . $formId . '/submissions?' . $params;
        $request = curl_init($url);
        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['APIKEY: ' . $api['api_key']],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw = curl_exec($request);
        $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
        if (!is_string($raw) || $status !== 200 || strlen($raw) > 2_000_000) {
            throw new RuntimeException('Jotform request failed');
        }
        $response = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        $items = $response['content'] ?? null;
        if (($response['responseCode'] ?? null) !== 200 || !is_array($items)) {
            throw new RuntimeException('Jotform response invalid');
        }
        $db->beginTransaction();
        foreach ($items as $item) {
            if (!is_array($item) || (string) ($item['form_id'] ?? '') !== $formId
                || !preg_match('/^[0-9]{1,32}$/D', (string) ($item['id'] ?? ''))
                || !is_array($item['answers'] ?? null)) {
                throw new RuntimeException('Jotform submission invalid');
            }
            $id = (string) $item['id'];
            $eventId = 'JF-' . $id;
            $body = json_encode([
                'event_id' => $eventId,
                'form_id' => $formId,
                'submission_id' => $id,
                'created_at' => $item['created_at'] ?? null,
                'answers' => $item['answers'],
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if (strlen($body) > 1_000_000) {
                throw new RuntimeException('Submission too large');
            }
            $taskId = 'VP-' . strtoupper(substr(hash('sha256', $eventId), 0, 20));
            $insert->execute([$eventId, 'contact', 'message', $body, $taskId]);
            if (strlen($id) > strlen($maxId) || (strlen($id) === strlen($maxId) && strcmp($id, $maxId) > 0)) {
                $maxId = $id;
            }
        }
        $db->commit();
        if (count($items) < 100) {
            $save = $db->prepare('INSERT INTO vp_sync_state (source, cursor) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE cursor = VALUES(cursor)');
            $save->execute([$source, $maxId]);
            echo "JOTFORM_SYNC_OK\n";
            exit(0);
        }
        $offset += count($items);
    }
    throw new RuntimeException('Backlog exceeds one run');
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "JOTFORM_SYNC_ERROR\n");
    exit(1);
} finally {
    $db->query("SELECT RELEASE_LOCK('vp_jotform_pneus')");
}
