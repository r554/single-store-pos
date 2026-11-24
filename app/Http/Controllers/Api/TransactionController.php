<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Checkout online (Fase 1): satu transaksi langsung ke server,
     * server-side pricing untuk akurasi. Dipakai saat online;
     * jalur offline pakai SyncController via outbox.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'device_id' => ['nullable', 'string', 'max:50'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,debit,credit,qris,transfer'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (Transaction::where('client_uuid', $data['client_uuid'])->exists()) {
            return response()->json([
                'message' => 'Transaksi sudah pernah diproses.',
            ], 409);
        }

        // Pre-check harga (server-side) sebelum DB transaction:
        // cash kurang bayar → 422, bukan exception di dalam transaction (500).
        $productPrices = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
            ->pluck('price', 'id');
        $subtotal = collect($data['items'])->sum(function ($item) use ($productPrices) {
            return round($item['qty'] * $productPrices[$item['product_id']] - ($item['discount'] ?? 0), 2);
        });

        if ($data['payment_method'] === 'cash' && $data['payment_amount'] < $subtotal) {
            return response()->json([
                'message' => 'Uang bayar kurang dari total tagihan.',
                'errors' => [
                    'payment_amount' => ['Jumlah bayar kurang dari total.'],
                ],
            ], 422);
        }

        $result = DB::transaction(function () use ($data, $request) {
            $subtotal = 0;
            $rows = [];

            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $lineDiscount = (float) ($item['discount'] ?? 0);
                $lineSubtotal = round($product->price * $item['qty'] - $lineDiscount, 2);
                $subtotal += $lineSubtotal;

                $rows[] = [
                    'product' => $product,
                    'qty' => $item['qty'],
                    'discount' => $lineDiscount,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $discount = (float) ($data['discount'] ?? 0);
            $taxRate = (float) ($data['tax'] ?? 0); // persen
            $afterDiscount = max(0, $subtotal - $discount);
            $tax = round($afterDiscount * $taxRate / 100, 2);
            $total = round($afterDiscount + $tax, 2);

            if ($data['payment_amount'] < $total && $data['payment_method'] === 'cash') {
                throw new \InvalidArgumentException('Jumlah bayar kurang dari total.');
            }

            $transaction = Transaction::create([
                'client_uuid' => $data['client_uuid'],
                'shift_id' => $data['shift_id'] ?? null,
                'user_id' => $request->user()->id,
                'device_id' => $data['device_id'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'payment_amount' => $data['payment_amount'],
                'change' => max(0, round($data['payment_amount'] - $total, 2)),
                'transacted_at' => now(),
                'synced_at' => now(),
            ]);

            foreach ($rows as $row) {
                $product = $row['product'];

                $transaction->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'qty' => $row['qty'],
                    'discount' => $row['discount'],
                    'subtotal' => $row['subtotal'],
                ]);

                $after = $product->stock - $row['qty'];
                $product->update(['stock' => $after]);

                StockMovement::create([
                    'client_uuid' => $data['client_uuid'] . ':' . $product->id,
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'qty_change' => -$row['qty'],
                    'stock_after' => $after,
                    'type' => 'out',
                    'note' => 'Penjualan online ' . $data['client_uuid'],
                ]);
            }

            $receiptNo = 'TRX-' . now()->format('ymd') . '-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
            $transaction->update(['receipt_no' => $receiptNo]);

            return $transaction->load('items');
        });

        return response()->json([
            'message' => 'Transaksi berhasil.',
            'transaction' => $result,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $transactions = Transaction::with('items', 'user:id,name')
            ->when($data['from'] ?? null, fn ($q) => $q->where('transacted_at', '>=', $data['from']))
            ->when($data['to'] ?? null, fn ($q) => $q->where('transacted_at', '<=', $data['to']))
            ->latest('transacted_at')
            ->paginate(50);

        return response()->json($transactions);
    }
}
