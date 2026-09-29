<?php
declare(strict_types=1);

final class NeedsInformation
{
    public static function normalize(array $fields): array
    {
        $allowed=['service','requested_date','requested_time_window','tire_size','quantity','vehicle','part_reference','price','source_reference'];
        $out=[];
        foreach($fields as $field){
            if(is_string($field) && in_array($field,$allowed,true)) $out[]=$field;
        }
        return array_values(array_unique($out));
    }

    public static function publicPrompt(array $fields): string
    {
        $labels=[
          'service'=>'le service souhaité',
          'requested_date'=>'la date souhaitée',
          'requested_time_window'=>'le créneau horaire souhaité',
          'tire_size'=>'la dimension exacte des pneus',
          'quantity'=>'la quantité',
          'vehicle'=>'le véhicule',
          'part_reference'=>'la référence de la pièce',
          'price'=>'le prix validé',
          'source_reference'=>'la référence source'
        ];
        $parts=[];
        foreach(self::normalize($fields) as $field) $parts[]=$labels[$field];
        if(!$parts) return '';
        return 'Il me manque '.implode(', ',$parts).'.';
    }
}
