<?php
// profile.php - Barber (Staff) personal profile page
require_once 'includes/db.php';
require_once 'includes/header.php';

// Only Staff (Barbers) use this page
if ($_SESSION['role'] !== 'staff') {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- Daily Earnings (Today) ---
$daily_query = "SELECT 
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total, 
        SUM(CASE WHEN type='income' THEN tip ELSE 0 END) as tips, 
        COUNT(CASE WHEN type='income' THEN 1 END) as count,
        SUM(CASE WHEN type='expense' AND expense_category='staff_loan' THEN amount ELSE 0 END) as loan
    FROM transactions 
    WHERE barber_id = $user_id AND DATE(created_at) = CURDATE()";
$daily_res = $conn->query($daily_query);
$daily = $daily_res->fetch_assoc();
$daily_gross_sales = ($daily['total'] ?? 0) - ($daily['tips'] ?? 0);
$daily_commission = ($daily_gross_sales * 0.9) / 2;
$daily_tips = $daily['tips'] ?? 0;
$daily_count = $daily['count'] ?? 0;
$daily_loan = $daily['loan'] ?? 0;
$daily_net = $daily_commission + $daily_tips - $daily_loan;

// --- Weekly Earnings (This Week, Mon-Sun) ---
$weekly_query = "SELECT 
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total, 
        SUM(CASE WHEN type='income' THEN tip ELSE 0 END) as tips, 
        COUNT(CASE WHEN type='income' THEN 1 END) as count,
        SUM(CASE WHEN type='expense' AND expense_category='staff_loan' THEN amount ELSE 0 END) as loan
    FROM transactions 
    WHERE barber_id = $user_id AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
$weekly_res = $conn->query($weekly_query);
$weekly = $weekly_res->fetch_assoc();
$weekly_gross_sales = ($weekly['total'] ?? 0) - ($weekly['tips'] ?? 0);
$weekly_commission = ($weekly_gross_sales * 0.9) / 2;
$weekly_tips = $weekly['tips'] ?? 0;
$weekly_count = $weekly['count'] ?? 0;
$weekly_loan = $weekly['loan'] ?? 0;
$weekly_net = $weekly_commission + $weekly_tips - $weekly_loan;

// --- Monthly Earnings (This Month) ---
$monthly_query = "SELECT 
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total, 
        SUM(CASE WHEN type='income' THEN tip ELSE 0 END) as tips, 
        COUNT(CASE WHEN type='income' THEN 1 END) as count,
        SUM(CASE WHEN type='expense' AND expense_category='staff_loan' THEN amount ELSE 0 END) as loan
    FROM transactions 
    WHERE barber_id = $user_id AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
$monthly_res = $conn->query($monthly_query);
$monthly = $monthly_res->fetch_assoc();
$monthly_gross_sales = ($monthly['total'] ?? 0) - ($monthly['tips'] ?? 0);
$monthly_commission = ($monthly_gross_sales * 0.9) / 2;
$monthly_tips = $monthly['tips'] ?? 0;
$monthly_count = $monthly['count'] ?? 0;
$monthly_loan = $monthly['loan'] ?? 0;
$monthly_net = $monthly_commission + $monthly_tips - $monthly_loan;

// --- Past 3 Weeks Breakdown ---
$past_weeks = [];
for ($i = 0; $i < 3; $i++) {
    $week_start = date('Y-m-d', strtotime("-$i week monday"));
    $week_end = date('Y-m-d', strtotime("-$i week sunday"));
    $label = date('M j', strtotime($week_start)) . ' - ' . date('M j', strtotime($week_end));
    
    $pw_query = "SELECT 
            SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total, 
            SUM(CASE WHEN type='income' THEN tip ELSE 0 END) as tips, 
            COUNT(CASE WHEN type='income' THEN 1 END) as count,
            SUM(CASE WHEN type='expense' AND expense_category='staff_loan' THEN amount ELSE 0 END) as loan
        FROM transactions 
        WHERE barber_id = $user_id AND DATE(created_at) BETWEEN '$week_start' AND '$week_end'";
    $pw_res = $conn->query($pw_query);
    $pw = $pw_res->fetch_assoc();
    
    $pw_gross = ($pw['total'] ?? 0) - ($pw['tips'] ?? 0);
    $pw_commission = ($pw_gross * 0.9) / 2;
    $pw_tips = $pw['tips'] ?? 0;
    $pw_loan = $pw['loan'] ?? 0;
    
    $past_weeks[] = [
        'label' => $label,
        'gross' => $pw_gross,
        'commission' => $pw_commission,
        'tips' => $pw_tips,
        'count' => $pw['count'] ?? 0,
        'loan' => $pw_loan,
        'net' => $pw_commission + $pw_tips - $pw_loan,
        'is_current' => $i === 0
    ];
}

// --- Tips Summary (All time) ---
$tips_all_query = "SELECT 
        SUM(tip) as total_earned,
        SUM(CASE WHEN tip_status = 'paid' THEN tip ELSE 0 END) as total_paid,
        SUM(CASE WHEN tip_status = 'unpaid' THEN tip ELSE 0 END) as total_unpaid
    FROM transactions 
    WHERE barber_id = $user_id AND tip > 0";
$tips_res = $conn->query($tips_all_query);
$tips_summary = $tips_res->fetch_assoc();
$tips_earned = $tips_summary['total_earned'] ?? 0;
$tips_paid = $tips_summary['total_paid'] ?? 0;
$tips_unpaid = $tips_summary['total_unpaid'] ?? 0;

// --- Recent Transactions (barber's own) ---
$recent_query = "
    SELECT t.*, s.name as service_name
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    WHERE t.barber_id = $user_id AND t.type = 'income'
    ORDER BY t.created_at DESC LIMIT 10
";
$recent_tx = $conn->query($recent_query);
?>

<style>
    .profile-header {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-teal), var(--accent-blue));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: white;
        box-shadow: 0 0 25px rgba(45, 212, 191, 0.3);
    }
    .profile-info h2 { margin-bottom: 0.25rem; }
    .profile-info span { color: var(--text-secondary); font-size: 0.9rem; }

    .earnings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .earn-card {
        padding: 1.5rem;
        text-align: center;
    }
    .earn-card-label {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .earn-card-value {
        font-size: 2rem;
        font-weight: 700;
    }
    .earn-card-sub {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }
    .earn-card-tip {
        color: #fbbf24;
        font-size: 0.85rem;
        margin-top: 0.35rem;
        font-weight: 600;
    }

    .weeks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .week-card {
        padding: 1.25rem;
    }
    .week-card-label {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-bottom: 0.5rem;
    }
    .week-card-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--accent-teal);
    }
