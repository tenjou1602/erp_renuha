<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

header('Location: dashboard.php');
exit();
