<?php
// farmer_batches.php
session_start();
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../farmer_login.php");
    exit;
}
$farmer_id = (int)$_SESSION['farmer_id'];

// DB config - change as needed
$DB_HOST = '127.0.0.1';
$DB_NAME = 'agriculture_db';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("DB connection error: " . $e->getMessage());
}

// Helper: farmer full name
function farmer_name($row) {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

// ---------------- ACTIONS ----------------
$action = $_GET['action'] ?? 'list';

// DELETE BATCH (farmer only)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM crop_batches WHERE id = :id AND crop_id IN (SELECT id FROM crops WHERE farmer_id = :fid)");
    $stmt->execute([':id' => $id, ':fid' => $farmer_id]);
    header("Location: farmer_batches.php");
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
        // QR path (we store the data used for QR; we can also store an external path if generating server-side)
        $qr_data = "batch_code:{$batch_code}"; // simple text embedded in QR
        // insert
        $ins = $pdo->prepare("INSERT INTO crop_batches (crop_id, batch_code, quantity, harvest_date, qr_code_path, created_at) VALUES (:cid, :bc, :qty, :hd, :qr, NOW())");
        $ins->execute([
            'cid' => $crop_id,
            'bc'  => $batch_code,
            'qty' => $quantity,
            'hd'  => $harvest_date ?: null,
            'qr'  => $qr_data
        ]);
        header("Location: farmer_batches.php");
        exit;
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
            header("Location: farmer_batches.php?action=timeline&batch=" . $batch_id);
            exit;
        } else {
            $error = "You can only add logs to your own batches.";
        }
    } else {
        $error = "Status is required.";
    }
}

// ---------------- FETCH DATA FOR LIST ----------------
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 8;
$start = ($page - 1) * $limit;

$params = ['fid' => $farmer_id
