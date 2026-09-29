<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__;
$checks=[
 'product_intake.php','supervisor.php','executor.php','recovery.php','health.php',
 'openai_supervisor_client.php','supervisor_decision_guard.php','ai_decision_dispatcher.php',
 'calendar_appointment.php','appointment_lifecycle.php','shopify_admin_client.php',
 'shopify_draft_runner.php','task_lifecycle.php'
];
$missing=[];
foreach($checks as $file) if(!is_file($base.'/'.$file)) $missing[]='file:'.$file;

$configPath=dirname(__DIR__,2).'/home/vp_config.php';
if(!is_file($configPath)) $missing[]='config:vp_config.php';

$openaiPath=dirname(__DIR__,2).'/home/vp_openai_config.php';
if(!is_file($openaiPath)) $missing[]='config:vp_openai_config.php';

$shopifyPath=dirname(__DIR__,2).'/home/vp_shopify_config.php';
if(!is_file($shopifyPath)) $missing[]='config:vp_shopify_config.php';

if($missing){
 echo 'VP_SYSTEM_PREFLIGHT_MISSING '.implode(',',$missing).PHP_EOL;
 exit(2);
}
echo "VP_SYSTEM_PREFLIGHT_OK".PHP_EOL;
