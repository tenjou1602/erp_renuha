<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireDepartment(['procurement']);
header('Location: dashboard.php');
exit();
