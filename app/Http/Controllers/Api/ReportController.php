<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\JsonRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Ringkasan penjualan + grafik harian + produk terlaris.
     * Query param: from, to (default: 30 hari terakhir).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($data['from'])
            ? \Illuminate\Support\Carbon::parse($data['from'])->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = isset($data['to'])
            ? \Illuminate\Support\Carbon::parse($data['to'])->endOfDay()
            : now()->endOfDay();

        $base = Transaction::whereBetween('transacted_at', [$from, $to]);

        $summary = [
            'transaction_count' => (clone $base)->count(),
            'gross_total' => (clone $base)->sum('total'),
            'discount_total' => (clone $base)->sum('discount'),
            'avg_total' => (clone $base)->avg('total'),
        ];

        // Grafik per hari.
        $daily = (clone $base)
            ->selectRaw("date(transacted_at) as day, count(*) as cnt, sum(total) as total")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Per metode bayar.
        $byMethod = (clone $base)
            ->selectRaw("payment_method, count(*) as cnt, sum(total) as total")
            ->groupBy('payment_method')
            ->get();

        // Per kasir.
        $byCashier = (clone $base)
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->selectRaw("users.name as cashier, count(*) as cnt, sum(transactions.total) as total")
            ->groupBy('cashier')
            ->orderByDesc('total')
            ->get();

        // Produk terlaris (berdasarkan qty dari transaction_items).
        $topProducts = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->whereBetween('transacted_at', [$from, $to])
            ->selectRaw("transaction_items.name, sum(transaction_items.qty) as qty, sum(transaction_items.subtotal) as revenue")
            ->groupBy('transaction_items.name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'summary' => $summary,
            'daily' => $daily,
            'by_method' => $byMethod,
            'by_cashier' => $byCashier,
            'top_products' => $topProducts,
        ]);
    }
}
