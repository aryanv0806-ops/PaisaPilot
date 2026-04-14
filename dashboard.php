<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

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

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Welcome back, <?php echo htmlspecialchars($user['username'] ?? 'User'); ?> 👋</h1>
    <p class="page-subtitle">Here's your financial overview at a glance</p>
</div>

<!-- Summary Widgets -->
<div class="widget-grid" id="summaryWidgets">
    <!-- Virtual Balance -->
    <div class="widget widget-blue">
        <div class="widget-header">
            <span class="widget-label">Cash Balance</span>
            <div class="widget-icon blue">
                <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
            </div>
        </div>
        <div class="widget-value rupee">₹<?php echo number_format($user['virtual_balance'] ?? 0, 2); ?></div>
        <div class="widget-change positive">Starting: ₹1,00,000</div>
    </div>

    <!-- Total Expenses -->
    <div class="widget widget-red">
        <div class="widget-header">
            <span class="widget-label">Total Expenses</span>
            <div class="widget-icon red">
                <svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
            </div>
        </div>
        <div class="widget-value rupee">₹<?php echo number_format($total_expenses, 2); ?></div>
        <div class="widget-change <?php echo ($expense_trend <= 0) ? 'positive' : 'negative'; ?>">
            <?php if ($expense_trend != 0): ?>
                <?php echo ($expense_trend > 0 ? '↑' : '↓') . ' ' . abs($expense_trend) . '% vs last month'; ?>
            <?php else: ?>
                <?php echo $expense_count; ?> transaction<?php echo ($expense_count != 1) ? 's' : ''; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Portfolio Invested -->
    <div class="widget widget-green">
        <div class="widget-header">
            <span class="widget-label">Invested Value</span>
            <div class="widget-icon green">
                <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
            </div>
        </div>
        <div class="widget-value rupee">₹<?php echo number_format($invested_value, 2); ?></div>
        <div class="widget-change positive"><?php echo $open_trades; ?> open position<?php echo ($open_trades !== 1) ? 's' : ''; ?></div>
    </div>

    <!-- Net Worth -->
    <div class="widget widget-purple">
        <div class="widget-header">
            <span class="widget-label">Net Worth</span>
            <div class="widget-icon orange">
                <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
            </div>
        </div>
        <div class="widget-value rupee">₹<?php echo number_format($user['virtual_balance'] + $invested_value, 2); ?></div>
        <div class="widget-change positive">Balance + Portfolio</div>
    </div>
</div>

<!-- Charts Row 1: Spending Trend + Category Breakdown -->
<div class="charts-grid" id="chartsSection">
    <!-- Daily Expense Trend (Area Chart) -->
    <div class="chart-card">
        <div class="chart-card-title">
            <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
            Spending Trend (Last 30 Days)
            <?php if (!$has_daily_data): ?><span class="text-muted" style="font-size:12px; font-weight:400; margin-left:auto;">Add expenses to see your data</span><?php endif; ?>
        </div>
        <div class="chart-wrapper">
            <canvas id="dailyTrendChart"></canvas>
        </div>
    </div>

    <!-- Expense Category Breakdown (Doughnut) -->
    <div class="chart-card">
        <div class="chart-card-title">
            <svg viewBox="0 0 24 24"><path d="M11 2v20c-5.07-.5-9-4.79-9-10s3.93-9.5 9-10zm2.03 0v8.99H22c-.47-4.74-4.24-8.52-8.97-8.99zm0 11.01V22c4.74-.47 8.5-4.25 8.97-8.99h-8.97z"/></svg>
            Expense Breakdown by Category
            <?php if (!$has_expense_data): ?><span class="text-muted" style="font-size:12px; font-weight:400; margin-left:auto;">Add expenses to see your data</span><?php endif; ?>
        </div>
        <div class="chart-wrapper">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Charts Row 2: Monthly Bar Chart + Portfolio Allocation -->
<div class="charts-grid">
    <!-- Monthly Expense Comparison (Bar Chart) -->
    <div class="chart-card">
        <div class="chart-card-title">
            <svg viewBox="0 0 24 24"><path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/></svg>
            Monthly Comparison
            <?php if (!$has_monthly_data): ?><span class="text-muted" style="font-size:12px; font-weight:400; margin-left:auto;">Add expenses to see your data</span><?php endif; ?>
        </div>
        <div class="chart-wrapper">
            <canvas id="monthlyBarChart"></canvas>
        </div>
    </div>

    <!-- Portfolio Allocation (Pie) -->
    <div class="chart-card">
        <div class="chart-card-title">
            <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
            Portfolio Allocation
            <?php if (!$has_portfolio_data): ?><span class="text-muted" style="font-size:12px; font-weight:400; margin-left:auto;">Buy stocks to see allocation</span><?php endif; ?>
        </div>
        <div class="chart-wrapper">
            <canvas id="portfolioChart"></canvas>
        </div>
    </div>
