<?php
declare(strict_types=1);

final class ShopifyAdminClient {
    private string $shop;
    private string $token;
    private string $version;

    public function __construct(array $config) {
        $this->shop=(string)($config['shop_domain']??'');
        $this->token=(string)($config['admin_access_token']??'');
        $this->version=(string)($config['api_version']??'2026-07');
        if(!preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/',$this->shop)) throw new RuntimeException('invalid shop domain');
        if(strlen($this->token)<20 || str_starts_with($this->token,'REPLACE_')) throw new RuntimeException('Shopify not configured');
    }

    private function graphQL(string $query,array $variables=[]): array {
        $url='https://'.$this->shop.'/admin/api/'.$this->version.'/graphql.json';
        $ch=curl_init($url);
        curl_setopt_array($ch,[
          CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
          CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>25,
          CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Shopify-Access-Token: '.$this->token],
          CURLOPT_POSTFIELDS=>json_encode(['query'=>$query,'variables'=>$variables],JSON_THROW_ON_ERROR)
        ]);
        $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if(!is_string($raw) || strlen($raw)>2_000_000) throw new RuntimeException('Shopify transport error');
        if($status===429 || $status>=500) throw new RuntimeException('Shopify temporary error');
        if($status<200 || $status>=300) throw new DomainException('Shopify request rejected');
        $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!empty($data['errors'])) throw new DomainException('Shopify GraphQL error');
        return $data['data']??[];
    }

    public function createDraft(array $draft): array {
        $product=[
          'title'=>(string)$draft['title'],
          'descriptionHtml'=>(string)$draft['description'],
          'vendor'=>(string)($draft['vendor']??'VOSGES PNEUS'),
          'productType'=>(string)($draft['product_type']??''),
          'tags'=>array_values((array)($draft['tags']??[])),
          'status'=>'DRAFT'
        ];
        $q='mutation CreateDraft($product: ProductCreateInput!) { productCreate(product:$product) { product { id title handle status vendor productType tags } userErrors { field message } } }';
        $d=$this->graphQL($q,['product'=>$product]);
        $r=$d['productCreate']??[];
        if(!empty($r['userErrors'])) throw new DomainException('Shopify product validation failed');
        if(!is_array($r['product']??null)) throw new RuntimeException('Shopify product missing');
        return $r['product'];
    }

    public function readProduct(string $id): array {
        $q='query Verify($id:ID!){ product(id:$id){ id title handle status vendor productType tags } }';
        $d=$this->graphQL($q,['id'=>$id]);
        if(!is_array($d['product']??null)) throw new RuntimeException('Shopify product not found');
        return $d['product'];
    }
}
