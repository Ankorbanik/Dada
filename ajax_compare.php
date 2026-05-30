<?php
session_start();
require_once 'db.php';
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end = $_GET['end'] ?? date('Y-m-d');
$interval = (strtotime($end)-strtotime($start))/86400;
$prev_start = date('Y-m-d', strtotime("$start - $interval days"));
$prev_end = date('Y-m-d', strtotime("$end - $interval days"));
$current = $conn->query("SELECT COUNT(*) as clicks, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE clicked_at BETWEEN '$start 00:00:00' AND '$end 23:59:59' AND is_bot=0")->fetch_assoc();
$previous = $conn->query("SELECT COUNT(*) as clicks, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE clicked_at BETWEEN '$prev_start 00:00:00' AND '$prev_end 23:59:59' AND is_bot=0")->fetch_assoc();
$clicks_change = round(($current['clicks'] - $previous['clicks']) / max($previous['clicks'],1) * 100,1);
$uniques_change = round(($current['uniques'] - $previous['uniques']) / max($previous['uniques'],1) * 100,1);
echo json_encode(['current_total_clicks'=>$current['clicks'], 'previous_total_clicks'=>$previous['clicks'], 'clicks_change'=>$clicks_change, 'current_uniques'=>$current['uniques'], 'previous_uniques'=>$previous['uniques'], 'uniques_change'=>$uniques_change]);
?>