<?php
declare(strict_types=1);
/* VOSGES PNEUS: private CLI product auditor and draft queue, no publication API. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Europe/Paris');
umask(0077);
require_once __DIR__.'/vp_stock.php';
if(is_file(__DIR__.'/vp_ebay_prepare.php')) require_once __DIR__.'/vp_ebay_prepare.php';

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

function moneyToCents(array $money): int {
    if (($money['currencyCode']??null)!=='EUR') throw new RuntimeException('devise_inattendue');
    $amount=$money['amount']??null;
    if (!is_string($amount)||!preg_match('/^(\\d{1,9})(?:\\.(\\d{1,2}))?$/D',$amount,$m)) throw new RuntimeException('prix_invalide');
    return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
}

function normalizeProduct(array $product): array {
    if (($product['variants']['pageInfo']['hasNextPage']??true)!==false) throw new RuntimeException('variantes_incompletes');
    $variants=[];
    foreach($product['variants']['nodes']??[] as $variant) {
        $variants[]=['id'=>$variant['id']??null,'sku'=>$variant['sku']??null,
            'price'=>moneyToCents($variant['price']??[]),'available'=>$variant['availableForSale']??null];
    }
    return ['handle'=>$product['handle']??null,'variants'=>$variants,
        'images'=>array_column($product['images']['nodes']??[],'url')];
}

function catalogQuery(): string {
    return <<<'GRAPHQL'
query CommercialCatalog($h0: String!, $h1: String!, $h2: String!, $h3: String!) @inContext(country: FR, language: FR) {
  p0: product(handle: $h0) { ...CommercialProduct }
  p1: product(handle: $h1) { ...CommercialProduct }
  p2: product(handle: $h2) { ...CommercialProduct }
  p3: product(handle: $h3) { ...CommercialProduct }
}
fragment CommercialProduct on Product {
  handle
  images(first: 10) { nodes { url } }
  variants(first: 10) {
    nodes { id sku availableForSale price { amount currencyCode } }
    pageInfo { hasNextPage }
  }
}
GRAPHQL;
}

function fetchCatalog(string $dir): array {
    if (!function_exists('curl_init')) throw new RuntimeException('curl_absent');
    // One official tokenless Storefront query per hour, never spoof a buyer IP.
    $cooldownPath=$dir.'/next_request.json';
    $cooldown=is_file($cooldownPath)?json_decode((string)file_get_contents($cooldownPath),true):[];
    if (time()<(int)($cooldown['not_before']??0)) throw new RuntimeException('lecture_differee');
    atomicWrite($cooldownPath,json_encode(['not_before'=>time()+60],JSON_THROW_ON_ERROR));
    $variables=[];
    foreach(manifest() as $i=>$item) $variables['h'.$i]=$item['handle'];
    $payload=json_encode(['query'=>catalogQuery(),'variables'=>$variables],JSON_THROW_ON_ERROR);
    $body=''; $tooLarge=false; $retryAfter=60;
    $ch=curl_init('https://zaiwdm-st.myshopify.com/api/2026-10/graphql.json');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_USERAGENT=>'VosgesPneus-CatalogAudit/2.0',
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
        CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$retryAfter){
            if(preg_match('/^Retry-After:\\s*(\\d+)/i',$line,$m)) $retryAfter=max(60,min(86400,(int)$m[1]));
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body,&$tooLarge){
            if(strlen($body)+strlen($chunk)>1048576){$tooLarge=true;return 0;}
            $body.=$chunk;return strlen($chunk);
        }
    ]);
    $ok=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($status===429) atomicWrite($cooldownPath,json_encode(['not_before'=>time()+$retryAfter],JSON_THROW_ON_ERROR));
    if($tooLarge) throw new RuntimeException('reponse_trop_grande');
    if($ok===false||$status!==200) throw new RuntimeException('http_'.$status);
    $response=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($response)||!empty($response['errors'])||!is_array($response['data']??null)) throw new RuntimeException('graphql_invalide');
    $catalog=[];
    foreach(manifest() as $i=>$item){
        $product=$response['data']['p'.$i]??null;
        $catalog[$item['handle']]=is_array($product)?$product:null;
    }
    return $catalog;
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
    $raw=['handle'=>$m[0]['handle'],'variants'=>['nodes'=>[['id'=>'gid://shopify/ProductVariant/123','sku'=>$m[0]['sku'],'availableForSale'=>true,'price'=>['amount'=>'69.00','currencyCode'=>'EUR']]],'pageInfo'=>['hasNextPage'=>false]],'images'=>['nodes'=>[['url'=>'https://cdn.shopify.com/a.jpg'],['url'=>'https://cdn.shopify.com/b.jpg']]]];
    $check(evaluateProduct($m[0],normalizeProduct($raw),$now)['issues']===[],'storefront_mapping');
    $check(moneyToCents(['amount'=>'0.01','currencyCode'=>'EUR'])===1,'cent_precision');
    $check(moneyToCents(['amount'=>'49.5','currencyCode'=>'EUR'])===4950,'decimal_padding');
    foreach([['amount'=>'69.00','currencyCode'=>'USD'],['amount'=>'69.001','currencyCode'=>'EUR']] as $bad){
        $rejected=false;try{moneyToCents($bad);}catch(RuntimeException $e){$rejected=true;}
        $check($rejected,'money_rejected');
    }
    $raw['variants']['pageInfo']['hasNextPage']=true;
    $rejected=false;try{normalizeProduct($raw);}catch(RuntimeException $e){$rejected=true;}
    $check($rejected,'pagination_rejected');
    echo "VP_COMMERCIAL_TESTS_OK 19 checks\n";
}

function renderCommercial(string $dir,array $report): void {
    if(function_exists('vpEbayPrepareSave')) vpEbayPrepareSave($dir,$report);
    $rows=$report['items'];
    $h='<meta charset="utf-8"><title>Agent commercial Vosges Pneus</title><h1>Vosges Pneus — contrôle commercial</h1><p>'.htmlspecialchars($report['checked_at']).'</p><p>Préparation active. Publication automatique non connectée. Quantités Shopify actualisées. Stock physique à confirmer avant publication.</p>';
    foreach($rows as $r){$h.='<p>Stock Shopify : '.htmlspecialchars((string)($r['live_inventory_quantity']??'inconnu')).' — '.htmlspecialchars($r['inventory_checked_at']??'').'</p>';$h.='<h2>'.htmlspecialchars($r['sku']).'</h2><p>'.htmlspecialchars($r['action']).'</p><p>'.htmlspecialchars(implode(', ',$r['issues'])).'</p>';if(isset($r['draft']))$h.='<h3>'.htmlspecialchars($r['draft']['title']).'</h3><p>'.htmlspecialchars($r['draft']['description']).'</p><p>'.htmlspecialchars((string)$r['price_eur']).' EUR</p>';}
    atomicWrite($dir.'/rapport.html',$h);
}

try {
    if (in_array('--self-test',$argv,true)){selfTest();exit(0);}
    $dir=__DIR__.'/vp_commercial'; if(!is_dir($dir)&&!mkdir($dir,0700)) throw new RuntimeException('dossier_impossible');
    $lock=fopen($dir.'/run.lock','c'); if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)) exit(0);
    $path=$dir.'/rapport.json';
    $stock=vpStockRead($dir);
    $cached=is_file($path)?json_decode((string)file_get_contents($path),true):null;
    if(!in_array('--force',$argv,true)&&is_array($cached)&&time()-(int)($cached['catalog_checked_unix']??strtotime($cached['checked_at']??'1970-01-01'))<3600){
        $cached['catalog_checked_unix']=$cached['catalog_checked_unix']??strtotime($cached['checked_at']);
        $cached['version']='commercial-lws-3';
        $cached['inventory_checked_at']=$stock['checked_at'];
        $cached['inventory_errors']=$stock['errors'];
        $cached['items']=vpStockMerge($cached['items']??[],$stock);
        atomicWrite($path,json_encode($cached,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
        renderCommercial($dir,$cached);
        echo "VP_COMMERCIAL_CACHE_OK stock_items=".count($stock['items'])." stock_errors=".$stock['errors']."\n";
        exit(($cached['errors']??1)||$stock['errors']?1:0);
    }
    $rows=[];$errors=0;$catalog=[];$catalogError=null;
    try{$catalog=fetchCatalog($dir);}catch(Throwable $e){$catalogError=$e instanceof RuntimeException?$e->getMessage():'json_invalide';}
    foreach(manifest() as $item){
      try{
        if($catalogError!==null) throw new RuntimeException($catalogError);
        if(!is_array($catalog[$item['handle']]??null)) throw new RuntimeException('produit_absent');
        $rows[]=evaluateProduct($item,normalizeProduct($catalog[$item['handle']]),time());
      }
      catch(Throwable $e){$errors++;$rows[]=['sku'=>$item['sku'],'action'=>'controle_impossible','issues'=>['lecture_boutique_echouee'],'error_code'=>$e instanceof RuntimeException?$e->getMessage():'json_invalide','publication_allowed'=>false];}
    }
    $rows=vpStockMerge($rows,$stock);
    $report=['catalog_checked_unix'=>time(),'inventory_checked_at'=>$stock['checked_at'],'inventory_errors'=>$stock['errors'],'version'=>'commercial-lws-3','source'=>'shopify_storefront_tokenless_2026-10','checked_at'=>date(DATE_ATOM),'mode'=>'audit_et_brouillons','publication_active'=>false,'errors'=>$errors,'items'=>$rows];
    atomicWrite($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
    renderCommercial($dir,$report);
    echo 'VP_COMMERCIAL_OK items='.count($rows).' errors='.$errors." publication=off\n";
    exit($errors||$stock['errors']?1:0);
} catch(Throwable $e){fwrite(STDERR,"VP_COMMERCIAL_ERROR\n");exit(1);}
