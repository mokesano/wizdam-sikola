<?php

namespace Wizdam\App\Jobs;

use Wizdam\App\Services\WizdamApiClient;

/**
 * Job untuk melakukan crawling data peneliti dari berbagai sumber
 * (Scopus, ORCID, Sinta, Google Scholar, dll)
 */
class ResearcherCrawlerJob extends JobAbstract
{
    private WizdamApiClient $apiClient;
    
    public function __construct(string $jobId, array $data = [], ?WizdamApiClient $apiClient = null)
    {
        parent::__construct($jobId, $data);
        $this->apiClient = $apiClient ?? new WizdamApiClient($_ENV['WIZDAM_API_URL'] ?? 'https://api.sangia.org');
    }
    
    public function handle(): mixed
    {
        $researcherId = $this->data['researcher_id'] ?? null;
        $sources = $this->data['sources'] ?? ['orcid', 'scopus', 'sinta'];
        
        if (!$researcherId) {
            throw new \InvalidArgumentException("researcher_id diperlukan");
        }
        
        $results = [];
        $totalSources = count($sources);
        $completedSources = 0;
        
        foreach ($sources as $index => $source) {
            $this->updateProgress(
                (int)(($index / $totalSources) * 100),
                "Crawling {$source}..."
            );
            
            try {
                $result = $this->crawlSource($source, $researcherId);
                $results[$source] = [
                    'success' => true,
                    'data' => $result
                ];
            } catch (\Exception $e) {
                $results[$source] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            $completedSources++;
        }
        
        $this->updateProgress(100, "Crawling completed");
        
        return [
            'researcher_id' => $researcherId,
            'sources' => $results,
            'summary' => [
                'total_sources' => $totalSources,
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success']))
            ]
        ];
    }
    
    /**
     * Crawl dari sumber tertentu
     */
    private function crawlSource(string $source, string $researcherId): array
    {
        return match($source) {
            'orcid' => $this->crawlOrcid($researcherId),
            'scopus' => $this->crawlScopus($researcherId),
            'sinta' => $this->crawlSinta($researcherId),
            'google_scholar' => $this->crawlGoogleScholar($researcherId),
            default => throw new \InvalidArgumentException("Sumber {$source} tidak didukung")
        };
    }
    
    private function crawlOrcid(string $researcherId): array
    {
        // Call API ORCID melalui Wizdam API
        $response = $this->apiClient->callMonolithic('/crawler/orcid', [
            'researcher_id' => $researcherId
        ]);
        
        return $response['data'] ?? [];
    }
    
    private function crawlScopus(string $researcherId): array
    {
        $response = $this->apiClient->callMonolithic('/crawler/scopus', [
            'researcher_id' => $researcherId
        ]);
        
        return $response['data'] ?? [];
    }
    
    private function crawlSinta(string $researcherId): array
    {
        $response = $this->apiClient->callMonolithic('/crawler/sinta', [
            'researcher_id' => $researcherId
        ]);
        
        return $response['data'] ?? [];
    }
    
    private function crawlGoogleScholar(string $researcherId): array
    {
        $response = $this->apiClient->callMonolithic('/crawler/google-scholar', [
            'researcher_id' => $researcherId
        ]);
        
        return $response['data'] ?? [];
    }
}
