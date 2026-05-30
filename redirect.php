<?php
require_once 'db.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
if (strlen($code) === 0) {
    http_response_code(404);
    die("Invalid link");
}

$stmt = $conn->prepare("SELECT long_url FROM urls WHERE short_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$stmt->bind_result($long_url);
if ($stmt->fetch()) {
    $stmt->close();

    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';

    $geo = getGeoData($ip, $conn);

    $device = 'desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini/i', $ua)) $device = 'mobile';
    elseif (preg_match('/Tablet|iPad|PlayBook/i', $ua)) $device = 'tablet';

    $browser = 'Other';
    if (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
    elseif (preg_match('/Chrome\//i', $ua) && !preg_match('/Edg\//i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome/i', $ua)) $browser = 'Safari';

    $os = 'Other';
    if (preg_match('/Windows NT 10\.0/i', $ua)) $os = 'Windows 10';
    elseif (preg_match('/Windows NT/i', $ua)) $os = 'Windows';
    elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
    elseif (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

    $is_bot = preg_match('/bot|crawler|spider|scraper|facebookexternalhit|twitterbot|whatsapp|telegrambot/i', $ua) ? 1 : 0;
    $fingerprint = hash('sha256', $ip . $ua);

    $now = new DateTime('now', new DateTimeZone('Asia/Dhaka'));
    $hour = (int)$now->format('G');
    $day = $now->format('l');

    $ins = $conn->prepare("INSERT INTO clicks (short_code, ip_address, country, city, device_type, browser, os, is_bot, referrer, visitor_fingerprint, hour_of_day, day_of_week) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param("sssssssissis", $code, $ip, $geo['country'], $geo['city'], $device, $browser, $os, $is_bot, $ref, $fingerprint, $hour, $day);
    $ins->execute();
    $ins->close();

    header("Location: " . $long_url, true, 302);
    exit;
} else {
    $stmt->close();
    http_response_code(404);
    die("Link not found");
}

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
?>