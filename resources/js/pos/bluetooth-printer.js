/**
 * Cetak via Web Bluetooth (eksperimental, Chrome/Edge di HTTPS/localhost).
 * Printer thermal Bluetooth umumnya mengekspos service "0000ff00-..."
 * (serial BLE) dengan characteristic notify/write 0xFF02.
 *
 * Bukan jalur utama: agent tetap pilihan pertama untuk USB/WiFi.
 * API: requestDevice → connect → kirim bytes ESC/POS per chunk 20 byte.
 */

const PRINTER_SERVICE = '000018f0-0000-1000-8000-00805f9b34fb';
const WRITE_CHARACTERISTIC = '00002af1-0000-1000-8000-00805f9b34fb';

let device = null;
let characteristic = null;

/** Pilihan pairing ke user (harap dipanggil dari gesture klik). */
export async function pairBluetoothPrinter() {
    if (!navigator.bluetooth) {
        throw new Error('Browser ini tidak mendukung Web Bluetooth. Gunakan Chrome/Edge.');
    }

    device = await navigator.bluetooth.requestDevice({
        filters: [{ services: [PRINTER_SERVICE] }],
        optionalServices: [PRINTER_SERVICE],
    });

    const server = await device.gatt.connect();
    const service = await server.getPrimaryService(PRINTER_SERVICE);
    characteristic = await service.getCharacteristic(WRITE_CHARACTERISTIC);

    return device.name ?? 'printer';
}

/** Kirim bytes ke printer BT; pecah per 20 byte (MTU aman). */
export async function writeBluetooth(bytes) {
    if (!characteristic) {
        throw new Error('Printer Bluetooth belum dipasangkan.');
    }

    for (let i = 0; i < bytes.length; i += 20) {
        const chunk = bytes.slice(i, i + 20);
        await characteristic.writeValue(chunk);
    }
    return true;
}

export function isBluetoothSupported() {
    return !!navigator.bluetooth;
}
