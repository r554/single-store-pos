/**
 * WebHID scanner (opsional, Chrome/Edge).
 *
 * Membaca scanner barcode langsung sebagai HID device — hasil scan masuk
 * walau fokus tidak di input aplikasi (operator klik di mana pun).
 * Pairing dilakukan sekali via prompt browser; browser mengingat grant.
 *
 * Dekoder: laporan keyboard HID standar (modifier + keycode array).
 */

// Usage page 0x07 (Keyboard) + usage 0x01 — kandidat scanner.
const HID_FILTER = { usagePage: 0x07, usage: 0x01 };

const KEYMAP = {
    0x04: 'a', 0x05: 'b', 0x06: 'c', 0x07: 'd', 0x08: 'e', 0x09: 'f',
    0x0a: 'g', 0x0b: 'h', 0x0c: 'i', 0x0d: 'j', 0x0e: 'k', 0x0f: 'l',
    0x10: 'm', 0x11: 'n', 0x12: 'o', 0x13: 'p', 0x14: 'q', 0x15: 'r',
    0x16: 's', 0x17: 't', 0x18: 'u', 0x19: 'v', 0x1a: 'w', 0x1b: 'x',
    0x1c: 'y', 0x1d: 'z',
    0x1e: '1', 0x1f: '2', 0x20: '3', 0x21: '4', 0x22: '5',
    0x23: '6', 0x24: '7', 0x25: '8', 0x26: '9', 0x27: '0',
    0x28: '\n', 0x2d: '-', 0x2e: '=', 0x2f: '[', 0x30: ']',
    0x33: ';', 0x34: "'", 0x36: ',', 0x37: '.', 0x38: '/',
};
const SHIFT_KEYMAP = {
    0x04: 'A', 0x05: 'B', 0x06: 'C', 0x07: 'D', 0x08: 'E', 0x09: 'F',
    0x0a: 'G', 0x0b: 'H', 0x0c: 'I', 0x0d: 'J', 0x0e: 'K', 0x0f: 'L',
    0x10: 'M', 0x11: 'N', 0x12: 'O', 0x13: 'P', 0x14: 'Q', 0x15: 'R',
    0x16: 'S', 0x17: 'T', 0x18: 'U', 0x19: 'V', 0x1a: 'W', 0x1b: 'X',
    0x1c: 'Y', 0x1d: 'Z',
    0x1e: '!', 0x1f: '@', 0x20: '#', 0x21: '$', 0x22: '%',
    0x23: '^', 0x24: '&', 0x25: '*', 0x26: '(', 0x27: ')',
};

export function isHidSupported() {
    return !!navigator.hid;
}

let inputReportHandler = null;
let pairedDevice = null;
let buffer = '';
let lastReportTime = 0;

/**
 * Pair + mulai mendengarkan scanner HID.
 * Return nama device. Harus dipanggil dari gesture klik (prompt browser).
 */
export async function pairHidScanner(onCode, { minLength = 3 } = {}) {
    if (!isHidSupported()) {
        throw new Error('Browser tidak mendukung WebHID. Gunakan Chrome/Edge.');
    }

    const [device] = await navigator.hid.requestDevice({ filters: [HID_FILTER] });
    if (!device) {
        throw new Error('Tidak ada scanner dipilih.');
    }

    await disconnectHid();
    await device.open();
    pairedDevice = device;

    inputReportHandler = (event) => {
        const { data } = event;
        if (data.byteLength < 2) return;

        const modifier = data.getUint8(0);
        const shift = (modifier & 0x22) !== 0; // left/right shift
        const now = performance.now();
        if (now - lastReportTime > 300) buffer = ''; // idle → buffer baru
        lastReportTime = now;

        for (let i = 1; i < Math.min(data.byteLength, 9); i++) {
            const keycode = data.getUint8(i);
            if (keycode === 0) continue;

            const ch = shift ? SHIFT_KEYMAP[keycode] : KEYMAP[keycode];
            if (ch === '\n') {
                const code = buffer.trim();
                buffer = '';
                if (code.length >= minLength) {
                    onCode(code);
                }
            } else if (ch) {
                buffer += ch;
            }
        }
    };

    device.addEventListener('inputreport', inputReportHandler);
    return device.productName ?? 'HID scanner';
}

/** Lepaskan scanner HID (mis. ganti mode). */
export async function disconnectHid() {
    if (pairedDevice) {
        try {
            if (inputReportHandler) {
                pairedDevice.removeEventListener('inputreport', inputReportHandler);
            }
            await pairedDevice.close();
        } catch {
            // device sudah lepas: abaikan.
        }
        pairedDevice = null;
        inputReportHandler = null;
        buffer = '';
    }
}
