<?php

declare(strict_types=1);

namespace Wizdam\Services\Harvesting;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Wizdam\Database\Models\ArticleModel;
use Wizdam\Services\SangiaApi\RawDataPersister;

/**
 * OAI-PMH Harvester — Pemanen metadata artikel melalui protokol resmi jurnal.
 *
 * Protokol: Open Archives Initiative – Protocol for Metadata Harvesting v2.0
 * Referensi: https://www.openarchives.org/OAI/openarchivesprotocol.html
 *
 * Format metadata yang didukung:
 *   - oai_dc     (Dublin Core)    — universal, semua repositori OAI-PMH
 *   - oai_jats   (JATS XML)       — jurnal biomedis dan sains (PMC, Copernicus, dll.)
 *   - oai_marc21 (MARC21)         — repositori perpustakaan
 *   - mods       (MODS)           — repositori akademik
 *   - etdms      (ETD-MS)         — tesis dan disertasi
 *
 * Target legal jurnal Indonesia (OAI-PMH publik):
 *   - Garuda (garuda.kemdikbud.go.id)
 *   - LIPI e-Journal (e-journal.lipi.go.id)
 *   - Jurnal UGM, UI, ITB, dll.
 *   - DOAJ Open Access journals
 *   - Zenodo (zenodo.org)
 *   - arXiv (export.arxiv.org)
 */
class OaiPmhHarvester
{
    private Client       $http;
    private ?ArticleModel $articleModel;

    private const RETRY_DELAY_S = 3;
    private const MAX_RETRIES   = 3;

    /** Target OAI-PMH Indonesia yang terverifikasi legal dan publik. */
    public const KNOWN_ENDPOINTS = [
        'garuda'   => 'https://garuda.kemdikbud.go.id/oai',
        'lipi'     => 'https://e-journal.lipi.go.id/index.php/index/oai',
        'zenodo'   => 'https://zenodo.org/oai2d',
        'arxiv'    => 'https://export.arxiv.org/oai2',
        'doaj'     => 'https://doaj.org/oai',
        'pmc'      => 'https://www.ncbi.nlm.nih.gov/pmc/oai/oai.cgi',
        'crossref' => 'https://oai.crossref.org/oai',
    ];

