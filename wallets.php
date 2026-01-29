<?php
require_once 'layout/header.php';

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $staffId = $_SESSION['finance_id'];
    
    // 1. DEPOSIT CHANNEL ACTIONS (Linked to Staff)
    if ($action === 'add') {
        try {
            $pdo->beginTransaction();

            $provider = trim($_POST['provider']);
            
            // Insert Deposit Method
            $sql = "INSERT INTO payment_methods (provider_name, account_name, account_number, admin_id, is_active) VALUES (?, ?, ?, ?, 1)";
            $pdo->prepare($sql)->execute([
                $provider, 
                $_POST['acc_name'], 
                $_POST['acc_num'],
                $staffId
            ]);
            
            // Auto-Create Withdrawal Option if requested
            if (isset($_POST['auto_create_withdraw'])) {
                // Check if this bank name already exists in withdrawal options
                $stmtCheck = $pdo->prepare("SELECT id FROM withdrawal_banks WHERE bank_name = ?");
                $stmtCheck->execute([$provider]);
                
                if ($stmtCheck->rowCount() == 0) {
                    // Add new withdrawal option, optionally linking to staff for tracking
                    // Assuming we added 'admin_id' to withdrawal_banks table in a migration
                    // If not, we just add it globally. 
                    // To support "show each user", we'd need that column. 
                    // For now, we'll insert standard global, or if column exists:
                    // $pdo->prepare("INSERT INTO withdrawal_banks (bank_name, is_active, admin_id) VALUES (?, 1, ?)")->execute([$provider, $staffId]);
                    
                    $pdo->prepare("INSERT INTO withdrawal_banks (bank_name, is_active) VALUES (?, 1)")->execute([$provider]);
                }
            }

            $pdo->commit();
            $msg = "Wallet added successfully.";
        } catch (Exception $e) { 
            $pdo->rollBack();
            $err = "Failed: " . $e->getMessage(); 
        }
    }
    elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $val = (int)$_POST['val'];
        $pdo->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ? AND admin_id = ?")->execute([$val, $id, $staffId]);
        $msg = "Status updated.";
    }
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM payment_methods WHERE id = ? AND admin_id = ?")->execute([$id, $staffId]);
        $msg = "Wallet deleted.";
    }

    // 2. WITHDRAWAL BANK ACTIONS
    elseif ($action === 'add_bank') {
        try {
            $bankName = trim($_POST['bank_name']);
            if(!empty($bankName)) {
                $pdo->prepare("INSERT INTO withdrawal_banks (bank_name, is_active) VALUES (?, 1)")->execute([$bankName]);
                $msg = "Withdrawal bank added to user list.";
            }
        } catch (Exception $e) { $err = "Failed: " . $e->getMessage(); }
    }
    elseif ($action === 'toggle_bank') {
        $id = (int)$_POST['id'];
        $val = (int)$_POST['val'];
        $pdo->prepare("UPDATE withdrawal_banks SET is_active = ? WHERE id = ?")->execute([$val, $id]);
        $msg = "Bank availability updated.";
    }
    elseif ($action === 'delete_bank') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM withdrawal_banks WHERE id = ?")->execute([$id]);
        $msg = "Bank removed.";
    }
}

