<?php
// farmer/dashboard.php
session_start();

$DB_HOST = 'localhost';
$DB_NAME = 'agriculture'; 
$DB_USER = 'root';
$DB_PASS = '';

define('OPENWEATHER_API_KEY', 'cc76f97ed44b85ef9221117b9eff168b');

// Angalia kama mkulima ameingia
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'farmer') {
    header("Location: index.php");
    exit;
}

$farmer_id = (int)$_SESSION['farmer_id'];

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// 1. Fetch Farmer info
$stmt = $pdo->prepare("SELECT * FROM farmers WHERE id = :id");
$stmt->execute([':id' => $farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farmer) {
    die("Farmer not found");
}

// 2. My farms
$farms = [];
$checkFarms = $pdo->query("SHOW TABLES LIKE 'farms'")->fetchColumn();
if ($checkFarms) {
    $stm = $pdo->prepare("SELECT * FROM farms WHERE farmer_id = :fid ORDER BY id DESC");
    $stm->execute([':fid' => $farmer_id]);
    $farms = $stm->fetchAll(PDO::FETCH_ASSOC);
}

// 3. My crops
$stm = $pdo->prepare("SELECT * FROM crops WHERE farmer_id = :fid ORDER BY created_at DESC");
$stm->execute([':fid' => $farmer_id]);
$crops = $stm->fetchAll(PDO::FETCH_ASSOC);

// 4. My batches
$batchList = [];
$checkBatches = $pdo->query("SHOW TABLES LIKE 'crop_batches'")->fetchColumn();
if ($checkBatches) {
    $stm = $pdo->prepare("SELECT b.*, c.crop_type, c.variety FROM crop_batches b
                         LEFT JOIN crops c ON c.id = b.crop_id
                         WHERE c.farmer_id = :fid
                         ORDER BY b.created_at DESC");
    $stm->execute([':fid' => $farmer_id]);
    $batchList = $stm->fetchAll(PDO::FETCH_ASSOC);
}

// 5. Notifications
$notifications = [];
$checkNotif = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchColumn();
if ($checkNotif) {
    $stm = $pdo->prepare("SELECT n.*, a.title, a.message, a.created_at AS advisory_created_at
                         FROM notifications n
                         LEFT JOIN advisory_messages a ON a.id = n.advisory_id
                         WHERE n.farmer_id = :fid
                         ORDER BY n.created_at DESC LIMIT 50");
    $stm->execute([':fid' => $farmer_id]);
    $notifications = $stm->fetchAll(PDO::FETCH_ASSOC);
}

// 6. Weather Logic
$weather = null;
if (defined('OPENWEATHER_API_KEY') && !empty($farmer['location'])) {
    $api = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($farmer['location']) . "&units=metric&appid=" . OPENWEATHER_API_KEY;
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $json = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http === 200 && $json) {
        $w = json_decode($json, true);
        if (isset($w['main'])) {
            $weather = [
                'temp' => $w['main']['temp'],
                'desc' => $w['weather'][0]['description'] ?? '',
                'icon' => $w['weather'][0]['icon']
            ];
        }
    }
}

// Bank Details
$query = "SELECT f.*, b.bank_name, b.account_number, b.account_name, b.expiry_date, b.cvv 
          FROM farmers f 
          LEFT JOIN bank_details b ON f.id = b.farmer_id 
          WHERE f.id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$farmer_id]);
$data = $stmt->fetch();

