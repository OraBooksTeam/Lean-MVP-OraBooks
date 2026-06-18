import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider, createHashRouter } from 'react-router-dom';
import AdminRoutes from './routes';
import { mountStandaloneApp } from './standalone';
import '@/styles/index.css';

declare global {
  interface Window {
    orabooksAdminMounted?: boolean;
  }
}

const adminRoot = document.getElementById('orabooks-admin-root');
if (adminRoot) {
  const initialRoute = adminRoot.dataset.adminRoute || '/admin/dashboard';
  if (!window.location.hash || window.location.hash === '#') {
    window.location.hash = initialRoute;
  }

  const router = createHashRouter([
    {
      path: '*',
      element: <AdminRoutes />,
    },
  ]);

  window.orabooksAdminMounted = true;
  ReactDOM.createRoot(adminRoot).render(
    <React.StrictMode>
      <RouterProvider router={router} />
    </React.StrictMode>
  );
}

const appRoot = document.getElementById('orabooks-app-root');
if (appRoot) {
  mountStandaloneApp(appRoot);
}
