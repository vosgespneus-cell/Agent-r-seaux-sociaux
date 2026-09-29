<?php
declare(strict_types=1);
require_once __DIR__.'/supervisor_decision_guard.php';

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
        $status=$mode==='approval_required' ? 'blocked' : 'pending';

        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare("SELECT status FROM vp_tasks WHERE task_id=:task FOR UPDATE");
            $q->execute([':task'=>$taskId]);
            $task=$q->fetch(PDO::FETCH_ASSOC);
            if (!$task) throw new DomainException('task_not_found');

            $ins=$pdo->prepare(
              "INSERT IGNORE INTO vp_actions(task_id,agent,action_type,status,max_attempts)
               VALUES(:task,:agent,:action,:status,3)"
            );
            $ins->execute([':task'=>$taskId,':agent'=>$agent,':action'=>$action,':status'=>$status]);

            $newTaskStatus=$status==='blocked' ? 'a_verifier' : 'en_cours';
            $up=$pdo->prepare("UPDATE vp_tasks SET status=:status WHERE task_id=:task AND status<>'termine'");
            $up->execute([':status'=>$newTaskStatus,':task'=>$taskId]);

            $pdo->commit();
            return ['task_id'=>$taskId,'agent'=>$agent,'action'=>$action,'status'=>$status,'mode'=>$mode];
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
