<?php
declare(strict_types=1);
/* VOSGES PNEUS: private CLI product auditor and draft queue, no publication API. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Europe/Paris');
umask(0077);

function manifest(): array {
    return [
      ['sku'=>'VP-DEM-M000T22471','handle'=>'demarreur-mitsubishi-psa-1-4-1-6-hdi-m000t22471-9663528880-occasion','price'=>6900,'title'=>'Démarreur Mitsubishi M000T22471 PSA 9663528880 occasion','note'=>'Références M000T22471 / 9663528880. Comparez la référence de votre pièce avant commande. Fonctionnement testé non affirmé.','ebay_id'=>null],
      ['sku'=>'VP-BER-PART-FARG-RF','handle'=>'feu-arriere-gauche-citroen-berlingo-peugeot-partner-m49-m59-cote-conducteur','price'=>3900,'title'=>'Feu arrière gauche Berlingo I Partner I M49 M59 occasion','note'=>'Côté conducteur. Plusieurs montages existent : comparez forme, fixation et porte-ampoules. Fonctionnement testé non affirmé.','ebay_id'=>null],
      ['sku'=>'VP-BER-M59-PHG-9644150980','handle'=>'phare-avant-gauche-citroen-berlingo-m59-phase-2-9644150980-6204ax','price'=>4900,'title'=>'Phare gauche Berlingo M59 phase 2 9644150980 6204AX occasion','note'=>'Optique terne / opacité et micro-rayures visibles. Comparez référence et connectique. Fonctionnement testé non affirmé.','ebay_id'=>null],
      ['sku'=>'VP-NEMO-BIP-FARD-01','handle'=>'feu-arriere-droit-citroen-nemo-peugeot-bipper-fiat-fiorino-cote-passager','price'=>4900,'title'=>'Feu arrière droit Nemo Bipper Fiorino occasion côté passager','note'=>'Comparez forme, fixations et montage des portes arrière. Fonctionnement testé non affirmé.','ebay_id'=>'800759185876']
    ];
}

function evaluateProduct(array $item, array $product, int $now): array {
    $issues=[];
    if (($product['handle']??null)!==$item['handle']) $issues[]='fiche_inattendue';
    $variants=array_values(array_filter($product['variants']??[], fn($v)=>($v['sku']??null)===$item['sku']));
    if (count($variants)!==1) $issues[]='sku_absent_ou_ambigu';
    $variant=$variants[0]??[];
    if (($variant['price']??null)!==$item['price']) $issues[]='prix_a_verifier';
    if (($variant['available']??false)!==true) $issues[]='indisponible_boutique';
    $photos=array_values(array_filter($product['images']??[], fn($u)=>is_string($u)&&preg_match('~^https://(?:www\.vosgespneus\.com|cdn\.shopify\.com)/~',$u)));
    if (count($photos)<2) $issues[]='photos_insuffisantes';
    $confirmed=strtotime('2026-10-05T08:20:38+02:00');
    if ($now-$confirmed>36*3600) $issues[]='stock_physique_a_reconfirmer';
    return [
      'sku'=>$item['sku'],'shopify_handle'=>$item['handle'],'shopify_variant_id'=>$variant['id']??null,
      'price_eur'=>$item['price']/100,'store_available'=>$variant['available']??null,
      'physical_stock_confirmation_at'=>date(DATE_ATOM,$confirmed),'live_inventory_quantity'=>null,
      'inventory_note'=>'La disponibilité publique Shopify ne prouve pas la quantité en stock.',
      'ebay_listing_id'=>$item['ebay_id'],'action'=>$item['ebay_id']?'annonce_existante_ne_pas_dupliquer':'brouillon_a_controler',
      'issues'=>$issues,'draft'=>['title'=>$item['title'],'condition'=>'occasion','description'=>$item['note'].' Photos de la pièce sur la boutique. Vosges Pneus, Thaon-les-Vosges.','photos'=>$photos],
      'publication_allowed'=>false,'publication_blockers'=>['connexion_api_publication_absente','stock_en_temps_reel_non_verifie','regles_livraison_retour_a_valider'],
      'shopify_url'=>'https://www.vosgespneus.com/products/'.$item['handle']
    ];
}

function fetchProduct(string $handle): array {
    $url='https://www.vosgespneus.com/products/'.$handle.'.js';
    if (!function_exists('curl_init')) throw new RuntimeException('curl_absent');
    $body=''; $tooLarge=false;
    // LWS overrides this hosted domain in /etc/hosts; use public DNS, keeping TLS verification.
    $records=dns_get_record('www.vosgespneus.com',DNS_A);
    $ips=[];
    foreach($records?:[] as $record) {
        $ip=$record['ip']??'';
        if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) $ips[]=$ip;
    }
    if(!$ips) throw new RuntimeException('dns_public_absent');
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_RESOLVE,['www.vosgespneus.com:443:'.implode(',',array_unique($ips))]);
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'VosgesPneus-CatalogAudit/1.0',CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body,&$tooLarge){ if(strlen($body)+strlen($chunk)>1048576){$tooLarge=true;return 0;} $body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($tooLarge) throw new RuntimeException('reponse_trop_grande');
    if ($ok===false||$status!==200) throw new RuntimeException('http_'.$status);
    $data=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($data)||!isset($data['variants'])) throw new RuntimeException('format_inattendu');
    return $data;
}

function atomicWrite(string $path,string $body): void {
    $tmp=$path.'.tmp.'.getmypid();
    if (file_put_contents($tmp,$body,LOCK_EX)!==strlen($body)) throw new RuntimeException('ecriture_impossible');
    chmod($tmp,0600);
    if (!rename($tmp,$path)) throw new RuntimeException('remplacement_impossible');
}

function selfTest(): void {
    $m=manifest(); $now=strtotime('2026-10-05T13:00:00+02:00');
    $fixture=['handle'=>$m[0]['handle'],'variants'=>[['sku'=>$m[0]['sku'],'price'=>6900,'available'=>true,'id'=>123]],'images'=>['https://cdn.shopify.com/a.jpg','https://cdn.shopify.com/b.jpg']];
    $check=static function(bool $ok,string $name){ if(!$ok) throw new RuntimeException('test_'.$name); };
    $r=evaluateProduct($m[0],$fixture,$now); $check($r['issues']===[],'normal'); $check($r['publication_allowed']===false,'no_publish');
    $f=$fixture;$f['variants'][0]['sku']='OTHER';$check(in_array('sku_absent_ou_ambigu',evaluateProduct($m[0],$f,$now)['issues'],true),'sku');
    $f=$fixture;$f['variants'][]=$f['variants'][0];$check(in_array('sku_absent_ou_ambigu',evaluateProduct($m[0],$f,$now)['issues'],true),'duplicate_sku');
    $f=$fixture;$f['variants'][0]['price']=1;$check(in_array('prix_a_verifier',evaluateProduct($m[0],$f,$now)['issues'],true),'price');
    $f=$fixture;$f['variants'][0]['available']=false;$check(in_array('indisponible_boutique',evaluateProduct($m[0],$f,$now)['issues'],true),'sold');
    $f=$fixture;$f['images']=['http://evil.test/x'];$check(in_array('photos_insuffisantes',evaluateProduct($m[0],$f,$now)['issues'],true),'photos');
    $check(in_array('stock_physique_a_reconfirmer',evaluateProduct($m[0],$fixture,$now+48*3600)['issues'],true),'stale_stock');
    $check(evaluateProduct($m[3],$fixture,$now)['action']==='annonce_existante_ne_pas_dupliquer','duplicate_ebay');
    foreach($m as $i) $check(strlen($i['title'])<=80,'title_length');
    echo "VP_COMMERCIAL_TESTS_OK 13 checks\n";
}

try {
    if (in_array('--self-test',$argv,true)){selfTest();exit(0);}
    $dir=__DIR__.'/vp_commercial'; if(!is_dir($dir)&&!mkdir($dir,0700)) throw new RuntimeException('dossier_impossible');
    $lock=fopen($dir.'/run.lock','c'); if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)) exit(0);
    $path=$dir.'/rapport.json';
    if(!in_array('--force',$argv,true)&&is_file($path)&&time()-filemtime($path)<3600){echo "VP_COMMERCIAL_CACHE_OK\n";exit(0);}
    $rows=[];$errors=0;
    foreach(manifest() as $item){
      try{$rows[]=evaluateProduct($item,fetchProduct($item['handle']),time());}
      catch(Throwable $e){$errors++;$rows[]=['sku'=>$item['sku'],'action'=>'controle_impossible','issues'=>['lecture_boutique_echouee'],'error_code'=>$e instanceof RuntimeException?$e->getMessage():'json_invalide','publication_allowed'=>false];}
    }
    $report=['version'=>'commercial-lws-1','checked_at'=>date(DATE_ATOM),'mode'=>'audit_et_brouillons','publication_active'=>false,'errors'=>$errors,'items'=>$rows];
    atomicWrite($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
    $h='<meta charset="utf-8"><title>Agent commercial Vosges Pneus</title><h1>Vosges Pneus — contrôle commercial</h1><p>'.htmlspecialchars($report['checked_at']).'</p><p>Préparation active. Publication automatique non connectée. Stock réel à vérifier avant publication.</p>';
    foreach($rows as $r){$h.='<h2>'.htmlspecialchars($r['sku']).'</h2><p>'.htmlspecialchars($r['action']).'</p><p>'.htmlspecialchars(implode(', ',$r['issues'])).'</p>';if(isset($r['draft']))$h.='<h3>'.htmlspecialchars($r['draft']['title']).'</h3><p>'.htmlspecialchars($r['draft']['description']).'</p><p>'.htmlspecialchars((string)$r['price_eur']).' EUR</p>';}
    atomicWrite($dir.'/rapport.html',$h);
    echo 'VP_COMMERCIAL_OK items='.count($rows).' errors='.$errors." publication=off\n";
    exit($errors?1:0);
} catch(Throwable $e){fwrite(STDERR,"VP_COMMERCIAL_ERROR\n");exit(1);}
