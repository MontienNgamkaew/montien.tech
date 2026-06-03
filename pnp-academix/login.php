<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Handle logout
if (isset($_GET['logout'])) {
    logout_user();
    // After logout, redirect to Portal
    redirect_to(get_portal_url());
}

// If already logged in, go to dashboard
if (is_logged_in()) {
    if (current_user_role() === 'admin') {
        redirect_to('admin/overview.php');
    } else {
        redirect_to('teacher/dashboard.php');
    }
}

// Not logged in — redirect to Portal for SSO authentication
redirect_to(get_portal_url());
