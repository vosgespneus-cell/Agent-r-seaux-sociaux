<?php
declare(strict_types=1);
/** Validate provenance recorded by the receiving LWS mail server. Never trust From alone. */
function vpCamodoMailAuthenticated(string $raw): bool {
    if (strlen($raw)>131072 || str_contains($raw,"\0")) return false;
    $raw=preg_replace("/\r?\n[ \t]+/"," ",$raw);
    $fields=[];
    foreach(preg_split("/\r?\n/",$raw) as $line){
        if($line==='')break;
        if(!preg_match('/^([A-Za-z0-9-]+):[ \t]*(.*)$/D',$line,$m))return false;
        $fields[strtolower($m[1])][]=$m[2];
    }
    foreach(['from','to','x-envelope-from'] as $name)if(count($fields[$name]??[])!==1)return false;
    if(strtolower(trim($fields['from'][0]))!=='noreply@camodo.de')return false;
    if(strtolower(trim($fields['to'][0]))!=='contact@vosgespneus.com')return false;
    if(strtolower(trim($fields['x-envelope-from'][0]))!=='<noreply@camodo.de>')return false;
    // LWS prepends its receiving hop. A sender-injected Received below it cannot satisfy this.
    $hops=$fields['received']??[];
    if(count($hops)<2)return false;
    if(!preg_match('/^from mail84\.lwspanel\.com \(localhost\.localdomain \[127\.0\.0\.1\]\) by filter[0-9]+\.mynetwork\.local /i',$hops[0]))return false;
    if(!preg_match('/^from [^ ]+ \([^\r\n]*\[([0-9.]+)\]\) by mail84\.lwspanel\.com \(Postfix\) with ESMTPS? .* for <contact@vosgespneus\.com>;/i',$hops[1],$hop))return false;
    if(!filter_var($hop[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
    $spf=$fields['received-spf'][0]??'';
    if(!preg_match('/^Pass \(mailfrom\) /i',$spf))return false;
    if(!str_contains(strtolower($spf),'envelope-from=noreply@camodo.de;'))return false;
    if(!preg_match('/client-ip=([0-9.]+);/i',$spf,$ip)||$ip[1]!==$hop[1])return false;
    $found=false;
    foreach($fields['authentication-results']??[] as $auth){
        if(!preg_match('/^mail84\.lwspanel\.com;/i',$auth))continue;
        if(!preg_match('/(?:^|;)\s*spf=pass\b[^;]*\bsmtp\.mailfrom=noreply@camodo\.de(?:;|$)/i',$auth))return false;
        $found=true;
    }
    return $found;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--self-test')exit(2);
    $f="From: noreply@camodo.de\r\nTo: contact@vosgespneus.com\r\nReceived: from mail84.lwspanel.com (localhost.localdomain [127.0.0.1]) by filter2.mynetwork.local (Postfix) with ESMTP id ABC for <contact@vosgespneus.com>; Thu, 29 Oct 2026 10:39:39 +0100\r\nReceived: from vmail-posthorn.telesoft.biz (vmail-posthorn.telesoft.biz [91.215.74.203]) by mail84.lwspanel.com (Postfix) with ESMTPS id ABC for <contact@vosgespneus.com>; Thu, 29 Oct 2026 10:39:30 +0100\r\nReceived-SPF: Pass (mailfrom) identity=mailfrom; client-ip=91.215.74.203; helo=example; envelope-from=noreply@camodo.de; receiver=vosgespneus.com\r\nX-Envelope-From: <noreply@camodo.de>\r\nAuthentication-Results: mail84.lwspanel.com; dkim=none; spf=pass (checked) smtp.mailfrom=noreply@camodo.de; dmarc=none\r\n";
    if(!vpCamodoMailAuthenticated($f))throw new RuntimeException('valid_fixture_rejected');
    $bad=[
      str_replace('From: noreply@camodo.de','From: attacker@example.invalid',$f),
      str_replace('spf=pass','spf=fail',$f),
      str_replace('Pass (mailfrom)','Fail (mailfrom)',$f),
      str_replace('client-ip=91.215.74.203','client-ip=91.215.74.204',$f),
      str_replace('by mail84.lwspanel.com','by attacker.example',$f),
      "From: noreply@camodo.de\r\n".$f,
      str_replace('X-Envelope-From: <noreply@camodo.de>','X-Envelope-From: <attacker@example.invalid>',$f),
      str_replace('To: contact@vosgespneus.com','To: other@example.invalid',$f),
      str_replace('by filter2.mynetwork.local','by attacker.example',$f),
      $f."Authentication-Results: mail84.lwspanel.com; spf=fail smtp.mailfrom=noreply@camodo.de\r\n"
    ];
    foreach($bad as $i=>$v)if(vpCamodoMailAuthenticated($v))throw new RuntimeException('spoof_accepted_'.$i);
    echo "CAMODO_MAIL_AUTH_OK cases=11 no_network=1 no_database=1\n";
}
