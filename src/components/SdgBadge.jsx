import React from 'react';

/**
 * Badge untuk menampilkan satu SDG Goal.
 * Props:
 *   sdg   {number}  - Nomor SDG (1–17)
 *   label {string}  - Label Bahasa Indonesia (opsional, ada fallback)
 *   score {number}  - Confidence score 0–1 (opsional)
 *   size  {'sm'|'md'|'lg'} - Ukuran badge
 */

const SDG_META = {
  1:  { label: 'Tanpa Kemiskinan',                        color: '#E5243B' },
  2:  { label: 'Tanpa Kelaparan',                         color: '#DDA63A' },
  3:  { label: 'Kehidupan Sehat dan Sejahtera',           color: '#4C9F38' },
  4:  { label: 'Pendidikan Berkualitas',                  color: '#C5192D' },
  5:  { label: 'Kesetaraan Gender',                       color: '#FF3A21' },
  6:  { label: 'Air Bersih dan Sanitasi',                 color: '#26BDE2' },
  7:  { label: 'Energi Bersih dan Terjangkau',            color: '#FCC30B' },
  8:  { label: 'Pekerjaan Layak dan Pertumbuhan Ekonomi', color: '#A21942' },
  9:  { label: 'Industri, Inovasi, dan Infrastruktur',   color: '#FD6925' },
  10: { label: 'Berkurangnya Kesenjangan',                color: '#DD1367' },
  11: { label: 'Kota dan Komunitas Berkelanjutan',        color: '#FD9D24' },
  12: { label: 'Konsumsi dan Produksi Bertanggung Jawab', color: '#BF8B2E' },
  13: { label: 'Penanganan Perubahan Iklim',              color: '#3F7E44' },
  14: { label: 'Ekosistem Lautan',                        color: '#0A97D9' },
  15: { label: 'Ekosistem Daratan',                       color: '#56C02B' },
  16: { label: 'Perdamaian, Keadilan, dan Kelembagaan',  color: '#00689D' },
  17: { label: 'Kemitraan untuk Mencapai Tujuan',         color: '#19486A' },
};

const SIZE = {
  sm: { badge: 'px-1.5 py-0.5 text-xs', icon: 'w-4 h-4 text-xs' },
  md: { badge: 'px-2 py-1 text-sm',     icon: 'w-5 h-5 text-sm' },
  lg: { badge: 'px-3 py-1.5 text-base', icon: 'w-6 h-6 text-base' },
};

const SdgBadge = ({ sdg, label, score, size = 'sm', showLabel = false, className = '' }) => {
  const num  = Number(sdg);
  const meta = SDG_META[num] ?? { label: `SDG ${num}`, color: '#888888' };
  const displayLabel = label ?? meta.label;
  const sz   = SIZE[size] ?? SIZE.sm;

  return (
    <span
      title={`${displayLabel}${score != null ? ` (${(score * 100).toFixed(0)}%)` : ''}`}
      className={`inline-flex items-center gap-1 rounded font-medium text-white ${sz.badge} ${className}`}
      style={{ backgroundColor: meta.color }}
    >
      <span className={`font-bold ${sz.icon}`}>{num}</span>
      {showLabel && <span className="truncate max-w-[160px]">{displayLabel}</span>}
      {score != null && !showLabel && (
        <span className="opacity-80 text-xs">({(score * 100).toFixed(0)}%)</span>
      )}
    </span>
  );
};

/**
 * Grid semua 17 SDG — dipakai di halaman profil peneliti / artikel.
 * Props:
 *   activeSdgs {Array} - [{sdg, score, label}] dari API
 */
export const SdgGrid = ({ activeSdgs = [] }) => {
  const activeMap = new Map(activeSdgs.map(s => [Number(s.sdg ?? s), s]));

  return (
    <div className="grid grid-cols-6 sm:grid-cols-9 gap-1.5">
      {Array.from({ length: 17 }, (_, i) => i + 1).map(n => {
        const active = activeMap.get(n);
        const meta   = SDG_META[n];
        return (
          <div
            key={n}
            title={`SDG ${n}: ${meta.label}${active ? ` — ${(active.score * 100).toFixed(0)}%` : ''}`}
            className={`w-8 h-8 rounded flex items-center justify-center text-xs font-bold text-white transition-opacity ${active ? 'opacity-100 ring-2 ring-offset-1 ring-white' : 'opacity-30'}`}
            style={{ backgroundColor: meta.color }}
          >
            {n}
          </div>
        );
      })}
    </div>
  );
};

export default SdgBadge;
