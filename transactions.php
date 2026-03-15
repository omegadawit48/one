<?php
// transactions.php
require_once 'includes/db.php';
require_once 'includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Staff (Barbers) cannot access this page
if ($role === 'staff') {
    header('Location: profile.php');
    exit;
}

// Cashier: can only see past 3 days
if ($role === 'cashier') {
    $where_clause = "DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
} else {
    // Admin/Manager: filter by month
    $filter_date = isset($_GET['date']) ? sanitize($conn, $_GET['date']) : date('Y-m');
    $where_clause = "DATE_FORMAT(t.created_at, '%Y-%m') = '$filter_date'";
}

// Fetch Transactions
$tx_query = "
    SELECT t.*, s.name as service_name, u.username as staff_name, b.username as barber_name
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN users b ON t.barber_id = b.id
    WHERE $where_clause
    ORDER BY t.created_at DESC
";
$transactions = $conn->query($tx_query);

// Handle Delete (Admin Only)
$message = '';
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $del_id = intval($_GET['delete']);
    $del_stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $del_stmt->bind_param("i", $del_id);
    if ($del_stmt->execute()) {
        $message = "<div class='badge badge-income' style='margin-bottom: 1rem;'>Transaction deleted successfully.</div>";
        $redirect_url = $role === 'cashier' ? 'transactions.php' : "transactions.php?date=$filter_date";
        header("Refresh:1; url=$redirect_url");
    } else {
        $message = "<div class='badge badge-expense' style='margin-bottom: 1rem;'>Error deleting transaction.</div>";
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <h2><i class="ph ph-receipt" style="color: var(--accent-teal);"></i> 
        <?php echo $role === 'cashier' ? 'Sales - Past 3 Days' : 'Ledger & Transactions'; ?>
    </h2>
    
    <div style="display: flex; gap: 1rem; align-items: center;">
        <?php if ($role !== 'cashier'): ?>
        <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
            <label for="date" style="color: var(--text-secondary); font-size: 0.9rem;">Month:</label>
            <input type="month" id="date" name="date" class="form-control" value="<?php echo $filter_date; ?>" style="padding: 0.5rem; max-width: 150px;" onchange="this.form.submit()">
        </form>
        <?php endif; ?>
        
        <a href="new_transaction.php" class="btn btn-primary" style="text-decoration: none;">
            <i class="ph ph-plus"></i> Record Sale/Expense
        </a>
    </div>
</div>

<?php echo $message; ?>

<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Type</th>
                <th>Category/Item</th>
                <th>Amount</th>
                <th>Barber</th>
                <?php if($role === 'admin' || $role === 'manager'): ?>
                    <th>Recorded By</th>
                <?php endif; ?>
                <th>Description</th>
                <?php if($role === 'admin'): ?>
                    <th style="text-align: right;">Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_income = 0;
            $total_expense = 0;
            while($t = $transactions->fetch_assoc()): 
                if($t['type'] === 'income') $total_income += $t['amount'];
                if($t['type'] === 'expense') $total_expense += $t['amount'];
            ?>
                <tr>
                    <td style="color: var(--text-secondary);">
                        <?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?>
                    </td>
                    <td>
                        <?php if($t['type'] == 'income'): ?>
                            <span class="badge badge-income" style="display: flex; align-items: center; gap: 4px; width: max-content;"><i class="ph ph-arrow-up-right"></i> Income</span>
                        <?php else: ?>
                            <span class="badge badge-expense" style="display: flex; align-items: center; gap: 4px; width: max-content;"><i class="ph ph-arrow-down-right"></i> Expense</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight: 500;">
                        <?php echo htmlspecialchars($t['service_name'] ?? 'Other/Custom'); ?>
                    </td>
                    <td class="<?php echo $t['type'] == 'income' ? 'text-success' : 'text-danger'; ?>" style="font-weight: 600;">
                        $<?php echo number_format($t['amount'], 2); ?>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <?php if ($t['barber_name']): ?>
                                <i class="ph ph-user-circle" style="color: var(--accent-blue);"></i>
                                <?php echo htmlspecialchars($t['barber_name']); ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php if($role === 'admin' || $role === 'manager'): ?>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="ph ph-user" style="color: var(--text-secondary);"></i>
                                <?php echo htmlspecialchars($t['staff_name']); ?>
                            </div>
                        </td>
                    <?php endif; ?>
                    <td style="color: var(--text-secondary); font-size: 0.9rem; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($t['description']); ?>">
                        <?php echo htmlspecialchars($t['description']) ?: '-'; ?>
                    </td>
                    <?php if($role === 'admin'): ?>
                        <td style="text-align: right;">
                            <a href="transactions.php?delete=<?php echo $t['id']; ?><?php echo $role !== 'cashier' ? '&date=' . $filter_date : ''; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; color: var(--accent-rose); border-color: rgba(244,63,94,0.3);" onclick="return confirm('Delete transaction completely?');">
                                <i class="ph ph-trash"></i>
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
            
            <?php if($transactions->num_rows === 0): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                        <i class="ph ph-receipt" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
                        No transactions found for this period.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if($transactions->num_rows > 0): ?>
    <div style="padding: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 2rem; background: rgba(0,0,0,0.2);">
        <div><span style="color: var(--text-secondary);">Total Income:</span> <span class="text-success" style="font-weight:bold;font-size:1.1rem;margin-left:0.5rem;">$<?php echo number_format($total_income, 2); ?></span></div>
        <?php if ($role !== 'cashier'): ?>
        <div><span style="color: var(--text-secondary);">Total Expense:</span> <span class="text-danger" style="font-weight:bold;font-size:1.1rem;margin-left:0.5rem;">$<?php echo number_format($total_expense, 2); ?></span></div>
        <div><span style="color: var(--text-secondary);">Net:</span> <span style="font-weight:bold;font-size:1.1rem;margin-left:0.5rem;color: <?php echo ($total_income - $total_expense) >= 0 ? 'var(--accent-teal)' : 'var(--accent-rose)'; ?>">$<?php echo number_format($total_income - $total_expense, 2); ?></span></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
