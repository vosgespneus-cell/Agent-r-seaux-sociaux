<?php
declare(strict_types=1);

// CLI only. Install outside htdocs as home/vp_worker.php and run by LWS cron.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$config = require __DIR__ . '/vp_config.php';
$db = new PDO($config['db_dsn'], $config['db_user'], $config['db_password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// A second cron invocation must not process the same batch concurrently.
if ((int) $db->query("SELECT GET_LOCK('vp_worker', 0)")->fetchColumn() !== 1) {
    exit(0);
}

try {
    $db->beginTransaction();
    $db->exec("INSERT IGNORE INTO vp_tasks (task_id, event_id, agent, status)
        SELECT e.task_id, e.event_id,
        CASE e.kind
            WHEN 'appel' THEN 'telephone'
            WHEN 'rendez_vous' THEN 'planning'
            WHEN 'produit' THEN 'produits'
            WHEN 'media' THEN 'communication'
            ELSE 'accueil'
        END,
        'nouveau'
        FROM vp_events e LEFT JOIN vp_tasks t ON t.event_id = e.event_id
        WHERE t.event_id IS NULL ORDER BY e.received_at ASC LIMIT 100");
    $db->commit();
    // Never print events or credentials in cron logs.
    echo "VP_WORKER_OK\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "VP_WORKER_ERROR\n");
    exit(1);
} finally {
    $db->query("SELECT RELEASE_LOCK('vp_worker')");
}
