<?php
// farmer/dashboard.php
// Single-file Farmer Dashboard (Modern Agriculture Theme - Option B)
// - Uses PDO
// - Shows: My Farms, My Crops, My Advisories, My Batches, Weather
// - Save as farmer/dashboard.php and open after farmer login
session_start();

// ---------------- CONFIG ----------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'agriculture'; // change to your DB
$DB_USER = 'root';
$DB_PASS = '';

// Optional: OpenWeather API key (put your key here to enable weather)
define('OPENWEATHER_API_KEY', ''); // <-- put your OpenWeather API key here

// require farmer logged in
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../index.php"); // adjust path to your login
    exit;
}
$farmer_id = (int)$_SESSION['farmer_id'];

// ---------------- PDO ----------------
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    echo "Database connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}

// ---------------- Fetch Data ----------------
// 1. Farmer info
$stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, phone, location FROM farmers WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2. My farms (if you keep farms table)
$farms = [];
if ($pdo->query("SHOW TABLES LIKE 'farms'")->fetchColumn()) {
    $stm = $pdo->prepare("SELECT * FROM farms WHERE farmer_id = :fid ORDER BY id DESC");
    $stm->execute([':fid'=>$farmer_id]);
    $farms = $stm->fetchAll(PDO::FETCH_ASSOC);
}

// 3. My crops
$stm = $pdo->prepare("SELECT * FROM crops WHERE farmer_id = :fid ORDER BY created_at DESC");
$stm->execute([':fid'=>$farmer_id]);
$crops = $stm->fetchAll(PDO::FETCH_ASSOC);

