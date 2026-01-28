<?php
session_start();

$processor_id = (int)$_SESSION['processor_id'];

//$stmt = $pdo->prepare("SELECT * FROM processors WHERE id = :id");



$pdo = new PDO(
    "mysql:host=localhost;dbname=agriculture;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }

$batchCode = $_GET['batch'] ?? '';
$batch = null;
$timeline = [];

/* FETCH BATCH */
if ($batchCode) {
    $q = $pdo->prepare("
        SELECT 
            b.id AS batch_id, b.batch_code, b.quantity,b.harvest_date,b.created_at,
            c.crop_type, c.variety,
            f.first_name, f.last_name,f.email,f.phone,f.location
        FROM crop_batches b
        JOIN crops c ON c.id=b.crop_id
        JOIN farmers f ON f.id=c.farmer_id
        WHERE b.batch_code=:bc LIMIT 1
    ");
    $q->execute(['bc'=>$batchCode]);
    $batch = $q->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        $t = $pdo->prepare("
            SELECT * FROM batch_status_logs
            WHERE batch_id=:id ORDER BY timestamp ASC
        ");
        $t->execute(['id'=>$batch['batch_id']]);
        $timeline = $t->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* SAVE PROCESSING LOG */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['batch_id'])) {

    $status = "Processed | Quality: ".$_POST['quality']." | Pack: ".$_POST['packaging'];

    $stmt = $pdo->prepare("
        INSERT INTO batch_status_logs
        (batch_id, actor_role, actor_id, status, notes, location, timestamp)
        VALUES (:bid,'processor',:pid,:st,:nt,:loc,NOW())
    ");
    $stmt->execute([
        'bid'=>$_POST['batch_id'],
        'pid'=>$processor_id,
        'st'=>$status,
        'nt'=>$_POST['notes'],
        'loc'=>$_POST['location']
    ]);

    header("Location: processor.php?batch=".$_POST['batch_code']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Processor Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,#0b9348,#1e88e5);
    }
.container{max-width:1100px;margin:auto;padding:16px}
.header{
    background:#6a1b9a;color:#fff;
    text-align:center;
    padding:20px;border-radius:14px;
    box-shadow:0 15px 35px rgba(0,0,0,.2)
}
.card{
    background:#fff;padding:16px;border-radius:14px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    margin-top:16px;transition:.3s
}
.card:hover{transform:translateY(-4px)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
input,textarea,select{
    width:90%;padding:10px;border-radius:10px;
    border:1px solid #ddd;font-family:Poppins
}
button{
    background:#6a1b9a;color:#fff;
    border:none;padding:12px;border-radius:10px;
    cursor:pointer;font-weight:500
}
.timeline{margin-top:20px}
.event{
    background:#fff;padding:14px;border-radius:12px;
    box-shadow:0 6px 18px rgba(0,0,0,.08);
    margin-bottom:10px;border-left:5px solid #6a1b9a
}
.badge{font-size:12px;color:#6a1b9a;font-weight:600}
</style>
</head>

<body>
<div class="container">

<div class="header">
    <h2><i class="fa fa-industry"></i> PROCESSOR UNIT</h2>
    <p>Scan QR or enter batch code</p>
</div>

<!-- SCAN -->
<div class="card">
    <form method="GET">
        <input name="batch" placeholder="Scan QR or enter batch code">
        <button style="margin-top:10px"><i class="fa fa-search"></i> LOAD</button>
    </form>
</div>

<?php if($batch): ?>
<div class="grid">

    <!-- BATCH INFO -->
    <div class="card">
        <h4>🌾 BATCH INFORMATION</h4>
        <div>BATCH CODE: <strong><?= esc($batch['batch_code']) ?></strong></div>
        <div>FARMER: <?= esc($batch['first_name'].' '.$batch['last_name']) ?></div>
        <div>CROP: <?= esc($batch['crop_type']) ?> (<?= esc($batch['variety']) ?>)</div>
        <div>TOTAL WEIGHT: <?= esc($batch['quantity']) ?> KG</div>
        <div>DATE ONBORDED: <?= esc($batch['created_at']) ?> GMT</div>
        <div>HARVEST DATE: <?= esc($batch['harvest_date']) ?> GMT</div>
        <div>FROM: <?= esc($batch['location']) ?> </div>
        <div>PHONE: <?= esc($batch['phone']) ?> </div>
        <div>EMAIL: <?= esc($batch['email']) ?></div>
    </div>

    <!-- PROCESSING FORM -->
    <div class="card">
        <h4>🏭 PROCESSING UPDATE</h4>
        <form method="POST">
            <input type="hidden" name="batch_id" value="<?= $batch['batch_id'] ?>">
            <input type="hidden" name="batch_code" value="<?= esc($batch['batch_code']) ?>">

            <label>Processing Date</label>
            <input type="date" required>

            <label>Quality Check</label>
            <select name="quality">
                <option>Passed</option>
                <option>Grade A</option>
                <option>Grade B</option>
                <option>Failed</option>
            </select>

            <label>Packaging Details</label>
            <input name="packaging" placeholder="e.g. 50kg bags" required>

            <label>Location</label>
            <input name="location" placeholder="Processing facility" required>

            <textarea name="notes" placeholder="Processing notes"></textarea>

            <button style="margin-top:10px">CONFIRM</button>
        </form>
    </div>
</div>

<!-- TIMELINE -->
<div class="card timeline">
    <h4>🔄 BATCH TIMELINE</h4>
    <?php foreach($timeline as $t): ?>
        <div class="event">
            <div class="badge"><?= strtoupper($t['actor_role']) ?></div>
            <div><?= esc($t['status']) ?></div>
            <div><?= esc($t['location']) ?></div>
            <div style="font-size:12px;color:#777"><?= esc($t['timestamp']) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
</body>
</html>