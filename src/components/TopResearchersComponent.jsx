import React, { useState, useMemo, useEffect, useCallback } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  PieChart, Pie, Cell, RadarChart, Radar, PolarGrid, PolarAngleAxis, PolarRadiusAxis,
} from 'recharts';
import { researcherApi } from '../services/api';
import { useAppContext } from '../context/AppContext';

const COLORS       = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8', '#82ca9d'];
const ITEMS_PER_PAGE = 20;

const Skeleton = ({ className = '' }) => (
  <div className={`animate-pulse bg-gray-200 rounded ${className}`} />
);

const SortHeader = ({ label, field, sortBy, sortDir, onSort }) => (
  <th
    className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none"
    onClick={() => onSort(field)}
  >
    <div className="flex items-center gap-1">
      {label}
      {sortBy === field && <span>{sortDir === 'desc' ? '↓' : '↑'}</span>}
    </div>
  </th>
);

const TopResearchersComponent = () => {
  const { stats } = useAppContext();

  const [selectedField,    setSelectedField]    = useState('all');
  const [selectedProvince, setSelectedProvince] = useState('');
  const [searchQ,          setSearchQ]          = useState('');
  const [sortBy,           setSortBy]           = useState('wizdam_score');
  const [sortDir,          setSortDir]          = useState('desc');
  const [currentPage,      setCurrentPage]      = useState(1);
  const [selectedResearcher, setSelectedResearcher] = useState(null);
  const [detailLoading,    setDetailLoading]    = useState(false);

  // ── Paginated list ─────────────────────────────────────────────────────────
  const [rows,    setRows]    = useState([]);
  const [meta,    setMeta]    = useState({ total: 0, pages: 1 });
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState(null);

  const fetchPage = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const res = await researcherApi.list({
        field:    selectedField,
        province: selectedProvince,
        q:        searchQ,
        page,
        per_page: ITEMS_PER_PAGE,
      });
      setRows(res.data ?? []);
      setMeta(res.meta ?? { total: 0, pages: 1 });
      setCurrentPage(page);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [selectedField, selectedProvince, searchQ]);

  useEffect(() => { fetchPage(1); }, [fetchPage]);

  // ── Sort client-side (since API returns pre-sorted by wizdam_score) ────────
  const sortedRows = useMemo(() => {
    const mul = sortDir === 'asc' ? 1 : -1;
    return [...rows].sort((a, b) => mul * ((a[sortBy] ?? 0) - (b[sortBy] ?? 0)));
  }, [rows, sortBy, sortDir]);

  const handleSort = (field) => {
    if (sortBy === field) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortBy(field); setSortDir('desc'); }
  };

  // ── Researcher detail ──────────────────────────────────────────────────────
  const openDetail = async (row) => {
    if (!row.orcid_id) { setSelectedResearcher({ ...row, _partial: true }); return; }
    setDetailLoading(true);
    try {
      const res = await researcherApi.detail(row.orcid_id);
      setSelectedResearcher(res.data ?? row);
    } catch {
      setSelectedResearcher({ ...row, _partial: true });
    } finally {
      setDetailLoading(false);
    }
  };

  // Distribution by field from global stats
  const fieldDist = (stats?.field_distribution ?? []).map(f => ({
    field:     f.field,
    count:     Number(f.researcher_count ?? 0),
    avgImpact: Number(f.avg_score ?? 0),
  }));

  const getInitials = (name = '') =>
    name.split(' ').filter(p => !['Dr.', 'Prof.', 'dr.'].includes(p)).map(n => n[0]).join('').slice(0, 2);

  const radarData = selectedResearcher ? [
    { name: 'H-Index',    value: Math.min((selectedResearcher.h_index ?? 0) * 3.5, 100) },
    { name: 'Sitasi',     value: Math.min((selectedResearcher.total_citations ?? 0) / 100, 90) },
    { name: 'Publikasi',  value: Math.min(selectedResearcher.total_publications ?? 0, 100) },
    { name: 'Skor',       value: Math.min(selectedResearcher.wizdam_score ?? 0, 100) },
  ] : [];

  return (
    <div className="bg-white rounded-lg shadow-md p-4">
      {/* Filters */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
        <h2 className="text-lg font-semibold">Peneliti Terkemuka di Indonesia</h2>
        <div className="flex flex-wrap gap-2">
          <input
            type="text"
            placeholder="Cari nama peneliti…"
            className="border rounded px-2 py-1 text-sm w-44"
            value={searchQ}
            onChange={e => setSearchQ(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && fetchPage(1)}
          />
          <select className="border rounded px-2 py-1 text-sm" value={selectedField} onChange={e => { setSelectedField(e.target.value); }}>
            <option value="all">Semua Bidang</option>
            <option value="Teknologi Informasi">Teknologi Informasi</option>
            <option value="Kedokteran">Kedokteran</option>
            <option value="Pertanian">Pertanian</option>
            <option value="Teknik">Teknik</option>
            <option value="Sosial Ekonomi">Sosial Ekonomi</option>
          </select>
          <select className="border rounded px-2 py-1 text-sm" value={selectedProvince} onChange={e => setSelectedProvince(e.target.value)}>
            <option value="">Semua Provinsi</option>
            <option value="DKI Jakarta">DKI Jakarta</option>
            <option value="Jawa Barat">Jawa Barat</option>
            <option value="Jawa Timur">Jawa Timur</option>
            <option value="DI Yogyakarta">DI Yogyakarta</option>
            <option value="Jawa Tengah">Jawa Tengah</option>
          </select>
        </div>
      </div>

      {/* Wizdam Score explanation */}
      <div className="bg-blue-50 p-4 rounded-lg mb-6">
        <h3 className="text-md font-semibold mb-2">Tentang Wizdam Impact Score</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
          {[
            { label: 'Akademik',  pct: '40%', color: 'blue' },
            { label: 'Sosial',    pct: '25%', color: 'green' },
            { label: 'Ekonomi',   pct: '20%', color: 'yellow' },
            { label: 'SDGs',      pct: '15%', color: 'purple' },
          ].map(({ label, pct, color }) => (
            <div key={label} className={`bg-${color}-100 rounded p-2 text-center`}>
              <p className="font-semibold">{pct}</p>
              <p className="text-xs text-gray-600">{label}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Distribution chart */}
      {fieldDist.length > 0 && (
        <div className="mb-6">
          <h3 className="text-md font-semibold mb-3">Distribusi Peneliti berdasarkan Bidang</h3>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={fieldDist}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="field" tick={{ fontSize: 11 }} />
              <YAxis />
              <Tooltip />
              <Legend />
              <Bar dataKey="count"     fill="#8884d8" name="Jumlah Peneliti" />
              <Bar dataKey="avgImpact" fill="#82ca9d" name="Skor Rata-rata"  />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Table */}
      {error ? (
        <p className="text-red-500 text-sm py-4">{error}</p>
      ) : loading ? (
        <div className="space-y-2">{[...Array(6)].map((_, i) => <Skeleton key={i} className="h-10" />)}</div>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Peneliti</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Afiliasi</th>
                  <SortHeader label="H-Index"    field="h_index"          sortBy={sortBy} sortDir={sortDir} onSort={handleSort} />
                  <SortHeader label="Publikasi"  field="total_publications" sortBy={sortBy} sortDir={sortDir} onSort={handleSort} />
                  <SortHeader label="Sitasi"     field="total_citations"  sortBy={sortBy} sortDir={sortDir} onSort={handleSort} />
                  <SortHeader label="Wizdam Score" field="wizdam_score"   sortBy={sortBy} sortDir={sortDir} onSort={handleSort} />
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {sortedRows.map(r => (
                  <tr
                    key={r.id}
                    className={`hover:bg-gray-50 cursor-pointer ${selectedResearcher?.id === r.id ? 'bg-blue-50' : ''}`}
                    onClick={() => openDetail(r)}
                  >
                    <td className="px-4 py-2">
                      <div className="text-sm font-medium text-gray-900">{r.full_name}</div>
                      <div className="text-xs text-gray-500">
                        {Array.isArray(r.field_of_study) ? r.field_of_study[0] : r.field_of_study}
                      </div>
                    </td>
                    <td className="px-4 py-2">
                      <div className="text-sm text-gray-900">{r.institution_name}</div>
                      <div className="text-xs text-gray-500">{r.province}</div>
                    </td>
                    <td className="px-4 py-2 text-sm text-gray-900">{r.h_index}</td>
                    <td className="px-4 py-2 text-sm text-gray-900">{r.total_publications}</td>
                    <td className="px-4 py-2 text-sm text-gray-900">{(r.total_citations ?? 0).toLocaleString('id-ID')}</td>
                    <td className="px-4 py-2">
                      <div className="flex items-center">
                        <div className="flex-1 bg-gray-200 rounded-full h-2.5 mr-2">
                          <div className="bg-blue-600 h-2.5 rounded-full" style={{ width: `${Math.min(100, r.wizdam_score)}%` }} />
                        </div>
                        <span className="text-sm font-medium text-gray-900">{r.wizdam_score}</span>
                      </div>
                    </td>
                  </tr>
                ))}
                {sortedRows.length === 0 && (
                  <tr><td colSpan={6} className="text-center text-gray-400 py-8">Tidak ada peneliti ditemukan.</td></tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div className="mt-4 flex justify-between items-center text-sm">
            <span className="text-gray-500">Total: {meta.total.toLocaleString('id-ID')} peneliti</span>
            <div className="flex gap-2">
              <button disabled={currentPage <= 1} onClick={() => fetchPage(currentPage - 1)}
                className="border rounded px-3 py-1 disabled:text-gray-300 hover:bg-gray-50">Sebelumnya</button>
              <span className="px-3 py-1 text-gray-600">{currentPage} / {meta.pages}</span>
              <button disabled={currentPage >= meta.pages} onClick={() => fetchPage(currentPage + 1)}
                className="border rounded px-3 py-1 disabled:text-gray-300 hover:bg-gray-50">Berikutnya</button>
            </div>
          </div>
        </>
      )}

      {/* Researcher Detail Panel */}
      {selectedResearcher && (
        <div className="mt-6 bg-blue-50 p-4 rounded-lg">
          <div className="flex justify-between items-start mb-4">
            <h3 className="text-md font-semibold">{selectedResearcher.full_name}</h3>
            <button onClick={() => setSelectedResearcher(null)} className="text-gray-500 hover:text-gray-700">✕</button>
          </div>

          {detailLoading ? (
            <div className="space-y-2">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-10" />)}</div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <div className="flex items-center mb-3">
                  <div className="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mr-4 text-xl font-semibold text-blue-700">
                    {getInitials(selectedResearcher.full_name)}
                  </div>
                  <div>
                    <p className="text-sm text-gray-700">{selectedResearcher.department}</p>
                    <p className="text-sm text-gray-700">{selectedResearcher.institution_name}, {selectedResearcher.city || selectedResearcher.province}</p>
                    <p className="text-sm text-blue-600 font-medium">Wizdam Score: {selectedResearcher.wizdam_score}</p>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3 mt-4">
                  {[
                    { label: 'H-Index',    value: selectedResearcher.h_index },
                    { label: 'i10-Index',  value: selectedResearcher.i10_index },
                    { label: 'Publikasi',  value: selectedResearcher.total_publications },
                    { label: 'Sitasi',     value: (selectedResearcher.total_citations ?? 0).toLocaleString('id-ID') },
                  ].map(({ label, value }) => (
                    <div key={label} className="bg-white rounded p-3 shadow-sm">
                      <p className="text-xs text-gray-500">{label}</p>
                      <p className="text-xl font-bold">{value}</p>
                    </div>
                  ))}
                </div>

                {/* SDG primary goals */}
                {(selectedResearcher.sdgs_primary_goals ?? []).length > 0 && (
                  <div className="mt-4">
                    <h4 className="text-sm font-semibold mb-2">SDG Utama:</h4>
                    <div className="flex flex-wrap gap-1">
                      {selectedResearcher.sdgs_primary_goals.map(sdg => (
                        <span key={sdg} className="text-xs px-2 py-0.5 bg-green-100 text-green-800 rounded-full">SDG {sdg}</span>
                      ))}
                    </div>
                  </div>
                )}

                {/* Recent publications */}
                {(selectedResearcher.recent_publications ?? []).length > 0 && (
                  <div className="mt-4">
                    <h4 className="text-sm font-semibold mb-2">Publikasi Terbaru:</h4>
                    <ul className="space-y-2">
                      {selectedResearcher.recent_publications.slice(0, 3).map(pub => (
                        <li key={pub.id} className="bg-white rounded p-2 text-sm">
                          <div className="font-medium line-clamp-2">{pub.title}</div>
                          <div className="text-xs text-gray-600 mt-1 flex justify-between">
                            <span>{pub.journal_title} ({pub.publication_year})</span>
                            <span>{pub.cited_by_count} sitasi</span>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>

              {/* Radar chart */}
              <div>
                <h4 className="text-sm font-semibold mb-3">Profil Dampak:</h4>
                <ResponsiveContainer width="100%" height={240}>
                  <RadarChart cx="50%" cy="50%" outerRadius="80%" data={radarData}>
                    <PolarGrid />
                    <PolarAngleAxis dataKey="name" />
                    <PolarRadiusAxis angle={30} domain={[0, 100]} />
                    <Radar name={selectedResearcher.full_name} dataKey="value" stroke="#8884d8" fill="#8884d8" fillOpacity={0.5} />
                    <Tooltip />
                  </RadarChart>
                </ResponsiveContainer>

                {/* Impact pillars */}
                {selectedResearcher.impact_pillars && (
                  <div className="mt-4 bg-white rounded p-3 shadow-sm">
                    <h4 className="text-sm font-semibold mb-2">4 Pilar Wizdam Score:</h4>
                    {[
                      { key: 'academic', label: 'Akademik (40%)',  color: 'blue' },
                      { key: 'social',   label: 'Sosial (25%)',    color: 'green' },
                      { key: 'economic', label: 'Ekonomi (20%)',   color: 'yellow' },
                      { key: 'sdg',      label: 'SDGs (15%)',      color: 'purple' },
                    ].map(({ key, label, color }) => (
                      <div key={key} className="mb-2">
                        <div className="flex justify-between text-xs mb-0.5">
                          <span>{label}</span>
                          <span>{selectedResearcher.impact_pillars[key]}</span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-2">
                          <div className={`bg-${color}-500 h-2 rounded-full`}
                            style={{ width: `${Math.min(100, selectedResearcher.impact_pillars[key])}%` }} />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default TopResearchersComponent;
