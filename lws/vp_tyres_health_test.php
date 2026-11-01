<?php
declare(strict_types=1);
require __DIR__.'/vp_tyres_health.php';
$dir=sys_get_temp_dir().'/vp-health-'.bin2hex(random_bytes(8));
mkdir($dir,0700);$now=time();$cases=0;
function checkHealth(string $stage,string $expected):void {
 global $dir,$now,$cases;
 $actual=vp_tyres_health($dir,$now)['tyre_'.$stage]['health'];
 if($actual!==$expected)throw new RuntimeException($stage.':'.$expected.':'.$actual);
 $cases++;
}
function intake(array $change=[],int $offset=0):void {
 global $dir,$now;
 $s=array_fill_keys(['scanned','already_attempted','duplicates','eligible','sent_attempts','accepted','errors'],0);
 file_put_contents($dir.'/client-intake.last.json',json_encode(['checked_at'=>$now+$offset,'summary'=>array_replace($s,$change)]));
}
function bridge(array $change=[],int $offset=0):void {
 global $dir,$now;
 $s=array_fill_keys(['saved','created','reconciled','unchanged','held','errors'],0);
 file_put_contents($dir.'/client-bridge.last.log','CLIENT_BRIDGE_RESULT '.json_encode(array_replace($s,$change))." invoices_sent=0 supplier_orders=0\n");
 touch($dir.'/client-bridge.last.log',$now+$offset);
}
try {
 checkHealth('intake','unknown');checkHealth('bridge','unknown');
 intake();bridge();checkHealth('intake','ok');checkHealth('bridge','ok');
 intake(['errors'=>1]);checkHealth('intake','error');
 intake([], -181);checkHealth('intake','stale');
 intake([],31);checkHealth('intake','stale');
 intake(['errors'=>'0']);checkHealth('intake','error');
 intake();file_put_contents($dir.'/client-intake.last.err','private error not exported');checkHealth('intake','error');
 bridge(['held'=>1]);checkHealth('bridge','error');
 bridge([], -181);checkHealth('bridge','stale');
 bridge();file_put_contents($dir.'/client-bridge.last.log','');checkHealth('bridge','running');
 file_put_contents($dir.'/client-bridge.last.log','malformed');checkHealth('bridge','error');
 bridge();file_put_contents($dir.'/client-bridge.last.err','failure');checkHealth('bridge','error');
 echo 'TYRES_HEALTH_TEST_OK cases='.$cases." no_network=1 no_database=1 no_mail=1\n";
} finally {
 foreach(glob($dir.'/*') as $p)unlink($p);
 rmdir($dir);
}
