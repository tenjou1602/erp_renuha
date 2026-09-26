<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireDepartment(['engineering']);
header('Location: dashboard.php');
exit();
