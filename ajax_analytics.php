<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['manage_access'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';
$short_code = isset($_GET['short_code']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['short_code']) : null;

header('Content-Type: application/json');

// Helper to execute query and return result
function executeQuery($sql, $params = [], $types = '') {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// ------------------- PER-LINK ACTIONS (for get_analytics.php) -------------------
if ($action == 'day_details' && $short_code) {
    $date = $_GET['date'] ?? '';
    $dateFormatted = date('Y-m-d', strtotime($date));
    // Unique = distinct IPs on that date
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE short_code = ? AND DATE(clicked_at) = ?",
                         [$short_code, $dateFormatted], 'ss');
    $stats = $res->fetch_assoc();
    
    $hourly = executeQuery("SELECT HOUR(clicked_at) as hour, COUNT(*) as cnt 
                            FROM clicks WHERE short_code = ? AND DATE(clicked_at) = ? 
                            GROUP BY hour ORDER BY hour",
                            [$short_code, $dateFormatted], 'ss');
    $hourlyData = [];
    while ($h = $hourly->fetch_assoc()) $hourlyData[$h['hour']] = $h['cnt'];
    
    $topCountries = executeQuery("SELECT country, COUNT(DISTINCT ip_address) as cnt 
                                  FROM clicks WHERE short_code = ? AND DATE(clicked_at) = ? 
                                  AND country IS NOT NULL GROUP BY country ORDER BY cnt DESC LIMIT 5",
                                  [$short_code, $dateFormatted], 'ss');
    $countries = [];
    while ($c = $topCountries->fetch_assoc()) $countries[] = $c;
    
    $topReferrers = executeQuery("SELECT 
        CASE 
            WHEN referrer LIKE '%facebook%' THEN 'Facebook'
            WHEN referrer LIKE '%twitter%' THEN 'Twitter'
            ELSE 'Other'
        END as source,
        COUNT(*) as clicks 
        FROM clicks WHERE short_code = ? AND DATE(clicked_at) = ? 
        GROUP BY source ORDER BY clicks DESC LIMIT 5",
        [$short_code, $dateFormatted], 'ss');
    $refs = [];
    while ($r = $topReferrers->fetch_assoc()) $refs[] = $r;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'hourly' => $hourlyData,
        'top_countries' => $countries,
        'top_referrers' => $refs
    ]);
}
elseif ($action == 'source_details' && $short_code) {
    $source = $_GET['source'] ?? '';
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE short_code = ? AND 
                         CASE 
                             WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                             WHEN referrer LIKE '%twitter%' THEN 'Twitter'
                             ELSE 'Direct'
                         END = ?",
                         [$short_code, $source], 'ss');
    $stats = $res->fetch_assoc();
    
    $recent = executeQuery("SELECT ip_address, country, DATE(clicked_at) as date 
                            FROM clicks WHERE short_code = ? AND 
                            CASE 
                                WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                                WHEN referrer LIKE '%twitter%' THEN 'Twitter'
                                ELSE 'Direct'
                            END = ? 
                            ORDER BY clicked_at DESC LIMIT 10",
                            [$short_code, $source], 'ss');
    $recents = [];
    while ($r = $recent->fetch_assoc()) $recents[] = $r;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'recent' => $recents
    ]);
}
elseif ($action == 'hour_details' && $short_code) {
    $hour = (int)$_GET['hour'];
    // Last 24 hours, distinct IPs in that hour
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE short_code = ? AND HOUR(clicked_at) = ? 
                         AND clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                         [$short_code, $hour], 'si');
    $stats = $res->fetch_assoc();
    
    $visitors = executeQuery("SELECT ip_address, country, device_type 
                              FROM clicks WHERE short_code = ? AND HOUR(clicked_at) = ? 
                              AND clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                              GROUP BY ip_address LIMIT 20",
                              [$short_code, $hour], 'si');
    $visList = [];
    while ($v = $visitors->fetch_assoc()) $visList[] = $v;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'visitors' => $visList
    ]);
}
elseif ($action == 'weekday_details' && $short_code) {
    $weekdayMap = ['রবি'=>1,'সোম'=>2,'মঙ্গল'=>3,'বুধ'=>4,'বৃহস্পতি'=>5,'শুক্র'=>6,'শনি'=>7];
    $dow = $weekdayMap[$_GET['weekday']] ?? 1;
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE short_code = ? AND DAYOFWEEK(clicked_at) = ?",
                         [$short_code, $dow], 'si');
    $stats = $res->fetch_assoc();
    
    $links = executeQuery("SELECT short_code as code, COUNT(*) as clicks 
                           FROM clicks WHERE short_code = ? AND DAYOFWEEK(clicked_at) = ? 
                           GROUP BY short_code ORDER BY clicks DESC LIMIT 5",
                           [$short_code, $dow], 'si');
    $topLinks = [];
    while ($l = $links->fetch_assoc()) $topLinks[] = $l;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'top_links' => $topLinks
    ]);
}
elseif ($action == 'device_details' && $short_code) {
    $device = $_GET['device'] ?? '';
    // For device details (all time), keep fingerprint or IP? We'll use IP for consistency
    $res = executeQuery("SELECT COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE short_code = ? AND device_type = ?",
                         [$short_code, $device], 'ss');
    $stats = $res->fetch_assoc();
    
    $recent = executeQuery("SELECT ip_address, browser, os, DATE(clicked_at) as date 
                            FROM clicks WHERE short_code = ? AND device_type = ? 
                            GROUP BY ip_address ORDER BY clicked_at DESC LIMIT 10",
                            [$short_code, $device], 'ss');
    $recents = [];
    while ($r = $recent->fetch_assoc()) $recents[] = $r;
    
    echo json_encode([
        'uniques' => $stats['uniques'],
        'recent' => $recents
    ]);
}
elseif ($action == 'country_cities' && $short_code) {
    $country = $_GET['country'] ?? '';
    $cities = executeQuery("SELECT city, COUNT(DISTINCT ip_address) as uniques, COUNT(*) as total_clicks 
                            FROM clicks WHERE short_code = ? AND country = ? AND city IS NOT NULL 
                            GROUP BY city ORDER BY uniques DESC",
                            [$short_code, $country], 'ss');
    $cityList = [];
    while ($c = $cities->fetch_assoc()) $cityList[] = $c;
    echo json_encode(['cities' => $cityList]);
}
elseif ($action == 'city_sessions' && $short_code) {
    $country = $_GET['country'] ?? '';
    $city = $_GET['city'] ?? '';
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE short_code = ? AND country = ? AND city = ?",
                         [$short_code, $country, $city], 'sss');
    $stats = $res->fetch_assoc();
    
    $recent = executeQuery("SELECT ip_address, device_type, browser, DATE(clicked_at) as date 
                            FROM clicks WHERE short_code = ? AND country = ? AND city = ? 
                            ORDER BY clicked_at DESC LIMIT 20",
                            [$short_code, $country, $city], 'sss');
    $recents = [];
    while ($r = $recent->fetch_assoc()) $recents[] = $r;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'recent' => $recents
    ]);
}

