<?php
session_start();

///////////////////////////////
// 1. DATABASE CONNECTION PDO
///////////////////////////////
$host = "localhost";
$dbname = "agriculture"; // badilisha kama DB yako ina jina tofauti
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

///////////////////////////////
// 2. FARMER LOGIN PROCESS
///////////////////////////////
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    // Login via phone OR email
    $stmt = $pdo->prepare("SELECT * FROM farmers WHERE (phone = :u OR email = :u) LIMIT 1");
    $stmt->execute(['u' => $user]);
    $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($farmer && hash('sha256', $pass) === $farmer['password']) {

        // Check if approved
        if ($farmer['status'] !== 'approved') {
            $error = "Your account is not yet approved!";
        } else {
            // Create session
            $_SESSION['farmer_id'] = $farmer['id'];
            $_SESSION['farmer_name'] = $farmer['first_name'];

            header("Location: farmer_dashboard.php"); // redirect to farmer dashboard
            exit;
        }
    } else {
        $error = "Invalid phone/email or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Farmer Login</title>

<!-- POPPINS FONT -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>

body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: #e8f5e9;
}

.container {
    display: flex;
    height: 100vh;
}

/* LEFT SLIDESHOW */
.left-side {
    width: 50%;
    position: relative;
    overflow: hidden;
}

.slide {
    display: none;
}

.left-side img {
    width: 100%;
    height: 100vh;
    object-fit: cover;
}

/* RIGHT LOGIN SIDE */
.right-side {
    width: 50%;
    background: white;
    padding: 60px;
    box-shadow: -5px 0 25px rgba(0,0,0,0.12);
}

.right-side h2 {
    font-size: 30px;
    margin-bottom: 5px;
    font-weight: 600;
    color: #046b06;
}

.right-side p {
    margin-top: 0;
    color: #555;
    font-size: 14px;
}

label {
    font-weight: 500;
    margin-top: 15px;
    display: block;
}

input {
    width: 100%;
    padding: 12px;
    border: 2px solid #cfd8dc;
    border-radius: 6px;
    margin-top: 5px;
    font-size: 15px;
    transition: 0.3s;
}

input:focus {
    border-color: #028a07;
    box-shadow: 0 0 8px rgba(2,136,209,0.3);
    outline: none;
}

.btn {
    width: 100%;
    padding: 12px;
    background: #028a07;
    color: white;
    border: none;
    border-radius: 6px;
    margin-top: 25px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}

.btn:hover {
    background: #046b06;
    box-shadow: 0 6px 12px rgba(0,0,0,0.2);
}

.error {
    color: red;
    margin-top: 15px;
    font-weight: 500;
}

</style>

</head>
<body>

<div class="container">

    <!-- LEFT SIDE SLIDESHOW -->
    <div class="left-side">
        <div class="slide"><img src="images/image 1.jpg"></div>
        <div class="slide"><img src="images/image 2.jpg"></div>
        <div class="slide"><img src="images/image 3.png"></div>
    </div>

    <!-- RIGHT SIDE LOGIN -->
    <div class="right-side">

        <h2>Farmer Login</h2>
        <p>Please enter your phone/email and password to continue.</p>

        <form method="POST">

            <label>Phone or Email</label>
            <input type="text" name="username" placeholder="e.g. 0712xxxxxx or email@example.com" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button class="btn">Sign In</button>

            <?php if(isset($error)): ?>
                <p class="error"><?= $error ?></p>
            <?php endif; ?>

        </form>

    </div>

</div>

<!-- SLIDESHOW SCRIPT -->
<script>
let slideIndex = 0;
showSlides();

function showSlides() {
    let slides = document.getElementsByClassName("slide");

    for (let i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";
    }

    slideIndex++;
    if (slideIndex > slides.length) { slideIndex = 1; }

    slides[slideIndex - 1].style.display = "block";

    setTimeout(showSlides, 3500);
}
</script>

</body>
</html>
