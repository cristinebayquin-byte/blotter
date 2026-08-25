<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/log.php';

session_timeout_guard();
require_login();

$pdo = db();

// Pagination + sorting
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$sortBy = (string)($_GET['sortBy'] ?? 'incident_date');
$sortDir = strtolower((string)($_GET['sortDir'] ?? 'desc'));

$allowedSort = [
  'reference_number','complainant_name','respondent_name','incident_type',
  'incident_date','incident_time','location','case_status','updated_at','created_at'
];
if (!in_array($sortBy, $allowedSort, true)) {
  $sortBy = 'incident_date';
}
if (!in_array($sortDir, ['asc','desc'], true)) {
  $sortDir = 'desc';
}

// Filters
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$type = trim((string)($_GET['incident_type'] ?? ''));
$complainant = trim((string)($_GET['complainant_name'] ?? ''));
$respondent = trim((string)($_GET['respondent_name'] ?? ''));
$status = trim((string)($_GET['case_status'] ?? ''));

$allowedStatuses = ['', 'Open','Under Mediation','Resolved','Closed'];
if (!in_array($status, $allowedStatuses, true)) {
  $status = '';
}

$errors = [];
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $errors[] = 'Invalid "From" date.';
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $errors[] = 'Invalid "To" date.';

$where = [];
$params = [];

if ($from !== '') {
  $where[] = 'incident_date >= :from';
  $params[':from'] = $from;
}
if ($to !== '') {
  $where[] = 'incident_date <= :to';
  $params[':to'] = $to;
}
if ($type !== '') {
  $where[] = 'incident_type LIKE :type';
  $params[':type'] = '%' . $type . '%';
}
if ($complainant !== '') {
  $where[] = 'complainant_name LIKE :complainant';
  $params[':complainant'] = '%' . $complainant . '%';
}
if ($respondent !== '') {
  $where[] = 'respondent_name LIKE :respondent';
  $params[':respondent'] = '%' . $respondent . '%';
}
if ($status !== '') {
  $where[] = 'case_status = :status';
  $params[':status'] = $status;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) AS c FROM blotter_records $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$sql = "SELECT id, reference_number, complainant_name, respondent_name, incident_type, incident_date, incident_time, location, case_status, updated_at
        FROM blotter_records
        $whereSql
        ORDER BY $sortBy $sortDir
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

function sort_link(string $col, string $label, string $currentSortBy, string $currentSortDir): string {
  $dir = 'asc';
  if ($currentSortBy === $col && $currentSortDir === 'asc') {
    $dir = 'desc';
  }
  $params = $_GET;
  $params['sortBy'] = $col;
  $params['sortDir'] = $dir;
  return '<a href="?' . htmlspecialchars(http_build_query($params)) . '">' . htmlspecialchars($label) . '</a>';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Search Blotter Records</title>
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
      <a class="nav-link text-white active" href="?route=search">Search</a>
      <a class="nav-link text-white" href="?route=reports">Reports</a>
      <?php if (in_array(current_user()['role'], ['captain','secretary'], true)): ?>
        <a class="nav-link text-white" href="?route=users">User Management</a>
      <?php endif; ?>
    </nav>
    <div class="mt-3 small opacity-75">Role: <?php echo htmlspecialchars((string)current_user()['role']); ?></div>
  </aside>

  <main class="flex-grow-1 p-4 app-main">
    <div class="d-flex align-items-start justify-content-between page-header">
      <div><h1 class="page-heading">Search Records</h1><p class="page-subtitle">Find and manage barangay blotter records.</p></div>
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
        <form method="get" class="row g-2" id="searchForm">
          <input type="hidden" name="route" value="search" />

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
            <input type="text" class="form-control" name="incident_type" placeholder="e.g., Theft" value="<?php echo htmlspecialchars($type); ?>">
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

          <div class="col-md-4">
            <label class="form-label">Complainant Name</label>
            <input type="text" class="form-control" name="complainant_name" value="<?php echo htmlspecialchars($complainant); ?>" placeholder="Search name">
          </div>
          <div class="col-md-4">
            <label class="form-label">Respondent Name</label>
            <input type="text" class="form-control" name="respondent_name" value="<?php echo htmlspecialchars($respondent); ?>" placeholder="Search name">
          </div>

          <div class="col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-primary" type="submit">Search</button>
            <a class="btn btn-outline-secondary" href="?route=search">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="text-muted">Total results: <b><?php echo $total; ?></b></div>
      <div class="text-muted small">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Ref #</th>
            <th><?php echo sort_link('incident_date','Incident Date',$sortBy,$sortDir); ?></th>
            <th>Type</th>
            <th>Complainant</th>
            <th>Respondent</th>
            <th>Location</th>
            <th><?php echo sort_link('case_status','Status',$sortBy,$sortDir); ?></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$r['reference_number']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['incident_date']); ?> <?php echo htmlspecialchars((string)$r['incident_time']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['incident_type']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['complainant_name']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['respondent_name']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['location']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['case_status']); ?></td>
              <td>
                <div class="d-flex gap-2">
                  <a class="btn btn-sm btn-outline-primary" href="?route=resolution_update&blotter_id=<?php echo (int)$r['id']; ?>">Resolution</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <nav class="mt-3">
      <ul class="pagination mb-0">
        <?php
          $buildPageLink = function(int $p) use ($pageSize, $sortBy, $sortDir, $from, $to, $type, $complainant, $respondent, $status) {
            $params = [
              'route' => 'search',
              'page' => $p,
              'sortBy' => $sortBy,
              'sortDir' => $sortDir,
              'from' => $from,
              'to' => $to,
              'incident_type' => $type,
              'complainant_name' => $complainant,
              'respondent_name' => $respondent,
              'case_status' => $status,
            ];
            // Remove empty
            foreach ($params as $k => $v) {
              if ($v === '') unset($params[$k]);
            }
            return '?' . htmlspecialchars(http_build_query($params));
          };
        ?>
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo $page <= 1 ? '#' : $buildPageLink($page-1); ?>">Previous</a>
        </li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p === 1 || $p === $totalPages || abs($p - $page) <= 2): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?php echo $buildPageLink($p); ?>"><?php echo $p; ?></a>
            </li>
          <?php elseif ($p === 2 && $page > 4): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php elseif ($p === $totalPages-1 && $page < $totalPages-3): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php endif; ?>
        <?php endfor; ?>
        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : $buildPageLink($page+1); ?>">Next</a>
        </li>
      </ul>
    </nav>

  </main>
</div>

<script>
  // Keep simple - no heavy validation required for filters
</script>
</body>
</html>

