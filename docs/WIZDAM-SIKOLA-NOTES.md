# Catatan Pengembangan untuk Wizdam Sikola

Dokumen ini berisi poin-poin penting yang harus diperhatikan saat membangun interface Wizdam Sikola sebagai frontend dari Sangia API Engine (wizdam-apis).

---

## 1. Autentikasi API Key

Wizdam Sikola adalah **satu-satunya** yang men-generate API key untuk user.

### Generate key (PHP — gunakan di backend Wizdam Sikola):
```php
use Sangia\Gateway\ApiKeyMiddleware;

$secret = env('WIZDAM_SHARED_SECRET'); // harus identik di kedua sistem
$key    = ApiKeyMiddleware::generateKey($userId, $secret);
// Simpan $key ke tabel users (kolom api_key) di wizdam_sikola DB
// Kirim $key ke user melalui UI
```

### Cabut key:
```php
// Panggil endpoint admin wizdam-apis
POST /api/v1/admin/keys/revoke
X-API-Key: {service_key_wizdam_sikola}
{ "key": "wz_42_1719000000_a3f8e2c1d5b7" }
```

**Penting:** Simpan `WIZDAM_SHARED_SECRET` yang **identik** di `.env` kedua sistem.

---

## 2. Pengelolaan Bobot Analisis (Admin Panel)

Wizdam Sikola mengontrol penuh semua bobot analisis melalui admin panel.  
Bobot dikirimkan ke wizdam-apis dalam setiap request — nilai dalam kode hanya fallback.

### Bobot yang bisa dikonfigurasi:

#### a) Bobot SDG Scoring (per versi)
```json
{
  "weights": {
    "keyword": 0.30,
    "similarity": 0.30,
    "substantive": 0.20,
    "causal": 0.20,
    "max_sdgs": 7,
    "thresholds": {
      "min": 0.20,
      "confidence": 0.30,
      "high": 0.60
    }
  }
}
```
Kirim di body request `POST /api/v1/sdg/{version}/classify`.

#### b) Bobot Komposit Wizdam Impact Score
```json
{
  "weights": {
    "academic": 0.40,
    "social": 0.25,
    "economic": 0.20,
    "sdg": 0.15
  }
}
```
Kirim di body request `POST /api/v1/impact/calculate`.

### Rekomendasi tabel di DB Wizdam Sikola:
```sql
CREATE TABLE analysis_weight_configs (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  config_key  VARCHAR(50) UNIQUE NOT NULL,  -- e.g. 'sdg_v5', 'impact_composite'
  weights     JSON NOT NULL,
  updated_by  INT,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

Saat memanggil API, load config dari DB dan sertakan dalam request body.

---

## 3. Arsitektur Data — Wizdam Sikola sebagai Sumber Kebenaran Tunggal

wizdam-apis adalah **pure analysis engine** — tidak menyimpan hasil apapun secara permanen.  
Semua persistensi adalah tanggung jawab Wizdam Sikola.

### Empat jalur masuk data ke Wizdam Sikola:

1. **Direct API call** — wizdam-apis fetch dari ORCID/Scopus/dll, lalu mengembalikan `raw_data` untuk disimpan
2. **Input dari UI** — user mengisi data social/economic, upload karya secara manual di Wizdam Sikola
3. **Registrasi + sync** — user koneksikan akun ORCID/Scopus saat mendaftar, data di-sync ke DB
4. **Proactive crawler** — Wizdam Sikola menjalankan crawler mandiri untuk memperbarui data secara berkala

### Pola `supplied_data` — Kirim data dari DB ke wizdam-apis

Jika Wizdam Sikola sudah memiliki data di DB, kirimkan dalam request body.  
wizdam-apis menggunakan data tersebut tanpa cURL ke API eksternal.

```json
{
  "orcid": "0000-0002-1234-5678",
  "supplied_works": [
    {
      "title": "Solar Panel Adoption in Rural Java",
      "doi": "10.1234/example",
      "publication_year": 2023,
      "type": "journal-article",
      "journal_title": "Renewable Energy",
      "authors_string": "Budi Santoso, Ani Wijaya"
    }
  ],
  "supplied_person": {
    "name": "Budi Santoso",
    "given_names": "Budi",
    "family_name": "Santoso",
    "country": "ID"
  },
  "supplied_scopus": {
    "author": { "full_name": "Budi Santoso", "h_index": 18, "citation_count": 3200 },
    "publications": []
  }
}
```

Response saat data disupply dari DB: `"data_source": "wizdam_sikola_db"`  
wizdam-apis tidak akan melakukan cURL ke ORCID/Scopus.

### Pola `raw_data` — Simpan data yang baru diambil ke DB

Ketika wizdam-apis terpaksa fetch dari API eksternal (karena data tidak disupply),  
response menyertakan field `raw_data` dengan data mentah dan `fetched_at`.  
**Wizdam Sikola harus menyimpan ini ke tabelnya** agar request berikutnya bisa menggunakan `supplied_data`.

```php
// Contoh: menyimpan raw_data setelah menerima response dari wizdam-apis
$response = $sangiaClient->getOrcidProfile($orcid);

