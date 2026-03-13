<?php
// new_user.php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Only Admin can add users
if ($_SESSION['role'] !== 'admin') {
    die("<div class='page-content'><h2>Access Denied</h2><p>Only administrators can add new users.</p></div>");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($conn, $_POST['username']);
    $role = sanitize($conn, $_POST['role']);
    $password = $_POST['password'];
    
    // Check if username exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $error = "Username already exists.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert_stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("sss", $username, $hash, $role);
        
        if ($insert_stmt->execute()) {
            $success = "User added successfully!";
        } else {
            $error = "Failed to add user.";
        }
    }
}
?>

<div style="max-width: 500px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2>Add New Staff</h2>
        <a href="users.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">
            <i class="ph ph-arrow-left"></i> Back
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
            <script>setTimeout(() => window.location.href='users.php', 1500);</script>
        <?php else: ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="role">Role</label>
                    <select id="role" name="role" class="form-control" required style="appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat, repeat; background-position: right .7em top 50%, 0 0; background-size: .65em auto, 100%;">
                        <option value="staff" style="background: var(--bg-dark);">Staff</option>
                        <option value="manager" style="background: var(--bg-dark);">Manager</option>
                        <option value="admin" style="background: var(--bg-dark);">Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="ph ph-user-plus"></i> Create User
                </button>
            </form>
            
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
