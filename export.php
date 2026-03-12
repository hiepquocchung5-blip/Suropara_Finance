<?php
// Suropara Finance - Export Handler (V2 UI)
require_once __DIR__ . '/config.php';
requireFinanceAuth();

// --- HANDLE EXPORT LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $q = $_POST['q'] ?? '';
    $type = $_POST['type'] ?? 'all';
    $status = $_POST['status'] ?? 'all';
    $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
    $dateTo = $_POST['date_to'] ?? date('Y-m-d');
    
    // Build Query (Same logic as history.php)
    $where = ["t.status != 'pending'"]; 
    $params = [];

    $where[] = "t.created_at BETWEEN ? AND ?";
    $params[] = "$dateFrom 00:00:00";
    $params[] = "$dateTo 23:59:59";

    if ($q) {
        $where[] = "(u.username LIKE ? OR u.phone LIKE ? OR t.id = ?)";
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = $q;
    }
    if ($type !== 'all') { $where[] = "t.type = ?"; $params[] = $type; }
    if ($status !== 'all') { $where[] = "t.status = ?"; $params[] = $status; }

    // If scope is 'my', restrict to current admin (Optional feature, usually export implies 'all' for reporting)
    // if(isset($_POST['scope']) && $_POST['scope'] == 'my') {
    //     $where[] = "t.processed_by_admin_id = ?";
    //     $params[] = $_SESSION['finance_id'];
    // }

    $whereSQL = implode(" AND ", $where);

    $sql = "
        SELECT t.id, t.created_at, u.username, u.phone, t.type, t.amount, 
               pm.provider_name, t.transaction_last_digits, t.status, 
               a.username as processed_by, t.admin_note
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
        LEFT JOIN admin_users a ON t.processed_by_admin_id = a.id
        WHERE $whereSQL 
        ORDER BY t.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV
    $filename = "surobank_tx_" . date('Ymd_Hi') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Custom header row
    fputcsv($output, ['TX ID', 'Date', 'User', 'Phone', 'Type', 'Amount (MMK)', 'Bank', 'Ref', 'Status', 'Processed By', 'Note']);
    
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    
    // Log the export action
    try {
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'transactions')")
            ->execute([$_SESSION['finance_id'], "Exported CSV: $type ($dateFrom to $dateTo)"]);
    } catch(Exception $e) {}
    
    exit; // Stop execution to serve file
}

// --- V2 UI RENDER ---
require_once 'layout/header.php';
?>

<!-- Sakura Particles Integration -->
<style>
    #sakura-container-export { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.3; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.3; } 90% { opacity: 0.3; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    .dash-wrapper { position: relative; z-index: 10; }
</style>
<div id="sakura-container-export"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-export');
        if(!container) return;
        const petalCount = window.innerWidth < 768 ? 10 : 20;
        for(let i=0; i<petalCount; i++) {
            let p = document.createElement('div');
            p.className = 'sakura-petal';
            p.style.width = p.style.height = (Math.random()*6+4) + 'px';
            p.style.left = Math.random()*100 + 'vw';
            p.style.animationDuration = (Math.random()*8+7) + 's';
            p.style.animationDelay = (Math.random()*5) + 's';
            container.appendChild(p);
        }
    });
</script>

