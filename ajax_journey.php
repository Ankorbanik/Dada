<?php
session_start();
require_once 'db.php';
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end = $_GET['end'] ?? date('Y-m-d');
$query = "SELECT visitor_fingerprint, GROUP_CONCAT(short_code ORDER BY clicked_at SEPARATOR ' → ') as path FROM clicks WHERE clicked_at BETWEEN '$start 00:00:00' AND '$end 23:59:59' AND is_bot=0 GROUP BY visitor_fingerprint HAVING COUNT(*) > 1";
$res = $conn->query($query);
$paths = [];
while($row=$res->fetch_assoc()) {
    $p = explode(' → ', $row['path']);
    $p = array_slice($p, 0, 3);
    $key = implode(' → ', $p);
    $paths[$key] = ($paths[$key]??0)+1;
}
arsort($paths);
$output = [];
foreach($paths as $path=>$count) $output[] = ['path'=>$path, 'count'=>$count];
echo json_encode(array_slice($output,0,10));
?>