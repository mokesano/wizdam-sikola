<?php

/**
 * Global helper functions
 * 
 * File ini berisi fungsi-fungsi helper yang dapat digunakan di seluruh aplikasi
 */

use Wizdam\Library\Helpers\Helpers;

if (!function_exists('uuid')) {
    function uuid(): string
    {
        return Helpers::uuid();
    }
}

if (!function_exists('format_number')) {
    function format_number(int|float $number, int $decimals = 0): string
    {
        return Helpers::formatNumber($number, $decimals);
    }
}

if (!function_exists('truncate')) {
    function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        return Helpers::truncate($text, $length, $suffix);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        return Helpers::slugify($text);
    }
}

if (!function_exists('time_ago')) {
    function time_ago(\DateTimeInterface $date): string
    {
        return Helpers::timeAgo($date);
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $input): string
    {
        return Helpers::sanitize($input);
    }
}

if (!function_exists('is_valid_email')) {
    function is_valid_email(string $email): bool
    {
        return Helpers::isValidEmail($email);
    }
}

if (!function_exists('random_string')) {
    function random_string(int $length = 32, string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'): string
    {
        return Helpers::randomString($length, $charset);
    }
}

if (!function_exists('human_file_size')) {
    function human_file_size(int $bytes): string
    {
        return Helpers::humanFileSize($bytes);
    }
}

if (!function_exists('is_ajax')) {
    function is_ajax(): bool
    {
        return Helpers::isAjax();
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        static $configs = [];
        
        if (empty($configs)) {
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $configFiles = glob($basePath . '/config/*.php');
            
            foreach ($configFiles as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $configs[$name] = require $file;
            }
        }
        
        $keys = explode('.', $key);
        $value = $configs;
        
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert boolean strings
        if (strtolower($value) === 'true') {
            return true;
        }
        
        if (strtolower($value) === 'false') {
            return false;
        }
        
        // Convert numeric strings
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        return $value;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $statusCode = 302): \Wizdam\Http\Response
    {
        return \Wizdam\Http\Response::redirect($url, $statusCode);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): \Wizdam\Http\Response
    {
        return \Wizdam\Http\Response::view($template, $data);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $statusCode = 200): \Wizdam\Http\Response
    {
        return \Wizdam\Http\Response::json($data, $statusCode);
    }
}

if (!function_exists('error_response')) {
    function error_response(string $message, int $statusCode = 400): \Wizdam\Http\Response
    {
        return \Wizdam\Http\Response::error($message, $statusCode);
    }
}

if (!function_exists('auth')) {
    function auth()
    {
        return \Wizdam\Core\App::getInstance()->getAuthService();
    }
}

if (!function_exists('db')) {
    function db()
    {
        return \Wizdam\Core\App::getInstance()->getDatabase();
    }
}

if (!function_exists('queue')) {
    function queue()
    {
        return \Wizdam\Core\App::getInstance()->getQueueManager();
    }
}

if (!function_exists('api_client')) {
    function api_client()
    {
        return \Wizdam\Core\App::getInstance()->getApiClient();
    }
}
