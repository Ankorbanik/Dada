<?php
session_start();
require_once 'db.php';
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end = $_GET['end'] ?? date('Y-m-d');
$result = $conn->query("SELECT DAYOFWEEK(clicked_at)-1 as dow, HOUR(clicked_at) as hr, COUNT(*) as cnt FROM clicks WHERE clicked_at BETWEEN '$start 00:00:00' AND '$end 23:59:59' AND is_bot=0 GROUP BY dow, hr");
$data = array_fill(0,7,array_fill(0,24,0));
while($r=$result->fetch_assoc()) $data[$r['dow']][$r['hr']] = $r['cnt'];
echo json_encode($data);
?>