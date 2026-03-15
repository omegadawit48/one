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
    // Handle inline transaction submission
    $tx_error = '';
    $tx_success = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_sale') {
        $tip = floatval($_POST['tip'] ?? 0);
        $description = sanitize($conn, $_POST['description']);
        $service_ids = $_POST['service_ids'] ?? [];
        $barber_id = isset($_POST['barber_id']) && $_POST['barber_id'] !== '' ? intval($_POST['barber_id']) : NULL;
        $payment_method = sanitize($conn, $_POST['payment_method']);

        if (empty($service_ids)) {
            $tx_error = "Please select at least one service.";
        } elseif (!$barber_id) {
            $tx_error = "Please select the barber who performed the service(s).";
        } elseif (!in_array($payment_method, ['cash', 'bank'])) {
            $tx_error = "Please select a valid payment method.";
        } else {
            $conn->begin_transaction();
            try {
                foreach ($service_ids as $index => $sid) {
                    if (empty($sid)) continue;
                    $service_id = intval($sid);
                    
                    // Calculate amount for this specific service
                    $service_price = 0;
                    $sp_res = $conn->query("SELECT price FROM services WHERE id = $service_id");
                    if ($sp_res && $sp_row = $sp_res->fetch_assoc()) {
                        $service_price = floatval($sp_row['price']);
                    }
                    
                    // Only apply tip to the very first service in the list to avoid duplicate tips
                    $row_tip = ($index === 0) ? $tip : 0;
                    $amount = $service_price + $row_tip;
                    
                    $stmt = $conn->prepare("INSERT INTO transactions (amount, tip, type, description, payment_method, service_id, user_id, barber_id) VALUES (?, ?, 'income', ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ddssiii", $amount, $row_tip, $description, $payment_method, $service_id, $user_id, $barber_id);
                    $stmt->execute();
                }
                $conn->commit();
                $tx_success = "Sale recorded successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $tx_error = "Failed to record: " . $e->getMessage();
            }
        }
    }

    // Fetch services and barbers for the inline form
    $services_res = $conn->query("SELECT id, name, price, type FROM services ORDER BY name ASC");
    $barbers_res = $conn->query("SELECT id, username FROM users WHERE role = 'staff' ORDER BY username ASC");

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
    .cashier-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    @media (max-width: 900px) {
        .cashier-layout { grid-template-columns: 1fr; }
    }
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-secondary);
        font-size: 0.85rem;
    }
    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
    }
    .stat-sub {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }
    .text-success { color: var(--accent-teal); }
    .text-danger { color: var(--accent-rose); }
    .quick-form {
        padding: 1.5rem;
    }
    .quick-form h3 {
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .payment-toggle {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    .payment-toggle label {
        cursor: pointer;
    }
    .payment-toggle input[type="radio"] { display: none; }
    .payment-toggle .pay-card {
        padding: 0.75rem;
        text-align: center;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: rgba(15, 23, 42, 0.5);
        transition: all 0.2s;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .payment-toggle input[type="radio"]:checked + .pay-card {
        border-color: var(--accent-teal);
        background: rgba(45, 212, 191, 0.1);
        color: var(--accent-teal);
        box-shadow: 0 0 10px rgba(45, 212, 191, 0.15);
    }
</style>

<div class="cashier-layout">
    <!-- LEFT: Quick Record Form -->
    <div class="glass-panel quick-form">
        <h3><i class="ph ph-plus-circle" style="color: var(--accent-teal);"></i> Quick Record Sale</h3>

        <?php if($tx_error): ?>
            <div style="background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.2); color: var(--accent-rose); padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                <?php echo $tx_error; ?>
            </div>
        <?php endif; ?>
        <?php if($tx_success): ?>
            <div style="background: rgba(45,212,191,0.1); border: 1px solid rgba(45,212,191,0.2); color: var(--accent-teal); padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                <?php echo $tx_success; ?>
            </div>
            <script>setTimeout(() => window.location.href='index.php', 1200);</script>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="record_sale">

            <div class="form-group">
                <label class="form-label" for="barber_id"><i class="ph ph-user-circle" style="color: var(--accent-blue);"></i> Barber</label>
                <select id="barber_id" name="barber_id" class="form-control" required style="background-color: var(--bg-dark);">
                    <option value="" style="background: var(--bg-dark);">-- Select Barber --</option>
                    <?php while($b = $barbers_res->fetch_assoc()): ?>
                        <option value="<?php echo $b['id']; ?>" style="background: var(--bg-dark);"><?php echo htmlspecialchars($b['username']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" id="services-container">
                <label class="form-label"><i class="ph ph-scissors" style="color: var(--accent-teal);"></i> Services</label>
                <div class="service-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <select name="service_ids[]" class="form-control" style="background-color: var(--bg-dark);" required>
                        <option value="" style="background: var(--bg-dark);">-- Select Service --</option>
                        <?php 
                        $services_res->data_seek(0);
                        while($s = $services_res->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $s['id']; ?>" style="background: var(--bg-dark);">
                                <?php echo htmlspecialchars($s['name']) . " - $" . number_format($s['price'], 2); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <button type="button" onclick="addServiceRow()" class="btn btn-outline" style="width: 100%; border-style: dashed; margin-bottom: 1rem; padding: 0.5rem; color: var(--text-secondary); border-color: var(--border-color);">
                <i class="ph ph-plus"></i> Add Another Service
            </button>

            <div class="form-group">
                <label class="form-label" for="tip"><i class="ph ph-hand-coins" style="color: var(--accent-teal);"></i> Tip (Optional)</label>
                <input type="number" step="0.01" id="tip" name="tip" class="form-control" placeholder="0.00" value="0" style="font-size: 1.1rem; font-weight: bold;">
            </div>

            <!-- Payment Method Toggle -->
            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <div class="payment-toggle">
                    <label>
                        <input type="radio" name="payment_method" value="cash" checked>
                        <div class="pay-card"><i class="ph ph-money"></i> Cash</div>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="bank">
                        <div class="pay-card"><i class="ph ph-bank"></i> Bank</div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Notes (Optional)</label>
                <input type="text" id="description" name="description" class="form-control" placeholder="E.g. Tips included">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                <i class="ph ph-floppy-disk"></i> Save Sale
            </button>
        </form>
    </div>

    <!-- RIGHT: Past 3 Days Stats -->
    <div>
        <h3 style="margin-bottom: 1rem;"><i class="ph ph-calendar-check" style="color: var(--accent-teal);"></i> Past 3 Days</h3>
        <div class="dashboard-grid">
            <?php foreach ($days as $day): ?>
                <div class="glass-panel stat-card">
                    <div class="stat-card-header">
                        <span><?php echo $day['label']; ?></span>
                        <span style="font-size: 0.75rem;"><?php echo date('M j', strtotime($day['date'])); ?></span>
                    </div>
                    <div class="stat-value text-success">$<?php echo number_format($day['total'], 2); ?></div>
                    <div class="stat-sub"><?php echo $day['count']; ?> sale<?php echo $day['count'] != 1 ? 's' : ''; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Sales Table -->
<h3 style="margin-bottom: 1rem;">Recent Sales</h3>
<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Barber</th>
                <th>Payment</th>
                <th>Sale Total</th>
                <th>Tip</th>
            </tr>
        </thead>
        <tbody>
            <?php if($recent_tx && $recent_tx->num_rows > 0): ?>
                <?php while($txn = $recent_tx->fetch_assoc()): ?>
                    <tr>
                        <td style="color: var(--text-secondary);"><?php echo date('M j, g:i a', strtotime($txn['created_at'])); ?></td>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($txn['service_name'] ?? $txn['description'] ?? 'Walk-in'); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="ph ph-user-circle" style="color: var(--accent-blue);"></i>
                                <?php echo htmlspecialchars($txn['barber_name'] ?? 'Unassigned'); ?>
                            </div>
                        </td>
                        <td>
                            <?php if (($txn['payment_method'] ?? 'cash') === 'cash'): ?>
                                <span class="badge" style="background: rgba(45,212,191,0.1); color: var(--accent-teal); border: 1px solid rgba(45,212,191,0.2);"><i class="ph ph-money"></i> Cash</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--accent-blue); border: 1px solid rgba(59,130,246,0.2);"><i class="ph ph-bank"></i> Bank</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-success" style="font-weight: 600;">$<?php echo number_format($txn['amount'], 2); ?></td>
                        <td>
                            <?php if ($txn['tip'] > 0): ?>
                                <?php if ($txn['tip_status'] === 'paid'): ?>
                                    <span style="color: var(--text-secondary); font-size: 0.85rem; font-style: italic;"><i class="ph ph-check-circle" style="color: var(--accent-teal);"></i> Paid out</span>
                                <?php else: ?>
                                    <span style="color: #fbbf24; font-weight: 700;">$<?php echo number_format($txn['tip'], 2); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No sales in the past 3 days.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function addServiceRow() {
    const container = document.getElementById('services-container');
    const firstRow = container.querySelector('.service-row');
    const newRow = document.createElement('div');
    newRow.className = 'service-row';
    newRow.style.display = 'flex';
    newRow.style.gap = '0.5rem';
    newRow.style.marginBottom = '0.5rem';
    
    // Clone select
    const select = firstRow.querySelector('select').cloneNode(true);
    select.value = '';
    
    // Create remove button
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-outline';
    removeBtn.style.padding = '0 0.75rem';
    removeBtn.style.borderColor = 'var(--accent-rose)';
    removeBtn.style.color = 'var(--accent-rose)';
    removeBtn.innerHTML = '<i class="ph ph-trash"></i>';
    removeBtn.onclick = function() { newRow.remove(); };
    
    newRow.appendChild(select);
    newRow.appendChild(removeBtn);
    container.appendChild(newRow);
}
</script>

<?php 
    require_once 'includes/footer.php';
    exit;
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

$where_clause = "1=1";

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
                <th>Payment</th>
                <th>Sale Total</th>
                <th>Tip</th>
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
                        <td><?php echo htmlspecialchars($txn['service_name'] ?? $txn['description'] ?? 'Walk-in Sale'); ?></td>
                        <td>
                            <?php if (($txn['payment_method'] ?? 'cash') === 'cash'): ?>
                                <span class="badge" style="background: rgba(45,212,191,0.1); color: var(--accent-teal); border: 1px solid rgba(45,212,191,0.2);"><i class="ph ph-money"></i> Cash</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--accent-blue); border: 1px solid rgba(59,130,246,0.2);"><i class="ph ph-bank"></i> Bank</span>
                            <?php endif; ?>
                        </td>
                        <td class="<?php echo $txn['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($txn['amount'], 2); ?>
                        </td>
                        <td>
                            <?php if ($txn['type'] === 'income' && $txn['tip'] > 0): ?>
                                <?php if ($txn['tip_status'] === 'paid'): ?>
                                    <span style="color: var(--text-secondary); font-size: 0.85rem; font-style: italic;"><i class="ph ph-check-circle" style="color: var(--accent-teal);"></i> Paid out</span>
                                <?php else: ?>
                                    <span style="color: #fbbf24; font-weight: 700;">$<?php echo number_format($txn['tip'], 2); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($txn['barber_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($txn['staff_name']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No recent transactions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
