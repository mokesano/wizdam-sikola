# Formula, Bobot, dan Metodologi Wizdam Sicola

## Wizdam Impact Score (WIS)

Wizdam Impact Score adalah skor komposit 0–100 yang mengukur dampak nyata seorang peneliti atau karya riset di empat dimensi:

```
WIS = (Academic × 40%) + (Social × 25%) + (Economic × 20%) + (SDG × 15%)
```

### Komponen 4 Pilar

| Pilar | Bobot Default | Sumber Data |
|-------|---------------|-------------|
| Academic | 40% | Sitasi, h-index, i10-index (ORCID + Scopus) |
| Social | 25% | Media mentions, policy citations, public engagement |
| Economic | 20% | Adopsi industri, paten yang mengutip, kolaborasi industry |
| SDG | 15% | Keterkaitan dengan 17 SDG PBB (oleh Sangia AI) |

**Catatan**: Bobot dapat dikonfigurasi melalui Admin Panel → Konfigurasi Bobot. Nilai disimpan di tabel `analysis_weight_configs` (key: `impact_composite`).

---

## Pilar Academic (Skor 0–100)

Dihitung oleh Sangia API Engine berdasarkan:

| Metrik | Deskripsi |
|--------|-----------|
| **h-index** | Jumlah h karya dengan minimal h sitasi |
| **i10-index** | Jumlah karya dengan minimal 10 sitasi |
| **total_citations** | Total sitasi dari semua karya |
| **citation_velocity** | Kecepatan pertumbuhan sitasi (sitasi/tahun) |
| **recent_citations** | Bobot lebih tinggi untuk sitasi 5 tahun terakhir |
| **co-author_network** | Luas jaringan kolaborasi internasional |

Formula internal (Sangia):
```
academic_raw = normalize(
  h_index       × 0.30 +
  citations_5y  × 0.35 +
  citation_vel  × 0.20 +
  i10_index     × 0.15
)
Academic = scale(academic_raw, 0, 100)
```

---

## Pilar Social (Skor 0–100)

Dimasukkan secara manual atau via crawler untuk masing-masing peneliti:

| Field | Rentang | Deskripsi |
|-------|---------|-----------|
| `media_mentions` | 0–100 | Penyebutan di media berita/blog |
| `policy_citations` | 0–100 | Dikutip dalam dokumen kebijakan pemerintah |
| `public_engagement` | 0–100 | Keterlibatan publik (seminar, podcast, dll.) |
| `social_media_impact` | 0–100 | Dampak di media sosial akademik (ResearchGate, Academia) |

Formula:
```
Social = (media_mentions × w1) + (policy_citations × w2) + (public_engagement × w3) + ...
```
Bobot `w1..wN` dikonfigurasi via Sangia API dengan `WeightConfigService`.

---

## Pilar Economic (Skor 0–100)

| Field | Rentang | Deskripsi |
|-------|---------|-----------|
| `industry_adoption` | 0–100 | Adopsi hasil riset oleh industri |
| `patents` | 0–100 | Paten yang mengutip atau berbasis karya peneliti |
| `startup_founded` | 0–100 | Startup yang lahir dari riset |
| `tech_transfer` | 0–100 | Transfer teknologi ke mitra industri |

---

## Pilar SDG (Skor 0–100)

SDG score dihitung dari derajat keterkaitan seluruh karya peneliti dengan 17 Tujuan Pembangunan Berkelanjutan PBB.

```
SDG_score = mean(confidence_score_per_sdg × relevance_weight)
```

Konfigurasi bobot SDG scoring (key: `sdg_v5`):

| Parameter | Default | Keterangan |
|-----------|---------|------------|
| `keyword` | 0.30 | Bobot pencocokan kata kunci SDG |
| `similarity` | 0.30 | Bobot kesamaan semantik (embedding) |
| `substantive` | 0.20 | Bobot analisis substantif konteks |
| `causal` | 0.20 | Bobot hubungan kausal dengan SDG |
| `max_sdgs` | 7 | Maksimum jumlah SDG yang diatribusikan |
| `threshold.min` | 0.20 | Skor minimum untuk dikategorikan relevan |
| `threshold.confidence` | 0.30 | Skor minimum untuk ditampilkan |
| `threshold.high` | 0.60 | Skor untuk label "Sangat Relevan" |

---

## SDG Classification — 17 Tujuan

