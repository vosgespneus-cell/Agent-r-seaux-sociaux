<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
ini_set('display_errors','0');umask(0077);define('VP_RUN_LIBRARY_ONLY',true);require __DIR__.'/vp_run.php';
function vp_status_snapshot(): array {
 $c=require __DIR__.'/vp_config.php';$ai=require __DIR__.'/vp_ai_config.php';$cal=require __DIR__.'/vp_calendar_config.php';$wa=require __DIR__.'/vp_wa_config.php';
 $db=new PDO($c['db_dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$now=time();$tz=new DateTimeZone('Europe/Paris');$date=(new DateTimeImmutable('now',$tz));
 $out=['updated_at'=>$date->format(DateTimeInterface::RFC3339),'agents'=>[],'mail'=>[],'tasks'=>[],'calendar'=>[],'ai'=>[],'whatsapp'=>[],'alerts'=>[]];
 foreach(vp_run_jobs() as $job=>$file){$s=vp_run_read(__DIR__.'/vp_run_'.$job.'.json');$h=vp_run_health($s,$now);$out['agents'][$job]=['health'=>$h,'last_success_at'=>isset($s['last_success_at'])?(new DateTimeImmutable('@'.$s['last_success_at']))->setTimezone($tz)->format(DateTimeInterface::RFC3339):null,'exit_code'=>$s['exit_code']??null,'failures'=>$s['failures']??0];if(in_array($h,['error','stale'],true))$out['alerts'][]='agent_'.$job.'_'.$h;}
 // The commercial cron runs outside vp_run.php; inspect its private report directly.
 $commercial=vp_run_read(__DIR__.'/vp_commercial/rapport.json');
 $stockAt=strtotime((string)($commercial['inventory_checked_at']??''));
 $commercialHealth='unknown';
 if($commercial){
  $commercialHealth=($stockAt===false||$stockAt>$now+30||$now-$stockAt>900)?'stale':
   (((int)($commercial['errors']??1)===0&&(int)($commercial['inventory_errors']??1)===0)?'ok':'error');
 }
 $out['agents']['commercial']=['health'=>$commercialHealth,
  'last_success_at'=>$commercialHealth==='ok'?(new DateTimeImmutable('@'.$stockAt))->setTimezone($tz)->format(DateTimeInterface::RFC3339):null,
  'exit_code'=>null,'failures'=>null];
 $out['commercial']=['publication_active'=>!empty($commercial['publication_active']),
  'items'=>count($commercial['items']??[]),'inventory_checked_at'=>$commercial['inventory_checked_at']??null];
 if(in_array($commercialHealth,['error','stale','unknown'],true))$out['alerts'][]='agent_commercial_'.$commercialHealth;
 $out['mail']['total']=(int)$db->query("SELECT COUNT(*) FROM vp_events WHERE source='email'")->fetchColumn();
 $out['mail']['awaiting_ai']=(int)$db->query("SELECT COUNT(*) FROM vp_events e LEFT JOIN vp_ai_mail_results a ON a.event_id=e.event_id WHERE e.source='email' AND a.event_id IS NULL")->fetchColumn();
 $out['mail']['awaiting_calendar']=(int)$db->query("SELECT COUNT(*) FROM vp_ai_mail_results a LEFT JOIN vp_calendar_inputs i ON i.source_event_id=a.event_id WHERE i.source_event_id IS NULL")->fetchColumn();
 foreach($db->query('SELECT status,COUNT(*) AS n FROM vp_tasks GROUP BY status') as $r)$out['tasks'][$r['status']]=(int)$r['n'];
 $out['calendar']['enabled']=!empty($cal['enabled']);$out['calendar']['states']=[];
 foreach($db->query('SELECT state,COUNT(*) AS n FROM vp_calendar_jobs GROUP BY state') as $r)$out['calendar']['states'][$r['state']]=(int)$r['n'];
 $b=vp_run_read(__DIR__.'/vp_ai_budget.json');$limit=min(20,max(1,(int)($ai['daily_request_limit']??20)));$used=($b['day']??'')===$date->format('Y-m-d')?(int)($b['requests']??0):0;
 $out['ai']=['enabled'=>!empty($ai['enabled']),'requests_today'=>$used,'daily_request_limit'=>$limit,'remaining'=>max(0,$limit-$used)];
 $out['whatsapp']=['receive_enabled'=>!empty($wa['receive_enabled']),'drafts_enabled'=>!empty($wa['drafts_enabled']),'send_enabled'=>!empty($wa['send_enabled']),'access_token_present'=>!empty($wa['access_token']),'production_number_configured'=>!empty($wa['production_phone_id'])&&in_array((string)$wa['production_phone_id'],$wa['phone_ids']??[],true)];
 $out['whatsapp']['queue_states']=[];$out['whatsapp']['human_review']=0;
 $waTables=(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('vp_wa_outbox','vp_wa_drafts')")->fetchColumn();
 if($waTables===2){
  foreach($db->query('SELECT state,COUNT(*) AS n FROM vp_wa_outbox GROUP BY state') as $r)$out['whatsapp']['queue_states'][$r['state']]=(int)$r['n'];
  $out['whatsapp']['human_review']=(int)$db->query("SELECT COUNT(*) FROM vp_wa_drafts WHERE state='review'")->fetchColumn();
  if($out['whatsapp']['human_review']>0)$out['alerts'][]='whatsapp_human_review';
  foreach(['failed','uncertain','expired','blocked'] as $state)if(($out['whatsapp']['queue_states'][$state]??0)>0)$out['alerts'][]='whatsapp_'.$state;
 }
 if(($out['calendar']['states']['review']??0)>0)$out['alerts'][]='calendar_review';if($used>=$limit)$out['alerts'][]='ai_daily_limit';
 $out['limits']=['google_calendar_delivery'=>'Not established by a successful LWS preparation alone','scope'=>'Aggregate operational counts only; no client messages or credentials'];return $out;
}
function vp_status_html(array $s): string {
 $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $names=['mail'=>'Réception des mails','jotform'=>'Formulaires Jotform','worker'=>'Répartition des tâches','ai'=>'Analyse des mails par IA','calendar'=>'Préparation du planning','commercial'=>'Stock et préparation commerciale','whatsapp'=>'Assistant WhatsApp','leads'=>'Suivi des demandes de pneus'];$labels=['ok'=>'Passage réussi','error'=>'Erreur à contrôler','stale'=>'Passage trop ancien','running'=>'En cours','unknown'=>'Premier passage attendu'];
 $rows='';foreach($s['agents'] as $job=>$a){$h=$a['health'];$rows.='<tr><td>'.$e($names[$job]).'</td><td class="'.$e($h).'">'.$e($labels[$h]).'</td><td>'.$e($a['last_success_at']??'Pas encore mesuré').'</td></tr>';}
 $state=$s['calendar']['states'];$ready=$state['ready']??0;$review=$state['review']??0;$linked=($state['linked']??0)+($state['sent']??0);
 return '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VOSGES PNEUS — Suivi des agents</title><style>body{margin:0;background:#f2f5f9;color:#183047;font:16px system-ui,sans-serif}main{max-width:1040px;margin:40px auto;padding:24px}h1{font-size:32px}.muted{color:#53697d}.cards{display:flex;flex-wrap:wrap;gap:16px}.card,section{background:white;border:1px solid #d7e0ea;border-radius:14px;padding:22px;margin:18px 0}.card{flex:1;min-width:180px}.card strong{display:block;font-size:32px;margin-top:12px}table{border-collapse:collapse;width:100%;text-align:left}td,th{padding:14px 8px;border-bottom:1px solid #e2e8ef}.ok{color:#087b54}.error,.stale{color:#ba362d}.running,.unknown{color:#926300}li{margin:10px 0}</style><main><p class="muted">CENTRE DE SUIVI · VOSGES PNEUS</p><h1>Nos agents, en un regard</h1><p class="muted">État enregistré le '.$e($s['updated_at']).'. Cette vue est un instantané privé.</p><div class="cards"><div class="card">Mails à analyser<strong>'.$e($s['mail']['awaiting_ai']).'</strong></div><div class="card">Rendez-vous prêts<strong>'.$e($ready).'</strong></div><div class="card">À vérifier<strong>'.$e($review).'</strong></div><div class="card">Appels IA aujourd’hui<strong>'.$e($s['ai']['requests_today']).' / '.$e($s['ai']['daily_request_limit']).'</strong></div></div><section><h2>Les derniers passages</h2><table><thead><tr><th>Agent</th><th>État</th><th>Dernière réussite</th></tr></thead><tbody>'.$rows.'</tbody></table></section><section><h2>Planning et connexions</h2><ul><li>'.$e($linked).' rendez-vous reconnus ou transmis</li><li>Une préparation réussie sur LWS ne prouve pas à elle seule la réception dans Google Agenda</li><li>WhatsApp : réception '.($s['whatsapp']['receive_enabled']?'activée':'non activée').' ; envoi '.($s['whatsapp']['send_enabled']?'activé':'non activé').'</li><li>Numéro de production : '.($s['whatsapp']['production_number_configured']?'configuré':'non raccordé').' ; accès API : '.($s['whatsapp']['access_token_present']?'présent':'absent').'</li><li>Un passage WhatsApp réussi ne prouve pas qu’un message réel a été reçu ou envoyé</li><li>Les clients, messages et clés restent hors de cette vue</li></ul></section></main></html>';
}
try {
 $s=vp_status_snapshot();
 if(in_array('--save',$argv,true)){vp_run_write(__DIR__.'/vp_monitor.json',$s);$tmp=tempnam(__DIR__,'vp-status-');if($tmp===false||file_put_contents($tmp,vp_status_html($s))===false||!chmod($tmp,0600)||!rename($tmp,__DIR__.'/vp_monitor.html'))throw new RuntimeException('save');echo "STATUS_SAVED_PRIVATE\n";exit;}
 if(in_array('--html',$argv,true)){echo vp_status_html($s);exit;}
 if(($argv[1]??'')==='--section'&&isset($s[$argv[2]??''])){$key=$argv[2];echo $key.'='.json_encode($s[$key],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
 echo json_encode($s,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'STATUS_ERROR type='.get_class($e)."\n");exit(1);}
