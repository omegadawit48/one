<?php
// services.php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Only Admin and Manager can access this page
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    die("<div class='page-content'><h2>Access Denied</h2><p>You do not have permission to view this page.</p></div>");
}

// Handle Service Deletion
$message = '';
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $del_id = intval($_GET['delete']);
    $del_stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $del_stmt->bind_param("i", $del_id);
    if ($del_stmt->execute()) {
        $message = "<div class='badge badge-income' style='margin-bottom: 1rem;'>Service deleted successfully.</div>";
        header("Refresh:1; url=services.php");
    } else {
        $message = "<div class='badge badge-expense' style='margin-bottom: 1rem;'>Error deleting service.</div>";
    }
}

// Handle Service Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = sanitize($conn, $_POST['name']);
    $type = sanitize($conn, $_POST['type']);
    $price = floatval($_POST['price']);
    
    $insert_stmt = $conn->prepare("INSERT INTO services (name, type, price) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("ssd", $name, $type, $price);
    
    if ($insert_stmt->execute()) {
        $message = "<div class='badge badge-income' style='margin-bottom: 1rem;'>Service/Product added successfully.</div>";
    } else {
        $message = "<div class='badge badge-expense' style='margin-bottom: 1rem;'>Error adding item.</div>";
    }
}


// Fetch Services
$stmt = $conn->prepare("SELECT id, name, type, price FROM services ORDER BY type, name ASC");
$stmt->execute();
$services = $stmt->get_result();

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2>Services & Products Catalog</h2>
    <?php if($_SESSION['role'] === 'admin'): ?>
        <button class="btn btn-primary" onclick="document.getElementById('addServiceModal').style.display='block';">
            <i class="ph ph-plus"></i> Add Item
        </button>
    <?php endif; ?>
</div>

<?php echo $message; ?>

<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Item Name</th>
                <th>Category</th>
                <th>Price</th>
                <?php if($_SESSION['role'] === 'admin'): ?>
                    <th style="text-align: right;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while($s = $services->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px; font-weight: 500;">
                            <i class="ph <?php echo $s['type'] === 'service' ? 'ph-scissors' : 'ph-package'; ?>" style="color: var(--accent-teal);"></i>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </div>
                    </td>
                    <td>
                        <span style="text-transform: capitalize; color: var(--text-secondary);">
                            <?php echo htmlspecialchars($s['type']); ?>
                        </span>
                    </td>
                    <td style="color: var(--text-primary); font-weight: 600;">
                        $<?php echo number_format($s['price'], 2); ?>
                    </td>
                    <?php if($_SESSION['role'] === 'admin'): ?>
                        <td style="text-align: right;">
                            <a href="services.php?delete=<?php echo $s['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; color: var(--accent-rose); border-color: rgba(244,63,94,0.3);" onclick="return confirm('Delete this item?');">
                                <i class="ph ph-trash"></i>
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
            
            <?php if($services->num_rows === 0): ?>
                <tr><td colspan="4" style="text-align: center; padding: 2rem;">No services found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Simple inline modal for adding service -->
<div id="addServiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.8); backdrop-filter: blur(5px); z-index: 100; align-items: center; justify-content: center;">
    <div class="glass-panel animate-fade-in" style="width: 100%; max-width: 400px; padding: 2rem; position: relative; margin: 10vh auto;">
        <button onclick="document.getElementById('addServiceModal').style.display='none';" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.5rem;">&times;</button>
        <h3 style="margin-bottom: 1.5rem;">Add New Item</h3>
        
        <form method="POST" action="services.php">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label" for="name">Item Name</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Skin Fade">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="type">Category</label>
                <select id="type" name="type" class="form-control" required style="appearance: none; background-color: var(--bg-dark);">
                    <option value="service" style="background: var(--bg-dark);">Service</option>
                    <option value="product" style="background: var(--bg-dark);">Product</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="price">Price ($)</label>
                <input type="number" step="0.01" id="price" name="price" class="form-control" required placeholder="25.00">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                <i class="ph ph-check"></i> Save Item
            </button>
        </form>
    </div>
</div>

<script>
    // Close modal if clicked outside
    window.onclick = function(event) {
        var modal = document.getElementById('addServiceModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
