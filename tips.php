<?php
// tips.php - Tips summary page
require_once 'includes/db.php';
require_once 'includes/header.php';

$role = $_SESSION['role'];

if ($role === 'staff') {
    header('Location: profile.php');
    exit;
}

// For cashier: today only; for admin/manager: selected month
if ($role === 'cashier') {
    $where_clause = "DATE(t.created_at) = CURDATE()";
    $period_label = "Today";
} else {
    $filter_date = isset($_GET['date']) ? sanitize($conn, $_GET['date']) : date('Y-m');
    $where_clause = "DATE_FORMAT(t.created_at, '%Y-%m') = '$filter_date'";
    $period_label = date('F Y', strtotime($filter_date . '-01'));
}

// Total tips
$total_tips_query = "SELECT SUM(tip) as total, COUNT(CASE WHEN tip > 0 THEN 1 END) as tip_count, COUNT(*) as total_tx FROM transactions t WHERE t.type = 'income' AND $where_clause";
$total_tips_res = $conn->query($total_tips_query)->fetch_assoc();
$total_tips = $total_tips_res['total'] ?? 0;
$tip_count = $total_tips_res['tip_count'] ?? 0;
$total_tx = $total_tips_res['total_tx'] ?? 0;
$tip_rate = $total_tx > 0 ? round(($tip_count / $total_tx) * 100) : 0;

// Tips by barber
$barber_tips_query = "
    SELECT 
        u.username as barber_name,
        SUM(t.tip) as total_tips,
        SUM(CASE WHEN t.tip_status = 'unpaid' THEN t.tip ELSE 0 END) as unpaid_tips,
        SUM(CASE WHEN t.tip_status = 'paid' THEN t.tip ELSE 0 END) as paid_tips,
        COUNT(CASE WHEN t.tip > 0 THEN 1 END) as tip_count,
        COUNT(*) as service_count
    FROM transactions t
    LEFT JOIN users u ON t.barber_id = u.id
    WHERE t.type = 'income' AND $where_clause AND t.barber_id IS NOT NULL
    GROUP BY t.barber_id, u.username
    ORDER BY total_tips DESC
";
$barber_tips = $conn->query($barber_tips_query);

// Recent tipped transactions
$recent_tips_query = "
    SELECT t.*, s.name as service_name, u.username as barber_name
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN users u ON t.barber_id = u.id
    WHERE t.type = 'income' AND t.tip > 0 AND $where_clause
    ORDER BY t.created_at DESC LIMIT 15
";
$recent_tips = $conn->query($recent_tips_query);
?>

