<?php

namespace Wizdam\Library\Core;

/**
 * Base class untuk semua library custom Wizdam
 */
abstract class LibraryAbstract
{
    protected array $config = [];
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->boot();
    }
    
    /**
     * Method yang dipanggil saat inisialisasi
     */
    protected function boot(): void
    {
        // Override di child class jika perlu
    }
    
    /**
     * Validasi konfigurasi
     */
    protected function validateConfig(array $required = []): bool
    {
        foreach ($required as $key) {
            if (!isset($this->config[$key])) {
                throw new \InvalidArgumentException("Konfigurasi '{$key}' diperlukan");
            }
        }
        return true;
    }
}
