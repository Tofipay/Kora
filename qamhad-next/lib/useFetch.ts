'use client';
import { useEffect, useState, useCallback } from 'react';

export interface FetchState<T> { data: T; loading: boolean; error: boolean; stale: boolean; reload: () => void; }

/**
 * Generic client fetch hook over the PHP API helpers. `fn` returns
 * `{ data, stale }`; the hook tracks loading/error and exposes a manual reload.
 */
export function useFetch<T>(fn: () => Promise<{ data: T; stale: boolean }>, deps: any[], initial: T): FetchState<T> {
  const [data, setData] = useState<T>(initial);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [stale, setStale] = useState(false);
  const [nonce, setNonce] = useState(0);
  const reload = useCallback(() => setNonce((n) => n + 1), []);

  useEffect(() => {
    let alive = true;
    setLoading(true); setError(false);
    fn().then(({ data, stale }) => {
      if (!alive) return;
      setData(data); setStale(stale); setLoading(false);
    }).catch(() => { if (alive) { setError(true); setLoading(false); } });
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, nonce]);

  return { data, loading, error, stale, reload };
}
