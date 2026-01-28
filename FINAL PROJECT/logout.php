
<?php
session_start();
session_destroy();
header("Location:Farmer_login.php");
exit;
