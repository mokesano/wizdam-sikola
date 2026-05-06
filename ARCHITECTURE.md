# Arsitektur Aplikasi Wizdam Scola

## Gambaran Umum

Wizdam Scola adalah platform pengukuran dampak riset Indonesia yang terintegrasi dengan Wizdam APIs untuk analisis SDGs, Wizdam Impact Score, dan berbagai fitur crawling data akademik.

## Struktur Domain

```
├── api.sangia.org          # API Gateway (di luar public_html)
│   ├── index.php           # Router utama API
│   ├── switzer.php         # Konfigurasi routing API
│   └── validation.php      # Validasi token API key
│
├── www.sangia.org          # Aplikasi Frontend (www.sangia.org)
│   └── /workspace          # Direktori aplikasi ini
│
├── developers.sangia.org   # Developer Portal (dokumentasi API)
│   └── Dokumentasi statis
│
└── worker.sangia.org       # (Opsional) Worker server untuk crawling
```

## Arsitektur Lapisan Aplikasi

### 1. **Presentation Layer** (`public/`, `views/`)
- `index.php` - Bootstrap aplikasi (~30 baris)
- Router - Mengatur routing ke controllers/handlers
- Middleware - Auth, Admin, Subscription checks
- Views - Twig templates untuk UI

### 2. **Application Layer** (`app/`)
- **Controllers/Handlers** - Menangani request HTTP
- **Services** - Logika bisnis utama
  - `WizdamApiClient` - Client untuk Wizdam APIs
  - `ApiKeyManager` - CRUD API keys
  - `AuthManager` - Authentication
- **Jobs** - Background jobs untuk proses async
  - `JobAbstract` - Base class untuk semua jobs
  - `ResearcherCrawlerJob` - Crawling data peneliti
  - `ImpactAnalysisJob` - Analisis SDGs & Impact Score
- **Repositories** - Data access layer
- **Models/DTOs** - Data transfer objects
- **Events** - Event system untuk decoupling

### 3. **Library Layer** (`library/`)
Kode custom lokal yang tidak disimpan di `/vendor`:
- **Core** - Base classes
- **Database** - `DatabaseManager` (delight-im wrapper)
- **Geo** - `GeoIpManager` untuk pemetaan lokasi
- **Helpers** - Helper functions umum
- **Http** - Request/Response helpers

### 4. **Infrastructure Layer**
- **Database** - MariaDB dengan delight-im
- **Queue** - Redis atau database-based queue
- **Cache** - Redis/Memcached
- **Storage** - File system untuk uploads

### 5. **External APIs** (`api.sangia.org`)
- SDGs Analysis API
- Wizdam Impact Score API
- Journal Fetching (Sinta, Scopus, dll)
- Article by DOI & OAI-PMH
- ORCID Fetching
- Scopus Articles
- Citation Crawlers (OpenAlex, OpenCitations)
- Researcher Identity Crawlers
- Institution Crawlers
- Altmetrics (News, Policy, Social Media)

## Pola Anti-Timeout

### Mode Monolitik
- Untuk data kecil (<1MB atau <100 items)
- Response langsung dalam satu request
- Timeout: 30 detik

### Mode Sequensial (Async Job)
- Untuk data besar atau proses kompleks
- API merespons dengan `job_id`
- Frontend polling status hingga selesai
- Proses dibagi menjadi stages:
  1. Fetch data dasar
  2. Analisis SDGs
  3. Fetch citations
  4. Hitung Impact Score
  5. Fetch altmetrics
  6. Finalisasi

## Manajemen API Keys

### Flow
1. User login di `www.sangia.org`
2. Buat API key di dashboard (nama, permissions, expiry)
3. System generate `api_key` (prefix: `wzd_`) dan `api_secret`
4. Secret ditampilkan sekali saja
5. API key digunakan untuk call ke `api.sangia.org`
6. `validation.php` validasi key sebelum proses

### Permissions
- `read:researcher` - Baca data peneliti
- `write:researcher` - Update data peneliti
- `read:publication` - Baca publikasi
- `read:analysis` - Akses analisis SDGs & Impact Score
- `trigger:crawler` - Trigger crawling
- `admin:keys` - Kelola API keys

## Database Schema

