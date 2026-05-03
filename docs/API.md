# Sangia API Engine — Dokumentasi API

**Base URL:** `https://api.sangia.org`  
**Versi API:** v1  
**Autentikasi:** `X-API-Key: wz_{user_id}_{timestamp}_{hmac16}`

> API key dihasilkan oleh **Wizdam Sicola** dan divalidasi secara stateless menggunakan HMAC-SHA256.  
> Semua endpoint wajib menyertakan API key kecuali yang ditandai _(publik)_.

---

## Daftar Endpoint

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/health` | Publik | Status layanan |
| GET | `/api/v1` | Publik | Katalog endpoint |
| GET | `/api/v1/sdg/versions` | Publik | Daftar versi SDG + bobot default |
| POST | `/api/v1/sdg/{version}/classify` | API Key | Klasifikasi SDG |
| POST | `/api/v1/sdg/classify` | API Key | Alias v5 |
| GET | `/api/v1/scopus/author` | API Key | Profil author Scopus |
| GET | `/api/v1/orcid/profile` | API Key | Profil peneliti ORCID |
| GET | `/api/v1/citation/doi` | API Key | Sitasi multi-sumber |
| GET | `/api/v1/journal/metrics` | API Key | Metrik jurnal Scopus |
| GET | `/api/v1/sinta/score` | API Key | Skor jurnal SINTA |
| POST | `/api/v1/impact/calculate` | API Key | Wizdam Impact Score |
| POST | `/api/v1/trend/analyze` | API Key | Trend Analysis |
| POST | `/api/v1/recommendation/policy` | API Key | Policy Recommendations |
| POST | `/api/v1/admin/keys/revoke` | API Key | Cabut API key |

---

## Autentikasi

Kirim API key melalui salah satu cara:
```
X-API-Key: wz_42_1719000000_a3f8e2c1d5b7
Authorization: Bearer wz_42_1719000000_a3f8e2c1d5b7
?api_key=wz_42_1719000000_a3f8e2c1d5b7
```

**Format key:** `wz_{user_id}_{unix_timestamp}_{hmac16}`  
- `hmac16` = 16 karakter pertama dari `HMAC-SHA256(user_id:timestamp, WIZDAM_SHARED_SECRET)`  
- TTL: 1 tahun sejak `timestamp`

**Response 401 jika key tidak valid:**
```json
{ "status": "error", "code": 401, "message": "Invalid or expired API key." }
```

**Rate Limit:** 60 request/60 detik per API key (default). Dapat dikonfigurasi via env.  
Header response: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

---

## Arsitektur Data

wizdam-apis adalah **pure analysis engine** — tidak menyimpan hasil apapun secara permanen.  
Semua persistensi data adalah tanggung jawab **Wizdam Sicola**.

### Pola `supplied_data` — Kirim data dari DB Wizdam Sicola

Jika Wizdam Sicola sudah memiliki data di DB, kirimkan dalam request body.  
wizdam-apis akan menggunakan data tersebut **tanpa melakukan cURL ke API eksternal**.

```json
{
  "orcid": "0000-0002-1234-5678",
  "supplied_works": [
    {
      "title": "Solar Panel Adoption in Rural Java",
      "doi": "10.1234/example",
      "publication_year": 2023,
      "type": "journal-article",
      "journal_title": "Renewable Energy"
    }
  ],
  "supplied_person": {
    "name": "Budi Santoso",
    "given_names": "Budi",
    "family_name": "Santoso"
  },
  "supplied_scopus": {
    "h_index": 18,
    "document_count": 145,
    "citation_count": 3200
  }
}
```

Response saat data disupply: `"data_source": "wizdam_sicola_db"`

### Pola `raw_data` — Simpan hasil ke DB Wizdam Sicola

Ketika wizdam-apis mengambil data dari API eksternal (ORCID/Scopus/dll), response menyertakan field `raw_data` berisi data mentah beserta `fetched_at`.  
Wizdam Sicola harus menyimpan ini ke tabelnya (citations_cache, author_profiles_cache, dll).

```json
{
  "status": "success",
  "...",
  "raw_data": {
    "doi": "10.1234/example",
    "metadata": { "..." : "..." },
    "fetched_at": "2025-01-01T00:00:00+00:00"
  }
}
```

Response saat data diambil dari API eksternal: `"data_source": "orcid_api"` / `"external_apis"` / dll.

---

## Override Bobot Analisis

Semua endpoint SDG classify dan impact calculate menerima objek `weights` dalam request body.  
Bobot dari Wizdam Sicola admin panel **selalu prioritas**; nilai default dalam kode hanya fallback.

### SDG Classify — override bobot + threshold:
```json
{
  "title": "...",
  "weights": {
    "keyword": 0.25,
    "similarity": 0.30,
    "substantive": 0.25,
    "causal": 0.20,
    "max_sdgs": 5,
    "thresholds": {
      "min": 0.18,
      "confidence": 0.28,
      "high": 0.55
    }
  }
}
```

### Impact Calculate — override bobot komposit:
```json
{
  "orcid": "0000-0002-1234-5678",
  "weights": {
    "academic": 0.45,
    "social": 0.20,
    "economic": 0.20,
    "sdg": 0.15
  }
}
```

---

## Pola Batch Anti-Timeout

Endpoint yang memproses profil ORCID (banyak karya) menggunakan pola batch untuk menghindari PHP timeout.  
Client memanggil endpoint berulang kali dengan `next_offset` sampai mendapat `status: "success"`.

**Parameter:**
- `offset` (int, default `0`) — posisi mulai batch
- `batch_size` (int, default `20`, max `50`) — jumlah karya per request

**Contoh alur (JavaScript/Wizdam Sicola):**
```javascript
async function classifyWithBatch(orcid, endpoint) {
  let offset = 0;
  while (true) {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'X-API-Key': apiKey, 'Content-Type': 'application/json' },
      body: JSON.stringify({ orcid, offset, batch_size: 20 })
    });
    const data = await res.json();
    if (data.status === 'success') return data;
    if (data.status !== 'processing') throw new Error(data.message);
    updateProgressBar(data.progress.percent);
    offset = data.next_offset;
    await delay(300); // jeda 300ms antar batch
  }
}
```

**Response saat processing:**
```json
{
  "status": "processing",
  "orcid": "0000-0002-1234-5678",
  "progress": { "processed": 20, "total_works": 50, "percent": 40 },
  "next_offset": 20
}
```

---

## Endpoint Detail

---

### GET `/health`
Status layanan. Tidak memerlukan API key.

**Response:**
```json
{ "status": "up", "service": "Sangia API Engine", "time": "2025-01-01T00:00:00+00:00" }
```

---

### GET `/api/v1/sdg/versions`
Daftar versi SDG analyzer + bobot dan threshold default.

**Response:**
```json
{
  "status": "success",
  "data": {
    "v5": {
      "label": "Causal-boosted stable (v5.1.8)",
      "weights": { "keyword": 0.30, "similarity": 0.30, "substantive": 0.20, "causal": 0.20 },
      "thresholds": { "min": 0.20, "confidence": 0.30, "high": 0.60 }
    }
  }
}
```

---

### POST `/api/v1/sdg/{version}/classify`
Klasifikasi SDG dari teks, DOI, atau ORCID.  
`{version}` = `v0` | `v1` | `v2` | `v3` | `v4` | `v5` | `v5e`

**Request body:**
```json
{
  "title": "Renewable Energy Adoption in Rural Indonesia",
  "abstract": "This study examines...",
  "orcid": "0000-0002-1234-5678",
  "doi": "10.1234/example",
  "refresh": false,
  "offset": 0,
  "batch_size": 20,
  "weights": { "keyword": 0.30, "similarity": 0.30, "substantive": 0.20, "causal": 0.20 },
  "supplied_works": []
}
```
Gunakan **salah satu**: `title+abstract`, `doi`, atau `orcid`. Jika `orcid`, gunakan pola batch.  
`supplied_works` — opsional, kirim data karya dari DB Wizdam Sicola untuk skip fetch ORCID.

**Response (title+abstract):**
```json
{
  "status": "success",
  "version": "v5",
  "weights_applied": { "keyword": 0.30, "...": "..." },
  "sdg_analysis": {
    "sdgs": ["SDG7", "SDG13"],
    "sdg_confidence": { "SDG7": 0.724, "SDG13": 0.611 },
    "contributor_types": { "SDG7": "Active Contributor" },
    "detailed_analysis": {
      "SDG7": {
        "score": 0.724,
        "confidence_level": "High",
        "components": { "keyword_score": 0.65, "similarity_score": 0.71, "substantive_score": 0.80, "causal_score": 0.75 }
      }
    }
  }
}
```

---

### GET `/api/v1/scopus/author`
Profil author dan daftar publikasi dari Scopus API.

**Query params:** `authorid` (required), `count` (1–25, default 10), `refresh` (bool)

**Request body (opsional — supplied data):**
```json
{
  "supplied_scopus": {
    "author": { "full_name": "Budi Santoso", "h_index": 18, "citation_count": 3200 },
    "publications": []
  }
}
```

**Response:**
```json
{
  "status": "success",
  "author_id": "57200000000",
  "author": {
    "full_name": "Budi Santoso",
    "affiliation": "Universitas Indonesia",
    "h_index": 18,
    "document_count": 145,
    "citation_count": 3200,
    "data_source": "scopus"
  },
  "publications": [ { "doi": "10.1234/example", "title": "...", "year": 2023, "cited_by_count": 42 } ],
  "data_source": "scopus_api",
  "raw_data": { "author": {}, "publications": [], "fetched_at": "2025-01-01T00:00:00+00:00" },
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/orcid/profile`
Profil peneliti lengkap dari ORCID public API.

**Query params:** `orcid` (required, format `0000-0000-0000-0000`), `refresh` (bool), `limit` (default 50)

**Request body (opsional — supplied data):**
```json
{
  "supplied_works": [ { "title": "...", "doi": "...", "publication_year": 2023 } ],
  "supplied_person": { "name": "Budi Santoso", "given_names": "Budi", "family_name": "Santoso" }
}
```

**Response:**
```json
{
  "status": "success",
  "orcid": "0000-0002-1234-5678",
  "person_summary": {
    "name": "Budi Santoso",
    "emails": ["budi@ui.ac.id"],
    "keywords": ["renewable energy", "SDG"],
    "country": "ID"
  },
  "works": [ { "title": "Solar Panel Adoption in Rural Java", "doi": "10.1234/example", "publication_year": 2023 } ],
  "works_count": 87,
  "data_source": "orcid_api",
  "raw_data": { "person": {}, "works": [], "fetched_at": "2025-01-01T00:00:00+00:00" },
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/citation/doi`
Data sitasi multi-sumber untuk sebuah DOI.

**Query params:** `doi` (required), `limit` (1–50, default 15), `refresh` (bool)

**Sumber:** OpenCitations → Crossref → OpenAlex → Semantic Scholar

**Response:**
```json
{
  "status": "success",
  "doi": "10.1234/example",
  "article_metadata": { "title": "...", "authors": ["Budi Santoso"], "publication_year": 2023, "is_referenced_by": 42 },
  "citations": {
    "opencitations": [ { "citing_doi": "10.5678/...", "source": "opencitations" } ],
    "crossref": [],
    "openalex": [],
    "semantic_scholar": []
  },
  "citation_count": { "opencitations": 12, "crossref": 0, "openalex": 8, "semantic_scholar": 6 },
  "total_unique": 18,
  "data_source": "external_apis",
  "raw_data": { "doi": "10.1234/example", "metadata": {}, "counts": {}, "fetched_at": "2025-01-01T00:00:00+00:00" },
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/journal/metrics`
Metrik jurnal dari Scopus Serial Title API.

**Query params:** `issn` (required, format `XXXX-XXXX`), `refresh` (bool)

**Response:**
```json
{
  "status": "success",
  "journal": {
    "title": "Renewable Energy",
    "issn_print": "0960-1481",
    "publisher": "Elsevier",
    "open_access": false,
    "active": true
  },
  "metrics": {
    "citescore": 13.2,
    "sjr": 1.845,
    "snip": 2.11,
    "quartile": "Q1",
    "subject_areas": [ { "name": "Renewable Energy", "code": "2105", "quartile": "Q1" } ]
  },
  "raw_data": { "issn": "0960-1481", "metrics": {}, "fetched_at": "2025-01-01T00:00:00+00:00" },
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/sinta/score`
Skor dan grade jurnal dari SINTA (Kemenristekdikti).

**Query params:** `issn` (required), `refresh` (bool)

**Response:**
```json
{
  "status": "success",
  "issn": "2549-1385",
  "title": "Jurnal Energi Terbarukan Indonesia",
  "impact": "2.45",
  "grade": "S2",
  "sinta_id": "123456",
  "sinta_url": "https://sinta.kemdiktisaintek.go.id/journals/profile/123456",
  "raw_data": { "issn": "2549-1385", "sinta": {}, "fetched_at": "2025-01-01T00:00:00+00:00" },
  "cache_info": { "from_cache": false }
}
```

---

### POST `/api/v1/impact/calculate`
Hitung Wizdam Impact Score (komposit 4 pilar). Mendukung pola batch dan supplied data.

**Request body:**
```json
{
  "orcid": "0000-0002-1234-5678",
  "scopus_id": "57200000000",
  "social": {
    "media_mentions": 75,
    "policy_citations": 60,
    "social_shares": 80,
    "news_coverage": 50
  },
  "economic": {
    "industry_adoption": 40,
    "patents": 20,
    "tech_transfer": 35,
    "startup_spinoffs": 10
  },
  "weights": { "academic": 0.40, "social": 0.25, "economic": 0.20, "sdg": 0.15 },
  "refresh": false,
  "offset": 0,
  "batch_size": 20,
  "supplied_works": [],
  "supplied_person": null,
  "supplied_scopus": null
}
```

**Response (final):**
```json
{
  "status": "success",
  "orcid": "0000-0002-1234-5678",
  "name": "Budi Santoso",
  "composite": 68.45,
  "pillars": { "academic": 82.10, "social": 66.25, "economic": 26.25, "sdg": 54.80 },
  "weights": { "academic": 0.40, "social": 0.25, "economic": 0.20, "sdg": 0.15 },
  "sdg_tags": [ { "sdg": 7, "code": "SDG7", "score": 0.724, "count": 12 } ],
  "academic_metrics": { "publication_count": 87, "h_index": 18, "citation_count": 3200 },
  "data_sources": { "orcid": "orcid_api", "scopus": "scopus_api" },
  "raw_data": { "orcid_person": {}, "orcid_works": [], "scopus": {}, "fetched_at": "2025-01-01T00:00:00+00:00" },
  "api_version": "v1.1-batch",
  "calculated_at": "2025-01-01T00:00:00+00:00",
  "cache_info": { "from_cache": false }
}
```

**Formula:**
```
Composite = Academic×w_academic + Social×w_social + Economic×w_economic + SDG×w_sdg

Academic  = (min(100, h_index×3.5) × 0.45) + (log10(citations+1)×25 × 0.35) + (min(100, pub_count×1.2) × 0.20)
Social    = avg(media_mentions, policy_citations, social_shares, news_coverage)  [0–100 each]
Economic  = avg(industry_adoption, patents, tech_transfer, startup_spinoffs)     [0–100 each]
SDG       = (coverage_ratio×0.4 + avg_confidence×0.6) × 100
            coverage_ratio = min(1.0, distinct_sdg_count / 5)
```

---

### POST `/api/v1/trend/analyze`
Analisis tren berdasarkan data karya peneliti.

**Request body:**
```json
{
  "orcid": "0000-0002-1234-5678",
  "analysis_type": "impact_trajectory",
  "time_range": "5y",
  "scopus_id": "57200000000",
  "refresh": false,
  "supplied_works": [
    {
      "title": "Solar Panel Adoption in Rural Java",
      "doi": "10.1234/example",
      "publication_year": 2023,
      "authors_string": "Budi Santoso, Ani Wijaya"
    }
  ],
  "supplied_scopus": null
}
```

**Tipe analisis (`analysis_type`):**

| Nilai | Keterangan | Data yang Dibutuhkan |
|-------|-----------|---------------------|
| `impact_trajectory` | Tren jumlah publikasi & sitasi per tahun | `supplied_works` dengan `publication_year` |
| `sdg_evolution` | Evolusi distribusi SDG per tahun | `supplied_works` dengan `title` + `publication_year` |
| `collaboration_network` | Jaringan co-author | `supplied_works` dengan `authors_string` atau `contributors` |
| `citation_growth` | Tren pertumbuhan sitasi dari Scopus | `scopus_id` wajib + `supplied_scopus` opsional |

**Parameter `time_range`:** `1y` | `3y` | `5y` | `10y` | `all`

**Response (`impact_trajectory`):**
```json
{
  "status": "success",
  "orcid": "0000-0002-1234-5678",
  "analysis_type": "impact_trajectory",
  "time_range": "5y",
  "data": {
    "yearly_publications": { "2019": 8, "2020": 12, "2021": 15, "2022": 18, "2023": 22 },
    "growth_trend": "increasing",
    "growth_rate_percent": 175.0,
    "peak_year": 2023,
    "total_works_analyzed": 75
  },
  "data_source": "wizdam_sicola_db",
  "api_version": "v1.0-trend"
}
```

**Response (`sdg_evolution`):**
```json
{
  "status": "success",
  "analysis_type": "sdg_evolution",
  "data": {
    "sdg_by_year": {
      "2021": { "SDG7": 5, "SDG13": 3 },
      "2022": { "SDG7": 8, "SDG9": 2 },
      "2023": { "SDG7": 10, "SDG13": 6, "SDG9": 4 }
    },
    "dominant_sdg": "SDG7",
    "emerging_sdgs": ["SDG9"],
    "total_works_analyzed": 75
  }
}
```

---

### POST `/api/v1/recommendation/policy`
Rekomendasi kebijakan berbasis data riset.

**Request body:**
```json
{
  "stakeholder_type": "government",
  "domain": "sdg_achievement",
  "time_horizon": "medium_term",
  "region": "Indonesia",
  "research_landscape": {
    "total_researchers": 1250,
    "total_publications": 8900,
    "dominant_sdgs": ["SDG4", "SDG7", "SDG13"],
    "weak_sdgs": ["SDG14", "SDG15"],
    "strong_sdgs": ["SDG4", "SDG7"],
    "avg_impact_score": 62.5,
    "collaboration_rate": 0.68,
    "international_collab_rate": 0.23,
    "top_institutions": ["Universitas Indonesia", "ITB"]
  }
}
```

**Tipe stakeholder (`stakeholder_type`):** `government` | `institution` | `industry` | `researcher` | `community`

**Response:**
```json
{
  "status": "success",
  "stakeholder_type": "government",
  "domain": "sdg_achievement",
  "region": "Indonesia",
  "time_horizon_key": "medium_term",
  "context_summary": {
    "data_driven": true,
    "researchers": 1250,
    "strong_sdgs": ["SDG4", "SDG7"],
    "weak_sdgs": ["SDG14", "SDG15"],
    "region": "Indonesia"
  },
  "recommendations": [
    {
      "id": "GOV-01",
      "priority": "high",
      "category": "infrastructure",
      "target_sdgs": ["SDG4", "SDG9", "SDG17"],
      "activity_keys": ["modernize_research_labs", "expand_digital_library", "build_hpc_center"],
      "expected_impact": { "research_capacity_increase": "40%", "international_collaboration_growth": "60%" },
      "time_horizon_key": "medium_term",
      "implementation": {
        "horizon_key": "medium_term",
        "steps": [
          { "phase": "phase_1", "activity_key": "modernize_research_labs" },
          { "phase": "phase_2", "activity_key": "expand_digital_library" },
          { "phase": "phase_3", "activity_key": "build_hpc_center" }
        ]
      },
      "success_metrics": {
        "tracking_period": "annual",
        "review_mechanism": "periodic_committee_review",
        "targets": { "research_capacity_increase": "40%" }
      }
    }
  ],
  "priority_matrix": {
    "high_priority": ["GOV-01", "GOV-02", "GOV-04"],
    "medium_priority": ["GOV-03"],
    "low_priority": [],
    "total": 4
  },
  "data_driven": true,
  "api_version": "v1.0-recommendation"
}
```

---

### POST `/api/v1/admin/keys/revoke`
Cabut API key (hanya untuk panggilan dari backend Wizdam Sicola).

**Request body:**
```json
{ "key": "wz_42_1719000000_a3f8e2c1d5b7" }
```

**Response:**
```json
{ "status": "success", "message": "Key revoked" }
```

---

## Error Responses

| HTTP Code | Keterangan |
|-----------|------------|
| 400 | Parameter tidak valid / kurang |
| 401 | API key tidak ada atau tidak valid |
| 404 | Data tidak ditemukan |
| 410 | Batch session expired — restart dari offset=0 |
| 429 | Rate limit exceeded |
| 500 | Internal server error |
| 502 | External API error (ORCID/Scopus/Crossref tidak merespons) |
| 503 | API key eksternal (Scopus) tidak dikonfigurasi |

**Format error:**
```json
{ "status": "error", "code": 400, "message": "orcid is required" }
```
