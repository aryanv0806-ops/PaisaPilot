<?php
require_once 'auth_check.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$page_title = 'Wallet';

$error = '';
$success = '';

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

include 'header.php';
?>

<h1>Wallet & Funds</h1>
<p>Add real money to your virtual trading wallet</p>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="widget">
    <h3>Current Balance</h3>
    <p>₹<?php echo number_format($current_balance, 2); ?></p>
</div>

<div class="form-card">
    <h2>Top Up Wallet</h2>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add_money">

        <div class="form-group">
            <label>Select Payment Method</label>
            <label><input type="radio" name="method" value="UPI" checked> UPI</label><br>
            <label><input type="radio" name="method" value="Credit Card"> Credit Card</label><br>
            <label><input type="radio" name="method" value="Net Banking"> Net Banking</label><br>
        </div>

        <div class="form-group">
            <label for="amount">Enter Amount (₹)</label>
            <input type="number" id="amount" name="amount" placeholder="e.g. 5000" step="1" min="10" required>
        </div>

        <button type="submit" class="btn">Proceed to Pay</button>
    </form>
</div>

<div class="table-card">
    <h3>Recent Deposits (<?php echo $deposits->num_rows; ?> entries)</h3>
    <table class="data-table">
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
                    <td><?php echo date('d M Y, h:i A', strtotime($dep['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($dep['method']); ?></td>
                    <td><?php echo htmlspecialchars($dep['reference_id']); ?></td>
                    <td>+₹<?php echo number_format($dep['amount'], 2); ?></td>
                    <td><?php echo ucfirst($dep['status']); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No deposits made yet. Top up your wallet to start trading!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
