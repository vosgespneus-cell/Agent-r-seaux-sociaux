<?php
declare(strict_types=1);
// CLI owner-only mail trial. No cron, client broadcast, payment or supplier write.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);
require __DIR__.'/review_lib.php';

function reviewNotification(array $quote,string $cap): array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$cap))throw new RuntimeException('invalid_capability');
    if(!filter_var($quote['customer_email']??'',FILTER_VALIDATE_EMAIL))throw new RuntimeException('invalid_recipient');
    $t=reviewTotals($quote,(string)$quote['quoted_quantity'],'0','0');
    $link='https://agents.vosgespneus.com/pneus-essai.php#'.$cap;
    $body=implode("\r\n",[
        'Bonjour,','',
        'Voici votre chiffrage VOSGES PNEUS — ESSAI PRIVÉ.',
        (string)$quote['title'],
        'Quantité : '.$t['quantity'],
        'Prix des pneus : '.reviewMoney($t['tyres']).' TTC, hors prestations et autres frais éventuels.','',
        'Retrouvez vos pneus et les prestations facultatives sur votre page personnelle :',
        $link,'',
        'Vous pourrez y choisir le montage et équilibrage, avec valve standard et recyclage, ainsi que le gardiennage.',
        'Cette page est encore en essai : aucun achat, aucune réservation et aucun paiement ne sont possibles.',
        'Le prix, la disponibilité, les frais et les délais restent à confirmer avant toute commande.',
        'Le lien personnel expire dans une heure. Ne le transférez pas.','',
        'VOSGES PNEUS',
        'contact@vosgespneus.com'
    ]);
    return ['subject'=>'[ESSAI VP-PNEUS-20261022] Votre sélection et vos prestations',
            'body'=>$body,'recipient'=>$quote['customer_email']];
}

try {
    if(($argv[1]??'')==='--self-test'){
        $q=['customer_email'=>'test@example.invalid','title'=>'Pneu de test 205/55 R16',
            'quoted_quantity'=>4,'unit_cents'=>4570,'rim'=>16];
        $n=reviewNotification($q,str_repeat('a',64));
        if(!str_contains($n['body'],'182,80 €')||!str_contains($n['body'],'ESSAI PRIVÉ')||
           !str_contains($n['body'],'aucun paiement'))throw new RuntimeException('template_test_failed');
        foreach(['bad','x'.str_repeat('a',63),str_repeat('a',65)] as $bad){
            try{reviewNotification($q,$bad);}catch(RuntimeException $e){continue;}
            throw new RuntimeException('capability_test_failed');
        }
        try{reviewNotification(array_replace($q,['customer_email'=>"x@example.invalid\r\nBcc: x@y.invalid"]),str_repeat('a',64));}
        catch(RuntimeException $e){echo "NOTIFICATION_SELF_TEST_OK cases=5\n";exit;}
        throw new RuntimeException('recipient_test_failed');
    }
    $mode=$argv[1]??'';$uid=$argv[2]??'';
    if(!in_array($mode,['--dry-run-owner','--send-owner-test'],true)||!ctype_digit($uid)||(int)$uid<1)
        throw new RuntimeException('owner_test_uid_required');
    $config=json_decode(file_get_contents(VP_REVIEW_DIR.'/notify-owner.json'),true,512,JSON_THROW_ON_ERROR);
    $owner=$config['owner_email']??'';
    if(!filter_var($owner,FILTER_VALIDATE_EMAIL))throw new RuntimeException('owner_not_configured');
    $db=reviewDb();
    $stmt=$db->prepare('SELECT * FROM quotes WHERE source_key LIKE ?');
    $stmt->execute(['owner-test:%:'.$uid]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)!==1)throw new RuntimeException('single_owner_quote_required');
    $q=$rows[0];
    if(strcasecmp($q['customer_email'],$owner)!==0)throw new RuntimeException('owner_recipient_mismatch');
    if($mode==='--dry-run-owner'){
        reviewNotification($q,str_repeat('a',64));
        echo "NOTIFICATION_DRY_RUN_OK owner_match=1 payment_enabled=0 sends=0\n";exit;
    }
    if(($config['enabled']??false)!==true||($config['owner_test_uid']??0)!==(int)$uid)
        throw new RuntimeException('specific_owner_test_not_enabled');
    if(!function_exists('mail'))throw new RuntimeException('mail_unavailable');
    $db->exec('CREATE TABLE IF NOT EXISTS owner_notifications(
      quote_id TEXT PRIMARY KEY, state TEXT NOT NULL, created INTEGER NOT NULL,
      FOREIGN KEY(quote_id) REFERENCES quotes(id))');
    $db->exec('BEGIN IMMEDIATE');
    try {
        $check=$db->prepare('SELECT state FROM owner_notifications WHERE quote_id=?');
        $check->execute([$q['id']]);
        if($check->fetchColumn()!==false){$db->exec('ROLLBACK');echo "NOTIFICATION_ALREADY_ATTEMPTED sends=0\n";exit;}
        $cap=bin2hex(random_bytes(32));$now=time();
        $db->prepare('UPDATE quotes SET capability_hash=?,expires=? WHERE id=?')
            ->execute([hash('sha256',$cap),$now+3600,$q['id']]);
        $db->prepare('INSERT INTO owner_notifications(quote_id,state,created) VALUES(?,?,?)')
            ->execute([$q['id'],'attempt_started',$now]);
        $db->exec('COMMIT');
    }catch(Throwable $e){try{$db->exec('ROLLBACK');}catch(Throwable $ignored){}throw $e;}
    $n=reviewNotification($q,$cap);
    $headers=['From'=>'VOSGES PNEUS <contact@vosgespneus.com>',
              'Reply-To'=>'contact@vosgespneus.com',
              'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8',
              'Content-Transfer-Encoding'=>'8bit',
              'Message-ID'=>'<vp-owner-'.$q['id'].'@agents.vosgespneus.com>'];
    $ok=mail($n['recipient'],$n['subject'],$n['body'],$headers);
    $db->prepare('UPDATE owner_notifications SET state=? WHERE quote_id=?')
        ->execute([$ok?'accepted_by_mail_transport':'transport_rejected',$q['id']]);
    echo $ok?"NOTIFICATION_ACCEPTED delivery_unverified=1 payment_enabled=0 sends=1\n":
             "NOTIFICATION_REJECTED delivery_unverified=1 automatic_retry=0\n";
    if(!$ok)exit(1);
}catch(Throwable $e){
    $code=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'notification_failed';
    fwrite(STDERR,'NOTIFICATION_ERROR '.$code.PHP_EOL);exit(1);
}
