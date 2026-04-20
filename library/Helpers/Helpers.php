<?php

namespace Wizdam\Library\Helpers;

/**
 * Helper functions untuk berbagai kebutuhan umum
 */
class Helpers
{
    /**
     * Generate UUID v4
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Format angka dengan separator ribuan
     */
    public static function formatNumber(int|float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals, ',', '.');
    }
    
    /**
     * Truncate string dengan ellipsis
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Slugify string untuk URL
     */
    public static function slugify(string $text): string
    {
        $text = preg_replace('~[^\p{Latin}\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        
        return empty($text) ? 'n-a' : $text;
    }
    
    /**
     * Human readable time ago
     */
    public static function timeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        $periods = [
            'year' => $diff->y,
            'month' => $diff->m,
            'day' => $diff->d,
            'hour' => $diff->h,
            'minute' => $diff->i,
            'second' => $diff->s
        ];
        
        foreach ($periods as $unit => $value) {
            if ($value > 0) {
                return "{$value} {$unit}" . ($value > 1 ? 's' : '') . ' ago';
            }
        }
        
        return 'just now';
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Generate random string
     */
    public static function randomString(int $length = 32, string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'): string
    {
        $result = '';
        $max = strlen($charset) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $max)];
        }
        
        return $result;
    }
    
    /**
     * Convert bytes to human readable size
     */
    public static function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
