<?php
declare(strict_types=1);

// Simple router entry point
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';

$route = $_GET['route'] ?? 'dashboard';

// Normalize routes
$route = trim((string)$route, '/');

$map = [
  'login' => __DIR__ . '/../modules/users/login.php',
  'logout' => __DIR__ . '/../modules/users/logout.php',
  'users' => __DIR__ . '/../modules/users/users.php',

  'dashboard' => __DIR__ . '/../modules/analytics/dashboard.php',

  'blotter_create' => __DIR__ . '/../modules/blotter/blotter_create.php',
  'blotter_update' => __DIR__ . '/../modules/blotter/blotter_update.php',
  'blotter_delete' => __DIR__ . '/../modules/blotter/blotter_delete.php',

  'search' => __DIR__ . '/../modules/search/search.php',

  'analytics_json' => __DIR__ . '/../modules/analytics/analytics_json.php',

  'reports' => __DIR__ . '/../modules/reports/reports.php',
  'export_pdf' => __DIR__ . '/../modules/reports/export_pdf.php',
  'export_excel' => __DIR__ . '/../modules/reports/export_excel.php',

  'resolution_create' => __DIR__ . '/../modules/blotter/resolution_create.php',
  'resolution_update' => __DIR__ . '/../modules/blotter/resolution_update.php',
];

$target = $map[$route] ?? null;
if (!$target || !file_exists($target)) {
  http_response_code(404);
  echo '404 - Route not found';
  exit;
}

// Protect all routes except login
if (!in_array($route, ['login'], true) && !is_logged_in()) {
  header('Location: ?route=login');
  exit;
}

require $target;

