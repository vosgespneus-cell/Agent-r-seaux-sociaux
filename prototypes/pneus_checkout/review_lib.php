<?php
declare(strict_types=1);
// Private owner-test workflow. No mail sending, supplier call or Shopify write.
const VP_REVIEW_DIR=__DIR__; // Same private directory in web and LWS cron namespaces.
function reviewDb(): PDO {
    umask(0077);
    if(!is_dir(VP_REVIEW_DIR))throw new RuntimeException('review_not_initialized');
    $p=new PDO('sqlite:'.VP_REVIEW_DIR.'/review.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $p->exec('PRAGMA foreign_keys=ON');$p->exec('PRAGMA busy_timeout=3000');
    return $p;
}
function reviewInit(): PDO {
    umask(0077);
    if(!is_dir(VP_REVIEW_DIR))mkdir(VP_REVIEW_DIR,0700);
    if(!is_dir(VP_REVIEW_DIR.'/sessions'))mkdir(VP_REVIEW_DIR.'/sessions',0700);
    $p=reviewDb();
    $p->exec('CREATE TABLE IF NOT EXISTS quotes(
      id TEXT PRIMARY KEY, source_key TEXT UNIQUE NOT NULL, title TEXT NOT NULL,
      reference TEXT NOT NULL, customer_email TEXT NOT NULL,
      quoted_quantity INTEGER NOT NULL, unit_cents INTEGER NOT NULL,
      rim INTEGER NOT NULL, created INTEGER NOT NULL, expires INTEGER NOT NULL,
      capability_hash TEXT NOT NULL, quantity INTEGER NOT NULL,
      fitting INTEGER NOT NULL DEFAULT 0, storage_pairs INTEGER NOT NULL DEFAULT 0,
      saved INTEGER NOT NULL DEFAULT 0)');
    $p->exec('CREATE TABLE IF NOT EXISTS opening_limits(bucket TEXT PRIMARY KEY, count INTEGER NOT NULL)');
    chmod(VP_REVIEW_DIR.'/review.sqlite',0600);
    return $p;
}
function reviewCustomerEmail(string $html): string {
    $dom=new DOMDocument();$before=libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($before);$emails=[];
    foreach($dom->getElementsByTagName('tr') as $tr){
        $cells=[];foreach($tr->childNodes as $c){
            if(in_array($c->nodeName,['td','th'],true))$cells[]=trim(preg_replace('/\s+/u',' ',$c->textContent)??'');
        }
        if(count($cells)===2&&$cells[0]==='Courriel:'){
            if(!filter_var($cells[1],FILTER_VALIDATE_EMAIL)||strlen($cells[1])>254)throw new RuntimeException('invalid_customer_email');
            $emails[]=$cells[1];
        }
    }
    if(count($emails)!==1)throw new RuntimeException('single_customer_required');
    return $emails[0];
}
function reviewInteger($value,int $min,int $max): int {
    if(is_int($value))$value=(string)$value;
    if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,2})$/D',$value))throw new RuntimeException('invalid_options');
    $n=(int)$value;if($n<$min||$n>$max)throw new RuntimeException('invalid_options');return $n;
}
function reviewTotals(array $quote,$quantity,$fitting,$pairs): array {
    $q=reviewInteger($quantity,1,min(24,(int)$quote['quoted_quantity']));
    $f=reviewInteger($fitting,0,1);$s=reviewInteger($pairs,0,12);
    $rim=(int)$quote['rim'];
    if($f&&$rim<13)throw new RuntimeException('missing_fitting_tariff');
    // Owner-confirmed fitting supplements, EUR/TTC per tyre. Storage term pending.
    $rate=$rim<=16?1500:($rim<=18?1800:2200);
    $tyres=(int)$quote['unit_cents']*$q;$fit=$f?$rate*$q:0;$storage=$s*3000;
    $valves=$f?300*$q:0;$recycling=$f?250*$q:0;
    return ['quantity'=>$q,'fitting'=>$f,'storage_pairs'=>$s,'tyres'=>$tyres,
        'fitting_total'=>$fit,'valve_total'=>$valves,'recycling_total'=>$recycling,
        'storage_total'=>$storage,'total'=>$tyres+$fit+$valves+$recycling+$storage,
        'fitting_rate'=>$rate,'fitting_bundle_rate'=>$rate+550];
}
function reviewMoney(int $cents): string {
    return number_format(intdiv($cents,100),0,',',' ').','.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT).' €';
}
function reviewH($v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
