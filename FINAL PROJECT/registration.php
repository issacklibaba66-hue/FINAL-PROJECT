<?php
session_start();

/////////////////////////////////////////
// 1. DATABASE CONNECTION USING PDO
/////////////////////////////////////////
$host = "localhost";
$dbname = "agriculture";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/////////////////////////////////////////
// 2. PROCESS REGISTRATION
/////////////////////////////////////////
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first   = trim($_POST['first_name']);
    $middle  = trim($_POST['middle_name']);
    $last    = trim($_POST['last_name']);
    $phone   = trim($_POST['phone']);
    $email   = trim($_POST['email']);
    $pass    = trim($_POST['password']);
    $loc     = trim($_POST['location']);
    $soil    = trim($_POST['soil_type']);
    $size    = trim($_POST['farm_size']);

    // Validate empty fields
    if (empty($first) || empty($last) || empty($phone) || empty($email) || empty($pass)) {
        $error = "Please fill all required fields!";
    } else {

        // Check duplicate email or phone
        $chk = $pdo->prepare("SELECT id FROM farmers WHERE phone = :p OR email = :e LIMIT 1");
        $chk->execute(['p' => $phone, 'e' => $email]);

        if ($chk->rowCount() > 0) {
            $error = "Phone or Email already exists!";
        } else {

            /////////////////////////////////
            // 3. HANDLE PROFILE PICTURE
            /////////////////////////////////
            $picName = null;

            if (!empty($_FILES['profile_pic']['name'])) {

                $file = $_FILES['profile_pic'];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg', 'jpeg', 'png'];

                if (!in_array(strtolower($ext), $allowed)) {
                    $error = "Invalid image format. Use JPG, JPEG, or PNG.";
                } else {
                    $picName = "farmer_" . time() . "_" . rand(1000,9999) . "." . $ext;
                    $uploadPath = "uploads/farmers/" . $picName;

                    move_uploaded_file($file['tmp_name'], $uploadPath);
                }
            }

            if (!isset($error)) {

                //////////////////////////////////////
                // 4. INSERT FARMER INTO DATABASE
                //////////////////////////////////////
                $stmt = $pdo->prepare("
                    INSERT INTO farmers 
                    (first_name, middle_name, last_name, phone, email, password, location, soil_type, farm_size, profile_pic, status, created_at)
                    VALUES 
                    (:f, :m, :l, :p, :e, :pw, :loc, :soil, :size, :pic, 'pending', NOW())
                ");

                $stmt->execute([
                    'f'   => $first,
                    'm'   => $middle,
                    'l'   => $last,
                    'p'   => $phone,
                    'e'   => $email,
                    'pw'  => hash('sha256', $pass),
                    'loc' => $loc,
                    'soil'=> $soil,
                    'size'=> $size,
                    'pic' => $picName
                ]);

                $success = "Account created successfully! Please wait for admin approval.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Farmer Registration</title>

<!-- POPPINS FONT -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: #e8f5e9;
}

.container {
    width: 480px;
    margin: 40px auto;
    background: white;
    padding: 30px 40px;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0,0,0,0.15);
}

h2 {
    text-align: center;
    font-size: 26px;
    margin-bottom: 10px;
    color: #046b06;
}

label {
    font-weight: 500;
    display: block;
    margin-top: 12px;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 2px solid #cfd8dc;
    border-radius: 6px;
    margin-top: 5px;
    font-size: 15px;
    transition: 0.3s;
}

input:focus, select:focus {
    border-color: #028a07;
    box-shadow: 0 0 6px rgba(2,136,209,0.3);
}

.btn {
    width: 100%;
    padding: 12px;
    background: #028a07;
    color: white;
    border: none;
    border-radius: 6px;
    margin-top: 25px;
    cursor: pointer;
    font-size: 17px;
    transition: 0.3s;
}

.btn:hover {
    background: #046b06;
}

.error {
    color: red;
    text-align: center;
    margin-top: 15px;
}

.success {
    color: green;
    text-align: center;
    margin-top: 15px;
}
</style>

</head>
<body>

<div class="container">

<h2>Farmer Registration</h2>

<form method="POST" enctype="multipart/form-data">

    <label>First Name *</label>
    <input type="text" name="first_name" required>

    <label>Middle Name</label>
    <input type="text" name="middle_name">

    <label>Last Name *</label>
    <input type="text" name="last_name" required>

    <label>Phone *</label>
    <input type="text" name="phone" required>

    <label>Email *</label>
    <input type="email" name="email" required>

    <label>Password *</label>
    <input type="password" name="password" required>

    <label>Farm Location</label>
    <input type="text" name="location">

    <label>Soil Type</label>
    <input type="text" name="soil_type">

    <label>Farm Size (in acres)</label>
    <input type="text" name="farm_size">

    <label>Profile Picture</label>
    <input type="file" name="profile_pic" accept="image/*">

    <button class="btn" type="submit">Create Account</button>

    <?php if(isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <?php if(isset($success)): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>

</form>

</div>

</body>
</html>
