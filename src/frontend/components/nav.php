<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Determine user display info
$userName = 'Guest';
$userRole = 'User';
$avatarInitials = 'GU';

if (isset($_SESSION['username'])) {
    $userName = ucfirst($_SESSION['username']);
    $userRole = 'Manager';
    $avatarInitials = strtoupper(substr($_SESSION['username'], 0, 2));
} elseif (isset($_SESSION['driver_name'])) {
    $userName = $_SESSION['driver_name'];
    $userRole = 'Driver';
    $nameParts = explode(' ', $userName);
    if (count($nameParts) >= 2) {
        $avatarInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } else {
        $avatarInitials = strtoupper(substr($userName, 0, 2));
    }
}

  $loginHref = '../../../login.php';
  $logoutHref = '../../../logout.php?return=login.php';
?>

<aside class="sidebar">
    <div class="sidebar-logo">
      <a href="<?php echo htmlspecialchars($loginHref); ?>" aria-label="Go to login page">
        <img class="logo-text" src="../assets/logo.png" alt="Logo">
      </a>
    </div>

    <nav class="nav-section">
      <div class="nav-label">Overview</div>

      <?php if ($userRole === 'Driver'): ?>
        <a href="tracking.php" class="nav-item <?php echo ($currentPage === 'tracking.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000-2H3a1 1 0 000 2h1a1 1 0 010 2v10a2 2 0 002 2h8a2 2 0 002-2V5a1 1 0 110-2h-1a1 1 0 000 2h2a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
          </svg>
          Tracking
        </a>
      <?php else: ?>
        <a href="index.php" class="nav-item <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 4a1 1 0 011-1h2a1 1 0 010 2H5v2h2a1 1 0 110 2H5v2h2a1 1 0 110 2H5v2h10v-2h-2a1 1 0 110-2h2v-2h-2a1 1 0 110-2h2V5h-2a1 1 0 110-2h2a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4z"/>
          </svg>
          Dashboard
        </a>

        <div class="nav-label" style="margin-top:16px;">Management</div>

        <a href="drivers.php" class="nav-item <?php echo ($currentPage === 'drivers.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
          </svg>
          Drivers
        </a>

        <a href="vehicles.php" class="nav-item <?php echo ($currentPage === 'vehicles.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
            <path d="M3 4h14l-1.5 9H4.5L3 4z"/>
          </svg>
          Vehicles
        </a>

        <a href="products.php" class="nav-item <?php echo ($currentPage === 'products.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
          </svg>
          Products
        </a>

        <a href="deliveries.php" class="nav-item <?php echo ($currentPage === 'deliveries.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3z"/>
          </svg>
          Deliveries
        </a>

        <a href="tracking.php" class="nav-item <?php echo ($currentPage === 'tracking.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000-2H3a1 1 0 000 2h1a1 1 0 010 2v10a2 2 0 002 2h8a2 2 0 002-2V5a1 1 0 110-2h-1a1 1 0 000 2h2a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
          </svg>
          Tracking
        </a>

        <div class="nav-label" style="margin-top:16px;">Orders</div>

        <a href="assignments.php" class="nav-item <?php echo ($currentPage === 'assignments.php') ? 'active' : ''; ?>">
          <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
          </svg>
          Assignments
        </a>
      <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
      <a href="<?php echo htmlspecialchars($logoutHref); ?>" class="user-chip" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius); cursor: pointer; transition: background 0.18s;" onmouseover="this.style.background='rgba(255,247,227,0.04)'" onmouseout="this.style.background=''">
        <div class="avatar"><?php echo htmlspecialchars($avatarInitials); ?></div>
        <div class="user-info">
          <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
          <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
        </div>
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="color:var(--text-muted); margin-left: auto;">
          <path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 5.293a1 1 0 010 1.414L9.414 10l1.293 1.293a1 1 0 01-1.414 1.414l-2-2a1 1 0 010-1.414l2-2a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
      </a>
    </div>
  </aside>