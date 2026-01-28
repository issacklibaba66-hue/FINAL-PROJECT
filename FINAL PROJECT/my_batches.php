<?php
session_start();
$farmer_id = (int)$_SESSION['farmer_id'];

$DB_HOST = '127.0.0.1';
$DB_NAME = 'agriculture';
$DB_USER = 'root';
$DB_PASS = '';
try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("DB connection error: " . $e->getMessage());
}
// small helper
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }

// ---------------- ACTIONS ----------------
$action = $_GET['action'] ?? 'list';
$error = $success = null;

// DELETE BATCH (farmer only)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE b FROM crop_batches b JOIN crops c ON c.id = b.crop_id WHERE b.id = :id AND c.farmer_id = :fid");
    $stmt->execute([':id' => $id, ':fid' => $farmer_id]);
    header("Location: my_batches.php");
    exit;
}

// ADD BATCH
if ($action === 'add_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $crop_id = (int)($_POST['crop_id'] ?? 0);
    $quantity = trim($_POST['quantity'] ?? '');
    $harvest_date = trim($_POST['harvest_date'] ?? '');

    // verify crop belongs to farmer
    $chk = $pdo->prepare("SELECT id, crop_type, variety FROM crops WHERE id = :id AND farmer_id = :fid LIMIT 1");
    $chk->execute([':id' => $crop_id, ':fid' => $farmer_id]);
    $crop = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$crop) {
        $error = "Invalid crop selection.";
    } else {
        // create unique batch code
        $batch_code = strtoupper('BATCH-' . date('Ymd') . '-' . substr(md5(uniqid((string)rand(), true)), 0, 6));
        $qr_data = "batch_code:{$batch_code}";

        $ins = $pdo->prepare("INSERT INTO crop_batches (crop_id, batch_code, quantity, harvest_date, qr_code_path, created_at) VALUES (:cid, :bc, :qty, :hd, :qr, NOW())");
        $ins->execute([
            ':cid' => $crop_id,
            ':bc'  => $batch_code,
            ':qty' => $quantity ?: null,
            ':hd'  => $harvest_date ?: null,
            ':qr'  => $qr_data
        ]);
        $success = "Batch created: {$batch_code}";
    }
}

// ADD LOG (farmer adding a movement note)
if ($action === 'add_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = (int)($_POST['batch_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if ($batch_id > 0 && $status !== '') {
        // ensure batch belongs to farmer
        $bchk = $pdo->prepare("SELECT b.id FROM crop_batches b JOIN crops c ON c.id = b.crop_id WHERE b.id = :bid AND c.farmer_id = :fid LIMIT 1");
        $bchk->execute([':bid' => $batch_id, ':fid' => $farmer_id]);
        if ($bchk->fetchColumn()) {
            $ins = $pdo->prepare("INSERT INTO batch_status_logs (batch_id, actor_role, actor_id, status, notes, location, timestamp) VALUES (:bid, 'farmer', :aid, :st, :notes, :loc, NOW())");
            $ins->execute([':bid' => $batch_id, ':aid' => $farmer_id, ':st' => $status, ':notes' => $notes, ':loc' => $location]);
            $success = "Movement log added.";
            // redirect to timeline view for UX
            header("Location: my_batches.php?action=timeline&batch=" . $batch_id);
            exit;
        } else {
            $error = "You can only add logs to your own batches.";
        }
    } else {
        $error = "Status is required.";
    }
}

// ---------------- LIST / SEARCH / PAGINATION ----------------
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 8;
$start = ($page - 1) * $limit;

$params = [':fid' => $farmer_id];
$where = "WHERE c.farmer_id = :fid";
if ($search !== '') {
    $where .= " AND (b.batch_code LIKE :s OR c.crop_type LIKE :s OR c.variety LIKE :s)";
    $params[':s'] = "%$search%";
}

// total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM crop_batches b JOIN crops c ON c.id = b.crop_id {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $limit));

