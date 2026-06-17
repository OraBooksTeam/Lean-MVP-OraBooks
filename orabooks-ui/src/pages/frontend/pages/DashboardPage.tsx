import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import {
  AlertTriangle,
  Banknote,
  BookOpen,
  CheckCircle2,
  Copy,
  FileText,
  RefreshCw,
  TrendingUp,
  Users,
} from 'lucide-react';

interface CustomerDashboardData {
  context: FrontendContext;
  stats: Record<string, number>;
  accounts_summary: { type: string; total: string | number }[];
  journal_statuses: { status: string; total: string | number }[];
  recent_journals: Record<string, any>[];
  recent_invoices: Record<string, any>[];
  timestamp?: string;
}

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

interface PartnerDashboardData {
  partner_code: string;
  partner_type?: string;
  organization_name?: string;
  code_status: string;
  org_status: string;
  org_name?: string;
  active_customer_count: number;
  is_dormant: boolean;
  read_only: boolean;
  payout_disabled: boolean;
  can_reactivate: boolean;
  attribution_stats: { total: number; verified: number; pending: number };
  commission_summary: {
    total_earned: number;
    pending_payout: number;
    paid: number;
    expired: number;
    currency: string;
  };
  payout_breakdown: Record<string, any>[];
  attributions: Record<string, any>[];
}

export default function DashboardPage() {
  const [context, setContext] = useState<FrontendContext | null>(null);
  const [customerData, setCustomerData] = useState<CustomerDashboardData | null>(null);
  const [partnerData, setPartnerData] = useState<PartnerDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    setCustomerData(null);
    setPartnerData(null);

    const ctx = await api.frontendContext();
    if (ctx.error) {
      setError((ctx as any).message || 'Please log in to view your dashboard.');
      setLoading(false);
      return;
    }

    const nextContext = (ctx as any).data as FrontendContext;
    setContext(nextContext);

    const isPartner = nextContext.organization?.organization_type === 'partner' || nextContext.user?.is_partner;
    const res = isPartner ? await api.partnerDashboard() : await api.customerDashboard();
    if (res.error) {
      setError((res as any).message || 'Failed to load dashboard.');
    } else if (isPartner) {
      setPartnerData((res as any).data);
    } else {
      setCustomerData((res as any).data);
    }
    setLoading(false);
  };

  useEffect(() => { void load(); }, []);

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50 p-6 lg:pl-80">
        <div className="mx-auto max-w-7xl space-y-6">
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
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <div className="glass-panel max-w-md p-6 text-center">
          <p className="font-medium text-danger">{error}</p>
          <Button onClick={load} variant="secondary" className="mt-4">Try again</Button>
        </div>
      </div>
    );
  }

  if (partnerData) {
    return <PartnerDashboard context={context} data={partnerData} onRefresh={load} />;
  }

  const data = customerData;
  const stats = data?.stats || {};
  const organization = data?.context.organization || context?.organization;
  const cards = [
    { label: 'Total Customers', value: stats.total_customers ?? 0, icon: Users, tone: 'text-primary bg-primary/10' },
    { label: 'Open Invoices', value: stats.unpaid_invoices ?? 0, icon: FileText, tone: 'text-amber-700 bg-amber-50' },
    { label: 'Outstanding AR', value: money(stats.outstanding_ar), icon: Banknote, tone: 'text-rose-700 bg-rose-50' },
    { label: 'Paid Revenue', value: money(stats.total_revenue), icon: TrendingUp, tone: 'text-emerald-700 bg-emerald-50' },
  ];

  return (
    <ClientShell title="Client Dashboard" eyebrow="Accounting workspace" organization={organization}>
      <div className="space-y-6">
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

function PartnerDashboard({
  context,
  data,
  onRefresh,
}: {
  context: FrontendContext | null;
  data: PartnerDashboardData;
  onRefresh: () => void;
}) {
  const [copied, setCopied] = useState(false);
  const [requesting, setRequesting] = useState(false);
  const organization = context?.organization || {
    name: data.org_name || data.organization_name,
    organization_type: 'partner',
    status: data.org_status,
    tier: 'partner',
  };

  const copyCode = async () => {
    await navigator.clipboard.writeText(data.partner_code);
    setCopied(true);
    void api.partnerCodeCopied('dashboard');
    window.setTimeout(() => setCopied(false), 1800);
  };

  const requestReactivation = async () => {
    if (!context?.organization?.id) return;
    setRequesting(true);
    await api.requestReactivation(context.organization.id, 'Requested from partner dashboard.');
    setRequesting(false);
  };

  return (
    <ClientShell title="Partner Dashboard" eyebrow="Commission workspace" organization={organization} isPartner>
      <div className="space-y-6">
        {data.code_status === 'inactive' && (
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex gap-3">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                <p className="text-sm font-medium">
                  Your partner program is inactive. You have no active customers and no new referral for 12 months. You cannot earn commissions until reactivated.
                </p>
              </div>
              <Button onClick={requestReactivation} loading={requesting} variant="secondary" size="sm">
                Request Reactivation
              </Button>
            </div>
          </div>
        )}

        {data.is_dormant && (
          <div className="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-medium text-sky-900">
            You have no active customers and have not had new attribution in more than 6 months. Share your code to keep your partner activity active.
          </div>
        )}

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <PartnerCard label="Total Earned" value={money(data.commission_summary.total_earned, data.commission_summary.currency)} />
          <PartnerCard label="Pending Payout" value={money(data.commission_summary.pending_payout, data.commission_summary.currency)} />
          <PartnerCard label="Active Customers" value={data.active_customer_count} />
          <PartnerCard label="Verified Attributions" value={data.attribution_stats.verified} />
        </div>

        <div className="grid gap-4 xl:grid-cols-3">
          <Panel title="Your Partner Code">
            <div className="rounded-2xl border border-border bg-slate-50 p-4">
              <p className="font-mono text-lg font-bold tracking-wide text-ink">{data.partner_code}</p>
              <p className="mt-2 text-xs text-slate-500">
                Customers use this code during signup. It earns commissions only while the code is active.
              </p>
              <Button onClick={copyCode} variant="secondary" className="mt-4 w-full">
                {copied ? <CheckCircle2 className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
                {copied ? 'Copied' : 'Copy Code'}
              </Button>
            </div>
          </Panel>
          <Panel title="Partner Status">
            <StatRow label="Code Status" value={data.code_status} />
            <StatRow label="Org Status" value={data.org_status} />
            <StatRow label="Partner Type" value={data.partner_type || 'individual'} />
            <StatRow label="Access Mode" value={data.read_only ? 'Read only' : 'Active'} />
          </Panel>
          <Panel title="Payout Status">
            <StatRow label="Paid" value={money(data.commission_summary.paid, data.commission_summary.currency)} />
            <StatRow label="Expired" value={money(data.commission_summary.expired, data.commission_summary.currency)} />
            <StatRow label="Payouts" value={data.payout_breakdown.length} />
            <StatRow label="Payout Hold" value={data.payout_disabled ? 'Yes' : 'No'} />
          </Panel>
        </div>

        <Panel title="Recent Attributions">
          <DataList
            empty="No attributions yet."
            rows={data.attributions.map((attr) => ({
              key: attr.id,
              title: attr.customer_email_masked || 'Customer',
              meta: `${attr.attribution_status || 'pending'} · ${attr.attribution_date || ''}`,
              value: attr.commission_status || '—',
            }))}
          />
        </Panel>

        <div className="flex justify-end">
          <Button onClick={onRefresh} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
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

function PartnerCard({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="stat-card">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-3xl font-black text-ink">{value}</p>
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
