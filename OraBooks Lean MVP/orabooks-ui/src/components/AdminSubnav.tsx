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
  const { pathname } = useLocation();
  const currentRoute = pathname || '/admin/dashboard';

  const items = useMemo(() => {
    const nav = (window as any).orabooks_ajax?.admin_nav as AdminNavItem[] | undefined;
    return nav?.length ? nav : [];
  }, []);

  if (items.length === 0) {
    return null;
  }

  return (
    <nav
      className="orabooks-admin-subnav mb-4 overflow-hidden rounded-2xl border border-border bg-white shadow-sm shadow-primary/5"
      aria-label="OraBooks sections"
    >
      <div className="flex flex-wrap gap-1 p-2">
        {items.map((item) => {
          const active = currentRoute === item.route || currentRoute.startsWith(`${item.route}/`);
          return (
            <a
              key={item.slug}
              href={adminUrl(item.slug)}
              className={`orabooks-admin-subnav-link ${active ? 'is-active' : ''}`}
            >
              {item.label}
            </a>
          );
        })}
      </div>
    </nav>
  );
}
