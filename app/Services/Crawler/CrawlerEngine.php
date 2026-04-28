<?php

declare(strict_types=1);

namespace Wizdam\Services\Crawler;

use Wizdam\Services\Harvesting\OaiPmhHarvester;
use Wizdam\Services\SangiaApi\RawDataPersister;
use Wizdam\Services\SangiaApi\SangiaGateway;

/**
 * CrawlerEngine — pipeline orkestrasi WizdamCrawler.
 *
 * Menggabungkan WebCrawler (web scraping) + OaiPmhHarvester (OAI-PMH API resmi)
 * + SangiaGateway (analisis) + RawDataPersister (penyimpanan) ke dalam satu
 * antarmuka harvesting yang konsisten.
 *
 * Gunakan metode tingkat-tinggi ini daripada memanggil komponen secara langsung.
 */
class CrawlerEngine
{
    private WebCrawler      $webCrawler;
    private OaiPmhHarvester $oaiHarvester;
    private SangiaGateway   $sangiaGateway;

    /** Statistik run terakhir */
    private array $stats = [
        'harvested'  => 0,
        'persisted'  => 0,
        'errors'     => 0,
        'sources'    => [],
    ];

    public function __construct()
    {
        $this->webCrawler    = new WebCrawler();
        $this->oaiHarvester  = new OaiPmhHarvester();
        $this->sangiaGateway = new SangiaGateway();
    }

    // ─── Researcher Harvesting ────────────────────────────────────────────────

    /**
     * Harvest profil peneliti lengkap dari semua sumber yang tersedia.
     *
     * @param string $orcid  ORCID iD peneliti (format 0000-0000-0000-0000)
     * @param array  $opts   Opsi: sources, persist, enrich_sangia
     * @return array         Profil gabungan dari semua sumber
     */
    public function harvestResearcher(string $orcid, array $opts = []): array
    {
        $sources       = $opts['sources'] ?? ['orcid', 'scholar', 'citations'];
        $persist       = $opts['persist'] ?? true;
        $enrichSangia  = $opts['enrich_sangia'] ?? false;

        $profile = [];
        $this->stats['sources'] = [];

        foreach ($sources as $source) {
            try {
                $data = match ($source) {
                    'orcid'    => $this->fromOrcidApi($orcid),
                    'scholar'  => $this->fromGoogleScholar($orcid),
                    'citations'=> $this->fromCitationNetworks($orcid),
                    default    => [],
                };

                if ($data) {
                    $profile[$source]               = $data;
                    $this->stats['sources'][$source] = count($data);
                    $this->stats['harvested']++;
                }
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->stats['sources'][$source] = 'error: ' . $e->getMessage();
                error_log("[CrawlerEngine] $source error for $orcid: " . $e->getMessage());
            }
        }

        // Note: per-source persistence is handled inside each helper method.
        // fromCitationNetworks → harvestCitations → saveCitation (per DOI).
        // Calling saveAuthorProfile here would write wrong-keyed data (orcid/scholar/citations)
        // into columns that expect orcid_person/orcid_works/scopus, corrupting cached profiles.

        if ($enrichSangia && !empty($profile['orcid'])) {
            try {
                $sangiaData = $this->sangiaGateway->post('/api/v1/enrich/researcher', [
                    'orcid'   => $orcid,
                    'profile' => $profile,
                ]);
                $profile['sangia_enrichment'] = $sangiaData;
            } catch (\Throwable $e) {
                error_log("[CrawlerEngine] Sangia enrich error for $orcid: " . $e->getMessage());
            }
        }

        return $profile;
    }

    // ─── OAI-PMH Journal Harvesting ──────────────────────────────────────────

    /**
     * Harvest artikel dari endpoint OAI-PMH resmi.
     *
     * Target yang didukung secara built-in: garuda, zenodo, arxiv, doaj, pmc.
     * Bisa juga menerima URL endpoint arbitrer.
     *
     * @param string $target    Nama built-in ('garuda', 'zenodo', …) atau URL lengkap
     * @param array  $opts      Opsi: from, until, set, metadataPrefix, maxRecords, persist
     * @return array            Artikel yang sudah dinormalisasi ke skema Wizdam
     */
    public function harvestJournal(string $target, array $opts = []): array
    {
        $from           = $opts['from']           ?? '';
        $until          = $opts['until']          ?? '';
        $set            = $opts['set']            ?? '';
        $metadataPrefix = $opts['metadataPrefix'] ?? 'oai_dc';
        $maxRecords     = $opts['maxRecords']      ?? 500;
        $persist        = $opts['persist']         ?? true;

        $collected = [];

        // Persistence handled here so OAI harvester's own $persist is always false,
        // preventing double-writes when $onBatch and $persist=true are both active.
        $onBatch = function (array $batch) use (&$collected, $persist, $maxRecords): bool {
            foreach ($batch as $article) {
                $collected[] = $article;

                if ($persist && !empty($article['doi'])) {
                    RawDataPersister::saveCitation($article['doi'], $article);
                    $this->stats['persisted']++;
                }

                $this->stats['harvested']++;
            }

            return count($collected) < $maxRecords;
        };

        $knownBuiltins = ['garuda', 'zenodo', 'arxiv', 'doaj', 'pmc', 'lipi', 'crossref'];

        if (in_array(strtolower($target), $knownBuiltins, true)) {
            $method = 'harvest' . ucfirst(strtolower($target));
            if (method_exists($this->oaiHarvester, $method)) {
                // Bug fix: pass ($from, $until, $set, persist=false, $onBatch) — 5 args matching updated signatures
                $this->oaiHarvester->$method($from, $until, $set, false, $onBatch);
            } else {
                $this->oaiHarvester->harvestAuto($target, $from, $until, $set, false, $onBatch);
            }
        } elseif (filter_var($target, FILTER_VALIDATE_URL)) {
            // Bug fix: correct parameter order — harvest($url, $prefix, $set, $from, $until, ...)
            $this->oaiHarvester->harvest(
                $target,
                $metadataPrefix,
                $set,
                $from,
                $until,
                false,
                $onBatch
            );
        }

        $this->stats['sources'][$target] = count($collected);

        return $collected;
    }

