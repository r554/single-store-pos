<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftReportTest extends TestCase
{
    use RefreshDatabase;

    private function cashier(): User
    {
        foreach (['pos.sell'] as $slug) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'test']);
        }

        $role = Role::create(['name' => 'Kasir', 'slug' => 'kasir-'.uniqid()]);
        $role->permissions()->sync(Permission::whereIn('slug', ['pos.sell'])->pluck('id'));

        $user = User::create([
            'name' => 'Kasir',
            'email' => uniqid().'@example.test',
            'password' => 'SI_SECRET_PASSWORD_TRD6JBFV',
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::withoutGlobalScope('active')->create(array_merge([
            'name' => 'Produk '.uniqid(),
            'price' => 10000,
            'stock' => 50,
        ], $attrs));
    }

    public function test_buka_dan_tutup_shift_dengan_selisih_kas(): void
    {
        $user = $this->cashier();
        $this->actingAs($user);

        $open = $this->postJson('/api/pos/shifts', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'opening_cash' => 100000,
        ]);
        $open->assertCreated();
        $shiftId = $open->json('shift.id');

        // Dua shift bersamaan ditolak.
        $this->postJson('/api/pos/shifts', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'opening_cash' => 0,
        ])->assertStatus(409);

        // Transaksi tunai 20.000 selama shift.
        $products = [$this->makeProduct(), $this->makeProduct()];
        $this->postJson('/api/pos/transactions', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'shift_id' => $shiftId,
            'payment_method' => 'cash',
            'payment_amount' => 20000,
            'items' => [
                ['product_id' => $products[0]->id, 'qty' => 1],
                ['product_id' => $products[1]->id, 'qty' => 1],
            ],
        ])->assertCreated();

        // Kas física 119.000 → selisih -1.000 (diharapkan 120.000).
        $close = $this->postJson("/api/pos/shifts/{$shiftId}/close", [
            'closing_cash' => 119000,
        ]);
        $close->assertOk()
            ->assertJsonPath('summary.transaction_count', 1)
            ->assertJsonPath('summary.cash_total', fn ($v) => abs($v - 20000) < 0.01)
            ->assertJsonPath('shift.expected_cash', fn ($v) => abs($v - 120000) < 0.01)
            ->assertJsonPath('difference', fn ($v) => abs($v + 1000) < 0.01);

        // Shift sudah ditutup → 409.
        $this->postJson("/api/pos/shifts/{$shiftId}/close", [
            'closing_cash' => 0,
        ])->assertStatus(409);
    }

    public function test_laporan_merangkum_transaksi(): void
    {
        $user = $this->cashier();
        $this->actingAs($user);

        $product = $this->makeProduct(['price' => 5000]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/pos/transactions', [
                'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'payment_method' => 'cash',
                'payment_amount' => 10000,
                'items' => [['product_id' => $product->id, 'qty' => 2]],
            ])->assertCreated();
        }

        $res = $this->getJson('/api/pos/reports');
        $res->assertOk()
            ->assertJsonPath('summary.transaction_count', 3)
            ->assertJsonPath('summary.gross_total', fn ($v) => abs($v - 30000) < 0.01)
            ->assertJsonPath('top_products.0.qty', 6);

        // Filter kosong: rentang mengembalikan jumlah sama.
        $res2 = $this->getJson('/api/pos/reports?from=' . now()->toDateString() . '&to=' . now()->toDateString());
        $res2->assertOk()->assertJsonPath('summary.transaction_count', 3);
    }

    public function test_user_lain_tidak_bisa_menutup_shift(): void
    {
        $owner = $this->cashier();
        $this->actingAs($owner);

        $open = $this->postJson('/api/pos/shifts', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'opening_cash' => 50000,
        ]);
        $shiftId = $open->json('shift.id');

        // Kasir lain.
        $other = $this->cashier();
        $this->actingAs($other);

        $this->postJson("/api/pos/shifts/{$shiftId}/close", [
            'closing_cash' => 0,
        ])->assertStatus(403);
    }
}
