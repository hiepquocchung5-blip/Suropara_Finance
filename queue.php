<?php
require_once 'layout/header.php';

// API_BASE_URL is now defined in config.php (loaded via layout/header.php)
// Fallback just in case config is missing it, but prefer constant
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', '/api'); 
}

$staffId = $_SESSION['finance_id'];
$viewMode = $_GET['view'] ?? 'my'; // 'my' = strict, 'all' = monitor

// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $txId = (int)$_POST['tx_id'];
    $decision = $_POST['decision']; // 'approve' | 'reject'
    $note = trim($_POST['note']);

    if ($decision === 'reject' && empty($note)) {
        $err = "A reason is required for rejection.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Lock Row
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$txId]);
            $tx = $stmt->fetch();

            if ($tx) {
                // 2. Conflict Check
                if ($tx['processed_by_admin_id'] !== null && $tx['processed_by_admin_id'] != $staffId) {
                    throw new Exception("Conflict: This transaction was just reserved by another agent.");
                }

                $status = ($decision === 'approve') ? 'approved' : 'rejected';
                
                // 3. Balance Updates
                if ($status === 'approved' && $tx['type'] === 'deposit') {
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
                } elseif ($status === 'rejected' && $tx['type'] === 'withdraw') {
                    // Refund user
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
                }
                
                // 4. Finalize
                $finalNote = $note ?: ($status === 'approved' ? 'Processed' : 'Rejected');
                $pdo->prepare("UPDATE transactions SET status = ?, processed_by_admin_id = ?, admin_note = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$status, $staffId, $finalNote, $txId]);
                
                // 5. Audit
                $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'transactions')")
                    ->execute([$staffId, ucfirst($status) . " TX #$txId"]);

                $pdo->commit();
                $msg = "Transaction #$txId processed successfully.";
            } else {
                throw new Exception("Transaction not found or already handled.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = "Error: " . $e->getMessage();
        }
    }
}

// --- QUEUE LOGIC ---

// 1. DEPOSITS
// Rule: 
// - If 'my': Show only deposits to payment methods I own (payment_methods.admin_id = $staffId)
// - If 'all': Show all pending deposits
$depSql = "
    SELECT t.*, u.username, u.phone, pm.provider_name, pm.admin_id as wallet_owner_id, a.username as wallet_owner_name
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
    LEFT JOIN admin_users a ON pm.admin_id = a.id
    WHERE t.type = 'deposit' AND t.status = 'pending'
";

if ($viewMode === 'my') {
    $depSql .= " AND pm.admin_id = $staffId";
}

$deposits = $pdo->query($depSql . " ORDER BY t.created_at ASC")->fetchAll();


// 2. WITHDRAWALS
// Rule:
// - If 'my': Show requests assigned to me OR unassigned
// - If 'all': Show all pending withdrawals
$withSql = "
    SELECT t.*, u.username, u.phone, u.balance as current_bal, a.username as assigned_to_name
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN admin_users a ON t.processed_by_admin_id = a.id
    WHERE t.type = 'withdraw' AND t.status = 'pending'
";

if ($viewMode === 'my') {
    $withSql .= " AND (t.processed_by_admin_id = $staffId OR t.processed_by_admin_id IS NULL)";
}

$withdrawals = $pdo->query($withSql . " ORDER BY (t.processed_by_admin_id = $staffId) DESC, t.created_at ASC")->fetchAll();
?>

<!-- VIEW CONTROLLER -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="text-white mb-0">TRANSACTION QUEUE</h5>
    
    <div class="btn-group">
        <a href="?view=my" class="btn btn-sm <?= $viewMode==='my' ? 'btn-warning fw-bold' : 'btn-outline-secondary' ?>">
            <i class="bi bi-person-check-fill"></i> MY TASKS
        </a>
        <a href="?view=all" class="btn btn-sm <?= $viewMode==='all' ? 'btn-info fw-bold' : 'btn-outline-secondary' ?>">
            <i class="bi bi-globe"></i> TEAM VIEW
        </a>
    </div>
