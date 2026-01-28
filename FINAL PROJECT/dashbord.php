<?php
session_start();
$admin_id = (int)$_SESSION['admin_id'];

// Mipangilio ya Database
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
// COUNTS
$farmerCount  = $pdo->query("SELECT COUNT(*) FROM farmers")->fetchColumn();
$batchCount   = $pdo->query("SELECT COUNT(*) FROM crop_batches")->fetchColumn();
$advisories   = $pdo->query("SELECT COUNT(*) FROM advisory_messages")->fetchColumn();
$logsCount    = $pdo->query("SELECT COUNT(*) FROM collectors")->fetchColumn();
$collectors    = $pdo->query("SELECT COUNT(*) FROM collectors")->fetchColumn();
$consumers    = $pdo->query("SELECT COUNT(*) FROM consumers")->fetchColumn();
$suppliers    = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$processors    = $pdo->query("SELECT COUNT(*) FROM processors")->fetchColumn();

// LIST FETCH (Search + Pagination)
$search = isset($_GET['search']) ? $_GET['search'] : "";
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 6;
$start  = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT * FROM farmers WHERE first_name LIKE :s OR last_name LIKE :s LIMIT $start, $limit");
$stmt->execute(['s' => "%$search%"]);
$farmers = $stmt->fetchAll();

$totalRows = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$totalPages = ceil($totalRows / $limit);

if (isset($_GET['search'])) {
 try {
        $pdo = new PDO("mysql:host=localhost;dbname=agriculture", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

 $search = $_GET['search'];

        $sql = $pdo->prepare("
            SELECT id, first_name, middle_name, last_name, phone, email,location,soil_type,farm_size,status, created_at
            FROM farmers
            WHERE first_name LIKE ?
               OR middle_name LIKE ?
               OR last_name LIKE ?
               OR phone LIKE ?
               OR email LIKE ?
               OR location LIKE ?
               OR soil_type LIKE ?
               OR farm_size LIKE ?
               OR status LIKE ?
               OR created_at LIKE ?
            LIMIT 20
        ");

        $like = "%$search%";
        $sql->execute([$like, $like, $like, $like, $like, $like,$like,$like,$like,$like]);

        echo json_encode($sql->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }

    exit;
}
$batchVolume = [];
$batchMonths = [];

$stmt2 = $pdo->prepare("
SELECT MONTH(created_at) as month, SUM(quantity) AS total_quantity FROM crop_batches GROUP BY MONTH(created_at)");
$stmt2->execute();
while($row = $stmt2->fetch(PDO::FETCH_ASSOC)){
    $batchMonths[] = "Month" . $row['month'];
    $batchVolume[] = $row['total_quantity'];
}

$farmers = [];
$months = [];

$stmt = $pdo->prepare("
SELECT MONTH(created_at) as month, COUNT(*) as total FROM farmers GROUP BY MONTH(created_at)");
$stmt->execute();

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $months[] = "Month " . $row['month'];   // ✔ sahihi
    $farmers[] = $row['total'];
}


// Sasa unaweza kutumia $totalFarmers na $totalBatches katika HTML yako kwa kuunganisha PHP na HTML.
// Kwa mfano, badala ya data-target="1250" unaweza kuandika:
// data-target="<?= $totalFarmers 
// ?>" 
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>


    <style>
        :root {
    --primary-color: #007bff; /* Bluu */
    --secondary-color: #6c757d; /* Kijivu */
    --success-color: #28a745; /* Kijani */
    --white: #ffffff;
    --dark-bg: #f4f7f6;
    --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.1);
    --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.2);
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
  /* Specific border colors for variety */
        .card.blue { border-left-color: var(--primary-color); }
        .card.green { border-left-color: var(--success-color); }
        .card.orange { border-left-color: var(--warning-color); }
        .card.red { border-left-color: var(--danger-color); }
        .card.info { border-left-color: var(--info-color); }
        .card.green .card-label { color: var(--success-color); }
        .card.orange .card-label { color: var(--warning-color); }
        .card.red .card-label { color: var(--danger-color); }
        .card.info .card-label { color: var(--info-color); }
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background:linear-gradient(135deg,#0b9348,#1e88e5);
    color: #333;
}

.dashboard-container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background-color: #2c3e50;
    color: var(--white);
    padding: 20px;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.15);
    height:100px;
}

.logo-section {
    display: flex;
    align-items: center;
    padding-bottom: 30px;
    border-bottom: 1px solid #4a627a;
    margin-bottom: 20px;
}

/* Animated Logo (CSS Keyframes) */
.animated-logo {
    font-size: 24px;
    font-weight: bold;
    margin-right: 10px;
    color: var(--primary-color);
    animation: pulse 2s infinite; /* Animation name, duration, loop */
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

.main-nav .nav-item {
    display: block;
    padding: 12px 15px;
    text-decoration: none;
    color: #bdc3c7;
    margin-bottom: 8px;
    border-radius: 6px;
    transition: background-color 0.3s, color 0.3s;
}

/* Hover Effects kwa Navigation */
.main-nav .nav-item:hover, .main-nav .nav-item.active {
    background-color: #34495e;
    color: var(--white);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

/* Main Content */
.main-content {
    flex-grow: 1;
    padding: 30px;
}

/* Top Bar na Welcome Message */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.welcome-message {
    font-size: 1.1em;
    font-weight: 500;
    padding: 10px 15px;
    background-color: rgba(40, 167, 69, 0.1);
    border-radius: 8px;
    color: var(--success-color); /* Green Color */
}

/* Live Search Bar */
.search-bar {
    position: relative;
    width: 300px;
}

.search-bar input {
    width: 100%;
    padding: 10px 40px 10px 15px;
    border: 1px solid #ccc;
    border-radius: 20px;
    transition: box-shadow 0.3s, border-color 0.3s;
}

.search-bar input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); /* Focus effect */
}

.search-bar i {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary-color);
}

