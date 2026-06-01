<?php

final class AuthController
{
    public function loginForm(?string $error = null): void
    {
        if (current_user() !== null) {
            redirect('/dashboard');
        }

        render('auth/login', [
            'title' => 'เข้าสู่ระบบผู้อนุมัติ',
            'error' => $error,
            'username' => $_POST['username'] ?? '',
        ]);
    }

    public function login(): void
    {
        verify_csrf();

        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->loginForm('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $update = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);

        redirect('/dashboard');
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
        redirect('/login');
    }
}
