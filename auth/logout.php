<?php
require_once __DIR__ . '/../includes/auth.php';

securityHeaders();

if (isLoggedIn()) {
    $user = currentUser();
    logSecurityEvent('logout', ['email' => $user['email'] ?? 'unknown']);
}

logoutUser();
header('Location: login.php');
exit;
