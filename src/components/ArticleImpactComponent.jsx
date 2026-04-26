import React, { useState, useCallback, useEffect } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  RadarChart, Radar, PolarGrid, PolarAngleAxis, PolarRadiusAxis,
} from 'recharts';
import { articleApi, impactScoreApi } from '../services/api';
import SdgBadge from './SdgBadge';

const Skeleton = ({ className = '' }) => <div className={`animate-pulse bg-gray-200 rounded ${className}`} />;
const ITEMS_PER_PAGE = 20;

const ArticleImpactMetrics = () => {
  const [searchQ,       setSearchQ]       = useState('');
  const [yearFilter,    setYearFilter]    = useState('');
  const [typeFilter,    setTypeFilter]    = useState('all');
  const [sortBy,        setSortBy]        = useState('wizdam_score');
  const [sortDir,       setSortDir]       = useState('desc');
  const [currentPage,   setCurrentPage]   = useState(1);
  const [selectedArticle, setSelectedArticle] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const [rows,    setRows]    = useState([]);
  const [meta,    setMeta]    = useState({ total: 0, pages: 1 });
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState(null);

  const fetchPage = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const res = await articleApi.list({
        q:        searchQ,
        year:     yearFilter || undefined,
        type:     typeFilter,
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
  }, [searchQ, yearFilter, typeFilter]);

  useEffect(() => { fetchPage(1); }, [fetchPage]);

  const openDetail = async (article) => {
    setSelectedArticle({ ...article, _loading: true });
    setDetailLoading(true);
    try {
      const res = await articleApi.detail(article.id);
      setSelectedArticle(res.data ?? article);
    } catch {
      setSelectedArticle({ ...article, _partial: true });
    } finally {
      setDetailLoading(false);
    }
  };

  const handleSort = (field) => {
    if (sortBy === field) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortBy(field); setSortDir('desc'); }
  };

  const sortedRows = [...rows].sort((a, b) => {
    const mul = sortDir === 'asc' ? 1 : -1;
    return mul * ((a[sortBy] ?? 0) - (b[sortBy] ?? 0));
  });

  const pillarData = selectedArticle?.impact_pillars ? [
    { name: 'Akademik (40%)', value: selectedArticle.impact_pillars.academic },
    { name: 'Sosial (25%)',   value: selectedArticle.impact_pillars.social },
    { name: 'Ekonomi (20%)', value: selectedArticle.impact_pillars.economic },
    { name: 'SDGs (15%)',     value: selectedArticle.impact_pillars.sdg },
  ] : [];

  const SortTh = ({ label, field }) => (
    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer"
      onClick={() => handleSort(field)}>
      <div className="flex items-center gap-1">
        {label}{sortBy === field && <span>{sortDir === 'desc' ? '↓' : '↑'}</span>}
      </div>
    </th>
  );

  const currentYear = new Date().getFullYear();

  return (
    <div className="bg-white rounded-lg shadow-md p-4">
      {/* Filters */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
        <h2 className="text-lg font-semibold">Dampak Artikel Penelitian</h2>
        <div className="flex flex-wrap gap-2">
          <input type="text" placeholder="Cari judul atau penulis…"
            className="border rounded px-2 py-1 text-sm w-48"
            value={searchQ}
            onChange={e => setSearchQ(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && fetchPage(1)} />
          <select className="border rounded px-2 py-1 text-sm" value={yearFilter}
            onChange={e => setYearFilter(e.target.value)}>
            <option value="">Semua Tahun</option>
            {[...Array(6)].map((_, i) => (
              <option key={i} value={currentYear - i}>{currentYear - i}</option>
            ))}
          </select>
          <select className="border rounded px-2 py-1 text-sm" value={typeFilter}
            onChange={e => setTypeFilter(e.target.value)}>
            <option value="all">Semua Tipe</option>
            <option value="article">Artikel</option>
            <option value="conference">Konferensi</option>
            <option value="book_chapter">Book Chapter</option>
          </select>
        </div>
      </div>

      {/* Impact Score explanation */}
      <div className="bg-blue-50 p-3 rounded-lg mb-6 text-sm">
        <p className="font-semibold mb-1">Wizdam Impact Score = 4 Pilar:</p>
        <div className="flex flex-wrap gap-2">
          {[['Akademik', '40%', 'blue'], ['Sosial', '25%', 'green'], ['Ekonomi', '20%', 'yellow'], ['SDGs', '15%', 'purple']].map(([l, p, c]) => (
            <span key={l} className={`bg-${c}-100 text-${c}-800 px-2 py-0.5 rounded text-xs font-medium`}>{l} {p}</span>
          ))}
        </div>
      </div>

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
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Judul / Penulis</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jurnal / Tahun</th>
                  <SortTh label="Sitasi"       field="cited_by_count" />
                  <SortTh label="Wizdam Score" field="wizdam_score"   />
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SDGs</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {sortedRows.map(a => (
                  <tr key={a.id}
                    className={`hover:bg-gray-50 cursor-pointer ${selectedArticle?.id === a.id ? 'bg-blue-50' : ''}`}
                    onClick={() => openDetail(a)}>
                    <td className="px-4 py-2">
                      <div className="text-sm font-medium text-gray-900 line-clamp-2">{a.title}</div>
                      <div className="text-xs text-gray-500 mt-0.5">{a.authors_list}</div>
                    </td>
                    <td className="px-4 py-2">
                      <div className="text-sm text-gray-900">{a.journal_title}</div>
                      <div className="text-xs text-gray-500">{a.publication_year}</div>
                    </td>
                    <td className="px-4 py-2 text-sm text-gray-900">{a.cited_by_count}</td>
                    <td className="px-4 py-2">
                      <div className="flex items-center">
                        <div className="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                          <div className="bg-blue-600 h-2 rounded-full" style={{ width: `${Math.min(100, a.wizdam_score)}%` }} />
                        </div>
                        <span className="text-sm font-medium">{a.wizdam_score}</span>
                      </div>
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex flex-wrap gap-1">
                        {(a.sdgs_goals ?? []).slice(0, 2).map(s => (
                          <span key={s.sdg ?? s} className="text-xs px-1.5 py-0.5 bg-green-100 text-green-700 rounded">
                            SDG {s.sdg ?? s}
                          </span>
                        ))}
                      </div>
                    </td>
                  </tr>
                ))}
                {sortedRows.length === 0 && (
                  <tr><td colSpan={5} className="text-center text-gray-400 py-8">Tidak ada artikel ditemukan.</td></tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="mt-4 flex justify-between items-center text-sm">
            <span className="text-gray-500">Total: {meta.total.toLocaleString('id-ID')} artikel</span>
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

      {/* Article Detail Panel */}
      {selectedArticle && (
        <div className="mt-6 bg-blue-50 p-4 rounded-lg">
          <div className="flex justify-between items-start mb-3">
            <h3 className="text-md font-semibold pr-4">{selectedArticle.title}</h3>
            <button onClick={() => setSelectedArticle(null)} className="text-gray-500 hover:text-gray-700 shrink-0">✕</button>
          </div>

          {detailLoading ? (
            <div className="space-y-2">{[...Array(3)].map((_, i) => <Skeleton key={i} className="h-10" />)}</div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-600"><span className="font-medium">Penulis:</span> {selectedArticle.authors_list}</p>
                <p className="text-sm text-gray-600 mt-1"><span className="font-medium">Jurnal:</span> {selectedArticle.journal_title} ({selectedArticle.publication_year})</p>
                {selectedArticle.doi && (
                  <p className="text-sm text-gray-600 mt-1">
                    <span className="font-medium">DOI:</span>{' '}
                    <a href={`https://doi.org/${selectedArticle.doi}`} target="_blank" rel="noreferrer"
                      className="text-blue-600 hover:underline">{selectedArticle.doi}</a>
                  </p>
                )}
                <p className="text-sm text-gray-600 mt-1"><span className="font-medium">Sitasi:</span> {selectedArticle.cited_by_count}</p>
                <p className="text-sm text-gray-600 mt-1">
                  <span className="font-medium">Akses:</span>{' '}
                  <span className={`px-1.5 py-0.5 rounded text-xs ${selectedArticle.access_type === 'open_access' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                    {selectedArticle.access_type === 'open_access' ? 'Open Access' : 'Berlangganan'}
                  </span>
                </p>

                {selectedArticle.abstract && (
                  <div className="mt-3">
                    <p className="text-sm font-medium">Abstrak:</p>
                    <p className="text-xs text-gray-600 mt-1 line-clamp-4">{selectedArticle.abstract}</p>
                  </div>
                )}

                {/* SDG Tags */}
                {(selectedArticle.sdg_tags ?? selectedArticle.sdgs_goals ?? []).length > 0 && (
                  <div className="mt-3">
                    <p className="text-sm font-medium mb-1">Keterkaitan SDGs:</p>
                    <div className="flex flex-wrap gap-1">
                      {(selectedArticle.sdg_tags ?? selectedArticle.sdgs_goals).map(s => (
                        <SdgBadge key={s.sdg ?? s} sdg={s.sdg ?? s} label={s.label} score={s.score} />
                      ))}
                    </div>
                  </div>
                )}
              </div>

              {/* Pillar chart */}
              <div>
                {pillarData.length > 0 ? (
                  <>
                    <h4 className="text-sm font-semibold mb-3">4 Pilar Wizdam Score: {selectedArticle.impact_pillars?.composite}</h4>
                    <ResponsiveContainer width="100%" height={220}>
                      <BarChart data={pillarData} layout="vertical">
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis type="number" domain={[0, 100]} />
                        <YAxis type="category" dataKey="name" width={120} tick={{ fontSize: 11 }} />
                        <Tooltip />
                        <Bar dataKey="value" fill="#8884d8" name="Skor" radius={[0, 4, 4, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  </>
                ) : (
                  <div className="text-center py-12 text-gray-400 text-sm">
                    <p>Impact Score belum dihitung untuk artikel ini.</p>
                    <button
                      className="mt-2 text-blue-600 hover:underline text-xs"
                      onClick={async () => {
                        await impactScoreApi.calculate('article', selectedArticle.id);
                        openDetail(selectedArticle);
                      }}>
                      Hitung sekarang
                    </button>
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

export default ArticleImpactMetrics;
