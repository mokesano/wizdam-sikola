import React, { useState } from 'react';
import {
  ComposedChart, Bar, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  ResponsiveContainer, PieChart, Pie, Cell, AreaChart, Area,
} from 'recharts';
import { useAppContext } from '../context/AppContext';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8', '#82ca9d', '#ffc658'];

const Skeleton = ({ className = '' }) => (
  <div className={`animate-pulse bg-gray-200 rounded ${className}`} />
);

const StatCard = ({ label, value, sub, color }) => (
  <div className={`${color} p-4 rounded-lg text-center`}>
    <p className="text-sm text-gray-500">{label}</p>
    <p className="text-2xl font-bold">{value}</p>
    {sub && <p className="text-xs text-green-600">{sub}</p>}
  </div>
);

const WizdomIndonesiaDashboard = () => {
  const [activeTab, setActiveTab] = useState('dashboard');

  const {
    stats, topResearchers, topArticles, trends,
    loading, errors,
    searchQuery, setSearchQuery,
    timeRange, setTimeRange,
  } = useAppContext();

  // Normalise field distribution dari stats API
  const fieldDistribution = (stats?.field_distribution ?? []).map(f => ({
    field:           f.field,
    researcherCount: Number(f.researcher_count ?? 0),
    avgImpact:       Number(f.avg_score ?? 0),
  }));

  // Normalise province distribution
  const provinceData = (stats?.province_distribution ?? []).slice(0, 7).map(p => ({
    province:        p.province,
    researchers:     Number(p.researcher_count ?? 0),
    avgImpact:       Number(p.avg_impact ?? 0),
  }));

  // Normalise trends API
  const trendData = trends.map(t => ({
    year:       t.year,
    akademik:   Number(t.avg_wizdam_score ?? 0),
    total:      Number(t.total_publications ?? 0),
    sitasi:     Number(t.total_citations ?? 0),
  }));

  const isLoadingDash = loading.stats || loading.researchers || loading.articles;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-blue-700 text-white p-4 shadow-md">
        <div className="container mx-auto flex flex-col md:flex-row justify-between items-center">
          <div className="flex items-center mb-4 md:mb-0">
            <div className="w-10 h-10 bg-blue-500 rounded mr-3 flex items-center justify-center font-bold text-lg">W</div>
            <h1 className="text-xl font-bold">Wizdam Indonesia</h1>
          </div>
          <div className="flex flex-col sm:flex-row items-center space-y-2 sm:space-y-0 sm:space-x-4">
            <div className="relative">
              <input
                type="text"
                placeholder="Cari peneliti, artikel atau institusi..."
                className="bg-blue-600 text-white placeholder-blue-300 px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-white w-64"
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
              />
              <span className="absolute right-3 top-2.5 text-blue-300">🔍</span>
            </div>
            <a href="/auth/login" className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-xs hover:bg-blue-400">
              Login
            </a>
          </div>
        </div>
      </header>

      {/* Navigation */}
      <nav className="bg-white shadow-sm">
        <div className="container mx-auto">
          <ul className="flex flex-wrap space-x-1 md:space-x-8 p-4">
            {[
              { key: 'dashboard',        label: 'Dashboard' },
              { key: 'articleImpact',    label: 'Dampak Artikel' },
              { key: 'researcherImpact', label: 'Peneliti Terkemuka' },
              { key: 'researcherMap',    label: 'Peta Distribusi' },
              { key: 'trends',           label: 'Tren & Analisis' },
            ].map(({ key, label }) => (
              <li key={key}>
                <button
                  className={`font-medium pb-1 ${activeTab === key ? 'text-blue-700 border-b-2 border-blue-700' : 'text-gray-600 hover:text-blue-700'}`}
                  onClick={() => setActiveTab(key)}
                >
                  {label}
                </button>
              </li>
            ))}
          </ul>
        </div>
      </nav>

      {/* Main Content */}
      <main className="container mx-auto p-4 md:p-6">
        {activeTab === 'dashboard' && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">

            {/* ── Statistik Ringkasan ── */}
            <div className="bg-white p-4 rounded-lg shadow col-span-3 mb-4">
              <h2 className="text-lg font-semibold mb-4">Ringkasan Penelitian Indonesia</h2>
              {loading.stats ? (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-20" />)}
                </div>
              ) : errors.stats ? (
                <p className="text-red-500 text-sm">{errors.stats}</p>
              ) : (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <StatCard
                    label="Total Peneliti" color="bg-blue-50"
                    value={(stats?.total_researchers ?? 0).toLocaleString('id-ID')}
                  />
                  <StatCard
                    label="Total Publikasi" color="bg-green-50"
                    value={(stats?.total_publications ?? 0).toLocaleString('id-ID')}
                  />
                  <StatCard
                    label="Total Sitasi" color="bg-yellow-50"
                    value={(stats?.total_citations ?? 0).toLocaleString('id-ID')}
                  />
                  <StatCard
                    label="Wizdam Score Rata-rata" color="bg-indigo-50"
                    value={stats?.avg_wizdam_score ?? '—'}
                  />
                </div>
              )}
            </div>

            {/* ── Dampak Berdasarkan Bidang ── */}
            <div className="bg-white p-4 rounded-lg shadow col-span-3 lg:col-span-2">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-lg font-semibold">Dampak Penelitian Berdasarkan Bidang</h2>
                <select
                  className="text-sm border rounded p-1"
                  value={timeRange}
                  onChange={e => setTimeRange(e.target.value)}
                >
                  <option value="all">Semua Waktu</option>
                  <option value="year">1 Tahun Terakhir</option>
                  <option value="3years">3 Tahun Terakhir</option>
                  <option value="5years">5 Tahun Terakhir</option>
                </select>
              </div>
              {loading.stats ? (
                <Skeleton className="h-72" />
              ) : fieldDistribution.length === 0 ? (
                <p className="text-gray-400 text-sm text-center py-16">Data belum tersedia.</p>
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <ComposedChart data={fieldDistribution}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="field" tick={{ fontSize: 11 }} />
                    <YAxis yAxisId="left"  orientation="left"  stroke="#8884d8" />
                    <YAxis yAxisId="right" orientation="right" stroke="#82ca9d" />
                    <Tooltip />
                    <Legend />
                    <Bar    yAxisId="left"  dataKey="researcherCount" fill="#8884d8" name="Jumlah Peneliti" />
                    <Line   yAxisId="right" type="monotone" dataKey="avgImpact" stroke="#82ca9d" name="Skor Rata-rata" />
                  </ComposedChart>
                </ResponsiveContainer>
              )}
            </div>

            {/* ── Distribusi Provinsi ── */}
            <div className="bg-white p-4 rounded-lg shadow">
              <h2 className="text-lg font-semibold mb-4">Distribusi Peneliti di Indonesia</h2>
              {loading.stats ? (
                <Skeleton className="h-72" />
              ) : provinceData.length === 0 ? (
                <p className="text-gray-400 text-sm text-center py-16">Data belum tersedia.</p>
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={provinceData}
                      cx="50%" cy="50%"
                      outerRadius={80}
                      dataKey="researchers"
                      nameKey="province"
                      label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                    >
                      {provinceData.map((_, i) => (
                        <Cell key={i} fill={COLORS[i % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip formatter={(v, _, p) => [v, p.payload.province]} />
                  </PieChart>
                </ResponsiveContainer>
              )}
              <div className="mt-2 text-center">
                <button className="text-blue-600 text-sm hover:text-blue-800" onClick={() => setActiveTab('researcherMap')}>
                  Lihat Peta Lengkap
                </button>
              </div>
            </div>

            {/* ── Peneliti Terkemuka ── */}
            <div className="bg-white p-4 rounded-lg shadow col-span-3 lg:col-span-2">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-lg font-semibold">Peneliti Terkemuka di Indonesia</h2>
                <button className="text-blue-600 text-sm hover:text-blue-800" onClick={() => setActiveTab('researcherImpact')}>
                  Lihat Semua
                </button>
              </div>
              {loading.researchers ? (
                <div className="space-y-2">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-10" />)}</div>
              ) : topResearchers.length === 0 ? (
                <p className="text-gray-400 text-sm text-center py-8">Data peneliti belum tersedia.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Peneliti</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Afiliasi</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">H-Index</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sitasi</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wizdam Score</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {topResearchers.map(r => (
                        <tr key={r.id} className="hover:bg-gray-50">
                          <td className="px-4 py-2 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">{r.full_name}</div>
                            <div className="text-xs text-gray-500">
                              {Array.isArray(r.field_of_study) ? r.field_of_study[0] : r.field_of_study}
                            </div>
                          </td>
                          <td className="px-4 py-2 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{r.institution_name}</div>
                            <div className="text-xs text-gray-500">{r.province}</div>
                          </td>
                          <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{r.h_index}</td>
                          <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                            {(r.total_citations ?? 0).toLocaleString('id-ID')}
                          </td>
                          <td className="px-4 py-2 whitespace-nowrap">
                            <div className="flex items-center">
                              <div className="flex-1 bg-gray-200 rounded-full h-2.5 mr-2">
                                <div className="bg-blue-600 h-2.5 rounded-full" style={{ width: `${Math.min(100, r.wizdam_score)}%` }} />
                              </div>
                              <span className="text-sm font-medium text-gray-900">{r.wizdam_score}</span>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {/* ── Artikel Dampak Tertinggi ── */}
            <div className="bg-white p-4 rounded-lg shadow col-span-3 lg:col-span-1">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-lg font-semibold">Artikel Dampak Tertinggi</h2>
                <button className="text-blue-600 text-sm hover:text-blue-800" onClick={() => setActiveTab('articleImpact')}>
                  Lihat Semua
                </button>
              </div>
              {loading.articles ? (
                <div className="space-y-3">{[...Array(3)].map((_, i) => <Skeleton key={i} className="h-16" />)}</div>
              ) : topArticles.length === 0 ? (
                <p className="text-gray-400 text-sm text-center py-8">Data artikel belum tersedia.</p>
              ) : (
                <ul className="space-y-3">
                  {topArticles.slice(0, 3).map(a => (
                    <li key={a.id} className="border-b pb-3">
                      <h3 className="text-sm font-medium text-gray-900 line-clamp-2">{a.title}</h3>
                      <p className="text-xs text-gray-600 mt-1">{a.authors_list}</p>
                      <div className="flex justify-between mt-1">
                        <span className="text-xs text-gray-500">{a.journal_title} ({a.publication_year})</span>
                        <span className="text-xs font-medium bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">
                          {a.wizdam_score}
                        </span>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            {/* ── Tren Penelitian ── */}
            <div className="bg-white p-4 rounded-lg shadow col-span-3">
              <h2 className="text-lg font-semibold mb-4">
                Tren Penelitian ({new Date().getFullYear() - 5}–{new Date().getFullYear()})
              </h2>
              {loading.trends ? (
                <Skeleton className="h-72" />
              ) : trendData.length === 0 ? (
                <p className="text-gray-400 text-sm text-center py-16">Data tren belum tersedia.</p>
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <AreaChart data={trendData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="year" />
                    <YAxis yAxisId="left" />
                    <YAxis yAxisId="right" orientation="right" />
                    <Tooltip />
                    <Legend />
                    <Area yAxisId="left" type="monotone" dataKey="total" stroke="#8884d8" fill="#8884d8" fillOpacity={0.3} name="Total Publikasi" />
                    <Line  yAxisId="right" type="monotone" dataKey="akademik" stroke="#ff7300" name="Avg Wizdam Score" />
                  </AreaChart>
                </ResponsiveContainer>
              )}
            </div>

          </div>
        )}
      </main>

      <footer className="bg-gray-100 p-4 mt-6">
        <div className="container mx-auto text-center text-sm text-gray-600">
          <p>© {new Date().getFullYear()} Wizdam Indonesia — Platform Analisis Dampak Penelitian Indonesia</p>
        </div>
      </footer>
    </div>
  );
};

export default WizdomIndonesiaDashboard;
