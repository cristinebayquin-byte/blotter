<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/log.php';

session_timeout_guard();
require_login();

$pdo = db();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Client-side validation exists; this is server-side validation.
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

  // Reference number auto-generation: BSJ-YYYYMM-XXXX
  if (!$errors) {
    $ym = date('Ym'); // current year-month
    $prefix = 'BSJ-' . $ym . '-';

    $stmt = $pdo->prepare("SELECT reference_number FROM blotter_records WHERE reference_number LIKE :p ORDER BY reference_number DESC LIMIT 1");
    $stmt->execute([':p' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $seq = 1;
    if ($last) {
      $m = [];
      if (preg_match('/-([0-9]{4})$/', (string)$last, $m)) {
        $seq = ((int)$m[1]) + 1;
      }
    }

    $reference = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

    $ins = $pdo->prepare('INSERT INTO blotter_records
      (reference_number, complainant_name, respondent_name, incident_type, incident_date, incident_time, location, narrative, case_status, created_by, updated_at)
      VALUES
      (:ref, :c, :r, :it, :id, :itme, :loc, :nar, :st, :created_by, NULL)');

    $ins->execute([
      ':ref' => $reference,
      ':c' => $complainant,
      ':r' => $respondent,
      ':it' => $incidentType,
      ':id' => $incidentDate,
      ':itme' => $incidentTime,
      ':loc' => $location,
      ':nar' => $narrative,
      ':st' => $caseStatus,
      ':created_by' => (int)current_user()['id'],
    ]);

    system_log('create', 'blotter_records', (int)$pdo->lastInsertId());
    $success = 'Record saved successfully. Reference: ' . htmlspecialchars($reference);
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>New Blotter Record</title>
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
      <a class="nav-link text-white active" href="?route=blotter_create">Blotter Records</a>
      <a class="nav-link text-white" href="?route=search">Search</a>
      <a class="nav-link text-white" href="?route=reports">Reports</a>
      <?php if (in_array(current_user()['role'], ['captain','secretary'], true)): ?>
        <a class="nav-link text-white" href="?route=users">User Management</a>
      <?php endif; ?>
    </nav>
  </aside>

  <main class="flex-grow-1 p-4 app-main">
    <div class="d-flex align-items-start justify-content-between page-header">
      <div><h1 class="page-heading">File New Record</h1><p class="page-subtitle">Document an incident for barangay review and resolution.</p></div>
      <a href="?route=logout" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" id="blotterForm" class="needs-validation" novalidate>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Complainant Name</label>
              <input class="form-control" name="complainant_name" required maxlength="120">
              <div class="invalid-feedback">Required.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Respondent Name</label>
              <input class="form-control" name="respondent_name" required maxlength="120">
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Incident Type</label>
              <input class="form-control" name="incident_type" required maxlength="80">
              <div class="invalid-feedback">Required.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Incident Date</label>
              <input type="date" class="form-control" name="incident_date" required>
              <div class="invalid-feedback">Required.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Incident Time</label>
              <input type="time" step="1" class="form-control" name="incident_time" required>
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Location / Zone</label>
              <input class="form-control" name="location" required maxlength="120">
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Case Status</label>
              <select class="form-select" name="case_status" required>
                <option value="Open">Open</option>
                <option value="Under Mediation">Under Mediation</option>
                <option value="Resolved">Resolved</option>
                <option value="Closed">Closed</option>
              </select>
              <div class="invalid-feedback">Required.</div>
            </div>

            <div class="col-12">
              <label class="form-label">Narrative Description</label>
              <textarea class="form-control" name="narrative" rows="4" required minlength="10" maxlength="5000"></textarea>
              <div class="invalid-feedback">Narrative is required (min 10 chars).</div>
            </div>
          </div>

          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save Record</button>
            <a class="btn btn-outline-secondary" href="?route=search">Go to Search</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<script>
  (function(){
    const form = document.getElementById('blotterForm');
    form.addEventListener('submit', function(e){
      if(!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    });
  })();
</script>
</body>
</html>

