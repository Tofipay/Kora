'use client';
import { usePathname } from 'next/navigation';
import { useFetch } from '@/lib/useFetch';
import { playerApi } from '@/lib/api';
import { idFromSlug, playerImg, Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { Loading, Empty } from '@/components/State';

/** Player page — profile header + vitals + per-competition stats. */
export default function PlayerDetail() {
  const path = usePathname() || '';
  const id = idFromSlug(path.split('/').filter(Boolean).pop() || '');
  const { data, loading } = useFetch<Dict | null>(() => playerApi.get(id), [id], null);

  if (loading) return <div className="container section"><Loading rows={5} /></div>;
  const p: Dict | null = data ? ((data.player as Dict) ?? data) : null;
  if (!p || !(p.title || p.full_title || p.name)) return <div className="container section"><Empty text={t('player.no_stats')} /></div>;

  const label = String(p.title ?? p.full_title ?? p.name ?? '—');
  const vitals: [string, any][] = [
    [t('player.team'), p.team_name],
    [t('player.position'), p.position ?? p.player_position],
    [t('player.nationality'), p.country ?? p.nationality],
    [t('player.number'), p.number ?? p.shirt_number],
    [t('player.age'), p.age],
  ];

  return (
    <>
      <div className="container page-head" style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
        <img src={playerImg(p, '128')} alt={label} width={64} height={64} style={{ borderRadius: '50%' }} />
        <h1>{label}</h1>
      </div>
      <div className="container">
        <div className="card glass-soft" style={{ padding: 16 }}>
          <dl className="player-vitals" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(140px,1fr))', gap: 12 }}>
            {vitals.filter(([, v]) => v !== undefined && v !== null && v !== '').map(([k, v]) => (
              <div key={k}><dt style={{ opacity: 0.7, fontSize: 13 }}>{k}</dt><dd style={{ fontWeight: 700 }}>{String(v)}</dd></div>
            ))}
          </dl>
        </div>
      </div>
    </>
  );
}
