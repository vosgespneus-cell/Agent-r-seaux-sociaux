<?php
declare(strict_types=1);
require __DIR__.'/../deploy/lws/private/product_candidate.php';

$c=[
 'decision'=>'ready',
 'source_reference'=>' PHOTO-001 ',
 'title'=>'Alternateur test',
 'description'=>'Description vérifiée',
 'price'=>50,
 'references'=>['ABC123',' DEF456 '],
 'missing_fields'=>[],
 'shopify_status'=>'DRAFT'
];
if(!ProductCandidate::isReady($c)) exit(1);
$a=ProductCandidate::fingerprint($c);
$c['references']=['DEF456','ABC123'];
$b=ProductCandidate::fingerprint($c);
if($a!==$b) exit(2);

$c['shopify_status']='ACTIVE';
if(ProductCandidate::isReady($c)) exit(3);
echo "VP_PRODUCT_CANDIDATE_OK\n";
