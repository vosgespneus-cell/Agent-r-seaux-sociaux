<?php
declare(strict_types=1);
require __DIR__.'/../deploy/lws/private/shopify_proof_verifier.php';
$r=ShopifyProofVerifier::verifyDraft(
 ['title'=>'Test','sku'=>'ABC','price'=>'10.00'],
 ['id'=>'gid://shopify/Product/1','title'=>'Test','status'=>'DRAFT','variants'=>[['sku'=>'ABC','price'=>'10.00']]]
);
if(!$r['verified']) exit(1);
$r2=ShopifyProofVerifier::verifyDraft(
 ['title'=>'Test','sku'=>'ABC','price'=>'10.00'],
 ['id'=>'gid://shopify/Product/1','title'=>'Test','status'=>'ACTIVE','variants'=>[['sku'=>'ABC','price'=>'10.00']]]
);
if($r2['verified']) exit(2);
echo "VP_SHOPIFY_PROOF_OK\n";
