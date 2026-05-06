# FAQ — Pertanyaan yang Sering Diajukan

## Umum

**Q: Apa itu Wizdam Scola?**  
A: Platform analisis dampak penelitian Indonesia yang mengukur kontribusi peneliti, artikel, institusi, dan jurnal menggunakan Wizdam Impact Score (WIS) — skor komposit dari 4 pilar: Akademik, Sosial, Ekonomi, dan SDG.

**Q: Apa itu Sangia AI Engine?**  
A: Sangia adalah backend analitik AI (`api.sangia.org`) yang melakukan kalkulasi berat: SDG classification, impact score, trend analysis, dan policy recommendation. Sangia tidak menyimpan data — semua persistensi dilakukan oleh Wizdam Scola. Sangia Engine dikelola terpisah di repository `wizdam-apis`.

**Q: Mengapa ada dua server (PHP port 8000 dan Node port 3000)?**  
A: Saat development, PHP melayani halaman Twig dan REST API, sementara Vite dev server melayani React frontend dengan Hot Module Replacement (HMR). Di production, hanya PHP yang berjalan — React di-build menjadi file statis di `public/app/`.

---

## Autentikasi

**Q: Mengapa login hanya via ORCID?**  
A: ORCID adalah identitas peneliti terverifikasi internasional. Login via ORCID memastikan hanya peneliti nyata yang bisa mengklaim profil mereka di platform.

