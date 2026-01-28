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

// 1. Kuregister Bank Details Mpya
if (isset($_POST['register_bank'])) {
    $sql = "INSERT INTO bank_details (farmer_id, bank_name, account_number, card_holder_name, expiry_date, cvv, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'active')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_POST['farmer_id'], $_POST['bank_name'], $_POST['acc_no'], $_POST['holder'], $_POST['expiry'], $_POST['cvv']]);
    header("Location: admin_dashboard.php?success=Registered");
}

// 2. Kubadili Status (Activate/Deactivate)
if (isset($_GET['toggle_status'])) {
    $new_status = ($_GET['current'] == 'active') ? 'deactivated' : 'active';
    $stmt = $pdo->prepare("UPDATE bank_details SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $_GET['id']]);
    header("Location: admin_dashboard.php");
}

// 3. Kufuta (Delete)
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM bank_details WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: admin_dashboard.php?deleted=1");
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <title>Admin - Manage Farmers Bank</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body class="bg-light">

<div class="container mt-4">
    <h2 class="mb-4">BANK MANAGEMENT PORTAL</h2>

    <div class="display-flex; justify-content:between mb-3">
        <input type="text" id="searchInput" class="form-control w-50" placeholder="Tafuta mkulima, benki, au namba ya akaunti..." onkeyup="liveSearch()">
        <div>
            <button class="btn btn-danger" onclick="exportToPDF()">EXPORT PDF</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">+ Register New</button>
        </div>
    </div>

    <div class="card shadow">
        <div class="table-responsive">
            <table class="table table-hover" id="bankTable">
                <thead class="table-dark">
                    <tr>
                        <th onclick="sortTable(0)" style="cursor:pointer">Mkulima ⇅</th>
                        <th>Benki</th>
                        <th>Akaunti</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php
                    $stmt = $pdo->query("SELECT b.*, f.first_name, f.last_name FROM bank_details b JOIN farmers f ON b.farmer_id = f.id");
                    while($row = $stmt->fetch()):
                        $status_class = ($row['status'] == 'active') ? 'success' : 'secondary';
                    ?>
                    <tr>
                        <td><?= $row['first_name'] . " " . $row['last_name'] ?></td>
                        <td><?= $row['bank_name'] ?></td>
                        <td><?= $row['account_number'] ?></td>
                        <td><span class="badge bg-<?= $status_class ?>"><?= ucfirst($row['status']) ?></span></td>
                        <td>
                            <a href="admin_bank_logic.php?toggle_status=1&id=<?= $row['id'] ?>&current=<?= $row['status'] ?>" class="btn btn-sm btn-warning">Toggle</a>
                            <a href="admin_bank_logic.php?delete_id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Una uhakika?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// 1. Live Search Logic
function liveSearch() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let rows = document.querySelectorAll("#tableBody tr");
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}

// 2. Export PDF Logic (Inachukua data iliyopo kwenye table sasa hivi)
function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.text("Farmer Bank Details Report", 14, 15);
    doc.autoTable({ html: '#bankTable', margin: { top: 25 } });
    doc.save("Bank_Report.pdf");
}

// 3. Sorting Logic
function sortTable(n) {
    var table = document.getElementById("bankTable");
    var rows = Array.from(table.rows).slice(1);
    rows.sort((a, b) => a.cells[n].innerText.localeCompare(b.cells[n].innerText));
    rows.forEach(row => table.appendChild(row));
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>