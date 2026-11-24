/**
 * Local Print Agent — jembatan browser ↔ printer kasir.
 *
 * Berjalan di PC kasir: http://127.0.0.1:9101
 * Endpoint:
 *   GET  /health        → status agent + config aktif
 *   POST /print         → cetak struk (payload JSON, lihat buildReceipt)
 *   POST /test          → cetak pola tes
 *
 * Transport printer (dari config):
 *   - tcp://192.168.x.x:9100   printer WiFi/LAN (jetdirect)
 *   - lpt                       printer paralel/USB share "LPT1" (Windows raw)
 *   - file://C:/pos/last.bin    dump ke file (untuk tes / spool manual)
 *
 * Jalankan: node print-agent/agent.mjs
 */

import http from 'node:http';
import net from 'node:net';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const CONFIG_PATH = path.join(__dirname, 'agent-config.json');

// ===== Config =====

const DEFAULT_CONFIG = {
    printer_url: 'file://receipt-last.bin', // ganti: tcp://192.168.1.50:9100
    paper_width: 80, // 58 atau 80 (mm)
    store_name: 'Single Store',
    store_address: '',
    store_phone: '',
    receipt_footer: 'Terima kasih atas kunjungan Anda!',
    char_delay_ms: 0, // delay antar chunk (printer lambat)
};

function loadConfig() {
    try {
        const raw = fs.readFileSync(CONFIG_PATH, 'utf8');
        return { ...DEFAULT_CONFIG, ...JSON.parse(raw) };
    } catch {
        return { ...DEFAULT_CONFIG };
    }
}

const config = loadConfig();

// Origin PWA yang diizinkan (CORS). Tambahkan origin lain bila perlu.
const ALLOWED_ORIGINS = new Set([
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:80',
    'null', // file:// saat dev
]);

// ===== ESC/POS builder (subset yang dipakai struk kasir) =====

const ESC = 0x1b;
const GS = 0x1d;

class EscPos {
    constructor() {
        this.bytes = [];
    }

    /** Init printer. */
    init() {
        this.bytes.push(ESC, 0x40);
        return this;
    }

    /** Codepage PC437 (default thermal). */
    codepage(n = 0) {
        this.bytes.push(ESC, 0x74, n & 0xff);
        return this;
    }

    align(mode) {
        const map = { left: 0, center: 1, right: 2 };
        this.bytes.push(ESC, 0x61, map[mode] ?? 0);
        return this;
    }

    /** bold on/off */
    bold(on) {
        this.bytes.push(ESC, 0x45, on ? 1 : 0);
        return this;
    }

    /** Ukuran teks: normal, double height, double width+height. */
    size(mode) {
        const map = { normal: 0x00, double_height: 0x01, double_width: 0x10, double: 0x11 };
        this.bytes.push(GS, 0x21, map[mode] ?? 0x00);
        return this;
    }

    text(str) {
        for (const ch of Buffer.from(str, 'latin1')) {
            this.bytes.push(ch);
        }
        return this;
    }

    newline(n = 1) {
        for (let i = 0; i < n; i++) this.bytes.push(0x0a);
        return this;
    }

    /** Potong kertas (partial cut, lebih aman untuk kebanyakan thermal). */
    cut() {
        this.bytes.push(GS, 0x56, 0x01);
        return this;
    }

    /** Feed n baris lalu potong. */
    feedAndCut(n = 3) {
        this.newline(n);
        return this.cut();
    }

    toBuffer() {
        return Buffer.from(this.bytes);
    }
}

/** Format satu baris struk dua kolom (label kiri, nilai kanan). */
function line(e, label, value, widthChars) {
    const l = String(label ?? '');
    const v = String(value ?? '');
    const space = Math.max(1, widthChars - l.length - v.length);
    e.text(l + ' '.repeat(space) + v);
    e.newline();
}

