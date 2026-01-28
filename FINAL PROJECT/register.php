<?php
$DB_HOST = "localhost";
$DB_NAME = "agriculture";
$DB_USER = "root";
$DB_PASS = "";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database error");
}

$error = $success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $role   = $_POST['role'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $pass   = $_POST['password'] ?? '';

    if ($role === '' || $name === '' || $email === ''|| $location === '' || $pass === '') {
        $error = "Please fill all required fields";
    } else {

        // check email exists
        $chk = $pdo->prepare("SELECT id FROM users_login WHERE email = :e");
        $chk->execute([':e' => $email]);

        if ($chk->fetch()) {
            $error = "Email already registered";
        } else {

            try {
                $pdo->beginTransaction();

                // 1️⃣ INSERT INTO PROFILE TABLE
                switch ($role) {

                    case 'farmer':
                        $stmt = $pdo->prepare(
                            "INSERT INTO farmers (first_name, email, phone,location)
                             VALUES (:n, :e, :p, :l)"
                        );
                        break;

                    case 'collector':
                        $stmt = $pdo->prepare(
                            "INSERT INTO collectors (company_name, email, phone, location)
                             VALUES (:n, :e, :p, :l)"
                        );
                        break;

                    case 'processor':
                        $stmt = $pdo->prepare(
                            "INSERT INTO processors (company_name, email, phone,location)
                             VALUES (:n, :e, :p, :l)"
                        );
                        break;

                    case 'consumer':
                        $stmt = $pdo->prepare(
                            "INSERT INTO consumers (full_name, email, phone,location)
                             VALUES (:n, :e, :p, :l)"
                        );
                        break;

                        case 'admin':
                            $stmt = $pdo->prepare(
                                "INSERT INTO admins (username, email, phone,location)
                                 VALUES (:n, :e, :p, :l)"
                            );
                            break;
                    default:
                        throw new Exception("Invalid role selected");
                }

                $stmt->execute([
                    ':n' => $name,
                    ':e' => $email,
                    ':p' => $phone,
                    ':l' => $location
                ]);

                $ref_id = $pdo->lastInsertId(); // ⭐ PROFILE ID

                // 2️⃣ INSERT INTO users_login
                $stmt2 = $pdo->prepare(
                    "INSERT INTO users_login (email, password, role, ref_id)
                     VALUES (:e, :pw, :r, :rid)"
                );
                $stmt2->execute([
                    ':e'   => $email,
                    ':pw'  => password_hash($pass, PASSWORD_DEFAULT),
                    ':r'   => $role,
                    ':rid' => $ref_id
                ]);

                $pdo->commit();
                $success = ucfirst($role) . " registered successfully. You can login now.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Unified Registration</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body{
  margin:0;
  font-family:'Poppins',sans-serif;
  background:linear-gradient(135deg,#0b9348,#1e88e5);
}
.card{
  max-width:420px;
  margin:50px auto;
  background:#fff;
  padding:25px;
  border-radius:14px;
  box-shadow:0 15px 30px rgba(0,0,0,.08);
}
h2{text-align:center;margin-bottom:10px}
input,select{
  width:100%;
  padding:12px;
  margin-top:10px;
  border-radius:8px;
  border:1px solid #ccc;
}
button{
  width:100%;
  margin-top:20px;
  padding:12px;
  border:none;
  border-radius:8px;
  background:#0b9348;
  color:#fff;
  font-size:16px;
  cursor:pointer;
}
button:hover{box-shadow:0 6px 12px rgba(0,0,0,.2)}
.msg{margin-top:15px;font-weight:500}
.error{color:#d32f2f}
.success{color:#0b9348}
</style>
</head>
<body>

<div class="card">
  <h2>CREATE ACCOUNT</h2>

  <form method="POST">

    <select name="role" required>
      <option value="">...SELECT ROLE...</option>
      <option value="admin">ADMIN</option>
      <option value="farmer">FARMER</option>
      <option value="collector">COLLECTOR</option>
      <option value="processor">PROCESSOR</option>
      <option value="consumer">CONSUMER</option>
    </select>

    <input name="name" placeholder="NAME" required>
    <input name="email" type="email" placeholder="EMAIL" required>
    <input name="phone" placeholder="PHONE">
    <input name="location" placeholder="LOCATION">
    <input name="password" type="password" placeholder="PASSWORD" required>

    <button type="submit">Register</button>

    <?php if($error): ?><div class="msg error"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="msg success"><?= $success ?></div><?php endif; ?>

  </form>
</div>

</body>
</html>
