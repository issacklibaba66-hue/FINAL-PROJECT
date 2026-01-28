<?php
session_start();
$DB_HOST = 'localhost';
$DB_NAME = 'agriculture'; 
$DB_USER = 'root';
$DB_PASS = '';
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

/* Protect */
if(!isset($_SESSION['role']) || $_SESSION['role']!=='admin'){
    header("Location: ../login.php"); exit;
}

$success=$error=null;

/* UPDATE STATUS + REPLY */
if($_SERVER['REQUEST_METHOD']==='POST'){

    $id     = (int)$_POST['id'];
    $status = $_POST['status'];
    $reply  = trim($_POST['reply']);

    // Get farmer info
    $q=$pdo->prepare("
      SELECT r.*, f.email, f.phone, f.first_name
      FROM farmer_reports r
      JOIN farmers f ON f.id=r.farmer_id
      WHERE r.id=?
    ");
    $q->execute([$id]);
    $data=$q->fetch(PDO::FETCH_ASSOC);

    if($data){

        // Update report
        $stmt=$pdo->prepare("
          UPDATE farmer_reports
          SET status=?, admin_reply=?, replied_at=NOW()
          WHERE id=?
        ");
        $stmt->execute([$status,$reply,$id]);

        /* SEND EMAIL */
        sendEmail($data['email'],$data['first_name'],$reply,$status);

        /* SEND WHATSAPP / SMS */
        sendWhatsapp($data['phone'],$reply,$status);

        $success="Reply sent successfully";
    }else{
        $error="Report not found";
    }
}


/* FETCH REPORTS */
$stmt=$pdo->query("
 SELECT r.*, f.first_name,f.last_name
 FROM farmer_reports r
 JOIN farmers f ON f.id=r.farmer_id
 ORDER BY r.id DESC
");
$reports=$stmt->fetchAll(PDO::FETCH_ASSOC);


/* ================= EMAIL FUNCTION ================= */

function sendEmail($to,$name,$reply,$status){

    $subject="Your Farm Report Update";

    $msg="
Hello $name,

Your farm report has been updated.

Status: $status

Reply:
$reply

Thank you.
Agri System Team
";

    @mail($to,$subject,$msg,"From: no-reply@agrisystem.com");
}


/* ================= WHATSAPP FUNCTION ================= */
/* Example: Twilio / Africa's Talking */

function sendWhatsapp($phone,$reply,$status){

    // EXAMPLE using Africa's Talking (replace keys)

    /*
    $username = 'YOUR_USERNAME';
    $apiKey   = 'YOUR_API_KEY';

    $msg="Report Update:
Status: $status
Reply: $reply";

    $url='https://api.africastalking.com/version1/messaging';

    $data=[
      'username'=>$username,
      'to'=>$phone,
      'message'=>$msg
    ];

    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
    curl_setopt($ch,CURLOPT_HTTPHEADER,[
        "apiKey:$apiKey"
    ]);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_exec($ch);
    curl_close($ch);
    */

    return true; // placeholder
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Farmer Reports</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">

<style>
body{font-family:Poppins;background:#f2f6f2;margin:0}
.container{max-width:1200px;margin:auto;padding:20px}

.card{
 background:#fff;
 padding:20px;
 border-radius:12px;
 box-shadow:0 10px 25px rgba(0,0,0,.08);
 margin-bottom:20px
}

table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #eee}

textarea{width:100%;padding:8px;border-radius:6px}

select,input,button{
 padding:8px;border-radius:6px;border:1px solid #ccc
}

button{
 background:#0b9348;color:#fff;border:none;cursor:pointer
}

button:hover{box-shadow:0 4px 8px rgba(0,0,0,.2)}

.status{
 padding:4px 8px;border-radius:6px;font-size:13px
}

.pending{background:#fff3cd}
.processed{background:#d1ecf1}
.replied{background:#d4edda}

.msg{font-weight:500}
.success{color:green}
.error{color:red}
</style>
</head>
<body>

<div class="container">

<h2>Farmer Reports Management</h2>

<?php if($success):?><p class="msg success"><?=$success?></p><?php endif;?>
<?php if($error):?><p class="msg error"><?=$error?></p><?php endif;?>


<?php foreach($reports as $r):?>

<div class="card">

<h4><?=$r['title']?> (<?=$r['first_name']?> <?=$r['last_name']?>)</h4>

<span class="status <?=$r['status']?>"><?=$r['status']?></span>

<p><?=$r['description']?></p>

<?php if($r['attachment']):?>
<a href="<?=$r['attachment']?>" target="_blank">View Attachment</a>
<?php endif;?>


<form method="POST" style="margin-top:15px">

<input type="hidden" name="id" value="<?=$r['id']?>">

<label>Status</label><br>
<select name="status">
  <option <?=$r['status']=='pending'?'selected':''?>>pending</option>
  <option <?=$r['status']=='processed'?'selected':''?>>processed</option>
  <option <?=$r['status']=='replied'?'selected':''?>>replied</option>
</select>

<br><br>

<label>Reply</label>
<textarea name="reply" rows="3" required><?=$r['admin_reply']?></textarea>

<br><br>

<button>Send Reply</button>

</form>

</div>

<?php endforeach;?>

</div>
</body>
</html>
