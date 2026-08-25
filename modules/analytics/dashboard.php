<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';

session_timeout_guard();
require_login();

$pdo = db();

$totalStmt = $pdo->query('SELECT COUNT(*) AS c FROM blotter_records');
$totalRecords = (int)$totalStmt->fetch()['c'];

$openStmt = $pdo->query("SELECT COUNT(*) AS c FROM blotter_records WHERE case_status = 'Open'");
$openCases = (int)$openStmt->fetch()['c'];

$month = date('Y-m');
$resolvedThisMonthStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM blotter_records WHERE case_status IN ('Resolved','Closed') AND DATE_FORMAT(incident_date, '%Y-%m') = :month");
$resolvedThisMonthStmt->execute([':month' => $month]);
$resolvedThisMonth = (int)$resolvedThisMonthStmt->fetch()['c'];

$typesStmt = $pdo->query("SELECT incident_type, COUNT(*) AS c FROM blotter_records GROUP BY incident_type ORDER BY c DESC LIMIT 6");
$types = $typesStmt->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <script src="assets/app.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="d-flex app-shell">
  <aside class="bg-dark text-white p-3 app-sidebar">
    <div class="app-brand">BRGY. SAN JOSE<small>BLOTTER SYSTEM</small></div>
    <nav class="nav flex-column gap-1">
      <a class="nav-link text-white active" href="?route=dashboard">Dashboard</a>
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
      <div><h1 class="page-heading">Command Center</h1><p class="page-subtitle">Real-time overview of barangay incidents and resolutions.</p></div>
      <a href="?route=blotter_create" class="btn btn-primary">+ File New Record</a>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="card shadow-sm metric-card">
          <div class="card-body">
            <div class="metric-icon">▤</div>
            <div class="display-6"><?php echo $totalRecords; ?></div>
            <div class="metric-label">Total Records</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm metric-card open">
          <div class="card-body">
            <div class="metric-icon">!</div>
            <div class="display-6"><?php echo $openCases; ?></div>
            <div class="metric-label">Open Cases</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm metric-card resolved">
          <div class="card-body">
            <div class="metric-icon">✓</div>
            <div class="display-6"><?php echo $resolvedThisMonth; ?></div>
            <div class="metric-label">Resolved This Month</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <h6 class="mb-0">Top Incident Types</h6>
            </div>
            <canvas id="typesChart" height="120"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-0">Status Breakdown</h6>
            <canvas id="statusChart" height="120"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <h6 class="mb-3">Incidents Trend (Last 6 Months)</h6>
        <canvas id="trendChart" height="90"></canvas>
      </div>
    </div>
  </main>
</div>

<script>
  const types = <?php echo json_encode(array_map(fn($r)=>$r['incident_type'], $types)); ?>;
  const typeCounts = <?php echo json_encode(array_map(fn($r)=>(int)$r['c'], $types)); ?>;

  const typesChart = new Chart(document.getElementById('typesChart'), {
    type: 'bar',
    data: {
      labels: types,
      datasets: [{
        label: 'Count',
        data: typeCounts,
        backgroundColor: 'rgba(220,164,0,0.35)',
        borderColor: '#dca400',
        borderWidth: 1
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });

  // Fetch analytics for other charts
  (async function(){
    const res = await fetch('?route=analytics_json');
    const data = await res.json();

    const statusChart = new Chart(document.getElementById('statusChart'), {
      type: 'pie',
      data: {
        labels: data.status.labels,
        datasets: [{
          data: data.status.values,
          backgroundColor: ['#dca400','#16834d','#5f7190','#dc3545','#7d5ba6'],
        }]
      },
      options: { responsive: true }
    });

    const trendChart = new Chart(document.getElementById('trendChart'), {
      type: 'line',
      data: {
        labels: data.trend.labels,
        datasets: [{
          label: 'Incidents',
          data: data.trend.values,
          borderColor: '#dca400',
          backgroundColor: 'rgba(220,164,0,0.15)',
          fill: true,
          tension: 0.25
        }]
      },
      options: { responsive: true }
    });
  })();
</script>
</body>
</html>