/* Stats Cards */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background-color: var(--white);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: transform 0.3s, box-shadow 0.3s;
    /* Shadows */
    box-shadow: var(--shadow-light); 
}

/* Hover Effect na Shadow kwa Card */
.card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.card-icon {
    font-size: 30px;
    padding: 15px;
    border-radius: 50%;
    /* Rangi za kipekee kwa kila kadi */
}

.farmer-card .card-icon { 
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color); 
    border-left-color:blue;
}
.batch-card .card-icon { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
.advisory-card .card-icon { background-color: rgba(23, 162, 184, 0.1); color: #17a2b8; }
.supply-card .card-icon { background-color: rgba(108, 117, 125, 0.1); color: var(--secondary-color); }

.card-info {
    text-align: right;
}

.stat-count {
    font-size: 2.2em;
    font-weight: bold;
}

/* Graphs Section */
.graphs-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.graph-box {
    background-color: var(--white);
    padding: 20px;
    border-radius: 12px;
    box-shadow: var(--shadow-light);
}
h2{
    text-align:center;
}
/* Footer */
footer {
            background-color:#2c3e50;
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-links h4 {
            margin-bottom: 20px;
            color: white;
        }
        
        .footer-links ul {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #bdc3c7;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="logo-section">
                <div class="animated-logo">A</div>
                <span>Admin Panel</span>
            </div>
            <nav class="main-nav">
                <a href="dashbord.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="manage_farmers.php." class="nav-item"><i class="fas fa-users"></i> Farmers</a>
                <a href="my_batches.php" class="nav-item"><i class="fas fa-seedling"></i> Crop Batches</a>
                <a href="manage_advisories_advanced.php" class="nav-item"><i class="fas fa-comments"></i> Advisories</a>
                <a href="manage_traceability.php" class="nav-item"><i class="fas fa-truck"></i> Supply Logs</a>
                <a href="bank_pro.php" class="nav-item"><i class="fas fa-truck"></i> bank managements</a>
                <a href="admin_report.php" class="nav-item"><i class="fas fa-truck"></i> Reports management</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="welcome-message">
                   
                    <p class="welcome">Welcome Back, <b><?=$_SESSION['admin_id']?><i class="fas fa-check-circle" style="color: #28a745;"></i> </p>
                </div>
                
                <div class="search-bar">
                    <input type="text" id="customerSearch" placeholder="Tafuta kwa haraka...">
                    <i class="fas fa-search"></i>
                    <div id="search-results" class="search-results-dropdown"></div> 
                </div>
            </header>

            <section class="stats-cards">
                 <div class="card blue">
                <div class="card farmer-card">
                    <div class="card-icon"><i class="fas fa-users"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">Total Farmers</h3>
                         <h2><?=$farmerCount?></h2>
                    </div>
                </div>
                </div>
<div class="card green">
                <div class="card batch-card">
                    <div class="card-icon"><i class="fas fa-seedling"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">Total Crop Batches</h3>
                         <h2><?=$batchCount?></h2>
                    </div>
                </div>
</div>
                <div class="card advisory-card">
                    <div class="card-icon"><i class="fas fa-comments"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">Total Advisories</h3>
                        <h2><?=$advisories?></h2>
                    </div>
                </div>

                <div class="card supply-card">
                    <div class="card-icon"><i class="fas fa-truck"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">Total Supply Chain Logs</h3>
                        <h2><?=$logsCount?></h2>
                    </div>
                </div>
                <div class="card supply-card">
                    <div class="card-icon"><i class="fas fa-truck"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">TOTAL COLLECTORS</h3>
                        <h2><?=$collectors?></h2>
                    </div>
                </div>
                <div class="card supply-card">
                    <div class="card-icon"><i class="fas fa-truck"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">TOTAL SUPPLIERS</h3>
                        <h2><?=$suppliers?></h2>
                    </div>
                </div>
                <div class="card supply-card">
                    <div class="card-icon"><i class="fas fa-truck"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">TOTAL PROCESSORS</h3>
                        <h2><?=$processors?></h2>
                    </div>
                </div>
                <div class="card supply-card">
                    <div class="card-icon"><i class="fas fa-truck"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">TOTAL CONSUMERS</h3>
                        <h2><?=$consumers?></h2>
                    </div>
                </div>
                <div class="card supply-card">
                    <div class="card-icon"><i class="fas fa-truck"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">FARMS MASTERS</h3>
                        <h2><?=$logsCount?></h2>
                    </div>
                </div>
            </section>
            
            <section class="graphs-section">
                <div class="graph-box">
                    <h3>Farmers Registration Trend</h3>
                    <canvas id="farmersChart"></canvas>
                    <button onclick="downloadPDF('farmersChart','Farmers Registration Report')">
    Download Farmers PDF
</button>
                </div>
                <div class="graph-box">
                    <h3>Monthly Batch Volume</h3>
                    <canvas id="batchChart"></canvas>
                    <button onclick="downloadPDF('batchChart','Monthly Batch Report')">
    Download Batch PDF
</button>
                </div>
            </section>
        </main>
    </div>
    <hr>
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    
                </div>
                
                <div class="footer-links">
                    <h4>SHORT LINKS</h4>
                    <ul>
                        <li><a href="dashbord.php">HOME</a></li>
                        <li><a href="About me.html">ABOUT</a></li>
                        <li><a href="portfolioo.html">DOCUMENTATION</a></li>
                        <li><a href="contact.html">HELP DESK</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>SERVICES</h4>
                    <ul>
                        <li><a href="services.htmlweb">WED DESIGNING</a></li>
                        <li><a href="services.htmlux">UX/UI DESIGNING</a></li>
                        <li><a href="services.htmlbranding">BRANDING</a></li>
                        <li><a href="services.htmlconsulting">CONSULTATIONS</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>LEGAL</h4>
                    <ul>
                        <li><a href="legal/privacy.html">PRIVACY POLICY</a></li>
                        <li><a href="legal/terms.html">TERMS OF SERVICES</a></li>
                        <li><a href="site.html">SITE</a></li>
                    </ul>
                </div> 
            </div>
            
            <div class="copyright">
                <p>&copy; SMART AGRICULTURE @ 2025. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', () => {

    // 1. Auto Increase Count Logic
    const counters = document.querySelectorAll('.stat-count');

    const updateCount = (counter) => {
        const target = +counter.getAttribute('data-target');
        let count = 0;
        
        // Duration ya animation (katika ms)
        const duration = 2000; 
        // Hatua za kuongezeka (ili iwe laini)
        const step = duration / target; 

        const timer = setInterval(() => {
            count += 1;
            counter.innerText = count.toLocaleString();

            if (count >= target) {
                clearInterval(timer);
                counter.innerText = target.toLocaleString(); // Hakikisha inaishia kwenye target
            }
        }, step);
    };

    counters.forEach(counter => {
        // Tumia Intersection Observer kuanzisha tu count inapoonekana (Advanced)
        // Kwa sasa tunaweza kuita moja kwa moja:
        updateCount(counter); 
    });


    // 2. Statistical Graphs (Chart.js)
    
    // Data ya Farmers Chart
   
    // Data ya Batches Chart
   
    
    
    // 3. Live Search Bar Logic (Frontend Simulation)
    const searchInput = document.querySelector('.search-bar input');
    const searchResultsDiv = document.getElementById('search-results');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        if (searchTerm.length > 1) {
             // Kawaida hapa AJAX/FETCH ingetumwa kwa search.php
             // Hapa chini ni mfano tu (Frontend Simulation)
             
             const dummyResults = ['Farmer John Doe', 'Batch #2024-001', 'Advisory on Pest Control', 'Supply Log: Dar to Arusha'];
             const filtered = dummyResults.filter(result => result.toLowerCase().includes(searchTerm));
             
             let html = '<ul>';
             if (filtered.length > 0) {
                 filtered.forEach(item => {
                     html += `<li><i class="fas fa-search"></i> ${item}</li>`;
                 });
             } else {
                 html += '<li>No results found.</li>';
             }
             html += '</ul>';
             searchResultsDiv.innerHTML = html;
             searchResultsDiv.style.display = 'block';

        } else {
             searchResultsDiv.style.display = 'none';
        }
    });
    
    // Ficha matokeo ya utafutaji mtu akibofya nje
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-bar')) {
            searchResultsDiv.style.display = 'none';
        }
    });

});
//live search
document.getElementById("customerSearch").addEventListener("keyup", function () {
    let search = this.value;

    if (search.length < 1) {
        document.getElementById("customerDetails").style.display = "none";
        return;
    }

    fetch("customer_search.php?search=" + search)
        .then(res => res.json())
        .then(data => {
            let box = document.getElementById("customerDetails");

            if (data.error) {
                box.innerHTML = "<div style='color:red;'>Error: " + data.error + "</div>";
                box.style.display = "block";
                return;
            }

            if (data.length === 0) {
                box.innerHTML = "<div>No results found...</div>";
                box.style.display = "block";
                return;
            }

            let html = "";
            data.forEach(c => {
                let fullName = `${c.first} ${c.middle} ${c.last}`.replace("  ", " ");

                html += `
                    <div class="customer-item" 
                        onclick="selectCustomer('${fullName}', '${c.phone}', '${c.email}')">
                        <strong>${fullName}</strong><br>
                        <small>Phone: ${c.phone}</small><br>
                        <small>Email: ${c.email}</small>
                    </div>
                `;
            });

            box.innerHTML = html;
            box.style.display = "block";
        });
});

