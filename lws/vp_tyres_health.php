<?php
declare(strict_types=1);
// Private, read-only counters. Never opens mail, credentials, or quote records.
function vp_tyres_health(string $dir, int $now): array {
    $read = static function(string $path): string {
        clearstatcache(true, $path);
        if (!is_file($path) || filesize($path) > 65536) return '';
        return (string)@file_get_contents($path);
    };
    $result = [];
    foreach (['intake', 'bridge'] as $stage) {
        $path = $dir.'/client-'.$stage.'.last.'.($stage === 'intake' ? 'json' : 'log');
        $raw = $read($path);
        clearstatcache(true, $path);
        $at = is_file($path) ? (int)filemtime($path) : 0;
        $summary = null;
        if ($stage === 'intake') {
            $data = json_decode($raw, true);
            if (is_array($data) && is_int($data['checked_at'] ?? null)) {
                $at = $data['checked_at'];
                $summary = $data['summary'] ?? null;
            }
        } elseif (preg_match('/^CLIENT_BRIDGE_RESULT (\{[^\r\n]+\}) invoices_sent=\d+ supplier_orders=\d+\s*$/D', $raw, $match)) {
            $summary = json_decode($match[1], true);
        }
        $counts = [];
        $fields = $stage === 'intake'
            ? ['scanned', 'already_attempted', 'duplicates', 'eligible', 'sent_attempts', 'accepted', 'errors']
            : ['saved', 'created', 'reconciled', 'unchanged', 'held', 'errors'];
        $valid = is_array($summary);
        foreach ($fields as $field) {
            if (!is_int($summary[$field] ?? null) || $summary[$field] < 0) $valid = false;
            else $counts[$field] = $summary[$field];
        }
        $err = $dir.'/client-'.$stage.'.last.err';
        clearstatcache(true, $err);
        $hasError = is_file($err) && filesize($err) > 0;
        if ($hasError) $health = 'error';
        elseif (!$at) $health = 'unknown';
        elseif ($at > $now + 30 || $now - $at > 180) $health = 'stale';
        elseif ($raw === '' && $now - $at < 60) $health = 'running';
        elseif (!$valid || ($counts['errors'] ?? 0) > 0 || ($counts['held'] ?? 0) > 0) $health = 'error';
        else $health = 'ok';
        $result['tyre_'.$stage] = [
            'health' => $health,
            'last_success_at' => $health === 'ok' ? (new DateTimeImmutable('@'.$at))->setTimezone(new DateTimeZone('Europe/Paris'))->format(DateTimeInterface::RFC3339) : null,
            'exit_code' => null,
            'failures' => null,
            'checked_at' => $at ?: null,
            'counts' => $counts,
        ];
    }
    return $result;
}
