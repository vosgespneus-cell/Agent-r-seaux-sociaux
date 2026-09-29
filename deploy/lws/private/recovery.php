<?php
declare(strict_types=1);

// Recovery worker: releases stale executions and quarantines exhausted actions.
if (PHP_SAPI !== 'cli') { exit(1); }
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
if ((int)$db->query("SELECT GET_LOCK('vp_recovery',0)")->fetchColumn()!==1){exit(0);}
try {
 $db->beginTransaction();
 // A process interrupted for >15 minutes is considered stale.
 $stale=$db->query("SELECT action_id, attempts, max_attempts FROM vp_actions
   WHERE status='running' AND locked_at < (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)
   FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
 foreach($stale as $a){
   $id=(int)$a['action_id'];
   $exhausted=(int)$a['attempts'] >= (int)$a['max_attempts'];
   $status=$exhausted?'failed':'retry';
   $runAfter=$exhausted?null:date('Y-m-d H:i:s',time()+min(3600,60*(2 ** max(0,(int)$a['attempts']-1))));
   $q=$db->prepare("UPDATE vp_actions SET status=?, run_after=COALESCE(?,run_after), locked_at=NULL,
       last_error='stale execution recovered' WHERE action_id=?");
   $q->execute([$status,$runAfter,$id]);
   $l=$db->prepare("INSERT INTO vp_action_log(action_id,from_status,to_status,note) VALUES (?,'running',?,'automatic recovery')");
   $l->execute([$id,$status]);
 }
 $db->commit();
 echo "VP_RECOVERY_OK ".count($stale)."\n";
} catch(Throwable $e){
 if($db->inTransaction()){$db->rollBack();}
 fwrite(STDERR,"VP_RECOVERY_ERROR\n"); exit(1);
} finally {$db->query("SELECT RELEASE_LOCK('vp_recovery')");}
