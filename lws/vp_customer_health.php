<?php
declare(strict_types=1);
// Aggregate monitoring only: no customer content, sends or purchases.
function vp_customer_counts(array $rows, int $now): array {
    $out=['pending'=>count($rows),'without_send_proof'=>0,'overdue'=>0,'reply_needs_review'=>0];
    foreach($rows as $r){
        if(($r['delivery_state']??'')!=='accepted')$out['without_send_proof']++;
        $created=strtotime((string)($r['created_at']??''));
        if($created===false||$created>$now+30||$now-$created>86400)$out['overdue']++;
        if(($r['state']??'')==='replied')$out['reply_needs_review']++;
    }
    return $out;
}
function vp_customer_health(PDO $db): array {
    $rows=$db->query("SELECT l.state,l.created_at,o.state delivery_state FROM vp_leads l LEFT JOIN vp_tasks t ON t.event_id=l.event_id LEFT JOIN vp_lead_outbox o ON o.event_id=l.event_id AND o.kind='clarification' WHERE l.state IN ('new','contacted','quoted','replied') AND (t.status IS NULL OR t.status NOT IN ('done','closed','completed','termine','terminé','traite','traité','annule','annulé'))")->fetchAll(PDO::FETCH_ASSOC);
    return vp_customer_counts($rows,time());
}
function vp_customer_alerts(array $counts): array {
    $alerts=[];
    foreach(['without_send_proof','overdue','reply_needs_review'] as $key)if(($counts[$key]??0)>0)$alerts[]='customer_'.$key;
    return $alerts;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--selftest')exit(2);
    $now=1800000000;
    $r=vp_customer_counts([
        ['state'=>'contacted','created_at'=>date('c',$now-90000),'delivery_state'=>null],
        ['state'=>'replied','created_at'=>date('c',$now-300),'delivery_state'=>'accepted'],
        ['state'=>'contacted','created_at'=>date('c',$now-300),'delivery_state'=>'uncertain'],
    ],$now);
    if($r!==['pending'=>3,'without_send_proof'=>2,'overdue'=>1,'reply_needs_review'=>1])throw new RuntimeException('COUNTS');
    if(count(vp_customer_alerts($r))!==3||vp_customer_alerts(vp_customer_counts([],$now)))throw new RuntimeException('ALERTS');
    if(vp_customer_counts([['created_at'=>'invalid','delivery_state'=>'accepted']],$now)['overdue']!==1)throw new RuntimeException('BAD_DATE');
    echo "CUSTOMER_HEALTH_SELFTEST_OK contacted_not_closed smtp_proof overdue reply_review no_external_calls\n";
}
