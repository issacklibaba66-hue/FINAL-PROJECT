<?php
session_start();
$collector_id = (int)$_SESSION['collector_id'];

$pdo = new PDO(
    "mysql:host=localhost;dbname=agriculture;charset=utf8mb4",
    "root","",
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);

/* ================= AJAX HANDLERS ================= */
if(isset($_GET['action'])){

/* 🔍 SEARCH FARMERS */
if($_GET['action']=='search_farmers'){
    $q="%".$_GET['q']."%";
    $stmt=$pdo->prepare("SELECT id,first_name,middle_name,last_name,phone,email,location
        FROM farmers
        WHERE first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR phone LIKE ?
        LIMIT 10
    ");
    $stmt->execute([$q,$q,$q,$q]);
    while($f=$stmt->fetch()){
        $fullname= htmlspecialchars($f['first_name'] . '' . $f['middle_name'] . '' . $f['last_name']);
        echo "<div class='farmer-item' 
            data-id='{$f['id']}'
            data-name='{$fullname}'
            data-phone='{$f['phone']}'
            data-email='{$f['email']}'
            data-location='{$f['location']}'>
        {$fullname} - {$f['phone']}
        </div>";
    }
    exit;
}

/* 🏦 FETCH BANK */
if($_GET['action']=='get_bank'){
    $stmt=$pdo->prepare("SELECT bank_name,account_name,account_number FROM bank_details WHERE farmer_id=?
    ");
    $stmt->execute([$_GET['farmer_id']]);
    echo json_encode($stmt->fetch());
    exit;
}

/* 📥 SUBMIT PURCHASE */
if($_GET['action']=='submit'){
    $farmer_id=$_POST['farmer_id'];
    $weight=$_POST['weight'];

    $batch="BATCH-".date("YmdHis").rand(100,999);

    $pdo->prepare("INSERT INTO purchases (collector_id,farmer_id,weight,batch_code)VALUES (?,?,?,?)
    ")->execute([$collector_id,$farmer_id,$weight,$batch]);

    $purchase_id=$pdo->lastInsertId();

    // QR + URL
    $url="http://localhost/agriculture/batch_view.php?batch=".$batch;
    $qr_path="qrcodes/".$batch.".png";

    if(!is_dir("qrcodes")) mkdir("qrcodes");

    include "phpqrcode/qrlib.php";
    QRcode::png($url,$qr_path);

    $pdo->prepare("
        INSERT INTO batches (batch_code,farmer_id,purchase_id,qr_code_path,batch_url)
        VALUES (?,?,?,?,?)
    ")->execute([$batch,$farmer_id,$purchase_id,$qr_path,$url]);

    $pdo->prepare("
        INSERT INTO notifications (farmer_id,message)
        VALUES (?,?)
    ")->execute([$farmer_id,"Mazao yako yamepokelewa. Batch: $batch"]);

    echo json_encode(["batch"=>$batch]);
    exit;
}
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Collector Purchase</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
<style>
body{font-family:Poppins;background:#eef2f7}
.card{
    width:500px;margin:40px auto;background:#fff;
    padding:25px;border-radius:15px;
    box-shadow:0 20px 40px rgba(0,0,0,.15)
}
input{width:100%;padding:10px;margin-bottom:10px}
button{padding:12px;width:100%;background:#1b5e20;color:#fff;border:none}
#results div{padding:8px;cursor:pointer}
#results div:hover{background:#f1f1f1}
</style>
</head>
<body>

<div class="card">
<h3>🌾 Purchase Produce</h3>

<input id="search" placeholder="Search farmer">
<div id="results"></div>

<input id="farmer_name" readonly>
<input id="phone" readonly>
<input id="email" readonly>
<input id="location" readonly>

<input id="bank_name" readonly>
<input id="account_name" readonly>
<input id="account_number" readonly>

<input type="number" id="weight" placeholder="Weight (kg)">
<input type="hidden" id="farmer_id">

<button onclick="submitPurchase()">Submit Purchase</button>
</div>

<script>
search.onkeyup=function(){
 if(this.value.length<2) return;
 fetch("?action=search_farmers&q="+this.value)
 .then(r=>r.text()).then(d=>results.innerHTML=d);
}
document.addEventListener('click', function(e){
    if(e.target.classList.contains('farmer-item'))
    {
        let el = e.target;
        selectFarmer(
            el.dataset.id,
            el.dataset.name,
            el.dataset.phone,
            el.dataset.email,
            el.dataset.location
        );
    }
});

 fetch("?action=get_bank&farmer_id="+id)
 .then(r=>r.json()).then(b=>{
   bank_name.value=b.bank_name;
   account_name.value=b.account_name;
   account_number.value="****"+b.account_number.slice(-4);
 });
}

function submitPurchase(){
 let f=new FormData();
 f.append("farmer_id",farmer_id.value);
 f.append("weight",weight.value);

 fetch("?action=submit",{method:"POST",body:f})
 .then(r=>r.json())
 .then(res=>{
   window.location="receipt_pdf.php?batch="+res.batch;
 });
}
</script>
</body>
</html>
