<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['manage_access'])) die('Unauthorized');
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end = $_GET['end'] ?? date('Y-m-d');
$res = $conn->query("SELECT short_code, visitor_fingerprint, country, device_type, browser, clicked_at FROM clicks WHERE clicked_at BETWEEN '$start 00:00:00' AND '$end 23:59:59'");
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="analytics.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['short_code','fingerprint','country','device','browser','clicked_at']);
while($row=$res->fetch_assoc()) fputcsv($out, $row);
fclose($out);
?>