<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

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

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Your Profile</h1>
    <p class="page-subtitle">Account overview and statistics</p>
</div>

<!-- Profile Card -->
<div class="profile-card" id="profileCard">
    <div class="profile-avatar-lg">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
    </div>

    <div class="profile-info">
        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
        <p><?php echo htmlspecialchars($user['email']); ?></p>
        <p class="text-muted" style="font-size:13px;">Member since <?php echo $member_since; ?></p>
    </div>

    <div class="profile-stats">
        <div class="profile-stat">
            <div class="profile-stat-value rupee text-blue">₹<?php echo number_format($user['virtual_balance'], 0); ?></div>
            <div class="profile-stat-label">Cash Balance</div>
        </div>
        <div class="profile-stat">
            <div class="profile-stat-value rupee text-green">₹<?php echo number_format($net_worth, 0); ?></div>
            <div class="profile-stat-label">Net Worth</div>
        </div>
        <div class="profile-stat">
            <div class="profile-stat-value text-red">₹<?php echo number_format($expense_stats['total'], 0); ?></div>
            <div class="profile-stat-label">Total Spent</div>
        </div>
    </div>

    <div class="profile-stats" style="border-top: 1px solid var(--border); padding-top: 24px; margin-top: 24px;">
        <div class="profile-stat">
            <div class="profile-stat-value"><?php echo $expense_stats['count']; ?></div>
            <div class="profile-stat-label">Expenses</div>
        </div>
        <div class="profile-stat">
            <div class="profile-stat-value"><?php echo $trade_stats['total']; ?></div>
            <div class="profile-stat-label">Total Trades</div>
        </div>
        <div class="profile-stat">
            <div class="profile-stat-value text-green"><?php echo $trade_stats['open_count']; ?></div>
            <div class="profile-stat-label">Open Positions</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="max-width: 600px; margin: 32px auto 0; display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
    <a href="expenses.php" class="btn btn-primary" id="quickExpense">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Add Expense
    </a>
    <a href="portfolio.php" class="btn btn-success" id="quickTrade">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
        New Trade
    </a>
    <a href="dashboard.php" class="btn btn-outline" id="quickDashboard">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
        Dashboard
    </a>
</div>

<?php include 'includes/footer.php'; ?>
