<?php
session_start();
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../my_farms.php");
  //  exit;
}
$DB_HOST = "localhost";
$DB_NAME = "agriculture";
$DB_USER = "root";
$DB_PASS = "";

// Create PDO
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$farmer_id = $_SESSION['farmer_id'];

$action = $_GET['action'] ?? 'view';

// DELETE FARM
if ($action === "delete" && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM farms WHERE id = :id AND farmer_id = :fid");
    $stmt->execute(['id' => $_GET['id'], 'fid' => $farmer_id]);
    header("Location: my_farms.php");
    exit;
}

// ADD FARM PROCESS
if ($action === "add_save") {

    $stmt = $pdo->prepare("
        INSERT INTO farms (farmer_id, farm_name, location, soil_type,size, created_at)
        VALUES (:fid, :name, :loc, :soil, :size, NOW())
    ");

    $stmt->execute([
        'fid' => $farmer_id,
        'name' => $_POST['farm_name'],
        'loc' => $_POST['location'],
        'soil' => $_POST['soil_type'],
        'size' => $_POST['size']
    ]);

    header("Location:my_farms.php");
    exit;
}

// FETCH FARM FOR EDIT
if ($action === "edit" && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM farms WHERE id = :id AND farmer_id = :fid LIMIT 1");
    $stmt->execute(['id' => $_GET['id'], 'fid' => $farmer_id]);
    $edit_farm = $stmt->fetch(PDO::FETCH_ASSOC);
}

// UPDATE FARM
if ($action === "edit_save") {

    $stmt = $pdo->prepare("
        UPDATE farms SET farm_name = :name, location = :loc, soil_type = :soil, size = :size 
        WHERE id = :id AND farmer_id = :fid
    ");

    $stmt->execute([
        'name' => $_POST['farm_name'],
        'loc' => $_POST['location'],
        'soil' => $_POST['soil_type'],
        'size' => $_POST['size'],
        'id' => $_POST['id'],
        'fid' => $farmer_id
    ]);

    header("Location:my_farms.php");
    exit;
}

$search = $_GET['search'] ?? '';

$stmt = $pdo->prepare("
    SELECT * FROM farms 
    WHERE farmer_id = :fid
    AND (farm_name LIKE :s OR location LIKE :s)
    ORDER BY id DESC
");
$stmt->execute(['fid' => $farmer_id, 's' => "%$search%"]);
$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
<title>Farm Management</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Poppins', sans-serif;
    background:linear-gradient(135deg,#0b9348,#1e88e5);
    margin: 0;
    padding: 0;
}

.container {
    width: 92%;
    margin: auto;
    padding: 20px;
}

h2 {
    color: #046b06;
}

.btn {
    padding: 8px 14px;
    background: #028a07;
    color: white;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
}

.btn:hover {
    background: #046b06;
}

/* TABLE */
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    color:white;
}

.table th, .table td {
    padding: 12px;
    border: 1px solid #cfd8dc;
    text-align:center;
}

.table th {
    background:white;
    color:black;
}

/* FORM */
.form-box {
    background: white;
    width: 450px;
    padding: 25px;
    border-radius: 10px;
    margin-top: 20px;
    box-shadow: 0 0 20px rgba(0,0,0,0.15);
    margin-left:30%;
}
h3{
    text-align:center;
}
input {
    width: 90%;
    padding: 12px;
    border: 2px solid #cfd8dc;
    border-radius: 6px;
    margin-top: 5px;
    margin-left:6px;
}

label {
    font-weight: 500;
    margin-top: 10px;
    display: block;
    margin-left:6px;
}

.save-btn {
    background: #028a07;
    color: white;
    border: none;
    width: 80%;
    padding: 12px;
    border-radius: 6px;
    margin-top: 10px;
    margin-left:40px;
}
h2{
    color:white;
    text-align:center;
}
</style>

</head>
<body>

<div class="container">

<h2>FARM MANAGEMENT</h2>

<?php if ($action === "add"): ?>

<!-- ADD FARM FORM -->
<div class="form-box">
<h3>ADD NEW FARM</h3>

<form method="POST" action="?action=add_save">

<label>Farm Name</label>
<input type="text" name="farm_name" required>

<label>Location</label>
<input type="text" name="location" required>

<label>Soil Type</label>
<input type="text" name="soil_type">

<label>Farm Size (acres)</label>
<input type="text" name="size">

<button class="save-btn">SAVE</button>

</form>
</div>

<?php elseif ($action === "edit"): ?>

<!-- EDIT FORM -->
<div class="form-box">
<h3>EDIT FARM</h3>

<form method="POST" action="?action=edit_save">

<input type="hidden" name="id" value="<?= $edit_farm['id'] ?>">

<label>Farm Name</label>
<input type="text" name="farm_name" value="<?= $edit_farm['farm_name'] ?>" required>

<label>Location</label>
<input type="text" name="location" value="<?= $edit_farm['location'] ?>" required>

<label>Soil Type</label>
<input type="text" name="soil_type" value="<?= $edit_farm['soil_type'] ?>">

<label>Farm Size</label>
<input type="text" name="size" value="<?= $edit_farm['size'] ?>">

<button class="save-btn">UPDATE</button>

</form>
</div>

<?php else: ?>

<!-- VIEW FARMS PAGE -->

<a class="btn" href="?action=add">+ ADD FARM</a>

<form method="GET" style="margin-top:15px;">
<input type="text" name="search" placeholder="Search farm..." value="<?= $search ?>">
<button class="btn">SEARCH</button>
</form>

<table class="table">
<tr>
    <th>#</th>
    <th>FARM NAME</th>
    <th>LOCATION</th>
    <th>SOIL TYPE</th>
    <th>SIZE</th>
    <th>ACTIONS</th>
</tr>

<?php foreach ($farms as $f): ?>
<tr>
    <td><?= $f['id'] ?></td>
    <td><?= $f['farm_name'] ?></td>
    <td><?= $f['location'] ?></td>
    <td><?= $f['soil_type'] ?></td>
    <td><?= $f['size'] ?></td>
    <td>
        <a class="btn" href="?action=edit&id=<?= $f['id'] ?>">EDIT</a>
        <a class="btn" style="background:#d32f2f" href="?action=delete&id=<?= $f['id'] ?>" 
           onclick="return confirm('Delete this farm?')">DELETE</a>
    </td>
</tr>
<?php endforeach; ?>

</table>

<?php endif; ?>

</div>

</body>
</html>