<style>
    .tips-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .tip-stat-card {
        padding: 1.5rem;
        text-align: center;
    }
    .tip-stat-label {
        color: var(--text-secondary);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }
    .tip-stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #fbbf24;
    }
    .tip-stat-sub {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }
    .barber-tip-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .barber-tip-card {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .barber-tip-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        flex-shrink: 0;
    }
    .barber-tip-info { flex: 1; }
    .barber-tip-name { font-weight: 600; margin-bottom: 0.25rem; }
    .barber-tip-amount { font-size: 1.3rem; font-weight: 700; color: #fbbf24; }
    .barber-tip-sub { color: var(--text-secondary); font-size: 0.8rem; }
    .text-success { color: var(--accent-teal); }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <h2><i class="ph ph-hand-coins" style="color: #fbbf24;"></i> Tips — <?php echo $period_label; ?></h2>
    
    <?php if ($role !== 'cashier'): ?>
    <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
        <label for="date" style="color: var(--text-secondary); font-size: 0.9rem;">Month:</label>
        <input type="month" id="date" name="date" class="form-control" value="<?php echo $filter_date; ?>" style="padding: 0.5rem; max-width: 150px;" onchange="this.form.submit()">
    </form>
    <?php endif; ?>
</div>

<!-- Overview Cards -->
<div class="tips-overview">
    <div class="glass-panel tip-stat-card">
        <div class="tip-stat-label">Total Tips</div>
        <div class="tip-stat-value">$<?php echo number_format($total_tips, 2); ?></div>
    </div>
    <div class="glass-panel tip-stat-card">
        <div class="tip-stat-label">Tipped Sales</div>
        <div class="tip-stat-value" style="color: var(--accent-teal);"><?php echo $tip_count; ?></div>
        <div class="tip-stat-sub">out of <?php echo $total_tx; ?> total</div>
    </div>
    <div class="glass-panel tip-stat-card">
        <div class="tip-stat-label">Tip Rate</div>
        <div class="tip-stat-value" style="color: var(--accent-blue);"><?php echo $tip_rate; ?>%</div>
        <div class="tip-stat-sub">of transactions include tips</div>
    </div>
    <?php if ($tip_count > 0): ?>
    <div class="glass-panel tip-stat-card">
        <div class="tip-stat-label">Avg Tip</div>
        <div class="tip-stat-value">$<?php echo number_format($total_tips / max($tip_count, 1), 2); ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Tips by Barber -->
<h3 style="margin-bottom: 1rem;"><i class="ph ph-users" style="color: #fbbf24;"></i> Tips by Barber</h3>
<div class="barber-tip-grid">
    <?php if ($barber_tips && $barber_tips->num_rows > 0): ?>
        <?php while($bt = $barber_tips->fetch_assoc()): ?>
            <div class="glass-panel barber-tip-card">
                <div class="barber-tip-avatar">
                    <i class="ph ph-user"></i>
                </div>
                <div class="barber-tip-info">
                    <div class="barber-tip-name"><?php echo htmlspecialchars($bt['barber_name']); ?></div>
                    <div class="barber-tip-amount"><i class="ph ph-hand-coins"></i> $<?php echo number_format($bt['total_tips'], 2); ?> Total</div>
                    <div style="font-size: 0.85rem; margin-top: 4px; margin-bottom: 4px; color: var(--text-primary);">
                        <span style="color: var(--accent-rose); margin-right: 8px; font-weight: 600;">Unpaid: $<?php echo number_format($bt['unpaid_tips'], 2); ?></span>
                        <span style="color: var(--accent-teal); font-weight: 600;">Paid: $<?php echo number_format($bt['paid_tips'], 2); ?></span>
                    </div>
                    <div class="barber-tip-sub"><?php echo $bt['tip_count']; ?> tipped of <?php echo $bt['service_count']; ?> services</div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="glass-panel" style="padding: 2rem; text-align: center; color: var(--text-secondary); grid-column: 1 / -1;">
            No tips recorded for this period.
        </div>
    <?php endif; ?>
</div>

<!-- Recent Tipped Transactions -->
<h3 style="margin-bottom: 1rem;"><i class="ph ph-clock-counter-clockwise" style="color: var(--text-secondary);"></i> Recent Tipped Sales</h3>
<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Barber</th>
                <th>Sale Total</th>
                <th>Tip</th>
            </tr>
        </thead>
        <tbody>
            <?php if($recent_tips && $recent_tips->num_rows > 0): ?>
                <?php while($rt = $recent_tips->fetch_assoc()): ?>
                    <tr>
                        <td style="color: var(--text-secondary);"><?php echo date('M j, g:i a', strtotime($rt['created_at'])); ?></td>
                        <td style="font-weight: 500;">
                            <i class="ph ph-scissors" style="color: var(--accent-teal); margin-right: 4px;"></i>
                            <?php echo htmlspecialchars($rt['service_name'] ?? 'Other'); ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="ph ph-user-circle" style="color: var(--accent-blue);"></i>
                                <?php echo htmlspecialchars($rt['barber_name'] ?? 'Unassigned'); ?>
                            </div>
                        </td>
                        <td class="text-success" style="font-weight: 600;">$<?php echo number_format($rt['amount'], 2); ?></td>
                        <td style="color: #fbbf24; font-weight: 700;"><i class="ph ph-hand-coins"></i> $<?php echo number_format($rt['tip'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        <i class="ph ph-hand-coins" style="font-size: 2rem; opacity: 0.5; display: block; margin-bottom: 0.5rem;"></i>
                        No tipped transactions found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
