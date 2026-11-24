/**
 * Modul cetak struk (client side) — jalur berjenjang:
 *
 * 1. Local Print Agent 127.0.0.1:9101 → raw ESC/POS (USB/LPT/WiFi, kualitas tercepat).
 * 2. Web Bluetooth (eksperimental, Chrome) → printer BT 58mm.
 * 3. window.print + CSS thermal → fallback universal via dialog print.
 *
 * Konfigurasi (localStorage "pos_print" — diubah lewat modal pengaturan cetak):
 *   { mode: 'auto'|'agent'|'browser', storeName, storeAddress, storePhone, footer, paperWidth }
 */

const SETTINGS_KEY = 'pos_print';
const AGENT_URL = 'http://127.0.0.1:9101';

const DEFAULT_SETTINGS = {
    mode: 'auto', // auto: coba agent → browser
    storeName: 'Single Store',
    storeAddress: '',
    storePhone: '',
    footer: 'Terima kasih atas kunjungan Anda!',
    paperWidth: 80,
};

export function getPrintSettings() {
    try {
        const raw = localStorage.getItem(SETTINGS_KEY);
        return raw ? { ...DEFAULT_SETTINGS, ...JSON.parse(raw) } : { ...DEFAULT_SETTINGS };
    } catch {
        return { ...DEFAULT_SETTINGS };
    }
}

export function savePrintSettings(settings) {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify({ ...getPrintSettings(), ...settings }));
}

/** Cek apakah print agent hidup. */
export async function isAgentReachable() {
    try {
        const res = await fetch(`${AGENT_URL}/health`, { signal: AbortSignal.timeout(1500) });
        return res.ok;
    } catch {
        return false;
    }
}

/** Tes cetak via agent. */
export async function testPrintAgent() {
    const res = await fetch(`${AGENT_URL}/test`, {
        method: 'POST',
        signal: AbortSignal.timeout(8000),
    });
    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.error ?? `Agent error ${res.status}`);
    }
    return res.json();
}

/**
 * Cetak struk. receipt = {
 *   receiptNo, cashier, items[{name,qty,price,discount,subtotal}],
 *   subtotal, discount, total, paymentMethod, paymentAmount, change, footer
 * }
 */
export async function printReceipt(receipt) {
    const settings = getPrintSettings();

    if (settings.mode !== 'browser') {
        const ok = await printViaAgent(receipt, settings);
        if (ok) return { via: 'agent' };
        if (settings.mode === 'agent') {
            throw new Error('Print agent tidak terjangkau. Jalankan print-agent/start-print-agent.bat atau ubah mode cetak ke Browser.');
        }
    }

    printViaBrowser(receipt, settings);
    return { via: 'browser' };
}

async function printViaAgent(receipt, settings) {
    try {
        const res = await fetch(`${AGENT_URL}/print`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(buildAgentPayload(receipt, settings)),
            signal: AbortSignal.timeout(8000),
        });
        return res.ok;
    } catch {
        return false;
    }
}

function buildAgentPayload(receipt, settings) {
    return {
        store_name: settings.storeName,
        paper_width: settings.paperWidth,
        receipt_no: receipt.receiptNo,
        cashier: receipt.cashier,
        items: receipt.items.map((i) => ({
            name: i.name,
            qty: i.qty,
            price: i.price,
            discount: i.discount || 0,
            subtotal: i.subtotal,
        })),
        subtotal: receipt.subtotal,
        discount: receipt.discount || 0,
        total: receipt.total,
        payment_method: receipt.paymentMethod,
        payment_amount: receipt.paymentAmount,
        change: receipt.change,
        footer: receipt.footer ?? settings.footer,
    };
}

/** Fallback universal: jendela print dengan template HTML thermal. */
export function printViaBrowser(receipt, settings) {
    const html = buildReceiptHtml(receipt, settings);
    const win = window.open('', '_blank', 'width=380,height=640');
    if (!win) {
        throw new Error('Popup diblokir. Izinkan popup untuk mencetak struk.');
    }
    win.document.write(html);
    win.document.close();
    win.focus();
    win.print();
    win.close();
}

function buildReceiptHtml(receipt, settings) {
    const money = (v) => 'Rp ' + Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    const row = (label, value, cls = '') =>
        `<div class="row ${cls}"><span>${esc(label)}</span><span>${esc(value)}</span></div>`;

    const itemsHtml = receipt.items.map((i) => `
        <div class="item">
            <div class="item-name">${esc(i.name)}</div>
            ${row(`${i.qty} x ${money(i.price)}`, money(i.subtotal), 'sub')}
            ${i.discount ? row('Diskon', '-' + money(i.discount), 'sub') : ''}
        </div>`).join('');

    const widthMm = settings.paperWidth === 58 ? 58 : 80;

    return `<!doctype html>
<html><head><meta charset="utf-8"><title>Struk ${esc(receipt.receiptNo ?? '')}</title>
<style>
    @page { size: ${widthMm}mm auto; margin: 2mm; }
    body { font-family: 'Courier New', monospace; font-size: 11px; color: #000; width: 100%; }
    h1 { font-size: 13px; text-align: center; margin: 0; letter-spacing: 1px; }
    .center { text-align: center; }
    .meta { margin: 2px 0 6px; }
    hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
    .item { margin: 3px 0; }
    .item-name { font-weight: bold; }
    .sub { color: #333; font-size: 10px; }
    .row { display: flex; justify-content: space-between; }
    .totals .row { margin: 1px 0; }
    .grand { font-weight: bold; font-size: 13px; border-top: 1px solid #000; padding-top: 3px; margin-top: 3px; }
    .footer { text-align: center; margin-top: 8px; }
</style></head>
<body>
    <h1>${esc(settings.storeName)}</h1>
    ${settings.storeAddress ? `<div class="center meta">${esc(settings.storeAddress)}</div>` : ''}
    ${settings.storePhone ? `<div class="center meta">${esc(settings.storePhone)}</div>` : ''}
    <hr>
    ${row('Tanggal', new Date().toLocaleString('id-ID'))}
    ${row('No. Struk', receipt.receiptNo ?? '-')}
    ${receipt.cashier ? row('Kasir', receipt.cashier) : ''}
    <hr>
    ${itemsHtml}
    <div class="totals">
        <hr>
        ${row('Subtotal', money(receipt.subtotal))}
        ${receipt.discount ? row('Diskon', '-' + money(receipt.discount)) : ''}
        ${receipt.tax ? row('PPN', money(receipt.tax)) : ''}
        <div class="row grand"><span>TOTAL</span><span>${money(receipt.total)}</span></div>
        ${row(`Bayar (${receipt.paymentMethod ?? 'cash'})`, money(receipt.paymentAmount))}
        ${row('Kembali', money(receipt.change))}
    </div>
    <div class="footer">${esc(receipt.footer ?? settings.footer)}</div>
    <script>window.onload = function () { window.print(); };</script>
</body></html>`;
}

function esc(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
