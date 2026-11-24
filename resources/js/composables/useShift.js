import { reactive } from 'vue';
import api from '../api';
import { db } from '../pos/db';
import { newClientUuid } from '../pos/sync';
import { getDeviceId } from '../pos/db';
import { useToast } from './useToast';

/**
 * Shift kasir: open/close + state aktif.
 * Offline: shift tetap bisa dibuka (disimpan lokal + outbox);
 * close dengan rekap dari transaksi lokal bila server tak terjangkau.
 */
const state = reactive({
    shift: null,
    loading: false,
    loaded: false,
});

export function useShift() {
    const toast = useToast();

    async function fetchActive() {
        state.loading = true;
        try {
            if (navigator.onLine !== false) {
                const { data } = await api.get('/pos/shifts/active');
                state.shift = data.shift;
                await db.shifts.put({ ...data.shift, client_uuid: String(data.shift.client_uuid) });
            } else {
                const local = await db.shifts.where('status').equals('open').last();
                state.shift = local ?? null;
            }
        } catch {
            // Offline / gagal: coba cache lokal.
            const local = await db.shifts.where('status').equals('open').last();
            state.shift = local ?? null;
        } finally {
            state.loading = false;
            state.loaded = true;
        }
        return state.shift;
    }

    async function open(openingCash) {
        const clientUuid = newClientUuid();
        const deviceId = await getDeviceId();
        const openedAt = new Date().toISOString();

        if (navigator.onLine === false) {
            // Offline: simpan lokal + outbox.
            const record = {
                client_uuid: clientUuid,
                status: 'open',
                user_id: null,
                device_id: deviceId,
                opened_at: openedAt,
                opening_cash: openingCash,
            };
            await db.shifts.put(record);
            const { enqueue } = await import('../pos/sync');
            await enqueue('shift_open', clientUuid, {
                device_id: deviceId,
                opened_at: openedAt,
                opening_cash: openingCash,
            });
            state.shift = record;
            toast.push('Shift dibuka (offline — akan tersinkron saat online).', 'success');
            return record;
        }

        const { data } = await api.post('/pos/shifts', {
            client_uuid: clientUuid,
            opening_cash: openingCash,
            device_id: deviceId,
        });
        state.shift = data.shift;
        await db.shifts.put({ ...data.shift, client_uuid: String(data.shift.client_uuid), status: 'open' });
        toast.push('Shift dibuka.', 'success');
        return data.shift;
    }

    /**
     * Tutup shift. Online: server menghitung rekap & selisih.
     * Offline: rekap dari transaksi lokal (client_uuid shift belum
     * dipetakan ke server → close ditunda via outbox shift_close
     * manual saat online; kasir tetap dapat ringkasan lokal).
     */
    async function close(closingCash) {
        if (!state.shift) return null;

        if (navigator.onLine === false) {
            const items = await db.transactions
                .where('shift_id').equals(Number(state.shift.id) || -1)
                .toArray();
            const localOnly = await db.transactions.toArray();
            const mine = localOnly.filter(
                (t) => t.transacted_at >= state.shift.opened_at,
            );
            const cashTotal = mine
                .filter((t) => t.payment_method === 'cash')
                .reduce((n, t) => n + Number(t.total), 0);
            const expected = Number(state.shift.opening_cash) + cashTotal;

            await db.shifts.update(state.shift.client_uuid, {
                status: 'closed',
                closed_at: new Date().toISOString(),
                closing_cash: closingCash,
                expected_cash: expected,
            });

            const result = {
                shift: await db.shifts.get(state.shift.client_uuid),
                summary: {
                    transaction_count: mine.length,
                    gross_total: mine.reduce((n, t) => n + Number(t.total), 0),
                    cash_total: cashTotal,
                },
                difference: closingCash - expected,
                offline: true,
            };
            state.shift = null;
            return result;
        }

        const shiftId = state.shift.id;
        const { data } = await api.post(`/pos/shifts/${shiftId}/close`, {
            closing_cash: closingCash,
        });
        state.shift = null;
        return data;
    }

    return {
        state,
        fetchActive,
        open,
        close,
    };
}
