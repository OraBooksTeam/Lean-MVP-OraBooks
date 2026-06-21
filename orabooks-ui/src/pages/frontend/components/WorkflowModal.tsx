import { useEffect, useId, type ReactNode } from 'react';
import { X } from 'lucide-react';
import Button from '@/components/Button';
import { cn } from '@/lib/utils';

type WorkflowModalProps = {
  open: boolean;
  title: string;
  description?: string;
  children?: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  confirmVariant?: 'primary' | 'danger';
  loading?: boolean;
  confirmDisabled?: boolean;
  onConfirm: () => void;
  onClose: () => void;
};

export default function WorkflowModal({
  open,
  title,
  description,
  children,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  confirmVariant = 'primary',
  loading = false,
  confirmDisabled = false,
  onConfirm,
  onClose,
}: WorkflowModalProps) {
  const titleId = useId();

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', onKeyDown);
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
      <button
        type="button"
        className="absolute inset-0 bg-black/45 backdrop-blur-[2px]"
        aria-label="Close dialog"
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="relative w-full max-w-md overflow-hidden rounded-2xl border border-primary/15 bg-white shadow-2xl shadow-primary/20"
      >
        <div className="flex items-start justify-between gap-3 border-b border-border bg-gradient-to-r from-primary/5 to-accent/5 px-5 py-4">
          <div>
            <h2 id={titleId} className="text-lg font-bold text-ink">
              {title}
            </h2>
            {description ? <p className="mt-1 text-sm text-slate-600">{description}</p> : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-500 transition hover:bg-white hover:text-ink"
            aria-label="Close"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {children ? <div className="space-y-4 px-5 py-4">{children}</div> : null}

        <div className="flex justify-end gap-2 border-t border-border bg-slate-50/80 px-5 py-4">
          <Button variant="secondary" size="sm" onClick={onClose} disabled={loading}>
            {cancelLabel}
          </Button>
          <Button
            size="sm"
            loading={loading}
            disabled={confirmDisabled || loading}
            onClick={onConfirm}
            className={cn(
              confirmVariant === 'danger'
                ? 'bg-danger hover:opacity-90'
                : 'bg-accent text-white shadow-sm shadow-accent/30 hover:bg-accent/90'
            )}
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
