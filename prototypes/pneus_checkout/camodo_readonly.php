<?php
declare(strict_types=1);
/** Private CLI-only probe. Reads one existing IMAP message, never marks read,
 * writes a record, sends mail, follows a supplier URL or creates an order.
 * Sender authenticity is NOT established by this probe. Not a production worker.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');

function cleanText(DOMNode $node): string {
    return trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
}
function euroCents(string $text): int {
    $text = preg_replace('/\s+/u', '', $text);
    if (!preg_match('/^([0-9]{1,7}),([0-9]{2})€$/uD', $text, $m)) {
        throw new RuntimeException('invalid_eur');
    }
    return (int)$m[1] * 100 + (int)$m[2];
}
function selectionFromHtml(string $html): array {
    if (strlen($html) > 1000000) throw new RuntimeException('oversize_html');
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    if (!$ok) throw new RuntimeException('invalid_html');
    $rows = [];
    foreach ($dom->getElementsByTagName('tr') as $tr) {
        $cells = [];
        foreach ($tr->childNodes as $cell) {
            if (in_array($cell->nodeName, ['td','th'], true)) $cells[] = cleanText($cell);
        }
        if ($cells) $rows[] = $cells;
    }
    $columns = ['Article(s)', 'Qté', 'Prix uni.', 'Total', 'TVA', 'Prix uni.', 'Total'];
    if (!in_array(['', 'PA HT', 'PV TTC'], $rows, true) || !in_array($columns, $rows, true)) {
        throw new RuntimeException('unknown_price_layout');
    }
    $items = array_values(array_filter($rows, fn($r) => count($r) === 7 &&
        preg_match('/\b[0-9]{3}\/[0-9]{2}\s+R[0-9]{2}\b/u', $r[0])));
    if (count($items) !== 1) throw new RuntimeException('single_product_required');
    $item = $items[0];
    if (!preg_match('/^([0-9]{1,2}),00$/D', $item[1], $m)) {
        throw new RuntimeException('invalid_quantity');
    }
    $qty = (int)$m[1];
    $unit = euroCents($item[5]); $total = euroCents($item[6]);
    if ($qty < 1 || $qty > 24 || $unit < 1 || $unit > 10000000 || $qty*$unit !== $total) {
        throw new RuntimeException('retail_total_mismatch');
    }
    $totals = array_values(array_filter($rows, fn($r) => count($r) === 2 && $r[0] === 'Prix total TTC'));
    if (count($totals) !== 1 || euroCents($totals[0][1]) !== $total) {
        throw new RuntimeException('quote_total_mismatch');
    }
    $links = [];
    foreach ($dom->getElementsByTagName('a') as $a) {
        if (cleanText($a) === 'Ajouter au panier') $links[] = $a->getAttribute('href');
    }
    if (count($links) !== 1) throw new RuntimeException('supplier_reference_required');
    $url = parse_url($links[0]);
    if (!$url || ($url['scheme']??'') !== 'https' || ($url['host']??'') !== 's-h-o-p24.pneus100.fr' ||
        ($url['path']??'') !== '/shop/wk.html' || isset($url['user']) || isset($url['pass']) ||
        isset($url['port']) || isset($url['fragment'])) throw new RuntimeException('unknown_supplier_link');
    $parts = explode('&', $url['query']??''); $q = [];
    foreach ($parts as $part) {
        $pair = explode('=', $part, 2);
        if (count($pair) !== 2) throw new RuntimeException('invalid_query');
        $key = urldecode($pair[0]);
        if (isset($q[$key])) throw new RuntimeException('duplicate_query_key');
        $q[$key] = urldecode($pair[1]);
    }
    if (count($q) !== 2 || !isset($q['PID'], $q['Menge']) ||
        !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $q['PID']) || $q['Menge'] !== (string)$qty) {
        throw new RuntimeException('invalid_supplier_reference');
    }
    return ['title'=>trim(explode('Base de calcul HT', $item[0], 2)[0]),
        'reference'=>$q['PID'], 'quantity'=>$qty, 'mail_unit_ttc_cents'=>$unit,
        'mail_total_ttc_cents'=>$total, 'supplier_recheck_required'=>true,
        'source_authenticated'=>false, 'payment_enabled'=>false];
}
function htmlParts($imap, int $uid, object $part, string $section=''): array {
    if (isset($part->disposition) && strtoupper($part->disposition) === 'ATTACHMENT') return [];
    if (isset($part->parts)) {
        $out = [];
        foreach ($part->parts as $n=>$child) {
            $out = array_merge($out, htmlParts($imap, $uid, $child, $section === '' ? (string)($n+1) : $section.'.'.($n+1)));
        }
        return $out;
    }
    if (($part->type??-1) !== 0 || strtoupper($part->subtype??'') !== 'HTML') return [];
    if (($part->bytes??0) > 1000000) throw new RuntimeException('oversize_part');
    $body = $section === '' ? imap_body($imap, $uid, FT_UID|FT_PEEK) : imap_fetchbody($imap, $uid, $section, FT_UID|FT_PEEK);
    if ($body === false || strlen($body) > 1400000) throw new RuntimeException('invalid_body');
    if (($part->encoding??0) === 3) $body = base64_decode($body, true);
    if (($part->encoding??0) === 4) $body = quoted_printable_decode($body);
    if ($body === false) throw new RuntimeException('invalid_encoding');
    foreach ($part->parameters??[] as $param) {
        if (strtolower($param->attribute) === 'charset' && strtoupper($param->value) !== 'UTF-8') {
            $body = iconv($param->value, 'UTF-8', $body);
            if ($body === false) throw new RuntimeException('invalid_charset');
        }
    }
    return [$body];
}

try {
    if (($argv[1]??'') === '--self-test') {
        $fixture = file_get_contents(__DIR__.'/camodo_fixture.html');
        $r = selectionFromHtml($fixture);
        if ($r['quantity'] !== 4 || $r['mail_unit_ttc_cents'] !== 5990 || $r['mail_total_ttc_cents'] !== 23960) {
            throw new RuntimeException('fixture_failed');
        }
        foreach ([['PV TTC','PV HT'],['59,90 €','59.90 €'],['Menge=4','Menge=3'],
            ['PID=EXAMPLE_1','PID=a&PID=b'],['s-h-o-p24.pneus100.fr','example.org']] as [$a,$b]) {
            try { selectionFromHtml(str_replace($a,$b,$fixture)); }
            catch (RuntimeException $e) { continue; }
            throw new RuntimeException('negative_fixture_failed');
        }
        echo "SELF_TEST_OK cases=6\n"; exit;
    }
    if (($argv[1]??'') !== '--uid' || !ctype_digit($argv[2]??'') || (int)$argv[2] < 1) {
        throw new RuntimeException('usage_uid_required');
    }
    $uid = (int)$argv[2];
    $config = require '/home/vp_mail_config.php';
    $imap = @imap_open('{mail84.lwspanel.com:993/imap/ssl}INBOX', 'contact@vosgespneus.com', $config['password'], OP_READONLY, 1);
    if (!$imap) throw new RuntimeException('imap_unavailable');
    try {
        $structure = imap_fetchstructure($imap, $uid, FT_UID);
        if (!$structure) throw new RuntimeException('message_missing');
        $parts = htmlParts($imap, $uid, $structure);
        if (count($parts) !== 1) throw new RuntimeException('single_html_part_required');
        $r = selectionFromHtml($parts[0]);
        // Do not print customer details, PID value, whole mail or cart URL.
        unset($r['reference']); $r['supplier_reference_found'] = true;
        echo json_encode($r, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    } finally { imap_close($imap); }
} catch (Throwable $e) {
    // Only fixed internal failure codes are returned, never exception contents
    // from external services, credentials, body or private paths.
    $code = preg_match('/^[a-z_]+$/D', $e->getMessage()) ? $e->getMessage() : 'probe_failed';
    fwrite(STDERR, 'CAMODO_PROBE_ERROR '.$code.PHP_EOL); exit(1);
}
