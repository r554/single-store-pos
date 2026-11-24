import { db, getDeviceId, getMeta, setMeta } from './db';
import { kickSync, newClientUuid } from './sync';

/**
 * Checkout offline-first.
 *
 * Alur:
 * 1. Transaksi SELALU ditulis ke IndexedDB (transactions + transaction_items)
 *    dengan nomor struk lokal OFF-{device}-{seq} dan status pending.
 * 2. Payload sync di-enqueue ke sync_queue (outbox).
 * 3. Saat online, sync engine push ke server; ServerController memberi
 *    nomor final; sync engine menandai transaksi synced dan menyimpan
 *    nomor final (replaceNumber).
 * 4. Stok lokal langsung dikurangi supaya kasir tahu stok tersisa.
 */

/** Sequence nomor struk offline, per device (persist di meta). */
async function nextOfflineSeq() {
    const current = await getMeta('offline_seq', 0);
    const next = current + 1;
    await setMeta('offline_seq', next);
    return next;
}

/** Pembulatan 2 desimal (hindari error floating point). */
function round(value) {
    return Math.round(value * 100) / 100;
}

/**
 * Simpan transaksi lokal + enqueue outbox + kurangi stok lokal.
 * Opsi shiftId: id shift server (bila sudah tersinkron) atau client_uuid
 * shift lokal saat offline — server memetakan saat shift_open diproses.
 * Return record transaksi (untuk struk).
 */
export async function checkout({ cart, discount, tax = 0, paymentMethod, paymentAmount, shiftId = null }) {
    if (cart.length === 0) {
        throw new Error('Keranjang kosong.');
    }

    const clientUuid = newClientUuid();
    const deviceId = await getDeviceId();
    const seq = await nextOfflineSeq();
    const now = new Date();
    const transactedAt = now.toISOString();

    const subtotal = cart.reduce((sum, item) => sum + Number(item.subtotal), 0);
    const taxAmount = Math.max(0, round(Number(tax) || 0));
    const total = Math.max(0, round(subtotal - (discount || 0) + taxAmount));
    const change = Math.max(0, round(paymentAmount - total));

    const receiptNo = `OFF-${deviceId}-${String(seq).padStart(5, '0')}`;

    const record = {
        client_uuid: clientUuid,
        receipt_no: receiptNo,
        status: 'pending',
        shift_id: shiftId,
        subtotal,
        discount: discount || 0,
        tax: taxAmount,
        total,
        payment_method: paymentMethod || 'cash',
        payment_amount: paymentAmount,
        change,
        transacted_at: transactedAt,
        device_id: deviceId,
    };

    await db.transaction('rw', db.transactions, db.transaction_items, db.products, db.sync_queue, async () => {
        // Header + items.
        await db.transactions.put(record);
        for (const item of cart) {
            await db.transaction_items.put({
                transaction_uuid: clientUuid,
                product_id: item.product_id,
                name: item.name,
                price: Number(item.price),
                qty: item.qty,
                discount: item.discount || 0,
                subtotal: Number(item.subtotal),
            });
        }

        // Kurangi stok lokal.
        for (const item of cart) {
            const product = await db.products.get(item.product_id);
            if (product) {
                await db.products.update(item.product_id, {
                    stock: Math.max(0, product.stock - item.qty),
                });
            }
        }

        // Enqueue payload sync (outbox).
        await db.sync_queue.add({
            client_uuid: clientUuid,
            type: 'transaction',
            status: 'pending',
            attempts: 0,
            payload: {
                subtotal,
                discount: discount || 0,
                tax: taxAmount,
                total,
                payment_method: paymentMethod || 'cash',
                payment_amount: paymentAmount,
                change,
                transacted_at: transactedAt,
                device_id: deviceId,
                shift_id: shiftId,
                items: cart.map((item) => ({
                    product_id: item.product_id,
                    name: item.name,
                    price: Number(item.price),
                    qty: item.qty,
                    discount: item.discount || 0,
                    subtotal: Number(item.subtotal),
                })),
            },
            created_at: now.toISOString(),
        });
    });

    // Trigger sync (no-op bila offline — event online yang akan menjalankan).
    kickSync();

    return record;
}

/** Ganti nomor struk offline → final, tandai synced. */
export async function markSynced(clientUuid, receiptNo) {
    await db.transactions.update(clientUuid, {
        status: 'synced',
        receipt_no: receiptNo,
        synced_at: new Date().toISOString(),
    });
}