// fetch page rows
$sql = "SELECT b.*, c.crop_type, c.variety FROM crop_batches b JOIN crops c ON c.id = b.crop_id {$where} ORDER BY b.id DESC LIMIT :start, :lim";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
$stmt->execute();
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// fetch crops for add-select
$crs = $pdo->prepare("SELECT id, crop_type, variety FROM crops WHERE farmer_id = :fid ORDER BY created_at DESC");
$crs->execute([':fid' => $farmer_id]);
$crops = $crs->fetchAll(PDO::FETCH_ASSOC);

// ---------------- TIMELINE VIEW ----------------
$timelineEvents = [];
$timelineBatch = null;
if ($action === 'timeline' && isset($_GET['batch'])) {
    $batch_id = (int)$_GET['batch'];
    // verify ownership
    $bchk = $pdo->prepare("SELECT b.*, c.crop_type, c.variety, c.farmer_id FROM crop_batches b JOIN crops c ON c.id = b.crop_id WHERE b.id = :bid LIMIT 1");
    $bchk->execute([':bid' => $batch_id]);
    $timelineBatch = $bchk->fetch(PDO::FETCH_ASSOC);
    if ($timelineBatch && (int)$timelineBatch['farmer_id'] === $farmer_id) {
        $ls = $pdo->prepare("SELECT l.* FROM batch_status_logs l WHERE l.batch_id = :bid ORDER BY l.timestamp ASC");
        $ls->execute([':bid' => $batch_id]);
        $timelineEvents = $ls->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Batch not found or not yours.";
    }
}

// ---------------- HTML / UI ----------------
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Batches</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#f3f9f3;--card:#fff;--primary:#0b9348;--muted:#6b7a86;--shadow:0 12px 30px rgba(12,35,25,0.06)}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:'Poppins',sans-serif;
  background:linear-gradient(135deg,#0b9348,#1e88e5);
  color:#072b16;
  }
.container{max-width:1100px;margin:20px auto;padding:16px}
.header{display:flex;justify-content:space-between;align-items:center;gap:12px}
.header h2{margin:0}
.controls{
  display:flex;gap:8px;align-items:center}
.input{
  padding:10px;
  border-radius:8px;
  border:1px solid #e6f2ec;
  }
