<?php
declare(strict_types=1);

final class ShopifyProofVerifier {
    public static function verifyDraft(array $expected, array $actual): array {
        $checks=[
            'id_present'=>str_starts_with((string)($actual['id']??''),'gid://shopify/Product/'),
            'draft_status'=>strtoupper((string)($actual['status']??''))==='DRAFT',
            'title_match'=>trim((string)($actual['title']??''))===trim((string)($expected['title']??'')),
        ];
        if(isset($expected['sku'])){
            $variants=(array)($actual['variants']??[]);
            $skus=array_map(static fn($v)=>(string)($v['sku']??''),$variants);
            $checks['sku_match']=in_array((string)$expected['sku'],$skus,true);
        }
        if(isset($expected['price'])){
            $variants=(array)($actual['variants']??[]);
            $prices=array_map(static fn($v)=>number_format((float)($v['price']??0),2,'.',''),$variants);
            $checks['price_match']=in_array(number_format((float)$expected['price'],2,'.',''),$prices,true);
        }
        return ['verified'=>!in_array(false,$checks,true),'checks'=>$checks,'product_id'=>(string)($actual['id']??'')];
    }
}
