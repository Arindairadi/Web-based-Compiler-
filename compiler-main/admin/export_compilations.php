<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(403); exit; }

$format = $_GET['export'] ?? 'csv';
$langFilter = $_GET['language'] ?? '';
$successFilter = isset($_GET['success']) ? (int)$_GET['success'] : -1;

$where = [];
$params = [];
if ($langFilter) { $where[] = "language = ?"; $params[] = $langFilter; }
if ($successFilter !== -1) { $where[] = "success = ?"; $params[] = $successFilter; }
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$query = "SELECT * FROM compilation_logs $whereSQL ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="compilations_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($data[0] ?? []));
    foreach ($data as $row) fputcsv($output, $row);
    fclose($output);
} elseif ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="compilations_' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
}
exit;