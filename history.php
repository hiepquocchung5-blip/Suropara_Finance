<?php
require_once 'layout/header.php';

// CONFIGURATION
// Fallback if not defined in config.php
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', '../api'); 
}

// PARAMS
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Default to start of month
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// BUILD QUERY
$where = ["t.status != 'pending'"]; // History only shows processed items
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="text-white fw-bold mb-0">TRANSACTION ARCHIVE</h4>
    
    <!-- EXPORT FORM -->
    <form action="export.php" method="POST" target="_blank" class="d-inline">
        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
        <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
        <button type="submit" class="btn btn-success fw-bold btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> EXPORT CSV
        </button>
    </form>
</div>

<!-- FILTERS -->
<div class="card mb-4 bg-dark border-secondary shadow-sm">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small text-muted mb-1">Search</label>
                <input type="text" name="q" class="form-control form-control-sm bg-black text-white border-secondary" placeholder="ID, Phone, Ref..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="small text-muted mb-1">Type</label>
                <select name="type" class="form-select form-select-sm bg-black text-white border-secondary">
                    <option value="all" <?= $type=='all'?'selected':'' ?>>All Types</option>
                    <option value="deposit" <?= $type=='deposit'?'selected':'' ?>>Deposit</option>
                    <option value="withdraw" <?= $type=='withdraw'?'selected':'' ?>>Withdraw</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-black text-white border-secondary">
                    <option value="all" <?= $status=='all'?'selected':'' ?>>All Status</option>
                    <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
                    <option value="rejected" <?= $status=='rejected'?'selected':'' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="small text-muted mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm bg-black text-white border-secondary" value="<?= $dateFrom ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="small text-muted mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm bg-black text-white border-secondary" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary btn-sm w-100 fw-bold"><i class="bi bi-filter"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card bg-dark border-secondary shadow-sm">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle small">
            <thead>
                <tr class="text-secondary text-uppercase" style="font-size: 0.75rem;">
                    <th>ID</th>
                    <th>Date</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Bank / Note</th>
                    <th>Processed By</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($logs)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No records found matching your filters.</td></tr>
                <?php else: foreach($logs as $row): 
                    $isDeposit = $row['type'] === 'deposit';
                    $color = $isDeposit ? 'success' : 'danger';
                    $sign = $isDeposit ? '+' : '-';
                    $icon = $isDeposit ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle';
                    
                    // Parse Provider from note if not in joined table (for withdrawals)
                    $provider = $row['provider_name'];
                    if (!$provider && preg_match('/\((.*?)\)/', $row['admin_note'], $matches)) {
                        $provider = $matches[1];
                    }

                    // Image URL Construction
                    $proofUrl = '';
                    if ($row['proof_image']) {
                        $cleanPath = ltrim($row['proof_image'], '/');
                        // Ensure API_BASE_URL does not have a trailing slash before appending
                        $proofUrl = rtrim(API_BASE_URL, '/') . '/' . $cleanPath;
                    }
                ?>
                <tr>
                    <td><span class="text-muted">#</span><?= $row['id'] ?></td>
                    <td>
                        <div class="text-white"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                        <span class="text-muted" style="font-size:0.8em"><?= date('H:i', strtotime($row['created_at'])) ?></span>
                    </td>
                    <td>
                        <div class="fw-bold text-white"><?= htmlspecialchars($row['username']) ?></div>
                        <div class="font-monospace text-muted" style="font-size:0.85em"><?= htmlspecialchars($row['phone']) ?></div>
                    </td>
                    <td>
                        <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-25">
                            <i class="bi <?= $icon ?> me-1"></i> <?= strtoupper($row['type']) ?>
                        </span>
                    </td>
                    <td class="fw-bold text-<?= $color ?> font-monospace fs-6">
                        <?= $sign ?><?= number_format($row['amount']) ?>
                    </td>
                    <td>
                        <div class="text-info"><?= htmlspecialchars($provider ?? 'System') ?></div>
                        <?php if($row['transaction_last_digits']): ?>
                            <small class="font-monospace text-warning">Ref: <?= htmlspecialchars($row['transaction_last_digits']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-secondary text-light">
                            <i class="bi bi-person-fill"></i> <?= htmlspecialchars($row['admin_name'] ?? 'System') ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['status']=='approved'): ?>
                            <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Approved</span>
                        <?php else: ?>
                            <span class="text-danger fw-bold"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-light" onclick='openDetailModal(<?= json_encode($row) ?>, "<?= $proofUrl ?>")' title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- PAGINATION -->
    <?php if($totalPages > 1): ?>
    <div class="card-footer border-secondary py-3">
        <nav>
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page-1 ?>&q=<?= urlencode($q) ?>&type=<?= $type ?>&status=<?= $status ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">&laquo; Prev</a>
                </li>
                <li class="page-item disabled"><span class="page-link bg-dark text-white border-secondary">Page <?= $page ?> of <?= $totalPages ?></span></li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page+1 ?>&q=<?= urlencode($q) ?>&type=<?= $type ?>&status=<?= $status ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">Next &raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- DETAIL MODAL -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary shadow-lg">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title text-white">Transaction Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Proof Image Section -->
                <div id="proofContainer" class="text-center mb-3 d-none">
                    <div class="bg-black p-1 rounded border border-secondary d-inline-block">
                         <img id="modalImg" src="" class="img-fluid rounded" style="max-height: 300px; width: auto; object-fit: contain;">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">STATUS</div>
                        <div id="modalStatus" class="fw-bold"></div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="text-muted small">AMOUNT</div>
                        <div id="modalAmount" class="fw-bold text-warning fs-5 font-monospace"></div>
                    </div>
                </div>

                <div class="bg-black p-3 rounded border border-secondary mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-bold">Admin Note / Reason</span>
                        <span class="text-secondary small" id="modalProcessedAt"></span>
                    </div>
                    <p class="text-white small mb-0 fst-italic" id="modalNote"></p>
                </div>
                
                <table class="table table-dark table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Transaction ID:</td><td class="text-end font-monospace" id="modalId"></td></tr>
                    <tr><td class="text-muted">User:</td><td class="text-end" id="modalUser"></td></tr>
                    <tr><td class="text-muted">Reference:</td><td class="text-end text-info" id="modalRef"></td></tr>
                    <tr><td class="text-muted">Processed By:</td><td class="text-end" id="modalAdmin"></td></tr>
                </table>
            </div>
            <div class="modal-footer border-secondary py-1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Use the constant defined by PHP for consistent image paths
