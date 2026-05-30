<?php
// track.php - নতুন ট্র্যাকিং API
header('Content-Type: application/json');
require_once 'db.php'; // আপনার বিদ্যমান db.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON']));
}

// বট ডিটেক্ট (আপনার পুরনো পদ্ধতির সাথে মিল রেখে)
$isBot = 0;
$userAgent = $data['user_agent'] ?? '';
$botPatterns = ['bot', 'crawl', 'spider', 'scraper', 'curl', 'wget', 'headless', 'phantom', 'selenium', 'puppeteer'];
foreach ($botPatterns as $pattern) {
    if (stripos($userAgent, $pattern) !== false) { $isBot = 1; break; }
}

$cookieId = $data['cookie_id'] ?? '';
$localId = $data['local_storage_id'] ?? '';
$fingerprint = $data['fingerprint_hash'] ?? '';
$sessionId = $data['session_id'] ?? '';

$conn->beginTransaction();
try {
    // ১. visitor খোঁজা / তৈরি
    $stmt = $conn->prepare("SELECT id FROM visitors WHERE cookie_id = ? OR local_storage_id = ? OR fingerprint_hash = ? LIMIT 1");
    $stmt->execute([$cookieId, $localId, $fingerprint]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($visitor) {
        $visitorId = $visitor['id'];
        $upd = $conn->prepare("UPDATE visitors SET last_seen = NOW() WHERE id = ?");
        $upd->execute([$visitorId]);
    } else {
        $ins = $conn->prepare("INSERT INTO visitors 
            (cookie_id, local_storage_id, fingerprint_hash, browser, os, device_type, screen_resolution, language, timezone, first_seen, last_seen) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $ins->execute([
            $cookieId, $localId, $fingerprint,
            $data['browser'] ?? null,
            $data['os'] ?? null,
            $data['device_type'] ?? null,
            $data['screen_resolution'] ?? null,
            $data['language'] ?? null,
            $data['timezone'] ?? null
        ]);
        $visitorId = $conn->lastInsertId();
    }
    
    // ২. শর্ট কোড বের করা (যদি URL টি শর্ট লিংক হয়)
    $shortCode = null;
    $longUrl = null;
    $currentUrl = $data['url'] ?? '';
    // আপনার .htaccess রুলে প্যাটার্ন: ^([a-zA-Z0-9_-]+)$
    if (preg_match('#/([a-zA-Z0-9_-]+)(?:$|\?)#', $currentUrl, $match)) {
        $shortCode = $match[1];
        $urlStmt = $conn->prepare("SELECT long_url FROM urls WHERE short_code = ?");
        $urlStmt->execute([$shortCode]);
        $urlRow = $urlStmt->fetch(PDO::FETCH_ASSOC);
        if ($urlRow) $longUrl = $urlRow['long_url'];
    }
    
    // ৩. IP অ্যাড্রেস (শুধু তথ্যের জন্য)
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $referrer = $data['referrer'] ?? '';
    $clickPos = $data['click_position'] ?? null;
    $clickX = $clickPos ? (int)$clickPos['x'] : null;
    $clickY = $clickPos ? (int)$clickPos['y'] : null;
    
    // ৪. ক্লিক সেভ (আপনার বিদ্যমান clicks টেবিলে)
    $insert = $conn->prepare("INSERT INTO clicks 
        (short_code, long_url, ip_address, referrer, user_agent, browser, os, device_type, 
         is_bot, visitor_fingerprint, visitor_id, cookie_id, local_storage_id, session_id, 
         fingerprint_hash, click_position_x, click_position_y, clicked_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $insert->execute([
        $shortCode,
        $longUrl,
        $ip,
        $referrer,
        $userAgent,
        $data['browser'] ?? null,
        $data['os'] ?? null,
        $data['device_type'] ?? null,
        $isBot,
        $fingerprint,   // ← পুরনো visitor_fingerprint কলামে ফিঙ্গারপ্রিন্ট
        $visitorId,
        $cookieId,
        $localId,
        $sessionId,
        $fingerprint,
        $clickX,
        $clickY
    ]);
    
    $conn->commit();
    echo json_encode(['status' => 'ok', 'visitor_id' => $visitorId, 'is_bot' => $isBot]);
    
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}