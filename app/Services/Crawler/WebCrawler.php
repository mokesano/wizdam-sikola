<?php

declare(strict_types=1);

namespace Wizdam\Services\Crawler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * WizdamCrawler — Smart Crawling Engine
 *
 * Mengambil data penelitian yang tidak tersedia melalui API resmi:
 *   - Google Scholar (nama penulis)
 *   - ResearchGate (profil penulis)
 *   - Website jurnal (ISSN/nama jurnal)
 *   - Database impact factor
 *   - Profil institusi penelitian
 *   - Jaringan sitasi (DOI)
 *   - Makalah terkait (kata kunci)
 *
 * Prinsip crawling yang bertanggung jawab (respectful crawling):
 *   - Rate limiting: jeda antar request (default 2 detik)
 *   - User agent rotasi untuk menghindari pemblokiran
 *   - Deteksi CAPTCHA dan penghentian otomatis
 *   - Mematuhi robots.txt
 *   - Cache hasil untuk mengurangi request ulang
 */
class WebCrawler
{
    private Client $http;
    private array  $userAgents;
    private int    $currentUaIndex = 0;

    /** Cache in-memory untuk sesi crawl saat ini. */
    private array $cache = [];

    private const DEFAULT_DELAY_S  = 2;
    private const MAX_RETRIES      = 3;
    private const CONNECT_TIMEOUT  = 10;
    private const REQUEST_TIMEOUT  = 30;

    public function __construct()
    {
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
        ];

