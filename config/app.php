<?php

return [
    'name'       => 'Wizdam AI-Sikola',
    'version'    => '1.0.0',
    'base_url'   => $_ENV['APP_URL'] ?? 'http://localhost',
    'base_path'  => $_ENV['APP_BASE_PATH'] ?? '',
    'debug'      => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'twig_cache' => filter_var($_ENV['TWIG_CACHE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'locale'     => 'id_ID',
    'timezone'   => 'Asia/Makassar',
];
