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
// ---------------- Fetch Data ----------------
// 1. Farmer info
$stmt = $pdo->prepare("SELECT * FROM farmers WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

function fullname($r){ return trim(($r['first_name']??''). ' ' . ($r['middle_name']??'') . ' ' . ($r['last_name']??''));
 }
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akaunti Yangu - ECO-STORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* CSS kwa ajili ya muundo wa Ukurasa wa Akaunti */
        :root {
            --primary-color: #007bff; /* Rangi ya Msingi */
            --text-color: #333;
            --bg-light: #f8f9fa;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color:lightgrey;
            margin: 0;
            padding-bottom: 70px; 
            color: var(--text-color);
        }
        .header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header h2 {
            margin: 0;
            font-size: 1.2em;
            font-weight: 600;
        }
        .profile-header {
            background-color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 10px;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            margin-bottom: 10px;
        }

        .profile-header h3 {
            margin: 5px 0 0 0;
            font-size: 1.2em;
            font-weight: 600;
        }

        .profile-header p {
            margin: 0;
            font-size: 0.9em;
            color: #6c757d;
        }

        .loyalty-info {
            background-color: #f1f8ff;
            color: var(--primary-color);
            padding: 8px;
            border-radius: 8px;
            margin-top: 15px;
            font-weight: 600;
            display: inline-block;
            font-size: 0.9em;
        }
        .account-menu {
            padding: 15px;
        }
        .menu-group {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .menu-item:last-child {
            border-bottom: none;
        }

        .menu-item:hover {
            background-color: var(--bg-light);
        }

        .menu-item i {
            font-size: 1.3em;
            width: 30px;
            text-align: center;
            color: var(--primary-color);
        }

        .menu-item .text-content {
            flex-grow: 1;
            margin-left: 15px;
        }

        .menu-item h4 {
            margin: 0;
            font-size: 1em;
            font-weight: 600;
        }

        .menu-item p {
            margin: 0;
            font-size: 0.8em;
            color: #6c757d;
        }

        .menu-item .arrow {
            color: #ccc;
        }
        
        /* Kitufe cha Logout (Toka) - Rangi Tofauti */
        .logout-item {
            color: #dc3545;
        }

        .logout-item i {
            color: #dc3545;
        }

        .logout-item .arrow {
            display: none;
        }

        /* ------------------ SEHEMU YA CHINI (NAVIGATION BAR) - Kwa Rejea ------------------ */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            background-color: white;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        }

        .nav-item {
            text-align: center;
            color: #6c757d;
            font-size: 0.8em;
        }

        .nav-item i {
            font-size: 1.5em;
            margin-bottom: 3px;
        }

        .nav-item.active {
            color: var(--primary-color);
            font-weight: 600;
        }
          .card {
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 50%;
            margin-left: 20%;
            margin-top: 10px;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .profile-mini { 
  display:flex;
   align-items:center; 
   gap:8px;
   }
   .profile-mini img {
   width:100px;
    height:100px;
     border-radius:50%;
      object-fit:cover;
       border:1px solid var(--border);
       }
    </style>
</head>
<body>

    <div class="header">
        <h2>My Account</h2>
    </div>
    <div class="card">
        <div class="profile-mini">
         <div class="profile-mini img"> <img src="<?php echo htmlspecialchars($farmer['profile_pic']); ?>" alt="pic"></div>
        </div>
        <h3> NAMES: <?= htmlspecialchars(fullname($farmer) ?: 'Farmer') ?></h3>
        <h3>EMAIL: <?= htmlspecialchars($farmer['email'] ?? 'Email Is Empty') ?></h3>
        <h3>PHONE: <?= htmlspecialchars($farmer['phone'] ?? 'Phohe Number Is Empty') ?></h3>
       <h3>FROM: <?= htmlspecialchars($farmer['location'] ?? 'Location not set') ?></h3>
        <div class="loyalty-info">
            <i class="fas fa-trophy">settings</i> 
        </div>
    
                <i class="fas fa-sign-out-alt"></i>
                <div class="text-content">
                    <h4> (Logout)</h4>
                    <div style="width:46px;height:46px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow)">
      <a href="../logout.php" title="Logout"><i class="fa fa-sign-out-alt" style="color:red"></i></a>
    </div>
                </div>
                </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuItems = document.querySelectorAll('.menu-item');
            
            menuItems.forEach(item => {
                item.addEventListener('click', () => {
                    const title = item.querySelector('h4').textContent;
                    alert(`Umebofya: ${title}. Ukurasa wa ${title} ungefunguliwa hapa.`);
                    
                    // Kazi halisi ya uhamishaji wa ukurasa (page redirection) ingeongezwa hapa.
                });
            });
        });
    </script>
</body>
</html>