if (isset($response['raw_data'])) {
    AuthorProfileCache::updateOrCreate(
        ['orcid' => $orcid],
        [
            'person_data'  => json_encode($response['raw_data']['person']),
            'works_data'   => json_encode($response['raw_data']['works']),
            'fetched_at'   => $response['raw_data']['fetched_at'],
        ]
    );
}
```

### Rekomendasi tabel cache di DB Wizdam Sikola:
```sql
-- Profil peneliti (ORCID + Scopus)
CREATE TABLE author_profiles_cache (
  orcid         VARCHAR(19) PRIMARY KEY,
  person_data   JSON,
  works_data    JSON,
  scopus_data   JSON,
  fetched_at    DATETIME,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sitasi per DOI
CREATE TABLE citations_cache (
  doi           VARCHAR(255) PRIMARY KEY,
  metadata      JSON,
  citations     JSON,
  counts        JSON,
  fetched_at    DATETIME,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Metrik jurnal
CREATE TABLE journal_profiles_cache (
  issn          VARCHAR(10) PRIMARY KEY,
  scopus_data   JSON,
  sinta_data    JSON,
  fetched_at    DATETIME,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Riwayat analisis
CREATE TABLE analysis_history (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  orcid         VARCHAR(19),
  analysis_type VARCHAR(50),   -- 'sdg', 'impact', 'trend', 'recommendation'
  result        JSON,
  calculated_at DATETIME,
  INDEX idx_orcid (orcid)
);
```

---

## 4. Pola Batch Anti-Timeout (ORCID)

Untuk endpoint yang memproses banyak karya (SDG classify dengan ORCID, Impact Score):

```javascript
async function runBatchAnalysis(endpoint, payload, onProgress) {
  let offset = 0;
  const batchSize = 20;

  while (true) {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'X-API-Key': userApiKey, 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, offset, batch_size: batchSize })
    });

    const data = await response.json();

    if (data.status === 'error') throw new Error(data.message);

    if (data.status === 'processing') {
      onProgress?.(data.progress); // { processed, total_works, percent }
      offset = data.next_offset;
      await new Promise(r => setTimeout(r, 300)); // 300ms delay
      continue;
    }

    if (data.status === 'success') {
      // Simpan raw_data ke DB jika ada
      if (data.raw_data) await saveRawDataToDb(data.raw_data);
      return data;
    }
  }
}
```

**Penting:** Jika server mengembalikan `status: "error", code: 410`, artinya session batch expired — restart dari `offset: 0`.

---

## 5. Suplai Data ke WizdamScoreEngine

Wizdam Impact Score menjadi **powerful** jika pilar Social dan Economic diisi dengan data nyata.

### Data Social Pillar (0–100 per metrik):
| Field | Cara Mendapatkan |
|-------|-----------------|
| `media_mentions` | Crawler berita/media (Google News API, MediaStack) |
| `policy_citations` | Input manual admin / crawler kebijakan |
| `social_shares` | Altmetric API, Twitter/X API |
| `news_coverage` | Crawler berita Indonesia (Kompas, Tempo, dll) |

### Data Economic Pillar (0–100 per metrik):
| Field | Cara Mendapatkan |
|-------|-----------------|
| `industry_adoption` | Input manual user/admin |
| `patents` | Crawler SIPO / Google Patents |
| `tech_transfer` | Input manual via form peneliti |
| `startup_spinoffs` | Input manual / data DIKTI |

### Rekomendasi flow data:
1. User mengisi data social/economic di profil Wizdam Sikola
2. Admin dapat memverifikasi dan menambahkan data dari crawler
3. Data disimpan di tabel `researcher_impact_inputs` di DB Wizdam Sikola
4. Saat memanggil `/api/v1/impact/calculate`, load dari DB dan kirim ke API

```sql
CREATE TABLE researcher_impact_inputs (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  orcid           VARCHAR(19) NOT NULL,
  input_type      ENUM('social', 'economic') NOT NULL,
  field_key       VARCHAR(50) NOT NULL,
  value           DECIMAL(5,2) NOT NULL,   -- 0.00 – 100.00
  source          ENUM('user_input', 'crawler', 'admin', 'api') DEFAULT 'user_input',
  verified        BOOLEAN DEFAULT FALSE,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY      uq_orcid_type_field (orcid, input_type, field_key)
);
```

---

## 6. Trend Analysis dan Policy Recommendation

### Trend Analysis (`POST /api/v1/trend/analyze`)

Gunakan `supplied_works` dari DB untuk menghindari fetch ke ORCID.  
Format minimal yang dibutuhkan dalam tiap item:

```json
{
  "title": "string — untuk sdg_evolution",
  "publication_year": 2023,
  "authors_string": "Nama1, Nama2 — untuk collaboration_network",
  "doi": "optional"
}
```

Simpan hasilnya di `analysis_history` untuk ditampilkan di dashboard tanpa re-compute.

### Policy Recommendation (`POST /api/v1/recommendation/policy`)

Kirim `research_landscape` dari DB Wizdam Sikola (hasil agregat analisis sebelumnya):

```php
$landscape = [
    'total_researchers'         => ResearcherProfile::count(),
    'total_publications'        => Work::count(),
    'dominant_sdgs'             => SdgStats::getDominant(3),
    'weak_sdgs'                 => SdgStats::getWeak(3),
    'strong_sdgs'               => SdgStats::getStrong(3),
    'avg_impact_score'          => ImpactScore::average('composite'),
    'collaboration_rate'        => CollabStats::getRate(),
    'international_collab_rate' => CollabStats::getInternationalRate(),
    'top_institutions'          => Institution::getTop(5),
];
```

Semakin lengkap `research_landscape`, semakin relevan rekomendasi yang dihasilkan.

---

## 7. Arsitektur yang Disarankan di Wizdam Sikola

```
Wizdam Sikola (Frontend + Backend PHP/Laravel)
│
├── Admin Panel
│   ├── User Management (generate/revoke API keys)
│   ├── Weight Configuration (simpan ke DB → kirim ke API)
│   ├── Crawler Management (trigger crawl social/economic data)
│   └── System Monitor (health check API)
│
├── Researcher Dashboard
│   ├── Input Social/Economic Data
│   ├── Trigger Impact Score Calculation (with progress bar)
│   ├── View SDG Analysis Results
│   ├── Trend Analysis Charts
│   └── Policy Recommendations
│
└── API Integration Layer
    ├── SangiaApiClient.php  (wrapper untuk semua call ke wizdam-apis)
    ├── WeightConfigService.php  (load config dari DB, sertakan di request)
    ├── BatchProcessor.php  (handle pola loop batch)
    └── RawDataPersister.php  (simpan raw_data ke tabel cache)
