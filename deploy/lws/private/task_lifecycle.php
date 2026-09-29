<?php
declare(strict_types=1);

final class TaskLifecycle {
    public static function complete(PDO $db,string $taskId,int $actionId,string $proof): void {
        if(trim($proof)==='') throw new DomainException('proof_required');
        $db->beginTransaction();
        try {
            $q=$db->prepare("UPDATE vp_actions SET status='done',result_reference=?,locked_at=NULL,last_error=NULL WHERE action_id=? AND task_id=? AND status IN ('running','waiting')");
            $q->execute([$proof,$actionId,$taskId]);
            if($q->rowCount()!==1) throw new DomainException('action_state_conflict');
            self::refreshTaskStatus($db,$taskId);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function fail(PDO $db,string $taskId,int $actionId,string $reason,bool $permanent=false): void {
        $reason=substr(trim($reason),0,255); if($reason==='')$reason='unspecified_error';
        $db->beginTransaction();
        try{
            $q=$db->prepare("SELECT attempts,max_attempts FROM vp_actions WHERE action_id=? AND task_id=? FOR UPDATE");
            $q->execute([$actionId,$taskId]);$a=$q->fetch(PDO::FETCH_ASSOC);
            if(!$a)throw new DomainException('action_not_found');
            if($permanent || (int)$a['attempts'] >= (int)$a['max_attempts']){
                $q=$db->prepare("UPDATE vp_actions SET status='failed',last_error=?,locked_at=NULL WHERE action_id=? AND task_id=?");
                $q->execute([$reason,$actionId,$taskId]);
                $q=$db->prepare("INSERT IGNORE INTO vp_dead_letters(action_id,task_id,reason) VALUES(?,?,?)");
                $q->execute([$actionId,$taskId,$reason]);
            }else{
                $delay=min(3600,60*(2 ** max(0,(int)$a['attempts']-1)));
                $runAfter=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s');
                $q=$db->prepare("UPDATE vp_actions SET status='retry',last_error=?,locked_at=NULL,run_after=? WHERE action_id=? AND task_id=?");
                $q->execute([$reason,$runAfter,$actionId,$taskId]);
            }
            self::refreshTaskStatus($db,$taskId);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function refreshTaskStatus(PDO $db,string $taskId): void {
        $q=$db->prepare("SELECT status,COUNT(*) n FROM vp_actions WHERE task_id=? GROUP BY status");
        $q->execute([$taskId]);$counts=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$counts[$r['status']]=(int)$r['n'];
        $total=array_sum($counts); if($total===0)return;
        if(($counts['failed']??0)>0){$status='bloque';}
        elseif(($counts['blocked']??0)>0){$status='a_verifier';}
        elseif(($counts['done']??0)===$total){$status='termine';}
        else{$status='en_cours';}
        $u=$db->prepare("UPDATE vp_tasks SET status=? WHERE task_id=?");$u->execute([$status,$taskId]);
    }
}
