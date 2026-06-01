<?php

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $basePath = rtrim(config('app')['base_path'], '/');

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . trim($path, '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                call_user_func($route['handler']);
                return;
            }
        }

        view('404 Not Found', '<h1 class="h3">ไม่พบหน้าที่ต้องการ</h1><p>กรุณาตรวจสอบ URL อีกครั้ง</p>', 404);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }
}
