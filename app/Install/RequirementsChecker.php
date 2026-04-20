<?php

namespace App\Install;

use PDO;
use Exception;

class RequirementsChecker
{
    private array $requirements = [
        'php_version' => '7.4',
        'extensions' => ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring', 'openssl'],
        'writable_dirs' => ['storage', 'storage/logs', 'storage/cache']
    ];

    public function check(): array
    {
        $results = [
            'passed' => true,
            'php_version' => false,
            'extensions' => [],
            'permissions' => []
        ];

        // Cek Versi PHP
        $results['php_version'] = version_compare(PHP_VERSION, $this->requirements['php_version'], '>=');
        if (!$results['php_version']) {
            $results['passed'] = false;
        }

        // Cek Ekstensi
        foreach ($this->requirements['extensions'] as $ext) {
            $loaded = extension_loaded($ext);
            $results['extensions'][$ext] = $loaded;
            if (!$loaded) {
                $results['passed'] = false;
            }
        }

        // Cek Izin Folder
        foreach ($this->requirements['writable_dirs'] as $dir) {
            $path = dirname(__DIR__, 2) . '/' . $dir;
            $writable = is_writable($path);
            $results['permissions'][$dir] = $writable;
            if (!$writable) {
                $results['passed'] = false;
            }
        }

        return $results;
    }
}
