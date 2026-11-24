# Plan: Aplikasi Kasir Single-Store (PWA Offline-First)

## 1. Tujuan

- Kasir tetap berjalan penuh saat internet gangguan (produk, transaksi, struk tetap jalan di komputer kasir).
- Saat internet tersedia kembali, seluruh transaksi otomatis ter-upload ke server tanpa aksi manual.
- Terhubung printer struk thermal (USB / Bluetooth / jaringan) dan mesin scanner barcode.
- Dibangun di atas fondasi yang sudah ada: Laravel + Vue 3 (SPA), session auth, RBAC.
- Platform: **PWA (browser Chrome/Edge)** sesuai keputusan — tanpa install aplikasi desktop.

## 2. Arsitektur Ringkas

```
┌─────────────────────────── PC Kasir (Chrome/Edge) ───────────────────────────┐
│  Vue 3 PWA                                                                   │
│  ├─ Halaman Kasir, Produk, Shift, Laporan                                    │
│  ├─ IndexedDB (Dexie): katalog produk, keranjang, transaksi, sync queue      │
│  ├─ Service Worker: cache app-shell agar bisa dibuka tanpa internet          │
│  ├─ Sync Engine: outbox → push batch saat online, pull delta saat online     │
│  └─ Barcode handler (keyboard-wedge / WebHID / kamera)                       │
│        │                                                                     │
│        ├─► Local Print Agent (127.0.0.1:9101) ──► printer USB thermal        │
│        ├─► Web Bluetooth (opsional) ────────────► printer BT thermal         │
│        └─► window.print CSS thermal (fallback)  ──► printer apa pun          │
└──────────────────────────────────────────────────────────────────────────────┘
                 │  sync saat internet tersedia (HTTPS / LAN)
                 ▼
┌─────────────────── Server Laravel (source of truth) ────────────────────────┐
│  API baru: /pos/catalog, /pos/sync, /pos/shifts, /pos/reports                │
│  Tabel: products, categories, stock_movements, transactions(+items), shifts  │
│  Dedup transaksi via client_uuid (UUIDv7) → tidak ada data ganda             │
└──────────────────────────────────────────────────────────────────────────────┘
```

Prinsip utama: **offline-first dengan outbox sync** — semua transaksi ditulis dulu ke IndexedDB, lalu di-push ke server saat koneksi tersedia. Server adalah sumber kebenaran; data lokal hanya "kaca sementara".

## 3. Komponen & Teknologi

### 3.1 PWA & Data Lokal

| Butuh | Pilihan |
|---|---|
| PWA plugin | `vite-plugin-pwa` (manifest, ikon, service worker, auto-update) |
| Database lokal | `dexie` (IndexedDB) — tabel: `products`, `categories`, `customers`, `settings`, `transactions`, `sync_queue`, `shifts`, `cashier_pin` |
| UUID | `uuid` (v7 — sort by waktu, cocok untuk outbox) |
| Katalog offline | `GET /api/pos/catalog` → snapshot penuh + delta `updated_at > cursor` saat online |

Catatan XAMPP: `http://localhost` dianggap secure origin oleh Chrome, jadi WebUSB/WebHID/Service Worker tetap berjalan tanpa HTTPS. Bila diakses dari PC lain via IP LAN, perlu HTTPS (mkcert) untuk fitur-fitur itu.

### 3.2 Sync Engine (inti offline)

- **Outbox**: setiap transaksi/penyesuaian stok/shift disimpan lokal dengan `client_uuid` dan `status: pending`, FIFO per device.
- **Push**: saat event `online`, timer 15 detik, atau Background Sync API → `POST /api/pos/sync` batch maks 50 transaksi. Berhasil → tandai `synced`, gagal (5xx/offline) → retry dengan exponential backoff.
- **Idempoten**: server menolak/men skip `client_uuid` yang sudah ada → aman dari duplikat saat retry.
- **Pull**: sebelum push, ambil delta katalog (produk baru/stok berubah) agar data kasir segar; yang terakhir kalah dianggap konflik → resolusi: **stok = authority server**, transaksi tidak pernah ditimpa.
- **Nomor struk**: saat offline pakai nomor sementara `OFF-{device}-{seq}`; setelah sync server berikan nomor final berurutan, UI menggantinya otomatis.
- **UI status**: indikator "Offline / Menyinkronkan (n transaksi tertunda)" + tombol "Sync sekarang".

### 3.3 Printer Struk (berlapis, sesuai pilihan "campuran")

1. **Local Print Agent (utama, USB + jaringan)** — skrip Node.js kecil di PC kasir, listen `127.0.0.1:9101`:
   - `POST /print` menerima buffer ESC/POS → kirim raw ke printer USB (USB printer port) atau TCP `9100` untuk printer WiFi/LAN.
   - CORS dibatasi hanya untuk origin aplikasi.
