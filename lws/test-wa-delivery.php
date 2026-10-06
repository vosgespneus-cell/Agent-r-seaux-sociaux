<?php
declare(strict_types=1);
// CLI only. Temporary tables, fake transport, no AI or Meta API call.
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/vp_wa_delivery.php';
$c=require __DIR__.'/vp_config.php';
$db=new PDO($c['db_dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TEMPORARY TABLE vp_wa_outbox (batch_id CHAR(64) PRIMARY KEY,phone_id VARCHAR(32),sender VARCHAR(24),reply TEXT,last_inbound BIGINT,state VARCHAR(16),remote_id VARCHAR(250),error_code VARCHAR(64),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$db->exec("CREATE TEMPORARY TABLE vp_wa_drafts (batch_id CHAR(64) PRIMARY KEY,state VARCHAR(16))");
$insert=$db->prepare('INSERT INTO vp_wa_outbox(batch_id,phone_id,sender,reply,last_inbound,state) VALUES(?,?,?,?,?,?)');
foreach([['a','123',time(),'ready'],['b','123',time()-86400,'ready'],['c','999',time(),'ready']] as [$id,$phone,$time,$state])$insert->execute([$id,$phone,'33600000000','Bonjour',$time,$state]);
$db->exec("INSERT INTO vp_wa_drafts VALUES('a','review')");
$calls=0;$transport=function()use(&$calls){$calls++;return ['state'=>'sent','id'=>'wamid.fixture','code'=>null];};
$cfg=['send_enabled'=>false,'access_token'=>'fixture','phone_ids'=>['123']];wa_dispatch($db,$cfg,$transport);
if($calls!==0)throw new RuntimeException('DISABLED_TEST');
$cfg['send_enabled']=true;wa_dispatch($db,$cfg,$transport);wa_dispatch($db,$cfg,$transport);
$states=$db->query('SELECT batch_id,state FROM vp_wa_outbox')->fetchAll(PDO::FETCH_KEY_PAIR);
if($calls!==1||$states['a']!=='sent'||$states['b']!=='expired'||$states['c']!=='blocked'||$db->query("SELECT state FROM vp_wa_drafts WHERE batch_id='a'")->fetchColumn()!=='review')throw new RuntimeException('CLAIM_TEST');
$insert->execute(['d','123','33600000000','Bonjour',time(),'ready']);
$uncertain=function()use(&$calls){$calls++;return wa_send_outcome(0,'');};wa_dispatch($db,$cfg,$uncertain);wa_dispatch($db,$cfg,$uncertain);
if($calls!==2||$db->query("SELECT state FROM vp_wa_outbox WHERE batch_id='d'")->fetchColumn()!=='uncertain')throw new RuntimeException('DUPLICATE_TEST');
$insert->execute(['e','123','33600000000','Bonjour',time(),'sending']);$db->exec("UPDATE vp_wa_outbox SET updated_at=DATE_SUB(NOW(),INTERVAL 6 MINUTE) WHERE batch_id='e'");wa_dispatch($db,$cfg,$transport);
if($calls!==2||$db->query("SELECT state FROM vp_wa_outbox WHERE batch_id='e'")->fetchColumn()!=='uncertain')throw new RuntimeException('CRASH_TEST');
echo "WA_DATABASE_SELFTEST_OK disabled once_only expired phone_filter human_review timeout crash no_external_calls\n";
