<?php
// types.php - Transaction Type Summary
require_once 'includes/db.php';
require_once 'includes/header.php';

$role = $_SESSION['role'];

if ($role === 'staff') {
    header('Location: profile.php');
    exit;
}

// For cashier: past 3 days only; for admin/manager: selected month
if ($role === 'cashier') {
    $where_clause = "DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
    $period_label = "Past 3 Days";
} else {
    $filter_date = isset($_GET['date']) ? sanitize($conn, $_GET['date']) : date('Y-m');
    $where_clause = "DATE_FORMAT(t.created_at, '%Y-%m') = '$filter_date'";
    $period_label = date('F Y', strtotime($filter_date . '-01'));
}

// Summary by service type
$type_query = "
    SELECT 
        s.name as service_name,
        COUNT(*) as total_count,
        SUM(t.amount) as total_amount,
        SUM(t.tip) as total_tips
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    WHERE t.type = 'income' AND $where_clause
    GROUP BY t.service_id, s.name
    ORDER BY total_amount DESC
";
$types = $conn->query($type_query);

// Overall totals
$overall_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(t.amount) as total_amount,
        SUM(t.tip) as total_tips
    FROM transactions t
    WHERE t.type = 'income' AND $where_clause
";
$overall = $conn->query($overall_query)->fetch_assoc();
$grand_total = $overall['total_amount'] ?? 0;
$grand_tips = $overall['total_tips'] ?? 0;
$grand_count = $overall['total_count'] ?? 0;

// Payment method breakdown
$payment_query = "
    SELECT 
        t.payment_method,
        COUNT(*) as count,
        SUM(t.amount) as total
    FROM transactions t
    WHERE t.type = 'income' AND $where_clause
    GROUP BY t.payment_method
";
$payments = $conn->query($payment_query);
?>

<style>
    .type-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .type-card {
        padding: 1.5rem;
    }
    .type-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .type-card-name {
        font-weight: 600;
        font-size: 1.05rem;
    }
    .type-card-count {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }
    .type-bar {
        height: 6px;
        border-radius: 3px;
        background: rgba(255,255,255,0.05);
        margin-bottom: 0.75rem;
        overflow: hidden;
    }
    .type-bar-fill {
        height: 100%;
        border-radius: 3px;
        background: linear-gradient(90deg, var(--accent-teal), var(--accent-blue));
        transition: width 0.5s ease;
    }
    .type-card-stats {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
    }
    .payment-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .pay-summary-card {
        padding: 1.5rem;
        text-align: center;
    }
    .pay-summary-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin-top: 0.5rem;
    }
    .pay-summary-count {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin-top: 0.25rem;
    }
    .text-success { color: var(--accent-teal); }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <h2><i class="ph ph-chart-pie-slice" style="color: var(--accent-teal);"></i> Transaction Types — <?php echo $period_label; ?></h2>
    
    <?php if ($role !== 'cashier'): ?>
    <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
        <label for="date" style="color: var(--text-secondary); font-size: 0.9rem;">Month:</label>
        <input type="month" id="date" name="date" class="form-control" value="<?php echo $filter_date; ?>" style="padding: 0.5rem; max-width: 150px;" onchange="this.form.submit()">
    </form>
    <?php endif; ?>
</div>

<!-- Overall Summary -->
<div class="payment-cards" style="margin-bottom: 2rem;">
    <div class="glass-panel pay-summary-card">
        <div style="color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Revenue</div>
        <div class="pay-summary-value text-success">$<?php echo number_format($grand_total, 2); ?></div>
        <div class="pay-summary-count"><?php echo $grand_count; ?> transaction<?php echo $grand_count != 1 ? 's' : ''; ?></div>
    </div>
    <div class="glass-panel pay-summary-card">
        <div style="color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Tips</div>
        <div class="pay-summary-value" style="color: #fbbf24;">$<?php echo number_format($grand_tips, 2); ?></div>
    </div>
    <?php while($pm = $payments->fetch_assoc()): ?>
    <div class="glass-panel pay-summary-card">
        <div style="color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">
            <?php if ($pm['payment_method'] === 'cash'): ?>
                <i class="ph ph-money" style="color: var(--accent-teal);"></i> Cash
            <?php else: ?>
                <i class="ph ph-bank" style="color: var(--accent-blue);"></i> Bank
            <?php endif; ?>
        </div>
        <div class="pay-summary-value" style="color: <?php echo $pm['payment_method'] === 'cash' ? 'var(--accent-teal)' : 'var(--accent-blue)'; ?>;">$<?php echo number_format($pm['total'], 2); ?></div>
        <div class="pay-summary-count"><?php echo $pm['count']; ?> transaction<?php echo $pm['count'] != 1 ? 's' : ''; ?></div>
    </div>
    <?php endwhile; ?>
</div>

<!-- By Service Type -->
<h3 style="margin-bottom: 1rem;"><i class="ph ph-scissors" style="color: var(--accent-blue);"></i> By Service</h3>
<div class="type-summary-grid">
    <?php if ($types && $types->num_rows > 0): ?>
        <?php while($t = $types->fetch_assoc()): 
            $pct = $grand_total > 0 ? ($t['total_amount'] / $grand_total) * 100 : 0;
        ?>
            <div class="glass-panel type-card">
                <div class="type-card-header">
                    <span class="type-card-name"><i class="ph ph-scissors" style="color: var(--accent-teal); margin-right: 4px;"></i> <?php echo htmlspecialchars($t['service_name'] ?? 'Other / Custom'); ?></span>
                    <span class="type-card-count"><?php echo $t['total_count']; ?> sale<?php echo $t['total_count'] != 1 ? 's' : ''; ?></span>
                </div>
                <div class="type-bar">
                    <div class="type-bar-fill" style="width: <?php echo round($pct); ?>%;"></div>
                </div>
                <div class="type-card-stats">
                    <span class="text-success" style="font-weight: 700;">$<?php echo number_format($t['total_amount'], 2); ?></span>
                    <span style="color: #fbbf24; font-weight: 600;"><i class="ph ph-hand-coins"></i> $<?php echo number_format($t['total_tips'], 2); ?></span>
                    <span style="color: var(--text-secondary);"><?php echo round($pct); ?>%</span>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="glass-panel" style="padding: 3rem; text-align: center; color: var(--text-secondary); grid-column: 1 / -1;">
            <i class="ph ph-chart-pie-slice" style="font-size: 3rem; opacity: 0.5; display: block; margin-bottom: 1rem;"></i>
            No transactions found for this period.
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
