# Wizdam Sikola – Platform Pengukuran Dampak Riset Indonesia

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![React](https://img.shields.io/badge/react-18.2.0-61DAFB.svg?logo=react)](https://reactjs.org/)
[![Tailwind CSS](https://img.shields.io/badge/tailwindcss-3.3.3-38B2AC.svg?logo=tailwind-css)](https://tailwindcss.com/)

Wizdam Sikola adalah platform berbasis web yang dirancang untuk mengukur dan menganalisis dampak riset di Indonesia. Aplikasi ini menyediakan dashboard interaktif dengan visualisasi data seperti tren publikasi, peta kolaborasi peneliti, dan peringkat peneliti berdasarkan dampak riset.

> **Live Demo:** [wizdam.sangia.org](https://wizdam.sangia.org)

---

## 🚀 Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| **Dashboard Interaktif** | Menampilkan ringkasan data dampak riset nasional secara visual. |
| **Analisis Tren** | Grafik publikasi dan sitasi dari waktu ke waktu. |
| **Peta Peneliti** | Visualisasi sebaran peneliti dan kolaborasi antar institusi/daerah. |
| **Peringkat Peneliti** | Daftar peneliti dengan dampak riset tertinggi. |
| **Dampak Artikel** | Analisis mendalam terhadap artikel ilmiah yang paling berpengaruh. |

---

## 🛠️ Teknologi yang Digunakan

- **Frontend:** [React 18](https://reactjs.org/) – library untuk membangun antarmuka pengguna.
- **Routing:** [React Router DOM 6](https://reactrouter.com/) – navigasi antar halaman.
- **Visualisasi Data:** [Recharts](https://recharts.org/) – library charting berbasis React.
- **Styling:** [Tailwind CSS 3](https://tailwindcss.com/) – framework CSS utility-first.
- **Bundler:** [React Scripts (Create React App)](https://create-react-app.dev/) – konfigurasi build siap pakai.

---

## 📦 Instalasi & Menjalankan Lokal

### Prasyarat
- [Node.js](https://nodejs.org/) versi 16 atau lebih baru
- npm atau yarn

### Langkah-langkah
```bash
# 1. Clone repositori
git clone https://github.com/mokesano/wizdam-sikola.git
cd wizdam-sikola

# 2. Instal dependensi
npm install
# atau
yarn install

# 3. Jalankan server development
npm start
# atau
yarn start
```

Aplikasi akan berjalan di [http://localhost:3000](http://localhost:3000).

### Build untuk Produksi
```bash
npm run build
# atau
yarn build
```

Hasil build akan disimpan di folder `build/`, siap untuk dideploy ke layanan hosting statis.

---

## 📁 Struktur Proyek

```
wizdam-sikola/
├── public/                 # Aset statis (index.html, favicon, dll.)
├── src/
│   ├── components/         # Komponen React
│   │   ├── ArticleImpactComponent.jsx
│   │   ├── ResearcherMapComponent.jsx
│   │   ├── TopResearchersComponent.jsx
│   │   ├── TrendsAnalysisComponent.jsx
│   │   └── WizdomIndonesiaDashboard.jsx
│   ├── context/            # Context API untuk state global
│   │   └── AppContext.js
│   ├── App.js              # Komponen utama & routing
│   ├── index.js            # Entry point React
│   └── index.css           # Styling global (Tailwind)
├── .gitignore
├── package.json            # Dependensi dan skrip npm
├── postcss.config.js       # Konfigurasi PostCSS
├── tailwind.config.js      # Konfigurasi Tailwind CSS
├── LICENSE
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
└── SECURITY.md
```

---

## 🤝 Kontribusi

Kami menyambut kontribusi dari komunitas! Silakan baca [CONTRIBUTING.md](CONTRIBUTING.md) untuk panduan lengkap tentang cara berkontribusi, mulai dari melaporkan bug hingga mengirimkan pull request.

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah **MIT License** – lihat file [LICENSE](LICENSE) untuk detail selengkapnya.

---

## 📞 Kontak

Dikembangkan oleh [@mokesano](https://github.com/mokesano)

- **Website:** [wizdam.sangia.org](https://wizdam.sangia.org)
- **Issues:** [GitHub Issues](https://github.com/mokesano/wizdam-sikola/issues)

---

*Dibuat dengan ❤️ untuk mendukung ekosistem riset Indonesia.*