    public function __construct()
    {
        $this->http         = new Client(['timeout' => 90, 'http_errors' => false]);
        $this->articleModel = null; // lazy init
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Verb: Identify
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Identify — ambil metadata repositori (nama, admin email, earliest date, dll.).
     *
     * @return array{repositoryName, adminEmail, earliestDatestamp, granularity, deletedRecord}
     */
    public function identify(string $baseUrl): array
    {
        $xml = $this->request($baseUrl, ['verb' => 'Identify']);
        if (!$xml) return [];

        $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $id = $xml->xpath('//oai:Identify')[0] ?? null;
        if (!$id) return [];

        return [
            'repositoryName'   => (string) ($id->repositoryName ?? ''),
            'adminEmail'       => (string) ($id->adminEmail ?? ''),
            'earliestDatestamp'=> (string) ($id->earliestDatestamp ?? ''),
            'granularity'      => (string) ($id->granularity ?? ''),
            'deletedRecord'    => (string) ($id->deletedRecord ?? ''),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Verb: ListSets
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ListSets — daftar koleksi/set yang tersedia di repositori.
     *
     * @return array<int, array{setSpec: string, setName: string}>
     */
    public function listSets(string $baseUrl): array
    {
        $xml  = $this->request($baseUrl, ['verb' => 'ListSets']);
        if (!$xml) return [];

        $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $sets = [];

        foreach ($xml->xpath('//oai:set') as $set) {
            $sets[] = [
                'setSpec' => (string) ($set->setSpec ?? ''),
                'setName' => (string) ($set->setName ?? ''),
            ];
        }

        return $sets;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Verb: ListMetadataFormats
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ListMetadataFormats — format metadata apa saja yang didukung repositori.
     *
     * @return array<int, array{metadataPrefix, schema, metadataNamespace}>
     */
    public function listMetadataFormats(string $baseUrl): array
    {
        $xml = $this->request($baseUrl, ['verb' => 'ListMetadataFormats']);
        if (!$xml) return [];

        $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $formats = [];

        foreach ($xml->xpath('//oai:metadataFormat') as $fmt) {
            $formats[] = [
                'metadataPrefix'    => (string) ($fmt->metadataPrefix ?? ''),
                'schema'            => (string) ($fmt->schema ?? ''),
                'metadataNamespace' => (string) ($fmt->metadataNamespace ?? ''),
            ];
        }

        return $formats;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Verb: ListRecords — harvest utama
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Harvest semua record dari endpoint OAI-PMH dengan pagination otomatis
     * via resumptionToken.
     *
     * @param string $baseUrl        URL dasar endpoint OAI-PMH
     * @param string $metadataPrefix Format metadata (oai_dc, oai_jats, mods, ...)
     * @param string $set            Set/koleksi opsional
     * @param string $from           Tanggal mulai (YYYY-MM-DD)
     * @param string $until          Tanggal akhir (YYYY-MM-DD)
     * @param bool   $persist        Simpan ke DB secara langsung
     * @param callable|null $onBatch Callback setiap batch: fn(array $articles, int $total)
     *
     * @return array<int, array> Semua artikel yang diparsing
     */
    public function harvest(
        string    $baseUrl,
        string    $metadataPrefix = 'oai_dc',
        string    $set            = '',
        string    $from           = '',
        string    $until          = '',
        bool      $persist        = false,
        ?callable $onBatch        = null
    ): array {
        $articles        = [];
        $resumptionToken = null;
        $pageNum         = 0;

        do {
            $params = ['verb' => 'ListRecords'];

            if ($resumptionToken) {
                $params['resumptionToken'] = $resumptionToken;
            } else {
                $params['metadataPrefix'] = $metadataPrefix;
                if ($set)   $params['set']   = $set;
                if ($from)  $params['from']  = $from;
                if ($until) $params['until'] = $until;
            }

            $xml = $this->request($baseUrl, $params);
            if (!$xml) break;

            $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');

            $batch = [];
            foreach ($xml->xpath('//oai:record') as $record) {
                $parsed = match ($metadataPrefix) {
                    'oai_jats' => $this->parseJatsRecord($record),
                    'mods'     => $this->parseModsRecord($record),
                    default    => $this->parseDcRecord($record),
                };

                if ($parsed) {
                    $batch[]    = $parsed;
                    $articles[] = $parsed;
                }
            }

            if ($persist && $batch) {
                $this->persistBatch($batch);
            }

            if ($onBatch && $batch) {
                $onBatch($batch, count($articles));
            }

            $token           = $xml->xpath('//oai:resumptionToken');
            $resumptionToken = (!empty($token) && (string) $token[0] !== '')
                ? (string) $token[0]
                : null;

            $pageNum++;
            // Jeda sopan antar halaman
            if ($resumptionToken) {
                sleep(2);
            }

        } while ($resumptionToken !== null);

        return $articles;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Harvest target terkenal (convenience methods)
    // ──────────────────────────────────────────────────────────────────────────

    /** Harvest dari Garuda Kemdikbud. */
    public function harvestGaruda(string $from = '', string $until = '', string $set = '', bool $persist = true, ?callable $onBatch = null): array
    {
        return $this->harvest(self::KNOWN_ENDPOINTS['garuda'], 'oai_dc', $set, $from, $until, $persist, $onBatch);
    }

    /** Harvest dari Zenodo dengan filter set (mis: 'user-indonesia'). */
    public function harvestZenodo(string $from = '', string $until = '', string $set = '', bool $persist = true, ?callable $onBatch = null): array
    {
        return $this->harvest(self::KNOWN_ENDPOINTS['zenodo'], 'oai_dc', $set, $from, $until, $persist, $onBatch);
    }

    /** Harvest dari arXiv dengan filter set bidang (mis: 'cs', 'physics'). */
    public function harvestArxiv(string $from = '', string $until = '', string $set = 'cs', bool $persist = true, ?callable $onBatch = null): array
    {
        return $this->harvest(self::KNOWN_ENDPOINTS['arxiv'], 'oai_dc', $set, $from, $until, $persist, $onBatch);
    }

    /** Harvest dari DOAJ. */
    public function harvestDoaj(string $from = '', string $until = '', string $set = '', bool $persist = true, ?callable $onBatch = null): array
    {
        return $this->harvest(self::KNOWN_ENDPOINTS['doaj'], 'oai_dc', $set, $from, $until, $persist, $onBatch);
    }

    /** Harvest dari PMC. */
    public function harvestPmc(string $from = '', string $until = '', string $set = '', bool $persist = true, ?callable $onBatch = null): array
    {
        return $this->harvest(self::KNOWN_ENDPOINTS['pmc'], 'oai_dc', $set, $from, $until, $persist, $onBatch);
    }

    /**
     * Harvest dari endpoint jurnal mana pun dengan deteksi format otomatis.
     * Mencoba oai_jats → mods → oai_dc secara berurutan.
     */
    public function harvestAuto(string $baseUrl, string $from = '', string $until = '', string $set = '', bool $persist = true, ?callable $onBatch = null): array
    {
        $formats = array_column($this->listMetadataFormats($baseUrl), 'metadataPrefix');

        $preferred = ['oai_jats', 'mods', 'oai_dc'];
        $prefix    = 'oai_dc';

        foreach ($preferred as $p) {
            if (in_array($p, $formats, true)) {
                $prefix = $p;
                break;
            }
        }

        return $this->harvest($baseUrl, $prefix, $set, $from, $until, $persist, $onBatch);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Parsers per format
    // ──────────────────────────────────────────────────────────────────────────

    /** Parse record Dublin Core (oai_dc). */
    private function parseDcRecord(\SimpleXMLElement $record): ?array
    {
        $record->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $metadata = $record->xpath('.//dc:*');
        if (!$metadata) return null;

        $raw = [];
        foreach ($metadata as $el) {
            $raw[$el->getName()][] = (string) $el;
        }

        $oaiId = (string) ($record->xpath('.//oai:header/oai:identifier')[0]
                  ?? $record->xpath('header/identifier')[0]
                  ?? '');

        return $this->normalizeArticle([
            'oai_id'      => $oaiId,
            'title'       => $raw['title'][0]       ?? '',
            'authors'     => $raw['creator']         ?? [],
            'abstract'    => $raw['description'][0] ?? '',
            'keywords'    => $raw['subject']         ?? [],
            'date'        => $raw['date'][0]         ?? '',
            'identifiers' => $raw['identifier']      ?? [],
            'source'      => $raw['source'][0]       ?? '',
            'language'    => $raw['language'][0]     ?? '',
            'type'        => $raw['type'][0]         ?? '',
            'publisher'   => $raw['publisher'][0]    ?? '',
            'format'      => 'oai_dc',
        ]);
    }

    /**
     * Parse record JATS XML (oai_jats) — format kaya raya untuk jurnal sains.
     * JATS = Journal Article Tag Suite (ANSI/NISO Z39.96)
     */
    private function parseJatsRecord(\SimpleXMLElement $record): ?array
    {
        $xml = $record->xpath('.//article')[0] ?? null;
        if (!$xml) return $this->parseDcRecord($record);

        $xml->registerXPathNamespace('xlink', 'http://www.w3.org/1999/xlink');

        // Judul
        $title = (string) ($xml->xpath('.//article-title')[0] ?? '');

        // Penulis
        $authors = [];
        foreach ($xml->xpath('.//contrib[@contrib-type="author"]') as $contrib) {
            $given  = (string) ($contrib->xpath('.//given-names')[0] ?? '');
            $family = (string) ($contrib->xpath('.//surname')[0]     ?? '');
            $orcid  = (string) ($contrib->xpath('.//contrib-id[@contrib-id-type="orcid"]')[0] ?? '');
            $authors[] = array_filter([
                'name'  => trim("$given $family"),
                'orcid' => preg_replace('#^https?://orcid\.org/#', '', $orcid),
            ]);
        }

        // Abstract
        $abstract = '';
        foreach ($xml->xpath('.//abstract//p') as $p) {
            $abstract .= ' ' . strip_tags((string) $p);
        }

        // DOI
        $doi = '';
        foreach ($xml->xpath('.//article-id') as $aid) {
            if ((string) $aid['pub-id-type'] === 'doi') {
                $doi = (string) $aid;
                break;
            }
        }

        // Tahun publikasi
        $year = (string) ($xml->xpath('.//pub-date/year')[0] ?? '');

        // Keywords
        $keywords = [];
        foreach ($xml->xpath('.//kwd') as $kwd) {
            $keywords[] = (string) $kwd;
        }

        // Journal info
        $journal   = (string) ($xml->xpath('.//journal-title')[0] ?? '');
        $issn      = (string) ($xml->xpath('.//issn[@pub-type="epub"]')[0]
                     ?? $xml->xpath('.//issn')[0] ?? '');
        $volume    = (string) ($xml->xpath('.//volume')[0] ?? '');
        $issue     = (string) ($xml->xpath('.//issue')[0]  ?? '');

        return $this->normalizeArticle([
            'oai_id'    => '',
            'title'     => $title,
            'authors'   => $authors,
            'abstract'  => trim($abstract),
            'keywords'  => $keywords,
            'date'      => $year,
            'identifiers' => $doi ? ["doi:$doi"] : [],
            'doi'       => $doi,
            'journal'   => $journal,
            'issn'      => $issn,
            'volume'    => $volume,
            'issue'     => $issue,
            'language'  => 'en',
            'type'      => 'journal-article',
            'format'    => 'oai_jats',
        ]);
    }

    /** Parse record MODS (Metadata Object Description Schema). */
    private function parseModsRecord(\SimpleXMLElement $record): ?array
    {
        $mods = $record->xpath('.//mods:mods')[0] ?? null;
        if (!$mods) return $this->parseDcRecord($record);

        $mods->registerXPathNamespace('mods', 'http://www.loc.gov/mods/v3');

        $title   = (string) ($mods->xpath('.//mods:title')[0]      ?? '');
        $authors = [];
        foreach ($mods->xpath('.//mods:name') as $name) {
            $given  = (string) ($name->xpath('.//mods:namePart[@type="given"]')[0]  ?? '');
            $family = (string) ($name->xpath('.//mods:namePart[@type="family"]')[0] ?? '');
            if ($given || $family) {
                $authors[] = ['name' => trim("$given $family")];
            }
        }
        $abstract = (string) ($mods->xpath('.//mods:abstract')[0] ?? '');
        $date     = (string) ($mods->xpath('.//mods:dateIssued')[0] ?? '');

        $identifiers = [];
        foreach ($mods->xpath('.//mods:identifier') as $id) {
            $identifiers[] = ((string) $id['type'] ?: '') . ':' . (string) $id;
        }

        return $this->normalizeArticle([
            'oai_id'      => '',
            'title'       => $title,
            'authors'     => $authors,
            'abstract'    => $abstract,
            'keywords'    => [],
            'date'        => $date,
            'identifiers' => $identifiers,
            'format'      => 'mods',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Normalisasi dan persistensi
    // ──────────────────────────────────────────────────────────────────────────

    /** Normalisasi semua format ke skema artikel Wizdam Scola. */
    private function normalizeArticle(array $raw): ?array
    {
        $title = trim($raw['title'] ?? '');
        if ($title === '') return null;

        // Ekstrak DOI dari identifiers jika belum ada
        $doi = $raw['doi'] ?? '';
        if (!$doi) {
            foreach ($raw['identifiers'] ?? [] as $id) {
                if (preg_match('#(10\.\d{4,}[/.].+)#i', $id, $m)) {
                    $doi = $m[1];
                    break;
                }
            }
        }

        // Normalisasi nama penulis
        $authors = array_map(function ($a) {
            if (is_string($a)) return ['name' => $a, 'orcid' => null];
            return ['name' => $a['name'] ?? '', 'orcid' => $a['orcid'] ?? null];
        }, $raw['authors'] ?? []);

        // Tahun dari tanggal
        $year = null;
        if (!empty($raw['date'])) {
            preg_match('/\d{4}/', $raw['date'], $m);
            $year = isset($m[0]) ? (int) $m[0] : null;
        }

        return [
            'oai_id'           => $raw['oai_id'] ?? '',
            'title'            => $title,
            'authors'          => $authors,
            'abstract'         => $raw['abstract'] ?? '',
            'keywords'         => $raw['keywords'] ?? [],
            'doi'              => $doi ?: null,
            'publication_year' => $year,
            'journal_title'    => $raw['journal'] ?? $raw['source'] ?? '',
            'journal_issn'     => $raw['issn'] ?? '',
            'volume'           => $raw['volume'] ?? '',
            'issue'            => $raw['issue']  ?? '',
            'language'         => $raw['language'] ?? '',
            'type'             => $raw['type'] ?? '',
            'metadata_format'  => $raw['format'] ?? 'oai_dc',
            'harvested_at'     => date('Y-m-d H:i:s'),
        ];
    }

    /** Simpan batch artikel ke database dan log via RawDataPersister. */
    private function persistBatch(array $articles): void
    {
        foreach ($articles as $article) {
            if ($article['doi']) {
                RawDataPersister::saveCitation($article['doi'], [
                    'metadata'   => $article,
                    'fetched_at' => $article['harvested_at'],
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTTP helper
    // ──────────────────────────────────────────────────────────────────────────

    private function request(string $baseUrl, array $params): ?\SimpleXMLElement
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->http->get($baseUrl, ['query' => $params]);
                $status   = $response->getStatusCode();
                $body     = (string) $response->getBody();

                if ($status === 503 || $status === 429) {
                    sleep(self::RETRY_DELAY_S * $attempt);
                    continue;
                }

                if ($status !== 200 || !$body) {
                    return null;
                }

                return new \SimpleXMLElement($body);

            } catch (GuzzleException $e) {
                error_log("[OaiPmhHarvester] HTTP error (attempt $attempt): " . $e->getMessage());
                if ($attempt < self::MAX_RETRIES) sleep(self::RETRY_DELAY_S);
            } catch (\Exception $e) {
                error_log("[OaiPmhHarvester] XML parse error: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }
}
