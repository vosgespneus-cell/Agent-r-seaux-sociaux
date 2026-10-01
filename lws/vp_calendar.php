<?php
declare(strict_types=1);
ini_set('display_errors','0');umask(0077);
function cal_db(): PDO {$c=require __DIR__.'/vp_config.php';return new PDO($c['db_dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);}
function cal_schema(PDO $db): void {$db->exec("CREATE TABLE IF NOT EXISTS vp_calendar_jobs (job_id CHAR(64) CHARACTER SET ascii PRIMARY KEY, source_event_id VARCHAR(128) CHARACTER SET ascii UNIQUE NOT NULL, order_ref VARCHAR(64), payload MEDIUMTEXT NOT NULL, state VARCHAR(16) NOT NULL, google_event_id VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");}
function cal_date($s): ?DateTimeImmutable {if(!is_string($s))return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s',$s,new DateTimeZone('Europe/Paris'));return $d&&$d->format('Y-m-d\\TH:i:s')===$s?$d:null;}
function cal_prepare(array $r, string $source, array $mail): ?array {
 if(($r['category']??'')!=='montage')return null;
 $start=cal_date($r['start_local']??null);$end=cal_date($r['end_local']??null);$ref=(string)($r['order_ref']??'');
 $state='review';$from=(string)($mail['from']??'');$sender=strtolower(trim($from));if(preg_match('/<([^<>]+)>/',$from,$m))$sender=strtolower(trim($m[1]));
 if(($r['needs_review']??true)===false&&$sender==='noreply@allopneus.com'&&preg_match('/^[0-9]{6,20}$/D',$ref)&&$start&&$end&&$start>new DateTimeImmutable()&&$end>$start&&$end->getTimestamp()-$start->getTimestamp()<=14400)$state='ready';
 $id=hash('sha256',($ref!==''?$ref:$source).'|'.($r['start_local']??''));
 return ['id'=>$id,'state'=>$state,'order_ref'=>$ref,'payload'=>['job_id'=>$id,'calendar_id'=>'vosgespneus@gmail.com','summary'=>'Montage Allopneus'.($ref!==''?' — '.$ref:''),'start'=>$start?$start->format(DateTimeInterface::RFC3339):null,'end'=>$end?$end->format(DateTimeInterface::RFC3339):null,'description'=>'Rendez-vous extrait du mail Allopneus. Commande : '.$ref,'location'=>'VOSGES PNEUS, 6 P rue des Aviots, 88150 Thaon-les-Vosges']];
}
try {
 if(PHP_SAPI==='cli'){
  if(in_array('--selftest',$argv,true)){
   $r=['category'=>'montage','start_local'=>'2099-10-07T08:00:00','end_local'=>null,'order_ref'=>'12345678','needs_review'=>true];$mail=['from'=>'Allopneus <noreply@allopneus.com>'];$a=cal_prepare($r,'fixture',$mail);if($a['state']!=='review')throw new Exception('MISSING_END');$r['end_local']='2099-10-07T08:30:00';$r['needs_review']=false;$b=cal_prepare($r,'fixture',$mail);if($b['state']!=='ready'||$a['id']!==$b['id'])throw new Exception('IDEMPOTENCY');if(cal_prepare($r,'fixture',['from'=>'unknown@example.com'])['state']!=='review')throw new Exception('SENDER');$r['category']='livraison';if(cal_prepare($r,'fixture',$mail)!==null)throw new Exception('DELIVERY');if(cal_date('2026-02-30T08:00:00')!==null)throw new Exception('DATE');echo 'CAL_SELFTEST_OK missing_end dates sender delivery idempotency'.PHP_EOL;exit;
  }
  $db=cal_db();cal_schema($db);$config=require __DIR__.'/vp_calendar_config.php';
  $rows=$db->query("SELECT e.event_id,e.body,a.result_json FROM vp_ai_mail_results a JOIN vp_events e ON e.event_id=a.event_id LEFT JOIN vp_calendar_jobs j ON j.source_event_id=e.event_id WHERE j.source_event_id IS NULL ORDER BY e.received_at LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
  $q=$db->prepare('INSERT IGNORE INTO vp_calendar_jobs(job_id,source_event_id,order_ref,payload,state,google_event_id) VALUES(?,?,?,?,?,?)');$count=0;
  foreach($rows as $row){$r=json_decode($row['result_json'],true,512,JSON_THROW_ON_ERROR);$mail=json_decode($row['body'],true,512,JSON_THROW_ON_ERROR);$job=cal_prepare($r,$row['event_id'],$mail);if(!$job)continue;$gid=null;
   // Verified existing event from the earlier manual import: never create a second one.
   if(isset($config['existing_events'][$row['event_id']])){$job['state']='linked';$gid=$config['existing_events'][$row['event_id']];}
   $q->execute([$job['id'],$row['event_id'],$job['order_ref'],json_encode($job['payload'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$job['state'],$gid]);$count+=$q->rowCount();
  }
  foreach($db->query('SELECT state,COUNT(*) AS n FROM vp_calendar_jobs GROUP BY state') as $s)echo 'CAL_JOBS '.$s['state'].'='.$s['n'].PHP_EOL;echo 'CAL_PREPARE_OK added='.$count.PHP_EOL;exit;
 }
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('{"error":"method"}');}
 $cfg=require __DIR__.'/vp_calendar_config.php';if(empty($cfg['enabled'])){http_response_code(503);exit('{"error":"not_connected"}');}
 $raw=file_get_contents('php://input',false,null,0,32769);$ts=(string)($_SERVER['HTTP_X_VP_TIMESTAMP']??'');$sig=(string)($_SERVER['HTTP_X_VP_SIGNATURE']??'');$secret=(string)($cfg['bridge_secret']??'');
 if($raw===false||strlen($raw)>32768||$secret===''||!ctype_digit($ts)||abs(time()-(int)$ts)>300||!hash_equals(hash_hmac('sha256',$ts.'.'.$raw,$secret),$sig)){http_response_code(403);exit('{"error":"forbidden"}');}
 $req=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$db=cal_db();cal_schema($db);
 if(($req['action']??'')==='pull'){$jobs=$db->query("SELECT payload FROM vp_calendar_jobs WHERE state='ready' ORDER BY updated_at LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);echo json_encode(['jobs'=>array_map(fn($s)=>json_decode($s,true),$jobs)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
 if(($req['action']??'')==='ack'&&preg_match('/^[a-f0-9]{64}$/D',(string)($req['job_id']??''))&&is_string($req['event_id']??null)&&strlen($req['event_id'])<=255){$q=$db->prepare("UPDATE vp_calendar_jobs SET state='sent',google_event_id=? WHERE job_id=? AND state='ready'");$q->execute([$req['event_id'],$req['job_id']]);echo '{"ok":true}';exit;}
 http_response_code(400);echo '{"error":"request"}';
}catch(Throwable $e){if(PHP_SAPI==='cli'){fwrite(STDERR,'CAL_ERROR type='.get_class($e).' line='.$e->getLine().PHP_EOL);exit(1);}http_response_code(500);echo '{"error":"temporary"}';}
