<?php

session_start();
$admin_id = (int)$_SESSION['admin_id'];
// ----------------- CONFIG -----------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'agriculture'; // change if needed
$DB_USER = 'root';
$DB_PASS = '';

// ----------------- PDO -----------------
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

// ----------------- Helpers -----------------
function tableExists(PDO $pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :table");
    $stmt->execute([':db' => $pdo->query('select database()')->fetchColumn(), ':table' => $table]);
    return (bool)$stmt->fetchColumn();
}
$has_notifications = tableExists($pdo, 'notifications');
$has_advisory = tableExists($pdo, 'advisory_messages');
$has_diseases = tableExists($pdo, 'disease_alerts');

// ----------------- AJAX HANDLER -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    try {
        // ---------- DISEASE ALERTS ----------
        if ($action === 'disease_add') {
            $disease_name = trim($_POST['disease_name'] ?? '');
            $crop_type = trim($_POST['crop_type'] ?? '');
            $region = trim($_POST['region'] ?? '');
            $advisory_message = trim($_POST['advisory_message'] ?? '');
            $severity = in_array($_POST['severity'] ?? '', ['Low','Medium','High']) ? $_POST['severity'] : 'Medium';
            $reported_at = $_POST['reported_at'] ?? date('Y-m-d');

            if ($disease_name === '' || $crop_type === '' || $region === '') throw new Exception('Disease, crop and region are required');

            $stmt = $pdo->prepare("INSERT INTO disease_alerts (disease_name, crop_type, region, advisory_message, severity, reported_at, created_at) VALUES (:dn, :ct, :reg, :am, :sev, :rep, NOW())");
            $stmt->execute([':dn'=>$disease_name, ':ct'=>$crop_type, ':reg'=>$region, ':am'=>$advisory_message, ':sev'=>$severity, ':rep'=>$reported_at]);

            echo json_encode(['success'=>true, 'message'=>'Disease alert added']);
            exit;
        }

        if ($action === 'disease_list') {
            $page = max(1, (int)($_POST['page'] ?? 1));
            $limit = (int)($_POST['limit'] ?? 8);
            $start = ($page -1) * $limit;
            $filter = trim($_POST['filter'] ?? '');
            if ($filter !== '') {
                $like = "%$filter%";
                $stmt = $pdo->prepare("SELECT * FROM disease_alerts WHERE disease_name LIKE :f OR crop_type LIKE :f OR region LIKE :f ORDER BY id DESC LIMIT :s, :l");
                $stmt->bindValue(':f',$like, PDO::PARAM_STR);
                $stmt->bindValue(':s',$start,PDO::PARAM_INT);
                $stmt->bindValue(':l',$limit,PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $count = $pdo->prepare("SELECT COUNT(*) FROM disease_alerts WHERE disease_name LIKE :f OR crop_type LIKE :f OR region LIKE :f");
                $count->execute([':f'=>$like]);
                $total = (int)$count->fetchColumn();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM disease_alerts ORDER BY id DESC LIMIT :s, :l");
                $stmt->bindValue(':s',$start,PDO::PARAM_INT);
                $stmt->bindValue(':l',$limit,PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total = (int)$pdo->query("SELECT COUNT(*) FROM disease_alerts")->fetchColumn();
            }
            echo json_encode(['success'=>true,'data'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit]);
            exit;
        }

        if ($action === 'disease_delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("DELETE FROM disease_alerts WHERE id = :id");
            $stmt->execute([':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'Disease alert deleted']);
            exit;
        }

        // ---------- ADVISORIES ----------
        if ($action === 'advisory_add') {
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $target_type = trim($_POST['target_type'] ?? 'all'); // all|region|crop|disease|farmer
            $target_value = trim($_POST['target_value'] ?? '');
            $disease_id = (int)($_POST['disease_id'] ?? 0);

            if ($title === '' || $message === '') throw new Exception('Title and message required');

            // insert advisory
            $stmt = $pdo->prepare("INSERT INTO advisory_messages (title, message, target_type, target_value, disease_id, created_by, created_at) VALUES (:t,:m,:tt,:tv,:did,:cb,NOW())");
            $stmt->execute([':t'=>$title, ':m'=>$message, ':tt'=>$target_type, ':tv'=>$target_value ?: null, ':did'=>$disease_id > 0 ? $disease_id : null, ':cb'=>$_SESSION['admin']]);

            $advisoryId = (int)$pdo->lastInsertId();

            // send in-app notifications if table exists
            if ($has_notifications) {
                // determine farmer ids matching target
                $farmerIds = [];
                if ($target_type === 'all') {
                    $farmerIds = $pdo->query("SELECT id FROM farmers")->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($target_type === 'region') {
                    $stmt = $pdo->prepare("SELECT id FROM farmers WHERE location LIKE :loc");
                    $stmt->execute([':loc'=>"%{$target_value}%"]);
                    $farmerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($target_type === 'crop') {
                    $stmt = $pdo->prepare("SELECT DISTINCT f.id FROM farmers f JOIN crops c ON c.farmer_id = f.id WHERE c.crop_type LIKE :c");
                    $stmt->execute([':c'=>"%{$target_value}%"]);
                    $farmerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($target_type === 'disease') {
                    // disease -> find disease_alert by id or by name
                    if ($disease_id > 0) {
                        // fetch disease record
                        $dd = $pdo->prepare("SELECT crop_type, region FROM disease_alerts WHERE id = :id");
                        $dd->execute([':id'=>$disease_id]);
                        $rec = $dd->fetch(PDO::FETCH_ASSOC);
                        if ($rec) {
                            $stmt = $pdo->prepare("SELECT DISTINCT f.id FROM farmers f JOIN crops c ON c.farmer_id = f.id WHERE c.crop_type LIKE :c AND f.location LIKE :loc");
                            $stmt->execute([':c'=>"%{$rec['crop_type']}%", ':loc'=>"%{$rec['region']}%"]);
                            $farmerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        }
                    } else {
                        // fallback: target_value might be disease name
                        $stmt = $pdo->prepare("SELECT crop_type, region FROM disease_alerts WHERE disease_name LIKE :d LIMIT 1");
                        $stmt->execute([':d'=>"%{$target_value}%"]);
                        $rec = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($rec) {
                            $stmt2 = $pdo->prepare("SELECT DISTINCT f.id FROM farmers f JOIN crops c ON c.farmer_id = f.id WHERE c.crop_type LIKE :c AND f.location LIKE :loc");
                            $stmt2->execute([':c'=>"%{$rec['crop_type']}%", ':loc'=>"%{$rec['region']}%"]);
                            $farmerIds = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                        }
                    }
                } elseif ($target_type === 'farmer') {
                    $fid = (int)$target_value;
                    if ($fid > 0) $farmerIds = [$fid];
                }

                // insert notifications
                $ins = $pdo->prepare("INSERT INTO notifications (farmer_id, advisory_id, message, sent_via, status, created_at) VALUES (:fid, :aid, :msg, 'in-app', 'sent', NOW())");
                foreach ($farmerIds as $fid) {
                    $ins->execute([':fid'=>$fid, ':aid'=>$advisoryId, ':msg'=>$message]);
                }
            }

            echo json_encode(['success'=>true,'message'=>'Advisory created and notifications sent (if applicable)']);
            exit;
        }

        if ($action === 'advisory_list') {
            $page = max(1, (int)($_POST['page'] ?? 1));
            $limit = (int)($_POST['limit'] ?? 8);
            $start = ($page -1) * $limit;
            $filter = trim($_POST['filter'] ?? '');
            $date_from = trim($_POST['date_from'] ?? '');
            $date_to = trim($_POST['date_to'] ?? '');
            $crop_filter = trim($_POST['crop_filter'] ?? '');
            $region_filter = trim($_POST['region_filter'] ?? '');
            $disease_filter = trim($_POST['disease_filter'] ?? '');

            // Base query with optional WHERE clauses
            $where = [];
            $params = [];

            if ($filter !== '') {
                $where[] = "(title LIKE :f OR message LIKE :f OR target_type LIKE :f OR target_value LIKE :f)";
                $params[':f'] = "%$filter%";
            }
            if ($date_from !== '') {
                $where[] = "created_at >= :df";
                $params[':df'] = $date_from . " 00:00:00";
            }
            if ($date_to !== '') {
                $where[] = "created_at <= :dt";
                $params[':dt'] = $date_to . " 23:59:59";
            }
            if ($crop_filter !== '') {
                $where[] = "(EXISTS (SELECT 1 FROM crops c WHERE c.crop_type LIKE :crop AND c.farmer_id = farmers_temp.id))";
                // we'll join farmers via subquery - but simpler approach: fetch advisories then filter client-side by crop (to keep SQL simple)
                // To keep server performant we ignore crop_filter here and let client search message text for crop mentions.
            }
            // Simple path: just do basic filter and pagination on advisory_messages
            if (!empty($where)) {
                $sql = "SELECT * FROM advisory_messages WHERE " . implode(" AND ", $where) . " ORDER BY id DESC LIMIT :s, :l";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
                $stmt->bindValue(':s',$start,PDO::PARAM_INT);
                $stmt->bindValue(':l',$limit,PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // count
                $countSql = "SELECT COUNT(*) FROM advisory_messages WHERE " . implode(" AND ", $where);
                $countStmt = $pdo->prepare($countSql);
                foreach ($params as $k=>$v) $countStmt->bindValue($k, $v);
                $countStmt->execute();
                $total = (int)$countStmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM advisory_messages ORDER BY id DESC LIMIT :s, :l");
                $stmt->bindValue(':s',$start,PDO::PARAM_INT);
                $stmt->bindValue(':l',$limit,PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total = (int)$pdo->query("SELECT COUNT(*) FROM advisory_messages")->fetchColumn();
            }

            echo json_encode(['success'=>true,'data'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit]);
            exit;
        }

        if ($action === 'advisory_fetch_single') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("SELECT * FROM advisory_messages WHERE id = :id");
            $stmt->execute([':id'=>$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Not found');
            echo json_encode(['success'=>true,'data'=>$row]);
            exit;
        }

        if ($action === 'advisory_update') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $target_type = trim($_POST['target_type'] ?? 'all');
            $target_value = trim($_POST['target_value'] ?? '');
            $disease_id = (int)($_POST['disease_id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            if ($title === '' || $message === '') throw new Exception('Title and message required');
            $stmt = $pdo->prepare("UPDATE advisory_messages SET title = :t, message = :m, target_type = :tt, target_value = :tv, disease_id = :did WHERE id = :id");
            $stmt->execute([':t'=>$title,':m'=>$message,':tt'=>$target_type,':tv'=>$target_value?:null,':did'=>$disease_id>0?$disease_id:null,':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'Advisory updated']);
            exit;
        }

        if ($action === 'advisory_delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("DELETE FROM advisory_messages WHERE id = :id");
            $stmt->execute([':id'=>$id]);
            if ($has_notifications) $pdo->prepare("DELETE FROM notifications WHERE advisory_id = :id")->execute([':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'Advisory deleted']);
            exit;
        }

        // ---------- AUTOCOMPLETE: farmers / crops / diseases ----------
        if ($action === 'farmer_suggestions') {
            $q = trim($_POST['q'] ?? '');
            $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, phone, location FROM farmers WHERE (first_name LIKE :q OR middle_name LIKE :q OR last_name LIKE :q OR phone LIKE :q) ORDER BY created_at DESC LIMIT 8");
            $stmt->execute([':q'=>"%$q%"]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$rows]);
            exit;
        }

        if ($action === 'crop_suggestions') {
            $q = trim($_POST['q'] ?? '');
            $stmt = $pdo->prepare("SELECT DISTINCT crop_type FROM crops WHERE crop_type LIKE :q LIMIT 8");
            $stmt->execute([':q'=>"%$q%"]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success'=>true,'data'=>$rows]);
            exit;
        }

        if ($action === 'disease_suggestions') {
            $q = trim($_POST['q'] ?? '');
            $stmt = $pdo->prepare("SELECT id, disease_name, crop_type, region FROM disease_alerts WHERE disease_name LIKE :q OR crop_type LIKE :q OR region LIKE :q LIMIT 8");
            $stmt->execute([':q'=>"%$q%"]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$rows]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);
    exit;
}

// ----------------- PAGE RENDER (GET) -----------------
$cntAdvisories = $has_advisory ? (int)$pdo->query("SELECT COUNT(*) FROM advisory_messages")->fetchColumn() : 0;
$cntDiseases = $has_diseases ? (int)$pdo->query("SELECT COUNT(*) FROM disease_alerts")->fetchColumn() : 0;
$cntFarmers = (int)$pdo->query("SELECT COUNT(*) FROM farmers")->fetchColumn();
$cntCrops = (int)$pdo->query("SELECT COUNT(DISTINCT crop_type) FROM crops")->fetchColumn();

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Advanced Advisory Management — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#f4f6fb;--card:#fff;--primary:#1e88e5;--accent:#27ae60;--muted:#7b8a99;--shadow:0 8px 24px rgba(14,30,37,0.08)}
*{box-sizing:border-box}body{margin:0;font-family:'Poppins',sans-serif;background:var(--bg);color:#222}
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:#0f1724;color:#fff;padding:20px 12px;box-shadow:2px 0 20px rgba(2,6,23,0.2)}
.brand{font-size:18px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.brand i{font-size:20px;color:var(--accent)}
.side-nav{margin-top:20px}
.side-nav a{display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;color:#cfe0ff;text-decoration:none;margin-bottom:6px;transition:all .18s}
.side-nav a:hover{background:rgba(255,255,255,0.03);transform:translateX(4px);color:#fff}
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
.search-box{position:relative;width:420px}
.search-box input{width:100%;padding:11px 12px;border-radius:8px;border:1px solid #e6eef8;font-size:14px}
.suggestions{position:absolute;left:0;right:0;top:44px;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(9,30,66,0.08);overflow:hidden;display:none;z-index:50}
.suggestions p{margin:0;padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f4f8}
.suggestions p:hover{background:#f7fbff}
.table{width:100%;border-collapse:collapse;margin-top:14px}
.table thead th{text-align:left;padding:12px 10px;color:#5b6b77;font-size:13px}
.table tbody tr{border-top:1px solid #f1f5f9;cursor:pointer}
.table td{padding:12px 10px;vertical-align:middle}
.actions{display:flex;gap:8px}
.icon-btn{border:none;padding:8px 10px;border-radius:8px;cursor:pointer;color:#fff;display:inline-flex;align-items:center;gap:8px}
.edit{background:#1e88e5}
.del{background:#ff6b6b}
.pager{display:flex;gap:8px;justify-content:center;margin-top:12px}
.pager button{background:#eef6ff;border:1px solid #dbeeff;padding:8px 10px;border-radius:8px;cursor:pointer}
.pager button.active{background:var(--primary);color:#fff}
.toast{position:fixed;right:22px;bottom:22px;background:#111;color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 10px 30px rgba(2,6,23,0.3);display:none;z-index:999}

/* modal */
.modal-backdrop{position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:200}
.modal{background:#fff;border-radius:12px;padding:18px;width:780px;max-width:96%;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal h3{margin-top:0}
.modal label{display:block;margin-top:8px}
.modal input, .modal select, .modal textarea{width:100%;padding:10px;border-radius:6px;border:1px solid #e6eef8;font-size:14px}
.row{display:flex;gap:8px}
.col{flex:1}
@media (max-width:900px){.sidebar{display:none}.main{margin-left:20px}.module{flex-direction:column}.search-box{width:100%}}
</style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fa fa-seedling"></i> AGRO-TRACE</div>
    <div class="side-nav">
        <a href="dashbord.php"><i class="fa fa-chart-line"></i> Dashboard</a>
        <a href="manage_farmers.php"><i class="fa fa-users"></i> Farmers</a>
        <a href="manage_crops.php"><i class="fa fa-leaf"></i> Crops</a>
        <a href="#" style="background:rgba(255,255,255,0.03)"><i class="fa fa-bullhorn"></i> Advisories <span class="badge"><?=$cntAdvisories?></span></a>
        <a href="traceability.php"><i class="fa fa-barcode"></i> Traceability</a>
        <a href="logout.php"><i class="fa fa-power-off"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="header">
        <div>
            <div class="welcome">ADVISORY MANAGEMENT</div>
            <div class="small-muted">Create targeted advisories and manage disease alerts</div>
        </div>
        <div style="text-align:right">
            <button id="openAdvisoryBtn" class="icon-btn" style="background:var(--accent);color:#fff;padding:10px 12px;border-radius:8px"><i class="fa fa-plus"></i> New Advisory</button>
            <button id="openDiseaseBtn" class="icon-btn" style="background:#ffb74d;color:#111;padding:10px 12px;border-radius:8px;margin-left:8px"><i class="fa fa-virus"></i> New Disease Alert</button>
        </div>
    </div>

    <div class="cards" style="margin-top:16px">
        <div class="card"><div class="icon"><i class="fa fa-bullhorn"></i></div><div class="info"><h3>Total Advisories</h3><h2><?=$cntAdvisories?></h2></div></div>
        <div class="card"><div class="icon"><i class="fa fa-virus"></i></div><div class="info"><h3>Disease Alerts</h3><h2><?=$cntDiseases?></h2></div></div>
        <div class="card"><div class="icon"><i class="fa fa-users"></i></div><div class="info"><h3>Farmers</h3><h2><?=$cntFarmers?></h2></div></div>
    </div>

    <div class="module" style="margin-top:18px">
        <div class="panel">
            <div class="search-row">
                <div>
                    <h3 style="margin:0 0 8px 0">ADVISORY HISTORY</h3>
                    <div class="small-muted">Filter by date, crop, region or disease</div>
                </div>

                <div style="display:flex;gap:12px;align-items:center">
                    <div style="display:flex;gap:8px;align-items:center">
                        <input id="filter_from" type="date" style="padding:8px;border-radius:6px;border:1px solid #e6eef8">
                        <input id="filter_to" type="date" style="padding:8px;border-radius:6px;border:1px solid #e6eef8">
                        <input id="filter_q" placeholder="Search text..." style="padding:9px;border-radius:6px;border:1px solid #e6eef8">
                    </div>
                    <div style="display:flex;gap:8px">
                        <input id="filter_crop" placeholder="Crop filter" style="padding:9px;border-radius:6px;border:1px solid #e6eef8">
                        <input id="filter_region" placeholder="Region filter" style="padding:9px;border-radius:6px;border:1px solid #e6eef8">
                        <button id="applyFilters" class="icon-btn" style="background:var(--primary)"><i class="fa fa-filter"></i></button>
                    </div>
                </div>
            </div>

            <div id="tableWrap" style="margin-top:12px"></div>
            <div class="pager" id="pager"></div>
        </div>

        <div style="width:360px">
            <div class="panel">
                <h4 style="margin-top:0">DISEASE ALERTS</h4>
                <div id="diseaseList" style="max-height:320px;overflow:auto"></div>
            </div>

            <div class="panel" style="margin-top:12px">
                <h4 style="margin-top:0">QUICK ACTIONS</h4>
                <p style="color:var(--muted)">Use disease alerts to quickly target advisories to farmers growing affected crops in the affected region.</p>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<!-- Modals: Advisory -->
<div id="modalBackdrop" class="modal-backdrop">
    <div class="modal" id="advisoryModal">
        <h3 id="advisoryModalTitle">NEW ADVISORY</h3>
        <form id="advForm">
            <input type="hidden" name="id" id="adv_id">
            <label>TITLE</label>
            <input type="text" name="title" id="adv_title" required>
            <label>MESSAGE</label>
            <textarea name="message" id="adv_message" rows="4" required style="resize:vertical"></textarea>
            <label>TARGET TYPE</label>
            <select name="target_type" id="adv_target_type">
                <option value="all">All farmers</option>
                <option value="region">By region</option>
                <option value="crop">By crop</option>
                <option value="disease">By disease alert</option>
                <option value="farmer">Specific farmer</option>
            </select>

            <div id="adv_target_controls" style="margin-top:8px"></div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                <button type="button" class="icon-btn" onclick="closeAdvModal()" style="background:#bdbdbd">Cancel</button>
                <button type="submit" class="icon-btn" style="background:var(--accent)"><i class="fa fa-paper-plane"></i> Save & Send</button>
            </div>
        </form>
    </div>
</div>

<!-- Modals: Disease -->
<div id="dModalBackdrop" class="modal-backdrop" style="display:none">
    <div class="modal" id="diseaseModal">
        <h3>New Disease Alert</h3>
        <form id="dForm">
            <label>Disease name</label>
            <input type="text" id="d_name" required>
            <label>Crop type</label>
            <input type="text" id="d_crop" required>
            <label>Region</label>
            <input type="text" id="d_region" required>
            <label>Severity</label>
            <select id="d_sev"><option>Low</option><option selected>Medium</option><option>High</option></select>
            <label>Reported at</label>
            <input type="date" id="d_reported" value="<?=date('Y-m-d')?>">
            <label>Advisory message (recommended actions)</label>
            <textarea id="d_advice" rows="3"></textarea>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                <button type="button" class="icon-btn" onclick="closeDiseaseModal()" style="background:#bdbdbd">Cancel</button>
                <button type="button" class="icon-btn" id="saveDiseaseBtn" style="background:#ffb74d"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirm Delete -->
<div id="confirmBackdrop" class="modal-backdrop" style="display:none">
    <div class="modal" style="width:420px">
        <h3 id="confirmTitle">CONFIRM</h3>
        <p id="confirmText">Are you sure?</p>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
            <button onclick="document.getElementById('confirmBackdrop').style.display='none'" class="icon-btn" style="background:#bdbdbd">Cancel</button>
            <button id="confirmYes" class="icon-btn del">YES, DELETE</button>
        </div>
    </div>
</div>

<script>
// ---------- Utilities ----------
function qs(s){return document.querySelector(s);}
function qsa(s){return Array.from(document.querySelectorAll(s));}
function showToast(msg, ok=true){ const t=qs('#toast'); t.style.background = ok? '#111' : '#b71c1c'; t.textContent = msg; t.style.display='block'; setTimeout(()=> t.style.display='none', 3500); }
function escapeHtml(s){ return String(s||'').replace(/[&<>"'\/]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;'}[ch];});}
function escapeJs(s){ return String(s||'').replace(/'/g,"\\'").replace(/"/g,'\\"'); }

// ---------- State ----------
let advisoryPage = 1;
let advisoryFilter = '';
let diseasePage = 1;
let pendingDelete = {type:null,id:null};

// ---------- Load advisory history ----------
function loadAdvisories(page=1){
    advisoryPage = page;
    const fd = new FormData();
    fd.append('action','advisory_list');
    fd.append('page', page);
    fd.append('limit', 8);
    fd.append('filter', qs('#filter_q').value.trim());
    fd.append('date_from', qs('#filter_from').value || '');
    fd.append('date_to', qs('#filter_to').value || '');
    fd.append('crop_filter', qs('#filter_crop').value.trim());
    fd.append('region_filter', qs('#filter_region').value.trim());
    fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
        if (!res.success) return showToast(res.message || 'Failed to load', false);
        renderAdvisoryTable(res.data);
        renderPager(res.total, res.page, res.limit);
    }).catch(()=> showToast('Server error', false));
}

function renderAdvisoryTable(rows){
    const wrap = qs('#tableWrap');
    if (!rows || rows.length===0){ wrap.innerHTML = '<div style="padding:14px;color:#777">No advisories found.</div>'; return; }
    let html = `<table class="table"><thead><tr><th>#</th><th>Title</th><th>Message</th><th>Target</th><th>Created</th><th>Actions</th></tr></thead><tbody>`;
    rows.forEach(r=>{
        const target = escapeHtml(r.target_type + (r.target_value? (' (' + r.target_value + ')') : ''));
        html += `<tr data-id="${r.id}"><td>${r.id}</td><td>${escapeHtml(r.title)}</td><td>${escapeHtml(r.message).slice(0,80)}${r.message.length>80?'...':''}</td><td>${target}</td><td>${escapeHtml(r.created_at)}</td><td><div class="actions"><button class="icon-btn edit" onclick="openEditAdvisory(${r.id})"><i class="fa fa-pen"></i></button><button class="icon-btn del" onclick="confirmDelete('advisory', ${r.id})"><i class="fa fa-trash"></i></button></div></td></tr>`;
    });
    html += `</tbody></table>`;
    wrap.innerHTML = html;
}

function renderPager(total, page, limit){
    const pager = qs('#pager');
    pager.innerHTML = '';
    const pages = Math.max(1, Math.ceil(total/limit));
    for (let i=1;i<=pages;i++){
        const b = document.createElement('button');
        b.textContent = i;
        if (i===page) b.classList.add('active');
        b.onclick = ()=> loadAdvisories(i);
        pager.appendChild(b);
    }
}

// ---------- Disease list (right panel) ----------
function loadDiseases(page=1){
    diseasePage = page;
    const fd = new FormData();
    fd.append('action','disease_list');
    fd.append('page', page);
    fd.append('limit', 8);
    fd.append('filter','');
    fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
        if (!res.success) return qs('#diseaseList').innerHTML = '<div style="color:#777">Failed to load</div>';
        renderDiseaseList(res.data);
    }).catch(()=> qs('#diseaseList').innerHTML = '<div style="color:#777">Server error</div>');
}

function renderDiseaseList(rows){
    const wrap = qs('#diseaseList');
    if (!rows || rows.length===0){ wrap.innerHTML = '<div style="padding:10px;color:#777">No disease alerts</div>'; return; }
    let html = '<ul style="list-style:none;padding-left:0;margin:0">';
    rows.forEach(r=>{
        html += `<li style="padding:10px;border-bottom:1px solid #f1f4f8">
            <div style="font-weight:600">${escapeHtml(r.disease_name)} <small style="color:#6b7a86">(${escapeHtml(r.crop_type)})</small></div>
            <div style="color:var(--muted);font-size:13px">${escapeHtml(r.region)} — ${escapeHtml(r.severity)}</div>
            <div style="margin-top:6px;font-size:13px;color:#555">${escapeHtml(r.advisory_message||'—')}</div>
            <div style="margin-top:8px"><button class="icon-btn edit" onclick="chooseDiseaseForAdvisory(${r.id}, '${escapeJs(r.disease_name)}')">
            <i class="fa fa-bullhorn"></i> USE</button>
            <button class="icon-btn del" onclick="confirmDelete('disease', ${r.id})"><i class="fa fa-trash"></i></button></div>
        </li>`;
    });
    html += '</ul>';
    wrap.innerHTML = html;
}

// ---------- Open advisory modal & dynamic controls ----------
qs('#openAdvisoryBtn').addEventListener('click', ()=> openAdvisoryModal());

function openAdvisoryModal(){
    qs('#advForm').reset();
    qs('#adv_id').value = '';
    qs('#advisoryModalTitle').innerText = 'New Advisory';
    renderAdvTargetControls('all');
    qs('#modalBackdrop').style.display = 'flex';
}

function closeAdvModal(){ qs('#modalBackdrop').style.display = 'none'; }

qs('#adv_target_type').addEventListener('change', function(){ renderAdvTargetControls(this.value); });

function renderAdvTargetControls(type){
    const wrap = qs('#adv_target_controls');
    wrap.innerHTML = '';
    if (type === 'region') {
        wrap.innerHTML = `<label>Region (match farmers.location contains)</label><input id="adv_target_region" name="target_value" placeholder="e.g. Morogoro">`;
    } else if (type === 'crop') {
        wrap.innerHTML = `<label>Crop type</label><input id="adv_target_crop" placeholder="Type crop..." autocomplete="off"><div id="adv_crop_suggestions" class="suggestions" style="top:44px"></div><input type="hidden" id="adv_target_crop_val" name="target_value">`;
        const cropInput = qs('#adv_target_crop'); let ct=null;
        cropInput.addEventListener('input', function(){
            const q = this.value.trim(); if (q.length<1) { qs('#adv_crop_suggestions').style.display='none'; return; }
            clearTimeout(ct); ct = setTimeout(()=> {
                const fd = new FormData(); fd.append('action','crop_suggestions'); fd.append('q', q);
                fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
                    if (!res.success) return;
                    qs('#adv_crop_suggestions').innerHTML = res.data.map(c=>`<p onclick="chooseAdvCrop('${escapeJs(c)}')">${escapeHtml(c)}</p>`).join('');
                    qs('#adv_crop_suggestions').style.display = res.data.length ? 'block' : 'none';
                });
            },160);
        });
    } else if (type === 'disease') {
        wrap.innerHTML = `<label>Choose disease alert</label><input id="adv_target_disease" placeholder="Type disease or crop or region..." autocomplete="off"><div id="adv_disease_suggestions" class="suggestions" style="top:44px"></div><input type="hidden" id="adv_target_disease_id" name="disease_id">`;
        const dInput = qs('#adv_target_disease'); let dt=null;
        dInput.addEventListener('input', function(){
            const q = this.value.trim(); if (q.length<1) { qs('#adv_disease_suggestions').style.display='none'; return; }
            clearTimeout(dt); dt = setTimeout(()=> {
                const fd = new FormData(); fd.append('action','disease_suggestions'); fd.append('q', q);
                fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
                    if (!res.success) return;
                    qs('#adv_disease_suggestions').innerHTML = res.data.map(d=>`<p data-id="${d.id}" onclick="chooseAdvDisease(${d.id}, '${escapeJs(d.disease_name)}')">${escapeHtml(d.disease_name)} <br><small style="color:#6b7a86">${escapeHtml(d.crop_type)} — ${escapeHtml(d.region)}</small></p>`).join('');
                    qs('#adv_disease_suggestions').style.display = res.data.length ? 'block' : 'none';
                });
            },160);
        });
    } else if (type === 'farmer') {
        wrap.innerHTML = `<label>Specific farmer</label><input id="adv_target_farmer" placeholder="Type farmer name or phone..." autocomplete="off"><input type="hidden" id="adv_target_farmer_id" name="target_value"><div id="adv_farmer_suggestions" class="suggestions" style="top:44px"></div>`;
        const fInput = qs('#adv_target_farmer'); let ft=null;
        fInput.addEventListener('input', function(){
            const q = this.value.trim(); if (q.length<1) { qs('#adv_farmer_suggestions').style.display='none'; return; }
            clearTimeout(ft); ft = setTimeout(()=> {
                const fd = new FormData(); fd.append('action','farmer_suggestions'); fd.append('q', q);
                fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
                    if (!res.success) return;
                    qs('#adv_farmer_suggestions').innerHTML = res.data.map(f=>`<p data-id="${f.id}" onclick="chooseAdvFarmer(${f.id}, '${escapeJs([f.first_name,f.middle_name,f.last_name].filter(Boolean).join(' '))}')">${escapeHtml([f.first_name,f.middle_name,f.last_name].filter(Boolean).join(' '))} <br><small style="color:#6b7a86">${escapeHtml(f.phone)}</small></p>`).join('');
                    qs('#adv_farmer_suggestions').style.display = res.data.length ? 'block' : 'none';
                });
            },160);
        });
    } else {
        wrap.innerHTML = `<div style="color:var(--muted)">This advisory will be sent to all farmers.</div>`;
    }
}

function chooseAdvCrop(val){ qs('#adv_target_crop').value = val; qs('#adv_target_crop_val').value = val; qs('#adv_crop_suggestions').style.display='none'; }
function chooseAdvDisease(id, name){ qs('#adv_target_disease').value = name; qs('#adv_target_disease_id').value = id; qs('#adv_disease_suggestions').style.display='none'; }
function chooseAdvFarmer(id, name){ qs('#adv_target_farmer').value = name; qs('#adv_target_farmer_id').value = id; qs('#adv_farmer_suggestions').style.display='none'; }

// advisory submit
qs('#advForm').addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData();
    const id = qs('#adv_id').value;
    fd.append('title', qs('#adv_title').value.trim());
    fd.append('message', qs('#adv_message').value.trim());
    const tt = qs('#adv_target_type').value;
    fd.append('target_type', tt);
    let tv = '';
    let did = '';
    if (tt === 'region') tv = qs('#adv_target_region').value.trim();
    else if (tt === 'crop') tv = qs('#adv_target_crop_val').value.trim() || qs('#adv_target_crop').value.trim();
    else if (tt === 'disease') { did = qs('#adv_target_disease_id').value || ''; tv = qs('#adv_target_disease').value.trim(); }
    else if (tt === 'farmer') tv = qs('#adv_target_farmer_id').value || '';
    fd.append('target_value', tv);
    if (did) fd.append('disease_id', did);
    fd.append('action', id ? 'advisory_update' : 'advisory_add');
    if (id) fd.append('id', id);

    fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
        if (!res.success) return showToast(res.message || 'Failed', false);
        showToast(res.message || 'Saved');
        closeAdvModal();
        loadAdvisories(1);
        loadDiseases(1);
    }).catch(()=> showToast('Server error', false));
});

// open edit advisory
function openEditAdvisory(id){
    const fd = new FormData(); fd.append('action','advisory_fetch_single'); fd.append('id', id);
    fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
        if (!res.success) return showToast(res.message || 'Failed', false);
        const d = res.data;
        qs('#adv_id').value = d.id;
        qs('#adv_title').value = d.title;
        qs('#adv_message').value = d.message;
        qs('#adv_target_type').value = d.target_type || 'all';
        renderAdvTargetControls(d.target_type || 'all');
        setTimeout(()=> {
            if (d.target_type === 'region') qs('#adv_target_region').value = d.target_value || '';
            else if (d.target_type === 'crop') { qs('#adv_target_crop').value = d.target_value || ''; qs('#adv_target_crop_val').value = d.target_value || ''; }
            else if (d.target_type === 'disease') { qs('#adv_target_disease_id').value = d.disease_id || ''; qs('#adv_target_disease').value = d.target_value || ''; }
            else if (d.target_type === 'farmer') { qs('#adv_target_farmer_id').value = d.target_value || ''; /* try fill name via farmer_suggestions */ }
        },140);
        qs('#advisoryModalTitle').innerText = 'Edit Advisory';
        qs('#modalBackdrop').style.display = 'flex';
    }).catch(()=> showToast('Server error', false));
}

// ---------- Disease modal ----------
qs('#openDiseaseBtn').addEventListener('click', ()=> { qs('#dModalBackdrop').style.display='flex'; });

function closeDiseaseModal(){ qs('#dModalBackdrop').style.display='none'; }
qs('#saveDiseaseBtn').addEventListener('click', function(){
    const dn = qs('#d_name').value.trim();
    const crop = qs('#d_crop').value.trim();
    const reg = qs('#d_region').value.trim();
    const sev = qs('#d_sev').value;
    const rep = qs('#d_reported').value || '';
    const adv = qs('#d_advice').value.trim();
    if (!dn || !crop || !reg) return showToast('Please fill disease, crop and region', false);
    const fd = new FormData();
    fd.append('action','disease_add');
    fd.append('disease_name', dn);
    fd.append('crop_type', crop);
    fd.append('region', reg);
    fd.append('severity', sev);
    fd.append('reported_at', rep);
    fd.append('advisory_message', adv);
    fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
        if (!res.success) return showToast(res.message || 'Failed', false);
        showToast(res.message || 'Saved');
        closeDiseaseModal();
        loadDiseases(1);
    }).catch(()=> showToast('Server error', false));
});

// ---------- Delete confirm ----------
function confirmDelete(type, id){
    pendingDelete.type = type; pendingDelete.id = id;
    qs('#confirmTitle').innerText = type === 'disease' ? 'Delete Disease' : (type === 'advisory' ? 'Delete Advisory' : 'Confirm');
    qs('#confirmText').innerText = 'Are you sure you want to delete this ' + type + '?';
    qs('#confirmBackdrop').style.display = 'flex';
}
qs('#confirmYes').addEventListener('click', function(){
    const type = pendingDelete.type; const id = pendingDelete.id;
    if (!type || !id) return;
    const fd = new FormData();
    fd.append('action', type === 'disease' ? 'disease_delete' : (type === 'advisory' ? 'advisory_delete' : ''));
    fd.append('id', id);
    fetch(location.pathname, {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
        qs('#confirmBackdrop').style.display = 'none';
        if (!res.success) return showToast(res.message || 'Delete failed', false);
        showToast(res.message || 'Deleted');
        loadAdvisories(1);
        loadDiseases(1);
    }).catch(()=> { qs('#confirmBackdrop').style.display = 'none'; showToast('Server error', false); });
});

// ---------- Suggestion closures ----------
document.addEventListener('click', function(e){
    if (!qs('#adv_target_crop') || !qs('#adv_crop_suggestions')) return;
    if (qs('#adv_crop_suggestions') && !qs('#adv_crop_suggestions').contains(e.target)) qs('#adv_crop_suggestions').style.display='none';
    if (qs('#adv_disease_suggestions') && !qs('#adv_disease_suggestions').contains(e.target)) qs('#adv_disease_suggestions').style.display='none';
    if (qs('#adv_farmer_suggestions') && !qs('#adv_farmer_suggestions').contains(e.target)) qs('#adv_farmer_suggestions').style.display='none';
});

// ---------- Filters ----------
qs('#applyFilters').addEventListener('click', ()=> loadAdvisories(1));

// initial load
loadAdvisories(1);
loadDiseases(1);

</script>
</body>
</html>
