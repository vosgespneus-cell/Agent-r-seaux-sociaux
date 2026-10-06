<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);
define('VP_RUN_LIBRARY_ONLY',true);require __DIR__.'/vp_run.php';
define('VP_LEADS_LIBRARY_ONLY',true);require __DIR__.'/vp_leads.php';
function sup_retry_allowed(string $job,array $heartbeat,array $history,int $now): bool {
 // No extra paid AI calls, WhatsApp sends, supplier purchases or publications.
 if(!in_array($job,['worker','calendar','leads'],true)||($heartbeat['state']??'')==='running')return false;
 if($now-(int)($heartbeat['finished_at']??0)<180)return false;
 if($now-(int)($history['last_retry_at']??0)<1800)return false;
 return count(array_filter($history['retry_times']??[],fn($t)=>is_int($t)&&$t>$now-86400))<3;
}
function sup_process(array $snapshot,array &$state,int $now,callable $read,callable $retry,callable $notify): array {
 $result=['checked'=>0,'retry_attempts'=>0,'open_incidents'=>0,'notifications'=>[]];
 foreach($snapshot['agents']??[] as $job=>$agent){
  $result['checked']++;$history=$state['jobs'][$job]??[];$health=$agent['health']??'unknown';
  if(in_array($health,['ok','running'],true)){
   if(!empty($history['incident'])){$history['last_resolved_at']=$now;unset($history['incident']);}
   $state['jobs'][$job]=$history;continue;
  }
  if(empty($history['incident']))$history['incident']=['opened_at'=>$now,'id'=>bin2hex(random_bytes(12))];
  $heartbeat=$read($job);
  if(sup_retry_allowed($job,$heartbeat,$history,$now)){
   $history['retry_times']=array_values(array_filter($history['retry_times']??[],fn($t)=>is_int($t)&&$t>$now-86400));
   $history['retry_times'][]=$now;$history['last_retry_at']=$now;
   $state['jobs'][$job]=$history;
   // Persist the attempt before launching: a supervisor crash does not immediately retry.
   $retry($job,$state);$result['retry_attempts']++;
   $fresh=$read($job);$health=vp_run_health($fresh,$now);
   if($health==='ok'){$history['last_resolved_at']=$now;unset($history['incident']);$state['jobs'][$job]=$history;continue;}
  }
  $result['open_incidents']++;$state['jobs'][$job]=$history;
  $result['notifications'][$job]=$notify($job,$health,$history['incident']['id']);
 }
 // Functional queue issues are separate from successful PHP exits.
 $functional=array_values(array_filter($snapshot['alerts']??[],fn($a)=>!str_starts_with($a,'agent_')));sort($functional);
 if($functional){
  $signature=hash('sha256',json_encode($functional));
  if(($state['functional']['signature']??'')!==$signature)$state['functional']=['signature'=>$signature,'id'=>bin2hex(random_bytes(12))];
  $result['open_incidents']++;$result['notifications']['functional']=$notify('files_et_limites',implode(',',$functional),$state['functional']['id']);
 }else unset($state['functional']);
 $state['checked_at']=$now;return $result;
}
function sup_monitor(): array {
 $command=[PHP_BINARY];if(php_ini_loaded_file())$command=array_merge($command,['-c',php_ini_loaded_file()]);
 $command=array_merge($command,['-d','extension_dir='.(string)ini_get('extension_dir'),__DIR__.'/vp_monitor.php']);
 $p=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file',__DIR__.'/vp_supervisor.err','a']],$pipes,__DIR__);
 if(!is_resource($p))throw new RuntimeException('MONITOR_LAUNCH');$raw=stream_get_contents($pipes[1],1048576);fclose($pipes[1]);$code=proc_close($p);
 $s=json_decode((string)$raw,true);if($code!==0||!is_array($s)||!is_array($s['agents']??null))throw new RuntimeException('MONITOR_INVALID');return $s;
}
function sup_test(): void {
 $now=100000;$h=['state'=>'error','finished_at'=>$now-300];
 if(!sup_retry_allowed('worker',$h,[],$now)||sup_retry_allowed('ai',$h,[],$now)||sup_retry_allowed('whatsapp',$h,[],$now))throw new Exception('ALLOWLIST');
 if(sup_retry_allowed('worker',['state'=>'running'],[],$now)||sup_retry_allowed('worker',$h,['last_retry_at'=>$now-100],$now)||sup_retry_allowed('worker',$h,['retry_times'=>[$now-3000,$now-4000,$now-5000]],$now))throw new Exception('LIMITS');
 $state=[];$attempts=0;$notices=[];$reader=function()use(&$attempts,$h,$now){return $attempts?['state'=>'ok','finished_at'=>$now]:$h;};
 $retry=function()use(&$attempts){$attempts++;};$notify=function($j,$v,$id)use(&$notices){$notices[$id]=true;return 'accepted';};
 $r=sup_process(['agents'=>['worker'=>['health'=>'error']]],$state,$now,$reader,$retry,$notify);
 if($attempts!==1||$r['open_incidents']!==0||$notices)throw new Exception('RECOVERY');
 $snap=['agents'=>['ai'=>['health'=>'error']]];sup_process($snap,$state,$now,$reader,$retry,$notify);sup_process($snap,$state,$now+60,$reader,$retry,$notify);
 if($attempts!==1||count($notices)!==1)throw new Exception('INCIDENT_KEY');
 sup_process(['agents'=>['ai'=>['health'=>'ok']]],$state,$now+120,$reader,$retry,$notify);sup_process($snap,$state,$now+180,$reader,$retry,$notify);
 if(count($notices)!==2)throw new Exception('NEW_INCIDENT');
 echo "SUPERVISOR_SELFTEST_OK recovery allowlist running_lock cooldown daily_limit incident_dedup no_external_calls\n";
}
try {
 if(in_array('--selftest',$argv,true)){sup_test();exit;}
 $lock=fopen(__DIR__.'/vp_supervisor.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
 $path=__DIR__.'/vp_supervisor_state.json';$state=vp_run_read($path);$snapshot=sup_monitor();$cfg=require __DIR__.'/vp_leads_config.php';$db=lead_db();follow_schema($db);
 $read=fn($job)=>vp_run_read(__DIR__.'/vp_run_'.$job.'.json');
 $retry=function($job,$persisted)use($path){vp_run_write($path,$persisted);$cmd=[PHP_BINARY];if(php_ini_loaded_file())$cmd=array_merge($cmd,['-c',php_ini_loaded_file()]);$cmd=array_merge($cmd,['-d','extension_dir='.(string)ini_get('extension_dir'),__DIR__.'/vp_run.php',$job]);$p=proc_open($cmd,[0=>['file','/dev/null','r'],1=>['file',__DIR__.'/vp_supervisor_retries.log','a'],2=>['file',__DIR__.'/vp_supervisor.err','a']],$pipes,__DIR__);if(!is_resource($p))throw new RuntimeException('RETRY_LAUNCH');proc_close($p);};
 $notify=function($job,$health,$id)use($db,$cfg){$body="VOSGES PNEUS — incident automatique\nTraitement : ".$job."\nÉtat : ".$health."\nLes reprises sont limitées aux traitements autorisés, au maximum 3 par 24 h avec 30 minutes entre tentatives. Vérifier le suivi privé LWS.\n";return follow_delivery($db,hash('sha256','supervisor:'.$job.':'.$id),'SUPERVISOR-'.$id,'supervisor_alert',$cfg['owner_email'],'VOSGES PNEUS — un agent demande votre attention',$body,$cfg['sender_email'],'lead_send');};
 $r=sup_process($snapshot,$state,time(),$read,$retry,$notify);vp_run_write($path,$state);
 vp_run_write(__DIR__.'/vp_supervisor_status.json',['finished_at'=>time(),'state'=>$r['open_incidents']?'attention':'ok']+$r);
 echo 'SUPERVISOR_RUN checked='.$r['checked'].' retries='.$r['retry_attempts'].' incidents='.$r['open_incidents'].PHP_EOL;
}catch(Throwable $e){vp_run_write(__DIR__.'/vp_supervisor_status.json',['finished_at'=>time(),'state'=>'error','error_type'=>get_class($e)]);fwrite(STDERR,'SUPERVISOR_ERROR type='.get_class($e).PHP_EOL);exit(1);}