</style>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-avatar">
        <i class="ph ph-user"></i>
    </div>
    <div class="profile-info">
        <h2><?php echo htmlspecialchars($username); ?></h2>
        <span><i class="ph ph-scissors"></i> Barber</span>
    </div>
</div>

<!-- Earnings Summary -->
<h3 style="margin-bottom: 1rem;"><i class="ph ph-chart-line-up" style="color: var(--accent-teal);"></i> My Earnings</h3>
<div class="earnings-grid">
    <div class="glass-panel earn-card">
        <div class="earn-card-label">Today</div>
        <div class="earn-card-value text-success">$<?php echo number_format($daily_net, 2); ?></div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
            <div>Service: $<?php echo number_format($daily_commission, 2); ?></div>
            <div style="color: #fbbf24;">Tips: $<?php echo number_format($daily_tips, 2); ?></div>
        </div>
        <?php if ($daily_loan > 0): ?>
            <div style="color: var(--accent-rose); font-size: 0.85rem; font-weight: bold; margin-top: 0.35rem;">Loan: -$<?php echo number_format($daily_loan, 2); ?></div>
        <?php endif; ?>
        <div class="earn-card-sub" style="margin-top: 0.5rem;"><?php echo $daily_count; ?> service<?php echo $daily_count != 1 ? 's' : ''; ?></div>
    </div>
    <div class="glass-panel earn-card">
        <div class="earn-card-label">This Week</div>
        <div class="earn-card-value text-success">$<?php echo number_format($weekly_net, 2); ?></div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
            <div>Service: $<?php echo number_format($weekly_commission, 2); ?></div>
            <div style="color: #fbbf24;">Tips: $<?php echo number_format($weekly_tips, 2); ?></div>
        </div>
        <?php if ($weekly_loan > 0): ?>
            <div style="color: var(--accent-rose); font-size: 0.85rem; font-weight: bold; margin-top: 0.35rem;">Loan: -$<?php echo number_format($weekly_loan, 2); ?></div>
        <?php endif; ?>
        <div class="earn-card-sub" style="margin-top: 0.5rem;"><?php echo $weekly_count; ?> service<?php echo $weekly_count != 1 ? 's' : ''; ?></div>
    </div>
    <div class="glass-panel earn-card">
        <div class="earn-card-label">This Month</div>
        <div class="earn-card-value text-success">$<?php echo number_format($monthly_net, 2); ?></div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
            <div>Service: $<?php echo number_format($monthly_commission, 2); ?></div>
            <div style="color: #fbbf24;">Tips: $<?php echo number_format($monthly_tips, 2); ?></div>
        </div>
        <?php if ($monthly_loan > 0): ?>
            <div style="color: var(--accent-rose); font-size: 0.85rem; font-weight: bold; margin-top: 0.35rem;">Loan: -$<?php echo number_format($monthly_loan, 2); ?></div>
        <?php endif; ?>
        <div class="earn-card-sub" style="margin-top: 0.5rem;"><?php echo $monthly_count; ?> service<?php echo $monthly_count != 1 ? 's' : ''; ?></div>
    </div>
