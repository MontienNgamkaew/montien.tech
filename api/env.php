<?php
/* -------------------------------------------------------------
 * LIGHTWEIGHT .ENV LOADER (DEPENDENCY-FREE)
 * -------------------------------------------------------------
 * อ่านค่าคอนฟิกลับ (รหัสผ่าน, คีย์ลับ) จากไฟล์ .env ที่ราก repo
 * โดยไม่ commit ขึ้น git  ใช้ฟังก์ชัน env('KEY', $default) เพื่อเรียกใช้
 *
 * ลำดับความสำคัญของค่า:
 *   1. ตัวแปรสภาพแวดล้อมจริง (getenv / $_ENV / $_SERVER) — เหมาะกับ Hostinger
 *   2. ไฟล์ .env ที่ราก repo (pnp-portal/.env)
 *   3. ค่าเริ่มต้น ($default) ที่ส่งเข้ามา
 * ------------------------------------------------------------- */

if (!function_exists('pnp_load_env')) {

    /**
     * โหลดไฟล์ .env เข้าหน่วยความจำเพียงครั้งเดียว (static cache)
     *
     * @return array<string,string>
     */
    function pnp_load_env(): array
    {
        static $loaded = null;
        if ($loaded !== null) {
            return $loaded;
        }

        $loaded = [];

        // .env อยู่ที่ราก repo: pnp-portal/.env  (env.php อยู่ใน pnp-portal/api/)
        $envPath = dirname(__DIR__) . '/.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return $loaded;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $loaded;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // ข้ามคอมเมนต์และบรรทัดว่าง
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            // ต้องมีเครื่องหมาย =
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // ตัดเครื่องหมายคำพูดครอบค่า (ถ้ามี)
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key !== '') {
                $loaded[$key] = $value;
            }
        }

        return $loaded;
    }
}

if (!function_exists('upload_base_path')) {
    /**
     * คืน absolute path ของโฟลเดอร์ uploads สำหรับแต่ละ app
     * ชี้ไปนอก public_html เพื่อให้ไฟล์ไม่หายเมื่อ Hostinger deploy ใหม่
     *
     * @param string $app  ชื่อแอป เช่น 'pnp-academix', 'pnpman'
     */
    function upload_base_path(string $app): string
    {
        $base = env('UPLOAD_BASE_PATH', dirname(__DIR__) . '/pnp_uploads');
        return rtrim($base, '/\\') . '/' . $app;
    }
}

if (!function_exists('env')) {
    /**
     * อ่านค่าคอนฟิกจากสภาพแวดล้อมหรือไฟล์ .env
     *
     * @param string $key     ชื่อคีย์
     * @param mixed  $default  ค่าเริ่มต้นหากไม่พบ
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        // 1. ตัวแปรสภาพแวดล้อมจริง (เช่น ที่ตั้งใน hPanel / Apache SetEnv)
        $fromServer = getenv($key);
        if ($fromServer !== false && $fromServer !== '') {
            return $fromServer;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        // 2. ไฟล์ .env
        $envFile = pnp_load_env();
        if (array_key_exists($key, $envFile) && $envFile[$key] !== '') {
            return $envFile[$key];
        }

        // 3. ค่าเริ่มต้น
        return $default;
    }
}
