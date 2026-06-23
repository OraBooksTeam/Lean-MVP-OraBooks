import { useEffect, useState, type ReactNode } from 'react';
import WpLink from '../components/WpLink';

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
  brand_name?: string | null;
  category_name?: string | null;
  hsn?: string | null;
  stock_keeping_unit?: string | null;
  barcode?: string | null;
  description?: string | null;
  item_image_url?: string | null;
  discount_type?: 'Percentage' | 'Fixed' | string;
  discount?: string | number;
  price?: string | number;
  purchase_price?: string | number;
  sales_price?: string | number;
  mrp?: string | number;
  profit_margin?: string | number;
  tax_name?: string | null;
  tax_percent?: string | number;
  tax_type?: 'Inclusive' | 'Exclusive' | string;
  warehouse_name?: string | null;
  item_type?: 'Single' | 'Variants' | 'service' | string;
  seller_points?: string | number;
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

type ProductFormState = {
  name: string;
  brand_name: string;
  category_name: string;
  unit: string;
  hsn: string;
  stock_keeping_unit: string;
  barcode: string;
  low_stock_threshold: string;
  seller_points: string;
  description: string;
  item_image_url: string;
  item_image_file: File | null;
  discount_type: 'Percentage' | 'Fixed';
  discount: string;
  price: string;
  purchase_price: string;
  tax_name: string;
  tax_percent: string;
  tax_type: 'Inclusive' | 'Exclusive';
  profit_margin: string;
  sales_price: string;
  mrp: string;
  warehouse_name: string;
  initial_stock: string;
  item_type: 'Single' | 'Variants' | 'service';
};

type LookupType = 'brand' | 'category' | 'unit' | 'tax' | 'warehouse';

type InventoryLookup = {
  id: number;
  lookup_type: LookupType;
  name: string;
  code?: string | null;
  tax_percent?: number | null;
  description?: string | null;
  warehouse_type?: string | null;
};

type LookupsMap = Record<LookupType, InventoryLookup[]>;

const selectClassName = 'w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

function emptyLookups(): LookupsMap {
  return { brand: [], category: [], unit: [], tax: [], warehouse: [] };
}

function normalizeLookupsResponse(data: unknown): LookupsMap {
  const grouped = emptyLookups();
  const payload = (data as { lookups?: unknown })?.lookups;
  if (!payload) {
    return grouped;
  }
  if (Array.isArray(payload)) {
    payload.forEach((item) => {
      const lookup = item as InventoryLookup;
      if (lookup.lookup_type && grouped[lookup.lookup_type]) {
        grouped[lookup.lookup_type].push(lookup);
      }
    });
    return grouped;
  }
  Object.keys(grouped).forEach((type) => {
    const items = (payload as Record<string, InventoryLookup[]>)[type];
    if (Array.isArray(items)) {
      grouped[type as LookupType] = items;
    }
  });
  return grouped;
}

function defaultWarehouseName(lookups: LookupsMap): string {
  return lookups.warehouse.find((item) => item.warehouse_type === 'system')?.name
    || lookups.warehouse[0]?.name
    || '';
}

