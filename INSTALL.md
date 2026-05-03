# Panduan Instalasi Wizdam Sicola

## Cara Instalasi (Tanpa Terminal)

Aplikasi Wizdam Sicola dilengkapi dengan **installer berbasis web** yang memudahkan proses instalasi tanpa perlu mengakses terminal/server command line.

### Langkah-langkah Instalasi:

1. **Upload File ke Hosting**
   - Upload semua file aplikasi ke direktori `public_html` atau folder domain Anda
   - Pastikan struktur folder tetap sama seperti aslinya

2. **Set Izin Folder**
   - Melalui File Manager hosting, pastikan folder berikut dapat ditulis (writable):
     - `/storage` (chmod 755 atau 777)
     - `/storage/logs` (chmod 755 atau 777)
     - `/storage/cache` (chmod 755 atau 777)

3. **Buka Halaman Instalasi**
   - Akses melalui browser: `https://www.sangia.org/install/`
   - Installer akan otomatis memeriksa persyaratan sistem

4. **Ikuti 4 Langkah Instalasi:**
   
   **Step 1: Persyaratan Sistem**
   - Sistem akan memeriksa versi PHP (minimal 7.4)
   - Memeriksa ekstensi PHP yang diperlukan
   - Memeriksa izin folder
   
   **Step 2: Konfigurasi Database**
   - Masukkan informasi database MariaDB/MySQL:
     - Host (biasanya `localhost`)
     - Port (biasanya `3306`)
     - Nama database (akan dibuat otomatis jika belum ada)
     - Username dan password database
   - Konfigurasi pengaturan aplikasi
   - Opsional: Masukkan API Key Wizdam API jika sudah punya
   
   **Step 3: Buat Akun Administrator**
   - Isi nama lengkap administrator
   - Masukkan email admin
   - Buat password (minimal 6 karakter)
   - **PENTING**: Simpan informasi login ini!
   
   **Step 4: Selesai**
   - Installer akan membuat file `.env` secara otomatis
   - Menjalankan skema database
   - Membuat akun admin pertama
   - Anda akan melihat halaman konfirmasi berhasil

5. **Keamanan Setelah Instalasi**
   - **HAPUS** folder `/public/install/` untuk keamanan
   - Login ke dashboard admin di `https://www.sangia.org/login.php`
   - Segera ubah password default administrator

## Struktur Aplikasi

```
/workspace
├── app/                    # Logika bisnis aplikasi
│   ├── Core/              # Container,基础类
│   ├── Http/              # Request, Response, Router, Middleware
│   ├── Services/          # Service layer (API client, dll)
│   ├── Repositories/      # Database access layer
│   ├── Models/            # Entity/DTO classes
│   ├── Jobs/              # Background jobs (crawling, analysis)
│   └── Install/           # Installer classes
├── library/                # Library kustom (bukan vendor)
├── config/                 # File konfigurasi
├── views/                  # Template HTML/PHP
├── public/                 # File publik (document root)
│   ├── index.php          # Entry point aplikasi
│   ├── install/           # Folder installer
│   └── assets/            # CSS, JS, images
├── storage/                # Logs, cache, uploads
├── vendor/                 # Composer dependencies
└── database_schema.sql    # Skema database
```

## Konfigurasi Pasca Instalasi

### 1. Dashboard Admin
Setelah login sebagai admin, Anda dapat:
- Mengelola pengguna (peneliti, institusi)
- Membuat dan mengelola API keys
- Memantau job queue (crawling, analisis)
- Melihat statistik dan visualisasi GeoIP

### 2. Integrasi Wizdam API
- Kunjungi `https://developers.sangia.org` untuk dokumentasi API
- Buat API key baru dari dashboard admin
- Konfigurasi endpoint API di pengaturan

### 3. Fitur Utama
- **Researcher Profile**: Peneliti dapat mengelola profil, publikasi, dan metrik
- **Wizdam Impact Score**: Analisis dampak penelitian berbasis SDGs
- **Crawling Otomatis**: Fetching data dari Sinta, Scopus, ORCID, dll
- **GeoIP Mapping**: Visualisasi lokasi peneliti dan institusi
- **Multi-tier Subscription**: Free, Pro, Enterprise

## Troubleshooting

### Error: "Requirements not met"
- Periksa versi PHP di hosting (harus >= 7.4)
- Aktifkan ekstensi PHP yang diperlukan melalui cPanel/Plesk
- Pastikan folder storage writable

### Error: "Database connection failed"
- Periksa kredensial database
- Pastikan database user memiliki hak CREATE DATABASE
- Cek apakah host database benar (biasanya `localhost`)

### Error: "Permission denied"
- Set chmod 755 atau 777 untuk folder storage
- Periksa ownership folder

### Installer tidak muncul
- Hapus cache browser
- Pastikan URL benar: `https://www.sangia.org/install/`
- Periksa error log hosting

## Dukungan

Untuk bantuan lebih lanjut:
- Dokumentasi API: https://developers.sangia.org
- Email support: support@sangia.org

---

**Catatan Penting**: 
- Installer hanya boleh dijalankan sekali saat pertama kali setup
- Setelah instalasi selesai, hapus folder `/public/install/`
- Backup database secara berkala
