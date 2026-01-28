<?php
session_start();
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../my_crops.php");
    exit;
}
$DB_HOST = "localhost";
$DB_NAME = "agriculture";
$DB_USER = "root";
$DB_PASS = "";
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
$farmer_id = $_SESSION['farmer_id'];

// ACTION
$action = $_GET['action'] ?? 'view';

if ($action === "delete" && isset($_GET['id'])) {

    $stmt = $pdo->prepare("DELETE FROM crops WHERE id = :id AND farmer_id = :fid");
    $stmt->execute(['id' => $_GET['id'], 'fid' => $farmer_id]);

    header("Location:my_crops.php");
    exit;
}

if ($action === "add_save") {

    $stmt = $pdo->prepare("
        INSERT INTO crops (farmer_id, crop_type, variety, planted_date, expected_harvest, created_at)
        VALUES (:fid, :type, :var, :plant, :harvest, NOW())
    ");

    $stmt->execute([
        'fid'     => $farmer_id,
        'type'    => $_POST['crop_type'],
        'var'     => $_POST['variety'],
        'plant'   => $_POST['planted_date'],
        'harvest' => $_POST['expected_harvest']
    ]);

    header("Location:my_crops.php");
    exit;
}

/*===============================
= 3. FETCH FOR EDIT
===============================*/
if ($action === "edit" && isset($_GET['id'])) {

    $stmt = $pdo->prepare("SELECT * FROM crops WHERE id = :id AND farmer_id = :fid LIMIT 1");
    $stmt->execute(['id' => $_GET['id'], 'fid' => $farmer_id]);

    $edit_crop = $stmt->fetch(PDO::FETCH_ASSOC);
}

/*===============================
= 4. SAVE EDIT
===============================*/
if ($action === "edit_save") {

    $stmt = $pdo->prepare("
        UPDATE crops SET 
        crop_type = :type, 
        variety = :var, 
        planted_date = :plant, 
        expected_harvest = :harvest
        WHERE id = :id AND farmer_id = :fid
    ");

    $stmt->execute([
        'type'    => $_POST['crop_type'],
        'var'     => $_POST['variety'],
        'plant'   => $_POST['planted_date'],
        'harvest' => $_POST['expected_harvest'],
        'id'      => $_POST['id'],
        'fid'     => $farmer_id
    ]);

    header("Location:my_crops.php");
    exit;
}

/*===============================
= 5. FETCH ALL CROPS + SEARCH
===============================*/
$search = $_GET['search'] ?? '';

$stmt = $pdo->prepare("
    SELECT * FROM crops 
    WHERE farmer_id = :fid
    AND (crop_type LIKE :s OR variety LIKE :s)
    ORDER BY id DESC
");
$stmt->execute([
    'fid' => $farmer_id,
    's'   => "%$search%"
]);
$crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>My Crops</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body { font-family:Poppins; background:#f1f8e9; padding:20px; }
.container { width:92%; margin:auto; }

h2 { color:#046b06; }

.btn {
    background:#028a07;
    color:white;
    padding:8px 14px;
    border-radius:6px;
    text-decoration:none;
    font-size:14px;
}
.btn:hover { background:#046b06; }

.table {
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}
.table th, .table td {
    border:1px solid #cfd8dc;
    padding:12px;
}
.table th { background:#a5d6a7; }

.form-box {
    width:450px;
    background:white;
    padding:25px;
    border-radius:12px;
    margin-top:20px;
    box-shadow:0 0 20px rgba(0,0,0,0.15);
}

input {
    width:100%;
    padding:12px;
    border:2px solid #cfd8dc;
    border-radius:6px;
    margin-top:5px;
}

label {
    display:block;
    margin-top:10px;
    font-weight:500;
}

.save-btn {
    width:100%;
    padding:12px;
    border:none;
    background:#028a07;
    color:white;
}
</style>

</head>
<body>

<div class="container">

<h2>My Crops</h2>

<?php if ($action === "add"): ?>

<!-- ADD FORM -->
<div class="form-box">
<h3>ADD NEW CROP</h3>

<form method="POST" action="?action=add_save">

<label>Crop Type</label>
<input type="text" name="crop_type" required placeholder="e.g. Maize, Rice, Tomatoes">

<label>Variety</label>
<input type="text" name="variety" required placeholder="e.g. Hybrid H513, TXD306">

<label>Planted Date</label>
<input type="date" name="planted_date" required>

<label>Expected Harvest Date</label>
<input type="date" name="expected_harvest" required>

<button class="save-btn">Save Crop</button>

</form>
</div>

<?php elseif ($action === "edit"): ?>

<!-- EDIT FORM -->
<div class="form-box">
<h3>Edit Crop</h3>

<form method="POST" action="?action=edit_save">

<input type="hidden" name="id" value="<?= $edit_crop['id'] ?>">

<label>Crop Type</label>
<input type="text" name="crop_type" value="<?= $edit_crop['crop_type'] ?>" required>

<label>Variety</label>
<input type="text" name="variety" value="<?= $edit_crop['variety'] ?>" required>

<label>Planted Date</label>
<input type="date" name="planted_date" value="<?= $edit_crop['planted_date'] ?>" required>

<label>Expected Harvest</label>
<input type="date" name="expected_harvest" value="<?= $edit_crop['expected_harvest'] ?>" required>

<button class="save-btn">Update Crop</button>

</form>
</div>

<?php else: ?>

<!-- VIEW CROPS LIST -->

<!-- ADD BUTTON -->
<a class="btn" href="?action=add">+ Add Crop</a>

<!-- SEARCH -->
<form method="GET" style="margin-top:15px;">
<input type="text" name="search" placeholder="Search crop..." value="<?= $search ?>">
<button class="btn">Search</button>
</form>

<!-- TABLE -->
<table class="table">
<tr>
    <th>Crop Type</th>
    <th>Variety</th>
    <th>Planted</th>
    <th>Expected Harvest</th>
    <th>Action</th>
</tr>

<?php foreach ($crops as $c): ?>
<tr>
    <td><?= $c['crop_type'] ?></td>
    <td><?= $c['variety'] ?></td>
    <td><?= $c['planted_date'] ?></td>
    <td><?= $c['expected_harvest'] ?></td>
    <td>
        <a class="btn" href="?action=edit&id=<?= $c['id'] ?>">Edit</a>
        <a class="btn" style="background:#d32f2f" 
           href="?action=delete&id=<?= $c['id'] ?>"
           onclick="return confirm('Delete this crop?')">
           Delete
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

<?php endif; ?>

</div>

</body>
</html>
