import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider, createHashRouter } from 'react-router-dom';
import AdminRoutes from './routes';
import AdminDashboard from './AdminDashboard';
import '@/styles/index.css';

declare global {
  interface Window {
    orabooksAdminMounted?: boolean;
  }
}

const adminRoot = document.getElementById('orabooks-admin-root');
if (adminRoot) {
  window.orabooksAdminMounted = true;
  ReactDOM.createRoot(adminRoot).render(
    <React.StrictMode>
      <AdminDashboard />
    </React.StrictMode>
  );
}

const appRoot = document.getElementById('orabooks-app-root');
if (appRoot) {
  const router = createHashRouter([
    {
      path: '/admin/*',
      element: <AdminRoutes />,
    },
  ]);

  ReactDOM.createRoot(appRoot).render(<RouterProvider router={router} />);
}