/** Bangun bytes struk dari payload POST /print. */
function buildReceipt(payload) {
    const width = payload.paper_width ?? config.paper_width;
    const widthChars = width === 58 ? 32 : 48;

    const e = new EscPos();
    e.init();
    e.align('center');
    e.bold(true);
    e.size('double_height');
    e.text((payload.store_name ?? config.store_name).slice(0, widthChars));
    e.newline();
    e.bold(false);
    e.size('normal');

    for (const meta of [config.store_address, config.store_phone]) {
        if (meta) {
            e.text(meta.slice(0, widthChars));
            e.newline();
        }
    }
    e.newline();

    e.align('left');
    line(e, 'Tanggal', new Date().toLocaleString('id-ID'), widthChars);
    line(e, 'No. Struk', payload.receipt_no ?? '-', widthChars);
    if (payload.cashier) {
        line(e, 'Kasir', payload.cashier, widthChars);
    }
    e.text('-'.repeat(widthChars));
    e.newline();

    for (const item of payload.items ?? []) {
        e.text(String(item.name ?? '').slice(0, widthChars));
        e.newline();
        line(e, `  ${item.qty} x ${formatMoney(item.price)}`, formatMoney(item.subtotal), widthChars);
        if (item.discount > 0) {
            line(e, '  Diskon', '-' + formatMoney(item.discount), widthChars);
        }
    }

    e.text('-'.repeat(widthChars));
    e.newline();

    e.bold(true);
    line(e, 'TOTAL', formatMoney(payload.total), widthChars);
    e.bold(false);
    if (payload.discount > 0) {
        line(e, 'Diskon', '-' + formatMoney(payload.discount), widthChars);
    }
    line(e, `Bayar (${payload.payment_method ?? 'cash'})`, formatMoney(payload.payment_amount), widthChars);
    line(e, 'Kembali', formatMoney(payload.change), widthChars);

    e.newline();
    e.align('center');
    e.text((payload.footer ?? config.receipt_footer).slice(0, widthChars * 2));
    e.newline();
    e.feedAndCut(3);

    return e.toBuffer();
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

// ===== Transport =====

function sendToPrinter(buffer, printerUrl) {
    const url = printerUrl ?? config.printer_url;

    if (url.startsWith('tcp://')) {
        return sendTcp(url, buffer);
    }
    if (url.startsWith('file://')) {
        return sendFile(url.slice('file://'.length), buffer);
    }
    if (url.startsWith('lpt')) {
        return sendLpt(url, buffer);
    }
    return Promise.reject(new Error(`Transport tidak dikenal: ${url}`));
}

function sendTcp(url, buffer) {
    const parsed = new URL(url);
    const host = parsed.hostname;
    const port = Number(parsed.port || 9100);

    return new Promise((resolve, reject) => {
        const socket = net.connect({ host, port, timeout: 5000 });
        socket.on('connect', () => {
            socket.end(buffer, () => resolve({ transport: 'tcp', host, port, bytes: buffer.length }));
        });
        socket.on('timeout', () => {
            socket.destroy();
            reject(new Error(`Timeout menyambung ${host}:${port}`));
        });
        socket.on('error', reject);
    });
}

function sendFile(filePath, buffer) {
    const target = path.isAbsolute(filePath) ? filePath : path.join(__dirname, filePath);
    fs.writeFileSync(target, buffer);
    return Promise.resolve({ transport: 'file', path: target, bytes: buffer.length });
}

import { promises as fsp } from 'node:fs';

async function sendLpt(url, buffer) {
    // Windows: raw write ke LPT (perlu printer di-share; alternatif: copy /b).
    const target = url.replace(/^file:\/\//, '');
    await fsp.writeFile(target, buffer);
    return { transport: 'lpt', path: target, bytes: buffer.length };
}

// ===== HTTP server =====

const server = http.createServer(async (req, res) => {
    const origin = req.headers.origin ?? '';
    const allowedOrigin = ALLOWED_ORIGINS.has(origin) ? origin : null;

    const corsHeaders = {
        'Access-Control-Allow-Origin': allowedOrigin ?? 'http://localhost',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type',
    };

    if (req.method === 'OPTIONS') {
        res.writeHead(204, corsHeaders);
        res.end();
        return;
    }

    // Hanya terima request dari localhost (keamanan dasar).
    const isLocal = ['127.0.0.1', '::1', 'localhost'].includes(req.socket.remoteAddress);

    function json(status, body) {
        res.writeHead(status, { 'Content-Type': 'application/json', ...corsHeaders });
        res.end(JSON.stringify(body));
    }

    if (!isLocal) {
        json(403, { error: 'Akses hanya dari localhost' });
        return;
    }

    if (req.method === 'GET' && req.url === '/health') {
        json(200, { ok: true, printer_url: config.printer_url, paper_width: config.paper_width });
        return;
    }

    if (req.method === 'POST' && (req.url === '/print' || req.url === '/test')) {
        let body = '';
        for await (const chunk of req) body += chunk;

        let payload;
        try {
            payload = body ? JSON.parse(body) : {};
        } catch {
            json(400, { error: 'JSON tidak valid' });
            return;
        }

        try {
            const buffer = req.url === '/test'
                ? buildReceipt({
                    receipt_no: 'TEST',
                    items: [{ name: 'Tes Cetak 1x', qty: 1, price: 1000, subtotal: 1000, discount: 0 }],
                    total: 1000,
                    payment_method: 'cash',
                    payment_amount: 1000,
                    change: 0,
                    footer: 'Jika terbaca, printer siap dipakai.',
                })
                : buildReceipt(payload);

            const info = await sendToPrinter(buffer, payload.printer_url);
            json(200, { ok: true, ...info });
        } catch (err) {
            json(502, { ok: false, error: err.message });
        }
        return;
    }

    json(404, { error: 'Tidak ditemukan' });
});

server.listen(9101, '127.0.0.1', () => {
    console.log('Local Print Agent berjalan di http://127.0.0.1:9101');
    console.log('Printer:', config.printer_url, `(${config.paper_width}mm)`);
    console.log('Config:', CONFIG_PATH);
});