function fullname($r) { 
    return trim(($r['first_name']??'') . ' ' . ($r['last_name']??'')); 
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Farmer Dashboard — <?= $farmer ? htmlspecialchars(fullname($farmer)) : 'Farmer' ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b9348;
            --primary-dark: #086e36;
            --accent: #1e88e5;
            --bg: #f4f7f6;
            --card-bg: #ffffff;
            --text-main: #2d3436;
            --text-muted: #636e72;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* --- Header & Navigation --- */
        .topbar {
            background: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header-left { display: flex; align-items: center; gap: 15px; }
        
        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(11, 147, 72, 0.3);
        }

        .user-info .hi { font-size: 14px; font-weight: 700; color: var(--text-main); margin: 0; }
        .user-info .loc { font-size: 11px; color: var(--text-muted); margin: 0; }

        /* --- Modern Sidebar --- */
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100%;
            background: #1a1a1a;
            color: white;
            transition: var(--transition);
            z-index: 2000;
            padding: 30px 20px;
        }

        .sidebar.active { left: 0; }
        
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            z-index: 1500;
        }

        .sidebar-overlay.active { display: block; }

        .sidebar h2 { color: var(--primary); margin-bottom: 30px; font-size: 22px; padding-left: 10px; }
        
        .nav-links { list-style: none; padding: 0; }
        .nav-links li { margin-bottom: 10px; }
        .nav-links a {
            color: #bdc3c7;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            border-radius: 10px;
            transition: var(--transition);
        }

        .nav-links a:hover, .nav-links a.active {
            background: var(--primary);
            color: white;
        }

        /* --- Main Content --- */
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 20px;
            padding: 25px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        /* --- Grid & Cards --- */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 18px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.03);
            transition: var(--transition);
        }

        .stat-card:hover { transform: translateY(-5px); }

        .stat-card i {
            font-size: 24px;
            color: var(--primary);
            background: #e8f5ed;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .stat-card .val { font-size: 28px; font-weight: 700; display: block; }
        .stat-card .label { color: var(--text-muted); font-size: 14px; }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .panel {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        /* --- Weather & Bank UI --- */
        .weather-widget {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
        }

        .bank-card-visual {
            perspective: 1000px;
            width: 100%;
            max-width: 320px;
            height: 190px;
            margin: 15px auto;
        }

        .bank-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.6s;
            transform-style: preserve-3d;
            cursor: pointer;
        }

        .bank-inner.flipped { transform: rotateY(180deg); }

        .card-front, .card-back {
            position: absolute;
            width: 100%; height: 100%;
            backface-visibility: hidden;
            border-radius: 15px;
            padding: 20px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-front { background: linear-gradient(135deg, #1e2a38, #3e4a59); }
        .card-back { background: #2d3436; transform: rotateY(180deg); }

        /* --- Responsive Logic --- */
        @media (max-width: 992px) {
            .main-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .topbar { padding: 10px 15px; }
            .hi { font-size: 13px; }
            .welcome-banner { padding: 20px; }
        }

        .menu-btn {
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-main);
        }
        
        .btn-modern {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .btn-modern:hover {
             background: var(--primary-dark);
         }
         /* Style ya Modal yenye Blur */
.modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px); /* Hii ndiyo inaleta blur background */
    display: none; /* Inafichwa mwanzoni */
    justify-content: center;
    align-items: center;
    z-index: 3000;
    padding: 20px;
}

.modal-card {
    background: white;
    width: 100%;
    max-width: 500px;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    position: relative;
    max-height: 80vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.close-btn {
    position: absolute;
    top: 15px; right: 15px;
    background: #f0f0f0;
    border: none;
    width: 30px; height: 30px;
    border-radius: 50%;
    cursor: pointer;
    font-weight: bold;
}

.adv-item-list {
    padding: 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: 0.2s;
}
.badge-new {
    position: absolute;
    top: -5px;
    left: -5px;
    background: #e74c3c; /* Rangi nyekundu au tumia var(--primary) */
    color: white;
    font-size: 9px;
    font-weight: bold;
    padding: 2px 8px;
    border-radius: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 1;
}

/* Blur effect kwa background */
.modal-overlay {
    backdrop-filter: blur(10px) brightness(0.8);
    -webkit-backdrop-filter: blur(10px) brightness(0.8);
}
.adv-item-list:hover { background: #f9f9f9; }
.badge-new {
    position: absolute;
    top: -5px;
    left: -5px;
    background: #e74c3c; /* Rangi nyekundu au tumia var(--primary) */
    color: white;
    font-size: 9px;
    font-weight: bold;
    padding: 2px 8px;
    border-radius: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 1;
}

/* Blur effect kwa background */
.modal-overlay {
    backdrop-filter: blur(10px) brightness(0.8);
    -webkit-backdrop-filter: blur(10px) brightness(0.8);
}
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="overlay"></div>

    <aside class="sidebar" id="sidebar">
        <h2>M-Shamba</h2>
        <ul class="nav-links">
            <li><a href="#" class="active"><i class="fa fa-home"></i> Home</a></li>
            <li><a href="my_farms.php"><i class="fa fa-tractor"></i> My Farms</a></li>
            <li><a href="my_crops.php"><i class="fa fa-seedling"></i> My Crops</a></li>
            <li><a href="my_batches.php"><i class="fa fa-qrcode"></i> Batches</a></li>
            <li><a href="payment.php"><i class="fa fa-wallet"></i> Payments</a></li>
            <li><a href="settings.php"><i class="fa fa-gear"></i> Settings</a></li>
            <li><a href="farmer_report.php"><i class="fa fa-gear"></i> reports</a></li>
            <li style="margin-top: 50px;"><a href="logout.php" style="color: #ff7675;"><i class="fa fa-sign-out"></i> Logout</a></li>
        </ul>
    </aside>

    <div class="topbar">
        <div class="header-left">
            <button class="menu-btn" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
            <div class="avatar"><?= strtoupper(substr($farmer['first_name'] ?? 'F',0,1)) ?></div>
            <div class="user-info">
                <p class="hi">HELLOW!!, <?= htmlspecialchars($farmer['first_name'] ?? 'FARMER') ?></p>
                <p class="loc"><i class="fa fa-location-dot"></i> <?= htmlspecialchars($farmer['location'] ?? 'Location') ?></p>
            </div>
        </div>
        <div class="header-right" style="display: flex; gap: 15px; align-items: center;">
            <div style="position: relative;">
                <i class="fa fa-bell" style="font-size: 20px; color: var(--text-muted);"></i>
                <span style="position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; width: 15px; height: 15px; font-size: 10px; display: flex; align-items: center; justify-content: center;"><?= count($notifications) ?></span>
            </div>
            <?php if ($weather): ?>
                <img src="https://openweathermap.org/img/wn/<?= $weather['icon'] ?>.png" width="35" alt="weather">
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <div class="welcome-banner">
            <h2 style="margin:0">WELCOME BACK, <?= htmlspecialchars($farmer['first_name']) ?>!</h2>
            <p style="opacity: 0.9; font-size: 14px;">TODAY WEATHER: <?= $weather ? $weather['desc'] : 'Better fo Farming' ?>. Check crop progress.</p>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <i class="fa fa-tractor"></i>
                <span class="val"><?= count($farms) ?></span>
                <span class="label">MAY FARMS</span>
            </div>
            <div class="stat-card">
                <i class="fa fa-leaf"></i>
                <span class="val"><?= count($crops) ?></span>
                <span class="label">MY CROPS</span>
            </div>
            <div class="stat-card">
                <i class="fa fa-box-open"></i>
                <span class="val"><?= count($batchList) ?></span>
                <span class="label">MY BATCHES</span>
            </div>
        </div>

        <div class="main-grid">
            <div class="content-left">
                <div class="panel">
                    <div class="panel-header">
                        <h3 style="margin:0">MESSAGES</h3>
                        <a href="javascript:void(0)" onclick="viewAllAdvisories()"style="font-size: 12px; color: var(--primary); text-decoration:none">View All</a>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <p style="color: var(--text-muted); font-size: 14px;">No Message Yet.</p>
                    <?php else: foreach (array_slice($notifications, 0, 3) as $n): ?>
                        <div style="padding: 15px; border-radius: 12px; background: #fcfcfc; border: 1px solid #f0f0f0; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">

                            <div>
                                <h4 style="margin: 0; font-size: 14px;"><?= htmlspecialchars($n['title'] ?? 'Ushauri') ?></h4>
                                <small style="color: var(--text-muted)"><?= date('M d, Y', strtotime($n['created_at'])) ?></small>
                            </div>
                            <a href="?action=read_adv&id=<?= $n['id'] ?>" class="btn-modern" style="padding: 5px 12px; font-size: 12px;">Read</a>
                            <button onclick="readSingle(<?= $index ?>)" class="btn-modern">Soma</button> 
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="content-right">
                <div class="panel">
                    <h3 style="margin:0 0 15px 0">BENK DETAILS</h3>
                    <div class="bank-card-visual">
                        <div class="bank-inner" id="bankCard" onclick="this.classList.toggle('flipped')">
                            <div class="card-front">
                                <div style="display:flex; justify-content:space-between; align-items:start">
                                    <span style="font-weight:600"><?= htmlspecialchars($data['bank_name'] ?? 'BANK') ?></span>
                                    <i class="fa fa-microchip" style="color: #ffd700; font-size: 24px;"></i>
                                </div>
                                <div style="font-size: 18px; letter-spacing: 2px; margin: 20px 0;">
                                    **** **** **** <?= substr($data['account_number'] ?? '0000', -4) ?>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size: 12px; opacity: 0.8;">
                                    <span><?= strtoupper($data['account_name'] ?? 'NAME') ?></span>
                                    <span><?= $data['expiry_date'] ?? 'MM/YY' ?></span>
                                </div>
                            </div>
                            <div class="card-back">
                                <div style="background:#000; height:35px; width:114%; margin-left:-20px; margin-top:10px"></div>
                                <div style="background:white; color:black; width:40px; padding:5px; text-align:center; align-self:flex-end; margin-top:20px; font-size:12px; border-radius:4px">
                                    <?= $data['cvv'] ?? '***' ?>
                                </div>
                                <p style="font-size: 8px; margin-top: 10px;">This card is property crdb.</p>
                            </div>
                        </div>
                    </div>
                    <p style="text-align: center; font-size: 12px; color: var(--text-muted); cursor: pointer;" onclick="document.getElementById('bankCard').classList.toggle('flipped')">
                        <i class="fa fa-rotate"></i> Click Card To View CVV
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        document.getElementById('overlay').onclick = toggleSidebar;

        // Boresha muonekano wa kadi za mazao kama unazihitaji
        function openAdvisory(id){
  if(!id){ alert('No advisory id'); return; }
  // Simple client-side show using existing page data: ideally implement endpoint to fetch full advisory by id
  // We'll try to open a new page if exists
  window.location.href = '?advModal=' + encodeURIComponent(id);
}
function closeAdv(){
   document.getElementById('advModal').style.display = 'none';
    }
    </script>
    <?php if (isset($action) && $action === 'view_all_adv'): ?>
<div class="modal-overlay">
    <div class="modal-content">
        <a href="farmer_dashboard.php" class="close-modal">&times;</a>
        <h3>ALL ADVISORIES</h3>
        <hr>
        <?php if (empty($notifications)): ?>
    <p style="color: var(--text-muted); font-size: 14px;">Huna mawaidha mapya kwa sasa.</p>
<?php else: ?>
    <?php foreach (array_slice($notifications, 0, 3) as $index => $n): ?>
        <div style="padding: 15px; border-radius: 12px; background: #fcfcfc; border: 1px solid #f0f0f0; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; position: relative;">
            
            <span class="badge-new" id="badge-<?= $index ?>">NEW</span>

            <div>
                <h4 style="margin: 0; font-size: 14px;"><?= htmlspecialchars($n['title'] ?? 'Ushauri') ?></h4>
                <small style="color: var(--text-muted)"><?= date('M d, Y', strtotime($n['created_at'])) ?></small>
            </div>
            
            <button onclick="readSingle(<?= $index ?>)" class="btn-modern" style="padding: 5px 12px; font-size: 12px;">Soma</button>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php 
if (isset($action) && $action === 'read_adv' && isset($_GET['id'])): 
    $selected = null;
    if (isset($notifications)) {
        foreach ($notifications as $n) {
            if ((int)$n['id'] === (int)$_GET['id']) { 
                $selected = $n; 
                break; 
            }
        }
    }
    if ($selected):
?>
<div class="modal-overlay">
    <div class="modal-content">
        <a href="?action=view_all_adv" class="close-modal">&larr;</a>
        <h3 style="color: #0b9348;"><?php echo esc($selected['title']); ?></h3>
        <p><small>Date: <?php echo $selected['advisory_created_at'] ?? $selected['created_at'] ?? ''; ?></small></p>
        <hr>
        <div style="line-height: 1.6; font-size: 16px;">
            <?php echo nl2br(esc($selected['message'])); ?>
        </div>
        <br>
        <a href="farmer_dashboard.php" style="display: block; text-align: center; background: #0b9348; color: white; padding: 10px; border-radius: 5px; text-decoration: none;">Close</a>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<div class="modal-overlay" id="globalModal">
    <div class="modal-card">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <div id="modalContent">
            </div>
    </div>
</div>
<script>
// Hakikisha hii ipo juu ya script yako ili kubeba data za mawaidha
const allAdvisories = <?= json_encode($notifications) ?>;

function showModal(contentHtml) {
    document.getElementById('modalContent').innerHTML = contentHtml;
    document.getElementById('globalModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('globalModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Function ya kusoma ujumbe mmoja
function readSingle(index) {
    const adv = allAdvisories[index];
    if (!adv) return; // Usalama kama data haipo

    const html = `
        <h3 style="color:var(--primary); margin-bottom:5px;">${adv.title || 'Ushauri'}</h3>
        <p style="font-size:11px; color:gray; margin-bottom:15px;">Iliwekwa: ${adv.created_at}</p>
        <div style="line-height:1.7; font-size:15px; color:#333; background:#f9f9f9; padding:15px; border-radius:10px;">
            ${adv.message.replace(/\n/g, '<br>')}
        </div>
        <br>
        <button class="btn-modern" style="width:100%; padding:12px;" onclick="closeModal()">Nimeelewa</button>
    `;
    
    showModal(html);

    // Ondoa badge ya "NEW" baada ya kusoma
    const badge = document.getElementById('badge-' + index);
    if (badge) {
        badge.style.transition = "opacity 0.5s ease";
        badge.style.opacity = "0";
        setTimeout(() => badge.remove(), 500);
    }
}

// Function ya kuona mawaidha yote
function viewAllAdvisories() {
    let html = '<h3 style="margin-bottom:15px;">Mawaidha Yote</h3><div style="max-height:400px; overflow-y:auto;">';
    
    if (allAdvisories.length === 0) {
        html += '<p style="text-align:center; color:gray;">Hakuna ujumbe wowote.</p>';
    } else {
        allAdvisories.forEach((adv, index) => {
            html += `
                <div class="adv-item-list" onclick="readSingle(${index})" style="padding:12px; border-bottom:1px solid #eee; transition:background 0.3s;">
                    <div style="font-weight:600; font-size:14px;">${adv.title || 'Ushauri'}</div>
                    <small style="color:gray;">${adv.created_at}</small>
                </div>`;
        });
    }
    
    html += '</div>';
    showModal(html);
}
// Data ya mawaidha kutoka PHP kwenda JS
const allAdvisories = <?= json_encode($notifications) ?>;

function closeModal() {
    document.getElementById('globalModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Rudisha scrolling
}

function showModal(contentHtml) {
    document.getElementById('modalContent').innerHTML = contentHtml;
    document.getElementById('globalModal').style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Zuia scrolling ya nyuma
}

// 1. Kazi ya "Ona Zote"
function viewAllAdvisories() {
    let html = '<h3>Mawaidha Yote</h3><hr>';
    if (allAdvisories.length === 0) {
        html += '<p>Hakuna mawaidha kwa sasa.</p>';
    } else {
        allAdvisories.forEach((adv, index) => {
            html += `
                <div class="adv-item-list" onclick="readSingle(${index})">
                    <strong>${adv.title || 'Ushauri'}</strong><br>
                    <small style="color:gray">${adv.created_at}</small>
                </div>`;
        });
    }
    showModal(html);
}

// 2. Kazi ya "Soma" (Moja kwa moja au kutoka kwenye list)
function readSingle(index) {
    const adv = allAdvisories[index];
    const html = `
        <h3 style="color:var(--primary)">${adv.title || 'Ushauri'}</h3>
        <p style="font-size:12px; color:gray">Tarehe: ${adv.created_at}</p>
        <hr>
        <div style="line-height:1.6; padding-top:10px">
            ${adv.message.replace(/\n/g, '<br>')}
        </div>
        <br>
        <button class="btn-modern" style="width:100%" onclick="closeModal()">Nimeelewa</button>
    `;
    showModal(html);
}
</script>
</body>
</html>