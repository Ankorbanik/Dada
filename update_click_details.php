<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['manage_access']) && !isset($_COOKIE['visitor_fp'])) die('Unauthorized');
$fingerprint = $_COOKIE['visitor_fp'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
if ($fingerprint && $input) {
    $x = $input['click_x'] ?? null;
    $y = $input['click_y'] ?? null;
    $vw = $input['viewport_w'] ?? null;
    $vh = $input['viewport_h'] ?? null;
    $load = $input['load_time'] ?? null;
    $conn->query("UPDATE clicks SET click_position_x=$x, click_position_y=$y, viewport_width=$vw, viewport_height=$vh, page_load_time=$load WHERE visitor_fingerprint='$fingerprint' ORDER BY clicked_at DESC LIMIT 1");
}
echo 'ok';
?>