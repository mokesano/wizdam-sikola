import React, { useState } from 'react';
import {
  LineChart, Line, BarChart, Bar, AreaChart, Area, ComposedChart,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import { useAppContext } from '../context/AppContext';

const Skeleton = ({ className = '' }) => <div className={`animate-pulse bg-gray-200 rounded ${className}`} />;

// Data ilustratif untuk kolaborasi dan regional (belum ada endpoint API-nya)
const COLLAB_TRENDS = [
  { year: 2019, nasional: 72.5, internasional: 27.5 },
  { year: 2020, nasional: 70.2, internasional: 29.8 },
  { year: 2021, nasional: 67.8, internasional: 32.2 },
  { year: 2022, nasional: 64.5, internasional: 35.5 },
  { year: 2023, nasional: 60.3, internasional: 39.7 },
  { year: 2024, nasional: 56.5, internasional: 43.5 },
];

const REGIONAL_DATA = [
  { country: 'Singapura', impact: 86.4, growth: 7.2 },
  { country: 'Malaysia',  impact: 78.3, growth: 12.4 },
  { country: 'Indonesia', impact: 74.6, growth: 15.8 },
  { country: 'Thailand',  impact: 72.1, growth: 10.5 },
  { country: 'Vietnam',   impact: 68.4, growth: 18.3 },
  { country: 'Filipina',  impact: 65.8, growth: 14.2 },
];

const TOPIC_TRENDS = [
  { topic: 'Kecerdasan Buatan',    growth: 35.4, publications: 1845, avgImpact: 82.7 },
  { topic: 'Energi Terbarukan',   growth: 28.9, publications: 1420, avgImpact: 79.4 },
  { topic: 'Perubahan Iklim',     growth: 26.5, publications: 1350, avgImpact: 80.1 },
  { topic: 'Kesehatan Digital',   growth: 24.2, publications: 1260, avgImpact: 78.5 },
  { topic: 'Keamanan Pangan',     growth: 21.7, publications: 1180, avgImpact: 76.8 },
  { topic: 'Ekonomi Digital',     growth: 20.3, publications: 1050, avgImpact: 75.2 },
  { topic: 'Keanekaragaman Hayati', growth: 18.6, publications: 980, avgImpact: 77.4 },
  { topic: 'Teknologi Pendidikan', growth: 17.9, publications: 925, avgImpact: 74.6 },
];

const TrendsAnalysisComponent = () => {
  const [showInsights, setShowInsights] = useState(true);

  const { trends, loading, errors } = useAppContext();

  // Normalise API trends untuk chart utama
  const trendData = trends.map(t => ({
    year:       t.year,
    publikasi:  Number(t.total_publications ?? 0),
    avgScore:   Number(t.avg_wizdam_score   ?? 0),
    sitasi:     Number(t.total_citations    ?? 0),
  }));

  // Prediksi sederhana: linear extrapolation 2 tahun ke depan
  const predictionData = (() => {
    if (trendData.length < 2) return trendData;
    const n = trendData.length;
    const last  = trendData[n - 1];
    const prev  = trendData[n - 2];
    const delta = last.avgScore - prev.avgScore;
    return [
      ...trendData.map(d => ({ ...d, predicted: null })),
      { year: last.year + 1, publikasi: null, sitasi: null, avgScore: null, predicted: +(last.avgScore + delta).toFixed(2) },
      { year: last.year + 2, publikasi: null, sitasi: null, avgScore: null, predicted: +(last.avgScore + delta * 2).toFixed(2) },
    ];
  })();

  // Hitung growth dari data nyata
  const growthStats = (() => {
    if (trendData.length < 2) return null;
    const first = trendData[0];
    const last  = trendData[trendData.length - 1];
    const pubGrowth   = first.publikasi ? (((last.publikasi  - first.publikasi)  / first.publikasi)  * 100).toFixed(1) : null;
    const scoreGrowth = first.avgScore  ? (((last.avgScore   - first.avgScore)   / first.avgScore)   * 100).toFixed(1) : null;
    const citeGrowth  = first.sitasi    ? (((last.sitasi     - first.sitasi)     / first.sitasi)     * 100).toFixed(1) : null;
    return { pubGrowth, scoreGrowth, citeGrowth };
  })();

  return (
    <div className="bg-white rounded-lg shadow-md p-4">
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-2">
        <h2 className="text-lg font-semibold">Tren dan Analisis Penelitian</h2>
        <button
          className={`px-3 py-1 text-sm rounded-md ${showInsights ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'}`}
          onClick={() => setShowInsights(s => !s)}
        >
          {showInsights ? 'Sembunyikan Insights' : 'Tampilkan Insights'}
        </button>
      </div>

      {/* ── Tren Publikasi & Skor (dari API) ── */}
      <section className="mb-8">
        <h3 className="text-md font-semibold mb-4">
          Tren Publikasi & Wizdam Score ({trendData[0]?.year ?? '…'}–{trendData[trendData.length - 1]?.year ?? '…'})
        </h3>

        {loading.trends ? (
          <Skeleton className="h-72" />
        ) : errors.trends ? (
          <p className="text-red-500 text-sm">{errors.trends}</p>
        ) : trendData.length === 0 ? (
          <p className="text-gray-400 text-sm text-center py-16">Data tren belum tersedia.</p>
        ) : (
          <>
            <ResponsiveContainer width="100%" height={320}>
              <ComposedChart data={trendData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="year" />
                <YAxis yAxisId="left"  orientation="left"  stroke="#8884d8" />
                <YAxis yAxisId="right" orientation="right" stroke="#82ca9d" />
                <Tooltip />
                <Legend />
                <Bar  yAxisId="left"  dataKey="publikasi" fill="#8884d8" fillOpacity={0.7} name="Total Publikasi" />
                <Line yAxisId="right" type="monotone" dataKey="avgScore" stroke="#ff7300" strokeWidth={2} name="Avg Wizdam Score" dot />
              </ComposedChart>
            </ResponsiveContainer>

            {growthStats && (
              <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-blue-50 p-3 rounded-lg">
                  <h4 className="text-sm font-medium text-blue-800">Pertumbuhan Publikasi</h4>
                  <p className="text-2xl font-bold text-blue-700">+{growthStats.pubGrowth}%</p>
                  <p className="text-xs text-blue-600">dari {trendData[0].year} hingga {trendData[trendData.length - 1].year}</p>
                </div>
                <div className="bg-orange-50 p-3 rounded-lg">
                  <h4 className="text-sm font-medium text-orange-800">Pertumbuhan Wizdam Score</h4>
                  <p className="text-2xl font-bold text-orange-700">+{growthStats.scoreGrowth}%</p>
                  <p className="text-xs text-orange-600">rata-rata skor meningkat</p>
                </div>
                <div className="bg-green-50 p-3 rounded-lg">
                  <h4 className="text-sm font-medium text-green-800">Pertumbuhan Total Sitasi</h4>
                  <p className="text-2xl font-bold text-green-700">+{growthStats.citeGrowth}%</p>
                  <p className="text-xs text-green-600">kumulatif semua publikasi</p>
                </div>
              </div>
            )}
          </>
        )}
      </section>

      {/* ── Prediksi (extrapolasi dari data nyata) ── */}
      <section className="mb-8">
        <h3 className="text-md font-semibold mb-4">Prediksi Wizdam Score (Ekstrapolasi Linear)</h3>
        {trendData.length >= 2 ? (
          <>
            <ResponsiveContainer width="100%" height={280}>
              <ComposedChart data={predictionData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="year" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Line type="monotone" dataKey="avgScore"  stroke="#8884d8" strokeWidth={2} name="Skor Aktual" connectNulls={false} />
                <Line type="monotone" dataKey="predicted" stroke="#82ca9d" strokeDasharray="6 3" strokeWidth={2} name="Skor Prediksi" connectNulls />
              </ComposedChart>
            </ResponsiveContainer>
            {showInsights && (
              <div className="mt-3 bg-indigo-50 p-3 rounded-lg text-sm text-indigo-700">
                Prediksi dihitung dari tren linear 2 tahun terakhir data nyata. Nilai aktual bergantung pada volume dan kualitas publikasi baru.
              </div>
            )}
          </>
        ) : (
          <p className="text-gray-400 text-sm text-center py-8">Perlu minimal 2 tahun data untuk prediksi.</p>
        )}
      </section>

      {/* ── Kolaborasi & Topik (ilustratif) ── */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div className="bg-white p-4 rounded-lg shadow-sm">
          <h3 className="text-md font-semibold mb-1">Tren Kolaborasi Internasional</h3>
          <p className="text-xs text-gray-400 mb-3 italic">Data ilustratif – akan diperbarui saat endpoint kolaborasi tersedia</p>
          <ResponsiveContainer width="100%" height={260}>
            <AreaChart data={COLLAB_TRENDS}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="year" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Area type="monotone" dataKey="nasional"      stackId="1" stroke="#8884d8" fill="#8884d8" fillOpacity={0.6} name="Nasional (%)" />
              <Area type="monotone" dataKey="internasional" stackId="1" stroke="#82ca9d" fill="#82ca9d" fillOpacity={0.6} name="Internasional (%)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        <div className="bg-white p-4 rounded-lg shadow-sm">
          <h3 className="text-md font-semibold mb-1">Topik dengan Pertumbuhan Tertinggi</h3>
          <p className="text-xs text-gray-400 mb-3 italic">Data ilustratif – akan diperbarui dari endpoint SDG/topik</p>
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={TOPIC_TRENDS} layout="vertical">
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis type="number" />
              <YAxis dataKey="topic" type="category" width={150} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Bar dataKey="growth" fill="#8884d8" name="Pertumbuhan (%)" radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* ── Benchmark Regional (ilustratif) ── */}
      <section className="mb-6">
        <h3 className="text-md font-semibold mb-1">Perbandingan Dampak Penelitian Regional</h3>
        <p className="text-xs text-gray-400 mb-3 italic">Data ilustratif – benchmark dari laporan SCImago 2024</p>
        <ResponsiveContainer width="100%" height={300}>
          <BarChart data={REGIONAL_DATA}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="country" />
            <YAxis yAxisId="left"  orientation="left"  stroke="#8884d8" />
            <YAxis yAxisId="right" orientation="right" stroke="#82ca9d" />
            <Tooltip />
            <Legend />
            <Bar yAxisId="left"  dataKey="impact" fill="#8884d8" name="Skor Dampak" />
            <Bar yAxisId="right" dataKey="growth" fill="#82ca9d" name="Pertumbuhan (%)" />
          </BarChart>
        </ResponsiveContainer>
        {showInsights && (
          <div className="mt-3 bg-purple-50 p-3 rounded-lg text-sm text-purple-700">
            Indonesia berada di posisi ketiga ASEAN dari segi skor dampak, namun memiliki laju pertumbuhan tertinggi kedua (15.8%). Dengan tren ini, Indonesia diprediksi melampaui Malaysia dalam 3–4 tahun.
          </div>
        )}
      </section>

      {/* ── Topik Potensial 2025–2030 ── */}
      {showInsights && (
        <section className="bg-gray-50 p-4 rounded-lg">
          <h3 className="text-md font-semibold mb-4">Topik Penelitian Potensial 2025–2030</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            {[
              { title: 'Inovasi Teknologi', color: 'blue', items: ['AI untuk Bahasa Lokal', 'Kota Pintar & Mobilitas', 'Blockchain untuk Governance'] },
              { title: 'Keberlanjutan',     color: 'green', items: ['Ketahanan Pangan Ekosistem', 'Energi Bersih Daerah Terpencil', 'Adaptasi Iklim Pesisir'] },
              { title: 'Kesehatan & Biomedis', color: 'yellow', items: ['Kedokteran Presisi Tropik', 'Biofarmasi Keanekaragaman Hayati', 'Kesiapsiagaan Pandemi'] },
            ].map(({ title, color, items }) => (
              <div key={title} className={`bg-${color}-50 p-3 rounded-lg`}>
                <h4 className={`font-medium text-${color}-800 mb-2`}>{title}</h4>
                <ul className={`space-y-1 text-${color}-700`}>
                  {items.map(i => <li key={i}>• {i}</li>)}
                </ul>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  );
};

export default TrendsAnalysisComponent;
