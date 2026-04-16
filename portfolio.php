<?php
require_once 'auth_check.php';
require_once 'db.php';

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

$prefill_symbol = $_GET['symbol'] ?? '';
$prefill_price = $_GET['price'] ?? '';

include 'header.php';
?>

<h1>Paper Trading Portfolio</h1>
<p>Simulate stock trading with virtual currency — Available: <strong>₹<?php echo number_format($current_balance, 2); ?></strong></p>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="widget-grid">
    <div class="widget">
        <h3>Open Positions</h3>
        <p><?php echo $portfolio_stats['count']; ?></p>
    </div>
    <div class="widget">
        <h3>Total Invested</h3>
        <p>₹<?php echo number_format($portfolio_stats['total_invested'], 2); ?></p>
    </div>
    <div class="widget">
        <h3>Cash Available</h3>
        <p>₹<?php echo number_format($current_balance, 2); ?></p>
    </div>
</div>

<div class="form-card">
    <h2>Market Watch — NSE Stocks <span id="dataSource" style="font-size: 13px;"></span></h2>
    <div>
        <label>Search: <input type="text" id="stockSearch" placeholder="Symbol..." oninput="filterStocks()"></label>
    </div>
    <ul id="marketGrid">
        <li>Loading market data...</li>
    </ul>
</div>

<div class="form-card" id="buyFormCard">
    <h2>Execute Buy Order <span id="selectedStockLabel" style="font-size: 13px;"></span></h2>
    <form method="POST" action="">
        <input type="hidden" name="action" value="buy_trade">
        <div class="form-group">
            <label for="stock_symbol">Stock Symbol</label>
            <input type="text" id="stock_symbol" name="stock_symbol" required value="<?php echo htmlspecialchars($prefill_symbol); ?>">
        </div>
        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="1" value="1" required>
        </div>
        <div class="form-group">
            <label for="entry_price">Entry Price (₹)</label>
            <input type="number" id="entry_price" name="entry_price" step="0.01" min="0.01" required value="<?php echo htmlspecialchars($prefill_price); ?>">
        </div>
        <div class="form-group">
            <label for="trade_date">Trade Date</label>
            <input type="date" id="trade_date" name="trade_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        
        <p>Estimated Total: <strong id="tradeTotal">₹0.00</strong></p>
        <button type="submit" class="btn">Buy Shares</button>
    </form>
</div>

<div class="table-card">
    <h3>📈 Your Open Positions (<?php echo $open_positions->num_rows; ?> active)</h3>
    <table class="data-table">
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
                    <td><?php echo htmlspecialchars($pos['stock_symbol']); ?></td>
                    <td><?php echo $pos['quantity']; ?></td>
                    <td>₹<?php echo number_format($pos['entry_price'], 2); ?></td>
                    <td>₹<?php echo number_format($pos['quantity'] * $pos['entry_price'], 2); ?></td>
                    <td><?php echo date('d M Y', strtotime($pos['trade_date'])); ?></td>
                    <td>
                        <form method="POST" action="" onsubmit="return confirm('Close this position?')">
                            <input type="hidden" name="action" value="close_trade">
                            <input type="hidden" name="trade_id" value="<?php echo $pos['id']; ?>">
                            <button type="submit" class="btn">Sell</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No open positions. Pick a stock from Market Watch above!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($closed_positions->num_rows > 0): ?>
<div class="table-card">
    <h3>📉 Closed Positions</h3>
    <table class="data-table">
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
                <td><?php echo htmlspecialchars($pos['stock_symbol']); ?></td>
                <td><?php echo $pos['quantity']; ?></td>
                <td>₹<?php echo number_format($pos['entry_price'], 2); ?></td>
                <td>₹<?php echo number_format($pos['quantity'] * $pos['entry_price'], 2); ?></td>
                <td><?php echo date('d M Y', strtotime($pos['trade_date'])); ?></td>
                <td>Closed</td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
let allStocks = [];

async function loadMarketData() {
    try {
        const res = await fetch('stocks.php?action=quotes');
        const json = await res.json();
        if (!json.success) return;

        allStocks = json.data;
        document.getElementById('dataSource').textContent = json.source === 'live' ? '(Live)' : '(Cached)';
        renderStockCards();
    } catch(e) {
        document.getElementById('dataSource').textContent = '(Offline)';
    }
}

function renderStockCards() {
    const grid = document.getElementById('marketGrid');
    const search = document.getElementById('stockSearch').value.toLowerCase();
    
    let filtered = allStocks;
    if (search) {
        filtered = filtered.filter(s => 
            s.symbol.toLowerCase().includes(search) || 
            s.name.toLowerCase().includes(search)
        );
    }

    if (filtered.length === 0) {
        grid.innerHTML = '<li>No stocks match your filter</li>';
        return;
    }

    grid.innerHTML = filtered.map(s => {
        return `<li><a href="#" onclick="selectStock('${s.symbol}', ${s.price}, '${s.name}'); return false;">${s.symbol} - ₹${s.price.toLocaleString('en-IN', {minimumFractionDigits:2})} (${s.change >= 0 ? '+' : ''}${s.changePercent}%)</a></li>`;
    }).join('');
}

function selectStock(symbol, price, name) {
    document.getElementById('stock_symbol').value = symbol;
    document.getElementById('entry_price').value = price.toFixed(2);
    document.getElementById('selectedStockLabel').textContent = `- Selected: ${name} (${symbol})`;
    updateTotal();
}

function filterStocks() {
    renderStockCards();
}

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

loadMarketData();
updateTotal();
setInterval(loadMarketData, 60000);
</script>

<?php include 'footer.php'; ?>
