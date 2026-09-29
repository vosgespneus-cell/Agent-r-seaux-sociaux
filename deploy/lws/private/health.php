<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
$counts=[];
foreach(['pending','running','waiting','retry','done','failed','blocked'] as $status){
 $q=$db->prepare('SELECT COUNT(*) FROM vp_actions WHERE status=?');
 $q->execute([$status]);$counts[$status]=(int)$q->fetchColumn();
}
$oldQueue=(int)$db->query("SELECT COUNT(*) FROM vp_actions
 WHERE status IN ('pending','retry') AND run_after<=CURRENT_TIMESTAMP
 AND updated_at<(CURRENT_TIMESTAMP - INTERVAL 30 MINUTE)")->fetchColumn();
$staleRunning=(int)$db->query("SELECT COUNT(*) FROM vp_actions
 WHERE status='running' AND locked_at<(CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)")->fetchColumn();
$oldWaiting=(int)$db->query("SELECT COUNT(*) FROM vp_actions
 WHERE status='waiting' AND updated_at<(CURRENT_TIMESTAMP - INTERVAL 2 HOUR)")->fetchColumn();
$state=($counts['failed']>0||$counts['blocked']>0||$oldQueue>0||$staleRunning>0||$oldWaiting>0)?'ATTENTION':'OK';
echo 'VP_HEALTH '.$state.' '.json_encode($counts,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)
 .' old_queue='.$oldQueue.' stale_running='.$staleRunning.' old_waiting='.$oldWaiting.PHP_EOL;
