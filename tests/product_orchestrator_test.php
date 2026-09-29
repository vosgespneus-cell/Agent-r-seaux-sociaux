<?php
declare(strict_types=1);
require __DIR__.'/../deploy/lws/private/product_orchestrator.php';

$intake=['source_reference'=>'LOT-001'];
$facts=['title'=>'Démarreur Valeo','description'=>'Pièce d’occasion vérifiée.','price'=>45,'references'=>['ABC']];
$c=ProductOrchestrator::buildCandidate($intake,$facts);
if($c['decision']!=='ready') exit(1);
$d=ProductOrchestrator::prepareDraft($c);
if(!$d['ready'] || strlen($d['fingerprint'])!==64 || $d['draft']['status']!=='DRAFT') exit(2);

$c2=ProductOrchestrator::buildCandidate($intake,['title'=>'Pièce sans prix','description'=>'Test']);
if($c2['decision']!=='needs_information' || !in_array('price',$c2['missing_fields'],true)) exit(3);
echo "VP_PRODUCT_ORCHESTRATOR_OK\n";
