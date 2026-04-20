<?php

namespace Wizdam\App\Jobs;

use Wizdam\App\Services\WizdamApiClient;

/**
 * Job untuk analisis SDGs dan Wizdam Impact Score secara sequensial
 * Mencegah timeout dengan membagi proses menjadi beberapa tahap
 */
class ImpactAnalysisJob extends JobAbstract
{
    private WizdamApiClient $apiClient;
    
    public function __construct(string $jobId, array $data = [], ?WizdamApiClient $apiClient = null)
    {
        parent::__construct($jobId, $data);
        $this->apiClient = $apiClient ?? new WizdamApiClient($_ENV['WIZDAM_API_URL'] ?? 'https://api.sangia.org');
    }
    
    public function handle(): mixed
    {
        $entityType = $this->data['entity_type'] ?? 'researcher'; // researcher, institution, article
        $entityId = $this->data['entity_id'] ?? null;
        
        if (!$entityId) {
            throw new \InvalidArgumentException("entity_id diperlukan");
        }
        
        $results = [];
        $stages = [
            'fetch_data' => 'Mengambil data dasar...',
            'analyze_sdgs' => 'Analisis SDGs...',
            'fetch_citations' => 'Mengambil data kutipan...',
            'analyze_impact' => 'Menghitung Wizdam Impact Score...',
            'fetch_altmetrics' => 'Mengambil altmetrics (news, policy, social media)...',
            'finalize' => 'Finalisasi hasil...'
        ];
        
        $totalStages = count($stages);
        $completedStage = 0;
        
        // Stage 1: Fetch Data Dasar
        $this->updateProgress(5, $stages['fetch_data']);
        $results['basic_data'] = $this->fetchBasicData($entityType, $entityId);
        $completedStage++;
        
        // Stage 2: Analisis SDGs
        $this->updateProgress(20, $stages['analyze_sdgs']);
        $results['sdgs_analysis'] = $this->analyzeSDGs($results['basic_data']);
        $completedStage++;
        
        // Stage 3: Fetch Citations
        $this->updateProgress(40, $stages['fetch_citations']);
        $results['citations'] = $this->fetchCitations($entityType, $entityId);
        $completedStage++;
        
        // Stage 4: Analisis Impact Score
        $this->updateProgress(60, $stages['analyze_impact']);
        $results['impact_score'] = $this->calculateImpactScore($results);
        $completedStage++;
        
        // Stage 5: Fetch Altmetrics
        $this->updateProgress(80, $stages['fetch_altmetrics']);
        $results['altmetrics'] = $this->fetchAltmetrics($entityType, $entityId);
        $completedStage++;
        
        // Stage 6: Finalisasi
        $this->updateProgress(95, $stages['finalize']);
        $results['final'] = $this->finalizeAnalysis($results);
        $completedStage++;
        
        $this->updateProgress(100, "Analysis completed");
        
        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'results' => $results,
            'stages_completed' => $completedStage,
            'total_stages' => $totalStages
        ];
    }
    
    private function fetchBasicData(string $entityType, string $entityId): array
    {
        $response = $this->apiClient->callMonolithic('/analysis/fetch-basic', [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
        
        return $response['data'] ?? [];
    }
    
    private function analyzeSDGs(array $basicData): array
    {
        $response = $this->apiClient->callMonolithic('/analysis/sdgs', [
            'data' => $basicData
        ]);
        
        return $response['data'] ?? [
            'sdgs_goals' => [],
            'primary_goals' => [],
            'relevance_score' => 0
        ];
    }
    
    private function fetchCitations(string $entityType, string $entityId): array
    {
        $response = $this->apiClient->callMonolithic('/analysis/citations', [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
        
        return $response['data'] ?? [
            'total_citations' => 0,
            'sources' => []
        ];
    }
    
    private function calculateImpactScore(array $results): array
    {
        $response = $this->apiClient->callMonolithic('/analysis/impact-score', [
            'sdgs_data' => $results['sdgs_analysis'],
            'citations_data' => $results['citations']
        ]);
        
        return $response['data'] ?? [
            'wizdam_score' => 0,
            'percentile' => 0,
            'category' => 'unknown'
        ];
    }
    
    private function fetchAltmetrics(string $entityType, string $entityId): array
    {
        $response = $this->apiClient->callMonolithic('/analysis/altmetrics', [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
        
        return $response['data'] ?? [
            'news_mentions' => 0,
            'policy_mentions' => 0,
            'social_media_mentions' => 0
        ];
    }
    
    private function finalizeAnalysis(array $results): array
    {
        $response = $this->apiClient->callMonolithic('/analysis/finalize', [
            'all_results' => $results
        ]);
        
        return $response['data'] ?? [];
    }
}
