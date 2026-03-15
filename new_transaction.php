<?php
// new_transaction.php
require_once 'includes/db.php';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = '';
$success = '';

// Only Admin, Manager, and Cashier can record transactions
if ($role === 'staff') {
    die("<div class='page-content'><h2>Access Denied</h2><p>Barbers cannot record transactions directly.</p></div>");
}

// Fetch active services for the dropdown
$services_res = $conn->query("SELECT id, name, price, type FROM services ORDER BY name ASC");

// Fetch barbers (staff role) for the cashier to associate with
$barbers_res = $conn->query("SELECT id, username FROM users WHERE role = 'staff' ORDER BY username ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = sanitize($conn, $_POST['type']);
    $amount = floatval($_POST['amount']);
    $description = sanitize($conn, $_POST['description']);
    $payment_method = sanitize($conn, $_POST['payment_method']);
    $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? intval($_POST['service_id']) : NULL;
    $barber_id = isset($_POST['barber_id']) && $_POST['barber_id'] !== '' ? intval($_POST['barber_id']) : NULL;
    
    // Validate inputs
    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif (!in_array($type, ['income', 'expense'])) {
        $error = "Invalid transaction type.";
    } elseif (!in_array($payment_method, ['cash', 'bank'])) {
        $error = "Please select a valid payment method.";
    } elseif ($type === 'income' && $role === 'cashier' && !$barber_id) {
        $error = "Please select the barber who performed this service.";
    } else {
        $stmt = $conn->prepare("INSERT INTO transactions (amount, type, description, payment_method, service_id, user_id, barber_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("dsssiii", $amount, $type, $description, $payment_method, $service_id, $user_id, $barber_id);
        
        if ($stmt->execute()) {
            $success = "Transaction recorded successfully!";
        } else {
            $error = "Failed to record transaction: " . $conn->error;
        }
    }
}
?>

<style>
    .payment-toggle {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    .payment-toggle label { cursor: pointer; }
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

<div style="max-width: 600px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2>Record Transaction</h2>
        <a href="transactions.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">
            <i class="ph ph-arrow-left"></i> Back to Ledger
        </a>
    </div>

    <div class="glass-panel" style="padding: 2rem;">
        <?php if($error): ?>
            <div class="badge badge-expense" style="display: block; margin-bottom: 1.5rem; text-align: center; padding: 0.75rem; border-radius: 8px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="badge badge-income" style="display: block; margin-bottom: 1.5rem; text-align: center; padding: 0.75rem; border-radius: 8px;">
                <?php echo $success; ?>
            </div>
            <script>setTimeout(() => window.location.href='new_transaction.php', 1500);</script>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Type Selection Cards -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <label style="cursor: pointer; position: relative;">
                    <input type="radio" name="type" value="income" checked id="type-income" style="display:none;" onchange="updateFormMode()">
                    <div class="glass-panel" id="card-income" style="padding: 1.5rem; text-align: center; border-color: var(--accent-teal); border-width: 2px;">
                        <i class="ph ph-trend-up" style="font-size: 2rem; color: var(--accent-teal); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--accent-teal);">Income / Sale</h4>
                    </div>
                </label>
                
                <?php if ($role === 'admin' || $role === 'manager'): ?>
                <label style="cursor: pointer; position: relative;">
                    <input type="radio" name="type" value="expense" id="type-expense" style="display:none;" onchange="updateFormMode()">
                    <div class="glass-panel" id="card-expense" style="padding: 1.5rem; text-align: center; opacity: 0.6;">
                        <i class="ph ph-trend-down" style="font-size: 2rem; color: var(--accent-rose); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--accent-rose);">Shop Expense</h4>
                    </div>
                </label>
                <?php endif; ?>
            </div>

            <!-- Barber Selection -->
            <div id="barber-group" class="form-group">
                <label class="form-label" for="barber_id">
                    <i class="ph ph-user-circle" style="color: var(--accent-blue);"></i> Barber Who Performed Service
                </label>
                <select id="barber_id" name="barber_id" class="form-control" style="background-color: var(--bg-dark);" <?php echo $role === 'cashier' ? 'required' : ''; ?>>
                    <option value="" style="background: var(--bg-dark);">-- Select Barber --</option>
                    <?php while($b = $barbers_res->fetch_assoc()): ?>
                        <option value="<?php echo $b['id']; ?>" style="background: var(--bg-dark);">
                            <?php echo htmlspecialchars($b['username']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div id="service-group" class="form-group">
                <label class="form-label" for="service_id">Service / Product Performed</label>
                <select id="service_id" name="service_id" class="form-control" style="background-color: var(--bg-dark);" onchange="updateAmount()">
                    <option value="" style="background: var(--bg-dark);">-- Custom / Other --</option>
                    <?php while($s = $services_res->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>" data-price="<?php echo $s['price']; ?>" style="background: var(--bg-dark);">
                            <?php echo htmlspecialchars($s['name']) . " - $" . number_format($s['price'], 2); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" id="amount-group">
                <label class="form-label" for="amount">Total Amount ($)</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 1rem; top: 0.75rem; color: var(--text-secondary);">$</span>
                    <input type="number" step="0.01" id="amount" name="amount" class="form-control" required style="padding-left: 2rem; font-size: 1.2rem; font-weight: bold;">
                </div>
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
                <label class="form-label" for="description">Notes / Description (Optional)</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="E.g., Client paid via Cash, tips included, or bought hair gel."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.1rem; padding: 1rem;">
                <i class="ph ph-floppy-disk"></i> Save Transaction
            </button>
        </form>
    </div>
</div>

<script>
    function updateFormMode() {
        const isIncome = document.getElementById('type-income').checked;
        const cardIncome = document.getElementById('card-income');
        const cardExpense = document.getElementById('card-expense');
        const serviceGroup = document.getElementById('service-group');
        const barberGroup = document.getElementById('barber-group');
        
        if (isIncome) {
            cardIncome.style.borderColor = 'var(--accent-teal)';
            cardIncome.style.borderWidth = '2px';
            cardIncome.style.opacity = '1';
            if (cardExpense) { cardExpense.style.borderColor = 'var(--border-color)'; cardExpense.style.borderWidth = '1px'; cardExpense.style.opacity = '0.6'; }
            serviceGroup.style.display = 'block';
            barberGroup.style.display = 'block';
        } else {
            if (cardExpense) { cardExpense.style.borderColor = 'var(--accent-rose)'; cardExpense.style.borderWidth = '2px'; cardExpense.style.opacity = '1'; }
            cardIncome.style.borderColor = 'var(--border-color)';
            cardIncome.style.borderWidth = '1px';
            cardIncome.style.opacity = '0.6';
            serviceGroup.style.display = 'none';
            barberGroup.style.display = 'none';
            document.getElementById('service_id').value = '';
            document.getElementById('barber_id').value = '';
        }
    }

    function updateAmount() {
        const serviceSelect = document.getElementById('service_id');
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        if (price) document.getElementById('amount').value = price;
    }
</script>

<?php require_once 'includes/footer.php'; ?>
