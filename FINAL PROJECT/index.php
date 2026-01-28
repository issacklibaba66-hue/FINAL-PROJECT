<?php
session_start();

$pdo = new PDO(
    "mysql:host=localhost;dbname=agriculture;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$email = $_POST['email'];
$password = $_POST['password'];

$stmt = $pdo->prepare("SELECT * FROM users_login WHERE email = :e AND status='active' LIMIT 1");
$stmt->execute([':e' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['ref_id']  = (int)$user['ref_id']; // ⭐ KEY


        // Update last login
        $pdo->prepare("UPDATE users_login SET last_login=NOW() WHERE id=:id")
            ->execute(['id'=>$user['id']]);

        // ROLE-BASED REDIRECT
        switch ($user['role']) {
            case 'farmer':
                $_SESSION['farmer_id'] = $_SESSION['ref_id'];
                header("Location: farmer_dashboard.php");
                break;
    
            case 'collector':
                $_SESSION['collector_id'] = $_SESSION['ref_id'];
                header("Location: COLLECTOR.php");
                break;
    
            case 'processor':
                $_SESSION['processor_id'] = $_SESSION['ref_id'];
                header("Location: processor.php");
                break;
    
            case 'consumer':
                $_SESSION['consumer_id'] = $_SESSION['ref_id'];
                header("Location: consumer.php");
                break;
    
            case 'admin':
                $_SESSION['admin_id'] = $_SESSION['ref_id'];
                header("Location: dashbord.php");
                break;
        }
        exit;
    }
    
    $error = "Invalid email or password";
    }

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title> Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,#0b9348,#1e88e5);
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}
.card{
    background:#fff;
    padding:30px;
    border-radius:16px;
    width:100%;
    max-width:380px;
    box-shadow:0 20px 40px rgba(0,0,0,.2);
}
h2{text-align:center;margin-bottom:20px}
input{
    width:100%;
    padding:12px;
    border-radius:10px;
    border:1px solid #ddd;
    margin-bottom:12px;
}
button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    background:#0b9348;
    color:#fff;
    font-weight:500;
    cursor:pointer;
}
.error{
    background:#fdecea;
    color:#c62828;
    padding:10px;
    border-radius:10px;
    margin-bottom:10px;
    text-align:center;
}
</style>
</head>

<body>
<div class="card">
    <h2>LOGIN</h2>

    <?php if($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email address" required>
        <input type="password" name="password" placeholder="Password" required>
        <button>Login</button>
    </form>
</div>
</body>
</html>






