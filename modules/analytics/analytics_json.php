<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

session_timeout_guard();
require_login();

$pdo = db();

// Status breakdown
$statusStmt = $pdo->query("SELECT case_status, COUNT(*) AS c FROM blotter_records GROUP BY case_status");
$rows = $statusStmt->fetchAll();

$labels = [];
$values = [];
foreach ($rows as $r) {
  $labels[] = (string)$r['case_status'];
  $values[] = (int)$r['c'];
}

// Trend over last 6 months
$trendStmt = $pdo->query("SELECT DATE_FORMAT(incident_date, '%Y-%m') AS ym, COUNT(*) AS c
  FROM blotter_records
  WHERE incident_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
  GROUP BY ym
  ORDER BY ym ASC");
$trendRows = $trendStmt->fetchAll();

$trendLabels = [];
$trendValues = [];
foreach ($trendRows as $r) {
  $trendLabels[] = (string)$r['ym'];
  $trendValues[] = (int)$r['c'];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'status' => ['labels' => $labels, 'values' => $values],
  'trend' => ['labels' => $trendLabels, 'values' => $trendValues],
]);

