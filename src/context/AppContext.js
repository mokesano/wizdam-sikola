import React, { createContext, useState, useContext, useEffect, useCallback } from 'react';
import { statsApi, researcherApi, articleApi, institutionApi } from '../services/api';

const AppContext = createContext();

export const useAppContext = () => useContext(AppContext);

export const AppProvider = ({ children }) => {
  // ── Filter state ──────────────────────────────────────────────────────────
  const [selectedField,  setSelectedField]  = useState('all');
  const [timeRange,      setTimeRange]      = useState('all');
  const [searchQuery,    setSearchQuery]    = useState('');

  // ── Global data state ─────────────────────────────────────────────────────
  const [stats,          setStats]          = useState(null);
  const [topResearchers, setTopResearchers] = useState([]);
  const [topArticles,    setTopArticles]    = useState([]);
  const [mapData,        setMapData]        = useState(null);
  const [trends,         setTrends]         = useState([]);

  // ── Loading / error state per resource ───────────────────────────────────
  const [loading, setLoading] = useState({
    stats: true, researchers: true, articles: true, map: false, trends: false,
  });
  const [errors, setErrors] = useState({});

  const setLoad  = (key, val) => setLoading(prev => ({ ...prev, [key]: val }));
  const setError = (key, msg) => setErrors(prev => ({ ...prev, [key]: msg }));

  // ── Fetchers ──────────────────────────────────────────────────────────────
  const fetchStats = useCallback(async () => {
    setLoad('stats', true);
    try {
      const res = await statsApi.getSummary();
      setStats(res.data ?? res);
    } catch (err) {
      setError('stats', err.message);
    } finally {
      setLoad('stats', false);
    }
  }, []);

  const fetchTopResearchers = useCallback(async () => {
    setLoad('researchers', true);
    try {
      const res = await researcherApi.top(10);
      setTopResearchers(res.data ?? []);
    } catch (err) {
      setError('researchers', err.message);
    } finally {
      setLoad('researchers', false);
    }
  }, []);

  const fetchTopArticles = useCallback(async () => {
    setLoad('articles', true);
    try {
      const res = await articleApi.top(10);
      setTopArticles(res.data ?? []);
    } catch (err) {
      setError('articles', err.message);
    } finally {
      setLoad('articles', false);
    }
  }, []);

  const fetchMapData = useCallback(async () => {
    setLoad('map', true);
    try {
      const res = await institutionApi.map();
      setMapData(res.data ?? null);
    } catch (err) {
      setError('map', err.message);
    } finally {
      setLoad('map', false);
    }
  }, []);

  const fetchTrends = useCallback(async () => {
    setLoad('trends', true);
    try {
      const year = new Date().getFullYear();
      const res  = await articleApi.trends({ from: year - 5, to: year });
      setTrends(res.data ?? []);
    } catch (err) {
      setError('trends', err.message);
    } finally {
      setLoad('trends', false);
    }
  }, []);

  // Bootstrap: muat semua data global saat pertama render
  useEffect(() => {
    fetchStats();
    fetchTopResearchers();
    fetchTopArticles();
    fetchTrends();
  }, [fetchStats, fetchTopResearchers, fetchTopArticles, fetchTrends]);

  // Map data dimuat lazily (hanya saat halaman peta dibuka)
  const loadMapData = useCallback(() => {
    if (!mapData && !loading.map) fetchMapData();
  }, [mapData, loading.map, fetchMapData]);

  const value = {
    // Filters
    selectedField,  setSelectedField,
    timeRange,      setTimeRange,
    searchQuery,    setSearchQuery,

    // Data
    stats,
    topResearchers,
    topArticles,
    mapData,
    trends,

    // Status
    loading,
    errors,

    // Actions
    refetch: {
      stats:       fetchStats,
      researchers: fetchTopResearchers,
      articles:    fetchTopArticles,
      map:         loadMapData,
      trends:      fetchTrends,
    },
  };

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
};
