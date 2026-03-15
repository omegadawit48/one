<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'staff') {
    echo "0.00";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['barber_id'])) {
    $barber_id = intval($_GET['barber_id']);
    $query = "SELECT SUM(tip) as unpaid_total FROM transactions WHERE barber_id = $barber_id AND tip > 0 AND tip_status = 'unpaid'";
    $res = $conn->query($query);
    if ($res && $row = $res->fetch_assoc()) {
        echo number_format($row['unpaid_total'] ?? 0, 2, '.', '');
    } else {
        echo "0.00";
    }
}
?>
