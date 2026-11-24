<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Katalog untuk mode offline kasir.
     *
     * - Tanpa parameter: snapshot penuh (produk aktif + kategori).
     * - ?updated_since=<cursor>: delta perubahan sejak cursor.
     * Response selalu menyertakan cursor baru untuk iterasi berikutnya.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'updated_since' => ['nullable', 'date'],
        ]);

        $since = $request->date('updated_since');

        // Tanpa global scope: produk nonaktif tetap dikirim sebagai marker
        // delete supaya klien offline tahu harus menghapus dari IDB-nya.
        $products = Product::withoutGlobalScope('active')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('updated_at')
            ->get(['id', 'sku', 'barcode', 'name', 'category_id', 'price', 'stock', 'is_active', 'updated_at']);

        $categories = Category::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('updated_at')
            ->get(['id', 'name', 'updated_at']);

        // Cursor = updated_at terakhir yang terkirim; query pakai >=
        // (idempoten di klien via IDB put) sehingga record pada detik
        // yang sama tidak pernah terlewat (presisi DB berbeda-beda).
        $productCursor = $products->last()?->updated_at;
        $categoryCursor = $categories->last()?->updated_at;
        $cursor = collect([$productCursor, $categoryCursor])
            ->filter()
            ->max()
            ?? now()->toIso8601String();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'cursor' => optional($cursor, fn ($c) => $c->toIso8601String()) ?? $cursor,
        ]);
    }
}
