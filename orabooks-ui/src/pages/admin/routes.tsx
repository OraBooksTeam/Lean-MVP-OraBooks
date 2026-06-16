import { Outlet } from 'react-router-dom';
import Sidebar from '@/components/Sidebar';

export default function AdminRoutes() {
  return (
    <div className="flex min-h-screen bg-slate-50 text-ink">
      <Sidebar />
      <main className="flex-1 overflow-y-auto p-6">
        <div className="mx-auto max-w-7xl">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