2. **window.print + CSS thermal (fallback universal)** — halaman struk 58/80 mm, `@media print`, bekerja di semua printer via dialog print.
3. **Web Bluetooth (eksperimental)** — langsung dari browser ke printer BT, tanpa agent.

Struk: builder ESC/POS (teks, logo raster opsional, potong kertas), pengaturan lebar 58/80 mm, footer, logo toko.

### 3.4 Scanner Barcode

- **Mode utama — keyboard-wedge (semua scanner USB umum)**: input tersembunyi selalu fokus di halaman kasir; deteksi "ketikan cepat + Enter" = hasil scan.
- **WebHID (Chrome)**: baca scanner langsung dari device tanpa perlu fokus input, untuk kasus operator sering klik di mana-mana.
- **Kamera (PWA di HP/tablet)**: `BarcodeDetector API` sebagai cadangan scan dari kamera.
- Semua hasil scan → event `scan` → tambah ke keranjang.

### 3.5 Modul Aplikasi (halaman baru)

- **Kasir**: grid produk + pencarian + input barcode, keranjang, diskon (per item / global), pajak, bayar tunai (kembalian) / multi-metode, struk.
- **Produk & Kategori**: CRUD, import CSV, atur stok minimum.
- **Stok**: barang masuk, koreksi, opname; riwayat `stock_movements`.
- **Shift**: buka/tutup kasir dengan hitung uang fisik, laporan selisih.
- **Laporan**: penjualan harian, per kasir, produk terlaris, dari data server (mau juga versi lokal saat offline).
- **Pengaturan struk & toko**: nama, alamat, logo, footer, lebar kertas, printer.

### 3.6 Backend (Laravel) — tambahan

- Migrasi: `categories`, `products`, `stock_movements`, `transactions` + `transaction_items`, `shifts`, kolom `client_uuid` (unique), `synced_at`, `device_id`.
- Endpoint (di RBAC `auth` + permission baru `pos.sell`, `products.*`, `shifts.*`, `reports.view`):
  - `GET  /api/pos/catalog` — snapshot/delta katalog.
  - `POST /api/pos/sync` — batch push transaksi (idempoten).
  - `GET/POST /api/pos/shifts` — kelola shift.
  - `GET  /api/pos/reports/*` — laporan.
- Seeder demo: produk contoh + kategori untuk uji coba.

## 4. Fase Kerja (roadmap)

| Fase | Isi | Selesai bila (Definition of Done) | Status |
|---|---|---|---|
| **0 — Fondasi PWA** | `vite-plugin-pwa`, manifest + ikon, service worker cache app-shell, setup Dexie, struktur folder modul POS | App terbuka dan navigasi jalan dengan DevTools "offline" | ✅ Selesai |
| **1 — POS online core** | Migrasi DB, CRUD produk/kategori, halaman Kasir + keranjang, checkout online, struk via `window.print` | Bisa jualan end-to-end saat online, struk tercetak | ✅ Selesai |
| **2 — Offline + Sync** | Outbox queue, sync engine (push/pull/dedup), snapshot katalog, login kasir offline via PIN lokal, UI status sync | Jualan penuh saat offline; internet nyala → data masuk server otomatis; retry aman tanpa duplikat | ✅ Selesai |
| **3 — Printer** | Local print agent, builder ESC/POS, Web Bluetooth eksperimental, pengaturan printer + tes cetak | Struk tercetak via agent (USB & WiFi) dan fallback window.print | ✅ Selesai |
| **4 — Scanner** | Keyboard-wedge handler, WebHID, kamera fallback, pengaturan prefix/suffix | Scan barcode masuk keranjang < 500 ms | ✅ Selesai |
| **5 — Shift, Laporan, Hardening** | Shift open/close, laporan, uji 2 kasir offline paralel, opname stok, dokumentasi | Laporan cocok dengan data server; pengujian offline menyeluruh lulus | 🔄 Selesai inti (shift + laporan); opname stok & uji paralel menyusul |

## 5. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Firefox/Safari tidak punya WebUSB/WebHID | Print agent + `window.print` sebagai jalur utama; fitur eksperimental opsional |
| Stok "drift" saat offline lama | Stok server = authority; opname berkala; laporan selisih |
| Jam PC kasir salah | Server timestamp sebagai waktu resmi; `client_uuid` v7 hanya urutan lokal |
| Dua kasir offline bersamaan | Dedup UUID, FIFO per device, transaksi tidak saling menimpa |
| Akses PWA dari PC lain via IP (bukan localhost) | Fitur perangkat butuh secure context → sediakan HTTPS mkcert atau jalankan di localhost |

## 6. Estimasi & Urutan

- Fase 0–1: fondasi jualan online (pekerjaan terbesar di UI).
- Fase 2: inti keunggulan (sync) — paling perlu pengujian teliti.
- Fase 3–4: perangkat keras (printer + scanner) — cepat setelah fungsinya ada.
- Fase 5: penyelesaian.

Setiap fase diakhiri demo + commit terpisah agar mudah di-review.