// ------------------- OVERALL ACTIONS (for overview_analytics.php) -------------------
elseif ($action == 'overall_day_details') {
    $date = $_GET['date'] ?? '';
    $dateFormatted = date('Y-m-d', strtotime($date));
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE DATE(clicked_at) = ?",
                         [$dateFormatted], 's');
    $stats = $res->fetch_assoc();
    
    $topLinks = executeQuery("SELECT short_code as code, COUNT(*) as clicks 
                              FROM clicks WHERE DATE(clicked_at) = ? 
                              GROUP BY short_code ORDER BY clicks DESC LIMIT 5",
                              [$dateFormatted], 's');
    $links = [];
    while ($l = $topLinks->fetch_assoc()) $links[] = $l;
    
    $topCountries = executeQuery("SELECT country, COUNT(DISTINCT ip_address) as cnt 
                                  FROM clicks WHERE DATE(clicked_at) = ? AND country IS NOT NULL 
                                  GROUP BY country ORDER BY cnt DESC LIMIT 5",
                                  [$dateFormatted], 's');
    $countries = [];
    while ($c = $topCountries->fetch_assoc()) $countries[] = $c;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'top_links' => $links,
        'top_countries' => $countries
    ]);
}
elseif ($action == 'overall_source_details') {
    $source = $_GET['source'] ?? '';
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE 
                         CASE 
                             WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                             WHEN referrer LIKE '%twitter%' THEN 'Twitter'
                             ELSE 'Direct'
                         END = ?",
                         [$source], 's');
    $stats = $res->fetch_assoc();
    
    $recent = executeQuery("SELECT ip_address, short_code as code, DATE(clicked_at) as date 
                            FROM clicks WHERE 
                            CASE 
                                WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                                WHEN referrer LIKE '%twitter%' THEN 'Twitter'
                                ELSE 'Direct'
                            END = ? 
                            ORDER BY clicked_at DESC LIMIT 15",
                            [$source], 's');
    $recents = [];
    while ($r = $recent->fetch_assoc()) $recents[] = $r;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'recent' => $recents
    ]);
}
elseif ($action == 'overall_hour_details') {
    $hour = (int)$_GET['hour'];
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE HOUR(clicked_at) = ? 
                         AND clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                         [$hour], 'i');
    $stats = $res->fetch_assoc();
    
    $visitors = executeQuery("SELECT ip_address, short_code as code, country 
                              FROM clicks WHERE HOUR(clicked_at) = ? 
                              AND clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                              GROUP BY ip_address LIMIT 20",
                              [$hour], 'i');
    $visList = [];
    while ($v = $visitors->fetch_assoc()) $visList[] = $v;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'visitors' => $visList
    ]);
}
elseif ($action == 'overall_weekday_details') {
    $weekdayMap = ['রবি'=>1,'সোম'=>2,'মঙ্গল'=>3,'বুধ'=>4,'বৃহস্পতি'=>5,'শুক্র'=>6,'শনি'=>7];
    $dow = $weekdayMap[$_GET['weekday']] ?? 1;
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE DAYOFWEEK(clicked_at) = ?",
                         [$dow], 'i');
    $stats = $res->fetch_assoc();
    
    $links = executeQuery("SELECT short_code as code, COUNT(*) as clicks 
                           FROM clicks WHERE DAYOFWEEK(clicked_at) = ? 
                           GROUP BY short_code ORDER BY clicks DESC LIMIT 5",
                           [$dow], 'i');
    $topLinks = [];
    while ($l = $links->fetch_assoc()) $topLinks[] = $l;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'top_links' => $topLinks
    ]);
}
elseif ($action == 'overall_device_details') {
    $device = $_GET['device'] ?? '';
    $res = executeQuery("SELECT COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE device_type = ?",
                         [$device], 's');
    $stats = $res->fetch_assoc();
    
    $recent = executeQuery("SELECT ip_address, browser, DATE(clicked_at) as date 
                            FROM clicks WHERE device_type = ? 
                            GROUP BY ip_address ORDER BY clicked_at DESC LIMIT 10",
                            [$device], 's');
    $recents = [];
    while ($r = $recent->fetch_assoc()) $recents[] = $r;
    
    echo json_encode([
        'uniques' => $stats['uniques'],
        'recent' => $recents
    ]);
}
elseif ($action == 'overall_country_cities') {
    $country = $_GET['country'] ?? '';
    $cities = executeQuery("SELECT city, COUNT(DISTINCT ip_address) as uniques, COUNT(*) as total_clicks 
                            FROM clicks WHERE country = ? AND city IS NOT NULL 
                            GROUP BY city ORDER BY uniques DESC",
                            [$country], 's');
    $cityList = [];
    while ($c = $cities->fetch_assoc()) $cityList[] = $c;
    echo json_encode(['cities' => $cityList]);
}
elseif ($action == 'overall_city_sessions') {
    $country = $_GET['country'] ?? '';
    $city = $_GET['city'] ?? '';
    $res = executeQuery("SELECT COUNT(*) as total, COUNT(DISTINCT ip_address) as uniques 
                         FROM clicks WHERE country = ? AND city = ?",
                         [$country, $city], 'ss');
    $stats = $res->fetch_assoc();
    
    $recent = executeQuery("SELECT ip_address, short_code as code, DATE(clicked_at) as date 
                            FROM clicks WHERE country = ? AND city = ? 
                            ORDER BY clicked_at DESC LIMIT 20",
                            [$country, $city], 'ss');
    $recents = [];
    while ($r = $recent->fetch_assoc()) $recents[] = $r;
    
    echo json_encode([
        'total_clicks' => $stats['total'],
        'uniques' => $stats['uniques'],
        'recent' => $recents
    ]);
}
else {
    echo json_encode(['error' => 'Invalid action']);
}
?>