<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
umask(0077);
function vp_replace_once(string $source,string $old,string $new): string {
    if(substr_count($source,$old)!==1)throw new RuntimeException('PATCH_CONTEXT_MISMATCH');
    return str_replace($old,$new,$source);
}
function vp_patch_file(string $name,callable $patch,string $marker): void {
    $path=__DIR__.'/'.$name;$old=file_get_contents($path);
    if($old===false)throw new RuntimeException('SOURCE_MISSING');
    if(str_contains($old,$marker)){echo $name." ALREADY_PATCHED\n";return;}
    $new=$patch($old);$stage=$path.'.stage-20261008';
    if(file_put_contents($stage,$new,LOCK_EX)===false||!chmod($stage,0600))throw new RuntimeException('STAGE');
    $cmd=[PHP_BINARY,'-n','-l',$stage];$p=proc_open($cmd,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('LINT_LAUNCH');
    stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
    if(proc_close($p)!==0)throw new RuntimeException('LINT_FAILED');
    $backup=$path.'.before-stabilize-'.date('YmdHis');
    if(!copy($path,$backup)||!chmod($backup,0600)||!rename($stage,$path))throw new RuntimeException('INSTALL');
    echo $name." PATCHED backup=".basename($backup)."\n";
}
if(($argv[1]??'')==='--selftest'){
    if(vp_replace_once('abc','b','X')!=='aXc')throw new RuntimeException('REPLACE');
    foreach(['missing','bb'] as $s){$caught=false;try{vp_replace_once($s,'b','X');}catch(RuntimeException $e){$caught=true;}if(!$caught)throw new RuntimeException('CONTEXT');}
    echo "PATCH_SELFTEST_OK unique_context no_external_calls\n";exit;
}
if(($argv[1]??'')!=='--apply')exit(2);
require_once __DIR__.'/vp_customer_health.php';
vp_patch_file('vp_monitor.php',function($s){
    $old="    \$out['limits']=";
    // Production versions may be compact: anchor the assignment, not indentation.
    $old="\$out['limits']=";
    $new="require_once __DIR__.'/vp_customer_health.php';\$out['customers']=vp_customer_health(\$db);\$out['alerts']=array_merge(\$out['alerts'],vp_customer_alerts(\$out['customers']));\n".$old;
    return vp_replace_once($s,$old,$new);
},"require_once __DIR__.'/vp_customer_health.php'");
vp_patch_file('vp_run.php',function($s){
    $old="function vp_run_execute(string \$job,array \$command,string \$dir): int {";
    $new=$old."\n    // Serialize child agents across cron phases to protect the shared process quota.\n    \$global=fopen(\$dir.'/vp_run_global.lock','c');if(!\$global)throw new RuntimeException('global_lock');if(!flock(\$global,LOCK_EX|LOCK_NB)){fclose(\$global);return 0;}";
    $s=vp_replace_once($s,$old,$new);
    return vp_replace_once($s,"}finally {flock(\$lock,LOCK_UN);fclose(\$lock);}","}finally {flock(\$lock,LOCK_UN);fclose(\$lock);flock(\$global,LOCK_UN);fclose(\$global);}");
},'vp_run_global.lock');
vp_patch_file('vp_jotform.php',function($s){
    $s=str_replace('echo "JOTFORM_NOT_CONFIGURED\\n";', 'fwrite(STDERR,"JOTFORM_NOT_CONFIGURED\\n");',$s);
    $s=preg_replace('/(fwrite\(STDERR,"JOTFORM_NOT_CONFIGURED\\\\n"\);\s*)exit\(0\);/','$1exit(1);',$s);
    $s=vp_replace_once($s,'$status = curl_getinfo($request, CURLINFO_HTTP_CODE);','$status = curl_getinfo($request, CURLINFO_HTTP_CODE);' . "\n        \$curlError = curl_errno(\$request);curl_close(\$request);");
    return vp_replace_once($s,'fwrite(STDERR, "JOTFORM_SYNC_ERROR\\n");','fwrite(STDERR, "JOTFORM_SYNC_ERROR type=".get_class($error)." http=".(int)($status??0)." curl=".(int)($curlError??0)."\\n");');
},'JOTFORM_SYNC_ERROR type=');
vp_patch_file('vp_supervisor.php',function($s){
    return vp_replace_once($s,"['worker','calendar','leads']","['worker','calendar','leads','jotform']");
},"['worker','calendar','leads','jotform']");
echo "STABILIZATION_APPLIED\n";
