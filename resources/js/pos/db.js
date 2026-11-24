import Dexie from 'dexie';

/**
 * Database lokal aplikasi kasir (IndexedDB via Dexie).
 * Semua transaksi offline ditulis di sini (outbox pattern),
 * lalu dipush ke server oleh sync engine saat koneksi tersedia.
 */
export const db = new Dexie('single-store-pos');

db.version(1).stores({
    // Katalog (mirror dari server, untuk jualan offline).
    products: 'id, sku, category_id, updated_at',
    categories: 'id, updated_at',

    // Outbox sync: antrian record yang menunggu di-upload.
    // type: 'transaction' | 'stock_adjustment' | 'shift_open' | 'shift_close'
    sync_queue: '++seq, client_uuid, type, status, created_at',
    sync_queue_status: '[type+status]',

    // Transaksi lokal (hasil checkout kasir).
    transactions: 'client_uuid, status, created_at, shift_id',
    transaction_items: 'transaction_uuid, product_id',

    // Shift kasir lokal.
    shifts: 'client_uuid, status, opened_at, closed_at',

    // Meta: cursor katalog, device id, dsb.
    meta: 'key',
});

/**
 * Versi 2: tambah index `name` (products, categories) untuk
 * orderBy('name'). Tanpa index, query tsb melempar SchemaError
 * (KeyPath name is not indexed) → katalog kasir tampak kosong.
 */
db.version(2).stores({
    products: 'id, sku, name, barcode, category_id, updated_at',
    categories: 'id, name, updated_at',
    sync_queue: '++seq, client_uuid, type, status, created_at',
    sync_queue_status: '[type+status]',
    transactions: 'client_uuid, status, created_at, shift_id',
    transaction_items: 'transaction_uuid, product_id',
    shifts: 'client_uuid, status, opened_at, closed_at',
    meta: 'key',
});

/** Ambil nilai meta berdasarkan key. */
export async function getMeta(key, fallback = null) {
    const row = await db.meta.get(key);
    return row ? row.value : fallback;
}

/** Simpan nilai meta berdasarkan key. */
export async function setMeta(key, value) {
    await db.meta.put({ key, value });
}

/** ID perangkat kasir (stabil, dipakai untuk prefix nomor offline). */
export async function getDeviceId() {
    let id = await getMeta('device_id');
    if (!id) {
        id = 'DEV-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        await setMeta('device_id', id);
    }
    return id;
}
