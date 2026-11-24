<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\StockMovement;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Endpoint push batch dari outbox offline.
     *
     * Idempoten: client_uuid yang sudah ada di-skip (dikembalikan ke
     * daftar accepted supaya klien menghapus dari antrian).
     * Setiap transaksi diproses dalam DB transaction + lock produk
     * supaya stok konsisten meski dua kasir sync bersamaan.
     */
    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'records' => ['required', 'array', 'max:50'],
            'records.*.client_uuid' => ['required', 'uuid'],
            'records.*.type' => ['required', 'in:transaction,shift_open,shift_close'],
            'records.*.payload' => ['required', 'array'],
            'records.*.created_at' => ['required', 'date'],
        ]);

        $accepted = [];
        $rejected = [];

        foreach ($data['records'] as $record) {
            try {
                $result = $this->process($record, $request->user());
                $accepted[] = $record['client_uuid'];
                if ($result['receipt_no'] ?? null) {
                    $receiptMap[$record['client_uuid']] = $result['receipt_no'];
                }
            } catch (\InvalidArgumentException $e) {
                // Validasi payload gagal: tolak record ini saja.
                $rejected[] = [
                    'client_uuid' => $record['client_uuid'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'accepted' => $accepted,
            'rejected' => $rejected,
            'receipt_map' => $receiptMap ?? [],
        ]);
    }

    private function process(array $record, $user): array
    {
        return DB::transaction(function () use ($record, $user) {
            // Idempoten: record pernah masuk → dianggap sukses.
            if (Transaction::where('client_uuid', $record['client_uuid'])->exists()
                || Shift::where('client_uuid', $record['client_uuid'])->exists()) {
                return [];
            }

            return match ($record['type']) {
                'transaction' => $this->processTransaction($record, $user),
                'shift_open' => $this->processShiftOpen($record, $user),
                'shift_close' => $this->processShiftClose($record, $user),
            };
        });
    }

    private function processTransaction(array $record, $user): array
    {
        $p = $record['payload'];
        $items = $p['items'] ?? [];

        if (count($items) === 0) {
            throw new \InvalidArgumentException('Transaksi tanpa item.');
        }

        // shift_id dari klien bisa berupa id shift server (transaksi online
        // setelah shift tersinkron) atau client_uuid shift lokal (offline).
        $shiftId = $p['shift_id'] ?? null;
        if ($shiftId !== null) {
            $resolved = Shift::where('client_uuid', $shiftId)->value('id');
            if ($resolved !== null) {
                $shiftId = $resolved;
            } elseif (!is_numeric($shiftId)) {
                // Shift lokal belum tersinkron: transaksi masuk tanpa shift,
                // tetap konsisten (laporan berbasis transacted_at pun lengkap).
                $shiftId = null;
            }
        }

        $transaction = Transaction::create([
            'client_uuid' => $record['client_uuid'],
            'shift_id' => $shiftId,
            'user_id' => $user->id,
            'device_id' => $p['device_id'] ?? null,
            'subtotal' => $p['subtotal'],
            'discount' => $p['discount'] ?? 0,
            'tax' => $p['tax'] ?? 0,
            'total' => $p['total'],
            'payment_method' => $p['payment_method'] ?? 'cash',
            'payment_amount' => $p['payment_amount'] ?? 0,
            'change' => $p['change'] ?? 0,
            'transacted_at' => $p['transacted_at'] ?? $record['created_at'],
            'synced_at' => now(),
        ]);

        foreach ($items as $item) {
            $product = Product::withoutGlobalScope('active')
                ->whereKey($item['product_id'] ?? null)
                ->lockForUpdate()
                ->first();

            $transaction->items()->create([
                'product_id' => $product->id ?? null,
                'name' => $item['name'],
                'price' => $item['price'],
                'qty' => $item['qty'],
                'discount' => $item['discount'] ?? 0,
                'subtotal' => $item['subtotal'],
            ]);

            if ($product) {
                $after = $product->stock - (int) $item['qty'];
                $product->update(['stock' => $after]);

                StockMovement::create([
                    'client_uuid' => $record['client_uuid'] . ':' . $item['product_id'],
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'qty_change' => -(int) $item['qty'],
                    'stock_after' => $after,
                    'type' => 'out',
                    'note' => 'Penjualan ' . $record['client_uuid'],
                ]);
            }
        }

        // Nomor struk final diberikan server (berurutan).
        $receiptNo = 'TRX-' . now()->format('ymd') . '-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
        $transaction->update(['receipt_no' => $receiptNo]);

        return ['receipt_no' => $receiptNo];
    }

    private function processShiftOpen(array $record, $user): array
    {
        $p = $record['payload'];

        $shift = Shift::create([
            'client_uuid' => $record['client_uuid'],
            'user_id' => $user->id,
            'device_id' => $p['device_id'] ?? null,
            'opened_at' => $p['opened_at'] ?? $record['created_at'],
            'opening_cash' => $p['opening_cash'] ?? 0,
        ]);

        return ['shift_id' => $shift->id];
    }

    private function processShiftClose(array $record, $user): array
    {
        $p = $record['payload'];
        $shift = Shift::where('client_uuid', $p['shift_uuid'] ?? '')->first();

        if (!$shift) {
            throw new \InvalidArgumentException('Shift tidak ditemukan.');
        }

        $shift->update([
            'closed_at' => $p['closed_at'] ?? now(),
            'closing_cash' => $p['closing_cash'] ?? null,
            'expected_cash' => $p['expected_cash'] ?? null,
        ]);

        return [];
    }
}
