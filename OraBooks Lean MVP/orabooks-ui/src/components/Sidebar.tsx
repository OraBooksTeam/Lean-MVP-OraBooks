import { LayoutDashboard, Building2, Users, UserCheck, ClipboardList, Settings, BookOpen } from 'lucide-react';
import { cn } from '@/lib/utils';

const nav = [
  { label: 'Dashboard', to: '/admin/dashboard', icon: <LayoutDashboard className="h-4 w-4" /> },
  { label: 'Organizations', to: '/admin/organizations', icon: <Building2 className="h-4 w-4" /> },
  { label: 'Users & Teams', to: '/admin/users', icon: <Users className="h-4 w-4" /> },
  { label: 'Partners', to: '/admin/partners', icon: <UserCheck className="h-4 w-4" /> },
  { label: 'Chart of Accounts', to: '/admin/coa', icon: <BookOpen className="h-4 w-4" /> },
  { label: 'Audit Log', to: '/admin/audit', icon: <ClipboardList className="h-4 w-4" /> },
  { label: 'Settings', to: '/admin/settings', icon: <Settings className="h-4 w-4" /> },
];

export default function Sidebar() {
  return (
    <aside className="hidden w-64 flex-col border-r border-border bg-white md:flex">
      <div className="flex h-16 items-center gap-2.5 border-b border-border px-5">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-black text-white">OB</div>
        <span className="text-base font-bold text-ink">OraBooks</span>
      </div>
      <nav className="flex-1 overflow-y-auto p-3">
        <ul className="space-y-0.5">
          {nav.map((item) => (
            <li key={item.to}>
              <a
                href={item.to}
                className={cn(
                  'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-ink'
                )}
              >
                <span className="text-slate-500">{item.icon}</span>
                {item.label}
              </a>
            </li>
          ))}
        </ul>
      </nav>
    </aside>
  );
}
