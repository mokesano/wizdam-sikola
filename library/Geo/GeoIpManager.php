<?php

namespace Wizdam\Library\Geo;

/**
 * GeoIP Manager untuk pemetaan lokasi peneliti dan institusi
 * Menggunakan file GeoIP.dat untuk lookup IP ke lokasi
 */
class GeoIpManager
{
    private string $datFilePath;
    private ?resource $handle = null;
    
    public function __construct(string $datFilePath)
    {
        if (!file_exists($datFilePath)) {
            throw new \RuntimeException("File GeoIP.dat tidak ditemukan: {$datFilePath}");
        }
        
        $this->datFilePath = $datFilePath;
    }
    
    /**
     * Lookup lokasi berdasarkan IP address
     * @return array ['country', 'region', 'city', 'latitude', 'longitude']
     */
    public function lookup(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("IP address tidak valid: {$ip}");
        }
        
        // Implementasi sederhana - bisa diganti dengan library MaxMind atau IP2Location
        // Untuk sekarang return default location Indonesia
        return [
            'country' => 'Indonesia',
            'country_code' => 'ID',
            'region' => '',
            'city' => '',
            'latitude' => -0.7893,
            'longitude' => 113.9213,
            'timezone' => 'Asia/Jakarta'
        ];
    }
    
    /**
     * Dapatkan lokasi dari request saat ini
     */
    public function getCurrentLocation(): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Handle jika behind proxy/load balancer
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return $this->lookup($ip);
    }
    
    /**
     * Batch lookup untuk multiple IPs
     */
    public function batchLookup(array $ips): array
    {
        $results = [];
        foreach ($ips as $ip) {
            try {
                $results[$ip] = $this->lookup($ip);
            } catch (\Exception $e) {
                $results[$ip] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }
    
    /**
     * Format data untuk visualisasi peta (Leaflet/Mapbox)
     */
    public function formatForMap(array $locations): array
    {
        $markers = [];
        
        foreach ($locations as $location) {
            if (isset($location['latitude'], $location['longitude'])) {
                $markers[] = [
                    'lat' => $location['latitude'],
                    'lng' => $location['longitude'],
                    'title' => $location['city'] ?? $location['region'] ?? 'Unknown',
                    'country' => $location['country'] ?? '',
                    'popup' => sprintf(
                        "<strong>%s</strong><br>%s, %s",
                        $location['city'] ?? 'Unknown',
                        $location['region'] ?? '',
                        $location['country'] ?? ''
                    )
                ];
            }
        }
        
        return $markers;
    }
    
    public function __destruct()
    {
        if ($this->handle && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
