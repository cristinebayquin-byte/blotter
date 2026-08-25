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

$existing = $pdo->prepare('SELECT * FROM case_resolutions WHERE blotter_record_id = :bid LIMIT 1');
$existing->execute([':bid' => $blotterId]);
$existingRow = $existing->fetch();

$notice = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $resolutionType = trim((string)($_POST['resolution_type'] ?? ''));
  $settlementDetails = trim((string)($_POST['settlement_details'] ?? ''));

  if ($resolutionType === '') $errors[] = 'Resolution type is required.';
  if ($settlementDetails === '' || mb_strlen($settlementDetails) < 10) $errors[] = 'Settlement details is required (min 10 chars).';

  if (!$errors) {
    $resolvedBy = (int)current_user()['id'];

    if ($existingRow) {
      $upd = $pdo->prepare('UPDATE case_resolutions
        SET resolution_type=:rt, settlement_details=:sd, resolved_by=:rb, resolved_at=NOW()
        WHERE blotter_record_id=:bid');
      $upd->execute([
        ':rt' => $resolutionType,
        ':sd' => $settlementDetails,
        ':rb' => $resolvedBy,
        ':bid' => $blotterId,
      ]);
      system_log('update', 'case_resolutions', (int)$existingRow['id']);
      $notice = 'Resolution updated successfully.';
    } else {
      $ins = $pdo->prepare('INSERT INTO case_resolutions (blotter_record_id, resolution_type, settlement_details, resolved_by, resolved_at)
        VALUES (:bid, :rt, :sd, :rb, NOW())');
      $ins->execute([
        ':bid' => $blotterId,
        ':rt' => $resolutionType,
        ':sd' => $settlementDetails,
        ':rb' => $resolvedBy,
      ]);
      system_log('create', 'case_resolutions', (int)$pdo->lastInsertId());
      $notice = 'Resolution saved successfully.';
    }

    // Optionally sync blotter status to Resolved if user provides resolution
    $pdo->prepare("UPDATE blotter_records SET case_status='Resolved', updated_at=NOW() WHERE id=:id")
      ->execute([':id' => $blotterId]);

    header('Location: ?route=resolution_update&blotter_id=' . $blotterId);
    exit;
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Case Resolution</title>
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
      <div><h1 class="page-heading">Case Resolution</h1><p class="page-subtitle">Record mediation and resolution details for this incident.</p></div>
      <a href="?route=search" class="btn btn-sm btn-outline-secondary">Back to Search</a>
    </div>

    <?php if ($notice): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-6">
            <div class="text-muted small">Reference #</div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string)$blotter['reference_number']); ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Incident Type</div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string)$blotter['incident_type']); ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Complainant</div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string)$blotter['complainant_name']); ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Respondent</div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string)$blotter['respondent_name']); ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" id="resForm" class="needs-validation" novalidate>
          <div class="mb-3">
            <label class="form-label">Resolution Type</label>
            <input class="form-control" name="resolution_type" required maxlength="80" placeholder="e.g., Barangay Mediation" value="<?php echo htmlspecialchars((string)($existingRow['resolution_type'] ?? '')); ?>">
            <div class="invalid-feedback">Resolution type is required.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Settlement / Agreement Details</label>
            <textarea class="form-control" name="settlement_details" rows="5" required minlength="10" maxlength="10000"><?php echo htmlspecialchars((string)($existingRow['settlement_details'] ?? '')); ?></textarea>
            <div class="invalid-feedback">Settlement details is required (min 10 chars).</div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save Resolution</button>
            <a class="btn btn-outline-secondary" href="?route=resolution_update&blotter_id=<?php echo $blotterId; ?>">View</a>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
  (function(){
    const form = document.getElementById('resForm');
    form.addEventListener('submit', function(e){
      if(!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    });
  })();
</script>
</body>
</html>

