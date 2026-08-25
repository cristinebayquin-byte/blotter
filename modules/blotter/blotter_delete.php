<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/log.php';

session_timeout_guard();
require_login();

if (!can_delete_blotter()) {
  http_response_code(403);
  echo '403 Forbidden';
  exit;
}

$pdo = db();
$blotterId = (int)($_GET['blotter_id'] ?? 0);
if ($blotterId <= 0) {
  http_response_code(400);
  echo 'Missing blotter_id.';
  exit;
}

$stmt = $pdo->prepare('SELECT reference_number, complainant_name, respondent_name FROM blotter_records WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $blotterId]);
$rec = $stmt->fetch();
if (!$rec) {
  http_response_code(404);
  echo 'Record not found.';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $confirm = (string)($_POST['confirm'] ?? '');
  if ($confirm === 'YES') {
    // deletion will cascade to case_resolutions (FK ON DELETE CASCADE)
    $del = $pdo->prepare('DELETE FROM blotter_records WHERE id = :id');
    $del->execute([':id' => $blotterId]);
    system_log('delete', 'blotter_records', $blotterId);
    header('Location: ?route=search');
    exit;
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Delete Blotter Record</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<div class="container" style="max-width: 720px; margin-top: 60px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">Confirm Delete</h4>
      <div class="alert alert-warning">
        Deleting this record cannot be undone.
      </div>

      <dl class="row">
        <dt class="col-sm-4">Reference #</dt>
        <dd class="col-sm-8"><?php echo htmlspecialchars((string)$rec['reference_number']); ?></dd>
        <dt class="col-sm-4">Complainant</dt>
        <dd class="col-sm-8"><?php echo htmlspecialchars((string)$rec['complainant_name']); ?></dd>
        <dt class="col-sm-4">Respondent</dt>
        <dd class="col-sm-8"><?php echo htmlspecialchars((string)$rec['respondent_name']); ?></dd>
      </dl>

      <form method="post" onsubmit="return confirm('Are you sure you want to delete this record? This will also remove related resolutions.');">
        <input type="hidden" name="confirm" value="YES" />
        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn btn-danger">Delete</button>
          <a href="?route=search" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>

