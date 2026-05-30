<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['manage_access'])) die('Unauthorized');

// ওভারঅল স্ট্যাটস
$totalClicks = $conn->query("SELECT COUNT(*) FROM clicks")->fetch_row()[0];
$totalUniques = $conn->query("SELECT COUNT(DISTINCT visitor_fingerprint) FROM clicks WHERE is_bot=0")->fetch_row()[0];
$totalBots = $conn->query("SELECT COUNT(*) FROM clicks WHERE is_bot=1")->fetch_row()[0];
$totalLinks = $conn->query("SELECT COUNT(*) FROM urls")->fetch_row()[0];

// টপ কান্ট্রি (ওভারঅল) শতাংশসহ
$topCountries = $conn->query("SELECT country, COUNT(DISTINCT visitor_fingerprint) as cnt, 
    (COUNT(DISTINCT visitor_fingerprint) / $totalUniques * 100) as percentage
    FROM clicks WHERE country IS NOT NULL GROUP BY country ORDER BY cnt DESC LIMIT 10");

$topCities = $conn->query("SELECT city, COUNT(DISTINCT visitor_fingerprint) as cnt,
    (COUNT(DISTINCT visitor_fingerprint) / $totalUniques * 100) as percentage
    FROM clicks WHERE city IS NOT NULL GROUP BY city ORDER BY cnt DESC LIMIT 10");

// ----- FIX: ডিভাইস, ব্রাউজার, ওএস - সর্বশেষ ক্লিক অনুযায়ী (সঠিক শতাংশ) -----
$topDevices = $conn->query("
    SELECT device_type, COUNT(*) as cnt, (COUNT(*) / $totalUniques * 100) as percentage
    FROM (
        SELECT c1.visitor_fingerprint, c1.device_type
        FROM clicks c1
        INNER JOIN (
            SELECT visitor_fingerprint, MAX(clicked_at) as max_clicked
            FROM clicks WHERE is_bot=0 AND device_type IS NOT NULL
            GROUP BY visitor_fingerprint
        ) latest ON c1.visitor_fingerprint = latest.visitor_fingerprint AND c1.clicked_at = latest.max_clicked
        WHERE c1.is_bot=0
    ) latest_devices
    GROUP BY device_type
");

$topBrowsers = $conn->query("
    SELECT browser, COUNT(*) as cnt, (COUNT(*) / $totalUniques * 100) as percentage
    FROM (
        SELECT c1.visitor_fingerprint, c1.browser
        FROM clicks c1
        INNER JOIN (
            SELECT visitor_fingerprint, MAX(clicked_at) as max_clicked
            FROM clicks WHERE is_bot=0 AND browser IS NOT NULL
            GROUP BY visitor_fingerprint
        ) latest ON c1.visitor_fingerprint = latest.visitor_fingerprint AND c1.clicked_at = latest.max_clicked
        WHERE c1.is_bot=0
    ) latest_browsers
    GROUP BY browser
");

$topOS = $conn->query("
    SELECT os, COUNT(*) as cnt, (COUNT(*) / $totalUniques * 100) as percentage
    FROM (
        SELECT c1.visitor_fingerprint, c1.os
        FROM clicks c1
        INNER JOIN (
            SELECT visitor_fingerprint, MAX(clicked_at) as max_clicked
            FROM clicks WHERE is_bot=0 AND os IS NOT NULL
            GROUP BY visitor_fingerprint
        ) latest ON c1.visitor_fingerprint = latest.visitor_fingerprint AND c1.clicked_at = latest.max_clicked
        WHERE c1.is_bot=0
    ) latest_os
    GROUP BY os
");

// গত ৩০ দিনের দৈনিক ক্লিক (সব লিংক মিলিয়ে)
$dailyTrend = $conn->query("SELECT DATE(clicked_at) as click_date, COUNT(DISTINCT visitor_fingerprint) as uniques, COUNT(*) as total_clicks
    FROM clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(clicked_at) ORDER BY click_date ASC");

// রেফারার সোর্স (ওভারঅল)
$referrers = $conn->query("SELECT 
    CASE 
        WHEN referrer LIKE '%facebook.com%' OR referrer LIKE '%fb.com%' THEN 'Facebook'
        WHEN referrer LIKE '%twitter.com%' OR referrer LIKE '%x.com%' THEN 'Twitter'
        WHEN referrer LIKE '%youtube.com%' THEN 'YouTube'
        WHEN referrer LIKE '%linkedin.com%' THEN 'LinkedIn'
        WHEN referrer LIKE '%instagram.com%' THEN 'Instagram'
        WHEN referrer LIKE '%pinterest.com%' THEN 'Pinterest'
        WHEN referrer LIKE '%whatsapp.com%' THEN 'WhatsApp'
        WHEN referrer LIKE '%tiktok.com%' THEN 'TikTok'
        WHEN referrer LIKE '%reddit.com%' THEN 'Reddit'
        WHEN referrer LIKE '%google.com%' OR referrer LIKE '%google.' THEN 'Google Search'
        WHEN referrer LIKE '%bing.com%' THEN 'Bing'
        WHEN referrer LIKE '%yahoo.com%' THEN 'Yahoo'
        WHEN referrer != '' THEN 'Other Website'
        ELSE 'Direct'
    END as source,
    COUNT(DISTINCT visitor_fingerprint) as uniques,
    COUNT(*) as total_clicks
    FROM clicks 
    GROUP BY source
    ORDER BY uniques DESC");

// টপ পারফর্মিং লিংক
$topLinks = $conn->query("SELECT u.short_code, u.long_url, 
    COUNT(c.id) as total_clicks, 
    COUNT(DISTINCT c.visitor_fingerprint) as uniques
    FROM urls u LEFT JOIN clicks c ON u.short_code = c.short_code 
    GROUP BY u.short_code ORDER BY total_clicks DESC LIMIT 10");

// টপ পারফর্মিং ডেট (সব লিংক মিলিয়ে)
$topDates = $conn->query("SELECT DATE(clicked_at) as click_date, COUNT(*) as total_clicks 
    FROM clicks GROUP BY DATE(clicked_at) ORDER BY total_clicks DESC LIMIT 5");

// NEW: Get overall country data for world map
$mapCountries = $conn->query("SELECT country, COUNT(DISTINCT visitor_fingerprint) as visitors FROM clicks WHERE country IS NOT NULL GROUP BY country");
$countryData = [];
while($row = $mapCountries->fetch_assoc()) {
    $countryData[$row['country']] = (int)$row['visitors'];
}

// ---- নতুন: পাই চার্টের জন্য দেশ ও ভিজিটর ডাটা ----
$pieCountries = $conn->query("SELECT country, COUNT(DISTINCT visitor_fingerprint) as visitors FROM clicks WHERE country IS NOT NULL GROUP BY country");
$pieLabels = []; $pieValues = [];
while($row = $pieCountries->fetch_assoc()) {
    $pieLabels[] = $row['country'];
    $pieValues[] = (int)$row['visitors'];
}
$totalUniquesForPie = array_sum($pieValues);

// ========== SAFE NEW DETAILED ANALYTICS (with column existence check) ==========
// Check if column exists to avoid errors
$hasConversion = $conn->query("SHOW COLUMNS FROM clicks LIKE 'is_conversion'")->num_rows > 0;
$hasPerformance = $conn->query("SHOW COLUMNS FROM clicks LIKE 'page_load_time'")->num_rows > 0;
$hasUtm = $conn->query("SHOW COLUMNS FROM clicks LIKE 'utm_source'")->num_rows > 0;
$hasDeviceModel = $conn->query("SHOW COLUMNS FROM clicks LIKE 'device_model'")->num_rows > 0;
$hasBrowserVersion = $conn->query("SHOW COLUMNS FROM clicks LIKE 'browser_version'")->num_rows > 0;

// Conversion & performance (only if columns exist)
$conversionStats = ['total_conversions'=>0, 'conversion_rate'=>0];
if($hasConversion) {
    $conv = $conn->query("SELECT COUNT(*) as total_conversions, (COUNT(*) / NULLIF((SELECT COUNT(*) FROM clicks WHERE is_bot=0), 0) * 100) as conversion_rate FROM clicks WHERE is_conversion = 1 AND is_bot=0");
    if($conv) $conversionStats = $conv->fetch_assoc();
}

$performanceStats = ['avg_load_time'=>0, 'avg_click_x'=>0, 'avg_click_y'=>0];
if($hasPerformance) {
    $perf = $conn->query("SELECT AVG(page_load_time) as avg_load_time, AVG(click_position_x) as avg_click_x, AVG(click_position_y) as avg_click_y FROM clicks WHERE is_bot=0 AND page_load_time IS NOT NULL");
    if($perf) $performanceStats = $perf->fetch_assoc();
}

// UTM breakdown (safe)
$utmSources = $utmMediums = $utmCampaigns = [];
if($hasUtm) {
    $utmSources = $conn->query("SELECT utm_source, COUNT(DISTINCT visitor_fingerprint) as uniques, COUNT(*) as clicks FROM clicks WHERE utm_source IS NOT NULL AND utm_source != '' GROUP BY utm_source ORDER BY uniques DESC LIMIT 10");
    $utmMediums = $conn->query("SELECT utm_medium, COUNT(DISTINCT visitor_fingerprint) as uniques, COUNT(*) as clicks FROM clicks WHERE utm_medium IS NOT NULL AND utm_medium != '' GROUP BY utm_medium ORDER BY uniques DESC LIMIT 10");
    $utmCampaigns = $conn->query("SELECT utm_campaign, COUNT(DISTINCT visitor_fingerprint) as uniques, COUNT(*) as clicks FROM clicks WHERE utm_campaign IS NOT NULL AND utm_campaign != '' GROUP BY utm_campaign ORDER BY uniques DESC LIMIT 10");
}

// Device models & browser versions (safe)
$deviceModels = $browserVersions = [];
if($hasDeviceModel) {
    $deviceModels = $conn->query("SELECT device_model, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE device_model IS NOT NULL AND device_model != '' GROUP BY device_model ORDER BY uniques DESC LIMIT 10");
}
if($hasBrowserVersion) {
    $browserVersions = $conn->query("SELECT browser, browser_version, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE browser_version IS NOT NULL GROUP BY browser, browser_version ORDER BY uniques DESC LIMIT 15");
}

// Hourly trend for last 7 days (always safe)
$hourly7Days = $conn->query("SELECT DATE(clicked_at) as dt, HOUR(clicked_at) as hr, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_bot=0 GROUP BY dt, hr ORDER BY dt, hr");

// Heatmap data (always safe)
$heatmapData = array_fill(0,7,array_fill(0,24,0));
$heatRes = $conn->query("SELECT DAYOFWEEK(clicked_at)-1 as dow, HOUR(clicked_at) as hr, COUNT(DISTINCT visitor_fingerprint) as cnt FROM clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_bot=0 GROUP BY dow, hr");
while($r=$heatRes->fetch_assoc()) $heatmapData[$r['dow']][$r['hr']] = $r['cnt'];

// Top journeys (always safe)
$journeyQuery = "SELECT visitor_fingerprint, GROUP_CONCAT(short_code ORDER BY clicked_at SEPARATOR ' → ') as path FROM clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_bot=0 GROUP BY visitor_fingerprint HAVING COUNT(*) > 1";
$journeyRes = $conn->query($journeyQuery);
$paths = [];
while($row=$journeyRes->fetch_assoc()) {
    $p = explode(' → ', $row['path']);
    $p = array_slice($p, 0, 3);
    $key = implode(' → ', $p);
    $paths[$key] = ($paths[$key]??0)+1;
}
arsort($paths);
$topJourneys = array_slice($paths, 0, 10);

// Comparison data (always safe)
$start30 = date('Y-m-d', strtotime('-30 days'));
$end30 = date('Y-m-d');
$prev_start = date('Y-m-d', strtotime("$start30 - 30 days"));
$prev_end = date('Y-m-d', strtotime("$end30 - 30 days"));
$currentPeriod = $conn->query("SELECT COUNT(*) as clicks, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE clicked_at BETWEEN '$start30 00:00:00' AND '$end30 23:59:59' AND is_bot=0")->fetch_assoc();
$prevPeriod = $conn->query("SELECT COUNT(*) as clicks, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE clicked_at BETWEEN '$prev_start 00:00:00' AND '$prev_end 23:59:59' AND is_bot=0")->fetch_assoc();
$clicks_change = round(($currentPeriod['clicks'] - $prevPeriod['clicks']) / max($prevPeriod['clicks'],1) * 100,1);
$uniques_change = round(($currentPeriod['uniques'] - $prevPeriod['uniques']) / max($prevPeriod['uniques'],1) * 100,1);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>সম্মিলিত অ্যানালিটিক্স ড্যাশবোর্ড</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        body { background:#f4f7f9; font-family: 'Segoe UI', sans-serif; margin:0; padding:2rem; }
        .container { max-width: 1400px; margin:0 auto; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; }
        .card { background:white; border-radius:1rem; padding:1.5rem; margin-bottom:1.5rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #e2e8f0; }
        .stats-grid { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
        .stat-card { flex:1; background:white; border-radius:1rem; padding:1rem; text-align:center; border:1px solid #e2e8f0; }
        .stat-number { font-size:2rem; font-weight:bold; color:#1d4ed8; }
        .btn-back { background:#3b82f6; color:white; padding:0.5rem 1rem; border-radius:2rem; text-decoration:none; display:inline-block; }
        .flex-2col { display:flex; gap:1.5rem; flex-wrap:wrap; }
        .col { flex:1; min-width:250px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:0.75rem; text-align:left; border-bottom:1px solid #e2e8f0; }
        th { background:#f1f5f9; }
        h3 { margin:0 0 1rem 0; border-left:4px solid #3b82f6; padding-left:12px; }
        .percentage { font-size:0.8rem; color:#64748b; }
        .heatmap-table { width:100%; border-collapse:collapse; font-size:0.75rem; text-align:center; }
        .heatmap-table td, .heatmap-table th { border:1px solid #ddd; padding:4px; }
        .heatmap-cell { background-color:#f0f9ff; }
        .realtime-online { font-size:1.2rem; font-weight:bold; color:#10b981; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 1000px; border-radius: 16px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-chart-line"></i> সম্মিলিত অ্যানালিটিক্স (সব লিংক)</h1>
        <div>
            <a href="manage.php" class="btn-back"><i class="fas fa-arrow-left"></i> লিংক ম্যানেজার</a>
        </div>
    </div>

    <!-- সারাংশ কার্ড -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?=$totalLinks?></div><div>মোট লিংক</div></div>
        <div class="stat-card"><div class="stat-number"><?=$totalClicks?></div><div>মোট ক্লিক</div></div>
        <div class="stat-card"><div class="stat-number"><?=$totalUniques?></div><div>ইউনিক ভিজিটর</div></div>
        <div class="stat-card"><div class="stat-number"><?=$totalBots?></div><div>বট ট্রাফিক</div></div>
    </div>

    <!-- ========== NEW: Real-time online + Comparison + Performance ========== -->
    <div class="flex-2col">
        <div class="card col">
            <h3><i class="fas fa-eye"></i> রিয়েল টাইম অনলাইন ভিজিটর</h3>
            <div id="realtimeOnline" class="realtime-online">লোড হচ্ছে...</div>
            <p class="percentage">গত ৫ মিনিটে সক্রিয় ইউনিক ভিজিটর</p>
        </div>
        <div class="card col">
            <h3><i class="fas fa-chart-line"></i> পূর্ববর্তী সময়ের সাথে তুলনা (গত ৩০ দিন)</h3>
            <p>ক্লিক: <strong><?=$currentPeriod['clicks']?></strong> (<?=$clicks_change>=0?'+':''?><?=$clicks_change?>%)<br>
            পূর্ববর্তী: <?=$prevPeriod['clicks']?></p>
            <p>ইউনিক: <strong><?=$currentPeriod['uniques']?></strong> (<?=$uniques_change>=0?'+':''?><?=$uniques_change?>%)<br>
            পূর্ববর্তী: <?=$prevPeriod['uniques']?></p>
        </div>
        <div class="card col">
            <h3><i class="fas fa-tachometer-alt"></i> পারফরম্যান্স & কনভার্সন</h3>
            <p>গড় পেজ লোড টাইম: <?=round($performanceStats['avg_load_time']??0,2)?> ms</p>
            <p>গড় ক্লিক পজিশন: (<?=round($performanceStats['avg_click_x']??0,0)?>, <?=round($performanceStats['avg_click_y']??0,0)?>)</p>
            <p>কনভার্সন রেট: <?=round($conversionStats['conversion_rate']??0,2)?>% (<?=($conversionStats['total_conversions']??0)?> টি)</p>
        </div>
    </div>

    <!-- ========== NEW: ক্লিক হিটম্যাপ (সপ্তাহের দিন + ঘন্টা) ========== -->
    <div class="card">
        <h3><i class="fas fa-fire"></i> ক্লিক হিটম্যাপ (সপ্তাহের দিন X ঘন্টা - গত ৩০ দিন)</h3>
        <div style="overflow-x:auto;">
            <table class="heatmap-table">
                <thead>
                    <tr><th>দিন/ঘণ্টা</th><?php for($h=0;$h<24;$h++) echo "<th>{$h}:00</th>"; ?></tr>
                </thead>
                <tbody>
                <?php 
                $days = ['রবি','সোম','মঙ্গল','বুধ','বৃহস্পতি','শুক্র','শনি'];
                $maxHeat = 0;
                foreach($heatmapData as $d) $maxHeat = max($maxHeat, max($d));
                for($d=0;$d<7;$d++):
                    echo "<tr><th>{$days[$d]}</th>";
                    for($h=0;$h<24;$h++):
                        $val = $heatmapData[$d][$h];
                        $intensity = $maxHeat>0 ? round($val/$maxHeat*100) : 0;
                        $bg = "background-color: rgba(59,130,246, ".($intensity/100).");";
                        echo "<td class='heatmap-cell' style='$bg'>{$val}</td>";
                    endfor;
                    echo "</tr>";
                endfor;
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========== NEW: শীর্ষ ভিজিটর জার্নি ========== -->
    <div class="card">
        <h3><i class="fas fa-route"></i> শীর্ষ ভিজিটর যাত্রাপথ (সর্বাধিক অনুসৃত পথ - গত ৩০ দিন)</h3>
        <div style="overflow-x:auto;">
            <table style="width:100%">
                <thead><tr><th>পথ (শর্ট কোড)</th><th>ভিজিটর সংখ্যা</th></tr></thead>
                <tbody>
                <?php foreach($topJourneys as $path=>$count): ?>
                    <tr><td><?=htmlspecialchars($path)?></td><td><?=$count?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========== NEW: UTM ক্যাম্পেইন বিশ্লেষণ (যদি ডাটা থাকে) ========== -->
    <?php if($hasUtm && $utmSources && $utmSources->num_rows > 0): ?>
    <div class="card">
        <h3><i class="fas fa-tags"></i> UTM ক্যাম্পেইন বিশ্লেষণ</h3>
        <div class="flex-2col">
            <div class="col">
                <h4>সোর্স</h4>
                <table><?php while($u=$utmSources->fetch_assoc()):?> <tr><td><?=htmlspecialchars($u['utm_source'])?></td><td><?=$u['uniques']?> ইউনিক</td><td><?=$u['clicks']?> ক্লিক</td></tr><?php endwhile;?></table>
            </div>
            <div class="col">
                <h4>মিডিয়াম</h4>
                <table><?php while($u=$utmMediums->fetch_assoc()):?> <tr><td><?=htmlspecialchars($u['utm_medium'])?></td><td><?=$u['uniques']?> ইউনিক</td><td><?=$u['clicks']?> ক্লিক</td></tr><?php endwhile;?></table>
            </div>
            <div class="col">
                <h4>ক্যাম্পেইন</h4>
                <table><?php while($u=$utmCampaigns->fetch_assoc()):?> <tr><td><?=htmlspecialchars($u['utm_campaign'])?></td><td><?=$u['uniques']?> ইউনিক</td><td><?=$u['clicks']?> ক্লিক</td></tr><?php endwhile;?></table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========== NEW: ডিভাইস মডেল এবং ব্রাউজার ভার্সন (যদি ডাটা থাকে) ========== -->
    <div class="flex-2col">
        <?php if($hasDeviceModel && $deviceModels && $deviceModels->num_rows > 0): ?>
        <div class="card col">
            <h3><i class="fas fa-mobile-alt"></i> ডিভাইস মডেল (টপ ১০)</h3>
            <table><?php while($d=$deviceModels->fetch_assoc()):?> <tr><td><?=htmlspecialchars($d['device_model'])?></td><td><?=$d['uniques']?> ইউনিক</td></tr><?php endwhile;?></table>
        </div>
        <?php endif; ?>
        <?php if($hasBrowserVersion && $browserVersions && $browserVersions->num_rows > 0): ?>
        <div class="card col">
            <h3><i class="fab fa-chrome"></i> ব্রাউজার ভার্সন (টপ ১৫)</h3>
            <table><?php while($b=$browserVersions->fetch_assoc()):?> <tr><td><?=htmlspecialchars($b['browser'])?> <?=htmlspecialchars($b['browser_version'])?></td><td><?=$b['uniques']?> ইউনিক</td></tr><?php endwhile;?></table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ========== NEW: গত ৭ দিনের ঘণ্টাভিত্তিক প্রবণতা ========== -->
    <div class="card">
        <h3><i class="fas fa-chart-line"></i> গত ৭ দিনে প্রতি ঘণ্টায় ইউনিক ভিজিটর (লাইন চার্ট)</h3>
        <canvas id="hourly7DaysChart" style="max-height: 300px;"></canvas>
        <?php
        $hourly7Data = [];
        while($row=$hourly7Days->fetch_assoc()) {
            $hourly7Data[$row['dt']][$row['hr']] = $row['uniques'];
        }
        $dates7 = array_keys($hourly7Data);
        $datasets7 = [];
        foreach($dates7 as $date) {
            $hoursData = array_fill(0,24,0);
            foreach($hourly7Data[$date] as $hr=>$val) $hoursData[$hr] = $val;
            $datasets7[] = ['label'=>date('d M', strtotime($date)), 'data'=>$hoursData, 'borderWidth'=>1];
        }
        ?>
        <script>
            const hourly7Ctx = document.getElementById('hourly7DaysChart').getContext('2d');
            new Chart(hourly7Ctx, {
                type: 'line',
                data: { labels: Array.from({length:24}, (_,i)=>i+':00'), datasets: <?=json_encode($datasets7)?> },
                options: { responsive: true, maintainAspectRatio: true }
            });
        </script>
    </div>

    <!-- ========== Existing charts continue below (fully unchanged) ========== -->
    <!-- ========== নতুন গ্রাফ ১: ২৪ ঘণ্টার বার চার্ট ========== -->
    <div class="card">
        <h3>⏱️ গত ২৪ ঘণ্টায় ভিজিটর (প্রতি ঘণ্টায়) - বারে ক্লিক করুন</h3>
        <canvas id="hourlyBarChart" style="max-height: 300px;"></canvas>
        <?php
        $hourlyOverall = $conn->query("SELECT HOUR(clicked_at) as hour, COUNT(DISTINCT visitor_fingerprint) as uniques FROM clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND is_bot=0 GROUP BY HOUR(clicked_at) ORDER BY hour ASC");
        $hoursData = array_fill(0, 24, 0);
        while ($h = $hourlyOverall->fetch_assoc()) {
            $hoursData[(int)$h['hour']] = $h['uniques'];
        }
        ?>
        <script>
            const hourLabels = Array.from({length: 24}, (_, i) => i + ':00');
            const hourData = <?= json_encode(array_values($hoursData)) ?>;
            new Chart(document.getElementById('hourlyBarChart'), {
                type: 'bar',
                data: { labels: hourLabels, datasets: [{ label: 'ইউনিক ভিজিটর', data: hourData, backgroundColor: '#3b82f6' }] },
                options: { responsive: true, onClick: (e, active) => { if(active.length) { let hour = active[0].label; showOverallHourDetails(hour); } } }
            });
        </script>
    </div>

    <!-- ========== নতুন গ্রাফ ২: দেশ অনুযায়ী ভিজিটর শতাংশ (পাই চার্ট + টেবিল) ========== -->
    <div class="card">
        <h3>🌍 দেশ অনুযায়ী ভিজিটর শতাংশ - দ্বিতীয় গ্রাফ</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <canvas id="countryPieChart" style="max-height: 350px; flex: 1;"></canvas>
            <div style="flex: 1; overflow-x: auto;">
                <table style="width:100%"><thead><tr><th>দেশ</th><th>ইউনিক ভিজিটর</th><th>শতাংশ</th></tr></thead>
                <tbody><?php foreach($pieLabels as $idx => $country): $percent = ($pieValues[$idx] / max($totalUniquesForPie,1)) * 100; ?>
                    <tr><td><?=htmlspecialchars($country)?></td><td><?=$pieValues[$idx]?></td><td class="percentage"><?=round($percent,1)?>%</td></tr>
                <?php endforeach; ?></tbody></table>
            </div>
        </div>
        <script> new Chart(document.getElementById('countryPieChart'), { type: 'pie', data: { labels: <?= json_encode($pieLabels) ?>, datasets: [{ data: <?= json_encode($pieValues) ?>, backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec489a','#14b8a6','#f97316','#06b6d4','#6366f1'] }] }, options: { responsive: true, plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} (${((ctx.raw/<?=$totalUniquesForPie?>)*100).toFixed(1)}%)` } } } } }); </script>
    </div>

    <!-- টপ কান্ট্রি, সিটি, ডিভাইস, ব্রাউজার, ওএস (শতাংশসহ - এখন সঠিক) -->
    <div class="flex-2col">
        <div class="card col"><h3>🌍 শীর্ষ দেশ</h3><table><?php while($c=$topCountries->fetch_assoc()):?><tr><td><?=$c['country']?></td><td><?=$c['cnt']?></td><td class="percentage"><?=round($c['percentage'],1)?>%</td></tr><?php endwhile;?></table></div>
        <div class="card col"><h3>🏙️ শীর্ষ শহর</h3><table><?php while($c=$topCities->fetch_assoc()):?><tr><td><?=$c['city']??'অজানা'?></td><td><?=$c['cnt']?></td><td class="percentage"><?=round($c['percentage'],1)?>%</td></tr><?php endwhile;?></table></div>
    </div>
    <div class="flex-2col">
        <div class="card col"><h3>📱 ডিভাইস (সঠিক শতাংশ)</h3><table><?php while($d=$topDevices->fetch_assoc()):?><tr><td><?=$d['device_type']?></td><td><?=$d['cnt']?></td><td class="percentage"><?=round($d['percentage'],1)?>%</td></tr><?php endwhile;?></table></div>
        <div class="card col"><h3>🌐 ব্রাউজার (সঠিক শতাংশ)</h3><table><?php while($b=$topBrowsers->fetch_assoc()):?><tr><td><?=$b['browser']?></td><td><?=$b['cnt']?></td><td class="percentage"><?=round($b['percentage'],1)?>%</td></tr><?php endwhile;?></table></div>
        <div class="card col"><h3>💻 ওএস (সঠিক শতাংশ)</h3><table><?php while($o=$topOS->fetch_assoc()):?><tr><td><?=$o['os']?></td><td><?=$o['cnt']?></td><td class="percentage"><?=round($o['percentage'],1)?>%</td></tr><?php endwhile;?></table></div>
    </div>

    <!-- গত ৩০ দিনের ক্লিক প্রবণতা (লাইন গ্রাফ + টেবিল) -->
    <div class="card">
        <h3>📈 গত ৩০ দিনে ক্লিক প্রবণতা (সব লিংক মিলিয়ে) - ক্লিক করুন</h3>
        <canvas id="overallTrend" style="max-height: 300px;"></canvas>
        <div style="margin-top:1rem; overflow-x:auto;"><table><tr><th>তারিখ</th><th>ইউনিক ভিজিটর</th><th>মোট ক্লিক</th></tr><?php $dailyTrend->data_seek(0); while($row=$dailyTrend->fetch_assoc()): ?><tr><td><?=date('d M Y', strtotime($row['click_date']))?></td><td><?=$row['uniques']?></td><td><?=$row['total_clicks']?></td></tr><?php endwhile; ?></table></div>
    </div>

    <!-- রেফারার সোর্স (বার চার্ট + টেবিল) -->
    <div class="card">
        <h3>🔗 উৎস বিশ্লেষণ (কোন সাইট থেকে আসছে?) - ক্লিক করুন</h3>
        <canvas id="overallReferrer" style="max-height: 250px;"></canvas>
        <div style="margin-top:1rem;"><table><tr><th>সোর্স</th><th>ইউনিক ভিজিটর</th><th>মোট ক্লিক</th></tr><?php $referrers->data_seek(0); while($ref=$referrers->fetch_assoc()): ?><tr><td><?=htmlspecialchars($ref['source'])?></td><td><?=$ref['uniques']?></td><td><?=$ref['total_clicks']?></td></tr><?php endwhile; ?></table></div>
    </div>

    <!-- টপ পারফর্মিং লিংক -->
    <div class="card">
        <h3>🔥 সবচেয়ে জনপ্রিয় লিংক (সর্বোচ্চ ক্লিক)</h3>
        <div style="overflow-x:auto;"><table><tr><th>শর্ট কোড</th><th>লম্বা URL</th><th>মোট ক্লিক</th><th>ইউনিক ভিজিটর</th><th>অ্যাকশন</th></tr><?php while($link=$topLinks->fetch_assoc()): ?><tr><td><code><?=htmlspecialchars($link['short_code'])?></code></td><td style="max-width:300px; overflow:hidden; text-overflow:ellipsis;"><?=htmlspecialchars($link['long_url'])?></td><td><?=$link['total_clicks']?></td><td><?=$link['uniques']?></td><td><button onclick="window.open('get_analytics.php?code=<?=$link['short_code']?>','_blank')" style="background:#0f766e; color:white; border:none; border-radius:20px; padding:4px 12px; cursor:pointer;">বিস্তারিত</button></td></tr><?php endwhile; ?></table></div>
    </div>

    <!-- টপ পারফর্মিং ডেট -->
    <div class="card">
        <h3>🏆 সবচেয়ে ব্যস্ত দিন (মোট ক্লিক অনুযায়ী)</h3>
        <table><tr><th>তারিখ</th><th>মোট ক্লিক</th></tr><?php while($d=$topDates->fetch_assoc()): ?><tr><td><?=date('d M Y', strtotime($d['click_date']))?></td><td><?=$d['total_clicks']?></td></tr><?php endwhile; ?></table>
    </div>

    <!-- অন্যান্য বিদ্যমান চার্ট (সপ্তাহ, ডোনাট, এরিয়া, হরাইজন্টাল বার) -->
    <div class="card">
        <h3>📆 সপ্তাহের দিন অনুযায়ী সার্বিক ক্লিক - বারে ক্লিক করুন</h3>
        <canvas id="weekdayOverallChart" style="max-height: 250px;"></canvas>
        <?php
        $weekOverall = $conn->query("SELECT DAYOFWEEK(clicked_at) as dow, COUNT(DISTINCT visitor_fingerprint) as uniques, COUNT(*) as total FROM clicks GROUP BY dow ORDER BY dow");
        $weekdays = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];
        $uniquesOverallWeek = array_fill(0, 7, 0);
        $totalOverallWeek = array_fill(0, 7, 0);
        while ($row = $weekOverall->fetch_assoc()) { $idx = (int)$row['dow'] - 1; $uniquesOverallWeek[$idx] = $row['uniques']; $totalOverallWeek[$idx] = $row['total']; }
        ?>
        <script> new Chart(document.getElementById('weekdayOverallChart'), { type: 'bar', data: { labels: <?= json_encode($weekdays) ?>, datasets: [{ label: 'ইউনিক ভিজিটর', data: <?= json_encode($uniquesOverallWeek) ?>, backgroundColor: '#f59e0b' }, { label: 'মোট ক্লিক', data: <?= json_encode($totalOverallWeek) ?>, backgroundColor: '#ef4444' }] } }); </script>
    </div>

    <div class="card">
        <h3>📱 সার্বিক ডিভাইস ডিস্ট্রিবিউশন - সেগমেন্টে ক্লিক করুন</h3>
        <canvas id="deviceDonutOverall" style="max-height: 250px;"></canvas>
        <?php $deviceOverall = $conn->query("SELECT device_type, COUNT(DISTINCT visitor_fingerprint) as cnt FROM clicks GROUP BY device_type"); $devLabelsO = []; $devCountsO = []; while ($d = $deviceOverall->fetch_assoc()) { $devLabelsO[] = $d['device_type'] ?: 'Unknown'; $devCountsO[] = $d['cnt']; } ?>
        <script> new Chart(document.getElementById('deviceDonutOverall'), { type: 'doughnut', data: { labels: <?= json_encode($devLabelsO) ?>, datasets: [{ data: <?= json_encode($devCountsO) ?>, backgroundColor: ['#0ea5e9', '#8b5cf6', '#ec489a', '#f97316'] }] } }); </script>
    </div>

    <div class="card">
        <h3>🤖 গত ৩০ দিনে রিয়েল ভিজিটর বনাম বট (সব লিংক)</h3>
        <canvas id="humanBotOverallArea" style="max-height: 280px;"></canvas>
        <?php $areaOverall = $conn->query("SELECT DATE(clicked_at) as dt, SUM(is_bot) as bots, COUNT(*) - SUM(is_bot) as humans FROM clicks WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(clicked_at) ORDER BY dt"); $datesO = []; $humansO = []; $botsO = []; while ($a = $areaOverall->fetch_assoc()) { $datesO[] = date('d M', strtotime($a['dt'])); $humansO[] = (int)$a['humans']; $botsO[] = (int)$a['bots']; } ?>
        <script> new Chart(document.getElementById('humanBotOverallArea'), { type: 'line', data: { labels: <?= json_encode($datesO) ?>, datasets: [{ label: 'রিয়েল ভিজিটর', data: <?= json_encode($humansO) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.3 }, { label: 'বট', data: <?= json_encode($botsO) ?>, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.1)', fill: true, tension: 0.3 }] } }); </script>
    </div>

    <div class="card">
        <h3>📊 শীর্ষ ১০ লিংক (মোট ক্লিক) – হরাইজন্টাল বার (ক্লিক করে বিস্তারিত দেখুন)</h3>
        <canvas id="topLinksHorizontal" style="max-height: 400px;"></canvas>
        <?php $topLinksChart = $conn->query("SELECT u.short_code, COUNT(c.id) as total_clicks FROM urls u LEFT JOIN clicks c ON u.short_code = c.short_code GROUP BY u.short_code ORDER BY total_clicks DESC LIMIT 10"); $codesHor = []; $clicksHor = []; while ($t = $topLinksChart->fetch_assoc()) { $codesHor[] = $t['short_code']; $clicksHor[] = (int)$t['total_clicks']; } ?>
        <script> new Chart(document.getElementById('topLinksHorizontal'), { type: 'bar', data: { labels: <?= json_encode($codesHor) ?>, datasets: [{ label: 'মোট ক্লিক', data: <?= json_encode($clicksHor) ?>, backgroundColor: '#3b82f6' }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: true } }); </script>
    </div>

    <!-- ওয়ার্ল্ড ম্যাপ ও সিটি ড্রিল-ডাউন -->
    <div class="card">
        <h3>🗺️ বিশ্ব মানচিত্র - দেশ অনুযায়ী দর্শক (ক্লিক করুন)</h3>
        <div id="world_map_div" style="height: 500px;"></div>
    </div>
    <div class="card" id="cityDrillCard" style="display:none;">
        <h3 id="cityDrillTitle">শহর বিশ্লেষণ</h3>
        <canvas id="cityBarChart" style="max-height: 300px;"></canvas>
        <div id="citySessionTable" style="margin-top:1rem; overflow-x:auto;"></div>
        <button onclick="closeCityDrill()" style="margin-top:10px; background:#ef4444; color:white; border:none; border-radius:20px; padding:6px 12px; cursor:pointer;">× বন্ধ করুন</button>
    </div>

    <!-- লাইফটাইম ইউনিক ইউজার টেবিল -->
    <div class="card">
        <h3>👥 প্রতিটি ইউনিক ইউজারের লাইফটাইম কার্যকলাপ</h3>
        <p><small>একটি ফিঙ্গারপ্রিন্ট = একটি নির্দিষ্ট ডিভাইস + ব্রাউজার (একই ওয়াইফাইয়ে ভিন্ন ডিভাইস আলাদা)। VPN বা IP পরিবর্তন করলেও একই ডিভাইস/ব্রাউজার হলে একই ইউজার হিসেবে গণ্য হবে।</small></p>
        <div style="overflow-x:auto;"><table style="width:100%; font-size:0.85rem;"><thead><tr style="background:#e2e8f0"><th>ফিঙ্গারপ্রিন্ট (শেষ ৮ অক্ষর)</th><th>মোট ক্লিক</th><th>ইউনিক লিংক</th><th>প্রথম ক্লিক</th><th>শেষ ক্লিক</th><th>ডিভাইস(সমূহ)</th><th>ব্রাউজার(সমূহ)</th><th>ওএস(সমূহ)</th><th>দেশ(সমূহ)</th><th>আইপি(সমূহ)</th></tr></thead><tbody>
        <?php $lifetimeUsers = $conn->query("SELECT visitor_fingerprint, COUNT(*) as total_clicks, COUNT(DISTINCT short_code) as unique_links, MIN(clicked_at) as first_click, MAX(clicked_at) as last_click, GROUP_CONCAT(DISTINCT device_type) as devices, GROUP_CONCAT(DISTINCT browser) as browsers, GROUP_CONCAT(DISTINCT os) as oss, GROUP_CONCAT(DISTINCT country) as countries, GROUP_CONCAT(DISTINCT ip_address) as ips FROM clicks WHERE visitor_fingerprint IS NOT NULL AND visitor_fingerprint != '' AND is_bot = 0 GROUP BY visitor_fingerprint ORDER BY total_clicks DESC LIMIT 100");
        while($u = $lifetimeUsers->fetch_assoc()): ?>
            <tr><td><code><?=substr(htmlspecialchars($u['visitor_fingerprint']), 0, 8)?>...</code></td><td><?=$u['total_clicks']?></td><td><?=$u['unique_links']?></td><td><?=date('d M Y H:i', strtotime($u['first_click']))?></td><td><?=date('d M Y H:i', strtotime($u['last_click']))?></td><td><?=htmlspecialchars($u['devices'])?></td><td><?=htmlspecialchars($u['browsers'])?></td><td><?=htmlspecialchars($u['oss'])?></td><td><?=htmlspecialchars($u['countries'])?></td><td><small><?=htmlspecialchars($u['ips'])?></small></td></tr>
        <?php endwhile; ?>
        </tbody></table></div>
    </div>
</div>

<div id="detailModal" class="modal"><div class="modal-content"><span class="close">&times;</span><div id="modalContent">লোড হচ্ছে...</div></div></div>

<script>
// Existing chart data for daily trend
const dailyLabels = []; const dailyUniques = [];
<?php $dailyTrend->data_seek(0); while($row = $dailyTrend->fetch_assoc()): echo "dailyLabels.push(" . json_encode(date('d M', strtotime($row['click_date']))) . ");\n"; echo "dailyUniques.push(" . intval($row['uniques']) . ");\n"; endwhile; ?>
let overallTrendChart = null;
if(dailyLabels.length > 0) { overallTrendChart = new Chart(document.getElementById('overallTrend'), { type: 'line', data: { labels: dailyLabels, datasets: [{ label: 'ইউনিক ভিজিটর', data: dailyUniques, borderColor: '#3b82f6', fill: false }] }, options: { responsive: true, onClick: (e, active) => { if(active.length) showOverallDayDetails(dailyLabels[active[0].index]); } } }); } else { document.getElementById('overallTrend').style.display = 'none'; document.getElementById('overallTrend').insertAdjacentHTML('afterend', '<div>📭 গত ৩০ দিনে কোনো ডাটা নেই</div>'); }

const refLabels = []; const refUniques = [];
<?php $referrers->data_seek(0); while($ref = $referrers->fetch_assoc()): echo "refLabels.push(" . json_encode($ref['source']) . ");\n"; echo "refUniques.push(" . intval($ref['uniques']) . ");\n"; endwhile; ?>
if(refLabels.length > 0) { new Chart(document.getElementById('overallReferrer'), { type: 'bar', data: { labels: refLabels, datasets: [{ label: 'ইউনিক ভিজিটর', data: refUniques, backgroundColor: '#10b981' }] }, options: { responsive: true, onClick: (e, active) => { if(active.length) showOverallSourceDetails(refLabels[active[0].index]); } } }); } else { document.getElementById('overallReferrer').style.display = 'none'; document.getElementById('overallReferrer').insertAdjacentHTML('afterend', '<div>📭 কোনো রেফারার তথ্য নেই</div>'); }

const weekdayCanvas = document.getElementById('weekdayOverallChart');
if(weekdayCanvas) { weekdayCanvas.onclick = (e) => { const active = Chart.getChart(weekdayCanvas).getElementsAtEvent(e); if(active.length) showOverallWeekdayDetails(active[0].label); }; }
const deviceDonutCanvas = document.getElementById('deviceDonutOverall');
if(deviceDonutCanvas) { deviceDonutCanvas.onclick = (e) => { const active = Chart.getChart(deviceDonutCanvas).getElementsAtEvent(e); if(active.length) showOverallDeviceDetails(active[0].label); }; }
const topLinksHorCanvas = document.getElementById('topLinksHorizontal');
if(topLinksHorCanvas) { topLinksHorCanvas.onclick = (e) => { const active = Chart.getChart(topLinksHorCanvas).getElementsAtEvent(e); if(active.length) { let code = <?= json_encode($codesHor) ?>[active[0].index]; window.open('get_analytics.php?code='+code, '_blank'); } }; }

const modal = document.getElementById('detailModal'); const span = document.getElementsByClassName('close')[0];
span.onclick = () => modal.style.display = "none"; window.onclick = (event) => { if(event.target == modal) modal.style.display = "none"; };

function showOverallDayDetails(dateLabel) { modal.style.display = "block"; fetch(`ajax_analytics.php?action=overall_day_details&date=${encodeURIComponent(dateLabel)}`).then(res => res.json()).then(data => { let html = `<h3>📅 ${dateLabel} - সার্বিক বিস্তারিত</h3><p>মোট ক্লিক: ${data.total_clicks} | ইউনিক: ${data.uniques}</p><h4>শীর্ষ লিংক</h4><table border=1 cellpadding=5><tr><th>শর্ট কোড</th><th>ক্লিক</th></tr>`; for(let l of data.top_links) html += `<tr><td>${l.code}</td><td>${l.clicks}</td></tr>`; html += `</table><h4>শীর্ষ দেশ</h4><table><tr><th>দেশ</th><th>ইউনিক</th></tr>`; for(let c of data.top_countries) html += `<tr><td>${c.country}</td><td>${c.cnt}</td></tr>`; html += `</table>`; document.getElementById('modalContent').innerHTML = html; }); }
function showOverallSourceDetails(source) { modal.style.display = "block"; fetch(`ajax_analytics.php?action=overall_source_details&source=${encodeURIComponent(source)}`).then(res => res.json()).then(data => { let html = `<h3>🔗 ${source}</h3><p>মোট ক্লিক: ${data.total_clicks} | ইউনিক: ${data.uniques}</p><h4>সাম্প্রতিক ভিজিট</h4><table><tr><th>আইপি</th><th>লিংক</th><th>তারিখ</th></tr>`; for(let v of data.recent) html += `<tr><td>${v.ip}</td><td>${v.code}</td><td>${v.date}</td></tr>`; html += `</table>`; document.getElementById('modalContent').innerHTML = html; }); }
function showOverallHourDetails(hourLabel) { let hour = parseInt(hourLabel); modal.style.display = "block"; fetch(`ajax_analytics.php?action=overall_hour_details&hour=${hour}`).then(res => res.json()).then(data => { let html = `<h3>⏱️ ঘণ্টা ${hour}:00-${hour+1}:00</h3><p>মোট ক্লিক: ${data.total_clicks} | ইউনিক: ${data.uniques}</p><h4>ভিজিটর তালিকা</h4><table><tr><th>আইপি</th><th>লিংক</th><th>দেশ</th></tr>`; for(let v of data.visitors) html += `<tr><td>${v.ip}</td><td>${v.code}</td><td>${v.country}</td></tr>`; html += `</table>`; document.getElementById('modalContent').innerHTML = html; }); }
function showOverallWeekdayDetails(day) { modal.style.display = "block"; fetch(`ajax_analytics.php?action=overall_weekday_details&weekday=${encodeURIComponent(day)}`).then(res => res.json()).then(data => { let html = `<h3>📆 ${day}বার</h3><p>মোট ক্লিক: ${data.total_clicks}</p><h4>শীর্ষ লিংক</h4><table><tr><th>লিংক</th><th>ক্লিক</th></tr>`; for(let l of data.top_links) html += `<tr><td>${l.code}</td><td>${l.clicks}</td></tr>`; html += `</table>`; document.getElementById('modalContent').innerHTML = html; }); }
function showOverallDeviceDetails(device) { modal.style.display = "block"; fetch(`ajax_analytics.php?action=overall_device_details&device=${encodeURIComponent(device)}`).then(res => res.json()).then(data => { let html = `<h3>📱 ${device}</h3><p>মোট ইউনিক: ${data.uniques}</p><h4>সাম্প্রতিক সেশন</h4><table><tr><th>আইপি</th><th>ব্রাউজার</th><th>তারিখ</th></tr>`; for(let v of data.recent) html += `<tr><td>${v.ip}</td><td>${v.browser}</td><td>${v.date}</td></tr>`; html += `</table>`; document.getElementById('modalContent').innerHTML = html; }); }

google.charts.load('current', { packages: ['geochart'] }); google.charts.setOnLoadCallback(drawWorldMapOverall);
function drawWorldMapOverall() { let dataArray = [['Country', 'Visitors']]; <?php foreach($countryData as $country => $visitors): ?>dataArray.push(['<?=addslashes($country)?>', <?=$visitors?>]); <?php endforeach; ?> let data = google.visualization.arrayToDataTable(dataArray); let chart = new google.visualization.GeoChart(document.getElementById('world_map_div')); google.visualization.events.addListener(chart, 'select', () => { let selection = chart.getSelection(); if(selection.length) { let country = data.getValue(selection[0].row, 0); showOverallCityBreakdown(country); } }); chart.draw(data, { colorAxis: { colors: ['#e0f2fe', '#3b82f6'] }, backgroundColor: '#f8fafc', datalessRegionColor: '#f1f5f9' }); }

let cityChartOverall = null;
function showOverallCityBreakdown(country) { document.getElementById('cityDrillCard').style.display = 'block'; document.getElementById('cityDrillTitle').innerHTML = `🏙️ ${country} - শহরভিত্তিক দর্শক`; fetch(`ajax_analytics.php?action=overall_country_cities&country=${encodeURIComponent(country)}`).then(res => res.json()).then(data => { if(cityChartOverall) cityChartOverall.destroy(); let ctx = document.getElementById('cityBarChart').getContext('2d'); cityChartOverall = new Chart(ctx, { type: 'bar', data: { labels: data.cities.map(c=>c.city), datasets: [{ label: 'ইউনিক ভিজিটর', data: data.cities.map(c=>c.uniques), backgroundColor: '#f97316' }] }, options: { onClick: (e, active) => { if(active.length) { let city = data.cities[active[0].index].city; showOverallCitySessions(country, city); } } } }); let tableHtml = `<h4>সব শহরের তালিকা</h4><table><tr><th>শহর</th><th>ইউনিক</th><th>মোট ক্লিক</th></tr>`; for(let c of data.cities) tableHtml += `<tr><td>${c.city}</td><td>${c.uniques}</td><td>${c.total_clicks}</td></tr>`; tableHtml += `</table>`; document.getElementById('citySessionTable').innerHTML = tableHtml; }); }
function showOverallCitySessions(country, city) { modal.style.display = "block"; fetch(`ajax_analytics.php?action=overall_city_sessions&country=${encodeURIComponent(country)}&city=${encodeURIComponent(city)}`).then(res => res.json()).then(data => { let html = `<h3>📍 ${city}, ${country}</h3><p>মোট ক্লিক: ${data.total_clicks} | ইউনিক: ${data.uniques}</p><h4>সাম্প্রতিক ভিজিট</h4><table><tr><th>আইপি</th><th>লিংক</th><th>তারিখ</th></tr>`; for(let v of data.recent) html += `<tr><td>${v.ip}</td><td>${v.code}</td><td>${v.date}</td></tr>`; html += `</table>`; document.getElementById('modalContent').innerHTML = html; }); }
function closeCityDrill() { document.getElementById('cityDrillCard').style.display = 'none'; }

function fetchRealtimeOnline() { fetch('ajax_realtime.php').then(res => res.json()).then(data => { document.getElementById('realtimeOnline').innerHTML = data.online + ' জন অনলাইন'; }).catch(err => console.error); }
fetchRealtimeOnline(); setInterval(fetchRealtimeOnline, 30000);
</script>
</body>
</html>