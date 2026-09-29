<?php
declare(strict_types=1);

// Preflight only: no writes. Run before replacing the current worker.
if(PHP_SAPI!=='cli') exit(1);
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false
]);
$required=['vp_events','vp_tasks','vp_product_intake','vp_actions','vp_action_log','vp_product_drafts'];
$missing=[];
$q=$db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
foreach($required as $table){
 $q->execute([$table]);
 if((int)$q->fetchColumn()!==1) $missing[]=$table;
}
if($missing){
 echo 'VP_PREFLIGHT_MISSING '.implode(',',$missing)."\n";
 exit(2);
}
echo "VP_PREFLIGHT_OK\n";
