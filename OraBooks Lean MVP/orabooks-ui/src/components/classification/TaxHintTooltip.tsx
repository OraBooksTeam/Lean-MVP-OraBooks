import { Info } from 'lucide-react';

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
 <span title="Suggested rate from tax engine" className="inline-flex items-center gap-1 text-slate-700">
 <Info className="h-3.5 w-3.5 text-slate-400" aria-hidden />
 {label}
 </span>
 );
}
