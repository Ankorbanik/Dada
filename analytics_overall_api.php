<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['manage_access'])) { http_response_code(403); die('Unauthorized'); }

$action = $_GET['action'] ?? '';
header('Content-Type: application/json');

if($action == 'hour_detail') {
    $hour = (int)$_GET['hour'];
    $stmt = $conn->prepare("SELECT country, COUNT(DISTINCT visitor_fingerprint) as cnt FROM clicks WHERE HOUR(clicked_at)=? AND country IS NOT NULL GROUP BY country ORDER BY cnt DESC");
    $stmt->bind_param("i", $hour);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = ['countries'=>[]];
    while($r=$res->fetch_assoc()) $data['countries'][] = $r;
    echo json_encode($data);
}
elseif($action == 'country_detail') {
    $country = $_GET['country'];
    $stmt = $conn->prepare("SELECT city, COUNT(DISTINCT visitor_fingerprint) as cnt FROM clicks WHERE country=? AND city IS NOT NULL GROUP BY city ORDER BY cnt DESC");
    $stmt->bind_param("s", $country);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = ['cities'=>[]];
    while($r=$res->fetch_assoc()) $data['cities'][] = $r;
    echo json_encode($data);
}
elseif($action == 'city_detail') {
    $city = $_GET['city'];
    $stmt = $conn->prepare("SELECT device_type, browser, COUNT(*) as cnt FROM clicks WHERE city=? GROUP BY device_type, browser");
    $stmt->bind_param("s", $city);
    $stmt->execute();
    $res = $stmt->get_result();
    $devices = []; $browsers = [];
    while($r=$res->fetch_assoc()) {
        $devices[] = $r['device_type']."(".$r['cnt'].")";
        $browsers[] = $r['browser']."(".$r['cnt'].")";
    }
    echo json_encode(['devices'=>implode(', ',$devices), 'browsers'=>implode(', ',$browsers)]);
}
elseif($action == 'user_detail') {
    $fp = $_GET['fingerprint'];
    $stmt = $conn->prepare("SELECT short_code, COUNT(*) as clicks, MIN(clicked_at) as first, MAX(clicked_at) as last FROM clicks WHERE visitor_fingerprint=? GROUP BY short_code ORDER BY clicks DESC");
    $stmt->bind_param("s", $fp);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = ['links'=>[]];
    while($r=$res->fetch_assoc()) $data['links'][] = $r;
    echo json_encode($data);
}
else {
    echo json_encode(['error'=>'invalid action']);
}
?>