// 4. My batches (via crops -> crop_batches)
$batchList = [];
if ($pdo->query("SHOW TABLES LIKE 'crop_batches'")->fetchColumn()) {
    $stm = $pdo->prepare("SELECT b.*, c.crop_type, c.variety FROM crop_batches b
                         LEFT JOIN crops c ON c.id = b.crop_id
                         WHERE c.farmer_id = :fid
                         ORDER BY b.created_at DESC");
    $stm->execute([':fid'=>$farmer_id]);
    $batchList = $stm->fetchAll(PDO::FETCH_ASSOC);
}

// 5. My advisories/notifications (if notifications table exists)
$notifications = [];
if ($pdo->query("SHOW TABLES LIKE 'notifications'")->fetchColumn()) {
    $stm = $pdo->prepare("SELECT n.*, a.title, a.message, a.created_at AS advisory_created_at
                         FROM notifications n
                         LEFT JOIN advisory_messages a ON a.id = n.advisory_id
                         WHERE n.farmer_id = :fid
                         ORDER BY n.created_at DESC LIMIT 50");
    $stm->execute([':fid'=>$farmer_id]);
    $notifications = $stm->fetchAll(PDO::FETCH_ASSOC);
} else {
    // fallback: advisories targeted via advisory_messages with target_type = 'all' or region matching
    $stm = $pdo->prepare("SELECT * FROM advisory_messages WHERE target_type = 'all' OR (target_type = 'region' AND :loc LIKE CONCAT('%', target_value, '%')) ORDER BY created_at DESC LIMIT 30");
    $stm->execute([':loc'=> $farmer['location'] ?? '']);
    $notifications = $stm->fetchAll(PDO::FETCH_ASSOC);
}

// 6. Weather: if API key set, try to fetch for farmer location (simple)
$weather = null;
if (defined('OPENWEATHER_API_KEY') && OPENWEATHER_API_KEY !== '' && !empty($farmer['location'])) {
    // Note: this is a simple call using the location string as "q". For production you should map to lat/lon
    //$q = urlencode($farmer['location']);
   // $api = "https://api.openweathermap.org/data/2.5/weather?q={$q}&units=metric&appid=" . OPENWEATHER_API_KEY;
    // Use @file_get_contents or curl; prefer curl if allow_url_fopen disabled
   // $ch = curl_init($api);
   // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    //$json = curl_exec($ch);
   // $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //curl_close($ch);
    if ($http === 200 && $json) {
        $w = json_decode($json, true);
        if (isset($w['main'])) {
            $weather = [
                'temp' => $w['main']['temp'],
                'feels_like' => $w['main']['feels_like'],
                'humidity' => $w['main']['humidity'],
                'desc' => $w['weather'][0]['description'] ?? '',
                'wind' => $w['wind']['speed'] ?? 0,
            ];
        }
    }
}

// small helper
function fullname($r){ return trim(($r['first_name']??''). ' ' . ($r['middle_name']??'') . ' ' . ($r['last_name']??'')); }

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Farmer Dashboard — <?=$farmer? htmlspecialchars(fullname($farmer)) : 'Farmer' ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --bg:#f3fbf6; --card:#ffffff; --primary:#0b9348; --muted:#6b7a86;
  --shadow: 0 12px 30px rgba(12,35,25,0.06);
}
*{box-sizing:border-box}
body{margin:0;font-family:'Poppins',sans-serif;background:linear-gradient(180deg,#eafaf0 0%, #f3fbf6 60%);color:#0b1a12}
.header{display:flex;justify-content:space-between;align-items:center;padding:18px 24px}
.profile{display:flex;gap:12px;align-items:center}
.avatar{width:64px;height:64px;border-radius:999px;background:linear-gradient(135deg,#dff6e8,#c2f0d6);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;color:var(--primary);box-shadow:var(--shadow)}
.hi{font-size:18px;font-weight:600}
.sub{color:var(--muted);font-size:13px}
.container{max-width:1100px;margin:18px auto;padding:0 16px}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:12px}
.card{background:var(--card);padding:14px;border-radius:12px;box-shadow:var(--shadow);display:flex;flex-direction:column;gap:8px}
.card .num{font-size:22px;font-weight:700}
.actions{display:flex;gap:8px;align-items:center}
.btn{padding:8px 10px;border-radius:8px;border:none;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.grid-2{display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-top:14px}
.panel{background:var(--card);padding:12px;border-radius:12px;box-shadow:var(--shadow)}
.list{display:flex;flex-direction:column;gap:10px;margin-top:8px}
.item{display:flex;gap:12px;align-items:center;padding:10px;border-radius:10px;border:1px solid #f0f6f1}
.item .meta{flex:1}
.small-muted{color:var(--muted);font-size:13px}
.qr{width:72px;height:72px;border-radius:8px;background:#fafafa;border:1px dashed #e6f2ea;display:flex;align-items:center;justify-content:center}
@media (max-width:900px){
  .cards{grid-template-columns:repeat(1,1fr)}
  .grid-2{grid-template-columns:1fr}
  .container{padding:0 12px}
}
</style>
</head>
<body>

<header class="header">
  <div class="profile">
    <div class="avatar"><?= strtoupper(substr($farmer['first_name'] ?? 'F',0,1)) ?></div>
    <div>
      <div class="hi">Hello, <?= htmlspecialchars(fullname($farmer) ?: 'Farmer') ?></div>
      <div class="sub"><?= htmlspecialchars($farmer['location'] ?? 'Location not set') ?></div>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:12px">
    <div style="text-align:right">
      <div class="small-muted">Notifications</div>
      <div style="font-weight:700"><?= count($notifications) ?></div>
    </div>
    <div style="text-align:right">
      <div class="small-muted">Weather</div>
      <?php if ($weather): ?>
        <div style="font-weight:700"><?= htmlspecialchars($weather['temp']) ?>°C</div>
      <?php else: ?>
        <div style="font-weight:700;color:var(--muted)">No data</div>
      <?php endif; ?>
    </div>
    <div style="width:46px;height:46px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow)">
      <a href="../logout.php" title="Logout"><i class="fa fa-sign-out-alt" style="color:var(--primary)"></i></a>
    </div>
  </div>
</header>

<div class="container">
  <!-- Top cards -->
  <div class="cards">
    <div class="card">
      <div class="small-muted">My Farms</div>
      <div class="num"><?= count($farms) ?></div>
      <div class="small-muted">Manage your farms, edit locations & soil info</div>
      <div class="actions">
        <button class="btn btn-primary" onclick="location.href='my_farms.php'"><i class="fa fa-tractor"></i>&nbsp; Manage</button>
      </div>
    </div>

    <div class="card">
      <div class="small-muted">My Crops</div>
      <div class="num"><?= count($crops) ?></div>
      <div class="small-muted">Track planted crops & expected harvest</div>
      <div class="actions">
        <button class="btn btn-primary" onclick="location.href='my_crops.php'"><i class="fa fa-seedling"></i>&nbsp; View</button>
      </div>
    </div>

    <div class="card">
      <div class="small-muted">My Batches</div>
      <div class="num"><?= count($batchList) ?></div>
      <div class="small-muted">QR codes & traceability for your batches</div>
      <div class="actions">
        <button class="btn btn-primary" onclick="location.href='my_batches.php'"><i class="fa fa-qrcode"></i>&nbsp; Open</button>
      </div>
    </div>
  </div>

  <!-- Two-column area -->
  <div class="grid-2">
    <!-- Left: advisories + recent crops -->
    <div>
      <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h3 style="margin:0">My Advisories</h3>
          <a href="my_advisories.php" class="small-muted">See all</a>
        </div>
        <div class="list">
          <?php if (empty($notifications)): ?>
            <div class="small-muted" style="padding:12px">No advisories yet.</div>
          <?php else: foreach ($notifications as $n): ?>
            <div class="item">
              <div class="meta">
                <div style="font-weight:600"><?= htmlspecialchars($n['title'] ?? ($n['message'] ? substr($n['message'],0,50).'...' : 'Advisory')) ?></div>
                <div class="small-muted"><?= htmlspecialchars(($n['advisory_created_at'] ?? $n['created_at'] ?? $n['created_at']) ) ?></div>
              </div>
              <div style="text-align:right">
                <button class="btn" onclick="openAdvisory(<?= (int)($n['advisory_id'] ?? $n['id'] ?? 0) ?>)">Read</button>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="panel" style="margin-top:12px">
        <h3 style="margin:0">My Crops (recent)</h3>
        <div class="list">
          <?php if (empty($crops)): ?>
            <div class="small-muted" style="padding:12px">No crops registered.</div>
          <?php else: foreach (array_slice($crops,0,6) as $c): ?>
            <div class="item">
              <div class="meta">
                <div style="font-weight:600"><?= htmlspecialchars($c['crop_type']) ?> <?= $c['variety'] ? ' — '.htmlspecialchars($c['variety']) : '' ?></div>
                <div class="small-muted">Planted: <?= htmlspecialchars($c['planted_date'] ?? '—') ?> • Harvest: <?= htmlspecialchars($c['expected_harvest'] ?? '—') ?></div>
              </div>
              <div style="text-align:right">
                <a href="my_crops.php" class="btn">Manage</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Right: weather + batches -->
    <div>
      <div class="panel">
        <h3 style="margin:0">Weather Update</h3>
        <?php if ($weather): ?>
          <div style="margin-top:10px">
            <div style="font-weight:700;font-size:20px"><?= htmlspecialchars($weather['temp']) ?>°C — <?= htmlspecialchars(ucfirst($weather['desc'])) ?></div>
            <div class="small-muted">Feels like <?= htmlspecialchars($weather['feels_like']) ?>°C • Humidity <?= htmlspecialchars($weather['humidity']) ?>%</div>
            <div style="margin-top:8px" class="small-muted">Wind <?= htmlspecialchars($weather['wind']) ?> m/s</div>
          </div>
        <?php else: ?>
          <div style="padding:12px;color:var(--muted)">Weather not available. To enable live weather add your OpenWeather API key in the dashboard file.</div>
        <?php endif; ?>
      </div>

      <div class="panel" style="margin-top:12px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h3 style="margin:0">My Batches (recent)</h3>
          <a href="my_batches.php" class="small-muted">See all</a>
        </div>
        <div class="list" style="margin-top:8px">
          <?php if (empty($batchList)): ?>
            <div class="small-muted" style="padding:12px">No batches recorded yet.</div>
          <?php else: foreach (array_slice($batchList,0,6) as $b): ?>
            <div class="item">
              <div class="qr"><?= $b['qr_code_path'] ? '<img src="'.htmlspecialchars($b['qr_code_path']).'" style="max-width:100%;border-radius:6px">' : '<i class="fa fa-qrcode" style="font-size:20px;color:var(--muted)"></i>' ?></div>
              <div class="meta">
                <div style="font-weight:600"><?= htmlspecialchars($b['batch_code']) ?></div>
                <div class="small-muted"><?= htmlspecialchars($b['crop_type'] ?? '—') ?> • <?= htmlspecialchars($b['quantity'] ?? '—') ?></div>
                <div class="small-muted">Harvest: <?= htmlspecialchars($b['harvest_date'] ?? '—') ?></div>
              </div>
              <div style="text-align:right">
                <button class="btn" onclick="location.href='view_timeline.php?batch=<?=$b['id']?>'"><i class="fa fa-clock-rotate-left"></i></button>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Advisory modal (simple) -->
<div id="advModal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;z-index:999">
  <div style="width:680px;max-width:94%;background:#fff;padding:16px;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,0.2)">
    <h3 id="advTitle">Advisory</h3>
    <div id="advBody" style="margin-top:8px"></div>
    <div style="text-align:right;margin-top:12px"><button onclick="closeAdv()" class="btn">Close</button></div>
  </div>
</div>

<script>
function openAdvisory(id){
  if(!id){ alert('No advisory id'); return; }
  // Simple client-side show using existing page data: ideally implement endpoint to fetch full advisory by id
  // We'll try to open a new page if exists
  window.location.href = 'my_advisories.php?advisory=' + encodeURIComponent(id);
}
function closeAdv(){ document.getElementById('advModal').style.display = 'none'; }

// small UX: show placeholder if user clicks Manage buttons without pages implemented
document.querySelectorAll('a[href^="my_"]').forEach(a=>{
  a.addEventListener('click', (e)=>{
    const href = a.getAttribute('href');
    // if the linked file does not exist on server it will 404; keep navigation as is.
  });
});
</script>

</body>
</html>