// =======================
// CLICK ITEM -> FILL INPUT
// =======================
function selectCustomer(name, phone, email) {
    document.getElementById("customerSearch").value = name;
    document.getElementById("customerDetails").style.display = "none";

    console.log("Selected:", name, phone, email); 
}
//graph

    </script>
<script>
document.addEventListener("DOMContentLoaded", function(){

    const modernColors = [
        '#4e73df', // Blue
        '#1cc88a', // Green
        '#36b9cc', // Cyan
        '#f6c23e', // Yellow
        '#e74a3b', // Red
        '#858796'  // Gray
    ];

    // Farmers Pie Chart
    const farmersData = {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            data: <?php echo json_encode($farmers); ?>,
            backgroundColor: modernColors,
            borderWidth: 2
        }]
    };

    new Chart(document.getElementById('farmersChart'), {
        type: 'doughnut',   // Donut style (modern)
        data: farmersData,
        plugins: [ChartDataLabels],
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                datalabels: {
                    color: '#fff',
                    font: {
                        weight: 'bold',
                        size: 14
                    },
                    formatter: (value, ctx) => {
                        let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        let percentage = (value * 100 / sum).toFixed(1) + "%";
                        return percentage;
                    }
                }
            }
        }
    });


    // Batch Volume Pie Chart
    const batchData = {
        labels: <?php echo json_encode($batchMonths); ?>,
        datasets: [{
            data: <?php echo json_encode($batchVolume); ?>,
            backgroundColor: modernColors,
            borderWidth: 2
        }]
    };

    new Chart(document.getElementById('batchChart'), {
        type: 'doughnut',
        data: batchData,
        plugins: [ChartDataLabels],
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                datalabels: {
                    color: '#fff',
                    font: {
                        weight: 'bold',
                        size: 14
                    },
                    formatter: (value, ctx) => {
                        let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        let percentage = (value * 100 / sum).toFixed(1) + "%";
                        return percentage;
                    }
                }
            }
        }
    });

});
//export graph
async function downloadPDF(chartId, title) {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();

    pdf.setFontSize(18);
    pdf.text(title, 20, 20);

    const canvas = document.getElementById(chartId);
    const image = canvas.toDataURL("image/png");

    pdf.addImage(image, 'PNG', 15, 40, 180, 120);
    pdf.save(chartId + ".pdf");
}
</script>
</body>
</html>