        $this->http = new Client([
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout'         => self::REQUEST_TIMEOUT,
            'http_errors'     => false,
            'verify'          => true,
            'headers'         => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Cache-Control'   => 'no-cache',
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scholar Profile Crawling
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Crawl profil Google Scholar berdasarkan nama penulis.
     *
     * @return array{
     *   name: string,
     *   affiliation: string|null,
     *   research_interests: array,
     *   citations: int|null,
     *   h_index: int|null,
     *   i10_index: int|null,
     *   articles: array,
     *   scholar_id: string|null
     * }
     */
    public function crawlGoogleScholar(string $authorName): array
    {
        $cacheKey = 'scholar:' . md5($authorName);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $url  = 'https://scholar.google.com/scholar?q=' . urlencode($authorName) . '&hl=id&as_sdt=0,5';
        $html = $this->respectfulRequest($url);

        if (!$html) {
            return $this->emptyScholarResult();
        }

        if ($this->detectCaptcha($html)) {
            error_log("[WizdamCrawler] Google Scholar CAPTCHA terdeteksi untuk: $authorName");
            return $this->emptyScholarResult();
        }

        $result = $this->parseScholarSearchResults($html, $authorName);
        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Crawl profil peneliti di ResearchGate.
     *
     * @return array{
     *   name: string|null,
     *   institution: string|null,
     *   publications: int|null,
     *   reads: int|null,
     *   citations: int|null,
     *   rg_score: float|null,
     *   skills: array,
     *   profile_url: string|null
     * }
     */
    public function crawlResearchGate(string $authorProfile): array
    {
        $cacheKey = 'rg:' . md5($authorProfile);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // authorProfile bisa berupa URL lengkap atau slug nama (e.g., "John-Doe-3")
        $url = str_starts_with($authorProfile, 'http')
            ? $authorProfile
            : 'https://www.researchgate.net/profile/' . ltrim($authorProfile, '/');

        $html = $this->respectfulRequest($url, 3);

        if (!$html || $this->detectCaptcha($html)) {
            return $this->emptyResearchGateResult();
        }

        $result = $this->parseResearchGateProfile($html, $url);
        $this->cache[$cacheKey] = $result;
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Journal Data Enhancement
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Crawl website jurnal untuk informasi tambahan yang tidak ada di API.
     *
     * @return array{
     *   title: string|null,
     *   publisher: string|null,
     *   scope: string|null,
     *   submission_url: string|null,
     *   review_time_weeks: int|null,
     *   acceptance_rate: float|null,
     *   open_access: bool,
     *   article_processing_charge: string|null
     * }
     */
    public function crawlJournalWebsite(string $issn, string $journalName = ''): array
    {
        $cacheKey = 'journal_web:' . $issn;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Coba beberapa sumber: Crossref → Springer → Elsevier fallback
        $result = $this->tryJournalFromCrossref($issn)
               ?: $this->tryJournalSearch($journalName ?: $issn);

        $this->cache[$cacheKey] = $result ?? $this->emptyJournalResult();
        return $this->cache[$cacheKey];
    }

    /**
     * Crawl database impact factor (Scimago, Ulrichsweb).
     *
     * @return array{
     *   sjr: float|null,
     *   snip: float|null,
     *   cite_score: float|null,
     *   impact_factor: float|null,
     *   quartile: string|null,
     *   h_index: int|null,
     *   source: string
     * }
     */
    public function crawlImpactFactorDatabases(string $issn): array
    {
        $cacheKey = 'if:' . $issn;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Scimago JR adalah sumber publik dan dapat di-crawl
        $scimagoUrl = 'https://www.scimagojr.com/journalsearch.php?tip=jou&q=' . urlencode($issn);
        $html       = $this->respectfulRequest($scimagoUrl, 2);

        $result = ['sjr' => null, 'snip' => null, 'cite_score' => null,
                   'impact_factor' => null, 'quartile' => null, 'h_index' => null,
                   'source' => 'scimago'];

        if ($html && !$this->detectCaptcha($html)) {
            $result = array_merge($result, $this->parseScimagoData($html));
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Institution Research Mapping
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Crawl profil institusi penelitian.
     *
     * @return array{
     *   name: string|null,
     *   type: string|null,
     *   address: string|null,
     *   website: string|null,
     *   research_centers: array,
     *   faculty_count: int|null,
     *   research_output: int|null
     * }
     */
    public function crawlInstitutionProfile(string $institutionName): array
    {
        $cacheKey = 'inst:' . md5($institutionName);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Coba Wikipedia sebagai sumber data institusi publik
        $wikiUrl = 'https://id.wikipedia.org/w/api.php?action=query&format=json'
                 . '&titles=' . urlencode($institutionName)
                 . '&prop=revisions|extracts&rvprop=content&exintro=true&explaintext=true';

        $json   = $this->respectfulRequest($wikiUrl, 1);
        $result = $this->emptyInstitutionResult();

        if ($json) {
            $data = json_decode($json, true);
            $page = reset($data['query']['pages'] ?? []);
            if ($page && !isset($page['missing'])) {
                $result['name']    = $page['title'] ?? $institutionName;
                $result['summary'] = $page['extract'] ?? null;
            }
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Cari peneliti dari direktori penelitian institusi.
     *
     * @return array Array profil peneliti dari direktori
     */
    public function crawlResearchDirectories(string $institution): array
    {
        $cacheKey = 'dir:' . md5($institution);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // SINTA adalah direktori resmi peneliti Indonesia — pakai via SangiaGateway
        // Fallback: scrape halaman SINTA publik
        $url  = 'https://sinta.kemdikbud.go.id/affiliations?q=' . urlencode($institution) . '&inst=&niddk=&page=1';
        $html = $this->respectfulRequest($url, 3);

        $result = [];
        if ($html && !$this->detectCaptcha($html)) {
            $result = $this->parseSintaAuthors($html);
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Citation Network Analysis
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Crawl jaringan sitasi untuk satu DOI melalui Crossref dan OpenCitations.
     *
     * @return array{
     *   doi: string,
     *   title: string|null,
     *   citing_dois: array,
     *   referenced_dois: array,
     *   citation_count: int,
     *   sources: array
     * }
     */
    public function crawlCitationNetworks(string $doi): array
    {
        $cacheKey = 'cit:' . md5($doi);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = ['doi' => $doi, 'title' => null, 'citing_dois' => [],
                   'referenced_dois' => [], 'citation_count' => 0, 'sources' => []];

        // 1. OpenCitations COCI API (publik, tidak butuh key)
        $ociUrl  = 'https://opencitations.net/index/coci/api/v1/citations/' . urlencode($doi);
        $ociJson = $this->respectfulRequest($ociUrl, 1);
        if ($ociJson) {
            $citations = json_decode($ociJson, true) ?? [];
            $result['citing_dois']   = array_column($citations, 'citing');
            $result['citation_count'] = count($citations);
            $result['sources'][]     = 'opencitations';
        }

        // 2. Crossref API untuk metadata dan referensi
        $crUrl  = 'https://api.crossref.org/works/' . urlencode($doi);
        $crJson = $this->respectfulRequest($crUrl, 1);
        if ($crJson) {
            $data  = json_decode($crJson, true);
            $work  = $data['message'] ?? [];
            $result['title']           = ($work['title'][0] ?? null);
            $result['referenced_dois'] = array_filter(
                array_column($work['reference'] ?? [], 'DOI')
            );
            if (!$result['citation_count'] && isset($work['is-referenced-by-count'])) {
                $result['citation_count'] = (int) $work['is-referenced-by-count'];
            }
            $result['sources'][] = 'crossref';
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Cari makalah terkait berdasarkan kata kunci melalui Semantic Scholar API.
     *
     * @return array Array metadata artikel
     */
    public function crawlRelatedPapers(string $keywords, int $limit = 10): array
    {
        $cacheKey = 'related:' . md5($keywords);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Semantic Scholar API — publik, rate limit 100 req/5 menit
        $url  = 'https://api.semanticscholar.org/graph/v1/paper/search'
              . '?query=' . urlencode($keywords)
              . '&limit=' . $limit
              . '&fields=title,authors,year,citationCount,externalIds,abstract';

        $json = $this->respectfulRequest($url, 1);
        if (!$json) {
            return [];
        }

        $data    = json_decode($json, true);
        $papers  = $data['data'] ?? [];
        $result  = array_map(fn($p) => [
            'title'     => $p['title'] ?? null,
            'authors'   => array_column($p['authors'] ?? [], 'name'),
            'year'      => $p['year'] ?? null,
            'citations' => $p['citationCount'] ?? 0,
            'doi'       => $p['externalIds']['DOI'] ?? null,
            'abstract'  => $p['abstract'] ?? null,
        ], $papers);

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Respectful Crawling Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * HTTP request dengan rate limiting, rotasi user-agent, dan retry.
     *
     * @param int $delaySec Jeda minimum dalam detik sebelum request
     */
    private function respectfulRequest(string $url, int $delaySec = self::DEFAULT_DELAY_S): string|false
    {
        // Cek robots.txt (cache sederhana per domain)
        if (!$this->isAllowedByRobots($url)) {
            error_log("[WizdamCrawler] Diblokir robots.txt: $url");
            return false;
        }

        sleep($delaySec);

        $ua = $this->rotateUserAgent();

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->http->get($url, [
                    'headers' => ['User-Agent' => $ua],
                ]);

                $status = $response->getStatusCode();
                $body   = (string) $response->getBody();

                if ($status === 200) {
                    return $body;
                }

                if ($status === 429 || $status === 503) {
                    // Too Many Requests / Service Unavailable — tunggu lebih lama
                    $retryAfter = (int) ($response->getHeaderLine('Retry-After') ?: ($attempt * 10));
                    error_log("[WizdamCrawler] Rate limited ($status) — tunggu {$retryAfter}s");
                    sleep($retryAfter);
                    continue;
                }

                if ($status === 403 || $status === 404) {
                    return false;
                }

            } catch (RequestException $e) {
                error_log("[WizdamCrawler] Request error (attempt $attempt): " . $e->getMessage());
                if ($attempt < self::MAX_RETRIES) {
                    sleep($attempt * 3);
                }
            } catch (GuzzleException $e) {
                error_log("[WizdamCrawler] Guzzle error: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Rotasi user agent secara round-robin.
     */
    private function rotateUserAgent(): string
    {
        $ua = $this->userAgents[$this->currentUaIndex % count($this->userAgents)];
        $this->currentUaIndex++;
        return $ua;
    }

    /**
     * Deteksi CAPTCHA dari konten HTML yang dikembalikan.
     */
    private function detectCaptcha(string $html): bool
    {
        $indicators = [
            'g-recaptcha',
            'hcaptcha',
            'cf-turnstile',
            'Please enable JavaScript',
            'Unusual traffic',
            'captcha',
            '/sorry/index',
            'detected unusual traffic',
            'bot detection',
        ];

        $lowerHtml = strtolower($html);
        foreach ($indicators as $indicator) {
            if (str_contains($lowerHtml, strtolower($indicator))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah URL diizinkan oleh robots.txt domain.
     * Cache per domain untuk efisiensi.
     */
    private array $robotsCache = [];

    private function isAllowedByRobots(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed) return true;

        $domain    = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $robotsUrl = $domain . '/robots.txt';

        if (!isset($this->robotsCache[$domain])) {
            try {
                $resp = $this->http->get($robotsUrl, [
                    'headers' => ['User-Agent' => $this->userAgents[0]],
                    'timeout' => 5,
                    'http_errors' => false,
                ]);
                $this->robotsCache[$domain] = (string) $resp->getBody();
            } catch (\Throwable) {
                $this->robotsCache[$domain] = '';
            }
        }

        // Parse sederhana: cek Disallow untuk User-agent: *
        $robotsTxt = $this->robotsCache[$domain];
        $path      = $parsed['path'] ?? '/';

        $lines     = explode("\n", $robotsTxt);
        $applies   = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if (stripos($line, 'User-agent: *') === 0) {
                $applies = true;
            } elseif (stripos($line, 'User-agent:') === 0) {
                $applies = false;
            } elseif ($applies && stripos($line, 'Disallow:') === 0) {
                $disallowed = trim(substr($line, strlen('Disallow:')));
                if ($disallowed && str_starts_with($path, $disallowed)) {
                    return false;
                }
            }
        }

        return true;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTML Parsers
    // ──────────────────────────────────────────────────────────────────────────

    private function parseScholarSearchResults(string $html, string $authorName): array
    {
        $result = $this->emptyScholarResult();

        // Scholar search result — cari elemen gs_rt (judul artikel) dan gs_a (penulis/jurnal)
        if (preg_match_all('/<h3[^>]*class="gs_rt"[^>]*>(.*?)<\/h3>/is', $html, $titleMatches)) {
            foreach (array_slice($titleMatches[1], 0, 5) as $title) {
                $result['articles'][] = ['title' => strip_tags($title)];
            }
        }

        return $result;
    }

    private function parseResearchGateProfile(string $html, string $url): array
    {
        $result             = $this->emptyResearchGateResult();
        $result['profile_url'] = $url;

        // Ekstrak statistik dasar dengan regex
        if (preg_match('/"publicationCount":(\d+)/', $html, $m)) {
            $result['publications'] = (int) $m[1];
        }
        if (preg_match('/"totalCitations":(\d+)/', $html, $m)) {
            $result['citations'] = (int) $m[1];
        }
        if (preg_match('/"rgScore":([\d.]+)/', $html, $m)) {
            $result['rg_score'] = (float) $m[1];
        }
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $result['name'] = trim(strip_tags($m[1]));
        }

        return $result;
    }

    private function parseScimagoData(string $html): array
    {
        $result = [];

        if (preg_match('/SJR[^<]*<[^>]+>([\d.]+)/', $html, $m)) {
            $result['sjr'] = (float) $m[1];
        }
        if (preg_match('/H\s*index[^<]*<[^>]+>(\d+)/', $html, $m)) {
            $result['h_index'] = (int) $m[1];
        }
        if (preg_match('/Q([1-4])/', $html, $m)) {
            $result['quartile'] = 'Q' . $m[1];
        }

        return $result;
    }

    private function parseSintaAuthors(string $html): array
    {
        $authors = [];
        if (preg_match_all('/<a[^>]+href="https:\/\/sinta\.kemdikbud\.go\.id\/authors\/profile\/(\d+)"[^>]*>(.*?)<\/a>/is', $html, $m)) {
            foreach (array_slice($m[0], 0, 20) as $i => $_) {
                $authors[] = [
                    'sinta_id' => $m[1][$i],
                    'name'     => strip_tags($m[2][$i]),
                ];
            }
        }
        return $authors;
    }

    private function tryJournalFromCrossref(string $issn): ?array
    {
        $url  = 'https://api.crossref.org/journals/' . urlencode($issn);
        $json = $this->respectfulRequest($url, 1);
        if (!$json) return null;

        $data    = json_decode($json, true);
        $message = $data['message'] ?? null;
        if (!$message) return null;

        return [
            'title'       => $message['title'] ?? null,
            'publisher'   => $message['publisher'] ?? null,
            'issn'        => $issn,
            'open_access' => false,
            'source'      => 'crossref',
        ];
    }

    private function tryJournalSearch(string $query): ?array
    {
        $url  = 'https://api.crossref.org/journals?query=' . urlencode($query) . '&rows=1';
        $json = $this->respectfulRequest($url, 1);
        if (!$json) return null;

        $data  = json_decode($json, true);
        $items = $data['message']['items'] ?? [];
        if (!$items) return null;

        $j = $items[0];
        return [
            'title'     => $j['title'] ?? null,
            'publisher' => $j['publisher'] ?? null,
            'source'    => 'crossref_search',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Empty result stubs
    // ──────────────────────────────────────────────────────────────────────────

    private function emptyScholarResult(): array
    {
        return ['name' => null, 'affiliation' => null, 'research_interests' => [],
                'citations' => null, 'h_index' => null, 'i10_index' => null,
                'articles' => [], 'scholar_id' => null];
    }

    private function emptyResearchGateResult(): array
    {
        return ['name' => null, 'institution' => null, 'publications' => null,
                'reads' => null, 'citations' => null, 'rg_score' => null,
                'skills' => [], 'profile_url' => null];
    }

    private function emptyJournalResult(): array
    {
        return ['title' => null, 'publisher' => null, 'scope' => null,
                'submission_url' => null, 'review_time_weeks' => null,
                'acceptance_rate' => null, 'open_access' => false,
                'article_processing_charge' => null];
    }

    private function emptyInstitutionResult(): array
    {
        return ['name' => null, 'type' => null, 'address' => null,
                'website' => null, 'research_centers' => [],
                'faculty_count' => null, 'research_output' => null,
                'summary' => null];
    }
}
