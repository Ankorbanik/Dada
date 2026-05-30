<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['manage_access'])) die('Unauthorized');

$code = isset($_GET['code']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['code']) : '';
if (empty($code)) die();

// মোট স্ট্যাটস
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT visitor_fingerprint) as uniques,
    SUM(is_bot) as bots,
    COUNT(*) - SUM(is_bot) as humans
    FROM clicks WHERE short_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// টপ কান্ট্রি (শতাংশসহ)
$stmt = $conn->prepare("SELECT country, COUNT(DISTINCT visitor_fingerprint) as cnt, 
    (COUNT(DISTINCT visitor_fingerprint) / ? * 100) as percentage
    FROM clicks WHERE short_code = ? AND country IS NOT NULL 
    GROUP BY country ORDER BY cnt DESC LIMIT 10");
$stmt->bind_param("is", $stats['uniques'], $code);
$stmt->execute();
$topCountries = $stmt->get_result();

// টপ সিটি (শতাংশসহ)
$stmt = $conn->prepare("SELECT city, COUNT(DISTINCT visitor_fingerprint) as cnt,
    (COUNT(DISTINCT visitor_fingerprint) / ? * 100) as percentage
    FROM clicks WHERE short_code = ? AND city IS NOT NULL 
    GROUP BY city ORDER BY cnt DESC LIMIT 10");
$stmt->bind_param("is", $stats['uniques'], $code);
$stmt->execute();
$topCities = $stmt->get_result();

// ডিভাইস
$stmt = $conn->prepare("SELECT device_type, COUNT(DISTINCT visitor_fingerprint) as cnt,
    (COUNT(DISTINCT visitor_fingerprint) / ? * 100) as percentage
    FROM clicks WHERE short_code = ? GROUP BY device_type");
$stmt->bind_param("is", $stats['uniques'], $code);
$stmt->execute();
$topDevices = $stmt->get_result();

// ব্রাউজার
$stmt = $conn->prepare("SELECT browser, COUNT(DISTINCT visitor_fingerprint) as cnt,
    (COUNT(DISTINCT visitor_fingerprint) / ? * 100) as percentage
    FROM clicks WHERE short_code = ? GROUP BY browser");
$stmt->bind_param("is", $stats['uniques'], $code);
$stmt->execute();
$topBrowsers = $stmt->get_result();

// ওএস
$stmt = $conn->prepare("SELECT os, COUNT(DISTINCT visitor_fingerprint) as cnt,
    (COUNT(DISTINCT visitor_fingerprint) / ? * 100) as percentage
    FROM clicks WHERE short_code = ? GROUP BY os");
$stmt->bind_param("is", $stats['uniques'], $code);
$stmt->execute();
$topOS = $stmt->get_result();

// টপ পারফর্মিং ডেট (সর্বোচ্চ মোট ক্লিকের ৫ দিন)
$stmt = $conn->prepare("SELECT 
    DATE(clicked_at) as click_date,
    COUNT(*) as total_clicks
    FROM clicks 
    WHERE short_code = ?
    GROUP BY DATE(clicked_at)
    ORDER BY total_clicks DESC
    LIMIT 5");
$stmt->bind_param("s", $code);
$stmt->execute();
$topDates = $stmt->get_result();

// সাম্প্রতিক ইউনিক সেশন (শেষ ৫০)
$stmt = $conn->prepare("SELECT ip_address, country, city, device_type, browser, os, is_bot, 
    DATE(clicked_at) as day, COUNT(*) as hits
    FROM clicks WHERE short_code = ?
    GROUP BY ip_address, visitor_fingerprint, DATE(clicked_at)
    ORDER BY day DESC, hits DESC LIMIT 50");
$stmt->bind_param("s", $code);
$stmt->execute();
$uniqueSessions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>লিংক অ্যানালিটিক্স - <?=htmlspecialchars($code)?></title>
    <style>
        body { font-family: 'Segoe UI', system-ui; background: #f8fafc; margin: 0; padding: 20px; }
        .dashboard-container { max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .stats-grid { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .stat-card { background: white; border-radius: 16px; padding: 1rem; flex: 1; text-align: center; border: 1px solid #e2e8f0; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #2563eb; }
        h2 { margin: 0 0 0.5rem 0; font-size: 1.3rem; }
        h3 { margin: 1rem 0 0.5rem; color: #1e293b; border-left: 4px solid #3b82f6; padding-left: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.6rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; font-weight: 600; }
        .badge { background: #e2e8f0; border-radius: 20px; padding: 2px 8px; font-size: 0.7rem; }
        .flex-2col { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .col { flex: 1; min-width: 250px; }
        .text-right { text-align: right; }
        .percentage { font-size: 0.8rem; color: #64748b; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div style="margin-bottom: 1rem;">
        <a href="manage.php" style="background:#3b82f6; color:white; padding:6px 12px; border-radius:20px; text-decoration:none;">← লিংক ম্যানেজার</a>
        <h1 style="margin-top:0.5rem;">📊 অ্যানালিটিক্স: <code><?=htmlspecialchars($code)?></code></h1>
    </div>

    <!-- সারাংশ কার্ড -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?=$stats['total']?></div><div>মোট ক্লিক</div></div>
        <div class="stat-card"><div class="stat-number"><?=$stats['uniques']?></div><div>ইউনিক ভিজিটর</div></div>
        <div class="stat-card"><div class="stat-number"><?=$stats['humans']?></div><div>রিয়েল ভিজিটর</div></div>
        <div class="stat-card"><div class="stat-number"><?=$stats['bots']?></div><div>বট ট্রাফিক</div></div>
    </div>

    <!-- টপ কান্ট্রি ও সিটি (শতাংশসহ) -->
    <div class="flex-2col">
        <div class="card col">
            <h3>🌍 শীর্ষ দেশ (ইউনিক)</h3>
            <table>
                <tr><th>দেশ</th><th>ইউনিক</th><th>শতাংশ</th></tr>
                <?php while($c=$topCountries->fetch_assoc()): ?>
                <tr><td><?=htmlspecialchars($c['country'])?></td><td><?=$c['cnt']?></td><td><?=round($c['percentage'],1)?>%</td></tr>
                <?php endwhile; ?>
            </table>
        </div>
        <div class="card col">
            <h3>🏙️ শীর্ষ শহর (ইউনিক)</h3>
            <table>
                <tr><th>শহর</th><th>ইউনিক</th><th>শতাংশ</th></tr>
                <?php while($c=$topCities->fetch_assoc()): ?>
                <tr><td><?=htmlspecialchars($c['city']??'অজানা')?></td><td><?=$c['cnt']?></td><td><?=round($c['percentage'],1)?>%</td></tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <!-- ডিভাইস, ব্রাউজার, ওএস (শতাংশসহ) -->
    <div class="flex-2col">
        <div class="card col">
            <h3>📱 ডিভাইস</h3>
            <table><?php while($d=$topDevices->fetch_assoc()):?><tr><td><?=$d['device_type']?></td><td><?=$d['cnt']?></td><td><?=round($d['percentage'],1)?>%</td></tr><?php endwhile;?></table>
        </div>
        <div class="card col">
            <h3>🌐 ব্রাউজার</h3>
            <table><?php while($b=$topBrowsers->fetch_assoc()):?><tr><td><?=$b['browser']?></td><td><?=$b['cnt']?></td><td><?=round($b['percentage'],1)?>%</td></tr><?php endwhile;?></table>
        </div>
        <div class="card col">
            <h3>💻 অপারেটিং সিস্টেম</h3>
            <table><?php while($o=$topOS->fetch_assoc()):?><tr><td><?=$o['os']?></td><td><?=$o['cnt']?></td><td><?=round($o['percentage'],1)?>%</td></tr><?php endwhile;?></table>
        </div>
    </div>

    <!-- শীর্ষ পারফর্মিং তারিখ -->
    <div class="card">
        <h3>🏆 সবচেয়ে ব্যস্ত দিন (মোট ক্লিক অনুযায়ী)</h3>
        <table>
            <tr><th>তারিখ</th><th>মোট ক্লিক</th></tr>
            <?php while($d=$topDates->fetch_assoc()): ?>
            <tr><td><?=date('d M Y', strtotime($d['click_date']))?></td><td><?=$d['total_clicks']?></td></tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- সাম্প্রতিক ইউনিক সেশন টেবিল -->
    <div class="card">
        <h3>🆕 সাম্প্রতিক ইউনিক সেশন</h3>
        <div style="overflow-x:auto;">
            <table>
                <tr><th>আইপি</th><th>দেশ</th><th>শহর</th><th>ডিভাইস</th><th>ব্রাউজার</th><th>OS</th><th>বট</th><th>তারিখ</th><th>হিট</th></tr>
                <?php while($r=$uniqueSessions->fetch_assoc()): ?>
                <tr>
                    <td><?=$r['ip_address']?></td>
                    <td><?=$r['country']?:'-'?></td>
                    <td><?=$r['city']?:'-'?></td>
                    <td><?=$r['device_type']?></td>
                    <td><?=$r['browser']?></td>
                    <td><?=$r['os']?></td>
                    <td><?=$r['is_bot']?'হ্যাঁ':'না'?></td>
                    <td><?=$r['day']?></td>
                    <td><?=$r['hits']?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>