<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Jual di Kasir', 'slug' => 'pos.sell', 'group' => 'Kasir'],
            ['name' => 'Lihat Produk', 'slug' => 'products.view', 'group' => 'Produk'],
            ['name' => 'Tambah Produk', 'slug' => 'products.create', 'group' => 'Produk'],
            ['name' => 'Ubah Produk', 'slug' => 'products.update', 'group' => 'Produk'],
            ['name' => 'Hapus Produk', 'slug' => 'products.delete', 'group' => 'Produk'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        // Superadmin mendapat semua permission baru.
        Role::where('slug', 'superadmin')->first()?->permissions()->sync(Permission::pluck('id'));

        $kasirRole = Role::updateOrCreate(
            ['slug' => 'kasir'],
            [
                'name' => 'Kasir',
                'description' => 'Melayani penjualan di halaman kasir.',
                'is_system' => true,
            ]
        );
        $kasirRole->permissions()->sync(
            Permission::whereIn('slug', ['pos.sell', 'products.view'])->pluck('id')
        );

        $kasir = User::updateOrCreate(
            ['email' => 'kasir@example.com'],
            [
                'name' => 'Kasir Demo',
                'phone' => '081200000001',
                'password' => Hash::make('Kasir123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $kasir->roles()->syncWithoutDetaching([$kasirRole->id]);

        // Katalog contoh.
        $categories = [
            'Minuman' => Category::updateOrCreate(['name' => 'Minuman'])->id,
            'Makanan' => Category::updateOrCreate(['name' => 'Makanan'])->id,
            'Snack' => Category::updateOrCreate(['name' => 'Snack'])->id,
        ];

        $products = [
            ['sku' => 'MNM-001', 'barcode' => '8991001', 'name' => 'Air Mineral 600ml', 'category' => 'Minuman', 'price' => 4000, 'cost' => 2500, 'stock' => 100],
            ['sku' => 'MNM-002', 'barcode' => '8991002', 'name' => 'Kopi Susu Botol', 'category' => 'Minuman', 'price' => 12000, 'cost' => 8500, 'stock' => 50],
            ['sku' => 'MNM-003', 'barcode' => '8991003', 'name' => 'Teh Kotak 250ml', 'category' => 'Minuman', 'price' => 5000, 'cost' => 3500, 'stock' => 80],
            ['sku' => 'MKN-001', 'barcode' => '8992001', 'name' => 'Roti Tawar', 'category' => 'Makanan', 'price' => 15000, 'cost' => 11000, 'stock' => 30],
            ['sku' => 'MKN-002', 'barcode' => '8992002', 'name' => 'Mie Instan Goreng', 'category' => 'Makanan', 'price' => 3500, 'cost' => 2700, 'stock' => 120],
            ['sku' => 'MKN-003', 'barcode' => '8992003', 'name' => 'Beras 5kg', 'category' => 'Makanan', 'price' => 62000, 'cost' => 55000, 'stock' => 20],
            ['sku' => 'SNK-001', 'barcode' => '8993001', 'name' => 'Keripik Kentang 68g', 'category' => 'Snack', 'price' => 9000, 'cost' => 7000, 'stock' => 60],
            ['sku' => 'SNK-002', 'barcode' => '8993002', 'name' => 'Coklat Batang', 'category' => 'Snack', 'price' => 11000, 'cost' => 8500, 'stock' => 45],
            ['sku' => 'SNK-003', 'barcode' => '8993003', 'name' => 'Biskuit Kaleng', 'category' => 'Snack', 'price' => 25000, 'cost' => 19500, 'stock' => 25],
        ];

        foreach ($products as $p) {
            Product::withoutGlobalScope('active')->updateOrCreate(
                ['sku' => $p['sku']],
                [
                    'barcode' => $p['barcode'],
                    'name' => $p['name'],
                    'category_id' => $categories[$p['category']],
                    'price' => $p['price'],
                    'cost' => $p['cost'],
                    'stock' => $p['stock'],
                    'stock_min' => 5,
                    'is_active' => true,
                ]
            );
        }
    }
}
