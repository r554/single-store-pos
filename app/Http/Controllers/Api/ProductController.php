<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'with_inactive' => ['nullable', 'boolean'],
        ]);

        $products = Product::withoutGlobalScope('active')
            ->with('category:id,name')
            ->when(
                $data['with_inactive'] ?? false,
                fn ($q) => $q,
                fn ($q) => $q->where('is_active', true)
            )
            ->when($data['search'] ?? null, function ($q) use ($data) {
                $s = '%' . $data['search'] . '%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $s)
                    ->orWhere('sku', 'like', $s)
                    ->orWhere('barcode', 'like', $s));
            })
            ->when($data['category_id'] ?? null, fn ($q) => $q->where('category_id', $data['category_id']))
            ->orderBy('name')
            ->paginate(50);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $product = Product::withoutGlobalScope('active')->create($data);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'product' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request, $product->id);

        $product->fill($data)->save();

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'product' => $product->fresh(),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return response()->json([
            'message' => 'Produk dinonaktifkan (soft delete).',
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = fn ($column) => [
            'nullable', 'string', 'max:100',
            Rule::unique('products', $column)->ignore($ignoreId),
        ];

        return $request->validate([
            'sku' => $unique('sku'),
            'barcode' => $unique('barcode'),
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer'],
            'stock_min' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
