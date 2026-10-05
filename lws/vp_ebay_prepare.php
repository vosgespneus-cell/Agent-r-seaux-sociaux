<?php
declare(strict_types=1);
// Private CLI: turns the commercial audit into an eBay work queue.
// No marketplace writes until a separately reviewed API executor is connected.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function vpEbayPrepare(array $report, int $now): array {
    $items=[];
    foreach ($report['items']??[] as $r) {
        $sku=$r['sku']??'';
        if (!is_string($sku)||$sku==='') continue;
        $blockers=[];
        if (($report['errors']??1)!==0||($report['inventory_errors']??1)!==0)
            $blockers[]='audit_en_erreur';
        $stockAt=strtotime($r['inventory_checked_at']??'');
        if ($stockAt===false||$stockAt>$now+30||$now-$stockAt>300)
            $blockers[]='stock_non_recent';
        if (!is_int($r['live_inventory_quantity']??null)||$r['live_inventory_quantity']!==1)
            $blockers[]='quantite_unitaire_non_verifiee';
        $physicalAt=strtotime($r['physical_stock_confirmation_at']??'');
        if ($physicalAt===false||$physicalAt>$now+30||$now-$physicalAt>36*3600)
            $blockers[]='stock_physique_a_reconfirmer';
        foreach ($r['issues']??[] as $issue) $blockers[]=$issue;
        $existing=$r['ebay_listing_id']??null;
        // Defense in depth: known active Nemo listing must never enter creation.
        if ($sku==='VP-NEMO-BIP-FARD-01') $existing='800759185876';
        $candidate=[
            'sku'=>$sku,'marketplace'=>'EBAY_FR','currency'=>'EUR',
            'price_eur'=>$r['price_eur']??null,'quantity'=>1,
            'title'=>$r['draft']['title']??null,
            'description'=>$r['draft']['description']??null,
            'photos'=>$r['draft']['photos']??[],
            'condition_description'=>'Occasion, fonctionnement teste non affirme'
        ];
        if (!is_string($candidate['title'])||strlen($candidate['title'])>80
            ||!is_string($candidate['description'])||count($candidate['photos'])<2)
            $blockers[]='contenu_incomplet';
        $key=hash('sha256',json_encode($candidate,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $items[]=[
            'sku'=>$sku,'revision'=>$key,'action'=>$existing?'surveiller_annonce_existante':'preparer_publication',
            'existing_listing_id'=>$existing,'candidate'=>$candidate,
            'eligible_for_api_preparation'=>!$existing&&!$blockers,
            'validation_blockers'=>array_values(array_unique($blockers)),
            'publication_active'=>false,
            'connection_blockers'=>$existing?[]:[
                'oauth_ebay_absent','categorie_et_aspects_a_verifier',
                'etat_ebay_a_valider_sans_promesse_de_fonctionnement',
                'politiques_livraison_retour_et_lieu_a_relier',
                'controle_doublons_sur_toutes_les_annonces_a_connecter',
                'gestion_vente_et_stock_a_connecter',
                'executeur_api_publication_non_connecte'
            ]
        ];
    }
    return ['version'=>'ebay-prepare-1','checked_at'=>date(DATE_ATOM,$now),
        'mode'=>'preparation_automatique','publication_active'=>false,'items'=>$items];
}

function vpEbayPrepareSave(string $dir,array $report): void {
    $queue=vpEbayPrepare($report,time());
    $body=json_encode($queue,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $target=$dir.'/ebay_queue.json';$tmp=$target.'.tmp.'.getmypid();
    if(file_put_contents($tmp,$body,LOCK_EX)!==strlen($body))throw new RuntimeException('ebay_queue_write');
    chmod($tmp,0600);
    if(!rename($tmp,$target))throw new RuntimeException('ebay_queue_rename');
    // Only record a new content revision; every execution still refreshes validation.
    $previous=$dir.'/ebay_queue_revision.json';
    $state=is_file($previous)?json_decode((string)file_get_contents($previous),true):[];
    $revision=hash('sha256',json_encode(array_column($queue['items'],'revision'),JSON_THROW_ON_ERROR));
    if(($state['revision']??null)!==$revision){
        $event=json_encode(['at'=>$queue['checked_at'],'event'=>'queue_prepared','revision'=>$revision,
            'items'=>count($queue['items']),'publication_active'=>false],JSON_THROW_ON_ERROR)."\n";
        if(file_put_contents($dir.'/ebay_journal.jsonl',$event,FILE_APPEND|LOCK_EX)!==strlen($event))
            throw new RuntimeException('ebay_journal_write');
        chmod($dir.'/ebay_journal.jsonl',0600);
        $tmp=$previous.'.tmp.'.getmypid();
        if(file_put_contents($tmp,json_encode(['revision'=>$revision],JSON_THROW_ON_ERROR),LOCK_EX)===false)
            throw new RuntimeException('ebay_revision_write');
        chmod($tmp,0600);
        if(!rename($tmp,$previous))throw new RuntimeException('ebay_revision_rename');
    }
}

if (realpath($argv[0]??'')===__FILE__) {
    date_default_timezone_set('Europe/Paris');umask(0077);
    if(in_array('--self-test',$argv,true)){
        $now=strtotime('2026-10-05T15:30:00+02:00');
        $r=['sku'=>'VP-DEM-M000T22471','issues'=>[],'live_inventory_quantity'=>1,
            'inventory_checked_at'=>date(DATE_ATOM,$now),'physical_stock_confirmation_at'=>date(DATE_ATOM,$now),
            'draft'=>['title'=>'Demarreur occasion','description'=>'Comparer les references','photos'=>['https://cdn.shopify.com/a','https://cdn.shopify.com/b']],'price_eur'=>69];
        $base=['errors'=>0,'inventory_errors'=>0,'items'=>[$r]];
        $check=static function($ok){if(!$ok)throw new RuntimeException('ebay_prepare_test');};
        $q=vpEbayPrepare($base,$now);$check($q['items'][0]['eligible_for_api_preparation']===true);
        $check($q['publication_active']===false);
        foreach(['zero','stale','physical','error','duplicate'] as $case){
            $b=$base;
            if($case==='zero')$b['items'][0]['live_inventory_quantity']=0;
            if($case==='stale')$b['items'][0]['inventory_checked_at']=date(DATE_ATOM,$now-301);
            if($case==='physical')$b['items'][0]['physical_stock_confirmation_at']=date(DATE_ATOM,$now-36*3600-1);
            if($case==='error')$b['inventory_errors']=1;
            if($case==='duplicate')$b['items'][0]['sku']='VP-NEMO-BIP-FARD-01';
            $check(vpEbayPrepare($b,$now)['items'][0]['eligible_for_api_preparation']===false);
        }
        $check(vpEbayPrepare($base,$now+1)['items'][0]['revision']===$q['items'][0]['revision']);
        echo "VP_EBAY_PREPARE_TESTS_OK 8 checks\n";exit;
    }
    $dir=__DIR__.'/vp_commercial';
    $report=json_decode((string)file_get_contents($dir.'/rapport.json'),true,64,JSON_THROW_ON_ERROR);
    vpEbayPrepareSave($dir,$report);
    echo "VP_EBAY_PREPARE_OK publication=off\n";
}
