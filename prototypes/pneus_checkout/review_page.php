<?php
declare(strict_types=1);
ini_set('display_errors','0');error_reporting(0);umask(0077);
require '/home/vp_pneus_review/review_lib.php';
if(($_SERVER['HTTP_HOST']??'')!=='agents.vosgespneus.com'||empty($_SERVER['HTTPS'])||strtolower((string)$_SERVER['HTTPS'])==='off'){
    http_response_code(404);exit;
}
$nonce=base64_encode(random_bytes(24));
header('Cache-Control: no-store');header('Referrer-Policy: same-origin');header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-$nonce'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
ini_set('session.use_strict_mode','1');session_name('vp_pneus_review');session_save_path(VP_REVIEW_DIR.'/sessions');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/pneus-essai.php']);session_start();
$quote=null;$error='';$db=reviewDb();
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(($_SERVER['HTTP_ORIGIN']??'')!=='https://agents.vosgespneus.com'||(int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(403);exit;}
    if(($_POST['action']??'')==='open'){
        $key=$_POST['key']??'';
        $bucket=hash('sha256',($_SERVER['REMOTE_ADDR']??'').':'.intdiv(time(),60));
        $limit=$db->prepare('INSERT INTO opening_limits(bucket,count) VALUES(?,1) ON CONFLICT(bucket) DO UPDATE SET count=count+1');$limit->execute([$bucket]);
        $check=$db->prepare('SELECT count FROM opening_limits WHERE bucket=?');$check->execute([$bucket]);
        if($check->fetchColumn()>30){http_response_code(429);exit;}
        if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)){http_response_code(403);exit;}
        $stmt=$db->prepare('SELECT id FROM quotes WHERE capability_hash=? AND expires>?');$stmt->execute([hash('sha256',$key),time()]);$id=$stmt->fetchColumn();
        if(!$id){http_response_code(403);exit;}
        session_regenerate_id(true);$_SESSION=['quote_id'=>$id,'csrf'=>bin2hex(random_bytes(32))];
        header('Content-Type: application/json');echo '{"opened":true}';exit;
    }
}
if(isset($_SESSION['quote_id'])){
    $stmt=$db->prepare('SELECT * FROM quotes WHERE id=? AND expires>?');$stmt->execute([$_SESSION['quote_id'],time()]);$quote=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
}
if($quote&&($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        if(($_POST['action']??'')!=='save'||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf']??'',$_POST['csrf']))throw new RuntimeException('csrf');
        $tot=reviewTotals($quote,$_POST['quantity']??'',isset($_POST['fitting'])?'1':'0',$_POST['storage_pairs']??'');
        $stmt=$db->prepare('UPDATE quotes SET quantity=?,fitting=?,storage_pairs=?,saved=1 WHERE id=? AND expires>?');
        $stmt->execute([$tot['quantity'],$tot['fitting'],$tot['storage_pairs'],$quote['id'],time()]);
        header('Location: /pneus-essai.php',true,303);exit;
    }catch(Throwable $e){$error='Les options n’ont pas été enregistrées. Vérifiez la quantité puis réessayez.';}
}
if($quote)$tot=reviewTotals($quote,(string)$quote['quantity'],(string)$quote['fitting'],(string)$quote['storage_pairs']);
$bridgeJob=null;
if($quote&&$quote['saved']){
    try{
        if($db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='shopify_bridge_jobs'")->fetchColumn()){
            $j=$db->prepare('SELECT state,order_name FROM shopify_bridge_jobs WHERE quote_id=?');
            $j->execute([$quote['id']]);$bridgeJob=$j->fetch(PDO::FETCH_ASSOC)?:null;
        }
    }catch(Throwable $ignored){}
}
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Votre sélection de pneus — VOSGES PNEUS</title>
<script nonce="<?=reviewH($nonce)?>">document.documentElement.dataset.opening=location.hash?'1':'0';</script>
<style>
#link-status{display:none}[data-opening="1"] #quote-view{display:none}[data-opening="1"] #link-status{display:block}*{box-sizing:border-box}body{margin:0;background:#f3f4ef;color:#17261f;font:16px system-ui;line-height:1.5}.top{background:#173b2d;color:white;padding:22px max(22px,calc((100vw - 1040px)/2));display:flex;justify-content:space-between;gap:20px}.brand{font-weight:850;letter-spacing:1px}.tag{color:#f7d37c;font-size:13px}.wrap{max-width:1040px;margin:30px auto;padding:0 20px}.test{background:#fff0c4;border:1px solid #e4ca76;padding:12px 16px;border-radius:10px;font-size:14px}h1{font-size:32px;line-height:1.2;margin:25px 0 10px}h2{font-size:20px;margin:0 0 18px}.sub{color:#52645b}.grid{display:grid;grid-template-columns:1.5fr 1fr;gap:22px;margin-top:25px}.card{background:white;border:1px solid #dce3dc;border-radius:16px;padding:24px;margin-bottom:18px}.tyre{font-size:23px;font-weight:750}.pill{display:inline-block;background:#edf4ee;color:#214d32;font-size:13px;padding:5px 10px;border-radius:20px;margin-bottom:12px}.option{padding:18px 0;border-top:1px solid #e1e8e1}label{font-weight:650}input[type=checkbox]{width:19px;height:19px;accent-color:#1c553a;vertical-align:middle;margin-right:9px}input[type=number],select{font:inherit;padding:10px;border:1px solid #a4b6a8;border-radius:7px;max-width:130px;margin:8px 0}select{min-width:160px}.small{font-size:13px;color:#617267;margin-top:6px}.row{display:flex;justify-content:space-between;gap:15px;margin:12px 0}.total{border-top:1px solid #d4dfd5;padding-top:20px;font-size:21px;font-weight:800}.total strong{font-size:30px}.button{border:0;background:#1c533a;color:white;font:700 16px system-ui;padding:15px 20px;border-radius:9px;width:100%;cursor:pointer}.disabled{background:#e5e8e3;color:#727b73;cursor:not-allowed;margin-top:12px}.notice{background:#eef5ed;padding:14px;border-radius:9px;font-size:14px}.success{background:#dff0e1;padding:14px 18px;border-radius:10px;margin:18px 0}.error{color:#9f2929}.steps{font-size:14px;color:#64786b;margin:10px 0 24px}@media(max-width:740px){.grid{grid-template-columns:1fr}.top{padding:18px 20px}h1{font-size:27px}.card{padding:20px}.tag{max-width:140px;text-align:right}}
</style>
<header class="top"><span class="brand">VOSGES PNEUS</span><span class="tag">Votre sélection, vos prestations</span></header>
<main class="wrap"><div class="test"><strong>ESSAI PRIVÉ</strong> — Aucun achat ni paiement. Montage, valve et recyclage chiffrés selon les tarifs confirmés ; durée du gardiennage à confirmer.</div>
<p id="link-status" class="notice" role="status">Ouverture de votre sélection…</p><section id="quote-view"><?php if(!$quote): ?><h1>Votre sélection de pneus</h1><p id="access" class="notice">Ouvrez votre lien personnel pour retrouver votre sélection. Ce lien peut avoir expiré.</p>
<?php else: ?><h1>Complétez votre sélection</h1><p class="sub">Vos pneus sont repris du devis. Choisissez les prestations qui vous conviennent.</p><div class="steps">1. Pneus sélectionnés &nbsp; · &nbsp; 2. Prestations &nbsp; · &nbsp; 3. Vérification avant paiement</div>
<?php if(($bridgeJob['state']??'')==='completed'): ?><div class="success" role="status">Votre commande de test <?=reviewH($bridgeJob['order_name'])?> est confirmée. Aucun débit réel.</div>
<?php elseif($quote['saved']): ?><div class="success" role="status">Vos options sont enregistrées pour cet essai. Aucune commande n’est passée.</div><?php endif; ?>
<?php if(($bridgeJob['state']??'')==='private_draft_stock_pending'): ?><p class="notice">Votre sélection est préparée. Nous devons confirmer la disponibilité, les frais et les modalités de remise avant tout paiement.</p><?php endif; ?>
<?php if($error): ?><p class="error" role="alert"><?=reviewH($error)?></p><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?=reviewH($_SESSION['csrf'])?>"><div class="grid"><section>
<div class="card"><span class="pill">Pneus sélectionnés</span><div class="tyre"><?=reviewH($quote['title'])?></div><p class="sub"><?=reviewMoney((int)$quote['unit_cents'])?> TTC par pneu</p><label for="qty">Quantité</label><br><input id="qty" name="quantity" type="number" min="1" max="<?=reviewH($quote['quoted_quantity'])?>" value="<?=reviewH($quote['quantity'])?>" required><div class="small">La disponibilité sera revérifiée avant tout paiement.</div></div>
<div class="card"><h2>Vos prestations</h2><div class="option"><label><input type="checkbox" name="fitting" value="1" <?=$quote['fitting']?'checked':''?>>Montage et équilibrage</label><div class="small"><?=reviewMoney($tot['fitting_bundle_rate'])?> TTC par pneu : montage <?=reviewMoney($tot['fitting_rate'])?> + valve standard 3,00 € + recyclage 2,50 €</div></div><div class="option"><label for="storage">Gardiennage</label><br><select id="storage" name="storage_pairs" aria-label="Gardiennage par paire"><?php for($i=0;$i<=12;$i++): ?><option value="<?=$i?>" <?=$quote['storage_pairs']==$i?'selected':''?>><?=$i===0?'Sans gardiennage':$i.' paire'.($i>1?'s':'')?></option><?php endfor; ?></select><div class="small">30,00 € TTC par paire pour cet essai. Période de garde à confirmer.</div></div></div></section>
<aside><div class="card"><h2>Votre récapitulatif</h2><div class="row"><span>Pneus × <?=$tot['quantity']?></span><strong><?=reviewMoney($tot['tyres'])?></strong></div><div class="row"><span>Montage et équilibrage</span><strong><?=reviewMoney($tot['fitting_total'])?></strong></div><div class="row"><span>Valves standard</span><strong><?=reviewMoney($tot['valve_total'])?></strong></div><div class="row"><span>Recyclage des pneus usagés</span><strong><?=reviewMoney($tot['recycling_total'])?></strong></div><div class="row"><span>Gardiennage</span><strong><?=reviewMoney($tot['storage_total'])?></strong></div><div class="row total"><span>Estimation TTC</span><strong><?=reviewMoney($tot['total'])?></strong></div><p class="small">Le récapitulatif est recalculé après enregistrement des options. Prix, stock et éventuels frais restent à confirmer.</p><button class="button" type="submit">Enregistrer mes options</button><button class="button disabled" type="button" disabled>Paiement indisponible pendant l’essai</button></div><p class="notice">Cet essai permet de vérifier le parcours. Vos choix ne réservent pas les pneus et ne déclenchent aucun achat.</p></aside></div></form>
<?php endif; ?></section></main><script nonce="<?=reviewH($nonce)?>">
let opening=false;
async function openPersonalSelection(){
    if(opening||!location.hash)return;
    const key=location.hash.slice(1);
    document.documentElement.dataset.opening='1';
    const status=document.getElementById('link-status');
    status.textContent='Ouverture de votre sélection…';
    history.replaceState(null,'',location.pathname);
    if(!/^[a-f0-9]{64}$/.test(key)){
        status.textContent='Ce lien personnel n’est pas valide. Utilisez le lien complet de votre message.';
        return;
    }
    opening=true;
    let accepted=false;
    try{
        const response=await fetch(location.pathname,{method:'POST',credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({action:'open',key})});
        accepted=response.ok;
        if(!accepted)status.textContent='Ce lien personnel a expiré ou n’est pas valide.';
    }catch{
        status.textContent='La sélection n’a pas pu être ouverte. Rouvrez votre lien personnel pour réessayer.';
    }
    opening=false;
    // A second link can arrive while the first request is being processed.
    // Open the latest one before reloading, without showing the old selection.
    if(location.hash)return openPersonalSelection();
    if(accepted)location.replace(location.pathname);
}
window.addEventListener('hashchange',openPersonalSelection);
openPersonalSelection();
</script></html>
