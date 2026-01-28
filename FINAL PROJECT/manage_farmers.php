<?php
session_start();

// ----------------- CONFIG -----------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'agriculture';   
$DB_USER = 'root';
$DB_PASS = '';

$admin_id = (int)$_SESSION['admin_id'];

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

// ----------------- Helper: check if column exists (for soft delete) -----------------
function columnExists(PDO $pdo, $table, $column) {
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':schema' => $pdo->query('select database()')->fetchColumn(), ':table' => $table, ':column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}
$has_deleted_at = columnExists($pdo, 'farmers', 'deleted_at');

// ----------------- AJAX HANDLER -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    try {
        // 1. Search suggestions - returns array of rows
        if ($action === 'search_suggestions') {
            $q = trim($_POST['q'] ?? '');
            $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, phone FROM farmers
                                   WHERE (first_name LIKE :q OR middle_name LIKE :q OR last_name LIKE :q OR phone LIKE :q)
                                   ".($has_deleted_at ? "AND deleted_at IS NULL" : "")."
                                   ORDER BY created_at DESC LIMIT 8");
            $stmt->execute([':q' => "%$q%"]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        // 2. Fetch page (pagination + optional filter)
        if ($action === 'fetch_page') {
            $page = max(1, (int)($_POST['page'] ?? 1));
            $limit = (int)($_POST['limit'] ?? 8);
            $start = ($page - 1) * $limit;
            $filter = trim($_POST['filter'] ?? '');
            if ($filter !== '') {
                $like = "%$filter%";
                $stmt = $pdo->prepare("SELECT * FROM farmers
                    WHERE (first_name LIKE :f OR middle_name LIKE :f OR last_name LIKE :f OR phone LIKE :f OR email LIKE :f)
                    ".($has_deleted_at ? "AND deleted_at IS NULL" : "")."
                    ORDER BY id DESC LIMIT :s, :l");
                $stmt->bindValue(':f', $like, PDO::PARAM_STR);
                $stmt->bindValue(':s', $start, PDO::PARAM_INT);
                $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM farmers
                    WHERE (first_name LIKE :f OR middle_name LIKE :f OR last_name LIKE :f OR phone LIKE :f OR email LIKE :f)
                    ".($has_deleted_at ? "AND deleted_at IS NULL" : ""));
                $countStmt->execute([':f' => $like]);
                $total = (int)$countStmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM farmers ".($has_deleted_at ? "WHERE deleted_at IS NULL" : "")." ORDER BY id DESC LIMIT :s, :l");
                $stmt->bindValue(':s', $start, PDO::PARAM_INT);
                $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $total = (int)$pdo->query("SELECT COUNT(*) FROM farmers ".($has_deleted_at ? "WHERE deleted_at IS NULL" : ""))->fetchColumn();
            }
            echo json_encode(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
            exit;
        }

        // 3. Approve
        if ($action === 'approve') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("UPDATE farmers SET status = 'approved' WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Farmer approved']);
            exit;
        }

        // 4. Reject
        if ($action === 'reject') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("UPDATE farmers SET status = 'rejected' WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Farmer rejected']);
            exit;
        }

        // 5. Reset password (default '123456' => sha256)
        if ($action === 'reset_pass') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $newHash = hash('sha256', '123456');
            $stmt = $pdo->prepare("UPDATE users_login SET password = :p WHERE id = :id");
            $stmt->execute([':p' => $newHash, ':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Password reset to default (123456)']);
            exit;
        }

        // 6. Delete (tries soft delete if deleted_at exists)
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            if ($has_deleted_at) {
                $stmt = $pdo->prepare("UPDATE farmers SET deleted_at = NOW() WHERE id = :id");
                $stmt->execute([':id' => $id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM farmers WHERE id = :id");
                $stmt->execute([':id' => $id]);
            }
            echo json_encode(['success' => true, 'message' => 'Farmer deleted']);
            exit;
        }

        // 7. Fetch single farmer (for edit modal)
        if ($action === 'fetch_single') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, phone, email, status, location, soil_type, farm_size FROM farmers WHERE id = :id ".($has_deleted_at ? "AND deleted_at IS NULL" : ""));
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Not found');
            echo json_encode(['success' => true, 'data' => $row]);
            exit;
        }

        // 8. Update farmer (Edit profile) - Basic fields (A chosen)
        if ($action === 'update') {
            // expected fields: id, first_name, middle_name, last_name, phone, email
            $id = (int)($_POST['id'] ?? 0);
            $first = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($id <= 0) throw new Exception('Invalid id');
            if ($first === '' || $last === '' || $phone === '') throw new Exception('First name, last name and phone are required');

            $stmt = $pdo->prepare("UPDATE farmers SET first_name = :f, middle_name = :m, last_name = :l, phone = :p, email = :e WHERE id = :id");
            $stmt->execute([':f'=>$first, ':m'=>$middle, ':l'=>$last, ':p'=>$phone, ':e'=>$email, ':id'=>$id]);

            echo json_encode(['success' => true, 'message' => 'Farmer updated']);
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
$cntFarmers = (int)$pdo->query("SELECT COUNT(*) FROM farmers ".($has_deleted_at ? "WHERE deleted_at IS NULL" : ""))->fetchColumn();
$cntPending = (int)$pdo->query("SELECT COUNT(*) FROM farmers WHERE status = 'pending' ".($has_deleted_at ? "AND deleted_at IS NULL" : ""))->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Farmers — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
    --bg:#f4f6fb;--card:#fff;--primary:#1e88e5;--accent:#27ae60;--muted:#7b8a99;
    --shadow: 0 8px 24px rgba(14,30,37,0.08);
}
*{
    box-sizing:border-box;
    }body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:linear-gradient(135deg,#0b9348,#1e88e5);
    }
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:#0f1724;color:#fff;padding:20px 12px;box-shadow:2px 0 20px rgba(2,6,23,0.2)}
.brand{font-size:18px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.brand i{font-size:20px;color:var(--accent)}
.side-nav{margin-top:20px}
.side-nav a{display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;color:#cfe0ff;text-decoration:none;margin-bottom:6px;transition:all .18s}
.side-nav a:hover{background:rgba(255,255,255,0.03);transform:translateX(4px);color:#fff}
.side-nav a .badge{margin-left:auto;background:#2b394a;padding:4px 8px;border-radius:6px;font-size:12px}
.main{margin-left:240px;padding:24px}
.header{display:flex;justify-content:space-between;align-items:center;gap:12px}
.welcome{font-size:20px;font-weight:600}
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
.search-box{position:relative;width:360px}
.search-box input{width:100%;padding:11px 12px;border-radius:8px;border:1px solid #e6eef8;font-size:14px}
.suggestions{position:absolute;left:0;right:0;top:44px;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(9,30,66,0.08);overflow:hidden;display:none;z-index:50}
.suggestions p{margin:0;padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f4f8}
.suggestions p:hover{background:#f7fbff}

.table{width:100%;border-collapse:collapse;margin-top:14px}
.table thead th{text-align:left;padding:12px 10px;color:#5b6b77;font-size:13px}
.table tbody tr{border-top:1px solid #f1f5f9;cursor:pointer}
.table td{padding:12px 10px;vertical-align:middle}
.status{padding:6px 9px;border-radius:8px;font-weight:600;font-size:13px}
.status.pending{background:#fff7e6;color:#c77800}
.status.approved{background:#e6fbf0;color:#1f8f5a}
.status.rejected{background:#ffe6e6;color:#bf2f2f}
.actions{display:flex;gap:8px}
.icon-btn{border:none;padding:8px 10px;border-radius:8px;cursor:pointer;color:#fff;display:inline-flex;align-items:center;gap:8px}
.approve{background:var(--accent)}
.reject{background:#e74c3c}
.reset{background:#6c5ce7}
.del{background:#ff6b6b}
.view{background:#1e88e5}

.pager{display:flex;gap:8px;justify-content:center;margin-top:12px}
.pager button{background:#eef6ff;border:1px solid #dbeeff;padding:8px 10px;border-radius:8px;cursor:pointer}
.pager button.active{background:var(--primary);color:#fff}

.toast{position:fixed;right:22px;bottom:22px;background:#111;color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 10px 30px rgba(2,6,23,0.3);display:none;z-index:999}

/* Modal basic */
.modal-backdrop{position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:200}
.modal{background:#fff;border-radius:12px;padding:18px;width:520px;max-width:92%;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal h3{margin-top:0}
.modal .row{display:flex;gap:8px}
.modal label{font-size:13px;margin-top:8px;display:block}
.modal input{width:100%;padding:10px;border-radius:6px;border:1px solid #e6eef8}

/* small responsive */
@media (max-width:900px){.sidebar{display:none}.main{margin-left:20px}.search-box{width:100%}.module{flex-direction:column}.panel{width:100%}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="brand"><i class="fa fa-seedling"></i> AGRO-TRACE</div>
    <div class="side-nav">
        <a href="dashbord.php"><i class="fa fa-chart-line"></i> DASHBOARD <span class="badge"><?=$cntFarmers?></span></a>
        <a href="#" style="background:rgba(255,255,255,0.03)"><i class="fa fa-users"></i> MANAGE FARMERS <span class="badge"><?=$cntPending?> pending</span></a>
        <a href="manage_crops.php"><i class="fa fa-leaf"></i> MANAGE CROPS</a>
        <a href="advisories.php"><i class="fa fa-bullhorn"></i> ADVIS0RIES</a>
        <a href="manage_traceability.php"><i class="fa fa-barcode"></i> TRACEABILITY</a>
        <a href="logout.php"><i class="fa fa-power-off"></i> LOGOUT</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">
    <div class="header">
        <div>
            <div class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['admin_id'])?></strong></div>
            <div class="small-muted">Admin panel — Manage registered farmers</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:13px;color:var(--muted)">Today: <?=date('d M, Y')?></div>
        </div>
    </div>

    <!-- TOP CARDS -->
    <div class="cards">
        <div class="card">
            <div class="icon"><i class="fa fa-users"></i></div>
            <div class="info"><h3>Total Farmers</h3><h2><?=$cntFarmers?></h2></div>
        </div>
        <div class="card">
            <div class="icon"><i class="fa fa-user-clock"></i></div>
            <div class="info"><h3>Pending</h3><h2><?=$cntPending?></h2></div>
        </div>
        <div class="card">
            <div class="icon"><i class="fa fa-check-circle"></i></div>
            <div class="info"><h3>Quick Actions</h3><h2>Approve / Reset</h2></div>
        </div>
    </div>

    <!-- MODULE -->
    <div class="module">
        <!-- LEFT: table -->
        <div class="panel">
            <div class="search-row">
                <div>
                    <h3 style="margin:0 0 8px 0">FARMERS</h3>
                    <div class="small-muted">Search, approve, update and manage farmers</div>
                </div>

                <div style="display:flex;gap:12px;align-items:center">
                    <div class="search-box">
                        <input id="searchInput" placeholder="Search by name or phone...">
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

        <!-- RIGHT: info / help -->
        <div style="width:320px">
            <div class="panel">
                <h4 style="margin-top:0">Selected Farmer</h4>
                <div id="selectedInfo" style="min-height:140px;color:#556">Click a farmer row to view details here</div>
                <hr>
                <div style="font-size:13px;color:var(--muted)">Actions: Approve, Reject, Reset Password (default '123456'), Delete</div>
            </div>

            <div class="panel" style="margin-top:12px">
                <h4 style="margin-top:0">Quick Tips</h4>
                <ul style="padding-left:18px;color:var(--muted)">
                    <li>Use autocomplete to quickly find farmers.</li>
                    <li>Actions are instant and update the table without reload.</li>
                    <li>Default reset password is <b>123456</b> (sha256 hashed).</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<!-- Toast -->
<div id="toast" class="toast"></div>

<!-- Modals -->
<div id="modalBackdrop" class="modal-backdrop">
    <div class="modal" id="editModal">
        <center><h3>EDIT DETAILS</h3></center>
        <form id="editForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="" style="margin-bottom:8px">
                <div style="margin-bottom:8px">
                    <label>FIRST NAME</label>
                    <input type="text" name="first_name" id="edit_first" required>
                </div>
                <br>
                <div style="margin-bottom:8px">
                    <label>MIDDLE NAME</label>
                    <input type="text" name="middle_name" id="edit_middle">
                </div>
            </div>
            <br>
            <div style="margin-bottom:8px">
                <label>LAST NAME</label>
                <input type="text" name="last_name" id="edit_last" required>
            </div>
            <br>
            <div style="margin-bottom:8px">
                <label>PHONE</label>
                <input type="text" name="phone" id="edit_phone" required>
            </div>
            <br>
            <div style="margin-bottom:8px">
                <label>EMAIL</label>
                <input type="email" name="email" id="edit_email">
            </div>

            <div style="display:flex;gap:8px;margin-top:10px;justify-content:flex-end">
                <button type="button" class="icon-btn" onclick="closeModal()" style="background:#bdbdbd">Cancel</button>
                <button type="submit" class="icon-btn" style="background:var(--primary)"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirm delete modal -->
<div id="confirmBackdrop" class="modal-backdrop" style="display:none">
    <div class="modal" style="width:420px">
        <h3>Delete Farmer</h3>
        <p>Are you sure you want to delete this farmer? This action can be reversed only if system uses soft-delete. Otherwise it will be permanent.</p>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
            <button onclick="document.getElementById('confirmBackdrop').style.display='none'" class="icon-btn" style="background:#bdbdbd">Cancel</button>
            <button id="confirmDeleteBtn" class="icon-btn del">Delete</button>
        </div>
    </div>
</div>

<script>
// ----------------- Utilities -----------------
function showToast(msg, ok=true){
    const t = document.getElementById('toast');
    t.style.background = ok ? '#111' : '#b71c1c';
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(()=> t.style.display='none', 3200);
}
function qs(selector){ return document.querySelector(selector); }
function qsa(selector){ return Array.from(document.querySelectorAll(selector)); }
function escapeHtml(s){ return String(s||'').replace(/[&<>"'\/]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;'}[ch];}); }

// ----------------- State -----------------
let currentPage = 1;
let currentFilter = '';
let selectedId = null;

// ----------------- Load page (AJAX) -----------------
function loadPage(page=1, filter='') {
    currentPage = page;
    currentFilter = filter;
    const fd = new FormData();
    fd.append('action', 'fetch_page');
    fd.append('page', page);
    fd.append('filter', filter);
    fd.append('limit', 8);

    fetch(location.pathname, {method:'POST', body: fd})
    .then(r => r.json())
    .then(res => {
        if (!res.success) return showToast(res.message || 'Failed to load', false);
        renderTable(res.data);
        renderPager(res.total, res.page, res.limit);
    })
    .catch(()=> showToast('Server error', false));
}

// ----------------- Render table -----------------
function renderTable(rows) {
    const wrap = document.getElementById('tableWrap');
    if (!rows || rows.length === 0) {
        wrap.innerHTML = '<div style="padding:18px;color:var(--muted)">No farmers found.</div>';
        return;
    }
    let html = `<table class="table"><thead><tr>
        <th>#</th><th>FULL NAME</th><th>PHONE</th><th>TIME</th><th>STATUS</th><th>ACTIONS</th>
    </tr></thead><tbody>`;
    rows.forEach(r=>{
        const fullname = escapeHtml([r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' '));
        const status = r.status || 'pending';
        const statusClass = status === 'approved' ? 'approved' : (status === 'rejected' ? 'rejected' : 'pending');
        html += `<tr data-id="${r.id}" onclick="selectRow(event, ${r.id})">
            <td>${r.id}</td>
            <td>${fullname}</td>
            <td>${escapeHtml(r.phone||'')}</td>
            <td>${escapeHtml(r.created_at||'')}</td>
            <td><span class="status ${statusClass}">${status}</span></td>
            <td>
                <div class="actions">
                    <button class="icon-btn approve" onclick="event.stopPropagation(); actionReq('approve', ${r.id})" title="Approve"><i class="fa fa-check"></i></button>
                    <button class="icon-btn reject" onclick="event.stopPropagation(); actionReq('reject', ${r.id})" title="Reject"><i class="fa fa-xmark"></i></button>
                    <button class="icon-btn reset" onclick="event.stopPropagation(); actionReq('reset_pass', ${r.id})" title="Reset Password"><i class="fa fa-key"></i></button>
                    <button class="icon-btn view" onclick="event.stopPropagation(); openEdit(${r.id})" title="Edit"><i class="fa fa-pen"></i></button>
                    <button class="icon-btn del" onclick="event.stopPropagation(); openDelete(${r.id})" title="Delete"><i class="fa fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    });
    html += `</tbody></table>`;
    wrap.innerHTML = html;
}

// ----------------- Pager -----------------
function renderPager(total, page, limit) {
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

// ----------------- Actions (approve, reject, reset, delete) -----------------
function actionReq(action, id) {
    if (!confirm(`Confirm ${action} ?`)) return;
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        if (!res.success) return showToast(res.message || 'Action failed', false);
        showToast(res.message || 'Done');
        loadPage(currentPage, currentFilter);
        if (selectedId == id) loadSelected(selectedId);
    }).catch(()=> showToast('Server error', false));
}

// ----------------- Select row to show details -----------------
function selectRow(e, id) {
    selectedId = id;
    // fetch current page rows and find the id (simple approach)
    const fd = new FormData();
    fd.append('action', 'fetch_page');
    fd.append('page', currentPage);
    fd.append('filter', currentFilter);
    fd.append('limit', 8);
    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        if (!res.success) return;
        const found = res.data.find(x => parseInt(x.id) === parseInt(id));
        if (!found) { document.getElementById('selectedInfo').innerText = 'Not found'; return; }
        loadSelected(found);
    });
}
function loadSelected(row) {
    const name = escapeHtml([row.first_name,row.middle_name,row.last_name].filter(Boolean).join(' '));
    const html = `<div style="font-weight:600">${name}</div>
        <div style="margin-top:8px;color:var(--muted)">Phone: ${escapeHtml(row.phone||'')}</div>
        <div style="margin-top:6px;color:var(--muted)">Email: ${escapeHtml(row.email||'—')}</div>
        <div style="margin-top:6px;color:var(--muted)">Status: <span class="status ${row.status}">${row.status}</span></div>
        <div style="margin-top:10px">
            <button class="icon-btn approve" onclick="actionReq('approve', ${row.id})"><i class="fa fa-check"></i> Approve</button>
            <button class="icon-btn reset" onclick="actionReq('reset_pass', ${row.id})"><i class="fa fa-key"></i> Reset</button>
            <button class="icon-btn del" onclick="openDelete(${row.id})"><i class="fa fa-trash"></i> Delete</button>
        </div>`;
    document.getElementById('selectedInfo').innerHTML = html;
}

// ----------------- Autocomplete -----------------
const input = document.getElementById('searchInput');
let timer = null;
input.addEventListener('input', function(){
    const q = this.value.trim();
    const box = document.getElementById('suggestions');
    if (q.length < 1) { box.style.display = 'none'; return; }
    clearTimeout(timer);
    timer = setTimeout(()=> {
        const fd = new FormData();
        fd.append('action','search_suggestions');
        fd.append('q', q);
        fetch(location.pathname, {method:'POST', body: fd})
        .then(r=>r.json())
        .then(res=>{
            if (!res.success) { box.style.display='none'; return; }
            const rows = res.data;
            if (!rows || rows.length===0){ box.style.display='none'; return; }
            box.innerHTML = rows.map(r => `<p data-id="${r.id}" onclick="chooseSuggestion(${r.id}, '${escapeJs([r.first_name,r.middle_name,r.last_name].filter(Boolean).join(' '))}')">${escapeHtml([r.first_name,r.middle_name,r.last_name].filter(Boolean).join(' '))}<br><small style="color:#6b7a86">${escapeHtml(r.phone)}</small></p>`).join('');
            box.style.display = 'block';
        });
    }, 200);
});
function chooseSuggestion(id, name){
    document.getElementById('searchInput').value = name;
    document.getElementById('suggestions').style.display = 'none';
    currentFilter = name;
    loadPage(1, name);
}
function escapeJs(s){ return String(s||'').replace(/'/g,"\\'").replace(/"/g,'\\"'); }

// ----------------- Edit modal -----------------
function openEdit(id){
    const fd = new FormData();
    fd.append('action','fetch_single');
    fd.append('id', id);
    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        if (!res.success) return showToast(res.message||'Failed', false);
        const d = res.data;
        document.getElementById('edit_id').value = d.id;
        document.getElementById('edit_first').value = d.first_name || '';
        document.getElementById('edit_middle').value = d.middle_name || '';
        document.getElementById('edit_last').value = d.last_name || '';
        document.getElementById('edit_phone').value = d.phone || '';
        document.getElementById('edit_email').value = d.email || '';
        openModal();
    }).catch(()=> showToast('Server error', false));
}

function openModal(){
    document.getElementById('modalBackdrop').style.display = 'flex';
}
function closeModal(){
    document.getElementById('modalBackdrop').style.display = 'none';
}

// handle edit form submit
document.getElementById('editForm').addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action','update');
    fetch(location.pathname, {method:'POST', body: fd})
    .then(r=>r.json())
    .then(res=>{
        if (!res.success) return showToast(res.message || 'Update failed', false);
        showToast(res.message || 'Updated');
        closeModal();
        loadPage(currentPage, currentFilter);
        if (selectedId) loadSelected(selectedId);
    }).catch(()=> showToast('Server error', false));
});

// ----------------- Delete modal flow -----------------
let pendingDeleteId = null;
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
        if (!res.success) return showToast(res.message || 'Delete failed', false);
        showToast(res.message || 'Deleted');
        loadPage(currentPage, currentFilter);
        if (selectedId === id) { selectedId = null; document.getElementById('selectedInfo').innerHTML = 'Click a farmer row to view details here'; }
    }).catch(()=> { document.getElementById('confirmBackdrop').style.display = 'none'; showToast('Server error', false); });
});

// ----------------- Refresh
document.getElementById('refreshBtn').addEventListener('click', function(){ document.getElementById('searchInput').value=''; currentFilter=''; loadPage(1); });

// initial load
loadPage(1);

// close suggestions when clicking outside
document.addEventListener('click', function(e){
    if (!document.getElementById('searchInput').contains(e.target)) document.getElementById('suggestions').style.display='none';
    if (!document.getElementById('modalBackdrop').contains(e.target) && document.getElementById('modalBackdrop').style.display==='flex') {
        // clicking outside modal closes it
        // but ensure we don't close when clicking inside modal content
        if (e.target === document.getElementById('modalBackdrop')) closeModal();
    }
});
</script>

</body>
</html>
