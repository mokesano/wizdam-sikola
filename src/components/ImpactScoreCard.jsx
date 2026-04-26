import React, { useState, useEffect } from 'react';
import {
  RadarChart, Radar, PolarGrid, PolarAngleAxis, PolarRadiusAxis,
  ResponsiveContainer, Tooltip,
} from 'recharts';
import { impactScoreApi } from '../services/api';
import { SdgGrid } from './SdgBadge';

/**
 * Kartu visualisasi lengkap Wizdam Impact Score (4 pilar).
 *
 * Props:
 *   entityType  {'researcher'|'article'|'institution'|'journal'}
 *   entityId    {number}
 *   initialData {object|null}  – data pilar yang sudah di-fetch (opsional, skip fetch)
 *   compact     {boolean}      – tampilan ringkas (tidak tampilkan radar & SDG grid)
 */

const PILLAR_CONFIG = [
  { key: 'academic', label: 'Akademik',  pct: 40, color: 'blue',   desc: 'Sitasi, H-index, produktivitas publikasi' },
  { key: 'social',   label: 'Sosial',    pct: 25, color: 'green',  desc: 'Mention media sosial, berita, kebijakan' },
  { key: 'economic', label: 'Ekonomi',   pct: 20, color: 'yellow', desc: 'Adopsi industri, paten, transfer teknologi' },
  { key: 'sdg',      label: 'SDGs',      pct: 15, color: 'purple', desc: 'Keterkaitan dengan 17 SDG PBB' },
];

const COLOR_CLASS = {
  blue:   { bar: 'bg-blue-500',   text: 'text-blue-700',   bg: 'bg-blue-50'   },
  green:  { bar: 'bg-green-500',  text: 'text-green-700',  bg: 'bg-green-50'  },
  yellow: { bar: 'bg-yellow-500', text: 'text-yellow-700', bg: 'bg-yellow-50' },
  purple: { bar: 'bg-purple-500', text: 'text-purple-700', bg: 'bg-purple-50' },
};

const Skeleton = ({ className = '' }) => <div className={`animate-pulse bg-gray-200 rounded ${className}`} />;

const ImpactScoreCard = ({ entityType, entityId, initialData = null, compact = false }) => {
  const [data,    setData]    = useState(initialData);
  const [loading, setLoading] = useState(!initialData);
  const [error,   setError]   = useState(null);
  const [recalculating, setRecalculating] = useState(false);

  useEffect(() => {
    if (initialData) { setData(initialData); setLoading(false); return; }
    if (!entityType || !entityId) return;

    let cancelled = false;
    setLoading(true);
    impactScoreApi.get(entityType, entityId)
      .then(res => { if (!cancelled) setData(res.data ?? res); })
      .catch(err => { if (!cancelled) setError(err.message); })
      .finally(() => { if (!cancelled) setLoading(false); });

    return () => { cancelled = true; };
  }, [entityType, entityId, initialData]);

  const handleRecalculate = async () => {
    setRecalculating(true);
    try {
      const res = await impactScoreApi.calculate(entityType, entityId);
      setData(res.data ?? data);
      setError(null);
    } catch (err) {
      setError(err.message);
    } finally {
      setRecalculating(false);
    }
  };

  if (loading) return (
    <div className="space-y-2">
      <Skeleton className="h-8 w-32" />
      {PILLAR_CONFIG.map(p => <Skeleton key={p.key} className="h-6" />)}
    </div>
  );

  if (error && !data) return (
    <div className="text-center py-6">
      <p className="text-gray-400 text-sm mb-2">Impact Score belum tersedia.</p>
      {entityType && entityId && (
        <button
          onClick={handleRecalculate}
          disabled={recalculating}
          className="text-blue-600 hover:underline text-sm disabled:opacity-50"
        >
          {recalculating ? 'Menghitung…' : 'Hitung sekarang via Sangia API'}
        </button>
      )}
    </div>
  );

  if (!data) return null;

  const radarData = PILLAR_CONFIG.map(p => ({
    pillar: `${p.label} (${p.pct}%)`,
    nilai:  data[p.key] ?? 0,
  }));

  const sdgTags = data.sdg_tags ?? [];
  const calculatedAt = data.calculated_at
    ? new Date(data.calculated_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })
    : null;

  return (
    <div className="space-y-4">
      {/* Composite Score */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-xs text-gray-500 uppercase tracking-wide">Wizdam Impact Score</p>
          <p className="text-4xl font-bold text-blue-700">{data.composite ?? '—'}</p>
          {calculatedAt && <p className="text-xs text-gray-400 mt-0.5">Dihitung: {calculatedAt}</p>}
        </div>
        {entityType && entityId && (
          <button
            onClick={handleRecalculate}
            disabled={recalculating}
            title="Hitung ulang via Sangia API"
            className="text-xs text-blue-600 hover:text-blue-800 disabled:opacity-50 border border-blue-200 rounded px-2 py-1"
          >
            {recalculating ? '…' : '↻ Recalculate'}
          </button>
        )}
      </div>

      {/* 4 Pillar bars */}
      <div className="space-y-3">
        {PILLAR_CONFIG.map(p => {
          const val = data[p.key] ?? 0;
          const cls = COLOR_CLASS[p.color];
          return (
            <div key={p.key}>
              <div className="flex justify-between text-sm mb-1">
                <div>
                  <span className={`font-medium ${cls.text}`}>{p.label}</span>
                  <span className="text-gray-400 text-xs ml-1">({p.pct}%)</span>
                </div>
                <span className="font-semibold">{val}</span>
              </div>
              <div className="w-full bg-gray-100 rounded-full h-3">
                <div
                  className={`${cls.bar} h-3 rounded-full transition-all`}
                  style={{ width: `${Math.min(100, val)}%` }}
                />
              </div>
              {!compact && <p className="text-xs text-gray-400 mt-0.5">{p.desc}</p>}
            </div>
          );
        })}
      </div>

      {/* Radar chart & SDG grid (tampil jika bukan compact) */}
      {!compact && (
        <>
          <ResponsiveContainer width="100%" height={220}>
            <RadarChart cx="50%" cy="50%" outerRadius="75%" data={radarData}>
              <PolarGrid />
              <PolarAngleAxis dataKey="pillar" tick={{ fontSize: 11 }} />
              <PolarRadiusAxis angle={30} domain={[0, 100]} tick={{ fontSize: 10 }} />
              <Radar dataKey="nilai" stroke="#6366f1" fill="#6366f1" fillOpacity={0.45} />
              <Tooltip />
            </RadarChart>
          </ResponsiveContainer>

          {sdgTags.length > 0 && (
            <div>
              <p className="text-sm font-medium text-gray-700 mb-2">Keterkaitan SDGs:</p>
              <SdgGrid activeSdgs={sdgTags} />
            </div>
          )}
        </>
      )}

      {/* Weights legend */}
      {!compact && (
        <div className="text-xs text-gray-400 border-t pt-2">
          Formula: <code className="bg-gray-100 px-1 rounded">
            (Akademik × 40%) + (Sosial × 25%) + (Ekonomi × 20%) + (SDGs × 15%)
          </code>
        </div>
      )}
    </div>
  );
};

export default ImpactScoreCard;
