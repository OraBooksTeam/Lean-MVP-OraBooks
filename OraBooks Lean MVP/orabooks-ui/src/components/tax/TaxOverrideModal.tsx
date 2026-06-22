import { useMemo, type ReactNode } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';

export type TaxConfig = { jurisdiction: string; override_reasons?: string[] };

export const DEFAULT_OVERRIDE_REASONS = [
  'WRONG_AI_CLASSIFICATION',
  'LOCAL_TAX_RULE',
  'MANUAL_JURISDICTION_ADJUSTMENT',
  'CUSTOMER_EXEMPTION',
  'REGIONAL_COMPLIANCE_OVERRIDE',
] as const;

type Preview = {
  newTax: number;
  newTotal: number;
};

type Props = {
  open: boolean;
  title: string;
  subtitle: string;
  taxRate: string;
  reasonCode: string;
  jurisdiction: string;
  taxConfigs: TaxConfig[];
  taxLocked: boolean;
  saving: boolean;
  error?: string;
  hasExistingOverride?: boolean;
  currency?: string;
  preview?: Preview | null;
  onClose: () => void;
  onTaxRateChange: (value: string) => void;
  onReasonChange: (value: string) => void;
  onJurisdictionChange: (value: string) => void;
  onApply: () => void;
  onClear?: () => void;
};

export function formatOverrideReason(code: string) {
  return code.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function TaxOverrideModal({
  open,
  title,
  subtitle,
  taxRate,
  reasonCode,
  jurisdiction,
  taxConfigs,
  taxLocked,
  saving,
  error,
  hasExistingOverride,
  currency = 'USD',
  preview,
  onClose,
  onTaxRateChange,
  onReasonChange,
  onJurisdictionChange,
  onApply,
  onClear,
}: Props) {
  const reasonOptions = useMemo(() => {
    const config = taxConfigs.find((c) => c.jurisdiction === jurisdiction);
    return config?.override_reasons?.length ? config.override_reasons : DEFAULT_OVERRIDE_REASONS;
  }, [taxConfigs, jurisdiction]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div
        className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-labelledby="tax-override-title"
      >
        <div className="flex items-center justify-between gap-3">
          <h3 id="tax-override-title" className="text-lg font-semibold text-ink">
            {title}
          </h3>
          <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">
            Close
          </button>
        </div>

        <p className="mt-2 text-sm text-slate-600">{subtitle}</p>

        {taxLocked && (
          <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            Tax is locked for this fiscal period. Override will be locked after posting.
          </div>
        )}

        {error && (
          <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>
        )}

        <div className="mt-4 grid gap-4">
          <Field label="Jurisdiction">
            <select
              value={jurisdiction}
              onChange={(e) => onJurisdictionChange(e.target.value)}
              disabled={taxLocked}
              className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
            >
              {(taxConfigs.length ? taxConfigs : [{ jurisdiction: 'US' }]).map((c) => (
                <option key={c.jurisdiction} value={c.jurisdiction}>
                  {c.jurisdiction}
                </option>
              ))}
            </select>
          </Field>

          <Field
            label="New tax rate (%)"
            hint="Override tax rate. Reason code required for audit."
          >
            <Input
              type="number"
              min="0"
              max="100"
              step="0.01"
              value={taxRate}
              onChange={(e) => onTaxRateChange(e.target.value)}
              disabled={taxLocked}
            />
          </Field>

          <Field label="Reason code" hint="Select the reason for changing tax.">
            <select
              value={reasonCode}
              onChange={(e) => onReasonChange(e.target.value)}
              disabled={taxLocked}
              className="w-full rounded-lg border border-border px-3 py-2.5 text-sm"
            >
              <option value="">Select a reason…</option>
              {reasonOptions.map((r) => (
                <option key={r} value={r}>
                  {formatOverrideReason(r)}
                </option>
              ))}
            </select>
          </Field>

          {preview && (
            <div className="rounded-lg border border-border bg-slate-50 p-3 text-sm">
              <div>New tax: {money(preview.newTax, currency)}</div>
              <div className="font-semibold">New total: {money(preview.newTotal, currency)}</div>
            </div>
          )}
        </div>

        <div className="mt-6 flex flex-wrap justify-end gap-2">
          {hasExistingOverride && onClear && (
            <Button variant="secondary" onClick={onClear} disabled={saving || taxLocked}>
              Clear override
            </Button>
          )}
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={onApply} disabled={saving || taxLocked || !reasonCode}>
            Apply override
          </Button>
        </div>
      </div>
    </div>
  );
}

function Field({
  label,
  hint,
  children,
}: {
  label: string;
  hint?: string;
  children: ReactNode;
}) {
  return (
    <label className="block space-y-1.5 text-sm">
      <span className="font-medium text-slate-700" title={hint}>
        {label}
      </span>
      {hint && <span className="block text-xs text-slate-500">{hint}</span>}
      {children}
    </label>
  );
}

function money(value: number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0));
}
