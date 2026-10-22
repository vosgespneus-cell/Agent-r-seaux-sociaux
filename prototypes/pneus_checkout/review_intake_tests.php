<?php
declare(strict_types=1);
// Private, test-address-only intake. No customer rollout or payment support.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);
require __DIR__.'/review_lib.php';
define('VP_CAMODO_LIBRARY_ONLY',true);
require '/home/vp_pneus_probe_20261021/camodo_readonly.php';

function intakeTestMarker(string $html): bool {
    $dom=new DOMDocument();$old=libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($old);
    foreach($dom->getElementsByTagName('div') as $node){
        $classes=preg_split('/\s+/',$node->getAttribute('class'));
        if(in_array('zusatztext',$classes,true)&&preg_match('/\btest\b/iu',cleanText($node)))return true;
    }
    return false;
}
function intakeFingerprint(array $selection,string $email): string {
    return hash('sha256',json_encode([strtolower($email),$selection['reference'],
        $selection['quantity'],$selection['mail_unit_ttc_cents']],JSON_THROW_ON_ERROR));
}
function intakeChild(string $script,array $args): void {
    if(!function_exists('exec'))throw new RuntimeException('child_execution_unavailable');
    $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/'.$script);
    foreach($args as $arg)$command.=' '.escapeshellarg((string)$arg);
    $output=[];$code=1;exec($command.' 2>&1',$output,$code);
    // Child output may contain personal links: never log it.
    if($code!==0)throw new RuntimeException('child_failed');
}

