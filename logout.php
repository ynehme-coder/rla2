<?php
session_start();

$returnTo = isset($_GET['return']) ? trim((string)$_GET['return']) : '';
$redirectTarget = 'login.php?logout=1';

if ($returnTo !== '' && strpos($returnTo, '://') === false && substr($returnTo, 0, 2) !== '//') {
    $redirectTarget = $returnTo;
}

// Destroy all session data
$_SESSION = array();

// If using session cookies, delete the cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect back to the originating page when possible
header('Location: ' . $redirectTarget);
exit();
?>
