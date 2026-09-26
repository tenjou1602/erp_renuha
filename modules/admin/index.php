<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

// Redirect to the new Executive Dashboard
header('Location: executive_dashboard.php');
exit();
