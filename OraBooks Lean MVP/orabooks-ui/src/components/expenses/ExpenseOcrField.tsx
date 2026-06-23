import ConfidenceBadge from '@/components/classification/ConfidenceBadge';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

type ExpenseOcrFieldProps = {
  label: string;
  fieldKey: string;
  value: string;
  type?: 'text' | 'date' | 'number';
  threshold?: number;
  fieldConfidence?: number | null;
  readOnly?: boolean;
  onChange?: (value: string) => void;
};

export default function ExpenseOcrField({
  label,
  fieldKey,
  value,
  type = 'text',
  threshold = 70,
  fieldConfidence,
  readOnly = false,
  onChange,
}: ExpenseOcrFieldProps) {
  return (
    <label className="block text-sm" data-field={fieldKey}>
      <span className="mb-1 flex flex-wrap items-center gap-2">
        <span className="font-semibold text-slate-700">{label}</span>
        {fieldConfidence != null && (
          <ConfidenceBadge value={fieldConfidence} threshold={threshold} />
        )}
      </span>
      {readOnly ? (
        <p className="rounded-lg border border-border bg-slate-50/80 px-3.5 py-2.5 text-sm text-slate-700">
          {value || '—'}
        </p>
      ) : (
        <input
          type={type}
          className={fieldClass}
          value={value}
          onChange={(e) => onChange?.(e.target.value)}
        />
      )}
    </label>
  );
}