</div>

<!-- Live Market Ticker -->
<div class="chart-card" style="margin-bottom: 32px;">
    <div class="chart-card-title">
        <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
        Live Market Overview
        <span id="marketStatus" class="text-muted" style="font-size:12px; font-weight:400; margin-left:auto;">Loading...</span>
    </div>
    <div id="marketTicker" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px,1fr)); gap:12px; padding:4px 0;">
        <!-- Populated by JS -->
        <div class="text-muted" style="grid-column: 1/-1; text-align:center; padding:30px;">Loading market data...</div>
    </div>
</div>

<!-- Recent Activity Tables -->
<div class="charts-grid">
    <!-- Recent Expenses -->
    <div class="table-card">
        <div class="table-card-header">
            <h3 class="table-card-title">Recent Expenses</h3>
            <a href="expenses.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px;">View All</a>
        </div>
        <table class="data-table" id="recentExpensesTable">
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
                        <td><span class="badge badge-<?php echo strtolower($exp['category']); ?>"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                        <td class="rupee" style="font-weight:600; color: var(--accent-red);">-₹<?php echo number_format($exp['amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">
                            <div class="empty-state" style="padding: 30px;">
                                <p>No expenses yet. <a href="expenses.php" style="color: var(--primary); font-weight:600;">Add one</a></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Trades -->
    <div class="table-card">
        <div class="table-card-header">
            <h3 class="table-card-title">Recent Trades</h3>
            <a href="portfolio.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px;">View All</a>
        </div>
        <table class="data-table" id="recentTradesTable">
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
                        <td style="font-weight:700; letter-spacing:0.5px;"><?php echo htmlspecialchars($trade['stock_symbol']); ?></td>
                        <td class="rupee"><?php echo $trade['quantity']; ?> × ₹<?php echo number_format($trade['entry_price'], 2); ?></td>
                        <td><span class="status-<?php echo $trade['status']; ?>"><?php echo ucfirst($trade['status']); ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">
                            <div class="empty-state" style="padding: 30px;">
                                <p>No trades yet. <a href="portfolio.php" style="color: var(--primary); font-weight:600;">Start trading</a></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// ============================================
// CHART CONFIGURATION
// ============================================
const chartColors = {
    primary: '#3a3fd8',
    green: '#00c896',
    red: '#f0423c',
    orange: '#ff9f43',
    blue: '#3b82f6',
    purple: '#8b5cf6',
    pink: '#ec4899',
    teal: '#14b8a6',
    indigo: '#6366f1',
    amber: '#f59e0b'
};

const pieColors = ['#3a3fd8','#00c896','#ff9f43','#f0423c','#8b5cf6','#ec4899','#14b8a6','#3b82f6','#f59e0b','#6366f1'];

Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#6b7280';

const tooltipConfig = {
    backgroundColor: '#1e2235',
    titleColor: '#fff',
    bodyColor: '#c5c7d3',
    borderColor: 'rgba(255,255,255,0.1)',
    borderWidth: 1,
    cornerRadius: 8,
    padding: 12,
    displayColors: false,
};

// ============================================
// CHART 1: Daily Spending Trend (Area)
// ============================================
const dailyCtx = document.getElementById('dailyTrendChart').getContext('2d');
const dailyGradient = dailyCtx.createLinearGradient(0, 0, 0, 300);
dailyGradient.addColorStop(0, 'rgba(58, 63, 216, 0.25)');
dailyGradient.addColorStop(1, 'rgba(58, 63, 216, 0.01)');

<?php if ($has_daily_data): ?>
const dailyLabels = <?php echo json_encode($daily_labels); ?>;
const dailyData = <?php echo json_encode($daily_amounts); ?>;
<?php else: ?>
// Empty state — show flat line at 0
const dailyLabels = ['No data yet'];
const dailyData = [0];
<?php endif; ?>

new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Daily Spending (₹)',
            data: dailyData,
            borderColor: chartColors.primary,
            backgroundColor: dailyGradient,
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointRadius: dailyData.length > 15 ? 0 : 5,
            pointHoverRadius: 7,
            pointBackgroundColor: '#fff',
            pointBorderColor: chartColors.primary,
            pointBorderWidth: 2.5,
            pointHoverBackgroundColor: chartColors.primary,
            pointHoverBorderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { display: false },
            tooltip: {
                ...tooltipConfig,
                callbacks: {
                    label: ctx => '₹' + ctx.parsed.y.toLocaleString('en-IN', {minimumFractionDigits:2})
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                ticks: { callback: val => '₹' + (val >= 1000 ? (val/1000).toFixed(0)+'k' : val) }
            },
            x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
        },
        animation: { duration: 1200, easing: 'easeOutQuart' }
    }
});

