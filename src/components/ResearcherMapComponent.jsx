import React, { useState, useEffect, useRef } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { useAppContext } from '../context/AppContext';

/**
 * Peta distribusi peneliti & institusi menggunakan Leaflet.js.
 * Leaflet diload secara dynamic agar tidak break SSR/CRA tree-shaking.
 *
 * Tampilan dua mode:
 *   - province  : CircleMarker per provinsi (radius ~ jumlah peneliti)
 *   - institution: Marker per institusi (koordinat GPS)
 */

const Skeleton = ({ className = '' }) => <div className={`animate-pulse bg-gray-200 rounded ${className}`} />;

// ── Warna berdasarkan skor dampak ─────────────────────────────────────────
function scoreColor(score) {
  if (score >= 80) return '#1d4ed8';
  if (score >= 70) return '#059669';
  if (score >= 60) return '#d97706';
  return '#dc2626';
}

// ── Radius marker berdasarkan jumlah peneliti ─────────────────────────────
function researcherRadius(count) {
  return Math.max(6, Math.min(30, Math.sqrt(count ?? 1) * 1.2));
}

const ResearcherDistributionMap = () => {
  const [mapView,          setMapView]          = useState('province');
  const [selectedItem,     setSelectedItem]     = useState(null);
  const [leafletReady,     setLeafletReady]     = useState(false);
  const [leafletError,     setLeafletError]     = useState(null);

  const mapRef        = useRef(null);  // DOM node
  const leafletMapRef = useRef(null);  // L.Map instance
  const layerRef      = useRef(null);  // LayerGroup

  const { mapData, loading, errors, refetch } = useAppContext();

  // Muat Leaflet lazily dan trigger map data load
  useEffect(() => {
    refetch.map();

    import('leaflet')
      .then(L => {
        // Patch icon default Leaflet (broken dengan bundler)
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
          iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
          iconUrl:       'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
          shadowUrl:     'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        });
        window._L = L;
        setLeafletReady(true);
      })
      .catch(() => setLeafletError('Gagal memuat library peta.'));

    // Inject Leaflet CSS
    if (!document.getElementById('leaflet-css')) {
      const link = document.createElement('link');
      link.id   = 'leaflet-css';
      link.rel  = 'stylesheet';
      link.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
      document.head.appendChild(link);
    }
  }, []);  // eslint-disable-line

  // Inisialisasi peta saat Leaflet siap dan DOM container tersedia
  useEffect(() => {
    if (!leafletReady || !mapRef.current || leafletMapRef.current) return;
    const L = window._L;
    const map = L.map(mapRef.current, { zoomControl: true, scrollWheelZoom: false }).setView([-2.5, 118], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 18,
    }).addTo(map);
    leafletMapRef.current = map;
    layerRef.current = L.layerGroup().addTo(map);
  }, [leafletReady]);

  // Render ulang markers setiap kali data atau mapView berubah
  useEffect(() => {
    if (!leafletMapRef.current || !window._L || !mapData) return;
    const L = window._L;
    layerRef.current.clearLayers();

    if (mapView === 'province') {
      (mapData.by_province ?? []).forEach(p => {
        // Gunakan koordinat kasar tiap provinsi (dari institutions yang punya lat/lng)
        const inst = (mapData.institutions ?? []).find(i => i.province === p.province && i.latitude);
        if (!inst) return;
        const circle = L.circleMarker([inst.latitude, inst.longitude], {
          radius:      researcherRadius(p.researcher_count),
          color:       scoreColor(p.avg_impact),
          fillColor:   scoreColor(p.avg_impact),
          fillOpacity: 0.65,
          weight:      2,
        }).bindPopup(`
          <b>${p.province}</b><br>
          Peneliti: ${Number(p.researcher_count).toLocaleString('id-ID')}<br>
          Avg Wizdam Score: ${Number(p.avg_impact).toFixed(1)}<br>
          Institusi: ${p.institution_count}
        `);
        circle.on('click', () => setSelectedItem({ ...p, _type: 'province' }));
        layerRef.current.addLayer(circle);
      });
    } else {
      (mapData.institutions ?? [])
        .filter(i => i.latitude && i.longitude)
        .forEach(inst => {
          const marker = L.circleMarker([inst.latitude, inst.longitude], {
            radius:      researcherRadius(inst.total_researchers),
            color:       scoreColor(inst.wizdam_score),
            fillColor:   scoreColor(inst.wizdam_score),
            fillOpacity: 0.7,
            weight:      2,
          }).bindPopup(`
            <b>${inst.name}</b><br>
            ${inst.province}<br>
            Peneliti: ${inst.total_researchers}<br>
            Wizdam Score: ${inst.wizdam_score}
          `);
          marker.on('click', () => setSelectedItem({ ...inst, _type: 'institution' }));
          layerRef.current.addLayer(marker);
        });
    }
  }, [mapData, mapView]);

  // Data chart: top provinsi
  const provinceChartData = (mapData?.by_province ?? [])
    .slice(0, 10)
    .map(p => ({
      province:        p.province,
      researchers:     Number(p.researcher_count ?? 0),
      avg_impact:      Number(p.avg_impact       ?? 0),
    }));

  const isLoadingMap = loading.map;

  return (
    <div className="bg-white rounded-lg shadow-md p-4">
      {/* Header + controls */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
        <h2 className="text-lg font-semibold">Peta Distribusi Peneliti Indonesia</h2>
        <div className="flex gap-2">
          <button
            onClick={() => setMapView('province')}
            className={`px-3 py-1 text-sm rounded ${mapView === 'province' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
          >
            Per Provinsi
          </button>
          <button
            onClick={() => setMapView('institution')}
            className={`px-3 py-1 text-sm rounded ${mapView === 'institution' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
          >
            Per Institusi
          </button>
        </div>
      </div>

      {/* Legend */}
      <div className="flex flex-wrap gap-4 mb-3 text-xs text-gray-600">
        <span>Ukuran lingkaran = jumlah peneliti</span>
        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-full bg-blue-700 inline-block" /> Skor ≥ 80</span>
        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-full bg-emerald-600 inline-block" /> Skor 70–79</span>
        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-full bg-amber-600 inline-block" /> Skor 60–69</span>
        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-full bg-red-600 inline-block" /> Skor &lt; 60</span>
      </div>

      {/* Map container */}
      {leafletError ? (
        <div className="h-96 flex items-center justify-center bg-gray-50 rounded text-red-500 text-sm">{leafletError}</div>
      ) : isLoadingMap || !leafletReady ? (
        <Skeleton className="h-96" />
      ) : errors.map ? (
        <div className="h-96 flex items-center justify-center bg-gray-50 rounded text-gray-400 text-sm">{errors.map}</div>
      ) : (
        <div ref={mapRef} className="h-96 rounded border border-gray-200 z-0" style={{ minHeight: 384 }} />
      )}

      <div className="mt-2 text-xs text-gray-400 text-right">
        Peta: © OpenStreetMap contributors
      </div>

      {/* Info panel item yang dipilih */}
      {selectedItem && (
        <div className="mt-4 bg-blue-50 p-4 rounded-lg">
          <div className="flex justify-between items-start mb-2">
            <h3 className="font-semibold text-gray-800">
              {selectedItem._type === 'province' ? selectedItem.province : selectedItem.name}
            </h3>
            <button onClick={() => setSelectedItem(null)} className="text-gray-400 hover:text-gray-600">✕</button>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            {selectedItem._type === 'province' ? (
              <>
                <div className="bg-white rounded p-2 shadow-sm text-center">
                  <p className="text-xs text-gray-500">Peneliti</p>
                  <p className="text-lg font-bold">{Number(selectedItem.researcher_count ?? 0).toLocaleString('id-ID')}</p>
                </div>
                <div className="bg-white rounded p-2 shadow-sm text-center">
                  <p className="text-xs text-gray-500">Institusi</p>
                  <p className="text-lg font-bold">{selectedItem.institution_count ?? 0}</p>
                </div>
                <div className="bg-white rounded p-2 shadow-sm text-center">
                  <p className="text-xs text-gray-500">Avg Wizdam Score</p>
                  <p className="text-lg font-bold">{Number(selectedItem.avg_impact ?? 0).toFixed(1)}</p>
                </div>
              </>
            ) : (
              <>
                <div className="bg-white rounded p-2 shadow-sm text-center">
                  <p className="text-xs text-gray-500">Provinsi</p>
                  <p className="text-sm font-semibold">{selectedItem.province}</p>
                </div>
                <div className="bg-white rounded p-2 shadow-sm text-center">
                  <p className="text-xs text-gray-500">Peneliti</p>
                  <p className="text-lg font-bold">{selectedItem.total_researchers}</p>
                </div>
                <div className="bg-white rounded p-2 shadow-sm text-center">
                  <p className="text-xs text-gray-500">Publikasi</p>
                  <p className="text-lg font-bold">{selectedItem.total_publications}</p>
                </div>
                <div className="bg-white rounded p-2 shadow-sm text-center">
                  <p className="text-xs text-gray-500">Wizdam Score</p>
                  <p className="text-lg font-bold">{selectedItem.wizdam_score}</p>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* Chart distribusi provinsi */}
      {provinceChartData.length > 0 && (
        <div className="mt-6">
          <h3 className="text-md font-semibold mb-3">Top 10 Provinsi — Distribusi Peneliti</h3>
          <ResponsiveContainer width="100%" height={280}>
            <BarChart data={provinceChartData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="province" tick={{ fontSize: 10 }} angle={-20} textAnchor="end" height={50} />
              <YAxis yAxisId="left"  orientation="left"  stroke="#8884d8" />
              <YAxis yAxisId="right" orientation="right" stroke="#82ca9d" />
              <Tooltip />
              <Legend />
              <Bar yAxisId="left"  dataKey="researchers" fill="#8884d8" name="Jumlah Peneliti" />
              <Bar yAxisId="right" dataKey="avg_impact"  fill="#82ca9d" name="Avg Wizdam Score" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Data kosong fallback */}
      {!isLoadingMap && leafletReady && !errors.map && (mapData?.by_province ?? []).length === 0 && (
        <p className="text-center text-gray-400 text-sm mt-4">
          Data peta belum tersedia. Pastikan database sudah diisi dan endpoint <code>/api/v1/institutions/map</code> berjalan.
        </p>
      )}
    </div>
  );
};

export default ResearcherDistributionMap;