try {
    if(($argv[1]??'')==='--self-test'){
        if(!intakeTestMarker('<div class="zusatztext"><p>Commentaire</p><p>test parcour</p></div>'))throw new RuntimeException('marker_failed');
        if(intakeTestMarker('<p>test</p><div class="zusatztext">commande réelle</div>'))throw new RuntimeException('marker_scope_failed');
        if(intakeTestMarker('<div class="zusatztext">contest</div>'))throw new RuntimeException('marker_word_failed');
        $s=['reference'=>'EXAMPLE','quantity'=>4,'mail_unit_ttc_cents'=>4603];
        if(intakeFingerprint($s,'TEST@example.invalid')!==intakeFingerprint($s,'test@example.invalid'))throw new RuntimeException('case_failed');
        if(intakeFingerprint($s,'test@example.invalid')===intakeFingerprint(array_replace($s,['quantity'=>2]),'test@example.invalid'))throw new RuntimeException('quantity_failed');
        echo "INTAKE_TEST_SELF_TEST_OK cases=5\n";exit;
    }
    $mode=$argv[1]??'--scan';
    if(!in_array($mode,['--scan','--run-tests'],true))throw new RuntimeException('invalid_mode');
    $config=json_decode(file_get_contents(VP_REVIEW_DIR.'/intake-test.json'),true,512,JSON_THROW_ON_ERROR);
    $allowed=$config['allowed_recipients']??[];$minimum=$config['minimum_uid']??0;
    if(!is_array($allowed)||!$allowed||count($allowed)>2||!is_int($minimum)||$minimum<1)throw new RuntimeException('invalid_test_configuration');
    foreach($allowed as $email)if(!is_string($email)||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('invalid_allowlist');
    if($mode==='--run-tests'&&($config['enabled']??false)!==true)throw new RuntimeException('test_intake_disabled');
    $lock=fopen(VP_REVIEW_DIR.'/intake-test.lock','c');chmod(VP_REVIEW_DIR.'/intake-test.lock',0600);
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('intake_busy');
    $db=reviewDb();
    $db->exec('CREATE TABLE IF NOT EXISTS intake_test_receipts(
      source_key TEXT PRIMARY KEY, fingerprint TEXT NOT NULL, state TEXT NOT NULL, created INTEGER NOT NULL)');
    $mailConfig=require '/home/vp_mail_config.php';
    $imap=@imap_open('{mail84.lwspanel.com:993/imap/ssl}INBOX','contact@vosgespneus.com',$mailConfig['password'],OP_READONLY,1);
    if(!$imap)throw new RuntimeException('imap_unavailable');
    try {
        $status=imap_status($imap,'{mail84.lwspanel.com:993/imap/ssl}INBOX',SA_UIDVALIDITY);
        if(!$status||!$status->uidvalidity)throw new RuntimeException('uidvalidity_required');
        if(($config['uidvalidity']??0)!==(int)$status->uidvalidity)throw new RuntimeException('mailbox_identity_changed');
        $uids=imap_search($imap,'UID '.$minimum.':* FROM camodo',SE_UID)?:[];
        $uids=array_slice(array_values(array_filter($uids,fn($uid)=>(int)$uid>=$minimum)),0,20);
        $summary=['scanned'=>0,'already_attempted'=>0,'ineligible'=>0,'recent_duplicate'=>0,'eligible'=>0,'sent_attempts'=>0,'errors'=>0];
        foreach($uids as $uid){
            $summary['scanned']++;$source='owner-test:'.$status->uidvalidity.':'.$uid;
            try {
                $parts=htmlParts($imap,(int)$uid,imap_fetchstructure($imap,(int)$uid,FT_UID));
                if(count($parts)!==1)throw new RuntimeException('single_html_required');
                $email=reviewCustomerEmail($parts[0]);
                if(!in_array(strtolower($email),array_map('strtolower',$allowed),true)||!intakeTestMarker($parts[0])){
                    $summary['ineligible']++;continue;
                }
                $selection=selectionFromHtml($parts[0]);$fingerprint=intakeFingerprint($selection,$email);
                $seen=$db->prepare('SELECT n.state FROM owner_notifications n JOIN quotes q ON q.id=n.quote_id WHERE q.source_key=?');
                $seen->execute([$source]);
                if($seen->fetchColumn()!==false){$summary['already_attempted']++;continue;}
                $record=$db->prepare('SELECT state FROM intake_test_receipts WHERE source_key=?');$record->execute([$source]);
                if($record->fetchColumn()!==false){$summary['already_attempted']++;continue;}
                $duplicate=$db->prepare('SELECT 1 FROM intake_test_receipts WHERE fingerprint=? AND created>? LIMIT 1');
                $duplicate->execute([$fingerprint,time()-900]);
                if($duplicate->fetchColumn()!==false){$summary['recent_duplicate']++;continue;}
                $summary['eligible']++;
                if($mode==='--scan')continue;
                $rate=(int)$db->query('SELECT COUNT(*) FROM owner_notifications WHERE created>'.(time()-3600))->fetchColumn();
                if($rate>=5||$summary['sent_attempts']>=2)throw new RuntimeException('test_rate_limit');
                // Journal before any send. Uncertain attempts are held for review, never automatically retried.
                $db->prepare('INSERT INTO intake_test_receipts VALUES(?,?,?,?)')->execute([$source,$fingerprint,'attempt_started',time()]);
                intakeChild('review_import.php',['--owner-test',(string)$uid]);
                $ownerPath=VP_REVIEW_DIR.'/notify-owner.json';$original=file_get_contents($ownerPath);
                $owner=json_decode($original,true,512,JSON_THROW_ON_ERROR);
                if(($owner['enabled']??null)!==false)throw new RuntimeException('manual_sender_busy');
                try {
                    file_put_contents($ownerPath,json_encode(['owner_email'=>$email,'owner_test_uid'=>(int)$uid,'enabled'=>true],JSON_THROW_ON_ERROR));
                    intakeChild('review_notify.php',['--send-owner-test',(string)$uid]);
                    $summary['sent_attempts']++;
                    $db->prepare('UPDATE intake_test_receipts SET state=? WHERE source_key=?')->execute(['transport_accepted',$source]);
                }finally{file_put_contents($ownerPath,$original);}
            }catch(Throwable $error){$summary['errors']++;}
        }
        echo 'INTAKE_TEST_RESULT '.json_encode($summary).' mode='.$mode.' payment_enabled=0'.PHP_EOL;
        if($summary['errors']>0)exit(1);
    }finally{imap_close($imap);}
}catch(Throwable $error){
    $code=preg_match('/^[a-z_]+$/D',$error->getMessage())?$error->getMessage():'intake_failed';
    fwrite(STDERR,'INTAKE_TEST_ERROR '.$code.PHP_EOL);exit(1);
}
