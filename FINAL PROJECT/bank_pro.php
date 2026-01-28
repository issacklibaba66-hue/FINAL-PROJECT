<?php
session_start();

/* ====== SECURITY ====== */
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    die("Access denied");
}

/* ====== DB CONNECTION ====== */
$pdo = new PDO(
    "mysql:host=localhost;dbname=agriculture;charset=utf8mb4",
    "root","",
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);

/* ====== AJAX ACTIONS ====== */
if(isset($_GET['action'])){

    /* SEARCH FARMERS */
if($_GET['action']=='search_farmers'){
    $q = "%".$_GET['q']."%";
    $stmt = $pdo->prepare("
        SELECT id,first_name,last_name,phone
        FROM farmers
        WHERE first_name LIKE ?
        OR last_name LIKE ?
        OR phone LIKE ?
        LIMIT 10
    ");
    $stmt->execute([$q,$q,$q]);

    while($f=$stmt->fetch()){
        echo "<div 
            style='padding:8px;cursor:pointer'
            onclick=\"selectFarmer('{$f['id']}','{$f['first_name']} {$f['last_name']} ({$f['phone']})')\">
            {$f['first_name']} {$f['last_name']} - {$f['phone']}
        </div>";
    }
    exit;
}

    /* FETCH DATA */
    if($_GET['action']=='fetch'){
        $q = "%".($_GET['q'] ?? '')."%";
        $stmt = $pdo->prepare("
            SELECT b.*,f.first_name,f.last_name
            FROM bank_details b
            JOIN farmers f ON b.farmer_id=f.id
            WHERE f.first_name LIKE ?
            OR f.last_name LIKE ?
            OR b.bank_name LIKE ?
            OR b.account_number LIKE ?
            ORDER BY b.id DESC
        ");
        $stmt->execute([$q,$q,$q,$q]);

        while($r=$stmt->fetch()){
            echo "<tr>
            <td>{$r['first_name']} {$r['last_name']}</td>
            <td>{$r['bank_name']}</td>
            <td>{$r['account_name']}</td>
            <td>****".substr($r['account_number'],-4)."</td>
            <td>{$r['expiry_date']}</td>
            <td class='{$r['status']}'>{$r['status']}</td>
            <td>
              <button onclick=\"toggleStatus({$r['id']},'{$r['status']}')\">Toggle</button>
              <button  onclick=\"delBank({$r['id']})\">Delete</button>
            </td>
            </tr>";
        }
        exit;
    }

    /* ADD */
    if($_GET['action']=='add'){
        $pdo->prepare("
            INSERT INTO bank_details
            (farmer_id,bank_name,account_name,account_number,expiry_date,status)
            VALUES (?,?,?,?,?,'active')
        ")->execute([
            $_POST['farmer_id'],
            $_POST['bank_name'],
            $_POST['account_name'],
            $_POST['account_number'],
            $_POST['expiry_date']
        ]);
        exit;
    }

    /* DELETE */
    if($_GET['action']=='delete'){
        $pdo->prepare("DELETE FROM bank_details WHERE id=?")
            ->execute([$_GET['id']]);
        exit;
    }

    /* TOGGLE STATUS */
    if($_GET['action']=='toggle'){
        $new = $_GET['status']=='active'?'inactive':'active';
        $pdo->prepare("UPDATE bank_details SET status=? WHERE id=?")
            ->execute([$new,$_GET['id']]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<title>Admin Bank Management</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">

<style>
*{font-family:Poppins;margin:0;padding:0;box-sizing:border-box}
body{
    background:linear-gradient(135deg,#0b9348,#1e88e5);
    }
.container{
    width:95%;
    margin:30px auto;
    background:#fff;
    padding:25px;
    border-radius:15px;
    box-shadow:0 15px 40px rgba(0,0,0,.1);
}
h2{
    margin-bottom:15px;
    text-align:center;
    }
input{
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
    width:88%;
}
button{
    cursor:pointer;
    background:#004a99;
    color:#fff;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
}
.delete{
    cursor:pointer;
    background:red;
    color:#fff;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
}
button:hover{
    opacity:.85;
    }
table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}
th,td{
    padding:12px;
    border-bottom:1px solid #eee;
}
th{
    background:lightblue;
    }
.active{color:green;font-weight:600}
.inactive{color:red;font-weight:600}

/* MODAL */
.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.5);
    backdrop-filter:blur(5px);
}
.modal-box{
    background:#fff;
    width:400px;
    margin:80px auto;
    padding:20px;
    border-radius:12px;
}

@media print{
    button,input{display:none}
    body{background:#fff}
}
h3{
    text-align:center;
}
</style>
</head>

<body>

<div class="container">
<h2>BANK INFORMATION</h2>

<input type="text" id="search" placeholder="search...">
<button onclick="openModal()">REGISTER</button>
<button onclick="window.print()">PRINT</button>

<table>
<thead>
<tr>
<th>FARMER</th>
<th>BANK</th>
<th>ACCOUNT NAME</th>
<th>ACCOUNT NUMBER</th>
<th>EXPRIY DATE</th>
<th>STATUS</th>
<th>ACTIONS</th>
</tr>
</thead>
<tbody id="tbody"></tbody>
</table>
</div>

<!-- MODAL -->
<div class="modal" id="modal">
<div class="modal-box">
<h3>ADD BANK DETAILS</h3>
<input type="text" id="farmer_search" placeholder="Search farmer name or phone" autocomplete="off">
<input type="hidden" id="farmer_id">

<div id="farmerResults" style="background:#fff;border:1px solid #ccc;border-radius:8px;
max-height:150px;
overflow-y:auto;
display:none;
">
</div>

<br><br>
<input id="bank_name" placeholder="Bank Name"><br><br>
<input id="account_name" placeholder="Account Name"><br><br>
<input id="account_number" placeholder="Account Number"><br><br>
<input type="date" id="expiry_date"><br><br>
<button onclick="save()">SAVE</button>
<button onclick="closeModal()">CANCEL</button>
</div>
</div>

<script>
function load(q=''){
 fetch("?action=fetch&q="+q)
 .then(r=>r.text())
 .then(d=>document.getElementById("tbody").innerHTML=d);
}
load();

document.getElementById("search").onkeyup=e=>load(e.target.value);

function openModal(){modal.style.display='block'}
function closeModal(){modal.style.display='none'}

function save(){
 let f=new FormData();
 ['farmer_id','bank_name','account_name','account_number','expiry_date']
 .forEach(i=>f.append(i,document.getElementById(i).value));
 fetch("?action=add",{method:'POST',body:f})
 .then(()=>{closeModal();load()});
}

function delBank(id){
 if(confirm("Delete record?")){
   fetch("?action=delete&id="+id).then(()=>load());
 }
}

function toggleStatus(id,status){
 fetch("?action=toggle&id="+id+"&status="+status)
 .then(()=>load());
}
const farmerSearch = document.getElementById("farmer_search");
const farmerResults = document.getElementById("farmerResults");

farmerSearch.onkeyup = function(){
    let q = this.value;
    if(q.length < 2){
        farmerResults.style.display="none";
        return;
    }
    fetch("?action=search_farmers&q="+q)
    .then(r=>r.text())
    .then(d=>{
        farmerResults.innerHTML=d;
        farmerResults.style.display="block";
    });
}

function selectFarmer(id,name){
    document.getElementById("farmer_id").value=id;
    farmerSearch.value=name;
    farmerResults.style.display="none";
}

</script>

</body>
</html>