**Q: Bagaimana jika saya belum punya ORCID?**  
A: Daftar gratis di [orcid.org](https://orcid.org/register). ORCID ID adalah standar identifikasi peneliti global yang diakui lebih dari 1.000 lembaga riset.

**Q: Apakah admin bisa login tanpa ORCID?**  
A: Tidak. Semua akun menggunakan ORCID. Role admin ditetapkan di database (`users.role = 'admin'`) setelah akun dibuat pertama kali.

---

## API Key

**Q: Untuk apa API Key?**  
A: API Key digunakan untuk memanggil Sangia API Engine secara langsung dari aplikasi lain atau skrip analitik. Format key: `wz_{user_id}_{timestamp}_{hmac16}`.

**Q: Berapa lama API Key berlaku?**  
A: 365 hari sejak dibuat. Setelah expired, generate key baru melalui Dashboard → Manajemen API Key.

**Q: Bagaimana jika API Key saya bocor?**  
A: Segera klik "Cabut API Key" di Dashboard. Key akan langsung di-blacklist di Sangia API Engine dan di-null-kan di database.

**Q: Kenapa WIZDAM_SHARED_SECRET harus sama antara Scola dan APIs?**  
A: Sangia API memvalidasi HMAC key secara lokal tanpa roundtrip ke database. Agar validasi berhasil di kedua sisi, secret harus identik.

---

## Wizdam Impact Score

**Q: Apakah skor WIS bisa berubah?**  
A: Ya. Skor direkam setiap kalkulasi (tidak di-update, melainkan di-insert baru). History skor tersimpan untuk grafik tren. Pemicunya bisa manual (tombol "Hitung Ulang") atau otomatis via job queue.

**Q: Mengapa skor saya lebih rendah dari yang diharapkan?**  
A: Beberapa kemungkinan:
- Data sitasi belum ter-update di Scopus/ORCID (tunggu 24–48 jam)
- Pilar Social dan Economic membutuhkan input manual yang belum diisi
- Karya di luar ORCID tidak terhitung (tambahkan karya di profil ORCID Anda)

**Q: Bisakah saya mengubah bobot 4 pilar?**  
A: Ya, melalui Admin Panel → Konfigurasi Bobot. Perubahan tersimpan di `analysis_weight_configs` dan langsung diterapkan pada kalkulasi berikutnya.

**Q: Apakah WIS sama dengan h-index?**  
A: Tidak. h-index hanya mengukur dampak akademik (sitasi). WIS mengukur 4 dimensi: akademik, sosial, ekonomi, dan kontribusi terhadap SDG. Seorang peneliti bisa punya h-index rendah tapi WIS tinggi jika risetnya berdampak sosial-ekonomi besar.

---

## SDG Classification

**Q: Bagaimana cara kerja klasifikasi SDG?**  
A: Sangia menganalisis judul dan abstrak artikel menggunakan 4 pendekatan:
1. Pencocokan kata kunci SDG (bobot 30%)
2. Kesamaan semantik dengan embedding SDG (30%)
3. Analisis substantif konteks (20%)
4. Hubungan kausal dengan target SDG (20%)

**Q: Berapa maksimum SDG yang bisa diatribusikan ke satu artikel?**  
A: Default 7 SDG. Nilai ini dapat dikonfigurasi oleh admin melalui bobot `sdg_v5.max_sdgs`.

**Q: Apakah SDG v5 berbeda dengan versi sebelumnya?**  
A: Ya. Sangia mendukung beberapa versi model SDG (v4, v5). v5 menggunakan model embedding yang lebih baru dengan akurasi lebih tinggi pada teks berbahasa Indonesia.

---

## Database dan Migrasi

**Q: Apa perbedaan `database_schema_full.sql` dan `database_migration_v2.sql`?**  
A:
- `database_schema_full.sql` — skema lengkap dari awal (buat dari nol)
- `database_migration_v2.sql` — tambahan tabel untuk integrasi Sangia API v2 (cache, weight configs, API logs). Jalankan setelah schema_full.

**Q: Apakah aman menjalankan `database_migration_v2.sql` berulang kali?**  
A: Ya. Semua pernyataan menggunakan `CREATE TABLE IF NOT EXISTS` dan `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`, jadi aman dijalankan ulang.

---

## Frontend React

**Q: Mengapa menggunakan Vite bukan Create React App (CRA)?**  
A: Vite jauh lebih cepat: HMR (hot reload) hampir instan vs 10–30 detik di CRA. Build produksi juga lebih kecil karena menggunakan Rollup dengan tree-shaking agresif.

**Q: Apa itu `window.__WIZDAM_INIT__`?**  
A: Objek yang diinjeksi oleh PHP ke halaman sebelum React dimuat, berisi:
```js
{
  apiUrl:      "/api/v1",         // URL API backend
  currentUser: { id, full_name }, // Data user yang sedang login
  csrfToken:   "abc123...",       // Token CSRF untuk request POST
  appPath:     "/app"             // Base path React Router
}
```
React membaca objek ini via `window.__WIZDAM_INIT__` saat startup.

**Q: Kenapa `/app/researchers/123` kadang 404 saat refresh?**  
A: Ini adalah SPA (Single Page Application) — routing dilakukan di sisi client. Saat hard refresh, browser meminta `/app/researchers/123` ke PHP. Konfigurasi route `/app/{path:.+}` (catch-all dengan regex `.+`) memastikan PHP selalu mengembalikan React shell, lalu React Router menangani navigasi.

**Q: Bagaimana cara ganti bahasa antarmuka?**  
A: Secara default, bahasa terdeteksi dari browser. Untuk ganti secara paksa:
```js
import i18n from './i18n';
i18n.changeLanguage('en'); // atau 'id'
```
Preferensi disimpan di `localStorage`.

---

## WizdamCrawler

**Q: Apakah WizdamCrawler aman digunakan?**  
A: Ya, dengan catatan:
- Mematuhi `robots.txt` secara otomatis
- Rate limiting default 2 detik antar request
- Berhenti otomatis jika CAPTCHA terdeteksi
- Tidak menyimpan data pribadi

**Q: Mengapa tidak semua data dari Google Scholar tersedia?**  
A: Google Scholar tidak memiliki API publik resmi dan aktif memblokir scraping. WizdamCrawler mencoba best-effort; jika CAPTCHA terdeteksi, proses dihentikan dan fallback ke data dari API resmi (Crossref, Semantic Scholar).

**Q: Data apa yang diprioritaskan untuk di-crawl?**  
A: Urutan prioritas: API resmi (Crossref, Semantic Scholar, OpenCitations) → Sangia API cache → Crawl web (Scholar, ResearchGate, Scimago). API resmi selalu lebih andal dan lebih cepat.

---

## Deployment

**Q: Bagaimana cara deploy ke production?**  
A:
1. Set `APP_ENV=production` dan `APP_DEBUG=false` di `.env`
2. Jalankan `npm run build` → output ke `public/app/`
3. Set `TWIG_CACHE=true` untuk performa
4. Gunakan Nginx/Apache dengan konfigurasi di `docs/INSTALLATION.md`
5. Set `ORCID_SANDBOX=false` untuk ORCID production

**Q: Apakah Redis wajib?**  
A: Tidak wajib. Redis digunakan untuk queue job background (`QUEUE_DRIVER=redis`). Jika tidak tersedia, set `QUEUE_DRIVER=database` — job disimpan di tabel MySQL.

**Q: Bagaimana cara update aplikasi?**  
A:
```bash
git pull origin main
composer install --optimize-autoloader
npm install && npm run build
# Jalankan migration baru jika ada:
mysql -u root -p wizdam_scola < database_migration_vX.sql
```
