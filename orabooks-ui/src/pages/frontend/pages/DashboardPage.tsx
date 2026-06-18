import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import PartnerProgramPage from './PartnerProgramPage';
import {
  ArrowRight,
  Banknote,
  FileText,
  Landmark,
  RefreshCw,
  TrendingUp,
  Users,
} from 'lucide-react';

interface FrontendContext {
  user: { is_partner: boolean; email?: string };
  organization: {
    id: number;
    name?: string;
    tier?: string;
    status?: string;
    organization_type?: string;
  } | null;
  role?: string;
}

interface CustomerDashboardData {
  context: FrontendContext;
  stats: Record<string, number>;
  accounts_summary: { type: string; total: string | number }[];
  journal_statuses: { status: string; total: string | number }[];
  recent_journals: Record<string, any>[];
  recent_invoices: Record<string, any>[];
  timestamp?: string;
}

export default function DashboardPage() {
  const [context, setContext] = useState<FrontendContext | null>(null);
  const [customerData, setCustomerData] = useState<CustomerDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [isPartner, setIsPartner] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    setCustomerData(null);

    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError(ctx.error || 'Please log in to view your dashboard.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data as FrontendContext;
    setContext(nextContext);

    const partner = nextContext.organization?.organization_type === 'partner' || nextContext.user?.is_partner;
    setIsPartner(partner);
    if (partner) {
      setLoading(false);
      return;
    }

    const res = await api.customerDashboard();
    if (res.error) setError(res.error || 'Failed to load dashboard.');
    else setCustomerData((res as any).data);
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  if (loading) {
    return (
      <div className="brand-page-bg min-h-screen p-6 lg:pl-80">
        <div className="w-full space-y-6">
          <div className="h-10 w-48 animate-pulse rounded-lg bg-slate-200" />
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="h-32 animate-pulse rounded-2xl bg-white border border-slate-200" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="brand-page-bg min-h-screen flex items-center justify-center">
        <div className="glass-panel max-w-md p-6 text-center">
          <p className="font-medium text-danger">{error}</p>
          <Button onClick={load} variant="secondary" className="mt-4">Try again</Button>
        </div>
      </div>
    );
  }

  if (isPartner) {
    return <PartnerProgramPage />;
  }

  const data = customerData;
  const stats = data?.stats || {};
  const organization = data?.context.organization || context?.organization;
  const cards = [
    { label: 'Total Customers', value: stats.total_customers ?? 0, icon: Users, tone: 'text-primary bg-primary/10' },
    { label: 'Open Invoices', value: stats.unpaid_invoices ?? 0, icon: FileText, tone: 'text-primary bg-primary/10' },
    { label: 'Outstanding AR', value: money(stats.outstanding_ar), icon: Banknote, tone: 'text-primary bg-primary/10' },
    { label: 'Paid Revenue', value: money(stats.total_revenue), icon: TrendingUp, tone: 'text-success bg-success/10' },
  ];

  return (
    <ClientShell title="Client Dashboard" eyebrow="Accounting workspace" organization={organization}>
      <div className="space-y-6">
        <section className="rounded-2xl border border-primary/20 bg-gradient-to-r from-primary to-primary/85 p-6 text-white shadow-lg shadow-primary/20">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div className="mb-2 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wide">
                <Landmark className="h-4 w-4" />
                Full Accounting Suite
              </div>
              <h2 className="text-2xl font-black">Advanced Accounting Workspace</h2>
              <p className="mt-2 max-w-2xl text-sm text-white/85">
                Sales, purchases, inventory, GL, journal entries, assets, and financial reports live in the
                dedicated accounting workspace — separate from this Lean MVP summary dashboard.
              </p>
            </div>
            <a
              href="/accounting/"
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-primary transition hover:bg-white/90"
            >
              Open Advanced Accounting
              <ArrowRight className="h-4 w-4" />
            </a>
          </div>
        </section>

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {cards.map((card) => {
            const Icon = card.icon;
            return (
              <div key={card.label} className="stat-card">
                <div className={`mb-4 inline-flex rounded-xl p-2.5 ${card.tone}`}>
                  <Icon className="h-5 w-5" />
                </div>
                <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{card.label}</p>
                <p className="mt-1 text-3xl font-black text-ink">{card.value}</p>
              </div>
            );
          })}
        </div>

        <div className="grid gap-4 xl:grid-cols-3">
          <Panel title="Recent Invoices" className="xl:col-span-2">
            <DataList
              empty="No invoices yet."
              rows={(data?.recent_invoices || []).map((invoice) => ({
                key: invoice.id,
                title: invoice.invoice_number || `Invoice #${invoice.id}`,
                meta: `${invoice.payment_status || 'unpaid'} · due ${invoice.due_date || 'not set'}`,
                value: money(invoice.total_amount, invoice.currency),
              }))}
            />
          </Panel>
          <Panel title="Accounting Health">
            <StatRow label="Paid Invoices" value={stats.paid_invoices ?? 0} />
            <StatRow label="Overdue Invoices" value={stats.overdue_invoices ?? 0} />
            <StatRow label="Active Customers" value={stats.active_customers ?? 0} />
            <StatRow label="Inactive Customers" value={stats.inactive_customers ?? 0} />
          </Panel>
          <Panel title="Chart of Accounts">
            <DataList
              empty="No accounts loaded."
              rows={(data?.accounts_summary || []).map((row) => ({
                key: row.type,
                title: titleCase(row.type),
                meta: 'Active accounts',
                value: row.total,
              }))}
            />
          </Panel>
          <Panel title="Journal Workflow" className="xl:col-span-2">
            <DataList
              empty="No journal entries yet."
              rows={(data?.recent_journals || []).map((journal) => ({
                key: journal.id,
                title: journal.journal_number || `Journal #${journal.id}`,
                meta: `${journal.status || 'draft'} · ${journal.transaction_date || journal.created_at || ''}`,
                value: money(journal.total_amount),
              }))}
            />
          </Panel>
        </div>
      </div>
    </ClientShell>
  );
}

function Panel({ title, children, className }: { title: string; children: ReactNode; className?: string }) {
  return (
    <section className={`glass-panel p-5 ${className || ''}`}>
      <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">{title}</h2>
      <div className="mt-4">{children}</div>
    </section>
  );
}

function DataList({
  rows,
  empty,
}: {
  rows: { key: string | number; title: string; meta: string; value: string | number }[];
  empty: string;
}) {
  if (rows.length === 0) {
    return <p className="rounded-xl border border-dashed border-border p-5 text-center text-sm text-slate-500">{empty}</p>;
  }

  return (
    <div className="divide-y divide-border">
      {rows.map((row) => (
        <div key={row.key} className="flex items-center justify-between gap-4 py-3">
          <div>
            <p className="font-semibold text-ink">{row.title}</p>
            <p className="text-xs text-slate-500">{row.meta}</p>
          </div>
          <p className="shrink-0 text-sm font-bold text-ink">{row.value}</p>
        </div>
      ))}
    </div>
  );
}

function StatRow({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-border bg-white px-4 py-2.5">
      <span className="text-sm text-slate-600">{label}</span>
      <span className="text-sm font-bold text-ink">{value || '—'}</span>
    </div>
  );
}

function money(value?: string | number, currency = 'USD') {
  const amount = Number(value || 0);
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);
}

function titleCase(value?: string) {
  return (value || 'Other').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
