<?php
declare(strict_types=1);
// Private supplier input and quote drafts. This module never sends or orders.
function quote_size(string $s): ?string {
 if(!preg_match('/^(\d{3})\s*\/?\s*(\d{2})\s*R\s*(\d{2})$/i',trim($s),$m))return null;
 return $m[1].'/'.$m[2].'R'.$m[3];
}
function quote_season(string $s): ?string {
 return ['Été'=>'summer','Hiver'=>'winter','4 saisons'=>'allseason','4 Saisons'=>'allseason','summer'=>'summer','winter'=>'winter','allseason'=>'allseason'][$s]??null;
}
function quote_prepare(array $p,array $feed,int $now): array {
 $result=['status'=>'draft_only','customer_message_sent'=>false,'orders_enabled'=>false,'offers'=>[]];
 if(($feed['schema']??null)!==1||!is_int($feed['observed_at']??null)||$feed['observed_at']>$now+60||$now-$feed['observed_at']>3600||!is_array($feed['offers']??null)){
  $result['status']='missing_or_stale_supplier_feed';return $result;
 }
 $size=quote_size((string)($p['size']??''));$season=quote_season((string)($p['season']??''));
 if(!$size||!$season){$result['status']='request_needs_review';return $result;}
 foreach($feed['offers'] as $o){
  if(!is_array($o)||($o['size']??null)!==$size||($o['season']??null)!==$season)continue;
  $price=$o['purchase_cents']??null;$basis=$o['price_basis']??null;
  if(!is_int($price)||$price<=0||$price>10000000||!in_array($basis,['PA_HT','PA_TTC'],true))continue;
  if($basis==='PA_HT'&&($o['vat_basis_points']??null)!==2000)continue;
  $purchase=$basis==='PA_HT'?intdiv($price*12000+5000,10000):$price;
  $quantity=$p['quantity']??null;$diameter=(int)substr($size,-2);
  if($diameter<13||$diameter>30)continue;
  $fitting=($p['service']??'')==='Pneus + montage';
  $unit=lead_total($purchase,1,$diameter,$fitting,0);
  $blocks=['validation humaine avant envoi'];
  if(empty($p['indices_confirmed']))$blocks[]='indices client à confirmer';
  // A supplier index is not proof of compatibility with this vehicle.
  if(!is_int($quantity)||$quantity<1||$quantity>20){$quantity=null;$blocks[]='quantité exacte';}
  $shipping=$o['shipping_total_cents']??null;
  if(!is_int($shipping)||$shipping<0){$shipping=null;$blocks[]='livraison à confirmer';}
  $stock=$o['stock_quantity']??null;
  if(!is_int($stock)||$stock<1||($quantity!==null&&$stock<$quantity))$blocks[]='stock suffisant à confirmer';
  if(empty($o['delivery_verified']))$blocks[]='délai à confirmer';
  if(($feed['source_kind']??'')!=='supplier_feed')$blocks[]='relevé manuel, actualisation fournisseur à confirmer';
  $result['offers'][]=['brand'=>(string)($o['brand']??''),'model'=>(string)($o['model']??''),'supplier_indices'=>(string)($o['indices']??''),'purchase_ttc_cents'=>$purchase,'margin_cents'=>500,'fitting_cents'=>$unit-$purchase-500,'unit_ttc_cents'=>$unit,'quantity'=>$quantity,'subtotal_before_shipping_cents'=>$quantity===null?null:$unit*$quantity,'shipping_total_cents'=>$shipping,'total_ttc_cents'=>$quantity===null||$shipping===null?null:$unit*$quantity+$shipping,'blocked_by'=>$blocks];
 }
 if(!$result['offers'])$result['status']='no_matching_supplier_offer';
 $result['supplier_observed_at']=$feed['observed_at'];return $result;
}
function quote_run(PDO $d): array {
 $path=__DIR__.'/vp_supplier_feed.json';$feed=[];
 if(is_file($path)&&filesize($path)<=1048576){$feed=json_decode((string)file_get_contents($path),true)??[];if(!is_array($feed))$feed=[];}
 $drafts=[];$offers=0;$now=time();
 foreach(lead_pending($d) as $r){$p=json_decode($r['payload'],true);$draft=quote_prepare(is_array($p)?$p:[],$feed,$now);$drafts[$r['event_id']]=$draft;$offers+=count($draft['offers']);}
 $out=['generated_at'=>$now,'automatic_collection_enabled'=>false,'customer_sending_enabled'=>false,'drafts'=>$drafts];
 $tmp=__DIR__.'/vp_lead_quotes.json.new';
 if(file_put_contents($tmp,json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false||!rename($tmp,__DIR__.'/vp_lead_quotes.json'))throw new RuntimeException('QUOTE_WRITE');
 return ['drafts'=>count($drafts),'offers'=>$offers,'automatic_collection_enabled'=>false];
}
function quote_test(): void {
 $p=['size'=>'22545R17','season'=>'4 saisons','quantity'=>2,'service'=>'Pneus + montage'];
 $o=['size'=>'225/45R17','season'=>'allseason','purchase_cents'=>6640,'price_basis'=>'PA_HT','vat_basis_points'=>2000];
 $f=['schema'=>1,'observed_at'=>10000,'source_kind'=>'manual_browser','offers'=>[$o]];
 $r=quote_prepare($p,$f,10001);$a=$r['offers'][0]??[];
 if(($a['purchase_ttc_cents']??0)!==7968||$a['unit_ttc_cents']!==10268||$a['subtotal_before_shipping_cents']!==20536||$a['total_ttc_cents']!==null||count($a['blocked_by'])<5)throw new Exception('QUOTE_HT_OR_BLOCKS');
 $f['offers'][0]['shipping_total_cents']=900;
 if(quote_prepare($p,$f,10001)['offers'][0]['total_ttc_cents']!==21436)throw new Exception('QUOTE_SHIPPING_ONCE');
 $f['offers'][0]['price_basis']='PV_TTC';if(quote_prepare($p,$f,10001)['offers'])throw new Exception('QUOTE_PV_REJECT');
 $f['offers'][0]=$o;$p['quantity']=null;if(quote_prepare($p,$f,10001)['offers'][0]['subtotal_before_shipping_cents']!==null)throw new Exception('QUOTE_QUANTITY');
 if(quote_prepare($p,$f,13601)['status']!=='missing_or_stale_supplier_feed')throw new Exception('QUOTE_STALE');
 $p['size']='215/55R16';if(quote_prepare($p,$f,10001)['offers'])throw new Exception('QUOTE_MATCH');
 echo "QUOTE_SELFTEST_OK HT shipping_once unknown_quantity stale_price PV_rejected matching no_external_calls\n";
}
