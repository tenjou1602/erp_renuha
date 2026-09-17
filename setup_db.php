<?php
// setup_db.php - Run this once to setup the database with proper passwords
require_once 'config/database.php';

// First, disable foreign key checks to allow deletion
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// Delete existing users
$pdo->exec("DELETE FROM users");

// Re-enable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// Create users with password 'password123' (hashed)
$password = password_hash('password123', PASSWORD_DEFAULT);

$users = [
    ['admin', $password, 'System Administrator', 'admin@runeha.com', 'admin', 'admin', 0],
    ['procurement_mgr', $password, 'Procurement Manager', 'procurement@runeha.com', 'procurement', 'manager', 50000],
    ['projects_mgr', $password, 'Projects Manager', 'projects@runeha.com', 'projects', 'manager', 50000],
    ['procurement_staff', $password, 'Procurement Staff', 'procurement.staff@runeha.com', 'procurement', 'staff', 25000],
    ['projects_staff', $password, 'Projects Staff', 'projects.staff@runeha.com', 'projects', 'staff', 25000],
    ['accounting_mgr', $password, 'Accounting Manager', 'accounting@runeha.com', 'accounting', 'manager', 50000],
    ['accounting_staff', $password, 'Accounting Staff', 'accounting.staff@runeha.com', 'accounting', 'staff', 25000],
    ['warehouse_mgr', $password, 'Warehouse Manager', 'warehouse@runeha.com', 'warehouse', 'manager', 50000],
    ['warehouse_staff', $password, 'Warehouse Staff', 'warehouse.staff@runeha.com', 'warehouse', 'staff', 25000],
];

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, department, role, monthly_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
    
    foreach ($users as $user) {
        $stmt->execute($user);
        echo "Created user: {$user[0]}<br>";
    }
    
    $pdo->commit();
    
    echo "<br><strong style='color:green;'>Setup complete! You can now login with:</strong><br>";
    echo "Username: <strong>admin</strong><br>";
    echo "Password: <strong>password123</strong><br>";
    echo "<br><a href='login.php' style='display:inline-block;padding:10px 20px;background:#4e73df;color:white;text-decoration:none;border-radius:8px;'>Go to Login</a>";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>