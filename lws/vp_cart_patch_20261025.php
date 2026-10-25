<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
umask(0077);
$dir=__DIR__.'/vp_pneus_review';
$live=dirname(__DIR__).'/htdocs/agents.vosgespneus.com/pneus-essai.php';
$stage=dirname($live).'/pneus-validation-20261025.php';
function onceReplace(string $s,string $a,string $b):string {if(substr_count($s,$a)!==1)throw new RuntimeException('patch_anchor');return str_replace($a,$b,$s);}
$mode=$argv[1]??'';
if($mode==='--stage'){
$s=file_get_contents($live);
$endpoint=<<<'PHP'
if(($_SERVER['REQUEST_METHOD']??'')==='GET'&&($_GET['cart_status']??'')==='1'){
 header('Content-Type: application/json');
 if(!$quote){http_response_code(410);echo '{"expired":true}';exit;}
 echo json_encode(['ready'=>$checkoutUrl!=='','completed'=>($bridgeJob['state']??'')==='completed']);exit;
}
PHP;
$s=onceReplace($s,'?><!doctype html',$endpoint."
?><!doctype html");
$s=onceReplace($s,'<p class="notice">Préparation du panier en cours. Actualisez cette page dans quelques minutes.</p>','<p id="cart-progress" class="notice" role="status">Préparation automatique du panier en cours. Le lien apparaîtra ici dès que votre sélection sera prête.</p>');
$s=onceReplace($s,'<?php if($checkoutUrl): ?><a class="button"','<?php if($checkoutUrl): ?><a id="cart-checkout" class="button"');
$s=onceReplace($s,'<form method="post">','<p id="cart-dirty" class="notice" role="status" hidden>Vos modifications ne sont pas enregistrées. Enregistrez vos options avant de poursuivre vers Shopify.</p><form method="post">');
$poll=<<<'JS'
// Poll only the current authenticated selection; stop while the customer edits.
const progress=document.getElementById('cart-progress');
let dirty=false;
const form=document.querySelector('form');
const pause=()=>{
 dirty=true;
 const checkout=document.getElementById('cart-checkout');
 if(checkout){checkout.removeAttribute('href');checkout.style.display='none';}
 const notice=document.getElementById('cart-dirty');if(notice)notice.hidden=false;
 if(progress)progress.textContent='Enregistrez vos options pour préparer le panier correspondant.';
};
form?.addEventListener('input',pause);form?.addEventListener('change',pause);
if(progress){
 let attempts=0;
 async function pollCart(){
  if(dirty)return;
  try{
   const response=await fetch(location.pathname+'?cart_status=1',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
   if(response.status===410){progress.textContent='Votre sélection a expiré. Rouvrez votre lien personnel.';return;}
   if(response.ok){const state=await response.json();if((state.ready||state.completed)&&!dirty){location.replace(location.pathname);return;}}
  }catch{}
  if(++attempts<72)setTimeout(pollCart,5000);
  else progress.textContent='La préparation prend plus de temps que prévu. Actualisez la préparation ou contactez l’atelier. Aucun achat n’a été effectué.';
 }
 setTimeout(pollCart,1000);
}
JS;
$s=onceReplace($s,'openPersonalSelection();' . "
" . '</script></html>','openPersonalSelection();' . "
" . $poll . "
" . '</script></html>');
$s=str_replace('/pneus-essai.php','/pneus-validation-20261025.php',$s);
file_put_contents($stage,$s,LOCK_EX);chmod($stage,0600);echo "STAGE_READY
";
}elseif($mode==='--activate'){
$s=file_get_contents($stage);$s=str_replace('/pneus-validation-20261025.php','/pneus-essai.php',$s);
$backup=$dir.'/page.before-autopoll-20261025.php';if(!is_file($backup))copy($live,$backup);
file_put_contents($live.'.next',$s,LOCK_EX);chmod($live.'.next',0600);rename($live.'.next',$live);echo "PRIVATE_PAGE_ACTIVATED
";
}elseif($mode==='--verify'){
require $dir.'/review_lib.php';
$n=0;
foreach([13=>1500,16=>1500,17=>1800,18=>2200,19=>2200,22=>2200] as $rim=>$rate)
foreach([1,2,4] as $qty)foreach([0,1] as $fit)foreach([0,1,2] as $pairs){
$q=['quoted_quantity'=>4,'rim'=>$rim,'unit_cents'=>4603];
$t=reviewTotals($q,$qty,$fit,$pairs);
$expected=4603*$qty+($fit?($rate+550)*$qty:0)+3000*$pairs;
if($t['total']!==$expected||$t['valve_total']!==($fit?300*$qty:0)||$t['recycling_total']!==($fit?250*$qty:0))throw new RuntimeException('tariff_matrix');$n++;
}
echo 'TARIFF_MATRIX_OK cases='.$n.PHP_EOL;
$before=new PDO('sqlite:'.$dir.'/review.before-20261025.sqlite');$now=reviewDb();$rows=0;$quotes=0;
foreach($before->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $table){
if($table==='opening_limits')continue;
if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('table');
$keys=[];foreach($before->query('PRAGMA table_info('.$table.')') as $c)if($c['pk'])$keys[]=$c['name'];
if(!$keys)throw new RuntimeException('primary_key');
foreach($before->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC) as $row){
$where=implode(' AND ',array_map(fn($k)=>$k.'=?',$keys));$st=$now->prepare('SELECT * FROM '.$table.' WHERE '.$where);$st->execute(array_map(fn($k)=>$row[$k],$keys));
if($st->fetch(PDO::FETCH_ASSOC)!==$row)throw new RuntimeException('previous_record_changed_'.$table);
$rows++;if($table==='quotes')$quotes++;
}}
echo 'PREVIOUS_RECORDS_UNCHANGED rows='.$rows.' quotes='.$quotes.PHP_EOL;
}else throw new RuntimeException('mode');
