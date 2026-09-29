<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
function scalar(PDO $db,string $sql):int{return(int)$db->query($sql)->fetchColumn();}
$r=[
 'tasks_new'=>scalar($db,"SELECT COUNT(*) FROM vp_tasks WHERE status='nouveau'"),
 'tasks_blocked'=>scalar($db,"SELECT COUNT(*) FROM vp_tasks WHERE status IN ('bloque','a_verifier')"),
 'actions_waiting'=>scalar($db,"SELECT COUNT(*) FROM vp_actions WHERE status='waiting'"),
 'actions_waiting_old'=>scalar($db,"SELECT COUNT(*) FROM vp_actions WHERE status='waiting' AND updated_at<(CURRENT_TIMESTAMP - INTERVAL 2 HOUR)"),
 'actions_retry'=>scalar($db,"SELECT COUNT(*) FROM vp_actions WHERE status='retry'"),
 'actions_failed'=>scalar($db,"SELECT COUNT(*) FROM vp_actions WHERE status='failed'"),
 'intake_new'=>scalar($db,"SELECT COUNT(*) FROM vp_product_intake WHERE processing_status='new'"),
 'intake_needs_information'=>scalar($db,"SELECT COUNT(*) FROM vp_product_intake WHERE processing_status='needs_information'"),
 'drafts_created_today'=>scalar($db,"SELECT COUNT(*) FROM vp_product_drafts WHERE status='created' AND updated_at>=CURRENT_DATE"),
 'drafts_unverified'=>scalar($db,"SELECT COUNT(*) FROM vp_product_drafts WHERE status='created' AND proof_verified=0"),
 'dead_letters'=>scalar($db,"SELECT COUNT(*) FROM vp_dead_letters")
];
$r['attention']=$r['tasks_blocked']+$r['actions_failed']+$r['actions_waiting_old']+$r['drafts_unverified']+$r['dead_letters'];
echo 'VP_DAILY_SUMMARY '.json_encode($r,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
