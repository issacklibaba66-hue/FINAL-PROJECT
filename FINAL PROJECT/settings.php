
<?php
session_start();
$DB_HOST = 'localhost';
$DB_NAME = 'agriculture'; 
$DB_USER = 'root';
$DB_PASS = '';
// require farmer logged in
if (!isset($_SESSION['farmer_id'])) {
    header("Location:login_farmer.php"); // adjust path to your login
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

$stmt = $pdo->prepare("SELECT * FROM farmers WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$msg=""; $err="";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update names
    if (isset($_POST['update_names'])) {
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        if ($first && $last) {
            $u = $pdo->prepare("UPDATE farmers SET first_name=?, middle_name=?, last_name=? WHERE id=?");
            $u->execute([$first, $middle, $last, $_SESSION['farmer_id']]);
            $msg = "NAMES UPDATED SUCCESSFULLY.";
        } else {
            $err = "INSERT ATLEAST FIRST OR LAST NAME.";
        }
    }

    // Upload profile pic
    if (isset($_POST['upload_pic']) && isset($_FILES['profile_pic'])) {
        $file = $_FILES['profile_pic'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                $target = "uploads/farmers_" . $_SESSION['farmer_id'] . "_" . time() . "." . $ext;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $u = $pdo->prepare("UPDATE farmers SET profile_pic=? WHERE id=?");
                    $u->execute([$target, $_SESSION['farmer_id']]);
                    $msg = "PROFILE PICTURE UPLOADED SUCCESSFULLY.";
                } else {
                    $err = "FAILED!! TO UPLOAD.";
                }
            } else {
                $err = "ONLY: jpg, jpeg, png, gif.";
            }
        } else {
            $err = "FILE ERROR.";
        }
    }
    // Refresh admin data
    $stmt->execute([$_SESSION['farmer_id']]);
    $farmer = $stmt->fetch();
}
// resert password
if(isset($_POST['reset'])){
    $email = $_POST['email'];
    $newpass = password_hash($_POST['newpass'], PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password='$newpass' WHERE email='$email'";
         if ($email && $newpass) {
            $u = $pdo->prepare("UPDATE farmers SET password='$newpass' WHERE email='$email'");
            $u->execute([ $newpass]);
            $msg = "NAMES UPDATED SUCCESSFULLY.";
        } else {
            $err = "INSERT ATLEAST FIRST OR LAST NAME.";
        }
}
include __DIR__ . "/header.php";
?>
<h1>SETTINGS</h1>

<div class="settings-grid">
  <div class="card">
    <h3>Profile</h3>
    <div class="profile-box">
      <img class="profile-big" src="<?php echo htmlspecialchars($admin['profile_pic']); ?>" alt="profile">
      <div class="names">
        <div><?php echo htmlspecialchars($farmer['first_name']); ?></div>
        <div><?php echo htmlspecialchars($farmer['middle_name']); ?></div>
        <div><?php echo htmlspecialchars($farmer['last_name']); ?></div>
      </div>
    </div>

    <?php if ($msg): ?><div class="success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      
      <input type="file" name="profile_pic" accept="image/*"class="form-group" required>
      <br>
      <button type="submit"class="button" name="upload_pic">UPLOAD</button>
    </form>
<br><br>
    <form method="post" class="form-group">
      <input type="text" name="first_name" value="<?php echo htmlspecialchars($farmer['first_name']); ?>" placeholder="First name" required>
      <input type="text" name="middle_name" value="<?php echo htmlspecialchars($farmer['middle_name']); ?>" placeholder="Middle name">
      <input type="text" name="last_name" value="<?php echo htmlspecialchars($farmer['last_name']); ?>" placeholder="Last name" required>
      <br><br>
      <button type="submit"class="button" name="update_names">UPDATE</button>
    </form>
  </div>
<br><br>
  <div class="card">
   <form method="POST"class="form-group">
    <h3>RESERT PASSWORD</h3>
    <input type="email" name="email" placeholder="User Email"class="form-group input" required><br>
    <input type="password" name="newpass" placeholder="New Password" required><br>
    <button name="reset">Reset Password</button>
</form>
</div>

<?php include __DIR__ . "/footer.php"; ?>
