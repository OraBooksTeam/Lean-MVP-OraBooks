import type { ReactNode } from 'react';

interface AdminPageShellProps {
  title: string;
  eyebrow?: string;
  description?: string;
  children: ReactNode;
  actions?: ReactNode;
}

export default function AdminPageShell({
  title,
  eyebrow = 'OraBooks Admin',
  description,
  children,
  actions,
}: AdminPageShellProps) {
  return (
    <div className="space-y-6">
      <header className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm shadow-primary/5">
        <div className="brand-accent-bar h-1.5" />
        <div className="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-primary">{eyebrow}</p>
            <h1 className="mt-1 text-2xl font-bold text-ink">{title}</h1>
            {description && <p className="mt-1 text-sm text-ink-secondary">{description}</p>}
          </div>
          {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
      </header>
      {children}
    </div>
  );
}
