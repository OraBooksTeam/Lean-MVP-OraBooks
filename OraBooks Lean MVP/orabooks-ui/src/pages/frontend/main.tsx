import React from 'react';
import ReactDOM from 'react-dom/client';
import FrontendRoutes from './App';
import ExportTriggerButton from '@/components/platform/ExportTriggerButton';
import { enforceHttpsIfRequired } from '@/lib/security/sl008';
import { registerOraBooksPwa } from '@/lib/pwa/register-pwa';
import { absorbAuthTokensFromUrl } from './lib/auth-routing';
import { migrateLegacyHashUrl } from './lib/wp-routing';
import '@/styles/index.css';

declare global {
  interface Window {
    orabooksReactMounted?: boolean;
    orabooksBootFrontend?: () => void;
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

function bootExportWidgets() {
  document.querySelectorAll<HTMLElement>('.orabooks-export-trigger-root').forEach((el) => {
    if (el.dataset.orabooksMounted === '1') {
      return;
    }
    el.dataset.orabooksMounted = '1';
    const exportType = el.dataset.exportType || 'report';
    const format = (el.dataset.format === 'pdf' ? 'pdf' : 'csv') as 'csv' | 'pdf';
    const label = el.dataset.label || 'Export CSV';
    ReactDOM.createRoot(el).render(
      <ExportTriggerButton exportType={exportType} format={format} label={label} />
    );
  });
}

function showFrontendBootError(root: HTMLElement, message: string) {
  root.classList.add('is-mounted');
  root.innerHTML = `<div style="padding:24px;border:1px solid #d8e6f3;border-radius:16px;background:#fff;color:#102f52;max-width:640px;margin:48px auto;">
    <h2 style="margin:0 0 8px;color:#1A69B4;">OraBooks could not start</h2>
    <p style="margin:0;">${message}</p>
  </div>`;
}

function mountThemePortal(root: HTMLElement) {
  if (!document.body.classList.contains('orabooks-page')) {
    return;
  }

  const immersive =
    document.body.classList.contains('orabooks-react-page')
    || document.body.classList.contains('orabooks-auth-page');

  if (!immersive) {
    return;
  }

  if (root.parentElement !== document.body) {
    document.body.appendChild(root);
  }

  root.classList.add('orabooks-theme-portal', 'orabooks-divi-portal');
  document.body.classList.add('orabooks-app-mounted');
}

function bootFrontend() {
  if (window.orabooksReactMounted) {
    return;
  }

  migrateLegacyHashUrl();
  enforceHttpsIfRequired();

  registerOraBooksPwa();
  bootExportWidgets();

  const root = document.getElementById('orabooks-app-root');
  if (!root) {
    return;
  }

  mountThemePortal(root);
  void absorbAuthTokensFromUrl().catch(() => undefined);

  try {
    window.orabooksReactMounted = true;
    root.classList.add('is-mounted');
    document.body.classList.add('orabooks-app-mounted');
    ReactDOM.createRoot(root).render(
      <FrontendErrorBoundary>
        <FrontendRoutes />
      </FrontendErrorBoundary>
    );
  } catch (err) {
    const msg = err instanceof Error ? err.message : 'Unknown error starting OraBooks.';
    showFrontendBootError(root, msg);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootFrontend);
} else {
  bootFrontend();
}

window.addEventListener('load', bootFrontend);
window.orabooksBootFrontend = bootFrontend;
