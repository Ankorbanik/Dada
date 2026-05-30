<?php
header('Content-Type: application/json');
require_once 'db.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/";

function generateShortCode($length = 3) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($chars), 0, $length);
}

function isUniqueCode($conn, $code) {
    $stmt = $conn->prepare("SELECT id FROM urls WHERE short_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();
    $count = $stmt->num_rows;
    $stmt->close();
    return $count === 0;
}

function validateUrl($url) {
    $url = trim($url);
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'http://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL);
}

function validateCustomCode($code) {
    return preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $code);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$long_url = isset($input['url']) ? $input['url'] : '';
$custom_code = isset($input['custom_code']) ? trim($input['custom_code']) : '';

$valid_url = validateUrl($long_url);
if (!$valid_url) {
    echo json_encode(['success' => false, 'error' => 'সঠিক URL দিন (http/https সহ)']);
    exit;
}

// ডুপ্লিকেট URL চেক - যদি আগে থেকে থাকে তাহলে সেই শর্ট কোড রিটার্ন
$stmt = $conn->prepare("SELECT short_code FROM urls WHERE long_url = ?");
$stmt->bind_param("s", $valid_url);
$stmt->execute();
$stmt->bind_result($existing_code);
if ($stmt->fetch()) {
    $stmt->close();
    echo json_encode(['success' => true, 'short_url' => $base_url . $existing_code]);
    exit;
}
$stmt->close();

if ($custom_code !== '') {
    if (!validateCustomCode($custom_code)) {
        echo json_encode(['success' => false, 'error' => 'কাস্টম কোডে শুধু ইংরেজি অক্ষর, সংখ্যা, ড্যাশ (-) ও আন্ডারস্কোর (_) ব্যবহার করুন (১-৫০ অক্ষর)']);
        exit;
    }
    if (!isUniqueCode($conn, $custom_code)) {
        echo json_encode(['success' => false, 'error' => 'এই কাস্টম কোডটি ইতিমধ্যে ব্যবহৃত']);
        exit;
    }
    $short_code = $custom_code;
} else {
    $short_code = '';
    $length = 3;
    $maxAttempts = 20;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $code = generateShortCode($length);
        if (isUniqueCode($conn, $code)) {
            $short_code = $code;
            break;
        }
        if ($i == $maxAttempts - 1 && $length < 6) {
            $length++;
            $i = 0;
        }
    }
    if (!$short_code) {
        echo json_encode(['success' => false, 'error' => 'সার্ভার ব্যস্ত, আবার চেষ্টা করুন']);
        exit;
    }
}

$insert = $conn->prepare("INSERT INTO urls (short_code, long_url) VALUES (?, ?)");
$insert->bind_param("ss", $short_code, $valid_url);
if ($insert->execute()) {
    echo json_encode(['success' => true, 'short_url' => $base_url . $short_code]);
} else {
    echo json_encode(['success' => false, 'error' => 'ডাটাবেজে সংরক্ষণ ব্যর্থ']);
}
$insert->close();
$conn->close();
?>