<?php
require_once 'db.php';

$code = isset($_POST['code']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['code']) : '';
$fingerprint = isset($_POST['fp']) ? preg_replace('/[^a-f0-9]/', '', $_POST['fp']) : '';
if (empty($code) || empty($fingerprint)) {
    http_response_code(400);
    echo "Missing data";
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ref = $_SERVER['HTTP_REFERER'] ?? '';

// জিওলোকেশন ফাংশন (আগের মতো)
function getGeoData($ip, $conn) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['country' => null, 'city' => null];
    }
    $stmt = $conn->prepare("SELECT country, city FROM ip_cache WHERE ip = ? AND updated_at > NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->bind_result($country, $city);
    if ($stmt->fetch()) {
        $stmt->close();
        return ['country' => $country, 'city' => $city];
    }
    $stmt->close();

    $url = "http://ip-api.com/json/".urlencode($ip)."?fields=status,country,city";
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp) {
        $data = json_decode($resp, true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            $country = $data['country'] ?? null;
            $city = $data['city'] ?? null;
            $ups = $conn->prepare("INSERT INTO ip_cache (ip, country, city) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE country = VALUES(country), city = VALUES(city), updated_at = NOW()");
            $ups->bind_param("sss", $ip, $country, $city);
            $ups->execute();
            $ups->close();
            return ['country' => $country, 'city' => $city];
        }
    }
    return ['country' => null, 'city' => null];
}

$geo = getGeoData($ip, $conn);

// ডিভাইস টাইপ
$device = 'desktop';
if (preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini/i', $ua)) $device = 'mobile';
elseif (preg_match('/Tablet|iPad|PlayBook/i', $ua)) $device = 'tablet';

// ব্রাউজার
$browser = 'Other';
if (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
elseif (preg_match('/Chrome\//i', $ua) && !preg_match('/Edg\//i', $ua)) $browser = 'Chrome';
elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome/i', $ua)) $browser = 'Safari';

// অপারেটিং সিস্টেম
$os = 'Other';
if (preg_match('/Windows NT 10\.0/i', $ua)) $os = 'Windows 10';
elseif (preg_match('/Windows NT/i', $ua)) $os = 'Windows';
elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
elseif (preg_match('/Android/i', $ua)) $os = 'Android';
elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

$is_bot = preg_match('/bot|crawler|spider|scraper|facebookexternalhit|twitterbot|whatsapp|telegrambot/i', $ua) ? 1 : 0;

// সময় তথ্য
date_default_timezone_set('Asia/Dhaka');
$hour = (int)date('G');
$day = date('l');

// ক্লিক সংরক্ষণ
$ins = $conn->prepare("INSERT INTO clicks (short_code, ip_address, country, city, device_type, browser, os, is_bot, referrer, visitor_fingerprint, hour_of_day, day_of_week) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$ins->bind_param("sssssssissis", $code, $ip, $geo['country'], $geo['city'], $device, $browser, $os, $is_bot, $ref, $fingerprint, $hour, $day);
$ins->execute();
$ins->close();

echo "OK";
?>