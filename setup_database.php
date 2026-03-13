<?php
// setup_database.php
// This script initializes the database and tables for the barbershop system.
// Run this file once in the browser (e.g., http://localhost/one/setup_database.php) or via CLI

$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password
$dbname = "barbershop_db";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// 1. Create Users Table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_users) === TRUE) {
    echo "Table 'users' created successfully.<br>";
} else {
    echo "Error creating table 'users': " . $conn->error . "<br>";
}

// 2. Create Services/Products Table
$sql_services = "CREATE TABLE IF NOT EXISTS services (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('service', 'product') NOT NULL DEFAULT 'service',
    price DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_services) === TRUE) {
    echo "Table 'services' created successfully.<br>";
} else {
    echo "Error creating table 'services': " . $conn->error . "<br>";
}

// 3. Create Transactions Table
$sql_transactions = "CREATE TABLE IF NOT EXISTS transactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    description TEXT,
    service_id INT(11) NULL,
    user_id INT(11) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql_transactions) === TRUE) {
    echo "Table 'transactions' created successfully.<br>";
} else {
    echo "Error creating table 'transactions': " . $conn->error . "<br>";
}

// Insert Default Admin User (Password: admin123)
$admin_password_hash = password_hash('admin123', PASSWORD_DEFAULT);
$sql_admin = "INSERT IGNORE INTO users (username, password_hash, role) VALUES ('admin', '$admin_password_hash', 'admin')";
if ($conn->query($sql_admin) === TRUE) {
    echo "Default admin user ensured (username: admin, password: admin123).<br>";
} else {
    echo "Error inserting admin user: " . $conn->error . "<br>";
}

// Seed some default services
$sql_seed_services = "INSERT IGNORE INTO services (id, name, type, price) VALUES 
    (1, 'Standard Haircut', 'service', 25.00),
    (2, 'Beard Trim', 'service', 15.00),
    (3, 'Haircut & Beard', 'service', 35.00),
    (4, 'Hair Gel 100ml', 'product', 12.50)";

if ($conn->query($sql_seed_services) === TRUE) {
    echo "Default services seeded.<br>";
} else {
    echo "Error seeding services: " . $conn->error . "<br>";
}

$conn->close();
echo "<h2>Database setup complete!</h2>";
echo "<p>Please delete this file (`setup_database.php`) after running it for security reasons.</p>";
?>
