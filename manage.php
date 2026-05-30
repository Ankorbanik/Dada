<?php
session_start();
require_once 'db.php';
$access_password = 'hifi';

if (isset($_POST['password'])) {
    if ($_POST['password'] === $access_password) $_SESSION['manage_access'] = true;
    else $login_error = "পাসওয়ার্ড ভুল!";
}
if (empty($_SESSION['manage_access'])) {
    ?>
    <!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><title>অ্যাক্সেস</title>
    <style>body{background:#0f172a;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;}.box{background:#1e293b;padding:2rem;border-radius:1rem;color:#f1f5f9;box-shadow:0 20px 40px rgba(0,0,0,0.4);}input{width:100%;padding:0.8rem;margin:1rem 0;border-radius:2rem;border:1px solid #334155;background:#0f172a;color:white;}button{width:100%;padding:0.8rem;background:#3b82f6;border:none;border-radius:2rem;color:white;font-weight:bold;cursor:pointer;}.error{color:#f87171;}</style></head><body>
    <div class="box"><h2>🔒 লিংক ম্যানেজার</h2>
    <?php if(isset($login_error)) echo "<p class='error'>$login_error</p>"; ?>
    <form method="post"><input type="password" name="password" placeholder="পাসওয়ার্ড"><button type="submit">প্রবেশ</button></form>
    </div></body></html>
    <?php exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/";

if (isset($_GET['delete'])) {
    $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['delete']);
    $conn->query("DELETE FROM clicks WHERE short_code = '$code'");
    $stmt = $conn->prepare("DELETE FROM urls WHERE short_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute(); $stmt->close();
    header("Location: manage.php?deleted=1"); exit;
}

$edit_error = '';
if (isset($_POST['edit_code'])) {
    $old = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['edit_code']);
    $new_short = trim($_POST['new_short']);
    $new_long = trim($_POST['new_long']);
    
    if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $new_short)) $edit_error = "কোড ফরম্যাট ভুল";
    elseif ($new_short !== $old) {
        $chk = $conn->prepare("SELECT id FROM urls WHERE short_code = ?");
        $chk->bind_param("s", $new_short);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) $edit_error = "কোড ব্যবহৃত"; 
        $chk->close();
    }
    if (!isset($edit_error)) {
        if (!preg_match('/^https?:\/\//i', $new_long)) $new_long = 'http://'.$new_long;
        if (!filter_var($new_long, FILTER_VALIDATE_URL)) $edit_error = "URL সঠিক নয়";
        else {
            $upd = $conn->prepare("UPDATE urls SET short_code=?, long_url=? WHERE short_code=?");
            $upd->bind_param("sss", $new_short, $new_long, $old);
            $upd->execute(); $upd->close();
            header("Location: manage.php?updated=1"); exit;
        }
    }
}

$totalLinks = $conn->query("SELECT COUNT(*) FROM urls")->fetch_row()[0];
$totalClicks = $conn->query("SELECT COUNT(*) FROM clicks")->fetch_row()[0];
$uniqueVisitors = $conn->query("SELECT COUNT(DISTINCT visitor_fingerprint) FROM clicks WHERE is_bot = 0")->fetch_row()[0];
$botClicks = $conn->query("SELECT COUNT(*) FROM clicks WHERE is_bot = 1")->fetch_row()[0];

