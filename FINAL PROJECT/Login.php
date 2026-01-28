<?php
session_start();

///////////////////////////////
// 1. DATABASE CONNECTION PDO
///////////////////////////////
$host = "localhost";
$dbname = "agriculture";
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
// 2. LOGIN PROCESS
///////////////////////////////
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
    $stmt->execute(['u' => $user]);
    $admin = $stmt->fetch();

    if ($admin && hash('sha256', $pass) === $admin['password']) {
        $_SESSION['admin'] = $admin['username'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login</title>

<!-- POPPINS FONT -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>

body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: #f1f1f1;
}

.container {
    display: flex;
    height: 100vh;
}

/* LEFT SLIDESHOW SIDE */
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
    box-shadow: -5px 0 20px rgba(0,0,0,0.1);
}

.right-side h2 {
    font-size: 28px;
    margin-bottom: 8px;
    font-weight: 600;
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
    border: 2px solid #ccc;
    border-radius: 6px;
    margin-top: 5px;
    font-size: 15px;
    transition: 0.3s;
}

input:focus {
    border-color: #0288d1;
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
        <div class="slide"><img src="2.jpg"></div>
        <div class="slide"><img src=""></div>
        <div class="slide"><img src="images/3.jpg"></div>
    </div>

    <!-- RIGHT SIDE LOGIN -->
    <div class="right-side">

        <h2>Sign In</h2>
        <p>Please enter your valid credentials to continue.</p>

        <form method="POST">

            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button class="bt
