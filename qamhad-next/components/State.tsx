'use client';
import { t } from '@/lib/i18n';

/** Lightweight skeleton shown while a section's data loads. */
export function Loading({ rows = 4 }: { rows?: number }) {
  return (
    <div className="q-loading" role="status" aria-live="polite" aria-busy="true">
      {Array.from({ length: rows }).map((_, i) => <div className="q-skeleton" key={i} />)}
      <span className="sr-only">{t('misc.loading')}</span>
    </div>
  );
}

/** Empty-state note. */
export function Empty({ text }: { text: string }) {
  return <p className="empty-note">{text}</p>;
}

/** Error state with a retry button (wired to the reused toast/app.js). */
export function ErrorState({ onRetry }: { onRetry?: () => void }) {
  return (
    <div className="q-error">
      <p className="empty-note">{t('misc.error')}</p>
      {onRetry ? <button className="btn btn-ghost" onClick={onRetry}>{t('misc.retry')}</button> : null}
    </div>
  );
}
