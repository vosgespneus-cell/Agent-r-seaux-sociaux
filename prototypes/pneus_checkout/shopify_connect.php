<?php
declare(strict_types=1);
/** One-use HTTPS credential bootstrap, deployed under a random filename.
 * Runtime state and credentials live outside the web root, never in this repo.
 * Establishes API authentication only. No order, payment or email action.
 */
ini_set('display_errors','0'); error_reporting(0); umask(0077);
const VP_CONNECT_DIR = '/home/vp_pneus_connection';
function vpWriteJson(string $path, array $value): void {
    $tmp = $path.'.tmp.'.bin2hex(random_bytes(8));
    $body = json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    if (file_put_contents($tmp,$body,LOCK_EX) !== strlen($body)) throw new RuntimeException('storage_failed');
    chmod($tmp,0600);
    if (!rename($tmp,$path)) throw new RuntimeException('storage_failed');
}
function vpConnectHttp(string $url, array $values): array {
    $body=''; $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($values),
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body){
            if(strlen($body)+strlen($chunk)>65536)return 0;
            $body.=$chunk;return strlen($chunk);
        }]);
    $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($ok===false||$status!==200)throw new RuntimeException('shopify_authentication_failed');
    $response=json_decode($body,true,16,JSON_THROW_ON_ERROR);
    $scopes=explode(',',(string)($response['scope']??''));sort($scopes);
    if(!in_array('read_orders',$scopes,true)||!in_array('write_draft_orders',$scopes,true)||
        array_diff($scopes,['read_orders','read_draft_orders','write_draft_orders'])||
        !is_string($response['access_token']??null)||strlen($response['access_token'])<20||
        !is_int($response['expires_in']??null)||$response['expires_in']<600||$response['expires_in']>86400){
        throw new RuntimeException('unexpected_permissions');
    }
    return $response;
}
if(PHP_SAPI==='cli') { echo "WEB_BOOTSTRAP_ONLY\n";exit; }
header('Cache-Control: no-store');header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$bootstrap=VP_CONNECT_DIR.'/bootstrap.json';
$cfg=is_file($bootstrap)?json_decode((string)file_get_contents($bootstrap),true):null;
if(!is_array($cfg)||($_SERVER['HTTP_HOST']??'')!=='agents.vosgespneus.com'||
    empty($_SERVER['HTTPS'])||strtolower((string)$_SERVER['HTTPS'])==='off'||
    basename(__FILE__)!==($cfg['setup_file']??'')||time()>($cfg['expires_at']??0)){
    http_response_code(404);exit;
}
session_name('vp_pneus_connect');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
$message='';$success=is_file(VP_CONNECT_DIR.'/credentials.json');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&!$success){
    $lock=fopen(VP_CONNECT_DIR.'/connect.lock','c');
    if(!$lock||!flock($lock,LOCK_EX)){http_response_code(503);exit;}
    try{
        $cfg=json_decode((string)file_get_contents($bootstrap),true,16,JSON_THROW_ON_ERROR);
        if(is_file(VP_CONNECT_DIR.'/credentials.json'))throw new RuntimeException('already_connected');
        if(time()>($cfg['expires_at']??0)||($cfg['attempts']??0)>=5)throw new RuntimeException('setup_expired');
        if(($_SERVER['HTTP_ORIGIN']??'')!=='https://agents.vosgespneus.com'||
            !is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){
            throw new RuntimeException('invalid_request');
        }
        $secret=$_POST['client_secret']??'';
        if(!is_string($secret)||!preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$secret)){
            throw new RuntimeException('invalid_secret_format');
        }
        $cfg['attempts']=($cfg['attempts']??0)+1;vpWriteJson($bootstrap,$cfg);
        if(!preg_match('/^[a-z0-9-]+\.myshopify\.com$/D',$cfg['shop']??''))throw new RuntimeException('invalid_shop');
        $token=vpConnectHttp('https://'.$cfg['shop'].'/admin/oauth/access_token',[
            'grant_type'=>'client_credentials','client_id'=>$cfg['client_id'],'client_secret'=>$secret]);
        vpWriteJson(VP_CONNECT_DIR.'/credentials.json',[
            'shop'=>$cfg['shop'],'client_id'=>$cfg['client_id'],'client_secret'=>$secret,
            'enabled'=>false,'created_at'=>time()]);
        vpWriteJson(VP_CONNECT_DIR.'/token.json',[
            'access_token'=>$token['access_token'],'expires_at'=>time()+$token['expires_in'],
            'scope'=>$token['scope']]);
        unset($secret,$token,$_POST['client_secret']);
        $_SESSION=[];session_destroy();$success=true;
    }catch(Throwable $e){
        $message='Connexion non confirmée. Vérifiez le secret de cette application et son installation. Aucun traitement activé.';
    }finally{if($lock){flock($lock,LOCK_UN);fclose($lock);}}
}
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion Shopify — VOSGES PNEUS</title>
<style>body{font:17px system-ui;background:#10201c;color:#f4f7f5;margin:0;padding:32px 18px}main{max-width:650px;margin:40px auto;background:#1a3029;padding:30px;border-radius:18px}h1{font-size:27px}label{display:block;margin:22px 0 8px}input,button{box-sizing:border-box;width:100%;padding:14px;border-radius:8px;border:1px solid #8baca0;font:inherit}input{background:#fff;color:#14231d}button{margin-top:24px;background:#e2b952;color:#14231d;font-weight:700;cursor:pointer}.notice{padding:16px;background:#28463a;border-radius:8px}.error{color:#ffd1c6}small{display:block;margin-top:20px;line-height:1.5}</style>
<main><h1>Connexion Shopify — commandes pneus</h1>
<?php if($success): ?><p class="notice">Connexion Shopify confirmée. Les identifiants sont enregistrés sur votre serveur. Le traitement automatique et le paiement restent désactivés pendant les tests.</p>
<?php else: ?><p>Application : <strong>VOSGES PNEUS - commandes pneus</strong></p>
<p>Copiez le secret client de cette application depuis le tableau de bord Shopify, puis saisissez-le ici.</p>
<?php if($message): ?><p class="error"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></p><?php endif; ?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8')?>">
<label for="client_id">ID client de l’application</label><input id="client_id" type="text" readonly value="<?=htmlspecialchars($cfg['client_id'],ENT_QUOTES,'UTF-8')?>">
<label for="client_secret">Secret client Shopify</label><input id="client_secret" name="client_secret" type="password" autocomplete="current-password" required minlength="20" maxlength="200">
<button type="submit">Connecter Shopify</button></form>
<small>Votre serveur utilise ce secret pour s’authentifier auprès de Shopify. Il est conservé hors du dossier public. Aucun achat, paiement ou envoi client n’est effectué par cette connexion.</small>
<?php endif; ?></main></html>
