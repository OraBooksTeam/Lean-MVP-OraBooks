type TaxHintTooltipProps = {
  taxHints?: {
    tax_rate?: number;
    tax_type?: string;
  } | null;
};

export default function TaxHintTooltip({ taxHints }: TaxHintTooltipProps) {
  if (!taxHints?.tax_type) {
    return <span className="text-slate-500">—</span>;
  }

  const label = `${taxHints.tax_type} ${taxHints.tax_rate ?? 0}%`;
  return (
    <span title="Tax hint from AI and SL-081 tax engine" className="inline-flex items-center gap-1">
      <span aria-hidden>💡</span>
      {label}
    </span>
  );
}
