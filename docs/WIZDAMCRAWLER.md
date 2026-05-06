# WizdamCrawler — Dokumentasi Teknis

> Mesin harvesting data riset resmi untuk ekosistem Wizdam.

---

## Daftar Isi

1. [Gambaran Umum](#gambaran-umum)
2. [Arsitektur Komponen](#arsitektur-komponen)
3. [OAI-PMH: Jalur Resmi](#oai-pmh-jalur-resmi)
4. [WebCrawler: Smart Scraping](#webcrawler-smart-scraping)
5. [CrawlerEngine: Orkestrasi](#crawlerengine-orkestrasi)
6. [CrawlerReceiver: Endpoint Penerima](#crawlerreceiver-endpoint-penerima)
7. [RawDataPersister: Penyimpanan Cache](#rawdatapersister-penyimpanan-cache)
8. [Endpoint OAI-PMH yang Dikenal](#endpoint-oai-pmh-yang-dikenal)
9. [Format Metadata](#format-metadata)
10. [Integrasi dengan Wizdam-APIs](#integrasi-dengan-wizdam-apis)
11. [Scheduled Jobs](#scheduled-jobs)
12. [Etika & Compliance](#etika--compliance)
13. [Contoh Penggunaan](#contoh-penggunaan)

---

## Gambaran Umum

WizdamCrawler adalah pipeline pengumpul data riset yang:

- Menggunakan **OAI-PMH v2.0** sebagai jalur resmi/legal untuk memanen metadata publikasi dari repositori akademik Indonesia dan internasional.
- Menggunakan **smart web scraping** (WebCrawler) sebagai pelengkap untuk data yang tidak tersedia via API resmi (Google Scholar, ResearchGate, Scimago, SINTA).
- Menormalisasi semua data ke **skema Wizdam** sebelum disimpan ke cache database.
- Mengalirkan data yang sudah dipanen ke **Sangia Engine** via pola `supplied_data` untuk kalkulasi Wizdam Impact Score tanpa latensi tambahan.

```
Sumber Data → [Harvesting Layer] → [Normalisasi] → [Cache DB] → [Sangia Engine]
```

---

## Arsitektur Komponen

```
app/Services/
├── Harvesting/
│   ├── OaiPmhHarvester.php   — Protokol OAI-PMH v2.0 (jalur resmi)
│   └── CrawlerReceiver.php   — Endpoint penerima payload crawler eksternal
└── Crawler/
    ├── WebCrawler.php         — Smart web scraping (Scholar, Scimago, dll.)
    └── CrawlerEngine.php      — Orkestrasi pipeline terpadu

app/Jobs/
├── ResearcherCrawlerJob.php  — Async job crawling profil peneliti
└── ImpactAnalysisJob.php     — Async job kalkulasi WIS via Sangia

app/Services/SangiaApi/
└── RawDataPersister.php      — Penyimpanan & load dari cache database
```

---

## OAI-PMH: Jalur Resmi

**File:** `app/Services/Harvesting/OaiPmhHarvester.php`

### OAI-PMH Verbs yang Didukung

| Verb | Metode | Keterangan |
|------|--------|------------|
| `Identify` | `identify(string $endpoint)` | Info repositori |
| `ListSets` | `listSets(string $endpoint)` | Daftar set/koleksi |
| `ListMetadataFormats` | `listMetadataFormats(string $endpoint)` | Format metadata tersedia |
| `ListRecords` | `harvest()` | Panen record dengan paginasi |
| resumptionToken | otomatis | Lanjutkan panen setelah paginasi |

### Metode Harvesting Built-in

```php
$harvester = new OaiPmhHarvester();

// Sumber Indonesia
$harvester->harvestGaruda($from, $until, $set, $onBatch);
$harvester->harvestLipi($from, $until, $set, $onBatch);

// Sumber Internasional
$harvester->harvestZenodo($from, $until, $set, $onBatch);
$harvester->harvestArxiv($from, $until, $set, $onBatch);
$harvester->harvestDoaj($from, $until, $set, $onBatch);
$harvester->harvestPmc($from, $until, $set, $onBatch);

// URL arbitrer
$harvester->harvestAuto($endpointUrl, $from, $until, $set, $onBatch);

// Generic (dengan opsi persist & callback)
$harvester->harvest(
    endpoint: 'https://example.org/oai',
    metadataPrefix: 'oai_dc',
    from: '2024-01-01',
    until: '2024-12-31',
    set: 'cs',
    persist: true,
    onBatch: function(array $batch): bool {
        // return false untuk berhenti
        return true;
    }
);
```

### Callback `$onBatch`

Setiap kali sekelompok record selesai diparse, callback `$onBatch(array $batch): bool` dipanggil. Return `false` untuk menghentikan harvesting (useful untuk limit, preview, dsb.).

```php
$collected = [];
$onBatch = function (array $batch) use (&$collected): bool {
    $collected = array_merge($collected, $batch);
    return count($collected) < 500; // berhenti setelah 500 record
};
```

---

## WebCrawler: Smart Scraping

**File:** `app/Services/Crawler/WebCrawler.php`

### Metode yang Tersedia

| Metode | Sumber | Data yang Dipanen |
|--------|--------|-------------------|
| `crawlGoogleScholar(name)` | Google Scholar | h-index, citedBy, artikel teratas |
| `crawlResearchGate(orcid)` | ResearchGate | reads, recommendations, followers |
| `crawlJournalWebsite(issn)` | Situs jurnal | kebijakan OA, scope, submission |
| `crawlImpactFactorDatabases(issn)` | Scimago | SJR, quartile, H-index, citescore |
| `crawlInstitutionProfile(name)` | Wikipedia API | deskripsi, founded, lokasi |
| `crawlResearchDirectories(id, source)` | SINTA | akreditasi, skor SINTA |
| `crawlCitationNetworks(doi)` | OpenCitations + Crossref | forward/backward citations |
| `crawlRelatedPapers(doi)` | Semantic Scholar | paper terkait, TLDRs |

### Respectful Crawling

```php
// Semua permintaan melewati respectfulRequest() yang:
// 1. Cek robots.txt (in-memory cache per session)
// 2. Jeda rate limiting (default 2s, configurable)
// 3. Rotasi user-agent dari pool
// 4. Deteksi CAPTCHA → throw exception
private function respectfulRequest(string $url, float $delaySec = 2.0): string
```

---

## CrawlerEngine: Orkestrasi

**File:** `app/Services/Crawler/CrawlerEngine.php`

Orkestrasi pipeline terpadu yang mengintegrasikan semua komponen.

### Harvesting Peneliti

```php
$engine = new CrawlerEngine();

$profile = $engine->harvestResearcher('0000-0002-1234-5678', [
    'sources'       => ['orcid', 'scholar', 'citations'],
    'persist'       => true,
    'enrich_sangia' => false,
]);
```

### Harvesting Jurnal via OAI-PMH

```php
// Sumber built-in
$articles = $engine->harvestJournal('garuda', [
    'from'       => '2024-01-01',
    'until'      => '2024-12-31',
    'maxRecords' => 500,
    'persist'    => true,
]);

// URL arbitrer
$articles = $engine->harvestJournal('https://journal.ugm.ac.id/oai', [
    'metadataPrefix' => 'oai_dc',
    'persist'        => true,
]);

// Semua sumber Indonesia sekaligus
$results = $engine->harvestAllIndonesianSources([
    'from'         => '2025-01-01',
    'maxPerSource' => 200,
]);
```

### Full Harvesting Cycle

```php
$stats = $engine->runFullCycle([
    'sources'            => ['garuda', 'zenodo'],
    'from'               => '2025-01-01',
    'maxRecords'         => 1000,
    'maxCitationLookups' => 100,
    'persist'            => true,
]);

// $stats = ['harvested' => 847, 'persisted' => 847, 'errors' => 2, 'sources' => [...]]
```

---

## CrawlerReceiver: Endpoint Penerima

**File:** `app/Services/Harvesting/CrawlerReceiver.php`
**Route:** `POST /api/crawler`
**Auth:** `Authorization: Bearer {CRAWLER_RECEIVER_TOKEN}`

Endpoint ini menerima payload dari crawler eksternal atau agen otomatis.

### Format Payload

```json
{
  "type": "researcher",
  "data": {
    "orcid": "0000-0002-1234-5678",
    "full_name": "Dr. Budi Santoso",
    "institution_id": 42,
    "email": "budi@universitas.ac.id"
  }
}
```

**Tipe yang didukung:** `researcher`, `article`, `journal`

### Respons

```json
{"status": "ok", "result": {"id": 123, "orcid": "0000-0002-1234-5678"}}
```

### Konfigurasi Token

```php
// config/api.php
return [
    'crawler_token' => $_ENV['CRAWLER_RECEIVER_TOKEN'] ?? '',
    // ...
];
```

---

## RawDataPersister: Penyimpanan Cache

**File:** `app/Services/SangiaApi/RawDataPersister.php`

Semua data yang dipanen disimpan ke tabel cache sebelum dikirim ke Sangia Engine.

| Metode | Tabel | Keterangan |
|--------|-------|------------|
| `saveAuthorProfile(orcid, data)` | `author_profiles_cache` | Profil peneliti |
| `loadAuthorProfile(orcid)` | `author_profiles_cache` | Load dari cache |
| `saveCitation(doi, data)` | `citations_cache` | Data sitasi/artikel |
| `saveAnalysis(orcid, type, data)` | `analysis_history` | Hasil analisis Sangia |

---

## Endpoint OAI-PMH yang Dikenal

| Nama | URL Endpoint | Format | Catatan |
|------|-------------|--------|---------|
| Garuda Kemdikbud | `https://garuda.kemdikbud.go.id/oai` | oai_dc, oai_jats | Jurnal Indonesia terlengkap |
| LIPI / BRIN | `https://lipi.go.id/oai` | oai_dc | Repositori BRIN |
| Zenodo | `https://zenodo.org/oai2d` | oai_dc, mods | CERN general purpose |
| arXiv | `https://export.arxiv.org/oai2` | oai_dc, mods | Preprint sains |
| DOAJ | `https://doaj.org/oai` | oai_dc, mods | Jurnal OA internasional |
| PubMed Central | `https://www.ncbi.nlm.nih.gov/pmc/oai/oai.cgi` | oai_dc, pmc | Biomedis |
| Crossref | `https://api.crossref.org/works` | JSON REST | DOI metadata |

Endpoint baru dapat ditambahkan di `OaiPmhHarvester::KNOWN_ENDPOINTS`.

---

## Format Metadata

### Skema Wizdam (hasil normalisasi)

Semua format (Dublin Core, JATS, MODS) dinormalisasi ke skema yang sama:

```php
[
    'title'             => string,
    'authors'           => array,       // [{name, orcid, affiliation}]
    'abstract'          => string,
    'doi'               => string,
    'journal'           => string,
    'issn'              => string,
    'publication_year'  => int,
    'keywords'          => array,
    'language'          => string,
    'url'               => string,
    'source_format'     => 'oai_dc|oai_jats|mods',
    'raw_identifier'    => string,      // OAI record identifier
]
```

### Parsing JATS XML (ORCID Extraction)

```xml
<!-- WizdamCrawler mengekstrak ORCID dari contrib JATS: -->
<contrib contrib-type="author">
    <name><surname>Santoso</surname><given-names>Budi</given-names></name>
    <contrib-id contrib-id-type="orcid">https://orcid.org/0000-0002-1234-5678</contrib-id>
</contrib>
```

---

## Integrasi dengan Wizdam-APIs

WizdamCrawler dan Sangia Engine bekerja bersama lewat pola **supplied_data**:

```
1. WizdamCrawler memanen dan menyimpan data di author_profiles_cache

2. ImpactAnalysisJob (queue) membaca cache:
   $cached = RawDataPersister::loadAuthorProfile($orcid);

3. Job mengirim data ke Sangia Engine sebagai supplied_data:
   ImpactScoreClient::calculateByOrcid($orcid, $entityId, $scopusId, ...)
   → POST ke api.sangia.org dengan body: { supplied_works: [...], supplied_person: {...} }

4. Sangia menggunakan data yang disuplai tanpa fetch ulang:
   response: { data_source: "wizdam_scola_db", wizdam_score: 87.4 }

5. Skor disimpan ke database Wizdam Scola
```

**Keuntungan pola ini:**
- Latensi kalkulasi turun drastis (tidak ada fetch eksternal di Sangia)
- Sangia tidak dibebani oleh rate limiting sumber eksternal
- Data lokal bisa di-enrich sebelum dikirim ke Sangia

---

## Scheduled Jobs

Untuk harvesting terjadwal, jalankan job via queue:

```bash
# Crawling profil peneliti
php artisan queue:work --queue=crawler

# Atau langsung via CLI
php bin/console crawler:run --source=garuda --from=2025-01-01 --limit=500
```

**Job yang tersedia:**

| Job Class | Queue | Trigger |
|-----------|-------|---------|
| `ResearcherCrawlerJob` | crawler | Saat peneliti baru ditambahkan |
| `ImpactAnalysisJob` | impact | Setelah crawling profil selesai |

---

## Etika & Compliance

WizdamCrawler mengikuti praktik terbaik etika web crawling:

1. **robots.txt**: Semua URL dicek terhadap `robots.txt` domain sebelum diakses.
2. **Rate Limiting**: Minimum 2 detik jeda antar permintaan per domain. Configurable via parameter `delaySec`.
3. **User-Agent**: Identitas transparan menggunakan string seperti `WizdamBot/1.0 (+https://wizdam.id/crawler)`.
4. **CAPTCHA Detection**: Crawler berhenti otomatis jika mendeteksi CAPTCHA/anti-bot.
5. **Prioritas OAI-PMH**: API publik resmi selalu digunakan sebelum scraping.
6. **Data Terbuka**: Hanya memanen data yang tersedia secara publik.

---

## Contoh Penggunaan

### Harvest seluruh artikel Garuda tahun 2025

```php
use Wizdam\Services\Crawler\CrawlerEngine;

$engine   = new CrawlerEngine();
$articles = $engine->harvestJournal('garuda', [
    'from'       => '2025-01-01',
    'until'      => '2025-12-31',
    'maxRecords' => 2000,
    'persist'    => true,
]);

echo "Dipanen: " . count($articles) . " artikel\n";
```

### Harvest profil peneliti dari ORCID + Scholar

```php
use Wizdam\Services\Crawler\CrawlerEngine;

$engine  = new CrawlerEngine();
$profile = $engine->harvestResearcher('0000-0002-1234-5678', [
    'sources' => ['orcid', 'scholar', 'citations'],
    'persist' => true,
]);

$stats = $engine->getStats();
// ['harvested' => 3, 'persisted' => 1, 'errors' => 0, 'sources' => [...]]
```

### Harvest sitasi untuk batch DOI

```php
use Wizdam\Services\Crawler\CrawlerEngine;

$dois   = ['10.1016/j.example.2024.001', '10.1038/nature.2024.002'];
$engine = new CrawlerEngine();

foreach ($dois as $doi) {
    $citations = $engine->harvestCitations($doi);
    echo "$doi: " . count($citations['citing_works'] ?? []) . " citasi\n";
}
```

### Harvest dari endpoint OAI-PMH universitas

```php
use Wizdam\Services\Harvesting\OaiPmhHarvester;

$harvester = new OaiPmhHarvester();

// Identifikasi endpoint terlebih dahulu
$info = $harvester->identify('https://jurnal.ugm.ac.id/oai');
echo "Repositori: " . $info['repositoryName'] . "\n";

// Lihat set yang tersedia
$sets = $harvester->listSets('https://jurnal.ugm.ac.id/oai');
foreach ($sets as $set) {
    echo $set['setSpec'] . ": " . $set['setName'] . "\n";
}

// Panen
$harvester->harvest(
    endpoint:       'https://jurnal.ugm.ac.id/oai',
    metadataPrefix: 'oai_jats',
    from:           '2024-01-01',
    persist:        true,
    onBatch:        function (array $batch): bool {
        echo "Batch: " . count($batch) . " artikel\n";
        return true;
    }
);
```
