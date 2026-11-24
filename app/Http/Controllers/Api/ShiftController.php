<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /** Shift aktif user yang sedang login. */
    public function active(Request $request): JsonResponse
    {
        $shift = Shift::where('user_id', $request->user()->id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        return response()->json([
            'shift' => $shift,
        ]);
    }

    /**
     * Buka shift (online). Kasir nyatakan uang awal di laci.
     */
    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $existing = Shift::where('user_id', $request->user()->id)->whereNull('closed_at')->first();
        if ($existing) {
            return response()->json([
                'message' => 'Masih ada shift yang aktif. Tutup dulu shift sebelumnya.',
            ], 409);
        }

        $shift = Shift::create([
            'client_uuid' => $data['client_uuid'],
            'user_id' => $request->user()->id,
            'device_id' => $data['device_id'] ?? null,
            'opened_at' => now(),
            'opening_cash' => $data['opening_cash'],
        ]);

        return response()->json([
            'message' => 'Shift dibuka.',
            'shift' => $shift,
        ], 201);
    }

    /**
     * Tutup shift: hitung rekap transaksi selama shift, bandingkan
     * dengan uang fisik yang dihitung kasir → selisih.
     */
    public function close(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
        ]);

        if ($shift->closed_at) {
            return response()->json(['message' => 'Shift sudah ditutup.'], 409);
        }
        if ($shift->user_id !== $request->user()->id && !$request->user()->hasPermission('shifts.manage')) {
            return response()->json(['message' => 'Bukan shift Anda.'], 403);
        }

        $summary = $this->shiftSummary($shift);

        // Uang diharapkan = kas awal + semua penjualan tunai shift ini.
        $expected = (float) $shift->opening_cash + $summary['cash_total'];

        $shift->update([
            'closed_at' => now(),
            'closing_cash' => $data['closing_cash'],
            'expected_cash' => $expected,
        ]);

        return response()->json([
            'message' => 'Shift ditutup.',
            'shift' => $shift->fresh(),
            'summary' => $summary,
            'difference' => round($data['closing_cash'] - $expected, 2),
        ]);
    }

    /** Rekap sebuah shift (transaksi, omzet, per metode bayar). */
    public function show(Request $request, Shift $shift): JsonResponse
    {
        $summary = $this->shiftSummary($shift);

        return response()->json([
            'shift' => $shift->load('user:id,name'),
            'summary' => $summary,
        ]);
    }

    private function shiftSummary(Shift $shift): array
    {
        $transactions = Transaction::where('shift_id', $shift->id)->get();

        $byMethod = $transactions
            ->groupBy('payment_method')
            ->map(fn ($rows) => [
                'count' => $rows->count(),
                'total' => (float) $rows->sum('total'),
            ]);

        return [
            'transaction_count' => $transactions->count(),
            'gross_total' => (float) $transactions->sum('total'),
            'discount_total' => (float) $transactions->sum('discount'),
            'cash_total' => (float) $transactions->where('payment_method', 'cash')->sum('total'),
            'by_method' => $byMethod,
        ];
    }
}
