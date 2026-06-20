type PwaConfig = {
  enabled?: boolean;
  service_worker_url?: string;
  service_worker_scope?: string;
};

declare global {
  interface Window {
    orabooks_ajax?: {
      pwa?: PwaConfig;
    };
  }
}

export function registerOraBooksPwa(): void {
  const config = window.orabooks_ajax?.pwa;
  if (!config?.enabled || !config.service_worker_url) {
    return;
  }
  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', () => {
    const scope = config.service_worker_scope
      || new URL('./', config.service_worker_url).href;

    void navigator.serviceWorker.register(config.service_worker_url!, { scope }).catch(() => {
      // Non-fatal — app still works without SW.
    });
  });
}

export function onOnline(callback: () => void): () => void {
  window.addEventListener('online', callback);
  return () => window.removeEventListener('online', callback);
}