</div>

<?php if(isset($msg)): ?><div class="alert alert-success small shadow-sm border-0 border-start border-success border-4"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if(isset($err)): ?><div class="alert alert-danger small shadow-sm border-0 border-start border-danger border-4"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- TABS -->
<ul class="nav nav-pills nav-fill mb-3" id="queue-tabs">
    <li class="nav-item">
        <button class="nav-link active w-100" onclick="switchTab('deposits')">
            Inbound <span class="badge bg-white text-dark ms-1"><?= count($deposits) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link w-100" onclick="switchTab('withdrawals')">
            Outbound <span class="badge bg-white text-dark ms-1"><?= count($withdrawals) ?></span>
        </button>
    </li>
</ul>

<!-- 1. DEPOSITS LIST -->
<div id="tab-deposits">
    <?php foreach($deposits as $d): 
        $isMyWallet = ($d['wallet_owner_id'] == $staffId);
        $ownerDisplay = $isMyWallet ? 'YOU' : ($d['wallet_owner_name'] ?? 'System');
        
        // Image Path Logic
        // Append API_BASE_URL to the relative path stored in DB
        $proofUrl = '';
        if ($d['proof_image']) {
            // Ensure no double slashes
            $proofUrl = rtrim(API_BASE_URL, '/') . '/' . ltrim($d['proof_image'], '/');
        }
    ?>
    <div class="card mb-3 border-start border-success border-4 shadow-sm <?= !$isMyWallet ? 'opacity-75' : '' ?>">
        <div class="card-body">
            <!-- Header Badge -->
            <div class="d-flex justify-content-between mb-2">
                <?php if($viewMode === 'all'): ?>
                    <span class="badge <?= $isMyWallet ? 'bg-success' : 'bg-secondary' ?> mb-2">
                        Wallet Owner: <?= htmlspecialchars($ownerDisplay) ?>
                    </span>
                <?php elseif($isMyWallet): ?>
                     <div class="badge bg-success mb-2 w-100"><i class="bi bi-star-fill"></i> DIRECT REQUEST</div>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h4 class="fw-bold text-white mb-0">
                        <?= number_format($d['amount']) ?> <span class="fs-6 text-muted">MMK</span>
                    </h4>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="bi bi-person"></i> <?= htmlspecialchars($d['username']) ?> 
                        <span class="mx-1">•</span> 
                        <span class="font-monospace text-light"><?= htmlspecialchars($d['phone']) ?></span>
                    </div>
                </div>
                
                <!-- Proof Thumbnail -->
                <?php if($proofUrl): ?>
                    <div class="position-relative" onclick='openVerifyModal(<?= json_encode($d) ?>, "<?= $proofUrl ?>")' style="cursor: pointer;">
                        <img src="<?= $proofUrl ?>" class="img-proof border border-secondary rounded" alt="Proof" style="width:60px; height:60px; object-fit:cover;">
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                            <span class="visually-hidden"><i class="bi bi-zoom-in"></i></span>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="text-danger small bg-dark p-2 rounded"><i class="bi bi-exclamation-circle"></i> No Proof</div>
                <?php endif; ?>
            </div>
            
            <div class="bg-black bg-opacity-20 p-2 rounded d-flex justify-content-between align-items-center mb-2 border border-white border-opacity-10">
                <div>
                    <span class="d-block small text-warning font-monospace" style="letter-spacing: 1px;">
                        REF: <?= htmlspecialchars($d['transaction_last_digits'] ?? '------') ?>
                    </span>
                    <div class="small text-info fw-bold text-uppercase">
                        <?= htmlspecialchars($d['provider_name'] ?? 'Bank Transfer') ?>
                    </div>
                </div>
                <div class="text-end small text-muted lh-1">
                    <?= date('H:i', strtotime($d['created_at'])) ?><br>
                    <span style="font-size:0.7em"><?= date('M d', strtotime($d['created_at'])) ?></span>
                </div>
            </div>
            
            <!-- Actions -->
            <?php if($isMyWallet): ?>
                <button class="btn btn-success w-100 fw-bold shadow-sm" onclick='openVerifyModal(<?= json_encode($d) ?>, "<?= $proofUrl ?>")'>
                    <i class="bi bi-shield-check me-1"></i> VERIFY & APPROVE
                </button>
            <?php else: ?>
                <button class="btn btn-secondary w-100 btn-sm" disabled>Managed by <?= $ownerDisplay ?></button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; if(empty($deposits)) echo '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No pending deposits.</div>'; ?>
