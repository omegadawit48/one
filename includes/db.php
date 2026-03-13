<?php
// includes/db.php
// Database connection file

$servername = "localhost";
$username = "root"; // Default XAMPP
$password = ""; // Default XAMPP
$dbname = "barbershop_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to sanitize inputs
function sanitize($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags($input)));
}
?>
