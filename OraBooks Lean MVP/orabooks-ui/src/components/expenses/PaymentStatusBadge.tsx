const STYLES: Record<string, string> = {
  unpaid: 'border-slate-200 bg-slate-50 text-slate-700',
  paid: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  reimbursable: 'border-blue-200 bg-blue-50 text-blue-800',
};

const LABELS: Record<string, string> = {
  unpaid: 'Unpaid',
  paid: 'Paid',
  reimbursable: 'Reimbursable',
};

export default function PaymentStatusBadge({ status }: { status?: string | null }) {
  const key = status || 'unpaid';
  return (
    <span className={`badge border capitalize ${STYLES[key] || STYLES.unpaid}`}>
      {LABELS[key] || key.replace('_', ' ')}
    </span>
  );
}
