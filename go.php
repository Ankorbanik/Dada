<?php
session_start();
require_once 'db.php';

$short_code = $_GET['code'] ?? '';
if (!$short_code) die('Invalid link');

// লং URL বের করা
$urlData = $conn->query("SELECT long_url FROM urls WHERE short_code='$short_code'")->fetch_assoc();
if (!$urlData) die('Link not found');

// -------------------- ট্র্যাকিং ডাটা সংগ্রহ --------------------
$fingerprint = $_COOKIE['visitor_fp'] ?? md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_ACCEPT_LANGUAGE']);
setcookie('visitor_fp', $fingerprint, time()+86400*365, '/');

$ip = $_SERVER['REMOTE_ADDR'];
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$is_bot = preg_match('/bot|crawler|spider|scraper/i', $user_agent) ? 1 : 0;

// ডিভাইস, ব্রাউজার, ওএস সনাক্তকরণ (সহজ লাইব্রেরি ব্যবহার করতে পারেন, এখানে বেসিক)
$device_type = (preg_match('/mobile/i',$user_agent)) ? 'Mobile' : ((preg_match('/tablet/i',$user_agent)) ? 'Tablet' : 'Desktop');
$browser = 'Unknown';
$browser_version = '';
$os = 'Unknown';
$device_model = '';

// ব্রাউজার সনাক্তকরণ
if (strpos($user_agent, 'Edg') !== false) $browser = 'Edge';
elseif (strpos($user_agent, 'Chrome') !== false) $browser = 'Chrome';
elseif (strpos($user_agent, 'Firefox') !== false) $browser = 'Firefox';
elseif (strpos($user_agent, 'Safari') !== false) $browser = 'Safari';
elseif (strpos($user_agent, 'OPR') !== false) $browser = 'Opera';
// সংস্করণ বের করা (সিম্পল)
preg_match('/'.$browser.'\/([0-9.]+)/', $user_agent, $match);
if(isset($match[1])) $browser_version = $match[1];

// ওএস
if (strpos($user_agent, 'Windows') !== false) $os = 'Windows';
elseif (strpos($user_agent, 'Mac') !== false) $os = 'macOS';
elseif (strpos($user_agent, 'Linux') !== false) $os = 'Linux';
elseif (strpos($user_agent, 'Android') !== false) $os = 'Android';
elseif (strpos($user_agent, 'iOS') !== false || strpos($user_agent, 'iPhone') !== false) $os = 'iOS';

// জিও লোকেশন (আপনার ইমপ্লিমেন্টেশন অনুযায়ী)
$country = 'Unknown';
$city = '';
// যদি আপনার কাছে ipapi বা অন্য সার্ভিস থাকে তবে কল করুন

// UTM প্যারামিটার
$utm_source = $_GET['utm_source'] ?? '';
$utm_medium = $_GET['utm_medium'] ?? '';
$utm_campaign = $_GET['utm_campaign'] ?? '';
$utm_term = $_GET['utm_term'] ?? '';
$utm_content = $_GET['utm_content'] ?? '';

// ক্লিক পজিশন ও লোড টাইম (AJAX থেকে আসবে, আমরা পরবর্তী কল এ সেট করব)
// এখানে ডিফল্ট null রাখব, পরে AJAX আপডেট করতে পারেন
$x = $_POST['click_x'] ?? null;
$y = $_POST['click_y'] ?? null;
$vp_w = $_POST['viewport_w'] ?? null;
$vp_h = $_POST['viewport_h'] ?? null;
$load_time = $_POST['load_time'] ?? null;

$is_conversion = (strpos($urlData['long_url'], 'thank-you') !== false) ? 1 : 0; // উদাহরণ

$stmt = $conn->prepare("INSERT INTO clicks 
    (short_code, visitor_fingerprint, is_bot, ip_address, country, city, device_type, browser, browser_version, os, device_model, referrer, clicked_at, utm_source, utm_medium, utm_campaign, utm_term, utm_content, click_position_x, click_position_y, viewport_width, viewport_height, page_load_time, is_conversion) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssissssssssssssssiiiiii", 
    $short_code, $fingerprint, $is_bot, $ip, $country, $city, $device_type, $browser, $browser_version, $os, $device_model, $referrer,
    $utm_source, $utm_medium, $utm_campaign, $utm_term, $utm_content,
    $x, $y, $vp_w, $vp_h, $load_time, $is_conversion);
$stmt->execute();
$stmt->close();

// রিডাইরেক্ট
header("Location: " . $urlData['long_url']);
exit;
?>