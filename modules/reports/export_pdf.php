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

if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { http_response_code(400); exit('Invalid From date'); }
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { http_response_code(400); exit('Invalid To date'); }

$where = [];
$params = [];
if ($from !== '') { $where[] = 'incident_date >= :from'; $params[':from'] = $from; }
if ($to !== '') { $where[] = 'incident_date <= :to'; $params[':to'] = $to; }
if ($type !== '') { $where[] = 'incident_type LIKE :type'; $params[':type'] = '%' . $type . '%'; }
if ($status !== '') { $where[] = 'case_status = :status'; $params[':status'] = $status; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rowsStmt = $pdo->prepare("SELECT reference_number, complainant_name, respondent_name, incident_type,
  incident_date, incident_time, location, case_status, narrative
  FROM blotter_records
  $whereSql
  ORDER BY incident_date DESC
  LIMIT 500");
$rowsStmt->execute($params);
$rows = $rowsStmt->fetchAll();

// Dompdf via composer/vendor
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\\Dompdf;

$dompdf = new Dompdf();

$html = '<html><head><style>'
  . 'body{font-family: DejaVu Sans, sans-serif; font-size:12px;}'
  . 'h2{margin:0 0 10px 0;} table{width:100%; border-collapse:collapse;} th,td{border:1px solid #ccc; padding:6px; vertical-align:top;} th{background:#f5f5f5;}'
  . '</style></head><body>'
  . '<h2>Barangay San Jose - Blotter Records Export (PDF)</h2>'
  . '<p>Generated at: ' . date('Y-m-d H:i:s') . '</p>'
  . '<p>Filters: ' . htmlspecialchars(($from?('From '.$from):'All')) . ', ' . htmlspecialchars(($to?('To '.$to):'All'))
  . ', ' . htmlspecialchars(($type?('Type '.$type):'All')) . ', ' . htmlspecialchars(($status?('Status '.$status):'All')) . '</p>'
  . '<table><thead><tr>'
  . '<th>Reference</th><th>Date/Time</th><th>Type</th><th>Complainant</th><th>Respondent</th><th>Location</th><th>Status</th><th>Narrative</th>'
  . '</tr></thead><tbody>';

foreach ($rows as $r) {
  $html .= '<tr>'
    . '<td>' . htmlspecialchars((string)$r['reference_number']) . '</td>'
    . '<td>' . htmlspecialchars((string)$r['incident_date']) . ' ' . htmlspecialchars((string)$r['incident_time']) . '</td>'
    . '<td>' . htmlspecialchars((string)$r['incident_type']) . '</td>'
    . '<td>' . htmlspecialchars((string)$r['complainant_name']) . '</td>'
    . '<td>' . htmlspecialchars((string)$r['respondent_name']) . '</td>'
    . '<td>' . htmlspecialchars((string)$r['location']) . '</td>'
    . '<td>' . htmlspecialchars((string)$r['case_status']) . '</td>'
    . '<td>' . nl2br(htmlspecialchars((string)$r['narrative'])) . '</td>'
    . '</tr>';
}

$html .= '</tbody></table>'
  . '<p style="margin-top:10px; color:#666;">Note: This export is limited to 500 records for performance.</p>'
  . '</body></html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4','portrait');
$dompdf->render();

$filename = 'barangay_san_jose_blotter_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);