```

### SangiaApiClient.php (contoh skeleton):
```php
class SangiaApiClient {
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $apiKey) {
        $this->baseUrl = config('services.sangia.url');
        $this->apiKey  = $apiKey;
    }

    public function calculateImpact(string $orcid, array $extra = []): array {
        $weights  = WeightConfigService::getForImpact();

        // Cek apakah data sudah ada di DB
        $cache    = AuthorProfileCache::find($orcid);
        $supplied = $cache ? [
            'supplied_works'  => json_decode($cache->works_data, true),
            'supplied_person' => json_decode($cache->person_data, true),
            'supplied_scopus' => json_decode($cache->scopus_data, true),
        ] : [];

        $result = $this->batchPost('/api/v1/impact/calculate', array_merge([
            'orcid'   => $orcid,
            'weights' => $weights,
        ], $supplied, $extra));

        // Simpan raw_data ke DB jika ada (data baru dari API eksternal)
        if (isset($result['raw_data'])) {
            RawDataPersister::saveAuthorProfile($orcid, $result['raw_data']);
        }

        return $result;
    }

    private function batchPost(string $endpoint, array $payload): array {
        $offset = 0;
        do {
            $result = $this->post($endpoint, array_merge($payload, ['offset' => $offset]));
            if ($result['status'] === 'processing') {
                $offset = $result['next_offset'];
                usleep(300000);
            }
        } while (($result['status'] ?? '') === 'processing');
        return $result;
    }

    private function post(string $path, array $body): array { /* ... */ }
}
```

---

## 8. CORS

Tambahkan domain Wizdam Sikola ke `CORS_ALLOWED_ORIGINS` di `.env` wizdam-apis:
```
CORS_ALLOWED_ORIGINS=https://app.wizdam.id,https://admin.wizdam.id,http://localhost:3000
```

---

## 9. Monitoring & Logging

Wizdam Sikola sebaiknya menyimpan log setiap call ke wizdam-apis di DB:
```sql
CREATE TABLE api_call_logs (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT,
  endpoint      VARCHAR(100),
  params        JSON,
  status        VARCHAR(20),
  duration_ms   INT,
  data_source   VARCHAR(50),   -- 'wizdam_sikola_db' atau 'orcid_api' dll
  called_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Log `data_source` berguna untuk mengukur efisiensi: seberapa sering Wizdam Sikola berhasil supply data dari DB vs harus fetch ke API eksternal.

---

## 10. Versi SDG yang Direkomendasikan

| Versi | Rekomendasi Penggunaan |
|-------|----------------------|
| `v5` | **Default** — gunakan untuk semua analisis produksi |
| `v5e` | Eksperimental — untuk testing weight baru |
| `v4` | Jika ingin bobot substantive lebih tinggi |
| `v0` | Hanya untuk komparasi / benchmark keyword-only |

Sediakan dropdown di UI untuk memilih versi, dengan default `v5`.

---

## 11. Struktur Response Standard

Semua response API mengikuti pola:
```json
{
  "status": "success" | "error" | "processing",
  "data_source": "wizdam_sikola_db" | "orcid_api" | "scopus_api" | "external_apis",
  "cache_info": { "from_cache": false },
  "raw_data": { "...": "...", "fetched_at": "2025-01-01T00:00:00+00:00" },
  "api_version": "v1.1-batch"
}
```

- `data_source: "wizdam_sikola_db"` → Wizdam Sikola supply data, tidak ada fetch eksternal
- `data_source: "orcid_api"` → wizdam-apis fetch dari ORCID, simpan `raw_data` ke DB
- `raw_data` hanya ada saat `data_source` bukan `wizdam_sikola_db`

Selalu periksa `status` sebelum memproses data. Jika `"processing"`, lakukan loop batch.
