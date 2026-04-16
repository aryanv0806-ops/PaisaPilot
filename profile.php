<?php
require_once 'auth_check.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$page_title = 'Profile';

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Total expenses
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count FROM expenses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$expense_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Total trades
$stmt = $conn->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) AS open_count, COALESCE(SUM(CASE WHEN status='open' THEN quantity * entry_price ELSE 0 END), 0) AS invested FROM trades WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trade_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

$member_since = date('d M Y', strtotime($user['created_at']));
$net_worth = $user['virtual_balance'] + $trade_stats['invested'];

include 'header.php';
?>

<h1>Your Profile</h1>
<p>Account overview and statistics</p>

<div class="profile-card">
    <h2><?php echo htmlspecialchars($user['username']); ?></h2>
    <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
    <p>Member since: <?php echo $member_since; ?></p>
    
    <hr>

    <p><strong>Cash Balance:</strong> ₹<?php echo number_format($user['virtual_balance'], 0); ?></p>
    <p><strong>Net Worth:</strong> ₹<?php echo number_format($net_worth, 0); ?></p>
    <p><strong>Total Spent:</strong> ₹<?php echo number_format($expense_stats['total'], 0); ?></p>
    <p><strong>Expenses:</strong> <?php echo $expense_stats['count']; ?></p>
    <p><strong>Total Trades:</strong> <?php echo $trade_stats['total']; ?></p>
    <p><strong>Open Positions:</strong> <?php echo $trade_stats['open_count']; ?></p>
</div>

<div class="content-split">
    <a href="expenses.php" class="btn">Add Expense</a>
    <a href="portfolio.php" class="btn">New Trade</a>
    <a href="dashboard.php" class="btn">Dashboard</a>
</div>

<?php include 'footer.php'; ?>
