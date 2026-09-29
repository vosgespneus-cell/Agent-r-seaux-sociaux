<?php
declare(strict_types=1);

// Supervisor v1: turns classified tasks into an auditable action queue.
// It does not call external platforms yet.
if (PHP_SAPI !== 'cli') { exit(1); }

$config = require __DIR__ . '/vp_config.php';
$db = new PDO($config['db_dsn'], $config['db_user'], $config['db_password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

if ((int)$db->query("SELECT GET_LOCK('vp_supervisor', 0)")->fetchColumn() !== 1) { exit(0); }

try {
    $db->beginTransaction();
    $sql = "INSERT IGNORE INTO vp_actions (task_id, agent, action_type)
            SELECT task_id, agent,
              CASE agent
                WHEN 'telephone' THEN 'prepare_callback'
                WHEN 'planning' THEN 'prepare_appointment'
                WHEN 'produits' THEN 'prepare_product'
                WHEN 'communication' THEN 'prepare_media'
                WHEN 'marketing' THEN 'prepare_campaign'
                WHEN 'stock' THEN 'inspect_stock'
                WHEN 'gestion' THEN 'inspect_management'
                ELSE 'triage'
              END
            FROM vp_tasks
            WHERE status IN ('nouveau','en_cours')
            ORDER BY created_at ASC
            LIMIT 100";
    $db->exec($sql);
    $db->commit();
    echo "VP_SUPERVISOR_OK\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) { $db->rollBack(); }
    fwrite(STDERR, "VP_SUPERVISOR_ERROR\n");
    exit(1);
} finally {
    $db->query("SELECT RELEASE_LOCK('vp_supervisor')");
}
