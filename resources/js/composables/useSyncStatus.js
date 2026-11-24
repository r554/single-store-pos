import { reactive } from 'vue';
import { initSync, onSyncState, syncNow } from '../pos/sync';

/**
 * Status koneksi & sinkronisasi untuk UI (indikator offline/pending sync).
 */
const state = reactive({
    status: navigator.onLine === false ? 'offline' : 'online',
    pending: null,
    syncing: false,
});

let initialized = false;

export function useSyncStatus() {
    if (!initialized) {
        initialized = true;
        initSync();
        onSyncState((s) => {
            state.status = s.status;
            state.pending = s.pending;
            state.syncing = s.status === 'syncing';
        });
    }

    return {
        state,
        syncNow,
    };
}
