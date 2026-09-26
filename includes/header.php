<?php
/**
 * Header include - Common header for all pages
 * 
 * This file should be included at the top of every page
 * It requires that $page_title is defined before inclusion
 */

// Ensure config is loaded
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../config/database.php';
}

// Ensure auth functions are available
if (!function_exists('checkAuth')) {
    require_once __DIR__ . '/auth.php';
}

$page_title = $page_title ?? 'RUNEHA INC. ERP';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
</head>
<body>
    <!-- Mobile menu toggle -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <?php 
    // Include sidebar if it exists
    $sidebar_file = __DIR__ . '/sidebar.php';
    if (file_exists($sidebar_file)) {
        include $sidebar_file;
    }
    ?>
    
    <div class="main-content">
        <div class="container">