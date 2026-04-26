import { useState, useEffect, useCallback, useRef } from 'react';

/**
 * Hook generik untuk fetching data dari API.
 * Mengelola: loading, error, data, dan abort otomatis saat unmount.
 *
 * @param {Function} fetcher  - Fungsi async yang memanggil API
 * @param {Array}    deps     - Dependency array (opsional)
 * @param {boolean}  immediate - Langsung fetch saat mount (default: true)
 */
export function useApi(fetcher, deps = [], immediate = true) {
  const [data,    setData]    = useState(null);
  const [loading, setLoading] = useState(immediate);
  const [error,   setError]   = useState(null);
  const abortRef = useRef(null);

  const execute = useCallback(async () => {
    if (abortRef.current) abortRef.current.abort();
    abortRef.current = new AbortController();

    setLoading(true);
    setError(null);

    try {
      const result = await fetcher();
      setData(result?.data ?? result);
    } catch (err) {
      if (err.name !== 'AbortError') {
        setError(err.message || 'Terjadi kesalahan.');
      }
    } finally {
      setLoading(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  useEffect(() => {
    if (immediate) execute();
    return () => abortRef.current?.abort();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [execute]);

  return { data, loading, error, refetch: execute };
}

/**
 * Hook dengan pagination bawaan.
 *
 * @param {Function} fetcher  - (page, perPage) => Promise
 * @param {number}   perPage  - Item per halaman
 * @param {Array}    deps     - Dependency array
 */
export function usePaginatedApi(fetcher, perPage = 20, deps = []) {
  const [page,    setPage]    = useState(1);
  const [data,    setData]    = useState([]);
  const [meta,    setMeta]    = useState({ total: 0, pages: 1 });
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState(null);

  const fetch = useCallback(async (p = page) => {
    setLoading(true);
    setError(null);
    try {
      const result = await fetcher(p, perPage);
      setData(result?.data  ?? []);
      setMeta(result?.meta  ?? { total: 0, pages: 1 });
      setPage(p);
    } catch (err) {
      setError(err.message || 'Terjadi kesalahan.');
    } finally {
      setLoading(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [perPage, ...deps]);

  useEffect(() => { fetch(1); }, [fetch]);

  return {
    data, meta, loading, error,
    page,
    nextPage: () => meta.pages > page ? fetch(page + 1) : undefined,
    prevPage: () => page > 1           ? fetch(page - 1) : undefined,
    goToPage: (p) => fetch(p),
    refetch:  () => fetch(page),
  };
}
