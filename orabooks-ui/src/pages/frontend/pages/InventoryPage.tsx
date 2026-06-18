import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import { Package, RefreshCw, TrendingDown } from 'lucide-react';

export default function InventoryPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    const res = await api.inventoryDashboard();
    if (res.error) setError(res.error || 'Unable to load inventory.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const products = data?.recent_products?.products || [];
  const movements = data?.recent_movements || [];
  const lowStockCount = data?.stats?.low_stock_count ?? 0;

  return (
    <ClientShell title="Inventory" eyebrow="Products & stock" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Products" value={data?.stats?.total_products ?? 0} />
          <Metric label="Active SKUs" value={data?.stats?.active_products ?? 0} />
          <Metric label="Stock Value" value={money(data?.stats?.total_stock_value)} />
          <Metric label="Low Stock Alerts" value={lowStockCount} tone={lowStockCount > 0 ? 'warning' : 'default'} />
        </div>

        {lowStockCount > 0 && (
          <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <TrendingDown className="mt-0.5 h-5 w-5 shrink-0" />
            <p>
              <span className="font-semibold">{lowStockCount}</span> product{lowStockCount === 1 ? '' : 's'} at or below the low-stock threshold.
            </p>
          </div>
        )}

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Products</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">SKU</th>
                <th className="px-5 py-3 font-semibold">Name</th>
                <th className="px-5 py-3 font-semibold">Unit</th>
                <th className="px-5 py-3 text-right font-semibold">Stock</th>
                <th className="px-5 py-3 text-right font-semibold">Avg Cost</th>
                <th className="px-5 py-3 text-right font-semibold">Value</th>
                <th className="px-5 py-3 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={7} className="px-5 py-8 text-center text-slate-500">Loading products...</td></tr>
              ) : products.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center">
                    <Package className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No inventory products found.</p>
                  </td>
                </tr>
              ) : products.map((product: any) => {
                const stock = Number(product.current_stock || 0);
                const avgCost = Number(product.average_cost || 0);
                const threshold = product.low_stock_threshold != null ? Number(product.low_stock_threshold) : null;
                const isLow = threshold != null && stock <= threshold;

                return (
                  <tr key={product.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3 font-mono text-xs font-semibold text-ink">{product.sku}</td>
                    <td className="px-5 py-3 font-semibold text-ink">{product.name}</td>
                    <td className="px-5 py-3 text-slate-600">{product.unit || 'piece'}</td>
                    <td className="px-5 py-3 text-right font-bold text-ink">{formatQty(stock)}</td>
                    <td className="px-5 py-3 text-right text-slate-600">{money(avgCost)}</td>
                    <td className="px-5 py-3 text-right font-bold text-ink">{money(stock * avgCost)}</td>
                    <td className="px-5 py-3">
                      {isLow ? (
                        <span className="badge border border-amber-200 bg-amber-50 text-amber-800">low stock</span>
                      ) : (
                        <StatusBadge active={Number(product.is_active) === 1} />
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Movements</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Date</th>
                <th className="px-5 py-3 font-semibold">Product</th>
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 text-right font-semibold">Qty Change</th>
                <th className="px-5 py-3 text-right font-semibold">Stock After</th>
                <th className="px-5 py-3 text-right font-semibold">Value</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading movements...</td></tr>
              ) : movements.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center text-sm text-slate-500">No stock movements recorded yet.</td>
                </tr>
              ) : movements.map((movement: any) => (
                <tr key={movement.id} className="hover:bg-slate-50/70">
                  <td className="px-5 py-3 text-slate-600">{formatDate(movement.created_at)}</td>
                  <td className="px-5 py-3">
                    <p className="font-semibold text-ink">{movement.product_name || movement.sku}</p>
                    {movement.sku && movement.product_name && (
                      <p className="text-xs text-slate-500">{movement.sku}</p>
                    )}
                  </td>
                  <td className="px-5 py-3"><MovementBadge type={movement.reference_type} /></td>
                  <td className={`px-5 py-3 text-right font-bold ${Number(movement.quantity_change) >= 0 ? 'text-success' : 'text-red-600'}`}>
                    {formatQty(Number(movement.quantity_change), true)}
                  </td>
                  <td className="px-5 py-3 text-right text-slate-600">{formatQty(Number(movement.stock_after))}</td>
                  <td className="px-5 py-3 text-right text-slate-600">{money(movement.movement_value)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
  );
}

function Metric({ label, value, tone = 'default' }: { label: string; value: string | number; tone?: 'default' | 'warning' }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className={`mt-2 text-3xl font-black ${tone === 'warning' ? 'text-amber-700' : 'text-ink'}`}>{value}</p>
    </div>
  );
}

function StatusBadge({ active }: { active: boolean }) {
  return (
    <span className={`badge border ${active ? 'border-success/20 bg-success/10 text-success' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>
      {active ? 'active' : 'inactive'}
    </span>
  );
}

function MovementBadge({ type }: { type: string }) {
  const colors: Record<string, string> = {
    opening: 'border-slate-200 bg-slate-50 text-slate-700',
    purchase: 'border-success/20 bg-success/10 text-success',
    sale: 'border-primary/20 bg-primary/10 text-primary-dark',
    adjustment: 'border-amber-200 bg-amber-50 text-amber-800',
  };

  return (
    <span className={`badge border ${colors[type] || 'border-border bg-slate-50 text-slate-700'}`}>
      {type || 'unknown'}
    </span>
  );
}

function formatQty(value: number, signed = false) {
  if (signed && value > 0) return `+${value.toLocaleString(undefined, { maximumFractionDigits: 4 })}`;
  return value.toLocaleString(undefined, { maximumFractionDigits: 4 });
}

function formatDate(value?: string) {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function money(value?: string | number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
