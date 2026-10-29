<?php
declare(strict_types=1);
// CLI-only, explicit application; never opens the quotes database or calls Shopify.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function vpCompletedTestState(array $draft): string {
    if (($draft['status'] ?? '') !== 'COMPLETED') return 'private_draft_stock_pending';
    if (($draft['order']['test'] ?? false) !== true) {
        throw new RuntimeException('non_test_order_requires_review');
    }
    if (($draft['order']['displayFinancialStatus'] ?? '') !== 'PAID') {
        throw new RuntimeException('test_order_not_paid');
    }
    return 'completed';
}

function vpFinalPatch(string $name, string $source): string {
    $oldGuard = "\$state=(\$draft['status']==='COMPLETED')?'completed':'private_draft_stock_pending';\n                if(\$state==='completed'&&(\$draft['order']['test']??false)!==true)throw new RuntimeException('non_test_order_requires_review');";
    $newGuard = "\$state=vpCompletedTestState(\$draft);";
    $edits = [
        'review_import.php' => ['18=>2350,19=>2750' => '18=>2750,19=>2750'],
        'cart_checks.php' => ['18=>1800,19=>2200' => '18=>2200,19=>2200'],
        'shopify_bridge.php' => [
            "require __DIR__.'/review_lib.php';" =>
                "require __DIR__.'/review_lib.php';\nrequire_once __DIR__.'/vp_completed_guard_20261025.php';",
            $oldGuard => $newGuard,
        ],
    ];
    if (!isset($edits[$name])) throw new RuntimeException('unknown_target');
    foreach ($edits[$name] as $before => $after) {
        // Exact final text marks an already patched source, even if it contains the old prefix.
        if (substr_count($source, $after) === 1) continue;
        if (substr_count($source, $before) !== 1) throw new RuntimeException('source_changed_'.$name);
        $source = str_replace($before, $after, $source);
    }
    return $source;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
try {
    if (($argv[1] ?? '') === '--self-test') {
        $cases = 0;
        foreach (['OPEN', 'INVOICE_SENT'] as $status) {
            if (vpCompletedTestState(['status'=>$status]) !== 'private_draft_stock_pending') throw new LogicException();
            $cases++;
        }
        if (vpCompletedTestState(['status'=>'COMPLETED','order'=>['test'=>true,'displayFinancialStatus'=>'PAID']]) !== 'completed') throw new LogicException();
        $cases++;
        foreach (['PENDING','AUTHORIZED','PARTIALLY_PAID','REFUNDED','PARTIALLY_REFUNDED','VOIDED','EXPIRED',''] as $status) {
            try {
                vpCompletedTestState(['status'=>'COMPLETED','order'=>['test'=>true,'displayFinancialStatus'=>$status]]);
                throw new LogicException('unpaid_accepted');
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== 'test_order_not_paid') throw $e;
            }
            $cases++;
        }
        foreach ([false, null, 'true', 1] as $test) {
            try {
                vpCompletedTestState(['status'=>'COMPLETED','order'=>['test'=>$test,'displayFinancialStatus'=>'PAID']]);
                throw new LogicException('live_accepted');
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== 'non_test_order_requires_review') throw $e;
            }
            $cases++;
        }
        foreach (['review_import.php'=>'[13=>2050,16=>2050,17=>2350,18=>2350,19=>2750,22=>2750]',
                  'cart_checks.php'=>'[13=>1500,16=>1500,17=>1800,18=>1800,19=>2200,22=>2200]'] as $name=>$fixture) {
            $patched = vpFinalPatch($name,$fixture);
            if ($patched === $fixture || vpFinalPatch($name,$patched) !== $patched) throw new LogicException('patch_not_idempotent');
            try { vpFinalPatch($name,'unexpected_source'); throw new LogicException(); }
            catch (RuntimeException $e) { if (!str_starts_with($e->getMessage(),'source_changed_')) throw $e; }
            $cases++;
        }
        echo "FINAL_GUARD_SELF_TEST_OK cases=$cases no_database=1 no_network=1\n";
        exit;
    }
    if (!in_array($argv[1] ?? '', ['--check','--apply'],true)) throw new RuntimeException('use_check_or_apply');
    $prepared = [];
    foreach (['review_import.php','cart_checks.php','shopify_bridge.php'] as $name) {
        $path=__DIR__.'/'.$name;
        $source=file_get_contents($path);
        if ($source === false) throw new RuntimeException('missing_target');
        $prepared[$path]=[$source,vpFinalPatch($name,$source)];
    }
    if ($argv[1] === '--check') {
        echo "FINAL_PATCH_CHECK_OK targets=3 database_untouched=1\n"; exit;
    }
    // Validate every anchor before any write; retain exact backups for review/rollback.
    foreach ($prepared as $path=>[$before,$after]) {
        if ($before === $after) continue;
        $backup=$path.'.before-final-20261025';
        if (file_exists($backup)) throw new RuntimeException('backup_already_exists');
    }
    foreach ($prepared as $path=>[$before,$after]) {
        if ($before === $after) continue;
        $backup=$path.'.before-final-20261025';
        if (file_put_contents($backup,$before,LOCK_EX) !== strlen($before)) throw new RuntimeException('backup_failed');
        chmod($backup,0600);
        $temp=$path.'.final-20261025.tmp';
        if (file_exists($temp)) throw new RuntimeException('temporary_file_exists');
        if (file_put_contents($temp,$after,LOCK_EX) !== strlen($after)) throw new RuntimeException('write_failed');
        chmod($temp,fileperms($path)&0777);
        if (!rename($temp,$path)) throw new RuntimeException('rename_failed');
    }
    echo "FINAL_PATCH_APPLIED targets=3 database_untouched=1 run_server_checks_required=1\n";
} catch (Throwable $e) {
    fwrite(STDERR,'FINAL_PATCH_ERROR '.$e->getMessage().PHP_EOL); exit(1);
}
