<?php
session_start();

$DB_HOST = '127.0.0.1';
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
    echo "DB connection error: " . htmlspecialchars($e->getMessage());
    exit;
}

// ----------------- AJAX HANDLER -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    try {
        // 1. Farmer suggestions for Add modal (searchable autocomplete)
        if ($action === 'farmer_suggestions') {
            $q = trim($_POST['q'] ?? '');
            $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, phone FROM farmers
                                   WHERE (first_name LIKE :q OR middle_name LIKE :q OR last_name LIKE :q OR phone LIKE :q)
                                   ORDER BY created_at DESC LIMIT 8");
            $stmt->execute([':q' => "%$q%"]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        // 2. Fetch page (list crops with optional filter)
        if ($action === 'fetch_page') {
            $page = max(1, (int)($_POST['page'] ?? 1));
            $limit = (int)($_POST['limit'] ?? 8);
            $start = ($page - 1) * $limit;
            $filter = trim($_POST['filter'] ?? '');

            if ($filter !== '') {
                $like = "%$filter%";
                $stmt = $pdo->prepare("SELECT c.*, f.first_name, f.middle_name, f.last_name FROM crops c
                    LEFT JOIN farmers f ON f.id = c.farmer_id
                    WHERE (c.crop_type LIKE :f OR c.variety LIKE :f OR f.first_name LIKE :f OR f.last_name LIKE :f)
                    ORDER BY c.id DESC LIMIT :s, :l");
                $stmt->bindValue(':f', $like, PDO::PARAM_STR);
                $stmt->bindValue(':s', $start, PDO::PARAM_INT);
                $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM crops c
                    LEFT JOIN farmers f ON f.id = c.farmer_id
                    WHERE (c.crop_type LIKE :f OR c.variety LIKE :f OR f.first_name LIKE :f OR f.last_name LIKE :f)");
                $countStmt->execute([':f' => $like]);
                $total = (int)$countStmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare("SELECT c.*, f.first_name, f.middle_name, f.last_name FROM crops c
                    LEFT JOIN farmers f ON f.id = c.farmer_id
                    ORDER BY c.id DESC LIMIT :s, :l");
                $stmt->bindValue(':s', $start, PDO::PARAM_INT);
                $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $total = (int)$pdo->query("SELECT COUNT(*) FROM crops")->fetchColumn();
            }
            echo json_encode(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
            exit;
        }

        // 3. Add crop
        if ($action === 'add') {
            $farmer_id = (int)($_POST['farmer_id'] ?? 0);
            $crop_type = trim($_POST['crop_type'] ?? '');
            $variety = trim($_POST['variety'] ?? '');
            $planted_date = trim($_POST['planted_date'] ?? '');
            $expected = trim($_POST['expected_harvest'] ?? '');

            if ($farmer_id <= 0) throw new Exception('Select a farmer');
            if ($crop_type === '') throw new Exception('Crop type required');
            if ($planted_date === '') throw new Exception('Planted date required');

            $stmt = $pdo->prepare("INSERT INTO crops (farmer_id, crop_type, variety, planted_date, expected_harvest, created_at) VALUES (:farmer_id, :crop_type, :variety, :planted, :expected, NOW())");
            $stmt->execute([
                ':farmer_id' => $farmer_id,
                ':crop_type' => $crop_type,
                ':variety' => $variety,
                ':planted' => $planted_date,
                ':expected' => $expected !== '' ? $expected : null
            ]);

            echo json_encode(['success' => true, 'message' => 'Crop added']);
            exit;
        }

        // 4. Fetch single crop for edit
        if ($action === 'fetch_single') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("SELECT * FROM crops WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Not found');
            echo json_encode(['success' => true, 'data' => $row]);
            exit;
        }

        // 5. Update crop
        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $farmer_id = (int)($_POST['farmer_id'] ?? 0);
            $crop_type = trim($_POST['crop_type'] ?? '');
            $variety = trim($_POST['variety'] ?? '');
            $planted_date = trim($_POST['planted_date'] ?? '');
            $expected = trim($_POST['expected_harvest'] ?? '');

            if ($id <= 0) throw new Exception('Invalid id');
            if ($farmer_id <= 0) throw new Exception('Select a farmer');
            if ($crop_type === '') throw new Exception('Crop type required');

            $stmt = $pdo->prepare("UPDATE crops SET farmer_id = :farmer_id, crop_type = :crop_type, variety = :variety, planted_date = :planted, expected_harvest = :expected WHERE id = :id");
            $stmt->execute([
                ':farmer_id' => $farmer_id,
                ':crop_type' => $crop_type,
                ':variety' => $variety,
                ':planted' => $planted_date,
                ':expected' => $expected !== '' ? $expected : null,
                ':id' => $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Crop updated']);
            exit;
        }

        // 6. Delete crop
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("DELETE FROM crops WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Crop deleted']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ----------------- PAGE RENDER (GET) -----------------
// quick counters
$cntCrops = (int)$pdo->query("SELECT COUNT(*) FROM crops")->fetchColumn();
$cntFarmers = (int)$pdo->query("SELECT COUNT(*) FROM farmers")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Crops — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<!-- Poppins & FontAwesome -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root{
    --bg:#f4f6fb;
    --card:#fff;
    --primary:#1e88e5;
    --accent:#27ae60;
    --muted:#7b8a99;
    --shadow: 0 8px 24px rgba(14,30,37,0.08);
}
*{
    box-sizing:border-box;
    }body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:linear-gradient(135deg,#0b9348,#1e88e5);
    color:white;
    }
.sidebar{
    position:fixed;
    left:0;
    top:0;
    width:220px;
    height:100vh;
    background:#0f1724;
    color:#fff;
    padding:20px 12px;
    box-shadow:2px 0 20px rgba(2,6,23,0.2)
    }
.brand{
    font-size:18px;
    font-weight:600;
    margin-bottom:18px;
    display:flex;
    align-items:center;
    gap:10px;
    }
.brand i{
    font-size:20px;
    color:var(--accent)
    }
.side-nav{
    margin-top:20px;
    }
.side-nav a{
    display:flex;
    align-items:center;
    gap:12px;
    padding:10px;
    border-radius:8px;
    color:#cfe0ff;
    text-decoration:none;
    margin-bottom:6px;
    transition:all .18s;
    }
.side-nav a:hover{
    background:rgba(255,255,255,0.03);
    transform:translateX(4px);
    color:#fff;
    }
.side-nav a .badge{
    margin-left:auto;
    background:#2b394a;
    padding:4px 8px;
    border-radius:6px;
    font-size:12px
    }
.main{
    margin-left:240px;
    padding:24px;
    }
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    }
.welcome{
    font-size:20px;font-weight:600}
.small-muted{color:var(--muted);font-size:13px;margin-top:4px}
.cards{display:flex;gap:18px;margin-top:18px}
.card{flex:1;background:var(--card);padding:16px;border-radius:12px;box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;transition:transform .16s}
.card:hover{transform:translateY(-6px)}
.card .icon{width:56px;height:56px;border-radius:10px;background:linear-gradient(135deg,#e6f0ff,#fff);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--primary)}
.card .info h3{margin:0;font-size:14px;color:#666}
.card .info h2{margin:4px 0 0;font-size:22px;font-weight:700}

.module{margin-top:22px;display:flex;gap:20px;align-items:flex-start}
.panel{flex:1;background:var(--card);border-radius:12px;padding:16px;box-shadow:var(--shadow)}
.search-row{display:flex;justify-content:space-between;align-items:center;gap:12px}
.search-box{position:relative;width:420px}
.search-box input{width:100%;padding:11px 12px;border-radius:8px;border:1px solid #e6eef8;font-size:14px}
.suggestions{position:absolute;left:0;right:0;top:44px;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(9,30,66,0.08);overflow:hidden;display:none;z-index:50}
.suggestions p{margin:0;padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f4f8}
.suggestions p:hover{background:#f7fbff}

.table{
    width:100%;
    border-collapse:collapse;
    margin-top:14px;
    }
.table thead th{
    text-align:left;
    padding:12px 10px;
    color:#5b6b77;
    font-size:13px;
    }
.table tbody tr{
    border-top:1px solid #f1f5f9;
    cursor:pointer;
    }
.table td{
    padding:12px 10px;
    vertical-align:middle;
    color:black;
    }
.actions{
    display:flex;
    gap:8px;
    }
.icon-btn{border:none;padding:8px 10px;border-radius:8px;cursor:pointer;color:#fff;display:inline-flex;align-items:center;gap:8px}
.edit{background:#1e88e5}
.del{background:#ff6b6b}

.pager{display:flex;gap:8px;justify-content:center;margin-top:12px}
.pager button{background:#eef6ff;border:1px solid #dbeeff;padding:8px 10px;border-radius:8px;cursor:pointer}
.pager button.active{background:var(--primary);color:#fff}

.toast{position:fixed;right:22px;bottom:22px;background:#111;color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 10px 30px rgba(2,6,23,0.3);display:none;z-index:999}

/* modal */
.modal-backdrop{position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:200}
.modal{background:#fff;border-radius:12px;padding:18px;width:640px;max-width:96%;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal h3{margin-top:0}
.modal label{display:block;margin-top:8px}
.modal input, .modal select, .modal textarea{width:100%;padding:10px;border-radius:6px;border:1px solid #e6eef8;font-size:14px}
.row{display:flex;gap:8px}
.row .col{flex:1}

/* responsive */
@media (max-width:900px){.sidebar{display:none}.main{margin-left:20px}.module{flex-direction:column}.search-box{width:100%}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="brand"><i class="fa fa-seedling"></i> AGRO-TRACE</div>
    <div class="side-nav">
        <a href="dashbord.php"><i class="fa fa-chart-line"></i> Dashboard <span class="badge"><?=$cntCrops?></span></a>
        <a href="manage_farmers.php"><i class="fa fa-users"></i> Manage Farmers <span class="badge"><?=$cntFarmers?></span></a>
        <a href="#" style="background:rgba(255,255,255,0.03)"><i class="fa fa-leaf"></i> Manage Crops</a>
        <a href="advisories.php"><i class="fa fa-bullhorn"></i> Advisories</a>
        <a href="traceability.php"><i class="fa fa-barcode"></i> Traceability</a>
        <a href="logout.php"><i class="fa fa-power-off"></i> Logout</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">
    <div class="header">
        <div>
            <div class="welcome"> REGISTERED CROPS</div>
            <div class="small-muted">Add, edit, delete crops. Link each crop to a farmer.</div>
        </div>
        <div style="text-align:right">
            <button id="openAddBtn" class="icon-btn" style="background:var(--accent);color:#fff;padding:10px 12px;border-radius:8px"><i class="fa fa-plus"></i> Add Crop</button>
        </div>
    </div>

    <!-- CARDS -->
    <div class="cards" style="margin-top:16px">
        <div class="card"><div class="icon"><i class="fa fa-leaf"></i></div><div class="info"><h3>Total Crops</h3><h2><?=$cntCrops?></h2></div></div>
        <div class="card"><div class="icon"><i class="fa fa-users"></i></div><div class="info"><h3>Registered Farmers</h3><h2><?=$cntFarmers?></h2></div></div>
    </div>

    <!-- MODULE -->
    <div class="module">
        <div class="panel">
            <div class="search-row">
                <div>
                    <h3 style="margin:0 0 8px 0">CROPS</h3>
                    <div class="small-muted">Search crops by type, variety or farmer</div>
                </div>

                <div style="display:flex;gap:12px;align-items:center">
                    <div class="search-box">
                        <input id="searchInput" placeholder="Search crop type, variety or farmer...">
                        <div id="suggestions" class="suggestions"></div>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button id="refreshBtn" title="Refresh" class="icon-btn view"><i class="fa fa-arrows-rotate"></i></button>
                    </div>
                </div>
            </div>

            <div id="tableWrap" style="margin-top:12px"></div>
            <div class="pager" id="pager"></div>
        </div>

        <div style="width:340px">
            <div class="panel">
                <h4 style="margin-top:0">QUICK INFO</h4>
                <p style="color:var(--muted)">Adding a crop links it to a farmer and records planted/expected harvest dates. Use the "Add Crop" button to create a new entry. Use Edit to change details.</p>
            </div>

            <div class="panel" style="margin-top:12px">
                <h4 style="margin-top:0">FARMER PICKER</h4>
                <p style="color:var(--muted)">When adding/editing you can search farmers by typing a name or phone number. Select from suggestions.</p>
            </div>
        </div>
    </div>

</div>

<!-- Toast -->
<div id="toast" class="toast"></div>

<!-- Add/Edit Modal -->
<div id="modalBackdrop" class="modal-backdrop">
    <div class="modal" id="addEditModal">
        <h3 id="modalTitle">Add Crop</h3>
        <form id="cropForm">
            <input type="hidden" name="id" id="crop_id">
            <label>Farmer</label>
            <div style="position:relative">
                <input type="text" id="farmerPicker" placeholder="Type farmer name or phone..." autocomplete="off">
                <div id="farmerSuggestions" class="suggestions" style="top:40px"></div>
                <input type="hidden" name="farmer_id" id="farmer_id">
            </div>

            <label>Crop Type</label>
            <input type="text" name="crop_type" id="crop_type" required>

            <label>Variety</label>
            <input type="text" name="variety" id="variety">

            <div class="row">
                <div class="col">
                    <label>Planted Date</label>
                    <input type="date" name="planted_date" id="planted_date" required>
                </div>
                <div class="col">
                    <label>Expected Harvest</label>
                    <input type="date" name="expected_harvest" id="expected_harvest">
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                <button type="button" class="icon-btn" onclick="closeModal()" style="background:#bdbdbd">Cancel</button>
                <button type="submit" class="icon-btn" style="background:var(--primary)"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirm -->
<div id="confirmBackdrop" class="modal-backdrop" style="display:none">
    <div class="modal" style="width:420px">
        <h3>Delete Crop</h3>
        <p>Are you sure you want to delete this crop record? This action cannot be undone.</p>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
            <button onclick="document.getElementById('confirmBackdrop').style.display='none'" class="icon-btn" style="background:#bdbdbd">Cancel</button>
            <button id="confirmDeleteBtn" class="icon-btn del">Delete</button>
        </div>
    </div>
</div>

<script>
// Utilities
function showToast(msg, ok=true){
    const t = document.getElementById('toast');
    t.style.background = ok ? '#111' : '#b71c1c';
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(()=> t.style.display='none', 3000);
}
function qs(s){ return document.querySelector(s); }
function qsa(s){ return Array.from(document.querySelectorAll(s)); }
function escapeHtml(s){ return String(s||'').replace(/[&<>"'\/]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;'}[ch];}); }

// State
let currentPage = 1;
let currentFilter = '';
let pendingDeleteId = null;

// Load page (AJAX)
function loadPage(page=1, filter=''){
    currentPage = page;
    currentFilter = filter;
    const fd = new FormData();
    fd.append('action','fetch_page');
    fd.append('page', page);
    fd.append('filter', filter);
    fd.append('limit', 8);
    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        if (!res.success) return showToast(res.message||'Failed to load', false);
        renderTable(res.data);
        renderPager(res.total, res.page, res.limit);
    }).catch(()=> showToast('Server error', false));
}

// Render table
function renderTable(rows){
    const wrap = document.getElementById('tableWrap');
    if (!rows || rows.length===0) { wrap.innerHTML = '<div style="padding:16px;color:#777">No crops found.</div>'; return; }
    let html = `<table class="table"><thead><tr>
        <th>#</th><th>CROP TYPE</th><th>VARIETY</th><th>FARMER</th><th>PLANTED</th><th>EXPECTED</th><th>ACTIONS</th>
    </tr></thead><tbody>`;
    rows.forEach(r=>{
        const farmer = escapeHtml([r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' '));
        html += `<tr data-id="${r.id}">
            <td>${r.id}</td>
            <td>${escapeHtml(r.crop_type)}</td>
            <td>${escapeHtml(r.variety||'—')}</td>
            <td>${farmer || '—'}</td>
            <td>${escapeHtml(r.planted_date||'—')}</td>
            <td>${escapeHtml(r.expected_harvest||'—')}</td>
            <td>
                <div class="actions">
                    <button class="icon-btn edit" onclick="openEdit(${r.id})" title="Edit"><i class="fa fa-pen"></i></button>
                    <button class="icon-btn del" onclick="openDelete(${r.id})" title="Delete"><i class="fa fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    });
    html += `</tbody>
    </table>`;
    wrap.innerHTML = html;
}

// Pager
function renderPager(total, page, limit){
    const pager = document.getElementById('pager');
    pager.innerHTML = '';
    const pages = Math.max(1, Math.ceil(total/limit));
    for (let i=1;i<=pages;i++){
        const b = document.createElement('button');
        b.textContent = i;
        if (i===page) b.classList.add('active');
        b.onclick = ()=> loadPage(i, currentFilter);
        pager.appendChild(b);
    }
}

// Add/Edit modal
const modalBackdrop = document.getElementById('modalBackdrop');
const addEditModal = document.getElementById('addEditModal');
const cropForm = document.getElementById('cropForm');

document.getElementById('openAddBtn').addEventListener('click', function(){
    openAdd();
});

function openAdd(){
    document.getElementById('modalTitle').innerText = 'Add Crop';
    cropForm.reset();
    document.getElementById('crop_id').value = '';
    document.getElementById('farmer_id').value = '';
    document.getElementById('farmerPicker').value = '';
    modalBackdrop.style.display = 'flex';
}

function closeModal(){ modalBackdrop.style.display = 'none'; }

// Farmer picker (autocomplete)
const farmerPicker = document.getElementById('farmerPicker');
const farmerSuggestions = document.getElementById('farmerSuggestions');
let farmerTimer = null;

farmerPicker.addEventListener('input', function(){
    const q = this.value.trim();
    if (q.length < 1) { farmerSuggestions.style.display = 'none'; return; }
    clearTimeout(farmerTimer);
    farmerTimer = setTimeout(()=> {
        const fd = new FormData();
        fd.append('action','farmer_suggestions');
        fd.append('q', q);
        fetch(location.pathname, {method:'POST', body: fd})
        .then(r=>r.json())
        .then(res=>{
            if (!res.success) { farmerSuggestions.style.display = 'none'; return; }
            const rows = res.data;
            if (!rows || rows.length===0){ farmerSuggestions.style.display='none'; return; }
            farmerSuggestions.innerHTML = rows.map(r=>`<p data-id="${r.id}" onclick="chooseFarmer(${r.id}, '${escapeJs([r.first_name,r.middle_name,r.last_name].filter(Boolean).join(' '))}')">${escapeHtml([r.first_name,r.middle_name,r.last_name].filter(Boolean).join(' '))}<br><small style="color:#6b7a86">${escapeHtml(r.phone)}</small></p>`).join('');
            farmerSuggestions.style.display = 'block';
        });
    }, 180);
});
function chooseFarmer(id, name){
    document.getElementById('farmer_id').value = id;
    farmerPicker.value = name;
    farmerSuggestions.style.display = 'none';
}
function escapeJs(s){ return String(s||'').replace(/'/g,"\\'").replace(/"/g,'\\"'); }

// Submit add/update form
cropForm.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(cropForm);
    const id = fd.get('id');
    fd.append('action', id ? 'update' : 'add');

    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        if (!res.success) return showToast(res.message||'Save failed', false);
        showToast(res.message||'Saved');
        closeModal();
        loadPage(currentPage, currentFilter);
    }).catch(()=> showToast('Server error', false));
});

// Open edit
function openEdit(id){
    const fd = new FormData();
    fd.append('action','fetch_single');
    fd.append('id', id);
    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        if (!res.success) return showToast(res.message||'Failed', false);
        const d = res.data;
        document.getElementById('modalTitle').innerText = 'Edit Crop';
        document.getElementById('crop_id').value = d.id;
        document.getElementById('crop_type').value = d.crop_type || '';
        document.getElementById('variety').value = d.variety || '';
        document.getElementById('planted_date').value = d.planted_date || '';
        document.getElementById('expected_harvest').value = d.expected_harvest || '';
        // fetch farmer name for farmerPicker
        // Try quick fetch from server for farmer name
        (function fetchFarmerName(fid){
            if (!fid) { document.getElementById('farmerPicker').value = ''; document.getElementById('farmer_id').value = ''; return; }
            // small inline fetch using farmer_suggestions q by id (we'll query DB properly by using a separate AJAX request)
            const ffd = new FormData();
            ffd.append('action','farmer_suggestions');
            ffd.append('q', ''); // fetch top results - but better to fetch specific id; our endpoint doesn't accept id; we can build farmer name from DB via fetch of farmers table using PHP on page render
            // Instead, do a simple synchronous fetch using a small endpoint: use existing farmer_suggestions and then find id
            fetch(location.pathname, {method:'POST', body: ffd})
            .then(r=>r.json())
            .then(rr=>{
                if (!rr.success) return;
                const found = rr.data.find(x=>parseInt(x.id)===parseInt(fid));
                if (found) chooseFarmer(found.id, [found.first_name,found.middle_name,found.last_name].filter(Boolean).join(' '));
                else { document.getElementById('farmerPicker').value = ''; document.getElementById('farmer_id').value = fid; }
            });
        })(d.farmer_id);

        modalBackdrop.style.display = 'flex';
    }).catch(()=> showToast('Server error', false));
}

// Delete flow
function openDelete(id){
    pendingDeleteId = id;
    document.getElementById('confirmBackdrop').style.display = 'flex';
}
document.getElementById('confirmDeleteBtn').addEventListener('click', function(){
    const id = pendingDeleteId;
    if (!id) return;
    const fd = new FormData();
    fd.append('action','delete');
    fd.append('id', id);
    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        document.getElementById('confirmBackdrop').style.display = 'none';
        if (!res.success) return showToast(res.message||'Delete failed', false);
        showToast(res.message||'Deleted');
        loadPage(currentPage, currentFilter);
    }).catch(()=> { document.getElementById('confirmBackdrop').style.display = 'none'; showToast('Server error', false); });
});

// Search suggestions for crops (populate table by clicking suggestion)
const searchInput = document.getElementById('searchInput');
const suggestionsBox = document.getElementById('suggestions');
let searchTimer = null;
searchInput.addEventListener('input', function(){
    const q = this.value.trim();
    if (q.length < 1) { suggestionsBox.style.display = 'none'; return; }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(()=> {
        // We'll reuse fetch_page with filter by setting currentFilter and loading page 1
        currentFilter = q;
        loadPage(1, q);
        // Optionally we can show suggestions UI by fetching limited results; for simplicity the table will show filtered rows
        suggestionsBox.style.display = 'none';
    }, 180);
});

// refresh
document.getElementById('refreshBtn').addEventListener('click', function(){ document.getElementById('searchInput').value=''; currentFilter=''; loadPage(1); });

// initial load
loadPage(1);

// close dropdowns when clicking outside
document.addEventListener('click', function(e){
    if (!document.getElementById('searchInput').contains(e.target)) suggestionsBox.style.display='none';
    if (!farmerPicker.contains(e.target)) farmerSuggestions.style.display='none';
    if (e.target === modalBackdrop) closeModal();
});
</script>

</body>
</html>
