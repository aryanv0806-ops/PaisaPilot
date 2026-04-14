<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

$user_id = $_SESSION['user_id'];
$page_title = 'Expenses';

$error = '';
$success = '';

// Handle add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_expense') {
        $amount = floatval($_POST['amount'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? '';

        if ($amount <= 0) {
            $error = 'Please enter a valid amount.';
        } elseif (empty($category)) {
            $error = 'Please select a category.';
        } elseif (empty($date)) {
            $error = 'Please select a date.';
        } else {
            // Deduct from balance
            $stmt = $conn->prepare("SELECT virtual_balance FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $balance = $stmt->get_result()->fetch_assoc()['virtual_balance'];
            $stmt->close();

            if ($amount > $balance) {
                $error = 'Insufficient balance! Your current balance is ₹' . number_format($balance, 2);
            } else {
                // Insert expense
                $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("idsss", $user_id, $amount, $category, $description, $date);

                if ($stmt->execute()) {
                    // Update user balance
                    $new_balance = $balance - $amount;
                    $update = $conn->prepare("UPDATE users SET virtual_balance = ? WHERE id = ?");
                    $update->bind_param("di", $new_balance, $user_id);
                    $update->execute();
                    $update->close();

                    $success = 'Expense of ₹' . number_format($amount, 2) . ' added successfully!';
                } else {
                    $error = 'Failed to add expense. Please try again.';
                }
                $stmt->close();
            }
        }
    }

    // Handle delete expense
    if ($_POST['action'] === 'delete_expense') {
        $expense_id = intval($_POST['expense_id'] ?? 0);

        // Fetch expense to refund
        $stmt = $conn->prepare("SELECT amount FROM expenses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $expense_id, $user_id);
        $stmt->execute();
        $exp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exp) {
            $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?")->bind_param("ii", $expense_id, $user_id);
            $del = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
            $del->bind_param("ii", $expense_id, $user_id);
            $del->execute();
            $del->close();

            // Refund balance
            $refund = $conn->prepare("UPDATE users SET virtual_balance = virtual_balance + ? WHERE id = ?");
            $refund->bind_param("di", $exp['amount'], $user_id);
            $refund->execute();
            $refund->close();

            $success = 'Expense deleted and ₹' . number_format($exp['amount'], 2) . ' refunded.';
        }
    }
}

// Fetch all expenses
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC, id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$expenses = $stmt->get_result();
$stmt->close();

// Fetch current balance
$stmt = $conn->prepare("SELECT virtual_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_balance = $stmt->get_result()->fetch_assoc()['virtual_balance'];
$stmt->close();

$conn->close();

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Expense Tracker</h1>
    <p class="page-subtitle">Log and manage your daily spending — Current Balance: <strong class="text-blue rupee">₹<?php echo number_format($current_balance, 2); ?></strong></p>
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

<div class="content-split">
    <!-- Add Expense Form -->
    <div class="form-card">
        <h2 class="form-card-title">
            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Add Expense
        </h2>
        <form method="POST" action="" id="expenseForm">
            <input type="hidden" name="action" value="add_expense">

            <div class="form-group">
                <label for="amount">Amount (₹)</label>
                <input type="number" id="amount" name="amount" placeholder="0.00" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select category...</option>
                    <option value="Food">🍔 Food & Dining</option>
                    <option value="Transport">🚗 Transport</option>
                    <option value="Entertainment">🎬 Entertainment</option>
                    <option value="Bills">📄 Bills & Utilities</option>
                    <option value="Shopping">🛍️ Shopping</option>
                    <option value="Health">🏥 Health</option>
                    <option value="Other">📦 Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" placeholder="e.g. Lunch at cafe">
            </div>

            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-2" id="addExpenseBtn">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Add Expense
            </button>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="table-card">
        <div class="table-card-header">
            <h3 class="table-card-title">Expense History</h3>
            <span class="text-muted" style="font-size:13px;"><?php echo $expenses->num_rows; ?> entries</span>
        </div>
        <div style="overflow-x: auto;">
            <table class="data-table" id="expensesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($expenses->num_rows > 0): ?>
                        <?php while ($exp = $expenses->fetch_assoc()): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($exp['date'])); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($exp['category']); ?>"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                            <td><?php echo htmlspecialchars($exp['description'] ?: '—'); ?></td>
                            <td class="rupee" style="font-weight:600; color: var(--accent-red);">-₹<?php echo number_format($exp['amount'], 2); ?></td>
                            <td>
                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this expense? The amount will be refunded.')">
                                    <input type="hidden" name="action" value="delete_expense">
                                    <input type="hidden" name="expense_id" value="<?php echo $exp['id']; ?>">
                                    <button type="submit" class="btn-delete-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                                    <p>No expenses recorded yet. Start tracking your spending!</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
