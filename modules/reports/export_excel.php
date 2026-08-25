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

$stmt = $pdo->prepare("SELECT reference_number, complainant_name, respondent_name, incident_type,
  incident_date, incident_time, location, case_status, narrative
  FROM blotter_records
  $whereSql
  ORDER BY incident_date DESC
  LIMIT 2000");
$stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\\PhpSpreadsheet\\Spreadsheet;
use PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Blotter Records');

$headers = ['Reference #','Incident Date','Incident Time','Incident Type','Complainant','Respondent','Location','Case Status','Narrative'];
$sheet->fromArray($headers, null, 'A1');

$rowNum = 2;
foreach ($rows as $r) {
  $sheet->setCellValue('A' . $rowNum, (string)$r['reference_number']);
  $sheet->setCellValue('B' . $rowNum, (string)$r['incident_date']);
  $sheet->setCellValue('C' . $rowNum, (string)$r['incident_time']);
  $sheet->setCellValue('D' . $rowNum, (string)$r['incident_type']);
  $sheet->setCellValue('E' . $rowNum, (string)$r['complainant_name']);
  $sheet->setCellValue('F' . $rowNum, (string)$r['respondent_name']);
  $sheet->setCellValue('G' . $rowNum, (string)$r['location']);
  $sheet->setCellValue('H' . $rowNum, (string)$r['case_status']);
  $sheet->setCellValue('I' . $rowNum, (string)$r['narrative']);
  $rowNum++;
}

// Basic column widths
$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(12);
$sheet->getColumnDimension('C')->setWidth(10);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(22);
$sheet->getColumnDimension('F')->setWidth(22);
$sheet->getColumnDimension('G')->setWidth(16);
$sheet->getColumnDimension('H')->setWidth(18);
$sheet->getColumnDimension('I')->setWidth(60);

$filename = 'barangay_san_jose_blotter_' . date('Ymd_His') . '.xlsx';
$writer = new Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');

