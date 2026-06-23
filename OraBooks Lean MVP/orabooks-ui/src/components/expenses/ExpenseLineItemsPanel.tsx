import ConfidenceBadge from '@/components/classification/ConfidenceBadge';

type LineItem = {
  id?: number;
  description?: string;
  quantity?: number;
  unit_price?: number | null;
  total_amount?: number | null;
  line_confidence?: number | null;
};

function money(value?: number | null) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}

export default function ExpenseLineItemsPanel({
  lineItems,
  threshold = 70,
}: {
  lineItems?: LineItem[];
  threshold?: number;
}) {
  if (!lineItems?.length) {
    return null;
  }

  return (
    <div className="mt-4 rounded-xl border border-border bg-slate-50/50 p-4">
      <h3 className="text-sm font-bold text-ink">Line items</h3>
      <div className="mt-3 overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border text-xs uppercase text-slate-500">
              <th className="py-2 pr-4 font-semibold">Description</th>
              <th className="py-2 pr-4 font-semibold">Qty</th>
              <th className="py-2 pr-4 text-right font-semibold">Unit</th>
              <th className="py-2 pr-4 text-right font-semibold">Total</th>
              <th className="py-2 font-semibold">Confidence</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border/70">
            {lineItems.map((line, index) => (
              <tr key={line.id ?? index}>
                <td className="py-2 pr-4 text-slate-700">{line.description || '—'}</td>
                <td className="py-2 pr-4 text-slate-600">{line.quantity ?? 1}</td>
                <td className="py-2 pr-4 text-right text-slate-600">{money(line.unit_price)}</td>
                <td className="py-2 pr-4 text-right font-medium text-ink">{money(line.total_amount)}</td>
                <td className="py-2">
                  {line.line_confidence != null ? (
                    <ConfidenceBadge value={line.line_confidence} threshold={threshold} />
                  ) : (
                    '—'
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
