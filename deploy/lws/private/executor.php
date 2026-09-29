<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
if((int)$db->query("SELECT GET_LOCK('vp_executor',0)")->fetchColumn()!==1){exit(0);}
function logTransition(PDO $db,int $id,?string $from,string $to,?string $note=null):void{
 $q=$db->prepare('INSERT INTO vp_action_log(action_id,from_status,to_status,note) VALUES(?,?,?,?)');
 $q->execute([$id,$from,$to,$note]);
}
try{
 $db->beginTransaction();
 $q=$db->query("SELECT action_id,task_id,agent,action_type,attempts,max_attempts,status
 FROM vp_actions WHERE status IN ('pending','retry') AND run_after<=CURRENT_TIMESTAMP
 ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
 $a=$q->fetch(PDO::FETCH_ASSOC);
 if(!$a){$db->commit();echo "VP_EXECUTOR_IDLE\n";exit(0);}
 $id=(int)$a['action_id']; $from=(string)$a['status'];
 $u=$db->prepare("UPDATE vp_actions SET status='running',attempts=attempts+1,locked_at=CURRENT_TIMESTAMP WHERE action_id=?");
 $u->execute([$id]); logTransition($db,$id,$from,'running','executor claimed action');

 $internal=['triage','prepare_media','prepare_campaign','inspect_stock','inspect_management','inspect_supervision'];
 $special=['handle_call'=>'telephone','check_appointment'=>'planning','build_product_candidate'=>'produits'];

 if(isset($special[$a['action_type']])){
  $u=$db->prepare("UPDATE vp_actions SET status='blocked',last_error=?,locked_at=NULL WHERE action_id=?");
  $u->execute(['awaiting '.$special[$a['action_type']].' specialized pipeline',$id]);
  logTransition($db,$id,'running','blocked','specialized pipeline required');
  $db->commit(); echo "VP_EXECUTOR_SPECIAL_WAIT\n"; exit(0);
 }
 if(!in_array($a['action_type'],$internal,true)){
  $u=$db->prepare("UPDATE vp_actions SET status='blocked',last_error='unknown action type',locked_at=NULL WHERE action_id=?");
  $u->execute([$id]); logTransition($db,$id,'running','blocked','unknown action type');
 }else{
  $u=$db->prepare("UPDATE vp_actions SET status='done',result_reference=?,locked_at=NULL WHERE action_id=?");
  $u->execute(['validated:'.$a['action_type'],$id]); logTransition($db,$id,'running','done','internal validation only');
 }
 $db->commit(); echo "VP_EXECUTOR_OK\n";
}catch(Throwable $e){
 if($db->inTransaction())$db->rollBack();
 fwrite(STDERR,"VP_EXECUTOR_ERROR\n");exit(1);
}finally{$db->query("SELECT RELEASE_LOCK('vp_executor')");}
