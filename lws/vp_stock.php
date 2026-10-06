<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function vpStockHttp(string $url, array $headers, string $payload): array {
    $body='';$large=false;$ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body,&$large){
            if(strlen($body)+strlen($chunk)>1048576){$large=true;return 0;}
            $body.=$chunk;return strlen($chunk);
        }]);
    $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($ok===false||$large||$status!==200)throw new RuntimeException('stock_http_'.$status);
    $data=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new RuntimeException('stock_json');
    return $data;
}
function vpStockWrite(string $path,array $data): void {
    $body=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    $tmp=$path.'.tmp.'.getmypid();
    if(file_put_contents($tmp,$body,LOCK_EX)!==strlen($body))throw new RuntimeException('stock_write');
    chmod($tmp,0600);if(!rename($tmp,$path))throw new RuntimeException('stock_rename');
}
function vpStockMap(array $nodes,array $expected): array {
    $rows=[];
    foreach($nodes as $node){
        if(!is_array($node)||!isset($expected[$node['id']??'']))throw new RuntimeException('stock_variant');
        $id=$node['id'];$sku=$expected[$id];
        if(isset($rows[$sku])||($node['sku']??null)!==$sku||($node['inventoryItem']['tracked']??false)!==true||!is_int($node['inventoryQuantity']??null))throw new RuntimeException('stock_unverified');
        $rows[$sku]=['variant_id'=>$id,'quantity'=>$node['inventoryQuantity'],'tracked'=>true];
    }
    if(count($rows)!==count($expected))throw new RuntimeException('stock_incomplete');
    return $rows;
}
function vpStockRead(string $dir,bool $force=false): array {
    $path=$dir.'/stock_live.json';
    $cache=is_file($path)?json_decode((string)file_get_contents($path),true):null;
    if(!$force&&is_array($cache)&&($cache['errors']??1)===0&&time()-(int)($cache['checked_unix']??0)<240)return $cache;
    try{
        $delay=$dir.'/stock_next_request.json';
        $next=is_file($delay)?json_decode((string)file_get_contents($delay),true):[];
        if(time()<(int)($next['not_before']??0))throw new RuntimeException('stock_deferred');
        vpStockWrite($delay,['not_before'=>time()+60]);
        $tokenPath=$dir.'/shopify_token.json';
        $token=is_file($tokenPath)?json_decode((string)file_get_contents($tokenPath),true):null;
        if(!is_array($token)||time()>=(int)($token['expires_at']??0)-300){
            $credentials=json_decode((string)file_get_contents($dir.'/shopify_credentials.json'),true,16,JSON_THROW_ON_ERROR);
            if(!is_string($credentials['client_id']??null)||!is_string($credentials['client_secret']??null))throw new RuntimeException('stock_credentials');
            $response=vpStockHttp('https://zaiwdm-st.myshopify.com/admin/oauth/access_token',
                ['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],
                http_build_query(['grant_type'=>'client_credentials','client_id'=>$credentials['client_id'],'client_secret'=>$credentials['client_secret']]));
            $scopes=explode(',',(string)($response['scope']??''));
            sort($scopes);
            if($scopes!==['read_inventory','read_products']||!is_string($response['access_token']??null)||!is_int($response['expires_in']??null))throw new RuntimeException('stock_token_scopes');
            $token=['access_token'=>$response['access_token'],'expires_at'=>time()+$response['expires_in']];
            vpStockWrite($tokenPath,$token);
        }
        $expected=[
            'gid://shopify/ProductVariant/58529701200203'=>'VP-BER-M59-PHG-9644150980',
            'gid://shopify/ProductVariant/58529706344779'=>'VP-BER-PART-FARG-RF',
            'gid://shopify/ProductVariant/58529816871243'=>'VP-NEMO-BIP-FARD-01',
            'gid://shopify/ProductVariant/58530241020235'=>'VP-DEM-M000T22471'
        ];
        $query='query CommercialInventory($ids: [ID!]!) { nodes(ids: $ids) { ... on ProductVariant { id sku inventoryQuantity inventoryItem { tracked } } } }';
        $data=vpStockHttp('https://zaiwdm-st.myshopify.com/admin/api/2026-10/graphql.json',
            ['Content-Type: application/json','Accept: application/json','X-Shopify-Access-Token: '.$token['access_token']],
            json_encode(['query'=>$query,'variables'=>['ids'=>array_keys($expected)]],JSON_THROW_ON_ERROR));
        if(!empty($data['errors'])||!is_array($data['data']['nodes']??null))throw new RuntimeException('stock_graphql');
        $rows=vpStockMap($data['data']['nodes'],$expected);
        $report=['source'=>'shopify_admin_2026-10','checked_at'=>date(DATE_ATOM),'checked_unix'=>time(),'errors'=>0,'items'=>$rows];
    }catch(Throwable $e){
        $code=$e instanceof RuntimeException?$e->getMessage():'stock_response_invalid';
        if(!preg_match('/^stock_[a-z0-9_]+$/D',$code))$code='stock_error';
        if($code==='stock_http_429')vpStockWrite($dir.'/stock_next_request.json',['not_before'=>time()+3600]);
        if($code==='stock_http_401'&&is_file($dir.'/shopify_token.json'))unlink($dir.'/shopify_token.json');
        $report=['source'=>'shopify_admin_2026-10','checked_at'=>date(DATE_ATOM),'checked_unix'=>time(),'errors'=>1,'error_code'=>$code,'items'=>[]];
    }
    vpStockWrite($path,$report);return $report;
}
function vpStockMerge(array $rows,array $stock): array {
    foreach($rows as &$row){
        $s=$stock['items'][$row['sku']??'']??null;
        $valid=($stock['errors']??1)===0&&is_array($s)&&($row['shopify_variant_id']??null)===$s['variant_id'];
        $row['live_inventory_quantity']=$valid?$s['quantity']:null;
        $row['inventory_checked_at']=$stock['checked_at'];
        $row['inventory_source']='shopify_admin_2026-10';
        $row['inventory_note']=$valid?'Quantité vendable Shopify suivie. Le stock physique reste à confirmer.':'Lecture du stock authentifié impossible.';
        $row['issues']=array_values(array_diff($row['issues']??[],['stock_shopify_indisponible','stock_shopify_epuise']));
        if(!$valid)$row['issues'][]='stock_shopify_indisponible';
        elseif($s['quantity']<=0)$row['issues'][]='stock_shopify_epuise';
        $row['publication_allowed']=false;
        $row['publication_blockers']=array_values(array_diff($row['publication_blockers']??[],['stock_en_temps_reel_non_verifie']));
        if(!$valid)$row['publication_blockers'][]='stock_en_temps_reel_non_verifie';
        elseif($s['quantity']<=0)$row['publication_blockers'][]='stock_shopify_epuise';
    }
    unset($row);return $rows;
}
if(realpath($argv[0]??'')===__FILE__){
    date_default_timezone_set('Europe/Paris');umask(0077);
    if(in_array('--self-test',$argv,true)){
        $expected=['gid://shopify/ProductVariant/1'=>'SKU'];
        $n=[['id'=>array_key_first($expected),'sku'=>'SKU','inventoryQuantity'=>1,'inventoryItem'=>['tracked'=>true]]];
        if(vpStockMap($n,$expected)['SKU']['quantity']!==1)exit(1);
        foreach(['missing','wrong_sku','untracked','null_quantity','duplicate'] as $test){
            $bad=$n;
            if($test==='missing')$bad=[];
            if($test==='wrong_sku')$bad[0]['sku']='OTHER';
            if($test==='untracked')$bad[0]['inventoryItem']['tracked']=false;
            if($test==='null_quantity')$bad[0]['inventoryQuantity']=null;
            if($test==='duplicate')$bad[]=$bad[0];
            $rejected=false;try{vpStockMap($bad,$expected);}catch(RuntimeException $e){$rejected=true;}
            if(!$rejected)exit(1);
        }
        $r=vpStockMerge([['sku'=>'SKU','shopify_variant_id'=>array_key_first($expected),'issues'=>[],'publication_blockers'=>['stock_en_temps_reel_non_verifie']]],['errors'=>0,'checked_at'=>'test','items'=>['SKU'=>['variant_id'=>array_key_first($expected),'quantity'=>0]]]);
        if($r[0]['publication_allowed']!==false||!in_array('stock_shopify_epuise',$r[0]['issues'],true))exit(1);
        $r=vpStockMerge($r,['errors'=>1,'checked_at'=>'test','items'=>[]]);
        if($r[0]['live_inventory_quantity']!==null||!in_array('stock_en_temps_reel_non_verifie',$r[0]['publication_blockers'],true))exit(1);
        echo "VP_STOCK_TESTS_OK 8 checks\n";exit(0);
    }
    $dir=__DIR__.'/vp_commercial';
    $lock=fopen($dir.'/stock.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(0);
    $r=vpStockRead($dir,in_array('--force',$argv,true));
    echo 'VP_STOCK_OK items='.count($r['items']).' errors='.$r['errors']."\n";
    if(isset($r['error_code']))echo $r['error_code']."\n";
    exit($r['errors']?1:0);
}
