<?php
// users.php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Only Admin and Manager can access this page
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    die("<div class='page-content'><h2>Access Denied</h2><p>You do not have permission to view this page.</p></div>");
}

// Fetch Users
$stmt = $conn->prepare("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->get_result();

// Handlers for Delete User
$message = '';
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $del_id = intval($_GET['delete']);
    if ($del_id !== $_SESSION['user_id']) { // Check cannot delete self
        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del_stmt->bind_param("i", $del_id);
        if ($del_stmt->execute()) {
            $message = "<div class='badge badge-income' style='margin-bottom: 1rem;'>User deleted successfully.</div>";
            header("Refresh:1; url=users.php");
        } else {
            $message = "<div class='badge badge-expense' style='margin-bottom: 1rem;'>Error deleting user.</div>";
        }
    } else {
        $message = "<div class='badge badge-expense' style='margin-bottom: 1rem;'>You cannot delete your own account.</div>";
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2>Staff Management</h2>
    <?php if($_SESSION['role'] === 'admin'): ?>
        <a href="new_user.php" class="btn btn-primary" style="text-decoration: none;">
            <i class="ph ph-user-plus"></i> Add New Staff
        </a>
    <?php endif; ?>
</div>

<?php echo $message; ?>

<div class="glass-panel table-container">
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Joined Date</th>
                <?php if($_SESSION['role'] === 'admin'): ?>
                    <th style="text-align: right;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while($u = $users->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-dark); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
                                <i class="ph ph-user"></i>
                            </div>
                            <?php echo htmlspecialchars($u['username']); ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background: rgba(255,255,255,0.1); border: 1px solid var(--border-color);">
                            <?php echo htmlspecialchars($u['role']); ?>
                        </span>
                    </td>
                    <td style="color: var(--text-secondary);">
                        <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                    </td>
                    <?php if($_SESSION['role'] === 'admin'): ?>
                        <td style="text-align: right;">
                            <?php if($u['id'] !== $_SESSION['user_id']): ?>
                                <a href="users.php?delete=<?php echo $u['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; color: var(--accent-rose); border-color: rgba(244,63,94,0.3);" onclick="return confirm('Are you sure you want to delete this user?');">
                                    <i class="ph ph-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
            
            <?php if($users->num_rows === 0): ?>
                <tr><td colspan="4" style="text-align: center; padding: 2rem;">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
