<?php
require_once 'layout/header.php';

if (!defined('API_BASE_URL')) {
    // Determine API URL securely via functions
    define('API_BASE_URL', getEnvSafe('API_PUBLIC_URL')); 
}

$staffId = $_SESSION['finance_id'];
$viewMode = $_GET['view'] ?? 'my'; 

// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $txId = (int)$_POST['tx_id'];
    $decision = $_POST['decision']; 
    $note = trim($_POST['note']);

    if ($decision === 'reject' && empty($note)) {
        $err = "Reason required for rejection.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$txId]);
            $tx = $stmt->fetch();

            if ($tx) {
                if ($tx['processed_by_admin_id'] !== null && $tx['processed_by_admin_id'] != $staffId) {
                    throw new Exception("Conflict: Reserved by another agent.");
                }

                $status = ($decision === 'approve') ? 'approved' : 'rejected';
                
                if ($status === 'approved' && $tx['type'] === 'deposit') {
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
                } elseif ($status === 'rejected' && $tx['type'] === 'withdraw') {
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
                }
                
                $finalNote = $note ?: ($status === 'approved' ? 'Processed' : 'Rejected');
                $pdo->prepare("UPDATE transactions SET status = ?, processed_by_admin_id = ?, admin_note = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$status, $staffId, $finalNote, $txId]);
                
                $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'transactions')")
                    ->execute([$staffId, ucfirst($status) . " TX #$txId"]);

                $pdo->commit();
                $msg = "TX #$txId processed!";
            } else {
                throw new Exception("TX not found or already handled.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = "Error: " . $e->getMessage();
        }
    }
}

// --- FETCH QUEUES ---
$depSql = "SELECT t.*, u.username, u.phone, pm.provider_name, pm.admin_id as wallet_owner_id, a.username as wallet_owner_name FROM transactions t JOIN users u ON t.user_id = u.id LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id LEFT JOIN admin_users a ON pm.admin_id = a.id WHERE t.type = 'deposit' AND t.status = 'pending'";
if ($viewMode === 'my') $depSql .= " AND pm.admin_id = $staffId";
$deposits = $pdo->query($depSql . " ORDER BY t.created_at ASC")->fetchAll();

$withSql = "SELECT t.*, u.username, u.phone, u.balance as current_bal, a.username as assigned_to_name FROM transactions t JOIN users u ON t.user_id = u.id LEFT JOIN admin_users a ON t.processed_by_admin_id = a.id WHERE t.type = 'withdraw' AND t.status = 'pending'";
if ($viewMode === 'my') $withSql .= " AND (t.processed_by_admin_id = $staffId OR t.processed_by_admin_id IS NULL)";
$withdrawals = $pdo->query($withSql . " ORDER BY (t.processed_by_admin_id = $staffId) DESC, t.created_at ASC")->fetchAll();
?>

<!-- V2 HEADER & TOGGLES -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-black m-0 italic tracking-widest text-white">LIVE QUEUE</h4>
    <div class="bg-dark rounded-pill p-1 border border-secondary shadow-sm">
        <a href="?view=my" class="btn btn-sm rounded-pill <?= $viewMode==='my' ? 'btn-info fw-bold text-dark' : 'text-muted' ?>">MINE</a>
        <a href="?view=all" class="btn btn-sm rounded-pill <?= $viewMode==='all' ? 'btn-info fw-bold text-dark' : 'text-muted' ?>">ALL</a>
    </div>
</div>

<?php if(isset($msg)): ?><div class="alert bg-success bg-opacity-25 text-success border border-success fw-bold small rounded-3 shadow-sm"><i class="bi bi-check-circle-fill"></i> <?= $msg ?></div><?php endif; ?>
<?php if(isset($err)): ?><div class="alert bg-danger bg-opacity-25 text-danger border border-danger fw-bold small rounded-3 shadow-sm"><i class="bi bi-x-circle-fill"></i> <?= $err ?></div><?php endif; ?>

