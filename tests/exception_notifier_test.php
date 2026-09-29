<?php
declare(strict_types=1);
require __DIR__.'/../deploy/lws/private/exception_notifier.php';
if(ExceptionNotifier::shouldNotify(['actions_failed'=>0,'tasks_blocked'=>0])) exit(1);
$s=['actions_failed'=>1,'intake_needs_information'=>2];
if(!ExceptionNotifier::shouldNotify($s)) exit(2);
$m=ExceptionNotifier::message($s);
if(!str_contains($m,'1 actions échouées') || !str_contains($m,'2 pièces à compléter')) exit(3);
echo "VP_EXCEPTION_NOTIFIER_OK\n";
