import { useMemo } from 'react';
import { useLocation } from 'react-router-dom';

interface AdminNavItem {
  slug: string;
  label: string;
  route: string;
}

function adminUrl(slug: string) {
  const base = (window as any).orabooks_ajax?.admin_base || '/wp-admin/admin.php';
  return `${base}?page=${slug}`;
}

export default function AdminSubnav() {
  const location = useLocation();
  const currentRoute = location.hash?.replace(/^#/, '') || '/admin/dashboard';

  const items = useMemo(() => {
    const nav = (window as any).orabooks_ajax?.admin_nav as AdminNavItem[] | undefined;
    return nav?.length ? nav : [];
  }, []);

  if (items.length === 0) {
    return null;
  }

  return (
    <nav
      className="mb-4 overflow-hidden rounded-2xl border border-border bg-white shadow-sm shadow-primary/5"
      aria-label="OraBooks sections"
    >
      <div className="brand-accent-bar h-1" />
      <div className="flex flex-wrap gap-1 p-2">
        {items.map((item) => {
          const active = currentRoute === item.route || currentRoute.startsWith(`${item.route}/`);
          return (
            <a
              key={item.slug}
              href={adminUrl(item.slug)}
              className={`rounded-lg px-3 py-2 text-xs font-semibold transition sm:text-sm ${
                active
                  ? 'bg-primary text-white shadow-sm shadow-primary/25'
                  : 'text-ink-secondary hover:bg-primary/5 hover:text-primary'
              }`}
            >
              {item.label}
            </a>
          );
        })}
      </div>
    </nav>
  );
}
