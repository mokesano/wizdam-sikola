# Panduan Halaman dan Menu Wizdam AI-Sikola

## Arsitektur Rendering

Wizdam AI-Sikola menggunakan dua model rendering:

| Model | Teknologi | Digunakan Untuk |
|-------|-----------|-----------------|
| **Server-Side (Twig)** | PHP + Twig templates | Halaman publik, auth, dashboard — SEO-friendly, cepat dimuat |
| **Client-Side (React SPA)** | React + Vite + React Router | Halaman interaktif dengan grafik, peta, analisis dinamis |

---

## Halaman Publik (Twig)

### `/` — Beranda / Daftar Peneliti

**Tujuan**: Pintu masuk utama. Menampilkan peneliti Indonesia dengan Wizdam Impact Score tertinggi.

**Konten**:
- Bar pencarian nama peneliti
- Filter berdasarkan bidang riset (Teknik, Kesehatan, Sosial, dll.)
- Tabel/grid peneliti diurutkan berdasarkan skor dampak tertinggi
- Rata-rata 4 pilar dampak nasional

**SEO**: Halaman ini di-render penuh oleh PHP sehingga dapat diindeks oleh mesin pencari.

---

### `/researcher/{orcid}` — Profil Peneliti

**Tujuan**: Halaman detail seorang peneliti berdasarkan ORCID ID.

**Konten**:
- Avatar, nama lengkap, jabatan, institusi
- Badge ORCID, SINTA ID, h-index, total sitasi
- **Wizdam Impact Score** (skor komposit + 4 pilar)
- Grafik tren skor 12 bulan terakhir
- Diagram breakdown 4 pilar (persentase)
- Tag SDG yang relevan dengan riset peneliti
- Tabel 10 artikel terbaru (judul, jurnal, tahun, sitasi, skor)

**Perilaku Khusus**: Jika ORCID belum ada di database, sistem otomatis menarik profil dari ORCID API dan menyimpannya.

---

### `/institution/{id}` — Profil Institusi

**Tujuan**: Halaman detail institusi penelitian (universitas, lembaga riset, dll.).

**Konten**:
- Nama, tipe, lokasi (provinsi/kota)
- Statistik agregat: jumlah peneliti, jumlah artikel, rata-rata skor dampak
- Daftar peneliti utama dari institusi tersebut
- Grafik produksi riset per tahun

---

### `/journal/{issn}` — Profil Jurnal

**Tujuan**: Halaman detail jurnal ilmiah berdasarkan ISSN.

**Konten**:
- Nama jurnal, publisher, ISSN
- Status indeksasi: Scopus, SINTA, WoS
- Metrik: CiteScore, SJR, SNIP, kuartil
- Grade SINTA (Q1–Q4 atau S1–S6)
- Daftar artikel Wizdam yang terbit di jurnal ini

---

### `/tools/image-resizer` — Pengubah Ukuran Gambar

**Tujuan**: Tool gratis untuk mengubah ukuran gambar.

**Konten**:
- Upload gambar (JPG, PNG, max 10 MB)
- Atur lebar × tinggi target
- Pilihan format output
- Tombol download hasil

---

### `/tools/pdf-compress` — Kompres PDF

**Tujuan**: Tool gratis untuk mengompres ukuran file PDF.

**Konten**:
- Upload PDF (max 50 MB)
- Pilihan kualitas kompresi
- Preview ukuran sebelum/sesudah
- Tombol download hasil

---

## Halaman Autentikasi

### `/auth/login` — Masuk

**Tujuan**: Login menggunakan ORCID OAuth2.

**Alur**:
1. User klik "Masuk dengan ORCID"
2. Redirect ke `orcid.org` untuk otorisasi
3. Callback ke `/auth/orcid-callback`
4. Jika berhasil: redirect ke `/dashboard`

**Catatan**: Login berbasis ORCID menjamin identitas peneliti yang terverifikasi.

---

### `/auth/logout` — Keluar

Menghapus sesi dan redirect ke beranda.

---

## Halaman Privat (Login Diperlukan)

### `/dashboard` — Dashboard Peneliti

**Tujuan**: Halaman pribadi peneliti yang sudah login.

