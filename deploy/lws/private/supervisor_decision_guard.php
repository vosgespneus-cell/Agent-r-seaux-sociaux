<?php
declare(strict_types=1);

final class SupervisorDecisionGuard
{
    private const AGENTS=['accueil','telephone','planning','produits','stock','marketing','communication','gestion','supervision'];
    private const MODES=['auto','draft','approval_required'];
    private const SENSITIVE=['spend_money','refund','delete_account','delete_customer_data','legal_commitment','change_credentials'];

    public static function enforce(array $d): array
    {
        if (!in_array($d['agent'] ?? '',self::AGENTS,true)) throw new DomainException('invalid_agent');
        if (!in_array($d['action_mode'] ?? '',self::MODES,true)) throw new DomainException('invalid_action_mode');

        $actions=array_values(array_filter($d['next_actions'] ?? [],'is_string'));
        foreach ($actions as $action) {
            if (in_array($action,self::SENSITIVE,true)) {
                $d['action_mode']='approval_required';
            }
        }
        if (($d['priority'] ?? '')==='urgent' && ($d['action_mode'] ?? '')==='auto') {
            $d['action_mode']='draft';
        }
        $d['next_actions']=$actions;
        return $d;
    }
}