$result = $conn->query("SELECT u.short_code, u.long_url, u.created_at,
    (SELECT COUNT(*) FROM clicks c WHERE c.short_code = u.short_code) AS clicks,
    (SELECT COUNT(DISTINCT visitor_fingerprint) FROM clicks c WHERE c.short_code = u.short_code AND is_bot = 0) AS uniques
    FROM urls u ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8"><title>অ্যানালিটিক্স ড্যাশবোর্ড</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f4f7f9;font-family:'Segoe UI',sans-serif;padding:2rem}
        .container{max-width:1200px;margin:0 auto}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}
        .logout-btn{background:#dc2626;color:white;padding:0.5rem 1.5rem;border-radius:2rem;text-decoration:none}
        .summary{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem}
        .card{background:white;border-radius:1rem;padding:1.2rem;flex:1 1 140px;text-align:center}
        .card .val{font-size:2rem;font-weight:700;color:#1d4ed8}
        .card .lbl{color:#64748b;font-size:0.8rem}
        .link-item{background:white;border-radius:1rem;padding:1.2rem;display:flex;align-items:center;border:1px solid #e2e8f0;margin-bottom:0.8rem;flex-wrap:wrap}
        .info-col{flex:3}
        .short-link{font-weight:600;color:#1d4ed8;text-decoration:none}
        .copy-btn{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:0.2rem 0.8rem;border-radius:2rem;cursor:pointer;margin-left:0.5rem}
        .actions-col{display:flex;gap:0.4rem;flex-wrap:wrap}
        .btn{border:1px solid #e2e8f0;padding:0.4rem 0.8rem;border-radius:2rem;cursor:pointer;background:white;font-size:0.8rem}
        .btn-details{color:#0f766e;border-color:#d1fae5}
        .btn-edit{color:#1d4ed8;border-color:#dbeafe}
        .btn-delete{color:#b91c1c;border-color:#fee2e2}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:1000;justify-content:center;align-items:center}
        .modal.active{display:flex}
        .modal-content{background:white;border-radius:1rem;padding:1.5rem;max-width:800px;width:90%;max-height:80vh;overflow-y:auto}
        .edit-form input, .edit-form textarea{width:100%;padding:0.6rem;margin:0.5rem 0;border-radius:0.5rem;border:1px solid #ccc}
        .edit-form button{padding:0.5rem 1rem;margin-right:0.5rem}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 অ্যানালিটিক্স ড্যাশবোর্ড</h1>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> লগআউট</a>
        <a href="overview_analytics.php" class="logout-btn" style="background:#0f766e; margin-right:1rem;"><i class="fas fa-chart-pie"></i> সম্মিলিত অ্যানালিটিক্স</a>
    </div>

    <div class="summary">
        <div class="card"><div class="val"><?=$totalLinks?></div><div class="lbl">মোট লিংক</div></div>
        <div class="card"><div class="val"><?=$totalClicks?></div><div class="lbl">মোট ক্লিক</div></div>
        <div class="card"><div class="val"><?=$uniqueVisitors?></div><div class="lbl">ইউনিক ভিজিটর</div></div>
        <div class="card"><div class="val"><?=$botClicks?></div><div class="lbl">বট ট্রাফিক</div></div>
    </div>

    <?php while($row = $result->fetch_assoc()):
        $short_url = $base_url . $row['short_code']; ?>
    <div class="link-item">
        <div class="info-col">
            <a href="<?=htmlspecialchars($short_url)?>" target="_blank" class="short-link"><?=htmlspecialchars($short_url)?></a>
            <button class="copy-btn" onclick="copyText('<?=htmlspecialchars($short_url)?>',this)"><i class="fas fa-copy"></i> কপি</button>
            <div style="font-size:0.8rem;color:#64748b;"><?=htmlspecialchars($row['long_url'])?></div>
            <div style="font-size:0.75rem;color:#94a3b8;">📅 <?=date('d M Y',strtotime($row['created_at']))?> | ক্লিক: <?=$row['clicks']?> | ইউনিক: <?=$row['uniques']?></div>
        </div>
        <div class="actions-col">
            <button class="btn btn-details" onclick="loadAnalytics('<?=$row['short_code']?>')"><i class="fas fa-chart-pie"></i> অ্যানালিটিক্স</button>
            <button class="btn btn-edit" onclick="openEditModal('<?=$row['short_code']?>', '<?=htmlspecialchars($row['long_url'])?>')"><i class="fas fa-pen"></i> এডিট</button>
            <a href="manage.php?delete=<?=$row['short_code']?>" class="btn btn-delete" onclick="return confirm('মুছতে চান? ক্লিক ডাটাও মুছে যাবে')"><i class="fas fa-trash"></i> ডিলিট</a>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<div class="modal" id="analyticsModal">
    <div class="modal-content">
        <button style="float:right;border:none;font-size:1.5rem;cursor:pointer" onclick="closeModal('analyticsModal')">&times;</button>
        <div id="analyticsContent">লোড হচ্ছে...</div>
    </div>
</div>

<div class="modal" id="editModal">
    <div class="modal-content">
        <button style="float:right;border:none;font-size:1.5rem;cursor:pointer" onclick="closeModal('editModal')">&times;</button>
        <h3>লিংক এডিট করুন</h3>
        <form method="post" class="edit-form">
            <input type="hidden" name="edit_code" id="edit_code">
            <label>নতুন শর্ট কোড</label>
            <input type="text" name="new_short" id="new_short" required pattern="[a-zA-Z0-9_-]{1,50}">
            <label>লম্বা URL</label>
            <textarea name="new_long" id="new_long" rows="3" required></textarea>
            <div style="margin-top:1rem">
                <button type="submit" class="btn btn-details">সংরক্ষণ</button>
                <button type="button" class="btn" onclick="closeModal('editModal')">বাতিল</button>
            </div>
            <?php if($edit_error):?><p style="color:red"><?=$edit_error?></p><?php endif;?>
        </form>
    </div>
</div>

<script>
function copyText(text,btn){
    navigator.clipboard.writeText(text).then(()=>{
        btn.innerHTML='<i class="fas fa-check"></i> কপি হয়েছে';
        setTimeout(()=>{ btn.innerHTML='<i class="fas fa-copy"></i> কপি'; },2000);
    });
}
function loadAnalytics(code){
    document.getElementById('analyticsModal').classList.add('active');
    document.getElementById('analyticsContent').innerHTML='লোড হচ্ছে...';
    fetch('get_analytics.php?code='+encodeURIComponent(code))
        .then(r=>r.text())
        .then(h=>{document.getElementById('analyticsContent').innerHTML=h;})
        .catch(()=>{document.getElementById('analyticsContent').innerHTML='ডাটা আনতে ব্যর্থ';});
}
function openEditModal(code, longUrl){
    document.getElementById('edit_code').value = code;
    document.getElementById('new_short').value = code;
    document.getElementById('new_long').value = longUrl;
    document.getElementById('editModal').classList.add('active');
}
function closeModal(modalId){
    document.getElementById(modalId).classList.remove('active');
}
</script>
</body>
</html>