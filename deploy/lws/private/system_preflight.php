<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__;$missing=[];$warnings=[];
$files=[
 'product_intake.php','supervisor.php','executor.php','recovery.php','health.php',
 'openai_supervisor_client.php','supervisor_decision_guard.php','ai_decision_dispatcher.php',
 'calendar_appointment.php','appointment_lifecycle.php','shopify_admin_client.php',
 'shopify_draft_runner.php','task_lifecycle.php'
];
foreach($files as $file) if(!is_file($base.'/'.$file)) $missing[]='file:'.$file;

$configPath=__DIR__.'/vp_config.php';
if(!is_file($configPath)){$missing[]='config:vp_config.php';}
else{
 try{
  $config=require $configPath;
  foreach(['db_dsn','db_user','db_password'] as $key) if(empty($config[$key])) $missing[]='config:'.$key;
  if(!$missing){
   $db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
   ]);
   foreach(['vp_events','vp_tasks','vp_actions','vp_action_log','vp_product_intake','vp_product_drafts','vp_dead_letters','vp_appointment_requests'] as $table){
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
    $q->execute([$table]);if((int)$q->fetchColumn()!==1)$missing[]='table:'.$table;
   }
   if(!in_array('table:vp_actions',$missing,true)){
    $q=$db->query("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='vp_actions' AND column_name='status'");
    $type=(string)$q->fetchColumn();if(!str_contains($type,"'waiting'"))$missing[]='schema:vp_actions.waiting';
   }
  }
 }catch(Throwable $e){$missing[]='database:unavailable';}
}
foreach(['vp_openai_config.php','vp_shopify_config.php'] as $cfg){
 if(!is_file(__DIR__.'/'.$cfg)) $warnings[]='config:'.$cfg;
}
if($missing){echo 'VP_SYSTEM_PREFLIGHT_MISSING '.implode(',',$missing).PHP_EOL;exit(2);}
if($warnings){echo 'VP_SYSTEM_PREFLIGHT_WARNING '.implode(',',$warnings).PHP_EOL;exit(1);}
echo "VP_SYSTEM_PREFLIGHT_OK".PHP_EOL;
