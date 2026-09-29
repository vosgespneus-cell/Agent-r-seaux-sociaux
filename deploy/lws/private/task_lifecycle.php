<?php
declare(strict_types=1);

final class TaskLifecycle {
    public static function complete(PDO $db,string $taskId,int $actionId,string $proof): void {
        if(trim($proof)==='') throw new DomainException('proof_required');
        $db->beginTransaction();
        try {
            $q=$db->prepare("UPDATE vp_actions SET status='done', result_reference=?, locked_at=NULL WHERE action_id=? AND task_id=? AND status='running'");
            $q->execute([$proof,$actionId,$taskId]);
            if($q->rowCount()!==1) throw new DomainException('action_state_conflict');

            $q=$db->prepare("SELECT COUNT(*) FROM vp_actions WHERE task_id=? AND status NOT IN ('done')");
            $q->execute([$taskId]);
            $remaining=(int)$q->fetchColumn();

            if($remaining===0){
                $q=$db->prepare("UPDATE vp_tasks SET status='termine' WHERE task_id=?");
                $q->execute([$taskId]);
            } else {
                $q=$db->prepare("SELECT COUNT(*) FROM vp_actions WHERE task_id=? AND status IN ('failed','blocked')");
                $q->execute([$taskId]);
                $blocked=(int)$q->fetchColumn();
                $status=$blocked>0 ? 'a_verifier' : 'en_cours';
                $q=$db->prepare("UPDATE vp_tasks SET status=? WHERE task_id=? AND status<>'termine'");
                $q->execute([$status,$taskId]);
            }
            $db->commit();
        } catch(Throwable $e){
            if($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function fail(PDO $db,string $taskId,int $actionId,string $reason,bool $permanent=false): void {
        $reason=substr(trim($reason),0,1000);
        if($reason==='') $reason='unspecified_error';
        if($permanent){
            $q=$db->prepare("UPDATE vp_actions SET status='failed', last_error=?, locked_at=NULL WHERE action_id=? AND task_id=?");
            $q->execute([$reason,$actionId,$taskId]);
            $q=$db->prepare("INSERT IGNORE INTO vp_dead_letters(action_id,task_id,reason) VALUES (?,?,?)");
            $q->execute([$actionId,$taskId,$reason]);
            $q=$db->prepare("UPDATE vp_tasks SET status='bloque' WHERE task_id=?");
            $q->execute([$taskId]);
            return;
        }

        $q=$db->prepare("SELECT attempts,max_attempts FROM vp_actions WHERE action_id=? AND task_id=?");
        $q->execute([$actionId,$taskId]);
        $a=$q->fetch(PDO::FETCH_ASSOC);
        if(!$a) throw new DomainException('action_not_found');

        if((int)$a['attempts'] >= (int)$a['max_attempts']){
            self::fail($db,$taskId,$actionId,$reason,true);
            return;
        }
        $delay=min(3600,60*(2 ** max(0,(int)$a['attempts']-1)));
        $q=$db->prepare("UPDATE vp_actions SET status='retry', last_error=?, locked_at=NULL,
          run_after=(CURRENT_TIMESTAMP + INTERVAL ? SECOND) WHERE action_id=? AND task_id=?");
        $q->execute([$reason,$delay,$actionId,$taskId]);
    }
}
