<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/log.php';

session_timeout_guard();
require_login();

if (!can_edit_blotter()) {
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

$notice = '';
$errors = [];

$cur = null;
$stmt = $pdo->prepare('SELECT * FROM blotter_records WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $blotterId]);
$cur = $stmt->fetch();
if (!$cur) {
  http_response_code(404);
  echo 'Record not found.';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $complainant = trim((string)($_POST['complainant_name'] ?? ''));
  $respondent = trim((string)($_POST['respondent_name'] ?? ''));
  $incidentType = trim((string)($_POST['incident_type'] ?? ''));
  $incidentDate = trim((string)($_POST['incident_date'] ?? ''));
  $incidentTime = trim((string)($_POST['incident_time'] ?? ''));
  $location = trim((string)($_POST['location'] ?? ''));
  $narrative = trim((string)($_POST['narrative'] ?? ''));
  $caseStatus = (string)($_POST['case_status'] ?? 'Open');

  $allowedStatuses = ['Open','Under Mediation','Resolved','Closed'];

  if ($complainant === '') $errors[] = 'Complainant name is required.';
  if ($respondent === '') $errors[] = 'Respondent name is required.';
  if ($incidentType === '') $errors[] = 'Incident type is required.';
  if ($incidentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $incidentDate)) $errors[] = 'Valid incident date is required.';
  if ($incidentTime === '' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $incidentTime)) $errors[] = 'Valid incident time is required.';
  if ($location === '') $errors[] = 'Location is required.';
  if ($narrative === '' || mb_strlen($narrative) < 10) $errors[] = 'Narrative is required (min 10 characters).';
  if (!in_array($caseStatus, $allowedStatuses, true)) $errors[] = 'Invalid case status.';

  if (!$errors) {
    $upd = $pdo->prepare('UPDATE blotter_records
      SET complainant_name=:c, respondent_name=:r, incident_type=:it,
          incident_date=:id, incident_time=:itme, location=:loc,
          narrative=:nar, case_status=:st, updated_at=NOW()
      WHERE id=:bid');

    $upd->execute([
      ':c' => $complainant,
      ':r' => $respondent,
      ':it' => $incidentType,
      ':id' => $incidentDate,
      ':itme' => $incidentTime,
      ':loc' => $location,
      ':nar' => $narrative,
      ':st' => $caseStatus,
      ':bid' => $blotterId,
    ]);

    system_log('update', 'blotter_records', $blotterId);
    $notice = 'Record updated successfully.';
    // refresh current values
    $stmt = $pdo->prepare('SELECT * FROM blotter_records WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $blotterId]);
    $cur = $stmt->fetch();
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Blotter Record</title>
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
      <div><h1 class="page-heading">Edit Incident Record</h1><p class="page-subtitle">Review and update incident information.</p></div>
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

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" id="editForm" class="needs-validation" novalidate>
          <div class="mb-3">
            <label class="form-label">Reference Number</label>
            <input class="form-control" value="<?php echo htmlspecialchars((string)$cur['reference_number']); ?>" disabled>
          </div>

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Complainant Name</label>
              <input class="form-control" name="complainant_name" required maxlength="120" value="<?php echo htmlspecialchars((string)$cur['complainant_name']); ?>">
              <div class="invalid-feedback">Required.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Respondent Name</label>
              <input class="form-control" name="respondent_name" required maxlength="120" value="<?php echo htmlspecialchars((string)$cur['respondent_name']); ?>">
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Incident Type</label>
              <input class="form-control" name="incident_type" required maxlength="80" value="<?php echo htmlspecialchars((string)$cur['incident_type']); ?>">
              <div class="invalid-feedback">Required.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Incident Date</label>
              <input type="date" class="form-control" name="incident_date" required value="<?php echo htmlspecialchars((string)$cur['incident_date']); ?>">
              <div class="invalid-feedback">Required.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Incident Time</label>
              <input type="time" step="1" class="form-control" name="incident_time" required value="<?php echo htmlspecialchars((string)$cur['incident_time']); ?>">
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Location / Zone</label>
              <input class="form-control" name="location" required maxlength="120" value="<?php echo htmlspecialchars((string)$cur['location']); ?>">
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Case Status</label>
              <select class="form-select" name="case_status" required>
                <?php foreach (['Open','Under Mediation','Resolved','Closed'] as $s): ?>
                  <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ((string)$cur['case_status']===$s)?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-12">
              <label class="form-label">Narrative Description</label>
              <textarea class="form-control" name="narrative" rows="4" required minlength="10" maxlength="5000"><?php echo htmlspecialchars((string)$cur['narrative']); ?></textarea>
              <div class="invalid-feedback">Narrative is required (min 10 chars).</div>
            </div>
          </div>

          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Update Record</button>
            <a class="btn btn-outline-secondary" href="?route=search">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<script>
  (function(){
    const form = document.getElementById('editForm');
    form.addEventListener('submit', function(e){
      if(!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    });
  })();
</script>
</body>
</html>

