import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider, createHashRouter } from 'react-router-dom';
import { enforceHttpsIfRequired } from '@/lib/security/sl008';
import AdminRoutes from './routes';
import { mountStandaloneApp } from './standalone';
import '@/styles/index.css';

declare global {
  interface Window {
    orabooksAdminMounted?: boolean;
  }
}

function showAdminBootError(root: HTMLElement, message: string) {
  root.classList.add('is-mounted');
  root.innerHTML = `<div style="padding:24px;border:1px solid #d8e6f3;border-radius:16px;background:#fff;color:#102f52;">
    <h2 style="margin:0 0 8px;color:#1A69B4;">OraBooks could not start</h2>
    <p style="margin:0;">${message}</p>
  </div>`;
}

function bootAdmin() {
  enforceHttpsIfRequired();

  const adminRoot = document.getElementById('orabooks-admin-root');
  if (!adminRoot) {
    return;
  }

  const initialRoute = adminRoot.dataset.adminRoute || '/admin/dashboard';
  window.location.hash = initialRoute;

  try {
    const router = createHashRouter([
      {
        path: '*',
        element: <AdminRoutes />,
      },
    ]);

    window.orabooksAdminMounted = true;
    adminRoot.classList.add('is-mounted');
    ReactDOM.createRoot(adminRoot).render(
      <React.StrictMode>
        <RouterProvider router={router} />
      </React.StrictMode>
    );
  } catch (err) {
    const msg = err instanceof Error ? err.message : 'Unknown error starting admin UI.';
    showAdminBootError(adminRoot, msg);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootAdmin);
} else {
  bootAdmin();
}

const appRoot = document.getElementById('orabooks-app-root');
if (appRoot) {
  mountStandaloneApp(appRoot);
}
