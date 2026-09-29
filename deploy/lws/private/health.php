<?php
declare(strict_types=1);

// Minimal health report for cron/log monitoring. No private payload is printed.
if (PHP_SAPI !== 'cli') { exit(1); }
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
$counts=[];
foreach(['pending','running','retry','done','failed','blocked'] as $status){
 $q=$db->prepare('SELECT COUNT(*) FROM vp_actions WHERE status=?');
 $q->execute([$status]); $counts[$status]=(int)$q->fetchColumn();
}
$old=(int)$db->query("SELECT COUNT(*) FROM vp_actions WHERE status IN ('pending','retry') AND created_at < (CURRENT_TIMESTAMP - INTERVAL 30 MINUTE)")->fetchColumn();
$state=($counts['failed']>0 || $old>0)?'ATTENTION':'OK';
echo 'VP_HEALTH '.$state.' '.json_encode($counts,JSON_UNESCAPED_SLASHES).' old_queue='.$old."\n";
