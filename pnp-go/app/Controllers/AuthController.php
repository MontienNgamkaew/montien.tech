<?php

final class AuthController
{
    public function loginForm(?string $error = null): void
    {
        if (current_user() !== null) {
            redirect('/dashboard');
        }

        // ไม่มีระบบ login เฉพาะของ pnp-go แล้ว — เข้าใช้งานผ่าน SSO ของพอร์ทัลกลางเท่านั้น
        $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
        header('Location: ' . ($isLocal ? '/pnp-portal/' : '/'));
        exit;
    }

    public function logout(): void
    {
        verify_csrf();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        
        // Single Logout (SLO): redirect to central portal and wipe localStorage token
        $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
        $portalUrl = $isLocal ? '/pnp-portal/?action=logout' : '/?action=logout';
        header('Location: ' . $portalUrl);
        exit;
    }
}
