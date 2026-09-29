<?php
declare(strict_types=1);
require __DIR__.'/../deploy/lws/private/product_draft_validator.php';

$ok=ProductDraftValidator::validate([
 'title'=>'Démarreur test',
 'description'=>'Pièce test pour validation locale.',
 'price'=>49,
 'source_reference'=>'TEST-REF-001',
 'tags'=>['occasion','test','occasion']
]);
if(!$ok['valid'] || $ok['normalized']['status']!=='DRAFT' || count($ok['normalized']['tags'])!==2){exit(1);}

$bad=ProductDraftValidator::validate([
 'title'=>'',
 'description'=>'',
 'price'=>0,
 'source_reference'=>'',
 'publish'=>true
]);
if($bad['valid'] || count($bad['errors'])<4){exit(2);}

echo "VP_PRODUCT_VALIDATOR_OK\n";
