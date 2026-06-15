<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(403); exit; }

$format = $_GET['export'] ?? 'csv';
$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = [];
$params = [];
if ($actionFilter) { $where[] = "a.action = ?"; $params[] = $actionFilter; }
if ($userFilter) { $where[] = "u.username LIKE ?"; $params[] = "%$userFilter%"; }
if ($dateFrom) { $where[] = "DATE(a.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where[] = "DATE(a.created_at) <= ?"; $params[] = $dateTo; }
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$query = "SELECT a.*, u.username FROM user_activity_logs a LEFT JOIN users u ON a.user_id = u.id $whereSQL ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($data[0] ?? []));
    foreach ($data as $row) fputcsv($output, $row);
    fclose($output);
} elseif ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="activity_' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
}
exit;