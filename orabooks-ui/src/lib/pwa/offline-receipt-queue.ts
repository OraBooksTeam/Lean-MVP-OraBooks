const DB_NAME = 'orabooks-pwa';
const STORE = 'offline-receipts';
const DB_VERSION = 1;

export type QueuedReceipt = {
  id: string;
  orgId: number;
  fileName: string;
  mimeType: string;
  idempotencyKey: string;
  createdAt: string;
  data: ArrayBuffer;
};

function openDb(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onerror = () => reject(request.error);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: 'id' });
      }
    };
    request.onsuccess = () => resolve(request.result);
  });
}

function withStore<T>(mode: IDBTransactionMode, fn: (store: IDBObjectStore) => IDBRequest<T>): Promise<T> {
  return openDb().then(
    (db) =>
      new Promise<T>((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const store = tx.objectStore(STORE);
        const request = fn(store);
        request.onsuccess = () => resolve(request.result as T);
        request.onerror = () => reject(request.error);
      })
  );
}

export async function queueReceipt(
  orgId: number,
  file: File,
  idempotencyKey: string
): Promise<QueuedReceipt> {
  const item: QueuedReceipt = {
    id: crypto.randomUUID(),
    orgId,
    fileName: file.name,
    mimeType: file.type || 'image/jpeg',
    idempotencyKey,
    createdAt: new Date().toISOString(),
    data: await file.arrayBuffer(),
  };

  await withStore('readwrite', (store) => store.put(item));
  return item;
}

export async function listQueuedReceipts(): Promise<QueuedReceipt[]> {
  return withStore<QueuedReceipt[]>('readonly', (store) => store.getAll());
}

export async function removeQueuedReceipt(id: string): Promise<void> {
  await withStore('readwrite', (store) => store.delete(id));
}

export async function syncQueuedReceipts(
  upload: (orgId: number, file: File, idempotencyKey: string) => Promise<{ error?: string }>
): Promise<{ synced: number; failed: number }> {
  const items = await listQueuedReceipts();
  let synced = 0;
  let failed = 0;

  for (const item of items) {
    const file = new File([item.data], item.fileName, { type: item.mimeType });
    const result = await upload(item.orgId, file, item.idempotencyKey);
    if (result.error) {
      failed += 1;
    } else {
      await removeQueuedReceipt(item.id);
      synced += 1;
    }
  }

  return { synced, failed };
}

export function isOffline(): boolean {
  return typeof navigator !== 'undefined' && !navigator.onLine;
}
