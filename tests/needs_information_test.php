<?php
declare(strict_types=1);
require_once __DIR__.'/../deploy/lws/private/needs_information.php';

$f=NeedsInformation::normalize(['tire_size','quantity','tire_size','password','phone']);
if($f!==['tire_size','quantity']) throw new RuntimeException('field whitelist failed');
$m=NeedsInformation::publicPrompt($f);
if(!str_contains($m,'dimension exacte') || !str_contains($m,'quantité')) throw new RuntimeException('prompt failed');
if(str_contains($m,'password') || str_contains($m,'phone')) throw new RuntimeException('unsafe field leaked');
echo "VP_NEEDS_INFORMATION_OK\n";
