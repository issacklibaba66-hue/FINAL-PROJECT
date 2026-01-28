<?php
// verify.php
$DB_HOST = "localhost";
$DB_NAME = "agriculture";
$DB_USER = "root";
$DB_PASS = "";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database error");
}

function esc($s){
    return htmlspecialchars($s ?? '', ENT_QUOTES);
}

$batchCode = $_GET['batch'] ?? '';
if ($batchCode && isset($_GET['data'])) {
    $batchCode= str_replace('batch_code:', $_GET['data']);
}
if(!$batchCode){
    die("invalid QR code")
}
// FETCH MAIN BATCH DATA
$stmt = $pdo->prepare("
    SELECT 
        b.batch_code, b.quantity, b.harvest_date,
        c.crop_type, c.variety, c.planted_date,
        f.first_name, f.middle_name, f.last_name, f.location
    FROM crop_batches b
    JOIN crops c ON c.id = b.crop_id
    JOIN farmers f ON f.id = c.farmer_id
    WHERE b.batch_code = :bc
    LIMIT 1
");
$stmt->execute([':bc' => $batchCode]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die("Batch not found");
}

// FETCH TRACEABILITY LOGS
$logs = $pdo->prepare("
    SELECT * FROM batch_status_logs
    WHERE batch_id = (
        SELECT id FROM crop_batches WHERE batch_code = :bc LIMIT 1
    )
    ORDER BY timestamp ASC
");
$logs->execute([':bc' => $batchCode]);
$timeline = $logs->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Product Verification</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:#f4faf6;
}
.container{
    max-width:1100px;
    margin:auto;
    padding:16px;
}
.header{
    background:#0b9348;
    color:#fff;
    padding:20px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,.15);
}
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:16px;
    margin-top:16px;
}
.card{
    background:#fff;
    padding:16px;
    border-radius:14px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    transition:.3s;
}
.card:hover{
    transform:translateY(-4px);
}
.title{
    font-weight:600;
    margin-bottom:8px;
}
.small{
    font-size:13px;
    color:#666;
}
.timeline{
    margin-top:20px;
}
.event{
    background:#fff;
    padding:14px;
    border-radius:12px;
    margin-bottom:10px;
    box-shadow:0 6px 18px rgba(0,0,0,.08);
}
.badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:20px;
    background:#e8f6ee;
    color:#0b9348;
    font-size:12px;
}
footer{
    text-align:center;
    margin-top:30px;
    font-size:13px;
    color:#777;
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <h2><i class="fa fa-qrcode"></i> Product Verification</h2>
        <div>Batch Code: <strong><?= esc($batch['batch_code']) ?></strong></div>
    </div>

    <div class="grid">

        <div class="card">
            <div class="title">🌾 Crop Details</div>
            <div class="small">Type: <?= esc($batch['crop_type']) ?></div>
            <div class="small">Variety: <?= esc($batch['variety']) ?></div>
            <div class="small">Planted: <?= esc($batch['planted_date']) ?></div>
            <div class="small">Harvested: <?= esc($batch['harvest_date']) ?></div>
        </div>

        <div class="card">
            <div class="title">🚜 Farm & Farmer</div>
            <div class="small">
                <?= esc($batch['first_name']." ".$batch['middle_name']." ".$batch['last_name']) ?>
            </div>
            <div class="small">Location: <?= esc($batch['location']) ?></div>
        </div>

        <div class="card">
            <div class="title">📦 Batch Summary</div>
            <div class="small">Quantity: <?= esc($batch['quantity']) ?></div>
            <div class="badge">Verified</div>
        </div>

    </div>

    <div class="timeline">
        <h3>🔍 Supply Chain Traceability</h3>

        <?php if(empty($timeline)): ?>
            <div class="small">No traceability records available.</div>
        <?php else: foreach($timeline as $t): ?>
            <div class="event">
                <strong><?= esc(strtoupper($t['actor_role'])) ?></strong>
                <div class="small"><?= esc($t['status']) ?></div>
                <div class="small"><?= esc($t['location']) ?></div>
                <div class="small"><?= esc($t['timestamp']) ?></div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <footer>
        ✔ This product is digitally traceable & verified via QR Code
    </footer>

</div>

</body>
</html>
