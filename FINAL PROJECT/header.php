
<?php
// header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/db.php";
$admin = null;
if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel</title>
  <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0/css/all.min.css">
  <script src="https://kit.fontawesome.com/a83e172831.js" crossorigin="anonymous"></script>
  <style type="text/css">
      :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-bg: #f8f9fc;
            --card-bg: #ffffff;
            --text-dark: #5a5c69;
            --sidebar-width: 250px;
        }
  body{
    background-color:lightgrey;
      font-family: 'Inter', sans-serif;
  }
  .navbar {
  height: 12px;
width:97%;
display:flex;
justify-content:space-between;
align-items: center;
background-color:green;
padding: 1.5rem;
box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
position: sticky;
top: 0;
z-index: 1000px;
color: white;
transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .navbar-container {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background color: #000;
              box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .navbar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .navbar-links {
            display: inline;
            flex-grow: 1;
            justify-content: center;
            gap: 2rem;
            color: white;
            font-weight: 500;
        }

        .navbar-links a {
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .navbar-links a:hover {
            color: white;
        }

        .search-bar {
            position: relative;
        }

        .search-bar input {
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .search-bar input:focus {
            border-color:black;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.5);
        }
        
        .search-bar .fa-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        :root.dark {
  --bg: #121212;
  --card: #1e1e1e;
  --text: #eaeaea;
  --muted: #aaa;
  --brand: #4ea1ff;
  --accent: #ffd54a;
  --border: #2a2a2a;
}
/* Main Content */
        .main-container {
            flex-grow: 1;
            max-width: 1200px;
            margin: auto;
            padding: 1.5rem 3rem;
        }

        /* Cards */
        .page-content {
            transition: opacity 0.5s ease;
        }

        .page-content.hidden {
            display: none;
            opacity: 0;
        }

        .page-title {
            font-size: 2.25rem;
            font-weight: 500;
            text-align: center;
            color: black;
            margin-bottom: 2rem;
        }

        .page-subtitle {
            text-align: center;
            color: black;
            margin-bottom: 3rem;
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1rem;
        }

        @media (min-width: 700px) {
            .navbar-links {
                display: flex;
            }
            .cards-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .card {
            background-color: #fff;
            width: 90%;
            margin-left:50px;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
          .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
          }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
         .card-min {
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .card-min:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .card-image {
            width: 100%;
            height: 12rem;
            margin-bottom: 1rem;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: white;
            margin-bottom: 0.5rem;
        }

        .card-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            margin-bottom: 1rem;
        }

        .card-price span {
            font-size: 0.875rem;
            font-weight: 400;
            color: green;
        }

        .subscribe-btn {
            width:10%;
            background-color: green;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 9999px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .subscribe-btn:hover {
            background-color: lightgreen;
        }
        
        /* Payment Page */
        .payment-methods-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .payment-card {
            background-color: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        
        .payment-card.selected {
            border: 4px solid #188302;
            transform: scale(1.05);
        }
        
        .payment-card:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .payment-logo {
            width: 4rem;
            height: 4rem;
            border-radius: 9999px;
            margin-bottom: 0.5rem;
        }

        .payment-form-container {
            display: none;
            justify-content: center;
        }

        .payment-form {
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 32rem;
            text-align: center;
        }

        .package-summary {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .package-summary p {
            font-size: 1rem;
            color: green;
        }

        .package-summary strong {
            font-size: 1.5rem;
            font-weight: 700;
            color: black;
        }
        .badge {
    padding: 5px 10px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
    display: inline-block;
}

.badge-success { background-color: #1cc88a; } /* Green */
.badge-warning { background-color: #f6c23e; } /* Orange */
.badge-danger  { background-color: #e74a3b; } /* Red */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            text-align: left;
            font-weight: 500;
            color: green;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: green;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.5);
        }

        button {
            background-color: green;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            width: 25%;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: lightgreen;
        }
        .button-danger {
            background-color: red;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 2px;
            cursor: pointer;
            width: 50%;
            transition: background-color 0.3s ease;
        }
            .button-edit {
            background-color: blue;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 2px;
            cursor: pointer;
            width: 10%;
            transition: background-color 0.3s ease;
        }
.alert { 
  background:red; 
  color:white; 
  padding:10px;
   border-radius:8px;
    border:1px solid #ffcccc;
     margin-bottom:10px; }
.success { 
  background:green;
   color:white; 
   padding:10px; 
   border-radius:8px;
    border:1px solid #bff3c3;
     margin-bottom:10px; }

        /* Modal / Confirmation Dialog */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.visible {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background-color: #fff;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            text-align: center;
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        
        .modal.visible .modal-content {
            transform: scale(1);
        }

        .modal-content h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #047e28;
            margin-bottom: 1rem;
        }

        .modal-content p {
            color: #047e29;
            margin-bottom: 0.5rem;
        }
        
        .modal-content strong {
            font-weight: 600;
        }

        .modal-content button {
            background-color: green;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            margin-top: 1rem;
        }

        /* Footer */
        .footer {
            background-color: black;
            color: #d1d5db;
            padding: 1rem;
            text-align: center;
            font-size: 0.875rem;
        }
        #loader {
  position: fixed;
  width: 100%;
  height: 100%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.logo-spin {
  width: 80px;
  height: 80px;
  animation: spin 2s linear infinite;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
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

.sidebar {
  position: fixed;
   top: 60px; 
   left: 0;
    bottom: 0; 
    width: 160px;
     background: green;
     margin-left:8px;
       box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border-right: 1px solid var(--border);
   padding: 16px;
    display:flex; 
    flex-direction:column;
     gap:10px;
       display: inline;
            flex-grow: 1;
            justify-content: center;
            gap: 2rem;
            color: white;
            font-weight: 500;
}
.sidebar a {
  text-decoration:none;
   color: var(--text); 
   padding:10px 12px;
   
   
}
.sidebar a:hover { 
  background: var(--bg);
 }
.content {
   margin-left: 220px;
    padding: 20px;
     min-height: calc(100vh - 60px);
     }
 .table { 
  width:100%;
   border-collapse: collapse;
   }
.table th, .table td {
   padding:10px; 
   border-bottom:1px solid var(--border);
    text-align:center; }
.table th { color: var(--muted);
   font-weight:600; 
   background: #0f750f;
            color: white;
}

.content {
   margin-left: 220px;
   padding: 20px;
    min-height: calc(100vh - 60px);
   }
/*.sidebar {
      width: 240px;
      background: #2c3e50;
      color: #ecf0f1;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px 0;
    }*/
     .sidebar img.profile {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 15px;
      border: 3px solid #fff;
    }
    .sidebar h2 {
         margin-bottom: 30px; 
         font-size: 1.2em; 
        }
    .nav-btn {
      width: 100%;
      padding: 12px 20px;
      text-align: left;
      border: none;
      background: none;
      color: inherit;
      font-size: 1em;
      cursor: pointer;
      transition: background 0.2s;
    }
      .nav-btn {
      width: 100%;
      padding: 12px 20px;
      text-align: left;
      border: none;
      background: none;
      color: inherit;
      font-size: 1em;
      cursor: pointer;
      transition: background 0.2s;
    }
    .nav-btn:hover,
    .nav-btn.active {
      background: #34495e;
    }
    .blue{
  background:magenta;
  color:white;
     border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .blue:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
.green{
  background:green;
  color:white;
     border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .green:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}
.orange{
  background:orange;
  color:white;
     border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .orange:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.red{
  background:red;
  color:white;
     border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .red:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}
.purple{
           background:purple;
            color:white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .purple:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}
.yellow{
  background:darkblue;
  color:white;
     border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
 .yellow:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}
   {
            margin-bottom: 1rem;
        }

        .select label {
            display: block;
            text-align: left;
            font-weight: 500;
            color: green;
            margin-bottom: 0.5rem;
        }

        select{
            width: 10%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        select :focus {
            border-color: green;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.5);
        }
        header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    position: fixed; /* Inakaa juu wakati wote */
    width: calc(100% - 60px); 
    z-index: 1000;
}

.logo {
    font-size: 1.5em;
    font-weight: bold;
    text-decoration: none;
    color: #333;
}
.hamburger-btn {
    width: 30px;
    height: 30px;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
    z-index: 1001; /* Lazima iwe juu ya Full-Screen Menu */
}

.line {
    display: block;
    width: 100%;
    height: 2px;
    background-color:white; /* Rangi ya mistari */
    transition: all 0.3s ease-in-out; /* Uhuishaji wa mgeuko */
}


.hamburger-btn.open .line:nth-child(1) {
    transform: translateY(14px) rotate(45deg); /* Mstari wa juu unashuka na kugeuka */
}

.hamburger-btn.open .line:nth-child(2) {
    opacity: 0; /* Mstari wa kati unafifia kabisa */
}

.hamburger-btn.open .line:nth-child(3) {
    transform: translateY(-14px) rotate(-45deg); /* Mstari wa chini unapanda na kugeuka */
}
.full-screen-menu {
    position: fixed;
    top: 0;
    left: 0;
    width: 50%;
    height: 50%;
   /* background-color: rgba(0, 0, 0, 0.95);  Overlay nyeusi maridadi */
    z-index: 1000;
    opacity: 0; 
    visibility: hidden; /* Mwanzo haionekani */
    transition: opacity 0.4s ease-in-out; /* Uhuishaji wa kufifia */
}

.full-screen-menu.show {
    opacity: 1;
    visibility: visible;
}

.menu-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
    text-align: center;
}

.full-screen-menu ul {
    list-style: none;
    padding: 0;
    margin-bottom: 50px;
}

.full-screen-menu li {
    margin: 20px 0;
}
/*.full-screen-menu a {
    color: #fff; 
    text-decoration: none;
    font-size: 2.5em; 
    font-weight: 700;
    letter-spacing: 2px;
    transition: color 0.2s;
}*/

.full-screen-menu a:hover {
    color: #ffc107; 
}
.cta-button {
    padding: 10px 20px;
    border: 2px solid #ffc107;
    color: #ffc107 !important; 
    font-size: 1.2em !important;
    text-transform: uppercase;
}

.cta-button:hover {
    background-color: #ffc107;
    color: #000 !important;
}
/* CSS ya Ziada: Kuzuia Scrolling */
.menu-open {
    overflow: hidden; /* Hii inazuia skrini nzima kusogezwa */
}
   .delete-btn {
            background: red;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            text-decoration: none;
        }
        /* Chart Container */
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .chart-header {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 15px;
        }
  </style>
  <script defer src="assets/js/app.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <nav class="navbar">
    <div class="navbar-logo">E-COMMERCE DASHBORD</div>
    <div class="search-bar">
      <input type="text" placeholder="search here...." class="fa-solid magnifying-glass">
    </div>
    <span><?php echo htmlspecialchars($admin['first_name']); ?></span>
    <a href="barcode.html"style="background:green;color:white;padding:6px 12px;border-radius:6px;text-decoration:none;margin-left:4%;">SCAN BAR/QR CODE</a>
    <div class="actions">
      <button id="darkToggle" title="Dark/Light">🌓</button>
      <?php if ($admin): ?>
      <?php endif; ?>
    </div>
    
    <a href="#" class="logo"></a>
    <button class="hamburger-btn" aria-label="Open menu">
        <span class="line"></span>
        <span class="line"></span>
        <span class="line"></span>
    </button>
      </header>
  </nav>
<nav class="full-screen-menu">

  <aside class="sidebar">
    <div class="profile-mini">
         <div class="profile-mini img"> <img src="<?php echo htmlspecialchars($admin['profile_pic']); ?>" alt="pic"></div>
        </div>
        <br>
        <div class="">
    <div class=""><a href="dashboard.php"><i class="fa-regular fa-house"></i> HOME</a></div>
    <br><br>
   <div class=""> <a href="customers.php"><i class="fa-solid fa-users-line"></i> CUSTOMERS</a></div>
   <br><br>
    <div class=""><a href="pack.php"><i class="fa-solid fa-house"></i> PRODUCTS</a></div>
    <br><br>
    <div class=""><a href="payment.php"><i class="fa-solid fa-wallet"></i> PAYMENTS</a></div>
    <br><br>
    <a href="orders.php">🚪 ORDERS</a></div>
    <br><br>
      <a href="vendors.php">🚪 VENDORS</a></div>
      <br><br>
    <div class=""><a href="settings.php"><i class="fa-solid fa-gear"></i> SETTINGS</a></div>
    <br><br>
    <a href="logout.php">🚪 LOG OUT</a></div>
      </div>
  </aside>
      </div>
      </nav>
  <script>
     const hamburgerBtn = document.querySelector('.hamburger-btn');
const fullScreenMenu = document.querySelector('.full-screen-menu');

hamburgerBtn.addEventListener('click', () => {
    // Badili class kwenye ikoni (kwa ajili ya animation ya 'X')
    hamburgerBtn.classList.toggle('open');
    
    // Badili class kwenye menyu (kwa ajili ya kufunika skrini)
    fullScreenMenu.classList.toggle('show');

    // Hii ni ya kisasa: Zuia scrolling ya body wakati menyu imefunguka
    document.body.classList.toggle('menu-open');
});
</script>
</body>
</html>