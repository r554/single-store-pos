import { v7 as uuidv7 } from 'uuid';
import api from '../api';
import { db, getMeta, setMeta } from './db';
import { markSynced } from './checkout';

/**
 * Sync engine: push outbox ke server saat online + pull delta katalog.
 *
 * Prinsip:
 * - Semua record lokal punya client_uuid (UUIDv7) → idempoten di server.
 * - Push per batch (maks 50), FIFO. Gagal → status tetap pending, di-retry
 *   dengan exponential backoff.
 * - Pull delta katalog berdasar cursor updated_at sebelum push,
 *   supaya data kasir segar sebelum transaksi masuk.
 */

const BATCH_SIZE = 50;
const BASE_BACKOFF_MS = 5000;
const MAX_BACKOFF_MS = 5 * 60 * 1000;

let syncing = false;
let backoffAttempt = 0;
let timer = null;
let pollHandle = null;

const listeners = new Set();

function notify(state) {
    for (const fn of listeners) fn(state);
}

/** Berlangganan perubahan status sync. Return fungsi unsubscribe. */
export function onSyncState(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
}

function isOnline() {
    return navigator.onLine !== false;
}

/** Minta sync segera (jalankan bila online; aman dipanggil kapan pun). */
export function kickSync() {
    scheduleSync(0);
}

/** Enqueue record manual ke outbox (Fase 2: dipakai shift, stok adjustment). */
export async function enqueue(type, clientUuid, payload) {
    const now = new Date().toISOString();
    await db.sync_queue.add({
        client_uuid: clientUuid,
        type,
        status: 'pending',
        attempts: 0,
        payload,
        created_at: now,
    });
    scheduleSync(0);
}

/** Jalankan sync bila online: pull delta lalu push outbox. */
export async function syncNow() {
    if (syncing || !isOnline()) {
        return;
    }

    syncing = true;
    notify({ status: 'syncing', pending: await pendingCount() });

    try {
        await pullCatalog();
        await pushOutbox();
        backoffAttempt = 0;
    } catch (err) {
        applyBackoff(err);
    } finally {
        syncing = false;
        notify({ status: idleStatus(), pending: await pendingCount() });
    }
}

function idleStatus() {
    if (!isOnline()) return 'offline';
    return 'online';
}

function applyBackoff(err) {
    const isNetworkError = !err?.response;
    if (!isNetworkError) {
        // Server merespons (mis. 4xx/5xx): catat saja, jangan backoff agresif.
        console.warn('[sync] server error:', err?.response?.status, err?.message);
    }
    backoffAttempt += 1;
    const delay = Math.min(
        BASE_BACKOFF_MS * 2 ** (backoffAttempt - 1),
        MAX_BACKOFF_MS,
    );
    scheduleSync(delay);
}

/** Poll berkala saat online supaya outbox tertinggal ikut terkirim. */
function ensurePolling() {
    if (pollHandle) return;
    pollHandle = setInterval(() => {
        if (isOnline() && !syncing) {
            pendingCount().then((n) => {
                if (n > 0) syncNow();
            });
        }
    }, 15000);
}

function scheduleSync(delayMs) {
    if (!isOnline() && delayMs === 0) {
        return; // menunggu event online.
    }
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        timer = null;
        syncNow();
    }, delayMs);
}

async function pendingCount() {
    return db.sync_queue.where('status').equals('pending').count();
}

/**
 * Tarik delta katalog dari server → simpan ke IndexedDB.
 * Produk yang dinonaktifkan di server dihapus dari IDB agar
 * tidak muncul di kasir offline.
 *
 * Jika IDB kosong tapi cursor lama tersimpan (mis. data terhapus),
 * cursor diabaikan → tarik snapshot penuh agar katalog tidak
 * kosong selamanya.
 */
export async function pullCatalog() {
    let cursor = await getMeta('catalog_cursor');

    if (cursor) {
        const localCount = await db.products.count();
        if (localCount === 0) {
            cursor = null;
        }
    }

    const { data } = await api.get('/pos/catalog', {
        params: cursor ? { updated_since: cursor } : undefined,
    });

    await db.transaction('rw', db.products, db.categories, db.meta, async () => {
        for (const product of data.products) {
            if (product.is_active === false) {
                await db.products.delete(product.id);
            } else {
                await db.products.put(product);
            }
        }
        for (const category of data.categories) {
            await db.categories.put(category);
        }
        await setMeta('catalog_cursor', data.cursor ?? nowIso());
    });
}

function nowIso() {
    return new Date().toISOString();
}

/** Push outbox per batch. Server dedup lewat client_uuid. */
async function pushOutbox() {
    while (true) {
        const batch = await db.sync_queue
            .where('status')
            .equals('pending')
            .limit(BATCH_SIZE)
            .toArray();

        if (batch.length === 0) break;

        const { data } = await api.post('/pos/sync', {
            records: batch.map((r) => ({
                client_uuid: r.client_uuid,
                type: r.type,
                payload: r.payload,
                created_at: r.created_at,
            })),
        });

        const accepted = new Set(data.accepted ?? []);
        const rejected = data.rejected ?? [];
        const receiptMap = data.receipt_map ?? {};

        await db.transaction('rw', db.sync_queue, db.transactions, async () => {
            for (const record of batch) {
                const isAccepted = accepted.has(record.client_uuid);
                const isRejected = rejected.some((r) => r.client_uuid === record.client_uuid);

                if (isAccepted && !isRejected) {
                    // Hapus dari outbox; terapkan nomor struk final bila ada.
                    await db.sync_queue.where('client_uuid').equals(record.client_uuid).delete();
                    const finalNo = receiptMap[record.client_uuid];
                    if (finalNo) {
                        await markSynced(record.client_uuid, finalNo);
                    }
                } else if (isRejected) {
                    const reason = rejected.find((r) => r.client_uuid === record.client_uuid);
                    await db.sync_queue.where('client_uuid').equals(record.client_uuid).modify({
                        status: 'failed',
                        last_error: reason?.error ?? 'rejected by server',
                    });
                }
                // Bukan accepted maupun rejected: biarkan pending (di-retry).
            }
        });

        notify({ status: 'syncing', pending: await pendingCount() });
    }
}

/** Binding event koneksi; dipanggil sekali dari useSyncStatus. */
export function initSync() {
    window.addEventListener('online', () => {
        notify({ status: 'online', pending: null });
        scheduleSync(0);
    });
    window.addEventListener('offline', () => {
        notify({ status: 'offline', pending: null });
    });
    ensurePolling();
    notify({ status: idleStatus(), pending: null });

    if (isOnline()) {
        scheduleSync(0);
    }

    return () => clearInterval(pollHandle);
}

/** Buat UUIDv7 baru untuk record lokal. */
export function newClientUuid() {
    return uuidv7();
}
