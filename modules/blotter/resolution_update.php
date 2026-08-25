<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/log.php';

session_timeout_guard();
require_login();

if (!can_update_resolution()) {
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

$stmt = $pdo->prepare('SELECT * FROM blotter_records WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $blotterId]);
$blotter = $stmt->fetch();
if (!$blotter) {
  http_response_code(404);
  echo 'Blotter record not found.';
  exit;
}

$resStmt = $pdo->prepare('SELECT cr.*, u.full_name AS resolved_by_name
  FROM case_resolutions cr
  LEFT JOIN users u ON u.id = cr.resolved_by
  WHERE cr.blotter_record_id = :bid
  ORDER BY cr.resolved_at DESC
  LIMIT 1');
$resStmt->execute([':bid' => $blotterId]);
$res = $resStmt->fetch();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Resolution Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <script src="assets/app.js" defer></script>
</head>
<body>
<div class="d-flex app-shell">
  <aside class="bg-dark text-white p-3 app-sidebar">
    <div class="app-brand">BRGY. SAN JOSE<small>BLOTTER SYSTEM</small></div>
    <nav class="nav flex-column gap-1">
      <a class="nav-link text-white" href="?route=dashboard">Dashboard</a>
      <a class="nav-link text-white" href="?route=blotter_create">Blotter Records</a>
      <a class="nav-link text-white" href="?route=search">Search</a>
      <a class="nav-link text-white" href="?route=reports">Reports</a>
      <?php if (in_array(current_user()['role'], ['captain','secretary'], true)): ?>
        <a class="nav-link text-white" href="?route=users">User Management</a>
      <?php endif; ?>
    </nav>
    <div class="mt-3 small opacity-75">Role: <?php echo htmlspecialchars((string)current_user()['role']); ?></div>
  </aside>

  <main class="flex-grow-1 p-4 app-main">
    <div class="d-flex align-items-start justify-content-between page-header">
      <div><h1 class="page-heading">Resolution Details</h1><p class="page-subtitle">Review the recorded resolution for this case.</p></div>
      <a href="?route=search" class="btn btn-sm btn-outline-secondary">Back to Search</a>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-6">
            <div class="text-muted small">Reference #</div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string)$blotter['reference_number']); ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Case Status</div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string)$blotter['case_status']); ?></div>
          </div>
        </div>
      </div>
    </div>

    <?php if (!$res): ?>
      <div class="alert alert-info">No resolution recorded yet for this case.</div>
      <a class="btn btn-primary" href="?route=resolution_create&blotter_id=<?php echo $blotterId; ?>">Add Resolution</a>
    <?php else: ?>
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-2">Resolution Information</h6>
            <a class="btn btn-outline-primary btn-sm" href="?route=resolution_create&blotter_id=<?php echo $blotterId; ?>">Edit</a>
          </div>

          <dl class="row mb-0 mt-3">
            <dt class="col-sm-4">Resolution Type</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars((string)$res['resolution_type']); ?></dd>

            <dt class="col-sm-4">Resolved By</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars((string)($res['resolved_by_name'] ?? '')); ?></dd>

            <dt class="col-sm-4">Resolved At</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars((string)$res['resolved_at']); ?></dd>

            <dt class="col-sm-4">Settlement Details</dt>
            <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars((string)$res['settlement_details'])); ?></dd>
          </dl>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>
</body>
</html>

