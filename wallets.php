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
            $accName = trim($_POST['acc_name']);
            $accNum = trim($_POST['acc_num']);
            
            if ($action === 'edit' && !empty($_POST['id'])) {
                // Edit existing
                $id = (int)$_POST['id'];
                $sql = "UPDATE payment_methods SET provider_name=?, account_name=?, account_number=? WHERE id=? AND admin_id=?";
                $pdo->prepare($sql)->execute([$provider, $accName, $accNum, $id, $staffId]);
                $msg = "Wallet updated successfully.";
            } else {
                // Insert Deposit Method
                $sql = "INSERT INTO payment_methods (provider_name, account_name, account_number, admin_id, is_active) VALUES (?, ?, ?, ?, 1)";
                $pdo->prepare($sql)->execute([$provider, $accName, $accNum, $staffId]);
                
                // Auto-Create Withdrawal Option if requested
                if (isset($_POST['auto_create_withdraw'])) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM withdrawal_banks WHERE bank_name = ?");
                    $stmtCheck->execute([$provider]);
                    
                    if ($stmtCheck->rowCount() == 0) {
                        $pdo->prepare("INSERT INTO withdrawal_banks (bank_name, is_active) VALUES (?, 1)")->execute([$provider]);
                    }
                }
                $msg = "Wallet added successfully.";
            }

            $pdo->commit();
        } catch (Exception $e) { 
            if($pdo->inTransaction()) $pdo->rollBack();
            $err = "Failed: " . $e->getMessage(); 
        }
    }
    elseif ($action === 'edit') { // Dedicated edit catch
        try {
            $id = (int)$_POST['id'];
            $provider = trim($_POST['provider']);
            $accName = trim($_POST['acc_name']);
            $accNum = trim($_POST['acc_num']);
            $sql = "UPDATE payment_methods SET provider_name=?, account_name=?, account_number=? WHERE id=? AND admin_id=?";
            $pdo->prepare($sql)->execute([$provider, $accName, $accNum, $id, $staffId]);
            $msg = "Wallet updated successfully.";
        } catch (Exception $e) {
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
$withdrawalBanks = $pdo->query("SELECT * FROM withdrawal_banks ORDER BY is_active DESC, bank_name ASC")->fetchAll();
?>

<!-- Sakura Particles Integration -->
<style>
    #sakura-container-wallets { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.3; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.3; } 90% { opacity: 0.3; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    .dash-wrapper { position: relative; z-index: 10; }
</style>
<div id="sakura-container-wallets"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-wallets');
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
            <h3 class="text-white fw-black mb-0 italic tracking-widest">CHANNELS</h3>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 2px;">チャネル管理</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm px-3 py-2 rounded-pill fw-bold border border-info text-info shadow-[0_0_10px_rgba(13,202,240,0.3)] hover:bg-info hover:text-dark transition" data-bs-toggle="modal" data-bs-target="#addBankModal">
                <i class="bi bi-bank me-1"></i> ADD BANK
            </button>
            <button class="btn btn-sm px-3 py-2 rounded-pill fw-bold border border-warning text-warning shadow-[0_0_10px_rgba(255,193,7,0.3)] hover:bg-warning hover:text-dark transition" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i> NEW WALLET
            </button>
        </div>
    </div>

    <?php if(isset($msg)): ?><div class="alert bg-success bg-opacity-25 text-success border border-success fw-bold small rounded-4 shadow-sm animate-pulse"><i class="bi bi-check-circle-fill"></i> <?= $msg ?></div><?php endif; ?>
    <?php if(isset($err)): ?><div class="alert bg-danger bg-opacity-25 text-danger border border-danger fw-bold small rounded-4 shadow-sm animate-pulse"><i class="bi bi-x-circle-fill"></i> <?= $err ?></div><?php endif; ?>

    <!-- V2 GLASS TABS -->
    <ul class="nav nav-pills nav-fill mb-4 bg-black bg-opacity-40 rounded-4 p-1 border border-white border-opacity-10 shadow-sm" id="wallet-tabs">
        <li class="nav-item">
            <button class="nav-link active rounded-pill fw-bold tracking-widest text-xs py-3" data-bs-toggle="pill" data-bs-target="#my-channels" style="transition: all 0.3s;">
                MY DEPOSITS <span class="badge bg-black text-white ms-1 border border-secondary"><?= count($myWallets) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill fw-bold tracking-widest text-xs py-3 text-muted" data-bs-toggle="pill" data-bs-target="#global-banks" style="transition: all 0.3s;" onclick="this.classList.remove('text-muted'); this.previousElementSibling.classList.add('text-muted');">
                WITHDRAW OPTIONS <span class="badge bg-black text-white ms-1 border border-secondary"><?= count($withdrawalBanks) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content pb-4">
        
        <!-- 1. MY DEPOSIT CHANNELS -->
        <div class="tab-pane fade show active" id="my-channels">
            <div class="row g-3">
                <?php foreach($myWallets as $w): 
                    $isActive = $w['is_active'];
                    
                    // Brand Colors
                    $borderColor = 'border-white border-opacity-10';
                    $iconClass = 'bi-wallet2 text-white';
                    $iconBg = 'bg-secondary';
                    $glow = '';

                    if (stripos($w['provider_name'], 'kbz') !== false) { $iconClass = 'bi-credit-card-2-front text-white'; $iconBg = 'bg-primary'; $borderColor = $isActive ? 'border-primary' : $borderColor; $glow = $isActive ? 'shadow-[0_0_20px_rgba(13,110,253,0.2)]' : ''; }
                    if (stripos($w['provider_name'], 'wave') !== false) { $iconClass = 'bi-phone text-dark'; $iconBg = 'bg-warning'; $borderColor = $isActive ? 'border-warning' : $borderColor; $glow = $isActive ? 'shadow-[0_0_20px_rgba(255,193,7,0.2)]' : ''; }
                    if (stripos($w['provider_name'], 'cb') !== false) { $iconClass = 'bi-bank text-white'; $iconBg = 'bg-orange-500'; $borderColor = $isActive ? 'border-orange-500' : $borderColor; }
                    if (stripos($w['provider_name'], 'usdt') !== false) { $iconClass = 'bi-currency-bitcoin text-white'; $iconBg = 'bg-success'; $borderColor = $isActive ? 'border-success' : $borderColor; $glow = $isActive ? 'shadow-[0_0_20px_rgba(25,135,84,0.2)]' : ''; }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="glass-card h-100 border border-2 <?= $borderColor ?> <?= $glow ?> p-4 position-relative overflow-hidden <?= !$isActive ? 'opacity-75 grayscale-[30%]' : '' ?>">
                        
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3 relative z-10">
                            <div class="d-flex align-items-center gap-3">
                                <div class="<?= $iconBg ?> p-3 rounded-circle fs-4 shadow-sm"><i class="bi <?= $iconClass ?>"></i></div>
                                <div>
                                    <h5 class="fw-black text-white m-0 italic tracking-wide"><?= htmlspecialchars($w['provider_name']) ?></h5>
                                    <span class="text-muted font-mono" style="font-size: 0.65rem;">ID: <?= $w['id'] ?></span>
                                </div>
                            </div>
                            
                            <div class="form-check form-switch">
                                <form method="POST" id="toggle-form-<?= $w['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <input type="hidden" name="val" value="<?= $isActive ? 0 : 1 ?>">
                                    <input class="form-check-input" type="checkbox" onchange="document.getElementById('toggle-form-<?= $w['id'] ?>').submit()" <?= $isActive ? 'checked' : '' ?> style="cursor: pointer; width: 3em; height: 1.5em;">
                                </form>
                            </div>
                        </div>

                        <!-- Account Details -->
                        <div class="bg-black bg-opacity-60 p-3 rounded-3 mb-4 border border-white border-opacity-5 relative z-10">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-gray-500 small text-uppercase fw-bold tracking-widest" style="font-size: 0.65rem;">Account Name</span>
                                <span class="text-white fw-bold small text-end"><?= htmlspecialchars($w['account_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-gray-500 small text-uppercase fw-bold tracking-widest" style="font-size: 0.65rem;">Number / Address</span>
                                <span class="text-cyan-400 font-mono fw-black fs-6 text-end letter-spacing-1"><?= htmlspecialchars($w['account_number']) ?></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between align-items-center relative z-10">
                            <?php if($isActive): ?>
                                <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-50 px-2 py-1"><span class="spinner-grow spinner-grow-sm me-1" style="width: 4px; height: 4px;"></span> LIVE</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-20 text-secondary border border-secondary px-2 py-1">OFFLINE</span>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm bg-white bg-opacity-10 text-white hover:bg-opacity-20 border-0 rounded-circle" style="width: 32px; height: 32px;" onclick='openEditModal(<?= json_encode($w) ?>)' title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Permanently delete this wallet?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <button class="btn btn-sm bg-danger bg-opacity-20 text-danger hover:bg-opacity-40 border-0 rounded-circle" style="width: 32px; height: 32px;" title="Delete"><i class="bi bi-trash3-fill"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if(empty($myWallets)): ?>
                    <div class="col-12">
                        <div class="glass-card text-center text-muted py-5 border border-dashed border-secondary">
                            <i class="bi bi-wallet-fill display-1 d-block mb-3 opacity-25"></i>
                            <h5 class="text-white fw-bold">No Deposit Accounts</h5>
                            <p class="small">Click <strong class="text-warning">NEW WALLET</strong> to add your receiving accounts.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. GLOBAL WITHDRAWAL BANKS -->
        <div class="tab-pane fade" id="global-banks">
            <div class="row g-3">
                <?php foreach($withdrawalBanks as $wb): $isActive = $wb['is_active']; ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="glass-card p-3 border border-white border-opacity-10 text-center h-100 d-flex flex-column justify-content-between <?= $isActive ? 'hover:border-info transition-colors' : 'opacity-50 grayscale' ?>">
                        
                        <div class="d-flex justify-content-end mb-2">
                             <form method="POST" id="toggle-bank-<?= $wb['id'] ?>">
                                <input type="hidden" name="action" value="toggle_bank">
                                <input type="hidden" name="id" value="<?= $wb['id'] ?>">
                                <input type="hidden" name="val" value="<?= $isActive ? 0 : 1 ?>">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" onchange="document.getElementById('toggle-bank-<?= $wb['id'] ?>').submit()" <?= $isActive ? 'checked' : '' ?> style="cursor: pointer;">
                                </div>
                            </form>
                        </div>

                        <div class="mb-3">
                            <div class="bg-black bg-opacity-50 rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-inner" style="width: 50px; height: 50px;">
                                <?php if($wb['logo_url']): ?>
                                    <img src="<?= htmlspecialchars($wb['logo_url']) ?>" width="28" height="28" class="rounded-circle object-fit-cover">
                                <?php else: ?>
                                    <i class="bi bi-bank2 text-info fs-3"></i>
                                <?php endif; ?>
                            </div>
                            <h6 class="text-white fw-bold m-0 tracking-wide"><?= htmlspecialchars($wb['bank_name']) ?></h6>
                            <span class="text-muted" style="font-size: 0.6rem;">USER OPTION</span>
                        </div>

                        <div>
                             <form method="POST" onsubmit="return confirm('Remove this bank entirely?');">
                                <input type="hidden" name="action" value="delete_bank">
                                <input type="hidden" name="id" value="<?= $wb['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger w-100 rounded-pill text-[10px] fw-bold border-opacity-50">REMOVE</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if(empty($withdrawalBanks)): ?>
                     <div class="col-12 text-center text-muted py-5 glass-card border border-dashed border-secondary">
                        No global withdrawal options set.
                    </div>
                <?php endif; ?>
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

<!-- Modal: Add/Edit Wallet (V2 Bottom Sheet Style) -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content glass-card bg-dark" style="border:none; border-top: 1px solid rgba(255,255,255,0.2); border-radius: 20px 20px 0 0;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-black text-white italic tracking-widest" id="walletModalTitle"><i class="bi bi-plus-circle text-warning me-2"></i>ADD CHANNEL</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-4">
                <form method="POST">
                    <input type="hidden" name="action" id="walletAction" value="add">
                    <input type="hidden" name="id" id="walletId" value="">
                    
                    <div class="form-floating mb-3">
                        <input type="text" name="provider" id="walletProvider" class="form-control bg-black text-white border-secondary rounded-4" list="bankOptions" placeholder="Type or select..." required>
                        <label class="text-muted"><i class="bi bi-bank me-1"></i> Provider Name</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" name="acc_name" id="walletName" class="form-control bg-black text-white border-secondary rounded-4" placeholder="e.g. U Ba" required>
                        <label class="text-muted"><i class="bi bi-person me-1"></i> Account Name</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="text" name="acc_num" id="walletNum" class="form-control bg-black text-white border-secondary rounded-4 font-mono fw-bold" placeholder="09..." required>
                        <label class="text-muted"><i class="bi bi-hash me-1"></i> Account Number</label>
                    </div>

                    <div class="bg-black bg-opacity-40 border border-white border-opacity-10 p-3 rounded-4 mb-4" id="autoCreateDiv">
                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                            <input class="form-check-input mt-0" type="checkbox" name="auto_create_withdraw" value="1" id="autoCheck" checked style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label text-white small fw-bold tracking-wide" for="autoCheck">
                                Enable for Player Withdrawals
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn w-100 py-3 rounded-pill fw-black shadow-lg" style="background: linear-gradient(135deg, #FFD700, #FDB931); color: #000; letter-spacing: 1px;">
                        SAVE CONFIGURATION
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Bank Option -->
<div class="modal fade" id="addBankModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card bg-dark border-info">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-info tracking-widest"><i class="bi bi-globe me-2"></i>GLOBAL OPTION</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_bank">
                    <div class="mb-4 mt-2">
                        <input type="text" name="bank_name" class="form-control bg-black text-white border-secondary rounded-3 py-3 text-center fw-bold" placeholder="e.g. PayPal" required>
                    </div>
                    <button type="submit" class="btn btn-info w-100 rounded-pill fw-black text-dark py-2">ENABLE</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openEditModal(wallet) {
    document.getElementById('walletModalTitle').innerHTML = '<i class="bi bi-pencil-square text-info me-2"></i>EDIT CHANNEL';
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
        document.getElementById('walletModalTitle').innerHTML = '<i class="bi bi-plus-circle text-warning me-2"></i>ADD CHANNEL';
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