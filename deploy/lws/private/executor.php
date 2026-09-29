<?php
declare(strict_types=1);

// Executor v1: safely consumes supervisor actions.
// External writes remain blocked until a dedicated adapter is explicitly enabled.
if (PHP_SAPI !== 'cli') { exit(1); }

$config = require __DIR__ . '/vp_config.php';
$db = new PDO($config['db_dsn'], $config['db_user'], $config['db_password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

if ((int)$db->query("SELECT GET_LOCK('vp_executor', 0)")->fetchColumn() !== 1) { exit(0); }

function logTransition(PDO $db, int $id, ?string $from, string $to, ?string $note = null): void {
    $q=$db->prepare('INSERT INTO vp_action_log (action_id, from_status, to_status, note) VALUES (?, ?, ?, ?)');
    $q->execute([$id,$from,$to,$note]);
}

try {
    $db->beginTransaction();
    $q=$db->query("SELECT action_id, task_id, agent, action_type, attempts, max_attempts
                   FROM vp_actions
                   WHERE status IN ('pending','retry') AND run_after <= CURRENT_TIMESTAMP
                   ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
    $action=$q->fetch(PDO::FETCH_ASSOC);
    if (!$action) {
        $db->commit();
        echo "VP_EXECUTOR_IDLE\n";
        exit(0);
    }

    $id=(int)$action['action_id'];
    $from=(string)($action['attempts'] > 0 ? 'retry' : 'pending');
    $u=$db->prepare("UPDATE vp_actions SET status='running', attempts=attempts+1, locked_at=CURRENT_TIMESTAMP WHERE action_id=?");
    $u->execute([$id]);
    logTransition($db,$id,$from,'running','executor claimed action');

    // V1 only validates routing. It deliberately performs no external write.
    $safeTypes=['triage','prepare_callback','prepare_appointment','prepare_product','prepare_media','prepare_campaign','inspect_stock','inspect_management'];
    if (!in_array($action['action_type'],$safeTypes,true)) {
        $u=$db->prepare("UPDATE vp_actions SET status='blocked', last_error='unknown action type' WHERE action_id=?");
        $u->execute([$id]);
        logTransition($db,$id,'running','blocked','unknown action type');
    } else {
        $u=$db->prepare("UPDATE vp_actions SET status='done', result_reference=? WHERE action_id=?");
        $u->execute(['validated:'.$action['action_type'],$id]);
        logTransition($db,$id,'running','done','routing validated; no external write');
    }
    $db->commit();
    echo "VP_EXECUTOR_OK\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) { $db->rollBack(); }
    fwrite(STDERR,"VP_EXECUTOR_ERROR\n");
    exit(1);
} finally {
    $db->query("SELECT RELEASE_LOCK('vp_executor')");
}
