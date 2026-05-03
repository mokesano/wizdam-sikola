# Panduan Instalasi Wizdam Sicola

## Prasyarat

| Komponen | Versi Minimum | Keterangan |
|----------|---------------|------------|
| PHP | 8.1 | Ekstensi: `pdo`, `pdo_mysql`, `json`, `mbstring`, `curl`, `redis` |
| MySQL / MariaDB | 8.0 / 10.6 | Charset: `utf8mb4` |
| Composer | 2.x | PHP dependency manager |
| Node.js | 18 LTS | JavaScript runtime |
| npm | 9.x | Node package manager |
| Web Server | Apache 2.4 / Nginx 1.24 | Untuk deployment produksi |

---

## Setup Otomatis (Direkomendasikan)

Jalankan satu perintah dari root project:

```bash
bash setup.sh
```

Script ini akan:
1. Memeriksa semua prasyarat
2. Menyalin `.env.example` → `.env`
3. Membuat direktori `storage/`
4. Menjalankan `composer install`
5. Menjalankan `npm install`
6. Build React SPA (jika `APP_ENV=production`)

---

## Setup Manual

### 1. Clone Repository

```bash
git clone https://github.com/mokesano/wizdam-sicola.git
cd wizdam-sicola
```

### 2. Install PHP Dependencies

```bash
composer install --optimize-autoloader
```

### 3. Install Node.js Dependencies

```bash
npm install
```

### 4. Konfigurasi Environment

```bash
cp .env.example .env
```

Edit `.env` dan isi nilai-nilai berikut:

```env
# Aplikasi
APP_NAME="Wizdam Sicola"
APP_ENV=development          # production untuk live
APP_DEBUG=true               # false di production
APP_URL=https://domain-anda.com
TWIG_CACHE=false             # true di production

# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=wizdam_sicola
DB_USERNAME=dbuser
DB_PASSWORD=password_aman

# ORCID OAuth2
ORCID_CLIENT_ID=APP-XXXXXXXXXXXX
ORCID_CLIENT_SECRET=xxxx-xxxx-xxxx
ORCID_REDIRECT_URI=https://domain-anda.com/auth/orcid-callback
ORCID_SANDBOX=true           # false di production

# Sangia AI Engine
SANGIA_API_URL=https://api.sangia.org
SANGIA_API_KEY=               # API key dari wizdam-apis
SANGIA_SERVICE_KEY=           # Kunci admin untuk revoke
WIZDAM_SHARED_SECRET=         # HARUS sama dengan wizdam-apis

# Keamanan
ENCRYPTION_KEY=               # 32 karakter random
```

Generate nilai aman:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### 5. Setup Database

```bash
# Buat database
mysql -u root -p -e "CREATE DATABASE wizdam_sicola CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Jalankan skema utama
mysql -u root -p wizdam_sicola < database_schema_full.sql

# Jalankan migrasi v2 (tabel cache & bobot)
mysql -u root -p wizdam_sicola < database_migration_v2.sql
```

### 6. Build React SPA

**Development** (hot reload, butuh terminal terpisah):
```bash
npm run dev
# Vite berjalan di http://localhost:3000
# Proxy otomatis ke PHP di http://localhost:8000
```

**Production**:
```bash
npm run build
# Output: public/app/ (hash-based filenames + manifest.json)
```

### 7. Jalankan PHP Server

```bash
# Development
php -S localhost:8000 -t public/

# Production: gunakan Apache/Nginx dengan konfigurasi di bawah
```

---

## Konfigurasi Apache

```apache
<VirtualHost *:80>
    ServerName domain-anda.com
    DocumentRoot /var/www/wizdam-sicola/public

    <Directory /var/www/wizdam-sicola/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/wizdam-error.log
    CustomLog ${APACHE_LOG_DIR}/wizdam-access.log combined
</VirtualHost>
```

File `.htaccess` di `public/`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

## Konfigurasi Nginx

```nginx
server {
    listen 80;
    server_name domain-anda.com;
    root /var/www/wizdam-sicola/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~* \.(js|css|png|jpg|gif|svg|ico|woff2?)$ {
        expires max;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## Variabel Environment Lengkap

| Variabel | Wajib | Default | Keterangan |
|----------|-------|---------|------------|
| `APP_ENV` | ✓ | `development` | `development` / `production` |
| `APP_DEBUG` | | `true` | Tampilkan stack trace error |
| `APP_URL` | ✓ | | URL lengkap aplikasi |
| `TWIG_CACHE` | | `false` | Cache template Twig |
| `APP_CORS_ORIGINS` | | `http://localhost:3000` | CORS origins (koma-separated) |
| `DB_HOST` | ✓ | `localhost` | Host database |
| `DB_DATABASE` | ✓ | `wizdam_sicola` | Nama database |
| `DB_USERNAME` | ✓ | `root` | Username DB |
| `DB_PASSWORD` | ✓ | | Password DB |
| `ORCID_CLIENT_ID` | ✓ | | Client ID dari orcid.org/developer |
| `ORCID_CLIENT_SECRET` | ✓ | | Client secret ORCID |
| `ORCID_REDIRECT_URI` | ✓ | | Harus terdaftar di ORCID Developer Tools |
| `ORCID_SANDBOX` | | `true` | Gunakan sandbox ORCID (testing) |
| `SANGIA_API_URL` | ✓ | `https://api.sangia.org` | URL Sangia API Engine |
| `SANGIA_API_KEY` | ✓ | | API key Sangia |
| `SANGIA_SERVICE_KEY` | | | Kunci admin Sangia (untuk revoke) |
| `WIZDAM_SHARED_SECRET` | ✓ | | Shared secret untuk HMAC key generation |
| `SCOPUS_API_KEY` | | | Key dari Elsevier Developer Portal |
| `ENCRYPTION_KEY` | ✓ | | 32-char key untuk enkripsi |
| `SESSION_LIFETIME` | | `120` | Masa hidup sesi (menit) |
| `CRAWLER_RECEIVER_TOKEN` | | | Token untuk webhook crawler |
| `VITE_API_URL` | | `/api/v1` | URL API untuk React frontend |

---

## Verifikasi Instalasi

```bash
# Cek PHP dependencies
composer validate

# Cek konfigurasi Vite
npm run build -- --dry-run 2>/dev/null || echo "Siap build"

# Tes koneksi DB
php -r "
require 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable('.');
\$dotenv->safeLoad();
\$pdo = new PDO('mysql:host=' . \$_ENV['DB_HOST'] . ';dbname=' . \$_ENV['DB_DATABASE'], \$_ENV['DB_USERNAME'], \$_ENV['DB_PASSWORD']);
echo 'DB OK: ' . \$pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
"
```
