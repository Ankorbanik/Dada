<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['manage_access'])) { http_response_code(403); die('Unauthorized'); }

$action = $_GET['action'] ?? '';
$code = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['code'] ?? '');

if(empty($code)) die('[]');

header('Content-Type: application/json');

if($action == 'hour_detail') {
    $hour = (int)$_GET['hour'];
    $stmt = $conn->prepare("SELECT country, COUNT(DISTINCT visitor_fingerprint) as cnt FROM clicks WHERE short_code=? AND HOUR(clicked_at)=? AND country IS NOT NULL GROUP BY country ORDER BY cnt DESC");
    $stmt->bind_param("si", $code, $hour);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = ['countries'=>[]];
    while($r=$res->fetch_assoc()) $data['countries'][] = $r;
    echo json_encode($data);
}
elseif($action == 'country_detail') {
    $country = $_GET['country'];
    $stmt = $conn->prepare("SELECT city, COUNT(DISTINCT visitor_fingerprint) as cnt FROM clicks WHERE short_code=? AND country=? AND city IS NOT NULL GROUP BY city ORDER BY cnt DESC");
    $stmt->bind_param("ss", $code, $country);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = ['cities'=>[]];
    while($r=$res->fetch_assoc()) $data['cities'][] = $r;
    echo json_encode($data);
}
elseif($action == 'city_detail') {
    $city = $_GET['city'];
    $stmt = $conn->prepare("SELECT device_type, browser, COUNT(*) as cnt FROM clicks WHERE short_code=? AND city=? GROUP BY device_type, browser");
    $stmt->bind_param("ss", $code, $city);
    $stmt->execute();
    $res = $stmt->get_result();
    $devices = []; $browsers = [];
    while($r=$res->fetch_assoc()) {
        $devices[] = $r['device_type']."(".$r['cnt'].")";
        $browsers[] = $r['browser']."(".$r['cnt'].")";
    }
    echo json_encode(['devices'=>implode(', ',$devices), 'browsers'=>implode(', ',$browsers)]);
}
else {
    echo json_encode(['error'=>'invalid action']);
}
?>