<?php

final class PageController
{
    public function home(): void
    {
        render('home', [
            'title' => 'หน้าแรก',
        ]);
    }

    public function placeholder(string $title): void
    {
        view(
            $title,
            '<div class="p-4 bg-white border rounded-2 shadow-sm">
                <h1 class="h4 mb-2">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
                <p class="mb-0">หน้านี้จะถูกพัฒนาในขั้นตอนถัดไป</p>
            </div>'
        );
    }
}
