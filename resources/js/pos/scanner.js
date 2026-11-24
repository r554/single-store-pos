/**
 * Helper barcode scanner — mode utama keyboard-wedge.
 *
 * Mayoritas scanner USB bekerja seperti keyboard: mengetik kode sangat
 * cepat lalu Enter (suffix CR). Handler global ini mendeteksi pola
 * tersebut tanpa mengganggu ketikan manusia.
 *
 * Logika deteksi scanner (anti-terpotong & anti-salah):
 * - Karakter diakumulasi per "burst"; burst dianggap selesai bila
 *   hening > 250ms atau menerima Enter.
 * - Burst dikirim ke handler hanya bila polanya scanner: mayoritas
 *   jeda antar karakter sangat cepat (<= ~100ms). Ketikan manusia
 *   (jeda 100-300ms per huruf) diabaikan — Enter mereka tetap
 *   berfungsi normal di input pencarian/form.
 * - Jangan membuang karakter di tengah burst walau ada jeda agak
 *   lambat (scanner lambat/buffering) — inilah penyebab barcode
 *   terpotong sebelumnya (8991002111004 → 8991002).
 *
 * Untuk input yang menangani scan sendiri (mis. kolom scan POS),
 * tandai elemennya dengan atribut data-pos-scan agar wedge di sini
 * melewatinya (hindari dobel tambah keranjang).
 *
 * Konfigurasi (localStorage "pos_scanner"):
 *   { mode, minSpeedMs, minLength, beep, suffixEnter }
 */

const SETTINGS_KEY = 'pos_scanner';

const DEFAULT_SETTINGS = {
    mode: 'keyboard', // keyboard | hid | camera
    minSpeedMs: 30, // jeda antar ketik yang dihitung "cepat"
    minLength: 3, // panjang minimum kode
    beep: true, // bunyi saat scan sukses
    suffixEnter: true, // scanner mengirim Enter di akhir (default scanner)
};

export function getScannerSettings() {
    try {
        const raw = localStorage.getItem(SETTINGS_KEY);
        return raw ? { ...DEFAULT_SETTINGS, ...JSON.parse(raw) } : { ...DEFAULT_SETTINGS };
    } catch {
        return { ...DEFAULT_SETTINGS };
    }
}

export function saveScannerSettings(settings) {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify({ ...getScannerSettings(), ...settings }));
}

// ===== Keyboard-wedge engine =====

const FLUSH_TIMEOUT_MS = 250; // hening sebelum burst dianggap selesai
const FAST_GAP_FLOOR_MS = 100; // gap <= ini dihitung "cepat" (scanner)
const HUMAN_PAUSE_MS = 1000; // jeda sepanjang ini = manusia (reset)

let buffer = '';
let lastKeyTime = 0;
let flushTimer = null;
const handlers = new Set();

// Statistik kecepatan burst aktif untuk membedakan scanner vs manusia.
let fastGaps = 0;
let totalGaps = 0;

function settings() {
    return getScannerSettings();
}

function emit(code) {
    for (const fn of handlers) {
        try {
            fn(code);
        } catch (err) {
            console.error('[scanner] handler error:', err);
        }
    }
    if (settings().beep) {
        beep();
    }
}

/** Bunyi pendek konfirmasi scan (Web Audio, tanpa file). */
let audioCtx = null;
function beep() {
    try {
        audioCtx ??= new AudioContext();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.frequency.value = 1200;
        gain.gain.value = 0.08;
        osc.connect(gain).connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.08);
    } catch {
        // Audio diblok: abaikan.
    }
}

function handleKeypress(e) {
    // Jangan intercept kombinasi modifier agar pintasan tetap jalan.
    if (e.ctrlKey || e.metaKey || e.altKey) {
        reset();
        return;
    }

    // Input dengan data-pos-scan menangani scan sendiri (hindari dobel).
    if (e.target?.closest?.('[data-pos-scan]')) {
        reset();
        return;
    }

    const now = performance.now();
    const gap = now - lastKeyTime;
    lastKeyTime = now;

    // Jeda panjang = manusia mulai mengetik ulang → buffer baru.
    if (gap > HUMAN_PAUSE_MS) {
        reset();
    }

    if (e.key === 'Enter') {
        flush();
        return;
    }

    if (e.key.length === 1) {
        if (gap > 0 && gap <= FAST_GAP_FLOOR_MS) {
            fastGaps += 1;
        }
        if (gap > 0 && gap <= HUMAN_PAUSE_MS) {
            totalGaps += 1;
        }
        buffer += e.key;
        scheduleFlush();
    }
}

function scheduleFlush() {
    if (flushTimer) clearTimeout(flushTimer);
    flushTimer = setTimeout(flush, FLUSH_TIMEOUT_MS);
}

function looksLikeScannerBurst(code) {
    const cfg = settings();
    if (code.length < cfg.minLength) return false;
    // Minimal 3 gap cepat, dan mayoritas gap cepat.
    return fastGaps >= 3 && totalGaps > 0 && fastGaps / totalGaps >= 0.5;
}

function flush() {
    if (flushTimer) {
        clearTimeout(flushTimer);
        flushTimer = null;
    }
    const code = buffer.trim();

    // Periksa pola SEBELUM statistik di-reset (kalau tidak, fastGaps
    // selalu 0 dan burst scanner tidak pernah dikenali).
    const isScanner = looksLikeScannerBurst(code);

    // Statistik burst selesai → reset untuk burst berikutnya.
    fastGaps = 0;
    totalGaps = 0;
    buffer = '';

    if (!isScanner) return;

    emit(code);
}

function reset() {
    buffer = '';
    fastGaps = 0;
    totalGaps = 0;
    if (flushTimer) {
        clearTimeout(flushTimer);
        flushTimer = null;
    }
}

let attached = false;

/**
 * Mendaftarkan callback scan. Listener keyboard dipasang sekali.
 * Hasil scan juga bisa datang dari mode kamera/HID via emit() di bawah.
 */
export function onScan(fn) {
    if (!attached) {
        attached = true;
        window.addEventListener('keypress', handleKeypress);
    }
    handlers.add(fn);
    return () => {
        handlers.delete(fn);
    };
}

// ===== Camera scanner (BarcodeDetector) =====

export function isCameraSupported() {
    return 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia;
}

/**
 * Scan via kamera: start() → onDetect(code) → stop().
 * Return controller {stop}.
 */
export async function scanWithCamera(videoEl, onDetect, { formats } = {}) {
    if (!isCameraSupported()) {
        throw new Error('Browser tidak mendukung BarcodeDetector. Gunakan Chrome/Edge terbaru.');
    }

    const detector = new window.BarcodeDetector(
        formats ? { formats } : undefined,
    );

    const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
        audio: false,
    });
    videoEl.srcObject = stream;
    await videoEl.play();

    let stopped = false;
    let lastCode = '';
    let lastTime = 0;

    async function tick() {
        if (stopped) return;
        try {
            const codes = await detector.detect(videoEl);
            if (codes.length > 0) {
                const code = codes[0].rawValue;
                const now = performance.now();
                // Debounce: kode sama dalam 1.5 detik hanya dihitung sekali.
                if (code !== lastCode || now - lastTime > 1500) {
                    lastCode = code;
                    lastTime = now;
                    emit(code);
                    onDetect?.(code);
                }
            }
        } catch {
            // frame belum siap: lanjut.
        }
        if (!stopped) {
            requestAnimationFrame(tick);
        }
    }

    requestAnimationFrame(tick);

    return {
        stop() {
            stopped = true;
            stream.getTracks().forEach((t) => t.stop());
            videoEl.srcObject = null;
        },
    };
}
