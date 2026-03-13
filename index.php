<?php
// index.php
require_once 'includes/db.php';
require_once 'includes/header.php'; // Also handles auth check

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Dashboard Stats logic
$stats = [
    'monthly_revenue' => 0,
    'monthly_expenses' => 0,
    'net_profit' => 0,
    'services_rendered' => 0
];

// If Admin/Manager, show company wide stats. If Staff, show only their performance
if ($role === 'admin' || $role === 'manager') {
    $where_clause = "1=1"; 
} else {
    $where_clause = "user_id = $user_id";
}

// Monthly Revenue
$revenue_query = "SELECT SUM(amount) as total FROM transactions WHERE type='income' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND $where_clause";
$res = $conn->query($revenue_query);
if ($res && $row = $res->fetch_assoc()) {
    $stats['monthly_revenue'] = $row['total'] ?? 0;
}

// Monthly Expenses (Staff usually don't record expenses, but just in case)
$expense_query = "SELECT SUM(amount) as total FROM transactions WHERE type='expense' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND $where_clause";
$res = $conn->query($expense_query);
if ($res && $row = $res->fetch_assoc()) {
    $stats['monthly_expenses'] = $row['total'] ?? 0;
}

$stats['net_profit'] = $stats['monthly_revenue'] - $stats['monthly_expenses'];

// Total services this month
$services_query = "SELECT COUNT(id) as total FROM transactions WHERE type='income' AND service_id IS NOT NULL AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND $where_clause";
$res = $conn->query($services_query);
if ($res && $row = $res->fetch_assoc()) {
    $stats['services_rendered'] = $row['total'] ?? 0;
}

// Recent transactions
$recent_tx_query = "
    SELECT t.*, s.name as service_name, u.username as staff_name
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE $where_clause
    ORDER BY t.created_at DESC LIMIT 5
";
$recent_tx = $conn->query($recent_tx_query);
?>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    .text-success { color: var(--accent-teal); }
    .text-danger { color: var(--accent-rose); }
</style>

<div class="dashboard-grid">
    <div class="glass-panel stat-card">
        <div class="stat-card-header">
            <span>Monthly Revenue</span>
            <i class="ph ph-trend-up text-success" style="font-size: 1.5rem;"></i>
        </div>
        <div class="stat-value text-success">$<?php echo number_format($stats['monthly_revenue'], 2); ?></div>
    </div>
    
    <div class="glass-panel stat-card">
        <div class="stat-card-header">
            <span>Monthly Expenses</span>
            <i class="ph ph-trend-down text-danger" style="font-size: 1.5rem;"></i>
        </div>
        <div class="stat-value text-danger">$<?php echo number_format($stats['monthly_expenses'], 2); ?></div>
    </div>
    
    <div class="glass-panel stat-card">
        <div class="stat-card-header">
            <span>Net Profit</span>
            <i class="ph ph-wallet text-<?php echo $stats['net_profit'] >= 0 ? 'success' : 'danger'; ?>" style="font-size: 1.5rem;"></i>
        </div>
        <div class="stat-value">$<?php echo number_format($stats['net_profit'], 2); ?></div>
    </div>

    <div class="glass-panel stat-card">
        <div class="stat-card-header">
            <span>Services Rendered</span>
            <i class="ph ph-scissors" style="font-size: 1.5rem; color: var(--accent-blue)"></i>
        </div>
        <div class="stat-value"><?php echo $stats['services_rendered']; ?></div>
    </div>
</div>

<h3 style="margin-bottom: 1rem;">Recent Transactions</h3>
<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description / Service</th>
                <th>Amount</th>
                <?php if($role !== 'staff') echo "<th>Staff</th>"; ?>
            </tr>
        </thead>
        <tbody>
            <?php if($recent_tx && $recent_tx->num_rows > 0): ?>
                <?php while($txn = $recent_tx->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('M j, g:i a', strtotime($txn['created_at'])); ?></td>
                        <td>
                            <?php if($txn['type'] == 'income'): ?>
                                <span class="badge badge-income">Income</span>
                            <?php else: ?>
                                <span class="badge badge-expense">Expense</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                                echo htmlspecialchars($txn['service_name'] ?? $txn['description'] ?? 'Walk-in Sale');
                            ?>
                        </td>
                        <td class="<?php echo $txn['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($txn['amount'], 2); ?>
                        </td>
                        <?php if($role !== 'staff'): ?>
                            <td><?php echo htmlspecialchars($txn['staff_name']); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No recent transactions found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