function emptyProductForm(): ProductFormState {
  return {
    name: '',
    brand_name: '',
    category_name: '',
    unit: '',
    hsn: '',
    stock_keeping_unit: '',
    barcode: '',
    low_stock_threshold: '',
    seller_points: '0',
    description: '',
    item_image_url: '',
    item_image_file: null,
    discount_type: 'Percentage',
    discount: '0',
    price: '',
    purchase_price: '',
    tax_name: '',
    tax_percent: '0',
    tax_type: 'Inclusive',
    profit_margin: '0',
    sales_price: '',
    mrp: '',
    warehouse_name: '',
    initial_stock: '',
    item_type: 'Single',
  };
}

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
  const [productForm, setProductForm] = useState<ProductFormState>(emptyProductForm());
  const [lookups, setLookups] = useState<LookupsMap>(emptyLookups());
  const [lookupModal, setLookupModal] = useState<LookupType | null>(null);
  const [lookupSaving, setLookupSaving] = useState(false);
  const [lookupError, setLookupError] = useState('');

  const [adjustProduct, setAdjustProduct] = useState<Product | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [productMovements, setProductMovements] = useState<any[]>([]);
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

  const loadLookups = async (targetOrgId = orgId): Promise<LookupsMap> => {
    if (!targetOrgId) {
      return emptyLookups();
    }
    const res = await api.inventoryLookupsList(targetOrgId);
    if (res.error) {
      setError(res.error);
      return emptyLookups();
    }
    const nextLookups = normalizeLookupsResponse((res as { data?: unknown }).data);
    setLookups(nextLookups);
    return nextLookups;
  };

  const openAddProduct = async () => {
    if (!orgId) {
      return;
    }
    setError('');
    setSuccess('');
    setLookupError('');
    const nextLookups = await loadLookups(orgId);
    const form = emptyProductForm();
    form.category_name = nextLookups.category[0]?.name || '';
    form.unit = nextLookups.unit[0]?.name || '';
    form.warehouse_name = defaultWarehouseName(nextLookups);
    setProductForm(form);
    setShowProductForm(true);
  };

  const handleLookupCreated = (lookup: InventoryLookup) => {
    setLookups((prev) => ({
      ...prev,
      [lookup.lookup_type]: [...prev[lookup.lookup_type], lookup].sort((a, b) => a.name.localeCompare(b.name)),
    }));

    setProductForm((prev) => {
      const next = { ...prev };
      if (lookup.lookup_type === 'brand') {
        next.brand_name = lookup.name;
      } else if (lookup.lookup_type === 'category') {
        next.category_name = lookup.name;
      } else if (lookup.lookup_type === 'unit') {
        next.unit = lookup.name;
      } else if (lookup.lookup_type === 'tax') {
        next.tax_name = lookup.name;
        next.tax_percent = String(lookup.tax_percent ?? 0);
      } else if (lookup.lookup_type === 'warehouse') {
        next.warehouse_name = lookup.name;
      }
      return next;
    });
    setLookupModal(null);
    setLookupError('');
  };

  const handleCreateLookup = async (type: LookupType, data: Record<string, string>) => {
    if (!orgId) {
      return;
    }
    setLookupSaving(true);
    setLookupError('');
    const res = await api.inventoryLookupCreate(orgId, type, data);
    if (res.error) {
      setLookupError(res.error);
      setLookupSaving(false);
      return;
    }
    const lookup = (res as { data?: { lookup?: InventoryLookup } }).data?.lookup;
    if (lookup) {
      handleLookupCreated(lookup);
    } else {
      setLookupModal(null);
    }
    setLookupSaving(false);
  };

  const handleSearch = () => { void load(); };

  const handleCreateProduct = async () => {
    if (!orgId || !productForm.name.trim() || !productForm.category_name.trim() || !productForm.unit.trim() || !productForm.price.trim()) {
      setError('Item name, category, unit, and price are required.');
      return;
    }

    if (parseFloat(productForm.price) < 0) {
      setError('Price cannot be negative.');
      return;
    }

    setSaving(true);
    setError('');
    const payload: Record<string, string | number | undefined> = {
      name: productForm.name.trim(),
      unit: productForm.unit.trim(),
      brand_name: productForm.brand_name.trim(),
      category_name: productForm.category_name.trim(),
      hsn: productForm.hsn.trim(),
      stock_keeping_unit: productForm.stock_keeping_unit.trim(),
      barcode: productForm.barcode.trim(),
      seller_points: parseFloat(productForm.seller_points) || 0,
      description: productForm.description.trim(),
      item_image_url: productForm.item_image_url.trim(),
      discount_type: productForm.discount_type,
      discount: parseFloat(productForm.discount) || 0,
      price: parseFloat(productForm.price) || 0,
      purchase_price: parseFloat(productForm.purchase_price) || 0,
      tax_name: productForm.tax_name.trim(),
      tax_percent: parseFloat(productForm.tax_percent) || 0,
      tax_type: productForm.tax_type,
      profit_margin: parseFloat(productForm.profit_margin) || 0,
      sales_price: parseFloat(productForm.sales_price) || 0,
      mrp: parseFloat(productForm.mrp) || 0,
      warehouse_name: productForm.warehouse_name.trim(),
      item_type: productForm.item_type,
      initial_stock: parseFloat(productForm.initial_stock) || 0,
      initial_cost: parseFloat(productForm.purchase_price) || parseFloat(productForm.price) || 0,
      low_stock_threshold: productForm.low_stock_threshold
        ? parseFloat(productForm.low_stock_threshold)
        : undefined,
    };
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== undefined) {
        formData.set(key, String(value));
      }
    });
    if (productForm.item_image_file) {
      formData.set('item_image', productForm.item_image_file);
    }

    const res = await api.inventoryProductCreateUpload(orgId, formData);

    if (res.error) setError(res.error);
    else {
      setSuccess('Product created.');
      setShowProductForm(false);
      setProductForm(emptyProductForm());
      await load();
    }
    setSaving(false);
  };

  const openProductDetail = async (product: Product) => {
    if (!orgId) return;
    setSelectedProduct(product);
    setError('');
    const res = await api.inventoryProductGet(orgId, product.id);
    if (!res.error) {
      const data = (res as any).data;
      if (data?.product) setSelectedProduct({ ...product, ...data.product });
      setProductMovements(data?.movements || []);
    } else {
      const movRes = await api.inventoryMovements(orgId, product.id, { limit: 50 });
      if (!movRes.error) setProductMovements((movRes as any).data?.movements || []);
    }
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
          <Button size="sm" onClick={() => { void openAddProduct(); }}>
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

        {selectedProduct && orgId && (
          <div className="glass-panel p-5">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
              <div>
                <h2 className="font-bold text-ink">{selectedProduct.name}</h2>
                <p className="text-sm text-slate-600">
                  SKU {selectedProduct.sku} · Stock {formatQty(Number(selectedProduct.current_stock || 0))} {selectedProduct.unit || 'piece'}
                  · Avg cost {money(Number(selectedProduct.average_cost || 0))}
                </p>
              </div>
              <div className="flex gap-2">
                <Button variant="secondary" size="sm" onClick={() => openAdjust(selectedProduct)}>Adjust stock</Button>
                <Button variant="secondary" size="sm" onClick={() => { setSelectedProduct(null); setProductMovements([]); }}>
                  Close
                </Button>
              </div>
            </div>
            {productMovements.length > 0 && (
              <div className="mt-4">
                <h3 className="mb-2 text-sm font-semibold text-ink">Movement history</h3>
                <table className="min-w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-border text-xs uppercase text-slate-500">
                      <th className="py-2 pr-3 font-semibold">Date</th>
                      <th className="py-2 pr-3 font-semibold">Type</th>
                      <th className="py-2 pr-3 text-right font-semibold">Qty</th>
                      <th className="py-2 pr-3 text-right font-semibold">Stock after</th>
                      <th className="py-2 font-semibold">Reason</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {productMovements.map((m) => (
                      <tr key={m.id}>
                        <td className="py-2 pr-3 text-slate-600">{String(m.created_at || '').slice(0, 10)}</td>
                        <td className="py-2 pr-3">{m.reference_type}</td>
                        <td className="py-2 pr-3 text-right font-medium">{formatQty(Number(m.quantity_change || 0))}</td>
                        <td className="py-2 pr-3 text-right">{formatQty(Number(m.stock_after || 0))}</td>
                        <td className="py-2 text-slate-600">{m.reason || m.note || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <div className="mt-4">
              <ResourceAttachmentsPanel
                orgId={orgId}
                resourceType="inventory_item"
                resourceId={selectedProduct.id}
                title="Product files"
              />
            </div>
          </div>
        )}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Products</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">Item Code</th>
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
                    <td className="px-5 py-3">
                      <p className="font-semibold text-ink">{product.name}</p>
                      {(product.category_name || product.brand_name || product.stock_keeping_unit) && (
                        <p className="text-xs text-slate-500">
                          {[product.category_name, product.brand_name, product.stock_keeping_unit ? `SKU ${product.stock_keeping_unit}` : ''].filter(Boolean).join(' · ')}
                        </p>
                      )}
                    </td>
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
                      <div className="flex flex-wrap gap-1">
                        <Button size="sm" variant="secondary" onClick={() => void openProductDetail(product)}>
                          View
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => openAdjust(product)}>
                          Adjust
                        </Button>
                        <WpLink to={`/attachments?resource_type=inventory_item&resource_id=${product.id}`}>
                          <Button size="sm" variant="secondary">
                            <Paperclip className="h-3.5 w-3.5" />
                            Files
                          </Button>
                        </WpLink>
                      </div>
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
            <ProductFields
              form={productForm}
              onChange={setProductForm}
              lookups={lookups}
              onOpenLookupModal={setLookupModal}
            />
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowProductForm(false)}>Cancel</Button>
              <Button onClick={handleCreateProduct} loading={saving} disabled={!productForm.name.trim() || !productForm.category_name.trim() || !productForm.unit.trim() || !productForm.price.trim()}>
                Save item
              </Button>
            </div>
          </Modal>
        )}

        {lookupModal && orgId && (
          <LookupCreateModal
            type={lookupModal}
            orgId={orgId}
            saving={lookupSaving}
            error={lookupError}
            onClose={() => { setLookupModal(null); setLookupError(''); }}
            onSubmit={(data) => { void handleCreateLookup(lookupModal, data); }}
          />
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

function ProductFields({
  form,
  onChange,
  lookups,
  onOpenLookupModal,
}: {
  form: ProductFormState;
  onChange: (next: ProductFormState) => void;
  lookups: LookupsMap;
  onOpenLookupModal: (type: LookupType) => void;
}) {
  const set = (patch: Partial<ProductFormState>) => onChange({ ...form, ...patch });

  const applyPricing = (patch: Partial<ProductFormState>) => {
    const next = { ...form, ...patch };
    const price = parseFloat(next.price) || 0;
    const taxPercent = parseFloat(next.tax_percent) || 0;
    const profitMargin = parseFloat(next.profit_margin) || 0;
    const purchasePrice = next.tax_type === 'Exclusive' && taxPercent > 0
      ? price + (price * taxPercent / 100)
      : price;
    const salesPrice = profitMargin > 0 ? price + (price * profitMargin / 100) : price;

    onChange({
      ...next,
      purchase_price: price > 0 ? purchasePrice.toFixed(2) : '',
      sales_price: price > 0 ? salesPrice.toFixed(2) : '',
      mrp: price > 0 ? salesPrice.toFixed(2) : '',
    });
  };

  const selectedTax = lookups.tax.find((tax) => tax.name === form.tax_name);

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <section className="space-y-4 rounded-2xl border border-border bg-slate-50/60 p-4">
        <h4 className="text-sm font-bold uppercase tracking-wide text-ink">Item Information</h4>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Item Code">
            <div className="rounded-lg border border-border bg-slate-100 px-3 py-2.5 text-sm text-slate-600">Auto-generated from ITM-1001</div>
          </Field>
          <Field label="Item Name">
            <Input value={form.name} onChange={(e) => set({ name: e.target.value })} placeholder="Enter item name" required />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Brand">
            <SelectWithAdd
              value={form.brand_name}
              options={lookups.brand}
              placeholder="- Select -"
              addTitle="Add Brand"
              onChange={(value) => set({ brand_name: value })}
              onAdd={() => onOpenLookupModal('brand')}
            />
          </Field>
          <Field label="Category">
            <SelectWithAdd
              value={form.category_name}
              options={lookups.category}
              placeholder="- Select -"
              addTitle="Add Category"
              onChange={(value) => set({ category_name: value })}
              onAdd={() => onOpenLookupModal('category')}
            />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Unit">
            <SelectWithAdd
              value={form.unit}
              options={lookups.unit}
              placeholder="- Select -"
              addTitle="Add Unit"
              onChange={(value) => set({ unit: value })}
              onAdd={() => onOpenLookupModal('unit')}
            />
          </Field>
          <Field label="HSN">
            <Input value={form.hsn} onChange={(e) => set({ hsn: e.target.value })} placeholder="HSN Code" />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="SKU">
            <Input value={form.stock_keeping_unit} onChange={(e) => set({ stock_keeping_unit: e.target.value.toUpperCase() })} placeholder="Stock Keeping Unit" />
          </Field>
          <Field label="Barcode">
            <Input value={form.barcode} onChange={(e) => set({ barcode: e.target.value })} placeholder="Barcode / UPC" />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Alert Quantity">
            <Input type="number" min="0" step="0.01" value={form.low_stock_threshold} onChange={(e) => set({ low_stock_threshold: e.target.value })} placeholder="0" />
          </Field>
          <Field label="Seller Points">
            <Input type="number" min="0" step="0.01" value={form.seller_points} onChange={(e) => set({ seller_points: e.target.value })} placeholder="0" />
          </Field>
        </div>

        <Field label="Description">
          <textarea
            value={form.description}
            onChange={(e) => set({ description: e.target.value })}
            rows={3}
            placeholder="Optional item details..."
            className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </Field>

        <Field label="Item Image">
          <input
            type="file"
            accept="image/*"
            onChange={(e) => set({ item_image_file: e.target.files?.[0] || null })}
            className="w-full rounded-lg border border-dashed border-border bg-white px-3 py-4 text-sm text-slate-600"
          />
        </Field>
        <Field label="Image URL (optional)">
          <Input type="url" value={form.item_image_url} onChange={(e) => set({ item_image_url: e.target.value })} placeholder="https://..." />
        </Field>
        {(form.item_image_file || form.item_image_url) && (
          <div className="h-24 w-24 overflow-hidden rounded-xl border border-border bg-white">
            <img
              src={form.item_image_file ? URL.createObjectURL(form.item_image_file) : form.item_image_url}
              alt="Item preview"
              className="h-full w-full object-cover"
            />
          </div>
        )}
      </section>

      <section className="space-y-4 rounded-2xl border border-border bg-slate-50/60 p-4">
        <h4 className="text-sm font-bold uppercase tracking-wide text-ink">Pricing & Stock</h4>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Discount Type">
            <select value={form.discount_type} onChange={(e) => set({ discount_type: e.target.value === 'Fixed' ? 'Fixed' : 'Percentage' })} className={selectClassName}>
              <option value="Percentage">Percentage (%)</option>
              <option value="Fixed">Fixed Amount</option>
            </select>
          </Field>
          <Field label="Discount">
            <Input type="number" min="0" step="0.01" value={form.discount} onChange={(e) => set({ discount: e.target.value })} placeholder="0.00" />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Price">
            <Input type="number" min="0" step="0.01" value={form.price} onChange={(e) => applyPricing({ price: e.target.value })} placeholder="0.00" required />
          </Field>
          <Field label="Purchase Price">
            <Input type="number" min="0" step="0.01" value={form.purchase_price} onChange={(e) => set({ purchase_price: e.target.value })} placeholder="0.00" />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Tax">
            <SelectWithAdd
              value={form.tax_name}
              options={lookups.tax}
              placeholder="- Select -"
              addTitle="Add Tax"
              getOptionLabel={(tax) => `${tax.name} (${tax.tax_percent ?? 0}%)`}
              onChange={(value) => {
                const tax = lookups.tax.find((option) => option.name === value);
                applyPricing({ tax_name: tax?.name || '', tax_percent: String(tax?.tax_percent ?? 0) });
              }}
              onAdd={() => onOpenLookupModal('tax')}
            />
            {selectedTax && <span className="mt-1 block text-xs text-slate-500">{selectedTax.tax_percent ?? 0}% tax selected</span>}
          </Field>
          <Field label="Tax Type">
            <select value={form.tax_type} onChange={(e) => applyPricing({ tax_type: e.target.value === 'Exclusive' ? 'Exclusive' : 'Inclusive' })} className={selectClassName}>
              <option value="Inclusive">Inclusive</option>
              <option value="Exclusive">Exclusive</option>
            </select>
          </Field>
        </div>

        <Field label="Profit Margin (%)">
          <Input type="number" min="0" step="0.01" value={form.profit_margin} onChange={(e) => applyPricing({ profit_margin: e.target.value })} placeholder="0.00" />
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Sales Price">
            <Input type="number" min="0" step="0.01" value={form.sales_price} onChange={(e) => set({ sales_price: e.target.value })} placeholder="0.00" />
          </Field>
          <Field label="MRP">
            <Input type="number" min="0" step="0.01" value={form.mrp} onChange={(e) => set({ mrp: e.target.value })} placeholder="0.00" />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Warehouse">
            <SelectWithAdd
              value={form.warehouse_name}
              options={lookups.warehouse}
              placeholder="- Select -"
              addTitle="Add Warehouse"
              onChange={(value) => set({ warehouse_name: value })}
              onAdd={() => onOpenLookupModal('warehouse')}
            />
          </Field>
          <Field label="Opening Stock">
            <Input type="number" min="0" step="0.01" value={form.initial_stock} onChange={(e) => set({ initial_stock: e.target.value })} placeholder="0.00" />
          </Field>
        </div>

        <Field label="Item Type">
          <select value={form.item_type} onChange={(e) => set({ item_type: e.target.value as ProductFormState['item_type'] })} className={selectClassName}>
            <option value="Single">Single Item</option>
            <option value="Variants">Product Variants</option>
            <option value="service">Service</option>
          </select>
        </Field>
      </section>
    </div>
  );
}

function SelectWithAdd({
  value,
  options,
  placeholder,
  onChange,
  onAdd,
  addTitle,
  getOptionLabel,
}: {
  value: string;
  options: InventoryLookup[];
  placeholder: string;
  onChange: (value: string) => void;
  onAdd: () => void;
  addTitle: string;
  getOptionLabel?: (item: InventoryLookup) => string;
}) {
  const labelFor = getOptionLabel || ((item: InventoryLookup) => item.name);

  return (
    <div className="flex gap-2">
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={selectClassName}
      >
        <option value="">{placeholder}</option>
        {options.map((option) => (
          <option key={option.id} value={option.name}>{labelFor(option)}</option>
        ))}
      </select>
      <button
        type="button"
        onClick={onAdd}
        title={addTitle}
        className="inline-flex shrink-0 items-center justify-center rounded-lg border border-border bg-slate-50 px-3 py-2.5 text-slate-600 transition hover:bg-slate-100"
      >
        <Plus className="h-4 w-4" />
      </button>
    </div>
  );
}

const LOOKUP_MODAL_TITLES: Record<LookupType, string> = {
  brand: 'Add Brand',
  category: 'Add Category',
  unit: 'Add Unit',
  tax: 'Add Tax',
  warehouse: 'Add Warehouse',
};

function LookupCreateModal({
  type,
  orgId,
  saving,
  error,
  onClose,
  onSubmit,
}: {
  type: LookupType;
  orgId: number;
  saving: boolean;
  error: string;
  onClose: () => void;
  onSubmit: (data: Record<string, string>) => void;
}) {
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [description, setDescription] = useState('');
  const [taxPercent, setTaxPercent] = useState('');

  useEffect(() => {
    setName('');
    setDescription('');
    setTaxPercent('');
    setCode('');

    if (type === 'brand' || type === 'category') {
      void api.inventoryLookupCode(orgId, type).then((res) => {
        if (!res.error) {
          setCode(String((res as { data?: { code?: string } }).data?.code || ''));
        }
      });
    }
  }, [type, orgId]);

  const handleSubmit = () => {
    if (!name.trim()) {
      return;
    }
    const payload: Record<string, string> = {
      name: name.trim(),
      description: description.trim(),
    };
    if (type === 'brand' || type === 'category') {
      payload.code = code.trim();
    }
    if (type === 'tax') {
      payload.tax_percent = taxPercent.trim() || '0';
    }
    onSubmit(payload);
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 p-4" onClick={onClose}>
      <div className="w-full max-w-md rounded-2xl border border-border bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-semibold text-ink">{LOOKUP_MODAL_TITLES[type]}</h3>
          <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">Close</button>
        </div>

        <div className="mt-4 space-y-4">
          {error && <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}

          {(type === 'brand' || type === 'category') && (
            <Field label={type === 'brand' ? 'Brand Code' : 'Category Code'}>
              <Input value={code} readOnly placeholder="Generating..." />
            </Field>
          )}

          <Field label={`${LOOKUP_MODAL_TITLES[type].replace('Add ', '')} Name`}>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={`Enter ${type} name`}
              required
            />
          </Field>

          {type === 'tax' && (
            <Field label="Tax Percentage">
              <Input
                type="number"
                min="0"
                step="0.01"
                value={taxPercent}
                onChange={(e) => setTaxPercent(e.target.value)}
                placeholder="0.00"
              />
            </Field>
          )}

          {type !== 'tax' && (
            <Field label="Description">
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={3}
                placeholder="Optional description"
                className="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
              />
            </Field>
          )}
        </div>

        <div className="mt-6 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={handleSubmit} loading={saving} disabled={!name.trim() || (type === 'tax' && taxPercent.trim() === '')}>
            Save
          </Button>
        </div>
      </div>
    </div>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
      <div className="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-border bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
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
