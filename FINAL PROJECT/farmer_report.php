<?php
// farmer_report.php
session_start();

$DB_HOST = 'localhost';
$DB_NAME = 'agriculture'; 
$DB_USER = 'root';
$DB_PASS = '';
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
if(!isset($_SESSION['role']) || $_SESSION['role']!=='farmer'){
    header("Location: ../login.php"); exit;
}

$farmer_id = $_SESSION['farmer_id'];
$error=$success=null;

// HANDLE SUBMIT
if($_SERVER['REQUEST_METHOD']==='POST'){

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    $filePath = null;

    if(!empty($_FILES['attachment']['name'])){
        $dir = "../uploads/reports/";
        if(!is_dir($dir)) mkdir($dir,0777,true);

        $ext = pathinfo($_FILES['attachment']['name'],PATHINFO_EXTENSION);
        $filePath = $dir.time()."_".rand().".".$ext;

        move_uploaded_file($_FILES['attachment']['tmp_name'],$filePath);
    }

    if($title!='' && $description!=''){
        $stmt = $pdo->prepare("INSERT INTO farmer_reports
        (farmer_id,title,description,attachment,status)
        VALUES(?,?,?,?, 'pending')");

        $stmt->execute([$farmer_id,$title,$description,$filePath]);

        $success="Report submitted successfully";
    }else{
        $error="Fill all required fields";
    }
}

// FETCH REPORTS
$stmt=$pdo->prepare("SELECT * FROM farmer_reports WHERE farmer_id=? ORDER BY id DESC");
$stmt->execute([$farmer_id]);
$reports=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>My Reports</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
<style>
body{font-family:Poppins;background:#f2f6f2;margin:0}
.container{max-width:1000px;margin:auto;padding:20px}
.card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.08);margin-bottom:20px}
input,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;margin-top:8px}
button{background:#0b9348;color:#fff;border:none;padding:10px 15px;border-radius:8px;cursor:pointer}
button:hover{box-shadow:0 5px 10px rgba(0,0,0,.2)}
.status{padding:5px 10px;border-radius:6px;font-size:13px}
.pending{background:#fff3cd;color:#856404}
.processed{background:#d1ecf1;color:#0c5460}
.replied{background:#d4edda;color:#155724}
</style>
</head>
<body>
<div class="container">

<h2>Disease / Problem Reports</h2>

<div class="card">
<h3>Submit New Report</h3>

<?php if($error):?><p style="color:red"><?=$error?></p><?php endif;?>
<?php if($success):?><p style="color:green"><?=$success?></p><?php endif;?>

<form method="POST" enctype="multipart/form-data">

<input name="title" placeholder="Problem title" required>

<textarea name="description" rows="4" placeholder="Describe your problem" required></textarea>

<label>Upload Image / Video (optional)</label>
<input type="file" name="attachment" accept="image/*,video/*">

<br><br>
<button>Submit Report</button>

</form>
</div>

<div class="card">
<h3>My Submitted Reports</h3>

<?php if(!$reports):?>
<p>No reports yet</p>
<?php endif;?>

<?php foreach($reports as $r):?>

<div style="border-bottom:1px solid #eee;padding:10px 0">

<h4><?=$r['title']?></h4>

<span class="status <?=$r['status']?>">
<?=$r['status']?>
</span>

<p><?=$r['description']?></p>

<?php if($r['attachment']):?>
<a href="<?=$r['attachment']?>" target="_blank">View Attachment</a>
<?php endif;?>

<?php if($r['admin_reply']):?>
<div style="background:#f8f9fa;padding:10px;border-radius:6px;margin-top:8px">
<strong>Reply:</strong><br>
<?=$r['admin_reply']?>
</div>
<?php endif;?>

</div>

<?php endforeach;?>

</div>
</div>
</body>
</html>