| No | Kode | Label | Warna UN |
|----|------|-------|----------|
| 1 | SDG1 | Tanpa Kemiskinan | #E5243B |
| 2 | SDG2 | Tanpa Kelaparan | #DDA63A |
| 3 | SDG3 | Kehidupan Sehat dan Sejahtera | #4C9F38 |
| 4 | SDG4 | Pendidikan Berkualitas | #C5192D |
| 5 | SDG5 | Kesetaraan Gender | #FF3A21 |
| 6 | SDG6 | Air Bersih dan Sanitasi | #26BDE2 |
| 7 | SDG7 | Energi Bersih dan Terjangkau | #FCC30B |
| 8 | SDG8 | Pekerjaan Layak dan Pertumbuhan Ekonomi | #A21942 |
| 9 | SDG9 | Industri, Inovasi, dan Infrastruktur | #FD6925 |
| 10 | SDG10 | Berkurangnya Kesenjangan | #DD1367 |
| 11 | SDG11 | Kota dan Komunitas Berkelanjutan | #FD9D24 |
| 12 | SDG12 | Konsumsi dan Produksi yang Bertanggung Jawab | #BF8B2E |
| 13 | SDG13 | Penanganan Perubahan Iklim | #3F7E44 |
| 14 | SDG14 | Ekosistem Lautan | #0A97D9 |
| 15 | SDG15 | Ekosistem Daratan | #56C02B |
| 16 | SDG16 | Perdamaian, Keadilan, dan Kelembagaan yang Tangguh | #00689D |
| 17 | SDG17 | Kemitraan untuk Mencapai Tujuan | #19486A |

---

## API Key — Format HMAC

API Key yang digenerate untuk pengguna mengikuti format:

```
wz_{user_id}_{unix_timestamp}_{hmac16}
```

**Keterangan**:
- `user_id` — ID pengguna di tabel `users`
- `unix_timestamp` — Waktu pembuatan key (Unix epoch)
- `hmac16` — 16 karakter pertama dari `HMAC-SHA256("{user_id}:{timestamp}", WIZDAM_SHARED_SECRET)`

**Validasi**:
```
TTL = 365 hari sejak timestamp
Valid jika: timestamp + 365×86400 > now() AND HMAC cocok
```

**Contoh**:
```
wz_42_1717200000_a3f8c2e1b4d5f678
```

`WIZDAM_SHARED_SECRET` harus sama antara wizdam-sicola dan wizdam-apis untuk validasi silang.

---

## Supplied Data Pattern

Untuk menghemat kuota API dan mengurangi latensi, Wizdam Sicola mengirimkan data yang sudah ada di database ke Sangia API (`supplied_data`), sehingga Sangia tidak perlu fetch ulang dari ORCID/Scopus.

```
IF author_profiles_cache EXISTS FOR orcid:
    kirim supplied_works, supplied_person, supplied_scopus ke Sangia
    → Sangia skip external fetch
    → data_source = 'wizdam_sicola_db'
ELSE:
    Sangia fetch dari ORCID/Scopus
    → simpan raw_data ke author_profiles_cache
    → data_source = 'orcid_api' / 'scopus_api'
```

---

## Batch Anti-Timeout Pattern

Untuk perhitungan yang panjang (banyak karya), Sangia API menggunakan pola batch:

```
offset = 0
LOOP:
    POST /api/v1/impact/calculate {orcid, offset, batch_size: 20}
    IF response.status == 'processing':
        offset = response.next_offset
        sleep(300ms)
        CONTINUE
    IF HTTP 410 (session expired):
        offset = 0
        retry (max 3x)
        CONTINUE
    IF response.status == 'success':
        RETURN result
```

---

## WizdamCrawler — Prioritas dan Rate Limit

| Sumber | Rate Limit | Delay | Catatan |
|--------|------------|-------|---------|
| Google Scholar | Tidak ada API resmi | 3–5 detik | CAPTCHA kemungkinan muncul |
| ResearchGate | Tidak ada API resmi | 3 detik | JavaScript-heavy |
| Crossref | 50 req/detik (tanpa key) | 1 detik | Direkomendasikan |
| Semantic Scholar | 100 req/5 menit | 1 detik | Gunakan API key untuk lebih banyak |
| OpenCitations | Tidak dibatasi | 1 detik | Gratis dan publik |
| Scimago | Tidak ada API resmi | 2 detik | Data terbuka |
| SINTA | Tidak ada API resmi | 3 detik | Gunakan via Sangia API |
