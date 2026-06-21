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
    orabooksPwa?: {
      canInstall: boolean;
      promptInstall: () => Promise<'accepted' | 'dismissed' | 'unavailable'>;
    };
  }
}

let deferredInstallPrompt: BeforeInstallPromptEvent | null = null;

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

function normalizeScope(scope: string | undefined, serviceWorkerUrl: string): string {
  if (scope) {
    try {
      return new URL(scope).href;
    } catch {
      return scope;
    }
  }

  return new URL('./', serviceWorkerUrl).href;
}

function notifyServiceWorkerUpdate(registration: ServiceWorkerRegistration): void {
  const waiting = registration.waiting;
  if (!waiting) {
    return;
  }

  waiting.postMessage({ type: 'SKIP_WAITING' });
  waiting.addEventListener('statechange', () => {
    if (waiting.state === 'activated') {
      window.location.reload();
    }
  });
}

export function registerOraBooksPwa(): void {
  const config = window.orabooks_ajax?.pwa;
  if (!config?.enabled || !config.service_worker_url) {
    return;
  }
  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event as BeforeInstallPromptEvent;
    window.dispatchEvent(new CustomEvent('orabooks-pwa-installable'));
  });

  window.orabooksPwa = {
    canInstall: false,
    promptInstall: async () => {
      if (!deferredInstallPrompt) {
        return 'unavailable';
      }

      await deferredInstallPrompt.prompt();
      const choice = await deferredInstallPrompt.userChoice;
      deferredInstallPrompt = null;
      return choice.outcome;
    },
  };

  Object.defineProperty(window.orabooksPwa, 'canInstall', {
    get: () => deferredInstallPrompt !== null,
  });

  window.addEventListener('load', () => {
    const scope = normalizeScope(config.service_worker_scope, config.service_worker_url!);

    void navigator.serviceWorker
      .register(config.service_worker_url!, { scope, type: 'classic' })
      .then((registration) => {
        registration.addEventListener('updatefound', () => {
          const installing = registration.installing;
          if (!installing) {
            return;
          }

          installing.addEventListener('statechange', () => {
            if (installing.state === 'installed' && navigator.serviceWorker.controller) {
              notifyServiceWorkerUpdate(registration);
            }
          });
        });

        if (registration.waiting && navigator.serviceWorker.controller) {
          notifyServiceWorkerUpdate(registration);
        }
      })
      .catch(() => {
        // Non-fatal — app still works without SW.
      });
  });
}

export function onOnline(callback: () => void): () => void {
  window.addEventListener('online', callback);
  return () => window.removeEventListener('online', callback);
}

export function isMobileDevice(): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  return window.matchMedia('(max-width: 768px), (pointer: coarse)').matches;
}

export function isStandalonePwa(): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  return window.matchMedia('(display-mode: standalone)').matches
    || (window.navigator as Navigator & { standalone?: boolean }).standalone === true;
}