<!-- MOBILE TAB SELECTOR -->
<div class="d-flex gap-2 mb-3">
    <button class="flex-fill btn btn-dark py-3 fw-bold rounded-4 border-success text-success border-2 active-tab-btn shadow-lg" id="btn-dep" onclick="switchTab('deposits')">
        <i class="bi bi-arrow-down-circle"></i> DEPOSITS <span class="badge bg-success ms-1"><?= count($deposits) ?></span>
    </button>
    <button class="flex-fill btn btn-dark py-3 fw-bold rounded-4 border-secondary text-muted" id="btn-with" onclick="switchTab('withdrawals')">
        <i class="bi bi-arrow-up-circle"></i> PAYOUTS <span class="badge bg-danger ms-1"><?= count($withdrawals) ?></span>
    </button>
</div>

<!-- 1. DEPOSIT CARDS (V2 MOBILE UI) -->
<div id="tab-deposits">
    <?php foreach($deposits as $d): 
        $isMyWallet = ($d['wallet_owner_id'] == $staffId);
        $proofUrl = $d['proof_image'] ? rtrim(API_BASE_URL, '/') . '/' . ltrim($d['proof_image'], '/') : '';
    ?>
    <div class="glass-card mb-3 p-3 position-relative overflow-hidden <?= !$isMyWallet ? 'opacity-75' : 'border-success' ?>">
        
        <?php if($isMyWallet): ?>
            <div class="position-absolute top-0 end-0 bg-success text-dark px-3 py-1 rounded-bl-3 fw-bold" style="font-size: 0.65rem;">YOUR TASK</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-start mb-3 mt-2">
            <div class="d-flex align-items-center gap-3">
                <!-- Proof Thumbnail -->
                <?php if($proofUrl): ?>
                    <div class="rounded-3 border border-secondary overflow-hidden shadow-sm position-relative" style="width: 65px; height: 65px; cursor: pointer;" onclick='openVerifyModal(<?= json_encode($d) ?>, "<?= $proofUrl ?>")'>
                        <img src="<?= $proofUrl ?>" class="w-100 h-100 object-fit-cover" alt="Proof">
                        <div class="position-absolute inset-0 bg-black bg-opacity-25 d-flex align-items-center justify-content-center">
                            <i class="bi bi-zoom-in text-white fs-5 drop-shadow"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="rounded-3 border border-danger bg-danger bg-opacity-25 text-danger d-flex flex-column align-items-center justify-content-center" style="width: 65px; height: 65px;">
                        <i class="bi bi-image-alt fs-4"></i>
                        <span style="font-size: 0.5rem;">NO IMG</span>
                    </div>
                <?php endif; ?>
                
                <div>
                    <h4 class="fw-black text-white m-0 font-mono tracking-tight text-success">+<?= number_format($d['amount']) ?> <span style="font-size:0.5em; color:#94a3b8;">MMK</span></h4>
                    <div class="text-muted fw-bold text-uppercase mt-1" style="font-size:0.65rem;">
                        <i class="bi bi-bank2"></i> <?= htmlspecialchars($d['provider_name'] ?? 'Bank') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-black bg-opacity-50 rounded-3 p-2 d-flex justify-content-between align-items-center mb-3 border border-white border-opacity-10">
            <div>
                <span class="text-muted d-block" style="font-size:0.6rem; text-transform:uppercase;">Player</span>
                <span class="text-light fw-bold" style="font-size:0.8rem;"><?= htmlspecialchars($d['username']) ?></span>
            </div>
            <div class="text-end">
                <span class="text-muted d-block" style="font-size:0.6rem; text-transform:uppercase;">Ref ID (Last 6)</span>
                <span class="font-mono text-warning fw-bold letter-spacing-1"><?= htmlspecialchars($d['transaction_last_digits'] ?? '------') ?></span>
            </div>
        </div>
        
        <?php if($isMyWallet): ?>
            <button class="btn btn-success w-100 fw-black shadow-lg py-2" onclick='openVerifyModal(<?= json_encode($d) ?>, "<?= $proofUrl ?>")'>
                <i class="bi bi-shield-check"></i> INSPECT & PROCESS
            </button>
        <?php else: ?>
            <div class="text-center text-muted small bg-black bg-opacity-25 py-2 rounded">
                <i class="bi bi-lock-fill"></i> Managed by: <?= htmlspecialchars($d['wallet_owner_name']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; if(empty($deposits)) echo '<div class="text-center text-muted py-5"><i class="bi bi-cup-hot fs-1 d-block mb-2 opacity-50"></i>Inbox Zero!</div>'; ?>
</div>

<!-- 2. WITHDRAWAL CARDS (V2 MOBILE UI) -->
<div id="tab-withdrawals" style="display:none;">
    <?php foreach($withdrawals as $w): 
        $isMyTask = ($w['processed_by_admin_id'] == $staffId);
        $isUnassigned = ($w['processed_by_admin_id'] === null);
        preg_match('/\((.*?)\)/', $w['admin_note'], $matches);
        $providerDisplay = $matches[1] ?? 'Unknown';
        
        // Extract Phone from Note for easy copying (Assume format "Target: 09123... (KBZ)")
        preg_match('/Target:\s*(\d+)/', $w['admin_note'], $phoneMatches);
        $targetPhone = $phoneMatches[1] ?? $w['phone'];
    ?>
    <div class="glass-card mb-3 p-3 <?= $isMyTask ? 'border-danger bg-danger bg-opacity-10' : 'border-secondary' ?>">
        
        <?php if($isMyTask): ?>
            <div class="badge bg-danger mb-2 w-100 text-start py-2"><i class="bi bi-pin-angle-fill"></i> ASSIGNED TO YOU</div>
        <?php elseif($isUnassigned): ?>
            <div class="badge bg-secondary text-dark mb-2 w-100 text-start py-2"><i class="bi bi-globe"></i> OPEN POOL</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-50">
            <div>
                <span class="text-muted d-block" style="font-size:0.6rem; text-transform:uppercase;">Player</span>
                <span class="fw-bold text-white"><?= htmlspecialchars($w['username']) ?></span>
            </div>
            <div class="text-end">
                <span class="text-muted d-block" style="font-size:0.6rem; text-transform:uppercase;">Available Balance</span>
                <span class="font-mono text-success fw-bold"><?= number_format($w['current_bal']) ?></span>
            </div>
        </div>

        <div class="bg-black bg-opacity-40 p-3 rounded-3 mb-3 border border-danger border-opacity-25">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small fw-bold">PAYOUT AMOUNT</span>
                <h3 class="fw-black text-danger m-0 font-mono">-<?= number_format($w['amount']) ?></h3>
            </div>
            <div class="d-flex justify-content-between align-items-center border-top border-white border-opacity-10 pt-2">
                <span class="badge bg-dark border border-secondary text-light fs-6"><?= htmlspecialchars($providerDisplay) ?></span>
                <div class="d-flex align-items-center gap-2 bg-secondary bg-opacity-25 px-3 py-1 rounded-pill cursor-pointer hover:bg-opacity-50 transition" onclick="copyToClip('<?= $targetPhone ?>')">
                    <span class="font-mono text-info fw-bold fs-6"><?= $targetPhone ?></span>
                    <i class="bi bi-copy text-white"></i>
                </div>
            </div>
        </div>

        <?php if($isMyTask || $isUnassigned): ?>
            <div class="row g-2">
                <div class="col-4">
                    <button class="btn btn-dark w-100 fw-bold border-danger text-danger py-2" onclick="openRejectModal(<?= $w['id'] ?>)">REJECT</button>
                </div>
                <div class="col-8">
                    <form method="POST">
                        <input type="hidden" name="action" value="process">
                        <input type="hidden" name="tx_id" value="<?= $w['id'] ?>">
                        <input type="hidden" name="decision" value="approve">
                        <input type="hidden" name="note" value="Sent via <?= htmlspecialchars($providerDisplay) ?>">
                        <button class="btn btn-danger w-100 fw-black shadow-lg py-2">MARK AS PAID <i class="bi bi-send-check ms-1"></i></button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center text-muted small bg-black py-2 rounded"><i class="bi bi-lock"></i> Processing by: <?= htmlspecialchars($w['assigned_to_name']) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; if(empty($withdrawals)) echo '<div class="text-center text-muted py-5"><i class="bi bi-emoji-sunglasses fs-1 d-block mb-2 opacity-50"></i>No pending payouts.</div>'; ?>
</div>

<!-- V2 ACTION MODALS (Bottom Sheet Style for Image Checking) -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content glass-card" style="border:none; border-top: 1px solid rgba(255,255,255,0.2); border-radius: 20px 20px 0 0; background: #111;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-black text-white italic tracking-widest"><i class="bi bi-search text-info"></i> INSPECT DEPOSIT</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body pt-3">
                <!-- Large Image Preview Area -->
                <div class="bg-black rounded-4 p-2 mb-3 shadow-inner position-relative border border-secondary" style="height: 35vh; min-height: 250px; display:flex; align-items:center; justify-content:center; overflow: hidden;">
                    <img id="modalProofImg" src="" class="img-fluid" style="object-fit: contain; width:100%; height:100%; transition: transform 0.3s;" alt="Receipt">
                    
                    <!-- Overlay Controls -->
                    <div class="position-absolute bottom-0 end-0 p-2">
                        <a id="fullResBtn" href="#" target="_blank" class="btn btn-sm btn-dark border-secondary shadow-lg">
                            <i class="bi bi-box-arrow-up-right text-info"></i> Full Res
                        </a>
                    </div>
                </div>
                
                <!-- Quick Compare Data -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="bg-dark bg-opacity-50 p-2 rounded-3 border border-success h-100 text-center">
                            <span class="text-muted d-block mb-1" style="font-size: 0.65rem; text-transform:uppercase;">Expected Amount</span>
                            <h4 class="text-success fw-black font-mono m-0" id="modalAmount"></h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-dark bg-opacity-50 p-2 rounded-3 border border-warning h-100 text-center">
                            <span class="text-muted d-block mb-1" style="font-size: 0.65rem; text-transform:uppercase;">Ref (Last 6)</span>
                            <h4 class="text-warning fw-black font-mono m-0 letter-spacing-1" id="modalDigits"></h4>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary opacity-25 my-3">
                
                <!-- Action Forms -->
                <form method="POST" id="approveForm">
                    <input type="hidden" name="action" value="process">
                    <input type="hidden" name="tx_id" id="modalTxId">
                    <input type="hidden" name="decision" value="approve">
                    <input type="hidden" name="note" value="Verified & Approved">
                    
                    <div class="form-check text-start mb-3 ps-4 bg-success bg-opacity-10 border border-success border-opacity-25 rounded p-2">
                        <input class="form-check-input border-success mt-1" type="checkbox" required id="checkMatch" style="cursor:pointer; transform: scale(1.2);">
                        <label class="form-check-label text-success fw-bold ms-2" for="checkMatch">
                            Data matches receipt exactly.
                        </label>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-4">
                            <button type="button" class="btn btn-dark border-danger text-danger w-100 fw-bold py-3" onclick="switchToReject()">
                                <i class="bi bi-x-lg"></i> REJECT
                            </button>
                        </div>
                        <div class="col-8">
                            <button type="submit" class="btn btn-success w-100 fw-black py-3 shadow-lg fs-5">
                                APPROVE <i class="bi bi-check2-circle ms-1"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: REJECT REASON -->
<div class="modal fade" id="rejectModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-danger bg-dark">
            <div class="modal-header border-danger text-danger py-3 bg-danger bg-opacity-10">
                <h5 class="modal-title fw-black"><i class="bi bi-exclamation-triangle-fill me-2"></i> REJECT TRANSACTION</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="process">
                    <input type="hidden" name="tx_id" id="rejectTxId">
                    <input type="hidden" name="decision" value="reject">
                    
                    <label class="form-label text-white small fw-bold">Reason for Rejection (Visible to Player)</label>
                    <select name="note_select" class="form-select bg-black text-white border-secondary mb-3 py-2 rounded-3 shadow-sm" onchange="document.getElementById('rejectNote').value = this.value">
                        <option value="">-- Quick Select Reason --</option>
                        <option value="Invalid Proof / Blurry Image">Invalid Proof / Blurry Image</option>
                        <option value="Transaction ID Mismatch">Transaction ID Mismatch</option>
                        <option value="Amount Mismatch">Amount Mismatch</option>
                        <option value="Duplicate Request Detected">Duplicate Request</option>
                        <option value="Bank Maintenance - Try Again Later">Bank Maintenance - Try Again Later</option>
                    </select>
                    
                    <textarea name="note" id="rejectNote" class="form-control bg-black text-white border-secondary rounded-3 mb-4 font-monospace" rows="3" placeholder="Or type a custom reason..." required></textarea>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary w-50 py-3 fw-bold" data-bs-dismiss="modal">CANCEL</button>
                        <button type="submit" class="btn btn-danger w-50 py-3 fw-black shadow-lg">CONFIRM REJECT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('tab-deposits').style.display = tab === 'deposits' ? 'block' : 'none';
    document.getElementById('tab-withdrawals').style.display = tab === 'withdrawals' ? 'block' : 'none';
    
    document.getElementById('btn-dep').className = tab === 'deposits' 
        ? 'flex-fill btn btn-dark py-3 fw-bold rounded-4 border-success text-success border-2 shadow-lg' 
        : 'flex-fill btn btn-dark py-3 fw-bold rounded-4 border-secondary text-muted';
        
    document.getElementById('btn-with').className = tab === 'withdrawals' 
        ? 'flex-fill btn btn-dark py-3 fw-bold rounded-4 border-danger text-danger border-2 shadow-lg' 
        : 'flex-fill btn btn-dark py-3 fw-bold rounded-4 border-secondary text-muted';
}