    /**
     * Harvest dari semua endpoint OAI-PMH Indonesia yang dikenal.
     *
     * @param array $opts  Opsi: from, until, maxPerSource, persist
     * @return array       Hasil per sumber
     */
    public function harvestAllIndonesianSources(array $opts = []): array
    {
        $sources = ['garuda', 'lipi'];
        $results = [];

        foreach ($sources as $src) {
            $results[$src] = $this->harvestJournal($src, array_merge($opts, [
                'maxRecords' => $opts['maxPerSource'] ?? 200,
            ]));
        }

        return $results;
    }

    // ─── Web Scraping ─────────────────────────────────────────────────────────

    /**
     * Scrape profil jurnal dari web (Scimago, SINTA, dsb.)
     */
    public function harvestJournalProfile(string $issn): array
    {
        $result = [];

        try {
            $impact  = $this->webCrawler->crawlImpactFactorDatabases($issn);
            $sinta   = $this->webCrawler->crawlResearchDirectories($issn, 'sinta');
            $result  = array_merge($impact, $sinta);
            $this->stats['harvested']++;
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            error_log("[CrawlerEngine] Journal profile error for $issn: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Scrape citation network untuk DOI tertentu (OpenCitations + Crossref).
     */
    public function harvestCitations(string $doi): array
    {
        try {
            $data = $this->webCrawler->crawlCitationNetworks($doi);
            RawDataPersister::saveCitation($doi, $data);
            $this->stats['harvested']++;
            $this->stats['persisted']++;
            return $data;
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            error_log("[CrawlerEngine] Citation error for $doi: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Scrape artikel terkait dari Semantic Scholar.
     */
    public function harvestRelatedPapers(string $doi): array
    {
        try {
            $data = $this->webCrawler->crawlRelatedPapers($doi);
            $this->stats['harvested']++;
            return $data;
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            return [];
        }
    }

    // ─── Bulk / Scheduled ─────────────────────────────────────────────────────

    /**
     * Jalankan full harvesting cycle: OAI-PMH + citation networks untuk semua
     * DOI yang ditemukan.
     *
     * @param array $opts  Opsi: from, until, maxRecords, sources, persist
     * @return array       Ringkasan statistik
     */
    public function runFullCycle(array $opts = []): array
    {
        $this->resetStats();

        // Phase 1: OAI-PMH harvest
        $sources  = $opts['sources'] ?? ['garuda', 'zenodo'];
        $articles = [];

        foreach ($sources as $src) {
            $batch    = $this->harvestJournal($src, $opts);
            $articles = array_merge($articles, $batch);
        }

        // Phase 2: Citation network untuk setiap DOI yang ditemukan
        $dois = array_unique(array_filter(array_column($articles, 'doi')));
        foreach (array_slice($dois, 0, $opts['maxCitationLookups'] ?? 50) as $doi) {
            $this->harvestCitations($doi);
        }

        return $this->getStats();
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    private function fromOrcidApi(string $orcid): array
    {
        $cached = RawDataPersister::loadAuthorProfile($orcid);
        return $cached['orcid_person'] ?? [];
    }

    private function fromGoogleScholar(string $orcid): array
    {
        $cached = RawDataPersister::loadAuthorProfile($orcid);
        $name   = $cached['supplied_person']['name'] ?? $orcid;
        return $this->webCrawler->crawlGoogleScholar($name);
    }

    private function fromCitationNetworks(string $orcid): array
    {
        $cached = RawDataPersister::loadAuthorProfile($orcid);
        $works  = $cached['supplied_works'] ?? [];
        $result = [];

        foreach (array_slice($works, 0, 10) as $work) {
            if (!empty($work['doi'])) {
                $result[] = $this->harvestCitations($work['doi']);
            }
        }

        return $result;
    }

    private function resetStats(): void
    {
        $this->stats = ['harvested' => 0, 'persisted' => 0, 'errors' => 0, 'sources' => []];
    }

    public function getStats(): array
    {
        return $this->stats;
    }
}
