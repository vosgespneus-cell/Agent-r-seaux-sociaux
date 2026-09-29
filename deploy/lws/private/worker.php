<?php
declare(strict_types=1);

// CLI only. Install outside htdocs as home/vp_worker.php and run by LWS cron.
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/product_intake.php';

$config = require __DIR__ . '/vp_config.php';
$db = new PDO($config['db_dsn'], $config['db_user'], $config['db_password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

if ((int)$db->query("SELECT GET_LOCK('vp_worker', 0)")->fetchColumn() !== 1) { exit(0); }

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

    // Product events are copied into the private product intake register.
    // INSERT/ON DUPLICATE logic makes repeated cron runs idempotent.
    $q=$db->query("SELECT e.task_id,e.source,e.body
        FROM vp_events e
        JOIN vp_tasks t ON t.event_id=e.event_id
        LEFT JOIN vp_product_intake p ON p.task_id=e.task_id
        WHERE e.kind='produit' AND t.agent='produits' AND p.task_id IS NULL
        ORDER BY e.received_at ASC LIMIT 100");
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        $intake=ProductIntake::fromEvent((string)$row['task_id'],(string)$row['source'],(string)$row['body']);
        ProductIntake::save($db,$intake);
    }

    $db->commit();
    echo "VP_WORKER_OK\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) { $db->rollBack(); }
    fwrite(STDERR,"VP_WORKER_ERROR\n");
    exit(1);
} finally {
    $db->query("SELECT RELEASE_LOCK('vp_worker')");
}
