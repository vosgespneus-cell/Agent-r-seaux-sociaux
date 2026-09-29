<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
if((int)$db->query("SELECT GET_LOCK('vp_supervisor',0)")->fetchColumn()!==1){exit(0);}
try{
 $db->beginTransaction();
 $sql="INSERT IGNORE INTO vp_actions(task_id,agent,action_type)
 SELECT task_id,agent,
 CASE agent
  WHEN 'telephone' THEN 'handle_call'
  WHEN 'planning' THEN 'check_appointment'
  WHEN 'produits' THEN 'build_product_candidate'
  WHEN 'communication' THEN 'prepare_media'
  WHEN 'marketing' THEN 'prepare_campaign'
  WHEN 'stock' THEN 'inspect_stock'
  WHEN 'gestion' THEN 'inspect_management'
  WHEN 'supervision' THEN 'inspect_supervision'
  ELSE 'triage'
 END
 FROM vp_tasks
 WHERE status IN ('nouveau','en_cours')
 ORDER BY created_at ASC LIMIT 100";
 $db->exec($sql);
 $db->commit();
 echo "VP_SUPERVISOR_OK\n";
}catch(Throwable $e){
 if($db->inTransaction())$db->rollBack();
 fwrite(STDERR,"VP_SUPERVISOR_ERROR\n");exit(1);
}finally{$db->query("SELECT RELEASE_LOCK('vp_supervisor')");}