// ============================================
// CHART 2: Category Doughnut
// ============================================
<?php if ($has_expense_data): ?>
const catLabels = <?php echo json_encode($categories); ?>;
const catData = <?php echo json_encode($cat_amounts); ?>;
<?php else: ?>
const catLabels = ['No expenses yet'];
const catData = [1];
<?php endif; ?>

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catData,
            backgroundColor: <?php echo $has_expense_data ? 'pieColors.slice(0, catLabels.length)' : '["#e5e7eb"]'; ?>,
            borderWidth: 0,
            hoverOffset: 8,
            borderRadius: 4,
            spacing: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'right',
                labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 12, weight: 500 } }
            },
            tooltip: {
                ...tooltipConfig,
                displayColors: true,
                callbacks: {
                    label: ctx => ctx.label + ': ₹' + ctx.parsed.toLocaleString('en-IN')
                }
            }
        },
        animation: { animateRotate: true, duration: 1000 }
    }
});

// ============================================
// CHART 3: Monthly Bar Chart
// ============================================
<?php if ($has_monthly_data): ?>
const monthLabels = <?php echo json_encode($months); ?>;
const monthData = <?php echo json_encode($monthly_amounts); ?>;
<?php else: ?>
const monthLabels = ['No data'];
const monthData = [0];
<?php endif; ?>

new Chart(document.getElementById('monthlyBarChart'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Monthly Expenses (₹)',
            data: monthData,
            backgroundColor: monthData.map((_, i) => pieColors[i % pieColors.length] + 'cc'),
            borderColor: monthData.map((_, i) => pieColors[i % pieColors.length]),
            borderWidth: 1.5,
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 60
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                ...tooltipConfig,
                callbacks: { label: ctx => '₹' + ctx.parsed.y.toLocaleString('en-IN') }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                ticks: { callback: val => '₹' + (val >= 1000 ? (val/1000).toFixed(0)+'k' : val) }
            },
            x: { grid: { display: false } }
        },
        animation: { duration: 1000, easing: 'easeOutQuart' }
    }
});

// ============================================
// CHART 4: Portfolio Allocation
// ============================================
<?php if ($has_portfolio_data): ?>
const portLabels = <?php echo json_encode($portfolio_symbols); ?>;
const portData = <?php echo json_encode($portfolio_values); ?>;
const portColors = pieColors.slice(0, portLabels.length);
<?php else: ?>
const portLabels = ['No positions'];
const portData = [1];
const portColors = ['#e5e7eb'];
<?php endif; ?>

new Chart(document.getElementById('portfolioChart'), {
    type: 'pie',
    data: {
        labels: portLabels,
        datasets: [{
            data: portData,
            backgroundColor: portColors,
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: { padding: 14, usePointStyle: true, pointStyle: 'circle', font: { size: 12, weight: 600 } }
            },
            tooltip: {
                ...tooltipConfig,
                displayColors: true,
                callbacks: {
                    label: ctx => ctx.label + ': ₹' + ctx.parsed.toLocaleString('en-IN', {minimumFractionDigits:2})
                }
            }
        },
        animation: { animateScale: true, duration: 1000 }
    }
});

// ============================================
// LIVE MARKET TICKER
// ============================================
async function loadMarketTicker() {
    try {
        const res = await fetch('api/stocks.php?action=quotes');
        const json = await res.json();
        if (!json.success) return;

        const container = document.getElementById('marketTicker');
        const status = document.getElementById('marketStatus');
        status.textContent = json.source === 'live' ? '🟢 Live' : '🔵 Market Data';
        
        // Show top 8 stocks
        const stocks = json.data.slice(0, 8);
        container.innerHTML = stocks.map(s => `
            <div style="background:#fafbfc; border:1px solid var(--border-light); border-radius:10px; padding:14px 16px; transition: all 0.2s; cursor:pointer;" 
                 onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" 
                 onmouseout="this.style.transform=''; this.style.boxShadow=''"
                 onclick="window.location.href='portfolio.php?symbol=${s.symbol}&price=${s.price}'">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-weight:700; font-size:14px; color:var(--primary);">${s.symbol}</span>
                    <span style="font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px; 
                        background:${s.change >= 0 ? 'var(--accent-green-light)' : 'var(--accent-red-light)'}; 
                        color:${s.change >= 0 ? '#059669' : 'var(--accent-red)'};">
                        ${s.change >= 0 ? '▲' : '▼'} ${Math.abs(s.changePercent)}%
                    </span>
                </div>
                <div style="font-size:18px; font-weight:700; color:var(--text-primary);">₹${s.price.toLocaleString('en-IN', {minimumFractionDigits:2})}</div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">${s.name}</div>
            </div>
        `).join('');

    } catch(e) {
        console.error('Market data error:', e);
        document.getElementById('marketStatus').textContent = '⚪ Offline';
    }
}

// Load market data on page load
loadMarketTicker();

// Auto-refresh every 60 seconds
setInterval(loadMarketTicker, 60000);
</script>

<?php include 'includes/footer.php'; ?>