.btn{padding:8px 12px;border-radius:8px;border:none;background:var(--primary);color:#fff;cursor:pointer}
.btn.soft{background:#eef6f0;color:var(--primary)}
.card{
  width:100%;
  background:var(--card);
  padding:12px;
  border-radius:12px;
  box-shadow:var(--shadow);
  margin-top:12px;
  }
  .card1{
  background:darkblue;
  padding:12px;
  border-radius:12px;
  box-shadow:var(--shadow);
  margin-top:12px;
  color:white;
  }
.table{width:100%;border-collapse:collapse;margin-top:8px}
.table th,.table td{padding:10px;border-bottom:1px solid #f1f5f1;text-align:left}
.table thead th{background:#f7fbf8}
.qr-thumb{width:72px;height:72px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fbfffb;border:1px dashed #e6f2ec}
.small{
  color:white;
  font-size:13px;
  }
.timeline{display:flex;flex-direction:column;gap:12px;margin-top:12px}
.timeline .evt{background:var(--card);padding:12px;border-radius:10px;box-shadow:var(--shadow)}
.form-inline{display:flex;gap:8px;align-items:center}
.form-inline input, .form-inline select { padding:8px;border-radius:8px;border:1px solid #eef6ef}
.toast{position:fixed;right:20px;bottom:20px;background:#111;color:#fff;padding:10px 12px;border-radius:8px;display:none}
@media(max-width:900px){.header{flex-direction:column;align-items:flex-start}.form-inline{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>

<div class="container">
<div class="card1">
  <div class="header">
    <div>
      <h2>MAY BATCHES</h2>
      <div class="small">QR codes, traceability & timeline for your produce</div>
    </div>
<br><br>
    <div class="controls">
      <form method="GET" style="display:flex;gap:8px" class="form-inline">
        <input class="input" type="hidden" name="page" value="1">
        <input class="input" name="search" placeholder="Search batch code or crop" value="<?= esc($search) ?>">
        <button class="btn" type="submit"><i class="fa fa-search"></i></button>
      </form>
     
    </div>
  </div>
</div>
<br> 
<a href="?action=add"class="soft btn">+ADD BATCH</a>
  <?php if($error): ?>
    <div class="card" style="border-left:4px solid #d32f2f;color:#d32f2f"><?= esc($error) ?></div>
  <?php endif; ?>
  <?php if($success): ?>
    <div class="card" style="border-left:4px solid #0b9348;color:#0b9348"><?= esc($success) ?></div>
  <?php endif; ?>

  <?php if ($action === 'add'): ?>
    <!-- ADD BATCH -->
    <div class="card">
      <h3 style="margin:0 0 8px 0">ADD NEW BATCH</h3>
      <form id="batch" method="POST" action="?action=add_save">
        <label class="small">Select Crop</label>
        <select name="crop_id" required style="padding:10px;border-radius:8px;border:1px solid #e6f2ec">
          <option value="">-- choose crop --</option>
          <?php foreach($crops as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= esc($c['crop_type']) ?> <?= $c['variety'] ? ' — '.esc($c['variety']) : '' ?></option>
          <?php endforeach; ?>
        </select>

        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
          <input name="quantity" placeholder="Quantity (e.g. 120kg)" style="flex:1;padding:10px;border-radius:8px;border:1px solid #eef6ef">
          <input type="date" name="harvest_date" style="padding:10px;border-radius:8px;border:1px solid #eef6ef">
        </div>
        <div style="margin-top:10px;display:flex;gap:8px">
          <button class="btn" type="submit">Save Batch</button>
          <a class="btn soft" href="my_batches.php">Cancel</a>
        </div>
      </form>
    </div>
  <?php elseif ($action === 'timeline' && $timelineBatch): ?>
    <!-- TIMELINE VIEW -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <h3 style="margin:0"><?= esc($timelineBatch['batch_code']) ?></h3>
          <div class="small"><?= esc($timelineBatch['crop_type']) ?> <?= $timelineBatch['variety'] ? ' — '.esc($timelineBatch['variety']) : '' ?></div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <?php
            // QR image URL using free API
            $qrText = urlencode($timelineBatch['qr_code_path'] ?: 'batch:'. $timelineBatch['batch_code']);
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data={$qrText}";
          ?>
          <a class="btn soft" href="<?= $qrUrl ?>" target="_blank" title="Open QR"><i class="fa fa-qrcode"></i> Open QR</a>
          <a class="btn" href="<?= $qrUrl ?>" download="<?= esc($timelineBatch['batch_code']) ?>.png"><i class="fa fa-download"></i> Download QR</a>
          <a class="btn soft" href="my_batches.php"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:12px;align-items:flex-start">
        <div style="width:300px">
          <div class="qr-thumb"><img src="<?= $qrUrl ?>" style="max-width:100%;border-radius:6px" alt="QR"></div>
          <div class="small" style="margin-top:8px">Batch code: <strong><?= esc($timelineBatch['batch_code']) ?></strong></div>
          <div class="small">Quantity: <?= esc($timelineBatch['quantity'] ?? '—') ?></div>
          <div class="small">Harvest: <?= esc($timelineBatch['harvest_date'] ?? '—') ?></div>
        </div>

        <div style="flex:1">
          <h4 style="margin:0 0 8px 0">MOVEMENT TIMELINE</h4>

          <?php if(empty($timelineEvents)): ?>
            <div class="small">No events yet. Add a movement log below.</div>
          <?php else: ?>
            <div class="timeline">
              <?php foreach($timelineEvents as $i=>$ev): 
                $role = esc($ev['actor_role']);
                $actorName = ($ev['actor_role'] === 'farmer') ? 'You' : ('ID '.$ev['actor_id']);
              ?>
                <div class="evt">
                  <div style="display:flex;justify-content:space-between;align-items:center">
                    <div><strong><?= esc($ev['status']) ?></strong> <span class="small">• <?= esc($role) ?></span></div>
                    <div class="small"><?= esc($ev['timestamp']) ?></div>
                  </div>
                  <div class="small" style="margin-top:6px"><?= nl2br(esc($ev['notes'])) ?></div>
                  <?php if(!empty($ev['location'])): ?>
                  <div class="small" style="margin-top:8px">Location: <?= esc($ev['location']) ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Add log form -->
          <div style="margin-top:12px" class="card">
            <h4 style="margin:0 0 8px 0">ADD MOVEMENT LOG</h4>
            <form method="POST" action="?action=add_log">
              <input type="hidden" name="batch_id" value="<?= (int)$timelineBatch['id'] ?>">
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input name="status" placeholder="Status (e.g., Harvested, Delivered)" required style="flex:1;padding:8px;border-radius:8px;border:1px solid #eef6ef">
                <input name="location" placeholder="Location (optional)" style="width:200px;padding:8px;border-radius:8px;border:1px solid #eef6ef">
              </div>
              <textarea name="notes" rows="3" placeholder="Notes (optional)" style="width:100%;margin-top:8px;padding:8px;border-radius:8px;border:1px solid #eef6ef"></textarea>
              <div style="margin-top:8px;display:flex;gap:8px">
                <a href="?action=add_log"class="btn">ADD LOG</a>
                <a class="btn soft" href="?action=timeline&batch=<?= (int)$timelineBatch['id'] ?>">REFRESH</a>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- LIST VIEW -->
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th style="width:90px">QR</th>
            <th>BATCH CODE</th>
            <th>QUANTITY</th>
            <th>HARVEST</th>
            <th>TIME</th>
            <th style="width:220px">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($batches)): ?>
            <tr><td colspan="6" class="small">No batches found.</td></tr>
          <?php else: foreach($batches as $b): 
            $qrText = urlencode($b['qr_code_path'] ?: 'batch:'.$b['batch_code']);
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={$qrText}";
          ?>
            <tr>
              <td><div class="qr-thumb"><img src="<?= $qrUrl ?>" style="max-width:84%;"></div></td>
              <td>
                <div style="font-weight:600"><?= esc($b['batch_code']) ?></div>
                <div class="small"><?= esc($b['crop_type']) ?> <?= $b['variety'] ? '• '.esc($b['variety']) : '' ?></div>
              </td>
              <td><?= esc($b['quantity'] ?? '—') ?></td>
              <td><?= esc($b['harvest_date'] ?? '—') ?></td>
              <td class="small"><?= esc($b['created_at']) ?></td>
              <td><br>
                <a class="btn" href="?action=timeline&batch=<?= (int)$b['id'] ?>"><i class="fa fa-clock-rotate-left"></i> Timeline</a>
                <br><br>
                <a class="btn soft" href="<?= $qrUrl ?>" target="_blank"><i class="fa fa-qrcode"></i> Open QR</a>
                <br><br>
                <a class="btn" href="<?= $qrUrl ?>" download="<?= esc($b['batch_code']) ?>.png"><i class="fa fa-download"></i> Download</a>
                <br><br>
                <a class="btn" style="background:#d32f2f" href="?action=delete&id=<?= (int)$b['id'] ?>" onclick="return confirm('Delete this batch?')"><i class="fa fa-trash"></i> Delete</a>
                <br><br>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <!-- pager -->
      <div style="margin-top:12px;display:flex;gap:8px;justify-content:center;align-items:center">
        <?php for($p=1;$p<=$pages;$p++): ?>
          <a class="btn soft" style="<?= $p===$page ? 'background:var(--primary);color:#fff' : '' ?>" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>

  <?php endif; ?>

</div>

<div id="toast" class="toast"></div>

<script>
function showToast(msg, ok=true){
  const t = document.getElementById('toast');
  t.style.background = ok? '#111' : '#b71c1c';
  t.textContent = msg;
  t.style.display = 'block';
  setTimeout(()=> t.style.display='none', 3000);
}
<?php if($error): ?>
  showToast(<?= json_encode($error) ?>, false);
<?php endif; ?>
<?php if($success): ?>
  showToast(<?= json_encode($success) ?>, true);
<?php endif; ?>
</script>

</body>
</html>
