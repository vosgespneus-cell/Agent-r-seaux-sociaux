<?php
declare(strict_types=1);

// Private CLI report. Do not put this file or its output in the public web root.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    $config = require __DIR__ . '/vp_config.php';
    $db = new PDO($config['db_dsn'], $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $synthetic = (int) $db->query("SELECT COUNT(*) FROM vp_events WHERE LEFT(event_id, 6) = CHAR(115,109,111,107,101,45)")->fetchColumn();
    $events = (int) $db->query("SELECT COUNT(*) FROM vp_events WHERE LEFT(event_id, 6) <> CHAR(115,109,111,107,101,45)")->fetchColumn();
    $tasks = (int) $db->query("SELECT COUNT(*) FROM vp_tasks WHERE LEFT(event_id, 6) <> CHAR(115,109,111,107,101,45)")->fetchColumn();
    $unassigned = (int) $db->query(
        "SELECT COUNT(*) FROM vp_events e LEFT JOIN vp_tasks t ON t.event_id = e.event_id WHERE t.event_id IS NULL AND LEFT(e.event_id, 6) <> CHAR(115,109,111,107,101,45)"
    )->fetchColumn();
    $jotform = (int) $db->query(
        "SELECT COUNT(*) FROM vp_events WHERE LEFT(event_id, 3) = CHAR(74,70,45)"
    )->fetchColumn();

    echo "VP_STATUS_OK\n";
    echo "events={$events} tasks={$tasks} unassigned={$unassigned} jotform={$jotform} synthetic={$synthetic}\n";
    foreach ($db->query("SELECT agent, status, COUNT(*) AS total FROM vp_tasks WHERE LEFT(event_id, 6) <> CHAR(115,109,111,107,101,45) GROUP BY agent, status ORDER BY agent, status") as $row) {
        // Agent and status are application-controlled labels, not customer content.
        echo $row['agent'] . '/' . $row['status'] . '=' . $row['total'] . "\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, "VP_STATUS_ERROR\n");
    exit(1);
}
