<?php
declare(strict_types=1);
require_once __DIR__.'/supervisor_decision_guard.php';
require_once __DIR__.'/task_lifecycle.php';

final class AIDecisionDispatcher
{
    private const ACTIONS=[
      'accueil'=>'triage',
      'telephone'=>'handle_call',
      'planning'=>'check_appointment',
      'produits'=>'build_product_candidate',
      'stock'=>'inspect_stock',
      'marketing'=>'prepare_campaign',
      'communication'=>'prepare_media',
      'gestion'=>'inspect_management',
      'supervision'=>'inspect_supervision'
    ];

    public static function dispatch(PDO $pdo,string $taskId,array $decision): array
    {
        $decision=SupervisorDecisionGuard::enforce($decision);
        $agent=(string)$decision['agent'];
        $action=self::ACTIONS[$agent] ?? null;
        if ($action===null) throw new DomainException('unsupported_agent');

        $mode=(string)$decision['action_mode'];
        $requestedStatus=$mode==='approval_required' ? 'blocked' : 'pending';

        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare("SELECT status FROM vp_tasks WHERE task_id=:task FOR UPDATE");
            $q->execute([':task'=>$taskId]);
            $task=$q->fetch(PDO::FETCH_ASSOC);
            if (!$task) throw new DomainException('task_not_found');

            // Terminal tasks are immutable: a replayed AI decision must not reopen them.
            if ((string)$task['status']==='termine') {
                $pdo->commit();
                return ['task_id'=>$taskId,'agent'=>$agent,'action'=>$action,'status'=>'done','mode'=>$mode,'replayed'=>true];
            }

            $ins=$pdo->prepare(
              "INSERT IGNORE INTO vp_actions(task_id,agent,action_type,status,max_attempts)
               VALUES(:task,:agent,:action,:status,3)"
            );
            $ins->execute([':task'=>$taskId,':agent'=>$agent,':action'=>$action,':status'=>$requestedStatus]);

            // Always read the persisted action after INSERT IGNORE. On replay, the
            // existing state is authoritative and must never be overwritten.
            $q=$pdo->prepare(
              "SELECT action_id,status FROM vp_actions
               WHERE task_id=:task AND agent=:agent AND action_type=:action
               FOR UPDATE"
            );
            $q->execute([':task'=>$taskId,':agent'=>$agent,':action'=>$action]);
            $persisted=$q->fetch(PDO::FETCH_ASSOC);
            if(!$persisted) throw new RuntimeException('action_persistence_failed');

            TaskLifecycle::refreshTaskStatus($pdo,$taskId);
            $pdo->commit();

            return [
              'task_id'=>$taskId,
              'agent'=>$agent,
              'action'=>$action,
              'action_id'=>(int)$persisted['action_id'],
              'status'=>(string)$persisted['status'],
              'mode'=>$mode,
              'replayed'=>$ins->rowCount()===0
            ];
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
