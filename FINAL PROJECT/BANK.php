<?php
session_start();

$host = 'localhost';
$db   = 'agriculture';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);

$farmer_id = $_SESSION['farmer_id'];

$query = "SELECT f.*, b.bank_name, b.account_number, b.card_holder_name, b.expiry_date, b.cvv 
          FROM farmers f 
          JOIN bank_details b ON f.id = b.farmer_id 
          WHERE f.id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$farmer_id]);
$data = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<title>Farmer Dashboard</title>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    background:linear-gradient(135deg,#e8f5e9,#f1f8ff);
    min-height:100vh;
}

/* ===== Dashboard Card ===== */
.dashboard{
    max-width:900px;
    margin:50px auto;
    background:#fff;
    padding:30px;
    border-radius:20px;
    box-shadow:0 20px 40px rgba(0,0,0,0.1);
    animation:fadeIn 0.8s ease;
}

@keyframes fadeIn{
    from{opacity:0; transform:translateY(20px);}
    to{opacity:1; transform:translateY(0);}
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.logo{
    display:flex;
    align-items:center;
    gap:10px;
    font-size:22px;
    font-weight:600;
    color:#1b5e20;
}

.logo i{
    animation:spin 4s linear infinite;
}

@keyframes spin{
    from{transform:rotate(0);}
    to{transform:rotate(360deg);}
}

.profile-info{
    margin-top:25px;
}

.profile-info h2{
    color:#333;
}

.profile-info p{
    margin-top:8px;
    color:#555;
}

.actions{
    margin-top:20px;
    display:flex;
    gap:15px;
}

.btn{
    padding:12px 22px;
    border:none;
    border-radius:30px;
    cursor:pointer;
    font-weight:500;
    transition:0.3s;
}

.btn-view{
    background:#1b5e20;
    color:#fff;
}

.btn-view:hover{
    transform:translateY(-3px);
    box-shadow:0 10px 25px rgba(27,94,32,0.4);
}

.btn-register{
    background:#004a99;
    color:#fff;
}

.btn-register:hover{
    transform:translateY(-3px);
    box-shadow:0 10px 25px rgba(0,74,153,0.4);
}

/* ===== Bank Card ===== */
.card-container{
    perspective:1000px;
    width:360px;
    height:210px;
    margin:40px auto;
    display:none;
}

.bank-card{
    width:100%;
    height:100%;
    position:relative;
    transform-style:preserve-3d;
    transition:transform 0.9s;
    cursor:pointer;
}

.bank-card.flipped{
    transform:rotateY(180deg);
}

.front,.back{
    position:absolute;
    width:100%;
    height:100%;
    backface-visibility:hidden;
    border-radius:18px;
    padding:25px;
    color:#fff;
    box-shadow:0 15px 35px rgba(0,0,0,0.4);
}

.front{
    background:linear-gradient(135deg,#1b5e20,#43a047);
}

.back{
    transform:rotateY(180deg);
    background:#222;
}

/* ===== Modal ===== */
.modal{
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.4);
    backdrop-filter:blur(6px);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:999;
}

.modal-content{
    background:#fff;
    width:400px;
    padding:30px;
    border-radius:20px;
    box-shadow:0 20px 40px rgba(0,0,0,0.3);
    animation:zoom 0.4s ease;
}

@keyframes zoom{
    from{transform:scale(0.8); opacity:0;}
    to{transform:scale(1); opacity:1;}
}

.modal-content h3{
    text-align:center;
    margin-bottom:20px;
}

.modal-content input{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border-radius:10px;
    border:1px solid #ccc;
}

.close{
    text-align:right;
    cursor:pointer;
    color:#c62828;
    font-weight:600;
}
</style>
</head>

<body>

<div class="dashboard">
    <div class="header">
        <div class="logo">
            <i class="fas fa-leaf"></i> Smart Agriculture
        </div>
    </div>

    <div class="profile-info">
        <h2>Mkulima: <?= $data['first_name']." ".$data['last_name']; ?></h2>
        <p><i class="fas fa-phone"></i> <?= $data['phone']; ?></p>
    </div>

    <div class="actions">
        <button class="btn btn-view" onclick="toggleCard()">
            <i class="fas fa-eye"></i> Ona Kadi ya Benki
        </button>
        <button class="btn btn-register" onclick="openModal()">
            <i class="fas fa-user-plus"></i> Register New
        </button>
    </div>

    <!-- Bank Card -->
    <div class="card-container" id="cardContainer">
        <div class="bank-card" id="bankCard" ondblclick="flipCard()">
            <div class="front">
                <h3><?= $data['bank_name']; ?> BANK</h3>
                <p style="margin-top:40px; font-size:20px; letter-spacing:2px;">
                    **** **** **** <?= substr($data['account_number'],-4); ?>
                </p>
                <small>HOLDER: <?= strtoupper($data['card_holder_name']); ?></small>
            </div>
            <div class="back">
                <div style="background:#000;height:40px;width:100%;"></div>
                <p style="margin-top:20px;">CVV: <?= $data['cvv']; ?></p>
                <small>Valid Thru: <?= $data['expiry_date']; ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal" id="registerModal">
    <div class="modal-content">
        <div class="close" onclick="closeModal()">✖</div>
        <h3>Register New User</h3>
        <input type="text" placeholder="First Name">
        <input type="text" placeholder="Last Name">
        <input type="email" placeholder="Email">
        <button class="btn btn-register" style="width:100%;">Submit</button>
    </div>
</div>

<script>
function toggleCard(){
    const c = document.getElementById("cardContainer");
    c.style.display = c.style.display === "none" ? "block" : "none";
}

function flipCard(){
    document.getElementById("bankCard").classList.toggle("flipped");
}

function openModal(){
    document.getElementById("registerModal").style.display="flex";
}

function closeModal(){
    document.getElementById("registerModal").style.display="none";
}
</script>

</body>
</html>
