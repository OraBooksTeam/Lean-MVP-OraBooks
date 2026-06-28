import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

import Button from '@/components/Button';
import { api } from '../api';
import CustomerFormFields, { customerFormPayload, emptyCustomerForm } from './CustomerFormFields';

export type CreatedCustomer = {
  id: number;
  display_name?: string | null;
  email?: string;
};

type CreateCustomerModalProps = {
  open: boolean;
  orgId: number;
  defaultCurrency?: string;
  onClose: () => void;
  onCreated: (customer: CreatedCustomer) => void;
};

export default function CreateCustomerModal({
  open,
  orgId,
  defaultCurrency = 'USD',
  onClose,
  onCreated,
}: CreateCustomerModalProps) {
  const [form, setForm] = useState(() => emptyCustomerForm(defaultCurrency));
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!open) return;
    setForm(emptyCustomerForm(defaultCurrency));
    setError('');
    setSaving(false);
  }, [open, defaultCurrency]);

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, onClose]);

  const handleCreate = async () => {
    if (!form.name.trim()) {
      setError('Customer name is required.');
      return;
    }

    setSaving(true);
    setError('');
    const res = await api.customerCreate(orgId, customerFormPayload(form));
    if (res.error) {
      setError(typeof res.error === 'string' ? res.error : 'Unable to create customer.');
      setSaving(false);
      return;
    }

    const customer = (res as { data?: { customer?: CreatedCustomer } }).data?.customer;
    if (!customer?.id) {
      setError('Customer was created but could not be selected.');
      setSaving(false);
      return;
    }

    onCreated(customer);
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
          className="orabooks-modal-panel max-w-5xl rounded-2xl border border-border bg-white shadow-xl"
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
            <h3 className="text-lg font-semibold text-ink">Add customer</h3>
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
            <CustomerFormFields form={form} onChange={setForm} />
          </div>
          <div className="shrink-0 border-t border-border bg-white px-6 py-4">
            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={onClose}>Cancel</Button>
              <Button onClick={() => { void handleCreate(); }} loading={saving} disabled={!form.name.trim()}>
                Create customer
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
}
