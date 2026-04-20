<?php

namespace Wizdam\App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * API Client untuk berkomunikasi dengan Wizdam APIs di api.sangia.org
 * Mendukung mode monolitik dan sequensial (async job)
 */
class WizdamApiClient
{
    private Client $client;
    private string $baseUrl;
    private ?string $apiKey = null;
    
    public function __construct(string $baseUrl, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30.0,
            'connect_timeout' => 10.0,
            'http_errors' => false,
        ]);
    }
    
    /**
     * Set API key untuk request selanjutnya
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Call API dengan mode monolitik (langsung dapat response)
     * @throws RequestException
     */
    public function callMonolithic(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $options = [
            'json' => $data,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ];
        
        if ($this->apiKey) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        
        try {
            $response = match($method) {
                'GET' => $this->client->get($endpoint, $options),
                'POST' => $this->client->post($endpoint, $options),
                'PUT' => $this->client->put($endpoint, $options),
                'DELETE' => $this->client->delete($endpoint, $options),
                default => throw new \InvalidArgumentException("Method {$method} tidak didukung")
            };
            
            return $this->parseResponse($response);
            
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return $this->parseResponse($e->getResponse());
            }
            throw $e;
        }
    }
    
    /**
     * Call API dengan mode sequensial (async job)
     * Returns job_id untuk polling status
     */
    public function callSequencial(string $endpoint, array $data = []): array
    {
        // Request dimulai sebagai async job
        $response = $this->callMonolithic($endpoint, array_merge($data, ['async' => true]));
        
        if (!isset($response['job_id'])) {
            throw new \RuntimeException("API tidak mengembalikan job_id untuk mode sequensial");
        }
        
        return [
            'job_id' => $response['job_id'],
            'status' => $response['status'] ?? 'pending',
            'message' => $response['message'] ?? 'Job queued for processing'
        ];
    }
    
    /**
     * Polling status dari async job
     */
    public function pollJobStatus(string $jobId): array
    {
        return $this->callMonolithic("/jobs/{$jobId}/status", [], 'GET');
    }
    
    /**
     * Polling sampai job selesai atau timeout
     * @param int $maxAttempts Maksimal percobaan polling
     * @param int $interval Interval antar polling dalam detik
     */
    public function waitForJob(string $jobId, int $maxAttempts = 60, int $interval = 2): array
    {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $status = $this->pollJobStatus($jobId);
            
            if (in_array($status['status'], ['completed', 'success', 'failed', 'error'])) {
                return $status;
            }
            
            sleep($interval);
            $attempts++;
        }
        
        throw new \RuntimeException("Job timeout setelah {$maxAttempts} attempts");
    }
    
    /**
     * Smart call: otomatis pilih mode berdasarkan ukuran data
     * Data besar -> sequensial, Data kecil -> monolitik
     */
    public function smartCall(string $endpoint, array $data = []): array
    {
        // Threshold: jika data > 1MB atau item > 100, gunakan sequensial
        $dataSize = strlen(json_encode($data));
        $itemCount = count($data, COUNT_RECURSIVE);
        
        if ($dataSize > 1048576 || $itemCount > 100) {
            return $this->callSequencial($endpoint, $data);
        }
        
        return $this->callMonolithic($endpoint, $data);
    }
    
    /**
     * Parse response JSON
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Download file dari API
     */
    public function downloadFile(string $endpoint, string $destination): bool
    {
        $options = [
            'sink' => $destination,
            'headers' => []
        ];
        
        if ($this->apiKey) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        
        $response = $this->client->get($endpoint, $options);
        
        return $response->getStatusCode() === 200;
    }
}
