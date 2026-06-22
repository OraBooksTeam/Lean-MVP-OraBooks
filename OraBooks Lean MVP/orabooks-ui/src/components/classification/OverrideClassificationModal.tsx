import { useState } from 'react';
import Button from '@/components/Button';
import Input from '@/components/Input';

type OverrideClassificationModalProps = {
  open: boolean;
  accountCode: string;
  taxRate: string;
  saving: boolean;
  onClose: () => void;
  onSubmit: (accountCode: string, taxRate?: number) => void;
};

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function OverrideClassificationModal({
  open,
  accountCode: initialAccount,
  taxRate: initialTaxRate,
  saving,
  onClose,
  onSubmit,
}: OverrideClassificationModalProps) {
  const [accountCode, setAccountCode] = useState(initialAccount);
  const [taxRate, setTaxRate] = useState(initialTaxRate);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <h3 className="text-lg font-bold text-ink">Override AI classification</h3>
        <p className="mt-1 text-sm text-slate-600">Choose the account code and optional tax rate.</p>
        <div className="mt-4 grid gap-3">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-600">Account code</span>
            <Input value={accountCode} onChange={(e) => setAccountCode(e.target.value)} placeholder="5100" />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-600">Tax rate (%)</span>
            <Input
              type="number"
              min="0"
              max="100"
              step="0.01"
              value={taxRate}
              onChange={(e) => setTaxRate(e.target.value)}
              placeholder="Optional"
              className={fieldClass}
            />
          </label>
        </div>
        <div className="mt-5 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button
            onClick={() => onSubmit(accountCode.trim(), taxRate.trim() ? Number(taxRate) : undefined)}
            disabled={saving || !accountCode.trim()}
          >
            Save override
          </Button>
        </div>
      </div>
    </div>
  );
}
