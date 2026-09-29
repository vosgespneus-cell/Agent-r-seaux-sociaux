<?php
declare(strict_types=1);

require_once __DIR__.'/product_candidate.php';
require_once __DIR__.'/product_draft_validator.php';

final class ProductOrchestrator {
    public static function buildCandidate(array $intake,array $facts): array {
        $references=array_values(array_unique(array_filter(array_map(
            static fn($v)=>trim((string)$v),
            (array)($facts['references']??[])
        ))));

        $candidate=[
          'decision'=>'needs_information',
          'source_reference'=>trim((string)($intake['source_reference']??'')),
          'title'=>trim((string)($facts['title']??'')),
          'description'=>trim((string)($facts['description']??'')),
          'vendor'=>trim((string)($facts['vendor']??'VOSGES PNEUS')),
          'product_type'=>trim((string)($facts['product_type']??'')),
          'price'=>$facts['price']??null,
          'stock'=>$facts['stock']??null,
          'sku'=>isset($facts['sku'])?trim((string)$facts['sku']):null,
          'tags'=>array_values(array_unique(array_filter((array)($facts['tags']??[])))),
          'references'=>$references,
          'compatibilities'=>array_values(array_unique(array_filter((array)($facts['compatibilities']??[])))),
          'facts'=>array_values(array_filter((array)($facts['facts']??[]))),
          'uncertainties'=>array_values(array_filter((array)($facts['uncertainties']??[]))),
          'missing_fields'=>[],
          'shopify_status'=>'DRAFT'
        ];

        foreach(['source_reference','title','description'] as $field){
            if(trim((string)($candidate[$field]??''))==='') $candidate['missing_fields'][]=$field;
        }
        if(!is_numeric($candidate['price']) || (float)$candidate['price']<=0) $candidate['missing_fields'][]='price';

        if(empty($candidate['missing_fields'])) $candidate['decision']='ready';
        return $candidate;
    }

    public static function prepareDraft(array $candidate): array {
        if(!ProductCandidate::isReady($candidate)){
            return ['ready'=>false,'reason'=>'candidate_incomplete','candidate'=>$candidate];
        }
        $validation=ProductDraftValidator::validate([
          'title'=>$candidate['title'],
          'description'=>$candidate['description'],
          'price'=>$candidate['price'],
          'vendor'=>$candidate['vendor'],
          'product_type'=>$candidate['product_type'],
          'tags'=>$candidate['tags'],
          'source_reference'=>$candidate['source_reference']
        ]);
        return [
          'ready'=>$validation['valid'],
          'fingerprint'=>ProductCandidate::fingerprint($candidate),
          'draft'=>$validation['normalized'],
          'errors'=>$validation['errors']
        ];
    }
}
