import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

import Button from '@/components/Button';
import Input from '@/components/Input';
import { api } from '../api';

export type CreatedProduct = {
  id: number;
  sku?: string;
  name?: string;
  stock_keeping_unit?: string | null;
  sales_price?: string | number;
  price?: string | number;
  mrp?: string | number;
  tax_name?: string | null;
  tax_percent?: string | number;
};

type LookupOption = { name: string };

type QuickProductForm = {
  name: string;
  category_name: string;
  unit: string;
  price: string;
  stock_keeping_unit: string;
};

const selectClassName = 'w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

const emptyForm = (): QuickProductForm => ({
  name: '',
  category_name: '',
  unit: 'piece',
  price: '',
  stock_keeping_unit: '',
});

type CreateProductModalProps = {
  open: boolean;
  orgId: number;
  onClose: () => void;
  onCreated: (product: CreatedProduct) => void;
};

export default function CreateProductModal({
  open,
  orgId,
  onClose,
  onCreated,
}: CreateProductModalProps) {
  const [form, setForm] = useState<QuickProductForm>(emptyForm);
  const [categories, setCategories] = useState<LookupOption[]>([]);
  const [units, setUnits] = useState<LookupOption[]>([]);
  const [lookupsLoading, setLookupsLoading] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!open) return;
    setForm(emptyForm());
    setError('');
    setSaving(false);
    setLookupsLoading(true);

    void api.inventoryLookupsList(orgId).then((res) => {
      if (res.error) {
        setCategories([]);
        setUnits([]);
        setLookupsLoading(false);
        return;
      }

      const payload = (res as { data?: { lookups?: Record<string, LookupOption[]> | LookupOption[] } }).data?.lookups;
      if (Array.isArray(payload)) {
        setCategories(payload.filter((item: any) => item.lookup_type === 'category').map((item: any) => ({ name: item.name })));
        setUnits(payload.filter((item: any) => item.lookup_type === 'unit').map((item: any) => ({ name: item.name })));
      } else if (payload && typeof payload === 'object') {
        setCategories((payload.category || []).map((item) => ({ name: item.name })));
        setUnits((payload.unit || []).map((item) => ({ name: item.name })));
      }
      setLookupsLoading(false);
    });
  }, [open, orgId]);

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, onClose]);

  const handleCreate = async () => {
    if (!form.name.trim() || !form.category_name.trim() || !form.unit.trim() || !form.price.trim()) {
      setError('Item name, category, unit, and price are required.');
      return;
    }

    if (parseFloat(form.price) < 0) {
      setError('Price cannot be negative.');
      return;
    }

    setSaving(true);
    setError('');
    const price = parseFloat(form.price) || 0;
    const res = await api.inventoryProductCreate(orgId, {
      name: form.name.trim(),
      category_name: form.category_name.trim(),
      unit: form.unit.trim(),
      price,
      sales_price: price,
      mrp: price,
      purchase_price: price,
      stock_keeping_unit: form.stock_keeping_unit.trim(),
      item_type: 'Single',
      initial_stock: 0,
    });

    if (res.error) {
      setError(typeof res.error === 'string' ? res.error : 'Unable to create product.');
      setSaving(false);
      return;
    }

    const product = (res as { data?: { product?: CreatedProduct } }).data?.product;
    if (!product?.id) {
      setError('Product was created but could not be applied to the line.');
      setSaving(false);
      return;
    }

    onCreated(product);
    setSaving(false);
  };

  if (!open || typeof document === 'undefined') {
    return null;
  }

  return createPortal(
    <div
      className="orabooks-modal-overlay"
      style={{
        display: 'block',
        position: 'fixed',
        inset: 0,
        zIndex: 100020,
        overflowY: 'auto',
        padding: '1rem',
        background: 'rgb(15 23 42 / 0.4)',
      }}
      onClick={onClose}
    >
      <div className="flex min-h-full items-start justify-center py-2">
        <div
          role="dialog"
          aria-modal="true"
          className="orabooks-modal-panel max-w-lg rounded-2xl border border-border bg-white shadow-xl"
          style={{
            display: 'flex',
            flexDirection: 'column',
            width: '100%',
            maxHeight: '90vh',
            marginInline: 'auto',
            overflow: 'hidden',
          }}
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex shrink-0 items-center justify-between gap-3 border-b border-border bg-white px-6 py-4">
            <h3 className="text-lg font-semibold text-ink">Add product</h3>
            <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">Close</button>
          </div>
          <div
            className="orabooks-modal-body px-6 py-4"
            style={{
              flex: '1 1 auto',
              minHeight: 0,
              overflowY: 'auto',
              WebkitOverflowScrolling: 'touch',
            }}
          >
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
            <div className="grid gap-4">
              <Field label="Item name">
                <Input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} placeholder="Enter item name" />
              </Field>
              <Field label="Category">
                {categories.length > 0 ? (
                  <select
                    value={form.category_name}
                    onChange={(e) => setForm((p) => ({ ...p, category_name: e.target.value }))}
                    className={selectClassName}
                    disabled={lookupsLoading}
                  >
                    <option value="">Select category…</option>
                    {categories.map((category) => (
                      <option key={category.name} value={category.name}>{category.name}</option>
                    ))}
                  </select>
                ) : (
                  <Input value={form.category_name} onChange={(e) => setForm((p) => ({ ...p, category_name: e.target.value }))} placeholder="e.g. General" />
                )}
              </Field>
              <Field label="Unit">
                {units.length > 0 ? (
                  <select
                    value={form.unit}
                    onChange={(e) => setForm((p) => ({ ...p, unit: e.target.value }))}
                    className={selectClassName}
                    disabled={lookupsLoading}
                  >
                    <option value="">Select unit…</option>
                    {units.map((unit) => (
                      <option key={unit.name} value={unit.name}>{unit.name}</option>
                    ))}
                  </select>
                ) : (
                  <Input value={form.unit} onChange={(e) => setForm((p) => ({ ...p, unit: e.target.value }))} placeholder="e.g. piece" />
                )}
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Price">
                  <Input type="number" min="0" step="0.01" value={form.price} onChange={(e) => setForm((p) => ({ ...p, price: e.target.value }))} placeholder="0.00" />
                </Field>
                <Field label="SKU (optional)">
                  <Input value={form.stock_keeping_unit} onChange={(e) => setForm((p) => ({ ...p, stock_keeping_unit: e.target.value.toUpperCase() }))} placeholder="Stock keeping unit" />
                </Field>
              </div>
              <p className="text-xs text-slate-500">Item code is auto-generated. The new product will be applied to this invoice line.</p>
            </div>
          </div>
          <div className="shrink-0 border-t border-border bg-white px-6 py-4">
            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={onClose}>Cancel</Button>
              <Button
                onClick={() => { void handleCreate(); }}
                loading={saving}
                disabled={!form.name.trim() || !form.category_name.trim() || !form.unit.trim() || !form.price.trim()}
              >
                Save item
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block space-y-1.5 text-sm">
      <span className="font-medium text-slate-700">{label}</span>
      {children}
    </label>
  );
}
