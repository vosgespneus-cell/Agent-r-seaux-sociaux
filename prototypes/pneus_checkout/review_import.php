<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);
require __DIR__.'/review_lib.php';
try{
    if(($argv[1]??'')==='--self-test'){
        $base=['quoted_quantity'=>4,'unit_cents'=>5718,'rim'=>16];
        foreach([[0,0,22872],[1,0,28872],[1,2,34872]] as [$f,$s,$expected]){
            if(reviewTotals($base,'4',(string)$f,(string)$s)['total']!==$expected)throw new RuntimeException('test_total_failed');
        }
        foreach([['5','0','0'],['4','2','0'],['4','1','13'],['1.5','0','0'],['04','0','0']] as $bad){
            try{reviewTotals($base,...$bad);}catch(RuntimeException $e){continue;}
            throw new RuntimeException('test_validation_failed');
        }
        $fake='<table><tr><th>Courriel:</th><td>test@example.invalid</td></tr></table>';
        if(reviewCustomerEmail($fake)!=='test@example.invalid')throw new RuntimeException('test_email_failed');
        try{reviewCustomerEmail($fake.$fake);}catch(RuntimeException $e){echo "REVIEW_SELF_TEST_OK cases=10\n";exit;}
        throw new RuntimeException('test_duplicate_email_failed');
    }
    if(($argv[1]??'')!=='--owner-test'||!ctype_digit($argv[2]??''))throw new RuntimeException('owner_test_uid_required');
    $uid=(int)$argv[2];if($uid<1)throw new RuntimeException('invalid_uid');
    define('VP_CAMODO_LIBRARY_ONLY',true);
    require '/home/vp_pneus_probe_20261021/camodo_readonly.php';
    $m=require '/home/vp_mail_config.php';
    $imap=@imap_open('{mail84.lwspanel.com:993/imap/ssl}INBOX','contact@vosgespneus.com',$m['password'],OP_READONLY,1);
    if(!$imap)throw new RuntimeException('imap_unavailable');
    try{
        $parts=htmlParts($imap,$uid,imap_fetchstructure($imap,$uid,FT_UID));
        if(count($parts)!==1)throw new RuntimeException('single_html_required');
        $selection=selectionFromHtml($parts[0]);$email=reviewCustomerEmail($parts[0]);
        $status=imap_status($imap,'{mail84.lwspanel.com:993/imap/ssl}INBOX',SA_UIDVALIDITY);
        if(!$status||!$status->uidvalidity)throw new RuntimeException('uidvalidity_required');
        $source='owner-test:'.$status->uidvalidity.':'.$uid;
    }finally{imap_close($imap);}
    if(!preg_match('/\bR\s*([0-9]{2})\b/',$selection['title'],$rim))throw new RuntimeException('rim_required');
    $db=reviewInit();$id=bin2hex(random_bytes(16));$cap=bin2hex(random_bytes(32));$now=time();
    $find=$db->prepare('SELECT id FROM quotes WHERE source_key=?');$find->execute([$source]);$existing=$find->fetchColumn();
    if($existing){$id=$existing;$stmt=$db->prepare('UPDATE quotes SET capability_hash=?,expires=? WHERE id=?');$stmt->execute([hash('sha256',$cap),$now+3600,$id]);}
    else{
        $stmt=$db->prepare('INSERT INTO quotes(id,source_key,title,reference,customer_email,quoted_quantity,unit_cents,rim,created,expires,capability_hash,quantity) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$id,$source,$selection['title'],$selection['reference'],$email,$selection['quantity'],
            $selection['mail_unit_ttc_cents'],(int)$rim[1],$now,$now+3600,hash('sha256',$cap),$selection['quantity']]);
    }
    echo "OWNER_TEST_READY customer_identified=true payment_enabled=false source_authenticated=false\n";
    // Fragment is not sent in HTTP requests. Do not send this test link to clients.
    echo 'OWNER_TEST_URL https://agents.vosgespneus.com/pneus-essai.php#'.$cap.PHP_EOL;
}catch(Throwable $e){
    if(ob_get_level())ob_end_clean();
    $code=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'import_failed';
    fwrite(STDERR,'REVIEW_ERROR '.$code.PHP_EOL);exit(1);
}
