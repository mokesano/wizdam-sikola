#!/usr/bin/env bash
# =============================================================================
# Wizdam Scola — Setup Script
# Jalankan dari root project: bash setup.sh
# =============================================================================
set -e

BOLD='\033[1m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

step()  { echo -e "\n${BOLD}▶ $1${NC}"; }
ok()    { echo -e "  ${GREEN}✔ $1${NC}"; }
warn()  { echo -e "  ${YELLOW}⚠ $1${NC}"; }
fail()  { echo -e "  ${RED}✘ $1${NC}"; }

echo -e "${BOLD}=============================================="
echo -e " Wizdam Scola — Setup"
echo -e "==============================================${NC}"

# ─── 1. Cek prasyarat ─────────────────────────────────────────────────────────
step "Memeriksa prasyarat sistem"

if ! command -v php &>/dev/null; then
    fail "PHP tidak ditemukan. Instal PHP >= 8.1"
    exit 1
fi
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
ok "PHP $PHP_VER ditemukan"

if ! command -v composer &>/dev/null; then
    fail "Composer tidak ditemukan. Instal dari https://getcomposer.org"
    exit 1
fi
ok "Composer ditemukan ($(composer --version --no-ansi | head -1))"

if ! command -v node &>/dev/null; then
    fail "Node.js tidak ditemukan. Instal dari https://nodejs.org (>= 18)"
    exit 1
fi
ok "Node.js $(node -v) ditemukan"

if ! command -v npm &>/dev/null; then
    fail "npm tidak ditemukan"
    exit 1
fi
ok "npm $(npm -v) ditemukan"

# ─── 2. Environment file ──────────────────────────────────────────────────────
step "Menyiapkan file environment"

if [ ! -f ".env" ]; then
    cp .env.example .env
    ok ".env dibuat dari .env.example"
    warn "Edit .env sebelum menjalankan aplikasi (DB, ORCID, Sangia API, dll.)"
else
    ok ".env sudah ada"
fi

# ─── 3. Direktori storage ─────────────────────────────────────────────────────
step "Membuat direktori storage"
mkdir -p storage/logs storage/cache
mkdir -p public/assets/images/resized
mkdir -p public/assets/pdf/compressed
ok "Direktori storage siap"

# ─── 4. PHP dependencies ──────────────────────────────────────────────────────
step "Menginstal PHP dependencies (composer install)"
composer install --no-interaction --prefer-dist --optimize-autoloader
ok "PHP dependencies terinstal"

# ─── 5. Node.js dependencies ─────────────────────────────────────────────────
step "Menginstal Node.js dependencies (npm install)"
npm install
ok "Node.js dependencies terinstal"

# ─── 6. Build React/Vite ─────────────────────────────────────────────────────
step "Build React SPA (npm run build)"

if [ "${APP_ENV:-development}" = "development" ]; then
    warn "APP_ENV=development — melewati build produksi."
    warn "Untuk development, jalankan 'npm run dev' secara terpisah."
else
    npm run build
    ok "React SPA berhasil di-build ke public/app/"
fi

# ─── 7. Ringkasan ────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}=============================================="
echo -e " Setup selesai! Langkah selanjutnya:"
echo -e "==============================================${NC}"
echo ""
echo "  1. Edit .env — pastikan DB, ORCID, dan Sangia API dikonfigurasi"
echo ""
echo "  2. Buat database MySQL:"
echo "       mysql -u root -p -e \"CREATE DATABASE wizdam_scola CHARACTER SET utf8mb4;\""
echo ""
echo "  3. Jalankan migrasi database:"
echo "       mysql -u root -p wizdam_scola < database_schema_full.sql"
echo "       mysql -u root -p wizdam_scola < database_migration_v2.sql"
echo ""
echo "  4. Jalankan PHP dev server:"
echo "       php -S localhost:8000 -t public/"
echo ""
echo "  5. Untuk React SPA (development, jalankan di terminal terpisah):"
echo "       npm run dev"
echo ""
echo "  6. Akses aplikasi:"
echo "       Twig pages  : http://localhost:8000"
echo "       React SPA   : http://localhost:8000/app  (pakai Vite dev server: http://localhost:3000)"
echo "       Dashboard   : http://localhost:8000/dashboard"
echo "       Admin Panel : http://localhost:8000/admin"
echo ""
echo -e "${GREEN}${BOLD}Selesai!${NC}"
