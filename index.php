<?php
// index.php - Dashboard
require_once 'includes/db.php';
require_once 'includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Staff (Barbers) should go to their profile page instead
if ($role === 'staff') {
    header('Location: profile.php');
    exit;
}

// ========================
// CASHIER DASHBOARD
// ========================
if ($role === 'cashier') {
    // Cashier sees past 3 days total income only (no profit, no expenses)
    $days = [];
    for ($i = 0; $i < 3; $i++) {
        $day_date = date('Y-m-d', strtotime("-$i days"));
        $day_label = $i === 0 ? 'Today' : ($i === 1 ? 'Yesterday' : date('l, M j', strtotime("-$i days")));
        
        $day_query = "SELECT SUM(amount) as total, COUNT(*) as count FROM transactions 
            WHERE type='income' AND DATE(created_at) = '$day_date'";
        $day_res = $conn->query($day_query);
        $day = $day_res->fetch_assoc();
        
        $days[] = [
            'label' => $day_label,
            'date' => $day_date,
            'total' => $day['total'] ?? 0,
            'count' => $day['count'] ?? 0
        ];
    }
    
    // Recent transactions (all staff, past 3 days)
    $recent_tx_query = "
        SELECT t.*, s.name as service_name, u.username as barber_name
        FROM transactions t
        LEFT JOIN services s ON t.service_id = s.id
        LEFT JOIN users u ON t.barber_id = u.id
        WHERE t.type = 'income' AND DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
        ORDER BY t.created_at DESC LIMIT 15
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
    .stat-sub {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }
    .text-success { color: var(--accent-teal); }
    .text-danger { color: var(--accent-rose); }
</style>

<h3 style="margin-bottom: 1rem;"><i class="ph ph-calendar-check" style="color: var(--accent-teal);"></i> Sales Overview - Past 3 Days</h3>
<div class="dashboard-grid">
    <?php foreach ($days as $day): ?>
        <div class="glass-panel stat-card">
            <div class="stat-card-header">
                <span><?php echo $day['label']; ?></span>
                <span style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo date('M j', strtotime($day['date'])); ?></span>
            </div>
            <div class="stat-value text-success">$<?php echo number_format($day['total'], 2); ?></div>
            <div class="stat-sub"><?php echo $day['count']; ?> sale<?php echo $day['count'] != 1 ? 's' : ''; ?> recorded</div>
        </div>
    <?php endforeach; ?>
</div>

<h3 style="margin-bottom: 1rem;">Recent Sales</h3>
<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Barber</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if($recent_tx && $recent_tx->num_rows > 0): ?>
                <?php while($txn = $recent_tx->fetch_assoc()): ?>
                    <tr>
                        <td style="color: var(--text-secondary);"><?php echo date('M j, g:i a', strtotime($txn['created_at'])); ?></td>
                        <td style="font-weight: 500;">
                            <?php echo htmlspecialchars($txn['service_name'] ?? $txn['description'] ?? 'Walk-in Sale'); ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="ph ph-user-circle" style="color: var(--accent-blue);"></i>
                                <?php echo htmlspecialchars($txn['barber_name'] ?? 'Unassigned'); ?>
                            </div>
                        </td>
                        <td class="text-success" style="font-weight: 600;">$<?php echo number_format($txn['amount'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No sales in the past 3 days.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
    require_once 'includes/footer.php';
    exit; // End cashier dashboard
}

// ========================
// ADMIN & MANAGER DASHBOARD
// ========================
$stats = [
    'monthly_revenue' => 0,
    'monthly_expenses' => 0,
    'net_profit' => 0,
    'services_rendered' => 0
];

$where_clause = "1=1"; // Admin/Manager see everything

// Monthly Revenue
$revenue_query = "SELECT SUM(amount) as total FROM transactions WHERE type='income' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND $where_clause";
$res = $conn->query($revenue_query);
if ($res && $row = $res->fetch_assoc()) {
    $stats['monthly_revenue'] = $row['total'] ?? 0;
}

// Monthly Expenses
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
    SELECT t.*, s.name as service_name, u.username as staff_name, b.username as barber_name
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN users b ON t.barber_id = b.id
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
                <th>Barber</th>
                <th>Recorded By</th>
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
                            <?php echo htmlspecialchars($txn['service_name'] ?? $txn['description'] ?? 'Walk-in Sale'); ?>
                        </td>
                        <td class="<?php echo $txn['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($txn['amount'], 2); ?>
                        </td>
                        <td><?php echo htmlspecialchars($txn['barber_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($txn['staff_name']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No recent transactions found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
