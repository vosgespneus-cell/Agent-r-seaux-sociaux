<?php
declare(strict_types=1);

final class ProductIntake {
    public static function fromEvent(string $taskId,string $source,string $rawBody): array {
        $data=json_decode($rawBody,true,32,JSON_THROW_ON_ERROR);
        if(($data['kind']??'')!=='produit') throw new InvalidArgumentException('not a product event');
        $payload=is_array($data['payload']??null)?$data['payload']:[];
        $sourceRef=trim((string)($payload['source_reference']??$data['event_id']??''));
        if($sourceRef==='') throw new InvalidArgumentException('missing source reference');

        $media=[];
        foreach((array)($payload['media']??[]) as $item){
            if(is_string($item) && preg_match('#^https://#i',$item)) $media[]=$item;
            if(count($media)>=12) break;
        }
        return [
          'task_id'=>$taskId,
          'source'=>$source,
          'source_reference'=>$sourceRef,
          'reference_text'=>mb_substr(trim((string)($payload['reference_text']??'')),0,255),
          'notes'=>mb_substr(trim((string)($payload['notes']??'')),0,5000),
          'media'=>$media,
        ];
    }

    public static function save(PDO $db,array $i): void {
        $q=$db->prepare("INSERT INTO vp_product_intake
          (task_id,source,source_reference,reference_text,notes,media_json)
          VALUES (?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE updated_at=CURRENT_TIMESTAMP");
        $q->execute([
          $i['task_id'],$i['source'],$i['source_reference'],$i['reference_text'],$i['notes'],
          json_encode($i['media'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)
        ]);
    }
}
