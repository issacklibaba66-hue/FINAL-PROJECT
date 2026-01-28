<?php
session_start();
$consumer_id = (int)$_SESSION['consumer_id'];

//$stmt = $pdo->prepare("SELECT * FROM consumers WHERE id = :id");

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
// FETCH MAIN BATCH DATA
$stmt = $pdo->prepare("
    SELECT 
        b.batch_code, b.quantity, b.harvest_date,
        c.crop_type, c.variety, c.planted_date,
        f.first_name, f.middle_name, f.last_name, f.location
    FROM crop_batches b
    JOIN crops c ON c.id = b.crop_id
    JOIN farmers f ON f.id = c.farmer_id
    WHERE b.batch_code = :bc
    LIMIT 1
");
$stmt->execute([':bc' => $batchCode]);
/*$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die("Batch not found");
}*/
// FETCH TRACEABILITY LOGS
$logs = $pdo->prepare("
    SELECT * FROM batch_status_logs
    WHERE batch_id = (
        SELECT id FROM crop_batches WHERE batch_code = :bc LIMIT 1
    )
    ORDER BY timestamp ASC
");
$logs->execute([':bc' => $batchCode]);
$timeline = $logs->fetchAll(PDO::FETCH_ASSOC);
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm to Consumer Traceability System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        :root {
            --primary-color: #2ecc71;
            --primary-dark: #27ae60;
            --secondary-color: #3498db;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .logo i {
            font-size: 2.5rem;
            color: var(--primary-color);
        }

        .logo h1 {
            color: var(--dark-color);
            font-size: 2.2rem;
            font-weight: 700;
        }

        .tagline {
            color: #666;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 992px) {
            .main-content {
                grid-template-columns: 1fr 2fr;
            }
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            height: 100%;
        }

        .card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-5px);
        }

        .card-title {
            color: var(--dark-color);
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary-color);
        }

        .qr-scanner-container {
            text-align: center;
        }

        #qr-reader {
            width: 100%;
            max-width: 400px;
            margin: 0 auto 20px;
            border: 3px solid var(--primary-color);
            border-radius: 10px;
            overflow: hidden;
        }

        .scanner-instruction {
            background: var(--light-color);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 0.95rem;
        }

        .scanner-instruction i {
            color: var(--secondary-color);
            margin-right: 8px;
        }

        .manual-input {
            margin-top: 25px;
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .input-group input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn {
            padding: 12px 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--secondary-color);
        }

        .btn-secondary:hover {
            background: #2980b9;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-item {
            padding: 15px;
            background: var(--light-color);
            border-radius: 10px;
            transition: var(--transition);
        }

        .info-item:hover {
            background: #d6eaf8;
            transform: translateX(5px);
        }

        .info-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1rem;
            color: var(--dark-color);
            font-weight: 600;
        }

        .certification-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            margin: 5px;
        }

        .certified {
            background: #d5f4e6;
            color: var(--primary-dark);
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .uncertified {
            background: #f8d7da;
            color: var(--danger-color);
        }

        .timeline {
            position: relative;
            padding: 20px 0;
            margin: 30px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--primary-color);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding-left: 50px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 5px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary-color);
        }

        .timeline-date {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        .timeline-content {
            background: var(--light-color);
            padding: 15px;
            border-radius: 10px;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .status-planted { background: #e8f5e9; color: #2e7d32; }
        .status-growing { background: #fff3e0; color: #ef6c00; }
        .status-harvested { background: #e3f2fd; color: #1565c0; }
        .status-processed { background: #f3e5f5; color: #7b1fa2; }
        .status-distributed { background: #e8eaf6; color: #3949ab; }

        .loading {
            text-align: center;
            padding: 40px;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hidden {
            display: none;
        }

        footer {
            text-align: center;
            margin-top: 50px;
            padding: 20px;
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .logo h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-seedling"></i>
                <h1>FarmTrace</h1>
            </div>
            <p class="tagline">Scan QR code to trace your food's journey from farm to table. Ensure quality, transparency, and trust.</p>
        </header>

        <div class="main-content">
            <!-- QR Scanner Section -->
            <div class="card qr-scanner-container">
                <h2 class="card-title">
                    <i class="fas fa-qrcode"></i> Scan Batch QR Code
                </h2>
                
                <div id="qr-reader"></div>
                
                <div class="scanner-instruction">
                    <p><i class="fas fa-info-circle"></i> Point your camera at the QR code on the product packaging to trace its origin</p>
                </div>
                
                <div class="manual-input">
                    <p style="margin-bottom: 10px; color: #666;">Or enter batch code manually:</p>
                    <div class="input-group">
                        <input type="text" id="batch-input" placeholder="Enter batch code (e.g., BATCH-001)">
                        <button class="btn" id="search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                
                <div id="loading" class="loading hidden">
                    <div class="loading-spinner"></div>
                    <p>Loading batch information...</p>
                </div>
            </div>

            <!-- Batch Information Section -->
            <div class="card">
                <div id="batch-info">
                    <h2 class="card-title">
                        <i class="fas fa-clipboard-check"></i> Batch Verification
                    </h2>
                    <p style="color: #666; margin-bottom: 30px;">Scan a QR code or enter batch code to view product traceability information</p>
                    
                    <div class="status-indicator status-distributed hidden" id="batch-status">
                        <i class="fas fa-check-circle"></i>
                        <span>Product Distributed</span>
                    </div>
                </div>

                <div id="batch-details" class="hidden">
                    <!-- Farm Details -->
                    <h3 style="color: var(--dark-color); margin: 30px 0 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-tractor"></i> Farm Details
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Farm Name</div>
                            <div class="info-value" id="farm-name">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Owner</div>
                            <div class="info-value" id="farm-owner">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Location</div>
                            <div class="info-value" id="farm-location">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Certification</div>
                            <div class="info-value">
                                <span class="certification-badge certified" id="farm-certification">Certified</span>
                            </div>
                        </div>
                    </div>

                    <!-- Crop Details -->
                    <h3 style="color: var(--dark-color); margin: 30px 0 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-leaf"></i> Crop Details
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Crop Type</div>
                            <div class="info-value" id="crop-type">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Variety</div>
                            <div class="info-value" id="crop-variety">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Planting Date</div>
                            <div class="info-value" id="planting-date">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Harvest Date</div>
                            <div class="info-value" id="harvest-date">-</div>
                        </div>
                    </div>

                    <!-- Supply Chain -->
                    <h3 style="color: var(--dark-color); margin: 30px 0 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exchange-alt"></i> Supply Chain Information
                    </h3>
                    
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date">April 16, 2024</div>
                            <div class="timeline-content">
                                <strong>Collection</strong>
                                <p>Collected by: <span id="collector-name">-</span></p>
                                <span class="certification-badge certified" id="collector-certification">Certified</span>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-date">April 17, 2024</div>
                            <div class="timeline-content">
                                <strong>Processing</strong>
                                <p>Processed by: <span id="processor-name">-</span></p>
                                <span class="certification-badge certified" id="processor-certification">Certified</span>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-date">April 18, 2024</div>
                            <div class="timeline-content">
                                <strong>Distribution</strong>
                                <p>Supplied by: <span id="supplier-name">-</span></p>
                                <span class="certification-badge certified" id="supplier-certification">Certified</span>
                            </div>
                        </div>
                    </div>

                    <!-- Certification -->
                    <h3 style="color: var(--dark-color); margin: 30px 0 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-certificate"></i> Certification Status
                    </h3>
                    <div class="info-grid">
                        <div class="info-item" style="text-align: center;">
                            <div class="info-label">Overall Certification</div>
                            <div class="info-value">
                                <span class="certification-badge certified" style="font-size: 1.2rem; padding: 10px 20px;">
                                    <i class="fas fa-shield-alt"></i> FULLY CERTIFIED
                                </span>
                            </div>
                            <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">This product meets all quality standards</p>
                        </div>
                    </div>

                    <button class="btn" id="verify-btn" style="margin-top: 30px; width: 100%;">
                        <i class="fas fa-check-circle"></i> Verify & Download Certificate
                    </button>
                </div>
            </div>
        </div>

        <footer>
            <p>FarmTrace &copy; 2024 | Ensuring Transparency in Food Supply Chain</p>
            <p style="margin-top: 5px; font-size: 0.8rem;">Scan. Trace. Trust.</p>
        </footer>
    </div>

    <script>
        // Sample data structure
        const sampleBatchData = {
            'BATCH-001': {
                batchCode: 'BATCH-001',
                status: 'distributed',
                farm: {
                    name: 'Green Valley Farm',
                    owner: 'John Smith',
                    location: '123 Farm Road, Agricultural Zone',
                    size: '50.5 acres',
                    certification: 'certified'
                },
                crop: {
                    type: 'Tomato',
                    variety: 'Cherry Tomato',
                    plantingDate: 'January 15, 2024',
                    harvestDate: 'April 15, 2024',
                    season: 'Summer'
                },
                supplyChain: {
                    collector: {
                        name: 'Fresh Harvest Co.',
                        certification: 'certified',
                        date: 'April 16, 2024'
                    },
                    processor: {
                        name: 'Pure Process Foods',
                        certification: 'certified',
                        date: 'April 17, 2024'
                    },
                    supplier: {
                        name: 'Organic Market Suppliers',
                        certification: 'certified',
                        date: 'April 18, 2024'
                    }
                }
            },
            'BATCH-002': {
                batchCode: 'BATCH-002',
                status: 'processed',
                farm: {
                    name: 'Sunrise Organic Farm',
                    owner: 'Maria Garcia',
                    location: '456 Organic Lane, Valley District',
                    size: '75.2 acres',
                    certification: 'certified'
                },
                crop: {
                    type: 'Carrot',
                    variety: 'Nantes',
                    plantingDate: 'February 1, 2024',
                    harvestDate: 'May 1, 2024',
                    season: 'Spring'
                },
                supplyChain: {
                    collector: {
                        name: 'Green Collectors',
                        certification: 'certified',
                        date: 'May 2, 2024'
                    },
                    processor: {
                        name: 'Quality Processors Ltd.',
                        certification: 'certified',
                        date: 'May 3, 2024'
                    },
                    supplier: {
                        name: 'Fresh Direct Supply',
                        certification: 'pending',
                        date: 'May 5, 2024'
                    }
                }
            }
        };

        // Initialize QR Scanner
        function onScanSuccess(decodedText, decodedResult) {
            console.log(`QR Code scanned: ${decodedText}`);
            handleBatchCode(decodedText);
        }

        function onScanFailure(error) {
            console.warn(`QR scan error: ${error}`);
        }

        const html5QrCode = new Html5Qrcode("qr-reader");
        const qrConfig = { 
            fps: 10, 
            qrbox: { width: 250, height: 250 } 
        };

        html5QrCode.start(
            { facingMode: "environment" },
            qrConfig,
            onScanSuccess,
            onScanFailure
        ).catch(err => {
            console.log("Unable to start QR scanner:", err);
        });

        // Handle manual search
        document.getElementById('search-btn').addEventListener('click', () => {
            const batchCode = document.getElementById('batch-input').value.trim().toUpperCase();
            if (batchCode) {
                handleBatchCode(batchCode);
            } else {
                alert('Please enter a batch code');
            }
        });

        // Handle Enter key in input
        document.getElementById('batch-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('search-btn').click();
            }
        });

        // Handle batch code
        async function handleBatchCode(batchCode) {
            showLoading(true);
            
            // Simulate API delay
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            const batchData = sampleBatchData[batchCode];
            
            if (batchData) {
                displayBatchData(batchData);
            } else {
                showError('Batch code not found. Please check and try again.');
            }
            
            showLoading(false);
        }

        // Display batch data
        function displayBatchData(data) {
            // Update batch status
            const statusElement = document.getElementById('batch-status');
            statusElement.className = `status-indicator status-${data.status}`;
            statusElement.querySelector('span').textContent = `Product ${data.status.charAt(0).toUpperCase() + data.status.slice(1)}`;
            statusElement.classList.remove('hidden');
            
            // Update farm details
            document.getElementById('farm-name').textContent = data.farm.name;
            document.getElementById('farm-owner').textContent = data.farm.owner;
            document.getElementById('farm-location').textContent = data.farm.location;
            
            const farmCert = document.getElementById('farm-certification');
            farmCert.textContent = data.farm.certification.charAt(0).toUpperCase() + data.farm.certification.slice(1);
            farmCert.className = `certification-badge ${data.farm.certification}`;
            
            // Update crop details
            document.getElementById('crop-type').textContent = data.crop.type;
            document.getElementById('crop-variety').textContent = data.crop.variety;
            document.getElementById('planting-date').textContent = data.crop.plantingDate;
            document.getElementById('harvest-date').textContent = data.crop.harvestDate;
            
            // Update supply chain
            document.getElementById('collector-name').textContent = data.supplyChain.collector.name;
            const collectorCert = document.getElementById('collector-certification');
            collectorCert.textContent = data.supplyChain.collector.certification.charAt(0).toUpperCase() + data.supplyChain.collector.certification.slice(1);
            collectorCert.className = `certification-badge ${data.supplyChain.collector.certification}`;
            
            document.querySelector('.timeline-item:nth-child(1) .timeline-date').textContent = data.supplyChain.collector.date;
            
            document.getElementById('processor-name').textContent = data.supplyChain.processor.name;
            const processorCert = document.getElementById('processor-certification');
            processorCert.textContent = data.supplyChain.processor.certification.charAt(0).toUpperCase() + data.supplyChain.processor.certification.slice(1);
            processorCert.className = `certification-badge ${data.supplyChain.processor.certification}`;
            
            document.querySelector('.timeline-item:nth-child(2) .timeline-date').textContent = data.supplyChain.processor.date;
            
            document.getElementById('supplier-name').textContent = data.supplyChain.supplier.name;
            const supplierCert = document.getElementById('supplier-certification');
            supplierCert.textContent = data.supplyChain.supplier.certification.charAt(0).toUpperCase() + data.supplyChain.supplier.certification.slice(1);
            supplierCert.className = `certification-badge ${data.supplyChain.supplier.certification}`;
            
            document.querySelector('.timeline-item:nth-child(3) .timeline-date').textContent = data.supplyChain.supplier.date;
            
            // Show batch details
            document.getElementById('batch-details').classList.remove('hidden');
        }

        // Show loading state
        function showLoading(show) {
            document.getElementById('loading').classList.toggle('hidden', !show);
        }

        // Show error message
        function showError(message) {
            alert(message);
        }

        // Handle verification button
        document.getElementById('verify-btn').addEventListener('click', () => {
            alert('Certificate downloaded successfully! This product has been verified and certified.');
        });

        // Demo: Auto-populate with sample data for demo
        document.addEventListener('DOMContentLoaded', () => {
            // Auto-populate with sample batch code
            document.getElementById('batch-input').value = 'BATCH-001';
            
            // Simulate a scan for demo purposes
            setTimeout(() => {
                handleBatchCode('BATCH-001');
            }, 500);
        });
    </script>
</body>
</html>