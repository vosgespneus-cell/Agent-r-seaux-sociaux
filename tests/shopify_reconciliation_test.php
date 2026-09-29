<?php
declare(strict_types=1);

$fingerprint=hash('sha256','LOT-001|ABC123');
$tag='vpauto:'.substr($fingerprint,0,24);
if(!preg_match('/^vpauto:[a-f0-9]{24}$/',$tag)) exit(1);
if($tag!=='vpauto:'.substr(hash('sha256','LOT-001|ABC123'),0,24)) exit(2);
if(str_contains($tag,'LOT') || str_contains($tag,'ABC')) exit(3);
echo "VP_SHOPIFY_RECONCILIATION_OK\n";
