<?php
declare(strict_types=1);

final class ExceptionNotifier {
    public static function shouldNotify(array $summary): bool {
        return (int)($summary['tasks_blocked']??0)>0
            || (int)($summary['actions_failed']??0)>0
            || (int)($summary['drafts_unverified']??0)>0
            || (int)($summary['dead_letters']??0)>0
            || (int)($summary['intake_needs_information']??0)>0;
    }

    public static function message(array $s): string {
        $parts=[];
        $map=[
          'tasks_blocked'=>'tâches bloquées',
          'actions_failed'=>'actions échouées',
          'drafts_unverified'=>'brouillons Shopify non vérifiés',
          'dead_letters'=>'anomalies permanentes',
          'intake_needs_information'=>'pièces à compléter'
        ];
        foreach($map as $key=>$label){
            $n=(int)($s[$key]??0);
            if($n>0) $parts[]=$n.' '.$label;
        }
        return $parts ? 'VOSGES PNEUS — intervention requise : '.implode(', ',$parts) : '';
    }
}