### Tabel Utama
- `users` - Pengguna dengan delight-im auth
- `institutions` - Institusi penelitian
- `researchers` - Peneliti (linked ke users)
- `publications` - Publikasi ilmiah
- `publication_authors` - Many-to-many penulis
- `api_keys` - API keys pengguna
- `jobs` - Queue untuk background jobs
- `citations` - Data kutipan
- `altmetrics` - Metrics dari news, policy, social media
- `user_activities` - Feed aktivitas
- `follows` - Follow system
- `settings` - Pengaturan aplikasi

## Fitur Aplikasi

### Halaman Publik
- Homepage dengan statistik
- Pencarian peneliti
- Pencarian institusi
- Pencarian publikasi
- Profil peneliti (publik)
- Profil institusi (publik)
- Leaderboard (top researchers, institutions)

### Halaman Login Required
- Dashboard personal (feed)
- Edit profil
- Upload avatar/cover
- Manage API keys
- Manual data update
- Trigger fetching/crawling
- View analysis results
- Follow researchers/institutions

### Fitur Berlangganan (Pro/Enterprise)
- Priority crawling
- Advanced analytics
- Bulk operations
- API rate limit lebih tinggi
- Export data
- Custom reports

## Visualisasi

### GeoIP Mapping
- Library: Leaflet.js (ringan, open-source)
- Data: GeoIP.dat untuk lookup lokasi
- Use cases:
  - Dashboard admin: viewer locations
  - Frontend: researcher/institution map
  - Heatmap distribusi riset

### Charts & Graphs
- Library: Chart.js atau ApexCharts
- Metrics:
  - Wizdam Impact Score trends
  - SDGs distribution
  - Citation metrics
  - Altmetrics breakdown

## Queue System

### Drivers
- **Database** (default) - Simpel, no extra dependency
- **Redis** (recommended for production) - Lebih cepat

### Job Types
- `ResearcherCrawlerJob` - Crawl peneliti dari berbagai sumber
- `ImpactAnalysisJob` - Analisis SDGs & Impact Score
- `CitationCrawlerJob` - Crawl citations
- `AltmetricCrawlerJob` - Crawl altmetrics
- `DataSyncJob` - Sync data berkala

### Worker Process
```bash
# CLI command untuk menjalankan worker
php cli.php queue:work --driver=database --max-jobs=100
```

## Keamanan

- Password hashing dengan delight-im (Argon2id)
- API key + secret authentication
- CSRF protection
- XSS prevention (htmlspecialchars)
- SQL injection prevention (prepared statements)
- Rate limiting untuk API
- Input validation
- HTTPS enforcement

## Development Guidelines

### Namespace Convention
- `Wizdam\App\*` - Application code
- `Wizdam\Library\*` - Custom library code

### Testing
- PHPUnit untuk unit tests
- Integration tests untuk API calls
- Mock external services

### Logging
- Storage: `storage/logs/`
- Levels: debug, info, warning, error
- Rotate logs harian

## Deployment

### Requirements
- PHP 8.1+
- MariaDB 10.6+
- Redis (optional but recommended)
- Composer
- Node.js (untuk asset compilation)

### Environment Variables (.env)
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.sangia.org

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=wizdam_scola
DB_USERNAME=user
DB_PASSWORD=secret

WIZDAM_API_URL=https://api.sangia.org

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

QUEUE_DRIVER=database

GEOIP_DAT_PATH=/path/to/GeoIP.dat
```

## Roadmap Pengembangan

### Phase 1 (Current)
- [x] Restructuring direktori
- [x] Setup library layer
- [x] Database schema
- [x] API client dengan mode hybrid
- [x] Queue system
- [ ] Auth system dengan delight-im
- [ ] Basic CRUD researchers/institutions

### Phase 2
- [ ] Integration dengan Wizdam APIs
- [ ] Crawling jobs implementation
- [ ] Dashboard admin
- [ ] GeoIP visualization
- [ ] API key management UI

### Phase 3
- [ ] Subscription system
- [ ] Advanced analytics
- [ ] Real-time updates (WebSocket)
- [ ] Mobile responsive optimization
- [ ] Performance optimization

### Phase 4
- [ ] Public API documentation (developers.sangia.org)
- [ ] SDK libraries (PHP, Python, JS)
- [ ] Webhook system
- [ ] Multi-language support
