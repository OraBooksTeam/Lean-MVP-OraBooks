import { AlertTriangle, RefreshCw, Sparkles } from 'lucide-react';
import WpLink from '@/pages/frontend/components/WpLink';
import Button from '@/components/Button';
import ConfidenceBadge from './ConfidenceBadge';
import TaxHintTooltip from './TaxHintTooltip';

export type ClassificationData = {
  status?: string;
  suggested_account_code?: string | null;
  account_confidence?: number | null;
  tax_hints?: { tax_rate?: number; tax_type?: string } | null;
  reason?: string | null;
  low_confidence?: boolean;
};

type ClassificationPanelProps = {
  classification?: ClassificationData | null;
  threshold?: number;
  canManage?: boolean;
  loading?: boolean;
  recordType: string;
  onApply?: () => void;
  onRerun?: () => void;
  onOverride?: () => void;
};

export default function ClassificationPanel({
  classification,
  threshold = 70,
  canManage = false,
  loading = false,
  onApply,
  onRerun,
  onOverride,
}: ClassificationPanelProps) {
  if (!classification) return null;

  const conf = classification.account_confidence;

  return (
    <div className="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2 text-sm font-semibold text-ink">
          <Sparkles className="h-4 w-4 text-primary" />
          AI Classification
        </div>
        <span className="badge border border-primary/20 bg-white text-primary">{classification.status || 'pending'}</span>
      </div>
      <div className="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
        <p>
          <strong>Suggested account:</strong>{' '}
          {classification.suggested_account_code || '—'}
          {conf != null && (
            <span className="ml-2">
              <ConfidenceBadge value={conf} threshold={threshold} />
            </span>
          )}
        </p>
        <p>
          <strong>Tax hint:</strong>{' '}
          <TaxHintTooltip taxHints={classification.tax_hints} />
        </p>
        {classification.reason && (
          <p className="md:col-span-2 text-xs text-slate-500">{classification.reason}</p>
        )}
      </div>
      {canManage && classification.status === 'processed' && (
        <div className="mt-3 flex flex-wrap gap-2">
          {onApply && (
            <Button size="sm" onClick={onApply} disabled={loading}>
              Apply AI suggestions
            </Button>
          )}
          {onOverride && (
            <Button size="sm" variant="secondary" onClick={onOverride} disabled={loading}>
              Override
            </Button>
          )}
          {onRerun && (
            <Button size="sm" variant="secondary" onClick={onRerun} disabled={loading}>
              <RefreshCw className="h-4 w-4" />
              Rerun classification
            </Button>
          )}
        </div>
      )}
      {classification.low_confidence && (
        <p className="mt-2 flex items-start gap-1.5 text-xs font-medium text-amber-700">
          <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
          <span>
            Low confidence — verify before submitting.{' '}
            <WpLink to="/ai-review" className="underline">
              AI Review
            </WpLink>
          </span>
        </p>
      )}
      {classification.status === 'failed' && (
        <p className="mt-2 text-xs font-medium text-red-700">Classification failed. Try rerun or override manually.</p>
      )}
    </div>
  );
}
