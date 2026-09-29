<?php
declare(strict_types=1);

// Recovery worker: releases stale executions and quarantines exhausted actions.
// It mirrors TaskLifecycle semantics without nesting transactions.
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
 $stale=$db->query("SELECT action_id,task_id,attempts,max_attempts FROM vp_actions
   WHERE status='running' AND locked_at < (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)
   FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);

 $affectedTasks=[];
 foreach($stale as $a){
   $id=(int)$a['action_id'];
   $taskId=(string)$a['task_id'];
   $attempts=(int)$a['attempts'];
   $maxAttempts=(int)$a['max_attempts'];
   $exhausted=$attempts >= $maxAttempts;
   $status=$exhausted?'failed':'retry';
   $reason='stale execution recovered';

   if($exhausted){
     $q=$db->prepare("UPDATE vp_actions
       SET status='failed',locked_at=NULL,last_error=?
       WHERE action_id=? AND task_id=? AND status='running'");
     $q->execute([$reason,$id,$taskId]);
     if($q->rowCount()!==1) throw new RuntimeException('stale_action_state_conflict');

     $q=$db->prepare("INSERT IGNORE INTO vp_dead_letters(action_id,task_id,reason) VALUES(?,?,?)");
     $q->execute([$id,$taskId,$reason]);
   }else{
     $delay=min(3600,60*(2 ** max(0,$attempts-1)));
     $runAfter=(new DateTimeImmutable('now',new DateTimeZone('UTC')))
       ->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s');
     $q=$db->prepare("UPDATE vp_actions
       SET status='retry',run_after=?,locked_at=NULL,last_error=?
       WHERE action_id=? AND task_id=? AND status='running'");
     $q->execute([$runAfter,$reason,$id,$taskId]);
     if($q->rowCount()!==1) throw new RuntimeException('stale_action_state_conflict');
   }

   $l=$db->prepare("INSERT INTO vp_action_log(action_id,from_status,to_status,note)
     VALUES (?,'running',?,'automatic recovery')");
   $l->execute([$id,$status]);
   $affectedTasks[$taskId]=true;
 }

 // Recompute parent task state for every recovered action.
 foreach(array_keys($affectedTasks) as $taskId){
   $q=$db->prepare("SELECT status,COUNT(*) n FROM vp_actions WHERE task_id=? GROUP BY status");
   $q->execute([$taskId]);
   $counts=[];
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$counts[$r['status']]=(int)$r['n'];}
   $total=array_sum($counts);
   if($total===0) continue;
   if(($counts['failed']??0)>0){$taskStatus='bloque';}
   elseif(($counts['blocked']??0)>0){$taskStatus='a_verifier';}
   elseif(($counts['done']??0)===$total){$taskStatus='termine';}
   else{$taskStatus='en_cours';}
   $u=$db->prepare("UPDATE vp_tasks SET status=? WHERE task_id=?");
   $u->execute([$taskStatus,$taskId]);
 }

 $db->commit();
 echo "VP_RECOVERY_OK ".count($stale)."\n";
} catch(Throwable $e){
 if($db->inTransaction()){$db->rollBack();}
 fwrite(STDERR,"VP_RECOVERY_ERROR\n"); exit(1);
} finally {$db->query("SELECT RELEASE_LOCK('vp_recovery')");}
