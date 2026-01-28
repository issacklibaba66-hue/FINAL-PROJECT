<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
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
$logsCount    = $pdo->query("SELECT COUNT(*) FROM batch_status_logs")->fetchColumn();

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

$query = $_POST['query'];

$stmt = $pdo->prepare("SELECT * FROM farmers 
                       WHERE first_name LIKE :q 
                          OR last_name LIKE :q 
                          OR phone LIKE :q 
                       LIMIT 5");
$stmt->execute(['q' => "%$query%"]);
$results = $stmt->fetchAll();

if ($results) {
    foreach ($results as $r) {
        $name = $r['first_name'] . " " . $r['last_name'];

        echo "<p onclick=\"fillField('$name')\" 
                 style='margin:0; padding:10px; cursor:pointer;'>
                $name <br> 
                <small>{$r['phone']}</small>
              </p>
              <hr style='margin:0'>";
    }
} else {
    echo "<p style='padding:10px; margin:0;'>No results found</p>";
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
    <style>
        :root {
    --primary-color: #007bff; /* Bluu */
    --secondary-color: #6c757d; /* Kijivu */
    --success-color: #28a745; /* Kijani */
    --white: #ffffff;
    --dark-bg: #f4f7f6;
    --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.1);
    --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.2);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background-color:lightgrey;
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

.farmer-card .card-icon { background-color: rgba(40, 167, 69, 0.1); color: var(--success-color); }
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
                <a href="#" class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="#" class="nav-item"><i class="fas fa-users"></i> Farmers</a>
                <a href="#" class="nav-item"><i class="fas fa-seedling"></i> Crop Batches</a>
                <a href="#" class="nav-item"><i class="fas fa-comments"></i> Advisories</a>
                <a href="#" class="nav-item"><i class="fas fa-truck"></i> Supply Logs</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="welcome-message">
                    <i class="fas fa-check-circle" style="color: #28a745;"></i>                    
                    <p class="welcome">Welcome Back, <b><?=$_SESSION['admin']?></b> 👋</p>
                </div>
                
                <div class="search-bar">
                    <input type="text" placeholder="Tafuta kwa haraka...">
                    <i class="fas fa-search"></i>
                    <div id="search-results" class="search-results-dropdown"></div> 
                </div>
            </header>

            <section class="stats-cards">
                <div class="card farmer-card">
                    <div class="card-icon"><i class="fas fa-users"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">Total Farmers</h3>
                         <h2><?=$farmerCount?></h2>
                    </div>
                </div>

                <div class="card batch-card">
                    <div class="card-icon"><i class="fas fa-seedling"></i></div>
                    <div class="card-info">
                        <h3 class="card-title">Total Crop Batches</h3>
                         <h2><?=$batchCount?></h2>
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
            </section>
            
            <section class="graphs-section">
                <div class="graph-box">
                    <h3>Farmers Registration Trend</h3>
                    <canvas id="farmersChart"></canvas>
                </div>
                <div class="graph-box">
                    <h3>Monthly Batch Volume</h3>
                    <canvas id="batchesChart"></canvas>
                </div>
            </section>
        </main>
    </div>

    <script src="script.js">
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
    const farmersCtx = document.getElementById('farmersChart').getContext('2d');
    new Chart(farmersCtx, {
        type: 'line', 
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            datasets: [{
                label: 'New Farmers Registered',
                data: [50, 75, 120, 150, 90, 180, 200], // Data kutoka DB
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderColor: '#28a745',
                borderWidth: 3,
                tension: 0.4, // Kufanya mstari uwe laini
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Data ya Batches Chart
    const batchesCtx = document.getElementById('batchesChart').getContext('2d');
    new Chart(batchesCtx, {
        type: 'bar',
        data: {
            labels: ['Rice', 'Maize', 'Coffee', 'Cotton'],
            datasets: [{
                label: 'Total Crop Batches',
                data: [400, 650, 150, 300], // Data kutoka DB
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(23, 162, 184, 0.8)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    
    
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
    </script>
</body>
</html>