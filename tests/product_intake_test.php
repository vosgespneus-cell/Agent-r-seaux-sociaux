<?php
declare(strict_types=1);
require __DIR__.'/../deploy/lws/private/product_intake.php';

$raw=json_encode([
 'event_id'=>'ATELIER-TEST-001',
 'source'=>'atelier',
 'kind'=>'produit',
 'payload'=>[
   'source_reference'=>'LOT-TEST-001',
   'reference_text'=>'ABC123',
   'notes'=>'Pièce test',
   'media'=>['https://example.invalid/1.jpg','ftp://bad.invalid/2.jpg']
 ]
],JSON_THROW_ON_ERROR);
$i=ProductIntake::fromEvent('VP-TEST','atelier',$raw);
if($i['source_reference']!=='LOT-TEST-001') exit(1);
if(count($i['media'])!==1) exit(2);
if($i['reference_text']!=='ABC123') exit(3);

$bad=json_encode(['event_id'=>'X','source'=>'atelier','kind'=>'message'],JSON_THROW_ON_ERROR);
try { ProductIntake::fromEvent('VP-X','atelier',$bad); exit(4); }
catch(InvalidArgumentException $e){}
echo "VP_PRODUCT_INTAKE_OK\n";
