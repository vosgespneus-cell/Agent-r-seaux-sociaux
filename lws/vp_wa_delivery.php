<?php
declare(strict_types=1);
// Private delivery engine. No access token belongs in this repository.
function wa_delivery_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS vp_wa_outbox (batch_id CHAR(64) CHARACTER SET ascii PRIMARY KEY, phone_id VARCHAR(32) CHARACTER SET ascii NOT NULL, sender VARCHAR(24) CHARACTER SET ascii NOT NULL, reply TEXT NOT NULL, last_inbound BIGINT NOT NULL, state VARCHAR(16) NOT NULL DEFAULT 'ready', remote_id VARCHAR(250) CHARACTER SET ascii NULL, error_code VARCHAR(64) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX wa_send_state(state), INDEX wa_remote(remote_id))");
}
function wa_window(int $timestamp, int $now): bool {
    return $timestamp > 0 && $timestamp <= $now + 30 && $timestamp > $now - 85800;
}
function wa_send_payload(string $sender, string $reply): array {
    if (!preg_match('/^[0-9]{6,20}$/D', $sender) || trim($reply)==='' || mb_strlen($reply)>1500) throw new RuntimeException('WA_SEND_INVALID');
    return ['messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$sender,'type'=>'text','text'=>['preview_url'=>false,'body'=>$reply]];
}
function wa_send_outcome(int $http, string $raw): array {
    $r=json_decode($raw,true);
    $id=$r['messages'][0]['id']??null;
    if ($http===200 && is_string($id) && str_starts_with($id,'wamid.') && strlen($id)<=250) return ['state'=>'sent','id'=>$id,'code'=>null];
    // Timeouts, 5xx and malformed success responses may have sent the message.
    // Hold them for a human; automatically retrying could send it twice.
    $definite=$http>=400 && $http<500;
    return ['state'=>$definite?'failed':'uncertain','id'=>null,'code'=>'HTTP_'.$http];
}
function wa_send_http(array $cfg, string $phone, array $payload): array {
    if (!preg_match('/^[0-9]{6,32}$/D',$phone) || !in_array($phone,$cfg['phone_ids']??[],true)) throw new RuntimeException('WA_PHONE_BLOCKED');
    $token=(string)($cfg['access_token']??'');$version=(string)($cfg['graph_version']??'v26.0');
    if ($token==='' || !preg_match('/^v[0-9]{2}\.0$/D',$version)) throw new RuntimeException('WA_ACCESS_MISSING');
    $h=curl_init('https://graph.facebook.com/'.$version.'/'.$phone.'/messages');
    curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json']]);
    $raw=curl_exec($h);$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
    return wa_send_outcome($http,is_string($raw)?$raw:'');
}
function wa_dispatch(PDO $db, array $cfg, ?callable $transport=null): void {
    if (empty($cfg['send_enabled']) || empty($cfg['access_token'])) return;
    // A previous process interrupted while sending is deliberately not retried.
    $db->exec("UPDATE vp_wa_outbox SET state='uncertain',error_code='INTERRUPTED' WHERE state='sending' AND updated_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE)");
    $q=$db->query("SELECT * FROM vp_wa_outbox WHERE state='ready' ORDER BY created_at,batch_id LIMIT 3");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $claim=$db->prepare("UPDATE vp_wa_outbox SET state=? WHERE batch_id=? AND state='ready'");
        $state=wa_window((int)$r['last_inbound'],time())?'sending':'expired';
        if (!in_array($r['phone_id'],$cfg['phone_ids']??[],true)) $state='blocked';
        $claim->execute([$state,$r['batch_id']]);if ($claim->rowCount()!==1 || $state!=='sending') continue;
        try {
            $payload=wa_send_payload($r['sender'],$r['reply']);
            $out=$transport?$transport($cfg,$r['phone_id'],$payload):wa_send_http($cfg,$r['phone_id'],$payload);
        } catch (Throwable $e) {$out=['state'=>'uncertain','id'=>null,'code'=>'TRANSPORT_EXCEPTION'];}
        $update=$db->prepare("UPDATE vp_wa_outbox SET state=?,remote_id=?,error_code=? WHERE batch_id=? AND state='sending'");
        $update->execute([$out['state'],$out['id'],$out['code'],$r['batch_id']]);
        if ($out['state']==='sent') {
            $update=$db->prepare("UPDATE vp_wa_drafts SET state='sent' WHERE batch_id=? AND state='ready'");$update->execute([$r['batch_id']]);
        }
        echo 'WA_DELIVERY state='.$out['state'].PHP_EOL;
    }
}
function wa_process(PDO $db, array $cfg): void {
    wa_delivery_schema($db);wa_dispatch($db,$cfg);
    if (empty($cfg['drafts_enabled'])) return;
    $groups=$db->query("SELECT phone_id,sender FROM vp_wa_messages WHERE state='pending' GROUP BY phone_id,sender HAVING MAX(received_at)<DATE_SUB(NOW(),INTERVAL 45 SECOND) LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($groups as $g) {
        $q=$db->prepare("SELECT * FROM vp_wa_messages WHERE phone_id=? AND sender=? AND state='pending' ORDER BY received_at,message_id LIMIT 30");$q->execute([$g['phone_id'],$g['sender']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if (!$rows) continue;
        $ids=array_column($rows,'message_id');$messages=[];$last=0;$attachment=false;
        foreach ($rows as $row) {$m=json_decode($row['body'],true,512,JSON_THROW_ON_ERROR);$messages[]=$m;$last=max($last,(int)$row['remote_ts']);$attachment=$attachment||!empty($m['attachment'])||!in_array($m['type'],['text','button','interactive'],true);}
        $batch=hash('sha256',implode('|',$ids));
        if (!wa_window($last,time())) {
            $q=$db->prepare("UPDATE vp_wa_messages SET state='review' WHERE message_id=? AND state='pending'");foreach ($ids as $id)$q->execute([$id]);continue;
        }
        if ($attachment) {
            $result=['business'=>'unknown','intent'=>'other','needs_review'=>true,'reply'=>'Votre message et sa pièce jointe ont été reçus par notre assistant automatique. Leur contenu doit être vérifié par notre équipe.'];
        } else {
            $q=$db->prepare("SELECT result_json,state FROM vp_wa_drafts WHERE phone_id=? AND sender=? ORDER BY created_at DESC LIMIT 5");$q->execute([$g['phone_id'],$g['sender']]);$history=array_map(fn($r)=>['state'=>$r['state'],'result'=>json_decode($r['result_json'],true)],$q->fetchAll(PDO::FETCH_ASSOC));
            $ai=require __DIR__.'/vp_ai_config.php';if(empty($ai['enabled'])) throw new RuntimeException('WA_AI_DISABLED');
            wa_budget((int)$cfg['daily_request_limit']);$result=wa_generate(['messages'=>$messages,'previous_drafts'=>$history],$ai);
            if ($result['business']==='unknown') $result['needs_review']=true;
            if ($result['needs_review']) $result['reply']='Votre message a été reçu par notre assistant automatique'.($result['business']==='transmalin'?' Transmalin':'').'. Notre équipe doit vérifier votre demande avant de confirmer une réponse.';
        }
        $db->beginTransaction();
        try {
            $q=$db->prepare('INSERT INTO vp_wa_drafts(batch_id,phone_id,sender,result_json,state) VALUES(?,?,?,?,?)');$q->execute([$batch,$g['phone_id'],$g['sender'],json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$result['needs_review']?'review':'ready']);
            // Only a safe acknowledgement is sent for a case needing a human.
            $q=$db->prepare('INSERT INTO vp_wa_outbox(batch_id,phone_id,sender,reply,last_inbound) VALUES(?,?,?,?,?)');$q->execute([$batch,$g['phone_id'],$g['sender'],$result['reply'],$last]);
            $q=$db->prepare("UPDATE vp_wa_messages SET state='drafted' WHERE message_id=? AND state='pending'");foreach($ids as $id)$q->execute([$id]);$db->commit();
        } catch(Throwable $e) {$db->rollBack();throw $e;}
        echo 'WA_DRAFT_OK business='.$result['business'].' review='.(int)$result['needs_review'].PHP_EOL;
    }
    wa_dispatch($db,$cfg);
}
function wa_delivery_selftest(): void {
    $now=1800000000;
    if(!wa_window($now,$now)||wa_window($now-86400,$now)||wa_window($now+31,$now)||wa_window(0,$now))throw new RuntimeException('WINDOW_TEST');
    $p=wa_send_payload('33600000000','Bonjour');if($p['type']!=='text'||$p['text']['preview_url']!==false)throw new RuntimeException('PAYLOAD_TEST');
    foreach(['','invalid'] as $bad){try{wa_send_payload($bad,'Bonjour');throw new LogicException('FAILED');}catch(RuntimeException $e){}}
    if(wa_send_outcome(200,'{"messages":[{"id":"wamid.fixture"}]}')['state']!=='sent'||wa_send_outcome(401,'{}')['state']!=='failed'||wa_send_outcome(0,'')['state']!=='uncertain'||wa_send_outcome(500,'{}')['state']!=='uncertain'||wa_send_outcome(200,'{}')['state']!=='uncertain')throw new RuntimeException('OUTCOME_TEST');
    echo "WA_DELIVERY_SELFTEST_OK window payload accepted rejected uncertain".PHP_EOL;
}
