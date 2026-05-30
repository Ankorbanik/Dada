<?php
$url = isset($_GET['code']) ? $_GET['code'] : '';
if (empty($url)) {
    die('No URL');
}
$qr_url = "https://quickchart.io/qr?text=" . urlencode($url) . "&size=200&margin=2";
header("Location: $qr_url");
exit;
?>