// --- FETCH DATA ---
// 1. My Deposit Channels
$myWallets = $pdo->prepare("
    SELECT pm.*, a.username as manager 
    FROM payment_methods pm 
    LEFT JOIN admin_users a ON pm.admin_id = a.id 
    WHERE pm.admin_id = ?
    ORDER BY pm.is_active DESC, pm.id DESC
");
$myWallets->execute([$_SESSION['finance_id']]);
$myWallets = $myWallets->fetchAll();

// 2. Withdrawal Banks (Global List)
// In a fuller implementation, we could filter this by admin_id if we added that column
$withdrawalBanks = $pdo->query("SELECT * FROM withdrawal_banks ORDER BY is_active DESC, bank_name ASC")->fetchAll();

// 3. Supported Banks List for Dropdown
// We use the withdrawal banks as the source of truth for "Supported Providers"
$supportedBanks = $withdrawalBanks; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="text-white fw-bold mb-0">CHANNEL MANAGER</h4>
        <small class="text-muted">Manage your deposit accounts & global withdrawal options</small>
    </div>
    <div class="d-flex gap-2">
         <button class="btn btn-info fw-bold btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#addBankModal">
            <i class="bi bi-bank me-1"></i> ADD BANK
        </button>
        <button class="btn btn-warning fw-bold btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> NEW WALLET
        </button>
    </div>
</div>

<?php if(isset($msg)): ?><div class="alert alert-success alert-dismissible fade show shadow-sm border-0 border-start border-success border-4"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if(isset($err)): ?><div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-danger border-4"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- TABS -->
<ul class="nav nav-pills nav-fill mb-4 bg-dark rounded p-1 border border-secondary" id="wallet-tabs">
    <li class="nav-item">
        <button class="nav-link active rounded-pill" data-bs-toggle="pill" data-bs-target="#my-channels">
            MY DEPOSIT ACCOUNTS <span class="badge bg-white text-dark ms-1"><?= count($myWallets) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link rounded-pill" data-bs-toggle="pill" data-bs-target="#global-banks">
            WITHDRAWAL OPTIONS <span class="badge bg-white text-dark ms-1"><?= count($withdrawalBanks) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">
    
    <!-- 1. MY DEPOSIT CHANNELS -->
    <div class="tab-pane fade show active" id="my-channels">
        <div class="row g-3">
            <?php foreach($myWallets as $w): 
                $isActive = $w['is_active'];
                $iconClass = 'bi-wallet2';
                if (stripos($w['provider_name'], 'kbz') !== false) $iconClass = 'bi-credit-card-2-front text-primary';
                if (stripos($w['provider_name'], 'wave') !== false) $iconClass = 'bi-phone text-warning';
                if (stripos($w['provider_name'], 'cb') !== false) $iconClass = 'bi-bank text-orange';
                if (stripos($w['provider_name'], 'usdt') !== false) $iconClass = 'bi-currency-bitcoin text-success';
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-start border-4 <?= $isActive ? 'border-success' : 'border-secondary' ?> shadow-sm bg-opacity-10">
                    <div class="card-body position-relative">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-black bg-opacity-50 p-2 rounded fs-4 text-white"><i class="bi <?= $iconClass ?>"></i></div>
                                <div>
                                    <h5 class="fw-bold text-white mb-0"><?= htmlspecialchars($w['provider_name']) ?></h5>
                                    <span class="badge bg-info text-dark" style="font-size: 0.6rem;">INBOUND</span>
                                </div>
                            </div>
                            
                            <div class="form-check form-switch">
                                <form method="POST" id="toggle-form-<?= $w['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <input type="hidden" name="val" value="<?= $isActive ? 0 : 1 ?>">
                                    <input class="form-check-input" type="checkbox" onchange="document.getElementById('toggle-form-<?= $w['id'] ?>').submit()" <?= $isActive ? 'checked' : '' ?> style="cursor: pointer; width: 2.5em; height: 1.25em;">
                                </form>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="bg-dark bg-opacity-50 p-3 rounded mb-3 border border-secondary border-opacity-25">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small text-uppercase">Account Name</span>
                                <span class="text-white small fw-bold text-end"><?= htmlspecialchars($w['account_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small text-uppercase">Number</span>
                                <span class="text-info font-monospace fw-bold fs-5 text-end"><?= htmlspecialchars($w['account_number']) ?></span>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="d-flex justify-content-between align-items-center border-top border-secondary border-opacity-25 pt-2">
                            <span class="badge <?= $isActive ? 'bg-success bg-opacity-25 text-success' : 'bg-secondary bg-opacity-25 text-secondary' ?>">
                                <i class="bi bi-circle-fill" style="font-size: 6px; vertical-align: middle;"></i> <?= $isActive ? 'ACTIVE' : 'HIDDEN' ?>
                            </span>
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-light border-0" onclick='openEditModal(<?= json_encode($w) ?>)' title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Permanently delete this wallet?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger border-0" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($myWallets)): ?>
                <div class="col-12 text-center text-muted py-5 border border-dashed border-secondary rounded-3">
                    <i class="bi bi-wallet2 fs-1 d-block mb-3 opacity-50"></i>
                    No accounts found. Click <b>New Wallet</b> to start.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. GLOBAL WITHDRAWAL BANKS -->
    <div class="tab-pane fade" id="global-banks">
        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                <span class="text-white fw-bold">Supported Withdrawal Options</span>
                <span class="badge bg-secondary"><?= count($withdrawalBanks) ?> Total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-secondary small text-uppercase">
                            <th>Provider</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($withdrawalBanks as $wb): $isActive = $wb['is_active']; ?>
                        <tr class="<?= $isActive ? '' : 'opacity-50' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-bank2 text-secondary fs-5"></i>
                                    <span class="fw-bold"><?= htmlspecialchars($wb['bank_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <form method="POST" id="toggle-bank-<?= $wb['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_bank">
                                    <input type="hidden" name="id" value="<?= $wb['id'] ?>">
                                    <input type="hidden" name="val" value="<?= $isActive ? 0 : 1 ?>">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" onchange="document.getElementById('toggle-bank-<?= $wb['id'] ?>').submit()" <?= $isActive ? 'checked' : '' ?> style="cursor: pointer;">
                                        <label class="form-check-label small text-muted"><?= $isActive ? 'Enabled' : 'Disabled' ?></label>
                                    </div>
                                </form>
                            </td>
                            <td class="text-end">
                                <form method="POST" onsubmit="return confirm('Remove this bank option from user withdrawal list?');">
                                    <input type="hidden" name="action" value="delete_bank">
                                    <input type="hidden" name="id" value="<?= $wb['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($withdrawalBanks)) echo '<tr><td colspan="3" class="text-center text-muted py-4">No banks defined.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- DATA LIST FOR PROVIDERS -->
<datalist id="bankOptions">
    <?php foreach($withdrawalBanks as $wb): ?>
        <option value="<?= htmlspecialchars($wb['bank_name']) ?>">
    <?php endforeach; ?>
    <option value="KBZPay">
    <option value="WavePay">
    <option value="CB Pay">
    <option value="AYA Pay">
    <option value="USDT (TRC20)">
</datalist>

<!-- Modal: Add/Edit Wallet -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="walletModalTitle">Add Deposit Channel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" id="walletAction" value="add">
                    <input type="hidden" name="id" id="walletId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Provider Name</label>
                        <input type="text" name="provider" id="walletProvider" class="form-control bg-black text-white border-secondary" list="bankOptions" placeholder="Type or select..." required>
                        <div class="form-text text-muted small">e.g. KBZPay, WavePay</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Account Name</label>
                        <input type="text" name="acc_name" id="walletName" class="form-control bg-black text-white border-secondary" placeholder="e.g. U Ba" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Account Number</label>
                        <input type="text" name="acc_num" id="walletNum" class="form-control bg-black text-white border-secondary" placeholder="09..." required>
                    </div>

                    <div class="form-check mb-3" id="autoCreateDiv">
                        <input class="form-check-input" type="checkbox" name="auto_create_withdraw" value="1" id="autoCheck" checked>
                        <label class="form-check-label text-white small" for="autoCheck">
                            Also enable this Provider for User Withdrawals?
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 fw-bold">SAVE WALLET</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Bank Option -->
<div class="modal fade" id="addBankModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Add Withdrawal Option</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_bank">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control bg-black text-white border-secondary" placeholder="e.g. PayPal" required>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold text-dark">ADD TO LIST</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openEditModal(wallet) {
    document.getElementById('walletModalTitle').innerText = 'Edit Wallet';
    document.getElementById('walletAction').value = 'edit';
    document.getElementById('walletId').value = wallet.id;
    document.getElementById('walletProvider').value = wallet.provider_name;
    document.getElementById('walletName').value = wallet.account_name;
    document.getElementById('walletNum').value = wallet.account_number;
    
    // Hide auto-create on edit
    const autoDiv = document.getElementById('autoCreateDiv');
    if(autoDiv) autoDiv.style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

// Reset modal on close
const addModalEl = document.getElementById('addModal');
if(addModalEl) {
    addModalEl.addEventListener('hidden.bs.modal', function () {
        document.getElementById('walletModalTitle').innerText = 'Add Deposit Channel';
        document.getElementById('walletAction').value = 'add';
        document.getElementById('walletId').value = '';
        document.getElementById('walletProvider').value = '';
        document.getElementById('walletName').value = '';
        document.getElementById('walletNum').value = '';
        const autoDiv = document.getElementById('autoCreateDiv');
        if(autoDiv) autoDiv.style.display = 'block';
    });
}
</script>

<?php require_once 'layout/footer.php'; ?>