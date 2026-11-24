# Local Print Agent

Jembatan kecil antara aplikasi kasir (browser) dan printer struk thermal di PC kasir.

## Cara kerja

```
Browser (PWA) --POST /print--> Agent (127.0.0.1:9101) --raw ESC/POS--> Printer
```

Agent menerjemahkan payload JSON struk menjadi **bytes ESC/POS** lalu mengirimkannya ke printer melalui salah satu transport (lihat `printer_url` di `agent-config.json`):

| `printer_url` | Untuk printer |
|---|---|
| `tcp://192.168.1.50:9100` | Printer WiFi/LAN (port jetdirect standar 9100) |
| `lpt` / `LPT1` | Printer terpasang paralel/USB yang di-share sebagai LPT (Windows) |
| `file://receipt-last.bin` | Dump ke file (tes tanpa printer; bisa dikirim manual: `copy /b receipt-last.bin \\\\PC\\PrinterShare`) |

## Menjalankan

1. Pastikan Node.js terpasang di PC kasir.
2. Edit `agent-config.json` (nama toko, printer, lebar kertas 58/80).
3. Jalankan:
   ```bat
   start-print-agent.bat
   ```
   atau manual: `node print-agent/agent.mjs`
4. Biarkan jendela agent tetap terbuka selama kasir beroperasi. Tambahkan ke Startup folder bila ingin jalan otomatis.

## Endpoint

- `GET /health` — status agent + config aktif.
- `POST /print` — cetak struk (dipanggil otomatis oleh aplikasi setelah checkout).
- `POST /test` — pola tes (cetak struk contoh).

## Keamanan

- Agent hanya menerima koneksi dari `127.0.0.1` (PC sendiri).
- CORS dibatasi origin aplikasi.
- Tidak ada akses ke database/aplikasi — hanya menerima payload struk.

## Printer USB yang tidak support raw/LPT

Mayoritas printer thermal USB bisa dicetak via salah satu cara:
1. Share printer lalu `net use LPT1 \\localhost\NamaPrinter`, lalu `printer_url: "LPT1"`.
2. Printer thermal dengan antarmuka jaringan: pakai `tcp://`.
3. Fallback: aplikasi otomatis memakai `window.print` bila agent tidak berjalan.
