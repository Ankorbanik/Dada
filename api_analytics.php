<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['manage_access'])) die('Unauthorized');
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end = $_GET['end'] ?? date('Y-m-d');
$data = $conn->query("SELECT * FROM clicks WHERE clicked_at BETWEEN '$start 00:00:00' AND '$end 23:59:59'")->fetch_all(MYSQLI_ASSOC);
header('Content-Type: application/json');
echo json_encode($data);
?>