<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['manage_access'])) die('Unauthorized');
$online = $conn->query("SELECT COUNT(DISTINCT visitor_fingerprint) FROM clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND is_bot=0")->fetch_row()[0];
echo json_encode(['online' => $online]);
?>