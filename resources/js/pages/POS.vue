<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ cashierName }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ clock }} WIB</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <BaseButton
                    variant="primary"
                    title="Cari produk manual (pilih dari daftar)"
                    @click="openProductSearch"
                >
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                        </svg>
                        Cari Produk
                    </span>
                </BaseButton>
                <BaseButton
                    variant="secondary"
                    :loading="reloading"
                    title="Tarik ulang katalog dari server"
                    @click="reloadCatalog"
                >
                    Muat Ulang
                </BaseButton>
                <button
                    type="button"
                    class="flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide transition"
                    :class="shiftState.shift
                        ? 'bg-emerald-500 text-white hover:bg-emerald-600'
                        : 'bg-slate-800 text-white hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700'"
                    :title="shiftState.shift ? 'Tutup shift' : 'Buka shift'"
                    @click="shiftModalMode = shiftState.shift ? 'close' : 'open'"
                >
                    <span class="relative flex h-2 w-2">
                        <span v-if="shiftState.shift" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-60"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full" :class="shiftState.shift ? 'bg-white' : 'bg-amber-400'"></span>
                    </span>
                    {{ shiftState.shift ? 'Shift Aktif' : 'Buka Shift' }}
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 p-2 text-slate-500 transition hover:bg-slate-100 disabled:opacity-40 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                    title="Scan via kamera"
                    :disabled="!cameraSupported"
                    @click="cameraOpen = true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25V6a2.25 2.25 0 012.25-2.25h1.372c.516 0 1.017-.208 1.38-.576l.302-.302c.3-.3.707-.468 1.13-.468h3.128c.423 0 .83.168 1.13.468l.302.302c.363.368.864.576 1.38.576H18.75A2.25 2.25 0 0121 6v2.25M3 8.25v10.5A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V8.25m-18 0h18M7.5 13.5h.008v.008H7.5V13.5zm3.75 0h.008v.008h-.008v-.008zm3.75 0h.008v.008h-.008v-.008z" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 p-2 text-slate-500 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                    title="Pengaturan scanner"
                    @click="scannerSettingsOpen = true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM15.75 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM15 15h.75v.75H15v-.75zM16.5 13.5h.75v.75h-.75v-.75zM13.5 16.5h.75v.75h-.75v-.75z" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 p-2 text-slate-500 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                    title="Pengaturan cetak struk"
                    @click="printSettingsOpen = true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0c.306.05.613.1.919.152a2.223 2.223 0 001.163-3.856m-2.082.704a41.126 41.126 0 00-13.92 0M3 11.5V9a2.25 2.25 0 012.25-2.25h1.372c.95 0 1.852-.4 2.516-1.106A2.25 2.25 0 0110.872 5h2.256a2.25 2.25 0 011.734.808c.644.706 1.566 1.11 2.516 1.11H18.75A2.25 2.25 0 0121 9v2.5m-18 0v5.75A2.25 2.25 0 005.25 20.5h13.5A2.25 2.25 0 0021 17.25v-5.75" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875v14.25M6.75 4.875v3M6.75 12v6.375M9.75 4.875V8.25M9.75 11.25v7.875M12.75 4.875V10.5M12.75 14.25v4.875M15.75 4.875v2.25M15.75 10.125v8.999M18.75 4.875v6.75M18.75 15v3.375" />
            </svg>
            <input
                ref="scanInput"
                v-model="scanQuery"
                type="text"
                data-pos-scan
                placeholder="Scan barcode di sini… — Enter untuk membayar"
                class="w-full rounded-xl border border-slate-300 bg-white py-3.5 pl-12 pr-4 text-base shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @input="onScanInput"
                @keydown.enter.prevent="onScanEnter"
            />
        </div>

        <div
            v-if="catalogError"
            class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
        >
            {{ catalogError }}
            <span v-if="products.length === 0">
                Katalog lokal juga kosong — pastikan Anda sudah login dan produk sudah ditambahkan di menu Produk.
            </span>
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5 dark:border-slate-700">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-800 dark:text-slate-100">Transaksi</h2>
                    <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        {{ cartCount }} item
                    </span>
                </div>
                <button
                    v-if="cart.length > 0"
                    type="button"
                    class="rounded-lg px-2.5 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/30"
                    title="Kosongkan keranjang"
                    @click="clearCart"
                >
                    Kosongkan
                </button>
            </div>

            <div v-if="cart.length === 0" class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                    <svg class="h-8 w-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Keranjang masih kosong</p>
                <p class="text-xs text-slate-400">Scan barcode di kolom atas, atau klik <span class="font-semibold text-indigo-500">Cari Produk</span></p>
            </div>

            <div v-else class="flex min-h-0 flex-1 flex-col">
                <div class="grid shrink-0 grid-cols-[2.5rem_7rem_minmax(0,1fr)_6.5rem_104px_7rem_2rem] items-center gap-3 border-b border-slate-200 bg-slate-100 px-4 py-2 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400">
                    <span>No</span>
                    <span>Kode</span>
                    <span>Nama Barang</span>
                    <span class="text-right">Harga</span>
                    <span class="text-center">Qty</span>
                    <span class="text-right">Subtotal</span>
                    <span />
                </div>
                <div class="min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                <div v-for="(item, idx) in cart" :key="item.product_id" class="grid grid-cols-[2.5rem_7rem_minmax(0,1fr)_6.5rem_104px_7rem_2rem] items-center gap-3 px-4 py-1.5">
                    <div class="text-xs font-semibold text-slate-400 tabular-nums">
                        {{ idx + 1 }}
                    </div>
                    <div class="truncate text-xs font-medium text-slate-500 dark:text-slate-400" :title="item.barcode || item.sku">
                        {{ item.barcode || item.sku || '—' }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100" :title="item.name">{{ item.name }}</p>
                    </div>
                    <div class="text-right text-xs text-slate-500 tabular-nums dark:text-slate-400">
                        {{ formatRupiah(item.price) }}
                    </div>
                    <div class="flex items-center justify-center">
                        <button
                            type="button"
                            class="h-8 w-8 rounded-l-lg border border-slate-200 bg-slate-50 text-base font-bold leading-none text-slate-600 transition hover:bg-slate-200 disabled:opacity-40 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            :disabled="item.qty <= 1"
                            @click="setQty(item, item.qty - 1)"
                        >−</button>
                        <input
                            type="text"
                            inputmode="numeric"
                            class="h-8 w-10 border-y border-slate-200 bg-white p-0 text-center text-sm font-bold text-slate-800 focus:bg-indigo-50 focus:outline-none dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            v-model.lazy="item.qty"
                            @focus="$event.target.select()"
                            @keydown.enter.prevent="$event.target.blur()"
                            @blur="setQty(item, item.qty)"
                        />
                        <button
                            type="button"
                            class="h-8 w-8 rounded-r-lg border border-slate-200 bg-slate-50 text-base font-bold leading-none text-slate-600 transition hover:bg-slate-200 disabled:opacity-40 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            :disabled="item.qty >= item.maxQty"
                            @click="setQty(item, item.qty + 1)"
                        >+</button>
                    </div>
                    <div class="text-right text-sm font-bold text-slate-800 tabular-nums dark:text-slate-100">
                        {{ formatRupiah(item.subtotal) }}
                    </div>
                    <button
                        type="button"
                        class="flex justify-center rounded-md p-1.5 text-slate-300 transition hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-900/30"
                        :title="`Hapus ${item.name}`"
                        @click="removeItem(item)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
                </div>
            </div>

            <div class="shrink-0 border-t-2 border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/40">
                <div class="mb-3 grid grid-cols-3 gap-3">
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Subtotal</p>
                        <p class="mt-0.5 text-sm font-bold text-slate-700 dark:text-slate-200">{{ formatRupiah(subtotal) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Diskon (Rp)</p>
                        <input
                            v-model.number="discount"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-bold text-slate-700 focus:outline-none dark:text-slate-200"
                            @focus="$event.target.select()"
                        />
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800">
                        <label class="flex cursor-pointer items-center gap-2 select-none">
                            <input
                                v-model="taxEnabled"
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer accent-indigo-600"
                            />
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                PPN 10%
                            </span>
                        </label>
                    </div>
                </div>
                <div
                    class="flex flex-col items-center justify-center rounded-xl bg-slate-900 px-5 py-4 dark:bg-slate-950"
                >
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Total Bayar</span>
                    <span class="mt-1 text-5xl font-black leading-none tracking-tight text-emerald-400 tabular-nums sm:text-6xl">
                        {{ formatRupiah(total) }}
                    </span>
                    <span v-if="discount > 0 || taxAmount > 0" class="mt-1.5 text-[11px] text-slate-400">
                        <span v-if="discount > 0">Diskon −{{ formatRupiah(discount) }}</span>
                        <span v-if="discount > 0 && taxAmount > 0"> · </span>
                        <span v-if="taxAmount > 0">PPN {{ Number(taxPercent) || 0 }}% {{ formatRupiah(taxAmount) }}</span>
                    </span>
                </div>
                <BaseButton
                    variant="primary"
                    class="mt-3 w-full py-4 text-base font-extrabold uppercase tracking-wide"
                    :disabled="cart.length === 0"
                    @click="openPayment"
                >
                    Bayar Sekarang (Enter)
                </BaseButton>
            </div>
        </div>

        <!-- Modal cari produk -->
        <div v-if="productSearchOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeProductSearch">
            <div class="flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Cari Produk</h2>
                        <button type="button" class="rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="closeProductSearch">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <input
                        v-model="productSearchQuery"
                        type="text"
                        data-pos-scan
                        placeholder="Ketik nama / SKU / barcode…"
                        class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        @keydown.enter.prevent="pickFirstResult"
                    />
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div v-if="loading" class="grid grid-cols-2 gap-3">
                        <div v-for="i in 6" :key="i" class="h-24 animate-pulse rounded-xl bg-slate-200 dark:bg-slate-700" />
                    </div>

                    <div v-else-if="filteredProducts.length === 0" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                        Produk tidak ditemukan.
                    </div>

                    <div v-else class="grid grid-cols-2 gap-3">
                        <button
                            v-for="product in filteredProducts"
                            :key="product.id"
                            type="button"
                            class="group rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-indigo-400 hover:shadow dark:border-slate-700 dark:bg-slate-800"
                            @click="addToCart(product)"
                        >
                            <p class="line-clamp-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ product.name }}
                            </p>
                            <p class="mt-1 text-[11px] text-slate-400">{{ product.sku ?? '-' }}</p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-sm font-bold text-indigo-600 dark:text-indigo-300">
                                    {{ formatRupiah(product.price) }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                    :class="product.stock > 0
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'"
                                >
                                    {{ product.stock }} pcs
                                </span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-if="clearCartDialog"
            title="Kosongkan Keranjang"
            :message="`Hapus semua ${cart.length} item dari keranjang? Item yang sudah dipilih akan dibuang.`"
            @cancel="clearCartDialog = false"
            @confirm="onClearCartConfirmed"
        />

        <PrinterSettingsModal
            v-if="printSettingsOpen"
            @close="printSettingsOpen = false"
        />

        <ScannerSettingsModal
            v-if="scannerSettingsOpen"
            @close="scannerSettingsOpen = false"
        />

        <CameraScanOverlay
            v-if="cameraOpen"
            @close="cameraOpen = false"
            @error="(msg) => { toast.push(msg, 'error'); cameraOpen = false; }"
        />

        <ShiftModal
            v-if="shiftModalMode"
            :mode="shiftModalMode"
            :shift="shiftState.shift"
            @close="shiftModalMode = null"
            @changed="fetchActiveShift"
        />

        <!-- Modal pembayaran -->
        <div v-if="paymentOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closePayment">
            <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl dark:bg-slate-800">
                <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-slate-100">Pembayaran</h2>
                <p class="mb-4 text-sm text-gray-500 dark:text-slate-400">
                    Total: <span class="font-bold text-slate-900 dark:text-slate-100">{{ formatRupiah(total) }}</span>
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Metode</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="method in paymentMethods"
                                :key="method.value"
                                type="button"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                                :class="paymentMethod === method.value
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300'"
                                @click="paymentMethod = method.value"
                            >
                                {{ method.label }}
                            </button>
                        </div>
                    </div>

                    <BaseRupiahInput
                        v-if="paymentMethod === 'cash'"
                        v-model="paymentAmount"
                        label="Uang Diterima"
                        autofocus
                        :error="((paymentAmount ?? 0) < total) ? 'Uang kurang dari total.' : ''"
                        @submit="confirmCheckout"
                    />

                    <div v-if="paymentMethod === 'cash' && (paymentAmount ?? 0) >= total" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        Kembalian: {{ formatRupiah((paymentAmount ?? 0) - total) }}
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <BaseButton variant="secondary" @click="closePayment">Batal</BaseButton>
                    <BaseButton
                        variant="primary"
                        :loading="processing"
                        :disabled="paymentMethod === 'cash' && ((paymentAmount ?? 0) < total)"
                        @click="confirmCheckout"
                    >
                        Selesaikan
                    </BaseButton>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';
import BaseRupiahInput from '../components/BaseRupiahInput.vue';
import { useToast } from '../composables/useToast';
import { useAuth } from '../auth';
import { useSyncStatus } from '../composables/useSyncStatus';
import { db } from '../pos/db';
import { checkout } from '../pos/checkout';
import { kickSync, pullCatalog } from '../pos/sync';
import { onScan, isCameraSupported } from '../pos/scanner';
import { printReceipt, getPrintSettings } from '../pos/printer';
import PrinterSettingsModal from '../components/pos/PrinterSettingsModal.vue';
import ScannerSettingsModal from '../components/pos/ScannerSettingsModal.vue';
import CameraScanOverlay from '../components/pos/CameraScanOverlay.vue';
import ShiftModal from '../components/pos/ShiftModal.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useShift } from '../composables/useShift';

const toast = useToast();
const catalogError = ref('');
const { state: shiftState, fetchActive: fetchActiveShift } = useShift();
const { state: authState } = useAuth();

// Kasir aktif + jam realtime untuk tampilan header POS.
const cashierName = computed(() => authState.user?.name ?? 'Kasir');

const clock = ref('');
let clockTimer = null;

function updateClock() {
    const now = new Date();
    clock.value = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }) + ' • ' + now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
}

const printSettingsOpen = ref(false);
const scannerSettingsOpen = ref(false);
const cameraOpen = ref(false);
const shiftModalMode = ref(null);
const cameraSupported = isCameraSupported();
const reloading = ref(false);

const products = ref([]);
const loading = ref(true);
const scanQuery = ref('');
const scanInput = ref(null);

// Modal cari produk (katalog pindah ke modal supaya halaman fokus transaksi).
const productSearchOpen = ref(false);
const productSearchQuery = ref('');

const cart = ref([]);
const discount = ref(0);
// PPN toggle: centang = 10%, hapus centang = 0% (tanpa ketik manual).
const taxEnabled = ref(false);
const taxPercent = computed(() => (taxEnabled.value ? 10 : 0));
const clearCartDialog = ref(false);

const paymentOpen = ref(false);
const processing = ref(false);
const paymentMethod = ref('cash');
const paymentAmount = ref(0);

const paymentMethods = [
    { value: 'cash', label: 'Tunai' },
    { value: 'qris', label: 'QRIS' },
    { value: 'debit', label: 'Debit' },
    { value: 'transfer', label: 'Transfer' },
];

const filteredProducts = computed(() => {
    const q = (productSearchOpen.value ? productSearchQuery.value : scanQuery.value).trim().toLowerCase();
    if (!q) return products.value;
    return products.value.filter((p) =>
        p.name.toLowerCase().includes(q)
        || (p.sku ?? '').toLowerCase().includes(q)
        || (p.barcode ?? '').includes(q),
    );
});

function openProductSearch() {
    productSearchQuery.value = '';
    productSearchOpen.value = true;
}

function closeProductSearch() {
    productSearchOpen.value = false;
    scanInput.value?.focus();
}

/** Enter di modal cari → tambah hasil teratas ke keranjang. */
function pickFirstResult() {
    const product = filteredProducts.value[0];
    if (product) {
        addToCart(product);
        productSearchQuery.value = '';
    }
}

const cartCount = computed(() => cart.value.reduce((n, i) => n + i.qty, 0));
const subtotal = computed(() => cart.value.reduce((n, i) => n + Number(i.subtotal), 0));
// PPN dihitung dari (subtotal - diskon), persen 0-100, dibatasi ≥ 0.
const taxAmount = computed(() => {
    const base = Math.max(0, subtotal.value - (discount.value || 0));
    const pct = Math.min(100, Math.max(0, Number(taxPercent.value) || 0));
    return round(base * pct / 100);
});
const total = computed(() => Math.max(0, round(subtotal.value - (discount.value || 0) + taxAmount.value)));

/** Muat katalog offline-first: refresh server (bila online) → baca IDB. */
async function loadCatalog() {
    loading.value = true;
    catalogError.value = '';

    try {
        if (navigator.onLine !== false) {
            await pullCatalog();
        }
    } catch (err) {
        // Tampilkan penyebab (mis. sesi berakhir) tapi tetap lanjut ke IDB.
        if (err?.response?.status === 401 || err?.response?.status === 419) {
            catalogError.value = 'Sesi berakhir. Muat ulang halaman untuk login kembali.';
        } else if (err?.response) {
            catalogError.value = `Katalog gagal dimuat dari server (${err.response.status}). Menampilkan data lokal.`;
        } else {
            catalogError.value = 'Server tidak terjangkau. Menampilkan data lokal.';
        }
    }

    try {
        // orderBy('name') butuh index 'name'; fallback toArray bila gagal.
        products.value = await db.products.orderBy('name').toArray();
    } catch {
        try {
            products.value = (await db.products.toArray())
                .sort((a, b) => String(a.name).localeCompare(String(b.name)));
        } catch {
            products.value = [];
            catalogError.value = 'Database lokal tidak dapat dibaca.';
        }
    } finally {
        loading.value = false;
    }
}

function addToCart(product) {
    if (product.stock <= 0) {
        toast.push(`Stok ${product.name} habis.`, 'error');
        return;
    }
    const existing = cart.value.find((i) => i.product_id === product.id);
    if (existing) {
        if (existing.qty >= product.stock) {
            toast.push(`Stok ${product.name} hanya ${product.stock}.`, 'error');
            return;
        }
        existing.qty += 1;
        existing.maxQty = product.stock;
        existing.subtotal = round(existing.qty * existing.price);
    } else {
        cart.value.push({
            product_id: product.id,
            sku: product.sku ?? '',
            barcode: product.barcode ?? '',
            name: product.name,
            price: Number(product.price),
            qty: 1,
            maxQty: product.stock,
            discount: 0,
            subtotal: Number(product.price),
        });
    }

    // Setelah klik produk, fokus kembali ke kolom scan agar
    // scanner/ketik barcode berikutnya langsung siap.
    scanInput.value?.focus();
}

/** Set qty keranjang dari input manual atau tombol +/-, dengan validasi stok. */
function setQty(item, value) {
    const qty = Math.floor(Number(value));
    if (!Number.isFinite(qty) || qty < 1) {
        item.qty = 1;
    } else if (qty > item.maxQty) {
        item.qty = item.maxQty;
        toast.push(`Stok ${item.name} hanya ${item.maxQty}.`, 'error');
    } else {
        item.qty = qty;
    }
    item.subtotal = round(item.qty * item.price - (item.discount || 0));
}

function removeItem(item) {
    cart.value = cart.value.filter((i) => i !== item);
    toast.push(`${item.name} dihapus dari keranjang.`, 'info');
}

/** Kosongkan seluruh keranjang — pakai dialog in-app agar tampil di fullscreen. */
function clearCart() {
    if (cart.value.length === 0) return;
    clearCartDialog.value = true;
}

function onClearCartConfirmed() {
    clearCartDialog.value = false;
    cart.value = [];
    discount.value = 0;
    toast.push('Keranjang dikosongkan.', 'info');
}

function findByBarcode(code) {
    const q = String(code).trim().toLowerCase();
    return products.value.find(
        (p) => (p.barcode ?? '') === q || (p.sku ?? '').toLowerCase() === q,
    );
}

function onScanEnter() {
    const q = scanQuery.value.trim();
    resetScanBurst();

    // Keranjang berisi + Enter di kolom kosong → buka pembayaran.
    if (!q) {
        if (cart.value.length > 0) {
            openPayment();
        }
        return;
    }

    const product = findByBarcode(q);
    if (product) {
        addToCart(product);
        scanQuery.value = '';
    } else if (isRecentScannerBurst()) {
        // Hasil scan (bukan ketikan manusia) yang tak dikenali →
        // beri peringatan, kosongkan kolom, dan fokus lagi untuk scan berikutnya.
        toast.push(`Barcode ${q} tidak ditemukan.`, 'error');
        scanQuery.value = '';
        scanInput.value?.focus();
    } else {
        // Ketikan manual bukan barcode → buka modal cari produk.
        productSearchQuery.value = q;
        productSearchOpen.value = true;
    }
}

// Enter segera setelah burst scan (jeda < 1s) → perlakukan sebagai scan.
function isRecentScannerBurst() {
    return inputFastGaps >= 3 || (inputLastTime > 0 && performance.now() - inputLastTime < 1000 && (scanQuery.value.length >= 4));
}

// ===== Deteksi scan langsung di kolom input =====
// Scanner USB mengetik sangat cepat. Bila input cocok barcode dan
// ritmenya cepat (tanpa perlu Enter), item langsung masuk keranjang.
const SCAN_IDLE_MS = 150;
const SCAN_FAST_MS = 50;
let inputLastTime = 0;
let inputFastGaps = 0;
let inputTotalGaps = 0;
let scanIdleTimer = null;

function resetScanBurst() {
    inputLastTime = 0;
    inputFastGaps = 0;
    inputTotalGaps = 0;
    if (scanIdleTimer) {
        clearTimeout(scanIdleTimer);
        scanIdleTimer = null;
    }
}

function onScanInput(event) {
    // Abaikan penghapusan/pemilih teks; hanya ketikan/penempelan yang dihitung.
    const type = event?.inputType ?? '';
    if (type && !type.startsWith('insert')) {
        return;
    }

    const now = performance.now();
    const gap = now - inputLastTime;
    inputLastTime = now;

    if (gap > 1000) {
        // Jeda panjang → awal burst baru.
        inputFastGaps = 0;
        inputTotalGaps = 0;
    } else if (gap > 0) {
        inputTotalGaps += 1;
        if (gap <= SCAN_FAST_MS) inputFastGaps += 1;
    }

    if (scanIdleTimer) clearTimeout(scanIdleTimer);
    scanIdleTimer = setTimeout(finishScanBurst, SCAN_IDLE_MS);
}

function finishScanBurst() {
    scanIdleTimer = null;

    const code = scanQuery.value.trim();
    if (!code) {
        resetScanBurst();
        return;
    }

    // Pola scanner: minimal 3 jeda cepat dan mayoritasnya cepat.
    const isScannerBurst = inputFastGaps >= 3 && inputTotalGaps > 0 && inputFastGaps / inputTotalGaps >= 0.5;
    if (!isScannerBurst) {
        resetScanBurst();
        return;
    }

    const product = findByBarcode(code);
    resetScanBurst();

    if (product) {
        addToCart(product);
        scanQuery.value = '';
    } else {
        toast.push(`Barcode ${code} tidak ditemukan.`, 'error');
        scanQuery.value = '';
        scanInput.value?.focus();
    }
}

const offScan = onScan(handleScanCode);

// Scanner HID (mode opsional) mengirim hasil via CustomEvent.
function onHidScan(event) {
    handleScanCode(event.detail);
}
window.addEventListener('pos-scan', onHidScan);

/** Pusat penanganan kode barcode → keranjang. */
function handleScanCode(code) {
    const product = findByBarcode(code);
    if (product) {
        addToCart(product);
        scanQuery.value = '';
    } else {
        toast.push(`Barcode ${code} tidak ditemukan.`, 'error');
        scanQuery.value = '';
        scanInput.value?.focus();
    }
}

function openPayment() {
    paymentAmount.value = 0;
    paymentMethod.value = 'cash';
    paymentOpen.value = true;
}

function closePayment() {
    paymentOpen.value = false;
}

async function confirmCheckout() {
    // Guard: Enter saat uang kurang / kosong / metode non-cash kurangvalid
    // tidak memproses — sama dengan kondisi tombol disabled.
    if (cart.value.length === 0) return;
    if (paymentMethod.value === 'cash' && ((paymentAmount.value ?? 0) < total.value)) return;

    processing.value = true;

    try {
        const record = await checkout({
            cart: cart.value,
            discount: discount.value || 0,
            tax: taxAmount.value,
            paymentMethod: paymentMethod.value,
            paymentAmount: paymentMethod.value === 'cash'
                ? Number(paymentAmount.value ?? 0)
                : total.value,
        });

        toast.push(
            record.status === 'pending'
                ? `Transaksi ${record.receipt_no} tersimpan lokal, menunggu sinkronisasi.`
                : `Transaksi ${record.receipt_no} berhasil.`,
            'success',
        );

        const settings = getPrintSettings();

        printReceipt({
            receiptNo: record.receipt_no,
            cashier: null,
            items: cart.value.map((i) => ({
                name: i.name,
                qty: i.qty,
                price: i.price,
                discount: i.discount || 0,
                subtotal: i.subtotal,
            })),
            subtotal: record.subtotal,
            discount: record.discount,
            tax: record.tax,
            total: record.total,
            paymentMethod: record.payment_method,
            paymentAmount: record.payment_amount,
            change: record.change,
            footer: settings.footer,
        });

        cart.value = [];
        discount.value = 0;
        scanQuery.value = '';
        paymentOpen.value = false;

        await loadCatalog();
        kickSync();
    } catch (err) {
        toast.push(err?.message ?? 'Checkout gagal.', 'error');
    } finally {
        processing.value = false;
        scanInput.value?.focus();
    }
}

function formatRupiah(value) {
    return 'Rp ' + Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function round(value) {
    return Math.round(value * 100) / 100;
}

async function reloadCatalog() {
    reloading.value = true;
    try {
        await pullCatalog();
        try {
            products.value = await db.products.orderBy('name').toArray();
        } catch {
            products.value = (await db.products.toArray())
                .sort((a, b) => String(a.name).localeCompare(String(b.name)));
        }
        toast.push(`Katalog diperbarui (${products.value.length} produk).`, 'success');
    } catch (err) {
        if (err?.response?.status === 401 || err?.response?.status === 419) {
            toast.push('Sesi berakhir. Muat ulang halaman (F5) untuk login kembali.', 'error');
        } else {
            toast.push('Gagal menarik katalog dari server.', 'error');
        }
    } finally {
        reloading.value = false;
    }
}

onMounted(() => {
    loadCatalog();
    fetchActiveShift();
    scanInput.value?.focus();
    updateClock();
    clockTimer = setInterval(updateClock, 1000);
    window.addEventListener('keydown', handleGlobalEnter);
});

onBeforeUnmount(() => {
    offScan();
    window.removeEventListener('pos-scan', onHidScan);
    window.removeEventListener('keydown', handleGlobalEnter);
    resetScanBurst();
    if (clockTimer) clearInterval(clockTimer);
});

/**
 * Enter di mana pun (fokus tidak di input lain) membuka modal pembayaran,
 * asalkan keranjang berisi — kasir tinggal scan lalu Enter.
 */
function handleGlobalEnter(event) {
    if (event.key !== 'Enter') return;
    // Modal cari produk menangani Enter sendiri (pilih hasil teratas).
    if (productSearchOpen.value) return;
    // Biarkan input/textarea/modal menangani Enter mereka sendiri.
    const tag = event.target?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'BUTTON') return;
    if (event.ctrlKey || event.metaKey || event.altKey) return;

    if (paymentOpen.value || processing.value) return;
    if (cart.value.length > 0) {
        event.preventDefault();
        openPayment();
    }
}
</script>
