<?php
declare(strict_types=1);
// Private CLI bridge. Test recipients only; no invoices, payments or supplier orders.
ini_set('display_errors','0');error_reporting(0);umask(0077);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/review_lib.php';
const VP_BRIDGE_CONNECTION='/home/vp_pneus_connection';
function bridgeJson(string $path): array {
    if(!is_file($path))throw new RuntimeException('configuration_missing');
    $v=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($v))throw new RuntimeException('configuration_invalid');return $v;
}
function bridgeWrite(string $path,array $v): void {
    $tmp=$path.'.tmp.'.bin2hex(random_bytes(8));
    if(file_put_contents($tmp,json_encode($v,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('storage_failed');
    chmod($tmp,0600);if(!rename($tmp,$path))throw new RuntimeException('storage_failed');
}
function bridgeHttp(string $url,string $body,array $headers): array {
    if(!str_starts_with($url,'https://zaiwdm-st.myshopify.com/'))throw new RuntimeException('unexpected_shop');
    $response='';$ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
      CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,
      CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>25,
      CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
      CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$response){
        if(strlen($response)+strlen($chunk)>1048576)return 0;
        $response.=$chunk;return strlen($chunk);
      }]);
    $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($ok===false||$status!==200)throw new RuntimeException('shopify_request_failed');
    $r=json_decode($response,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($r))throw new RuntimeException('shopify_response_invalid');return $r;
}
function bridgeConnectionDir(): string {return dirname(VP_REVIEW_DIR).'/vp_pneus_connection';}
function bridgeToken(): string {
    $d=bridgeConnectionDir();$c=bridgeJson($d.'/credentials.json');
    if(($c['enabled']??false)!==true||($c['shop']??'')!=='zaiwdm-st.myshopify.com')throw new RuntimeException('connection_disabled');
    $t=is_file($d.'/token.json')?bridgeJson($d.'/token.json'):[];
    if(($t['expires_at']??0)<time()+300){
        $r=bridgeHttp('https://zaiwdm-st.myshopify.com/admin/oauth/access_token',http_build_query([
          'grant_type'=>'client_credentials','client_id'=>$c['client_id'],'client_secret'=>$c['client_secret']]),
          ['Content-Type: application/x-www-form-urlencoded','Accept: application/json']);
        if(!is_string($r['access_token']??null)||!is_int($r['expires_in']??null)||$r['expires_in']<600||$r['expires_in']>86400)throw new RuntimeException('token_response_invalid');
        $t=['access_token'=>$r['access_token'],'scope'=>$r['scope']??'','expires_at'=>time()+$r['expires_in']];
        bridgeScopes((string)$t['scope']);bridgeWrite($d.'/token.json',$t);
    }
    bridgeScopes((string)($t['scope']??''));
    if(!is_string($t['access_token']??null)||strlen($t['access_token'])<20)throw new RuntimeException('token_missing');
    return $t['access_token'];
}
function bridgeScopes(string $scope): void {
    $s=explode(',',$scope);
    if(!in_array('write_draft_orders',$s,true)||!in_array('read_orders',$s,true)||array_diff($s,['read_orders','read_draft_orders','write_draft_orders']))throw new RuntimeException('unexpected_permissions');
}
function bridgeGraph(string $query,array $variables): array {
    $r=bridgeHttp('https://zaiwdm-st.myshopify.com/admin/api/2026-10/graphql.json',
      json_encode(['query'=>$query,'variables'=>$variables],JSON_THROW_ON_ERROR),
      ['Content-Type: application/json','X-Shopify-Access-Token: '.bridgeToken()]);
    if(!empty($r['errors'])||!is_array($r['data']??null))throw new RuntimeException('shopify_graphql_error');return $r['data'];
}
function bridgeMoney(int $n): string {
    if($n<0)throw new RuntimeException('invalid_money');return intdiv($n,100).'.'.str_pad((string)($n%100),2,'0',STR_PAD_LEFT);
}
function bridgeInput(array $q): array {
    if(!preg_match('/^[a-f0-9]{32}$/D',$q['id']??''))throw new RuntimeException('invalid_quote');
    $t=reviewTotals($q,$q['quantity'],$q['fitting'],$q['storage_pairs']);
    $items=[];$add=static function(string $title,int $qty,int $unit)use(&$items){$items[]=[
      'title'=>$title,'quantity'=>$qty,'originalUnitPriceWithCurrency'=>['amount'=>bridgeMoney($unit),'currencyCode'=>'EUR'],
      'taxable'=>true,'requiresShipping'=>false];};
    $add('ESSAI PRIVÉ — '.$q['title'],$t['quantity'],(int)$q['unit_cents']);
    if($t['fitting']){
        $add('Montage et équilibrage',$t['quantity'],$t['fitting_rate']);
        $add('Valve standard',$t['quantity'],300);$add('Recyclage pneus usagés',$t['quantity'],250);
    }
    if($t['storage_pairs'])$add('Gardiennage par paire — durée à confirmer',$t['storage_pairs'],3000);
    return ['lineItems'=>$items,'shippingAddress'=>['countryCode'=>'FR'],'presentmentCurrencyCode'=>'EUR',
      'taxExempt'=>false,'acceptAutomaticDiscounts'=>false,'allowDiscountCodesInCheckout'=>false,
      'tags'=>['VP_TEST_PNEUS','NE_PAS_TRAITER','VPQ_'.$q['id']],
      'customAttributes'=>[['key'=>'vp_quote_id','value'=>$q['id']]],
      'note'=>'ESSAI PRIVÉ. Ne pas traiter ni encaisser. Stock, frais et livraison/retrait non validés. Aucun envoi de facture.'];
}
function bridgeMatches(array $d,int $expected): bool {
    $m=$d['totalPriceSet']['shopMoney']??[];
    return ($m['currencyCode']??'')==='EUR'&&is_numeric($m['amount']??null)&&abs((float)$m['amount']-$expected/100)<0.000001;
}
const BRIDGE_FIND='query BridgeFind($search: String!) { draftOrders(first:10,query:$search) { nodes { id name status totalPriceSet { shopMoney { amount currencyCode } } order { id name test displayFinancialStatus } } pageInfo { hasNextPage endCursor } } }';
const BRIDGE_CALC='mutation BridgeCalculate($input:DraftOrderInput!) { draftOrderCalculate(input:$input) { calculatedDraftOrder { taxesIncluded totalPriceSet { shopMoney { amount currencyCode } } } userErrors { field message } } }';
const BRIDGE_CREATE='mutation BridgeCreate($input:DraftOrderInput!) { draftOrderCreate(input:$input) { draftOrder { id name status totalPriceSet { shopMoney { amount currencyCode } } } userErrors { field message } } }';
try{
    if(($argv[1]??'')==='--self-test'){
        $q=['id'=>str_repeat('a',32),'title'=>'Pneu fictif','quantity'=>4,'quoted_quantity'=>4,'rim'=>16,'unit_cents'=>4603,'fitting'=>1,'storage_pairs'=>2];
        $i=bridgeInput($q);$sum=0;foreach($i['lineItems'] as $l)$sum+=(int)round((float)$l['originalUnitPriceWithCurrency']['amount']*100)*$l['quantity'];
        if($sum!==32612||count($i['lineItems'])!==5)throw new RuntimeException('pricing_test_failed');
        if(strlen($i['tags'][2])>40)throw new RuntimeException('tag_test_failed');
        if(!bridgeMatches(['totalPriceSet'=>['shopMoney'=>['amount'=>'326.12','currencyCode'=>'EUR']]],32612))throw new RuntimeException('total_test_failed');
        if(bridgeMatches(['totalPriceSet'=>['shopMoney'=>['amount'=>'326.13','currencyCode'=>'EUR']]],32612))throw new RuntimeException('mismatch_test_failed');
        if(isset($i['email'])||isset($i['customerId']))throw new RuntimeException('identity_test_failed');
        bridgeScopes('read_orders,write_draft_orders');
        try{bridgeScopes('read_orders,write_draft_orders,write_products');throw new LogicException();}catch(RuntimeException $expected){}
        echo "BRIDGE_SELF_TEST_OK cases=7 payment_enabled=0\n";exit;
    }
    if(($argv[1]??'')!=='--sync-tests')throw new RuntimeException('mode_required');
    $cfg=bridgeJson(VP_REVIEW_DIR.'/bridge-tests.json');
    if(($cfg['enabled']??false)!==true)throw new RuntimeException('bridge_disabled');
    $allowed=$cfg['allowed_recipients']??[];
    if(!is_array($allowed)||!$allowed||count($allowed)>2)throw new RuntimeException('invalid_allowlist');
    foreach($allowed as $e)if(!is_string($e)||!filter_var($e,FILTER_VALIDATE_EMAIL))throw new RuntimeException('invalid_allowlist');
    $lock=fopen(VP_REVIEW_DIR.'/shopify-bridge.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('bridge_busy');
    bridgeToken();$db=reviewDb();
    $db->exec('CREATE TABLE IF NOT EXISTS shopify_bridge_jobs(quote_id TEXT PRIMARY KEY, fingerprint TEXT NOT NULL, state TEXT NOT NULL, draft_id TEXT, draft_name TEXT, order_name TEXT, updated INTEGER NOT NULL)');
    $rows=$db->query('SELECT * FROM quotes WHERE saved=1 ORDER BY created DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
    $summary=['saved'=>0,'created'=>0,'reconciled'=>0,'unchanged'=>0,'held'=>0,'errors'=>0];
    foreach($rows as $q){
        if(!in_array(strtolower($q['customer_email']),array_map('strtolower',$allowed),true))continue;
        if(!preg_match('/^owner-test:[0-9]+:[0-9]+$/D',$q['source_key']))continue;
        $summary['saved']++;
        try{
            $input=bridgeInput($q);$t=reviewTotals($q,$q['quantity'],$q['fitting'],$q['storage_pairs']);
            $fingerprint=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));
            $job=$db->prepare('SELECT * FROM shopify_bridge_jobs WHERE quote_id=?');$job->execute([$q['id']]);$job=$job->fetch(PDO::FETCH_ASSOC);
            if($job&&$job['fingerprint']!==$fingerprint){$summary['held']++;continue;}
            $remote=bridgeGraph(BRIDGE_FIND,['search'=>'tag:VPQ_'.$q['id']])['draftOrders'];
            if($remote['pageInfo']['hasNextPage']||count($remote['nodes'])>1)throw new RuntimeException('ambiguous_draft');
            $draft=$remote['nodes'][0]??null;
            if($draft){
                if(!bridgeMatches($draft,$t['total']))throw new RuntimeException('draft_total_mismatch');
                $state=($draft['status']==='COMPLETED')?'completed':'private_draft_stock_pending';
                if($state==='completed'&&($draft['order']['test']??false)!==true)throw new RuntimeException('non_test_order_requires_review');
                if($job&&$job['state']===$state){$summary['unchanged']++;continue;}
                $db->prepare('INSERT INTO shopify_bridge_jobs VALUES(?,?,?,?,?,?,?) ON CONFLICT(quote_id) DO UPDATE SET state=excluded.state,draft_id=excluded.draft_id,draft_name=excluded.draft_name,order_name=excluded.order_name,updated=excluded.updated')->execute([
                  $q['id'],$fingerprint,$state,$draft['id'],$draft['name'],$draft['order']['name']??null,time()]);
                $summary['reconciled']++;continue;
            }
            // Uncertain attempts never create again; expired quotes cannot create a new draft.
            if($job||$q['expires']<=time()){$summary['held']++;continue;}
            $calc=bridgeGraph(BRIDGE_CALC,['input'=>$input])['draftOrderCalculate'];
            if($calc['userErrors']||($calc['calculatedDraftOrder']['taxesIncluded']??false)!==true||!bridgeMatches($calc['calculatedDraftOrder'],$t['total']))throw new RuntimeException('calculation_mismatch');
            $db->prepare('INSERT INTO shopify_bridge_jobs VALUES(?,?,?,?,?,?,?)')->execute([$q['id'],$fingerprint,'attempt_started',null,null,null,time()]);
            $result=bridgeGraph(BRIDGE_CREATE,['input'=>$input])['draftOrderCreate'];
            if($result['userErrors']||!is_array($result['draftOrder'])||!bridgeMatches($result['draftOrder'],$t['total']))throw new RuntimeException('creation_requires_review');
            $d=$result['draftOrder'];$db->prepare('UPDATE shopify_bridge_jobs SET state=?,draft_id=?,draft_name=?,updated=? WHERE quote_id=?')->execute(['private_draft_stock_pending',$d['id'],$d['name'],time(),$q['id']]);$summary['created']++;
        }catch(Throwable $e){$summary['errors']++;}
    }
    echo 'BRIDGE_RESULT '.json_encode($summary).' payment_enabled=0 invoices_sent=0'.PHP_EOL;
    if($summary['errors'])exit(1);
}catch(Throwable $e){$code=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'bridge_failed';fwrite(STDERR,'BRIDGE_ERROR '.$code.PHP_EOL);exit(1);}