function openVerifyModal(tx, imageUrl) {
    // Populate Data
    document.getElementById('modalTxId').value = tx.id;
    document.getElementById('modalTxIdReject').value = tx.id; 
    document.getElementById('modalAmount').innerText = new Intl.NumberFormat().format(tx.amount);
    document.getElementById('modalDigits').innerText = tx.transaction_last_digits || '------';
    
    // Handle Image
    const imgEl = document.getElementById('modalProofImg');
    const fullResBtn = document.getElementById('fullResBtn');
    
    if (imageUrl) {
        imgEl.src = imageUrl;
        imgEl.style.display = 'block';
        fullResBtn.href = imageUrl;
        fullResBtn.style.display = 'inline-block';
    } else {
        imgEl.style.display = 'none'; 
        fullResBtn.style.display = 'none';
    }
    
    // Reset Safety Checkbox
    document.getElementById('checkMatch').checked = false;
    
    // Show Modal
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
}

function openRejectModal(id) {
    document.getElementById('rejectTxId').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function switchToReject() {
    const id = document.getElementById('modalTxId').value;
    
    // Hide Verify Modal
    const verifyModalEl = document.getElementById('verifyModal');
    const verifyModal = bootstrap.Modal.getInstance(verifyModalEl);
    if(verifyModal) verifyModal.hide();
    
    // Wait for transition to finish, then open Reject Modal
    setTimeout(() => {
        openRejectModal(id);
    }, 400); 
}

function copyToClip(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Phone number copied: ' + text);
        });
    } else {
        // Fallback
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        alert('Phone number copied: ' + text);
    }
}
</script>

<!-- Hidden Input for passing ID between modals -->
<input type="hidden" id="modalTxIdReject">

<?php require_once 'layout/footer.php'; ?>