</div>

<!-- 2. WITHDRAWALS LIST -->
<div id="tab-withdrawals" style="display:none;">
    <?php foreach($withdrawals as $w): 
        $isMyTask = ($w['processed_by_admin_id'] == $staffId);
        $isUnassigned = ($w['processed_by_admin_id'] === null);
        
        // Parse Provider Name from Note (e.g. "Target: ... (KBZPay)")
        $providerDisplay = 'Unknown';
        if (preg_match('/\((.*?)\)/', $w['admin_note'], $matches)) {
            $providerDisplay = $matches[1];
        }
    ?>
    <div class="card mb-3 border-start border-danger border-4 shadow-sm <?= $isMyTask ? 'bg-danger bg-opacity-10' : '' ?>">
        <div class="card-body">
            
            <div class="d-flex justify-content-between mb-2">
                <?php if($isMyTask): ?>
                    <span class="badge bg-danger"><i class="bi bi-pin-fill"></i> ASSIGNED TO YOU</span>
                <?php elseif($isUnassigned): ?>
                    <span class="badge bg-secondary">OPEN POOL</span>
                <?php else: ?>
                    <span class="badge bg-dark border border-secondary text-muted">Assigned to: <?= htmlspecialchars($w['assigned_to_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between mb-2">
                <div>
                    <h4 class="fw-bold text-white mb-0"><?= number_format($w['amount']) ?> <small class="fs-6">MMK</small></h4>
                    <div class="small text-danger fw-bold">User Bal: <?= number_format($w['current_bal']) ?></div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-light"><?= htmlspecialchars($w['username']) ?></div>
                    <div class="font-monospace text-info small"><?= htmlspecialchars($w['phone']) ?></div>
                    <span class="badge bg-dark border border-secondary text-light mt-1 text-uppercase">
                        <i class="bi bi-bank2 me-1"></i> <?= htmlspecialchars($providerDisplay) ?>
                    </span>
                </div>
            </div>
            
            <?php if($isMyTask || $isUnassigned): ?>
                <div class="row g-2">
                    <div class="col-4">
                        <button class="btn btn-outline-danger w-100" onclick="openRejectModal(<?= $w['id'] ?>)">REJECT</button>
                    </div>
                    <div class="col-8">
                        <form method="POST">
                            <input type="hidden" name="action" value="process">
                            <input type="hidden" name="tx_id" value="<?= $w['id'] ?>">
                            <input type="hidden" name="decision" value="approve">
                            <input type="hidden" name="note" value="Sent via <?= htmlspecialchars($providerDisplay) ?>">
                            <button class="btn btn-success w-100 fw-bold shadow-sm">
                                MARK AS SENT <i class="bi bi-send-fill ms-1"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-muted small border-top border-white border-opacity-10 pt-2">
                    Locked by <?= htmlspecialchars($w['assigned_to_name']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; if(empty($withdrawals)) echo '<div class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 d-block mb-2 opacity-50"></i>All caught up!</div>'; ?>
</div>

<!-- MODAL: VERIFY DEPOSIT -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title text-white">Verify Deposit</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="bg-black p-1 rounded mb-3 border border-secondary d-inline-block w-100" style="min-height:200px; display:flex; align-items:center; justify-content:center;">
                    <img id="modalProofImg" src="" class="img-fluid rounded" style="max-height: 400px; object-fit: contain;">
                </div>
                
                <h2 class="text-warning fw-bold mb-1" id="modalAmount"></h2>
                <div class="small text-muted mb-3">Expected Amount</div>

                <div class="bg-black p-2 rounded mb-3 border border-warning d-flex justify-content-between align-items-center px-3">
                    <span class="text-secondary small">LAST 6 DIGITS</span>
                    <span class="fs-4 font-monospace text-white letter-spacing-2" id="modalDigits">------</span>
                </div>
                
                <form method="POST" class="d-grid gap-2">
                    <input type="hidden" name="action" value="process">
                    <input type="hidden" name="tx_id" id="modalTxId">
                    <input type="hidden" name="decision" value="approve">
                    <input type="text" name="note" class="form-control bg-dark text-white border-secondary mb-2" placeholder="Admin Note (Optional)">
                    
                    <div class="form-check text-start mb-2 ps-4">
                        <input class="form-check-input" type="checkbox" required id="checkMatch" style="cursor:pointer">
                        <label class="form-check-label small text-white" for="checkMatch">I confirmed the amount & digits match.</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success fw-bold py-2 shadow">CONFIRM APPROVAL</button>
                </form>
                
                <div class="mt-3 pt-2 border-top border-secondary">
                     <button class="btn btn-sm btn-outline-danger w-100" onclick="switchToReject()">Reject Transaction</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: REJECT TRANSACTION -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-danger">
            <div class="modal-header border-danger text-danger py-2">
                <h6 class="modal-title">Reject Transaction</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="process">
                    <input type="hidden" name="tx_id" id="rejectTxId">
                    <input type="hidden" name="decision" value="reject">
                    
                    <div class="mb-3">
                        <label class="form-label text-white small">Reason for Rejection (Required)</label>
                        <select name="note_select" class="form-select bg-black text-white border-secondary mb-2" onchange="document.getElementById('rejectNote').value = this.value">
                            <option value="">Select a common reason...</option>
                            <option value="Invalid Proof / Blur Image">Invalid Proof / Blur Image</option>
                            <option value="Transaction ID Mismatch">Transaction ID Mismatch</option>
                            <option value="Amount Mismatch">Amount Mismatch</option>
                            <option value="Duplicate Request">Duplicate Request</option>
                            <option value="Bank Service Down">Bank Service Down</option>
                        </select>
                        <textarea name="note" id="rejectNote" class="form-control bg-black text-white border-secondary" rows="3" placeholder="Enter specific reason..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-danger w-100 fw-bold shadow">CONFIRM REJECTION</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Use the constant defined by PHP
const API_BASE_URL = "<?= API_BASE_URL ?>";

function switchTab(tab) {
    document.getElementById('tab-deposits').style.display = tab === 'deposits' ? 'block' : 'none';
    document.getElementById('tab-withdrawals').style.display = tab === 'withdrawals' ? 'block' : 'none';
    document.querySelectorAll('#queue-tabs .nav-link').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
}

function openVerifyModal(tx, imageUrl) {
    document.getElementById('modalTxId').value = tx.id;
    document.getElementById('modalTxIdReject').value = tx.id; // Sync for switch
    document.getElementById('modalAmount').innerText = new Intl.NumberFormat().format(tx.amount) + ' MMK';
    document.getElementById('modalDigits').innerText = tx.transaction_last_digits || '------';
    
    // Set Image Source
    const imgEl = document.getElementById('modalProofImg');
    if (imageUrl) {
        imgEl.src = imageUrl;
        imgEl.style.display = 'block';
    } else {
        imgEl.style.display = 'none'; 
    }
    
    // Reset Checkbox
    document.getElementById('checkMatch').checked = false;
    
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
}

function openRejectModal(id) {
    document.getElementById('rejectTxId').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function switchToReject() {
    const id = document.getElementById('modalTxId').value;
    bootstrap.Modal.getInstance(document.getElementById('verifyModal')).hide();
    
    // Small delay to allow modal transition
    setTimeout(() => {
        openRejectModal(id);
    }, 300); 
}
</script>

<!-- Hidden Input for Modal Switching logic if needed -->
<input type="hidden" id="modalTxIdReject">

<?php require_once 'layout/footer.php'; ?>