**Konten**:
- Profil lengkap (terhubung dengan ORCID)
- **Wizdam Impact Score saya** — skor komposit dan 4 pilar
- Riwayat skor (tren)
- Artikel saya yang tercatat
- **Manajemen API Key**:
  - Lihat key aktif
  - Generate key baru
  - Salin key ke clipboard
  - Cabut key

---

## Halaman Admin (Role Admin Diperlukan)

### `/admin` — Panel Admin

**Tujuan**: Pengelolaan platform oleh administrator.

**Menu**:

| Sub-menu | Fungsi |
|----------|--------|
| Analytics | Statistik global platform: total pengguna, total artikel, distribusi skor |
| Konfigurasi Bobot | Atur bobot 4 pilar dan bobot SDG scoring (disimpan ke DB) |
| Manajemen Pengguna | Lihat, nonaktifkan, ubah role pengguna |
| Log API | Pantau penggunaan Sangia API per user, endpoint, durasi, sumber data |

---

## React SPA Dinamis (`/app/*`)

Semua halaman di bawah `/app` dilayani oleh shell Twig yang memuat bundle React. Navigasi antar halaman dilakukan di sisi client (tanpa reload penuh).

### `/app` — Dashboard Interaktif

**Konten**:
- Statistik ringkasan (total peneliti, artikel, institusi, rata-rata skor)
- Grafik Recharts: tren publikasi per tahun, distribusi skor, top SDG
- Kartu peneliti teratas dengan skor real-time

### `/app/researchers` — Daftar Peneliti

**Konten**:
- Tabel peneliti lengkap (pagination 20/halaman)
- Filter: bidang, institusi, rentang skor
- Sorting: nama, skor, h-index, sitasi
- Klik baris → halaman profil Twig (`/researcher/{orcid}`)

### `/app/researcher-map` — Peta Persebaran Peneliti

**Konten**:
- Peta Indonesia interaktif (Leaflet.js + React-Leaflet)
- Marker institusi dengan jumlah peneliti
- Klik marker → popup daftar peneliti
- Filter berdasarkan provinsi atau bidang
- Heatmap densitas peneliti

### `/app/article-impact` — Analisis Dampak Artikel

**Konten**:
- Form input judul + abstrak artikel
- Tombol "Klasifikasikan SDG" → kirim ke `POST /api/v1/sdg/classify`
- Hasil: tag SDG dengan skor kepercayaan dan warna
- Grafik Recharts: distribusi skor SDG

### `/app/trends` — Analisis Tren

**Konten**:
- Grafik tren publikasi per tahun (area chart)
- Tren pertumbuhan sitasi (line chart)
- SDG evolution: pergeseran fokus riset per tahun
- Filter: per institusi, per bidang, per SDG

---

## API Endpoints (untuk Integrasi)

### REST API v1 — `/api/v1/`

Semua endpoint mengembalikan JSON dan mendukung CORS.

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/stats` | GET | Ringkasan statistik platform |
| `/api/v1/researchers` | GET | Daftar peneliti (query: `q`, `field`, `page`, `limit`) |
| `/api/v1/researchers/top` | GET | Top 10 peneliti berdasarkan skor |
| `/api/v1/researchers/{orcid}` | GET | Detail profil peneliti |
| `/api/v1/articles` | GET | Daftar artikel (query: `q`, `year`, `page`) |
| `/api/v1/articles/top` | GET | Top 10 artikel berdasarkan skor |
| `/api/v1/articles/trends` | GET | Data tren tahunan |
| `/api/v1/articles/{id}` | GET | Detail artikel |
| `/api/v1/institutions` | GET | Daftar institusi |
| `/api/v1/institutions/map` | GET | Data koordinat untuk peta |
| `/api/v1/institutions/{id}` | GET | Detail institusi |
| `/api/v1/impact-scores/{type}/{id}` | GET | Skor terkini |
| `/api/v1/impact-scores/{type}/{id}/calculate` | POST | Trigger kalkulasi ulang |
| `/api/v1/impact-scores/{type}/{id}/history` | GET | Riwayat skor (default 12 bulan) |
| `/api/v1/impact-scores/averages/{type}` | GET | Rata-rata pilar |
| `/api/v1/sdg/classify` | POST | Klasifikasi SDG dari teks (body: `{title, abstract}`) |
| `/api/crawler` | POST | Webhook untuk WizdamCrawler |

**Format Response**:
```json
{
  "success": true,
  "data": { ... },
  "meta": { "page": 1, "total": 250 }
}
```
