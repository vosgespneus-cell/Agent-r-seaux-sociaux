<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
umask(0077);ini_set('display_errors','0');
require __DIR__.'/vp_pneus_review/review_lib.php';
function childTrial(string $path,array $args):int {
 $cmd=[PHP_BINARY];$ini=php_ini_loaded_file();if($ini)$cmd=array_merge($cmd,['-c',$ini]);
 $cmd=array_merge($cmd,['-d','extension_dir='.ini_get('extension_dir'),$path],$args);
 $p=proc_open($cmd,[0=>['file','/dev/null','r'],1=>['file','php://stdout','w'],2=>['file','php://stderr','w']],$pipes,VP_REVIEW_DIR);
 if(!is_resource($p))throw new RuntimeException('child');return proc_close($p);
}
try{
 $mode=$argv[1]??'';
 $manifest=VP_REVIEW_DIR.'/mailcart-trial-20261025.json';$db=reviewDb();
 $sender=VP_REVIEW_DIR.'/review_notify_trial_20261025.php';
 if($mode==='--prepare'){
  if(is_file($manifest)){echo "TRIAL_ALREADY_PREPARED no_new_quote=1
";exit;}
  $id=trim(file_get_contents(VP_REVIEW_DIR.'/e2e-test-id.txt'));
  $s=$db->prepare('SELECT * FROM quotes WHERE id=?');$s->execute([$id]);$q=$s->fetch(PDO::FETCH_ASSOC);
  if(!$q||$q['reference']!=='TEST-SANS-ACHAT'||!str_starts_with($q['title'],'TEST PARCOURS'))throw new RuntimeException('test_only');
  $uid=random_int(100000000,999999999);$q['id']=bin2hex(random_bytes(16));$q['source_key']='owner-test:'.time().':'.$uid;
  $q['customer_email']='vosgespneus@gmail.com';$q['created']=time();$q['expires']=time()+3600;
  $q['capability_hash']=hash('sha256',random_bytes(32));$q['quantity']=4;$q['quoted_quantity']=4;$q['fitting']=0;$q['storage_pairs']=0;$q['saved']=0;
  $db->prepare('INSERT INTO quotes ('.implode(',',array_keys($q)).') VALUES ('.implode(',',array_fill(0,count($q),'?')).')')->execute(array_values($q));
  file_put_contents($manifest,json_encode(['quote_id'=>$q['id'],'uid'=>$uid,'recipient'=>'vosgespneus@gmail.com']),LOCK_EX);chmod($manifest,0600);
  $src=file_get_contents(VP_REVIEW_DIR.'/review_notify.php');
  if(substr_count($src,'[ESSAI VP-PNEUS-20261022]')!==1)throw new RuntimeException('subject_anchor');
  $src=str_replace('[ESSAI VP-PNEUS-20261022]','[ESSAI VP-PNEUS-20261025]',$src);
  $old='Cette page est encore en essai : aucun achat, aucune réservation et aucun paiement ne sont possibles.';
  if(substr_count($src,$old)!==1)throw new RuntimeException('body_anchor');
  $src=str_replace($old,'Cette page est en essai : vérifiez le panier Shopify sans payer. Aucun achat ni réservation ne sont déclenchés ; aucun paiement réel ne doit être effectué.',$src);
  file_put_contents($sender,$src,LOCK_EX);chmod($sender,0600);echo "TRIAL_PREPARED recipient=vosgespneus@gmail.com old_quotes_untouched=1 sends=0
";exit;
 }
 if($mode==='--diagnostic'){
$src=file_get_contents($sender);
$from=<<<'FROM'
fwrite(STDERR,'NOTIFICATION_ERROR '.$code.PHP_EOL);exit(1);
FROM;
$to=<<<'TO'
fwrite(STDERR,'NOTIFICATION_ERROR '.$code.' type='.get_class($e).' line='.$e->getLine().PHP_EOL);exit(1);
TO;
if(substr_count($src,$from)!==1)throw new RuntimeException('diagnostic_anchor');
file_put_contents($sender,str_replace($from,$to,$src),LOCK_EX);echo "DIAGNOSTIC_READY sends=0
";exit;
}
if($mode==='--template-test'){exit(childTrial($sender,['--self-test']));}
 if($mode==='--status'){
$m=json_decode(file_get_contents($manifest),true);$s=$db->prepare('SELECT state FROM owner_notifications WHERE quote_id=?');$s->execute([$m['quote_id']]);
echo json_encode($db->query('PRAGMA table_info(owner_notifications)')->fetchAll(PDO::FETCH_ASSOC)).PHP_EOL;
echo json_encode(['php'=>PHP_VERSION,'mail'=>function_exists('mail'),'receipt'=>$s->fetchColumn(),'config_enabled'=>json_decode(file_get_contents(VP_REVIEW_DIR.'/notify-owner.json'),true)['enabled']??null]).PHP_EOL;exit;
}
if(!in_array($mode,['--dry-run','--send'],true))throw new RuntimeException('mode');
 $m=json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
 $s=$db->prepare('SELECT * FROM quotes WHERE id=?');$s->execute([$m['quote_id']]);$q=$s->fetch(PDO::FETCH_ASSOC);
 if(!$q||$q['reference']!=='TEST-SANS-ACHAT'||$q['customer_email']!=='vosgespneus@gmail.com'||$q['source_key']!=='owner-test:'.$q['created'].':'.$m['uid'])throw new RuntimeException('wrong_trial');
 $s->closeCursor();$s=null;$db=null;
 $lock=fopen(VP_REVIEW_DIR.'/intake-test.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('intake_busy');
 $path=VP_REVIEW_DIR.'/notify-owner.json';$original=file_get_contents($path);$config=json_decode($original,true,512,JSON_THROW_ON_ERROR);
 if(($config['enabled']??null)!==false)throw new RuntimeException('sender_busy');
 try{
  file_put_contents($path,json_encode(['owner_email'=>'vosgespneus@gmail.com','owner_test_uid'=>$m['uid'],'enabled'=>$mode==='--send']),LOCK_EX);
  $exit=childTrial($sender,[$mode==='--send'?'--send-owner-test':'--dry-run-owner',(string)$m['uid']]);
 }finally{file_put_contents($path,$original,LOCK_EX);flock($lock,LOCK_UN);fclose($lock);}
 exit($exit);
}catch(Throwable $e){fwrite(STDERR,'TRIAL_ERROR '.get_class($e).' '.(preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'failed').PHP_EOL);exit(1);}
