import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { registerSW } from 'virtual:pwa-register';

// Registrasi service worker PWA (cache app-shell untuk mode offline).
const updateSW = registerSW({
    onNeedRefresh() {
        // TODO: tampilkan prompt "update tersedia" (versi berikutnya).
        console.info('Versi baru aplikasi tersedia. Muat ulang untuk memperbarui.');
    },
    onOfflineReady() {
        console.info('Aplikasi siap digunakan offline.');
    },
});

// Ekspos agar bisa dipanggil dari konsol bila perlu (debug/opsi update).
window.__updateSW = updateSW;

createApp(App).use(router).mount('#app');
