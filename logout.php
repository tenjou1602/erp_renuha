<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'auth', 'User logged out');
}

session_destroy();

header('Location: login.php?logged_out=1');
exit();
