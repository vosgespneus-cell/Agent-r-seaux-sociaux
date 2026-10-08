<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
ini_set('display_errors','0');umask(0077);
function readiness_read(string $path): array {
 if(!is_file($path)||filesize($path)>1048576)return [];
 $r=json_decode((string)file_get_contents($path),true);return is_array($r)?$r:[];
}
function readiness_supplier(array $f,int $now): array {
 $t=$f['observed_at']??null;
 $fresh=is_int($t)&&$t<=$now+60&&$now-$t<=3600;
 return ['state'=>$fresh?'fresh':'missing_or_stale','automatic_collection'=>($f['source_kind']??'')==='supplier_feed',
 'age_seconds'=>is_int($t)?max(0,$now-$t):null,'offers'=>count(is_array($f['offers']??null)?$f['offers']:[])];
}
function readiness_outcome(int $http,array $r): array {
 return ['state'=>$http===200&&isset($r['id'])?'accessible':'unavailable','http'=>$http,
 'error_code'=>isset($r['error']['code'])?(int)$r['error']['code']:null];
}
function readiness_wa(array $cfg): array {
 $phone=(string)($cfg['production_phone_id']??'');$version=(string)($cfg['graph_version']??'v26.0');
 if(empty($cfg['access_token'])||!preg_match('/^[0-9]{6,32}$/D',$phone)||!preg_match('/^v[0-9]{2}\.0$/D',$version))
 return ['state'=>'not_configured','http'=>null,'error_code'=>null];
 $h=curl_init('https://graph.facebook.com/'.$version.'/'.$phone.'?fields=id');
 curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
 CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
 CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$cfg['access_token']]]);
 $raw=curl_exec($h);$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
 $r=json_decode(is_string($raw)?$raw:'',true);return readiness_outcome($http,is_array($r)?$r:[]);
}
function readiness_write(string $path,array $r): void {
 $tmp=tempnam(dirname($path),'vp-ready-');if($tmp===false)throw new RuntimeException('TEMP');
 try{if(file_put_contents($tmp,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false||
 !chmod($tmp,0600)||!rename($tmp,$path))throw new RuntimeException('SAVE');}
 finally{if(is_file($tmp))unlink($tmp);}
}
function readiness_test(): void {
 if(readiness_supplier(['observed_at'=>1000,'offers'=>[]],1001)['state']!=='fresh')throw new Exception('FRESH');
 if(readiness_supplier(['observed_at'=>1000],4601)['state']!=='missing_or_stale')throw new Exception('STALE');
 if(readiness_supplier(['observed_at'=>2000],1000)['state']!=='missing_or_stale')throw new Exception('FUTURE');
 if(readiness_supplier([],1000)['state']!=='missing_or_stale')throw new Exception('MISSING');
 if(readiness_outcome(400,['error'=>['code'=>200]])['state']!=='unavailable')throw new Exception('PERMISSION');
 if(readiness_outcome(200,[])['state']!=='unavailable')throw new Exception('EMPTY');
 if(readiness_outcome(200,['id'=>'fixture'])['state']!=='accessible')throw new Exception('ACCESS');
 echo "READINESS_SELFTEST_OK freshness future permissions malformed_success no_external_calls\n";
}
try{
 if(in_array('--selftest',$argv,true)){readiness_test();exit;}
 $lock=fopen(__DIR__.'/vp_readiness.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
 $now=time();$wa=require __DIR__.'/vp_wa_config.php';$old=readiness_read(__DIR__.'/vp_readiness.json');
 $probe=$old['whatsapp']['api']??[];
 if(in_array('--probe',$argv,true)||!is_int($probe['checked_at']??null)||$probe['checked_at']>$now+60||$now-$probe['checked_at']>=3600)
 {$probe=readiness_wa($wa);$probe['checked_at']=$now;}
 $feed=readiness_supplier(readiness_read(__DIR__.'/vp_supplier_feed.json'),$now);
 $quotes=readiness_read(__DIR__.'/vp_lead_quotes.json');$states=[];
 foreach($quotes['drafts']??[] as $d){$k=(string)($d['status']??'unknown');$states[$k]=($states[$k]??0)+1;}
 $commercial=readiness_read(__DIR__.'/vp_commercial/rapport.json');
 $blockers=[];
 if(empty($wa['send_enabled']))$blockers[]='whatsapp_send_disabled';
 if(($probe['state']??'')!=='accessible')$blockers[]='whatsapp_api_unavailable';
 if(empty($commercial['publication_active']))$blockers[]='social_publication_disabled';
 if($feed['state']!=='fresh'&&count($quotes['drafts']??[])>0)$blockers[]='supplier_prices_stale';
 if(!$feed['automatic_collection'])$blockers[]='supplier_collection_not_connected';
 $report=['updated_at'=>date(DATE_ATOM),'whatsapp'=>['receive_enabled'=>!empty($wa['receive_enabled']),
 'drafts_enabled'=>!empty($wa['drafts_enabled']),'send_enabled'=>!empty($wa['send_enabled']),'api'=>$probe],
 'supplier'=>$feed,'quote_statuses'=>$states,'social_publication_active'=>!empty($commercial['publication_active']),
 'blockers'=>$blockers,'scope'=>'Operational checks only; no messages, orders, publication or AI calls.'];
 readiness_write(__DIR__.'/vp_readiness.json',$report);
 echo 'READINESS_SAVED blockers='.count($blockers).' api='.($probe['state']??'unknown')."\n";
}catch(Throwable $e){fwrite(STDERR,'READINESS_ERROR type='.get_class($e)."\n");exit(1);}