const API_BASE_URL = "<?= API_BASE_URL ?>";

function openDetailModal(tx, imgUrl) {
    document.getElementById('modalId').innerText = "#" + tx.id;
    document.getElementById('modalUser').innerText = tx.username + " (" + tx.phone + ")";
    document.getElementById('modalAmount').innerText = new Intl.NumberFormat().format(tx.amount) + " MMK";
    document.getElementById('modalRef').innerText = tx.transaction_last_digits || '-';
    document.getElementById('modalAdmin').innerText = tx.admin_name || 'System';
    document.getElementById('modalProcessedAt').innerText = tx.updated_at;
    
    // Status Logic
    const statusEl = document.getElementById('modalStatus');
    if (tx.status === 'approved') statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> APPROVED</span>';
    else if (tx.status === 'rejected') statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> REJECTED</span>';
    else statusEl.innerHTML = '<span class="text-warning">PENDING</span>';

    // Note Logic
    document.getElementById('modalNote').innerText = tx.admin_note || "No notes recorded.";
    
    // Image Logic
    const imgContainer = document.getElementById('proofContainer');
    const imgEl = document.getElementById('modalImg');
    
    if (imgUrl) {
        imgEl.src = imgUrl;
        imgContainer.classList.remove('d-none');
    } else {
        imgContainer.classList.add('d-none');
        imgEl.src = '';
    }
    
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>

<?php require_once 'layout/footer.php'; ?>