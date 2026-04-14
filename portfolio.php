<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

$user_id = $_SESSION['user_id'];
$page_title = 'Portfolio';

$error = '';
$success = '';

// Handle buy trade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'buy_trade') {
        $symbol = strtoupper(trim($_POST['stock_symbol'] ?? ''));
        $quantity = intval($_POST['quantity'] ?? 0);
        $entry_price = floatval($_POST['entry_price'] ?? 0);
        $trade_date = $_POST['trade_date'] ?? '';

        $total_cost = $quantity * $entry_price;

        if (empty($symbol)) {
            $error = 'Please enter a stock symbol.';
        } elseif ($quantity <= 0) {
            $error = 'Quantity must be at least 1.';
        } elseif ($entry_price <= 0) {
            $error = 'Please enter a valid entry price.';
        } elseif (empty($trade_date)) {
            $error = 'Please select a trade date.';
        } else {
            $stmt = $conn->prepare("SELECT virtual_balance FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $balance = $stmt->get_result()->fetch_assoc()['virtual_balance'];
            $stmt->close();

            if ($total_cost > $balance) {
                $error = 'Insufficient balance! You need ₹' . number_format($total_cost, 2) . ' but only have ₹' . number_format($balance, 2);
            } else {
                $type = 'buy';
                $status = 'open';
                $stmt = $conn->prepare("INSERT INTO trades (user_id, stock_symbol, type, quantity, entry_price, status, trade_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issidss", $user_id, $symbol, $type, $quantity, $entry_price, $status, $trade_date);

                if ($stmt->execute()) {
                    $new_balance = $balance - $total_cost;
                    $update = $conn->prepare("UPDATE users SET virtual_balance = ? WHERE id = ?");
                    $update->bind_param("di", $new_balance, $user_id);
                    $update->execute();
                    $update->close();
                    $success = 'Bought ' . $quantity . ' shares of ' . $symbol . ' at ₹' . number_format($entry_price, 2) . ' each. Total: ₹' . number_format($total_cost, 2);
                } else {
                    $error = 'Failed to execute trade. Please try again.';
                }
                $stmt->close();
            }
        }
    }

    if ($_POST['action'] === 'close_trade') {
        $trade_id = intval($_POST['trade_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM trades WHERE id = ? AND user_id = ? AND status = 'open'");
        $stmt->bind_param("ii", $trade_id, $user_id);
        $stmt->execute();
        $trade = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($trade) {
            $refund_amount = $trade['quantity'] * $trade['entry_price'];
            $update = $conn->prepare("UPDATE trades SET status = 'closed' WHERE id = ?");
            $update->bind_param("i", $trade_id);
            $update->execute();
            $update->close();
            $refund = $conn->prepare("UPDATE users SET virtual_balance = virtual_balance + ? WHERE id = ?");
            $refund->bind_param("di", $refund_amount, $user_id);
            $refund->execute();
            $refund->close();
            $success = 'Closed position on ' . $trade['stock_symbol'] . '. ₹' . number_format($refund_amount, 2) . ' returned to balance.';
        } else {
            $error = 'Trade not found or already closed.';
        }
    }
}

// Fetch open positions
$stmt = $conn->prepare("SELECT * FROM trades WHERE user_id = ? AND status = 'open' ORDER BY trade_date DESC, id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$open_positions = $stmt->get_result();
$stmt->close();

// Fetch closed positions
$stmt = $conn->prepare("SELECT * FROM trades WHERE user_id = ? AND status = 'closed' ORDER BY trade_date DESC, id DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$closed_positions = $stmt->get_result();
$stmt->close();

// Portfolio stats
$stmt = $conn->prepare("SELECT COUNT(*) AS count, COALESCE(SUM(quantity * entry_price), 0) AS total_invested FROM trades WHERE user_id = ? AND status = 'open'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$portfolio_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch current balance
$stmt = $conn->prepare("SELECT virtual_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_balance = $stmt->get_result()->fetch_assoc()['virtual_balance'];
$stmt->close();

$conn->close();

// Pre-fill from URL params (from dashboard market ticker click)
$prefill_symbol = $_GET['symbol'] ?? '';
$prefill_price = $_GET['price'] ?? '';

include 'includes/header.php';
?>

<style>
/* Market Watch Styles */
.market-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.stock-card {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    padding: 20px;
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.stock-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}
.stock-card::after {
    content: 'Buy →';
    position: absolute;
    top: 12px;
    right: 12px;
    font-size: 11px;
    font-weight: 700;
    color: var(--primary);
    background: var(--primary-glow);
    padding: 3px 10px;
    border-radius: 12px;
    opacity: 0;
    transition: var(--transition);
}
.stock-card:hover::after { opacity: 1; }

.stock-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.stock-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 14px;
    color: #fff;
    flex-shrink: 0;
}
.stock-symbol { font-weight: 700; font-size: 16px; color: var(--text-primary); }
.stock-name { font-size: 12px; color: var(--text-muted); }
.stock-price-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 8px;
}
.stock-price { font-size: 22px; font-weight: 700; color: var(--text-primary); }
.stock-change {
    font-size: 13px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 14px;
}
.stock-change.up { background: var(--accent-green-light); color: #059669; }
.stock-change.down { background: var(--accent-red-light); color: var(--accent-red); }
.stock-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--text-muted);
    border-top: 1px solid var(--border-light);
    padding-top: 10px;
    margin-top: 4px;
}
.stock-sparkline { height: 40px; margin: 4px 0; }

/* Sector filter tabs */
.sector-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.sector-tab {
    padding: 6px 16px;
    border-radius: 20px;
    border: 1.5px solid var(--border);
    background: transparent;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.sector-tab:hover { border-color: var(--primary); color: var(--primary); }
.sector-tab.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}
.stock-search {
    padding: 10px 16px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    width: 260px;
    outline: none;
    transition: var(--transition);
}
.stock-search:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Paper Trading Portfolio</h1>
    <p class="page-subtitle">Simulate stock trading with virtual currency — Available: <strong class="text-blue rupee">₹<?php echo number_format($current_balance, 2); ?></strong></p>
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

<!-- Portfolio Summary Widgets -->
<div class="widget-grid" style="margin-bottom: 28px;">
    <div class="widget widget-green">
        <div class="widget-header">
            <span class="widget-label">Open Positions</span>
            <div class="widget-icon green">
                <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
            </div>
        </div>
        <div class="widget-value"><?php echo $portfolio_stats['count']; ?></div>
    </div>
    <div class="widget widget-blue">
        <div class="widget-header">
            <span class="widget-label">Total Invested</span>
            <div class="widget-icon blue">
                <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
            </div>
        </div>
        <div class="widget-value rupee">₹<?php echo number_format($portfolio_stats['total_invested'], 2); ?></div>
    </div>
    <div class="widget widget-orange">
        <div class="widget-header">
            <span class="widget-label">Cash Available</span>
            <div class="widget-icon orange">
                <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
            </div>
        </div>
        <div class="widget-value rupee">₹<?php echo number_format($current_balance, 2); ?></div>
    </div>
</div>

<!-- ============================================ -->
<!-- MARKET WATCH - Live Stock Data               -->
<!-- ============================================ -->
<div class="form-card" style="padding: 24px 28px;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom: 20px;">
        <h2 class="form-card-title" style="margin-bottom:0;">
            <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
            Market Watch — NSE Stocks
            <span id="dataSource" class="text-muted" style="font-size:12px; font-weight:400; margin-left: 8px;"></span>
        </h2>
        <div style="display:flex; gap:10px; align-items:center;">
            <input type="text" class="stock-search" id="stockSearch" placeholder="🔍 Search stocks..." oninput="filterStocks()">
            <button onclick="refreshMarket()" class="btn btn-outline" style="padding:8px 16px; font-size:13px;" id="refreshBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Sector Filter Tabs -->
    <div class="sector-tabs" id="sectorTabs">
        <button class="sector-tab active" onclick="filterBySector('all', this)">All</button>
        <button class="sector-tab" onclick="filterBySector('Banking', this)">🏦 Banking</button>
        <button class="sector-tab" onclick="filterBySector('IT', this)">💻 IT</button>
        <button class="sector-tab" onclick="filterBySector('Energy', this)">⚡ Energy</button>
        <button class="sector-tab" onclick="filterBySector('Auto', this)">🚗 Auto</button>
        <button class="sector-tab" onclick="filterBySector('FMCG', this)">🛒 FMCG</button>
        <button class="sector-tab" onclick="filterBySector('Pharma', this)">💊 Pharma</button>
        <button class="sector-tab" onclick="filterBySector('Metals', this)">⛏️ Metals</button>
    </div>

    <!-- Stock Cards Grid -->
    <div class="market-grid" id="marketGrid">
        <div class="text-muted" style="grid-column:1/-1; text-align:center; padding:40px;">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" style="width:40px;height:40px;fill:var(--border);margin-bottom:8px;"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
                <p>Loading market data...</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- BUY ORDER FORM (Collapsible)                 -->
<!-- ============================================ -->
<div class="form-card" id="buyFormCard" style="margin-top: 28px;">
    <h2 class="form-card-title">
        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Execute Buy Order
        <span id="selectedStockLabel" style="font-size:13px; font-weight:400; color:var(--text-muted); margin-left:auto;"></span>
    </h2>
    <form method="POST" action="" id="tradeForm">
        <input type="hidden" name="action" value="buy_trade">
        <div class="form-grid">
            <div class="form-group">
                <label for="stock_symbol">Stock Symbol</label>
                <input type="text" id="stock_symbol" name="stock_symbol" placeholder="e.g. RELIANCE" required style="text-transform: uppercase;"
                       value="<?php echo htmlspecialchars($prefill_symbol); ?>">
            </div>
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" placeholder="Shares" min="1" value="1" required>
            </div>
            <div class="form-group">
                <label for="entry_price">Entry Price (₹)</label>
                <input type="number" id="entry_price" name="entry_price" placeholder="Price per share" step="0.01" min="0.01" required
                       value="<?php echo htmlspecialchars($prefill_price); ?>">
            </div>
            <div class="form-group">
                <label for="trade_date">Trade Date</label>
                <input type="date" id="trade_date" name="trade_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        <!-- Live Total Preview -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; background: linear-gradient(135deg, var(--accent-blue-light), #f0f4ff); border-radius: 10px; padding: 16px 20px; margin-top: 20px;">
            <div>
                <span style="font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Estimated Total</span>
                <div id="tradeTotal" class="rupee" style="font-size: 24px; font-weight: 700; color: var(--primary);">₹0.00</div>
            </div>
            <button type="submit" class="btn btn-success" id="buyBtn" style="padding: 14px 32px;">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
                Buy Shares
            </button>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- OPEN POSITIONS TABLE                         -->
<!-- ============================================ -->
<div class="table-card" style="margin-top: 28px;">
    <div class="table-card-header">
        <h3 class="table-card-title">📈 Your Open Positions</h3>
        <span class="text-muted" style="font-size:13px;"><?php echo $open_positions->num_rows; ?> active</span>
    </div>
    <div style="overflow-x: auto;">
        <table class="data-table" id="openPositionsTable">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Qty</th>
                    <th>Entry Price</th>
                    <th>Total Value</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($open_positions->num_rows > 0): ?>
                    <?php while ($pos = $open_positions->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:700; letter-spacing:0.5px; color: var(--primary);"><?php echo htmlspecialchars($pos['stock_symbol']); ?></td>
                        <td><?php echo $pos['quantity']; ?></td>
                        <td class="rupee">₹<?php echo number_format($pos['entry_price'], 2); ?></td>
                        <td class="rupee" style="font-weight:600;">₹<?php echo number_format($pos['quantity'] * $pos['entry_price'], 2); ?></td>
                        <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($pos['trade_date'])); ?></td>
                        <td>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Close this position? The invested amount will return to your balance.')">
                                <input type="hidden" name="action" value="close_trade">
                                <input type="hidden" name="trade_id" value="<?php echo $pos['id']; ?>">
                                <button type="submit" class="btn-delete-sm" style="background: var(--accent-orange-light); color: var(--accent-orange);">Sell</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
                                <p>No open positions. Pick a stock from Market Watch above!</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($closed_positions->num_rows > 0): ?>
<div class="table-card" style="margin-top: 28px;">
    <div class="table-card-header">
        <h3 class="table-card-title">📉 Closed Positions</h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="data-table" id="closedPositionsTable">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Qty</th>
                    <th>Entry Price</th>
                    <th>Total Value</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($pos = $closed_positions->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($pos['stock_symbol']); ?></td>
                    <td><?php echo $pos['quantity']; ?></td>
                    <td class="rupee">₹<?php echo number_format($pos['entry_price'], 2); ?></td>
                    <td class="rupee">₹<?php echo number_format($pos['quantity'] * $pos['entry_price'], 2); ?></td>
                    <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($pos['trade_date'])); ?></td>
                    <td><span class="status-closed">Closed</span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- JAVASCRIPT: Market Data, Filtering, Trading  -->
<!-- ============================================ -->
<script>
let allStocks = [];
let currentSector = 'all';

const avatarColors = ['#3a3fd8','#00c896','#ff9f43','#f0423c','#8b5cf6','#ec4899','#14b8a6','#3b82f6','#f59e0b','#6366f1',
                      '#ef4444','#10b981','#6366f1','#f97316','#06b6d4','#8b5cf6','#84cc16','#e11d48','#0ea5e9','#a855f7'];

// Load market data
async function loadMarketData() {
    try {
        const res = await fetch('api/stocks.php?action=quotes');
        const json = await res.json();
        if (!json.success) return;

        allStocks = json.data;
        document.getElementById('dataSource').textContent = 
            json.source === 'live' ? '🟢 Live prices' : '🔵 Market data';
        
        renderStockCards();
    } catch(e) {
        console.error('Failed to load market data:', e);
        document.getElementById('dataSource').textContent = '⚪ Offline';
    }
}

function renderStockCards() {
    const grid = document.getElementById('marketGrid');
    const search = document.getElementById('stockSearch').value.toLowerCase();
    
    let filtered = allStocks;
    
    // Sector filter
    if (currentSector !== 'all') {
        filtered = filtered.filter(s => s.sector === currentSector);
    }
    
    // Search filter
    if (search) {
        filtered = filtered.filter(s => 
            s.symbol.toLowerCase().includes(search) || 
            s.name.toLowerCase().includes(search) ||
            s.sector.toLowerCase().includes(search)
        );
    }

    if (filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--text-muted);">No stocks match your filter</div>';
        return;
    }

    grid.innerHTML = filtered.map((s, i) => {
        const isUp = s.change >= 0;
        const colorIdx = i % avatarColors.length;
        const initials = s.symbol.substring(0, 2);
        
        return `
        <div class="stock-card" onclick="selectStock('${s.symbol}', ${s.price}, '${s.name}')" data-sector="${s.sector}" data-symbol="${s.symbol}">
            <div class="stock-header">
                <div class="stock-avatar" style="background: ${avatarColors[colorIdx]};">${initials}</div>
                <div>
                    <div class="stock-symbol">${s.symbol}</div>
                    <div class="stock-name">${s.name}</div>
                </div>
            </div>
            <div class="stock-price-row">
                <span class="stock-price rupee">₹${s.price.toLocaleString('en-IN', {minimumFractionDigits:2})}</span>
                <span class="stock-change ${isUp ? 'up' : 'down'}">
                    ${isUp ? '▲' : '▼'} ${Math.abs(s.changePercent).toFixed(2)}%
                </span>
            </div>
            <div class="stock-meta">
                <span>Open: ₹${s.open.toLocaleString('en-IN')}</span>
                <span>High: ₹${s.dayHigh.toLocaleString('en-IN')}</span>
                <span>Low: ₹${s.dayLow.toLocaleString('en-IN')}</span>
            </div>
        </div>`;
    }).join('');
}

// Select a stock to buy
function selectStock(symbol, price, name) {
    document.getElementById('stock_symbol').value = symbol;
    document.getElementById('entry_price').value = price.toFixed(2);
    document.getElementById('selectedStockLabel').textContent = `Selected: ${name} (${symbol})`;
    
    // Scroll to buy form
    document.getElementById('buyFormCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Flash effect
    const card = document.getElementById('buyFormCard');
    card.style.boxShadow = '0 0 0 3px var(--primary), var(--shadow-md)';
    setTimeout(() => { card.style.boxShadow = 'var(--shadow-md)'; }, 1500);
    
    updateTotal();
}

// Sector filter
function filterBySector(sector, btn) {
    currentSector = sector;
    document.querySelectorAll('.sector-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    renderStockCards();
}

// Search filter
function filterStocks() {
    renderStockCards();
}

// Refresh market data
function refreshMarket() {
    const btn = document.getElementById('refreshBtn');
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="animation: spin 0.6s linear infinite;"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg> Loading...';
    loadMarketData().finally(() => {
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg> Refresh';
    });
}

// Live total calculator
const qtyInput = document.getElementById('quantity');
const priceInput = document.getElementById('entry_price');
const totalDisplay = document.getElementById('tradeTotal');

function updateTotal() {
    const qty = parseFloat(qtyInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const total = qty * price;
    totalDisplay.textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

qtyInput.addEventListener('input', updateTotal);
priceInput.addEventListener('input', updateTotal);

// Spin animation for refresh button
const style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);

// Initial load
loadMarketData();
updateTotal();

// Auto-refresh every 60 seconds
setInterval(loadMarketData, 60000);
</script>

<?php include 'includes/footer.php'; ?>
