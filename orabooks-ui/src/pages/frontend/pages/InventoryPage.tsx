import { useEffect, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import ResourceAttachmentsPanel from '../components/ResourceAttachmentsPanel';
import { Info, Package, Paperclip, Plus, RefreshCw, TrendingDown } from 'lucide-react';

type Product = {
  id: number;
  sku: string;
  name: string;
  unit?: string;
  current_stock?: string | number;
  average_cost?: string | number;
  low_stock_threshold?: string | number | null;
  is_active?: number;
};

const ADJUSTMENT_REASONS = [
  'PHYSICAL_COUNT',
  'DAMAGED_GOODS',
  'THEFT_OR_LOSS',
  'DATA_CORRECTION',
  'OTHER',
];

export default function InventoryPage() {
  const [context, setContext] = useState<any>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [movements, setMovements] = useState<any[]>([]);
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState('');

  const [showProductForm, setShowProductForm] = useState(false);
  const [productForm, setProductForm] = useState({
    sku: '',
    name: '',
    unit: 'piece',
    initial_stock: '',
    initial_cost: '',
    low_stock_threshold: '',
  });

  const [adjustProduct, setAdjustProduct] = useState<Product | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [adjustForm, setAdjustForm] = useState({
    quantity_change: '',
    reason: 'PHYSICAL_COUNT',
    note: '',
  });

  const orgId = context?.organization?.id;
  const lowStockCount = stats?.low_stock_count ?? 0;

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');

    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Unable to load organization context.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data;
    setContext(nextContext);
    const nextOrgId = nextContext?.organization?.id;
    if (!nextOrgId) {
      setError('Organization is not set up yet.');
      setLoading(false);
      return;
    }

    const [dashRes, productsRes, movementsRes] = await Promise.all([
      api.inventoryDashboard(),
      api.inventoryProductsList(nextOrgId, { limit: 100, search: search.trim() || undefined }),
      api.inventoryMovements(nextOrgId, 0, { limit: 50 }),
    ]);

    if (dashRes.error) setError(dashRes.error);
    else setStats((dashRes as any).data?.stats || null);

    if (!productsRes.error) setProducts((productsRes as any).data?.products || []);
    if (!movementsRes.error) setMovements((movementsRes as any).data?.movements || []);

    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  const handleSearch = () => { void load(); };

  const handleCreateProduct = async () => {
    if (!orgId || !productForm.sku.trim() || !productForm.name.trim()) {
      setError('SKU and name are required.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.inventoryProductCreate(orgId, {
      sku: productForm.sku.trim(),
      name: productForm.name.trim(),
      unit: productForm.unit,
      initial_stock: parseFloat(productForm.initial_stock) || 0,
      initial_cost: parseFloat(productForm.initial_cost) || 0,
      low_stock_threshold: productForm.low_stock_threshold
        ? parseFloat(productForm.low_stock_threshold)
        : undefined,
    });

    if (res.error) setError(res.error);
    else {
      setSuccess('Product created.');
      setShowProductForm(false);
      setProductForm({ sku: '', name: '', unit: 'piece', initial_stock: '', initial_cost: '', low_stock_threshold: '' });
      await load();
    }
    setSaving(false);
  };

  const openAdjust = (product: Product) => {
    setAdjustProduct(product);
    setAdjustForm({ quantity_change: '', reason: 'PHYSICAL_COUNT', note: '' });
    setError('');
  };

  const handleAdjustStock = async () => {
    if (!orgId || !adjustProduct) return;
    const change = parseFloat(adjustForm.quantity_change);
    if (!change || change === 0) {
      setError('Enter a non-zero quantity change (+ or -).');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.inventoryAdjustStock(
      orgId,
      adjustProduct.id,
      change,
      adjustForm.reason,
      adjustForm.note
    );

    if (res.error) setError(res.error);
    else {
      setSuccess('Stock adjusted.');
      setAdjustProduct(null);
      await load();
    }
    setSaving(false);
  };

  return (
    <ClientShell
      title="Inventory"
      eyebrow="SL-034 Products & stock"
      organization={context?.organization}
      isPartner={context?.organization?.organization_type === 'partner'}
    >
      <div className="space-y-5">
        <div className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Manage SKUs with weighted-average costing. Stock adjustments require a reason and are logged as movements. Negative stock is blocked.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Products" value={stats?.total_products ?? 0} />
          <Metric label="Active SKUs" value={stats?.active_products ?? 0} />
          <Metric label="Stock Value" value={money(stats?.total_stock_value)} />
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

        <div className="flex flex-wrap items-center gap-3">
          <div className="min-w-[200px] flex-1">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by SKU or name…"
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            />
          </div>
          <Button onClick={handleSearch} variant="secondary" size="sm">Search</Button>
          <Button size="sm" onClick={() => { setShowProductForm(true); setError(''); setSuccess(''); }}>
            <Plus className="h-4 w-4" />
            Add product
          </Button>
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && !showProductForm && !adjustProduct && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">{success}</div>
        )}

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
                <th className="px-5 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr><td colSpan={8} className="px-5 py-8 text-center text-slate-500">Loading products…</td></tr>
              ) : products.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-5 py-10 text-center">
                    <Package className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm text-slate-500">No inventory products found.</p>
                  </td>
                </tr>
              ) : products.map((product) => {
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
                    <td className="px-5 py-3">
                      <Button size="sm" variant="secondary" onClick={() => openAdjust(product)}>
                        Adjust
                      </Button>
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
                <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-500">Loading movements…</td></tr>
              ) : movements.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center text-sm text-slate-500">No stock movements recorded yet.</td>
                </tr>
              ) : movements.map((movement) => (
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

        {showProductForm && (
          <Modal title="Add product" onClose={() => setShowProductForm(false)}>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="SKU"><Input value={productForm.sku} onChange={(e) => setProductForm((p) => ({ ...p, sku: e.target.value.toUpperCase() }))} /></Field>
              <Field label="Name"><Input value={productForm.name} onChange={(e) => setProductForm((p) => ({ ...p, name: e.target.value }))} /></Field>
              <Field label="Unit"><Input value={productForm.unit} onChange={(e) => setProductForm((p) => ({ ...p, unit: e.target.value }))} /></Field>
              <Field label="Low stock threshold"><Input type="number" min="0" step="0.01" value={productForm.low_stock_threshold} onChange={(e) => setProductForm((p) => ({ ...p, low_stock_threshold: e.target.value }))} /></Field>
              <Field label="Opening stock"><Input type="number" min="0" step="0.01" value={productForm.initial_stock} onChange={(e) => setProductForm((p) => ({ ...p, initial_stock: e.target.value }))} /></Field>
              <Field label="Unit cost"><Input type="number" min="0" step="0.01" value={productForm.initial_cost} onChange={(e) => setProductForm((p) => ({ ...p, initial_cost: e.target.value }))} /></Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowProductForm(false)}>Cancel</Button>
              <Button onClick={handleCreateProduct} disabled={saving}>Create product</Button>
            </div>
          </Modal>
        )}

        {adjustProduct && (
          <Modal title={`Adjust stock — ${adjustProduct.sku}`} onClose={() => setAdjustProduct(null)}>
            <p className="mb-4 text-sm text-slate-600">
              Current stock: {formatQty(Number(adjustProduct.current_stock || 0))}
            </p>
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Quantity change (+ in / − out)">
                <Input type="number" step="0.01" value={adjustForm.quantity_change} onChange={(e) => setAdjustForm((p) => ({ ...p, quantity_change: e.target.value }))} />
              </Field>
              <Field label="Reason">
                <select value={adjustForm.reason} onChange={(e) => setAdjustForm((p) => ({ ...p, reason: e.target.value }))} className="w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                  {ADJUSTMENT_REASONS.map((r) => (
                    <option key={r} value={r}>{formatReason(r)}</option>
                  ))}
                </select>
              </Field>
              <Field label="Note (optional)">
                <Input value={adjustForm.note} onChange={(e) => setAdjustForm((p) => ({ ...p, note: e.target.value }))} />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setAdjustProduct(null)}>Cancel</Button>
              <Button onClick={handleAdjustStock} disabled={saving}>Apply adjustment</Button>
            </div>
          </Modal>
        )}
      </div>
    </ClientShell>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-semibold text-ink">{title}</h3>
          <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">Close</button>
        </div>
        <div className="mt-4">{children}</div>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5 text-sm">
      <span className="font-medium text-slate-700">{label}</span>
      {children}
    </label>
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

function formatReason(code: string) {
  return code.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
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
