<?php
declare(strict_types=1);

final class ProductCandidate {
    public static function fingerprint(array $candidate): string {
        $source=strtolower(trim((string)($candidate['source_reference']??'')));
        $refs=array_map(static fn($v)=>strtolower(trim((string)$v)),(array)($candidate['references']??[]));
        sort($refs,SORT_STRING);
        return hash('sha256',$source.'|'.implode('|',$refs));
    }

    public static function isReady(array $c): bool {
        return ($c['decision']??'')==='ready'
            && trim((string)($c['source_reference']??''))!==''
            && trim((string)($c['title']??''))!==''
            && trim((string)($c['description']??''))!==''
            && is_numeric($c['price']??null)
            && (float)$c['price']>0
            && ($c['shopify_status']??'')==='DRAFT'
            && empty($c['missing_fields']??[]);
    }
}
