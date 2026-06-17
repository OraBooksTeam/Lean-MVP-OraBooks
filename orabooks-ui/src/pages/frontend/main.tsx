import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider, createHashRouter } from 'react-router-dom';
import FrontendRoutes from './App';
import '@/styles/index.css';

declare global {
  interface Window {
    orabooksReactMounted?: boolean;
  }
}

class FrontendErrorBoundary extends React.Component<
  { children: React.ReactNode },
  { error: string }
> {
  state = { error: '' };

  static getDerivedStateFromError(error: Error) {
    return { error: error.message || 'The OraBooks frontend could not render.' };
  }

  render() {
    if (this.state.error) {
      return (
        <div className="min-h-screen bg-muted px-4 py-12 text-ink">
          <div className="mx-auto max-w-2xl rounded-2xl border border-border bg-white p-6 shadow-xl shadow-primary/10">
            <p className="text-xs font-bold uppercase tracking-[0.3em] text-accent">OraBooks</p>
            <h1 className="mt-2 text-2xl font-black text-primary">Frontend render error</h1>
            <p className="mt-3 text-sm text-ink-secondary">
              The React bundle loaded, but a page component failed to render. Check the browser
              console for the full stack trace.
            </p>
            <pre className="mt-4 overflow-auto rounded-xl bg-muted p-4 text-xs text-ink">
              {this.state.error}
            </pre>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

const root = document.getElementById('orabooks-app-root');
const initialRoute = root?.dataset.initialRoute;
if (initialRoute && !window.location.hash) {
  window.location.hash = initialRoute;
}

const router = createHashRouter([
  {
    path: '/',
    element: <FrontendRoutes />,
    children: [
      { path: '/', element: <div /> },
      { path: '/login', element: <div /> },
      { path: '/register', element: <div /> },
      { path: '/tier-selection', element: <div /> },
      { path: '/dashboard', element: <div /> },
      { path: '/customers', element: <div /> },
      { path: '/invoices', element: <div /> },
      { path: '/chart-of-accounts', element: <div /> },
      { path: '/journals', element: <div /> },
      { path: '/partner-onboarding', element: <div /> },
      { path: '/notifications', element: <div /> },
      { path: '/my-exports', element: <div /> },
      { path: '/profile', element: <div /> },
    ],
  },
]);

if (root) {
  window.orabooksReactMounted = true;
  ReactDOM.createRoot(root).render(
    <FrontendErrorBoundary>
      <RouterProvider router={router} />
    </FrontendErrorBoundary>
  );
}
