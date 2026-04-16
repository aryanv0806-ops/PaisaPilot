<?php
require_once 'auth_check.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$page_title = 'Dashboard';

// Fetch user data
$stmt = $conn->prepare("SELECT username, virtual_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Total expenses
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM expenses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$exp_res = $stmt->get_result()->fetch_assoc();
$total_expenses = $exp_res['total_expenses'] ?? 0;
$stmt->close();

// Count expenses
$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM expenses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$count_res = $stmt->get_result()->fetch_assoc();
$expense_count = $count_res['count'] ?? 0;
$stmt->close();

// Open trades count & invested value
$stmt = $conn->prepare("SELECT COUNT(*) AS count, COALESCE(SUM(quantity * entry_price), 0) AS invested FROM trades WHERE user_id = ? AND status = 'open'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trade_data = $stmt->get_result()->fetch_assoc();
$open_trades = $trade_data['count'] ?? 0;
$invested_value = $trade_data['invested'] ?? 0;
$stmt->close();

// ---- REAL DATA: Expense by category (for doughnut chart) ----
$stmt = $conn->prepare("SELECT category, SUM(amount) AS total FROM expenses WHERE user_id = ? GROUP BY category ORDER BY total DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cat_result = $stmt->get_result();
$categories = [];
$cat_amounts = [];
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row['category'];
    $cat_amounts[] = (float)$row['total'];
}
$stmt->close();
$has_expense_data = !empty($categories);

// ---- REAL DATA: Daily expenses for last 30 days (for area chart) ----
$stmt = $conn->prepare("
    SELECT date, SUM(amount) AS total 
    FROM expenses 
    WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY date
    ORDER BY date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$daily_result = $stmt->get_result();
$daily_labels = [];
$daily_amounts = [];
while ($row = $daily_result->fetch_assoc()) {
    $daily_labels[] = date('d M', strtotime($row['date']));
    $daily_amounts[] = (float)$row['total'];
}
$stmt->close();
$has_daily_data = !empty($daily_labels);

// ---- REAL DATA: Monthly expenses for last 6 months (for bar chart) ----
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(date, '%Y-%m') AS month, SUM(amount) AS total 
    FROM expenses 
    WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_result = $stmt->get_result();
$months = [];
$monthly_amounts = [];
while ($row = $monthly_result->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_amounts[] = (float)$row['total'];
}
$stmt->close();
$has_monthly_data = !empty($months);

// ---- REAL DATA: Portfolio allocation by stock (for pie chart) ----
$stmt = $conn->prepare("
    SELECT stock_symbol, SUM(quantity * entry_price) AS total_value, SUM(quantity) AS total_qty
    FROM trades 
    WHERE user_id = ? AND status = 'open'
    GROUP BY stock_symbol
    ORDER BY total_value DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$portfolio_result = $stmt->get_result();
$portfolio_symbols = [];
$portfolio_values = [];
while ($row = $portfolio_result->fetch_assoc()) {
    $portfolio_symbols[] = $row['stock_symbol'];
    $portfolio_values[] = (float)$row['total_value'];
}
$stmt->close();
$has_portfolio_data = !empty($portfolio_symbols);

// Recent expenses (last 5)
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC, id DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_expenses = $stmt->get_result();
$stmt->close();

// Recent trades (last 5)
$stmt = $conn->prepare("SELECT * FROM trades WHERE user_id = ? ORDER BY trade_date DESC, id DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_trades = $stmt->get_result();
$stmt->close();

// ---- REAL DATA: Expense trend (this month vs last month) ----
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE user_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$this_month_res = $stmt->get_result()->fetch_assoc();
$this_month_expense = $this_month_res['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE user_id = ? AND MONTH(date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$last_month_res = $stmt->get_result()->fetch_assoc();
$last_month_expense = $last_month_res['total'] ?? 0;
$stmt->close();

$expense_trend = ($last_month_expense > 0) ? round((($this_month_expense - $last_month_expense) / $last_month_expense) * 100, 1) : 0;

$conn->close();

include 'header.php';
?>

<h1>Welcome back, <?php echo htmlspecialchars($user['username'] ?? 'User'); ?> 👋</h1>
<p>Here's your financial overview at a glance</p>

<div class="widget-grid">
    <div class="widget">
        <h3>Cash Balance</h3>
        <p>₹<?php echo number_format($user['virtual_balance'] ?? 0, 2); ?></p>
        <p><small>Starting: ₹1,00,000</small></p>
    </div>

    <div class="widget">
        <h3>Total Expenses</h3>
        <p>₹<?php echo number_format($total_expenses, 2); ?></p>
        <p><small>
            <?php if ($expense_trend != 0): ?>
                <?php echo ($expense_trend > 0 ? 'Up' : 'Down') . ' ' . abs($expense_trend) . '% vs last month'; ?>
            <?php else: ?>
                <?php echo $expense_count; ?> transaction(s)
            <?php endif; ?>
        </small></p>
    </div>

    <div class="widget">
        <h3>Invested Value</h3>
        <p>₹<?php echo number_format($invested_value, 2); ?></p>
        <p><small><?php echo $open_trades; ?> open position(s)</small></p>
    </div>

    <div class="widget">
        <h3>Net Worth</h3>
        <p>₹<?php echo number_format($user['virtual_balance'] + $invested_value, 2); ?></p>
        <p><small>Balance + Portfolio</small></p>
    </div>
</div>

<div class="charts-grid">
    <div class="chart-card">
        <h3>Expense Breakdown by Category</h3>
        <?php if (!$has_expense_data): ?><p><em>Add expenses to see your data</em></p><?php endif; ?>
        <canvas id="categoryChart"></canvas>
    </div>
    <div class="chart-card">
        <h3>Portfolio Allocation</h3>
        <?php if (!$has_portfolio_data): ?><p><em>Buy stocks to see allocation</em></p><?php endif; ?>
        <canvas id="portfolioChart"></canvas>
    </div>
</div>

<div class="widget">
    <h3>Live Market Overview</h3>
    <p id="marketStatus">Loading...</p>
    <ul id="marketTicker">
        <li>Loading market data...</li>
    </ul>
</div>

<div class="widget-grid">
    <div class="table-card">
        <h3>Recent Expenses <a href="expenses.php" class="btn">View All</a></h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_expenses->num_rows > 0): ?>
                    <?php while ($exp = $recent_expenses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($exp['date'])); ?></td>
                        <td><?php echo htmlspecialchars($exp['category']); ?></td>
                        <td>-₹<?php echo number_format($exp['amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No expenses yet. <a href="expenses.php">Add one</a></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-card">
        <h3>Recent Trades <a href="portfolio.php" class="btn">View All</a></h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Qty × Price</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_trades->num_rows > 0): ?>
                    <?php while ($trade = $recent_trades->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($trade['stock_symbol']); ?></td>
                        <td><?php echo $trade['quantity']; ?> × ₹<?php echo number_format($trade['entry_price'], 2); ?></td>
                        <td><?php echo ucfirst($trade['status']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No trades yet. <a href="portfolio.php">Start trading</a></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// CHART CONFIGURATION
const pieColors = ['#0066cc', '#008800', '#cc0000', '#ff8800', '#6600cc'];

<?php if ($has_expense_data): ?>
new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($categories); ?>,
        datasets: [{
            data: <?php echo json_encode($cat_amounts); ?>,
            backgroundColor: pieColors
        }]
    }
});
<?php endif; ?>

<?php if ($has_portfolio_data): ?>
new Chart(document.getElementById('portfolioChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($portfolio_symbols); ?>,
        datasets: [{
            data: <?php echo json_encode($portfolio_values); ?>,
            backgroundColor: pieColors
        }]
    }
});
<?php endif; ?>

// LIVE MARKET TICKER
async function loadMarketTicker() {
    try {
        const res = await fetch('stocks.php?action=quotes');
        const json = await res.json();
        if (!json.success) return;

        document.getElementById('marketStatus').textContent = json.source === 'live' ? 'Live Data' : 'Cached Data';
        const container = document.getElementById('marketTicker');
        container.innerHTML = json.data.slice(0, 8).map(s => `
            <li>
                <a href="portfolio.php?symbol=${s.symbol}&price=${s.price}">
                    <strong>${s.symbol}</strong> - ₹${s.price} (${s.change >= 0 ? '+' : ''}${s.changePercent}%) [${s.name}]
                </a>
            </li>
        `).join('');
    } catch(e) {
        document.getElementById('marketStatus').textContent = 'Offline';
    }
}
loadMarketTicker();
setInterval(loadMarketTicker, 60000);
</script>

<?php include 'footer.php'; ?>