<div class="dash-wrapper">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-white fw-black mb-0 italic tracking-widest">DATA EXPORT</h3>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 2px;">データ出力</div>
        </div>
        <a href="history.php" class="btn btn-sm px-3 py-2 rounded-pill fw-bold border border-secondary text-muted hover:bg-secondary hover:text-white transition">
            <i class="bi bi-arrow-left me-1"></i> BACK
        </a>
    </div>

    <!-- Hidden iframe to handle the download without refreshing the page -->
    <iframe name="downloadFrame" id="downloadFrame" style="display:none;"></iframe>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="glass-card border border-success border-opacity-50 overflow-hidden p-0 shadow-lg">
                <div class="bg-success bg-opacity-20 text-success fw-black p-3 border-b border-success border-opacity-25 tracking-widest italic d-flex align-items-center">
                    <i class="bi bi-file-earmark-spreadsheet me-2 fs-5"></i> GENERATE CSV REPORT
                </div>
                
                <div class="p-4 bg-black bg-opacity-50">
                    <form method="POST" target="downloadFrame" id="exportForm" onsubmit="handleExport()">
                        <input type="hidden" name="export" value="1">
                        
                        <div class="bg-black bg-opacity-40 p-3 rounded-4 border border-white border-opacity-10 mb-4">
                            <label class="form-label text-muted small fw-bold uppercase tracking-widest mb-2"><i class="bi bi-filter me-1"></i> Transaction Filter</label>
                            
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="form-floating">
                                        <select name="type" id="exType" class="form-select bg-dark text-white border-secondary rounded-3">
                                            <option value="all">All Types</option>
                                            <option value="deposit" <?= isset($_POST['type']) && $_POST['type'] == 'deposit' ? 'selected' : '' ?>>Deposits</option>
                                            <option value="withdraw" <?= isset($_POST['type']) && $_POST['type'] == 'withdraw' ? 'selected' : '' ?>>Withdrawals</option>
                                            <option value="bonus" <?= isset($_POST['type']) && $_POST['type'] == 'bonus' ? 'selected' : '' ?>>Bonuses</option>
                                        </select>
                                        <label for="exType" class="text-muted small">Category</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating">
                                        <select name="status" id="exStatus" class="form-select bg-dark text-white border-secondary rounded-3">
                                            <option value="all">All Status</option>
                                            <option value="approved" <?= isset($_POST['status']) && $_POST['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="rejected" <?= isset($_POST['status']) && $_POST['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                        <label for="exStatus" class="text-muted small">Result</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                     <div class="form-floating">
                                        <input type="text" name="q" id="exQuery" class="form-control bg-dark text-white border-secondary rounded-3" placeholder="Search..." value="<?= htmlspecialchars($_POST['q'] ?? '') ?>">
                                        <label for="exQuery" class="text-muted small">Search ID or Phone (Optional)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-black bg-opacity-40 p-3 rounded-4 border border-white border-opacity-10 mb-4">
                            <label class="form-label text-muted small fw-bold uppercase tracking-widest mb-2"><i class="bi bi-calendar-range me-1"></i> Date Range</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="date" name="date_from" id="exFrom" class="form-control bg-dark text-white border-secondary rounded-3 font-mono" value="<?= $_POST['date_from'] ?? date('Y-m-01') ?>" required>
                                        <label for="exFrom" class="text-muted small">Start Date</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="date" name="date_to" id="exTo" class="form-control bg-dark text-white border-secondary rounded-3 font-mono" value="<?= $_POST['date_to'] ?? date('Y-m-d') ?>" required>
                                        <label for="exTo" class="text-muted small">End Date</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" id="downloadBtn" class="btn w-100 py-3 rounded-pill fw-black shadow-lg d-flex align-items-center justify-content-center gap-2 transition-transform active:scale-95" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff; letter-spacing: 1px;">
                            <i class="bi bi-cloud-download fs-5"></i>
                            <span>DOWNLOAD REPORT</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function handleExport() {
    const btn = document.getElementById('downloadBtn');
    const originalHtml = btn.innerHTML;
    
    // Visual feedback
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <span class="ms-2">GENERATING...</span>';
    btn.classList.add('disabled');
    
    // Reset button after a short delay assuming download starts
    setTimeout(() => {
        btn.innerHTML = '<i class="bi bi-check-circle-fill fs-5"></i> <span>DOWNLOAD COMPLETE</span>';
        btn.classList.remove('disabled');
        
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 3000);
    }, 1500);
}
</script>

<?php require_once 'layout/footer.php'; ?>