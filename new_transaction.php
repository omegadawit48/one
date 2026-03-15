<?php
// new_transaction.php (Now Dedicated "Record Expense")
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

// Fetch barbers (staff role) for tip payment / staff loan
$barbers_res = $conn->query("SELECT id, username FROM users WHERE role = 'staff' ORDER BY username ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_category = sanitize($conn, $_POST['expense_category']);
    $amount = floatval($_POST['amount']);
    $description = sanitize($conn, $_POST['description']);
    $payment_method = sanitize($conn, $_POST['payment_method']);
    $barber_id = isset($_POST['barber_id']) && $_POST['barber_id'] !== '' ? intval($_POST['barber_id']) : NULL;
    
    // Validate inputs
    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif (!in_array($expense_category, ['goods', 'tip_payment', 'staff_loan'])) {
        $error = "Invalid expense category.";
    } elseif (!in_array($payment_method, ['cash', 'bank'])) {
        $error = "Please select a valid payment method.";
    } elseif (in_array($expense_category, ['tip_payment', 'staff_loan']) && !$barber_id) {
        $error = "Please select a Barber for Tip Payments or Staff Loans.";
    } else {
        $stmt = $conn->prepare("INSERT INTO transactions (amount, type, expense_category, description, payment_method, user_id, barber_id) VALUES (?, 'expense', ?, ?, ?, ?, ?)");
        $stmt->bind_param("dsssii", $amount, $expense_category, $description, $payment_method, $user_id, $barber_id);
        
        if ($stmt->execute()) {
            $success = "Expense recorded successfully!";
            
            // If paying out tips, mark all currently unpaid tips for that barber as paid
            if ($expense_category === 'tip_payment') {
                $update_tips = $conn->prepare("UPDATE transactions SET tip_status = 'paid' WHERE barber_id = ? AND tip > 0 AND tip_status = 'unpaid'");
                $update_tips->bind_param("i", $barber_id);
                $update_tips->execute();
            }
        } else {
            $error = "Failed to record expense: " . $conn->error;
        }
    }
}
?>

<style>
    .payment-toggle {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .payment-toggle label {
        cursor: pointer;
    }
    .payment-toggle input[type="radio"] {
        display: none;
    }
    .pay-card {
        background: var(--bg-dark);
        border: 1px solid var(--border-color);
        padding: 1rem;
        border-radius: 8px;
        text-align: center;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .payment-toggle input[type="radio"]:checked + .pay-card {
        border-color: var(--accent-rose);
        background: rgba(244, 63, 94, 0.1);
        color: var(--accent-rose);
        box-shadow: 0 0 10px rgba(244, 63, 94, 0.15);
    }
</style>

<div class="page-content">
    <div style="max-width: 600px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>Record Expense</h2>
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
                <div class="badge badge-expense" style="background: rgba(45,212,191,0.1); color: var(--accent-teal); border-color: rgba(45,212,191,0.2); display: block; margin-bottom: 1.5rem; text-align: center; padding: 0.75rem; border-radius: 8px;">
                    <?php echo $success; ?>
                </div>
                <script>setTimeout(() => window.location.href='new_transaction.php', 1500);</script>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Expense Category -->
                <div class="form-group">
                    <label class="form-label" for="expense_category">
                        <i class="ph ph-tag" style="color: var(--accent-rose);"></i> Expense Category
                    </label>
                    <select id="expense_category" name="expense_category" class="form-control" style="background-color: var(--bg-dark);" required onchange="toggleBarberField()">
                        <option value="" style="background: var(--bg-dark);">-- Select Category --</option>
                        <option value="goods" style="background: var(--bg-dark);">Goods Purchase (Supplies, etc.)</option>
                        <option value="tip_payment" style="background: var(--bg-dark);">Tip Payment (Paying out tips)</option>
                        <option value="staff_loan" style="background: var(--bg-dark);">Staff Loan / Advance</option>
                    </select>
                </div>

                <!-- Barber Selection (Hidden by default, shown for tips and loans) -->
                <div id="barber-group" class="form-group" style="display: none;">
                    <label class="form-label" for="barber_id">
                        <i class="ph ph-user-circle" style="color: var(--accent-blue);"></i> Select Barber
                    </label>
                    <select id="barber_id" name="barber_id" class="form-control" style="background-color: var(--bg-dark);" onchange="fetchUnpaidTips()">
                        <option value="" style="background: var(--bg-dark);">-- Select Barber --</option>
                        <?php while($b = $barbers_res->fetch_assoc()): ?>
                            <option value="<?php echo $b['id']; ?>" style="background: var(--bg-dark);">
                                <?php echo htmlspecialchars($b['username']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Amount -->
                <div class="form-group">
                    <label class="form-label" for="amount" id="amount-label">Total Amount ($)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 1rem; top: 0.75rem; color: var(--text-secondary);">$</span>
                        <input type="number" step="0.01" id="amount" name="amount" class="form-control" required style="padding-left: 2rem; font-size: 1.2rem; font-weight: bold;" placeholder="0.00">
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

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label" for="description">Notes / Description (Optional)</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="E.g., Bought 10 bottles of hair gel"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.1rem; padding: 1rem; background: var(--accent-rose);">
                    <i class="ph ph-floppy-disk"></i> Save Expense
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleBarberField() {
        const cat = document.getElementById('expense_category').value;
        const barberGroup = document.getElementById('barber-group');
        const barberInput = document.getElementById('barber_id');
        const amountLabel = document.getElementById('amount-label');
        const amountInput = document.getElementById('amount');
        
        if (cat === 'tip_payment' || cat === 'staff_loan') {
            barberGroup.style.display = 'block';
            barberInput.required = true;
            if (cat === 'tip_payment') {
                amountLabel.innerHTML = '<i class="ph ph-hand-coins" style="color: #fbbf24;"></i> Unpaid Tip Amount ($)';
                amountInput.readOnly = true;
                amountInput.style.backgroundColor = 'rgba(255,255,255,0.05)';
                fetchUnpaidTips();
            } else {
                amountLabel.innerHTML = 'Total Amount ($)';
                amountInput.readOnly = false;
                amountInput.style.backgroundColor = '';
                amountInput.value = '';
            }
        } else {
            barberGroup.style.display = 'none';
            barberInput.required = false;
            barberInput.value = '';
            amountLabel.innerHTML = 'Total Amount ($)';
            amountInput.readOnly = false;
            amountInput.style.backgroundColor = '';
            amountInput.value = '';
        }
    }

    async function fetchUnpaidTips() {
        const cat = document.getElementById('expense_category').value;
        const barberId = document.getElementById('barber_id').value;
        const amountInput = document.getElementById('amount');
        
        if (cat === 'tip_payment' && barberId) {
            try {
                const response = await fetch(`ajax_get_unpaid_tips.php?barber_id=${barberId}`);
                if (response.ok) {
                    const totalUnpaid = await response.text();
                    amountInput.value = totalUnpaid;
                } else {
                    amountInput.value = '0.00';
                }
            } catch (err) {
                console.error("Failed to fetch unpaid tips", err);
                amountInput.value = '0.00';
            }
        } else if (cat === 'tip_payment') {
            amountInput.value = '0.00'; // Reset if no barber picked yet
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
