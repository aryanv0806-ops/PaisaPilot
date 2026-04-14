<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

$user_id = $_SESSION['user_id'];
$page_title = 'Wallet';

$error = '';
$success = '';

// Handle add money
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_money') {
        $amount = floatval($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? '');
        $reference_id = 'TXN' . strtoupper(uniqid());

        if ($amount <= 0) {
            $error = 'Please enter a valid amount greater than 0.';
        } elseif (empty($method)) {
            $error = 'Please select a payment method.';
        } else {
            // Insert deposit record
            $status = 'success';
            $stmt = $conn->prepare("INSERT INTO deposits (user_id, amount, method, reference_id, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("idsss", $user_id, $amount, $method, $reference_id, $status);

            if ($stmt->execute()) {
                // Update user balance
                $update = $conn->prepare("UPDATE users SET virtual_balance = virtual_balance + ? WHERE id = ?");
                $update->bind_param("di", $amount, $user_id);
                $update->execute();
                $update->close();

                $success = 'Successfully added ₹' . number_format($amount, 2) . ' to your wallet via ' . htmlspecialchars($method) . '!';
            } else {
                $error = 'Failed to process transaction. Please try again.';
            }
            $stmt->close();
        }
    }
}

// Fetch current balance
$stmt = $conn->prepare("SELECT virtual_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_balance = $stmt->get_result()->fetch_assoc()['virtual_balance'];
$stmt->close();

// Fetch deposit history
$stmt = $conn->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deposits = $stmt->get_result();
$stmt->close();

$conn->close();

include 'includes/header.php';
?>

<style>
/* Payment Method styles */
.payment-methods {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.payment-method {
    flex: 1;
    min-width: 120px;
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 16px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: #fff;
    position: relative;
    overflow: hidden;
}
.payment-method:hover {
    border-color: var(--primary-light);
    background: var(--bg-body);
}
.payment-method.active {
    border-color: var(--primary);
    background: var(--primary-glow);
    color: var(--primary-dark);
}
.payment-method.active::after {
    content: '✓';
    position: absolute;
    top: 6px;
    right: 8px;
    color: var(--primary);
    font-weight: 800;
    font-size: 14px;
}
.payment-method svg {
    width: 28px;
    height: 28px;
    fill: currentColor;
    margin-bottom: 8px;
}
.payment-method-name {
    font-size: 13px;
    font-weight: 600;
}
.preset-amounts {
    display: flex;
    gap: 10px;
    margin-top: 8px;
    margin-bottom: 20px;
}
.preset-amount {
    padding: 6px 12px;
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}
.preset-amount:hover {
    background: var(--border-light);
    border-color: var(--text-muted);
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Wallet & Funds</h1>
    <p class="page-subtitle">Add real money to your virtual trading wallet</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- Top up Widget -->
<div class="widget widget-blue" style="max-width: 400px; margin-bottom: 32px;">
    <div class="widget-header">
        <span class="widget-label">Current Balance</span>
        <div class="widget-icon blue">
            <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
        </div>
    </div>
    <div class="widget-value rupee">₹<?php echo number_format($current_balance, 2); ?></div>
</div>

<div class="content-split">
    <!-- Add Money Form -->
    <div class="form-card">
        <h2 class="form-card-title">
            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Top Up Wallet
        </h2>
        <form method="POST" action="" id="topupForm">
            <input type="hidden" name="action" value="add_money">
            <input type="hidden" name="method" id="selectedMethod" value="UPI">

            <div class="form-group" style="margin-bottom: 24px;">
                <label>Select Payment Method</label>
                <div class="payment-methods" style="margin-top: 8px;">
                    <div class="payment-method active" onclick="selectMethod('UPI', this)">
                        <svg viewBox="0 0 24 24"><path d="M2 5v14h20V5H2zm18 12H4V7h16v10zm-9-2h2v-4h4V9H7v2h4v4z"/></svg>
                        <div class="payment-method-name">UPI</div>
                    </div>
                    <div class="payment-method" onclick="selectMethod('Credit Card', this)">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        <div class="payment-method-name">Card</div>
                    </div>
                    <div class="payment-method" onclick="selectMethod('Net Banking', this)">
                        <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>
                        <div class="payment-method-name">Net Banking</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="amount">Enter Amount (₹)</label>
                <input type="number" id="amount" name="amount" placeholder="e.g. 5000" step="1" min="10" required style="font-size: 20px; font-weight: 600;">
                
                <div class="preset-amounts">
                    <span class="preset-amount" onclick="setAmount(1000)">+ ₹1,000</span>
                    <span class="preset-amount" onclick="setAmount(5000)">+ ₹5,000</span>
                    <span class="preset-amount" onclick="setAmount(10000)">+ ₹10,000</span>
                    <span class="preset-amount" onclick="setAmount(50000)">+ ₹50,000</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-2" id="payBtn">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Proceed to Pay
            </button>
        </form>
    </div>

    <!-- Transaction History -->
    <div class="table-card">
        <div class="table-card-header">
            <h3 class="table-card-title">Recent Deposits</h3>
            <span class="text-muted" style="font-size:13px;"><?php echo $deposits->num_rows; ?> entries</span>
        </div>
        <div style="overflow-x: auto;">
            <table class="data-table" id="depositsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Ref ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($deposits->num_rows > 0): ?>
                        <?php while ($dep = $deposits->fetch_assoc()): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo date('d M Y, h:i A', strtotime($dep['created_at'])); ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($dep['method']); ?></td>
                            <td class="font-mono text-muted" style="font-size: 12px;"><?php echo htmlspecialchars($dep['reference_id']); ?></td>
                            <td class="rupee" style="font-weight:700; color: var(--accent-green);">+₹<?php echo number_format($dep['amount'], 2); ?></td>
                            <td><span class="status-<?php echo ($dep['status'] == 'success') ? 'open' : 'closed'; ?>" style="<?php echo ($dep['status'] == 'success') ? '' : 'background: var(--accent-red-light); color: var(--accent-red);'; ?>"><?php echo ucfirst($dep['status']); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                                    <p>No deposits made yet. Top up your wallet to start trading!</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function selectMethod(method, element) {
    document.getElementById('selectedMethod').value = method;
    
    // Remove active class from all
    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('active');
    });
    
    // Add to clicked
    element.classList.add('active');
}

function setAmount(val) {
    const amountInput = document.getElementById('amount');
    const currentVal = parseFloat(amountInput.value) || 0;
    amountInput.value = currentVal + val;
}

// Add simulated loading delay
document.getElementById('topupForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('payBtn');
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="animation: spin 1s linear infinite;"><path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z"/></svg> Processing Payment...';
    btn.style.opacity = "0.8";
    btn.style.pointerEvents = "none";
});
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<?php include 'includes/footer.php'; ?>
