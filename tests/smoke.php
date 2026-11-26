<?php
require_once __DIR__ . '/../db.php';
$count = (int)$db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
assert($count >= 1, "Expected seeded tasks in php-task-manager");
echo "php-task-manager smoke test PASSED (tasks={$count})\n";
