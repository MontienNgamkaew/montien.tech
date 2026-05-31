<?php
/* -------------------------------------------------------------
 * DEPENDENCY-FREE JWT (JSON WEB TOKEN) ENCODER & DECODER IN PHP
 * Supports HMAC-SHA256 (HS256) signature
 * ------------------------------------------------------------- */

class JWT {
    
    /**
     * แปลงข้อความเป็น Base64Url
     */
    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * แปลง Base64Url กลับเป็นข้อความเดิม
     */
    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    /**
     * เข้ารหัสและสร้าง JWT Token
     */
    public static function encode($payload, $secret) {
        // ส่วนที่ 1: Header
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $base64UrlHeader = self::base64UrlEncode($header);

        // ส่วนที่ 2: Payload
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        // ส่วนที่ 3: Signature
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        // รวมร่างโทเค็น
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * ถอดรหัสและตรวจสอบความถูกต้องของ JWT Token
     */
    public static function decode($token, $secret) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false; // โทเค็นมีรูปแบบไม่ถูกต้อง
        }

        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

        // ถอดรหัสข้อมูล
        $header = json_decode(self::base64UrlDecode($base64UrlHeader), true);
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);

        if (!$header || !$payload) {
            return false; // ไม่สามารถดึงข้อมูล JSON ได้
        }

        // ตรวจสอบลายเซ็น (Signature Verification)
        $signature = self::base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);

        if (!hash_equals($signature, $expectedSignature)) {
            return false; // ลายเซ็นไม่ถูกต้องหรือถูกแก้ไขข้อมูล
        }

        // ตรวจสอบวันหมดอายุ (Expiry Claim: exp)
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false; // โทเค็นหมดอายุแล้ว
        }

        return $payload;
    }
}
