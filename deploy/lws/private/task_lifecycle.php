<?php
declare(strict_types=1);

final class TaskLifecycle {
    public static function complete(PDO $db,string $taskId,int $actionId,string $proof): void {
        $q=$db->prepare("UPDATE vp_actions SET status='done', result_reference=?, locked_at=NULL WHERE action_id=?");
        $q->execute([$proof,$actionId]);
        $q=$db->prepare("UPDATE vp_tasks SET status='termine' WHERE task_id=?");
        $q->execute([$taskId]);
    }

    public static function fail(PDO $db,string $taskId,int $actionId,string $reason,bool $permanent=false): void {
        if($permanent){
            $q=$db->prepare("UPDATE vp_actions SET status='failed', last_error=?, locked_at=NULL WHERE action_id=?");
            $q->execute([$reason,$actionId]);
            $q=$db->prepare("INSERT IGNORE INTO vp_dead_letters(action_id,task_id,reason) VALUES (?,?,?)");
            $q->execute([$actionId,$taskId,$reason]);
            $q=$db->prepare("UPDATE vp_tasks SET status='bloque' WHERE task_id=?");
            $q->execute([$taskId]);
            return;
        }
        $q=$db->prepare("UPDATE vp_actions SET status='retry', last_error=?, locked_at=NULL,
             run_after=(CURRENT_TIMESTAMP + INTERVAL 5 MINUTE) WHERE action_id=?");
        $q->execute([$reason,$actionId]);
    }
}
