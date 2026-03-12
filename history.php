<?php
require_once 'layout/header.php';

// CONFIGURATION
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', getEnvSafe('API_PUBLIC_URL', 'https://apisuro.online')); 
}

$staffId = $_SESSION['finance_id'];
$staffRole = $_SESSION['finance_role'];

// PARAMS
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Reduced limit for mobile-friendly scrolling
$offset = ($page - 1) * $limit;

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')); // Default to last 7 days for mobile
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$viewScope = $_GET['scope'] ?? 'my'; // 'my' or 'all'

// BUILD QUERY
$where = ["t.status != 'pending'"]; 
$params = [];

// Date Filter
$where[] = "t.created_at BETWEEN ? AND ?";
$params[] = "$dateFrom 00:00:00";
$params[] = "$dateTo 23:59:59";

// Search Filter
if ($q) {
    $where[] = "(u.username LIKE ? OR u.phone LIKE ? OR t.id = ? OR t.transaction_last_digits LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = $q; $params[] = "%$q%";
}

// Type Filter
if ($type !== 'all') {
    $where[] = "t.type = ?";
    $params[] = $type;
}

// Status Filter
if ($status !== 'all') {
    $where[] = "t.status = ?";
    $params[] = $status;
}

// Scope Filter (Staff only sees their own by default)
if ($viewScope === 'my') {
    $where[] = "t.processed_by_admin_id = ?";
    $params[] = $staffId;
}

$whereSQL = implode(" AND ", $where);

// FETCH DATA
$sql = "
    SELECT t.*, u.username, u.phone, pm.provider_name, a.username as admin_name
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
    LEFT JOIN admin_users a ON t.processed_by_admin_id = a.id
    WHERE $whereSQL 
    ORDER BY t.updated_at DESC 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// PAGINATION COUNT
$countSql = "
    SELECT COUNT(*) 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE $whereSQL
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);
?>

<!-- Sakura Particles Integration -->
<style>
    #sakura-container-history { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.3; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.3; } 90% { opacity: 0.3; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    .dash-wrapper { position: relative; z-index: 10; }
