'use client';
import { Dict, teamName, teamImg, teamUrl } from '@/lib/helpers';
import { t } from '@/lib/i18n';

/** Standings table — 1:1 port of partials/standings-table.php. */
export default function StandingsTable({ rows, compact = false, highlightTeam = 0 }: { rows: Dict[]; compact?: boolean; highlightTeam?: number }) {
  return (
    <div className="table-wrap">
      <table className={`standings${compact ? ' compact' : ''}`}>
        <thead>
          <tr>
            <th className="col-pos">#</th>
            <th className="col-team">{t('standings.team')}</th>
            <th>{t('standings.played')}</th>
            {!compact && (<>
              <th>{t('standings.win')}</th>
              <th>{t('standings.draw')}</th>
              <th>{t('standings.lose')}</th>
              <th>{t('standings.gf')}</th>
              <th>{t('standings.ga')}</th>
            </>)}
            <th>{t('standings.gd')}</th>
            <th className="col-pts">{t('standings.points')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => {
            const pos = i + 1;
            const team = (r.team_name ?? {}) as Dict;
            const color = String(r.color ?? '');
            const isMe = highlightTeam && Number(r.team_id ?? 0) === highlightTeam;
            return (
              <tr key={pos} className={isMe ? 'row-highlight' : undefined}>
                <td className="col-pos"><span className="pos-pill" style={color ? ({ ['--pos-color' as any]: color }) : undefined}>{pos}</span></td>
                <td className="col-team">
                  <a href={teamUrl(team)}>
                    <img src={teamImg(team)} alt="" width={22} height={22} loading="lazy" decoding="async" />
                    <span>{teamName(team)}</span>
                  </a>
                </td>
                <td>{Number(r.play ?? 0)}</td>
                {!compact && (<>
                  <td>{Number(r.wins ?? 0)}</td>
                  <td>{Number(r.draw ?? 0)}</td>
                  <td>{Number(r.lose ?? 0)}</td>
                  <td>{Number(r.for ?? 0)}</td>
                  <td>{Number(r.against ?? 0)}</td>
                </>)}
                <td>{Number(r.diff ?? 0)}</td>
                <td className="col-pts"><strong>{Number(r.points ?? 0)}</strong></td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
