<?php
declare(strict_types=1);
ini_set('display_errors','0');umask(0077);

// Private CLI runner: only these existing agents can be launched.
function vp_run_jobs(): array {return ['mail'=>'vp_mail.php','jotform'=>'vp_jotform.php','worker'=>'vp_worker.php','ai'=>'vp_ai_mail.php','calendar'=>'vp_calendar.php'];}
function vp_run_write(string $path,array $state): void {
 $tmp=tempnam(dirname($path),'vp-run-');if($tmp===false)throw new RuntimeException('state');
 try {if(file_put_contents($tmp,json_encode($state,JSON_THROW_ON_ERROR),LOCK_EX)===false||!chmod($tmp,0600)||!rename($tmp,$path))throw new RuntimeException('state');}
 finally {if(is_file($tmp))unlink($tmp);}
}
function vp_run_read(string $path): array {$s=is_file($path)?json_decode((string)file_get_contents($path),true):null;return is_array($s)?$s:[];}
function vp_run_health(array $s,int $now): string {
 if(!$s)return 'unknown';$t=(int)($s['finished_at']??$s['started_at']??0);
 if($now-$t>900)return 'stale';return ($s['state']??'')==='ok'?'ok':(($s['state']??'')==='running'?'running':'error');
}
function vp_run_execute(string $job,array $command,string $dir): int {
 $lock=fopen($dir.'/vp_run_'.$job.'.lock','c');if(!$lock)throw new RuntimeException('lock');
 if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return 0;}
 try {
  $path=$dir.'/vp_run_'.$job.'.json';$previous=vp_run_read($path);$s=['state'=>'running','started_at'=>time(),'last_success_at'=>$previous['last_success_at']??null,'failures'=>(int)($previous['failures']??0)];vp_run_write($path,$s);
  // Forward output to the original cron destinations; never copy it to the heartbeat.
  try {$process=proc_open($command,[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes,$dir);$exit=is_resource($process)?proc_close($process):127;}
  catch(Throwable $e){$exit=127;}
  $s['finished_at']=time();$s['exit_code']=$exit;$s['state']=$exit===0?'ok':'error';
  if($exit===0)$s['last_success_at']=$s['finished_at'];else $s['failures']++;
  vp_run_write($path,$s);return $exit===0?0:1;
 }finally {flock($lock,LOCK_UN);fclose($lock);}
}
if(defined('VP_RUN_LIBRARY_ONLY'))return;
if(PHP_SAPI!=='cli')exit(1);
try {
 if(($argv[1]??'')==='--selftest'){
  $dir=sys_get_temp_dir().'/vp-run-test-'.bin2hex(random_bytes(8));if(!mkdir($dir,0700))throw new RuntimeException('test');
  try {
   if(vp_run_execute('fixture',[PHP_BINARY,'-r','exit(0);'],$dir)!==0)throw new RuntimeException('success');$a=vp_run_read($dir.'/vp_run_fixture.json');
   if(vp_run_health($a,time())!=='ok'||empty($a['last_success_at']))throw new RuntimeException('heartbeat');
   if(vp_run_execute('fixture',[PHP_BINARY,'-r','exit(7);'],$dir)!==1)throw new RuntimeException('failure');$b=vp_run_read($dir.'/vp_run_fixture.json');
   if($b['exit_code']!==7||$b['failures']!==1||$b['last_success_at']!==$a['last_success_at']||vp_run_health($b,time())!=='error')throw new RuntimeException('failure_state');
   if(vp_run_health([],time())!=='unknown'||vp_run_health($a,time()+901)!=='stale')throw new RuntimeException('stale');
   $lock=fopen($dir.'/vp_run_fixture.lock','c');flock($lock,LOCK_EX);vp_run_execute('fixture',[PHP_BINARY,'-r','exit(0);'],$dir);flock($lock,LOCK_UN);fclose($lock);
   if(vp_run_read($dir.'/vp_run_fixture.json')!==$b)throw new RuntimeException('overlap');
   if((fileperms($dir.'/vp_run_fixture.json')&0777)!==0600)throw new RuntimeException('permissions');
   echo "RUN_SELFTEST_OK success failure stale unknown overlap private\n";
  }finally {foreach(glob($dir.'/*')?:[] as $p)unlink($p);rmdir($dir);}exit;
 }
 $job=$argv[1]??'';$jobs=vp_run_jobs();if(!isset($jobs[$job]))exit(2);
 exit(vp_run_execute($job,[PHP_BINARY,__DIR__.'/'.$jobs[$job]],__DIR__));
}catch(Throwable $e){fwrite(STDERR,'RUN_ERROR type='.get_class($e)."\n");exit(1);}
