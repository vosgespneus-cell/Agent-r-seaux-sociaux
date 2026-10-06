<?php
declare(strict_types=1);
ini_set('display_errors','0');umask(0077);
// Private LWS agent. Customer messages are drafts only; alerts go to the owner.
function lead_db(): PDO {$c=require __DIR__.'/vp_config.php';return new PDO($c['db_dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);}
function lead_schema(PDO $d): void {
 $d->exec("CREATE TABLE IF NOT EXISTS vp_leads (event_id VARCHAR(128) CHARACTER SET ascii PRIMARY KEY,payload MEDIUMTEXT NOT NULL,state VARCHAR(20) NOT NULL DEFAULT 'new',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB");
 $d->exec("CREATE TABLE IF NOT EXISTS vp_lead_alerts (alert_id CHAR(64) CHARACTER SET ascii PRIMARY KEY,state VARCHAR(20) NOT NULL,event_ids TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB");
}
function lead_text($v): string {if(is_array($v))$v=implode(' ',array_filter($v,'is_scalar'));return trim(preg_replace('/[\x00-\x1F\x7F]/u',' ',(string)$v)??'');}
function lead_prepare(array $b): ?array {
 if(($b['form_id']??'')!=='262624697144060'||!is_array($b['answers']??null))return null;
 $a=$b['answers'];$get=fn($id)=>lead_text($a[$id]['answer']??'');
 $name=$get(3);$comment=$get(24);
 if($name==='Test Automatisation'&&str_contains($comment,'TEST TECHNIQUE'))return null;
 $size=$get(28);if($size==='Autre dimension')$size=$get(29);
 $diameter=null;if(preg_match('/\bR\s*(\d{2})\b/i',$size,$m))$diameter=(int)$m[1];
 $mount=$diameter!==null&&$diameter>=13?($diameter<=15?1500:($diameter<=17?1800:2200)):null;
 $phone=preg_replace('/\D/','',$get(4));$quantity=$get(15);$exact=ctype_digit($quantity)&&(int)$quantity>=1&&(int)$quantity<=20?(int)$quantity:null;
 $service=$get(21);$fitting=$service==='Pneus + montage';
 $missing=['prix achat TTC Allopneus','disponibilité et délai','frais de livraison'];
 if($get(12)===''||$get(13)==='')$missing[]='indices de charge et de vitesse';
 if($exact===null)$missing[]='quantité exacte (4+ déclaré)';
 if($diameter===null||$mount===null)$missing[]='dimension valide';
 $draft='Bonjour '.$name.', nous avons bien reçu votre demande de '.$quantity.' pneu(s) '.$size.' '.$get(14).'. Nous vérifions les références, la disponibilité et le prix avec'.($fitting?' montage et équilibrage':' fourniture seule').'. Pour établir une proposition précise, merci de confirmer les indices de charge et de vitesse'.($exact===null?' ainsi que la quantité exacte':'').'.';
 return ['name'=>$name,'phone'=>$phone,'email'=>$get(6),'postal_code'=>$get(5),'size'=>$size,'season'=>$get(14),'quantity_declared'=>$quantity,'quantity'=>$exact,'service'=>$service,'budget'=>$get(25),'comment'=>$comment,'margin_cents_per_tyre'=>500,'fitting_cents_per_tyre'=>$fitting?$mount:0,'missing'=>$missing,'customer_draft'=>$draft,'customer_message_sent'=>false,'source_created_at'=>$b['created_at']??null,'submission_url'=>'https://form.jotform.com/inbox/262624697144060/'.rawurlencode((string)($b['submission_id']??''))];
}
function lead_total(int $purchase,int $quantity,int $diameter,bool $fitting,int $shipping): int {
 if($purchase<=0||$quantity<1||$quantity>20||$diameter<13||$diameter>30||$shipping<0)throw new InvalidArgumentException('PRICE_INPUT');
 $mount=$fitting?($diameter<=15?1500:($diameter<=17?1800:2200)):0;
 return ($purchase+500+$mount)*$quantity+$shipping;
}
function lead_ingest(PDO $d): int {
 $q=$d->prepare('SELECT e.event_id,e.body FROM vp_events e LEFT JOIN vp_leads l ON l.event_id=e.event_id WHERE e.event_id LIKE ? AND l.event_id IS NULL ORDER BY e.received_at LIMIT 100');$q->execute(['JF-%']);
 $insert=$d->prepare('INSERT IGNORE INTO vp_leads(event_id,payload,state) VALUES(?,?,?)');$n=0;
 foreach($q as $r){$b=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);$p=lead_prepare($b);$insert->execute([$r['event_id'],json_encode($p??['excluded'=>'technical_test_or_other_form'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$p?'new':'excluded']);$n+=$p?$insert->rowCount():0;}
 return $n;
}
function lead_pending(PDO $d): array {
 // A task closed elsewhere also stops reminders. The worker's 'nouveau' remains actionable.
 return $d->query("SELECT l.event_id,l.payload,l.state FROM vp_leads l LEFT JOIN vp_tasks t ON t.event_id=l.event_id WHERE l.state IN ('new','contacted','quoted') AND (t.status IS NULL OR t.status NOT IN ('done','closed','completed','termine','terminé','traite','traité','annule','annulé')) ORDER BY l.created_at,l.event_id")->fetchAll(PDO::FETCH_ASSOC);
}
function lead_alert(PDO $d,array $cfg,callable $send): string {
 $rows=lead_pending($d);if(!$rows)return 'NO_PENDING';
 $known=[];foreach($d->query("SELECT event_ids FROM vp_lead_alerts WHERE state IN ('accepted','sending','uncertain')") as $r)foreach(json_decode($r['event_ids'],true) as $id)$known[$id]=true;
 $fresh=array_values(array_filter($rows,fn($r)=>!isset($known[$r['event_id']])));
 $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Paris')))->format('Y-m-d');
 if(!$fresh){$last=$d->query("SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM vp_lead_alerts WHERE state IN ('accepted','sending','uncertain')")->fetchColumn();if($last&&(new DateTimeImmutable('@'.(int)$last))->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d')===$today)return 'ALREADY_ALERTED_TODAY';}
 $ids=array_column($rows,'event_id');sort($ids);
 $key=hash('sha256',($fresh?'new:'.implode(',',array_column($fresh,'event_id')):'reminder:'.$today));
 $get=$d->prepare('SELECT state,TIMESTAMPDIFF(SECOND,updated_at,NOW()) AS age FROM vp_lead_alerts WHERE alert_id=?');$get->execute([$key]);$previous=$get->fetch(PDO::FETCH_ASSOC);
 if($previous&&($previous['state']!=='failed'||(int)$previous['age']<900))return 'ALREADY_RECORDED';
 if(!$fresh&&(int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Paris')))->format('G')<9)return 'BEFORE_REMINDER';
 $body="VOSGES PNEUS — demandes à traiter\n\n";
 foreach($rows as $r){$p=json_decode($r['payload'],true);$body.=$p['name'].' | '.$p['size'].' | '.$p['season'].' | quantité '.$p['quantity_declared']."\nTéléphone : ".$p['phone']."\n".$p['submission_url']."\nÉtat : ".$r['state']."\n\n";}
 $body.="Marge : 5 EUR/pneu. Montage/équilibrage : 13-15 pouces 15 EUR ; 16-17 pouces 18 EUR ; au-delà 22 EUR.\nAucun prix fournisseur n'est inventé. Aucun message client n'a été envoyé par cet agent.\nPour arrêter le rappel d'une demande traitée : vp_php vp_leads.php --close JF-identifiant\n";
 $d->prepare("INSERT INTO vp_lead_alerts(alert_id,state,event_ids) VALUES(?,'sending',?) ON DUPLICATE KEY UPDATE state='sending',updated_at=NOW()")->execute([$key,json_encode($ids)]);
 // 'sending' is persisted before external mail: a crash never blindly resends.
 try {$ok=$send($cfg['owner_email'],'VOSGES PNEUS : '.count($rows).' demande(s) de pneus à traiter',$body,$cfg['sender_email']);$state=$ok?'accepted':'failed';}
 catch(Throwable $e){$state='uncertain';}
 $d->prepare('UPDATE vp_lead_alerts SET state=?,updated_at=NOW() WHERE alert_id=?')->execute([$state,$key]);return strtoupper($state);
}
function lead_test(): void {
 $b=['form_id'=>'262624697144060','answers'=>[3=>['answer'=>['first'=>'Client','last'=>'Test']],28=>['answer'=>'225/45 R17'],15=>['answer'=>'2'],21=>['answer'=>'Pneus + montage']]];
 $p=lead_prepare($b);if($p['margin_cents_per_tyre']!==500||$p['fitting_cents_per_tyre']!==1800||$p['quantity']!==2||$p['customer_message_sent']!==false)throw new Exception('PREPARE');
 if(lead_total(7000,2,17,true,0)!==18600||lead_total(7000,4,16,true,900)!==38100||lead_total(7000,2,20,true,0)!==19400)throw new Exception('PRICES');
 $b['answers'][15]['answer']='4+';if(lead_prepare($b)['quantity']!==null)throw new Exception('QUANTITY');
 $b['answers'][3]['answer']='Test Automatisation';$b['answers'][24]['answer']='TEST TECHNIQUE — aucune commande client';if(lead_prepare($b)!==null)throw new Exception('TEST_FILTER');
 echo "LEAD_SELFTEST_OK tariffs quantity drafts test_filter\n";
}
try {
 if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
 if(in_array('--selftest',$argv,true)){lead_test();exit;}
 if(in_array('--selftest-db',$argv,true)){
  $d=lead_db();foreach([
   "CREATE TEMPORARY TABLE vp_events(event_id VARCHAR(128) PRIMARY KEY,body TEXT,received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
   "CREATE TEMPORARY TABLE vp_tasks(event_id VARCHAR(128),status VARCHAR(32))",
   "CREATE TEMPORARY TABLE vp_leads(event_id VARCHAR(128) PRIMARY KEY,payload TEXT,state VARCHAR(20),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
   "CREATE TEMPORARY TABLE vp_lead_alerts(alert_id CHAR(64) PRIMARY KEY,state VARCHAR(20),event_ids TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"
  ] as $sql)$d->exec($sql);
  $b=['form_id'=>'262624697144060','answers'=>[3=>['answer'=>'Fixture client'],28=>['answer'=>'225/45 R17'],15=>['answer'=>'2'],21=>['answer'=>'Pneus + montage']]];
  $d->prepare('INSERT INTO vp_events(event_id,body) VALUES(?,?)')->execute(['JF-1',json_encode($b)]);
  if(lead_ingest($d)!==1||lead_ingest($d)!==0||count(lead_pending($d))!==1)throw new Exception('DEDUP');
  $calls=0;$send=function()use(&$calls){$calls++;return true;};$cfg=['owner_email'=>'fixture@example.com','sender_email'=>'fixture@example.com'];
  if(lead_alert($d,$cfg,$send)!=='ACCEPTED'||lead_alert($d,$cfg,$send)!=='ALREADY_ALERTED_TODAY'||$calls!==1)throw new Exception('ALERT_DEDUP');
  $d->exec("UPDATE vp_leads SET state='closed'");if(lead_alert($d,$cfg,$send)!=='NO_PENDING'||$calls!==1)throw new Exception('CLOSE');
  echo "LEAD_DATABASE_SELFTEST_OK ingest duplicate alert_once close no_external_calls\n";exit;
 }
 $lock=fopen(__DIR__.'/vp_leads.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo "LEAD_LOCKED\n";exit;}
 $d=lead_db();lead_schema($d);
 if(($argv[1]??'')==='--close'){$id=$argv[2]??'';if(!preg_match('/^JF-\d{1,32}$/D',$id))throw new InvalidArgumentException('EVENT_ID');$q=$d->prepare("UPDATE vp_leads SET state='closed' WHERE event_id=? AND state<>'excluded'");$q->execute([$id]);echo 'LEAD_CLOSED count='.$q->rowCount()."\n";exit;}
 $added=lead_ingest($d);$rows=lead_pending($d);
 if(in_array('--preview',$argv,true)){echo json_encode(array_map(fn($r)=>['event_id'=>$r['event_id'],'state'=>$r['state'],'request'=>json_decode($r['payload'],true)],$rows),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";exit;}
 $cfg=require __DIR__.'/vp_leads_config.php';
 if(!filter_var($cfg['owner_email']??'',FILTER_VALIDATE_EMAIL)||!filter_var($cfg['sender_email']??'',FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('MAIL_CONFIG');
 $state=empty($cfg['alerts_enabled'])?'DISABLED':lead_alert($d,$cfg,fn($to,$subject,$body,$sender)=>mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,['From'=>$sender,'Content-Type'=>'text/plain; charset=UTF-8']));
 $health=['finished_at'=>time(),'state'=>in_array($state,['FAILED','UNCERTAIN'],true)?'error':'ok','added'=>$added,'pending'=>count($rows),'alert'=>$state];
 file_put_contents(__DIR__.'/vp_leads_status.json.new',json_encode($health));rename(__DIR__.'/vp_leads_status.json.new',__DIR__.'/vp_leads_status.json');
 echo 'LEAD_RUN added='.$added.' pending='.count($rows).' alert='.$state."\n";
 if($health['state']==='error')exit(1);
}catch(Throwable $e){fwrite(STDERR,'LEAD_ERROR type='.get_class($e).' line='.$e->getLine()."\n");exit(1);}
