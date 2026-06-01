<?php

function config(string $name): array
{
    static $items = [];

    if (!isset($items[$name])) {
        $path = dirname(__DIR__) . '/config/' . $name . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("Config file not found: {$name}");
        }

        $items[$name] = require $path;
    }

    return $items[$name];
}

function view(string $title, string $content, int $statusCode = 200): void
{
    http_response_code($statusCode);

    echo '<!doctype html>';
    echo '<html lang="th">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head>';
    echo '<body class="bg-light">';
    echo '<main class="container py-5">';
    echo $content;
    echo '</main>';
    echo '</body>';
    echo '</html>';
}

function render(string $template, array $data = [], int $statusCode = 200): void
{
    $path = dirname(__DIR__) . '/app/Views/' . $template . '.php';

    if (!is_file($path)) {
        throw new RuntimeException("View not found: {$template}");
    }

    http_response_code($statusCode);
    extract($data, EXTR_SKIP);

    require $path;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        view('Invalid request', '<h1 class="h4">คำขอไม่ถูกต้อง</h1><p>กรุณาลองใหม่อีกครั้ง</p>', 419);
        exit;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;

    if ($user !== null && (int) $user['id'] === (int) $_SESSION['user_id']) {
        return $user;
    }

    $statement = Database::connection()->prepare('SELECT * FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch() ?: null;

    return $user;
}

function require_auth(): array
{
    $user = current_user();

    if ($user === null) {
        redirect('/login');
    }

    return $user;
}

function role_label(string $role): string
{
    return [
        'supply_head' => 'หัวหน้างานพัสดุ',
        'deputy_director' => 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร',
        'director' => 'ผู้อำนวยการ',
        'admin' => 'ผู้ดูแลระบบ',
    ][$role] ?? $role;
}

function status_badge_class(string $status): string
{
    return [
        'submitted' => 'status-info',
        'pending_level_1' => 'status-warning',
        'pending_level_2' => 'status-warning',
        'pending_level_3' => 'status-warning',
        'approved' => 'status-success',
        'rejected' => 'status-danger',
        'cancelled' => 'status-muted',
    ][$status] ?? 'status-info';
}

function redirect(string $path): void
{
    $basePath = rtrim(config('app')['base_path'], '/');
    header('Location: ' . $basePath . '/' . ltrim($path, '/'));
    exit;
}
