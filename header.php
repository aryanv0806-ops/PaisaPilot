<?php
// Get current page for active nav link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paisa Pilot | <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar">
        <span class="nav-brand-name">Paisa Pilot</span>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="portfolio.php" class="<?php echo ($current_page == 'portfolio.php') ? 'active' : ''; ?>">Portfolio</a></li>
            <li><a href="expenses.php" class="<?php echo ($current_page == 'expenses.php') ? 'active' : ''; ?>">Expenses</a></li>
            <li><a href="wallet.php" class="<?php echo ($current_page == 'wallet.php') ? 'active' : ''; ?>">Wallet</a></li>
            <li><a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">Profile</a></li>
            <li><a href="logout.php" class="nav-logout">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
