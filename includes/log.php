<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function system_log(string $action, string $targetTable, ?int $targetId = null): void
{
  $u = current_user();
  $userId = $u ? (int)$u['id'] : null;

  $stmt = db()->prepare('INSERT INTO system_logs (user_id, action, target_table, target_id) VALUES (:user_id, :action, :target_table, :target_id)');
  $stmt->execute([
    ':user_id' => $userId,
    ':action' => $action,
    ':target_table' => $targetTable,
    ':target_id' => $targetId,
  ]);
}

