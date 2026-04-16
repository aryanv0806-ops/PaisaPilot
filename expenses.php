<?php
require_once 'auth_check.php';
require_once 'db.php';

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

include 'header.php';
?>

<h1>Expense Tracker</h1>
<p>Log and manage your daily spending — Current Balance: <strong>₹<?php echo number_format($current_balance, 2); ?></strong></p>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="form-card">
    <h2>Add Expense</h2>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add_expense">

        <div class="form-group">
            <label for="amount">Amount (₹)</label>
            <input type="number" id="amount" name="amount" placeholder="0.00" step="0.01" min="0.01" required>
        </div>

        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category" required>
                <option value="">Select category...</option>
                <option value="Food">Food & Dining</option>
                <option value="Transport">Transport</option>
                <option value="Entertainment">Entertainment</option>
                <option value="Bills">Bills & Utilities</option>
                <option value="Shopping">Shopping</option>
                <option value="Health">Health</option>
                <option value="Other">Other</option>
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

        <button type="submit" class="btn">Add Expense</button>
    </form>
</div>

<div class="table-card">
    <h3>Expense History (<?php echo $expenses->num_rows; ?> entries)</h3>
    <table class="data-table">
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
                    <td><?php echo date('d M Y', strtotime($exp['date'])); ?></td>
                    <td><?php echo htmlspecialchars($exp['category']); ?></td>
                    <td><?php echo htmlspecialchars($exp['description'] ?: '—'); ?></td>
                    <td>-₹<?php echo number_format($exp['amount'], 2); ?></td>
                    <td>
                        <form method="POST" action="" onsubmit="return confirm('Delete this expense?')">
                            <input type="hidden" name="action" value="delete_expense">
                            <input type="hidden" name="expense_id" value="<?php echo $exp['id']; ?>">
                            <button type="submit" class="btn">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No expenses recorded yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
