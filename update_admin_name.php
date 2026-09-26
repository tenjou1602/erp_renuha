<?php
require_once 'config/database.php';
global $pdo;
$pdo->exec("UPDATE users SET full_name = 'Executive Admin' WHERE username = 'admin'");
echo "Updated admin name to Executive Admin";
