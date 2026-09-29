<?php
declare(strict_types=1);

final class ProductDraftValidator {
    public static function validate(array $p): array {
        $errors=[];
        $title=trim((string)($p['title']??''));
        $description=trim((string)($p['description']??''));
        $source=trim((string)($p['source_reference']??''));
        $price=$p['price']??null;

        if ($title==='' || mb_strlen($title)>180) $errors[]='invalid_title';
        if ($description==='') $errors[]='missing_description';
        if (!is_numeric($price) || (float)$price<=0) $errors[]='invalid_price';
        if ($source==='') $errors[]='missing_source_reference';
        if (!empty($p['publish'])) $errors[]='publication_not_allowed';
        if (isset($p['inventory']) && (int)$p['inventory']<0) $errors[]='invalid_inventory';

        return [
          'valid'=>$errors===[],
          'errors'=>$errors,
          'normalized'=>[
            'title'=>$title,
            'description'=>$description,
            'price'=>is_numeric($price)?number_format((float)$price,2,'.',''):null,
            'vendor'=>trim((string)($p['vendor']??'VOSGES PNEUS')),
            'product_type'=>trim((string)($p['product_type']??'')),
            'tags'=>array_values(array_unique(array_filter(array_map('trim',(array)($p['tags']??[]))))),
            'source_reference'=>$source,
            'status'=>'DRAFT'
          ]
        ];
    }
}