</div>

<!-- Past 3 Weeks -->
<h3 style="margin-bottom: 1rem;"><i class="ph ph-calendar" style="color: var(--accent-blue);"></i> Past 3 Weeks</h3>
<div class="weeks-grid">
    <?php foreach ($past_weeks as $week): ?>
        <div class="glass-panel week-card" style="<?php echo $week['is_current'] ? 'border-color: var(--accent-teal); border-width: 1px;' : ''; ?>">
            <div class="week-card-label">
                <?php echo $week['is_current'] ? '🟢 Current Week' : ''; ?>
                <?php echo $week['label']; ?>
            </div>
            <div class="week-card-value">$<?php echo number_format($week['net'], 2); ?></div>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin: 0.4rem 0;">
                <div>Svc: $<?php echo number_format($week['commission'], 2); ?></div>
                <div style="color: #fbbf24;">Tip: $<?php echo number_format($week['tips'], 2); ?></div>
            </div>
            <?php if ($week['loan'] > 0): ?>
                <div style="color: var(--accent-rose); font-size: 0.8rem; font-weight: bold;">Loan: -$<?php echo number_format($week['loan'], 2); ?></div>
            <?php endif; ?>
            <div class="earn-card-sub" style="margin-top: 0.4rem;"><?php echo $week['count']; ?> service<?php echo $week['count'] != 1 ? 's' : ''; ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tips Breakdown -->
<h3 style="margin-bottom: 1rem;"><i class="ph ph-hand-coins" style="color: #fbbf24;"></i> My Tips Balance</h3>
<div class="earnings-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="glass-panel earn-card">
        <div class="earn-card-label">Total Tips Earned</div>
        <div class="earn-card-value" style="color: #fbbf24;">$<?php echo number_format($tips_earned, 2); ?></div>
    </div>
    <div class="glass-panel earn-card">
        <div class="earn-card-label">Already Paid Out</div>
        <div class="earn-card-value text-success">$<?php echo number_format($tips_paid, 2); ?></div>
    </div>
    <div class="glass-panel earn-card" style="border: 1px solid var(--accent-rose);">
        <div class="earn-card-label">Pending / Unpaid</div>
        <div class="earn-card-value text-danger">$<?php echo number_format($tips_unpaid, 2); ?></div>
        <div class="earn-card-sub">Will be paid in next payout</div>
    </div>
</div>

<!-- Recent Services -->
<h3 style="margin-bottom: 1rem;"><i class="ph ph-clock-counter-clockwise" style="color: var(--text-secondary);"></i> Recent Services</h3>
<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Total Sale</th>
                <th>Tip</th>
                <th>My Share</th>
                <th>Net Pay</th>
            </tr>
        </thead>
        <tbody>
            <?php if($recent_tx && $recent_tx->num_rows > 0): ?>
                <?php while($txn = $recent_tx->fetch_assoc()): ?>
                    <tr>
                        <td style="color: var(--text-secondary);"><?php echo date('M j, g:i a', strtotime($txn['created_at'])); ?></td>
                        <td style="font-weight: 500;">
                            <i class="ph ph-scissors" style="color: var(--accent-teal); margin-right: 4px;"></i>
                            <?php echo htmlspecialchars($txn['service_name'] ?? $txn['description'] ?? 'Walk-in'); ?>
                        </td>
                        <td class="text-success" style="font-weight: 600;">$<?php echo number_format($txn['amount'], 2); ?></td>
                        <td style="color: #fbbf24; font-weight: 600;">
                            <?php if (($txn['tip'] ?? 0) > 0): ?>
                                <i class="ph ph-hand-coins"></i> $<?php echo number_format($txn['tip'], 2); ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <?php 
                            $row_gross = $txn['amount'] - ($txn['tip'] ?? 0);
                            $row_comm = ($row_gross * 0.9) / 2;
                            $row_net = $row_comm + ($txn['tip'] ?? 0);
                        ?>
                        <td style="color: var(--accent-blue); font-weight: 600;">$<?php echo number_format($row_comm, 2); ?></td>
                        <td class="text-success" style="font-weight: 700; border-left: 1px solid rgba(255,255,255,0.05);">$<?php echo number_format($row_net, 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        <i class="ph ph-scissors" style="font-size: 2rem; opacity: 0.5; margin-bottom: 0.5rem; display: block;"></i>
                        No services recorded yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