</style>
<div id="sakura-container-history"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-history');
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
            <h3 class="text-white fw-black mb-0 italic tracking-widest">ARCHIVE</h3>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 2px;">取引履歴</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm px-3 py-2 rounded-pill fw-bold border border-info text-info shadow-[0_0_10px_rgba(13,202,240,0.3)] hover:bg-info hover:text-dark transition" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> REFRESH
            </button>
            <form action="export.php" method="POST" target="_blank" class="m-0 d-inline">
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                <button type="submit" class="btn btn-sm px-3 py-2 rounded-pill fw-bold border border-success text-success shadow-[0_0_10px_rgba(25,135,84,0.3)] hover:bg-success hover:text-dark transition">
                    <i class="bi bi-download me-1"></i> EXPORT
                </button>
            </form>
        </div>
    </div>

    <!-- MOBILE-FRIENDLY FILTERS -->
    <div class="glass-card p-3 mb-4 border border-secondary border-opacity-50 shadow-sm">
        <form method="GET" id="filterForm">
            <!-- Search & Scope Row -->
            <div class="row g-2 mb-2">
                <div class="col-8">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-black border-secondary text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control bg-black text-white border-secondary" placeholder="ID, Phone..." value="<?= htmlspecialchars($q) ?>">
                    </div>
                </div>
                <div class="col-4">
                    <select name="scope" class="form-select form-select-sm bg-black text-white border-secondary" onchange="document.getElementById('filterForm').submit()">
                        <option value="my" <?= $viewScope=='my'?'selected':'' ?>>My Logs</option>
                        <option value="all" <?= $viewScope=='all'?'selected':'' ?>>All Staff</option>
                    </select>
                </div>
            </div>
            
            <!-- Type & Status Row -->
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <select name="type" class="form-select form-select-sm bg-black text-white border-secondary">
                        <option value="all" <?= $type=='all'?'selected':'' ?>>All Types</option>
                        <option value="deposit" <?= $type=='deposit'?'selected':'' ?>>Deposits</option>
                        <option value="withdraw" <?= $type=='withdraw'?'selected':'' ?>>Payouts</option>
                        <option value="bonus" <?= $type=='bonus'?'selected':'' ?>>Bonuses</option>
                    </select>
                </div>
                <div class="col-6">
                    <select name="status" class="form-select form-select-sm bg-black text-white border-secondary">
                        <option value="all" <?= $status=='all'?'selected':'' ?>>All Status</option>
                        <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $status=='rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
            </div>

            <!-- Date & Submit Row -->
            <div class="row g-2 align-items-center">
                <div class="col-5">
                    <input type="date" name="date_from" class="form-control form-control-sm bg-black text-white border-secondary text-[10px]" value="<?= $dateFrom ?>">
                </div>
                <div class="col-5">
                    <input type="date" name="date_to" class="form-control form-control-sm bg-black text-white border-secondary text-[10px]" value="<?= $dateTo ?>">
                </div>
                <div class="col-2">
                    <button type="submit" class="btn btn-info btn-sm w-100 fw-bold"><i class="bi bi-funnel"></i></button>
                </div>
            </div>
        </form>
    </div>

    <!-- RESULTS INFO -->
    <div class="text-muted small fw-bold tracking-widest mb-3 ps-1">
        FOUND <?= number_format($totalRecords) ?> RECORDS
    </div>

    <!-- LOG CARDS (V2 Mobile UI) -->
    <div class="row g-3">
        <?php if(empty($logs)): ?>
            <div class="col-12 text-center text-muted py-5 glass-card border border-dashed border-secondary">
                <i class="bi bi-journal-x display-4 d-block mb-3 opacity-50"></i>
                No records found matching filters.
            </div>
        <?php else: foreach($logs as $row): 
            $isDeposit = $row['type'] === 'deposit';
            $isBonus = $row['type'] === 'bonus';
            
            $borderColor = 'border-secondary';
            $icon = 'bi-arrow-left-right';
            $iconColor = 'text-muted';
            $sign = '';
            
            if ($row['status'] === 'approved') {
                if ($isDeposit) { $borderColor = 'border-success'; $icon = 'bi-arrow-down-circle'; $iconColor = 'text-success'; $sign = '+'; }
                elseif ($isBonus) { $borderColor = 'border-warning'; $icon = 'bi-gift'; $iconColor = 'text-warning'; $sign = '+'; }
                else { $borderColor = 'border-danger'; $icon = 'bi-arrow-up-circle'; $iconColor = 'text-danger'; $sign = '-'; }
            } else {
                $borderColor = 'border-gray-600';
                $icon = 'bi-x-circle';
                $iconColor = 'text-gray-500';
            }
            
            // Parse Provider from note if not in joined table (for withdrawals)
            $provider = $row['provider_name'];
            if (!$provider && preg_match('/\((.*?)\)/', $row['admin_note'], $matches)) {
                $provider = $matches[1];
            }

            // Image URL Construction
            $proofUrl = '';
            if ($row['proof_image']) {
                $cleanPath = ltrim($row['proof_image'], '/');
                $proofUrl = rtrim(API_BASE_URL, '/') . '/' . $cleanPath;
            }
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="glass-card p-3 border-start border-4 <?= $borderColor ?> cursor-pointer hover:bg-opacity-80 transition" onclick='openDetailModal(<?= json_encode($row) ?>, "<?= $proofUrl ?>")'>
                
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= $icon ?> <?= $iconColor ?> fs-5"></i>
                        <div>
                            <div class="text-[10px] text-gray-500 font-bold uppercase tracking-wider"><?= $row['type'] ?></div>
                            <div class="fw-black font-mono <?= $iconColor ?> m-0 lh-1 fs-5"><?= $sign ?><?= number_format($row['amount']) ?></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if($row['status']=='approved'): ?>
                            <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-50"><i class="bi bi-check2"></i> DONE</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50"><i class="bi bi-x-lg"></i> FAIL</span>
                        <?php endif; ?>
                        <div class="text-[9px] text-muted mt-1 font-mono"><?= date('m/d H:i', strtotime($row['created_at'])) ?></div>
                    </div>
                </div>

                <div class="bg-black bg-opacity-40 rounded p-2 d-flex justify-content-between align-items-center border border-white border-opacity-5">
                    <div>
                        <div class="text-white fw-bold small"><?= htmlspecialchars($row['username']) ?></div>
                        <div class="text-[9px] text-info font-mono"><?= htmlspecialchars($provider ?? 'System') ?> <?= $row['transaction_last_digits'] ? "(*{$row['transaction_last_digits']})" : "" ?></div>
                    </div>
                    <div class="text-end">
                         <div class="text-[9px] text-gray-500 uppercase">Agent</div>
                         <div class="text-[10px] text-white fw-bold"><i class="bi bi-person-fill text-muted"></i> <?= htmlspecialchars($row['admin_name'] ?? 'Auto') ?></div>
                    </div>
                </div>

            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    
    <!-- PAGINATION -->
    <?php if($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <ul class="pagination pagination-sm gap-2">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link bg-black text-white border-secondary rounded-pill px-3" href="?page=<?= $page-1 ?>&q=<?= urlencode($q) ?>&type=<?= $type ?>&status=<?= $status ?>&scope=<?= $viewScope ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">&laquo; PREV</a>
            </li>
            <li class="page-item disabled"><span class="page-link bg-transparent text-muted border-0 fw-bold px-3"><?= $page ?> / <?= $totalPages ?></span></li>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link bg-black text-white border-secondary rounded-pill px-3" href="?page=<?= $page+1 ?>&q=<?= urlencode($q) ?>&type=<?= $type ?>&status=<?= $status ?>&scope=<?= $viewScope ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">NEXT &raquo;</a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- DETAIL MODAL (V2 Bottom Sheet Style) -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content glass-card bg-dark" style="border:none; border-top: 1px solid rgba(255,255,255,0.2); border-radius: 20px 20px 0 0;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-black text-white italic tracking-widest"><i class="bi bi-receipt text-info"></i> LOG ENTRY</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3 pb-4">
                
                <div class="text-center mb-4">
                    <div class="text-muted small fw-bold tracking-widest uppercase mb-1" id="modalType"></div>
                    <div id="modalAmount" class="fw-black fs-1 font-mono lh-1 mb-2"></div>
                    <div id="modalStatus"></div>
                </div>

                <div class="bg-black bg-opacity-50 p-3 rounded-4 border border-secondary mb-3 space-y-2 text-sm font-mono">
                    <div class="d-flex justify-content-between border-bottom border-white border-opacity-10 pb-1">
                        <span class="text-muted">TX ID</span>
                        <span class="text-white fw-bold" id="modalId"></span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-white border-opacity-10 pb-1">
                        <span class="text-muted">Date</span>
                        <span class="text-white" id="modalProcessedAt"></span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-white border-opacity-10 pb-1">
                        <span class="text-muted">Player</span>
                        <span class="text-info fw-bold" id="modalUser"></span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-white border-opacity-10 pb-1">
                        <span class="text-muted">Bank/Ref</span>
                        <span class="text-warning fw-bold" id="modalRef"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Agent</span>
                        <span class="text-white" id="modalAdmin"></span>
                    </div>
                </div>

                <div class="bg-black bg-opacity-30 p-3 rounded-4 border border-white border-opacity-5 mb-3">
                    <div class="text-[10px] text-gray-500 font-bold uppercase mb-1"><i class="bi bi-file-text me-1"></i> Agent Note</div>
                    <p class="text-white text-xs mb-0 fst-italic" id="modalNote"></p>
                </div>
                
                <!-- Proof Image Section -->
                <div id="proofContainer" class="d-none">
                    <div class="text-[10px] text-gray-500 font-bold uppercase mb-1"><i class="bi bi-image me-1"></i> Attached Proof</div>
                    <div class="bg-black p-1 rounded-4 border border-secondary d-flex justify-content-center align-items-center overflow-hidden" style="height: 200px;">
                         <img id="modalImg" src="" class="img-fluid object-fit-contain w-100 h-100">
                    </div>
                    <a id="proofLink" href="#" target="_blank" class="btn btn-sm btn-outline-info w-100 rounded-pill mt-2 fw-bold">OPEN FULL SIZE</a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function openDetailModal(tx, imgUrl) {
    document.getElementById('modalId').innerText = "#" + tx.id;
    document.getElementById('modalType').innerText = tx.type;
    
    // Format Amount
    const amtEl = document.getElementById('modalAmount');
    amtEl.innerText = new Intl.NumberFormat().format(tx.amount) + " MMK";
    if(tx.type === 'deposit' || tx.type === 'bonus') amtEl.className = 'fw-black fs-1 font-mono lh-1 mb-2 text-success';
    else amtEl.className = 'fw-black fs-1 font-mono lh-1 mb-2 text-danger';

    document.getElementById('modalUser').innerText = tx.username + " (" + tx.phone + ")";
    
    const prov = tx.provider_name || 'System';
    const ref = tx.transaction_last_digits ? ` (*${tx.transaction_last_digits})` : '';
    document.getElementById('modalRef').innerText = prov + ref;
    
    document.getElementById('modalAdmin').innerText = tx.admin_name || 'Auto/System';
    document.getElementById('modalProcessedAt').innerText = tx.updated_at || tx.created_at;
    
    // Status Logic
    const statusEl = document.getElementById('modalStatus');
    if (tx.status === 'approved') statusEl.innerHTML = '<span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill"></i> APPROVED</span>';
    else if (tx.status === 'rejected') statusEl.innerHTML = '<span class="badge bg-danger px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill"></i> REJECTED</span>';
    else statusEl.innerHTML = '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill">PENDING</span>';

    // Note Logic
    document.getElementById('modalNote').innerText = tx.admin_note || "No additional notes provided.";
    
    // Image Logic
    const imgContainer = document.getElementById('proofContainer');
    const imgEl = document.getElementById('modalImg');
    const proofLink = document.getElementById('proofLink');
    
    if (imgUrl) {
        imgEl.src = imgUrl;
        proofLink.href = imgUrl;
        imgContainer.classList.remove('d-none');
    } else {
        imgContainer.classList.add('d-none');
        imgEl.src = '';
    }
    
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>

<?php require_once 'layout/footer.php'; ?>