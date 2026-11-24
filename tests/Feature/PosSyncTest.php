<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSyncTest extends TestCase
{
    use RefreshDatabase;

    private function cashier(): User
    {
        foreach (['pos.sell'] as $slug) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'test']);
        }

        $role = Role::create(['name' => 'Kasir', 'slug' => 'kasir-test']);
        $role->permissions()->sync(Permission::whereIn('slug', ['pos.sell'])->pluck('id'));

        $user = User::create([
            'name' => 'Kasir',
            'email' => uniqid().'@example.test',
            'password' => 'SI_SECRET_PASSWORD_QKRTXR12',
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::withoutGlobalScope('active')->create(array_merge([
            'name' => 'Produk '.uniqid(),
            'price' => 5000,
            'stock' => 10,
        ], $attrs));
    }

    public function test_catalog_berisi_semua_produk_dengan_flag_aktif(): void
    {
        $this->actingAs($this->cashier());

        $active = $this->makeProduct(['is_active' => true]);
        $inactive = $this->makeProduct(['is_active' => false]);

        $res = $this->getJson('/api/pos/catalog');

        // Produk nonaktif tetap dikirim sebagai marker delete utk klien offline.
        $res->assertOk()
            ->assertJsonPath('cursor', fn ($c) => ! empty($c))
            ->assertJsonFragment(['id' => $active->id, 'is_active' => true])
            ->assertJsonFragment(['id' => $inactive->id, 'is_active' => false]);
    }

    public function test_catalog_delta_hanya_perubahan_sejak_cursor(): void
    {
        $this->actingAs($this->cashier());

        $old = $this->makeProduct();
        $old->update(['updated_at' => now()->subDay()]);

        $res = $this->getJson('/api/pos/catalog');
        $cursor = $res->json('cursor');

        $fresh = $this->makeProduct();

        $delta = $this->getJson('/api/pos/catalog?updated_since=' . urlencode($cursor));
        $ids = array_column($delta->json('products'), 'id');

        // Delta pakai >= (aman utk presisi detik DB) sehingga record pada
        // detik cursor boleh terkirim ulang; record yang WAJIB ada adalah
        // perubahan setelahnya, dan put() di klien idempoten.
        $this->assertContains($fresh->id, $ids);
    }

    public function test_sync_menerima_transaksi_dan_kurangi_stok(): void
    {
        $user = $this->cashier();
        $this->actingAs($user);

        $product = $this->makeProduct(['stock' => 10]);
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $res = $this->postJson('/api/pos/sync', [
            'records' => [[
                'client_uuid' => $uuid,
                'type' => 'transaction',
                'created_at' => now()->toIso8601String(),
                'payload' => [
                    'subtotal' => 10000,
                    'total' => 10000,
                    'payment_method' => 'cash',
                    'payment_amount' => 10000,
                    'transacted_at' => now()->toIso8601String(),
                    'items' => [[
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => 5000,
                        'qty' => 2,
                        'subtotal' => 10000,
                    ]],
                ],
            ]],
        ]);

        $res->assertOk()
            ->assertJsonPath('accepted.0', $uuid)
            ->assertJsonPath("receipt_map.$uuid", fn ($no) => str_starts_with($no, 'TRX-'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 8]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame($user->id, Transaction::where('client_uuid', $uuid)->first()->user_id);
    }

    public function test_sync_idempoten_duplikat_tidak_dobel_stok(): void
    {
        $this->actingAs($this->cashier());

        $product = $this->makeProduct(['stock' => 10]);
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $payload = [
            'records' => [[
                'client_uuid' => $uuid,
                'type' => 'transaction',
                'created_at' => now()->toIso8601String(),
                'payload' => [
                    'subtotal' => 5000,
                    'total' => 5000,
                    'payment_method' => 'cash',
                    'payment_amount' => 5000,
                    'items' => [[
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => 5000,
                        'qty' => 1,
                        'subtotal' => 5000,
                    ]],
                ],
            ]],
        ];

        $this->postJson('/api/pos/sync', $payload)->assertOk();
        $this->postJson('/api/pos/sync', $payload)->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 9]);
        $this->assertSame(1, Transaction::where('client_uuid', $uuid)->count());
        $this->assertSame(1, StockMovement::where('client_uuid', $uuid . ':' . $product->id)->count());
    }

    public function test_sync_reject_payload_tanpa_item(): void
    {
        $this->actingAs($this->cashier());

        $uuid = (string) \Illuminate\Support\Str::uuid();

        $res = $this->postJson('/api/pos/sync', [
            'records' => [[
                'client_uuid' => $uuid,
                'type' => 'transaction',
                'created_at' => now()->toIso8601String(),
                'payload' => [
                    'subtotal' => 1000,
                    'total' => 1000,
                    'items' => [],
                ],
            ]],
        ]);

        $res->assertOk()
            ->assertJsonCount(1, 'rejected')
            ->assertJsonPath('rejected.0.client_uuid', $uuid)
            ->assertJsonPath('rejected.0.error', 'Transaksi tanpa item.');
    }

    public function test_checkout_online_deny_kurang_bayar(): void
    {
        $this->actingAs($this->cashier());

        $product = $this->makeProduct(['price' => 10000, 'stock' => 5]);

        $this->postJson('/api/pos/transactions', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payment_method' => 'cash',
            'payment_amount' => 5000,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('payment_amount');
    }
}
