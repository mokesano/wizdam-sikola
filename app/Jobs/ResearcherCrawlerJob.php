<?php

declare(strict_types=1);

namespace Wizdam\Jobs;

use Wizdam\Services\Crawler\WebCrawler;
use Wizdam\Services\Harvesting\OaiPmhHarvester;
use Wizdam\Services\SangiaApi\RawDataPersister;
use Wizdam\Services\Core\ProfileManager;

/**
 * Job untuk crawling data peneliti dari berbagai sumber.
 * Menggunakan WebCrawler (Scholar, ResearchGate, Crossref, Semantic Scholar)
 * dan ProfileManager (ORCID API).
 */
class ResearcherCrawlerJob extends JobAbstract
{
    private WebCrawler     $crawler;
    private ProfileManager $profileManager;

    public function __construct(string $jobId, array $data = [])
    {
        parent::__construct($jobId, $data);
        $this->crawler        = new WebCrawler();
        $this->profileManager = new ProfileManager();
    }

    public function handle(): mixed
    {
        $orcid   = $this->data['orcid']   ?? null;
        $sources = $this->data['sources'] ?? ['orcid', 'scholar', 'citations'];

        if (!$orcid) {
            throw new \InvalidArgumentException('orcid diperlukan');
        }

        $results       = [];
        $totalSources  = count($sources);

        foreach ($sources as $i => $source) {
            $this->updateProgress((int)(($i / $totalSources) * 90), "Crawling $source...");

            try {
                $results[$source] = match($source) {
                    'orcid'   => $this->crawlOrcid($orcid),
                    'scholar' => $this->crawlScholar($orcid),
                    'citations' => $this->crawlCitations($orcid),
                    default   => [],
                };
            } catch (\Throwable $e) {
                error_log("[ResearcherCrawlerJob] $source error for $orcid: " . $e->getMessage());
                $results[$source] = ['error' => $e->getMessage()];
            }
        }

        $this->updateProgress(100, 'Selesai');

        return [
            'orcid'   => $orcid,
            'sources' => $results,
            'success' => count(array_filter($results, fn($r) => !isset($r['error']))),
            'failed'  => count(array_filter($results, fn($r) => isset($r['error']))),
        ];
    }

    private function crawlOrcid(string $orcid): array
    {
        $profile = $this->profileManager->fetchFromOrcid($orcid);
        if ($profile) {
            RawDataPersister::saveAuthorProfile($orcid, ['orcid_person' => $profile]);
        }
        return $profile;
    }

    private function crawlScholar(string $orcid): array
    {
        // Cari nama peneliti dari cache DB terlebih dahulu
        $cached = RawDataPersister::loadAuthorProfile($orcid);
        $name   = $cached['supplied_person']['name'] ?? $orcid;
        return $this->crawler->crawlGoogleScholar($name);
    }

    private function crawlCitations(string $orcid): array
    {
        $cached = RawDataPersister::loadAuthorProfile($orcid);
        $works  = $cached['supplied_works'] ?? [];
        $result = [];

        foreach (array_slice($works, 0, 10) as $work) {
            $doi = $work['doi'] ?? null;
            if ($doi) {
                $cit = $this->crawler->crawlCitationNetworks($doi);
                RawDataPersister::saveCitation($doi, $cit);
                $result[] = $cit;
            }
        }

        return $result;
    }
}
