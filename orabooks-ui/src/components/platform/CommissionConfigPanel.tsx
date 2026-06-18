import { useEffect, useState, type FormEvent } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '@/pages/frontend/api';
import { RefreshCw, Settings2 } from 'lucide-react';

export default function CommissionConfigPanel() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [exportMessage, setExportMessage] = useState('');
  const [form, setForm] = useState({
    base_monthly_amount: '',
    max_years: '',
    yearly_percentages: '[20,15,10,5,2.5,1]',
    min_payout_threshold: '',
    customer_active_window_days: '',
    expiry_accounting_action: 'reverse_expense',
    payout_fee_type: 'percentage',
    payout_fee_rate: '',
  });

  const load = () => {
    setLoading(true);
    setError('');
    api.commissionConfigGet().then((res: any) => {
      if (res.error) setError(res.error);
      else if (res.data) {
        const c = res.data;
        setForm({
          base_monthly_amount: String(c.base_monthly_amount ?? ''),
          max_years: String(c.max_years ?? ''),
          yearly_percentages: JSON.stringify(c.yearly_percentages ?? []),
          min_payout_threshold: String(c.min_payout_threshold ?? ''),
          customer_active_window_days: String(c.customer_active_window_days ?? ''),
          expiry_accounting_action: c.expiry_accounting_action || 'reverse_expense',
          payout_fee_type: c.payout_fee_type || 'percentage',
          payout_fee_rate: String(c.payout_fee_rate ?? ''),
        });
      }
      setLoading(false);
    });
  };

  useEffect(() => {
    load();
  }, []);

  const update = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setMessage('');
    try {
      const res = await api.commissionConfigUpdate(form);
      if (res.error) setError(typeof res.error === 'string' ? res.error : 'Update failed');
      else setMessage('Configuration updated successfully.');
    } finally {
      setSaving(false);
    }
  };

  const requestExport = async (format: 'csv' | 'pdf') => {
    setExportMessage('');
    const res = await api.exportRequest('commission_config', format);
    if (res.error) setExportMessage(res.error);
    else setExportMessage('Export queued — you will get a notification when ready.');
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-2">
        <Button onClick={load} variant="secondary" size="sm">
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
        <span className="text-sm font-semibold text-ink">Export config</span>
        <Button type="button" variant="secondary" size="sm" onClick={() => requestExport('csv')}>
          Export CSV
        </Button>
        <Button type="button" size="sm" onClick={() => requestExport('pdf')}>
          Export PDF
        </Button>
        {exportMessage && <span className="text-sm text-slate-600">{exportMessage}</span>}
      </div>

      {loading ? (
        <div className="h-64 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <form onSubmit={submit} className="glass-panel space-y-4 p-6">
          <div className="grid gap-4 md:grid-cols-2">
            <Input
              label="Base Monthly Amount"
              type="number"
              step="0.01"
              min="0"
              value={form.base_monthly_amount}
              onChange={(e) => update('base_monthly_amount', e.target.value)}
            />
            <Input
              label="Max Years"
              type="number"
              min="1"
              max="10"
              value={form.max_years}
              onChange={(e) => update('max_years', e.target.value)}
            />
            <Input
              label="Yearly Percentages (JSON array)"
              value={form.yearly_percentages}
              onChange={(e) => update('yearly_percentages', e.target.value)}
              placeholder="[20,15,10,5,2.5,1]"
            />
            <Input
              label="Minimum Payout Threshold"
              type="number"
              step="0.01"
              min="0"
              value={form.min_payout_threshold}
              onChange={(e) => update('min_payout_threshold', e.target.value)}
            />
            <Input
              label="Customer Active Window (Days)"
              type="number"
              min="1"
              max="365"
              value={form.customer_active_window_days}
              onChange={(e) => update('customer_active_window_days', e.target.value)}
            />
            <div>
              <label className="mb-1 block text-sm font-medium text-ink">Expiry Accounting Action</label>
              <select
                className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                value={form.expiry_accounting_action}
                onChange={(e) => update('expiry_accounting_action', e.target.value)}
              >
                <option value="reverse_expense">Reverse Expense</option>
                <option value="income">Expired Commission Income</option>
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-ink">Payout Fee Type</label>
              <select
                className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                value={form.payout_fee_type}
                onChange={(e) => update('payout_fee_type', e.target.value)}
              >
                <option value="percentage">Percentage</option>
                <option value="flat">Flat</option>
              </select>
            </div>
            <Input
              label="Payout Fee Rate"
              type="number"
              step="0.0001"
              min="0"
              value={form.payout_fee_rate}
              onChange={(e) => update('payout_fee_rate', e.target.value)}
            />
          </div>
          <p className="text-xs text-slate-500">
            If fee type is percentage, e.g. 2.5 = 2.5%. If flat, enter the dollar amount.
          </p>
          {error && <p className="text-sm text-danger">{error}</p>}
          {message && <p className="text-sm text-emerald-700">{message}</p>}
          <Button type="submit" loading={saving}>
            <Settings2 className="h-4 w-4" />
            Update Configuration
          </Button>
        </form>
      )}
    </div>
  );
}
