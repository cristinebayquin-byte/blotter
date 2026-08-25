<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';

session_timeout_guard();
require_login();

$pdo = db();

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$type = trim((string)($_GET['incident_type'] ?? ''));
$status = trim((string)($_GET['case_status'] ?? ''));

$errors = [];
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $errors[] = 'Invalid From date.';
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $errors[] = 'Invalid To date.';

$where = [];
$params = [];

if ($from !== '') { $where[] = 'incident_date >= :from'; $params[':from'] = $from; }
if ($to !== '') { $where[] = 'incident_date <= :to'; $params[':to'] = $to; }
if ($type !== '') { $where[] = 'incident_type LIKE :type'; $params[':type'] = '%' . $type . '%'; }
if ($status !== '') { $where[] = 'case_status = :status'; $params[':status'] = $status; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM blotter_records $whereSql");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetch()['c'];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reports</title>
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
      <a class="nav-link text-white active" href="?route=reports">Reports</a>
      <?php if (in_array(current_user()['role'], ['captain','secretary'], true)): ?>
        <a class="nav-link text-white" href="?route=users">User Management</a>
      <?php endif; ?>
    </nav>
    <div class="mt-3 small opacity-75">Role: <?php echo htmlspecialchars((string)current_user()['role']); ?></div>
  </aside>

  <main class="flex-grow-1 p-4 app-main">
    <div class="d-flex align-items-start justify-content-between page-header">
      <div><h1 class="page-heading">Reports & Analytics</h1><p class="page-subtitle">Filter, review, and export official blotter data.</p></div>
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

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="get" class="row g-2">
          <input type="hidden" name="route" value="reports" />

          <div class="col-md-3">
            <label class="form-label">From</label>
            <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($from); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">To</label>
            <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($to); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Incident Type</label>
            <input type="text" class="form-control" name="incident_type" value="<?php echo htmlspecialchars($type); ?>" placeholder="e.g., Theft">
          </div>
          <div class="col-md-3">
            <label class="form-label">Case Status</label>
            <select class="form-select" name="case_status">
              <option value="">All</option>
              <?php foreach (['Open','Under Mediation','Resolved','Closed'] as $s): ?>
                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 d-flex gap-2 align-items-end">
            <button class="btn btn-primary" type="submit">Update Filters</button>
            <span class="text-muted">Matched records: <b><?php echo $total; ?></b></span>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6>Export Records</h6>
            <p class="text-muted small mb-3">Exports the filtered record set.</p>
            <?php
              $query = http_build_query(array_filter([
                'from' => $from,
                'to' => $to,
                'incident_type' => $type,
                'case_status' => $status,
              ]));
            ?>
            <a class="btn btn-outline-primary" href="?route=export_pdf&<?php echo htmlspecialchars($query); ?>">Export to PDF</a>
            <a class="btn btn-outline-success ms-2" href="?route=export_excel&<?php echo htmlspecialchars($query); ?>">Export to Excel</a>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6>Export Analytics</h6>
            <p class="text-muted small mb-3">Exports current analytics snapshot (records filtered the same way).</p>
            <a class="btn btn-outline-dark" href="#" onclick="alert('Analytics-to-PDF export will be added in next step.'); return false;">Export Analytics (PDF)</a>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>

