<?php
declare(strict_types=1);
require_once __DIR__.'/../deploy/lws/private/supervisor_decision_guard.php';

$d=SupervisorDecisionGuard::enforce([
 'agent'=>'planning','priority'=>'normal','action_mode'=>'auto',
 'next_actions'=>['check_appointment']
]);
if ($d['action_mode']!=='auto') throw new RuntimeException('safe action changed');

$d=SupervisorDecisionGuard::enforce([
 'agent'=>'gestion','priority'=>'normal','action_mode'=>'auto',
 'next_actions'=>['refund']
]);
if ($d['action_mode']!=='approval_required') throw new RuntimeException('sensitive action not blocked');

$d=SupervisorDecisionGuard::enforce([
 'agent'=>'telephone','priority'=>'urgent','action_mode'=>'auto',
 'next_actions'=>['handle_call']
]);
if ($d['action_mode']!=='draft') throw new RuntimeException('urgent auto action not downgraded');

echo "VP_SUPERVISOR_GUARD_OK\n";
